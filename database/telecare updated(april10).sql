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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointment_logs`
--

LOCK TABLES `appointment_logs` WRITE;
/*!40000 ALTER TABLE `appointment_logs` DISABLE KEYS */;
INSERT INTO `appointment_logs` VALUES (1,1,4,'Rejectd','','2026-04-08 22:06:59'),(2,2,4,'Approved','','2026-04-08 22:07:04'),(3,3,4,'Approved','','2026-04-08 22:18:18'),(4,4,4,'Approved','','2026-04-08 23:43:11'),(5,5,4,'Approved','','2026-04-09 00:58:04'),(6,5,4,'Completed','','2026-04-09 02:06:58');
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
  `patient_id` int NOT NULL,
  `doctor_id` int NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `type` enum('General','Follow-up','Emergency','Teleconsult') DEFAULT 'Teleconsult',
  `status` enum('Pending','DoctorApproved','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `notes` text,
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
  KEY `patient_id` (`patient_id`),
  KEY `doctor_id` (`doctor_id`),
  CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `appointments`
--

LOCK TABLES `appointments` WRITE;
/*!40000 ALTER TABLE `appointments` DISABLE KEYS */;
INSERT INTO `appointments` VALUES (1,1,4,'2026-04-08','14:30:00','Teleconsult','Cancelled','','Unpaid','2026-04-08 04:11:58',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,0,NULL),(2,1,4,'2026-04-11','22:30:00','Teleconsult','Completed','','Paid','2026-04-08 14:06:36','pi_EEDeoMth7JKXLrZu8hiFNn9N','TC-C42E5194','2026-04-08 22:07:36',NULL,NULL,NULL,NULL,NULL,0,NULL),(3,1,4,'2026-04-08','22:30:00','Teleconsult','Completed','','Paid','2026-04-08 14:18:08','src_FvSt2JR2Wx3aAet7dgSzrNxz','TC-6ED24CF3','2026-04-08 22:18:46','[10:30:00 PM] System: Call connected\r\n[10:30:00 PM] System: Call connected\r\n[10:30 PM] John Noel Oraño: hello doc\r\n[10:31 PM] Dr. Ma. Aberlee Lacanaria: hi how are you\r\n[10:31 PM] John Noel Oraño: I feel bad I have headache and stomach ache\r\n[10:32 PM] Dr. Ma. Aberlee Lacanaria: drink some medicines like paracetamol and diatabs\r\n[10:34:57 PM] System: Call ended\n[10:30:00 PM] System: Call connected\r\n[10:30:00 PM] System: Call connected\r\n[10:30 PM] You: hello doc\r\n[10:31 PM] Dr. Ma. Aberlee Lacanaria: hi how are you\r\n[10:31 PM] You: I feel bad I have headache and stomach ache\r\n[10:32 PM] Dr. Ma. Aberlee Lacanaria: drink some medicines like paracetamol and diatabs\r\n[10:34:59 PM] System: Call ended\n[10:37:23 PM] System: Call connected\r\n[10:37:23 PM] System: Call connected\r\n[10:37 PM] Dr. Ma. Aberlee Lacanaria: hi\r\n[10:37 PM] You: hello doc\r\n[10:38:00 PM] System: Call ended\n[10:37:23 PM] System: Call connected\r\n[10:37:23 PM] System: Call connected\r\n[10:37 PM] Dr. Ma. Aberlee Lacanaria: hi\r\n[10:37 PM] John Noel Oraño: hello doc\r\n[10:38:02 PM] System: Call ended\n[10:39:15 PM] System: Call connected\r\n[10:39:15 PM] System: Call connected\r\n[10:39 PM] John Noel Oraño: Hello how are you\r\n[10:39 PM] Dr. Ma. Aberlee Lacanaria: I dont feel goodd\r\n[10:39:40 PM] System: Call ended\n[10:39:15 PM] System: Call connected\r\n[10:39:15 PM] System: Call connected\r\n[10:39 PM] You: Hello how are you\r\n[10:39 PM] Dr. Ma. Aberlee Lacanaria: I dont feel goodd\r\n[10:39:42 PM] System: Call ended\n\n[--- Rejoined Session — Apr 8, 2026 10:47 PM ---]\n[10:47:05 PM] System: Call connected\r\n[10:47:05 PM] System: Call connected\r\n[10:47 PM] John Noel Oraño: hi\r\n[10:47 PM] Dr. Ma. Aberlee Lacanaria: hello\r\n[10:47 PM] Dr. Ma. Aberlee Lacanaria: Im now good\r\n[10:47 PM] John Noel Oraño: okay me too\r\n[10:47:36 PM] System: Call ended\n[10:47:05 PM] System: Call connected\r\n[10:47:05 PM] System: Call connected\r\n[10:47 PM] You: hi\r\n[10:47 PM] Dr. Ma. Aberlee Lacanaria: hello\r\n[10:47 PM] Dr. Ma. Aberlee Lacanaria: Im now good\r\n[10:47 PM] You: okay me too\r\n[10:47:39 PM] System: Call ended\n[10:51:57 PM] System: Call connected\r\n[10:51:57 PM] System: Call connected\r\n[10:52 PM] John Noel Oraño: hello\r\n[10:52 PM] John Noel Oraño: hi\r\n[10:52 PM] John Noel Oraño: hi\r\n[10:52 PM] John Noel Oraño: hi\r\n[10:52 PM] John Noel Oraño: are you okay\r\n[10:52 PM] Dr. Ma. Aberlee Lacanaria: yes im good what do u feel now\r\n[10:52 PM] John Noel Oraño: good\r\n[10:52 PM] Dr. Ma. Aberlee Lacanaria: oki loveu\r\n[10:52:45 PM] System: Call ended\n[10:57:02 PM] System: Call connected\r\n[10:57:02 PM] System: Call connected\r\n[10:57 PM] Dr. Ma. Aberlee Lacanaria: hello\r\n[10:57 PM] Dr. Ma. Aberlee Lacanaria: hello\r\n[10:57 PM] Dr. Ma. Aberlee Lacanaria: hello\r\n[10:57 PM] Dr. Ma. Aberlee Lacanaria: how are you\r\n[10:57 PM] John Noel Oraño: good\r\n[10:57 PM] John Noel Oraño: good\r\n[10:57 PM] John Noel Oraño: nice\r\n[10:57 PM] John Noel Oraño: i feel bad\r\n[10:57 PM] John Noel Oraño: stomachache\r\n[10:57:31 PM] System: Call ended\n[10:57:02 PM] System: Call connected\r\n[10:57:02 PM] System: Call connected\r\n[10:57 PM] Dr. Ma. Aberlee Lacanaria: hello\r\n[10:57 PM] Dr. Ma. Aberlee Lacanaria: hello\r\n[10:57 PM] Dr. Ma. Aberlee Lacanaria: hello\r\n[10:57 PM] Dr. Ma. Aberlee Lacanaria: how are you\r\n[10:57 PM] You: good\r\n[10:57 PM] You: good\r\n[10:57 PM] You: nice\r\n[10:57 PM] You: i feel bad\r\n[10:57 PM] You: stomachache\r\n[10:59:21 PM] System: Call ended\n\n[--- Rejoined Session — Apr 8, 2026 11:10 PM ---]\n[11:09:14 PM] System: Call connected\r\n[11:09:14 PM] System: Call connected\r\n[11:09 PM] Dr. Ma. Aberlee Lacanaria: hel;llo\r\n[11:09 PM] Dr. Ma. Aberlee Lacanaria: hello\r\n[11:09 PM] Dr. Ma. Aberlee Lacanaria: hi\r\n[11:09 PM] Dr. Ma. Aberlee Lacanaria: hi\r\n[11:09 PM] Dr. Ma. Aberlee Lacanaria: hi\r\n[11:09 PM] John Noel Oraño: hi doc im all good now thank you for the tips and tricks\r\n[11:09 PM] Dr. Ma. Aberlee Lacanaria: AHAAHAHA oki\r\n[11:10:12 PM] System: Call ended\n[11:09:14 PM] System: Call connected\r\n[11:09:14 PM] System: Call connected\r\n[11:09 PM] Dr. Ma. Aberlee Lacanaria: hel;llo\r\n[11:09 PM] Dr. Ma. Aberlee Lacanaria: hello\r\n[11:09 PM] Dr. Ma. Aberlee Lacanaria: hi\r\n[11:09 PM] Dr. Ma. Aberlee Lacanaria: hi\r\n[11:09 PM] Dr. Ma. Aberlee Lacanaria: hi\r\n[11:09 PM] You: hi doc im all good now thank you for the tips and tricks\r\n[11:09 PM] Dr. Ma. Aberlee Lacanaria: AHAAHAHA oki\r\n[11:10:20 PM] System: Call ended\n[11:11:52 PM] System: Call connected\r\n[11:11:52 PM] System: Call connected\r\n[11:11 PM] Dr. Ma. Aberlee Lacanaria: heyy\r\n[11:12 PM] John Noel Oraño: im good\r\n[11:12 PM] John Noel Oraño: thank you for the tips and tricks doctor lacanaria\r\n[11:12 PM] Dr. Ma. Aberlee Lacanaria: HAHAHAHAAHA oki\r\n[11:12:38 PM] System: Call ended\n\n[--- Rejoined Session — Apr 8, 2026 11:17 PM ---]\n[11:16:44 PM] System: Call connected\r\n[11:16:44 PM] System: Call connected\r\n[11:16 PM] Dr. Ma. Aberlee Lacanaria: sah\r\n[11:16 PM] Dr. Ma. Aberlee Lacanaria: hello\r\n[11:17 PM] John Noel Oraño: hi\r\n[11:17 PM] John Noel Oraño: hi\r\n[11:17 PM] John Noel Oraño: hi\r\n[11:17 PM] John Noel Oraño: hello\r\n[11:17 PM] John Noel Oraño: Im all goods now thank you for the tips and consultation doc\r\n[11:17 PM] Dr. Ma. Aberlee Lacanaria: HAHAHAHAHAH welcome\r\n[11:17:36 PM] System: Call ended\n[11:11:52 PM] System: Call connected\r\n[11:11:52 PM] System: Call connected\r\n[11:11 PM] Dr. Ma. Aberlee Lacanaria: heyy\r\n[11:12 PM] You: im good\r\n[11:12 PM] You: thank you for the tips and tricks doctor lacanaria\r\n[11:12 PM] Dr. Ma. Aberlee Lacanaria: HAHAHAHAAHA oki\r\n[11:16:44 PM] System: Call connected\r\n[11:16:44 PM] System: Call connected\r\n[11:16 PM] Dr. Ma. Aberlee Lacanaria: sah\r\n[11:16 PM] Dr. Ma. Aberlee Lacanaria: hello\r\n[11:17 PM] You: hi\r\n[11:17 PM] You: hi\r\n[11:17 PM] You: hi\r\n[11:17 PM] You: hello\r\n[11:17 PM] You: Im all goods now thank you for the tips and consultation doc\r\n[11:17 PM] Dr. Ma. Aberlee Lacanaria: HAHAHAHAHAH welcome\r\n[11:18:06 PM] System: Call ended\n[11:23:16 PM] System: Call connected\r\n[11:23:16 PM] System: Call connected\r\n[11:23 PM] Dr. Ma. Aberlee Lacanaria: h\r\n[11:23 PM] Dr. Ma. Aberlee Lacanaria: i\r\n[11:23 PM] Dr. Ma. Aberlee Lacanaria: hi\r\n[11:23 PM] Dr. Ma. Aberlee Lacanaria: if u have further questions ask the chatbot\r\n[11:23 PM] Dr. Ma. Aberlee Lacanaria: consultation done wait for the summary in your page\r\n[11:24 PM] John Noel Oraño: HAHAHAHAH OKI\r\n[11:24:11 PM] System: Call ended\n[11:23:16 PM] System: Call connected\r\n[11:23:16 PM] System: Call connected\r\n[11:23 PM] Dr. Ma. Aberlee Lacanaria: h\r\n[11:23 PM] Dr. Ma. Aberlee Lacanaria: i\r\n[11:23 PM] Dr. Ma. Aberlee Lacanaria: hi\r\n[11:23 PM] Dr. Ma. Aberlee Lacanaria: if u have further questions ask the chatbot\r\n[11:23 PM] Dr. Ma. Aberlee Lacanaria: consultation done wait for the summary in your page\r\n[11:24 PM] You: HAHAHAHAH OKI\r\n[11:24:56 PM] System: Call ended','I\'m going to catch the smoke off. I\'m going to catch the smoke off. I\'m going to catch the smoke off. I\'m going to catch the smoke off. I\'m going to catch the smoke off. I\'m going to catch the smoke off. I\'m going to catch the smoke off. I\'m going to catch the smoke off. I\'m going to catch the smoke off. I\'m going to catch the smoke off. I\'m going to catch the smoke off. I\'m going to catch the smoke off. you you you you you you you you I don\'t know what I\'m talking about. I know what I mean, I know what I mean. Hello, hello, hello, hello. Come on, I know. More intense than 10 seconds before, hello, hello, hello, hello, hello. Come on, I know. More intense than 10 seconds before, oh, how? Try and act in. Oh, how? Try and act in. you you you you you you you you\nI\'m going to catch the smoke. Okay. Then. Okay. Then. Then. Okay. Then. Then. you I love it. I will go. Hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello 7 8 9 10 11 12 13 14 9 10 11 12 13 14 15 16 17 18 19 19 20 I know what I mean. I know what I mean. I know what I mean. I know what I mean. I know what I mean. I know what I mean. I know what I mean. I know what I mean. I know what I mean. I know what I mean. I know what I mean. I know what I mean. I know what I mean. I know what I mean. you you you you you you you you .\nI\'m not going to do this. I\'m not going to do this. I\'m not going to do this. I\'m not going to do this. Let me check what happened. Let me check what happened. What is this? No, no, no. No, no, no, no. No, no. No, no.\nI\'m going to put it on the front. I\'m going to put it on the front. I\'m going to put it on the front. I\'m going to put it on the front. Let me tell you one. No, no, no. Right, no, no, no. Right. No, no. No, no.\nthe body. Go, eat, plah, jump, jump, you know what? Go, eat, plah, jump, jump, you know what? Go, just eat, no. No. No. No. No. No. No. No. No. No. No. No. No. No. No.\nthe body. Go, eat, plah, jump, jump, you know what? So, we play the last one. Do. No. No. No. No. No. That\'s easy. That\'s easy. That\'s easy. That\'s easy. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that.\n\n[--- Rejoined Session — Apr 8, 2026 10:47 PM ---]\nbut what\'s the beat in the cup? what\'s the beat in the cup? All. All. All. All. All. All. All. All. All. All. All. All. All. All. All. All. All. All. All. All. All. All. All. All. All. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that.\nbut what\'s the beat? with the cup. And alt. And alt. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that.\nNo, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no you you\nWhat? What? What? What? What? What? What? What? What? What? What? What? What? What? What? What? What? What? What? What? What? What? What? What? What? I\'m going to take you to the top of the table. I\'m going to take you to the top of the table. I\'m going to take you to the top of the table. I\'m going to take you to the top of the table. I\'m going to take you to the top of the table. I\'m going to take you to the top of the table. I\'m going to take you to the top of the table. I\'m going to take you to the top of the table. I\'m going to take you to the top of the table. I\'m going to take you to the top of the table. I\'m going to take you to the top of the table. I\'m going to take you to the top of the table.\nI\'m going to take a 10. 10. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. 10. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. It\'s been 5. Yes, gagho! Yes, gagho! Tang Ina wungu mana? Tang Ina wungu mana? Hello. I\'m not going to get out of here. I\'m not going to get out of here. I\'m not going to get out of here. I\'m not going to get out of here. I\'m not going to get out of here. I\'m not going to get out of here. I\'m not going to get out of here. I\'m not going to get out of here. I\'m not going to get out of here. I\'m not going to get out of here. I\'m not going to get out of here. I\'m not going to get out of here. I\'m not going to get out of here. I\'m not going to get out of here. I\'m not going to get out of here. I\'m going to say that. I\'m going to say that. I\'m going to say that. I\'m going to say that. I\'m going to say that. I\'m going to say that. I\'m going to say that. I\'m going to say that. I\'m going to say that. I\'m going to say that. I\'m going to say that. I\'m going to say that. I\'m going to say that. I\'m going to say that. I\'m going to say that. We spread parent. So we spread parent. So we spread parent. So we spread parent. I don\'t know what I\'m doing. So I\'m doing this. I don\'t know what I\'m doing. So I\'m doing this. Because I don\'t know what I\'m doing. I don\'t know what I\'m doing. Because I don\'t know what I\'m doing. I\'m doing this. I\'m doing this. I\'m doing this. I\'m doing this. I\'m doing this. I\'m doing this. and it\'s not old it\'s not one huh not one uh... open source uh... open source I\'ll go back on noises. Let me. I\'ll go back on noises.\n\n[--- Rejoined Session — Apr 8, 2026 11:10 PM ---]\nI\'m not done with the issue. I\'m not done with the issue. I\'m not done with the issue. Deep set. Deep set. Deep set. Deep set. Deep set. Okay, we have a list. Hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hi, hello, hi, hello, hi, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello, hello Hello, hello, how are you? Hello, hello, hello, how are you? How are you? Roll, stop, stop.\nI\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. I\'m not going to do that. So, we have options. Hello, welcome to 3 4. We have options. Hello, welcome to 3 4 5 1 2. Hello, hello, hello. 5 1 2. Hello, hello, hello. Hi, hello, hi. Hello.\nthen right next chart and then right next chart and Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello, hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. Hello. How are you? Hello. Hello. Hello.\n\n[--- Rejoined Session — Apr 8, 2026 11:17 PM ---]\nNo, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no 1 2 3 1 2 3 1 2 3 1 2 3 I\'m going to show you the key. I\'m going to show you the key. I\'m going to show you the key. I\'m going to show you the key. I\'m going to show you the key. I\'m going to show you the key. I\'m going to show you the key. I\'m going to show you the key. I\'m going to show you the key. I\'m going to show you the key. I\'m going to show you the key. I\'m going to show you the key. I\'m going to show you the key. I\'m going to show you the key. I\'m going to start with the first one. I\'m going to start with the first one. I\'m going to start with the second one. I\'m going to start with the second one. I\'m going to start with the second one. I\'m going to start with the second one. I\'m going to start with the second one. I\'m going to start with the second one. I\'m going to start with the second one. I\'m going to start with the second one. I\'m going to start with the second one. I\'m going to start with the second one. I\'m going to start with the second one. I\'m going to start with the second one.\nNo, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no you you\nNo, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no, no I\'m a sister. I\'m a sister. I\'m a sister. I\'m a sister. I\'m a sister. I\'m a sister. I\'m a sister. I\'m a sister. I\'m a sister. I\'m a sister. I\'m a sister. I\'m a sister. I\'m a sister. I\'m a sister. Okay. Okay. Okay. Okay. Okay. Okay. Okay. Okay. Okay. Okay. Okay. Okay. Okay. Okay. you you','1. Chief Complaint\nPatient reported feeling unwell with headache and stomach ache.\n\n2. Symptoms Discussed\nHeadache, stomach ache.\n\n3. Doctor\'s Assessment\nThe doctor engaged in multiple connection attempts and brief conversations with the patient. The patient initially reported feeling unwell with headache and stomach ache. Later in the consultation, the patient stated they were feeling \"all good now\" and thanked the doctor for tips and tricks. The doctor\'s responses were often brief and sometimes included laughter. The audio transcript contains a significant amount of unintelligible speech and repeated phrases, making a detailed clinical assessment difficult to ascertain.\n\n4. Diagnosis (if mentioned)\nNot discussed.\n\n5. Treatment Plan / Prescriptions\nParacetamol and Diatabs were recommended.\n\n6. Follow-up Instructions\nThe doctor advised the patient to ask the chatbot for further questions and stated that the consultation was done, with a summary to be available on the patient\'s page.','summary_3.pdf','3_1775661300',0,NULL),(4,1,4,'2026-04-09','00:00:00','Teleconsult','Completed','','Paid','2026-04-08 15:43:03','src_ZpiWqU4ttEbB9L6mSWVKrX4X','TC-539FBAC5','2026-04-08 23:43:26','[12:00:00 AM] System: Call connected\r\n[12:00:00 AM] System: Call connected\r\n[12:00 AM] John Noel Oraño: hi\r\n[12:00 AM] John Noel Oraño: howw  are you\r\n[12:00 AM] Dr. Ma. Aberlee Lacanaria: i\'m good, how are you feeling\r\n[12:01 AM] John Noel Oraño: i have headache and asthma\r\n[12:01 AM] Dr. Ma. Aberlee Lacanaria: drink paracetamol and breath in and out for 5 seconds\r\n[12:01 AM] Dr. Ma. Aberlee Lacanaria: hahahaha\r\n[12:01 AM] Dr. Ma. Aberlee Lacanaria: meow meow\r\n[12:02 AM] John Noel Oraño: HAHAHAH oki doc thank you i\'ll be back later\r\n[12:02 AM] Dr. Ma. Aberlee Lacanaria: ok\r\n[12:02 AM] Dr. Ma. Aberlee Lacanaria: for further questions ask the chatbot\r\n[12:02:27 AM] System: Call ended\n[12:00:00 AM] System: Call connected\r\n[12:00:00 AM] System: Call connected\r\n[12:00 AM] You: hi\r\n[12:00 AM] You: howw  are you\r\n[12:00 AM] Dr. Ma. Aberlee Lacanaria: i\'m good, how are you feeling\r\n[12:01 AM] You: i have headache and asthma\r\n[12:01 AM] Dr. Ma. Aberlee Lacanaria: drink paracetamol and breath in and out for 5 seconds\r\n[12:01 AM] Dr. Ma. Aberlee Lacanaria: hahahaha\r\n[12:01 AM] Dr. Ma. Aberlee Lacanaria: meow meow\r\n[12:02 AM] You: HAHAHAH oki doc thank you i\'ll be back later\r\n[12:02 AM] Dr. Ma. Aberlee Lacanaria: ok\r\n[12:02 AM] Dr. Ma. Aberlee Lacanaria: for further questions ask the chatbot\r\n[12:04:01 AM] System: Call ended\n\n[--- Rejoined Session — Apr 9, 2026 12:21 AM ---]\n[12:18:20 AM] System: Call connected\r\n[12:18:20 AM] System: Call connected\r\n[12:21:10 AM] System: Call ended\n[12:18:20 AM] System: Call connected\r\n[12:18:20 AM] System: Call connected\r\n[12:21:13 AM] System: Call ended\n[12:26:36 AM] System: Call connected\r\n[12:26:36 AM] System: Call connected\r\n[12:27:05 AM] System: Call ended\n[12:26:36 AM] System: Call connected\r\n[12:26:36 AM] System: Call connected\r\n[12:27:07 AM] System: Call ended\n\n[--- Rejoined Session — Apr 9, 2026 12:54 AM ---]\n[12:53:51 AM] System: Call connected\r\n[12:53:51 AM] System: Call connected\r\n[12:54:42 AM] System: Call ended\n[12:53:51 AM] System: Call connected\r\n[12:53:51 AM] System: Call connected\r\n[12:54:44 AM] System: Call ended','Hello. Hello. Hello. Hello. the first one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one is the second one I\'m going to do it. I\'m going to do it. I\'m going to do it. I\'m going to do it. I\'m going to do it. I\'m going to do it. I\'m going to do it. I\'m going to do it. I\'m going to do it. I\'m going to do it. I\'m going to do it. I\'m going to do it. I\'m going to do it. I\'m going to do it. I\'m sorry. you you you you you\nNo. No. No. No. you you you you you you you you you you you you you you you\n\n[--- Rejoined Session — Apr 9, 2026 12:21 AM ---]\nI got the first child. I got the first child. Love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love, love for the day\'s consultation. Before we begin, please remember for the day\'s consultation. Before we begin, please remember for the day\'s consultation. Thank you, Nene, and day\'s for the day\'s consultation. Thank you. I understand that your experience in favor sought out head. I understand that your experience in favor So it would be a day and my cough. I will ask a few questions to bed and my cough. I will ask a few questions to bed and ask if you\'re a good patient. When your symptoms start, it says you\'re a good patient. When your symptoms start, what was your highest record at temperature? and what was your highest recorded temperature? How do you explain any good equations of flour? Are you explaining how you explain any good equations of flour? Are you explaining any adi-chinaras in terms such as shortness of bread, just me adi-chinaras in terms such as shortness of bread, just being a lot of taste first, man. based on the order of taste first man. Based on your responses, your symptoms are consistent. On your responses, your symptoms are consistent with the possible viral after the respiratory tract infection. With the possible viral after the respiratory tract infection. Come on if you at this time your condition come on if you at this time your condition appears Man if you\'re bull at home for your treatment I recommend Aphearies man if you\'re bull at home for your treatment I recommend the following medications for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first time, for the first for date. said there is been 10 empty word thousand empty for days. Said there is been 10 empty. Take once daily for calls or a day. Take once, take once daily for calls or a day takes in terms. The gondiz Europe\'s or tablets take us direct that takes in terms. The gondiz Europe\'s or tablets take us direct that for cafe relief. In addition to medications, please serve for carefully. In addition to medications, please observe the failure. Get adequate rest and serve the failure. Get adequate rest and avoid the strengthness of the activities. Drink plenty of blood, avoid the strengthness of the activities. Drink plenty of blood which stay hydrated. 1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1.1 I will now generate your prescription and consultation. I will now generate your prescription and consultation. Some are read through the system. Do you have any questions or organisations? Some are read through the system. Do you have any questions or organisations before we end the consultation? Thank you. And I hope you feel that resistance before we end the consultation. Thank you. And I hope you feel that I still want to fuck you. Still want to fuck you.\nof the plane. you you you you you you you you you you\nI think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I think I\n. . . . you\n\n[--- Rejoined Session — Apr 9, 2026 12:54 AM ---]\nThank you for poisoning the parole in theimatum of Alto Horizon Fioreal for treatment sao For no treatment and commit to follow medications, paracetamol by Bandland MG, take one tablet every 4 to 6 hours as needed for fever. maximum of 4,000 mg per day set there is fever maximum of 4,000 mg per day set there is lean 10 mg take once day if we\'re colds or allergic lean 10 mg take once day if we\'re colds or alexine symptoms about these erupts or tablets take a team symptoms about these erupts or tablets take us direct and work off really must direct and work off really yeah yeah\nThank you. Thank you. Thank you. Thank you.','1. Chief Complaint\nThe patient\'s main reason for the visit is a headache and asthma.\n\n2. Symptoms Discussed\nThe patient mentioned having a headache and asthma, and also possibly having a fever and cold or allergic symptoms, as the doctor recommended taking paracetamol and an antihistamine.\n\n3. Doctor\'s Assessment\nThe doctor\'s assessment is that the patient\'s symptoms are consistent with a possible viral respiratory tract infection.\n\n4. Diagnosis (if mentioned)\nNot discussed.\n\n5. Treatment Plan / Prescriptions\nThe doctor prescribed paracetamol, 1000 mg to be taken every 4 to 6 hours as needed for fever, with a maximum of 4000 mg per day, and an antihistamine, possibly 10 mg to be taken once a day for cold or allergic symptoms.\n\n6. Follow-up Instructions\nThe doctor advised the patient to get adequate rest, drink plenty of fluids to stay hydrated, and avoid strenuous activities, and to ask the chatbot for further questions, but no specific follow-up appointment was mentioned.','summary_4.pdf','4_1775666700',0,'2026-04-09 00:52:12'),(5,1,4,'2026-04-09','01:00:00','Teleconsult','Completed','','Paid','2026-04-08 16:57:55','src_8mcoTaeYNwfQwDU6fdWoGGuf','TC-FD475C6F','2026-04-09 00:58:20','[01:10:04 AM] System: Call connected\r\n[01:10:04 AM] System: Call connected\r\n[01:11:17 AM] System: Call ended\n[01:10:04 AM] System: Call connected\r\n[01:10:04 AM] System: Call connected\r\n[01:11:19 AM] System: Call ended\n[01:12:31 AM] System: Call connected\r\n[01:12:31 AM] System: Call connected\r\n[01:13:29 AM] System: Call ended\n[01:12:31 AM] System: Call connected\r\n[01:12:31 AM] System: Call connected\r\n[01:13:32 AM] System: Call ended\n\n[--- Rejoined Session — Apr 9, 2026 1:20 AM ---]\n[01:19:21 AM] System: Call connected\r\n[01:19:21 AM] System: Call connected\r\n[01:20:01 AM] System: Call ended\n[01:19:20 AM] System: Call connected\r\n[01:19:20 AM] System: Call connected\r\n[01:20:04 AM] System: Call ended\n\n[--- Rejoined Session — Apr 9, 2026 1:40 AM ---]\n[01:39:42 AM] System: Call connected\r\n[01:39:42 AM] System: Call connected\r\n[01:40:10 AM] System: Call ended\n[01:39:42 AM] System: Call connected\r\n[01:39:42 AM] System: Call connected\r\n[01:40:12 AM] System: Call ended\n[01:41:55 AM] System: Call connected\r\n[01:41:55 AM] System: Call connected\r\n[01:42:13 AM] System: Call ended\n[01:41:55 AM] System: Call connected\r\n[01:41:55 AM] System: Call connected\r\n[01:42:16 AM] System: Call ended','la gana la gana yeah hello hello wala na puputo parit padgahatan ko yung at puputo parit padgahatan ko yung at hello hello hello hello parang hihihalong siya ni napuputo hello hello hello parang hihihalong siya ni napuputo masahin ko ulit For your treatment, I recommend the following medications. Para set a multi-bohadrid M, I recommend the following medications. Para set a multi-bohadrid MG, take one tablet every 4 to 6 hours as needed. G, take one tablet every 4 to 6 hours as needed for fever. Maximum of 4,000 MG per day. for fever maximum of 4000 mg per day setter is in 10 mg take once daily for colds or allergic allergic symptoms or tablets or allergic allergic symptoms the one this here or tablets take us directed for cop relief take us directed for cop relief in addition to medication please observe the fat in addition to medication please observe the fat get adequate rest and avoid strength activities on simple it rest in avoid strength as activities on the moment hello I call it out hello I call it out one two three\nHuman Services and mortgage. Thank you. Thank you. Thank you. Thank you.\nno no no no no no hello hello hello hello I will now generate your escape gun and consultation summary through the system. Do you have any questions or concerns before we end the consultation? before we end the consultation. Para 10 months, 500 MT. Para 10 months, 500 MT. Take 1 tablet, 4 to 6 arts, as needed for paper. Tablet, 4 to 6 arts, as needed for paper. It\'s not simple of 4,000 MT per day. Set 3. logo hello hello hello long oh hello hello hello Hello.\nNope. Nope. Thank you. Thank you. Thank you.\n\n[--- Rejoined Session — Apr 9, 2026 1:20 AM ---]\nno no no no no thank you and I hope you feel better soon Thank you and I hope you feel better soon. Beats on your heart. Beats on your heart. Antes, your symptoms are consistent. With a possible viral. Antes, your symptoms are consistent. With a possible viral. or a pro respiratory tract infection or commonly flu a pro respiratory tract infection or commonly flu at this time your condition appears manageable at home at this time your condition appears manageable at home okay okay\nare you going to look for Kunga H Luna? NO are you going to look for Kunga H Luna? Thank you. Thank you.\n\n[--- Rejoined Session — Apr 9, 2026 1:40 AM ---]\nHello Hello Hello Hello Hello Hello Hello Hello Hello Hello Hello Hello Hello Hello No Yeah What What I\'m going to attach it.\nHELLO HELLO Hello Hello Hello you hello hello hi hello hello hi hi what what same\nHello Bro.\nLove Love Thank you.','1. Chief Complaint\r\nThe patient\'s main reason for the visit was not clearly stated due to the disjointed and unclear nature of the conversation, but it appears to be related to symptoms that could be consistent with a viral or respiratory tract infection.\r\n\r\n2. Symptoms Discussed\r\nThe symptoms mentioned during the consultation include fever and possibly cold or allergic symptoms, as the doctor mentions medications for these conditions.\r\n\r\n3. Doctor\'s Assessment\r\nThe doctor\'s assessment is that the patient\'s symptoms are consistent with a possible viral or respiratory tract infection, commonly known as the flu, and that the condition appears manageable at home.\r\n\r\n4. Diagnosis (if mentioned)\r\nNot discussed, but the doctor implies a possible viral or respiratory tract infection.\r\n\r\n5. Treatment Plan / Prescriptions\r\nThe doctor recommends the following medications: Paracetamol 500 MG, one tablet every 4 to 6 hours as needed, with a maximum of 4000 mg per day for fever. Additionally, a 10 mg tablet is recommended once daily for colds or allergic symptoms. The patient is also advised to get adequate rest and avoid strenuous activities.\r\n\r\n6. Follow-up Instructions\r\nThe doctor advises the patient to get adequate rest and avoid strenuous activities. There is no mention of a follow-up appointment, but the doctor expresses hope that the patient will feel better soon.','summary_5.pdf','5_1775669400',1,'2026-04-09 02:08:07');
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
) ENGINE=InnoDB AUTO_INCREMENT=140 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctor_schedules`
--

LOCK TABLES `doctor_schedules` WRITE;
/*!40000 ALTER TABLE `doctor_schedules` DISABLE KEYS */;
INSERT INTO `doctor_schedules` VALUES (115,4,'Monday','00:00:00','06:00:00'),(116,4,'Monday','14:30:00','19:30:00'),(117,4,'Monday','22:30:00','23:30:00'),(118,4,'Tuesday','14:30:00','19:30:00'),(119,4,'Wednesday','14:30:00','19:30:00'),(120,4,'Wednesday','22:30:00','23:30:00'),(121,4,'Thursday','00:00:00','23:30:00'),(122,4,'Saturday','17:00:00','23:00:00'),(123,4,'Sunday','17:00:00','20:30:00'),(136,5,'Monday','06:00:00','13:00:00'),(137,5,'Monday','15:00:00','19:00:00'),(138,5,'Friday','06:00:00','14:00:00'),(139,5,'Saturday','06:00:00','14:00:00');
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `doctors`
--

LOCK TABLES `doctors` WRITE;
/*!40000 ALTER TABLE `doctors` DISABLE KEYS */;
INSERT INTO `doctors` VALUES (4,'Ma. Aberlee Lacanaria','General','','EXCELLCARE MEDICAL SYSTEM INC.','almondtofu25@gmail.com','$2y$10$KFzfCMLW/6YVdUWjiYG8b.g0yyUP6ZAW24jy1H7DrbopyR.btv15a','09999999999','uploads/profiles/doc_69b50c372d091.jfif','General doctor at EXCELLCARE MEDICAL SYSTEM INC.',500.00,'Filipino',0.0,'senior','active',1,'','',NULL,NULL,1,1,'2026-03-14 00:39:41',1,NULL,'2026-03-21 07:46:05',1,'2026-03-14 06:46:05','2026-03-14 07:39:41'),(5,'Ian Matthew Payawal','General','Cardiology','EXCELLCARE MEDICAL SYSTEM INC.','dizoah3@gmail.com','$2y$10$/WhcdL6CDFFo7M2w0ynJ4eq0kPxJ.Wnn/Nn4VhHl7.6evTz5DykqW','09999999992','uploads/profiles/doc_69bd4ead3abc2.png','Cardiology subspecialty',750.00,'English, Filipino',0.0,'junior','active',1,'','','uploads/docs/doc_5_69d7b40d58257.exe',NULL,1,0,'2026-04-09 08:13:33',1,NULL,'2026-03-22 11:33:12',1,'2026-03-15 10:33:12','2026-04-09 14:19:46');
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
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `patients`
--

LOCK TABLES `patients` WRITE;
/*!40000 ALTER TABLE `patients` DISABLE KEYS */;
INSERT INTO `patients` VALUES (1,'John Noel Oraño','2004-12-10','Male','johnnoelorano@gmail.com','09923139504','uploads/profiles/patient_69ad35693609a.png','','','','$2y$10$wQnaZBDlwvLZ4x71lZFcweFP0.3rbD5BSR91Tj88UUCGs3DDk/wma','',NULL,'NPC KANAN MAKISIG STREET BARANGAY 171','CALOOCAN CITY','Philippines','','','English','2026-03-08 06:11:37','2026-04-04 07:28:33',1,NULL,NULL,'050d738f352fb8f8c427aa61c4626ba2589063d03fc95193085674eb313e6692','2026-04-04 16:28:33',1),(5,'Cid Kagenou','2011-03-01','Male','cidkag1210@gmail.com','09123456789','uploads/profiles/patient_69b66c6f61c19.png','','','','$2y$10$X.uZM7Zu/WPogMFGiCEIjOIDyRQYm.7vchIVgaamtNEZgdaQPjByO','',NULL,'Hillcrest Village Gate 1','Caloocan City','Philippines','','','English','2026-03-15 08:00:28','2026-03-29 18:50:03',1,NULL,NULL,NULL,NULL,1),(6,'John Nowel','2008-04-03','Male','sumivalo10@gmail.com','+639923139504',NULL,'','','','$2y$10$z3EWoneDOMdsJezI/0cgBOvGTgWjbPwYxSLOE0SgoZhOY5dHq46YG','',NULL,'','City of Caloocan','Philippines','','','English','2026-04-04 07:07:34','2026-04-04 07:18:53',0,'9d01ef1f345fbe20b2875603736c41a22457ab33fba251d3182fe89238061f0b','2026-04-05 09:18:53',NULL,NULL,1);
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
  `role` enum('receptionist','coordinator','supervisor') NOT NULL DEFAULT 'receptionist',
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
INSERT INTO `staff_accounts` VALUES (4,'TELE-CARE Staff','staff@telecare.com','$2y$10$YnlzuMjQm2ntktIn7faJ9eOWpWDQwGxNhLOk5hmquxEvebnJ/Ko7G','receptionist','active','2026-03-22 15:47:28');
/*!40000 ALTER TABLE `staff_accounts` ENABLE KEYS */;
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

-- Dump completed on 2026-04-10  0:50:12
