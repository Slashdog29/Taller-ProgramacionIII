-- =====================================================================
-- SCRIPT COMPLETO PARA LA BASE DE DATOS bdcyber-center
-- CON MANEJO DE LLAVES FORÁNEAS DURANTE LA CARGA DE DATOS
-- =====================================================================

-- Desactivar comprobaciones de claves foráneas para evitar errores de orden
SET FOREIGN_KEY_CHECKS = 0;

-- Eliminar la base de datos si existe y crearla de nuevo
DROP DATABASE IF EXISTS `bdcyber-center`;
CREATE DATABASE `bdcyber-center`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `bdcyber-center`;

-- Activar el planificador de eventos (requiere privilegios)
SET GLOBAL event_scheduler = ON;

-- =====================================================================
-- 1. TABLAS PRINCIPALES (en orden de dependencia)
-- =====================================================================

-- Tabla marca (referenciada por computadoras)
DROP TABLE IF EXISTS `marca`;
CREATE TABLE `marca` (
  `id_marca` int NOT NULL AUTO_INCREMENT,
  `nombremarca` varchar(50) NOT NULL,
  PRIMARY KEY (`id_marca`),
  UNIQUE KEY `nombremarca` (`nombremarca`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tipos de periféricos
DROP TABLE IF EXISTS `tipos_periferico`;
CREATE TABLE `tipos_periferico` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_componente` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_componente` (`nombre_componente`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tipos de cliente
DROP TABLE IF EXISTS `tipos_cliente`;
CREATE TABLE `tipos_cliente` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) NOT NULL,
  `tarifa_por_hora` decimal(10,2) NOT NULL DEFAULT 0.00,
  `exento_pago` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`),
  CONSTRAINT `chk_tarifa_no_negativa` CHECK (`tarifa_por_hora` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuarios del sistema
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_completo` varchar(150) NOT NULL,
  `cedula_identidad` varchar(20) NOT NULL,
  `correo_institucional` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `rol` enum('super_admin','auditor','operador') NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultimo_login` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cedula_identidad` (`cedula_identidad`),
  UNIQUE KEY `correo_institucional` (`correo_institucional`),
  KEY `idx_rol_activo` (`rol`,`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Computadoras (depende de marca)
DROP TABLE IF EXISTS `computadoras`;
CREATE TABLE `computadoras` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_puesto` int NOT NULL,
  `codigo_bien_nacional` varchar(100) NOT NULL,
  `numero_serial_chasis` varchar(100) NOT NULL,
  `marca` int NOT NULL,
  `modelo` varchar(50) NOT NULL,
  `color` varchar(30) NOT NULL,
  `direccion_ip` varchar(45) DEFAULT NULL,
  `ubicacion_administrativa` varchar(100) DEFAULT 'Sala de Ciber Center',
  `estado_operativo` enum('disponible','ocupado','mantenimiento','desincorporado') DEFAULT 'disponible',
  `fecha_incorporacion` date NOT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_puesto` (`numero_puesto`),
  UNIQUE KEY `codigo_bien_nacional` (`codigo_bien_nacional`),
  UNIQUE KEY `numero_serial_chasis` (`numero_serial_chasis`),
  UNIQUE KEY `direccion_ip` (`direccion_ip`),
  KEY `idx_estado` (`estado_operativo`),
  KEY `idx_ip` (`direccion_ip`),
  KEY `marca` (`marca`),
  CONSTRAINT `computadoras_ibfk_1` FOREIGN KEY (`marca`) REFERENCES `marca` (`id_marca`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Periféricos (depende de computadoras y tipos_periferico)
DROP TABLE IF EXISTS `perifericos`;
CREATE TABLE `perifericos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `computadora_id` int DEFAULT NULL,
  `tipo_periferico_id` int NOT NULL,
  `codigo_bien_nacional` varchar(100) NOT NULL,
  `numero_serial_fabrica` varchar(100) NOT NULL,
  `marca` varchar(50) NOT NULL,
  `modelo` varchar(50) NOT NULL,
  `color` varchar(30) NOT NULL,
  `estado_fisico` enum('excelente','bueno','regular','dañado') DEFAULT 'excelente',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_bien_nacional` (`codigo_bien_nacional`),
  UNIQUE KEY `numero_serial_fabrica` (`numero_serial_fabrica`),
  KEY `idx_computadora` (`computadora_id`),
  KEY `idx_tipo` (`tipo_periferico_id`),
  CONSTRAINT `perifericos_ibfk_1` FOREIGN KEY (`computadora_id`) REFERENCES `computadoras` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `perifericos_ibfk_2` FOREIGN KEY (`tipo_periferico_id`) REFERENCES `tipos_periferico` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clientes (depende de tipos_cliente)
DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo_cliente_id` int NOT NULL,
  `cedula_o_codigo` varchar(50) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `correo` varchar(100) DEFAULT NULL,
  `estado_cuenta` enum('activo','suspendido') DEFAULT 'activo',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `cedula_o_codigo` (`cedula_o_codigo`),
  KEY `idx_tipo_cliente` (`tipo_cliente_id`),
  KEY `idx_estado_cuenta` (`estado_cuenta`),
  CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`tipo_cliente_id`) REFERENCES `tipos_cliente` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_cedula_valida` CHECK (char_length(`cedula_o_codigo`) >= 5)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sesiones (depende de computadoras, clientes, usuarios)
DROP TABLE IF EXISTS `sesiones`;
CREATE TABLE `sesiones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `computadora_id` int NOT NULL,
  `cliente_id` int NOT NULL,
  `usuario_operador_id` int NOT NULL,
  `hora_inicio` timestamp NOT NULL DEFAULT current_timestamp(),
  `hora_fin` timestamp NULL DEFAULT NULL,
  `minutos_consumidos` int GENERATED ALWAYS AS (timestampdiff(MINUTE,`hora_inicio`,`hora_fin`)) STORED,
  `monto_tarifa_aplicada` decimal(10,2) NOT NULL,
  `monto_total_pagado` decimal(10,2) DEFAULT 0.00,
  `comprobante_factura` varchar(50) DEFAULT NULL,
  `estado_transaccion` enum('en_curso','finalizado','anulado') DEFAULT 'en_curso',
  PRIMARY KEY (`id`),
  UNIQUE KEY `comprobante_factura` (`comprobante_factura`),
  KEY `usuario_operador_id` (`usuario_operador_id`),
  KEY `idx_computadora_estado` (`computadora_id`,`estado_transaccion`),
  KEY `idx_cliente` (`cliente_id`),
  KEY `idx_fechas` (`hora_inicio`,`hora_fin`),
  CONSTRAINT `sesiones_ibfk_1` FOREIGN KEY (`computadora_id`) REFERENCES `computadoras` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `sesiones_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `sesiones_ibfk_3` FOREIGN KEY (`usuario_operador_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_monto_tarifa_positivo` CHECK (`monto_tarifa_aplicada` >= 0),
  CONSTRAINT `chk_monto_pagado_no_negativo` CHECK (`monto_total_pagado` >= 0),
  CONSTRAINT `chk_minutos_no_negativos` CHECK (`minutos_consumidos` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditorías de bienes (depende de usuarios)
DROP TABLE IF EXISTS `auditorias_bienes`;
CREATE TABLE `auditorias_bienes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tabla_auditada` enum('computadoras','perifericos') NOT NULL,
  `activo_id` int NOT NULL,
  `codigo_bien_nacional_verificado` varchar(100) NOT NULL,
  `estado_constatado` enum('operativo','no_localizado','dañado_reparable','propuesto_descargo') NOT NULL,
  `observaciones_legales` text NOT NULL,
  `fecha_auditoria` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_auditor_id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_auditor_id` (`usuario_auditor_id`),
  KEY `idx_fecha_auditoria` (`fecha_auditoria`),
  CONSTRAINT `auditorias_bienes_ibfk_1` FOREIGN KEY (`usuario_auditor_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 2. TABLAS COMPLEMENTARIAS
-- =====================================================================

-- Historial de eventos
DROP TABLE IF EXISTS `historial`;
CREATE TABLE `historial` (
  `idhistorial` int NOT NULL AUTO_INCREMENT,
  `usuario` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `fyh` datetime NOT NULL,
  `sector` varchar(50) NOT NULL,
  `acciones` text NOT NULL,
  PRIMARY KEY (`idhistorial`),
  KEY `idx_usuario` (`usuario`),
  KEY `idx_fyh` (`fyh`),
  KEY `idx_sector` (`sector`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuración global
DROP TABLE IF EXISTS `configuracion`;
CREATE TABLE `configuracion` (
  `id` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) NOT NULL,
  `valor` text NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log de cambios en bienes
DROP TABLE IF EXISTS `log_cambios_bienes`;
CREATE TABLE `log_cambios_bienes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tabla_afectada` varchar(50) DEFAULT NULL,
  `id_registro` int DEFAULT NULL,
  `campo_modificado` varchar(50) DEFAULT NULL,
  `valor_anterior` text DEFAULT NULL,
  `valor_nuevo` text DEFAULT NULL,
  `usuario_id` int NOT NULL,
  `usuario_ejecutor` varchar(150) DEFAULT NULL,
  `fecha_cambio` timestamp NOT NULL DEFAULT current_timestamp(),
  `accion` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tabla_registro` (`tabla_afectada`,`id_registro`),
  KEY `idx_usuario` (`usuario_id`),
  CONSTRAINT `log_cambios_bienes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- 3. CARGA DE DATOS (en orden de dependencia)
-- =====================================================================

-- Datos de marca
INSERT INTO `marca` (`id_marca`, `nombremarca`) VALUES
(1,'lenovo'),(2,'hp'),(3,'dell'),(4,'apple'),(5,'acer'),
(6,'toshiva'),(7,'asus'),(8,'samsung'),(9,'soni'),(10,'lg'),
(11,'alienwre'),(12,'lanix'),(13,'generica'),(14,'msi'),(15,'gigabyte'),
(16,'huawei'),(17,'razer'),(18,'corsair'),(19,'logitech');

-- Tipos de periférico
INSERT INTO `tipos_periferico` (`nombre_componente`) VALUES
('Monitor'),('Teclado'),('Mouse'),('Auriculares / Audífonos'),
('Cámara Web'),('Regulador de Voltaje / UPS');

-- Tipos de cliente
INSERT INTO `tipos_cliente` (`nombre_rol`, `tarifa_por_hora`, `exento_pago`) VALUES
('Invitado', 2.50, 0),
('Estudiante', 1.50, 0),
('Profesor', 0.00, 1),
('Administrativo', 0.00, 1);

-- Usuario administrador (contraseña: admin123)
INSERT INTO `usuarios` (`id`, `nombre_completo`, `cedula_identidad`, `correo_institucional`, `password_hash`, `rol`, `activo`) VALUES
(1, 'Administrador', '00000000', 'admin@ciber.edu', '$2y$10$zQ8IuFI9B3yHCS6Y9Mqz5OdV/KM7rA3/1j69B0LlHxn0x1vr7r1Om', 'super_admin', 1);

-- Computadoras (8 equipos)
INSERT INTO `computadoras` (`id`, `numero_puesto`, `codigo_bien_nacional`, `numero_serial_chasis`, `marca`, `modelo`, `color`, `direccion_ip`, `estado_operativo`, `fecha_incorporacion`) VALUES
(1, 1, 'BIEN-PC-001', 'SN-CHASIS-001', 3, 'Optiplex 3080', 'Negro', '192.168.1.101', 'ocupado', '2026-05-15'),
(2, 2, 'BIEN-PC-002', 'SN-CHASIS-002', 1, 'ThinkCentre M70q', 'Negro', '192.168.1.102', 'ocupado', '2026-01-15'),
(3, 3, 'BIEN-PC-003', 'SN-CHASIS-003', 2, 'ProDesk 400 G6', 'Gris Plata', '192.168.1.103', 'ocupado', '2026-02-10'),
(4, 4, 'BIEN-PC-004', 'SN-CHASIS-004', 3, 'Optiplex 5090', 'Negro', '192.168.1.104', 'disponible', '2026-03-01'),
(5, 5, 'BIEN-PC-005', 'SN-CHASIS-005', 7, 'ROG Strix GA15', 'Gris Oscuro', '192.168.1.105', 'disponible', '2026-03-15'),
(6, 6, 'BIEN-PC-006', 'SN-CHASIS-006', 1, 'IdeaCentre 5', 'Negro', '192.168.1.106', 'disponible', '2026-04-20'),
(7, 7, 'BIEN-PC-007', 'SN-CHASIS-007', 2, 'EliteDesk 800', 'Negro', '192.168.1.107', 'disponible', '2026-05-02'),
(8, 8, 'BIEN-PC-008', 'SN-CHASIS-008', 5, 'Aspire TC', 'Negro', '192.168.1.108', 'disponible', '2026-05-10');

-- Periféricos (referencian computadoras existentes)
INSERT INTO `perifericos` (`computadora_id`, `tipo_periferico_id`, `codigo_bien_nacional`, `numero_serial_fabrica`, `marca`, `modelo`, `color`, `estado_fisico`) VALUES
(1, 1, 'BIEN-MON-001', 'SN-MON-001', 'Dell', 'E2420H', 'Negro', 'excelente'),
(1, 2, 'BIEN-TEC-001', 'SN-TEC-001', 'Logitech', 'K120', 'Negro', 'bueno'),
(1, 3, 'BIEN-MOU-001', 'SN-MOU-001', 'Logitech', 'M90', 'Negro', 'bueno'),
(2, 1, 'BIEN-MON-002', 'SN-MON-002', 'Lenovo', 'ThinkVision T24i', 'Negro', 'excelente'),
(2, 2, 'BIEN-TEC-002', 'SN-TEC-002', 'Lenovo', 'Essential', 'Negro', 'excelente'),
(2, 3, 'BIEN-MOU-002', 'SN-MOU-002', 'Lenovo', 'Essential Mouse', 'Negro', 'excelente'),
(2, 6, 'BIEN-UPS-002', 'SN-UPS-002', 'APC', 'Easy UPS 650VA', 'Negro', 'bueno'),
(3, 1, 'BIEN-MON-003', 'SN-MON-003', 'HP', 'P24h G4', 'Negro', 'excelente'),
(3, 2, 'BIEN-TEC-003', 'SN-TEC-003', 'HP', 'Pavilion 300', 'Negro', 'bueno'),
(3, 3, 'BIEN-MOU-003', 'SN-MOU-003', 'HP', 'Pavilion 300 M', 'Negro', 'bueno'),
(4, 1, 'BIEN-MON-004', 'SN-MON-004', 'Samsung', 'T35F', 'Negro', 'bueno'),
(4, 4, 'BIEN-AUD-004', 'SN-AUD-004', 'Sony', 'WH-CH510', 'Azul', 'regular'),
(5, 1, 'BIEN-MON-005', 'SN-MON-005', 'Asus', 'VP228HE', 'Negro', 'excelente'),
(5, 2, 'BIEN-TEC-005', 'SN-TEC-005', 'Razer', 'Cynosa V2', 'Negro', 'excelente'),
(5, 3, 'BIEN-MOU-005', 'SN-MOU-005', 'Razer', 'DeathAdder Essential', 'Negro', 'excelente'),
(5, 5, 'BIEN-CAM-005', 'SN-CAM-005', 'Logitech', 'C920 HD Pro', 'Negro', 'excelente');

-- Clientes
INSERT INTO `clientes` (`id`, `tipo_cliente_id`, `cedula_o_codigo`, `nombre`, `apellido`, `correo`, `estado_cuenta`) VALUES
(1, 2, 'V-12345678', 'Juan', 'Pérez', 'juan@estudiante.edu', 'activo'),
(2, 1, 'V-20111222', 'Carlos', 'Mendoza', 'carlos.mendoza@gmail.com', 'activo'),
(3, 2, 'V-25333444', 'María', 'Rodríguez', 'maria.rodriguez@estudiante.edu', 'activo'),
(4, 2, 'V-27555666', 'Alejandro', 'Gómez', 'alejandro.gomez@estudiante.edu', 'activo'),
(5, 3, 'V-15888999', 'Roberto', 'Lovera', 'roberto.lovera@profesor.edu', 'activo'),
(6, 4, 'V-18222333', 'Ana', 'Castillo', 'ana.castillo@administrativo.edu', 'activo'),
(7, 2, 'V-29444555', 'Luis', 'Sánchez', 'luis.sanchez@estudiante.edu', 'suspendido'),
(8, 1, 'V-30666777', 'Gabriela', 'Torres', NULL, 'activo');

-- Sesiones
INSERT INTO `sesiones` (`id`, `computadora_id`, `cliente_id`, `usuario_operador_id`, `hora_inicio`, `hora_fin`, `monto_tarifa_aplicada`, `monto_total_pagado`, `comprobante_factura`, `estado_transaccion`) VALUES
(1, 1, 1, 1, '2026-05-15 08:00:00', '2026-05-15 09:00:00', 2.50, 2.50, 'FAC-20260515-00001', 'finalizado'),
(2, 2, 2, 1, '2026-05-15 08:30:00', '2026-05-15 10:30:00', 1.50, 3.00, 'FAC-20260515-00002', 'finalizado'),
(3, 3, 5, 1, '2026-05-15 09:00:00', '2026-05-15 10:15:00', 0.00, 0.00, 'FAC-20260515-00003', 'finalizado');

-- Configuración
INSERT INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
('impuesto_porcentaje', '16', 'IVA o impuesto aplicado al total'),
('duracion_maxima_sesion_horas', '4', 'Máximo de horas por sesión permitida'),
('redondear_minutos', '5', 'Redondear los minutos consumidos al múltiplo de X'),
('hora_cierre_automatico', '23:59:59', 'Hora límite para forzar cierre de sesiones'),
('notificar_mantenimiento', '1', 'Si está activo, envía alertas de mantenimiento (1/0)');

-- =====================================================================
-- 4. FUNCIONES, PROCEDIMIENTOS, VISTAS, TRIGGERS Y EVENTOS
-- (Se mantienen igual que antes, pero los incluyo completos)
-- =====================================================================

DELIMITER ;;

DROP FUNCTION IF EXISTS `calcular_costo_sesion`;
CREATE FUNCTION `calcular_costo_sesion`(p_tarifa_hora DECIMAL(10,2), p_minutos INT)
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE redondear INT;
    DECLARE minutos_redondeados INT;
    SET redondear = COALESCE((SELECT CAST(valor AS UNSIGNED) FROM configuracion WHERE clave = 'redondear_minutos'), 5);
    SET minutos_redondeados = CEIL(p_minutos / redondear) * redondear;
    RETURN ROUND((minutos_redondeados / 60) * p_tarifa_hora, 2);
END;;

DROP FUNCTION IF EXISTS `aplicar_impuesto`;
CREATE FUNCTION `aplicar_impuesto`(p_monto DECIMAL(10,2))
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE impuesto DECIMAL(5,2);
    SET impuesto = COALESCE((SELECT CAST(valor AS DECIMAL(5,2)) FROM configuracion WHERE clave = 'impuesto_porcentaje'), 16);
    RETURN ROUND(p_monto * (1 + impuesto/100), 2);
END;;

DROP PROCEDURE IF EXISTS `sp_cerrar_sesiones_vencidas`;
CREATE PROCEDURE `sp_cerrar_sesiones_vencidas`()
BEGIN
    DECLARE max_horas INT;
    SET max_horas = COALESCE((SELECT CAST(valor AS UNSIGNED) FROM configuracion WHERE clave = 'duracion_maxima_sesion_horas'), 4);
    
    UPDATE sesiones
    SET hora_fin = NOW(),
        estado_transaccion = 'anulado'
    WHERE hora_fin IS NULL
      AND estado_transaccion = 'en_curso'
      AND TIMESTAMPDIFF(HOUR, hora_inicio, NOW()) >= max_horas;
      
    UPDATE computadoras c
    JOIN sesiones s ON c.id = s.computadora_id
    SET c.estado_operativo = 'disponible'
    WHERE s.hora_fin IS NOT NULL 
      AND s.estado_transaccion = 'anulado'
      AND c.estado_operativo = 'ocupado';
END;;

DROP PROCEDURE IF EXISTS `sp_cierre_caja`;
CREATE PROCEDURE `sp_cierre_caja`(IN p_fecha DATE, IN p_id_usuario_cierre INT)
BEGIN
    DECLARE total_recaudado DECIMAL(12,2);
    DECLARE total_sesiones INT;
    DECLARE minutos_totales INT;
    
    SELECT IFNULL(SUM(monto_total_pagado), 0),
           COUNT(*),
           IFNULL(SUM(TIMESTAMPDIFF(MINUTE, hora_inicio, hora_fin)), 0)
    INTO total_recaudado, total_sesiones, minutos_totales
    FROM sesiones
    WHERE DATE(hora_inicio) = p_fecha
      AND estado_transaccion = 'finalizado';
      
    INSERT INTO historial (usuario, ip, fyh, sector, acciones)
    VALUES (
        (SELECT nombre_completo FROM usuarios WHERE id = p_id_usuario_cierre),
        'sistema',
        NOW(),
        'cierre_caja',
        CONCAT('Cierre del día ', p_fecha, ': Total recaudado = ', total_recaudado, 
               ', Sesiones = ', total_sesiones, ', Minutos = ', minutos_totales)
    );
    
    SELECT total_recaudado AS recaudacion, total_sesiones AS sesiones, minutos_totales AS minutos;
END;;

DELIMITER ;

-- Vistas
DROP VIEW IF EXISTS `vista_sesiones_activas`;
CREATE VIEW `vista_sesiones_activas` AS
SELECT 
    s.id AS id_sesion,
    c.numero_puesto AS pc_numero,
    CONCAT(cl.nombre, ' ', cl.apellido) AS cliente_nombre,
    cl.cedula_o_codigo AS cliente_documento,
    tc.nombre_rol AS tipo_cliente,
    s.hora_inicio,
    TIMESTAMPDIFF(MINUTE, s.hora_inicio, NOW()) AS minutos_transcurridos,
    s.monto_tarifa_aplicada AS tarifa_por_hora,
    ROUND((TIMESTAMPDIFF(MINUTE, s.hora_inicio, NOW()) / 60) * s.monto_tarifa_aplicada, 2) AS monto_estimado,
    u.nombre_completo AS operador
FROM sesiones s
JOIN computadoras c ON s.computadora_id = c.id
JOIN clientes cl ON s.cliente_id = cl.id
JOIN tipos_cliente tc ON cl.tipo_cliente_id = tc.id
JOIN usuarios u ON s.usuario_operador_id = u.id
WHERE s.hora_fin IS NULL AND s.estado_transaccion = 'en_curso';

DROP VIEW IF EXISTS `vista_recaudacion_diaria`;
CREATE VIEW `vista_recaudacion_diaria` AS
SELECT 
    DATE(s.hora_inicio) AS fecha,
    COUNT(s.id) AS total_sesiones_finalizadas,
    SUM(s.monto_total_pagado) AS total_recaudado,
    AVG(s.monto_total_pagado) AS promedio_por_sesion,
    SUM(TIMESTAMPDIFF(MINUTE, s.hora_inicio, s.hora_fin)) AS total_minutos_servidos,
    COUNT(DISTINCT s.cliente_id) AS clientes_atendidos
FROM sesiones s
WHERE s.hora_fin IS NOT NULL 
  AND s.estado_transaccion = 'finalizado'
GROUP BY DATE(s.hora_inicio)
ORDER BY fecha DESC;

DROP VIEW IF EXISTS `vista_inventario_computadoras`;
CREATE VIEW `vista_inventario_computadoras` AS
SELECT 
    c.id AS compu_id,
    c.numero_puesto,
    c.codigo_bien_nacional AS bien_nacional_pc,
    m.nombremarca AS pc_marca,
    c.modelo AS pc_modelo,
    c.color AS pc_color,
    c.estado_operativo,
    c.direccion_ip,
    (SELECT COUNT(*) FROM perifericos p WHERE p.computadora_id = c.id) AS perifericos_asignados,
    GROUP_CONCAT(DISTINCT CONCAT(tp.nombre_componente, ' (', p.marca, ' ', p.modelo, ')') SEPARATOR '; ') AS detalle_perifericos
FROM computadoras c
LEFT JOIN marca m ON c.marca = m.id_marca
LEFT JOIN perifericos p ON c.id = p.computadora_id
LEFT JOIN tipos_periferico tp ON p.tipo_periferico_id = tp.id
GROUP BY c.id;

DROP VIEW IF EXISTS `vista_clientes_top`;
CREATE VIEW `vista_clientes_top` AS
SELECT 
    cl.id,
    CONCAT(cl.nombre, ' ', cl.apellido) AS nombre_completo,
    cl.cedula_o_codigo,
    tc.nombre_rol AS tipo,
    COUNT(s.id) AS total_sesiones,
    SUM(s.monto_total_pagado) AS total_gastado,
    MAX(s.hora_inicio) AS ultima_visita
FROM clientes cl
JOIN tipos_cliente tc ON cl.tipo_cliente_id = tc.id
LEFT JOIN sesiones s ON cl.id = s.cliente_id AND s.estado_transaccion = 'finalizado'
GROUP BY cl.id
ORDER BY total_gastado DESC;

-- Triggers (con desactivación temporal de comprobaciones)
DELIMITER ;;

DROP TRIGGER IF EXISTS `tr_prevent_delete_clientes`;
CREATE TRIGGER `tr_prevent_delete_clientes` BEFORE DELETE ON `clientes` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se permite eliminar clientes. Use estado_cuenta = suspendido.';
END;;

DROP TRIGGER IF EXISTS `tr_audit_computadoras`;
CREATE TRIGGER `tr_audit_computadoras` AFTER UPDATE ON `computadoras` FOR EACH ROW
BEGIN
    DECLARE v_usuario_id INT DEFAULT COALESCE(@usuario_actual, 1);
    IF OLD.estado_operativo != NEW.estado_operativo THEN
        INSERT INTO log_cambios_bienes (tabla_afectada, id_registro, campo_modificado, valor_anterior, valor_nuevo, usuario_id, usuario_ejecutor, accion)
        VALUES ('computadoras', NEW.id, 'estado_operativo', OLD.estado_operativo, NEW.estado_operativo, v_usuario_id, CURRENT_USER(), 'UPDATE');
    END IF;
END;;

DROP TRIGGER IF EXISTS `tr_prevent_delete_computadoras`;
CREATE TRIGGER `tr_prevent_delete_computadoras` BEFORE DELETE ON `computadoras` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se permite eliminar computadoras. Use estado_operativo = desincorporado.';
END;;

DROP TRIGGER IF EXISTS `tr_periferico_unico`;
CREATE TRIGGER `tr_periferico_unico` BEFORE INSERT ON `perifericos` FOR EACH ROW
BEGIN
    DECLARE contador INT;
    IF NEW.computadora_id IS NOT NULL THEN
        SELECT COUNT(*) INTO contador FROM perifericos 
        WHERE computadora_id = NEW.computadora_id AND tipo_periferico_id = NEW.tipo_periferico_id;
        IF contador > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ya existe un periférico del mismo tipo asignado a esta computadora';
        END IF;
    END IF;
END;;

DROP TRIGGER IF EXISTS `tr_audit_perifericos`;
CREATE TRIGGER `tr_audit_perifericos` AFTER UPDATE ON `perifericos` FOR EACH ROW
BEGIN
    DECLARE v_usuario_id INT DEFAULT COALESCE(@usuario_actual, 1);
    IF OLD.computadora_id != NEW.computadora_id THEN
        INSERT INTO log_cambios_bienes (tabla_afectada, id_registro, campo_modificado, valor_anterior, valor_nuevo, usuario_id, usuario_ejecutor, accion)
        VALUES ('perifericos', NEW.id, 'computadora_id', OLD.computadora_id, NEW.computadora_id, v_usuario_id, CURRENT_USER(), 'UPDATE');
    END IF;
END;;

DROP TRIGGER IF EXISTS `tr_validar_computadora_disponible`;
CREATE TRIGGER `tr_validar_computadora_disponible` BEFORE INSERT ON `sesiones` FOR EACH ROW
BEGIN
    DECLARE estado_pc VARCHAR(20);
    SELECT estado_operativo INTO estado_pc FROM computadoras WHERE id = NEW.computadora_id;
    IF estado_pc IN ('mantenimiento', 'desincorporado') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede iniciar sesión: computadora en mantenimiento o desincorporada';
    END IF;
    IF estado_pc = 'ocupado' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede iniciar sesión: computadora ya ocupada';
    END IF;
END;;

DROP TRIGGER IF EXISTS `tr_validar_cliente_activo`;
CREATE TRIGGER `tr_validar_cliente_activo` BEFORE INSERT ON `sesiones` FOR EACH ROW
BEGIN
    DECLARE estado_cliente VARCHAR(20);
    SELECT estado_cuenta INTO estado_cliente FROM clientes WHERE id = NEW.cliente_id;
    IF estado_cliente = 'suspendido' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cliente suspendido, no puede usar el servicio';
    END IF;
END;;

DROP TRIGGER IF EXISTS `tr_validar_tiempo_cliente`;
CREATE TRIGGER `tr_validar_tiempo_cliente` BEFORE INSERT ON `sesiones` FOR EACH ROW
BEGIN
    DECLARE ultima_fin DATETIME;
    SELECT MAX(hora_fin) INTO ultima_fin FROM sesiones 
    WHERE cliente_id = NEW.cliente_id AND estado_transaccion = 'finalizado'
      AND hora_fin > NOW() - INTERVAL 1 HOUR;
    IF ultima_fin IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cliente ya tuvo una sesión en la última hora, espere antes de iniciar otra.';
    END IF;
END;;

DROP TRIGGER IF EXISTS `tr_computadora_ocupar`;
CREATE TRIGGER `tr_computadora_ocupar` AFTER INSERT ON `sesiones` FOR EACH ROW
BEGIN
    UPDATE computadoras SET estado_operativo = 'ocupado' WHERE id = NEW.computadora_id;
END;;

DROP TRIGGER IF EXISTS `tr_computadora_liberar`;
CREATE TRIGGER `tr_computadora_liberar` BEFORE UPDATE ON `sesiones` FOR EACH ROW
BEGIN
    IF NEW.hora_fin IS NOT NULL AND OLD.hora_fin IS NULL THEN
        UPDATE computadoras
        SET estado_operativo = 'disponible'
        WHERE id = NEW.computadora_id AND estado_operativo = 'ocupado';
    END IF;
END;;

DROP TRIGGER IF EXISTS `tr_calcular_monto_sesion`;
CREATE TRIGGER `tr_calcular_monto_sesion` BEFORE UPDATE ON `sesiones` FOR EACH ROW
BEGIN
    IF NEW.hora_fin IS NOT NULL AND OLD.hora_fin IS NULL THEN
        SET NEW.monto_total_pagado = calcular_costo_sesion(NEW.monto_tarifa_aplicada, NEW.minutos_consumidos);
    END IF;
END;;

DROP TRIGGER IF EXISTS `tr_generar_comprobante`;
CREATE TRIGGER `tr_generar_comprobante` BEFORE UPDATE ON `sesiones` FOR EACH ROW
BEGIN
    DECLARE correlativo INT;
    IF NEW.hora_fin IS NOT NULL AND OLD.hora_fin IS NULL AND NEW.comprobante_factura IS NULL THEN
        SELECT IFNULL(MAX(CAST(SUBSTRING_INDEX(comprobante_factura, '-', -1) AS UNSIGNED)), 0) + 1
        INTO correlativo
        FROM sesiones
        WHERE DATE(hora_inicio) = CURDATE() AND comprobante_factura IS NOT NULL;
        SET NEW.comprobante_factura = CONCAT('FAC-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-', LPAD(correlativo, 5, '0'));
    END IF;
END;;

DROP TRIGGER IF EXISTS `tr_proteger_super_admin`;
CREATE TRIGGER `tr_proteger_super_admin` BEFORE UPDATE ON `usuarios` FOR EACH ROW
BEGIN
    DECLARE total_activos INT;
    IF OLD.rol = 'super_admin' AND NEW.activo = 0 THEN
        SELECT COUNT(*) INTO total_activos FROM usuarios WHERE rol = 'super_admin' AND activo = 1;
        IF total_activos <= 1 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede desactivar el único super_admin activo';
        END IF;
    END IF;
END;;

DROP TRIGGER IF EXISTS `tr_historial_login_app`;
CREATE TRIGGER `tr_historial_login_app` AFTER UPDATE ON `usuarios` FOR EACH ROW
BEGIN
    IF NEW.ultimo_login IS NOT NULL AND OLD.ultimo_login IS NULL THEN
        INSERT INTO historial (usuario, ip, fyh, sector, acciones)
        VALUES (NEW.correo_institucional, 'app_trigger', NOW(), 'autenticacion', 'Inicio de sesión registrado por trigger');
    END IF;
END;;

DROP TRIGGER IF EXISTS `tr_prevent_delete_usuarios`;
CREATE TRIGGER `tr_prevent_delete_usuarios` BEFORE DELETE ON `usuarios` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se permite eliminar usuarios. Use activo = 0.';
END;;

DELIMITER ;

-- Eventos
DROP EVENT IF EXISTS `evento_cierre_automatico`;
DELIMITER ;;
CREATE EVENT `evento_cierre_automatico`
ON SCHEDULE EVERY 1 DAY STARTS CONCAT(CURDATE(), ' 23:59:59')
DO
BEGIN
    CALL sp_cerrar_sesiones_vencidas();
    INSERT INTO historial (usuario, ip, fyh, sector, acciones)
    VALUES ('sistema', '127.0.0.1', NOW(), 'evento_programado', 'Cierre automático de sesiones vencidas');
END;;

DROP EVENT IF EXISTS `evento_limpiar_historial`;
CREATE EVENT `evento_limpiar_historial`
ON SCHEDULE EVERY 1 WEEK STARTS CURRENT_TIMESTAMP + INTERVAL 7 DAY
DO
BEGIN
    DELETE FROM historial WHERE fyh < DATE_SUB(NOW(), INTERVAL 3 MONTH);
    DELETE FROM log_cambios_bienes WHERE fecha_cambio < DATE_SUB(NOW(), INTERVAL 6 MONTH);
END;;
DELIMITER ;

-- Reactivar comprobaciones de claves foráneas
SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- FIN DEL SCRIPT
-- =====================================================================
