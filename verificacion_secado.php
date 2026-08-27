<?php
// verificacion_secado.php
session_start();
if (!isset($_SESSION['nomina'])) {
    header("Location: index.php");
    exit();
}

date_default_timezone_set('America/Mazatlan');

// ========================================================================
// 1. CONEXIÓN A LA BASE DE DATOS DE PROCESOS (Progel_procesos)
// ========================================================================
$host_procesos = "localhost";
$user_procesos = "root";
$pass_procesos = ""; 
$db_procesos   = "Progel_procesos";

$conn_procesos = mysqli_connect($host_procesos, $user_procesos, $pass_procesos, $db_procesos);
if (!$conn_procesos) {
    die("Error de conexión a Progel_procesos: " . mysqli_connect_error());
}

$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');

// ========================================================================
// 2. LÓGICA DE SEMAFORIZACIÓN CON COLORES OFICIALES DEL CORE
// ========================================================================
function get_cell_style($column, $value, $secador = null) {
    // Si está vacío o es un guion, no pintamos nada
    if ($value === null || $value === '-' || $value === '') {
        return '';
    }

    // Colores fieles al estándar del Core
    $green  = 'background-color: #198754 !important; color: #ffffff !important; font-weight: 900;';
    $yellow = 'background-color: #ffc107 !important; color: #000000 !important; font-weight: 900;';
    $red    = 'background-color: #dc3545 !important; color: #ffffff !important; font-weight: 900;';

    // ¡MAGIA!: Si el valor individual es F.O., lo pintamos de ROJO
    if (strtoupper(trim($value)) === 'F.O.') {
        return $red;
    }

    $val = is_numeric($value) ? floatval($value) : $value;
    
    switch ($column) {
        case 'altura_galleta':
            if ($val >= 6.5 && $val <= 7.5) return $green;
            if ($val >= 6.0 && $val < 6.5) return $yellow;
            return $red;

        case 'flujo':
            if ($secador == 1) {
                if ($val >= 26 && $val <= 28) return $green;
                if ($val >= 24 && $val < 26) return $yellow;
                return $red;
            } elseif ($secador == 2) {
                if ($val == 24) return $green;
                if ($val >= 22 && $val < 24) return $yellow;
                return $red;
            } else { // S3 a S5
                if ($val >= 10 && $val <= 13) return $green;
                if ($val > 13 && $val <= 16) return $yellow;
                return $red;
            }

        case 'hz':
            if ($secador == 1) {
                if ($val >= 45 && $val <= 52) return $green;
                return $red;
            } elseif ($secador == 2) {
                if ($val == 52.8) return $green;
                return $red;
            } else { // S3 a S5
                if ($val > 16 && $val <= 18) return $green;
                if ($val >= 14 && $val <= 16) return $yellow;
                return $red;
            }

        case 'hum_penultima':
            if ($secador == 1) {
                if ($val < 12) return $green;
                if ($val >= 12 && $val <= 15) return $yellow;
                return $red;
            } else { // S2, S3, S4, S5
                if ($val < 10) return $green;
                if ($val >= 10 && $val <= 12) return $yellow;
                return $red;
            }

        case 'hum_ultima':
            if ($secador == 1 || $secador == 2) {
                if ($val < 12) return $green;
                if ($val >= 12 && $val <= 15) return $yellow;
                return $red;
            } else { // S3, S4, S5
                if ($val < 10) return $green;
                if ($val >= 10 && $val <= 12) return $yellow;
                return $red;
            }

        case 'rc9':
            if ($secador == 2) {
                if ($val < 10) return $green;
                if ($val >= 10 && $val <= 12) return $yellow;
                return $red;
            }
            return '';

        case 'hum_relativa_cam5':
            if ($val < 36) return $green;
            if ($val >= 36 && $val <= 37) return $yellow;
            return $red;

        case 'textura':
            $t = strtolower(trim($val));
            if ($t === 'firme' || $t === 'buena') return $green;
            if ($t === 'plástica' || $t === 'plastica') return $yellow;
            if ($t === 'húmeda' || $t === 'humeda' || $t === 'quebradiza') return $red;
            return '';
    }
    return '';
}

// ========================================================================
// 3. CONSULTA DE REGISTROS AGRUPADOS POR HORA
// ========================================================================
$resultados_por_hora = [];
$sql_verif = "SELECT * FROM verificacion_secado WHERE fecha = ? ORDER BY hora ASC, secador ASC";
$stmt_v = mysqli_prepare($conn_procesos, $sql_verif);
if ($stmt_v) {
    mysqli_stmt_bind_param($stmt_v, 's', $fecha_filtro);
    mysqli_stmt_execute($stmt_v);
    $res_v = mysqli_stmt_get_result($stmt_v);
    while ($row = mysqli_fetch_assoc($res_v)) {
        $hora_grupo = date('H:00', strtotime($row['hora']));
        $resultados_por_hora[$hora_grupo][] = $row;
    }
    mysqli_stmt_close($stmt_v);
}

include 'includes/header.php';
?>

<style>
    body { background-color: #f0f4f8; }
    
    /* Forzamos la tabla a ocupar todo el ancho disponible */
    .container-fluid {
        padding-left: 15px !important;
        padding-right: 15px !important;
        max-width: 100% !important;
    }

    .page-shell { margin-top: 15px; }

    /* Barra Superior Blanca Core */
    .top-bar-scada {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        border: 1px solid #e2e8f0;
    }

    /* Contenedor de las Tablas Blancas */
    .scada-table-wrapper {
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
        border: 1px solid #cbd5e1;
        overflow: hidden;
        margin-bottom: 2.5rem;
        width: 100%;
    }

    .scada-header {
        background: #ffffff;
        color: #0f172a;
        text-align: left;
        font-size: 1.15rem;
        font-weight: 800;
        padding: 15px 20px;
        border-bottom: 2px solid #e2e8f0;
    }

    .table-scada {
        width: 100%;
        table-layout: fixed; 
        border-collapse: collapse;
        margin: 0;
    }

    /* Encabezados Oscuros Limpios (Slate 800) */
    .table-scada th {
        background-color: #1e293b !important;
        color: #ffffff !important;
        border: 1px solid #334155 !important;
        text-align: center;
        vertical-align: top;
        padding: 12px 6px;
        font-size: 0.85rem;
    }

    /* Celdas de Datos Blancas */
    .table-scada td {
        border: 1px solid #cbd5e1 !important;
        text-align: center;
        vertical-align: middle;
        padding: 15px 8px;
        font-size: 1.25rem; 
        font-weight: 800;
        color: #1e293b;
    }

    .table-scada tr:hover td { background-color: #f8fafc; }

    .col-hora { width: 10%; background-color: #f1f5f9 !important; border-right: 3px solid #cbd5e1 !important;}
    .col-data { width: 11.25%; } 

    /* ========================================================================
       DISEÑO DEL SEMÁFORO VERTICAL
       ======================================================================== */
    .semaforo-container {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        margin-top: 8px;
    }

    /* Caja negra tipo semáforo de calle */
    .semaforo-vertical {
        display: flex;
        flex-direction: column;
        gap: 4px;
        background: #000;
        padding: 5px 6px;
        border-radius: 12px;
        box-shadow: inset 0 2px 4px rgba(0,0,0,0.6), 0 1px 2px rgba(255,255,255,0.2);
        border: 2px solid #333;
    }

    /* Lámparas del semáforo */
    .semaforo-vertical .circle {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        opacity: 0.3;
    }
    .semaforo-vertical .circle.green  { background-color: #22c55e; opacity: 1; box-shadow: 0 0 8px #22c55e; }
    .semaforo-vertical .circle.yellow { background-color: #eab308; opacity: 1; box-shadow: 0 0 8px #eab308; }
    .semaforo-vertical .circle.red    { background-color: #ef4444; opacity: 1; box-shadow: 0 0 8px #ef4444; }

    /* Textos al lado del semáforo */
    .semaforo-text {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        font-size: 0.65rem;
        font-weight: 700;
        line-height: 16px; /* Para que alinee perfecto con las luces */
        text-align: left;
    }
    .st-green  { color: #4ade80; }
    .st-yellow { color: #facc15; }
    .st-red    { color: #f87171; }

    /* ========================================================================
       LISTAS DE RANGOS MÚLTIPLES (FLUJO Y HZ)
       ======================================================================== */
    .multi-rango-list {
        text-align: left;
        display: inline-block;
        font-size: 0.65rem;
        line-height: 1.4;
        margin-top: 8px;
        background: rgba(0,0,0,0.2);
        padding: 6px;
        border-radius: 6px;
        border: 1px solid #334155;
    }
    .multi-rango-list .badge-s { background-color: #475569; padding: 2px 4px; border-radius: 4px; font-weight: 800; color: white; margin-right: 4px;}

</style>

<div class="container-fluid page-shell">
    
    <div class="top-bar-scada d-flex flex-wrap justify-content-between align-items-center mb-4 p-3">
        <div>
            <h4 class="fw-black text-primary m-0" style="font-weight: 900;"><i class="bi bi-ui-checks-grid me-2"></i>Verificación de Secado</h4>
        </div>
        <div class="d-flex align-items-center gap-3">
            <form method="GET" action="" class="d-flex align-items-center gap-2 m-0 bg-light p-2 rounded-3 border">
                <label class="text-secondary small fw-bold"><i class="bi bi-calendar-event-fill me-1"></i>Fecha:</label>
                <input type="date" name="fecha" class="form-control form-control-sm fw-bold text-dark border-0 bg-white" value="<?= $fecha_filtro ?>" onchange="this.form.submit()">
            </form>
            <a href="reporte_maestro.php" class="btn btn-outline-secondary btn-sm fw-bold rounded-pill px-4 py-2 shadow-sm">
                <i class="bi bi-arrow-left me-1"></i> Regresar al Maestro
            </a>
        </div>
    </div>

    <?php if (!empty($resultados_por_hora)): ?>
        <?php foreach ($resultados_por_hora as $hora_grupo => $registros): ?>
        <div class="scada-table-wrapper">
            
            <div class="scada-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history text-primary me-2"></i> LECTURAS DE LAS <span class="text-primary"><?= htmlspecialchars($hora_grupo) ?> HRS</span></span>
                <span class="fs-6 text-muted fw-normal"><i class="bi bi-calendar-check me-1"></i> <?= date('d/m/Y', strtotime($fecha_filtro)) ?></span>
            </div>
            
            <div class="table-responsive">
                <table class="table table-scada">
                    <thead>
                        <tr>
                            <th class="col-hora">HORA / SECADOR</th>
                            
                            <th class="col-data">
                                <span class="d-block text-info">ALTURA</span>
                                <div class="semaforo-container">
                                    <div class="semaforo-vertical">
                                        <div class="circle green"></div>
                                        <div class="circle yellow"></div>
                                        <div class="circle red"></div>
                                    </div>
                                    <div class="semaforo-text">
                                        <span class="st-green">6.5 - 7.5</span>
                                        <span class="st-yellow">6.0 - 6.5</span>
                                        <span class="st-red">&lt; 6.0 ó &gt; 7.5</span>
                                    </div>
                                </div>
                            </th>
                            
                            <th class="col-data">
                                <span class="d-block text-info">FLUJO</span>
                                <div class="multi-rango-list">
                                    <div><span class="badge-s">S1</span> <span class="st-green">26-28</span> | <span class="st-yellow">24-26</span> | <span class="st-red">&lt;24</span></div>
                                    <div class="mt-1"><span class="badge-s">S2</span> <span class="st-green">24</span> | <span class="st-yellow">22-24</span> | <span class="st-red">&lt;22</span></div>
                                    <div class="mt-1"><span class="badge-s">S3-5</span> <span class="st-green">10-13</span> | <span class="st-yellow">14-16</span> | <span class="st-red">Otro</span></div>
                                </div>
                            </th>
                            
                            <th class="col-data">
                                <span class="d-block text-info">FREC. (Hz)</span>
                                <div class="multi-rango-list">
                                    <div><span class="badge-s">S1</span> <span class="st-green">45-52</span> | <span class="st-red">Otro</span></div>
                                    <div class="mt-1"><span class="badge-s">S2</span> <span class="st-green">52.8</span> | <span class="st-red">Otro</span></div>
                                    <div class="mt-1"><span class="badge-s">S3-5</span> <span class="st-green">16-18</span> | <span class="st-yellow">14-16</span> | <span class="st-red">Otro</span></div>
                                </div>
                            </th>
                            
                            <th class="col-data">
                                <span class="d-block text-info">HUM. PENÚLTIMA</span>
                                <div class="multi-rango-list">
                                    <div><span class="badge-s">S1</span> <span class="st-green">&lt;12</span> | <span class="st-yellow">12-15</span> | <span class="st-red">&gt;15</span></div>
                                    <div class="mt-1"><span class="badge-s">S2-5</span> <span class="st-green">&lt;10</span> | <span class="st-yellow">10-12</span> | <span class="st-red">&gt;12</span></div>
                                </div>
                            </th>
                            
                            <th class="col-data">
                                <span class="d-block text-info">HUM. ÚLTIMA</span>
                                <div class="multi-rango-list">
                                    <div><span class="badge-s">S1-2</span> <span class="st-green">&lt;12</span> | <span class="st-yellow">12-15</span> | <span class="st-red">&gt;15</span></div>
                                    <div class="mt-1"><span class="badge-s">S3-5</span> <span class="st-green">&lt;10</span> | <span class="st-yellow">10-12</span> | <span class="st-red">&gt;12</span></div>
                                </div>
                            </th>
                            
                            <th class="col-data">
                                <span class="d-block text-info">RC 9 (Solo S2)</span>
                                <div class="semaforo-container">
                                    <div class="semaforo-vertical">
                                        <div class="circle green"></div>
                                        <div class="circle yellow"></div>
                                        <div class="circle red"></div>
                                    </div>
                                    <div class="semaforo-text">
                                        <span class="st-green">&lt; 10</span>
                                        <span class="st-yellow">10 - 12</span>
                                        <span class="st-red">&gt; 12</span>
                                    </div>
                                </div>
                            </th>
                            
                            <th class="col-data">
                                <span class="d-block text-info">H.R. CÁM 5</span>
                                <div class="semaforo-container">
                                    <div class="semaforo-vertical">
                                        <div class="circle green"></div>
                                        <div class="circle yellow"></div>
                                        <div class="circle red"></div>
                                    </div>
                                    <div class="semaforo-text">
                                        <span class="st-green">&lt; 36</span>
                                        <span class="st-yellow">36 - 37</span>
                                        <span class="st-red">&gt; 37</span>
                                    </div>
                                </div>
                            </th>
                            
                            <th class="col-data">
                                <span class="d-block text-info">TEXTURA</span>
                                <div class="semaforo-container">
                                    <div class="semaforo-vertical">
                                        <div class="circle green"></div>
                                        <div class="circle yellow"></div>
                                        <div class="circle red"></div>
                                    </div>
                                    <div class="semaforo-text">
                                        <span class="st-green">Firme / Buena</span>
                                        <span class="st-yellow">Plástica</span>
                                        <span class="st-red">Húmeda / Queb.</span>
                                    </div>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($registros as $row): ?>
                            <?php
                                // MAGIA: Convertir los -1 secretos de vuelta a F.O. antes de revisar los estilos
                                $campos_revisar = ['altura_galleta', 'flujo', 'hz', 'hum_penultima', 'hum_ultima', 'rc9', 'hum_relativa_cam5'];
                                foreach ($campos_revisar as $c) {
                                    if ($row[$c] == -1) {
                                        $row[$c] = 'F.O.';
                                    }
                                }

                                $altura_style = get_cell_style('altura_galleta', $row['altura_galleta'], $row['secador']);
                                $flujo_style = get_cell_style('flujo', $row['flujo'], $row['secador']);
                                $hz_style = get_cell_style('hz', $row['hz'], $row['secador']);
                                $hum_p_style = get_cell_style('hum_penultima', $row['hum_penultima'], $row['secador']);
                                $hum_u_style = get_cell_style('hum_ultima', $row['hum_ultima'], $row['secador']);
                                $rc9_style = get_cell_style('rc9', $row['rc9'], $row['secador']);
                                $hr_cam_style = get_cell_style('hum_relativa_cam5', $row['hum_relativa_cam5'], $row['secador']);
                                $textura_style = get_cell_style('textura', $row['textura'], $row['secador']);
                            ?>
                            <tr>
                                <td class="col-hora text-center align-middle">
                                    <div class="d-flex flex-column align-items-center justify-content-center">
                                        <span class="badge bg-primary fs-6 mb-2 py-2 px-3 shadow-sm rounded-pill">S<?= htmlspecialchars($row['secador']) ?></span>
                                        <span class="text-dark fw-black" style="font-size: 1rem;"><i class="bi bi-clock text-primary"></i> <?= date('H:i', strtotime($row['hora'])) ?></span>
                                        <span class="text-secondary fw-bold mt-1" style="font-size: 0.75rem;"><i class="bi bi-person-fill"></i> <?= htmlspecialchars($row['operador'] ?? 'S/N') ?></span>
                                    </div>
                                </td>
                                
                                <?php if ($row['estado_fo'] == 1): ?>
                                    <td colspan="8" class='text-danger bg-danger bg-opacity-10 fs-4 fw-black' style="letter-spacing: 2px;">F.O. (FUERA DE OPERACIÓN)</td>
                                <?php else: ?>
                                    <td style='<?= $altura_style ?>'><?= ($row['altura_galleta'] !== null ? htmlspecialchars($row['altura_galleta']) : '-') ?></td>
                                    <td style='<?= $flujo_style ?>'><?= ($row['flujo'] !== null ? htmlspecialchars($row['flujo']) : '-') ?></td>
                                    <td style='<?= $hz_style ?>'><?= ($row['hz'] !== null ? htmlspecialchars($row['hz']) : '-') ?></td>
                                    <td style='<?= $hum_p_style ?>'><?= ($row['hum_penultima'] !== null ? htmlspecialchars($row['hum_penultima']) : '-') ?></td>
                                    <td style='<?= $hum_u_style ?>'><?= ($row['hum_ultima'] !== null ? htmlspecialchars($row['hum_ultima']) : '-') ?></td>
                                    <td style='<?= $rc9_style ?>'><?= ($row['rc9'] !== null ? htmlspecialchars($row['rc9']) : '-') ?></td>
                                    <td style='<?= $hr_cam_style ?>'><?= ($row['hum_relativa_cam5'] !== null ? htmlspecialchars($row['hum_relativa_cam5']) : '-') ?></td>
                                    <td style='<?= $textura_style ?>; font-size: 1rem; text-transform: uppercase;'><?= ($row['textura'] ?? '-') ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="master-card p-5 text-center bg-white shadow-sm border rounded-4">
            <i class="bi bi-inbox text-secondary opacity-25" style="font-size: 5rem;"></i>
            <h4 class="fw-bold text-dark mt-3">SIN REGISTROS EN ESTA FECHA</h4>
            <p class="text-muted fs-5">No se han encontrado lecturas de secado para el <b><?= date('d/m/Y', strtotime($fecha_filtro)) ?></b>.</p>
        </div>
    <?php endif; ?>

</div>

<?php 
mysqli_close($conn_procesos);
include 'includes/footer.php'; 
?>