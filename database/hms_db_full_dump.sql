-- MariaDB dump 10.17  Distrib 10.4.11-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: hms_db
-- ------------------------------------------------------
-- Server version	10.4.11-MariaDB

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
-- Table structure for table `admissions`
--

DROP TABLE IF EXISTS `admissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `encounter_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `admitting_doctor_id` int(11) NOT NULL,
  `admission_reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `initial_bed_id` int(11) NOT NULL,
  `current_bed_id` int(11) NOT NULL,
  `admission_datetime` datetime NOT NULL,
  `expected_discharge_date` date DEFAULT NULL,
  `status` enum('Admitted','Discharged','Transferred') COLLATE utf8mb4_unicode_ci DEFAULT 'Admitted',
  PRIMARY KEY (`id`),
  UNIQUE KEY `encounter_id` (`encounter_id`),
  KEY `patient_id` (`patient_id`),
  KEY `admitting_doctor_id` (`admitting_doctor_id`),
  KEY `initial_bed_id` (`initial_bed_id`),
  KEY `current_bed_id` (`current_bed_id`),
  CONSTRAINT `admissions_ibfk_1` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `admissions_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `admissions_ibfk_3` FOREIGN KEY (`admitting_doctor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `admissions_ibfk_4` FOREIGN KEY (`initial_bed_id`) REFERENCES `beds` (`id`),
  CONSTRAINT `admissions_ibfk_5` FOREIGN KEY (`current_bed_id`) REFERENCES `beds` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admissions`
--

LOCK TABLES `admissions` WRITE;
/*!40000 ALTER TABLE `admissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `admissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `department_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `slot_time` time NOT NULL,
  `token_number` int(11) DEFAULT 1,
  `type` enum('New','Follow-up','Routine') COLLATE utf8mb4_unicode_ci DEFAULT 'New',
  `status` enum('Scheduled','Confirmed','Checked-In','In-Consultation','Completed','Cancelled','No-Show') COLLATE utf8mb4_unicode_ci DEFAULT 'Scheduled',
  `reason_for_visit` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancellation_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointment_number` (`appointment_number`),
  KEY `idx_doc_date` (`doctor_id`,`appointment_date`),
  KEY `idx_patient_apt` (`patient_id`),
  KEY `department_id` (`department_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `appointments_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (1,'APT-20260918-001',1,2,3,'2026-09-18','11:00:00',1,'New','Checked-In','Hypertension and headache evaluation',NULL,1,'2026-09-18 00:27:02'),(2,'APT-20260925-001',1,2,3,'2026-09-25','10:00:00',1,'Follow-up','Scheduled','Clinical follow-up & lab review after consultation',NULL,1,'2026-09-18 00:27:02'),(3,'APT-20260918-002',2,2,3,'2026-09-18','11:00:00',2,'New','Checked-In','Hypertension and headache evaluation',NULL,1,'2026-09-18 05:07:19'),(4,'APT-20260925-002',2,2,3,'2026-09-25','10:00:00',2,'Follow-up','Scheduled','Clinical follow-up & lab review after consultation',NULL,1,'2026-09-18 05:07:19'),(8,'APT-20260918-003',2,2,3,'2026-09-18','10:00:00',3,'New','Checked-In','',NULL,1,'2026-09-18 10:52:42');
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `table_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `record_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `old_values` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_values` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_event` (`user_id`,`event_type`),
  KEY `idx_table_record` (`table_name`,`record_id`)
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'SYSTEM_INIT','system','1',NULL,'{\"status\":\"Pristine database ready for live operation\"}','127.0.0.1','CLI/Migration','2026-09-18 00:26:58'),(2,NULL,'CREATE','patients','1',NULL,'{\"mrn\":\"MRN-2026-0001\"}','127.0.0.1','CLI/Browser','2026-09-18 00:27:02'),(3,NULL,'CREATE','appointments','1',NULL,'{\"token\":1}','127.0.0.1','CLI/Browser','2026-09-18 00:27:02'),(4,NULL,'CREATE','payments','1',NULL,'{\"receipt\":\"RCPT-2026-0001\",\"amount\":4204}','127.0.0.1','CLI/Browser','2026-09-18 00:27:02'),(5,7,'LOGIN','users','7',NULL,'{\"username\":\"lab.usman\"}','::1','CLI/Browser','2026-09-18 05:05:58'),(6,6,'LOGIN','users','6',NULL,'{\"username\":\"pharm.kamran\"}','::1','CLI/Browser','2026-09-18 05:05:58'),(7,5,'LOGIN','users','5',NULL,'{\"username\":\"nurse.fatima\"}','::1','CLI/Browser','2026-09-18 05:05:59'),(8,2,'LOGIN','users','2',NULL,'{\"username\":\"dr.tariq\"}','::1','CLI/Browser','2026-09-18 05:05:59'),(9,8,'LOGIN','users','8',NULL,'{\"username\":\"cashier.hassan\"}','::1','CLI/Browser','2026-09-18 05:06:00'),(10,9,'LOGIN','users','9',NULL,'{\"username\":\"reception.sana\"}','::1','CLI/Browser','2026-09-18 05:06:00'),(11,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 05:06:01'),(12,7,'LOGIN','users','7',NULL,'{\"username\":\"lab.usman\"}','::1','CLI/Browser','2026-09-18 05:06:50'),(13,6,'LOGIN','users','6',NULL,'{\"username\":\"pharm.kamran\"}','::1','CLI/Browser','2026-09-18 05:06:50'),(14,10,'LOGIN','users','10',NULL,'{\"username\":\"radio.farooq\"}','::1','CLI/Browser','2026-09-18 05:06:51'),(15,5,'LOGIN','users','5',NULL,'{\"username\":\"nurse.fatima\"}','::1','CLI/Browser','2026-09-18 05:06:51'),(16,2,'LOGIN','users','2',NULL,'{\"username\":\"dr.tariq\"}','::1','CLI/Browser','2026-09-18 05:06:51'),(17,8,'LOGIN','users','8',NULL,'{\"username\":\"cashier.hassan\"}','::1','CLI/Browser','2026-09-18 05:06:52'),(18,9,'LOGIN','users','9',NULL,'{\"username\":\"reception.sana\"}','::1','CLI/Browser','2026-09-18 05:06:52'),(19,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 05:06:53'),(20,NULL,'CREATE','patients','2',NULL,'{\"mrn\":\"MRN-2026-0002\"}','127.0.0.1','CLI/Browser','2026-09-18 05:07:19'),(21,NULL,'CREATE','appointments','3',NULL,'{\"token\":2}','127.0.0.1','CLI/Browser','2026-09-18 05:07:19'),(22,NULL,'CREATE','payments','2',NULL,'{\"receipt\":\"RCPT-2026-0002\",\"amount\":4204}','127.0.0.1','CLI/Browser','2026-09-18 05:07:19'),(23,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 05:07:26'),(24,1,'LOGOUT','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 05:10:14'),(25,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 05:14:12'),(26,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 05:14:26'),(27,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 05:15:29'),(28,1,'CREATE','users','13',NULL,'{\"username\":\"test.specialist\",\"role_id\":3,\"full_name\":\"Dr. Test Specialist\"}','::1','CLI/Browser','2026-09-18 05:15:29'),(29,1,'UPDATE','users','13',NULL,'{\"status\":\"Inactive\",\"username\":\"test.specialist\"}','::1','CLI/Browser','2026-09-18 05:15:29'),(30,1,'UPDATE','users','13',NULL,'{\"status\":\"Active\",\"username\":\"test.specialist\"}','::1','CLI/Browser','2026-09-18 05:15:29'),(31,1,'UPDATE','users','13',NULL,'{\"action\":\"password_reset\",\"username\":\"test.specialist\"}','::1','CLI/Browser','2026-09-18 05:15:30'),(32,13,'LOGIN','users','13',NULL,'{\"username\":\"test.specialist\"}','::1','CLI/Browser','2026-09-18 05:15:30'),(33,7,'LOGIN','users','7',NULL,'{\"username\":\"lab.usman\"}','::1','CLI/Browser','2026-09-18 05:15:34'),(34,6,'LOGIN','users','6',NULL,'{\"username\":\"pharm.kamran\"}','::1','CLI/Browser','2026-09-18 05:15:34'),(35,10,'LOGIN','users','10',NULL,'{\"username\":\"radio.farooq\"}','::1','CLI/Browser','2026-09-18 05:15:35'),(36,5,'LOGIN','users','5',NULL,'{\"username\":\"nurse.fatima\"}','::1','CLI/Browser','2026-09-18 05:15:35'),(37,2,'LOGIN','users','2',NULL,'{\"username\":\"dr.tariq\"}','::1','CLI/Browser','2026-09-18 05:15:35'),(38,8,'LOGIN','users','8',NULL,'{\"username\":\"cashier.hassan\"}','::1','CLI/Browser','2026-09-18 05:15:36'),(39,9,'LOGIN','users','9',NULL,'{\"username\":\"reception.sana\"}','::1','CLI/Browser','2026-09-18 05:15:36'),(40,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 05:15:37'),(41,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 07:18:49'),(42,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 07:30:33'),(43,12,'LOGIN','users','12',NULL,'{\"username\":\"admin.hashim\"}','::1','CLI/Browser','2026-09-18 07:30:33'),(44,14,'LOGIN','users','14',NULL,'{\"username\":\"dr.asif\"}','::1','CLI/Browser','2026-09-18 07:30:33'),(45,7,'LOGIN','users','7',NULL,'{\"username\":\"lab.usman\"}','::1','CLI/Browser','2026-09-18 07:30:34'),(46,6,'LOGIN','users','6',NULL,'{\"username\":\"pharm.kamran\"}','::1','CLI/Browser','2026-09-18 07:30:34'),(47,10,'LOGIN','users','10',NULL,'{\"username\":\"radio.farooq\"}','::1','CLI/Browser','2026-09-18 07:30:34'),(48,5,'LOGIN','users','5',NULL,'{\"username\":\"nurse.fatima\"}','::1','CLI/Browser','2026-09-18 07:30:34'),(49,11,'LOGIN','users','11',NULL,'{\"username\":\"dr.imran\"}','::1','CLI/Browser','2026-09-18 07:30:34'),(50,2,'LOGIN','users','2',NULL,'{\"username\":\"dr.tariq\"}','::1','CLI/Browser','2026-09-18 07:30:35'),(51,15,'LOGIN','users','15',NULL,'{\"username\":\"dr.noman\"}','::1','CLI/Browser','2026-09-18 07:30:35'),(52,16,'LOGIN','users','16',NULL,'{\"username\":\"dr.sumaira\"}','::1','CLI/Browser','2026-09-18 07:30:35'),(53,8,'LOGIN','users','8',NULL,'{\"username\":\"cashier.hassan\"}','::1','CLI/Browser','2026-09-18 07:30:35'),(54,9,'LOGIN','users','9',NULL,'{\"username\":\"reception.sana\"}','::1','CLI/Browser','2026-09-18 07:30:35'),(55,17,'LOGIN','users','17',NULL,'{\"username\":\"store.zubair\"}','::1','CLI/Browser','2026-09-18 07:30:35'),(56,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 07:32:09'),(57,12,'LOGIN','users','12',NULL,'{\"username\":\"admin.hashim\"}','::1','CLI/Browser','2026-09-18 07:32:09'),(58,14,'LOGIN','users','14',NULL,'{\"username\":\"dr.asif\"}','::1','CLI/Browser','2026-09-18 07:32:10'),(59,7,'LOGIN','users','7',NULL,'{\"username\":\"lab.usman\"}','::1','CLI/Browser','2026-09-18 07:32:10'),(60,6,'LOGIN','users','6',NULL,'{\"username\":\"pharm.kamran\"}','::1','CLI/Browser','2026-09-18 07:32:10'),(61,10,'LOGIN','users','10',NULL,'{\"username\":\"radio.farooq\"}','::1','CLI/Browser','2026-09-18 07:32:10'),(62,5,'LOGIN','users','5',NULL,'{\"username\":\"nurse.fatima\"}','::1','CLI/Browser','2026-09-18 07:32:11'),(63,11,'LOGIN','users','11',NULL,'{\"username\":\"dr.imran\"}','::1','CLI/Browser','2026-09-18 07:32:11'),(64,2,'LOGIN','users','2',NULL,'{\"username\":\"dr.tariq\"}','::1','CLI/Browser','2026-09-18 07:32:11'),(65,15,'LOGIN','users','15',NULL,'{\"username\":\"dr.noman\"}','::1','CLI/Browser','2026-09-18 07:32:11'),(66,16,'LOGIN','users','16',NULL,'{\"username\":\"dr.sumaira\"}','::1','CLI/Browser','2026-09-18 07:32:12'),(67,8,'LOGIN','users','8',NULL,'{\"username\":\"cashier.hassan\"}','::1','CLI/Browser','2026-09-18 07:32:12'),(68,9,'LOGIN','users','9',NULL,'{\"username\":\"reception.sana\"}','::1','CLI/Browser','2026-09-18 07:32:12'),(69,17,'LOGIN','users','17',NULL,'{\"username\":\"store.zubair\"}','::1','CLI/Browser','2026-09-18 07:32:12'),(70,1,'LOGOUT','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 07:44:59'),(71,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 07:49:15'),(72,12,'LOGIN','users','12',NULL,'{\"username\":\"admin.hashim\"}','::1','CLI/Browser','2026-09-18 07:49:15'),(73,14,'LOGIN','users','14',NULL,'{\"username\":\"dr.asif\"}','::1','CLI/Browser','2026-09-18 07:49:15'),(74,7,'LOGIN','users','7',NULL,'{\"username\":\"lab.usman\"}','::1','CLI/Browser','2026-09-18 07:49:16'),(75,6,'LOGIN','users','6',NULL,'{\"username\":\"pharm.kamran\"}','::1','CLI/Browser','2026-09-18 07:49:16'),(76,10,'LOGIN','users','10',NULL,'{\"username\":\"radio.farooq\"}','::1','CLI/Browser','2026-09-18 07:49:16'),(77,5,'LOGIN','users','5',NULL,'{\"username\":\"nurse.fatima\"}','::1','CLI/Browser','2026-09-18 07:49:16'),(78,11,'LOGIN','users','11',NULL,'{\"username\":\"dr.imran\"}','::1','CLI/Browser','2026-09-18 07:49:17'),(79,2,'LOGIN','users','2',NULL,'{\"username\":\"dr.tariq\"}','::1','CLI/Browser','2026-09-18 07:49:17'),(80,15,'LOGIN','users','15',NULL,'{\"username\":\"dr.noman\"}','::1','CLI/Browser','2026-09-18 07:49:17'),(81,16,'LOGIN','users','16',NULL,'{\"username\":\"dr.sumaira\"}','::1','CLI/Browser','2026-09-18 07:49:17'),(82,8,'LOGIN','users','8',NULL,'{\"username\":\"cashier.hassan\"}','::1','CLI/Browser','2026-09-18 07:49:17'),(83,9,'LOGIN','users','9',NULL,'{\"username\":\"reception.sana\"}','::1','CLI/Browser','2026-09-18 07:49:18'),(84,17,'LOGIN','users','17',NULL,'{\"username\":\"store.zubair\"}','::1','CLI/Browser','2026-09-18 07:49:18'),(85,6,'LOGIN','users','6',NULL,'{\"username\":\"pharm.kamran\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 07:54:19'),(86,6,'LOGOUT','users','6',NULL,'{\"username\":\"pharm.kamran\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 10:36:24'),(87,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 10:36:27'),(88,1,'LOGOUT','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 10:37:23'),(89,6,'LOGIN','users','6',NULL,'{\"username\":\"pharm.kamran\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 10:37:33'),(90,6,'LOGOUT','users','6',NULL,'{\"username\":\"pharm.kamran\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 10:40:23'),(91,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 10:40:26'),(92,1,'LOGOUT','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 10:50:54'),(93,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 10:51:59'),(94,1,'CREATE','appointments','8',NULL,'{\"apt_number\":\"APT-20260918-003\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 10:52:42'),(95,1,'LOGOUT','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 10:53:06'),(96,2,'LOGIN','users','2',NULL,'{\"username\":\"dr.tariq\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 10:53:40'),(97,2,'UPDATE','encounters','3',NULL,'{\"diagnosis\":\"tuborclosis\",\"status\":\"In-Progress\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 12:17:43'),(98,2,'LOGOUT','users','2',NULL,'{\"username\":\"dr.tariq\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 13:38:23'),(99,2,'LOGIN','users','2',NULL,'{\"username\":\"dr.tariq\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 13:38:34'),(100,2,'LOGOUT','users','2',NULL,'{\"username\":\"dr.tariq\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 13:39:24'),(101,6,'LOGIN','users','6',NULL,'{\"username\":\"pharm.kamran\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 13:39:30'),(102,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 14:18:42'),(103,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 14:20:52'),(104,1,'CREATE','users','18',NULL,'{\"username\":\"test.specialist\",\"role_id\":3,\"full_name\":\"Dr. Test Specialist\"}','::1','CLI/Browser','2026-09-18 14:20:52'),(105,1,'UPDATE','users','18',NULL,'{\"status\":\"Inactive\",\"username\":\"test.specialist\"}','::1','CLI/Browser','2026-09-18 14:20:52'),(106,1,'UPDATE','users','18',NULL,'{\"status\":\"Active\",\"username\":\"test.specialist\"}','::1','CLI/Browser','2026-09-18 14:20:52'),(107,1,'UPDATE','users','18',NULL,'{\"action\":\"password_reset\",\"username\":\"test.specialist\"}','::1','CLI/Browser','2026-09-18 14:20:52'),(108,18,'LOGIN','users','18',NULL,'{\"username\":\"test.specialist\"}','::1','CLI/Browser','2026-09-18 14:20:53'),(109,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 14:23:41'),(110,1,'LOGOUT','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 14:23:41'),(111,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 14:23:43'),(112,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 14:27:09'),(113,1,'LOGOUT','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 14:27:34'),(114,6,'LOGIN','users','6',NULL,'{\"username\":\"pharm.kamran\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 14:27:43'),(115,6,'LOGOUT','users','6',NULL,'{\"username\":\"pharm.kamran\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 14:28:35'),(116,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/152.0.0.0 Safari/537.36','2026-09-18 14:28:43'),(117,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','CLI/Browser','2026-09-18 14:31:43'),(118,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Microsoft Windows 10.0.19045; en-US) PowerShell/7.6.5','2026-09-18 14:31:45'),(119,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-28 13:53:45'),(120,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','::1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36','2026-10-29 06:33:04'),(121,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 10:32:25'),(122,1,'LOGIN','users','1',NULL,'{\"username\":\"superadmin\"}','127.0.0.1','Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/156.0.0.0 Safari/537.36 Edg/156.0.0.0','2026-10-29 11:40:21');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bed_allocations`
--

DROP TABLE IF EXISTS `bed_allocations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bed_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admission_id` int(11) NOT NULL,
  `bed_id` int(11) NOT NULL,
  `allocated_at` datetime NOT NULL,
  `released_at` datetime DEFAULT NULL,
  `transfer_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `allocated_by` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `admission_id` (`admission_id`),
  KEY `bed_id` (`bed_id`),
  KEY `allocated_by` (`allocated_by`),
  CONSTRAINT `bed_allocations_ibfk_1` FOREIGN KEY (`admission_id`) REFERENCES `admissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bed_allocations_ibfk_2` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`),
  CONSTRAINT `bed_allocations_ibfk_3` FOREIGN KEY (`allocated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bed_allocations`
--

LOCK TABLES `bed_allocations` WRITE;
/*!40000 ALTER TABLE `bed_allocations` DISABLE KEYS */;
/*!40000 ALTER TABLE `bed_allocations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `beds`
--

DROP TABLE IF EXISTS `beds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `beds` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_id` int(11) NOT NULL,
  `bed_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bed_type` enum('Standard','Electric','ICU_Ventilated','Emergency_Trolley','Pediatric_Cot') COLLATE utf8mb4_unicode_ci DEFAULT 'Standard',
  `daily_tariff` decimal(10,2) NOT NULL DEFAULT 1500.00,
  `status` enum('Available','Reserved','Occupied','Cleaning','Maintenance') COLLATE utf8mb4_unicode_ci DEFAULT 'Available',
  `current_encounter_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `room_id` (`room_id`),
  KEY `current_encounter_id` (`current_encounter_id`),
  CONSTRAINT `beds_ibfk_1` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`) ON DELETE CASCADE,
  CONSTRAINT `beds_ibfk_2` FOREIGN KEY (`current_encounter_id`) REFERENCES `encounters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `beds`
--

LOCK TABLES `beds` WRITE;
/*!40000 ALTER TABLE `beds` DISABLE KEYS */;
INSERT INTO `beds` VALUES (1,1,'MMW-B1','Standard',1500.00,'Available',NULL),(2,1,'MMW-B2','Standard',1500.00,'Available',NULL),(3,1,'MMW-B3','Standard',1500.00,'Available',NULL),(4,1,'MMW-B4','Standard',1500.00,'Available',NULL),(5,2,'FMW-B1','Standard',1500.00,'Available',NULL),(6,2,'FMW-B2','Standard',1500.00,'Available',NULL),(7,3,'ICU-B1','ICU_Ventilated',8500.00,'Available',NULL),(8,3,'ICU-B2','ICU_Ventilated',8500.00,'Available',NULL),(9,4,'PVT-201','Electric',6000.00,'Available',NULL),(10,5,'PVT-202','Electric',6000.00,'Available',NULL),(11,6,'ER-T1','Emergency_Trolley',2500.00,'Available',NULL),(12,6,'ER-T2','Emergency_Trolley',2500.00,'Available',NULL);
/*!40000 ALTER TABLE `beds` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bill_items`
--

DROP TABLE IF EXISTS `bill_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bill_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_id` int(11) NOT NULL,
  `item_type` enum('Consultation','Bed_Charge','Medication','Lab_Test','Procedure','Nursing','Other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount` decimal(10,2) DEFAULT 0.00,
  `net_price` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `bill_id` (`bill_id`),
  CONSTRAINT `bill_items_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bill_items`
--

LOCK TABLES `bill_items` WRITE;
/*!40000 ALTER TABLE `bill_items` DISABLE KEYS */;
INSERT INTO `bill_items` VALUES (1,1,'Consultation',NULL,'Cardiologist Consultation Fee',1,2500.00,2500.00,0.00,2500.00,'2026-09-18 00:27:02'),(2,1,'',1,'Lab Test: Complete Blood Count (CBC)',1,850.00,850.00,0.00,850.00,'2026-09-18 00:27:02'),(3,1,'',2,'Lab Test: Blood Glucose Fasting (FBG)',1,350.00,350.00,0.00,350.00,'2026-09-18 00:27:02'),(4,1,'',3,'Pharmacy: Risek (Omeprazole) (Batch #BT-RSK-9901)',14,36.00,504.00,0.00,504.00,'2026-09-18 00:27:02'),(5,2,'Consultation',NULL,'Cardiologist Consultation Fee',1,2500.00,2500.00,0.00,2500.00,'2026-09-18 05:07:19'),(6,2,'',1,'Lab Test: Complete Blood Count (CBC)',1,850.00,850.00,0.00,850.00,'2026-09-18 05:07:19'),(7,2,'',2,'Lab Test: Blood Glucose Fasting (FBG)',1,350.00,350.00,0.00,350.00,'2026-09-18 05:07:19'),(8,2,'',3,'Pharmacy: Risek (Omeprazole) (Batch #BT-RSK-9901)',14,36.00,504.00,0.00,504.00,'2026-09-18 05:07:19'),(9,1,'Medication',1,'Pharmacy: Risek 40mg (1 Cap (40mg)) [Batch: BT-RSK-9901, Exp: 02/27]',14,36.00,504.00,0.00,504.00,'2026-09-18 08:31:08'),(10,3,'Consultation',NULL,'Specialist Doctor Consultation Fee',1,2000.00,2000.00,0.00,2000.00,'2026-09-18 12:17:43'),(11,3,'',1,'Lab Test: Complete Blood Count (CBC with ESR)',1,850.00,850.00,0.00,850.00,'2026-09-18 12:17:43'),(12,3,'',2,'Lab Test: Fasting Blood Glucose (FBG)',1,350.00,350.00,0.00,350.00,'2026-09-18 12:17:43'),(13,3,'',7,'Lab Test: Hepatitis B Surface Antigen (HBsAg Rapid)',1,750.00,750.00,0.00,750.00,'2026-09-18 12:17:43'),(14,3,'',5,'Lab Test: Lipid Profile (Cholesterol, HDL, LDL, Triglycerides)',1,1600.00,1600.00,0.00,1600.00,'2026-09-18 12:17:43'),(15,3,'',4,'Lab Test: Liver Function Test (LFT Profile)',1,1400.00,1400.00,0.00,1400.00,'2026-09-18 12:17:43'),(16,3,'',3,'Lab Test: Renal Function Test (Urea, Creatinine)',1,1100.00,1100.00,0.00,1100.00,'2026-09-18 12:17:43'),(17,3,'',6,'Lab Test: Urine Routine Examination (Urine R/E)',1,450.00,450.00,0.00,450.00,'2026-09-18 12:17:43');
/*!40000 ALTER TABLE `bill_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bills`
--

DROP TABLE IF EXISTS `bills`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bills` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bill_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `encounter_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `bill_type` enum('OPD','IPD','Emergency') COLLATE utf8mb4_unicode_ci NOT NULL,
  `bill_date` datetime NOT NULL,
  `gross_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `tax_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `net_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `paid_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('Unpaid','Partially-Paid','Fully-Paid','Refunded') COLLATE utf8mb4_unicode_ci DEFAULT 'Unpaid',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `bill_number` (`bill_number`),
  KEY `idx_bill_patient` (`patient_id`),
  KEY `idx_bill_enc` (`encounter_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `bills_ibfk_1` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`),
  CONSTRAINT `bills_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `bills_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bills`
--

LOCK TABLES `bills` WRITE;
/*!40000 ALTER TABLE `bills` DISABLE KEYS */;
INSERT INTO `bills` VALUES (1,'INV-2026-0001',1,1,'OPD','2026-09-18 05:27:02',4708.00,0.00,0.00,4708.00,4204.00,504.00,'Fully-Paid',1,'2026-09-18 00:27:02'),(2,'INV-2026-0002',2,2,'OPD','2026-09-18 10:07:19',4204.00,0.00,0.00,4204.00,4204.00,0.00,'Fully-Paid',1,'2026-09-18 05:07:19'),(3,'INV-2026-0003',3,2,'OPD','2026-09-18 17:17:43',8500.00,0.00,0.00,8500.00,0.00,8500.00,'Unpaid',2,'2026-09-18 12:17:43');
/*!40000 ALTER TABLE `bills` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blood_donors`
--

DROP TABLE IF EXISTS `blood_donors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blood_donors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `donor_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` enum('Male','Female','Other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `dob` date DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cnic` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_donation_date` date DEFAULT NULL,
  `screening_status` enum('Eligible','Deferred','Permanently_Ineligible') COLLATE utf8mb4_unicode_ci DEFAULT 'Eligible',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `donor_number` (`donor_number`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blood_donors`
--

LOCK TABLES `blood_donors` WRITE;
/*!40000 ALTER TABLE `blood_donors` DISABLE KEYS */;
INSERT INTO `blood_donors` VALUES (1,'DNR-2026-001','Hamza Qureshi','Male',NULL,'O+','+92 300 1122334','17301-1122334-1',NULL,'Eligible','2026-09-17 20:16:02'),(2,'DNR-2026-002','Adnan Malik','Male',NULL,'A+','+92 333 4455667','17301-5566778-2',NULL,'Eligible','2026-09-17 20:16:02'),(3,'DNR-2026-003','Rashid Mehmood','Male',NULL,'B+','+92 345 7788990','17301-9988112-3',NULL,'Eligible','2026-09-17 20:16:02');
/*!40000 ALTER TABLE `blood_donors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blood_requests`
--

DROP TABLE IF EXISTS `blood_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blood_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `request_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `encounter_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `component_type` enum('Whole_Blood','Packed_Red_Cells_PRBC','Fresh_Frozen_Plasma_FFP','Platelets_PC','Cryoprecipitate') COLLATE utf8mb4_unicode_ci NOT NULL,
  `units_required` int(11) NOT NULL DEFAULT 1,
  `urgency` enum('Routine','Urgent','Life_Saving_STAT') COLLATE utf8mb4_unicode_ci DEFAULT 'Routine',
  `clinical_indication` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Pending','Cross_Matched','Partially_Issued','Issued','Cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `request_number` (`request_number`),
  KEY `encounter_id` (`encounter_id`),
  KEY `patient_id` (`patient_id`),
  KEY `requested_by` (`requested_by`),
  CONSTRAINT `blood_requests_ibfk_1` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `blood_requests_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `blood_requests_ibfk_3` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blood_requests`
--

LOCK TABLES `blood_requests` WRITE;
/*!40000 ALTER TABLE `blood_requests` DISABLE KEYS */;
/*!40000 ALTER TABLE `blood_requests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `blood_units`
--

DROP TABLE IF EXISTS `blood_units`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blood_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unit_barcode` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `donor_id` int(11) DEFAULT NULL,
  `component_type` enum('Whole_Blood','Packed_Red_Cells_PRBC','Fresh_Frozen_Plasma_FFP','Platelets_PC','Cryoprecipitate') COLLATE utf8mb4_unicode_ci NOT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-') COLLATE utf8mb4_unicode_ci NOT NULL,
  `volume_ml` int(11) DEFAULT 450,
  `collection_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `storage_freezer_location` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Refrigerated Bay A',
  `status` enum('Available','Reserved_CrossMatch','Issued','Expired','Discarded') COLLATE utf8mb4_unicode_ci DEFAULT 'Available',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unit_barcode` (`unit_barcode`),
  KEY `donor_id` (`donor_id`),
  CONSTRAINT `blood_units_ibfk_1` FOREIGN KEY (`donor_id`) REFERENCES `blood_donors` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `blood_units`
--

LOCK TABLES `blood_units` WRITE;
/*!40000 ALTER TABLE `blood_units` DISABLE KEYS */;
INSERT INTO `blood_units` VALUES (1,'BLD-OP-2601',1,'Packed_Red_Cells_PRBC','O+',450,'2026-09-18','2026-10-23','Cold Storage A1','Available'),(2,'BLD-AP-2602',2,'Packed_Red_Cells_PRBC','A+',450,'2026-09-18','2026-10-23','Cold Storage A2','Available'),(3,'BLD-BP-2603',3,'Packed_Red_Cells_PRBC','B+',450,'2026-09-18','2026-10-23','Cold Storage A3','Available'),(4,'BLD-FFP-2604',1,'Fresh_Frozen_Plasma_FFP','O+',250,'2026-09-18','2027-09-18','Deep Freezer B1 (-30C)','Available');
/*!40000 ALTER TABLE `blood_units` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clinical_vitals`
--

DROP TABLE IF EXISTS `clinical_vitals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clinical_vitals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `encounter_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `recorded_by` int(11) NOT NULL,
  `temperature_celsius` decimal(4,1) DEFAULT NULL,
  `pulse_rate_bpm` int(11) DEFAULT NULL,
  `systolic_bp` int(11) DEFAULT NULL,
  `diastolic_bp` int(11) DEFAULT NULL,
  `respiratory_rate` int(11) DEFAULT NULL,
  `spo2_percentage` int(11) DEFAULT NULL,
  `blood_glucose_mg_dl` int(11) DEFAULT NULL,
  `height_cm` decimal(5,1) DEFAULT NULL,
  `weight_kg` decimal(5,1) DEFAULT NULL,
  `bmi` decimal(4,1) DEFAULT NULL,
  `pain_scale` tinyint(4) DEFAULT NULL,
  `notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `encounter_id` (`encounter_id`),
  KEY `patient_id` (`patient_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `clinical_vitals_ibfk_1` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clinical_vitals_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `clinical_vitals_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clinical_vitals`
--

LOCK TABLES `clinical_vitals` WRITE;
/*!40000 ALTER TABLE `clinical_vitals` DISABLE KEYS */;
INSERT INTO `clinical_vitals` VALUES (1,3,2,2,37.0,78,117,80,NULL,98,NULL,NULL,72.0,NULL,0,NULL,'2026-09-18 12:17:43');
/*!40000 ALTER TABLE `clinical_vitals` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `consultation_notes`
--

DROP TABLE IF EXISTS `consultation_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consultation_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `encounter_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `chief_complaint` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `history_of_present_illness` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `past_medical_history` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `physical_examination` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provisional_diagnosis` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `icd10_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `clinical_advice` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `encounter_id` (`encounter_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `consultation_notes_ibfk_1` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `consultation_notes_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultation_notes`
--

LOCK TABLES `consultation_notes` WRITE;
/*!40000 ALTER TABLE `consultation_notes` DISABLE KEYS */;
INSERT INTO `consultation_notes` VALUES (1,1,2,'Occasional pounding headache, stress related',NULL,NULL,'BP: 145/92 mmHg, Pulse: 78 bpm, Chest clear','Stage 1 Essential Hypertension','I10','Prescribed antihypertensive, ordered CBC & Fasting Glucose. Advised lifestyle modification.','2026-09-25','2026-09-18 00:27:02','2026-09-18 00:27:02'),(2,2,2,'Occasional pounding headache, stress related',NULL,NULL,'BP: 145/92 mmHg, Pulse: 78 bpm, Chest clear','Stage 1 Essential Hypertension','I10','Prescribed antihypertensive, ordered CBC & Fasting Glucose. Advised lifestyle modification.','2026-09-25','2026-09-18 05:07:19','2026-09-18 05:07:19'),(3,3,2,'fever pain headache',NULL,NULL,'','tuborclosis','','',NULL,'2026-09-18 12:17:43','2026-09-18 12:17:43');
/*!40000 ALTER TABLE `consultation_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `departments`
--

DROP TABLE IF EXISTS `departments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('Clinical','Ancillary','Administrative','Support') COLLATE utf8mb4_unicode_ci DEFAULT 'Clinical',
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `departments`
--

LOCK TABLES `departments` WRITE;
/*!40000 ALTER TABLE `departments` DISABLE KEYS */;
INSERT INTO `departments` VALUES (1,'Administration','ADM','Administrative','Hospital Executive Management & IT','Active','2026-09-17 14:05:51'),(2,'General Medicine','MED','Clinical','Internal medicine, chronic conditions and adult care','Active','2026-09-17 14:05:51'),(3,'Cardiology','CARD','Clinical','Heart diseases, ECG, echo and cardiac catheterization','Active','2026-09-17 14:05:51'),(4,'Pediatrics','PED','Clinical','Infant, child and adolescent health','Active','2026-09-17 14:05:51'),(5,'General Surgery','SURG','Clinical','Laparoscopic, trauma and elective surgeries','Active','2026-09-17 14:05:51'),(6,'Obstetrics & Gynecology','OBGYN','Clinical','Maternal health, labor room and women care','Active','2026-09-17 14:05:51'),(7,'Emergency & Trauma (ER)','ER','Clinical','24/7 Acute trauma, resuscitation and triage','Active','2026-09-17 14:05:51'),(8,'Pathology & Laboratory','LAB','Ancillary','Hematology, biochemistry, microbiology & serology','Active','2026-09-17 14:05:51'),(9,'Radiology & Imaging','RAD','Ancillary','Digital X-Ray, Ultrasound, CT Scan & MRI','Active','2026-09-17 14:05:51'),(10,'Inpatient & Wards (IPD)','IPD','Support','Ward care, nursing stations and bed allocation','Active','2026-09-17 14:05:51'),(11,'Pharmacy & Formulary','PHARM','Ancillary','Inpatient dispensing, outpatient pharmacy, formulary','Active','2026-09-17 14:05:51'),(12,'Finance & Billing','FIN','Administrative','Cashier desk, revenue cycle, insurance & claims','Active','2026-09-17 14:05:51');
/*!40000 ALTER TABLE `departments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `discharge_summaries`
--

DROP TABLE IF EXISTS `discharge_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `discharge_summaries` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `encounter_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `admission_diagnosis` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `final_diagnosis` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `hospital_course` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `procedures_performed` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `condition_at_discharge` enum('Cured','Improved','Stable','Unchanged','Guarded','Critical') COLLATE utf8mb4_unicode_ci DEFAULT 'Stable',
  `discharge_medications` longtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dietary_instructions` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `follow_up_instructions` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `next_visit_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `encounter_id` (`encounter_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `discharge_summaries_ibfk_1` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `discharge_summaries_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `discharge_summaries`
--

LOCK TABLES `discharge_summaries` WRITE;
/*!40000 ALTER TABLE `discharge_summaries` DISABLE KEYS */;
/*!40000 ALTER TABLE `discharge_summaries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drug_batches`
--

DROP TABLE IF EXISTS `drug_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drug_batches` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `drug_item_id` int(11) NOT NULL,
  `batch_number` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `manufacturer` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mfg_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `unit_purchase_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_sale_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `initial_quantity` int(11) NOT NULL,
  `current_quantity` int(11) NOT NULL,
  `status` enum('Active','Quarantined','Expired','Depleted') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_batch_exp` (`expiry_date`),
  KEY `drug_item_id` (`drug_item_id`),
  CONSTRAINT `drug_batches_ibfk_1` FOREIGN KEY (`drug_item_id`) REFERENCES `drug_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drug_batches`
--

LOCK TABLES `drug_batches` WRITE;
/*!40000 ALTER TABLE `drug_batches` DISABLE KEYS */;
INSERT INTO `drug_batches` VALUES (1,1,'BT-PAN-2401','GSK Pakistan','2025-01-10','2027-01-09',2.00,3.50,2000,2000,'Active','2026-09-17 14:05:52'),(2,2,'BT-AUG-2399','GSK Pakistan','2025-02-15','2026-11-30',38.00,52.00,500,500,'Active','2026-09-17 14:05:52'),(3,3,'BT-RSK-9901','Getz Pharma','2025-03-01','2027-02-28',25.00,36.00,800,758,'Active','2026-09-17 14:05:52'),(4,4,'BT-LOP-1120','Highnoon Laboratories','2024-11-01','2026-10-31',1.80,3.00,1500,1500,'Active','2026-09-17 14:05:52'),(5,5,'BT-NOR-5521','Pfizer Pakistan','2025-01-05','2027-01-04',12.00,18.00,600,600,'Active','2026-09-17 14:05:52'),(6,6,'BT-GLU-7732','Merck Marker','2025-04-10','2027-04-09',4.50,7.50,1000,1000,'Active','2026-09-17 14:05:52'),(7,7,'BT-ROC-0012','Roche Pakistan','2025-02-20','2026-08-31',280.00,390.00,200,200,'Active','2026-09-17 14:05:52'),(8,8,'BT-TOR-4401','Atco Laboratories','2025-05-15','2027-05-14',45.00,65.00,300,300,'Active','2026-09-17 14:05:52'),(9,10,'BT-MORPH-2026A','Global Pharma Ltd','2026-06-18','2028-03-18',180.00,260.00,50,50,'Active','2026-09-18 08:12:07'),(10,11,'BT-FENT-2026B','Global Pharma Ltd','2026-07-18','2027-11-18',320.00,450.00,40,40,'Active','2026-09-18 08:12:07'),(11,12,'BT-VAL-8821','Global Pharma Ltd','2026-04-18','2027-06-18',95.00,140.00,80,80,'Active','2026-09-18 08:12:07'),(12,13,'BT-MID-4401','Global Pharma Ltd','2026-05-18','2027-08-18',145.00,210.00,60,60,'Active','2026-09-18 08:12:07'),(13,14,'BT-TRM-9912','Global Pharma Ltd','2026-03-18','2027-12-18',180.00,250.00,120,120,'Active','2026-09-18 08:12:07'),(14,15,'BT-AUG-EXP-NEAR','Global Pharma Ltd','2024-11-18','2026-10-23',260.00,320.00,45,45,'Active','2026-09-18 08:12:07'),(15,16,'BT-ROCE-EXP-NEAR','Global Pharma Ltd','2024-12-18','2026-11-05',380.00,490.00,35,35,'Active','2026-09-18 08:12:07'),(16,17,'BT-DIC-EXPIRED','Global Pharma Ltd','2024-07-18','2026-08-31',35.00,60.00,15,15,'Expired','2026-09-18 08:12:07'),(17,18,'BT-BRU-5521','Global Pharma Ltd','2026-05-18','2028-05-18',85.00,120.00,90,90,'Active','2026-09-18 08:12:07'),(18,19,'BT-NS-1000-88','Global Pharma Ltd','2026-06-18','2028-09-18',120.00,190.00,250,250,'Active','2026-09-18 08:12:07'),(19,20,'BT-RL-1000-77','Global Pharma Ltd','2026-06-18','2028-09-18',140.00,220.00,180,180,'Active','2026-09-18 08:12:07'),(20,21,'BT-VEN-3012','Global Pharma Ltd','2026-04-18','2028-01-18',450.00,620.00,65,65,'Active','2026-09-18 08:12:07'),(21,22,'BT-ADR-9021','Global Pharma Ltd','2026-07-18','2027-11-18',85.00,130.00,100,86,'Active','2026-09-18 08:12:07');
/*!40000 ALTER TABLE `drug_batches` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drug_categories`
--

DROP TABLE IF EXISTS `drug_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drug_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drug_categories`
--

LOCK TABLES `drug_categories` WRITE;
/*!40000 ALTER TABLE `drug_categories` DISABLE KEYS */;
INSERT INTO `drug_categories` VALUES (1,'Antibiotics & Antimicrobials','Broad-spectrum and targeted antibacterial therapy'),(2,'Cardiovascular & Antihypertensives','Blood pressure, cardiac, and lipid control'),(3,'Analgesics & Antipyretics','Pain management and fever reduction'),(4,'Gastrointestinal & Antacids','PPIs, H2 blockers, antiemetics and antidiarrheals'),(5,'Antidiabetics & Endocrine','Oral hypoglycemic agents and insulin formulations'),(6,'Respiratory & Bronchodilators','Inhalers, mucolytics and bronchodilators'),(10,'Gastrointestinal & Antiulcerants','Proton pump inhibitors and motility drugs'),(11,'Respiratory & Inhalers','Bronchodilators and respiratory agents'),(12,'Controlled Substances & Narcotics','Strict Schedule II/IV Opioids, sedatives, and controlled anesthetics'),(13,'Parenteral & IV Fluids','Intravenous crystalloids, colloids, and electrolyte infusions'),(14,'Emergency & Resuscitation','Crash cart critical adrenaline, atropine, and vasopressors');
/*!40000 ALTER TABLE `drug_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `drug_items`
--

DROP TABLE IF EXISTS `drug_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `drug_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `brand_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generic_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dosage_form` enum('Tablet','Capsule','Syrup','Injection','Infusion','Ointment','Eye_Drops','Inhaler','Other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `strength` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reorder_level` int(11) DEFAULT 50,
  `unit_of_measure` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'Strip / Box',
  `requires_prescription` tinyint(1) DEFAULT 1,
  `is_controlled_substance` tinyint(1) DEFAULT 0,
  `narcotic_schedule` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `storage_condition` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Room Temperature',
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  PRIMARY KEY (`id`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `drug_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `drug_categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `drug_items`
--

LOCK TABLES `drug_items` WRITE;
/*!40000 ALTER TABLE `drug_items` DISABLE KEYS */;
INSERT INTO `drug_items` VALUES (1,3,'Panadol 500mg','Paracetamol','Tablet','500mg',100,'Box of 200',0,0,NULL,'Room Temperature','Active'),(2,1,'Augmentin 625mg','Amoxicillin + Clavulanic Acid','Tablet','625mg',50,'Pack of 14',1,0,NULL,'Room Temperature','Active'),(3,4,'Risek 40mg','Omeprazole','Capsule','40mg',60,'Pack of 14',1,0,NULL,'Room Temperature','Active'),(4,2,'Loprin 75mg','Aspirin (Low Dose)','Tablet','75mg',80,'Box of 30',0,0,NULL,'Room Temperature','Active'),(5,2,'Norvasc 5mg','Amlodipine Besylate','Tablet','5mg',50,'Pack of 30',1,0,NULL,'Room Temperature','Active'),(6,5,'Glucophage 500mg','Metformin HCl','Tablet','500mg',75,'Box of 50',1,0,NULL,'Room Temperature','Active'),(7,1,'Rocephin 1g IV','Ceftriaxone Sodium','Injection','1000mg',30,'Vial',1,0,NULL,'Room Temperature','Active'),(8,3,'Toradol 30mg','Ketorolac Tromethamine','Injection','30mg/ml',25,'Ampoule',1,0,NULL,'Room Temperature','Active'),(9,6,'Ventolin Inhaler','Salbutamol Sulfate','Inhaler','100mcg/puff',20,'Canister (200 doses)',1,0,NULL,'Room Temperature','Active'),(10,12,'Morphine Inj 10mg/ml','Morphine Sulphate','Injection','10mg/ml (1ml Ampoule)',20,'Ampoule',1,1,'Schedule II (Opioid)','Narcotics Double-Lock Safe','Active'),(11,12,'Fentanyl Inj 50mcg/ml','Fentanyl Citrate','Injection','50mcg/ml (2ml Ampoule)',15,'Ampoule',1,1,'Schedule II (Opioid)','Narcotics Double-Lock Safe','Active'),(12,12,'Valium (Diazepam) Inj 10mg','Diazepam','Injection','10mg/2ml Ampoule',25,'Ampoule',1,1,'Schedule IV (Sedative)','Controlled Cabinet (15-25°C)','Active'),(13,12,'Midazolam Inj 5mg/ml','Midazolam HCl','Injection','5mg/ml Ampoule',20,'Ampoule',1,1,'Schedule IV (Sedative/Pre-op)','Controlled Cabinet','Active'),(14,12,'Tramal (Tramadol) 50mg','Tramadol Hydrochloride','Capsule','50mg',30,'Strip of 10',1,1,'Schedule IV (Opioid)','Room Temperature','Active'),(15,1,'Augmentin 625mg Tab','Amoxicillin + Clavulanic Acid','Tablet','625mg',50,'Strip of 10',1,0,'Prescription Only','Dry Cool Place (<25°C)','Active'),(16,1,'Rocephin (Ceftriaxone) 1g IV','Ceftriaxone Sodium','Injection','1g Vial + Water for Inj',40,'Vial',1,0,'Prescription Only','Cool Room Temp (<25°C)','Active'),(17,3,'Dicloran 75mg Inj','Diclofenac Sodium','Injection','75mg/3ml Ampoule',20,'Ampoule',1,0,'Prescription Only','Quarantine Shelf','Active'),(18,3,'Brufen Suspension 120ml','Ibuprofen','Syrup','100mg/5ml (120ml Bottle)',40,'Bottle',0,0,'OTC','Room Temperature','Active'),(19,13,'Normal Saline 0.9% 1000ml','Sodium Chloride 0.9%','Infusion','0.9% w/v (1000ml Bottle)',100,'IV Bottle',1,0,'Hospital IV Formulation','Room Temperature','Active'),(20,13,'Ringer Lactate (Hartmann) 1000ml','Compound Sodium Lactate','Infusion','1000ml IV Bottle',80,'IV Bottle',1,0,'Hospital IV Formulation','Room Temperature','Active'),(21,11,'Ventolin Evohaler 100mcg','Salbutamol Sulphate','Inhaler','100mcg / Dose (200 Actuations)',30,'Inhaler Device',1,0,'Prescription Only','Below 30°C Protect from frost','Active'),(22,14,'Adrenaline 1:1000 Inj','Epinephrine 1mg/ml','Injection','1mg/ml Ampoule',30,'Ampoule',1,0,'Crash Cart Life-Saving','Protect from Light (2-8°C or <25°C)','Active');
/*!40000 ALTER TABLE `drug_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `encounters`
--

DROP TABLE IF EXISTS `encounters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `encounters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `encounter_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `patient_id` int(11) NOT NULL,
  `encounter_type` enum('OPD','Emergency','IPD') COLLATE utf8mb4_unicode_ci NOT NULL,
  `department_id` int(11) NOT NULL,
  `attending_doctor_id` int(11) NOT NULL,
  `admission_datetime` datetime NOT NULL,
  `discharge_datetime` datetime DEFAULT NULL,
  `encounter_status` enum('In-Progress','Completed','Admitted','Cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'In-Progress',
  `triage_level` enum('ESI-1','ESI-2','ESI-3','ESI-4','ESI-5','Routine') COLLATE utf8mb4_unicode_ci DEFAULT 'Routine',
  `primary_diagnosis` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discharge_disposition` enum('Discharged_Home','Admitted_to_Ward','Admitted_to_ICU','Transferred','LAMA','Deceased') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `encounter_number` (`encounter_number`),
  KEY `idx_enc_patient` (`patient_id`),
  KEY `idx_enc_status` (`encounter_status`),
  KEY `idx_enc_type` (`encounter_type`),
  KEY `department_id` (`department_id`),
  KEY `attending_doctor_id` (`attending_doctor_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `encounters_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `encounters_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`),
  CONSTRAINT `encounters_ibfk_3` FOREIGN KEY (`attending_doctor_id`) REFERENCES `users` (`id`),
  CONSTRAINT `encounters_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `encounters`
--

LOCK TABLES `encounters` WRITE;
/*!40000 ALTER TABLE `encounters` DISABLE KEYS */;
INSERT INTO `encounters` VALUES (1,'ENC-2026-0001',1,'OPD',3,2,'2026-09-18 05:27:02',NULL,'','Routine',NULL,NULL,1,'2026-09-18 00:27:02'),(2,'ENC-2026-0002',2,'OPD',3,2,'2026-09-18 10:07:19',NULL,'','Routine',NULL,NULL,1,'2026-09-18 05:07:19'),(3,'ENC-2026-0003',2,'OPD',3,2,'2026-09-18 15:54:09',NULL,'In-Progress','Routine','tuborclosis','',2,'2026-09-18 10:54:09');
/*!40000 ALTER TABLE `encounters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hospital_profiles`
--

DROP TABLE IF EXISTS `hospital_profiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hospital_profiles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `hospital_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tagline` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'Excellence in Patient Care & Clinical Service',
  `license_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'Pakistan',
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `emergency_hotline` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `currency_symbol` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT 'PKR ',
  `default_tax_rate` decimal(5,2) DEFAULT 0.00,
  `time_zone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Asia/Karachi',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hospital_profiles`
--

LOCK TABLES `hospital_profiles` WRITE;
/*!40000 ALTER TABLE `hospital_profiles` DISABLE KEYS */;
INSERT INTO `hospital_profiles` VALUES (1,'Hayat Medicare Teaching Hospital','Compassionate Care, Advanced Clinical Excellence','MED-REG-2026-994','NTN-8839201-4','Sector B-3, Phase 5, Hayatabad','Peshawar','Pakistan','+92 91 5892001','info@hayatmedicare.org','+92 91 1122334','PKR ',0.00,'Asia/Karachi','2026-09-17 14:05:51','2026-09-17 14:05:51');
/*!40000 ALTER TABLE `hospital_profiles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `insurance_claims`
--

DROP TABLE IF EXISTS `insurance_claims`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `insurance_claims` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `claim_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bill_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `policy_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `pre_auth_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `claim_amount` decimal(12,2) NOT NULL,
  `approved_amount` decimal(12,2) DEFAULT 0.00,
  `patient_copay_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('Draft','Submitted','Approved','Partially_Approved','Rejected','Settled') COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `remarks` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `submitted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `claim_number` (`claim_number`),
  KEY `bill_id` (`bill_id`),
  KEY `patient_id` (`patient_id`),
  KEY `company_id` (`company_id`),
  CONSTRAINT `insurance_claims_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `insurance_claims_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `insurance_claims_ibfk_3` FOREIGN KEY (`company_id`) REFERENCES `insurance_companies` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `insurance_claims`
--

LOCK TABLES `insurance_claims` WRITE;
/*!40000 ALTER TABLE `insurance_claims` DISABLE KEYS */;
/*!40000 ALTER TABLE `insurance_claims` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `insurance_companies`
--

DROP TABLE IF EXISTS `insurance_companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `insurance_companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credit_period_days` int(11) DEFAULT 30,
  `status` enum('Active','Suspended') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `company_code` (`company_code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `insurance_companies`
--

LOCK TABLES `insurance_companies` WRITE;
/*!40000 ALTER TABLE `insurance_companies` DISABLE KEYS */;
INSERT INTO `insurance_companies` VALUES (1,'State Life Health TPA','SLIC-HEALTH','Tariq Javed','+92 51 9201122','claims@statelife.com.pk',45,'Active'),(2,'Jubilee Life Healthcare','JUBILEE-INS','Saima Rizvi','+92 21 111111555','healthclaims@jubileelife.com',30,'Active'),(3,'Pak Qatar Takaful','PQ-TAKAFUL','Farhan Siddiqui','+92 91 5889900','corporate@pakqatar.com.pk',30,'Active');
/*!40000 ALTER TABLE `insurance_companies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_categories`
--

DROP TABLE IF EXISTS `inventory_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_categories`
--

LOCK TABLES `inventory_categories` WRITE;
/*!40000 ALTER TABLE `inventory_categories` DISABLE KEYS */;
INSERT INTO `inventory_categories` VALUES (1,'Surgical Consumables & Sutures','SURG-CONS'),(2,'Sterile PPE & Disposables','PPE-DISP'),(3,'Biomedical Instruments & Sensors','BIOMED');
/*!40000 ALTER TABLE `inventory_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventory_items`
--

DROP TABLE IF EXISTS `inventory_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `item_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `generic_name` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dosage_form` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'General',
  `is_narcotic` tinyint(1) DEFAULT 0,
  `narcotic_schedule` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `storage_condition` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Room Temperature',
  `batch_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `item_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit_of_measure` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT 'Box / Pack',
  `current_stock` int(11) NOT NULL DEFAULT 0,
  `reorder_level` int(11) NOT NULL DEFAULT 20,
  `unit_cost_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `unit_sale_price` decimal(10,2) DEFAULT 0.00,
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_code` (`item_code`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `inventory_items_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventory_items`
--

LOCK TABLES `inventory_items` WRITE;
/*!40000 ALTER TABLE `inventory_items` DISABLE KEYS */;
INSERT INTO `inventory_items` VALUES (1,1,'Sterile Surgical Gloves (Size 7.5)',NULL,'General',0,NULL,'Room Temperature',NULL,NULL,'INV-GLV-75','Box of 50 pairs',100,30,850.00,0.00,'Active'),(2,1,'Vicryl 3-0 Absorbable Suture Pack',NULL,'General',0,NULL,'Room Temperature',NULL,NULL,'INV-SUT-V30','Box of 12',45,15,3200.00,0.00,'Active'),(3,2,'Disposable N95 Surgical Masks',NULL,'General',0,NULL,'Room Temperature',NULL,NULL,'INV-N95-MSK','Box of 20',80,25,600.00,0.00,'Active'),(4,2,'IV Cannula 20G (Pink)',NULL,'General',0,NULL,'Room Temperature',NULL,NULL,'INV-CAN-20G','Box of 100',95,20,1400.00,0.00,'Active'),(5,1,'Morphine Inj 10mg/ml','Morphine Sulphate','Injection',1,'Schedule II (Opioid)','Narcotics Double-Lock Safe','BT-MORPH-2026A','2028-03-18','MED-MORPHI-681','Ampoule',50,20,180.00,260.00,'Active'),(6,1,'Fentanyl Inj 50mcg/ml','Fentanyl Citrate','Injection',1,'Schedule II (Opioid)','Narcotics Double-Lock Safe','BT-FENT-2026B','2027-11-18','MED-FENTAN-433','Ampoule',40,15,320.00,450.00,'Active'),(7,1,'Valium (Diazepam) Inj 10mg','Diazepam','Injection',1,'Schedule IV (Sedative)','Controlled Cabinet (15-25°C)','BT-VAL-8821','2027-06-18','MED-VALIUM-584','Ampoule',80,25,95.00,140.00,'Active'),(8,1,'Midazolam Inj 5mg/ml','Midazolam HCl','Injection',1,'Schedule IV (Sedative/Pre-op)','Controlled Cabinet','BT-MID-4401','2027-08-18','MED-MIDAZO-800','Ampoule',60,20,145.00,210.00,'Active'),(9,1,'Tramal (Tramadol) 50mg','Tramadol Hydrochloride','Capsule',1,'Schedule IV (Opioid)','Room Temperature','BT-TRM-9912','2027-12-18','MED-TRAMAL-674','Strip of 10',120,30,180.00,250.00,'Active'),(10,1,'Augmentin 625mg Tab','Amoxicillin + Clavulanic Acid','Tablet',0,'Prescription Only','Dry Cool Place (<25°C)','BT-AUG-EXP-NEAR','2026-10-23','MED-AUGMEN-616','Strip of 10',45,50,260.00,320.00,'Active'),(11,1,'Rocephin (Ceftriaxone) 1g IV','Ceftriaxone Sodium','Injection',0,'Prescription Only','Cool Room Temp (<25°C)','BT-ROCE-EXP-NEAR','2026-11-05','MED-ROCEPH-682','Vial',35,40,380.00,490.00,'Active'),(12,1,'Dicloran 75mg Inj','Diclofenac Sodium','Injection',0,'Prescription Only','Quarantine Shelf','BT-DIC-EXPIRED','2026-08-31','MED-DICLOR-616','Ampoule',15,20,35.00,60.00,'Active'),(13,1,'Brufen Suspension 120ml','Ibuprofen','Syrup',0,'OTC','Room Temperature','BT-BRU-5521','2028-05-18','MED-BRUFEN-208','Bottle',90,40,85.00,120.00,'Active'),(14,1,'Normal Saline 0.9% 1000ml','Sodium Chloride 0.9%','Infusion',0,'Hospital IV Formulation','Room Temperature','BT-NS-1000-88','2028-09-18','MED-NORMAL-706','IV Bottle',250,100,120.00,190.00,'Active'),(15,1,'Ringer Lactate (Hartmann) 1000ml','Compound Sodium Lactate','Infusion',0,'Hospital IV Formulation','Room Temperature','BT-RL-1000-77','2028-09-18','MED-RINGER-163','IV Bottle',180,80,140.00,220.00,'Active'),(16,1,'Ventolin Evohaler 100mcg','Salbutamol Sulphate','Inhaler',0,'Prescription Only','Below 30°C Protect from frost','BT-VEN-3012','2028-01-18','MED-VENTOL-192','Inhaler Device',65,30,450.00,620.00,'Active'),(17,1,'Adrenaline 1:1000 Inj','Epinephrine 1mg/ml','Injection',0,'Crash Cart Life-Saving','Protect from Light (2-8°C or <25°C)','BT-ADR-9021','2027-11-18','MED-ADRENA-830','Ampoule',100,30,85.00,130.00,'Active');
/*!40000 ALTER TABLE `inventory_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_categories`
--

DROP TABLE IF EXISTS `lab_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `category_name` (`category_name`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_categories`
--

LOCK TABLES `lab_categories` WRITE;
/*!40000 ALTER TABLE `lab_categories` DISABLE KEYS */;
INSERT INTO `lab_categories` VALUES (1,'Clinical Hematology','HEM'),(2,'Clinical Biochemistry','BIO'),(3,'Chemical Pathology & Urine','CP'),(4,'Serology & Immunology','SERO');
/*!40000 ALTER TABLE `lab_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_order_items`
--

DROP TABLE IF EXISTS `lab_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lab_order_id` int(11) NOT NULL,
  `lab_test_id` int(11) NOT NULL,
  `sample_barcode` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sample_collected_at` datetime DEFAULT NULL,
  `collected_by` int(11) DEFAULT NULL,
  `item_status` enum('Pending','Collected','Tested','Verified') COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  PRIMARY KEY (`id`),
  KEY `lab_order_id` (`lab_order_id`),
  KEY `lab_test_id` (`lab_test_id`),
  KEY `collected_by` (`collected_by`),
  CONSTRAINT `lab_order_items_ibfk_1` FOREIGN KEY (`lab_order_id`) REFERENCES `lab_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_order_items_ibfk_2` FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests` (`id`),
  CONSTRAINT `lab_order_items_ibfk_3` FOREIGN KEY (`collected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_order_items`
--

LOCK TABLES `lab_order_items` WRITE;
/*!40000 ALTER TABLE `lab_order_items` DISABLE KEYS */;
INSERT INTO `lab_order_items` VALUES (1,1,1,'BC-2026-47176','2026-09-18 05:27:02',1,'Verified'),(2,1,2,NULL,NULL,NULL,'Pending'),(3,2,1,'BC-2026-28108','2026-09-18 10:07:19',1,'Verified'),(4,2,2,NULL,NULL,NULL,'Pending'),(5,3,1,NULL,NULL,NULL,'Pending'),(6,3,2,NULL,NULL,NULL,'Pending'),(7,3,7,NULL,NULL,NULL,'Pending'),(8,3,5,NULL,NULL,NULL,'Pending'),(9,3,4,NULL,NULL,NULL,'Pending'),(10,3,3,NULL,NULL,NULL,'Pending'),(11,3,6,NULL,NULL,NULL,'Pending');
/*!40000 ALTER TABLE `lab_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_orders`
--

DROP TABLE IF EXISTS `lab_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `encounter_id` int(11) NOT NULL,
  `ordered_by` int(11) NOT NULL,
  `order_datetime` datetime NOT NULL,
  `priority` enum('Routine','Urgent','STAT') COLLATE utf8mb4_unicode_ci DEFAULT 'Routine',
  `clinical_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Ordered','Sample_Collected','Processing','Completed','Cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'Ordered',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `encounter_id` (`encounter_id`),
  KEY `ordered_by` (`ordered_by`),
  CONSTRAINT `lab_orders_ibfk_1` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_orders_ibfk_2` FOREIGN KEY (`ordered_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_orders`
--

LOCK TABLES `lab_orders` WRITE;
/*!40000 ALTER TABLE `lab_orders` DISABLE KEYS */;
INSERT INTO `lab_orders` VALUES (1,'LAB-2026-0001',1,2,'2026-09-18 05:27:02','Routine',NULL,'Ordered'),(2,'LAB-2026-0002',2,2,'2026-09-18 10:07:19','Routine',NULL,'Ordered'),(3,'LAB-2026-0003',3,2,'2026-09-18 17:17:43','Routine',NULL,'Ordered');
/*!40000 ALTER TABLE `lab_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_results`
--

DROP TABLE IF EXISTS `lab_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lab_order_item_id` int(11) NOT NULL,
  `parameter_id` int(11) NOT NULL,
  `result_value` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abnormal_flag` enum('Normal','Low','High','Critical_Low','Critical_High') COLLATE utf8mb4_unicode_ci DEFAULT 'Normal',
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tested_by` int(11) NOT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lab_order_item_id` (`lab_order_item_id`),
  KEY `parameter_id` (`parameter_id`),
  KEY `tested_by` (`tested_by`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `lab_results_ibfk_1` FOREIGN KEY (`lab_order_item_id`) REFERENCES `lab_order_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lab_results_ibfk_2` FOREIGN KEY (`parameter_id`) REFERENCES `lab_test_parameters` (`id`),
  CONSTRAINT `lab_results_ibfk_3` FOREIGN KEY (`tested_by`) REFERENCES `users` (`id`),
  CONSTRAINT `lab_results_ibfk_4` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_results`
--

LOCK TABLES `lab_results` WRITE;
/*!40000 ALTER TABLE `lab_results` DISABLE KEYS */;
INSERT INTO `lab_results` VALUES (1,1,1,'14.2','Normal',NULL,1,1,'2026-09-18 05:27:02'),(2,1,2,'7500','Normal',NULL,1,1,'2026-09-18 05:27:02'),(3,1,3,'245000','Normal',NULL,1,1,'2026-09-18 05:27:02'),(4,1,4,'245000','Normal',NULL,1,1,'2026-09-18 05:27:02'),(5,3,1,'14.2','Normal',NULL,1,1,'2026-09-18 10:07:19'),(6,3,2,'7500','Normal',NULL,1,1,'2026-09-18 10:07:19'),(7,3,3,'245000','Normal',NULL,1,1,'2026-09-18 10:07:19'),(8,3,4,'245000','Normal',NULL,1,1,'2026-09-18 10:07:19');
/*!40000 ALTER TABLE `lab_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_test_parameters`
--

DROP TABLE IF EXISTS `lab_test_parameters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_test_parameters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lab_test_id` int(11) NOT NULL,
  `parameter_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unit` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_range` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `normal_min` decimal(8,2) DEFAULT NULL,
  `normal_max` decimal(8,2) DEFAULT NULL,
  `critical_low` decimal(8,2) DEFAULT NULL,
  `critical_high` decimal(8,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lab_test_id` (`lab_test_id`),
  CONSTRAINT `lab_test_parameters_ibfk_1` FOREIGN KEY (`lab_test_id`) REFERENCES `lab_tests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_test_parameters`
--

LOCK TABLES `lab_test_parameters` WRITE;
/*!40000 ALTER TABLE `lab_test_parameters` DISABLE KEYS */;
INSERT INTO `lab_test_parameters` VALUES (1,1,'Hemoglobin (Hb)','g/dL','13.0 - 17.0 (M) / 12.0 - 15.5 (F)',12.00,17.00,7.00,20.00),(2,1,'Total Leukocyte Count (TLC)','x10^3/uL','4.0 - 11.0',4.00,11.00,2.00,30.00),(3,1,'Platelet Count','x10^3/uL','150 - 450',150.00,450.00,50.00,1000.00),(4,1,'Erythrocyte Sedimentation Rate (ESR)','mm/1st hr','0 - 15',0.00,15.00,0.00,80.00),(5,2,'Fasting Plasma Glucose','mg/dL','70 - 100 (Normal), 100 - 125 (Pre-diabetic)',70.00,100.00,45.00,400.00),(6,3,'Serum Creatinine','mg/dL','0.6 - 1.2',0.60,1.20,0.40,5.00),(7,3,'Blood Urea','mg/dL','15 - 45',15.00,45.00,10.00,120.00),(8,4,'Serum Bilirubin (Total)','mg/dL','0.2 - 1.2',0.20,1.20,0.10,8.00),(9,4,'ALT (SGPT)','U/L','10 - 45',10.00,45.00,5.00,300.00),(10,5,'Total Serum Cholesterol','mg/dL','Desirable < 200',120.00,200.00,80.00,350.00);
/*!40000 ALTER TABLE `lab_test_parameters` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_tests`
--

DROP TABLE IF EXISTS `lab_tests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lab_tests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `test_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `test_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specimen_type` enum('Blood','Serum','Plasma','Urine','Stool','CSF','Sputum','Biopsy_Tissue','Swab') COLLATE utf8mb4_unicode_ci DEFAULT 'Blood',
  `standard_price` decimal(10,2) NOT NULL DEFAULT 500.00,
  `turnaround_hours` int(11) DEFAULT 12,
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `test_code` (`test_code`),
  KEY `category_id` (`category_id`),
  CONSTRAINT `lab_tests_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `lab_categories` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_tests`
--

LOCK TABLES `lab_tests` WRITE;
/*!40000 ALTER TABLE `lab_tests` DISABLE KEYS */;
INSERT INTO `lab_tests` VALUES (1,1,'Complete Blood Count (CBC with ESR)','CBC-01','Blood',850.00,4,'Active'),(2,2,'Fasting Blood Glucose (FBG)','FBG-01','Plasma',350.00,2,'Active'),(3,2,'Renal Function Test (Urea, Creatinine)','RFT-01','Serum',1100.00,6,'Active'),(4,2,'Liver Function Test (LFT Profile)','LFT-01','Serum',1400.00,6,'Active'),(5,2,'Lipid Profile (Cholesterol, HDL, LDL, Triglycerides)','LIP-01','Serum',1600.00,8,'Active'),(6,3,'Urine Routine Examination (Urine R/E)','URE-01','Urine',450.00,2,'Active'),(7,4,'Hepatitis B Surface Antigen (HBsAg Rapid)','HBS-01','Serum',750.00,2,'Active');
/*!40000 ALTER TABLE `lab_tests` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `nursing_notes`
--

DROP TABLE IF EXISTS `nursing_notes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nursing_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `encounter_id` int(11) NOT NULL,
  `nurse_id` int(11) NOT NULL,
  `note_type` enum('Routine_Care','Shift_Handover','Doctor_Order_Executed','Incident_Report','Pain_Assessment') COLLATE utf8mb4_unicode_ci DEFAULT 'Routine_Care',
  `content` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `encounter_id` (`encounter_id`),
  KEY `nurse_id` (`nurse_id`),
  CONSTRAINT `nursing_notes_ibfk_1` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `nursing_notes_ibfk_2` FOREIGN KEY (`nurse_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nursing_notes`
--

LOCK TABLES `nursing_notes` WRITE;
/*!40000 ALTER TABLE `nursing_notes` DISABLE KEYS */;
/*!40000 ALTER TABLE `nursing_notes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ot_rooms`
--

DROP TABLE IF EXISTS `ot_rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ot_rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `room_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `theatre_type` enum('Major_General','Laparoscopy_Endo','Orthopedic_Trauma','Cardiac_OT','Obstetric_Labor') COLLATE utf8mb4_unicode_ci DEFAULT 'Major_General',
  `status` enum('Available','In_Surgery','Sterilization','Maintenance') COLLATE utf8mb4_unicode_ci DEFAULT 'Available',
  PRIMARY KEY (`id`),
  UNIQUE KEY `room_number` (`room_number`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ot_rooms`
--

LOCK TABLES `ot_rooms` WRITE;
/*!40000 ALTER TABLE `ot_rooms` DISABLE KEYS */;
INSERT INTO `ot_rooms` VALUES (1,'General & Laparoscopic OT Suite 1','OT-01','Laparoscopy_Endo','Available'),(2,'Orthopedic & Trauma Laminar Flow OT','OT-02','Orthopedic_Trauma','Available'),(3,'Emergency & Acute Surgical Suite','OT-03','Major_General','Available');
/*!40000 ALTER TABLE `ot_rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patient_allergies`
--

DROP TABLE IF EXISTS `patient_allergies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patient_allergies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(11) NOT NULL,
  `allergy_type` enum('Drug','Food','Environmental','Other') COLLATE utf8mb4_unicode_ci DEFAULT 'Drug',
  `allergen_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `severity` enum('Mild','Moderate','Severe','Life-Threatening') COLLATE utf8mb4_unicode_ci DEFAULT 'Moderate',
  `reaction_details` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `recorded_by` (`recorded_by`),
  CONSTRAINT `patient_allergies_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patient_allergies_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient_allergies`
--

LOCK TABLES `patient_allergies` WRITE;
/*!40000 ALTER TABLE `patient_allergies` DISABLE KEYS */;
/*!40000 ALTER TABLE `patient_allergies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patients`
--

DROP TABLE IF EXISTS `patients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `patients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mrn` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` enum('Male','Female','Other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `dob` date DEFAULT NULL,
  `age` int(11) DEFAULT NULL,
  `blood_group` enum('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') COLLATE utf8mb4_unicode_ci DEFAULT 'Unknown',
  `marital_status` enum('Single','Married','Divorced','Widowed','Other') COLLATE utf8mb4_unicode_ci DEFAULT 'Single',
  `national_id` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone_primary` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone_secondary` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT 'Peshawar',
  `emergency_contact_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_relation` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `emergency_contact_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `registered_by` int(11) DEFAULT NULL,
  `status` enum('Active','Deceased','Archived') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `mrn` (`mrn`),
  KEY `idx_mrn` (`mrn`),
  KEY `idx_phone` (`phone_primary`),
  KEY `idx_national_id` (`national_id`),
  KEY `idx_name` (`first_name`,`last_name`),
  KEY `registered_by` (`registered_by`),
  CONSTRAINT `patients_ibfk_1` FOREIGN KEY (`registered_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patients`
--

LOCK TABLES `patients` WRITE;
/*!40000 ALTER TABLE `patients` DISABLE KEYS */;
INSERT INTO `patients` VALUES (1,'MRN-2026-0001','Arshad','Mehmood','Male','1988-04-12',38,'B+','Married','17301-1222988-1','03001222988',NULL,NULL,'University Town Sector B','Peshawar',NULL,NULL,NULL,1,'Active','2026-09-18 00:27:02','2026-09-18 00:27:02'),(2,'MRN-2026-0002','Arshad','Mehmood','Male','1988-04-12',38,'B+','Married','17301-8039893-1','03008039893',NULL,NULL,'University Town Sector B','Peshawar',NULL,NULL,NULL,1,'Active','2026-09-18 05:07:19','2026-09-18 05:07:19');
/*!40000 ALTER TABLE `patients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receipt_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `bill_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_method` enum('Cash','Credit_Card','Debit_Card','Bank_Transfer','Online') COLLATE utf8mb4_unicode_ci DEFAULT 'Cash',
  `transaction_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_datetime` datetime NOT NULL,
  `received_by` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `receipt_number` (`receipt_number`),
  KEY `bill_id` (`bill_id`),
  KEY `patient_id` (`patient_id`),
  KEY `received_by` (`received_by`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`bill_id`) REFERENCES `bills` (`id`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `payments_ibfk_3` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
INSERT INTO `payments` VALUES (1,'RCPT-2026-0001',1,1,4204.00,'Cash','CASHIER-REGISTER-1','2026-09-18 05:27:02',1),(2,'RCPT-2026-0002',2,2,4204.00,'Cash','CASHIER-REGISTER-1','2026-09-18 10:07:19',1);
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `module` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_key` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_key` (`permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `permissions`
--

LOCK TABLES `permissions` WRITE;
/*!40000 ALTER TABLE `permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pharmacy_sale_items`
--

DROP TABLE IF EXISTS `pharmacy_sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pharmacy_sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pharmacy_sale_id` int(11) NOT NULL,
  `drug_batch_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `dosage_instructions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pharmacy_sale_id` (`pharmacy_sale_id`),
  KEY `drug_batch_id` (`drug_batch_id`),
  CONSTRAINT `pharmacy_sale_items_ibfk_1` FOREIGN KEY (`pharmacy_sale_id`) REFERENCES `pharmacy_sales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pharmacy_sale_items_ibfk_2` FOREIGN KEY (`drug_batch_id`) REFERENCES `drug_batches` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pharmacy_sale_items`
--

LOCK TABLES `pharmacy_sale_items` WRITE;
/*!40000 ALTER TABLE `pharmacy_sale_items` DISABLE KEYS */;
INSERT INTO `pharmacy_sale_items` VALUES (1,1,3,14,36.00,504.00,NULL),(2,2,21,2,130.00,260.00,NULL),(3,3,21,2,130.00,260.00,NULL),(4,4,21,2,130.00,260.00,NULL),(5,5,21,2,130.00,260.00,NULL),(6,6,21,2,130.00,260.00,NULL),(7,7,21,2,130.00,260.00,NULL),(8,8,2,10,52.00,520.00,'1 ?????? 1 ?????? (?????????? ???? ??????)'),(9,8,1,10,3.50,35.00,'1 ???????? ???? ?????? 3 ?????? ???? ???????? ??????'),(10,8,3,7,36.00,252.00,'1 ???????????? ?????? ???????? ??????'),(11,9,5,30,18.00,540.00,'1 ???????? ???????????? ?????? ?????????? ???? ??????'),(12,9,4,30,3.00,90.00,'1 ???????? ???????????? ?????????? ???? ?????????? ???? ??????'),(13,10,21,2,130.00,260.00,NULL),(14,11,2,10,52.00,520.00,'1 ?????? 1 ?????? (?????????? ???? ??????)'),(15,11,1,10,3.50,35.00,'1 ???????? ???? ?????? 3 ?????? ???? ???????? ??????'),(16,11,3,7,36.00,252.00,'1 ???????????? ?????? ???????? ??????');
/*!40000 ALTER TABLE `pharmacy_sale_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pharmacy_sales`
--

DROP TABLE IF EXISTS `pharmacy_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pharmacy_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_invoice_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prescription_id` int(11) DEFAULT NULL,
  `encounter_id` int(11) DEFAULT NULL,
  `patient_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `tax_amount` decimal(10,2) DEFAULT 0.00,
  `net_total` decimal(10,2) NOT NULL,
  `amount_tendered` decimal(10,2) DEFAULT 0.00,
  `change_amount` decimal(10,2) DEFAULT 0.00,
  `sale_notes` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_method` enum('Cash','Card','Bill_to_Encounter','Bank_Transfer','Mobile_Wallet') COLLATE utf8mb4_unicode_ci DEFAULT 'Cash',
  `payment_reference` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dispensed_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_invoice_number` (`sale_invoice_number`),
  KEY `prescription_id` (`prescription_id`),
  KEY `encounter_id` (`encounter_id`),
  KEY `patient_id` (`patient_id`),
  KEY `dispensed_by` (`dispensed_by`),
  CONSTRAINT `pharmacy_sales_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pharmacy_sales_ibfk_2` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pharmacy_sales_ibfk_3` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `pharmacy_sales_ibfk_4` FOREIGN KEY (`dispensed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pharmacy_sales`
--

LOCK TABLES `pharmacy_sales` WRITE;
/*!40000 ALTER TABLE `pharmacy_sales` DISABLE KEYS */;
INSERT INTO `pharmacy_sales` VALUES (1,'PH-INV-20260918-0001',1,1,1,'Arshad Mehmood',NULL,504.00,0.00,0.00,504.00,0.00,0.00,NULL,'Bill_to_Encounter',NULL,1,'2026-09-18 08:31:08'),(2,'POS-20260918-0002',NULL,NULL,NULL,'Walk-in Customer (Test)','0300-5551234',260.00,5.00,0.00,255.00,300.00,45.00,'Test Walk-in POS','Cash',NULL,1,'2026-09-18 10:10:50'),(3,'POS-20260918-0003',NULL,NULL,NULL,'Walk-in Customer (Test)','0300-5551234',260.00,5.00,0.00,255.00,300.00,45.00,'Test Walk-in POS','Cash',NULL,1,'2026-09-18 10:11:21'),(4,'POS-20260918-0004',NULL,NULL,NULL,'Walk-in Customer (Test)','0300-5551234',260.00,5.00,0.00,255.00,300.00,45.00,'Test Walk-in POS','Cash',NULL,1,'2026-09-18 10:11:45'),(5,'POS-20260918-0005',NULL,NULL,NULL,'Walk-in Customer (Test)','0300-5551234',260.00,5.00,0.00,255.00,300.00,45.00,'Test Walk-in POS','Cash',NULL,1,'2026-09-18 10:12:33'),(6,'POS-20260918-0006',NULL,NULL,NULL,'Walk-in Customer (Test)','0300-5551234',260.00,5.00,0.00,255.00,300.00,45.00,'Test Walk-in POS','Cash',NULL,1,'2026-09-18 10:13:42'),(7,'POS-20260918-0007',NULL,NULL,NULL,'Walk-in Customer (Test)','0300-5551234',260.00,5.00,0.00,255.00,300.00,45.00,'Test Walk-in POS','Cash',NULL,1,'2026-09-18 10:31:31'),(8,'POS-20260918-0008',3,NULL,NULL,'Arshad Mehmood',NULL,807.00,0.00,0.00,807.00,807.00,0.00,NULL,'Cash',NULL,1,'2026-09-18 10:32:11'),(9,'POS-20260918-0009',4,NULL,NULL,'Arshad Mehmood',NULL,630.00,0.00,0.00,630.00,630.00,0.00,NULL,'Cash',NULL,1,'2026-09-18 10:32:36'),(10,'POS-20260918-0010',NULL,NULL,NULL,'Walk-in Customer (Test)','0300-5551234',260.00,5.00,0.00,255.00,300.00,45.00,'Test Walk-in POS','Cash',NULL,1,'2026-09-18 10:44:59'),(11,'POS-20260918-0011',3,NULL,NULL,'Arshad Mehmood',NULL,807.00,0.00,0.00,807.00,807.00,0.00,NULL,'Cash',NULL,1,'2026-09-18 10:45:40');
/*!40000 ALTER TABLE `pharmacy_sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescription_items`
--

DROP TABLE IF EXISTS `prescription_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prescription_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_id` int(11) NOT NULL,
  `drug_item_id` int(11) NOT NULL,
  `dosage` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `frequency` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `duration_days` int(11) NOT NULL DEFAULT 5,
  `route` enum('Oral','IV','IM','SC','Topical','Sublingual','Inhalation') COLLATE utf8mb4_unicode_ci DEFAULT 'Oral',
  `instructions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT 'After meals',
  `quantity_prescribed` int(11) NOT NULL,
  `quantity_dispensed` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `prescription_id` (`prescription_id`),
  KEY `drug_item_id` (`drug_item_id`),
  CONSTRAINT `prescription_items_ibfk_1` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescription_items_ibfk_2` FOREIGN KEY (`drug_item_id`) REFERENCES `drug_items` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescription_items`
--

LOCK TABLES `prescription_items` WRITE;
/*!40000 ALTER TABLE `prescription_items` DISABLE KEYS */;
INSERT INTO `prescription_items` VALUES (1,1,3,'1 Cap (40mg)','1-0-0 (OD Morning)',14,'Oral','Before breakfast',14,14),(2,2,3,'1 Cap (40mg)','1-0-0 (OD Morning)',14,'Oral','Before breakfast',14,14),(3,3,2,'625mg','Twice Daily (BID)',5,'Oral','1 ?????? 1 ?????? (?????????? ???? ??????)',10,0),(4,3,1,'500mg','Thrice Daily (TID)',3,'Oral','1 ???????? ???? ?????? 3 ?????? ???? ???????? ??????',10,0),(5,3,3,'40mg','Once Daily (OD)',7,'Oral','1 ???????????? ?????? ???????? ??????',7,0),(6,4,5,'5mg','Once Daily (OD)',30,'Oral','1 ???????? ???????????? ?????? ?????????? ???? ??????',30,0),(7,4,4,'75mg','Once Daily (OD)',30,'Oral','1 ???????? ???????????? ?????????? ???? ?????????? ???? ??????',30,0);
/*!40000 ALTER TABLE `prescription_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescriptions`
--

DROP TABLE IF EXISTS `prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `prescription_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `encounter_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `status` enum('Pending','Partially-Dispensed','Dispensed','Cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'Pending',
  `clinical_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `prescription_number` (`prescription_number`),
  KEY `encounter_id` (`encounter_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescriptions`
--

LOCK TABLES `prescriptions` WRITE;
/*!40000 ALTER TABLE `prescriptions` DISABLE KEYS */;
INSERT INTO `prescriptions` VALUES (1,'RX-2026-0001',1,2,'Dispensed','Take medication before breakfast','2026-09-18 00:27:02'),(2,'RX-2026-0002',2,2,'Dispensed','Take medication before breakfast','2026-09-18 05:07:19'),(3,'RX-2026-0003',1,2,'Pending','Acute Pharyngitis & Fever: Prescribed oral antibiotic, analgesic, and PPI.','2026-09-18 10:23:03'),(4,'RX-2026-0004',2,2,'Pending','Hypertension & Gastritis: Routine OPD follow-up for BP control and acid peptic symptoms.','2026-09-18 10:23:03');
/*!40000 ALTER TABLE `prescriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_order_items`
--

DROP TABLE IF EXISTS `purchase_order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_id` int(11) NOT NULL,
  `item_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `po_id` (`po_id`),
  CONSTRAINT `purchase_order_items_ibfk_1` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_order_items`
--

LOCK TABLES `purchase_order_items` WRITE;
/*!40000 ALTER TABLE `purchase_order_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_order_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `purchase_orders`
--

DROP TABLE IF EXISTS `purchase_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `po_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery_date` date DEFAULT NULL,
  `total_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `status` enum('Draft','Approved','Received','Cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `po_number` (`po_number`),
  KEY `supplier_id` (`supplier_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `purchase_orders_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  CONSTRAINT `purchase_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `purchase_orders`
--

LOCK TABLES `purchase_orders` WRITE;
/*!40000 ALTER TABLE `purchase_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `purchase_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `radiology_orders`
--

DROP TABLE IF EXISTS `radiology_orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radiology_orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `encounter_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `ordered_by` int(11) NOT NULL,
  `order_datetime` datetime NOT NULL,
  `priority` enum('Routine','Urgent','STAT') COLLATE utf8mb4_unicode_ci DEFAULT 'Routine',
  `clinical_indication` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Ordered','Scheduled','Exam_Completed','Reported','Cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'Ordered',
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_number` (`order_number`),
  KEY `encounter_id` (`encounter_id`),
  KEY `service_id` (`service_id`),
  KEY `ordered_by` (`ordered_by`),
  CONSTRAINT `radiology_orders_ibfk_1` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `radiology_orders_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `radiology_services` (`id`),
  CONSTRAINT `radiology_orders_ibfk_3` FOREIGN KEY (`ordered_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `radiology_orders`
--

LOCK TABLES `radiology_orders` WRITE;
/*!40000 ALTER TABLE `radiology_orders` DISABLE KEYS */;
/*!40000 ALTER TABLE `radiology_orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `radiology_reports`
--

DROP TABLE IF EXISTS `radiology_reports`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radiology_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `radiology_order_id` int(11) NOT NULL,
  `radiologist_id` int(11) NOT NULL,
  `radiological_findings` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `impression` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `technique_used` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `report_status` enum('Draft','Finalized') COLLATE utf8mb4_unicode_ci DEFAULT 'Draft',
  `finalized_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `radiology_order_id` (`radiology_order_id`),
  KEY `radiologist_id` (`radiologist_id`),
  CONSTRAINT `radiology_reports_ibfk_1` FOREIGN KEY (`radiology_order_id`) REFERENCES `radiology_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `radiology_reports_ibfk_2` FOREIGN KEY (`radiologist_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `radiology_reports`
--

LOCK TABLES `radiology_reports` WRITE;
/*!40000 ALTER TABLE `radiology_reports` DISABLE KEYS */;
/*!40000 ALTER TABLE `radiology_reports` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `radiology_services`
--

DROP TABLE IF EXISTS `radiology_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `radiology_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `modality` enum('X-Ray','Ultrasound','CT-Scan','MRI','Mammography','Fluoroscopy') COLLATE utf8mb4_unicode_ci NOT NULL,
  `standard_tariff` decimal(10,2) NOT NULL DEFAULT 1500.00,
  `preparation_notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `turnaround_hours` int(11) DEFAULT 12,
  `status` enum('Active','Inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_code` (`service_code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `radiology_services`
--

LOCK TABLES `radiology_services` WRITE;
/*!40000 ALTER TABLE `radiology_services` DISABLE KEYS */;
INSERT INTO `radiology_services` VALUES (1,'Digital Chest X-Ray (PA View)','RAD-CXR','X-Ray',1200.00,'Remove metallic jewelry and shirts with metallic buttons.',4,'Active'),(2,'Abdomen & Pelvis Ultrasound (USG)','RAD-USG-ABD','Ultrasound',2200.00,'Fasting for 6 hours required; full bladder for pelvic scan.',6,'Active'),(3,'Brain CT Scan (Non-Contrast)','RAD-CT-BRN','CT-Scan',6500.00,'No specific prep for non-contrast.',8,'Active'),(4,'Lumbar Spine MRI (L-Spine)','RAD-MRI-LSP','MRI',12500.00,'Check for pacemakers, metallic implants, or foreign bodies.',12,'Active'),(5,'X-Ray Knee Joint (AP & Lateral)','RAD-XR-KN','X-Ray',1400.00,'No preparation required.',4,'Active');
/*!40000 ALTER TABLE `radiology_services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `role_permissions`
--

LOCK TABLES `role_permissions` WRITE;
/*!40000 ALTER TABLE `role_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_system_role` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Super Administrator','super_admin','Full unrestricted access to system configurations, users and audit logs',1,'2026-09-17 14:05:52'),(2,'Hospital Administrator','hospital_admin','Executive management of hospital operations and departments',1,'2026-09-17 14:05:52'),(3,'Consultant Doctor','doctor','Outpatient consultation, clinical documentation, CPOE orders & rounding',1,'2026-09-17 14:05:52'),(4,'Staff Nurse','nurse','Inpatient bed care, vitals charting, nursing notes and MAR',1,'2026-09-17 14:05:52'),(5,'Pharmacist','pharmacist','Prescription review, drug dispensing, batch and inventory management',1,'2026-09-17 14:05:52'),(6,'Laboratory Technician','lab_technician','Specimen intake, analyzer operations and lab result entry',1,'2026-09-17 14:05:52'),(7,'Billing Cashier','cashier','Patient invoice generation, payments, refunds and cash drawer balancing',1,'2026-09-17 14:05:52'),(8,'Front Desk Receptionist','receptionist','Patient registration, appointments booking and token queue check-in',1,'2026-09-17 14:05:52'),(9,'Radiologist','radiologist','Radiology imaging review, PACS DICOM study evaluation and radiology diagnostic reporting',1,'2026-09-18 05:03:50');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rooms`
--

DROP TABLE IF EXISTS `rooms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rooms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ward_id` int(11) NOT NULL,
  `room_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `room_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT 'Standard',
  `daily_rate` decimal(10,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `ward_id` (`ward_id`),
  CONSTRAINT `rooms_ibfk_1` FOREIGN KEY (`ward_id`) REFERENCES `wards` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rooms`
--

LOCK TABLES `rooms` WRITE;
/*!40000 ALTER TABLE `rooms` DISABLE KEYS */;
INSERT INTO `rooms` VALUES (1,1,'Ward-101','General Ward Shared',1500.00),(2,2,'Ward-102','General Ward Shared',1500.00),(3,3,'ICU-Room-01','ICU Negative Pressure',8500.00),(4,4,'Suite-201','VIP Single Deluxe Suite',6000.00),(5,4,'Suite-202','VIP Single Deluxe Suite',6000.00),(6,5,'ER-Triage-A','Acute Trauma Bay',2500.00);
/*!40000 ALTER TABLE `rooms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_roster`
--

DROP TABLE IF EXISTS `staff_roster`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_roster` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `shift_id` int(11) NOT NULL,
  `duty_date` date NOT NULL,
  `department_id` int(11) NOT NULL,
  `status` enum('Scheduled','Present','Absent','On_Leave') COLLATE utf8mb4_unicode_ci DEFAULT 'Scheduled',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `shift_id` (`shift_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `staff_roster_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_roster_ibfk_2` FOREIGN KEY (`shift_id`) REFERENCES `staff_shifts` (`id`),
  CONSTRAINT `staff_roster_ibfk_3` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_roster`
--

LOCK TABLES `staff_roster` WRITE;
/*!40000 ALTER TABLE `staff_roster` DISABLE KEYS */;
/*!40000 ALTER TABLE `staff_roster` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_shifts`
--

DROP TABLE IF EXISTS `staff_shifts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `staff_shifts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `shift_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `description` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_shifts`
--

LOCK TABLES `staff_shifts` WRITE;
/*!40000 ALTER TABLE `staff_shifts` DISABLE KEYS */;
INSERT INTO `staff_shifts` VALUES (1,'Morning Shift','08:00:00','14:00:00','Primary OPD and elective surgery roster'),(2,'Evening Shift','14:00:00','20:00:00','Second round clinical coverage and admissions'),(3,'Night Duty','20:00:00','08:00:00','Overnight critical care and 24/7 ER coverage');
/*!40000 ALTER TABLE `staff_shifts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_person` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ntn_tax_number` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('Active','Blacklisted') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `suppliers`
--

LOCK TABLES `suppliers` WRITE;
/*!40000 ALTER TABLE `suppliers` DISABLE KEYS */;
INSERT INTO `suppliers` VALUES (1,'MedTech Surgical Supplies Ltd.','SUP-MEDTECH','Zubair Shah','+92 91 5271122','orders@medtech.com.pk',NULL,NULL,'Active'),(2,'Siemens Healthcare Pakistan','SUP-SIEMENS','Arsalan Baig','+92 21 34567890','service.pk@siemens-healthineers.com',NULL,NULL,'Active');
/*!40000 ALTER TABLE `suppliers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `surgery_bookings`
--

DROP TABLE IF EXISTS `surgery_bookings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `surgery_bookings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `booking_number` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `encounter_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `ot_room_id` int(11) NOT NULL,
  `lead_surgeon_id` int(11) NOT NULL,
  `anesthesiologist_id` int(11) DEFAULT NULL,
  `procedure_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scheduled_datetime` datetime NOT NULL,
  `estimated_duration_minutes` int(11) DEFAULT 120,
  `anesthesia_type` enum('General','Spinal','Epidural','Local','Sedation') COLLATE utf8mb4_unicode_ci DEFAULT 'General',
  `pre_op_diagnosis` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `booking_status` enum('Scheduled','In-Progress','Completed','Cancelled') COLLATE utf8mb4_unicode_ci DEFAULT 'Scheduled',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `booking_number` (`booking_number`),
  KEY `encounter_id` (`encounter_id`),
  KEY `patient_id` (`patient_id`),
  KEY `ot_room_id` (`ot_room_id`),
  KEY `lead_surgeon_id` (`lead_surgeon_id`),
  KEY `anesthesiologist_id` (`anesthesiologist_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `surgery_bookings_ibfk_1` FOREIGN KEY (`encounter_id`) REFERENCES `encounters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `surgery_bookings_ibfk_2` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`),
  CONSTRAINT `surgery_bookings_ibfk_3` FOREIGN KEY (`ot_room_id`) REFERENCES `ot_rooms` (`id`),
  CONSTRAINT `surgery_bookings_ibfk_4` FOREIGN KEY (`lead_surgeon_id`) REFERENCES `users` (`id`),
  CONSTRAINT `surgery_bookings_ibfk_5` FOREIGN KEY (`anesthesiologist_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `surgery_bookings_ibfk_6` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `surgery_bookings`
--

LOCK TABLES `surgery_bookings` WRITE;
/*!40000 ALTER TABLE `surgery_bookings` DISABLE KEYS */;
/*!40000 ALTER TABLE `surgery_bookings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `surgical_records`
--

DROP TABLE IF EXISTS `surgical_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `surgical_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `surgery_booking_id` int(11) NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime DEFAULT NULL,
  `operative_notes` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `post_op_diagnosis` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `implant_details` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sponge_swab_count_correct` tinyint(1) DEFAULT 1,
  `blood_loss_ml` int(11) DEFAULT 50,
  `post_op_destination` enum('PACU_Recovery','Surgical_ICU','Ward') COLLATE utf8mb4_unicode_ci DEFAULT 'PACU_Recovery',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `surgery_booking_id` (`surgery_booking_id`),
  CONSTRAINT `surgical_records_ibfk_1` FOREIGN KEY (`surgery_booking_id`) REFERENCES `surgery_bookings` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `surgical_records`
--

LOCK TABLES `surgical_records` WRITE;
/*!40000 ALTER TABLE `surgical_records` DISABLE KEYS */;
/*!40000 ALTER TABLE `surgical_records` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tariff_services`
--

DROP TABLE IF EXISTS `tariff_services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tariff_services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `department_id` int(11) DEFAULT NULL,
  `service_name` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `service_code` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `category` enum('Consultation','Nursing','Bed_Charge','Procedure','Equipment','Emergency','Other') COLLATE utf8mb4_unicode_ci NOT NULL,
  `standard_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `service_code` (`service_code`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `tariff_services_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tariff_services`
--

LOCK TABLES `tariff_services` WRITE;
/*!40000 ALTER TABLE `tariff_services` DISABLE KEYS */;
INSERT INTO `tariff_services` VALUES (1,2,'General Physician Consultation','CONS-GP','Consultation',1500.00,1),(2,3,'Specialist Cardiology Consultation','CONS-CARD','Consultation',2500.00,1),(3,4,'Pediatric Specialist Consultation','CONS-PED','Consultation',1800.00,1),(4,7,'Emergency Trauma Registration & Triage','ER-TRIAGE','Emergency',1000.00,1),(5,3,'12-Lead Electrocardiogram (ECG)','DIAG-ECG','Procedure',800.00,1),(6,10,'General Ward Daily Bed & Nursing Care','BED-GEN','Bed_Charge',1500.00,1),(7,10,'ICU Ventilated Bed & Critical Nursing Care','BED-ICU','Bed_Charge',8500.00,1),(8,10,'VIP Private Deluxe Suite Daily Tariff','BED-PVT','Bed_Charge',6000.00,1),(9,5,'Wound Debridement & Minor Surgical Dressing','PROC-DRESS','Procedure',1200.00,1),(10,7,'Nebulization Session','PROC-NEB','Procedure',400.00,1);
/*!40000 ALTER TABLE `tariff_services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role_id` int(11) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `designation` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qualification` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `status` enum('Active','Inactive','Suspended') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  `last_login_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  KEY `department_id` (`department_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `users_ibfk_2` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'superadmin','director.arshad@hayatmedicare.org','$2y$10$Sj6N2KVy8/Z4oDRP2ovsWeL3ZHmMLXbagZtSCbhx.Cnm.GTz1K.H2','Dr. Arshad Khan','+92 300 1110001',1,1,'Executive Hospital Director & CEO','MS Software Systems',0.00,'Active','2026-10-29 16:40:21','2026-09-17 14:05:52','2026-10-29 11:40:21'),(2,'dr.tariq','tariq.opd@hayatmedicare.org','$2y$10$ZMvwiEE.DYK3SK.aQlZEUuSyXJMXDmUsi2koiwvouXcrQtZI/J0Ui','Dr. Tariq Jameel','+92 300 7770001',3,3,'Chief of OPD & Consultant Cardiologist','MBBS, FCPS (Cardiology), FACC',2000.00,'Active','2026-09-18 18:38:34','2026-09-17 14:05:52','2026-09-18 13:38:34'),(3,'dr.ayesha','dr.ayesha@hayatmedicare.org','$2y$10$aUMC.pYhQA60ztp/Lt4VvOhGCn54gafX5zFg5rHtxcavhX8xRtAtG','Dr. Ayesha Rehman','+92 321 5551234',3,2,'Consultant Physician','MBBS, MRCP (UK)',2000.00,'Active',NULL,'2026-09-17 14:05:52','2026-09-17 14:05:52'),(4,'dr.bilal','dr.bilal@hayatmedicare.org','$2y$10$aUMC.pYhQA60ztp/Lt4VvOhGCn54gafX5zFg5rHtxcavhX8xRtAtG','Dr. Bilal Khan','+92 345 8887766',3,4,'Consultant Pediatrician','MBBS, FCPS (Pediatrics)',1800.00,'Active',NULL,'2026-09-17 14:05:52','2026-09-17 14:05:52'),(5,'nurse.fatima','fatima.nursing@hayatmedicare.org','$2y$10$pwdsQKYLEOCfjx/54vW9Cu3u/cz9CZSUHg839hmvm0cGtVbITxKQW','Fatima Noor','+92 300 5550001',4,NULL,'Nursing Superintendent & Wards / IPD Manager','BS Nursing, PNC Certified',0.00,'Active','2026-09-18 12:49:16','2026-09-17 14:05:52','2026-09-18 07:49:16'),(6,'pharm.kamran','kamran.pharmacy@hayatmedicare.org','$2y$10$y51afg1I7aS05GJljESPRONIflr3tjDDGXkRqV94RvZ8DCmXbQ65y','Kamran Bashir','+92 300 3330001',5,11,'Chief Pharmacist & Pharmacy Director','Pharm-D, RPh',0.00,'Active','2026-09-18 19:27:43','2026-09-17 14:05:52','2026-09-18 14:27:43'),(7,'lab.usman','usman.pathology@hayatmedicare.org','$2y$10$/4xhOc4kJQ8yo1qGZTfkBOAUnzgS3FHbu00VYupvIlRwvn43IrN6O','Dr. Usman Tariq','+92 300 2220001',6,8,'Head of Pathology & Laboratory Manager','MBBS, M.Phil (Hematology)',0.00,'Active','2026-09-18 12:49:16','2026-09-17 14:05:52','2026-09-18 07:49:16'),(8,'cashier.hassan','hassan.finance@hayatmedicare.org','$2y$10$PArxAEOFOggef2dRSAstq.a8DE6PhzwaDir1CodjuuPuIA3xRaIEq','Hassan Raza','+92 300 1230001',7,NULL,'Finance Controller & Central Billing Manager','B.Com, Accounts Specialist',0.00,'Active','2026-09-18 12:49:17','2026-09-17 14:05:52','2026-09-18 07:49:17'),(9,'reception.sana','sana.reception@hayatmedicare.org','$2y$10$k3sDZSYcvUpnWQ3FSFc11OSqSpyK9FDVxtkPm0gQHK0CBuWbjRNkm','Sana Sheikh','+92 300 2340001',8,NULL,'Patient Access & Front Desk Reception Manager','BBA, Patient Relations',0.00,'Active','2026-09-18 12:49:18','2026-09-17 14:05:52','2026-09-18 07:49:18'),(10,'radio.farooq','farooq.radiology@hayatmedicare.org','$2y$10$SZUBT8sBrnlq3MnHpL2KL.IFelGNuNiA.ZnqrvHCqhCeQksppbwdi','Dr. Farooq Khan','+92 300 4440001',9,9,'Head of Radiology & Imaging / PACS Director','MBBS, FCPS (Radiology)',2500.00,'Active','2026-09-18 12:49:16','2026-09-18 05:03:50','2026-09-18 07:49:16'),(11,'dr.imran','imran.surgery@hayatmedicare.org','$2y$10$KpbFq2Id5VzamYG0ODtmJOF/VRwUjcZwFilQ6Isn3YthllsI7Yr2a','Prof. Dr. Imran Malik','+92 300 6660001',3,5,'Director of Surgery & OT Operations Lead','MBBS, FCPS (Surgery), FRCS (Glasgow)',3000.00,'Active','2026-09-18 12:49:17','2026-09-18 05:11:05','2026-09-18 07:49:17'),(12,'admin.hashim','hashim.ali@hayatmedicare.org','$2y$10$Y3DCiGgU.00tstZxIdNmDOHPUyrhNR7gxoI8NAKjW4wuppgyBpK/C','Hashim Ali','+92 300 1110002',2,1,'Chief Operating Officer (COO) / Operations Director','MBA Healthcare Management',0.00,'Active','2026-09-18 12:49:15','2026-09-18 05:11:05','2026-09-18 07:49:15'),(14,'dr.asif','ms.asif@hayatmedicare.org','$2y$10$l2tDJ7p6J8gtBJfKnd5gn.teGpHnB0PfaV7B9QvaQk7/aKrkBGEm.','Prof. Dr. Asif Mehmood','+92 300 1110003',2,1,'Medical Superintendent (MS) / Clinical Director',NULL,2500.00,'Active','2026-09-18 12:49:15','2026-09-18 07:24:23','2026-09-18 07:49:15'),(15,'dr.noman','noman.er@hayatmedicare.org','$2y$10$3qFWttlx0lHcPiPlimx53OLZYXOIy7xha.PcSVnj6fBdi58/Akf/i','Dr. Noman Akram','+92 300 8880001',3,NULL,'Head of Emergency & Trauma Care Unit',NULL,2000.00,'Active','2026-09-18 12:49:17','2026-09-18 07:24:23','2026-09-18 07:49:17'),(16,'dr.sumaira','sumaira.bloodbank@hayatmedicare.org','$2y$10$dlAca9XHxKlVokyEi/4.SeBKiVW4ywjLVmcdnmUOp.rJbHZirDzMO','Dr. Sumaira Naz','+92 300 9990001',6,8,'Blood Bank & Transfusion Medicine Officer',NULL,0.00,'Active','2026-09-18 12:49:17','2026-09-18 07:24:24','2026-09-18 07:49:17'),(17,'store.zubair','zubair.procurement@hayatmedicare.org','$2y$10$T2ZNi463upcSfsKTH1PllOmv7lcjH5yONTtgAXAyg/mCLn.FyEBaW','Zubair Ahmed','+92 300 3450001',5,11,'Central Stores & Procurement Manager',NULL,0.00,'Active','2026-09-18 12:49:18','2026-09-18 07:24:24','2026-09-18 07:49:18');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `wards`
--

DROP TABLE IF EXISTS `wards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wards` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ward_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ward_type` enum('General_Male','General_Female','Semi-Private','Private','ICU','CCU','Emergency_Bay','Isolation','Maternity') COLLATE utf8mb4_unicode_ci NOT NULL,
  `floor_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '1st Floor',
  `total_capacity` int(11) DEFAULT 10,
  `status` enum('Active','Under_Maintenance','Inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'Active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `wards`
--

LOCK TABLES `wards` WRITE;
/*!40000 ALTER TABLE `wards` DISABLE KEYS */;
INSERT INTO `wards` VALUES (1,'Male Medical General Ward','MMW-1','General_Male','1st Floor - Wing A',10,'Active'),(2,'Female Medical General Ward','FMW-1','General_Female','1st Floor - Wing B',10,'Active'),(3,'Intensive Care Unit (ICU)','ICU-3','ICU','3rd Floor - Critical',6,'Active'),(4,'Executive Private Suites','PVT-2','Private','2nd Floor - VIP Wing',5,'Active'),(5,'Emergency Observation Bay','ER-BAY','Emergency_Bay','Ground Floor - Traum',8,'Active');
/*!40000 ALTER TABLE `wards` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-29 20:32:28
