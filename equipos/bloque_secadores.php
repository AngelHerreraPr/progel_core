<?php
// Progel_cores/equipos/bloque_secadores.php

// FUNCIÓN: Búsqueda Dual (Local y Reporte Maestro)
function obtenerRegistroSecador($conn, $param_id, $nombre_param, $equipo_id) {
    global $conn_procesos; // <-- ¡MAGIA! Usamos la conexión segura del servidor

    // 1. Primero revisa si se guardó en Progel_coreV2
    include_once __DIR__ . '/../config/db_v2.php';
    include_once __DIR__ . '/../includes/mapeo_v2.php';
    global $conn_v2;
    if (isset($conn_v2) && $conn_v2) {
        $v2 = obtenerLecturaRecienteV2($conn_v2, $param_id, $equipo_id);
        if ($v2) return $v2;
    }

    // 2. Si no, revisa bitacora_lecturas por compatibilidad histórica
    $q = "SELECT valor_capturado, observaciones FROM bitacora_lecturas 
          WHERE parametro_id = $param_id 
          AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 90 MINUTE) 
          ORDER BY id DESC LIMIT 1";
    $res = mysqli_query($conn, $q);
    $local = mysqli_fetch_assoc($res);
    if ($local) return $local;

    // 2. Si no hay registro local, busca automáticamente en el Reporte Maestro (Progel_procesos)
    $secador_num = 0;
    if ($equipo_id == 21) $secador_num = 1;
    if ($equipo_id == 22) $secador_num = 2;
    if ($equipo_id == 23) $secador_num = 3;
    if ($equipo_id == 24) $secador_num = 4;

    if ($secador_num > 0) {
        $col = "";
        $n = mb_strtolower($nombre_param, 'UTF-8');
        
        // Mapeamos el nombre del parámetro con la columna de la tabla maestra
        if (strpos($n, 'flujo') !== false) $col = 'flujo';
        elseif (strpos($n, 'frecuencia') !== false || strpos($n, 'velocidad') !== false) $col = 'hz';
        elseif (strpos($n, 'rc 9') !== false || strpos($n, 'rc9') !== false) $col = 'rc9';
        elseif (strpos($n, 'penúltima') !== false || strpos($n, 'penultima') !== false) $col = 'hum_penultima';
        elseif (strpos($n, 'producto final') !== false || strpos($n, 'última') !== false) $col = 'hum_ultima';
        elseif (strpos($n, 'altura') !== false) $col = 'altura_galleta';
        elseif (strpos($n, 'relativa') !== false) $col = 'hum_relativa_cam5';
        elseif (strpos($n, 'textura') !== false) $col = 'textura';

        // Si detectó la columna y hay conexión activa al servidor...
        if ($col !== "" && $conn_procesos) {
            $fecha_hoy = date('Y-m-d');
            // Buscamos si en los últimos 90 mins alguien guardó desde la matriz SCADA
            $q_proc = "SELECT $col as val, estado_fo FROM verificacion_secado 
                       WHERE fecha = '$fecha_hoy' AND secador = $secador_num 
                       AND creado_en >= DATE_SUB(NOW(), INTERVAL 90 MINUTE) 
                       ORDER BY creado_en DESC LIMIT 1";
            $res_proc = mysqli_query($conn_procesos, $q_proc);
            if ($res_proc) {
                $row_proc = mysqli_fetch_assoc($res_proc);
                if ($row_proc) {
                    if ($row_proc['estado_fo'] == 1) {
                        return ['valor_capturado' => 'F.O.', 'observaciones' => 'Auto-Sync (Reporte Maestro)'];
                    }
                    if ($row_proc['val'] !== null && $row_proc['val'] !== '') {
                        // LA MAGIA PARA ARREGLAR EL -1
                        $valor_sincronizado = $row_proc['val'];
                        if ($valor_sincronizado == -1 || $valor_sincronizado == '-1' || $valor_sincronizado == '-1.00') {
                            $valor_sincronizado = 'F.O.';
                        }
                        return ['valor_capturado' => $valor_sincronizado, 'observaciones' => 'Auto-Sync (Reporte Maestro)'];
                    }
                }
            }
        }
    }
    return null;
}

function getSemaforoColorSecador($val, $rb, $ab, $aa, $ra) {
    if (!is_numeric($val)) return 'bg-secondary';
    $v = (float)$val;
    $rb = $rb ?? -999999; $ab = $ab ?? -999999;
    $aa = $aa ?? 999999; $ra = $ra ?? 999999;
    if ($v <= $rb || $v >= $ra) return 'semaforo-r';
    if (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) return 'semaforo-a';
    return 'semaforo-v';
}

function fmt($val) { return number_format((float)$val, 2, '.', ''); }

// LÓGICA DE AGRUPACIÓN INTELIGENTE
$es_grupo_12 = in_array($equipo_id, [21, 22]);
$titulo_modulo = $es_grupo_12 ? 'Módulo: Secadores 1 y 2' : 'Módulo: Secadores 3 y 4';
$array_equipos = $es_grupo_12 ? [21 => 'Secador 1', 22 => 'Secador 2'] : [23 => 'Secador 3', 24 => 'Secador 4'];
?>

<style>
    .input-semaforo { transition: background-color 0.3s ease, color 0.3s ease; border-radius: 8px 0 0 8px !important; height: 55px; }
    .input-semaforo:not(.semaforo-v):not(.semaforo-a):not(.semaforo-r) { background-color: #ffffff !important; color: #495057 !important; border: 1px solid #ced4da !important; }
    .semaforo-v { background-color: #28a745 !important; color: #ffffff !important; border: 2px solid #1e7e34 !important; }
    .semaforo-a { background-color: #ffc107 !important; color: #000000 !important; border: 2px solid #d39e00 !important; }
    .semaforo-r { background-color: #dc3545 !important; color: #ffffff !important; border: 2px solid #a8363b !important; }
    .input-semaforo::placeholder { color: #adb5bd !important; }
    .semaforo-v::placeholder, .semaforo-a::placeholder, .semaforo-r::placeholder { color: rgba(255,255,255,0.7) !important; }
    .badge-guardado { padding: 12px; border-radius: 8px; font-size: 1.2rem; }
    .nav-pills .nav-link { color: #495057; border: 1px solid #dee2e6; margin-bottom: 5px; background-color: #f8f9fa; }
    .nav-pills .nav-link.active { background-color: #6c757d; color: white; border-color: #6c757d; }
    .textura-desc { font-size: 0.75rem; line-height: 1.2; color: #6c757d; display: block; margin-top: 4px; }
</style>

<div class="container-fluid px-0">
    <div class="mb-4 text-end d-flex justify-content-between align-items-center bg-white p-3 rounded-3 shadow-sm border-start border-4 border-secondary">
        <div>
            <h5 class="m-0 fw-bold text-dark"><i class="bi bi-wind text-secondary me-2"></i><?= htmlspecialchars($titulo_modulo) ?></h5>
            <small class="text-muted">Captura Independiente por Máquina (Cada 3 Hrs)</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-outline-dark btn-sm fw-bold" disabled>
                <i class="bi bi-person-badge"></i> Nómina: <span class="text-dark"><?php echo htmlspecialchars($_SESSION['nomina']); ?></span>
            </button>
            <a href="historial.php?id=<?php echo $equipo_id; ?>" class="btn btn-secondary btn-sm fw-bold shadow-sm">
                <i class="bi bi-clock-history"></i> Historial
            </a>
        </div>
    </div>

    <?php 
    $active_tab_id = in_array((int)$equipo_id, array_keys($array_equipos)) ? (int)$equipo_id : array_key_first($array_equipos);
    ?>
    <ul class="nav nav-pills flex-wrap mb-4 gap-2 justify-content-center" role="tablist">
        <?php foreach($array_equipos as $id_eq_actual => $nombre_eq_actual) { 
            $is_tab_active = ($id_eq_actual === $active_tab_id);
        ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $is_tab_active ? 'active' : '' ?> fw-bold fs-5 shadow-sm px-5 py-2" data-bs-toggle="pill" data-bs-target="#tab_<?= $id_eq_actual ?>" type="button" role="tab">
                    <i class="bi bi-box-fill me-1"></i> <?= htmlspecialchars($nombre_eq_actual) ?>
                </button>
            </li>
        <?php } ?>
    </ul>

    <div class="tab-content">
        <?php foreach($array_equipos as $id_eq_actual => $nombre_eq_actual) { 
            $is_pane_active = ($id_eq_actual === $active_tab_id);
        ?>
        <div class="tab-pane fade <?= $is_pane_active ? 'show active' : '' ?>" id="tab_<?= $id_eq_actual ?>" role="tabpanel">
            
            <form action="guardar.php" method="POST">
                <input type="hidden" name="equipo_id" value="<?= $id_eq_actual ?>">
                <input type="hidden" name="frecuencia_tab" value="CADA 3 HORAS">
                <input type="hidden" name="numero_nomina" value="<?= htmlspecialchars($_SESSION['nomina']); ?>">

                <!-- CAMPO DE LOTE EN LA PARTE SUPERIOR -->
                <div class="row mb-4 px-3">
                    <div class="col-12 col-md-5">
                        <div class="p-3 bg-white border border-2 border-primary rounded-3 shadow-sm">
                            <label class="form-label fw-bold text-primary mb-1">
                                <i class="bi bi-box-seam me-1"></i> Número de Lote Actual:
                            </label>
                            <input type="text" name="lote" class="form-control form-control-lg text-uppercase fw-bold bg-light" placeholder="Ej. L-1024" autocomplete="off" required>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-dark text-white py-3 px-4 fw-bold d-flex justify-content-between align-items-center">
                        <div>
                            <span class="fs-5 text-light"><i class="bi bi-table me-2"></i> Captura de <?= htmlspecialchars($nombre_eq_actual) ?></span>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-danger btn-sm fw-bold" onclick="marcarEquipo('F.O.', <?= $id_eq_actual ?>)">
                                    <i class="bi bi-x-circle-fill me-1"></i> F.O. General
                                </button>
                                <button type="button" class="btn btn-info text-white btn-sm fw-bold" onclick="marcarEquipo('LAVADO', <?= $id_eq_actual ?>)">
                                    <i class="bi bi-droplet-fill me-1"></i> LAVADO
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm fw-bold" onclick="marcarEquipo('', <?= $id_eq_actual ?>)">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar
                                </button>
                            </div>
                            <a href="historial.php?id=<?= $id_eq_actual ?>" class="btn btn-secondary btn-sm fw-bold shadow-sm">
                                <i class="bi bi-clock-history"></i>
                            </a>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle m-0 bg-white">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="width: 25%;">Parámetro / Variable</th>
                                    <th style="width: 32%;">Límites de Referencia</th>
                                    <th class="text-center" style="width: 20%;">Lectura Real</th>
                                    <th class="pe-4 text-center">Observaciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $stmt_p = mysqli_prepare($conn, "SELECT * FROM parametros WHERE equipo_id = ? ORDER BY id ASC");
                                mysqli_stmt_bind_param($stmt_p, "i", $id_eq_actual);
                                mysqli_stmt_execute($stmt_p);
                                $params = mysqli_stmt_get_result($stmt_p);

                                while ($p = mysqli_fetch_assoc($params)) {
                                    $reg = obtenerRegistroSecador($conn, $p['id'], $p['nombre_parametro'], $id_eq_actual);
                                    
                                    $rb = $p['rojo_bajo']; $ab = $p['amarillo_bajo'];
                                    $aa = $p['amarillo_alto']; $ra = $p['rojo_alto'];
                                    $n = $p['nombre_parametro'];
                                ?>
                                <tr>
                                    <td class="fw-bold ps-4 text-dark fs-6"><?= htmlspecialchars($n) ?></td>
                                    
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
                                                    <?= ($ab !== null ? fmt($ab + 0.01) : ($rb !== null ? fmt($rb + 0.01) : 'Min')) ?> a <?= ($aa !== null ? fmt($aa - 0.01) : ($ra !== null ? fmt($ra - 0.01) : 'Max')) ?>
                                                </div>
                                                <?php if ($aa !== null && $ra !== null && $aa < $ra): ?>
                                                    <div>🟡 <strong style="color: #d97706;">Alerta Alta:</strong> <?= fmt($aa) ?> a <?= fmt($ra - 0.01) ?></div>
                                                <?php endif; ?>
                                                <?php if ($ra !== null): ?>
                                                    <div>🔴 <strong class="text-danger">Crítico Alto:</strong> &ge; <?= fmt($ra) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted fw-bold">Evaluación Cualitativa</span>
                                            <span class="textura-desc mt-2">
                                                <b>Firme:</b> Color uniforme, desaparece al presionar.<br>
                                                <b>Plástica:</b> Ligosa, se deforma antes de romper.<br>
                                                <b>Húmeda:</b> Brillo alto, queda marca, pegajosa.<br>
                                                <b>Sobresecada:</b> Se quiebra fácil, "crack".<br>
                                                <b>Quemada:</b> Amarillo intenso/café.
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center px-3">
                                        <?php if ($reg) { 
                                            // LA MAGIA DEL COLOR AUTOMÁTICO
                                            if ($p['tipo_dato'] == 'NUMERICO') {
                                                $badge_color = getSemaforoColorSecador($reg['valor_capturado'], $rb, $ab, $aa, $ra);
                                            } else {
                                                $val_t = $reg['valor_capturado'];
                                                if ($val_t == 'Firme') $badge_color = 'bg-success';
                                                elseif ($val_t == 'Plástica') $badge_color = 'bg-warning text-dark';
                                                elseif ($val_t == 'Húmeda' || $val_t == 'Sobreseca' || $val_t == 'Quemada') $badge_color = 'bg-danger';
                                                else $badge_color = 'bg-dark';
                                            }
                                            
                                            // Si trae F.O. u otro texto forzamos un color
                                            if ($reg['valor_capturado'] === 'F.O.') $badge_color = 'bg-danger text-white';
                                        ?>
                                            <div class="badge-guardado fw-bold text-center <?= $badge_color ?> text-white border-0 shadow-sm">
                                                <i class="bi bi-check-circle-fill me-1"></i><?= htmlspecialchars($reg['valor_capturado']) ?>
                                            </div>
                                            <!-- PASAMOS EL VALOR OCULTO PARA QUE EL OPERADOR PUEDA GUARDAR SU JUSTIFICACIÓN SI APLICA -->
                                            <input type="hidden" name="param_<?= $p['id'] ?>" value="<?= htmlspecialchars($reg['valor_capturado']) ?>">
                                        <?php } else { ?>
                                            <?php if ($p['tipo_dato'] == 'NUMERICO') { ?>
                                                <div class="input-group shadow-sm" style="border-radius: 8px;">
                                                    <input type="number" step="0.01" name="param_<?= $p['id'] ?>" 
                                                        class="form-control text-center fw-bold fs-4 input-semaforo input-equipo-<?= $id_eq_actual ?>" id="param_<?= $p['id'] ?>"
                                                        data-rb="<?= $rb ?>" data-ab="<?= $ab ?>" data-aa="<?= $aa ?>" data-ra="<?= $ra ?>"
                                                        placeholder="0.00" oninput="evaluarSemaforoSecador(this)">
                                                    <button type="button" class="input-group-text bg-white border-danger text-danger fw-bold" style="cursor:pointer;" onclick="ponerFO_individual('param_<?= $p['id'] ?>')">F.O.</button>
                                                </div>
                                            <?php } else { ?>
                                                <select name="param_<?= $p['id'] ?>" class="form-select text-center fw-bold bg-light shadow-sm input-equipo-<?= $id_eq_actual ?> input-semaforo" id="param_<?= $p['id'] ?>" style="height: 55px; font-size: 1.1rem; border-radius: 8px !important;" onchange="evaluarSemaforoTextura(this)">
                                                    <option value="">-- Textura --</option>
                                                    <option value="Firme">Firme</option>
                                                    <option value="Plástica">Plástica</option>
                                                    <option value="Húmeda">Húmeda</option>
                                                    <option value="Sobreseca">Sobreseca</option>
                                                    <option value="Quemada">Quemada</option>
                                                </select>
                                            <?php } ?>
                                        <?php } ?>
                                    </td>
                                    
                                    <!-- AQUI ES DONDE DESAPARECEN LAS OBSERVACIONES SI ESTÁ EN VERDE -->
                                    <td class="pe-4 align-middle text-center">
                                        <?php if ($reg) { 
                                            // Evaluamos si el color devuelto indica una alerta/falla (Rojo o Amarillo)
                                            $es_fo = ($reg['valor_capturado'] === 'F.O.');
                                            $es_alerta = !$es_fo && (strpos($badge_color, 'semaforo-r') !== false || strpos($badge_color, 'semaforo-a') !== false || strpos($badge_color, 'bg-danger') !== false || strpos($badge_color, 'bg-warning') !== false);
                                            
                                            // Si la observación es solo el texto automático, lo ponemos vacío para que el operador escriba
                                            $obs_val = ($reg['observaciones'] === 'Auto-Sync (Reporte Maestro)') ? '' : $reg['observaciones'];

                                            if ($es_alerta) { 
                                        ?>
                                                <textarea name="obs_<?= $p['id'] ?>" class="form-control form-control-sm border-danger bg-danger bg-opacity-10 input-equipo-<?= $id_eq_actual ?>" rows="2" placeholder="⚠️ ¡OBLIGATORIO JUSTIFICAR FALLA/ALERTA!" required><?= htmlspecialchars($obs_val) ?></textarea>
                                        <?php } else { ?>
                                                <span class="text-muted small fw-bold" style="opacity: 0.6;"><i class="bi bi-check-circle text-success"></i> No requiere nota</span>
                                        <?php } ?>
                                        
                                        <?php } else { ?>
                                            <!-- Arranca escondido, el JS lo mostrará solo si el semáforo está rojo/amarillo -->
                                            <textarea name="obs_<?= $p['id'] ?>" class="form-control form-control-sm input-equipo-<?= $id_eq_actual ?>" id="obs_<?= $p['id'] ?>" rows="2" placeholder="Notas..." style="display: none;"></textarea>
                                            <span id="label_ok_<?= $p['id'] ?>" class="text-muted small fw-bold" style="display: block; margin-top: 5px; opacity: 0.6;">- Ingresa un valor -</span>
                                        <?php } ?>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="mb-5">
                    <button type="submit" class="btn btn-dark btn-lg w-100 py-3 fw-bold fs-5 shadow">
                        <i class="bi bi-save-fill me-2"></i> Guardar <?= htmlspecialchars($nombre_eq_actual) ?>
                    </button>
                </div>
            </form>

        </div>
        <?php $first = false; } ?>
    </div>
</div>

<script>
// BOTÓN DE F.O INDIVIDUAL PARA CADA CÁMARA O MOTOR
function ponerFO_individual(idInput) {
    const campo = document.getElementById(idInput);
    if (campo) {
        if (campo.value === "F.O.") {
            campo.type = 'number'; 
            campo.value = "";
        } else {
            campo.type = 'text'; 
            campo.value = "F.O.";
        }
        // Desatamos la misma función del semáforo para que aplique la magia del cuadro de notas
        evaluarSemaforoSecador(campo);
    }
}

// BOTÓN MAESTRO DE F.O DE LA PARTE DE ARRIBA (TODO EL SECADOR)
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
            evaluarSemaforoTextura(el);
        } else if (el.tagName === 'INPUT') {
            if (el.type === 'number' && valor !== '') {
                el.type = 'text';
            } else if (valor === '' && el.type === 'text') {
                el.type = 'number';
            }

            el.value = valor;
            evaluarSemaforoSecador(el);
        }
    });
}

window.marcarEquipo = marcarEquipo;

// MAGIA: APARECER O DESAPARECER LA CAJA DE TEXTO DEPENDIENDO DEL SEMÁFORO (NÚMEROS)
function evaluarSemaforoSecador(input) {
    if (!input) return;

    let paramId = input.id.replace('param_', '');
    let obsInput = document.getElementById('obs_' + paramId);
    let labelOk = document.getElementById('label_ok_' + paramId);

    // Si borran el valor, reseteamos todo
    if (input.value === '') {
        input.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
        input.style.backgroundColor = '';
        input.style.color = '';
        if (obsInput) {
            obsInput.style.display = 'none';
            obsInput.required = false;
            obsInput.value = '';
            obsInput.classList.remove('border-danger', 'border-warning', 'bg-danger', 'bg-warning', 'bg-opacity-10');
        }
        if (labelOk) {
            labelOk.style.display = 'block';
            labelOk.innerHTML = '- Ingresa un valor -';
        }
        return;
    }

    // Si ponen F.O., no es obligatorio justificar y escondemos la caja
    if (input.value === 'F.O.') {
        input.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
        input.style.backgroundColor = '#f8d7da';
        input.style.color = '#dc3545';
        if (obsInput) {
            obsInput.style.display = 'none';
            obsInput.required = false;
            obsInput.value = '';
            obsInput.classList.remove('border-danger', 'border-warning', 'bg-danger', 'bg-warning', 'bg-opacity-10');
        }
        if (labelOk) {
            labelOk.style.display = 'block';
            labelOk.innerHTML = '- F.O. (No requiere nota) -';
        }
        return;
    } else {
        input.style.backgroundColor = '';
        input.style.color = '';
    }
    
    const val = parseFloat(input.value);
    let rb = parseFloat(input.getAttribute('data-rb')); if(isNaN(rb)) rb = -999999;
    let ab = parseFloat(input.getAttribute('data-ab')); if(isNaN(ab)) ab = -999999;
    let aa = parseFloat(input.getAttribute('data-aa')); if(isNaN(aa)) aa = 999999;
    let ra = parseFloat(input.getAttribute('data-ra')); if(isNaN(ra)) ra = 999999;

    input.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
    if (obsInput) obsInput.classList.remove('border-danger', 'border-warning', 'bg-danger', 'bg-warning', 'bg-opacity-10');

    // LÓGICA DE ROJO (Falla Crítica)
    if (val <= rb || val >= ra) { 
        input.classList.add('semaforo-r'); 
        if (obsInput && !obsInput.readOnly) {
            obsInput.style.display = 'block';
            obsInput.required = true;
            obsInput.classList.add('border-danger', 'bg-danger', 'bg-opacity-10');
            obsInput.placeholder = "⚠️ ¡OBLIGATORIO JUSTIFICAR FALLA!";
        }
        if (labelOk) labelOk.style.display = 'none';
    } 
    // LÓGICA DE AMARILLO (Alerta)
    else if ((val > rb && val <= ab) || (val >= aa && val < ra)) { 
        input.classList.add('semaforo-a'); 
        if (obsInput && !obsInput.readOnly) {
            obsInput.style.display = 'block';
            obsInput.required = true;
            obsInput.classList.add('border-warning', 'bg-warning', 'bg-opacity-10');
            obsInput.placeholder = "⚠️ ¡OBLIGATORIO JUSTIFICAR ALERTA!";
        }
        if (labelOk) labelOk.style.display = 'none';
    } 
    // LÓGICA DE VERDE (Óptimo)
    else { 
        input.classList.add('semaforo-v'); 
        if (obsInput) {
            obsInput.style.display = 'none';
            obsInput.required = false;
            obsInput.value = '';
        }
        if (labelOk) {
            labelOk.style.display = 'block';
            labelOk.innerHTML = '<i class="bi bi-check-circle text-success"></i> Óptimo (No requiere nota)';
        }
    }
}

// MAGIA: APARECER O DESAPARECER LA CAJA DE TEXTO (SELECT DE TEXTURA)
function evaluarSemaforoTextura(select) {
    if (!select) return;
    
    let paramId = select.id.replace('param_', '');
    let obsInput = document.getElementById('obs_' + paramId);
    let labelOk = document.getElementById('label_ok_' + paramId);
    
    select.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
    if (obsInput) {
        obsInput.style.display = 'none';
        obsInput.required = false;
        obsInput.classList.remove('border-danger', 'border-warning', 'bg-danger', 'bg-warning', 'bg-opacity-10');
    }

    let val = select.value;
    if (val === '') {
        if (labelOk) { labelOk.style.display = 'block'; labelOk.innerHTML = '- Selecciona un valor -'; }
        return;
    }

    if (val === 'Firme') { // VERDE
        select.classList.add('semaforo-v');
        if (labelOk) { labelOk.style.display = 'block'; labelOk.innerHTML = '<i class="bi bi-check-circle text-success"></i> Óptimo (No requiere nota)'; }
    } else if (val === 'Plástica') { // AMARILLO
        select.classList.add('semaforo-a');
        if (obsInput && !obsInput.readOnly) {
            obsInput.style.display = 'block';
            obsInput.required = true;
            obsInput.classList.add('border-warning', 'bg-warning', 'bg-opacity-10');
            obsInput.placeholder = "⚠️ ¡OBLIGATORIO JUSTIFICAR ALERTA!";
        }
        if (labelOk) labelOk.style.display = 'none';
    } else if (val === 'Húmeda' || val === 'Sobreseca' || val === 'Quemada') { // ROJO
        select.classList.add('semaforo-r');
        if (obsInput && !obsInput.readOnly) {
            obsInput.style.display = 'block';
            obsInput.required = true;
            obsInput.classList.add('border-danger', 'bg-danger', 'bg-opacity-10');
            obsInput.placeholder = "⚠️ ¡OBLIGATORIO JUSTIFICAR FALLA!";
        }
        if (labelOk) labelOk.style.display = 'none';
    } else if (val === 'F.O.' || val === 'LAVADO') {
        select.style.backgroundColor = '#f8d7da';
        select.style.color = '#dc3545';
        if (labelOk) { labelOk.style.display = 'block'; labelOk.innerHTML = '- No requiere nota -'; }
    }
}
</script>