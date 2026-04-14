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
  `empresa_id` int(11) NOT NULL,
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
-- Table structure for table `combo_productos`
--

DROP TABLE IF EXISTS `combo_productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `combo_productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `regla_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `cantidad_requerida` int(11) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `regla_id` (`regla_id`),
  CONSTRAINT `combo_productos_ibfk_1` FOREIGN KEY (`regla_id`) REFERENCES `promociones_reglas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `combo_productos`
--

LOCK TABLES `combo_productos` WRITE;
/*!40000 ALTER TABLE `combo_productos` DISABLE KEYS */;
/*!40000 ALTER TABLE `combo_productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comprobante_afip`
--

DROP TABLE IF EXISTS `comprobante_afip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comprobante_afip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venta_id` int(11) NOT NULL,
  `tipo_cbte` int(11) DEFAULT NULL,
  `punto_vta` int(11) DEFAULT NULL,
  `nro_cbte` int(11) DEFAULT NULL,
  `cae` varchar(20) DEFAULT NULL,
  `fecha_vto_cae` date DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL,
  `response_afip` text DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `entorno` varchar(10) DEFAULT 'DEV',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comprobante_afip`
--

LOCK TABLES `comprobante_afip` WRITE;
/*!40000 ALTER TABLE `comprobante_afip` DISABLE KEYS */;
INSERT INTO `comprobante_afip` VALUES
(1,122,11,1,222,'74123456789012','2026-12-31','APROBADO',NULL,'2026-04-08 19:36:46','DEV'),
(2,124,11,1,224,'74123456789012','2026-12-31','APROBADO',NULL,'2026-04-08 19:49:38','DEV');
/*!40000 ALTER TABLE `comprobante_afip` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `config_pagos`
--

DROP TABLE IF EXISTS `config_pagos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `config_pagos` (
  `id` int(11) NOT NULL DEFAULT 1,
  `mp_access_token` text DEFAULT NULL,
  `mp_user_id` varchar(50) DEFAULT NULL,
  `mp_external_id` varchar(50) DEFAULT 'CAJA_01',
  `modo_sandbox` tinyint(4) DEFAULT 1,
  `pw_api_key` text DEFAULT NULL,
  `pw_merchant_id` varchar(50) DEFAULT NULL,
  `empresa_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config_pagos`
--

LOCK TABLES `config_pagos` WRITE;
/*!40000 ALTER TABLE `config_pagos` DISABLE KEYS */;
INSERT INTO `config_pagos` VALUES
(1,'TEST-TOKEN-123','','CAJA_01',1,'','',0);
/*!40000 ALTER TABLE `config_pagos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cupones`
--

DROP TABLE IF EXISTS `cupones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cupones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_qr` varchar(50) DEFAULT NULL,
  `descuento_porcentaje` decimal(5,2) DEFAULT NULL,
  `usos_maximos` int(11) DEFAULT 1,
  `usos_actuales` int(11) DEFAULT 0,
  `fecha_expiracion` date DEFAULT NULL,
  `activo` tinyint(4) DEFAULT 1,
  `fecha_inicio` date DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_qr` (`codigo_qr`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cupones`
--

LOCK TABLES `cupones` WRITE;
/*!40000 ALTER TABLE `cupones` DISABLE KEYS */;
INSERT INTO `cupones` VALUES
(1,'22',5.00,1,0,'2026-04-08',1,NULL,NULL,1);
/*!40000 ALTER TABLE `cupones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `empresas`
--

DROP TABLE IF EXISTS `empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empresas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `cuit` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `empresas`
--

LOCK TABLES `empresas` WRITE;
/*!40000 ALTER TABLE `empresas` DISABLE KEYS */;
INSERT INTO `empresas` VALUES
(1,'BOTILLERIA CURICO','20-258472811',1),
(2,'TEST','3423542456',1);
/*!40000 ALTER TABLE `empresas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `finanzas`
--

DROP TABLE IF EXISTS `finanzas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `finanzas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) DEFAULT NULL,
  `tipo` enum('INGRESO','GASTO') NOT NULL,
  `categoria` varchar(50) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `metodo_pago` enum('EFECTIVO','TRANSFERENCIA','TARJETA') DEFAULT 'EFECTIVO',
  `fecha` date DEFAULT curdate(),
  `hora` time DEFAULT curtime(),
  `usuario_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empresa_id` (`empresa_id`),
  CONSTRAINT `finanzas_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finanzas`
--

LOCK TABLES `finanzas` WRITE;
/*!40000 ALTER TABLE `finanzas` DISABLE KEYS */;
INSERT INTO `finanzas` VALUES
(1,1,'INGRESO','Ventas',174.00,'Venta POS #149','EFECTIVO','2026-04-13','20:50:06',1);
/*!40000 ALTER TABLE `finanzas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimientos_caja`
--

DROP TABLE IF EXISTS `movimientos_caja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimientos_caja` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) DEFAULT NULL,
  `tipo` enum('INGRESO','GASTO') DEFAULT NULL,
  `categoria` varchar(50) DEFAULT NULL,
  `monto` decimal(10,2) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha` timestamp NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empresa_id` (`empresa_id`),
  CONSTRAINT `movimientos_caja_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
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
  `ingresos_brutos` decimal(5,2) DEFAULT 0.00,
  `ganancia_sugerida` decimal(5,2) DEFAULT 0.00,
  `cuit` varchar(20) DEFAULT NULL,
  `direccion` varchar(100) DEFAULT NULL,
  `condicion_iva` varchar(50) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `ruta_tickets` varchar(255) DEFAULT '',
  `empresa_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nombre_negocio`
--

LOCK TABLES `nombre_negocio` WRITE;
/*!40000 ALTER TABLE `nombre_negocio` DISABLE KEYS */;
INSERT INTO `nombre_negocio` VALUES
(1,'BOTILLERIA CURICO','PUNTO DE VENTA','$',21.00,3.00,50.00,'20-258472811','INDEPENDENCIA 3100','No Inscripto','1160281010','/home/pi/facturacion',0);
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
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `venta_id` (`venta_id`),
  KEY `idx_pagos_fecha` (`fecha`),
  KEY `idx_pagos_estado` (`estado`),
  CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos`
--

LOCK TABLES `pagos` WRITE;
/*!40000 ALTER TABLE `pagos` DISABLE KEYS */;
INSERT INTO `pagos` VALUES
(1,3,'efectivo',8000.00,'2026-04-05 15:05:51',0.00,0.00,'pendiente',1),
(2,5,'efectivo',2000.00,'2026-04-05 15:15:11',0.00,0.00,'pendiente',1),
(3,25,'EFECTIVO',10000.00,'2026-04-05 16:34:03',0.00,0.00,'pendiente',1),
(4,36,'EFECTIVO',2000.00,'2026-04-05 17:34:20',0.00,0.00,'pendiente',1),
(5,40,'EFECTIVO',4000.00,'2026-04-05 17:56:13',0.00,0.00,'pendiente',1),
(6,41,'EFECTIVO',4000.00,'2026-04-05 18:01:02',0.00,0.00,'pendiente',1),
(7,45,'EFECTIVO',2000.00,'2026-04-05 18:02:38',0.00,0.00,'pendiente',1),
(8,58,'EFECTIVO',2000.00,'2026-04-05 18:42:27',0.00,0.00,'pendiente',1),
(9,61,'EFECTIVO',2000.00,'2026-04-05 18:44:12',0.00,0.00,'pendiente',1),
(10,69,'EFECTIVO',2000.00,'2026-04-06 18:57:41',0.00,0.00,'pendiente',1),
(11,69,'EFECTIVO',2000.00,'2026-04-06 18:57:52',0.00,0.00,'pendiente',1),
(12,71,'EFECTIVO',2000.00,'2026-04-06 19:01:16',0.00,0.00,'pendiente',1),
(13,72,'EFECTIVO',2000.00,'2026-04-06 19:03:53',0.00,0.00,'pendiente',1),
(14,74,'EFECTIVO',4000.00,'2026-04-06 19:06:45',0.00,0.00,'pendiente',1),
(15,78,'EFECTIVO',2000.00,'2026-04-06 19:11:46',0.00,0.00,'pendiente',1),
(16,80,'EFECTIVO',2000.00,'2026-04-06 19:18:03',0.00,0.00,'pendiente',1),
(17,84,'EFECTIVO',2000.00,'2026-04-06 19:35:22',0.00,0.00,'completado',1),
(18,86,'EFECTIVO',8000.00,'2026-04-06 19:37:58',0.00,0.00,'completado',1),
(19,91,'EFECTIVO',2000.00,'2026-04-06 21:14:21',0.00,0.00,'completado',1),
(20,93,'EFECTIVO',4000.00,'2026-04-06 21:24:14',0.00,0.00,'completado',1),
(21,96,'EFECTIVO',4000.00,'2026-04-06 21:27:35',0.00,0.00,'completado',1),
(22,NULL,'EFECTIVO',2000.00,'2026-04-08 15:52:59',0.00,2000.00,'completado',1),
(23,NULL,'EFECTIVO',2000.00,'2026-04-08 15:55:47',0.00,2000.00,'completado',1),
(24,NULL,'EFECTIVO',2000.00,'2026-04-08 15:58:10',18000.00,20000.00,'completado',1),
(25,NULL,'TARJETA',2000.00,'2026-04-08 16:02:38',0.00,2000.00,'completado',1),
(26,NULL,'TARJETA',2000.00,'2026-04-08 16:14:14',0.00,2000.00,'completado',1),
(27,NULL,'TARJETA',2000.00,'2026-04-08 16:16:59',0.00,2000.00,'completado',1),
(28,NULL,'TARJETA',2000.00,'2026-04-08 16:21:14',0.00,2000.00,'completado',1),
(29,NULL,'TARJETA',2000.00,'2026-04-08 16:21:48',0.00,2000.00,'completado',1),
(30,NULL,'EFECTIVO',2000.00,'2026-04-08 16:27:13',0.00,2000.00,'completado',1),
(31,114,'TARJETA',2000.00,'2026-04-08 16:30:18',0.00,2000.00,'completado',1),
(32,115,'TRANSFERENCIA',6000.00,'2026-04-08 16:31:52',0.00,6000.00,'completado',1),
(33,116,'TARJETA',4000.00,'2026-04-08 16:33:01',0.00,4000.00,'completado',1),
(34,118,'TARJETA',2000.00,'2026-04-08 16:33:37',0.00,2000.00,'completado',1),
(35,120,'TARJETA',2000.00,'2026-04-08 16:34:33',0.00,2000.00,'completado',1),
(36,122,'QR',2000.00,'2026-04-08 16:36:46',0.00,2000.00,'completado',1),
(37,124,'QR',2000.00,'2026-04-08 16:49:38',0.00,2000.00,'completado',1),
(38,149,'EFECTIVO',174.00,'2026-04-13 20:50:06',9826.00,10000.00,'completado',1);
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
  `ultimo_usuario_id` int(11) DEFAULT 1,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `categoria_id` (`categoria_id`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES
(1,'123','Coca-Cola','None',2000.00,38,1,NULL,'2026-04-04 11:47:18','imagenes/123.png',1200.00,1,1),
(2,'22222','juan','',1860.00,100,1,NULL,'2026-04-06 19:41:44',NULL,1000.00,1,1),
(3,'62345','juanamnuel','',372.00,10,1,NULL,'2026-04-08 12:10:07',NULL,200.00,1,1),
(4,'2542312','axelito','',174.00,5,1,NULL,'2026-04-08 21:41:21',NULL,100.00,1,1),
(6,'12334','juan','',174.00,9,1,NULL,'2026-04-13 18:22:52',NULL,100.00,1,1);
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
-- Table structure for table `promociones_combos`
--

DROP TABLE IF EXISTS `promociones_combos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promociones_combos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_promo` varchar(100) DEFAULT NULL,
  `productos_ids` varchar(255) DEFAULT NULL,
  `descuento_porcentaje` decimal(5,2) DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `activo` tinyint(4) DEFAULT 1,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promociones_combos`
--

LOCK TABLES `promociones_combos` WRITE;
/*!40000 ALTER TABLE `promociones_combos` DISABLE KEYS */;
INSERT INTO `promociones_combos` VALUES
(1,'verano promo','1,3',10.00,NULL,1,1);
/*!40000 ALTER TABLE `promociones_combos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promociones_reglas`
--

DROP TABLE IF EXISTS `promociones_reglas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promociones_reglas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) DEFAULT NULL,
  `tipo` enum('COMBO','VOLUMEN') DEFAULT NULL,
  `precio_fijo` decimal(10,2) DEFAULT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `activo` tinyint(4) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promociones_reglas`
--

LOCK TABLES `promociones_reglas` WRITE;
/*!40000 ALTER TABLE `promociones_reglas` DISABLE KEYS */;
/*!40000 ALTER TABLE `promociones_reglas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `promociones_volumen`
--

DROP TABLE IF EXISTS `promociones_volumen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `promociones_volumen` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) DEFAULT NULL,
  `cantidad_minima` int(11) DEFAULT NULL,
  `descuento_porcentaje` decimal(5,2) DEFAULT NULL,
  `activo` tinyint(4) DEFAULT 1,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `promociones_volumen_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `promociones_volumen`
--

LOCK TABLES `promociones_volumen` WRITE;
/*!40000 ALTER TABLE `promociones_volumen` DISABLE KEYS */;
INSERT INTO `promociones_volumen` VALUES
(1,1,6,10.00,1,1),
(2,1,6,10.00,1,0);
/*!40000 ALTER TABLE `promociones_volumen` ENABLE KEYS */;
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
  `empresa_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES
(1,'ubilla','a6ace2cf5fb423550d66c67b83a0e91af70a522fa58c7ad4dab6b9f94c082656','admin',0),
(2,'jefe','452b889d10df869834152618463e1c07ce88001a40c9fff5d4fdf300c65684c6','jefe',0),
(3,'cajero','f976d9b6177d7595d3d45c3c927b0a813c21fac23ed9e5f938813925f6d5eb27','cajero',0),
(4,'cajero','fea740101dbb727886b6908e7bc196a55054374c6827b41a60081c2525975b4d','cajero',0);
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
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
(41,96,1,2,2000.00,4000.00,0.00),
(42,NULL,1,1,2000.00,2000.00,0.00),
(43,NULL,1,1,2000.00,2000.00,0.00),
(44,NULL,1,1,2000.00,2000.00,0.00),
(45,NULL,1,1,2000.00,2000.00,0.00),
(46,NULL,1,1,2000.00,2000.00,0.00),
(47,NULL,1,1,2000.00,2000.00,0.00),
(48,NULL,1,1,2000.00,2000.00,0.00),
(49,NULL,1,1,2000.00,2000.00,0.00),
(50,NULL,1,1,2000.00,2000.00,0.00),
(51,114,1,1,2000.00,2000.00,0.00),
(52,115,1,3,2000.00,6000.00,0.00),
(53,116,1,2,2000.00,4000.00,0.00),
(54,118,1,1,2000.00,2000.00,0.00),
(55,120,1,1,2000.00,2000.00,0.00),
(56,122,1,1,2000.00,2000.00,0.00),
(57,124,1,1,2000.00,2000.00,0.00),
(58,132,1,1,2000.00,2000.00,0.00),
(59,147,6,1,174.00,174.00,0.00),
(60,147,6,1,174.00,174.00,0.00),
(61,147,6,1,174.00,174.00,0.00),
(62,147,6,1,174.00,174.00,0.00),
(63,148,6,1,174.00,174.00,0.00),
(64,148,6,1,174.00,174.00,0.00),
(65,149,6,1,174.00,174.00,100.00);
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
  `usuario_id` int(11) DEFAULT 1,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  CONSTRAINT `ventas_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=157 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas`
--

LOCK TABLES `ventas` WRITE;
/*!40000 ALTER TABLE `ventas` DISABLE KEYS */;
INSERT INTO `ventas` VALUES
(1,'2026-04-04 11:47:29',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(2,'2026-04-04 11:52:54',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(3,'2026-04-05 15:05:15',NULL,8000.00,'TICKET','COMPLETADA',NULL,1,0),
(4,'2026-04-05 15:05:51',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(5,'2026-04-05 15:14:54',NULL,2000.00,'TICKET','COMPLETADA',NULL,1,0),
(6,'2026-04-05 15:15:11',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(7,'2026-04-05 15:16:09',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(8,'2026-04-05 15:27:28',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(9,'2026-04-05 15:28:44',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(10,'2026-04-05 15:31:16',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(11,'2026-04-05 15:32:28',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(12,'2026-04-05 15:35:09',NULL,0.00,NULL,'PENDIENTE',NULL,1,0),
(13,'2026-04-05 15:35:43',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(14,'2026-04-05 15:39:53',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(15,'2026-04-05 15:52:08',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(16,'2026-04-05 15:58:59',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(17,'2026-04-05 16:01:21',NULL,0.00,'TICKET','COMPLETADA',NULL,1,0),
(18,'2026-04-05 16:08:41',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(19,'2026-04-05 16:09:04',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(20,'2026-04-05 16:16:36',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(21,'2026-04-05 16:19:36',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(22,'2026-04-05 16:23:51',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(23,'2026-04-05 16:28:32',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(24,'2026-04-05 16:31:18',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(25,'2026-04-05 16:33:47',NULL,10000.00,NULL,'COMPLETADA',NULL,1,0),
(26,'2026-04-05 16:34:03',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(27,'2026-04-05 17:18:17',NULL,0.00,NULL,'ABIERTA',NULL,1,0),
(28,'2026-04-05 17:19:00',NULL,0.00,NULL,'ABIERTA',NULL,1,0),
(29,'2026-04-05 17:20:22',NULL,0.00,NULL,'ABIERTA',NULL,1,0),
(30,'2026-04-05 17:27:07',NULL,0.00,NULL,'ABIERTA',NULL,1,0),
(31,'2026-04-05 17:30:23',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(32,'2026-04-05 17:31:46',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(33,'2026-04-05 17:32:12',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(34,'2026-04-05 17:32:33',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(35,'2026-04-05 17:33:24',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(36,'2026-04-05 17:33:57',NULL,2000.00,NULL,'COMPLETADA',NULL,1,0),
(37,'2026-04-05 17:34:20',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(38,'2026-04-05 17:35:28',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(39,'2026-04-05 17:35:59',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(40,'2026-04-05 17:56:00',NULL,4000.00,NULL,'COMPLETADA',NULL,1,0),
(41,'2026-04-05 18:00:53',NULL,4000.00,NULL,'COMPLETADA',NULL,1,0),
(42,'2026-04-05 18:01:02',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(43,'2026-04-05 18:01:41',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(44,'2026-04-05 18:02:19',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(45,'2026-04-05 18:02:36',NULL,2000.00,NULL,'COMPLETADA',NULL,1,0),
(46,'2026-04-05 18:02:38',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(47,'2026-04-05 18:06:05',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(48,'2026-04-05 18:12:55',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(49,'2026-04-05 18:19:40',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(50,'2026-04-05 18:19:49',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(51,'2026-04-05 18:19:58',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(52,'2026-04-05 18:24:02',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(53,'2026-04-05 18:36:22',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(54,'2026-04-05 18:40:21',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(55,'2026-04-05 18:41:50',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(56,'2026-04-05 18:41:59',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(57,'2026-04-05 18:42:11',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(58,'2026-04-05 18:42:24',NULL,2000.00,NULL,'COMPLETADA',NULL,1,0),
(59,'2026-04-05 18:42:27',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(60,'2026-04-05 18:43:29',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(61,'2026-04-05 18:44:02',NULL,2000.00,NULL,'COMPLETADA',NULL,1,0),
(62,'2026-04-05 18:44:12',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(63,'2026-04-06 18:39:45',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(64,'2026-04-06 18:41:43',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(65,'2026-04-06 18:43:45',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(66,'2026-04-06 18:45:43',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(67,'2026-04-06 18:47:40',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(68,'2026-04-06 18:51:00',NULL,2000.00,NULL,'COMPLETADA',NULL,1,0),
(69,'2026-04-06 18:57:23',NULL,2000.00,NULL,'COMPLETADA',NULL,1,0),
(70,'2026-04-06 18:58:40',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(71,'2026-04-06 19:01:04',NULL,2000.00,NULL,'COMPLETADA',NULL,1,0),
(72,'2026-04-06 19:03:43',NULL,2000.00,NULL,'COMPLETADA',NULL,1,0),
(73,'2026-04-06 19:03:53',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(74,'2026-04-06 19:06:33',NULL,4000.00,NULL,'COMPLETADA',NULL,1,0),
(75,'2026-04-06 19:06:48',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(76,'2026-04-06 19:08:35',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(77,'2026-04-06 19:09:16',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(78,'2026-04-06 19:11:29',NULL,2000.00,NULL,'COMPLETADA',NULL,1,0),
(79,'2026-04-06 19:11:49',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(80,'2026-04-06 19:17:51',NULL,2000.00,NULL,'COMPLETADA',NULL,1,0),
(81,'2026-04-06 19:18:06',NULL,0.00,NULL,'COMPLETADA',NULL,1,0),
(82,'2026-04-06 19:30:45',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(83,'2026-04-06 19:33:19',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(84,'2026-04-06 19:35:13',NULL,2000.00,NULL,'COMPLETADA',2000.00,1,0),
(85,'2026-04-06 19:35:25',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(86,'2026-04-06 19:37:47',NULL,8000.00,NULL,'COMPLETADA',8000.00,1,0),
(87,'2026-04-06 19:38:01',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(88,'2026-04-06 21:00:13',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(89,'2026-04-06 21:03:38',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(90,'2026-04-06 21:08:41',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(91,'2026-04-06 21:14:07',NULL,2000.00,NULL,'COMPLETADA',2000.00,1,0),
(92,'2026-04-06 21:14:25',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(93,'2026-04-06 21:23:32',NULL,4000.00,NULL,'COMPLETADA',4000.00,1,0),
(94,'2026-04-06 21:24:20',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(95,'2026-04-06 21:24:49',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(96,'2026-04-06 21:27:02',NULL,4000.00,NULL,'COMPLETADA',4000.00,1,0),
(97,'2026-04-06 21:27:38',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(98,'2026-04-06 21:29:12',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(99,'2026-04-06 21:36:37',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(100,'2026-04-06 21:54:28',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(101,'2026-04-06 22:07:55',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(102,'2026-04-06 22:10:22',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(103,'2026-04-06 22:17:12',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(104,'2026-04-06 22:17:14',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(105,'2026-04-06 22:18:18',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(106,'2026-04-06 22:18:25',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(107,'2026-04-06 22:18:36',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(108,'2026-04-06 22:20:37',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(109,'2026-04-06 22:20:39',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(110,'2026-04-06 22:21:01',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(111,'2026-04-06 22:21:45',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(112,'2026-04-06 22:23:08',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(113,'2026-04-06 22:26:19',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(114,'2026-04-08 16:30:18',NULL,2000.00,NULL,'COMPLETADA',2000.00,NULL,0),
(115,'2026-04-08 16:30:20',NULL,6000.00,NULL,'COMPLETADA',6000.00,NULL,0),
(116,'2026-04-08 16:31:53',NULL,4000.00,NULL,'COMPLETADA',4000.00,NULL,0),
(117,'2026-04-08 16:33:02',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,0),
(118,'2026-04-08 16:33:32',NULL,2000.00,NULL,'COMPLETADA',2000.00,NULL,0),
(119,'2026-04-08 16:33:39',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,0),
(120,'2026-04-08 16:34:27',NULL,2000.00,NULL,'COMPLETADA',2000.00,NULL,0),
(121,'2026-04-08 16:34:34',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,0),
(122,'2026-04-08 16:36:41',NULL,2000.00,NULL,'COMPLETADA',2000.00,NULL,0),
(123,'2026-04-08 16:36:47',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,0),
(124,'2026-04-08 16:49:33',NULL,2000.00,NULL,'COMPLETADA',2000.00,NULL,0),
(125,'2026-04-08 16:49:40',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,0),
(126,'2026-04-08 16:55:21',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,0),
(127,'2026-04-08 17:39:11',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,0),
(128,'2026-04-08 18:14:35',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,0),
(129,'2026-04-08 18:15:41',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,0),
(130,'2026-04-08 20:06:05',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(131,'2026-04-08 20:11:20',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(132,'2026-04-08 20:16:47',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(133,'2026-04-08 20:21:07',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(134,'2026-04-08 20:53:26',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(135,'2026-04-08 20:53:35',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(136,'2026-04-08 21:02:02',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(137,'2026-04-08 21:37:46',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(138,'2026-04-09 15:58:50',NULL,0.00,NULL,'COMPLETADA',0.00,1,0),
(139,'2026-04-13 18:10:43',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(140,'2026-04-13 18:10:56',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(141,'2026-04-13 18:15:18',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(142,'2026-04-13 18:18:26',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(143,'2026-04-13 18:18:53',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(144,'2026-04-13 18:18:59',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(145,'2026-04-13 18:22:18',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(146,'2026-04-13 18:22:58',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(147,'2026-04-13 18:29:22',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(148,'2026-04-13 20:32:59',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(149,'2026-04-13 20:49:41',NULL,174.00,NULL,'COMPLETADA',74.00,1,1),
(150,'2026-04-13 20:50:11',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(151,'2026-04-13 20:50:42',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(152,'2026-04-13 20:57:45',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(153,'2026-04-13 21:05:53',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(154,'2026-04-13 21:09:16',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(155,'2026-04-13 21:10:03',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(156,'2026-04-13 21:33:05',NULL,0.00,NULL,'COMPLETADA',0.00,1,1);
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

-- Dump completed on 2026-04-13 21:36:13
