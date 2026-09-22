<?php
/**
 * includes/mapeo_v2.php
 * Catálogo de equivalencias y función helper para insertar registros
 * directamente en la base de datos limpia Progel_coreV2.
 */

// Mapeo exhaustivo de parametro_id (BD antigua) -> [tabla_destino, columna_destino] (BD Progel_coreV2)
$GLOBALS['MAP_PARAMETROS_V2'] = [
    // Chillers Votator (81: 1 al 4, 82: 5 al 7)
    342 => ['bitacora_chillers_votator', 'temp_inyeccion_maestro_c'],
    343 => ['bitacora_chillers_votator', 'temp_retorno_maestro_c'],
    344 => ['bitacora_chillers_votator', 'delta_temp_maestro_c'],
    345 => ['bitacora_chillers_votator', 'temp_inyeccion_esclavo_c'],
    346 => ['bitacora_chillers_votator', 'temp_retorno_esclavo_c'],
    347 => ['bitacora_chillers_votator', 'delta_temp_esclavo_c'],
    348 => ['bitacora_chillers_votator', 'presion_inyeccion_glicol_psi'],

    349 => ['bitacora_chillers_votator', 'temp_inyeccion_maestro_c'],
    350 => ['bitacora_chillers_votator', 'temp_retorno_maestro_c'],
    351 => ['bitacora_chillers_votator', 'delta_temp_maestro_c'],
    352 => ['bitacora_chillers_votator', 'temp_inyeccion_esclavo_c'],
    353 => ['bitacora_chillers_votator', 'temp_retorno_esclavo_c'],
    354 => ['bitacora_chillers_votator', 'delta_temp_esclavo_c'],
    355 => ['bitacora_chillers_votator', 'presion_inyeccion_glicol_psi'],

    // Chillers Normales (91: 1 al 4, 92: 5 al 7)
    356 => ['bitacora_chillers_normales', 'concentracion_glicol_porc'],
    357 => ['bitacora_chillers_normales', 'presion_baja_ch1_psi'],
    358 => ['bitacora_chillers_normales', 'presion_alta_ch1_psi'],
    359 => ['bitacora_chillers_normales', 'presion_baja_ch2_psi'],
    360 => ['bitacora_chillers_normales', 'presion_alta_ch2_psi'],
    361 => ['bitacora_chillers_normales', 'presion_baja_ch3_psi'],
    362 => ['bitacora_chillers_normales', 'presion_alta_ch3_psi'],
    363 => ['bitacora_chillers_normales', 'presion_baja_ch4_psi'],
    364 => ['bitacora_chillers_normales', 'presion_alta_ch4_psi'],
    365 => ['bitacora_chillers_normales', 'rutina_limpieza_condensadores'],
    366 => ['bitacora_chillers_normales', 'rutina_limpieza_filtros_bombas'],
    367 => ['bitacora_chillers_normales', 'rutina_limpieza_filtros_glicol'],
    368 => ['bitacora_chillers_normales', 'rutina_limpieza_filtros_succion'],
    369 => ['bitacora_chillers_normales', 'rutina_alarmas'],
    370 => ['bitacora_chillers_normales', 'nivel_glicol_porc'],
    371 => ['bitacora_chillers_normales', 'frecuencia_bombas_hz'],
    372 => ['bitacora_chillers_normales', 'temp_in_ch1_2_c'],
    373 => ['bitacora_chillers_normales', 'temp_out_ch1_2_c'],
    374 => ['bitacora_chillers_normales', 'delta_temp_ch1_2_c'],
    375 => ['bitacora_chillers_normales', 'temp_in_ch3_4_c'],
    376 => ['bitacora_chillers_normales', 'temp_out_ch3_4_c'],
    377 => ['bitacora_chillers_normales', 'delta_temp_ch3_4_c'],
    378 => ['bitacora_chillers_normales', 'presion_bombas_glicol_psi'],

    399 => ['bitacora_chillers_normales', 'concentracion_glicol_porc'],
    400 => ['bitacora_chillers_normales', 'presion_baja_ch5_psi'],
    401 => ['bitacora_chillers_normales', 'presion_alta_ch5_psi'],
    402 => ['bitacora_chillers_normales', 'presion_baja_ch6_psi'],
    403 => ['bitacora_chillers_normales', 'presion_alta_ch6_psi'],
    404 => ['bitacora_chillers_normales', 'presion_baja_ch7_psi'],
    405 => ['bitacora_chillers_normales', 'presion_alta_ch7_psi'],
    406 => ['bitacora_chillers_normales', 'rutina_limpieza_condensadores'],
    407 => ['bitacora_chillers_normales', 'rutina_limpieza_filtros_bombas'],
    408 => ['bitacora_chillers_normales', 'rutina_limpieza_filtros_glicol'],
    409 => ['bitacora_chillers_normales', 'rutina_limpieza_filtros_succion'],
    410 => ['bitacora_chillers_normales', 'nivel_glicol_porc'],
    411 => ['bitacora_chillers_normales', 'frecuencia_bombas_hz'],
    412 => ['bitacora_chillers_normales', 'temp_in_ch5_6_c'],
    413 => ['bitacora_chillers_normales', 'temp_out_ch5_6_c'],
    414 => ['bitacora_chillers_normales', 'delta_temp_ch5_6_c'],
    415 => ['bitacora_chillers_normales', 'temp_in_ch7_c'],
    416 => ['bitacora_chillers_normales', 'temp_out_ch7_c'],
    417 => ['bitacora_chillers_normales', 'delta_temp_ch7_c'],
    418 => ['bitacora_chillers_normales', 'presion_bombas_glicol_psi'],
    419 => ['bitacora_chillers_normales', 'rutina_alarmas'],

    // Compresores (12: 30 HP, 13: 50 HP)
    420 => ['bitacora_compresores', 'humedad_ambiental_porc'],
    421 => ['bitacora_compresores', 'temp_ambiental_c'],
    422 => ['bitacora_compresores', 'limpieza_filtros'],
    423 => ['bitacora_compresores', 'horas_trabajadas'],
    424 => ['bitacora_compresores', 'inspeccion_alarmas'],

    425 => ['bitacora_compresores', 'humedad_ambiental_porc'],
    426 => ['bitacora_compresores', 'temp_ambiental_c'],
    427 => ['bitacora_compresores', 'limpieza_filtros'],
    428 => ['bitacora_compresores', 'horas_trabajadas'],
    429 => ['bitacora_compresores', 'inspeccion_alarmas'],

    // Concentradores (14, 15, 16, 17)
    238 => ['bitacora_concentradores', 'solidos_entrada_porc'],
    239 => ['bitacora_concentradores', 'flujo_entrada_lpm'],
    240 => ['bitacora_concentradores', 'presion_vapor_psi'],
    241 => ['bitacora_concentradores', 'vacio_mmhg'],
    242 => ['bitacora_concentradores', 'temp_salida_grenetina_c'],
    243 => ['bitacora_concentradores', 'solidos_salida_porc'],
    244 => ['bitacora_concentradores', 'flujo_salida_lpm'],

    245 => ['bitacora_concentradores', 'solidos_entrada_porc'],
    246 => ['bitacora_concentradores', 'flujo_entrada_lpm'],
    247 => ['bitacora_concentradores', 'presion_vapor_psi'],
    248 => ['bitacora_concentradores', 'vacio_mmhg'],
    249 => ['bitacora_concentradores', 'temp_salida_grenetina_c'],
    250 => ['bitacora_concentradores', 'solidos_salida_porc'],
    251 => ['bitacora_concentradores', 'flujo_salida_lpm'],

    252 => ['bitacora_concentradores', 'solidos_entrada_porc'],
    253 => ['bitacora_concentradores', 'flujo_entrada_lpm'],
    254 => ['bitacora_concentradores', 'presion_vapor_psi'],
    255 => ['bitacora_concentradores', 'vacio_mmhg'],
    256 => ['bitacora_concentradores', 'temp_salida_grenetina_c'],
    257 => ['bitacora_concentradores', 'solidos_salida_porc'],
    258 => ['bitacora_concentradores', 'flujo_salida_lpm'],

    259 => ['bitacora_concentradores', 'solidos_entrada_porc'],
    260 => ['bitacora_concentradores', 'flujo_entrada_lpm'],
    261 => ['bitacora_concentradores', 'presion_vapor_psi'],
    262 => ['bitacora_concentradores', 'vacio_mmhg'],
    263 => ['bitacora_concentradores', 'temp_salida_grenetina_c'],
    264 => ['bitacora_concentradores', 'solidos_salida_porc'],
    265 => ['bitacora_concentradores', 'flujo_salida_lpm'],

    // Concentrador Invertido (18)
    266 => ['bitacora_concentradores', 'temp_caldo_entrada_c'],
    267 => ['bitacora_concentradores', 'flujo_entrada_lpm'],
    268 => ['bitacora_concentradores', 'solidos_entrada_porc'],
    269 => ['bitacora_concentradores', 'flujo_salida_lpm'],
    270 => ['bitacora_concentradores', 'presion_vapor_psi'],
    271 => ['bitacora_concentradores', 'presion_vacio_kg_cm2'],
    272 => ['bitacora_concentradores', 'solidos_salida_porc'],
    273 => ['bitacora_concentradores', 'temp_agua_in_condensador_c'],
    274 => ['bitacora_concentradores', 'temp_agua_out_condensador_c'],
    275 => ['bitacora_concentradores', 'flujo_salida_lpm'],

    // Secadores (21, 22, 23, 24)
    450 => ['bitacora_secadores', 'flujo_l_min'],
    451 => ['bitacora_secadores', 'velocidad_banda_m_h'],
    452 => ['bitacora_secadores', 'altura_galleta_cm'],
    453 => ['bitacora_secadores', 'humedad_churro_recamara_5_porc'],
    454 => ['bitacora_secadores', 'humedad_churro_penultima_porc'],
    455 => ['bitacora_secadores', 'humedad_relativa_recamara_5_porc'],
    456 => ['bitacora_secadores', 'humedad_producto_final_porc'],
    457 => ['bitacora_secadores', 'textura_galleta'],

    458 => ['bitacora_secadores', 'flujo_l_min'],
    459 => ['bitacora_secadores', 'velocidad_banda_m_h'],
    460 => ['bitacora_secadores', 'altura_galleta_cm'],
    461 => ['bitacora_secadores', 'humedad_churro_recamara_5_porc'],
    462 => ['bitacora_secadores', 'humedad_churro_penultima_porc'],
    463 => ['bitacora_secadores', 'humedad_relativa_recamara_5_porc'],
    464 => ['bitacora_secadores', 'humedad_producto_final_porc'],
    465 => ['bitacora_secadores', 'textura_galleta'],

    466 => ['bitacora_secadores', 'flujo_l_min'],
    467 => ['bitacora_secadores', 'frecuencia_hz'],
    468 => ['bitacora_secadores', 'altura_galleta_cm'],
    469 => ['bitacora_secadores', 'humedad_churro_recamara_5_porc'],
    470 => ['bitacora_secadores', 'humedad_churro_penultima_porc'],
    471 => ['bitacora_secadores', 'humedad_relativa_recamara_5_porc'],
    472 => ['bitacora_secadores', 'humedad_producto_final_porc'],
    473 => ['bitacora_secadores', 'temp_recamara_1_c'],
    474 => ['bitacora_secadores', 'temp_recamara_2_c'],
    475 => ['bitacora_secadores', 'temp_recamara_3_c'],
    476 => ['bitacora_secadores', 'temp_recamara_4_c'],
    477 => ['bitacora_secadores', 'textura_galleta'],

    478 => ['bitacora_secadores', 'humedad_churro_recamara_5_porc'],
    479 => ['bitacora_secadores', 'altura_galleta_cm'],
    480 => ['bitacora_secadores', 'frecuencia_hz'],
    481 => ['bitacora_secadores', 'flujo_l_min'],
    482 => ['bitacora_secadores', 'humedad_churro_penultima_porc'],
    483 => ['bitacora_secadores', 'humedad_relativa_recamara_5_porc'],
    484 => ['bitacora_secadores', 'humedad_producto_final_porc'],
    485 => ['bitacora_secadores', 'temp_recamara_1_c'],
    486 => ['bitacora_secadores', 'temp_recamara_2_c'],
    487 => ['bitacora_secadores', 'temp_recamara_3_c'],
    488 => ['bitacora_secadores', 'temp_recamara_4_c'],
    489 => ['bitacora_secadores', 'textura_galleta'],
];

$GLOBALS['NOMBRES_EQUIPOS_V2'] = [
    12 => 'Compresor 30 HP',
    13 => 'Compresor 50 HP',
    14 => 'Concentrador 1',
    15 => 'Concentrador 2',
    16 => 'Concentrador 3',
    17 => 'Concentrador 4',
    18 => 'Concentrador Invertido',
    21 => 'Secador 1',
    22 => 'Secador 2',
    23 => 'Secador 3',
    24 => 'Secador 4',
    81 => 'Chillers Votator (1 al 4)',
    82 => 'Chillers Votator (5 al 7)',
    91 => 'Chillers Normales (1 al 4)',
    92 => 'Chillers Normales (5 al 7)'
];

/**
 * Inserta un registro estructurado y horizontal en Progel_coreV2.
 * 
 * @param mysqli $conn_v2 Conexión activa a Progel_coreV2
 * @param int $equipo_id ID del equipo
 * @param string $numero_nomina Nómina del operador
 * @param string|null $lote Lote de producción (si aplica)
 * @param array $valoresCapturados Arreglo asociativo ['columna' => valor]
 * @param string $observaciones Notas o justificaciones
 * @return bool True si se insertó con éxito
 */
function guardarRegistroEnV2($conn_v2, $equipo_id, $numero_nomina, $lote, array $valoresCapturados, $observaciones = '') {
    if (!$conn_v2 || empty($valoresCapturados)) {
        return false;
    }

    $nombres = $GLOBALS['NOMBRES_EQUIPOS_V2'];
    $equipoNombre = $nombres[$equipo_id] ?? "Equipo $equipo_id";

    // Determinar la tabla de destino
    $tabla = '';
    if (in_array($equipo_id, [21, 22, 23, 24])) {
        $tabla = 'bitacora_secadores';
    } elseif (in_array($equipo_id, [14, 15, 16, 17, 18])) {
        $tabla = 'bitacora_concentradores';
    } elseif (in_array($equipo_id, [81, 82])) {
        $tabla = 'bitacora_chillers_votator';
    } elseif (in_array($equipo_id, [91, 92])) {
        $tabla = 'bitacora_chillers_normales';
    } elseif (in_array($equipo_id, [12, 13])) {
        $tabla = 'bitacora_compresores';
    } else {
        return false; // Equipo no mapeado
    }

    $fecha = date('Y-m-d');
    $hora = (int)date('G');

    $cols = ['fecha', 'hora', 'operador_nomina', 'observaciones'];
    $vals = [$fecha, $hora, $numero_nomina, $observaciones];
    $types = 'siss';

    // Columna de identificación de equipo y lote según la tabla
    if ($tabla === 'bitacora_secadores') {
        $cols[] = 'secador_nombre';
        $vals[] = $equipoNombre;
        $types .= 's';
        $cols[] = 'lote';
        $vals[] = $lote;
        $types .= 's';
    } elseif ($tabla === 'bitacora_concentradores') {
        $cols[] = 'concentrador_nombre';
        $vals[] = $equipoNombre;
        $types .= 's';
        $cols[] = 'lote';
        $vals[] = $lote;
        $types .= 's';
    } elseif ($tabla === 'bitacora_chillers_votator' || $tabla === 'bitacora_chillers_normales') {
        $cols[] = 'equipo_nombre';
        $vals[] = $equipoNombre;
        $types .= 's';
    } elseif ($tabla === 'bitacora_compresores') {
        $cols[] = 'compresor_nombre';
        $vals[] = $equipoNombre;
        $types .= 's';
    }

    // Agregar las variables capturadas
    foreach ($valoresCapturados as $col => $val) {
        $cols[] = "`$col`";
        $vals[] = $val;
        $types .= is_numeric($val) ? 'd' : 's';
    }

    $colList = implode(', ', array_map(function($c) {
        return strpos($c, '`') !== false ? $c : "`$c`";
    }, $cols));
    $placeholders = implode(', ', array_fill(0, count($vals), '?'));

    $sql = "INSERT INTO `$tabla` ($colList) VALUES ($placeholders)";
    $stmt = mysqli_prepare($conn_v2, $sql);

    if (!$stmt) {
        error_log("Error al preparar INSERT en Progel_coreV2 ($tabla): " . mysqli_error($conn_v2));
        return false;
    }

    // mysqli_stmt_bind_param dinámico
    $params = array_merge([$stmt, $types], $vals);
    $refs = [];
    foreach ($params as $key => $value) {
        $refs[$key] = &$params[$key];
    }
    call_user_func_array('mysqli_stmt_bind_param', $refs);

    $res = mysqli_stmt_execute($stmt);
    if (!$res) {
        error_log("Error al ejecutar INSERT en Progel_coreV2 ($tabla): " . mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);

    return $res;
}

/**
 * Consulta la última lectura de un parámetro en Progel_coreV2 (últimos 90 minutos)
 * para pintar los semáforos y badges de la pantalla.
 */
function obtenerLecturaRecienteV2($conn_v2, $param_id, $equipo_id = 0) {
    if (!$conn_v2 || !isset($GLOBALS['MAP_PARAMETROS_V2'][$param_id])) {
        return null;
    }

    $tabla = $GLOBALS['MAP_PARAMETROS_V2'][$param_id][0];
    $col = $GLOBALS['MAP_PARAMETROS_V2'][$param_id][1];
    $nombres = $GLOBALS['NOMBRES_EQUIPOS_V2'];
    $eqNombre = $nombres[$equipo_id] ?? '';

    $whereEquipo = "1=1";
    if (!empty($eqNombre)) {
        $escNombre = mysqli_real_escape_string($conn_v2, $eqNombre);
        if ($tabla === 'bitacora_secadores') {
            $whereEquipo = "secador_nombre = '$escNombre'";
        } elseif ($tabla === 'bitacora_concentradores') {
            $whereEquipo = "concentrador_nombre = '$escNombre'";
        } elseif ($tabla === 'bitacora_chillers_votator' || $tabla === 'bitacora_chillers_normales') {
            $whereEquipo = "equipo_nombre = '$escNombre'";
        } elseif ($tabla === 'bitacora_compresores') {
            $whereEquipo = "compresor_nombre = '$escNombre'";
        }
    }

    $q = "SELECT `$col` AS val, observaciones FROM `$tabla` 
          WHERE $whereEquipo 
          AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 90 MINUTE)
          AND `$col` IS NOT NULL
          ORDER BY id DESC LIMIT 1";

    $res = mysqli_query($conn_v2, $q);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        if ($row['val'] !== null && $row['val'] !== '') {
            return [
                'valor_capturado' => $row['val'],
                'observaciones' => $row['observaciones'] ?? ''
            ];
        }
    }

    return null;
}


