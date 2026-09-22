<?php
// Progel_cores/guardar.php - Guardado EXCLUSIVO en Progel_coreV2
include 'config/db.php'; 
include 'config/db_v2.php';
include 'includes/mapeo_v2.php';

function show_sweet_alert($icon, $title, $text, $redirect_url) {
    $redirect_js = "window.location.href = '$redirect_url';";
    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><title>Procesando...</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>body { background-color: #f4f6f9; }</style>
</head>
<body>
    <script>
        Swal.fire({
            icon: '$icon', title: '$title', text: '$text',
            timer: 2500, showConfirmButton: false, timerProgressBar: true
        }).then(() => { $redirect_js });
    </script>
</body>
</html>
HTML;
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $global_equipo_id = (int)($_POST['equipo_id'] ?? 0);
    $numero_nomina = $_POST['numero_nomina'] ?? 'DESCONOCIDO';
    $es_multiequipo = $_POST['es_multiequipo'] ?? 0;
    
    // Atrapamos el lote que viene del formulario
    $lote = $_POST['lote'] ?? null;

    if (empty($numero_nomina)) die("Error: Falta número de nómina.");

    $guardados_count = 0;
    $v2_por_equipo = [];
    $v2_obs = [];

    foreach ($_POST as $key => $valor) {
        // Solo procesamos si el campo se llenó
        if (strpos($key, 'param_') === 0 && trim($valor) !== '') {
            $parametro_id = (int)str_replace('param_', '', $key);
            $observacion = $_POST['obs_' . $parametro_id] ?? '';

            if ($es_multiequipo == 1 && $conn) {
                $q_eq = mysqli_query($conn, "SELECT equipo_id FROM parametros WHERE id = $parametro_id");
                $r_eq = mysqli_fetch_assoc($q_eq);
                $true_equipo_id = (int)($r_eq['equipo_id'] ?? $global_equipo_id);
            } else {
                $true_equipo_id = (int)$global_equipo_id;
            }

            // Agrupamos para guardar estructurado y horizontal en Progel_coreV2
            if (isset($GLOBALS['MAP_PARAMETROS_V2'][$parametro_id])) {
                $columnaV2 = $GLOBALS['MAP_PARAMETROS_V2'][$parametro_id][1];
                $v2_por_equipo[$true_equipo_id][$columnaV2] = $valor;
                if (!empty($observacion) && empty($v2_obs[$true_equipo_id])) {
                    $v2_obs[$true_equipo_id] = $observacion;
                }
                $guardados_count++;
            }
        }
    }

    if ($conn) {
        mysqli_close($conn);
    }

    // ====================================================================
    // GUARDADO EXCLUSIVO EN LA NUEVA BASE DE DATOS: Progel_coreV2
    // (CERO INSERTS A progel_cores)
    // ====================================================================
    $guardado_exitoso = false;
    if (isset($conn_v2) && $conn_v2 && !empty($v2_por_equipo)) {
        foreach ($v2_por_equipo as $eq_id => $valoresCols) {
            $obsFinal = $v2_obs[$eq_id] ?? '';
            if (guardarRegistroEnV2($conn_v2, $eq_id, $numero_nomina, $lote, $valoresCols, $obsFinal)) {
                $guardado_exitoso = true;
            }
        }
        mysqli_close($conn_v2);
    }

    // Redirección inteligente: regresamos al equipo que se estaba capturando para ver sus badges y semáforos
    $redirect_url = ($global_equipo_id > 0) ? "captura.php?id=$global_equipo_id" : 'index.php';

    if ($guardados_count > 0 && $guardado_exitoso) {
        show_sweet_alert('success', '¡Guardado con Éxito!', "Se registraron $guardados_count parámetros en Progel_coreV2.", $redirect_url);
    } elseif ($guardados_count > 0 && !$guardado_exitoso) {
        show_sweet_alert('error', 'Error al Guardar', 'Hubo un problema al conectar o insertar en Progel_coreV2.', $redirect_url);
    } else {
        show_sweet_alert('info', 'Sin cambios', 'No llenaste ningún campo nuevo.', $redirect_url);
    }
}