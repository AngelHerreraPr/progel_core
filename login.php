<?php
// progel_core/login.php
session_start();

// Si ya inició sesión, lo mandamos directo al mapa
if (isset($_SESSION['nomina'])) {
    header("Location: index.php");
    exit();
}

include 'config/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nomina = trim($_POST['nomina'] ?? '');
    
    if (!empty($nomina)) {
        // Asegurar que la columna activo exista en la tabla usuarios
        $chk_col = @mysqli_query($conn, "SHOW COLUMNS FROM usuarios LIKE 'activo'");
        if ($chk_col && mysqli_num_rows($chk_col) === 0) {
            @mysqli_query($conn, "ALTER TABLE usuarios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1");
        }

        $stmt = mysqli_prepare($conn, "SELECT * FROM usuarios WHERE nomina = ?");
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "s", $nomina);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            if ($result && $row = mysqli_fetch_assoc($result)) {
                if (isset($row['activo']) && (int)$row['activo'] === 0) {
                    $error = "Esta nómina se encuentra dada de baja y no tiene acceso al sistema.";
                } else {
                    // Guardamos sus datos en la sesión del navegador (soporta columna 'nombre' o 'nombre_completo')
                    $_SESSION['nomina'] = $nomina;
                    $_SESSION['nombre'] = $row['nombre'] ?? $row['nombre_completo'] ?? 'Usuario';
                    $_SESSION['rol'] = strtoupper(trim($row['rol'] ?? 'OPERADOR'));
                    $_SESSION['zona'] = $row['zona_asignada'] ?? 'TODAS';
                    $_SESSION['ultima_actividad'] = time();

                    // Cookie global compartida para Single Sign-On (SSO) con Sis_extraccion y demás módulos
                    setcookie('progel_nomina_activa', $nomina, time() + (86400 * 30), '/');
                    
                    // Acceso concedido
                    header("Location: index.php");
                    exit();
                }
            } else {
                $error = "Nómina no autorizada en el sistema.";
            }
            mysqli_stmt_close($stmt);
        } else {
            $error = "Error al consultar la tabla de usuarios en la base de datos.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es" translate="no">
<head>
    <meta charset="UTF-8">
    <meta name="google" content="notranslate">
    <title>Acceso - PROGEL</title>
    <link href="assets/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <style>
        body { background-color: #f4f6f9; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { max-width: 400px; width: 100%; border-radius: 15px; overflow: hidden; }
        .login-header { background: linear-gradient(135deg, #0d6efd, #0b5ed7); color: white; padding: 30px 20px; text-align: center; }
    </style>
</head>
<body>
    <div class="card shadow-lg border-0 login-card">
        <div class="login-header">
            <i class="bi bi-shield-lock text-white" style="font-size: 3rem;"></i>
            <h3 class="mt-2 fw-bold">PROGEL SISTEMA</h3>
            <p class="mb-0 text-light">Control de Acceso</p>
        </div>
        <div class="card-body p-4">
            <?php if (isset($_GET['timeout'])): ?>
                <div class="alert alert-warning text-center p-2 fw-semibold">
                    <i class="bi bi-clock-history me-1"></i> Tu sesión se cerró automáticamente por inactividad.
                </div>
            <?php endif; ?>
            <?php if($error): ?>
                <div class="alert alert-danger text-center p-2"><i class="bi bi-exclamation-triangle"></i> <?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-4">
                    <label class="form-label fw-bold text-secondary">Número de Nómina</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-person-badge"></i></span>
                        <input type="number" name="nomina" class="form-control form-control-lg" placeholder="Ej. 12345" required autofocus>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold shadow-sm">
                    Ingresar <i class="bi bi-box-arrow-in-right ms-1"></i>
                </button>
            </form>
        </div>
    </div>
</body>
</html>
