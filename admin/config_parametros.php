<?php
// config_parametros.php
// Ventana exclusiva de Administrador para gestionar y separar rangos/parámetros por Zona y Equipo
// Conserva historial de rangos anteriores para no afectar lecturas previas al cambio.

session_start();
date_default_timezone_set('America/Mazatlan');

if (!isset($_SESSION['nomina'])) {
    header("Location: ../login.php");
    exit();
}

$rol_usuario = strtoupper(trim($_SESSION['rol'] ?? 'OPERADOR'));
if ($rol_usuario !== 'ADMIN') {
    // Bloqueo estricto: SOLO el Administrador puede entrar a esta ventana
    header("Location: ../index.php?acceso_denegado=1");
    exit();
}

include __DIR__ . '/../config/db.php';
include_once __DIR__ . '/../includes/rangos_historicos.php';

asegurarTablaHistorialRangos($conn);

// Asegurar que exista el parámetro de Delta de Presión para Sistema de Membranas (equipo_id = 100)
$chk_memb_param = mysqli_query($conn, "SELECT id FROM parametros WHERE equipo_id = 100 AND nombre_parametro LIKE '%Delta de Presi%' LIMIT 1");
if ($chk_memb_param && mysqli_num_rows($chk_memb_param) === 0) {
    // Verificar si existe el equipo 100 en la tabla equipos
    $chk_eq100 = mysqli_query($conn, "SELECT id FROM equipos WHERE id = 100 LIMIT 1");
    if ($chk_eq100 && mysqli_num_rows($chk_eq100) > 0) {
        @mysqli_query($conn, "INSERT INTO parametros (equipo_id, nombre_parametro, tipo_dato, rojo_bajo, amarillo_bajo, amarillo_alto, rojo_alto, frecuencia, grupo)
                              VALUES (100, 'Delta de Presión (Membranas)', 'NUMERICO', -0.01, -0.01, 1.20, 1.80, 'DIARIO', 'MEMBRANAS')");
    }
}

$mensaje_exito = '';
$mensaje_error = '';

// Procesar actualización de parámetros (Solo ADMIN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'guardar_parametro') {
        $param_id = (int)($_POST['parametro_id'] ?? 0);
        $rb_nuevo = ($_POST['rojo_bajo'] !== '' && $_POST['rojo_bajo'] !== null) ? (float)$_POST['rojo_bajo'] : null;
        $ab_nuevo = ($_POST['amarillo_bajo'] !== '' && $_POST['amarillo_bajo'] !== null) ? (float)$_POST['amarillo_bajo'] : null;
        $aa_nuevo = ($_POST['amarillo_alto'] !== '' && $_POST['amarillo_alto'] !== null) ? (float)$_POST['amarillo_alto'] : null;
        $ra_nuevo = ($_POST['rojo_alto'] !== '' && $_POST['rojo_alto'] !== null) ? (float)$_POST['rojo_alto'] : null;
        $motivo   = trim($_POST['motivo'] ?? '');
        $admin_nomina = $_SESSION['nomina'] ?? 'ADMIN';

        // Consultar valores actuales antes de modificar
        $q_act = mysqli_query($conn, "SELECT * FROM parametros WHERE id = $param_id LIMIT 1");
        if ($q_act && ($p_act = mysqli_fetch_assoc($q_act))) {
            $rb_ant = $p_act['rojo_bajo'] !== null ? (float)$p_act['rojo_bajo'] : null;
            $ab_ant = $p_act['amarillo_bajo'] !== null ? (float)$p_act['amarillo_bajo'] : null;
            $aa_ant = $p_act['amarillo_alto'] !== null ? (float)$p_act['amarillo_alto'] : null;
            $ra_ant = $p_act['rojo_alto'] !== null ? (float)$p_act['rojo_alto'] : null;
            $eq_id_act = (int)$p_act['equipo_id'];

            $hubo_cambio = (
                $rb_ant !== $rb_nuevo ||
                $ab_ant !== $ab_nuevo ||
                $aa_ant !== $aa_nuevo ||
                $ra_ant !== $ra_nuevo
            );

            if ($hubo_cambio) {
                $fecha_ahora = date('Y-m-d H:i:s');
                $rb_ant_sql = $rb_ant !== null ? "'$rb_ant'" : "NULL";
                $ab_ant_sql = $ab_ant !== null ? "'$ab_ant'" : "NULL";
                $aa_ant_sql = $aa_ant !== null ? "'$aa_ant'" : "NULL";
                $ra_ant_sql = $ra_ant !== null ? "'$ra_ant'" : "NULL";

                $rb_nue_sql = $rb_nuevo !== null ? "'$rb_nuevo'" : "NULL";
                $ab_nue_sql = $ab_nuevo !== null ? "'$ab_nuevo'" : "NULL";
                $aa_nue_sql = $aa_nuevo !== null ? "'$aa_nuevo'" : "NULL";
                $ra_nue_sql = $ra_nuevo !== null ? "'$ra_nuevo'" : "NULL";

                $motivo_esc = mysqli_real_escape_string($conn, $motivo ?: 'Actualización de rango por Administrador');
                $nomina_esc = mysqli_real_escape_string($conn, $admin_nomina);

                // 1. Guardar el rango anterior en el historial con la fecha/hora exacta del cambio
                $sql_hist = "INSERT INTO parametros_historial_rangos 
                    (parametro_id, equipo_id, rojo_bajo_ant, amarillo_bajo_ant, amarillo_alto_ant, rojo_alto_ant,
                     rojo_bajo_nuevo, amarillo_bajo_nuevo, amarillo_alto_nuevo, rojo_alto_nuevo, motivo, admin_nomina, fecha_cambio)
                    VALUES (
                        $param_id, $eq_id_act, $rb_ant_sql, $ab_ant_sql, $aa_ant_sql, $ra_ant_sql,
                        $rb_nue_sql, $ab_nue_sql, $aa_nue_sql, $ra_nue_sql, '$motivo_esc', '$nomina_esc', '$fecha_ahora'
                    )";
                mysqli_query($conn, $sql_hist);

                // 2. Actualizar el parámetro con los nuevos límites para las capturas futuras
                $sql_upd = "UPDATE parametros SET 
                    rojo_bajo = $rb_nue_sql,
                    amarillo_bajo = $ab_nue_sql,
                    amarillo_alto = $aa_nue_sql,
                    rojo_alto = $ra_nue_sql
                    WHERE id = $param_id";
                if (mysqli_query($conn, $sql_upd)) {
                    $mensaje_exito = "Se actualizaron los rangos de «" . htmlspecialchars($p_act['nombre_parametro']) . "». Los registros capturados antes de hoy a las " . date('H:i:s', strtotime($fecha_ahora)) . " conservarán su evaluación con el rango anterior y mostrarán una señal identificadora.";
                } else {
                    $mensaje_error = "Error al actualizar el parámetro: " . mysqli_error($conn);
                }
            } else {
                $mensaje_error = "No se detectaron cambios en los valores del parámetro «" . htmlspecialchars($p_act['nombre_parametro']) . "».";
            }
        }
    }
}

// Cargar Zonas, Áreas y Equipos para separar y filtrar cómodamente
$zonas = [];
$q_z = mysqli_query($conn, "SELECT * FROM zonas ORDER BY id ASC");
if ($q_z) while ($r = mysqli_fetch_assoc($q_z)) $zonas[] = $r;

// Consultar todos los parámetros con su Zona, Área y Equipo
$sql_params = "SELECT p.*, 
                      e.nombre AS equipo_nombre, 
                      a.id AS area_id, a.nombre AS area_nombre, 
                      z.id AS zona_id, z.nombre AS zona_nombre
               FROM parametros p
               JOIN equipos e ON p.equipo_id = e.id
               JOIN areas a ON e.area_id = a.id
               JOIN zonas z ON a.zona_id = z.id
               ORDER BY z.id ASC, a.id ASC, e.id ASC, p.id ASC";
$res_params = mysqli_query($conn, $sql_params);
$lista_parametros = [];
$equipos_unicos = [];
if ($res_params) {
    while ($row = mysqli_fetch_assoc($res_params)) {
        $lista_parametros[] = $row;
        $equipos_unicos[$row['equipo_id']] = [
            'id' => $row['equipo_id'],
            'nombre' => str_replace(['Producto Votator', 'Producto '], 'Votator ', $row['equipo_nombre']),
            'zona_id' => $row['zona_id'],
            'zona_nombre' => $row['zona_nombre']
        ];
    }
}

// Cargar historial de cambios para mostrar el conteo y bitácora
$historial_por_param = cargarHistorialRangos($conn);

// Últimos 100 cambios realizados para la ventana de bitácora
$ultimos_cambios = [];
$q_ult = mysqli_query($conn, "SELECT h.*, p.nombre_parametro, e.nombre AS equipo_nombre, z.nombre AS zona_nombre
                              FROM parametros_historial_rangos h
                              LEFT JOIN parametros p ON h.parametro_id = p.id
                              LEFT JOIN equipos e ON h.equipo_id = e.id
                              LEFT JOIN areas a ON e.area_id = a.id
                              LEFT JOIN zonas z ON a.zona_id = z.id
                              ORDER BY h.fecha_cambio DESC, h.id DESC LIMIT 100");
if ($q_ult) {
    while ($u = mysqli_fetch_assoc($q_ult)) {
        $ultimos_cambios[] = $u;
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
    body {
        background: #f1f5f9;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
    }
    .admin-hero {
        background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
        color: white;
        border-radius: 18px;
        padding: 1.6rem 2rem;
        box-shadow: 0 10px 25px rgba(30, 27, 75, 0.18);
        margin-bottom: 1.5rem;
    }
    .filter-card {
        background: #ffffff;
        border-radius: 14px;
        padding: 1.2rem 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        border: 1px solid #e2e8f0;
        margin-bottom: 1.5rem;
    }
    .btn-zona-tab {
        border-radius: 30px;
        font-weight: 700;
        font-size: 0.85rem;
        padding: 0.45rem 1.1rem;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #334155;
        transition: all 0.2s;
    }
    .btn-zona-tab.active, .btn-zona-tab:hover {
        background: #4f46e5;
        color: white;
        border-color: #4338ca;
    }
    .equipo-section-card {
        background: #ffffff;
        border-radius: 14px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
        margin-bottom: 1.25rem;
        overflow: hidden;
    }
    .equipo-section-header {
        background: #0f172a;
        color: #ffffff;
        padding: 0.85rem 1.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }
    .input-rango {
        width: 95px;
        text-align: center;
        font-weight: 700;
        font-family: monospace;
        font-size: 0.9rem;
    }
    .input-rango-rb { border-color: #ef4444; background-color: #fef2f2; color: #991b1b; }
    .input-rango-ab { border-color: #f59e0b; background-color: #fffbeb; color: #92400e; }
    .input-rango-aa { border-color: #f59e0b; background-color: #fffbeb; color: #92400e; }
    .input-rango-ra { border-color: #ef4444; background-color: #fef2f2; color: #991b1b; }
    .badge-optimo-preview {
        background-color: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
        font-weight: 700;
        font-size: 0.8rem;
        padding: 0.35rem 0.65rem;
        border-radius: 8px;
        display: inline-block;
        font-family: monospace;
    }
    .badge-modificado {
        background-color: #ede9fe;
        color: #5b21b6;
        border: 1px solid #c4b5fd;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 0.2rem 0.55rem;
        border-radius: 20px;
    }
</style>

<div class="container-fluid py-4 px-4">
    <!-- Encabezado Exclusivo Admin -->
    <div class="admin-hero d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2">
                <span class="badge bg-warning text-dark fw-bold px-3 py-1 rounded-pill">
                    <i class="bi bi-shield-lock-fill me-1"></i> EXCLUSIVO ADMINISTRADOR
                </span>
                <span class="badge bg-light text-dark fw-bold px-3 py-1 rounded-pill">
                    Nómina: <?= htmlspecialchars($_SESSION['nomina']) ?>
                </span>
            </div>
            <h3 class="fw-bold m-0"><i class="bi bi-sliders2-vertical me-2"></i> Control y Modificación de Rangos de Parámetros</h3>
            <p class="m-0 text-white-50 small mt-1">
                Separa por Zona o Equipo para ajustar los límites. <strong>Los registros anteriores a un cambio conservan su rango original y muestran una señal identificadora.</strong>
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-warning fw-bold rounded-pill px-4 shadow-sm text-dark" data-bs-toggle="modal" data-bs-target="#modalBitacoraCambios">
                <i class="bi bi-clock-history me-1"></i> Bitácora de Cambios (<?= count($ultimos_cambios) ?>)
            </button>
            <a href="../reporte_general.php" class="btn btn-light fw-bold rounded-pill px-4 shadow-sm">
                <i class="bi bi-grid-3x3-gap-fill me-1"></i> Ir a Reporte General
            </a>
            <a href="../index.php" class="btn btn-outline-light fw-bold rounded-pill px-4">
                <i class="bi bi-arrow-left me-1"></i> Volver al Mapa
            </a>
        </div>
    </div>

    <?php if ($mensaje_exito): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-start border-5 border-success fw-semibold" role="alert">
            <i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i> <?= $mensaje_exito ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($mensaje_error): ?>
        <div class="alert alert-warning alert-dismissible fade show shadow-sm border-start border-5 border-warning fw-semibold" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2 fs-5 align-middle"></i> <?= $mensaje_error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Barra de Separación por Zona, Equipo y Buscador -->
    <div class="filter-card">
        <div class="row g-3 align-items-center">
            <div class="col-lg-5">
                <label class="small fw-bold text-secondary d-block mb-2"><i class="bi bi-geo-alt-fill text-danger me-1"></i> 1. Separar por Zona:</label>
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn-zona-tab active" onclick="filtrarZonaParam(0, this)">
                        Todas las Zonas
                    </button>
                    <?php foreach ($zonas as $z): ?>
                        <button type="button" class="btn-zona-tab" onclick="filtrarZonaParam(<?= (int)$z['id'] ?>, this)">
                            <?= htmlspecialchars($z['nombre']) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-lg-4">
                <label class="small fw-bold text-secondary d-block mb-2"><i class="bi bi-cpu-fill text-primary me-1"></i> 2. Separar por Equipo:</label>
                <select id="selectEquipoFiltro" class="form-select fw-bold" onchange="aplicarFiltrosParam()">
                    <option value="0">-- Todos los Equipos --</option>
                    <?php foreach ($equipos_unicos as $eq): ?>
                        <option value="<?= (int)$eq['id'] ?>" data-zona="<?= (int)$eq['zona_id'] ?>">
                            [<?= htmlspecialchars($eq['zona_nombre']) ?>] <?= htmlspecialchars($eq['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="small fw-bold text-secondary d-block mb-2"><i class="bi bi-search text-success me-1"></i> 3. Buscar Parámetro:</label>
                <input type="text" id="inputBuscarParam" class="form-control" placeholder="Ej. Temperatura, Brix, Presión..." onkeyup="aplicarFiltrosParam()">
            </div>
        </div>
    </div>

    <!-- Listado de Parámetros agrupados por Equipo -->
    <?php
    $params_por_equipo = [];
    foreach ($lista_parametros as $p) {
        $params_por_equipo[$p['equipo_id']]['info'] = [
            'equipo_id' => $p['equipo_id'],
            'equipo_nombre' => str_replace(['Producto Votator', 'Producto '], 'Votator ', $p['equipo_nombre']),
            'area_nombre' => $p['area_nombre'],
            'zona_id' => $p['zona_id'],
            'zona_nombre' => $p['zona_nombre']
        ];
        $params_por_equipo[$p['equipo_id']]['items'][] = $p;
    }
    ?>

    <div id="contenedorEquiposParams">
        <?php foreach ($params_por_equipo as $eq_id => $bloque): 
            $info = $bloque['info'];
            $items = $bloque['items'];
        ?>
            <div class="equipo-section-card bloque-equipo-param" 
                 data-zona-id="<?= (int)$info['zona_id'] ?>" 
                 data-equipo-id="<?= (int)$eq_id ?>">
                <div class="equipo-section-header">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="badge bg-primary rounded-pill px-3 py-1"><?= htmlspecialchars($info['zona_nombre']) ?></span>
                        <span class="badge bg-secondary rounded-pill px-2 py-1 small"><?= htmlspecialchars($info['area_nombre']) ?></span>
                        <h5 class="m-0 fw-bold text-white ms-1">
                            <i class="bi bi-cpu me-1 text-warning"></i> <?= htmlspecialchars($info['equipo_nombre']) ?>
                        </h5>
                    </div>
                    <span class="badge bg-light text-dark fw-bold"><?= count($items) ?> Parámetros</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small text-uppercase">
                            <tr>
                                <th class="ps-3" style="min-width: 220px;">Parámetro</th>
                                <th class="text-center">Frecuencia / Tipo</th>
                                <th class="text-center text-danger" title="Valores menores o iguales a este límite se marcan en ROJO">🔴 Crítico Bajo (&lt;=)</th>
                                <th class="text-center text-warning" title="Valores entre Crítico Bajo y este límite se marcan en AMARILLO">🟡 Alerta Baja (&lt;=)</th>
                                <th class="text-center text-success">🟢 Rango Óptimo</th>
                                <th class="text-center text-warning" title="Valores desde este límite hasta antes de Crítico Alto se marcan en AMARILLO">🟡 Alerta Alta (&gt;=)</th>
                                <th class="text-center text-danger" title="Valores mayores o iguales a este límite se marcan en ROJO">🔴 Crítico Alto (&gt;=)</th>
                                <th style="min-width: 170px;">Motivo del Cambio</th>
                                <th class="text-end pe-3">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $p): 
                                $pid = (int)$p['id'];
                                $es_num = ($p['tipo_dato'] === 'NUMERICO');
                                $cambios_param = $historial_por_param[$pid] ?? [];
                                $num_cambios = count($cambios_param);
                                $ultimo_cambio = $num_cambios > 0 ? $cambios_param[$num_cambios - 1] : null;
                                $form_id = "form_param_" . $pid;
                            ?>
                                <tr class="fila-param-config" data-nombre="<?= htmlspecialchars(mb_strtolower($p['nombre_parametro'] . ' ' . $info['equipo_nombre'], 'UTF-8')) ?>">
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($p['nombre_parametro']) ?></div>
                                        <?php if ($num_cambios > 0): ?>
                                            <div class="mt-1">
                                                <span class="badge-modificado" title="Último cambio: <?= date('d/m/Y H:i', strtotime($ultimo_cambio['fecha_cambio'])) ?> por Nómina <?= htmlspecialchars($ultimo_cambio['admin_nomina']) ?> (Rango ant: <?= $ultimo_cambio['amarillo_bajo_ant'] ?> a <?= $ultimo_cambio['amarillo_alto_ant'] ?>)">
                                                    <i class="bi bi-clock-history me-1"></i> <?= $num_cambios ?> cambio(s) · Últ: <?= date('d/m/Y H:i', strtotime($ultimo_cambio['fecha_cambio'])) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-secondary border small"><?= htmlspecialchars($p['frecuencia'] ?: 'DIARIO') ?></span>
                                        <div class="small text-muted" style="font-size: 0.7rem;"><?= htmlspecialchars($p['tipo_dato']) ?></div>
                                    </td>

                                    <?php if ($es_num): ?>
                                        <td class="text-center">
                                            <input type="number" step="0.01" name="rojo_bajo" form="<?= $form_id ?>"
                                                   class="form-control form-control-sm mx-auto input-rango input-rango-rb"
                                                   value="<?= htmlspecialchars($p['rojo_bajo'] ?? '') ?>"
                                                   oninput="actualizarPreviewOptimo(<?= $pid ?>)" id="rb_<?= $pid ?>">
                                        </td>
                                        <td class="text-center">
                                            <input type="number" step="0.01" name="amarillo_bajo" form="<?= $form_id ?>"
                                                   class="form-control form-control-sm mx-auto input-rango input-rango-ab"
                                                   value="<?= htmlspecialchars($p['amarillo_bajo'] ?? '') ?>"
                                                   oninput="actualizarPreviewOptimo(<?= $pid ?>)" id="ab_<?= $pid ?>">
                                        </td>
                                        <td class="text-center">
                                            <span class="badge-optimo-preview" id="optimo_<?= $pid ?>">
                                                <?php if ($p['amarillo_bajo'] !== null && $p['amarillo_alto'] !== null): ?>
                                                    &gt; <?= (float)$p['amarillo_bajo'] ?> a &lt; <?= (float)$p['amarillo_alto'] ?>
                                                <?php else: ?>
                                                    Sin definir
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <input type="number" step="0.01" name="amarillo_alto" form="<?= $form_id ?>"
                                                   class="form-control form-control-sm mx-auto input-rango input-rango-aa"
                                                   value="<?= htmlspecialchars($p['amarillo_alto'] ?? '') ?>"
                                                   oninput="actualizarPreviewOptimo(<?= $pid ?>)" id="aa_<?= $pid ?>">
                                        </td>
                                        <td class="text-center">
                                            <input type="number" step="0.01" name="rojo_alto" form="<?= $form_id ?>"
                                                   class="form-control form-control-sm mx-auto input-rango input-rango-ra"
                                                   value="<?= htmlspecialchars($p['rojo_alto'] ?? '') ?>"
                                                   oninput="actualizarPreviewOptimo(<?= $pid ?>)" id="ra_<?= $pid ?>">
                                        </td>
                                        <td>
                                            <input type="text" name="motivo" form="<?= $form_id ?>"
                                                   class="form-control form-control-sm"
                                                   placeholder="Ej. Ajuste de receta / proceso...">
                                        </td>
                                        <td class="text-end pe-3">
                                            <form id="<?= $form_id ?>" method="POST" action="config_parametros.php" class="m-0">
                                                <input type="hidden" name="accion" value="guardar_parametro">
                                                <input type="hidden" name="parametro_id" value="<?= $pid ?>">
                                                <button type="submit" class="btn btn-sm btn-primary fw-bold rounded-pill px-3 shadow-sm">
                                                    <i class="bi bi-floppy-fill me-1"></i> Guardar
                                                </button>
                                            </form>
                                        </td>
                                    <?php else: ?>
                                        <td colspan="7" class="text-center text-muted small fst-italic">
                                            Parámetro cualitativo (Sí / No / Realizada / Estado) — no utiliza límites numéricos.
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Modal de Bitácora de Cambios de Rangos -->
<div class="modal fade" id="modalBitacoraCambios" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-clock-history text-warning me-2"></i> Historial de Modificaciones de Rangos
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped align-middle text-center mb-0 small">
                        <thead class="table-secondary">
                            <tr>
                                <th>Fecha / Hora Exacta del Cambio</th>
                                <th>Admin (Nómina)</th>
                                <th>Zona / Equipo</th>
                                <th>Parámetro</th>
                                <th>Rango Anterior (Conservado en registros previos)</th>
                                <th>Rango Nuevo</th>
                                <th>Motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($ultimos_cambios)): ?>
                                <?php foreach ($ultimos_cambios as $c): ?>
                                    <tr>
                                        <td class="fw-bold font-monospace"><?= date('d/m/Y H:i:s', strtotime($c['fecha_cambio'])) ?></td>
                                        <td><span class="badge bg-dark rounded-pill px-2 py-1"><i class="bi bi-person-badge me-1"></i><?= htmlspecialchars($c['admin_nomina']) ?></span></td>
                                        <td class="fw-semibold"><?= htmlspecialchars(($c['zona_nombre'] ? $c['zona_nombre'] . ' · ' : '') . ($c['equipo_nombre'] ?? 'Equipo #' . $c['equipo_id'])) ?></td>
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($c['nombre_parametro'] ?? 'Parámetro #' . $c['parametro_id']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace">
                                                🔴 &lt;= <?= (float)$c['rojo_bajo_ant'] ?> |
                                                🟡 <?= (float)$c['amarillo_bajo_ant'] ?> |
                                                🟢 Óptimo |
                                                🟡 <?= (float)$c['amarillo_alto_ant'] ?> |
                                                🔴 &gt;= <?= (float)$c['rojo_alto_ant'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary bg-opacity-10 text-primary border border-primary font-monospace">
                                                🔴 &lt;= <?= (float)$c['rojo_bajo_nuevo'] ?> |
                                                🟡 <?= (float)$c['amarillo_bajo_nuevo'] ?> |
                                                🟢 Óptimo |
                                                🟡 <?= (float)$c['amarillo_alto_nuevo'] ?> |
                                                🔴 &gt;= <?= (float)$c['rojo_alto_nuevo'] ?>
                                            </span>
                                        </td>
                                        <td class="text-start text-muted"><?= htmlspecialchars($c['motivo'] ?: '-') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="py-4 text-muted">Aún no se han registrado modificaciones de rangos.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let zonaSeleccionadaParam = 0;

function filtrarZonaParam(zonaId, btn) {
    zonaSeleccionadaParam = zonaId;
    document.querySelectorAll('.btn-zona-tab').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');

    // Filtrar opciones del select de equipos según la zona
    const selectEq = document.getElementById('selectEquipoFiltro');
    Array.from(selectEq.options).forEach(opt => {
        if (opt.value === "0") return;
        const zOpt = parseInt(opt.getAttribute('data-zona') || '0', 10);
        opt.hidden = (zonaId !== 0 && zOpt !== zonaId);
    });
    if (selectEq.selectedOptions[0]?.hidden) {
        selectEq.value = "0";
    }
    aplicarFiltrosParam();
}

function aplicarFiltrosParam() {
    const eqId = parseInt(document.getElementById('selectEquipoFiltro').value || '0', 10);
    const texto = (document.getElementById('inputBuscarParam').value || '').toLowerCase().trim();

    document.querySelectorAll('.bloque-equipo-param').forEach(bloque => {
        const bZona = parseInt(bloque.getAttribute('data-zona-id') || '0', 10);
        const bEq   = parseInt(bloque.getAttribute('data-equipo-id') || '0', 10);

        const cumpleZona = (zonaSeleccionadaParam === 0 || bZona === zonaSeleccionadaParam);
        const cumpleEq   = (eqId === 0 || bEq === eqId);

        if (!cumpleZona || !cumpleEq) {
            bloque.style.display = 'none';
            return;
        }

        let filasVisibles = 0;
        bloque.querySelectorAll('.fila-param-config').forEach(fila => {
            const nombre = fila.getAttribute('data-nombre') || '';
            if (!texto || nombre.includes(texto)) {
                fila.style.display = '';
                filasVisibles++;
            } else {
                fila.style.display = 'none';
            }
        });

        bloque.style.display = (filasVisibles > 0) ? '' : 'none';
    });
}

function actualizarPreviewOptimo(pid) {
    const ab = document.getElementById('ab_' + pid)?.value;
    const aa = document.getElementById('aa_' + pid)?.value;
    const span = document.getElementById('optimo_' + pid);
    if (!span) return;
    if (ab !== '' && aa !== '') {
        span.innerHTML = `&gt; ${ parseFloat(ab) } a &lt; ${ parseFloat(aa) }`;
    } else {
        span.textContent = 'Sin definir';
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>

