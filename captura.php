<?php
// Progel_cores/captura.php
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
$nombre_equipo = $equipo['equipo'] ?? 'Equipo';

include 'includes/header.php';
?>

<div class="container-fluid py-4 px-4">
    <div class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded shadow-sm border-start border-5 border-primary">
        <div>
            <h2 class="m-0 fw-bold text-dark"><?= htmlspecialchars($nombre_equipo) ?></h2>
            <small class="text-muted">Operador: <?= htmlspecialchars($_SESSION['nombre']) ?> (Nómina: <?= htmlspecialchars($_SESSION['nomina']) ?>)</small>
        </div>
        <a href="index.php" class="btn btn-outline-secondary fw-bold">
            Regresar al Mapa
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
            el.setAttribute('readonly', true);
            el.style.backgroundColor = (estado === 'F.O.') ? '#f8d7da' : (estado === 'LAVADO' ? '#cff4fc' : '');
            el.style.color = (estado === 'F.O.') ? '#842029' : (estado === 'LAVADO' ? '#055160' : '');
            el.style.fontWeight = 'bold';
            el.classList.remove('semaforo-v', 'semaforo-a', 'semaforo-r');
        }
    });
}
</script>
<?php include 'includes/footer.php'; ?>