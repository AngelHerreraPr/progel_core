<?php
// Progel_cores/equipos/bloque_compresores.php

function obtenerRegistroCompresorInteligente($conn, $param_id, $frecuencia) {
    include_once __DIR__ . '/../config/db_v2.php';
    include_once __DIR__ . '/../includes/mapeo_v2.php';
    global $conn_v2;
    if (isset($conn_v2) && $conn_v2) {
        $v2 = obtenerLecturaRecienteV2($conn_v2, $param_id);
        if ($v2) return $v2;
    }

    $intervalo = ($frecuencia == 'SEMANAL') ? '7 DAY' : '12 HOUR';
    $q = "SELECT valor_capturado, observaciones FROM bitacora_lecturas 
          WHERE parametro_id = $param_id 
          AND fecha_registro >= DATE_SUB(NOW(), INTERVAL $intervalo) 
          ORDER BY id DESC LIMIT 1";
    $res = mysqli_query($conn, $q);
    return mysqli_fetch_assoc($res);
}

function getSemaforoColorCompresor($val, $rb, $ab, $aa, $ra) {
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
while($f = mysqli_fetch_assoc($q_frec)){ 
    $frecuencias[] = $f['frecuencia']; 
}
if(empty($frecuencias)) {
    $frecuencias[] = 'DIARIO';
}
?>

<style>
    .input-semaforo { transition: background-color 0.3s ease, color 0.3s ease; border-radius: 8px !important; height: 55px; }
    .input-semaforo:not(.semaforo-v):not(.semaforo-a):not(.semaforo-r) { background-color: #ffffff !important; color: #495057 !important; border: 1px solid #ced4da !important; }
    .semaforo-v { background-color: #2e8b57 !important; color: #ffffff !important; border: 2px solid #257447 !important; }
    .semaforo-a { background-color: #e49a32 !important; color: #ffffff !important; border: 2px solid #c47b1c !important; }
    .semaforo-r { background-color: #c94436 !important; color: #ffffff !important; border: 2px solid #a9362c !important; }
    .input-semaforo::placeholder { color: #adb5bd !important; }
    .semaforo-v::placeholder, .semaforo-a::placeholder, .semaforo-r::placeholder { color: rgba(255,255,255,0.7) !important; }
    .badge-guardado { padding: 12px; border-radius: 8px; font-size: 1.2rem; }
    .nav-pills .nav-link { color: #495057; border: 1px solid #dee2e6; margin-bottom: 5px; background-color: #f8f9fa; }
    .nav-pills .nav-link.active { background-color: #343a40; color: white; border-color: #343a40; }
</style>

<div class="container-fluid px-0">
    <div class="mb-4 text-end d-flex justify-content-between align-items-center bg-white p-3 rounded-3 shadow-sm border-start border-4 border-warning">
        <div>
            <h5 class="m-0 fw-bold text-dark">Control de Compresores</h5>
            <small class="text-muted">Zona Negra - Variables Ambientales y MTTO</small>
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
                Nómina: <span class="text-dark"><?php echo htmlspecialchars($_SESSION['nomina']); ?></span>
            </button>
            <a href="historial.php?id=<?php echo $equipo_id; ?>" class="btn btn-secondary btn-sm fw-bold shadow-sm">
                Historial
            </a>
        </div>
    </div>

    <ul class="nav nav-pills flex-wrap mb-4 gap-2 justify-content-center" role="tablist">
        <?php foreach($frecuencias as $index => $frec) { 
            $clase_activa = ($index == 0) ? 'active' : '';
        ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $clase_activa; ?> fw-bold fs-6 shadow-sm px-4 py-2" data-bs-toggle="pill" data-bs-target="#tab_<?php echo md5($frec); ?>" type="button" role="tab">
                    Rutina <?php echo htmlspecialchars($frec); ?>
                </button>
            </li>
        <?php } ?>
    </ul>

    <div class="tab-content">
        <?php foreach($frecuencias as $index => $frec) { 
            $clase_pane = ($index == 0) ? 'show active' : '';
        ?>
        <div class="tab-pane fade <?php echo $clase_pane; ?>" id="tab_<?php echo md5($frec); ?>" role="tabpanel">
            <form action="guardar.php" method="POST">
                <input type="hidden" name="equipo_id" value="<?php echo $equipo_id; ?>">
                <input type="hidden" name="frecuencia_tab" value="<?php echo htmlspecialchars($frec); ?>">
                <input type="hidden" name="numero_nomina" value="<?php echo htmlspecialchars($_SESSION['nomina']); ?>">

                <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-white border-bottom py-3 px-4 fw-bold d-flex justify-content-between align-items-center">
                        <span class="text-warning fs-5">Módulo: <?php echo htmlspecialchars($frec); ?></span>
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
                                $stmt_p = mysqli_prepare($conn, "SELECT * FROM parametros WHERE equipo_id = ? AND frecuencia = ? ORDER BY id ASC");
                                mysqli_stmt_bind_param($stmt_p, "is", $equipo_id, $frec);
                                mysqli_stmt_execute($stmt_p);
                                $params = mysqli_stmt_get_result($stmt_p);

                                while ($p = mysqli_fetch_assoc($params)) {
                                    $reg = obtenerRegistroCompresorInteligente($conn, $p['id'], $frec);
                                    
                                    $rb = $p['rojo_bajo']; $ab = $p['amarillo_bajo'];
                                    $aa = $p['amarillo_alto']; $ra = $p['rojo_alto'];
                                    $n = $p['nombre_parametro'];
                                ?>
                                <tr>
                                    <td class="fw-bold ps-4 text-dark fs-6"><?php echo htmlspecialchars($n); ?></td>
                                    
                                    <td class="text-secondary">
                                        <?php if ($p['tipo_dato'] == 'NUMERICO' && ($rb !== null || $ra !== null || $aa !== null)) { ?>
                                            <ul class="list-unstyled mb-0" style="font-size: 0.85rem; line-height: 1.6;">
                                                <?php if($rb !== null) { ?> <li>🔴 <strong style="color: #dc3545;">Rojo Bajo:</strong> &lt;= <?php echo $rb; ?></li> <?php } ?>
                                                <?php if($ab !== null) { ?> <li>🟡 <strong style="color: #e69623;">Amarillo Bajo:</strong> <?php echo fmt($rb + 0.01); ?> a <?php echo $ab; ?></li> <?php } ?>
                                                <li>🟢 <strong style="color: #28a745;">Verde (Óptimo):</strong> <?php echo $ab !== null ? fmt($ab + 0.01) : 'Mín'; ?> a <?php echo $aa !== null ? fmt($aa - 0.01) : 'Máx'; ?></li>
                                                <?php if($aa !== null) { ?> <li>🟡 <strong style="color: #e69623;">Amarillo Alto:</strong> <?php echo $aa; ?> a <?php echo fmt($ra - 0.01); ?></li> <?php } ?>
                                                <?php if($ra !== null) { ?> <li>🔴 <strong style="color: #dc3545;">Rojo Alto:</strong> &gt;= <?php echo $ra; ?></li> <?php } ?>
                                            </ul>
                                        <?php } else { ?>
                                            <span class="text-muted">Sin semáforo / Captura Directa</span>
                                        <?php } ?>
                                    </td>

                                    <td class="text-center px-3">
                                        <?php if ($reg) { 
                                            $badge_color = ($p['tipo_dato'] == 'NUMERICO') ? getSemaforoColorCompresor($reg['valor_capturado'], $rb, $ab, $aa, $ra) : 'bg-dark';
                                        ?>
                                            <div class="badge-guardado fw-bold text-center <?php echo $badge_color; ?> text-white border-0 shadow-sm">
                                                <?php echo htmlspecialchars($reg['valor_capturado']); ?>
                                            </div>
                                        <?php } else { ?>
                                            <?php if ($p['tipo_dato'] == 'NUMERICO') { ?>
                                                <input type="number" step="0.01" name="param_<?php echo $p['id']; ?>" 
                                                    class="form-control text-center fw-bold fs-4 input-semaforo input-equipo-<?php echo $equipo_id; ?>" id="param_<?php echo $p['id']; ?>"
                                                    data-rb="<?php echo $rb; ?>" data-ab="<?php echo $ab; ?>" data-aa="<?php echo $aa; ?>" data-ra="<?php echo $ra; ?>"
                                                    placeholder="0.00" oninput="evaluarSemaforoCompresor(this);">
                                            <?php } elseif ($p['tipo_dato'] == 'NUMERICO_LIBRE') { ?>
                                                <input type="number" step="0.01" name="param_<?php echo $p['id']; ?>" 
                                                    class="form-control text-center fw-bold fs-4 input-equipo-<?php echo $equipo_id; ?>" id="param_<?php echo $p['id']; ?>" placeholder="Horas..." required>
                                            <?php } elseif ($p['tipo_dato'] == 'CUALITATIVO_SN') { ?>
                                                <select name="param_<?php echo $p['id']; ?>" class="form-select text-center fw-bold bg-light input-equipo-<?php echo $equipo_id; ?>" id="param_<?php echo $p['id']; ?>" style="height: 55px;" required>
                                                    <option value="">-- Seleccionar --</option>
                                                    <option value="Sí">Sí</option>
                                                    <option value="No">No</option>
                                                </select>
                                            <?php } elseif ($p['tipo_dato'] == 'CUALITATIVO_ALARMAS') { ?>
                                                <select name="param_<?php echo $p['id']; ?>" class="form-select text-center fw-bold bg-light input-equipo-<?php echo $equipo_id; ?>" id="param_<?php echo $p['id']; ?>" style="height: 55px;" required>
                                                    <option value="">-- Seleccionar --</option>
                                                    <option value="Sí presenta">Sí presenta</option>
                                                    <option value="No presenta">No presenta</option>
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
                        Guardar Avances <?php echo htmlspecialchars($frec); ?>
                    </button>
                </div>
            </form>
        </div>
        <?php } ?>
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

function evaluarSemaforoCompresor(input) {
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
</script>