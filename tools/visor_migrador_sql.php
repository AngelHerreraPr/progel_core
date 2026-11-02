<?php
session_start();

if (!isset($_SESSION['nomina'])) {
    header("Location: ../login.php");
    exit();
}

if (strtoupper(trim($_SESSION['rol'] ?? '')) !== 'ADMIN') {
    http_response_code(403);
    die("<div style='font-family:sans-serif;text-align:center;margin-top:80px;'><h2>🔒 Acceso Restringido</h2><p>Solo el <b>Administrador (ADMIN)</b> puede acceder a Diagnóstico y Carga de SQL.</p><a href='../index.php' style='display:inline-block;margin-top:15px;padding:10px 20px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;'>Volver al Inicio</a></div>");
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/db_v2.php';

$mensaje = null;
$tipoMensaje = 'info';
$resumenCarga = null;

$carpetasBusqueda = [
    'Descargas' => 'C:\\Users\\Aherrera\\Downloads',
    'Escritorio' => 'C:\\Users\\Aherrera\\Desktop',
    'Backups Sistema' => __DIR__ . '/../backups'
];

$archivosDetectados = [];
foreach ($carpetasBusqueda as $origen => $ruta) {
    if (is_dir($ruta)) {
        $archivos = glob($ruta . '/*.sql');
        if ($archivos) {
            foreach ($archivos as $arch) {
                $archivosDetectados[] = [
                    'origen' => $origen,
                    'ruta' => $arch,
                    'nombre' => basename($arch),
                    'tamano_mb' => round(filesize($arch) / 1024 / 1024, 2),
                    'fecha' => date('d/m/Y H:i', filemtime($arch))
                ];
            }
        }
    }
}

// Ordenar archivos por fecha más reciente primero
usort($archivosDetectados, function ($a, $b) {
    return filemtime($b['ruta']) - filemtime($a['ruta']);
});

// Acción de Procesar Archivo SQL o Pegado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion_procesar'])) {
    $archivoAProcesar = null;
    $archivoTemporal = false;

    if (!empty($_POST['archivo_seleccionado']) && file_exists($_POST['archivo_seleccionado'])) {
        $archivoAProcesar = $_POST['archivo_seleccionado'];
    } elseif (isset($_FILES['archivo_sql']) && $_FILES['archivo_sql']['error'] === UPLOAD_ERR_OK) {
        $archivoAProcesar = $_FILES['archivo_sql']['tmp_name'];
    } elseif (!empty($_POST['sql_pegado'])) {
        $tmpPath = sys_get_temp_dir() . '/progel_sql_pegado_' . time() . '.sql';
        file_put_contents($tmpPath, $_POST['sql_pegado']);
        $archivoAProcesar = $tmpPath;
        $archivoTemporal = true;
    }

    if ($archivoAProcesar) {
        try {
            // 1. Ejecutar importación a MySQL en la base de datos progel_core
            $mysqlBin = 'C:\\wamp64\\bin\\mysql\\mysql8.4.7\\bin\\mysql.exe';
            if (!file_exists($mysqlBin)) {
                // Intentar buscar en Wamp
                $mysqlList = glob('C:\\wamp64\\bin\\mysql\\*\\bin\\mysql.exe');
                if (!empty($mysqlList)) {
                    $mysqlBin = $mysqlList[0];
                }
            }

            // 1. Ejecutar importación a MySQL en ambas bases de datos: progel_core y progel_core
            $cmdCores = "\"$mysqlBin\" -u root -pPr0gel+2024 progel_core -e \"source " . str_replace('\\', '/', $archivoAProcesar) . "\" 2>&1";
            exec($cmdCores, $salidaMysql, $retCodeMysql);

            $cmdCore = "\"$mysqlBin\" -u root -pPr0gel+2024 progel_core -e \"source " . str_replace('\\', '/', $archivoAProcesar) . "\" 2>&1";
            exec($cmdCore, $salidaMysql2, $retCodeMysql2);

            $phpBin = 'C:\\wamp64\\bin\\php\\php8.2.29\\php.exe';
            if (!file_exists($phpBin)) {
                $phpList = glob('C:\\wamp64\\bin\\php\\*\\php.exe');
                if (!empty($phpList)) {
                    $phpBin = $phpList[0];
                }
            }

            // 1.5 Asegurar equipo 100 de Membranas en las bases de datos
            $scriptMembrana = __DIR__ . '/../scripts/insert_membranas_db.php';
            if (file_exists($scriptMembrana)) {
                exec("\"$phpBin\" \"$scriptMembrana\" 2>&1");
            }

            // 2. Ejecutar script ETL migrar_a_v2.php
            $scriptEtl = __DIR__ . '/../scripts/migrar_a_v2.php';
            $cmdEtl = "\"$phpBin\" \"$scriptEtl\" 2>&1";
            exec($cmdEtl, $salidaEtl, $retCodeEtl);

            $mensaje = "¡Proceso completado con éxito! Los datos fueron importados y transformados a Progel_coreV2.";
            $tipoMensaje = 'success';
            $resumenCarga = implode("\n", $salidaEtl);

            if ($archivoTemporal && file_exists($archivoAProcesar)) {
                unlink($archivoAProcesar);
            }
        } catch (Exception $e) {
            $mensaje = "Error al procesar: " . $e->getMessage();
            $tipoMensaje = 'danger';
        }
    } else {
        $mensaje = "Por favor selecciona un archivo SQL, súbelo o pega el código SQL.";
        $tipoMensaje = 'warning';
    }
}

// Estadísticas actuales de Progel_coreV2
$stats = [];
$tablasV2 = [
    'bitacora_secadores' => 'Secadores',
    'bitacora_concentradores' => 'Concentradores',
    'bitacora_chillers_votator' => 'Chillers Votator',
    'bitacora_chillers_normales' => 'Chillers Normales',
    'bitacora_compresores' => 'Compresores',
    'produccion_reporte_maestro' => 'Sábana de Producción'
];

$fechaHoy = date('Y-m-d');
$fechaAyer = date('Y-m-d', strtotime('-1 day'));

foreach ($tablasV2 as $tbl => $etiqueta) {
    $qTotal = mysqli_query($conn_v2, "SELECT COUNT(*), MIN(fecha), MAX(fecha) FROM `$tbl`");
    $rTotal = $qTotal ? mysqli_fetch_row($qTotal) : [0, '-', '-'];

    $qAyer = mysqli_query($conn_v2, "SELECT COUNT(*) FROM `$tbl` WHERE fecha = '$fechaAyer'");
    $rAyer = $qAyer ? mysqli_fetch_row($qAyer)[0] : 0;

    $qHoy = mysqli_query($conn_v2, "SELECT COUNT(*) FROM `$tbl` WHERE fecha = '$fechaHoy'");
    $rHoy = $qHoy ? mysqli_fetch_row($qHoy)[0] : 0;

    $stats[$tbl] = [
        'nombre' => $etiqueta,
        'total' => $rTotal[0],
        'min_fecha' => $rTotal[1],
        'max_fecha' => $rTotal[2],
        'ayer' => $rAyer,
        'hoy' => $rHoy
    ];
}

// Datos para el visor en vivo
$tablaSeleccionada = $_GET['tabla'] ?? 'bitacora_secadores';
if (!array_key_exists($tablaSeleccionada, $tablasV2)) {
    $tablaSeleccionada = 'bitacora_secadores';
}
$fechaSeleccionada = $_GET['fecha'] ?? $fechaAyer;

// Consultar registros en vivo para la tabla y fecha seleccionada
$sqlVisor = "SELECT * FROM `$tablaSeleccionada` WHERE fecha = '$fechaSeleccionada' ORDER BY hora DESC, id DESC LIMIT 50";
$resVisor = mysqli_query($conn_v2, $sqlVisor);
$filasVisor = [];
$columnasVisor = [];
if ($resVisor && mysqli_num_rows($resVisor) > 0) {
    while ($row = mysqli_fetch_assoc($resVisor)) {
        if (empty($columnasVisor)) {
            $columnasVisor = array_keys($row);
        }
        $filasVisor[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Visor y Diagnóstico SQL | PROGEL CORE</title>
    <link href="../assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .navbar-progel { background-color: #0b3d91; }
        .card-stat { border-radius: 10px; border: none; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: transform 0.2s; }
        .card-stat:hover { transform: translateY(-3px); }
        .badge-ayer { background-color: #fd7e14; color: #fff; }
        .badge-hoy { background-color: #198754; color: #fff; }
        .table-responsive { max-height: 480px; overflow-y: auto; }
        .tab-btn { border-radius: 8px 8px 0 0; font-weight: 600; }
        .log-box { background-color: #1e1e1e; color: #39ff14; font-family: 'Courier New', monospace; font-size: 0.85rem; padding: 15px; border-radius: 8px; max-height: 250px; overflow-y: auto; }
    </style>
</head>
<body>

<nav class="navbar navbar-dark navbar-progel shadow-sm py-2 mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand d-flex align-items-center" href="../index.php">
            <i class="bi bi-database-check me-2 fs-4 text-warning"></i>
            <span class="fw-bold">PROGEL CORE</span>&nbsp;<span class="fw-light">| Diagnóstico y Visor SQL</span>
        </a>
        <div class="d-flex gap-2">
            <a href="../reporte_general.php" class="btn btn-outline-light btn-sm"><i class="bi bi-file-earmark-bar-graph"></i> Reporte General</a>
            <a href="../index.php" class="btn btn-light btn-sm text-primary fw-bold"><i class="bi bi-house-door"></i> Inicio</a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4">

    <?php if ($mensaje): ?>
        <div class="alert alert-<?= $tipoMensaje ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi <?= $tipoMensaje === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill' ?> me-2"></i>
            <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($resumenCarga): ?>
        <div class="card shadow-sm mb-4 border-success">
            <div class="card-header bg-success text-white fw-bold">
                <i class="bi bi-terminal-fill me-2"></i> Registro Detallado de la Carga
            </div>
            <div class="card-body p-0">
                <pre class="log-box m-0"><?= htmlspecialchars($resumenCarga) ?></pre>
            </div>
        </div>
    <?php endif; ?>

    <!-- TARJETAS DE ESTADO ACTUAL EN BASE DE DATOS PROGEL_COREV2 -->
    <div class="row g-3 mb-4">
        <div class="col-12">
            <h5 class="fw-bold text-secondary mb-1">
                <i class="bi bi-speedometer2 text-primary me-2"></i> Estatus Actual de Datos en Progel_coreV2
            </h5>
            <small class="text-muted">Muestra cuántos registros están guardados en las tablas limpias para ayer y hoy.</small>
        </div>

        <?php foreach ($stats as $tbl => $st): ?>
            <div class="col-md-4 col-xl-2">
                <div class="card card-stat h-100 p-3 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark small text-truncate" title="<?= $st['nombre'] ?>"><?= $st['nombre'] ?></span>
                        <span class="badge bg-primary rounded-pill"><?= number_format($st['total']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mb-2">
                        <span>Ayer (<?= date('d/m', strtotime($fechaAyer)) ?>):</span>
                        <strong class="<?= $st['ayer'] > 0 ? 'text-success' : 'text-danger' ?>"><?= $st['ayer'] ?> filas</strong>
                    </div>
                    <div class="d-flex justify-content-between small text-muted">
                        <span>Hoy (<?= date('d/m', strtotime($fechaHoy)) ?>):</span>
                        <strong class="<?= $st['hoy'] > 0 ? 'text-success' : 'text-secondary' ?>"><?= $st['hoy'] ?> filas</strong>
                    </div>
                    <hr class="my-2">
                    <small class="text-muted" style="font-size: 0.72rem;">Último: <?= $st['max_fecha'] ?: 'Sin datos' ?></small>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- PANEL PRINCIPAL: 2 COLUMNAS (CARGA SQL A LA IZQUIERDA, VISOR EN VIVO A LA DERECHA) -->
    <div class="row g-4">
        
        <!-- COLUMNA 1: FORMULARIO DE CARGA / IMPORTADOR -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h6 class="fw-bold text-primary mb-0">
                        <i class="bi bi-cloud-arrow-up-fill me-2"></i> Importar y Cargar Archivo SQL
                    </h6>
                    <small class="text-muted">Carga un volcado de phpMyAdmin para pasarlo a Progel_coreV2</small>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        
                        <!-- Opción A: Archivos detectados en la máquina -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">
                                <i class="bi bi-folder-check text-success me-1"></i> Archivos SQL detectados en tu equipo:
                            </label>
                            <?php if (!empty($archivosDetectados)): ?>
                                <select name="archivo_seleccionado" class="form-select form-select-sm mb-1">
                                    <option value="">-- Seleccionar archivo detectado --</option>
                                    <?php foreach ($archivosDetectados as $idx => $arch): ?>
                                        <option value="<?= htmlspecialchars($arch['ruta']) ?>" <?= $idx === 0 ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($arch['nombre']) ?> (<?= $arch['tamano_mb'] ?> MB) - <?= $arch['origen'] ?> [<?= $arch['fecha'] ?>]
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted" style="font-size: 0.75rem;">Se preselecciona el más reciente (ej. de hoy).</small>
                            <?php else: ?>
                                <p class="text-muted small">No se detectaron archivos .sql en Descargas o Escritorio.</p>
                            <?php endif; ?>
                        </div>

                        <div class="text-center my-2 text-muted small fw-bold">--- O BIEN SUBE OTRO ARCHIVO ---</div>

                        <!-- Opción B: Subir Archivo Nuevo -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">
                                <i class="bi bi-upload text-primary me-1"></i> Subir un archivo .sql desde tu explorador:
                            </label>
                            <input type="file" name="archivo_sql" class="form-control form-control-sm" accept=".sql">
                        </div>

                        <!-- Opción C: Pegar texto SQL -->
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-secondary">
                                <i class="bi bi-code-square text-secondary me-1"></i> O pegar código SQL aquí:
                            </label>
                            <textarea name="sql_pegado" class="form-control" rows="3" placeholder="Pega aquí sentencias INSERT INTO bitacora_lecturas..."></textarea>
                        </div>

                        <button type="submit" name="accion_procesar" value="1" class="btn btn-primary w-100 py-2 fw-bold shadow-sm">
                            <i class="bi bi-play-circle-fill me-2"></i> Procesar y Cargar a Progel_coreV2
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- COLUMNA 2: VISOR EN VIVO DE DATOS -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="bi bi-table text-success me-2"></i> Visor de Datos Guardados
                            </h6>
                            <small class="text-muted">Revisa con exactitud qué filas existen por fecha y equipo.</small>
                        </div>
                        
                        <!-- Filtros del Visor -->
                        <form method="GET" class="d-flex gap-2 align-items-center">
                            <select name="tabla" class="form-select form-select-sm" onchange="this.form.submit()">
                                <?php foreach ($tablasV2 as $tblKey => $tblLabel): ?>
                                    <option value="<?= $tblKey ?>" <?= $tablaSeleccionada === $tblKey ? 'selected' : '' ?>>
                                        <?= $tblLabel ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <input type="date" name="fecha" value="<?= htmlspecialchars($fechaSeleccionada) ?>" class="form-control form-control-sm" onchange="this.form.submit()">

                            <a href="?tabla=<?= $tablaSeleccionada ?>&fecha=<?= $fechaAyer ?>" class="btn btn-sm btn-outline-warning" title="Ver Ayer">Ayer</a>
                            <a href="?tabla=<?= $tablaSeleccionada ?>&fecha=<?= $fechaHoy ?>" class="btn btn-sm btn-outline-success" title="Ver Hoy">Hoy</a>
                        </form>
                    </div>
                </div>

                <div class="card-body p-0">
                    <?php if (!empty($filasVisor)): ?>
                        <div class="p-2 bg-light border-bottom d-flex justify-content-between small text-muted">
                            <span>Mostrando <strong><?= count($filasVisor) ?></strong> registros para el día <strong><?= htmlspecialchars($fechaSeleccionada) ?></strong></span>
                            <span class="badge bg-success">Cargados en Progel_coreV2</span>
                        </div>
                        <div class="table-responsive p-0">
                            <table class="table table-hover table-striped table-bordered table-sm m-0 align-middle" style="font-size: 0.8rem;">
                                <thead class="table-dark sticky-top">
                                    <tr>
                                        <?php foreach ($columnasVisor as $col): ?>
                                            <th><?= htmlspecialchars($col) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filasVisor as $fila): ?>
                                        <tr>
                                            <?php foreach ($fila as $colName => $val): ?>
                                                <td class="<?= $val === null || $val === '' ? 'text-muted' : '' ?>">
                                                    <?= htmlspecialchars($val ?? '-') ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-secondary opacity-50"></i>
                            <h6>No se encontraron registros para la fecha <strong><?= htmlspecialchars($fechaSeleccionada) ?></strong> en <strong><?= htmlspecialchars($tablasV2[$tablaSeleccionada]) ?></strong>.</h6>
                            <p class="small mb-0">Usa los botones superiores para cambiar de fecha o revisa la fecha más reciente con el formulario de la izquierda.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
