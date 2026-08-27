<?php  
session_start(); 

// Activar reporte de errores temporalmente para diagnosticar si algo falla
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['nomina'])) {     
    header("Location: login.php");     
    exit(); 
} 

include 'config/db.php';  
include 'includes/header.php'; 
?>

<style>
    body {
        background-color: #eef2f7;
        font-family: 'Inter', system-ui, -apple-system, sans-serif;
        color: #1e293b;
    }
    .map-header {
        background: #ffffff;
        border-radius: 16px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid #e2e8f0;
    }
    .user-banner {
        background: #ffffff;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }
    /* Tarjetas de zona con fondo blanco sólido y bordes distintivos */
    .zona-card {
        background: #ffffff !important;
        border-radius: 16px !important;
        padding: 2.2rem 1.5rem !important;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05) !important;
        border: 1px solid #e2e8f0 !important;
        text-decoration: none !important;
        transition: all 0.25s ease-in-out !important;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        min-height: 160px;
        color: #1e293b !important;
    }
    .zona-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
        border-color: #3b82f6 !important;
    }
    .zona-card i {
        font-size: 2.5rem;
        margin-bottom: 0.8rem;
    }
    .zona-card.blanca { border-left: 6px solid #198754 !important; }
    .zona-card.gris   { border-left: 6px solid #6c757d !important; }
    .zona-card.negra  { border-left: 6px solid #212529 !important; }
</style>

<div class="container py-4">
    <div class="map-header text-center">
        <h2 class="fw-bold text-dark m-0">Mapa de Zonas Disponibles</h2>
        <p class="text-muted mt-1 mb-0">Selecciona la zona en donde se encuentra el equipo</p>
    </div>

    <div class="user-banner d-flex justify-content-between align-items-center p-3 mb-4">
        <div>
            <span class="text-muted small fw-bold text-uppercase">Usuario Conectado:</span>
            <h5 class="m-0 fw-bold text-primary">
                <?= htmlspecialchars($_SESSION['nombre'] ?? 'Usuario') ?> 
                <span class="badge bg-secondary ms-2" style="font-size: 0.75rem;"><?= htmlspecialchars($_SESSION['rol'] ?? 'OPERADOR') ?></span>
            </h5>
        </div>
        <a href="logout.php" class="btn btn-danger btn-sm fw-bold rounded-pill px-3 shadow-sm">
            <i class="bi bi-box-arrow-right me-1"></i> Cerrar Sesión
        </a>
    </div>

    <?php 
    $rol_usuario = $_SESSION['rol'] ?? 'OPERADOR'; 
    // Acceso directo a reportes para roles de supervisión/administración
    if (in_array($rol_usuario, ['ADMIN', 'JEFATURA', 'SUPERVISOR'])):
    ?>
        <div class="col-12 mb-4">
            <div class="card shadow-sm border-0 text-white rounded-4 overflow-hidden" style="background: linear-gradient(135deg, #198754, #157347);">
                <div class="card-body d-flex justify-content-between align-items-center p-4">
                    <div>
                        <h4 class="fw-bold mb-1"><i class="bi bi-clipboard-data me-2"></i> Estancia de Producción</h4>
                        <p class="mb-0 text-light small">Módulo exclusivo de supervisión y control operativo.</p>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="reporte_general.php" class="btn btn-light text-success fw-bold px-3 py-2 shadow-sm rounded-pill">
                            <i class="bi bi-grid-3x3-gap-fill me-1"></i> Reporte Operativo
                        </a>
                        <a href="reporte_maestro.php" class="btn btn-dark text-white fw-bold px-3 py-2 shadow-sm rounded-pill">
                            <i class="bi bi-layout-text-window-reverse me-1"></i> Reporte Maestro
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
        <?php     
        $rol = $_SESSION['rol'] ?? 'OPERADOR';     
        $zona_asignada = strtoupper(trim($_SESSION['zona'] ?? ''));     
        
        $es_admin_o_jefe = in_array($rol, ['ADMIN', 'JEFATURA']);     
        
        // Verificación flexible de zona asignada (soporta nombres directos o múltiples)
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
                    $clase_borde = 'blanca';
                    $color_icono = 'text-success';
                    if (stripos($nombre_zona, 'Gris') !== false) { 
                        $clase_borde = 'gris'; 
                        $color_icono = 'text-secondary'; 
                    }
                    if (stripos($nombre_zona, 'Negra') !== false) { 
                        $clase_borde = 'negra'; 
                        $color_icono = 'text-dark'; 
                    }
            ?>         
                <div class="col-md-4">             
                    <a href="zona.php?id=<?= $id_zona ?>" class="zona-card <?= $clase_borde ?>">                 
                        <i class="bi bi-building-fill <?= $color_icono ?>"></i>
                        <span class="fw-bold fs-5 text-dark"><?= htmlspecialchars($nombre_zona) ?></span>
                        <small class="text-muted mt-1">Ingresar al área</small>
                    </a>         
                </div>     
            <?php         
                }     
            }
        } else {
            echo '<div class="col-12 text-center text-muted py-4">No hay zonas configuradas en la base de datos.</div>';
        }
        ?> 
    </div>
</div> 

<?php include 'includes/footer.php'; ?>