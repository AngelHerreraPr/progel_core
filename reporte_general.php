<?php
// Progel_cores/reporte_general.php
session_start();
date_default_timezone_set('America/Mazatlan');
include 'config/db.php';

if (!isset($_SESSION['nomina'])) {
    header("Location: login.php");
    exit();
}

// ===== FUNCIÓN AUXILIAR PARA LOS COLORES EXACTOS =====
function getBadgeClass($val, $p) {
    if ($val === null) return 'bg-gray';
    if (is_numeric($val)) {
        $v = (float)$val;
        $rb = $p['rojo_bajo'] ?? -999999;
        $ra = $p['rojo_alto'] ?? 999999;
        $ab = $p['amarillo_bajo'] ?? -999999;
        $aa = $p['amarillo_alto'] ?? 999999;
        if ($v <= $rb || $v >= $ra) return 'bg-red';
        if (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) return 'bg-yellow';
        return 'bg-green';
    }
    $val_low = strtolower(trim($val));
    if (in_array($val_low, ['realizada', 'sí', 'si', 'no presenta', 'firme'])) return 'bg-green';
    if (in_array($val_low, ['no aplica', 'n/a'])) return 'bg-gray';
    if (in_array($val_low, ['lavado'])) return 'bg-lavado';
    if (in_array($val_low, ['f.o.', 'mtto', 'no', 'sí presenta', 'húmeda', 'sobreseca', 'quemada'])) return 'bg-red';
    return 'bg-yellow';
}

// ===== PARÁMETROS DE FILTRO Y FECHA ACTUAL =====
$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'diaria';
$fecha_hoy = date('Y-m-d');
$hora_actual_num = (int)date('G');

if ($vista == 'semanal') {
    $lunes = date('Y-m-d', strtotime('monday this week', strtotime($fecha_filtro)));
    $domingo = date('Y-m-d', strtotime('sunday this week', strtotime($fecha_filtro)));
    $dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    $dias_fechas = [];
    for ($i = 0; $i < 7; $i++) {
        $dias_fechas[] = date('Y-m-d', strtotime("$lunes + $i days"));
    }
}

// Bloques de 2 horas
$horas_columnas_base = [
    "00:00", "02:00", "04:00", "06:00",
    "08:00", "10:00", "12:00", "14:00",
    "16:00", "18:00", "20:00", "22:00"
];

// Si es el día de hoy, mostramos las horas conforme van pasando en el día (evita tabla gigante con horas futuras vacías)
$mostrar_todas = isset($_GET['todas_horas']) && $_GET['todas_horas'] == '1';
if ($vista == 'diaria' && $fecha_filtro == $fecha_hoy && !$mostrar_todas) {
    $horas_columnas = array_values(array_filter($horas_columnas_base, function($h) use ($hora_actual_num) {
        return (int)substr($h, 0, 2) <= $hora_actual_num;
    }));
    if (empty($horas_columnas)) {
        $horas_columnas = ["00:00"];
    }
} else {
    $horas_columnas = $horas_columnas_base;
}

// ===== MAGIA: BLOQUEO DE ZONA NEGRA PARA SUPERVISORES =====
$rol_usuario_actual = $_SESSION['rol'] ?? '';
$filtro_zona_sql = ($rol_usuario_actual === 'SUPERVISOR') ? "WHERE id != 3" : "";
$filtro_area_sql = ($rol_usuario_actual === 'SUPERVISOR') ? "AND a.zona_id != 3" : "";

// ===== KPIs =====
$total_lecturas = $total_verdes = $total_amarillos = $total_rojos = 0;
$filtro_kpi_zona = ($rol_usuario_actual === 'SUPERVISOR') ? "JOIN parametros p ON b.parametro_id = p.id JOIN equipos eq ON p.equipo_id = eq.id JOIN areas ar ON eq.area_id = ar.id WHERE ar.zona_id != 3 AND" : "JOIN parametros p ON b.parametro_id = p.id WHERE";
$fecha_where = ($vista == 'diaria') ? "DATE(b.fecha_registro) = '$fecha_filtro'" : "DATE(b.fecha_registro) BETWEEN '$lunes' AND '$domingo'";

$q_kpi = mysqli_query($conn, "SELECT b.valor_capturado, p.rojo_bajo, p.amarillo_bajo, p.amarillo_alto, p.rojo_alto 
                              FROM bitacora_lecturas b 
                              $filtro_kpi_zona $fecha_where");
if ($q_kpi) {
    while ($k = mysqli_fetch_assoc($q_kpi)) {
        $val = $k['valor_capturado'];
        if ($val !== null && is_numeric($val)) {
            $total_lecturas++;
            $v = (float)$val;
            $rb = $k['rojo_bajo'] ?? -999999;
            $ra = $k['rojo_alto'] ?? 999999;
            $ab = $k['amarillo_bajo'] ?? -999999;
            $aa = $k['amarillo_alto'] ?? 999999;
            if ($v <= $rb || $v >= $ra) $total_rojos++;
            elseif (($v > $rb && $v <= $ab) || ($v >= $aa && $v < $ra)) $total_amarillos++;
            else $total_verdes++;
        }
    }
}

// Zonas y conteo de equipos 
$q_zonas = mysqli_query($conn, "SELECT * FROM zonas $filtro_zona_sql ORDER BY id ASC");
$lista_zonas = [];
if ($q_zonas) while ($z = mysqli_fetch_assoc($q_zonas)) $lista_zonas[] = $z;

$conteo_equipos = [];
$q_count = mysqli_query($conn, "SELECT a.zona_id, COUNT(e.id) as total FROM equipos e JOIN areas a ON e.area_id = a.id WHERE e.nombre NOT LIKE 'Producto Votator' $filtro_area_sql GROUP BY a.zona_id");
if ($q_count) while ($row = mysqli_fetch_assoc($q_count)) $conteo_equipos[$row['zona_id']] = $row['total'];

// =========================================================================
// OPTIMIZACIÓN EXTREMA: PRE-CARGA MASIVA DE LECTURAS (ELIMINA MILES DE QUERYS)
// =========================================================================
$cache_lecturas = [];
if ($vista == 'diaria') {
    $q_cache = "SELECT parametro_id, valor_capturado, observaciones, lote, HOUR(fecha_registro) as h_reg 
                FROM bitacora_lecturas 
                WHERE DATE(fecha_registro) = '$fecha_filtro' 
                ORDER BY id ASC";
    $res_cache = mysqli_query($conn, $q_cache);
    if ($res_cache) {
        while ($row = mysqli_fetch_assoc($res_cache)) {
            $pid = $row['parametro_id'];
            $h = (int)$row['h_reg'];
            $bloque = floor($h / 2) * 2; // Agrupa horas impares a su bloque par anterior
            $cache_lecturas[$pid][$bloque] = $row;
        }
    }
} else {
    // Para vista semanal: Obtenemos las lecturas de la semana (soporta promedios numéricos y valores cualitativos como 'Sí' o 'Realizada')
    $q_cache = "SELECT parametro_id, DATE(fecha_registro) as dia, valor_capturado, observaciones, lote 
                FROM bitacora_lecturas 
                WHERE DATE(fecha_registro) BETWEEN '$lunes' AND '$domingo' 
                ORDER BY id ASC";
    $res_cache = mysqli_query($conn, $q_cache);
    if ($res_cache) {
        $sum_vals = [];
        $count_vals = [];
        while ($row = mysqli_fetch_assoc($res_cache)) {
            $pid = $row['parametro_id'];
            $dia = $row['dia'];
            $val = $row['valor_capturado'];
            if (is_numeric($val)) {
                $sum_vals[$pid][$dia] = ($sum_vals[$pid][$dia] ?? 0) + (float)$val;
                $count_vals[$pid][$dia] = ($count_vals[$pid][$dia] ?? 0) + 1;
                $cache_lecturas[$pid][$dia] = round($sum_vals[$pid][$dia] / $count_vals[$pid][$dia], 2);
            } else {
                $cache_lecturas[$pid][$dia] = $val;
            }
            $cache_lecturas[$pid][$dia . '_obs'] = $row['observaciones'];
            $cache_lecturas[$pid][$dia . '_lote'] = $row['lote'];
        }
    }
}

include 'includes/header.php';
?>

<link rel="stylesheet" href="assets/css/reporte_general.css">

<div class="container-fluid">
    <div class="header-main">
        <h1><i class="bi bi-grid-3x3-gap-fill"></i> Reporte de Operación</h1>
        <div class="header-controls">
            <form method="GET" id="formVista" class="d-flex align-items-center gap-2 flex-wrap">
                <input type="hidden" name="vista" id="inputVistaHidden" value="<?= htmlspecialchars($vista) ?>">
                <input type="date" name="fecha" class="form-control form-control-sm fw-bold" value="<?= htmlspecialchars($fecha_filtro) ?>" onchange="document.getElementById('formVista').submit()">
                <div class="vista-toggle">
                    <button type="button" class="btn-vista <?= $vista=='diaria'?'active':'' ?>" onclick="document.getElementById('inputVistaHidden').value='diaria'; document.getElementById('formVista').submit();">Día</button>
                    <button type="button" class="btn-vista <?= $vista=='semanal'?'active':'' ?>" onclick="document.getElementById('inputVistaHidden').value='semanal'; document.getElementById('formVista').submit();">Semana</button>
                </div>
                <?php if ($vista == 'diaria' && $fecha_filtro == $fecha_hoy): ?>
                    <button type="button" class="btn btn-sm <?= $mostrar_todas ? 'btn-primary' : 'btn-outline-secondary' ?> fw-bold" onclick="const url = new URL(window.location.href); url.searchParams.set('todas_horas', '<?= $mostrar_todas ? '0' : '1' ?>'); window.location.href = url.href;" title="Alternar entre ver solo las horas transcurridas o todas las 24 hrs">
                        <i class="bi <?= $mostrar_todas ? 'bi-clock' : 'bi-eye' ?>"></i> <?= $mostrar_todas ? 'Ver transcurridas' : 'Ver 24 hrs' ?>
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-dark btn-sm fw-bold" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
            </form>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card" style="border-left-color: var(--primary-blue);">
            <div class="kpi-info">
                <span class="kpi-label">Total Lecturas</span>
                <span class="kpi-number"><?= $total_lecturas ?></span>
                <div class="progress-bar-custom"><div class="bar" style="width:100%; background:var(--primary-blue);"></div></div>
            </div>
            <i class="bi bi-card-checklist kpi-icon"></i>
        </div>
        <div class="kpi-card" style="border-left-color: var(--accent-green);">
            <div class="kpi-info">
                <span class="kpi-label">🟢 Óptimas</span>
                <span class="kpi-number" style="color:var(--accent-green);"><?= $total_verdes ?></span>
                <div class="progress-bar-custom"><div class="bar" style="width:<?= ($total_lecturas?($total_verdes/$total_lecturas*100):0) ?>%; background:var(--accent-green);"></div></div>
            </div>
            <i class="bi bi-check-circle-fill kpi-icon" style="color:var(--accent-green);"></i>
        </div>
        <div class="kpi-card" style="border-left-color: var(--accent-yellow);">
            <div class="kpi-info">
                <span class="kpi-label">🟡 Alertas</span>
                <span class="kpi-number" style="color:var(--accent-yellow);"><?= $total_amarillos ?></span>
                <div class="progress-bar-custom"><div class="bar" style="width:<?= ($total_lecturas?($total_amarillos/$total_lecturas*100):0) ?>%; background:var(--accent-yellow);"></div></div>
            </div>
            <i class="bi bi-exclamation-triangle-fill kpi-icon" style="color:var(--accent-yellow);"></i>
        </div>
        <div class="kpi-card" style="border-left-color: var(--accent-red);">
            <div class="kpi-info">
                <span class="kpi-label">🔴 Críticas</span>
                <span class="kpi-number" style="color:var(--accent-red);"><?= $total_rojos ?></span>
                <div class="progress-bar-custom"><div class="bar" style="width:<?= ($total_lecturas?($total_rojos/$total_lecturas*100):0) ?>%; background:var(--accent-red);"></div></div>
            </div>
            <i class="bi bi-x-circle-fill kpi-icon" style="color:var(--accent-red);"></i>
        </div>
    </div>

    <div class="filters-panel">
        <div class="filter-row" style="border-bottom: 1px solid #e9ecef; padding-bottom: 0.8rem; margin-bottom: 0.5rem;">
            <div class="search-wrapper flex-grow-1">
                <i class="bi bi-search text-muted"></i>
                <input type="text" id="buscadorVivo" placeholder="Buscar equipo o parámetro..." onkeyup="filtrarEnVivo()">
                <button class="btn-clear" type="button" onclick="limpiarBuscador()"><i class="bi bi-x-circle"></i> Limpiar</button>
            </div>
            <span class="fw-bold" style="font-size: 0.85rem; margin-left: 0.5rem;"><span id="contador-visibles">0</span> equipos visibles</span>
        </div>

        <div class="filter-row">
            <span class="filter-label"><i class="bi bi-geo-alt-fill text-danger"></i> Zona</span>
            <button class="btn-filter active" onclick="seleccionarZona(0, this)">
                <i class="bi bi-globe"></i> Todas
                <span class="badge-count"><?= array_sum($conteo_equipos) ?></span>
            </button>
            <?php foreach ($lista_zonas as $z): 
                $count = $conteo_equipos[$z['id']] ?? 0;
            ?>
                <button class="btn-filter" onclick="seleccionarZona(<?= $z['id'] ?>, this)">
                    <i class="bi bi-building"></i> <?= htmlspecialchars($z['nombre']) ?>
                    <span class="badge-count"><?= $count ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="filter-row">
            <span class="filter-label"><i class="bi bi-collection-fill text-success"></i> Grupo</span>
            <button class="btn-filter green active" onclick="seleccionarGrupo('todos', this)">
                <i class="bi bi-asterisk"></i> Todos
            </button>
            <button class="btn-filter green" onclick="seleccionarGrupo('votator', this)">
                <i class="bi bi-arrow-repeat"></i> Votators
            </button>
            <button class="btn-filter green" onclick="seleccionarGrupo('concentrador', this)">
                <i class="bi bi-layers-half"></i> Concentradores
            </button>
            <button class="btn-filter green" onclick="seleccionarGrupo('secador', this)">
                <i class="bi bi-wind"></i> Secadores
            </button>
            <button class="btn-filter green" onclick="seleccionarGrupo('chiller', this)">
                <i class="bi bi-snow"></i> Chillers
            </button>
            <button class="btn-filter green" onclick="seleccionarGrupo('caldera', this)">
                <i class="bi bi-fire"></i> Calderas
            </button>
        </div>

        <div class="filter-row">
            <span class="filter-label"><i class="bi bi-cpu-fill text-warning"></i> Equipo</span>
            <button class="btn-filter yellow active" onclick="seleccionarEquipo(0, this)">
                <i class="bi bi-any"></i> Cualquiera
            </button>
            <div id="contenedorBotonesEquipos" class="d-flex flex-wrap gap-1">
                <?php
                $q_eq_all = mysqli_query($conn, "SELECT e.id, e.nombre, a.zona_id FROM equipos e JOIN areas a ON e.area_id = a.id WHERE e.nombre NOT LIKE 'Producto Votator' $filtro_area_sql ORDER BY e.id ASC");
                if ($q_eq_all) {
                    while ($eq = mysqli_fetch_assoc($q_eq_all)) {
                        $nombre_btn = str_replace(['Producto Votator', 'Producto '], 'Votator ', $eq['nombre']);
                        $nombre_norm = mb_strtolower($nombre_btn, 'UTF-8');
                        if (strpos($nombre_norm, 'votator') !== false || 
                            strpos($nombre_norm, 'secador') !== false || 
                            strpos($nombre_norm, 'concentrador') !== false) {
                            continue;
                        }
                        echo '<button class="btn-filter yellow btn-equipo-item" ' .
                             'data-equipo-id="' . $eq['id'] . '" ' .
                             'data-zona-id="' . $eq['zona_id'] . '" ' .
                             'data-equipo-nombre="' . htmlspecialchars($nombre_norm) . '" ' .
                             'onclick="seleccionarEquipo(' . $eq['id'] . ', this)">' .
                             htmlspecialchars($nombre_btn) . '</button>';
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <div class="accordion accordion-zona" id="accordionZonas">
        <?php
        $sql_zonas = "SELECT * FROM zonas $filtro_zona_sql ORDER BY id ASC";
        $res_zonas = mysqli_query($conn, $sql_zonas);
        if ($res_zonas && mysqli_num_rows($res_zonas) > 0) {
            $first = true;
            while ($z = mysqli_fetch_assoc($res_zonas)) {
                $zona_id = $z['id'];
                $zona_nombre = $z['nombre'];
                ?>
                <div class="accordion-item bloque-zona" data-zona-id="<?= $zona_id ?>">
                    <h2 class="accordion-header" id="headingZona<?= $zona_id ?>">
                        <button class="accordion-button <?= $first ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseZona<?= $zona_id ?>" aria-expanded="<?= $first ? 'true' : 'false' ?>" aria-controls="collapseZona<?= $zona_id ?>">
                            <i class="bi bi-building me-2 text-primary"></i>
                            ZONA: <?= htmlspecialchars($zona_nombre) ?>
                            <span class="zona-badge"><?= $conteo_equipos[$zona_id] ?? 0 ?> equipos</span>
                            <?php if ($vista == 'diaria'): ?>
                                <span class="zona-badge"><i class="bi bi-calendar"></i> <?= $fecha_filtro ?></span>
                            <?php else: ?>
                                <span class="zona-badge"><i class="bi bi-calendar-week"></i> <?= $lunes ?> → <?= $domingo ?></span>
                            <?php endif; ?>
                        </button>
                    </h2>
                    <div id="collapseZona<?= $zona_id ?>" class="accordion-collapse collapse <?= $first ? 'show' : '' ?>" data-bs-parent="#accordionZonas">
                        <div class="accordion-body">
                            <?php
                            $sql_areas = "SELECT * FROM areas WHERE zona_id = $zona_id ORDER BY id ASC";
                            $res_areas = mysqli_query($conn, $sql_areas);
                            if ($res_areas && mysqli_num_rows($res_areas) > 0) {
                                while ($a = mysqli_fetch_assoc($res_areas)) {
                                    $area_id = $a['id'];
                                    $area_nombre = $a['nombre'];
                                    ?>
                                    <div class="bloque-area" data-area-id="<?= $area_id ?>">
                                        <div class="d-flex align-items-center my-3">
                                            <h6 class="fw-bold text-uppercase text-dark m-0" style="letter-spacing: 0.03em; font-size: 0.9rem;">
                                                <i class="bi bi-diagram-3 me-2 text-primary"></i> Área: <?= htmlspecialchars($area_nombre) ?>
                                            </h6>
                                            <hr class="flex-grow-1 ms-3 my-0 border-dark opacity-10">
                                        </div>

                                        <div class="equipos-grid">
                                            <?php
                                            $sql_equipos = "SELECT * FROM equipos WHERE area_id = $area_id AND nombre NOT LIKE 'Producto Votator' ORDER BY id ASC";
                                            $res_equipos = mysqli_query($conn, $sql_equipos);
                                            if ($res_equipos && mysqli_num_rows($res_equipos) > 0) {
                                                while ($eq = mysqli_fetch_assoc($res_equipos)) {
                                                    $eq_id = $eq['id'];
                                                    $eq_nombre = str_replace(['Producto Votator', 'Producto '], 'Votator ', $eq['nombre']);
                                                    $eq_nombre_normalizado = mb_strtolower($eq_nombre, 'UTF-8');
                                                    ?>
                                                    <div class="tarjeta-equipo" 
                                                         data-equipo-id="<?= $eq_id ?>" 
                                                         data-zona-id="<?= $zona_id ?>" 
                                                         data-equipo-nombre="<?= htmlspecialchars($eq_nombre_normalizado) ?>">
                                                        <div class="card-equipo" id="equipo-<?= $eq_id ?>">
                                                            <div class="card-header-equipo">
                                                                <h6><i class="bi bi-cpu"></i> <?= htmlspecialchars($eq_nombre) ?></h6>
                                                                <div class="d-flex align-items-center gap-2">
                                                                    <?php
                                                                    // Verificar si el equipo cuenta con parámetros de rutina semanal
                                                                    $tiene_semanal = false;
                                                                    $q_chk_sem = mysqli_query($conn, "SELECT 1 FROM parametros WHERE equipo_id = $eq_id AND frecuencia = 'SEMANAL' LIMIT 1");
                                                                    if ($q_chk_sem && mysqli_num_rows($q_chk_sem) > 0) $tiene_semanal = true;
                                                                    ?>
                                                                    <?php if ($vista == 'diaria' && $tiene_semanal): ?>
                                                                        <a href="reporte_general.php?vista=semanal&fecha=<?= urlencode($fecha_filtro) ?>#equipo-<?= $eq_id ?>" class="btn btn-outline-info btn-sm fw-bold" style="font-size: 0.72rem; padding: 0.15rem 0.6rem; border-radius: 20px;" title="Ver rutina semanal de este equipo">
                                                                            <i class="bi bi-calendar-week"></i> Rutina Semanal
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    <a href="historial.php?id=<?= $eq_id ?>" class="btn-historial"><i class="bi bi-clock-history"></i> Historial</a>
                                                                </div>
                                                            </div>
                                                            <div class="table-wrap">
                                                                <table class="table-matriz">
                                                                    <thead>
                                                                        <tr>
                                                                            <th style="text-align:left; padding-left:1rem; min-width:180px;">Parámetro</th>
                                                                            <?php
                                                                            if ($vista == 'diaria') {
                                                                                foreach ($horas_columnas as $hora) {
                                                                                    $h_num = (int)substr($hora, 0, 2);
                                                                                    $es_hora_actual = ($fecha_filtro == $fecha_hoy && $hora_actual_num >= $h_num && $hora_actual_num < ($h_num + 2));
                                                                                    $th_class = $es_hora_actual ? 'th-hora-actual' : '';
                                                                                    echo "<th class='$th_class'" . ($es_hora_actual ? " title='Bloque horario actual en curso'" : "") . ">$hora</th>";
                                                                                }
                                                                            } else {
                                                                                foreach ($dias_semana as $dia) {
                                                                                    echo "<th>$dia</th>";
                                                                                }
                                                                            }
                                                                            ?>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        <?php
                                                                        // En la vista diaria NO mostramos parámetros semanales (evita falsos 'Sin registro' cada 2 horas)
                                                                        $filtro_param_frec = ($vista == 'diaria') ? "AND (frecuencia != 'SEMANAL' OR frecuencia IS NULL)" : "";
                                                                        $q_params = "SELECT * FROM parametros WHERE equipo_id = $eq_id $filtro_param_frec ORDER BY id ASC";
                                                                        $res_params = mysqli_query($conn, $q_params);
                                                                        if ($res_params && mysqli_num_rows($res_params) > 0) {
                                                                            while ($p = mysqli_fetch_assoc($res_params)) {
                                                                                $p_nombre_norm = mb_strtolower($p['nombre_parametro'], 'UTF-8');
                                                                                ?>
                                                                                <tr class="fila-parametro" data-param-nombre="<?= htmlspecialchars($p_nombre_norm) ?>">
                                                                                    <td class="parametro-nombre">
                                                                                        <?= htmlspecialchars($p['nombre_parametro']) ?>
                                                                                        <?php if ($vista == 'semanal' && ($p['frecuencia'] ?? '') == 'SEMANAL'): ?>
                                                                                            <span class="badge bg-secondary ms-1" style="font-size:0.6rem; vertical-align:middle;">Semanal</span>
                                                                                        <?php endif; ?>
                                                                                    </td>
                                                                                    <?php
                                                                                    if ($vista == 'diaria') {
                                                                                        foreach ($horas_columnas as $hora) {
                                                                                            $hora_num = (int)substr($hora, 0, 2);
                                                                                            
                                                                                            // Determinar si la hora ya concluyó
                                                                                            $hora_ya_paso = false;
                                                                                            if ($fecha_filtro < $fecha_hoy) {
                                                                                                $hora_ya_paso = true; // Día anterior completo
                                                                                            } elseif ($fecha_filtro == $fecha_hoy) {
                                                                                                // El bloque de 2 horas (ej. 08:00 comprende 08:00 a 09:59). A las 10:00 ya concluyó.
                                                                                                $hora_ya_paso = ($hora_actual_num >= ($hora_num + 2));
                                                                                            }

                                                                                            // OPTIMIZACIÓN: Tomamos la información de la memoria cache
                                                                                            $reg = $cache_lecturas[$p['id']][$hora_num] ?? null;
                                                                                            $val = $reg['valor_capturado'] ?? null;
                                                                                            $obs = $reg['observaciones'] ?? '';
                                                                                            $lote_val = $reg['lote'] ?? '';

                                                                                            $badge_class = getBadgeClass($val, $p);
                                                                                            ?>
                                                                                            <td>
                                                                                                <?php if ($val !== null): ?>
                                                                                                    <span class="badge-valor <?= $badge_class ?>"><?= htmlspecialchars($val) ?></span>
                                                                                                    <?php if (!empty($lote_val)): ?>
                                                                                                        <br><div class="lote-badge"><i class="bi bi-box-seam"></i> <?= htmlspecialchars($lote_val) ?></div>
                                                                                                    <?php endif; ?>
                                                                                                    <?php if (!empty($obs)): ?>
                                                                                                        <div class="obs-box"><?= htmlspecialchars($obs) ?></div>
                                                                                                    <?php endif; ?>
                                                                                                <?php else: ?>
                                                                                                    <?php if ($hora_ya_paso): ?>
                                                                                                        <span class="badge-no-registro" title="Hora concluida: no se hizo ningún registro">Sin registro</span>
                                                                                                    <?php else: ?>
                                                                                                        <span class="text-muted" title="Hora pendiente">—</span>
                                                                                                    <?php endif; ?>
                                                                                                <?php endif; ?>
                                                                                            </td>
                                                                                        <?php }
                                                                                    } else {
                                                                                        // Vista semanal
                                                                                        foreach ($dias_fechas as $dia) {
                                                                                            $dia_ya_paso = ($dia < $fecha_hoy);

                                                                                            // OPTIMIZACIÓN: Tomamos la información de la memoria cache
                                                                                            $val = $cache_lecturas[$p['id']][$dia] ?? null;
                                                                                            $obs = $cache_lecturas[$p['id']][$dia . '_obs'] ?? '';
                                                                                            $lote_val = $cache_lecturas[$p['id']][$dia . '_lote'] ?? '';

                                                                                            $badge_class = getBadgeClass($val, $p);
                                                                                            ?>
                                                                                            <td>
                                                                                                <?php if ($val !== null): ?>
                                                                                                    <span class="badge-valor <?= $badge_class ?>"><?= is_numeric($val) ? number_format((float)$val, 1) : htmlspecialchars($val) ?></span>
                                                                                                    <?php if (!empty($lote_val)): ?>
                                                                                                        <br><div class="lote-badge"><i class="bi bi-box-seam"></i> <?= htmlspecialchars($lote_val) ?></div>
                                                                                                    <?php endif; ?>
                                                                                                    <?php if (!empty($obs)): ?>
                                                                                                        <div class="obs-box"><?= htmlspecialchars($obs) ?></div>
                                                                                                    <?php endif; ?>
                                                                                                <?php else: ?>
                                                                                                    <?php if ($dia_ya_paso): ?>
                                                                                                        <span class="badge-no-registro" title="Día concluido: no se hizo ningún registro">Sin registro</span>
                                                                                                    <?php else: ?>
                                                                                                        <span class="text-muted" title="Día pendiente">—</span>
                                                                                                    <?php endif; ?>
                                                                                                <?php endif; ?>
                                                                                            </td>
                                                                                        <?php }
                                                                                    }
                                                                                    ?>
                                                                                </tr>
                                                                                <?php
                                                                            }
                                                                        } else {
                                                                            echo '<tr><td colspan="' . ($vista=='diaria'?count($horas_columnas)+1:8) . '" class="text-muted text-center py-2">Sin parámetros configurados.</td></tr>';
                                                                        }
                                                                        ?>
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php
                                                }
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                    </div>
                </div>
                <?php
                $first = false;
            }
        }
        ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.card-equipo').forEach(card => {
        let rojos = 0, amarillos = 0, verdes = 0;
        card.querySelectorAll('.badge-valor').forEach(badge => {
            if (badge.classList.contains('bg-red')) rojos++;
            else if (badge.classList.contains('bg-yellow')) amarillos++;
            else if (badge.classList.contains('bg-green')) verdes++;
        });
        let color = '#198754';
        if (rojos > verdes && rojos > amarillos) color = '#b22222';
        else if (amarillos > verdes && amarillos > rojos) color = '#b8860b';
        card.style.borderLeftColor = color;
    });

    actualizarContador();
});

let zonaSeleccionadaId = 0;
let grupoSeleccionado = 'todos';
let equipoSeleccionadoId = 0;

function seleccionarZona(zonaId, btn) {
    zonaSeleccionadaId = zonaId;
    grupoSeleccionado = 'todos';
    equipoSeleccionadoId = 0;
    actualizarBotones(btn, '.btn-filter[onclick*="seleccionarZona"]');
    document.querySelectorAll('.btn-filter.green').forEach(b => b.classList.remove('active'));
    document.querySelector('.btn-filter.green[onclick*="todos"]')?.classList.add('active');
    document.querySelectorAll('.btn-filter.yellow').forEach(b => b.classList.remove('active'));
    document.querySelector('.btn-filter.yellow[onclick*="seleccionarEquipo(0"]')?.classList.add('active');
    actualizarBotonesEquiposVisibles();
    aplicarFiltrosMatriz();
    actualizarContador();
}

function seleccionarGrupo(grupo, btn) {
    grupoSeleccionado = grupo;
    equipoSeleccionadoId = 0;
    actualizarBotones(btn, '.btn-filter.green');
    document.querySelectorAll('.btn-filter.yellow').forEach(b => b.classList.remove('active'));
    document.querySelector('.btn-filter.yellow[onclick*="seleccionarEquipo(0"]')?.classList.add('active');
    actualizarBotonesEquiposVisibles();
    aplicarFiltrosMatriz();
    actualizarContador();
}

function seleccionarEquipo(equipoId, btn) {
    equipoSeleccionadoId = equipoId;
    actualizarBotones(btn, '.btn-filter.yellow');
    aplicarFiltrosMatriz();
    actualizarContador();
}

function actualizarBotones(btn, selector) {
    document.querySelectorAll(selector).forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
}

function actualizarBotonesEquiposVisibles() {
    document.querySelectorAll('.btn-equipo-item').forEach(btn => {
        const zId = parseInt(btn.dataset.zonaId);
        const nombre = btn.dataset.equipoNombre || '';
        const zonaOk = (zonaSeleccionadaId === 0 || zId === zonaSeleccionadaId);
        const grupoOk = (grupoSeleccionado === 'todos' || nombre.includes(grupoSeleccionado));
        btn.style.display = (zonaOk && grupoOk) ? 'inline-block' : 'none';
    });
}

function aplicarFiltrosMatriz() {
    document.querySelectorAll('.tarjeta-equipo').forEach(tarjeta => {
        const eqId = parseInt(tarjeta.dataset.equipoId);
        const zId = parseInt(tarjeta.dataset.zonaId);
        const nombre = tarjeta.dataset.equipoNombre || '';
        const zonaOk = (zonaSeleccionadaId === 0 || zId === zonaSeleccionadaId);
        const grupoOk = (grupoSeleccionado === 'todos' || nombre.includes(grupoSeleccionado));
        const equipoOk = (equipoSeleccionadoId === 0 || eqId === equipoSeleccionadoId);
        tarjeta.style.display = (zonaOk && grupoOk && equipoOk) ? 'block' : 'none';
    });
    actualizarVisibilidadSecciones();
}

function actualizarVisibilidadSecciones() {
    document.querySelectorAll('.bloque-area').forEach(area => {
        const visibles = Array.from(area.querySelectorAll('.tarjeta-equipo')).filter(e => e.style.display !== 'none').length;
        area.style.display = (visibles > 0) ? 'block' : 'none';
    });
    document.querySelectorAll('.bloque-zona').forEach(zona => {
        const visibles = Array.from(zona.querySelectorAll('.bloque-area')).filter(a => a.style.display !== 'none').length;
        zona.style.display = (visibles > 0) ? 'block' : 'none';
    });
}

function actualizarContador() {
    const visibles = document.querySelectorAll('.tarjeta-equipo[style*="display: block"]').length;
    document.getElementById('contador-visibles').textContent = visibles;
}

function filtrarEnVivo() {
    const texto = document.getElementById('buscadorVivo').value.toLowerCase().trim();
    document.querySelectorAll('.tarjeta-equipo').forEach(tarjeta => {
        const nombre = tarjeta.dataset.equipoNombre || '';
        let coincide = (texto === '' || nombre.includes(texto));
        tarjeta.querySelectorAll('.fila-parametro').forEach(p => {
            const pNombre = p.dataset.paramNombre || '';
            const td = p.querySelector('.parametro-nombre');
            if (td) {
                let contenido = td.textContent;
                if (texto !== '' && pNombre.includes(texto)) {
                    coincide = true;
                    td.innerHTML = contenido.replace(new RegExp(texto.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi'), match => `<mark style="background:#ffeb3b; padding:0 2px;">${match}</mark>`);
                      td.innerHTML = contenido;
                }
            }
        });
        tarjeta.style.display = coincide ? 'block' : 'none';
    });
    actualizarVisibilidadSecciones();
    actualizarContador();
}

function limpiarBuscador() {
    document.getElementById('buscadorVivo').value = '';
    document.querySelectorAll('.fila-parametro .parametro-nombre').forEach(td => td.innerHTML = td.textContent);
    aplicarFiltrosMatriz();
    actualizarContador();
    seleccionarZona(0, document.querySelector('.btn-filter[onclick*="seleccionarZona(0"]'));
}

window.addEventListener('load', function() {
    document.querySelectorAll('.tarjeta-equipo').forEach(el => el.style.display = 'block');
    actualizarVisibilidadSecciones();
    actualizarContador();
});
</script>

<?php include 'includes/footer.php'; ?>