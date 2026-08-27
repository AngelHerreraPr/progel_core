<?php
// Progel_core/reporte_general.php
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
    if (in_array($val_low, ['f.o.', 'lavado', 'mtto', 'no', 'sí presenta', 'húmeda', 'sobreseca', 'quemada'])) return 'bg-red';
    return 'bg-yellow';
}

// ===== PARÁMETROS DE FILTRO =====
$fecha_filtro = isset($_GET['fecha']) ? $_GET['fecha'] : date('Y-m-d');
$vista = isset($_GET['vista']) ? $_GET['vista'] : 'diaria';

if ($vista == 'semanal') {
    $lunes = date('Y-m-d', strtotime('monday this week', strtotime($fecha_filtro)));
    $domingo = date('Y-m-d', strtotime('sunday this week', strtotime($fecha_filtro)));
    $dias_semana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
    $dias_fechas = [];
    for ($i = 0; $i < 7; $i++) {
        $dias_fechas[] = date('Y-m-d', strtotime("$lunes + $i days"));
    }
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

include 'includes/header.php';
?>

<style>
    main.container { max-width: 100% !important; width: 100% !important; padding-left: 1.5rem !important; padding-right: 1.5rem !important; margin-top: 1rem !important; }
    :root { --primary-dark: #1a2332; --primary-blue: #2c3e8f; --accent-green: #2e8b57; --accent-yellow: #e49a32; --accent-red: #c94436; --card-shadow: 0 4px 15px rgba(0,0,0,0.05); }
    body { background: #eef2f7; font-family: 'Inter', system-ui, sans-serif; color: #1a2332; }
    
    .header-main { background: white; border-radius: 15px; padding: 1.2rem 2rem; margin-bottom: 1.5rem; box-shadow: var(--card-shadow); display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; border: 1px solid rgba(0,0,0,0.03); }
    .header-main h1 { font-weight: 800; font-size: 1.5rem; color: var(--primary-dark); margin: 0; }
    .header-main h1 i { color: var(--primary-blue); margin-right: 0.5rem; }
    
    .vista-toggle { background: #e9ecef; border-radius: 40px; padding: 0.2rem; display: inline-flex; }
    .vista-toggle .btn-vista { border: none; background: transparent; padding: 0.4rem 1.2rem; border-radius: 30px; font-weight: 700; font-size: 0.8rem; color: #495057; transition: all 0.2s; }
    .vista-toggle .btn-vista.active { background: white; color: var(--primary-blue); box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
    .kpi-card { background: white; border-radius: 12px; padding: 1.2rem 1.5rem; box-shadow: var(--card-shadow); border-left: 6px solid var(--primary-blue); display: flex; justify-content: space-between; align-items: center; }
    .kpi-card .kpi-label { font-size: 0.7rem; text-transform: uppercase; color: #6c757d; font-weight: 800; }
    .kpi-card .kpi-number { font-size: 2rem; font-weight: 900; line-height: 1.2; }
    .kpi-card .kpi-icon { font-size: 2.2rem; opacity: 0.2; }
    
    .filters-panel { background: white; border-radius: 15px; padding: 1.2rem; margin-bottom: 1.5rem; box-shadow: var(--card-shadow); border: 1px solid rgba(0,0,0,0.02); }
    .filter-row { display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem 1rem; padding: 0.4rem 0; border-bottom: 1px solid #f0f2f5; }
    .filter-row:last-child { border-bottom: none; }
    .filter-label { font-weight: 800; font-size: 0.75rem; text-transform: uppercase; color: #6c757d; min-width: 90px; }
    
    .btn-filter { border-radius: 30px; padding: 0.25rem 1rem; font-weight: 700; font-size: 0.75rem; border: 1px solid #dde1e5; background: white; color: #1a2332; cursor: pointer; transition: all 0.15s;}
    .btn-filter .badge-count { background: rgba(0,0,0,0.06); border-radius: 20px; padding: 0.05rem 0.6rem; font-size: 0.65rem; font-weight: 800; margin-left: 0.3rem; }
    .btn-filter.active { background: var(--primary-blue); color: white; border-color: var(--primary-blue); }
    .btn-filter.active .badge-count { background: rgba(255,255,255,0.2); color: white; }
    .btn-filter.green.active { background: var(--accent-green); border-color: var(--accent-green); }
    .btn-filter.yellow.active { background: var(--accent-yellow); border-color: var(--accent-yellow); color: white; }
    
    .search-wrapper { background: #f1f4f8; border-radius: 40px; padding: 0.1rem 0.1rem 0.1rem 1.2rem; display: flex; align-items: center; flex: 1; min-width: 200px; }
    .search-wrapper input { border: none; background: transparent; outline: none; padding: 0.5rem 0.2rem; font-weight: 600; width: 100%; font-size: 0.85rem; }
    .search-wrapper .btn-clear { background: transparent; border: none; color: #6c757d; font-weight: 700; font-size: 0.75rem; padding-right: 1rem; }
    
    .equipos-grid { display: grid; grid-template-columns: 1fr; gap: 1.5rem; align-items: start; }
    
    .card-equipo { background: white; border-radius: 15px; overflow: hidden; box-shadow: var(--card-shadow); border-left: 5px solid #198754; display: flex; flex-direction: column; width: 100%; }
    .card-equipo .card-header-equipo { padding: 0.9rem 1.5rem; background: #1a2332; border-bottom: 1px solid #edf0f5; display: flex; justify-content: space-between; align-items: center; }
    .card-equipo .card-header-equipo h6 { margin: 0; font-weight: 800; font-size: 1.15rem; color: #ffffff; }
    .card-equipo .card-header-equipo .btn-historial { background: rgba(255,255,255,0.1); font-size: 0.8rem; font-weight: 700; padding: 0.35rem 1rem; border-radius: 30px; border: 1px solid rgba(255,255,255,0.2); color: #ffffff; text-decoration: none; transition: 0.2s; }
    .card-equipo .card-header-equipo .btn-historial:hover { background: #ffffff; color: #1a2332; }

    .frecuencia-header { padding: 0.7rem 1.2rem; font-weight: 900; font-size: 0.85rem; letter-spacing: 1px; text-transform: uppercase; border-top: 1px solid rgba(0,0,0,0.1); display: flex; align-items: center; gap: 0.5rem; }
    
    .table-wrap { overflow-x: auto; padding: 0; width: 100%; }
    .table-matriz { width: 100%; border-collapse: collapse; font-size: 0.9rem; min-width: 500px; }
    .table-matriz thead th { background: #f8fafc; color: #475569; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; padding: 0.8rem 0.6rem; text-align: center; border-bottom: 2px solid #e2e8f0; }
    .table-matriz tbody td { padding: 0.8rem 0.6rem; vertical-align: middle; border-bottom: 1px solid #f0f2f5; text-align: center; }
    
    .table-matriz .parametro-nombre { font-weight: 800; color: #1e293b; text-align: left; padding-left: 1.2rem !important; background: #ffffff; border-right: 1px solid #e2e8f0; white-space: normal; line-height: 1.4; font-size: 0.95rem; width: 30%; }
    
    /* === LECTURAS MÁS GRANDES Y LLAMATIVAS === */
    .badge-valor { display: inline-block; padding: 0.5rem 1.1rem; border-radius: 10px; font-weight: 900; font-size: 1.25rem; min-width: 75px; color: white; border: 2px solid transparent; text-shadow: 1px 1px 2px rgba(0,0,0,0.2); box-shadow: 0 4px 8px rgba(0,0,0,0.12); }
    .badge-valor.bg-green { background-color: #2e8b57 !important; border-color: #257447 !important; color: #ffffff !important; }
    .badge-valor.bg-yellow { background-color: #e49a32 !important; border-color: #c47b1c !important; color: #ffffff !important; }
    .badge-valor.bg-red { background-color: #c94436 !important; border-color: #a9362c !important; color: #ffffff !important; }
    .badge-valor.bg-gray { background-color: #6c757d !important; border-color: #495057 !important; color: #ffffff !important; }
    
    .bg-purple { background-color: #8b5cf6 !important; color: white !important; } 

    /* === OBSERVACIONES Y NÓMINA EN TABLA === */
    .obs-box { font-size: 0.85rem; line-height: 1.4; padding: 6px 10px; background-color: #f8f9fa; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; white-space: normal; word-wrap: break-word; font-style: italic; text-align: left; }
    .nomina-pill { font-size: 0.8rem; font-weight: 800; background: #f1f5f9; color: #334155; padding: 0.3rem 0.6rem; border-radius: 6px; border: 1px solid #cbd5e1; display: inline-block; }

    /* Contenedor flexible de Dos Columnas para Diario y Semanal (Izquierda y Derecha) */
    .rutinas-side-by-side { display: grid; grid-template-columns: 1fr 1fr; gap: 0; width: 100%; border-top: 1px solid #cbd5e1; }
    .rutina-columna { width: 100%; overflow-x: auto; background: #ffffff; }
    .rutina-columna:first-child { border-right: 2px solid #cbd5e1; }

    .accordion-zona .accordion-item { border: none; border-radius: 15px !important; margin-bottom: 1.2rem; background: transparent; }
    .accordion-zona .accordion-header { background: white; border-radius: 15px !important; box-shadow: var(--card-shadow); margin-bottom: 0.5rem; }
    .accordion-zona .accordion-button { background: white; color: var(--primary-dark); font-weight: 800; padding: 1rem 1.8rem; border: none; box-shadow: none; border-radius: 15px !important; font-size: 1.1rem; }
    .accordion-zona .accordion-button .zona-badge { background: #e9ecef; color: #1a2332; border-radius: 30px; padding: 0.2rem 0.8rem; font-size: 0.8rem; font-weight: 700; margin-left: 0.8rem; }
    .accordion-zona .accordion-body { padding: 0.5rem 0; }
    
    @media (max-width: 992px) { 
        .rutinas-side-by-side { grid-template-columns: 1fr; } 
        .rutina-columna:first-child { border-right: none; border-bottom: 2px solid #cbd5e1; }
    }
    @media (max-width: 768px) { .header-main { flex-direction: column; align-items: stretch; gap: 1rem; padding: 1rem; } }
</style>

<div class="container-fluid">
    <div class="header-main">
        <h1><i class="bi bi-grid-3x3-gap-fill"></i> Reporte de Operación</h1>
        <div class="header-controls">
            <form method="GET" class="d-flex align-items-center gap-2 flex-wrap">
                <input type="date" name="fecha" class="form-control form-control-sm fw-bold" value="<?= htmlspecialchars($fecha_filtro) ?>" onchange="this.form.submit()">
                <div class="vista-toggle">
                    <button type="submit" name="vista" value="diaria" class="btn-vista <?= $vista=='diaria'?'active':'' ?>">Día</button>
                    <button type="submit" name="vista" value="semanal" class="btn-vista <?= $vista=='semanal'?'active':'' ?>">Semana</button>
                </div>
                <button type="button" class="btn btn-outline-dark btn-sm fw-bold" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
            </form>
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
                        if (strpos($nombre_norm, 'votator') !== false || strpos($nombre_norm, 'secador') !== false || strpos($nombre_norm, 'concentrador') !== false) {
                            continue;
                        }
                        echo '<button class="btn-filter yellow btn-equipo-item" data-equipo-id="' . $eq['id'] . '" data-zona-id="' . $eq['zona_id'] . '" data-equipo-nombre="' . htmlspecialchars($nombre_norm) . '" onclick="seleccionarEquipo(' . $eq['id'] . ', this)">' . htmlspecialchars($nombre_btn) . '</button>';
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <!-- ACORDEÓN DE ZONAS -->
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
                        <button class="accordion-button <?= $first ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseZona<?= $zona_id ?>">
                            <i class="bi bi-building me-2 text-primary"></i> ZONA: <?= htmlspecialchars($zona_nombre) ?>
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
                                                    <div class="tarjeta-equipo" data-equipo-id="<?= $eq_id ?>" data-zona-id="<?= $zona_id ?>" data-equipo-nombre="<?= htmlspecialchars($eq_nombre_normalizado) ?>">
                                                        <div class="card-equipo" id="equipo-<?= $eq_id ?>">
                                                            <div class="card-header-equipo">
                                                                <h6><i class="bi bi-cpu text-info"></i> <?= htmlspecialchars($eq_nombre) ?></h6>
                                                                <a href="historial.php?id=<?= $eq_id ?>" class="btn-historial"><i class="bi bi-clock-history"></i> Historial</a>
                                                            </div>
                                                            
                                                            <?php
                                                            // ====== SECCIÓN 1: PARÁMETROS POR HORA (CUADRÍCULA) ======
                                                            $q_frec_h = mysqli_query($conn, "SELECT DISTINCT frecuencia FROM parametros WHERE equipo_id = $eq_id AND frecuencia IN ('CADA 2 HORAS', 'CADA 3 HORAS') LIMIT 1");
                                                            $frec_h_row = mysqli_fetch_assoc($q_frec_h);
                                                            
                                                            if ($frec_h_row) {
                                                                $frec_horas = $frec_h_row['frecuencia'];
                                                                $salto_horas = ($frec_horas == 'CADA 3 HORAS') ? 3 : 2;
                                                                $columnas_h = ($salto_horas == 3) ? ["00:00", "03:00", "06:00", "09:00", "12:00", "15:00", "18:00", "21:00"] : ["00:00", "02:00", "04:00", "06:00", "08:00", "10:00", "12:00", "14:00", "16:00", "18:00", "20:00", "22:00"];
                                                                
                                                                echo '<div class="frecuencia-header bg-light" style="color: #475569;"><i class="bi bi-activity me-1"></i> LECTURAS (' . $salto_horas . ' HRS)</div>';
                                                                echo '<div class="table-wrap"><table class="table-matriz table-hover">';
                                                                echo '<thead><tr><th style="text-align:left; padding-left:1.5rem; min-width:220px;">Parámetro</th>';
                                                                if ($vista == 'diaria') {
                                                                    foreach ($columnas_h as $h) echo "<th>$h</th>";
                                                                } else {
                                                                    foreach ($dias_semana as $d) echo "<th>$d</th>";
                                                                }
                                                                echo '</tr></thead><tbody>';

                                                                $q_params_h = mysqli_query($conn, "SELECT * FROM parametros WHERE equipo_id = $eq_id AND frecuencia = '$frec_horas' ORDER BY id ASC");
                                                                while ($p = mysqli_fetch_assoc($q_params_h)) {
                                                                    $p_nombre_norm = mb_strtolower($p['nombre_parametro'], 'UTF-8');
                                                                    echo '<tr class="fila-parametro" data-param-nombre="'.htmlspecialchars($p_nombre_norm).'"><td class="parametro-nombre">'.htmlspecialchars($p['nombre_parametro']).'</td>';
                                                                    
                                                                    if ($vista == 'diaria') {
                                                                        foreach ($columnas_h as $hora) {
                                                                            $hora_num = (int)substr($hora, 0, 2);
                                                                            $hora_fin = $hora_num + $salto_horas;
                                                                            
                                                                            $sql_reg = "SELECT valor_capturado, observaciones FROM bitacora_lecturas 
                                                                                        WHERE parametro_id = {$p['id']} AND DATE(fecha_registro) = '$fecha_filtro' 
                                                                                        AND HOUR(fecha_registro) >= $hora_num AND HOUR(fecha_registro) < $hora_fin 
                                                                                        ORDER BY id DESC LIMIT 1";
                                                                            $res_reg = mysqli_query($conn, $sql_reg);
                                                                            $reg = mysqli_fetch_assoc($res_reg);
                                                                            
                                                                            $val = $reg['valor_capturado'] ?? null;
                                                                            $obs = $reg['observaciones'] ?? '';
                                                                            $badge_class = getBadgeClass($val, $p);
                                                                            
                                                                            echo '<td>';
                                                                            if ($val !== null) {
                                                                                echo '<span class="badge-valor '.$badge_class.'">'.htmlspecialchars($val).'</span>';
                                                                                if (!empty($obs)) echo '<div class="obs-box" title="'.htmlspecialchars($obs).'"><i class="bi bi-chat-text"></i> Nota</div>';
                                                                            } else {
                                                                                echo '<span class="text-muted" style="opacity:0.3;">—</span>';
                                                                            }
                                                                            echo '</td>';
                                                                        }
                                                                    } else {
                                                                        foreach ($dias_fechas as $dia) {
                                                                            $sql_reg = "SELECT AVG(valor_capturado) as promedio FROM bitacora_lecturas WHERE parametro_id = {$p['id']} AND DATE(fecha_registro) = '$dia' GROUP BY DATE(fecha_registro) ORDER BY fecha_registro DESC LIMIT 1";
                                                                            $res_reg = mysqli_query($conn, $sql_reg);
                                                                            $reg = mysqli_fetch_assoc($res_reg);
                                                                            $val = $reg['promedio'] ?? null;
                                                                            echo '<td>';
                                                                            if ($val !== null) echo '<span class="badge-valor bg-gray">'.number_format($val, 1).'</span>';
                                                                            else echo '<span class="text-muted" style="opacity:0.3;">—</span>';
                                                                            echo '</td>';
                                                                        }
                                                                    }
                                                                    echo '</tr>';
                                                                }
                                                                echo '</tbody></table></div>';
                                                            }

                                                            // ====== SECCIÓN 2 & 3: RUTINAS DIARIAS (IZQ) Y SEMANALES (DER) LADO A LADO BIEN HECHAS ======
                                                            $q_diario = mysqli_query($conn, "SELECT * FROM parametros WHERE equipo_id = $eq_id AND frecuencia = 'DIARIO' ORDER BY id ASC");
                                                            $has_diario = mysqli_num_rows($q_diario) > 0;

                                                            $q_semanal = mysqli_query($conn, "SELECT * FROM parametros WHERE equipo_id = $eq_id AND frecuencia IN ('SEMANAL', 'MENSUAL') ORDER BY FIELD(frecuencia, 'SEMANAL', 'MENSUAL'), id ASC");
                                                            $has_semanal = mysqli_num_rows($q_semanal) > 0;

                                                            if ($has_diario || $has_semanal) {
                                                                echo '<div class="rutinas-side-by-side">';
                                                                
                                                                // COLUMNA IZQUIERDA: DIARIO (CON NÓMINA, HORA Y NOTA EN TABLA LIMPIA)
                                                                echo '<div class="rutina-columna">';
                                                                if ($has_diario) {
                                                                    echo '<div class="frecuencia-header bg-primary text-white"><i class="bi bi-calendar-day me-2"></i> RUTINAS DIARIAS</div>';
                                                                    echo '<div class="table-wrap"><table class="table-matriz table-hover m-0">';
                                                                    echo '<thead><tr>';
                                                                    echo '<th style="text-align:left; padding-left:1.2rem; width:32%;">Parámetro</th>';
                                                                    echo '<th style="width:18%;">Lectura</th>';
                                                                    echo '<th style="width:15%;">Nómina</th>';
                                                                    echo '<th style="text-align:left; width:25%;">Observaciones</th>';
                                                                    echo '<th style="width:10%;">Hora</th>';
                                                                    echo '</tr></thead><tbody>';
                                                                    
                                                                    while ($p = mysqli_fetch_assoc($q_diario)) {
                                                                        $p_nombre_norm = mb_strtolower($p['nombre_parametro'], 'UTF-8');
                                                                        $sql_reg = "SELECT valor_capturado, observaciones, fecha_registro, numero_nomina FROM bitacora_lecturas WHERE parametro_id = {$p['id']} AND DATE(fecha_registro) = '$fecha_filtro' ORDER BY id DESC LIMIT 1";
                                                                        $res_reg = mysqli_query($conn, $sql_reg);
                                                                        $reg = mysqli_fetch_assoc($res_reg);
                                                                        
                                                                        $val = $reg['valor_capturado'] ?? null;
                                                                        $obs = $reg['observaciones'] ?? '';
                                                                        $nomina_val = $reg['numero_nomina'] ?? '';
                                                                        $badge_class = getBadgeClass($val, $p);
                                                                        
                                                                        echo '<tr class="fila-parametro" data-param-nombre="'.htmlspecialchars($p_nombre_norm).'">';
                                                                        echo '<td class="parametro-nombre">'.htmlspecialchars($p['nombre_parametro']).'</td>';
                                                                        
                                                                        if ($val !== null) {
                                                                            echo '<td><span class="badge-valor '.$badge_class.'">'.htmlspecialchars($val).'</span></td>';
                                                                            echo '<td><span class="nomina-pill">'.htmlspecialchars($nomina_val).'</span></td>';
                                                                            echo '<td style="text-align:left; vertical-align:middle;">';
                                                                            if(!empty($obs)) { echo '<div class="obs-box m-0">'.htmlspecialchars($obs).'</div>'; } 
                                                                            else { echo '<span class="text-muted" style="font-size:0.8rem;">Sin notas</span>'; }
                                                                            echo '</td>';
                                                                            echo '<td><div class="text-muted fw-bold" style="font-size:0.75rem;"><i class="bi bi-clock-fill text-success me-1"></i>'.date('h:i A', strtotime($reg['fecha_registro'])).'</div></td>';
                                                                        } else {
                                                                            echo '<td><span class="badge bg-light text-muted border py-1 px-2">Pendiente</span></td>';
                                                                            echo '<td><span class="text-muted">—</span></td>';
                                                                            echo '<td><span class="text-muted">—</span></td>';
                                                                            echo '<td><span class="text-muted">—</span></td>';
                                                                        }
                                                                        echo '</tr>';
                                                                    }
                                                                    echo '</tbody></table></div>';
                                                                } else {
                                                                    echo '<div class="frecuencia-header bg-light text-muted"><i class="bi bi-calendar-day me-2"></i> RUTINAS DIARIAS</div>';
                                                                    echo '<div class="p-4 text-center text-muted small">— Sin parámetros diarios —</div>';
                                                                }
                                                                echo '</div>';

                                                                // COLUMNA DERECHA: SEMANAL (CON NÓMINA, FECHA Y NOTA EN TABLA LIMPIA)
                                                                echo '<div class="rutina-columna">';
                                                                if ($has_semanal) {
                                                                    echo '<div class="frecuencia-header bg-purple text-white"><i class="bi bi-calendar-week me-2"></i> RUTINAS SEMANALES</div>';
                                                                    echo '<div class="table-wrap"><table class="table-matriz table-hover m-0">';
                                                                    echo '<thead><tr>';
                                                                    echo '<th style="text-align:left; padding-left:1.2rem; width:32%;">Parámetro</th>';
                                                                    echo '<th style="width:18%;">Lectura</th>';
                                                                    echo '<th style="width:15%;">Nómina</th>';
                                                                    echo '<th style="text-align:left; width:25%;">Observaciones</th>';
                                                                    echo '<th style="width:10%;">Fecha</th>';
                                                                    echo '</tr></thead><tbody>';
                                                                    
                                                                    while ($p = mysqli_fetch_assoc($q_semanal)) {
                                                                        $p_nombre_norm = mb_strtolower($p['nombre_parametro'], 'UTF-8');
                                                                        $sql_reg = "SELECT valor_capturado, observaciones, fecha_registro, numero_nomina FROM bitacora_lecturas WHERE parametro_id = {$p['id']} AND DATE(fecha_registro) <= '$fecha_filtro' AND DATE(fecha_registro) >= DATE_SUB('$fecha_filtro', INTERVAL 7 DAY) ORDER BY id DESC LIMIT 1";
                                                                        $res_reg = mysqli_query($conn, $sql_reg);
                                                                        $reg = mysqli_fetch_assoc($res_reg);
                                                                        
                                                                        $val = $reg['valor_capturado'] ?? null;
                                                                        $obs = $reg['observaciones'] ?? '';
                                                                        $nomina_val = $reg['numero_nomina'] ?? '';
                                                                        $badge_class = getBadgeClass($val, $p);
                                                                        
                                                                        echo '<tr class="fila-parametro" data-param-nombre="'.htmlspecialchars($p_nombre_norm).'">';
                                                                        echo '<td class="parametro-nombre">'.htmlspecialchars($p['nombre_parametro']).'</td>';
                                                                        
                                                                        if ($val !== null) {
                                                                            echo '<td><span class="badge-valor '.$badge_class.'">'.htmlspecialchars($val).'</span></td>';
                                                                            echo '<td><span class="nomina-pill">'.htmlspecialchars($nomina_val).'</span></td>';
                                                                            echo '<td style="text-align:left; vertical-align:middle;">';
                                                                            if(!empty($obs)) { echo '<div class="obs-box m-0">'.htmlspecialchars($obs).'</div>'; } 
                                                                            else { echo '<span class="text-muted" style="font-size:0.8rem;">Sin notas</span>'; }
                                                                            echo '</td>';
                                                                            echo '<td><div class="text-muted fw-bold" style="font-size:0.75rem;"><i class="bi bi-calendar-check text-success me-1"></i>'.date('d/m/Y', strtotime($reg['fecha_registro'])).'</div></td>';
                                                                        } else {
                                                                            echo '<td><span class="badge bg-light text-muted border py-1 px-2">Pendiente</span></td>';
                                                                            echo '<td><span class="text-muted">—</span></td>';
                                                                            echo '<td><span class="text-muted">—</span></td>';
                                                                            echo '<td><span class="text-muted">—</span></td>';
                                                                        }
                                                                        echo '</tr>';
                                                                    }
                                                                    echo '</tbody></table></div>';
                                                                } else {
                                                                    echo '<div class="frecuencia-header bg-light text-muted"><i class="bi bi-calendar-week me-2"></i> RUTINAS SEMANALES</div>';
                                                                    echo '<div class="p-4 text-center text-muted small">— Sin parámetros semanales —</div>';
                                                                }
                                                                echo '</div>';

                                                                echo '</div>'; // Fin de rutinas-side-by-side
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
    actualizarContador();
});

let zonaSeleccionadaId = 0, grupoSeleccionado = 'todos', equipoSeleccionadoId = 0;
function seleccionarZona(zonaId, btn) { zonaSeleccionadaId = zonaId; grupoSeleccionado = 'todos'; equipoSeleccionadoId = 0; actualizarBotones(btn, '.btn-filter[onclick*="seleccionarZona"]'); document.querySelectorAll('.btn-filter.green').forEach(b => b.classList.remove('active')); document.querySelector('.btn-filter.green[onclick*="todos"]')?.classList.add('active'); document.querySelectorAll('.btn-filter.yellow').forEach(b => b.classList.remove('active')); document.querySelector('.btn-filter.yellow[onclick*="seleccionarEquipo(0"]')?.classList.add('active'); actualizarBotonesEquiposVisibles(); aplicarFiltrosMatriz(); actualizarContador(); }
function seleccionarGrupo(grupo, btn) { grupoSeleccionado = grupo; equipoSeleccionadoId = 0; actualizarBotones(btn, '.btn-filter.green'); document.querySelectorAll('.btn-filter.yellow').forEach(b => b.classList.remove('active')); document.querySelector('.btn-filter.yellow[onclick*="seleccionarEquipo(0"]')?.classList.add('active'); actualizarBotonesEquiposVisibles(); aplicarFiltrosMatriz(); actualizarContador(); }
function seleccionarEquipo(equipoId, btn) { equipoSeleccionadoId = equipoId; actualizarBotones(btn, '.btn-filter.yellow'); aplicarFiltrosMatriz(); actualizarContador(); }
function actualizarBotones(btn, selector) { document.querySelectorAll(selector).forEach(b => b.classList.remove('active')); if (btn) btn.classList.add('active'); }
function actualizarBotonesEquiposVisibles() { document.querySelectorAll('.btn-equipo-item').forEach(btn => { const zId = parseInt(btn.dataset.zonaId); const nombre = btn.dataset.equipoNombre || ''; const zonaOk = (zonaSeleccionadaId === 0 || zId === zonaSeleccionadaId); const grupoOk = (grupoSeleccionado === 'todos' || nombre.includes(grupoSeleccionado)); btn.style.display = (zonaOk && grupoOk) ? 'inline-block' : 'none'; }); }
function aplicarFiltrosMatriz() { document.querySelectorAll('.tarjeta-equipo').forEach(tarjeta => { const eqId = parseInt(tarjeta.dataset.equipoId); const zId = parseInt(tarjeta.dataset.zonaId); const nombre = tarjeta.dataset.equipoNombre || ''; const zonaOk = (zonaSeleccionadaId === 0 || zId === zonaSeleccionadaId); const grupoOk = (grupoSeleccionado === 'todos' || nombre.includes(grupoSeleccionado)); const equipoOk = (equipoSeleccionadoId === 0 || eqId === equipoSeleccionadoId); tarjeta.style.display = (zonaOk && grupoOk && equipoOk) ? 'block' : 'none'; }); actualizarVisibilidadSecciones(); }
function actualizarVisibilidadSecciones() { document.querySelectorAll('.bloque-area').forEach(area => { const visibles = Array.from(area.querySelectorAll('.tarjeta-equipo')).filter(e => e.style.display !== 'none').length; area.style.display = (visibles > 0) ? 'block' : 'none'; }); document.querySelectorAll('.bloque-zona').forEach(zona => { const visibles = Array.from(zona.querySelectorAll('.bloque-area')).filter(a => a.style.display !== 'none').length; zona.style.display = (visibles > 0) ? 'block' : 'none'; }); }
function actualizarContador() { const visibles = document.querySelectorAll('.tarjeta-equipo[style*="display: block"]').length; document.getElementById('contador-visibles').textContent = visibles; }
function filtrarEnVivo() { const texto = document.getElementById('buscadorVivo').value.toLowerCase().trim(); document.querySelectorAll('.tarjeta-equipo').forEach(tarjeta => { const nombre = tarjeta.dataset.equipoNombre || ''; let coincide = (texto === '' || nombre.includes(texto)); tarjeta.querySelectorAll('.fila-parametro').forEach(p => { const pNombre = p.dataset.paramNombre || ''; const td = p.querySelector('.parametro-nombre'); if (td) { let contenido = td.textContent; if (texto !== '' && pNombre.includes(texto)) { coincide = true; td.innerHTML = contenido.replace(new RegExp(texto.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi'), match => `<mark style="background:#ffeb3b; padding:0 2px;">${match}</mark>`); } else { td.innerHTML = contenido; } } }); tarjeta.style.display = coincide ? 'block' : 'none'; }); actualizarVisibilidadSecciones(); actualizarContador(); }
function limpiarBuscador() { document.getElementById('buscadorVivo').value = ''; document.querySelectorAll('.fila-parametro .parametro-nombre').forEach(td => td.innerHTML = td.textContent); aplicarFiltrosMatriz(); actualizarContador(); seleccionarZona(0, document.querySelector('.btn-filter[onclick*="seleccionarZona(0"]')); }
</script>

<?php include 'includes/footer.php'; ?>