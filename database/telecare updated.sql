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
-- Table structure for table `appointments`
--

DROP TABLE IF EXISTS `appointments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `appointments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `type` enum('General','Follow-up','Emergency','Teleconsult') DEFAULT 'Teleconsult',
  `status` enum('Pending','Confirmed','Completed','Cancelled') DEFAULT 'Pending',
  `notes` text,
  `payment_status` enum('Unpaid','Paid') DEFAULT 'Unpaid',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
/*!40000 ALTER TABLE `appointments` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctor_schedules`
--

LOCK TABLES `doctor_schedules` WRITE;
/*!40000 ALTER TABLE `doctor_schedules` DISABLE KEYS */;
INSERT INTO `doctor_schedules` VALUES (1,4,'Monday','14:30:00','19:45:00'),(2,4,'Tuesday','14:30:00','19:30:00'),(3,4,'Wednesday','14:30:00','19:30:00');
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctors`
--

LOCK TABLES `doctors` WRITE;
/*!40000 ALTER TABLE `doctors` DISABLE KEYS */;
INSERT INTO `doctors` VALUES (4,'Ma. Aberlee Lacanaria','General','','EXCELLCARE MEDICAL SYSTEM INC.','almondtofu25@gmail.com','$2y$10$KFzfCMLW/6YVdUWjiYG8b.g0yyUP6ZAW24jy1H7DrbopyR.btv15a','09999999999','uploads/profiles/doc_69b50c372d091.jfif','General doctor at EXCELLCARE MEDICAL SYSTEM INC.',500.00,'Filipino',0.0,'senior','active',1,'','',NULL,NULL,1,1,'2026-03-14 00:39:41',1,NULL,'2026-03-21 07:46:05',1,'2026-03-14 06:46:05','2026-03-14 07:39:41');
/*!40000 ALTER TABLE `doctors` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lab_results`
--

LOCK TABLES `lab_results` WRITE;
/*!40000 ALTER TABLE `lab_results` DISABLE KEYS */;
INSERT INTO `lab_results` VALUES (1,1,'uploads/ocr/ocr_69aee8a9b1de2.pdf','prescription','Prescription 1','--- Page 1 ---\nTELE-CARE Medical Clinic\n123 Mabini Street, Caloocan City, Metro Manila | Tel: (02) 8123-4567\ntelecaremedical@telecare.com\n\nPATIENT NAME: John Noel Orano DATE: March 9, 2026\nAGE / SEX: 22 years old / Male PATIENT ID: TC-2026-0042\nADDRESS: NPC Kanan Makisig St., Brgy. 171, Caloocan City\nPHONE: 09923139504\n1. Amoxicillin 500mg Capsule\n\nSig: Take 1 capsule every 8 hours for 7 days\n\nDispense: 21 capsules | Refills: 0\n2. Metformin 500mg Tablet\n\nSig: Take 1 tablet twice daily with meals (morning and evening)\n\nDispense: 60 tablets | Refills: 2\n3. Losartan Potassium 50mg Tablet\n\nSig: Take 1 tablet once daily in the morning\n\nDispense: 30 tablets | Refills: 3\nNOTES:\nPatient advised to take medications with food. Avoid alcohol while on Amoxicillin. Monitor blood sugar levels daily. Return for\nfollow-up after 2 weeks or immediately if symptoms worsen.\n\nDr. Maria Santos, MD\nCardiologist | PRC Lic. No. 0123456\nSt. Luke Medical Wellness Center\nThis prescription is valid for 30 days from the date of issue. | TELE-CARE © 2026','2026-03-09 15:35:10'),(2,5,'uploads/ocr/ocr_69b66f98ab067.pdf','prescription','Prescription 1','--- Page 1 ---\n321 Quezon Avenue, Quezon City, Metro Manila | Tel: (02) 8321-9876\ninfo@excellcare.com | www.excellcare.com\nPATIENT NAME: ~— Bernard Dela Cruz Santos DATE: March 15, 2026\nAGE / SEX: 34 years old / Male PATIENT == TC-2026-0155\nID:\nDIAGNOSIS: Upper Respiratory Tract Infection, PHONE: 09561234567\nAsthma\nALLERGY: Sulfonamides (avoid)\nRx\n1. Azithromycin 500mg Tablet\nSig: Take 1 tablet once daily for 5 days\nDispense: 5 tablets | Refills: 0\nWARNING: Complete the full course even if feeling better. Avoid antacids within 2 hours of taking.\n2. Salbutamol 2mg Tablet\nSig: Take 1 tablet three times daily (morning, afternoon, and evening)\nDispense: 30 tablets | Refills: 2\nWARNING: May cause tremors or increased heart rate. Do not exceed prescribed dose. Avoid caffeine.\n3. Montelukast 10mg Tablet\nSig: Take 1 tablet once daily at bedtime\nDispense: 30 tablets | Refills: 3\nWARNING: Report any changes in mood or behavior immediately. Do not stop without consulting your doctor.\n4. Cetirizine 10mg Tablet\nSig: Take 1 tablet once daily at night\nDispense: 14 tablets | Refills: 1\nWARNING: May cause drowsiness. Avoid driving or operating machinery after taking. Avoid alcohol.\n5. Paracetamol 500mg Tablet\nSig: Take 1-2 tablets every 4 to 6 hours as needed for fever or pain. Do not exceed 8 tablets per day.\nDispense: 20 tablets | Refills: 0\nWARNING: Do not take with other paracetamol-containing products. Avoid alcohol. Urgent — stop\nimmediately if yellowing of skin or eyes occurs.\nNOTES:\nPatient advised to rest and increase fluid intake. Steam inhalation 2-3 times daily recommended. Avoid\ncold drinks and air-conditioned environments. Use Salbutamol as rescue medication during acute asthma\nattack. Return immediately if dysonea worsens or fever persists beyond 3 days. Follow-up in 1 week.\nDr. Ma. Aberlee Lacanaria\n\n--- Page 2 ---\nGeneral Practitioner | PRC Lic. No. 0312456\nEXCELLCARE MEDICAL SYSTEM INC.\nThis prescription is valid for 30 days from the date of issue. | EXCELLCARE © 2026','2026-03-15 08:36:48');
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
-- Table structure for table `patient_doctors`
--

DROP TABLE IF EXISTS `patient_doctors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `patient_doctors` (
  `id` int NOT NULL AUTO_INCREMENT,
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_assignment` (`patient_id`,`doctor_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `patient_doctors_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `patient_doctors_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patient_doctors`
--

LOCK TABLES `patient_doctors` WRITE;
/*!40000 ALTER TABLE `patient_doctors` DISABLE KEYS */;
INSERT INTO `patient_doctors` VALUES (1,1,4,'2026-03-14 06:48:13'),(2,5,4,'2026-03-15 08:38:09');
/*!40000 ALTER TABLE `patient_doctors` ENABLE KEYS */;
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
  `gender` enum('Male','Female','Prefer not to say') NOT NULL,
  `email` varchar(150) NOT NULL,
  `phone_number` varchar(20) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `emergency_name` varchar(150) DEFAULT NULL,
  `emergency_relationship` varchar(80) DEFAULT NULL,
  `emergency_number` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `security_question` varchar(255) DEFAULT NULL,
  `security_answer` varchar(255) DEFAULT NULL,
  `home_address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country_region` varchar(100) DEFAULT NULL,
  `insurance_provider` varchar(150) DEFAULT NULL,
  `insurance_policy_no` varchar(100) DEFAULT NULL,
  `preferred_language` varchar(80) DEFAULT 'English',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_verified` tinyint(1) NOT NULL DEFAULT '0',
  `verification_token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patients`
--

LOCK TABLES `patients` WRITE;
/*!40000 ALTER TABLE `patients` DISABLE KEYS */;
INSERT INTO `patients` VALUES (1,'John Noel Oraño','2004-12-10','Male','johnnoelorano@gmail.com','09923139504','uploads/profiles/patient_69ad35693609a.png','','','','$2y$10$.0dGQe0M5MZ7CbYzHVxr3.MOuseWn8XxI5LIEyy2VAuwE06n.tgPq','',NULL,'NPC KANAN MAKISIG STREET BARANGAY 171','CALOOCAN CITY','Philippines','','','English','2026-03-08 06:11:37','2026-03-08 08:38:01',0,NULL,NULL),(5,'Cid Kagenou','2011-03-01','Male','cidkag1210@gmail.com','09123456789','uploads/profiles/patient_69b66c6f61c19.png','','','','$2y$10$X.uZM7Zu/WPogMFGiCEIjOIDyRQYm.7vchIVgaamtNEZgdaQPjByO','',NULL,'Hillcrest Village Gate 1','Caloocan City','Philippines','','','English','2026-03-15 08:00:28','2026-03-15 08:23:44',1,NULL,NULL);
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
-- Dumping events for database 'telecare'
--

--
-- Dumping routines for database 'telecare'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-03-15 17:50:42
