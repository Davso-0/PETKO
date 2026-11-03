-- ============================================================
-- PETKO · Módulo de Consultas Veterinarias
-- Script idempotente: se puede ejecutar varias veces sin error.
-- Requiere MariaDB 10.0.2+ (ADD COLUMN IF NOT EXISTS).
-- ============================================================

CREATE TABLE IF NOT EXISTS `mascotas` (
  `id_mascota` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `especie` varchar(50) NOT NULL,
  `estado` enum('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA',
  PRIMARY KEY (`id_mascota`),
  KEY `id_usuario` (`id_usuario`),
  CONSTRAINT `mascotas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `consultas` (
  `id_consulta` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_mascota` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `motivo` text NOT NULL,
  `estado` enum('PENDIENTE','ATENDIDA','CANCELADA') DEFAULT 'PENDIENTE',
  PRIMARY KEY (`id_consulta`),
  KEY `id_mascota` (`id_mascota`),
  KEY `id_sucursal` (`id_sucursal`),
  CONSTRAINT `consultas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  CONSTRAINT `consultas_ibfk_2` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Borrado lógico: una mascota con historial clínico se marca INACTIVA
-- en lugar de eliminarse, para conservar sus consultas (FK consultas_ibfk_1).
ALTER TABLE `mascotas`
  ADD COLUMN IF NOT EXISTS `estado` enum('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA';
