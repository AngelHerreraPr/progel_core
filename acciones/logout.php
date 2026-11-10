<?php
// progel_core/acciones/logout.php
session_start();
session_unset();
session_destroy();
// Limpiar cookies globales de SSO
setcookie('progel_nomina_activa', '', time() - 3600, '/');
setcookie('nomina', '', time() - 3600, '/');
$timeout_param = isset($_GET['timeout']) ? '?timeout=1' : '';
header("Location: ../login.php" . $timeout_param);
exit();
?>