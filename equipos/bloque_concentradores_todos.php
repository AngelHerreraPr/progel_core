<?php
// Progel_core/equipos/bloque_concentradores_todos.php

function obtenerRegistroExistente2Horas($conn, $param_id) {
    $q = "SELECT valor_capturado, observaciones FROM bitacora_lecturas 
          WHERE parametro_id = $param_id 
          AND fecha_registro >= DATE_SUB(NOW(), INTERVAL 90 MINUTE) 
          ORDER BY id DESC LIMIT 1";
    $res = mysqli_query($conn, $q);
    return mysqli_fetch_assoc($res);
}

$ids_concentradores = "14, 15, 16, 17, 18";
$q_grupos = mysqli_query($conn, "SELECT DISTINCT equipo_id, grupo FROM parametros WHERE equipo_id IN ($ids_concentradores) ORDER BY equipo_id ASC");
$pestañas = [];
while($g = mysqli_fetch_assoc($q_grupos)){
    $pestañas[] = [
        'id' => $g['equipo_id'],
        'nombre' => $g['grupo']
    ];
}
?>

<style>
    .semaforo-v { background-color: #2e8b57 !important; color: #ffffff !important; font-weight: bold; border-color: #257447 !important; }
    .semaforo-a { background-color: #e49a32 !important; color: #ffffff !important; font-weight: bold; border-color: #c47b1c !important; }
    .semaforo-r { background-color: #c94436 !important; color: #ffffff !important; font-weight: bold; border-color: #a9362c !important; }
    .badge-guardado { padding: 10px; background-color: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; border-radius: 6px; display: block; width: 100%; }

    /* ESTILO PARA CAMPOS BLOQUEADOS EN GRIS (DESACTIVADO) */
    .input-bloqueado-gris {
        background-color: #e9ecef !important;
        cursor: not-allowed !important;
        color: #6c757d !important;
        font-weight: bold;
        border-style: dashed !important;
    }

    /* CORRECCIÓN DE ESPACIOS EN LAS TABLAS */
    .table th, .table td {
        vertical-align: middle !important;
        padding: 12px 15px !important;
    }
    
    .table td:nth-child(2) {
        min-width: 240px; 
        font-size: 0.82rem;
        white-space: nowrap;
    }
</style>

<div class="container-fluid px-0">

    <div class="mb-4 text-end d-flex justify-content-between align-items-center bg-white p-3 rounded-3 shadow-sm border-start border-4 border-primary">
        <div>
            <h5 class="m-0 fw-bold text-dark">Panel Maestro de Concentradores</h5>
            <small class="text-muted">Zona Gris - Control Individual por Concentrador (Cada 2 Horas)</small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" class="btn btn-outline-dark btn-sm fw-bold" disabled>
                Nómina: <span class="text-dark"><?= htmlspecialchars($_SESSION['nomina']) ?></span>
            </button>
        </div>
    </div>

    <!-- Pestañas de Navegación -->
    <ul class="nav nav-pills mb-3 gap-2" id="pills-tab" role="tablist">
        <?php foreach($pestañas as $index => $tab): 
            $tab_id = "tab_" . $tab['id'];
            $es_invertido = (strpos(strtolower($tab['nombre']), 'invertido') !== false);
            $clase_activa = ($index == 0) ? 'active' : '';
        ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $clase_activa ?> fw-bold fs-6 shadow-sm px-4 py-2" data-bs-toggle="pill" data-bs-target="#<?= $tab_id ?>" type="button" role="tab" onclick="cambiarColorPestañaConc(this, <?= $es_invertido ? 'true' : 'false' ?>)">
                    <?= htmlspecialchars($tab['nombre']) ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="tab-content">
        <?php foreach($pestañas as $index => $tab): 
            $tab_id = "tab_" . $tab['id'];
            $es_invertido = (strpos(strtolower($tab['nombre']), 'invertido') !== false);
            $color_header = $es_invertido ? 'bg-primary' : 'bg-dark';
            $btn_color = $es_invertido ? 'btn-primary' : 'btn-dark';
            $clase_pane = ($index == 0) ? 'show active' : '';
        ?>
        <div class="tab-pane fade <?= $clase_pane ?>" id="<?= $tab_id ?>" role="tabpanel">
            
            <form action="guardar.php" method="POST">
                <input type="hidden" name="es_multiequipo" value="1">
                <input type="hidden" name="equipo_id" value="<?= $tab['id'] ?>">
                <input type="hidden" name="frecuencia_tab" value="CADA 2 HORAS">
                <input type="hidden" name="numero_nomina" value="<?= htmlspecialchars($_SESSION['nomina']) ?>">

                <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                    <div class="card-header <?= $color_header ?> text-white py-2 px-3 fw-bold d-flex justify-content-between align-items-center">
                        <div>
                            <span>Módulo: <?= htmlspecialchars($tab['nombre']) ?></span>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-danger btn-sm fw-bold" onclick="marcarEquipo('F.O.', <?= $tab['id'] ?>)">
                                    <i class="bi bi-x-circle-fill me-1"></i> F.O.
                                </button>
                                <button type="button" class="btn btn-info text-white btn-sm fw-bold" onclick="marcarEquipo('LAVADO', <?= $tab['id'] ?>)">
                                    <i class="bi bi-droplet-fill me-1"></i> LAVADO
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm fw-bold" onclick="marcarEquipo('', <?= $tab['id'] ?>)">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar
                                </button>
                            </div>
                            <a href="historial.php?id=<?= $tab['id'] ?>" class="btn btn-light btn-sm fw-bold shadow-sm text-dark">
                                <i class="bi bi-clock-history"></i>
                            </a>
                            <span class="badge bg-light text-dark">RUTINA: 2 HRS</span>
                        </div>
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
                                $stmt_p = mysqli_prepare($conn, "SELECT * FROM parametros WHERE equipo_id = ? ORDER BY id ASC");
                                mysqli_stmt_bind_param($stmt_p, "i", $tab['id']);
                                mysqli_stmt_execute($stmt_p);
                                $params = mysqli_stmt_get_result($stmt_p);

                                while ($p = mysqli_fetch_assoc($params)): 
                                    $reg = obtenerRegistroExistente2Horas($conn, $p['id']);
                                    $rb = $p['rojo_bajo'];
                                    $ab = $p['amarillo_bajo'];
                                    $aa = $p['amarillo_alto'];
                                    $ra = $p['rojo_alto'];
                                    $nombre_param = mb_strtolower(trim($p['nombre_parametro']), 'UTF-8');

                                    // Detectamos cualquier parámetro que tenga la palabra "condensador" (entrada y salida)
                                    $es_desactivado = (strpos($nombre_param, 'condensador') !== false);
                                ?>
                                <tr>
                                    <td class="fw-bold ps-3 text-dark">
                                        <?= htmlspecialchars($p['nombre_parametro']) ?>
                                        <?php if ($es_desactivado): ?>
                                            <span class="badge bg-secondary ms-2" style="font-size: 0.65rem;">DESACTIVADO</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-secondary" style="font-size: 0.85rem; line-height: 1.4;">
                                        <?php if ($es_desactivado): ?>
                                            <span class="text-muted fst-italic">Parámetro no requerido</span>
                                        <?php elseif ($p['tipo_dato'] == 'NUMERICO' && ($rb !== null || $ra !== null || $aa !== null)): ?>
                                            <?php if($rb !== null): ?> <div style="color: #dc3545; font-weight: bold;">Rojo Bajo: &lt;= <?= $rb ?></div> <?php endif; ?>
                                            <?php if($ab !== null): ?> <div style="color: #fd7e14; font-weight: bold;">Amarillo Bajo: <?= $rb ?? 'Mín' ?> a <?= $ab ?></div> <?php endif; ?>
                                            
                                            <div style="color: #198754; font-weight: bold;">Verde (Óptimo): <?= $ab ?? 'Mín' ?> a <?= $aa ?? 'Máx' ?></div>
                                            
                                            <?php if($aa !== null): ?> <div style="color: #fd7e14; font-weight: bold;">Amarillo Alto: <?= $aa ?> a <?= $ra ?? 'Máx' ?></div> <?php endif; ?>
                                            <?php if($ra !== null): ?> <div style="color: #dc3545; font-weight: bold;">Rojo Alto: &gt;= <?= $ra ?></div> <?php endif; ?>
                                        <?php elseif ($p['tipo_dato'] == 'NUMERICO'): ?>
                                            <span class="text-muted">Sin límite definido</span>
                                        <?php else: ?>
                                            <span class="text-muted">Control Cualitativo</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center px-2">
                                        <?php if ($reg): ?>
                                            <div class="badge-guardado fw-bold text-center">
                                                <?= htmlspecialchars($reg['valor_capturado']) ?>
                                            </div>
                                        <?php else: ?>
                                            <?php if ($es_desactivado): ?>
                                                <!-- CAMPO BLOQUEADO EN GRIS QUE MUESTRA DESACTIVADO -->
                                                <input type="text" name="param_<?= $p['id'] ?>" 
                                                    class="form-control text-center fw-bold fs-5 shadow-sm input-bloqueado-gris input-equipo-<?= $tab['id'] ?>" 
                                                    id="param_<?= $p['id'] ?>" 
                                                    value="DESACTIVADO" readonly tabindex="-1">
                                            <?php elseif ($p['tipo_dato'] == 'NUMERICO'): ?>
                                                <input type="number" step="0.01" name="param_<?= $p['id'] ?>" 
                                                    class="form-control text-center fw-bold fs-5 shadow-sm input-equipo-<?= $tab['id'] ?>" id="param_<?= $p['id'] ?>"
                                                    data-rb="<?= $rb ?>" data-ab="<?= $ab ?>" 
                                                    data-aa="<?= $aa ?>" data-ra="<?= $ra ?>"
                                                    oninput="evaluarSemaforo(this)"
                                                    onchange="forzarNegativo(this); evaluarSemaforo(this)">
                                            <?php else: ?>
                                                <input type="text" name="param_<?= $p['id'] ?>" class="form-control text-center input-equipo-<?= $tab['id'] ?>" id="param_<?= $p['id'] ?>" placeholder="Valor...">
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-3">
                                        <?php if ($reg): ?>
                                            <div class="text-muted small fst-italic"><?= htmlspecialchars($reg['observaciones']) ?: 'Sin notas' ?></div>
                                        <?php else: ?>
                                            <?php if ($es_desactivado): ?>
                                                <input type="hidden" name="obs_<?= $p['id'] ?>" value="Desactivado del sistema">
                                                <span class="text-muted small">—</span>
                                            <?php else: ?>
                                                <textarea name="obs_<?= $p['id'] ?>" class="form-control form-control-sm input-equipo-<?= $tab['id'] ?>" id="obs_<?= $p['id'] ?>" rows="1" placeholder="Notas..."></textarea>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="mb-4">
                    <button type="submit" class="btn <?= $btn_color ?> btn-lg w-100 py-3 fw-bold fs-5 shadow">
                        Guardar <?= htmlspecialchars($tab['nombre']) ?>
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

    // Seleccionamos los inputs que NO estén bloqueados en gris (excluye los desactivados)
    const campos = document.querySelectorAll('input.input-equipo-' + equipoId + ':not(.input-bloqueado-gris), select.input-equipo-' + equipoId);

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

function cambiarColorPestañaConc(btn, esInvertido) {
    document.querySelectorAll('.nav-pills .nav-link').forEach(el => {
        el.classList.remove('active-invertido');
    });
    if(esInvertido) {
        btn.classList.add('active-invertido');
    }
}

function forzarNegativo(input) {
    let rb = parseFloat(input.getAttribute('data-rb'));
    let aa = parseFloat(input.getAttribute('data-aa'));
    if (rb < 0 || aa < 0) {
        let val = parseFloat(input.value);
        if (!isNaN(val) && val > 0) {
            input.value = (val * -1);
        }
    }
}

function evaluarSemaforo(input) {
    const val = parseFloat(input.value);
    
    let rb = parseFloat(input.getAttribute('data-rb')); if(isNaN(rb)) rb = -999999;
    let ab = parseFloat(input.getAttribute('data-ab')); if(isNaN(ab)) ab = -999999;
    let aa = parseFloat(input.getAttribute('data-aa')); if(isNaN(aa)) aa = 999999;
    let ra = parseFloat(input.getAttribute('data-ra')); if(isNaN(ra)) ra = 999999;

    input.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
    if (isNaN(val) || input.value === '') return;

    if (val <= rb || val >= ra) { input.classList.add('semaforo-r'); } 
    else if ((val > rb && val <= ab) || (val >= aa && val < ra)) { input.classList.add('semaforo-a'); } 
    else { input.classList.add('semaforo-v'); }
}
</script>