-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 20-11-2026 a las 03:28:47
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `kion`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cajas`
--

CREATE TABLE `cajas` (
  `id_caja` int(11) NOT NULL,
  `nombre` varchar(50) DEFAULT NULL,
  `id_sucursal` int(11) DEFAULT NULL,
  `fondo_base` decimal(10,2) DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'ACTIVA',
  `fecha_registro` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cajas`
--

INSERT INTO `cajas` (`id_caja`, `nombre`, `id_sucursal`, `fondo_base`, `estado`, `fecha_registro`) VALUES
(11, 'Caja 1', 4, NULL, 'ACTIVA', NULL),
(12, 'Caja 2', 4, NULL, 'ACTIVA', NULL),
(13, 'Caja de prueba', 4, NULL, 'ACTIVA', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cajeros`
--

CREATE TABLE `cajeros` (
  `id_cajero` int(11) NOT NULL,
  `id_caja` int(11) NOT NULL,
  `id_sucursal` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cajeros`
--

INSERT INTO `cajeros` (`id_cajero`, `id_caja`, `id_sucursal`, `nombre`, `password_hash`, `estado`, `fecha_registro`) VALUES
(6, 11, 4, 'Mia', '$2y$10$/WlLt/yATokpJMH6qSd3z.DbJoVjruhsfxYUP.nEVcen/T2VBvinW', 'ACTIVO', '2026-10-02 07:09:23'),
(7, 12, 4, 'Fernando', '$2y$10$zWR4g4CBb3iBeF9tIoXOC.mJh.CH/8dnMatJZy4Lunz/vKcS02Iw2', 'ACTIVO', '2026-10-02 07:09:33'),
(8, 13, 4, 'prueba1', '$2y$10$VNggkQoGpgk83.q4W3vLPe2YFX5LdoQabDt88CPFUo5MH84OKg2I2', 'ACTIVO', '2026-10-02 07:09:41');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id_categoria` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id_categoria`, `nombre`, `descripcion`) VALUES
(1, 'Medicamentos', 'Medicamentos veterinarios'),
(2, 'Alimentos', 'Alimentos para animales'),
(3, 'Antiparasitarios', 'Productos antiparasitarios'),
(4, 'Higiene', 'Productos de higiene'),
(5, 'Accesorios', 'Accesorios para mascotas'),
(6, 'Herramientas Agropecuarias', 'Equipo y herramientas para el campo, granjas y manejo de ganado'),
(7, 'Semillas y Fertilizantes', 'Insumos agrícolas, control de plagas y nutrición vegetal'),
(8, 'Suplementos y Vitaminas', 'Complementos vitamínicos para grandes y pequeñas especies');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `consultas`
--

CREATE TABLE `consultas` (
  `id_consulta` int(10) UNSIGNED NOT NULL,
  `id_mascota` int(10) UNSIGNED NOT NULL,
  `id_sucursal` int(10) UNSIGNED NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `motivo` text NOT NULL,
  `estado` enum('PENDIENTE','ATENDIDA','CANCELADA') DEFAULT 'PENDIENTE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `consultas`
--

INSERT INTO `consultas` (`id_consulta`, `id_mascota`, `id_sucursal`, `fecha_hora`, `motivo`, `estado`) VALUES
(1, 1, 4, '2026-10-27 09:00:00', 'vomito y pelaje caido', 'CANCELADA'),
(2, 2, 4, '2026-10-27 09:30:00', 'nnnjnnjnjnjnjn', 'CANCELADA'),
(3, 2, 4, '2026-10-27 10:00:00', 'mjnjnjnjnjnj', 'CANCELADA'),
(4, 2, 4, '2026-10-27 10:30:00', 'jhhjhhhyhyy', 'CANCELADA'),
(5, 1, 4, '2026-10-27 11:00:00', 'ghvgyyfyfyfytfyf', 'CANCELADA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_ventas`
--

CREATE TABLE `detalle_ventas` (
  `id_detalle` bigint(20) UNSIGNED NOT NULL,
  `id_venta` bigint(20) UNSIGNED NOT NULL,
  `id_producto` int(10) UNSIGNED NOT NULL,
  `cantidad` int(10) UNSIGNED NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_ventas`
--

INSERT INTO `detalle_ventas` (`id_detalle`, `id_venta`, `id_producto`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(10, 9, 18, 1, 123.00, 123.00),
(11, 10, 23, 2, 889.00, 1778.00),
(12, 10, 20, 1, 83.00, 83.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventarios`
--

CREATE TABLE `inventarios` (
  `id_inventario` int(10) UNSIGNED NOT NULL,
  `id_sucursal` int(10) UNSIGNED NOT NULL,
  `id_producto` int(10) UNSIGNED NOT NULL,
  `existencias` int(11) NOT NULL DEFAULT 0,
  `stock_minimo` int(11) NOT NULL DEFAULT 5
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inventarios`
--

INSERT INTO `inventarios` (`id_inventario`, `id_sucursal`, `id_producto`, `existencias`, `stock_minimo`) VALUES
(17, 4, 19, 13, 5),
(18, 5, 20, 0, 5),
(19, 4, 20, 3, 5),
(20, 5, 21, 0, 5),
(21, 4, 21, 15, 5),
(22, 5, 22, 0, 5),
(23, 4, 22, 30, 5),
(24, 5, 23, 0, 5),
(25, 4, 23, 14, 5),
(26, 5, 24, 0, 5),
(27, 4, 24, 18, 5),
(28, 5, 25, 0, 5),
(29, 4, 25, 21, 5),
(30, 5, 26, 0, 5),
(31, 4, 26, 156, 5),
(32, 6, 25, 25, 5),
(33, 5, 27, 25, 5),
(34, 6, 27, 25, 5),
(35, 4, 27, 25, 5),
(36, 5, 28, 25, 5),
(37, 6, 28, 25, 5),
(38, 4, 28, 25, 5),
(39, 5, 29, 25, 5),
(40, 6, 29, 25, 5),
(41, 4, 29, 25, 5),
(42, 5, 30, 25, 5),
(43, 6, 30, 25, 5),
(44, 4, 30, 25, 5),
(45, 5, 31, 25, 5),
(46, 6, 31, 25, 5),
(47, 4, 31, 25, 5),
(48, 5, 32, 25, 5),
(49, 6, 32, 25, 5),
(50, 4, 32, 25, 5),
(51, 5, 33, 25, 5),
(52, 6, 33, 25, 5),
(53, 4, 33, 25, 5),
(54, 5, 34, 25, 5),
(55, 6, 34, 25, 5),
(56, 4, 34, 25, 5),
(57, 5, 35, 25, 5),
(58, 6, 35, 25, 5),
(59, 4, 35, 25, 5),
(60, 5, 36, 25, 5),
(61, 6, 36, 25, 5),
(62, 4, 36, 25, 5),
(63, 6, 22, 25, 5),
(64, 6, 23, 25, 5),
(65, 5, 37, 25, 5),
(66, 6, 37, 25, 5),
(67, 4, 37, 25, 5),
(68, 5, 38, 25, 5),
(69, 6, 38, 25, 5),
(70, 4, 38, 25, 5),
(71, 5, 39, 25, 5),
(72, 6, 39, 25, 5),
(73, 4, 39, 25, 5),
(74, 5, 40, 25, 5),
(75, 6, 40, 25, 5),
(76, 4, 40, 25, 5),
(77, 5, 41, 25, 5),
(78, 6, 41, 25, 5),
(79, 4, 41, 25, 5),
(80, 5, 42, 25, 5),
(81, 6, 42, 25, 5),
(82, 4, 42, 25, 5),
(83, 5, 43, 25, 5),
(84, 6, 43, 25, 5),
(85, 4, 43, 25, 5),
(86, 5, 44, 25, 5),
(87, 6, 44, 25, 5),
(88, 4, 44, 25, 5),
(89, 5, 45, 25, 5),
(90, 6, 45, 25, 5),
(91, 4, 45, 25, 5),
(92, 5, 46, 25, 5),
(93, 6, 46, 25, 5),
(94, 4, 46, 25, 5),
(95, 6, 26, 25, 5),
(96, 5, 47, 25, 5),
(97, 6, 47, 25, 5),
(98, 4, 47, 25, 5),
(99, 5, 48, 25, 5),
(100, 6, 48, 25, 5),
(101, 4, 48, 25, 5),
(102, 5, 49, 25, 5),
(103, 6, 49, 25, 5),
(104, 4, 49, 25, 5),
(105, 5, 50, 25, 5),
(106, 6, 50, 25, 5),
(107, 4, 50, 25, 5),
(108, 5, 51, 25, 5),
(109, 6, 51, 25, 5),
(110, 4, 51, 25, 5),
(111, 5, 52, 25, 5),
(112, 6, 52, 25, 5),
(113, 4, 52, 25, 5),
(114, 5, 53, 25, 5),
(115, 6, 53, 25, 5),
(116, 4, 53, 25, 5),
(117, 5, 54, 25, 5),
(118, 6, 54, 25, 5),
(119, 4, 54, 25, 5),
(120, 5, 55, 25, 5),
(121, 6, 55, 25, 5),
(122, 4, 55, 25, 5),
(123, 5, 56, 25, 5),
(124, 6, 56, 25, 5),
(125, 4, 56, 25, 5),
(126, 6, 24, 25, 5),
(127, 5, 57, 25, 5),
(128, 6, 57, 25, 5),
(129, 4, 57, 25, 5),
(130, 5, 58, 25, 5),
(131, 6, 58, 25, 5),
(132, 4, 58, 25, 5),
(133, 5, 59, 25, 5),
(134, 6, 59, 25, 5),
(135, 4, 59, 25, 5),
(136, 5, 60, 25, 5),
(137, 6, 60, 25, 5),
(138, 4, 60, 25, 5),
(139, 5, 61, 25, 5),
(140, 6, 61, 25, 5),
(141, 4, 61, 25, 5),
(142, 5, 62, 25, 5),
(143, 6, 62, 25, 5),
(144, 4, 62, 25, 5),
(145, 5, 63, 25, 5),
(146, 6, 63, 25, 5),
(147, 4, 63, 25, 5),
(148, 5, 64, 25, 5),
(149, 6, 64, 25, 5),
(150, 4, 64, 25, 5),
(151, 5, 65, 25, 5),
(152, 6, 65, 25, 5),
(153, 4, 65, 25, 5),
(154, 5, 66, 25, 5),
(155, 6, 66, 25, 5),
(156, 4, 66, 25, 5),
(157, 5, 18, 25, 5),
(158, 6, 18, 25, 5),
(159, 4, 18, 25, 5),
(160, 5, 19, 25, 5),
(161, 6, 19, 25, 5),
(162, 6, 20, 25, 5),
(163, 6, 21, 25, 5),
(164, 5, 67, 25, 5),
(165, 6, 67, 25, 5),
(166, 4, 67, 25, 5),
(167, 5, 68, 25, 5),
(168, 6, 68, 25, 5),
(169, 4, 68, 25, 5),
(170, 5, 69, 25, 5),
(171, 6, 69, 25, 5),
(172, 4, 69, 25, 5),
(173, 5, 70, 25, 5),
(174, 6, 70, 25, 5),
(175, 4, 70, 25, 5),
(176, 5, 71, 25, 5),
(177, 6, 71, 25, 5),
(178, 4, 71, 25, 5),
(179, 5, 72, 25, 5),
(180, 6, 72, 25, 5),
(181, 4, 72, 25, 5),
(182, 5, 73, 25, 5),
(183, 6, 73, 25, 5),
(184, 4, 73, 25, 5),
(185, 5, 74, 25, 5),
(186, 6, 74, 25, 5),
(187, 4, 74, 25, 5),
(188, 5, 75, 25, 5),
(189, 6, 75, 25, 5),
(190, 4, 75, 25, 5),
(191, 5, 76, 25, 5),
(192, 6, 76, 25, 5),
(193, 4, 76, 25, 5);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `mascotas`
--

CREATE TABLE `mascotas` (
  `id_mascota` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `especie` varchar(50) NOT NULL,
  `estado` enum('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `mascotas`
--

INSERT INTO `mascotas` (`id_mascota`, `id_usuario`, `nombre`, `especie`, `estado`) VALUES
(1, 14, 'gato', 'Conejo', 'INACTIVA'),
(2, 14, 'pedro', 'Perro', 'INACTIVA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `metodos_pago`
--

CREATE TABLE `metodos_pago` (
  `id_metodo_pago` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `metodos_pago`
--

INSERT INTO `metodos_pago` (`id_metodo_pago`, `nombre`) VALUES
(1, 'Efectivo'),
(2, 'Tarjeta'),
(3, 'Transferencia');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id_producto` int(10) UNSIGNED NOT NULL,
  `codigo` varchar(50) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `id_categoria` int(10) UNSIGNED NOT NULL,
  `precio` decimal(10,2) NOT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `imagen_url` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id_producto`, `codigo`, `nombre`, `descripcion`, `id_categoria`, `precio`, `estado`, `imagen_url`) VALUES
(18, 'VET-0012', 'Androide moderno', 'as', 5, 123.00, 'INACTIVO', 'https://upload.wikimedia.org/wikipedia/commons/7/76/Bryan_Dechart_QD_interview_%28cropped%29.jpeg?utm_source=es.wikipedia.org&utm_campaign=index&utm_content=original'),
(19, 'VET-001', 'Collar para Gato Fancy Pets', 'Elástico \r\nVarios Colores\r\n 1 Pza', 5, 40.00, 'ACTIVO', 'https://i5-mx.walmartimages.com/asr/45ed35ce-37f6-4d2c-9601-c25c852c9957.da4ffa3fcebe067a1afb4818b0305b89.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF'),
(20, 'VET-002', 'Collar Antipulgas Fancy Pets', 'Razas Chicas y Cachorros \r\n1 Pieza de 39 cm', 5, 83.00, 'ACTIVO', 'https://i5-mx.walmartimages.com/asr/09fb1284-f1bb-4a33-bb0d-23255fb9d797.53594044031eac76085e79d5d76e1b9f.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF'),
(21, 'VET-003', 'Cama Para Mascota Wow Pets', 'furry cushion cama para mascotaofrece un descanso cómodo. resistente y fácil delimpiar, proporciona a tu mascota el confort que se merece encualquier época del año.', 5, 810.00, 'ACTIVO', 'https://http2.mlstatic.com/D_NQ_NP_2X_832418-MLM98168453444_112025-F.webp'),
(22, 'VET-004', 'Croquetas Para Gato Hill\'s Science Diet Alimento Para Gato Senior', 'Sabor Pollo\r\ncontenido 7.3Kg', 2, 1530.00, 'ACTIVO', 'https://http2.mlstatic.com/D_NQ_NP_2X_948672-MLA99338544644_112025-F.webp'),
(23, 'VET-005', 'Alimento Seco para Gatos Adultos Whiskas 4 en 1, 100% Nutrición Completa y Balanceada – Sabor Carne 20kg Alimento Seco para Gatos Adultos Whiskas 4 en', '100% Nutrición Completa y Balanceada – Sabor Carne 20kg', 2, 889.00, 'ACTIVO', 'https://http2.mlstatic.com/D_NQ_NP_2X_939234-MLA115723586037_082026-F.webp'),
(24, 'VET-006', 'Shampoo De Zábila Para Caballo', 'Sábila 1 L Aranda', 4, 230.00, 'ACTIVO', 'https://http2.mlstatic.com/D_NQ_NP_2X_672876-MLA99821792583_112025-F.webp'),
(25, 'VET-007', 'Trafull Desparasitante Para Perros', 'Caja Con 10 Tabletas 2pz', 1, 399.00, 'ACTIVO', 'https://http2.mlstatic.com/D_NQ_NP_2X_742842-MLM98886359065_112025-F.webp'),
(26, 'VET-008', 'Credelio 3 Tabletas Antipulgas Antiparasitos', NULL, 3, 999.00, 'ACTIVO', 'https://http2.mlstatic.com/D_NQ_NP_2X_633810-MLM112748042869_062026-F.webp'),
(27, 'MED-001', 'Amoxicilina + Ácido Clavulánico 250mg', 'Antibiótico de amplio espectro para perros y gatos. Caja con 14 tabletas.', 1, 245.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQstvo-RoZCLRGlDYieEhIjofrrm5ULcGDj0KISGV4T_g&s=10'),
(28, 'MED-002', 'Meloxicam Veterinario 1.5mg/ml', 'Antiinflamatorio y analgésico oral en suspensión. Frasco con 30ml y jeringa dosificadora.', 1, 205.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRveftUs35a1Gy7gqsx1KYn47uoqjxbwxheOAIBss3e1w&s=10'),
(29, 'MED-003', 'Otiflex Limpiador Ótico 100ml', 'Solución limpiadora y antiséptica para prevención de otitis en perros y gatos.', 1, 170.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRSHpjAxsKs10bGRbtqZni1f6PPz9mnfmrzRdgHrn6ncw&s=10'),
(30, 'MED-004', 'Doxiciclina 100mg Veterinaria', 'Tratamiento para infecciones respiratorias y ehrlichiosis canina. Caja con 20 tabletas.', 1, 180.00, 'ACTIVO', 'https://petspharma.com.mx/wp-content/uploads/2024/01/Doxipets_100_20tabs-2.png'),
(31, 'MED-005', 'Colirio Oftálmico Tobramicina Vet', 'Gotas oftálmicas antibióticas para conjuntivitis e infecciones oculares. Frasco 10ml.', 1, 150.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTDsVez6EtzRv6DfyHhReGNr0K8_1HfVVMljeMHgyFnIQ&s=10'),
(32, 'MED-006', 'Suero Oral Electrolitos Pet', 'Rehidratante oral sabor pollo para casos de diarrea o golpe de calor. Botella 500ml.', 1, 80.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTHWonlZJgoNrh1qRNsibum-azfmmI6FaQaJKbHTodgsQ&s=10'),
(33, 'MED-007', 'Omeprazol Veterinario 20mg', 'Protector gástrico para gastritis y reflujo en caninos. Caja con 14 cápsulas.', 1, 140.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRuo2gXOXdO1T0O9j-2rPHXIJ5hKajp8wJcxPEHScMJTQ&s=10'),
(34, 'MED-008', 'Complejo B + Hierro Inyectable 50ml', 'Suplemento vitamínico para recuperación postoperatoria y anemia en animales.', 1, 125.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQgGPv7FESUAHL839ODnGsOIQoyNh3pCyg-ajBJ4B48XA&s=10'),
(35, 'MED-009', 'Pomada Cicatrizante con Óxido de Zinc', 'Crema regeneradora para heridas superficiales, raspones y almohadillas agrietadas. Tubo 60g.', 1, 110.00, 'ACTIVO', 'https://farmaciassimilaresmx.vtexassets.com/arquivos/ids/164368/157-OXIDO-DE-ZINC-25GR-100GR-PASTA-1-PZA-ALPHARMA-1.jpg?v=639105027916900000'),
(36, 'MED-010', 'Probiótico Digestivo FloraPet', 'Pasta oral con probióticos para restaurar la flora intestinal. Jeringa 15ml.', 1, 190.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTu9ThWl4993Hcs7X96l2nvyuPNrMSvJ8WTjFQS93f2NA&s=10'),
(37, 'ALI-001', 'Croquetas Nupec Adulto 8kg', 'Alimento balanceado super premium para perro adulto razas medianas y grandes.', 2, 680.00, 'ACTIVO', 'https://m.media-amazon.com/images/I/519APa4Ez6L._AC_UF1000,1000_QL80_.jpg'),
(38, 'ALI-002', 'Croquetas Nupec Cachorro 2kg', 'Fórmula alta en proteína y DHA para el desarrollo óptimo de cachorros.', 2, 230.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTR7FrIaNQjGPjIiqsb6AGwvekA2r74yFk8a7mHX824Hc1LJZJiQKgHJtXj&s=10'),
(39, 'ALI-003', 'Royal Canin Urinary S/O Gato 1.5kg', 'Alimento terapéutico para disolución de cálculos urinarios en felinos.', 2, 585.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSD7TYbG-dWHs0-sUG-Y8OnFNj8mAcok4Z6L47EI6GqWYruk1r_KkP7vrUe&s=10'),
(40, 'ALI-004', 'Pro Plan Adulto Razas Pequeñas 3kg', 'Alimento con carne fresca de pollo y tecnología OptiLife.', 2, 465.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSFYR8GUeRat0CJMvaLfIjRWhUzfVg_8E4spkMV6vx8mA&s=10'),
(41, 'ALI-005', 'Lata Hill\'s Prescription Diet a/d 156g', 'Alimento húmedo de alta densidad calórica para perros y gatos en recuperación.', 2, 85.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRZknoGPrvuT2-hR_yit7AegpZ3Js3MKDct0zeC0D3b9A&s=10'),
(42, 'ALI-006', 'Sobre Whiskas Pollo en Salsa 85g', 'Alimento húmedo completo para gatos adultos sabor pollo.', 2, 15.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSkyovS9uIp0E70hV22qc3x7AEV9dptI8Sl2t033lfShg&s=10'),
(43, 'ALI-007', 'Croquetas Cat Chow Hogareños 3kg', 'Alimento para gatos de interior con control de bolas de pelo.', 2, 255.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR4IKQmKpw7LIOKY9-u-Z07sIDPwksu0ivT09jHcCUNow&s'),
(44, 'ALI-008', 'Premios Dentales Pedigree Dentastix (7 pzas)', 'Snacks masticables que ayudan a reducir la formación de sarro.', 2, 75.00, 'ACTIVO', 'https://i5-mx.walmartimages.com/asr/50c7fb2e-0853-430c-9fc1-24ac3528ecea.dec029c2b8eeff9aac701d13d7e5ef02.jpeg?odnHeight=612&odnWidth=612&odnBg=FFFFFF'),
(45, 'ALI-009', 'Leche Maternizada en Polvo para Cachorros 300g', 'Sustituto lácteo completo para cachorros recién nacidos o huérfanos.', 2, 265.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQHaOhVMPwQKKEKKbrSj4QDpojkFXwf7Hf0-T153uwuYA&s'),
(46, 'ALI-010', 'Alimento para Roedores y Conejos 1kg', 'Mezcla de pellets de alfalfa, cereales y vegetales deshidratados.', 2, 100.00, 'ACTIVO', 'https://http2.mlstatic.com/D_NQ_NP_606484-MLA99850416083_112025-O.webp'),
(47, 'ANT-001', 'NexGard Tableta Masticable (10-25kg)', 'Antipulgas y garrapatas mensual sabor carne para perros medianos.', 3, 310.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSMX9OssoVDs57iLQUvDHUftpBcj9M3ML7yIhbSj5s46Q&s=10'),
(48, 'ANT-002', 'Bravecto Tableta Masticable (4.5-10kg)', 'Protección de hasta 12 semanas contra pulgas y garrapatas para perros pequeños.', 3, 535.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTMAtnFv52ixP9pLGhjJoj98VJL5WQoA2Fv3xQVkewVIA&s'),
(49, 'ANT-003', 'Simparica Trio (20-40kg)', 'Protección mensual contra pulgas, garrapatas, gusano del corazón y parásitos internos.', 3, 390.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQjNzuSXL6NiJRnZNveoZu5YXj0IeDmcUDH4x4-ua4OZw&s'),
(50, 'ANT-004', 'Pipeta Frontline Plus Gatos', 'Antiparasitario tópico contra pulgas, piojos y garrapatas para felinos.', 3, 185.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTbqQWMHoAio64Lzz0EGnnZtHsN8B7-CTfMR9dI18kI2A&s=10'),
(51, 'ANT-005', 'Desparasitante Interno Drontal Plus (2 tabs)', 'Tabletas de amplio espectro contra lombrices y tenias en perros.', 3, 160.00, 'ACTIVO', 'https://assets.elanco.com/8e0bf1c2-1ae4-001f-9257-f2be3c683fb1/1df7af76-6ebc-4045-8e95-3aec84b82b2e/Drontal%20Plus%20Saborizado_Argentina_850x850.png?w=750&q=75&auto=format'),
(52, 'ANT-006', 'Collar Antipulgas Seresto Perro Grande', 'Collar repelente con protección continua de hasta 8 meses.', 3, 845.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSVlchMQL_zZvSrW4PzsFKY17GV3Ck5H5kJINkF0wUpQQ&s'),
(53, 'ANT-007', 'Pipeta Advantage Multi Gato (hasta 4kg)', 'Solución spot-on contra pulgas, ácaros del oído y parásitos gastrointestinales.', 3, 235.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRAtbwF2Xf4vZxvxQlz28lKKgiRmlEWY06WaEV1NMOKzg&s=10'),
(54, 'ANT-008', 'Desparasitante Suspensión Cachorros 20ml', 'Fórmula líquida suave con pirantel y febantel para cachorros desde 2 semanas.', 3, 120.00, 'ACTIVO', 'https://www.mistermascotas.com.mx/cdn/shop/files/10001_1024x.png?v=1749573177'),
(55, 'ANT-009', 'Spray Antipulgas Fipronil 250ml', 'Atomizador de acción rápida para eliminar infestaciones en pelaje y camas.', 3, 250.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQzbRMk_DiKGhrWFHcQH4C_YW5d5MmX_A7OdkN-Nt1XUg&s=10'),
(56, 'ANT-010', 'Jabón Antipulgas con Permetrina 100g', 'Jabón en barra auxiliar en el control de ectoparásitos durante el baño.', 3, 55.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTl3FHXLaqfpiFJc9XQmaYkuJsFUWd56k05-xOiXz_oKw&s=10'),
(57, 'HIG-001', 'Shampoo Hipoalergénico de Avena 500ml', 'Calma la comezón e hidrata pieles sensibles sin parabenos ni sulfatos.', 4, 155.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQjDqyYOuRdaZhozVMFSqduI-JPoh3im-VDzfE7lldWMw&s'),
(58, 'HIG-002', 'Shampoo Medicado con Clorhexidina 250ml', 'Antiséptico y antifúngico auxiliar en dermatitis bacterianas.', 4, 210.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQL1H_gevV4uU3LoWvbscuktD0oc8JWeazqNH81eEwxzQ&s=10'),
(59, 'HIG-003', 'Arena Aglutinante para Gato 10kg', 'Arena de bentonita natural con control de olores y bajo nivel de polvo.', 4, 200.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSD4yYIdmxgRfQI_gkRtxXqJfmywIDS2KTTBwQqqFPRUA&s=10'),
(60, 'HIG-004', 'Tapetes Entrenadores Absorbentes (30 pzas)', 'Tapetes con carbón activado y atrayente para cachorros.', 4, 225.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQvAH7ejsX0T7kCpw12d2mPTipzec29rxO_bhmLxE7rxQ&s=10'),
(61, 'HIG-005', 'Pasta Dental Enzimática Sabor Carne + Cepillo', 'Kit de higiene bucal que combate el mal aliento y la placa dental.', 4, 140.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRrYdeoJoY2ufzk9ZmfNxx-56qqQMz09WfcAlMnII0Tww&s'),
(62, 'HIG-006', 'Toallitas Húmedas para Mascotas (80 pzas)', 'Limpieza rápida de patas, pelaje y cara con aloe vera y manzanilla.', 4, 80.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRRtDVo2d3OQBkW5wPoVUlIWfhbTLrTcLXRwh1flfUYgw&s=10'),
(63, 'HIG-007', 'Espuma de Baño en Seco 200ml', 'Limpiador sin enjuague ideal para gatos o perros recién vacunados.', 4, 135.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTLXI49JoMLGCVD4Ft7oLK2vzn8AHAvG1hGKrwWes-6NA&s'),
(64, 'HIG-008', 'Perfume Colonia Fresca Pet 120ml', 'Loción sin alcohol con aroma suave de larga duración.', 4, 110.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTGWkgzy10aeVxhTf08eMN8Li9XEmxm5OipE4nQcgINhg&s'),
(65, 'HIG-009', 'Bolsas Biodegradables para Heces (8 rollos)', 'Paquete con 120 bolsas resistentes y amigables con el ambiente.', 4, 95.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQvbUfFuZjwsgrvLKnNJCh-yJ-A05RS8m44yjmOiSxXhw&s'),
(66, 'HIG-010', 'Eliminador de Olores y Manchas Enzimático 750ml', 'Neutraliza olores de orina en pisos, alfombras y muebles.', 4, 145.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcR9hY9gXHcK63sqJB1jxPuXlf9PcQP8feVzQ5M9WZndaQ&s'),
(67, 'ACC-001', 'Correa Retráctil 5 Metros Reforzada', 'Correa ergonómica con botón de freno para perros de hasta 25kg.', 5, 250.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRITj_M-qegVLBD2O0ZlkWxUJhomWoQWtBttNqyET72uw&s'),
(68, 'ACC-002', 'Pechera Táctica Ajustable Talla M', 'Arnés antitirones transpirable con broches de seguridad y bandas reflejantes.', 5, 295.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTw3kN8S3gEh_KVyijBANKTb_BMwEPON52_hrtsLKuKGw&s'),
(69, 'ACC-003', 'Plato Doble de Acero Inoxidable Antiderrapante', 'Comedero y bebedero con base de silicón fácil de lavar.', 5, 170.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTt_T8OjmEdY5FhXUbcmj5Efp1XYED8_YvjWAXIaUVCGQ&s'),
(70, 'ACC-004', 'Cama Ortopédica Lavable Talla Grande', 'Cama acolchada con funda removible con cierre y base impermeable.', 5, 560.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSNVvVtegvtl69yaszXysOXUsA60jvipTKKJznLfPdRxA&s=10'),
(71, 'ACC-005', 'Transportadora Rígida Mediana (IATA)', 'Jaula transportadora con puerta metálica y ventilación lateral.', 5, 670.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSd8_94hfzynR54f7ayouTbUOXxxnMewDpl2JTpLGlTJg&s'),
(72, 'ACC-006', 'Rascador de Sisal con Pelota para Gato', 'Poste rascador vertical de 50cm con base afelpada.', 5, 275.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcS5qoBEnIn6cit4JRlUI2eu5rLzsyJSQZlCb0fi9GP1YA&s'),
(73, 'ACC-007', 'Juguete Mordedera Kong Classic Mediano', 'Hule natural ultrarresistente rellenable con premios.', 5, 240.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTsXGTj-Gj31gxjC480CeL7cl2jAVWeEiN8Y2gied-7NQ&s=10'),
(74, 'ACC-008', 'Cepillo Carda Autolimpiante', 'Remueve pelo muerto y nudos con botón expulsor de pelo.', 5, 140.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQayy8we1AfAfe1t9sJwWsD-FgxWbXShl_TfYO7bbAURw&s=10'),
(75, 'ACC-009', 'Cortauñas Profesional con Tope de Seguridad', 'Pinza de acero inoxidable para perros y gatos con lima incluida.', 5, 125.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSXezChKFHCtYFI3hesAHqlxWoKgQQ3G0bLKLwjXWcFDg&s=10'),
(76, 'ACC-010', 'Collar Isabelino Acolchado Talla M', 'Cono de recuperación cómodo y ligero con ajuste de velcro.', 5, 160.00, 'ACTIVO', 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRIuT0SBaSQaSASZMdLu9F-26Oayux4j5XW59VmocSUTw&s');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `retiros_caja`
--

CREATE TABLE `retiros_caja` (
  `id_retiro` int(11) NOT NULL,
  `id_turno` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `retiros_caja`
--

INSERT INTO `retiros_caja` (`id_retiro`, `id_turno`, `monto`, `motivo`, `fecha`) VALUES
(1, 4, 230.00, 'Retiro manual', '2026-10-02 08:04:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id_rol` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id_rol`, `nombre`) VALUES
(1, 'Administrador General'),
(3, 'Cajero'),
(2, 'Gerente'),
(4, 'Usuario');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `sucursales`
--

CREATE TABLE `sucursales` (
  `id_sucursal` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `direccion` varchar(255) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `contacto` varchar(150) DEFAULT NULL,
  `estado` enum('ACTIVA','INACTIVA') NOT NULL DEFAULT 'ACTIVA'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `sucursales`
--

INSERT INTO `sucursales` (`id_sucursal`, `nombre`, `direccion`, `telefono`, `contacto`, `estado`) VALUES
(4, 'Manzanillo', 'Calle prueba #24', '3141572390', 'veterinaria_juarez@gmail.com', 'ACTIVA'),
(5, 'Colima', 'Calle prueba2 #55', '3141572367', 'veterinaria_ibarra@gmail.com', 'ACTIVA'),
(6, 'Editame!', 'estoy mal', '3141572361', 'ninguno@gmail.com', 'INACTIVA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turnos_caja`
--

CREATE TABLE `turnos_caja` (
  `id_turno` int(11) NOT NULL,
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
  `estado` enum('ABIERTO','CERRADO') NOT NULL DEFAULT 'ABIERTO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `turnos_caja`
--

INSERT INTO `turnos_caja` (`id_turno`, `id_caja`, `id_cajero`, `id_sucursal`, `fecha_inicio`, `fecha_fin`, `dinero_inicial`, `total_vendido`, `total_retiros`, `dinero_esperado`, `dinero_contado`, `diferencia`, `estado_corte`, `horas_extra`, `estado`) VALUES
(3, 13, 8, 4, '2026-10-02 07:09:57', '2026-10-02 07:10:35', 2500.00, 123.00, 0.00, 2623.00, 2623.00, 0.00, 'EXACTO', 0, 'CERRADO'),
(4, 11, 6, 4, '2026-10-02 08:03:54', '2026-10-02 08:04:33', 2500.00, 1861.00, 230.00, 4131.00, 4131.00, 0.00, 'EXACTO', 0, 'CERRADO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `correo` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `id_rol` int(10) UNSIGNED NOT NULL,
  `id_sucursal` int(10) UNSIGNED DEFAULT NULL,
  `estado` enum('ACTIVO','INACTIVO') NOT NULL DEFAULT 'ACTIVO',
  `remember_token` char(64) DEFAULT NULL,
  `remember_expires_at` datetime DEFAULT NULL,
  `recovery_token` char(64) DEFAULT NULL,
  `recovery_expires_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id_usuario`, `nombre`, `apellido`, `correo`, `password_hash`, `id_rol`, `id_sucursal`, `estado`, `remember_token`, `remember_expires_at`, `recovery_token`, `recovery_expires_at`) VALUES
(7, 'Admin Prueba', 'Demo', 'admin_demo@kion.com', '$2y$10$DCWt21M8r6CgbzZTauWgjeLa0.i3SmhOINVCRUHQqkXo4j4U2wTVW', 1, NULL, 'ACTIVO', NULL, NULL, NULL, NULL),
(11, 'Gerente', 'Manzanillo', 'GerenteManzanillo@gmail.com', '$2y$10$rrOpbVxSJ33D8wymuPFB9O356gV9Q.HGrpKR4sgtQKZIJs90zNe4W', 2, 4, 'ACTIVO', NULL, NULL, NULL, NULL),
(12, 'Gerente', 'Colima', 'GerenteColima@gmail.com', '$2y$10$DeOtKtCrwGuRZLumlfuMxepUIMFdbCoW8kKaPh1BMCc0VnkVOVTvm', 2, 5, 'ACTIVO', NULL, NULL, NULL, NULL),
(14, 'Usuario', '-', 'Usuariocomun@gmail.com', '$2y$10$QeKVLbaIbkEhFUMjHEkiy.x2w3ew.NFbzyXo6P1HoMgO.g62UlxKi', 4, NULL, 'ACTIVO', NULL, NULL, NULL, NULL),
(15, 'David', 'SALGADO', 'dsalgadozepeda@gmail.com', '$2y$10$KgxtU0hoj2eXJ9wUi/QLquJv5Rrye6L8CmUlkoQdS4Pk.t6V9z6qi', 4, NULL, 'ACTIVO', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id_venta` bigint(20) UNSIGNED NOT NULL,
  `id_sucursal` int(10) UNSIGNED NOT NULL,
  `id_usuario` int(10) UNSIGNED NOT NULL,
  `id_metodo_pago` int(10) UNSIGNED NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `total` decimal(10,2) NOT NULL,
  `estado` enum('COMPLETADA','CANCELADA') NOT NULL DEFAULT 'COMPLETADA',
  `id_turno` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id_venta`, `id_sucursal`, `id_usuario`, `id_metodo_pago`, `fecha_hora`, `total`, `estado`, `id_turno`) VALUES
(9, 4, 11, 1, '2026-10-02 07:10:17', 123.00, 'COMPLETADA', 3),
(10, 4, 11, 1, '2026-10-02 08:04:12', 1861.00, 'COMPLETADA', 4);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `cajas`
--
ALTER TABLE `cajas`
  ADD PRIMARY KEY (`id_caja`);

--
-- Indices de la tabla `cajeros`
--
ALTER TABLE `cajeros`
  ADD PRIMARY KEY (`id_cajero`),
  ADD KEY `idx_cajeros_caja` (`id_caja`),
  ADD KEY `idx_cajeros_sucursal` (`id_sucursal`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id_categoria`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `consultas`
--
ALTER TABLE `consultas`
  ADD PRIMARY KEY (`id_consulta`),
  ADD KEY `id_mascota` (`id_mascota`),
  ADD KEY `id_sucursal` (`id_sucursal`);

--
-- Indices de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_venta` (`id_venta`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `inventarios`
--
ALTER TABLE `inventarios`
  ADD PRIMARY KEY (`id_inventario`),
  ADD UNIQUE KEY `id_sucursal` (`id_sucursal`,`id_producto`),
  ADD KEY `id_producto` (`id_producto`);

--
-- Indices de la tabla `mascotas`
--
ALTER TABLE `mascotas`
  ADD PRIMARY KEY (`id_mascota`),
  ADD KEY `id_usuario` (`id_usuario`);

--
-- Indices de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  ADD PRIMARY KEY (`id_metodo_pago`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id_producto`),
  ADD UNIQUE KEY `codigo` (`codigo`),
  ADD KEY `id_categoria` (`id_categoria`);

--
-- Indices de la tabla `retiros_caja`
--
ALTER TABLE `retiros_caja`
  ADD PRIMARY KEY (`id_retiro`),
  ADD KEY `idx_retiros_turno` (`id_turno`);

--
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id_rol`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `sucursales`
--
ALTER TABLE `sucursales`
  ADD PRIMARY KEY (`id_sucursal`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `turnos_caja`
--
ALTER TABLE `turnos_caja`
  ADD PRIMARY KEY (`id_turno`),
  ADD KEY `idx_turnos_caja` (`id_caja`),
  ADD KEY `idx_turnos_cajero` (`id_cajero`),
  ADD KEY `idx_turnos_sucursal` (`id_sucursal`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `correo` (`correo`),
  ADD KEY `id_rol` (`id_rol`),
  ADD KEY `id_sucursal` (`id_sucursal`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id_venta`),
  ADD KEY `id_sucursal` (`id_sucursal`),
  ADD KEY `id_usuario` (`id_usuario`),
  ADD KEY `id_metodo_pago` (`id_metodo_pago`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `cajas`
--
ALTER TABLE `cajas`
  MODIFY `id_caja` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT de la tabla `cajeros`
--
ALTER TABLE `cajeros`
  MODIFY `id_cajero` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id_categoria` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `consultas`
--
ALTER TABLE `consultas`
  MODIFY `id_consulta` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  MODIFY `id_detalle` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `inventarios`
--
ALTER TABLE `inventarios`
  MODIFY `id_inventario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=287;

--
-- AUTO_INCREMENT de la tabla `mascotas`
--
ALTER TABLE `mascotas`
  MODIFY `id_mascota` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `metodos_pago`
--
ALTER TABLE `metodos_pago`
  MODIFY `id_metodo_pago` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id_producto` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT de la tabla `retiros_caja`
--
ALTER TABLE `retiros_caja`
  MODIFY `id_retiro` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id_rol` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `sucursales`
--
ALTER TABLE `sucursales`
  MODIFY `id_sucursal` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de la tabla `turnos_caja`
--
ALTER TABLE `turnos_caja`
  MODIFY `id_turno` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id_usuario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_venta` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `cajeros`
--
ALTER TABLE `cajeros`
  ADD CONSTRAINT `fk_cajero_caja` FOREIGN KEY (`id_caja`) REFERENCES `cajas` (`id_caja`),
  ADD CONSTRAINT `fk_cajero_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`);

--
-- Filtros para la tabla `consultas`
--
ALTER TABLE `consultas`
  ADD CONSTRAINT `consultas_ibfk_1` FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`),
  ADD CONSTRAINT `consultas_ibfk_2` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`);

--
-- Filtros para la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`id_venta`) REFERENCES `ventas` (`id_venta`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `inventarios`
--
ALTER TABLE `inventarios`
  ADD CONSTRAINT `inventarios_ibfk_1` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inventarios_ibfk_2` FOREIGN KEY (`id_producto`) REFERENCES `productos` (`id_producto`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `mascotas`
--
ALTER TABLE `mascotas`
  ADD CONSTRAINT `mascotas_ibfk_1` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`);

--
-- Filtros para la tabla `productos`
--
ALTER TABLE `productos`
  ADD CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`id_categoria`) REFERENCES `categorias` (`id_categoria`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `retiros_caja`
--
ALTER TABLE `retiros_caja`
  ADD CONSTRAINT `fk_retiro_turno` FOREIGN KEY (`id_turno`) REFERENCES `turnos_caja` (`id_turno`);

--
-- Filtros para la tabla `turnos_caja`
--
ALTER TABLE `turnos_caja`
  ADD CONSTRAINT `fk_turno_caja` FOREIGN KEY (`id_caja`) REFERENCES `cajas` (`id_caja`),
  ADD CONSTRAINT `fk_turno_cajero` FOREIGN KEY (`id_cajero`) REFERENCES `cajeros` (`id_cajero`),
  ADD CONSTRAINT `fk_turno_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`);

--
-- Filtros para la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`id_rol`) REFERENCES `roles` (`id_rol`) ON UPDATE CASCADE,
  ADD CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursales` (`id_sucursal`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ventas_ibfk_3` FOREIGN KEY (`id_metodo_pago`) REFERENCES `metodos_pago` (`id_metodo_pago`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
