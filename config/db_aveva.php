<?php
// Archivo: config/db_aveva.php

// Configuración de la base de datos SQL Server (AVEVA)
$sqlsrv_host = "192.168.1.105";
$sqlsrv_name = "AVEVA_TAGS";
$sqlsrv_user = "PBI";
$sqlsrv_pass = "Fer_Zam@2025"; // Pon la contraseña si la tiene, si no, déjalo vacío

try {
    // Armamos el DSN para SQL Server
    $dsn = "sqlsrv:Server=$sqlsrv_host;Database=$sqlsrv_name";
    
    // Opciones para manejar errores y forzar formato de texto
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];

    // Instanciamos la conexión PDO
    $conn_aveva = new PDO($dsn, $sqlsrv_user, $sqlsrv_pass, $options);
    
} catch (PDOException $e) {
    // Si falla, mostramos el error (puedes comentarlo después cuando esté en producción)
    die("Error de conexión a AVEVA: " . $e->getMessage());
}
?>