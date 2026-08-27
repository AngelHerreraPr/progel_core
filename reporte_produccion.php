<?php
session_start();
include 'config/db.php';

$rol_actual = $_SESSION['rol'] ?? 'OPERADOR';
$roles_permitidos = ['SUPERVISOR', 'ADMIN', 'JEFATURA'];

if (!isset($_SESSION['nomina']) || !in_array($rol_actual, $roles_permitidos)) {
    header("Location: index.php?error=acceso_denegado");
    exit();
}

include 'includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-4 rounded shadow-sm border-start border-5 border-success">
        <div>
            <h2 class="fw-bold text-dark m-0"><i class="bi bi-shield-check text-success me-2"></i>Reporte de Producción (Supervisor)</h2>
            <p class="text-muted mb-0">Panel exclusivo de supervisión y control gerencial de planta.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary fw-bold">
            <i class="bi bi-arrow-left"></i> Volver al Menú
        </a>
    </div>

    <div class="card shadow-sm border-0 rounded-4 p-4 bg-white">
        <h4 class="fw-bold text-primary mb-3">Estancia Restringida</h4>
        <p class="text-secondary">Bienvenido, <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong>. Esta sección cuenta con privilegios de nivel <strong><?= htmlspecialchars($rol_actual) ?></strong>.</p>

        <div class="alert alert-success mt-3">
            <i class="bi bi-info-circle-fill me-2"></i> Aquí puedes integrar indicadores avanzados, métricas de eficiencia o accesos directos a los reportes maestros de planta.
        </div>

        <div class="mt-4">
            <a href="reporte_maestro.php" class="btn btn-primary fw-bold px-4 py-2 shadow-sm">
                <i class="bi bi-layout-text-window-reverse me-2"></i> Ir al Reporte Maestro de Sábana
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
