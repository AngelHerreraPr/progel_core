<?php
// progel_core/historial.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
date_default_timezone_set('America/Mazatlan');

include_once 'config/db.php';
include_once 'config/db_v2.php';
include_once 'includes/mapeo_v2.php';
include_once 'includes/rangos_historicos.php';

$historial_rangos = cargarHistorialRangos($conn);

// Recibimos qué equipo queremos revisar
$equipo_id = (int)($_GET['id'] ?? 0);

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

$fecha_filtro = isset($_GET['fecha']) && !empty($_GET['fecha']) ? $_GET['fecha'] : $fecha_sugerida;
$turno_filtro = isset($_GET['turno']) ? strtolower(trim($_GET['turno'])) : 'ambos';
if (!in_array($turno_filtro, ['diurno', 'nocturno', 'ambos'])) {
    $turno_filtro = 'ambos';
}

$es_membrana = ($equipo_id == 100);

// Frecuencia / Intervalo de columnas (1h = cada hora, 2h = cada 2 horas, auto = horas con datos)
$intervalo_filtro = isset($_GET['intervalo']) ? strtolower(trim($_GET['intervalo'])) : ($es_membrana ? '1h' : '2h');
if (!in_array($intervalo_filtro, ['1h', '2h', 'auto'])) {
    $intervalo_filtro = $es_membrana ? '1h' : '2h';
}

$fecha_hoy = date('Y-m-d');
$fecha_anterior = date('Y-m-d', strtotime($fecha_filtro . ' -1 day'));
$fecha_siguiente = date('Y-m-d', strtotime($fecha_filtro . ' +1 day'));

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
    
    $qM = mysqli_query($cMemb, "SELECT * FROM bitacora_membranas $whereMemb ORDER BY fecha_registro ASC, paquete_numero ASC");
    if ($qM && mysqli_num_rows($qM) > 0) {
        while ($rm = mysqli_fetch_assoc($qM)) {
            $lista_membranas[] = $rm;
        }
    } else {
        // Fallback por fecha simple si aún no hay registros con rango completo
        $fallback = mysqli_query($cMemb, "SELECT * FROM bitacora_membranas WHERE fecha = '$fecha_filtro' ORDER BY hora ASC, paquete_numero ASC");
        if ($fallback) {
            while ($rm = mysqli_fetch_assoc($fallback)) {
                $lista_membranas[] = $rm;
            }
        }
    }

    // Organizar en matriz por horas y paquetes
    foreach ($lista_membranas as $rm) {
        $fReg = !empty($rm['fecha_registro']) ? $rm['fecha_registro'] : ($rm['fecha'] . ' ' . $rm['hora']);
        $map = mapearBloqueTurno($fReg, $fecha_filtro, $intervalo_filtro);
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

if (!$es_membrana) {
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

    // B) Integración con Progel_coreV2 (Secadores, Concentradores, Chillers, Compresores)
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
    } elseif (in_array($equipo_id, [91, 92])) {
        $tabla_v2 = 'bitacora_chillers_normales';
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
        'nombre' => 'ETAPA 1 (Ultrafiltración)',
        'paquetes' => [1, 2, 3, 4, 9]
    ],
    2 => [
        'nombre' => 'ETAPA 2 (Ultrafiltración)',
        'paquetes' => [5, 6, 7, 8]
    ],
    3 => [
        'nombre' => 'ETAPA 3 (Ultrafiltración)',
        'paquetes' => [10, 11]
    ],
    4 => [
        'nombre' => 'ETAPA 4 (Ultrafiltración)',
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
            min-width: 210px !important;
            width: 215px !important;
            max-width: 245px !important;
        }
        
        /* Columnas de Rangos Verde, Amarillo, Rojo */
        .th-rango-verde {
            background-color: #2e8b57 !important;
            color: #ffffff !important;
            min-width: 105px !important;
            width: 110px !important;
            max-width: 130px !important;
            padding: 6px 8px !important;
            text-align: center !important;
            font-size: 0.78rem !important;
            font-weight: 800 !important;
        }
        .th-rango-amarillo {
            background-color: #e49a32 !important;
            color: #ffffff !important;
            min-width: 118px !important;
            width: 122px !important;
            max-width: 145px !important;
            padding: 6px 8px !important;
            text-align: center !important;
            font-size: 0.78rem !important;
            font-weight: 800 !important;
        }
        .th-rango-rojo {
            background-color: #c94436 !important;
            color: #ffffff !important;
            min-width: 118px !important;
            width: 122px !important;
            max-width: 145px !important;
            padding: 6px 8px !important;
            text-align: center !important;
            font-size: 0.78rem !important;
            font-weight: 800 !important;
        }
        .table-matriz-turno th, .table-matriz-turno td {
            border: 1px solid #475569 !important;
            padding: 5px 6px !important;
            text-align: center !important;
            vertical-align: middle !important;
            min-height: 35px !important;
            height: auto !important;
            white-space: nowrap !important;
        }
        .table-matriz-turno thead tr th {
            background-color: #1e293b !important;
            color: #ffffff !important;
            font-weight: 800 !important;
            font-size: 0.78rem !important;
            letter-spacing: 0.3px !important;
        }
        .table-matriz-turno thead th.th-hora {
            min-width: 68px;
        }

        /* Celdas del desglose de rangos en tbody */
        .col-rango-verde {
            text-align: center !important;
            background-color: #f0fdf4 !important;
            color: #166534 !important;
            font-weight: 800 !important;
            font-size: 0.75rem !important;
            line-height: 1.25 !important;
            min-width: 105px !important;
            width: 110px !important;
            max-width: 130px !important;
            padding: 5px 8px !important;
            border-left: 1px solid #86efac !important;
            border-right: 1px solid #86efac !important;
            white-space: normal !important;
            overflow: visible !important;
        }
        .col-rango-amarillo {
            text-align: center !important;
            background-color: #fffbeb !important;
            color: #92400e !important;
            font-weight: 800 !important;
            font-size: 0.74rem !important;
            line-height: 1.25 !important;
            min-width: 118px !important;
            width: 122px !important;
            max-width: 145px !important;
            padding: 5px 8px !important;
            border-left: 1px solid #fde68a !important;
            border-right: 1px solid #fde68a !important;
            white-space: normal !important;
            overflow: visible !important;
        }
        .col-rango-rojo {
            text-align: center !important;
            background-color: #fef2f2 !important;
            color: #991b1b !important;
            font-weight: 800 !important;
            font-size: 0.74rem !important;
            line-height: 1.25 !important;
            min-width: 118px !important;
            width: 122px !important;
            max-width: 145px !important;
            padding: 5px 8px !important;
            border-left: 1px solid #fca5a5 !important;
            border-right: 1px solid #fca5a5 !important;
            white-space: normal !important;
            overflow: visible !important;
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
            box-shadow: 2px 0 5px rgba(0,0,0,0.03);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            min-width: 210px !important;
            width: 215px !important;
            max-width: 245px !important;
        }
        .table-matriz-turno tbody tr:hover td.td-param {
            background-color: #f1f5f9;
        }
        .table-matriz-turno tfoot td {
            background-color: #ffffff;
            font-size: 0.76rem;
            font-weight: 600;
            color: #475569;
        }
        .table-matriz-turno tfoot td.td-footer-label {
            position: sticky;
            left: 0;
            z-index: 4;
            background-color: #f1f5f9;
            text-align: left !important;
            padding: 6px 12px !important;
            color: #0f172a;
            font-weight: 700;
        }
        /* CELDAS SÓLIDAS INDUSTRIALES (IMAGEN 2) */
        .cell-matrix {
            font-weight: 800 !important;
            font-size: 0.8rem !important;
            text-align: center !important;
            vertical-align: middle !important;
            cursor: pointer !important;
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
            background-color: #e49a32 !important;
            border: 1px solid #c47b1c !important;
            color: #ffffff !important;
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
        .cell-matrix-empty {
            background-color: #ffffff !important;
            color: #cbd5e1 !important;
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
        .seccion-etapa-header td {
            letter-spacing: 0.05em;
        }
    </style>
</head>
<body>

<div class="container-fluid py-4 px-3 px-md-4">
    
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
                $link_nueva_captura = "http://localhost/sis_extraccion/index.php?view=votators";
            } elseif (in_array($equipo_id, [21, 22, 23, 24])) {
                $link_nueva_captura = "http://localhost/sis_extraccion/index.php?view=secadores";
            }
            ?>
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
            <?php if ($es_membrana): ?>
                <input type="hidden" name="etapa" id="inputEtapa" value="<?= $filtro_etapa ?>">
            <?php endif; ?>
            
            <!-- Selector de Fecha con botones Anterior / Hoy / Siguiente -->
            <div class="col-xl-5 col-lg-5 col-md-6 col-12">
                <label class="form-label small fw-bold text-secondary mb-1">
                    <i class="bi bi-calendar-event me-1"></i> Fecha del Día Operativo:
                </label>
                <div class="input-group input-group-sm">
                    <a href="historial.php?id=<?= $equipo_id ?>&fecha=<?= $fecha_anterior ?>&turno=<?= $turno_filtro ?>&intervalo=<?= $intervalo_filtro ?><?= $es_membrana ? "&etapa=$filtro_etapa" : "" ?>" 
                       class="btn btn-outline-secondary fw-bold" title="Día Anterior">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                    <input type="date" name="fecha" value="<?= htmlspecialchars($fecha_filtro) ?>" 
                           class="form-control form-control-sm fw-bold text-center" 
                           onchange="document.getElementById('formFiltro').submit();">
                    <a href="historial.php?id=<?= $equipo_id ?>&fecha=<?= $fecha_siguiente ?>&turno=<?= $turno_filtro ?>&intervalo=<?= $intervalo_filtro ?><?= $es_membrana ? "&etapa=$filtro_etapa" : "" ?>" 
                       class="btn btn-outline-secondary fw-bold" title="Día Siguiente">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <a href="historial.php?id=<?= $equipo_id ?>&fecha=<?= $fecha_hoy ?>&turno=<?= $turno_filtro ?>&intervalo=<?= $intervalo_filtro ?><?= $es_membrana ? "&etapa=$filtro_etapa" : "" ?>" 
                       class="btn <?= $fecha_filtro == $fecha_hoy ? 'btn-primary' : 'btn-outline-primary' ?> fw-bold">
                        Hoy
                    </a>
                </div>
            </div>

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
        </form>
    </div>

    <!-- ===================================================================== -->
    <!-- RENDERIZADO EXCLUSIVO: MATRIZ POR TURNOS                             -->
    <!-- ===================================================================== -->

    <?php if ($es_membrana): ?>
        <!-- BANNER Y FILTRO DE ETAPAS PARA MEMBRANAS (ID 100) -->
        <div class="card shadow-sm border-0 mb-4 p-3 bg-white">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary fs-6 px-3 py-2 rounded-pill">
                        <i class="bi bi-funnel-fill me-1"></i> Membranas de Ultrafiltración
                    </span>
                    <span class="text-muted small">
                        Registros en Turno: <strong><?= count($lista_membranas) ?></strong>
                    </span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <label class="small fw-bold text-secondary">Filtrar por Etapa:</label>
                    <select class="form-select form-select-sm fw-bold" style="width: auto;" 
                            onchange="const url = new URL(window.location.href); url.searchParams.set('etapa', this.value); window.location.href = url.href;">
                        <option value="0" <?= ($filtro_etapa ?? 0) == 0 ? 'selected' : '' ?>>Todas las Etapas (1 a 4)</option>
                        <option value="1" <?= ($filtro_etapa ?? 0) == 1 ? 'selected' : '' ?>>Etapa 1 (Paquetes 1, 2, 3, 4, 9)</option>
                        <option value="2" <?= ($filtro_etapa ?? 0) == 2 ? 'selected' : '' ?>>Etapa 2 (Paquetes 5, 6, 7, 8)</option>
                        <option value="3" <?= ($filtro_etapa ?? 0) == 3 ? 'selected' : '' ?>>Etapa 3 (Paquetes 10, 11)</option>
                        <option value="4" <?= ($filtro_etapa ?? 0) == 4 ? 'selected' : '' ?>>Etapa 4 (Paquetes 12, 13)</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- MATRIZ MEMBRANAS (HORA POR HORA CON VERDE / AMARILLO / ROJO) -->
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
                    <span class="badge bg-secondary text-white rounded-pill px-3 py-1 fw-bold"><?= $total_en_turno ?> Paquetes</span>
                </div>
            </div>

            <!-- Tabla Matricial de Membranas -->
            <div class="table-container-responsive mb-4">
                <table class="table-matriz-turno">
                    <thead>
                        <tr class="header-navy" style="<?= $t_key === 'nocturno' ? 'background-color: #0f172a !important;' : '' ?>">
                            <th class="th-param">Parámetro / Paquete</th>
                            <th class="th-rango-verde" title="Rango Óptimo: ΔP < 1.20 bar">Verde</th>
                            <th class="th-rango-amarillo" title="Rango de Alerta: 1.20 - 1.80 bar">Amarillo</th>
                            <th class="th-rango-rojo" title="Rango Crítico: ≥ 1.80 bar o F.O.">Rojo</th>
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
                            <td class="col-rango-verde text-muted fw-bold" style="font-size: 11px;">--</td>
                            <td class="col-rango-amarillo text-muted fw-bold" style="font-size: 11px;">--</td>
                            <td class="col-rango-rojo text-muted fw-bold" style="font-size: 11px;">--</td>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                $h_exacta = $matriz_membranas[$t_key]['horas_exactas'][$b_key] ?? null;
                                $h_str = $h_exacta ? htmlspecialchars($h_exacta) : '';
                            ?>
                                <td class="hora-val-cell text-center font-monospace <?= $h_exacta ? 'fw-bold text-dark' : 'text-muted' ?>">
                                    <?= $h_str ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Secciones por Etapa -->
                        <?php 
                        $etapas_mostrar = ($filtro_etapa > 0) ? [$filtro_etapa => $etapas_membranas_def[$filtro_etapa]] : $etapas_membranas_def;
                        foreach ($etapas_mostrar as $e_num => $e_info): 
                        ?>
                            <tr class="seccion-etapa-header">
                                <td colspan="<?= count($t_bloques) + 4 ?>" class="fw-bold text-uppercase py-1 px-3" 
                                    style="background-color: #f1f5f9; color: #1e3a8a; font-size: 0.78rem; text-align: left; border-top: 2px solid #cbd5e1;">
                                    <i class="bi bi-diagram-3-fill me-1 text-primary"></i> <?= $e_info['nombre'] ?>
                                </td>
                            </tr>
                            
                            <?php foreach ($e_info['paquetes'] as $paq_num): ?>
                                <tr>
                                    <!-- Nombre del Paquete -->
                                    <td class="td-param">
                                        <span class="badge bg-secondary rounded-pill me-1" style="font-size: 0.68rem;">E<?= $e_num ?></span>
                                        Paquete <?= $paq_num ?> <small class="text-muted">(ΔP bar)</small>
                                    </td>
                                    <!-- Rangos Verde, Amarillo, Rojo -->
                                    <td class="col-rango-verde" title="Rango Óptimo: &lt; 1.20 bar">&lt; 1.20 bar</td>
                                    <td class="col-rango-amarillo" title="Rango de Alerta: 1.20 - 1.80 bar">1.20 - 1.80 bar</td>
                                    <td class="col-rango-rojo" title="Rango Crítico: ≥ 1.80 bar o Fuera de Operación">≥ 1.80 bar / F.O.</td>

                                    <!-- Horas del Turno (Celdas Pintadas Completas como Imagen 2) -->
                                    <?php foreach ($t_bloques as $b_key => $b_data): 
                                        $rm = $matriz_membranas[$t_key]['paquetes'][$paq_num][$b_key] ?? null;
                                        if ($rm):
                                            $est = strtoupper(trim($rm['estado'] ?? ''));
                                            $dp = $rm['delta_presion'];
                                            $fExacta = !empty($rm['fecha_registro']) ? $rm['fecha_registro'] : ($rm['fecha'] . ' ' . $rm['hora']);
                                            $fExactaStr = date("d/m/Y h:i:s A", strtotime($fExacta));

                                            if ($est === 'LAVADO') {
                                                $cCls = 'cell-matrix-lavado';
                                                $bText = 'LAVADO';
                                            } elseif ($est === 'MMT' || $est === 'MMTT') {
                                                $cCls = 'cell-matrix-amarillo';
                                                $bText = 'MMTT';
                                            } elseif ($est === 'FO' || $est === 'F.O.') {
                                                $cCls = 'cell-matrix-rojo';
                                                $bText = 'F.O.';
                                            } elseif ($dp !== null && is_numeric($dp)) {
                                                $dpVal = floatval($dp);
                                                if ($dpVal < 1.20) $cCls = 'cell-matrix-verde';
                                                elseif ($dpVal < 1.80) $cCls = 'cell-matrix-amarillo';
                                                else $cCls = 'cell-matrix-rojo';
                                                $bText = number_format($dpVal, 2);
                                            } else {
                                                $cCls = 'cell-matrix-gris';
                                                $bText = !empty($est) ? $est : 'OK';
                                            }

                                            $pEnt = ($rm['presion_entrada'] !== null && $rm['presion_entrada'] !== '') ? $rm['presion_entrada'] . ' bar' : '-';
                                            $pSal = ($rm['presion_salida'] !== null && $rm['presion_salida'] !== '') ? $rm['presion_salida'] . ' bar' : '-';
                                            $bEnt = ($rm['brix_entrada'] !== null && $rm['brix_entrada'] !== '') ? $rm['brix_entrada'] . ' °Bx' : '-';
                                            $bSal = ($rm['brix_salida'] !== null && $rm['brix_salida'] !== '') ? $rm['brix_salida'] . ' °Bx' : '-';
                                            $bPer = ($rm['brix_permeado'] !== null && $rm['brix_permeado'] !== '') ? $rm['brix_permeado'] . ' °Bx' : '-';
                                            $obsVal = !empty($rm['observaciones']) ? $rm['observaciones'] : 'Sin observaciones';
                                            $opVal = $rm['operador_nomina'] ?? 'N/A';
                                            $loteInfo = "P.Ent: $pEnt | P.Sal: $pSal | Brix Sal: $bSal";

                                            $pNomJson = htmlspecialchars(json_encode("Membranas Etapa $e_num - Paquete $paq_num"), ENT_QUOTES, 'UTF-8');
                                            $valJson = htmlspecialchars(json_encode("ΔP: $bText (Estado: $est)"), ENT_QUOTES, 'UTF-8');
                                            $bClassJson = htmlspecialchars(json_encode($cCls), ENT_QUOTES, 'UTF-8');
                                            $fExactaJson = htmlspecialchars(json_encode($fExactaStr), ENT_QUOTES, 'UTF-8');
                                            $nomJson = htmlspecialchars(json_encode($opVal), ENT_QUOTES, 'UTF-8');
                                            $loteJson = htmlspecialchars(json_encode($loteInfo), ENT_QUOTES, 'UTF-8');
                                            $obsJson = htmlspecialchars(json_encode($obsVal), ENT_QUOTES, 'UTF-8');
                                            $rangoJson = htmlspecialchars(json_encode("ΔP Óptimo: < 1.20 bar | Alerta: 1.20 - 1.80 bar | Crítico: ≥ 1.80 bar"), ENT_QUOTES, 'UTF-8');
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
                            <?php endforeach; ?>
                        <?php endforeach; ?>

                        <!-- Monitoreo de Concentración y Brix -->
                        <tr class="seccion-etapa-header">
                            <td colspan="<?= count($t_bloques) + 4 ?>" class="fw-bold text-uppercase py-1 px-3" 
                                style="background-color: #fef9c3; color: #854d0e; font-size: 0.78rem; text-align: left; border-top: 2px solid #fde047;">
                                <i class="bi bi-droplet-half me-1"></i> MONITOREO DE CONCENTRACIÓN Y BRIX
                            </td>
                        </tr>
                        
                        <!-- Brix Entrada -->
                        <tr>
                            <td class="td-param">Brix Entrada (°Bx)</td>
                            <td class="col-rango-verde" title="Rango de Entrada Recomendado">4.0 - 8.0 °Bx</td>
                            <td class="col-rango-amarillo">3.0 - 4.0 °Bx</td>
                            <td class="col-rango-rojo">&lt; 3.0 °Bx</td>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                $bx = $matriz_membranas[$t_key]['brix_entrada'][$b_key] ?? null;
                            ?>
                                <td>
                                    <?= ($bx !== null) ? '<span class="badge bg-light text-dark border fw-bold px-2 py-1">' . number_format($bx, 1) . '</span>' : '<span class="text-muted opacity-50">-</span>' ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Brix Salida -->
                        <tr>
                            <td class="td-param">Brix Salida / Concentrado (°Bx)</td>
                            <td class="col-rango-verde" title="Concentración Óptima">≥ 18.0 °Bx</td>
                            <td class="col-rango-amarillo">16.0 - 17.9 °Bx</td>
                            <td class="col-rango-rojo">&lt; 16.0 °Bx</td>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                $bx = $matriz_membranas[$t_key]['brix_salida'][$b_key] ?? null;
                                $cBrix = ($bx !== null) ? ($bx >= 18.0 ? 'cell-matrix-verde' : ($bx >= 16.0 ? 'cell-matrix-amarillo' : 'cell-matrix-rojo')) : '';
                            ?>
                                <?php if ($bx !== null): ?>
                                    <td class="cell-matrix <?= $cBrix ?>"><?= number_format($bx, 1) ?></td>
                                <?php else: ?>
                                    <td class="cell-matrix cell-matrix-empty">-</td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>

                        <!-- Brix Permeado -->
                        <tr>
                            <td class="td-param">Brix Permeado (°Bx)</td>
                            <td class="col-rango-verde" title="Permeado Óptimo">≤ 0.5 °Bx</td>
                            <td class="col-rango-amarillo">0.5 - 1.0 °Bx</td>
                            <td class="col-rango-rojo">&gt; 1.0 °Bx</td>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                $bx = $matriz_membranas[$t_key]['brix_permeado'][$b_key] ?? null;
                                $cBrix = ($bx !== null) ? ($bx <= 0.5 ? 'cell-matrix-verde' : ($bx <= 1.0 ? 'cell-matrix-amarillo' : 'cell-matrix-rojo')) : '';
                            ?>
                                <?php if ($bx !== null): ?>
                                    <td class="cell-matrix <?= $cBrix ?>"><?= number_format($bx, 1) ?></td>
                                <?php else: ?>
                                    <td class="cell-matrix cell-matrix-empty">-</td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>

                    <!-- Pie de Tabla con Resumen de Operador y Observaciones -->
                    <tfoot>
                        <!-- Fila Nómina / Operador -->
                        <tr>
                            <td class="td-footer-label"><i class="bi bi-person-badge text-primary me-1"></i> Operador (Nómina)</td>
                            <td class="col-rango-verde text-muted fw-bold">--</td>
                            <td class="col-rango-amarillo text-muted fw-bold">--</td>
                            <td class="col-rango-rojo text-muted fw-bold">--</td>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                $op = $matriz_membranas[$t_key]['meta'][$b_key]['operador'] ?? '';
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

                        <!-- Fila Observaciones -->
                        <tr>
                            <td class="td-footer-label"><i class="bi bi-chat-text text-secondary me-1"></i> Observaciones</td>
                            <td class="col-rango-verde text-muted fw-bold">--</td>
                            <td class="col-rango-amarillo text-muted fw-bold">--</td>
                            <td class="col-rango-rojo text-muted fw-bold">--</td>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                $ob = $matriz_membranas[$t_key]['meta'][$b_key]['obs'] ?? '';
                            ?>
                                <?php if (!empty($ob)): ?>
                                    <td class="cell-obs-interactive" style="background-color: #fffbeb !important; color: #92400e !important;" 
                                        onclick="alert('Observación de Membranas (<?= $b_key ?>):\n\n<?= addslashes(htmlspecialchars($ob)) ?>')" 
                                        title="<?= htmlspecialchars($ob) ?>">
                                        <div class="d-flex align-items-center justify-content-center gap-1" style="max-width: 100%; overflow: hidden;">
                                            <i class="bi bi-chat-left-text-fill text-warning flex-shrink-0" style="font-size: 10px;"></i>
                                            <span class="text-truncate" style="font-size: 9.5px;"><?= htmlspecialchars($ob) ?></span>
                                        </div>
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
                            <td class="col-rango-verde text-muted fw-bold" style="font-size: 11px;">--</td>
                            <td class="col-rango-amarillo text-muted fw-bold" style="font-size: 11px;">--</td>
                            <td class="col-rango-rojo text-muted fw-bold" style="font-size: 11px;">--</td>
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

                        <!-- Filas de Parámetros con Desglose Verde, Amarillo, Rojo -->
                        <?php if (!empty($params_lista)): ?>
                            <?php foreach ($params_lista as $pid => $p): 
                                $rangos_desglosados = obtenerRangosDesglosados($p);
                            ?>
                            <tr>
                                <!-- Columna de Parámetro (Fija a la izquierda) -->
                                <td class="td-param" title="<?= htmlspecialchars($p['nombre_parametro']) ?>">
                                    <span class="text-truncate d-inline-block" style="max-width: 215px;">
                                        <?= htmlspecialchars($p['nombre_parametro']) ?>
                                    </span>
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
                                        $badgeClassJson = htmlspecialchars(json_encode($badge_class), ENT_QUOTES, 'UTF-8');
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

                    <!-- Pie de Tabla con Resumen de Operador, Lote y Observaciones por Bloque -->
                    <tfoot>
                        <!-- Fila Nómina / Operador -->
                        <tr>
                            <td class="td-footer-label"><i class="bi bi-person-badge text-primary me-1"></i> Operador (Nómina)</td>
                            <td class="col-rango-verde text-muted fw-bold">--</td>
                            <td class="col-rango-amarillo text-muted fw-bold">--</td>
                            <td class="col-rango-rojo text-muted fw-bold">--</td>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                $op = $matriz[$t_key]['meta'][$b_key]['operador'] ?? '';
                            ?>
                                <td style="background-color: #ffffff;">
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

                        <!-- Fila Lote -->
                        <tr>
                            <td class="td-footer-label"><i class="bi bi-tag text-success me-1"></i> Lote Capturado</td>
                            <td class="col-rango-verde text-muted fw-bold">--</td>
                            <td class="col-rango-amarillo text-muted fw-bold">--</td>
                            <td class="col-rango-rojo text-muted fw-bold">--</td>
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
                            <td class="col-rango-verde text-muted fw-bold">--</td>
                            <td class="col-rango-amarillo text-muted fw-bold">--</td>
                            <td class="col-rango-rojo text-muted fw-bold">--</td>
                            <?php foreach ($t_bloques as $b_key => $b_data): 
                                $ob = $matriz[$t_key]['meta'][$b_key]['obs'] ?? '';
                            ?>
                                <?php if (!empty($ob)): ?>
                                    <td class="cell-obs-interactive" style="background-color: #fffbeb !important; color: #92400e !important;" 
                                        onclick="alert('Observación de <?= htmlspecialchars($equipo_nombre, ENT_QUOTES) ?> (<?= $b_key ?>):\n\n<?= addslashes(htmlspecialchars($ob)) ?>')" 
                                        title="<?= htmlspecialchars($ob) ?>">
                                        <div class="d-flex align-items-center justify-content-center gap-1" style="max-width: 100%; overflow: hidden;">
                                            <i class="bi bi-chat-left-text-fill text-warning flex-shrink-0" style="font-size: 10px;"></i>
                                            <span class="text-truncate" style="font-size: 9.5px;"><?= htmlspecialchars($ob) ?></span>
                                        </div>
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
</script>

</body>
</html>
