<?php  
session_start(); 

// Activar reporte de errores temporalmente para desarrollo
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['nomina'])) {     
    header("Location: login.php");     
    exit(); 
} 

include 'config/db.php';  
include 'includes/header.php'; 

// ==========================================
// CONFIGURACIÓN VISUAL DEL ROL DE USUARIO
// ==========================================
$rol_usuario = strtoupper(trim($_SESSION['rol'] ?? 'OPERADOR'));

switch ($rol_usuario) {
    case 'ADMIN':
        $badge_rol_class = 'badge-rol-admin';
        $icono_rol = 'bi-shield-lock-fill';
        $texto_rol = 'Administrador General';
        break;
    case 'SUPERVISOR':
        $badge_rol_class = 'badge-rol-supervisor';
        $icono_rol = 'bi-person-badge-fill';
        $texto_rol = 'Supervisor de Planta';
        break;
    case 'JEFATURA':
        $badge_rol_class = 'badge-rol-jefatura';
        $icono_rol = 'bi-award-fill';
        $texto_rol = 'Jefatura de Operación';
        break;
    case 'VISITANTE':
    case 'LECTURA':
        $badge_rol_class = 'badge-rol-visitante';
        $icono_rol = 'bi-eye-fill';
        $texto_rol = 'Visitante (Solo Lectura)';
        break;
    default:
        $badge_rol_class = 'badge-rol-operador';
        $icono_rol = 'bi-person-gear';
        $texto_rol = 'Operador de Planta';
        break;
}
?>

<style>
    :root {
        --primary-blue: #0d6efd;
        --dark-slate: #1e293b;
        --border-color: #e2e8f0;
    }

    body {
        background: linear-gradient(135deg, #f0f4f8 0%, #e2e8f0 100%);
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: #1e293b;
        min-height: 100vh;
    }

    /* Hero Banner Principal */
    .hero-panel {
        background: #ffffff;
        border-radius: 20px;
        padding: 1.8rem 2.2rem;
        margin-bottom: 2rem;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
        border: 1px solid rgba(255, 255, 255, 0.8);
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 1.5rem;
    }

    /* Badges de Roles Visuales y Distintivos */
    .badge-rol {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.35rem 0.9rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 800;
        letter-spacing: 0.5px;
        text-transform: uppercase;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
    }
    .badge-rol-admin {
        background: linear-gradient(135deg, #6366f1, #4f46e5);
        color: #ffffff;
        border: 1px solid #4338ca;
    }
    .badge-rol-supervisor {
        background: linear-gradient(135deg, #0284c7, #0369a1);
        color: #ffffff;
        border: 1px solid #075985;
    }
    .badge-rol-jefatura {
        background: linear-gradient(135deg, #d97706, #b45309);
        color: #ffffff;
        border: 1px solid #92400e;
    }
    .badge-rol-operador {
        background: linear-gradient(135deg, #10b981, #059669);
        color: #ffffff;
        border: 1px solid #047857;
    }
    .badge-rol-visitante {
        background: linear-gradient(135deg, #64748b, #475569);
        color: #ffffff;
        border: 1px solid #334155;
    }

    .user-info-box {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: #f8fafc;
        padding: 0.4rem 1rem;
        border-radius: 50px;
        border: 1px solid #cbd5e1;
        font-size: 0.85rem;
        font-weight: 700;
        color: #334155;
    }

    /* Card de Supervisión */
    .supervision-card {
        background: linear-gradient(135deg, #0f766e 0%, #115e59 100%);
        border-radius: 20px;
        border: none;
        box-shadow: 0 10px 25px rgba(15, 118, 110, 0.2);
        color: white;
        overflow: hidden;
        position: relative;
    }

    .supervision-card::after {
        content: '\F2B4';
        font-family: 'bootstrap-icons';
        position: absolute;
        right: -20px;
        bottom: -30px;
        font-size: 9rem;
        color: rgba(255, 255, 255, 0.07);
        pointer-events: none;
    }

    /* Tarjetas de Zona */
    .zona-card {
        background: #ffffff !important;
        border-radius: 20px !important;
        padding: 2.2rem 1.8rem !important;
        box-shadow: 0 8px 24px rgba(149, 157, 165, 0.08) !important;
        border: 1px solid rgba(226, 232, 240, 0.8) !important;
        text-decoration: none !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        min-height: 220px;
    }

    .zona-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 16px 32px rgba(0, 0, 0, 0.12) !important;
    }

    .icon-wrapper {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2rem;
        margin-bottom: 1.2rem;
        transition: transform 0.3s ease;
    }

    .zona-card:hover .icon-wrapper {
        transform: scale(1.1);
    }

    .zona-card.blanca .icon-wrapper { background: #dcfce7; color: #16a34a; }

    .zona-card.gris .icon-wrapper { background: #f1f5f9; color: #475569; }

    .zona-card.negra .icon-wrapper { background: #e2e8f0; color: #0f172a; }

    .btn-enter-zone {
        font-size: 0.85rem;
        font-weight: 700;
        border-radius: 30px;
        padding: 0.4rem 1.2rem;
        margin-top: 1rem;
        transition: all 0.2s;
    }
</style>

<div class="container py-4">

    <!-- Header con Rol e Información del Usuario -->
    <div class="hero-panel">
        <div>
            <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                <span class="badge-rol <?= $badge_rol_class ?>">
                    <i class="bi <?= $icono_rol ?>"></i> <?= htmlspecialchars($texto_rol) ?>
                </span>
                <span class="user-info-box">
                    <i class="bi bi-person-fill text-primary"></i> <?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?>
                    <span class="text-muted small">| Nómina: <?= htmlspecialchars($_SESSION['nomina'] ?? 'N/A') ?></span>
                </span>
            </div>
            <h2 class="fw-black text-dark m-0" style="font-weight: 800; letter-spacing: -0.5px;">Mapa de Zonas y Módulos</h2>
            <p class="text-muted m-0 small mt-1">Selecciona el área de trabajo correspondiente para ingresar.</p>
        </div>
        <div>
            <a href="logout.php" class="btn btn-outline-danger fw-bold rounded-pill px-4 shadow-sm">
                <i class="bi bi-box-arrow-right me-1"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <!-- Panel Exclusivo para Supervisión y Roles Privilegiados -->
    <?php if (in_array($rol_usuario, ['ADMIN', 'JEFATURA', 'SUPERVISOR'])): ?>
        <div class="col-12 mb-4">
            <div class="card supervision-card p-4">
                <div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3 p-0 position-relative" style="z-index: 2;">
                    <div>
                        <span class="badge bg-white bg-opacity-20 text-black px-3 py-1 rounded-pill mb-2 fw-bold">
                            <i class="bi bi-stars me-1"></i> Panel de Supervisión
                        </span>
                        <h4 class="fw-bold mb-1">Estancia y Monitoreo General de Planta</h4>
                        <p class="mb-0 text-white-50 small">Acceso centralizado para el control gerencial, sábanas de producción y reportes consolidados.</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="reporte_general.php" class="btn btn-light fw-bold px-4 py-2 shadow-sm rounded-pill" style="color: #0f766e;">
                            <i class="bi bi-grid-3x3-gap-fill me-1"></i> Reporte Operativo
                        </a>
                        <a href="reporte_maestro.php" class="btn btn-dark fw-bold px-4 py-2 shadow-sm rounded-pill">
                            <i class="bi bi-layout-text-window-reverse me-1"></i> Reporte Hora por Hora
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Cuadrícula de Zonas -->
    <div class="row g-4 justify-content-center">
        <?php     
        $zona_asignada = strtoupper(trim($_SESSION['zona'] ?? ''));     
        $es_admin_o_jefe = in_array($rol_usuario, ['ADMIN', 'JEFATURA']);     
        
        $ver_blanca = $es_admin_o_jefe || ($zona_asignada === 'TODAS') || (strpos($zona_asignada, 'BLANCA') !== false);     
        $ver_gris   = $es_admin_o_jefe || ($zona_asignada === 'TODAS') || (strpos($zona_asignada, 'GRIS') !== false);     
        $ver_negra  = $es_admin_o_jefe || ($zona_asignada === 'TODAS') || (strpos($zona_asignada, 'NEGRA') !== false);     
        
        $zonas_result = mysqli_query($conn, "SELECT id, nombre FROM zonas ORDER BY id ASC");     
        
        if ($zonas_result && mysqli_num_rows($zonas_result) > 0) {
            while ($zona = mysqli_fetch_assoc($zonas_result)) {         
                $nombre_zona = trim($zona['nombre']);         
                $id_zona = $zona['id'];         
                
                $mostrar_zona = false;         
                if (stripos($nombre_zona, 'Blanca') !== false && $ver_blanca) $mostrar_zona = true;         
                if (stripos($nombre_zona, 'Gris') !== false && $ver_gris) $mostrar_zona = true;         
                if (stripos($nombre_zona, 'Negra') !== false && $ver_negra) $mostrar_zona = true;         
                
                if ($mostrar_zona) {                          
                    $clase_card = 'blanca';
                    $icono = 'bi-wind';
                    $btn_class = 'btn-outline-success';
                    $desc = 'Secadores, Votators y Líneas de Secado';

                    if (stripos($nombre_zona, 'Gris') !== false) { 
                        $clase_card = 'gris'; 
                        $icono = 'bi-layers-half';
                        $btn_class = 'btn-outline-secondary';
                        $desc = 'Concentradores, Clarificación y Membranas';
                    }
                    if (stripos($nombre_zona, 'Negra') !== false) { 
                        $clase_card = 'negra'; 
                        $icono = 'bi-snow';
                        $btn_class = 'btn-outline-dark';
                        $desc = 'Chillers, Compresores y Servicios';
                    }
            ?>         
                <div class="col-md-6 col-lg-4">             
                    <a href="zona.php?id=<?= $id_zona ?>" class="zona-card <?= $clase_card ?>">                 
                        <div class="icon-wrapper">
                            <i class="bi <?= $icono ?>"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($nombre_zona) ?></h4>
                        <p class="text-muted small mb-0 px-2"><?= $desc ?></p>
                        <span class="btn btn-sm <?= $btn_class ?> btn-enter-zone">
                            Ingresar a la Zona <i class="bi bi-arrow-right-short ms-1"></i>
                        </span>
                    </a>         
                </div>     
            <?php         
                }     
            }
        } else {
            echo '<div class="col-12 text-center text-muted py-5 bg-white rounded-4 shadow-sm">No hay zonas configuradas en la base de datos.</div>';
        }
        ?> 
    </div>
</div> 

<?php include 'includes/footer.php';