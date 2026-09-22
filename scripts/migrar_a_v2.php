<?php
/**
 * ====================================================================
 * SCRIPT DE MIGRACIÓN Y TRANSFORMACIÓN ETL: PROGEL_CORES -> PROGEL_V2
 * ====================================================================
 * Este script lee todos los datos históricos de 'progel_cores' y los
 * pivota / estructura en la nueva base de datos limpia 'progel_v2'.
 * 
 * NOTA: NO modifica, no borra ni altera nada en 'progel_cores'.
 * Es un proceso seguro de solo lectura en la BD origen.
 */

declare(ticks = 1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('memory_limit', '512M');
set_time_limit(0);

echo "====================================================================\n";
echo "  INICIANDO MIGRACIÓN AUTOMÁTICA: progel_cores -> Progel_coreV2\n";
echo "====================================================================\n\n";

$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdoCores = new PDO("mysql:host=$host;dbname=progel_cores;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdoV2 = new PDO("mysql:host=$host;dbname=Progel_coreV2;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("ERROR al conectar a MySQL: " . $e->getMessage() . "\n");
}

echo "[OK] Conectado exitosamente a ambas bases de datos.\n";

// Deshabilitar temporalmente foreign keys para truncar y llenar
$pdoV2->exec("SET FOREIGN_KEY_CHECKS = 0;");

$tablasV2 = [
    'catalogo_zonas',
    'catalogo_areas',
    'catalogo_equipos',
    'usuarios',
    'bitacora_chillers_votator',
    'bitacora_chillers_normales',
    'bitacora_compresores',
    'bitacora_concentradores',
    'bitacora_secadores',
    'produccion_reporte_maestro'
];

foreach ($tablasV2 as $tbl) {
    $pdoV2->exec("TRUNCATE TABLE `$tbl`;");
}
$pdoV2->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "[OK] Tablas de progel_v2 preparadas y limpias.\n\n";

// --------------------------------------------------------------------
// 1. MIGRAR CATÁLOGOS BASE
// --------------------------------------------------------------------
echo "1. Migrando catálogos base...\n";

// 1.1 Zonas
$zonas = $pdoCores->query("SELECT id, nombre FROM zonas")->fetchAll();
$stmtZona = $pdoV2->prepare("INSERT INTO catalogo_zonas (id, nombre) VALUES (?, ?)");
foreach ($zonas as $z) {
    $stmtZona->execute([$z['id'], $z['nombre']]);
}
echo "   -> " . count($zonas) . " zonas migradas.\n";

// 1.2 Áreas
$areas = $pdoCores->query("SELECT id, zona_id, nombre FROM areas")->fetchAll();
$stmtArea = $pdoV2->prepare("INSERT INTO catalogo_areas (id, zona_id, nombre) VALUES (?, ?, ?)");
foreach ($areas as $a) {
    $stmtArea->execute([$a['id'], $a['zona_id'], $a['nombre']]);
}
echo "   -> " . count($areas) . " áreas migradas.\n";

// 1.3 Equipos
$equipos = $pdoCores->query("SELECT id, nombre, area_id, tipo FROM equipos")->fetchAll();
$stmtEquipo = $pdoV2->prepare("INSERT INTO catalogo_equipos (id, codigo, nombre, area_id, tipo_equipo, activo) VALUES (?, ?, ?, ?, ?, 1)");
foreach ($equipos as $eq) {
    $tipo = 'otro';
    if ($eq['tipo'] === 'chiller_normal') $tipo = 'chiller_normal';
    elseif ($eq['tipo'] === 'votator') $tipo = 'chiller_votator';
    elseif ($eq['tipo'] === 'compresor') $tipo = 'compresor';
    elseif ($eq['tipo'] === 'concentrador') $tipo = 'concentrador';
    elseif ($eq['tipo'] === 'secador') $tipo = 'secador';

    $codigo = 'EQ-' . str_pad($eq['id'], 3, '0', STR_PAD_LEFT);
    $stmtEquipo->execute([$eq['id'], $codigo, $eq['nombre'], $eq['area_id'], $tipo]);
}
echo "   -> " . count($equipos) . " equipos en catálogo migrados.\n";

// 1.4 Usuarios
$usuarios = $pdoCores->query("SELECT nomina, nombre, rol, zona_asignada FROM usuarios")->fetchAll();
$stmtUsuario = $pdoV2->prepare("INSERT INTO usuarios (nomina, nombre_completo, rol, zona_asignada, activo) VALUES (?, ?, ?, ?, 1)");
foreach ($usuarios as $u) {
    $rol = strtolower(trim($u['rol']));
    if (!in_array($rol, ['operador', 'supervisor', 'admin', 'auditor'])) {
        $rol = 'operador';
    }
    $stmtUsuario->execute([$u['nomina'], $u['nombre'], $rol, $u['zona_asignada'] ?? 'TODAS']);
}
echo "   -> " . count($usuarios) . " usuarios migrados.\n\n";

// --------------------------------------------------------------------
// 2. MIGRAR REPORTE MAESTRO DE PRODUCCIÓN (sup_captura_produccion)
// --------------------------------------------------------------------
echo "2. Migrando sábana maestra de producción (sup_captura_produccion)...\n";
$sqlProd = "
    INSERT INTO Progel_coreV2.produccion_reporte_maestro (
        id, fecha, hora, consumo_cuero_kg, cocedores_manual, caldo_pre_uf, pre_concentrado, caldo_concentrado,
        votators_activos, flujo_votator_1_lh, flujo_votator_2_lh, flujo_votator_3_lh, flujo_votator_4_lh,
        flujo_votator_5_lh, flujo_votator_6_lh, solidos_brix, humedad_tunel_1_porc, humedad_tunel_2_porc,
        humedad_tunel_3_porc, humedad_tunel_4_porc, humedad_tunel_5_porc, velocidad_tunel_1_mh,
        velocidad_tunel_2_mh, velocidad_tunel_3_mh, velocidad_tunel_4_mh, kg_teoricos, kg_reales,
        rechazo_kg, remoler_kg, eficiencia_porcentaje, observaciones_acciones, fecha_registro_real
    )
    SELECT
        id, fecha, HOUR(hora),
        NULLIF(consumo_cuero, ''),
        NULLIF(cocedores_manual, ''),
        NULLIF(caldo_pre_uf, ''),
        NULLIF(pre_concentrado, ''),
        NULLIF(caldo_concentrado, ''),
        NULLIF(votators_activos, ''),
        NULLIF(flujo_v1, ''),
        NULLIF(flujo_v2, ''),
        NULLIF(flujo_v3, ''),
        NULLIF(flujo_v4, ''),
        NULLIF(flujo_v5, ''),
        NULLIF(flujo_v6, ''),
        NULLIF(solidos_brix, ''),
        NULLIF(hum_t1, ''),
        NULLIF(hum_t2, ''),
        NULLIF(hum_t3, ''),
        NULLIF(hum_t4, ''),
        NULLIF(hum_t5, ''),
        NULLIF(vel_t1, ''),
        NULLIF(vel_t2, ''),
        NULLIF(vel_t3, ''),
        NULLIF(vel_t4, ''),
        NULLIF(kg_teoricos, ''),
        NULLIF(kg_reales, ''),
        NULLIF(rechazo, ''),
        NULLIF(remoler, ''),
        NULLIF(eficiencia, ''),
        NULLIF(acciones, ''),
        fecha_registro_real
    FROM progel_cores.sup_captura_produccion
";
$filasProd = $pdoV2->exec($sqlProd);
echo "   -> $filasProd registros de reporte maestro migrados exitosamente.\n\n";

// --------------------------------------------------------------------
// 3. MIGRAR Y PIVOTAR BITÁCORAS HORARIAS (bitacora_lecturas)
// --------------------------------------------------------------------
echo "3. Migrando y pivotando bitácoras horarias de máquinas...\n";

// Mapeo exhaustivo de parametro_id -> [tabla_destino, columna_destino]
$mapParametros = [
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

// Nombres legibles de equipos
$nombresEquipos = [
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

// Extraer todas las lecturas de progel_cores
$query = "
    SELECT 
        b.id,
        b.equipo_id,
        b.parametro_id,
        b.valor_capturado,
        b.observaciones,
        b.numero_nomina,
        b.fecha_registro,
        b.lote,
        e.nombre AS equipo_nombre
    FROM bitacora_lecturas b
    LEFT JOIN equipos e ON b.equipo_id = e.id
    ORDER BY b.equipo_id, b.fecha_registro ASC
";

$stmtLecturas = $pdoCores->query($query);

// Agrupamos en memoria por [equipo_id, fecha_registro, numero_nomina]
$sesiones = [];
$totalLecturasLeidas = 0;
$lecturasIgnoradas = 0;

while ($row = $stmtLecturas->fetch()) {
    $totalLecturasLeidas++;
    $eqId = (int)$row['equipo_id'];
    $pId = (int)$row['parametro_id'];

    // Si el equipo no existe en el catálogo activo o el parámetro no está mapeado, omitir
    if (!isset($nombresEquipos[$eqId]) || !isset($mapParametros[$pId])) {
        $lecturasIgnoradas++;
        continue;
    }

    $tablaDestino = $mapParametros[$pId][0];
    $columna = $mapParametros[$pId][1];

    $timestamp = $row['fecha_registro'];
    $nomina = $row['numero_nomina'];
    $key = "{$tablaDestino}_{$eqId}_{$timestamp}_{$nomina}";

    if (!isset($sesiones[$key])) {
        $dt = new DateTime($timestamp);
        $sesiones[$key] = [
            'equipo_id' => $eqId,
            'equipo_nombre' => $row['equipo_nombre'] ?? $nombresEquipos[$eqId],
            'fecha' => $dt->format('Y-m-d'),
            'hora' => (int)$dt->format('G'),
            'operador_nomina' => $nomina,
            'observaciones' => $row['observaciones'],
            'fecha_registro' => $timestamp,
            'lote' => $row['lote'],
            'tabla' => $tablaDestino,
            'valores' => []
        ];
    }

    $columna = $mapParametros[$pId][1];
    $val = trim($row['valor_capturado']);
    
    // Si la columna es decimal, validamos valor numérico
    if (is_numeric($val)) {
        $sesiones[$key]['valores'][$columna] = (float)$val;
    } else {
        $sesiones[$key]['valores'][$columna] = $val !== '' ? $val : null;
    }

    if (!empty($row['observaciones']) && empty($sesiones[$key]['observaciones'])) {
        $sesiones[$key]['observaciones'] = $row['observaciones'];
    }
    if (!empty($row['lote']) && empty($sesiones[$key]['lote'])) {
        $sesiones[$key]['lote'] = $row['lote'];
    }
}

echo "   -> Total lecturas leídas: $totalLecturasLeidas\n";
echo "   -> Total sesiones agrupadas para insertar: " . count($sesiones) . "\n";
echo "   -> Lecturas históricas de pruebas omitidas: $lecturasIgnoradas\n\n";

// Insertar en progel_v2
$contadores = [
    'bitacora_chillers_votator' => 0,
    'bitacora_chillers_normales' => 0,
    'bitacora_compresores' => 0,
    'bitacora_concentradores' => 0,
    'bitacora_secadores' => 0
];

$pdoV2->beginTransaction();

foreach ($sesiones as $s) {
    $tabla = $s['tabla'];
    $cols = ['fecha', 'hora', 'operador_nomina', 'observaciones', 'fecha_registro'];
    $vals = [$s['fecha'], $s['hora'], $s['operador_nomina'], $s['observaciones'], $s['fecha_registro']];

    // Nombre de columna de equipo según la tabla
    if ($tabla === 'bitacora_chillers_votator' || $tabla === 'bitacora_chillers_normales') {
        $cols[] = 'equipo_nombre';
        $vals[] = $s['equipo_nombre'];
    } elseif ($tabla === 'bitacora_compresores') {
        $cols[] = 'compresor_nombre';
        $vals[] = $s['equipo_nombre'];
    } elseif ($tabla === 'bitacora_concentradores') {
        $cols[] = 'concentrador_nombre';
        $vals[] = $s['equipo_nombre'];
        $cols[] = 'lote';
        $vals[] = $s['lote'];
    } elseif ($tabla === 'bitacora_secadores') {
        $cols[] = 'secador_nombre';
        $vals[] = $s['equipo_nombre'];
        $cols[] = 'lote';
        $vals[] = $s['lote'];
    }

    foreach ($s['valores'] as $col => $val) {
        $cols[] = "`$col`";
        $vals[] = $val;
    }

    $colList = implode(', ', array_map(function($c) {
        return strpos($c, '`') !== false ? $c : "`$c`";
    }, $cols));
    $placeholders = implode(', ', array_fill(0, count($vals), '?'));

    $sql = "INSERT INTO `$tabla` ($colList) VALUES ($placeholders)";
    $stmt = $pdoV2->prepare($sql);
    $stmt->execute($vals);

    $contadores[$tabla]++;
}

$pdoV2->commit();

echo "4. Resumen de registros insertados en tablas horizontales de progel_v2:\n";
foreach ($contadores as $tbl => $count) {
    echo "   * $tbl: $count filas horarias completas\n";
}

echo "\n====================================================================\n";
echo "  ¡MIGRACIÓN Y TRANSFORMACIÓN A PROGEL_V2 COMPLETADA CON ÉXITO!      \n";
echo "====================================================================\n";
