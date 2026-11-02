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

    // Votators de Producto (83: Producto Votator, 84..89: Votator 1 al 6)
    232 => ['bitacora_chillers_votator', 'temp_entrada_producto_c'],
    233 => ['bitacora_chillers_votator', 'temp_salida_producto_c'],
    234 => ['bitacora_chillers_votator', 'delta_temp_producto_c'],
    235 => ['bitacora_chillers_votator', 'flujo'],
    236 => ['bitacora_chillers_votator', 'textura_visual'],
    237 => ['bitacora_chillers_votator', 'lubricacion_votator'],

    562 => ['bitacora_chillers_votator', 'temp_entrada_producto_c'],
    563 => ['bitacora_chillers_votator', 'temp_salida_producto_c'],
    564 => ['bitacora_chillers_votator', 'delta_temp_producto_c'],
    565 => ['bitacora_chillers_votator', 'flujo'],
    566 => ['bitacora_chillers_votator', 'textura_visual'],
    567 => ['bitacora_chillers_votator', 'lubricacion_votator'],

    568 => ['bitacora_chillers_votator', 'temp_entrada_producto_c'],
    569 => ['bitacora_chillers_votator', 'temp_salida_producto_c'],
    570 => ['bitacora_chillers_votator', 'delta_temp_producto_c'],
    571 => ['bitacora_chillers_votator', 'flujo'],
    572 => ['bitacora_chillers_votator', 'textura_visual'],
    573 => ['bitacora_chillers_votator', 'lubricacion_votator'],

    574 => ['bitacora_chillers_votator', 'temp_entrada_producto_c'],
    575 => ['bitacora_chillers_votator', 'temp_salida_producto_c'],
    576 => ['bitacora_chillers_votator', 'delta_temp_producto_c'],
    577 => ['bitacora_chillers_votator', 'flujo'],
    578 => ['bitacora_chillers_votator', 'textura_visual'],
    579 => ['bitacora_chillers_votator', 'lubricacion_votator'],

    580 => ['bitacora_chillers_votator', 'temp_entrada_producto_c'],
    581 => ['bitacora_chillers_votator', 'temp_salida_producto_c'],
    582 => ['bitacora_chillers_votator', 'delta_temp_producto_c'],
    583 => ['bitacora_chillers_votator', 'flujo'],
    584 => ['bitacora_chillers_votator', 'textura_visual'],
    585 => ['bitacora_chillers_votator', 'lubricacion_votator'],

    586 => ['bitacora_chillers_votator', 'temp_entrada_producto_c'],
    587 => ['bitacora_chillers_votator', 'temp_salida_producto_c'],
    588 => ['bitacora_chillers_votator', 'delta_temp_producto_c'],
    589 => ['bitacora_chillers_votator', 'flujo'],
    590 => ['bitacora_chillers_votator', 'textura_visual'],
    591 => ['bitacora_chillers_votator', 'lubricacion_votator'],

    592 => ['bitacora_chillers_votator', 'temp_entrada_producto_c'],
    593 => ['bitacora_chillers_votator', 'temp_salida_producto_c'],
    594 => ['bitacora_chillers_votator', 'delta_temp_producto_c'],
    595 => ['bitacora_chillers_votator', 'flujo'],
    596 => ['bitacora_chillers_votator', 'textura_visual'],
    597 => ['bitacora_chillers_votator', 'lubricacion_votator'],

    // Chillers Normales (91: 1 al 4, 92: 5 al 7)
    196 => ['bitacora_chillers_normales', 'concentracion_glicol_porc'],
    186 => ['bitacora_chillers_normales', 'nivel_glicol_porc'],
    187 => ['bitacora_chillers_normales', 'frecuencia_bombas_hz'],
    188 => ['bitacora_chillers_normales', 'temp_in_ch1_2_c'],
    189 => ['bitacora_chillers_normales', 'temp_out_ch1_2_c'],
    190 => ['bitacora_chillers_normales', 'delta_temp_ch1_2_c'],
    191 => ['bitacora_chillers_normales', 'temp_in_ch3_4_c'],
    192 => ['bitacora_chillers_normales', 'temp_out_ch3_4_c'],
    193 => ['bitacora_chillers_normales', 'delta_temp_ch3_4_c'],
    194 => ['bitacora_chillers_normales', 'presion_bombas_glicol_psi'],
    209 => ['bitacora_chillers_normales', 'rutina_alarmas'],

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
    1 => 'Chillers Normales (1 al 4)',
    2 => 'Chillers Normales (5 al 7)',
    30 => 'Compresor 30 HP',
    50 => 'Compresor 50 HP',
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
    83 => 'Producto Votator',
    84 => 'Votator 1',
    85 => 'Votator 2',
    86 => 'Votator 3',
    87 => 'Votator 4',
    88 => 'Votator 5',
    89 => 'Votator 6',
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
    } elseif (in_array($equipo_id, [81, 82, 83, 84, 85, 86, 87, 88, 89])) {
        $tabla = 'bitacora_chillers_votator';
    } elseif (in_array($equipo_id, [1, 2, 91, 92])) {
        $tabla = 'bitacora_chillers_normales';
    } elseif (in_array($equipo_id, [12, 13, 30, 50])) {
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

    // Agregar las variables capturadas como cadena para soportar tanto valores numéricos como 'F.O.' / 'LAVADO'
    foreach ($valoresCapturados as $col => $val) {
        $cols[] = "`$col`";
        $vals[] = (string)$val;
        $types .= 's';
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

// =========================================================================
// INTEGRACIÓN DIRECTA DE CONCENTRADORES CON SIS_EXTRACCION
// (bd_extraccion.datos_concentradores y progel_procesos.datos_concentradores)
// =========================================================================
$GLOBALS['MAP_CONCENTRADORES_SIS_EXTRACCION'] = [
    14 => [
        'ext_id' => 9,
        'proc_tipo' => 'Conc1',
        'orden_params' => [242, 238, 243, 239, 241, 599, 240, 600, 601, 244, 602],
        'cols' => [
            242 => ['temperatura',       'temperatura'],
            238 => ['solidos_entrada',   'sol_ent'],
            243 => ['solidos_salida',    'sol_sal'],
            239 => ['flujo',             null],
            241 => ['vacio',             'vacio'],
            599 => ['corriente',         'amperaje'],
            240 => ['presion_vapor',     'presion'],
            600 => ['apertura_valvula',  null],
            601 => ['nivel_condensados', null],
            244 => ['flujo_salida',      null],
            602 => ['temp_salida',       null],
        ]
    ],
    15 => [
        'ext_id' => 10,
        'proc_tipo' => 'Conc2',
        'orden_params' => [249, 245, 250, 246, 248, 603, 247, 604, 605, 251, 606],
        'cols' => [
            249 => ['temperatura',       'temperatura'],
            245 => ['solidos_entrada',   'sol_ent'],
            250 => ['solidos_salida',    'sol_sal'],
            246 => ['flujo',             null],
            248 => ['vacio',             'vacio'],
            603 => ['corriente',         'amperaje'],
            247 => ['presion_vapor',     'presion'],
            604 => ['apertura_valvula',  null],
            605 => ['nivel_condensados', null],
            251 => ['flujo_salida',      null],
            606 => ['temp_salida',       null],
        ]
    ],
    16 => [
        'ext_id' => 11,
        'proc_tipo' => 'Conc3',
        'orden_params' => [256, 252, 257, 253, 255, 607, 254, 608, 609, 258, 610],
        'cols' => [
            256 => ['temperatura',       'temperatura'],
            252 => ['solidos_entrada',   'sol_ent'],
            257 => ['solidos_salida',    'sol_sal'],
            253 => ['flujo',             null],
            255 => ['vacio',             'vacio'],
            607 => ['corriente',         'amperaje'],
            254 => ['presion_vapor',     'presion'],
            608 => ['apertura_valvula',  null],
            609 => ['nivel_condensados', null],
            258 => ['flujo_salida',      null],
            610 => ['temp_salida',       null],
        ]
    ],
    17 => [
        'ext_id' => 12,
        'proc_tipo' => 'Conc4',
        'orden_params' => [263, 259, 264, 260, 262, 611, 261, 612, 613, 265, 614],
        'cols' => [
            263 => ['temperatura',       'temperatura'],
            259 => ['solidos_entrada',   'sol_ent'],
            264 => ['solidos_salida',    'sol_sal'],
            260 => ['flujo',             null],
            262 => ['vacio',             'vacio'],
            611 => ['corriente',         'amperaje'],
            261 => ['presion_vapor',     'presion'],
            612 => ['apertura_valvula',  null],
            613 => ['nivel_condensados', null],
            265 => ['flujo_salida',      null],
            614 => ['temp_salida',       null],
        ]
    ],
    18 => [
        'ext_id' => 13,
        'proc_tipo' => 'Invertido',
        'orden_params' => [266, 268, 272, 267, 275, 269, 270, 271, 273, 274],
        'cols' => [
            266 => ['temperatura',     'temperatura'],
            268 => ['solidos_entrada', 'sol_ent'],
            272 => ['solidos_salida',  'sol_sal'],
            267 => ['flujo',           null],
            275 => ['flujo_salida',    null],
            269 => ['flujo_caldo',     null],
            270 => ['presion_vapor',   'presion'],
            271 => ['vacio',           'vacio'],
            273 => ['temp_interna',    null],
            274 => ['temp_salida',     null],
        ]
    ]
];

$GLOBALS['TEXTOS_SEMAFORO_CONCENTRADORES'] = [
    // Concentrador 1 (14)
    242 => ['verde' => '< 50 °C',    'amarillo' => '50 a 54 °C',      'rojo' => '> 54 °C'],
    238 => ['verde' => '21 – 22 %',  'amarillo' => '19 – 20 %',       'rojo' => '< 19 %'],
    243 => ['verde' => '≥ 37 %',     'amarillo' => '34 a <37 %',      'rojo' => '< 34 %'],
    239 => ['verde' => '34 m³/h',    'amarillo' => '30 a <34 m³/h',   'rojo' => '<30 o >34 m³/h'],
    241 => ['verde' => '> 525 mmHg', 'amarillo' => '490 a <525 mmHg', 'rojo' => '< 490 mmHg'],
    599 => ['verde' => '< 85 A',     'amarillo' => '85 A',            'rojo' => '≥ 87 A'],
    240 => ['verde' => '< 32 psi',   'amarillo' => '32 psi',          'rojo' => '> 32 psi'],
    600 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],
    601 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],
    244 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],
    602 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],

    // Concentrador 2 (15)
    249 => ['verde' => '< 50 °C',    'amarillo' => '50 a 54 °C',      'rojo' => '> 54 °C'],
    245 => ['verde' => '21 – 22 %',  'amarillo' => '19 – 20 %',       'rojo' => '< 19 %'],
    250 => ['verde' => '≥ 37 %',     'amarillo' => '34 a <37 %',      'rojo' => '< 34 %'],
    246 => ['verde' => '35 m³/h',    'amarillo' => '31 a <35 m³/h',   'rojo' => '<31 o >35 m³/h'],
    248 => ['verde' => '> 525 mmHg', 'amarillo' => '490 a <525 mmHg', 'rojo' => '< 490 mmHg'],
    603 => ['verde' => '< 60 A',     'amarillo' => '60 a 61 A',       'rojo' => '> 61 A'],
    247 => ['verde' => '< 32 psi',   'amarillo' => '32 psi',          'rojo' => '> 32 psi'],
    604 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],
    605 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],
    251 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],
    606 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],

    // Concentrador 3 (16)
    256 => ['verde' => '< 50 °C',    'amarillo' => '50 a 54 °C',      'rojo' => '> 54 °C'],
    252 => ['verde' => '21 – 22 %',  'amarillo' => '19 – 20 %',       'rojo' => '< 19 %'],
    257 => ['verde' => '≥ 37 %',     'amarillo' => '34 a <37 %',      'rojo' => '< 34 %'],
    253 => ['verde' => '35 m³/h',    'amarillo' => '31 a <35 m³/h',   'rojo' => '<31 o >35 m³/h'],
    255 => ['verde' => '> 525 mmHg', 'amarillo' => '490 a <525 mmHg', 'rojo' => '< 490 mmHg'],
    607 => ['verde' => '< 60 A',     'amarillo' => '60 a 62 A',       'rojo' => '> 62 A'],
    254 => ['verde' => '< 32 psi',   'amarillo' => '32 psi',          'rojo' => '> 32 psi'],
    608 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],
    609 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],
    258 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],
    610 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],

    // Concentrador 4 (17)
    263 => ['verde' => '< 50 °C',    'amarillo' => '50 a 54 °C',      'rojo' => '> 54 °C'],
    259 => ['verde' => '21 – 22 %',  'amarillo' => '19 – 20 %',       'rojo' => '< 19 %'],
    264 => ['verde' => '≥ 37 %',     'amarillo' => '34 a <37 %',      'rojo' => '< 34 %'],
    260 => ['verde' => '38 m³/h',    'amarillo' => '31 a <38 m³/h',   'rojo' => '<31 o >38 m³/h'],
    262 => ['verde' => '> 525 mmHg', 'amarillo' => '490 a <525 mmHg', 'rojo' => '< 490 mmHg'],
    611 => ['verde' => '< 60 A',     'amarillo' => '60 a 62 A',       'rojo' => '> 62 A'],
    261 => ['verde' => '< 32 psi',   'amarillo' => '32 psi',          'rojo' => '> 32 psi'],
    612 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],
    613 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],
    265 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],
    614 => ['verde' => 'Sensor',     'amarillo' => '-',               'rojo' => '-'],

    // Concentrador Invertido (18)
    266 => ['verde' => '<50-54 °C',    'amarillo' => '55-56 °C',      'rojo' => '>57 °C'],
    268 => ['verde' => '18-20 %',      'amarillo' => '16-18 %',       'rojo' => '>22 %'],
    272 => ['verde' => '>31 %',        'amarillo' => '28-31 %',       'rojo' => '<28 %'],
    267 => ['verde' => '<60 LPM',      'amarillo' => '59-50 LPM',     'rojo' => '<50 LPM'],
    275 => ['verde' => 'Sensor',       'amarillo' => '-',             'rojo' => '-'],
    269 => ['verde' => '35-39 LPM',    'amarillo' => '34-35 LPM',     'rojo' => '<47 LPM'],
    270 => ['verde' => '57 PSI',       'amarillo' => '<57-54 PSI',    'rojo' => '<54 PSI'],
    271 => ['verde' => '≥0.85 kg/cm²', 'amarillo' => '<0.84 kg/cm²',  'rojo' => '<0.82 kg/cm²'],
    273 => ['verde' => '25-26 °C',     'amarillo' => '27-28 °C',      'rojo' => '>29 °C'],
    274 => ['verde' => '35-36 °C',     'amarillo' => '37-38 °C',      'rojo' => '>39 °C'],
];
