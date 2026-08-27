<?php
// Progel_core/equipos/bloque_chillers.php
// MÓDULO EXCLUSIVO PARA CHILLERS VOTATOR (IDs 81 y 82) CON DOBLE DELTA AUTOMÁTICO

if (!function_exists('obtenerRegistroChillerVotatorID')) {
    function obtenerRegistroChillerVotatorID($conn, $param_id) {
        $q = "SELECT valor_capturado, observaciones FROM bitacora_lecturas 
              WHERE parametro_id = $param_id 
              AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 90 MINUTE) 
              ORDER BY id DESC LIMIT 1";
        $res = mysqli_query($conn, $q);
        return mysqli_fetch_assoc($res);
    }
}

if (!function_exists('getSemaforoColorCV')) {
    function getSemaforoColorCV($val, $rb, $ab, $aa, $ra) {
        if (!is_numeric($val)) return 'bg-secondary';
        $v = (float)$val;
        $rb = ($rb !== null) ? (float)$rb : -999999;
        $ab = ($ab !== null) ? (float)$ab : -999999;
        $aa = ($aa !== null) ? (float)$aa : 999999;
        $ra = ($ra !== null) ? (float)$ra : 999999;

        if ($v <= $rb || $v >= $ra) return 'semaforo-r';
        if (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) return 'semaforo-a';
        return 'semaforo-v';
    }
}

if (!function_exists('fmtCV')) {
    function fmtCV($val) { 
        return ($val !== null && is_numeric($val)) ? number_format((float)$val, 2, '.', '') : '-'; 
    }
}

// CONSULTA DIRECTA POR LOS IDs 81 Y 82
$ids_objetivo = [81, 82];
$q_chillers_v = mysqli_query($conn, "SELECT id, nombre FROM equipos WHERE id IN (" . implode(',', $ids_objetivo) . ") ORDER BY id ASC");

$pestañas_chiller_v = [];
if ($q_chillers_v) {
    while($c = mysqli_fetch_assoc($q_chillers_v)){
        $pestañas_chiller_v[] = [
            'id' => $c['id'],
            'nombre' => $c['nombre']
        ];
    }
}

$equipo_id_solicitado = intval($_GET['id'] ?? 0);
?>

<style>
    /* BLINDAJE PARA QUE EL DELTA SÍ SE PINTE Y NO SE QUEDE GRIS */
    .input-delta-auto { background-color: #f8f9fa !important; color: #495057 !important; cursor: not-allowed; font-weight: bold; border: 1px dashed #adb5bd !important; }
    .input-delta-auto.semaforo-v { background-color: #28a745 !important; color: #ffffff !important; border: 2px solid #1e7e34 !important; }
    .input-delta-auto.semaforo-a { background-color: #ffc107 !important; color: #000000 !important; border: 2px solid #d39e00 !important; }
    .input-delta-auto.semaforo-r { background-color: #dc3545 !important; color: #ffffff !important; border: 2px solid #a8363b !important; }
</style>

<div class="container-fluid px-0">
    <div class="mb-4 text-end d-flex justify-content-between align-items-center bg-white p-3 rounded-3 shadow-sm border-start border-4 border-info">
        <div>
            <h5 class="m-0 fw-bold text-dark"><i class="bi bi-snow text-info me-2"></i>Control de Chillers Votator</h5>
            <small class="text-muted">Módulo de Temperaturas y Doble Delta (Maestro/Esclavo)</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-outline-dark btn-sm fw-bold" disabled>
                <i class="bi bi-person-badge"></i> Nómina: <span class="text-dark"><?= htmlspecialchars($_SESSION['nomina']) ?></span>
            </button>
        </div>
    </div>

    <ul class="nav nav-pills flex-wrap mb-4 gap-2 justify-content-center" id="pills-tab-chillers-votator" role="tablist">
        <?php foreach($pestañas_chiller_v as $index => $tab): 
            $es_active = ($equipo_id_solicitado == $tab['id']) || ($equipo_id_solicitado == 0 && $index == 0);
        ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-bold fs-5 shadow-sm px-5 py-2 <?= $es_active ? 'active' : '' ?>" 
                        id="tab-btn-<?= $tab['id'] ?>" 
                        data-bs-toggle="pill" 
                        data-bs-target="#tab-content-chiller-v-<?= $tab['id'] ?>" 
                        type="button" role="tab">
                    <i class="bi bi-snow me-1"></i><?= htmlspecialchars($tab['nombre']) ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content" id="pills-tabContent-chillers-votator">
        <?php foreach($pestañas_chiller_v as $index => $tab): 
            $eq_id = $tab['id'];
            $eq_nombre = $tab['nombre'];
            $es_active = ($equipo_id_solicitado == $eq_id) || ($equipo_id_solicitado == 0 && $index == 0);
        ?>
            <div class="tab-pane fade <?= $es_active ? 'show active' : '' ?>" id="tab-content-chiller-v-<?= $eq_id ?>" role="tabpanel">
                <form action="guardar.php" method="POST">
                    <input type="hidden" name="equipo_id" value="<?= $eq_id ?>">
                    <input type="hidden" name="frecuencia_tab" value="CADA 2 HORAS">
                    <input type="hidden" name="numero_nomina" value="<?= htmlspecialchars($_SESSION['nomina'] ?? '') ?>">

                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                        <div class="card-header bg-dark text-white py-3 px-4 fw-bold d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div><span class="fs-5 text-light"><i class="bi bi-table me-2"></i> Captura de <?= htmlspecialchars($eq_nombre) ?></span></div>
                            
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <div class="btn-group btn-group-sm shadow-sm">
                                    <button type="button" class="btn btn-warning btn-sm text-dark fw-bold px-3" onclick="marcarEquipoCV('MTTO', <?= $eq_id ?>); calcularAutoDeltas(<?= $eq_id ?>);">MTTO</button>
                                    <button type="button" class="btn btn-info text-white btn-sm fw-bold px-3" onclick="marcarEquipoCV('LAVADO', <?= $eq_id ?>); calcularAutoDeltas(<?= $eq_id ?>);">LAVADO</button>
                                    <button type="button" class="btn btn-outline-light btn-sm fw-bold px-3" onclick="marcarEquipoCV('', <?= $eq_id ?>); calcularAutoDeltas(<?= $eq_id ?>);">Limpiar</button>
                                </div>
                                <a href="historial.php?id=<?= $eq_id ?>" class="btn btn-secondary btn-sm fw-bold shadow-sm">
                                    <i class="bi bi-clock-history"></i> Historial
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
                                    mysqli_stmt_bind_param($stmt_p, "i", $eq_id);
                                    mysqli_stmt_execute($stmt_p);
                                    $params = mysqli_stmt_get_result($stmt_p);

                                    while ($p = mysqli_fetch_assoc($params)):
                                        $reg = obtenerRegistroChillerVotatorID($conn, $p['id']);
                                        $rb = $p['rojo_bajo']; $ab = $p['amarillo_bajo'];
                                        $aa = $p['amarillo_alto']; $ra = $p['rojo_alto'];
                                        $n = $p['nombre_parametro'];
                                        
                                        // Normalización limpia para PHP
                                        $n_low = mb_strtolower($n, 'UTF-8');
                                        $n_low = str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $n_low);

                                        $clase_extra = "temp-param";
                                        $es_delta = (strpos($n_low, 'delta') !== false);
                                    ?>
                                    <tr>
                                        <td class="fw-bold ps-4 text-dark fs-6"><?= htmlspecialchars($n) ?></td> 
                                        
                                        <td class="text-secondary align-middle">
                                            <?php if ($p['tipo_dato'] == 'NUMERICO' && ($rb !== null || $ra !== null)): ?>
                                                <div class="caja-limites shadow-sm">
                                                    <?php if ($rb !== null): ?> <div>🔴 <strong class="text-danger">Crítico Bajo:</strong> &le; <?= fmtCV($rb) ?></div> <?php endif; ?>
                                                    <?php if ($rb !== null && $ab !== null): ?> <div>🟡 <strong style="color: #d97706;">Alerta Baja:</strong> &gt; <?= fmtCV($rb) ?> a <?= fmtCV($ab) ?></div> <?php endif; ?>
                                                    <div>🟢 <strong class="text-success">Óptimo:</strong> &gt; <?= ($ab !== null) ? fmtCV($ab) : 'Min' ?> a &lt; <?= ($aa !== null) ? fmtCV($aa) : 'Max' ?></div>
                                                    <?php if ($aa !== null && $ra !== null): ?> <div>🟡 <strong style="color: #d97706;">Alerta Alta:</strong> &ge; <?= fmtCV($aa) ?> a &lt; <?= fmtCV($ra) ?></div> <?php endif; ?>
                                                    <?php if ($ra !== null): ?> <div>🔴 <strong class="text-danger">Crítico Alto:</strong> &ge; <?= fmtCV($ra) ?></div> <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge bg-secondary px-3 py-2">Cualitativo / Control</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-center px-3">
                                            <?php if ($reg): 
                                                if ($p['tipo_dato'] == 'NUMERICO') {
                                                    $badge_color = getSemaforoColorCV($reg['valor_capturado'], $rb, $ab, $aa, $ra);
                                                } else {
                                                    $val_t = strtolower(trim($reg['valor_capturado']));
                                                    if ($val_t == 'realizada' || $val_t == 'sí' || $val_t == 'si') $badge_color = 'bg-success';
                                                    else if ($val_t == 'no aplica' || $val_t == 'n/a') $badge_color = 'bg-secondary';
                                                    else $badge_color = 'bg-dark';
                                                }

                                                if ($reg['valor_capturado'] === 'F.O.') $badge_color = 'bg-danger text-white';
                                                else if ($reg['valor_capturado'] === 'LAVADO' || $reg['valor_capturado'] === 'MTTO') $badge_color = 'bg-info text-white';
                                            ?>
                                                <div class="badge-guardado fw-bold text-center <?= $badge_color ?> text-white border-0 shadow-sm mx-auto">
                                                    <i class="bi bi-lock-fill me-1"></i><?= htmlspecialchars($reg['valor_capturado']) ?>
                                                </div>
                                            <?php else: ?>
                                                <?php if ($p['tipo_dato'] == 'NUMERICO'): ?>
                                                    <div class="input-group shadow-sm" style="border-radius: 8px;">
                                                        <!-- MAGIA: AQUÍ LLAMAMOS A forzarNegativoCV EN EL onchange -->
                                                        <input type="number" step="0.01" name="param_<?= $p['id'] ?>" 
                                                            class="form-control text-center fw-bold fs-4 input-semaforo input-equipo-<?= $eq_id ?> <?= $clase_extra ?>" id="param_<?= $p['id'] ?>"
                                                            data-rb="<?= $rb ?>" data-ab="<?= $ab ?>" data-aa="<?= $aa ?>" data-ra="<?= $ra ?>" data-nombre="<?= htmlspecialchars($n) ?>"
                                                            placeholder="<?= $es_delta ? 'Auto...' : '0.00' ?>" 
                                                            oninput="evaluarSemaforoCV(this); calcularAutoDeltas(<?= $eq_id ?>);" 
                                                            onchange="forzarNegativoCV(this); evaluarSemaforoCV(this); calcularAutoDeltas(<?= $eq_id ?>);"
                                                            <?= $es_delta ? 'readonly tabindex="-1" class="input-delta-auto"' : '' ?>>
                                                            
                                                        <?php if(!$es_delta): ?>
                                                            <button type="button" class="input-group-text bg-white border-danger text-danger fw-bold" style="cursor:pointer;" onclick="ponerFO_individual_cv('param_<?= $p['id'] ?>'); calcularAutoDeltas(<?= $eq_id ?>);">F.O.</button>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <select name="param_<?= $p['id'] ?>" class="form-select text-center fw-bold bg-light shadow-sm input-equipo-<?= $eq_id ?> input-semaforo" id="param_<?= $p['id'] ?>" style="height: 55px; font-size: 1.1rem; border-radius: 8px !important;" onchange="evaluarSemaforoCVText(this)">
                                                        <option value="">-- Seleccionar --</option>
                                                        <option value="Realizada">Realizada</option>
                                                        <option value="No Aplica">No Aplica</option>
                                                    </select>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>

                                        <td class="pe-4 align-middle text-center">
                                            <?php if ($reg): 
                                                $es_fo = ($reg['valor_capturado'] === 'F.O.' || $reg['valor_capturado'] === 'LAVADO' || $reg['valor_capturado'] === 'MTTO');
                                                $es_alerta = !$es_fo && (strpos($badge_color, 'semaforo-r') !== false || strpos($badge_color, 'semaforo-a') !== false || strpos($badge_color, 'bg-danger') !== false || strpos($badge_color, 'bg-warning') !== false);
                                                $obs_val = $reg['observaciones'];

                                                if ($es_alerta) { 
                                            ?>
                                                    <textarea name="obs_<?= $p['id'] ?>" class="form-control form-control-sm border-danger bg-danger bg-opacity-10 input-equipo-<?= $eq_id ?>" rows="2" placeholder="⚠️ ¡OBLIGATORIO JUSTIFICAR FALLA/ALERTA!" required><?= htmlspecialchars($obs_val) ?></textarea>
                                            <?php } else { ?>
                                                    <span class="text-muted small fw-bold" style="opacity: 0.6;"><i class="bi bi-check-circle text-success"></i> No requiere nota</span>
                                            <?php } ?>
                                            
                                            <?php else: ?>
                                                <textarea name="obs_<?= $p['id'] ?>" class="form-control form-control-sm input-equipo-<?= $eq_id ?>" id="obs_<?= $p['id'] ?>" rows="2" placeholder="Notas..." style="display: none;"></textarea>
                                                <span id="label_ok_<?= $p['id'] ?>" class="text-muted small fw-bold" style="display: block; margin-top: 5px; opacity: 0.6;">- Ingresa un valor -</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="mb-5">
                        <button type="submit" class="btn btn-dark btn-lg w-100 py-3 fw-bold fs-5 shadow" style="--bs-btn-border-radius: .75rem;">
                            <i class="bi bi-save-fill me-2 text-warning"></i> Guardar <?= htmlspecialchars($eq_nombre) ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
// ==========================================
// CEREBRO INDEPENDIENTE A PRUEBA DE BALAS
// ==========================================

// 1. FORZAR NEGATIVOS
function forzarNegativoCV(input) {
    if (!input) return;
    let rb = parseFloat(input.getAttribute('data-rb'));
    let aa = parseFloat(input.getAttribute('data-aa'));
    
    // Si la máquina tiene límites negativos y el usuario metió un número mayor a cero
    if (rb < 0 || aa < 0) {
        let val = parseFloat(input.value);
        if (!isNaN(val) && val > 0) {
            input.value = (val * -1).toFixed(2);
        }
    }
}

// 2. BOTÓN F.O.
function ponerFO_individual_cv(idInput) {
    const campo = document.getElementById(idInput);
    if (campo) {
        if (campo.value === "F.O.") {
            campo.type = 'number'; campo.value = "";
        } else {
            campo.type = 'text'; campo.value = "F.O.";
        }
        evaluarSemaforoCV(campo);
    }
}

// 3. BOTONES MAESTROS ARRIBA
function marcarEquipoCV(valor, equipoId) {
    if (!equipoId) return;
    const campos = document.querySelectorAll('input.input-equipo-' + equipoId + ', select.input-equipo-' + equipoId);
    
    campos.forEach(function(el) {
        if (!el) return;
        if (el.tagName === 'SELECT') {
            let encontrada = false;
            for (let i = 0; i < el.options.length; i++) {
                if (el.options[i].value === valor) { encontrada = true; break; }
            }
            if (!encontrada && valor !== '') {
                const opt = document.createElement('option');
                opt.value = valor; opt.text = valor; el.add(opt);
            }
            el.value = valor;
            evaluarSemaforoCVText(el);
        } else if (el.tagName === 'INPUT') {
            if (el.type === 'number' && valor !== '') el.type = 'text';
            else if (valor === '' && el.type === 'text') el.type = 'number';
            
            el.value = valor;
            evaluarSemaforoCV(el);
        }
    });
}

// 4. EVALUADOR DE COLORES Y CAJITAS
function evaluarSemaforoCV(input) {
    if (!input) return;
    let paramId = input.id.replace('param_', '');
    let obsInput = document.getElementById('obs_' + paramId);
    let labelOk = document.getElementById('label_ok_' + paramId);

    input.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
    input.style.backgroundColor = ''; input.style.color = '';
    if (obsInput) { obsInput.style.display = 'none'; obsInput.required = false; obsInput.classList.remove('border-danger', 'border-warning', 'bg-danger', 'bg-warning', 'bg-opacity-10'); }

    if (input.value === '') {
        if (labelOk) { labelOk.style.display = 'block'; labelOk.innerHTML = '- Ingresa un valor -'; }
        return;
    }

    if (input.value === 'F.O.' || input.value === 'LAVADO' || input.value === 'MTTO') {
        input.style.backgroundColor = (input.value === 'F.O.') ? '#f8d7da' : '#cff4fc';
        input.style.color = (input.value === 'F.O.') ? '#dc3545' : '#055160';
        if (labelOk) { labelOk.style.display = 'block'; labelOk.innerHTML = `- ${input.value} (No requiere nota) -`; }
        return;
    } 
    
    const val = parseFloat(input.value);
    let rb = parseFloat(input.getAttribute('data-rb')); if(isNaN(rb)) rb = -999999;
    let ab = parseFloat(input.getAttribute('data-ab')); if(isNaN(ab)) ab = -999999;
    let aa = parseFloat(input.getAttribute('data-aa')); if(isNaN(aa)) aa = 999999;
    let ra = parseFloat(input.getAttribute('data-ra')); if(isNaN(ra)) ra = 999999;

    if (val <= rb || val >= ra) { 
        input.classList.add('semaforo-r'); 
        if (obsInput && !obsInput.readOnly) {
            obsInput.style.display = 'block'; obsInput.required = true;
            obsInput.classList.add('border-danger', 'bg-danger', 'bg-opacity-10');
            obsInput.placeholder = "⚠️ ¡OBLIGATORIO JUSTIFICAR FALLA!";
        }
        if (labelOk) labelOk.style.display = 'none';
    } 
    else if ((val > rb && val <= ab) || (val >= aa && val < ra)) { 
        input.classList.add('semaforo-a'); 
        if (obsInput && !obsInput.readOnly) {
            obsInput.style.display = 'block'; obsInput.required = true;
            obsInput.classList.add('border-warning', 'bg-warning', 'bg-opacity-10');
            obsInput.placeholder = "⚠️ ¡OBLIGATORIO JUSTIFICAR ALERTA!";
        }
        if (labelOk) labelOk.style.display = 'none';
    } 
    else { 
        input.classList.add('semaforo-v'); 
        if (labelOk) { labelOk.style.display = 'block'; labelOk.innerHTML = '<i class="bi bi-check-circle text-success"></i> Óptimo'; }
    }
}

function evaluarSemaforoCVText(select) {
    if (!select) return;
    let paramId = select.id.replace('param_', '');
    let obsInput = document.getElementById('obs_' + paramId);
    let labelOk = document.getElementById('label_ok_' + paramId);
    
    select.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
    select.style.backgroundColor = ''; select.style.color = '';

    if (obsInput) { obsInput.style.display = 'none'; obsInput.required = false; obsInput.classList.remove('border-danger', 'border-warning', 'bg-danger', 'bg-warning', 'bg-opacity-10'); }
    
    let val = select.value;
    if (val === '') {
        if (labelOk) { labelOk.style.display = 'block'; labelOk.innerHTML = '- Selecciona un valor -'; }
        return;
    }

    if (val === 'Realizada' || val === 'No Aplica') { 
        select.classList.add('semaforo-v');
        if (labelOk) { labelOk.style.display = 'block'; labelOk.innerHTML = '<i class="bi bi-check-circle text-success"></i> Registrado'; }
    } else if (val === 'F.O.' || val === 'LAVADO' || val === 'MTTO') {
        select.style.backgroundColor = (val === 'F.O.') ? '#f8d7da' : '#cff4fc';
        select.style.color = (val === 'F.O.') ? '#dc3545' : '#055160';
        if (labelOk) { labelOk.style.display = 'block'; labelOk.innerHTML = `- ${val} -`; }
    }
}

// 5. CÁLCULO DE DELTAS
function calcularAutoDeltas(equipoId) {
    const inputs = document.querySelectorAll('.input-equipo-' + equipoId);
    
    let inM = null, outM = null, deltaM = null;
    let inE = null, outE = null, deltaE = null;
    let inGen = null, outGen = null, deltaGen = null;

    inputs.forEach(input => {
        let nombre = input.getAttribute('data-nombre');
        if(!nombre) return;
        
        nombre = nombre.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

        if (nombre.includes('maestro')) {
            if (nombre.includes('inyecci') || nombre.includes('entrada') || nombre.match(/\bin\b/)) inM = input;
            if (nombre.includes('retorno') || nombre.includes('salida') || nombre.match(/\bout\b/)) outM = input;
            if (nombre.includes('delta') || nombre.includes('diferencial')) deltaM = input;
        }
        else if (nombre.includes('esclavo')) {
            if (nombre.includes('inyecci') || nombre.includes('entrada') || nombre.match(/\bin\b/)) inE = input;
            if (nombre.includes('retorno') || nombre.includes('salida') || nombre.match(/\bout\b/)) outE = input;
            if (nombre.includes('delta') || nombre.includes('diferencial')) deltaE = input;
        }
        else {
            if (nombre.includes('inyecci') || nombre.includes('entrada') || nombre.match(/\bin\b/)) inGen = input;
            if (nombre.includes('retorno') || nombre.includes('salida') || nombre.match(/\bout\b/)) outGen = input;
            if (nombre.includes('delta') || nombre.includes('diferencial')) deltaGen = input;
        }
    });

    function restarYEvaluar(input1, input2, inputResultado) {
        if (input1 && input2 && inputResultado) {
            let v1 = parseFloat(input1.value);
            let v2 = parseFloat(input2.value);
            if (!isNaN(v1) && !isNaN(v2)) {
                inputResultado.value = Math.abs(v1 - v2).toFixed(2);
                evaluarSemaforoCV(inputResultado);
            } else {
                inputResultado.value = '';
                inputResultado.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
                let obsInput = document.getElementById('obs_' + inputResultado.id.replace('param_', ''));
                let labelOk = document.getElementById('label_ok_' + inputResultado.id.replace('param_', ''));
                if(obsInput) { obsInput.style.display = 'none'; obsInput.required = false; }
                if(labelOk) { labelOk.style.display = 'block'; labelOk.innerHTML = '- Auto -'; }
            }
        }
    }

    restarYEvaluar(inM, outM, deltaM);
    restarYEvaluar(inE, outE, deltaE);
    restarYEvaluar(inGen, outGen, deltaGen);
}

document.addEventListener('DOMContentLoaded', function() {
    calcularAutoDeltas(81);
    calcularAutoDeltas(82);

    const equipoIdSolicitado = <?= $equipo_id_solicitado ?>;
    if (equipoIdSolicitado > 0) {
        const targetTabBtn = document.getElementById('tab-btn-' + equipoIdSolicitado);
        if (targetTabBtn) { new bootstrap.Tab(targetTabBtn).show(); }
    }
});
</script>