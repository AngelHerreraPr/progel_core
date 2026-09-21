<?php
// Progel_cores/guardar.php
include 'config/db.php'; 

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
    $global_equipo_id = $_POST['equipo_id'] ?? 0;
    $numero_nomina = $_POST['numero_nomina'] ?? 'DESCONOCIDO';
    $es_multiequipo = $_POST['es_multiequipo'] ?? 0;
    
    // Atrapamos el lote que viene del formulario
    $lote = $_POST['lote'] ?? null;

    if (empty($numero_nomina)) die("Error: Falta número de nómina.");

    // GUARDADO INCREMENTAL: Agregamos la columna 'lote' y un '?' extra
    $query = "INSERT INTO bitacora_lecturas (equipo_id, parametro_id, valor_capturado, observaciones, numero_nomina, lote) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    
    // Atrapamos el error si falta una columna en la tabla
    if (!$stmt) {
        die("<div style='padding: 20px; background: #fee2e2; color: #b91c1c; font-family: sans-serif; border-left: 5px solid #b91c1c;'>
                <h3>Error de Estructura en Base de Datos</h3>
                <p><strong>Detalle:</strong> " . mysqli_error($conn) . "</p>
             </div>");
    }

    $guardados_count = 0;

    foreach ($_POST as $key => $valor) {
        // Solo guardamos si el campo se llenó
        if (strpos($key, 'param_') === 0 && trim($valor) !== '') {
            $parametro_id = str_replace('param_', '', $key);
            $observacion = $_POST['obs_' . $parametro_id] ?? '';

            if ($es_multiequipo == 1) {
                $q_eq = mysqli_query($conn, "SELECT equipo_id FROM parametros WHERE id = $parametro_id");
                $r_eq = mysqli_fetch_assoc($q_eq);
                $true_equipo_id = $r_eq['equipo_id'] ?? $global_equipo_id;
            } else {
                $true_equipo_id = $global_equipo_id;
            }

            mysqli_stmt_bind_param($stmt, "iissss", $true_equipo_id, $parametro_id, $valor, $observacion, $numero_nomina, $lote);

            // Atrapamos el error si los datos no coinciden
            if (!mysqli_stmt_execute($stmt)) {
                die("<div style='padding: 20px; background: #fff3cd; color: #856404; font-family: sans-serif; border-left: 5px solid #ffeeba;'>
                        <h3>Error al Insertar el Dato</h3>
                        <p><strong>Parámetro ID:</strong> $parametro_id</p>
                        <p><strong>Detalle:</strong> " . mysqli_stmt_error($stmt) . "</p>
                     </div>");
            }

            $guardados_count++;
        }
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    if ($guardados_count > 0) {
        show_sweet_alert('success', '¡Guardado con Éxito!', "Se han registrado $guardados_count lecturas nuevas en la bitácora.", 'index.php');
    } else {
        show_sweet_alert('info', 'Sin cambios', 'No llenaste ningún campo nuevo.', 'index.php');
    }
}
?>