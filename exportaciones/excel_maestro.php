<?php
include __DIR__ . '/../config/db.php';
date_default_timezone_set('America/Mazatlan');

$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-d');
$fecha_fin = $_POST['fecha_fin'] ?? date('Y-m-d');

// Consultar los datos desde la BD anterior (SUP_captura_produccion)
$stmt = mysqli_prepare($conn, "SELECT * FROM SUP_captura_produccion WHERE fecha BETWEEN ? AND ? ORDER BY fecha ASC, hora ASC");
if (!$stmt) {
    die("Error al consultar datos: " . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, "ss", $fecha_inicio, $fecha_fin);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Consultar humedades de secadores en progel_procesos (calidad_humedad_secadores) para ese rango
$humedades_calidad = [];
if (isset($conn_procesos) && $conn_procesos) {
    $stmt_h = mysqli_prepare($conn_procesos, "SELECT fecha, HOUR(hora) AS hr, secador, hum_ultima, estado_fo FROM calidad_humedad_secadores WHERE fecha BETWEEN ? AND ? AND secador BETWEEN 1 AND 4 ORDER BY hora ASC, id ASC");
    if ($stmt_h) {
        mysqli_stmt_bind_param($stmt_h, "ss", $fecha_inicio, $fecha_fin);
        mysqli_stmt_execute($stmt_h);
        $res_h = mysqli_stmt_get_result($stmt_h);
        while ($rh = mysqli_fetch_assoc($res_h)) {
            $f_key = $rh['fecha'];
            $h_key = (int)$rh['hr'];
            $sec = (int)$rh['secador'];
            $humedades_calidad[$f_key][$h_key][$sec] = ($rh['estado_fo'] == 1) ? 'F.O.' : $rh['hum_ultima'];
        }
        mysqli_stmt_close($stmt_h);
    }
}

// Configurar encabezados para Excel
header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=Reporte_Maestro_{$fecha_inicio}_al_{$fecha_fin}.xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "<html><head><meta charset='UTF-8'></head><body>";
echo "<table border='1'>";
echo "<thead>";
echo "<tr style='background-color: #0d6efd; color: white;'>";
echo "<th>Fecha</th>";
echo "<th>Hora</th>";
echo "<th>Turno</th>";
echo "<th>Consumo Cuero (Kg/hr)</th>";
echo "<th>Cocedores Manual</th>";
echo "<th>Caldo en Pre-UF</th>";
echo "<th>Pre-Concentrado</th>";
echo "<th>Caldo Concentrado</th>";
echo "<th>Votators Activos</th>";
echo "<th>Flujo V1 (L/h)</th>";
echo "<th>Flujo V2 (L/h)</th>";
echo "<th>Flujo V3 (L/h)</th>";
echo "<th>Flujo V4 (L/h)</th>";
echo "<th>Flujo V5 (L/h)</th>";
echo "<th>Flujo V6 (L/h)</th>";
echo "<th>S&oacute;lidos (Brix)</th>";
echo "<th>Humedad Secador 1 (%)</th>";
echo "<th>Humedad Secador 2 (%)</th>";
echo "<th>Humedad Secador 3 (%)</th>";
echo "<th>Humedad Secador 4 (%)</th>";
echo "<th>Humedad T&uacute;nel 5 (%)</th>";
echo "<th>Velocidad T&uacute;nel 1 (m/h)</th>";
echo "<th>Velocidad T&uacute;nel 2 (m/h)</th>";
echo "<th>Velocidad T&uacute;nel 3 (m/h)</th>";
echo "<th>Velocidad T&uacute;nel 4 (m/h)</th>";
echo "<th>Kg Te&oacute;ricos</th>";
echo "<th>Kg Reales</th>";
echo "<th>Rechazo (Kg)</th>";
echo "<th>Remoler (Kg)</th>";
echo "<th>Eficiencia (%)</th>";
echo "<th>Acciones / Observaciones</th>";
echo "<th>Fecha Registro Real</th>";
echo "</tr>";
echo "</thead>";
echo "<tbody>";

while ($row = mysqli_fetch_assoc($result)) {
    $hora_num = (int)substr($row['hora'], 0, 2);
    $turno = ($hora_num >= 7 && $hora_num < 19) ? 'Diurno' : 'Nocturno';
    $fecha_row = $row['fecha'] ?? '';

    $h1 = $humedades_calidad[$fecha_row][$hora_num][1] ?? ($row['hum_t1'] ?? '');
    $h2 = $humedades_calidad[$fecha_row][$hora_num][2] ?? ($row['hum_t2'] ?? '');
    $h3 = $humedades_calidad[$fecha_row][$hora_num][3] ?? ($row['hum_t3'] ?? '');
    $h4 = $humedades_calidad[$fecha_row][$hora_num][4] ?? ($row['hum_t4'] ?? '');

    echo "<tr>";
    echo "<td>" . htmlspecialchars($fecha_row, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . str_pad($hora_num, 2, '0', STR_PAD_LEFT) . ":00</td>";
    echo "<td>$turno</td>";
    echo "<td>" . htmlspecialchars($row['consumo_cuero'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['cocedores_manual'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['caldo_pre_uf'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['pre_concentrado'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['caldo_concentrado'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['votators_activos'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['flujo_v1'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['flujo_v2'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['flujo_v3'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['flujo_v4'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['flujo_v5'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['flujo_v6'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['solidos_brix'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars((string)$h1, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars((string)$h2, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars((string)$h3, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars((string)$h4, ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['hum_t5'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['vel_t1'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['vel_t2'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['vel_t3'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['vel_t4'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['kg_teoricos'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['kg_reales'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['rechazo'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['remoler'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['eficiencia'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['acciones'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "<td>" . htmlspecialchars($row['fecha_registro_real'] ?? '', ENT_QUOTES, 'UTF-8') . "</td>";
    echo "</tr>";
}

echo "</tbody>";
echo "</table>";
echo "</body></html>";
?>
