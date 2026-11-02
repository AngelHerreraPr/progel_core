<?php
session_start();
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('America/Mazatlan');

include __DIR__ . '/../config/db.php';
include __DIR__ . '/../config/db_v2.php';

if (!isset($_SESSION['nomina'])) {
    header("Location: ../login.php");
    exit();
}

$rol_actual = strtoupper(trim($_SESSION['rol'] ?? ''));
if ($rol_actual !== 'ADMIN') {
    http_response_code(403);
    die("<div style='font-family:sans-serif;text-align:center;margin-top:80px;'><h2>Acceso Restringido</h2><p>Solo el <b>Administrador (ADMIN)</b> puede gestionar usuarios, zonas y permisos de acceso.</p><a href='../index.php' style='display:inline-block;margin-top:15px;padding:10px 20px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:8px;font-weight:bold;'>Volver al Inicio</a></div>");
}

$admin_nomina = (int)$_SESSION['nomina'];

// Asegurar que las columnas 'activo', 'nombre' y 'zona_asignada' (VARCHAR 100) existan correctamente
$chk_col = @mysqli_query($conn, "SHOW COLUMNS FROM usuarios LIKE 'activo'");
if ($chk_col && mysqli_num_rows($chk_col) === 0) {
    @mysqli_query($conn, "ALTER TABLE usuarios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1");
}
$chk_nom = @mysqli_query($conn, "SHOW COLUMNS FROM usuarios LIKE 'nombre'");
if ($chk_nom && mysqli_num_rows($chk_nom) === 0) {
    @mysqli_query($conn, "ALTER TABLE usuarios ADD COLUMN nombre VARCHAR(120) NULL AFTER nomina");
    @mysqli_query($conn, "UPDATE usuarios SET nombre = nombre_completo WHERE nombre IS NULL");
}
$chk_za = @mysqli_query($conn, "SHOW COLUMNS FROM usuarios LIKE 'zona_asignada'");
if ($chk_za && $row_za = mysqli_fetch_assoc($chk_za)) {
    if (stripos($row_za['Type'], 'varchar(100)') === false) {
        @mysqli_query($conn, "ALTER TABLE usuarios MODIFY COLUMN zona_asignada VARCHAR(100) DEFAULT 'TODAS'");
    }
}
if (isset($conn_v2) && $conn_v2) {
    $chk_za_v2 = @mysqli_query($conn_v2, "SHOW COLUMNS FROM usuarios LIKE 'zona_asignada'");
    if ($chk_za_v2 && $row_za_v2 = mysqli_fetch_assoc($chk_za_v2)) {
        if (stripos($row_za_v2['Type'], 'varchar(100)') === false) {
            @mysqli_query($conn_v2, "ALTER TABLE usuarios MODIFY COLUMN zona_asignada VARCHAR(100) DEFAULT 'TODAS'");
        }
    }
}

/**
 * Normaliza cualquier formato o combinacion de zonas a una llave canonica
 */
function normalizarZonaKey($z) {
    $z = strtoupper(trim((string)$z));
    if ($z === '' || $z === 'TODAS') return 'TODAS';
    $partes = [];
    if (strpos($z, 'BLANCA') !== false) $partes[] = 'ZONA_BLANCA';
    if (strpos($z, 'GRIS') !== false)   $partes[] = 'ZONA_GRIS';
    if (strpos($z, 'NEGRA') !== false)  $partes[] = 'ZONA_NEGRA';
    
    if (count($partes) === 3 || count($partes) === 0) return 'TODAS';
    return implode(', ', $partes);
}

/**
 * Sincroniza un usuario con Progel_coreV2.usuarios
 */
function sincronizarUsuarioV2($conn_v2, $nomina, $nombre, $rol, $zona_asignada, $activo) {
    if (!$conn_v2) return;
    $rol_v2 = strtolower(trim($rol));
    if (!in_array($rol_v2, ['operador', 'supervisor', 'admin', 'auditor'])) {
        if ($rol_v2 === 'jefatura') $rol_v2 = 'supervisor';
        elseif ($rol_v2 === 'lectura' || $rol_v2 === 'visitante') $rol_v2 = 'auditor';
        else $rol_v2 = 'operador';
    }
    $nom_esc = mysqli_real_escape_string($conn_v2, (string)$nomina);
    $nombre_esc = mysqli_real_escape_string($conn_v2, $nombre);
    $rol_esc = mysqli_real_escape_string($conn_v2, $rol_v2);
    $zona_esc = mysqli_real_escape_string($conn_v2, $zona_asignada);
    $act_int = (int)$activo;

    $sql = "INSERT INTO usuarios (nomina, nombre_completo, rol, zona_asignada, activo)
            VALUES ('$nom_esc', '$nombre_esc', '$rol_esc', '$zona_esc', $act_int)
            ON DUPLICATE KEY UPDATE
                nombre_completo = VALUES(nombre_completo),
                rol = VALUES(rol),
                zona_asignada = VALUES(zona_asignada),
                activo = VALUES(activo)";
    @mysqli_query($conn_v2, $sql);
}

$mensaje = '';
$tipo_alerta = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // 1. AGREGAR NUEVO USUARIO
    if ($accion === 'agregar') {
        $nueva_nomina = (int)($_POST['nomina'] ?? 0);
        $nuevo_nombre = trim($_POST['nombre'] ?? '');
        $nuevo_rol    = strtoupper(trim($_POST['rol'] ?? 'OPERADOR'));
        $nueva_zona   = normalizarZonaKey($_POST['zona_asignada'] ?? 'ZONA_BLANCA');

        if ($nueva_nomina <= 0 || $nuevo_nombre === '') {
            $mensaje = "Por favor ingresa un n&uacute;mero de n&oacute;mina v&aacute;lido y el nombre completo.";
            $tipo_alerta = "warning";
        } else {
            // Verificar si ya existe la nomina
            $chk = mysqli_query($conn, "SELECT * FROM usuarios WHERE nomina = $nueva_nomina LIMIT 1");
            if ($chk && mysqli_num_rows($chk) > 0) {
                $ex = mysqli_fetch_assoc($chk);
                $ex_nom = htmlspecialchars($ex['nombre'] ?? $ex['nombre_completo'] ?? '');
                $estado_txt = (!isset($ex['activo']) || (int)$ex['activo'] === 1) ? 'ACTIVO' : 'DADO DE BAJA';
                $mensaje = "La n&oacute;mina #$nueva_nomina ya est&aacute; registrada a nombre de \"$ex_nom\" (Estado: $estado_txt). Puedes modificar su zona o reactivarla en la tabla inferior.";
                $tipo_alerta = "warning";
            } else {
                $stmt = mysqli_prepare($conn, "INSERT INTO usuarios (nomina, nombre, rol, zona_asignada, activo) VALUES (?, ?, ?, ?, 1)");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "isss", $nueva_nomina, $nuevo_nombre, $nuevo_rol, $nueva_zona);
                    $ok = mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                } else {
                    $stmt2 = mysqli_prepare($conn, "INSERT INTO usuarios (nomina, nombre, rol, zona_asignada) VALUES (?, ?, ?, ?)");
                    if ($stmt2) {
                        mysqli_stmt_bind_param($stmt2, "isss", $nueva_nomina, $nuevo_nombre, $nuevo_rol, $nueva_zona);
                        $ok = mysqli_stmt_execute($stmt2);
                        mysqli_stmt_close($stmt2);
                    } else {
                        $ok = false;
                    }
                }

                if ($ok) {
                    sincronizarUsuarioV2($conn_v2 ?? null, $nueva_nomina, $nuevo_nombre, $nuevo_rol, $nueva_zona, 1);
                    $mensaje = "Usuario #$nueva_nomina (" . htmlspecialchars($nuevo_nombre) . ") agregado exitosamente con acceso a: " . htmlspecialchars($nueva_zona) . ".";
                    $tipo_alerta = "success";
                } else {
                    $mensaje = "Error al registrar el usuario: " . htmlspecialchars(mysqli_error($conn));
                    $tipo_alerta = "danger";
                }
            }
        }
    }

    // 2. MODIFICAR ZONA, ROL O NOMBRE DE UN USUARIO
    elseif ($accion === 'modificar') {
        $nomina_mod = (int)($_POST['nomina'] ?? 0);
        $nombre_mod = trim($_POST['nombre'] ?? '');
        $rol_mod    = strtoupper(trim($_POST['rol'] ?? 'OPERADOR'));
        $zona_mod   = normalizarZonaKey($_POST['zona_asignada'] ?? 'TODAS');

        if ($nomina_mod > 0 && $nombre_mod !== '') {
            if ($nomina_mod === $admin_nomina && $rol_mod !== 'ADMIN') {
                $mensaje = "Por seguridad no puedes quitarte el rol de ADMIN a tu propia n&oacute;mina (#$admin_nomina).";
                $tipo_alerta = "warning";
            } else {
                $stmt = mysqli_prepare($conn, "UPDATE usuarios SET nombre = ?, rol = ?, zona_asignada = ? WHERE nomina = ?");
                if ($stmt) {
                    mysqli_stmt_bind_param($stmt, "sssi", $nombre_mod, $rol_mod, $zona_mod, $nomina_mod);
                    if (mysqli_stmt_execute($stmt)) {
                        $q_act = mysqli_query($conn, "SELECT * FROM usuarios WHERE nomina = $nomina_mod LIMIT 1");
                        $r_act = $q_act ? mysqli_fetch_assoc($q_act) : ['activo' => 1];
                        sincronizarUsuarioV2($conn_v2 ?? null, $nomina_mod, $nombre_mod, $rol_mod, $zona_mod, $r_act['activo'] ?? 1);
                        $mensaje = "Datos actualizados para la n&oacute;mina #$nomina_mod (" . htmlspecialchars($nombre_mod) . ") &rarr; Zona: " . htmlspecialchars($zona_mod) . " | Rol: " . htmlspecialchars($rol_mod) . ".";
                        $tipo_alerta = "success";
                    } else {
                        $mensaje = "Error al actualizar: " . htmlspecialchars(mysqli_error($conn));
                        $tipo_alerta = "danger";
                    }
                    mysqli_stmt_close($stmt);
                }
            }
        }
    }

    // 3. DAR DE BAJA (SIN ELIMINAR) O REACTIVAR ACCESO
    elseif ($accion === 'cambiar_estado') {
        $nomina_est   = (int)($_POST['nomina'] ?? 0);
        $nuevo_estado = (int)($_POST['nuevo_estado'] ?? 0);

        if ($nomina_est === $admin_nomina && $nuevo_estado === 0) {
            $mensaje = "No puedes dar de baja tu propia n&oacute;mina de Administrador (#$admin_nomina).";
            $tipo_alerta = "warning";
        } elseif ($nomina_est > 0) {
            $stmt = mysqli_prepare($conn, "UPDATE usuarios SET activo = ? WHERE nomina = ?");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ii", $nuevo_estado, $nomina_est);
                if (mysqli_stmt_execute($stmt)) {
                    $q_u = mysqli_query($conn, "SELECT * FROM usuarios WHERE nomina = $nomina_est LIMIT 1");
                    if ($q_u && $ru = mysqli_fetch_assoc($q_u)) {
                        $nom_u = $ru['nombre'] ?? $ru['nombre_completo'] ?? '';
                        sincronizarUsuarioV2($conn_v2 ?? null, $nomina_est, $nom_u, $ru['rol'], $ru['zona_asignada'], $nuevo_estado);
                        if ($nuevo_estado === 0) {
                            $mensaje = "La n&oacute;mina #$nomina_est (" . htmlspecialchars($nom_u) . ") fue DADA DE BAJA. Su historial se conserva intacto, pero ya no podr&aacute; iniciar sesi&oacute;n.";
                            $tipo_alerta = "secondary";
                        } else {
                            $mensaje = "La n&oacute;mina #$nomina_est (" . htmlspecialchars($nom_u) . ") fue REACTIVADA y ya tiene acceso nuevamente al sistema.";
                            $tipo_alerta = "success";
                        }
                    }
                }
                mysqli_stmt_close($stmt);
            }
        }
    }
}

// Consultar todos los usuarios
$usuarios = [];
$total_activos = 0;
$total_bajas = 0;
$res_u = @mysqli_query($conn, "SELECT * FROM usuarios ORDER BY activo DESC, rol ASC, nombre ASC");
if (!$res_u) {
    $res_u = @mysqli_query($conn, "SELECT * FROM usuarios ORDER BY rol ASC, nombre ASC");
}
if ($res_u) {
    while ($u = mysqli_fetch_assoc($res_u)) {
        $u['nombre'] = $u['nombre'] ?? $u['nombre_completo'] ?? '';
        $u['activo'] = isset($u['activo']) ? (int)$u['activo'] : 1;
        if ($u['activo'] === 1) $total_activos++;
        else $total_bajas++;
        $usuarios[] = $u;
    }
}

// Catalogo de opciones de zonas agrupado
$zonas_catalogo = [
    'Acceso a 1 Zona' => [
        'ZONA_BLANCA' => 'Solo Zona Blanca (Secadores / Votators)',
        'ZONA_GRIS'   => 'Solo Zona Gris (Concentradores / Membranas)',
        'ZONA_NEGRA'  => 'Solo Zona Negra (Chillers / Compresores)',
    ],
    'Acceso Combinado (2 Zonas)' => [
        'ZONA_BLANCA, ZONA_GRIS'  => 'Zona Blanca + Zona Gris',
        'ZONA_BLANCA, ZONA_NEGRA' => 'Zona Blanca + Zona Negra',
        'ZONA_GRIS, ZONA_NEGRA'   => 'Zona Gris + Zona Negra',
    ],
    'Acceso Global' => [
        'TODAS' => 'Todas las Zonas (Acceso Total)',
    ]
];

$roles_catalogo = [
    'OPERADOR'   => 'OPERADOR (Captura en su Zona)',
    'SUPERVISOR' => 'SUPERVISOR (Reportes y Supervision)',
    'JEFATURA'   => 'JEFATURA (Monitoreo General)',
    'ADMIN'      => 'ADMIN (Control Total)',
    'LECTURA'    => 'LECTURA (Solo Consulta)'
];

include __DIR__ . '/../includes/header.php';
?>

<style>
    .admin-users-header {
        background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);
        color: #fff;
        border-radius: 16px;
        padding: 1.5rem 2rem;
        margin-bottom: 1.5rem;
        box-shadow: 0 10px 25px rgba(15, 23, 42, 0.15);
    }
    .kpi-mini-card {
        background: #fff;
        border-radius: 14px;
        padding: 1rem 1.25rem;
        border: 1px solid #e2e8f0;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .row-baja {
        background-color: #fef2f2 !important;
        opacity: 0.88;
    }
    .row-baja td {
        color: #7f1d1d;
    }
    .badge-acceso-activo {
        background: #dcfce7;
        color: #166534;
        border: 1px solid #86efac;
        font-weight: 800;
        padding: 0.35rem 0.7rem;
        border-radius: 50px;
        font-size: 0.75rem;
    }
    .badge-acceso-baja {
        background: #fee2e2;
        color: #991b1b;
        border: 1px solid #fca5a5;
        font-weight: 800;
        padding: 0.35rem 0.7rem;
        border-radius: 50px;
        font-size: 0.75rem;
    }
    .badge-zona-pill {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 0.2rem 0.5rem;
        border-radius: 6px;
        margin-right: 3px;
        margin-bottom: 2px;
    }
    .badge-zona-blanca { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
    .badge-zona-gris   { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
    .badge-zona-negra  { background: #e2e8f0; color: #0f172a; border: 1px solid #94a3b8; }
    .badge-zona-todas  { background: #e0e7ff; color: #3730a3; border: 1px solid #c7d2fe; }
</style>

<div class="pb-5">
    <!-- Encabezado -->
    <div class="admin-users-header d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <span class="badge bg-warning text-dark fw-bold mb-2 px-3 py-1 rounded-pill">
                <i class="bi bi-shield-lock-fill me-1"></i> Exclusivo Administrador
            </span>
            <h3 class="fw-bold mb-1"><i class="bi bi-people-fill me-2"></i>Panel de Control de Usuarios y Accesos</h3>
            <p class="mb-0 text-white-50 small">
                Asigna acceso a <strong>1 zona, 2 zonas o todas las zonas</strong> por usuario, cambia roles o <strong>da de baja por n&uacute;mero de n&oacute;mina</strong> sin borrar su historial de capturas.
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="../index.php" class="btn btn-light fw-bold btn-sm px-3 py-2 rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> Volver al Mapa
            </a>
        </div>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-<?= $tipo_alerta ?> alert-dismissible fade show shadow-sm fw-semibold" role="alert">
            <i class="bi bi-info-circle-fill me-1"></i> <?= $mensaje ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- KPIs rapidos -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="kpi-mini-card">
                <div>
                    <div class="text-muted small fw-bold text-uppercase">Total Registrados</div>
                    <div class="fs-3 fw-black text-dark"><?= count($usuarios) ?></div>
                </div>
                <span class="fs-2 text-primary"><i class="bi bi-people"></i></span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-mini-card" style="border-left: 4px solid #16a34a;">
                <div>
                    <div class="text-success small fw-bold text-uppercase">Con Acceso Activo</div>
                    <div class="fs-3 fw-black text-success"><?= $total_activos ?></div>
                </div>
                <span class="fs-2 text-success"><i class="bi bi-person-check-fill"></i></span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="kpi-mini-card" style="border-left: 4px solid #dc2626;">
                <div>
                    <div class="text-danger small fw-bold text-uppercase">Dados de Baja (Sin Acceso)</div>
                    <div class="fs-3 fw-black text-danger"><?= $total_bajas ?></div>
                </div>
                <span class="fs-2 text-danger"><i class="bi bi-person-x-fill"></i></span>
            </div>
        </div>
    </div>

    <!-- Tarjeta para Agregar Nuevo Usuario -->
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h6 class="fw-bold m-0 text-primary">
                <i class="bi bi-person-plus-fill me-2"></i>Dar de Alta Nuevo Usuario / N&oacute;mina
            </h6>
        </div>
        <div class="card-body px-4 py-3">
            <form method="POST" class="row g-3 align-items-end">
                <input type="hidden" name="accion" value="agregar">
                <div class="col-sm-6 col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">N&uacute;m. de N&oacute;mina</label>
                    <input type="number" name="nomina" class="form-control fw-bold" placeholder="Ej. 3901" required min="1">
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Nombre Completo</label>
                    <input type="text" name="nombre" class="form-control" placeholder="Nombre y Apellidos" required>
                </div>
                <div class="col-sm-6 col-md-2">
                    <label class="form-label small fw-bold text-secondary mb-1">Rol en el Sistema</label>
                    <select name="rol" class="form-select fw-semibold">
                        <?php foreach ($roles_catalogo as $r_key => $r_label): ?>
                            <option value="<?= $r_key ?>"><?= htmlspecialchars($r_label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-6 col-md-3">
                    <label class="form-label small fw-bold text-secondary mb-1">Zona(s) Asignada(s)</label>
                    <select name="zona_asignada" class="form-select fw-semibold">
                        <?php foreach ($zonas_catalogo as $grupo_lbl => $opciones): ?>
                            <optgroup label="<?= htmlspecialchars($grupo_lbl) ?>">
                                <?php foreach ($opciones as $z_key => $z_label): ?>
                                    <option value="<?= $z_key ?>" <?= ($z_key === 'ZONA_BLANCA') ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($z_label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">
                        <i class="bi bi-plus-circle me-1"></i> Agregar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filtros y Tabla de Usuarios -->
    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-white border-bottom py-3 px-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <h6 class="fw-bold m-0 text-dark">
                <i class="bi bi-list-check me-2 text-primary"></i>Padr&oacute;n de Usuarios (Modificar Zonas, Rol o Dar de Baja)
            </h6>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <input type="text" id="buscarUsuarioInput" class="form-control form-control-sm" style="min-width: 200px;" placeholder="Buscar nomina o nombre..." onkeyup="filtrarTablaUsuarios()">
                <select id="filtroEstadoSelect" class="form-select form-select-sm" style="width: auto;" onchange="filtrarTablaUsuarios()">
                    <option value="TODOS">Todos los estados</option>
                    <option value="1">Solo Activos</option>
                    <option value="0">Solo Dados de Baja</option>
                </select>
                <select id="filtroZonaSelect" class="form-select form-select-sm" style="width: auto;" onchange="filtrarTablaUsuarios()">
                    <option value="TODAS_FILTRO">Todas las zonas</option>
                    <optgroup label="Filtrar por Zona">
                        <option value="BLANCA">Acceso a Zona Blanca</option>
                        <option value="GRIS">Acceso a Zona Gris</option>
                        <option value="NEGRA">Acceso a Zona Negra</option>
                    </optgroup>
                    <optgroup label="Combinacion Exacta">
                        <option value="ZONA_BLANCA">Solo Zona Blanca</option>
                        <option value="ZONA_GRIS">Solo Zona Gris</option>
                        <option value="ZONA_NEGRA">Solo Zona Negra</option>
                        <option value="ZONA_BLANCA, ZONA_GRIS">Blanca + Gris</option>
                        <option value="ZONA_BLANCA, ZONA_NEGRA">Blanca + Negra</option>
                        <option value="ZONA_GRIS, ZONA_NEGRA">Gris + Negra</option>
                        <option value="TODAS">Acceso Total (TODAS)</option>
                    </optgroup>
                </select>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="tablaUsuariosAdmin">
                <thead class="table-light text-secondary small text-uppercase">
                    <tr>
                        <th class="ps-4" style="width: 100px;">N&oacute;mina</th>
                        <th style="width: 155px;">Estado</th>
                        <th>Nombre del Usuario</th>
                        <th style="width: 280px;">Zona(s) Asignada(s)</th>
                        <th style="width: 220px;">Rol</th>
                        <th class="text-end pe-4" style="width: 230px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u):
                        $nom = (int)$u['nomina'];
                        $es_activo = ($u['activo'] === 1);
                        $form_id = "form_mod_" . $nom;
                        $z_canon = normalizarZonaKey($u['zona_asignada'] ?? 'TODAS');
                    ?>
                        <tr class="fila-usuario <?= $es_activo ? '' : 'row-baja' ?>"
                            data-texto="<?= htmlspecialchars(mb_strtolower($nom . ' ' . $u['nombre'], 'UTF-8')) ?>"
                            data-activo="<?= $es_activo ? '1' : '0' ?>"
                            data-zona="<?= htmlspecialchars($z_canon) ?>">
                            
                            <td class="ps-4 fw-black fs-6">
                                #<?= $nom ?>
                                <?php if ($nom === $admin_nomina): ?>
                                    <span class="badge bg-dark ms-1" style="font-size:0.65rem;">T&Uacute;</span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <?php if ($es_activo): ?>
                                    <span class="badge-acceso-activo">
                                        <i class="bi bi-check-circle-fill me-1"></i> ACTIVO
                                    </span>
                                <?php else: ?>
                                    <span class="badge-acceso-baja">
                                        <i class="bi bi-slash-circle-fill me-1"></i> DADO DE BAJA
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td>
                                <form id="<?= $form_id ?>" method="POST" class="m-0">
                                    <input type="hidden" name="accion" value="modificar">
                                    <input type="hidden" name="nomina" value="<?= $nom ?>">
                                </form>
                                <input type="text" name="nombre" form="<?= $form_id ?>"
                                       class="form-control form-control-sm fw-semibold"
                                       value="<?= htmlspecialchars($u['nombre']) ?>" required>
                            </td>

                            <td>
                                <select name="zona_asignada" form="<?= $form_id ?>" class="form-select form-select-sm fw-semibold">
                                    <?php foreach ($zonas_catalogo as $grupo_lbl => $opciones): ?>
                                        <optgroup label="<?= htmlspecialchars($grupo_lbl) ?>">
                                            <?php foreach ($opciones as $z_key => $z_label): ?>
                                                <option value="<?= $z_key ?>" <?= ($z_canon === $z_key) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($z_label) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <div class="mt-1">
                                    <?php if ($z_canon === 'TODAS'): ?>
                                        <span class="badge-zona-pill badge-zona-todas"><i class="bi bi-globe me-1"></i>Todas las Zonas</span>
                                    <?php else: ?>
                                        <?php if (strpos($z_canon, 'BLANCA') !== false): ?>
                                            <span class="badge-zona-pill badge-zona-blanca"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Blanca</span>
                                        <?php endif; ?>
                                        <?php if (strpos($z_canon, 'GRIS') !== false): ?>
                                            <span class="badge-zona-pill badge-zona-gris"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Gris</span>
                                        <?php endif; ?>
                                        <?php if (strpos($z_canon, 'NEGRA') !== false): ?>
                                            <span class="badge-zona-pill badge-zona-negra"><i class="bi bi-circle-fill me-1" style="font-size:0.5rem;"></i>Negra</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <td>
                                <select name="rol" form="<?= $form_id ?>" class="form-select form-select-sm fw-semibold"
                                        <?= ($nom === $admin_nomina) ? 'title="Tu propio rol ADMIN esta protegido"' : '' ?>>
                                    <?php foreach ($roles_catalogo as $r_key => $r_label): ?>
                                        <option value="<?= $r_key ?>" <?= (strtoupper(trim($u['rol'] ?? '')) === $r_key) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($r_label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>

                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-1 align-items-center">
                                    <button type="submit" form="<?= $form_id ?>" class="btn btn-sm btn-primary fw-bold px-2" title="Guardar cambios de Zona, Rol o Nombre">
                                        <i class="bi bi-floppy-fill me-1"></i> Guardar
                                    </button>

                                    <?php if ($nom !== $admin_nomina): ?>
                                        <form method="POST" class="m-0 d-inline" onsubmit="return confirm('<?= $es_activo ? '\u00bfDar de BAJA a la n\u00f3mina #' . $nom . '? Ya no podr\u00e1 ingresar al sistema con su n\u00famero de n\u00f3mina, pero todos sus registros hist\u00f3ricos se conservar\u00e1n.' : '\u00bfReactivar el acceso en el sistema para la n\u00f3mina #' . $nom . '?' ?>');">
                                            <input type="hidden" name="accion" value="cambiar_estado">
                                            <input type="hidden" name="nomina" value="<?= $nom ?>">
                                            <input type="hidden" name="nuevo_estado" value="<?= $es_activo ? 0 : 1 ?>">
                                            <?php if ($es_activo): ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger fw-bold px-2" title="Bloquear acceso sin eliminar el usuario">
                                                    <i class="bi bi-person-dash-fill me-1"></i> Dar de Baja
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" class="btn btn-sm btn-success fw-bold px-2" title="Volver a permitir acceso con esta nomina">
                                                    <i class="bi bi-person-check-fill me-1"></i> Reactivar
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function filtrarTablaUsuarios() {
    const q = (document.getElementById('buscarUsuarioInput').value || '').toLowerCase().trim();
    const est = document.getElementById('filtroEstadoSelect').value;
    const filtroZona = document.getElementById('filtroZonaSelect').value;
    const filas = document.querySelectorAll('#tablaUsuariosAdmin tbody .fila-usuario');

    filas.forEach(tr => {
        const texto = tr.getAttribute('data-texto') || '';
        const act = tr.getAttribute('data-activo') || '1';
        const z = (tr.getAttribute('data-zona') || '').toUpperCase();

        const coincideTexto = (q === '' || texto.includes(q));
        const coincideEstado = (est === 'TODOS' || act === est);

        let coincideZona = false;
        if (filtroZona === 'TODAS_FILTRO') {
            coincideZona = true;
        } else if (filtroZona === 'BLANCA') {
            coincideZona = (z === 'TODAS' || z.includes('BLANCA'));
        } else if (filtroZona === 'GRIS') {
            coincideZona = (z === 'TODAS' || z.includes('GRIS'));
        } else if (filtroZona === 'NEGRA') {
            coincideZona = (z === 'TODAS' || z.includes('NEGRA'));
        } else {
            coincideZona = (z === filtroZona);
        }

        tr.style.display = (coincideTexto && coincideEstado && coincideZona) ? '' : 'none';
    });
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
