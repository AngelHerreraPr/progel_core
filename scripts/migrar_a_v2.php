<?php
/**
 * ====================================================================
 * SCRIPT DE MIGRACIÓN Y TRANSFORMACIÓN ETL: progel_core -> PROGEL_V2
 * ====================================================================
 * Este script lee todos los datos históricos de 'progel_core' y los
 * pivota / estructura en la nueva base de datos limpia 'Progel_coreV2'.
 */

declare(ticks = 1);
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('memory_limit', '512M');
set_time_limit(0);

echo "====================================================================\n";
echo "  INICIANDO MIGRACIÓN AUTOMÁTICA: progel_core -> Progel_coreV2\n";
echo "====================================================================\n\n";

$host = '127.0.0.1';
$user = 'root';
$passwords = ['Pr0gel+2024', ''];
$pdoCores = null;
$pdoV2 = null;

foreach ($passwords as $pass) {
    try {
        $pdoCores = new PDO("mysql:host=$host;dbname=progel_core;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $pdoV2 = new PDO("mysql:host=$host;dbname=Progel_coreV2;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        break;
    } catch (PDOException $e) {
        continue;
    }
}

if (!$pdoCores || !$pdoV2) {
    die("ERROR al conectar a MySQL con los usuarios/contraseñas configuradas.\n");
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

// Asegurar columnas de producto Votator en bitacora_chillers_votator
$colsVotatorExtra = [
    'temp_entrada_producto_c',
    'temp_salida_producto_c',
    'delta_temp_producto_c',
    'flujo',
    'textura_visual',
    'lubricacion_votator'
];
$existingColsVot = $pdoV2->query("SHOW COLUMNS FROM `bitacora_chillers_votator`")->fetchAll(PDO::FETCH_COLUMN);
foreach ($colsVotatorExtra as $colExtra) {
    if (!in_array($colExtra, $existingColsVot)) {
        $pdoV2->exec("ALTER TABLE `bitacora_chillers_votator` ADD COLUMN `$colExtra` VARCHAR(50) NULL DEFAULT NULL");
    }
}

// Asegurar que las columnas de medición en Progel_coreV2 acepten tanto valores numéricos como estados operativos ('F.O.', 'LAVADO', 'MTTO') sin convertirlos en 0.00
$alterCols = [
    'bitacora_chillers_votator' => [
        'temp_inyeccion_maestro_c', 'temp_retorno_maestro_c', 'delta_temp_maestro_c',
        'temp_inyeccion_esclavo_c', 'temp_retorno_esclavo_c', 'delta_temp_esclavo_c',
        'presion_inyeccion_glicol_psi',
        'temp_entrada_producto_c', 'temp_salida_producto_c', 'delta_temp_producto_c',
        'flujo', 'textura_visual', 'lubricacion_votator'
    ],
    'bitacora_chillers_normales' => [
        'concentracion_glicol_porc', 'nivel_glicol_porc', 'frecuencia_bombas_hz', 'presion_bombas_glicol_psi',
        'presion_baja_ch1_psi', 'presion_alta_ch1_psi', 'presion_baja_ch2_psi', 'presion_alta_ch2_psi',
        'presion_baja_ch3_psi', 'presion_alta_ch3_psi', 'presion_baja_ch4_psi', 'presion_alta_ch4_psi',
        'presion_baja_ch5_psi', 'presion_alta_ch5_psi', 'presion_baja_ch6_psi', 'presion_alta_ch6_psi',
        'presion_baja_ch7_psi', 'presion_alta_ch7_psi',
        'temp_in_ch1_2_c', 'temp_out_ch1_2_c', 'delta_temp_ch1_2_c',
        'temp_in_ch3_4_c', 'temp_out_ch3_4_c', 'delta_temp_ch3_4_c',
        'temp_in_ch5_6_c', 'temp_out_ch5_6_c', 'delta_temp_ch5_6_c',
        'temp_in_ch7_c', 'temp_out_ch7_c', 'delta_temp_ch7_c'
    ],
    'bitacora_compresores' => [
        'humedad_ambiental_porc', 'temp_ambiental_c', 'horas_trabajadas'
    ],
    'bitacora_concentradores' => [
        'solidos_entrada_porc', 'flujo_entrada_lpm', 'presion_vapor_psi', 'vacio_mmhg',
        'temp_salida_grenetina_c', 'solidos_salida_porc', 'flujo_salida_lpm',
        'temp_caldo_entrada_c', 'temp_agua_in_condensador_c', 'temp_agua_out_condensador_c',
        'presion_vacio_kg_cm2'
    ],
    'bitacora_secadores' => [
        'flujo_l_min', 'velocidad_banda_m_h', 'frecuencia_hz', 'altura_galleta_cm',
        'humedad_churro_recamara_5_porc', 'humedad_churro_penultima_porc',
        'humedad_relativa_recamara_5_porc', 'humedad_producto_final_porc',
        'temp_recamara_1_c', 'temp_recamara_2_c', 'temp_recamara_3_c', 'temp_recamara_4_c'
    ],
    'produccion_reporte_maestro' => [
        'consumo_cuero_kg', 'flujo_votator_1_lh', 'flujo_votator_2_lh', 'flujo_votator_3_lh',
        'flujo_votator_4_lh', 'flujo_votator_5_lh', 'flujo_votator_6_lh', 'solidos_brix',
        'humedad_tunel_1_porc', 'humedad_tunel_2_porc', 'humedad_tunel_3_porc',
        'humedad_tunel_4_porc', 'humedad_tunel_5_porc',
        'velocidad_tunel_1_mh', 'velocidad_tunel_2_mh', 'velocidad_tunel_3_mh', 'velocidad_tunel_4_mh',
        'kg_teoricos', 'kg_reales', 'rechazo_kg', 'remoler_kg', 'eficiencia_porcentaje'
    ]
];

foreach ($alterCols as $tbl => $cols) {
    $modParts = [];
    foreach ($cols as $c) {
        $modParts[] = "MODIFY COLUMN `$c` VARCHAR(50) NULL DEFAULT NULL";
    }
    $pdoV2->exec("ALTER TABLE `$tbl` " . implode(', ', $modParts));
}

$pdoV2->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "[OK] Tablas de Progel_coreV2 preparadas y columnas optimizadas (VARCHAR para soportar F.O./LAVADO).\n\n";

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
    elseif (strpos((string)$eq['tipo'], 'membrana') !== false) $tipo = 'membrana';

    $codigo = 'EQ-' . str_pad($eq['id'], 3, '0', STR_PAD_LEFT);
    $stmtEquipo->execute([$eq['id'], $codigo, $eq['nombre'], $eq['area_id'], $tipo]);
}
echo "   -> " . count($equipos) . " equipos en catálogo migrados.\n";

// 1.5 Usuarios
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
    INSERT IGNORE INTO Progel_coreV2.produccion_reporte_maestro (
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
    FROM progel_core.sup_captura_produccion
";
$filasProd = $pdoV2->exec($sqlProd);
echo "   -> $filasProd registros de reporte maestro migrados exitosamente.\n\n";

// --------------------------------------------------------------------
// 3. MIGRAR Y PIVOTAR BITÁCORAS HORARIAS (bitacora_lecturas)
// --------------------------------------------------------------------
echo "3. Migrando y pivotando bitácoras horarias de máquinas...\n";

require_once __DIR__ . '/../includes/mapeo_v2.php';
$mapParametros = $GLOBALS['MAP_PARAMETROS_V2'];

// Equipos activos en la migración (excluyendo IDs duplicados de la primera semana 1, 2, 30, 50 que ya están en 91, 92, 12, 13)
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

// Detectar si 'progel_core' o 'progel_core' tiene más lecturas
$dbLecturas = 'progel_core';
try {
    $count1 = (int)$pdoCores->query("SELECT COUNT(*) FROM progel_core.bitacora_lecturas")->fetchColumn();
    $count2 = (int)$pdoCores->query("SELECT COUNT(*) FROM progel_core.bitacora_lecturas")->fetchColumn();
    if ($count1 > $count2) {
        $dbLecturas = 'progel_core';
        echo "   -> Seleccionada BD '$dbLecturas' por contener más registros ($count1 vs $count2).\n";
    } else {
        echo "   -> Seleccionada BD '$dbLecturas' ($count2 registros).\n";
    }
} catch (Exception $e) {
    echo "   -> Usando base de datos '$dbLecturas' por defecto.\n";
}

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
    FROM {$dbLecturas}.bitacora_lecturas b
    LEFT JOIN {$dbLecturas}.equipos e ON b.equipo_id = e.id
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
            'equipo_nombre' => $nombresEquipos[$eqId],
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

    $val = trim((string)$row['valor_capturado']);
    $sesiones[$key]['valores'][$columna] = ($val !== '') ? $val : null;

    if (!empty($row['observaciones']) && empty($sesiones[$key]['observaciones'])) {
        $sesiones[$key]['observaciones'] = $row['observaciones'];
    }
    if (!empty($row['lote']) && empty($sesiones[$key]['lote'])) {
        $sesiones[$key]['lote'] = $row['lote'];
    }
}

echo "   -> Total lecturas leídas: $totalLecturasLeidas\n";
echo "   -> Total sesiones agrupadas para insertar: " . count($sesiones) . "\n";
echo "   -> Lecturas duplicadas de primera semana omitidas: $lecturasIgnoradas\n\n";

// Insertar en Progel_coreV2
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

echo "4. Resumen de registros insertados en tablas horizontales de Progel_coreV2:\n";
foreach ($contadores as $tbl => $count) {
    echo "   * $tbl: $count filas horarias completas\n";
}

echo "\n====================================================================\n";
echo "  ¡MIGRACIÓN Y TRANSFORMACIÓN A PROGEL_COREV2 COMPLETADA CON ÉXITO!\n";
echo "====================================================================\n";
