-- ====================================================================
-- PROGEL V2 - BASE DE DATOS INDUSTRIAL ESTRUCTURADA Y AUTODOCUMENTADA
-- Servidor: MySQL 8.4+ / MariaDB
-- Juego de caracteres: utf8mb4 / Collate: utf8mb4_unicode_ci
-- ====================================================================

CREATE DATABASE IF NOT EXISTS `Progel_coreV2` 
DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `Progel_coreV2`;

-- --------------------------------------------------------------------
-- 1. CATÁLOGOS BASE
-- --------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `catalogo_zonas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre de la zona de planta (ej. PRODUCCIÓN, SERVICIOS)',
  `descripcion` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo de zonas generales de la planta';

CREATE TABLE IF NOT EXISTS `catalogo_areas` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `zona_id` INT DEFAULT NULL COMMENT 'ID de zona a la que pertenece el área',
  `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre del área (ej. Área de Chillers, Secadores, etc.)',
  PRIMARY KEY (`id`),
  KEY `idx_area_zona` (`zona_id`),
  CONSTRAINT `fk_area_zona` FOREIGN KEY (`zona_id`) REFERENCES `catalogo_zonas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo de áreas operativas dentro de cada zona';

CREATE TABLE IF NOT EXISTS `catalogo_equipos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(50) NOT NULL UNIQUE COMMENT 'Identificador único legible (ej. CHILLER-VOT-1, SEC-01)',
  `nombre` VARCHAR(100) NOT NULL COMMENT 'Nombre comercial o técnico del equipo',
  `area_id` INT DEFAULT NULL,
  `tipo_equipo` ENUM('chiller_votator', 'chiller_normal', 'compresor', 'concentrador', 'secador', 'otro') NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = Activo en planta, 0 = Inactivo o dado de baja',
  PRIMARY KEY (`id`),
  KEY `idx_equipo_area` (`area_id`),
  CONSTRAINT `fk_equipo_area` FOREIGN KEY (`area_id`) REFERENCES `catalogo_areas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo maestro de equipos y maquinaria monitoreada';

CREATE TABLE IF NOT EXISTS `usuarios` (
  `nomina` VARCHAR(50) NOT NULL COMMENT 'Número de nómina del empleado (identificador único)',
  `nombre_completo` VARCHAR(120) NOT NULL COMMENT 'Nombre y apellido del empleado',
  `rol` ENUM('operador', 'supervisor', 'admin', 'auditor') NOT NULL DEFAULT 'operador',
  `zona_id` INT DEFAULT NULL,
  `area_id` INT DEFAULT NULL,
  `zona_asignada` VARCHAR(50) DEFAULT 'TODAS',
  `password_hash` VARCHAR(255) DEFAULT NULL COMMENT 'Hash seguro de contraseña',
  `activo` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = En funciones, 0 = Inactivo',
  `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`nomina`),
  KEY `idx_usuario_zona` (`zona_id`),
  KEY `idx_usuario_area` (`area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Catálogo de personal con acceso al sistema de captura';

-- --------------------------------------------------------------------
-- 2. BITÁCORAS HORARIAS POR TIPO DE MAQUINARIA (FORMATO HORIZONTAL)
-- Cada fila = Una hora de lectura completa de una máquina.
-- Cero confusiones de ID: Columnas explícitas con unidades de medida.
-- --------------------------------------------------------------------

-- 2.1 CHILLERS DE VOTATORS
CREATE TABLE IF NOT EXISTS `bitacora_chillers_votator` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `fecha` DATE NOT NULL COMMENT 'Fecha de la lectura (YYYY-MM-DD)',
  `hora` TINYINT NOT NULL COMMENT 'Hora del turno (1 a 24)',
  `equipo_nombre` VARCHAR(100) NOT NULL COMMENT 'Ej. Chillers Votator (1 al 4) o Chillers Votator (5 al 7)',
  `temp_inyeccion_maestro_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temperatura Inyección Maestro en °C',
  `temp_retorno_maestro_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temperatura Retorno Maestro en °C',
  `delta_temp_maestro_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Delta de temperatura Maestro en °C',
  `temp_inyeccion_esclavo_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temperatura Inyección Esclavo en °C',
  `temp_retorno_esclavo_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temperatura Retorno Esclavo en °C',
  `delta_temp_esclavo_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Delta de temperatura Esclavo en °C',
  `presion_inyeccion_glicol_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Presión de inyección del glicol en PSI',
  `operador_nomina` VARCHAR(50) DEFAULT NULL COMMENT 'Nómina del operador que capturó',
  `observaciones` TEXT DEFAULT NULL COMMENT 'Notas, anomalías o justificaciones de paro',
  `fecha_registro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Momento exacto en que se grabó en el servidor',
  PRIMARY KEY (`id`),
  KEY `idx_chiller_vot_fecha_hora` (`fecha`, `hora`),
  KEY `idx_chiller_vot_equipo` (`equipo_nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Bitácora horaria para Chillers del área de Votators';

-- 2.2 CHILLERS NORMALES
CREATE TABLE IF NOT EXISTS `bitacora_chillers_normales` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `fecha` DATE NOT NULL COMMENT 'Fecha de la lectura (YYYY-MM-DD)',
  `hora` TINYINT NOT NULL COMMENT 'Hora del turno (1 a 24)',
  `equipo_nombre` VARCHAR(100) NOT NULL COMMENT 'Ej. Chillers Normales (1 al 4) o Chillers Normales (5 al 7)',
  `concentracion_glicol_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Concentración de glicol (%)',
  `nivel_glicol_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Nivel del tanque de glicol (%)',
  `frecuencia_bombas_hz` DECIMAL(6,2) DEFAULT NULL COMMENT 'Frecuencia de bombas de envío (Hz)',
  `presion_bombas_glicol_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Presión de bombas de glicol (PSI)',
  -- Presiones por Chiller individual
  `presion_baja_ch1_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 1 - Presión baja de gas (PSI)',
  `presion_alta_ch1_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 1 - Presión alta de gas (PSI)',
  `presion_baja_ch2_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 2 - Presión baja de gas (PSI)',
  `presion_alta_ch2_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 2 - Presión alta de gas (PSI)',
  `presion_baja_ch3_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 3 - Presión baja de gas (PSI)',
  `presion_alta_ch3_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 3 - Presión alta de gas (PSI)',
  `presion_baja_ch4_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 4 - Presión baja de gas (PSI)',
  `presion_alta_ch4_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 4 - Presión alta de gas (PSI)',
  `presion_baja_ch5_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 5 - Presión baja de gas (PSI)',
  `presion_alta_ch5_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 5 - Presión alta de gas (PSI)',
  `presion_baja_ch6_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 6 - Presión baja de gas (PSI)',
  `presion_alta_ch6_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 6 - Presión alta de gas (PSI)',
  `presion_baja_ch7_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 7 - Presión baja de gas (PSI)',
  `presion_alta_ch7_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Chiller 7 - Presión alta de gas (PSI)',
  -- Temperaturas por circuito
  `temp_in_ch1_2_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temp. entrada chillers 1, 2 en °C (IN)',
  `temp_out_ch1_2_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temp. salida chillers 1, 2 en °C (OUT)',
  `delta_temp_ch1_2_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Delta de temperatura chillers 1, 2 en °C',
  `temp_in_ch3_4_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temp. entrada chillers 3, 4 en °C (IN)',
  `temp_out_ch3_4_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temp. salida chillers 3, 4 en °C (OUT)',
  `delta_temp_ch3_4_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Delta de temperatura chillers 3, 4 en °C',
  `temp_in_ch5_6_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temp. entrada chillers 5, 6 en °C (IN)',
  `temp_out_ch5_6_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temp. salida chillers 5, 6 en °C (OUT)',
  `delta_temp_ch5_6_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Delta de temperatura chillers 5, 6 en °C',
  `temp_in_ch7_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temp. entrada chiller 7 en °C (IN)',
  `temp_out_ch7_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temp. salida chiller 7 en °C (OUT)',
  `delta_temp_ch7_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Delta de temperatura chiller 7 en °C',
  -- Rutinas de mantenimiento
  `rutina_limpieza_condensadores` VARCHAR(50) DEFAULT NULL COMMENT 'Estado de limpieza de condensadores',
  `rutina_limpieza_filtros_bombas` VARCHAR(50) DEFAULT NULL COMMENT 'Estado de limpieza de filtros bombas',
  `rutina_limpieza_filtros_glicol` VARCHAR(50) DEFAULT NULL COMMENT 'Estado de limpieza de filtros glicol',
  `rutina_limpieza_filtros_succion` VARCHAR(50) DEFAULT NULL COMMENT 'Estado de limpieza de filtros succión',
  `rutina_alarmas` VARCHAR(50) DEFAULT NULL COMMENT 'Revisión y estatus de alarmas',
  `operador_nomina` VARCHAR(50) DEFAULT NULL,
  `observaciones` TEXT DEFAULT NULL,
  `fecha_registro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chiller_norm_fecha_hora` (`fecha`, `hora`),
  KEY `idx_chiller_norm_equipo` (`equipo_nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Bitácora horaria para Chillers Normales de Planta';

-- 2.3 COMPRESORES
CREATE TABLE IF NOT EXISTS `bitacora_compresores` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `fecha` DATE NOT NULL COMMENT 'Fecha de la lectura (YYYY-MM-DD)',
  `hora` TINYINT NOT NULL COMMENT 'Hora del turno (1 a 24)',
  `compresor_nombre` VARCHAR(100) NOT NULL COMMENT 'Compresor 30 HP o Compresor 50 HP',
  `humedad_ambiental_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Humedad ambiental (%)',
  `temp_ambiental_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temperatura ambiental (°C)',
  `horas_trabajadas` DECIMAL(10,2) DEFAULT NULL COMMENT 'Horómetro / Horas trabajadas acumuladas',
  `limpieza_filtros` VARCHAR(50) DEFAULT NULL COMMENT 'Estatus de limpieza de filtros',
  `inspeccion_alarmas` VARCHAR(50) DEFAULT NULL COMMENT 'Estatus de alarmas del compresor',
  `operador_nomina` VARCHAR(50) DEFAULT NULL,
  `observaciones` TEXT DEFAULT NULL,
  `fecha_registro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_compresor_fecha_hora` (`fecha`, `hora`),
  KEY `idx_compresor_nombre` (`compresor_nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Bitácora horaria para Compresores de Aire';

-- 2.4 CONCENTRADORES
CREATE TABLE IF NOT EXISTS `bitacora_concentradores` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `fecha` DATE NOT NULL COMMENT 'Fecha de la lectura (YYYY-MM-DD)',
  `hora` TINYINT NOT NULL COMMENT 'Hora del turno (1 a 24)',
  `concentrador_nombre` VARCHAR(100) NOT NULL COMMENT 'Concentrador 1, 2, 3, 4 o Concentrador Invertido',
  `lote` VARCHAR(100) DEFAULT NULL COMMENT 'Número o código de lote de producción',
  `solidos_entrada_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Sólidos a la entrada (%)',
  `flujo_entrada_lpm` DECIMAL(6,2) DEFAULT NULL COMMENT 'Flujo a la entrada (LPM)',
  `presion_vapor_psi` DECIMAL(6,2) DEFAULT NULL COMMENT 'Presión de vapor (lb/pulg²)',
  `vacio_mmhg` DECIMAL(6,2) DEFAULT NULL COMMENT 'Vacío (mm Hg)',
  `temp_salida_grenetina_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temperatura de salida grenetina (°C)',
  `solidos_salida_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Sólidos finales a la salida (%)',
  `flujo_salida_lpm` DECIMAL(6,2) DEFAULT NULL COMMENT 'Flujo a la salida (LPM)',
  -- Variables específicas de Concentrador Invertido
  `temp_caldo_entrada_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Invertido: Temp. caldo entrada (°C)',
  `temp_agua_in_condensador_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Invertido: Temp. entrada agua condensador (°C)',
  `temp_agua_out_condensador_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Invertido: Temp. salida agua condensador (°C)',
  `presion_vacio_kg_cm2` DECIMAL(6,2) DEFAULT NULL COMMENT 'Invertido: Presión de vacío (kg/cm²)',
  `operador_nomina` VARCHAR(50) DEFAULT NULL,
  `observaciones` TEXT DEFAULT NULL,
  `fecha_registro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_concentrador_fecha_hora` (`fecha`, `hora`),
  KEY `idx_concentrador_nombre` (`concentrador_nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Bitácora horaria para los Concentradores de Grenetina';

-- 2.5 SECADORES
CREATE TABLE IF NOT EXISTS `bitacora_secadores` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `fecha` DATE NOT NULL COMMENT 'Fecha de la lectura (YYYY-MM-DD)',
  `hora` TINYINT NOT NULL COMMENT 'Hora del turno (1 a 24)',
  `secador_nombre` VARCHAR(100) NOT NULL COMMENT 'Secador 1, 2, 3 o 4',
  `lote` VARCHAR(100) DEFAULT NULL COMMENT 'Número o código de lote de producción',
  `flujo_l_min` DECIMAL(6,2) DEFAULT NULL COMMENT 'Flujo de alimentación (L/min)',
  `velocidad_banda_m_h` DECIMAL(6,2) DEFAULT NULL COMMENT 'Velocidad de la banda (m/h)',
  `frecuencia_hz` DECIMAL(6,2) DEFAULT NULL COMMENT 'Frecuencia de malla (Hz)',
  `altura_galleta_cm` DECIMAL(6,2) DEFAULT NULL COMMENT 'Altura de la galleta (cm)',
  `humedad_churro_recamara_5_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Humedad churro recámara 5 (%)',
  `humedad_churro_penultima_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Humedad churro penúltima (%)',
  `humedad_relativa_recamara_5_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Humedad relativa recámara 5 (%)',
  `humedad_producto_final_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Humedad producto final (%)',
  `temp_recamara_1_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temperatura recámara 1 (°C)',
  `temp_recamara_2_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temperatura recámara 2 (°C)',
  `temp_recamara_3_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temperatura recámara 3 (°C)',
  `temp_recamara_4_c` DECIMAL(6,2) DEFAULT NULL COMMENT 'Temperatura recámara 4 (°C)',
  `textura_galleta` VARCHAR(50) DEFAULT NULL COMMENT 'Evaluación sensorial de textura',
  `operador_nomina` VARCHAR(50) DEFAULT NULL,
  `observaciones` TEXT DEFAULT NULL,
  `fecha_registro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_secador_fecha_hora` (`fecha`, `hora`),
  KEY `idx_secador_nombre` (`secador_nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Bitácora horaria para los Secadores de Planta';

-- --------------------------------------------------------------------
-- 3. REPORTE MAESTRO DE PRODUCCIÓN (SÁBANA DE CONTROL DE PLANTA)
-- Reemplaza a sup_captura_produccion con nombres 100% claros y legibles.
-- --------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `produccion_reporte_maestro` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `fecha` DATE NOT NULL COMMENT 'Fecha de producción (YYYY-MM-DD)',
  `hora` TINYINT NOT NULL COMMENT 'Hora del turno (1 a 24)',
  
  -- Consumo y preparación
  `consumo_cuero_kg` DECIMAL(10,2) DEFAULT NULL COMMENT 'Consumo de cuero / materia prima en Kg',
  `cocedores_manual` VARCHAR(100) DEFAULT NULL COMMENT 'Estatus y asignación de cocedores manuales',
  `caldo_pre_uf` VARCHAR(100) DEFAULT NULL COMMENT 'Control de caldo Pre Ultrafiltración',
  `pre_concentrado` VARCHAR(100) DEFAULT NULL COMMENT 'Control de caldo Pre Concentrado',
  `caldo_concentrado` VARCHAR(100) DEFAULT NULL COMMENT 'Control de caldo Concentrado',
  
  -- Operación de Votators
  `votators_activos` VARCHAR(100) DEFAULT NULL COMMENT 'Identificación de votators en marcha (ej. 1,2,3)',
  `flujo_votator_1_lh` DECIMAL(8,2) DEFAULT NULL COMMENT 'Flujo Votator 1 en L/h',
  `flujo_votator_2_lh` DECIMAL(8,2) DEFAULT NULL COMMENT 'Flujo Votator 2 en L/h',
  `flujo_votator_3_lh` DECIMAL(8,2) DEFAULT NULL COMMENT 'Flujo Votator 3 en L/h',
  `flujo_votator_4_lh` DECIMAL(8,2) DEFAULT NULL COMMENT 'Flujo Votator 4 en L/h',
  `flujo_votator_5_lh` DECIMAL(8,2) DEFAULT NULL COMMENT 'Flujo Votator 5 en L/h',
  `flujo_votator_6_lh` DECIMAL(8,2) DEFAULT NULL COMMENT 'Flujo Votator 6 en L/h',
  `solidos_brix` DECIMAL(6,2) DEFAULT NULL COMMENT 'Concentración de sólidos en grados Brix (°Bx)',
  
  -- Secadores / Túneles (Humedades y Velocidades)
  `humedad_tunel_1_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Humedad túnel 1 (%)',
  `humedad_tunel_2_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Humedad túnel 2 (%)',
  `humedad_tunel_3_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Humedad túnel 3 (%)',
  `humedad_tunel_4_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Humedad túnel 4 (%)',
  `humedad_tunel_5_porc` DECIMAL(6,2) DEFAULT NULL COMMENT 'Humedad túnel 5 (%)',
  
  `velocidad_tunel_1_mh` DECIMAL(6,2) DEFAULT NULL COMMENT 'Velocidad túnel 1 en m/h',
  `velocidad_tunel_2_mh` DECIMAL(6,2) DEFAULT NULL COMMENT 'Velocidad túnel 2 en m/h',
  `velocidad_tunel_3_mh` DECIMAL(6,2) DEFAULT NULL COMMENT 'Velocidad túnel 3 en m/h',
  `velocidad_tunel_4_mh` DECIMAL(6,2) DEFAULT NULL COMMENT 'Velocidad túnel 4 en m/h',
  
  -- Balances de Masa y Rendimiento
  `kg_teoricos` DECIMAL(10,2) DEFAULT NULL COMMENT 'Kilogramos teóricos calculados',
  `kg_reales` DECIMAL(10,2) DEFAULT NULL COMMENT 'Kilogramos reales pesados/producidos',
  `rechazo_kg` DECIMAL(10,2) DEFAULT NULL COMMENT 'Kilogramos de producto rechazado',
  `remoler_kg` DECIMAL(10,2) DEFAULT NULL COMMENT 'Kilogramos de producto para remoler',
  `eficiencia_porcentaje` DECIMAL(6,2) DEFAULT NULL COMMENT 'Eficiencia operativa de la hora (%)',
  
  -- Auditoría y Supervisión
  `observaciones_acciones` TEXT DEFAULT NULL COMMENT 'Acciones correctivas u observaciones del supervisor',
  `supervisor_nomina` VARCHAR(50) DEFAULT NULL COMMENT 'Nómina del supervisor de turno',
  `fecha_registro_real` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp real de inserción',
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_prod_fecha_hora` (`fecha`, `hora`),
  KEY `idx_prod_maestro_supervisor` (`supervisor_nomina`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sábana maestra de producción horaria de planta';
