<?php
// Archivo: logica_reporte_maestro.php

// Función exclusiva para saber el estado de los cocedores desde la BD vieja
function obtenerEstadoCocedores($conn_procesos, $id_datos_hora) {    
    // Si no hay un ID válido, no hay nada que buscar.
    if (empty($id_datos_hora)) {
        return ['activos' => '', 'fo' => '', 'total_activos' => 0];
    }

    // Usar sentencias preparadas para seguridad
    $sql = "SELECT numero_cocedor, estado_fo, temp_entrada, temp_salida
            FROM datos_cocedores 
            WHERE datos_hora = ?
            ORDER BY numero_cocedor ASC";
            
    $stmt = mysqli_prepare($conn_procesos, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id_datos_hora);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $activos = [];
    $fuera_operacion = [];
    $temps_entrada = [];
    $temps_salida = [];
    
    // Verificamos si hay datos
    if ($resultado && mysqli_num_rows($resultado) > 0) {
        while ($fila = mysqli_fetch_assoc($resultado)) {
            if ($fila['estado_fo'] == 0) {
                $activos[] = $fila['numero_cocedor']; // Trabajando
                $temps_entrada[] = $fila['temp_entrada'];
                $temps_salida[] = $fila['temp_salida'];
            } else {
                $fuera_operacion[] = $fila['numero_cocedor']; // Apagado
            }
        }
    }
    
    mysqli_stmt_close($stmt);

    // Retornamos el arreglo
    $avg_temp_entrada = count($temps_entrada) > 0 ? array_sum($temps_entrada) / count($temps_entrada) : 0;
    $avg_temp_salida = count($temps_salida) > 0 ? array_sum($temps_salida) / count($temps_salida) : 0;

    return [
        'activos' => implode(", ", $activos),
        'fo' => implode(", ", $fuera_operacion),
        'total_activos' => count($activos),
        'avg_temp_entrada' => $avg_temp_entrada,
        'avg_temp_salida' => $avg_temp_salida
    ];
}

// Nota: Aquí mismo puedes ir agregando después las funciones para 
// jalar la humedad, los sólidos, etc., todo separadito del Core.

// Función para obtener los sólidos del clarificador desde la BD vieja
function obtenerSolidosClarificador($conn_procesos, $id_datos_hora) {
    // Si no hay un ID válido, no hay nada que buscar.
    if (empty($id_datos_hora)) {
        return null;
    }

    // Usar sentencias preparadas para seguridad
    $sql = "SELECT solidos FROM datos_clarificador WHERE id_datos_hora = ? LIMIT 1";
    
    $stmt = mysqli_prepare($conn_procesos, $sql);
    if (!$stmt) {
        // En un entorno de producción, sería mejor registrar el error que usar die().
        // error_log("Error al preparar la consulta de sólidos: " . mysqli_error($conn_procesos));
        return null;
    }
    
    mysqli_stmt_bind_param($stmt, "i", $id_datos_hora);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    
    $fila = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    return $fila ? $fila['solidos'] : null;
}

/**
 * Obtener lecturas de humedad de secadores desde la base de datos externa 'progel_procesos'
 * Tabla: calidad_humedad_secadores
 * Campos: id, fecha, hora, secador, hum_ultima, estado_fo, operador, creado_en
 * 
 * @param mysqli $conn_procesos Conexión a la base de datos progel_procesos
 * @param string $fecha_seleccionada Fecha base (YYYY-MM-DD)
 * @param string $turno 'diurno' o 'nocturno'
 * @return array Array indexado por [$hora_clave][$secador] = datos_fila
 */
function obtenerHumedadSecadoresProcesos($conn_procesos, $fecha_seleccionada, $turno = 'diurno') {
    $datos = [];
    if (!$conn_procesos) return $datos;

    if ($turno === 'diurno') {
        $sql = "SELECT id, fecha, hora, secador, hum_ultima, estado_fo, operador, creado_en 
                FROM calidad_humedad_secadores 
                WHERE fecha = ? AND HOUR(hora) BETWEEN 7 AND 18 AND secador BETWEEN 1 AND 4
                ORDER BY hora ASC, id ASC";
        $stmt = mysqli_prepare($conn_procesos, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 's', $fecha_seleccionada);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $h_int = (int)date('H', strtotime($row['hora']));
                $hora_clave = str_pad($h_int, 2, '0', STR_PAD_LEFT) . ':00';
                $sec = (int)$row['secador'];
                $datos[$hora_clave][$sec] = $row;
            }
            mysqli_stmt_close($stmt);
        }
    } else {
        $fecha_siguiente = date('Y-m-d', strtotime($fecha_seleccionada . ' +1 day'));
        $sql = "SELECT id, fecha, hora, secador, hum_ultima, estado_fo, operador, creado_en 
                FROM calidad_humedad_secadores 
                WHERE ((fecha = ? AND HOUR(hora) >= 19) OR (fecha = ? AND HOUR(hora) <= 6)) AND secador BETWEEN 1 AND 4
                ORDER BY fecha ASC, hora ASC, id ASC";
        $stmt = mysqli_prepare($conn_procesos, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $fecha_seleccionada, $fecha_siguiente);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($row = mysqli_fetch_assoc($res)) {
                $h_int = (int)date('H', strtotime($row['hora']));
                $hora_clave = str_pad($h_int, 2, '0', STR_PAD_LEFT) . ':00';
                $sec = (int)$row['secador'];
                $datos[$hora_clave][$sec] = $row;
            }
            mysqli_stmt_close($stmt);
        }
    }

    return $datos;
}

/**
 * Obtener clase de semáforo para la humedad del secador
 */
function getHumedadSemaforoClass($secador, $val, $estado_fo = 0) {
    if ($estado_fo == 1 || strtoupper(trim((string)$val)) === 'F.O.') {
        return 'semaforo-r-sabana'; 
    }
    if ($val === null || $val === '' || $val === '-') {
        return '';
    }
    $num = floatval($val);
    if ($secador == 1 || $secador == 2) {
        if ($num < 12) return 'semaforo-v-sabana';
        if ($num <= 15) return 'semaforo-a-sabana';
        return 'semaforo-r-sabana';
    } else {
        if ($num < 10) return 'semaforo-v-sabana';
        if ($num <= 12) return 'semaforo-a-sabana';
        return 'semaforo-r-sabana';
    }
}
?>