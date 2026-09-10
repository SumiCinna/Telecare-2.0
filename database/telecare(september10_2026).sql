-- MySQL dump 10.13  Distrib 8.0.43, for Win64 (x86_64)
--
-- Host: localhost    Database: telecare
-- ------------------------------------------------------
-- Server version	8.0.43

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'Admin','admin@telecare.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','2026-03-08 07:01:28');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment_document_drafts`
--

DROP TABLE IF EXISTS `appointment_document_drafts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointment_document_drafts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appointment_id` int NOT NULL,
  `doc_type` enum('prescription','lab_request','med_cert') NOT NULL,
  `draft_text` longtext NOT NULL,
  `source_summary` longtext,
  `source_model` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_appt_doc` (`appointment_id`,`doc_type`),
  KEY `idx_appointment_id` (`appointment_id`),
  CONSTRAINT `appointment_document_drafts_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_document_drafts`
--

LOCK TABLES `appointment_document_drafts` WRITE;
/*!40000 ALTER TABLE `appointment_document_drafts` DISABLE KEYS */;
/*!40000 ALTER TABLE `appointment_document_drafts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointment_logs`
--

DROP TABLE IF EXISTS `appointment_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointment_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `appointment_id` int NOT NULL,
  `staff_id` int NOT NULL,
  `action` varchar(60) NOT NULL,
  `notes` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `appointment_id` (`appointment_id`),
  KEY `staff_id` (`staff_id`),
  CONSTRAINT `appointment_logs_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointment_logs_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff_accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_logs`
--

LOCK TABLES `appointment_logs` WRITE;
/*!40000 ALTER TABLE `appointment_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `appointment_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reference_no` varchar(20) DEFAULT NULL,
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `type` enum('General','Follow-up','Emergency','Teleconsult') DEFAULT 'Teleconsult',
  `department` varchar(50) DEFAULT NULL,
  `status` enum('Pending','DoctorApproved','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `completed_at` datetime DEFAULT NULL,
  `notes` text,
  `reason` varchar(500) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_type` varchar(20) DEFAULT NULL,
  `attachment_ocr_text` mediumtext,
  `payment_status` enum('Unpaid','Paid') DEFAULT 'Unpaid',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `paymongo_link_id` varchar(100) DEFAULT NULL,
  `receipt_number` varchar(30) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `chat_log` text,
  `consultation_transcript` text,
  `consultation_summary` text,
  `summary_pdf_path` varchar(255) DEFAULT NULL,
  `summary_session_key` varchar(50) DEFAULT NULL,
  `summary_edited` tinyint(1) DEFAULT '0',
  `summary_reviewed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_reference_no` (`reference_no`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (1,'APT-2026-94856',1,4,'2026-08-22','17:00:00','Teleconsult','General Medicine','Confirmed',NULL,'Fever, Headache','Fever, Headache',NULL,NULL,NULL,'Paid','2026-08-22 14:13:57','src_h85hrYVXAV4Wi4thHsTLh5Mu','TC-9BC7F0F1','2026-08-22 22:17:16',NULL,NULL,NULL,'summary_1.pdf',NULL,0,NULL),(2,'APT-2026-15039',1,4,'2026-08-22','21:30:00','Teleconsult','General Medicine','Cancelled',NULL,'Fever, Cough / Cold','Fever, Cough / Cold',NULL,NULL,NULL,'Unpaid','2026-08-22 14:13:57',NULL,NULL,NULL,NULL,NULL,NULL,'summary_2.pdf',NULL,0,NULL),(3,'APT-2026-59332',1,4,'2026-08-22','22:30:00','Teleconsult','General Medicine','Cancelled',NULL,'Fever, Headache','Fever, Headache',NULL,NULL,NULL,'Unpaid','2026-08-22 14:13:57',NULL,NULL,NULL,NULL,NULL,NULL,'summary_3.pdf',NULL,0,NULL),(4,'APT-2026-08891',1,4,'2026-08-23','17:00:00','Teleconsult','General Medicine','Cancelled',NULL,'Fever, Headache','Fever, Headache',NULL,NULL,NULL,'Unpaid','2026-08-22 14:23:52',NULL,NULL,NULL,NULL,NULL,NULL,'summary_4.pdf',NULL,0,NULL),(5,'APT-2026-38437',1,4,'2026-09-04','23:58:53','Teleconsult','General Medicine / General Practice','Confirmed',NULL,NULL,'Instant call started by Dr. EXAMPLE DOCTOR#1',NULL,NULL,NULL,'Paid','2026-09-04 15:58:53',NULL,NULL,'2026-09-04 23:58:53',NULL,NULL,NULL,'summary_5.pdf',NULL,0,NULL),(6,'APT-2026-33184',1,4,'2026-09-04','23:59:28','Teleconsult','General Medicine / General Practice','Confirmed',NULL,NULL,'Instant call started by Dr. EXAMPLE DOCTOR#1',NULL,NULL,NULL,'Paid','2026-09-04 15:59:28',NULL,NULL,'2026-09-04 23:59:28',NULL,NULL,NULL,'summary_6.pdf',NULL,0,NULL),(7,'APT-2026-99196',1,4,'2026-09-04','23:59:29','Teleconsult','General Medicine / General Practice','Confirmed',NULL,NULL,'Instant call started by Dr. EXAMPLE DOCTOR#1',NULL,NULL,NULL,'Paid','2026-09-04 15:59:29',NULL,NULL,'2026-09-04 23:59:29',NULL,NULL,NULL,'summary_7.pdf',NULL,0,NULL),(8,'APT-2026-45636',1,4,'2026-09-04','23:59:59','Teleconsult','General Medicine / General Practice','Confirmed',NULL,NULL,'Instant call started by Dr. EXAMPLE DOCTOR#1',NULL,NULL,NULL,'Paid','2026-09-04 15:59:59',NULL,NULL,'2026-09-04 23:59:59',NULL,NULL,NULL,'summary_8.pdf',NULL,0,NULL),(9,'APT-2026-52079',1,4,'2026-09-05','00:00:57','Teleconsult','General Medicine / General Practice','Confirmed',NULL,NULL,'Instant call started by Dr. EXAMPLE DOCTOR#1',NULL,NULL,NULL,'Paid','2026-09-04 16:00:57',NULL,NULL,'2026-09-05 00:00:57',NULL,NULL,NULL,NULL,NULL,0,NULL),(10,'APT-2026-91955',1,4,'2026-09-05','00:01:43','Teleconsult','General Medicine / General Practice','Confirmed',NULL,NULL,'Instant call started by Dr. EXAMPLE DOCTOR#1',NULL,NULL,NULL,'Paid','2026-09-04 16:01:43',NULL,NULL,'2026-09-05 00:01:43',NULL,NULL,NULL,NULL,NULL,0,NULL),(11,'APT-2026-22297',1,4,'2026-09-09','16:30:00','Teleconsult','General Medicine / General Practice','Completed','2026-09-09 01:01:11','','Colds / Runny nose',NULL,NULL,NULL,'Paid','2026-09-08 13:40:19','src_nxwDFAMfJicBK81Tz2Z5Pvr4','TC-D27C0C67','2026-09-08 21:40:35',NULL,NULL,NULL,NULL,NULL,0,NULL),(12,'APT-2026-25191',1,4,'2026-09-09','22:30:00','Teleconsult','General Medicine / General Practice','Confirmed',NULL,'','Fever',NULL,NULL,NULL,'Paid','2026-09-09 13:35:31','src_tCwfqbnAKQYAoiz7zGozDYeC','TC-34C1769E','2026-09-09 21:35:54',NULL,NULL,NULL,'summary_12.pdf',NULL,0,NULL),(13,'APT-2026-38836',1,4,'2026-09-11','13:00:00','Teleconsult','General Medicine / General Practice','Cancelled',NULL,'','Fever',NULL,NULL,NULL,'Unpaid','2026-09-09 14:54:54',NULL,NULL,NULL,NULL,NULL,NULL,'summary_13.pdf',NULL,0,NULL),(14,'IC-6136E3B3',1,4,'2026-09-10','20:30:44','Teleconsult',NULL,'Confirmed',NULL,NULL,'Instant call started by doctor',NULL,NULL,NULL,'Paid','2026-09-10 12:30:44',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL);
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `audit_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `admin_id` int NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(30) NOT NULL,
  `entity_id` int NOT NULL,
  `old_values` json DEFAULT NULL,
  `new_values` json DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `entity` (`entity_type`,`entity_id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'update','doctor',5,'{\"email\": \"dizoah3@gmail.com\", \"full_name\": \"Ian Matthew Payawal\"}','{\"email\": \"dizoah3@gmail.com\", \"full_name\": \"Ian Matthew Payawal\"}','2026-08-20 00:32:09'),(2,1,'update','staff',4,'{\"email\": \"staff@telecare.com\", \"full_name\": \"TELE-CARE Staff\"}','{\"email\": \"staff@telecare.com\", \"full_name\": \"TELE-CARE StaffS\"}','2026-08-20 00:41:26'),(3,1,'update','staff',4,'{\"email\": \"staff@telecare.com\", \"full_name\": \"TELE-CARE StaffS\"}','{\"email\": \"staff@telecare.com\", \"full_name\": \"TELE-CARE Staff\"}','2026-08-20 00:41:35'),(4,1,'update','doctor',4,'{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"Ma. Aberlee Lacanaria\"}','{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#1\"}','2026-08-20 00:42:12'),(5,1,'create','doctor',6,NULL,'{\"email\": \"xdreiaawe@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#2\", \"specialty\": \"General\", \"subspecialty\": \"\"}','2026-08-20 14:20:01'),(6,1,'update','doctor',4,'{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#1\"}','{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#1\"}','2026-08-21 16:53:37'),(7,1,'toggle','doctor',6,'{\"status\": \"active\"}','{\"status\": \"inactive\"}','2026-08-21 16:55:15'),(8,1,'toggle','doctor',6,'{\"status\": \"inactive\"}','{\"status\": \"active\"}','2026-08-21 16:55:15'),(9,1,'create','service',5,NULL,'{\"name\": \"asda\", \"price\": \"222\", \"category\": \"Laboratory\"}','2026-08-23 20:54:32'),(10,1,'set_requirement','service',5,NULL,'{\"product_id\": 2, \"quantity_used\": 1}','2026-08-23 20:54:43'),(11,1,'set_requirement','service',5,NULL,'{\"product_id\": 5, \"quantity_used\": 1}','2026-08-23 20:54:46'),(12,1,'remove_requirement','service',5,'{\"requirement_id\": 3}',NULL,'2026-08-23 20:56:06'),(13,1,'remove_requirement','service',5,'{\"requirement_id\": 4}',NULL,'2026-08-23 20:56:07'),(14,1,'create','service',6,NULL,'{\"name\": \"SADA\", \"price\": \"222\", \"category\": \"Laboratory\"}','2026-08-23 21:09:23'),(15,1,'create','service',7,NULL,'{\"name\": \"sdada\", \"price\": \"222\", \"category\": \"Laboratory\"}','2026-08-23 22:07:43'),(16,1,'create','service',8,NULL,'{\"name\": \"sda\", \"price\": \"222\", \"category\": \"Laboratory\"}','2026-08-23 22:11:57'),(17,1,'set_requirement','service',8,NULL,'{\"product_id\": 31, \"quantity_used\": 1}','2026-08-23 22:12:02'),(18,1,'archive','service',5,'{\"status\": \"Active\"}','{\"status\": \"Archived\"}','2026-08-23 22:12:18'),(19,1,'set_requirement','service',5,NULL,'{\"product_id\": 26, \"quantity_used\": 1}','2026-08-23 22:12:30'),(20,1,'remove_requirement','service',2,'{\"requirement_id\": 2}',NULL,'2026-08-23 22:54:40'),(21,1,'set_requirement','service',2,NULL,'{\"product_id\": 26, \"quantity_used\": 1}','2026-08-23 22:54:45'),(22,1,'deactivate','service',1,'{\"status\": \"Active\"}','{\"status\": \"Inactive\"}','2026-08-29 15:24:44'),(23,1,'deactivate','service',4,'{\"status\": \"Active\"}','{\"status\": \"Inactive\"}','2026-08-29 15:27:26'),(24,1,'deactivate','service',4,'{\"status\": \"Active\"}','{\"status\": \"Inactive\"}','2026-08-29 15:27:38'),(25,1,'deactivate','service',4,'{\"status\": \"Active\"}','{\"status\": \"Inactive\"}','2026-08-29 15:29:24'),(26,1,'deactivate','service',4,'{\"status\": \"Active\"}','{\"status\": \"Inactive\"}','2026-08-29 15:32:32'),(27,1,'activate','service',4,'{\"status\": \"Inactive\"}','{\"status\": \"Active\"}','2026-08-29 15:32:37'),(28,1,'activate','service',5,'{\"status\": \"Inactive\"}','{\"status\": \"Active\"}','2026-08-29 15:33:02'),(29,1,'deactivate','service',5,'{\"status\": \"Active\"}','{\"status\": \"Inactive\"}','2026-08-29 15:33:06'),(30,1,'update','doctor',4,'{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#1\"}','{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#1\"}','2026-09-04 22:27:17'),(31,1,'update','doctor',6,'{\"email\": \"xdreiaawe@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#2\"}','{\"email\": \"xdreiaawe@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#2\"}','2026-09-04 22:27:24'),(32,1,'update','document_templates',0,NULL,'{\"clinic_name\": \"hi\"}','2026-09-08 23:23:58'),(33,1,'update','document_templates',0,NULL,'{\"clinic_name\": \"hi\"}','2026-09-08 23:29:17'),(34,1,'create','document_templates',2,NULL,'{\"template_name\": \"Clinic\"}','2026-09-09 01:34:19'),(35,1,'set_default','document_templates',2,NULL,'{\"doc_type\": \"prescription\"}','2026-09-09 01:34:30'),(36,1,'update','document_templates',2,NULL,'{\"template_name\": \"Clinic\"}','2026-09-09 01:35:10');
/*!40000 ALTER TABLE `audit_logs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `discounts`
--

DROP TABLE IF EXISTS `discounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `discounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `type` enum('Percentage','Fixed') NOT NULL DEFAULT 'Percentage',
  `value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `conditions_text` text,
  `status` enum('Active','Archived') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `discounts`
--

LOCK TABLES `discounts` WRITE;
/*!40000 ALTER TABLE `discounts` DISABLE KEYS */;
INSERT INTO `discounts` VALUES (1,'Senior Citizen','Percentage',20.00,'Valid Senior Citizen ID required.','Active','2026-08-23 11:27:45','2026-08-23 11:27:45'),(2,'PWD','Percentage',20.00,'Valid PWD ID required.','Active','2026-08-23 11:27:45','2026-08-23 11:27:45'),(3,'Clinic Promo','Fixed',100.00,'Applies to walk-in transactions above ₱500.','Active','2026-08-23 11:27:45','2026-08-23 11:27:45');
/*!40000 ALTER TABLE `discounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctor_schedule_settings`
--

DROP TABLE IF EXISTS `doctor_schedule_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctor_schedule_settings` (
  `doctor_id` int NOT NULL,
  `consultation_duration` smallint unsigned NOT NULL DEFAULT '30',
  `appointment_interval` smallint unsigned NOT NULL DEFAULT '5',
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `consultation_types` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'In-person',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doctor_id`),
  CONSTRAINT `doctor_schedule_settings_doctor_fk` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctor_schedule_settings`
--

LOCK TABLES `doctor_schedule_settings` WRITE;
/*!40000 ALTER TABLE `doctor_schedule_settings` DISABLE KEYS */;
INSERT INTO `doctor_schedule_settings` VALUES (4,30,5,NULL,NULL,'In-person','2026-09-10 09:33:58');
/*!40000 ALTER TABLE `doctor_schedule_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctor_schedules`
--

DROP TABLE IF EXISTS `doctor_schedules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctor_schedules` (
  `id` int NOT NULL AUTO_INCREMENT,
  `doctor_id` int NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  PRIMARY KEY (`id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `doctor_schedules_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctor_schedules`
--

LOCK TABLES `doctor_schedules` WRITE;
/*!40000 ALTER TABLE `doctor_schedules` DISABLE KEYS */;
INSERT INTO `doctor_schedules` VALUES (136,5,'Monday','06:00:00','13:00:00'),(137,5,'Monday','15:00:00','19:00:00'),(138,5,'Friday','06:00:00','14:00:00'),(139,5,'Saturday','06:00:00','14:00:00'),(150,4,'Monday','09:00:00','17:00:00');
/*!40000 ALTER TABLE `doctor_schedules` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `doctors`
--

DROP TABLE IF EXISTS `doctors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `doctors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `subspecialty` varchar(100) DEFAULT NULL,
  `clinic_name` varchar(150) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `bio` text,
  `consultation_fee` decimal(10,2) DEFAULT '0.00',
  `languages_spoken` varchar(255) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT '0.0',
  `access_level` enum('junior','senior','consultant') DEFAULT 'junior',
  `status` enum('pending','active','inactive') DEFAULT 'pending',
  `is_available` tinyint(1) DEFAULT '0',
  `license_number` varchar(100) DEFAULT NULL,
  `issuing_board` varchar(150) DEFAULT NULL,
  `license_file` varchar(255) DEFAULT NULL,
  `board_cert_file` varchar(255) DEFAULT NULL,
  `consent_signed` tinyint(1) DEFAULT '0',
  `is_verified` tinyint(1) DEFAULT '0',
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int DEFAULT NULL,
  `invite_token` varchar(100) DEFAULT NULL,
  `invite_expires` datetime DEFAULT NULL,
  `setup_complete` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `verified_by` (`verified_by`),
  CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`verified_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctors`
--

LOCK TABLES `doctors` WRITE;
/*!40000 ALTER TABLE `doctors` DISABLE KEYS */;
INSERT INTO `doctors` VALUES (4,'EXAMPLE DOCTOR#1','General','General Medicine / General Practice','','EXCELLCARE MEDICAL SYSTEM INC.','almondtofu25@gmail.com','$2y$10$KFzfCMLW/6YVdUWjiYG8b.g0yyUP6ZAW24jy1H7DrbopyR.btv15a','09999999999','uploads/profiles/doc_69b50c372d091.jfif','General doctor at EXCELLCARE MEDICAL SYSTEM INC.',500.00,'Filipino',0.0,'senior','active',1,'','',NULL,NULL,1,1,'2026-03-14 00:39:41',1,NULL,'2026-03-21 07:46:05',1,'2026-03-14 06:46:05','2026-09-04 14:18:19'),(5,'Ian Matthew Payawal','General','Cardiology','Cardiology','TELE-CARE STAFF','dizoah3@gmail.com','$2y$10$/WhcdL6CDFFo7M2w0ynJ4eq0kPxJ.Wnn/Nn4VhHl7.6evTz5DykqW','09999999992','uploads/profiles/doc_69bd4ead3abc2.png','Cardiology subspecialty',750.00,'English, Filipino',0.0,'junior','active',1,'','','uploads/docs/doc_5_69d7b40d58257.exe',NULL,1,0,'2026-04-09 08:13:33',1,NULL,'2026-03-22 11:33:12',1,'2026-03-15 10:33:12','2026-08-21 13:57:01'),(6,'EXAMPLE DOCTOR#2','General','Cardiology','','','xdreiaawe@gmail.com','$2y$10$0uhxv9h108ktfIiuuNvE9.IfYwnWXZNm958koGuybGGE.Mp1Kug36','',NULL,NULL,0.00,'',0.0,'junior','active',1,NULL,NULL,NULL,NULL,0,0,NULL,NULL,NULL,NULL,1,'2026-08-20 06:20:01','2026-09-04 14:27:24');
/*!40000 ALTER TABLE `doctors` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_templates`
--

DROP TABLE IF EXISTS `document_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_templates` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `doc_type` varchar(30) NOT NULL,
  `template_name` varchar(255) NOT NULL DEFAULT '',
  `clinic_name` varchar(255) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `contact_no` varchar(100) NOT NULL DEFAULT '',
  `footer_note` text,
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_doc_type` (`doc_type`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_templates`
--

LOCK TABLES `document_templates` WRITE;
/*!40000 ALTER TABLE `document_templates` DISABLE KEYS */;
INSERT INTO `document_templates` VALUES (1,'prescription','Main Clinic','hi','','','',0,'Active','2026-09-08 17:34:29'),(2,'prescription','Clinic','Telecare','Telecare','09923139504','',1,'Active','2026-09-08 17:34:29');
/*!40000 ALTER TABLE `document_templates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_templates_backup`
--

DROP TABLE IF EXISTS `document_templates_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_templates_backup` (
  `doc_type` varchar(30) NOT NULL,
  `clinic_name` varchar(255) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `contact_no` varchar(100) NOT NULL DEFAULT '',
  `physician_name` varchar(255) NOT NULL DEFAULT '',
  `credentials` varchar(100) NOT NULL DEFAULT '',
  `license_no` varchar(100) NOT NULL DEFAULT '',
  `ptr_no` varchar(100) NOT NULL DEFAULT '',
  `footer_note` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_templates_backup`
--

LOCK TABLES `document_templates_backup` WRITE;
/*!40000 ALTER TABLE `document_templates_backup` DISABLE KEYS */;
INSERT INTO `document_templates_backup` VALUES ('prescription','hi','','','','','','','','2026-09-08 15:23:58');
/*!40000 ALTER TABLE `document_templates_backup` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `document_templates_old`
--

DROP TABLE IF EXISTS `document_templates_old`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_templates_old` (
  `doc_type` varchar(30) NOT NULL,
  `clinic_name` varchar(255) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `contact_no` varchar(100) NOT NULL DEFAULT '',
  `physician_name` varchar(255) NOT NULL DEFAULT '',
  `credentials` varchar(100) NOT NULL DEFAULT '',
  `license_no` varchar(100) NOT NULL DEFAULT '',
  `ptr_no` varchar(100) NOT NULL DEFAULT '',
  `footer_note` text,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doc_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_templates_old`
--

LOCK TABLES `document_templates_old` WRITE;
/*!40000 ALTER TABLE `document_templates_old` DISABLE KEYS */;
INSERT INTO `document_templates_old` VALUES ('prescription','hi','','','','','','','','2026-09-08 15:23:58');
/*!40000 ALTER TABLE `document_templates_old` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hmo_coverage`
--

DROP TABLE IF EXISTS `hmo_coverage`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hmo_coverage` (
  `id` int NOT NULL AUTO_INCREMENT,
  `provider_id` int NOT NULL,
  `description` varchar(200) NOT NULL,
  `coverage_type` enum('Percentage','Fixed') NOT NULL DEFAULT 'Percentage',
  `coverage_value` decimal(10,2) NOT NULL DEFAULT '0.00',
  `notes` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `provider_id` (`provider_id`),
  CONSTRAINT `hmo_coverage_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `hmo_providers` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hmo_coverage`
--

LOCK TABLES `hmo_coverage` WRITE;
/*!40000 ALTER TABLE `hmo_coverage` DISABLE KEYS */;
INSERT INTO `hmo_coverage` VALUES (1,1,'General Consultation','Percentage',100.00,'Fully covered, no co-pay.','2026-08-23 11:28:12'),(2,1,'Laboratory Tests','Percentage',80.00,'20% co-pay applies.','2026-08-23 11:28:12'),(3,2,'General Consultation','Fixed',300.00,'Fixed coverage amount, excess billed to patient.','2026-08-23 11:28:12');
/*!40000 ALTER TABLE `hmo_coverage` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `hmo_providers`
--

DROP TABLE IF EXISTS `hmo_providers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hmo_providers` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `status` enum('Active','Archived') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `hmo_providers`
--

LOCK TABLES `hmo_providers` WRITE;
/*!40000 ALTER TABLE `hmo_providers` DISABLE KEYS */;
INSERT INTO `hmo_providers` VALUES (1,'Maxicare','Anna Cruz','02-8888-1234','partners@maxicare.example','Active','2026-08-23 11:28:12','2026-08-23 11:28:12'),(2,'Intellicare','Mark Reyes','02-8888-5678','partners@intellicare.example','Active','2026-08-23 11:28:12','2026-08-23 11:28:12');
/*!40000 ALTER TABLE `hmo_providers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lab_results`
--

DROP TABLE IF EXISTS `lab_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `lab_results` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `doc_type` enum('lab_result','prescription','lab_request','med_cert','unknown') DEFAULT 'unknown',
  `doc_label` varchar(200) DEFAULT NULL,
  `extracted_text` longtext,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `lab_results_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_results`
--

LOCK TABLES `lab_results` WRITE;
/*!40000 ALTER TABLE `lab_results` DISABLE KEYS */;
INSERT INTO `lab_results` VALUES (1,1,'uploads/ocr/ocr_69aee8a9b1de2.pdf','prescription','Prescription 1','--- Page 1 ---\nTELE-CARE Medical Clinic\n123 Mabini Street, Caloocan City, Metro Manila | Tel: (02) 8123-4567\ntelecaremedical@telecare.com\n\nPATIENT NAME: John Noel Orano DATE: March 9, 2026\nAGE / SEX: 22 years old / Male PATIENT ID: TC-2026-0042\nADDRESS: NPC Kanan Makisig St., Brgy. 171, Caloocan City\nPHONE: 09923139504\n1. Amoxicillin 500mg Capsule\n\nSig: Take 1 capsule every 8 hours for 7 days\n\nDispense: 21 capsules | Refills: 0\n2. Metformin 500mg Tablet\n\nSig: Take 1 tablet twice daily with meals (morning and evening)\n\nDispense: 60 tablets | Refills: 2\n3. Losartan Potassium 50mg Tablet\n\nSig: Take 1 tablet once daily in the morning\n\nDispense: 30 tablets | Refills: 3\nNOTES:\nPatient advised to take medications with food. Avoid alcohol while on Amoxicillin. Monitor blood sugar levels daily. Return for\nfollow-up after 2 weeks or immediately if symptoms worsen.\n\nDr. Maria Santos, MD\nCardiologist | PRC Lic. No. 0123456\nSt. Luke Medical Wellness Center\nThis prescription is valid for 30 days from the date of issue. | TELE-CARE © 2026','2026-03-09 15:35:10'),(2,5,'uploads/ocr/ocr_69b66f98ab067.pdf','prescription','Prescription 1','--- Page 1 ---\n321 Quezon Avenue, Quezon City, Metro Manila | Tel: (02) 8321-9876\ninfo@excellcare.com | www.excellcare.com\nPATIENT NAME: ~— Bernard Dela Cruz Santos DATE: March 15, 2026\nAGE / SEX: 34 years old / Male PATIENT == TC-2026-0155\nID:\nDIAGNOSIS: Upper Respiratory Tract Infection, PHONE: 09561234567\nAsthma\nALLERGY: Sulfonamides (avoid)\nRx\n1. Azithromycin 500mg Tablet\nSig: Take 1 tablet once daily for 5 days\nDispense: 5 tablets | Refills: 0\nWARNING: Complete the full course even if feeling better. Avoid antacids within 2 hours of taking.\n2. Salbutamol 2mg Tablet\nSig: Take 1 tablet three times daily (morning, afternoon, and evening)\nDispense: 30 tablets | Refills: 2\nWARNING: May cause tremors or increased heart rate. Do not exceed prescribed dose. Avoid caffeine.\n3. Montelukast 10mg Tablet\nSig: Take 1 tablet once daily at bedtime\nDispense: 30 tablets | Refills: 3\nWARNING: Report any changes in mood or behavior immediately. Do not stop without consulting your doctor.\n4. Cetirizine 10mg Tablet\nSig: Take 1 tablet once daily at night\nDispense: 14 tablets | Refills: 1\nWARNING: May cause drowsiness. Avoid driving or operating machinery after taking. Avoid alcohol.\n5. Paracetamol 500mg Tablet\nSig: Take 1-2 tablets every 4 to 6 hours as needed for fever or pain. Do not exceed 8 tablets per day.\nDispense: 20 tablets | Refills: 0\nWARNING: Do not take with other paracetamol-containing products. Avoid alcohol. Urgent — stop\nimmediately if yellowing of skin or eyes occurs.\nNOTES:\nPatient advised to rest and increase fluid intake. Steam inhalation 2-3 times daily recommended. Avoid\ncold drinks and air-conditioned environments. Use Salbutamol as rescue medication during acute asthma\nattack. Return immediately if dysonea worsens or fever persists beyond 3 days. Follow-up in 1 week.\nDr. Ma. Aberlee Lacanaria\n\n--- Page 2 ---\nGeneral Practitioner | PRC Lic. No. 0312456\nEXCELLCARE MEDICAL SYSTEM INC.\nThis prescription is valid for 30 days from the date of issue. | EXCELLCARE © 2026','2026-03-15 08:36:48'),(5,8,'uploads/ocr/ocr_6a7efb64eed92.jpg','unknown','','4:11 • 9	(VOLTE 2 450	1 450,	51	\r\nJerson Sagun	\r\n& About me	\r\n• Contact details	>	\r\ni Emergency contact details	\r\n• Address	\r\n• Payment method	\r\n• Delete account','2026-08-14 11:26:30'),(6,1,'uploads/generated_documents/document_11_prescription_20260909_012037.html','prescription','Prescription - Dr. EXAMPLE DOCTOR#1','PRESCRIPTION\r\n\r\nPatient\'s Name: \r\nAge: 21\r\nSex: Male\r\nAddress: \r\nDate: September 9, 2026\r\n\r\nRx [MEDICATION NAME / STRENGTH / FORM]\r\n[INSTRUCTIONS / DOSAGE]\r\nDispense: [QUANTITY / VOLUME]\r\nLabel: [DIRECTIONS FOR USE]\r\n[ADDITIONAL INSTRUCTIONS]\r\n\r\nClinical note based on consultation summary:\r\nNot discussed.','2026-09-08 17:20:37'),(7,1,'uploads/generated_documents/document_11_prescription_20260909_013440.pdf','prescription','Prescription - Dr. EXAMPLE DOCTOR#1','Rx [MEDICATION NAME / STRENGTH / FORM]\r\n[INSTRUCTIONS / DOSAGE]\r\nDispense: [QUANTITY / VOLUME]\r\nLabel: [DIRECTIONS FOR USE]\r\n[ADDITIONAL INSTRUCTIONS]\r\n\r\nClinical note based on consultation summary:\r\nNot discussed.','2026-09-08 17:34:40'),(8,1,'uploads/generated_documents/document_11_prescription_20260909_014052.pdf','prescription','Prescription - Dr. EXAMPLE DOCTOR#1','Rx [MEDICATION NAME / STRENGTH / FORM]\r\n[INSTRUCTIONS / DOSAGE]\r\nDispense: [QUANTITY / VOLUME]\r\nLabel: [DIRECTIONS FOR USE]\r\n[ADDITIONAL INSTRUCTIONS]\r\n\r\nClinical note based on consultation summary:\r\nNot discussed.','2026-09-08 17:40:52');
/*!40000 ALTER TABLE `lab_results` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `legal_policies`
--

DROP TABLE IF EXISTS `legal_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legal_policies` (
  `id` int NOT NULL AUTO_INCREMENT,
  `slug` varchar(60) NOT NULL,
  `title` varchar(150) NOT NULL,
  `type` varchar(40) NOT NULL,
  `short_desc` varchar(255) DEFAULT NULL,
  `content` longtext NOT NULL,
  `version` varchar(20) NOT NULL DEFAULT 'v1.0',
  `status` enum('Draft','Published','Archived') NOT NULL DEFAULT 'Draft',
  `applicable_to` varchar(255) DEFAULT 'all',
  `effective_date` date DEFAULT NULL,
  `updated_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `legal_policies`
--

LOCK TABLES `legal_policies` WRITE;
/*!40000 ALTER TABLE `legal_policies` DISABLE KEYS */;
INSERT INTO `legal_policies` VALUES (1,'data-privacy-notice','Data Privacy Notice','Data & Privacy','Guidelines for data collection, usage, and protection.','<h4>1. Who We Are</h4>\r\n<p>This Data Privacy Notice is issued by TELE-CARE (\"we,\" \"us,\" or \"our\") for our telemedicine platform, in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and its Implementing Rules and Regulations.</p>\r\n\r\n<h4>2. Personal Data We Collect</h4>\r\n<p>When you register and use TELE-CARE, we collect:</p>\r\n<ul>\r\n  <li>Identity information — full name, date of birth, contact number, email address</li>\r\n  <li>Account credentials — password (stored in encrypted/hashed form)</li>\r\n  <li>Health information — medical history, symptoms, consultation notes, prescriptions, lab results, and other information you or your attending doctor provide during a teleconsultation</li>\r\n  <li>Technical data — device information, IP address, browser type, and log data related to your use of the platform</li>\r\n  <li>Communications — messages, video/audio session metadata, and files exchanged with healthcare providers through the platform</li>\r\n</ul>\r\n\r\n<h4>3. Why We Collect Your Data</h4>\r\n<p>Your personal and health data are collected and processed to:</p>\r\n<ul>\r\n  <li>Create and manage your patient account</li>\r\n  <li>Facilitate teleconsultations between you and licensed healthcare providers</li>\r\n  <li>Maintain accurate medical and consultation records</li>\r\n  <li>Send appointment confirmations, reminders, and account-related notifications</li>\r\n  <li>Process payments for consultations, where applicable</li>\r\n  <li>Comply with legal, regulatory, and reporting obligations</li>\r\n  <li>Improve the safety, security, and functionality of the platform</li>\r\n</ul>\r\n\r\n<h4>4. Sensitive Personal Information</h4>\r\n<p>Health-related data is classified as \"sensitive personal information\" under RA 10173. We only process this data with your explicit consent, and access is restricted to your attending healthcare provider, authorized clinic/administrative staff directly involved in your care, and personnel required by law or regulation.</p>\r\n\r\n<h4>5. Data Sharing and Disclosure</h4>\r\n<p>We do not sell your personal data. Your information may only be shared with:</p>\r\n<ul>\r\n  <li>Licensed doctors and staff directly involved in your consultation</li>\r\n  <li>Service providers who support platform operations (e.g., email delivery, secure hosting) under confidentiality obligations</li>\r\n  <li>Government agencies or regulators, when required by law, court order, or public health reporting requirements</li>\r\n</ul>\r\n\r\n<h4>6. Data Retention</h4>\r\n<p>Your personal and medical records are retained for as long as your account is active and for a period thereafter as required by applicable healthcare recordkeeping laws and regulations, after which the data will be securely disposed of or anonymized.</p>\r\n\r\n<h4>7. Your Rights</h4>\r\n<p>Under the Data Privacy Act, you have the right to be informed, to access, to object, to correct, to erase or block your data (subject to legal retention requirements), to data portability, and to file a complaint with the National Privacy Commission. To exercise these rights, contact us through the channels provided on your patient dashboard.</p>\r\n\r\n<h4>8. Security Measures</h4>\r\n<p>We apply organizational, physical, and technical safeguards — including password hashing, encrypted connections, and access controls — to protect your data against unauthorized access, alteration, disclosure, or destruction.</p>\r\n\r\n<h4>9. Consent</h4>\r\n<p>By creating a TELE-CARE account, you acknowledge that you have read and understood this Data Privacy Notice and consent to the collection, use, and processing of your personal and sensitive personal information as described above.</p>','v1.4','Published','all','2026-09-07','Super Admin','2026-09-07 10:30:07','2026-09-07 11:23:47'),(2,'privacy-policy','Privacy Policy','Data & Privacy','Details on how user information is collected, used, and secured.','<h4>1. Overview</h4>\r\n<p>This Privacy Policy explains how TELE-CARE handles information collected through our telemedicine platform, in addition to the specific commitments made in our Data Privacy Notice.&nbsp;</p>\r\n\r\n<h4>2. Information We Collect Automatically</h4>\r\n<p>When you use TELE-CARE, our systems may automatically collect device type, browser, IP address, session timestamps, and general usage patterns (e.g., pages visited, features used) to help us maintain and improve the platform.</p>\r\n\r\n<h4>3. Cookies and Similar Technologies</h4>\r\n<p>TELE-CARE may use cookies or similar technologies to keep you logged in, remember your preferences, and understand how the platform is used. You can control cookies through your browser settings, though disabling them may affect platform functionality.</p>\r\n\r\n<h4>4. How We Use Information</h4>\r\n<ul>\r\n  <li>To operate, maintain, and secure the platform</li>\r\n  <li>To personalize your experience (e.g., pre-filling known details, showing relevant appointment information)</li>\r\n  <li>To detect, investigate, and prevent fraudulent or unauthorized activity</li>\r\n  <li>To analyze aggregate usage trends for service improvement, using de-identified data where possible</li>\r\n</ul>\r\n\r\n<h4>5. Third-Party Services</h4>\r\n<p>TELE-CARE relies on trusted third-party providers for functions such as email delivery and secure video communication. These providers process data only as necessary to perform their function and are contractually or technically restricted from using your data for unrelated purposes.</p>\r\n\r\n<h4>6. Data Storage and International Transfers</h4>\r\n<p>Your data is stored on secure servers. Where any data is processed or stored outside the Philippines by a service provider, we take reasonable steps to ensure it remains protected to a standard consistent with the Data Privacy Act.</p>\r\n\r\n<h4>7. Children\'s Privacy</h4>\r\n<p>TELE-CARE is intended for users 18 years of age and older. We do not knowingly collect personal data from minors through direct patient registration.</p>\r\n\r\n<h4>8. Updates to This Policy</h4>\r\n<p>We may revise this Privacy Policy periodically. Material changes will be communicated through the platform or via email prior to taking effect.</p>\r\n\r\n<h4>9. Contact Us</h4>\r\n<p>For questions, concerns, or requests relating to this Privacy Policy or your personal data, please reach out through the support channels available on your TELE-CARE patient dashboard.</p>','v1.5','Published','all','2026-09-07','Super Admin','2026-09-07 10:30:07','2026-09-09 13:00:05'),(3,'terms-and-conditions','Terms of Use and Agreement','Terms & Agreements','Rules and conditions for using the platform.','<h4>1. Acceptance of Terms</h4>\n<p>These Terms and Conditions (&quot;Terms&quot;) govern your access to and use of TELE-CARE, operated by TELE-CARE. By creating an account, you agree to be bound by these Terms. If you do not agree, do not use the platform.</p>\n\n<h4>2. Eligibility</h4>\n<p>You must be at least 18 years old to register a patient account. By registering, you represent that the information you provide is accurate, current, and complete, and that you will keep it updated.</p>\n\n<h4>3. Nature of the Service</h4>\n<p>TELE-CARE connects patients with licensed healthcare providers for remote consultations. TELE-CARE is a technology platform and does not itself practice medicine. Medical advice, diagnosis, and treatment are provided solely by the licensed healthcare professionals you consult through the platform.</p>\n\n<h4>4. Not for Emergency Use</h4>\n<p>TELE-CARE is not intended for medical emergencies. If you are experiencing a medical emergency, call your local emergency hotline or go to the nearest emergency room immediately. Do not rely on this platform for time-critical or life-threatening conditions.</p>\n\n<h4>5. Account Responsibilities</h4>\n<ul>\n  <li>You are responsible for maintaining the confidentiality of your login credentials</li>\n  <li>You are responsible for all activity that occurs under your account</li>\n  <li>You must notify us immediately of any unauthorized use of your account</li>\n  <li>Providing false medical or personal information may result in suspension or deactivation of your account</li>\n</ul>\n\n<h4>6. Consultations and Payments</h4>\n<p>Consultation fees, where applicable, will be disclosed prior to booking. Payment terms, cancellation policies, and refund conditions may be presented separately at the time of booking and form part of these Terms by reference.</p>\n\n<h4>7. Prohibited Conduct</h4>\n<p>You agree not to misuse the platform, including but not limited to: impersonating another person, attempting to access another user&#39;s account or medical records, uploading harmful code, or using the platform for any unlawful purpose.</p>\n\n<h4>8. Account Suspension and Termination</h4>\n<p>TELE-CARE reserves the right to suspend or deactivate accounts that violate these Terms, provide fraudulent information, or misuse the platform, with or without prior notice where warranted.</p>\n\n<h4>9. Limitation of Liability</h4>\n<p>To the extent permitted by law, TELE-CARE shall not be liable for indirect, incidental, or consequential damages arising from your use of the platform, technical interruptions, or reliance on information exchanged during a teleconsultation, except as required by applicable law.</p>\n\n<h4>10. Changes to These Terms</h4>\n<p>We may update these Terms from time to time. Continued use of TELE-CARE after changes are posted constitutes acceptance of the revised Terms.</p>\n\n<h4>11. Governing Law</h4>\n<p>These Terms are governed by the laws of the Republic of the Philippines.</p>','v1.1','Published','all','2026-09-07','Super Admin','2026-09-07 10:30:07','2026-09-07 10:30:07'),(4,'payment-policy','Payment Policy','Payments','Policies for payments, refunds, and transactions.','<h3>Overview</h3>\n<p>TELE-CARE is a telehealth platform that connects you with licensed doctors for remote consultations. By using our service, you acknowledge this Payment &amp; Data Handling Policy.</p>\n\n<h3>Information We Collect</h3>\n<ul>\n  <li><strong>Account:</strong> Full name, email, phone, date of birth</li>\n  <li><strong>Medical:</strong> Health records, consultation notes, diagnoses</li>\n  <li><strong>Payment:</strong> Billing details only&mdash;card details are NOT stored by us</li>\n  <li><strong>Communications:</strong> Chat messages, voice and video recordings during consultations</li>\n</ul>\n\n<h3>Voice &amp; Video Recording</h3>\n<div class=\"highlight\">\n  <strong>You consent to voice and video recording during consultations for:</strong>\n  <ul style=\"margin-top:0.6rem;\">\n    <li>Medical record keeping and continuity of care</li>\n    <li>Quality assurance and compliance verification</li>\n    <li>Patient and provider safety</li>\n    <li>AI Summarization of the teleconsultations</li>\n  </ul>\n</div>\n<p style=\"font-size:0.88rem;margin-top:0.8rem;\">Recordings are encrypted and stored securely. Contact support to access your recording.</p>\n\n<h3>Payment Security</h3>\n<p><strong>We use PayMongo for all payments.</strong> Your card details are transmitted directly to PayMongo and are NOT stored on TELE-CARE servers. We only store billing name, email, and payment status for records.</p>\n<p style=\"font-size:0.88rem;\"><em>PayMongo is PCI DSS compliant and handles all sensitive payment data securely.</em></p>\n\n<h3>How We Use Your Information</h3>\n<ul>\n  <li>To provide telehealth services and schedule appointments</li>\n  <li>To process payments securely through PayMongo</li>\n  <li>To maintain medical records and consultation history</li>\n  <li>To send appointment reminders and follow-ups</li>\n  <li>To improve platform security and performance</li>\n  <li>To comply with legal requirements</li>\n</ul>\n\n<h3>Who Can Access Your Information</h3>\n<ul>\n  <li><strong>Your Doctor:</strong> Full access to medical records for treatment only</li>\n  <li><strong>Support Team:</strong> Limited access to billing and account info</li>\n  <li><strong>PayMongo:</strong> Billing details for payment processing only</li>\n  <li><strong>Legal:</strong> Disclosure only as required by law</li>\n</ul>\n<p style=\"font-size:0.88rem;margin-top:0.8rem;\"><em>Your consultation recordings and medical records are NOT shared with third parties without your consent.</em></p>\n\n<h3>Data Security</h3>\n<ul>\n  <li>All data transmitted via TLS/SSL encryption</li>\n  <li>Medical records stored on secure servers with access controls</li>\n  <li>Payment processing delegated to certified PayMongo</li>\n  <li>Consultation sessions require authentication</li>\n</ul>\n\n<h3>Your Rights</h3>\n<ul>\n  <li>Request access to your personal data and medical records</li>\n  <li>Request correction of inaccurate information</li>\n  <li>Request the clinic for deletion of your account at any time</li>\n</ul>\n\n<h3>Contact</h3>\n<p>For questions about this Payment &amp; Data Handling Policy or your data:<br/><strong>telecareteamsystem@gmail.com</strong></p>','v1.0','Published','all','2026-09-07','Super Admin','2026-09-07 10:30:07','2026-09-07 10:30:07');
/*!40000 ALTER TABLE `legal_policies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `legal_policy_versions`
--

DROP TABLE IF EXISTS `legal_policy_versions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `legal_policy_versions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `policy_id` int NOT NULL,
  `version` varchar(20) NOT NULL,
  `content` longtext NOT NULL,
  `status` varchar(20) NOT NULL,
  `revision_notes` text,
  `updated_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `policy_id` (`policy_id`),
  CONSTRAINT `fk_policy_version_policy` FOREIGN KEY (`policy_id`) REFERENCES `legal_policies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `legal_policy_versions`
--

LOCK TABLES `legal_policy_versions` WRITE;
/*!40000 ALTER TABLE `legal_policy_versions` DISABLE KEYS */;
INSERT INTO `legal_policy_versions` VALUES (1,1,'v1.2','<h4>1. Who We Are</h4>\n<p>This Data Privacy Notice is issued by TELE-CARE (&quot;we,&quot; &quot;us,&quot; or &quot;our&quot;) for our telemedicine platform, in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and its Implementing Rules and Regulations.</p>\n\n<h4>2. Personal Data We Collect</h4>\n<p>When you register and use TELE-CARE, we collect:</p>\n<ul>\n  <li>Identity information &mdash; full name, date of birth, contact number, email address</li>\n  <li>Account credentials &mdash; password (stored in encrypted/hashed form)</li>\n  <li>Health information &mdash; medical history, symptoms, consultation notes, prescriptions, lab results, and other information you or your attending doctor provide during a teleconsultation</li>\n  <li>Technical data &mdash; device information, IP address, browser type, and log data related to your use of the platform</li>\n  <li>Communications &mdash; messages, video/audio session metadata, and files exchanged with healthcare providers through the platform</li>\n</ul>\n\n<h4>3. Why We Collect Your Data</h4>\n<p>Your personal and health data are collected and processed to:</p>\n<ul>\n  <li>Create and manage your patient account</li>\n  <li>Facilitate teleconsultations between you and licensed healthcare providers</li>\n  <li>Maintain accurate medical and consultation records</li>\n  <li>Send appointment confirmations, reminders, and account-related notifications</li>\n  <li>Process payments for consultations, where applicable</li>\n  <li>Comply with legal, regulatory, and reporting obligations</li>\n  <li>Improve the safety, security, and functionality of the platform</li>\n</ul>\n\n<h4>4. Sensitive Personal Information</h4>\n<p>Health-related data is classified as &quot;sensitive personal information&quot; under RA 10173. We only process this data with your explicit consent, and access is restricted to your attending healthcare provider, authorized clinic/administrative staff directly involved in your care, and personnel required by law or regulation.</p>\n\n<h4>5. Data Sharing and Disclosure</h4>\n<p>We do not sell your personal data. Your information may only be shared with:</p>\n<ul>\n  <li>Licensed doctors and staff directly involved in your consultation</li>\n  <li>Service providers who support platform operations (e.g., email delivery, secure hosting) under confidentiality obligations</li>\n  <li>Government agencies or regulators, when required by law, court order, or public health reporting requirements</li>\n</ul>\n\n<h4>6. Data Retention</h4>\n<p>Your personal and medical records are retained for as long as your account is active and for a period thereafter as required by applicable healthcare recordkeeping laws and regulations, after which the data will be securely disposed of or anonymized.</p>\n\n<h4>7. Your Rights</h4>\n<p>Under the Data Privacy Act, you have the right to be informed, to access, to object, to correct, to erase or block your data (subject to legal retention requirements), to data portability, and to file a complaint with the National Privacy Commission. To exercise these rights, contact us through the channels provided on your patient dashboard.</p>\n\n<h4>8. Security Measures</h4>\n<p>We apply organizational, physical, and technical safeguards &mdash; including password hashing, encrypted connections, and access controls &mdash; to protect your data against unauthorized access, alteration, disclosure, or destruction.</p>\n\n<h4>9. Consent</h4>\n<p>By creating a TELE-CARE account, you acknowledge that you have read and understood this Data Privacy Notice and consent to the collection, use, and processing of your personal and sensitive personal information as described above.</p>','Published','Initial migration from hardcoded page content.','Super Admin','2026-09-07 10:30:07'),(2,2,'v1.3','<h4>1. Overview</h4>\n<p>This Privacy Policy explains how TELE-CARE handles information collected through our telemedicine platform, in addition to the specific commitments made in our Data Privacy Notice.</p>\n\n<h4>2. Information We Collect Automatically</h4>\n<p>When you use TELE-CARE, our systems may automatically collect device type, browser, IP address, session timestamps, and general usage patterns (e.g., pages visited, features used) to help us maintain and improve the platform.</p>\n\n<h4>3. Cookies and Similar Technologies</h4>\n<p>TELE-CARE may use cookies or similar technologies to keep you logged in, remember your preferences, and understand how the platform is used. You can control cookies through your browser settings, though disabling them may affect platform functionality.</p>\n\n<h4>4. How We Use Information</h4>\n<ul>\n  <li>To operate, maintain, and secure the platform</li>\n  <li>To personalize your experience (e.g., pre-filling known details, showing relevant appointment information)</li>\n  <li>To detect, investigate, and prevent fraudulent or unauthorized activity</li>\n  <li>To analyze aggregate usage trends for service improvement, using de-identified data where possible</li>\n</ul>\n\n<h4>5. Third-Party Services</h4>\n<p>TELE-CARE relies on trusted third-party providers for functions such as email delivery and secure video communication. These providers process data only as necessary to perform their function and are contractually or technically restricted from using your data for unrelated purposes.</p>\n\n<h4>6. Data Storage and International Transfers</h4>\n<p>Your data is stored on secure servers. Where any data is processed or stored outside the Philippines by a service provider, we take reasonable steps to ensure it remains protected to a standard consistent with the Data Privacy Act.</p>\n\n<h4>7. Children&#39;s Privacy</h4>\n<p>TELE-CARE is intended for users 18 years of age and older. We do not knowingly collect personal data from minors through direct patient registration.</p>\n\n<h4>8. Updates to This Policy</h4>\n<p>We may revise this Privacy Policy periodically. Material changes will be communicated through the platform or via email prior to taking effect.</p>\n\n<h4>9. Contact Us</h4>\n<p>For questions, concerns, or requests relating to this Privacy Policy or your personal data, please reach out through the support channels available on your TELE-CARE patient dashboard.</p>','Published','Initial migration from hardcoded page content.','Super Admin','2026-09-07 10:30:07'),(3,3,'v1.1','<h4>1. Acceptance of Terms</h4>\n<p>These Terms and Conditions (&quot;Terms&quot;) govern your access to and use of TELE-CARE, operated by TELE-CARE. By creating an account, you agree to be bound by these Terms. If you do not agree, do not use the platform.</p>\n\n<h4>2. Eligibility</h4>\n<p>You must be at least 18 years old to register a patient account. By registering, you represent that the information you provide is accurate, current, and complete, and that you will keep it updated.</p>\n\n<h4>3. Nature of the Service</h4>\n<p>TELE-CARE connects patients with licensed healthcare providers for remote consultations. TELE-CARE is a technology platform and does not itself practice medicine. Medical advice, diagnosis, and treatment are provided solely by the licensed healthcare professionals you consult through the platform.</p>\n\n<h4>4. Not for Emergency Use</h4>\n<p>TELE-CARE is not intended for medical emergencies. If you are experiencing a medical emergency, call your local emergency hotline or go to the nearest emergency room immediately. Do not rely on this platform for time-critical or life-threatening conditions.</p>\n\n<h4>5. Account Responsibilities</h4>\n<ul>\n  <li>You are responsible for maintaining the confidentiality of your login credentials</li>\n  <li>You are responsible for all activity that occurs under your account</li>\n  <li>You must notify us immediately of any unauthorized use of your account</li>\n  <li>Providing false medical or personal information may result in suspension or deactivation of your account</li>\n</ul>\n\n<h4>6. Consultations and Payments</h4>\n<p>Consultation fees, where applicable, will be disclosed prior to booking. Payment terms, cancellation policies, and refund conditions may be presented separately at the time of booking and form part of these Terms by reference.</p>\n\n<h4>7. Prohibited Conduct</h4>\n<p>You agree not to misuse the platform, including but not limited to: impersonating another person, attempting to access another user&#39;s account or medical records, uploading harmful code, or using the platform for any unlawful purpose.</p>\n\n<h4>8. Account Suspension and Termination</h4>\n<p>TELE-CARE reserves the right to suspend or deactivate accounts that violate these Terms, provide fraudulent information, or misuse the platform, with or without prior notice where warranted.</p>\n\n<h4>9. Limitation of Liability</h4>\n<p>To the extent permitted by law, TELE-CARE shall not be liable for indirect, incidental, or consequential damages arising from your use of the platform, technical interruptions, or reliance on information exchanged during a teleconsultation, except as required by applicable law.</p>\n\n<h4>10. Changes to These Terms</h4>\n<p>We may update these Terms from time to time. Continued use of TELE-CARE after changes are posted constitutes acceptance of the revised Terms.</p>\n\n<h4>11. Governing Law</h4>\n<p>These Terms are governed by the laws of the Republic of the Philippines.</p>','Published','Initial migration from hardcoded page content.','Super Admin','2026-09-07 10:30:07'),(4,4,'v1.0','<h3>Overview</h3>\n<p>TELE-CARE is a telehealth platform that connects you with licensed doctors for remote consultations. By using our service, you acknowledge this Payment &amp; Data Handling Policy.</p>\n\n<h3>Information We Collect</h3>\n<ul>\n  <li><strong>Account:</strong> Full name, email, phone, date of birth</li>\n  <li><strong>Medical:</strong> Health records, consultation notes, diagnoses</li>\n  <li><strong>Payment:</strong> Billing details only&mdash;card details are NOT stored by us</li>\n  <li><strong>Communications:</strong> Chat messages, voice and video recordings during consultations</li>\n</ul>\n\n<h3>Voice &amp; Video Recording</h3>\n<div class=\"highlight\">\n  <strong>You consent to voice and video recording during consultations for:</strong>\n  <ul style=\"margin-top:0.6rem;\">\n    <li>Medical record keeping and continuity of care</li>\n    <li>Quality assurance and compliance verification</li>\n    <li>Patient and provider safety</li>\n    <li>AI Summarization of the teleconsultations</li>\n  </ul>\n</div>\n<p style=\"font-size:0.88rem;margin-top:0.8rem;\">Recordings are encrypted and stored securely. Contact support to access your recording.</p>\n\n<h3>Payment Security</h3>\n<p><strong>We use PayMongo for all payments.</strong> Your card details are transmitted directly to PayMongo and are NOT stored on TELE-CARE servers. We only store billing name, email, and payment status for records.</p>\n<p style=\"font-size:0.88rem;\"><em>PayMongo is PCI DSS compliant and handles all sensitive payment data securely.</em></p>\n\n<h3>How We Use Your Information</h3>\n<ul>\n  <li>To provide telehealth services and schedule appointments</li>\n  <li>To process payments securely through PayMongo</li>\n  <li>To maintain medical records and consultation history</li>\n  <li>To send appointment reminders and follow-ups</li>\n  <li>To improve platform security and performance</li>\n  <li>To comply with legal requirements</li>\n</ul>\n\n<h3>Who Can Access Your Information</h3>\n<ul>\n  <li><strong>Your Doctor:</strong> Full access to medical records for treatment only</li>\n  <li><strong>Support Team:</strong> Limited access to billing and account info</li>\n  <li><strong>PayMongo:</strong> Billing details for payment processing only</li>\n  <li><strong>Legal:</strong> Disclosure only as required by law</li>\n</ul>\n<p style=\"font-size:0.88rem;margin-top:0.8rem;\"><em>Your consultation recordings and medical records are NOT shared with third parties without your consent.</em></p>\n\n<h3>Data Security</h3>\n<ul>\n  <li>All data transmitted via TLS/SSL encryption</li>\n  <li>Medical records stored on secure servers with access controls</li>\n  <li>Payment processing delegated to certified PayMongo</li>\n  <li>Consultation sessions require authentication</li>\n</ul>\n\n<h3>Your Rights</h3>\n<ul>\n  <li>Request access to your personal data and medical records</li>\n  <li>Request correction of inaccurate information</li>\n  <li>Request the clinic for deletion of your account at any time</li>\n</ul>\n\n<h3>Contact</h3>\n<p>For questions about this Payment &amp; Data Handling Policy or your data:<br/><strong>telecareteamsystem@gmail.com</strong></p>','Published','Initial migration from hardcoded page content.','Super Admin','2026-09-07 10:30:07'),(8,1,'v1.3','<h4>1. Who We Are</h4>\r\n<p>This Data Privacy Notice is issued by TELE-CARE (\"we,\" \"us,\" or \"our\") for our telemedicine platform, in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and its Implementing Rules and Regulations. ok</p>\r\n\r\n<h4>2. Personal Data We Collect</h4>\r\n<p>When you register and use TELE-CARE, we collect:</p>\r\n<ul>\r\n  <li>Identity information — full name, date of birth, contact number, email address</li>\r\n  <li>Account credentials — password (stored in encrypted/hashed form)</li>\r\n  <li>Health information — medical history, symptoms, consultation notes, prescriptions, lab results, and other information you or your attending doctor provide during a teleconsultation</li>\r\n  <li>Technical data — device information, IP address, browser type, and log data related to your use of the platform</li>\r\n  <li>Communications — messages, video/audio session metadata, and files exchanged with healthcare providers through the platform</li>\r\n</ul>\r\n\r\n<h4>3. Why We Collect Your Data</h4>\r\n<p>Your personal and health data are collected and processed to:</p>\r\n<ul>\r\n  <li>Create and manage your patient account</li>\r\n  <li>Facilitate teleconsultations between you and licensed healthcare providers</li>\r\n  <li>Maintain accurate medical and consultation records</li>\r\n  <li>Send appointment confirmations, reminders, and account-related notifications</li>\r\n  <li>Process payments for consultations, where applicable</li>\r\n  <li>Comply with legal, regulatory, and reporting obligations</li>\r\n  <li>Improve the safety, security, and functionality of the platform</li>\r\n</ul>\r\n\r\n<h4>4. Sensitive Personal Information</h4>\r\n<p>Health-related data is classified as \"sensitive personal information\" under RA 10173. We only process this data with your explicit consent, and access is restricted to your attending healthcare provider, authorized clinic/administrative staff directly involved in your care, and personnel required by law or regulation.</p>\r\n\r\n<h4>5. Data Sharing and Disclosure</h4>\r\n<p>We do not sell your personal data. Your information may only be shared with:</p>\r\n<ul>\r\n  <li>Licensed doctors and staff directly involved in your consultation</li>\r\n  <li>Service providers who support platform operations (e.g., email delivery, secure hosting) under confidentiality obligations</li>\r\n  <li>Government agencies or regulators, when required by law, court order, or public health reporting requirements</li>\r\n</ul>\r\n\r\n<h4>6. Data Retention</h4>\r\n<p>Your personal and medical records are retained for as long as your account is active and for a period thereafter as required by applicable healthcare recordkeeping laws and regulations, after which the data will be securely disposed of or anonymized.</p>\r\n\r\n<h4>7. Your Rights</h4>\r\n<p>Under the Data Privacy Act, you have the right to be informed, to access, to object, to correct, to erase or block your data (subject to legal retention requirements), to data portability, and to file a complaint with the National Privacy Commission. To exercise these rights, contact us through the channels provided on your patient dashboard.</p>\r\n\r\n<h4>8. Security Measures</h4>\r\n<p>We apply organizational, physical, and technical safeguards — including password hashing, encrypted connections, and access controls — to protect your data against unauthorized access, alteration, disclosure, or destruction.</p>\r\n\r\n<h4>9. Consent</h4>\r\n<p>By creating a TELE-CARE account, you acknowledge that you have read and understood this Data Privacy Notice and consent to the collection, use, and processing of your personal and sensitive personal information as described above.</p>','Published','try','Super Admin','2026-09-07 11:23:17'),(9,1,'v1.4','<h4>1. Who We Are</h4>\r\n<p>This Data Privacy Notice is issued by TELE-CARE (\"we,\" \"us,\" or \"our\") for our telemedicine platform, in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and its Implementing Rules and Regulations.</p>\r\n\r\n<h4>2. Personal Data We Collect</h4>\r\n<p>When you register and use TELE-CARE, we collect:</p>\r\n<ul>\r\n  <li>Identity information — full name, date of birth, contact number, email address</li>\r\n  <li>Account credentials — password (stored in encrypted/hashed form)</li>\r\n  <li>Health information — medical history, symptoms, consultation notes, prescriptions, lab results, and other information you or your attending doctor provide during a teleconsultation</li>\r\n  <li>Technical data — device information, IP address, browser type, and log data related to your use of the platform</li>\r\n  <li>Communications — messages, video/audio session metadata, and files exchanged with healthcare providers through the platform</li>\r\n</ul>\r\n\r\n<h4>3. Why We Collect Your Data</h4>\r\n<p>Your personal and health data are collected and processed to:</p>\r\n<ul>\r\n  <li>Create and manage your patient account</li>\r\n  <li>Facilitate teleconsultations between you and licensed healthcare providers</li>\r\n  <li>Maintain accurate medical and consultation records</li>\r\n  <li>Send appointment confirmations, reminders, and account-related notifications</li>\r\n  <li>Process payments for consultations, where applicable</li>\r\n  <li>Comply with legal, regulatory, and reporting obligations</li>\r\n  <li>Improve the safety, security, and functionality of the platform</li>\r\n</ul>\r\n\r\n<h4>4. Sensitive Personal Information</h4>\r\n<p>Health-related data is classified as \"sensitive personal information\" under RA 10173. We only process this data with your explicit consent, and access is restricted to your attending healthcare provider, authorized clinic/administrative staff directly involved in your care, and personnel required by law or regulation.</p>\r\n\r\n<h4>5. Data Sharing and Disclosure</h4>\r\n<p>We do not sell your personal data. Your information may only be shared with:</p>\r\n<ul>\r\n  <li>Licensed doctors and staff directly involved in your consultation</li>\r\n  <li>Service providers who support platform operations (e.g., email delivery, secure hosting) under confidentiality obligations</li>\r\n  <li>Government agencies or regulators, when required by law, court order, or public health reporting requirements</li>\r\n</ul>\r\n\r\n<h4>6. Data Retention</h4>\r\n<p>Your personal and medical records are retained for as long as your account is active and for a period thereafter as required by applicable healthcare recordkeeping laws and regulations, after which the data will be securely disposed of or anonymized.</p>\r\n\r\n<h4>7. Your Rights</h4>\r\n<p>Under the Data Privacy Act, you have the right to be informed, to access, to object, to correct, to erase or block your data (subject to legal retention requirements), to data portability, and to file a complaint with the National Privacy Commission. To exercise these rights, contact us through the channels provided on your patient dashboard.</p>\r\n\r\n<h4>8. Security Measures</h4>\r\n<p>We apply organizational, physical, and technical safeguards — including password hashing, encrypted connections, and access controls — to protect your data against unauthorized access, alteration, disclosure, or destruction.</p>\r\n\r\n<h4>9. Consent</h4>\r\n<p>By creating a TELE-CARE account, you acknowledge that you have read and understood this Data Privacy Notice and consent to the collection, use, and processing of your personal and sensitive personal information as described above.</p>','Published','done testing','Super Admin','2026-09-07 11:23:47'),(10,2,'v1.4','<h4>1. Overview</h4>\r\n<p>This Privacy Policy explains how TELE-CARE handles information collected through our telemedicine platform, in addition to the specific commitments made in our Data Privacy Notice. ok</p>\r\n\r\n<h4>2. Information We Collect Automatically</h4>\r\n<p>When you use TELE-CARE, our systems may automatically collect device type, browser, IP address, session timestamps, and general usage patterns (e.g., pages visited, features used) to help us maintain and improve the platform.</p>\r\n\r\n<h4>3. Cookies and Similar Technologies</h4>\r\n<p>TELE-CARE may use cookies or similar technologies to keep you logged in, remember your preferences, and understand how the platform is used. You can control cookies through your browser settings, though disabling them may affect platform functionality.</p>\r\n\r\n<h4>4. How We Use Information</h4>\r\n<ul>\r\n  <li>To operate, maintain, and secure the platform</li>\r\n  <li>To personalize your experience (e.g., pre-filling known details, showing relevant appointment information)</li>\r\n  <li>To detect, investigate, and prevent fraudulent or unauthorized activity</li>\r\n  <li>To analyze aggregate usage trends for service improvement, using de-identified data where possible</li>\r\n</ul>\r\n\r\n<h4>5. Third-Party Services</h4>\r\n<p>TELE-CARE relies on trusted third-party providers for functions such as email delivery and secure video communication. These providers process data only as necessary to perform their function and are contractually or technically restricted from using your data for unrelated purposes.</p>\r\n\r\n<h4>6. Data Storage and International Transfers</h4>\r\n<p>Your data is stored on secure servers. Where any data is processed or stored outside the Philippines by a service provider, we take reasonable steps to ensure it remains protected to a standard consistent with the Data Privacy Act.</p>\r\n\r\n<h4>7. Children\'s Privacy</h4>\r\n<p>TELE-CARE is intended for users 18 years of age and older. We do not knowingly collect personal data from minors through direct patient registration.</p>\r\n\r\n<h4>8. Updates to This Policy</h4>\r\n<p>We may revise this Privacy Policy periodically. Material changes will be communicated through the platform or via email prior to taking effect.</p>\r\n\r\n<h4>9. Contact Us</h4>\r\n<p>For questions, concerns, or requests relating to this Privacy Policy or your personal data, please reach out through the support channels available on your TELE-CARE patient dashboard.</p>','Published','test','Super Admin','2026-09-09 12:55:15'),(11,2,'v1.5','<h4>1. Overview</h4>\r\n<p>This Privacy Policy explains how TELE-CARE handles information collected through our telemedicine platform, in addition to the specific commitments made in our Data Privacy Notice.&nbsp;</p>\r\n\r\n<h4>2. Information We Collect Automatically</h4>\r\n<p>When you use TELE-CARE, our systems may automatically collect device type, browser, IP address, session timestamps, and general usage patterns (e.g., pages visited, features used) to help us maintain and improve the platform.</p>\r\n\r\n<h4>3. Cookies and Similar Technologies</h4>\r\n<p>TELE-CARE may use cookies or similar technologies to keep you logged in, remember your preferences, and understand how the platform is used. You can control cookies through your browser settings, though disabling them may affect platform functionality.</p>\r\n\r\n<h4>4. How We Use Information</h4>\r\n<ul>\r\n  <li>To operate, maintain, and secure the platform</li>\r\n  <li>To personalize your experience (e.g., pre-filling known details, showing relevant appointment information)</li>\r\n  <li>To detect, investigate, and prevent fraudulent or unauthorized activity</li>\r\n  <li>To analyze aggregate usage trends for service improvement, using de-identified data where possible</li>\r\n</ul>\r\n\r\n<h4>5. Third-Party Services</h4>\r\n<p>TELE-CARE relies on trusted third-party providers for functions such as email delivery and secure video communication. These providers process data only as necessary to perform their function and are contractually or technically restricted from using your data for unrelated purposes.</p>\r\n\r\n<h4>6. Data Storage and International Transfers</h4>\r\n<p>Your data is stored on secure servers. Where any data is processed or stored outside the Philippines by a service provider, we take reasonable steps to ensure it remains protected to a standard consistent with the Data Privacy Act.</p>\r\n\r\n<h4>7. Children\'s Privacy</h4>\r\n<p>TELE-CARE is intended for users 18 years of age and older. We do not knowingly collect personal data from minors through direct patient registration.</p>\r\n\r\n<h4>8. Updates to This Policy</h4>\r\n<p>We may revise this Privacy Policy periodically. Material changes will be communicated through the platform or via email prior to taking effect.</p>\r\n\r\n<h4>9. Contact Us</h4>\r\n<p>For questions, concerns, or requests relating to this Privacy Policy or your personal data, please reach out through the support channels available on your TELE-CARE patient dashboard.</p>','Published','test','Super Admin','2026-09-09 13:00:05');
/*!40000 ALTER TABLE `legal_policy_versions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sender_type` enum('patient','doctor') NOT NULL,
  `sender_id` int NOT NULL,
  `receiver_type` enum('patient','doctor') NOT NULL,
  `receiver_id` int NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `messages`
--

LOCK TABLES `messages` WRITE;
/*!40000 ALTER TABLE `messages` DISABLE KEYS */;
INSERT INTO `messages` VALUES (1,'doctor',4,'patient',1,'0',1,'2026-03-14 07:16:19'),(2,'doctor',4,'patient',1,'0',1,'2026-03-14 07:16:30'),(3,'doctor',4,'patient',1,'Hello',1,'2026-03-14 07:17:54'),(4,'patient',1,'doctor',4,'Hello doc',1,'2026-03-14 07:20:37'),(5,'doctor',4,'patient',5,'How are you?',1,'2026-03-15 08:39:36');
/*!40000 ALTER TABLE `messages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reference_type` varchar(30) DEFAULT NULL,
  `reference_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_notif` (`patient_id`,`type`,`reference_id`),
  KEY `idx_patient_read` (`patient_id`,`is_read`),
  KEY `idx_patient_created` (`patient_id`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=3074 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notifications`
--

LOCK TABLES `notifications` WRITE;
/*!40000 ALTER TABLE `notifications` DISABLE KEYS */;
INSERT INTO `notifications` VALUES (1,1,'appointment_approved','Appointment Approved','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 5, 2026 at 12:01 AM has been confirmed.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',10),(2,1,'appointment_reminder','Appointment Reminder','You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 12:01 AM.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',10),(3,1,'appointment_approved','Appointment Approved','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 5, 2026 at 12:00 AM has been confirmed.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',9),(4,1,'appointment_reminder','Appointment Reminder','You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 12:00 AM.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',9),(5,1,'appointment_approved','Appointment Approved','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:59 PM has been confirmed.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',8),(6,1,'appointment_reminder','Appointment Reminder','You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 11:59 PM.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',8),(7,1,'appointment_approved','Appointment Approved','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:59 PM has been confirmed.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',7),(8,1,'appointment_reminder','Appointment Reminder','You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 11:59 PM.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',7),(9,1,'appointment_approved','Appointment Approved','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:59 PM has been confirmed.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',6),(10,1,'appointment_reminder','Appointment Reminder','You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 11:59 PM.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',6),(11,1,'appointment_approved','Appointment Approved','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:58 PM has been confirmed.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',5),(12,1,'appointment_reminder','Appointment Reminder','You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 11:58 PM.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',5),(13,1,'appointment_cancelled','Appointment Cancelled','Your appointment with Dr. EXAMPLE DOCTOR#1 on Aug 23, 2026 at 5:00 PM was cancelled.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',4),(14,1,'appointment_cancelled','Appointment Cancelled','Your appointment with Dr. EXAMPLE DOCTOR#1 on Aug 22, 2026 at 10:30 PM was cancelled.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',3),(15,1,'appointment_cancelled','Appointment Cancelled','Your appointment with Dr. EXAMPLE DOCTOR#1 on Aug 22, 2026 at 9:30 PM was cancelled.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',2),(16,1,'appointment_approved','Appointment Approved','Your appointment with Dr. EXAMPLE DOCTOR#1 on Aug 22, 2026 at 5:00 PM has been confirmed.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',1),(17,1,'appointment_missed','Missed Appointment','Your appointment with Dr. EXAMPLE DOCTOR#1 on Aug 22, 2026 at 5:00 PM was missed.','router.php?page=visits',1,'2026-09-04 16:21:59','appointment',1),(36,1,'appointment_missed','Missed Appointment','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 5, 2026 at 12:01 AM was missed.','router.php?page=visits',1,'2026-09-04 16:24:31','appointment',10),(38,1,'appointment_missed','Missed Appointment','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 5, 2026 at 12:00 AM was missed.','router.php?page=visits',1,'2026-09-04 16:24:31','appointment',9),(40,1,'appointment_missed','Missed Appointment','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:59 PM was missed.','router.php?page=visits',1,'2026-09-04 16:24:31','appointment',8),(42,1,'appointment_missed','Missed Appointment','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:59 PM was missed.','router.php?page=visits',1,'2026-09-04 16:24:31','appointment',7),(44,1,'appointment_missed','Missed Appointment','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:59 PM was missed.','router.php?page=visits',1,'2026-09-04 16:24:31','appointment',6),(46,1,'appointment_missed','Missed Appointment','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:58 PM was missed.','router.php?page=visits',1,'2026-09-04 16:24:31','appointment',5),(460,1,'appointment_approved','Appointment Approved','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 9, 2026 at 4:30 PM has been confirmed.','router.php?page=visits',1,'2026-09-08 13:40:35','appointment',11),(461,1,'appointment_reminder','Appointment Reminder','You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 4:30 PM.','router.php?page=visits',1,'2026-09-08 13:40:35','appointment',11),(612,1,'consultation_completed','Consultation Completed','Your consultation with Dr. EXAMPLE DOCTOR#1 on Sep 9, 2026 has been completed.','router.php?page=visits',1,'2026-09-08 17:20:20','appointment',11),(1476,1,'appointment_approved','Appointment Approved','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 9, 2026 at 10:30 PM has been confirmed.','router.php?page=visits',1,'2026-09-09 13:35:54','appointment',12),(1477,1,'appointment_reminder','Appointment Reminder','You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 10:30 PM.','router.php?page=visits',1,'2026-09-09 13:35:54','appointment',12),(2258,1,'consultation_soon','Consultation Starting Soon','Your consultation with Dr. EXAMPLE DOCTOR#1 starts at 10:30 PM.','router.php?page=visits',0,'2026-09-09 14:04:34','appointment',12),(2562,1,'appointment_missed','Missed Appointment','Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 9, 2026 at 10:30 PM was missed.','router.php?page=visits',0,'2026-09-09 14:49:25','appointment',12),(2821,1,'appointment_request','Appointment Request Submitted','Your request for an appointment with Dr. EXAMPLE DOCTOR#1 on Sep 11, 2026 at 1:00 PM is pending confirmation.','router.php?page=visits',0,'2026-09-09 14:54:57','appointment',13),(3073,1,'instant_call','Incoming Instant Call','Dr. EXAMPLE DOCTOR#1 started an instant video consultation with you. Tap to join.','router.php?page=visits',0,'2026-09-10 12:30:44','appointment',14);
/*!40000 ALTER TABLE `notifications` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `patients`
--

DROP TABLE IF EXISTS `patients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `patients` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` varchar(20) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `emergency_name` varchar(50) DEFAULT NULL,
  `emergency_relationship` varchar(20) DEFAULT NULL,
  `emergency_number` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `security_question` varchar(100) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL,
  `home_address` varchar(100) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `country_region` varchar(50) DEFAULT NULL,
  `insurance_provider` varchar(20) DEFAULT NULL,
  `insurance_policy_no` varchar(20) DEFAULT NULL,
  `preferred_language` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `verification_token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `remember_selector` varchar(32) DEFAULT NULL,
  `remember_validator_hash` varchar(64) DEFAULT NULL,
  `remember_expires` datetime DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `notif_appointment_reminders` tinyint(1) NOT NULL DEFAULT '1',
  `notif_payment_notifications` tinyint(1) NOT NULL DEFAULT '1',
  `notif_medical_record_updates` tinyint(1) NOT NULL DEFAULT '1',
  `account_status` varchar(20) NOT NULL DEFAULT 'active',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patients`
--

LOCK TABLES `patients` WRITE;
/*!40000 ALTER TABLE `patients` DISABLE KEYS */;
INSERT INTO `patients` VALUES (1,'John Noel Oraño','2004-12-10','Male','johnnoelorano@gmail.com','09923139504',NULL,'uploads/profiles/patient_69ad35693609a.png','','','','$2y$10$HDx76CM.oTMDTehDq.Rwmu16LHCvP.rpz5WHuK3cx/S1ufdeLa5ka','',NULL,'NPC KANAN MAKISIG STREET BARANGAY 171','CALOOCAN CITY','Philippines','','','English','2026-03-08 06:11:37','2026-09-09 15:33:49',1,NULL,NULL,'a192bfa68c01c8c184036738739167867708f4d54f374ccea90a07346441a8e8','2026-09-09 21:45:04',1,NULL,NULL,NULL,0,1,1,1,'active'),(5,'Cid Kagenou','2011-03-01','Male','cidkag1210@gmail.com','09123456789',NULL,'uploads/profiles/patient_69b66c6f61c19.png','','','','$2y$10$X.uZM7Zu/WPogMFGiCEIjOIDyRQYm.7vchIVgaamtNEZgdaQPjByO','',NULL,'Hillcrest Village Gate 1','Caloocan City','Philippines','','','English','2026-03-15 08:00:28','2026-03-29 18:50:03',1,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,0,1,1,1,'active'),(6,'John Nowel','2008-04-03','Male','sumivalo10@gmail.com','+639923139504',NULL,NULL,'','','','$2y$10$z3EWoneDOMdsJezI/0cgBOvGTgWjbPwYxSLOE0SgoZhOY5dHq46YG','',NULL,'','City of Caloocan','Philippines','','','English','2026-04-04 07:07:34','2026-04-04 07:18:53',0,'9d01ef1f345fbe20b2875603736c41a22457ab33fba251d3182fe89238061f0b','2026-04-05 09:18:53',NULL,NULL,1,NULL,NULL,NULL,0,1,1,1,'active'),(7,'Jerson Sagun','2008-08-09','Male','jersonsagun@gmail.com','+639923139504',NULL,NULL,'','','','$2y$10$h4kFa3vluW0FOuw0v6yNseqhRWMjTDu8Xug5pCBimowS21FLLaQUO','',NULL,'','City of Caloocan','Philippines','','','English','2026-08-10 08:17:30','2026-08-10 08:17:30',0,'1d9162f9963ac2cef5c6451c0c2b93f320565d409d8da6f37a026f1d22880a5f','2026-08-11 10:17:30',NULL,NULL,1,NULL,NULL,NULL,0,1,1,1,'active'),(8,'Jerson Uanan Sagun','2004-10-15',NULL,'sagun.jersonbsis2023@gmail.com','+639602059511',NULL,NULL,NULL,NULL,NULL,'$2y$10$WefO41SasPK3Pws2u5oHoeC3r3r7q3.8E5.4ORgVoIG.gaKpp9pWy',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-14 11:23:03','2026-08-14 11:23:19',1,NULL,NULL,NULL,NULL,1,NULL,NULL,NULL,0,1,1,1,'active');
/*!40000 ALTER TABLE `patients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pos_sale_items`
--

DROP TABLE IF EXISTS `pos_sale_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_sale_items` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sale_id` int NOT NULL,
  `service_id` int NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `quantity` int NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sale_id` (`sale_id`),
  KEY `service_id` (`service_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pos_sale_items`
--

LOCK TABLES `pos_sale_items` WRITE;
/*!40000 ALTER TABLE `pos_sale_items` DISABLE KEYS */;
INSERT INTO `pos_sale_items` VALUES (1,1,8,'sda',1,222.00,222.00);
/*!40000 ALTER TABLE `pos_sale_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pos_sales`
--

DROP TABLE IF EXISTS `pos_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pos_sales` (
  `id` int NOT NULL AUTO_INCREMENT,
  `staff_id` int NOT NULL,
  `patient_name` varchar(150) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `staff_id` (`staff_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pos_sales`
--

LOCK TABLES `pos_sales` WRITE;
/*!40000 ALTER TABLE `pos_sales` DISABLE KEYS */;
INSERT INTO `pos_sales` VALUES (1,4,NULL,222.00,'2026-08-24 21:09:18');
/*!40000 ALTER TABLE `pos_sales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `prescriptions`
--

DROP TABLE IF EXISTS `prescriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `prescriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `medication_name` varchar(150) NOT NULL,
  `dosage` varchar(80) DEFAULT NULL,
  `frequency` varchar(80) DEFAULT NULL,
  `refills_remaining` int DEFAULT '0',
  `prescribed_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` text,
  `status` enum('Active','Expired','Cancelled') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `prescriptions`
--

LOCK TABLES `prescriptions` WRITE;
/*!40000 ALTER TABLE `prescriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `prescriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `products` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `category` enum('Medicine','Testing Kits') NOT NULL,
  `description` text,
  `unit` enum('Tablet','Capsule','Bottle','Box','Vial','Piece','Pack','Syrup') NOT NULL DEFAULT 'Piece',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `reorder_level` int NOT NULL DEFAULT '0',
  `stock_quantity` int NOT NULL DEFAULT '0',
  `status` enum('Active','Archived') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'Paracetamol 500mg','Medicine','Pain reliever / fever reducer','Tablet',10.00,20,120,'Active','2026-08-22 15:33:51','2026-08-22 15:33:51'),(2,'Amoxicillin 500mg','Medicine','Antibiotic capsule','Capsule',25.00,15,50,'Active','2026-08-22 15:33:51','2026-08-22 15:33:51'),(3,'Vitamin C 500mg','Medicine','Immune support supplement','Tablet',15.00,10,80,'Active','2026-08-22 15:33:51','2026-08-23 13:02:48'),(4,'Digital Thermometer','Medicine','Non-contact infrared thermometer','Piece',350.00,5,12,'Active','2026-08-22 15:33:51','2026-08-23 13:02:48'),(5,'Cough Syrup 60ml','Medicine','Relief for dry and wet cough','Bottle',85.00,8,3,'Active','2026-08-22 15:33:51','2026-08-22 15:33:51'),(26,'Urine Specimen Container','Testing Kits','Sterile container for urinalysis specimen collection.','Piece',10.00,20,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(27,'Stool Specimen Container','Testing Kits','Container for fecalysis (stool) specimen collection.','Piece',10.00,20,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(28,'Fecal Occult Blood Test Stick','Testing Kits','Stick used for fecal occult blood testing.','Piece',15.00,20,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(29,'Pregnancy Test Kit (Strip)','Testing Kits','Urine pregnancy test strip.','Piece',25.00,15,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(30,'Blood Glucose Test Strip','Testing Kits','Test strip for blood glucose monitoring.','Piece',20.00,30,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(31,'Lancet','Testing Kits','Sterile lancet for capillary blood sampling.','Piece',3.00,50,99,'Active','2026-08-23 14:10:22','2026-08-24 13:09:18'),(32,'Cotton Swab (Sterile)','Testing Kits','Sterile cotton swab for specimen collection.','Piece',2.00,50,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(33,'Alcohol Swab','Testing Kits','Alcohol prep pad for skin disinfection before sampling.','Piece',1.50,50,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(34,'Vacutainer Blood Collection Tube','Testing Kits','Tube for venous blood sample collection.','Piece',12.00,30,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(35,'Rapid Antigen Test Kit','Testing Kits','Rapid antigen test kit for infectious disease screening.','Box',150.00,10,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `receipt_settings`
--

DROP TABLE IF EXISTS `receipt_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `receipt_settings` (
  `id` int NOT NULL DEFAULT '1',
  `clinic_name` varchar(150) NOT NULL DEFAULT 'ExcellCare Medical System Inc.',
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `tin` varchar(30) DEFAULT NULL,
  `footer_note` varchar(255) DEFAULT 'Thank you for choosing ExcellCare!',
  `show_logo` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `chk_single_row` CHECK ((`id` = 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `receipt_settings`
--

LOCK TABLES `receipt_settings` WRITE;
/*!40000 ALTER TABLE `receipt_settings` DISABLE KEYS */;
INSERT INTO `receipt_settings` VALUES (1,'ExcellCare Medical System Inc.','123 Sample St., Quezon City','(02) 8888-0000','000-000-000-000','Thank you for choosing ExcellCare!',1,'2026-08-23 11:28:41');
/*!40000 ALTER TABLE `receipt_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `service_requirements`
--

DROP TABLE IF EXISTS `service_requirements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `service_requirements` (
  `id` int NOT NULL AUTO_INCREMENT,
  `service_id` int NOT NULL,
  `product_id` int NOT NULL,
  `quantity_used` int NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_service_product` (`service_id`,`product_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `service_requirements_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `service_requirements_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `service_requirements`
--

LOCK TABLES `service_requirements` WRITE;
/*!40000 ALTER TABLE `service_requirements` DISABLE KEYS */;
INSERT INTO `service_requirements` VALUES (1,1,1,2,'2026-08-23 11:25:58'),(5,8,31,1,'2026-08-23 14:12:02'),(6,5,26,1,'2026-08-23 14:12:30'),(7,2,26,1,'2026-08-23 14:54:45');
/*!40000 ALTER TABLE `service_requirements` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `services`
--

DROP TABLE IF EXISTS `services`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `services` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `category` enum('Laboratory','X-ray','Chemical','Consultation','Other') NOT NULL DEFAULT 'Other',
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `services`
--

LOCK TABLES `services` WRITE;
/*!40000 ALTER TABLE `services` DISABLE KEYS */;
INSERT INTO `services` VALUES (1,'CBC Test','Complete Blood Count test',350.00,'Laboratory','Active','2026-08-23 11:25:58','2026-08-23 11:25:58'),(2,'Urinalysis','Routine urine test',150.00,'Laboratory','Active','2026-08-23 11:25:58','2026-08-23 11:25:58'),(3,'X-Ray Imaging','Standard single-view X-ray',320.00,'X-ray','Active','2026-08-23 11:25:58','2026-08-23 11:25:58'),(4,'General Consultation','Walk-in consultation with a physician',500.00,'Consultation','Active','2026-08-23 11:25:58','2026-08-29 07:32:37'),(5,'asda','asdada',222.00,'Laboratory','Inactive','2026-08-23 12:54:32','2026-08-29 07:33:06'),(6,'SADA','DASDSAD',222.00,'Laboratory','Active','2026-08-23 13:09:23','2026-08-23 13:09:23'),(7,'sdada','adad',222.00,'Laboratory','Active','2026-08-23 14:07:43','2026-08-23 14:07:43'),(8,'sda','adas',222.00,'Laboratory','Active','2026-08-23 14:11:57','2026-08-23 14:11:57');
/*!40000 ALTER TABLE `services` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `staff_accounts`
--

DROP TABLE IF EXISTS `staff_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `staff_accounts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `role` varchar(50) NOT NULL DEFAULT 'staff',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `staff_accounts`
--

LOCK TABLES `staff_accounts` WRITE;
/*!40000 ALTER TABLE `staff_accounts` DISABLE KEYS */;
INSERT INTO `staff_accounts` VALUES (4,'TELE-CARE Staff','staff@telecare.com','$2y$10$YnlzuMjQm2ntktIn7faJ9eOWpWDQwGxNhLOk5hmquxEvebnJ/Ko7G','active','staff','2026-03-22 15:47:28');
/*!40000 ALTER TABLE `staff_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `super_admins`
--

DROP TABLE IF EXISTS `super_admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `super_admins` (
  `id` int NOT NULL AUTO_INCREMENT,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `super_admins`
--

LOCK TABLES `super_admins` WRITE;
/*!40000 ALTER TABLE `super_admins` DISABLE KEYS */;
INSERT INTO `super_admins` VALUES (1,'Super Admin','superadmin@telecare.com','$2y$10$qHQuthKpG4iEn0iENp2s2e0wweLna5Oii.Y.Sb8VoykbampPFd2FG','2026-09-06 14:25:10');
/*!40000 ALTER TABLE `super_admins` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-10 23:01:11
