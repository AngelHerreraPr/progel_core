<?php
$dumpFile = 'C:/Users/Aherrera/Downloads/progel_core (5).sql';
$mysqlBin = 'C:/wamp64/bin/mysql/mysql8.4.7/bin/mysql.exe';
$user = 'root';
$pass = 'Pr0gel+2024';

if (!file_exists($dumpFile)) {
    die("ERROR: El archivo $dumpFile no existe.\n");
}

echo "=== IMPORTANDO DUMP EN progel_core ===\n";
echo "Archivo: $dumpFile (" . number_format(filesize($dumpFile)) . " bytes)\n";

// Target databases to keep both 100% updated
$dbs = ['progel_core', 'progel_core'];

foreach ($dbs as $db) {
    echo "\n--> Importando en base de datos: $db ...\n";
    $cmd = "\"$mysqlBin\" -u $user -p$pass --default-character-set=utf8mb4 $db < \"$dumpFile\" 2>&1";
    $output = [];
    $returnVar = 0;
    exec($cmd, $output, $returnVar);

    if ($returnVar === 0) {
        echo "[OK] Importación exitosa en $db!\n";
    } else {
        echo "[ERROR] Falló importación en $db (código $returnVar):\n";
        echo implode("\n", $output) . "\n";
    }
}

// Ahora verificar filas en ambas BD
$conn = mysqli_connect('127.0.0.1', $user, $pass);
if ($conn) {
    echo "\n=== VERIFICACIÓN DE FILAS ===\n";
    foreach ($dbs as $db) {
        $res = mysqli_query($conn, "SELECT COUNT(*) as total, MAX(id) as max_id, MAX(fecha_registro) as max_fecha FROM $db.bitacora_lecturas");
        if ($res && $row = mysqli_fetch_assoc($res)) {
            echo "Base de datos `$db`.bitacora_lecturas:\n";
            echo "   Total filas: " . number_format($row['total']) . "\n";
            echo "   Max ID: " . $row['max_id'] . "\n";
            echo "   Última fecha: " . $row['max_fecha'] . "\n";
        }
        
        $resEquipos = mysqli_query($conn, "SELECT COUNT(*) as c FROM $db.equipos");
        $cEq = $resEquipos ? mysqli_fetch_assoc($resEquipos)['c'] : 0;
        
        $resParam = mysqli_query($conn, "SELECT COUNT(*) as c FROM $db.parametros");
        $cParam = $resParam ? mysqli_fetch_assoc($resParam)['c'] : 0;
        
        echo "   Equipos: $cEq | Parámetros: $cParam\n";
    }
}
