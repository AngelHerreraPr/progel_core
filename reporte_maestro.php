<?php
// 1. Conexiones a las bases de datos
include 'config/db.php'; 
include 'config/db_aveva.php'; 
include 'logica_reporte_maestro.php'; 

date_default_timezone_set('America/Mazatlan');

define('PARAM_ID_COCEDORES_ACTIVOS', 301); 
define('PARAM_ID_SOLIDOS_CLARI', 302);     

// ========================================================================
// LÓGICA DE FECHA Y TURNO (LIBERADA PARA PERMITIR CAMBIAR DE FECHA)
// ========================================================================
$hora_actual_num = (int)date('G');

if ($hora_actual_num >= 7 && $hora_actual_num < 19) {
    $turno_default = 'diurno';
    $fecha_default = date('Y-m-d');
} else {
    $turno_default = 'nocturno';
    if ($hora_actual_num < 7) {
        $fecha_default = date('Y-m-d', strtotime('-1 day'));
    } else {
        $fecha_default = date('Y-m-d');
    }
}

// Ahora el sistema respeta SIEMPRE la fecha que elijas en el selector
$fecha_seleccionada = $_GET['fecha'] ?? $fecha_default;
$turno_seleccionado = $_GET['turno'] ?? $turno_default;
$hora_cerrada_actual = str_pad($hora_actual_num, 2, '0', STR_PAD_LEFT) . ':00';

function getSabanaSemaforoClass($real, $objetivo_str) {
    if ($real === null || $real === '' || $real === '-') return ''; 
    if (strtoupper(trim($real)) === 'F.O.') return 'semaforo-r-sabana';
    $real_val = floatval($real);
    if (preg_match('/([\d\.]+) A ([\d\.]+)/i', $objetivo_str, $matches)) {
        $min = floatval($matches[1]);
        $max = floatval($matches[2]);
        if ($real_val >= $min && $real_val <= $max) return 'semaforo-v-sabana'; 
        else return 'semaforo-r-sabana'; 
    }
    if (is_numeric($objetivo_str)) {
        $objetivo_val = floatval($objetivo_str);
        if ($real_val >= $objetivo_val) return 'semaforo-v-sabana'; 
        if ($real_val >= $objetivo_val * 0.9) return 'semaforo-a-sabana'; 
        return 'semaforo-r-sabana'; 
    }
    return '';
}

$manual_data = [];

if ($turno_seleccionado == 'diurno') {
    $sql_manual = "SELECT * FROM SUP_captura_produccion WHERE fecha = ? AND HOUR(hora) BETWEEN 7 AND 18";
    $stmt_manual = mysqli_prepare($conn, $sql_manual);
    mysqli_stmt_bind_param($stmt_manual, 's', $fecha_seleccionada);
} else { 
    $fecha_siguiente = date('Y-m-d', strtotime($fecha_seleccionada . ' +1 day'));
    $sql_manual = "SELECT * FROM SUP_captura_produccion WHERE (fecha = ? AND HOUR(hora) >= 19) OR (fecha = ? AND HOUR(hora) <= 6)";
    $stmt_manual = mysqli_prepare($conn, $sql_manual);
    mysqli_stmt_bind_param($stmt_manual, 'ss', $fecha_seleccionada, $fecha_siguiente);
}

if ($stmt_manual) {
    mysqli_stmt_execute($stmt_manual);
    $res_manual = mysqli_stmt_get_result($stmt_manual);
    while ($row = mysqli_fetch_assoc($res_manual)) {
        $hora_clave = substr($row['hora'], 0, 5);
        $manual_data[$hora_clave] = $row;
    }
    mysqli_stmt_close($stmt_manual);
}

// LECTURA BD ANTIGUA (Para precargar al editar)
$conn_procesos_local = mysqli_connect("localhost", "root", "", "Progel_procesos");
if ($conn_procesos_local) {
    if ($turno_seleccionado == 'diurno') {
        $sql_sec = "SELECT * FROM verificacion_secado WHERE fecha = ? AND HOUR(hora) BETWEEN 7 AND 18";
        $stmt_sec = mysqli_prepare($conn_procesos_local, $sql_sec);
        mysqli_stmt_bind_param($stmt_sec, 's', $fecha_seleccionada);
    } else {
        $sql_sec = "SELECT * FROM verificacion_secado WHERE (fecha = ? AND HOUR(hora) >= 19) OR (fecha = ? AND HOUR(hora) <= 6)";
        $stmt_sec = mysqli_prepare($conn_procesos_local, $sql_sec);
        mysqli_stmt_bind_param($stmt_sec, 'ss', $fecha_seleccionada, $fecha_siguiente);
    }

    if ($stmt_sec) {
        mysqli_stmt_execute($stmt_sec);
        $res_sec = mysqli_stmt_get_result($stmt_sec);
        while ($row = mysqli_fetch_assoc($res_sec)) {
            $hora_clave = substr($row['hora'], 0, 5);
            if (!isset($manual_data[$hora_clave])) {
                $manual_data[$hora_clave] = [];
            }
            if (!isset($manual_data[$hora_clave]['secadores'])) {
                $manual_data[$hora_clave]['secadores'] = [];
            }
            $manual_data[$hora_clave]['secadores'][$row['secador']] = [
                'estado_fo' => $row['estado_fo'],
                'flujo' => $row['flujo'],
                'hz' => $row['hz'],
                'rc9' => $row['rc9'],
                'hum_penultima' => $row['hum_penultima'],
                'hum_ultima' => $row['hum_ultima'],
                'altura_galleta' => $row['altura_galleta'],
                'hum_relativa_cam5' => $row['hum_relativa_cam5'],
                'textura' => $row['textura']
            ];
        }
        mysqli_stmt_close($stmt_sec);
    }
    mysqli_close($conn_procesos_local);
}

$horas_diurno = [];
for ($i = 7; $i <= 18; $i++) $horas_diurno[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';

$horas_nocturno = [];
for ($i = 19; $i <= 23; $i++) $horas_nocturno[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
for ($i = 0; $i <= 6; $i++) $horas_nocturno[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';

$todas_las_horas = array_merge($horas_diurno, $horas_nocturno);

include 'includes/header.php'; 
?>

<link rel="stylesheet" href="assets/css/reporte_maestro.css">


<div class="container-fluid px-4 page-shell">    
    
    <div class="master-card">
        <div class="panel-header d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <h3 class="fw-bold text-primary m-0"><i class="bi bi-layout-text-window-reverse me-2"></i>Reporte de Producción</h3>
                <span class="text-muted small">Supervisión y control operativo general</span>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <a href="verificacion_secado.php" class="btn btn-success fw-bold rounded-pill shadow-sm px-4">
                    <i class="bi bi-wind me-1"></i> Ir a Verificación de Secado
                </a>
                <a href="index.php" class="btn btn-outline-secondary fw-bold rounded-pill shadow-sm px-4">
                    <i class="bi bi-arrow-left me-1"></i> Menú
                </a>
            </div>
        </div>
        
        <div class="bg-light p-3 border-bottom">
            <form method="GET" action="" class="d-flex flex-wrap align-items-center gap-3 m-0">
                <div class="input-group shadow-sm" style="max-width: 250px;">
                    <span class="input-group-text bg-white border-end-0 text-primary"><i class="bi bi-calendar-date-fill"></i></span>
                    <input type="date" id="fecha" name="fecha" class="form-control border-start-0 fw-bold text-secondary" value="<?= $fecha_seleccionada ?>" onchange="this.form.submit()">
                </div>
                
                <div class="btn-group shadow-sm" role="group">
                    <input type="radio" class="btn-check" name="turno" id="turno_diurno" value="diurno" onchange="this.form.submit()" <?= ($turno_seleccionado == 'diurno') ? 'checked' : '' ?>>
                    <label class="btn <?= ($turno_seleccionado == 'diurno') ? 'btn-primary' : 'btn-white bg-white text-secondary border' ?> fw-bold px-4" for="turno_diurno"><i class="bi bi-brightness-high-fill me-1"></i> Diurno</label>

                    <input type="radio" class="btn-check" name="turno" id="turno_nocturno" value="nocturno" onchange="this.form.submit()" <?= ($turno_seleccionado == 'nocturno') ? 'checked' : '' ?>>
                    <label class="btn <?= ($turno_seleccionado == 'nocturno') ? 'btn-dark' : 'btn-white bg-white text-secondary border' ?> fw-bold px-4" for="turno_nocturno"><i class="bi bi-moon-stars-fill me-1"></i> Nocturno</label>
                </div>
            </form>  
        </div>
    </div>

    <!-- PANEL DE CAPTURA MANUAL -->
    <div class="master-card">
        <form method="POST" action="guardar_hora.php" autocomplete="off" id="form-captura-manual">
            <input type="hidden" name="fecha_original" value="<?= $fecha_seleccionada ?>">
            <input type="hidden" name="turno" value="<?= $turno_seleccionado ?>">
            <input type="hidden" name="fecha" value="<?= $fecha_seleccionada ?>">

            <div class="panel-header d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom">
                <div>
                    <h5 class="fw-bold text-dark m-0"><i class="bi bi-ui-checks-grid text-primary me-2"></i>Captura de Parámetros</h5>
                    <small class="text-muted">Selecciona la hora para cargar o registrar datos.</small>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <!-- ACCIONES MAESTRAS RÁPIDAS (F.O. / LAVADO / LIMPIAR) -->
                    <div class="btn-group shadow-sm" role="group" aria-label="Acciones rápidas globales">
                        <button type="button" class="btn btn-outline-danger btn-sm fw-bold btn-rapido-accion d-flex align-items-center gap-1" onclick="marcarTodo('FO')" title="Marcar toda la maquinaria en Fuera de Operación">
                            <i class="bi bi-x-circle-fill text-danger"></i> <span>F.O.</span>
                        </button>
                        <button type="button" class="btn btn-outline-info btn-sm fw-bold text-dark btn-rapido-accion d-flex align-items-center gap-1" onclick="marcarTodo('LAVADO')" title="Marcar toda la maquinaria en Lavado (CIP)">
                            <i class="bi bi-droplet-fill text-info"></i> <span>LAVADO</span>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm btn-rapido-accion d-flex align-items-center gap-1" onclick="marcarTodo('LIMPIAR')" title="Restablecer todos los campos a blanco/numérico">
                            <i class="bi bi-arrow-counterclockwise"></i> <span>Limpiar</span>
                        </button>
                    </div>

                    <select name="hora" id="cap_hora" class="form-select form-select-lg fw-bold border-primary text-primary bg-primary bg-opacity-10 shadow-sm" style="min-width: 150px;" onchange="gestionarEstadoFormulario(this.value)" required>
                        <option value="">-- Hora --</option>
                        <?php foreach ($todas_las_horas as $h): ?>
                            <?php
                                $tiene_datos = isset($manual_data[$h]);
                                $selected = ($h == $hora_cerrada_actual && !$tiene_datos) ? 'selected' : '';
                            ?>
                            <option value="<?= $h ?>" <?= $selected ?> data-guardado="<?= $tiene_datos ? 'true' : 'false' ?>">
                                <?= $h ?> <?= $tiene_datos ? '✓ (Guardado)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="card-body p-4">

                <div id="mensaje-bloqueo" class="alert alert-info text-center fw-bold shadow-sm" style="display: none; border-radius: 10px;">
                    <i class="bi bi-pencil-square me-2"></i> Estás editando un registro previamente guardado. Al guardar se actualizará la información.
                </div>
                
                <!-- SECCIÓN 1: PROCESOS -->
                <div class="group-card mb-4 shadow-sm">
                    <h6 class="section-title text-secondary"><i class="bi bi-gear-wide-connected text-secondary"></i> Procesos y Cocedores</h6>
                    <div class="input-grid">
                        <div><label class="input-label">Consumo cuero/hr</label><input type="number" step="0.01" name="consumo_cuero" class="form-control form-control-sm" required></div>
                        <div><label class="input-label text-dark">Cocedores Manual</label><input type="number" step="0.01" name="cocedores_manual" class="form-control form-control-sm border-dark" required></div>
                        <div><label class="input-label">Caldo en Pre-UF</label><input type="number" step="0.01" name="caldo_pre_uf" class="form-control form-control-sm" required></div>
                        <div><label class="input-label">Pre-Concentrado</label><input type="number" step="0.01" name="pre_concentrado" class="form-control form-control-sm" required></div>
                        <div><label class="input-label">Caldo Concentrado</label><input type="number" step="0.01" name="caldo_concentrado" class="form-control form-control-sm" required></div>
                        <div><label class="input-label text-warning">Votators activos</label><input type="number" name="votators_activos" class="form-control form-control-sm border-warning" required></div>
                    </div>
                </div>

                <!-- SECCIÓN 2: VOTATORS Y BRIX -->
                <div class="group-card mb-4 shadow-sm" style="background-color: #fffaf0; border-color: #ffe69c;">
                    <h6 class="section-title text-warning text-darken"><i class="bi bi-droplet-half text-warning"></i> Flujo de Votators y Sólidos (Oprime F.O. si apagado)</h6>
                    <div class="input-grid input-grid-compact">
                        <?php 
                        $campos_fo_v = [
                            'flujo_v1' => 'V1', 'flujo_v2' => 'V2', 'flujo_v3' => 'V3', 
                            'flujo_v4' => 'V4', 'flujo_v5' => 'V5', 'flujo_v6' => 'V6'
                        ];
                        foreach ($campos_fo_v as $c_name => $c_label): 
                        ?>
                        <div>
                            <label class="input-label"><?= $c_label ?></label>
                            <div class="input-group input-group-sm shadow-sm rounded-2">
                                <input type="number" step="0.01" id="input_<?= $c_name ?>" name="<?= $c_name ?>" class="form-control border-end-0" oninput="sumarFlujoVotators()" required>
                                <button type="button" class="input-group-text input-group-text-fo" onclick="ponerFO('input_<?= $c_name ?>')" title="Clic: F.O. / LAVADO / Normal">F.O.</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <div>
                            <label class="input-label text-dark">Sólidos Conc</label>
                            <div class="input-group input-group-sm shadow-sm rounded-2">
                                <input type="number" step="0.01" id="input_solidos_brix" name="solidos_brix" class="form-control border-dark border-end-0" oninput="calcularTeoricos()" required>
                                <button type="button" class="input-group-text input-group-text-fo border-dark" onclick="ponerFO('input_solidos_brix')" title="Clic: F.O. / LAVADO / Normal">F.O.</button>
                            </div>
                        </div>

                        <div>
                            <label class="input-label text-primary">Flujo Total</label>
                            <input type="text" id="flujo_votators_total" class="form-control form-control-sm text-primary fw-bold bg-white shadow-sm" readonly placeholder="Auto">
                        </div>
                    </div>
                </div>

                <!-- SECCIÓN 3: TÚNELES Y MATRIZ SECADORES (1 AL 4) COMPACTADA -->
                <div class="group-card mb-4 shadow-sm" style="background-color: #f8fff9; border-color: #a3cfbb;">
                    <h6 class="section-title text-success text-darken"><i class="bi bi-thermometer-half text-success"></i> Humedad y Velocidad de Túneles</h6>
                    
                    <div class="input-grid input-grid-compact mb-4">
                        <!-- Botones de F.O. para Humedades -->
                        <?php for($i=1; $i<=4; $i++): ?>
                        <div>
                            <label class="input-label">Hum T<?= $i ?></label>
                            <div class="input-group input-group-sm shadow-sm rounded-2">
                                <input type="number" step="0.01" name="hum_t<?= $i ?>" id="input_hum_t<?= $i ?>" class="form-control border-end-0" oninput="copiarHumedadUltima()">
                                <button type="button" class="input-group-text input-group-text-fo" onclick="ponerFO('input_hum_t<?= $i ?>')" title="Clic: F.O. / LAVADO / Normal">F.O.</button>
                            </div>
                        </div>
                        <?php endfor; ?>
                        
                        <!-- Botones de F.O. para Velocidades -->
                        <?php for($i=1; $i<=4; $i++): ?>
                        <div>
                            <label class="input-label">Vel T<?= $i ?></label>
                            <div class="input-group input-group-sm shadow-sm rounded-2">
                                <input type="number" step="0.01" name="vel_t<?= $i ?>" id="input_vel_t<?= $i ?>" class="form-control border-end-0" oninput="copiarVelocidadHZ()">
                                <button type="button" class="input-group-text input-group-text-fo" onclick="ponerFO('input_vel_t<?= $i ?>')" title="Clic: F.O. / LAVADO / Normal">F.O.</button>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                    
                    <h6 class="section-title text-success text-darken mt-4 pt-2 border-top border-success border-opacity-25">
                        <i class="bi bi-ui-checks text-success"></i> Matriz Rápida: Parámetros de Secado
                    </h6>
                    
                    <div class="table-responsive shadow-sm rounded-3">
                        <table class="table table-secadores m-0">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Sec</th>
                                    <th style="width: 60px;">F.O.</th>
                                    <th>Flujo<br><span class="text-secondary fw-normal opacity-75" style="font-size:0.60rem;">(S1:26-28|S2:24|S3-4:10-13)</span></th>
                                    <th>HZ<br><span class="text-secondary fw-normal opacity-75" style="font-size:0.60rem;">(S1:45-52|S2:52.8|S3-4:>16-18)</span></th>
                                    <th>RC 9<br><span class="text-secondary fw-normal opacity-75" style="font-size:0.60rem;">(&lt; 10)</span></th>
                                    <th>H. Penúlt.<br><span class="text-secondary fw-normal opacity-75" style="font-size:0.60rem;">(&lt; 12)</span></th>
                                    <th>H. Última<br><span class="text-secondary fw-normal opacity-75" style="font-size:0.60rem;">(&lt; 12)</span></th>
                                    <th>Alt. Gall.<br><span class="text-secondary fw-normal opacity-75" style="font-size:0.60rem;">(6.5-7.5)</span></th>
                                    <th>HR Cám 5<br><span class="text-secondary fw-normal opacity-75" style="font-size:0.60rem;">(&lt; 36)</span></th>
                                    <th style="width: 140px;">Textura<br><span class="text-secondary fw-normal opacity-75" style="font-size:0.60rem;">(Firme)</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for($s=1; $s<=4; $s++): ?>
                                <tr>
                                    <td data-label="Secador" class="text-center align-middle">
                                        <span class="badge bg-success bg-opacity-75 text-white fs-6 shadow-sm">S<?= $s ?></span>
                                    </td>
                                    <td data-label="Apagar (F.O.)" class="text-center align-middle">
                                        <div class="form-check form-switch d-flex justify-content-end m-0">
                                            <input class="form-check-input border-danger" type="checkbox" name="estado_fo_<?= $s ?>" id="estado_fo_<?= $s ?>" value="1" onchange="toggleFOSecador(<?= $s ?>)" style="cursor:pointer;">
                                        </div>
                                    </td>
                                    <td data-label="Flujo">
                                        <input type="text" name="flujo_secado_<?= $s ?>" id="flujo_secado_<?= $s ?>" class="input-secador bg-light fw-bold text-secondary" placeholder="0.0" readonly>
                                    </td>
                                    <td data-label="Frec. (Hz)">
                                        <input type="text" name="hz_secado_<?= $s ?>" id="hz_secado_<?= $s ?>" class="input-secador bg-light fw-bold text-secondary" placeholder="0.0" readonly>
                                    </td>
                                    <td data-label="RC 9"><input type="number" step="0.01" name="rc9_<?= $s ?>" id="rc9_<?= $s ?>" class="input-secador" placeholder="0.0"></td>
                                    <td data-label="Hum. Penúlt."><input type="number" step="0.01" name="hum_penultima_<?= $s ?>" id="hum_penultima_<?= $s ?>" class="input-secador" placeholder="0.0"></td>
                                    
                                    <td data-label="Hum. Última">
                                        <input type="text" name="hum_ultima_<?= $s ?>" id="hum_ultima_<?= $s ?>" class="input-secador bg-light fw-bold text-secondary" placeholder="0.0" readonly>
                                    </td>
                                    
                                    <td data-label="Alt. Galleta"><input type="number" step="0.01" name="altura_galleta_<?= $s ?>" id="altura_galleta_<?= $s ?>" class="input-secador" placeholder="0.0"></td>
                                    <td data-label="HR Cám. 5"><input type="number" step="0.01" name="hum_relativa_cam5_<?= $s ?>" id="hum_relativa_cam5_<?= $s ?>" class="input-secador" placeholder="0.0"></td>
                                    <td data-label="Textura">
                                        <select name="textura_secado_<?= $s ?>" id="textura_secado_<?= $s ?>" class="select-secador">
                                            <option value="">--</option>
                                            <option value="Firme">Firme</option>
                                            <option value="Plástica">Plástica</option>
                                            <option value="Húmeda">Húmeda</option>
                                            <option value="F.O.">F.O.</option>
                                            <option value="LAVADO">LAVADO</option>
                                        </select>
                                    </td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECCIÓN 4: PRODUCCIÓN Y ACCIONES -->
                <div class="row g-4">
                    <div class="col-12 col-xl-7">
                        <div class="group-card shadow-sm" style="background-color: #f0f8ff; border-color: #9eeaf9;">
                            <h6 class="section-title text-info text-darken"><i class="bi bi-box-seam text-info"></i> Producción (Kg y Eficiencia)</h6>
                            <div class="input-grid">
                                
                                <div><label class="input-label">Teóricos</label>
                                    <input type="text" id="cap_kg_teoricos" name="kg_teoricos" class="form-control form-control-sm shadow-sm bg-white text-dark fw-bold border-info" readonly placeholder="0.00" required>
                                </div>
                                
                                <div><label class="input-label">Reales</label>
                                    <input type="number" step="0.01" name="kg_reales" class="form-control form-control-sm shadow-sm" id="cap_kg_reales" oninput="calcularEficiencia()" required>
                                </div>
                                
                                <div><label class="input-label text-danger">Rechazado</label><input type="number" step="0.01" name="rechazo" class="form-control form-control-sm border-danger shadow-sm" required></div>
                                <div><label class="input-label text-warning">Remoler</label><input type="number" step="0.01" name="remoler" class="form-control form-control-sm border-warning shadow-sm" required></div>
                                
                                <div><label class="input-label text-primary">Eficiencia</label>
                                    <input type="text" name="eficiencia" class="form-control form-control-sm bg-primary bg-opacity-10 text-primary fw-bold shadow-sm" id="cap_eficiencia" readonly placeholder="%">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-12 col-xl-5">
                        <div class="group-card border-danger border-opacity-50 bg-danger bg-opacity-10 shadow-sm">
                            <h6 class="section-title text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Código Rojo</h6>
                            <div>
                                <label class="input-label text-danger">Acciones a tomar ante fallas</label>
                                <textarea name="acciones" class="form-control border-danger shadow-sm rounded-3" rows="2" placeholder="Describe la acción correctiva detalladamente..." required></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOTÓN DE GUARDADO FINAL -->
                <div class="mt-4 pt-3 border-top">
                    <button type="submit" id="btn-guardar-hora" class="btn btn-primary btn-lg w-100 fw-bold shadow-lg py-3 fs-4 rounded-4" style="letter-spacing: 1px;">
                        <i class="bi bi-save-fill me-2"></i> GUARDAR REGISTRO DE HORA
                    </button>
                </div>

            </div>
        </form>
    </div>

    <!-- SÁBANA MAESTRA LIMPIA -->
    <?php
    function renderTablaSabana($horas, $titulo, $icono, $badge, $manual_data, $conn_procesos, $conn_aveva, $fecha_seleccionada) {
    ?>
    <h5 class="fw-bold text-dark mt-5 mb-3 d-flex align-items-center">
        <i class="bi <?= $icono ?> text-primary me-2 fs-4"></i> <?= $titulo ?> 
        <span class="badge bg-secondary ms-3 fw-normal"><?= $badge ?></span>
    </h5>
    
    <div class="table-wrapper mb-5">
        <table class="table table-bordered text-center table-sabana">
            <thead class="bg-dark text-white">
                <tr>
                    <th rowspan="3" class="bg-primary text-white border-end-0" style="font-size: 0.8rem;">HORA</th>
                    <th colspan="15" class="bg-secondary text-white">PROCESOS</th>
                    <th colspan="8" class="bg-success text-white">TÚNELES</th>
                    <th colspan="10" class="bg-warning text-dark">VOTATORS</th>
                    <th colspan="8" class="bg-info text-dark">PRODUCCIÓN</th>
                    <th rowspan="3" class="bg-danger text-white">Acciones</th>
                </tr>
                <tr>
                    <th rowspan="2" class="bg-light text-dark">Consumo<br>cuero/hr</th>
                    <th colspan="2" class="bg-light text-dark">Cocedores</th>
                    <th colspan="2" class="bg-light text-dark">Sólidos clarif.</th>
                    <th colspan="2" class="bg-light text-dark">Pre-UF</th>
                    <th colspan="2" class="bg-light text-dark">Tanque 7</th>
                    <th colspan="2" class="bg-light text-dark">Tanque 8</th>
                    <th colspan="2" class="bg-light text-dark">Pre-Concentrado</th>
                    <th colspan="2" class="bg-light text-dark">Concentrado</th>
                    
                    <th colspan="4" class="bg-light text-dark">HUMEDAD</th>
                    <th colspan="4" class="bg-light text-dark">VELOCIDAD</th>
                    
                    <th colspan="2" class="bg-light text-dark">Vot. Activos</th>
                    <th colspan="8" class="bg-light text-dark">Flujo votators</th>
                    <th colspan="2" class="bg-light text-dark">Solidos Conc.</th>
                    <th colspan="6" class="bg-light text-dark">Kg entregados</th>
                </tr>
                <tr>
                    <th class="text-muted">Obj</th><th>Real</th>
                    <th class="text-muted">Obj</th><th>Real</th>
                    <th class="text-muted">Obj</th><th>Real</th>
                    <th class="text-muted">Obj</th><th>Real</th>
                    <th class="text-muted">Obj</th><th>Real</th>
                    <th class="text-muted">Obj</th><th>Real</th>
                    <th class="text-muted">Obj</th><th>Real</th>
                    
                    <th>T1</th><th>T2</th><th>T3</th><th>T4</th>
                    <th>T1</th><th>T2</th><th>T3</th><th>T4</th>
                    
                    <th class="text-muted">Obj</th><th>Real</th>
                    <th class="text-muted">Obj</th><th>V1</th><th>V2</th><th>V3</th><th>V4</th><th>V5</th><th>V6</th><th>Total</th>
                    <th class="text-muted">Obj</th><th>Real</th>
                    <th class="text-muted">Obj</th><th>Teóricos</th><th>Reales</th><th>Rechazo</th><th>Remoler</th><th>EFI</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($horas as $hora) { 
                    $hora_int = intval(substr($hora, 0, 2));
                    $fecha_para_query = $fecha_seleccionada;

                    if (strpos($titulo, 'Nocturno') !== false && $hora_int <= 6) {
                        $fecha_para_query = date('Y-m-d', strtotime($fecha_seleccionada . ' +1 day'));
                    }

                    $dh = $manual_data[$hora] ?? null;

                    $hora_nominal_print = $hora;
                    $hora_real_print = '';
                    $color_hora = '';
                    if (isset($dh['fecha_registro_real']) && !empty($dh['fecha_registro_real'])) {
                        $hora_real_print = date('H:i', strtotime($dh['fecha_registro_real']));
                        $h_nom = intval(substr($hora, 0, 2));
                        $h_real = intval(date('H', strtotime($dh['fecha_registro_real'])));
                        $color_hora = ($h_nom == $h_real) ? 'text-success' : 'text-danger';
                    }

                    $cocedores_real = (isset($dh['cocedores_manual']) && trim($dh['cocedores_manual']) !== '') ? $dh['cocedores_manual'] : '-';
                    $clase_cocedores = getSabanaSemaforoClass($cocedores_real, '8');
                    
                    $solidos_clari_real = '-';
                    if (isset($conn_procesos)) {
                        $hora_exacta = substr($hora, 0, 5); 
                        $sql_solidos = "SELECT c.solidos FROM datos_clarificador c INNER JOIN datos_hora h ON c.id_datos_hora = h.id WHERE DATE(h.fecha_creacion) = '$fecha_para_query' AND h.hora = '$hora_exacta' LIMIT 1";
                        $res_solidos = mysqli_query($conn_procesos, $sql_solidos);
                        if ($res_solidos && mysqli_num_rows($res_solidos) > 0) {
                            $row_solidos = mysqli_fetch_assoc($res_solidos);
                            $solidos_clari_real = number_format((float)$row_solidos['solidos'], 2);
                        }
                    }
                    $clase_solidos = getSabanaSemaforoClass($solidos_clari_real, '2.5 A 3');
                    
                    $aveva_data = []; 
                    if (isset($conn_aveva)) {
                        $fecha_hora_inicio = $fecha_para_query . ' ' . $hora . ':00';
                        $fecha_hora_fin = $fecha_para_query . ' ' . substr($hora, 0, 2) . ':59:59';
                        $sql_aveva = "SELECT TOP 1 [LITROS_TANQUE_7], [LITROS_TANQUE_8] FROM [TREND001] WHERE [Time_Stamp] BETWEEN :inicio AND :fin ORDER BY [Time_Stamp] DESC";
                        try {
                            $stmt_aveva = $conn_aveva->prepare($sql_aveva);
                            $stmt_aveva->execute([':inicio' => $fecha_hora_inicio, ':fin' => $fecha_hora_fin]);
                            $row_aveva = $stmt_aveva->fetch(PDO::FETCH_ASSOC);
                            if ($row_aveva) $aveva_data = $row_aveva; 
                        } catch (PDOException $e) {}
                    }
                    $val_tq7 = isset($aveva_data['LITROS_TANQUE_7']) ? number_format((float)$aveva_data['LITROS_TANQUE_7'], 0) : '-';
                    $val_tq8 = isset($aveva_data['LITROS_TANQUE_8']) ? number_format((float)$aveva_data['LITROS_TANQUE_8'], 0) : '-';

                    $pv = function($key) use ($dh) {
                        return (isset($dh[$key]) && trim($dh[$key]) !== '') ? htmlspecialchars($dh[$key]) : '-';
                    };

                    $color_estado = function($v) {
                        $val = strtoupper(trim((string)$v));
                        if ($val === 'F.O.') return 'semaforo-r-sabana';
                        if ($val === 'LAVADO') return 'semaforo-azul-sabana';
                        return '';
                    };

                    $eficiencia_calculada = '-';
                    $kg_reales_val = $pv('kg_reales');
                    $kg_teoricos_guardados = $pv('kg_teoricos'); 

                    if (is_numeric($kg_reales_val) && is_numeric($kg_teoricos_guardados) && $kg_teoricos_guardados > 0) {
                        $eficiencia_calculada = number_format(($kg_reales_val / $kg_teoricos_guardados) * 100, 2) . '%';
                    }

                    $flujo_total_votators = 0;
                    for ($i = 1; $i <= 6; $i++) {
                        $val_flujo = $pv('flujo_v' . $i);
                        if (is_numeric($val_flujo)) {
                            $flujo_total_votators += floatval($val_flujo);
                        }
                    }
                ?>
                <tr>
                    <td class="hora-cell text-center align-middle">
                        <?= $hora_nominal_print ?>
                        <?php if($hora_real_print): ?>
                            <br><small class="fw-bold <?= $color_hora ?>" style="font-size: 0.70rem;" title="Hora real del sistema"><?= $hora_real_print ?></small>
                        <?php endif; ?>
                    </td>
                    
                    <td class="fw-bold bg-light"><?= $pv('consumo_cuero') ?></td>
                    <td class="text-muted bg-light">8</td> 
                    <td class="fw-bold bg-light <?= $clase_cocedores ?>"><?= $cocedores_real ?></td>
                    <td class="text-muted bg-light">2.5 A 3</td>
                    <td class="fw-bold bg-light <?= $clase_solidos ?>"><?= $solidos_clari_real ?></td>
                    <td class="text-muted bg-light">4000</td> 
                    <td class="fw-bold bg-light"><?= $pv('caldo_pre_uf') ?></td>
                    <td class="text-muted bg-light">2k-4k</td> 
                    <td class="text-primary fw-bold bg-light"><?= $val_tq7 ?></td>
                    <td class="text-muted bg-light">2k-4k</td> 
                    <td class="text-primary fw-bold bg-light"><?= $val_tq8 ?></td>
                    <td class="text-muted bg-light">1500</td> 
                    <td class="fw-bold bg-light"><?= $pv('pre_concentrado') ?></td>
                    <td class="text-muted bg-light">6k-7k</td> 
                    <td class="fw-bold border-end border-2 bg-light"><?= $pv('caldo_concentrado') ?></td>
                    
                    <td class="fw-bold <?= $color_estado($pv('hum_t1')) ?>"><?= $pv('hum_t1') ?></td>
                    <td class="fw-bold <?= $color_estado($pv('hum_t2')) ?>"><?= $pv('hum_t2') ?></td>
                    <td class="fw-bold <?= $color_estado($pv('hum_t3')) ?>"><?= $pv('hum_t3') ?></td>
                    <td class="fw-bold border-end border-2 <?= $color_estado($pv('hum_t4')) ?>"><?= $pv('hum_t4') ?></td>
                    
                    <td class="fw-bold <?= $color_estado($pv('vel_t1')) ?>"><?= $pv('vel_t1') ?></td>
                    <td class="fw-bold <?= $color_estado($pv('vel_t2')) ?>"><?= $pv('vel_t2') ?></td>
                    <td class="fw-bold <?= $color_estado($pv('vel_t3')) ?>"><?= $pv('vel_t3') ?></td>
                    <td class="fw-bold border-end border-2 <?= $color_estado($pv('vel_t4')) ?>"><?= $pv('vel_t4') ?></td>
                    
                    <td class="text-muted bg-light">4</td> 
                    <td class="fw-bold bg-light"><?= $pv('votators_activos') ?></td>
                    <td class="text-muted bg-light">150</td>
                    <td class="fw-bold bg-light <?= $color_estado($pv('flujo_v1')) ?>"><?= $pv('flujo_v1') ?></td> 
                    <td class="fw-bold bg-light <?= $color_estado($pv('flujo_v2')) ?>"><?= $pv('flujo_v2') ?></td> 
                    <td class="fw-bold bg-light <?= $color_estado($pv('flujo_v3')) ?>"><?= $pv('flujo_v3') ?></td> 
                    <td class="fw-bold bg-light <?= $color_estado($pv('flujo_v4')) ?>"><?= $pv('flujo_v4') ?></td> 
                    <td class="fw-bold bg-light <?= $color_estado($pv('flujo_v5')) ?>"><?= $pv('flujo_v5') ?></td> 
                    <td class="fw-bold bg-light <?= $color_estado($pv('flujo_v6')) ?>"><?= $pv('flujo_v6') ?></td> 
                    <td class="fw-bold border-end border-2 text-primary bg-light"><?= ($flujo_total_votators > 0) ? number_format($flujo_total_votators, 2) : '-' ?></td>
                    
                    <td class="text-muted bg-light">19</td> 
                    <td class="fw-bold text-dark bg-light <?= $color_estado($pv('solidos_brix')) ?>"><?= $pv('solidos_brix') ?></td>
                    <td class="text-muted">856</td>
                    <td class="fw-bold"><?= $kg_teoricos_guardados ?></td> 
                    <td class="fw-bold text-success"><?= $pv('kg_reales') ?></td> 
                    <td class="fw-bold text-danger"><?= $pv('rechazo') ?></td> 
                    <td class="fw-bold text-warning"><?= $pv('remoler') ?></td> 
                    <td class="fw-bold border-end border-2 text-primary"><?= $eficiencia_calculada ?></td>
                    
                    <td class="text-danger text-start px-2 small bg-light"><?= $pv('acciones') ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php } ?>

    <?php 
        if ($turno_seleccionado == 'diurno') {
            renderTablaSabana($horas_diurno, 'Turno Diurno', 'bi-brightness-high-fill', '07:00 a 18:00', $manual_data, $conn_procesos, $conn_aveva, $fecha_seleccionada);
        } else { 
            renderTablaSabana($horas_nocturno, 'Turno Nocturno', 'bi-moon-stars-fill', '19:00 a 06:00', $manual_data, $conn_procesos, $conn_aveva, $fecha_seleccionada);
        }
    ?>

</div>

<script>
    // =======================================================
    // 1. CÁLCULO DE PRODUCCIÓN TEÓRICA
    // =======================================================
    function calcularTeoricos() {
        const flujoTotalInput = document.getElementById('flujo_votators_total');
        const solidosBrixInput = document.getElementById('input_solidos_brix');
        const teoricosInput = document.getElementById('cap_kg_teoricos');

        if (flujoTotalInput && solidosBrixInput && teoricosInput) {
            let flujoTotal = parseFloat(flujoTotalInput.value);
            let solidosBrix = parseFloat(solidosBrixInput.value);
            let valBrixStr = (solidosBrixInput.value || '').trim().toUpperCase();

            if (!isNaN(flujoTotal) && !isNaN(solidosBrix) && valBrixStr !== 'F.O.' && valBrixStr !== 'LAVADO') {
                let produccionTeorica = flujoTotal * (solidosBrix / 100) * 60 * 0.85;
                teoricosInput.value = produccionTeorica.toFixed(2);
            } else {
                teoricosInput.value = '';
            }
            calcularEficiencia(); 
        }
    }

    // =======================================================
    // AUTO-CALCULAR FLUJOS DE SECADORES Y TEÓRICOS
    // =======================================================
    function sumarFlujoVotators() {
        const getRaw = (id) => {
            let el = document.getElementById(id);
            return el ? (el.value || '').trim().toUpperCase() : '';
        };
        const getNum = (val) => {
            return (val === 'F.O.' || val === 'LAVADO' || val === '') ? 0 : (parseFloat(val) || 0);
        };

        let raw1 = getRaw('input_flujo_v1'); let v1 = getNum(raw1);
        let raw2 = getRaw('input_flujo_v2'); let v2 = getNum(raw2);
        let raw3 = getRaw('input_flujo_v3'); let v3 = getNum(raw3);
        let raw4 = getRaw('input_flujo_v4'); let v4 = getNum(raw4);
        let raw5 = getRaw('input_flujo_v5'); let v5 = getNum(raw5);
        let raw6 = getRaw('input_flujo_v6'); let v6 = getNum(raw6);

        let total = v1 + v2 + v3 + v4 + v5 + v6;
        const totalInput = document.getElementById('flujo_votators_total');
        if (totalInput) totalInput.value = total > 0 ? total.toFixed(2) : '';

        let s1 = document.getElementById('flujo_secado_1');
        let s2 = document.getElementById('flujo_secado_2');
        let s3 = document.getElementById('flujo_secado_3');
        let s4 = document.getElementById('flujo_secado_4');

        const resolverFlujoSecador = (rA, rB, sumVal) => {
            if (rA === 'F.O.' || (rB && rB === 'F.O.')) return 'F.O.';
            if (rA === 'LAVADO' || (rB && rB === 'LAVADO')) return 'LAVADO';
            return sumVal > 0 ? sumVal.toFixed(2) : '';
        };

        if(s1 && (!document.getElementById('estado_fo_1') || !document.getElementById('estado_fo_1').checked)) { 
            s1.value = resolverFlujoSecador(raw1, raw2, v1 + v2);
            aplicarColorTiempoReal(s1); 
        }
        if(s2 && (!document.getElementById('estado_fo_2') || !document.getElementById('estado_fo_2').checked)) { 
            s2.value = resolverFlujoSecador(raw3, raw4, v3 + v4);
            aplicarColorTiempoReal(s2); 
        }
        if(s3 && (!document.getElementById('estado_fo_3') || !document.getElementById('estado_fo_3').checked)) { 
            s3.value = resolverFlujoSecador(raw5, null, v5);
            aplicarColorTiempoReal(s3); 
        }
        if(s4 && (!document.getElementById('estado_fo_4') || !document.getElementById('estado_fo_4').checked)) { 
            s4.value = resolverFlujoSecador(raw6, null, v6);
            aplicarColorTiempoReal(s4); 
        }

        calcularTeoricos();
    }

    // =======================================================
    // AUTO-COPIAR VELOCIDAD A HZ DE SECADORES
    // =======================================================
    function copiarVelocidadHZ() {
        for(let i=1; i<=4; i++) {
            let hz = document.getElementById('hz_secado_' + i);
            let vel = document.getElementById('input_vel_t' + i);
            
            if(hz && vel && (!document.getElementById('estado_fo_' + i) || !document.getElementById('estado_fo_' + i).checked)) {
                let vVal = (vel.value || '').trim().toUpperCase();
                if ((vVal === 'F.O.' || vVal === 'LAVADO') && hz.tagName === 'INPUT' && hz.type === 'number') {
                    hz.type = 'text'; 
                }
                hz.value = vel.value; 
                aplicarColorTiempoReal(hz);
            }
        }
    }

    // =======================================================
    // AUTO-COPIAR HUMEDAD DE TÚNEL A H. ÚLTIMA DE SECADOR
    // =======================================================
    function copiarHumedadUltima() {
        for(let i=1; i<=4; i++) {
            let h_ultima = document.getElementById('hum_ultima_' + i);
            let hum_tunel = document.getElementById('input_hum_t' + i);
            
            if(h_ultima && hum_tunel && (!document.getElementById('estado_fo_' + i) || !document.getElementById('estado_fo_' + i).checked)) {
                let hVal = (hum_tunel.value || '').trim().toUpperCase();
                if ((hVal === 'F.O.' || hVal === 'LAVADO') && h_ultima.tagName === 'INPUT' && h_ultima.type === 'number') {
                    h_ultima.type = 'text'; 
                }
                h_ultima.value = hum_tunel.value; 
                aplicarColorTiempoReal(h_ultima);
            }
        }
    }

    // =======================================================
    // CÁLCULO DE EFICIENCIA POR REGLA DE TRES
    // =======================================================
    function calcularEficiencia() {
        const kgRealesInput = document.getElementById('cap_kg_reales');
        const teoricosInput = document.getElementById('cap_kg_teoricos');
        const eficienciaInput = document.getElementById('cap_eficiencia');

        if (kgRealesInput && eficienciaInput && teoricosInput) {
            const reales = parseFloat(kgRealesInput.value);
            const objetivoTeorico = parseFloat(teoricosInput.value);

            if (!isNaN(reales) && reales > 0 && !isNaN(objetivoTeorico) && objetivoTeorico > 0) {
                const eficiencia = (reales / objetivoTeorico) * 100;
                eficienciaInput.value = eficiencia.toFixed(2) + '%';
            } else {
                eficienciaInput.value = '';
            }
        }
    }

    // =======================================================
    // ACTUALIZAR BOTÓN LATERAL DERECHO [F.O. / LAV]
    // =======================================================
    function actualizarBotonLateral(idInput, estado) {
        const campo = document.getElementById(idInput);
        if (!campo) return;
        const parent = campo.closest('.input-group');
        if (!parent) return;
        const btn = parent.querySelector('.input-group-text-fo');
        if (!btn) return;

        let est = (estado || '').trim().toUpperCase();

        if (est === 'LAVADO') {
            btn.textContent = 'LAV';
            btn.classList.remove('btn-lateral-fo');
            btn.classList.add('btn-lateral-lavado');
            btn.title = 'Estado: LAVADO (Clic para Normal)';
        } else if (est === 'F.O.') {
            btn.textContent = 'F.O.';
            btn.classList.remove('btn-lateral-lavado');
            btn.classList.add('btn-lateral-fo');
            btn.title = 'Estado: F.O. (Clic para LAVADO)';
        } else {
            btn.textContent = 'F.O.';
            btn.classList.remove('btn-lateral-fo', 'btn-lateral-lavado');
            btn.title = 'Clic para alternar F.O. / LAVADO';
        }
    }

    // =======================================================
    // ALTERNAR ESTADO INDIVIDUAL: Normal -> F.O. -> LAVADO -> Normal
    // =======================================================
    function ponerFO(idInput) {
        const campo = document.getElementById(idInput);
        if (!campo) return;

        let actual = (campo.value || '').trim().toUpperCase();

        if (actual !== 'F.O.' && actual !== 'LAVADO') {
            // Pasa a F.O.
            if (campo.type === 'number') campo.type = 'text';
            campo.value = 'F.O.';
            campo.style.backgroundColor = '#fee2e2';
            campo.style.color = '#dc2626';
            campo.style.borderColor = '#fca5a5';
            campo.style.fontWeight = 'bold';
            campo.style.textAlign = 'center';
            campo.style.fontSize = '0.80rem';
            campo.style.letterSpacing = '';
            actualizarBotonLateral(idInput, 'F.O.');
        } else if (actual === 'F.O.') {
            // Pasa a LAVADO
            if (campo.type === 'number') campo.type = 'text';
            campo.value = 'LAVADO';
            campo.style.backgroundColor = '#cff4fc';
            campo.style.color = '#055160';
            campo.style.borderColor = '#9eeaf9';
            campo.style.fontWeight = 'bold';
            campo.style.textAlign = 'center';
            campo.style.fontSize = '0.72rem';
            campo.style.letterSpacing = '-0.3px';
            actualizarBotonLateral(idInput, 'LAVADO');
        } else {
            // Pasa a Normal / Vacío
            if (campo.id !== 'cap_kg_teoricos') campo.type = 'number';
            campo.value = '';
            campo.style.backgroundColor = '';
            campo.style.color = '';
            campo.style.borderColor = '';
            campo.style.fontWeight = '';
            campo.style.textAlign = '';
            campo.style.fontSize = '';
            campo.style.letterSpacing = '';
            actualizarBotonLateral(idInput, '');
        }

        if(idInput.includes('flujo_v')) sumarFlujoVotators();
        if(idInput.includes('vel_t')) copiarVelocidadHZ();
        if(idInput.includes('hum_t')) copiarHumedadUltima();
        if(idInput === 'input_solidos_brix') calcularTeoricos();
    }

    // =======================================================
    // ACCIÓN MAESTRA: MARCAR TODO (FO / LAVADO / LIMPIAR)
    // =======================================================
    function marcarTodo(modo) {
        const horaSelect = document.getElementById('cap_hora');
        if (!horaSelect || !horaSelect.value) {
            Swal.fire({
                icon: 'warning',
                title: 'Selecciona una hora',
                text: 'Primero selecciona la hora en la que deseas registrar los datos.',
                timer: 2500,
                showConfirmButton: false
            });
            if (horaSelect) horaSelect.focus();
            return;
        }

        const camposVotators = ['input_flujo_v1', 'input_flujo_v2', 'input_flujo_v3', 'input_flujo_v4', 'input_flujo_v5', 'input_flujo_v6', 'input_solidos_brix'];
        const camposTuneles = [
            'input_hum_t1', 'input_hum_t2', 'input_hum_t3', 'input_hum_t4',
            'input_vel_t1', 'input_vel_t2', 'input_vel_t3', 'input_vel_t4'
        ];
        const camposTodos = [...camposVotators, ...camposTuneles];

        if (modo === 'FO') {
            camposTodos.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.type = 'text';
                    el.value = 'F.O.';
                    el.style.backgroundColor = '#fee2e2';
                    el.style.color = '#dc2626';
                    el.style.borderColor = '#fca5a5';
                    el.style.fontWeight = 'bold';
                    el.style.textAlign = 'center';
                    el.style.fontSize = '0.80rem';
                    el.style.letterSpacing = '';
                }
                actualizarBotonLateral(id, 'F.O.');
            });

            // Votators activos en 0
            const votActivos = document.querySelector('input[name="votators_activos"]');
            if (votActivos) votActivos.value = '0';

            // Secadores 1 al 4 a F.O.
            for (let s = 1; s <= 4; s++) {
                const chk = document.getElementById('estado_fo_' + s);
                if (chk) chk.checked = true;
                toggleFOSecador(s, 'F.O.');
            }

            // Observación de acciones
            const acc = document.querySelector('input[name="acciones"]');
            if (acc && acc.value.trim() === '') {
                acc.value = 'LÍNEA EN FUERA DE OPERACIÓN (F.O.)';
            }

        } else if (modo === 'LAVADO') {
            camposTodos.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.type = 'text';
                    el.value = 'LAVADO';
                    el.style.backgroundColor = '#cff4fc';
                    el.style.color = '#055160';
                    el.style.borderColor = '#9eeaf9';
                    el.style.fontWeight = 'bold';
                    el.style.textAlign = 'center';
                    el.style.fontSize = '0.72rem';
                    el.style.letterSpacing = '-0.3px';
                }
                actualizarBotonLateral(id, 'LAVADO');
            });

            // Votators activos en 0
            const votActivos = document.querySelector('input[name="votators_activos"]');
            if (votActivos) votActivos.value = '0';

            // Secadores 1 al 4 a LAVADO
            for (let s = 1; s <= 4; s++) {
                const chk = document.getElementById('estado_fo_' + s);
                if (chk) chk.checked = true;
                toggleFOSecador(s, 'LAVADO');
            }

            // Observación de acciones
            const acc = document.querySelector('input[name="acciones"]');
            if (acc && acc.value.trim() === '') {
                acc.value = 'LÍNEA EN LAVADO GENERAL (CIP)';
            }

        } else if (modo === 'LIMPIAR') {
            camposTodos.forEach(id => {
                const el = document.getElementById(id);
                if (el) {
                    el.type = 'number';
                    el.value = '';
                    el.style.backgroundColor = '';
                    el.style.color = '';
                    el.style.borderColor = '';
                    el.style.fontWeight = '';
                    el.style.textAlign = '';
                    el.style.fontSize = '';
                    el.style.letterSpacing = '';
                }
                actualizarBotonLateral(id, '');
            });

            const votActivos = document.querySelector('input[name="votators_activos"]');
            if (votActivos) votActivos.value = '';

            for (let s = 1; s <= 4; s++) {
                const chk = document.getElementById('estado_fo_' + s);
                if (chk) chk.checked = false;
                toggleFOSecador(s, 'F.O.');
            }

            const acc = document.querySelector('input[name="acciones"]');
            if (acc && (acc.value === 'LÍNEA EN FUERA DE OPERACIÓN (F.O.)' || acc.value === 'LÍNEA EN LAVADO GENERAL (CIP)')) {
                acc.value = '';
            }
        }

        // Recalcular todo
        sumarFlujoVotators();
        copiarVelocidadHZ();
        copiarHumedadUltima();
        calcularTeoricos();
    }

    function aplicarColorTiempoReal(elemento) {
        let valStr = elemento.value.trim();
        let esCampoAuto = elemento.id.includes('flujo_secado') || elemento.id.includes('hz_secado') || elemento.id.includes('hum_ultima');
        
        // F.O. automático
        if (valStr === 'F.O.') {
            if(elemento.tagName === 'INPUT' && elemento.type === 'number') elemento.type = 'text';
            elemento.style.backgroundColor = '#f8d7da'; 
            elemento.style.color = '#dc3545';
            elemento.style.fontWeight = 'bold';
            elemento.style.textAlign = 'center';
            elemento.style.fontSize = '0.80rem';
            elemento.style.letterSpacing = '';
            return;
        }

        // LAVADO automático
        if (valStr === 'LAVADO') {
            if(elemento.tagName === 'INPUT' && elemento.type === 'number') elemento.type = 'text';
            elemento.style.backgroundColor = '#cff4fc'; 
            elemento.style.color = '#055160';
            elemento.style.fontWeight = 'bold';
            elemento.style.textAlign = 'center';
            elemento.style.fontSize = '0.72rem';
            elemento.style.letterSpacing = '-0.3px';
            return;
        }

        if (valStr === '' || (elemento.readOnly && !esCampoAuto)) {
            elemento.style.backgroundColor = '#f8f9fa'; 
            elemento.style.color = '#2c3e50';
            elemento.style.fontWeight = '600';
            return;
        }

        let val = parseFloat(valStr);
        let idCompleto = elemento.id; 
        let secador = parseInt(idCompleto.slice(-1)); 
        
        let colorVerde = '#198754';
        let colorAmarillo = '#ffc107';
        let colorRojo = '#dc3545';
        
        let bgColor = '';
        let textColor = '#ffffff'; 

        if (idCompleto.includes('altura_galleta')) {
            if (val >= 6.5 && val <= 7.5) bgColor = colorVerde;
            else if (val >= 6.0 && val < 6.5) { bgColor = colorAmarillo; textColor = '#000'; }
            else bgColor = colorRojo;
        } 
        else if (idCompleto.includes('flujo_secado')) {
            if (secador === 1) {
                if (val >= 26 && val <= 28) bgColor = colorVerde;
                else if (val >= 24 && val < 26) { bgColor = colorAmarillo; textColor = '#000'; }
                else bgColor = colorRojo;
            } else if (secador === 2) {
                if (val === 24) bgColor = colorVerde;
                else if (val >= 22 && val < 24) { bgColor = colorAmarillo; textColor = '#000'; }
                else bgColor = colorRojo;
            } else {
                if (val >= 10 && val <= 13) bgColor = colorVerde;
                else if (val > 13 && val <= 16) { bgColor = colorAmarillo; textColor = '#000'; }
                else bgColor = colorRojo;
            }
        }
        else if (idCompleto.includes('hz_secado')) {
            if (secador === 1) {
                if (val >= 45 && val <= 52) bgColor = colorVerde;
                else bgColor = colorRojo;
            } else if (secador === 2) {
                if (val === 52.8) bgColor = colorVerde;
                else bgColor = colorRojo;
            } else {
                if (val > 16 && val <= 18) bgColor = colorVerde;
                else if (val >= 14 && val <= 16) { bgColor = colorAmarillo; textColor = '#000'; }
                else bgColor = colorRojo;
            }
        }
        else if (idCompleto.includes('hum_penultima')) {
            if (secador === 1) {
                if (val < 12) bgColor = colorVerde;
                else if (val >= 12 && val <= 15) { bgColor = colorAmarillo; textColor = '#000'; }
                else bgColor = colorRojo;
            } else { 
                if (val < 10) bgColor = colorVerde;
                else if (val >= 10 && val <= 12) { bgColor = colorAmarillo; textColor = '#000'; }
                else bgColor = colorRojo;
            }
        }
        else if (idCompleto.includes('hum_ultima')) {
            if (secador === 1 || secador === 2) {
                if (val < 12) bgColor = colorVerde;
                else if (val >= 12 && val <= 15) { bgColor = colorAmarillo; textColor = '#000'; }
                else bgColor = colorRojo;
            } else { 
                if (val < 10) bgColor = colorVerde;
                else if (val >= 10 && val <= 12) { bgColor = colorAmarillo; textColor = '#000'; }
                else bgColor = colorRojo;
            }
        }
        else if (idCompleto.includes('rc9')) {
            if (secador === 2) {
                if (val < 10) bgColor = colorVerde;
                else if (val >= 10 && val <= 12) { bgColor = colorAmarillo; textColor = '#000'; }
                else bgColor = colorRojo;
            }
        }
        else if (idCompleto.includes('hum_relativa_cam5')) {
            if (val < 36) bgColor = colorVerde;
            else if (val >= 36 && val <= 37) { bgColor = colorAmarillo; textColor = '#000'; }
            else bgColor = colorRojo;
        }
        else if (idCompleto.includes('textura_secado')) {
            let txt = valStr.toLowerCase();
            if (txt === 'firme' || txt === 'buena') bgColor = colorVerde;
            else if (txt === 'plástica' || txt === 'plastica') { bgColor = colorAmarillo; textColor = '#000'; }
            else if (txt === 'húmeda' || txt === 'humeda' || txt === 'quebradiza') bgColor = colorRojo;
            else if (txt === 'lavado') { bgColor = '#cff4fc'; textColor = '#055160'; }
            else if (txt === 'f.o.') { bgColor = '#f8d7da'; textColor = '#dc3545'; }
        }

        if (bgColor !== '') {
            elemento.style.backgroundColor = bgColor;
            elemento.style.color = textColor;
            elemento.style.fontWeight = '900';
        } else {
            elemento.style.backgroundColor = '#f8f9fa'; 
            elemento.style.color = '#2c3e50';
        }
    }

    function toggleFOSecador(s, estado = 'F.O.') {
        const isFO = document.getElementById('estado_fo_' + s).checked;
        const campos = ['flujo_secado_', 'hz_secado_', 'rc9_', 'hum_penultima_', 'hum_ultima_', 'altura_galleta_', 'hum_relativa_cam5_', 'textura_secado_'];
        
        campos.forEach(prefix => {
            const el = document.getElementById(prefix + s);
            if (el) {
                if (isFO) {
                    if(el.tagName === 'INPUT') el.type = 'text'; 
                    el.value = estado; 
                    
                    if (estado === 'LAVADO') {
                        el.style.backgroundColor = '#cff4fc'; 
                        el.style.color = '#055160'; 
                        el.style.borderColor = '#9eeaf9';
                        el.style.textAlign = 'center';
                        el.style.fontSize = '0.72rem';
                        el.style.letterSpacing = '-0.3px';
                    } else {
                        el.style.backgroundColor = '#f8d7da'; 
                        el.style.color = '#dc3545'; 
                        el.style.borderColor = '#fca5a5';
                        el.style.textAlign = 'center';
                        el.style.fontSize = '0.80rem';
                        el.style.letterSpacing = '';
                    }
                    el.style.fontWeight = 'bold';

                    if (prefix !== 'flujo_secado_' && prefix !== 'hz_secado_' && prefix !== 'hum_ultima_') {
                        el.readOnly = true; 
                    }
                } else {
                    if (prefix !== 'flujo_secado_' && prefix !== 'hz_secado_' && prefix !== 'hum_ultima_') {
                        el.readOnly = false; 
                    }
                    if(el.tagName === 'INPUT' && prefix !== 'textura_secado_') el.type = 'number'; 
                    if (el.value === 'F.O.' || el.value === 'LAVADO') el.value = ''; 
                    
                    el.style.backgroundColor = '#f8f9fa';
                    el.style.color = '#2c3e50';
                    el.style.borderColor = '';
                    el.style.fontWeight = '600';
                    el.style.textAlign = 'center';
                    el.style.fontSize = '';
                    el.style.letterSpacing = '';
                    
                    if (prefix === 'flujo_secado_') sumarFlujoVotators();
                    else if (prefix === 'hz_secado_') copiarVelocidadHZ();
                    else if (prefix === 'hum_ultima_') copiarHumedadUltima();
                    else aplicarColorTiempoReal(el); 
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        const formCaptura = document.getElementById('form-captura-manual');
        if(formCaptura) {
            formCaptura.addEventListener('submit', function() {
                const btn = document.getElementById('btn-guardar-hora');
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Guardando...';
                btn.disabled = true;
            });
            formCaptura.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.keyCode === 13) {
                    const activeElement = document.activeElement;
                    if (activeElement.tagName === 'BUTTON' && activeElement.type === 'submit') return;
                    e.preventDefault(); 
                    const focusableElements = Array.from(formCaptura.querySelectorAll('input:not([type="hidden"]), select, button, textarea')).filter(el => !el.disabled && !el.readOnly && el.offsetParent !== null);
                    const currentIndex = focusableElements.indexOf(activeElement);
                    const nextIndex = (currentIndex + 1) % focusableElements.length;
                    focusableElements[nextIndex].focus();
                }
            });
        }

        const inputsSecadores = document.querySelectorAll('.input-secador, .select-secador');
        inputsSecadores.forEach(input => {
            input.addEventListener('input', function() { aplicarColorTiempoReal(this); });
            input.addEventListener('change', function() { aplicarColorTiempoReal(this); });
        });

        const datosGuardados = <?= json_encode($manual_data) ?>;
        const formInputs = Array.from(document.querySelectorAll('#form-captura-manual input, #form-captura-manual select, #form-captura-manual textarea, #form-captura-manual button'));
        const botonGuardar = document.getElementById('btn-guardar-hora');
        const mensajeBloqueo = document.getElementById('mensaje-bloqueo');
        const selectHora = document.getElementById('cap_hora');

        window.gestionarEstadoFormulario = function(horaSeleccionada) {
            const datosDeLaHora = datosGuardados[horaSeleccionada];
            
            formInputs.forEach(el => {
                if (el.type !== 'hidden' && el.id !== 'cap_hora' && !el.type.includes('submit')) {
                    if (el.type === 'checkbox') el.checked = false;
                    else el.value = '';
                    
                    if (el.id !== 'cap_eficiencia' && el.id !== 'flujo_votators_total' && el.id !== 'cap_kg_teoricos') el.readOnly = false;
                    if (el.id && (el.id.includes('flujo_secado') || el.id.includes('hz_secado') || el.id.includes('hum_ultima'))) el.readOnly = true; 
                    
                    if (el.tagName === 'BUTTON') el.disabled = false;
                    
                    if (el.tagName === 'INPUT' && el.id && (el.id.startsWith('input_') || el.id.includes('secado') || el.id.includes('hum_') || el.id.includes('rc9_') || el.id.includes('altura_'))) {
                        if(el.id !== 'cap_kg_teoricos') el.type = 'number';
                    }
                }
            });
            document.getElementById('cap_eficiencia').value = '';
            document.getElementById('flujo_votators_total').value = '';
            document.getElementById('cap_kg_teoricos').value = '';

            for(let s=1; s<=4; s++) toggleFOSecador(s);

            const idsBotonesLaterales = [
                'input_flujo_v1', 'input_flujo_v2', 'input_flujo_v3', 'input_flujo_v4', 'input_flujo_v5', 'input_flujo_v6', 'input_solidos_brix',
                'input_hum_t1', 'input_hum_t2', 'input_hum_t3', 'input_hum_t4',
                'input_vel_t1', 'input_vel_t2', 'input_vel_t3', 'input_vel_t4'
            ];
            idsBotonesLaterales.forEach(id => actualizarBotonLateral(id, ''));

            inputsSecadores.forEach(input => aplicarColorTiempoReal(input));

            if (datosDeLaHora) {
                mensajeBloqueo.style.display = 'block';
                botonGuardar.disabled = false;
                botonGuardar.innerHTML = '<i class="bi bi-save-fill me-2"></i> ACTUALIZAR REGISTRO';
                botonGuardar.classList.remove('btn-primary');
                botonGuardar.classList.add('btn-warning');

                formInputs.forEach(el => {
                    if (el.name && datosDeLaHora[el.name] !== undefined) {
                        el.value = datosDeLaHora[el.name];
                        if (el.value === 'F.O.') {
                            if(el.tagName === 'INPUT' && el.id !== 'cap_kg_teoricos') el.type = 'text';
                            el.style.backgroundColor = '#fee2e2';
                            el.style.color = '#dc2626';
                            el.style.borderColor = '#fca5a5';
                            el.style.textAlign = 'center';
                            el.style.fontSize = '0.80rem';
                            el.style.letterSpacing = '';
                            actualizarBotonLateral(el.id, 'F.O.');
                        } else if (el.value === 'LAVADO') {
                            if(el.tagName === 'INPUT' && el.id !== 'cap_kg_teoricos') el.type = 'text';
                            el.style.backgroundColor = '#cff4fc';
                            el.style.color = '#055160';
                            el.style.borderColor = '#9eeaf9';
                            el.style.textAlign = 'center';
                            el.style.fontSize = '0.72rem';
                            el.style.letterSpacing = '-0.3px';
                            actualizarBotonLateral(el.id, 'LAVADO');
                        } else if (el.id) {
                            actualizarBotonLateral(el.id, '');
                        }
                    }
                });

                if (datosDeLaHora.secadores) {
                    for(let s=1; s<=4; s++) {
                        if (datosDeLaHora.secadores[s]) {
                            let ds = datosDeLaHora.secadores[s];
                            
                            if(document.getElementById('estado_fo_'+s)) {
                                document.getElementById('estado_fo_'+s).checked = (ds.estado_fo == 1);
                                let estSec = (ds.textura === 'LAVADO') ? 'LAVADO' : 'F.O.';
                                toggleFOSecador(s, estSec);
                            }
                            
                            if (ds.estado_fo != 1) {
                                let fields = ['flujo_secado_', 'hz_secado_', 'rc9_', 'hum_penultima_', 'hum_ultima_', 'altura_galleta_', 'hum_relativa_cam5_', 'textura_secado_'];
                                fields.forEach(f => {
                                    let element = document.getElementById(f+s);
                                    let fieldName = f.replace('_secado_', '').replace('_'+s, ''); 
                                    if(f === 'flujo_secado_') fieldName = 'flujo';
                                    if(f === 'hz_secado_') fieldName = 'hz';
                                    if(f === 'textura_secado_') fieldName = 'textura';

                                    if(element && ds[fieldName] !== undefined) {
                                        if(element.tagName === 'INPUT') element.type = 'text'; 
                                        element.value = ds[fieldName];
                                        aplicarColorTiempoReal(element); 
                                    }
                                });
                            }
                        }
                    }
                }

                // Aseguramos que la matriz de abajo se sincronice con todo lo que cargó de la BD
                sumarFlujoVotators();
                copiarVelocidadHZ();
                copiarHumedadUltima();

            } else {
                mensajeBloqueo.style.display = 'none';
                botonGuardar.disabled = false;
                botonGuardar.innerHTML = '<i class="bi bi-save-fill me-2"></i> GUARDAR REGISTRO DE HORA';
                botonGuardar.classList.add('btn-primary');
                botonGuardar.classList.remove('btn-warning');
            }
        };

        if (selectHora.value) gestionarEstadoFormulario(selectHora.value);
        document.getElementById('fecha').addEventListener('change', function() {
            setTimeout(() => { if (selectHora.value) gestionarEstadoFormulario(selectHora.value); }, 500); 
        });

        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const hora_op = urlParams.get('hora_op');

        if (status) {
            let title, text, icon;
            if (status === 'success') {
                title = '¡Guardado!'; text = `El registro para la hora ${hora_op} se guardó correctamente.`; icon = 'success';
            } else if (status === 'error') {
                title = 'Error'; text = `Hubo un problema al guardar el registro para la hora ${hora_op}.`; icon = 'error';
            }
            Swal.fire({ title: title, text: text, icon: icon, timer: 3000, timerProgressBar: true, showConfirmButton: false });
            window.history.replaceState({}, document.title, window.location.pathname + `?fecha=${urlParams.get('fecha')}&turno=${urlParams.get('turno')}`);
        }
    });
</script>

<script>
    // =======================================================
    // REFRESCO INTELIGENTE CADA 5 MINUTOS (300,000 ms)
    // =======================================================
    let tiempoRefresco;                 

    function iniciarTemporizador() {
        // Limpiamos el temporizador anterior
        clearTimeout(tiempoRefresco);
        
        // Configuramos el nuevo temporizador a 5 minutos (300,000 ms)
        tiempoRefresco = setTimeout(function() {
            // Recarga la página conservando los parámetros de la URL (fecha y turno)
            window.location.reload();
        }, 300000); 
    }

    // Iniciamos el contador en cuanto carga la página (o después de un guardado)
    iniciarTemporizador();

    // MAGIA: Si el operador está usando la página, reiniciamos los 5 minutos 
    // para evitar que se le recargue la página mientras captura datos.
    document.addEventListener('mousemove', iniciarTemporizador);
    document.addEventListener('keypress', iniciarTemporizador);
    document.addEventListener('click', iniciarTemporizador);
    document.addEventListener('scroll', iniciarTemporizador);
</script>

<?php include 'includes/footer.php'; ?>