<?php
// progel_core/equipos/bloque_chillers_normales.php

include_once __DIR__ . '/../config/db_v2.php';
include_once __DIR__ . '/../includes/mapeo_v2.php';

// 1. Fechas y referencias operativas
$hoy = date('Y-m-d');
$lunes_esta_semana = date('Y-m-d 00:00:00', strtotime('monday this week'));
$domingo_esta_semana = date('Y-m-d 23:59:59', strtotime('sunday this week'));
$eqNombre = ($equipo_id == 91) ? 'Chillers Normales (1 al 4)' : (($equipo_id == 92) ? 'Chillers Normales (5 al 7)' : '');

// 2. Parámetros agrupados por frecuencia y grupo
$params_por_frec_grupo = [];
$q_all_params_cn = mysqli_query($conn, "SELECT * FROM parametros WHERE equipo_id = $equipo_id ORDER BY id ASC");
if ($q_all_params_cn) {
    while ($p_row = mysqli_fetch_assoc($q_all_params_cn)) {
        $f = $p_row['frecuencia'];
        $g = $p_row['grupo'] ?: 'General';
        $params_por_frec_grupo[$f][$g][] = $p_row;
    }
}

// 3. Cargar lecturas de HOY para DIARIO
$lecturas_diario_hoy = [];
$meta_diario_hoy = ['operador' => '', 'hora' => ''];
$q_dia = mysqli_query($conn, "
    SELECT parametro_id, valor_capturado, observaciones, fecha_registro, numero_nomina 
    FROM bitacora_lecturas 
    WHERE equipo_id = $equipo_id 
      AND DATE(fecha_registro) = '$hoy' 
    ORDER BY id DESC
");
if ($q_dia) {
    while ($r = mysqli_fetch_assoc($q_dia)) {
        $pid = (int)$r['parametro_id'];
        if (!isset($lecturas_diario_hoy[$pid])) {
            $lecturas_diario_hoy[$pid] = $r;
            if (empty($meta_diario_hoy['hora'])) {
                $meta_diario_hoy['hora'] = date('H:i:s', strtotime($r['fecha_registro']));
                $meta_diario_hoy['operador'] = $r['numero_nomina'];
            }
        }
    }
}

// 4. Cargar lecturas de la SEMANA ACTUAL para SEMANAL
$lecturas_semanal_actual = [];
$q_sem = mysqli_query($conn, "
    SELECT parametro_id, valor_capturado, observaciones, fecha_registro, numero_nomina 
    FROM bitacora_lecturas 
    WHERE equipo_id = $equipo_id 
      AND fecha_registro >= '$lunes_esta_semana' 
    ORDER BY id DESC
");
if ($q_sem) {
    while ($r = mysqli_fetch_assoc($q_sem)) {
        $pid = (int)$r['parametro_id'];
        if (!isset($lecturas_semanal_actual[$pid])) {
            $lecturas_semanal_actual[$pid] = $r;
        }
    }
}

// 5. Complementar con Progel_coreV2 (bitacora_chillers_normales) si existen registros más recientes
if (isset($conn_v2) && $conn_v2 && !empty($eqNombre)) {
    $escNombre = mysqli_real_escape_string($conn_v2, $eqNombre);
    $q_v2 = mysqli_query($conn_v2, "
        SELECT * FROM bitacora_chillers_normales 
        WHERE equipo_nombre = '$escNombre' 
          AND fecha_registro >= '$lunes_esta_semana' 
        ORDER BY id DESC
    ");
    if ($q_v2) {
        while ($r_v2 = mysqli_fetch_assoc($q_v2)) {
            $es_hoy = ($r_v2['fecha'] === $hoy || date('Y-m-d', strtotime($r_v2['fecha_registro'])) === $hoy);
            foreach ($GLOBALS['MAP_PARAMETROS_V2'] as $pid => $info) {
                if ($info[0] === 'bitacora_chillers_normales') {
                    $col = $info[1];
                    if (isset($r_v2[$col]) && $r_v2[$col] !== null && $r_v2[$col] !== '') {
                        if ($es_hoy && !isset($lecturas_diario_hoy[$pid])) {
                            $lecturas_diario_hoy[$pid] = [
                                'parametro_id' => $pid,
                                'valor_capturado' => $r_v2[$col],
                                'observaciones' => $r_v2['observaciones'] ?? '',
                                'fecha_registro' => $r_v2['fecha_registro'],
                                'numero_nomina' => $r_v2['operador_nomina'] ?? ''
                            ];
                            if (empty($meta_diario_hoy['hora'])) {
                                $meta_diario_hoy['hora'] = date('H:i:s', strtotime($r_v2['fecha_registro']));
                                $meta_diario_hoy['operador'] = $r_v2['operador_nomina'] ?? '';
                            }
                        }
                        if (!isset($lecturas_semanal_actual[$pid])) {
                            $lecturas_semanal_actual[$pid] = [
                                'parametro_id' => $pid,
                                'valor_capturado' => $r_v2[$col],
                                'observaciones' => $r_v2['observaciones'] ?? '',
                                'fecha_registro' => $r_v2['fecha_registro'],
                                'numero_nomina' => $r_v2['operador_nomina'] ?? ''
                            ];
                        }
                    }
                }
            }
        }
    }
}

// Helper para obtener la lectura según la frecuencia
if (!function_exists('obtenerRegistroChillerInteligente')) {
    function obtenerRegistroChillerInteligente($param_id, $frecuencia) {
        global $lecturas_diario_hoy, $lecturas_semanal_actual;
        if ($frecuencia === 'DIARIO') {
            return $lecturas_diario_hoy[$param_id] ?? null;
        } else {
            return $lecturas_semanal_actual[$param_id] ?? null;
        }
    }
}

// Conteo para determinar si DIARIO está completo hoy
$params_diarios_total = 0;
if (isset($params_por_frec_grupo['DIARIO'])) {
    foreach ($params_por_frec_grupo['DIARIO'] as $grp => $p_list) {
        $params_diarios_total += count($p_list);
    }
}
$diario_completo_hoy = ($params_diarios_total > 0 && count($lecturas_diario_hoy) >= $params_diarios_total);

// Conteo de parámetros semanales
$params_semanales_total = 0;
if (isset($params_por_frec_grupo['SEMANAL'])) {
    foreach ($params_por_frec_grupo['SEMANAL'] as $grp => $p_list) {
        $params_semanales_total += count($p_list);
    }
}
$semanales_registrados_count = count($lecturas_semanal_actual);

if (!function_exists('getSemaforoColor')) {
    function getSemaforoColor($val, $rb, $ab, $aa, $ra) {
        if (!is_numeric($val)) return 'bg-secondary';
        $v = (float)$val;
        $rb = $rb ?? -999999; $ab = $ab ?? -999999;
        $aa = $aa ?? 999999; $ra = $ra ?? 999999;
        if ($v <= $rb || $v >= $ra) return 'semaforo-r';
        if (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) return 'semaforo-a';
        return 'semaforo-v';
    }
}

if (!function_exists('fmt')) {
    function fmt($val) { return number_format((float)$val, 2, '.', ''); }
}

$frecuencias = array_keys($params_por_frec_grupo);
if (empty($frecuencias)) $frecuencias = ['DIARIO'];
?>

<style>
    .input-semaforo {
        transition: background-color 0.3s ease, color 0.3s ease;
        border-radius: 8px !important;
        height: 55px;
    }
    .input-semaforo:not(.semaforo-v):not(.semaforo-a):not(.semaforo-r) {
        background-color: #ffffff !important; color: #495057 !important; border: 1px solid #ced4da !important;
    }
    .semaforo-v { background-color: #2e8b57 !important; color: #ffffff !important; border: 2px solid #257447 !important; }
    .semaforo-a { background-color: #e49a32 !important; color: #ffffff !important; border: 2px solid #c47b1c !important; }
    .semaforo-r { background-color: #c94436 !important; color: #ffffff !important; border: 2px solid #a9362c !important; }
    .input-semaforo::placeholder { color: #adb5bd !important; }
    .semaforo-v::placeholder, .semaforo-a::placeholder, .semaforo-r::placeholder { color: rgba(255,255,255,0.7) !important; }
    .input-delta-auto {
        background-color: #f8f9fa !important; color: #495057 !important; cursor: not-allowed;
        font-weight: bold; border: 1px dashed #adb5bd !important;
    }
    .badge-guardado { padding: 12px; border-radius: 8px; font-size: 1.2rem; }
    .nav-pills .nav-link { color: #495057; border: 1px solid #dee2e6; margin-bottom: 5px; background-color: #f8f9fa; }
    .nav-pills .nav-link.active { background-color: #1e293b; color: white; border-color: #1e293b; }
    .card-resumen-diario { background: #f8fafc; border: 2px dashed #94a3b8; border-radius: 12px; }
</style>

<div class="container-fluid px-0">
    <!-- BARRA SUPERIOR DE ACCIONES -->
    <div class="mb-4 text-end d-flex justify-content-between align-items-center bg-white p-3 rounded-3 shadow-sm border-start border-4 border-dark">
        <div>
            <h5 class="m-0 fw-bold text-dark">Control de Chillers Normales</h5>
            <small class="text-muted">Zona Negra · Temperaturas, Deltas, Presiones y Rutinas</small>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <div class="btn-group btn-group-sm" role="group">
                <button type="button" class="btn btn-danger btn-sm fw-bold" onclick="marcarEquipo('F.O.', <?= $equipo_id ?>)">
                    <i class="bi bi-x-circle-fill me-1"></i> F.O.
                </button>
                <button type="button" class="btn btn-info text-white btn-sm fw-bold" onclick="marcarEquipo('LAVADO', <?= $equipo_id ?>)">
                    <i class="bi bi-droplet-fill me-1"></i> LAVADO
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm fw-bold" onclick="marcarEquipo('', <?= $equipo_id ?>)">
                    <i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar
                </button>
            </div>
            <a href="historial.php?id=<?= $equipo_id ?>" class="btn btn-secondary btn-sm fw-bold shadow-sm">
                <i class="bi bi-clock-history me-1"></i> Historial
            </a>
        </div>
    </div>

    <!-- PESTAÑAS DIARIO / SEMANAL -->
    <ul class="nav nav-pills flex-wrap mb-4 gap-2 justify-content-center" role="tablist">
        <?php foreach($frecuencias as $index => $frec): 
            $clase_activa = ($index == 0) ? 'active' : '';
            $es_diario = ($frec === 'DIARIO');
        ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $clase_activa ?> fw-bold fs-6 shadow-sm px-4 py-2" data-bs-toggle="pill" data-bs-target="#tab_<?= md5($frec) ?>" type="button" role="tab">
                    <i class="bi <?= ($es_diario ? 'bi-calendar-check' : 'bi-calendar-week') ?> me-2"></i>
                    Frecuencia: <?= htmlspecialchars($frec) ?>
                    <?php if ($es_diario): ?>
                        <?php if ($diario_completo_hoy): ?>
                            <span class="badge bg-success ms-2 rounded-pill"><i class="bi bi-check-lg"></i> Hoy Guardado</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark ms-2 rounded-pill"><i class="bi bi-clock-fill"></i> Pendiente Hoy</span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-primary ms-2 rounded-pill"><?= $semanales_registrados_count ?>/<?= $params_semanales_total ?></span>
                    <?php endif; ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- CONTENIDO DE LAS PESTAÑAS -->
    <div class="tab-content">
        <?php foreach($frecuencias as $index => $frec): 
            $clase_pane = ($index == 0) ? 'show active' : '';
            $es_diario = ($frec === 'DIARIO');
            $grupos_en_frec = $params_por_frec_grupo[$frec] ?? [];
        ?>
        <div class="tab-pane fade <?= $clase_pane ?>" id="tab_<?= md5($frec) ?>" role="tabpanel">

            <?php if ($es_diario): ?>
                <!-- ======================================================== -->
                <!-- PESTAÑA: DIARIO (SE LLENA TODO CADA DÍA, SE REINICIA DIARIO) -->
                <!-- ======================================================== -->

                <?php if ($diario_completo_hoy): ?>
                    <!-- Vista de Resumen cuando el día de hoy ya se completó -->
                    <div id="vista_resumen_diario" class="mb-4">
                        <div class="alert alert-success d-flex flex-wrap justify-content-between align-items-center rounded-3 shadow-sm border-0 border-start border-5 border-success p-3 mb-4">
                            <div>
                                <h6 class="fw-bold mb-1 text-success fs-5">
                                    <i class="bi bi-check-circle-fill me-2"></i> Registro Diario de Hoy (<?= date('d/m/Y') ?>) Completado
                                </h6>
                                <span class="text-secondary small">
                                    Todos los parámetros diarios fueron capturados<?= !empty($meta_diario_hoy['hora']) ? " a las <strong>{$meta_diario_hoy['hora']}</strong>" : '' ?>. Al cambiar de día operativo, se habilitará automáticamente la captura limpia del nuevo día.
                                </span>
                            </div>
                            <div class="mt-2 mt-md-0">
                                <button type="button" class="btn btn-outline-success fw-bold px-3 py-2 shadow-sm" onclick="habilitarModificacionDiaria()">
                                    <i class="bi bi-pencil-square me-1"></i> Modificar Captura de Hoy
                                </button>
                            </div>
                        </div>

                        <!-- Tarjetas de visualización de los valores de hoy -->
                        <?php foreach ($grupos_en_frec as $grupo_nombre => $params_en_grupo): ?>
                            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                                <div class="card-header bg-dark text-white py-2 px-3 fw-bold d-flex justify-content-between align-items-center">
                                    <span><?= htmlspecialchars($grupo_nombre) ?></span>
                                    <span class="badge bg-success"><i class="bi bi-check2"></i> Guardado Hoy</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle m-0 bg-white">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3" style="width: 35%;">Parámetro / Variable</th>
                                                <th style="width: 25%;">Límites de Referencia</th>
                                                <th class="text-center" style="width: 18%;">Valor Registrado Hoy</th>
                                                <th>Observaciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($params_en_grupo as $p): 
                                                $reg = obtenerRegistroChillerInteligente($p['id'], 'DIARIO');
                                                $val = $reg['valor_capturado'] ?? '---';
                                                $obs = $reg['observaciones'] ?? '';
                                                $rb = $p['rojo_bajo']; $ab = $p['amarillo_bajo'];
                                                $aa = $p['amarillo_alto']; $ra = $p['rojo_alto'];
                                                $badge_color = getSemaforoColor($val, $rb, $ab, $aa, $ra);
                                            ?>
                                            <tr>
                                                <td class="fw-bold ps-3 text-dark"><?= htmlspecialchars($p['nombre_parametro']) ?></td>
                                                <td class="text-secondary align-middle small">
                                                    <?php if ($p['tipo_dato'] == 'NUMERICO' && ($rb !== null || $ra !== null)): ?>
                                                        <span>🟢 <?= ($ab !== null ? fmt($ab + 0.01) : 'Min') ?> a <?= ($aa !== null ? fmt($aa - 0.01) : 'Max') ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">Cualitativo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center px-2">
                                                    <div class="badge-guardado fw-bold text-center <?= $badge_color ?> text-white border-0 shadow-sm py-2">
                                                        <?= htmlspecialchars($val) ?>
                                                    </div>
                                                </td>
                                                <td class="pe-3 small text-muted fst-italic">
                                                    <?= htmlspecialchars($obs) ?: 'Sin observaciones' ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Alerta de Captura Diaria Pendiente -->
                    <div class="alert alert-warning mb-4 rounded-3 shadow-sm border-0 border-start border-5 border-warning p-3">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-calendar-event fs-3 text-warning me-3"></i>
                            <div>
                                <h6 class="fw-bold mb-1 text-dark">Inspección Diaria de Chillers · Fecha: <?= date('d/m/Y') ?></h6>
                                <span class="small text-muted">Para registrar el control diario de chillers <strong>es obligatorio llenar todos los parámetros</strong> de la bitácora.</span>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Formulario de Captura / Edición Diaria -->
                <div id="vista_formulario_diario" style="<?= $diario_completo_hoy ? 'display: none;' : '' ?>">
                    <form action="acciones/guardar.php" method="POST" id="form_chiller_diario" onsubmit="return validarDiarioCompleto(event)">
                        <input type="hidden" name="equipo_id" value="<?= $equipo_id ?>">
                        <input type="hidden" name="frecuencia_tab" value="DIARIO">
                        <input type="hidden" name="numero_nomina" value="<?= htmlspecialchars($_SESSION['nomina']) ?>">

                        <?php foreach ($grupos_en_frec as $grupo_nombre => $params_en_grupo): ?>
                            <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                                <div class="card-header bg-dark text-white py-2 px-3 fw-bold d-flex justify-content-between align-items-center">
                                    <span><?= htmlspecialchars($grupo_nombre) ?></span>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-asterisk"></i> Obligatorio Llenar Todo</span>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle m-0 bg-white">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3" style="width: 35%;">Parámetro / Variable</th>
                                                <th style="width: 25%;">Límites de Referencia</th>
                                                <th class="text-center" style="width: 18%;">Lectura Real</th>
                                                <th>Observaciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            foreach ($params_en_grupo as $p): 
                                                $reg = obtenerRegistroChillerInteligente($p['id'], 'DIARIO');
                                                $val_guardado = $reg ? $reg['valor_capturado'] : '';
                                                $obs_guardada = $reg ? $reg['observaciones'] : '';
                                                $rb = $p['rojo_bajo']; $ab = $p['amarillo_bajo'];
                                                $aa = $p['amarillo_alto']; $ra = $p['rojo_alto'];
                                                $n_lower = strtolower($p['nombre_parametro']);
                                                
                                                $clase_extra = "input-semaforo input-diario-requerido";
                                                $es_delta = false;
                                                $grupo_id = "";

                                                if (strpos($n_lower, '1, 2') !== false) $grupo_id = "12";
                                                elseif (strpos($n_lower, '3, 4') !== false) $grupo_id = "34";
                                                elseif (strpos($n_lower, '5, 6') !== false) $grupo_id = "56";
                                                elseif (strpos($n_lower, '7') !== false && strpos($n_lower, '5') === false) $grupo_id = "7";

                                                if ($grupo_id !== "") {
                                                    if (strpos($n_lower, 'in') !== false || strpos($n_lower, 'entrada') !== false) $clase_extra .= " temp-in-" . $grupo_id;
                                                    elseif (strpos($n_lower, 'out') !== false || strpos($n_lower, 'salida') !== false) $clase_extra .= " temp-out-" . $grupo_id;
                                                    elseif (strpos($n_lower, 'delta') !== false) {
                                                        $clase_extra .= " temp-delta-" . $grupo_id . " input-delta-auto";
                                                        $es_delta = true;
                                                    }
                                                }
                                            ?>
                                            <tr>
                                                <td class="fw-bold ps-3 text-dark">
                                                    <?= htmlspecialchars($p['nombre_parametro']) ?>
                                                    <?php if (!$es_delta): ?>
                                                        <span class="text-danger">*</span>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <td class="text-secondary align-middle">
                                                    <?php if ($p['tipo_dato'] == 'NUMERICO' && ($rb !== null || $ra !== null)): ?>
                                                        <div style="font-size: 0.82rem; line-height: 1.4;">
                                                            <?php if ($rb !== null): ?>
                                                                <div>🔴 <strong class="text-danger">Crítico Bajo:</strong> &le; <?= fmt($rb) ?></div>
                                                            <?php endif; ?>

                                                            <?php if ($ab !== null && $ab > $rb): ?>
                                                                <div>🟡 <strong style="color: #d97706;">Alerta Baja:</strong> <?= fmt($rb + 0.01) ?> a <?= fmt($ab) ?></div>
                                                            <?php endif; ?>

                                                            <div>🟢 <strong class="text-success">Óptimo:</strong> 
                                                                <?= ($ab !== null ? fmt($ab + 0.01) : ($rb !== null ? fmt($rb + 0.01) : 'Min')) ?> 
                                                                a 
                                                                <?= ($aa !== null ? fmt($aa - 0.01) : ($ra !== null ? fmt($ra - 0.01) : 'Max')) ?>
                                                            </div>

                                                            <?php if ($aa !== null && $ra !== null && $aa < $ra): ?>
                                                                <div>🟡 <strong style="color: #d97706;">Alerta Alta:</strong> <?= fmt($aa) ?> a <?= fmt($ra - 0.01) ?></div>
                                                            <?php endif; ?>

                                                            <?php if ($ra !== null): ?>
                                                                <div>🔴 <strong class="text-danger">Crítico Alto:</strong> &ge; <?= fmt($ra) ?></div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="badge bg-light text-dark border">Verificación / Rutina Cualitativa</span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="text-center px-2">
                                                    <?php if ($p['tipo_dato'] == 'NUMERICO'): ?>
                                                        <input type="number" step="0.01" name="param_<?= $p['id'] ?>" 
                                                            class="form-control text-center fw-bold fs-5 shadow-sm <?= $clase_extra ?> input-equipo-<?= $equipo_id ?>" 
                                                            id="param_<?= $p['id'] ?>"
                                                            value="<?= htmlspecialchars($val_guardado) ?>"
                                                            data-rb="<?= $rb ?>" data-ab="<?= $ab ?>" data-aa="<?= $aa ?>" data-ra="<?= $ra ?>"
                                                            data-nombre="<?= htmlspecialchars($p['nombre_parametro']) ?>"
                                                            oninput="evaluarSemaforoChiller(this); calcularDeltasAutomaticas();"
                                                            onchange="forzarNegativoChiller(this); evaluarSemaforoChiller(this);"
                                                            <?= $es_delta ? 'readonly tabindex="-1" placeholder="Auto..."' : 'required' ?>>
                                                    <?php else: ?>
                                                        <select name="param_<?= $p['id'] ?>" 
                                                                class="form-select text-center fw-bold bg-light <?= $clase_extra ?> input-equipo-<?= $equipo_id ?>" 
                                                                id="param_<?= $p['id'] ?>"
                                                                data-nombre="<?= htmlspecialchars($p['nombre_parametro']) ?>"
                                                                required>
                                                            <option value="">Seleccionar...</option>
                                                            <option value="Sí" <?= $val_guardado === 'Sí' ? 'selected' : '' ?>>Sí</option>
                                                            <option value="No" <?= $val_guardado === 'No' ? 'selected' : '' ?>>No</option>
                                                            <option value="N/A" <?= $val_guardado === 'N/A' ? 'selected' : '' ?>>N/A</option>
                                                        </select>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="pe-3">
                                                    <textarea name="obs_<?= $p['id'] ?>" class="form-control form-control-sm input-equipo-<?= $equipo_id ?>" id="obs_<?= $p['id'] ?>" rows="1" placeholder="Notas..."><?= htmlspecialchars($obs_guardada) ?></textarea>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <div class="mt-4 p-2">
                            <button type="submit" class="btn btn-dark btn-lg w-100 py-3 fw-bold fs-5 shadow" style="--bs-btn-border-radius: .75rem;">
                                <i class="bi bi-floppy-fill me-2 text-warning"></i> Guardar Registro Diario (Obligatorio Llenar Todo)
                            </button>
                        </div>
                    </form>
                </div>

            <?php else: ?>
                <!-- ======================================================== -->
                <!-- PESTAÑA: SEMANAL (SE PUEDE REGISTRAR LO QUE SE TIENE GUARDADO) -->
                <!-- ======================================================== -->

                <!-- Banner Informativo de la Semana -->
                <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center mb-4 rounded-3 shadow-sm border-0 border-start border-5 border-info p-3">
                    <div>
                        <h6 class="fw-bold mb-1 text-primary fs-5">
                            <i class="bi bi-calendar-week me-2"></i> Rutinas y Variables Semanales
                        </h6>
                        <span class="text-secondary small">
                            Semana en curso (del <strong><?= date('d/m/Y', strtotime($lunes_esta_semana)) ?></strong> al <strong><?= date('d/m/Y', strtotime($domingo_esta_semana)) ?></strong>). 
                            Puedes registrar y guardar los avances que tengas listos; <strong>lo que ya guardaste se conserva y se mantiene registrado durante la semana</strong>.
                        </span>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <span class="badge bg-primary text-white fs-6 px-3 py-2 rounded-pill shadow-sm">
                            <i class="bi bi-list-check me-1"></i> <?= $semanales_registrados_count ?> / <?= $params_semanales_total ?> Registrados
                        </span>
                    </div>
                </div>

                <form action="acciones/guardar.php" method="POST" id="form_chiller_semanal" onsubmit="return validarSemanal(event)">
                    <input type="hidden" name="equipo_id" value="<?= $equipo_id ?>">
                    <input type="hidden" name="frecuencia_tab" value="SEMANAL">
                    <input type="hidden" name="numero_nomina" value="<?= htmlspecialchars($_SESSION['nomina']) ?>">

                    <?php foreach ($grupos_en_frec as $grupo_nombre => $params_en_grupo): ?>
                        <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                            <div class="card-header bg-dark text-white py-2 px-3 fw-bold d-flex justify-content-between align-items-center">
                                <span><?= htmlspecialchars($grupo_nombre) ?></span>
                                <span class="badge bg-secondary text-white">Registro Acumulativo Semanal</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle m-0 bg-white">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3" style="width: 35%;">Parámetro / Rutina Semanal</th>
                                            <th style="width: 25%;">Límites de Referencia</th>
                                            <th class="text-center" style="width: 20%;">Lectura / Estado</th>
                                            <th>Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        foreach ($params_en_grupo as $p): 
                                            $reg = obtenerRegistroChillerInteligente($p['id'], 'SEMANAL');
                                            $val_guardado = $reg ? $reg['valor_capturado'] : '';
                                            $obs_guardada = $reg ? $reg['observaciones'] : '';
                                            $rb = $p['rojo_bajo']; $ab = $p['amarillo_bajo'];
                                            $aa = $p['amarillo_alto']; $ra = $p['rojo_alto'];
                                            $tiene_registro = ($val_guardado !== '' && $val_guardado !== null);
                                            $clase_semaforo_ini = $tiene_registro ? getSemaforoColor($val_guardado, $rb, $ab, $aa, $ra) : '';
                                        ?>
                                        <tr>
                                            <td class="fw-bold ps-3 text-dark">
                                                <div class="d-flex align-items-center justify-content-between">
                                                    <span><?= htmlspecialchars($p['nombre_parametro']) ?></span>
                                                    <?php if ($p['tipo_dato'] != 'NUMERICO'): ?>
                                                        <?php if ($tiene_registro && in_array(strtoupper(trim((string)$val_guardado)), ['1', 'SI', 'SÍ', 'OK', 'REALIZADA', 'CUMPLIDA', 'TRUE'])): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 small ms-2">
                                                                <i class="bi bi-check-circle-fill me-1"></i>Realizada
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle py-1 px-2 small ms-2">
                                                                <i class="bi bi-x-circle-fill me-1"></i>No Realizada
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <?php if ($tiene_registro): ?>
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle py-1 px-2 small ms-2" title="Ya registrado en esta semana">
                                                                <i class="bi bi-check-circle-fill me-1"></i>Registrado
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-muted border py-1 px-2 small ms-2">
                                                                Sin captura
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <td class="text-secondary align-middle">
                                                <?php if ($p['tipo_dato'] == 'NUMERICO' && ($rb !== null || $ra !== null)): ?>
                                                    <div style="font-size: 0.82rem; line-height: 1.4;">
                                                        <?php if ($rb !== null): ?>
                                                            <div>🔴 <strong class="text-danger">Crítico Bajo:</strong> &le; <?= fmt($rb) ?></div>
                                                        <?php endif; ?>
                                                        <div>🟢 <strong class="text-success">Óptimo:</strong> 
                                                            <?= ($ab !== null ? fmt($ab + 0.01) : ($rb !== null ? fmt($rb + 0.01) : 'Min')) ?> a <?= ($aa !== null ? fmt($aa - 0.01) : ($ra !== null ? fmt($ra - 0.01) : 'Max')) ?>
                                                        </div>
                                                        <?php if ($ra !== null): ?>
                                                            <div>🔴 <strong class="text-danger">Crítico Alto:</strong> &ge; <?= fmt($ra) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-dark border">Rutina Cualitativa de Mantenimiento</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="text-center px-2">
                                                <?php if ($p['tipo_dato'] == 'NUMERICO'): ?>
                                                    <input type="number" step="0.01" name="param_<?= $p['id'] ?>" 
                                                        class="form-control text-center fw-bold fs-5 shadow-sm input-semaforo <?= $clase_semaforo_ini ?> input-equipo-<?= $equipo_id ?>" 
                                                        id="param_<?= $p['id'] ?>"
                                                        value="<?= htmlspecialchars($val_guardado) ?>"
                                                        placeholder="---"
                                                        data-rb="<?= $rb ?>" data-ab="<?= $ab ?>" data-aa="<?= $aa ?>" data-ra="<?= $ra ?>"
                                                        oninput="evaluarSemaforoChiller(this);"
                                                        onchange="forzarNegativoChiller(this); evaluarSemaforoChiller(this);">
                                                <?php else: ?>
                                                    <select name="param_<?= $p['id'] ?>" 
                                                            class="form-select text-center fw-bold bg-light input-semaforo input-equipo-<?= $equipo_id ?>" 
                                                            id="param_<?= $p['id'] ?>">
                                                        <option value="">Seleccionar...</option>
                                                        <option value="Sí" <?= in_array(strtoupper(trim((string)$val_guardado)), ['SÍ', 'SI', '1', 'REALIZADA']) ? 'selected' : '' ?>>Realizada (Sí)</option>
                                                        <option value="No" <?= in_array(strtoupper(trim((string)$val_guardado)), ['NO', '0', 'NO REALIZADA', 'NO SE HIZO']) ? 'selected' : '' ?>>No Realizada (No se hizo)</option>
                                                        <option value="N/A" <?= strtoupper(trim((string)$val_guardado)) === 'N/A' ? 'selected' : '' ?>>N/A</option>
                                                    </select>
                                                <?php endif; ?>
                                            </td>
                                            <td class="pe-3">
                                                <textarea name="obs_<?= $p['id'] ?>" class="form-control form-control-sm input-equipo-<?= $equipo_id ?>" id="obs_<?= $p['id'] ?>" rows="1" placeholder="Notas..."><?= htmlspecialchars($obs_guardada) ?></textarea>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="mt-4 p-2">
                        <button type="submit" class="btn btn-dark btn-lg w-100 py-3 fw-bold fs-5 shadow" style="--bs-btn-border-radius: .75rem;">
                            <i class="bi bi-cloud-arrow-up-fill me-2 text-info"></i> Guardar Avances SEMANAL
                        </button>
                    </div>
                </form>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function marcarEquipo(valor, equipoId) {
    if (!equipoId) return;

    const campos = document.querySelectorAll('input.input-equipo-' + equipoId + ', select.input-equipo-' + equipoId);

    campos.forEach(function(el) {
        if (!el) return;

        if (el.tagName === 'SELECT') {
            let encontrada = false;
            for (let i = 0; i < el.options.length; i++) {
                if (el.options[i].value === valor) {
                    encontrada = true;
                    break;
                }
            }
            if (!encontrada && valor !== '') {
                const opt = document.createElement('option');
                opt.value = valor;
                opt.text = valor;
                el.add(opt);
            }
            el.value = valor;
        } else if (el.tagName === 'INPUT') {
            if (el.type === 'number' && valor !== '') {
                el.type = 'text';
            } else if (valor === '' && el.type === 'text') {
                el.type = 'number';
            }

            el.value = valor;
            el.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
        }
    });
}

window.marcarEquipo = marcarEquipo;

function forzarNegativoChiller(input) {
    if (!input) return;
    let rb = parseFloat(input.getAttribute('data-rb'));
    let aa = parseFloat(input.getAttribute('data-aa'));
    
    if (rb < 0 || aa < 0) {
        let val = parseFloat(input.value);
        if (!isNaN(val) && val > 0) {
            input.value = (val * -1).toFixed(2);
        }
    }
    calcularDeltasAutomaticas();
}

function evaluarSemaforoChiller(input) {
    if (!input || input.value === '') {
        if (input) input.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
        return;
    }
    const val = parseFloat(input.value);
    let rb = parseFloat(input.getAttribute('data-rb')); if(isNaN(rb)) rb = -999999;
    let ab = parseFloat(input.getAttribute('data-ab')); if(isNaN(ab)) ab = -999999;
    let aa = parseFloat(input.getAttribute('data-aa')); if(isNaN(aa)) aa = 999999;
    let ra = parseFloat(input.getAttribute('data-ra')); if(isNaN(ra)) ra = 999999;

    input.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
    if (val <= rb || val >= ra) { input.classList.add('semaforo-r'); } 
    else if ((val > rb && val <= ab) || (val >= aa && val < ra)) { input.classList.add('semaforo-a'); } 
    else { input.classList.add('semaforo-v'); }
}

function calcularDeltasAutomaticas() {
    const grupos = ['12', '34', '56', '7'];
    grupos.forEach(grupo => {
        let inInput = document.querySelector('.temp-in-' + grupo);
        let outInput = document.querySelector('.temp-out-' + grupo);
        let deltaInput = document.querySelector('.temp-delta-' + grupo);

        if (inInput && outInput && deltaInput) {
            let valIn = parseFloat(inInput.value);
            let valOut = parseFloat(outInput.value);
            
            if (!isNaN(valIn) && !isNaN(valOut)) {
                let resta = Math.abs(valIn - valOut); 
                deltaInput.value = resta.toFixed(2);
                evaluarSemaforoChiller(deltaInput); 
            } else {
                deltaInput.value = '';
                deltaInput.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
            }
        }
    });
}

function validarDiarioCompleto(event) {
    const form = document.getElementById('form_chiller_diario');
    if (!form) return true;

    // Buscar todos los inputs editables requeridos
    const inputs = form.querySelectorAll('input.input-diario-requerido:not([readonly]), select.input-diario-requerido');
    let faltantes = [];

    inputs.forEach(el => {
        if (!el.value || el.value.trim() === '') {
            faltantes.push(el);
            el.style.border = '2px solid #dc3545';
        } else {
            el.style.border = '';
        }
    });

    if (faltantes.length > 0) {
        event.preventDefault();
        faltantes[0].focus();
        
        const primerNombre = faltantes[0].getAttribute('data-nombre') || 'un parámetro';
        const msg = 'Para el registro diario de chillers es obligatorio llenar todos los campos antes de guardar. Te faltan ' + faltantes.length + ' campos por registrar (ej: ' + primerNombre + ').';

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Registro Diario Incompleto',
                text: msg,
                confirmButtonColor: '#1e293b',
                confirmButtonText: 'Entendido, completar campos'
            });
        } else {
            alert(msg);
        }
        return false;
    }
    return true;
}

function validarSemanal(event) {
    const form = document.getElementById('form_chiller_semanal');
    if (!form) return true;

    const campos = form.querySelectorAll('input[name^="param_"], select[name^="param_"]');
    let llenos = 0;
    campos.forEach(el => {
        if (el.value && el.value.trim() !== '') {
            llenos++;
        }
    });

    if (llenos === 0) {
        event.preventDefault();
        const msg = 'Ingresa o selecciona al menos un parámetro o rutina semanal antes de guardar.';
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'info',
                title: 'Sin datos para guardar',
                text: msg,
                confirmButtonColor: '#1e293b'
            });
        } else {
            alert(msg);
        }
        return false;
    }
    return true;
}

function habilitarModificacionDiaria() {
    const resumen = document.getElementById('vista_resumen_diario');
    const formulario = document.getElementById('vista_formulario_diario');
    if (resumen && formulario) {
        resumen.style.display = 'none';
        formulario.style.display = 'block';
        // Recalcular semáforos y deltas
        calcularDeltasAutomaticas();
        formulario.querySelectorAll('input.input-semaforo').forEach(evaluarSemaforoChiller);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    calcularDeltasAutomaticas();
    document.querySelectorAll('input.input-semaforo').forEach(evaluarSemaforoChiller);
});
</script>
