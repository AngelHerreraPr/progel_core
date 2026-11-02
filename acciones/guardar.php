<?php
// progel_core/guardar.php - Guardado robusto en Progel_coreV2 con preservación de TODOS los comentarios
include __DIR__ . '/../config/db.php'; 
include __DIR__ . '/../config/db_v2.php';
include __DIR__ . '/../includes/mapeo_v2.php';

function show_sweet_alert($icon, $title, $text, $redirect_url) {
    $redirect_js = "window.location.href = '$redirect_url';";
    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8"><title>Procesando...</title>
    <script src="../assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>
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
    $v2_obs_list = [];

    // Preparar inserción en bitacora_lecturas (para respaldo histórico individual por parámetro)
    $stmt_bl = null;
    if ($conn) {
        $stmt_bl = @mysqli_prepare($conn, "INSERT INTO bitacora_lecturas (equipo_id, parametro_id, valor_capturado, observaciones, numero_nomina, lote) VALUES (?, ?, ?, ?, ?, ?)");
    }

    foreach ($_POST as $key => $valor) {
        // Solo procesamos si el campo se llenó
        if (strpos($key, 'param_') === 0 && trim($valor) !== '') {
            $parametro_id = (int)str_replace('param_', '', $key);
            $observacion = trim($_POST['obs_' . $parametro_id] ?? '');

            $nom_param = '';
            if ($conn) {
                $q_p = mysqli_query($conn, "SELECT equipo_id, nombre_parametro FROM parametros WHERE id = $parametro_id LIMIT 1");
                if ($q_p && $rp = mysqli_fetch_assoc($q_p)) {
                    $true_equipo_id = ($es_multiequipo == 1 && !empty($rp['equipo_id'])) ? (int)$rp['equipo_id'] : (int)$global_equipo_id;
                    $nom_param = trim($rp['nombre_parametro'] ?? '');
                } else {
                    $true_equipo_id = (int)$global_equipo_id;
                }
            } else {
                $true_equipo_id = (int)$global_equipo_id;
            }

            // 1. Guardar individualmente cada parámetro y su propio comentario en bitacora_lecturas
            if ($stmt_bl) {
                @mysqli_stmt_bind_param($stmt_bl, "iissss", $true_equipo_id, $parametro_id, $valor, $observacion, $numero_nomina, $lote);
                @mysqli_stmt_execute($stmt_bl);
            }

            // 2. Agrupar para tabla horizontal de bitácora
            if (isset($GLOBALS['MAP_PARAMETROS_V2'][$parametro_id])) {
                $columnaV2 = $GLOBALS['MAP_PARAMETROS_V2'][$parametro_id][1];
                $v2_por_equipo[$true_equipo_id][$columnaV2] = $valor;
                
                // Conservar cada comentario individual
                if ($observacion !== '') {
                    $etiqueta = !empty($nom_param) ? "[$nom_param]: " : '';
                    $v2_obs_list[$true_equipo_id][] = $etiqueta . $observacion;
                }
                $guardados_count++;
            }
        }
    }

    if ($stmt_bl) {
        @mysqli_stmt_close($stmt_bl);
    }
    if ($conn) {
        mysqli_close($conn);
    }

    $guardado_exitoso = false;
    if (isset($conn_v2) && $conn_v2 && !empty($v2_por_equipo)) {
        foreach ($v2_por_equipo as $eq_id => $valoresCols) {
            // Unir todos los comentarios de las diferentes variables de ese equipo
            $obsFinal = '';
            if (!empty($v2_obs_list[$eq_id])) {
                if (count($v2_obs_list[$eq_id]) === 1) {
                    $obsFinal = $v2_obs_list[$eq_id][0];
                } else {
                    $obsFinal = implode(" | ", $v2_obs_list[$eq_id]);
                }
            }

            if (guardarRegistroEnV2($conn_v2, $eq_id, $numero_nomina, $lote, $valoresCols, $obsFinal)) {
                $guardado_exitoso = true;
            }
        }
        mysqli_close($conn_v2);
    }

    // Redirección inteligente: regresamos al equipo que se estaba capturando
    $redirect_url = ($global_equipo_id > 0) ? "../captura.php?id=$global_equipo_id" : '../index.php';

    if ($guardados_count > 0 && $guardado_exitoso) {
        show_sweet_alert('success', '¡Guardado con Éxito!', "Se registraron $guardados_count parámetros y sus observaciones en la base de datos.", $redirect_url);
    } elseif ($guardados_count > 0 && !$guardado_exitoso) {
        show_sweet_alert('error', 'Error al Guardar', 'Hubo un problema al conectar o insertar en la base de datos.', $redirect_url);
    } else {
        show_sweet_alert('info', 'Sin cambios', 'No llenaste ningún campo nuevo.', $redirect_url);
    }
}
?>
