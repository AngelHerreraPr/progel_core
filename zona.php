<?php  
session_start(); 
include 'config/db.php';  

if(!isset($_GET['id']) || !is_numeric($_GET['id'])) {     
    header("Location: index.php");     
    exit(); 
} 

$zona_id = intval($_GET['id']); 
$stmt_zona = mysqli_prepare($conn, "SELECT * FROM zonas WHERE id = ?"); 
mysqli_stmt_bind_param($stmt_zona, "i", $zona_id); 
mysqli_stmt_execute($stmt_zona); 
$result_zona = mysqli_stmt_get_result($stmt_zona); 
$zona = mysqli_fetch_assoc($result_zona); 

if(!$zona) {     
    header("Location: index.php");     
    exit(); 
} 

include 'includes/header.php'; 
?>

<style>
    .zona-header {
        background: white;
        border-radius: 15px;
        padding: 1.5rem 2rem;
        box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        border: 1px solid rgba(0,0,0,0.03);
    }
    .panel-equipos {
        background: #ffffff;
        border-radius: 16px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.04);
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    .panel-equipos-header {
        background: #1e293b;
        color: white;
        padding: 1.2rem 1.8rem;
        font-weight: 800;
    }
    /* Tarjetas de equipos con fondo completamente blanco, sombra suave y bordes limpios */
    .equipo-card-base { 
        display: flex; 
        flex-direction: column; 
        align-items: center; 
        justify-content: center; 
        width: 100%; 
        height: 100%; 
        padding: 1.5rem 1rem; 
        background-color: #ffffff !important; 
        color: #1e293b !important; 
        border-radius: 12px !important; 
        text-decoration: none !important; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.04) !important; 
        border: 1px solid #e2e8f0 !important; 
        transition: all 0.2s ease-in-out; 
        min-height: 125px; 
    } 
    .equipo-card-base:hover { 
        transform: translateY(-4px); 
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08) !important; 
        border-color: #3b82f6 !important;
    }
</style>

<div class="container-fluid py-4 px-4">
    <div class="zona-header d-flex justify-content-between align-items-center mb-4">
        <div>         
            <h2 class="fw-bold text-dark m-0">Zona: <?= htmlspecialchars($zona['nombre']) ?></h2>         
            <span class="text-muted small">Selecciona el equipo o módulo que deseas capturar.</span>     
        </div>     
        <a href="index.php" class="btn btn-outline-dark fw-bold shadow-sm rounded-pill px-4">
            <i class="bi bi-arrow-left me-1"></i> Volver al Mapa
        </a> 
    </div> 

    <div class="panel-equipos mb-5">     
        <div class="panel-equipos-header">         
            <h5 class="m-0 fw-bold"><i class="bi bi-cpu-fill me-2 text-warning"></i> Panel de Control de Equipos</h5>     </div>     
        <div class="card-body p-4 bg-light">                  
            <?php         
            $stmt_areas = mysqli_prepare($conn, "SELECT * FROM areas WHERE zona_id = ? ORDER BY id ASC");         
            mysqli_stmt_bind_param($stmt_areas, "i", $zona_id);         
            mysqli_stmt_execute($stmt_areas);         
            $areas = mysqli_stmt_get_result($stmt_areas);         
            
            if (mysqli_num_rows($areas) == 0) {             
                echo "<div class='alert alert-secondary text-center fw-bold my-3'>No hay áreas ni equipos registrados en esta zona.</div>";         
            }         
            
            while($a = mysqli_fetch_assoc($areas)) {              
                $stmt_equipos = mysqli_prepare($conn, "SELECT * FROM equipos WHERE area_id = ? ORDER BY id ASC");             
                mysqli_stmt_bind_param($stmt_equipos, "i", $a['id']);             
                mysqli_stmt_execute($stmt_equipos);             
                $equipos_result = mysqli_stmt_get_result($stmt_equipos);             
                
                if (mysqli_num_rows($equipos_result) > 0) {         
            ?>             
                <div class="d-flex align-items-center mb-3 mt-4">                 
                    <h5 class="text-secondary fw-bold m-0 text-uppercase" style="font-size: 0.9rem; letter-spacing: 0.5px;">                      
                        <i class="bi bi-diagram-3-fill text-primary me-2"></i> Área: <?= htmlspecialchars($a['nombre']) ?>                 
                    </h5>                 
                    <hr class="flex-grow-1 ms-3 my-0 border-secondary opacity-25">             
                </div>             
                <div class="row g-3 mb-4">                 
                    <?php                 
                    $has_rendered_votator_unificado = false;                                  
                    $votator_ids_unificados = [84, 85, 86, 87, 88, 89];                                   
                    $votator_maestro_viejo_id = [8];                  
                    $has_rendered_votator_maestro_viejo = false;                 
                    
                    while ($eq = mysqli_fetch_assoc($equipos_result)) {                     
                        $id_eq = $eq['id'];                     
                        $nombre_eq = $eq['nombre'];                     
                        $n_low = mb_strtolower($nombre_eq, 'UTF-8');                     
                        
                        $rol_usuario = $_SESSION['rol'] ?? 'OPERADOR';                     
                        $zona_asignada = $_SESSION['zona'] ?? '';                                          
                        $es_rol_privilegiado = in_array($rol_usuario, ['ADMIN', 'JEFATURA', 'SUPERVISOR']);                     
                        $es_de_zona_negra = in_array($zona_asignada, ['ZONA_NEGRA', 'TODAS']);                     
                        
                        if (!$es_rol_privilegiado && !$es_de_zona_negra) {                         
                            if (in_array($id_eq, [12, 13])) continue;                     
                        }                     
                        
                        if (in_array($id_eq, $votator_ids_unificados) || (strpos($n_low, 'votator') !== false && strpos($n_low, 'chiller') === false && !in_array($id_eq, $votator_maestro_viejo_id))) {                         
                            if (!$has_rendered_votator_unificado) {                 
                    ?>                             
                                <div class="col-md-6 col-lg-4">                                  
                                    <a href="captura.php?id=84" class="equipo-card-base border-start border-4 border-danger">                                     
                                        <span class="badge bg-danger text-white mb-2 px-3 py-1 fs-6">Bloque Unificado</span>                                     
                                        <span class="fw-bold fs-6 text-dark text-center">Votators</span>                                     
                                        <small class="text-muted mt-1 text-center">Captura y Control de Línea</small>                                 
                                    </a>                             
                                </div>                 
                    <?php                             
                                $has_rendered_votator_unificado = true;                         
                            }                         
                            continue;                     
                        }                     
                        
                        if (in_array($id_eq, $votator_maestro_viejo_id)) {                         
                            if (!$has_rendered_votator_maestro_viejo) {                 
                    ?>                             
                                <div class="col-md-6 col-lg-4">                                  
                                    <a href="captura.php?id=8" class="equipo-card-base border-start border-4 border-danger">                                     
                                        <span class="fw-bold fs-6 text-dark text-center">Sistema Votator (Maestro Viejo)</span>                                     
                                        <small class="text-muted mt-1 text-center">Control de Temperaturas</small>                                 
                                    </a>                             
                                </div>                 
                    <?php                             
                                $has_rendered_votator_maestro_viejo = true;                         
                            }                         
                            continue;                     
                        }                     
                        
                        if ($id_eq == 91 || $id_eq == 92) {                         
                            $titulo = ($id_eq == 91) ? 'Chillers Normales (1 al 4)' : 'Chillers Normales (5 al 7)';                 
                    ?>                         
                            <div class="col-md-6 col-lg-4">                              
                                <a href="captura.php?id=<?= $id_eq ?>" class="equipo-card-base border-start border-4 border-primary">                                 
                                    <span class="fw-bold fs-6 text-dark text-center"><?= $titulo ?></span>                                 
                                    <small class="text-muted mt-1 text-center">Zona Negra - Control</small>                             
                                </a>                         
                            </div>                 
                    <?php                         
                            continue;                     
                        }                     
                        
                        if (in_array($id_eq, [21, 22, 23, 24])) {                         
                            if ($id_eq == 22 || $id_eq == 24) continue;                         
                            $titulo = ($id_eq == 21) ? 'Secadores 1 y 2' : 'Secadores 3 y 4';                 
                    ?>                         
                            <div class="col-md-6 col-lg-4">                              
                                <a href="captura.php?id=<?= $id_eq ?>" class="equipo-card-base border-start border-4 border-success">                                 
                                    <span class="fw-bold fs-6 text-dark text-center"><?= $titulo ?></span>                                 
                                    <small class="text-muted mt-1 text-center">Zona Blanca - Control</small>                             
                                </a>                         
                            </div>                 
                    <?php                         
                            continue;                     
                        }                                         
                        
                        if (in_array($id_eq, [14, 15, 16, 17, 18])) {                         
                            if ($id_eq != 14) continue;                 
                    ?>                          
                            <div class="col-md-6 col-lg-4">                              
                                <a href="captura.php?id=14" class="equipo-card-base border-start border-4 border-secondary">                                 
                                    <span class="fw-bold fs-6 text-dark text-center">Concentradores (1 al 4 e Invertido)</span>                                 
                                    <small class="text-muted mt-1 text-center">Zona Gris - Control General</small>                             
                                </a>                         
                            </div>                 
                    <?php                         
                            continue;                     
                        }                     
                        
                        if ($id_eq == 12 || $id_eq == 13) {                 
                    ?>                         
                            <div class="col-md-6 col-lg-4">                              
                                <a href="captura.php?id=<?= $id_eq ?>" class="equipo-card-base border-start border-4 border-warning">                                 
                                    <span class="fw-bold fs-6 text-dark text-center"><?= htmlspecialchars($nombre_eq) ?></span>                                 
                                    <small class="text-muted mt-1 text-center">Zona Negra - Control</small>                             
                                </a>                         
                            </div>                 
                    <?php                         
                            continue;                     
                        }                     
                        
                        $link_destino = "captura.php?id=" . $id_eq;                     
                        $patron = '/Bit[ a]cora.*2\s*HORAS/i';                     
                        $nombre_equipo_limpio = trim(preg_replace($patron, '', $nombre_eq));                 
                    ?>                     
                        <div class="col-md-6 col-lg-4">                          
                            <a href="<?= $link_destino ?>" class="equipo-card-base">                             
                                <span class="fw-bold text-center fs-6 text-dark"><?= htmlspecialchars($nombre_equipo_limpio) ?></span>                         
                            </a>                     
                        </div>                 
                    <?php                 
                    }                 
                    ?>             
                </div>         
        <?php              
                }          
            }          
        ?> 
        </div>     
    </div> 
</div> 

<?php include 'includes/footer.php'; ?>