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

date_default_timezone_set('America/Mexico_City');

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
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        gap: 4px;
        scrollbar-width: thin;
    }
    .nav-tabs-membranas::-webkit-scrollbar {
        height: 4px;
    }
    .nav-tabs-membranas::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 4px;
    }
    .nav-tabs-membranas .nav-link {
        color: #475569;
        font-weight: 700;
        border-radius: 12px 12px 0 0;
        padding: 0.75rem 1.2rem;
        border: none;
        border-bottom: 3px solid transparent;
        transition: all 0.2s ease;
        white-space: nowrap;
        flex-shrink: 0;
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
        font-size: 1.05rem;
        font-weight: 800;
        padding: 4px 6px;
        border-radius: 8px;
        background: #f1f5f9;
        border: 1px solid #cbd5e1;
        display: block;
        width: 100%;
        text-align: center;
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
        font-size: 0.72rem;
        font-weight: 700;
        color: #475569;
        margin-bottom: 2px;
        white-space: nowrap;
    }

    /* Responsividad Tablet (768px a 1024px) */
    @media (min-width: 768px) and (max-width: 1024px) {
        .card-paquete-header {
            padding: 0.55rem 0.75rem !important;
        }
        .card-paquete .card-body {
            padding: 0.75rem !important;
        }
        .btn-toggle-estado {
            font-size: 0.70rem !important;
            padding: 2px 6px !important;
        }
        .delta-badge {
            font-size: 0.95rem !important;
            padding: 3px 4px !important;
        }
        .label-param {
            font-size: 0.67rem !important;
        }
        .input-metrica {
            font-size: 0.85rem !important;
            padding: 0.25rem 0.4rem !important;
        }
        .nav-tabs-membranas .nav-link {
            padding: 0.55rem 0.85rem !important;
            font-size: 0.85rem !important;
        }
    }

    /* Responsividad Laptop y Escritorio (>= 1025px) */
    @media (min-width: 1025px) {
        .card-paquete-header {
            padding: 0.75rem 1rem !important;
        }
        .btn-toggle-estado {
            font-size: 0.75rem !important;
            padding: 3px 8px !important;
        }
        .delta-badge {
            font-size: 1.1rem !important;
            padding: 4px 8px !important;
        }
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

    /* Estilos de Bitácora Sábana (Hoja Física) en el Modal de Registros del Día */
    .bitacora-sabana-sheet {
        background: #ffffff;
        border: 2px solid #000000;
        border-radius: 6px;
        overflow: hidden;
        margin-bottom: 18px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
    }
    .bitacora-sabana-top {
        border-bottom: 2px solid #000000;
        padding: 8px 14px;
        background: #ffffff;
        color: #000000;
    }
    .bitacora-titulo-caja {
        border: 2px solid #000000;
        padding: 4px 18px;
        font-weight: 900;
        font-size: 0.95rem;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        text-align: center;
        background: #f8fafc;
        color: #000000;
    }
    .bitacora-campo-linea {
        font-size: 0.80rem;
        font-weight: 800;
        color: #000000;
    }
    .bitacora-campo-linea span {
        display: inline-block;
        border-bottom: 1.5px solid #000000;
        min-width: 90px;
        padding: 0 6px;
        font-weight: 700;
        color: #0f172a;
    }
    .tabla-sabana-membranas {
        width: 100%;
        border-collapse: collapse;
        background: #ffffff;
        font-size: 0.72rem;
    }
    .tabla-sabana-membranas th,
    .tabla-sabana-membranas td {
        border: 1px solid #000000 !important;
        padding: 3px 4px !important;
        text-align: center;
        vertical-align: middle;
        white-space: nowrap;
    }
    .tabla-sabana-membranas thead tr.row-etapa th {
        background-color: #e2e8f0 !important;
        color: #000000 !important;
        font-weight: 900 !important;
        font-size: 0.76rem !important;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 1.5px solid #000000 !important;
    }
    .tabla-sabana-membranas thead tr.row-paquete th {
        background-color: #f1f5f9 !important;
        color: #000000 !important;
        font-weight: 800 !important;
        font-size: 0.72rem !important;
    }
    .tabla-sabana-membranas thead tr.row-param-nom th {
        background-color: #f8fafc !important;
        color: #000000 !important;
        font-weight: 800 !important;
        font-size: 0.64rem !important;
        line-height: 1.1 !important;
        padding: 4px 3px !important;
        white-space: normal !important;
        min-width: 42px;
    }
    .tabla-sabana-membranas thead tr.row-param-rango th {
        font-size: 0.61rem !important;
        font-weight: 800 !important;
        line-height: 1.1 !important;
        padding: 3px 3px !important;
        border-bottom: 2px solid #000000 !important;
        white-space: normal !important;
    }
    .th-rango-pendiente {
        background-color: #ffffff !important;
        color: #000000 !important;
        font-style: italic;
    }
    .th-rango-semaforo {
        background-color: #ffffff !important;
        color: #000000 !important;
    }
    .borde-fin-paquete {
        border-right: 2px solid #000000 !important;
    }
    .borde-fin-etapa {
        border-right: 3px solid #000000 !important;
    }
    .sticky-col-hora {
        position: sticky;
        left: 0;
        z-index: 4;
        background-color: #ffffff !important;
        color: #000000 !important;
        font-weight: 800 !important;
        min-width: 58px !important;
        border-right: 2px solid #000000 !important;
    }
    .cell-matrix-blanco {
        background-color: #ffffff !important;
        color: #000000 !important;
        font-weight: 800 !important;
    }
    .cell-matrix-empty {
        background-color: #ffffff !important;
        color: #000000 !important;
        font-weight: 600 !important;
    }
    .cell-matrix-verde {
        background-color: #2e8b57 !important;
        color: #ffffff !important;
        font-weight: 800 !important;
    }
    .cell-matrix-amarillo {
        background-color: #facc15 !important;
        color: #111827 !important;
        font-weight: 800 !important;
    }
    .cell-matrix-rojo {
        background-color: #c94436 !important;
        color: #ffffff !important;
        font-weight: 800 !important;
    }
    .cell-matrix-lavado {
        background-color: #2563eb !important;
        color: #ffffff !important;
        font-weight: 800 !important;
    }
    .tabla-sabana-membranas.modo-hoja-fisica .col-extra-memb,
    .tabla-sabana-membranas.modo-hoja-fisica .col-semaforo-memb {
        display: none !important;
    }
    .tabla-sabana-membranas.modo-compacto .col-extra-memb {
        display: none !important;
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
                    <a href="historial.php?id=100&print=1" target="_blank" class="btn btn-dark btn-sm fw-bold px-3 py-2 rounded-pill shadow-sm">
                        <i class="bi bi-printer-fill me-1"></i> Imprimir Membranas
                    </a>
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

            <!-- FECHA, HORA Y OPERADOR -->
            <div class="row g-3 align-items-center">
                <div class="col-sm-6 col-md-3">
                    <label class="label-param d-block text-secondary">
                        <i class="bi bi-calendar-event me-1"></i> Fecha
                    </label>
                    <input type="date" id="membrana_fecha" class="form-control form-control-sm fw-bold border-secondary-subtle bg-light" value="<?= date('Y-m-d') ?>" readonly tabindex="-1" style="pointer-events: none; cursor: not-allowed;">
                </div>
                <div class="col-sm-6 col-md-4">
                    <label class="label-param d-block text-secondary">
                        <i class="bi bi-clock-fill me-1 text-primary"></i> Hora de Captura
                    </label>
                    <input type="hidden" id="membrana_hora" value="<?= $horaActualStr ?>">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-dark text-warning border-dark fw-bold"><i class="bi bi-lock-fill"></i></span>
                        <input type="text" id="membrana_hora_display" class="form-control fw-bold bg-light text-dark border-secondary-subtle font-monospace" value="<?= $horaActualStr ?> hrs" readonly tabindex="-1" style="pointer-events: none; cursor: not-allowed;">
                    </div>
                </div>
                <div class="col-12 col-md-5">
                    <label class="label-param d-block text-secondary"><i class="bi bi-person-badge me-1"></i> Operador Responsable</label>
                    <span class="badge bg-light text-dark border px-3 py-2 rounded-pill small fw-bold">
                        <?= htmlspecialchars($_SESSION['nombre'] ?? 'Operador') ?>
                    </span>
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
                        <div class="col-12 col-md-6 col-lg-6 col-xl-<?php echo ($etapaNum == 1) ? '4' : '6'; ?>">
                            <?php renderizarTarjetaPaquete($paqNum, $etapaNum, $e); ?>
                        </div>
                    <?php endforeach; ?>
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
                            <div class="col-12 col-md-6 col-lg-4 col-xl-3">
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
        <div class="card-paquete-header d-flex justify-content-between align-items-center gap-2 flex-wrap">
            <div class="d-flex align-items-center gap-1">
                <span class="badge bg-dark rounded-pill px-3 py-1 fs-6">Paquete <?= $paqNum ?></span>
            </div>

            <!-- BOTONES DE EXCEPCIÓN (LAVADO / MMT / F.O.) -->
            <div class="d-flex gap-1 flex-nowrap justify-content-end">
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
                    <div class="col-4 text-center">
                        <label class="label-param d-block text-primary">Delta Presión</label>
                        <div class="delta-badge" id="delta_display_<?= $uid ?>" title="Presión Entrada menos Presión Salida">0.00</div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 2: CONTROL DE CONCENTRACIÓN BRIX Y TEMPERATURA -->
            <div class="bg-light p-2 rounded-3 border mb-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mb-2">
                    <div class="text-uppercase fw-bold text-secondary" style="font-size: 0.68rem; letter-spacing: 0.5px;">
                        <i class="bi bi-droplet-half text-success me-1"></i> Control de Brix y Temperatura
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="d-flex justify-content-between align-items-center mb-1 flex-nowrap">
                            <span class="label-param m-0 fw-bold text-dark text-truncate">Brix Entrada</span>
                            <span id="badge_brix_serie_<?= $uid ?>" class="badge rounded-pill bg-success-subtle text-success border border-success-subtle d-none" style="font-size: 0.58rem; padding: 2px 5px; flex-shrink: 0;">Inicial</span>
                        </div>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.1" class="form-control form-control-sm input-metrica input-campo-<?= $uid ?> input-etapa-<?= $etapaNum ?>" id="brix_in_<?= $uid ?>" placeholder="0.0" oninput="sincronizarInputPaquete('<?= $uid ?>', 'brix_in')">
                            <span class="input-group-text px-1 bg-white text-muted" style="font-size: 0.68rem;">°Bx</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex justify-content-between align-items-center mb-1 flex-nowrap">
                            <span class="label-param m-0 fw-bold text-dark text-truncate">Brix Salida</span>
                            <span class="badge rounded-pill bg-light text-secondary border" style="font-size: 0.58rem; padding: 2px 5px; flex-shrink: 0;" title="Se envía automáticamente al Brix Entrada del siguiente paquete activo">→ Sig.</span>
                        </div>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.1" class="form-control form-control-sm input-metrica input-campo-<?= $uid ?> input-etapa-<?= $etapaNum ?>" id="brix_out_<?= $uid ?>" placeholder="0.0" oninput="alCambiarBrixSalida('<?= $uid ?>')">
                            <span class="input-group-text px-1 bg-white text-muted" style="font-size: 0.68rem;">°Bx</span>
                        </div>
                    </div>
                </div>
                <div class="row g-2 pt-2 border-top">
                    <div class="col-6">
                        <div class="d-flex justify-content-between align-items-center mb-1 flex-nowrap">
                            <span class="label-param m-0 fw-bold text-dark text-truncate">Permeado</span>
                            <span id="sem_perm_<?= $uid ?>" class="badge rounded-pill bg-light text-muted border" style="font-size: 0.58rem; padding: 2px 5px; flex-shrink: 0;" title="Verde: < 0.1 | Amarillo: 0.1 - 0.5 | Rojo: > 0.5">&lt;0.1</span>
                        </div>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.01" class="form-control form-control-sm input-metrica input-campo-<?= $uid ?> input-etapa-<?= $etapaNum ?>" id="brix_perm_<?= $uid ?>" placeholder="0.00" oninput="evaluarSemaforoMembrana('<?= $uid ?>', true)">
                            <span class="input-group-text px-1 bg-white text-muted" style="font-size: 0.68rem;">°Bx</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="d-flex justify-content-between align-items-center mb-1 flex-nowrap">
                            <span class="label-param m-0 fw-bold text-dark text-truncate"><i class="bi bi-thermometer-half text-danger"></i> Temp.</span>
                            <span id="sem_temp_<?= $uid ?>" class="badge rounded-pill bg-light text-muted border" style="font-size: 0.58rem; padding: 2px 5px; flex-shrink: 0;" title="Verde: 45 - 55 °C | Amarillo: 40 - 44.9 y 56 - 60 °C | Rojo: < 39 o > 60 °C">45-55°</span>
                        </div>
                        <div class="input-group input-group-sm">
                            <input type="number" step="0.1" class="form-control form-control-sm input-metrica input-campo-<?= $uid ?> input-etapa-<?= $etapaNum ?>" id="temp_<?= $uid ?>" placeholder="45.0" oninput="evaluarSemaforoMembrana('<?= $uid ?>', true)">
                            <span class="input-group-text px-1 bg-white text-muted" style="font-size: 0.68rem;">°C</span>
                        </div>
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

// Orden en serie paquete por paquete (Etapa 1 -> Etapa 2 -> Etapa 3 -> Etapa 4)
const ORDEN_SERIE_PAQUETES = [1, 2, 3, 4, 9, 5, 6, 7, 8, 10, 11, 12, 13];

// Variables para rastrear etapas y paquetes bloqueados para la hora actual seleccionada
let etapasBloqueadas = {};
let paquetesBloqueados = {};

function verificarHoraAutomatica() {
    const ahora = new Date();
    const horaActual = String(ahora.getHours()).padStart(2, '0') + ':00';
    const yyyy = ahora.getFullYear();
    const mm = String(ahora.getMonth() + 1).padStart(2, '0');
    const dd = String(ahora.getDate()).padStart(2, '0');
    const fechaActual = `${yyyy}-${mm}-${dd}`;

    const inputHora = document.getElementById('membrana_hora');
    const displayHora = document.getElementById('membrana_hora_display');
    const inputFecha = document.getElementById('membrana_fecha');

    let cambio = false;
    if (inputFecha && inputFecha.value !== fechaActual) {
        inputFecha.value = fechaActual;
        cambio = true;
    }
    if (inputHora && inputHora.value !== horaActual) {
        inputHora.value = horaActual;
        if (displayHora) {
            displayHora.value = `${horaActual} hrs (Hora Actual del Sistema)`;
        }
        cambio = true;
    }
    if (cambio) {
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
    deltaDisplay.style.color = '#475569';
    deltaDisplay.style.backgroundColor = '#f1f5f9';
    deltaDisplay.style.borderColor = '#cbd5e1';

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
// PROPAGACIÓN AUTOMÁTICA EN SERIE DE BRIX (1→2→3→4→9 → 5→6→7→8 → 10→11 → 12→13)
// El Brix de Salida de cada paquete activo pasa al Brix de Entrada del siguiente
// =========================================================================
function alCambiarBrixSalida(uid) {
    sincronizarInputPaquete(uid, 'brix_out');
    propagarBrixEnSerie();
}

function propagarBrixEnSerie() {
    let brixParaSiguiente = null; // Valor que recibirá el siguiente paquete activo
    let paqOrigen = null;

    ORDEN_SERIE_PAQUETES.forEach(pNum => {
        const uid = pNum.toString();
        const card = document.getElementById('card-paq-' + uid);
        const estado = card ? (card.getAttribute('data-estado') || 'OPERACION') : 'OPERACION';

        const bIn = document.getElementById('brix_in_' + uid);
        const bInGen = document.getElementById('brix_in_general_' + uid);
        const bOut = document.getElementById('brix_out_' + uid);
        const bOutGen = document.getElementById('brix_out_general_' + uid);
        const badge = document.getElementById('badge_brix_serie_' + uid);
        const badgeGen = document.getElementById('badge_brix_serie_general_' + uid);

        // Si el paquete está en F.O., LAVADO o MMT, NO tiene ni recibe Brix de Entrada ni de Salida (no se guarda nada)
        if (estado !== 'OPERACION') {
            [badge, badgeGen].forEach(b => {
                if (b) {
                    b.classList.remove('d-none');
                    b.className = 'badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle';
                    b.style.fontSize = '0.58rem';
                    b.style.padding = '2px 6px';
                    b.textContent = estado === 'FO' ? 'F.O. (Fuera de Operación)' : estado;
                    b.title = 'Paquete no operativo: sin datos de Brix';
                }
            });
            [bIn, bInGen].forEach(inp => {
                if (inp) {
                    inp.value = '';
                    inp.readOnly = true;
                    inp.placeholder = '--';
                    inp.style.backgroundColor = '#f1f5f9';
                    inp.style.borderColor = '';
                }
            });
            [bOut, bOutGen].forEach(inp => {
                if (inp) {
                    inp.value = '';
                    inp.readOnly = true;
                    inp.placeholder = '--';
                }
            });
            // Este paquete en F.O. no modifica brixParaSiguiente ni paqOrigen; el siguiente activo recibirá el del último activo previo
            return;
        }

        // Si el paquete ESTÁ EN OPERACIÓN:
        if (paqOrigen === null) {
            // Es el primer paquete activo de la serie (usualmente Paquete 1)
            [badge, badgeGen].forEach(b => {
                if (b) {
                    b.classList.remove('d-none');
                    b.className = 'badge rounded-pill bg-success-subtle text-success border border-success-subtle';
                    b.style.fontSize = '0.58rem';
                    b.style.padding = '2px 5px';
                    b.textContent = 'Inicial';
                    b.title = 'Primer paquete en operación: ingresa aquí el Brix de Entrada inicial';
                }
            });
            if (!paquetesBloqueados[pNum]) {
                [bIn, bInGen].forEach(inp => {
                    if (inp) {
                        inp.readOnly = false;
                        inp.placeholder = '0.0';
                        inp.style.backgroundColor = '';
                        inp.style.borderColor = '';
                        inp.style.fontWeight = '600';
                    }
                });
            }
        } else {
            // Es un paquete subsecuente activo: recibe ÚNICAMENTE el Brix Salida del paquete activo anterior
            const tieneBrixAnterior = (brixParaSiguiente !== null && brixParaSiguiente !== '');
            [badge, badgeGen].forEach(b => {
                if (b) {
                    b.classList.remove('d-none');
                    if (tieneBrixAnterior) {
                        b.className = 'badge rounded-pill bg-primary text-white';
                        b.textContent = `Auto P${paqOrigen}`;
                        b.title = `Toma automáticamente el Brix de Salida del Paquete ${paqOrigen}`;
                    } else {
                        b.className = 'badge rounded-pill bg-secondary-subtle text-secondary border';
                        b.textContent = `Pend. P${paqOrigen}`;
                        b.title = `Esperando a que se ingrese el Brix de Salida en el Paquete ${paqOrigen}`;
                    }
                    b.style.fontSize = '0.58rem';
                    b.style.padding = '2px 5px';
                }
            });

            if (!paquetesBloqueados[pNum]) {
                [bIn, bInGen].forEach(inp => {
                    if (inp) {
                        inp.value = tieneBrixAnterior ? brixParaSiguiente : '';
                        inp.readOnly = true;
                        inp.placeholder = tieneBrixAnterior ? `Auto (P${paqOrigen})` : `Pendiente P${paqOrigen}`;
                        inp.style.backgroundColor = tieneBrixAnterior ? '#eff6ff' : '#f8fafc';
                        inp.style.borderColor = tieneBrixAnterior ? '#3b82f6' : '';
                        inp.style.fontWeight = '800';
                    }
                });
            }
        }

        // Habilitar salida de este paquete si no está bloqueado
        if (!paquetesBloqueados[pNum]) {
            [bOut, bOutGen].forEach(inp => {
                if (inp) {
                    inp.readOnly = false;
                    inp.placeholder = '0.0';
                }
            });
        }

        // El Brix que pasará al SIGUIENTE paquete activo es ÚNICAMENTE el que se escriba en la salida de ESTE paquete
        const valOut = (bOut && bOut.value.trim() !== '') ? bOut.value.trim() : ((bOutGen && bOutGen.value.trim() !== '') ? bOutGen.value.trim() : '');
        brixParaSiguiente = (valOut !== '') ? valOut : null;
        paqOrigen = pNum;
    });
}

// =========================================================================
// SEMÁFORO EN TIEMPO REAL PARA PERMEADO (°Bx) Y TEMPERATURA (°C)
// =========================================================================
function evaluarSemaforoMembrana(uid, sincronizar = false) {
    const permInput = document.getElementById('brix_perm_' + uid);
    const permBadge = document.getElementById('sem_perm_' + uid);
    const tempInput = document.getElementById('temp_' + uid);
    const tempBadge = document.getElementById('sem_temp_' + uid);

    // 1. Evaluar Permeado: < 0.1 Verde | 0.1 - 0.5 Amarillo | > 0.5 Rojo
    if (permInput) {
        const rawPerm = permInput.value.trim();
        if (rawPerm === '' || isNaN(parseFloat(rawPerm))) {
            permInput.style.backgroundColor = '';
            permInput.style.borderColor = '';
            permInput.style.color = '';
            if (permBadge) {
                permBadge.className = 'badge rounded-pill bg-light text-muted border';
                permBadge.textContent = '<0.1';
            }
        } else {
            const valP = parseFloat(rawPerm);
            if (valP < 0.1) {
                permInput.style.backgroundColor = '#dcfce7';
                permInput.style.borderColor = '#16a34a';
                permInput.style.color = '#14532d';
                if (permBadge) {
                    permBadge.className = 'badge rounded-pill bg-success text-white';
                    permBadge.textContent = 'Óptimo';
                }
            } else if (valP >= 0.1 && valP <= 0.5) {
                permInput.style.backgroundColor = '#fef9c3';
                permInput.style.borderColor = '#eab308';
                permInput.style.color = '#713f12';
                if (permBadge) {
                    permBadge.className = 'badge rounded-pill bg-warning text-dark';
                    permBadge.textContent = 'Alerta';
                }
            } else {
                permInput.style.backgroundColor = '#fee2e2';
                permInput.style.borderColor = '#dc2626';
                permInput.style.color = '#7f1d1d';
                if (permBadge) {
                    permBadge.className = 'badge rounded-pill bg-danger text-white';
                    permBadge.textContent = 'Crítico';
                }
            }
        }
    }

    // 2. Evaluar Temperatura: 45-55 Verde | 40-44.9 y 56-60 Amarillo | <39 (o <40) y >60 Rojo
    if (tempInput) {
        const rawTemp = tempInput.value.trim();
        if (rawTemp === '' || isNaN(parseFloat(rawTemp))) {
            tempInput.style.backgroundColor = '';
            tempInput.style.borderColor = '';
            tempInput.style.color = '';
            if (tempBadge) {
                tempBadge.className = 'badge rounded-pill bg-light text-muted border';
                tempBadge.textContent = '45-55°';
            }
        } else {
            const valT = parseFloat(rawTemp);
            if (valT >= 45 && valT <= 55) {
                tempInput.style.backgroundColor = '#dcfce7';
                tempInput.style.borderColor = '#16a34a';
                tempInput.style.color = '#14532d';
                if (tempBadge) {
                    tempBadge.className = 'badge rounded-pill bg-success text-white';
                    tempBadge.textContent = 'Óptimo';
                }
            } else if ((valT >= 40 && valT < 45) || (valT > 55 && valT <= 60)) {
                tempInput.style.backgroundColor = '#fef9c3';
                tempInput.style.borderColor = '#eab308';
                tempInput.style.color = '#713f12';
                if (tempBadge) {
                    tempBadge.className = 'badge rounded-pill bg-warning text-dark';
                    tempBadge.textContent = 'Alerta';
                }
            } else {
                tempInput.style.backgroundColor = '#fee2e2';
                tempInput.style.borderColor = '#dc2626';
                tempInput.style.color = '#7f1d1d';
                if (tempBadge) {
                    tempBadge.className = 'badge rounded-pill bg-danger text-white';
                    tempBadge.textContent = 'Crítico';
                }
            }
        }
    }

    if (sincronizar) {
        sincronizarInputPaquete(uid, 'brix_perm');
        sincronizarInputPaquete(uid, 'temp');
        const uidSinc = uid.startsWith('general_') ? uid.replace('general_', '') : 'general_' + uid;
        evaluarSemaforoMembrana(uidSinc, false);
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
    propagarBrixEnSerie();
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
        // Limpiar absolutamente todos los datos y restablecer cuando está en F.O., Lavado o MMT (no se guarda nada)
        campos.forEach(c => { c.value = ''; });
        const bIn = document.getElementById('brix_in_' + uid);
        const bOut = document.getElementById('brix_out_' + uid);
        if (bIn) bIn.value = '';
        if (bOut) bOut.value = '';

        const deltaDisplay = document.getElementById('delta_display_' + uid);
        if (deltaDisplay) {
            deltaDisplay.textContent = '0.00';
            deltaDisplay.style.color = '#6c757d';
            deltaDisplay.style.backgroundColor = '#e9ecef';
            deltaDisplay.style.borderColor = '#cbd5e1';
        }
        evaluarSemaforoMembrana(uid, false);
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
        propagarBrixEnSerie();
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
    const tempVal  = esOperacion ? (document.getElementById('temp_' + uid)?.value || document.getElementById('temp_general_' + uid)?.value || '') : '';
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
        temperatura: tempVal,
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
        const tempVal  = esOperacion ? (document.getElementById('temp_' + uid)?.value || '') : '';
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
            temperatura: tempVal,
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
                ['p_in_', 'p_out_', 'brix_in_', 'brix_out_', 'brix_perm_', 'temp_', 'obs_'].forEach(f => {
                    const el = document.getElementById(f + uid);
                    if (el) {
                        el.value = '';
                        el.disabled = false;
                        el.classList.remove('campo-bloqueado');
                    }
                });
                calcularDeltaPaquete(uid);
                evaluarSemaforoMembrana(uid, false);

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
                    const temp = document.getElementById('temp_' + uid);
                    const obs = document.getElementById('obs_' + uid);

                    if (pIn && datos.presion_entrada !== null) pIn.value = datos.presion_entrada;
                    if (pOut && datos.presion_salida !== null) pOut.value = datos.presion_salida;
                    if (bIn && datos.brix_entrada !== null) bIn.value = datos.brix_entrada;
                    if (bOut && datos.brix_salida !== null) bOut.value = datos.brix_salida;
                    if (bPerm && datos.brix_permeado !== null) bPerm.value = datos.brix_permeado;
                    if (temp && datos.temperatura !== undefined && datos.temperatura !== null) temp.value = datos.temperatura;
                    if (obs && datos.observaciones !== null) obs.value = datos.observaciones;

                    calcularDeltaPaquete(uid);
                    evaluarSemaforoMembrana(uid, false);

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
        // Propagar Brix en serie desde los paquetes guardados/activos hacia los siguientes pendientes
        propagarBrixEnSerie();
    } catch (err) {
        console.error('Error consultando lecturas previas:', err);
    }
}

// =========================================================================
// MODAL DE CONSULTA DE REGISTROS DEL DÍA EN MEMBRANAS (FORMATO HOJA FÍSICA)
// =========================================================================
let registrosDiaCache = [];
let modoColsModalSabana = 2; // 2 = Hoja Física (Presión Entrada + Sólidos Salida), 4 = +Permeado/Temp, 7 = Expandida
let turnoFiltroModalSabana = 'ambos';

async function abrirModalRegistrosDia() {
    const fecha = document.getElementById('membrana_fecha').value || new Date().toISOString().split('T')[0];
    const modalEl = document.getElementById('modalRegistrosDia');
    const tbody = document.getElementById('tbodyModalRegistros');
    const contSabana = document.getElementById('contenedorSabanaModalDia');
    const conteo = document.getElementById('conteoRegistrosModal');
    const titulo = document.getElementById('tituloModalRegistrosDia');

    if (titulo) titulo.textContent = `Bitácora de Operación de Membranas — ${fecha}`;
    if (contSabana) contSabana.innerHTML = `<div class="py-5 text-center text-muted bg-white rounded-3 border"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Cargando bitácora del ${fecha}...</div>`;
    if (tbody) tbody.innerHTML = `<tr><td colspan="13" class="py-4 text-center text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div> Obteniendo registros del ${fecha}...</td></tr>`;
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

        registrosDiaCache = (res.success && Array.isArray(res.registros)) ? res.registros : [];
        if (conteo) conteo.textContent = `${registrosDiaCache.length} Lecturas Registradas`;

        renderizarSabanaModalDia(registrosDiaCache, fecha);
        renderizarTablaModal(registrosDiaCache);
    } catch (e) {
        console.error(e);
        if (contSabana) contSabana.innerHTML = `<div class="py-4 text-center text-danger bg-white rounded-3 border"><i class="bi bi-exclamation-triangle-fill me-2"></i>Error al consultar los registros del servidor.</div>`;
        if (tbody) tbody.innerHTML = `<tr><td colspan="13" class="py-4 text-center text-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i>Error al consultar los registros del servidor.</td></tr>`;
    }
}

function cambiarVistaInternaModalDia(vista) {
    const sabanaWrapper = document.getElementById('wrapperSabanaModalDia');
    const listaWrapper = document.getElementById('wrapperListaModalDia');
    const btnSab = document.getElementById('btnModalVistaSabana');
    const btnLis = document.getElementById('btnModalVistaLista');
    const grpCols = document.getElementById('grpColsModalSabana');

    if (vista === 'lista') {
        if (sabanaWrapper) sabanaWrapper.classList.add('d-none');
        if (listaWrapper) listaWrapper.classList.remove('d-none');
        if (grpCols) grpCols.classList.add('d-none');
        if (btnSab) btnSab.className = 'btn btn-outline-dark btn-sm fw-bold';
        if (btnLis) btnLis.className = 'btn btn-dark btn-sm fw-bold active';
    } else {
        if (sabanaWrapper) sabanaWrapper.classList.remove('d-none');
        if (listaWrapper) listaWrapper.classList.add('d-none');
        if (grpCols) grpCols.classList.remove('d-none');
        if (btnSab) btnSab.className = 'btn btn-dark btn-sm fw-bold active';
        if (btnLis) btnLis.className = 'btn btn-outline-dark btn-sm fw-bold';
    }
}

function cambiarColsModalSabana(cols) {
    modoColsModalSabana = cols;
    const fecha = document.getElementById('membrana_fecha').value || new Date().toISOString().split('T')[0];
    renderizarSabanaModalDia(registrosDiaCache, fecha);

    const b2 = document.getElementById('btnModalCols2');
    const b4 = document.getElementById('btnModalCols4');
    const b7 = document.getElementById('btnModalCols7');
    if (b2) b2.className = (cols === 2) ? 'btn btn-primary btn-sm fw-bold active' : 'btn btn-outline-primary btn-sm fw-bold';
    if (b4) b4.className = (cols === 4) ? 'btn btn-primary btn-sm fw-bold active' : 'btn btn-outline-primary btn-sm fw-bold';
    if (b7) b7.className = (cols === 7) ? 'btn btn-primary btn-sm fw-bold active' : 'btn btn-outline-primary btn-sm fw-bold';
}

function cambiarTurnoModalSabana(turno) {
    turnoFiltroModalSabana = turno;
    const fecha = document.getElementById('membrana_fecha').value || new Date().toISOString().split('T')[0];
    renderizarSabanaModalDia(registrosDiaCache, fecha);
}

function renderizarSabanaModalDia(registros, fechaStr) {
    const cont = document.getElementById('contenedorSabanaModalDia');
    if (!cont) return;

    const etapasDef = [
        { num: 1, paquetes: [1, 2, 3, 4, 9] },
        { num: 2, paquetes: [5, 6, 7, 8] },
        { num: 3, paquetes: [10, 11] },
        { num: 4, paquetes: [12, 13] }
    ];
    const ordenSerie = [1, 2, 3, 4, 9, 5, 6, 7, 8, 10, 11, 12, 13];

    // Mapear lecturas por hora ("HH:00") y paquete
    const mapaPorHoraPaq = {};
    (registros || []).forEach(r => {
        let hRaw = (r.hora || '').trim();
        let hNum = 0;
        if (hRaw.includes(':')) {
            hNum = parseInt(hRaw.split(':')[0], 10);
        } else if (r.fecha_registro && r.fecha_registro.includes(' ')) {
            hNum = parseInt(r.fecha_registro.split(' ')[1].split(':')[0], 10);
        }
        const hKey = String(isNaN(hNum) ? 0 : hNum).padStart(2, '0') + ':00';
        const pNum = parseInt(r.paquete_numero, 10);
        if (!mapaPorHoraPaq[hKey]) mapaPorHoraPaq[hKey] = {};
        mapaPorHoraPaq[hKey][pNum] = r;
    });

    const diasSemana = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    const mesesCortos = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    const partesF = fechaStr.split('-');
    const objFecha = new Date(parseInt(partesF[0], 10), parseInt(partesF[1], 10) - 1, parseInt(partesF[2], 10));
    const nombreDia = diasSemana[objFecha.getDay()] || '';
    const diaFormateado = `${partesF[2]}-${mesesCortos[objFecha.getMonth()]}-${partesF[0].slice(-2)}`;

    const turnosDef = [
        {
            key: 'matutino',
            nombre: 'Día',
            horas: ['07:00','08:00','09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00']
        },
        {
            key: 'nocturno',
            nombre: 'Noche',
            horas: ['19:00','20:00','21:00','22:00','23:00','00:00','01:00','02:00','03:00','04:00','05:00','06:00']
        }
    ];

    const clsModoTabla = (modoColsModalSabana === 2)
        ? 'modo-hoja-fisica'
        : (modoColsModalSabana === 4 ? 'modo-compacto' : '');

    let htmlTotal = '';

    turnosDef.forEach(t => {
        if (turnoFiltroModalSabana !== 'ambos' && turnoFiltroModalSabana !== t.key) return;

        // Recolectar operadores del turno
        const opsSet = new Set();
        t.horas.forEach(hk => {
            const mp = mapaPorHoraPaq[hk] || {};
            Object.values(mp).forEach(rm => {
                if (rm.operador_nomina) opsSet.add(rm.operador_nomina);
            });
        });
        const opsStr = opsSet.size > 0 ? Array.from(opsSet).join(', ') : 'Sin registro';

        htmlTotal += `
        <div class="bitacora-sabana-sheet">
            <div class="bitacora-sabana-top">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="bitacora-campo-linea">DÍA: <span>${diaFormateado}</span></div>
                        <div class="bitacora-campo-linea">FECHA: <span>${nombreDia}</span></div>
                    </div>
                    <div class="bitacora-titulo-caja">BITÁCORA DE OPERACIÓN DE MEMBRANAS</div>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="bitacora-campo-linea">TURNO: <span>${t.nombre}</span></div>
                        <div class="bitacora-campo-linea">OPERADOR: <span>${opsStr}</span></div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="tabla-sabana-membranas ${clsModoTabla}">
                    <thead>
                        <tr class="row-etapa">
                            <th rowspan="3" class="sticky-col-hora">HORA</th>
                            <th rowspan="3" style="min-width: 60px; border-right: 1.5px solid #000 !important;"># Lote /<br>Operador</th>
                            <th rowspan="3" class="borde-fin-etapa" style="min-width: 56px;">Sólidos<br>de entrada</th>
                            ${etapasDef.map(e => `<th colspan="${e.paquetes.length * modoColsModalSabana}" class="borde-fin-etapa">ETAPA ${e.num}</th>`).join('')}
                            <th rowspan="2" class="borde-fin-etapa" style="min-width: 62px; background-color: #e2e8f0 !important; color: #000 !important;">Salida de<br>Membranas</th>
                            <th rowspan="4" style="min-width: 80px;">OBSERVACIONES</th>
                        </tr>
                        <tr class="row-paquete">
                            ${etapasDef.map(e => e.paquetes.map((p, idx) => {
                                const clsB = (idx === e.paquetes.length - 1) ? 'borde-fin-etapa' : 'borde-fin-paquete';
                                return `<th colspan="${modoColsModalSabana}" class="${clsB}">Paquete ${p}</th>`;
                            }).join('')).join('')}
                        </tr>
                        <tr class="row-param-nom">
                            ${etapasDef.map(e => e.paquetes.map((p, idx) => {
                                const clsB = (idx === e.paquetes.length - 1) ? 'borde-fin-etapa' : 'borde-fin-paquete';
                                return `
                                    <th>Presión<br>Entrada</th>
                                    <th class="col-extra-memb">Presión<br>Salida</th>
                                    <th class="col-extra-memb">Delta<br>ΔP</th>
                                    <th class="col-extra-memb">Brix<br>Entrada</th>
                                    <th class="${clsB}">Sólidos<br>Salida</th>
                                    <th class="col-semaforo-memb">Permeado<br>(°Bx)</th>
                                    <th class="col-semaforo-memb ${clsB}">Temp.<br>(°C)</th>
                                `;
                            }).join('')).join('')}
                            <th class="borde-fin-etapa">%<br>Sólidos</th>
                        </tr>
                        <tr class="row-param-rango">
                            <th class="sticky-col-hora" style="font-size: 0.60rem !important;">MIN %</th>
                            <th class="th-rango-pendiente" style="border-right: 1.5px solid #000 !important;">Pendiente</th>
                            <th class="th-rango-pendiente borde-fin-etapa">Pendiente</th>
                            ${etapasDef.map(e => e.paquetes.map((p, idx) => {
                                const clsB = (idx === e.paquetes.length - 1) ? 'borde-fin-etapa' : 'borde-fin-paquete';
                                return `
                                    <th class="th-rango-pendiente">Pendiente</th>
                                    <th class="th-rango-pendiente col-extra-memb">Pendiente</th>
                                    <th class="th-rango-pendiente col-extra-memb">Pendiente</th>
                                    <th class="th-rango-pendiente col-extra-memb">Pendiente</th>
                                    <th class="th-rango-pendiente ${clsB}">Pendiente</th>
                                    <th class="th-rango-semaforo col-semaforo-memb">&lt;0.1 V<br><span style="font-size:0.53rem;">0.1-0.5 A | &gt;0.5 R</span></th>
                                    <th class="th-rango-semaforo col-semaforo-memb ${clsB}">45-55 °C<br><span style="font-size:0.53rem;">&lt;39 / &gt;60 R</span></th>
                                `;
                            }).join('')).join('')}
                            <th class="th-rango-pendiente borde-fin-etapa">Pendiente</th>
                        </tr>
                    </thead>
                    <tbody>
        `;

        t.horas.forEach(hk => {
            const mp = mapaPorHoraPaq[hk] || {};
            let opHora = '';
            let solidosIn = '';
            let solidosOutFin = '';
            const obsArr = [];

            ordenSerie.forEach(pNum => {
                const rm = mp[pNum];
                if (rm) {
                    if (!opHora && rm.operador_nomina) opHora = rm.operador_nomina;
                    if (rm.observaciones) obsArr.push(`P${pNum}: ${rm.observaciones}`);
                    if ((rm.estado || 'OPERACION').toUpperCase() === 'OPERACION') {
                        if (solidosIn === '' && rm.brix_entrada !== null && rm.brix_entrada !== '') {
                            solidosIn = parseFloat(rm.brix_entrada).toFixed(1);
                        }
                        if (rm.brix_salida !== null && rm.brix_salida !== '') {
                            solidosOutFin = parseFloat(rm.brix_salida).toFixed(1);
                        }
                    }
                }
            });

            htmlTotal += `
                <tr>
                    <td class="sticky-col-hora font-monospace">${hk}</td>
                    <td class="cell-matrix-blanco" style="border-right: 1.5px solid #000 !important;">${opHora || '/'}</td>
                    <td class="${solidosIn !== '' ? 'cell-matrix-blanco' : 'cell-matrix-empty'} borde-fin-etapa">${solidosIn !== '' ? solidosIn : '/'}</td>
            `;

            etapasDef.forEach(e => {
                e.paquetes.forEach((pNum, idx) => {
                    const clsB = (idx === e.paquetes.length - 1) ? 'borde-fin-etapa' : 'borde-fin-paquete';
                    const rm = mp[pNum];
                    if (!rm) {
                        htmlTotal += `
                            <td class="cell-matrix-empty">/</td>
                            <td class="cell-matrix-empty col-extra-memb">/</td>
                            <td class="cell-matrix-empty col-extra-memb">/</td>
                            <td class="cell-matrix-empty col-extra-memb">/</td>
                            <td class="cell-matrix-empty ${clsB}">/</td>
                            <td class="cell-matrix-empty col-semaforo-memb">/</td>
                            <td class="cell-matrix-empty col-semaforo-memb ${clsB}">/</td>
                        `;
                    } else {
                        const est = (rm.estado || 'OPERACION').toUpperCase().trim();
                        if (['LAVADO', 'MMT', 'MMTT', 'FO', 'F.O.'].includes(est)) {
                            let clsEst = 'cell-matrix-rojo';
                            let txtEst = 'F.O.';
                            if (est === 'LAVADO') { clsEst = 'cell-matrix-lavado'; txtEst = 'LAVADO'; }
                            else if (est === 'MMT' || est === 'MMTT') { clsEst = 'cell-matrix-amarillo'; txtEst = 'MMTT'; }
                            htmlTotal += `<td colspan="${modoColsModalSabana}" class="${clsEst} ${clsB}">${txtEst}</td>`;
                        } else {
                            const vPin = (rm.presion_entrada !== null && rm.presion_entrada !== '') ? Math.round(parseFloat(rm.presion_entrada)).toString() : '/';
                            const vPout = (rm.presion_salida !== null && rm.presion_salida !== '') ? parseFloat(rm.presion_salida).toFixed(1) : '/';
                            const vDp = (rm.delta_presion !== null && rm.delta_presion !== '') ? parseFloat(rm.delta_presion).toFixed(1) : '/';
                            const vBin = (rm.brix_entrada !== null && rm.brix_entrada !== '') ? parseFloat(rm.brix_entrada).toFixed(1) : '/';
                            const vBout = (rm.brix_salida !== null && rm.brix_salida !== '') ? parseFloat(rm.brix_salida).toFixed(1) : '/';

                            let vPerm = '/';
                            let clsPerm = 'cell-matrix-empty';
                            if (rm.brix_permeado !== null && rm.brix_permeado !== '') {
                                const bp = parseFloat(rm.brix_permeado);
                                vPerm = bp.toFixed(2);
                                if (bp < 0.1) clsPerm = 'cell-matrix-verde';
                                else if (bp <= 0.5) clsPerm = 'cell-matrix-amarillo';
                                else clsPerm = 'cell-matrix-rojo';
                            }

                            let vTemp = '/';
                            let clsTemp = 'cell-matrix-empty';
                            if (rm.temperatura !== undefined && rm.temperatura !== null && rm.temperatura !== '') {
                                const tv = parseFloat(rm.temperatura);
                                vTemp = tv.toFixed(1);
                                if (tv >= 45 && tv <= 55) clsTemp = 'cell-matrix-verde';
                                else if ((tv >= 40 && tv < 45) || (tv > 55 && tv <= 60)) clsTemp = 'cell-matrix-amarillo';
                                else clsTemp = 'cell-matrix-rojo';
                            }

                            htmlTotal += `
                                <td class="${vPin !== '/' ? 'cell-matrix-blanco' : 'cell-matrix-empty'}">${vPin}</td>
                                <td class="col-extra-memb ${vPout !== '/' ? 'cell-matrix-blanco' : 'cell-matrix-empty'}">${vPout}</td>
                                <td class="col-extra-memb ${vDp !== '/' ? 'cell-matrix-blanco' : 'cell-matrix-empty'}">${vDp}</td>
                                <td class="col-extra-memb ${vBin !== '/' ? 'cell-matrix-blanco' : 'cell-matrix-empty'}">${vBin}</td>
                                <td class="${vBout !== '/' ? 'cell-matrix-blanco' : 'cell-matrix-empty'} ${clsB}">${vBout}</td>
                                <td class="col-semaforo-memb ${clsPerm}">${vPerm}</td>
                                <td class="col-semaforo-memb ${clsTemp} ${clsB}">${vTemp}</td>
                            `;
                        }
                    }
                });
            });

            const obsTxt = obsArr.length > 0 ? obsArr.join(' | ') : '/';
            htmlTotal += `
                    <td class="${solidosOutFin !== '' ? 'cell-matrix-blanco' : 'cell-matrix-empty'} borde-fin-etapa">${solidosOutFin !== '' ? solidosOutFin : '/'}</td>
                    <td class="cell-matrix-blanco text-truncate" style="max-width: 140px;" title="${obsTxt.replace(/"/g, '&quot;')}">${obsTxt}</td>
                </tr>
            `;
        });

        htmlTotal += `
                    </tbody>
                </table>
            </div>
        </div>`;
    });

    cont.innerHTML = htmlTotal;
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

        let bPermHtml = '-';
        if (r.brix_permeado !== null && r.brix_permeado !== '') {
            const bpVal = parseFloat(r.brix_permeado);
            let bpCls = 'bg-success text-white';
            if (bpVal > 0.5) bpCls = 'bg-danger text-white';
            else if (bpVal >= 0.1) bpCls = 'bg-warning text-dark';
            bPermHtml = `<span class="badge ${bpCls} px-2 py-1 font-monospace fw-bold">${bpVal.toFixed(2)} °Bx</span>`;
        }

        let tempHtml = '-';
        if (r.temperatura !== undefined && r.temperatura !== null && r.temperatura !== '') {
            const tVal = parseFloat(r.temperatura);
            let tCls = 'bg-success text-white';
            if (tVal < 40 || tVal > 60) tCls = 'bg-danger text-white';
            else if ((tVal >= 40 && tVal < 45) || (tVal > 55 && tVal <= 60)) tCls = 'bg-warning text-dark';
            tempHtml = `<span class="badge ${tCls} px-2 py-1 font-monospace fw-bold">${tVal.toFixed(1)} °C</span>`;
        }

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
            <td>${bPermHtml}</td>
            <td>${tempHtml}</td>
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

// Cargar lecturas existentes al inicializar la página y vigilar cambio de hora automático
document.addEventListener('DOMContentLoaded', function() {
    consultarLecturasPrevias();
    setInterval(verificarHoraAutomatica, 15000);
});
</script>

<!-- MODAL DE REGISTROS DEL DÍA (BITÁCORA DE OPERACIÓN DE MEMBRANAS TIPO HOJA FÍSICA) -->
<div class="modal fade" id="modalRegistrosDia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" style="max-width: 98vw; width: 98vw;">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-table fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold m-0" id="tituloModalRegistrosDia">Bitácora de Operación de Membranas</h5>
                        <small class="text-white-50">Formato de Hoja Física con parámetros en Pendiente y valores en fondo blanco y letra negra</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 bg-light">
                <!-- Barra Superior de Modo de Vista y Columnas -->
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 bg-white p-2 rounded-3 border shadow-sm">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" id="btnModalVistaSabana" class="btn btn-dark btn-sm fw-bold active" onclick="cambiarVistaInternaModalDia('sabana')">
                                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Bitácora Hoja Física (Sábana)
                            </button>
                            <button type="button" id="btnModalVistaLista" class="btn btn-outline-dark btn-sm fw-bold" onclick="cambiarVistaInternaModalDia('lista')">
                                <i class="bi bi-list-ul me-1"></i> Lista Detallada
                            </button>
                        </div>

                        <div class="btn-group btn-group-sm" id="grpColsModalSabana" role="group">
                            <button type="button" id="btnModalCols2" class="btn btn-primary btn-sm fw-bold active" onclick="cambiarColsModalSabana(2)">
                                Hoja Física (Presión + Sólidos)
                            </button>
                            <button type="button" id="btnModalCols4" class="btn btn-outline-primary btn-sm fw-bold" onclick="cambiarColsModalSabana(4)">
                                + Permeado y Temp (4 Cols)
                            </button>
                            <button type="button" id="btnModalCols7" class="btn btn-outline-primary btn-sm fw-bold" onclick="cambiarColsModalSabana(7)">
                                Expandida (7 Cols)
                            </button>
                        </div>

                        <select class="form-select form-select-sm fw-bold" style="width: auto;" onchange="cambiarTurnoModalSabana(this.value)">
                            <option value="ambos">Ambos Turnos (Día y Noche)</option>
                            <option value="matutino">Solo Turno Día (07:00 - 18:00)</option>
                            <option value="nocturno">Solo Turno Noche (19:00 - 06:00)</option>
                        </select>
                    </div>

                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary rounded-pill px-3 py-2 small" id="conteoRegistrosModal">Cargando...</span>
                    </div>
                </div>

                <!-- VISTA 1: BITÁCORA SÁBANA (HOJA FÍSICA) POR DEFECTO -->
                <div id="wrapperSabanaModalDia" style="max-height: 74vh; overflow-y: auto;">
                    <div id="contenedorSabanaModalDia"></div>
                </div>

                <!-- VISTA 2: LISTA DETALLADA SECUNDARIA -->
                <div id="wrapperListaModalDia" class="d-none">
                    <div class="row g-2 mb-3 align-items-center">
                        <div class="col-md-6">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" id="buscadorModalRegistros" class="form-control border-start-0" placeholder="Buscar por paquete, hora, operador, estado..." onkeyup="filtrarTablaModalRegistros()">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <select id="filtroEtapaModalRegistros" class="form-select form-select-sm fw-bold" onchange="filtrarTablaModalRegistros()">
                                <option value="">Todas las Etapas</option>
                                <option value="1">Etapa 1 (Paq 1, 2, 3, 4, 9)</option>
                                <option value="2">Etapa 2 (Paq 5, 6, 7, 8)</option>
                                <option value="3">Etapa 3 (Paq 10, 11)</option>
                                <option value="4">Etapa 4 (Paq 12, 13)</option>
                            </select>
                        </div>
                    </div>

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
                                    <th>Temperatura</th>
                                    <th>Observaciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyModalRegistros" style="font-size: 0.85rem;">
                                <tr>
                                    <td colspan="13" class="py-4 text-center text-muted">
                                        <div class="spinner-border spinner-border-sm text-primary me-2"></div> Cargando registros del día...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-2 px-4 justify-content-between">
                <div class="d-flex gap-2">
                    <a href="historial.php?id=100" class="btn btn-dark btn-sm fw-bold rounded-pill px-3">
                        <i class="bi bi-clock-history me-1"></i> Abrir Historial Completo / Imprimir
                    </a>
                    <a href="reporte_general.php?fecha=<?= date('Y-m-d') ?>" class="btn btn-outline-primary btn-sm fw-bold rounded-pill px-3">
                        <i class="bi bi-grid-3x3-gap-fill me-1"></i> Ir a Reporte General
                    </a>
                </div>
                <button type="button" class="btn btn-secondary btn-sm fw-bold rounded-pill px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
