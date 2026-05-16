/*
=====================================================================
 Script completo para bdcyber-center (corregido)
 Ejecutar en Navicat (MySQL/MariaDB)
=====================================================================
*/

-- Eliminar la base de datos si existe y crearla de nuevo
DROP DATABASE IF EXISTS `bdcyber-center`;
CREATE DATABASE `bdcyber-center`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE `bdcyber-center`;

-- Activar el planificador de eventos (requiere privilegios)
SET GLOBAL event_scheduler = ON;

-- =================================================================
-- 1. TABLAS PRINCIPALES
-- =================================================================

-- Tabla marca (referenciada por computadoras)
CREATE TABLE `marca` (
  `id_marca` INT NOT NULL AUTO_INCREMENT,
  `nombremarca` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id_marca`),
  UNIQUE KEY `nombremarca` (`nombremarca`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos iniciales de marcas
INSERT INTO `marca` (`id_marca`, `nombremarca`) VALUES
(5,'acer'),(11,'alienwre'),(4,'apple'),(7,'asus'),(3,'dell'),
(13,'generica'),(2,'hp'),(12,'lanix'),(1,'lenovo'),(10,'lg'),
(8,'samsung'),(9,'soni'),(6,'toshiva');

-- Tipos de periféricos
CREATE TABLE `tipos_periferico` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre_componente` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_componente` (`nombre_componente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tipos_periferico` (`nombre_componente`) VALUES
('Monitor'),('Teclado'),('Mouse'),('Auriculares / Audífonos'),
('Cámara Web'),('Regulador de Voltaje / UPS');

-- Tipos de cliente
CREATE TABLE `tipos_cliente` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre_rol` VARCHAR(50) NOT NULL,
  `tarifa_por_hora` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `exento_pago` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_rol` (`nombre_rol`),
  CONSTRAINT `chk_tarifa_no_negativa` CHECK (`tarifa_por_hora` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `tipos_cliente` (`nombre_rol`, `tarifa_por_hora`, `exento_pago`) VALUES
('Invitado', 2.50, 0),
('Estudiante', 1.50, 0),
('Profesor', 0.00, 1),
('Administrativo', 0.00, 1);

-- Usuarios del sistema (personal)
CREATE TABLE `usuarios` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre_completo` VARCHAR(150) NOT NULL,
  `cedula_identidad` VARCHAR(20) NOT NULL,
  `correo_institucional` VARCHAR(100) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `rol` ENUM('super_admin','auditor','operador') NOT NULL,
  `activo` TINYINT(1) DEFAULT 1,
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_login` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cedula_identidad` (`cedula_identidad`),
  UNIQUE KEY `correo_institucional` (`correo_institucional`),
  KEY `idx_rol_activo` (`rol`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Usuario administrador
INSERT INTO `usuarios` (`nombre_completo`, `cedula_identidad`, `correo_institucional`, `password_hash`, `rol`, `activo`) VALUES
('Administrador', '00000000', 'admin@ciber.edu', '$2y$10$afJ8NbVycIXYF.BgusRTwuPtELDb5FwgCp.njVkpbHLGdgiLasQui', 'super_admin', 1);

-- Computadoras
CREATE TABLE `computadoras` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `numero_puesto` INT NOT NULL,
  `codigo_bien_nacional` VARCHAR(100) NOT NULL,
  `numero_serial_chasis` VARCHAR(100) NOT NULL,
  `marca` INT NOT NULL,  -- FK a marca.id_marca
  `modelo` VARCHAR(50) NOT NULL,
  `color` VARCHAR(30) NOT NULL,
  `direccion_ip` VARCHAR(45) DEFAULT NULL,
  `ubicacion_administrativa` VARCHAR(100) DEFAULT 'Sala de Ciber Center',
  `estado_operativo` ENUM('disponible','ocupado','mantenimiento','desincorporado') DEFAULT 'disponible',
  `fecha_incorporacion` DATE NOT NULL,
  `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_puesto` (`numero_puesto`),
  UNIQUE KEY `codigo_bien_nacional` (`codigo_bien_nacional`),
  UNIQUE KEY `numero_serial_chasis` (`numero_serial_chasis`),
  UNIQUE KEY `direccion_ip` (`direccion_ip`),
  KEY `idx_estado` (`estado_operativo`),
  KEY `idx_ip` (`direccion_ip`),
  KEY `marca` (`marca`),
  CONSTRAINT `computadoras_ibfk_1` FOREIGN KEY (`marca`) REFERENCES `marca` (`id_marca`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `computadoras` (`numero_puesto`, `codigo_bien_nacional`, `numero_serial_chasis`, `marca`, `modelo`, `color`, `direccion_ip`, `fecha_incorporacion`) VALUES
(1, 'BIEN-PC-001', 'SN-CHASIS-001', 3, 'Optiplex 3080', 'Negro', '192.168.1.101', CURDATE());

-- Periféricos
CREATE TABLE `perifericos` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `computadora_id` INT DEFAULT NULL,
  `tipo_periferico_id` INT NOT NULL,
  `codigo_bien_nacional` VARCHAR(100) NOT NULL,
  `numero_serial_fabrica` VARCHAR(100) NOT NULL,
  `marca` VARCHAR(50) NOT NULL,
  `modelo` VARCHAR(50) NOT NULL,
  `color` VARCHAR(30) NOT NULL,
  `estado_fisico` ENUM('excelente','bueno','regular','dañado') DEFAULT 'excelente',
  `fecha_registro` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_bien_nacional` (`codigo_bien_nacional`),
  UNIQUE KEY `numero_serial_fabrica` (`numero_serial_fabrica`),
  KEY `idx_computadora` (`computadora_id`),
  KEY `idx_tipo` (`tipo_periferico_id`),
  CONSTRAINT `perifericos_ibfk_1` FOREIGN KEY (`computadora_id`) REFERENCES `computadoras` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `perifericos_ibfk_2` FOREIGN KEY (`tipo_periferico_id`) REFERENCES `tipos_periferico` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Clientes
CREATE TABLE `clientes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tipo_cliente_id` INT NOT NULL,
  `cedula_o_codigo` VARCHAR(50) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `apellido` VARCHAR(100) NOT NULL,
  `correo` VARCHAR(100) DEFAULT NULL,
  `estado_cuenta` ENUM('activo','suspendido') DEFAULT 'activo',
  `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `cedula_o_codigo` (`cedula_o_codigo`),
  KEY `idx_tipo_cliente` (`tipo_cliente_id`),
  KEY `idx_estado_cuenta` (`estado_cuenta`),
  CONSTRAINT `clientes_ibfk_1` FOREIGN KEY (`tipo_cliente_id`) REFERENCES `tipos_cliente` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `chk_cedula_valida` CHECK (CHAR_LENGTH(`cedula_o_codigo`) >= 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `clientes` (`tipo_cliente_id`, `cedula_o_codigo`, `nombre`, `apellido`, `correo`) VALUES
(2, 'V-12345678', 'Juan', 'Pérez', 'juan@estudiante.edu');

-- Sesiones
CREATE TABLE `sesiones` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `computadora_id` INT NOT NULL,
  `cliente_id` INT NOT NULL,
  `usuario_operador_id` INT NOT NULL,
  `hora_inicio` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `hora_fin` TIMESTAMP NULL DEFAULT NULL,
  `minutos_consumidos` INT GENERATED ALWAYS AS (TIMESTAMPDIFF(MINUTE, `hora_inicio`, `hora_fin`)) STORED,
  `monto_tarifa_aplicada` DECIMAL(10,2) NOT NULL,
  `monto_total_pagado` DECIMAL(10,2) DEFAULT 0.00,
  `comprobante_factura` VARCHAR(50) DEFAULT NULL,
  `estado_transaccion` ENUM('en_curso','finalizado','anulado') DEFAULT 'en_curso',
  PRIMARY KEY (`id`),
  UNIQUE KEY `comprobante_factura` (`comprobante_factura`),
  KEY `usuario_operador_id` (`usuario_operador_id`),
  KEY `idx_computadora_estado` (`computadora_id`, `estado_transaccion`),
  KEY `idx_cliente` (`cliente_id`),
  KEY `idx_fechas` (`hora_inicio`, `hora_fin`),
  CONSTRAINT `sesiones_ibfk_1` FOREIGN KEY (`computadora_id`) REFERENCES `computadoras` (`id`),
  CONSTRAINT `sesiones_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `sesiones_ibfk_3` FOREIGN KEY (`usuario_operador_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `chk_monto_tarifa_positivo` CHECK (`monto_tarifa_aplicada` >= 0),
  CONSTRAINT `chk_monto_pagado_no_negativo` CHECK (`monto_total_pagado` >= 0),
  CONSTRAINT `chk_minutos_no_negativos` CHECK (`minutos_consumidos` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Auditorías de bienes
CREATE TABLE `auditorias_bienes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tabla_auditada` ENUM('computadoras','perifericos') NOT NULL,
  `activo_id` INT NOT NULL,
  `codigo_bien_nacional_verificado` VARCHAR(100) NOT NULL,
  `estado_constatado` ENUM('operativo','no_localizado','dañado_reparable','propuesto_descargo') NOT NULL,
  `observaciones_legales` TEXT NOT NULL,
  `fecha_auditoria` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `usuario_auditor_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  KEY `usuario_auditor_id` (`usuario_auditor_id`),
  KEY `idx_fecha_auditoria` (`fecha_auditoria`),
  CONSTRAINT `auditorias_bienes_ibfk_1` FOREIGN KEY (`usuario_auditor_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- 2. TABLAS COMPLEMENTARIAS
-- =================================================================

-- Historial de eventos (usado por index.php)
CREATE TABLE `historial` (
  `idhistorial` INT NOT NULL AUTO_INCREMENT,
  `usuario` VARCHAR(100) NOT NULL,
  `ip` VARCHAR(45) NOT NULL,
  `fyh` DATETIME NOT NULL,
  `sector` VARCHAR(50) NOT NULL,
  `acciones` TEXT NOT NULL,
  PRIMARY KEY (`idhistorial`),
  KEY `idx_usuario` (`usuario`),
  KEY `idx_fyh` (`fyh`),
  KEY `idx_sector` (`sector`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configuración global
CREATE TABLE `configuracion` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `clave` VARCHAR(50) NOT NULL,
  `valor` TEXT NOT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `configuracion` (`clave`, `valor`, `descripcion`) VALUES
('impuesto_porcentaje', '16', 'IVA o impuesto aplicado al total'),
('duracion_maxima_sesion_horas', '4', 'Máximo de horas por sesión permitida'),
('redondear_minutos', '5', 'Redondear los minutos consumidos al múltiplo de X'),
('hora_cierre_automatico', '23:59:59', 'Hora límite para forzar cierre de sesiones'),
('notificar_mantenimiento', '1', 'Si está activo, envía alertas de mantenimiento (1/0)');

-- Log de cambios en bienes (con FK a usuarios)
CREATE TABLE `log_cambios_bienes` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tabla_afectada` VARCHAR(50) DEFAULT NULL,
  `id_registro` INT DEFAULT NULL,
  `campo_modificado` VARCHAR(50) DEFAULT NULL,
  `valor_anterior` TEXT DEFAULT NULL,
  `valor_nuevo` TEXT DEFAULT NULL,
  `usuario_id` INT NOT NULL,
  `usuario_ejecutor` VARCHAR(150) DEFAULT NULL,
  `fecha_cambio` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `accion` VARCHAR(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tabla_registro` (`tabla_afectada`, `id_registro`),
  KEY `idx_usuario` (`usuario_id`),
  CONSTRAINT `log_cambios_bienes_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =================================================================
-- 3. FUNCIONES
-- =================================================================
DELIMITER ;;

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

CREATE FUNCTION `aplicar_impuesto`(p_monto DECIMAL(10,2))
RETURNS DECIMAL(10,2)
DETERMINISTIC
BEGIN
    DECLARE impuesto DECIMAL(5,2);
    SET impuesto = COALESCE((SELECT CAST(valor AS DECIMAL(5,2)) FROM configuracion WHERE clave = 'impuesto_porcentaje'), 16);
    RETURN ROUND(p_monto * (1 + impuesto/100), 2);
END;;

DELIMITER ;

-- =================================================================
-- 4. PROCEDIMIENTOS ALMACENADOS
-- =================================================================
DELIMITER ;;

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

-- =================================================================
-- 5. VISTAS
-- =================================================================
CREATE OR REPLACE VIEW `vista_sesiones_activas` AS
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

CREATE OR REPLACE VIEW `vista_recaudacion_diaria` AS
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

CREATE OR REPLACE VIEW `vista_inventario_computadoras` AS
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

CREATE OR REPLACE VIEW `vista_clientes_top` AS
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

-- =================================================================
-- 6. TRIGGERS (disparadores)
-- =================================================================
DELIMITER ;;

-- Prevenir DELETE en clientes
CREATE TRIGGER `tr_prevent_delete_clientes` BEFORE DELETE ON `clientes` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se permite eliminar clientes. Use estado_cuenta = suspendido.';
END;;

-- Auditar cambios en computadoras
CREATE TRIGGER `tr_audit_computadoras` AFTER UPDATE ON `computadoras` FOR EACH ROW
BEGIN
    DECLARE v_usuario_id INT DEFAULT COALESCE(@usuario_actual, 1);
    IF OLD.estado_operativo != NEW.estado_operativo THEN
        INSERT INTO log_cambios_bienes (tabla_afectada, id_registro, campo_modificado, valor_anterior, valor_nuevo, usuario_id, usuario_ejecutor, accion)
        VALUES ('computadoras', NEW.id, 'estado_operativo', OLD.estado_operativo, NEW.estado_operativo, v_usuario_id, CURRENT_USER(), 'UPDATE');
    END IF;
END;;

-- Prevenir DELETE en computadoras
CREATE TRIGGER `tr_prevent_delete_computadoras` BEFORE DELETE ON `computadoras` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se permite eliminar computadoras. Use estado_operativo = desincorporado.';
END;;

-- Unicidad por tipo de periférico en una misma computadora
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

-- Auditar cambios en periféricos
CREATE TRIGGER `tr_audit_perifericos` AFTER UPDATE ON `perifericos` FOR EACH ROW
BEGIN
    DECLARE v_usuario_id INT DEFAULT COALESCE(@usuario_actual, 1);
    IF OLD.computadora_id != NEW.computadora_id THEN
        INSERT INTO log_cambios_bienes (tabla_afectada, id_registro, campo_modificado, valor_anterior, valor_nuevo, usuario_id, usuario_ejecutor, accion)
        VALUES ('perifericos', NEW.id, 'computadora_id', OLD.computadora_id, NEW.computadora_id, v_usuario_id, CURRENT_USER(), 'UPDATE');
    END IF;
END;;

-- Validar que la computadora esté disponible antes de insertar sesión
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

-- Validar que el cliente esté activo
CREATE TRIGGER `tr_validar_cliente_activo` BEFORE INSERT ON `sesiones` FOR EACH ROW
BEGIN
    DECLARE estado_cliente VARCHAR(20);
    SELECT estado_cuenta INTO estado_cliente FROM clientes WHERE id = NEW.cliente_id;
    IF estado_cliente = 'suspendido' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cliente suspendido, no puede usar el servicio';
    END IF;
END;;

-- Validar tiempo mínimo entre sesiones del mismo cliente
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

-- Marcar computadora como ocupada al iniciar sesión
CREATE TRIGGER `tr_computadora_ocupar` AFTER INSERT ON `sesiones` FOR EACH ROW
BEGIN
    UPDATE computadoras SET estado_operativo = 'ocupado' WHERE id = NEW.computadora_id;
END;;

-- Marcar computadora como disponible al finalizar sesión
CREATE TRIGGER `tr_computadora_liberar` BEFORE UPDATE ON `sesiones` FOR EACH ROW
BEGIN
    IF NEW.hora_fin IS NOT NULL AND OLD.hora_fin IS NULL THEN
        UPDATE computadoras
        SET estado_operativo = 'disponible'
        WHERE id = NEW.computadora_id AND estado_operativo = 'ocupado';
    END IF;
END;;

-- Calcular monto total al finalizar sesión
CREATE TRIGGER `tr_calcular_monto_sesion` BEFORE UPDATE ON `sesiones` FOR EACH ROW
BEGIN
    IF NEW.hora_fin IS NOT NULL AND OLD.hora_fin IS NULL THEN
        SET NEW.monto_total_pagado = calcular_costo_sesion(NEW.monto_tarifa_aplicada, NEW.minutos_consumidos);
    END IF;
END;;

-- Generar comprobante de factura automático
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

-- Proteger desactivación del último super_admin
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

-- Registrar en historial cuando se actualiza ultimo_login (desde la aplicación)
CREATE TRIGGER `tr_historial_login_app` AFTER UPDATE ON `usuarios` FOR EACH ROW
BEGIN
    IF NEW.ultimo_login IS NOT NULL AND OLD.ultimo_login IS NULL THEN
        INSERT INTO historial (usuario, ip, fyh, sector, acciones)
        VALUES (NEW.correo_institucional, 'app_trigger', NOW(), 'autenticacion', 'Inicio de sesión registrado por trigger');
    END IF;
END;;

-- Prevenir DELETE en usuarios
CREATE TRIGGER `tr_prevent_delete_usuarios` BEFORE DELETE ON `usuarios` FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se permite eliminar usuarios. Use activo = 0.';
END;;

DELIMITER ;

-- =================================================================
-- 7. EVENTOS PROGRAMADOS
-- =================================================================
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

-- =================================================================
-- FIN DEL SCRIPT
-- =================================================================
SET FOREIGN_KEY_CHECKS = 1;
