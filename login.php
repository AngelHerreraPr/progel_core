<?php
// Progel_core/login.php
session_start();

// Si ya inició sesión, lo mandamos directo al mapa
if (isset($_SESSION['nomina'])) {
    header("Location: index.php");
    exit();
}

include 'config/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nomina = $_POST['nomina'] ?? '';
    
    if (!empty($nomina)) {
        $stmt = mysqli_prepare($conn, "SELECT nombre, rol, zona_asignada FROM usuarios WHERE nomina = ?");
        mysqli_stmt_bind_param($stmt, "i", $nomina);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($row = mysqli_fetch_assoc($result)) {
            // Guardamos sus datos en la sesión del navegador
            $_SESSION['nomina'] = $nomina;
            $_SESSION['nombre'] = $row['nombre'];
            $_SESSION['rol'] = $row['rol'];
            $_SESSION['zona'] = $row['zona_asignada'];
            
            // Acceso concedido
            header("Location: index.php");
            exit();
        } else {
            $error = "Nómina no autorizada en el sistema.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acceso - PROGEL</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
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
