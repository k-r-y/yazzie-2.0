-- MariaDB dump 10.19  Distrib 10.4.28-MariaDB, for osx10.10 (x86_64)
--
-- Host: localhost    Database: yazzie
-- ------------------------------------------------------
-- Server version	10.4.28-MariaDB

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
-- Table structure for table `archived_bookings`
--

DROP TABLE IF EXISTS `archived_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `archived_bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `original_id` int(10) unsigned NOT NULL COMMENT 'Original booking ID before archive',
  `client_name` varchar(100) NOT NULL,
  `client_phone` varchar(20) DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `event_location` text DEFAULT NULL,
  `pax_count` int(10) unsigned NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_status` enum('unpaid','partial','paid') NOT NULL,
  `notes` text DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_by` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_archived_original` (`original_id`),
  KEY `idx_event_date` (`event_date`),
  KEY `idx_archived_by` (`archived_by`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `archived_bookings`
--

LOCK TABLES `archived_bookings` WRITE;
/*!40000 ALTER TABLE `archived_bookings` DISABLE KEYS */;
INSERT INTO `archived_bookings` VALUES (1,3,'Dela Torre Family','09201234567','2026-04-04','10:00:00','GMA, Cavite',30,16500.00,16500.00,'paid',NULL,'2026-04-12 11:19:11',1),(2,11,'vfsvfsvfs','09646465464','2026-04-20',NULL,'dfsvfsf',300,35000.00,35000.00,'paid',NULL,'2026-04-13 13:03:49',1),(3,10,'joven kufal','09878263972','2026-04-16',NULL,'Dasmariñas City',300,35000.00,35000.00,'paid','asdasd','2026-04-15 14:15:25',1),(4,15,'sadasdas','09902821893','2026-04-18',NULL,'Dasmariñas',50,10000.00,10000.00,'paid',NULL,'2026-04-15 23:51:35',1);
/*!40000 ALTER TABLE `archived_bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_log`
--

DROP TABLE IF EXISTS `audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL COMMENT 'Who performed the action',
  `action` varchar(60) NOT NULL COMMENT 'e.g. payment_recorded, booking_confirmed',
  `entity` varchar(30) NOT NULL COMMENT 'booking | payment | client | job_order',
  `entity_id` int(10) unsigned NOT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'State before the change' CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'State after the change' CHECK (json_valid(`new_value`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_al_entity` (`entity`,`entity_id`),
  KEY `idx_al_user` (`user_id`),
  KEY `idx_al_action` (`action`),
  KEY `idx_al_ts` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_log`
--

LOCK TABLES `audit_log` WRITE;
/*!40000 ALTER TABLE `audit_log` DISABLE KEYS */;
INSERT INTO `audit_log` VALUES (1,1,'payment_recorded','payment',1,NULL,'{\"booking_id\":3,\"amount\":4555,\"method\":\"cash\"}','::1','2026-04-11 13:03:05'),(2,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"4\",\"amount\":38441.669999999998253770172595977783203125,\"notes\":\"Downpayment\"}','::1','2026-04-11 15:18:15'),(3,1,'booking_created','booking',4,NULL,'{\"client_id\":1,\"event_date\":\"2026-04-23\",\"total_cost\":76883.330000000001746229827404022216796875,\"booking_status\":\"confirmed\"}','::1','2026-04-11 15:18:15'),(4,1,'payment_recorded','payment',3,NULL,'{\"booking_id\":4,\"amount\":38441.66000000000349245965480804443359375,\"method\":\"cash\"}','::1','2026-04-11 15:19:03'),(5,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"5\",\"amount\":5000,\"notes\":\"Downpayment\"}','::1','2026-04-11 15:25:53'),(6,1,'booking_created','booking',5,NULL,'{\"client_id\":4,\"event_date\":\"2026-04-16\",\"total_cost\":10000,\"booking_status\":\"confirmed\"}','::1','2026-04-11 15:25:53'),(7,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"6\",\"amount\":17500,\"notes\":\"Downpayment\"}','::1','2026-04-11 15:39:04'),(8,1,'booking_created','booking',6,NULL,'{\"client_id\":5,\"event_date\":\"2026-04-30\",\"total_cost\":35000,\"booking_status\":\"confirmed\"}','::1','2026-04-11 15:39:04'),(9,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"7\",\"amount\":17500,\"notes\":\"Downpayment\"}','::1','2026-04-11 15:57:07'),(10,1,'booking_created','booking',7,NULL,'{\"client_id\":6,\"event_date\":\"2026-04-15\",\"total_cost\":30720,\"booking_status\":\"confirmed\"}','::1','2026-04-11 15:57:07'),(11,1,'payment_recorded','payment',7,NULL,'{\"booking_id\":3,\"amount\":11945,\"method\":\"cash\"}','::1','2026-04-12 11:19:03'),(12,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"8\",\"amount\":14625,\"notes\":\"Downpayment\"}','::1','2026-04-12 11:27:46'),(13,1,'booking_created','booking',8,NULL,'{\"client_id\":6,\"event_date\":\"2026-04-25\",\"total_cost\":29250,\"booking_status\":\"confirmed\"}','::1','2026-04-12 11:27:46'),(14,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"9\",\"amount\":17500,\"notes\":\"Downpayment\"}','::1','2026-04-12 12:37:44'),(15,1,'booking_created','booking',9,NULL,'{\"client_id\":4,\"event_date\":\"2026-04-24\",\"total_cost\":35000,\"booking_status\":\"confirmed\"}','::1','2026-04-12 12:37:44'),(16,1,'booking_status_changed','booking',9,'{\"booking_status\":\"confirmed\"}','{\"booking_status\":\"cancelled\"}','::1','2026-04-13 00:21:54'),(17,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"10\",\"amount\":17500,\"notes\":\"Downpayment\"}','::1','2026-04-13 02:43:35'),(18,1,'booking_created','booking',10,NULL,'{\"client_id\":13,\"event_date\":\"2026-04-16\",\"total_cost\":35000,\"booking_status\":\"confirmed\"}','::1','2026-04-13 02:43:35'),(19,1,'payment_recorded','payment',11,NULL,'{\"booking_id\":10,\"amount\":17500,\"method\":\"cash\"}','::1','2026-04-13 02:44:37'),(20,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"11\",\"amount\":17500,\"notes\":\"Downpayment\"}','::1','2026-04-13 07:51:19'),(21,1,'booking_created','booking',11,NULL,'{\"client_id\":14,\"event_date\":\"2026-04-20\",\"total_cost\":35000,\"booking_status\":\"confirmed\"}','::1','2026-04-13 07:51:19'),(22,1,'payment_recorded','payment',13,NULL,'{\"booking_id\":11,\"amount\":17500,\"method\":\"cash\"}','::1','2026-04-13 07:53:04'),(23,1,'booking_status_changed','booking',11,'{\"booking_status\":\"confirmed\"}','{\"booking_status\":\"completed\"}','::1','2026-04-13 07:59:59'),(24,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"12\",\"amount\":10000,\"notes\":\"Downpayment\"}','::1','2026-04-13 23:57:21'),(25,1,'booking_created','booking',12,NULL,'{\"client_id\":15,\"event_date\":\"2026-04-20\",\"total_cost\":20000,\"booking_status\":\"confirmed\"}','::1','2026-04-13 23:57:21'),(26,1,'payment_recorded','payment',15,NULL,'{\"booking_id\":12,\"amount\":10000,\"method\":\"cash\"}','::1','2026-04-14 00:02:57'),(27,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"13\",\"amount\":5000,\"notes\":\"Downpayment\"}','::1','2026-04-14 12:07:51'),(28,1,'booking_created','booking',13,NULL,'{\"client_id\":11,\"event_date\":\"2026-04-29\",\"total_cost\":10000,\"booking_status\":\"confirmed\"}','::1','2026-04-14 12:07:51'),(29,1,'payment_recorded','payment',17,NULL,'{\"booking_id\":8,\"amount\":14625,\"method\":\"cash\"}','::1','2026-04-14 12:08:35'),(30,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"14\",\"amount\":5400,\"notes\":\"Downpayment\"}','::1','2026-04-14 16:27:56'),(31,1,'booking_created','booking',14,NULL,'{\"client_id\":11,\"event_date\":\"2026-05-07\",\"total_cost\":10000,\"booking_status\":\"confirmed\"}','::1','2026-04-14 16:27:56'),(32,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"15\",\"amount\":5000,\"notes\":\"Downpayment\"}','::1','2026-04-14 16:45:32'),(33,1,'booking_created','booking',15,NULL,'{\"client_id\":16,\"event_date\":\"2026-04-18\",\"total_cost\":10000,\"booking_status\":\"confirmed\"}','::1','2026-04-14 16:45:32'),(34,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"16\",\"amount\":5000,\"notes\":\"Downpayment\"}','::1','2026-04-14 17:00:37'),(35,1,'booking_created','booking',16,NULL,'{\"client_id\":5,\"event_date\":\"2026-05-29\",\"total_cost\":10000,\"booking_status\":\"confirmed\"}','::1','2026-04-14 17:00:37'),(36,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"17\",\"amount\":5000,\"notes\":\"Downpayment\"}','::1','2026-04-14 17:06:33'),(37,1,'booking_created','booking',17,NULL,'{\"client_id\":16,\"event_date\":\"2026-05-22\",\"total_cost\":10000,\"booking_status\":\"confirmed\"}','::1','2026-04-14 17:06:33'),(38,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"18\",\"amount\":5000,\"notes\":\"Downpayment\"}','::1','2026-04-15 13:38:20'),(39,1,'booking_created','booking',18,NULL,'{\"client_id\":5,\"event_date\":\"2026-06-30\",\"total_cost\":10000,\"booking_status\":\"confirmed\"}','::1','2026-04-15 13:38:20'),(40,1,'booking_status_changed','booking',10,'{\"booking_status\":\"confirmed\"}','{\"booking_status\":\"completed\"}','::1','2026-04-15 14:15:18'),(41,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"19\",\"amount\":8800,\"notes\":\"Downpayment\"}','::1','2026-04-15 14:24:13'),(42,1,'booking_created','booking',19,NULL,'{\"client_id\":15,\"event_date\":\"2026-04-21\",\"total_cost\":17600,\"booking_status\":\"confirmed\"}','::1','2026-04-15 14:24:13'),(43,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"20\",\"amount\":5000,\"notes\":\"Downpayment\"}','::1','2026-04-15 14:54:59'),(44,1,'booking_created','booking',20,NULL,'{\"client_id\":15,\"event_date\":\"2026-05-30\",\"total_cost\":10000,\"booking_status\":\"confirmed\"}','::1','2026-04-15 14:54:59'),(45,1,'booking_status_changed','booking',15,'{\"booking_status\":\"confirmed\"}','{\"booking_status\":\"completed\"}','::1','2026-04-15 23:51:02'),(46,1,'payment_recorded','payment',25,NULL,'{\"booking_id\":15,\"amount\":5000,\"method\":\"cash\"}','::1','2026-04-15 23:51:27'),(47,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"21\",\"amount\":5000,\"notes\":\"Downpayment\"}','::1','2026-04-16 00:11:59'),(48,1,'booking_created','booking',21,NULL,'{\"client_id\":17,\"event_date\":\"2026-04-19\",\"total_cost\":10000,\"booking_status\":\"confirmed\"}','::1','2026-04-16 00:11:59'),(49,1,'payment_recorded','payment',27,NULL,'{\"booking_id\":21,\"amount\":5000,\"method\":\"cash\"}','::1','2026-04-16 00:25:50'),(50,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"22\",\"amount\":15000,\"notes\":\"Downpayment\"}','::1','2026-04-16 00:41:29'),(51,1,'booking_created','booking',22,NULL,'{\"client_id\":18,\"event_date\":\"2026-04-22\",\"total_cost\":30000,\"booking_status\":\"confirmed\"}','::1','2026-04-16 00:41:29'),(52,1,'payment_recorded','payment',29,NULL,'{\"booking_id\":22,\"amount\":15000,\"method\":\"maya\"}','::1','2026-04-16 00:43:07'),(53,1,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"23\",\"amount\":5000,\"notes\":\"Downpayment\"}','::1','2026-04-16 04:01:10'),(54,1,'booking_created','booking',23,NULL,'{\"client_id\":11,\"event_date\":\"2026-04-23\",\"total_cost\":10000,\"booking_status\":\"confirmed\"}','::1','2026-04-16 04:01:10'),(55,1,'client_created','client',20,NULL,'{\"name\":\"adasda\",\"email\":\"pawcasiano@kld.edu.ph\"}','::1','2026-04-18 13:56:45'),(56,1,'client_created','client',21,NULL,'{\"name\":\"kry\",\"email\":\"casianoprince5@gmail.com\"}','::1','2026-04-18 15:23:06'),(57,20,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"56\",\"amount\":24950.00999999999839928932487964630126953125,\"notes\":\"Downpayment\"}','::1','2026-04-18 16:03:27'),(58,20,'booking_created','booking',56,NULL,'{\"client_id\":21,\"event_date\":\"2026-04-22\",\"total_cost\":83166.669999999998253770172595977783203125,\"booking_status\":\"confirmed\"}','::1','2026-04-18 16:03:27'),(59,20,'payment_recorded','payment',32,NULL,'{\"booking_id\":56,\"amount\":58216.66000000000349245965480804443359375,\"method\":\"cash\"}','::1','2026-04-18 16:08:24'),(60,20,'payment_deleted','payment',31,'{\"booking_id\":56}',NULL,'::1','2026-04-18 16:08:41'),(61,20,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"57\",\"amount\":18879,\"notes\":\"Downpayment\"}','::1','2026-04-18 16:19:05'),(62,20,'booking_created','booking',57,NULL,'{\"client_id\":21,\"event_date\":\"2030-04-25\",\"total_cost\":62930,\"booking_status\":\"confirmed\"}','::1','2026-04-18 16:19:05'),(63,20,'payment_recorded','payment',0,NULL,'{\"booking_id\":\"58\",\"amount\":74323.330000000001746229827404022216796875,\"notes\":\"Downpayment\"}','::1','2026-04-18 17:19:23'),(64,20,'booking_created','booking',58,NULL,'{\"client_id\":21,\"event_date\":\"2026-04-20\",\"total_cost\":74323.330000000001746229827404022216796875,\"booking_status\":\"confirmed\"}','::1','2026-04-18 17:19:23'),(65,21,'client_created','client',22,NULL,'{\"name\":\"Prince Andrew Casiano\",\"email\":\"casianoprince5@gmail.com\"}','::1','2026-04-19 04:35:48');
/*!40000 ALTER TABLE `audit_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_breakages`
--

DROP TABLE IF EXISTS `booking_breakages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_breakages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `equipment_id` int(10) unsigned NOT NULL,
  `quantity` int(10) unsigned NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL COMMENT 'Snapshotted replacement cost at the time of logging',
  `total_cost` decimal(10,2) NOT NULL COMMENT 'qty * unit_price',
  `notes` varchar(255) DEFAULT NULL,
  `logged_by` int(10) unsigned NOT NULL,
  `logged_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bb_booking` (`booking_id`),
  KEY `idx_bb_equipment` (`equipment_id`),
  KEY `fk_bb_logger` (`logged_by`),
  CONSTRAINT `fk_bb_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bb_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bb_logger` FOREIGN KEY (`logged_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_breakages`
--

LOCK TABLES `booking_breakages` WRITE;
/*!40000 ALTER TABLE `booking_breakages` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_breakages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_cancellations`
--

DROP TABLE IF EXISTS `booking_cancellations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_cancellations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `requested_by` int(10) unsigned NOT NULL,
  `approved_by` int(10) unsigned DEFAULT NULL,
  `status` enum('requested','approved','rejected') NOT NULL DEFAULT 'requested',
  `reason` varchar(255) DEFAULT NULL,
  `policy_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`policy_json`)),
  `total_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `deposit_forfeit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `refundable_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `forfeited_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `refund_status` enum('pending','processed','waived') NOT NULL DEFAULT 'pending',
  `refund_method` enum('cash','gcash','maya','bank_transfer') DEFAULT NULL,
  `refund_reference` varchar(100) DEFAULT NULL,
  `refund_processed_at` timestamp NULL DEFAULT NULL,
  `refund_processed_by` int(10) unsigned DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cancel_booking` (`booking_id`),
  KEY `idx_cancel_status` (`status`),
  KEY `idx_cancel_created` (`created_at`),
  KEY `fk_cancel_requester` (`requested_by`),
  KEY `fk_cancel_approver` (`approved_by`),
  CONSTRAINT `fk_cancel_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_cancel_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_cancel_requester` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_cancellations`
--

LOCK TABLES `booking_cancellations` WRITE;
/*!40000 ALTER TABLE `booking_cancellations` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_cancellations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_custom_items`
--

DROP TABLE IF EXISTS `booking_custom_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_custom_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` enum('main','dessert','other') NOT NULL DEFAULT 'other',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_bci_booking` (`booking_id`),
  CONSTRAINT `fk_bci_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_custom_items`
--

LOCK TABLES `booking_custom_items` WRITE;
/*!40000 ALTER TABLE `booking_custom_items` DISABLE KEYS */;
INSERT INTO `booking_custom_items` VALUES (1,56,'lechon','other',9000.00,NULL,'2026-04-18 16:03:27'),(2,57,'Buko Pandan','other',10000.00,NULL,'2026-04-18 16:19:05');
/*!40000 ALTER TABLE `booking_custom_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_dishes`
--

DROP TABLE IF EXISTS `booking_dishes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_dishes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `dish_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booking_dish` (`booking_id`,`dish_id`),
  KEY `idx_bd_booking` (`booking_id`),
  KEY `idx_bd_dish` (`dish_id`),
  CONSTRAINT `fk_bd_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bd_dish` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=205 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_dishes`
--

LOCK TABLES `booking_dishes` WRITE;
/*!40000 ALTER TABLE `booking_dishes` DISABLE KEYS */;
INSERT INTO `booking_dishes` VALUES (184,56,2),(185,56,4),(186,56,5),(187,56,7),(188,56,31),(189,56,65),(190,56,67),(191,57,7),(192,57,15),(193,57,26),(194,57,43),(195,57,50),(196,57,65),(197,57,67),(198,58,9),(199,58,13),(200,58,36),(201,58,55),(202,58,61),(203,58,62),(204,58,67);
/*!40000 ALTER TABLE `booking_dishes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `booking_staff`
--

DROP TABLE IF EXISTS `booking_staff`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `booking_staff` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `staff_id` int(10) unsigned NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'Staff',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_by` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_booking_staff` (`booking_id`,`staff_id`),
  KEY `idx_staff_id` (`staff_id`),
  KEY `fk_bs_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_bs_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bs_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bs_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `booking_staff`
--

LOCK TABLES `booking_staff` WRITE;
/*!40000 ALTER TABLE `booking_staff` DISABLE KEYS */;
/*!40000 ALTER TABLE `booking_staff` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bookings`
--

DROP TABLE IF EXISTS `bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bookings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `package_id` int(10) unsigned DEFAULT NULL,
  `event_type` varchar(50) DEFAULT 'Wedding',
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `actual_start_time` time DEFAULT NULL,
  `actual_end_time` time DEFAULT NULL,
  `overtime_minutes` int(10) unsigned DEFAULT 0,
  `overtime_rate` decimal(10,2) DEFAULT 200.00,
  `overtime_total` decimal(10,2) DEFAULT 0.00,
  `event_location` text DEFAULT NULL,
  `pax_count` int(10) unsigned NOT NULL DEFAULT 1,
  `base_pax` int(10) unsigned DEFAULT NULL,
  `extra_pax` int(10) unsigned DEFAULT 0,
  `base_price` decimal(10,2) DEFAULT NULL,
  `extra_cost` decimal(10,2) DEFAULT 0.00,
  `transport_fee` decimal(10,2) DEFAULT 0.00,
  `surcharge_total` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Sum of all custom_items price',
  `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `booking_status` enum('inquiry','pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'inquiry',
  `is_archived` tinyint(1) NOT NULL DEFAULT 0,
  `archived_at` timestamp NULL DEFAULT NULL,
  `archived_by` int(10) unsigned DEFAULT NULL,
  `invoice_token` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `event_report_notes` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `report_submitted_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `report_submitted_at` timestamp NULL DEFAULT NULL,
  `dietary_notes` text DEFAULT NULL COMMENT 'Allergy notes and special dietary requests (e.g. less salt, no pork, no fish)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_event_date` (`event_date`),
  KEY `idx_client` (`client_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_pay_status` (`payment_status`),
  KEY `idx_book_status` (`booking_status`),
  KEY `idx_package_id` (`package_id`),
  KEY `idx_event_date` (`event_date`),
  KEY `idx_bookings_archived` (`is_archived`,`event_date`),
  KEY `idx_archived` (`is_archived`),
  CONSTRAINT `fk_bookings_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_bookings_package2` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bookings_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=59 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bookings`
--

LOCK TABLES `bookings` WRITE;
/*!40000 ALTER TABLE `bookings` DISABLE KEYS */;
INSERT INTO `bookings` VALUES (56,21,8,'basta','2026-04-22',NULL,NULL,NULL,0,200.00,0.00,'dasma',89,75,NULL,62500.00,11666.67,0.00,9000.00,83166.67,58216.66,'partial','confirmed',0,NULL,NULL,'0242396d83484a6ab2a3121ed1804919','asdasda',NULL,20,NULL,'2026-04-18 16:03:27','2026-04-18 16:08:41',NULL,'adasdad'),(57,21,4,'Corporation','2030-04-25',NULL,NULL,NULL,0,200.00,0.00,'dsfsfsd',67,50,NULL,39500.00,13430.00,0.00,10000.00,62930.00,18879.00,'partial','confirmed',0,NULL,NULL,'62515022c1dfc2741cec4494c17bd533','HELLOwORLD',NULL,20,NULL,'2026-04-18 16:19:05','2026-04-18 16:19:05',NULL,'ASDADA'),(58,21,8,'Corporation','2026-04-20',NULL,NULL,NULL,0,200.00,0.00,'asdfsdfsd',88,75,NULL,62500.00,10833.33,990.00,0.00,74323.33,74323.33,'paid','confirmed',0,NULL,NULL,'2ab5ce96931d3622f05cad0802c2c4fe','effewwe',NULL,20,NULL,'2026-04-18 17:19:23','2026-04-18 17:19:23',NULL,NULL);
/*!40000 ALTER TABLE `bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `messenger_link` varchar(255) DEFAULT NULL COMMENT 'Facebook/Messenger link',
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
INSERT INTO `clients` VALUES (21,'kry','casianoprince5@gmail.com','','09950219612','Dasmariñas','2026-04-18 15:23:06'),(22,'Prince Andrew Casiano','casianoprince5@gmail.com','','09389349498','basta','2026-04-19 04:35:48');
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dish_ingredients`
--

DROP TABLE IF EXISTS `dish_ingredients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dish_ingredients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dish_id` int(10) unsigned NOT NULL,
  `ingredient_name` varchar(100) NOT NULL,
  `qty_per_pax` decimal(10,4) NOT NULL COMMENT 'Amount needed for 1 person',
  `unit` varchar(20) NOT NULL COMMENT 'kg, pcs, liters, packs',
  `price_per_unit` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_dish` (`dish_id`),
  CONSTRAINT `fk_dish_ing` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dish_ingredients`
--

LOCK TABLES `dish_ingredients` WRITE;
/*!40000 ALTER TABLE `dish_ingredients` DISABLE KEYS */;
/*!40000 ALTER TABLE `dish_ingredients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `dishes`
--

DROP TABLE IF EXISTS `dishes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dishes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `category` varchar(30) NOT NULL DEFAULT 'main' COMMENT 'Beef, Pork, Chicken, Seafood, Vegetables, Pasta, Rice, Dessert, Additional',
  `base_pax` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `custom_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Extra charge if included in booking (e.g. Lechon)',
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=70 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `dishes`
--

LOCK TABLES `dishes` WRITE;
/*!40000 ALTER TABLE `dishes` DISABLE KEYS */;
INSERT INTO `dishes` VALUES (1,'Beef Caldereta','Beef',0,1,'2026-04-18 14:27:58',0.00,NULL),(2,'Creamy Beef w/ Mushroom','Beef',50,1,'2026-04-18 14:27:58',0.00,NULL),(3,'Beef Steak','Beef',50,1,'2026-04-18 14:27:58',0.00,NULL),(4,'Beef Salpicao','Beef',50,1,'2026-04-18 14:27:58',0.00,NULL),(5,'Beef Pepper Steak','Beef',50,1,'2026-04-18 14:27:58',0.00,NULL),(6,'Beef Stir Fry','Beef',50,1,'2026-04-18 14:27:58',0.00,NULL),(7,'Beef Shanghai','Beef',50,1,'2026-04-18 14:27:58',0.00,NULL),(8,'Beef Steak BBQ','Beef',50,1,'2026-04-18 14:27:58',0.00,NULL),(9,'Beef Kare-Kare','Beef',50,1,'2026-04-18 14:27:58',0.00,NULL),(10,'Beef Teriyaki','Beef',50,1,'2026-04-18 14:27:58',0.00,NULL),(11,'Beef Broccoli','Beef',0,1,'2026-04-18 14:27:58',0.00,NULL),(12,'Pork Teriyaki','Pork',50,1,'2026-04-18 14:27:58',0.00,NULL),(13,'Pork Menudo','Pork',50,1,'2026-04-18 14:27:58',0.00,NULL),(14,'Pork Caldereta','Pork',50,1,'2026-04-18 14:27:58',0.00,NULL),(15,'Pork Adobo','Pork',50,1,'2026-04-18 14:27:58',0.00,NULL),(16,'Pork Humba','Pork',50,1,'2026-04-18 14:27:58',0.00,NULL),(17,'Pork Bistek','Pork',0,1,'2026-04-18 14:27:58',0.00,NULL),(18,'Sweet & Sour Pork','Pork',50,1,'2026-04-18 14:27:58',0.00,NULL),(19,'Pork Sisig','Pork',50,1,'2026-04-18 14:27:58',0.00,NULL),(20,'Pork Shanghai','Pork',50,1,'2026-04-18 14:27:58',0.00,NULL),(21,'Pork Hamonado','Pork',50,1,'2026-04-18 14:27:58',0.00,NULL),(22,'Pork BBQ','Pork',50,1,'2026-04-18 14:27:58',0.00,NULL),(23,'Liempo','Pork',50,1,'2026-04-18 14:27:58',0.00,NULL),(24,'Buttered Porkchop','Pork',50,1,'2026-04-18 14:27:58',0.00,NULL),(25,'Fried Porkchop','Pork',50,1,'2026-04-18 14:27:58',0.00,NULL),(26,'Chicken Cordon Bleu','Chicken',50,1,'2026-04-18 14:27:58',0.00,NULL),(27,'Fried Chicken','Chicken',50,1,'2026-04-18 14:27:58',0.00,NULL),(28,'Pineapple Chicken','Chicken',50,1,'2026-04-18 14:27:58',0.00,NULL),(29,'Orange Chicken','Chicken',0,1,'2026-04-18 14:27:58',0.00,NULL),(30,'Chicken Afritada','Chicken',0,1,'2026-04-18 14:27:58',0.00,NULL),(31,'Chicken Curry','Chicken',50,1,'2026-04-18 14:27:58',0.00,NULL),(32,'Creamy Chicken w/ Mushroom','Chicken',50,1,'2026-04-18 14:27:58',0.00,NULL),(33,'Chicken Adobo','Chicken',50,1,'2026-04-18 14:27:58',0.00,NULL),(34,'Chicken Sisig','Chicken',50,1,'2026-04-18 14:27:58',0.00,NULL),(35,'Garlic Buttered Chicken','Chicken',50,1,'2026-04-18 14:27:58',0.00,NULL),(36,'Chicken Teriyaki','Chicken',50,1,'2026-04-18 14:27:58',0.00,NULL),(37,'Chicken Buffalo','Chicken',50,1,'2026-04-18 14:27:58',0.00,NULL),(38,'Chicken Inasal','Chicken',50,1,'2026-04-18 14:27:58',0.00,NULL),(39,'Chicken Fillet w/ Pineapple','Chicken',50,1,'2026-04-18 14:27:58',0.00,NULL),(40,'Chicken BBQ','Chicken',50,1,'2026-04-18 14:27:58',0.00,NULL),(41,'Sweet & Sour Fish Fillet','Seafood',50,1,'2026-04-18 14:27:58',0.00,NULL),(42,'Fish Fillet in Black Beans & On','Seafood',50,1,'2026-04-18 14:27:58',0.00,NULL),(43,'Fish Fillet w/ Lemon Butter Sauce','Seafood',50,1,'2026-04-18 14:27:58',0.00,NULL),(44,'Fish Fillet w/ Tartar Sauce','Seafood',50,1,'2026-04-18 14:27:58',0.00,NULL),(45,'Buttered Tahong','Seafood',50,1,'2026-04-18 14:27:58',0.00,NULL),(46,'Buttered Shrimp w/ Garlic','Seafood',50,1,'2026-04-18 14:27:58',0.00,NULL),(47,'Seafood Curry','Seafood',50,1,'2026-04-18 14:27:58',0.00,NULL),(48,'Garlic Squid','Seafood',50,1,'2026-04-18 14:27:58',0.00,NULL),(49,'Fish Shanghai','Seafood',50,1,'2026-04-18 14:27:58',0.00,NULL),(50,'Mixed Butter Veggies','Vegetables',50,1,'2026-04-18 14:27:58',0.00,NULL),(51,'Sauteed Marble Potatoes','Vegetables',50,1,'2026-04-18 14:27:58',0.00,NULL),(52,'Chopsuey','Vegetables',50,1,'2026-04-18 14:27:58',0.00,NULL),(53,'Ampalaya Con Carne','Vegetables',50,1,'2026-04-18 14:27:58',0.00,NULL),(54,'Skinless Lumpiang Ubod','Vegetables',50,1,'2026-04-18 14:27:58',0.00,NULL),(55,'Kangkong w/ Tofu','Vegetables',50,1,'2026-04-18 14:27:58',0.00,NULL),(56,'Creamy Sweet Spaghetti','Pasta',50,1,'2026-04-18 14:27:58',0.00,NULL),(57,'Carbonara','Pasta',50,1,'2026-04-18 14:27:58',0.00,NULL),(58,'Sisig Pasta','Pasta',50,1,'2026-04-18 14:27:58',0.00,NULL),(59,'Pancit Canton','Pasta',50,1,'2026-04-18 14:27:58',0.00,NULL),(60,'Pancit Bihon','Pasta',50,1,'2026-04-18 14:27:58',0.00,NULL),(61,'Mixed Bihon/Canton','Pasta',50,1,'2026-04-18 14:27:58',0.00,NULL),(62,'Coffee Jelly','Dessert',50,1,'2026-04-18 14:27:58',0.00,NULL),(63,'Buko Pandan','Dessert',50,1,'2026-04-18 14:27:58',0.00,NULL),(64,'Mango Tapioca','Dessert',50,1,'2026-04-18 14:27:58',0.00,NULL),(65,'Fruit Salad','Dessert',50,1,'2026-04-18 14:27:58',0.00,NULL),(66,'Buko Salad','Dessert',50,1,'2026-04-18 14:27:58',0.00,NULL),(67,'Plain Rice','Rice',50,1,'2026-04-18 14:27:58',0.00,NULL),(68,'Java Rice','Rice',50,1,'2026-04-18 14:27:58',0.00,NULL),(69,'Garlic Rice','Rice',50,1,'2026-04-18 14:27:58',0.00,NULL);
/*!40000 ALTER TABLE `dishes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `email_queue`
--

DROP TABLE IF EXISTS `email_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `email_queue` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `recipient_email` varchar(255) NOT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `body_html` longtext NOT NULL,
  `status` enum('pending','sending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` int(10) unsigned NOT NULL DEFAULT 0,
  `error_log` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `sent_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_queue_status` (`status`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `email_queue`
--

LOCK TABLES `email_queue` WRITE;
/*!40000 ALTER TABLE `email_queue` DISABLE KEYS */;
INSERT INTO `email_queue` VALUES (41,'casianoprince5@gmail.com','kry','Booking Confirmed — Yazzies Catering OMS','\n    <div style=\'font-family:-apple-system, BlinkMacSystemFont, \\`Segoe UI\\`, Roboto, Helvetica, Arial, sans-serif; max-width:560px; margin:0 auto; background-color:#F2F2F7; padding:20px; border-radius:12px;\'>\n      <div style=\'background:linear-gradient(135deg, #30D158, #25A244); padding:32px; border-radius:16px 16px 0 0; text-align:center;\'>\n        <div style=\'font-size:36px; margin-bottom:8px;\'>🍽️</div>\n        <h1 style=\'color:#ffffff; margin:0; font-size:22px; font-weight:700; letter-spacing:-0.5px;\'>Yazzies Catering</h1>\n        <p style=\'color:rgba(255,255,255,0.9); margin:4px 0 0; font-size:14px; font-weight:500;\'>Booking Confirmed</p>\n      </div>\n      <div style=\'background:#ffffff; padding:32px; border-left:0.5px solid rgba(60,60,67,0.08); border-right:0.5px solid rgba(60,60,67,0.08);\'>\n        <h2 style=\'color:#000000; font-size:18px; margin:0 0 8px; font-weight:600; letter-spacing:-0.3px;\'>Hello, kry!</h2>\n        <p style=\'color:rgba(60,60,67,0.8); font-size:14px; line-height:1.6; margin:0 0 24px;\'>Your event booking is officially secured. We are thrilled to cater for you. Here are the event details:</p>\n        \n        <div style=\'background:#F2F2F7; border-radius:12px; padding:20px; margin-bottom:24px;\'>\n          <table style=\'width:100%; border-collapse:collapse;\'>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500; width:40%;\'>Event Date</td><td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>April 20, 2026</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Menu Package</td><td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>Luxury (75 Pax)</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Guest Count</td><td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>88 guests</td></tr>\n            <tr><td style=\'padding:8px 0; padding-top:16px; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Total Cost</td><td style=\'padding:8px 0; padding-top:16px; font-weight:600; font-size:14px; color:#000000;\'>₱74,323.33</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Amount Paid</td><td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#30D158;\'>₱74,323.33</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Remaining Balance</td><td style=\'padding:8px 0; font-weight:700; font-size:14px; color:#FF3B30;\'>₱0.00</td></tr>\n          </table>\n        </div>\n        \n        <p style=\'color:rgba(60,60,67,0.6); font-size:13px; line-height:1.5; margin:0 0 16px;\'>Please settle any remaining balance on or before the event date. We look forward to serving you.</p>\n        <p style=\'color:#000000; font-size:14px; font-weight:600; margin:0;\'>Thank you! 🎉</p>\n      </div>\n      <div style=\'background:#F2F2F7; padding:20px; border-radius:0 0 16px 16px; text-align:center; font-size:12px; font-weight:500; color:rgba(60,60,67,0.4); border:0.5px solid rgba(60,60,67,0.08); border-top:none;\'>\n        Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite\n      </div>\n    </div>','sent',0,NULL,'2026-04-18 17:19:23','2026-04-18 17:25:30'),(42,'casianoprince5@gmail.com','kry','Payment Reminder: Your event is in 3 days! — Yazzies Catering OMS','\n        <div style=\'font-family:-apple-system, BlinkMacSystemFont, \\`Segoe UI\\`, Roboto, Helvetica, Arial, sans-serif; max-width:560px; margin:0 auto; background-color:#F2F2F7; padding:20px; border-radius:12px;\'>\n          <div style=\'background:linear-gradient(135deg, #FF9500, #FF5E00); padding:32px; border-radius:16px 16px 0 0; text-align:center;\'>\n            <div style=\'font-size:36px; margin-bottom:8px;\'>🔔</div>\n            <h1 style=\'color:#ffffff; margin:0; font-size:22px; font-weight:700; letter-spacing:-0.5px;\'>Balance Reminder</h1>\n            <p style=\'color:rgba(255,255,255,0.9); margin:4px 0 0; font-size:14px; font-weight:500;\'>Yazzies Catering</p>\n          </div>\n          <div style=\'background:#ffffff; padding:32px; border-left:0.5px solid rgba(60,60,67,0.08); border-right:0.5px solid rgba(60,60,67,0.08);\'>\n            <h2 style=\'color:#000000; font-size:18px; margin:0 0 8px; font-weight:600; letter-spacing:-0.3px;\'>Hello, kry!</h2>\n            <p style=\'color:rgba(60,60,67,0.8); font-size:14px; line-height:1.6; margin:0 0 24px;\'>This is a friendly reminder that your catering event is just 3 days away! To ensure everything runs smoothly, please settle your remaining balance.</p>\n            \n            <div style=\'background:#FFF9F0; border:1px solid #FF9500; border-radius:12px; padding:20px; margin-bottom:24px; text-align:center;\'>\n                <p style=\'color:rgba(60,60,67,0.6); font-size:13px; margin:0 0 8px; font-weight:500;\'>Remaining Balance for April 22, 2026</p>\n                <div style=\'font-size:32px; font-weight:800; color:#FF9500; letter-spacing:-1px;\'>₱24,950.01</div>\n            </div>\n            \n            <p style=\'color:rgba(60,60,67,0.6); font-size:13px; line-height:1.5; margin:0 0 16px;\'>If you have already made the payment, please disregard this email or send us a copy of your proof of payment. We are excited to serve you!</p>\n            <p style=\'color:#000000; font-size:14px; font-weight:600; margin:0;\'>See you soon! 🍽️</p>\n          </div>\n          <div style=\'background:#F2F2F7; padding:20px; border-radius:0 0 16px 16px; text-align:center; font-size:12px; font-weight:500; color:rgba(60,60,67,0.4); border:0.5px solid rgba(60,60,67,0.08); border-top:none;\'>\n            Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite\n          </div>\n        </div>','sent',0,NULL,'2026-04-18 17:25:30','2026-04-18 17:28:27'),(43,'casianoprince5@gmail.com','kry','Payment Reminder: Your event is in 3 days! — Yazzies Catering OMS','\n        <div style=\'font-family:-apple-system, BlinkMacSystemFont, \\`Segoe UI\\`, Roboto, Helvetica, Arial, sans-serif; max-width:560px; margin:0 auto; background-color:#F2F2F7; padding:20px; border-radius:12px;\'>\n          <div style=\'background:linear-gradient(135deg, #FF9500, #FF5E00); padding:32px; border-radius:16px 16px 0 0; text-align:center;\'>\n            <div style=\'font-size:36px; margin-bottom:8px;\'>🔔</div>\n            <h1 style=\'color:#ffffff; margin:0; font-size:22px; font-weight:700; letter-spacing:-0.5px;\'>Balance Reminder</h1>\n            <p style=\'color:rgba(255,255,255,0.9); margin:4px 0 0; font-size:14px; font-weight:500;\'>Yazzies Catering</p>\n          </div>\n          <div style=\'background:#ffffff; padding:32px; border-left:0.5px solid rgba(60,60,67,0.08); border-right:0.5px solid rgba(60,60,67,0.08);\'>\n            <h2 style=\'color:#000000; font-size:18px; margin:0 0 8px; font-weight:600; letter-spacing:-0.3px;\'>Hello, kry!</h2>\n            <p style=\'color:rgba(60,60,67,0.8); font-size:14px; line-height:1.6; margin:0 0 24px;\'>This is a friendly reminder that your catering event is just 3 days away! To ensure everything runs smoothly, please settle your remaining balance.</p>\n            \n            <div style=\'background:#FFF9F0; border:1px solid #FF9500; border-radius:12px; padding:20px; margin-bottom:24px; text-align:center;\'>\n                <p style=\'color:rgba(60,60,67,0.6); font-size:13px; margin:0 0 8px; font-weight:500;\'>Remaining Balance for April 22, 2026</p>\n                <div style=\'font-size:32px; font-weight:800; color:#FF9500; letter-spacing:-1px;\'>₱24,950.01</div>\n            </div>\n            \n            <p style=\'color:rgba(60,60,67,0.6); font-size:13px; line-height:1.5; margin:0 0 16px;\'>If you have already made the payment, please disregard this email or send us a copy of your proof of payment. We are excited to serve you!</p>\n            <p style=\'color:#000000; font-size:14px; font-weight:600; margin:0;\'>See you soon! 🍽️</p>\n          </div>\n          <div style=\'background:#F2F2F7; padding:20px; border-radius:0 0 16px 16px; text-align:center; font-size:12px; font-weight:500; color:rgba(60,60,67,0.4); border:0.5px solid rgba(60,60,67,0.08); border-top:none;\'>\n            Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite\n          </div>\n        </div>','sent',0,NULL,'2026-04-18 17:28:27','2026-04-18 17:30:57'),(44,'casianoprince5@gmail.com','kry','Payment Reminder: Your event is in 3 days! — Yazzies Catering OMS','\n        <div style=\'font-family:-apple-system, BlinkMacSystemFont, \\`Segoe UI\\`, Roboto, Helvetica, Arial, sans-serif; max-width:560px; margin:0 auto; background-color:#F2F2F7; padding:20px; border-radius:12px;\'>\n          <div style=\'background:linear-gradient(135deg, #FF9500, #FF5E00); padding:32px; border-radius:16px 16px 0 0; text-align:center;\'>\n            <div style=\'font-size:36px; margin-bottom:8px;\'>🔔</div>\n            <h1 style=\'color:#ffffff; margin:0; font-size:22px; font-weight:700; letter-spacing:-0.5px;\'>Balance Reminder</h1>\n            <p style=\'color:rgba(255,255,255,0.9); margin:4px 0 0; font-size:14px; font-weight:500;\'>Yazzies Catering</p>\n          </div>\n          <div style=\'background:#ffffff; padding:32px; border-left:0.5px solid rgba(60,60,67,0.08); border-right:0.5px solid rgba(60,60,67,0.08);\'>\n            <h2 style=\'color:#000000; font-size:18px; margin:0 0 8px; font-weight:600; letter-spacing:-0.3px;\'>Hello, kry!</h2>\n            <p style=\'color:rgba(60,60,67,0.8); font-size:14px; line-height:1.6; margin:0 0 24px;\'>This is a friendly reminder that your catering event is just 3 days away! To ensure everything runs smoothly, please settle your remaining balance.</p>\n            \n            <div style=\'background:#FFF9F0; border:1px solid #FF9500; border-radius:12px; padding:20px; margin-bottom:24px; text-align:center;\'>\n                <p style=\'color:rgba(60,60,67,0.6); font-size:13px; margin:0 0 8px; font-weight:500;\'>Remaining Balance for April 22, 2026</p>\n                <div style=\'font-size:32px; font-weight:800; color:#FF9500; letter-spacing:-1px;\'>₱24,950.01</div>\n            </div>\n            \n            <p style=\'color:rgba(60,60,67,0.6); font-size:13px; line-height:1.5; margin:0 0 16px;\'>If you have already made the payment, please disregard this email or send us a copy of your proof of payment. We are excited to serve you!</p>\n            <p style=\'color:#000000; font-size:14px; font-weight:600; margin:0;\'>See you soon! 🍽️</p>\n          </div>\n          <div style=\'background:#F2F2F7; padding:20px; border-radius:0 0 16px 16px; text-align:center; font-size:12px; font-weight:500; color:rgba(60,60,67,0.4); border:0.5px solid rgba(60,60,67,0.08); border-top:none;\'>\n            Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite\n          </div>\n        </div>','sent',0,NULL,'2026-04-18 17:30:58','2026-04-18 17:32:53'),(45,'casianoprince5@gmail.com','kry','Payment Reminder: Your event is in 3 days! — Yazzies Catering OMS','\n        <div style=\'font-family:-apple-system, BlinkMacSystemFont, \\`Segoe UI\\`, Roboto, Helvetica, Arial, sans-serif; max-width:560px; margin:0 auto; background-color:#F2F2F7; padding:20px; border-radius:12px;\'>\n          <div style=\'background:linear-gradient(135deg, #FF9500, #FF5E00); padding:32px; border-radius:16px 16px 0 0; text-align:center;\'>\n            <div style=\'font-size:36px; margin-bottom:8px;\'>🔔</div>\n            <h1 style=\'color:#ffffff; margin:0; font-size:22px; font-weight:700; letter-spacing:-0.5px;\'>Balance Reminder</h1>\n            <p style=\'color:rgba(255,255,255,0.9); margin:4px 0 0; font-size:14px; font-weight:500;\'>Yazzies Catering</p>\n          </div>\n          <div style=\'background:#ffffff; padding:32px; border-left:0.5px solid rgba(60,60,67,0.08); border-right:0.5px solid rgba(60,60,67,0.08);\'>\n            <h2 style=\'color:#000000; font-size:18px; margin:0 0 8px; font-weight:600; letter-spacing:-0.3px;\'>Hello, kry!</h2>\n            <p style=\'color:rgba(60,60,67,0.8); font-size:14px; line-height:1.6; margin:0 0 24px;\'>This is a friendly reminder that your catering event is just 3 days away! To ensure everything runs smoothly, please settle your remaining balance.</p>\n            \n            <div style=\'background:#FFF9F0; border:1px solid #FF9500; border-radius:12px; padding:20px; margin-bottom:24px; text-align:center;\'>\n                <p style=\'color:rgba(60,60,67,0.6); font-size:13px; margin:0 0 8px; font-weight:500;\'>Remaining Balance for April 22, 2026</p>\n                <div style=\'font-size:32px; font-weight:800; color:#FF9500; letter-spacing:-1px;\'>₱24,950.01</div>\n            </div>\n            \n            <p style=\'color:rgba(60,60,67,0.6); font-size:13px; line-height:1.5; margin:0 0 16px;\'>If you have already made the payment, please disregard this email or send us a copy of your proof of payment. We are excited to serve you!</p>\n            <p style=\'color:#000000; font-size:14px; font-weight:600; margin:0;\'>See you soon! 🍽️</p>\n          </div>\n          <div style=\'background:#F2F2F7; padding:20px; border-radius:0 0 16px 16px; text-align:center; font-size:12px; font-weight:500; color:rgba(60,60,67,0.4); border:0.5px solid rgba(60,60,67,0.08); border-top:none;\'>\n            Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite\n          </div>\n        </div>','pending',0,NULL,'2026-04-18 17:32:53',NULL),(46,'pawcasiano@kld.edu.ph','Paw Casiano','📋 You\'ve been assigned to an event — Yazzies Catering OMS','\n    <div style=\'font-family:-apple-system, BlinkMacSystemFont, \\`Segoe UI\\`, Roboto, Helvetica, Arial, sans-serif; max-width:560px; margin:0 auto; background-color:#F2F2F7; padding:20px; border-radius:12px;\'>\n      <div style=\'background:linear-gradient(135deg, #30D158, #25A244); padding:32px; border-radius:16px 16px 0 0; text-align:center;\'>\n        <div style=\'font-size:36px; margin-bottom:8px;\'>🍽️</div>\n        <h1 style=\'color:#ffffff; margin:0; font-size:22px; font-weight:700; letter-spacing:-0.5px;\'>Event Assignment</h1>\n        <p style=\'color:rgba(255,255,255,0.9); margin:4px 0 0; font-size:14px; font-weight:500;\'>Yazzies Catering OMS</p>\n      </div>\n      <div style=\'background:#ffffff; padding:32px; border-left:0.5px solid rgba(60,60,67,0.08); border-right:0.5px solid rgba(60,60,67,0.08);\'>\n        <h2 style=\'color:#000000; font-size:18px; margin:0 0 8px; font-weight:600; letter-spacing:-0.3px;\'>Hi, Paw Casiano!</h2>\n        <p style=\'color:rgba(60,60,67,0.8); font-size:14px; line-height:1.6; margin:0 0 24px;\'>You have been selected as part of the team for an upcoming catering event. Please review the details below.</p>\n\n        <div style=\'background:#F2F2F7; border-radius:12px; padding:20px; margin-bottom:24px;\'>\n          <table style=\'width:100%; border-collapse:collapse;\'>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500; width:38%;\'>Your Role</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>head_cook</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Event Date</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>April 25, 2030</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Time</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>—</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Location</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>dsfsfsd</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Guests</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>67 pax</td></tr>\n          </table>\n        </div>\n\n        <p style=\'color:rgba(60,60,67,0.8); font-size:13px; line-height:1.5; margin:0 0 16px;\'>Log in to your account to view full details and confirm your availability.</p>\n        <div style=\'text-align:center;\'>\n            <a href=\'http://localhost/test/\' style=\'display:inline-block; margin-top:8px; background:#30D158; color:#ffffff; padding:12px 24px; border-radius:10px; font-weight:600; font-size:14px; text-decoration:none;\'>View My Schedule</a>\n        </div>\n      </div>\n      <div style=\'background:#F2F2F7; padding:20px; border-radius:0 0 16px 16px; text-align:center; font-size:12px; font-weight:500; color:rgba(60,60,67,0.4); border:0.5px solid rgba(60,60,67,0.08); border-top:none;\'>\n        Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite\n      </div>\n    </div>','pending',0,NULL,'2026-04-19 04:02:48',NULL),(47,'pawcasiano@kld.edu.ph','Paw Casiano','📋 You\'ve been assigned to an event — Yazzies Catering OMS','\n    <div style=\'font-family:-apple-system, BlinkMacSystemFont, \\`Segoe UI\\`, Roboto, Helvetica, Arial, sans-serif; max-width:560px; margin:0 auto; background-color:#F2F2F7; padding:20px; border-radius:12px;\'>\n      <div style=\'background:linear-gradient(135deg, #30D158, #25A244); padding:32px; border-radius:16px 16px 0 0; text-align:center;\'>\n        <div style=\'font-size:36px; margin-bottom:8px;\'>🍽️</div>\n        <h1 style=\'color:#ffffff; margin:0; font-size:22px; font-weight:700; letter-spacing:-0.5px;\'>Event Assignment</h1>\n        <p style=\'color:rgba(255,255,255,0.9); margin:4px 0 0; font-size:14px; font-weight:500;\'>Yazzies Catering OMS</p>\n      </div>\n      <div style=\'background:#ffffff; padding:32px; border-left:0.5px solid rgba(60,60,67,0.08); border-right:0.5px solid rgba(60,60,67,0.08);\'>\n        <h2 style=\'color:#000000; font-size:18px; margin:0 0 8px; font-weight:600; letter-spacing:-0.3px;\'>Hi, Paw Casiano!</h2>\n        <p style=\'color:rgba(60,60,67,0.8); font-size:14px; line-height:1.6; margin:0 0 24px;\'>You have been selected as part of the team for an upcoming catering event. Please review the details below.</p>\n\n        <div style=\'background:#F2F2F7; border-radius:12px; padding:20px; margin-bottom:24px;\'>\n          <table style=\'width:100%; border-collapse:collapse;\'>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500; width:38%;\'>Your Role</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>waiter</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Event Date</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>April 25, 2030</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Time</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>—</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Location</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>dsfsfsd</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Guests</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>67 pax</td></tr>\n          </table>\n        </div>\n\n        <p style=\'color:rgba(60,60,67,0.8); font-size:13px; line-height:1.5; margin:0 0 16px;\'>Log in to your account to view full details and confirm your availability.</p>\n        <div style=\'text-align:center;\'>\n            <a href=\'http://localhost/test/\' style=\'display:inline-block; margin-top:8px; background:#30D158; color:#ffffff; padding:12px 24px; border-radius:10px; font-weight:600; font-size:14px; text-decoration:none;\'>View My Schedule</a>\n        </div>\n      </div>\n      <div style=\'background:#F2F2F7; padding:20px; border-radius:0 0 16px 16px; text-align:center; font-size:12px; font-weight:500; color:rgba(60,60,67,0.4); border:0.5px solid rgba(60,60,67,0.08); border-top:none;\'>\n        Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite\n      </div>\n    </div>','pending',0,NULL,'2026-04-19 04:23:15',NULL),(48,'pawcasiano@kld.edu.ph','Paw Casiano','📋 You\'ve been assigned to an event — Yazzies Catering OMS','\n    <div style=\'font-family:-apple-system, BlinkMacSystemFont, \\`Segoe UI\\`, Roboto, Helvetica, Arial, sans-serif; max-width:560px; margin:0 auto; background-color:#F2F2F7; padding:20px; border-radius:12px;\'>\n      <div style=\'background:linear-gradient(135deg, #30D158, #25A244); padding:32px; border-radius:16px 16px 0 0; text-align:center;\'>\n        <div style=\'font-size:36px; margin-bottom:8px;\'>🍽️</div>\n        <h1 style=\'color:#ffffff; margin:0; font-size:22px; font-weight:700; letter-spacing:-0.5px;\'>Event Assignment</h1>\n        <p style=\'color:rgba(255,255,255,0.9); margin:4px 0 0; font-size:14px; font-weight:500;\'>Yazzies Catering OMS</p>\n      </div>\n      <div style=\'background:#ffffff; padding:32px; border-left:0.5px solid rgba(60,60,67,0.08); border-right:0.5px solid rgba(60,60,67,0.08);\'>\n        <h2 style=\'color:#000000; font-size:18px; margin:0 0 8px; font-weight:600; letter-spacing:-0.3px;\'>Hi, Paw Casiano!</h2>\n        <p style=\'color:rgba(60,60,67,0.8); font-size:14px; line-height:1.6; margin:0 0 24px;\'>You have been selected as part of the team for an upcoming catering event. Please review the details below.</p>\n\n        <div style=\'background:#F2F2F7; border-radius:12px; padding:20px; margin-bottom:24px;\'>\n          <table style=\'width:100%; border-collapse:collapse;\'>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500; width:38%;\'>Your Role</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>waiter</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Event Date</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>April 25, 2030</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Time</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>—</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Location</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>dsfsfsd</td></tr>\n            <tr><td style=\'padding:8px 0; color:rgba(60,60,67,0.6); font-size:13px; font-weight:500;\'>Guests</td>\n                <td style=\'padding:8px 0; font-weight:600; font-size:14px; color:#000000;\'>67 pax</td></tr>\n          </table>\n        </div>\n\n        <p style=\'color:rgba(60,60,67,0.8); font-size:13px; line-height:1.5; margin:0 0 16px;\'>Log in to your account to view full details and confirm your availability.</p>\n        <div style=\'text-align:center;\'>\n            <a href=\'http://localhost/test/\' style=\'display:inline-block; margin-top:8px; background:#30D158; color:#ffffff; padding:12px 24px; border-radius:10px; font-weight:600; font-size:14px; text-decoration:none;\'>View My Schedule</a>\n        </div>\n      </div>\n      <div style=\'background:#F2F2F7; padding:20px; border-radius:0 0 16px 16px; text-align:center; font-size:12px; font-weight:500; color:rgba(60,60,67,0.4); border:0.5px solid rgba(60,60,67,0.08); border-top:none;\'>\n        Yazzies Catering &bull; Barangay St. Peter, Dasmariñas City, Cavite\n      </div>\n    </div>','pending',0,NULL,'2026-04-19 04:25:17',NULL);
/*!40000 ALTER TABLE `email_queue` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipment`
--

DROP TABLE IF EXISTS `equipment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `replacement_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipment`
--

LOCK TABLES `equipment` WRITE;
/*!40000 ALTER TABLE `equipment` DISABLE KEYS */;
INSERT INTO `equipment` VALUES (1,'Dinner Plate (Ceramic)','pcs',150.00,1,'2026-04-16 15:55:04','2026-04-16 15:55:04'),(2,'Spoon/Fork (Stainless)','pcs',45.00,1,'2026-04-16 15:55:04','2026-04-16 15:55:04'),(3,'Highball Glass','pcs',85.00,1,'2026-04-16 15:55:04','2026-04-16 15:55:04'),(4,'Water Goblet','pcs',120.00,1,'2026-04-16 15:55:04','2026-04-16 15:55:04'),(5,'Melamine Plate','pcs',75.00,1,'2026-04-16 15:55:04','2026-04-16 15:55:04'),(6,'Serving Spoon (Large)','pcs',250.00,1,'2026-04-16 15:55:04','2026-04-16 15:55:04'),(7,'Chafing Dish Lid','pcs',1200.00,1,'2026-04-16 15:55:04','2026-04-16 15:55:04'),(8,'Table Cloth (Large)','pcs',650.00,1,'2026-04-16 15:55:04','2026-04-16 15:55:04');
/*!40000 ALTER TABLE `equipment` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `job_orders`
--

DROP TABLE IF EXISTS `job_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_orders` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `staff_id` int(10) unsigned NOT NULL,
  `role_required` varchar(50) NOT NULL DEFAULT 'waiter',
  `status` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `job_class` enum('head_cook','cook','waiter','server','helper','any') NOT NULL DEFAULT 'any' COMMENT 'Mirrors the staff job_class at time of assignment',
  PRIMARY KEY (`id`),
  KEY `idx_booking_id` (`booking_id`),
  KEY `idx_staff_id` (`staff_id`),
  CONSTRAINT `fk_joborders_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_joborders_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=86 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `job_orders`
--

LOCK TABLES `job_orders` WRITE;
/*!40000 ALTER TABLE `job_orders` DISABLE KEYS */;
INSERT INTO `job_orders` VALUES (85,57,22,'waiter','accepted','2026-04-19 04:25:17','2026-04-19 04:26:04',NULL,'waiter');
/*!40000 ALTER TABLE `job_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `leave_requests`
--

DROP TABLE IF EXISTS `leave_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leave_requests` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` int(10) unsigned NOT NULL,
  `leave_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(10) unsigned DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_date` (`staff_id`,`leave_date`),
  KEY `idx_date` (`leave_date`),
  KEY `idx_status` (`status`),
  KEY `fk_lr_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_lr_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lr_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `leave_requests`
--

LOCK TABLES `leave_requests` WRITE;
/*!40000 ALTER TABLE `leave_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `leave_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL COMMENT 'IPv4 or IPv6',
  `email` varchar(255) DEFAULT NULL COMMENT 'Email attempted (for auditing)',
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`,`attempted_at`),
  KEY `idx_cleanup` (`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `login_attempts`
--

LOCK TABLES `login_attempts` WRITE;
/*!40000 ALTER TABLE `login_attempts` DISABLE KEYS */;
/*!40000 ALTER TABLE `login_attempts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `type` enum('job_assigned','job_declined','leave_approved','leave_rejected','leave_reviewed','balance_reminder','event_reminder','general') NOT NULL DEFAULT 'general',
  `title` varchar(150) NOT NULL,
  `body` text DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `booking_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `link_url` varchar(500) DEFAULT NULL COMMENT 'Optional URL to navigate to when notification is clicked',
  PRIMARY KEY (`id`),
  KEY `idx_user_read` (`user_id`,`is_read`),
  KEY `idx_created` (`created_at`),
  KEY `fk_notif_booking` (`booking_id`),
  CONSTRAINT `fk_notif_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (88,19,'balance_reminder','💰 Pending Balance: kry','Booking #56 is in 3 days but still has a remaining balance of ₱24,950.01',0,56,'2026-04-18 17:32:53',NULL),(89,20,'balance_reminder','💰 Pending Balance: kry','Booking #56 is in 3 days but still has a remaining balance of ₱24,950.01',1,56,'2026-04-18 17:32:53',NULL),(90,21,'balance_reminder','💰 Pending Balance: kry','Booking #56 is in 3 days but still has a remaining balance of ₱24,950.01',1,56,'2026-04-18 17:32:53',NULL),(91,22,'job_assigned','New Job Offer: head_cook','Event on Apr 25 for kry. Please respond in your Job Board.',1,NULL,'2026-04-19 04:02:48',NULL),(92,22,'job_assigned','New Job Offer: waiter','Event on Apr 25 for kry. Please respond in your Job Board.',1,NULL,'2026-04-19 04:23:15',NULL),(93,22,'job_assigned','New Job Offer: waiter','Event on Apr 25 for kry. Please respond in your Job Board.',1,NULL,'2026-04-19 04:25:17',NULL);
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `packages`
--

DROP TABLE IF EXISTS `packages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `packages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `set_name` varchar(100) NOT NULL,
  `pax_count` int(10) unsigned NOT NULL COMMENT 'Base pax count for this tier',
  `price` decimal(10,2) NOT NULL COMMENT 'Flat price at base pax_count',
  `max_main_dishes` int(10) unsigned NOT NULL DEFAULT 5,
  `max_desserts` int(10) unsigned NOT NULL DEFAULT 1,
  `includes_rice` tinyint(1) NOT NULL DEFAULT 1,
  `inclusions` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_package_tier` (`set_name`,`pax_count`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `packages`
--

LOCK TABLES `packages` WRITE;
/*!40000 ALTER TABLE `packages` DISABLE KEYS */;
INSERT INTO `packages` VALUES (1,'Standard',50,28500.00,5,1,1,'helloWorld',0),(2,'Standard',75,34500.00,5,1,1,NULL,1),(3,'Standard',100,41500.00,5,1,1,NULL,1),(4,'Premium',50,39500.00,5,1,1,'HELLoWorld',1),(5,'Premium',75,45500.00,5,1,1,'basta',1),(6,'Premium',100,52500.00,5,1,1,NULL,1),(7,'Luxury',50,41500.00,5,1,1,NULL,1),(8,'Luxury',75,62500.00,5,1,1,NULL,1),(9,'Luxury',100,72500.00,5,1,1,NULL,1);
/*!40000 ALTER TABLE `packages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `booking_id` int(10) unsigned NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','gcash','maya') NOT NULL DEFAULT 'cash',
  `payment_type` enum('payment','refund') NOT NULL DEFAULT 'payment',
  `refund_reason` varchar(255) DEFAULT NULL,
  `reference_no` varchar(100) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_booking` (`booking_id`),
  KEY `idx_recorded` (`recorded_by`),
  KEY `idx_payments_type` (`payment_type`),
  CONSTRAINT `fk_payments_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (32,56,58216.66,'cash','payment',NULL,'','2026-04-19',NULL,20,'2026-04-18 16:08:24'),(33,57,18879.00,'cash','payment',NULL,NULL,'2026-04-19','Downpayment',20,'2026-04-18 16:19:05'),(34,58,74323.33,'cash','payment',NULL,NULL,'2026-04-19','Downpayment',20,'2026-04-18 17:19:23');
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recipe_ingredients`
--

DROP TABLE IF EXISTS `recipe_ingredients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recipe_ingredients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `dish_id` int(10) unsigned NOT NULL,
  `ingredient_name` varchar(100) NOT NULL,
  `base_quantity` decimal(10,4) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `unit_price` decimal(10,2) DEFAULT NULL COMMENT 'Price per unit. NULL = not yet costed.',
  `supplier` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dish_id` (`dish_id`),
  CONSTRAINT `recipe_ingredients_ibfk_1` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=387 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recipe_ingredients`
--

LOCK TABLES `recipe_ingredients` WRITE;
/*!40000 ALTER TABLE `recipe_ingredients` DISABLE KEYS */;
INSERT INTO `recipe_ingredients` VALUES (1,1,'Chicken',10.0000,'kg','2026-04-11 16:09:58',NULL,NULL),(2,1,'Soy Sauce',1.5000,'L','2026-04-11 16:09:58',NULL,NULL),(3,11,'Chicken breast',500.0000,'g','2026-04-12 14:42:08',NULL,NULL),(4,17,'gelatin powerrr',2.0000,'packs','2026-04-12 15:19:07',NULL,NULL),(5,11,'gata',10.0000,'L','2026-04-12 15:39:30',NULL,NULL),(6,30,'tofu',2.0000,'pcs','2026-04-13 00:13:16',NULL,NULL),(7,11,'potato',300.0000,'g','2026-04-13 02:45:57',NULL,NULL),(8,29,'shrimp',0.5000,'kg','2026-04-14 16:31:06',NULL,NULL),(9,2,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(10,2,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(11,2,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(12,2,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(13,2,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(14,2,'Beef',8.0000,'kg','2026-04-19 04:39:03',450.00,'Default Supplier'),(15,3,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(16,3,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(17,3,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(18,3,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(19,3,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(20,3,'Beef',8.0000,'kg','2026-04-19 04:39:03',450.00,'Default Supplier'),(21,4,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(22,4,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(23,4,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(24,4,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(25,4,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(26,4,'Beef',8.0000,'kg','2026-04-19 04:39:03',450.00,'Default Supplier'),(27,5,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(28,5,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(29,5,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(30,5,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(31,5,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(32,5,'Beef',8.0000,'kg','2026-04-19 04:39:03',450.00,'Default Supplier'),(33,6,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(34,6,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(35,6,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(36,6,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(37,6,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(38,6,'Beef',8.0000,'kg','2026-04-19 04:39:03',450.00,'Default Supplier'),(39,7,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(40,7,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(41,7,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(42,7,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(43,7,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(44,7,'Beef',8.0000,'kg','2026-04-19 04:39:03',450.00,'Default Supplier'),(45,8,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(46,8,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(47,8,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(48,8,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(49,8,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(50,8,'Beef',8.0000,'kg','2026-04-19 04:39:03',450.00,'Default Supplier'),(51,9,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(52,9,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(53,9,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(54,9,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(55,9,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(56,9,'Beef',8.0000,'kg','2026-04-19 04:39:03',450.00,'Default Supplier'),(57,10,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(58,10,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(59,10,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(60,10,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(61,10,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(62,10,'Beef',8.0000,'kg','2026-04-19 04:39:03',450.00,'Default Supplier'),(63,12,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(64,12,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(65,12,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(66,12,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(67,12,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(68,12,'Pork',10.0000,'kg','2026-04-19 04:39:03',320.00,'Default Supplier'),(69,13,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(70,13,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(71,13,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(72,13,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(73,13,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(74,13,'Pork',10.0000,'kg','2026-04-19 04:39:03',320.00,'Default Supplier'),(75,14,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(76,14,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(77,14,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(78,14,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(79,14,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(80,14,'Pork',10.0000,'kg','2026-04-19 04:39:03',320.00,'Default Supplier'),(81,15,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(82,15,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(83,15,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(84,15,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(85,15,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(86,15,'Pork',10.0000,'kg','2026-04-19 04:39:03',320.00,'Default Supplier'),(87,16,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(88,16,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(89,16,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(90,16,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(91,16,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(92,16,'Pork',10.0000,'kg','2026-04-19 04:39:03',320.00,'Default Supplier'),(93,18,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(94,18,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(95,18,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(96,18,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(97,18,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(98,18,'Pork',10.0000,'kg','2026-04-19 04:39:03',320.00,'Default Supplier'),(99,19,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(100,19,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(101,19,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(102,19,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(103,19,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(104,19,'Pork',10.0000,'kg','2026-04-19 04:39:03',320.00,'Default Supplier'),(105,20,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(106,20,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(107,20,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(108,20,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(109,20,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(110,20,'Pork',10.0000,'kg','2026-04-19 04:39:03',320.00,'Default Supplier'),(111,21,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(112,21,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(113,21,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(114,21,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(115,21,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(116,21,'Pork',10.0000,'kg','2026-04-19 04:39:03',320.00,'Default Supplier'),(117,22,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(118,22,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(119,22,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(120,22,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(121,22,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(122,22,'Pork',10.0000,'kg','2026-04-19 04:39:03',320.00,'Default Supplier'),(123,23,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(124,23,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(125,23,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(126,23,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(127,23,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(128,23,'Pork',10.0000,'kg','2026-04-19 04:39:03',320.00,'Default Supplier'),(129,24,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(130,24,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(131,24,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(132,24,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(133,24,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(134,24,'Pork',10.0000,'kg','2026-04-19 04:39:03',320.00,'Default Supplier'),(135,25,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(136,25,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(137,25,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(138,25,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(139,25,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(140,25,'Pork',10.0000,'kg','2026-04-19 04:39:03',320.00,'Default Supplier'),(141,26,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(142,26,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(143,26,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(144,26,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(145,26,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(146,26,'Chicken',12.0000,'kg','2026-04-19 04:39:03',240.00,'Default Supplier'),(147,27,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(148,27,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(149,27,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(150,27,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(151,27,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(152,27,'Chicken',12.0000,'kg','2026-04-19 04:39:03',240.00,'Default Supplier'),(153,28,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(154,28,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(155,28,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(156,28,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(157,28,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(158,28,'Chicken',12.0000,'kg','2026-04-19 04:39:03',240.00,'Default Supplier'),(159,31,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(160,31,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(161,31,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(162,31,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(163,31,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(164,31,'Chicken',12.0000,'kg','2026-04-19 04:39:03',240.00,'Default Supplier'),(165,32,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(166,32,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(167,32,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(168,32,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(169,32,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(170,32,'Chicken',12.0000,'kg','2026-04-19 04:39:03',240.00,'Default Supplier'),(171,33,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(172,33,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(173,33,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(174,33,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(175,33,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(176,33,'Chicken',12.0000,'kg','2026-04-19 04:39:03',240.00,'Default Supplier'),(177,34,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(178,34,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(179,34,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(180,34,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(181,34,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(182,34,'Chicken',12.0000,'kg','2026-04-19 04:39:03',240.00,'Default Supplier'),(183,35,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(184,35,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(185,35,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(186,35,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(187,35,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(188,35,'Chicken',12.0000,'kg','2026-04-19 04:39:03',240.00,'Default Supplier'),(189,36,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(190,36,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(191,36,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(192,36,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(193,36,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(194,36,'Chicken',12.0000,'kg','2026-04-19 04:39:03',240.00,'Default Supplier'),(195,37,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(196,37,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(197,37,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(198,37,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(199,37,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(200,37,'Chicken',12.0000,'kg','2026-04-19 04:39:03',240.00,'Default Supplier'),(201,38,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(202,38,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(203,38,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(204,38,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(205,38,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(206,38,'Chicken',12.0000,'kg','2026-04-19 04:39:03',240.00,'Default Supplier'),(207,39,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(208,39,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(209,39,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(210,39,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(211,39,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(212,39,'Chicken',12.0000,'kg','2026-04-19 04:39:03',240.00,'Default Supplier'),(213,40,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(214,40,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(215,40,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(216,40,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(217,40,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(218,40,'Chicken',12.0000,'kg','2026-04-19 04:39:03',240.00,'Default Supplier'),(219,41,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(220,41,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(221,41,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(222,41,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(223,41,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(224,41,'Seafood/Fish',10.0000,'kg','2026-04-19 04:39:03',380.00,'Default Supplier'),(225,42,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(226,42,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(227,42,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(228,42,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(229,42,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(230,42,'Seafood/Fish',10.0000,'kg','2026-04-19 04:39:03',380.00,'Default Supplier'),(231,43,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(232,43,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(233,43,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(234,43,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(235,43,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(236,43,'Seafood/Fish',10.0000,'kg','2026-04-19 04:39:03',380.00,'Default Supplier'),(237,44,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(238,44,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(239,44,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(240,44,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(241,44,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(242,44,'Seafood/Fish',10.0000,'kg','2026-04-19 04:39:03',380.00,'Default Supplier'),(243,45,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(244,45,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(245,45,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(246,45,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(247,45,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(248,45,'Seafood/Fish',10.0000,'kg','2026-04-19 04:39:03',380.00,'Default Supplier'),(249,46,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(250,46,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(251,46,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(252,46,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(253,46,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(254,46,'Seafood/Fish',10.0000,'kg','2026-04-19 04:39:03',380.00,'Default Supplier'),(255,47,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(256,47,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(257,47,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(258,47,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(259,47,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(260,47,'Seafood/Fish',10.0000,'kg','2026-04-19 04:39:03',380.00,'Default Supplier'),(261,48,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(262,48,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(263,48,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(264,48,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(265,48,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(266,48,'Seafood/Fish',10.0000,'kg','2026-04-19 04:39:03',380.00,'Default Supplier'),(267,49,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(268,49,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(269,49,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(270,49,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(271,49,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(272,49,'Seafood/Fish',10.0000,'kg','2026-04-19 04:39:03',380.00,'Default Supplier'),(273,50,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(274,50,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(275,50,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(276,50,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(277,50,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(278,51,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(279,51,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(280,51,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(281,51,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(282,51,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(283,52,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(284,52,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(285,52,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(286,52,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(287,52,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(288,53,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(289,53,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(290,53,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(291,53,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(292,53,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(293,54,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(294,54,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(295,54,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(296,54,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(297,54,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(298,55,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(299,55,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(300,55,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(301,55,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(302,55,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(303,56,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(304,56,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(305,56,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(306,56,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(307,56,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(308,56,'Noodles/Pasta',6.0000,'kg','2026-04-19 04:39:03',110.00,'Default Supplier'),(309,57,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(310,57,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(311,57,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(312,57,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(313,57,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(314,57,'Noodles/Pasta',6.0000,'kg','2026-04-19 04:39:03',110.00,'Default Supplier'),(315,58,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(316,58,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(317,58,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(318,58,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(319,58,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(320,58,'Noodles/Pasta',6.0000,'kg','2026-04-19 04:39:03',110.00,'Default Supplier'),(321,59,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(322,59,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(323,59,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(324,59,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(325,59,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(326,59,'Noodles/Pasta',6.0000,'kg','2026-04-19 04:39:03',110.00,'Default Supplier'),(327,60,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(328,60,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(329,60,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(330,60,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(331,60,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(332,60,'Noodles/Pasta',6.0000,'kg','2026-04-19 04:39:03',110.00,'Default Supplier'),(333,61,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(334,61,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(335,61,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(336,61,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(337,61,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(338,61,'Noodles/Pasta',6.0000,'kg','2026-04-19 04:39:03',110.00,'Default Supplier'),(339,62,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(340,62,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(341,62,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(342,62,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(343,62,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(344,62,'Dessert Base',5.0000,'kg','2026-04-19 04:39:03',120.00,'Default Supplier'),(345,63,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(346,63,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(347,63,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(348,63,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(349,63,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(350,63,'Dessert Base',5.0000,'kg','2026-04-19 04:39:03',120.00,'Default Supplier'),(351,64,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(352,64,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(353,64,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(354,64,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(355,64,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(356,64,'Dessert Base',5.0000,'kg','2026-04-19 04:39:03',120.00,'Default Supplier'),(357,65,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(358,65,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(359,65,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(360,65,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(361,65,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(362,65,'Dessert Base',5.0000,'kg','2026-04-19 04:39:03',120.00,'Default Supplier'),(363,66,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(364,66,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(365,66,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(366,66,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(367,66,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(368,66,'Dessert Base',5.0000,'kg','2026-04-19 04:39:03',120.00,'Default Supplier'),(369,67,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(370,67,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(371,67,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(372,67,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(373,67,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(374,67,'Rice',10.0000,'kg','2026-04-19 04:39:03',58.00,'Default Supplier'),(375,68,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(376,68,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(377,68,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(378,68,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(379,68,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(380,68,'Rice',10.0000,'kg','2026-04-19 04:39:03',58.00,'Default Supplier'),(381,69,'Garlic',0.1000,'kg','2026-04-19 04:39:03',150.00,'Default Supplier'),(382,69,'Onion',0.2000,'kg','2026-04-19 04:39:03',80.00,'Default Supplier'),(383,69,'Cooking Oil',0.5000,'L','2026-04-19 04:39:03',72.00,'Default Supplier'),(384,69,'Salt',0.0500,'kg','2026-04-19 04:39:03',25.00,'Default Supplier'),(385,69,'Pepper',0.0200,'kg','2026-04-19 04:39:03',350.00,'Default Supplier'),(386,69,'Rice',10.0000,'kg','2026-04-19 04:39:03',58.00,'Default Supplier');
/*!40000 ALTER TABLE `recipe_ingredients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `settings`
--

DROP TABLE IF EXISTS `settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(80) NOT NULL,
  `value` text DEFAULT NULL,
  `type` enum('string','int','float','bool','json') NOT NULL DEFAULT 'string',
  `description` varchar(255) DEFAULT NULL,
  `category` varchar(30) DEFAULT 'general',
  `updated_by` int(10) unsigned DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_settings_key` (`key`),
  KEY `idx_settings_updated_by` (`updated_by`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES (1,'min_pax','50','int','Minimum allowed pax per booking','general',NULL,'2026-04-15 14:10:33'),(2,'max_pax','300','int','Maximum allowed pax per booking','general',NULL,'2026-04-15 14:10:33'),(3,'min_lead_time_days','1','int','Minimum lead time in days for bookings/reschedules','general',NULL,'2026-04-18 16:23:45'),(4,'min_dp_percent','0.30','float','Minimum downpayment percent (0.50 = 50%)','general',NULL,'2026-04-18 15:32:42'),(8,'standard_dp_percent','0.30','float','Downpayment required for standard bookings (30%)','finance',NULL,'2026-04-16 16:10:32'),(9,'rush_dp_percent','1.00','float','Downpayment required for bookings < 48hrs away (100%)','finance',NULL,'2026-04-16 16:10:32'),(10,'operating_hours_start','08:00','string','Earliest event start time','operations',NULL,'2026-04-16 16:10:32'),(11,'operating_hours_end','21:59','string','Latest event end time','operations',NULL,'2026-04-16 16:10:32'),(12,'staff_ratio_premium','10','int','Guest-to-staff ratio for Weddings/Corporate (1:10)','operations',NULL,'2026-04-16 16:10:32'),(13,'staff_ratio_standard','20','int','Guest-to-staff ratio for other events (1:20)','operations',NULL,'2026-04-16 16:10:32'),(14,'extra_pax_rate','125','float','Extra Pax Rate (PHP)','financial',NULL,'2026-04-18 05:23:34'),(15,'max_super_admins','1','int','Maximum Super Admin Accounts','system',NULL,'2026-04-18 05:23:34'),(16,'company_name','Yazzies Catering Services','string','Company Name','general',NULL,'2026-04-18 05:23:34'),(23,'overtime_rate_per_hour','200','float','Hourly Overtime Rate (PHP)','financial',NULL,'2026-04-18 06:27:46'),(24,'event_duration_hours','4','int','Standard Event Duration (Hours)','booking',NULL,'2026-04-18 06:27:46'),(25,'waiter_ratio_wedding','10','int','Pax per Waiter (Wedding/Corp)','staffing',NULL,'2026-04-18 06:27:46'),(26,'waiter_ratio_birthday','20','int','Pax per Waiter (Birthday/Other)','staffing',NULL,'2026-04-18 06:27:46'),(27,'staff_hourly_rate','75','float','Staff Hourly Rate (PHP)','financial',NULL,'2026-04-18 06:27:46');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `taste_test_appointments`
--

DROP TABLE IF EXISTS `taste_test_appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `taste_test_appointments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned DEFAULT NULL,
  `prospect_name` varchar(120) DEFAULT NULL,
  `prospect_phone` varchar(20) DEFAULT NULL,
  `prospect_email` varchar(255) DEFAULT NULL,
  `desired_date` date NOT NULL,
  `desired_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('requested','confirmed','completed','cancelled') NOT NULL DEFAULT 'requested',
  `created_by` int(10) unsigned NOT NULL,
  `confirmed_by` int(10) unsigned DEFAULT NULL,
  `cancelled_by` int(10) unsigned DEFAULT NULL,
  `converted_booking_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tta_status_date` (`status`,`desired_date`),
  KEY `idx_tta_client` (`client_id`),
  KEY `idx_tta_converted_booking` (`converted_booking_id`),
  KEY `fk_tta_created_by` (`created_by`),
  KEY `fk_tta_confirmed_by` (`confirmed_by`),
  KEY `fk_tta_cancelled_by` (`cancelled_by`),
  CONSTRAINT `fk_tta_cancelled_by` FOREIGN KEY (`cancelled_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_tta_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tta_confirmed_by` FOREIGN KEY (`confirmed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_tta_converted_booking` FOREIGN KEY (`converted_booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tta_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `taste_test_appointments`
--

LOCK TABLES `taste_test_appointments` WRITE;
/*!40000 ALTER TABLE `taste_test_appointments` DISABLE KEYS */;
/*!40000 ALTER TABLE `taste_test_appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `taste_test_feedback`
--

DROP TABLE IF EXISTS `taste_test_feedback`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `taste_test_feedback` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `appointment_id` int(10) unsigned NOT NULL,
  `rating` tinyint(3) unsigned DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `recorded_by` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ttf_appt` (`appointment_id`),
  KEY `fk_ttf_recorder` (`recorded_by`),
  CONSTRAINT `fk_ttf_appt` FOREIGN KEY (`appointment_id`) REFERENCES `taste_test_appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ttf_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `taste_test_feedback`
--

LOCK TABLES `taste_test_feedback` WRITE;
/*!40000 ALTER TABLE `taste_test_feedback` DISABLE KEYS */;
/*!40000 ALTER TABLE `taste_test_feedback` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `taste_testing`
--

DROP TABLE IF EXISTS `taste_testing`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `taste_testing` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `scheduled_date` date NOT NULL,
  `scheduled_time` time DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled','converted') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `converted_to_booking_id` int(10) unsigned DEFAULT NULL,
  `converted_at` timestamp NULL DEFAULT NULL,
  `converted_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tt_client` (`client_id`),
  KEY `idx_tt_date` (`scheduled_date`),
  KEY `idx_tt_status` (`status`),
  KEY `fk_tt_created` (`created_by`),
  KEY `fk_tt_booking` (`converted_to_booking_id`),
  KEY `fk_tt_converter` (`converted_by`),
  CONSTRAINT `fk_tt_booking` FOREIGN KEY (`converted_to_booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tt_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  CONSTRAINT `fk_tt_converter` FOREIGN KEY (`converted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tt_created` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `taste_testing`
--

LOCK TABLES `taste_testing` WRITE;
/*!40000 ALTER TABLE `taste_testing` DISABLE KEYS */;
/*!40000 ALTER TABLE `taste_testing` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','frontdesk','staff') NOT NULL DEFAULT 'staff',
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `job_class` enum('head_cook','cook','waiter','server','helper','any','admin','super_admin','frontdesk') NOT NULL DEFAULT 'any',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (19,'Super Admin','superadmin@yazzies.com','$2y$10$9YHJMyHpEIVCb2r.Zqgd0e0/rn5SaYZiKYXd2bZ40t9iJjKoNdLYm','super_admin',NULL,1,'2026-04-18 15:58:43','super_admin'),(20,'Administrator','admin@yazzies.com','$2y$10$m3kR5eihgUlRQFNd6q9oB.GWiE.vA0lnjEUN2UhM6PSanHTdBNDZ.','admin',NULL,1,'2026-04-18 15:58:43','admin'),(21,'Frontdesk Staff','frontdesk@yazzies.com','$2y$10$d6giE.RfXHmIPuYwtrBAO.e9Zjp7MdZXpVxz95QqErYBhhPyLN2Iq','frontdesk',NULL,1,'2026-04-18 15:58:43','frontdesk'),(22,'Paw Casiano','pawcasiano@kld.edu.ph','$2y$10$pMqS6B3PC5V69ESWvdxCquy6N5QXHGX12HiNVule9aupDtLZ8G7VK','staff',NULL,1,'2026-04-18 15:58:43','waiter');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-19 12:43:33
