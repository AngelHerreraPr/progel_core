<?php
$dbs = ['Progel_coreV2', 'progel_core', 'progel_core'];
$passwords = ['Pr0gel+2024', ''];

$sql = "
CREATE TABLE IF NOT EXISTS `bitacora_membranas` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `hora` varchar(10) NOT NULL,
  `etapa` tinyint NOT NULL,
  `paquete_numero` tinyint NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'OPERACION',
  `presion_entrada` decimal(6,2) DEFAULT NULL,
  `presion_salida` decimal(6,2) DEFAULT NULL,
  `delta_presion` decimal(6,2) DEFAULT NULL,
  `brix_entrada` decimal(6,2) DEFAULT NULL,
  `brix_salida` decimal(6,2) DEFAULT NULL,
  `brix_permeado` decimal(6,2) DEFAULT NULL,
  `operador_nomina` varchar(50) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_fecha_hora_paquete` (`fecha`, `hora`, `paquete_numero`),
  KEY `idx_fecha_etapa` (`fecha`, `etapa`),
  KEY `idx_etapa_paquete` (`etapa`, `paquete_numero`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

foreach ($dbs as $db) {
    $conn = null;
    foreach ($passwords as $p) {
        try {
            $conn = new PDO("mysql:host=127.0.0.1;dbname=$db;charset=utf8mb4", 'root', $p, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            break;
        } catch (PDOException $e) {
            continue;
        }
    }
    
    if ($conn) {
        $conn->exec($sql);
        echo "[OK] Tabla bitacora_membranas creada/verificada en $db\n";
    } else {
        echo "[ERROR] No se pudo conectar a $db\n";
    }
}
