-- =====================================================================
-- PETKO / KION · BASE DE DATOS COMPLETA (UN SOLO ARCHIVO)
--
-- Reúne en un solo script:
--   * el esquema base (kion.sql original) y sus catálogos,
--   * la migración complementaria (google_id, imagen_url, cajas, cajeros,
--     turnos_caja, retiros_caja, ventas.id_turno, rol "Usuario"),
--   * una sucursal inicial (solo si todavía no existe ninguna).
--
-- Uso: phpMyAdmin -> Importar (o pestaña SQL) -> este archivo.
-- Es idempotente: se puede ejecutar varias veces sin borrar datos.
-- El proyecto usa SOLO la base `kion` (src/php/config/conexion_BD.php).
--
-- NO incluye usuarios ni contraseñas. Para crear el primer administrador
-- genera un hash con:  php -r "echo password_hash('TuClave', PASSWORD_DEFAULT);"
-- e insértalo en `usuarios` (ver README, paso 7).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `kion` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `kion`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- Tablas base
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
  `id_rol` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sucursales` (
  `id_sucursal` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `contacto` varchar(150) DEFAULT NULL,
  `estado` enum('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA',
  PRIMARY KEY (`id_sucursal`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `google_id` varchar(255) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `id_rol` int(10) UNSIGNED NOT NULL,
  `id_sucursal` int(10) UNSIGNED DEFAULT NULL,
  `remember_token` char(64) DEFAULT NULL,
  `remember_expires_at` datetime DEFAULT NULL,
  `recovery_token` char(64) DEFAULT NULL,
  `recovery_expires_at` datetime DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `correo` (`correo`),
  UNIQUE KEY `uq_usuarios_google_id` (`google_id`),
  KEY `id_rol` (`id_rol`),
  KEY `id_sucursal` (`id_sucursal`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE,
  CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categorias` (
  `id_categoria` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_categoria`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `productos` (
  `id_producto` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_categoria` int(10) UNSIGNED NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `imagen_url` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id_producto`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `id_categoria` (`id_categoria`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventarios` (
  `id_inventario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_sucursal` int(10) UNSIGNED NOT NULL,
  `id_producto` int(10) UNSIGNED NOT NULL,
  `existencias` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(11) NOT NULL DEFAULT 5,
  PRIMARY KEY (`id_inventario`),
  UNIQUE KEY `id_sucursal` (`id_sucursal`,`id_producto`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `inventarios_ibfk_1` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `inventarios_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `metodos_pago` (
  `id_metodo_pago` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`id_metodo_pago`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Cajas, cajeros, turnos y retiros (dashboard_gerente.php)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cajas` (
  `id_caja` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) DEFAULT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `fondo_base` decimal(10,2) DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'ACTIVA',
  `fecha_registro` datetime DEFAULT NULL,
  PRIMARY KEY (`id_caja`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cajeros` (
  `id_cajero` int(11) NOT NULL AUTO_INCREMENT,
  `id_caja` int(11) NOT NULL,
  `id_sucursal` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cajero`),
  KEY `idx_cajeros_caja` (`id_caja`),
  KEY `idx_cajeros_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_cajero_caja` FOREIGN KEY (`id_caja`) REFERENCES `cajas` (`id_caja`),
  CONSTRAINT `fk_cajero_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `turnos_caja` (
  `id_turno` int(11) NOT NULL AUTO_INCREMENT,
  `id_caja` int(11) NOT NULL,
  `id_cajero` int(11) NOT NULL,
  `id_sucursal` int(10) UNSIGNED NOT NULL,
  `fecha_inicio` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_fin` datetime DEFAULT NULL,
  `dinero_inicial` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total_vendido` decimal(10,2) DEFAULT NULL,
  `total_retiros` decimal(10,2) DEFAULT NULL,
  `dinero_esperado` decimal(10,2) DEFAULT NULL,
  `dinero_contado` decimal(10,2) DEFAULT NULL,
  `diferencia` decimal(10,2) DEFAULT NULL,
  `estado_corte` enum('EXACTO','SOBRANTE','FALTANTE') DEFAULT NULL,
  `horas_extra` tinyint(1) NOT NULL DEFAULT 0,
  `estado` enum('ABIERTO','CERRADO') NOT NULL DEFAULT 'ABIERTO',
  PRIMARY KEY (`id_turno`),
  KEY `idx_turnos_caja` (`id_caja`),
  KEY `idx_turnos_cajero` (`id_cajero`),
  KEY `idx_turnos_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_turno_caja` FOREIGN KEY (`id_caja`) REFERENCES `cajas` (`id_caja`),
  CONSTRAINT `fk_turno_cajero` FOREIGN KEY (`id_cajero`) REFERENCES `cajeros` (`id_cajero`),
  CONSTRAINT `fk_turno_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `retiros_caja` (
  `id_retiro` int(11) NOT NULL AUTO_INCREMENT,
  `id_turno` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_retiro`),
  KEY `idx_retiros_turno` (`id_turno`),
  CONSTRAINT `fk_retiro_turno` FOREIGN KEY (`id_turno`) REFERENCES `turnos_caja` (`id_turno`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------------------
-- Ventas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `ventas` (
  `id_venta` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_sucursal` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_metodo_pago` int(10) UNSIGNED NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `estado` enum('COMPLETADA','CANCELADA') NOT NULL DEFAULT 'COMPLETADA',
  `id_turno` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_venta`),
  KEY `id_sucursal` (`id_sucursal`),
  KEY `id_usuario` (`id_usuario`),
  KEY `id_metodo_pago` (`id_metodo_pago`),
  CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON UPDATE CASCADE,
  CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `ventas_ibfk_3` FOREIGN KEY (`id_metodo_pago`) REFERENCES `metodos_pago` (`id_metodo_pago`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `detalle_ventas` (
  `id_detalle` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `id_venta` bigint(20) UNSIGNED NOT NULL,
  `id_producto` int(10) UNSIGNED NOT NULL,
  `cantidad` int(10) UNSIGNED NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `id_venta` (`id_venta`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Por si alguna tabla ya existía de una importación anterior incompleta:
-- se agregan las columnas que el código necesita.
-- ---------------------------------------------------------------------
ALTER TABLE `usuarios`  ADD COLUMN IF NOT EXISTS `google_id` varchar(255) DEFAULT NULL AFTER `correo`;
ALTER TABLE `usuarios`  ADD COLUMN IF NOT EXISTS `remember_token` char(64) DEFAULT NULL;
ALTER TABLE `usuarios`  ADD COLUMN IF NOT EXISTS `remember_expires_at` datetime DEFAULT NULL;
ALTER TABLE `usuarios`  ADD COLUMN IF NOT EXISTS `recovery_token` char(64) DEFAULT NULL;
ALTER TABLE `usuarios`  ADD COLUMN IF NOT EXISTS `recovery_expires_at` datetime DEFAULT NULL;
ALTER TABLE `productos` ADD COLUMN IF NOT EXISTS `imagen_url` varchar(500) DEFAULT NULL;
ALTER TABLE `ventas`    ADD COLUMN IF NOT EXISTS `id_turno` int(11) DEFAULT NULL;

-- ---------------------------------------------------------------------
-- Catálogos iniciales
-- ---------------------------------------------------------------------
INSERT IGNORE INTO `roles` (`id_rol`, `nombre`) VALUES
(1, 'Administrador General'),
(2, 'Gerente'),
(3, 'Cajero'),
(4, 'Usuario');

INSERT IGNORE INTO `categorias` (`id_categoria`, `nombre`, `descripcion`) VALUES
(1, 'Medicamentos', 'Medicamentos veterinarios'),
(2, 'Alimentos', 'Alimentos para animales'),
(3, 'Antiparasitarios', 'Productos antiparasitarios'),
(4, 'Higiene', 'Productos de higiene'),
(5, 'Accesorios', 'Accesorios para mascotas');

INSERT IGNORE INTO `metodos_pago` (`id_metodo_pago`, `nombre`) VALUES
(1, 'Efectivo'),
(2, 'Tarjeta'),
(3, 'Transferencia');

-- ---------------------------------------------------------------------
-- Sucursal inicial (el panel de gerente exige al menos una)
-- ---------------------------------------------------------------------
INSERT INTO `sucursales` (`nombre`, `direccion`, `telefono`, `contacto`, `estado`)
SELECT 'Sucursal Central', 'Dirección por definir', NULL, NULL, 'ACTIVA' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `sucursales`);

SET FOREIGN_KEY_CHECKS = 1;
