<?php
// Progel_cores/historial.php
include 'config/db.php';
include 'config/db_v2.php';
include 'includes/mapeo_v2.php';

// Recibimos qué equipo queremos revisar
$equipo_id = (int)($_GET['id'] ?? 0);

// Buscamos el nombre del equipo para el título
$q_equipo = mysqli_query($conn, "SELECT nombre FROM equipos WHERE id = $equipo_id");
$equipo_nombre = ($row = mysqli_fetch_assoc($q_equipo)) ? $row['nombre'] : 'Equipo Desconocido';

// =========================================================================
// CONSULTA PRINCIPAL: Progel_coreV2
// =========================================================================
$tabla_v2 = '';
$campo_equipo_v2 = '';
$eq_nombre_v2 = $GLOBALS['NOMBRES_EQUIPOS_V2'][$equipo_id] ?? '';

if (in_array($equipo_id, [21, 22, 23, 24])) {
    $tabla_v2 = 'bitacora_secadores';
    $campo_equipo_v2 = 'secador_nombre';
} elseif (in_array($equipo_id, [14, 15, 16, 17, 18])) {
    $tabla_v2 = 'bitacora_concentradores';
    $campo_equipo_v2 = 'concentrador_nombre';
} elseif (in_array($equipo_id, [81, 82])) {
    $tabla_v2 = 'bitacora_chillers_votator';
    $campo_equipo_v2 = 'equipo_nombre';
} elseif (in_array($equipo_id, [91, 92])) {
    $tabla_v2 = 'bitacora_chillers_normales';
    $campo_equipo_v2 = 'equipo_nombre';
} elseif (in_array($equipo_id, [12, 13])) {
    $tabla_v2 = 'bitacora_compresores';
    $campo_equipo_v2 = 'compresor_nombre';
}

$lista_lecturas = [];

// Si el equipo pertenece a una tabla de Progel_coreV2
if ($tabla_v2 !== '' && isset($conn_v2) && $conn_v2) {
    // Parámetros y columnas del equipo
    $p_res = mysqli_query($conn, "SELECT * FROM parametros WHERE equipo_id = $equipo_id ORDER BY id ASC");
    $params_map = [];
    while ($p = mysqli_fetch_assoc($p_res)) {
        if (isset($GLOBALS['MAP_PARAMETROS_V2'][$p['id']])) {
            $col = $GLOBALS['MAP_PARAMETROS_V2'][$p['id']][1];
            $params_map[$col] = $p;
        }
    }

    $esc_nombre = mysqli_real_escape_string($conn_v2, $eq_nombre_v2);
    $sql_v2 = "SELECT * FROM `$tabla_v2` WHERE `$campo_equipo_v2` = '$esc_nombre' ORDER BY id DESC LIMIT 50";
    $res_v2 = mysqli_query($conn_v2, $sql_v2);

    if ($res_v2 && mysqli_num_rows($res_v2) > 0) {
        while ($row = mysqli_fetch_assoc($res_v2)) {
            foreach ($params_map as $col => $p_info) {
                if (isset($row[$col]) && $row[$col] !== null && $row[$col] !== '') {
                    $lista_lecturas[] = [
                        'fecha_registro'   => $row['fecha_registro'],
                        'numero_nomina'    => $row['operador_nomina'],
                        'lote'             => $row['lote'] ?? '',
                        'nombre_parametro' => $p_info['nombre_parametro'],
                        'valor_capturado'  => $row[$col],
                        'observaciones'    => $row['observaciones'] ?? '',
                        'tipo_dato'        => $p_info['tipo_dato'],
                        'rojo_bajo'        => $p_info['rojo_bajo'],
                        'amarillo_bajo'    => $p_info['amarillo_bajo'],
                        'amarillo_alto'    => $p_info['amarillo_alto'],
                        'rojo_alto'        => $p_info['rojo_alto']
                    ];
                }
            }
        }
    }
}

// Fallback a bitacora_lecturas si no hay registros en V2
if (empty($lista_lecturas)) {
    $query = "SELECT b.fecha_registro, b.numero_nomina, p.nombre_parametro, b.valor_capturado, b.observaciones,
                     p.tipo_dato, p.rojo_bajo, p.amarillo_bajo, p.amarillo_alto, p.rojo_alto
              FROM bitacora_lecturas b
              INNER JOIN parametros p ON b.parametro_id = p.id
              WHERE b.equipo_id = ?
              ORDER BY b.fecha_registro DESC LIMIT 100";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "i", $equipo_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($result) {
        while ($r = mysqli_fetch_assoc($result)) {
            $lista_lecturas[] = $r;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial - <?= htmlspecialchars($equipo_nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #f4f6f9; }
        .table-hover tbody tr:hover { background-color: #e9ecef; }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm border-start border-5 border-warning">
        <h2 class="m-0 fw-bold text-dark">
            <i class="bi bi-clock-history text-warning me-2"></i> Historial de Registro: <span class="text-primary"><?= htmlspecialchars($equipo_nombre) ?></span>
        </h2>
        <div class="d-flex gap-2">
            <a href="reporte_general.php?equipo_id=<?= $equipo_id ?>" class="btn btn-outline-success fw-bold">
                <i class="bi bi-grid-3x3-gap me-1"></i> Vista Matricial
            </a>
            <button onclick="window.history.back();" class="btn btn-outline-secondary fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </button>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle text-center m-0">
                    <thead class="table-dark">
                        <tr style="height: 50px;">
                            <th class="bg-primary text-white"><i class="bi bi-calendar-event"></i> Fecha y Hora</th>
                            <th><i class="bi bi-person-badge"></i> Nómina / Lote</th>
                            <th class="text-start ps-3"><i class="bi bi-list-check"></i> Parámetro Medido</th>
                            <th style="width: 150px;"><i class="bi bi-speedometer2"></i> Lectura</th>
                            <th><i class="bi bi-chat-square-text"></i> Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($lista_lecturas)):
                            foreach ($lista_lecturas as $r):
                                $fecha_bonita = date("d/m/Y - h:i A", strtotime($r['fecha_registro']));

                                // --- Lógica de Semáforo e Íconos ---
                                $valor = $r['valor_capturado'];
                                $class = '';
                                $icon_class = '';
                                $icon_color_class = 'text-secondary';
                                $lower_val = strtolower(trim($valor));

                                if ($r['tipo_dato'] == 'NUMERICO' && is_numeric($valor)) {
                                    $v = floatval($valor);
                                    if (isset($r['rojo_bajo'])) {
                                        if ($v <= floatval($r['rojo_bajo']) || $v >= floatval($r['rojo_alto'])) $class = 'semaforo-r';
                                        else if (($v > floatval($r['rojo_bajo']) && $v <= floatval($r['amarillo_bajo'])) || ($v >= floatval($r['amarillo_alto']) && $v < floatval($r['rojo_alto']))) $class = 'semaforo-a';
                                        else $class = 'semaforo-v';
                                    }
                                } else { 
                                    if (in_array($lower_val, ['no', 'no realizado', 'no cumple', 'fideo plastoso', 'sí presenta', 'quemada', 'húmeda', 'f.o.'])) $class = 'semaforo-r';
                                    else if (in_array($lower_val, ['deformación en el fideo', 'deformación', 'pendiente', 'sobresecada', 'plástica'])) $class = 'semaforo-a';
                                    else if (in_array($lower_val, ['sí', 'realizado', 'cumple', 'fideo continuo', 'firme', 'no presenta'])) $class = 'semaforo-v';
                                }

                                if ($class === 'semaforo-v') {
                                    $icon_class = 'bi-check-circle-fill';
                                    $icon_color_class = 'text-success';
                                } elseif ($class === 'semaforo-a') {
                                    $icon_class = 'bi-exclamation-triangle-fill';
                                    $icon_color_class = 'text-warning';
                                } elseif ($class === 'semaforo-r') {
                                    $icon_class = 'bi-x-circle-fill';
                                    $icon_color_class = 'text-danger';
                                }
                        ?>
                        <tr class="<?= $class ?>" style="height: 60px;">
                            <td class="fw-bold text-secondary"><?= $fecha_bonita ?></td>
                            <td>
                                <span class="badge bg-dark fs-6 rounded-pill px-3 py-1"><?= htmlspecialchars($r['numero_nomina']) ?></span>
                                <?php if (!empty($r['lote'])): ?>
                                    <span class="badge bg-primary text-white fs-6 rounded-pill px-2 py-1 ms-1">Lote: <?= htmlspecialchars($r['lote']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-start fw-bold text-dark ps-3"><?= htmlspecialchars($r['nombre_parametro']) ?></td>
                            <td class="fs-5 fw-bold">
                                <?php if($icon_class): ?><i class="bi <?= $icon_class ?> <?= $icon_color_class ?> me-2"></i><?php endif; ?>
                                <?= htmlspecialchars($r['valor_capturado']) ?>
                            </td>
                            <td class="text-muted text-start" style="font-size: 0.9rem;"><?= htmlspecialchars($r['observaciones']) ?: '<em class="text-light-50">Sin notas</em>' ?></td>
                        </tr>
                        <?php
                            endforeach;
                        else:
                        ?>
                        <tr>
                            <td colspan="5" class="py-5 text-muted fs-4">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                Aún no hay registros en el historial de este equipo.
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
