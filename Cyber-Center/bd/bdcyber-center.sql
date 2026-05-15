/*
 Navicat Premium Dump SQL

 Source Server         : PHP8
 Source Server Type    : MySQL
 Source Server Version : 120202 (12.2.2-MariaDB)
 Source Host           : localhost:3306
 Source Schema         : bdcyber-center

 Target Server Type    : MySQL
 Target Server Version : 120202 (12.2.2-MariaDB)
 File Encoding         : 65001

 Date: 15/05/2026 14:04:20
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for auditorias_bienes
-- ----------------------------
DROP TABLE IF EXISTS `auditorias_bienes`;
CREATE TABLE `auditorias_bienes`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `tabla_auditada` enum('computadoras','perifericos') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo_id` int NOT NULL,
  `codigo_bien_nacional_verificado` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado_constatado` enum('operativo','no_localizado','dañado_reparable','propuesto_descargo') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `observaciones_legales` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha_auditoria` timestamp NULL DEFAULT current_timestamp(),
  `usuario_auditor_id` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `usuario_auditor_id`(`usuario_auditor_id` ASC) USING BTREE,
  INDEX `idx_fecha_auditoria`(`fecha_auditoria` ASC) USING BTREE,
  CONSTRAINT `1` FOREIGN KEY (`usuario_auditor_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of auditorias_bienes
-- ----------------------------

-- ----------------------------
-- Table structure for clientes
-- ----------------------------
DROP TABLE IF EXISTS `clientes`;
CREATE TABLE `clientes`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `tipo_cliente_id` int NOT NULL,
  `cedula_o_codigo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apellido` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `correo` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `estado_cuenta` enum('activo','suspendido') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'activo',
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `cedula_o_codigo`(`cedula_o_codigo` ASC) USING BTREE,
  INDEX `idx_tipo_cliente`(`tipo_cliente_id` ASC) USING BTREE,
  INDEX `idx_estado_cuenta`(`estado_cuenta` ASC) USING BTREE,
  CONSTRAINT `1` FOREIGN KEY (`tipo_cliente_id`) REFERENCES `tipos_cliente` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chk_cedula_valida` CHECK (char_length(`cedula_o_codigo`) >= 5)
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of clientes
-- ----------------------------
INSERT INTO `clientes` VALUES (1, 2, 'V-12345678', 'Juan', 'Pérez', 'juan@estudiante.edu', 'activo', '2026-05-15 14:03:47');

-- ----------------------------
-- Table structure for computadoras
-- ----------------------------
DROP TABLE IF EXISTS `computadoras`;
CREATE TABLE `computadoras`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_puesto` int NOT NULL,
  `codigo_bien_nacional` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_serial_chasis` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `marca` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `direccion_ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `ubicacion_administrativa` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'Sala de Ciber Center',
  `estado_operativo` enum('disponible','ocupado','mantenimiento','desincorporado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'disponible',
  `fecha_incorporacion` date NOT NULL,
  `actualizado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `numero_puesto`(`numero_puesto` ASC) USING BTREE,
  UNIQUE INDEX `codigo_bien_nacional`(`codigo_bien_nacional` ASC) USING BTREE,
  UNIQUE INDEX `numero_serial_chasis`(`numero_serial_chasis` ASC) USING BTREE,
  UNIQUE INDEX `direccion_ip`(`direccion_ip` ASC) USING BTREE,
  INDEX `idx_estado`(`estado_operativo` ASC) USING BTREE,
  INDEX `idx_ip`(`direccion_ip` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of computadoras
-- ----------------------------
INSERT INTO `computadoras` VALUES (1, 1, 'BIEN-PC-001', 'SN-CHASIS-001', 'Dell', 'Optiplex 3080', 'Negro', '192.168.1.101', 'Sala de Ciber Center', 'disponible', '2026-05-15', '2026-05-15 14:03:47');

-- ----------------------------
-- Table structure for configuracion
-- ----------------------------
DROP TABLE IF EXISTS `configuracion`;
CREATE TABLE `configuracion`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `clave` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `actualizado_en` timestamp NULL DEFAULT current_timestamp() ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `clave`(`clave` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of configuracion
-- ----------------------------
INSERT INTO `configuracion` VALUES (1, 'impuesto_porcentaje', '16', 'IVA o impuesto aplicado al total', '2026-05-15 14:03:47');
INSERT INTO `configuracion` VALUES (2, 'duracion_maxima_sesion_horas', '4', 'Máximo de horas por sesión permitida', '2026-05-15 14:03:47');
INSERT INTO `configuracion` VALUES (3, 'redondear_minutos', '5', 'Redondear los minutos consumidos al múltiplo de X', '2026-05-15 14:03:47');
INSERT INTO `configuracion` VALUES (4, 'hora_cierre_automatico', '23:59:59', 'Hora límite para forzar cierre de sesiones', '2026-05-15 14:03:47');
INSERT INTO `configuracion` VALUES (5, 'notificar_mantenimiento', '1', 'Si está activo, envía alertas de mantenimiento (1/0)', '2026-05-15 14:03:47');

-- ----------------------------
-- Table structure for historial
-- ----------------------------
DROP TABLE IF EXISTS `historial`;
CREATE TABLE `historial`  (
  `idhistorial` int NOT NULL AUTO_INCREMENT,
  `usuario` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `fyh` datetime NOT NULL,
  `sector` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `acciones` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`idhistorial`) USING BTREE,
  INDEX `idx_usuario`(`usuario` ASC) USING BTREE,
  INDEX `idx_fyh`(`fyh` ASC) USING BTREE,
  INDEX `idx_sector`(`sector` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of historial
-- ----------------------------

-- ----------------------------
-- Table structure for log_cambios_bienes
-- ----------------------------
DROP TABLE IF EXISTS `log_cambios_bienes`;
CREATE TABLE `log_cambios_bienes`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `tabla_afectada` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `id_registro` int NULL DEFAULT NULL,
  `campo_modificado` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `valor_anterior` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `valor_nuevo` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `usuario_id` int NOT NULL,
  `usuario_ejecutor` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `fecha_cambio` timestamp NULL DEFAULT current_timestamp(),
  `accion` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_tabla_registro`(`tabla_afectada` ASC, `id_registro` ASC) USING BTREE,
  INDEX `idx_usuario`(`usuario_id` ASC) USING BTREE,
  CONSTRAINT `1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of log_cambios_bienes
-- ----------------------------

-- ----------------------------
-- Table structure for perifericos
-- ----------------------------
DROP TABLE IF EXISTS `perifericos`;
CREATE TABLE `perifericos`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `computadora_id` int NULL DEFAULT NULL,
  `tipo_periferico_id` int NOT NULL,
  `codigo_bien_nacional` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero_serial_fabrica` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `marca` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `modelo` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado_fisico` enum('excelente','bueno','regular','dañado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'excelente',
  `fecha_registro` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `codigo_bien_nacional`(`codigo_bien_nacional` ASC) USING BTREE,
  UNIQUE INDEX `numero_serial_fabrica`(`numero_serial_fabrica` ASC) USING BTREE,
  INDEX `idx_computadora`(`computadora_id` ASC) USING BTREE,
  INDEX `idx_tipo`(`tipo_periferico_id` ASC) USING BTREE,
  CONSTRAINT `1` FOREIGN KEY (`computadora_id`) REFERENCES `computadoras` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `2` FOREIGN KEY (`tipo_periferico_id`) REFERENCES `tipos_periferico` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of perifericos
-- ----------------------------

-- ----------------------------
-- Table structure for sesiones
-- ----------------------------
DROP TABLE IF EXISTS `sesiones`;
CREATE TABLE `sesiones`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `computadora_id` int NOT NULL,
  `cliente_id` int NOT NULL,
  `usuario_operador_id` int NOT NULL,
  `hora_inicio` timestamp NULL DEFAULT current_timestamp(),
  `hora_fin` timestamp NULL DEFAULT NULL,
  `minutos_consumidos` int GENERATED ALWAYS AS (timestampdiff(MINUTE,`hora_inicio`,`hora_fin`)) STORED NULL,
  `monto_tarifa_aplicada` decimal(10, 2) NOT NULL,
  `monto_total_pagado` decimal(10, 2) NULL DEFAULT 0.00,
  `comprobante_factura` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `estado_transaccion` enum('en_curso','finalizado','anulado') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'en_curso',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `comprobante_factura`(`comprobante_factura` ASC) USING BTREE,
  INDEX `usuario_operador_id`(`usuario_operador_id` ASC) USING BTREE,
  INDEX `idx_computadora_estado`(`computadora_id` ASC, `estado_transaccion` ASC) USING BTREE,
  INDEX `idx_cliente`(`cliente_id` ASC) USING BTREE,
  INDEX `idx_fechas`(`hora_inicio` ASC, `hora_fin` ASC) USING BTREE,
  CONSTRAINT `1` FOREIGN KEY (`computadora_id`) REFERENCES `computadoras` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `3` FOREIGN KEY (`usuario_operador_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT `chk_monto_tarifa_positivo` CHECK (`monto_tarifa_aplicada` >= 0),
  CONSTRAINT `chk_monto_pagado_no_negativo` CHECK (`monto_total_pagado` >= 0),
  CONSTRAINT `chk_minutos_no_negativos` CHECK (`minutos_consumidos` >= 0)
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of sesiones
-- ----------------------------

-- ----------------------------
-- Table structure for tipos_cliente
-- ----------------------------
DROP TABLE IF EXISTS `tipos_cliente`;
CREATE TABLE `tipos_cliente`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_rol` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tarifa_por_hora` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `exento_pago` tinyint(1) NULL DEFAULT 0,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `nombre_rol`(`nombre_rol` ASC) USING BTREE,
  CONSTRAINT `chk_tarifa_no_negativa` CHECK (`tarifa_por_hora` >= 0)
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of tipos_cliente
-- ----------------------------
INSERT INTO `tipos_cliente` VALUES (1, 'Invitado', 2.50, 0);
INSERT INTO `tipos_cliente` VALUES (2, 'Estudiante', 1.50, 0);
INSERT INTO `tipos_cliente` VALUES (3, 'Profesor', 0.00, 1);
INSERT INTO `tipos_cliente` VALUES (4, 'Administrativo', 0.00, 1);

-- ----------------------------
-- Table structure for tipos_periferico
-- ----------------------------
DROP TABLE IF EXISTS `tipos_periferico`;
CREATE TABLE `tipos_periferico`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_componente` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `nombre_componente`(`nombre_componente` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of tipos_periferico
-- ----------------------------
INSERT INTO `tipos_periferico` VALUES (4, 'Auriculares / Audífonos');
INSERT INTO `tipos_periferico` VALUES (5, 'Cámara Web');
INSERT INTO `tipos_periferico` VALUES (1, 'Monitor');
INSERT INTO `tipos_periferico` VALUES (3, 'Mouse');
INSERT INTO `tipos_periferico` VALUES (6, 'Regulador de Voltaje / UPS');
INSERT INTO `tipos_periferico` VALUES (2, 'Teclado');

-- ----------------------------
-- Table structure for usuarios
-- ----------------------------
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios`  (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_completo` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cedula_identidad` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `correo_institucional` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('super_admin','auditor','operador') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NULL DEFAULT 1,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `ultimo_login` datetime NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `cedula_identidad`(`cedula_identidad` ASC) USING BTREE,
  UNIQUE INDEX `correo_institucional`(`correo_institucional` ASC) USING BTREE,
  INDEX `idx_rol_activo`(`rol` ASC, `activo` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Records of usuarios
-- ----------------------------
INSERT INTO `usuarios` VALUES (1, 'Super Administrador', '00000000', 'admin@ciber.edu', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'super_admin', 1, '2026-05-15 14:03:47', NULL);

-- ----------------------------
-- View structure for vista_clientes_top
-- ----------------------------
DROP VIEW IF EXISTS `vista_clientes_top`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `vista_clientes_top` AS select `cl`.`id` AS `id`,concat(`cl`.`nombre`,' ',`cl`.`apellido`) AS `nombre_completo`,`cl`.`cedula_o_codigo` AS `cedula_o_codigo`,`tc`.`nombre_rol` AS `tipo`,count(`s`.`id`) AS `total_sesiones`,sum(`s`.`monto_total_pagado`) AS `total_gastado`,max(`s`.`hora_inicio`) AS `ultima_visita` from ((`clientes` `cl` join `tipos_cliente` `tc` on(`cl`.`tipo_cliente_id` = `tc`.`id`)) left join `sesiones` `s` on(`cl`.`id` = `s`.`cliente_id` and `s`.`estado_transaccion` = 'finalizado')) group by `cl`.`id` order by sum(`s`.`monto_total_pagado`) desc;

-- ----------------------------
-- View structure for vista_inventario_computadoras
-- ----------------------------
DROP VIEW IF EXISTS `vista_inventario_computadoras`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `vista_inventario_computadoras` AS select `c`.`id` AS `compu_id`,`c`.`numero_puesto` AS `numero_puesto`,`c`.`codigo_bien_nacional` AS `bien_nacional_pc`,`c`.`marca` AS `pc_marca`,`c`.`modelo` AS `pc_modelo`,`c`.`color` AS `pc_color`,`c`.`estado_operativo` AS `estado_operativo`,`c`.`direccion_ip` AS `direccion_ip`,(select count(0) from `perifericos` `p` where `p`.`computadora_id` = `c`.`id`) AS `perifericos_asignados`,group_concat(distinct concat(`tp`.`nombre_componente`,' (',`p`.`marca`,' ',`p`.`modelo`,')') separator '; ') AS `detalle_perifericos` from ((`computadoras` `c` left join `perifericos` `p` on(`c`.`id` = `p`.`computadora_id`)) left join `tipos_periferico` `tp` on(`p`.`tipo_periferico_id` = `tp`.`id`)) group by `c`.`id`;

-- ----------------------------
-- View structure for vista_recaudacion_diaria
-- ----------------------------
DROP VIEW IF EXISTS `vista_recaudacion_diaria`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `vista_recaudacion_diaria` AS select cast(`s`.`hora_inicio` as date) AS `fecha`,count(`s`.`id`) AS `total_sesiones_finalizadas`,sum(`s`.`monto_total_pagado`) AS `total_recaudado`,avg(`s`.`monto_total_pagado`) AS `promedio_por_sesion`,sum(timestampdiff(MINUTE,`s`.`hora_inicio`,`s`.`hora_fin`)) AS `total_minutos_servidos`,count(distinct `s`.`cliente_id`) AS `clientes_atendidos` from `sesiones` `s` where `s`.`hora_fin` is not null and `s`.`estado_transaccion` = 'finalizado' group by cast(`s`.`hora_inicio` as date) order by cast(`s`.`hora_inicio` as date) desc;

-- ----------------------------
-- View structure for vista_sesiones_activas
-- ----------------------------
DROP VIEW IF EXISTS `vista_sesiones_activas`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `vista_sesiones_activas` AS select `s`.`id` AS `id_sesion`,`c`.`numero_puesto` AS `pc_numero`,concat(`cl`.`nombre`,' ',`cl`.`apellido`) AS `cliente_nombre`,`cl`.`cedula_o_codigo` AS `cliente_documento`,`tc`.`nombre_rol` AS `tipo_cliente`,`s`.`hora_inicio` AS `hora_inicio`,timestampdiff(MINUTE,`s`.`hora_inicio`,current_timestamp()) AS `minutos_transcurridos`,`s`.`monto_tarifa_aplicada` AS `tarifa_por_hora`,round(timestampdiff(MINUTE,`s`.`hora_inicio`,current_timestamp()) / 60 * `s`.`monto_tarifa_aplicada`,2) AS `monto_estimado`,`u`.`nombre_completo` AS `operador` from ((((`sesiones` `s` join `computadoras` `c` on(`s`.`computadora_id` = `c`.`id`)) join `clientes` `cl` on(`s`.`cliente_id` = `cl`.`id`)) join `tipos_cliente` `tc` on(`cl`.`tipo_cliente_id` = `tc`.`id`)) join `usuarios` `u` on(`s`.`usuario_operador_id` = `u`.`id`)) where `s`.`hora_fin` is null and `s`.`estado_transaccion` = 'en_curso';

-- ----------------------------
-- Function structure for aplicar_impuesto
-- ----------------------------
DROP FUNCTION IF EXISTS `aplicar_impuesto`;
delimiter ;;
CREATE FUNCTION `aplicar_impuesto`(p_monto DECIMAL(10,2))
 RETURNS decimal(10,2)
  DETERMINISTIC
BEGIN
    DECLARE impuesto DECIMAL(5,2);
    SET impuesto = COALESCE((SELECT CAST(valor AS DECIMAL(5,2)) FROM configuracion WHERE clave = 'impuesto_porcentaje'), 16);
    RETURN ROUND(p_monto * (1 + impuesto/100), 2);
END
;;
delimiter ;

-- ----------------------------
-- Function structure for calcular_costo_sesion
-- ----------------------------
DROP FUNCTION IF EXISTS `calcular_costo_sesion`;
delimiter ;;
CREATE FUNCTION `calcular_costo_sesion`(p_tarifa_hora DECIMAL(10,2), p_minutos INT)
 RETURNS decimal(10,2)
  DETERMINISTIC
BEGIN
    DECLARE redondear INT;
    DECLARE minutos_redondeados INT;
    SET redondear = COALESCE((SELECT CAST(valor AS UNSIGNED) FROM configuracion WHERE clave = 'redondear_minutos'), 5);
    SET minutos_redondeados = CEIL(p_minutos / redondear) * redondear;
    RETURN ROUND((minutos_redondeados / 60) * p_tarifa_hora, 2);
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_cerrar_sesiones_vencidas
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_cerrar_sesiones_vencidas`;
delimiter ;;
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
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_cierre_caja
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_cierre_caja`;
delimiter ;;
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
END
;;
delimiter ;

-- ----------------------------
-- Event structure for evento_cierre_automatico
-- ----------------------------
DROP EVENT IF EXISTS `evento_cierre_automatico`;
delimiter ;;
CREATE EVENT `evento_cierre_automatico`
ON SCHEDULE
EVERY '1' DAY STARTS '2026-05-15 23:59:59'
DO BEGIN
    CALL sp_cerrar_sesiones_vencidas();
    INSERT INTO historial (usuario, ip, fyh, sector, acciones)
    VALUES ('sistema', '127.0.0.1', NOW(), 'evento_programado', 'Cierre automático de sesiones vencidas');
END
;;
delimiter ;

-- ----------------------------
-- Event structure for evento_limpiar_historial
-- ----------------------------
DROP EVENT IF EXISTS `evento_limpiar_historial`;
delimiter ;;
CREATE EVENT `evento_limpiar_historial`
ON SCHEDULE
EVERY '1' WEEK STARTS '2026-05-22 14:03:47'
DO BEGIN
    DELETE FROM historial WHERE fyh < DATE_SUB(NOW(), INTERVAL 3 MONTH);
    DELETE FROM log_cambios_bienes WHERE fecha_cambio < DATE_SUB(NOW(), INTERVAL 6 MONTH);
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table clientes
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_prevent_delete_clientes`;
delimiter ;;
CREATE TRIGGER `tr_prevent_delete_clientes` BEFORE DELETE ON `clientes` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se permite eliminar clientes. Use estado_cuenta = suspendido.';
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table computadoras
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_audit_computadoras`;
delimiter ;;
CREATE TRIGGER `tr_audit_computadoras` AFTER UPDATE ON `computadoras` FOR EACH ROW BEGIN
    DECLARE v_usuario_id INT DEFAULT COALESCE(@usuario_actual, 1);
    IF OLD.estado_operativo != NEW.estado_operativo THEN
        INSERT INTO log_cambios_bienes (tabla_afectada, id_registro, campo_modificado, valor_anterior, valor_nuevo, usuario_id, usuario_ejecutor, accion)
        VALUES ('computadoras', NEW.id, 'estado_operativo', OLD.estado_operativo, NEW.estado_operativo, v_usuario_id, CURRENT_USER(), 'UPDATE');
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table computadoras
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_prevent_delete_computadoras`;
delimiter ;;
CREATE TRIGGER `tr_prevent_delete_computadoras` BEFORE DELETE ON `computadoras` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se permite eliminar computadoras. Use estado_operativo = desincorporado.';
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table perifericos
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_periferico_unico`;
delimiter ;;
CREATE TRIGGER `tr_periferico_unico` BEFORE INSERT ON `perifericos` FOR EACH ROW BEGIN
    DECLARE contador INT;
    IF NEW.computadora_id IS NOT NULL THEN
        SELECT COUNT(*) INTO contador FROM perifericos 
        WHERE computadora_id = NEW.computadora_id AND tipo_periferico_id = NEW.tipo_periferico_id;
        IF contador > 0 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ya existe un periférico del mismo tipo asignado a esta computadora';
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table perifericos
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_audit_perifericos`;
delimiter ;;
CREATE TRIGGER `tr_audit_perifericos` AFTER UPDATE ON `perifericos` FOR EACH ROW BEGIN
    DECLARE v_usuario_id INT DEFAULT COALESCE(@usuario_actual, 1);
    IF OLD.computadora_id != NEW.computadora_id THEN
        INSERT INTO log_cambios_bienes (tabla_afectada, id_registro, campo_modificado, valor_anterior, valor_nuevo, usuario_id, usuario_ejecutor, accion)
        VALUES ('perifericos', NEW.id, 'computadora_id', OLD.computadora_id, NEW.computadora_id, v_usuario_id, CURRENT_USER(), 'UPDATE');
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table sesiones
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_validar_computadora_disponible`;
delimiter ;;
CREATE TRIGGER `tr_validar_computadora_disponible` BEFORE INSERT ON `sesiones` FOR EACH ROW BEGIN
    DECLARE estado_pc VARCHAR(20);
    SELECT estado_operativo INTO estado_pc FROM computadoras WHERE id = NEW.computadora_id;
    IF estado_pc IN ('mantenimiento', 'desincorporado') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede iniciar sesión: computadora en mantenimiento o desincorporada';
    END IF;
    IF estado_pc = 'ocupado' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede iniciar sesión: computadora ya ocupada';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table sesiones
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_validar_cliente_activo`;
delimiter ;;
CREATE TRIGGER `tr_validar_cliente_activo` BEFORE INSERT ON `sesiones` FOR EACH ROW BEGIN
    DECLARE estado_cliente VARCHAR(20);
    SELECT estado_cuenta INTO estado_cliente FROM clientes WHERE id = NEW.cliente_id;
    IF estado_cliente = 'suspendido' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cliente suspendido, no puede usar el servicio';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table sesiones
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_validar_tiempo_cliente`;
delimiter ;;
CREATE TRIGGER `tr_validar_tiempo_cliente` BEFORE INSERT ON `sesiones` FOR EACH ROW BEGIN
    DECLARE ultima_fin DATETIME;
    SELECT MAX(hora_fin) INTO ultima_fin FROM sesiones 
    WHERE cliente_id = NEW.cliente_id AND estado_transaccion = 'finalizado'
      AND hora_fin > NOW() - INTERVAL 1 HOUR;
    IF ultima_fin IS NOT NULL THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cliente ya tuvo una sesión en la última hora, espere antes de iniciar otra.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table sesiones
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_computadora_ocupar`;
delimiter ;;
CREATE TRIGGER `tr_computadora_ocupar` AFTER INSERT ON `sesiones` FOR EACH ROW BEGIN
    UPDATE computadoras SET estado_operativo = 'ocupado' WHERE id = NEW.computadora_id;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table sesiones
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_computadora_liberar`;
delimiter ;;
CREATE TRIGGER `tr_computadora_liberar` BEFORE UPDATE ON `sesiones` FOR EACH ROW BEGIN
    IF NEW.hora_fin IS NOT NULL AND OLD.hora_fin IS NULL THEN
        UPDATE computadoras
        SET estado_operativo = 'disponible'
        WHERE id = NEW.computadora_id AND estado_operativo = 'ocupado';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table sesiones
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_calcular_monto_sesion`;
delimiter ;;
CREATE TRIGGER `tr_calcular_monto_sesion` BEFORE UPDATE ON `sesiones` FOR EACH ROW BEGIN
    IF NEW.hora_fin IS NOT NULL AND OLD.hora_fin IS NULL THEN
        SET NEW.monto_total_pagado = calcular_costo_sesion(NEW.monto_tarifa_aplicada, NEW.minutos_consumidos);
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table sesiones
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_generar_comprobante`;
delimiter ;;
CREATE TRIGGER `tr_generar_comprobante` BEFORE UPDATE ON `sesiones` FOR EACH ROW BEGIN
    DECLARE correlativo INT;
    IF NEW.hora_fin IS NOT NULL AND OLD.hora_fin IS NULL AND NEW.comprobante_factura IS NULL THEN
        SELECT IFNULL(MAX(CAST(SUBSTRING_INDEX(comprobante_factura, '-', -1) AS UNSIGNED)), 0) + 1
        INTO correlativo
        FROM sesiones
        WHERE DATE(hora_inicio) = CURDATE() AND comprobante_factura IS NOT NULL;
        SET NEW.comprobante_factura = CONCAT('FAC-', DATE_FORMAT(CURDATE(), '%Y%m%d'), '-', LPAD(correlativo, 5, '0'));
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table usuarios
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_proteger_super_admin`;
delimiter ;;
CREATE TRIGGER `tr_proteger_super_admin` BEFORE UPDATE ON `usuarios` FOR EACH ROW BEGIN
    DECLARE total_activos INT;
    IF OLD.rol = 'super_admin' AND NEW.activo = 0 THEN
        SELECT COUNT(*) INTO total_activos FROM usuarios WHERE rol = 'super_admin' AND activo = 1;
        IF total_activos <= 1 THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se puede desactivar el único super_admin activo';
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table usuarios
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_historial_login_app`;
delimiter ;;
CREATE TRIGGER `tr_historial_login_app` AFTER UPDATE ON `usuarios` FOR EACH ROW BEGIN
    IF NEW.ultimo_login IS NOT NULL AND OLD.ultimo_login IS NULL THEN
        INSERT INTO historial (usuario, ip, fyh, sector, acciones)
        VALUES (NEW.correo_institucional, 'app_trigger', NOW(), 'autenticacion', 'Inicio de sesión registrado por trigger');
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table usuarios
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_prevent_delete_usuarios`;
delimiter ;;
CREATE TRIGGER `tr_prevent_delete_usuarios` BEFORE DELETE ON `usuarios` FOR EACH ROW BEGIN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se permite eliminar usuarios. Use activo = 0.';
END
;;
delimiter ;

SET FOREIGN_KEY_CHECKS = 1;
