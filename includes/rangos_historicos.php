<?php
// includes/rangos_historicos.php
// Gestión de rangos históricos de parámetros para conservar la evaluación original en lecturas previas a un cambio.

function asegurarTablaHistorialRangos($conn) {
    if (!$conn) return;
    $sql = "CREATE TABLE IF NOT EXISTS parametros_historial_rangos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        parametro_id INT NOT NULL,
        equipo_id INT NOT NULL DEFAULT 0,
        rojo_bajo_ant DECIMAL(12,4) NULL,
        amarillo_bajo_ant DECIMAL(12,4) NULL,
        amarillo_alto_ant DECIMAL(12,4) NULL,
        rojo_alto_ant DECIMAL(12,4) NULL,
        rojo_bajo_nuevo DECIMAL(12,4) NULL,
        amarillo_bajo_nuevo DECIMAL(12,4) NULL,
        amarillo_alto_nuevo DECIMAL(12,4) NULL,
        rojo_alto_nuevo DECIMAL(12,4) NULL,
        motivo VARCHAR(255) DEFAULT NULL,
        admin_nomina VARCHAR(50) NOT NULL,
        fecha_cambio DATETIME NOT NULL,
        INDEX idx_param_fecha (parametro_id, fecha_cambio)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    @mysqli_query($conn, $sql);
}

/**
 * Carga todos los cambios de rangos agrupados por parametro_id en orden cronológico ascendente (fecha_cambio ASC).
 */
function cargarHistorialRangos($conn) {
    asegurarTablaHistorialRangos($conn);
    $mapa = [];
    $res = @mysqli_query($conn, "SELECT * FROM parametros_historial_rangos ORDER BY fecha_cambio ASC, id ASC");
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $pid = (int)$row['parametro_id'];
            $mapa[$pid][] = $row;
        }
    }
    return $mapa;
}

/**
 * Devuelve los límites vigentes en el momento exacto ($fecha_registro) en que se capturó una lectura.
 * Si el parámetro fue modificado DESPUÉS de $fecha_registro, retorna los rangos anteriores y marca 'es_rango_anterior' = true.
 */
function obtenerParametroEnFecha($param_actual, $fecha_registro, $historial_rangos) {
    $resultado = $param_actual;
    $resultado['es_rango_anterior'] = false;
    $resultado['info_cambio'] = null;

    $pid = (int)($param_actual['id'] ?? 0);
    if ($pid <= 0 || empty($fecha_registro) || empty($historial_rangos[$pid])) {
        return $resultado;
    }

    $ts_lectura = is_numeric($fecha_registro) ? (int)$fecha_registro : strtotime($fecha_registro);
    if (!$ts_lectura) {
        return $resultado;
    }

    // Como $historial_rangos[$pid] está ordenado por fecha_cambio ASC,
    // el PRIMER cambio cuya fecha_cambio sea MAYOR a la fecha de la lectura
    // contiene en *_ant los rangos exactos que estaban vigentes cuando se tomó esa lectura.
    foreach ($historial_rangos[$pid] as $cambio) {
        $ts_cambio = strtotime($cambio['fecha_cambio']);
        if ($ts_cambio && $ts_lectura < $ts_cambio) {
            $resultado['rojo_bajo'] = $cambio['rojo_bajo_ant'];
            $resultado['amarillo_bajo'] = $cambio['amarillo_bajo_ant'];
            $resultado['amarillo_alto'] = $cambio['amarillo_alto_ant'];
            $resultado['rojo_alto'] = $cambio['rojo_alto_ant'];
            $resultado['es_rango_anterior'] = true;

            $rb_a = $cambio['rojo_bajo_ant'] !== null ? (float)$cambio['rojo_bajo_ant'] : '-';
            $ab_a = $cambio['amarillo_bajo_ant'] !== null ? (float)$cambio['amarillo_bajo_ant'] : '-';
            $aa_a = $cambio['amarillo_alto_ant'] !== null ? (float)$cambio['amarillo_alto_ant'] : '-';
            $ra_a = $cambio['rojo_alto_ant'] !== null ? (float)$cambio['rojo_alto_ant'] : '-';

            $fecha_fmt = date('d/m/Y H:i', $ts_cambio);
            $resultado['info_cambio'] = [
                'fecha_cambio' => $cambio['fecha_cambio'],
                'fecha_cambio_fmt' => $fecha_fmt,
                'admin_nomina' => $cambio['admin_nomina'],
                'motivo' => $cambio['motivo'] ?? '',
                'resumen_anterior' => "Óptimo: > {$ab_a} a < {$aa_a} | Alerta: {$rb_a}..{$ab_a} / {$aa_a}..{$ra_a} | Crítico: <= {$rb_a} / >= {$ra_a}",
                'tooltip' => "Evaluado con rango anterior (modificado el {$fecha_fmt} por Nómina {$cambio['admin_nomina']}). Rango aplicado: Óptimo > {$ab_a} a < {$aa_a} (Crítico <= {$rb_a} o >= {$ra_a})"
            ];
            break;
        }
    }

    return $resultado;
}
?>

