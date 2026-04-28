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
-- Table structure for table `alertas_comportamiento`
--

DROP TABLE IF EXISTS `alertas_comportamiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alertas_comportamiento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `evento_id` int(11) DEFAULT NULL,
  `tipo_alerta` varchar(50) DEFAULT NULL,
  `mensaje` text DEFAULT NULL,
  `notificada` tinyint(1) DEFAULT 0,
  `fecha_notificacion` timestamp NULL DEFAULT NULL,
  `empresa_id` int(11) DEFAULT NULL,
  `activa` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_evento` (`evento_id`),
  KEY `idx_empresa_notificada` (`empresa_id`,`notificada`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alertas_comportamiento`
--

LOCK TABLES `alertas_comportamiento` WRITE;
/*!40000 ALTER TABLE `alertas_comportamiento` DISABLE KEYS */;
/*!40000 ALTER TABLE `alertas_comportamiento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `alertas_seguridad`
--

DROP TABLE IF EXISTS `alertas_seguridad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alertas_seguridad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `persona_riesgo_id` int(11) NOT NULL,
  `camara_id` int(11) NOT NULL,
  `fecha` timestamp NULL DEFAULT current_timestamp(),
  `confianza` decimal(3,2) DEFAULT 0.00,
  `estado` enum('ACTIVA','ATENDIDA','FALSA') DEFAULT 'ACTIVA',
  `acciones_tomadas` text DEFAULT NULL,
  `notificaciones_enviadas` text DEFAULT NULL COMMENT 'JSON con canales notificados',
  `empresa_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_persona_fecha` (`persona_riesgo_id`,`fecha`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alertas_seguridad`
--

LOCK TABLES `alertas_seguridad` WRITE;
/*!40000 ALTER TABLE `alertas_seguridad` DISABLE KEYS */;
/*!40000 ALTER TABLE `alertas_seguridad` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asiento_detalles`
--

DROP TABLE IF EXISTS `asiento_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asiento_detalles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asiento_id` int(11) NOT NULL,
  `cuenta_id` int(11) NOT NULL,
  `debe` decimal(15,2) DEFAULT 0.00,
  `haber` decimal(15,2) DEFAULT 0.00,
  `descripcion` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_asiento_detalles_asiento` (`asiento_id`),
  KEY `idx_asiento_detalles_cuenta` (`cuenta_id`),
  CONSTRAINT `asiento_detalles_ibfk_1` FOREIGN KEY (`asiento_id`) REFERENCES `asientos_contables` (`id`) ON DELETE CASCADE,
  CONSTRAINT `asiento_detalles_ibfk_2` FOREIGN KEY (`cuenta_id`) REFERENCES `plan_cuentas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=689 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asiento_detalles`
--

LOCK TABLES `asiento_detalles` WRITE;
/*!40000 ALTER TABLE `asiento_detalles` DISABLE KEYS */;
INSERT INTO `asiento_detalles` VALUES
(349,89,1,8000.00,0.00,'Venta de contado'),
(350,89,25,0.00,6611.57,'Ventas del período'),
(351,89,29,4800.00,0.00,'Costo de mercaderías vendidas'),
(352,89,18,0.00,1388.43,'IVA 21% ventas'),
(353,90,1,2000.00,0.00,'Venta de contado'),
(354,90,25,0.00,1652.89,'Ventas del período'),
(355,90,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(356,90,18,0.00,347.11,'IVA 21% ventas'),
(357,91,1,2000.00,0.00,'Venta de contado'),
(358,91,25,0.00,1652.89,'Ventas del período'),
(359,91,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(360,91,18,0.00,347.11,'IVA 21% ventas'),
(361,92,1,2000.00,0.00,'Venta de contado'),
(362,92,25,0.00,1652.89,'Ventas del período'),
(363,92,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(364,92,18,0.00,347.11,'IVA 21% ventas'),
(365,93,1,4000.00,0.00,'Venta de contado'),
(366,93,25,0.00,3305.79,'Ventas del período'),
(367,93,29,2400.00,0.00,'Costo de mercaderías vendidas'),
(368,93,18,0.00,694.21,'IVA 21% ventas'),
(369,94,1,2000.00,0.00,'Venta de contado'),
(370,94,25,0.00,1652.89,'Ventas del período'),
(371,94,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(372,94,18,0.00,347.11,'IVA 21% ventas'),
(373,95,1,2000.00,0.00,'Venta de contado'),
(374,95,25,0.00,1652.89,'Ventas del período'),
(375,95,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(376,95,18,0.00,347.11,'IVA 21% ventas'),
(377,96,1,10000.00,0.00,'Venta de contado'),
(378,96,25,0.00,8264.46,'Ventas del período'),
(379,96,29,6000.00,0.00,'Costo de mercaderías vendidas'),
(380,96,18,0.00,1735.54,'IVA 21% ventas'),
(381,97,1,2000.00,0.00,'Venta de contado'),
(382,97,25,0.00,1652.89,'Ventas del período'),
(383,97,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(384,97,18,0.00,347.11,'IVA 21% ventas'),
(385,98,1,4000.00,0.00,'Venta de contado'),
(386,98,25,0.00,3305.79,'Ventas del período'),
(387,98,29,2400.00,0.00,'Costo de mercaderías vendidas'),
(388,98,18,0.00,694.21,'IVA 21% ventas'),
(389,99,1,4000.00,0.00,'Venta de contado'),
(390,99,25,0.00,3305.79,'Ventas del período'),
(391,99,29,2400.00,0.00,'Costo de mercaderías vendidas'),
(392,99,18,0.00,694.21,'IVA 21% ventas'),
(393,100,1,2000.00,0.00,'Venta de contado'),
(394,100,25,0.00,1652.89,'Ventas del período'),
(395,100,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(396,100,18,0.00,347.11,'IVA 21% ventas'),
(397,101,1,2000.00,0.00,'Venta de contado'),
(398,101,25,0.00,1652.89,'Ventas del período'),
(399,101,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(400,101,18,0.00,347.11,'IVA 21% ventas'),
(401,102,1,2000.00,0.00,'Venta de contado'),
(402,102,25,0.00,1652.89,'Ventas del período'),
(403,102,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(404,102,18,0.00,347.11,'IVA 21% ventas'),
(405,103,1,2000.00,0.00,'Venta de contado'),
(406,103,25,0.00,1652.89,'Ventas del período'),
(407,103,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(408,103,18,0.00,347.11,'IVA 21% ventas'),
(409,104,1,2000.00,0.00,'Venta de contado'),
(410,104,25,0.00,1652.89,'Ventas del período'),
(411,104,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(412,104,18,0.00,347.11,'IVA 21% ventas'),
(413,105,1,4000.00,0.00,'Venta de contado'),
(414,105,25,0.00,3305.79,'Ventas del período'),
(415,105,29,2400.00,0.00,'Costo de mercaderías vendidas'),
(416,105,18,0.00,694.21,'IVA 21% ventas'),
(417,106,1,2000.00,0.00,'Venta de contado'),
(418,106,25,0.00,1652.89,'Ventas del período'),
(419,106,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(420,106,18,0.00,347.11,'IVA 21% ventas'),
(421,107,1,4000.00,0.00,'Venta de contado'),
(422,107,25,0.00,3305.79,'Ventas del período'),
(423,107,29,2400.00,0.00,'Costo de mercaderías vendidas'),
(424,107,18,0.00,694.21,'IVA 21% ventas'),
(425,108,1,2000.00,0.00,'Venta de contado'),
(426,108,25,0.00,1652.89,'Ventas del período'),
(427,108,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(428,108,18,0.00,347.11,'IVA 21% ventas'),
(429,109,1,4000.00,0.00,'Venta de contado'),
(430,109,25,0.00,3305.79,'Ventas del período'),
(431,109,29,2400.00,0.00,'Costo de mercaderías vendidas'),
(432,109,18,0.00,694.21,'IVA 21% ventas'),
(433,110,1,2000.00,0.00,'Venta de contado'),
(434,110,25,0.00,1652.89,'Ventas del período'),
(435,110,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(436,110,18,0.00,347.11,'IVA 21% ventas'),
(437,111,1,2000.00,0.00,'Venta de contado'),
(438,111,25,0.00,1652.89,'Ventas del período'),
(439,111,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(440,111,18,0.00,347.11,'IVA 21% ventas'),
(441,112,1,4000.00,0.00,'Venta de contado'),
(442,112,25,0.00,3305.79,'Ventas del período'),
(443,112,29,2400.00,0.00,'Costo de mercaderías vendidas'),
(444,112,18,0.00,694.21,'IVA 21% ventas'),
(445,113,1,2000.00,0.00,'Venta de contado'),
(446,113,25,0.00,1652.89,'Ventas del período'),
(447,113,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(448,113,18,0.00,347.11,'IVA 21% ventas'),
(449,114,1,2000.00,0.00,'Venta de contado'),
(450,114,25,0.00,1652.89,'Ventas del período'),
(451,114,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(452,114,18,0.00,347.11,'IVA 21% ventas'),
(453,115,1,4000.00,0.00,'Venta de contado'),
(454,115,25,0.00,3305.79,'Ventas del período'),
(455,115,29,2400.00,0.00,'Costo de mercaderías vendidas'),
(456,115,18,0.00,694.21,'IVA 21% ventas'),
(457,116,1,2000.00,0.00,'Venta de contado'),
(458,116,25,0.00,1652.89,'Ventas del período'),
(459,116,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(460,116,18,0.00,347.11,'IVA 21% ventas'),
(461,117,1,2000.00,0.00,'Venta de contado'),
(462,117,25,0.00,1652.89,'Ventas del período'),
(463,117,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(464,117,18,0.00,347.11,'IVA 21% ventas'),
(465,118,1,2000.00,0.00,'Venta de contado'),
(466,118,25,0.00,1652.89,'Ventas del período'),
(467,118,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(468,118,18,0.00,347.11,'IVA 21% ventas'),
(469,119,1,8000.00,0.00,'Venta de contado'),
(470,119,25,0.00,6611.57,'Ventas del período'),
(471,119,29,4800.00,0.00,'Costo de mercaderías vendidas'),
(472,119,18,0.00,1388.43,'IVA 21% ventas'),
(473,120,1,2000.00,0.00,'Venta de contado'),
(474,120,25,0.00,1652.89,'Ventas del período'),
(475,120,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(476,120,18,0.00,347.11,'IVA 21% ventas'),
(477,121,1,4000.00,0.00,'Venta de contado'),
(478,121,25,0.00,3305.79,'Ventas del período'),
(479,121,29,2400.00,0.00,'Costo de mercaderías vendidas'),
(480,121,18,0.00,694.21,'IVA 21% ventas'),
(481,122,1,4000.00,0.00,'Venta de contado'),
(482,122,25,0.00,3305.79,'Ventas del período'),
(483,122,29,2400.00,0.00,'Costo de mercaderías vendidas'),
(484,122,18,0.00,694.21,'IVA 21% ventas'),
(485,123,1,2000.00,0.00,'Venta de contado'),
(486,123,25,0.00,1652.89,'Ventas del período'),
(487,123,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(488,123,18,0.00,347.11,'IVA 21% ventas'),
(489,124,1,696.00,0.00,'Venta de contado'),
(490,124,25,0.00,575.21,'Ventas del período'),
(491,124,29,400.00,0.00,'Costo de mercaderías vendidas'),
(492,124,18,0.00,120.79,'IVA 21% ventas'),
(493,125,1,348.00,0.00,'Venta de contado'),
(494,125,25,0.00,287.60,'Ventas del período'),
(495,125,29,200.00,0.00,'Costo de mercaderías vendidas'),
(496,125,18,0.00,60.40,'IVA 21% ventas'),
(497,126,1,174.00,0.00,'Venta de contado'),
(498,126,25,0.00,143.80,'Ventas del período'),
(499,126,29,100.00,0.00,'Costo de mercaderías vendidas'),
(500,126,18,0.00,30.20,'IVA 21% ventas'),
(501,127,1,8546.00,0.00,'Venta de contado'),
(502,127,25,0.00,7062.81,'Ventas del período'),
(503,127,29,5100.00,0.00,'Costo de mercaderías vendidas'),
(504,127,18,0.00,1483.19,'IVA 21% ventas'),
(505,128,1,2000.00,0.00,'Venta de contado'),
(506,128,25,0.00,1652.89,'Ventas del período'),
(507,128,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(508,128,18,0.00,347.11,'IVA 21% ventas'),
(509,129,1,2000.00,0.00,'Venta de contado'),
(510,129,25,0.00,1652.89,'Ventas del período'),
(511,129,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(512,129,18,0.00,347.11,'IVA 21% ventas'),
(513,130,1,2000.00,0.00,'Venta de contado'),
(514,130,25,0.00,1652.89,'Ventas del período'),
(515,130,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(516,130,18,0.00,347.11,'IVA 21% ventas'),
(517,131,1,2000.00,0.00,'Venta de contado'),
(518,131,25,0.00,1652.89,'Ventas del período'),
(519,131,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(520,131,18,0.00,347.11,'IVA 21% ventas'),
(521,132,1,2000.00,0.00,'Venta de contado'),
(522,132,25,0.00,1652.89,'Ventas del período'),
(523,132,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(524,132,18,0.00,347.11,'IVA 21% ventas'),
(525,133,1,2000.00,0.00,'Venta de contado'),
(526,133,25,0.00,1652.89,'Ventas del período'),
(527,133,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(528,133,18,0.00,347.11,'IVA 21% ventas'),
(529,134,1,8000.00,0.00,'Venta de contado'),
(530,134,25,0.00,6611.57,'Ventas del período'),
(531,134,29,4800.00,0.00,'Costo de mercaderías vendidas'),
(532,134,18,0.00,1388.43,'IVA 21% ventas'),
(533,135,1,8000.00,0.00,'Venta de contado'),
(534,135,25,0.00,6611.57,'Ventas del período'),
(535,135,29,4800.00,0.00,'Costo de mercaderías vendidas'),
(536,135,18,0.00,1388.43,'IVA 21% ventas'),
(537,136,1,2000.00,0.00,'Venta de contado'),
(538,136,25,0.00,1652.89,'Ventas del período'),
(539,136,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(540,136,18,0.00,347.11,'IVA 21% ventas'),
(541,137,1,2000.00,0.00,'Venta de contado'),
(542,137,25,0.00,1652.89,'Ventas del período'),
(543,137,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(544,137,18,0.00,347.11,'IVA 21% ventas'),
(545,138,1,2000.00,0.00,'Venta de contado'),
(546,138,25,0.00,1652.89,'Ventas del período'),
(547,138,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(548,138,18,0.00,347.11,'IVA 21% ventas'),
(549,139,1,2000.00,0.00,'Venta de contado'),
(550,139,25,0.00,1652.89,'Ventas del período'),
(551,139,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(552,139,18,0.00,347.11,'IVA 21% ventas'),
(553,140,1,4000.00,0.00,'Venta de contado'),
(554,140,25,0.00,3305.79,'Ventas del período'),
(555,140,29,2400.00,0.00,'Costo de mercaderías vendidas'),
(556,140,18,0.00,694.21,'IVA 21% ventas'),
(557,141,1,2000.00,0.00,'Venta de contado'),
(558,141,25,0.00,1652.89,'Ventas del período'),
(559,141,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(560,141,18,0.00,347.11,'IVA 21% ventas'),
(561,142,1,6000.00,0.00,'Venta de contado'),
(562,142,25,0.00,4958.68,'Ventas del período'),
(563,142,29,3600.00,0.00,'Costo de mercaderías vendidas'),
(564,142,18,0.00,1041.32,'IVA 21% ventas'),
(565,143,1,8000.00,0.00,'Venta de contado'),
(566,143,25,0.00,6611.57,'Ventas del período'),
(567,143,29,4800.00,0.00,'Costo de mercaderías vendidas'),
(568,143,18,0.00,1388.43,'IVA 21% ventas'),
(569,144,1,2000.00,0.00,'Venta de contado'),
(570,144,25,0.00,1652.89,'Ventas del período'),
(571,144,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(572,144,18,0.00,347.11,'IVA 21% ventas'),
(573,145,1,2000.00,0.00,'Venta de contado'),
(574,145,25,0.00,1652.89,'Ventas del período'),
(575,145,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(576,145,18,0.00,347.11,'IVA 21% ventas'),
(577,146,1,1740.00,0.00,'Venta de contado'),
(578,146,25,0.00,1438.02,'Ventas del período'),
(579,146,29,1000.00,0.00,'Costo de mercaderías vendidas'),
(580,146,18,0.00,301.98,'IVA 21% ventas'),
(581,147,1,2000.00,0.00,'Venta de contado'),
(582,147,25,0.00,1652.89,'Ventas del período'),
(583,147,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(584,147,18,0.00,347.11,'IVA 21% ventas'),
(585,148,1,2000.00,0.00,'Venta de contado'),
(586,148,25,0.00,1652.89,'Ventas del período'),
(587,148,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(588,148,18,0.00,347.11,'IVA 21% ventas'),
(589,149,1,2000.00,0.00,'Venta de contado'),
(590,149,25,0.00,1652.89,'Ventas del período'),
(591,149,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(592,149,18,0.00,347.11,'IVA 21% ventas'),
(593,150,1,2000.00,0.00,'Venta de contado'),
(594,150,25,0.00,1652.89,'Ventas del período'),
(595,150,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(596,150,18,0.00,347.11,'IVA 21% ventas'),
(597,151,1,2000.00,0.00,'Venta de contado'),
(598,151,25,0.00,1652.89,'Ventas del período'),
(599,151,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(600,151,18,0.00,347.11,'IVA 21% ventas'),
(601,152,1,2000.00,0.00,'Venta de contado'),
(602,152,25,0.00,1652.89,'Ventas del período'),
(603,152,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(604,152,18,0.00,347.11,'IVA 21% ventas'),
(605,153,1,2000.00,0.00,'Venta de contado'),
(606,153,25,0.00,1652.89,'Ventas del período'),
(607,153,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(608,153,18,0.00,347.11,'IVA 21% ventas'),
(609,154,1,2000.00,0.00,'Venta de contado'),
(610,154,25,0.00,1652.89,'Ventas del período'),
(611,154,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(612,154,18,0.00,347.11,'IVA 21% ventas'),
(613,155,1,2000.00,0.00,'Venta de contado'),
(614,155,25,0.00,1652.89,'Ventas del período'),
(615,155,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(616,155,18,0.00,347.11,'IVA 21% ventas'),
(617,156,1,2000.00,0.00,'Venta de contado'),
(618,156,25,0.00,1652.89,'Ventas del período'),
(619,156,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(620,156,18,0.00,347.11,'IVA 21% ventas'),
(621,157,1,2000.00,0.00,'Venta de contado'),
(622,157,25,0.00,1652.89,'Ventas del período'),
(623,157,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(624,157,18,0.00,347.11,'IVA 21% ventas'),
(625,158,1,2000.00,0.00,'Venta de contado'),
(626,158,25,0.00,1652.89,'Ventas del período'),
(627,158,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(628,158,18,0.00,347.11,'IVA 21% ventas'),
(629,159,1,2000.00,0.00,'Venta de contado'),
(630,159,25,0.00,1652.89,'Ventas del período'),
(631,159,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(632,159,18,0.00,347.11,'IVA 21% ventas'),
(633,160,1,2000.00,0.00,'Venta de contado'),
(634,160,25,0.00,1652.89,'Ventas del período'),
(635,160,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(636,160,18,0.00,347.11,'IVA 21% ventas'),
(637,161,1,2000.00,0.00,'Venta de contado'),
(638,161,25,0.00,1652.89,'Ventas del período'),
(639,161,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(640,161,18,0.00,347.11,'IVA 21% ventas'),
(641,162,1,2000.00,0.00,'Venta de contado'),
(642,162,25,0.00,1652.89,'Ventas del período'),
(643,162,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(644,162,18,0.00,347.11,'IVA 21% ventas'),
(645,163,1,174.00,0.00,'Venta de contado'),
(646,163,25,0.00,143.80,'Ventas del período'),
(647,163,29,100.00,0.00,'Costo de mercaderías vendidas'),
(648,163,18,0.00,30.20,'IVA 21% ventas'),
(649,164,1,696.00,0.00,'Venta de contado'),
(650,164,25,0.00,575.21,'Ventas del período'),
(651,164,29,400.00,0.00,'Costo de mercaderías vendidas'),
(652,164,18,0.00,120.79,'IVA 21% ventas'),
(653,165,1,5580.00,0.00,'Venta de contado'),
(654,165,25,0.00,4611.57,'Ventas del período'),
(655,165,29,3000.00,0.00,'Costo de mercaderías vendidas'),
(656,165,18,0.00,968.43,'IVA 21% ventas'),
(657,166,1,1860.00,0.00,'Venta de contado'),
(658,166,25,0.00,1537.19,'Ventas del período'),
(659,166,29,1000.00,0.00,'Costo de mercaderías vendidas'),
(660,166,18,0.00,322.81,'IVA 21% ventas'),
(661,167,1,1860.00,0.00,'Venta de contado'),
(662,167,25,0.00,1537.19,'Ventas del período'),
(663,167,29,1000.00,0.00,'Costo de mercaderías vendidas'),
(664,167,18,0.00,322.81,'IVA 21% ventas'),
(665,168,1,1860.00,0.00,'Venta de contado'),
(666,168,25,0.00,1537.19,'Ventas del período'),
(667,168,29,1000.00,0.00,'Costo de mercaderías vendidas'),
(668,168,18,0.00,322.81,'IVA 21% ventas'),
(669,169,1,1860.00,0.00,'Venta de contado'),
(670,169,25,0.00,1537.19,'Ventas del período'),
(671,169,29,1000.00,0.00,'Costo de mercaderías vendidas'),
(672,169,18,0.00,322.81,'IVA 21% ventas'),
(673,170,1,1860.00,0.00,'Venta de contado'),
(674,170,25,0.00,1537.19,'Ventas del período'),
(675,170,29,1000.00,0.00,'Costo de mercaderías vendidas'),
(676,170,18,0.00,322.81,'IVA 21% ventas'),
(677,171,1,372.00,0.00,'Venta de contado'),
(678,171,25,0.00,307.44,'Ventas del período'),
(679,171,29,200.00,0.00,'Costo de mercaderías vendidas'),
(680,171,18,0.00,64.56,'IVA 21% ventas'),
(681,172,1,2000.00,0.00,'Venta de contado'),
(682,172,25,0.00,1652.89,'Ventas del período'),
(683,172,29,1200.00,0.00,'Costo de mercaderías vendidas'),
(684,172,18,0.00,347.11,'IVA 21% ventas'),
(685,181,1,4348.00,0.00,'Venta de contado'),
(686,181,25,0.00,3593.39,'Ventas del período'),
(687,181,29,2600.00,0.00,'Costo de mercaderías vendidas'),
(688,181,18,0.00,754.61,'IVA 21% ventas');
/*!40000 ALTER TABLE `asiento_detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asientos_contables`
--

DROP TABLE IF EXISTS `asientos_contables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asientos_contables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `numero` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `tipo_comprobante` varchar(50) DEFAULT NULL,
  `nro_comprobante` varchar(50) DEFAULT NULL,
  `total_debe` decimal(15,2) DEFAULT 0.00,
  `total_haber` decimal(15,2) DEFAULT 0.00,
  `usuario_id` int(11) NOT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_asientos_fecha` (`empresa_id`,`fecha`),
  CONSTRAINT `asientos_contables_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  CONSTRAINT `asientos_contables_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=182 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asientos_contables`
--

LOCK TABLES `asientos_contables` WRITE;
/*!40000 ALTER TABLE `asientos_contables` DISABLE KEYS */;
INSERT INTO `asientos_contables` VALUES
(89,1,1,'2026-04-05','Venta #3 - ','Factura','3',8000.00,8000.00,1,'2026-04-27 19:18:00'),
(90,1,2,'2026-04-05','Venta #5 - ','Factura','5',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(91,1,3,'2026-04-05','Venta #6 - ','Factura','6',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(92,1,4,'2026-04-05','Venta #8 - ','Factura','8',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(93,1,5,'2026-04-05','Venta #9 - ','Factura','9',4000.00,4000.00,1,'2026-04-27 19:18:00'),
(94,1,6,'2026-04-05','Venta #15 - ','Factura','15',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(95,1,7,'2026-04-05','Venta #17 - ','Factura','17',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(96,1,8,'2026-04-05','Venta #25 - ','Factura','25',10000.00,10000.00,1,'2026-04-27 19:18:00'),
(97,1,9,'2026-04-05','Venta #36 - ','Factura','36',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(98,1,10,'2026-04-05','Venta #40 - ','Factura','40',4000.00,4000.00,1,'2026-04-27 19:18:00'),
(99,1,11,'2026-04-05','Venta #41 - ','Factura','41',4000.00,4000.00,1,'2026-04-27 19:18:00'),
(100,1,12,'2026-04-05','Venta #43 - ','Factura','43',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(101,1,13,'2026-04-05','Venta #44 - ','Factura','44',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(102,1,14,'2026-04-05','Venta #45 - ','Factura','45',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(103,1,15,'2026-04-05','Venta #47 - ','Factura','47',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(104,1,16,'2026-04-05','Venta #53 - ','Factura','53',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(105,1,17,'2026-04-05','Venta #54 - ','Factura','54',4000.00,4000.00,1,'2026-04-27 19:18:00'),
(106,1,18,'2026-04-05','Venta #56 - ','Factura','56',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(107,1,19,'2026-04-05','Venta #57 - ','Factura','57',4000.00,4000.00,1,'2026-04-27 19:18:00'),
(108,1,20,'2026-04-05','Venta #58 - ','Factura','58',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(109,1,21,'2026-04-05','Venta #60 - ','Factura','60',4000.00,4000.00,1,'2026-04-27 19:18:00'),
(110,1,22,'2026-04-05','Venta #61 - ','Factura','61',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(111,1,23,'2026-04-06','Venta #68 - ','Factura','68',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(112,1,24,'2026-04-06','Venta #69 - ','Factura','69',4000.00,4000.00,1,'2026-04-27 19:18:00'),
(113,1,25,'2026-04-06','Venta #71 - ','Factura','71',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(114,1,26,'2026-04-06','Venta #72 - ','Factura','72',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(115,1,27,'2026-04-06','Venta #74 - ','Factura','74',4000.00,4000.00,1,'2026-04-27 19:18:00'),
(116,1,28,'2026-04-06','Venta #78 - ','Factura','78',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(117,1,29,'2026-04-06','Venta #80 - ','Factura','80',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(118,1,30,'2026-04-06','Venta #84 - ','Factura','84',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(119,1,31,'2026-04-06','Venta #86 - ','Factura','86',8000.00,8000.00,1,'2026-04-27 19:18:00'),
(120,1,32,'2026-04-06','Venta #91 - ','Factura','91',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(121,1,33,'2026-04-06','Venta #93 - ','Factura','93',4000.00,4000.00,1,'2026-04-27 19:18:00'),
(122,1,34,'2026-04-06','Venta #96 - ','Factura','96',4000.00,4000.00,1,'2026-04-27 19:18:00'),
(123,1,35,'2026-04-08','Venta #132 - ','Factura','132',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(124,1,36,'2026-04-13','Venta #147 - ','Factura','147',696.00,696.00,1,'2026-04-27 19:18:00'),
(125,1,37,'2026-04-13','Venta #148 - ','Factura','148',348.00,348.00,1,'2026-04-27 19:18:00'),
(126,1,38,'2026-04-13','Venta #149 - ','Factura','149',174.00,174.00,1,'2026-04-27 19:18:00'),
(127,1,39,'2026-04-13','Venta #158 - ','Factura','158',8546.00,8546.00,1,'2026-04-27 19:18:00'),
(128,1,40,'2026-04-14','Venta #166 - ','Factura','166',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(129,1,41,'2026-04-14','Venta #167 - ','Factura','167',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(130,1,42,'2026-04-14','Venta #168 - ','Factura','168',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(131,1,43,'2026-04-14','Venta #169 - ','Factura','169',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(132,1,44,'2026-04-14','Venta #170 - ','Factura','170',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(133,1,45,'2026-04-14','Venta #172 - ','Factura','172',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(134,1,46,'2026-04-14','Venta #173 - ','Factura','173',8000.00,8000.00,1,'2026-04-27 19:18:00'),
(135,1,47,'2026-04-14','Venta #174 - ','Factura','174',8000.00,8000.00,1,'2026-04-27 19:18:00'),
(136,1,48,'2026-04-14','Venta #175 - ','Factura','175',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(137,1,49,'2026-04-14','Venta #176 - ','Factura','176',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(138,1,50,'2026-04-14','Venta #177 - ','Factura','177',2000.00,2000.00,1,'2026-04-27 19:18:00'),
(139,1,51,'2026-04-14','Venta #178 - ','Factura','178',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(140,1,52,'2026-04-14','Venta #179 - ','Factura','179',4000.00,4000.00,1,'2026-04-27 19:18:01'),
(141,1,53,'2026-04-14','Venta #180 - ','Factura','180',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(142,1,54,'2026-04-14','Venta #181 - Juan Manuel Ubilla','Factura','181',6000.00,6000.00,1,'2026-04-27 19:18:01'),
(143,1,55,'2026-04-15','Venta #182 - ','Factura','182',8000.00,8000.00,1,'2026-04-27 19:18:01'),
(144,1,56,'2026-04-15','Venta #183 - ','Factura','183',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(145,1,57,'2026-04-15','Venta #184 - ','Factura','184',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(146,1,58,'2026-04-15','Venta #185 - ','Factura','185',1740.00,1740.00,1,'2026-04-27 19:18:01'),
(147,1,59,'2026-04-15','Venta #186 - ','Factura','186',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(148,1,60,'2026-04-15','Venta #187 - ','Factura','187',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(149,1,61,'2026-04-15','Venta #188 - ','Factura','188',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(150,1,62,'2026-04-15','Venta #189 - ','Factura','189',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(151,1,63,'2026-04-15','Venta #190 - ','Factura','190',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(152,1,64,'2026-04-15','Venta #191 - ','Factura','191',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(153,1,65,'2026-04-15','Venta #192 - ','Factura','192',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(154,1,66,'2026-04-15','Venta #193 - ','Factura','193',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(155,1,67,'2026-04-15','Venta #194 - ','Factura','194',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(156,1,68,'2026-04-15','Venta #195 - ','Factura','195',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(157,1,69,'2026-04-15','Venta #196 - ','Factura','196',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(158,1,70,'2026-04-15','Venta #197 - ','Factura','197',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(159,1,71,'2026-04-15','Venta #198 - ','Factura','198',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(160,1,72,'2026-04-15','Venta #199 - ','Factura','199',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(161,1,73,'2026-04-15','Venta #200 - ','Factura','200',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(162,1,74,'2026-04-15','Venta #201 - ','Factura','201',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(163,1,75,'2026-04-15','Venta #202 - ','Factura','202',174.00,174.00,1,'2026-04-27 19:18:01'),
(164,1,76,'2026-04-15','Venta #203 - ','Factura','203',696.00,696.00,1,'2026-04-27 19:18:01'),
(165,1,77,'2026-04-15','Venta #204 - ','Factura','204',5580.00,5580.00,1,'2026-04-27 19:18:01'),
(166,1,78,'2026-04-15','Venta #205 - ','Factura','205',1860.00,1860.00,1,'2026-04-27 19:18:01'),
(167,1,79,'2026-04-15','Venta #206 - ','Factura','206',1860.00,1860.00,1,'2026-04-27 19:18:01'),
(168,1,80,'2026-04-16','Venta #207 - ','Factura','207',1860.00,1860.00,1,'2026-04-27 19:18:01'),
(169,1,81,'2026-04-16','Venta #208 - ','Factura','208',1860.00,1860.00,1,'2026-04-27 19:18:01'),
(170,1,82,'2026-04-17','Venta #209 - ','Factura','209',1860.00,1860.00,1,'2026-04-27 19:18:01'),
(171,1,83,'2026-04-24','Venta #210 - ','Factura','210',372.00,372.00,1,'2026-04-27 19:18:01'),
(172,1,84,'2026-04-27','Venta #211 - ','Factura','211',2000.00,2000.00,1,'2026-04-27 19:18:01'),
(173,1,85,'2026-04-27','Venta #242 - ','FACTURA','242',0.00,0.00,1,'2026-04-28 00:13:39'),
(174,1,86,'2026-04-27','Venta #244 - ','FACTURA','244',47060.00,41600.00,1,'2026-04-28 00:16:17'),
(175,1,87,'2026-04-27','Venta #245 - ','FACTURA','245',11732.76,10356.00,1,'2026-04-28 01:02:13'),
(176,1,88,'2026-04-27','Venta #246 - ','FACTURA','246',3930.54,3474.00,1,'2026-04-28 01:09:00'),
(177,1,89,'2026-04-27','Venta #247 - ','FACTURA','247',7861.08,6948.00,1,'2026-04-28 01:22:08'),
(178,1,90,'2026-04-27','Venta #248 - ','FACTURA','248',4241.08,3748.00,1,'2026-04-28 01:28:00'),
(179,1,91,'2026-04-27','Venta #249 - ','FACTURA','249',8171.62,7222.00,1,'2026-04-28 01:28:23'),
(180,1,92,'2026-04-27','Venta #250 - ','FACTURA','250',7861.08,6948.00,1,'2026-04-28 01:33:35'),
(181,1,93,'2026-04-27','Venta #250 - ','Factura','250',4348.00,4348.00,1,'2026-04-28 02:55:51');
/*!40000 ALTER TABLE `asientos_contables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `asientos_movimientos`
--

DROP TABLE IF EXISTS `asientos_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asientos_movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `asiento_id` int(11) NOT NULL,
  `cuenta_id` int(11) NOT NULL,
  `debe` decimal(15,2) DEFAULT 0.00,
  `haber` decimal(15,2) DEFAULT 0.00,
  `descripcion` varchar(500) DEFAULT NULL,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_asiento_id` (`asiento_id`),
  KEY `idx_cuenta_id` (`cuenta_id`),
  CONSTRAINT `asientos_movimientos_ibfk_1` FOREIGN KEY (`asiento_id`) REFERENCES `asientos_contables` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asientos_movimientos`
--

LOCK TABLES `asientos_movimientos` WRITE;
/*!40000 ALTER TABLE `asientos_movimientos` DISABLE KEYS */;
INSERT INTO `asientos_movimientos` VALUES
(1,174,1,26000.00,0.00,'Venta #244','2026-04-28 00:16:17'),
(2,174,5,5460.00,0.00,'IVA Débito Fiscal #244','2026-04-28 00:16:17'),
(3,174,4,0.00,26000.00,'Ingreso por Venta #244','2026-04-28 00:16:17'),
(4,174,6,15600.00,0.00,'Costo Venta #244','2026-04-28 00:16:17'),
(5,174,3,0.00,15600.00,'Salida Inventario #244','2026-04-28 00:16:17'),
(6,175,1,6556.00,0.00,'Venta #245','2026-04-28 01:02:13'),
(7,175,5,1376.76,0.00,'IVA Débito Fiscal #245','2026-04-28 01:02:13'),
(8,175,4,0.00,6556.00,'Ingreso por Venta #245','2026-04-28 01:02:13'),
(9,175,6,3800.00,0.00,'Costo Venta #245','2026-04-28 01:02:13'),
(10,175,3,0.00,3800.00,'Salida Inventario #245','2026-04-28 01:02:13'),
(11,176,1,2174.00,0.00,'Venta #246','2026-04-28 01:09:00'),
(12,176,5,456.54,0.00,'IVA Débito Fiscal #246','2026-04-28 01:09:00'),
(13,176,4,0.00,2174.00,'Ingreso por Venta #246','2026-04-28 01:09:00'),
(14,176,6,1300.00,0.00,'Costo Venta #246','2026-04-28 01:09:00'),
(15,176,3,0.00,1300.00,'Salida Inventario #246','2026-04-28 01:09:00'),
(16,177,1,4348.00,0.00,'Venta #247','2026-04-28 01:22:08'),
(17,177,5,913.08,0.00,'IVA Débito Fiscal #247','2026-04-28 01:22:08'),
(18,177,4,0.00,4348.00,'Ingreso por Venta #247','2026-04-28 01:22:08'),
(19,177,6,2600.00,0.00,'Costo Venta #247','2026-04-28 01:22:08'),
(20,177,3,0.00,2600.00,'Salida Inventario #247','2026-04-28 01:22:08'),
(21,178,1,2348.00,0.00,'Venta #248','2026-04-28 01:28:00'),
(22,178,5,493.08,0.00,'IVA Débito Fiscal #248','2026-04-28 01:28:00'),
(23,178,4,0.00,2348.00,'Ingreso por Venta #248','2026-04-28 01:28:00'),
(24,178,6,1400.00,0.00,'Costo Venta #248','2026-04-28 01:28:00'),
(25,178,3,0.00,1400.00,'Salida Inventario #248','2026-04-28 01:28:00'),
(26,179,1,4522.00,0.00,'Venta #249','2026-04-28 01:28:23'),
(27,179,5,949.62,0.00,'IVA Débito Fiscal #249','2026-04-28 01:28:23'),
(28,179,4,0.00,4522.00,'Ingreso por Venta #249','2026-04-28 01:28:23'),
(29,179,6,2700.00,0.00,'Costo Venta #249','2026-04-28 01:28:23'),
(30,179,3,0.00,2700.00,'Salida Inventario #249','2026-04-28 01:28:23'),
(31,180,1,4348.00,0.00,'Venta #250','2026-04-28 01:33:35'),
(32,180,5,913.08,0.00,'IVA Débito Fiscal #250','2026-04-28 01:33:35'),
(33,180,4,0.00,4348.00,'Ingreso por Venta #250','2026-04-28 01:33:35'),
(34,180,6,2600.00,0.00,'Costo Venta #250','2026-04-28 01:33:35'),
(35,180,3,0.00,2600.00,'Salida Inventario #250','2026-04-28 01:33:35');
/*!40000 ALTER TABLE `asientos_movimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `avisos`
--

DROP TABLE IF EXISTS `avisos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `avisos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `cliente_id` int(11) DEFAULT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo_aviso` enum('perdido','encontrado','mascota','servicio','otro') DEFAULT 'otro',
  `imagen` varchar(500) DEFAULT NULL,
  `prompt_ia` text DEFAULT NULL,
  `generador_ia` varchar(50) DEFAULT NULL,
  `enviado_banner` tinyint(1) DEFAULT 0,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `fecha_expiracion` datetime DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `telefono_contacto` varchar(50) DEFAULT NULL,
  `email_contacto` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_cliente` (`cliente_id`),
  KEY `idx_activo` (`activo`),
  KEY `idx_tipo` (`tipo_aviso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `avisos`
--

LOCK TABLES `avisos` WRITE;
/*!40000 ALTER TABLE `avisos` DISABLE KEYS */;
/*!40000 ALTER TABLE `avisos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `banners`
--

DROP TABLE IF EXISTS `banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `ruta_imagen` text DEFAULT NULL,
  `fecha_caducidad` date DEFAULT NULL,
  `tiempo_visualizacion` int(11) DEFAULT 10,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `banners`
--

LOCK TABLES `banners` WRITE;
/*!40000 ALTER TABLE `banners` DISABLE KEYS */;
INSERT INTO `banners` VALUES
(2,1,'Promo: Coca-Cola','files/empresa_1/banners/2026/04/20260427_224448_56dd81a7.jpeg','2026-05-01',30,1,'2026-04-28 01:44:51','2026-04-28 01:44:51');
/*!40000 ALTER TABLE `banners` ENABLE KEYS */;
UNLOCK TABLES;

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
-- Table structure for table `camaras`
--

DROP TABLE IF EXISTS `camaras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `camaras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `puerto` int(11) DEFAULT 554,
  `usuario` varchar(50) DEFAULT NULL,
  `password` varchar(100) DEFAULT NULL,
  `tipo` varchar(20) DEFAULT 'RTSP',
  `marca` varchar(50) DEFAULT NULL,
  `modelo` varchar(50) DEFAULT NULL,
  `ruta_stream` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `empresa_id` int(11) NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `camaras`
--

LOCK TABLES `camaras` WRITE;
/*!40000 ALTER TABLE `camaras` DISABLE KEYS */;
/*!40000 ALTER TABLE `camaras` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES
(1,'prueba',1);
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `centros_costo`
--

DROP TABLE IF EXISTS `centros_costo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `centros_costo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `empresa_id` (`empresa_id`),
  CONSTRAINT `centros_costo_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `centros_costo`
--

LOCK TABLES `centros_costo` WRITE;
/*!40000 ALTER TABLE `centros_costo` DISABLE KEYS */;
/*!40000 ALTER TABLE `centros_costo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  `nombre` varchar(255) DEFAULT NULL,
  `apellido` varchar(255) DEFAULT NULL,
  `documento` varchar(50) DEFAULT NULL,
  `tipo_documento` varchar(20) DEFAULT NULL,
  `condicion_iva` varchar(50) DEFAULT NULL,
  `telefono` varchar(30) DEFAULT NULL,
  `whatsapp` varchar(30) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL COMMENT 'Ruta a la foto para reconocimiento facial',
  `acepta_whatsapp` tinyint(1) NOT NULL DEFAULT 0,
  `comentarios` text DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  `foto_cliente` varchar(255) DEFAULT NULL COMMENT 'Path a la foto principal del cliente',
  `foto_opcional` varchar(255) DEFAULT NULL COMMENT 'Path a una foto secundaria opcional del cliente',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
INSERT INTO `clientes` VALUES
(1,1,'Juan Manuel','Ubilla','20-25847281-1','DNI','Responsable Inscripto','','','',NULL,0,'','2026-04-14 22:52:23','','');
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
  `empresa_id` int(11) DEFAULT NULL,
  `venta_id` int(11) DEFAULT NULL,
  `tipo_cbte` int(11) DEFAULT NULL,
  `punto_vta` int(11) DEFAULT NULL,
  `nro_cbte` int(11) DEFAULT NULL,
  `cae` varchar(20) DEFAULT NULL,
  `fecha_vto_cae` date DEFAULT NULL,
  `estado` varchar(20) DEFAULT NULL,
  `response_afip` text DEFAULT NULL,
  `entorno` varchar(10) DEFAULT NULL,
  `fecha_emision` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comprobante_afip`
--

LOCK TABLES `comprobante_afip` WRITE;
/*!40000 ALTER TABLE `comprobante_afip` DISABLE KEYS */;
INSERT INTO `comprobante_afip` VALUES
(1,1,200,11,1,0,NULL,NULL,'ERROR','0','DEV','2026-04-15 19:07:06'),
(2,1,201,11,1,28137265,NULL,'2026-04-15','APROBADO','{\"cae\": null, \"nro_cbte\": 0, \"resultado\": \"E\", \"error\": \"0\"}','DEV','2026-04-15 19:09:36'),
(3,1,202,11,1,16466333,NULL,'2026-04-15','APROBADO','{\"cae\": null, \"nro_cbte\": 0, \"resultado\": \"E\", \"error\": \"0\"}','DEV','2026-04-15 19:23:57'),
(4,1,203,11,1,96962973,NULL,'2026-04-15','APROBADO','{\"cae\": null, \"nro_cbte\": 0, \"resultado\": \"E\", \"error\": \"0\"}','DEV','2026-04-15 19:25:03'),
(5,1,204,11,1,0,NULL,'2026-04-15','APROBADO','{\"cae\": null, \"nro_cbte\": 0, \"resultado\": \"E\", \"error\": \"0\"}','DEV','2026-04-15 20:43:45'),
(6,1,205,11,1,0,NULL,'2026-04-15','APROBADO','{\"cae\": null, \"nro_cbte\": 0, \"resultado\": \"E\", \"error\": \"0\"}','DEV','2026-04-15 20:44:17'),
(7,1,206,11,1,92763332,NULL,'2026-04-15','APROBADO','{\"cae\": null, \"nro_cbte\": 0, \"resultado\": \"E\", \"error\": \"0\"}','DEV','2026-04-15 20:48:04'),
(8,1,207,11,1,57528302,NULL,'2026-04-16','APROBADO','{\"cae\": null, \"nro_cbte\": 0, \"resultado\": \"E\", \"error\": \"0\"}','DEV','2026-04-16 21:03:20'),
(9,1,208,11,1,31607493,NULL,'2026-04-16','APROBADO','{\"cae\": null, \"nro_cbte\": 0, \"resultado\": \"E\", \"error\": \"0\"}','DEV','2026-04-16 21:07:41'),
(10,1,209,11,1,43645536,NULL,'2026-04-17','APROBADO','{\"cae\": null, \"nro_cbte\": 0, \"resultado\": \"E\", \"error\": \"0\"}','DEV','2026-04-17 13:52:54'),
(11,1,210,11,1,92006463,NULL,'2026-04-24','APROBADO','{\"cae\": null, \"nro_cbte\": 0, \"resultado\": \"E\", \"error\": \"0\"}','DEV','2026-04-24 19:27:27');
/*!40000 ALTER TABLE `comprobante_afip` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `config_alertas`
--

DROP TABLE IF EXISTS `config_alertas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `config_alertas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `alertas_activas` tinyint(1) DEFAULT 1,
  `notificacion_sonido` tinyint(1) DEFAULT 1,
  `notificacion_pantalla` tinyint(1) DEFAULT 1,
  `email_alerta` tinyint(1) DEFAULT 0,
  `whatsapp_alerta` tinyint(1) DEFAULT 0,
  `umbral_confianza` decimal(3,2) DEFAULT 0.80,
  `tiempo_grabacion_seg` int(11) DEFAULT 60,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa_id` (`empresa_id`),
  KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config_alertas`
--

LOCK TABLES `config_alertas` WRITE;
/*!40000 ALTER TABLE `config_alertas` DISABLE KEYS */;
/*!40000 ALTER TABLE `config_alertas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `config_camara`
--

DROP TABLE IF EXISTS `config_camara`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `config_camara` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `grabar_ventas` tinyint(1) DEFAULT 1,
  `deteccion_movimiento` tinyint(1) DEFAULT 1,
  `calidad_video` varchar(10) DEFAULT '720p',
  `duracion_grabacion` int(11) DEFAULT 30,
  `almacenamiento_maximo` int(11) DEFAULT 1000 COMMENT 'MB',
  `horario_inicio` time DEFAULT '08:00:00',
  `horario_fin` time DEFAULT '22:00:00',
  `alertas_fuera_horario` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `empresa_id` (`empresa_id`),
  KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config_camara`
--

LOCK TABLES `config_camara` WRITE;
/*!40000 ALTER TABLE `config_camara` DISABLE KEYS */;
/*!40000 ALTER TABLE `config_camara` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `config_ia`
--

DROP TABLE IF EXISTS `config_ia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `config_ia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `generador_defecto` varchar(50) DEFAULT 'dalle',
  `api_key_dalle` varchar(500) DEFAULT NULL,
  `api_key_stable` varchar(500) DEFAULT NULL,
  `api_key_midjourney` varchar(500) DEFAULT NULL,
  `url_stable` varchar(500) DEFAULT NULL,
  `url_midjourney` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config_ia`
--

LOCK TABLES `config_ia` WRITE;
/*!40000 ALTER TABLE `config_ia` DISABLE KEYS */;
/*!40000 ALTER TABLE `config_ia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `config_iva`
--

DROP TABLE IF EXISTS `config_iva`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `config_iva` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `tasa` decimal(5,2) NOT NULL,
  `descripcion` varchar(50) DEFAULT NULL,
  `tipo_cuenta_ventas` int(11) DEFAULT NULL,
  `tipo_cuenta_compras` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `empresa_id` (`empresa_id`),
  KEY `tipo_cuenta_ventas` (`tipo_cuenta_ventas`),
  KEY `tipo_cuenta_compras` (`tipo_cuenta_compras`),
  CONSTRAINT `config_iva_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  CONSTRAINT `config_iva_ibfk_2` FOREIGN KEY (`tipo_cuenta_ventas`) REFERENCES `plan_cuentas` (`id`),
  CONSTRAINT `config_iva_ibfk_3` FOREIGN KEY (`tipo_cuenta_compras`) REFERENCES `plan_cuentas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config_iva`
--

LOCK TABLES `config_iva` WRITE;
/*!40000 ALTER TABLE `config_iva` DISABLE KEYS */;
INSERT INTO `config_iva` VALUES
(1,1,21.00,'IVA 21%',NULL,NULL),
(2,1,10.50,'IVA 10.5%',NULL,NULL),
(3,1,27.00,'IVA 27%',NULL,NULL),
(4,1,0.00,'IVA Exento',NULL,NULL);
/*!40000 ALTER TABLE `config_iva` ENABLE KEYS */;
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
  `modo_api_key` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `config_pagos`
--

LOCK TABLES `config_pagos` WRITE;
/*!40000 ALTER TABLE `config_pagos` DISABLE KEYS */;
INSERT INTO `config_pagos` VALUES
(1,'TEST-TOKEN-123','','CAJA_01',1,'','',0,'');
/*!40000 ALTER TABLE `config_pagos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ctacte_clientes`
--

DROP TABLE IF EXISTS `ctacte_clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ctacte_clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `tipo_movimiento` enum('DEUDA','PAGO') NOT NULL,
  `comprobante_tipo` varchar(50) DEFAULT NULL,
  `comprobante_nro` varchar(50) DEFAULT NULL,
  `importe` decimal(15,2) NOT NULL,
  `saldo` decimal(15,2) NOT NULL,
  `fecha` date NOT NULL,
  `vencimiento` date DEFAULT NULL,
  `asiento_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cliente_id` (`cliente_id`),
  KEY `asiento_id` (`asiento_id`),
  KEY `idx_ctacte_cliente_saldo` (`empresa_id`,`cliente_id`,`saldo`),
  CONSTRAINT `ctacte_clientes_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  CONSTRAINT `ctacte_clientes_ibfk_2` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`),
  CONSTRAINT `ctacte_clientes_ibfk_3` FOREIGN KEY (`asiento_id`) REFERENCES `asientos_contables` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ctacte_clientes`
--

LOCK TABLES `ctacte_clientes` WRITE;
/*!40000 ALTER TABLE `ctacte_clientes` DISABLE KEYS */;
/*!40000 ALTER TABLE `ctacte_clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ctacte_proveedores`
--

DROP TABLE IF EXISTS `ctacte_proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ctacte_proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `tipo_movimiento` enum('DEUDA','PAGO') NOT NULL,
  `comprobante_tipo` varchar(50) DEFAULT NULL,
  `comprobante_nro` varchar(50) DEFAULT NULL,
  `importe` decimal(15,2) NOT NULL,
  `saldo` decimal(15,2) NOT NULL,
  `fecha` date NOT NULL,
  `vencimiento` date DEFAULT NULL,
  `asiento_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `asiento_id` (`asiento_id`),
  KEY `idx_ctacte_proveedor_saldo` (`empresa_id`,`proveedor_id`,`saldo`),
  CONSTRAINT `ctacte_proveedores_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  CONSTRAINT `ctacte_proveedores_ibfk_2` FOREIGN KEY (`asiento_id`) REFERENCES `asientos_contables` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ctacte_proveedores`
--

LOCK TABLES `ctacte_proveedores` WRITE;
/*!40000 ALTER TABLE `ctacte_proveedores` DISABLE KEYS */;
/*!40000 ALTER TABLE `ctacte_proveedores` ENABLE KEYS */;
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
-- Table structure for table `detecciones_faciales`
--

DROP TABLE IF EXISTS `detecciones_faciales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detecciones_faciales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `perfil_id` int(11) DEFAULT NULL COMMENT 'ID de perfil facial si es cliente',
  `persona_riesgo_id` int(11) DEFAULT NULL COMMENT 'ID de persona de riesgo si aplica',
  `camara_id` int(11) NOT NULL,
  `fecha` timestamp NULL DEFAULT current_timestamp(),
  `confianza` decimal(3,2) DEFAULT 0.00,
  `tipo_deteccion` enum('CLIENTE','RIESGO','DESCONOCIDO') DEFAULT 'DESCONOCIDO',
  `venta_id` int(11) DEFAULT NULL COMMENT 'Vinculado con venta si aplica',
  `empresa_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_camara_fecha` (`camara_id`,`fecha`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_tipo_deteccion` (`tipo_deteccion`),
  KEY `idx_venta` (`venta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detecciones_faciales`
--

LOCK TABLES `detecciones_faciales` WRITE;
/*!40000 ALTER TABLE `detecciones_faciales` DISABLE KEYS */;
/*!40000 ALTER TABLE `detecciones_faciales` ENABLE KEYS */;
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
-- Table structure for table `eventos_camara`
--

DROP TABLE IF EXISTS `eventos_camara`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eventos_camara` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `camara_id` int(11) NOT NULL,
  `tipo_evento` varchar(20) NOT NULL COMMENT 'movimiento, venta, manual',
  `fecha` timestamp NULL DEFAULT current_timestamp(),
  `archivo_video` varchar(255) DEFAULT NULL,
  `duracion` int(11) DEFAULT 30 COMMENT 'segundos',
  `venta_id` int(11) DEFAULT NULL,
  `thumbnail` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `empresa_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_camara_fecha` (`camara_id`,`fecha`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_venta` (`venta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eventos_camara`
--

LOCK TABLES `eventos_camara` WRITE;
/*!40000 ALTER TABLE `eventos_camara` DISABLE KEYS */;
/*!40000 ALTER TABLE `eventos_camara` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `eventos_comportamiento`
--

DROP TABLE IF EXISTS `eventos_comportamiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `eventos_comportamiento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `camara_id` int(11) DEFAULT NULL,
  `tipo_evento` varchar(50) DEFAULT NULL COMMENT 'MOVIMIENTO SOSPECHOSO, POSTURA ANOMALA, AGLOMERACION, OBJETO OCULTO',
  `nivel_riesgo` enum('BAJO','MEDIO','ALTO','CRITICO') DEFAULT 'MEDIO',
  `descripcion` text DEFAULT NULL,
  `coordenadas` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'x,y,w,h del área detectada' CHECK (json_valid(`coordenadas`)),
  `confianza` decimal(3,2) DEFAULT 0.00,
  `frame_imagen` varchar(255) DEFAULT NULL COMMENT 'URL del frame donde se detectó',
  `fecha` timestamp NULL DEFAULT current_timestamp(),
  `empresa_id` int(11) DEFAULT NULL,
  `activa` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_camara_fecha` (`camara_id`,`fecha`),
  KEY `idx_empresa_tipo` (`empresa_id`,`tipo_evento`),
  KEY `idx_riesgo_fecha` (`nivel_riesgo`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `eventos_comportamiento`
--

LOCK TABLES `eventos_comportamiento` WRITE;
/*!40000 ALTER TABLE `eventos_comportamiento` DISABLE KEYS */;
/*!40000 ALTER TABLE `eventos_comportamiento` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finanzas`
--

LOCK TABLES `finanzas` WRITE;
/*!40000 ALTER TABLE `finanzas` DISABLE KEYS */;
INSERT INTO `finanzas` VALUES
(1,1,'INGRESO','Ventas',174.00,'Venta POS #149','EFECTIVO','2026-04-13','20:50:06',1),
(2,1,'INGRESO','Ventas',8546.00,'Venta POS #158','EFECTIVO','2026-04-13','21:44:35',1),
(3,1,'INGRESO','Ventas',2000.00,'Venta POS #169','EFECTIVO','2026-04-14','12:37:48',1),
(4,1,'INGRESO','Ventas',2000.00,'Venta POS #170','EFECTIVO','2026-04-14','12:40:01',1),
(5,1,'INGRESO','Ventas',2000.00,'Venta POS #172','EFECTIVO','2026-04-14','21:01:16',1),
(6,1,'INGRESO','Ventas',2000.00,'Venta POS #175','EFECTIVO','2026-04-14','21:14:29',1),
(7,1,'INGRESO','Ventas',2000.00,'Venta POS #176','EFECTIVO','2026-04-14','21:36:19',1),
(8,1,'INGRESO','Ventas',2000.00,'Venta POS #177','EFECTIVO','2026-04-14','21:37:05',1),
(9,1,'INGRESO','Ventas',2000.00,'Venta POS #178','EFECTIVO','2026-04-14','21:39:13',1),
(10,1,'INGRESO','Ventas',4000.00,'Venta POS #179','EFECTIVO','2026-04-14','21:57:39',1),
(11,1,'INGRESO','Ventas',2000.00,'Venta #180 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-14','22:51:35',1),
(12,1,'INGRESO','Ventas',6000.00,'Venta #181 (Cliente: Juan Manuel Ubilla)','EFECTIVO','2026-04-14','22:52:57',1),
(13,1,'INGRESO','Ventas',8000.00,'Venta #182 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','10:33:54',1),
(14,1,'INGRESO','Ventas',2000.00,'Venta #183 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','11:00:58',1),
(15,1,'INGRESO','Ventas',2000.00,'Venta #184 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','11:02:46',1),
(16,1,'INGRESO','Ventas',1740.00,'Venta #185 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','11:03:40',1),
(17,1,'INGRESO','Ventas',2000.00,'Venta #186 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','11:03:53',1),
(18,1,'INGRESO','Ventas',2000.00,'Venta #187 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','11:19:04',1),
(19,1,'INGRESO','Ventas',2000.00,'Venta #188 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','11:24:55',1),
(20,1,'INGRESO','Ventas',2000.00,'Venta #189 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','11:26:27',1),
(21,1,'INGRESO','Ventas',2000.00,'Venta #190 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','11:29:35',1),
(22,1,'INGRESO','Ventas',2000.00,'Venta #191 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','11:30:33',1),
(23,1,'INGRESO','Ventas',2000.00,'Venta #192 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','12:04:48',1),
(24,1,'INGRESO','Ventas',2000.00,'Venta #193 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','12:16:51',1),
(25,1,'INGRESO','Ventas',2000.00,'Venta #194 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','12:17:47',1),
(26,1,'INGRESO','Ventas',2000.00,'Venta #195 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','12:18:43',1),
(27,1,'INGRESO','Ventas',2000.00,'Venta #196 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','12:28:52',1),
(28,1,'INGRESO','Ventas',2000.00,'Venta #197 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','12:35:49',1),
(29,1,'INGRESO','Ventas',2000.00,'Venta #198 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','16:03:56',1),
(30,1,'INGRESO','Ventas',2000.00,'Venta #199 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','16:06:41',1),
(31,1,'INGRESO','Ventas',2000.00,'Venta #200 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','16:07:06',1),
(32,1,'INGRESO','Ventas',2000.00,'Venta #201 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','16:09:36',1),
(33,1,'INGRESO','Ventas',174.00,'Venta #202 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','16:23:57',1),
(34,1,'INGRESO','Ventas',696.00,'Venta #203 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','16:25:03',1),
(35,1,'INGRESO','Ventas',5580.00,'Venta #204 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','17:43:45',1),
(36,1,'INGRESO','Ventas',1860.00,'Venta #205 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','17:44:17',1),
(37,1,'INGRESO','Ventas',1860.00,'Venta #206 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-15','17:48:04',1),
(38,1,'INGRESO','Ventas',1860.00,'Venta #207 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-16','18:03:39',1),
(39,1,'INGRESO','Ventas',1860.00,'Venta #208 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-16','18:07:43',1),
(40,1,'INGRESO','Ventas',1860.00,'Venta #209 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-17','10:52:54',1),
(41,1,'INGRESO','Ventas',372.00,'Venta #210 (Cliente: CONSUMIDOR FINAL)','EFECTIVO','2026-04-24','16:27:27',1),
(42,1,'INGRESO','Ventas',2000.00,'Venta #211','EFECTIVO','2026-04-27','09:28:13',1),
(71,1,'INGRESO','Ventas',8000.00,'Venta #240','EFECTIVO','2026-04-27','21:10:25',1),
(73,1,'INGRESO','Ventas',5774.00,'Venta #242','EFECTIVO','2026-04-27','21:13:39',1),
(75,1,'INGRESO','Ventas',26000.00,'Venta #244','EFECTIVO','2026-04-27','21:16:17',1),
(76,1,'INGRESO','Ventas',6556.00,'Venta #245','EFECTIVO','2026-04-27','22:02:13',1),
(77,1,'INGRESO','Ventas',2174.00,'Venta #246','EFECTIVO','2026-04-27','22:09:00',1),
(78,1,'INGRESO','Ventas',4348.00,'Venta #247','TARJETA','2026-04-27','22:22:08',1),
(79,1,'INGRESO','Ventas',2348.00,'Venta #248','EFECTIVO','2026-04-27','22:28:00',1),
(80,1,'INGRESO','Ventas',4522.00,'Venta #249','EFECTIVO','2026-04-27','22:28:23',1),
(81,1,'INGRESO','Ventas',4348.00,'Venta #250','EFECTIVO','2026-04-27','22:33:34',1);
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
  `permitir_fraccion` tinyint(1) DEFAULT 0,
  `ruta_imagenes` varchar(255) DEFAULT NULL,
  `siempre_fiscal` tinyint(4) DEFAULT 0,
  `iibb` float DEFAULT 0,
  `margen_ganancia` float DEFAULT 0,
  `afip_mock` tinyint(4) DEFAULT 0,
  `ventas_fraccion` tinyint(4) DEFAULT 0,
  `punto_vta` int(11) DEFAULT 1,
  `afip_cert` text DEFAULT NULL,
  `afip_key` text DEFAULT NULL,
  `afip_prod` tinyint(4) DEFAULT 0,
  `icono_app` varchar(255) DEFAULT NULL COMMENT 'Path al icono de la aplicación (.ico, .png, .jpg)',
  `impresora_auto` tinyint(1) DEFAULT 0 COMMENT 'Imprimir ticket automáticamente después de cada venta',
  `impresora_ticket` varchar(255) DEFAULT 'Default' COMMENT 'Nombre o IP de la impresora de tickets',
  `impresora_factura` varchar(255) DEFAULT 'Default' COMMENT 'Nombre o IP de la impresora de facturas',
  `ia_proveedor` varchar(255) DEFAULT 'OpenAI (DALL-E)',
  `ia_ruta_imagenes` text DEFAULT './imagenes_generadas',
  `dlna_ruta_banners` text DEFAULT NULL,
  `dlna_ruta_imagenes` text DEFAULT NULL,
  `dlna_ruta_videos` text DEFAULT NULL,
  `dlna_activo` tinyint(1) DEFAULT 0,
  `dlna_auto_start` tinyint(1) DEFAULT 0,
  `dlna_tipo_servidor` varchar(20) DEFAULT 'local',
  `dlna_ip_servidor` varchar(50) DEFAULT '192.168.1.100',
  `dlna_puerto_servidor` varchar(10) DEFAULT '8200',
  `whatsapp_sid` varchar(255) DEFAULT NULL,
  `whatsapp_api_key` varchar(255) DEFAULT NULL,
  `whatsapp_api_secret` varchar(255) DEFAULT NULL,
  `whatsapp_phone` varchar(50) DEFAULT NULL,
  `email_host` varchar(255) DEFAULT NULL,
  `email_port` int(11) DEFAULT 587,
  `email_username` varchar(255) DEFAULT NULL,
  `email_password` varchar(255) DEFAULT NULL,
  `email_encryption` varchar(20) DEFAULT 'tls',
  `email_from_name` varchar(255) DEFAULT NULL,
  `email_from_email` varchar(255) DEFAULT NULL,
  `afip_cuit` varchar(20) DEFAULT NULL,
  `afip_punto_venta` int(11) DEFAULT 0,
  `afip_certificado` text DEFAULT NULL,
  `afip_clave` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_impresora_auto` (`impresora_auto`),
  KEY `idx_impresora_ticket` (`impresora_ticket`),
  KEY `idx_impresora_factura` (`impresora_factura`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nombre_negocio`
--

LOCK TABLES `nombre_negocio` WRITE;
/*!40000 ALTER TABLE `nombre_negocio` DISABLE KEYS */;
INSERT INTO `nombre_negocio` VALUES
(1,'BOTILLERIA CURICO','PUNTO DE VENTA','$',21.00,3.00,50.00,'20-258472811','INDEPENDENCIA 3100','No Inscripto','1160281010','files/empresa_1/tickets/',1,1,'files/empresa_1/productos/',0,3,50,1,0,1,'','',0,NULL,0,'Default','Default','Ideogram AI','files/empresa_1/ia/','files/empresa_1/banners/','files/empresa_1/imagenes/','files/empresa_1/videos/',1,1,'remoto','192.168.1.101','8200',NULL,NULL,NULL,NULL,NULL,587,NULL,NULL,'tls',NULL,NULL,'',0,'','');
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
  `estado_validacion` varchar(20) DEFAULT 'pendiente' COMMENT 'Estado de validación del pago',
  `qr_data` text DEFAULT NULL COMMENT 'Datos del QR generado',
  `fecha_validacion` datetime DEFAULT NULL COMMENT 'Fecha de validación del pago',
  `respuesta_gateway` text DEFAULT NULL COMMENT 'Respuesta del gateway de pago',
  `external_reference` varchar(100) DEFAULT NULL COMMENT 'Referencia externa del pago',
  PRIMARY KEY (`id`),
  KEY `venta_id` (`venta_id`),
  KEY `idx_pagos_fecha` (`fecha`),
  KEY `idx_pagos_estado` (`estado`),
  KEY `idx_estado_validacion` (`estado_validacion`),
  KEY `idx_external_reference` (`external_reference`),
  KEY `idx_fecha_validacion` (`fecha_validacion`),
  CONSTRAINT `pagos_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=119 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pagos`
--

LOCK TABLES `pagos` WRITE;
/*!40000 ALTER TABLE `pagos` DISABLE KEYS */;
INSERT INTO `pagos` VALUES
(1,3,'efectivo',8000.00,'2026-04-05 15:05:51',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(2,5,'efectivo',2000.00,'2026-04-05 15:15:11',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(3,25,'EFECTIVO',10000.00,'2026-04-05 16:34:03',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(4,36,'EFECTIVO',2000.00,'2026-04-05 17:34:20',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(5,40,'EFECTIVO',4000.00,'2026-04-05 17:56:13',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(6,41,'EFECTIVO',4000.00,'2026-04-05 18:01:02',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(7,45,'EFECTIVO',2000.00,'2026-04-05 18:02:38',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(8,58,'EFECTIVO',2000.00,'2026-04-05 18:42:27',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(9,61,'EFECTIVO',2000.00,'2026-04-05 18:44:12',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(10,69,'EFECTIVO',2000.00,'2026-04-06 18:57:41',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(11,69,'EFECTIVO',2000.00,'2026-04-06 18:57:52',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(12,71,'EFECTIVO',2000.00,'2026-04-06 19:01:16',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(13,72,'EFECTIVO',2000.00,'2026-04-06 19:03:53',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(14,74,'EFECTIVO',4000.00,'2026-04-06 19:06:45',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(15,78,'EFECTIVO',2000.00,'2026-04-06 19:11:46',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(16,80,'EFECTIVO',2000.00,'2026-04-06 19:18:03',0.00,0.00,'pendiente',1,'pendiente',NULL,NULL,NULL,NULL),
(17,84,'EFECTIVO',2000.00,'2026-04-06 19:35:22',0.00,0.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(18,86,'EFECTIVO',8000.00,'2026-04-06 19:37:58',0.00,0.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(19,91,'EFECTIVO',2000.00,'2026-04-06 21:14:21',0.00,0.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(20,93,'EFECTIVO',4000.00,'2026-04-06 21:24:14',0.00,0.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(21,96,'EFECTIVO',4000.00,'2026-04-06 21:27:35',0.00,0.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(22,NULL,'EFECTIVO',2000.00,'2026-04-08 15:52:59',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(23,NULL,'EFECTIVO',2000.00,'2026-04-08 15:55:47',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(24,NULL,'EFECTIVO',2000.00,'2026-04-08 15:58:10',18000.00,20000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(25,NULL,'TARJETA',2000.00,'2026-04-08 16:02:38',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(26,NULL,'TARJETA',2000.00,'2026-04-08 16:14:14',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(27,NULL,'TARJETA',2000.00,'2026-04-08 16:16:59',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(28,NULL,'TARJETA',2000.00,'2026-04-08 16:21:14',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(29,NULL,'TARJETA',2000.00,'2026-04-08 16:21:48',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(30,NULL,'EFECTIVO',2000.00,'2026-04-08 16:27:13',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(31,114,'TARJETA',2000.00,'2026-04-08 16:30:18',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(32,115,'TRANSFERENCIA',6000.00,'2026-04-08 16:31:52',0.00,6000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(33,116,'TARJETA',4000.00,'2026-04-08 16:33:01',0.00,4000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(34,118,'TARJETA',2000.00,'2026-04-08 16:33:37',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(35,120,'TARJETA',2000.00,'2026-04-08 16:34:33',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(36,122,'QR',2000.00,'2026-04-08 16:36:46',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(37,124,'QR',2000.00,'2026-04-08 16:49:38',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(38,149,'EFECTIVO',174.00,'2026-04-13 20:50:06',9826.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(39,158,'EFECTIVO',8546.00,'2026-04-13 21:44:35',1454.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(40,169,'EFECTIVO',2000.00,'2026-04-14 12:37:48',8000.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(41,170,'EFECTIVO',2000.00,'2026-04-14 12:40:01',1000.00,3000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(42,172,'EFECTIVO',2000.00,'2026-04-14 21:01:16',1000.00,3000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(43,175,'EFECTIVO',2000.00,'2026-04-14 21:14:29',3000.00,5000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(44,176,'EFECTIVO',2000.00,'2026-04-14 21:36:19',8000.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(45,177,'EFECTIVO',2000.00,'2026-04-14 21:37:05',3000.00,5000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(46,178,'EFECTIVO',2000.00,'2026-04-14 21:39:13',8000.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(47,179,'EFECTIVO',4000.00,'2026-04-14 21:57:39',6000.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(48,180,'EFECTIVO',2000.00,'2026-04-14 22:51:35',3000.00,5000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(49,181,'EFECTIVO',6000.00,'2026-04-14 22:52:57',0.00,6000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(50,182,'EFECTIVO',8000.00,'2026-04-15 10:33:54',2000.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(51,183,'EFECTIVO',2000.00,'2026-04-15 11:00:58',8000.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(52,184,'EFECTIVO',2000.00,'2026-04-15 11:02:46',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(53,185,'EFECTIVO',1740.00,'2026-04-15 11:03:40',1260.00,3000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(54,186,'EFECTIVO',2000.00,'2026-04-15 11:03:53',7000.00,9000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(55,187,'EFECTIVO',2000.00,'2026-04-15 11:19:03',8000.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(56,188,'EFECTIVO',2000.00,'2026-04-15 11:24:55',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(57,189,'EFECTIVO',2000.00,'2026-04-15 11:26:27',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(58,190,'EFECTIVO',2000.00,'2026-04-15 11:29:35',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(59,191,'EFECTIVO',2000.00,'2026-04-15 11:30:33',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(60,192,'EFECTIVO',2000.00,'2026-04-15 12:04:48',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(61,193,'EFECTIVO',2000.00,'2026-04-15 12:16:51',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(62,194,'EFECTIVO',2000.00,'2026-04-15 12:17:47',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(63,195,'EFECTIVO',2000.00,'2026-04-15 12:18:43',8000.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(64,196,'EFECTIVO',2000.00,'2026-04-15 12:28:52',8000.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(65,197,'EFECTIVO',2000.00,'2026-04-15 12:35:49',8000.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(66,198,'EFECTIVO',2000.00,'2026-04-15 16:03:56',8000.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(67,199,'EFECTIVO',2000.00,'2026-04-15 16:06:41',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(68,200,'EFECTIVO',2000.00,'2026-04-15 16:07:06',8000.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(69,201,'EFECTIVO',2000.00,'2026-04-15 16:09:36',8000.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(70,202,'EFECTIVO',174.00,'2026-04-15 16:23:57',9826.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(71,203,'EFECTIVO',696.00,'2026-04-15 16:25:03',9304.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(72,204,'EFECTIVO',5580.00,'2026-04-15 17:43:45',4420.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(73,205,'EFECTIVO',1860.00,'2026-04-15 17:44:17',140.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(74,206,'EFECTIVO',1860.00,'2026-04-15 17:48:04',140.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(75,207,'EFECTIVO',1860.00,'2026-04-16 18:03:27',8140.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(76,208,'EFECTIVO',1860.00,'2026-04-16 18:07:42',8140.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(77,209,'EFECTIVO',1860.00,'2026-04-17 10:52:54',8140.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(78,210,'EFECTIVO',372.00,'2026-04-24 16:27:27',9628.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(79,211,'EFECTIVO',2000.00,'2026-04-27 09:28:13',0.00,2000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(108,240,'EFECTIVO',8000.00,'2026-04-27 21:10:25',12000.00,20000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(110,242,'EFECTIVO',5774.00,'2026-04-27 21:13:39',4226.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(112,244,'EFECTIVO',26000.00,'2026-04-27 21:16:17',4000.00,30000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(113,245,'EFECTIVO',6556.00,'2026-04-27 22:02:13',3444.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(114,246,'EFECTIVO',2174.00,'2026-04-27 22:09:00',7826.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(115,247,'TARJETA',4348.00,'2026-04-27 22:22:08',0.00,4348.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(116,248,'EFECTIVO',2348.00,'2026-04-27 22:28:00',17652.00,20000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(117,249,'EFECTIVO',4522.00,'2026-04-27 22:28:23',5478.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL),
(118,250,'EFECTIVO',4348.00,'2026-04-27 22:33:34',5652.00,10000.00,'completado',1,'pendiente',NULL,NULL,NULL,NULL);
/*!40000 ALTER TABLE `pagos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patrones_sospechosos`
--

DROP TABLE IF EXISTS `patrones_sospechosos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patrones_sospechosos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo_patron` varchar(50) DEFAULT NULL COMMENT 'MOVIMIENTO_RAPIDO, POSTURA_AGACHADA, MIRADA_ALREDEDOR, MANOS_EN_BOLSILLOS',
  `nivel_riesgo` enum('BAJO','MEDIO','ALTO','CRITICO') DEFAULT 'MEDIO',
  `activo` tinyint(1) DEFAULT 1,
  `empresa_id` int(11) DEFAULT NULL,
  `umbral_confianza` decimal(3,2) DEFAULT 0.70,
  PRIMARY KEY (`id`),
  KEY `idx_empresa_tipo` (`empresa_id`,`tipo_patron`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patrones_sospechosos`
--

LOCK TABLES `patrones_sospechosos` WRITE;
/*!40000 ALTER TABLE `patrones_sospechosos` DISABLE KEYS */;
/*!40000 ALTER TABLE `patrones_sospechosos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `perfiles_faciales`
--

DROP TABLE IF EXISTS `perfiles_faciales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `perfiles_faciales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) DEFAULT NULL,
  `face_data` varchar(500) NOT NULL COMMENT 'Datos faciales codificados',
  `confianza` decimal(3,2) DEFAULT 0.95,
  `fecha_registro` timestamp NULL DEFAULT current_timestamp(),
  `ultima_deteccion` timestamp NULL DEFAULT NULL,
  `empresa_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_cliente` (`cliente_id`),
  KEY `idx_face_data` (`face_data`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `perfiles_faciales`
--

LOCK TABLES `perfiles_faciales` WRITE;
/*!40000 ALTER TABLE `perfiles_faciales` DISABLE KEYS */;
/*!40000 ALTER TABLE `perfiles_faciales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `periodos_contables`
--

DROP TABLE IF EXISTS `periodos_contables`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `periodos_contables` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `año` int(11) NOT NULL,
  `mes` int(11) NOT NULL,
  `estado` enum('ABIERTO','CERRADO') DEFAULT 'ABIERTO',
  `fecha_cierre` date DEFAULT NULL,
  `usuario_cierre_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_periodo_empresa` (`empresa_id`,`año`,`mes`),
  KEY `usuario_cierre_id` (`usuario_cierre_id`),
  CONSTRAINT `periodos_contables_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`),
  CONSTRAINT `periodos_contables_ibfk_2` FOREIGN KEY (`usuario_cierre_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `periodos_contables`
--

LOCK TABLES `periodos_contables` WRITE;
/*!40000 ALTER TABLE `periodos_contables` DISABLE KEYS */;
/*!40000 ALTER TABLE `periodos_contables` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `personas_riesgo`
--

DROP TABLE IF EXISTS `personas_riesgo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `personas_riesgo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) DEFAULT NULL,
  `alias` varchar(100) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL COMMENT 'Ruta a la foto',
  `tipo_riesgo` enum('ALTO','MEDIO','BAJO') DEFAULT 'MEDIO',
  `nivel_peligro` int(11) DEFAULT 3 COMMENT '1-10',
  `descripcion` text DEFAULT NULL COMMENT 'Descripción del riesgo',
  `modus_operandi` text DEFAULT NULL COMMENT 'Método habitual',
  `fecha_registro` timestamp NULL DEFAULT current_timestamp(),
  `ultima_deteccion` timestamp NULL DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `empresa_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`empresa_id`),
  KEY `idx_tipo_riesgo` (`tipo_riesgo`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `personas_riesgo`
--

LOCK TABLES `personas_riesgo` WRITE;
/*!40000 ALTER TABLE `personas_riesgo` DISABLE KEYS */;
/*!40000 ALTER TABLE `personas_riesgo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plan_cuentas`
--

DROP TABLE IF EXISTS `plan_cuentas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plan_cuentas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `tipo` enum('ACTIVO','PASIVO','PATRIMONIO_NETO','INGRESO','GASTO','RESULTADO') NOT NULL,
  `subtipo` varchar(50) DEFAULT NULL,
  `nivel` int(11) DEFAULT 1,
  `padre_id` int(11) DEFAULT NULL,
  `imputable` tinyint(1) DEFAULT 1,
  `ajuste_inflacion` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_codigo_empresa` (`empresa_id`,`codigo`),
  KEY `padre_id` (`padre_id`),
  CONSTRAINT `plan_cuentas_ibfk_1` FOREIGN KEY (`padre_id`) REFERENCES `plan_cuentas` (`id`),
  CONSTRAINT `plan_cuentas_ibfk_2` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plan_cuentas`
--

LOCK TABLES `plan_cuentas` WRITE;
/*!40000 ALTER TABLE `plan_cuentas` DISABLE KEYS */;
INSERT INTO `plan_cuentas` VALUES
(1,1,'1.01.01','Caja','ACTIVO','Caja y Bancos',1,NULL,1,0,'2026-04-27 18:39:25'),
(2,1,'1.01.02','Banco Cuenta Corriente','ACTIVO','Caja y Bancos',1,NULL,1,0,'2026-04-27 18:39:25'),
(3,1,'1.01.03','Banco Caja de Ahorro','ACTIVO','Caja y Bancos',1,NULL,1,0,'2026-04-27 18:39:25'),
(4,1,'1.02.01','Clientes','ACTIVO','Créditos',1,NULL,1,0,'2026-04-27 18:39:25'),
(5,1,'1.02.02','Deudores Varios','ACTIVO','Créditos',1,NULL,1,0,'2026-04-27 18:39:25'),
(6,1,'1.03.01','IVA Crédito Fiscal','ACTIVO','Créditos Fiscales',1,NULL,1,0,'2026-04-27 18:39:25'),
(7,1,'1.03.02','Retenciones de IIBB a Recuperar','ACTIVO','Créditos Fiscales',1,NULL,1,0,'2026-04-27 18:39:25'),
(8,1,'1.03.03','Retenciones de Ganancias a Recuperar','ACTIVO','Créditos Fiscales',1,NULL,1,0,'2026-04-27 18:39:25'),
(9,1,'1.04.01','Mercaderías','ACTIVO','Bienes de Cambio',1,NULL,1,0,'2026-04-27 18:39:25'),
(10,1,'1.04.02','Materias Primas','ACTIVO','Bienes de Cambio',1,NULL,1,0,'2026-04-27 18:39:25'),
(11,1,'2.01.01','Rodados','ACTIVO','Bienes de Uso',1,NULL,1,0,'2026-04-27 18:39:25'),
(12,1,'2.01.02','Mobiliario','ACTIVO','Bienes de Uso',1,NULL,1,0,'2026-04-27 18:39:25'),
(13,1,'2.01.03','Equipos de Computación','ACTIVO','Bienes de Uso',1,NULL,1,0,'2026-04-27 18:39:25'),
(14,1,'2.02.01','Amortización Acumulada Rodados','ACTIVO','Amortizaciones Acumuladas',1,NULL,1,0,'2026-04-27 18:39:25'),
(15,1,'2.02.02','Amortización Acumulada Mobiliario','ACTIVO','Amortizaciones Acumuladas',1,NULL,1,0,'2026-04-27 18:39:25'),
(16,1,'3.01.01','Proveedores','PASIVO','Deudas Comerciales',1,NULL,1,0,'2026-04-27 18:39:25'),
(17,1,'3.01.02','Acreedores Varios','PASIVO','Deudas Comerciales',1,NULL,1,0,'2026-04-27 18:39:25'),
(18,1,'3.02.01','IVA Débito Fiscal','PASIVO','Deudas Fiscales',1,NULL,1,0,'2026-04-27 18:39:25'),
(19,1,'3.02.02','Retenciones de IIBB a Pagar','PASIVO','Deudas Fiscales',1,NULL,1,0,'2026-04-27 18:39:25'),
(20,1,'3.02.03','Retenciones de Ganancias a Pagar','PASIVO','Deudas Fiscales',1,NULL,1,0,'2026-04-27 18:39:25'),
(21,1,'3.02.04','SUSS a Pagar','PASIVO','Deudas Fiscales',1,NULL,1,0,'2026-04-27 18:39:25'),
(22,1,'3.03.01','Sueldos a Pagar','PASIVO','Remuneraciones',1,NULL,1,0,'2026-04-27 18:39:25'),
(23,1,'3.03.02','Cargas Sociales a Pagar','PASIVO','Remuneraciones',1,NULL,1,0,'2026-04-27 18:39:25'),
(24,1,'3.04.01','Bancos Préstamos','PASIVO','Deudas Financieras',1,NULL,1,0,'2026-04-27 18:39:25'),
(25,1,'5.01.01','Ventas','INGRESO','Ventas',1,NULL,1,0,'2026-04-27 18:39:25'),
(26,1,'5.01.02','Ventas Exportaciones','INGRESO','Ventas',1,NULL,1,0,'2026-04-27 18:39:25'),
(27,1,'5.02.01','Intereses Ganados','INGRESO','Ingresos Financieros',1,NULL,1,0,'2026-04-27 18:39:25'),
(28,1,'5.03.01','Otros Ingresos','INGRESO','Otros Ingresos',1,NULL,1,0,'2026-04-27 18:39:25'),
(29,1,'6.01.01','Costo Mercaderías Vendidas','GASTO','Costo de Ventas',1,NULL,1,0,'2026-04-27 18:39:25'),
(30,1,'6.02.01','Sueldos y Jornales','GASTO','Gastos de Personal',1,NULL,1,0,'2026-04-27 18:39:25'),
(31,1,'6.02.02','Cargas Sociales','GASTO','Gastos de Personal',1,NULL,1,0,'2026-04-27 18:39:25'),
(32,1,'6.03.01','Alquiler Local Comercial','GASTO','Alquileres',1,NULL,1,0,'2026-04-27 18:39:25'),
(33,1,'6.04.01','Servicios Luz','GASTO','Servicios',1,NULL,1,0,'2026-04-27 18:39:25'),
(34,1,'6.04.02','Servicios Agua','GASTO','Servicios',1,NULL,1,0,'2026-04-27 18:39:25'),
(35,1,'6.04.03','Servicios Teléfono/Internet','GASTO','Servicios',1,NULL,1,0,'2026-04-27 18:39:25'),
(36,1,'6.05.01','Publicidad y Marketing','GASTO','Gastos Comerciales',1,NULL,1,0,'2026-04-27 18:39:25'),
(37,1,'6.06.01','Intereses Pagados','GASTO','Gastos Financieros',1,NULL,1,0,'2026-04-27 18:39:25'),
(38,1,'6.07.01','Amortización Rodados','GASTO','Amortizaciones',1,NULL,1,0,'2026-04-27 18:39:25'),
(39,1,'6.07.02','Amortización Mobiliario','GASTO','Amortizaciones',1,NULL,1,0,'2026-04-27 18:39:25'),
(40,1,'6.08.01','Impuestos y Tasas','GASTO','Impuestos',1,NULL,1,0,'2026-04-27 18:39:25'),
(41,1,'6.09.01','Mantenimiento y Reparaciones','GASTO','Mantenimiento',1,NULL,1,0,'2026-04-27 18:39:25'),
(42,1,'6.10.01','Honorarios Profesionales','GASTO','Servicios Profesionales',1,NULL,1,0,'2026-04-27 18:39:25');
/*!40000 ALTER TABLE `plan_cuentas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuesto_detalles`
--

DROP TABLE IF EXISTS `presupuesto_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `presupuesto_detalles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `presupuesto_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `producto_nombre` varchar(200) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00,
  `precio_unitario` decimal(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `iva_porcentaje` decimal(5,2) NOT NULL DEFAULT 21.00,
  `iva_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_presupuesto_id` (`presupuesto_id`),
  KEY `idx_producto_id` (`producto_id`),
  CONSTRAINT `presupuesto_detalles_ibfk_1` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuesto_detalles`
--

LOCK TABLES `presupuesto_detalles` WRITE;
/*!40000 ALTER TABLE `presupuesto_detalles` DISABLE KEYS */;
INSERT INTO `presupuesto_detalles` VALUES
(1,1,4,'Producto ID 4',1.00,174.00,174.00,0.00,0.00,174.00),
(2,1,3,'Producto ID 3',1.00,2000.00,2000.00,0.00,0.00,2000.00);
/*!40000 ALTER TABLE `presupuesto_detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuesto_seguimiento`
--

DROP TABLE IF EXISTS `presupuesto_seguimiento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `presupuesto_seguimiento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `presupuesto_id` int(11) NOT NULL,
  `tipo_accion` enum('creado','enviado','visto','aprobado','rechazado','recordatorio','convertido') NOT NULL,
  `descripcion` text NOT NULL,
  `fecha_accion` datetime NOT NULL DEFAULT current_timestamp(),
  `creado_por` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_presupuesto_id` (`presupuesto_id`),
  KEY `idx_tipo_accion` (`tipo_accion`),
  KEY `idx_fecha_accion` (`fecha_accion`),
  CONSTRAINT `presupuesto_seguimiento_ibfk_1` FOREIGN KEY (`presupuesto_id`) REFERENCES `presupuestos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuesto_seguimiento`
--

LOCK TABLES `presupuesto_seguimiento` WRITE;
/*!40000 ALTER TABLE `presupuesto_seguimiento` DISABLE KEYS */;
INSERT INTO `presupuesto_seguimiento` VALUES
(1,1,'creado','Presupuesto creado exitosamente','2026-04-28 00:05:01',1);
/*!40000 ALTER TABLE `presupuesto_seguimiento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuestos`
--

DROP TABLE IF EXISTS `presupuestos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `presupuestos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `numero_presupuesto` varchar(50) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `iva_porcentaje` decimal(5,2) NOT NULL DEFAULT 21.00,
  `iva_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente','aprobado','rechazado','vencido','convertido') NOT NULL DEFAULT 'pendiente',
  `validez_dias` int(11) NOT NULL DEFAULT 30,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_vencimiento` datetime DEFAULT NULL,
  `fecha_aprobacion` datetime DEFAULT NULL,
  `fecha_conversion` datetime DEFAULT NULL,
  `creado_por` int(11) NOT NULL,
  `aprobado_por` int(11) DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_empresa_id` (`empresa_id`),
  KEY `idx_cliente_id` (`cliente_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha_creacion` (`fecha_creacion`),
  KEY `idx_numero_presupuesto` (`numero_presupuesto`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuestos`
--

LOCK TABLES `presupuestos` WRITE;
/*!40000 ALTER TABLE `presupuestos` DISABLE KEYS */;
INSERT INTO `presupuestos` VALUES
(1,1,1,'PRES-2026-0001','presupuesto x','rwerwe',0.00,21.00,0.00,0.00,'pendiente',30,'2026-04-28 00:05:01','2026-05-28 00:05:01',NULL,NULL,1,NULL,'');
/*!40000 ALTER TABLE `presupuestos` ENABLE KEYS */;
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
  `codigo_barra` varchar(50) DEFAULT NULL,
  `nombre` varchar(255) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `precio` decimal(10,2) DEFAULT NULL,
  `stock` decimal(10,3) NOT NULL DEFAULT 0.000,
  `permite_fracciones` tinyint(1) DEFAULT 0,
  `activo` tinyint(1) DEFAULT 1,
  `categoria_id` int(11) DEFAULT NULL,
  `tags` varchar(255) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  `imagen` varchar(255) DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT 0.00,
  `ultimo_usuario_id` int(11) DEFAULT 1,
  `empresa_id` int(11) NOT NULL DEFAULT 1,
  `venta_por_peso` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`),
  KEY `categoria_id` (`categoria_id`),
  KEY `idx_tags` (`tags`),
  CONSTRAINT `productos_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES
(1,'123','','Coca-Cola','None',2000.00,1000.000,0,0,NULL,'','2026-04-04 11:47:18','files/empresa_1/productos/20260427_220025_2ef2e7ef.jpg',1200.00,1,1,0),
(2,'22222',NULL,'juan','',1860.00,90.000,0,1,NULL,NULL,'2026-04-06 19:41:44',NULL,1000.00,1,1,0),
(3,'62345','','Coca-Cola','None',2000.00,71.000,0,1,NULL,'','2026-04-08 12:10:07','files/empresa_1/productos/20260427_220052_e1f258e5.jpg',1200.00,1,1,0),
(4,'2542312','','axelito','',174.00,86.000,0,1,NULL,'','2026-04-08 21:41:21','files/empresa_1/productos/20260427_220041_dafef302.jpg',100.00,1,1,0),
(6,'12334',NULL,'juan','',174.00,7.000,0,1,NULL,NULL,'2026-04-13 18:22:52',NULL,100.00,1,1,0),
(7,'000000',NULL,'hola','',1740.00,8.000,0,1,NULL,NULL,'2026-04-15 11:03:12',NULL,1000.00,1,1,0);
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
-- Table structure for table `proveedores_ia`
--

DROP TABLE IF EXISTS `proveedores_ia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedores_ia` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) DEFAULT NULL,
  `nombre` varchar(255) NOT NULL,
  `url_api` text DEFAULT NULL,
  `url_web` text DEFAULT NULL,
  `api_key` text DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `creado_en` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedores_ia`
--

LOCK TABLES `proveedores_ia` WRITE;
/*!40000 ALTER TABLE `proveedores_ia` DISABLE KEYS */;
INSERT INTO `proveedores_ia` VALUES
(1,1,'OpenAI (DALL-E)','https://api.openai.com/v1/images/generations','https://openai.com/dall-e-2','','OpenAI DALL-E - Generador de imágenes de alta calidad',1,'2026-04-26 15:03:53','2026-04-26 15:23:29'),
(2,1,'Stability AI','https://api.stability.ai/v1/generation','https://stability.ai','','Stability AI - Generador de imágenes con Stable Diffusion',1,'2026-04-26 15:03:53','2026-04-26 15:23:29'),
(3,1,'Replicate','https://api.replicate.com/v1/predictions','https://replicate.com','','Replicate - Plataforma de modelos de IA',1,'2026-04-26 15:03:53','2026-04-26 15:23:29'),
(4,1,'Hugging Face','https://api-inference.huggingface.co/models','https://huggingface.co','','Hugging Face - Modelos de IA open source',1,'2026-04-26 15:03:53','2026-04-26 15:23:29'),
(5,NULL,'Ideogram AI','222','https://ideogram.ai/t/explore','222','Ideogram AI - Generador de imágenes con excelente manejo de texto',1,'2026-04-26 15:16:02','2026-04-26 18:19:21'),
(6,NULL,'Meta AI (Imagine)','','https://www.meta.ai/ai/image','','Meta AI Imagine - Generador de imágenes de Meta',1,'2026-04-26 15:16:02','2026-04-26 15:16:02'),
(7,NULL,'Meta AI (Movie Gen)','','https://www.meta.ai/ai/video','','Meta AI Movie Gen - Generador de videos de Meta',1,'2026-04-26 15:16:02','2026-04-26 15:16:02');
/*!40000 ALTER TABLE `proveedores_ia` ENABLE KEYS */;
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
  `rol_cajero` tinyint(1) NOT NULL DEFAULT 0,
  `permiso_presupuestos` text DEFAULT 'ver,crear,editar,eliminar,aprobar,imprimir',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES
(1,'ubilla','a6ace2cf5fb423550d66c67b83a0e91af70a522fa58c7ad4dab6b9f94c082656','admin',1,0,'ver,crear,editar,eliminar,aprobar,imprimir'),
(2,'jefe','452b889d10df869834152618463e1c07ce88001a40c9fff5d4fdf300c65684c6','jefe',1,0,'ver,crear,editar,eliminar,aprobar,imprimir'),
(3,'cajero','f976d9b6177d7595d3d45c3c927b0a813c21fac23ed9e5f938813925f6d5eb27','cajero',1,0,'ver,crear,editar,eliminar,aprobar,imprimir'),
(4,'cajero','fea740101dbb727886b6908e7bc196a55054374c6827b41a60081c2525975b4d','cajero',1,0,'ver,crear,editar,eliminar,aprobar,imprimir'),
(5,'prueba','a665a45920422f9d417e4867efdc4fb8a04a1f3fff1fa07e998e86f7f7a27ae3','admin',1,0,'ver,crear,editar,eliminar,aprobar,imprimir'),
(6,'juanmanuel','f809ccfef1ca97649e592dbfcb2a4c7e2b684d3afabae170f9946715a68893f4','admin',1,0,'ver,crear,editar,eliminar,aprobar,imprimir'),
(7,'axel','4183b9f5ed14b64d012ce1e728cfa1e7afc399cb82b6729b222784db6b1a50a7','admin',1,0,'ver,crear,editar,eliminar,aprobar,imprimir');
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
  `cantidad` decimal(10,3) NOT NULL DEFAULT 0.000,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  `costo_unitario` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `venta_id` (`venta_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `venta_items_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`),
  CONSTRAINT `venta_items_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=180 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `venta_items`
--

LOCK TABLES `venta_items` WRITE;
/*!40000 ALTER TABLE `venta_items` DISABLE KEYS */;
INSERT INTO `venta_items` VALUES
(1,3,1,1.000,2000.00,2000.00,1200.00),
(2,3,1,1.000,2000.00,2000.00,1200.00),
(3,3,1,1.000,2000.00,2000.00,1200.00),
(4,3,1,1.000,2000.00,2000.00,1200.00),
(5,5,1,1.000,2000.00,2000.00,1200.00),
(6,6,1,1.000,2000.00,2000.00,1200.00),
(7,8,1,1.000,2000.00,2000.00,1200.00),
(8,9,1,1.000,2000.00,2000.00,1200.00),
(9,9,1,1.000,2000.00,2000.00,1200.00),
(10,15,1,1.000,2000.00,2000.00,1200.00),
(11,17,1,1.000,2000.00,2000.00,1200.00),
(12,25,1,5.000,2000.00,10000.00,1200.00),
(13,36,1,1.000,2000.00,2000.00,1200.00),
(14,40,1,2.000,2000.00,4000.00,1200.00),
(15,41,1,2.000,2000.00,4000.00,1200.00),
(16,43,1,1.000,2000.00,2000.00,1200.00),
(17,44,1,1.000,2000.00,2000.00,1200.00),
(18,45,1,1.000,2000.00,2000.00,1200.00),
(19,47,1,1.000,2000.00,2000.00,1200.00),
(20,53,1,1.000,2000.00,2000.00,1200.00),
(21,54,1,1.000,2000.00,2000.00,1200.00),
(22,54,1,1.000,2000.00,2000.00,1200.00),
(23,56,1,1.000,2000.00,2000.00,1200.00),
(24,57,1,2.000,2000.00,4000.00,1200.00),
(25,58,1,1.000,2000.00,2000.00,1200.00),
(26,60,1,1.000,2000.00,2000.00,1200.00),
(27,60,1,1.000,2000.00,2000.00,1200.00),
(28,61,1,1.000,2000.00,2000.00,1200.00),
(29,68,1,1.000,2000.00,2000.00,1200.00),
(30,69,1,1.000,2000.00,2000.00,1200.00),
(31,69,1,1.000,2000.00,2000.00,1200.00),
(32,71,1,1.000,2000.00,2000.00,1200.00),
(33,72,1,1.000,2000.00,2000.00,1200.00),
(34,74,1,2.000,2000.00,4000.00,1200.00),
(35,78,1,1.000,2000.00,2000.00,1200.00),
(36,80,1,1.000,2000.00,2000.00,1200.00),
(37,84,1,1.000,2000.00,2000.00,1200.00),
(38,86,1,4.000,2000.00,8000.00,1200.00),
(39,91,1,1.000,2000.00,2000.00,1200.00),
(40,93,1,2.000,2000.00,4000.00,1200.00),
(41,96,1,2.000,2000.00,4000.00,1200.00),
(42,NULL,1,1.000,2000.00,2000.00,1200.00),
(43,NULL,1,1.000,2000.00,2000.00,1200.00),
(44,NULL,1,1.000,2000.00,2000.00,1200.00),
(45,NULL,1,1.000,2000.00,2000.00,1200.00),
(46,NULL,1,1.000,2000.00,2000.00,1200.00),
(47,NULL,1,1.000,2000.00,2000.00,1200.00),
(48,NULL,1,1.000,2000.00,2000.00,1200.00),
(49,NULL,1,1.000,2000.00,2000.00,1200.00),
(50,NULL,1,1.000,2000.00,2000.00,1200.00),
(51,114,1,1.000,2000.00,2000.00,1200.00),
(52,115,1,3.000,2000.00,6000.00,1200.00),
(53,116,1,2.000,2000.00,4000.00,1200.00),
(54,118,1,1.000,2000.00,2000.00,1200.00),
(55,120,1,1.000,2000.00,2000.00,1200.00),
(56,122,1,1.000,2000.00,2000.00,1200.00),
(57,124,1,1.000,2000.00,2000.00,1200.00),
(58,132,1,1.000,2000.00,2000.00,1200.00),
(59,147,6,1.000,174.00,174.00,100.00),
(60,147,6,1.000,174.00,174.00,100.00),
(61,147,6,1.000,174.00,174.00,100.00),
(62,147,6,1.000,174.00,174.00,100.00),
(63,148,6,1.000,174.00,174.00,100.00),
(64,148,6,1.000,174.00,174.00,100.00),
(65,149,6,1.000,174.00,174.00,100.00),
(66,158,1,4.000,2000.00,8000.00,1200.00),
(67,158,6,1.000,174.00,174.00,100.00),
(68,158,3,1.000,372.00,372.00,200.00),
(69,166,1,1.000,2000.00,2000.00,1200.00),
(70,167,1,1.000,2000.00,2000.00,1200.00),
(71,168,1,1.000,2000.00,2000.00,1200.00),
(72,169,1,1.000,2000.00,2000.00,1200.00),
(73,170,1,1.000,2000.00,2000.00,1200.00),
(74,172,1,1.000,2000.00,2000.00,1200.00),
(75,173,1,4.000,2000.00,8000.00,1200.00),
(76,174,1,4.000,2000.00,8000.00,1200.00),
(77,175,1,1.000,2000.00,2000.00,1200.00),
(78,176,1,1.000,2000.00,2000.00,1200.00),
(79,177,1,1.000,2000.00,2000.00,1200.00),
(80,178,1,1.000,2000.00,2000.00,1200.00),
(81,179,1,2.000,2000.00,4000.00,1200.00),
(82,180,1,1.000,2000.00,2000.00,1200.00),
(83,181,1,3.000,2000.00,6000.00,1200.00),
(84,182,1,4.000,2000.00,8000.00,1200.00),
(85,183,1,1.000,2000.00,2000.00,1200.00),
(86,184,1,1.000,2000.00,2000.00,1200.00),
(87,185,7,1.000,1740.00,1740.00,1000.00),
(88,186,1,1.000,2000.00,2000.00,1200.00),
(89,187,1,1.000,2000.00,2000.00,1200.00),
(90,188,1,1.000,2000.00,2000.00,1200.00),
(91,189,1,1.000,2000.00,2000.00,1200.00),
(92,190,1,1.000,2000.00,2000.00,1200.00),
(93,191,1,1.000,2000.00,2000.00,1200.00),
(94,192,1,1.000,2000.00,2000.00,1200.00),
(95,193,1,1.000,2000.00,2000.00,1200.00),
(96,194,1,1.000,2000.00,2000.00,1200.00),
(97,195,1,1.000,2000.00,2000.00,1200.00),
(98,196,1,1.000,2000.00,2000.00,1200.00),
(99,197,1,1.000,2000.00,2000.00,1200.00),
(100,198,1,1.000,2000.00,2000.00,1200.00),
(101,199,1,1.000,2000.00,2000.00,1200.00),
(102,200,1,1.000,2000.00,2000.00,1200.00),
(103,201,1,1.000,2000.00,2000.00,1200.00),
(104,202,4,1.000,174.00,174.00,100.00),
(105,203,4,4.000,174.00,696.00,100.00),
(106,204,2,3.000,1860.00,5580.00,1000.00),
(107,205,2,1.000,1860.00,1860.00,1000.00),
(108,206,2,1.000,1860.00,1860.00,1000.00),
(109,207,2,1.000,1860.00,1860.00,1000.00),
(110,208,2,1.000,1860.00,1860.00,1000.00),
(111,209,2,1.000,1860.00,1860.00,1000.00),
(112,210,3,1.000,372.00,372.00,200.00),
(113,211,3,1.000,2000.00,2000.00,1200.00),
(159,240,3,4.000,2000.00,8000.00,1200.00),
(161,242,3,1.000,2000.00,2000.00,1200.00),
(162,242,7,1.000,1740.00,1740.00,1000.00),
(163,242,2,1.000,1860.00,1860.00,1000.00),
(164,242,6,1.000,174.00,174.00,100.00),
(166,244,3,13.000,2000.00,26000.00,1200.00),
(167,245,4,4.000,174.00,696.00,100.00),
(168,245,2,1.000,1860.00,1860.00,1000.00),
(169,245,3,2.000,2000.00,4000.00,1200.00),
(170,246,4,1.000,174.00,174.00,100.00),
(171,246,3,1.000,2000.00,2000.00,1200.00),
(172,247,4,2.000,174.00,348.00,100.00),
(173,247,3,2.000,2000.00,4000.00,1200.00),
(174,248,4,2.000,174.00,348.00,100.00),
(175,248,3,1.000,2000.00,2000.00,1200.00),
(176,249,4,3.000,174.00,522.00,100.00),
(177,249,3,2.000,2000.00,4000.00,1200.00),
(178,250,4,2.000,174.00,348.00,100.00),
(179,250,3,2.000,2000.00,4000.00,1200.00);
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
) ENGINE=InnoDB AUTO_INCREMENT=251 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas`
--

LOCK TABLES `ventas` WRITE;
/*!40000 ALTER TABLE `ventas` DISABLE KEYS */;
INSERT INTO `ventas` VALUES
(1,'2026-04-04 11:47:29',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(2,'2026-04-04 11:52:54',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(3,'2026-04-05 15:05:15',NULL,8000.00,'TICKET','COMPLETADA',NULL,1,1),
(4,'2026-04-05 15:05:51',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(5,'2026-04-05 15:14:54',NULL,2000.00,'TICKET','COMPLETADA',NULL,1,1),
(6,'2026-04-05 15:15:11',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(7,'2026-04-05 15:16:09',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(8,'2026-04-05 15:27:28',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(9,'2026-04-05 15:28:44',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(10,'2026-04-05 15:31:16',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(11,'2026-04-05 15:32:28',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(12,'2026-04-05 15:35:09',NULL,0.00,NULL,'PENDIENTE',NULL,1,1),
(13,'2026-04-05 15:35:43',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(14,'2026-04-05 15:39:53',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(15,'2026-04-05 15:52:08',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(16,'2026-04-05 15:58:59',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(17,'2026-04-05 16:01:21',NULL,0.00,'TICKET','COMPLETADA',NULL,1,1),
(18,'2026-04-05 16:08:41',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(19,'2026-04-05 16:09:04',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(20,'2026-04-05 16:16:36',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(21,'2026-04-05 16:19:36',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(22,'2026-04-05 16:23:51',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(23,'2026-04-05 16:28:32',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(24,'2026-04-05 16:31:18',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(25,'2026-04-05 16:33:47',NULL,10000.00,NULL,'COMPLETADA',NULL,1,1),
(26,'2026-04-05 16:34:03',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(27,'2026-04-05 17:18:17',NULL,0.00,NULL,'ABIERTA',NULL,1,1),
(28,'2026-04-05 17:19:00',NULL,0.00,NULL,'ABIERTA',NULL,1,1),
(29,'2026-04-05 17:20:22',NULL,0.00,NULL,'ABIERTA',NULL,1,1),
(30,'2026-04-05 17:27:07',NULL,0.00,NULL,'ABIERTA',NULL,1,1),
(31,'2026-04-05 17:30:23',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(32,'2026-04-05 17:31:46',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(33,'2026-04-05 17:32:12',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(34,'2026-04-05 17:32:33',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(35,'2026-04-05 17:33:24',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(36,'2026-04-05 17:33:57',NULL,2000.00,NULL,'COMPLETADA',NULL,1,1),
(37,'2026-04-05 17:34:20',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(38,'2026-04-05 17:35:28',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(39,'2026-04-05 17:35:59',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(40,'2026-04-05 17:56:00',NULL,4000.00,NULL,'COMPLETADA',NULL,1,1),
(41,'2026-04-05 18:00:53',NULL,4000.00,NULL,'COMPLETADA',NULL,1,1),
(42,'2026-04-05 18:01:02',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(43,'2026-04-05 18:01:41',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(44,'2026-04-05 18:02:19',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(45,'2026-04-05 18:02:36',NULL,2000.00,NULL,'COMPLETADA',NULL,1,1),
(46,'2026-04-05 18:02:38',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(47,'2026-04-05 18:06:05',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(48,'2026-04-05 18:12:55',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(49,'2026-04-05 18:19:40',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(50,'2026-04-05 18:19:49',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(51,'2026-04-05 18:19:58',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(52,'2026-04-05 18:24:02',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(53,'2026-04-05 18:36:22',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(54,'2026-04-05 18:40:21',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(55,'2026-04-05 18:41:50',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(56,'2026-04-05 18:41:59',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(57,'2026-04-05 18:42:11',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(58,'2026-04-05 18:42:24',NULL,2000.00,NULL,'COMPLETADA',NULL,1,1),
(59,'2026-04-05 18:42:27',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(60,'2026-04-05 18:43:29',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(61,'2026-04-05 18:44:02',NULL,2000.00,NULL,'COMPLETADA',NULL,1,1),
(62,'2026-04-05 18:44:12',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(63,'2026-04-06 18:39:45',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(64,'2026-04-06 18:41:43',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(65,'2026-04-06 18:43:45',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(66,'2026-04-06 18:45:43',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(67,'2026-04-06 18:47:40',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(68,'2026-04-06 18:51:00',NULL,2000.00,NULL,'COMPLETADA',NULL,1,1),
(69,'2026-04-06 18:57:23',NULL,2000.00,NULL,'COMPLETADA',NULL,1,1),
(70,'2026-04-06 18:58:40',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(71,'2026-04-06 19:01:04',NULL,2000.00,NULL,'COMPLETADA',NULL,1,1),
(72,'2026-04-06 19:03:43',NULL,2000.00,NULL,'COMPLETADA',NULL,1,1),
(73,'2026-04-06 19:03:53',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(74,'2026-04-06 19:06:33',NULL,4000.00,NULL,'COMPLETADA',NULL,1,1),
(75,'2026-04-06 19:06:48',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(76,'2026-04-06 19:08:35',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(77,'2026-04-06 19:09:16',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(78,'2026-04-06 19:11:29',NULL,2000.00,NULL,'COMPLETADA',NULL,1,1),
(79,'2026-04-06 19:11:49',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(80,'2026-04-06 19:17:51',NULL,2000.00,NULL,'COMPLETADA',NULL,1,1),
(81,'2026-04-06 19:18:06',NULL,0.00,NULL,'COMPLETADA',NULL,1,1),
(82,'2026-04-06 19:30:45',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(83,'2026-04-06 19:33:19',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(84,'2026-04-06 19:35:13',NULL,2000.00,NULL,'COMPLETADA',2000.00,1,1),
(85,'2026-04-06 19:35:25',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(86,'2026-04-06 19:37:47',NULL,8000.00,NULL,'COMPLETADA',8000.00,1,1),
(87,'2026-04-06 19:38:01',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(88,'2026-04-06 21:00:13',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(89,'2026-04-06 21:03:38',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(90,'2026-04-06 21:08:41',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(91,'2026-04-06 21:14:07',NULL,2000.00,NULL,'COMPLETADA',2000.00,1,1),
(92,'2026-04-06 21:14:25',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(93,'2026-04-06 21:23:32',NULL,4000.00,NULL,'COMPLETADA',4000.00,1,1),
(94,'2026-04-06 21:24:20',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(95,'2026-04-06 21:24:49',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(96,'2026-04-06 21:27:02',NULL,4000.00,NULL,'COMPLETADA',4000.00,1,1),
(97,'2026-04-06 21:27:38',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(98,'2026-04-06 21:29:12',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(99,'2026-04-06 21:36:37',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(100,'2026-04-06 21:54:28',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(101,'2026-04-06 22:07:55',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(102,'2026-04-06 22:10:22',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(103,'2026-04-06 22:17:12',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(104,'2026-04-06 22:17:14',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(105,'2026-04-06 22:18:18',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(106,'2026-04-06 22:18:25',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(107,'2026-04-06 22:18:36',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(108,'2026-04-06 22:20:37',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(109,'2026-04-06 22:20:39',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(110,'2026-04-06 22:21:01',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(111,'2026-04-06 22:21:45',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(112,'2026-04-06 22:23:08',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(113,'2026-04-06 22:26:19',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(114,'2026-04-08 16:30:18',NULL,2000.00,NULL,'COMPLETADA',2000.00,NULL,1),
(115,'2026-04-08 16:30:20',NULL,6000.00,NULL,'COMPLETADA',6000.00,NULL,1),
(116,'2026-04-08 16:31:53',NULL,4000.00,NULL,'COMPLETADA',4000.00,NULL,1),
(117,'2026-04-08 16:33:02',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,1),
(118,'2026-04-08 16:33:32',NULL,2000.00,NULL,'COMPLETADA',2000.00,NULL,1),
(119,'2026-04-08 16:33:39',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,1),
(120,'2026-04-08 16:34:27',NULL,2000.00,NULL,'COMPLETADA',2000.00,NULL,1),
(121,'2026-04-08 16:34:34',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,1),
(122,'2026-04-08 16:36:41',NULL,2000.00,NULL,'COMPLETADA',2000.00,NULL,1),
(123,'2026-04-08 16:36:47',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,1),
(124,'2026-04-08 16:49:33',NULL,2000.00,NULL,'COMPLETADA',2000.00,NULL,1),
(125,'2026-04-08 16:49:40',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,1),
(126,'2026-04-08 16:55:21',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,1),
(127,'2026-04-08 17:39:11',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,1),
(128,'2026-04-08 18:14:35',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,1),
(129,'2026-04-08 18:15:41',NULL,0.00,NULL,'COMPLETADA',0.00,NULL,1),
(130,'2026-04-08 20:06:05',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(131,'2026-04-08 20:11:20',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(132,'2026-04-08 20:16:47',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(133,'2026-04-08 20:21:07',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(134,'2026-04-08 20:53:26',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(135,'2026-04-08 20:53:35',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(136,'2026-04-08 21:02:02',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(137,'2026-04-08 21:37:46',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(138,'2026-04-09 15:58:50',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
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
(156,'2026-04-13 21:33:05',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(157,'2026-04-13 21:43:51',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(158,'2026-04-13 21:44:12',NULL,8546.00,NULL,'COMPLETADA',3446.00,1,1),
(159,'2026-04-13 21:44:36',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(160,'2026-04-13 22:02:19',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(161,'2026-04-13 22:05:22',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(162,'2026-04-14 11:33:45',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(163,'2026-04-14 11:34:00',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(164,'2026-04-14 11:38:59',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(165,'2026-04-14 12:18:37',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(166,'2026-04-14 12:24:40',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(167,'2026-04-14 12:32:05',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(168,'2026-04-14 12:32:32',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(169,'2026-04-14 12:37:48',NULL,2000.00,NULL,'COMPLETADA',2000.00,1,1),
(170,'2026-04-14 12:40:01',NULL,2000.00,NULL,'COMPLETADA',2000.00,1,1),
(171,'2026-04-14 12:40:57',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(172,'2026-04-14 21:01:16',NULL,2000.00,NULL,'COMPLETADA',2000.00,1,1),
(173,'2026-04-14 21:11:48',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(174,'2026-04-14 21:14:16',NULL,0.00,NULL,'COMPLETADA',0.00,1,1),
(175,'2026-04-14 21:14:29',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(176,'2026-04-14 21:36:19',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(177,'2026-04-14 21:37:05',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(178,'2026-04-14 21:39:12',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(179,'2026-04-14 21:57:39',NULL,4000.00,NULL,'COMPLETADA',1600.00,1,1),
(180,'2026-04-14 22:51:35',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(181,'2026-04-14 22:52:57',1,6000.00,NULL,'COMPLETADA',2400.00,1,1),
(182,'2026-04-15 10:33:54',NULL,8000.00,NULL,'COMPLETADA',3200.00,1,1),
(183,'2026-04-15 11:00:58',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(184,'2026-04-15 11:02:46',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(185,'2026-04-15 11:03:40',NULL,1740.00,NULL,'COMPLETADA',740.00,1,1),
(186,'2026-04-15 11:03:53',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(187,'2026-04-15 11:19:03',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(188,'2026-04-15 11:24:55',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(189,'2026-04-15 11:26:27',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(190,'2026-04-15 11:29:35',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(191,'2026-04-15 11:30:33',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(192,'2026-04-15 12:04:48',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(193,'2026-04-15 12:16:51',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(194,'2026-04-15 12:17:47',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(195,'2026-04-15 12:18:43',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(196,'2026-04-15 12:28:51',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(197,'2026-04-15 12:35:49',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(198,'2026-04-15 16:03:56',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(199,'2026-04-15 16:06:41',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(200,'2026-04-15 16:07:05',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(201,'2026-04-15 16:09:36',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(202,'2026-04-15 16:23:57',NULL,174.00,NULL,'COMPLETADA',74.00,1,1),
(203,'2026-04-15 16:25:03',NULL,696.00,NULL,'COMPLETADA',296.00,1,1),
(204,'2026-04-15 17:43:45',NULL,5580.00,NULL,'COMPLETADA',2580.00,1,1),
(205,'2026-04-15 17:44:17',NULL,1860.00,NULL,'COMPLETADA',860.00,1,1),
(206,'2026-04-15 17:48:04',NULL,1860.00,NULL,'COMPLETADA',860.00,1,1),
(207,'2026-04-16 18:02:53',NULL,1860.00,NULL,'COMPLETADA',860.00,1,1),
(208,'2026-04-16 18:07:36',NULL,1860.00,NULL,'COMPLETADA',860.00,1,1),
(209,'2026-04-17 10:52:54',NULL,1860.00,NULL,'COMPLETADA',860.00,1,1),
(210,'2026-04-24 16:27:27',NULL,372.00,NULL,'COMPLETADA',172.00,1,1),
(211,'2026-04-27 09:28:13',NULL,2000.00,NULL,'COMPLETADA',800.00,1,1),
(240,'2026-04-27 21:10:25',NULL,8000.00,NULL,'COMPLETADA',3200.00,1,1),
(242,'2026-04-27 21:13:39',NULL,5774.00,NULL,'COMPLETADA',2474.00,1,1),
(244,'2026-04-27 21:16:17',NULL,26000.00,NULL,'COMPLETADA',10400.00,1,1),
(245,'2026-04-27 22:02:13',NULL,6556.00,NULL,'COMPLETADA',2756.00,1,1),
(246,'2026-04-27 22:09:00',NULL,2174.00,NULL,'COMPLETADA',874.00,1,1),
(247,'2026-04-27 22:22:08',NULL,4348.00,NULL,'COMPLETADA',1748.00,1,1),
(248,'2026-04-27 22:28:00',NULL,2348.00,NULL,'COMPLETADA',948.00,1,1),
(249,'2026-04-27 22:28:23',NULL,4522.00,NULL,'COMPLETADA',1822.00,1,1),
(250,'2026-04-27 22:33:34',NULL,4348.00,NULL,'COMPLETADA',1748.00,1,1);
/*!40000 ALTER TABLE `ventas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_campaigns`
--

DROP TABLE IF EXISTS `whatsapp_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `whatsapp_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `mensaje` text NOT NULL,
  `segmento` varchar(100) DEFAULT 'todos',
  `fecha_programada` datetime NOT NULL,
  `fecha_envio` datetime DEFAULT NULL,
  `estado` enum('pendiente','enviando','completado','cancelado','fallido') DEFAULT 'pendiente',
  `total_clientes` int(11) DEFAULT 0,
  `mensajes_enviados` int(11) DEFAULT 0,
  `mensajes_fallidos` int(11) DEFAULT 0,
  `creado_por` int(11) NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_campaigns`
--

LOCK TABLES `whatsapp_campaigns` WRITE;
/*!40000 ALTER TABLE `whatsapp_campaigns` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_campaigns` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_config`
--

DROP TABLE IF EXISTS `whatsapp_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `whatsapp_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `api_key` varchar(255) NOT NULL,
  `api_secret` varchar(255) NOT NULL,
  `phone_number` varchar(50) NOT NULL,
  `sid` varchar(255) NOT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `empresa_id` (`empresa_id`),
  CONSTRAINT `whatsapp_config_ibfk_1` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_config`
--

LOCK TABLES `whatsapp_config` WRITE;
/*!40000 ALTER TABLE `whatsapp_config` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_messages`
--

DROP TABLE IF EXISTS `whatsapp_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `whatsapp_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campaign_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `telefono` varchar(50) NOT NULL,
  `mensaje` text NOT NULL,
  `estado` enum('pendiente','enviado','entregado','leido','fallido') DEFAULT 'pendiente',
  `mensaje_sid` varchar(255) DEFAULT NULL,
  `error_mensaje` text DEFAULT NULL,
  `fecha_envio` timestamp NULL DEFAULT NULL,
  `fecha_entrega` timestamp NULL DEFAULT NULL,
  `fecha_lectura` timestamp NULL DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_campaign` (`campaign_id`),
  KEY `idx_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_messages`
--

LOCK TABLES `whatsapp_messages` WRITE;
/*!40000 ALTER TABLE `whatsapp_messages` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `whatsapp_templates`
--

DROP TABLE IF EXISTS `whatsapp_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `whatsapp_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `empresa_id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `asunto` varchar(255) DEFAULT NULL,
  `mensaje` text NOT NULL,
  `variables` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1,
  `creado_por` int(11) NOT NULL,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `fecha_actualizacion` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_empresa` (`empresa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `whatsapp_templates`
--

LOCK TABLES `whatsapp_templates` WRITE;
/*!40000 ALTER TABLE `whatsapp_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `whatsapp_templates` ENABLE KEYS */;
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

-- Dump completed on 2026-04-28 11:25:16
