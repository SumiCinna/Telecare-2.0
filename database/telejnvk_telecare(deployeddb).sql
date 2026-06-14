-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 12, 2026 at 03:38 PM
-- Server version: 11.4.10-MariaDB-cll-lve
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `telejnvk_telecare`
--

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `full_name`, `email`, `password`, `created_at`) VALUES
(1, 'Admin', 'admin@telecare.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2026-03-08 07:01:28');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `type` enum('General','Follow-up','Emergency','Teleconsult') DEFAULT 'Teleconsult',
  `status` enum('Pending','DoctorApproved','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `rejection_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `payment_status` enum('Unpaid','Paid') DEFAULT 'Unpaid',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `paymongo_link_id` varchar(100) DEFAULT NULL,
  `receipt_number` varchar(30) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `chat_log` text DEFAULT NULL,
  `consultation_transcript` text DEFAULT NULL,
  `consultation_summary` text DEFAULT NULL,
  `summary_pdf_path` varchar(255) DEFAULT NULL,
  `summary_session_key` varchar(50) DEFAULT NULL,
  `summary_edited` tinyint(1) DEFAULT 0,
  `summary_reviewed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `type`, `status`, `rejection_reason`, `notes`, `payment_status`, `created_at`, `paymongo_link_id`, `receipt_number`, `paid_at`, `chat_log`, `consultation_transcript`, `consultation_summary`, `summary_pdf_path`, `summary_session_key`, `summary_edited`, `summary_reviewed_at`) VALUES
(1, 1, 4, '2026-04-13', '02:30:14', 'Teleconsult', 'Cancelled', 'Schedule conflict', NULL, 'Unpaid', '2026-04-12 18:30:14', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(2, 8, 4, '2026-04-13', '02:30:28', 'Teleconsult', 'Confirmed', NULL, NULL, 'Paid', '2026-04-12 18:30:28', 'src_3qp23gY1UY4rvKVwTihsKd2S', 'TC-A149876F', '2026-04-12 14:31:16', '[02:32:48 AM] System: Call connected\r\n[02:32:48 AM] System: Call connected\r\n[02:34:48 AM] System: Call ended\n[02:32:51 AM] System: Call connected\r\n[02:32:51 AM] System: Call connected\r\n[02:34:54 AM] System: Call ended', 'What? What? What? What? Game? Game? Game? Game? Magandang araw po. Ako si Dr. Santos. Game? Good morning, I\'m Dr. Santos. I\'m your doctor now. Can I know your name? I\'m your doctor now. Can I know your name? How are you, Gerson? What are you thinking now? Person, what are you thinking now? When did you start your own Oboh at Lagnat? When did you start your Oboh at Lagnat? May kasama po ba itong sipon, pananakit ng katawan o hirap sa paghinga? Sige po, may iniinom na po ba kayong gamot ngayon? Base po sa inyo, iniinom na po ba kayong gamot ngayon? Based on your symptoms, you may have a viral infection like trancaso. Imoongkahiko po ang mga sumusunod. Uminom ng maraming tubig. Imoongkahiko po ang mga sumusunod. Uminom ng maraming tubig. Magpahinga ng sapat. Uminom ng paracetamol para sa lagnat. Ayon sa tamang dosage. Pahinga ng sapat. Uminom ng paracetamol para sa lagnat. Ayon sa tamang dosage. If you don\'t have a problem with 3 days, or if you don\'t have a problem with 5 days, or if you don\'t have a problem with 5 days, Right up, Heyerta from speaking to us agad Walang ano man po. Sa mga susunod na tanong ay kausapin mo lang ang chatbot. Sa mga susunod na tanong ay kausapin mo lang ang chatbot. And to, isa pa sa kabilis. And to, isa pa sa kabilis. Isa pang call.\nNot wait lang. Game. Go. I Jerson Sagun I Jerson Sagun I don know 5 days 5 days 5 days 5 days 5 days 5 days Opo may sipon po at medyo masakit ang katawan Opo may sipon po at medyo masakit ang katawan Wala pa po, Dok. Wala pa po, Dok. Thank you. Thank you very much. Ako po, Doc. Salamat po ulit. Ako po, Doc. Salamat po ulit. End na to. End na to. See you. See you.', '1. Chief Complaint\nThe patient\'s main reason for the visit is not explicitly stated, but it can be inferred that the patient is experiencing symptoms such as Oboh (possibly referring to cough) and Lagnat (fever).\n\n2. Symptoms Discussed\nThe symptoms mentioned during the consultation include Oboh (cough), Lagnat (fever), and the doctor also inquired about the presence of sipon (cold), pananakit ng katawan (body pain), and hirap sa paghinga (difficulty breathing).\n\n3. Doctor\'s Assessment\nThe doctor\'s clinical observations and findings suggest that the patient may have a viral infection, such as trancaso (possibly referring to a common cold or flu).\n\n4. Diagnosis (if mentioned)\nNot discussed, but the doctor suspects a viral infection.\n\n5. Treatment Plan / Prescriptions\nThe doctor recommended the patient to drink plenty of water, take paracetamol for the fever according to the correct dosage, and get enough rest.\n\n6. Follow-up Instructions\nThe doctor did not explicitly mention any follow-up appointments, but advised the patient to contact them again if the symptoms persist for 3-5 days, and also instructed the patient to direct any further questions to the chatbot.', 'summary_2.pdf', '2_1776018600', 0, NULL),
(3, 1, 4, '2026-04-13', '02:37:42', 'Teleconsult', 'Pending', NULL, NULL, 'Unpaid', '2026-04-12 18:37:42', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(4, 8, 4, '2026-04-13', '02:37:52', 'Teleconsult', 'Confirmed', NULL, NULL, 'Paid', '2026-04-12 18:37:52', 'src_dctyBkVDZsuc1EvAyDJHMwEm', 'TC-9E923EF3', '2026-04-12 14:39:37', '[02:40:24 AM] System: Call connected\r\n[02:40:24 AM] System: Call connected\r\n[02:43:38 AM] System: Call ended\n[02:40:27 AM] System: Call connected\r\n[02:40:27 AM] System: Call connected\r\n[02:43:45 AM] System: Call ended', 'On my card that\'s on me On my card that\'s on me What we missed What we missed Where the hell is that? Eight lang. Mungi ito. Where the hell is that? Eight lang. Mungi ito. Okay. Game game. Okay. Game game. Good day, I\'m Dr. Santos. I will be your attending physician for today\'s consultation. Before we begin, please confirm your full name and age for verification. Thank you I understand that you are experiencing fever sore throat headache Thank you I understand that you are experiencing fever sore throat headache and mild cough I will ask a few questions to better assess your condition I will ask a few questions to better assess your condition When did your symptoms start And what was your highest recorded temperature When did your symptoms start And what was your highest recorded temperature Have you taken any medication so far Are you experiencing any additional symptoms such as shortness of breath Chest pain or loss of taste or smell Thank you Okay thank you Based on your responses, your symptoms are consistent with the possible viral upper respiratory tract infection or commonly called flu. At this time, respiratory tract infection or commonly called flu. At this time, your condition appears manageable at home. For your treatment, I recommend the following medications. Paracetamol 500mg, take one tablet every 4-6 hours as needed for fever. Maximum of 4,000 mg tablet every 4 to 6 hours as needed for fever. Maximum of 4,000 mg per day. Setirizine 10 mg take once daily for colds or allergic symptoms. Lagundi syrup or tablets take as directed for cough allergic symptoms. Lagundi syrup or tablets take as directed for cough relief. In addition to medication, please observe the following. Get adequate rest and avoid strenuous activities. Drink plenty of fluids to stay hydrated. Monitor your temperature regularly. If your symptoms to stay hydrated, monitor your temperature regularly If your symptoms worsen or persist beyond 3-5 days Or if you experience difficulty breathing, seek immediate medical attention Difficulty breathing, seek immediate medical attention I will now generate your prescription and consultation summary through the system. Do you have any questions or concerns before we end the consultation? For further assistance, please end the consultation. For further assistance, please ask the chatbot if you have questions about the system.\nHey, Gagi. Wait, wait, wait. Wala pa lang ano yan? Hey, Gagi. Wait, wait, wait. Wala pa lang ano yan? Wala atang ano tawag dito? Wala ata itong patient? Wala atang ano tawag dito? wala ata itong patient sige ako na lang mag prompt, imprompto na lang ako sige ako na lang mag prompt imprompto na lang ako game game I\'m Jason Sagun 21 years old I\'m Jason Sagun 21 years old I I feeling I I feeling weak I have a fever and I cold having sore throat and headache I have a fever and I cold having sore throat and headache Thank you I started to I started having symptoms last week ago and my highest recorded temperature was around 39 degrees I have not taken any medication so far and I only experiencing high fever I have been experiencing high fever so far and I only experiencing high fever Thank you. Thank you. Thank you. Thank you, Doc. Thank you Doc. No. No. No I\'m okay I\'m okay Okay.', '1. Chief Complaint\nThe patient\'s main reason for the visit is experiencing fever, sore throat, headache, and mild cough.\n\n2. Symptoms Discussed\nThe symptoms mentioned during the consultation include fever, sore throat, headache, mild cough, and the patient was also asked about shortness of breath, chest pain, or loss of taste or smell.\n\n3. Doctor\'s Assessment\nThe doctor assessed the patient\'s symptoms as consistent with a possible viral upper respiratory tract infection, commonly called flu, and determined that the condition appears manageable at home.\n\n4. Diagnosis (if mentioned)\nThe diagnosis given is a possible viral upper respiratory tract infection or commonly called flu.\n\n5. Treatment Plan / Prescriptions\nThe treatment plan includes the following medications: Paracetamol 500mg to be taken every 4-6 hours as needed for fever, with a maximum of 4,000 mg per day, Setirizine 10 mg to be taken once daily for colds or allergic symptoms, and Lagundi syrup or tablets to be taken as directed for cough relief. In addition to medication, the patient is advised to get adequate rest, avoid strenuous activities, drink plenty of fluids to stay hydrated, and monitor their temperature regularly.\n\n6. Follow-up Instructions\nThe patient is advised to seek immediate medical attention if their symptoms worsen or persist beyond 3-5 days, or if they experience difficulty breathing. The patient can also ask the chatbot for further assistance or questions about the system.', 'summary_4.pdf', '4_1776018600', 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `appointment_logs`
--

CREATE TABLE `appointment_logs` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `action` varchar(60) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `appointment_logs`
--

INSERT INTO `appointment_logs` (`id`, `appointment_id`, `staff_id`, `action`, `notes`, `created_at`) VALUES
(1, 2, 4, 'Approved', '', '2026-04-12 14:30:38'),
(2, 4, 4, 'Approved', '', '2026-04-12 14:38:29');

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `subspecialty` varchar(100) DEFAULT NULL,
  `clinic_name` varchar(150) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `languages_spoken` varchar(255) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 0.0,
  `access_level` enum('junior','senior','consultant') DEFAULT 'junior',
  `status` enum('pending','active','inactive') DEFAULT 'pending',
  `is_available` tinyint(1) DEFAULT 0,
  `license_number` varchar(100) DEFAULT NULL,
  `issuing_board` varchar(150) DEFAULT NULL,
  `license_file` varchar(255) DEFAULT NULL,
  `board_cert_file` varchar(255) DEFAULT NULL,
  `consent_signed` tinyint(1) DEFAULT 0,
  `is_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `invite_token` varchar(100) DEFAULT NULL,
  `invite_expires` datetime DEFAULT NULL,
  `setup_complete` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `full_name`, `specialty`, `subspecialty`, `clinic_name`, `email`, `password`, `phone_number`, `profile_photo`, `bio`, `consultation_fee`, `languages_spoken`, `rating`, `access_level`, `status`, `is_available`, `license_number`, `issuing_board`, `license_file`, `board_cert_file`, `consent_signed`, `is_verified`, `verified_at`, `verified_by`, `invite_token`, `invite_expires`, `setup_complete`, `created_at`, `updated_at`) VALUES
(4, 'Ma. Aberlee Lacanaria', 'General', '', 'EXCELLCARE MEDICAL SYSTEM INC.', 'almondtofu25@gmail.com', '$2y$10$KFzfCMLW/6YVdUWjiYG8b.g0yyUP6ZAW24jy1H7DrbopyR.btv15a', '09999999999', 'uploads/profiles/doc_69db96632aac7.jpg', 'General doctor at EXCELLCARE MEDICAL SYSTEM INC.', 500.00, 'Filipino', 0.0, 'senior', 'active', 1, '', '', NULL, NULL, 1, 1, '2026-03-14 00:39:41', 1, NULL, '2026-03-21 07:46:05', 1, '2026-03-14 06:46:05', '2026-04-12 12:56:03'),
(5, 'Ian Matthew Payawal', 'General', 'Cardiology', 'EXCELLCARE MEDICAL SYSTEM INC.', 'dizoah3@gmail.com', '$2y$10$/WhcdL6CDFFo7M2w0ynJ4eq0kPxJ.Wnn/Nn4VhHl7.6evTz5DykqW', '09999999992', 'uploads/profiles/doc_69db96403d71f.jpg', 'Cardiology subspecialty', 900.00, 'English, Filipino', 0.0, 'junior', 'active', 1, '', '', 'uploads/docs/doc_5_69d7b40d58257.exe', NULL, 1, 0, '2026-04-09 08:13:33', 1, NULL, '2026-03-22 11:33:12', 1, '2026-03-15 10:33:12', '2026-04-12 14:07:19'),
(6, 'Ian Santos', 'Neurologist', '', NULL, 'jaimepayawal17@gmail.com', NULL, NULL, NULL, NULL, 0.00, NULL, 0.0, 'junior', 'pending', 0, NULL, NULL, NULL, NULL, 0, 0, NULL, NULL, '743643bef9fe3b33ef4d8619c5d967a7fa564482087bf40a04840f458d26b1ec', '2026-04-19 12:35:02', 0, '2026-04-12 12:35:02', '2026-04-12 12:35:02');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_schedules`
--

CREATE TABLE `doctor_schedules` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `day_of_week` enum('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `doctor_schedules`
--

INSERT INTO `doctor_schedules` (`id`, `doctor_id`, `day_of_week`, `start_time`, `end_time`) VALUES
(136, 5, 'Monday', '06:00:00', '13:00:00'),
(137, 5, 'Monday', '15:00:00', '19:00:00'),
(138, 5, 'Friday', '06:00:00', '14:00:00'),
(139, 5, 'Saturday', '06:00:00', '14:00:00'),
(183, 4, 'Monday', '06:00:00', '18:00:00'),
(184, 4, 'Tuesday', '06:00:00', '18:00:00'),
(185, 4, 'Wednesday', '06:00:00', '18:00:00'),
(186, 4, 'Thursday', '06:00:00', '18:00:00'),
(187, 4, 'Friday', '06:00:00', '18:00:00'),
(188, 4, 'Saturday', '08:00:00', '22:00:00'),
(189, 4, 'Monday', '00:00:00', '06:00:00'),
(190, 4, 'Sunday', '08:00:00', '22:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `lab_results`
--

CREATE TABLE `lab_results` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `doc_type` enum('lab_result','prescription','unknown') DEFAULT 'unknown',
  `doc_label` varchar(200) DEFAULT NULL,
  `extracted_text` longtext DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `lab_results`
--

INSERT INTO `lab_results` (`id`, `patient_id`, `file_path`, `doc_type`, `doc_label`, `extracted_text`, `uploaded_at`) VALUES
(1, 1, 'uploads/ocr/ocr_69aee8a9b1de2.pdf', 'prescription', 'Prescription 1', '--- Page 1 ---\nTELE-CARE Medical Clinic\n123 Mabini Street, Caloocan City, Metro Manila | Tel: (02) 8123-4567\ntelecaremedical@telecare.com\n\nPATIENT NAME: John Noel Orano DATE: March 9, 2026\nAGE / SEX: 22 years old / Male PATIENT ID: TC-2026-0042\nADDRESS: NPC Kanan Makisig St., Brgy. 171, Caloocan City\nPHONE: 09923139504\n1. Amoxicillin 500mg Capsule\n\nSig: Take 1 capsule every 8 hours for 7 days\n\nDispense: 21 capsules | Refills: 0\n2. Metformin 500mg Tablet\n\nSig: Take 1 tablet twice daily with meals (morning and evening)\n\nDispense: 60 tablets | Refills: 2\n3. Losartan Potassium 50mg Tablet\n\nSig: Take 1 tablet once daily in the morning\n\nDispense: 30 tablets | Refills: 3\nNOTES:\nPatient advised to take medications with food. Avoid alcohol while on Amoxicillin. Monitor blood sugar levels daily. Return for\nfollow-up after 2 weeks or immediately if symptoms worsen.\n\nDr. Maria Santos, MD\nCardiologist | PRC Lic. No. 0123456\nSt. Luke Medical Wellness Center\nThis prescription is valid for 30 days from the date of issue. | TELE-CARE © 2026', '2026-03-09 15:35:10'),
(2, 5, 'uploads/ocr/ocr_69b66f98ab067.pdf', 'prescription', 'Prescription 1', '--- Page 1 ---\n321 Quezon Avenue, Quezon City, Metro Manila | Tel: (02) 8321-9876\ninfo@excellcare.com | www.excellcare.com\nPATIENT NAME: ~— Bernard Dela Cruz Santos DATE: March 15, 2026\nAGE / SEX: 34 years old / Male PATIENT == TC-2026-0155\nID:\nDIAGNOSIS: Upper Respiratory Tract Infection, PHONE: 09561234567\nAsthma\nALLERGY: Sulfonamides (avoid)\nRx\n1. Azithromycin 500mg Tablet\nSig: Take 1 tablet once daily for 5 days\nDispense: 5 tablets | Refills: 0\nWARNING: Complete the full course even if feeling better. Avoid antacids within 2 hours of taking.\n2. Salbutamol 2mg Tablet\nSig: Take 1 tablet three times daily (morning, afternoon, and evening)\nDispense: 30 tablets | Refills: 2\nWARNING: May cause tremors or increased heart rate. Do not exceed prescribed dose. Avoid caffeine.\n3. Montelukast 10mg Tablet\nSig: Take 1 tablet once daily at bedtime\nDispense: 30 tablets | Refills: 3\nWARNING: Report any changes in mood or behavior immediately. Do not stop without consulting your doctor.\n4. Cetirizine 10mg Tablet\nSig: Take 1 tablet once daily at night\nDispense: 14 tablets | Refills: 1\nWARNING: May cause drowsiness. Avoid driving or operating machinery after taking. Avoid alcohol.\n5. Paracetamol 500mg Tablet\nSig: Take 1-2 tablets every 4 to 6 hours as needed for fever or pain. Do not exceed 8 tablets per day.\nDispense: 20 tablets | Refills: 0\nWARNING: Do not take with other paracetamol-containing products. Avoid alcohol. Urgent — stop\nimmediately if yellowing of skin or eyes occurs.\nNOTES:\nPatient advised to rest and increase fluid intake. Steam inhalation 2-3 times daily recommended. Avoid\ncold drinks and air-conditioned environments. Use Salbutamol as rescue medication during acute asthma\nattack. Return immediately if dysonea worsens or fever persists beyond 3 days. Follow-up in 1 week.\nDr. Ma. Aberlee Lacanaria\n\n--- Page 2 ---\nGeneral Practitioner | PRC Lic. No. 0312456\nEXCELLCARE MEDICAL SYSTEM INC.\nThis prescription is valid for 30 days from the date of issue. | EXCELLCARE © 2026', '2026-03-15 08:36:48'),
(3, 1, 'uploads/ocr/ocr_69d91f898d5d8.png', 'unknown', '', 'Excell	tare', '2026-04-10 16:04:27'),
(4, 1, 'uploads/ocr/ocr_69d922c2a2756.png', 'lab_result', '', 'MediCore Diagnostics	Date Collected: April 10, 2026	LABORATORY RESULTS	\r\nADVANCED LABORATORY SCIENCES	\r\nDate Released: April 11, 2026	\r\n› 123 Medical Plaza, Quezon City, Philippines	Specimen: Whole Blood / Serum	\r\n(+63 2 8888-7777 |lab@medicore.ph	Lab Code: MC-2026-04-8821	\r\nComplete Blood Count + Lipid Panel	LAB #8821	\r\nPATIENT INFORMATION	\r\nFULL NAME	\r\nJuan dela Cruz	\r\nDATE OF BIRTH	\r\nMarch 15, 1985	\r\nAGE | SEX	\r\n41 years / Male	\r\nPHILHEALTH NO.	\r\n04-123456789-0	\r\nREQUESTING PHYSICIAN	\r\nDr. Maria Santos, MD	\r\nDEPARTMENT	\r\nInternal Medicine	\r\nFASTING STATUS	\r\n10 Hours Fasting	\r\nPATIENT ID	\r\nPT-2026-00441	\r\nTEST RESULTS	\r\nTEST NAME	RESULT	REFERENCE RANGE	UNITS	STATUS	\r\nCOMPLETE BLOOD COUNT (CBC)	\r\nHemoglobin	14.8	13.5 - 17.5	g/dL	Normal	\r\nHematocrit	44.2	41.0 - 53.0	Normal	\r\nWhite Blood Cells	11.3 H	4.5 - 11.0	x10%uL	High	\r\nPlatelets	198	150 - 400	x10%uL	Normal	\r\nRed Blood Cells	5.1	4.5 - 6.5	×10/uL	Normal	\r\nLIPID PANEL	\r\nTotal Cholesterol	218 H	< 200	mg/dL	High	\r\nLDL Cholesterol	142 H	< 130	mg/dL	High	\r\nHDL Cholesterol	38 L	> 40	mg/dL	Low	\r\nTriglycerides	165	< 150	mg/dL	Borderline	\r\nBLOOD GLUCOSE	\r\nFasting Blood Glucose	95	70 - 99	mg/dL	Normal	\r\nHbAlc	5.6	< 5.7	%	Normal	\r\nNotes & Remarks	\r\nElevated WBC may indicate early infection or stress response. Recommend follow-up.	\r\nDr. Elena Reyes, MD, FPSP	\r\nLipid panel results suggest dietary modification and possible statin therapy.	Medical Laboratory Director	\r\nPlease correlate with clinical findings.	Lic. No. 0041233	\r\nMediCore Diagnostics - Accredited by DOH Philippines	Page 1 of 1 | Generated: April 11, 2026 08:34 AM', '2026-04-10 16:18:13'),
(5, 1, 'uploads/ocr/ocr_69d92422b783e.jpg', 'lab_result', 'jerson', 'AMed Cares	MEDICAL CENTER CO.	#3 Genesis St. Monticello Subdivision Brgy. 177 Caloocan City	Landmark: Near Zabarte Road after Coca Cola Warehouse	Clinic Hours: Monday-Saturday 7AM to 5PM / Sunday: 7AM to 3PM	\r\n09498805343 / 09280369679 / 0285367861 medcares.mcdc@gmail.com	\r\nDOH ACCREDITED (LIC NO: 13-0645-26-CL-2)	\r\nHEMATOLOGY	\r\nNAME:	SAGUN, JERSON	AGE:	21	DATE:	\r\n03/20/2026	\r\nPHYSICIAN: DR. FIESTA	GENDER:	M	REF. NO:	\r\nCOMPONENT	RESULT	NORMAL VALUE	COMPONENT	\r\nRESULT	NORMAL VALUE	\r\nHemoglobin	158	M=140-180 F=120-160 g/L	Platelet Count	349	\r\n150 - 450 × 10°/L	\r\nHematocrit	0.49	M=0.40-0.54 F=0.37-0.47%	Reticulocytes	\r\n0.5 - 1.5%	\r\nErythrocytes	5.73	M=4.6-6.2 F=4.2-5.4 x 10\"/L	ESR	\r\nM=0-10mm/hr	\r\nWBC	5.1	5 - 10 × 10/L	\r\nF=0-20mm/hr	\r\nDEFFERENTIAL COUNT	\r\nSegmenters	0.62	0.45 - 0.65	MCV	86.1	\r\n80-95 Fl	\r\nLymphocytes	0.28	0.25 - 0.35	MCH	27.6	26-34 Pg	\r\nMonocytes	0.08	0.0 - 0.08	MCHC	321	\r\n320-360 g/L	\r\nEosinophils	0.02	0.0 - 0.04	Blood Type	\r\nBasophils	0.0 - 0.01	RH	\r\nUses Auto-Hematology Analyzer Bio ELAB EC 38	\r\nsecret BotoN RAT	Verified by:	FAITH ELIJAP	A BLONES, RMIT	Mostmo L. Pronto, M.D., F.PS.P.	\r\nLIC NO. 0119990	HC NO. 0130086	Lic. No. S6740	\r\nMedical Technologist	Medical Technologist	Pathologist', '2026-04-10 16:24:05'),
(6, 1, 'uploads/ocr/ocr_69d9246769f8c.jpg', 'lab_result', 'sagun', 'A Med Cares	MEDICAL CENTER CO.	• 12 Genesis St. Monticello Subdivision Brgy. 177 Caloocan City	© Clinic Hours: Monday-Saturday 7AM to 5PM / Sunday: 7AM to 3PM	Landmark: Near Zabarte Road after Coca Cola Warehouse	\r\nL 09498805343 / 09280369679/0285367861 medcares.mcdc@gmail.com	\r\nName of Patient: SAGUN,JERSON	Age: 21 Sex: MALE	\r\nReferred by:	Date: 03-20-2026	\r\nEXAMINATION: CHEST X-RAY	Case no: 26-2115	\r\nRADIOLOGY REPORT	\r\nCHEST X-RAY (PA VIEW)	\r\nNo active parenchymal infiltrates are seen.	\r\nHeart is not enlarged.	\r\nHemi diaphragm and bony thorax are unremarkable.	\r\nIMPRESSION:	\r\nNO SIGNIFICANT CHEST FINDINGS.	\r\nREYNALDO B. PAUSANOS MD, FPCR	\r\nRadiologist/Sonologist	\r\nLic #0048787	\r\nle radiologic interpretation is only part of the over-all assessment of the patient\'s present condition at the time	\r\nexamination. The report is best interpreted by the attending physician in correlation with clinical, laboratory,	\r\nI other diagnostic test results.', '2026-04-10 16:25:13'),
(7, 1, 'uploads/ocr/ocr_69d92722004cf.pdf', 'prescription', '', '--- Page 1 ---\nEXCELLCARE MEDICAL SYSTEM INC.	\r\n321 Quezon Avenue, Quezon City, Metro Manila I Tel: (02) 8321-9876	\r\ninfo@excellcare.com | www.excellcare.com	\r\nPATIENT NAME:	Bernard Dela Cruz Santos	DATE:	March 15, 2026	\r\nAGE / SEX:	PATIENT	TC-2026-0155	\r\n34 years old / Male	\r\nID:	\r\nDIAGNOSIS:	PHONE:	09561234567	\r\nUpper Respiratory Tract Infection,	\r\nAsthma	\r\nALLERGY:	Sulfonamides (avoid)	\r\nRx	\r\n1. Azithromycin 500mg Tablet	\r\nSig: Take 1 tablet once daily for 5 days	\r\nDispense: 5 tablets I Refills: 0	\r\nWARNING: Complete the full course even if feeling better. Avoid antacids within 2 hours of taking.	\r\n2. Salbutamol 2mg Tablet	\r\nSig: Take 1 tablet three times daily (morning, afternoon, and evening)	\r\nDispense: 30 tablets | Refills: 2	\r\nWARNING: May cause tremors or increased heart rate. Do not exceed prescribed dose. Avoid caffeine.	\r\n3. Montelukast 10mg Tablet	\r\nSig: Take 1 tablet once daily at bedtime	\r\nDispense: 30 tablets | Refills: 3	\r\nWARNING: Report any changes in mood or behavior immediately. Do not stop without consulting your doctor.	\r\n4. Cetirizine 10mg Tablet	\r\nSig: Take 1 tablet once daily at night	\r\nDispense: 14 tablets | Refills: 1	\r\nWARNING: May cause drowsiness. Avoid driving or operating machinery after taking. Avoid alcohol.	\r\n5. Paracetamol 500mg Tablet	\r\nSig: Take 1-2 tablets every 4 to 6 hours as needed for fever or pain. Do not exceed 8 tablets per day.	\r\nDispense: 20 tablets I Refills: 0	\r\nWARNING: Do not take with other paracetamol-containing products. Avoid alcohol. Urgent - stop	\r\nimmediately if yellowing of skin or eyes occurs.	\r\nNOTES:	\r\nPatient advised to rest and increase fluid intake. Steam inhalation 2-3 times daily recommended. Avoid	\r\ncold drinks and air-conditioned environments. Use Salbutamol as rescue medication during acute asthma	\r\nattack. Return immediately if dyspnea worsens or fever persists beyond 3 days. Follow-up in 1 week.	\r\nDr. Ma. Aberlee Lacanaria\n\n--- Page 2 ---\nGeneral Practitioner I PRC Lic. No. 0312456	\r\nEXCELLCARE MEDICAL SYSTEM INC.	\r\nThis prescription is valid for 30 days from the date of issue. I EXCELLCARE © 2026', '2026-04-10 16:36:52');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `sender_type` enum('patient','doctor') NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_type` enum('patient','doctor') NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `sent_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `sender_type`, `sender_id`, `receiver_type`, `receiver_id`, `message`, `is_read`, `sent_at`) VALUES
(1, 'doctor', 4, 'patient', 1, '0', 1, '2026-03-14 07:16:19'),
(2, 'doctor', 4, 'patient', 1, '0', 1, '2026-03-14 07:16:30'),
(3, 'doctor', 4, 'patient', 1, 'Hello', 1, '2026-03-14 07:17:54'),
(4, 'patient', 1, 'doctor', 4, 'Hello doc', 1, '2026-03-14 07:20:37'),
(5, 'doctor', 4, 'patient', 5, 'How are you?', 1, '2026-03-15 08:39:36');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
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
  `google_id` varchar(50) DEFAULT NULL,
  `auth_provider` enum('manual','google') NOT NULL DEFAULT 'manual',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `deactivation_reason` varchar(500) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `full_name`, `date_of_birth`, `gender`, `email`, `phone_number`, `profile_photo`, `emergency_name`, `emergency_relationship`, `emergency_number`, `password`, `security_question`, `security_answer`, `home_address`, `city`, `country_region`, `insurance_provider`, `insurance_policy_no`, `preferred_language`, `google_id`, `auth_provider`, `created_at`, `updated_at`, `is_verified`, `verification_token`, `token_expires_at`, `reset_token`, `reset_expires`, `is_active`, `deactivation_reason`) VALUES
(1, 'John Noel Oraño', '2004-12-10', 'Male', 'johnnoelorano@gmail.com', '09923139504', 'uploads/profiles/patient_69db968263174.jpg', '', '', '', '$2y$10$wQnaZBDlwvLZ4x71lZFcweFP0.3rbD5BSR91Tj88UUCGs3DDk/wma', '', NULL, 'NPC KANAN MAKISIG STREET BARANGAY 171', 'CALOOCAN CITY', 'Philippines', '', '', 'English', NULL, 'manual', '2026-03-08 06:11:37', '2026-04-12 12:56:34', 1, NULL, NULL, '050d738f352fb8f8c427aa61c4626ba2589063d03fc95193085674eb313e6692', '2026-04-04 16:28:33', 1, NULL),
(5, 'Cid Kagenou', '2011-03-01', 'Male', 'cidkag1210@gmail.com', '09123456789', 'uploads/profiles/patient_69b66c6f61c19.png', '', '', '', '$2y$10$X.uZM7Zu/WPogMFGiCEIjOIDyRQYm.7vchIVgaamtNEZgdaQPjByO', '', NULL, 'Hillcrest Village Gate 1', 'Caloocan City', 'Philippines', '', '', 'English', NULL, 'manual', '2026-03-15 08:00:28', '2026-03-29 18:50:03', 1, NULL, NULL, NULL, NULL, 1, NULL),
(6, 'John Nowel', '2008-04-03', 'Male', 'sumivalo10@gmail.com', '+639923139504', NULL, '', '', '', '$2y$10$z3EWoneDOMdsJezI/0cgBOvGTgWjbPwYxSLOE0SgoZhOY5dHq46YG', '', NULL, '', 'City of Caloocan', 'Philippines', '', '', 'English', NULL, 'manual', '2026-04-04 07:07:34', '2026-04-04 07:18:53', 0, '9d01ef1f345fbe20b2875603736c41a22457ab33fba251d3182fe89238061f0b', '2026-04-05 09:18:53', NULL, NULL, 1, NULL),
(7, 'Andrei Mallari Mesa', '2004-06-30', 'Male', 'mesa.andreibsis2023@gmail.com', '+639660138508', 'uploads/profiles/patient_69d8193138dee0.34688495.jpg', '', '', '', '$2y$10$tMeqm2GGPbdgvasmHW76N.NJZU3K59uSkqxvQ45zcONw3ye9VvsNi', '', NULL, '', 'City of Caloocan', 'Philippines', '', '', 'English', NULL, 'manual', '2026-04-09 21:25:05', '2026-04-12 15:48:56', 1, NULL, NULL, '3a4a87d065c36ac0ed1c90b813f4a1f1b4bf358c9befae0b231539654d4f96f3', '2026-04-12 12:48:56', 1, NULL),
(8, 'Jerson Sagun', '2004-10-15', 'Male', 'sagun.jersonbsis2023@gmail.com', '+639602059511', NULL, '', '', '', '$2y$10$7UwJWbzttwcR1VwTIGkxyebq5TYE8KJSeJL76i5GmrIminGcF60YW', '', NULL, 'Block 11, Lot 5 Interpolate St., Senate Village - Phase 1', 'City of Caloocan', 'Philippines', '', '', 'English', NULL, 'manual', '2026-04-10 08:19:24', '2026-04-10 08:20:00', 1, NULL, NULL, NULL, NULL, 1, NULL),
(9, 'Jam rhia saggy', '2006-12-18', 'Male', 'sagunjames24@gmail.com', '+639212757413', NULL, 'yaa', '', '', '$2y$10$XJ8bQwlGELogfc9tvGJAluvTBcop3pEDHL/o1ij/LT0Uvll6EbOFG', '', NULL, '', 'City of Caloocan', 'Philippines', '', '', 'English', NULL, 'manual', '2026-04-10 11:08:40', '2026-04-12 19:16:21', 1, NULL, NULL, NULL, NULL, 0, 'Duplicate account detected.'),
(10, 'dadfa sdf ASD', '2001-10-10', 'Male', 'ASFDDAF@GMAIL.COM', '+639228441011', NULL, '', '', '', '$2y$10$9xvef7.RYABVmbiasb7oTe22K/vT/NXGEvQv4JmIT7k2dFSmIH5WG', '', NULL, 'Interpolate St.', '', 'Philippines', '', '', 'English', NULL, 'manual', '2026-04-10 12:08:33', '2026-04-12 19:16:29', 0, '38174465c081ac70be5d95a548e256462ea23e6535ffed4da572ef81e7173f4e', '2026-04-11 12:08:33', NULL, NULL, 0, 'Suspicious or fraudulent account activity detected.'),
(11, 'ey ria', '2008-04-09', 'Female', 'xdreiaawe@gmail.com', '+639923139504', 'uploads/profiles/patient_g_69d8f4da65ea53.51182315.jpg', '', '', '', '$2y$10$CXrjDXO8KlgGsRJlyXwnFOHtqB0m9dnUEsr6t.RFZz/krVlGeSfhK', '', NULL, '', 'City of Caloocan', 'Philippines', '', '', 'English', '116446608577966139282', 'google', '2026-04-10 13:02:18', '2026-04-12 15:06:35', 1, NULL, NULL, NULL, NULL, 0, 'Violation of platform terms and conditions.'),
(12, 'Jerson Uanan', '2008-04-02', 'Male', 'jersonsagun1123@gmail.com', '+639999999999', NULL, '', '', '', '$2y$10$AwKUtPdKYMgOrWwqZL1lquQIFkfquHYOMe6q9rw7W2wZmeIvFee8u', '', NULL, '', 'City of Caloocan', 'Philippines', '', '', 'English', NULL, 'manual', '2026-04-11 08:55:33', '2026-04-11 08:55:33', 0, '3357c7c7f4af985c0a8c5a3659bb7116243ddf983af133817b5c7fe9a7a0405e', '2026-04-12 08:55:33', NULL, NULL, 1, NULL),
(13, 'Serva, Riggie', '2004-08-12', 'Prefer not to say', 'renzserdolgie@gmail.com', '+639636470089', 'uploads/profiles/patient_69dbf103a7e609.25706553.png', '', '', '', '$2y$10$QxzjwgPmKiYKwr6o30BO3uePyAI./9K.OinE0jhi9RVQBii0wMyD.', '', NULL, '111111', 'City of Caloocan', 'Philippines', '11111111', '11111', 'English', '107601148343507504712', 'google', '2026-04-12 19:22:43', '2026-04-12 19:22:43', 0, 'c2e879b970331e8c86086a5156e4c8a90819991fe8d53dd389c4409ac3416e7c', '2026-04-13 19:22:43', NULL, NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `patient_doctors`
--

CREATE TABLE `patient_doctors` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `assigned_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `patient_doctors`
--

INSERT INTO `patient_doctors` (`id`, `patient_id`, `doctor_id`, `assigned_at`) VALUES
(1, 1, 4, '2026-03-14 06:48:13'),
(2, 5, 4, '2026-03-15 08:38:09');

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `medication_name` varchar(150) NOT NULL,
  `dosage` varchar(80) DEFAULT NULL,
  `frequency` varchar(80) DEFAULT NULL,
  `refills_remaining` int(11) DEFAULT 0,
  `prescribed_date` date NOT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('Active','Expired','Cancelled') DEFAULT 'Active',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `staff_accounts`
--

CREATE TABLE `staff_accounts` (
  `id` int(11) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('receptionist','coordinator','supervisor') NOT NULL DEFAULT 'receptionist',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `staff_accounts`
--

INSERT INTO `staff_accounts` (`id`, `full_name`, `email`, `password`, `role`, `status`, `created_at`) VALUES
(4, 'TELE-CARE Staff', 'staff@telecare.com', '$2y$10$YnlzuMjQm2ntktIn7faJ9eOWpWDQwGxNhLOk5hmquxEvebnJ/Ko7G', 'receptionist', 'active', '2026-03-22 15:47:28');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `appointment_logs`
--
ALTER TABLE `appointment_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `idx_google_id` (`google_id`);

--
-- Indexes for table `patient_doctors`
--
ALTER TABLE `patient_doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`patient_id`,`doctor_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `staff_accounts`
--
ALTER TABLE `staff_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `appointment_logs`
--
ALTER TABLE `appointment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=191;

--
-- AUTO_INCREMENT for table `lab_results`
--
ALTER TABLE `lab_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `patient_doctors`
--
ALTER TABLE `patient_doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staff_accounts`
--
ALTER TABLE `staff_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_logs`
--
ALTER TABLE `appointment_logs`
  ADD CONSTRAINT `appointment_logs_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_logs_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff_accounts` (`id`);

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`verified_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD CONSTRAINT `doctor_schedules_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD CONSTRAINT `lab_results_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_doctors`
--
ALTER TABLE `patient_doctors`
  ADD CONSTRAINT `patient_doctors_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `patient_doctors_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
