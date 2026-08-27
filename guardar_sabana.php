<?php
session_start();
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
    $fecha_sabana = $_POST['fecha_sabana'];
    $turno_sabana = $_POST['turno_sabana'];

    $consumo = $_POST['consumo_cuero'] ?? [];
    $pre_uf = $_POST['caldo_pre_uf'] ?? [];
    $pre_conc = $_POST['pre_concentrado'] ?? [];
    $caldo_conc = $_POST['caldo_concentrado'] ?? [];
    $votators = $_POST['votators_activos'] ?? [];

    $ht1 = $_POST['hum_t1'] ?? []; $ht2 = $_POST['hum_t2'] ?? []; $ht3 = $_POST['hum_t3'] ?? [];
    $ht4 = $_POST['hum_t4'] ?? []; $ht5 = $_POST['hum_t5'] ?? [];

    $vt1 = $_POST['vel_t1'] ?? []; $vt2 = $_POST['vel_t2'] ?? []; $vt3 = $_POST['vel_t3'] ?? [];
    $vt4 = $_POST['vel_t4'] ?? [];

    $kg_t = $_POST['kg_teoricos'] ?? []; $kg_r = $_POST['kg_reales'] ?? [];
    $rech = $_POST['rechazo'] ?? []; $remo = $_POST['remoler'] ?? [];
    $efic = $_POST['eficiencia'] ?? [];

    $acc = $_POST['acciones'] ?? [];

    $horas_a_procesar = array_unique(array_merge(
        array_keys($consumo), array_keys($pre_uf), array_keys($pre_conc), array_keys($caldo_conc), array_keys($votators),
        array_keys($ht1), array_keys($ht2), array_keys($ht3), array_keys($ht4), array_keys($ht5),
        array_keys($vt1), array_keys($vt2), array_keys($vt3), array_keys($vt4),
        array_keys($kg_t), array_keys($kg_r), array_keys($rech), array_keys($remo), array_keys($efic),
        array_keys($acc)
    ));

    if (empty($horas_a_procesar)) {
        show_sweet_alert('info', 'Sin Cambios', 'No se detectaron datos para guardar.', "reporte_maestro.php?fecha=$fecha_sabana&turno=$turno_sabana");
    }

    $sql = "INSERT INTO SUP_captura_produccion (
                fecha, hora, consumo_cuero, caldo_pre_uf, pre_concentrado, caldo_concentrado, votators_activos,
                hum_t1, hum_t2, hum_t3, hum_t4, hum_t5,
                vel_t1, vel_t2, vel_t3, vel_t4,
                kg_teoricos, kg_reales, rechazo, remoler, eficiencia, acciones
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                consumo_cuero=VALUES(consumo_cuero), caldo_pre_uf=VALUES(caldo_pre_uf), pre_concentrado=VALUES(pre_concentrado),
                caldo_concentrado=VALUES(caldo_concentrado), votators_activos=VALUES(votators_activos),
                hum_t1=VALUES(hum_t1), hum_t2=VALUES(hum_t2), hum_t3=VALUES(hum_t3), hum_t4=VALUES(hum_t4), hum_t5=VALUES(hum_t5),
                vel_t1=VALUES(vel_t1), vel_t2=VALUES(vel_t2), vel_t3=VALUES(vel_t3), vel_t4=VALUES(vel_t4),
                kg_teoricos=VALUES(kg_teoricos), kg_reales=VALUES(kg_reales), rechazo=VALUES(rechazo),
                remoler=VALUES(remoler), eficiencia=VALUES(eficiencia), acciones=VALUES(acciones)";

    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        die("Error al preparar la consulta: " . mysqli_error($conn));
    }

    $val = function($arr, $h) {
        return (isset($arr[$h]) && trim($arr[$h]) !== '' && $arr[$h] !== '-') ? $arr[$h] : null;
    };

    foreach ($horas_a_procesar as $hora) {
        $hora_int = intval(substr($hora, 0, 2));
        $fecha_para_db = $fecha_sabana;
        if ($turno_sabana == 'nocturno' && $hora_int <= 6) {
            $fecha_para_db = date('Y-m-d', strtotime($fecha_sabana . ' +1 day'));
        }
        $hora_para_db = $hora . ':00';

        mysqli_stmt_bind_param($stmt, 'ssssssssssssssssssssss',
            $fecha_para_db,
            $hora_para_db,
            $val($consumo, $hora),
            $val($pre_uf, $hora),
            $val($pre_conc, $hora),
            $val($caldo_conc, $hora),
            $val($votators, $hora),
            $val($ht1, $hora), $val($ht2, $hora), $val($ht3, $hora), $val($ht4, $hora), $val($ht5, $hora),
            $val($vt1, $hora), $val($vt2, $hora), $val($vt3, $hora), $val($vt4, $hora),
            $val($kg_t, $hora), $val($kg_r, $hora), $val($rech, $hora), $val($remo, $hora), $val($efic, $hora),
            $val($acc, $hora)
        );
        mysqli_stmt_execute($stmt);
    }

    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    show_sweet_alert('success', '¡Sábana Actualizada!', 'Tus datos de producción se guardaron correctamente.', "reporte_maestro.php?fecha=$fecha_sabana&turno=$turno_sabana");
} else {
    header('Location: reporte_maestro.php');
    exit();
}
?>