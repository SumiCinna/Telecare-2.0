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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (1,'APT-2026-94856',1,4,'2026-08-22','17:00:00','Teleconsult','General Medicine','Confirmed','Fever, Headache','Fever, Headache',NULL,NULL,NULL,'Paid','2026-08-22 14:13:57','src_h85hrYVXAV4Wi4thHsTLh5Mu','TC-9BC7F0F1','2026-08-22 22:17:16',NULL,NULL,NULL,NULL,NULL,0,NULL),(2,'APT-2026-15039',1,4,'2026-08-22','21:30:00','Teleconsult','General Medicine','Cancelled','Fever, Cough / Cold','Fever, Cough / Cold',NULL,NULL,NULL,'Unpaid','2026-08-22 14:13:57',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL),(3,'APT-2026-59332',1,4,'2026-08-22','22:30:00','Teleconsult','General Medicine','Cancelled','Fever, Headache','Fever, Headache',NULL,NULL,NULL,'Unpaid','2026-08-22 14:13:57',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL),(4,'APT-2026-08891',1,4,'2026-08-23','17:00:00','Teleconsult','General Medicine','Cancelled','Fever, Headache','Fever, Headache',NULL,NULL,NULL,'Unpaid','2026-08-22 14:23:52',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL);
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
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `audit_logs`
--

LOCK TABLES `audit_logs` WRITE;
/*!40000 ALTER TABLE `audit_logs` DISABLE KEYS */;
INSERT INTO `audit_logs` VALUES (1,1,'update','doctor',5,'{\"email\": \"dizoah3@gmail.com\", \"full_name\": \"Ian Matthew Payawal\"}','{\"email\": \"dizoah3@gmail.com\", \"full_name\": \"Ian Matthew Payawal\"}','2026-08-20 00:32:09'),(2,1,'update','staff',4,'{\"email\": \"staff@telecare.com\", \"full_name\": \"TELE-CARE Staff\"}','{\"email\": \"staff@telecare.com\", \"full_name\": \"TELE-CARE StaffS\"}','2026-08-20 00:41:26'),(3,1,'update','staff',4,'{\"email\": \"staff@telecare.com\", \"full_name\": \"TELE-CARE StaffS\"}','{\"email\": \"staff@telecare.com\", \"full_name\": \"TELE-CARE Staff\"}','2026-08-20 00:41:35'),(4,1,'update','doctor',4,'{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"Ma. Aberlee Lacanaria\"}','{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#1\"}','2026-08-20 00:42:12'),(5,1,'create','doctor',6,NULL,'{\"email\": \"xdreiaawe@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#2\", \"specialty\": \"General\", \"subspecialty\": \"\"}','2026-08-20 14:20:01'),(6,1,'update','doctor',4,'{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#1\"}','{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#1\"}','2026-08-21 16:53:37'),(7,1,'toggle','doctor',6,'{\"status\": \"active\"}','{\"status\": \"inactive\"}','2026-08-21 16:55:15'),(8,1,'toggle','doctor',6,'{\"status\": \"inactive\"}','{\"status\": \"active\"}','2026-08-21 16:55:15'),(9,1,'create','service',5,NULL,'{\"name\": \"asda\", \"price\": \"222\", \"category\": \"Laboratory\"}','2026-08-23 20:54:32'),(10,1,'set_requirement','service',5,NULL,'{\"product_id\": 2, \"quantity_used\": 1}','2026-08-23 20:54:43'),(11,1,'set_requirement','service',5,NULL,'{\"product_id\": 5, \"quantity_used\": 1}','2026-08-23 20:54:46'),(12,1,'remove_requirement','service',5,'{\"requirement_id\": 3}',NULL,'2026-08-23 20:56:06'),(13,1,'remove_requirement','service',5,'{\"requirement_id\": 4}',NULL,'2026-08-23 20:56:07'),(14,1,'create','service',6,NULL,'{\"name\": \"SADA\", \"price\": \"222\", \"category\": \"Laboratory\"}','2026-08-23 21:09:23'),(15,1,'create','service',7,NULL,'{\"name\": \"sdada\", \"price\": \"222\", \"category\": \"Laboratory\"}','2026-08-23 22:07:43'),(16,1,'create','service',8,NULL,'{\"name\": \"sda\", \"price\": \"222\", \"category\": \"Laboratory\"}','2026-08-23 22:11:57'),(17,1,'set_requirement','service',8,NULL,'{\"product_id\": 31, \"quantity_used\": 1}','2026-08-23 22:12:02'),(18,1,'archive','service',5,'{\"status\": \"Active\"}','{\"status\": \"Archived\"}','2026-08-23 22:12:18'),(19,1,'set_requirement','service',5,NULL,'{\"product_id\": 26, \"quantity_used\": 1}','2026-08-23 22:12:30'),(20,1,'remove_requirement','service',2,'{\"requirement_id\": 2}',NULL,'2026-08-23 22:54:40'),(21,1,'set_requirement','service',2,NULL,'{\"product_id\": 26, \"quantity_used\": 1}','2026-08-23 22:54:45');
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
) ENGINE=InnoDB AUTO_INCREMENT=150 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctor_schedules`
--

LOCK TABLES `doctor_schedules` WRITE;
/*!40000 ALTER TABLE `doctor_schedules` DISABLE KEYS */;
INSERT INTO `doctor_schedules` VALUES (136,5,'Monday','06:00:00','13:00:00'),(137,5,'Monday','15:00:00','19:00:00'),(138,5,'Friday','06:00:00','14:00:00'),(139,5,'Saturday','06:00:00','14:00:00'),(140,4,'Monday','00:00:00','06:00:00'),(141,4,'Monday','14:30:00','19:30:00'),(142,4,'Monday','22:30:00','23:30:00'),(143,4,'Tuesday','14:30:00','19:30:00'),(144,4,'Wednesday','14:30:00','19:30:00'),(145,4,'Wednesday','22:30:00','23:30:00'),(146,4,'Thursday','00:00:00','23:30:00'),(147,4,'Saturday','17:00:00','23:00:00'),(148,4,'Sunday','17:00:00','20:30:00'),(149,4,'Friday','13:00:00','22:00:00');
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
INSERT INTO `doctors` VALUES (4,'EXAMPLE DOCTOR#1','General','General Medicine','','EXCELLCARE MEDICAL SYSTEM INC.','almondtofu25@gmail.com','$2y$10$KFzfCMLW/6YVdUWjiYG8b.g0yyUP6ZAW24jy1H7DrbopyR.btv15a','09999999999','uploads/profiles/doc_69b50c372d091.jfif','General doctor at EXCELLCARE MEDICAL SYSTEM INC.',500.00,'Filipino',0.0,'senior','active',1,'','',NULL,NULL,1,1,'2026-03-14 00:39:41',1,NULL,'2026-03-21 07:46:05',1,'2026-03-14 06:46:05','2026-08-21 13:57:01'),(5,'Ian Matthew Payawal','General','Cardiology','Cardiology','TELE-CARE STAFF','dizoah3@gmail.com','$2y$10$/WhcdL6CDFFo7M2w0ynJ4eq0kPxJ.Wnn/Nn4VhHl7.6evTz5DykqW','09999999992','uploads/profiles/doc_69bd4ead3abc2.png','Cardiology subspecialty',750.00,'English, Filipino',0.0,'junior','active',1,'','','uploads/docs/doc_5_69d7b40d58257.exe',NULL,1,0,'2026-04-09 08:13:33',1,NULL,'2026-03-22 11:33:12',1,'2026-03-15 10:33:12','2026-08-21 13:57:01'),(6,'EXAMPLE DOCTOR#2','General','Pediatrics','',NULL,'xdreiaawe@gmail.com','$2y$10$0uhxv9h108ktfIiuuNvE9.IfYwnWXZNm958koGuybGGE.Mp1Kug36',NULL,NULL,NULL,0.00,NULL,0.0,'junior','active',1,NULL,NULL,NULL,NULL,0,0,NULL,NULL,NULL,NULL,1,'2026-08-20 06:20:01','2026-08-21 13:57:19');
/*!40000 ALTER TABLE `doctors` ENABLE KEYS */;
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
  `doc_type` enum('lab_result','prescription','unknown') DEFAULT 'unknown',
  `doc_label` varchar(200) DEFAULT NULL,
  `extracted_text` longtext,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  CONSTRAINT `lab_results_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_results`
--

LOCK TABLES `lab_results` WRITE;
/*!40000 ALTER TABLE `lab_results` DISABLE KEYS */;
INSERT INTO `lab_results` VALUES (1,1,'uploads/ocr/ocr_69aee8a9b1de2.pdf','prescription','Prescription 1','--- Page 1 ---\nTELE-CARE Medical Clinic\n123 Mabini Street, Caloocan City, Metro Manila | Tel: (02) 8123-4567\ntelecaremedical@telecare.com\n\nPATIENT NAME: John Noel Orano DATE: March 9, 2026\nAGE / SEX: 22 years old / Male PATIENT ID: TC-2026-0042\nADDRESS: NPC Kanan Makisig St., Brgy. 171, Caloocan City\nPHONE: 09923139504\n1. Amoxicillin 500mg Capsule\n\nSig: Take 1 capsule every 8 hours for 7 days\n\nDispense: 21 capsules | Refills: 0\n2. Metformin 500mg Tablet\n\nSig: Take 1 tablet twice daily with meals (morning and evening)\n\nDispense: 60 tablets | Refills: 2\n3. Losartan Potassium 50mg Tablet\n\nSig: Take 1 tablet once daily in the morning\n\nDispense: 30 tablets | Refills: 3\nNOTES:\nPatient advised to take medications with food. Avoid alcohol while on Amoxicillin. Monitor blood sugar levels daily. Return for\nfollow-up after 2 weeks or immediately if symptoms worsen.\n\nDr. Maria Santos, MD\nCardiologist | PRC Lic. No. 0123456\nSt. Luke Medical Wellness Center\nThis prescription is valid for 30 days from the date of issue. | TELE-CARE © 2026','2026-03-09 15:35:10'),(2,5,'uploads/ocr/ocr_69b66f98ab067.pdf','prescription','Prescription 1','--- Page 1 ---\n321 Quezon Avenue, Quezon City, Metro Manila | Tel: (02) 8321-9876\ninfo@excellcare.com | www.excellcare.com\nPATIENT NAME: ~— Bernard Dela Cruz Santos DATE: March 15, 2026\nAGE / SEX: 34 years old / Male PATIENT == TC-2026-0155\nID:\nDIAGNOSIS: Upper Respiratory Tract Infection, PHONE: 09561234567\nAsthma\nALLERGY: Sulfonamides (avoid)\nRx\n1. Azithromycin 500mg Tablet\nSig: Take 1 tablet once daily for 5 days\nDispense: 5 tablets | Refills: 0\nWARNING: Complete the full course even if feeling better. Avoid antacids within 2 hours of taking.\n2. Salbutamol 2mg Tablet\nSig: Take 1 tablet three times daily (morning, afternoon, and evening)\nDispense: 30 tablets | Refills: 2\nWARNING: May cause tremors or increased heart rate. Do not exceed prescribed dose. Avoid caffeine.\n3. Montelukast 10mg Tablet\nSig: Take 1 tablet once daily at bedtime\nDispense: 30 tablets | Refills: 3\nWARNING: Report any changes in mood or behavior immediately. Do not stop without consulting your doctor.\n4. Cetirizine 10mg Tablet\nSig: Take 1 tablet once daily at night\nDispense: 14 tablets | Refills: 1\nWARNING: May cause drowsiness. Avoid driving or operating machinery after taking. Avoid alcohol.\n5. Paracetamol 500mg Tablet\nSig: Take 1-2 tablets every 4 to 6 hours as needed for fever or pain. Do not exceed 8 tablets per day.\nDispense: 20 tablets | Refills: 0\nWARNING: Do not take with other paracetamol-containing products. Avoid alcohol. Urgent — stop\nimmediately if yellowing of skin or eyes occurs.\nNOTES:\nPatient advised to rest and increase fluid intake. Steam inhalation 2-3 times daily recommended. Avoid\ncold drinks and air-conditioned environments. Use Salbutamol as rescue medication during acute asthma\nattack. Return immediately if dysonea worsens or fever persists beyond 3 days. Follow-up in 1 week.\nDr. Ma. Aberlee Lacanaria\n\n--- Page 2 ---\nGeneral Practitioner | PRC Lic. No. 0312456\nEXCELLCARE MEDICAL SYSTEM INC.\nThis prescription is valid for 30 days from the date of issue. | EXCELLCARE © 2026','2026-03-15 08:36:48'),(5,8,'uploads/ocr/ocr_6a7efb64eed92.jpg','unknown','','4:11 • 9	(VOLTE 2 450	1 450,	51	\r\nJerson Sagun	\r\n& About me	\r\n• Contact details	>	\r\ni Emergency contact details	\r\n• Address	\r\n• Payment method	\r\n• Delete account','2026-08-14 11:26:30');
/*!40000 ALTER TABLE `lab_results` ENABLE KEYS */;
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patients`
--

LOCK TABLES `patients` WRITE;
/*!40000 ALTER TABLE `patients` DISABLE KEYS */;
INSERT INTO `patients` VALUES (1,'John Noel Oraño','2004-12-10','Male','johnnoelorano@gmail.com','09923139504','uploads/profiles/patient_69ad35693609a.png','','','','$2y$10$HDx76CM.oTMDTehDq.Rwmu16LHCvP.rpz5WHuK3cx/S1ufdeLa5ka','',NULL,'NPC KANAN MAKISIG STREET BARANGAY 171','CALOOCAN CITY','Philippines','','','English','2026-03-08 06:11:37','2026-08-07 07:06:12',1,NULL,NULL,NULL,NULL,1),(5,'Cid Kagenou','2011-03-01','Male','cidkag1210@gmail.com','09123456789','uploads/profiles/patient_69b66c6f61c19.png','','','','$2y$10$X.uZM7Zu/WPogMFGiCEIjOIDyRQYm.7vchIVgaamtNEZgdaQPjByO','',NULL,'Hillcrest Village Gate 1','Caloocan City','Philippines','','','English','2026-03-15 08:00:28','2026-03-29 18:50:03',1,NULL,NULL,NULL,NULL,1),(6,'John Nowel','2008-04-03','Male','sumivalo10@gmail.com','+639923139504',NULL,'','','','$2y$10$z3EWoneDOMdsJezI/0cgBOvGTgWjbPwYxSLOE0SgoZhOY5dHq46YG','',NULL,'','City of Caloocan','Philippines','','','English','2026-04-04 07:07:34','2026-04-04 07:18:53',0,'9d01ef1f345fbe20b2875603736c41a22457ab33fba251d3182fe89238061f0b','2026-04-05 09:18:53',NULL,NULL,1),(7,'Jerson Sagun','2008-08-09','Male','jersonsagun@gmail.com','+639923139504',NULL,'','','','$2y$10$h4kFa3vluW0FOuw0v6yNseqhRWMjTDu8Xug5pCBimowS21FLLaQUO','',NULL,'','City of Caloocan','Philippines','','','English','2026-08-10 08:17:30','2026-08-10 08:17:30',0,'1d9162f9963ac2cef5c6451c0c2b93f320565d409d8da6f37a026f1d22880a5f','2026-08-11 10:17:30',NULL,NULL,1),(8,'Jerson Uanan Sagun','2004-10-15',NULL,'sagun.jersonbsis2023@gmail.com','+639602059511',NULL,NULL,NULL,NULL,'$2y$10$WefO41SasPK3Pws2u5oHoeC3r3r7q3.8E5.4ORgVoIG.gaKpp9pWy',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-08-14 11:23:03','2026-08-14 11:23:19',1,NULL,NULL,NULL,NULL,1);
/*!40000 ALTER TABLE `patients` ENABLE KEYS */;
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
INSERT INTO `products` VALUES (1,'Paracetamol 500mg','Medicine','Pain reliever / fever reducer','Tablet',10.00,20,120,'Active','2026-08-22 15:33:51','2026-08-22 15:33:51'),(2,'Amoxicillin 500mg','Medicine','Antibiotic capsule','Capsule',25.00,15,50,'Active','2026-08-22 15:33:51','2026-08-22 15:33:51'),(3,'Vitamin C 500mg','Medicine','Immune support supplement','Tablet',15.00,10,80,'Active','2026-08-22 15:33:51','2026-08-23 13:02:48'),(4,'Digital Thermometer','Medicine','Non-contact infrared thermometer','Piece',350.00,5,12,'Active','2026-08-22 15:33:51','2026-08-23 13:02:48'),(5,'Cough Syrup 60ml','Medicine','Relief for dry and wet cough','Bottle',85.00,8,3,'Active','2026-08-22 15:33:51','2026-08-22 15:33:51'),(26,'Urine Specimen Container','Testing Kits','Sterile container for urinalysis specimen collection.','Piece',10.00,20,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(27,'Stool Specimen Container','Testing Kits','Container for fecalysis (stool) specimen collection.','Piece',10.00,20,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(28,'Fecal Occult Blood Test Stick','Testing Kits','Stick used for fecal occult blood testing.','Piece',15.00,20,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(29,'Pregnancy Test Kit (Strip)','Testing Kits','Urine pregnancy test strip.','Piece',25.00,15,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(30,'Blood Glucose Test Strip','Testing Kits','Test strip for blood glucose monitoring.','Piece',20.00,30,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(31,'Lancet','Testing Kits','Sterile lancet for capillary blood sampling.','Piece',3.00,50,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(32,'Cotton Swab (Sterile)','Testing Kits','Sterile cotton swab for specimen collection.','Piece',2.00,50,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(33,'Alcohol Swab','Testing Kits','Alcohol prep pad for skin disinfection before sampling.','Piece',1.50,50,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(34,'Vacutainer Blood Collection Tube','Testing Kits','Tube for venous blood sample collection.','Piece',12.00,30,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49'),(35,'Rapid Antigen Test Kit','Testing Kits','Rapid antigen test kit for infectious disease screening.','Box',150.00,10,100,'Active','2026-08-23 14:10:22','2026-08-23 14:11:49');
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
  `status` enum('Active','Archived') NOT NULL DEFAULT 'Active',
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
INSERT INTO `services` VALUES (1,'CBC Test','Complete Blood Count test',350.00,'Laboratory','Active','2026-08-23 11:25:58','2026-08-23 11:25:58'),(2,'Urinalysis','Routine urine test',150.00,'Laboratory','Active','2026-08-23 11:25:58','2026-08-23 11:25:58'),(3,'X-Ray Imaging','Standard single-view X-ray',320.00,'X-ray','Active','2026-08-23 11:25:58','2026-08-23 11:25:58'),(4,'General Consultation','Walk-in consultation with a physician',500.00,'Consultation','Active','2026-08-23 11:25:58','2026-08-23 11:25:58'),(5,'asda','asdada',222.00,'Laboratory','Archived','2026-08-23 12:54:32','2026-08-23 14:12:18'),(6,'SADA','DASDSAD',222.00,'Laboratory','Active','2026-08-23 13:09:23','2026-08-23 13:09:23'),(7,'sdada','adad',222.00,'Laboratory','Active','2026-08-23 14:07:43','2026-08-23 14:07:43'),(8,'sda','adas',222.00,'Laboratory','Active','2026-08-23 14:11:57','2026-08-23 14:11:57');
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
INSERT INTO `staff_accounts` VALUES (4,'TELE-CARE Staff','staff@telecare.com','$2y$10$YnlzuMjQm2ntktIn7faJ9eOWpWDQwGxNhLOk5hmquxEvebnJ/Ko7G','active','2026-03-22 15:47:28');
/*!40000 ALTER TABLE `staff_accounts` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-23 23:04:47
