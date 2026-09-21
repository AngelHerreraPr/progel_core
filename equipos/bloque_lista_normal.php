<?php 
// Progel_cores/equipos/bloque_lista_normal.php

// Buscamos dinámicamente las frecuencias que tenga este equipo en la BD
$stmt_f = mysqli_prepare($conn, "SELECT DISTINCT frecuencia FROM parametros WHERE equipo_id = ? ORDER BY FIELD(frecuencia, 'DIARIO', 'SEMANAL', 'MENSUAL')");
mysqli_stmt_bind_param($stmt_f, "i", $equipo_id);
mysqli_stmt_execute($stmt_f);
$res_f = mysqli_stmt_get_result($stmt_f);

$frecuencias_tab = [];
while($row_f = mysqli_fetch_assoc($res_f)) {
    if(!empty($row_f['frecuencia'])) $frecuencias_tab[] = $row_f['frecuencia'];
}
if (empty($frecuencias_tab)) $frecuencias_tab = ['DIARIO'];
?>

<div class="mb-3 d-flex justify-content-between align-items-center gap-2 flex-wrap">
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
    <div class="d-flex justify-content-end gap-2">
        <button type="button" class="btn btn-outline-primary btn-sm" disabled>
            Nómina: <span class="fw-bold text-dark"><?= htmlspecialchars($_SESSION['nomina']) ?></span>
        </button>
        <a href="historial.php?id=<?= $equipo_id ?>" class="btn btn-warning btn-sm fw-bold text-dark shadow-sm">
            Historial del Equipo
        </a>
    </div>
</div>

<ul class="nav nav-pills mb-3" role="tablist">
    <?php foreach($frecuencias_tab as $index => $ft): ?>
        <li class="nav-item">
            <button class="nav-link <?= $index == 0 ? 'active' : '' ?>" data-bs-toggle="pill" data-bs-target="#tab-<?= str_replace(' ', '_', $ft) ?>">
                <?= htmlspecialchars($ft) ?>
            </button>
        </li>
    <?php endforeach; ?>
</ul>

<div class="tab-content">
    <?php foreach($frecuencias_tab as $index => $ft): ?>
        <div class="tab-pane fade <?= $index == 0 ? 'show active' : '' ?>" id="tab-<?= str_replace(' ', '_', $ft) ?>">
            <form action="/Progel_cores/guardar.php" method="POST" class="formCaptura">
                <input type="hidden" name="equipo_id" value="<?= $equipo_id ?>">
                <input type="hidden" name="frecuencia_tab" value="<?= htmlspecialchars($ft) ?>">
                <input type="hidden" name="numero_nomina" value="<?= htmlspecialchars($_SESSION['nomina']) ?>">

                <?php
                // Obtener los grupos únicos asignados a este equipo (ej. CHILLER 1, CHILLER 2, GENERAL)
                $stmt_grupos = mysqli_prepare($conn, "SELECT DISTINCT grupo FROM parametros WHERE equipo_id = ? AND frecuencia = ? ORDER BY grupo ASC");
                mysqli_stmt_bind_param($stmt_grupos, "is", $equipo_id, $ft);
                mysqli_stmt_execute($stmt_grupos);
                $query_grupos = mysqli_stmt_get_result($stmt_grupos);

                while ($g = mysqli_fetch_assoc($query_grupos)):
                    $nombre_grupo = $g['grupo'] ?: 'GENERAL';
                ?>
                    <div class="card shadow-sm border-0 mb-4 rounded-3 overflow-hidden">
                        <div class="card-header bg-dark text-white py-2 px-3 fw-bold">
                            <?= htmlspecialchars($nombre_grupo) ?>
                        </div>
                        <div class="card-body p-0">
                            <table class="table table-bordered align-middle m-0 bg-white">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3" style="width: 35%;">Parámetro / Variable</th>
                                        <th style="width: 25%;">Límites de Referencia</th>
                                        <th class="text-center" style="width: 180px;">Valor Real</th>
                                        <th>Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    // Consultar únicamente los parámetros pertenecientes a este grupo específico
                                    $stmt_p = mysqli_prepare($conn, "SELECT * FROM parametros WHERE equipo_id = ? AND frecuencia = ? AND (grupo = ? OR (grupo IS NULL AND ? = 'GENERAL')) ORDER BY id ASC");
                                    mysqli_stmt_bind_param($stmt_p, "isss", $equipo_id, $ft, $nombre_grupo, $nombre_grupo);
                                    mysqli_stmt_execute($stmt_p);
                                    $params = mysqli_stmt_get_result($stmt_p);

                                    while ($p = mysqli_fetch_assoc($params)):
                                        $rb = $p['rojo_bajo']; $ab = $p['amarillo_bajo'];
                                        $aa = $p['amarillo_alto']; $ra = $p['rojo_alto'];
                                        $nombre_param = strtolower($p['nombre_parametro']);
                                    ?>
                                    <tr>
                                        <td class="fw-bold text-dark ps-3"><?= htmlspecialchars($p['nombre_parametro']) ?></td>
                                        
                                        <td class="text-secondary" style="font-size: 0.82rem; line-height: 1.5; white-space: nowrap;">
                                            <?php if ($p['tipo_dato'] == 'NUMERICO' && $rb !== NULL): ?>
                                                <ul class="list-unstyled mb-0">
                                                    <li class="d-flex align-items-center"><div class="me-2" style="width:12px; height:12px; background-color:#cb444a; border-radius:50%;"></div><strong>Crítico Bajo:</strong><span class="ms-1">&lt;= <?= $rb ?></span></li>
                                                    <li class="d-flex align-items-center"><div class="me-2" style="width:12px; height:12px; background-color:#e69623; border-radius:50%;"></div><strong>Alerta Baja:</strong><span class="ms-1">&gt; <?= $rb ?> a <?= $ab ?></span></li>
                                                    <li class="d-flex align-items-center"><div class="me-2" style="width:12px; height:12px; background-color:#28a745; border-radius:50%;"></div><strong>Óptimo:</strong><span class="ms-1">&gt; <?= $ab ?> a &lt; <?= $aa ?></span></li>
                                                    <li class="d-flex align-items-center"><div class="me-2" style="width:12px; height:12px; background-color:#e69623; border-radius:50%;"></div><strong>Alerta Alta:</strong><span class="ms-1">&gt;= <?= $aa ?> a &lt; <?= $ra ?></span></li>
                                                    <li class="d-flex align-items-center"><div class="me-2" style="width:12px; height:12px; background-color:#cb444a; border-radius:50%;"></div><strong>Crítico Alto:</strong><span class="ms-1">&gt;= <?= $ra ?></span></li>
                                                </ul>
                                            <?php else: ?>
                                                <span class="text-muted">Cualitativo / Control</span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-center pe-3">
                                            <?php if ($p['tipo_dato'] == 'NUMERICO'): ?>
                                                <input type="number" step="0.01" name="param_<?= $p['id'] ?>"
                                                       class="form-control text-center fw-bold input-equipo-<?= $equipo_id ?>" id="param_<?= $p['id'] ?>"
                                                       data-rb="<?= $p['rojo_bajo'] ?>" data-ab="<?= $p['amarillo_bajo'] ?>" 
                                                       data-aa="<?= $p['amarillo_alto'] ?>" data-ra="<?= $p['rojo_alto'] ?>"
                                                       oninput="evaluarSemaforoUnico(this)"
                                                       onchange="forzarNegativoUnico(this); evaluarSemaforoUnico(this)" required>
                                            <?php else: ?>
                                                <input type="text" name="param_<?= $p['id'] ?>" class="form-control text-center input-equipo-<?= $equipo_id ?>" id="param_<?= $p['id'] ?>" placeholder="Notas...">
                                            <?php endif; ?>
                                        </td>
                                        <td class="pe-3">
                                            <textarea name="obs_<?= $p['id'] ?>" class="form-control form-control-sm input-equipo-<?= $equipo_id ?>" id="obs_<?= $p['id'] ?>" rows="1" placeholder="Observaciones..."></textarea>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endwhile; ?>

                <div class="mt-4 p-2">
                    <button type="submit" class="btn btn-primary btn-lg w-100 py-3 fw-bold fs-4">
                        Guardar Registro <?= htmlspecialchars($ft) ?>
                    </button>
                </div>
            </form>
        </div>
    <?php endforeach; ?>
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

function forzarNegativoUnico(input) {
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
}

function evaluarSemaforoUnico(input) {
    const rb = parseFloat(input.getAttribute('data-rb'));
    const ab = parseFloat(input.getAttribute('data-ab'));
    const aa = parseFloat(input.getAttribute('data-aa'));
    const ra = parseFloat(input.getAttribute('data-ra'));
    const val = parseFloat(input.value);

    input.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
    if (isNaN(val) || input.value === '') return;

    if (val <= rb || val >= ra) {
        input.classList.add('semaforo-r');
    } else if ((val > rb && val <= ab) || (val >= aa && val < ra)) {
        input.classList.add('semaforo-a');
    } else {
        input.classList.add('semaforo-v');
    }
}
</script>