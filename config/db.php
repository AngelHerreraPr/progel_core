<?php
// Forzar la hora exacta de México para todas las bitácoras
date_default_timezone_set('America/Mazatlan');

// Credenciales de tu servidor local
$host = "localhost";
$user = "root";
$pass = ""; 



$db_core = "progel_core";
$conn = mysqli_connect($host, $user, $pass, $db_core);

if (!$conn) {
    die("Error de conexión a progel_core: " . mysqli_connect_error());
}

$db_procesos = "progel_procesos";
$conn_procesos = mysqli_connect($host, $user, $pass, $db_procesos);

if (!$conn_procesos) {
    die("Error de conexión a progel_procesos: " . mysqli_connect_error());
}


?>