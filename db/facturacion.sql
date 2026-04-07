-- MariaDB dump 10.19  Distrib 10.11.3-MariaDB, for debian-linux-gnueabihf (armv7l)
--
-- Host: localhost    Database: facturacion
-- ------------------------------------------------------
-- Server version	10.11.3-MariaDB-1+rpi1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `caja`
--

DROP TABLE IF EXISTS `caja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `caja` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha_apertura` datetime DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `monto_inicial` decimal(10,2) DEFAULT NULL,
  `monto_final` decimal(10,2) DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `caja`
--

LOCK TABLES `caja` WRITE;
/*!40000 ALTER TABLE `caja` DISABLE KEYS */;
/*!40000 ALTER TABLE `caja` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) DEFAULT NULL,
  `documento` varchar(50) DEFAULT NULL,
  `tipo_documento` varchar(20) DEFAULT NULL,
  `condicion_iva` varchar(50) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comprobantes_afip`
--

DROP TABLE IF EXISTS `comprobantes_afip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comprobantes_afip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venta_id` int(11) DEFAULT NULL,
  `cae` varchar(50) DEFAULT NULL,
  `fecha_vencimiento` date DEFAULT NULL,
  `numero_comprobante` int(11) DEFAULT NULL,
  `punto_venta` int(11) DEFAULT NULL,
  `tipo_comprobante` varchar(10) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `venta_id` (`venta_id`),
  CONSTRAINT `comprobantes_afip_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comprobantes_afip`
--

LOCK TABLES `comprobantes_afip` WRITE;
/*!40000 ALTER TABLE `comprobantes_afip` DISABLE KEYS */;
/*!40000 ALTER TABLE `comprobantes_afip` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimientos_caja`
--

DROP TABLE IF EXISTS `movimientos_caja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimientos_caja` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `caja_id` int(11) DEFAULT NULL,
  `tipo` varchar(20) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `monto` decimal(10,2) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `caja_id` (`caja_id`),
  CONSTRAINT `movimientos_caja_ibfk_1` FOREIGN KEY (`caja_id`) REFERENCES `caja` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimientos_caja`
--

LOCK TABLES `movimientos_caja` WRITE;
/*!40000 ALTER TABLE `movimientos_caja` DISABLE KEYS */;
/*!40000 ALTER TABLE `movimientos_caja` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `nombre_negocio`
--

DROP TABLE IF EXISTS `nombre_negocio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nombre_negocio` (
  `id` int(11) NOT NULL CHECK (`id` = 1),
  `nombre_negocio` varchar(100) DEFAULT 'NEXUS POS',
  `eslogan` varchar(150) DEFAULT 'POINT OF SALE',
  `moneda` varchar(5) DEFAULT '$',
  `impuesto` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nombre_negocio`
--

LOCK TABLES `nombre_negocio` WRITE;
/*!40000 ALTER TABLE `nombre_negocio` DISABLE KEYS */;
INSERT INTO `nombre_negocio` VALUES
(1,'BOTILLERIA CURICO','PUNTO DE VENTA','$',0.00);
/*!40000 ALTER TABLE `nombre_negocio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pagos`
--

DROP TABLE IF EXISTS `pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venta_id` int(11) DEFAULT NULL,
  `metodo` varchar(50) DEFAULT NULL,
  `monto` decimal(10,2) DEFAULT NULL,
  `fecha` datetime DEFAULT current_timestamp(),
  `vuelto` decimal(10,2) DEFAULT 0.00,
  `entregado` decimal(10,2) DEFAULT 0.00,
  `estado` varchar(20) DEFAULT 'pendiente',
  PRIMARY KEY (`id`),
  KEY `venta_id` (`venta_id`),
  KEY `idx_pagos_fecha` (`fecha`),
  KEY `idx_pagos_estado` (`estado`),
  CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos`
--

LOCK TABLES `pagos` WRITE;
/*!40000 ALTER TABLE `pagos` DISABLE KEYS */;
INSERT INTO `pagos` VALUES
(1,3,'efectivo',8000.00,'2026-04-05 15:05:51',0.00,0.00,'pendiente'),
(2,5,'efectivo',2000.00,'2026-04-05 15:15:11',0.00,0.00,'pendiente'),
(3,25,'EFECTIVO',10000.00,'2026-04-05 16:34:03',0.00,0.00,'pendiente'),
(4,36,'EFECTIVO',2000.00,'2026-04-05 17:34:20',0.00,0.00,'pendiente'),
(5,40,'EFECTIVO',4000.00,'2026-04-05 17:56:13',0.00,0.00,'pendiente'),
(6,41,'EFECTIVO',4000.00,'2026-04-05 18:01:02',0.00,0.00,'pendiente'),
(7,45,'EFECTIVO',2000.00,'2026-04-05 18:02:38',0.00,0.00,'pendiente'),
(8,58,'EFECTIVO',2000.00,'2026-04-05 18:42:27',0.00,0.00,'pendiente'),
(9,61,'EFECTIVO',2000.00,'2026-04-05 18:44:12',0.00,0.00,'pendiente'),
(10,69,'EFECTIVO',2000.00,'2026-04-06 18:57:41',0.00,0.00,'pendiente'),
(11,69,'EFECTIVO',2000.00,'2026-04-06 18:57:52',0.00,0.00,'pendiente'),
(12,71,'EFECTIVO',2000.00,'2026-04-06 19:01:16',0.00,0.00,'pendiente'),
(13,72,'EFECTIVO',2000.00,'2026-04-06 19:03:53',0.00,0.00,'pendiente'),
(14,74,'EFECTIVO',4000.00,'2026-04-06 19:06:45',0.00,0.00,'pendiente'),
(15,78,'EFECTIVO',2000.00,'2026-04-06 19:11:46',0.00,0.00,'pendiente'),
(16,80,'EFECTIVO',2000.00,'2026-04-06 19:18:03',0.00,0.00,'pendiente'),
(17,84,'EFECTIVO',2000.00,'2026-04-06 19:35:22',0.00,0.00,'completado'),
(18,86,'EFECTIVO',8000.00,'2026-04-06 19:37:58',0.00,0.00,'completado'),
(19,91,'EFECTIVO',2000.00,'2026-04-06 21:14:21',0.00,0.00,'completado'),
(20,93,'EFECTIVO',4000.00,'2026-04-06 21:24:14',0.00,0.00,'completado'),
(21,96,'EFECTIVO',4000.00,'2026-04-06 21:27:35',0.00,0.00,'completado');
/*!40000 ALTER TABLE `pagos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `stock` int(11) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `categoria_id` int(11) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  `imagen` varchar(255) DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `categoria_id` (`categoria_id`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES
(1,'123','Coca-Cola','None',2000.00,57,1,NULL,'2026-04-04 11:47:18','imagenes/123.png',1200.00),
(2,'22222','juan','',2000.00,100,1,NULL,'2026-04-06 19:41:44','imagenes/coca.png',1000.00);
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos_precios_historial`
--

DROP TABLE IF EXISTS `productos_precios_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `productos_precios_historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) DEFAULT NULL,
  `precio_venta` decimal(10,2) DEFAULT NULL,
  `precio_costo` decimal(10,2) DEFAULT NULL,
  `fecha_desde` datetime DEFAULT current_timestamp(),
  `fecha_hasta` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `productos_precios_historial_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos_precios_historial`
--

LOCK TABLES `productos_precios_historial` WRITE;
/*!40000 ALTER TABLE `productos_precios_historial` DISABLE KEYS */;
/*!40000 ALTER TABLE `productos_precios_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `rol` enum('admin','jefe','cajero') NOT NULL DEFAULT 'cajero',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES
(1,'ubilla','a6ace2cf5fb423550d66c67b83a0e91af70a522fa58c7ad4dab6b9f94c082656','admin'),
(2,'jefe','452b889d10df869834152618463e1c07ce88001a40c9fff5d4fdf300c65684c6','jefe'),
(3,'cajero','f976d9b6177d7595d3d45c3c927b0a813c21fac23ed9e5f938813925f6d5eb27','cajero'),
(4,'cajero','fea740101dbb727886b6908e7bc196a55054374c6827b41a60081c2525975b4d','cajero');
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `venta_items`
--

DROP TABLE IF EXISTS `venta_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `venta_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venta_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `costo_unitario` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `venta_id` (`venta_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `venta_items_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`),
  CONSTRAINT `venta_items_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `venta_items`
--

LOCK TABLES `venta_items` WRITE;
/*!40000 ALTER TABLE `venta_items` DISABLE KEYS */;
INSERT INTO `venta_items` VALUES
(1,3,1,1,2000.00,2000.00,0.00),
(2,3,1,1,2000.00,2000.00,0.00),
(3,3,1,1,2000.00,2000.00,0.00),
(4,3,1,1,2000.00,2000.00,0.00),
(5,5,1,1,2000.00,2000.00,0.00),
(6,6,1,1,2000.00,2000.00,0.00),
(7,8,1,1,2000.00,2000.00,0.00),
(8,9,1,1,2000.00,2000.00,0.00),
(9,9,1,1,2000.00,2000.00,0.00),
(10,15,1,1,2000.00,2000.00,0.00),
(11,17,1,1,2000.00,2000.00,0.00),
(12,25,1,5,2000.00,10000.00,0.00),
(13,36,1,1,2000.00,2000.00,0.00),
(14,40,1,2,2000.00,4000.00,0.00),
(15,41,1,2,2000.00,4000.00,0.00),
(16,43,1,1,2000.00,2000.00,0.00),
(17,44,1,1,2000.00,2000.00,0.00),
(18,45,1,1,2000.00,2000.00,0.00),
(19,47,1,1,2000.00,2000.00,0.00),
(20,53,1,1,2000.00,2000.00,0.00),
(21,54,1,1,2000.00,2000.00,0.00),
(22,54,1,1,2000.00,2000.00,0.00),
(23,56,1,1,2000.00,2000.00,0.00),
(24,57,1,2,2000.00,4000.00,0.00),
(25,58,1,1,2000.00,2000.00,0.00),
(26,60,1,1,2000.00,2000.00,0.00),
(27,60,1,1,2000.00,2000.00,0.00),
(28,61,1,1,2000.00,2000.00,0.00),
(29,68,1,1,2000.00,2000.00,0.00),
(30,69,1,1,2000.00,2000.00,0.00),
(31,69,1,1,2000.00,2000.00,0.00),
(32,71,1,1,2000.00,2000.00,0.00),
(33,72,1,1,2000.00,2000.00,0.00),
(34,74,1,2,2000.00,4000.00,0.00),
(35,78,1,1,2000.00,2000.00,0.00),
(36,80,1,1,2000.00,2000.00,0.00),
(37,84,1,1,2000.00,2000.00,0.00),
(38,86,1,4,2000.00,8000.00,0.00),
(39,91,1,1,2000.00,2000.00,0.00),
(40,93,1,2,2000.00,4000.00,0.00),
(41,96,1,2,2000.00,4000.00,0.00);
/*!40000 ALTER TABLE `venta_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ventas`
--

DROP TABLE IF EXISTS `ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ventas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fecha` datetime DEFAULT current_timestamp(),
  `cliente_id` int(11) DEFAULT NULL,
  `total` decimal(10,2) DEFAULT NULL,
  `tipo_comprobante` varchar(10) DEFAULT NULL,
  `estado` varchar(20) DEFAULT 'COMPLETADA',
  `ganancia` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas`
--

LOCK TABLES `ventas` WRITE;
/*!40000 ALTER TABLE `ventas` DISABLE KEYS */;
INSERT INTO `ventas` VALUES
(1,'2026-04-04 11:47:29',NULL,0.00,'TICKET','COMPLETADA',NULL),
(2,'2026-04-04 11:52:54',NULL,0.00,'TICKET','COMPLETADA',NULL),
(3,'2026-04-05 15:05:15',NULL,8000.00,'TICKET','COMPLETADA',NULL),
(4,'2026-04-05 15:05:51',NULL,0.00,'TICKET','COMPLETADA',NULL),
(5,'2026-04-05 15:14:54',NULL,2000.00,'TICKET','COMPLETADA',NULL),
(6,'2026-04-05 15:15:11',NULL,0.00,'TICKET','COMPLETADA',NULL),
(7,'2026-04-05 15:16:09',NULL,0.00,'TICKET','COMPLETADA',NULL),
(8,'2026-04-05 15:27:28',NULL,0.00,'TICKET','COMPLETADA',NULL),
(9,'2026-04-05 15:28:44',NULL,0.00,'TICKET','COMPLETADA',NULL),
(10,'2026-04-05 15:31:16',NULL,0.00,'TICKET','COMPLETADA',NULL),
(11,'2026-04-05 15:32:28',NULL,0.00,'TICKET','COMPLETADA',NULL),
(12,'2026-04-05 15:35:09',NULL,0.00,NULL,'PENDIENTE',NULL),
(13,'2026-04-05 15:35:43',NULL,0.00,'TICKET','COMPLETADA',NULL),
(14,'2026-04-05 15:39:53',NULL,0.00,'TICKET','COMPLETADA',NULL),
(15,'2026-04-05 15:52:08',NULL,0.00,'TICKET','COMPLETADA',NULL),
(16,'2026-04-05 15:58:59',NULL,0.00,'TICKET','COMPLETADA',NULL),
(17,'2026-04-05 16:01:21',NULL,0.00,'TICKET','COMPLETADA',NULL),
(18,'2026-04-05 16:08:41',NULL,0.00,NULL,'COMPLETADA',NULL),
(19,'2026-04-05 16:09:04',NULL,0.00,NULL,'COMPLETADA',NULL),
(20,'2026-04-05 16:16:36',NULL,0.00,NULL,'COMPLETADA',NULL),
(21,'2026-04-05 16:19:36',NULL,0.00,NULL,'COMPLETADA',NULL),
(22,'2026-04-05 16:23:51',NULL,0.00,NULL,'COMPLETADA',NULL),
(23,'2026-04-05 16:28:32',NULL,0.00,NULL,'COMPLETADA',NULL),
(24,'2026-04-05 16:31:18',NULL,0.00,NULL,'COMPLETADA',NULL),
(25,'2026-04-05 16:33:47',NULL,10000.00,NULL,'COMPLETADA',NULL),
(26,'2026-04-05 16:34:03',NULL,0.00,NULL,'COMPLETADA',NULL),
(27,'2026-04-05 17:18:17',NULL,0.00,NULL,'ABIERTA',NULL),
(28,'2026-04-05 17:19:00',NULL,0.00,NULL,'ABIERTA',NULL),
(29,'2026-04-05 17:20:22',NULL,0.00,NULL,'ABIERTA',NULL),
(30,'2026-04-05 17:27:07',NULL,0.00,NULL,'ABIERTA',NULL),
(31,'2026-04-05 17:30:23',NULL,0.00,NULL,'COMPLETADA',NULL),
(32,'2026-04-05 17:31:46',NULL,0.00,NULL,'COMPLETADA',NULL),
(33,'2026-04-05 17:32:12',NULL,0.00,NULL,'COMPLETADA',NULL),
(34,'2026-04-05 17:32:33',NULL,0.00,NULL,'COMPLETADA',NULL),
(35,'2026-04-05 17:33:24',NULL,0.00,NULL,'COMPLETADA',NULL),
(36,'2026-04-05 17:33:57',NULL,2000.00,NULL,'COMPLETADA',NULL),
(37,'2026-04-05 17:34:20',NULL,0.00,NULL,'COMPLETADA',NULL),
(38,'2026-04-05 17:35:28',NULL,0.00,NULL,'COMPLETADA',NULL),
(39,'2026-04-05 17:35:59',NULL,0.00,NULL,'COMPLETADA',NULL),
(40,'2026-04-05 17:56:00',NULL,4000.00,NULL,'COMPLETADA',NULL),
(41,'2026-04-05 18:00:53',NULL,4000.00,NULL,'COMPLETADA',NULL),
(42,'2026-04-05 18:01:02',NULL,0.00,NULL,'COMPLETADA',NULL),
(43,'2026-04-05 18:01:41',NULL,0.00,NULL,'COMPLETADA',NULL),
(44,'2026-04-05 18:02:19',NULL,0.00,NULL,'COMPLETADA',NULL),
(45,'2026-04-05 18:02:36',NULL,2000.00,NULL,'COMPLETADA',NULL),
(46,'2026-04-05 18:02:38',NULL,0.00,NULL,'COMPLETADA',NULL),
(47,'2026-04-05 18:06:05',NULL,0.00,NULL,'COMPLETADA',NULL),
(48,'2026-04-05 18:12:55',NULL,0.00,NULL,'COMPLETADA',NULL),
(49,'2026-04-05 18:19:40',NULL,0.00,NULL,'COMPLETADA',NULL),
(50,'2026-04-05 18:19:49',NULL,0.00,NULL,'COMPLETADA',NULL),
(51,'2026-04-05 18:19:58',NULL,0.00,NULL,'COMPLETADA',NULL),
(52,'2026-04-05 18:24:02',NULL,0.00,NULL,'COMPLETADA',NULL),
(53,'2026-04-05 18:36:22',NULL,0.00,NULL,'COMPLETADA',NULL),
(54,'2026-04-05 18:40:21',NULL,0.00,NULL,'COMPLETADA',NULL),
(55,'2026-04-05 18:41:50',NULL,0.00,NULL,'COMPLETADA',NULL),
(56,'2026-04-05 18:41:59',NULL,0.00,NULL,'COMPLETADA',NULL),
(57,'2026-04-05 18:42:11',NULL,0.00,NULL,'COMPLETADA',NULL),
(58,'2026-04-05 18:42:24',NULL,2000.00,NULL,'COMPLETADA',NULL),
(59,'2026-04-05 18:42:27',NULL,0.00,NULL,'COMPLETADA',NULL),
(60,'2026-04-05 18:43:29',NULL,0.00,NULL,'COMPLETADA',NULL),
(61,'2026-04-05 18:44:02',NULL,2000.00,NULL,'COMPLETADA',NULL),
(62,'2026-04-05 18:44:12',NULL,0.00,NULL,'COMPLETADA',NULL),
(63,'2026-04-06 18:39:45',NULL,0.00,NULL,'COMPLETADA',NULL),
(64,'2026-04-06 18:41:43',NULL,0.00,NULL,'COMPLETADA',NULL),
(65,'2026-04-06 18:43:45',NULL,0.00,NULL,'COMPLETADA',NULL),
(66,'2026-04-06 18:45:43',NULL,0.00,NULL,'COMPLETADA',NULL),
(67,'2026-04-06 18:47:40',NULL,0.00,NULL,'COMPLETADA',NULL),
(68,'2026-04-06 18:51:00',NULL,2000.00,NULL,'COMPLETADA',NULL),
(69,'2026-04-06 18:57:23',NULL,2000.00,NULL,'COMPLETADA',NULL),
(70,'2026-04-06 18:58:40',NULL,0.00,NULL,'COMPLETADA',NULL),
(71,'2026-04-06 19:01:04',NULL,2000.00,NULL,'COMPLETADA',NULL),
(72,'2026-04-06 19:03:43',NULL,2000.00,NULL,'COMPLETADA',NULL),
(73,'2026-04-06 19:03:53',NULL,0.00,NULL,'COMPLETADA',NULL),
(74,'2026-04-06 19:06:33',NULL,4000.00,NULL,'COMPLETADA',NULL),
(75,'2026-04-06 19:06:48',NULL,0.00,NULL,'COMPLETADA',NULL),
(76,'2026-04-06 19:08:35',NULL,0.00,NULL,'COMPLETADA',NULL),
(77,'2026-04-06 19:09:16',NULL,0.00,NULL,'COMPLETADA',NULL),
(78,'2026-04-06 19:11:29',NULL,2000.00,NULL,'COMPLETADA',NULL),
(79,'2026-04-06 19:11:49',NULL,0.00,NULL,'COMPLETADA',NULL),
(80,'2026-04-06 19:17:51',NULL,2000.00,NULL,'COMPLETADA',NULL),
(81,'2026-04-06 19:18:06',NULL,0.00,NULL,'COMPLETADA',NULL),
(82,'2026-04-06 19:30:45',NULL,0.00,NULL,'COMPLETADA',0.00),
(83,'2026-04-06 19:33:19',NULL,0.00,NULL,'COMPLETADA',0.00),
(84,'2026-04-06 19:35:13',NULL,2000.00,NULL,'COMPLETADA',2000.00),
(85,'2026-04-06 19:35:25',NULL,0.00,NULL,'COMPLETADA',0.00),
(86,'2026-04-06 19:37:47',NULL,8000.00,NULL,'COMPLETADA',8000.00),
(87,'2026-04-06 19:38:01',NULL,0.00,NULL,'COMPLETADA',0.00),
(88,'2026-04-06 21:00:13',NULL,0.00,NULL,'COMPLETADA',0.00),
(89,'2026-04-06 21:03:38',NULL,0.00,NULL,'COMPLETADA',0.00),
(90,'2026-04-06 21:08:41',NULL,0.00,NULL,'COMPLETADA',0.00),
(91,'2026-04-06 21:14:07',NULL,2000.00,NULL,'COMPLETADA',2000.00),
(92,'2026-04-06 21:14:25',NULL,0.00,NULL,'COMPLETADA',0.00),
(93,'2026-04-06 21:23:32',NULL,4000.00,NULL,'COMPLETADA',4000.00),
(94,'2026-04-06 21:24:20',NULL,0.00,NULL,'COMPLETADA',0.00),
(95,'2026-04-06 21:24:49',NULL,0.00,NULL,'COMPLETADA',0.00),
(96,'2026-04-06 21:27:02',NULL,4000.00,NULL,'COMPLETADA',4000.00),
(97,'2026-04-06 21:27:38',NULL,0.00,NULL,'COMPLETADA',0.00),
(98,'2026-04-06 21:29:12',NULL,0.00,NULL,'COMPLETADA',0.00),
(99,'2026-04-06 21:36:37',NULL,0.00,NULL,'COMPLETADA',0.00),
(100,'2026-04-06 21:54:28',NULL,0.00,NULL,'COMPLETADA',0.00),
(101,'2026-04-06 22:07:55',NULL,0.00,NULL,'COMPLETADA',0.00),
(102,'2026-04-06 22:10:22',NULL,0.00,NULL,'COMPLETADA',0.00),
(103,'2026-04-06 22:17:12',NULL,0.00,NULL,'COMPLETADA',0.00),
(104,'2026-04-06 22:17:14',NULL,0.00,NULL,'COMPLETADA',0.00),
(105,'2026-04-06 22:18:18',NULL,0.00,NULL,'COMPLETADA',0.00),
(106,'2026-04-06 22:18:25',NULL,0.00,NULL,'COMPLETADA',0.00),
(107,'2026-04-06 22:18:36',NULL,0.00,NULL,'COMPLETADA',0.00),
(108,'2026-04-06 22:20:37',NULL,0.00,NULL,'COMPLETADA',0.00),
(109,'2026-04-06 22:20:39',NULL,0.00,NULL,'COMPLETADA',0.00),
(110,'2026-04-06 22:21:01',NULL,0.00,NULL,'COMPLETADA',0.00),
(111,'2026-04-06 22:21:45',NULL,0.00,NULL,'COMPLETADA',0.00),
(112,'2026-04-06 22:23:08',NULL,0.00,NULL,'COMPLETADA',0.00),
(113,'2026-04-06 22:26:19',NULL,0.00,NULL,'COMPLETADA',0.00);
/*!40000 ALTER TABLE `ventas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'facturacion'
--

--
-- Dumping routines for database 'facturacion'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-06 22:28:27
