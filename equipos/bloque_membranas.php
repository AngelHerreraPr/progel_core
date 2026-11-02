<?php
/**
 * equipos/bloque_membranas.php
 * Vista de Captura del Sistema de Membranas de Ultrafiltración (Zona Gris)
 * 13 Paquetes distribuidos en 4 Etapas:
 *   - Etapa 1: Paq 1, Paq 2, Paq 3, Paq 4, Paq 9 (5 paquetes)
 *   - Etapa 2: Paq 5, Paq 6, Paq 7, Paq 8 (4 paquetes)
 *   - Etapa 3: Paq 10, Paq 11 (2 paquetes)
 *   - Etapa 4: Paq 12, Paq 13 (2 paquetes)
 *
 * Funcionalidad:
 *   - Horas automáticas cada hora (00:00 a 23:00).
 *   - Guardado INDEPENDIENTE por cada Etapa.
 *   - BLOQUEO DE SEGURIDAD: Si una etapa ya fue registrada en la fecha y hora seleccionada, no permite guardar más.
 *   - Enlace directo a Historial y Reporte General.
 *   - Se eliminó el badge repetitivo de "En Operación".
 */

date_default_timezone_set('America/Mazatlan');

$etapasMembranas = [
    1 => [
        'nombre' => 'Etapa 1',
        'subtitulo' => 'Filtración Primaria',
        'color' => '#0d6efd',
        'badge' => 'primary',
        'paquetes' => [1, 2, 3, 4, 9]
    ],
    2 => [
        'nombre' => 'Etapa 2',
        'subtitulo' => 'Concentración Intermedia',
        'color' => '#198754',
        'badge' => 'success',
        'paquetes' => [5, 6, 7, 8]
    ],
    3 => [
        'nombre' => 'Etapa 3',
        'subtitulo' => 'Concentración Avanzada',
        'color' => '#fd7e14',
        'badge' => 'warning',
        'paquetes' => [10, 11]
    ],
    4 => [
        'nombre' => 'Etapa 4',
        'subtitulo' => 'Concentración Final / Pulido',
        'color' => '#6f42c1',
        'badge' => 'purple',
        'paquetes' => [12, 13]
    ]
];

// Hora automática actual (cada 1 hora)
$horaActualInt = (int)date('G');
$horaActualStr = str_pad($horaActualInt, 2, '0', STR_PAD_LEFT) . ':00';

// Lista de las 24 horas del día (registro horario)
$horasBloques = [];
for ($h = 0; $h < 24; $h++) {
    $horasBloques[] = str_pad($h, 2, '0', STR_PAD_LEFT) . ':00';
}
?>

<!-- Carga de SweetAlert2 para notificaciones visuales -->
<script src="assets/vendor/sweetalert2/sweetalert2.all.min.js"></script>

<style>
    main.container {
        max-width: 98vw !important;
        width: 98vw !important;
        padding-left: 0.8rem !important;
        padding-right: 0.8rem !important;
    }
    .membranas-container {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        width: 100% !important;
    }
    .badge-purple { background-color: #6f42c1; color: #fff; }

    /* Barra de Navegación de Etapas */
    .nav-tabs-membranas {
        border-bottom: 2px solid #e2e8f0;
    }
    .nav-tabs-membranas .nav-link {
        color: #475569;
        font-weight: 700;
        border-radius: 12px 12px 0 0;
        padding: 0.85rem 1.4rem;
        border: none;
        border-bottom: 3px solid transparent;
        transition: all 0.2s ease;
    }
    .nav-tabs-membranas .nav-link:hover {
        color: #0d6efd;
        background-color: rgba(13, 110, 253, 0.04);
    }
    .nav-tabs-membranas .nav-link.active {
        color: #0d6efd;
        border-bottom: 3px solid #0d6efd;
        background-color: #ffffff;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.03);
    }

    /* Tarjetas de Paquete */
    .card-paquete {
        background: #ffffff;
        border-radius: 14px;
        border: 2px solid #e2e8f0;
        box-shadow: 0 3px 12px rgba(0,0,0,0.04);
        transition: all 0.25s ease;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    .card-paquete:hover {
        box-shadow: 0 8px 24px rgba(0,0,0,0.09);
        border-color: #cbd5e1;
    }

    /* Header del paquete según su estado */
    .card-paquete-header {
        padding: 0.85rem 1.1rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fafbfe;
        border-radius: 12px 12px 0 0;
    }

    /* Estado: EN OPERACIÓN (Normal / Verde sutil) */
    .card-paquete.paquete-operacion {
        border-left: 5px solid #198754 !important;
    }

    /* Estado: EN LAVADO (Azul) */
    .card-paquete.paquete-lavado {
        border: 2px solid #0dcaf0 !important;
        background: #f4fcfe;
    }
    .card-paquete.paquete-lavado .card-paquete-header {
        background: #0dcaf0;
        color: #000000;
    }

    /* Estado: EN MANTENIMIENTO / MMT (Amarillo / Naranja) */
    .card-paquete.paquete-mmt {
        border: 2px solid #ffc107 !important;
        background: #fffdf5;
    }
    .card-paquete.paquete-mmt .card-paquete-header {
        background: #ffc107;
        color: #000000;
    }

    /* Estado: FUERA DE OPERACIÓN / F.O. (Rojo) */
    .card-paquete.paquete-fo {
        border: 2px solid #dc3545 !important;
        background: #fff5f5;
    }
    .card-paquete.paquete-fo .card-paquete-header {
        background: #dc3545;
        color: #ffffff;
    }

    /* Campos bloqueados */
    .campo-bloqueado {
        background-color: #e9ecef !important;
        color: #6c757d !important;
        cursor: not-allowed;
    }

    /* Delta P Display */
    .delta-badge {
        font-family: 'Courier New', monospace;
        font-size: 1.15rem;
        font-weight: 800;
        padding: 4px 10px;
        border-radius: 8px;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        display: inline-block;
        min-width: 75px;
        text-align: right;
        color: #0f766e;
    }

    .input-metrica {
        font-weight: 600;
        text-align: right;
    }
    .input-metrica:focus {
        border-color: #0d6efd;
        box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
    }
    .label-param {
        font-size: 0.73rem;
        font-weight: 700;
        color: #64748b;
        margin-bottom: 3px;
    }

    /* Botón de Excepción (Lavado) */
    .btn-toggle-estado {
        font-size: 0.76rem;
        font-weight: 700;
        border-radius: 20px;
        padding: 3px 10px;
        transition: all 0.2s ease;
    }

    /* Botón Guardar Etapa */
    .btn-guardar-etapa {
        font-size: 0.95rem;
        letter-spacing: 0.3px;
        transition: all 0.2s ease;
    }
    .btn-guardar-etapa:hover:not(:disabled) {
        transform: translateY(-2px);
    }
</style>

<div class="membranas-container">

    <!-- ========================================================================= -->
    <!-- 1. ENCABEZADO Y CONTROLES GLOBALES DE FECHA / HORA AUTOMÁTICA             -->
    <!-- ========================================================================= -->
    <div class="card border-0 shadow-sm rounded-4 mb-4 bg-white">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                        <span class="badge bg-secondary px-3 py-1 rounded-pill">
                            <i class="bi bi-layers-half me-1"></i> Zona Gris
                        </span>
                        <span class="badge bg-primary px-3 py-1 rounded-pill">
                            <i class="bi bi-funnel me-1"></i> Sistema de Ultrafiltración
                        </span>
                        <span class="badge bg-success px-3 py-1 rounded-pill">
                            <i class="bi bi-clock-history me-1"></i> Toma Horaria Automática
                        </span>
                        <span class="badge bg-dark px-3 py-1 rounded-pill fw-bold">
                            <i class="bi bi-shield-lock me-1"></i> Bloqueo de Duplicados
                        </span>
                    </div>
                    <h3 class="fw-bold text-dark m-0">Captura de Membranas por Etapa</h3>
                    <p class="text-muted small m-0 mt-1">Guarda cada etapa de manera independiente cuando captures sus mediciones.</p>
                </div>

                <!-- ACCIONES RÁPIDAS: HISTORIAL, REGISTROS DEL DÍA Y REPORTE GENERAL -->
                <div class="d-flex gap-2 align-items-center flex-wrap">
                    <button type="button" class="btn btn-outline-info btn-sm fw-bold px-3 py-2 rounded-pill shadow-sm" onclick="abrirModalRegistrosDia()">
                        <i class="bi bi-table me-1"></i> Ver Registros del Día
                    </button>
                    <a href="reporte_general.php?fecha=<?= date('Y-m-d') ?>" class="btn btn-outline-primary btn-sm fw-bold px-3 py-2 rounded-pill shadow-sm">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i> Ver en Reporte General
                    </a>
                    <a href="historial.php?id=100" class="btn btn-outline-secondary btn-sm fw-bold px-3 py-2 rounded-pill shadow-sm">
                        <i class="bi bi-clock-history me-1"></i> Ver Historial
                    </a>
                </div>
            </div>

            <hr class="my-3 opacity-25">

            <!-- SELECTOR DE FECHA, HORA AUTOMÁTICA Y OPERADOR -->
            <div class="row g-3 align-items-center">
                <div class="col-md-3">
                    <label class="label-param d-block text-secondary"><i class="bi bi-calendar-event me-1"></i> Fecha</label>
                    <input type="date" id="membrana_fecha" class="form-control form-control-sm fw-bold border-secondary-subtle" value="<?= date('Y-m-d') ?>" onchange="consultarLecturasPrevias()">
                </div>
                <div class="col-md-3">
                    <label class="label-param d-block text-secondary">
                        <i class="bi bi-clock me-1"></i> Hora de Captura <span class="badge bg-success-subtle text-success small ms-1">Automática</span>
                    </label>
                    <select id="membrana_hora" class="form-select form-select-sm fw-bold border-secondary-subtle" onchange="consultarLecturasPrevias()">
                        <?php foreach ($horasBloques as $hb): ?>
                            <option value="<?= $hb ?>" <?= ($hb === $horaActualStr) ? 'selected' : '' ?>><?= $hb ?> hrs <?= ($hb === $horaActualStr) ? '(Hora Actual)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="label-param d-block text-secondary"><i class="bi bi-person-badge me-1"></i> Operador Responsable</label>
                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small fw-bold">
                        <?= htmlspecialchars($_SESSION['nombre'] ?? 'Operador') ?> (<?= htmlspecialchars($_SESSION['nomina'] ?? 'N/A') ?>)
                    </span>
                    <button type="button" class="btn btn-link btn-sm text-decoration-none text-muted ms-2" onclick="fijarHoraActual()" title="Regresar a la hora exacta actual">
                        <i class="bi bi-arrow-repeat"></i> Fijar hora actual
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- 2. PESTAÑAS INDIVIDUALES POR ETAPA + TABLERO GENERAL                     -->
    <!-- ========================================================================= -->
    <ul class="nav nav-tabs nav-tabs-membranas mb-4" id="membranaTabs" role="tablist">
        <?php foreach ($etapasMembranas as $etapaNum => $e): ?>
            <li class="nav-item">
                <button class="nav-link <?= $etapaNum === 1 ? 'active' : '' ?>" id="tab-etapa-<?= $etapaNum ?>" data-bs-toggle="tab" data-bs-target="#panel-etapa-<?= $etapaNum ?>" type="button">
                    <span class="badge bg-<?= $e['badge'] ?> rounded-pill me-1"><?= $etapaNum ?></span>
                    <?= $e['nombre'] ?> 
                    <small class="text-muted ms-1">(<?= count($e['paquetes']) ?> Paq.)</small>
                    <span id="tab-check-<?= $etapaNum ?>" class="ms-1 d-none text-success"><i class="bi bi-check-circle-fill"></i></span>
                    <span id="tab-lock-<?= $etapaNum ?>" class="ms-1 d-none text-secondary"><i class="bi bi-lock-fill"></i></span>
                </button>
            </li>
        <?php endforeach; ?>
        <li class="nav-item ms-auto">
            <button class="nav-link text-secondary" id="tab-todas" data-bs-toggle="tab" data-bs-target="#panel-todas" type="button">
                <i class="bi bi-grid-3x3-gap-fill me-1"></i> Ver Todos (13 Paquetes)
            </button>
        </li>
    </ul>

    <!-- ========================================================================= -->
    <!-- 3. CONTENIDO DE LAS PESTAÑAS (CADA ETAPA CON GUARDADO Y BLOQUEO)          -->
    <!-- ========================================================================= -->
    <div class="tab-content" id="membranasTabContent">

        <!-- PESTAÑAS INDIVIDUALES (ETAPAS 1, 2, 3 Y 4) -->
        <?php foreach ($etapasMembranas as $etapaNum => $e): ?>
            <div class="tab-pane fade <?= $etapaNum === 1 ? 'show active' : '' ?>" id="panel-etapa-<?= $etapaNum ?>" role="tabpanel">
                
                <!-- ALERTA DE BLOQUEO SI YA SE REGISTRÓ ESTA HORA -->
                <div id="alerta-bloqueada-etapa-<?= $etapaNum ?>" class="alert alert-warning py-2 px-3 small fw-bold mb-3 d-none rounded-3 border-warning">
                    <i class="bi bi-lock-fill text-dark me-2"></i>
                    <span>Esta etapa ya fue registrada para las <span class="hora-bloqueada-txt-<?= $etapaNum ?> fw-bold"></span> hrs. Por seguridad y control de turno, no se permite guardar más en este bloque horario.</span>
                </div>

                <!-- Encabezado de la etapa individual con botón de guardado -->
                <div class="card border-0 shadow-sm rounded-4 mb-4 p-3 bg-white border-start border-4" style="border-left-color: <?= $e['color'] ?> !important;">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge bg-<?= $e['badge'] ?> fs-6 px-3 py-1 rounded-pill"><?= $e['nombre'] ?></span>
                                <span id="status-etapa-badge-<?= $etapaNum ?>" class="badge bg-light text-muted border px-3 py-1 rounded-pill small">
                                    <i class="bi bi-circle me-1"></i> Sin guardar en esta hora
                                </span>
                            </div>
                            <h5 class="fw-bold text-dark mb-0"><?= $e['subtitulo'] ?></h5>
                            <small class="text-muted">Paquetes asignados: <strong><?= implode(', ', array_map(fn($p) => "Paquete $p", $e['paquetes'])) ?></strong></small>
                        </div>

                        <!-- ACCIONES RÁPIDAS Y BOTÓN DE GUARDADO INDEPENDIENTE -->
                        <div class="d-flex gap-2 align-items-center flex-wrap">
                            <div class="btn-group btn-group-sm btn-group-etapa-<?= $etapaNum ?>">
                                <button type="button" class="btn btn-outline-info fw-bold text-dark px-2" onclick="marcarEtapa(<?= $etapaNum ?>, 'LAVADO')" title="Marcar toda la etapa en Lavado">
                                    <i class="bi bi-droplet-fill"></i> Todos Lavado
                                </button>
                                <button type="button" class="btn btn-outline-warning fw-bold text-dark px-2" onclick="marcarEtapa(<?= $etapaNum ?>, 'MMT')" title="Marcar toda la etapa en Mantenimiento (MMT)">
                                    <i class="bi bi-tools"></i> Todos MMT
                                </button>
                                <button type="button" class="btn btn-outline-danger fw-bold px-2" onclick="marcarEtapa(<?= $etapaNum ?>, 'FO')" title="Marcar toda la etapa en Fuera de Operación (F.O.)">
                                    <i class="bi bi-x-circle-fill"></i> Todos F.O.
                                </button>
                                <button type="button" class="btn btn-outline-secondary fw-bold px-2" onclick="marcarEtapa(<?= $etapaNum ?>, 'OPERACION')" title="Restablecer a Operación normal">
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                            </div>

                            <!-- BOTÓN DE GUARDAR EXCLUSIVO PARA ESTA ETAPA -->
                            <button type="button" class="btn btn-success fw-bold px-4 py-2 rounded-pill shadow-sm btn-guardar-etapa" id="btn-guardar-top-<?= $etapaNum ?>" onclick="guardarEtapaIndividual(<?= $etapaNum ?>)">
                                <i class="bi bi-cloud-arrow-up-fill me-1"></i> Guardar <?= $e['nombre'] ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Grilla de Paquetes de la Etapa -->
                <div class="row g-3">
                    <?php foreach ($e['paquetes'] as $paqNum): ?>
                        <div class="col-md-6 col-xl-<?php echo ($etapaNum == 1) ? '4' : '6'; ?>">
                            <?php renderizarTarjetaPaquete($paqNum, $etapaNum, $e); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Barra de Guardado Inferior para la Etapa -->
                <div class="card border-0 shadow-sm rounded-4 mt-4 p-3 bg-white border-start border-4" style="border-left-color: <?= $e['color'] ?> !important;">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <span class="fw-bold text-dark"><i class="bi bi-check2-circle text-success me-1"></i> Finalizar captura de <?= $e['nombre'] ?></span>
                            <p class="text-muted small m-0">Guarda únicamente los paquetes correspondientes a esta etapa.</p>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <span id="status-etapa-foot-<?= $etapaNum ?>" class="text-muted small me-2"></span>
                            <button type="button" class="btn btn-success fw-bold px-4 py-2 rounded-pill shadow-sm btn-guardar-etapa" id="btn-guardar-bot-<?= $etapaNum ?>" onclick="guardarEtapaIndividual(<?= $etapaNum ?>)">
                                <i class="bi bi-save2-fill me-1"></i> Guardar <?= $e['nombre'] ?>
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        <?php endforeach; ?>

        <!-- TAB EXTRA: TABLERO GENERAL (TODOS LOS 13 PAQUETES A LA VISTA) -->
        <div class="tab-pane fade" id="panel-todas" role="tabpanel">
            <div class="alert alert-info py-2 px-3 rounded-3 mb-4 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-info-circle-fill me-2"></i> Vista general del sistema completo. Puedes guardar cada etapa con su botón individual correspondiente.</span>
                <a href="reporte_general.php?fecha=<?= date('Y-m-d') ?>" class="btn btn-sm btn-primary fw-bold rounded-pill px-3 shadow-sm">
                    <i class="bi bi-table me-1"></i> Abrir en Reporte General
                </a>
            </div>

            <?php foreach ($etapasMembranas as $etapaNum => $e): ?>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex align-items-center">
                            <span class="badge bg-<?= $e['badge'] ?> fs-6 px-3 py-1 rounded-pill me-2"><?= $e['nombre'] ?></span>
                            <h6 class="fw-bold text-dark m-0"><?= $e['subtitulo'] ?> (<?= count($e['paquetes']) ?> Paquetes)</h6>
                        </div>
                        <button type="button" class="btn btn-sm btn-success fw-bold rounded-pill px-3 shadow-sm btn-guardar-etapa" id="btn-guardar-general-<?= $etapaNum ?>" onclick="guardarEtapaIndividual(<?= $etapaNum ?>)">
                            <i class="bi bi-save2 me-1"></i> Guardar <?= $e['nombre'] ?>
                        </button>
                    </div>
                    <div class="row g-3">
                        <?php foreach ($e['paquetes'] as $paqNum): ?>
                            <div class="col-md-6 col-lg-4">
                                <?php renderizarTarjetaPaquete($paqNum, $etapaNum, $e, 'general_'); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

</div>

<?php

function renderizarTarjetaPaquete($paqNum, $etapaNum, $etapaInfo, $prefijoId = '') {
    $uid = $prefijoId . $paqNum;
?>
    <div class="card-paquete paquete-operacion" id="card-paq-<?= $uid ?>" data-estado="OPERACION" data-paquete="<?= $paqNum ?>" data-etapa="<?= $etapaNum ?>">
        
        <!-- HEADER DEL PAQUETE (Sin badge repetitivo de "En Operación") -->
        <div class="card-paquete-header">
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-dark rounded-pill px-3 py-1 fs-6">Paquete <?= $paqNum ?></span>
            </div>

            <!-- BOTONES DE EXCEPCIÓN (LAVADO / MMT / F.O.) -->
            <div class="d-flex gap-1 flex-wrap justify-content-end">
                <button type="button" class="btn btn-sm btn-outline-info btn-toggle-estado text-dark" id="btn-lav-<?= $uid ?>" onclick="toggleEstado('<?= $uid ?>', 'LAVADO')" title="En Lavado CIP">
                    <i class="bi bi-droplet-fill"></i> Lavado
                </button>
                <button type="button" class="btn btn-sm btn-outline-warning btn-toggle-estado text-dark" id="btn-mmt-<?= $uid ?>" onclick="toggleEstado('<?= $uid ?>', 'MMT')" title="En Mantenimiento">
                    <i class="bi bi-tools"></i> MMT
                </button>
                <button type="button" class="btn btn-sm btn-outline-danger btn-toggle-estado" id="btn-fo-<?= $uid ?>" onclick="toggleEstado('<?= $uid ?>', 'FO')" title="Fuera de Operación">
                    <i class="bi bi-x-circle-fill"></i> F.O.
                </button>
            </div>
        </div>

        <!-- CUERPO DE DATOS DEL PAQUETE -->
        <div class="card-body p-3 d-flex flex-column justify-content-between">

            <!-- SECCIÓN 1: PRESIONES Y DELTA AUTOMÁTICO -->
            <div class="bg-light p-2 rounded-3 border mb-3">
                <div class="text-uppercase fw-bold text-secondary mb-2" style="font-size: 0.68rem; letter-spacing: 0.5px;">
                    <i class="bi bi-speedometer text-primary me-1"></i> Control de Presión
                </div>
                <div class="row g-2 align-items-center">
                    <div class="col-4">
                        <label class="label-param d-block">Presión Entrada</label>
                        <input type="number" step="0.01" class="form-control form-control-sm input-metrica input-campo-<?= $uid ?> input-etapa-<?= $etapaNum ?>" id="p_in_<?= $uid ?>" placeholder="0.00" oninput="calcularDeltaPaquete('<?= $uid ?>')">
                    </div>
                    <div class="col-4">
                        <label class="label-param d-block">Presión Salida</label>
                        <input type="number" step="0.01" class="form-control form-control-sm input-metrica input-campo-<?= $uid ?> input-etapa-<?= $etapaNum ?>" id="p_out_<?= $uid ?>" placeholder="0.00" oninput="calcularDeltaPaquete('<?= $uid ?>')">
                    </div>
                    <div class="col-4">
                        <label class="label-param d-block text-primary">Delta de Presión</label>
                        <div class="delta-badge" id="delta_display_<?= $uid ?>" title="Presión Entrada menos Presión Salida">0.00</div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 2: CONTROL DE CONCENTRACIÓN BRIX -->
            <div class="bg-light p-2 rounded-3 border mb-3">
                <div class="text-uppercase fw-bold text-secondary mb-2" style="font-size: 0.68rem; letter-spacing: 0.5px;">
                    <i class="bi bi-droplet-half text-success me-1"></i> Control de Brix (°Bx)
                </div>
                <div class="row g-2">
                    <div class="col-4">
                        <label class="label-param d-block">Brix Entrada</label>
                        <input type="number" step="0.1" class="form-control form-control-sm input-metrica input-campo-<?= $uid ?> input-etapa-<?= $etapaNum ?>" id="brix_in_<?= $uid ?>" placeholder="0.0">
                    </div>
                    <div class="col-4">
                        <label class="label-param d-block">Brix Salida</label>
                        <input type="number" step="0.1" class="form-control form-control-sm input-metrica input-campo-<?= $uid ?> input-etapa-<?= $etapaNum ?>" id="brix_out_<?= $uid ?>" placeholder="0.0">
                    </div>
                    <div class="col-4">
                        <label class="label-param d-block">Brix Permeado</label>
                        <input type="number" step="0.1" class="form-control form-control-sm input-metrica input-campo-<?= $uid ?> input-etapa-<?= $etapaNum ?>" id="brix_perm_<?= $uid ?>" placeholder="0.0">
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 3: OBSERVACIONES Y GUARDADO POR PAQUETE -->
            <div>
                <input type="text" class="form-control form-control-sm input-campo-<?= $uid ?> input-etapa-<?= $etapaNum ?> mb-2" id="obs_<?= $uid ?>" placeholder="Observaciones del Paquete <?= $paqNum ?>..." style="font-size: 0.75rem;" oninput="sincronizarInputPaquete('<?= $uid ?>', 'obs')">
                <button type="button" class="btn btn-sm btn-success w-100 fw-bold rounded-pill shadow-sm" id="btn-guardar-paq-<?= $uid ?>" onclick="guardarPaqueteIndividual(<?= $paqNum ?>, <?= $etapaNum ?>)">
                    <i class="bi bi-save me-1"></i> Guardar Paquete <?= $paqNum ?>
                </button>
            </div>

        </div>
    </div>
<?php
}
?>

<script>
// Mapeo oficial de etapas y paquetes
const ETAPAS_CONFIG = {
    1: [1, 2, 3, 4, 9],
    2: [5, 6, 7, 8],
    3: [10, 11],
    4: [12, 13]
};

// Variables para rastrear etapas y paquetes bloqueados para la hora actual seleccionada
let etapasBloqueadas = {};
let paquetesBloqueados = {};

function fijarHoraActual() {
    const ahora = new Date();
    const horaActual = String(ahora.getHours()).padStart(2, '0') + ':00';
    const selectHora = document.getElementById('membrana_hora');
    if (selectHora) {
        selectHora.value = horaActual;
        consultarLecturasPrevias();
    }
}

// =========================================================================
// CÁLCULO AUTOMÁTICO DE DELTA P (Presión Entrada - Presión Salida)
// =========================================================================
function calcularDeltaPaquete(uid) {
    const pInInput = document.getElementById('p_in_' + uid);
    const pOutInput = document.getElementById('p_out_' + uid);
    const deltaDisplay = document.getElementById('delta_display_' + uid);

    if (!pInInput || !pOutInput || !deltaDisplay) return;

    const pIn = parseFloat(pInInput.value) || 0;
    const pOut = parseFloat(pOutInput.value) || 0;

    let delta = pIn - pOut;
    if (delta < 0) delta = 0;

    deltaDisplay.textContent = delta.toFixed(2);

    if (delta >= 1.8) {
        deltaDisplay.style.color = '#dc3545';
        deltaDisplay.style.backgroundColor = '#ffeef0';
        deltaDisplay.style.borderColor = '#dc3545';
    } else if (delta >= 1.2) {
        deltaDisplay.style.color = '#e49a32';
        deltaDisplay.style.backgroundColor = '#fffbeb';
        deltaDisplay.style.borderColor = '#e49a32';
    } else {
        deltaDisplay.style.color = '#0f766e';
        deltaDisplay.style.backgroundColor = '#f1f5f9';
        deltaDisplay.style.borderColor = '#cbd5e1';
    }

    // Sincronizar con la otra pestaña (Individual <-> General)
    const uidSinc = uid.startsWith('general_') ? uid.replace('general_', '') : 'general_' + uid;
    const pInSinc = document.getElementById('p_in_' + uidSinc);
    const pOutSinc = document.getElementById('p_out_' + uidSinc);
    const deltaSinc = document.getElementById('delta_display_' + uidSinc);
    if (pInSinc && pInSinc.value !== pInInput.value) pInSinc.value = pInInput.value;
    if (pOutSinc && pOutSinc.value !== pOutInput.value) pOutSinc.value = pOutInput.value;
    if (deltaSinc) {
        deltaSinc.textContent = deltaDisplay.textContent;
        deltaSinc.style.color = deltaDisplay.style.color;
        deltaSinc.style.backgroundColor = deltaDisplay.style.backgroundColor;
        deltaSinc.style.borderColor = deltaDisplay.style.borderColor;
    }
}

function sincronizarInputPaquete(uid, prefijo) {
    const inp = document.getElementById(prefijo + '_' + uid);
    if (!inp) return;
    const uidSinc = uid.startsWith('general_') ? uid.replace('general_', '') : 'general_' + uid;
    const inpSinc = document.getElementById(prefijo + '_' + uidSinc);
    if (inpSinc && inpSinc.value !== inp.value) {
        inpSinc.value = inp.value;
    }
}

// =========================================================================
// TOGGLE DE ESTADO INDEPENDIENTE: LAVADO, MMT, F.O.
// =========================================================================
function toggleEstado(uid, estadoObjetivo) {
    const card = document.getElementById('card-paq-' + uid);
    if (!card) return;
    const etapa = card.getAttribute('data-etapa');
    const paqNum = card.getAttribute('data-paquete');

    if (etapasBloqueadas[etapa] || paquetesBloqueados[paqNum]) {
        Swal.fire({
            icon: 'info',
            title: 'Paquete Bloqueado',
            text: 'Este paquete ya cuenta con registro guardado para este bloque horario y no se puede modificar.',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
        return;
    }

    const estadoActual = card.getAttribute('data-estado') || 'OPERACION';
    const nuevoEstado = (estadoActual === estadoObjetivo) ? 'OPERACION' : estadoObjetivo;
    aplicarEstadoPaquete(uid, nuevoEstado);

    // Sincronizar con la vista general si existe el clon
    const uidSincronizado = uid.startsWith('general_') ? uid.replace('general_', '') : 'general_' + uid;
    if (document.getElementById('card-paq-' + uidSincronizado)) {
        aplicarEstadoPaquete(uidSincronizado, nuevoEstado);
    }
}

function aplicarEstadoPaquete(uid, estado) {
    const card = document.getElementById('card-paq-' + uid);
    if (!card) return;

    if (estado === 'F.O.') estado = 'FO';
    if (!['OPERACION', 'LAVADO', 'MMT', 'FO'].includes(estado)) estado = 'OPERACION';

    card.setAttribute('data-estado', estado);

    const btnLav = document.getElementById('btn-lav-' + uid);
    const btnMmt = document.getElementById('btn-mmt-' + uid);
    const btnFo  = document.getElementById('btn-fo-' + uid);
    const campos = document.querySelectorAll('.input-campo-' + uid);
    const paqNum = card.getAttribute('data-paquete');
    const estaBloq = !!paquetesBloqueados[paqNum];

    card.classList.remove('paquete-operacion', 'paquete-lavado', 'paquete-mmt', 'paquete-fo');

    if (btnLav) {
        btnLav.classList.remove('btn-info', 'text-white');
        btnLav.classList.add('btn-outline-info', 'text-dark');
    }
    if (btnMmt) {
        btnMmt.classList.remove('btn-warning', 'text-dark', 'border-dark');
        btnMmt.classList.add('btn-outline-warning', 'text-dark');
    }
    if (btnFo) {
        btnFo.classList.remove('btn-danger', 'text-white');
        btnFo.classList.add('btn-outline-danger');
    }

    if (estado === 'LAVADO') {
        card.classList.add('paquete-lavado');
        if (btnLav) {
            btnLav.classList.remove('btn-outline-info', 'text-dark');
            btnLav.classList.add('btn-info', 'text-white');
        }
    } else if (estado === 'MMT') {
        card.classList.add('paquete-mmt');
        if (btnMmt) {
            btnMmt.classList.remove('btn-outline-warning');
            btnMmt.classList.add('btn-warning', 'text-dark', 'border-dark');
        }
    } else if (estado === 'FO') {
        card.classList.add('paquete-fo');
        if (btnFo) {
            btnFo.classList.remove('btn-outline-danger');
            btnFo.classList.add('btn-danger', 'text-white');
        }
    } else {
        card.classList.add('paquete-operacion');
        calcularDeltaPaquete(uid);
    }

    const enExcepcion = (estado !== 'OPERACION');

    if (enExcepcion && !estaBloq) {
        // Limpiar todos los datos y restablecer Delta a 0.00 cuando está en Lavado, MMT o F.O.
        campos.forEach(c => { c.value = ''; });
        const deltaDisplay = document.getElementById('delta_display_' + uid);
        if (deltaDisplay) {
            deltaDisplay.textContent = '0.00';
            deltaDisplay.style.color = '#6c757d';
            deltaDisplay.style.backgroundColor = '#e9ecef';
            deltaDisplay.style.borderColor = '#cbd5e1';
        }
    }

    campos.forEach(c => {
        if (estaBloq || enExcepcion) {
            c.classList.add('campo-bloqueado');
            c.disabled = true;
        } else {
            c.classList.remove('campo-bloqueado');
            c.disabled = false;
        }
    });
}

function marcarEtapa(etapaNum, estado) {
    if (etapasBloqueadas[etapaNum]) {
        Swal.fire({
            icon: 'info',
            title: 'Etapa Bloqueada',
            text: 'Esta etapa ya cuenta con registro para este bloque horario.',
            timer: 2000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end'
        });
        return;
    }

    if (ETAPAS_CONFIG[etapaNum]) {
        ETAPAS_CONFIG[etapaNum].forEach(paq => {
            if (!paquetesBloqueados[paq]) {
                aplicarEstadoPaquete(paq.toString(), estado);
                if (document.getElementById('card-paq-general_' + paq)) {
                    aplicarEstadoPaquete('general_' + paq, estado);
                }
            }
        });
    }
}

// =========================================================================
// GUARDADO INDIVIDUAL POR PAQUETE
// =========================================================================
async function guardarPaqueteIndividual(paqNum, etapaNum) {
    if (paquetesBloqueados[paqNum] || etapasBloqueadas[etapaNum]) {
        Swal.fire({
            icon: 'warning',
            title: 'Paquete ya guardado',
            text: `El Paquete ${paqNum} ya fue registrado para esta hora.`,
            confirmButtonText: 'Entendido'
        });
        return;
    }

    const fecha = document.getElementById('membrana_fecha').value;
    const hora = document.getElementById('membrana_hora').value;

    const uid = paqNum.toString();
    const card = document.getElementById('card-paq-' + uid);
    const estado = card ? (card.getAttribute('data-estado') || 'OPERACION') : 'OPERACION';
    const esOperacion = (estado === 'OPERACION');

    const pInVal   = esOperacion ? (document.getElementById('p_in_' + uid)?.value || document.getElementById('p_in_general_' + uid)?.value || '') : '';
    const pOutVal  = esOperacion ? (document.getElementById('p_out_' + uid)?.value || document.getElementById('p_out_general_' + uid)?.value || '') : '';
    const deltaVal = esOperacion ? (document.getElementById('delta_display_' + uid)?.textContent || '') : '';
    const bInVal   = esOperacion ? (document.getElementById('brix_in_' + uid)?.value || document.getElementById('brix_in_general_' + uid)?.value || '') : '';
    const bOutVal  = esOperacion ? (document.getElementById('brix_out_' + uid)?.value || document.getElementById('brix_out_general_' + uid)?.value || '') : '';
    const bPermVal = esOperacion ? (document.getElementById('brix_perm_' + uid)?.value || document.getElementById('brix_perm_general_' + uid)?.value || '') : '';
    const obsVal   = esOperacion ? (document.getElementById('obs_' + uid)?.value || document.getElementById('obs_general_' + uid)?.value || '') : '';

    const paquetesData = [{
        paquete_numero: paqNum,
        estado: estado,
        presion_entrada: pInVal,
        presion_salida: pOutVal,
        delta_presion: deltaVal,
        brix_entrada: bInVal,
        brix_salida: bOutVal,
        brix_permeado: bPermVal,
        observaciones: obsVal
    }];

    const btnPaq = document.getElementById('btn-guardar-paq-' + uid);
    const btnPaqGen = document.getElementById('btn-guardar-paq-general_' + uid);

    [btnPaq, btnPaqGen].forEach(b => {
        if (b) {
            b.disabled = true;
            b.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
        }
    });

    try {
        const response = await fetch('acciones/guardar_membranas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'guardar',
                etapa: etapaNum,
                fecha: fecha,
                hora: hora,
                paquetes: paquetesData
            })
        });

        const res = await response.json();

        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: `¡Paquete ${paqNum} Guardado!`,
                text: `Se registró el Paquete ${paqNum} (${estado}) para las ${hora} hrs.`,
                timer: 2200,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });
            await consultarLecturasPrevias();
        } else {
            Swal.fire({
                icon: res.bloqueado ? 'warning' : 'error',
                title: res.bloqueado ? 'Registro ya existente' : 'Error al guardar',
                text: res.message || 'No se pudo guardar el paquete.',
                confirmButtonText: 'Entendido'
            });
            await consultarLecturasPrevias();
        }
    } catch (err) {
        console.error(err);
        Swal.fire('Error de Conexión', 'Hubo un error de red al procesar el guardado.', 'error');
        await consultarLecturasPrevias();
    }
}

// =========================================================================
// GUARDADO INDEPENDIENTE POR ETAPA CON BLOQUEO ANTI-DUPLICADOS
// =========================================================================
async function guardarEtapaIndividual(etapaNum) {
    if (etapasBloqueadas[etapaNum]) {
        Swal.fire({
            icon: 'warning',
            title: 'No se puede guardar',
            text: `La Etapa ${etapaNum} ya fue registrada por completo para esta hora.`,
            confirmButtonText: 'Entendido'
        });
        return;
    }

    const paquetesNums = ETAPAS_CONFIG[etapaNum];
    if (!paquetesNums) {
        Swal.fire('Error', 'Etapa no válida.', 'error');
        return;
    }

    const fecha = document.getElementById('membrana_fecha').value;
    const hora = document.getElementById('membrana_hora').value;

    const paquetesData = [];

    paquetesNums.forEach(pNum => {
        // Ignorar los paquetes que ya fueron guardados individualmente
        if (paquetesBloqueados[pNum]) return;

        const uid = pNum.toString();
        const card = document.getElementById('card-paq-' + uid);
        const estado = card ? (card.getAttribute('data-estado') || 'OPERACION') : 'OPERACION';
        const esOperacion = (estado === 'OPERACION');

        const pInVal   = esOperacion ? (document.getElementById('p_in_' + uid)?.value || '') : '';
        const pOutVal  = esOperacion ? (document.getElementById('p_out_' + uid)?.value || '') : '';
        const deltaVal = esOperacion ? (document.getElementById('delta_display_' + uid)?.textContent || '') : '';
        const bInVal   = esOperacion ? (document.getElementById('brix_in_' + uid)?.value || '') : '';
        const bOutVal  = esOperacion ? (document.getElementById('brix_out_' + uid)?.value || '') : '';
        const bPermVal = esOperacion ? (document.getElementById('brix_perm_' + uid)?.value || '') : '';
        const obsVal   = esOperacion ? (document.getElementById('obs_' + uid)?.value || '') : '';

        paquetesData.push({
            paquete_numero: pNum,
            estado: estado,
            presion_entrada: pInVal,
            presion_salida: pOutVal,
            delta_presion: deltaVal,
            brix_entrada: bInVal,
            brix_salida: bOutVal,
            brix_permeado: bPermVal,
            observaciones: obsVal
        });
    });

    if (paquetesData.length === 0) {
        Swal.fire('Información', 'Todos los paquetes de esta etapa ya fueron guardados.', 'info');
        return;
    }

    // Indicador visual de cargando en los botones de esta etapa
    const btnTop = document.getElementById('btn-guardar-top-' + etapaNum);
    const btnBot = document.getElementById('btn-guardar-bot-' + etapaNum);
    const btnGen = document.getElementById('btn-guardar-general-' + etapaNum);

    [btnTop, btnBot, btnGen].forEach(btn => {
        if (btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';
        }
    });

    try {
        const response = await fetch('acciones/guardar_membranas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'guardar',
                etapa: etapaNum,
                fecha: fecha,
                hora: hora,
                paquetes: paquetesData
            })
        });

        const res = await response.json();

        if (res.success) {
            Swal.fire({
                icon: 'success',
                title: `¡Etapa ${etapaNum} Guardada!`,
                text: `Se registraron exitosamente los paquetes restantes de la Etapa ${etapaNum} para las ${hora} hrs.`,
                timer: 2500,
                showConfirmButton: false,
                position: 'top-end',
                toast: true
            });

            // Refrescar estado y bloquear la etapa
            await consultarLecturasPrevias();
        } else {
            Swal.fire({
                icon: res.bloqueado ? 'warning' : 'error',
                title: res.bloqueado ? 'Registro ya existente' : 'Error al guardar',
                text: res.message || 'No se pudo guardar la etapa.',
                confirmButtonText: 'Entendido'
            });
            await consultarLecturasPrevias();
        }
    } catch (err) {
        console.error(err);
        Swal.fire('Error de Conexión', 'Hubo un error de red al procesar el guardado.', 'error');
        await consultarLecturasPrevias();
    }
}

// =========================================================================
// CONSULTA DE LECTURAS PREVIAS Y GESTIÓN DE BLOQUEO POR HORA / PAQUETE
// =========================================================================
async function consultarLecturasPrevias() {
    const fecha = document.getElementById('membrana_fecha').value;
    const hora = document.getElementById('membrana_hora').value;

    etapasBloqueadas = {};
    paquetesBloqueados = {};

    try {
        const response = await fetch('acciones/guardar_membranas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                accion: 'consultar',
                fecha: fecha,
                hora: hora
            })
        });

        const res = await response.json();

        // 1. Limpiar todos los campos y resetear a estado desbloqueado inicialmente
        for (let p = 1; p <= 13; p++) {
            ['', 'general_'].forEach(pref => {
                const uid = pref + p;
                aplicarEstadoPaquete(uid, 'OPERACION');
                ['p_in_', 'p_out_', 'brix_in_', 'brix_out_', 'brix_perm_', 'obs_'].forEach(f => {
                    const el = document.getElementById(f + uid);
                    if (el) {
                        el.value = '';
                        el.disabled = false;
                        el.classList.remove('campo-bloqueado');
                    }
                });
                calcularDeltaPaquete(uid);

                const btnPaq = document.getElementById('btn-guardar-paq-' + uid);
                if (btnPaq) {
                    btnPaq.disabled = false;
                    btnPaq.className = 'btn btn-sm btn-success w-100 fw-bold rounded-pill shadow-sm';
                    btnPaq.innerHTML = `<i class="bi bi-save me-1"></i> Guardar Paquete ${p}`;
                }
            });
        }

        for (let e = 1; e <= 4; e++) {
            const tabCheck = document.getElementById('tab-check-' + e);
            const tabLock = document.getElementById('tab-lock-' + e);
            const badgeTop = document.getElementById('status-etapa-badge-' + e);
            const footText = document.getElementById('status-etapa-foot-' + e);
            const avisoBloq = document.getElementById('alerta-bloqueada-etapa-' + e);
            const btnTop = document.getElementById('btn-guardar-top-' + e);
            const btnBot = document.getElementById('btn-guardar-bot-' + e);
            const btnGen = document.getElementById('btn-guardar-general-' + e);

            if (tabCheck) tabCheck.classList.add('d-none');
            if (tabLock) tabLock.classList.add('d-none');
            if (avisoBloq) avisoBloq.classList.add('d-none');

            if (badgeTop) {
                badgeTop.className = 'badge bg-light text-muted border px-3 py-1 rounded-pill small';
                badgeTop.innerHTML = '<i class="bi bi-circle me-1"></i> Sin guardar en este bloque';
            }
            if (footText) footText.innerHTML = '';

            // Habilitar botones de guardar etapa
            [btnTop, btnBot, btnGen].forEach(btn => {
                if (btn) {
                    btn.disabled = false;
                    btn.className = 'btn btn-success fw-bold px-4 py-2 rounded-pill shadow-sm btn-guardar-etapa';
                    btn.innerHTML = `<i class="bi bi-cloud-arrow-up-fill me-1"></i> Guardar Etapa ${e}`;
                }
            });
        }

        // 2. Si existen datos guardados para esta fecha y hora, aplicarlos por paquete y verificar si toda la etapa está completa
        if (res.success && res.lecturas && Object.keys(res.lecturas).length > 0) {
            const conteoGuardadosPorEtapa = { 1: 0, 2: 0, 3: 0, 4: 0 };

            Object.entries(res.lecturas).forEach(([pNumStr, datos]) => {
                const pNum = parseInt(pNumStr);
                const etapa = parseInt(datos.etapa);
                paquetesBloqueados[pNum] = true;
                if (etapa && conteoGuardadosPorEtapa[etapa] !== undefined) {
                    conteoGuardadosPorEtapa[etapa]++;
                }

                ['', 'general_'].forEach(pref => {
                    const uid = pref + pNum;
                    aplicarEstadoPaquete(uid, datos.estado || 'OPERACION');

                    const pIn = document.getElementById('p_in_' + uid);
                    const pOut = document.getElementById('p_out_' + uid);
                    const bIn = document.getElementById('brix_in_' + uid);
                    const bOut = document.getElementById('brix_out_' + uid);
                    const bPerm = document.getElementById('brix_perm_' + uid);
                    const obs = document.getElementById('obs_' + uid);

                    if (pIn && datos.presion_entrada !== null) pIn.value = datos.presion_entrada;
                    if (pOut && datos.presion_salida !== null) pOut.value = datos.presion_salida;
                    if (bIn && datos.brix_entrada !== null) bIn.value = datos.brix_entrada;
                    if (bOut && datos.brix_salida !== null) bOut.value = datos.brix_salida;
                    if (bPerm && datos.brix_permeado !== null) bPerm.value = datos.brix_permeado;
                    if (obs && datos.observaciones !== null) obs.value = datos.observaciones;

                    calcularDeltaPaquete(uid);

                    // Bloquear inputs de este paquete individual
                    document.querySelectorAll('.input-campo-' + uid).forEach(inp => {
                        inp.disabled = true;
                        inp.classList.add('campo-bloqueado');
                    });

                    // Actualizar botón individual del paquete a Guardado
                    const btnPaq = document.getElementById('btn-guardar-paq-' + uid);
                    if (btnPaq) {
                        btnPaq.disabled = true;
                        btnPaq.className = 'btn btn-sm btn-secondary w-100 fw-bold rounded-pill shadow-sm';
                        btnPaq.innerHTML = `<i class="bi bi-lock-fill me-1"></i> Paquete ${pNum} Guardado (${hora})`;
                    }
                });
            });

            // Verificar qué etapas ya tienen TODOS sus paquetes guardados
            for (let e = 1; e <= 4; e++) {
                const totalEtapa = ETAPAS_CONFIG[e].length;
                const guardados = conteoGuardadosPorEtapa[e] || 0;
                const badgeTop = document.getElementById('status-etapa-badge-' + e);

                if (guardados > 0 && guardados < totalEtapa) {
                    if (badgeTop) {
                        badgeTop.className = 'badge bg-warning text-dark border px-3 py-1 rounded-pill small fw-bold';
                        badgeTop.innerHTML = `<i class="bi bi-clock-history me-1"></i> Parcial: ${guardados}/${totalEtapa} Paquetes guardados`;
                    }
                } else if (guardados >= totalEtapa) {
                    etapasBloqueadas[e] = true;

                    const tabCheck = document.getElementById('tab-check-' + e);
                    const tabLock = document.getElementById('tab-lock-' + e);
                    const footText = document.getElementById('status-etapa-foot-' + e);
                    const avisoBloq = document.getElementById('alerta-bloqueada-etapa-' + e);
                    const txtHora = document.querySelector('.hora-bloqueada-txt-' + e);
                    const btnTop = document.getElementById('btn-guardar-top-' + e);
                    const btnBot = document.getElementById('btn-guardar-bot-' + e);
                    const btnGen = document.getElementById('btn-guardar-general-' + e);

                    if (tabCheck) tabCheck.classList.remove('d-none');
                    if (tabLock) tabLock.classList.remove('d-none');
                    if (avisoBloq) avisoBloq.classList.remove('d-none');
                    if (txtHora) txtHora.textContent = hora;

                    if (badgeTop) {
                        badgeTop.className = 'badge bg-secondary text-white border px-3 py-1 rounded-pill small fw-bold';
                        badgeTop.innerHTML = `<i class="bi bi-lock-fill me-1"></i> Etapa Completa (${hora} hrs)`;
                    }
                    if (footText) {
                        footText.innerHTML = `<span class="badge bg-secondary text-white px-2 py-1 rounded-pill"><i class="bi bi-lock-fill me-1"></i> Ya Registrado: ${hora} hrs</span>`;
                    }

                    [btnTop, btnBot, btnGen].forEach(btn => {
                        if (btn) {
                            btn.disabled = true;
                            btn.className = 'btn btn-secondary fw-bold px-4 py-2 rounded-pill shadow-sm btn-guardar-etapa';
                            btn.innerHTML = `<i class="bi bi-lock-fill me-1"></i> Etapa ${e} Completa (${hora} hrs)`;
                        }
                    });
                }
            }
        }
    } catch (err) {
        console.error('Error consultando lecturas previas:', err);
    }
}

// =========================================================================
// MODAL DE CONSULTA DE REGISTROS DEL DÍA EN MEMBRANAS
// =========================================================================
let registrosDiaCache = [];

async function abrirModalRegistrosDia() {
    const fecha = document.getElementById('membrana_fecha').value || new Date().toISOString().split('T')[0];
    const modalEl = document.getElementById('modalRegistrosDia');
    const tbody = document.getElementById('tbodyModalRegistros');
    const conteo = document.getElementById('conteoRegistrosModal');
    const titulo = document.getElementById('tituloModalRegistrosDia');

    if (titulo) titulo.textContent = `Registros de Membranas - ${fecha}`;
    if (tbody) tbody.innerHTML = `<tr><td colspan="12" class="py-4 text-center text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Obteniendo registros del ${fecha}...</td></tr>`;
    if (conteo) conteo.textContent = 'Cargando...';

    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    try {
        const resp = await fetch('acciones/guardar_membranas.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ accion: 'obtener_dia', fecha: fecha })
        });
        const res = await resp.json();

        if (res.success && res.registros && res.registros.length > 0) {
            registrosDiaCache = res.registros;
            if (conteo) conteo.textContent = `${res.registros.length} Lecturas Registradas`;
            renderizarTablaModal(res.registros);
        } else {
            registrosDiaCache = [];
            if (conteo) conteo.textContent = '0 Lecturas';
            if (tbody) tbody.innerHTML = `<tr><td colspan="12" class="py-5 text-center text-muted fs-6"><i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>No se han guardado lecturas de Membranas para el ${fecha}.</td></tr>`;
        }
    } catch (e) {
        console.error(e);
        if (tbody) tbody.innerHTML = `<tr><td colspan="12" class="py-4 text-center text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Error al consultar los registros del servidor.</td></tr>`;
    }
}

function renderizarTablaModal(registros) {
    const tbody = document.getElementById('tbodyModalRegistros');
    if (!tbody) return;

    if (!registros || registros.length === 0) {
        tbody.innerHTML = `<tr><td colspan="12" class="py-4 text-center text-muted">No hay registros que coincidan con la búsqueda.</td></tr>`;
        return;
    }

    let html = '';
    registros.forEach(r => {
        const est = (r.estado || 'OPERACION').toUpperCase();
        let badgeEst = '<span class="badge bg-success px-2 py-1">Operación</span>';
        if (est === 'LAVADO') badgeEst = '<span class="badge bg-info text-dark px-2 py-1">Lavado</span>';
        else if (est === 'MMT') badgeEst = '<span class="badge bg-warning text-dark px-2 py-1">MMT</span>';
        else if (est === 'FO' || est === 'F.O.') badgeEst = '<span class="badge bg-danger text-white px-2 py-1">F.O.</span>';

        const dVal = (r.delta_presion !== null && r.delta_presion !== '') ? parseFloat(r.delta_presion) : null;
        let deltaHtml = '-';
        if (dVal !== null) {
            let dClass = 'bg-success text-white';
            if (dVal >= 1.8) dClass = 'bg-danger text-white';
            else if (dVal >= 1.2) dClass = 'bg-warning text-dark';
            deltaHtml = `<span class="badge ${dClass} px-2 py-1 font-monospace fw-bold">${dVal.toFixed(2)}</span>`;
        }

        const pIn = (r.presion_entrada !== null && r.presion_entrada !== '') ? parseFloat(r.presion_entrada).toFixed(2) + ' bar' : '-';
        const pOut = (r.presion_salida !== null && r.presion_salida !== '') ? parseFloat(r.presion_salida).toFixed(2) + ' bar' : '-';
        const bIn = (r.brix_entrada !== null && r.brix_entrada !== '') ? parseFloat(r.brix_entrada).toFixed(1) + ' °Bx' : '-';
        const bOut = (r.brix_salida !== null && r.brix_salida !== '') ? parseFloat(r.brix_salida).toFixed(1) + ' °Bx' : '-';
        const bPerm = (r.brix_permeado !== null && r.brix_permeado !== '') ? parseFloat(r.brix_permeado).toFixed(1) + ' °Bx' : '-';
        const horaExacta = r.fecha_registro ? r.fecha_registro.split(' ')[1] : r.hora;

        html += `
        <tr>
            <td class="fw-bold text-dark font-monospace">${horaExacta}</td>
            <td><span class="badge bg-light text-dark border fw-bold"><i class="bi bi-person-badge me-1 text-secondary"></i>${r.operador_nomina || 'N/A'}</span></td>
            <td><span class="badge bg-secondary rounded-pill px-2 py-1">Etapa ${r.etapa}</span></td>
            <td class="fw-bold"><span class="badge bg-dark rounded-pill px-2 py-1 me-1 small">P${r.paquete_numero}</span> Paq. ${r.paquete_numero}</td>
            <td>${badgeEst}</td>
            <td class="font-monospace">${pIn}</td>
            <td class="font-monospace">${pOut}</td>
            <td>${deltaHtml}</td>
            <td>${bIn}</td>
            <td>${bOut}</td>
            <td>${bPerm}</td>
            <td class="text-start small text-muted text-truncate" style="max-width: 180px;" title="${r.observaciones || ''}">${r.observaciones || '-'}</td>
        </tr>`;
    });

    tbody.innerHTML = html;
}

function filtrarTablaModalRegistros() {
    const texto = (document.getElementById('buscadorModalRegistros')?.value || '').toLowerCase().trim();
    const etapaFiltro = document.getElementById('filtroEtapaModalRegistros')?.value || '';

    const filtrados = registrosDiaCache.filter(r => {
        const etapaOk = !etapaFiltro || r.etapa.toString() === etapaFiltro;
        if (!etapaOk) return false;

        if (!texto) return true;

        const strBuscar = `${r.hora} ${r.fecha_registro} ${r.etapa} ${r.paquete_numero} ${r.estado} ${r.operador_nomina} ${r.observaciones} ${r.presion_entrada} ${r.presion_salida} ${r.delta_presion} ${r.brix_entrada} ${r.brix_salida} ${r.brix_permeado}`.toLowerCase();
        return strBuscar.includes(texto);
    });

    renderizarTablaModal(filtrados);
    const conteo = document.getElementById('conteoRegistrosModal');
    if (conteo) conteo.textContent = `${filtrados.length} de ${registrosDiaCache.length} registros`;
}

// Cargar lecturas existentes al inicializar la página
document.addEventListener('DOMContentLoaded', function() {
    consultarLecturasPrevias();
});
</script>

<!-- MODAL DE REGISTROS DEL DÍA -->
<div class="modal fade" id="modalRegistrosDia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 96vw; width: 96vw;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-table fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold m-0" id="tituloModalRegistrosDia">Registros de Membranas del Día</h5>
                        <small class="text-white-50">Consulta detallada de todas las lecturas capturadas por hora y etapa</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- Barra de Filtros del Modal -->
                <div class="row g-2 mb-3 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="buscadorModalRegistros" class="form-control border-start-0" placeholder="Buscar por paquete, hora, operador, estado..." onkeyup="filtrarTablaModalRegistros()">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select id="filtroEtapaModalRegistros" class="form-select form-select-sm fw-bold" onchange="filtrarTablaModalRegistros()">
                            <option value="">Todas las Etapas</option>
                            <option value="1">Etapa 1 (Paq 1, 2, 3, 4, 9)</option>
                            <option value="2">Etapa 2 (Paq 5, 6, 7, 8)</option>
                            <option value="3">Etapa 3 (Paq 10, 11)</option>
                            <option value="4">Etapa 4 (Paq 12, 13)</option>
                        </select>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-primary rounded-pill px-3 py-2 small" id="conteoRegistrosModal">Cargando...</span>
                    </div>
                </div>

                <!-- Tabla de Resultados -->
                <div class="table-responsive bg-white rounded-3 shadow-sm border" style="max-height: 520px;">
                    <table class="table table-hover table-sm align-middle text-center m-0" id="tablaModalRegistros">
                        <thead class="table-dark sticky-top small">
                            <tr>
                                <th>Hora Exacta</th>
                                <th>Nómina</th>
                                <th>Etapa</th>
                                <th>Paquete</th>
                                <th>Estado</th>
                                <th>P. Entrada</th>
                                <th>P. Salida</th>
                                <th>Delta de Presión</th>
                                <th>Brix Entrada</th>
                                <th>Brix Salida</th>
                                <th>Brix Permeado</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyModalRegistros" style="font-size: 0.85rem;">
                            <tr>
                                <td colspan="12" class="py-4 text-center text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2"></div> Cargando registros del día...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2 px-4 justify-content-between">
                <a href="reporte_general.php?fecha=<?= date('Y-m-d') ?>" class="btn btn-outline-primary btn-sm fw-bold rounded-pill px-3">
                    <i class="bi bi-grid-3x3-gap-fill me-1"></i> Ir a Matriz en Reporte General
                </a>
                <button type="button" class="btn btn-secondary btn-sm fw-bold rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
