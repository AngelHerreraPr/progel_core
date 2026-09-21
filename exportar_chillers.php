<?php
// Progel_cores/exportar_chillers.php
session_start();
include 'config/db.php';

// Validar que el usuario haya iniciado sesión
if (!isset($_SESSION['nomina'])) {
    header("Location: login.php");
    exit();
}

// RESTRICCIÓN DE SEGURIDAD: Solo Administradores (ADMIN o JEFATURA)
$rol_actual = strtoupper(trim($_SESSION['rol'] ?? ''));
if (!in_array($rol_actual, ['ADMIN', 'JEFATURA'])) {
    http_response_code(403);
    die("<div style='font-family:sans-serif; text-align:center; padding:50px;'>
            <h2 style='color:#dc3545;'>⛔ Acceso Denegado</h2>
            <p>Esta función de exportación está restringida exclusivamente para usuarios con rol de <strong>Administrador</strong>.</p>
            <a href='javascript:history.back()' style='color:#0d6efd;'>&larr; Volver</a>
         </div>");
}

// EXCLUSIVO PARA CHILLERS VOTATOR (1 AL 4) -> IDs 81 Y 82
$where_sql = "WHERE b.equipo_id IN (81, 82)";

// Función para identificar exactamente la unidad Chiller Votator 1, 2, 3 o 4
function obtenerUnidadChiller($equipo_id, $param_nombre) {
    $param_low = mb_strtolower($param_nombre, 'UTF-8');
    $param_low = str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $param_low);

    if (strpos($param_low, 'maestro') !== false) {
        return ($equipo_id == 81) ? 'Chiller Votator 1' : 'Chiller Votator 3';
    } elseif (strpos($param_low, 'esclavo') !== false) {
        return ($equipo_id == 81) ? 'Chiller Votator 2' : 'Chiller Votator 4';
    } else {
        if (preg_match('/chiller\s*(\d+)/i', $param_nombre, $matches)) {
            return 'Chiller Votator ' . $matches[1];
        }
        return ($equipo_id == 81) ? 'Chiller Votator (1 y 2)' : 'Chiller Votator (3 y 4)';
    }
}

// Configurar encabezados para descarga en Excel (CSV UTF-8 con BOM)
$filename = "Reporte_Chillers_Votator_1al4_" . date('Y-m-d_H-i') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// BOM para compatibilidad con Excel y caracteres especiales (UTF-8)
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Encabezados limpios con Identificación de Chiller Específico (1 al 4)
fputcsv($output, [
    'Fecha y Hora',
    'Nombre Equipo General',
    'Chiller / Unidad',
    'Nómina Operador',
    'Parámetro / Variable',
    'Crítico Bajo (Rojo)',
    'Alerta Baja (Amarillo)',
    'Alerta Alta (Amarillo)',
    'Crítico Alto (Rojo)',
    'Lectura / Valor Capturado',
    'Observaciones / Justificación'
]);

// Consulta a la base de datos EXCLUSIVA para Chillers Votator (1 al 4)
$query = "SELECT 
            b.fecha_registro,
            e.id AS equipo_id,
            e.nombre AS equipo_nombre,
            b.numero_nomina,
            p.nombre_parametro,
            p.rojo_bajo,
            p.amarillo_bajo,
            p.amarillo_alto,
            p.rojo_alto,
            b.valor_capturado,
            b.observaciones
          FROM bitacora_lecturas b
          INNER JOIN parametros p ON b.parametro_id = p.id
          INNER JOIN equipos e ON b.equipo_id = e.id
          $where_sql
          ORDER BY b.fecha_registro DESC, e.id ASC, p.id ASC";

$result = mysqli_query($conn, $query);

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Obtener el nombre de unidad específico (Chiller Votator 1, 2, 3 o 4)
        $chiller_unidad = obtenerUnidadChiller($row['equipo_id'], $row['nombre_parametro']);
        
        // Etiquetar la variable con la unidad explícita
        $parametro_etiquetado = '[' . $chiller_unidad . '] ' . str_replace([' (Maestro)', ' (Esclavo)', ' Maestro', ' Esclavo'], '', $row['nombre_parametro']);

        fputcsv($output, [
            date('d/m/Y h:i A', strtotime($row['fecha_registro'])),
            $row['equipo_nombre'],
            $chiller_unidad,
            $row['numero_nomina'],
            $parametro_etiquetado,
            ($row['rojo_bajo'] !== null && $row['rojo_bajo'] !== '') ? $row['rojo_bajo'] : '-',
            ($row['amarillo_bajo'] !== null && $row['amarillo_bajo'] !== '') ? $row['amarillo_bajo'] : '-',
            ($row['amarillo_alto'] !== null && $row['amarillo_alto'] !== '') ? $row['amarillo_alto'] : '-',
            ($row['rojo_alto'] !== null && $row['rojo_alto'] !== '') ? $row['rojo_alto'] : '-',
            $row['valor_capturado'],
            (!empty($row['observaciones']) && $row['observaciones'] !== 'Sin notas') ? $row['observaciones'] : 'Sin notas'
        ]);
    }
}

fclose($output);
exit();
?>
