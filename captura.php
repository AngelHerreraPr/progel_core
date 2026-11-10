<?php
// progel_core/captura.php
session_start();

// SI NO HA INICIADO SESIÓN, NO LO DEJAMOS PASAR
if (!isset($_SESSION['nomina'])) {
    header("Location: login.php");
    exit();
}

include 'config/db.php';
include 'includes/funciones.php';

// Atrapamos los datos dinámicos de la URL
$tipo = $_GET['tipo'] ?? '';
$equipo_id = intval($_GET['id'] ?? 0);

// Redirección directa para Votators, Secadores y Concentradores al sistema externo Sis_Extracción (registro antiguo de Votators obsoleto)
if (in_array($equipo_id, [8, 83, 84, 85, 86, 87, 88, 89]) || strtolower($tipo) === 'votator') {
    header("Location: http://192.168.1.105/sis_extraccion/index.php?view=votators");
    exit();
}
if (in_array($equipo_id, [21, 22, 23, 24])) {
    header("Location: http://192.168.1.105/sis_extraccion/index.php?view=secadores");
    exit();
}
if (in_array($equipo_id, [14, 15, 16, 17, 18])) {
    $map_conc_ext = [14 => 9, 15 => 10, 16 => 11, 17 => 12, 18 => 13];
    $ext_id = $map_conc_ext[$equipo_id] ?? 9;
    header("Location: http://192.168.1.105/sis_extraccion/index.php?view=concentradores&equipo_id=$ext_id");
    exit();
}

// Buscamos el nombre del equipo
$stmt = mysqli_prepare($conn, "SELECT e.nombre as equipo, a.nombre as area, z.nombre as zona, z.id as zona_id 
                               FROM equipos e 
                               JOIN areas a ON e.area_id = a.id 
                               JOIN zonas z ON a.zona_id = z.id 
                               WHERE e.id = ?");
mysqli_stmt_bind_param($stmt, "i", $equipo_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$equipo = mysqli_fetch_assoc($result);
if ($equipo_id == 100) {
    $nombre_equipo = !empty($equipo['equipo']) ? $equipo['equipo'] : 'Sistema de Membranas (Ultrafiltración)';
} else {
    $nombre_equipo = $equipo['equipo'] ?? 'Equipo';
}

// Control de acceso al equipo según las zonas autorizadas del usuario
$rol_u = strtoupper(trim($_SESSION['rol'] ?? ''));
$zona_u = strtoupper(trim($_SESSION['zona'] ?? 'TODAS'));
$es_admin_jefe = in_array($rol_u, ['ADMIN', 'JEFATURA', 'SUPERVISOR']);

if (!$es_admin_jefe && $zona_u !== 'TODAS' && $equipo && !empty($equipo['zona'])) {
    $nom_z = strtoupper($equipo['zona']);
    $tiene_acceso = false;
    if (strpos($nom_z, 'BLANCA') !== false && strpos($zona_u, 'BLANCA') !== false) $tiene_acceso = true;
    if (strpos($nom_z, 'GRIS') !== false && strpos($zona_u, 'GRIS') !== false) $tiene_acceso = true;
    if (strpos($nom_z, 'NEGRA') !== false && strpos($zona_u, 'NEGRA') !== false) $tiene_acceso = true;

    if (!$tiene_acceso) {
        header("Location: index.php?error=zona_no_autorizada");
        exit();
    }
}

include 'includes/header.php';
?>

<div class="container-fluid py-3 px-2 px-md-3 px-xl-4">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm border-start border-5 border-primary">
        <div>
            <h2 class="m-0 fw-bold text-dark"><?= htmlspecialchars($nombre_equipo) ?></h2>
            <small class="text-muted">Operador: <?= htmlspecialchars($_SESSION['nombre']) ?></small>
        </div>
        <a href="<?= !empty($equipo['zona_id']) ? 'zona.php?id=' . (int)$equipo['zona_id'] : 'index.php' ?>" class="btn btn-outline-secondary fw-bold">
            <i class="bi bi-arrow-left me-1"></i> <?= !empty($equipo['zona']) ? 'Volver a ' . htmlspecialchars($equipo['zona']) : 'Regresar al Mapa' ?>
        </a>
    </div>

    <div class="row">
        <div class="col-12">
            <?php include __DIR__ . '/equipos/bloque_captura.php'; ?>
        </div>
    </div>
</div>

<script>
function marcarEquipo(estado, equipoId) {
    let selector = '.input-equipo-' + equipoId;
    let campos = document.querySelectorAll(selector);

    campos.forEach(function(el) {
        if (!el) return;

        if (el.tagName === 'SELECT') {
            let encontrada = false;
            for (let i = 0; i < el.options.length; i++) {
                if (el.options[i].value === estado) {
                    encontrada = true;
                    break;
                }
            }
            if (!encontrada && estado !== '') {
                let opt = document.createElement('option');
                opt.value = estado;
                opt.text = estado;
                el.add(opt);
            }
            el.value = estado;
        } else if (el.tagName === 'INPUT') {
            if (el.type === 'number' && estado !== '') {
                el.type = 'text';
            } else if (estado === '' && el.type === 'text') {
                el.type = 'number';
            }

            el.value = estado;
            if (estado === '') {
                el.removeAttribute('readonly');
                el.style.backgroundColor = '';
                el.style.color = '';
            } else {
                el.setAttribute('readonly', true);
                if (estado === 'F.O.') {
                    el.style.backgroundColor = '#f8d7da';
                    el.style.color = '#842029';
                } else if (estado === 'LAVADO') {
                    el.style.backgroundColor = '#cff4fc';
                    el.style.color = '#055160';
                } else if (estado === 'MMTT' || estado === 'MTTO' || estado === 'MMT') {
                    el.style.backgroundColor = '#fff3cd';
                    el.style.color = '#664d03';
                }
            }
            el.style.fontWeight = 'bold';
            el.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
        }
    });
}
</script>
<?php include 'includes/footer.php'; ?>
