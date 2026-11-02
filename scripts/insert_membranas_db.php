<?php
$dbs = ['progel_core', 'progel_core'];

foreach ($dbs as $db) {
    $conn = mysqli_connect('127.0.0.1', 'root', 'Pr0gel+2024', $db);
    if (!$conn) {
        echo "Error conectando a $db\n";
        continue;
    }

    // Asegurar que exista el área 20 (Área de Concentradores / Membranas)
    $qArea = mysqli_query($conn, "SELECT id FROM areas WHERE id = 20");
    if (mysqli_num_rows($qArea) == 0) {
        mysqli_query($conn, "INSERT INTO areas (id, zona_id, nombre) VALUES (20, 2, 'Área de Concentradores y Membranas')");
    }

    // Insertar o actualizar equipo 100
    $qEq = mysqli_query($conn, "SELECT id FROM equipos WHERE id = 100");
    if (mysqli_num_rows($qEq) == 0) {
        $insert = mysqli_query($conn, "INSERT INTO equipos (id, nombre, area_id, tipo) VALUES (100, 'Sistema de Membranas', 20, 'membrana')");
        if ($insert) {
            echo "[OK] Equipo 100 insertado en $db\n";
        } else {
            echo "[ERROR] Falló inserción en $db: " . mysqli_error($conn) . "\n";
        }
    } else {
        mysqli_query($conn, "UPDATE equipos SET nombre = 'Sistema de Membranas', area_id = 20, tipo = 'membrana' WHERE id = 100");
        echo "[OK] Equipo 100 ya existía y fue actualizado en $db\n";
    }
}

// También en Progel_coreV2 si catalogo_equipos existe
$connV2 = mysqli_connect('127.0.0.1', 'root', 'Pr0gel+2024', 'Progel_coreV2');
if ($connV2) {
    mysqli_query($connV2, "INSERT IGNORE INTO catalogo_equipos (id, codigo, nombre, area_id, tipo_equipo, activo) VALUES (100, 'EQ-100', 'Sistema de Membranas', 20, 'membrana', 1)");
    echo "[OK] Equipo 100 asegurado en Progel_coreV2.catalogo_equipos\n";
}
