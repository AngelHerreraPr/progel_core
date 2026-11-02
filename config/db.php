<?php
// Forzar la hora exacta de México para todas las bitácoras
date_default_timezone_set('America/Mazatlan');

// Credenciales de tu servidor local
$host = "localhost";
$user = "root";
$pass = "Pr0gel+2024"; 



$db_core = "progel_corev2";
$conn = mysqli_connect($host, $user, $pass, $db_core);

if (!$conn) {
    die("Error de conexión a progel_core: " . mysqli_connect_error());
}
mysqli_set_charset($conn, "utf8mb4");

$db_procesos = "progel_procesos";
$conn_procesos = mysqli_connect($host, $user, $pass, $db_procesos);

if (!$conn_procesos) {
    die("Error de conexión a progel_procesos: " . mysqli_connect_error());
}
mysqli_set_charset($conn_procesos, "utf8mb4");

$db_extraccion = "bd_extraccion";
$conn_extraccion = @mysqli_connect($host, $user, $pass, $db_extraccion);
if ($conn_extraccion) {
    mysqli_set_charset($conn_extraccion, "utf8mb4");
}


?>