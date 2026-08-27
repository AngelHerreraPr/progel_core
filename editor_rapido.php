<?php
// Progel_core/editor_rapido.php
session_start();
date_default_timezone_set('America/Mazatlan');
include 'config/db.php';

// PROTECCIÓN DE ACCESO POR ROL
$rol_actual = $_SESSION['rol'] ?? '';
if (!in_array($rol_actual, ['ADMIN', 'SUPERVISOR', 'JEFATURA'])) {
    header("Location: index.php");
    exit();
}

// =========================================================================
// MAGIA DEL NIP: Bloquear y Desbloquear Pantalla
// =========================================================================
$nip_secreto = "2212"; // <-- Tu NIP

// 1. Si presionas "Bloquear", borramos el permiso de la sesión
if (isset($_GET['bloquear'])) {
    unset($_SESSION['editor_desbloqueado']);
    header("Location: editor_rapido.php");
    exit();
}

// 2. Si enviaron el formulario con el NIP
$error_nip = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['nip_acceso'])) {
    if ($_POST['nip_acceso'] === $nip_secreto) {
        $_SESSION['editor_desbloqueado'] = true;
        header("Location: editor_rapido.php");
        exit();
    } else {
        $error_nip = "NIP incorrecto. Acceso denegado.";
    }
}

// 3. PANTALLA DE BLOQUEO (Si no ha puesto el NIP)
if (!isset($_SESSION['editor_desbloqueado']) || $_SESSION['editor_desbloqueado'] !== true) {
    include 'includes/header.php';
    ?>
    <style>
        body { background-color: #eef2f7; }
        .lock-container { display: flex; align-items: center; justify-content: center; min-height: 75vh; }
        .lock-card { background: white; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; width: 100%; max-width: 400px; border: 1px solid rgba(0,0,0,0.05); }
        .lock-header { background: #1e293b; padding: 2rem; text-align: center; color: white; }
        .lock-icon { font-size: 3.5rem; color: #3b82f6; margin-bottom: 0.5rem; }
        .input-nip { font-size: 2.5rem; letter-spacing: 10px; text-align: center; font-weight: 900; border: 2px solid #cbd5e1; border-radius: 12px; padding: 10px; color: #0f172a; transition: all 0.3s; }
        .input-nip:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59,130,246,0.15); outline: none; }
    </style>
    <div class="container-fluid lock-container">
        <div class="lock-card">
            <div class="lock-header">
                <i class="bi bi-shield-lock-fill lock-icon"></i>
                <h3 class="fw-bold m-0">Acceso Restringido</h3>
                <p class="text-white-50 m-0" style="font-size: 0.85rem;">Módulo de Edición de Base de Datos</p>
            </div>
            <div class="p-4">
                <?php if ($error_nip !== ""): ?>
                    <div class="alert alert-danger fw-bold text-center py-2"><i class="bi bi-exclamation-triangle-fill me-1"></i> <?= $error_nip ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-4 text-center">
                        <label class="text-muted fw-bold mb-2 small text-uppercase">Ingresa tu NIP de Autorización</label>
                        <input type="password" name="nip_acceso" class="form-control input-nip" placeholder="••••" maxlength="4" autofocus required autocomplete="off">
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm rounded-pill mb-2">
                        Desbloquear <i class="bi bi-unlock-fill ms-1"></i>
                    </button>
                    <a href="index.php" class="btn btn-light border w-100 fw-bold rounded-pill text-muted">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
    <?php
    include 'includes/footer.php';
    exit();
}

// =========================================================================
// MAGIA AJAX: Guardado en milisegundos
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update') {
    $id = intval($_POST['id']);
    $valor = $_POST['valor'];
    $obs = $_POST['obs'];
    
    $stmt = mysqli_prepare($conn, "UPDATE bitacora_lecturas SET valor_capturado = ?, observaciones = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ssi", $valor, $obs, $id);
    
    if(mysqli_stmt_execute($stmt)) {
        echo "OK";
    } else {
        echo "ERROR";
    }
    exit;
}

$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
$equipo_filtro = $_GET['equipo'] ?? '';

// EXTRACCIÓN Y AGRUPACIÓN DE DATOS
$filtro_sql = "DATE(b.fecha_registro) = '$fecha_filtro'";
if ($equipo_filtro != '') $filtro_sql .= " AND b.equipo_id = $equipo_filtro";

$query = "SELECT b.id, b.fecha_registro, e.nombre as equipo_nombre, p.nombre_parametro, 
                 b.valor_capturado, b.observaciones 
          FROM bitacora_lecturas b
          JOIN equipos e ON b.equipo_id = e.id
          JOIN parametros p ON b.parametro_id = p.id
          WHERE $filtro_sql
          ORDER BY e.nombre ASC, b.fecha_registro ASC, p.id ASC"; // Ordenado cronológicamente

$res = mysqli_query($conn, $query);
$registros_agrupados = [];

if (mysqli_num_rows($res) > 0) {
    while($row = mysqli_fetch_assoc($res)) {
        $equipo = $row['equipo_nombre'];
        $hora = date('H:i', strtotime($row['fecha_registro']));
        $registros_agrupados[$equipo][$hora][] = $row;
    }
}

include 'includes/header.php';
?>

<style>
    body { background-color: #eef2f7; font-family: 'Inter', sans-serif; color: #1e293b; }
    
    /* ===== MAGIA: PANEL FIJO (STICKY) ===== */
    .header-panel { 
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 12px; 
        padding: 1rem 1.5rem; 
        box-shadow: 0 4px 20px rgba(0,0,0,0.08); 
        margin-bottom: 1.5rem; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        flex-wrap: wrap; 
        gap: 1rem; 
        border-left: 5px solid #0d6efd; 
        position: sticky; 
        top: 15px; 
        z-index: 1020; 
    }
    
    .filter-bar { display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end; }
    
    .grid-maquinas {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
        gap: 1.5rem;
        align-items: start;
    }

    .maquina-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.04); border: 1px solid #e2e8f0; overflow: hidden; }
    .maquina-header { background: #1e293b; color: #ffffff; padding: 0.6rem 1.2rem; font-weight: 800; font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem; }

    /* ===== MAGIA: CARRUSEL HORIZONTAL PARA LAS HORAS ===== */
    .horas-container {
        display: flex;
        overflow-x: auto;
        padding: 0.8rem;
        gap: 1.5rem; /* Más separación entre columnas de horas */
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f8fafc;
    }
    .horas-container::-webkit-scrollbar { height: 10px; }
    .horas-container::-webkit-scrollbar-track { background: #f8fafc; border-radius: 4px; }
    .horas-container::-webkit-scrollbar-thumb { background-color: #94a3b8; border-radius: 4px; }

    .hora-bloque { 
        flex: 0 0 360px; /* Tarjeta un poco más ancha para que no se aplasten los campos */
        border: 1px solid #cbd5e1; 
        border-radius: 8px; 
        background: #f8fafc; 
        overflow: hidden; 
        display: flex;
        flex-direction: column;
    }
    
    .hora-titulo { background: #e0f2fe; color: #0369a1; padding: 0.4rem 0.8rem; font-size: 0.8rem; font-weight: 800; border-bottom: 1px solid #cbd5e1; display: flex; align-items: center; gap: 0.4rem; justify-content: center; }

    /* PARÁMETROS: Ligeramente más altos para respirar */
    .param-item { padding: 0.6rem 0.8rem; border-bottom: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 0.4rem; }
    .param-item:last-child { border-bottom: none; }
    .param-nombre { font-size: 0.75rem; font-weight: 700; color: #334155; text-transform: uppercase; white-space: normal; line-height: 1.2; }

    .param-controles { display: flex; gap: 0.5rem; align-items: center; width: 100%; box-sizing: border-box; }
    
    /* TAMAÑOS RÍGIDOS PARA EVITAR QUE SE APLASTEN */
    .inp-valor { 
        flex: 0 0 80px; 
        width: 80px; 
        text-align: center; 
        border: 1px solid #94a3b8; 
        border-radius: 5px; 
        font-weight: 800; 
        color: #0f172a; 
        padding: 0.3rem; 
        font-size: 0.85rem; 
    }
    .inp-valor:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 2px rgba(59,130,246,0.2); }
    
    /* min-width: 0 previene que el input de obs empuje al botón verde */
    .inp-obs { 
        flex: 1; 
        min-width: 0; 
        border: 1px solid #cbd5e1; 
        border-radius: 5px; 
        font-size: 0.75rem; 
        padding: 0.3rem 0.5rem; 
        font-style: italic; 
    }
    .inp-obs:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 2px rgba(59,130,246,0.2); }

    /* BLINDAJE EXTREMO DEL BOTÓN PARA QUE NUNCA SE ENCOJA */
    .btn-guardar-mini { 
        flex: 0 0 40px !important; 
        min-width: 40px !important; 
        max-width: 40px !important; 
        height: 35px; 
        background: #10b981; 
        color: white; 
        border: none; 
        border-radius: 5px; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        cursor: pointer; 
        transition: all 0.2s; 
        padding: 0; 
    }
    .btn-guardar-mini:hover { background: #059669; }
    .btn-guardar-mini:disabled { background: #94a3b8; cursor: not-allowed; }

    /* ===== BOTÓN FLOTANTE "IR ARRIBA" ===== */
    .btn-flotante {
        position: fixed;
        bottom: 25px;
        right: 25px;
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 4px 15px rgba(13, 110, 253, 0.4);
        z-index: 1050;
        cursor: pointer;
        opacity: 0.9;
        transition: all 0.3s;
    }
    .btn-flotante:hover { transform: translateY(-5px); opacity: 1; }

    @media (max-width: 576px) { 
        .grid-maquinas { grid-template-columns: 1fr; } 
        .hora-bloque { flex: 0 0 320px; } 
    }
</style>

<div class="container-fluid py-3 position-relative">
    
    <!-- ENCABEZADO Y FILTROS FIJOS -->
    <div class="header-panel">
        <div>
            <h4 class="m-0 fw-bold text-dark"><i class="bi bi-grid-1x2 text-primary me-2"></i>Edición en Cuadrícula</h4>
        </div>
        
        <form method="GET" class="filter-bar m-0">
            <div>
                <label class="fw-bold text-secondary" style="font-size: 0.7rem;">FECHA:</label>
                <input type="date" name="fecha" class="form-control form-control-sm fw-bold border-primary" value="<?= htmlspecialchars($fecha_filtro) ?>" onchange="this.form.submit()">
            </div>
            <div style="min-width: 200px;">
                <label class="fw-bold text-secondary" style="font-size: 0.7rem;">MÁQUINA:</label>
                <select name="equipo" class="form-select form-select-sm fw-bold bg-light" onchange="this.form.submit()">
                    <option value="">-- Ver Todas --</option>
                    <?php
                    $q_eq = mysqli_query($conn, "SELECT id, nombre FROM equipos ORDER BY nombre ASC");
                    while($eq = mysqli_fetch_assoc($q_eq)) {
                        $sel = ($equipo_filtro == $eq['id']) ? 'selected' : '';
                        echo "<option value='{$eq['id']}' $sel>{$eq['nombre']}</option>";
                    }
                    ?>
                </select>
            </div>
            
            <a href="?bloquear=1" class="btn btn-danger btn-sm fw-bold shadow-sm px-3 mb-1"><i class="bi bi-lock-fill me-1"></i> Bloquear</a>
            <a href="index.php" class="btn btn-dark btn-sm fw-bold shadow-sm px-3 mb-1"><i class="bi bi-house-door-fill me-1"></i> Inicio</a>
        </form>
    </div>

    <!-- CUADRÍCULA DE MÁQUINAS -->
    <?php if (empty($registros_agrupados)): ?>
        <div class="text-center py-5 bg-white rounded-4 shadow-sm border border-secondary border-opacity-25" style="border-style: dashed !important;">
            <i class="bi bi-folder-x text-muted opacity-25" style="font-size: 3rem;"></i>
            <h5 class="fw-bold text-secondary mt-3">Sin datos para mostrar</h5>
            <p class="text-muted small">No hay lecturas registradas para esta fecha.</p>
        </div>
    <?php else: ?>

        <div class="grid-maquinas">
            <?php foreach ($registros_agrupados as $equipo_nombre => $horas): ?>
                
                <!-- TARJETA POR EQUIPO -->
                <div class="maquina-card">
                    <div class="maquina-header">
                        <i class="bi bi-cpu text-warning"></i> <?= htmlspecialchars($equipo_nombre) ?>
                    </div>
                    
                    <!-- CONTENEDOR CON SCROLL HORIZONTAL -->
                    <div class="horas-container">
                        <?php foreach ($horas as $hora => $parametros): ?>
                            <!-- BLOQUE POR HORA -->
                            <div class="hora-bloque">
                                <div class="hora-titulo">
                                    <i class="bi bi-clock"></i> <?= $hora ?> hrs
                                </div>
                                
                                <div>
                                    <?php foreach ($parametros as $p): ?>
                                        <div class="param-item">
                                            <div class="param-nombre" title="<?= htmlspecialchars($p['nombre_parametro']) ?>">
                                                <?= htmlspecialchars($p['nombre_parametro']) ?>
                                            </div>
                                            <div class="param-controles">
                                                <input type="text" id="val_<?= $p['id'] ?>" class="inp-valor" value="<?= htmlspecialchars($p['valor_capturado']) ?>">
                                                <input type="text" id="obs_<?= $p['id'] ?>" class="inp-obs" placeholder="Añadir nota..." value="<?= htmlspecialchars($p['observaciones']) ?>">
                                                <button class="btn-guardar-mini" onclick="guardarEdicion(<?= $p['id'] ?>, this)" title="Guardar Corrección">
                                                    <i class="bi bi-check2"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div> <!-- Fin horas-container -->
                    
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>

    <!-- BOTÓN FLOTANTE PARA SUBIR -->
    <button onclick="window.scrollTo({top: 0, behavior: 'smooth'});" class="btn btn-primary btn-flotante" title="Ir arriba">
        <i class="bi bi-arrow-up"></i>
    </button>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function guardarEdicion(id, btn) {
    let valorNuevo = document.getElementById('val_' + id).value;
    let obsNueva = document.getElementById('obs_' + id).value;
    
    let originalIcon = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" style="width: 1rem; height: 1rem;"></span>';
    btn.disabled = true;

    let formData = new FormData();
    formData.append('action', 'update');
    formData.append('id', id);
    formData.append('valor', valorNuevo);
    formData.append('obs', obsNueva);

    fetch('editor_rapido.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(result => {
        btn.innerHTML = '<i class="bi bi-check2-all"></i>';
        btn.style.background = '#0ea5e9'; 
        
        setTimeout(() => { 
            btn.innerHTML = originalIcon; 
            btn.disabled = false; 
            btn.style.background = ''; 
        }, 1500);

        if(result.trim() === 'OK') {
            Swal.fire({
                toast: true, position: 'top-end', icon: 'success',
                title: 'Corregido', showConfirmButton: false, timer: 1500,
                background: '#f0fdf4', color: '#166534'
            });
        } else {
            Swal.fire('Error', 'No se pudo guardar la información.', 'error');
        }
    })
    .catch(error => {
        btn.innerHTML = originalIcon;
        btn.disabled = false;
        Swal.fire('Error', 'Falla de conexión.', 'error');
    });
}
</script>

<?php include 'includes/footer.php'; ?>