<?php
// progel_core/historial.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('America/Mexico_City');

// Expiración automática de sesión por inactividad DESACTIVADA temporalmente a petición
if (!isset($_SESSION['nomina'])) {
    header("Location: login.php");
    exit();
}
/*
$tiempo_inactividad_max = 300;
if (isset($_SESSION['ultima_actividad']) && (time() - $_SESSION['ultima_actividad']) >= $tiempo_inactividad_max) {
    session_unset();
    session_destroy();
    setcookie('progel_nomina_activa', '', time() - 3600, '/');
    setcookie('nomina', '', time() - 3600, '/');
    header("Location: login.php?timeout=1");
    exit();
}
*/
$_SESSION['ultima_actividad'] = time();

include_once 'config/db.php';
include_once 'config/db_v2.php';
include_once 'includes/mapeo_v2.php';
include_once 'includes/rangos_historicos.php';

$historial_rangos = cargarHistorialRangos($conn);

// Recibimos qué equipo queremos revisar
$equipo_id = (int)($_GET['id'] ?? 0);

// Bloquear/redirigir el acceso al registro antiguo obsoleto de Votators (IDs 8, 83, 84..89)
if (in_array($equipo_id, [8, 83, 84, 85, 86, 87, 88, 89])) {
    header("Location: http://192.168.1.105/sis_extraccion/index.php?view=votators");
    exit();
}

// Buscamos información detallada del equipo (nombre, área, zona)
$q_equipo = mysqli_query($conn, "SELECT e.*, a.nombre as area_nombre, a.zona_id, z.nombre as zona_nombre 
                                  FROM equipos e 
                                  LEFT JOIN areas a ON e.area_id = a.id 
                                  LEFT JOIN zonas z ON a.zona_id = z.id 
                                  WHERE e.id = $equipo_id");
$equipo_info = mysqli_fetch_assoc($q_equipo);
$equipo_nombre = $equipo_info['nombre'] ?? 'Equipo Desconocido';
$area_nombre = $equipo_info['area_nombre'] ?? '';
$zona_nombre = $equipo_info['zona_nombre'] ?? '';
$zona_id = (int)($equipo_info['zona_id'] ?? 0);

// =========================================================================
// DEFINICIÓN DE FECHAS Y TURNOS
// Diurno:   07:00 AM a 07:00 PM (07:00:00 a 18:59:59)
// Nocturno: 07:00 PM de fecha a 07:00 AM del día siguiente (19:00:00 a 06:59:59)
// =========================================================================
$hora_actual_num = (int)date('G');
if ($hora_actual_num >= 7 && $hora_actual_num < 19) {
    $turno_actual_sugerido = 'diurno';
    $fecha_sugerida = date('Y-m-d');
} else {
    $turno_actual_sugerido = 'nocturno';
    // Si es de madrugada (00:00 a 06:59), el turno nocturno pertenece a la fecha de ayer
    $fecha_sugerida = ($hora_actual_num < 7) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
}

$rol_actual_hist = strtoupper(trim($_SESSION['rol'] ?? 'OPERADOR'));
$es_operador_hist = ($rol_actual_hist === 'OPERADOR');

if ($es_operador_hist) {
    $fecha_filtro = $fecha_sugerida;
} else {
    $fecha_filtro = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : $fecha_sugerida;
}
$turno_filtro = isset($_GET['turno']) ? strtolower(trim($_GET['turno'])) : 'ambos';
if (!in_array($turno_filtro, ['diurno', 'nocturno', 'ambos'])) {
    $turno_filtro = 'ambos';
}

$es_membrana = ($equipo_id == 100);
$es_chiller_hist = in_array($equipo_id, [91, 92]);

// Vista para Chillers: 'diario', 'semanal' o 'ambos' (por defecto 'ambos' para ver Diario y Semanal juntos)
$vista_chiller = isset($_GET['vista_chiller']) ? strtolower(trim($_GET['vista_chiller'])) : 'ambos';
if (!in_array($vista_chiller, ['diario', 'semanal', 'ambos'])) {
    $vista_chiller = 'ambos';
}

// Frecuencia / Intervalo de columnas (1h = cada hora, 2h = cada 2 horas, auto = horas con datos)
$intervalo_filtro = isset($_GET['intervalo']) ? strtolower(trim($_GET['intervalo'])) : ($es_membrana ? '1h' : '2h');
if (!in_array($intervalo_filtro, ['1h', '2h', 'auto'])) {
    $intervalo_filtro = $es_membrana ? '1h' : '2h';
}

$fecha_hoy = date('Y-m-d');
$fecha_anterior = date('Y-m-d', strtotime($fecha_filtro . ' -1 day'));
$fecha_siguiente = date('Y-m-d', strtotime($fecha_filtro . ' +1 day'));

// Cálculo de semana operativa (Lunes a Domingo) para historial semanal de Chillers
$ts_fecha_filtro = strtotime($fecha_filtro);
$dia_sem_num = (int)date('N', $ts_fecha_filtro); // 1 (Lunes) a 7 (Domingo)
$lunes_sem = date('Y-m-d', strtotime('-' . ($dia_sem_num - 1) . ' days', $ts_fecha_filtro));
$domingo_sem = date('Y-m-d', strtotime('+' . (7 - $dia_sem_num) . ' days', $ts_fecha_filtro));
$semana_anterior = date('Y-m-d', strtotime($fecha_filtro . ' -7 days'));
$semana_siguiente = date('Y-m-d', strtotime($fecha_filtro . ' +7 days'));

$nombres_dias_es = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    7 => 'Domingo'
];
$dias_semana_chiller = [];
for ($d_i = 0; $d_i < 7; $d_i++) {
    $f_dia = date('Y-m-d', strtotime($lunes_sem . " +$d_i days"));
    $dias_semana_chiller[$f_dia] = [
        'num' => $d_i + 1,
        'nombre' => $nombres_dias_es[$d_i + 1],
        'fecha' => $f_dia,
        'corto' => date('d/m', strtotime($f_dia))
    ];
}

// =========================================================================
// Función para mapear un timestamp exacto a su turno y columna de hora
// =========================================================================
if (!function_exists('mapearBloqueTurno')) {
    function mapearBloqueTurno($fecha_reg_str, $fecha_base, $intervalo = '1h') {
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
                    // Modo 2 horas
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
                    // Modo 2 horas
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
                    // Modo 2 horas
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
        if (isset($p['equipo_id']) && (int)$p['equipo_id'] === 100) {
            return [
                'verde' => 'Por definir',
                'amarillo' => 'Por definir',
                'rojo' => 'Por definir'
            ];
        }
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

// Función para obtener clase semáforo horizontal
if (!function_exists('getSemaforoHorizontal')) {
    function getSemaforoHorizontal($valor, $p_info) {
        if ($valor === null || $valor === '' || $valor === '-') return '';
        $lower_val = strtolower(trim($valor));
        
        // Estados operativos estándar
        if (in_array($lower_val, ['lavado', 'lavado cip'])) return 'badge-lavado text-dark';
        if (in_array($lower_val, ['mmtt', 'mmt', 'mtto', 'mantenimiento'])) return 'bg-warning text-dark';
        if (in_array($lower_val, ['f.o.', 'fo', 'f/o', 'fuera de operación', 'fuera de operacion', 'no realizado', 'no cumple', 'fideo plastoso', 'sí presenta', 'si presenta', 'quemada', 'húmeda', 'humeda'])) return 'bg-danger text-white';
        if (in_array($lower_val, ['deformación en el fideo', 'deformación', 'deformacion', 'pendiente', 'sobresecada', 'plástica', 'plastica'])) return 'bg-warning text-dark';
        if (in_array($lower_val, ['sí', 'si', 'realizado', 'cumple', 'fideo continuo', 'firme', 'no presenta', 'en operación', 'en operacion'])) return 'bg-success text-white';
        
        // Comparación numérica contra rangos
        if (isset($p_info['tipo_dato']) && $p_info['tipo_dato'] == 'NUMERICO' && is_numeric($valor)) {
            $v = floatval($valor);
            $rb = isset($p_info['rojo_bajo']) && $p_info['rojo_bajo'] !== '' && $p_info['rojo_bajo'] !== null ? floatval($p_info['rojo_bajo']) : -999999;
            $ra = isset($p_info['rojo_alto']) && $p_info['rojo_alto'] !== '' && $p_info['rojo_alto'] !== null ? floatval($p_info['rojo_alto']) : 999999;
            $ab = isset($p_info['amarillo_bajo']) && $p_info['amarillo_bajo'] !== '' && $p_info['amarillo_bajo'] !== null ? floatval($p_info['amarillo_bajo']) : -999999;
            $aa = isset($p_info['amarillo_alto']) && $p_info['amarillo_alto'] !== '' && $p_info['amarillo_alto'] !== null ? floatval($p_info['amarillo_alto']) : 999999;
            
            if ($v <= $rb || $v >= $ra) return 'bg-danger text-white';
            if (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) return 'bg-warning text-dark';
            return 'bg-success text-white';
        }
        
        return 'bg-secondary text-white';
    }
}

// =========================================================================
// 1. CASO ESPECIAL: MEMBRANAS (ID 100)
// =========================================================================
$lista_membranas = [];
$matriz_membranas = [
    'diurno' => [
        'paquetes' => [],      // [paq_num][bloque] = $rm
        'horas_exactas' => [], // [bloque] = '09:15:30'
        'brix_entrada' => [],  // [bloque] = val
        'brix_salida' => [],   // [bloque] = val
        'brix_permeado' => [], // [bloque] = val
        'meta' => []           // [bloque] = ['operador' => ..., 'obs' => ..., 'fecha_exacta' => ...]
    ],
    'nocturno' => [
        'paquetes' => [],
        'horas_exactas' => [],
        'brix_entrada' => [],
        'brix_salida' => [],
        'brix_permeado' => [],
        'meta' => []
    ]
];

$conteo_lecturas_turno = ['diurno' => 0, 'nocturno' => 0];
$horas_detectadas_turno = ['diurno' => [], 'nocturno' => []];

$filtro_etapa = isset($_GET['etapa']) ? intval($_GET['etapa']) : 0;
$filtro_paquete = isset($_GET['paquete']) ? intval($_GET['paquete']) : 0;

if ($es_membrana) {
    $cMemb = (isset($conn_v2) && $conn_v2) ? $conn_v2 : $conn;
    
    // Filtro de fecha y turno para Membranas
    if ($turno_filtro === 'diurno') {
        $whereMemb = "WHERE fecha_registro >= '$fecha_filtro 07:00:00' AND fecha_registro <= '$fecha_filtro 18:59:59'";
    } elseif ($turno_filtro === 'nocturno') {
        $whereMemb = "WHERE fecha_registro >= '$fecha_filtro 19:00:00' AND fecha_registro <= '$fecha_siguiente 06:59:59'";
    } else {
        // Ambos turnos del ciclo de producción (24 horas continuas)
        $whereMemb = "WHERE fecha_registro >= '$fecha_filtro 07:00:00' AND fecha_registro <= '$fecha_siguiente 06:59:59'";
    }
    if ($filtro_etapa > 0) {
        $whereMemb .= " AND etapa = $filtro_etapa";
    }
    if ($filtro_paquete > 0) {
        $whereMemb .= " AND paquete_numero = $filtro_paquete";
    }
    
    $qM = mysqli_query($cMemb, "SELECT * FROM bitacora_membranas $whereMemb ORDER BY fecha_registro ASC, paquete_numero ASC");
    if ($qM && mysqli_num_rows($qM) > 0) {
        while ($rm = mysqli_fetch_assoc($qM)) {
            $lista_membranas[] = $rm;
        }
    } else {
        // Fallback por fecha simple si aún no hay registros con rango completo
        $extraWhere = ($filtro_etapa > 0 ? " AND etapa = $filtro_etapa" : "") . ($filtro_paquete > 0 ? " AND paquete_numero = $filtro_paquete" : "");
        $fallback = mysqli_query($cMemb, "SELECT * FROM bitacora_membranas WHERE fecha IN ('$fecha_filtro', '$fecha_siguiente') $extraWhere ORDER BY hora ASC, paquete_numero ASC");
        if ($fallback) {
            while ($rm = mysqli_fetch_assoc($fallback)) {
                $lista_membranas[] = $rm;
            }
        }
    }

    // Organizar en matriz por horas y paquetes
    foreach ($lista_membranas as $rm) {
        $hora_op = !empty($rm['hora']) ? (strlen($rm['hora']) === 5 ? $rm['hora'] . ':00' : $rm['hora']) : '';
        $fOp = (!empty($rm['fecha']) && !empty($hora_op)) ? ($rm['fecha'] . ' ' . $hora_op) : ($rm['fecha_registro'] ?? '');
        $fReg = !empty($rm['fecha_registro']) ? $rm['fecha_registro'] : $fOp;
        $map = mapearBloqueTurno($fReg, $fecha_filtro, $intervalo_filtro);
        if (!$map && !empty($fOp)) {
            $map = mapearBloqueTurno($fOp, $fecha_filtro, $intervalo_filtro);
        }
        if (!$map) continue;
        $t = $map['turno'];
        $b = $map['bloque'];
        $paq = (int)$rm['paquete_numero'];

        $matriz_membranas[$t]['paquetes'][$paq][$b] = $rm;
        $conteo_lecturas_turno[$t]++;
        $horas_detectadas_turno[$t][$b] = true;

        if (empty($matriz_membranas[$t]['horas_exactas'][$b])) {
            $matriz_membranas[$t]['horas_exactas'][$b] = date('H:i:s', strtotime($fReg));
        }

        if ($rm['brix_entrada'] !== null && $rm['brix_entrada'] !== '') {
            $matriz_membranas[$t]['brix_entrada'][$b] = $rm['brix_entrada'];
        }
        if ($rm['brix_salida'] !== null && $rm['brix_salida'] !== '') {
            $matriz_membranas[$t]['brix_salida'][$b] = $rm['brix_salida'];
        }
        if ($rm['brix_permeado'] !== null && $rm['brix_permeado'] !== '') {
            $matriz_membranas[$t]['brix_permeado'][$b] = $rm['brix_permeado'];
        }

        if (empty($matriz_membranas[$t]['meta'][$b]['operador']) && !empty($rm['operador_nomina'])) {
            $matriz_membranas[$t]['meta'][$b]['operador'] = $rm['operador_nomina'];
        }
        if (empty($matriz_membranas[$t]['meta'][$b]['obs']) && !empty($rm['observaciones'])) {
            $matriz_membranas[$t]['meta'][$b]['obs'] = $rm['observaciones'];
        }
        $matriz_membranas[$t]['meta'][$b]['fecha_exacta'] = $fReg;
    }
}

// =========================================================================
// 2. CASO GENERAL: EQUIPOS NORMALES
// =========================================================================
$params_lista = [];
$map_param_to_v2_col = [];

if (!$es_membrana) {
    $p_res = mysqli_query($conn, "SELECT * FROM parametros WHERE equipo_id = $equipo_id ORDER BY id ASC");
    if ($p_res) {
        while ($p = mysqli_fetch_assoc($p_res)) {
            $pid = (int)$p['id'];
            $params_lista[$pid] = $p;
            if (isset($GLOBALS['MAP_PARAMETROS_V2'][$pid])) {
                $map_param_to_v2_col[$pid] = $GLOBALS['MAP_PARAMETROS_V2'][$pid][1];
            }
        }
    }
    // Ordenar parámetros de Concentradores (14..18) igual que en sis_extraccion
    if (isset($GLOBALS['MAP_CONCENTRADORES_SIS_EXTRACCION'][$equipo_id]['orden_params'])) {
        $params_ordenados = [];
        foreach ($GLOBALS['MAP_CONCENTRADORES_SIS_EXTRACCION'][$equipo_id]['orden_params'] as $pid_ord) {
            if (isset($params_lista[$pid_ord])) {
                $params_ordenados[$pid_ord] = $params_lista[$pid_ord];
            }
        }
        foreach ($params_lista as $pid_rest => $p_rest) {
            if (!isset($params_ordenados[$pid_rest])) {
                $params_ordenados[$pid_rest] = $p_rest;
            }
        }
        $params_lista = $params_ordenados;
    }
}

// Inicializamos la matriz de turnos para equipos normales
$matriz = [
    'diurno' => [
        'valores' => [],      // [param_id][bloque] = [val, fecha_exacta, operador, lote, obs]
        'meta' => []          // [bloque] = [operador, lote, obs, fecha_exacta]
    ],
    'nocturno' => [
        'valores' => [],
        'meta' => []
    ]
];

if (!$es_membrana && !$es_chiller_hist) {
    // A) Carga desde BD tradicional: bitacora_lecturas
    $sql_v1 = "SELECT b.parametro_id, b.valor_capturado, b.fecha_registro, b.numero_nomina, b.lote, b.observaciones 
               FROM bitacora_lecturas b
               WHERE b.equipo_id = $equipo_id 
                 AND b.fecha_registro >= '$fecha_filtro 07:00:00' 
                 AND b.fecha_registro <= '$fecha_siguiente 06:59:59' 
               ORDER BY b.fecha_registro ASC";
    $res_v1 = mysqli_query($conn, $sql_v1);
    if ($res_v1) {
        while ($r = mysqli_fetch_assoc($res_v1)) {
            $map = mapearBloqueTurno($r['fecha_registro'], $fecha_filtro, $intervalo_filtro);
            if (!$map) continue;
            $t = $map['turno'];
            $b = $map['bloque'];
            $pid = (int)$r['parametro_id'];

            $matriz[$t]['valores'][$pid][$b] = [
                'val' => $r['valor_capturado'],
                'fecha_exacta' => $r['fecha_registro'],
                'operador' => $r['numero_nomina'] ?? '',
                'lote' => $r['lote'] ?? '',
                'obs' => $r['observaciones'] ?? ''
            ];

            $matriz[$t]['meta'][$b] = [
                'operador' => $r['numero_nomina'] ?? '',
                'lote' => $r['lote'] ?? '',
                'obs' => $r['observaciones'] ?? '',
                'fecha_exacta' => $r['fecha_registro']
            ];

            $conteo_lecturas_turno[$t]++;
            $horas_detectadas_turno[$t][$b] = true;
        }
    }

    // B) Integración con Progel_coreV2 (Secadores, Concentradores, Chillers Votator, Compresores)
    $tabla_v2 = '';
    $campo_equipo_v2 = '';
    $eq_nombre_v2 = $GLOBALS['NOMBRES_EQUIPOS_V2'][$equipo_id] ?? '';

    if (in_array($equipo_id, [21, 22, 23, 24])) {
        $tabla_v2 = 'bitacora_secadores';
        $campo_equipo_v2 = 'secador_nombre';
    } elseif (in_array($equipo_id, [14, 15, 16, 17, 18])) {
        $tabla_v2 = 'bitacora_concentradores';
        $campo_equipo_v2 = 'concentrador_nombre';
    } elseif (in_array($equipo_id, [81, 82, 83, 84, 85, 86, 87, 88, 89])) {
        $tabla_v2 = 'bitacora_chillers_votator';
        $campo_equipo_v2 = 'equipo_nombre';
    } elseif (in_array($equipo_id, [12, 13])) {
        $tabla_v2 = 'bitacora_compresores';
        $campo_equipo_v2 = 'compresor_nombre';
    }

    if ($tabla_v2 !== '' && isset($conn_v2) && $conn_v2 && !empty($eq_nombre_v2)) {
        $esc_nombre = mysqli_real_escape_string($conn_v2, $eq_nombre_v2);
        $sql_v2 = "SELECT * FROM `$tabla_v2` 
                   WHERE `$campo_equipo_v2` = '$esc_nombre' 
                     AND fecha_registro >= '$fecha_filtro 07:00:00' 
                     AND fecha_registro <= '$fecha_siguiente 06:59:59' 
                   ORDER BY fecha_registro ASC";
        $res_v2 = mysqli_query($conn_v2, $sql_v2);
        if ($res_v2) {
            while ($row = mysqli_fetch_assoc($res_v2)) {
                $f_reg = $row['fecha_registro'] ?? ($row['fecha'] . ' ' . sprintf('%02d:00:00', $row['hora'] ?? 0));
                $map = mapearBloqueTurno($f_reg, $fecha_filtro, $intervalo_filtro);
                if (!$map) continue;
                $t = $map['turno'];
                $b = $map['bloque'];

                foreach ($map_param_to_v2_col as $pid => $col) {
                    if (isset($row[$col]) && $row[$col] !== null && $row[$col] !== '') {
                        $matriz[$t]['valores'][$pid][$b] = [
                            'val' => $row[$col],
                            'fecha_exacta' => $f_reg,
                            'operador' => $row['operador_nomina'] ?? '',
                            'lote' => $row['lote'] ?? '',
                            'obs' => $row['observaciones'] ?? ''
                        ];
                        $conteo_lecturas_turno[$t]++;
                        $horas_detectadas_turno[$t][$b] = true;
                    }
                }

                $matriz[$t]['meta'][$b] = [
                    'operador' => $row['operador_nomina'] ?? ($matriz[$t]['meta'][$b]['operador'] ?? ''),
                    'lote' => $row['lote'] ?? ($matriz[$t]['meta'][$b]['lote'] ?? ''),
                    'obs' => $row['observaciones'] ?? ($matriz[$t]['meta'][$b]['obs'] ?? ''),
                    'fecha_exacta' => $f_reg
                ];
            }
        }
    }
}

if ($es_chiller_hist) {
    // Carga Unificada Ultra-Rápida (una sola pasada semanal) exclusivamente para Chillers Normales (91 y 92)
    $params_diarios_chiller = [];
    $params_semanales_chiller = [];
    $capturas_dia_chiller = [
        'sesiones' => [],
        'valores'  => []
    ];
    $matriz_semanal_chiller = [
        'valores' => [],
        'meta'    => []
    ];
    $lecturas_rutinas_semanales = [];

    foreach ($params_lista as $pid => $p_item) {
        $freq_up = strtoupper(trim($p_item['frecuencia'] ?? 'DIARIO'));
        if ($freq_up === 'SEMANAL') {
            $params_semanales_chiller[$pid] = $p_item;
        } else {
            $params_diarios_chiller[$pid] = $p_item;
        }
    }

    // 1) Consulta única por rango indexado en bitacora_lecturas para toda la semana ($lunes_sem a $domingo_sem)
    $sql_sem_v1 = "SELECT b.parametro_id, b.valor_capturado, b.fecha_registro, b.numero_nomina, b.lote, b.observaciones 
                   FROM bitacora_lecturas b
                   WHERE b.equipo_id = $equipo_id 
                     AND b.fecha_registro >= '$lunes_sem 00:00:00' 
                     AND b.fecha_registro <= '$domingo_sem 23:59:59'
                   ORDER BY b.fecha_registro ASC";
    $res_sem_v1 = mysqli_query($conn, $sql_sem_v1);
    if ($res_sem_v1) {
        while ($r = mysqli_fetch_assoc($res_sem_v1)) {
            $pid = (int)$r['parametro_id'];
            $val_r = $r['valor_capturado'];
            if ($val_r === null || $val_r === '') continue;

            $f_reg = $r['fecha_registro'];
            $ts_r = strtotime($f_reg);
            $f_dia = date('Y-m-d', $ts_r);

            if (isset($params_semanales_chiller[$pid])) {
                $lecturas_rutinas_semanales[$pid] = [
                    'val' => $val_r,
                    'fecha_exacta' => $f_reg,
                    'operador' => $r['numero_nomina'] ?? '',
                    'lote' => $r['lote'] ?? '',
                    'obs' => $r['observaciones'] ?? ''
                ];
            } elseif (isset($params_diarios_chiller[$pid]) && isset($dias_semana_chiller[$f_dia])) {
                $hist_prev = $matriz_semanal_chiller['valores'][$pid][$f_dia]['historial_dia'] ?? [];
                $hist_prev[] = date('H:i', $ts_r) . ': ' . $val_r;
                $matriz_semanal_chiller['valores'][$pid][$f_dia] = [
                    'val' => $val_r,
                    'fecha_exacta' => $f_reg,
                    'operador' => $r['numero_nomina'] ?? '',
                    'lote' => $r['lote'] ?? '',
                    'obs' => $r['observaciones'] ?? '',
                    'total_capturas' => count($hist_prev),
                    'historial_dia' => $hist_prev
                ];
                $matriz_semanal_chiller['meta'][$f_dia] = [
                    'hora' => date('H:i:s', $ts_r),
                    'fecha_exacta' => $f_reg,
                    'operador' => $r['numero_nomina'] ?? ($matriz_semanal_chiller['meta'][$f_dia]['operador'] ?? ''),
                    'lote' => $r['lote'] ?? ($matriz_semanal_chiller['meta'][$f_dia]['lote'] ?? ''),
                    'obs' => !empty($r['observaciones']) ? $r['observaciones'] : ($matriz_semanal_chiller['meta'][$f_dia]['obs'] ?? ''),
                    'total_capturas' => max($matriz_semanal_chiller['meta'][$f_dia]['total_capturas'] ?? 0, count($hist_prev))
                ];

                if ($f_dia === $fecha_filtro) {
                    $ses_key = date('H:i', $ts_r);
                    $capturas_dia_chiller['sesiones'][$ses_key] = [
                        'hora' => date('H:i:s', $ts_r),
                        'fecha_exacta' => $f_reg,
                        'operador' => $r['numero_nomina'] ?? '',
                        'lote' => $r['lote'] ?? '',
                        'obs' => $r['observaciones'] ?? ''
                    ];
                    $capturas_dia_chiller['valores'][$pid][$ses_key] = [
                        'val' => $val_r,
                        'fecha_exacta' => $f_reg,
                        'operador' => $r['numero_nomina'] ?? '',
                        'lote' => $r['lote'] ?? '',
                        'obs' => $r['observaciones'] ?? ''
                    ];
                }
            }
        }
    }

    // 2) Consulta única en bitacora_chillers_normales para toda la semana ($lunes_sem a $domingo_sem)
    $eq_nombre_v2 = $GLOBALS['NOMBRES_EQUIPOS_V2'][$equipo_id] ?? '';
    if (isset($conn_v2) && $conn_v2 && !empty($eq_nombre_v2)) {
        $esc_nombre = mysqli_real_escape_string($conn_v2, $eq_nombre_v2);
        $sql_sem_v2 = "SELECT * FROM `bitacora_chillers_normales` 
                       WHERE `equipo_nombre` = '$esc_nombre' 
                         AND (
                             (fecha >= '$lunes_sem' AND fecha <= '$domingo_sem')
                             OR (fecha_registro >= '$lunes_sem 00:00:00' AND fecha_registro <= '$domingo_sem 23:59:59')
                         )
                       ORDER BY fecha_registro ASC";
        $res_sem_v2 = mysqli_query($conn_v2, $sql_sem_v2);
        if ($res_sem_v2) {
            while ($row = mysqli_fetch_assoc($res_sem_v2)) {
                $f_reg = $row['fecha_registro'] ?? ($row['fecha'] . ' ' . sprintf('%02d:00:00', $row['hora'] ?? 0));
                $ts_r = strtotime($f_reg);
                $f_dia = !empty($row['fecha']) ? $row['fecha'] : date('Y-m-d', $ts_r);

                // Evaluar parámetros semanales en la fila
                foreach ($params_semanales_chiller as $pid => $p_sem) {
                    $col_sem = $map_param_to_v2_col[$pid] ?? null;
                    if ($col_sem && isset($row[$col_sem]) && $row[$col_sem] !== null && $row[$col_sem] !== '') {
                        $lecturas_rutinas_semanales[$pid] = [
                            'val' => $row[$col_sem],
                            'fecha_exacta' => $f_reg,
                            'operador' => $row['operador_nomina'] ?? '',
                            'lote' => $row['lote'] ?? '',
                            'obs' => $row['observaciones'] ?? ''
                        ];
                    }
                }

                // Evaluar parámetros diarios en la fila
                if (isset($dias_semana_chiller[$f_dia])) {
                    $hubo_diario = false;
                    $max_cap_dia = $matriz_semanal_chiller['meta'][$f_dia]['total_capturas'] ?? 0;
                    $ses_key = date('H:i', $ts_r);

                    foreach ($params_diarios_chiller as $pid => $p_info) {
                        $col = $map_param_to_v2_col[$pid] ?? null;
                        if ($col && isset($row[$col]) && $row[$col] !== null && $row[$col] !== '') {
                            $hubo_diario = true;
                            $hist_prev = $matriz_semanal_chiller['valores'][$pid][$f_dia]['historial_dia'] ?? [];
                            $etiqueta_h = date('H:i', $ts_r) . ': ' . $row[$col];
                            if (!in_array($etiqueta_h, $hist_prev)) {
                                $hist_prev[] = $etiqueta_h;
                            }
                            $matriz_semanal_chiller['valores'][$pid][$f_dia] = [
                                'val' => $row[$col],
                                'fecha_exacta' => $f_reg,
                                'operador' => $row['operador_nomina'] ?? '',
                                'lote' => $row['lote'] ?? '',
                                'obs' => $row['observaciones'] ?? '',
                                'total_capturas' => count($hist_prev),
                                'historial_dia' => $hist_prev
                            ];
                            if (count($hist_prev) > $max_cap_dia) {
                                $max_cap_dia = count($hist_prev);
                            }

                            if ($f_dia === $fecha_filtro) {
                                $capturas_dia_chiller['valores'][$pid][$ses_key] = [
                                    'val' => $row[$col],
                                    'fecha_exacta' => $f_reg,
                                    'operador' => $row['operador_nomina'] ?? '',
                                    'lote' => $row['lote'] ?? '',
                                    'obs' => $row['observaciones'] ?? ''
                                ];
                            }
                        }
                    }

                    if ($hubo_diario) {
                        $matriz_semanal_chiller['meta'][$f_dia] = [
                            'hora' => date('H:i:s', $ts_r),
                            'fecha_exacta' => $f_reg,
                            'operador' => $row['operador_nomina'] ?? ($matriz_semanal_chiller['meta'][$f_dia]['operador'] ?? ''),
                            'lote' => $row['lote'] ?? ($matriz_semanal_chiller['meta'][$f_dia]['lote'] ?? ''),
                            'obs' => !empty($row['observaciones']) ? $row['observaciones'] : ($matriz_semanal_chiller['meta'][$f_dia]['obs'] ?? ''),
                            'total_capturas' => $max_cap_dia
                        ];

                        if ($f_dia === $fecha_filtro) {
                            $capturas_dia_chiller['sesiones'][$ses_key] = [
                                'hora' => date('H:i:s', $ts_r),
                                'fecha_exacta' => $f_reg,
                                'operador' => $row['operador_nomina'] ?? ($capturas_dia_chiller['sesiones'][$ses_key]['operador'] ?? ''),
                                'lote' => $row['lote'] ?? ($capturas_dia_chiller['sesiones'][$ses_key]['lote'] ?? ''),
                                'obs' => $row['observaciones'] ?? ($capturas_dia_chiller['sesiones'][$ses_key]['obs'] ?? '')
                            ];
                        }
                    }
                }
            }
        }
    }
}

if (!$es_membrana && !$es_chiller_hist) {

    // C) Integración directa con Sis_Extracción para Concentradores (14..18):
    //    1) progel_procesos.datos_concentradores + datos_hora
    //    2) bd_extraccion.datos_concentradores
    if (isset($GLOBALS['MAP_CONCENTRADORES_SIS_EXTRACCION'][$equipo_id])) {
        $cfg_conc = $GLOBALS['MAP_CONCENTRADORES_SIS_EXTRACCION'][$equipo_id];
        $ext_id   = (int)$cfg_conc['ext_id'];
        $p_tipo   = $cfg_conc['proc_tipo'];
        $cols_map = $cfg_conc['cols'];

        // C.1) progel_procesos.datos_concentradores
        if (isset($conn_procesos) && $conn_procesos) {
            $esc_tipo = mysqli_real_escape_string($conn_procesos, $p_tipo);
            $sql_proc_c = "SELECT d.*, dh.hora, dh.hora_exacta, dh.fecha_creacion, dh.nomina_captura
                           FROM datos_concentradores d
                           INNER JOIN datos_hora dh ON dh.id = d.id_datos_hora
                           WHERE d.tipo = '$esc_tipo'
                             AND ((dh.hora_exacta >= '$fecha_filtro 07:00:00' AND dh.hora_exacta <= '$fecha_siguiente 06:59:59')
                               OR (dh.fecha_creacion >= '$fecha_filtro 07:00:00' AND dh.fecha_creacion <= '$fecha_siguiente 06:59:59'))
                           ORDER BY d.id ASC";
            $res_proc_c = @mysqli_query($conn_procesos, $sql_proc_c);
            if ($res_proc_c) {
                while ($rp = mysqli_fetch_assoc($res_proc_c)) {
                    $f_ref = !empty($rp['hora_exacta']) ? $rp['hora_exacta'] : $rp['fecha_creacion'];
                    $map = mapearBloqueTurno($f_ref, $fecha_filtro, $intervalo_filtro);
                    if (!$map) continue;
                    $t = $map['turno'];
                    $b = $map['bloque'];
                    $es_fo = ((int)($rp['estado_fo'] ?? 0) === 1);
                    $nom_op = $rp['nomina_captura'] ?? '';
                    $obs_str = $es_fo ? 'Fuera de Operación (F.O.)' : "Bitácora #{$rp['id']}";

                    foreach ($cols_map as $pid => $par_cols) {
                        $col_proc = $par_cols[1];
                        if ($es_fo) {
                            $matriz[$t]['valores'][$pid][$b] = [
                                'val' => 'F.O.',
                                'fecha_exacta' => $f_ref,
                                'operador' => $nom_op,
                                'lote' => '',
                                'obs' => $obs_str
                            ];
                            $conteo_lecturas_turno[$t]++;
                            $horas_detectadas_turno[$t][$b] = true;
                        } elseif ($col_proc && isset($rp[$col_proc]) && $rp[$col_proc] !== null && $rp[$col_proc] !== '') {
                            $matriz[$t]['valores'][$pid][$b] = [
                                'val' => $rp[$col_proc],
                                'fecha_exacta' => $f_ref,
                                'operador' => $nom_op,
                                'lote' => '',
                                'obs' => $obs_str
                            ];
                            $conteo_lecturas_turno[$t]++;
                            $horas_detectadas_turno[$t][$b] = true;
                        }
                    }
                    $matriz[$t]['meta'][$b] = [
                        'operador' => $nom_op ?: ($matriz[$t]['meta'][$b]['operador'] ?? ''),
                        'lote' => $matriz[$t]['meta'][$b]['lote'] ?? '',
                        'obs' => $obs_str,
                        'fecha_exacta' => $f_ref
                    ];
                }
            }
        }

        // C.2) bd_extraccion.datos_concentradores (capturas directas desde sis_extraccion/index.php?view=concentradores)
        if (isset($conn_extraccion) && $conn_extraccion) {
            $sql_ext_c = "SELECT * FROM datos_concentradores
                          WHERE concentrador_id = $ext_id
                            AND fecha_registro >= '$fecha_filtro 07:00:00'
                            AND fecha_registro <= '$fecha_siguiente 06:59:59'
                          ORDER BY fecha_registro ASC";
            $res_ext_c = @mysqli_query($conn_extraccion, $sql_ext_c);
            if ($res_ext_c) {
                while ($re = mysqli_fetch_assoc($res_ext_c)) {
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
                    $nom_ext = $re['nomina'] ?? ($matriz[$t]['meta'][$b]['operador'] ?? '');

                    foreach ($cols_map as $pid => $par_cols) {
                        $col_ext = $par_cols[0];
                        if ($estado_txt !== null) {
                            $matriz[$t]['valores'][$pid][$b] = [
                                'val' => $estado_txt,
                                'fecha_exacta' => $f_reg,
                                'operador' => $nom_ext,
                                'lote' => '',
                                'obs' => $obs_ext
                            ];
                            $conteo_lecturas_turno[$t]++;
                            $horas_detectadas_turno[$t][$b] = true;
                        } elseif ($col_ext && isset($re[$col_ext]) && $re[$col_ext] !== null && $re[$col_ext] !== '') {
                            $matriz[$t]['valores'][$pid][$b] = [
                                'val' => $re[$col_ext],
                                'fecha_exacta' => $f_reg,
                                'operador' => $nom_ext,
                                'lote' => '',
                                'obs' => $obs_ext
                            ];
                            $conteo_lecturas_turno[$t]++;
                            $horas_detectadas_turno[$t][$b] = true;
                        }
                    }

                    $matriz[$t]['meta'][$b] = [
                        'operador' => $nom_ext,
                        'lote' => $matriz[$t]['meta'][$b]['lote'] ?? '',
                        'obs' => $obs_ext ?: ($matriz[$t]['meta'][$b]['obs'] ?? ''),
                        'fecha_exacta' => $f_reg
                    ];
                }
            }
        }
    }
}

// =========================================================================
// CONFIGURACIÓN DINÁMICA DE BLOQUES SEGÚN EL INTERVALO ELEGIDO
// =========================================================================
$generarBloquesTurno = function($t_key) use ($intervalo_filtro, $horas_detectadas_turno, $fecha_filtro, $fecha_siguiente) {
    $bloques = [];
    
    if ($intervalo_filtro === '1h') {
        if ($t_key === 'diurno') {
            for ($h = 7; $h <= 18; $h++) {
                $str = sprintf('%02d:00', $h);
                $bloques[$str] = ['label' => $str, 'sub' => date('d/m', strtotime($fecha_filtro))];
            }
        } else {
            // 19:00 a 23:00 hoy
            for ($h = 19; $h <= 23; $h++) {
                $str = sprintf('%02d:00', $h);
                $bloques[$str] = ['label' => $str, 'sub' => date('d/m', strtotime($fecha_filtro))];
            }
            // 00:00 a 06:00 mañana (+1)
            for ($h = 0; $h <= 6; $h++) {
                $str = sprintf('%02d:00', $h);
                $bloques[$str] = ['label' => $str, 'sub' => date('d/m', strtotime($fecha_siguiente)) . ' (+1)'];
            }
        }
    } elseif ($intervalo_filtro === '2h') {
        if ($t_key === 'diurno') {
            $pares = ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'];
            foreach ($pares as $p) {
                $bloques[$p] = ['label' => $p, 'sub' => 'Bloque 2h'];
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
    } else {
        // 'auto': Solo horas detectadas con datos
        $horas_keys = array_keys($horas_detectadas_turno[$t_key] ?? []);
        if ($t_key === 'nocturno') {
            $ordenNoche = ['19:00'=>1,'20:00'=>2,'21:00'=>3,'22:00'=>4,'23:00'=>5,'00:00'=>6,'01:00'=>7,'02:00'=>8,'03:00'=>9,'04:00'=>10,'05:00'=>11,'06:00'=>12];
            usort($horas_keys, function($a, $b) use ($ordenNoche) {
                return ($ordenNoche[$a] ?? 99) <=> ($ordenNoche[$b] ?? 99);
            });
        } else {
            sort($horas_keys);
        }

        if (empty($horas_keys)) {
            // Si no hay datos, mostrar al menos el rango estándar de horas
            $horas_keys = ($t_key === 'diurno') ? ['08:00', '10:00', '12:00', '14:00', '16:00', '18:00'] : ['20:00', '22:00', '00:00', '02:00', '04:00', '06:00'];
        }

        foreach ($horas_keys as $hk) {
            $hNum = (int)substr($hk, 0, 2);
            $sub = ($t_key === 'nocturno' && $hNum < 7) ? date('d/m', strtotime($fecha_siguiente)) . ' (+1)' : date('d/m', strtotime($fecha_filtro));
            $bloques[$hk] = ['label' => $hk, 'sub' => $sub];
        }
    }

    return $bloques;
};

// Configuración de los turnos
$turnos_config = [
    'diurno' => [
        'titulo' => 'Turno Diurno (07:00 AM - 07:00 PM)',
        'subtitulo' => 'Fecha: ' . date('d/m/Y', strtotime($fecha_filtro)),
        'icono' => 'bi-sun-fill text-warning',
        'badge_class' => 'bg-warning text-dark border',
        'card_header_bg' => '#fff8e1',
        'card_border' => '#ffe082',
        'bloques' => $generarBloquesTurno('diurno')
    ],
    'nocturno' => [
        'titulo' => 'Turno Nocturno (07:00 PM - 07:00 AM del día siguiente)',
        'subtitulo' => date('d/m/Y', strtotime($fecha_filtro)) . ' (19:00) a ' . date('d/m/Y', strtotime($fecha_siguiente)) . ' (07:00)',
        'icono' => 'bi-moon-stars-fill text-primary',
        'badge_class' => 'bg-primary text-white',
        'card_header_bg' => '#e8f0fe',
        'card_border' => '#b3d1ff',
        'bloques' => $generarBloquesTurno('nocturno')
    ]
];

// Qué turnos renderizar
$turnos_a_mostrar = [];
if ($turno_filtro === 'diurno') {
    $turnos_a_mostrar[] = 'diurno';
} elseif ($turno_filtro === 'nocturno') {
    $turnos_a_mostrar[] = 'nocturno';
} else {
    $turnos_a_mostrar = ['diurno', 'nocturno'];
}

// Estructura de Paquetes por Etapa para Membranas
$etapas_membranas_def = [
    1 => [
        'nombre' => 'ETAPA 1 — Filtración Primaria',
        'subtitulo' => 'Filtración Primaria',
        'color' => '#0d6efd',
        'badge' => 'primary',
        'paquetes' => [1, 2, 3, 4, 9]
    ],
    2 => [
        'nombre' => 'ETAPA 2 — Concentración Intermedia',
        'subtitulo' => 'Concentración Intermedia',
        'color' => '#198754',
        'badge' => 'success',
        'paquetes' => [5, 6, 7, 8]
    ],
    3 => [
        'nombre' => 'ETAPA 3 — Concentración Avanzada',
        'subtitulo' => 'Concentración Avanzada',
        'color' => '#fd7e14',
        'badge' => 'warning text-dark',
        'paquetes' => [10, 11]
    ],
    4 => [
        'nombre' => 'ETAPA 4 — Concentración Final / Pulido',
        'subtitulo' => 'Concentración Final / Pulido',
        'color' => '#6f42c1',
        'badge' => 'dark',
        'paquetes' => [12, 13]
    ]
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial por Turnos - <?= htmlspecialchars($equipo_nombre) ?></title>
    <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <style>
        :root {
            --primary-blue: #1e3a8a;
            --accent-green: #10b981;
            --accent-yellow: #f59e0b;
            --accent-red: #ef4444;
            --dark-navy: #0f172a;
            --bg-light: #f8fafc;
        }
        body { 
            background-color: var(--bg-light); 
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            color: #334155;
        }
        .header-box {
            background: #ffffff;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border-left: 5px solid #2563eb;
        }
        .filter-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            border: 1px solid #e2e8f0;
        }
        .shift-card {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .shift-header {
            padding: 0.9rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
        }
        .table-matriz-turno {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin: 0;
            font-size: 0.85rem;
        }
        .table-matriz-turno th, .table-matriz-turno td {
            padding: 0.45rem 0.4rem;
            text-align: center;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
            border-right: 1px solid #e2e8f0;
        }
        .table-matriz-turno thead th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.82rem;
            letter-spacing: 0.02em;
            position: sticky;
            top: 0;
            z-index: 5;
        }
        .table-matriz-turno thead th.th-param {
            position: sticky;
            left: 0;
            z-index: 6;
            background-color: #0f172a;
            text-align: left !important;
            padding: 6px 12px !important;
            min-width: 250px !important;
            width: 265px !important;
            max-width: 310px !important;
            overflow: hidden !important;
            text-overflow: ellipsis !important;
        }
        
        /* Columnas de Rangos Verde, Amarillo, Rojo (Amplias y sin recortes) */
        .th-rango-verde {
            background-color: #2e8b57 !important;
            border: 1px solid #257447 !important;
            color: #ffffff !important;
            min-width: 112px !important;
            width: 120px !important;
            max-width: 150px !important;
            padding: 6px 8px !important;
            text-align: center !important;
            font-size: 0.76rem !important;
            font-weight: 800 !important;
            letter-spacing: 0.2px !important;
        }
        .th-rango-amarillo {
            background-color: #facc15 !important;
            border: 1px solid #ca8a04 !important;
            color: #111827 !important;
            min-width: 112px !important;
            width: 120px !important;
            max-width: 150px !important;
            padding: 6px 8px !important;
            text-align: center !important;
            font-size: 0.76rem !important;
            font-weight: 800 !important;
            letter-spacing: 0.2px !important;
        }
        .th-rango-rojo {
            background-color: #c94436 !important;
            border: 1px solid #a9362c !important;
            color: #ffffff !important;
            min-width: 112px !important;
            width: 120px !important;
            max-width: 150px !important;
            padding: 6px 8px !important;
            text-align: center !important;
            font-size: 0.76rem !important;
            font-weight: 800 !important;
            letter-spacing: 0.2px !important;
        }
        .table-matriz-turno th, .table-matriz-turno td {
            border: 1px solid #475569 !important;
            padding: 6px 8px !important;
            text-align: center !important;
            vertical-align: middle !important;
            min-height: 34px !important;
            height: auto !important;
            white-space: nowrap !important;
        }
        .table-matriz-turno thead tr th {
            background-color: #1e293b !important;
            color: #ffffff !important;
            font-weight: 800 !important;
            font-size: 0.75rem !important;
            letter-spacing: 0.2px !important;
        }
        .table-matriz-turno thead th.th-hora {
            min-width: 68px;
            padding: 5px 6px !important;
        }

        /* Celdas del desglose de rangos en tbody (Completamente visibles, sin recortar números) */
        .col-rango-verde,
        .row-hora-registro td.col-rango-verde {
            text-align: center !important;
            background-color: #2e8b57 !important;
            border: 1px solid #257447 !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 0.74rem !important;
            line-height: 1.25 !important;
            min-width: 112px !important;
            width: 120px !important;
            max-width: 150px !important;
            padding: 6px 8px !important;
            white-space: normal !important;
            word-break: break-word !important;
        }
        .col-rango-amarillo,
        .row-hora-registro td.col-rango-amarillo {
            text-align: center !important;
            background-color: #facc15 !important;
            border: 1px solid #ca8a04 !important;
            color: #111827 !important;
            font-weight: 700 !important;
            font-size: 0.74rem !important;
            line-height: 1.25 !important;
            min-width: 112px !important;
            width: 120px !important;
            max-width: 150px !important;
            padding: 6px 8px !important;
            white-space: normal !important;
            word-break: break-word !important;
        }
        .col-rango-rojo,
        .row-hora-registro td.col-rango-rojo {
            text-align: center !important;
            background-color: #c94436 !important;
            border: 1px solid #a9362c !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 0.74rem !important;
            line-height: 1.25 !important;
            min-width: 112px !important;
            width: 120px !important;
            max-width: 150px !important;
            padding: 6px 8px !important;
            white-space: normal !important;
            word-break: break-word !important;
        }
        .col-rango-gris,
        .row-hora-registro td.col-rango-gris {
            text-align: center !important;
            background-color: #64748b !important;
            border: 1px solid #475569 !important;
            color: #ffffff !important;
            font-weight: 700 !important;
            font-size: 0.74rem !important;
            line-height: 1.25 !important;
            min-width: 112px !important;
            width: 120px !important;
            max-width: 150px !important;
            padding: 6px 8px !important;
            white-space: normal !important;
            word-break: break-word !important;
        }
        .seccion-etapa-header td {
            text-align: left !important;
            padding: 6px 14px !important;
            font-weight: 800 !important;
            font-size: 0.78rem !important;
            letter-spacing: 0.03em !important;
        }

        /* Fila 1: Hora del Registro */
        .row-hora-registro td {
            background-color: #ffffff !important;
            border-bottom: 2px solid #475569 !important;
            font-weight: 700;
            font-size: 0.76rem;
            color: #0f172a;
        }
        .row-hora-registro td.td-param {
            background-color: #f1f5f9 !important;
            color: #0f172a !important;
            font-weight: 800 !important;
            letter-spacing: 0.02em;
            text-align: left !important;
        }
        .hora-val-cell {
            font-family: monospace;
            font-weight: 800;
            font-size: 0.75rem;
            background-color: #ffffff !important;
            padding: 3px 4px !important;
        }

        .table-matriz-turno tbody td.td-param {
            position: sticky;
            left: 0;
            z-index: 4;
            background-color: #f8fafc;
            text-align: left !important;
            padding: 6px 12px !important;
            font-weight: 700;
            color: #0f172a;
            font-size: 0.78rem;
            line-height: 1.25 !important;
            box-shadow: 2px 0 5px rgba(0,0,0,0.03);
            white-space: normal !important;
            word-break: break-word;
            min-width: 250px !important;
            width: 265px !important;
            max-width: 310px !important;
        }
        .table-matriz-turno tbody tr:hover td.td-param {
            background-color: #f1f5f9;
        }
        .table-matriz-turno tfoot td {
            background-color: #ffffff;
            font-size: 0.76rem;
            font-weight: 600;
            color: #475569;
            padding: 7px 8px !important;
            height: auto !important;
        }
        .table-matriz-turno tfoot td.td-footer-label {
            position: sticky;
            left: 0;
            z-index: 4;
            background-color: #f1f5f9;
            text-align: left !important;
            padding: 7px 12px !important;
            color: #0f172a;
            font-weight: 700;
            font-size: 0.78rem;
            min-width: 250px !important;
            width: 265px !important;
            max-width: 310px !important;
        }

        /* Diseño especial amplio y limpio para las tablas de Chillers Normales (Diario y Semanal) */
        #bloqueChillerDiario .table-matriz-turno thead th.th-param,
        #bloqueChillerSemanal .table-matriz-turno thead th.th-param,
        #bloqueChillerDiario .table-matriz-turno tbody td.td-param,
        #bloqueChillerSemanal .table-matriz-turno tbody td.td-param,
        #bloqueChillerDiario .table-matriz-turno tfoot td.td-footer-label,
        #bloqueChillerSemanal .table-matriz-turno tfoot td.td-footer-label {
            min-width: 295px !important;
            width: 315px !important;
            max-width: 360px !important;
            padding: 8px 14px !important;
            font-size: 0.81rem !important;
            line-height: 1.3 !important;
            white-space: normal !important;
        }
        #bloqueChillerDiario .table-matriz-turno .th-rango-verde,
        #bloqueChillerDiario .table-matriz-turno .th-rango-amarillo,
        #bloqueChillerDiario .table-matriz-turno .th-rango-rojo,
        #bloqueChillerDiario .table-matriz-turno .col-rango-verde,
        #bloqueChillerDiario .table-matriz-turno .col-rango-amarillo,
        #bloqueChillerDiario .table-matriz-turno .col-rango-rojo,
        #bloqueChillerSemanal .table-matriz-turno .th-rango-verde,
        #bloqueChillerSemanal .table-matriz-turno .th-rango-amarillo,
        #bloqueChillerSemanal .table-matriz-turno .th-rango-rojo,
        #bloqueChillerSemanal .table-matriz-turno .col-rango-verde,
        #bloqueChillerSemanal .table-matriz-turno .col-rango-amarillo,
        #bloqueChillerSemanal .table-matriz-turno .col-rango-rojo {
            min-width: 118px !important;
            width: 125px !important;
            max-width: 155px !important;
            padding: 7px 10px !important;
            font-size: 0.77rem !important;
            white-space: normal !important;
        }
        /* CELDAS SÓLIDAS INDUSTRIALES (IMAGEN 2) */
        .cell-matrix {
            font-weight: 800 !important;
            font-size: 0.76rem !important;
            text-align: center !important;
            vertical-align: middle !important;
            cursor: pointer !important;
            padding: 4px 5px !important;
            min-width: 60px !important;
            transition: filter 0.15s ease !important;
            user-select: none !important;
        }
        .cell-matrix:hover {
            filter: brightness(1.12) !important;
            outline: 2px solid #ffffff !important;
            outline-offset: -2px !important;
        }
        .cell-matrix-verde {
            background-color: #2e8b57 !important;
            border: 1px solid #257447 !important;
            color: #ffffff !important;
        }
        .cell-matrix-amarillo {
            background-color: #facc15 !important;
            border: 1px solid #ca8a04 !important;
            color: #111827 !important;
        }
        .cell-matrix-rojo {
            background-color: #c94436 !important;
            border: 1px solid #a9362c !important;
            color: #ffffff !important;
        }
        .cell-matrix-lavado {
            background-color: #0284c7 !important;
            border: 1px solid #0369a1 !important;
            color: #ffffff !important;
        }
        .cell-matrix-gris {
            background-color: #64748b !important;
            border: 1px solid #475569 !important;
            color: #ffffff !important;
        }
        .cell-matrix-blanco {
            background-color: #ffffff !important;
            border: 1px solid #000000 !important;
            color: #000000 !important;
            font-weight: 800 !important;
        }
        .cell-matrix-blanco:hover {
            background-color: #f1f5f9 !important;
            outline: 2px solid #0f172a !important;
            outline-offset: -2px !important;
        }
        /* ESTILOS DE LA SÁBANA / BITÁCORA CONCENTRADA DE MEMBRANAS (FORMATO HOJA FÍSICA) */
        .bitacora-sabana-sheet {
            background: #ffffff;
            border: 2px solid #000000;
            border-radius: 6px;
            overflow: hidden;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08);
            margin-bottom: 2rem;
        }
        .bitacora-sabana-top {
            background: #ffffff;
            border-bottom: 2px solid #000000;
            padding: 12px 16px;
            color: #000000;
        }
        .bitacora-titulo-caja {
            border: 2px solid #000000;
            background: #ffffff;
            color: #000000;
            font-weight: 900;
            font-size: 1rem;
            letter-spacing: 0.8px;
            padding: 6px 24px;
            text-transform: uppercase;
            text-align: center;
        }
        .bitacora-campo-linea {
            font-size: 0.82rem;
            color: #000000;
            font-weight: 700;
        }
        .bitacora-campo-linea span {
            border-bottom: 1.5px solid #000000;
            padding: 0 8px 1px 8px;
            font-weight: 800;
            color: #000000;
        }
        .tabla-sabana-membranas {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.74rem;
            background-color: #ffffff;
            color: #000000;
        }
        .tabla-sabana-membranas th,
        .tabla-sabana-membranas td {
            border: 1px solid #000000 !important;
            padding: 3px 4px !important;
            text-align: center !important;
            vertical-align: middle !important;
            white-space: nowrap !important;
            color: #000000;
        }
        .tabla-sabana-membranas thead tr.row-etapa th {
            background-color: #f8fafc !important;
            color: #000000 !important;
            font-size: 0.78rem !important;
            font-weight: 900 !important;
            letter-spacing: 0.6px;
            text-transform: uppercase;
            padding: 5px 6px !important;
            border-bottom: 2px solid #000000 !important;
        }
        .tabla-sabana-membranas thead tr.row-paquete th {
            background-color: #ffffff !important;
            color: #000000 !important;
            font-size: 0.74rem !important;
            font-weight: 800 !important;
            padding: 4px 5px !important;
            border-bottom: 1.5px solid #000000 !important;
        }
        .tabla-sabana-membranas thead tr.row-param-nom th {
            background-color: #ffffff !important;
            color: #000000 !important;
            font-size: 0.64rem !important;
            font-weight: 800 !important;
            line-height: 1.1 !important;
            padding: 3px 3px !important;
            white-space: normal !important;
            min-width: 44px;
        }
        .tabla-sabana-membranas thead tr.row-param-rango th {
            font-size: 0.62rem !important;
            font-weight: 800 !important;
            line-height: 1.1 !important;
            padding: 3px 3px !important;
            border-bottom: 2px solid #000000 !important;
            white-space: normal !important;
        }
        .th-rango-pendiente {
            background-color: #ffffff !important;
            color: #000000 !important;
            font-style: italic;
        }
        .th-rango-semaforo {
            background-color: #ffffff !important;
            color: #000000 !important;
        }
        .borde-fin-paquete {
            border-right: 2px solid #000000 !important;
        }
        .borde-fin-etapa {
            border-right: 3px solid #000000 !important;
        }
        .sticky-col-hora {
            position: sticky;
            left: 0;
            z-index: 4;
            background-color: #ffffff !important;
            color: #000000 !important;
            font-weight: 800 !important;
            min-width: 60px !important;
            border-right: 2px solid #000000 !important;
        }
        .tabla-sabana-membranas.modo-hoja-fisica .col-extra-memb,
        .tabla-sabana-membranas.modo-hoja-fisica .col-semaforo-memb {
            display: none !important;
        }
        .tabla-sabana-membranas.modo-compacto .col-extra-memb {
            display: none !important;
        }
        .cell-matrix-empty {
            background-color: #ffffff !important;
            color: #000000 !important;
            font-weight: 600 !important;
            cursor: default !important;
        }
        .cell-obs-interactive {
            cursor: pointer !important;
            transition: all 0.15s ease-in-out !important;
        }
        .cell-obs-interactive:hover {
            outline: 2px solid #e49a32 !important;
            background-color: #fef08a !important;
        }
        .btn-obs-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            background-color: #fef3c7;
            color: #92400e;
            border: 1px solid #f59e0b;
            border-radius: 999px;
            padding: 2px 9px;
            font-size: 0.72rem;
            font-weight: 700;
            white-space: nowrap;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .btn-obs-pill:hover {
            background-color: #fde68a;
            color: #78350f;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .title-equipo {
            font-size: 0.88rem;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-left: 5px solid #0284c7;
            border-radius: 4px;
        }
        .badge-rango-ant {
            background-color: #f3e8ff;
            color: #7e22ce;
            border: 1px solid #d8b4fe;
            font-size: 0.60rem;
            font-weight: 700;
            padding: 1px 3px;
            border-radius: 3px;
            margin-top: 2px;
            cursor: help;
        }
        .btn-filtro-custom {
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
        }
        .table-container-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .modal-badge-grande {
            font-size: 1.35rem;
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            font-weight: 800;
        }
        .modal-badge-grande.cell-matrix-verde,
        .modal-badge-grande.bg-success {
            background-color: #2e8b57 !important;
            border: 2px solid #257447 !important;
            color: #ffffff !important;
        }
        .modal-badge-grande.cell-matrix-amarillo,
        .modal-badge-grande.bg-warning {
            background-color: #facc15 !important;
            border: 2px solid #ca8a04 !important;
            color: #111827 !important;
        }
        .modal-badge-grande.cell-matrix-rojo,
        .modal-badge-grande.bg-danger {
            background-color: #c94436 !important;
            border: 2px solid #a9362c !important;
            color: #ffffff !important;
        }
        .seccion-etapa-header td {
            letter-spacing: 0.05em;
        }
        .doc-header-print {
            display: none;
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            background-color: #ffffff;
        }
        .doc-header-print td {
            border: 1.5px solid #0f172a;
            vertical-align: middle;
            padding: 6px 10px;
            text-align: center;
        }
        @media print {
            body {
                zoom: 0.72;
                margin: 0 !important;
                padding: 4mm !important;
                background: #ffffff !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
            @page {
                size: landscape;
                margin: 4mm;
            }
            .header-box,
            .filter-card,
            .no-print,
            .btn,
            select {
                display: none !important;
            }
            .doc-header-print {
                display: table !important;
            }
            .container-fluid {
                padding: 0 !important;
            }
            .table-container-responsive {
                overflow: visible !important;
                box-shadow: none !important;
            }
            .table-matriz-turno {
                width: 100% !important;
                min-width: 100% !important;
                font-size: 8.5px !important;
            }
            .table-matriz-turno th,
            .table-matriz-turno td {
                padding: 2px 3px !important;
                height: 22px !important;
                border: 1px solid #000000 !important;
            }
            .bloque-paquete-print {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                margin-bottom: 10px !important;
            }
        }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-3 px-md-4">

    <!-- ENCABEZADO DE IMPRESIÓN OFICIAL (SOLO VISIBLE AL IMPRIMIR) -->
    <table class="doc-header-print">
        <tr>
            <td style="width: 22%;">
                <img src="img/negro.png" onerror="this.src='img/Logo Progel_small.jpg'" alt="Progel" style="max-height: 45px; max-width: 160px; object-fit: contain;">
            </td>
            <td style="width: 56%;">
                <div style="font-size: 11px; font-weight: 800; color: #334155;">PROGEL MEXICANA, S.A. DE C.V.</div>
                <div style="font-size: 15px; font-weight: 900; color: #0f172a; text-transform: uppercase;">
                    <?= $es_membrana ? 'BITÁCORA DIARIA DE SISTEMA DE MEMBRANAS (ULTRAFILTRACIÓN)' : 'HISTORIAL DE OPERACIÓN: ' . htmlspecialchars($equipo_nombre) ?>
                </div>
                <div style="font-size: 10px; font-weight: 700; color: #475569;">
                    <?= htmlspecialchars(($zona_nombre ?: 'ZONA GRIS') . ' · ' . ($area_nombre ?: 'ULTRAFILTRACIÓN')) ?>
                </div>
            </td>
            <td style="width: 22%; font-size: 10px; text-align: left;">
                <div><strong>Fecha Operativa:</strong> <?= date('d/m/Y', strtotime($fecha_filtro)) ?></div>
                <div><strong>Turno:</strong> <?= strtoupper(htmlspecialchars($turno_filtro)) ?></div>
                <?php if ($es_membrana): ?>
                    <div><strong>Filtro:</strong> <?= $filtro_etapa > 0 ? "Etapa $filtro_etapa" : 'Todas las Etapas' ?><?= $filtro_paquete > 0 ? " · Paquete $filtro_paquete" : '' ?></div>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    
    <!-- ENCABEZADO PRINCIPAL -->
    <div class="header-box mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary text-white rounded-pill px-3 py-1 fw-bold"><?= htmlspecialchars($zona_nombre ?: 'Zona') ?></span>
                <span class="badge bg-light text-secondary border rounded-pill px-3 py-1"><?= htmlspecialchars($area_nombre ?: 'Área') ?></span>
            </div>
            <h2 class="m-0 fw-bold text-dark d-flex align-items-center flex-wrap gap-2">
                <i class="bi bi-clock-history text-primary"></i> 
                Historial de Operación: <span class="text-primary"><?= htmlspecialchars($equipo_nombre) ?></span>
            </h2>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php
            $link_nueva_captura = "captura.php?id=$equipo_id";
            if (in_array($equipo_id, [84, 85, 86, 87, 88, 89])) {
                $link_nueva_captura = "http://192.168.1.105/sis_extraccion/index.php?view=votators";
            } elseif (in_array($equipo_id, [21, 22, 23, 24])) {
                $link_nueva_captura = "http://192.168.1.105/sis_extraccion/index.php?view=secadores";
            } elseif (in_array($equipo_id, [14, 15, 16, 17, 18])) {
                $map_conc_ext_h = [14 => 9, 15 => 10, 16 => 11, 17 => 12, 18 => 13];
                $ext_id_h = $map_conc_ext_h[$equipo_id] ?? 9;
                $link_nueva_captura = "http://192.168.1.105/sis_extraccion/index.php?view=concentradores&equipo_id=$ext_id_h";
            }
            ?>
            <button type="button" onclick="window.print()" class="btn btn-dark fw-bold px-3 shadow-sm">
                <i class="bi bi-printer-fill me-1"></i> <?= $es_membrana ? 'Imprimir Membranas' : 'Imprimir' ?>
            </button>
            <a href="<?= $link_nueva_captura ?>" class="btn btn-success fw-bold px-3 shadow-sm">
                <i class="bi bi-pencil-square me-1"></i> Nueva Captura
            </a>
            <a href="<?= ($zona_id > 0) ? 'reporte_general.php?zona=' . $zona_id : 'reporte_general.php' ?>" class="btn btn-outline-primary fw-bold" title="Ver Reporte General de la Zona <?= htmlspecialchars($zona_nombre ?: '') ?>">
                <i class="bi bi-grid-3x3-gap me-1"></i> Reporte General
            </a>
            <a href="<?= ($zona_id > 0) ? 'zona.php?id=' . $zona_id : 'index.php' ?>" class="btn btn-outline-secondary fw-bold" title="Volver a la Zona <?= htmlspecialchars($zona_nombre ?: '') ?>">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
        </div>
    </div>

    <!-- PANEL DE CONTROL Y FILTROS POR FECHA, TURNO E INTERVALO -->
    <div class="filter-card mb-4">
        <form method="GET" action="historial.php" id="formFiltro" class="row g-3 align-items-center">
            <input type="hidden" name="id" value="<?= $equipo_id ?>">
            <input type="hidden" name="turno" id="inputTurno" value="<?= htmlspecialchars($turno_filtro) ?>">
            <input type="hidden" name="intervalo" id="inputIntervalo" value="<?= htmlspecialchars($intervalo_filtro) ?>">
            <?php if ($es_chiller_hist): ?>
                <input type="hidden" name="vista_chiller" id="inputVistaChiller" value="<?= htmlspecialchars($vista_chiller) ?>">
            <?php endif; ?>
            <?php if ($es_membrana): ?>
                <input type="hidden" name="etapa" id="inputEtapa" value="<?= $filtro_etapa ?>">
                <input type="hidden" name="paquete" id="inputPaquete" value="<?= $filtro_paquete ?>">
            <?php endif; ?>
            
            <!-- Selector de Fecha con botones Anterior / Hoy / Siguiente -->
            <div class="col-xl-5 col-lg-5 col-md-6 col-12">
                <label class="form-label small fw-bold text-secondary mb-1">
                    <i class="bi bi-calendar-event me-1"></i> Fecha del Día Operativo<?= $es_chiller_hist ? " (Semana: " . date('d/m', strtotime($lunes_sem)) . " al " . date('d/m', strtotime($domingo_sem)) . ")" : "" ?>:
                </label>
                <?php if ($es_operador_hist): ?>
                    <div class="input-group input-group-sm">
                        <input type="date" name="fecha" value="<?= htmlspecialchars($fecha_filtro) ?>" 
                               class="form-control form-control-sm fw-bold text-center bg-light" readonly>
                        <span class="input-group-text bg-primary text-white fw-bold">
                            <i class="bi bi-lock-fill me-1"></i> Solo Día Actual
                        </span>
                    </div>
                <?php else: ?>
                    <div class="input-group input-group-sm">
                        <?php if ($es_chiller_hist): ?>
                            <a href="historial.php?id=<?= $equipo_id ?>&fecha=<?= $semana_anterior ?>&vista_chiller=<?= $vista_chiller ?>" 
                               class="btn btn-outline-dark fw-bold" title="Semana Anterior (-7 días)">
                                <i class="bi bi-chevron-double-left"></i> Sem
                            </a>
                        <?php endif; ?>
                        <a href="historial.php?id=<?= $equipo_id ?>&fecha=<?= $fecha_anterior ?>&turno=<?= $turno_filtro ?>&intervalo=<?= $intervalo_filtro ?><?= $es_chiller_hist ? "&vista_chiller=$vista_chiller" : "" ?><?= $es_membrana ? "&etapa=$filtro_etapa&paquete=$filtro_paquete" : "" ?>" 
                           class="btn btn-outline-secondary fw-bold" title="Día Anterior">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                        <input type="date" name="fecha" value="<?= htmlspecialchars($fecha_filtro) ?>" 
                               class="form-control form-control-sm fw-bold text-center" 
                               onchange="document.getElementById('formFiltro').submit();">
                        <a href="historial.php?id=<?= $equipo_id ?>&fecha=<?= $fecha_siguiente ?>&turno=<?= $turno_filtro ?>&intervalo=<?= $intervalo_filtro ?><?= $es_chiller_hist ? "&vista_chiller=$vista_chiller" : "" ?><?= $es_membrana ? "&etapa=$filtro_etapa&paquete=$filtro_paquete" : "" ?>" 
                           class="btn btn-outline-secondary fw-bold" title="Día Siguiente">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        <?php if ($es_chiller_hist): ?>
                            <a href="historial.php?id=<?= $equipo_id ?>&fecha=<?= $semana_siguiente ?>&vista_chiller=<?= $vista_chiller ?>" 
                               class="btn btn-outline-dark fw-bold" title="Semana Siguiente (+7 días)">
                                Sem <i class="bi bi-chevron-double-right"></i>
                            </a>
                        <?php endif; ?>
                        <a href="historial.php?id=<?= $equipo_id ?>&fecha=<?= $fecha_hoy ?>&turno=<?= $turno_filtro ?>&intervalo=<?= $intervalo_filtro ?><?= $es_chiller_hist ? "&vista_chiller=$vista_chiller" : "" ?><?= $es_membrana ? "&etapa=$filtro_etapa&paquete=$filtro_paquete" : "" ?>" 
                           class="btn <?= $fecha_filtro == $fecha_hoy ? 'btn-primary' : 'btn-outline-primary' ?> fw-bold">
                            Hoy
                        </a>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($es_chiller_hist): ?>
                <!-- Selector de Modo de Historial para Chillers Normales (Cambio instantáneo sin recargar) -->
                <div class="col-xl-4 col-lg-4 col-md-6 col-12">
                    <label class="form-label small fw-bold text-secondary mb-1">
                        <i class="bi bi-calendar2-week me-1"></i> Modo de Historial (Chillers Normales):
                    </label>
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <button type="button" id="btnVistaChillerDiario"
                                class="btn btn-filtro-custom <?= $vista_chiller === 'diario' ? 'btn-primary active fw-bold' : 'btn-outline-primary' ?>" 
                                onclick="cambiarVistaChiller('diario')">
                            <i class="bi bi-calendar-day me-1"></i> Diario
                        </button>
                        <button type="button" id="btnVistaChillerSemanal"
                                class="btn btn-filtro-custom <?= $vista_chiller === 'semanal' ? 'btn-success active fw-bold' : 'btn-outline-success' ?>" 
                                onclick="cambiarVistaChiller('semanal')">
                            <i class="bi bi-calendar-range me-1"></i> Semanal (Lun-Dom)
                        </button>
                        <button type="button" id="btnVistaChillerAmbos"
                                class="btn btn-filtro-custom <?= $vista_chiller === 'ambos' ? 'btn-dark active fw-bold' : 'btn-outline-dark' ?>" 
                                onclick="cambiarVistaChiller('ambos')">
                            <i class="bi bi-layout-split me-1"></i> Diario + Semanal
                        </button>
                    </div>
                </div>

                <!-- Acceso Rápido únicamente entre Chillers Normales (1 al 4 y 5 al 7) -->
                <div class="col-xl-3 col-lg-3 col-md-12 col-12">
                    <label class="form-label small fw-bold text-secondary mb-1">
                        <i class="bi bi-snow2 me-1"></i> Cambiar Banco de Chillers:
                    </label>
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <a href="historial.php?id=91&fecha=<?= $fecha_filtro ?>&vista_chiller=<?= $vista_chiller ?>" 
                           class="btn btn-filtro-custom <?= $equipo_id == 91 ? 'btn-info text-dark fw-bold active' : 'btn-outline-secondary' ?>"
                           title="Chillers Normales (1 al 4)">
                            Chillers 1 al 4
                        </a>
                        <a href="historial.php?id=92&fecha=<?= $fecha_filtro ?>&vista_chiller=<?= $vista_chiller ?>" 
                           class="btn btn-filtro-custom <?= $equipo_id == 92 ? 'btn-info text-dark fw-bold active' : 'btn-outline-secondary' ?>"
                           title="Chillers Normales (5 al 7)">
                            Chillers 5 al 7
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Selector de Turnos Operativos (Diurno 7am-7pm / Nocturno 7pm-7am / Ambos) -->
                <div class="col-xl-4 col-lg-4 col-md-6 col-12">
                    <label class="form-label small fw-bold text-secondary mb-1">
                        <i class="bi bi-shuffle me-1"></i> Turno Operativo:
                    </label>
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <button type="button" 
                                class="btn btn-filtro-custom <?= $turno_filtro === 'diurno' ? 'btn-warning text-dark active' : 'btn-outline-warning text-dark' ?>" 
                                onclick="cambiarTurno('diurno')">
                            <i class="bi bi-sun-fill me-1"></i> Diurno
                        </button>
                        <button type="button" 
                                class="btn btn-filtro-custom <?= $turno_filtro === 'nocturno' ? 'btn-primary active' : 'btn-outline-primary' ?>" 
                                onclick="cambiarTurno('nocturno')">
                            <i class="bi bi-moon-stars-fill me-1"></i> Nocturno
                        </button>
                        <button type="button" 
                                class="btn btn-filtro-custom <?= $turno_filtro === 'ambos' ? 'btn-dark active' : 'btn-outline-dark' ?>" 
                                onclick="cambiarTurno('ambos')">
                            <i class="bi bi-check2-all me-1"></i> Ambos
                        </button>
                    </div>
                </div>

                <!-- Selector de Frecuencia / Intervalo de Horas (1 Hora vs 2 Horas vs Solo con Datos) -->
                <div class="col-xl-3 col-lg-3 col-md-12 col-12">
                    <label class="form-label small fw-bold text-secondary mb-1">
                        <i class="bi bi-clock me-1"></i> Intervalo de Columnas:
                    </label>
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <button type="button" 
                                class="btn btn-filtro-custom <?= $intervalo_filtro === '1h' ? 'btn-info text-dark fw-bold active' : 'btn-outline-info text-dark' ?>" 
                                onclick="cambiarIntervalo('1h')" title="Muestra las 12 horas individuales del turno">
                            1 Hora
                        </button>
                        <button type="button" 
                                class="btn btn-filtro-custom <?= $intervalo_filtro === '2h' ? 'btn-info text-dark fw-bold active' : 'btn-outline-info text-dark' ?>" 
                                onclick="cambiarIntervalo('2h')" title="Muestra bloques consolidados de cada 2 horas">
                            2 Horas
                        </button>
                        <button type="button" 
                                class="btn btn-filtro-custom <?= $intervalo_filtro === 'auto' ? 'btn-info text-dark fw-bold active' : 'btn-outline-info text-dark' ?>" 
                                onclick="cambiarIntervalo('auto')" title="Muestra únicamente las horas donde hay capturas">
                            Solo con Datos
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- ===================================================================== -->
    <!-- RENDERIZADO EXCLUSIVO: MATRIZ POR TURNOS                             -->
    <!-- ===================================================================== -->

    <?php if ($es_membrana): ?>
        <?php
            $dias_semana_es = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            $dia_nombre_memb = $dias_semana_es[(int)date('w', strtotime($fecha_filtro))] ?? '';
        ?>
        <!-- BANNER Y FILTROS DE ETAPA, PAQUETE Y MODO DE VISTA PARA MEMBRANAS (ID 100) -->
        <div class="card shadow-sm border-0 mb-4 p-3 bg-white no-print">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill">
                        <i class="bi bi-funnel-fill me-1"></i> Bitácora de Operación de Membranas
                    </span>
                    <div class="btn-group btn-group-sm ms-1" role="group" aria-label="Selector de vista de membranas">
                        <button type="button" id="btnVistaSabanaMemb" class="btn btn-dark fw-bold px-3 active" onclick="cambiarVistaMembranas('sabana')">
                            <i class="bi bi-table me-1"></i> Bitácora Concentrada (Hoja Física)
                        </button>
                        <button type="button" id="btnVistaIndividualMemb" class="btn btn-outline-dark fw-bold px-3" onclick="cambiarVistaMembranas('individual')">
                            <i class="bi bi-grid-1x2 me-1"></i> Tablas por Paquete
                        </button>
                    </div>
                    <div class="btn-group btn-group-sm ms-1" id="grupoColumnasSabana" role="group">
                        <button type="button" id="btnColsFisica" class="btn btn-primary fw-bold active" onclick="cambiarColumnasSabana(2)" title="Igual a la hoja física: Presión Entrada y Sólidos Salida con parámetros en Pendiente y fondo blanco">
                            <i class="bi bi-file-earmark-spreadsheet me-1"></i> Hoja Física (Presión + Sólidos)
                        </button>
                        <button type="button" id="btnColsCompacta" class="btn btn-outline-primary fw-bold" onclick="cambiarColumnasSabana(4)" title="Agrega Permeado y Temp °C con semáforo">
                            <i class="bi bi-layout-three-columns me-1"></i> + Permeado y Temp (4 Cols)
                        </button>
                        <button type="button" id="btnColsCompleta" class="btn btn-outline-primary fw-bold" onclick="cambiarColumnasSabana(7)" title="Muestra los 7 parámetros por paquete (+P. Salida, ΔP, Brix Entrada)">
                            <i class="bi bi-columns-gap me-1"></i> Expandida (7 Cols)
                        </button>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="d-flex align-items-center gap-2">
                        <label class="small fw-bold text-secondary mb-0">Etapa:</label>
                        <select class="form-select form-select-sm fw-bold" style="width: auto;" 
                                onchange="const url = new URL(window.location.href); url.searchParams.set('etapa', this.value); url.searchParams.set('paquete', '0'); window.location.href = url.href;">
                            <option value="0" <?= ($filtro_etapa ?? 0) == 0 ? 'selected' : '' ?>>Todas las Etapas (1 a 4)</option>
                            <option value="1" <?= ($filtro_etapa ?? 0) == 1 ? 'selected' : '' ?>>Etapa 1 (Paq. 1, 2, 3, 4, 9)</option>
                            <option value="2" <?= ($filtro_etapa ?? 0) == 2 ? 'selected' : '' ?>>Etapa 2 (Paq. 5, 6, 7, 8)</option>
                            <option value="3" <?= ($filtro_etapa ?? 0) == 3 ? 'selected' : '' ?>>Etapa 3 (Paq. 10, 11)</option>
                            <option value="4" <?= ($filtro_etapa ?? 0) == 4 ? 'selected' : '' ?>>Etapa 4 (Paq. 12, 13)</option>
                        </select>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <label class="small fw-bold text-secondary mb-0">Paquete:</label>
                        <select class="form-select form-select-sm fw-bold" style="width: auto;" 
                                onchange="const url = new URL(window.location.href); url.searchParams.set('paquete', this.value); window.location.href = url.href;">
                            <option value="0" <?= ($filtro_paquete ?? 0) == 0 ? 'selected' : '' ?>>Todos los Paquetes (1 al 13)</option>
                            <?php foreach ($etapas_membranas_def as $e_id_sel => $e_info_sel): ?>
                                <optgroup label="<?= htmlspecialchars($e_info_sel['nombre']) ?>">
                                    <?php foreach ($e_info_sel['paquetes'] as $p_sel): ?>
                                        <option value="<?= $p_sel ?>" <?= ($filtro_paquete ?? 0) == $p_sel ? 'selected' : '' ?>>
                                            Paquete <?= $p_sel ?> (Etapa <?= $e_id_sel ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="button" onclick="window.print()" class="btn btn-dark btn-sm fw-bold px-3 shadow-sm">
                        <i class="bi bi-printer-fill me-1"></i> Imprimir Bitácora
                    </button>
                </div>
            </div>
        </div>

        <!-- ===================================================================== -->
        <!-- 1. VISTA SÁBANA CONCENTRADA (IGUAL A LA HOJA FÍSICA DE BITÁCORA)      -->
        <!--    Etapas 1 a 4 y sus Paquetes arriba en columnas | Horas en filas    -->
        <!-- ===================================================================== -->
        <div id="contenedorVistaSabanaMemb">
            <?php foreach ($turnos_a_mostrar as $t_key): 
                $t_info = $turnos_config[$t_key];
                $t_bloques = $t_info['bloques'];
                $etapas_mostrar = ($filtro_etapa > 0 && isset($etapas_membranas_def[$filtro_etapa]))
                    ? [$filtro_etapa => $etapas_membranas_def[$filtro_etapa]]
                    : $etapas_membranas_def;

                // Filtrar paquetes activos y recolectar operadores del turno
                $etapas_sabana = [];
                $operadores_turno_arr = [];
                foreach ($etapas_mostrar as $e_num => $e_info) {
                    $paqs = $e_info['paquetes'];
                    if ($filtro_paquete > 0) {
                        $paqs = array_values(array_filter($paqs, fn($pn) => $pn === $filtro_paquete));
                    }
                    if (!empty($paqs)) {
                        $etapas_sabana[$e_num] = $e_info;
                        $etapas_sabana[$e_num]['paquetes_activos'] = $paqs;
                        foreach ($paqs as $pn) {
                            $lects = $matriz_membranas[$t_key]['paquetes'][$pn] ?? [];
                            foreach ($lects as $rm_op) {
                                if (!empty($rm_op['operador_nomina'])) {
                                    $operadores_turno_arr[$rm_op['operador_nomina']] = true;
                                }
                            }
                        }
                    }
                }
                $operadores_turno_str = !empty($operadores_turno_arr) ? implode(', ', array_keys($operadores_turno_arr)) : 'Sin registro';
            ?>
            <div class="bitacora-sabana-sheet">
                <!-- ENCABEZADO IDÉNTICO A LA HOJA FÍSICA DE BITÁCORA DE OPERACIÓN DE MEMBRANAS -->
                <div class="bitacora-sabana-top">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-4 flex-wrap">
                            <div class="bitacora-campo-linea">
                                DÍA: <span><?= date('d-M-y', strtotime($fecha_filtro)) ?></span>
                            </div>
                            <div class="bitacora-campo-linea">
                                FECHA: <span><?= htmlspecialchars($dia_nombre_memb) ?></span>
                            </div>
                        </div>

                        <div class="bitacora-titulo-caja">
                            BITÁCORA DE OPERACIÓN DE MEMBRANAS
                        </div>

                        <div class="d-flex align-items-center gap-4 flex-wrap">
                            <div class="bitacora-campo-linea">
                                TURNO: <span><?= $t_key === 'nocturno' ? 'Noche' : 'Día' ?></span>
                            </div>
                            <div class="bitacora-campo-linea">
                                OPERADOR: <span><?= htmlspecialchars($operadores_turno_str) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABLA SÁBANA CONCENTRADA (MODO HOJA FÍSICA DE 2 COLUMNAS POR PAQUETE ACTIVO POR DEFECTO) -->
                <div class="table-container-responsive">
                    <table class="tabla-sabana-membranas modo-hoja-fisica">
                        <thead>
                            <!-- NIVEL 1: ETAPAS (1 a 4) Y SALIDA DE MEMBRANAS -->
                            <tr class="row-etapa">
                                <th rowspan="3" class="sticky-col-hora" style="z-index: 7;">
                                    HORA
                                </th>
                                <th rowspan="3" style="min-width: 62px; border-right: 1.5px solid #000000 !important;">
                                    # Lote /<br>Operador
                                </th>
                                <th rowspan="3" class="borde-fin-etapa" style="min-width: 58px;">
                                    Sólidos<br> de entrada
                                </th>
                                <?php foreach ($etapas_sabana as $e_num => $e_info): 
                                    $num_paqs_e = count($etapas_sabana[$e_num]['paquetes_activos']);
                                ?>
                                    <th colspan="<?= $num_paqs_e * 2 ?>" class="th-etapa-sabana borde-fin-etapa" data-num-paqs="<?= $num_paqs_e ?>">
                                        ETAPA <?= $e_num ?>
                                    </th>
                                <?php endforeach; ?>
                                <th rowspan="2" class="borde-fin-etapa" style="min-width: 64px; background-color: #e2e8f0 !important; color: #000000 !important;">
                                    Salida de<br>Membranas
                                </th>
                                <th rowspan="4" style="min-width: 85px;">
                                    OBSERVACIONES
                                </th>
                            </tr>

                            <!-- NIVEL 2: PAQUETES DE CADA ETAPA -->
                            <tr class="row-paquete">
                                <?php foreach ($etapas_sabana as $e_num => $e_info): 
                                    $paqs_act = $e_info['paquetes_activos'];
                                    $ult_idx = count($paqs_act) - 1;
                                    foreach ($paqs_act as $idx_p => $paq_num):
                                        $cls_borde = ($idx_p === $ult_idx) ? 'borde-fin-etapa' : 'borde-fin-paquete';
                                ?>
                                    <th colspan="2" class="th-paquete-sabana <?= $cls_borde ?>">
                                        Paquete <?= $paq_num ?>
                                    </th>
                                <?php endforeach; endforeach; ?>
                            </tr>

                            <!-- NIVEL 3: NOMBRES DE PARÁMETROS POR PAQUETE -->
                            <tr class="row-param-nom">
                                <?php foreach ($etapas_sabana as $e_num => $e_info): 
                                    $paqs_act = $e_info['paquetes_activos'];
                                    $ult_idx = count($paqs_act) - 1;
                                    foreach ($paqs_act as $idx_p => $paq_num):
                                        $cls_borde = ($idx_p === $ult_idx) ? 'borde-fin-etapa' : 'borde-fin-paquete';
                                ?>
                                    <th title="Presión de Entrada (bar)">Presión<br>Entrada</th>
                                    <th class="col-extra-memb" title="Presión de Salida (bar)">Presión<br>Salida</th>
                                    <th class="col-extra-memb" title="Delta de Presión (ΔP bar)">Delta<br>ΔP</th>
                                    <th class="col-extra-memb" title="Brix de Entrada (°Bx)">Brix<br>Entrada</th>
                                    <th class="th-solidos-salida <?= $cls_borde ?>" data-borde="<?= $cls_borde ?>" title="Sólidos / Brix de Salida (°Bx)">Sólidos<br>Salida</th>
                                    <th class="col-semaforo-memb" title="Brix Permeado (°Bx)">Permeado<br>(°Bx)</th>
                                    <th class="col-semaforo-memb <?= $cls_borde ?>" title="Temperatura de Membrana (°C)">Temp.<br>(°C)</th>
                                <?php endforeach; endforeach; ?>
                                <th class="borde-fin-etapa" title="Porcentaje de Sólidos Final de Salida de Membranas">%<br>Sólidos</th>
                            </tr>

                            <!-- NIVEL 4: RANGOS / PARÁMETROS META ("Pendiente" en todos los parámetros de la hoja) -->
                            <tr class="row-param-rango">
                                <th class="sticky-col-hora" style="font-size: 0.62rem !important;">MIN %</th>
                                <th class="th-rango-pendiente" style="border-right: 1.5px solid #000000 !important;">Pendiente</th>
                                <th class="th-rango-pendiente borde-fin-etapa">Pendiente</th>
                                <?php foreach ($etapas_sabana as $e_num => $e_info): 
                                    $paqs_act = $e_info['paquetes_activos'];
                                    $ult_idx = count($paqs_act) - 1;
                                    foreach ($paqs_act as $idx_p => $paq_num):
                                        $cls_borde = ($idx_p === $ult_idx) ? 'borde-fin-etapa' : 'borde-fin-paquete';
                                ?>
                                    <th class="th-rango-pendiente">Pendiente</th>
                                    <th class="th-rango-pendiente col-extra-memb">Pendiente</th>
                                    <th class="th-rango-pendiente col-extra-memb">Pendiente</th>
                                    <th class="th-rango-pendiente col-extra-memb">Pendiente</th>
                                    <th class="th-rango-pendiente <?= $cls_borde ?>">Pendiente</th>
                                    <th class="th-rango-semaforo col-semaforo-memb" title="Verde: < 0.1 | Amarillo: 0.1 - 0.5 | Rojo: > 0.5">
                                        &lt;0.1 V<br><span style="font-size:0.55rem;">0.1-0.5 A | &gt;0.5 R</span>
                                    </th>
                                    <th class="th-rango-semaforo col-semaforo-memb <?= $cls_borde ?>" title="Rojo: < 39 | Amarillo: 40 - 44.9 | Verde: 45 - 55 | Amarillo: 56 - 60 | Rojo: > 60">
                                        45-55 °C<br><span style="font-size:0.55rem;">&lt;39 / &gt;60 R</span>
                                    </th>
                                <?php endforeach; endforeach; ?>
                                <th class="th-rango-pendiente borde-fin-etapa">Pendiente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                // Buscar operador, sólidos de entrada iniciales, sólidos finales de salida y observaciones de esta hora
                                $op_hora = '';
                                $solidos_entrada_ini = '';
                                $solidos_salida_fin = '';
                                $obs_hora_lista = [];
                                $orden_serie_busqueda = [1, 2, 3, 4, 9, 5, 6, 7, 8, 10, 11, 12, 13];
                                foreach ($orden_serie_busqueda as $p_s) {
                                    $rm_s = $matriz_membranas[$t_key]['paquetes'][$p_s][$b_key] ?? null;
                                    if ($rm_s && strtoupper(trim($rm_s['estado'] ?? 'OPERACION')) === 'OPERACION') {
                                        if ($solidos_entrada_ini === '' && $rm_s['brix_entrada'] !== null && $rm_s['brix_entrada'] !== '') {
                                            $solidos_entrada_ini = number_format(floatval($rm_s['brix_entrada']), 1);
                                        }
                                        if ($rm_s['brix_salida'] !== null && $rm_s['brix_salida'] !== '') {
                                            $solidos_salida_fin = number_format(floatval($rm_s['brix_salida']), 1);
                                        }
                                    }
                                }
                                foreach ($etapas_sabana as $e_num => $e_info) {
                                    foreach ($e_info['paquetes_activos'] as $paq_num) {
                                        $rm_h = $matriz_membranas[$t_key]['paquetes'][$paq_num][$b_key] ?? null;
                                        if ($rm_h) {
                                            if (empty($op_hora) && !empty($rm_h['operador_nomina'])) {
                                                $op_hora = $rm_h['operador_nomina'];
                                            }
                                            if (!empty($rm_h['observaciones'])) {
                                                $obs_hora_lista[] = "Paq. $paq_num (E$e_num): " . $rm_h['observaciones'];
                                            }
                                        }
                                    }
                                }
                            ?>
                            <tr>
                                <!-- Columna 1: HORA -->
                                <td class="sticky-col-hora font-monospace">
                                    <div class="fw-bold" style="font-size: 0.78rem; color: #000000;"><?= htmlspecialchars($b_data['label']) ?></div>
                                </td>

                                <!-- Columna 2: OPERADOR / # LOTE (Fondo blanco y letra negra) -->
                                <td class="cell-matrix-blanco" style="border-right: 1.5px solid #000000 !important;">
                                    <?= !empty($op_hora) ? htmlspecialchars($op_hora) : '/' ?>
                                </td>

                                <!-- Columna 3: SÓLIDOS DE ENTRADA (Fondo blanco y letra negra) -->
                                <td class="<?= $solidos_entrada_ini !== '' ? 'cell-matrix-blanco' : 'cell-matrix-empty' ?> borde-fin-etapa">
                                    <?= $solidos_entrada_ini !== '' ? $solidos_entrada_ini : '/' ?>
                                </td>

                                <!-- Columnas por Etapa y Paquete -->
                                <?php foreach ($etapas_sabana as $e_num => $e_info): 
                                    $paqs_act = $e_info['paquetes_activos'];
                                    $ult_idx = count($paqs_act) - 1;
                                    foreach ($paqs_act as $idx_p => $paq_num):
                                        $cls_borde = ($idx_p === $ult_idx) ? 'borde-fin-etapa' : 'borde-fin-paquete';
                                        $rm = $matriz_membranas[$t_key]['paquetes'][$paq_num][$b_key] ?? null;

                                        if (!$rm):
                                ?>
                                    <td class="cell-matrix-empty">/</td>
                                    <td class="cell-matrix-empty col-extra-memb">/</td>
                                    <td class="cell-matrix-empty col-extra-memb">/</td>
                                    <td class="cell-matrix-empty col-extra-memb">/</td>
                                    <td class="cell-matrix-empty <?= $cls_borde ?>">/</td>
                                    <td class="cell-matrix-empty col-semaforo-memb">/</td>
                                    <td class="cell-matrix-empty col-semaforo-memb <?= $cls_borde ?>">/</td>
                                <?php
                                        else:
                                            $est = strtoupper(trim($rm['estado'] ?? 'OPERACION'));
                                            $fEx = !empty($rm['fecha_registro']) ? date('d/m/Y H:i:s', strtotime($rm['fecha_registro'])) : ($fecha_filtro . ' ' . $rm['hora']);
                                            $opM = $rm['operador_nomina'] ?? 'N/A';
                                            $obsM = !empty($rm['observaciones']) ? $rm['observaciones'] : 'Sin observaciones';
                                            $loteM = "Etapa $e_num · Paquete $paq_num";

                                            if (in_array($est, ['LAVADO', 'MMT', 'MMTT', 'FO', 'F.O.'])):
                                                if ($est === 'LAVADO') { $clsEst = 'cell-matrix-lavado'; $txtEst = 'LAVADO'; }
                                                elseif ($est === 'MMT' || $est === 'MMTT') { $clsEst = 'cell-matrix-amarillo'; $txtEst = 'MMTT'; }
                                                else { $clsEst = 'cell-matrix-rojo'; $txtEst = 'F.O.'; }
                                ?>
                                    <td colspan="2" class="cell-matrix td-estado-sabana <?= $clsEst ?> <?= $cls_borde ?>"><?= $txtEst ?></td>
                                <?php
                                            else:
                                                $vPin  = ($rm['presion_entrada'] !== null && $rm['presion_entrada'] !== '') ? number_format(floatval($rm['presion_entrada']), 0) : '/';
                                                $vPout = ($rm['presion_salida'] !== null && $rm['presion_salida'] !== '') ? number_format(floatval($rm['presion_salida']), 1) : '/';
                                                $vDp   = ($rm['delta_presion'] !== null && $rm['delta_presion'] !== '') ? number_format(floatval($rm['delta_presion']), 1) : '/';
                                                $vBin  = ($rm['brix_entrada'] !== null && $rm['brix_entrada'] !== '') ? number_format(floatval($rm['brix_entrada']), 1) : '/';
                                                $vBout = ($rm['brix_salida'] !== null && $rm['brix_salida'] !== '') ? number_format(floatval($rm['brix_salida']), 1) : '/';

                                                // Semáforo Permeado (< 0.1 Verde | 0.1 - 0.5 Amarillo | > 0.5 Rojo)
                                                $bxPerm = ($rm['brix_permeado'] !== null && $rm['brix_permeado'] !== '') ? floatval($rm['brix_permeado']) : null;
                                                if ($bxPerm !== null) {
                                                    $vPerm = number_format($bxPerm, 2);
                                                    if ($bxPerm < 0.1) $clsPerm = 'cell-matrix-verde';
                                                    elseif ($bxPerm <= 0.5) $clsPerm = 'cell-matrix-amarillo';
                                                    else $clsPerm = 'cell-matrix-rojo';
                                                } else {
                                                    $vPerm = '/';
                                                    $clsPerm = 'cell-matrix-empty';
                                                }

                                                // Semáforo Temperatura (< 39 Rojo | 40 - 44.9 Amarillo | 45 - 55 Verde | 56 - 60 Amarillo | > 60 Rojo)
                                                $tMemb = (isset($rm['temperatura']) && $rm['temperatura'] !== null && $rm['temperatura'] !== '') ? floatval($rm['temperatura']) : null;
                                                if ($tMemb !== null) {
                                                    $vTemp = number_format($tMemb, 1);
                                                    if ($tMemb >= 45 && $tMemb <= 55) $clsTemp = 'cell-matrix-verde';
                                                    elseif (($tMemb >= 40 && $tMemb < 45) || ($tMemb > 55 && $tMemb <= 60)) $clsTemp = 'cell-matrix-amarillo';
                                                    else $clsTemp = 'cell-matrix-rojo';
                                                } else {
                                                    $vTemp = '/';
                                                    $clsTemp = 'cell-matrix-empty';
                                                }

                                                $fExJ  = htmlspecialchars(json_encode($fEx), ENT_QUOTES, 'UTF-8');
                                                $opMJ  = htmlspecialchars(json_encode($opM), ENT_QUOTES, 'UTF-8');
                                                $loteJ = htmlspecialchars(json_encode($loteM . " | P.Ent: $vPin | P.Sal: $vPout | ΔP: $vDp | Brix Ent: $vBin | Brix Sal: $vBout"), ENT_QUOTES, 'UTF-8');
                                                $obsMJ = htmlspecialchars(json_encode($obsM), ENT_QUOTES, 'UTF-8');
                                                $pendJ = htmlspecialchars(json_encode("Pendiente"), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <!-- 1. Presión Entrada (Fondo blanco y letra negra) -->
                                    <td class="cell-matrix <?= $vPin !== '/' ? 'cell-matrix-blanco' : 'cell-matrix-empty' ?>"
                                        onclick='abrirModalDetalle(<?= htmlspecialchars(json_encode("Paquete $paq_num (E$e_num) · Presión Entrada"), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode("$vPin bar"), ENT_QUOTES, "UTF-8") ?>, "cell-matrix-blanco", <?= $fExJ ?>, <?= $opMJ ?>, <?= $loteJ ?>, <?= $obsMJ ?>, <?= $pendJ ?>)'>
                                        <?= $vPin ?>
                                    </td>
                                    <!-- 2. Presión Salida (Fondo blanco y letra negra - Oculta en vista compacta/física) -->
                                    <td class="cell-matrix col-extra-memb <?= $vPout !== '/' ? 'cell-matrix-blanco' : 'cell-matrix-empty' ?>"
                                        onclick='abrirModalDetalle(<?= htmlspecialchars(json_encode("Paquete $paq_num (E$e_num) · Presión Salida"), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode("$vPout bar"), ENT_QUOTES, "UTF-8") ?>, "cell-matrix-blanco", <?= $fExJ ?>, <?= $opMJ ?>, <?= $loteJ ?>, <?= $obsMJ ?>, <?= $pendJ ?>)'>
                                        <?= $vPout ?>
                                    </td>
                                    <!-- 3. Delta P (Fondo blanco y letra negra - Oculta en vista compacta/física) -->
                                    <td class="cell-matrix col-extra-memb <?= $vDp !== '/' ? 'cell-matrix-blanco' : 'cell-matrix-empty' ?>"
                                        onclick='abrirModalDetalle(<?= htmlspecialchars(json_encode("Paquete $paq_num (E$e_num) · Delta ΔP"), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode("$vDp bar"), ENT_QUOTES, "UTF-8") ?>, "cell-matrix-blanco", <?= $fExJ ?>, <?= $opMJ ?>, <?= $loteJ ?>, <?= $obsMJ ?>, <?= $pendJ ?>)'>
                                        <?= $vDp ?>
                                    </td>
                                    <!-- 4. Brix Entrada (Fondo blanco y letra negra - Oculta en vista compacta/física) -->
                                    <td class="cell-matrix col-extra-memb <?= $vBin !== '/' ? 'cell-matrix-blanco' : 'cell-matrix-empty' ?>"
                                        onclick='abrirModalDetalle(<?= htmlspecialchars(json_encode("Paquete $paq_num (E$e_num) · Brix Entrada"), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode("$vBin °Bx"), ENT_QUOTES, "UTF-8") ?>, "cell-matrix-blanco", <?= $fExJ ?>, <?= $opMJ ?>, <?= $loteJ ?>, <?= $obsMJ ?>, <?= $pendJ ?>)'>
                                        <?= $vBin ?>
                                    </td>
                                    <!-- 5. Sólidos / Brix Salida (Fondo blanco y letra negra) -->
                                    <td class="cell-matrix <?= $vBout !== '/' ? 'cell-matrix-blanco' : 'cell-matrix-empty' ?> <?= $cls_borde ?>"
                                        onclick='abrirModalDetalle(<?= htmlspecialchars(json_encode("Paquete $paq_num (E$e_num) · Sólidos / Brix Salida"), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode("$vBout °Bx"), ENT_QUOTES, "UTF-8") ?>, "cell-matrix-blanco", <?= $fExJ ?>, <?= $opMJ ?>, <?= $loteJ ?>, <?= $obsMJ ?>, <?= $pendJ ?>)'>
                                        <?= $vBout ?>
                                    </td>
                                    <!-- 6. Permeado (Con Semáforo) -->
                                    <td class="cell-matrix col-semaforo-memb <?= $clsPerm ?>"
                                        onclick='abrirModalDetalle(<?= htmlspecialchars(json_encode("Paquete $paq_num (E$e_num) · Brix Permeado"), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode("$vPerm °Bx"), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($clsPerm), ENT_QUOTES, "UTF-8") ?>, <?= $fExJ ?>, <?= $opMJ ?>, <?= $loteJ ?>, <?= $obsMJ ?>, <?= htmlspecialchars(json_encode("Verde: < 0.1 | Amarillo: 0.1 - 0.5 | Rojo: > 0.5"), ENT_QUOTES, "UTF-8") ?>)'>
                                        <?= $vPerm ?>
                                    </td>
                                    <!-- 7. Temperatura (Con Semáforo) -->
                                    <td class="cell-matrix col-semaforo-memb <?= $clsTemp ?> <?= $cls_borde ?>"
                                        onclick='abrirModalDetalle(<?= htmlspecialchars(json_encode("Paquete $paq_num (E$e_num) · Temperatura (°C)"), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode("$vTemp °C"), ENT_QUOTES, "UTF-8") ?>, <?= htmlspecialchars(json_encode($clsTemp), ENT_QUOTES, "UTF-8") ?>, <?= $fExJ ?>, <?= $opMJ ?>, <?= $loteJ ?>, <?= $obsMJ ?>, <?= htmlspecialchars(json_encode("Verde: 45 - 55 °C | Amarillo: 40 - 44.9 / 56 - 60 °C | Rojo: < 39 o > 60 °C"), ENT_QUOTES, "UTF-8") ?>)'>
                                        <?= $vTemp ?>
                                    </td>
                                <?php
                                            endif;
                                        endif;
                                    endforeach;
                                endforeach; ?>

                                <!-- Columna Penúltima: SALIDA DE MEMBRANAS (% SÓLIDOS - Fondo blanco y letra negra) -->
                                <td class="<?= $solidos_salida_fin !== '' ? 'cell-matrix-blanco' : 'cell-matrix-empty' ?> borde-fin-etapa">
                                    <?= $solidos_salida_fin !== '' ? $solidos_salida_fin : '/' ?>
                                </td>

                                <!-- Columna Final: OBSERVACIONES DE LA HORA -->
                                <td style="background-color: #ffffff;">
                                    <?php if (!empty($obs_hora_lista)): 
                                        $obs_concat = implode("\n• ", $obs_hora_lista);
                                        $obs_concat = "• " . $obs_concat;
                                        $titObsH = htmlspecialchars(json_encode("Observaciones de Membranas ($b_key hrs)"), ENT_QUOTES, 'UTF-8');
                                        $subObsH = htmlspecialchars(json_encode("Fecha: " . date('d/m/Y', strtotime($fecha_filtro)) . " · Hora: $b_key"), ENT_QUOTES, 'UTF-8');
                                        $txtObsH = htmlspecialchars(json_encode($obs_concat), ENT_QUOTES, 'UTF-8');
                                        $nomObsH = htmlspecialchars(json_encode($op_hora ?: 'N/A'), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <span class="btn-obs-pill" onclick='abrirModalObservacion(<?= $titObsH ?>, <?= $subObsH ?>, <?= $txtObsH ?>, <?= $nomObsH ?>)' title="Clic para ver observaciones">
                                            <i class="bi bi-chat-left-text-fill text-warning"></i> Ver nota (<?= count($obs_hora_lista) ?>)
                                        </span>
                                    <?php else: ?>
                                        <span class="text-dark">/</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ===================================================================== -->
        <!-- 2. MATRICES INDIVIDUALES POR PAQUETE (CON "Pendiente" Y FONDO BLANCO) -->
        <!-- ===================================================================== -->
        <div id="contenedorVistaIndividualMemb" style="display: none;">
        <?php foreach ($turnos_a_mostrar as $t_key): 
            $t_info = $turnos_config[$t_key];
            $t_bloques = $t_info['bloques'];
            $etapas_mostrar = ($filtro_etapa > 0 && isset($etapas_membranas_def[$filtro_etapa]))
                ? [$filtro_etapa => $etapas_membranas_def[$filtro_etapa]]
                : $etapas_membranas_def;
        ?>
            <?php foreach ($etapas_mostrar as $e_num => $e_info): 
                $paquetes_de_etapa = $e_info['paquetes'];
                if ($filtro_paquete > 0) {
                    $paquetes_de_etapa = array_values(array_filter($paquetes_de_etapa, fn($pn) => $pn === $filtro_paquete));
                }
                if (empty($paquetes_de_etapa)) continue;
            ?>
                <!-- ENCABEZADO VISUAL DE LA ETAPA -->
                <div class="card border-0 shadow-sm mb-3" style="border-left: 6px solid <?= $e_info['color'] ?> !important; background: #f8fafc;">
                    <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-<?= $e_info['badge'] ?> fs-6 px-3 py-1 rounded-pill">
                                <i class="bi bi-diagram-3-fill me-1"></i> ETAPA <?= $e_num ?>
                            </span>
                            <h6 class="m-0 fw-bold text-dark"><?= htmlspecialchars($e_info['nombre']) ?></h6>
                            <span class="text-muted small">(Paquetes: <?= implode(', ', $e_info['paquetes']) ?>)</span>
                        </div>
                        <span class="badge <?= $t_key === 'nocturno' ? 'bg-primary text-white' : 'bg-warning text-dark' ?> px-3 py-1">
                            <i class="bi <?= $t_info['icono'] ?> me-1"></i><?= strtoupper($t_info['titulo']) ?>
                        </span>
                    </div>
                </div>

                <!-- TABLA INDIVIDUAL POR CADA PAQUETE DE LA ETAPA -->
                <?php foreach ($paquetes_de_etapa as $paq_num): 
                    $lecturas_paq_turno = $matriz_membranas[$t_key]['paquetes'][$paq_num] ?? [];
                    $conteo_paq = count($lecturas_paq_turno);
                ?>
                <div class="mb-4 bloque-paquete-print" id="bloque-paquete-<?= $paq_num ?>-<?= $t_key ?>">
                    <!-- Barra Superior del Paquete con su Etapa -->
                    <div class="title-equipo" style="border-left-color: <?= $e_info['color'] ?>;">
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <span class="badge bg-<?= $e_info['badge'] ?> px-3 py-1 fs-6">
                                <i class="bi bi-layers-fill me-1"></i> ETAPA <?= $e_num ?> · <?= htmlspecialchars($e_info['subtitulo']) ?>
                            </span>
                            <span class="badge bg-dark text-white px-3 py-1 fs-6">
                                <i class="bi bi-box-seam me-1"></i> PAQUETE <?= $paq_num ?>
                            </span>
                            <span class="vr mx-1"></span>
                            <span class="badge <?= $t_key === 'nocturno' ? 'bg-primary text-white' : 'bg-warning text-dark' ?> px-3 py-1">
                                <i class="bi <?= $t_info['icono'] ?> me-1"></i><?= strtoupper($t_info['titulo']) ?>
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span><strong>Fecha Operativa:</strong> <?= date('Y-m-d', strtotime($fecha_filtro)) ?></span>
                            <span class="badge <?= $conteo_paq > 0 ? 'bg-success' : 'bg-secondary' ?> text-white rounded-pill px-3 py-1 fw-bold">
                                <?= $conteo_paq ?> Capturas
                            </span>
                        </div>
                    </div>

                    <!-- Tabla Completa de Parámetros del Paquete -->
                    <div class="table-container-responsive mb-3">
                        <table class="table-matriz-turno">
                            <thead>
                                <tr class="header-navy" style="<?= $t_key === 'nocturno' ? 'background-color: #0f172a !important;' : '' ?>">
                                    <th class="th-param">Parámetro (Paquete <?= $paq_num ?> · Etapa <?= $e_num ?>)</th>
                                    <th class="th-rango-verde" title="Rango Óptimo">Verde</th>
                                    <th class="th-rango-amarillo" title="Rango de Alerta">Amarillo</th>
                                    <th class="th-rango-rojo" title="Rango Crítico">Rojo</th>
                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                        $hNum = intval(substr($b_key, 0, 2));
                                        $esDiaSig = ($t_key === 'nocturno' && $hNum < 12);
                                    ?>
                                        <th class="th-hora" style="vertical-align: middle;">
                                            <div class="fw-bold" style="font-size: 11px;"><?= $b_data['label'] ?></div>
                                            <div style="font-size: 8px; font-weight: 600; opacity: 0.9; color: <?= $esDiaSig ? '#93c5fd' : '#cbd5e1' ?>;">
                                                <?= $b_data['sub'] ?>
                                            </div>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- 1. Hora Exacta del Registro del Paquete -->
                                <tr class="row-hora-registro">
                                    <td class="td-param fw-bold">
                                        <i class="bi bi-clock-history me-1 text-primary"></i> Hora del Registro
                                    </td>
                                    <td class="col-rango-gris fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                                    <td class="col-rango-gris fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                                    <td class="col-rango-gris fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                        $rm = $lecturas_paq_turno[$b_key] ?? null;
                                        $h_str = '';
                                        if ($rm) {
                                            $h_str = !empty($rm['fecha_registro']) ? date('H:i:s', strtotime($rm['fecha_registro'])) : ($rm['hora'] ?? '');
                                        }
                                    ?>
                                        <td class="hora-val-cell text-center font-monospace <?= $h_str ? 'fw-bold text-dark' : 'text-muted' ?>">
                                            <?= htmlspecialchars($h_str) ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>

                                <!-- 2. Operador (Nómina) del Paquete (En la parte superior) -->
                                <tr class="row-hora-registro">
                                    <td class="td-param fw-bold">
                                        <i class="bi bi-person-badge text-primary me-1"></i> Operador (Nómina)
                                    </td>
                                    <td class="col-rango-gris fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                                    <td class="col-rango-gris fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                                    <td class="col-rango-gris fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                        $rm = $lecturas_paq_turno[$b_key] ?? null;
                                        $op = $rm['operador_nomina'] ?? '';
                                    ?>
                                        <td style="background-color: #ffffff; text-align: center;">
                                            <?php if (!empty($op)): ?>
                                                <span class="badge bg-dark text-white rounded-pill px-2 py-1" style="font-size: 0.72rem;"><?= htmlspecialchars($op) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size: 0.75rem;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>

                                <!-- 3. Presión de Entrada (bar) -->
                                <tr>
                                    <td class="td-param">Presión Entrada (bar)</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                        $rm = $lecturas_paq_turno[$b_key] ?? null;
                                        if ($rm):
                                            $est = strtoupper(trim($rm['estado'] ?? 'OPERACION'));
                                            $pIn = $rm['presion_entrada'];
                                            if ($est === 'LAVADO') { echo '<td class="cell-matrix cell-matrix-lavado">LAVADO</td>'; }
                                            elseif ($est === 'MMT' || $est === 'MMTT') { echo '<td class="cell-matrix cell-matrix-amarillo">MMTT</td>'; }
                                            elseif ($est === 'FO' || $est === 'F.O.') { echo '<td class="cell-matrix cell-matrix-rojo">F.O.</td>'; }
                                            elseif ($pIn !== null && $pIn !== '') {
                                                $vPIn = floatval($pIn);
                                                echo '<td class="cell-matrix cell-matrix-blanco">' . number_format($vPIn, 2) . '</td>';
                                            } else {
                                                echo '<td class="cell-matrix cell-matrix-empty">-</td>';
                                            }
                                        else:
                                            echo '<td class="cell-matrix cell-matrix-empty">-</td>';
                                        endif;
                                    endforeach; ?>
                                </tr>

                                <!-- 4. Presión de Salida (bar) -->
                                <tr>
                                    <td class="td-param">Presión Salida (bar)</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                        $rm = $lecturas_paq_turno[$b_key] ?? null;
                                        if ($rm):
                                            $est = strtoupper(trim($rm['estado'] ?? 'OPERACION'));
                                            $pOut = $rm['presion_salida'];
                                            if ($est === 'LAVADO') { echo '<td class="cell-matrix cell-matrix-lavado">LAVADO</td>'; }
                                            elseif ($est === 'MMT' || $est === 'MMTT') { echo '<td class="cell-matrix cell-matrix-amarillo">MMTT</td>'; }
                                            elseif ($est === 'FO' || $est === 'F.O.') { echo '<td class="cell-matrix cell-matrix-rojo">F.O.</td>'; }
                                            elseif ($pOut !== null && $pOut !== '') {
                                                $vPOut = floatval($pOut);
                                                echo '<td class="cell-matrix cell-matrix-blanco">' . number_format($vPOut, 2) . '</td>';
                                            } else {
                                                echo '<td class="cell-matrix cell-matrix-empty">-</td>';
                                            }
                                        else:
                                            echo '<td class="cell-matrix cell-matrix-empty">-</td>';
                                        endif;
                                    endforeach; ?>
                                </tr>

                                <!-- 5. Delta de Presión (ΔP bar) -->
                                <tr>
                                    <td class="td-param">Delta de Presión (ΔP bar)</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                        $rm = $lecturas_paq_turno[$b_key] ?? null;
                                        if ($rm):
                                            $est = strtoupper(trim($rm['estado'] ?? ''));
                                            $dp = $rm['delta_presion'];
                                            $fExacta = !empty($rm['fecha_registro']) ? $rm['fecha_registro'] : ($rm['fecha'] . ' ' . $rm['hora']);
                                            $fExactaStr = date("d/m/Y h:i:s A", strtotime($fExacta));

                                            if ($est === 'LAVADO') { $cCls = 'cell-matrix-lavado'; $bText = 'LAVADO'; }
                                            elseif ($est === 'MMT' || $est === 'MMTT') { $cCls = 'cell-matrix-amarillo'; $bText = 'MMTT'; }
                                            elseif ($est === 'FO' || $est === 'F.O.') { $cCls = 'cell-matrix-rojo'; $bText = 'F.O.'; }
                                            elseif ($dp !== null && is_numeric($dp)) {
                                                $dpVal = floatval($dp);
                                                $cCls = 'cell-matrix-blanco';
                                                $bText = number_format($dpVal, 2);
                                            } else {
                                                $cCls = 'cell-matrix-empty';
                                                $bText = '-';
                                            }

                                            $pEnt = ($rm['presion_entrada'] !== null && $rm['presion_entrada'] !== '') ? $rm['presion_entrada'] . ' bar' : '-';
                                            $pSal = ($rm['presion_salida'] !== null && $rm['presion_salida'] !== '') ? $rm['presion_salida'] . ' bar' : '-';
                                            $bSal = ($rm['brix_salida'] !== null && $rm['brix_salida'] !== '') ? $rm['brix_salida'] . ' °Bx' : '-';
                                            $obsVal = !empty($rm['observaciones']) ? $rm['observaciones'] : 'Sin observaciones';
                                            $opVal = $rm['operador_nomina'] ?? 'N/A';
                                            $loteInfo = "Etapa $e_num · Paquete $paq_num | P.Ent: $pEnt | P.Sal: $pSal | Brix Sal: $bSal";

                                            $pNomJson = htmlspecialchars(json_encode("Etapa $e_num - Paquete $paq_num (ΔP bar)"), ENT_QUOTES, 'UTF-8');
                                            $valJson = htmlspecialchars(json_encode("ΔP: $bText (Estado: $est)"), ENT_QUOTES, 'UTF-8');
                                            $bClassJson = htmlspecialchars(json_encode($cCls), ENT_QUOTES, 'UTF-8');
                                            $fExactaJson = htmlspecialchars(json_encode($fExactaStr), ENT_QUOTES, 'UTF-8');
                                            $nomJson = htmlspecialchars(json_encode($opVal), ENT_QUOTES, 'UTF-8');
                                            $loteJson = htmlspecialchars(json_encode($loteInfo), ENT_QUOTES, 'UTF-8');
                                            $obsJson = htmlspecialchars(json_encode($obsVal), ENT_QUOTES, 'UTF-8');
                                            $rangoJson = htmlspecialchars(json_encode("Pendiente"), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <td class="cell-matrix <?= $cCls ?>" 
                                            onclick='abrirModalDetalle(<?= $pNomJson ?>, <?= $valJson ?>, <?= $bClassJson ?>, <?= $fExactaJson ?>, <?= $nomJson ?>, <?= $loteJson ?>, <?= $obsJson ?>, <?= $rangoJson ?>)'
                                            title="P.Ent: <?= $pEnt ?> | P.Sal: <?= $pSal ?> | Brix: <?= $bSal ?>">
                                            <?= $bText ?>
                                        </td>
                                    <?php else: ?>
                                        <td class="cell-matrix cell-matrix-empty">-</td>
                                    <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>

                                <!-- 6. Brix Entrada (°Bx) del Paquete -->
                                <tr>
                                    <td class="td-param">Brix Entrada (°Bx)</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                        $rm = $lecturas_paq_turno[$b_key] ?? null;
                                        if ($rm):
                                            $est = strtoupper(trim($rm['estado'] ?? 'OPERACION'));
                                            $bx = ($rm['brix_entrada'] !== null && $rm['brix_entrada'] !== '') ? floatval($rm['brix_entrada']) : null;
                                            if ($est === 'LAVADO') { echo '<td class="cell-matrix cell-matrix-lavado">LAVADO</td>'; }
                                            elseif ($est === 'MMT' || $est === 'MMTT') { echo '<td class="cell-matrix cell-matrix-amarillo">MMTT</td>'; }
                                            elseif ($est === 'FO' || $est === 'F.O.') { echo '<td class="cell-matrix cell-matrix-rojo">F.O.</td>'; }
                                            elseif ($bx !== null) { echo '<td class="cell-matrix cell-matrix-blanco">' . number_format($bx, 1) . '</td>'; }
                                            else { echo '<td class="cell-matrix cell-matrix-empty">-</td>'; }
                                        else:
                                            echo '<td class="cell-matrix cell-matrix-empty">-</td>';
                                        endif;
                                    endforeach; ?>
                                </tr>

                                <!-- 7. Brix Salida / Concentrado (°Bx) del Paquete -->
                                <tr>
                                    <td class="td-param">Brix Salida / Concentrado (°Bx)</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <td class="col-rango-gris">Pendiente</td>
                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                        $rm = $lecturas_paq_turno[$b_key] ?? null;
                                        if ($rm):
                                            $est = strtoupper(trim($rm['estado'] ?? 'OPERACION'));
                                            $bx = ($rm['brix_salida'] !== null && $rm['brix_salida'] !== '') ? floatval($rm['brix_salida']) : null;
                                            if ($est === 'LAVADO') { echo '<td class="cell-matrix cell-matrix-lavado">LAVADO</td>'; }
                                            elseif ($est === 'MMT' || $est === 'MMTT') { echo '<td class="cell-matrix cell-matrix-amarillo">MMTT</td>'; }
                                            elseif ($est === 'FO' || $est === 'F.O.') { echo '<td class="cell-matrix cell-matrix-rojo">F.O.</td>'; }
                                            elseif ($bx !== null) { echo '<td class="cell-matrix cell-matrix-blanco">' . number_format($bx, 1) . '</td>'; }
                                            else { echo '<td class="cell-matrix cell-matrix-empty">-</td>'; }
                                        else:
                                            echo '<td class="cell-matrix cell-matrix-empty">-</td>';
                                        endif;
                                    endforeach; ?>
                                </tr>

                                <!-- 8. Brix Permeado (°Bx) del Paquete -->
                                <tr>
                                    <td class="td-param">Brix Permeado (°Bx)</td>
                                    <td class="col-rango-verde">&lt; 0.1</td>
                                    <td class="col-rango-amarillo">0.1 - 0.5</td>
                                    <td class="col-rango-rojo">&gt; 0.5</td>
                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                        $rm = $lecturas_paq_turno[$b_key] ?? null;
                                        if ($rm):
                                            $est = strtoupper(trim($rm['estado'] ?? 'OPERACION'));
                                            $bx = ($rm['brix_permeado'] !== null && $rm['brix_permeado'] !== '') ? floatval($rm['brix_permeado']) : null;
                                            if ($est === 'LAVADO') {
                                                echo '<td class="cell-matrix cell-matrix-lavado">LAVADO</td>';
                                            } elseif ($est === 'MMT' || $est === 'MMTT') {
                                                echo '<td class="cell-matrix cell-matrix-amarillo">MMTT</td>';
                                            } elseif ($est === 'FO' || $est === 'F.O.') {
                                                echo '<td class="cell-matrix cell-matrix-rojo">F.O.</td>';
                                            } elseif ($bx !== null) {
                                                if ($bx < 0.1) {
                                                    $clsPerm = 'cell-matrix-verde';
                                                } elseif ($bx >= 0.1 && $bx <= 0.5) {
                                                    $clsPerm = 'cell-matrix-amarillo';
                                                } else {
                                                    $clsPerm = 'cell-matrix-rojo';
                                                }
                                                $txtPerm = number_format($bx, 2);
                                                $fExPerm = !empty($rm['fecha_registro']) ? date('d/m/Y H:i:s', strtotime($rm['fecha_registro'])) : ($fecha_filtro . ' ' . $rm['hora']);
                                                $opPerm = $rm['operador_nomina'] ?? 'N/A';
                                                $obsPerm = !empty($rm['observaciones']) ? $rm['observaciones'] : 'Sin observaciones';
                                                $lotePerm = "Etapa $e_num · Paquete $paq_num";
                                                $pNomPJson = htmlspecialchars(json_encode("Etapa $e_num - Paquete $paq_num · Brix Permeado (°Bx)"), ENT_QUOTES, 'UTF-8');
                                                $valPJson = htmlspecialchars(json_encode($txtPerm . " °Bx"), ENT_QUOTES, 'UTF-8');
                                                $clsPJson = htmlspecialchars(json_encode($clsPerm), ENT_QUOTES, 'UTF-8');
                                                $fExPJson = htmlspecialchars(json_encode($fExPerm), ENT_QUOTES, 'UTF-8');
                                                $nomPJson = htmlspecialchars(json_encode($opPerm), ENT_QUOTES, 'UTF-8');
                                                $lotePJson = htmlspecialchars(json_encode($lotePerm), ENT_QUOTES, 'UTF-8');
                                                $obsPJson = htmlspecialchars(json_encode($obsPerm), ENT_QUOTES, 'UTF-8');
                                                $rangoPJson = htmlspecialchars(json_encode("Verde: < 0.1 | Amarillo: 0.1 - 0.5 | Rojo: > 0.5"), ENT_QUOTES, 'UTF-8');
                                                echo "<td class=\"cell-matrix $clsPerm\" onclick='abrirModalDetalle($pNomPJson, $valPJson, $clsPJson, $fExPJson, $nomPJson, $lotePJson, $obsPJson, $rangoPJson)' title=\"Permeado: $txtPerm °Bx (Clic para ver detalle)\">$txtPerm</td>";
                                            } else {
                                                echo '<td class="cell-matrix cell-matrix-empty">-</td>';
                                            }
                                        else:
                                            echo '<td class="cell-matrix cell-matrix-empty">-</td>';
                                        endif;
                                    endforeach; ?>
                                </tr>

                                <!-- 9. Temperatura (°C) del Paquete -->
                                <tr>
                                    <td class="td-param">Temperatura (°C)</td>
                                    <td class="col-rango-verde">45 - 55</td>
                                    <td class="col-rango-amarillo">40 - 44.9 / 56 - 60</td>
                                    <td class="col-rango-rojo">&lt; 39 / &gt; 60</td>
                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                        $rm = $lecturas_paq_turno[$b_key] ?? null;
                                        if ($rm):
                                            $est = strtoupper(trim($rm['estado'] ?? 'OPERACION'));
                                            $tempVal = (isset($rm['temperatura']) && $rm['temperatura'] !== null && $rm['temperatura'] !== '') ? floatval($rm['temperatura']) : null;
                                            if ($est === 'LAVADO') {
                                                echo '<td class="cell-matrix cell-matrix-lavado">LAVADO</td>';
                                            } elseif ($est === 'MMT' || $est === 'MMTT') {
                                                echo '<td class="cell-matrix cell-matrix-amarillo">MMTT</td>';
                                            } elseif ($est === 'FO' || $est === 'F.O.') {
                                                echo '<td class="cell-matrix cell-matrix-rojo">F.O.</td>';
                                            } elseif ($tempVal !== null) {
                                                if ($tempVal >= 45 && $tempVal <= 55) {
                                                    $clsTemp = 'cell-matrix-verde';
                                                } elseif (($tempVal >= 40 && $tempVal < 45) || ($tempVal > 55 && $tempVal <= 60)) {
                                                    $clsTemp = 'cell-matrix-amarillo';
                                                } else {
                                                    $clsTemp = 'cell-matrix-rojo';
                                                }
                                                $txtTemp = number_format($tempVal, 1);
                                                $fExTemp = !empty($rm['fecha_registro']) ? date('d/m/Y H:i:s', strtotime($rm['fecha_registro'])) : ($fecha_filtro . ' ' . $rm['hora']);
                                                $opTemp = $rm['operador_nomina'] ?? 'N/A';
                                                $obsTemp = !empty($rm['observaciones']) ? $rm['observaciones'] : 'Sin observaciones';
                                                $loteTemp = "Etapa $e_num · Paquete $paq_num";
                                                $pNomTJson = htmlspecialchars(json_encode("Etapa $e_num - Paquete $paq_num · Temperatura (°C)"), ENT_QUOTES, 'UTF-8');
                                                $valTJson = htmlspecialchars(json_encode($txtTemp . " °C"), ENT_QUOTES, 'UTF-8');
                                                $clsTJson = htmlspecialchars(json_encode($clsTemp), ENT_QUOTES, 'UTF-8');
                                                $fExTJson = htmlspecialchars(json_encode($fExTemp), ENT_QUOTES, 'UTF-8');
                                                $nomTJson = htmlspecialchars(json_encode($opTemp), ENT_QUOTES, 'UTF-8');
                                                $loteTJson = htmlspecialchars(json_encode($loteTemp), ENT_QUOTES, 'UTF-8');
                                                $obsTJson = htmlspecialchars(json_encode($obsTemp), ENT_QUOTES, 'UTF-8');
                                                $rangoTJson = htmlspecialchars(json_encode("Verde: 45 - 55 °C | Amarillo: 40 - 44.9 / 56 - 60 °C | Rojo: < 39 o > 60 °C"), ENT_QUOTES, 'UTF-8');
                                                echo "<td class=\"cell-matrix $clsTemp\" onclick='abrirModalDetalle($pNomTJson, $valTJson, $clsTJson, $fExTJson, $nomTJson, $loteTJson, $obsTJson, $rangoTJson)' title=\"Temperatura: $txtTemp °C (Clic para ver detalle)\">$txtTemp</td>";
                                            } else {
                                                echo '<td class="cell-matrix cell-matrix-empty">-</td>';
                                            }
                                        else:
                                            echo '<td class="cell-matrix cell-matrix-empty">-</td>';
                                        endif;
                                    endforeach; ?>
                                </tr>
                            </tbody>
                            <tfoot>
                                <!-- Observaciones del Paquete -->
                                <tr>
                                    <td class="td-footer-label"><i class="bi bi-chat-text text-secondary me-1"></i> Observaciones</td>
                                    <td class="col-rango-gris fw-bold" style="opacity: 0.85;">--</td>
                                    <td class="col-rango-gris fw-bold" style="opacity: 0.85;">--</td>
                                    <td class="col-rango-gris fw-bold" style="opacity: 0.85;">--</td>
                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                        $rm = $lecturas_paq_turno[$b_key] ?? null;
                                        $ob = $rm['observaciones'] ?? '';
                                        $op_m_obs = $rm['operador_nomina'] ?? '';
                                    ?>
                                        <?php if (!empty($ob)): 
                                            $titObsM = htmlspecialchars(json_encode("Etapa $e_num · Paquete $paq_num"), ENT_QUOTES, 'UTF-8');
                                            $subObsM = htmlspecialchars(json_encode("Fecha: " . date('d/m/Y', strtotime($fecha_filtro)) . " · Hora: $b_key"), ENT_QUOTES, 'UTF-8');
                                            $txtObsM = htmlspecialchars(json_encode($ob), ENT_QUOTES, 'UTF-8');
                                            $nomObsM = htmlspecialchars(json_encode($op_m_obs ?: 'N/A'), ENT_QUOTES, 'UTF-8');
                                        ?>
                                            <td class="cell-obs-interactive" style="background-color: #fffbeb !important;" 
                                                onclick='abrirModalObservacion(<?= $titObsM ?>, <?= $subObsM ?>, <?= $txtObsM ?>, <?= $nomObsM ?>)' 
                                                title="Clic para ver observación completa">
                                                <span class="btn-obs-pill">
                                                    <i class="bi bi-chat-left-text-fill text-warning"></i> Ver nota
                                                </span>
                                            </td>
                                        <?php else: ?>
                                            <td class="text-muted" style="background-color: #ffffff; font-size: 0.75rem;">-</td>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </div>

        <script>
        function cambiarVistaMembranas(modo) {
            const divSabana = document.getElementById('contenedorVistaSabanaMemb');
            const divIndiv = document.getElementById('contenedorVistaIndividualMemb');
            const btnSabana = document.getElementById('btnVistaSabanaMemb');
            const btnIndiv = document.getElementById('btnVistaIndividualMemb');
            const grpCols = document.getElementById('grupoColumnasSabana');

            if (modo === 'individual') {
                if (divSabana) divSabana.style.display = 'none';
                if (divIndiv) divIndiv.style.display = '';
                if (grpCols) grpCols.style.display = 'none';
                if (btnSabana) { btnSabana.classList.remove('btn-dark', 'active'); btnSabana.classList.add('btn-outline-dark'); }
                if (btnIndiv) { btnIndiv.classList.remove('btn-outline-dark'); btnIndiv.classList.add('btn-dark', 'active'); }
            } else {
                if (divSabana) divSabana.style.display = '';
                if (divIndiv) divIndiv.style.display = 'none';
                if (grpCols) grpCols.style.display = '';
                if (btnSabana) { btnSabana.classList.remove('btn-outline-dark'); btnSabana.classList.add('btn-dark', 'active'); }
                if (btnIndiv) { btnIndiv.classList.remove('btn-dark', 'active'); btnIndiv.classList.add('btn-outline-dark'); }
            }
        }

        function cambiarColumnasSabana(colsPorPaq) {
            if (colsPorPaq === true) colsPorPaq = 4;
            if (colsPorPaq === false) colsPorPaq = 7;
            colsPorPaq = parseInt(colsPorPaq, 10) || 2;

            document.querySelectorAll('.tabla-sabana-membranas').forEach(tb => {
                tb.classList.remove('modo-hoja-fisica', 'modo-compacto');
                if (colsPorPaq === 2) tb.classList.add('modo-hoja-fisica');
                else if (colsPorPaq === 4) tb.classList.add('modo-compacto');
            });
            document.querySelectorAll('.th-etapa-sabana').forEach(th => {
                const nPaqs = parseInt(th.getAttribute('data-num-paqs') || '1', 10);
                th.setAttribute('colspan', (nPaqs * colsPorPaq).toString());
            });
            document.querySelectorAll('.th-paquete-sabana, .td-estado-sabana').forEach(el => {
                el.setAttribute('colspan', colsPorPaq.toString());
            });

            const btnFis = document.getElementById('btnColsFisica');
            const btnComp = document.getElementById('btnColsCompacta');
            const btnFull = document.getElementById('btnColsCompleta');
            if (btnFis) btnFis.className = (colsPorPaq === 2) ? 'btn btn-primary fw-bold active' : 'btn btn-outline-primary fw-bold';
            if (btnComp) btnComp.className = (colsPorPaq === 4) ? 'btn btn-primary fw-bold active' : 'btn btn-outline-primary fw-bold';
            if (btnFull) btnFull.className = (colsPorPaq === 7) ? 'btn btn-primary fw-bold active' : 'btn btn-outline-primary fw-bold';
        }
        </script>

    <?php elseif ($es_chiller_hist): ?>
        <!-- ===================================================================== -->
        <!-- HISTORIAL DIARIO Y SEMANAL EXCLUSIVO PARA CHILLERS NORMALES (91, 92)  -->
        <!-- ===================================================================== -->

        <?php 
            $sesiones_dia = $capturas_dia_chiller['sesiones'] ?? [];
            $num_sesiones_dia = count($sesiones_dia);
        ?>
        <!-- 1. SECCIÓN DE HISTORIAL DIARIO (DÍA SELECCIONADO) -->
        <div id="bloqueChillerDiario" class="mb-4" style="<?= !in_array($vista_chiller, ['diario', 'ambos']) ? 'display: none;' : '' ?>">
            <div class="title-equipo" style="border-left-color: #0284c7;">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span><strong>Equipo:</strong> <?= htmlspecialchars($equipo_nombre) ?></span>
                    <span class="vr mx-1"></span>
                    <span class="badge bg-primary text-white px-3 py-1 fs-6">
                        <i class="bi bi-calendar-day me-1"></i> HISTORIAL DIARIO — PARÁMETROS DIARIOS
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span><strong>Fecha Operativa:</strong> <?= date('d/m/Y', strtotime($fecha_filtro)) ?></span>
                    <span class="badge <?= $num_sesiones_dia > 0 ? 'bg-success' : 'bg-secondary' ?> text-white rounded-pill px-3 py-1 fw-bold">
                        <?= $num_sesiones_dia ?> <?= $num_sesiones_dia === 1 ? 'Captura en el Día' : 'Capturas en el Día' ?>
                    </span>
                </div>
            </div>

            <div class="table-container-responsive mb-4">
                <table class="table-matriz-turno">
                    <thead>
                        <tr class="header-navy">
                            <th class="th-param">Parámetro Operativo</th>
                            <th class="th-rango-verde" title="Rango Óptimo">Verde</th>
                            <th class="th-rango-amarillo" title="Rango de Alerta">Amarillo</th>
                            <th class="th-rango-rojo" title="Rango Crítico">Rojo</th>
                            <?php if ($num_sesiones_dia > 0): 
                                $idx_ses = 1;
                                foreach ($sesiones_dia as $ses_key => $ses_meta):
                            ?>
                                <th class="th-hora" style="vertical-align: middle; min-width: 120px;">
                                    <div class="fw-bold" style="font-size: 11px;">
                                        <?= $num_sesiones_dia === 1 ? 'Lectura del Día' : "Captura #$idx_ses" ?>
                                    </div>
                                    <div style="font-size: 9px; font-weight: 600; color: #93c5fd;">
                                        <?= htmlspecialchars($ses_meta['hora']) ?>
                                    </div>
                                </th>
                            <?php 
                                $idx_ses++;
                                endforeach; 
                            else: ?>
                                <th class="th-hora" style="vertical-align: middle; min-width: 150px;">
                                    <div class="fw-bold" style="font-size: 11px;">Lectura del Día</div>
                                    <div style="font-size: 9px; font-weight: 600; color: #cbd5e1;">Sin Captura</div>
                                </th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Fila 1: Hora del Registro -->
                        <tr class="row-hora-registro">
                            <td class="td-param fw-bold">
                                <i class="bi bi-clock-history me-1 text-primary"></i> Hora del Registro
                            </td>
                            <td class="col-rango-verde fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <td class="col-rango-amarillo fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <td class="col-rango-rojo fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <?php if ($num_sesiones_dia > 0): ?>
                                <?php foreach ($sesiones_dia as $ses_key => $ses_meta): ?>
                                    <td class="hora-val-cell text-center font-monospace fw-bold text-dark">
                                        <?= htmlspecialchars($ses_meta['hora'] ?: '-') ?>
                                    </td>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <td class="hora-val-cell text-center font-monospace text-muted">-</td>
                            <?php endif; ?>
                        </tr>

                        <!-- Fila 2: Operador (Nómina) -->
                        <tr class="row-hora-registro">
                            <td class="td-param fw-bold">
                                <i class="bi bi-person-badge text-primary me-1"></i> Operador (Nómina)
                            </td>
                            <td class="col-rango-verde fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <td class="col-rango-amarillo fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <td class="col-rango-rojo fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <?php if ($num_sesiones_dia > 0): ?>
                                <?php foreach ($sesiones_dia as $ses_key => $ses_meta): 
                                    $op_dia = $ses_meta['operador'] ?? '';
                                ?>
                                    <td style="background-color: #ffffff; text-align: center;">
                                        <?php if (!empty($op_dia)): ?>
                                            <span class="badge bg-dark text-white rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                                                <?= htmlspecialchars($op_dia) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted" style="font-size: 0.75rem;">-</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <td style="background-color: #ffffff; text-align: center;">
                                    <span class="text-muted" style="font-size: 0.75rem;">-</span>
                                </td>
                            <?php endif; ?>
                        </tr>

                        <!-- Filas de Parámetros Diarios -->
                        <?php foreach ($params_diarios_chiller as $pid => $p): 
                            $rangos_desglosados = obtenerRangosDesglosados($p);
                        ?>
                        <tr>
                            <td class="td-param" title="<?= htmlspecialchars($p['nombre_parametro']) ?>">
                                <?= htmlspecialchars($p['nombre_parametro']) ?>
                            </td>
                            <td class="col-rango-verde" title="Rango Óptimo: <?= htmlspecialchars($rangos_desglosados['verde']) ?>">
                                <?= htmlspecialchars($rangos_desglosados['verde']) ?>
                            </td>
                            <td class="col-rango-amarillo" title="Rango de Alerta: <?= htmlspecialchars($rangos_desglosados['amarillo']) ?>">
                                <?= htmlspecialchars($rangos_desglosados['amarillo']) ?>
                            </td>
                            <td class="col-rango-rojo" title="Rango Crítico: <?= htmlspecialchars($rangos_desglosados['rojo']) ?>">
                                <?= htmlspecialchars($rangos_desglosados['rojo']) ?>
                            </td>

                            <?php if ($num_sesiones_dia > 0): ?>
                                <?php foreach ($sesiones_dia as $ses_key => $ses_meta): 
                                    $lectura = $capturas_dia_chiller['valores'][$pid][$ses_key] ?? null;
                                    $val = $lectura['val'] ?? null;
                                    if ($val !== null && $val !== '') {
                                        $f_exacta = $lectura['fecha_exacta'] ?? null;
                                        $p_eval = obtenerParametroEnFecha($p, $f_exacta, $historial_rangos);
                                        $es_rango_ant = !empty($p_eval['es_rango_anterior']);
                                        $tooltip_ant = $p_eval['info_cambio']['tooltip'] ?? '';
                                        $badge_class = getSemaforoHorizontal($val, $p_eval);

                                        if (strpos($badge_class, 'bg-success') !== false) $cCls = 'cell-matrix-verde';
                                        elseif (strpos($badge_class, 'bg-warning') !== false) $cCls = 'cell-matrix-amarillo';
                                        elseif (strpos($badge_class, 'bg-danger') !== false) $cCls = 'cell-matrix-rojo';
                                        elseif (strpos($badge_class, 'badge-lavado') !== false) $cCls = 'cell-matrix-lavado';
                                        else $cCls = 'cell-matrix-gris';

                                        $mostrar = is_numeric($val) ? (floatval($val) == intval($val) ? number_format(floatval($val), 0) : number_format(floatval($val), 2)) : htmlspecialchars($val);

                                        $pNomJson = htmlspecialchars(json_encode($p['nombre_parametro']), ENT_QUOTES, 'UTF-8');
                                        $valJson = htmlspecialchars(json_encode($mostrar), ENT_QUOTES, 'UTF-8');
                                        $badgeClassJson = htmlspecialchars(json_encode($cCls), ENT_QUOTES, 'UTF-8');
                                        $fExactaJson = htmlspecialchars(json_encode($f_exacta ? date("d/m/Y h:i:s A", strtotime($f_exacta)) : '-'), ENT_QUOTES, 'UTF-8');
                                        $nomJson = htmlspecialchars(json_encode($lectura['operador'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                                        $loteJson = htmlspecialchars(json_encode($lectura['lote'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $obsJson = htmlspecialchars(json_encode($lectura['obs'] ?? 'Sin observaciones'), ENT_QUOTES, 'UTF-8');
                                        $rangoMetaJson = htmlspecialchars(json_encode("Verde: {$rangos_desglosados['verde']} | Amarillo: {$rangos_desglosados['amarillo']} | Rojo: {$rangos_desglosados['rojo']}"), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <td class="cell-matrix <?= $cCls ?>"
                                        onclick='abrirModalDetalle(<?= $pNomJson ?>, <?= $valJson ?>, <?= $badgeClassJson ?>, <?= $fExactaJson ?>, <?= $nomJson ?>, <?= $loteJson ?>, <?= $obsJson ?>, <?= $rangoMetaJson ?>)'
                                        title="<?= htmlspecialchars($p['nombre_parametro']) ?>: <?= $mostrar ?> (Clic para ver detalle)">
                                        <?= $mostrar ?>
                                        <?php if ($es_rango_ant): ?>
                                            <span class="badge-rango-ant d-block" title="<?= htmlspecialchars($tooltip_ant) ?>">R. ant</span>
                                        <?php endif; ?>
                                    </td>
                                <?php } else { ?>
                                    <td class="cell-matrix cell-matrix-empty">-</td>
                                <?php } ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <td class="cell-matrix cell-matrix-empty">-</td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="td-footer-label"><i class="bi bi-chat-text text-secondary me-1"></i> Observaciones</td>
                            <td class="col-rango-verde fw-bold" style="opacity: 0.85;">--</td>
                            <td class="col-rango-amarillo fw-bold" style="opacity: 0.85;">--</td>
                            <td class="col-rango-rojo fw-bold" style="opacity: 0.85;">--</td>
                            <?php if ($num_sesiones_dia > 0): ?>
                                <?php foreach ($sesiones_dia as $ses_key => $ses_meta): 
                                    $ob_dia = $ses_meta['obs'] ?? '';
                                    $op_dia_obs = $ses_meta['operador'] ?? '';
                                ?>
                                    <?php if (!empty($ob_dia)): 
                                        $titObsD = htmlspecialchars(json_encode($equipo_nombre), ENT_QUOTES, 'UTF-8');
                                        $subObsD = htmlspecialchars(json_encode("Fecha: " . date('d/m/Y', strtotime($fecha_filtro)) . " · Hora: " . ($ses_meta['hora'] ?? $ses_key)), ENT_QUOTES, 'UTF-8');
                                        $txtObsD = htmlspecialchars(json_encode($ob_dia), ENT_QUOTES, 'UTF-8');
                                        $nomObsD = htmlspecialchars(json_encode($op_dia_obs ?: 'N/A'), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <td class="cell-obs-interactive" style="background-color: #fffbeb !important;"
                                            onclick='abrirModalObservacion(<?= $titObsD ?>, <?= $subObsD ?>, <?= $txtObsD ?>, <?= $nomObsD ?>)'
                                            title="Clic para ver observación completa">
                                            <span class="btn-obs-pill">
                                                <i class="bi bi-chat-left-text-fill text-warning"></i> Ver nota
                                            </span>
                                        </td>
                                    <?php else: ?>
                                        <td class="text-muted" style="background-color: #ffffff; font-size: 0.75rem;">-</td>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <td class="text-muted" style="background-color: #ffffff; font-size: 0.75rem;">-</td>
                            <?php endif; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <?php 
            $dias_con_captura_sem = count($matriz_semanal_chiller['meta'] ?? []);
            $rutinas_capturadas_sem = count($lecturas_rutinas_semanales ?? []);
        ?>
        <!-- 2. SECCIÓN DE HISTORIAL SEMANAL (LUNES A DOMINGO + RUTINAS SEMANALES) -->
        <div id="bloqueChillerSemanal" class="mb-4" style="<?= !in_array($vista_chiller, ['semanal', 'ambos']) ? 'display: none;' : '' ?>">
            <!-- 2.A) MATRIZ SEMANAL DÍA POR DÍA (LUNES A DOMINGO) -->
            <div class="title-equipo" style="border-left-color: #16a34a;">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span><strong>Equipo:</strong> <?= htmlspecialchars($equipo_nombre) ?></span>
                    <span class="vr mx-1"></span>
                    <span class="badge bg-success text-white px-3 py-1 fs-6">
                        <i class="bi bi-calendar-range me-1"></i> HISTORIAL SEMANAL — SEGUIMIENTO DE LUNES A DOMINGO
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span><strong>Semana:</strong> <?= date('d/m/Y', strtotime($lunes_sem)) ?> al <?= date('d/m/Y', strtotime($domingo_sem)) ?></span>
                    <span class="badge bg-dark text-white rounded-pill px-3 py-1 fw-bold">
                        <?= $dias_con_captura_sem ?> / 7 Días con Registro
                    </span>
                </div>
            </div>

            <div class="table-container-responsive mb-4">
                <table class="table-matriz-turno">
                    <thead>
                        <tr class="header-navy" style="background-color: #0f172a !important;">
                            <th class="th-param">Parámetro Operativo</th>
                            <th class="th-rango-verde" title="Rango Óptimo">Verde</th>
                            <th class="th-rango-amarillo" title="Rango de Alerta">Amarillo</th>
                            <th class="th-rango-rojo" title="Rango Crítico">Rojo</th>
                            <?php foreach ($dias_semana_chiller as $f_dia => $d_info): 
                                $esDiaActivo = ($f_dia === $fecha_filtro);
                            ?>
                                <th class="th-hora" style="vertical-align: middle; min-width: 105px; <?= $esDiaActivo ? 'background-color: #1e40af !important; box-shadow: inset 0 -3px 0 #38bdf8;' : '' ?>">
                                    <?php if (!$es_operador_hist): ?>
                                        <a href="historial.php?id=<?= $equipo_id ?>&fecha=<?= $f_dia ?>&vista_chiller=<?= $vista_chiller ?>" 
                                           class="text-white text-decoration-none d-block" title="Ir al detalle del día <?= $d_info['nombre'] ?> <?= $d_info['corto'] ?>">
                                            <div class="fw-bold" style="font-size: 11px;"><?= $d_info['nombre'] ?></div>
                                            <div style="font-size: 9px; font-weight: 600; color: <?= $esDiaActivo ? '#fde047' : '#cbd5e1' ?>;">
                                                <?= $d_info['corto'] ?><?= $esDiaActivo ? ' ★' : '' ?>
                                            </div>
                                        </a>
                                    <?php else: ?>
                                        <div class="fw-bold" style="font-size: 11px;"><?= $d_info['nombre'] ?></div>
                                        <div style="font-size: 9px; font-weight: 600; color: <?= $esDiaActivo ? '#fde047' : '#cbd5e1' ?>;">
                                            <?= $d_info['corto'] ?><?= $esDiaActivo ? ' ★' : '' ?>
                                        </div>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Fila 1: Hora del Registro por Día de la Semana -->
                        <tr class="row-hora-registro">
                            <td class="td-param fw-bold">
                                <i class="bi bi-clock-history me-1 text-primary"></i> Hora del Registro
                            </td>
                            <td class="col-rango-verde fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <td class="col-rango-amarillo fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <td class="col-rango-rojo fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <?php foreach ($dias_semana_chiller as $f_dia => $d_info): 
                                $meta_d = $matriz_semanal_chiller['meta'][$f_dia] ?? null;
                                $hora_d = $meta_d['hora'] ?? '';
                                $tot_cap_d = (int)($meta_d['total_capturas'] ?? 0);
                            ?>
                                <td class="hora-val-cell text-center font-monospace <?= !empty($hora_d) ? 'fw-bold text-dark' : 'text-muted' ?>">
                                    <?= !empty($hora_d) ? htmlspecialchars($hora_d) : '-' ?>
                                    <?php if ($tot_cap_d > 1): ?>
                                        <span class="badge bg-info text-dark d-block mx-auto mt-1" style="font-size: 8.5px; width: fit-content;">
                                            <?= $tot_cap_d ?> lecturas
                                        </span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Fila 2: Operador (Nómina) por Día de la Semana -->
                        <tr class="row-hora-registro">
                            <td class="td-param fw-bold">
                                <i class="bi bi-person-badge text-primary me-1"></i> Operador (Nómina)
                            </td>
                            <td class="col-rango-verde fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <td class="col-rango-amarillo fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <td class="col-rango-rojo fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <?php foreach ($dias_semana_chiller as $f_dia => $d_info): 
                                $op_d = $matriz_semanal_chiller['meta'][$f_dia]['operador'] ?? '';
                            ?>
                                <td style="background-color: #ffffff; text-align: center;">
                                    <?php if (!empty($op_d)): ?>
                                        <span class="badge bg-dark text-white rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                                            <?= htmlspecialchars($op_d) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 0.75rem;">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Filas de Parámetros Diarios de Lunes a Domingo -->
                        <?php foreach ($params_diarios_chiller as $pid => $p): 
                            $rangos_desglosados = obtenerRangosDesglosados($p);
                        ?>
                        <tr>
                            <td class="td-param" title="<?= htmlspecialchars($p['nombre_parametro']) ?>">
                                <?= htmlspecialchars($p['nombre_parametro']) ?>
                            </td>
                            <td class="col-rango-verde" title="Rango Óptimo: <?= htmlspecialchars($rangos_desglosados['verde']) ?>">
                                <?= htmlspecialchars($rangos_desglosados['verde']) ?>
                            </td>
                            <td class="col-rango-amarillo" title="Rango de Alerta: <?= htmlspecialchars($rangos_desglosados['amarillo']) ?>">
                                <?= htmlspecialchars($rangos_desglosados['amarillo']) ?>
                            </td>
                            <td class="col-rango-rojo" title="Rango Crítico: <?= htmlspecialchars($rangos_desglosados['rojo']) ?>">
                                <?= htmlspecialchars($rangos_desglosados['rojo']) ?>
                            </td>

                            <?php foreach ($dias_semana_chiller as $f_dia => $d_info): 
                                $lectura = $matriz_semanal_chiller['valores'][$pid][$f_dia] ?? null;
                                $val = $lectura['val'] ?? null;

                                if ($val !== null && $val !== '') {
                                    $f_exacta = $lectura['fecha_exacta'] ?? null;
                                    $p_eval = obtenerParametroEnFecha($p, $f_exacta, $historial_rangos);
                                    $es_rango_ant = !empty($p_eval['es_rango_anterior']);
                                    $tooltip_ant = $p_eval['info_cambio']['tooltip'] ?? '';
                                    $badge_class = getSemaforoHorizontal($val, $p_eval);

                                    if (strpos($badge_class, 'bg-success') !== false) $cCls = 'cell-matrix-verde';
                                    elseif (strpos($badge_class, 'bg-warning') !== false) $cCls = 'cell-matrix-amarillo';
                                    elseif (strpos($badge_class, 'bg-danger') !== false) $cCls = 'cell-matrix-rojo';
                                    elseif (strpos($badge_class, 'badge-lavado') !== false) $cCls = 'cell-matrix-lavado';
                                    else $cCls = 'cell-matrix-gris';

                                    $mostrar = is_numeric($val) ? (floatval($val) == intval($val) ? number_format(floatval($val), 0) : number_format(floatval($val), 2)) : htmlspecialchars($val);

                                    $obs_modal = $lectura['obs'] ?? 'Sin observaciones';
                                    if (!empty($lectura['historial_dia']) && count($lectura['historial_dia']) > 1) {
                                        $obs_modal .= "\n\nLecturas del día (" . count($lectura['historial_dia']) . "):\n- " . implode("\n- ", $lectura['historial_dia']);
                                    }

                                    $pNomJson = htmlspecialchars(json_encode($p['nombre_parametro'] . ' (' . $d_info['nombre'] . ' ' . $d_info['corto'] . ')'), ENT_QUOTES, 'UTF-8');
                                    $valJson = htmlspecialchars(json_encode($mostrar), ENT_QUOTES, 'UTF-8');
                                    $badgeClassJson = htmlspecialchars(json_encode($cCls), ENT_QUOTES, 'UTF-8');
                                    $fExactaJson = htmlspecialchars(json_encode($f_exacta ? date("d/m/Y h:i:s A", strtotime($f_exacta)) : '-'), ENT_QUOTES, 'UTF-8');
                                    $nomJson = htmlspecialchars(json_encode($lectura['operador'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                                    $loteJson = htmlspecialchars(json_encode($lectura['lote'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                    $obsJson = htmlspecialchars(json_encode($obs_modal), ENT_QUOTES, 'UTF-8');
                                    $rangoMetaJson = htmlspecialchars(json_encode("Verde: {$rangos_desglosados['verde']} | Amarillo: {$rangos_desglosados['amarillo']} | Rojo: {$rangos_desglosados['rojo']}"), ENT_QUOTES, 'UTF-8');
                            ?>
                                <td class="cell-matrix <?= $cCls ?>"
                                    onclick='abrirModalDetalle(<?= $pNomJson ?>, <?= $valJson ?>, <?= $badgeClassJson ?>, <?= $fExactaJson ?>, <?= $nomJson ?>, <?= $loteJson ?>, <?= $obsJson ?>, <?= $rangoMetaJson ?>)'
                                    title="<?= htmlspecialchars($p['nombre_parametro']) ?> (<?= $d_info['nombre'] ?> <?= $d_info['corto'] ?>): <?= $mostrar ?>">
                                    <?= $mostrar ?>
                                    <?php if ($es_rango_ant): ?>
                                        <span class="badge-rango-ant d-block" title="<?= htmlspecialchars($tooltip_ant) ?>">R. ant</span>
                                    <?php endif; ?>
                                </td>
                            <?php } else { ?>
                                <td class="cell-matrix cell-matrix-empty">-</td>
                            <?php } ?>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td class="td-footer-label"><i class="bi bi-chat-text text-secondary me-1"></i> Observaciones</td>
                            <td class="col-rango-verde fw-bold" style="opacity: 0.85;">--</td>
                            <td class="col-rango-amarillo fw-bold" style="opacity: 0.85;">--</td>
                            <td class="col-rango-rojo fw-bold" style="opacity: 0.85;">--</td>
                            <?php foreach ($dias_semana_chiller as $f_dia => $d_info): 
                                $ob_d = $matriz_semanal_chiller['meta'][$f_dia]['obs'] ?? '';
                                $op_d_obs = $matriz_semanal_chiller['meta'][$f_dia]['operador'] ?? '';
                            ?>
                                <?php if (!empty($ob_d)): 
                                    $titObsS = htmlspecialchars(json_encode($equipo_nombre), ENT_QUOTES, 'UTF-8');
                                    $subObsS = htmlspecialchars(json_encode($d_info['nombre'] . " " . date('d/m/Y', strtotime($f_dia))), ENT_QUOTES, 'UTF-8');
                                    $txtObsS = htmlspecialchars(json_encode($ob_d), ENT_QUOTES, 'UTF-8');
                                    $nomObsS = htmlspecialchars(json_encode($op_d_obs ?: 'N/A'), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <td class="cell-obs-interactive" style="background-color: #fffbeb !important;"
                                        onclick='abrirModalObservacion(<?= $titObsS ?>, <?= $subObsS ?>, <?= $txtObsS ?>, <?= $nomObsS ?>)'
                                        title="Clic para ver observación completa">
                                        <span class="btn-obs-pill">
                                            <i class="bi bi-chat-left-text-fill text-warning"></i> Ver nota
                                        </span>
                                    </td>
                                <?php else: ?>
                                    <td class="text-muted" style="background-color: #ffffff; font-size: 0.75rem;">-</td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- 2.B) TABLA DE PARÁMETROS Y RUTINAS DE FRECUENCIA SEMANAL -->
            <?php if (!empty($params_semanales_chiller)): ?>
            <div class="title-equipo" style="border-left-color: #7c3aed;">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span><strong>Banco de Chillers:</strong> <?= in_array($equipo_id, [81, 91]) ? 'Chillers (1 al 4)' : 'Chillers (5 al 7)' ?></span>
                    <span class="vr mx-1"></span>
                    <span class="badge text-white px-3 py-1 fs-6" style="background-color: #7c3aed;">
                        <i class="bi bi-clipboard2-check me-1"></i> PARÁMETROS Y RUTINAS DE FRECUENCIA SEMANAL
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span><strong>Semana:</strong> <?= date('d/m/Y', strtotime($lunes_sem)) ?> al <?= date('d/m/Y', strtotime($domingo_sem)) ?></span>
                    <span class="badge bg-secondary text-white rounded-pill px-3 py-1 fw-bold">
                        <?= $rutinas_capturadas_sem ?> / <?= count($params_semanales_chiller) ?> Registrados
                    </span>
                </div>
            </div>

            <div class="table-container-responsive mb-4">
                <table class="table-matriz-turno">
                    <thead>
                        <tr class="header-navy" style="background-color: #311068 !important;">
                            <th class="th-param">Parámetro / Rutina Semanal</th>
                            <th style="width: 110px;">Categoría</th>
                            <th class="th-rango-verde" title="Rango Óptimo">Verde</th>
                            <th class="th-rango-amarillo" title="Rango de Alerta">Amarillo</th>
                            <th class="th-rango-rojo" title="Rango Crítico">Rojo</th>
                            <th style="width: 150px;">Valor / Estado Semanal</th>
                            <th style="width: 165px;">Fecha y Hora de Captura</th>
                            <th style="width: 130px;">Operador (Nómina)</th>
                            <th style="min-width: 180px;">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($params_semanales_chiller as $pid => $p_sem): 
                            $es_rutina_sem = (strtoupper(trim($p_sem['grupo'] ?? '')) === 'RUTINAS' || stripos($p_sem['nombre_parametro'], 'Limpieza') !== false);
                            $rangos_sem = obtenerRangosDesglosados($p_sem);
                            $lec_sem = $lecturas_rutinas_semanales[$pid] ?? null;
                            $val_sem = $lec_sem['val'] ?? null;
                        ?>
                        <tr>
                            <td class="td-param" title="<?= htmlspecialchars($p_sem['nombre_parametro']) ?>">
                                <span class="fw-bold"><?= htmlspecialchars($p_sem['nombre_parametro']) ?></span>
                            </td>
                            <td class="text-center" style="background-color: #f8fafc; font-size: 10px; font-weight: 700;">
                                <?php if ($es_rutina_sem): ?>
                                    <span class="badge bg-info text-dark">RUTINA</span>
                                <?php else: ?>
                                    <span class="badge bg-primary">PARÁMETRO</span>
                                <?php endif; ?>
                            </td>
                            <td class="col-rango-verde">
                                <?= $es_rutina_sem ? 'REALIZADA' : htmlspecialchars($rangos_sem['verde']) ?>
                            </td>
                            <td class="col-rango-amarillo">
                                <?= $es_rutina_sem ? '--' : htmlspecialchars($rangos_sem['amarillo']) ?>
                            </td>
                            <td class="col-rango-rojo">
                                <?= $es_rutina_sem ? 'NO REALIZADA' : htmlspecialchars($rangos_sem['rojo']) ?>
                            </td>

                            <?php if ($val_sem !== null && $val_sem !== ''): 
                                $f_ex_sem = $lec_sem['fecha_exacta'] ?? null;
                                if ($es_rutina_sem) {
                                    $cumplida = (in_array(strtoupper(trim((string)$val_sem)), ['1', 'SI', 'SÍ', 'OK', 'REALIZADA', 'CUMPLIDA', 'TRUE']));
                                    $cClsSem = $cumplida ? 'cell-matrix-verde' : 'cell-matrix-rojo';
                                    $mostrarSem = $cumplida ? 'REALIZADA' : 'NO REALIZADA';
                                } else {
                                    $p_eval_s = obtenerParametroEnFecha($p_sem, $f_ex_sem, $historial_rangos);
                                    $badge_s = getSemaforoHorizontal($val_sem, $p_eval_s);
                                    if (strpos($badge_s, 'bg-success') !== false) $cClsSem = 'cell-matrix-verde';
                                    elseif (strpos($badge_s, 'bg-warning') !== false) $cClsSem = 'cell-matrix-amarillo';
                                    elseif (strpos($badge_s, 'bg-danger') !== false) $cClsSem = 'cell-matrix-rojo';
                                    elseif (strpos($badge_s, 'badge-lavado') !== false) $cClsSem = 'cell-matrix-lavado';
                                    else $cClsSem = 'cell-matrix-gris';

                                    $mostrarSem = is_numeric($val_sem) ? (floatval($val_sem) == intval($val_sem) ? number_format(floatval($val_sem), 0) : number_format(floatval($val_sem), 2)) : htmlspecialchars($val_sem);
                                }

                                $pNomJson = htmlspecialchars(json_encode($p_sem['nombre_parametro']), ENT_QUOTES, 'UTF-8');
                                $valJson = htmlspecialchars(json_encode($mostrarSem), ENT_QUOTES, 'UTF-8');
                                $badgeClassJson = htmlspecialchars(json_encode($cClsSem), ENT_QUOTES, 'UTF-8');
                                $fExactaJson = htmlspecialchars(json_encode($f_ex_sem ? date("d/m/Y h:i:s A", strtotime($f_ex_sem)) : '-'), ENT_QUOTES, 'UTF-8');
                                $nomJson = htmlspecialchars(json_encode($lec_sem['operador'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                                $loteJson = htmlspecialchars(json_encode($lec_sem['lote'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                $obsJson = htmlspecialchars(json_encode($lec_sem['obs'] ?? 'Sin observaciones'), ENT_QUOTES, 'UTF-8');
                                $rangoMetaJson = htmlspecialchars(json_encode($es_rutina_sem ? "Verde: REALIZADA | Rojo: NO REALIZADA" : "Verde: {$rangos_sem['verde']} | Amarillo: {$rangos_sem['amarillo']} | Rojo: {$rangos_sem['rojo']}"), ENT_QUOTES, 'UTF-8');
                            ?>
                                <td class="cell-matrix <?= $cClsSem ?>"
                                    onclick='abrirModalDetalle(<?= $pNomJson ?>, <?= $valJson ?>, <?= $badgeClassJson ?>, <?= $fExactaJson ?>, <?= $nomJson ?>, <?= $loteJson ?>, <?= $obsJson ?>, <?= $rangoMetaJson ?>)'>
                                    <?= $mostrarSem ?>
                                </td>
                                <td class="text-center font-monospace fw-bold text-dark" style="background-color: #ffffff; font-size: 11px;">
                                    <?= $f_ex_sem ? date('d/m/Y H:i:s', strtotime($f_ex_sem)) : '-' ?>
                                </td>
                                <td class="text-center" style="background-color: #ffffff;">
                                    <?php if (!empty($lec_sem['operador'])): ?>
                                        <span class="badge bg-dark text-white rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                                            <?= htmlspecialchars($lec_sem['operador']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center px-2" style="background-color: #ffffff;">
                                    <?php if (!empty($lec_sem['obs'])): 
                                        $titObsRut = htmlspecialchars(json_encode($p_sem['nombre_parametro']), ENT_QUOTES, 'UTF-8');
                                        $subObsRut = htmlspecialchars(json_encode($f_ex_sem ? date('d/m/Y H:i:s', strtotime($f_ex_sem)) : "Semana $lunes_sem al $domingo_sem"), ENT_QUOTES, 'UTF-8');
                                        $txtObsRut = htmlspecialchars(json_encode($lec_sem['obs']), ENT_QUOTES, 'UTF-8');
                                        $nomObsRut = htmlspecialchars(json_encode($lec_sem['operador'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                                    ?>
                                        <span class="btn-obs-pill" onclick='abrirModalObservacion(<?= $titObsRut ?>, <?= $subObsRut ?>, <?= $txtObsRut ?>, <?= $nomObsRut ?>)' title="Clic para ver observación completa">
                                            <i class="bi bi-chat-left-text-fill text-warning"></i> Ver nota
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php else: ?>
                                <?php if ($es_rutina_sem): ?>
                                    <td class="cell-matrix cell-matrix-rojo">NO REALIZADA</td>
                                <?php else: ?>
                                    <td class="cell-matrix cell-matrix-empty">-</td>
                                <?php endif; ?>
                                <td class="text-center text-muted" style="background-color: #ffffff;">-</td>
                                <td class="text-center text-muted" style="background-color: #ffffff;">-</td>
                                <td class="text-center text-muted" style="background-color: #ffffff;">-</td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- MATRIZ DE EQUIPOS REGULARES (HORA POR HORA CON VERDE / AMARILLO / ROJO) -->
        <?php foreach ($turnos_a_mostrar as $t_key): 
            $t_info = $turnos_config[$t_key];
            $t_bloques = $t_info['bloques'];
            $total_en_turno = $conteo_lecturas_turno[$t_key] ?? 0;
        ?>
        <div class="mb-4">
            <!-- Barra de Equipo y Turno (Formato Idéntico a Imagen 2) -->
            <div class="title-equipo" style="<?= $t_key === 'nocturno' ? 'border-left-color: #3b82f6;' : 'border-left-color: #0284c7;' ?>">
                <div class="d-flex align-items-center gap-2">
                    <span><strong>Equipo:</strong> <?= htmlspecialchars($equipo_nombre) ?></span>
                    <span class="vr mx-1"></span>
                    <span class="badge <?= $t_key === 'nocturno' ? 'bg-primary text-white' : 'bg-warning text-dark' ?> px-3 py-1 fs-6">
                        <i class="bi <?= $t_info['icono'] ?> me-1"></i><?= strtoupper($t_info['titulo']) ?>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span><strong>Fecha Operativa:</strong> <?= date('Y-m-d', strtotime($fecha_filtro)) ?></span>
                    <span class="badge bg-secondary text-white rounded-pill px-3 py-1 fw-bold"><?= $total_en_turno ?> Capturas</span>
                </div>
            </div>

            <!-- Tabla Matricial del Turno -->
            <div class="table-container-responsive mb-4">
                <table class="table-matriz-turno">
                    <thead>
                        <tr class="header-navy" style="<?= $t_key === 'nocturno' ? 'background-color: #0f172a !important;' : '' ?>">
                            <th class="th-param">Parámetro Operativo</th>
                            <th class="th-rango-verde" title="Rango Óptimo">Verde</th>
                            <th class="th-rango-amarillo" title="Rango de Alerta">Amarillo</th>
                            <th class="th-rango-rojo" title="Rango Crítico">Rojo</th>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                $hNum = intval(substr($b_key, 0, 2));
                                $esDiaSig = ($t_key === 'nocturno' && $hNum < 12);
                            ?>
                                <th class="th-hora" style="vertical-align: middle;">
                                    <div class="fw-bold" style="font-size: 11px;"><?= $b_data['label'] ?></div>
                                    <div style="font-size: 8px; font-weight: 600; opacity: 0.9; color: <?= $esDiaSig ? '#93c5fd' : '#cbd5e1' ?>;">
                                        <?= $b_data['sub'] ?>
                                    </div>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Fila 1: Hora del Registro (Exacta) -->
                        <tr class="row-hora-registro">
                            <td class="td-param fw-bold">
                                <i class="bi bi-clock-history me-1 text-primary"></i> Hora del Registro
                            </td>
                            <td class="col-rango-verde fw-bold" style="opacity: 0.85;" style="font-size: 11px;">--</td>
                            <td class="col-rango-amarillo fw-bold" style="opacity: 0.85;" style="font-size: 11px;">--</td>
                            <td class="col-rango-rojo fw-bold" style="opacity: 0.85;" style="font-size: 11px;">--</td>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                $f_exacta_bloque = $matriz[$t_key]['meta'][$b_key]['fecha_exacta'] ?? null;
                                if (!$f_exacta_bloque && !empty($matriz[$t_key]['valores'])) {
                                    foreach ($matriz[$t_key]['valores'] as $p_vals) {
                                        if (!empty($p_vals[$b_key]['fecha_exacta'])) {
                                            $f_exacta_bloque = $p_vals[$b_key]['fecha_exacta'];
                                            break;
                                        }
                                    }
                                }
                                $hora_str = $f_exacta_bloque ? date('H:i:s', strtotime($f_exacta_bloque)) : '';
                            ?>
                                <td class="hora-val-cell text-center font-monospace <?= $f_exacta_bloque ? 'fw-bold text-dark' : 'text-muted' ?>">
                                    <?= $hora_str ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Fila 2: Operador (Nómina) en la parte superior -->
                        <tr class="row-hora-registro">
                            <td class="td-param fw-bold">
                                <i class="bi bi-person-badge text-primary me-1"></i> Operador (Nómina)
                            </td>
                            <td class="col-rango-verde fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <td class="col-rango-amarillo fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <td class="col-rango-rojo fw-bold" style="opacity: 0.85; font-size: 11px;">--</td>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                $op = $matriz[$t_key]['meta'][$b_key]['operador'] ?? '';
                            ?>
                                <td style="background-color: #ffffff; text-align: center;">
                                    <?php if (!empty($op)): ?>
                                        <span class="badge bg-dark text-white rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                                            <?= htmlspecialchars($op) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 0.75rem;">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Filas de Parámetros con Desglose Verde, Amarillo, Rojo -->
                        <?php if (!empty($params_lista)): ?>
                            <?php foreach ($params_lista as $pid => $p): 
                                $rangos_desglosados = obtenerRangosDesglosados($p);
                            ?>
                            <tr>
                                <!-- Columna de Parámetro (Fija a la izquierda) -->
                                <td class="td-param" title="<?= htmlspecialchars($p['nombre_parametro']) ?>">
                                    <?= htmlspecialchars($p['nombre_parametro']) ?>
                                </td>

                                <!-- Columnas de Rangos Verde, Amarillo, Rojo -->
                                <td class="col-rango-verde" title="Rango Óptimo: <?= htmlspecialchars($rangos_desglosados['verde']) ?>">
                                    <?= htmlspecialchars($rangos_desglosados['verde']) ?>
                                </td>
                                <td class="col-rango-amarillo" title="Rango de Alerta: <?= htmlspecialchars($rangos_desglosados['amarillo']) ?>">
                                    <?= htmlspecialchars($rangos_desglosados['amarillo']) ?>
                                </td>
                                <td class="col-rango-rojo" title="Rango Crítico: <?= htmlspecialchars($rangos_desglosados['rojo']) ?>">
                                    <?= htmlspecialchars($rangos_desglosados['rojo']) ?>
                                </td>

                                <!-- Celdas de las horas del Turno (Pintadas completas como Imagen 2) -->
                                <?php foreach ($t_bloques as $b_key => $b_data): 
                                    $lectura = $matriz[$t_key]['valores'][$pid][$b_key] ?? null;
                                    $val = $lectura['val'] ?? null;
                                    
                                    if ($val !== null && $val !== '') {
                                        $f_exacta = $lectura['fecha_exacta'] ?? null;
                                        $p_eval = obtenerParametroEnFecha($p, $f_exacta, $historial_rangos);
                                        $es_rango_ant = !empty($p_eval['es_rango_anterior']);
                                        $tooltip_ant = $p_eval['info_cambio']['tooltip'] ?? '';
                                        $badge_class = getSemaforoHorizontal($val, $p_eval);

                                        if (strpos($badge_class, 'bg-success') !== false) $cCls = 'cell-matrix-verde';
                                        elseif (strpos($badge_class, 'bg-warning') !== false) $cCls = 'cell-matrix-amarillo';
                                        elseif (strpos($badge_class, 'bg-danger') !== false) $cCls = 'cell-matrix-rojo';
                                        elseif (strpos($badge_class, 'badge-lavado') !== false) $cCls = 'cell-matrix-lavado';
                                        else $cCls = 'cell-matrix-gris';

                                        $mostrar = is_numeric($val) ? (floatval($val) == intval($val) ? number_format(floatval($val), 0) : number_format(floatval($val), 2)) : htmlspecialchars($val);

                                        // Atributos de clic para abrir el modal con el detalle completo
                                        $pNomJson = htmlspecialchars(json_encode($p['nombre_parametro']), ENT_QUOTES, 'UTF-8');
                                        $valJson = htmlspecialchars(json_encode($mostrar), ENT_QUOTES, 'UTF-8');
                                        $badgeClassJson = htmlspecialchars(json_encode($cCls), ENT_QUOTES, 'UTF-8');
                                        $fExactaJson = htmlspecialchars(json_encode($f_exacta ? date("d/m/Y h:i:s A", strtotime($f_exacta)) : '-'), ENT_QUOTES, 'UTF-8');
                                        $nomJson = htmlspecialchars(json_encode($lectura['operador'] ?? 'N/A'), ENT_QUOTES, 'UTF-8');
                                        $loteJson = htmlspecialchars(json_encode($lectura['lote'] ?? '-'), ENT_QUOTES, 'UTF-8');
                                        $obsJson = htmlspecialchars(json_encode($lectura['obs'] ?? 'Sin observaciones'), ENT_QUOTES, 'UTF-8');
                                        $rangoMetaJson = htmlspecialchars(json_encode("Verde: {$rangos_desglosados['verde']} | Amarillo: {$rangos_desglosados['amarillo']} | Rojo: {$rangos_desglosados['rojo']}"), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <td class="cell-matrix <?= $cCls ?>" 
                                        onclick='abrirModalDetalle(<?= $pNomJson ?>, <?= $valJson ?>, <?= $badgeClassJson ?>, <?= $fExactaJson ?>, <?= $nomJson ?>, <?= $loteJson ?>, <?= $obsJson ?>, <?= $rangoMetaJson ?>)'
                                        title="<?= htmlspecialchars($p['nombre_parametro']) ?>: <?= $mostrar ?> (Clic para ver detalle)">
                                        <?= $mostrar ?>
                                        <?php if ($es_rango_ant): ?>
                                            <span class="badge-rango-ant d-block" title="<?= htmlspecialchars($tooltip_ant) ?>">R. ant</span>
                                        <?php endif; ?>
                                    </td>
                                <?php } else { ?>
                                    <td class="cell-matrix cell-matrix-empty">-</td>
                                <?php } ?>
                                <?php endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= count($t_bloques) + 4 ?>" class="py-5 text-muted fs-5">
                                    <i class="bi bi-info-circle fs-2 d-block mb-2 text-secondary"></i>
                                    Este equipo no tiene parámetros configurados en el catálogo.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>

                    <!-- Pie de Tabla con Resumen de Lote y Observaciones por Bloque -->
                    <tfoot>
                        <!-- Fila Lote -->
                        <tr>
                            <td class="td-footer-label"><i class="bi bi-tag text-success me-1"></i> Lote Capturado</td>
                            <td class="col-rango-verde fw-bold" style="opacity: 0.85;">--</td>
                            <td class="col-rango-amarillo fw-bold" style="opacity: 0.85;">--</td>
                            <td class="col-rango-rojo fw-bold" style="opacity: 0.85;">--</td>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                $lt = $matriz[$t_key]['meta'][$b_key]['lote'] ?? '';
                            ?>
                                <td style="background-color: #ffffff;">
                                    <?php if (!empty($lt)): ?>
                                        <span class="badge bg-primary text-white rounded-pill px-2 py-1" style="font-size: 0.72rem;">
                                            <?= htmlspecialchars($lt) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size: 0.75rem;">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Fila Observaciones -->
                        <tr>
                            <td class="td-footer-label"><i class="bi bi-chat-text text-secondary me-1"></i> Observaciones</td>
                            <td class="col-rango-verde fw-bold" style="opacity: 0.85;">--</td>
                            <td class="col-rango-amarillo fw-bold" style="opacity: 0.85;">--</td>
                            <td class="col-rango-rojo fw-bold" style="opacity: 0.85;">--</td>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                $ob = $matriz[$t_key]['meta'][$b_key]['obs'] ?? '';
                                $op_reg_obs = $matriz[$t_key]['meta'][$b_key]['operador'] ?? '';
                            ?>
                                <?php if (!empty($ob)): 
                                    $titObsReg = htmlspecialchars(json_encode($equipo_nombre), ENT_QUOTES, 'UTF-8');
                                    $subObsReg = htmlspecialchars(json_encode("Fecha: " . date('d/m/Y', strtotime($fecha_filtro)) . " · Bloque: $b_key"), ENT_QUOTES, 'UTF-8');
                                    $txtObsReg = htmlspecialchars(json_encode($ob), ENT_QUOTES, 'UTF-8');
                                    $nomObsReg = htmlspecialchars(json_encode($op_reg_obs ?: 'N/A'), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <td class="cell-obs-interactive" style="background-color: #fffbeb !important;" 
                                        onclick='abrirModalObservacion(<?= $titObsReg ?>, <?= $subObsReg ?>, <?= $txtObsReg ?>, <?= $nomObsReg ?>)' 
                                        title="Clic para ver observación completa">
                                        <span class="btn-obs-pill">
                                            <i class="bi bi-chat-left-text-fill text-warning"></i> Ver nota
                                        </span>
                                    </td>
                                <?php else: ?>
                                    <td class="text-muted" style="background-color: #ffffff; font-size: 0.75rem;">-</td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

</div>

<!-- MODAL PARA VER DETALLE COMPLETO DE CUALQUIER LECTURA -->
<div class="modal fade" id="modalDetalleLectura" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="modalParamNombre">Detalle del Parámetro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-3">
                    <span class="modal-badge-grande shadow-sm" id="modalValorBadge">-</span>
                </div>
                <div class="row g-2 text-start mt-3 p-3 bg-light rounded border">
                    <div class="col-12">
                        <span class="text-muted small d-block">Rangos Operativos:</span>
                        <strong class="text-dark" id="modalRangoMeta">-</strong>
                    </div>
                    <div class="col-6 mt-2">
                        <span class="text-muted small d-block">Fecha y Hora Exacta:</span>
                        <strong class="text-dark" id="modalFechaHora">-</strong>
                    </div>
                    <div class="col-6 mt-2">
                        <span class="text-muted small d-block">Nómina del Operador:</span>
                        <strong class="text-dark" id="modalNomina">-</strong>
                    </div>
                    <div class="col-12 mt-2">
                        <span class="text-muted small d-block">Lote / Datos Asociados:</span>
                        <strong class="text-dark" id="modalLote">-</strong>
                    </div>
                    <div class="col-12 mt-3 pt-2 border-top">
                        <span class="text-muted small d-block">Notas u Observaciones:</span>
                        <p class="m-0 text-dark" id="modalObservaciones" style="white-space: pre-wrap;">-</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary btn-sm fw-bold px-3" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EXCLUSIVO PARA VER OBSERVACIONES DE FORMA LIMPIA Y ESTÉTICA -->
<div class="modal fade" id="modalObservacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header text-dark border-0" style="background-color: #fef3c7;">
                <div>
                    <h5 class="modal-title fw-bold mb-0 d-flex align-items-center gap-2" style="color: #92400e;">
                        <i class="bi bi-chat-left-text-fill text-warning fs-5"></i>
                        <span id="modalObsTitulo">Observaciones</span>
                    </h5>
                    <small class="fw-semibold" style="color: #b45309;" id="modalObsSubtitulo">-</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-white">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted small fw-bold"><i class="bi bi-person-badge me-1"></i>Operador (Nómina):</span>
                    <span class="badge bg-dark text-white rounded-pill px-3 py-1" id="modalObsNomina">N/A</span>
                </div>
                <div class="p-3 rounded-3 border" style="background-color: #fffbeb; border-color: #fde68a !important; max-height: 320px; overflow-y: auto;">
                    <div id="modalObsContenido" class="text-dark fw-semibold" style="font-size: 0.92rem; line-height: 1.5; white-space: pre-wrap;">-</div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0 py-2">
                <button type="button" class="btn btn-dark btn-sm fw-bold px-4 rounded-pill" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script>
function cambiarTurno(turno) {
    document.getElementById('inputTurno').value = turno;
    document.getElementById('formFiltro').submit();
}

function cambiarIntervalo(intervalo) {
    document.getElementById('inputIntervalo').value = intervalo;
    document.getElementById('formFiltro').submit();
}

function cambiarVistaChiller(vista) {
    const inp = document.getElementById('inputVistaChiller');
    if (inp) inp.value = vista;

    const bDiario = document.getElementById('bloqueChillerDiario');
    const bSemanal = document.getElementById('bloqueChillerSemanal');
    if (bDiario && bSemanal) {
        bDiario.style.display = (vista === 'diario' || vista === 'ambos') ? '' : 'none';
        bSemanal.style.display = (vista === 'semanal' || vista === 'ambos') ? '' : 'none';

        const btnD = document.getElementById('btnVistaChillerDiario');
        const btnS = document.getElementById('btnVistaChillerSemanal');
        const btnA = document.getElementById('btnVistaChillerAmbos');
        if (btnD) btnD.className = 'btn btn-filtro-custom ' + (vista === 'diario' ? 'btn-primary active fw-bold' : 'btn-outline-primary');
        if (btnS) btnS.className = 'btn btn-filtro-custom ' + (vista === 'semanal' ? 'btn-success active fw-bold' : 'btn-outline-success');
        if (btnA) btnA.className = 'btn btn-filtro-custom ' + (vista === 'ambos' ? 'btn-dark active fw-bold' : 'btn-outline-dark');

        try {
            const url = new URL(window.location.href);
            url.searchParams.set('vista_chiller', vista);
            window.history.replaceState({}, '', url.toString());
            document.querySelectorAll('a[href*="vista_chiller="]').forEach(a => {
                const aUrl = new URL(a.href, window.location.origin);
                aUrl.searchParams.set('vista_chiller', vista);
                a.href = aUrl.pathname + aUrl.search;
            });
        } catch (e) {}
    } else if (inp) {
        document.getElementById('formFiltro').submit();
    }
}

let modalDetalleBS = null;
function abrirModalDetalle(paramNombre, valor, semaforoClass, fechaHora, nomina, lote, obs, rangoMeta) {
    document.getElementById('modalParamNombre').textContent = paramNombre;
    const badge = document.getElementById('modalValorBadge');
    badge.textContent = valor;
    badge.className = 'modal-badge-grande shadow-sm ' + semaforoClass;
    
    document.getElementById('modalRangoMeta').textContent = rangoMeta || '-';
    document.getElementById('modalFechaHora').textContent = fechaHora || '-';
    document.getElementById('modalNomina').textContent = nomina || 'N/A';
    document.getElementById('modalLote').textContent = lote || '-';
    document.getElementById('modalObservaciones').textContent = obs || 'Sin observaciones registradas.';

    if (!modalDetalleBS) {
        modalDetalleBS = new bootstrap.Modal(document.getElementById('modalDetalleLectura'));
    }
    modalDetalleBS.show();
}

let modalObsBS = null;
function abrirModalObservacion(titulo, subtitulo, obsTexto, nomina) {
    document.getElementById('modalObsTitulo').textContent = titulo || 'Observaciones';
    document.getElementById('modalObsSubtitulo').textContent = subtitulo || '';
    document.getElementById('modalObsNomina').textContent = nomina || 'N/A';

    const contenedor = document.getElementById('modalObsContenido');
    const limpio = (obsTexto || '').trim();
    if (limpio.includes(' | ')) {
        const partes = limpio.split(' | ').map(p => p.trim()).filter(Boolean);
        contenedor.innerHTML = '';
        const ul = document.createElement('ul');
        ul.className = 'mb-0 ps-3';
        partes.forEach(item => {
            const li = document.createElement('li');
            li.className = 'mb-1';
            li.textContent = item;
            ul.appendChild(li);
        });
        contenedor.appendChild(ul);
    } else {
        contenedor.textContent = limpio || 'Sin observaciones registradas.';
    }

    if (!modalObsBS) {
        modalObsBS = new bootstrap.Modal(document.getElementById('modalObservacion'));
    }
    modalObsBS.show();
}

// Cierre automático de sesión tras 5 minutos DESACTIVADO temporalmente a petición
document.addEventListener('DOMContentLoaded', function() {
    /*
    const TIEMPO_LIMITE_INACTIVIDAD_MS = 5 * 60 * 1000;
    let temporizadorInactividad;

    function cerrarSesionPorInactividad() {
        window.location.href = 'acciones/logout.php?timeout=1';
    }

    function reiniciarTemporizadorInactividad() {
        clearTimeout(temporizadorInactividad);
        temporizadorInactividad = setTimeout(cerrarSesionPorInactividad, TIEMPO_LIMITE_INACTIVIDAD_MS);
    }

    ['mousemove', 'mousedown', 'keydown', 'touchstart', 'touchend', 'touchmove', 'scroll', 'click'].forEach(evento => {
        document.addEventListener(evento, reiniciarTemporizadorInactividad, { passive: true });
    });

    reiniciarTemporizadorInactividad();
    */

    const paramsUrl = new URLSearchParams(window.location.search);
    if (paramsUrl.get('print') === '1') {
        setTimeout(() => { window.print(); }, 350);
    }
});
</script>

</body>
</html>
