<?php
// progel_core/reporte_general.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('America/Mazatlan');

include_once 'config/db.php';
include_once 'config/db_v2.php';
include_once 'includes/mapeo_v2.php';
include_once 'includes/rangos_historicos.php';

if (!isset($_SESSION['nomina'])) {
    header("Location: login.php");
    exit();
}

$historial_rangos = cargarHistorialRangos($conn);

// =========================================================================
// DEFINICIÓN DE FECHAS Y TURNOS OPERATIVOS
// Diurno:   07:00 AM a 07:00 PM (07:00:00 a 18:59:59)
// Nocturno: 07:00 PM de fecha a 07:00 AM del día siguiente (19:00:00 a 06:59:59)
// =========================================================================
$hora_actual_num = (int)date('G');
if ($hora_actual_num >= 7 && $hora_actual_num < 19) {
    $turno_actual_sugerido = 'diurno';
    $fecha_sugerida = date('Y-m-d');
} else {
    $turno_actual_sugerido = 'nocturno';
    $fecha_sugerida = ($hora_actual_num < 7) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
}

$fecha_filtro = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : $fecha_sugerida;
$turno_filtro = isset($_GET['turno']) ? strtolower(trim($_GET['turno'])) : $turno_actual_sugerido;
if (!in_array($turno_filtro, ['diurno', 'nocturno', 'ambos'])) {
    $turno_filtro = 'diurno';
}

$vista = isset($_GET['vista']) ? strtolower(trim($_GET['vista'])) : 'diaria';
if (!in_array($vista, ['diaria', 'semanal'])) {
    $vista = 'diaria';
}

$intervalo_filtro = isset($_GET['intervalo']) ? strtolower(trim($_GET['intervalo'])) : '1h';
if (!in_array($intervalo_filtro, ['1h', '2h', 'auto'])) {
    $intervalo_filtro = '1h';
}

// Filtro por Zona (Soporta id numérico o slug: 'blanca', 'gris', 'negra')
$rol_u = strtoupper(trim($_SESSION['rol'] ?? ''));
$zona_u = strtoupper(trim($_SESSION['zona'] ?? 'TODAS'));
$es_admin_jefe = in_array($rol_u, ['ADMIN', 'JEFATURA']);

$zona_filtro = 0;
if (isset($_GET['zona']) && $_GET['zona'] !== '') {
    $z_param = strtolower(trim($_GET['zona']));
    if (is_numeric($z_param)) {
        $zona_filtro = (int)$z_param;
    } elseif (strpos($z_param, 'blanca') !== false) {
        $zona_filtro = 1;
    } elseif (strpos($z_param, 'gris') !== false) {
        $zona_filtro = 2;
    } elseif (strpos($z_param, 'negra') !== false) {
        $zona_filtro = 3;
    }
} elseif (isset($_GET['zona_id']) && !empty($_GET['zona_id'])) {
    $zona_filtro = (int)$_GET['zona_id'];
}

// Si no se especificó zona en URL pero el usuario no es admin/jefe y tiene una zona fija asignada
if ($zona_filtro === 0 && !$es_admin_jefe && $zona_u !== 'TODAS') {
    if (strpos($zona_u, 'BLANCA') !== false) $zona_filtro = 1;
    elseif (strpos($zona_u, 'GRIS') !== false) $zona_filtro = 2;
    elseif (strpos($zona_u, 'NEGRA') !== false) $zona_filtro = 3;
}

$fecha_hoy = date('Y-m-d');
$fecha_anterior = date('Y-m-d', strtotime($fecha_filtro . ' -1 day'));
$fecha_siguiente = date('Y-m-d', strtotime($fecha_filtro . ' +1 day'));

// Para vista semanal
if ($vista === 'semanal') {
    $lunes = date('Y-m-d', strtotime('monday this week', strtotime($fecha_filtro)));
    $domingo = date('Y-m-d', strtotime('sunday this week', strtotime($fecha_filtro)));
    $dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    $dias_fechas = [];
    for ($i = 0; $i < 7; $i++) {
        $dias_fechas[] = date('Y-m-d', strtotime("$lunes + $i days"));
    }
}

// =========================================================================
// Función para mapear un timestamp exacto a su turno y columna de hora
// =========================================================================
if (!function_exists('mapearBloqueTurno')) {
    function mapearBloqueTurno($fecha_reg_str, $fecha_base, $intervalo = '2h') {
        if (empty($fecha_reg_str)) return null;
        $ts = strtotime($fecha_reg_str);
        if (!$ts) return null;

        $d = date('Y-m-d', $ts);
        $h = (int)date('G', $ts);
        $m = (int)date('i', $ts);
        $t_dec = $h + ($m / 60.0);
        $fecha_sig = date('Y-m-d', strtotime($fecha_base . ' +1 day'));

        if ($d === $fecha_base) {
            if ($t_dec >= 7.0 && $t_dec < 19.0) {
                // Turno Diurno (07:00 a 18:59)
                if ($intervalo === '1h' || $intervalo === 'auto') {
                    $bloque = sprintf('%02d:00', $h);
                } else {
                    if ($t_dec < 9.0) $bloque = '08:00';
                    elseif ($t_dec < 11.0) $bloque = '10:00';
                    elseif ($t_dec < 13.0) $bloque = '12:00';
                    elseif ($t_dec < 15.0) $bloque = '14:00';
                    elseif ($t_dec < 17.0) $bloque = '16:00';
                    else $bloque = '18:00';
                }
                return ['turno' => 'diurno', 'bloque' => $bloque, 'ts' => $ts];
            } elseif ($t_dec >= 19.0) {
                // Turno Nocturno (parte hoy 19:00 a 23:59)
                if ($intervalo === '1h' || $intervalo === 'auto') {
                    $bloque = sprintf('%02d:00', $h);
                } else {
                    if ($t_dec < 21.0) $bloque = '20:00';
                    elseif ($t_dec < 23.0) $bloque = '22:00';
                    else $bloque = '00:00';
                }
                return ['turno' => 'nocturno', 'bloque' => $bloque, 'ts' => $ts];
            }
        } elseif ($d === $fecha_sig) {
            if ($t_dec < 7.0) {
                // Turno Nocturno (parte mañana 00:00 a 06:59)
                if ($intervalo === '1h' || $intervalo === 'auto') {
                    $bloque = sprintf('%02d:00', $h);
                } else {
                    if ($t_dec < 1.0) $bloque = '00:00';
                    elseif ($t_dec < 3.0) $bloque = '02:00';
                    elseif ($t_dec < 5.0) $bloque = '04:00';
                    else $bloque = '06:00';
                }
                return ['turno' => 'nocturno', 'bloque' => $bloque, 'ts' => $ts];
            }
        }
        return null;
    }
}

// =========================================================================
// Función para desglosar Rangos de Parámetros en 3 Columnas: Verde, Amarillo, Rojo
// =========================================================================
if (!function_exists('obtenerRangosDesglosados')) {
    function obtenerRangosDesglosados($p) {
        $pid_check = isset($p['id']) ? (int)$p['id'] : 0;
        if ($pid_check > 0 && isset($GLOBALS['TEXTOS_SEMAFORO_CONCENTRADORES'][$pid_check])) {
            return $GLOBALS['TEXTOS_SEMAFORO_CONCENTRADORES'][$pid_check];
        }
        if (($p['tipo_dato'] ?? '') !== 'NUMERICO') {
            $nom = mb_strtolower($p['nombre_parametro'] ?? '', 'UTF-8');
            if (strpos($nom, 'textura') !== false) {
                return [
                    'verde' => 'Firme y uniforme',
                    'amarillo' => 'Deformación en fideo',
                    'rojo' => 'Fideo plastoso'
                ];
            }
            if (strpos($nom, 'lubric') !== false) {
                return [
                    'verde' => 'Óptima',
                    'amarillo' => 'Insuficiente',
                    'rojo' => 'Sin lubricación'
                ];
            }
            if (strpos($nom, 'limpieza') !== false || strpos($nom, 'estado') !== false) {
                return [
                    'verde' => 'En Operación / OK',
                    'amarillo' => 'MMTT / Lavado',
                    'rojo' => 'F.O. / Falla'
                ];
            }
            return [
                'verde' => 'Normal / Sí',
                'amarillo' => 'Alerta / Parcial',
                'rojo' => 'No cumple / F.O.'
            ];
        }

        $rb = ($p['rojo_bajo'] !== null && $p['rojo_bajo'] !== '') ? floatval($p['rojo_bajo']) : null;
        $ab = ($p['amarillo_bajo'] !== null && $p['amarillo_bajo'] !== '') ? floatval($p['amarillo_bajo']) : null;
        $aa = ($p['amarillo_alto'] !== null && $p['amarillo_alto'] !== '') ? floatval($p['amarillo_alto']) : null;
        $ra = ($p['rojo_alto'] !== null && $p['rojo_alto'] !== '') ? floatval($p['rojo_alto']) : null;

        if ($rb === null && $ab === null && $aa === null && $ra === null) {
            return ['verde' => '-', 'amarillo' => '-', 'rojo' => '-'];
        }

        $fmt = function($num) {
            if ($num === null) return '';
            $r = round($num);
            if (abs($num - $r) < 0.005) {
                return (string)$r;
            }
            $formatted = number_format($num, 2, '.', '');
            return rtrim(rtrim($formatted, '0'), '.');
        };

        // 1. VERDE (Óptimo)
        $verde = '-';
        if ($ab !== null && $aa !== null) {
            if ($ab == $aa) {
                $verde = $fmt($ab);
            } else {
                $verde = "{$fmt($ab)} - {$fmt($aa)}";
            }
        } elseif ($ab !== null && $aa === null) {
            $verde = "≥ {$fmt($ab)}";
        } elseif ($aa !== null && $ab === null) {
            $verde = "≤ {$fmt($aa)}";
        }

        // 2. AMARILLO (Alerta)
        $partesAm = [];
        if ($rb !== null && $ab !== null && $ab > $rb) {
            $partesAm[] = "{$fmt($rb)} - {$fmt($ab)}";
        } elseif ($ab !== null && $rb === null) {
            $partesAm[] = "< {$fmt($ab)}";
        }

        if ($aa !== null && $ra !== null && $ra > $aa) {
            $partesAm[] = "{$fmt($aa)} - {$fmt($ra)}";
        } elseif ($aa !== null && $ra === null) {
            $partesAm[] = "> {$fmt($aa)}";
        }
        $amarillo = !empty($partesAm) ? implode(' y ', $partesAm) : '-';

        // 3. ROJO (Crítico)
        $partesRojo = [];
        if ($rb !== null) {
            $partesRojo[] = "≤ {$fmt($rb)}";
        }
        if ($ra !== null) {
            $partesRojo[] = "≥ {$fmt($ra)}";
        }
        $rojo = !empty($partesRojo) ? implode(' o ', $partesRojo) : '-';

        return [
            'verde' => $verde,
            'amarillo' => $amarillo,
            'rojo' => $rojo
        ];
    }
}

// Función semáforo
if (!function_exists('getBadgeClass')) {
    function getBadgeClass($val, $p) {
        if ($val === null || $val === '' || $val === '-') return 'bg-gray';
        if (is_numeric($val)) {
            $v = (float)$val;
            $rb = isset($p['rojo_bajo']) && $p['rojo_bajo'] !== null && $p['rojo_bajo'] !== '' ? (float)$p['rojo_bajo'] : -999999;
            $ra = isset($p['rojo_alto']) && $p['rojo_alto'] !== null && $p['rojo_alto'] !== '' ? (float)$p['rojo_alto'] : 999999;
            $ab = isset($p['amarillo_bajo']) && $p['amarillo_bajo'] !== null && $p['amarillo_bajo'] !== '' ? (float)$p['amarillo_bajo'] : -999999;
            $aa = isset($p['amarillo_alto']) && $p['amarillo_alto'] !== null && $p['amarillo_alto'] !== '' ? (float)$p['amarillo_alto'] : 999999;
            if ($v <= $rb || $v >= $ra) return 'bg-red';
            if (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) return 'bg-yellow';
            return 'bg-green';
        }
        $val_low = strtolower(trim($val));
        if (in_array($val_low, ['realizada', 'sí', 'si', 'no presenta', 'firme', 'firme y uniforme', 'en operación', 'en operacion', 'óptima', 'optima'])) return 'bg-green';
        if (in_array($val_low, ['no aplica', 'n/a'])) return 'bg-gray';
        if (in_array($val_low, ['lavado', 'lavado cip'])) return 'bg-lavado';
        if (in_array($val_low, ['mmtt', 'mmt', 'mtto', 'deformación en el fideo', 'deformacion en el fideo', 'insuficiente'])) return 'bg-yellow';
        if (in_array($val_low, ['f.o.', 'fo', 'f/o', 'fuera de operación', 'fuera de operacion', 'no', 'sí presenta', 'si presenta', 'húmeda', 'sobreseca', 'quemada', 'fideo plastoso', 'sin lubricación', 'sin lubricacion'])) return 'bg-red';
        return 'bg-yellow';
    }
}

// =========================================================================
// CONFIGURACIÓN DE BLOQUES HORARIOS DEL TURNO
// =========================================================================
$generarBloquesTurno = function($t_key) use ($intervalo_filtro, $fecha_filtro, $fecha_siguiente) {
    $bloques = [];
    if ($intervalo_filtro === '1h') {
        if ($t_key === 'diurno') {
            for ($h = 7; $h <= 18; $h++) {
                $str = sprintf('%02d:00', $h);
                $bloques[$str] = ['label' => $str, 'sub' => date('d/m', strtotime($fecha_filtro))];
            }
        } else {
            for ($h = 19; $h <= 23; $h++) {
                $str = sprintf('%02d:00', $h);
                $bloques[$str] = ['label' => $str, 'sub' => date('d/m', strtotime($fecha_filtro))];
            }
            for ($h = 0; $h <= 6; $h++) {
                $str = sprintf('%02d:00', $h);
                $bloques[$str] = ['label' => $str, 'sub' => date('d/m', strtotime($fecha_siguiente)) . ' (+1)'];
            }
        }
    } else {
        // Bloques de 2 horas (diseño limpio y amplio)
        if ($t_key === 'diurno') {
            $pares = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'];
            foreach ($pares as $p) {
                $bloques[$p] = ['label' => $p, 'sub' => date('d/m', strtotime($fecha_filtro))];
            }
        } else {
            $pares = [
                '20:00' => date('d/m', strtotime($fecha_filtro)),
                '22:00' => date('d/m', strtotime($fecha_filtro)),
                '00:00' => date('d/m', strtotime($fecha_siguiente)) . ' (+1)',
                '02:00' => date('d/m', strtotime($fecha_siguiente)) . ' (+1)',
                '04:00' => date('d/m', strtotime($fecha_siguiente)) . ' (+1)',
                '06:00' => date('d/m', strtotime($fecha_siguiente)) . ' (+1)'
            ];
            foreach ($pares as $p => $sub) {
                $bloques[$p] = ['label' => $p, 'sub' => $sub];
            }
        }
    }
    return $bloques;
};

$turnos_config = [
    'diurno' => [
        'titulo' => 'Turno Diurno (07:00 AM - 07:00 PM)',
        'icono' => 'bi-sun-fill text-warning',
        'badge' => 'bg-warning text-dark',
        'bloques' => $generarBloquesTurno('diurno')
    ],
    'nocturno' => [
        'titulo' => 'Turno Nocturno (07:00 PM - 07:00 AM)',
        'icono' => 'bi-moon-stars-fill text-primary',
        'badge' => 'bg-primary text-white',
        'bloques' => $generarBloquesTurno('nocturno')
    ]
];

$turnos_a_mostrar = ($turno_filtro === 'diurno') ? ['diurno'] : (($turno_filtro === 'nocturno') ? ['nocturno'] : ['diurno', 'nocturno']);

// Metadatos de parámetros para evaluar límites de KPI
$res_p_meta = mysqli_query($conn, "SELECT id, equipo_id, nombre_parametro, tipo_dato, rojo_bajo, amarillo_bajo, amarillo_alto, rojo_alto FROM parametros");
$meta_params = [];
$map_inverso_v2 = [];
$param_membrana_delta = null;
if ($res_p_meta) {
    while ($p = mysqli_fetch_assoc($res_p_meta)) {
        $pid = (int)$p['id'];
        $eid = (int)$p['equipo_id'];
        $meta_params[$pid] = $p;
        if ($eid === 100 && stripos($p['nombre_parametro'] ?? '', 'Delta') !== false) {
            $param_membrana_delta = $p;
        }
        if (isset($GLOBALS['MAP_PARAMETROS_V2'][$pid])) {
            $tabla = $GLOBALS['MAP_PARAMETROS_V2'][$pid][0];
            $col   = $GLOBALS['MAP_PARAMETROS_V2'][$pid][1];
            $eqNom = $GLOBALS['NOMBRES_EQUIPOS_V2'][$eid] ?? "Equipo $eid";
            $map_inverso_v2[$tabla][$eqNom][$col] = $pid;
        }
    }
}

// Zonas y conteo de equipos 
$q_zonas = mysqli_query($conn, "SELECT * FROM zonas ORDER BY id ASC");
$lista_zonas = [];
if ($q_zonas) while ($z = mysqli_fetch_assoc($q_zonas)) $lista_zonas[] = $z;

$conteo_equipos = [];
$q_count = mysqli_query($conn, "SELECT a.zona_id, COUNT(e.id) as total FROM equipos e JOIN areas a ON e.area_id = a.id WHERE e.nombre NOT LIKE 'Producto Votator' GROUP BY a.zona_id");
if ($q_count) while ($row = mysqli_fetch_assoc($q_count)) $conteo_equipos[$row['zona_id']] = $row['total'];

// =========================================================================
// CARGA Y CACHÉ DE LECTURAS (POR TURNO Y BLOQUE)
// =========================================================================
$total_lecturas = $total_verdes = $total_amarillos = $total_rojos = 0;
$cache_lecturas = []; // [turno][parametro_id][bloque] = [val, obs, lote, nomina, fecha_reg]
$cache_meta_equipo = []; // [turno][equipo_id][bloque] = ['fecha_exacta' => ..., 'operador' => ...]

// Rango de fechas según el turno
$whereFechaDiurno = "fecha_registro >= '$fecha_filtro 07:00:00' AND fecha_registro <= '$fecha_filtro 18:59:59'";
$whereFechaNocturno = "fecha_registro >= '$fecha_filtro 19:00:00' AND fecha_registro <= '$fecha_siguiente 06:59:59'";
$whereFechaCompleto = "fecha_registro >= '$fecha_filtro 07:00:00' AND fecha_registro <= '$fecha_siguiente 06:59:59'";

if ($vista === 'diaria') {
    $whereFechaActiva = ($turno_filtro === 'diurno') ? $whereFechaDiurno : (($turno_filtro === 'nocturno') ? $whereFechaNocturno : $whereFechaCompleto);

    // A) bitacora_lecturas tradicional
    $sql_cache = "SELECT b.parametro_id, b.valor_capturado, b.observaciones, b.lote, b.numero_nomina, b.fecha_registro, p.equipo_id 
                  FROM bitacora_lecturas b 
                  JOIN parametros p ON b.parametro_id = p.id
                  WHERE $whereFechaActiva 
                  ORDER BY b.fecha_registro ASC";
    $res_cache = mysqli_query($conn, $sql_cache);
    if ($res_cache) {
        while ($row = mysqli_fetch_assoc($res_cache)) {
            $map = mapearBloqueTurno($row['fecha_registro'], $fecha_filtro, $intervalo_filtro);
            if (!$map) continue;
            $t = $map['turno'];
            $b = $map['bloque'];
            $pid = (int)$row['parametro_id'];
            $eid = (int)$row['equipo_id'];
            $val = $row['valor_capturado'];

            $cache_lecturas[$t][$pid][$b] = [
                'val' => $val,
                'obs' => $row['observaciones'] ?? '',
                'lote' => $row['lote'] ?? '',
                'nomina' => $row['numero_nomina'] ?? '',
                'fecha_reg' => $row['fecha_registro']
            ];

            if (empty($cache_meta_equipo[$t][$eid][$b]['fecha_exacta'])) {
                $cache_meta_equipo[$t][$eid][$b]['fecha_exacta'] = $row['fecha_registro'];
            }
            if (empty($cache_meta_equipo[$t][$eid][$b]['operador']) && !empty($row['numero_nomina'])) {
                $cache_meta_equipo[$t][$eid][$b]['operador'] = $row['numero_nomina'];
            }

            // KPIs
            if ($val !== null && is_numeric($val) && isset($meta_params[$pid])) {
                $total_lecturas++;
                $p_eval = obtenerParametroEnFecha($meta_params[$pid], $row['fecha_registro'], $historial_rangos);
                $rb = $p_eval['rojo_bajo'] ?? -999999;
                $ra = $p_eval['rojo_alto'] ?? 999999;
                $ab = $p_eval['amarillo_bajo'] ?? -999999;
                $aa = $p_eval['amarillo_alto'] ?? 999999;
                $v = (float)$val;
                if ($v <= $rb || $v >= $ra) $total_rojos++;
                elseif (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) $total_amarillos++;
                else $total_verdes++;
            }
        }
    }

    // B) Tablas progel_coreV2 (Concentradores, Secadores, Chillers, Compresores)
    if (isset($conn_v2) && $conn_v2 && !empty($map_inverso_v2)) {
        foreach ($map_inverso_v2 as $tabla => $equipos) {
            $col_eq = 'equipo_nombre';
            if ($tabla === 'bitacora_secadores') $col_eq = 'secador_nombre';
            elseif ($tabla === 'bitacora_concentradores') $col_eq = 'concentrador_nombre';
            elseif ($tabla === 'bitacora_compresores') $col_eq = 'compresor_nombre';

            $sql_v2 = "SELECT * FROM `$tabla` WHERE $whereFechaActiva ORDER BY fecha_registro ASC";
            $res_v2 = mysqli_query($conn_v2, $sql_v2);
            if ($res_v2) {
                while ($row = mysqli_fetch_assoc($res_v2)) {
                    $eqActual = $row[$col_eq] ?? '';
                    if (isset($equipos[$eqActual])) {
                        $colsMap = $equipos[$eqActual];
                        $f_reg_v2 = $row['fecha_registro'] ?? ($row['fecha'] . ' ' . sprintf('%02d:00:00', $row['hora'] ?? 0));
                        $map = mapearBloqueTurno($f_reg_v2, $fecha_filtro, $intervalo_filtro);
                        if (!$map) continue;
                        $t = $map['turno'];
                        $b = $map['bloque'];

                        foreach ($colsMap as $col => $pid) {
                            if (isset($row[$col]) && $row[$col] !== null && $row[$col] !== '') {
                                $val = $row[$col];
                                $eid = (int)($meta_params[$pid]['equipo_id'] ?? 0);

                                $cache_lecturas[$t][$pid][$b] = [
                                    'val' => $val,
                                    'obs' => $row['observaciones'] ?? '',
                                    'lote' => $row['lote'] ?? '',
                                    'nomina' => $row['operador_nomina'] ?? '',
                                    'fecha_reg' => $f_reg_v2
                                ];

                                if (empty($cache_meta_equipo[$t][$eid][$b]['fecha_exacta'])) {
                                    $cache_meta_equipo[$t][$eid][$b]['fecha_exacta'] = $f_reg_v2;
                                }
                                if (empty($cache_meta_equipo[$t][$eid][$b]['operador']) && !empty($row['operador_nomina'])) {
                                    $cache_meta_equipo[$t][$eid][$b]['operador'] = $row['operador_nomina'];
                                }

                                if (is_numeric($val) && isset($meta_params[$pid])) {
                                    $total_lecturas++;
                                    $p_eval = obtenerParametroEnFecha($meta_params[$pid], $f_reg_v2, $historial_rangos);
                                    $rb = $p_eval['rojo_bajo'] ?? -999999;
                                    $ra = $p_eval['rojo_alto'] ?? 999999;
                                    $ab = $p_eval['amarillo_bajo'] ?? -999999;
                                    $aa = $p_eval['amarillo_alto'] ?? 999999;
                                    $v = (float)$val;
                                    if ($v <= $rb || $v >= $ra) $total_rojos++;
                                    elseif (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) $total_amarillos++;
                                    else $total_verdes++;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    // B.2) Integración directa con Sis_Extracción para Concentradores (14..18):
    //      1) progel_procesos.datos_concentradores + datos_hora
    //      2) bd_extraccion.datos_concentradores
    if (!empty($GLOBALS['MAP_CONCENTRADORES_SIS_EXTRACCION'])) {
        // 1) progel_procesos.datos_concentradores
        if (isset($conn_procesos) && $conn_procesos) {
            $sql_proc_all = "SELECT d.*, dh.hora, dh.hora_exacta, dh.fecha_creacion, dh.nomina_captura
                             FROM datos_concentradores d
                             INNER JOIN datos_hora dh ON dh.id = d.id_datos_hora
                             WHERE ((dh.hora_exacta >= '$fecha_filtro 07:00:00' AND dh.hora_exacta <= '$fecha_siguiente 06:59:59')
                                 OR (dh.fecha_creacion >= '$fecha_filtro 07:00:00' AND dh.fecha_creacion <= '$fecha_siguiente 06:59:59'))
                             ORDER BY d.id ASC";
            $res_proc_all = @mysqli_query($conn_procesos, $sql_proc_all);
            if ($res_proc_all) {
                $map_tipo_to_eq = ['Conc1' => 14, 'Conc2' => 15, 'Conc3' => 16, 'Conc4' => 17, 'Invertido' => 18];
                while ($rp = mysqli_fetch_assoc($res_proc_all)) {
                    $eid_c = $map_tipo_to_eq[$rp['tipo']] ?? 0;
                    if (!$eid_c || !isset($GLOBALS['MAP_CONCENTRADORES_SIS_EXTRACCION'][$eid_c])) continue;
                    $f_ref = !empty($rp['hora_exacta']) ? $rp['hora_exacta'] : $rp['fecha_creacion'];
                    $map = mapearBloqueTurno($f_ref, $fecha_filtro, $intervalo_filtro);
                    if (!$map) continue;
                    $t = $map['turno'];
                    if ($turno_filtro !== 'ambos' && $t !== $turno_filtro) continue;
                    $b = $map['bloque'];
                    $es_fo = ((int)($rp['estado_fo'] ?? 0) === 1);
                    $nom_op = $rp['nomina_captura'] ?? '';
                    $obs_str = $es_fo ? 'Fuera de Operación (F.O.)' : "Bitácora #{$rp['id']}";

                    foreach ($GLOBALS['MAP_CONCENTRADORES_SIS_EXTRACCION'][$eid_c]['cols'] as $pid => $par_cols) {
                        $col_proc = $par_cols[1];
                        $val_c = null;
                        if ($es_fo) {
                            $val_c = 'F.O.';
                        } elseif ($col_proc && isset($rp[$col_proc]) && $rp[$col_proc] !== null && $rp[$col_proc] !== '') {
                            $val_c = $rp[$col_proc];
                        }
                        if ($val_c !== null) {
                            $cache_lecturas[$t][$pid][$b] = [
                                'val' => $val_c,
                                'obs' => $obs_str,
                                'lote' => '',
                                'nomina' => $nom_op,
                                'fecha_reg' => $f_ref
                            ];
                            if (is_numeric($val_c) && isset($meta_params[$pid])) {
                                $total_lecturas++;
                                $p_eval = obtenerParametroEnFecha($meta_params[$pid], $f_ref, $historial_rangos);
                                $rb = $p_eval['rojo_bajo'] ?? -999999;
                                $ra = $p_eval['rojo_alto'] ?? 999999;
                                $ab = $p_eval['amarillo_bajo'] ?? -999999;
                                $aa = $p_eval['amarillo_alto'] ?? 999999;
                                $v = (float)$val_c;
                                if ($v <= $rb || $v >= $ra) $total_rojos++;
                                elseif (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) $total_amarillos++;
                                else $total_verdes++;
                            }
                        }
                    }
                    $cache_meta_equipo[$t][$eid_c][$b]['fecha_exacta'] = $f_ref;
                    if (!empty($nom_op)) {
                        $cache_meta_equipo[$t][$eid_c][$b]['operador'] = $nom_op;
                    }
                }
            }
        }

        // 2) bd_extraccion.datos_concentradores
        if (isset($conn_extraccion) && $conn_extraccion) {
            $sql_ext_all = "SELECT * FROM datos_concentradores WHERE $whereFechaActiva ORDER BY fecha_registro ASC";
            $res_ext_all = @mysqli_query($conn_extraccion, $sql_ext_all);
            if ($res_ext_all) {
                $map_extid_to_eq = [9 => 14, 10 => 15, 11 => 16, 12 => 17, 13 => 18];
                while ($re = mysqli_fetch_assoc($res_ext_all)) {
                    $eid_c = $map_extid_to_eq[(int)($re['concentrador_id'] ?? 0)] ?? 0;
                    if (!$eid_c || !isset($GLOBALS['MAP_CONCENTRADORES_SIS_EXTRACCION'][$eid_c])) continue;
                    $f_reg = $re['fecha_registro'];
                    $map = mapearBloqueTurno($f_reg, $fecha_filtro, $intervalo_filtro);
                    if (!$map) continue;
                    $t = $map['turno'];
                    $b = $map['bloque'];
                    $est_id = isset($re['estado_id']) && $re['estado_id'] !== null && $re['estado_id'] !== '' ? (int)$re['estado_id'] : 0;
                    $estado_txt = null;
                    if ($est_id === 1) $estado_txt = 'LAVADO';
                    elseif ($est_id === 2) $estado_txt = 'MMTT';
                    elseif ($est_id === 3) $estado_txt = 'F.O.';

                    $obs_ext = $re['observaciones'] ?? ($re['motivo_mmtt'] ?? '');
                    $nom_ext = $re['nomina'] ?? ($cache_meta_equipo[$t][$eid_c][$b]['operador'] ?? '');

                    foreach ($GLOBALS['MAP_CONCENTRADORES_SIS_EXTRACCION'][$eid_c]['cols'] as $pid => $par_cols) {
                        $col_ext = $par_cols[0];
                        $val_c = null;
                        if ($estado_txt !== null) {
                            $val_c = $estado_txt;
                        } elseif ($col_ext && isset($re[$col_ext]) && $re[$col_ext] !== null && $re[$col_ext] !== '') {
                            $val_c = $re[$col_ext];
                        }
                        if ($val_c !== null) {
                            $cache_lecturas[$t][$pid][$b] = [
                                'val' => $val_c,
                                'obs' => $obs_ext,
                                'lote' => '',
                                'nomina' => $nom_ext,
                                'fecha_reg' => $f_reg
                            ];
                            if (is_numeric($val_c) && isset($meta_params[$pid])) {
                                $total_lecturas++;
                                $p_eval = obtenerParametroEnFecha($meta_params[$pid], $f_reg, $historial_rangos);
                                $rb = $p_eval['rojo_bajo'] ?? -999999;
                                $ra = $p_eval['rojo_alto'] ?? 999999;
                                $ab = $p_eval['amarillo_bajo'] ?? -999999;
                                $aa = $p_eval['amarillo_alto'] ?? 999999;
                                $v = (float)$val_c;
                                if ($v <= $rb || $v >= $ra) $total_rojos++;
                                elseif (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) $total_amarillos++;
                                else $total_verdes++;
                            }
                        }
                    }
                    $cache_meta_equipo[$t][$eid_c][$b]['fecha_exacta'] = $f_reg;
                    if (!empty($nom_ext)) {
                        $cache_meta_equipo[$t][$eid_c][$b]['operador'] = $nom_ext;
                    }
                }
            }
        }
    }

    // C) Membranas de Ultrafiltración
    $c_memb_q = (isset($conn_v2) && $conn_v2) ? $conn_v2 : $conn;
    $membranas_turnos = [
        'diurno' => ['paquetes' => [], 'horas_exactas' => [], 'brix_in' => [], 'brix_out' => [], 'brix_perm' => [], 'meta' => []],
        'nocturno' => ['paquetes' => [], 'horas_exactas' => [], 'brix_in' => [], 'brix_out' => [], 'brix_perm' => [], 'meta' => []]
    ];
    $total_memb_en_turno = 0;

    if ($c_memb_q) {
        $q_memb = mysqli_query($c_memb_q, "SELECT * FROM bitacora_membranas 
                                            WHERE $whereFechaActiva 
                                            ORDER BY fecha_registro ASC, paquete_numero ASC");
        if ($q_memb) {
            while ($rm = mysqli_fetch_assoc($q_memb)) {
                $fReg = !empty($rm['fecha_registro']) ? $rm['fecha_registro'] : ($rm['fecha'] . ' ' . $rm['hora']);
                $map = mapearBloqueTurno($fReg, $fecha_filtro, $intervalo_filtro);
                if (!$map) continue;
                $t = $map['turno'];
                $b = $map['bloque'];
                $pNum = (int)$rm['paquete_numero'];

                $membranas_turnos[$t]['paquetes'][$pNum][$b] = $rm;
                if (empty($membranas_turnos[$t]['horas_exactas'][$b])) {
                    $membranas_turnos[$t]['horas_exactas'][$b] = date('H:i:s', strtotime($fReg));
                }
                if ($rm['brix_entrada'] !== null && $rm['brix_entrada'] !== '') {
                    $membranas_turnos[$t]['brix_in'][$b] = $rm['brix_entrada'];
                }
                if ($rm['brix_salida'] !== null && $rm['brix_salida'] !== '') {
                    $membranas_turnos[$t]['brix_out'][$b] = $rm['brix_salida'];
                }
                if ($rm['brix_permeado'] !== null && $rm['brix_permeado'] !== '') {
                    $membranas_turnos[$t]['brix_perm'][$b] = $rm['brix_permeado'];
                }
                if (empty($membranas_turnos[$t]['meta'][$b]['operador']) && !empty($rm['operador_nomina'])) {
                    $membranas_turnos[$t]['meta'][$b]['operador'] = $rm['operador_nomina'];
                }
                if (empty($membranas_turnos[$t]['meta'][$b]['obs']) && !empty($rm['observaciones'])) {
                    $membranas_turnos[$t]['meta'][$b]['obs'] = $rm['observaciones'];
                }

                $total_memb_en_turno++;
                $dp = $rm['delta_presion'];
                if ($dp !== null && is_numeric($dp)) {
                    $total_lecturas++;
                    $v = (float)$dp;
                    if ($v >= 1.8) $total_rojos++;
                    elseif ($v >= 1.2) $total_amarillos++;
                    else $total_verdes++;
                }
            }
        }
    }
} else {
    // Modo Semanal (Promedios lunes a domingo)
    $q_sem = "SELECT b.parametro_id, DATE(b.fecha_registro) as dia, b.fecha_registro, b.valor_capturado, b.observaciones, b.lote 
              FROM bitacora_lecturas b 
              WHERE DATE(b.fecha_registro) BETWEEN '$lunes' AND '$domingo' 
              ORDER BY b.fecha_registro ASC";
    $res_sem = mysqli_query($conn, $q_sem);
    if ($res_sem) {
        $sum_vals = [];
        $count_vals = [];
        while ($row = mysqli_fetch_assoc($res_sem)) {
            $pid = (int)$row['parametro_id'];
            $dia = $row['dia'];
            $val = $row['valor_capturado'];
            if (is_numeric($val)) {
                $sum_vals[$pid][$dia] = ($sum_vals[$pid][$dia] ?? 0) + (float)$val;
                $count_vals[$pid][$dia] = ($count_vals[$pid][$dia] ?? 0) + 1;
                $cache_lecturas['semanal'][$pid][$dia] = round($sum_vals[$pid][$dia] / $count_vals[$pid][$dia], 2);
            } else {
                $cache_lecturas['semanal'][$pid][$dia] = $val;
            }
            $cache_lecturas['semanal'][$pid][$dia . '_obs'] = $row['observaciones'];
            $cache_lecturas['semanal'][$pid][$dia . '_lote'] = $row['lote'];
            $cache_lecturas['semanal'][$pid][$dia . '_fecha_reg'] = $row['fecha_registro'];
        }
    }
}

// Estructura de Paquetes para Membranas
$etapas_membranas_def = [
    1 => ['nombre' => 'ETAPA 1 (Paquetes 1, 2, 3, 4, 9)', 'paquetes' => [1, 2, 3, 4, 9]],
    2 => ['nombre' => 'ETAPA 2 (Paquetes 5, 6, 7, 8)', 'paquetes' => [5, 6, 7, 8]],
    3 => ['nombre' => 'ETAPA 3 (Paquetes 10, 11)', 'paquetes' => [10, 11]],
    4 => ['nombre' => 'ETAPA 4 (Paquetes 12, 13)', 'paquetes' => [12, 13]]
];

include 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/reporte_general.css?v=<?= filemtime(__DIR__ . '/assets/css/reporte_general.css') ?>">
<style>
main.container { 
    max-width: 99vw !important; 
    width: 99vw !important; 
    padding-left: 0.75rem !important; 
    padding-right: 0.75rem !important; 
    margin: 0.5rem auto !important; 
}
.container-fluid {
    padding-left: 0 !important;
    padding-right: 0 !important;
    width: 100% !important;
}
.equipos-grid {
    display: grid !important;
    grid-template-columns: 1fr !important;
    gap: 1.5rem !important;
}
.card-equipo {
    border-radius: 12px !important;
    border: 1px solid #cbd5e1 !important;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04) !important;
}
.table-matriz thead th {
    background-color: #0f172a !important;
    color: #ffffff !important;
    font-size: 0.76rem !important;
    letter-spacing: 0.02em;
}
.table-matriz tbody td {
    padding: 0.35rem 0.4rem !important;
    font-size: 0.82rem !important;
}
.badge-valor {
    padding: 0.28rem 0.5rem !important;
    font-size: 0.78rem !important;
    font-weight: 700 !important;
    border-radius: 6px !important;
    cursor: pointer;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}
.btn-filtro-turno {
    font-weight: 700;
    font-size: 0.8rem;
    padding: 0.35rem 0.85rem;
    border-radius: 8px;
}
.rango-ant-badge {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    margin-top: 2px;
    padding: 1px 5px;
    border-radius: 4px;
    font-size: 0.62rem;
    font-weight: 700;
    background-color: #f3e8ff;
    color: #6b21a8;
    border: 1px solid #d8b4fe;
    cursor: help;
}
</style>

<div class="container-fluid py-3">

    <!-- ENCABEZADO DEL REPORTE -->
    <div class="header-main mb-3">
        <div>
            <h1>
                <i class="bi bi-grid-3x3-gap-fill text-primary"></i> Reporte de Operación por Turnos
                <?php if ($zona_filtro > 0): 
                    $nom_zona_filtrada = '';
                    foreach ($lista_zonas as $lz) { if ((int)$lz['id'] === $zona_filtro) { $nom_zona_filtrada = $lz['nombre']; break; } }
                ?>
                    <span class="badge bg-danger text-white rounded-pill px-3 py-1 ms-2 fs-6 align-middle shadow-sm">
                        <i class="bi bi-geo-alt-fill me-1"></i> Zona: <?= htmlspecialchars($nom_zona_filtrada ?: "Zona $zona_filtro") ?>
                    </span>
                <?php endif; ?>
            </h1>
            <span class="text-muted small fw-semibold">
                Monitoreo general de parámetros en planta · Fecha: <strong><?= date('d/m/Y', strtotime($fecha_filtro)) ?></strong>
            </span>
        </div>
        <div class="header-controls d-flex align-items-center gap-2 flex-wrap">
            <?php if ($zona_filtro > 0): ?>
                <a href="zona.php?id=<?= $zona_filtro ?>" class="btn btn-outline-secondary btn-sm fw-bold px-3">
                    <i class="bi bi-arrow-left me-1"></i> Volver a <?= htmlspecialchars($nom_zona_filtrada ?: "Zona $zona_filtro") ?>
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-dark btn-sm fw-bold px-3" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Imprimir Reporte
            </button>
            <?php if (strtoupper(trim($_SESSION['rol'] ?? '')) === 'ADMIN'): ?>
                <a href="config_parametros.php" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm">
                    <i class="bi bi-sliders me-1"></i> Configurar Rangos
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- TARJETAS DE INDICADORES (KPIS) -->
    <div class="kpi-grid mb-3">
        <div class="kpi-card" style="border-left-color: var(--primary-blue);">
            <div class="kpi-info">
                <span class="kpi-label">Lecturas en Turno</span>
                <span class="kpi-number"><?= $total_lecturas ?></span>
                <div class="progress-bar-custom"><div class="bar" style="width:100%; background:var(--primary-blue);"></div></div>
            </div>
            <i class="bi bi-card-checklist kpi-icon"></i>
        </div>
        <div class="kpi-card" style="border-left-color: var(--accent-green);">
            <div class="kpi-info">
                <span class="kpi-label">🟢 Óptimas (Verde)</span>
                <span class="kpi-number" style="color:var(--accent-green);"><?= $total_verdes ?></span>
                <div class="progress-bar-custom"><div class="bar" style="width:<?= ($total_lecturas?($total_verdes/$total_lecturas*100):0) ?>%; background:var(--accent-green);"></div></div>
            </div>
            <i class="bi bi-check-circle-fill kpi-icon" style="color:var(--accent-green);"></i>
        </div>
        <div class="kpi-card" style="border-left-color: var(--accent-yellow);">
            <div class="kpi-info">
                <span class="kpi-label">🟡 Alertas (Amarillo)</span>
                <span class="kpi-number" style="color:var(--accent-yellow);"><?= $total_amarillos ?></span>
                <div class="progress-bar-custom"><div class="bar" style="width:<?= ($total_lecturas?($total_amarillos/$total_lecturas*100):0) ?>%; background:var(--accent-yellow);"></div></div>
            </div>
            <i class="bi bi-exclamation-triangle-fill kpi-icon" style="color:var(--accent-yellow);"></i>
        </div>
        <div class="kpi-card" style="border-left-color: var(--accent-red);">
            <div class="kpi-info">
                <span class="kpi-label">🔴 Críticas (Rojo)</span>
                <span class="kpi-number" style="color:var(--accent-red);"><?= $total_rojos ?></span>
                <div class="progress-bar-custom"><div class="bar" style="width:<?= ($total_lecturas?($total_rojos/$total_lecturas*100):0) ?>%; background:var(--accent-red);"></div></div>
            </div>
            <i class="bi bi-x-circle-fill kpi-icon" style="color:var(--accent-red);"></i>
        </div>
    </div>

    <!-- PANEL DE CONTROL: FECHA, TURNO, INTERVALO Y BUSCADOR -->
    <div class="filters-panel mb-4 p-3 bg-white border rounded-3 shadow-sm">
        <form method="GET" action="reporte_general.php" id="formFiltroGeneral" class="row g-3 align-items-center mb-3">
            <input type="hidden" name="vista" id="inputVista" value="<?= htmlspecialchars($vista) ?>">
            <input type="hidden" name="turno" id="inputTurno" value="<?= htmlspecialchars($turno_filtro) ?>">
            <input type="hidden" name="intervalo" id="inputIntervalo" value="<?= htmlspecialchars($intervalo_filtro) ?>">
            <input type="hidden" name="zona" id="inputZona" value="<?= $zona_filtro ?>">

            <!-- Selector de Fecha con botones Anterior / Hoy / Siguiente -->
            <div class="col-xl-4 col-lg-5 col-md-6 col-12">
                <label class="form-label small fw-bold text-secondary mb-1">
                    <i class="bi bi-calendar-event me-1"></i> Día Operativo:
                </label>
                <div class="input-group input-group-sm">
                    <?php $query_zona_param = ($zona_filtro > 0) ? "&zona=$zona_filtro" : ""; ?>
                    <a href="reporte_general.php?fecha=<?= $fecha_anterior ?>&turno=<?= $turno_filtro ?>&intervalo=<?= $intervalo_filtro ?>&vista=<?= $vista ?><?= $query_zona_param ?>" 
                       class="btn btn-outline-secondary fw-bold" title="Día Anterior">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <input type="date" name="fecha" value="<?= htmlspecialchars($fecha_filtro) ?>" 
                           class="form-control form-control-sm fw-bold text-center" 
                           onchange="document.getElementById('formFiltroGeneral').submit();">
                    <a href="reporte_general.php?fecha=<?= $fecha_siguiente ?>&turno=<?= $turno_filtro ?>&intervalo=<?= $intervalo_filtro ?>&vista=<?= $vista ?><?= $query_zona_param ?>" 
                       class="btn btn-outline-secondary fw-bold" title="Día Siguiente">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <a href="reporte_general.php?fecha=<?= $fecha_hoy ?>&turno=<?= $turno_filtro ?>&intervalo=<?= $intervalo_filtro ?>&vista=<?= $vista ?><?= $query_zona_param ?>" 
                       class="btn <?= $fecha_filtro == $fecha_hoy ? 'btn-primary' : 'btn-outline-primary' ?> fw-bold">
                        Hoy
                    </a>
                </div>
            </div>

            <!-- Selector de Turno Operativo -->
            <div class="col-xl-4 col-lg-4 col-md-6 col-12">
                <label class="form-label small fw-bold text-secondary mb-1">
                    <i class="bi bi-shuffle me-1"></i> Turno Operativo de Planta:
                </label>
                <div class="btn-group btn-group-sm w-100" role="group">
                    <button type="button" 
                            class="btn btn-filtro-turno <?= $turno_filtro === 'diurno' ? 'btn-warning text-dark active' : 'btn-outline-warning text-dark' ?>" 
                            onclick="cambiarTurno('diurno')">
                        <i class="bi bi-sun-fill me-1"></i> Diurno (7am-7pm)
                    </button>
                    <button type="button" 
                            class="btn btn-filtro-turno <?= $turno_filtro === 'nocturno' ? 'btn-primary active' : 'btn-outline-primary' ?>" 
                            onclick="cambiarTurno('nocturno')">
                        <i class="bi bi-moon-stars-fill me-1"></i> Nocturno (7pm-7am)
                    </button>
                    <button type="button" 
                            class="btn btn-filtro-turno <?= $turno_filtro === 'ambos' ? 'btn-dark active' : 'btn-outline-dark' ?>" 
                            onclick="cambiarTurno('ambos')">
                        <i class="bi bi-check2-all me-1"></i> Ambos
                    </button>
                </div>
            </div>

            <!-- Selector de Intervalo de Columnas -->
            <div class="col-xl-4 col-lg-3 col-md-12 col-12">
                <label class="form-label small fw-bold text-secondary mb-1">
                    <i class="bi bi-clock me-1"></i> Intervalo de Columnas:
                </label>
                <div class="btn-group btn-group-sm w-100" role="group">
                    <button type="button" 
                            class="btn <?= $intervalo_filtro === '2h' ? 'btn-info text-dark fw-bold active' : 'btn-outline-info text-dark' ?>" 
                            onclick="cambiarIntervalo('2h')" title="Bloques consolidados de 2 horas (diseño amplio y limpio)">
                        2 Horas (Recomendado)
                    </button>
                    <button type="button" 
                            class="btn <?= $intervalo_filtro === '1h' ? 'btn-info text-dark fw-bold active' : 'btn-outline-info text-dark' ?>" 
                            onclick="cambiarIntervalo('1h')" title="Cada hora individual">
                        1 Hora
                    </button>
                </div>
            </div>
        </form>

        <!-- BUSCADOR EN VIVO Y FILTROS RÁPIDOS POR ZONA Y GRUPO -->
        <div class="filter-row border-top pt-2 mt-2">
            <div class="search-wrapper flex-grow-1">
                <i class="bi bi-search text-muted"></i>
                <input type="text" id="buscadorVivo" placeholder="Buscar equipo o parámetro en vivo..." onkeyup="filtrarEnVivo()">
                <button class="btn-clear" type="button" onclick="limpiarBuscador()"><i class="bi bi-x-circle"></i> Limpiar</button>
            </div>
            <span class="fw-bold text-secondary small ms-2"><span id="contador-visibles">0</span> equipos visibles</span>
        </div>

        <?php if ($zona_filtro > 0): ?>
            <!-- Zona Fijada: Vista exclusiva sin opción de cambiar a otras zonas -->
            <div class="filter-row mt-2 align-items-center">
                <span class="filter-label"><i class="bi bi-geo-alt-fill text-danger"></i> Zona:</span>
                <span class="badge bg-primary text-white fs-6 px-3 py-2 rounded-pill shadow-sm">
                    <i class="bi bi-building me-1"></i> <?= htmlspecialchars($nom_zona_filtrada) ?>
                    <span class="badge bg-white text-primary ms-2 rounded-pill"><?= $conteo_equipos[$zona_filtro] ?? 0 ?> equipos</span>
                </span>
                <span class="badge bg-light text-secondary border ms-2">
                    <i class="bi bi-lock-fill me-1"></i> Bloqueado a esta zona
                </span>
            </div>
            
            <div class="filter-row mt-1">
                <span class="filter-label"><i class="bi bi-collection-fill text-success"></i> Equipos de <?= htmlspecialchars($nom_zona_filtrada) ?></span>
                <button class="btn-filter green active" onclick="seleccionarGrupo('todos', this)"><i class="bi bi-asterisk"></i> Todos</button>
                <?php if ($zona_filtro === 1): // Zona Blanca ?>
                    <button class="btn-filter green" onclick="seleccionarGrupo('votator', this)"><i class="bi bi-arrow-repeat"></i> Votators</button>
                    <button class="btn-filter green" onclick="seleccionarGrupo('secador', this)"><i class="bi bi-wind"></i> Secadores</button>
                <?php elseif ($zona_filtro === 2): // Zona Gris ?>
                    <button class="btn-filter green" onclick="seleccionarGrupo('concentrador', this)"><i class="bi bi-layers-half"></i> Concentradores</button>
                    <button class="btn-filter green" onclick="seleccionarGrupo('membrana', this)"><i class="bi bi-funnel-fill"></i> Membranas</button>
                <?php elseif ($zona_filtro === 3): // Zona Negra ?>
                    <button class="btn-filter green" onclick="seleccionarGrupo('chiller', this)"><i class="bi bi-snow"></i> Chillers</button>
                    <button class="btn-filter green" onclick="seleccionarGrupo('compresor', this)"><i class="bi bi-hdd-network"></i> Compresores</button>
                    <button class="btn-filter green" onclick="seleccionarGrupo('caldera', this)"><i class="bi bi-fire"></i> Calderas</button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Todas las Zonas (acceso general sin zona fija) -->
            <div class="filter-row mt-2">
                <span class="filter-label"><i class="bi bi-geo-alt-fill text-danger"></i> Zona</span>
                <button class="btn-filter active" onclick="seleccionarZona(0, this)">
                    <i class="bi bi-globe"></i> Todas
                    <span class="badge-count"><?= array_sum($conteo_equipos) ?></span>
                </button>
                <?php foreach ($lista_zonas as $z): 
                    $count = $conteo_equipos[$z['id']] ?? 0;
                ?>
                    <button class="btn-filter" onclick="seleccionarZona(<?= $z['id'] ?>, this)">
                        <i class="bi bi-building"></i> <?= htmlspecialchars($z['nombre']) ?>
                        <span class="badge-count"><?= $count ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="filter-row mt-1">
                <span class="filter-label"><i class="bi bi-collection-fill text-success"></i> Grupo</span>
                <button class="btn-filter green active" onclick="seleccionarGrupo('todos', this)"><i class="bi bi-asterisk"></i> Todos</button>
                <button class="btn-filter green" onclick="seleccionarGrupo('concentrador', this)"><i class="bi bi-layers-half"></i> Concentradores</button>
                <button class="btn-filter green" onclick="seleccionarGrupo('secador', this)"><i class="bi bi-wind"></i> Secadores</button>
                <button class="btn-filter green" onclick="seleccionarGrupo('chiller', this)"><i class="bi bi-snow"></i> Chillers</button>
                <button class="btn-filter green" onclick="seleccionarGrupo('votator', this)"><i class="bi bi-arrow-repeat"></i> Votators</button>
                <button class="btn-filter green" onclick="seleccionarGrupo('membrana', this)"><i class="bi bi-funnel-fill"></i> Membranas</button>
            </div>
        <?php endif; ?>
    </div>

    <!-- ACORDEÓN DE ZONAS Y EQUIPOS -->
    <div class="accordion accordion-zona" id="accordionZonas">
        <?php
        $sql_zonas = ($zona_filtro > 0) ? "SELECT * FROM zonas WHERE id = $zona_filtro ORDER BY id ASC" : "SELECT * FROM zonas ORDER BY id ASC";
        $res_zonas = mysqli_query($conn, $sql_zonas);
        if ($res_zonas && mysqli_num_rows($res_zonas) > 0) {
            $first = true;
            while ($z = mysqli_fetch_assoc($res_zonas)) {
                $zona_id = (int)$z['id'];
                $zona_nombre = $z['nombre'];
                $esta_zona_abierta = ($zona_filtro === 0) ? $first : ($zona_filtro === $zona_id);
                $zona_display = ($zona_filtro === 0 || $zona_filtro === $zona_id) ? '' : 'style="display: none;"';
                ?>
                <div class="accordion-item bloque-zona mb-3 border rounded-3 overflow-hidden shadow-sm" data-zona-id="<?= $zona_id ?>" <?= $zona_display ?>>
                    <h2 class="accordion-header" id="headingZona<?= $zona_id ?>">
                        <button class="accordion-button <?= $esta_zona_abierta ? '' : 'collapsed' ?> bg-light fw-bold text-dark py-3" type="button" data-bs-toggle="collapse" data-bs-target="#collapseZona<?= $zona_id ?>" aria-expanded="<?= $esta_zona_abierta ? 'true' : 'false' ?>">
                            <i class="bi bi-building me-2 text-primary fs-5"></i>
                            ZONA: <?= htmlspecialchars($zona_nombre) ?>
                            <span class="zona-badge ms-3"><?= $conteo_equipos[$zona_id] ?? 0 ?> equipos</span>
                            <span class="zona-badge ms-2"><i class="bi bi-calendar me-1"></i> <?= date('d/m/Y', strtotime($fecha_filtro)) ?></span>
                        </button>
                    </h2>
                    <div id="collapseZona<?= $zona_id ?>" class="accordion-collapse collapse <?= $esta_zona_abierta ? 'show' : '' ?>" data-bs-parent="#accordionZonas">
                        <div class="accordion-body p-3 bg-white">
                            <?php
                            $sql_areas = "SELECT * FROM areas WHERE zona_id = $zona_id ORDER BY id ASC";
                            $res_areas = mysqli_query($conn, $sql_areas);
                            if ($res_areas && mysqli_num_rows($res_areas) > 0) {
                                while ($a = mysqli_fetch_assoc($res_areas)) {
                                    $area_id = $a['id'];
                                    $area_nombre = $a['nombre'];
                                    ?>
                                    <div class="bloque-area mb-4" data-area-id="<?= $area_id ?>">
                                        <div class="d-flex align-items-center mb-3">
                                            <h6 class="fw-bold text-uppercase text-dark m-0" style="letter-spacing: 0.04em; font-size: 0.88rem;">
                                                <i class="bi bi-diagram-3-fill me-2 text-primary"></i> Área: <?= htmlspecialchars($area_nombre) ?>
                                            </h6>
                                            <hr class="flex-grow-1 ms-3 my-0 border-dark opacity-10">
                                        </div>

                                        <div class="equipos-grid">
                                            <?php
                                            $sql_equipos = "SELECT * FROM equipos WHERE area_id = $area_id AND nombre NOT LIKE 'Producto Votator' ORDER BY id ASC";
                                            $res_equipos = mysqli_query($conn, $sql_equipos);
                                            if ($res_equipos && mysqli_num_rows($res_equipos) > 0) {
                                                while ($eq = mysqli_fetch_assoc($res_equipos)) {
                                                    $eq_id = (int)$eq['id'];
                                                    $eq_nombre = str_replace(['Producto Votator', 'Producto '], 'Votator ', $eq['nombre']);
                                                    $eq_nombre_normalizado = mb_strtolower($eq_nombre, 'UTF-8');

                                                    $es_secador = in_array($eq_id, [21, 22, 23, 24]);
                                                    $es_votator = in_array($eq_id, [84, 85, 86, 87, 88, 89]);
                                                    $es_concentrador = in_array($eq_id, [14, 15, 16, 17, 18]);
                                                    $ext_conc_id = $GLOBALS['MAP_CONCENTRADORES_SIS_EXTRACCION'][$eq_id]['ext_id'] ?? 9;

                                                    // -----------------------------------------------------------------
                                                    // CASO ESPECIAL: MEMBRANAS (ID 100)
                                                    // -----------------------------------------------------------------
                                                    if ($eq_id === 100) {
                                                    ?>
                                                    <div class="tarjeta-equipo mb-3" data-equipo-id="100" data-zona-id="<?= $zona_id ?>" data-equipo-nombre="sistema de membranas ultrafiltracion membrana">
                                                        <div class="card-equipo" id="equipo-100">
                                                            <div class="card-header-equipo bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <h6 class="m-0 fw-bold"><i class="bi bi-funnel-fill text-primary me-2"></i> <?= htmlspecialchars($eq_nombre) ?></h6>
                                                                    <span class="badge bg-primary text-white rounded-pill px-3 py-1 small">4 Etapas · 13 Paquetes</span>
                                                                    <span class="badge bg-info text-dark rounded-pill px-2 py-1 small"><?= $total_memb_en_turno ?> Capturas</span>
                                                                </div>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <a href="captura.php?id=100" class="btn-captura"><i class="bi bi-pencil-square me-1"></i> Capturar</a>
                                                                    <a href="historial.php?id=100&fecha=<?= $fecha_filtro ?>&turno=<?= $turno_filtro ?>" class="btn-historial"><i class="bi bi-clock-history me-1"></i> Historial</a>
                                                                </div>
                                                            </div>
                                                            
                                                            <?php foreach ($turnos_a_mostrar as $t_key): 
                                                                $t_info = $turnos_config[$t_key];
                                                                $t_bloques = $t_info['bloques'];
                                                            ?>
                                                            <div class="mb-4">
                                                                <!-- Barra de Equipo y Turno (Estilo Idéntico a Imagen 2) -->
                                                                <div class="title-equipo" style="<?= $t_key === 'nocturno' ? 'border-left-color: #3b82f6;' : 'border-left-color: #0284c7;' ?>">
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <span><strong>Equipo:</strong> <?= htmlspecialchars($eq_nombre) ?></span>
                                                                        <span class="vr mx-1"></span>
                                                                        <span class="badge <?= $t_key === 'nocturno' ? 'bg-primary text-white' : 'bg-warning text-dark' ?> px-3 py-1 fs-6">
                                                                            <i class="bi <?= $t_info['icono'] ?> me-1"></i><?= strtoupper($t_info['titulo']) ?>
                                                                        </span>
                                                                    </div>
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <span><strong>Fecha Operativa:</strong> <?= date('Y-m-d', strtotime($fecha_filtro)) ?></span>
                                                                        <a href="captura.php?id=100" class="btn btn-sm btn-success rounded-pill px-3 fw-bold ms-2" style="font-size:0.75rem;"><i class="bi bi-pencil-square me-1"></i> Capturar</a>
                                                                        <a href="historial.php?id=100&fecha=<?= $fecha_filtro ?>&turno=<?= $t_key ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" style="font-size:0.75rem;"><i class="bi bi-clock-history me-1"></i> Historial</a>
                                                                    </div>
                                                                </div>

                                                                <table class="table-matriz">
                                                                    <thead>
                                                                        <tr class="header-navy" style="<?= $t_key === 'nocturno' ? 'background-color: #0f172a !important;' : '' ?>">
                                                                            <th style="width: 215px; text-align: left !important; padding-left: 12px !important;">Paquete / Parámetro</th>
                                                                            <th class="th-rango-verde" title="Rango Óptimo">Verde</th>
                                                                            <th class="th-rango-amarillo" title="Rango de Alerta">Amarillo</th>
                                                                            <th class="th-rango-rojo" title="Rango Crítico">Rojo</th>
                                                                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                                                                $hNum = intval(substr($b_key, 0, 2));
                                                                                $esDiaSig = ($t_key === 'nocturno' && $hNum < 12);
                                                                            ?>
                                                                                <th style="vertical-align: middle;">
                                                                                    <div style="font-size: 11px; font-weight: bold;"><?= $b_data['label'] ?></div>
                                                                                    <div style="font-size: 8px; font-weight: 600; opacity: 0.9; color: <?= $esDiaSig ? '#93c5fd' : '#cbd5e1' ?>;"><?= $b_data['sub'] ?></div>
                                                                                </th>
                                                                            <?php endforeach; ?>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <!-- Fila 1: Hora del Registro -->
                                                                        <tr class="row-hora-registro">
                                                                            <td class="param-name">Hora del Registro</td>
                                                                            <td class="text-center text-muted fw-bold" style="background-color: #f8fafc; font-size: 9px;">--</td>
                                                                            <td class="text-center text-muted fw-bold" style="background-color: #f8fafc; font-size: 9px;">--</td>
                                                                            <td class="text-center text-muted fw-bold" style="background-color: #f8fafc; font-size: 9px;">--</td>
                                                                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                                                                $h_ex = $membranas_turnos[$t_key]['horas_exactas'][$b_key] ?? null;
                                                                            ?>
                                                                                <td class="hora-val-cell text-center <?= $h_ex ? 'fw-bold text-dark' : 'text-muted' ?>">
                                                                                    <?= $h_ex ? htmlspecialchars($h_ex) : '' ?>
                                                                                </td>
                                                                            <?php endforeach; ?>
                                                                        </tr>

                                                                        <!-- Paquetes por Etapa -->
                                                                        <?php foreach ($etapas_membranas_def as $eNum => $eInfo): ?>
                                                                            <tr class="seccion-etapa-header" style="background-color: #f1f5f9; border-top: 2px solid #cbd5e1;">
                                                                                <td colspan="<?= count($t_bloques) + 4 ?>" class="fw-bold text-uppercase py-1 px-3 text-start text-primary" style="font-size: 0.78rem; text-align: left !important;">
                                                                                    <i class="bi bi-diagram-3-fill me-1"></i> <?= $eInfo['nombre'] ?>
                                                                                </td>
                                                                            </tr>
                                                                            <?php foreach ($eInfo['paquetes'] as $paqNum): ?>
                                                                                <tr>
                                                                                    <td class="param-name">
                                                                                        <span class="badge bg-secondary rounded-pill me-1" style="font-size: 0.65rem;">E<?= $eNum ?></span>
                                                                                        Paquete <?= $paqNum ?> <small class="text-muted">(ΔP bar)</small>
                                                                                    </td>
                                                                                    <td class="col-rango-verde">&lt; 1.20 bar</td>
                                                                                    <td class="col-rango-amarillo">1.20 - 1.80 bar</td>
                                                                                    <td class="col-rango-rojo">≥ 1.80 bar / F.O.</td>
                                                                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                                                                        $rm = $membranas_turnos[$t_key]['paquetes'][$paqNum][$b_key] ?? null;
                                                                                        if ($rm):
                                                                                            $est = strtoupper(trim($rm['estado'] ?? ''));
                                                                                            $dp = $rm['delta_presion'];
                                                                                            if ($est === 'LAVADO') { $cCls = 'cell-matrix-lavado'; $bText = 'LAVADO'; }
                                                                                            elseif ($est === 'MMT' || $est === 'MMTT') { $cCls = 'cell-matrix-amarillo'; $bText = 'MMTT'; }
                                                                                            elseif ($est === 'FO' || $est === 'F.O.') { $cCls = 'cell-matrix-rojo'; $bText = 'F.O.'; }
                                                                                            elseif ($dp !== null && is_numeric($dp)) {
                                                                                                $dpVal = floatval($dp);
                                                                                                if ($dpVal < 1.20) $cCls = 'cell-matrix-verde';
                                                                                                elseif ($dpVal < 1.80) $cCls = 'cell-matrix-amarillo';
                                                                                                else $cCls = 'cell-matrix-rojo';
                                                                                                $bText = number_format($dpVal, 2);
                                                                                            } else {
                                                                                                $cCls = 'cell-matrix-gris';
                                                                                                $bText = !empty($est) ? $est : 'OK';
                                                                                            }
                                                                                            $regJson = htmlspecialchars(json_encode($rm), ENT_QUOTES, 'UTF-8');
                                                                                    ?>
                                                                                        <td class="cell-matrix <?= $cCls ?>" onclick='abrirModalDetalleMemb(<?= $regJson ?>)' title="Clic para ver detalle completo">
                                                                                            <?= $bText ?>
                                                                                        </td>
                                                                                    <?php else: ?>
                                                                                        <td class="cell-matrix cell-matrix-empty">-</td>
                                                                                    <?php endif; ?>
                                                                                    <?php endforeach; ?>
                                                                                </tr>
                                                                            <?php endforeach; ?>
                                                                        <?php endforeach; ?>

                                                                        <!-- Monitoreo de Brix -->
                                                                        <tr class="seccion-etapa-header" style="background-color: #fef9c3; border-top: 2px solid #fde047;">
                                                                            <td colspan="<?= count($t_bloques) + 4 ?>" class="fw-bold text-uppercase py-1 px-3 text-start" style="color: #854d0e; font-size: 0.78rem; text-align: left !important;">
                                                                                <i class="bi bi-droplet-half me-1"></i> MONITOREO DE BRIX Y CONCENTRACIÓN
                                                                            </td>
                                                                        </tr>
                                                                        <tr>
                                                                            <td class="param-name">Brix Entrada (°Bx)</td>
                                                                            <td class="col-rango-verde">4.0 - 8.0 °Bx</td>
                                                                            <td class="col-rango-amarillo">3.0 - 4.0 °Bx</td>
                                                                            <td class="col-rango-rojo">&lt; 3.0 °Bx</td>
                                                                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                                                                $bx = $membranas_turnos[$t_key]['brix_in'][$b_key] ?? null;
                                                                            ?>
                                                                                <td><?= ($bx !== null) ? '<span class="badge bg-light text-dark border fw-bold">' . number_format($bx, 1) . '</span>' : '<span class="text-muted" style="opacity:0.35;">-</span>' ?></td>
                                                                            <?php endforeach; ?>
                                                                        </tr>
                                                                        <tr>
                                                                            <td class="param-name">Brix Salida / Concentrado (°Bx)</td>
                                                                            <td class="col-rango-verde">≥ 18.0 °Bx</td>
                                                                            <td class="col-rango-amarillo">16.0 - 17.9 °Bx</td>
                                                                            <td class="col-rango-rojo">&lt; 16.0 °Bx</td>
                                                                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                                                                $bx = $membranas_turnos[$t_key]['brix_out'][$b_key] ?? null;
                                                                                $cClsBrix = ($bx !== null) ? ($bx >= 18.0 ? 'cell-matrix-verde' : ($bx >= 16.0 ? 'cell-matrix-amarillo' : 'cell-matrix-rojo')) : '';
                                                                            ?>
                                                                                <?php if ($bx !== null): ?>
                                                                                    <td class="cell-matrix <?= $cClsBrix ?>"><?= number_format($bx, 1) ?></td>
                                                                                <?php else: ?>
                                                                                    <td class="cell-matrix cell-matrix-empty">-</td>
                                                                                <?php endif; ?>
                                                                            <?php endforeach; ?>
                                                                        </tr>
                                                                        <tr>
                                                                            <td class="param-name">Brix Permeado (°Bx)</td>
                                                                            <td class="col-rango-verde">≤ 0.5 °Bx</td>
                                                                            <td class="col-rango-amarillo">0.5 - 1.0 °Bx</td>
                                                                            <td class="col-rango-rojo">&gt; 1.0 °Bx</td>
                                                                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                                                                $bx = $membranas_turnos[$t_key]['brix_perm'][$b_key] ?? null;
                                                                                $cClsBrix = ($bx !== null) ? ($bx <= 0.5 ? 'cell-matrix-verde' : ($bx <= 1.0 ? 'cell-matrix-amarillo' : 'cell-matrix-rojo')) : '';
                                                                            ?>
                                                                                <?php if ($bx !== null): ?>
                                                                                    <td class="cell-matrix <?= $cClsBrix ?>"><?= number_format($bx, 1) ?></td>
                                                                                <?php else: ?>
                                                                                    <td class="cell-matrix cell-matrix-empty">-</td>
                                                                                <?php endif; ?>
                                                                            <?php endforeach; ?>
                                                                        </tr>

                                                                        <!-- Observaciones / Acciones Membranas -->
                                                                        <tr class="row-observaciones">
                                                                            <td class="param-name">
                                                                                <div class="d-flex align-items-center justify-content-between">
                                                                                    <span class="text-truncate">Observaciones / Acciones</span>
                                                                                    <button type="button" class="btn btn-outline-dark py-0 px-1 ms-1 text-nowrap shadow-none" style="font-size: 8.5px; border-color: #cbd5e1; background: #ffffff;" onclick="toggleDesplegarObservaciones('obs_box_100_<?= $t_key ?>')">
                                                                                        <i class="bi bi-arrows-expand me-1"></i>Ver todas
                                                                                    </button>
                                                                                </div>
                                                                            </td>
                                                                            <td class="col-rango-verde text-muted fw-bold">--</td>
                                                                            <td class="col-rango-amarillo text-muted fw-bold">--</td>
                                                                            <td class="col-rango-rojo text-muted fw-bold">--</td>
                                                                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                                                                $obs_b = $membranas_turnos[$t_key]['meta'][$b_key]['obs'] ?? '';
                                                                            ?>
                                                                                <?php if (!empty($obs_b)): ?>
                                                                                    <td class="cell-obs-interactive" style="background-color: #fffbeb !important; color: #92400e !important;" 
                                                                                        onclick="alert('Observación de Membranas (<?= $b_key ?>):\n\n<?= addslashes(htmlspecialchars($obs_b)) ?>')" 
                                                                                        title="<?= htmlspecialchars($obs_b) ?>">
                                                                                        <div class="d-flex align-items-center justify-content-center gap-1" style="max-width: 100%; overflow: hidden;">
                                                                                            <i class="bi bi-chat-left-text-fill text-warning flex-shrink-0" style="font-size: 10px;"></i>
                                                                                            <span class="text-truncate" style="font-size: 9.5px;"><?= htmlspecialchars($obs_b) ?></span>
                                                                                        </div>
                                                                                    </td>
                                                                                <?php else: ?>
                                                                                    <td class="text-muted" style="background-color: #ffffff; font-size: 0.75rem;">-</td>
                                                                                <?php endif; ?>
                                                                            <?php endforeach; ?>
                                                                        </tr>

                                                                        <!-- Operador Membranas -->
                                                                        <tr class="row-operador">
                                                                            <td class="param-name"><i class="bi bi-person-badge text-primary me-1"></i> Operador (Nómina)</td>
                                                                            <td class="col-rango-verde text-muted fw-bold">--</td>
                                                                            <td class="col-rango-amarillo text-muted fw-bold">--</td>
                                                                            <td class="col-rango-rojo text-muted fw-bold">--</td>
                                                                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                                                                $op = $membranas_turnos[$t_key]['meta'][$b_key]['operador'] ?? '';
                                                                            ?>
                                                                                <td style="background-color: #ffffff;">
                                                                                    <?php if (!empty($op)): ?>
                                                                                        <span class="badge bg-dark text-white rounded-pill px-2 py-1" style="font-size: 0.72rem;"><?= htmlspecialchars($op) ?></span>
                                                                                    <?php else: ?>
                                                                                        <span class="text-muted" style="font-size: 0.75rem;">-</span>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                            <?php endforeach; ?>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>

                                                                <!-- Panel desplegable de Observaciones Membranas -->
                                                                <div id="obs_box_100_<?= $t_key ?>" class="p-3 bg-light border rounded mb-3" style="display: none;">
                                                                    <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-chat-left-text-fill text-warning me-1"></i> Observaciones <?= $t_info['titulo'] ?> - Membranas</h6>
                                                                    <ul class="list-group list-group-flush small">
                                                                        <?php 
                                                                        $hay_obs_m = false;
                                                                        foreach ($t_bloques as $b_key => $b_data) {
                                                                            $o = $membranas_turnos[$t_key]['meta'][$b_key]['obs'] ?? '';
                                                                            if (!empty($o)) {
                                                                                $hay_obs_m = true;
                                                                                echo '<li class="list-group-item bg-transparent py-1"><strong>' . $b_key . ':</strong> ' . htmlspecialchars($o) . '</li>';
                                                                            }
                                                                        }
                                                                        if (!$hay_obs_m) {
                                                                            echo '<li class="list-group-item bg-transparent text-muted py-1">No hay observaciones registradas en este turno.</li>';
                                                                        }
                                                                        ?>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <?php
                                                        continue;
                                                    }
                                                    ?>

                                                    <!-- ------------------------------------------------------------- -->
                                                    <!-- CASO GENERAL: EQUIPOS NORMALES (CONCENTRACIÓN, CHILLERS, ETC.) -->
                                                    <!-- ------------------------------------------------------------- -->
                                                    <div class="tarjeta-equipo mb-3" data-equipo-id="<?= $eq_id ?>" data-zona-id="<?= $zona_id ?>" data-equipo-nombre="<?= htmlspecialchars($eq_nombre_normalizado) ?>">
                                                        <div class="card-equipo" id="equipo-<?= $eq_id ?>">
                                                            <div class="card-header-equipo bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <h6 class="m-0 fw-bold"><i class="bi bi-cpu text-primary me-2"></i> <?= htmlspecialchars($eq_nombre) ?></h6>
                                                                    <?php if ($es_secador): ?>
                                                                        <span class="badge bg-warning text-dark border rounded-pill px-2 py-1 small">Módulo Secado</span>
                                                                    <?php elseif ($es_votator): ?>
                                                                        <span class="badge bg-info text-dark rounded-pill px-2 py-1 small">Votator</span>
                                                                    <?php elseif ($es_concentrador): ?>
                                                                        <span class="badge bg-primary text-white rounded-pill px-2 py-1 small">Módulo Concentración</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <?php if ($es_secador): ?>
                                                                        <a href="http://localhost/sis_extraccion/index.php?view=secadores" class="btn btn-sm btn-outline-primary fw-bold rounded-pill" target="_blank" style="font-size:0.75rem;">
                                                                            <i class="bi bi-box-arrow-up-right me-1"></i> Sis_Extracción
                                                                        </a>
                                                                    <?php elseif ($es_votator): ?>
                                                                        <a href="http://localhost/sis_extraccion/index.php?view=votators" class="btn btn-sm btn-outline-primary fw-bold rounded-pill" target="_blank" style="font-size:0.75rem;">
                                                                            <i class="bi bi-box-arrow-up-right me-1"></i> Sis_Extracción
                                                                        </a>
                                                                    <?php else: ?>
                                                                        <a href="captura.php?id=<?= $eq_id ?>" class="btn-captura"><i class="bi bi-pencil-square me-1"></i> Capturar</a>
                                                                    <?php endif; ?>
                                                                    <a href="historial.php?id=<?= $eq_id ?>&fecha=<?= $fecha_filtro ?>&turno=<?= $turno_filtro ?>" class="btn-historial"><i class="bi bi-clock-history me-1"></i> Historial</a>
                                                                </div>
                                                            </div>

                                                            <?php foreach ($turnos_a_mostrar as $t_key): 
                                                                $t_info = $turnos_config[$t_key];
                                                                $t_bloques = $t_info['bloques'];
                                                            ?>
                                                            <div class="mb-4">
                                                                <!-- Barra de Equipo y Turno (Formato Idéntico a Imagen 2) -->
                                                                <div class="title-equipo" style="<?= $t_key === 'nocturno' ? 'border-left-color: #3b82f6;' : 'border-left-color: #0284c7;' ?>">
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <span><strong>Equipo:</strong> <?= htmlspecialchars($eq_nombre) ?></span>
                                                                        <span class="vr mx-1"></span>
                                                                        <span class="badge <?= $t_key === 'nocturno' ? 'bg-primary text-white' : 'bg-warning text-dark' ?> px-3 py-1 fs-6">
                                                                            <i class="bi <?= $t_info['icono'] ?> me-1"></i><?= strtoupper($t_info['titulo']) ?>
                                                                        </span>
                                                                        <?php if ($es_secador): ?>
                                                                            <span class="badge bg-warning text-dark border rounded-pill px-2 py-1 small">Módulo Secado</span>
                                                                        <?php elseif ($es_votator): ?>
                                                                            <span class="badge bg-info text-dark rounded-pill px-2 py-1 small">Votator</span>
                                                                        <?php elseif ($es_concentrador): ?>
                                                                            <span class="badge bg-primary text-white rounded-pill px-2 py-1 small">Módulo Concentración</span>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="d-flex align-items-center gap-2">
                                                                        <span><strong>Fecha Operativa:</strong> <?= date('Y-m-d', strtotime($fecha_filtro)) ?></span>
                                                                        <?php if ($es_secador): ?>
                                                                            <a href="http://localhost/sis_extraccion/index.php?view=secadores" class="btn btn-sm btn-outline-primary fw-bold rounded-pill" target="_blank" style="font-size:0.75rem;">
                                                                                <i class="bi bi-box-arrow-up-right me-1"></i> Sis_Extracción
                                                                            </a>
                                                                        <?php elseif ($es_votator): ?>
                                                                            <a href="http://localhost/sis_extraccion/index.php?view=votators" class="btn btn-sm btn-outline-primary fw-bold rounded-pill" target="_blank" style="font-size:0.75rem;">
                                                                                <i class="bi bi-box-arrow-up-right me-1"></i> Sis_Extracción
                                                                            </a>
                                                                        <?php else: ?>
                                                                            <a href="captura.php?id=<?= $eq_id ?>" class="btn btn-sm btn-success rounded-pill px-3 fw-bold ms-2" style="font-size:0.75rem;"><i class="bi bi-pencil-square me-1"></i> Capturar</a>
                                                                        <?php endif; ?>
                                                                        <a href="historial.php?id=<?= $eq_id ?>&fecha=<?= $fecha_filtro ?>&turno=<?= $t_key ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold" style="font-size:0.75rem;"><i class="bi bi-clock-history me-1"></i> Historial</a>
                                                                    </div>
                                                                </div>

                                                                <table class="table-matriz">
                                                                    <thead>
                                                                        <tr class="header-navy" style="<?= $t_key === 'nocturno' ? 'background-color: #0f172a !important;' : '' ?>">
                                                                            <th style="width: 215px; text-align: left !important; padding-left: 12px !important;">Parámetro</th>
                                                                            <th class="th-rango-verde" title="Rango Óptimo">Verde</th>
                                                                            <th class="th-rango-amarillo" title="Rango de Alerta">Amarillo</th>
                                                                            <th class="th-rango-rojo" title="Rango Crítico">Rojo</th>
                                                                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                                                                $hNum = intval(substr($b_key, 0, 2));
                                                                                $esDiaSig = ($t_key === 'nocturno' && $hNum < 12);
                                                                            ?>
                                                                                <th style="vertical-align: middle;">
                                                                                    <div style="font-size: 11px; font-weight: bold;"><?= $b_data['label'] ?></div>
                                                                                    <div style="font-size: 8px; font-weight: 600; opacity: 0.9; color: <?= $esDiaSig ? '#93c5fd' : '#cbd5e1' ?>;"><?= $b_data['sub'] ?></div>
                                                                                </th>
                                                                            <?php endforeach; ?>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <!-- Fila 1: Hora del Registro (Exacta) -->
                                                                        <tr class="row-hora-registro">
                                                                            <td class="param-name">Hora del Registro</td>
                                                                            <td class="text-center text-muted fw-bold" style="background-color: #f8fafc; font-size: 9px;">--</td>
                                                                            <td class="text-center text-muted fw-bold" style="background-color: #f8fafc; font-size: 9px;">--</td>
                                                                            <td class="text-center text-muted fw-bold" style="background-color: #f8fafc; font-size: 9px;">--</td>
                                                                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                                                                $f_ex = $cache_meta_equipo[$t_key][$eq_id][$b_key]['fecha_exacta'] ?? null;
                                                                                $hora_str = $f_ex ? date('H:i:s', strtotime($f_ex)) : '';
                                                                            ?>
                                                                                <td class="hora-val-cell text-center <?= $f_ex ? 'fw-bold text-dark' : 'text-muted' ?>">
                                                                                    <?= $hora_str ?>
                                                                                </td>
                                                                            <?php endforeach; ?>
                                                                        </tr>

                                                                        <!-- Filas de Parámetros -->
                                                                        <?php
                                                                        $q_params = "SELECT * FROM parametros WHERE equipo_id = $eq_id ORDER BY id ASC";
                                                                        $res_params = mysqli_query($conn, $q_params);
                                                                        $lista_params_eq = [];
                                                                        if ($res_params && mysqli_num_rows($res_params) > 0) {
                                                                            while ($p_tmp = mysqli_fetch_assoc($res_params)) {
                                                                                $lista_params_eq[(int)$p_tmp['id']] = $p_tmp;
                                                                            }
                                                                            if ($es_concentrador && isset($GLOBALS['MAP_CONCENTRADORES_SIS_EXTRACCION'][$eq_id]['orden_params'])) {
                                                                                $ordenados_tmp = [];
                                                                                foreach ($GLOBALS['MAP_CONCENTRADORES_SIS_EXTRACCION'][$eq_id]['orden_params'] as $pid_o) {
                                                                                    if (isset($lista_params_eq[$pid_o])) {
                                                                                        $ordenados_tmp[$pid_o] = $lista_params_eq[$pid_o];
                                                                                    }
                                                                                }
                                                                                foreach ($lista_params_eq as $pid_r => $p_r) {
                                                                                    if (!isset($ordenados_tmp[$pid_r])) {
                                                                                        $ordenados_tmp[$pid_r] = $p_r;
                                                                                    }
                                                                                }
                                                                                $lista_params_eq = $ordenados_tmp;
                                                                            }
                                                                            foreach ($lista_params_eq as $p) {
                                                                                $pid = (int)$p['id'];
                                                                                $p_nombre_norm = mb_strtolower($p['nombre_parametro'], 'UTF-8');
                                                                                $rangos = obtenerRangosDesglosados($p);
                                                                                $tiene_cambios_hist = !empty($historial_rangos[$pid]);
                                                                                $ult_cambio_info = $tiene_cambios_hist ? end($historial_rangos[$pid]) : null;
                                                                                ?>
                                                                                <tr class="fila-parametro" data-param-nombre="<?= htmlspecialchars($p_nombre_norm) ?>">
                                                                                    <td class="param-name" title="<?= htmlspecialchars($p['nombre_parametro']) ?>">
                                                                                        <span class="text-truncate d-inline-block" style="max-width: 215px;">
                                                                                            <?= htmlspecialchars($p['nombre_parametro']) ?>
                                                                                        </span>
                                                                                        <?php if ($tiene_cambios_hist && $ult_cambio_info): ?>
                                                                                            <span class="badge-param-modificado ms-1" title="Rango ajustado el <?= date('d/m/Y', strtotime($ult_cambio_info['fecha_cambio'])) ?>">
                                                                                                <i class="bi bi-sliders"></i>
                                                                                            </span>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                    <td class="col-rango-verde" title="Rango Óptimo: <?= htmlspecialchars($rangos['verde']) ?>"><?= htmlspecialchars($rangos['verde']) ?></td>
                                                                                    <td class="col-rango-amarillo" title="Rango de Alerta: <?= htmlspecialchars($rangos['amarillo']) ?>"><?= htmlspecialchars($rangos['amarillo']) ?></td>
                                                                                    <td class="col-rango-rojo" title="Rango Crítico: <?= htmlspecialchars($rangos['rojo']) ?>"><?= htmlspecialchars($rangos['rojo']) ?></td>

                                                                                    <!-- Celdas de Datos por Bloque Horario (Pintadas completas como Imagen 2) -->
                                                                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                                                                        $reg = $cache_lecturas[$t_key][$pid][$b_key] ?? null;
                                                                                        $val = $reg['val'] ?? null;
                                                                                        if ($val !== null && $val !== '') {
                                                                                            $f_reg_celda = $reg['fecha_reg'] ?? null;
                                                                                            $p_eval = obtenerParametroEnFecha($p, $f_reg_celda, $historial_rangos);
                                                                                            $bClass = getBadgeClass($val, $p_eval);
                                                                                            if ($bClass === 'bg-green') $cCls = 'cell-matrix-verde';
                                                                                            elseif ($bClass === 'bg-yellow') $cCls = 'cell-matrix-amarillo';
                                                                                            elseif ($bClass === 'bg-red') $cCls = 'cell-matrix-rojo';
                                                                                            elseif ($bClass === 'bg-lavado') $cCls = 'cell-matrix-lavado';
                                                                                            else $cCls = 'cell-matrix-gris';

                                                                                            $mostrar = is_numeric($val) ? (floatval($val) == intval($val) ? number_format(floatval($val), 0) : number_format(floatval($val), 2)) : htmlspecialchars($val);
                                                                                            $lote_val = $reg['lote'] ?? '';
                                                                                            $obs_val = $reg['obs'] ?? '';
                                                                                            $nom_val = $reg['nomina'] ?? '';

                                                                                            // JSON para el modal
                                                                                            $pNomJ = htmlspecialchars(json_encode($p['nombre_parametro']), ENT_QUOTES, 'UTF-8');
                                                                                            $valJ = htmlspecialchars(json_encode($mostrar), ENT_QUOTES, 'UTF-8');
                                                                                            $bClassJ = htmlspecialchars(json_encode($bClass), ENT_QUOTES, 'UTF-8');
                                                                                            $fExJ = htmlspecialchars(json_encode($f_reg_celda ? date("d/m/Y h:i:s A", strtotime($f_reg_celda)) : '-'), ENT_QUOTES, 'UTF-8');
                                                                                            $nomJ = htmlspecialchars(json_encode($nom_val ?: 'N/A'), ENT_QUOTES, 'UTF-8');
                                                                                            $loteJ = htmlspecialchars(json_encode($lote_val ?: '-'), ENT_QUOTES, 'UTF-8');
                                                                                            $obsJ = htmlspecialchars(json_encode($obs_val ?: 'Sin notas'), ENT_QUOTES, 'UTF-8');
                                                                                            $rangoJ = htmlspecialchars(json_encode("Verde: {$rangos['verde']} | Amarillo: {$rangos['amarillo']} | Rojo: {$rangos['rojo']}"), ENT_QUOTES, 'UTF-8');
                                                                                    ?>
                                                                                        <td class="cell-matrix <?= $cCls ?>" 
                                                                                            onclick='abrirModalDetalleGral(<?= $pNomJ ?>, <?= $valJ ?>, <?= $bClassJ ?>, <?= $fExJ ?>, <?= $nomJ ?>, <?= $loteJ ?>, <?= $obsJ ?>, <?= $rangoJ ?>)'
                                                                                            title="<?= htmlspecialchars($p['nombre_parametro']) ?>: <?= $mostrar ?> (Clic para ver detalle)">
                                                                                            <?= $mostrar ?>
                                                                                        </td>
                                                                                    <?php } else { ?>
                                                                                        <td class="cell-matrix cell-matrix-empty">-</td>
                                                                                    <?php } ?>
                                                                                    <?php endforeach; ?>
                                                                                </tr>
                                                                                <?php
                                                                            }
                                                                        } else {
                                                                            echo '<tr><td colspan="' . (count($t_bloques) + 4) . '" class="text-muted text-center py-3">Este equipo no tiene parámetros configurados.</td></tr>';
                                                                        }
                                                                        ?>

                                                                        <!-- Fila Observaciones / Acciones (Idéntico a Imagen 2) -->
                                                                        <tr class="row-observaciones">
                                                                            <td class="param-name">
                                                                                <div class="d-flex align-items-center justify-content-between">
                                                                                    <span class="text-truncate">Observaciones / Acciones</span>
                                                                                    <button type="button" class="btn btn-outline-dark py-0 px-1 ms-1 text-nowrap shadow-none" style="font-size: 8.5px; border-color: #cbd5e1; background: #ffffff;" onclick="toggleDesplegarObservaciones('obs_box_<?= $eq_id ?>_<?= $t_key ?>')">
                                                                                        <i class="bi bi-arrows-expand me-1"></i>Ver todas
                                                                                    </button>
                                                                                </div>
                                                                            </td>
                                                                            <td class="col-rango-verde text-muted fw-bold">--</td>
                                                                            <td class="col-rango-amarillo text-muted fw-bold">--</td>
                                                                            <td class="col-rango-rojo text-muted fw-bold">--</td>
                                                                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                                                                $obs_b = $cache_meta_equipo[$t_key][$eq_id][$b_key]['obs'] ?? '';
                                                                            ?>
                                                                                <?php if (!empty($obs_b)): ?>
                                                                                    <td class="cell-obs-interactive" style="background-color: #fffbeb !important; color: #92400e !important;" 
                                                                                        onclick="alert('Observación de <?= htmlspecialchars($eq_nombre, ENT_QUOTES) ?> (<?= $b_key ?>):\n\n<?= addslashes(htmlspecialchars($obs_b)) ?>')" 
                                                                                        title="<?= htmlspecialchars($obs_b) ?>">
                                                                                        <div class="d-flex align-items-center justify-content-center gap-1" style="max-width: 100%; overflow: hidden;">
                                                                                            <i class="bi bi-chat-left-text-fill text-warning flex-shrink-0" style="font-size: 10px;"></i>
                                                                                            <span class="text-truncate" style="font-size: 9.5px;"><?= htmlspecialchars($obs_b) ?></span>
                                                                                        </div>
                                                                                    </td>
                                                                                <?php else: ?>
                                                                                    <td class="text-muted" style="background-color: #ffffff; font-size: 0.75rem;">-</td>
                                                                                <?php endif; ?>
                                                                            <?php endforeach; ?>
                                                                        </tr>

                                                                        <!-- Fila Operador (Nómina) -->
                                                                        <tr class="row-operador">
                                                                            <td class="param-name"><i class="bi bi-person-badge text-primary me-1"></i> Operador (Nómina)</td>
                                                                            <td class="col-rango-verde text-muted fw-bold">--</td>
                                                                            <td class="col-rango-amarillo text-muted fw-bold">--</td>
                                                                            <td class="col-rango-rojo text-muted fw-bold">--</td>
                                                                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                                                                $op = $cache_meta_equipo[$t_key][$eq_id][$b_key]['operador'] ?? '';
                                                                            ?>
                                                                                <td style="background-color: #ffffff;">
                                                                                    <?php if (!empty($op)): ?>
                                                                                        <span class="badge bg-dark text-white rounded-pill px-2 py-1" style="font-size: 0.72rem;"><?= htmlspecialchars($op) ?></span>
                                                                                    <?php else: ?>
                                                                                        <span class="text-muted" style="font-size: 0.75rem;">-</span>
                                                                                    <?php endif; ?>
                                                                                </td>
                                                                            <?php endforeach; ?>
                                                                        </tr>
                                                                    </tbody>
                                                                </table>

                                                                <!-- Panel desplegable de Observaciones del Equipo -->
                                                                <div id="obs_box_<?= $eq_id ?>_<?= $t_key ?>" class="p-3 bg-light border rounded mb-3" style="display: none;">
                                                                    <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-chat-left-text-fill text-warning me-1"></i> Observaciones <?= $t_info['titulo'] ?> - <?= htmlspecialchars($eq_nombre) ?></h6>
                                                                    <ul class="list-group list-group-flush small">
                                                                        <?php 
                                                                        $hay_obs_eq = false;
                                                                        foreach ($t_bloques as $b_key => $b_data) {
                                                                            $o = $cache_meta_equipo[$t_key][$eq_id][$b_key]['obs'] ?? '';
                                                                            if (!empty($o)) {
                                                                                $hay_obs_eq = true;
                                                                                echo '<li class="list-group-item bg-transparent py-1"><strong>' . $b_key . ':</strong> ' . htmlspecialchars($o) . '</li>';
                                                                            }
                                                                        }
                                                                        if (!$hay_obs_eq) {
                                                                            echo '<li class="list-group-item bg-transparent text-muted py-1">No hay observaciones registradas en este turno.</li>';
                                                                        }
                                                                        ?>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <?php
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <?php
                $first = false;
            }
        }
        ?>
    </div>
</div>

<!-- MODAL PARA VER DETALLE COMPLETO DE CUALQUIER LECTURA GENERAL -->
<div class="modal fade" id="modalDetalleGeneral" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="modalGeneralParamNombre">Detalle del Parámetro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-3">
                    <span class="modal-badge-grande shadow-sm" id="modalGeneralValorBadge">-</span>
                </div>
                <div class="row g-2 text-start mt-3 p-3 bg-light rounded border">
                    <div class="col-12">
                        <span class="text-muted small d-block">Rangos Operativos:</span>
                        <strong class="text-dark" id="modalGeneralRangoMeta">-</strong>
                    </div>
                    <div class="col-6 mt-2">
                        <span class="text-muted small d-block">Fecha y Hora Exacta:</span>
                        <strong class="text-dark" id="modalGeneralFechaHora">-</strong>
                    </div>
                    <div class="col-6 mt-2">
                        <span class="text-muted small d-block">Nómina del Operador:</span>
                        <strong class="text-dark" id="modalGeneralNomina">-</strong>
                    </div>
                    <div class="col-12 mt-2">
                        <span class="text-muted small d-block">Lote / Datos Asociados:</span>
                        <strong class="text-dark" id="modalGeneralLote">-</strong>
                    </div>
                    <div class="col-12 mt-3 pt-2 border-top">
                        <span class="text-muted small d-block">Notas u Observaciones:</span>
                        <p class="m-0 text-dark" id="modalGeneralObservaciones" style="white-space: pre-wrap;">-</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm fw-bold px-3" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE DETALLE DE MEDIDAS DE MEMBRANAS -->
<div class="modal fade" id="modalDetalleMembrana" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3 px-4">
                <div>
                    <h5 class="modal-title fw-bold m-0" id="mDetalleMembTitulo">
                        <i class="bi bi-funnel-fill me-2"></i> Detalle de Medición de Membranas
                    </h5>
                    <small class="opacity-75" id="mDetalleMembSubtitulo">Lectura individual por paquete</small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="d-flex flex-wrap justify-content-between align-items-center bg-white p-3 rounded-3 shadow-sm mb-3 border">
                    <div>
                        <span class="text-secondary small fw-bold d-block">HORA Y FECHA</span>
                        <span class="fw-bold text-dark fs-6" id="mDetalleFechaHora">-</span>
                    </div>
                    <div>
                        <span class="text-secondary small fw-bold d-block">ESTADO OPERATIVO</span>
                        <span id="mDetalleEstadoBadge" class="badge bg-success fs-6 px-3 py-1">En Operación</span>
                    </div>
                    <div>
                        <span class="text-secondary small fw-bold d-block">OPERADOR RESPONSABLE</span>
                        <span class="badge bg-light text-dark border px-3 py-1 fw-bold fs-6" id="mDetalleOperador">-</span>
                    </div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-primary border-4">
                            <span class="text-muted small fw-bold text-uppercase">Presión Entrada</span>
                            <div class="d-flex align-items-baseline gap-1 mt-2">
                                <span class="fs-3 fw-bold text-dark font-monospace" id="mDetallePin">-</span>
                                <span class="text-muted small fw-bold">bar</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-primary border-4">
                            <span class="text-muted small fw-bold text-uppercase">Presión Salida</span>
                            <div class="d-flex align-items-baseline gap-1 mt-2">
                                <span class="fs-3 fw-bold text-dark font-monospace" id="mDetallePout">-</span>
                                <span class="text-muted small fw-bold">bar</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-danger border-4">
                            <span class="text-muted small fw-bold text-uppercase">Delta de Presión</span>
                            <div class="d-flex align-items-baseline gap-1 mt-2">
                                <span class="fs-3 fw-bold font-monospace" id="mDetalleDelta">-</span>
                                <span class="text-muted small fw-bold">bar</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-warning border-4">
                            <span class="text-muted small fw-bold text-uppercase">Brix Entrada</span>
                            <div class="d-flex align-items-baseline gap-1 mt-2">
                                <span class="fs-3 fw-bold text-dark font-monospace" id="mDetalleBin">-</span>
                                <span class="text-muted small fw-bold">°Bx</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-warning border-4">
                            <span class="text-muted small fw-bold text-uppercase">Brix Salida</span>
                            <div class="d-flex align-items-baseline gap-1 mt-2">
                                <span class="fs-3 fw-bold text-dark font-monospace" id="mDetalleBout">-</span>
                                <span class="text-muted small fw-bold">°Bx</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-0 shadow-sm rounded-3 h-100 p-3 bg-white border-start border-info border-4">
                            <span class="text-muted small fw-bold text-uppercase">Brix Permeado</span>
                            <div class="d-flex align-items-baseline gap-1 mt-2">
                                <span class="fs-3 fw-bold text-dark font-monospace" id="mDetalleBperm">-</span>
                                <span class="text-muted small fw-bold">°Bx</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-2">
                    <span class="text-muted small fw-bold text-uppercase mb-1"><i class="bi bi-chat-left-text me-1"></i> Observaciones</span>
                    <p class="m-0 text-dark small" id="mDetalleObservaciones">-</p>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2 px-4 justify-content-between">
                <a id="mDetalleLinkHistorial" href="historial.php?id=100" class="btn btn-outline-secondary btn-sm fw-bold rounded-pill px-3">
                    <i class="bi bi-clock-history me-1"></i> Ver en Historial Completo
                </a>
                <button type="button" class="btn btn-secondary btn-sm fw-bold rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
function cambiarTurno(turno) {
    document.getElementById('inputTurno').value = turno;
    document.getElementById('formFiltroGeneral').submit();
}

function cambiarIntervalo(intervalo) {
    document.getElementById('inputIntervalo').value = intervalo;
    document.getElementById('formFiltroGeneral').submit();
}

let modalGeneralBS = null;
function abrirModalDetalleGral(paramNombre, valor, badgeClass, fechaHora, nomina, lote, obs, rangoMeta) {
    document.getElementById('modalGeneralParamNombre').textContent = paramNombre;
    const badge = document.getElementById('modalGeneralValorBadge');
    badge.textContent = valor;
    badge.className = 'modal-badge-grande shadow-sm ' + badgeClass;
    
    document.getElementById('modalGeneralRangoMeta').textContent = rangoMeta || '-';
    document.getElementById('modalGeneralFechaHora').textContent = fechaHora || '-';
    document.getElementById('modalGeneralNomina').textContent = nomina || 'N/A';
    document.getElementById('modalGeneralLote').textContent = lote || '-';
    document.getElementById('modalGeneralObservaciones').textContent = obs || 'Sin observaciones registradas.';

    if (!modalGeneralBS) {
        modalGeneralBS = new bootstrap.Modal(document.getElementById('modalDetalleGeneral'));
    }
    modalGeneralBS.show();
}

let modalMembBS = null;
function abrirModalDetalleMemb(reg) {
    if (!reg) return;
    const modalEl = document.getElementById('modalDetalleMembrana');
    if (!modalEl) return;

    document.getElementById('mDetalleMembTitulo').innerHTML = `<i class="bi bi-funnel-fill me-2"></i> Paquete ${reg.paquete_numero} · Etapa ${reg.etapa || ''}`;
    document.getElementById('mDetalleFechaHora').textContent = `${reg.fecha} ${reg.hora || ''}`;

    const est = (reg.estado || 'OPERACION').toUpperCase();
    const badgeEst = document.getElementById('mDetalleEstadoBadge');
    if (est === 'LAVADO') {
        badgeEst.className = 'badge bg-info text-dark fs-6 px-3 py-1';
        badgeEst.textContent = 'Lavado CIP';
    } else if (est === 'MMT' || est === 'MMTT') {
        badgeEst.className = 'badge bg-warning text-dark fs-6 px-3 py-1';
        badgeEst.textContent = 'Mantenimiento (MMTT)';
    } else if (est === 'FO' || est === 'F.O.') {
        badgeEst.className = 'badge bg-danger text-white fs-6 px-3 py-1';
        badgeEst.textContent = 'Fuera de Operación (F.O.)';
    } else {
        badgeEst.className = 'badge bg-success fs-6 px-3 py-1';
        badgeEst.textContent = 'En Operación';
    }

    document.getElementById('mDetalleOperador').textContent = `Nómina: ${reg.operador_nomina || 'N/A'}`;
    document.getElementById('mDetallePin').textContent = (reg.presion_entrada !== null && reg.presion_entrada !== '') ? parseFloat(reg.presion_entrada).toFixed(2) : '-';
    document.getElementById('mDetallePout').textContent = (reg.presion_salida !== null && reg.presion_salida !== '') ? parseFloat(reg.presion_salida).toFixed(2) : '-';

    const deltaEl = document.getElementById('mDetalleDelta');
    if (reg.delta_presion !== null && reg.delta_presion !== '') {
        const dVal = parseFloat(reg.delta_presion);
        deltaEl.textContent = dVal.toFixed(2);
        if (dVal >= 1.8) deltaEl.className = 'fs-3 fw-bold font-monospace text-danger';
        else if (dVal >= 1.2) deltaEl.className = 'fs-3 fw-bold font-monospace text-warning';
        else deltaEl.className = 'fs-3 fw-bold font-monospace text-success';
    } else {
        deltaEl.textContent = '-';
        deltaEl.className = 'fs-3 fw-bold font-monospace text-muted';
    }

    document.getElementById('mDetalleBin').textContent = (reg.brix_entrada !== null && reg.brix_entrada !== '') ? parseFloat(reg.brix_entrada).toFixed(1) : '-';
    document.getElementById('mDetalleBout').textContent = (reg.brix_salida !== null && reg.brix_salida !== '') ? parseFloat(reg.brix_salida).toFixed(1) : '-';
    document.getElementById('mDetalleBperm').textContent = (reg.brix_permeado !== null && reg.brix_permeado !== '') ? parseFloat(reg.brix_permeado).toFixed(1) : '-';
    document.getElementById('mDetalleObservaciones').textContent = reg.observaciones && reg.observaciones.trim() ? reg.observaciones : 'Sin observaciones registradas.';

    const linkHist = document.getElementById('mDetalleLinkHistorial');
    if (linkHist) {
        linkHist.href = `historial.php?id=100&fecha=${encodeURIComponent(reg.fecha)}&etapa=${reg.etapa || 0}`;
    }

    if (!modalMembBS) {
        modalMembBS = new bootstrap.Modal(modalEl);
    }
    modalMembBS.show();
}

// Filtros de UI
const zonaBloqueadaId = <?= (int)$zona_filtro ?>;
let zonaSeleccionadaId = <?= (int)$zona_filtro ?>;
let grupoSeleccionado = 'todos';

function seleccionarZona(zonaId, btn) {
    if (zonaBloqueadaId > 0 && zonaId !== zonaBloqueadaId) {
        return; // Vista bloqueada a la zona de origen
    }
    zonaSeleccionadaId = zonaId;
    grupoSeleccionado = 'todos';
    const inputZona = document.getElementById('inputZona');
    if (inputZona) inputZona.value = zonaId;

    document.querySelectorAll('.btn-filter[onclick*="seleccionarZona"]').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    document.querySelectorAll('.btn-filter.green').forEach(b => b.classList.remove('active'));
    document.querySelector('.btn-filter.green[onclick*="todos"]')?.classList.add('active');

    // Si se seleccionó una zona específica, abrir su acordeón
    if (zonaId > 0) {
        const coll = document.getElementById('collapseZona' + zonaId);
        if (coll) {
            coll.classList.add('show');
            const btnColl = document.querySelector(`[data-bs-target="#collapseZona${zonaId}"]`);
            if (btnColl) {
                btnColl.classList.remove('collapsed');
                btnColl.setAttribute('aria-expanded', 'true');
            }
        }
    } else {
        // Al seleccionar todas, mostrar todos los acordeones
        document.querySelectorAll('.bloque-zona').forEach(bz => bz.style.display = 'block');
    }

    aplicarFiltrosMatriz();
}

function seleccionarGrupo(grupo, btn) {
    grupoSeleccionado = grupo;
    document.querySelectorAll('.btn-filter.green').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    aplicarFiltrosMatriz();
}

function aplicarFiltrosMatriz() {
    document.querySelectorAll('.tarjeta-equipo').forEach(tarjeta => {
        const zId = parseInt(tarjeta.dataset.zonaId || 0);
        const nombre = tarjeta.dataset.equipoNombre || '';
        const zonaOk = (zonaSeleccionadaId === 0 || zId === zonaSeleccionadaId);
        const grupoOk = (grupoSeleccionado === 'todos' || nombre.includes(grupoSeleccionado));
        tarjeta.style.display = (zonaOk && grupoOk) ? 'block' : 'none';
    });
    actualizarVisibilidadSecciones();
}

function actualizarVisibilidadSecciones() {
    document.querySelectorAll('.bloque-area').forEach(area => {
        const visibles = Array.from(area.querySelectorAll('.tarjeta-equipo')).filter(e => e.style.display !== 'none').length;
        area.style.display = (visibles > 0) ? 'block' : 'none';
    });
    document.querySelectorAll('.bloque-zona').forEach(zona => {
        const zId = parseInt(zona.dataset.zonaId || 0);
        if (zonaSeleccionadaId > 0 && zId !== zonaSeleccionadaId) {
            zona.style.display = 'none';
        } else {
            const visibles = Array.from(zona.querySelectorAll('.bloque-area')).filter(a => a.style.display !== 'none').length;
            zona.style.display = (visibles > 0 || zonaSeleccionadaId === 0) ? 'block' : 'none';
        }
    });
    const totalVisibles = document.querySelectorAll('.tarjeta-equipo:not([style*="display: none"])').length;
    const contadorEl = document.getElementById('contador-visibles');
    if (contadorEl) contadorEl.textContent = totalVisibles;
}

function filtrarEnVivo() {
    const texto = document.getElementById('buscadorVivo').value.toLowerCase().trim();
    document.querySelectorAll('.tarjeta-equipo').forEach(tarjeta => {
        const nombre = tarjeta.dataset.equipoNombre || '';
        let coincide = (texto === '' || nombre.includes(texto));
        tarjeta.querySelectorAll('.fila-parametro').forEach(p => {
            const pNombre = p.dataset.paramNombre || '';
            if (texto !== '' && pNombre.includes(texto)) coincide = true;
        });
        tarjeta.style.display = coincide ? 'block' : 'none';
    });
    actualizarVisibilidadSecciones();
}

function limpiarBuscador() {
    document.getElementById('buscadorVivo').value = '';
    if (zonaBloqueadaId > 0) {
        seleccionarGrupo('todos', document.querySelector('.btn-filter.green[onclick*="todos"]'));
    } else {
        seleccionarZona(0, document.querySelector('.btn-filter[onclick*="seleccionarZona(0"]'));
    }
}

function toggleDesplegarObservaciones(id) {
    const el = document.getElementById(id);
    if (!el) return;
    if (el.style.display === 'none' || el.style.display === '') {
        el.style.display = 'block';
    } else {
        el.style.display = 'none';
    }
}

window.addEventListener('load', function() {
    aplicarFiltrosMatriz();
});
</script>

<?php include 'includes/footer.php'; ?>
