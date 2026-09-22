<?php
// config/db_v2.php - Conexión a la nueva Base de Datos Progel_coreV2
date_default_timezone_set('America/Mazatlan');

$host_v2 = "localhost";
$user_v2 = "root";
$pass_v2 = "";
$db_v2   = "Progel_coreV2";

$conn_v2 = mysqli_connect($host_v2, $user_v2, $pass_v2, $db_v2);

if (!$conn_v2) {
    error_log("Error de conexión a Progel_coreV2: " . mysqli_connect_error());
} else {
    mysqli_set_charset($conn_v2, "utf8mb4");
}

