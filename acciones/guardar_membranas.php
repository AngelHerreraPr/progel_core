<?php
// guardar_membranas.php - Guardado independiente por etapa de Membranas
session_start();
date_default_timezone_set('America/Mazatlan');

header('Content-Type: application/json; charset=utf-8');

include_once __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../config/db_v2.php';

// Leer datos (soporta JSON payload o POST directo)
$inputRaw = file_get_contents('php://input');
$data = json_decode($inputRaw, true);
if (!$data && !empty($_POST)) {
    $data = $_POST;
}

if (!$data) {
    echo json_encode([
        'success' => false,
        'message' => 'No se recibieron datos para procesar.'
    ]);
    exit();
}

$accion = $data['accion'] ?? 'guardar';

// ACCIÓN: CONSULTAR LECTURAS PREVIAS
if ($accion === 'consultar') {
    $fecha = $data['fecha'] ?? date('Y-m-d');
    $etapa = isset($data['etapa']) ? intval($data['etapa']) : null;
    $hora = $data['hora'] ?? null;

    $dbsToCheck = [];
    if (isset($conn_v2) && $conn_v2) $dbsToCheck[] = $conn_v2;
    if (isset($conn) && $conn) $dbsToCheck[] = $conn;

    $lecturas = [];
    foreach ($dbsToCheck as $c) {
        $where = "WHERE fecha = '" . mysqli_real_escape_string($c, $fecha) . "'";
        if ($etapa) $where .= " AND etapa = $etapa";
        if ($hora) $where .= " AND hora = '" . mysqli_real_escape_string($c, $hora) . "'";

        $q = mysqli_query($c, "SELECT * FROM bitacora_membranas $where ORDER BY fecha_registro DESC");
        if ($q && mysqli_num_rows($q) > 0) {
            while ($r = mysqli_fetch_assoc($q)) {
                $pNum = $r['paquete_numero'];
                if (!isset($lecturas[$pNum])) {
                    $lecturas[$pNum] = $r;
                }
            }
            break;
        }
    }

    echo json_encode([
        'success' => true,
        'fecha' => $fecha,
        'etapa' => $etapa,
        'lecturas' => $lecturas
    ]);
    exit();
}

// ACCIÓN: OBTENER TODOS LOS REGISTROS DEL DÍA
if ($accion === 'obtener_dia') {
    $fecha = $data['fecha'] ?? date('Y-m-d');
    $c = (isset($conn_v2) && $conn_v2) ? $conn_v2 : $conn;
    $fechaEsc = mysqli_real_escape_string($c, $fecha);

    $q = mysqli_query($c, "SELECT * FROM bitacora_membranas WHERE fecha = '$fechaEsc' ORDER BY hora DESC, etapa ASC, paquete_numero ASC");
    $registros = [];
    $horasRegistradas = [];
    if ($q) {
        while ($r = mysqli_fetch_assoc($q)) {
            $registros[] = $r;
            $hKey = substr($r['hora'], 0, 5);
            if (!in_array($hKey, $horasRegistradas)) {
                $horasRegistradas[] = $hKey;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'fecha' => $fecha,
        'total' => count($registros),
        'horas' => $horasRegistradas,
        'registros' => $registros
    ]);
    exit();
}

// ACCIÓN: GUARDAR ETAPA INDEPENDIENTE
if ($accion === 'guardar') {
    $etapa = intval($data['etapa'] ?? 0);
    if ($etapa < 1 || $etapa > 4) {
        echo json_encode([
            'success' => false,
            'message' => 'Número de etapa inválido (debe ser 1, 2, 3 o 4).'
        ]);
        exit();
    }

    $fecha = !empty($data['fecha']) ? $data['fecha'] : date('Y-m-d');
    $hora = !empty($data['hora']) ? $data['hora'] : date('H:i');
    $nomina = $_SESSION['nomina'] ?? ($data['numero_nomina'] ?? 'DESCONOCIDO');
    $paquetes = $data['paquetes'] ?? [];

    if (empty($paquetes) || !is_array($paquetes)) {
        echo json_encode([
            'success' => false,
            'message' => 'No se encontraron datos de paquetes para la Etapa ' . $etapa
        ]);
        exit();
    }

    // Lista de conexiones activas para guardar (V2 y cores para respaldo)
    $conexiones = [];
    if (isset($conn_v2) && $conn_v2) $conexiones[] = $conn_v2;
    if (isset($conn) && $conn) $conexiones[] = $conn;

    if (empty($conexiones)) {
        echo json_encode([
            'success' => false,
            'message' => 'Error crítico: No hay conexión disponible a la base de datos.'
        ]);
        exit();
    }

    // Validación de Bloqueo: verificar cuáles paquetes ya están guardados en esta fecha y hora
    $forzar = !empty($data['forzar_edicion']) && in_array(strtoupper($_SESSION['rol'] ?? ''), ['ADMIN', 'SUPERVISOR', 'JEFATURA']);
    $paquetesYaGuardados = [];
    if (!$forzar) {
        $checkConn = !empty($conn_v2) ? $conn_v2 : $conn;
        $fechaEsc = mysqli_real_escape_string($checkConn, $fecha);
        $horaEsc = mysqli_real_escape_string($checkConn, $hora);
        $qCheck = mysqli_query($checkConn, "SELECT paquete_numero FROM bitacora_membranas WHERE fecha = '$fechaEsc' AND hora = '$horaEsc' AND etapa = $etapa");
        if ($qCheck) {
            while ($rCheck = mysqli_fetch_assoc($qCheck)) {
                $paquetesYaGuardados[] = (int)$rCheck['paquete_numero'];
            }
        }
        // Filtrar del payload los paquetes que ya estaban guardados para no sobreescribirlos
        $paquetes = array_values(array_filter($paquetes, function($p) use ($paquetesYaGuardados) {
            return !in_array(intval($p['paquete_numero'] ?? 0), $paquetesYaGuardados);
        }));

        if (empty($paquetes)) {
            echo json_encode([
                'success' => false,
                'bloqueado' => true,
                'message' => "El/los paquete(s) seleccionado(s) ya tienen una captura registrada para las $hora hrs del $fecha. No se permiten registros duplicados."
            ]);
            exit();
        }
    }

    $insertados = 0;
    $errores = [];

    foreach ($conexiones as $c) {
        $stmt = mysqli_prepare($c, "
            INSERT INTO bitacora_membranas (
                fecha, hora, etapa, paquete_numero, estado,
                presion_entrada, presion_salida, delta_presion,
                brix_entrada, brix_salida, brix_permeado,
                operador_nomina, observaciones
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                estado = VALUES(estado),
                presion_entrada = VALUES(presion_entrada),
                presion_salida = VALUES(presion_salida),
                delta_presion = VALUES(delta_presion),
                brix_entrada = VALUES(brix_entrada),
                brix_salida = VALUES(brix_salida),
                brix_permeado = VALUES(brix_permeado),
                operador_nomina = VALUES(operador_nomina),
                observaciones = VALUES(observaciones),
                fecha_registro = CURRENT_TIMESTAMP
        ");

        if (!$stmt) {
            $errores[] = mysqli_error($c);
            continue;
        }

        foreach ($paquetes as $p) {
            $paqNum = intval($p['paquete_numero'] ?? 0);
            if ($paqNum <= 0) continue;

            $estado = strtoupper(trim($p['estado'] ?? 'OPERACION'));
            if ($estado === 'F.O.') $estado = 'FO';
            if (!in_array($estado, ['OPERACION', 'LAVADO', 'MMT', 'FO'])) {
                $estado = 'OPERACION';
            }

            if ($estado !== 'OPERACION') {
                $pIn = null;
                $pOut = null;
                $delta = null;
                $bIn = null;
                $bOut = null;
                $bPerm = null;
                $obs = null;
            } else {
                $pIn   = (isset($p['presion_entrada']) && $p['presion_entrada'] !== '') ? floatval($p['presion_entrada']) : null;
                $pOut  = (isset($p['presion_salida']) && $p['presion_salida'] !== '') ? floatval($p['presion_salida']) : null;
                $delta = (isset($p['delta_presion']) && $p['delta_presion'] !== '') ? floatval($p['delta_presion']) : null;
                if ($delta === null && $pIn !== null && $pOut !== null) {
                    $delta = max(0, $pIn - $pOut);
                }

                $bIn   = (isset($p['brix_entrada']) && $p['brix_entrada'] !== '') ? floatval($p['brix_entrada']) : null;
                $bOut  = (isset($p['brix_salida']) && $p['brix_salida'] !== '') ? floatval($p['brix_salida']) : null;
                $bPerm = (isset($p['brix_permeado']) && $p['brix_permeado'] !== '') ? floatval($p['brix_permeado']) : null;

                $obs = !empty($p['observaciones']) ? trim($p['observaciones']) : null;
            }

            mysqli_stmt_bind_param(
                $stmt,
                "ssiisdddddsss",
                $fecha,
                $hora,
                $etapa,
                $paqNum,
                $estado,
                $pIn,
                $pOut,
                $delta,
                $bIn,
                $bOut,
                $bPerm,
                $nomina,
                $obs
            );

            if (mysqli_stmt_execute($stmt)) {
                $insertados++;
            } else {
                $errores[] = mysqli_stmt_error($stmt);
            }
        }
        mysqli_stmt_close($stmt);
    }

    if ($insertados > 0) {
        echo json_encode([
            'success' => true,
            'message' => "¡Etapa $etapa guardada exitosamente!",
            'etapa' => $etapa,
            'fecha' => $fecha,
            'hora' => $hora,
            'paquetes_guardados' => count($paquetes),
            'timestamp' => date('d/m/Y H:i:s')
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'No se pudieron guardar los registros de la etapa.',
            'errores' => $errores
        ]);
    }
    exit();
}
