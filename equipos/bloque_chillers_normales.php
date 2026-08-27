<?php
// Progel_core/equipos/bloque_chillers_normales.php

function obtenerRegistroChillerInteligente($conn, $param_id, $frecuencia) {
    $intervalo = ($frecuencia == 'SEMANAL') ? '7 DAY' : '12 HOUR';
    $q = "SELECT valor_capturado, observaciones FROM bitacora_lecturas 
          WHERE parametro_id = $param_id 
          AND fecha_registro >= DATE_SUB(NOW(), INTERVAL $intervalo) 
          ORDER BY id DESC LIMIT 1";
    $res = mysqli_query($conn, $q);
    return mysqli_fetch_assoc($res);
}

function getSemaforoColor($val, $rb, $ab, $aa, $ra) {
    if (!is_numeric($val)) return 'bg-secondary';
    $v = (float)$val;
    $rb = $rb ?? -999999; $ab = $ab ?? -999999;
    $aa = $aa ?? 999999; $ra = $ra ?? 999999;
    if ($v <= $rb || $v >= $ra) return 'semaforo-r';
    if (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) return 'semaforo-a';
    return 'semaforo-v';
}

function fmt($val) { return number_format((float)$val, 2, '.', ''); }

$q_frec = mysqli_query($conn, "SELECT DISTINCT frecuencia FROM parametros WHERE equipo_id = $equipo_id ORDER BY frecuencia ASC");
$frecuencias = [];
while($f = mysqli_fetch_assoc($q_frec)){ $frecuencias[] = $f['frecuencia']; }
if(empty($frecuencias)) $frecuencias[] = 'DIARIO';
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
    .nav-pills .nav-link.active { background-color: #343a40; color: white; border-color: #343a40; }
</style>

<div class="container-fluid px-0">
    <div class="mb-4 text-end d-flex justify-content-between align-items-center bg-white p-3 rounded-3 shadow-sm border-start border-4 border-dark">
        <div>
            <h5 class="m-0 fw-bold text-dark">Control de Chillers Normales</h5>
            <small class="text-muted">Zona Negra - Temperaturas y Deltas</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
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
            <button type="button" class="btn btn-outline-dark btn-sm fw-bold" disabled>
                Nómina: <span class="text-dark"><?= htmlspecialchars($_SESSION['nomina']) ?></span>
            </button>
            <a href="historial.php?id=<?= $equipo_id ?>" class="btn btn-secondary btn-sm fw-bold shadow-sm">
                Historial
            </a>
        </div>
    </div>

    <ul class="nav nav-pills flex-wrap mb-4 gap-2 justify-content-center" role="tablist">
        <?php foreach($frecuencias as $index => $frec): 
            $clase_activa = ($index == 0) ? 'active' : '';
        ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $clase_activa ?> fw-bold fs-6 shadow-sm px-4 py-2" data-bs-toggle="pill" data-bs-target="#tab_<?= md5($frec) ?>" type="button" role="tab">
                    Rutina <?= htmlspecialchars($frec) ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php foreach($frecuencias as $index => $frec): 
            $clase_pane = ($index == 0) ? 'show active' : '';
        ?>
        <div class="tab-pane fade <?= $clase_pane ?>" id="tab_<?= md5($frec) ?>" role="tabpanel">
            <form action="guardar.php" method="POST">
                <input type="hidden" name="equipo_id" value="<?= $equipo_id ?>">
                <input type="hidden" name="frecuencia_tab" value="<?= htmlspecialchars($frec) ?>">
                <input type="hidden" name="numero_nomina" value="<?= htmlspecialchars($_SESSION['nomina']) ?>">

                <?php
                $q_grupos = mysqli_query($conn, "SELECT DISTINCT grupo FROM parametros WHERE equipo_id = $equipo_id AND frecuencia = '$frec' ORDER BY grupo ASC");
                while($g = mysqli_fetch_assoc($q_grupos)):
                    $grupo_nombre = $g['grupo'] ?? 'General';
                ?>
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-dark text-white py-2 px-3 fw-bold">
                        <?= htmlspecialchars($grupo_nombre) ?>
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
                                $stmt_p = mysqli_prepare($conn, "SELECT * FROM parametros WHERE equipo_id = ? AND frecuencia = ? AND grupo = ? ORDER BY id ASC");
                                mysqli_stmt_bind_param($stmt_p, "iss", $equipo_id, $frec, $grupo_nombre);
                                mysqli_stmt_execute($stmt_p);
                                $params = mysqli_stmt_get_result($stmt_p);

                                while ($p = mysqli_fetch_assoc($params)): 
                                    $reg = obtenerRegistroChillerInteligente($conn, $p['id'], $frec);
                                    $rb = $p['rojo_bajo']; $ab = $p['amarillo_bajo'];
                                    $aa = $p['amarillo_alto']; $ra = $p['rojo_alto'];
                                    $n_lower = strtolower($p['nombre_parametro']);
                                    
                                    $clase_extra = "input-semaforo";
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
                                    <td class="fw-bold ps-3 text-dark"><?= htmlspecialchars($p['nombre_parametro']) ?></td>
                                    
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
                                        <?php if ($reg): 
                                            $badge_color = getSemaforoColor($reg['valor_capturado'], $rb, $ab, $aa, $ra);
                                        ?>
                                            <div class="badge-guardado fw-bold text-center <?= $badge_color ?> text-white border-0 shadow-sm">
                                                <?= htmlspecialchars($reg['valor_capturado']) ?>
                                            </div>
                                        <?php else: ?>
                                            <?php if ($p['tipo_dato'] == 'NUMERICO'): ?>
                                                <input type="number" step="0.01" name="param_<?= $p['id'] ?>" 
                                                    class="form-control text-center fw-bold fs-5 shadow-sm <?= $clase_extra ?> input-equipo-<?= $equipo_id ?>" id="param_<?= $p['id'] ?>"
                                                    data-rb="<?= $rb ?>" data-ab="<?= $ab ?>" data-aa="<?= $aa ?>" data-ra="<?= $ra ?>"
                                                    oninput="evaluarSemaforoChiller(this); calcularDeltasAutomaticas();"
                                                    onchange="forzarNegativoChiller(this); evaluarSemaforoChiller(this);"
                                                    <?= $es_delta ? 'readonly tabindex="-1" placeholder="Auto..."' : '' ?>>
                                            <?php else: ?>
                                                <select name="param_<?= $p['id'] ?>" class="form-select text-center fw-bold bg-light input-equipo-<?= $equipo_id ?>" id="param_<?= $p['id'] ?>">
                                                    <option value="">Seleccionar...</option>
                                                    <option value="Sí">Sí</option>
                                                    <option value="No">No</option>
                                                    <option value="N/A">N/A</option>
                                                </select>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-3">
                                        <?php if ($reg): ?>
                                            <div class="text-muted small fst-italic"><?= htmlspecialchars($reg['observaciones']) ?: 'Sin observaciones' ?></div>
                                        <?php else: ?>
                                            <textarea name="obs_<?= $p['id'] ?>" class="form-control form-control-sm input-equipo-<?= $equipo_id ?>" id="obs_<?= $p['id'] ?>" rows="1" placeholder="Notas..."></textarea>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endwhile; ?>

                <div class="mt-4 p-2">
                    <button type="submit" class="btn btn-dark btn-lg w-100 py-3 fw-bold fs-5 shadow" style="--bs-btn-border-radius: .75rem;">
                        Guardar Avances <?= htmlspecialchars($frec) ?>
                    </button>
                </div>
            </form>
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
    
    // Si los límites son negativos, y el operador teclea un positivo, lo corregimos.
    if (rb < 0 || aa < 0) {
        let val = parseFloat(input.value);
        if (!isNaN(val) && val > 0) {
            input.value = (val * -1).toFixed(2);
        }
    }
    // ¡AQUÍ ESTÁ LA MAGIA! Obligamos al sistema a recalcular el delta DESPUÉS de poner el signo negativo
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
            
            // Solo calcula si ambos tienen números válidos
            if (!isNaN(valIn) && !isNaN(valOut)) {
                // Cálculo invencible con valor absoluto
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
document.addEventListener('DOMContentLoaded', calcularDeltasAutomaticas);
</script>