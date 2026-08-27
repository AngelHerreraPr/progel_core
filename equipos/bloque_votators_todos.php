<?php
// Progel_coree/equipos/bloque_votators_todos.php
// MÓDULO EXCLUSIVO PARA VOTATORS (IDs 84 al 89) CON DELTA A PRUEBA DE BALAS

if (!function_exists('obtenerRegistroExistenteVotatorTodos')) {
    function obtenerRegistroExistenteVotatorTodos($conn, $param_id) {
        $q = "SELECT valor_capturado, observaciones FROM bitacora_lecturas 
              WHERE parametro_id = $param_id 
              AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 90 MINUTE) 
              ORDER BY id DESC LIMIT 1";
        $res = mysqli_query($conn, $q);
        return mysqli_fetch_assoc($res);
    }
}

if (!function_exists('getSemaforoColorVotatorTodos')) {
    function getSemaforoColorVotatorTodos($val, $rb, $ab, $aa, $ra) {
        if (!is_numeric($val)) return 'bg-secondary text-white';
        $v = (float)$val;
        $rb = ($rb !== null) ? (float)$rb : -999999;
        $ab = ($ab !== null) ? (float)$ab : -999999;
        $aa = ($aa !== null) ? (float)$aa : 999999;
        $ra = ($ra !== null) ? (float)$ra : 999999;

        if ($v <= $rb || $v >= $ra) return 'semaforo-rojo';
        if (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) return 'semaforo-amarillo';
        return 'semaforo-verde';
    }
}

if (!function_exists('fmtVT')) {
    function fmtVT($val) { 
        return ($val !== null && is_numeric($val)) ? number_format((float)$val, 2, '.', '') : '-'; 
    }
}

// 🎯 CONSULTA DIRECTA POR LOS IDs DE TUS VOTATORS (84 AL 89)
$ids_votators = [84, 85, 86, 87, 88, 89];
$q_votators = mysqli_query($conn, "SELECT id, nombre FROM equipos WHERE id IN (" . implode(',', $ids_votators) . ") ORDER BY id ASC");

$pestañas_votator = [];
if($q_votators){
    while($v = mysqli_fetch_assoc($q_votators)){
        $pestañas_votator[] = [
            'id' => $v['id'],
            'nombre' => $v['nombre']
        ];
    }
}

$equipo_id_solicitado = intval($_GET['id'] ?? 0);
?>

<style>
    .semaforo-verde { background-color: #198754 !important; color: #ffffff !important; }
    .semaforo-amarillo { background-color: #ffc107 !important; color: #000000 !important; }
    .semaforo-rojo { background-color: #dc3545 !important; color: #ffffff !important; }
    
    .badge-guardado { padding: 12px; border-radius: 8px; font-size: 1.1rem; }
    
    .caja-limites {
        font-size: 0.80rem; line-height: 1.4; background: #f8f9fa; 
        padding: 8px 12px; border-radius: 6px; border: 1px solid #dee2e6;
        text-align: left; display: inline-block; width: 100%;
    }
    .color-alerta-texto { color: #d39e00 !important; }
    .input-delta-auto { background-color: #e9ecef !important; cursor: not-allowed; }
</style>

<div class="container-fluid px-0">
    <ul class="nav nav-pills mb-4 bg-dark p-2 rounded shadow-sm flex-nowrap overflow-auto" id="pills-tab-votators" role="tablist">
        <?php foreach($pestañas_votator as $index => $tab): 
            $es_active = ($equipo_id_solicitado == $tab['id']) || ($equipo_id_solicitado == 0 && $index == 0);
        ?>
            <li class="nav-item me-2" role="presentation">
                <button class="nav-link text-white fw-bold <?= $es_active ? 'active' : '' ?>" 
                        id="tab-btn-<?= $tab['id'] ?>" 
                        data-bs-toggle="pill" 
                        data-bs-target="#tab-content-votator-<?= $tab['id'] ?>" 
                        type="button" role="tab">
                    <i class="bi bi-arrow-repeat me-1"></i><?= htmlspecialchars($tab['nombre']) ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content" id="pills-tabContent-votators">
        <?php foreach($pestañas_votator as $index => $tab): 
            $eq_id = $tab['id'];
            $eq_nombre = $tab['nombre'];
            $es_active = ($equipo_id_solicitado == $eq_id) || ($equipo_id_solicitado == 0 && $index == 0);
        ?>
            <div class="tab-pane fade <?= $es_active ? 'show active' : '' ?>" id="tab-content-votator-<?= $eq_id ?>" role="tabpanel">
                <div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden">
                    
                    <div class="card-header bg-danger bg-gradient text-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span class="fs-5 fw-bold"><i class="bi bi-arrow-repeat text-warning me-2"></i><?= htmlspecialchars($eq_nombre) ?></span>
                        
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="btn-group shadow-sm">
                                <button type="button" class="btn btn-warning btn-sm text-dark fw-bold px-3" onclick="marcarEquipoVotator('LAVADO', <?= $eq_id ?>)">LAVADO</button>
                                <button type="button" class="btn btn-info text-dark btn-sm fw-bold px-3" onclick="marcarEquipoVotator('F/O', <?= $eq_id ?>)">F/O</button>
                                <button type="button" class="btn btn-outline-light btn-sm fw-bold px-3" onclick="marcarEquipoVotator('', <?= $eq_id ?>)">Limpiar</button>
                            </div>
                            <a href="historial.php?id=<?= $eq_id ?>" class="btn btn-light btn-sm text-dark fw-bold px-3 py-1 shadow-sm" style="font-size: 0.85rem;">
                                <i class="bi bi-clock-history me-1"></i> Historial
                            </a>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <form action="guardar.php" method="POST">
                            <input type="hidden" name="equipo_id" value="<?= $eq_id ?>">
                            <input type="hidden" name="frecuencia_tab" value="CADA 2 HORAS">
                            <input type="hidden" name="numero_nomina" value="<?= htmlspecialchars($_SESSION['nomina'] ?? '') ?>">

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle m-0 bg-white style-sm text-center">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 25%; text-align: left;" class="ps-3">Parámetro / Variable</th>
                                            <th style="width: 33%;">Límites de Referencia</th>
                                            <th style="width: 22%;">Lectura Real</th>
                                            <th style="width: 20%;">Observaciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $stmt_p = mysqli_prepare($conn, "SELECT * FROM parametros WHERE equipo_id = ? ORDER BY id ASC");
                                        mysqli_stmt_bind_param($stmt_p, "i", $eq_id);
                                        mysqli_stmt_execute($stmt_p);
                                        $params = mysqli_stmt_get_result($stmt_p);

                                        while ($p = mysqli_fetch_assoc($params)):
                                            $reg = obtenerRegistroExistenteVotatorTodos($conn, $p['id']);
                                            $rb = $p['rojo_bajo']; $ab = $p['amarillo_bajo'];
                                            $aa = $p['amarillo_alto']; $ra = $p['rojo_alto'];
                                            $n = $p['nombre_parametro'];
                                            
                                            // Normalización para identificar el Delta en PHP
                                            $n_low = mb_strtolower($n, 'UTF-8');
                                            $n_low = str_replace(['á','é','í','ó','ú'], ['a','e','i','o','u'], $n_low);

                                            $clase_extra = "input-semaforo";
                                            $es_delta = (strpos($n_low, 'delta') !== false);

                                            // Si es cualitativo y tiene opciones específicas (Textura/Lubricación)
                                            $es_cualitativo_especial = false;
                                            $opciones_html = "";
                                            if ($p['tipo_dato'] == 'CUALITATIVO') {
                                                if (strpos($n_low, 'textura') !== false) {
                                                    $es_cualitativo_especial = true;
                                                    $opciones_html = '<option value="Fideo continuo">Fideo continuo</option><option value="Deformación en el fideo">Deformación en el fideo</option><option value="Fideo plastoso">Fideo plastoso</option>';
                                                } elseif (strpos($n_low, 'lubricac') !== false) {
                                                    $es_cualitativo_especial = true;
                                                    $opciones_html = '<option value="Adecuada">Adecuada</option><option value="Insuficiente">Insuficiente</option><option value="Sin lubricación">Sin lubricación</option>';
                                                }
                                            }

                                            // GENERACIÓN DE ENTRADAS
                                            if ($p['tipo_dato'] == 'NUMERICO') {
                                                $propiedades_extra = $es_delta ? 'readonly placeholder="Auto..." tabindex="-1" style="background-color: #e9ecef !important; cursor: not-allowed; height: 55px; max-width: 100%; margin: 0 auto;"' : 'placeholder="0.00" style="height: 55px; max-width: 100%; margin: 0 auto;"';
                                                
                                                // La magia: le inyectamos el 'data-nombre' al HTML
                                                $campo_input = '<input type="number" step="0.01" name="param_' . $p['id'] . '" ' .
                                                    'class="form-control text-center fw-bold fs-4 bg-light ' . $clase_extra . ' input-equipo-' . $eq_id . '" ' .
                                                    'id="param_' . $p['id'] . '" ' .
                                                    'data-nombre="' . htmlspecialchars($n) . '" ' .
                                                    'data-rb="' . ($rb ?? '') . '" data-ab="' . ($ab ?? '') . '" ' .
                                                    'data-aa="' . ($aa ?? '') . '" data-ra="' . ($ra ?? '') . '" ' .
                                                    'oninput="evaluarSemaforo(this); calcularAutoDeltasVotator(' . $eq_id . ');" ' .
                                                    'onchange="evaluarSemaforo(this); calcularAutoDeltasVotator(' . $eq_id . ');" ' .
                                                    $propiedades_extra . '>';
                                            } else {
                                                if ($es_cualitativo_especial) {
                                                    $campo_input = '<select name="param_' . $p['id'] . '" class="form-select text-center fw-bold bg-light input-equipo-' . $eq_id . '" style="height: 55px; max-width: 100%; margin: 0 auto;">' .
                                                        '<option value="">-- Seleccionar --</option>' . $opciones_html . '<option value="No aplica">No aplica</option></select>';
                                                } else {
                                                    $campo_input = '<select name="param_' . $p['id'] . '" class="form-select text-center fw-bold bg-light input-equipo-' . $eq_id . '" style="height: 55px; max-width: 100%; margin: 0 auto;">' .
                                                        '<option value="">-- Seleccionar --</option><option value="Realizada">Realizada</option><option value="No Aplica">No Aplica</option></select>';
                                                }
                                            }
                                        ?>
                                        <tr>
                                            <td class="fw-bold text-dark text-start ps-3"><?= htmlspecialchars($n) ?></td> 
                                            
                                            <td class="py-2 px-3">
                                                <?php if ($p['tipo_dato'] == 'NUMERICO'): ?>
                                                    <div class="caja-limites shadow-sm">
                                                        <?php if ($rb !== null): ?> <div class="text-danger fw-bold">🔴 Crítico Bajo: &le; <?= fmtVT($rb) ?></div> <?php endif; ?>
                                                        <?php if ($rb !== null && $ab !== null): ?> <div class="fw-bold color-alerta-texto">🟡 Alerta Baja: &gt; <?= fmtVT($rb) ?> a <?= fmtVT($ab) ?></div> <?php endif; ?>
                                                        <div class="text-success fw-bold">🟢 Óptimo: &gt; <?= ($ab !== null) ? fmtVT($ab) : 'Min' ?> a &lt; <?= ($aa !== null) ? fmtVT($aa) : 'Max' ?></div>
                                                        <?php if ($aa !== null && $ra !== null): ?> <div class="fw-bold color-alerta-texto">🟡 Alerta Alta: &ge; <?= fmtVT($aa) ?> a &lt; <?= fmtVT($ra) ?></div> <?php endif; ?>
                                                        <?php if ($ra !== null): ?> <div class="text-danger fw-bold">🔴 Crítico Alto: &ge; <?= fmtVT($ra) ?></div> <?php endif; ?>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary px-3 py-2">Cualitativo / Control</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="px-2">
                                                <?php if ($reg): ?>
                                                    <?php $badge_color = ($p['tipo_dato'] == 'NUMERICO') ? getSemaforoColorVotatorTodos($reg['valor_capturado'], $rb, $ab, $aa, $ra) : 'bg-dark text-white'; ?>
                                                    <div class="badge-guardado fw-bold text-center <?= $badge_color ?> border-0 shadow-sm mx-auto" style="max-width: 100%;">
                                                        <i class="bi bi-lock-fill me-1"></i><?= htmlspecialchars($reg['valor_capturado']) ?>
                                                    </div>
                                                <?php else: ?>
                                                    <?= $campo_input ?>
                                                <?php endif; ?>
                                            </td>

                                            <td class="pe-3">
                                                <?php if ($reg): ?>
                                                    <small class="text-muted fst-italic"><?= htmlspecialchars($reg['observaciones']) ?: 'Sin obs.' ?></small>
                                                <?php else: ?>
                                                    <textarea name="obs_<?= $p['id'] ?>" class="form-control form-control-sm input-equipo-<?= $eq_id ?>" rows="2" placeholder="Notas..."></textarea>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="p-3 bg-light border-top text-end">
                                <button type="submit" class="btn btn-dark w-100 fw-bold py-3 shadow-sm fs-5">
                                    <i class="bi bi-save-fill me-2 text-warning"></i> Guardar <?= htmlspecialchars($eq_nombre) ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function evaluarSemaforo(input) { 
    if (!input || input.value === '') {
        if (input) input.classList.remove('semaforo-verde', 'semaforo-amarillo', 'semaforo-rojo'); 
        return;
    }
    const val = parseFloat(input.value);
    let rb = parseFloat(input.getAttribute('data-rb')); if(isNaN(rb)) rb = -999999;
    let ab = parseFloat(input.getAttribute('data-ab')); if(isNaN(ab)) ab = -999999;
    let aa = parseFloat(input.getAttribute('data-aa')); if(isNaN(aa)) aa = 999999;
    let ra = parseFloat(input.getAttribute('data-ra')); if(isNaN(ra)) ra = 999999;

    input.classList.remove('semaforo-verde', 'semaforo-amarillo', 'semaforo-rojo'); 
    
    if (val <= rb || val >= ra) { 
        input.classList.add('semaforo-rojo'); 
    } else if ((val > rb && val <= ab) || (val >= aa && val < ra)) { 
        input.classList.add('semaforo-amarillo'); 
    } else { 
        input.classList.add('semaforo-verde'); 
    }
}

// 🧮 NUEVA INTELIGENCIA JAVASCRIPT A PRUEBA DE BALAS PARA LOS VOTATORS (IDs 84-89)
function calcularAutoDeltasVotator(equipoId) {
    const inputs = document.querySelectorAll('.input-equipo-' + equipoId);
    let inV = null, outV = null, deltaV = null;

    // Escaneamos todas las cajas
    inputs.forEach(input => {
        let nombre = input.getAttribute('data-nombre');
        if(!nombre) return;
        
        // Convertimos a minúsculas y le borramos todos los acentos a la mala
        nombre = nombre.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");

        // Detectamos quién es quién
        if (nombre.includes('entrada') || nombre.includes('inyeccion')) inV = input;
        if (nombre.includes('salida') || nombre.includes('retorno')) outV = input;
        if (nombre.includes('delta')) deltaV = input;
    });

    // Hacemos la resta
    if (inV && outV && deltaV) {
        let v1 = parseFloat(inV.value);
        let v2 = parseFloat(outV.value);
        if (!isNaN(v1) && !isNaN(v2)) {
            deltaV.value = Math.abs(v1 - v2).toFixed(2);
            evaluarSemaforo(deltaV);
        } else {
            deltaV.value = '';
            deltaV.classList.remove('semaforo-verde', 'semaforo-amarillo', 'semaforo-rojo');
        }
    }
}

function marcarEquipoVotator(valor, equipoId) { 
    if (!equipoId) return;
    const campos = document.querySelectorAll('.input-equipo-' + equipoId);

    campos.forEach(function(el) {
        if (!el) return;
        if (el.tagName === 'SELECT') {
            let encontrada = false;
            for (let i = 0; i < el.options.length; i++) {
                if (el.options[i].value === valor) { encontrada = true; break; }
            }
            if (!encontrada && valor !== '') {
                const opt = document.createElement('option');
                opt.value = valor; opt.text = valor;
                el.add(opt);
            }
            el.value = valor;
        } else if (el.tagName === 'INPUT') {
            if (el.type === 'number' && valor !== '') { el.type = 'text'; }
            else if (valor === '' && el.type === 'text') { el.type = 'number'; }
            el.value = valor;
            el.classList.remove('semaforo-verde', 'semaforo-amarillo', 'semaforo-rojo');
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[type="number"].input-semaforo:not([readonly])').forEach(function(input) {
        if (input.value !== '') { 
            evaluarSemaforo(input);
        }
    });

    const equipoIdSolicitado = <?= $equipo_id_solicitado ?>;
    if (equipoIdSolicitado > 0) {
        const targetTabBtn = document.getElementById('tab-btn-' + equipoIdSolicitado);
        if (targetTabBtn) { new bootstrap.Tab(targetTabBtn).show(); }
    }
});
</script>