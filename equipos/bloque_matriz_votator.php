<?php
// Progel_core/equipos/bloque_matriz_votator.php
// VISTA DEFINITIVA Y CORREGIDA PARA VOTATOR (IDs 81, 82, 83)

function obtenerRegistroExistenteVotator($conn, $param_id) {
    $q = "SELECT valor_capturado, observaciones FROM bitacora_lecturas 
          WHERE parametro_id = $param_id 
          AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 90 MINUTE) 
          ORDER BY id DESC LIMIT 1";
    $res = mysqli_query($conn, $q);
    return mysqli_fetch_assoc($res);
}

function getSemaforoColorVotator($val, $rb, $ab, $aa, $ra) {
    if (!is_numeric($val)) return 'bg-secondary';
    $v = (float)$val;
    $rb = $rb ?? -999999; $ab = $ab ?? -999999;
    $aa = $aa ?? 999999; $ra = $ra ?? 999999;
    if ($v <= $rb || $v >= $ra) return 'semaforo-r';
    if (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) return 'semaforo-a';
    return 'semaforo-v';
}

function fmt($val) { return number_format((float)$val, 2, '.', ''); }

// Normalizador universal de texto para evitar fallos por acentos (Inyección -> inyeccion)
function normalizarTextoVotator($texto) {
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = str_replace(['á','é','í','ó','ú','ñ','Á','É','Í','Ó','Ú','Ñ'], ['a','e','i','o','u','n','a','e','i','o','u','n'], $texto);
    return $texto;
}

// Datos del equipo
$stmt_eq = mysqli_prepare($conn, "SELECT * FROM equipos WHERE id = ?");
mysqli_stmt_bind_param($stmt_eq, "i", $equipo_id);
mysqli_stmt_execute($stmt_eq);
$info_equipo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_eq));
$nombre_equipo = $info_equipo['nombre'] ?? 'Control Votator';

$color_header = 'bg-primary';
if ($equipo_id == 82) $color_header = 'bg-info';
if ($equipo_id == 83) $color_header = 'bg-danger';
?>

<style>
    .input-semaforo { transition: background-color 0.3s ease, color 0.3s ease; border-radius: 8px !important; height: 55px; }
    .input-semaforo:not(.semaforo-v):not(.semaforo-a):not(.semaforo-r) { background-color: #ffffff !important; color: #495057 !important; border: 1px solid #ced4da !important; }
    .semaforo-v { background-color: #28a745 !important; color: #ffffff !important; border: 2px solid #1e7e34 !important; }
    .semaforo-a { background-color: #e69623 !important; color: #ffffff !important; border: 2px solid #c27d1a !important; }
    .semaforo-r { background-color: #cb444a !important; color: #ffffff !important; border: 2px solid #a8363b !important; }
    .input-semaforo::placeholder { color: #adb5bd !important; }
    .semaforo-v::placeholder, .semaforo-a::placeholder, .semaforo-r::placeholder { color: rgba(255,255,255,0.7) !important; }
    .input-delta-auto { background-color: #f8f9fa !important; color: #495057 !important; cursor: not-allowed; font-weight: bold; border: 1px dashed #adb5bd !important; }
    .badge-guardado { padding: 12px; border-radius: 8px; font-size: 1.2rem; }
</style>

<div class="container-fluid px-0">
    <div class="mb-4 text-end d-flex justify-content-between align-items-center bg-white p-3 rounded-3 shadow-sm border-start border-4 border-dark">
        <div>
            <h5 class="m-0 fw-bold text-dark"><i class="bi bi-cpu text-secondary me-2"></i>Módulo Votator</h5>
            <small class="text-muted">Captura Unificada (Cada 2 Horas)</small>
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
                <i class="bi bi-person-badge"></i> Nómina: <span class="text-dark"><?php echo htmlspecialchars($_SESSION['nomina']); ?></span>
            </button>
            <a href="historial.php?id=<?php echo $equipo_id; ?>" class="btn btn-secondary btn-sm fw-bold shadow-sm">
                <i class="bi bi-clock-history"></i> Historial
            </a>
        </div>
    </div>

    <form action="guardar.php" method="POST">
        <input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">
        <input type="hidden" name="frecuencia_tab" value="CADA 2 HORAS">
        <input type="hidden" name="numero_nomina" value="<?php echo htmlspecialchars($_SESSION['nomina']); ?>">

        <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
            <div class="card-header <?php echo $color_header; ?> text-white py-3 px-4 fw-bold d-flex justify-content-between align-items-center">
                <span class="fs-5"><i class="bi bi-table me-2 text-light"></i> Módulo: <?php echo htmlspecialchars($nombre_equipo); ?></span>
                <span class="badge bg-light text-dark shadow-sm">RUTINA: 2 HRS</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle m-0 bg-white">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width: 28%;">Parámetro / Variable</th>
                            <th style="width: 32%;">Límites de Referencia</th>
                            <th class="text-center" style="width: 20%;">Lectura Real</th>
                            <th class="pe-4">Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt_p = mysqli_prepare($conn, "SELECT * FROM parametros WHERE equipo_id = ? ORDER BY id ASC");
                        mysqli_stmt_bind_param($stmt_p, "i", $equipo_id);
                        mysqli_stmt_execute($stmt_p);
                        $params = mysqli_stmt_get_result($stmt_p);

                        while ($p = mysqli_fetch_assoc($params)) {
                            $reg = obtenerRegistroExistenteVotator($conn, $p['id']);
                            $rb = $p['rojo_bajo']; $ab = $p['amarillo_bajo'];
                            $aa = $p['amarillo_alto']; $ra = $p['rojo_alto'];
                            $n = $p['nombre_parametro'];
                            $n_norm = normalizarTextoVotator($n);

                            // Lógica de detección ultra inteligente con texto normalizado
                            $clase_extra = "input-semaforo";
                            $es_delta = false;
                            $grupo_id = "";

                            if (strpos($n_norm, 'maestro') !== false) {
                                $grupo_id = "maestro";
                            } elseif (strpos($n_norm, 'esclavo') !== false) {
                                $grupo_id = "esclavo";
                            } elseif (strpos($n_norm, 'producto') !== false) {
                                $grupo_id = "producto";
                            }

                            if ($grupo_id !== "") {
                                if (strpos($n_norm, 'inyeccion') !== false || strpos($n_norm, 'entrada') !== false || strpos($n_norm, 'in') !== false) {
                                    $clase_extra .= " temp-in-" . $grupo_id;
                                } elseif (strpos($n_norm, 'retorno') !== false || strpos($n_norm, 'salida') !== false || strpos($n_norm, 'out') !== false) {
                                    $clase_extra .= " temp-out-" . $grupo_id;
                                } elseif (strpos($n_norm, 'delta') !== false) {
                                    $clase_extra .= " temp-delta-" . $grupo_id . " input-delta-auto";
                                    $es_delta = true;
                                }
                            }
                        ?>
                        <tr>
                            <td class="fw-bold ps-4 text-dark fs-6"><?php echo htmlspecialchars($n); ?></td>
                            
                            <td class="text-secondary align-middle">
                                <?php if ($p['tipo_dato'] == 'NUMERICO' && ($rb !== null || $ra !== null)) { ?>
                                    <div style="font-size: 0.82rem; line-height: 1.4;">
                                        <?php if ($rb !== null) { ?>
                                            <div>🔴 <strong class="text-danger">Crítico Bajo:</strong> &le; <?php echo fmt($rb); ?></div>
                                        <?php } ?>
                                        <?php if ($ab !== null && $ab > $rb) { ?>
                                            <div>🟡 <strong style="color: #d97706;">Alerta Baja:</strong> <?php echo fmt($rb + 0.01); ?> a <?php echo fmt($ab); ?></div>
                                        <?php } ?>
                                        <div>🟢 <strong class="text-success">Óptimo:</strong> 
                                            <?php echo ($ab !== null ? fmt($ab + 0.01) : ($rb !== null ? fmt($rb + 0.01) : 'Min')); ?> a <?php echo ($aa !== null ? fmt($aa - 0.01) : ($ra !== null ? fmt($ra - 0.01) : 'Max')); ?>
                                        </div>
                                        <?php if ($aa !== null && $ra !== null && $aa < $ra) { ?>
                                            <div>🟡 <strong style="color: #d97706;">Alerta Alta:</strong> <?php echo fmt($aa); ?> a <?php echo fmt($ra - 0.01); ?></div>
                                        <?php } ?>
                                        <?php if ($ra !== null) { ?>
                                            <div>🔴 <strong class="text-danger">Crítico Alto:</strong> &ge; <?php echo fmt($ra); ?></div>
                                        <?php } ?>
                                    </div>
                                <?php } else { ?>
                                    <span class="badge bg-light text-dark border">Verificación / Rutina Cualitativa</span>
                                <?php } ?>
                            </td>

                            <td class="text-center px-3">
                                <?php if ($reg) { 
                                    $badge_color = ($p['tipo_dato'] == 'NUMERICO') ? getSemaforoColorVotator($reg['valor_capturado'], $rb, $ab, $aa, $ra) : 'bg-dark';
                                ?>
                                    <div class="badge-guardado fw-bold text-center <?php echo $badge_color; ?> text-white border-0 shadow-sm">
                                        <i class="bi bi-lock-fill me-1"></i><?php echo htmlspecialchars($reg['valor_capturado']); ?>
                                    </div>
                                <?php } else { ?>
                                    <?php if ($p['tipo_dato'] == 'NUMERICO') { ?>
                                        <input type="number" step="0.01" name="param_<?php echo $p['id']; ?>" 
                                            class="form-control text-center fw-bold fs-4 <?php echo $clase_extra; ?> input-equipo-<?php echo $equipo_id; ?>" id="param_<?php echo $p['id']; ?>"
                                            data-rb="<?php echo $rb; ?>" data-ab="<?php echo $ab; ?>" 
                                            data-aa="<?php echo $aa; ?>" data-ra="<?php echo $ra; ?>"
                                            oninput="evaluarSemaforoVotator(this); calcularDeltasVotator();"
                                            onchange="forzarNegativoVotator(this); evaluarSemaforoVotator(this); calcularDeltasVotator();"
                                            <?php echo $es_delta ? 'readonly placeholder="Auto..."' : 'placeholder="0.00"'; ?>>
                                    <?php } else { ?>
                                        <select name="param_<?php echo $p['id']; ?>" class="form-select text-center fw-bold bg-light input-equipo-<?php echo $equipo_id; ?>" id="param_<?php echo $p['id']; ?>" style="height: 55px;">
                                            <option value="">-- Seleccionar --</option>
                                            <option value="Realizada">Realizada</option>
                                            <option value="No Aplica">No Aplica</option>
                                        </select>
                                    <?php } ?>
                                <?php } ?>
                            </td>
                            <td class="pe-4">
                                <?php if ($reg) { ?>
                                    <div class="text-muted small fst-italic"><?php echo htmlspecialchars($reg['observaciones']) ?: 'Sin observaciones'; ?></div>
                                <?php } else { ?>
                                    <textarea name="obs_<?php echo $p['id']; ?>" class="form-control form-control-sm input-equipo-<?php echo $equipo_id; ?>" id="obs_<?php echo $p['id']; ?>" rows="2" placeholder="Notas..."></textarea>
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
                <i class="bi bi-save-fill me-2"></i> Guardar Registros - <?php echo htmlspecialchars($nombre_equipo); ?>
            </button>
        </div>
    </form>
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

function forzarNegativoVotator(input) {
    if (!input || input.value === '') return;
    let rb = parseFloat(input.getAttribute('data-rb'));
    let aa = parseFloat(input.getAttribute('data-aa'));
    if (rb < 0 || aa < 0) {
        let val = parseFloat(input.value);
        if (!isNaN(val) && val > 0) {
            input.value = (val * -1).toFixed(2);
        }
    }
    calcularDeltasVotator();
}

function calcularDeltasVotator() {
    const grupos = ['maestro', 'esclavo', 'producto'];
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
                evaluarSemaforoVotator(deltaInput);
            } else {
                deltaInput.value = '';
                deltaInput.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
            }
        }
    });
}

function evaluarSemaforoVotator(input) {
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

document.addEventListener('DOMContentLoaded', calcularDeltasVotator);
</script>