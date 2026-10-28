-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 22, 2026 at 07:54 AM
-- Server version: 10.11.19-MariaDB-cll-lve
-- PHP Version: 8.4.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `telecare`
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
  `reference_no` varchar(20) DEFAULT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `type` enum('General','Follow-up','Emergency','Teleconsult') DEFAULT 'Teleconsult',
  `department` varchar(50) DEFAULT NULL,
  `status` enum('Pending','DoctorApproved','Confirmed','Completed','Cancelled') NOT NULL DEFAULT 'Pending',
  `completed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `attachment_type` varchar(20) DEFAULT NULL,
  `attachment_ocr_text` mediumtext DEFAULT NULL,
  `payment_status` enum('Unpaid','Paid') DEFAULT 'Unpaid',
  `payment_method` enum('Regular','GCash','YAKAP','HMO') NOT NULL DEFAULT 'Regular',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `reference_no`, `patient_id`, `doctor_id`, `appointment_date`, `appointment_time`, `type`, `department`, `status`, `completed_at`, `notes`, `reason`, `attachment_path`, `attachment_type`, `attachment_ocr_text`, `payment_status`, `payment_method`, `created_at`, `paymongo_link_id`, `receipt_number`, `paid_at`, `chat_log`, `consultation_transcript`, `consultation_summary`, `summary_pdf_path`, `summary_session_key`, `summary_edited`, `summary_reviewed_at`) VALUES
(1, 'APT-2026-52261', 10, 4, '2026-09-18', '16:15:00', 'Teleconsult', 'General Medicine / General Practice', 'Cancelled', NULL, '', 'Fever', NULL, NULL, NULL, 'Unpaid', 'Regular', '2026-09-18 07:38:05', 'src_nfoH7akBxkKRFBTTisV5qC24', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(2, 'APT-2026-97880', 10, 4, '2026-09-18', '16:05:00', 'Teleconsult', 'General Medicine / General Practice', 'Completed', '2026-09-18 13:34:01', '', 'Loss of appetite', NULL, NULL, NULL, 'Paid', 'Regular', '2026-09-18 07:55:53', 'src_rx6bkrvGhkZUkssEeARYpBse', 'TC-8BAD4E69', '2026-09-18 00:56:13', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(3, 'IC-0491B55F', 7, 4, '2026-09-18', '17:08:46', 'Teleconsult', NULL, 'Completed', '2026-09-18 17:08:46', NULL, 'Instant call started by doctor', NULL, NULL, NULL, 'Paid', 'Regular', '2026-09-18 09:08:46', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(4, 'IC-DBED6EA5', 8, 4, '2026-09-18', '17:09:14', 'Teleconsult', NULL, 'Completed', '2026-09-18 17:09:14', NULL, 'Instant call started by doctor', NULL, NULL, NULL, 'Paid', 'Regular', '2026-09-18 09:09:14', NULL, NULL, NULL, '[05:09:49 PM] System: Call connected\r\n[05:09:49 PM] System: Call connected\r\n[05:11:09 PM] System: Call ended\n\n[--- Rejoined Session — Sep 18, 2026 5:16 PM ---]\n[05:15:18 PM] System: Call connected\r\n[05:15:18 PM] System: Call connected\r\n[05:16:11 PM] System: Call ended', 'Ayan na, tanginang pangit ang UI. Ayan na, tanginang pangit ang UI. Ano yung ano? Ang panggit ka naman, wala. Sabi ko kasi sa\'yo mag-script ka eh. Ano yung ano? Ang panggit ka naman, wala. Sabi ko kasi sa\'yo mag-script ka eh. Oh, sige. Oh, sige. Ingredients. Ingredients. I didn\'t know in Adobe ingredients bro I didn\'t know in Adobe ingredients bro so patient so patient See you, salita ka nga lang Dok, may hug mo ka nung Mako, it\'s pa rin yung controller Salita ka nga lang Dok, may hug mo ka nung Mako, it\'s pa rin yung controller Rinig ba kita? Walang sound yung laptop Rinig ba kita? Walang sound yung laptop Walang sound yung laptop, Tol Why is home? What sound using your laptop, Tull? Tell me more of my mouth! Give me more of my mouth! Don\'t you dare me to be able to be able to be able to be able you. Can I say... Ane, what\'s your concern? What\'s your concern with, Udalox? oh my tool love oh my tool levels I think you have cancer AIDS and yes I know cause I think you have cancer AIDS and yes I know just drink by Jessica and I know you said just drink by Jessica and I know you said\n\n[--- Rejoined Session — Sep 18, 2026 5:16 PM ---]\nhello hello I mean no or any government I mean no or any government pero sir I\'m speaking Oh Sir a speaker I speak I speak I speak I I\'m gonna let it I\'ll try again you know that in union and the hill and yeah the name of the day the speaker neto the boom the speaker neto the boom yeah no no no no no really really really like Di ba yung cellphone ni Sagul? Like, sa cellphone ni Sagul. Di ba yung AP? Di ba yung cellphone ni Sagul? Yung AP?', '1. Chief Complaint\nNot discussed.\n\n2. History of Present Illness\nNot discussed.\n\n3. Doctor\'s Assessment\nNot discussed.\n\n4. Diagnosis (if mentioned)\nNot discussed.\n\n5. Treatment Plan / Prescriptions\nNot discussed.\n\n6. Follow-up Instructions\nNot discussed.', 'summary_4.pdf', '4_1789722900', 0, '2026-09-18 02:11:50'),
(5, 'IC-5177495A', 8, 4, '2026-09-18', '17:17:47', 'Teleconsult', NULL, 'Completed', '2026-09-18 13:34:01', NULL, 'Instant call started by doctor', NULL, NULL, NULL, 'Paid', 'Regular', '2026-09-18 09:17:47', NULL, NULL, NULL, '[05:31:49 PM] System: Call connected\r\n[05:31:49 PM] System: Call connected\r\n[05:35:14 PM] System: Call ended\n[05:38:05 PM] System: Call connected\r\n[05:38:05 PM] System: Call connected\r\n[05:38:38 PM] System: Call ended\n\n[--- Rejoined Session — Sep 18, 2026 6:13 PM ---]\n[06:11:27 PM] System: Call connected\r\n[06:11:27 PM] System: Call connected\r\n[06:13:09 PM] System: Call ended\n\n[--- Rejoined Session — Sep 18, 2026 6:16 PM ---]\n[06:14:53 PM] System: Call connected\r\n[06:14:53 PM] System: Call connected\r\n[06:15:57 PM] System: Call ended', 'Hey Oh Problema You set No, you set When did you drink, what did you drink in the background with Jesus? So GC? Shalong, what do you hear about this? What kind of song is you do, what kind of song is you do? You are the song. What kind of song is you do? Antol I don\'t see it I don\'t see it I don\'t see it I don\'t see it I don\'t see it Ano ulam nyo? Ano ulam nyo? Magsabi ka nga ng ano? You\'re saying that you\'re sick. You sick You sick You sick You sick Toll you can see me did you read Did you call me alt tab I just kidding did you call me alt tab Did you call me alt tab I just kidding I don know game Alt tab I just kidding I don know game So do you call me alt tab so laptop mo na lang basahin dami mong reklamo ano kapasyente ko ah dami mong reklamo ano kapasyente ko ah wang hina mo wang hina mo I not script I\'m script. They were now. They were now. And I was the beginning. Oh, okay. Ano ba sa sabihin? O sige O sige Teknik ako O sige Teknik ako Okay Okay San yan? San yan? Ah, ito. Sige, sige game. Isa pa ulit. Ah, ito. Sige, sige game. Isa pa ulit. Nakaka-experience ka ba na ano? Fever? Do you have any feelings? Fever? Chess? Fain? Shortness of breath or wheezing? Chess? Fain? Shortness of breath or wheezing? Do you have any feelings? Do you have allergies, my idol? Do you have allergies? Do you have allergies, my idol? Do you have medications? I have a A.S.A.V. When you have a... oh swing yeah yeah yeah okay okay avoid my nano it take my city if you have a boy my nano it take my city is a lot of multiple times multiple times no okay my god Oh no Oh\nHey, hello. Hey, hello. Enrico. Enrico. you you you okay it\'s long it\'s long\n\n[--- Rejoined Session — Sep 18, 2026 6:13 PM ---]\nI think that and I can It\'s I V I V Can we hear and see Oh Dito Okay What Good afternoon doctor Yes, everything is patient I had a quote for about five days started as a dry cut but yesterday I started caught cutting up a small amount of yellowish plan I also have a sore throat nasal congestion and I\'ve been feeling tired I have a my asthma since I was a teenager I\'m allergic to a book friend a book a book propel okay it causes me to develop hives I don\'t have any known antibiotics allergies okay also you salbutamol inhaler when needed I also took paracetamol 500 milligrams when I had fever okay medicine containing paracetamol P-militerine color pain okay can you hear me yes we are drink I broke a film five times a day fuck you\n\n[--- Rejoined Session — Sep 18, 2026 6:16 PM ---]\nI know I know hello can you hear me yes we are yes I think I broke open five times a day and and drink parasitamon by Jesse three times a day and drink HIV five times a day and drink water daily every five minutes you two gallons of water every five minutes try go long play lang um anababa pere sabihin tal um nyo sir drink nyo sir ah think nyo sir five times a day five times a day', '1. Chief Complaint\r\nNot discussed.\r\n\r\n2. History of Present Illness\r\nNot discussed.\r\n\r\n3. Doctor\'s Assessment\r\nNot discussed.\r\n\r\n4. Diagnosis (if mentioned)\r\nNot discussed.\r\n\r\n5. Treatment Plan / Prescriptions\r\nNot discussed.\r\n\r\n6. Follow-up Instructions\r\nNot discussed.ad\r\n\r\n[#session:5_1789725600]\r\n[--- AI Summary - Sep 18, 2026 6:13 PM ---]\r\n1. Chief Complaint\r\nCough lasting five days that began dry and has become productive with yellowish sputum, accompanied by sore throat, nasal congestion, and fatigue.\r\n\r\n2. History of Present Illness\r\nThe patient reports a five‑day history of a dry cough that progressed to a small amount of yellowish sputum beginning yesterday. He also notes a sore throat, nasal congestion, and generalized tiredness. He denies known antibiotic allergies and has a longstanding history of asthma, using a salbutamol inhaler as needed. He takes 500 mg paracetamol for fever when it occurs. He is allergic to an unspecified substance that causes hives. Plan: symptomatic management and monitoring of respiratory symptoms.\r\n\r\n3. Doctor\'s Assessment\r\nNot discussed.\r\n\r\n4. Diagnosis (if mentioned)\r\nNot discussed.\r\n\r\n5. Treatment Plan / Prescriptions\r\nNot discussed.\r\n\r\n6. Follow-up Instructions\r\nNot discussed.\r\n\r\n[#session:5_1789726500]\r\n[--- AI Summary - Sep 18, 2026 6:16 PM ---]\r\n1. Chief Complaint\r\nNot discussed.\r\n\r\n2. History of Present Illness\r\nThe patient reported that he is taking an unspecified medication (referred to as “parasitamon”) three times daily and another medication (referred to as “HIV”) five times daily. He also stated that he consumes a large volume of water, approximately two gallons, every five minutes throughout the day. The patient did not describe any specific symptoms, pain, or functional limitation associated with these regimens. No onset, duration, or aggravating/relieving factors were provided. Plan: Not discussed.\r\n\r\n3. Doctor\'s Assessment\r\nNot discussed.\r\n\r\n4. Diagnosis (if mentioned)\r\nNot discussed.\r\n\r\n5. Treatment Plan / Prescriptions\r\nNot discussed.\r\n\r\n6. Follow-up Instructions\r\nNot discussed. asdwadasdaw', 'summary_5.pdf', '5_1789726500', 1, NULL),
(6, 'IC-D327C849', 8, 4, '2026-09-18', '18:29:27', 'Teleconsult', NULL, 'Completed', '2026-09-18 13:34:01', NULL, 'Instant call started by doctor', NULL, NULL, NULL, 'Paid', 'Regular', '2026-09-18 10:29:27', NULL, NULL, NULL, '[06:29:42 PM] System: Call connected\r\n[06:29:42 PM] System: Call connected\r\n[06:30:44 PM] System: Call ended', 'DOCTOR SAID:\nI\'m going to save you. Same tol game. I\'m first, I\'m first. You have five gallons of water for two minutes. Then, biogiesic. Can you drink biogiesic two times a day? I\'m gonna go up and you said you said you said drink you said three times a day away then I go what\'s your symptoms and shit okay drink plenty of water and don\'t suck bricks okay\n\nPATIENT SAID:\nNo. Rineg, rineg. Inuubo, inihika, nilagnat ako ng isang araw. May hilo, masakit ulo. Okay na. Okay.', '[#session:6_1789727400]\n[--- AI Summary - Sep 18, 2026 6:30 PM ---]\n1. Chief Complaint\nAcute onset of fever, cough, shortness of breath, dizziness, and headache lasting one day.\n\n2. History of Present Illness\nThe patient reported that one day prior she developed a fever accompanied by cough, shortness of breath, dizziness, and headache. The symptoms were continuous and systemic in nature. She denied any worsening factors and noted that the symptoms have now improved. No other associated symptoms such as chest pain or gastrointestinal upset were mentioned. Plan: increase fluid intake and take Biogiesic twice daily.\n\n3. Doctor\'s Assessment\nThe physician evaluated the patient’s acute febrile respiratory illness and deemed it likely a self‑limited viral process. He emphasized adequate hydration and symptomatic relief with an antipyretic/analgesic.\n\n4. Diagnosis (if mentioned)\nNot discussed.\n\n5. Treatment Plan / Prescriptions\nBiogiesic (acetaminophen) – take twice daily as tolerated. Advise drinking plenty of water. Avoid “sucking bricks” (i.e., avoid harmful practices).\n\n6. Follow-up Instructions\nNot discussed.', 'summary_6.pdf', '6_1789727400', 0, NULL),
(7, 'APT-2026-08622', 10, 4, '2026-09-19', '09:30:00', 'Teleconsult', 'General Medicine / General Practice', 'Completed', '2026-09-20 19:00:20', '', 'Fever, Cough, Colds / Runny nose, Sore throat', NULL, NULL, NULL, 'Paid', 'Regular', '2026-09-18 15:15:41', 'src_FouKFxntwGDg11xFfaR6zsrJ', 'TC-F5CFD8B3', '2026-09-18 08:19:05', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(8, 'APT-2026-30222', 8, 4, '2026-09-19', '09:00:00', 'Teleconsult', 'General Medicine / General Practice', 'Completed', '2026-09-20 19:00:20', '', 'Fever, Cough, Colds / Runny nose, Sore throat', NULL, NULL, NULL, 'Paid', 'Regular', '2026-09-18 15:23:47', 'src_SSeJa4D7J6pGKfKkqyEW3Rfq', 'TC-3E74E8E0', '2026-09-18 08:24:24', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(9, 'IC-3110E676', 8, 4, '2026-09-18', '23:41:44', 'Teleconsult', NULL, 'Completed', '2026-09-18 13:34:01', NULL, 'Instant call started by doctor', NULL, NULL, NULL, 'Paid', 'Regular', '2026-09-18 15:41:44', NULL, NULL, NULL, '[11:52:05 PM] System: Call connected\r\n[11:52:05 PM] System: Call connected\r\n[12:03:22 AM] System: Call ended', 'DOCTOR SAID:\nI\'m going to start. I\'m going to start. I\'m going to start. Okay. Okay. I\'m like this guy Is this a bad thing? I\'m like this guy I can hear it I\'m gonna share my screen. I\'m gonna share my screen. Did you see the sticky note? I\'m gonna share my screen. I\'m gonna share my screen. Okay. Hello hello hello hello hello hello hello hello.. I don\'t know how to save this game I\'ll put that right now Ay gagi! I rolled it! Sorry! I\'ll start this game Do you want to write a script? Okay. Huh? Do you want to send it again? Do you want to send it again? Do you want to send it again? Oh, that\'s what I want to do. Oh, that\'s what I want. You know what I want. Ulet, ulet. Thank you Good day. Can you hear me clearly? Good day. Okay good, so what seems to be the problem today? Hmm okay, may fever ka ba? Aside sa ubo and fever, may sore throat, sipon, headache or body weakness ka ba? How about sa paghinga mo? May shortness of breath or chest pain ba? Hmm, may history ka ba ng asthma? Are you using any medications for your asthma? Okay, wala ng ibang medication. Ilang beses mo siya ininom How many times did you drink Okay, so clarify. I just want to take you cold medicine twice plus a separate paracetamol. Okay, important na manote natin yan because the cold medicine already contains paracetamol. So, kailangan mag-ingat sa pag-take ng multiple medicines na may same ingredient. That\'s understandable. May known drug allergies ka ba? I\'m going to go to the video. and then we\'ll be able to see you and you know I\'m gonna send one and I\'m gonna have a little bit but I won I How is this? How is this summary? Let\'s see. I\'ll show you. Thank you. so wait tell them I gonna and I know it on the way please go along on a mini note one you will be perfect I do pretend I view profan wait no Lana sana oh and him away so send I have my oldest him in a small I can introduce all that introduce outlet huh Good evening, can you hear me clearly? Okay, anong meron ngayon? May difficulty ba sa breathing or chest pain? Okay, may medications ka bang ginagamit? Okay, any drug allergies? Noted, may maintenance medication ka ba? Sige, I\'ll document your symptoms, asthma and hypertension history, medications and ibuprofen allergy. Since may wishing ka, monitor your breathing closely. If magkaroon ka ng severe difficulty breathing, chest pain, or coughing up blood, seek emergency care immediately. You\'re welcome. We\'ll schedule a follow-up check-up if your symptoms are improving. Take care.\n\nPATIENT SAID:\nOkay. May Iis naman? Eh di, ikaw na magaling. Record na. oh oh oh oh I\'m not sure screen I\'m not sure screen screen Okay. record na recording this is gay but I cut the bat okay okay game I\'m gonna naka ready script then wait lang ako muna di naman I may pinago ko ginawa kong taglish the pro yung kanina okay recording now one two three uh good day panelists this is group telecare recording a demonstration for our teleconsultation procedure. For this demonstration, we will proceed with the normal, sorry, mali. Ulit. Okay. Okay. Okay. Good day panelists This is Group Telecare recording a demonstration for our teleconsultation procedure for this demonstration we proceed with the teleconsultation immediately using the instant call or our test features to proceed immediately uh sorry thank you so much protect nalilito na ko last last last wait Good day, panelists. This is Group Telecare recording a demonstration for our teleconsultation procedure. For this demonstration, we will proceed with the normal teleconsultation, but using our instant call or our test features to proceed with the teleconsultation. Thank you so much. Yes, Doc, medyo may delay lang ng konti pero malino naman po. Actually, Doc, mga 5 days na po akong inuubo. Noong una dry cough lang siya pero nitong last 2 days may konting plema na parang yellowish po yung kulay ng plema ko. Meron dok, kagabi inabot po ako ng 38.2 Celsius sa temperature ko. Bumaba naman after ko uminom ng paracetamol. Meron po, masakit po yung lalamunan ko, congested yung ilong ko, may headache din and nangihina ko nito mga nakaraang araw. Yes, sir. may salbutamol po ko at inhaler po. Usually, ginagamit ko lang kapag may wheezing or nahihirapan akong huminga. Ginamit ko po, ay ginamit ko po dalawang beses kagabi. And yung sa ano lang po kagabi, paracetamol yun lang po yung ininom ko Tapos bumili rin po ako ng cold medicine for the cold Two times, umaga and morning, then I will take a cold. Yes, the paracetamol is inanom ko bago po matulog. Ah, okay, Doc. Hindi ko po kasi napansin na may paracetamol din pala yung cold medicine na ininom ko. Meron, Doc. Allergic po ako sa Ibang ingredients ng paracetamol. Nagkaroon ako ng... Wait, sorry. Pause mo nga, Jan. Gagyang haba. Wait lang. Wait, last one. Tuloy-tuloy na. Iano na. Ha? Oo. Ha? Ha? Bye, clean ko. Stop mo muna. Stop mo muna. I cut mo na lang yung first part or yung second part na ano, nag-intro ako. Okay, papakita mo pa yun pre. Pati yung sa resibo. Wait nga. So now on the roll. Thomas Maykle. My bad my bad Ibuprofen Ibuprofen? Ibuprofen ba yun, ma? Ah. ah, Bisaya. Game. Mas, mas may clean na yan, mas may clean na yan. Game na? Oh. Okay na boy? Okay, good day panelists, this is Group Telecare, recording a demonstration for our teleconsultation procedure. For this testing, we used our instant call feature to proceed with the teleconsultation immediately. So thank you. Go. Yes, Doc, medyo may delay lang ng konti, pero clear naman po. 5 days ago I was in a dry coughed, but for the next 2 days it was a yellowish color. Wala chest pain, but there was a wheezing on the morning. May asthma since I was a young kid. Salbutamol inhaler po kapag may wheezing. Para naman po sa lagnat ko kagabi, uminom ako ng paracetamol. Tapos uminom din po ako ng cold medicine kagabi. Allergic po ako ng ibuprofen. Yun lang naman, Doc. Wala, Doc. Ano lang, for hypertension lang. Opo, Doc. Thank you. Thank you, Doc.', '[#session:9_1789747200]\r\n[--- AI Summary - Sep 19, 2026 12:04 AM ---]\r\n1. Chief Complaint\r\nCough for five days with recent yellowish sputum, fever, sore throat, nasal congestion, headache, and generalized weakness.\r\n\r\n2. History of Present Illness\r\nThe patient reported that five days ago he developed a dry cough which progressed over the past two days to a productive cough with yellowish sputum. Yesterday night he experienced a fever of 38.2 °C that subsided after taking paracetamol. He also noted sore throat, nasal congestion, headache, and fatigue over the same period. He has a long‑standing history of asthma, using a salbutamol inhaler twice the previous night for wheezing, and a history of hypertension. He took a cold medicine that also contained paracetamol in addition to a separate dose of paracetamol, raising concern for duplicate dosing. He reports an allergy to ibuprofen. Plan: monitor symptoms, avoid duplicate paracetamol, continue salbutamol PRN, and arrange follow‑up.\r\n\r\n3. Doctor\'s Assessment\r\nThe physician identified an acute upper respiratory infection with possible bronchitis and noted an asthma component. He emphasized the risk of inadvertent overdose from multiple agents containing paracetamol and confirmed the patient’s ibuprofen allergy. He instructed the patient to monitor for worsening dyspnea, chest pain, or hemoptysis and to seek emergency care if these occur.\r\n\r\n4. Diagnosis (if mentioned)\r\nNot discussed.\r\n\r\n5. Treatment Plan / Prescriptions\r\n- Continue salbutamol inhaler as needed for wheezing or shortness of breath.  \r\n- Use paracetamol only as a single agent for fever/pain; avoid additional cold medicines that contain paracetamol.  \r\n- Avoid ibuprofen due to documented allergy.  \r\n- No new prescriptions were written.\r\n\r\n6. Follow-up Instructions\r\nThe patient was advised to follow up for a repeat evaluation if symptoms improve or worsen, and to seek immediate emergency care if he develops severe breathing difficulty, chest pain, or coughs up blood. Not discussed.', 'summary_9.pdf', '9_1789747200', 1, '2026-09-18 09:04:52'),
(10, 'APT-2026-92374', 1, 4, '2026-09-19', '15:00:00', 'Teleconsult', 'General Medicine / General Practice', 'Completed', '2026-09-20 19:00:20', '', 'Fever', 'uploads/patient_docs/doc_1_6aad67d15aa74.jpg,uploads/patient_docs/doc_1_6aad67d2c5f6e.jpg', 'unknown,unknown', '\n---\n', 'Paid', 'Regular', '2026-09-18 16:39:33', 'src_Gjy2kY4o8d1Btceo4pgGkasK', 'TC-4AE9E88F', '2026-09-18 09:40:25', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(11, 'APT-2026-18240', 1, 4, '2026-09-19', '11:30:00', 'Teleconsult', 'General Medicine / General Practice', 'Cancelled', NULL, '', 'Fever', NULL, NULL, NULL, 'Unpaid', 'Regular', '2026-09-18 16:46:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(12, 'IC-400A3FB0', 8, 4, '2026-09-25', '22:41:40', 'Teleconsult', NULL, 'Completed', '2026-09-25 22:42:12', NULL, 'Instant call started by doctor', NULL, NULL, NULL, 'Paid', 'Regular', '2026-09-25 14:41:40', NULL, NULL, NULL, '[10:46:32 PM] System: Call connected\r\n[10:46:32 PM] System: Call connected\r\n[10:47:49 PM] System: Call ended\n\n[--- Rejoined Session — Sep 25, 2026 11:02 PM ---]\n[10:59:52 PM] System: Call connected\r\n[10:59:52 PM] System: Call connected\r\n[11:01:40 PM] System: Call ended', 'DOCTOR SAID:\nokay wait lang di kita rinig may may ka ba rinig mo ako rinig mo ako okay ayan ayan mag sabi ka lang ulit hangin ano yun Okay, let\'s try it.\n\nPATIENT SAID:\nThank you. Okay, ito pala. Ano ito test? Biogesic paracetamol inuubo. Hu hu hu. Ano ba? Ano mo pwede sakit? I was in a few days, and then I was in a phone, and then I was in a phone. Then I was in a phone, and then I was in a phone. Why, big doc? I don\'t have a phone.\n\n[--- Rejoined Session — Sep 25, 2026 11:02 PM ---]\nDOCTOR SAID:\nno game I don\'t know. Try it. You\'re going to do it now? Okay. Okay. Working. I I I I I I I I I I okay okay okay okay okay okay okay I\'m getting a little bit of a bite. Mmm.\n\nPATIENT SAID:\nWait lang. Test call. Ano? Try na. Pag nag-ibanta ba ko marirecord? Basta PC. Okay, sige, magsasalita ako. Ah, oo. Five days na pa akong inuubo. First dry cough lang pero ngayon yellowish plema na may sore throat sipon at nilalagnat na ako. Kagabi umabot ng 38.2 yung Celsius ko, Doc. Wala pong chest pain pero may konting whizzing kapag gabi. May asthma din po ako since bata. Ang medication po, salbutamol inhaler po. Kapag may wheezing for the fever, uminom ako ng paracetamol 500 mg. Tapos may cold medicine din ako na may paracetamol. Ano? Ano to? Penileferin? At chlorphenamine? Basta yun, Doc. Hindi ko alam ano yun. Sige, Doc. Inom lang ako ng by Jessica Tubeg. Noted. Okay. Sige, tinan mo. Ay, eto pala, may screenshot ako.', '1. Chief Complaint\r\nNot discussed.\r\nFilipino: Hindi napag-usapan.\r\n\r\n2. History of Present Illness\r\nNot discussed.\r\nFilipino: Hindi napag-usapan.\r\n\r\n3. Doctor\'s Assessment\r\nNot discussed.\r\nFilipino: Hindi napag-usapan.\r\n\r\n4. Diagnosis (if mentioned)\r\nNot discussed.\r\nFilipino: Hindi napag-usapan.\r\n\r\n5. Treatment Plan / Prescriptions\r\nNot discussed.\r\nFilipino: Hindi napag-usapan.\r\n\r\n6. Follow-up Instructions\r\nNot discussed.\r\nFilipino: Hindi napag-usapan.\n\n1. Chief Complaint\nCough for five days, now productive with yellow sputum, accompanied by sore throat, nasal congestion, fever up to 38.2 °C, and nighttime wheezing in a patient with a history of asthma.\nFilipino: Ubo ng limang araw, ngayon may plema na dilaw, kasabay ng sore throat, sipon, lagnat hanggang 38.2 °C, at pag-ungol sa gabi sa pasyenteng may kasaysayan ng hika.\n\n2. History of Present Illness\nThe patient reported a five‑day history of cough that began as a dry cough and progressed to a productive cough with yellowish sputum. He also experienced sore throat, rhinorrhea, and a fever that peaked at 38.2 °C last night. He denied chest pain but noted mild wheezing, especially at night. He has a longstanding diagnosis of asthma and uses a salbutamol inhaler; for fever and wheezing he self‑administers 500 mg paracetamol and a cold medication containing paracetamol and chlorphenamine. Plan: continue current asthma therapy, use antipyretics as needed, and seek further medical evaluation if symptoms worsen or persist.\nFilipino: Iniulat ng pasyente ang limang araw na kasaysayan ng ubo na nagsimula bilang tuyong ubo at naging produktibo na may dilaw na plema. Naranasan din niya ang sore throat, sipon, at lagnat na umabot sa 38.2 °C kagabi. Itinanggi niya ang pananakit ng dibdib ngunit napansin ang bahagyang pag-ungol, lalo na sa gabi. May matagal nang diagnosis ng hika at gumagamit ng salbutamol inhaler; para sa lagnat at pag-ungol, umiinom siya ng 500 mg na paracetamol at isang gamot sa sipon na may paracetamol at chlorphenamine. Plano: ipagpatuloy ang kasalukuyang therapy para sa hika, gumamit ng antipyretic kung kinakailangan, at magpatingin muli kung lumala o magpatuloy ang mga sintomas.\n\n3. Doctor\'s Assessment\nNot discussed.\nFilipino: Hindi napag-usapan.\n\n4. Diagnosis (if mentioned)\nNot discussed.\nFilipino: Hindi napag-usapan.\n\n5. Treatment Plan / Prescriptions\nNot discussed.\nFilipino: Hindi napag-usapan.\n\n6. Follow-up Instructions\nNot discussed.\nFilipino: Hindi napag-usapan.', 'summary_12.pdf', '12_1790348400', 0, '2026-09-25 22:48:57'),
(13, 'APT-2026-10406', 8, 4, '2026-09-26', '12:00:00', 'Teleconsult', 'General Medicine / General Practice', 'Completed', '2026-10-01 23:56:56', '', 'Fever', NULL, NULL, NULL, 'Paid', 'Regular', '2026-09-25 14:41:51', 'src_FNhAMLj5NDUkytwENasYuCMw', 'TC-CE17359D', '2026-09-25 22:42:41', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(14, 'IC-FEE291F7', 8, 4, '2026-09-26', '00:26:07', 'Teleconsult', NULL, 'Completed', '2026-09-26 00:38:29', NULL, 'Instant call started by doctor', NULL, NULL, NULL, 'Paid', 'Regular', '2026-09-25 16:26:07', NULL, NULL, NULL, '[12:50:22 AM] System: Call connected\r\n[12:50:22 AM] System: Call connected\r\n[12:51:20 AM] System: Call ended\n\n[--- Rejoined Session — Sep 26, 2026 1:07 AM ---]\n[01:05:38 AM] System: Call connected\r\n[01:05:38 AM] System: Call connected\r\n[01:06:56 AM] System: Call ended\n[01:10:57 AM] System: Call connected\r\n[01:10:57 AM] System: Call connected\r\n[01:12:03 AM] System: Call ended', 'DOCTOR SAID:\nHello, I\'m not going to go to the office. I\'m not going to go to the office. I\'m going to go to the office. I\'m going to go to the office consultation. Sige, uminom ka ng tubig Wait lang Okay, okay, okay na yung ano, leave button Patihin nga sa\'yo, okay na sa\'yo Okay, okay Yun lang Uminom ka ng tubig Saka ng tubig Sige, bye bye\n\nPATIENT SAID:\nI\'m going to go to the hospital. I have an allergy to Ibuprofen. I have a biodesic. Okay. I\'m going to leave now. Okay. Bye.\n\n[--- Rejoined Session — Sep 26, 2026 1:07 AM ---]\nDOCTOR SAID:\nOh, I\'m going to go. You can go to the bathroom? You can go to the bathroom? What\'s up? Okay, you\'ll be able to go. What do you mean? I\'ll try it again. The recipe of adobo. No, it\'s not really a summary. I\'ll try it again. I\'ll try it again. What did you do? Did you screenshot it? You\'re a little bit. You\'re a little bit. You\'re a little bit. Uy Okay wait try lang ulit\n\nPATIENT SAID:\nNo. No. Oh. Puminom na ako ng tubig. Pero sinisipon pa rin ako. Oh, my man. Oh, where did that?\nDOCTOR SAID:\nHello? Wala kang cam? Are you there bro? Inum ka na ba? Paano ka? Urinalis is test. I\'m going to get out of the way. What? I\'m going to buy a saging. I\'m going to McDonald\'s. I\'m going to die until 3am, 3pm. I\'m going to have defense. I\'m going to be a joke. I\'ll say you later Look Okay, try it again\n\nPATIENT SAID:\nOh. Oh. Oh, I\'m going to go. Sige. Sabi mo eh. Okay. Thank you.', '1. Chief Complaint\r\nPatient reports a known allergy to ibuprofen and persistent nasal congestion with a runny nose despite drinking water, and states she plans to go to the hospital for further evaluation.  \r\nFilipino: Ang pasyente ay nag-ulat ng kilalang allergy sa ibuprofen at patuloy na sipon at pagbahing kahit uminom na ng tubig, at nagsabi na pupunta siya sa ospital para sa karagdagang pagsusuri.\r\n\r\n2. History of Present Illness\r\nThe patient, a female (age not provided), presented with a documented ibuprofen allergy; the onset, specific symptoms, and severity of the reaction were not described. She denied pain, swelling, or other acute complaints. She also reported ongoing nasal congestion and rhinorrhea that began prior to the call and persisted despite adequate oral hydration. She expressed intent to seek in‑person evaluation at the hospital. Plan: avoid ibuprofen, continue hydration, obtain further in‑person assessment.  \r\nFilipino: Ang pasyente, isang babae (walang ibinigay na edad), ay nag-ulat ng dokumentadong allergy sa ibuprofen; hindi nailahad ang simula, partikular na sintomas, at tindi ng reaksyon. Wala siyang iniulat na pananakit, pamamaga, o iba pang agarang reklamo. Nabanggit din niya ang patuloy na sipon at pagbahing na nagsimula bago ang tawag at hindi nawala kahit uminom ng sapat na tubig. Plano: iwasan ang ibuprofen, magpatuloy sa pag-inom ng tubig, at magpatingin nang personal sa ospital.\r\n\r\n3. Doctor\'s Assessment\r\nThe physician acknowledged the reported ibuprofen allergy, noted the continued nasal symptoms, found no acute abnormalities during the teleconsultation, and reiterated avoidance of ibuprofen and adequate fluid intake.  \r\nFilipino: Kinilala ng doktor ang iniulat na allergy sa ibuprofen, napansin ang patuloy na sintomas ng sipon, walang natukoy na agarang abnormalidad sa teleconsultation, at muling pinayuhan ang pasyente na iwasan ang ibuprofen at uminom ng sapat na tubig.\r\n\r\n4. Diagnosis (if mentioned)\r\nNot discussed.  \r\nFilipino: Hindi napag-usapan.\r\n\r\n5. Treatment Plan / Prescriptions\r\nNot discussed.  \r\nFilipino: Hindi napag-usapan.\r\n\r\n6. Follow-up Instructions\r\nPatient was advised to avoid ibuprofen, continue staying hydrated, and seek in‑person evaluation at the hospital as needed.  \r\nFilipino: Pinayuhan ang pasyente na iwasan ang ibuprofen, magpatuloy sa pag-inom ng tubig, at magpatingin nang personal sa ospital kung kinakailangan.', 'summary_14.pdf', '14_1790355600', 1, '2026-09-26 01:13:47'),
(15, 'APT-2026-24673', 15, 5, '2026-10-02', '10:00:00', 'Teleconsult', 'Cardiology', 'Confirmed', NULL, '', 'Shortness of breath', NULL, NULL, NULL, 'Paid', 'Regular', '2026-10-01 14:16:39', 'src_TSrUdo5NbNrwQKRuuHnPQMiA', 'TC-31DEB132', '2026-10-01 22:16:56', NULL, NULL, NULL, NULL, NULL, 0, NULL),
(16, 'APT-2026-22550', 1, 4, '2026-10-11', '14:00:00', 'Teleconsult', 'General Medicine / General Practice', 'Cancelled', NULL, '', 'I need a neurologist to check', NULL, NULL, NULL, 'Unpaid', 'Regular', '2026-10-11 05:46:34', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(17, 'APT-2026-26846', 1, 4, '2026-10-11', '14:30:00', 'Teleconsult', 'General Medicine / General Practice', 'Cancelled', NULL, '', 'i feel sick in my stomach', NULL, NULL, NULL, 'Unpaid', 'Regular', '2026-10-11 06:25:50', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(18, 'APT-2026-50297', 1, 4, '2026-10-11', '15:00:00', 'Teleconsult', 'General Medicine / General Practice', 'Cancelled', NULL, '', 'i need help', NULL, NULL, NULL, 'Unpaid', 'Regular', '2026-10-11 06:47:47', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(19, 'APT-2026-68058', 1, 4, '2026-10-15', '13:00:00', 'Teleconsult', 'General Medicine / General Practice', 'Cancelled', NULL, '', 'feel sick and stomachache', NULL, NULL, NULL, 'Unpaid', 'Regular', '2026-10-15 04:52:00', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(20, 'APT-2026-99058', 16, 4, '2026-10-17', '09:00:00', 'Teleconsult', 'General Medicine / General Practice', 'Completed', '2026-10-17 10:05:09', '', 'feeling sick and cough', NULL, NULL, NULL, 'Unpaid', 'HMO', '2026-10-17 00:12:30', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(21, 'APT-2026-59659', 15, 4, '2026-10-17', '16:00:00', 'Teleconsult', 'General Medicine / General Practice', 'Confirmed', NULL, '', 'Fever days ago', NULL, NULL, NULL, 'Unpaid', 'HMO', '2026-10-17 00:23:52', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(22, 'APT-2026-50260', 15, 4, '2026-10-17', '15:30:00', 'Teleconsult', 'General Medicine / General Practice', 'Cancelled', NULL, '', 'A mild cough', NULL, NULL, NULL, 'Unpaid', 'Regular', '2026-10-17 01:16:11', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(23, 'APT-2026-74317', 15, 4, '2026-10-23', '16:00:00', 'Teleconsult', 'General Medicine / General Practice', 'Confirmed', NULL, '', 'Osigeeeeeeee', NULL, NULL, NULL, 'Unpaid', 'HMO', '2026-10-17 01:34:10', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(24, 'APT-2026-99320', 10, 4, '2026-10-17', '14:30:00', 'Teleconsult', 'General Medicine / General Practice', 'Confirmed', NULL, '', 'my cough is worsening everyday', NULL, NULL, NULL, 'Unpaid', 'HMO', '2026-10-17 05:33:58', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL),
(25, 'APT-2026-57255', 10, 4, '2026-10-17', '15:30:00', 'Teleconsult', 'General Medicine / General Practice', 'Confirmed', NULL, '', 'cough and fever', NULL, NULL, NULL, 'Unpaid', 'YAKAP', '2026-10-17 06:49:03', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL);

--
-- Triggers `appointments`
--
DELIMITER $$
CREATE TRIGGER `trg_appt_completed_at` BEFORE UPDATE ON `appointments` FOR EACH ROW BEGIN
  IF NEW.`status` = 'Completed' AND NEW.`completed_at` IS NULL THEN
    SET NEW.`completed_at` = NOW();
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_document_drafts`
--

CREATE TABLE `appointment_document_drafts` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `doc_type` enum('prescription','lab_request','med_cert') NOT NULL,
  `draft_text` longtext NOT NULL,
  `payload_json` longtext DEFAULT NULL,
  `source_summary` longtext DEFAULT NULL,
  `source_model` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_document_drafts`
--

INSERT INTO `appointment_document_drafts` (`id`, `appointment_id`, `doc_type`, `draft_text`, `payload_json`, `source_summary`, `source_model`, `created_at`, `updated_at`) VALUES
(1, 5, 'prescription', 'Rx [MEDICATION NAME / STRENGTH / FORM]\n[INSTRUCTIONS / DOSAGE]\nDispense: [QUANTITY / VOLUME]\nLabel: [DIRECTIONS FOR USE]\n[ADDITIONAL INSTRUCTIONS]\n\nClinical note based on consultation summary:\n1. Chief Complaint\nNot discussed.\n\n2. History of Present Illness\nThe patient reported that he is taking an unspecified medication (referred to as “parasitamon”) three times daily and another medication (referred to as “HIV”) five times daily. He also stated that he consumes a large volume of water, approximately two gallons, every five minutes throughout the day. The patient did not describe any specific symptoms, pain, or functional limitation associated with these regimens. No onset, duration, or aggravating/relieving factors were provided. Plan: Not discussed.\n\n3. Doctor\'s Assessment\nNot discussed.\n\n4. Diagnosis (if mentioned)\nNot discussed.\n\n5. Treatment Plan / Prescriptions\nNot discussed.\n\n6. Follow-up Instructions\nNot discussed.', NULL, '1. Chief Complaint\nNot discussed.\n\n2. History of Present Illness\nThe patient reported that he is taking an unspecified medication (referred to as “parasitamon”) three times daily and another medication (referred to as “HIV”) five times daily. He also stated that he consumes a large volume of water, approximately two gallons, every five minutes throughout the day. The patient did not describe any specific symptoms, pain, or functional limitation associated with these regimens. No onset, duration, or aggravating/relieving factors were provided. Plan: Not discussed.\n\n3. Doctor\'s Assessment\nNot discussed.\n\n4. Diagnosis (if mentioned)\nNot discussed.\n\n5. Treatment Plan / Prescriptions\nNot discussed.\n\n6. Follow-up Instructions\nNot discussed.', 'groq', '2026-09-18 10:13:16', '2026-09-18 10:16:02'),
(3, 6, 'prescription', 'Rx [MEDICATION NAME / STRENGTH / FORM]\n[INSTRUCTIONS / DOSAGE]\nDispense: [QUANTITY / VOLUME]\nLabel: [DIRECTIONS FOR USE]\n[ADDITIONAL INSTRUCTIONS]\n\nClinical note based on consultation summary:\n1. Chief Complaint\nAcute onset of fever, cough, shortness of breath, dizziness, and headache lasting one day.\n\n2. History of Present Illness\nThe patient reported that one day prior she developed a fever accompanied by cough, shortness of breath, dizziness, and headache. The symptoms were continuous and systemic in nature. She denied any worsening factors and noted that the symptoms have now improved. No other associated symptoms such as chest pain or gastrointestinal upset were mentioned. Plan: increase fluid intake and take Biogiesic twice daily.\n\n3. Doctor\'s Assessment\nThe physician evaluated the patient’s acute febrile respiratory illness and deemed it likely a self‑limited viral process. He emphasized adequate hydration and symptomatic relief with an antipyretic/analgesic.\n\n4. Diagnosis (if mentioned)\nNot discussed.\n\n5. Treatment Plan / Prescriptions\nBiogiesic (acetaminophen) – take twice daily as tolerated. Advise drinking plenty of water. Avoid “sucking bricks” (i.e., avoid harmful practices).\n\n6. Follow-up Instructions\nNot discussed.', NULL, '1. Chief Complaint\nAcute onset of fever, cough, shortness of breath, dizziness, and headache lasting one day.\n\n2. History of Present Illness\nThe patient reported that one day prior she developed a fever accompanied by cough, shortness of breath, dizziness, and headache. The symptoms were continuous and systemic in nature. She denied any worsening factors and noted that the symptoms have now improved. No other associated symptoms such as chest pain or gastrointestinal upset were mentioned. Plan: increase fluid intake and take Biogiesic twice daily.\n\n3. Doctor\'s Assessment\nThe physician evaluated the patient’s acute febrile respiratory illness and deemed it likely a self‑limited viral process. He emphasized adequate hydration and symptomatic relief with an antipyretic/analgesic.\n\n4. Diagnosis (if mentioned)\nNot discussed.\n\n5. Treatment Plan / Prescriptions\nBiogiesic (acetaminophen) – take twice daily as tolerated. Advise drinking plenty of water. Avoid “sucking bricks” (i.e., avoid harmful practices).\n\n6. Follow-up Instructions\nNot discussed.', 'groq', '2026-09-18 10:30:51', '2026-09-18 10:30:51'),
(6, 4, 'lab_request', 'TELE-CARE MEDICAL NETWORK\nCaloocan City, Metro Manila\nContact No.: 09923139504\n\nLABORATORY & DIAGNOSTIC REQUEST\n\nPatient Name: Jerson Uanan Sagun\nAge / Gender: 21 / \nPatient ID: #PT-0008\nDate: September 18, 2026 - 5:09 PM\n\nPriority: Routine\nRequested Diagnostic Examinations:\n\nEXAMPLE DOCTOR#1, MD\nLicense No.: 0148920\nPTR No.: 8841209', '{\"diagnosis\":\"\",\"prescription\":{\"drugs\":[{\"name\":\"\",\"dispense\":\"\",\"freq\":\"\",\"duration\":\"\",\"sig\":\"\"}],\"notes\":\"\"},\"med_cert\":{\"leave_from\":\"\",\"leave_to\":\"\",\"fitness\":\"Unfit for work\\/school duties (Strict Bed Rest)\",\"remarks\":\"\",\"notes\":\"\"},\"lab_request\":{\"priority\":\"Routine\",\"tests\":[],\"indication\":\"\",\"notes\":\"\"}}', NULL, NULL, '2026-09-18 13:53:07', '2026-09-18 13:53:07'),
(8, 4, 'prescription', 'Telecare\nTelecare\nContact No.: 09923139504\n\nELECTRONIC MEDICAL PRESCRIPTION\n\nPatient Name: Jerson Uanan Sagun\nAge / Gender: 21 / Male\nPatient ID: #PT-0008\nDate: September 18, 2026 - 5:09 PM\nClinical Impression / Diagnosis: EXAMPLE\n\nRx\n1. BIOGESIC   Disp: 2 TABS\n   Sig: TAKE AFTER MEALS (DRINK 1 EVERY 6 HOURS — 7 DAYS)\n\nEXAMPLE DOCTOR#1, MD\nLicense No.: 0148920\nPTR No.: 8841209', '{\"diagnosis\":\"EXAMPLE\",\"prescription\":{\"drugs\":[{\"name\":\"BIOGESIC\",\"dispense\":\"2 TABS\",\"freq\":\"DRINK 1 EVERY 6 HOURS\",\"duration\":\"7 DAYS\",\"sig\":\"TAKE AFTER MEALS\"}],\"notes\":\"\"},\"med_cert\":{\"leave_from\":\"\",\"leave_to\":\"\",\"fitness\":\"Unfit for work\\/school duties (Strict Bed Rest)\",\"remarks\":\"\",\"notes\":\"\"},\"lab_request\":{\"priority\":\"Routine\",\"tests\":[],\"indication\":\"\",\"notes\":\"\"}}', NULL, NULL, '2026-09-18 14:13:17', '2026-09-18 14:13:17'),
(9, 9, 'prescription', 'Rx [MEDICATION NAME / STRENGTH / FORM]\n[INSTRUCTIONS / DOSAGE]\nDispense: [QUANTITY / VOLUME]\nLabel: [DIRECTIONS FOR USE]\n[ADDITIONAL INSTRUCTIONS]\n\nClinical note based on consultation summary:\n1. Chief Complaint\nCough for five days with recent yellowish sputum, fever, sore throat, nasal congestion, headache, and generalized weakness.\n\n2. History of Present Illness\nThe patient reported that five days ago he developed a dry cough which progressed over the past two days to a productive cough with yellowish sputum. Yesterday night he experienced a fever of 38.2 °C that subsided after taking paracetamol. He also noted sore throat, nasal congestion, headache, and fatigue over the same period. He has a long‑standing history of asthma, using a salbutamol inhaler twice the previous night for wheezing, and a history of hypertension. He took a cold medicine that also contained paracetamol in addition to a separate dose of paracetamol, raising concern for duplicate dosing. He reports an allergy to ibuprofen. Plan: monitor symptoms, avoid duplicate paracetamol, continue salbutamol PRN, and arrange follow‑up.\n\n3. Doctor\'s Assessment\nThe physician identified an acute upper respiratory infection with possible bronchitis and noted an asthma component. He emphasized the risk of inadvertent overdose from multiple agents containing paracetamol and confirmed the patient’s ibuprofen allergy. He instructed the patient to monitor for worsening dyspnea, chest pain, or hemoptysis and to seek emergency care if these occur.\n\n4. Diagnosis (if mentioned)\nNot discussed.\n\n5. Treatment Plan / Prescriptions\n- Continue salbutamol inhaler as needed for wheezing or shortness of breath.  \n- Use paracetamol only as a single agent for fever/pain; avoid additional cold medicines that contain paracetamol.  \n- Avoid ibuprofen due to documented allergy.  \n- No new prescriptions were written.\n\n6. Follow-up Instructions\nThe patient was advised to follow up for a repeat evaluation if symptoms improve or worsen, and to seek immediate emergency care if he develops severe breathing difficulty, chest pain, or coughs up blood. Not discussed.', NULL, '1. Chief Complaint\nCough for five days with recent yellowish sputum, fever, sore throat, nasal congestion, headache, and generalized weakness.\n\n2. History of Present Illness\nThe patient reported that five days ago he developed a dry cough which progressed over the past two days to a productive cough with yellowish sputum. Yesterday night he experienced a fever of 38.2 °C that subsided after taking paracetamol. He also noted sore throat, nasal congestion, headache, and fatigue over the same period. He has a long‑standing history of asthma, using a salbutamol inhaler twice the previous night for wheezing, and a history of hypertension. He took a cold medicine that also contained paracetamol in addition to a separate dose of paracetamol, raising concern for duplicate dosing. He reports an allergy to ibuprofen. Plan: monitor symptoms, avoid duplicate paracetamol, continue salbutamol PRN, and arrange follow‑up.\n\n3. Doctor\'s Assessment\nThe physician identified an acute upper respiratory infection with possible bronchitis and noted an asthma component. He emphasized the risk of inadvertent overdose from multiple agents containing paracetamol and confirmed the patient’s ibuprofen allergy. He instructed the patient to monitor for worsening dyspnea, chest pain, or hemoptysis and to seek emergency care if these occur.\n\n4. Diagnosis (if mentioned)\nNot discussed.\n\n5. Treatment Plan / Prescriptions\n- Continue salbutamol inhaler as needed for wheezing or shortness of breath.  \n- Use paracetamol only as a single agent for fever/pain; avoid additional cold medicines that contain paracetamol.  \n- Avoid ibuprofen due to documented allergy.  \n- No new prescriptions were written.\n\n6. Follow-up Instructions\nThe patient was advised to follow up for a repeat evaluation if symptoms improve or worsen, and to seek immediate emergency care if he develops severe breathing difficulty, chest pain, or coughs up blood. Not discussed.', 'groq', '2026-09-18 16:04:00', '2026-09-18 16:04:00'),
(10, 4, 'med_cert', 'TELE-CARE MEDICAL NETWORK\nCaloocan City, Metro Manila\nContact No.: 09923139504\n\nOFFICIAL MEDICAL CERTIFICATE\n\nPatient Name: Jerson Uanan Sagun\nAge / Gender: 21 / Male\nPatient ID: #PT-0008\nDate: September 18, 2026 - 5:09 PM\nClinical Impression / Diagnosis: MED CERT\n\nTO WHOM IT MAY CONCERN\n\nThis is to certify that Jerson Uanan Sagun, 21 years of age, was clinically examined and treated via TELE-CARE secure teleconsultation on September 18, 2026 - 5:09 PM under my medical care.\nDiagnostic Evaluation: MED CERT\nClinical Fitness Determination: Fit to return to work/school\nRecommended Rest Period: 2026-09-03 to 2026-09-24\n\nEXAMPLE DOCTOR#1, MD\nLicense No.: 0148920\nThis document can be used for non-medico-legal purposes only.', '{\"diagnosis\":\"MED CERT\",\"prescription\":{\"drugs\":[{\"name\":\"\",\"dispense\":\"\",\"freq\":\"\",\"duration\":\"\",\"sig\":\"\"}],\"notes\":\"\"},\"med_cert\":{\"leave_from\":\"2026-09-03\",\"leave_to\":\"2026-09-24\",\"fitness\":\"Fit to return to work\\/school\",\"remarks\":\"\",\"notes\":\"\"},\"lab_request\":{\"priority\":\"Routine\",\"tests\":[],\"indication\":\"\",\"notes\":\"\"}}', NULL, NULL, '2026-09-18 17:21:23', '2026-09-18 17:21:23'),
(11, 12, 'prescription', 'Rx [MEDICATION NAME / STRENGTH / FORM]\n[INSTRUCTIONS / DOSAGE]\nDispense: [QUANTITY / VOLUME]\nLabel: [DIRECTIONS FOR USE]\n[ADDITIONAL INSTRUCTIONS]\n\nClinical note based on consultation summary:\n1. Chief Complaint\nCough for five days, now productive with yellow sputum, accompanied by sore throat, nasal congestion, fever up to 38.2 °C, and nighttime wheezing in a patient with a history of asthma.\nFilipino: Ubo ng limang araw, ngayon may plema na dilaw, kasabay ng sore throat, sipon, lagnat hanggang 38.2 °C, at pag-ungol sa gabi sa pasyenteng may kasaysayan ng hika.\n\n2. History of Present Illness\nThe patient reported a five‑day history of cough that began as a dry cough and progressed to a productive cough with yellowish sputum. He also experienced sore throat, rhinorrhea, and a fever that peaked at 38.2 °C last night. He denied chest pain but noted mild wheezing, especially at night. He has a longstanding diagnosis of asthma and uses a salbutamol inhaler; for fever and wheezing he self‑administers 500 mg paracetamol and a cold medication containing paracetamol and chlorphenamine. Plan: continue current asthma therapy, use antipyretics as needed, and seek further medical evaluation if symptoms worsen or persist.\nFilipino: Iniulat ng pasyente ang limang araw na kasaysayan ng ubo na nagsimula bilang tuyong ubo at naging produktibo na may dilaw na plema. Naranasan din niya ang sore throat, sipon, at lagnat na umabot sa 38.2 °C kagabi. Itinanggi niya ang pananakit ng dibdib ngunit napansin ang bahagyang pag-ungol, lalo na sa gabi. May matagal nang diagnosis ng hika at gumagamit ng salbutamol inhaler; para sa lagnat at pag-ungol, umiinom siya ng 500 mg na paracetamol at isang gamot sa sipon na may paracetamol at chlorphenamine. Plano: ipagpatuloy ang kasalukuyang therapy para sa hika, gumamit ng antipyretic kung kinakailangan, at magpatingin muli kung lumala o magpatuloy ang mga sintomas.\n\n3. Doctor\'s Assessment\nNot discussed.\nFilipino: Hindi napag-usapan.\n\n4. Diagnosis (if mentioned)\nNot discussed.\nFilipino: Hindi napag-usapan.\n\n5. Treatment Plan / Prescriptions\nNot discussed.\nFilipino: Hindi napag-usapan.\n\n6. Follow-up Instructions\nNot discussed.\nFilipino: Hindi napag-usapan.', NULL, '1. Chief Complaint\nCough for five days, now productive with yellow sputum, accompanied by sore throat, nasal congestion, fever up to 38.2 °C, and nighttime wheezing in a patient with a history of asthma.\nFilipino: Ubo ng limang araw, ngayon may plema na dilaw, kasabay ng sore throat, sipon, lagnat hanggang 38.2 °C, at pag-ungol sa gabi sa pasyenteng may kasaysayan ng hika.\n\n2. History of Present Illness\nThe patient reported a five‑day history of cough that began as a dry cough and progressed to a productive cough with yellowish sputum. He also experienced sore throat, rhinorrhea, and a fever that peaked at 38.2 °C last night. He denied chest pain but noted mild wheezing, especially at night. He has a longstanding diagnosis of asthma and uses a salbutamol inhaler; for fever and wheezing he self‑administers 500 mg paracetamol and a cold medication containing paracetamol and chlorphenamine. Plan: continue current asthma therapy, use antipyretics as needed, and seek further medical evaluation if symptoms worsen or persist.\nFilipino: Iniulat ng pasyente ang limang araw na kasaysayan ng ubo na nagsimula bilang tuyong ubo at naging produktibo na may dilaw na plema. Naranasan din niya ang sore throat, sipon, at lagnat na umabot sa 38.2 °C kagabi. Itinanggi niya ang pananakit ng dibdib ngunit napansin ang bahagyang pag-ungol, lalo na sa gabi. May matagal nang diagnosis ng hika at gumagamit ng salbutamol inhaler; para sa lagnat at pag-ungol, umiinom siya ng 500 mg na paracetamol at isang gamot sa sipon na may paracetamol at chlorphenamine. Plano: ipagpatuloy ang kasalukuyang therapy para sa hika, gumamit ng antipyretic kung kinakailangan, at magpatingin muli kung lumala o magpatuloy ang mga sintomas.\n\n3. Doctor\'s Assessment\nNot discussed.\nFilipino: Hindi napag-usapan.\n\n4. Diagnosis (if mentioned)\nNot discussed.\nFilipino: Hindi napag-usapan.\n\n5. Treatment Plan / Prescriptions\nNot discussed.\nFilipino: Hindi napag-usapan.\n\n6. Follow-up Instructions\nNot discussed.\nFilipino: Hindi napag-usapan.', 'groq', '2026-09-25 15:02:20', '2026-09-25 15:02:20');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_hmo`
--

CREATE TABLE `appointment_hmo` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `hmo_provider` varchar(100) NOT NULL,
  `hmo_member_id` varchar(60) NOT NULL,
  `member_type` enum('Member','Dependent') NOT NULL,
  `principal_member_name` varchar(150) NOT NULL,
  `company_employer` varchar(150) DEFAULT NULL,
  `hmo_plan` varchar(100) DEFAULT NULL,
  `patient_name` varchar(150) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `contact_number` varchar(20) NOT NULL,
  `service_type` enum('Online Consultation','Follow-up Consultation') NOT NULL,
  `loa_number` varchar(60) DEFAULT NULL,
  `coverage_status` enum('Pending','In Verification','Action Required','Approved','Not Approved','Not Covered') NOT NULL DEFAULT 'Pending',
  `hmo_coverage_amount` decimal(10,2) DEFAULT NULL,
  `patient_share` decimal(10,2) DEFAULT NULL,
  `diagnosis` text DEFAULT NULL,
  `consultation_notes` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `supporting_documents` varchar(255) DEFAULT NULL,
  `claim_reference_no` varchar(60) DEFAULT NULL,
  `claim_status` enum('Pending','Approved','Denied','Processed') NOT NULL DEFAULT 'Pending',
  `consent` tinyint(1) NOT NULL DEFAULT 0,
  `consent_at` datetime DEFAULT NULL,
  `provider_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `provider_confirmed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `staff_instructions` text DEFAULT NULL,
  `approval_loa_no` varchar(60) DEFAULT NULL,
  `valid_from` date DEFAULT NULL,
  `valid_until` date DEFAULT NULL,
  `approved_service` varchar(150) DEFAULT NULL,
  `benefit_note` varchar(255) DEFAULT NULL,
  `verification_method` varchar(60) DEFAULT NULL,
  `decision_reason` varchar(255) DEFAULT NULL,
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `status_updated_at` datetime DEFAULT NULL,
  `patient_submitted_at` datetime DEFAULT NULL,
  `assistance_requested` tinyint(1) NOT NULL DEFAULT 0,
  `assistance_note` varchar(255) DEFAULT NULL,
  `patient_updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_hmo`
--

INSERT INTO `appointment_hmo` (`id`, `appointment_id`, `hmo_provider`, `hmo_member_id`, `member_type`, `principal_member_name`, `company_employer`, `hmo_plan`, `patient_name`, `date_of_birth`, `contact_number`, `service_type`, `loa_number`, `coverage_status`, `hmo_coverage_amount`, `patient_share`, `diagnosis`, `consultation_notes`, `prescription`, `supporting_documents`, `claim_reference_no`, `claim_status`, `consent`, `consent_at`, `provider_confirmed`, `provider_confirmed_at`, `created_at`, `staff_instructions`, `approval_loa_no`, `valid_from`, `valid_until`, `approved_service`, `benefit_note`, `verification_method`, `decision_reason`, `decided_by`, `decided_at`, `status_updated_at`, `patient_submitted_at`, `assistance_requested`, `assistance_note`, `patient_updated_at`) VALUES
(1, 20, 'Asalus Corporation (Intellicare)', '999999', 'Member', 'Amy Yap', NULL, NULL, 'John Noel Orano', '2004-12-10', '09923139504', 'Online Consultation', NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', 1, '2026-10-17 08:13:38', 0, NULL, '2026-10-17 00:13:38', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-17 08:15:13', 0, NULL, '2026-10-17 08:15:13'),
(2, 21, 'Asalus Corporation (Intellicare)', '017363639303', 'Member', 'Andrei', NULL, NULL, 'Andrei Mesa', '2004-06-30', '+639660138508', 'Online Consultation', NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', 1, '2026-10-17 08:24:31', 0, NULL, '2026-10-17 00:24:31', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-10-17 08:25:24', 0, NULL, '2026-10-17 08:25:24'),
(3, 23, 'Asalus Corporation (Intellicare)', '726363663633', 'Member', 'Drei', NULL, NULL, 'Andrei Mesa', '2004-06-30', '+639660138508', 'Online Consultation', NULL, 'Pending', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', 1, '2026-10-17 09:34:40', 0, NULL, '2026-10-17 01:34:40', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, NULL, NULL),
(4, 24, 'PhilhealthCare, Inc. (PhilCare)', '8463738284673', 'Member', 'Sheldon couper', NULL, NULL, 'Payawal Ian Matthew S.', '2005-12-10', '+639615081766', 'Online Consultation', '30858439348', 'Approved', NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'Pending', 1, '2026-10-17 13:36:04', 1, '2026-10-17 13:43:49', '2026-10-17 05:36:04', NULL, '30858439348', NULL, NULL, 'Online Consultation', NULL, 'LOA/eLOA presented by patient', NULL, 4, '2026-10-17 13:43:49', '2026-10-17 13:43:49', '2026-10-17 13:39:34', 0, NULL, '2026-10-17 13:39:34');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_hmo_docs`
--

CREATE TABLE `appointment_hmo_docs` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `doc_type` varchar(20) NOT NULL,
  `stored_name` varchar(80) NOT NULL,
  `original_name` varchar(150) NOT NULL,
  `mime` varchar(60) NOT NULL,
  `size_bytes` int(11) NOT NULL DEFAULT 0,
  `uploaded_by` enum('patient','staff') NOT NULL DEFAULT 'patient',
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_hmo_docs`
--

INSERT INTO `appointment_hmo_docs` (`id`, `appointment_id`, `doc_type`, `stored_name`, `original_name`, `mime`, `size_bytes`, `uploaded_by`, `created_at`) VALUES
(1, 20, 'card', 'fcee8b8636d86138ba1ec34454ecb46d.jpg', 'c84ea630-ad68-493f-afde-29d9b097729e.jpg', 'image/jpeg', 207157, 'patient', '2026-10-17 00:14:57'),
(2, 21, 'card', 'b4c9d840ce8c2aa9f3283a6a700f77da.png', 'file_0000000055c4820797b4882678d9bc87.png', 'image/png', 1314831, 'patient', '2026-10-17 00:24:56'),
(4, 24, 'card', '42f5a8376b504147603c08e83bda324b.png', 'Neon Crown Z Gaming Emblem.png', 'image/png', 1172923, 'patient', '2026-10-17 05:39:16');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `appointment_yakap`
--

CREATE TABLE `appointment_yakap` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `philhealth_pin` varchar(14) NOT NULL,
  `patient_name` varchar(150) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `member_type` enum('Member','Dependent') NOT NULL,
  `contact_number` varchar(20) NOT NULL,
  `address` varchar(255) NOT NULL,
  `yakap_clinic` varchar(150) NOT NULL,
  `empanelment_status` enum('Empaneled','Not Yet Empaneled') NOT NULL,
  `fpe_status` enum('Completed','Not Yet Completed') NOT NULL,
  `consent` tinyint(1) NOT NULL DEFAULT 0,
  `consent_at` datetime DEFAULT NULL,
  `verification_status` enum('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
  `diagnosis_icd10` text DEFAULT NULL,
  `consultation_notes` text DEFAULT NULL,
  `prescription` text DEFAULT NULL,
  `pcu_reference_no` varchar(60) DEFAULT NULL,
  `provider_confirmed` tinyint(1) NOT NULL DEFAULT 0,
  `provider_confirmed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment_yakap`
--

INSERT INTO `appointment_yakap` (`id`, `appointment_id`, `philhealth_pin`, `patient_name`, `date_of_birth`, `member_type`, `contact_number`, `address`, `yakap_clinic`, `empanelment_status`, `fpe_status`, `consent`, `consent_at`, `verification_status`, `diagnosis_icd10`, `consultation_notes`, `prescription`, `pcu_reference_no`, `provider_confirmed`, `provider_confirmed_at`, `created_at`) VALUES
(1, 25, '273126312641', 'Payawal Ian Matthew S.', '2005-12-10', 'Member', '+639615081766', 'kalantas street hillcrest village', 'CALOOCAN CITY', 'Empaneled', 'Completed', 1, '2026-10-17 14:51:49', 'Pending', NULL, NULL, NULL, NULL, 0, NULL, '2026-10-17 06:51:49');

-- --------------------------------------------------------

--
-- Table structure for table `app_login_handoffs`
--

CREATE TABLE `app_login_handoffs` (
  `token_hash` char(64) NOT NULL,
  `session_data` mediumtext NOT NULL,
  `redirect` varchar(500) NOT NULL,
  `expires_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity_type` varchar(30) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `admin_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `created_at`) VALUES
(1, 1, 'update', 'doctor', 5, '{\"email\": \"dizoah3@gmail.com\", \"full_name\": \"Ian Matthew Payawal\"}', '{\"email\": \"dizoah3@gmail.com\", \"full_name\": \"Ian Matthew Payawal\"}', '2026-08-20 00:32:09'),
(2, 1, 'update', 'staff', 4, '{\"email\": \"staff@telecare.com\", \"full_name\": \"TELE-CARE Staff\"}', '{\"email\": \"staff@telecare.com\", \"full_name\": \"TELE-CARE StaffS\"}', '2026-08-20 00:41:26'),
(3, 1, 'update', 'staff', 4, '{\"email\": \"staff@telecare.com\", \"full_name\": \"TELE-CARE StaffS\"}', '{\"email\": \"staff@telecare.com\", \"full_name\": \"TELE-CARE Staff\"}', '2026-08-20 00:41:35'),
(4, 1, 'update', 'doctor', 4, '{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"Ma. Aberlee Lacanaria\"}', '{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#1\"}', '2026-08-20 00:42:12'),
(5, 1, 'create', 'doctor', 6, NULL, '{\"email\": \"xdreiaawe@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#2\", \"specialty\": \"General\", \"subspecialty\": \"\"}', '2026-08-20 14:20:01'),
(6, 1, 'update', 'doctor', 4, '{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#1\"}', '{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#1\"}', '2026-08-21 16:53:37'),
(7, 1, 'toggle', 'doctor', 6, '{\"status\": \"active\"}', '{\"status\": \"inactive\"}', '2026-08-21 16:55:15'),
(8, 1, 'toggle', 'doctor', 6, '{\"status\": \"inactive\"}', '{\"status\": \"active\"}', '2026-08-21 16:55:15'),
(9, 1, 'create', 'service', 5, NULL, '{\"name\": \"asda\", \"price\": \"222\", \"category\": \"Laboratory\"}', '2026-08-23 20:54:32'),
(10, 1, 'set_requirement', 'service', 5, NULL, '{\"product_id\": 2, \"quantity_used\": 1}', '2026-08-23 20:54:43'),
(11, 1, 'set_requirement', 'service', 5, NULL, '{\"product_id\": 5, \"quantity_used\": 1}', '2026-08-23 20:54:46'),
(12, 1, 'remove_requirement', 'service', 5, '{\"requirement_id\": 3}', NULL, '2026-08-23 20:56:06'),
(13, 1, 'remove_requirement', 'service', 5, '{\"requirement_id\": 4}', NULL, '2026-08-23 20:56:07'),
(14, 1, 'create', 'service', 6, NULL, '{\"name\": \"SADA\", \"price\": \"222\", \"category\": \"Laboratory\"}', '2026-08-23 21:09:23'),
(15, 1, 'create', 'service', 7, NULL, '{\"name\": \"sdada\", \"price\": \"222\", \"category\": \"Laboratory\"}', '2026-08-23 22:07:43'),
(16, 1, 'create', 'service', 8, NULL, '{\"name\": \"sda\", \"price\": \"222\", \"category\": \"Laboratory\"}', '2026-08-23 22:11:57'),
(17, 1, 'set_requirement', 'service', 8, NULL, '{\"product_id\": 31, \"quantity_used\": 1}', '2026-08-23 22:12:02'),
(18, 1, 'archive', 'service', 5, '{\"status\": \"Active\"}', '{\"status\": \"Archived\"}', '2026-08-23 22:12:18'),
(19, 1, 'set_requirement', 'service', 5, NULL, '{\"product_id\": 26, \"quantity_used\": 1}', '2026-08-23 22:12:30'),
(20, 1, 'remove_requirement', 'service', 2, '{\"requirement_id\": 2}', NULL, '2026-08-23 22:54:40'),
(21, 1, 'set_requirement', 'service', 2, NULL, '{\"product_id\": 26, \"quantity_used\": 1}', '2026-08-23 22:54:45'),
(22, 1, 'deactivate', 'service', 1, '{\"status\": \"Active\"}', '{\"status\": \"Inactive\"}', '2026-08-29 15:24:44'),
(23, 1, 'deactivate', 'service', 4, '{\"status\": \"Active\"}', '{\"status\": \"Inactive\"}', '2026-08-29 15:27:26'),
(24, 1, 'deactivate', 'service', 4, '{\"status\": \"Active\"}', '{\"status\": \"Inactive\"}', '2026-08-29 15:27:38'),
(25, 1, 'deactivate', 'service', 4, '{\"status\": \"Active\"}', '{\"status\": \"Inactive\"}', '2026-08-29 15:29:24'),
(26, 1, 'deactivate', 'service', 4, '{\"status\": \"Active\"}', '{\"status\": \"Inactive\"}', '2026-08-29 15:32:32'),
(27, 1, 'activate', 'service', 4, '{\"status\": \"Inactive\"}', '{\"status\": \"Active\"}', '2026-08-29 15:32:37'),
(28, 1, 'activate', 'service', 5, '{\"status\": \"Inactive\"}', '{\"status\": \"Active\"}', '2026-08-29 15:33:02'),
(29, 1, 'deactivate', 'service', 5, '{\"status\": \"Active\"}', '{\"status\": \"Inactive\"}', '2026-08-29 15:33:06'),
(30, 1, 'update', 'doctor', 4, '{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#1\"}', '{\"email\": \"almondtofu25@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#1\"}', '2026-09-04 22:27:17'),
(31, 1, 'update', 'doctor', 6, '{\"email\": \"xdreiaawe@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#2\"}', '{\"email\": \"xdreiaawe@gmail.com\", \"full_name\": \"EXAMPLE DOCTOR#2\"}', '2026-09-04 22:27:24'),
(32, 1, 'update', 'document_templates', 0, NULL, '{\"clinic_name\": \"hi\"}', '2026-09-08 23:23:58'),
(33, 1, 'update', 'document_templates', 0, NULL, '{\"clinic_name\": \"hi\"}', '2026-09-08 23:29:17'),
(34, 1, 'create', 'document_templates', 2, NULL, '{\"template_name\": \"Clinic\"}', '2026-09-09 01:34:19'),
(35, 1, 'set_default', 'document_templates', 2, NULL, '{\"doc_type\": \"prescription\"}', '2026-09-09 01:34:30'),
(36, 1, 'update', 'document_templates', 2, NULL, '{\"template_name\": \"Clinic\"}', '2026-09-09 01:35:10'),
(37, 1, 'toggle', 'doctor', 6, '{\"status\":\"active\"}', '{\"status\":\"inactive\"}', '2026-09-18 10:31:34'),
(38, 1, 'toggle', 'doctor', 6, '{\"status\":\"inactive\"}', '{\"status\":\"active\"}', '2026-09-18 10:31:44'),
(39, 1, 'update', 'document_templates', 3, NULL, '{\"template_name\":\"Main Facility\"}', '2026-09-18 10:36:23'),
(40, 1, 'update', 'document_templates', 4, NULL, '{\"template_name\":\"Standard Certificate\"}', '2026-09-18 10:36:37'),
(41, 1, 'archive', 'document_templates', 1, '{\"status\":\"Active\"}', '{\"status\":\"Archived\"}', '2026-09-18 10:36:51'),
(42, 1, 'archive', 'product', 4, '{\"status\":\"Active\"}', '{\"status\":\"Archived\"}', '2026-09-18 10:37:41'),
(43, 1, 'remove_requirement', 'service', 1, '{\"requirement_id\":1}', NULL, '2026-09-18 10:38:11'),
(44, 1, 'create', 'doctor', 7, NULL, '{\"full_name\":\"DR EXAMPLE#3\",\"email\":\"oranojohnnoelbsis2023@gmail.com\",\"department\":\"Neurology\",\"specialty\":\"Neurologist\",\"subspecialty\":\"\"}', '2026-09-18 14:14:46');

-- --------------------------------------------------------

--
-- Table structure for table `discounts`
--

CREATE TABLE `discounts` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('Percentage','Fixed') NOT NULL DEFAULT 'Percentage',
  `value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `conditions_text` text DEFAULT NULL,
  `status` enum('Active','Archived') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `discounts`
--

INSERT INTO `discounts` (`id`, `name`, `type`, `value`, `conditions_text`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Senior Citizen', 'Percentage', 20.00, 'Valid Senior Citizen ID required.', 'Active', '2026-08-23 11:27:45', '2026-08-23 11:27:45'),
(2, 'PWD', 'Percentage', 20.00, 'Valid PWD ID required.', 'Active', '2026-08-23 11:27:45', '2026-08-23 11:27:45'),
(3, 'Clinic Promo', 'Fixed', 100.00, 'Applies to walk-in transactions above ₱500.', 'Active', '2026-08-23 11:27:45', '2026-08-23 11:27:45');

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `specialty` varchar(100) DEFAULT NULL,
  `department` varchar(50) DEFAULT NULL,
  `subspecialty` varchar(100) DEFAULT NULL,
  `credentials` varchar(120) NOT NULL DEFAULT '',
  `clinic_name` varchar(150) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `consultation_fee` decimal(10,2) DEFAULT 0.00,
  `languages_spoken` varchar(255) DEFAULT NULL,
  `rating` decimal(2,1) DEFAULT 0.0,
  `rating_count` int(11) NOT NULL DEFAULT 0,
  `access_level` enum('junior','senior','consultant') DEFAULT 'junior',
  `status` enum('pending','active','inactive') DEFAULT 'pending',
  `is_available` tinyint(1) DEFAULT 0,
  `license_number` varchar(100) DEFAULT NULL,
  `ptr_number` varchar(100) NOT NULL DEFAULT '',
  `s2_number` varchar(100) NOT NULL DEFAULT '',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`id`, `full_name`, `specialty`, `department`, `subspecialty`, `credentials`, `clinic_name`, `email`, `password`, `phone_number`, `profile_photo`, `bio`, `consultation_fee`, `languages_spoken`, `rating`, `rating_count`, `access_level`, `status`, `is_available`, `license_number`, `ptr_number`, `s2_number`, `issuing_board`, `license_file`, `board_cert_file`, `consent_signed`, `is_verified`, `verified_at`, `verified_by`, `invite_token`, `invite_expires`, `setup_complete`, `created_at`, `updated_at`) VALUES
(4, 'EXAMPLE DOCTOR#1', 'General', 'General Medicine / General Practice', '', 'MD', 'EXCELLCARE MEDICAL SYSTEM INC.', 'almondtofu25@gmail.com', '$2y$10$KFzfCMLW/6YVdUWjiYG8b.g0yyUP6ZAW24jy1H7DrbopyR.btv15a', '09999999999', 'uploads/profiles/doc_6aad576da8ad7.jpg', 'General doctor at EXCELLCARE MEDICAL SYSTEM INC.', 500.00, 'Filipino', 5.0, 1, 'senior', 'active', 1, '0148920', '8841209', '', '', NULL, NULL, 1, 1, '2026-03-14 00:39:41', 1, NULL, '2026-03-21 07:46:05', 1, '2026-03-14 06:46:05', '2026-09-18 15:23:25'),
(5, 'Ian Matthew Payawal', 'General', 'Cardiology', 'Cardiology', '', 'TELE-CARE STAFF', 'dizoah3@gmail.com', '$2y$10$/WhcdL6CDFFo7M2w0ynJ4eq0kPxJ.Wnn/Nn4VhHl7.6evTz5DykqW', '09999999992', 'uploads/profiles/doc_69bd4ead3abc2.png', 'Cardiology subspecialty', 750.00, 'English, Filipino', 0.0, 0, 'junior', 'active', 1, '', '', '', '', 'uploads/docs/doc_5_69d7b40d58257.exe', NULL, 1, 0, '2026-04-09 08:13:33', 1, NULL, '2026-03-22 11:33:12', 1, '2026-03-15 10:33:12', '2026-08-21 13:57:01'),
(6, 'EXAMPLE DOCTOR#2', 'General', 'Cardiology', '', '', '', 'xdreiaawe@gmail.com', '$2y$10$0uhxv9h108ktfIiuuNvE9.IfYwnWXZNm958koGuybGGE.Mp1Kug36', '', NULL, NULL, 0.00, '', 0.0, 0, 'junior', 'active', 0, NULL, '', '', NULL, NULL, NULL, 0, 0, NULL, NULL, NULL, NULL, 1, '2026-08-20 06:20:01', '2026-09-18 17:31:44'),
(7, 'DR EXAMPLE#3', 'Neurologist', 'Neurology', '', '', NULL, 'oranojohnnoelbsis2023@gmail.com', '$2y$10$LptCP3x4T3J9Uua61QoskOa01/5llwYWhrNeJlY01bfCHO/gTdGFy', NULL, NULL, NULL, 0.00, NULL, 0.0, 0, 'junior', 'active', 1, NULL, '', '', NULL, NULL, NULL, 0, 0, NULL, NULL, '90be6fa7d881038123b65b8fa5e319bfac5652270678ff2cb8923f721c8f3f0b', '2026-09-25 21:14:46', 0, '2026-09-18 21:14:46', '2026-09-18 21:14:46');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_ratings`
--

CREATE TABLE `doctor_ratings` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `doctor_ratings`
--

INSERT INTO `doctor_ratings` (`id`, `patient_id`, `doctor_id`, `rating`, `comment`, `created_at`, `updated_at`) VALUES
(1, 1, 4, 5, 'Very professional', '2026-09-11 07:28:30', '2026-09-11 07:28:30');

--
-- Triggers `doctor_ratings`
--
DELIMITER $$
CREATE TRIGGER `trg_doctor_ratings_after_delete` AFTER DELETE ON `doctor_ratings` FOR EACH ROW BEGIN
  UPDATE `doctors` d
  SET d.rating = (SELECT COALESCE(ROUND(AVG(r.rating), 1), 0.0) FROM `doctor_ratings` r WHERE r.doctor_id = OLD.doctor_id),
      d.rating_count = (SELECT COUNT(*) FROM `doctor_ratings` r WHERE r.doctor_id = OLD.doctor_id)
  WHERE d.id = OLD.doctor_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_doctor_ratings_after_insert` AFTER INSERT ON `doctor_ratings` FOR EACH ROW BEGIN
  UPDATE `doctors` d
  SET d.rating = (SELECT ROUND(AVG(r.rating), 1) FROM `doctor_ratings` r WHERE r.doctor_id = NEW.doctor_id),
      d.rating_count = (SELECT COUNT(*) FROM `doctor_ratings` r WHERE r.doctor_id = NEW.doctor_id)
  WHERE d.id = NEW.doctor_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_doctor_ratings_after_update` AFTER UPDATE ON `doctor_ratings` FOR EACH ROW BEGIN
  UPDATE `doctors` d
  SET d.rating = (SELECT ROUND(AVG(r.rating), 1) FROM `doctor_ratings` r WHERE r.doctor_id = NEW.doctor_id),
      d.rating_count = (SELECT COUNT(*) FROM `doctor_ratings` r WHERE r.doctor_id = NEW.doctor_id)
  WHERE d.id = NEW.doctor_id;

  -- Rare edge case: doctor_id itself was changed on the row.
  IF OLD.doctor_id <> NEW.doctor_id THEN
    UPDATE `doctors` d
    SET d.rating = (SELECT COALESCE(ROUND(AVG(r.rating), 1), 0.0) FROM `doctor_ratings` r WHERE r.doctor_id = OLD.doctor_id),
        d.rating_count = (SELECT COUNT(*) FROM `doctor_ratings` r WHERE r.doctor_id = OLD.doctor_id)
    WHERE d.id = OLD.doctor_id;
  END IF;
END
$$
DELIMITER ;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor_schedules`
--

INSERT INTO `doctor_schedules` (`id`, `doctor_id`, `day_of_week`, `start_time`, `end_time`) VALUES
(136, 5, 'Monday', '06:00:00', '13:00:00'),
(137, 5, 'Monday', '15:00:00', '19:00:00'),
(138, 5, 'Friday', '06:00:00', '14:00:00'),
(139, 5, 'Saturday', '06:00:00', '14:00:00'),
(151, 4, 'Monday', '09:00:00', '17:00:00'),
(152, 4, 'Tuesday', '09:00:00', '17:00:00'),
(153, 4, 'Wednesday', '09:00:00', '17:00:00'),
(154, 4, 'Thursday', '09:00:00', '17:00:00'),
(155, 4, 'Friday', '09:00:00', '17:00:00'),
(156, 4, 'Saturday', '09:00:00', '17:00:00'),
(157, 4, 'Sunday', '09:00:00', '17:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_schedule_requests`
--

CREATE TABLE `doctor_schedule_requests` (
  `id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `slots_json` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending',
  `staff_note` varchar(255) DEFAULT NULL,
  `submitted_at` datetime NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `doctor_seen` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data for table `doctor_schedule_requests`
--

INSERT INTO `doctor_schedule_requests` (`id`, `doctor_id`, `slots_json`, `status`, `staff_note`, `submitted_at`, `reviewed_at`, `reviewed_by`, `doctor_seen`) VALUES
(1, 4, '[{\"day\":\"Monday\",\"start\":\"09:00\",\"end\":\"17:00\"},{\"day\":\"Tuesday\",\"start\":\"09:00\",\"end\":\"17:00\"},{\"day\":\"Wednesday\",\"start\":\"09:00\",\"end\":\"17:00\"},{\"day\":\"Thursday\",\"start\":\"09:00\",\"end\":\"17:00\"},{\"day\":\"Friday\",\"start\":\"09:00\",\"end\":\"17:00\"},{\"day\":\"Saturday\",\"start\":\"09:00\",\"end\":\"17:00\"},{\"day\":\"Sunday\",\"start\":\"09:00\",\"end\":\"17:00\"}]', 'Rejected', 'test', '2026-09-18 13:55:06', '2026-09-18 13:58:56', 4, 1);

-- --------------------------------------------------------

--
-- Table structure for table `doctor_schedule_settings`
--

CREATE TABLE `doctor_schedule_settings` (
  `doctor_id` int(11) NOT NULL,
  `consultation_duration` smallint(5) UNSIGNED NOT NULL DEFAULT 30,
  `appointment_interval` smallint(5) UNSIGNED NOT NULL DEFAULT 5,
  `break_start` time DEFAULT NULL,
  `break_end` time DEFAULT NULL,
  `consultation_types` varchar(255) NOT NULL DEFAULT 'In-person',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `document_templates`
--

CREATE TABLE `document_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `doc_type` varchar(30) NOT NULL,
  `template_name` varchar(255) NOT NULL DEFAULT '',
  `clinic_name` varchar(255) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `contact_no` varchar(100) NOT NULL DEFAULT '',
  `footer_note` text DEFAULT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_templates`
--

INSERT INTO `document_templates` (`id`, `doc_type`, `template_name`, `clinic_name`, `address`, `contact_no`, `footer_note`, `is_default`, `status`, `updated_at`) VALUES
(1, 'prescription', 'Main Clinic', 'hi', '', '', '', 0, 'Archived', '2026-09-18 17:36:51'),
(2, 'prescription', 'Clinic', 'Telecare', 'Telecare', '09923139504', '', 1, 'Active', '2026-09-08 17:34:29'),
(3, 'lab_request', 'Main Facility', 'TELE-CARE', 'Caloocan City, Metro Manila', '09923139504', '', 1, 'Active', '2026-09-18 17:36:23'),
(4, 'med_cert', 'Standard Certificate', 'TELE-CARE', 'Caloocan City, Metro Manila', '09923139504', 'This document can be used for non-medico-legal purposes only.', 1, 'Active', '2026-09-18 17:36:37');

-- --------------------------------------------------------

--
-- Table structure for table `document_templates_backup`
--

CREATE TABLE `document_templates_backup` (
  `doc_type` varchar(30) NOT NULL,
  `clinic_name` varchar(255) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `contact_no` varchar(100) NOT NULL DEFAULT '',
  `physician_name` varchar(255) NOT NULL DEFAULT '',
  `credentials` varchar(100) NOT NULL DEFAULT '',
  `license_no` varchar(100) NOT NULL DEFAULT '',
  `ptr_no` varchar(100) NOT NULL DEFAULT '',
  `footer_note` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_templates_backup`
--

INSERT INTO `document_templates_backup` (`doc_type`, `clinic_name`, `address`, `contact_no`, `physician_name`, `credentials`, `license_no`, `ptr_no`, `footer_note`, `updated_at`) VALUES
('prescription', 'hi', '', '', '', '', '', '', '', '2026-09-08 15:23:58');

-- --------------------------------------------------------

--
-- Table structure for table `document_templates_old`
--

CREATE TABLE `document_templates_old` (
  `doc_type` varchar(30) NOT NULL,
  `clinic_name` varchar(255) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `contact_no` varchar(100) NOT NULL DEFAULT '',
  `physician_name` varchar(255) NOT NULL DEFAULT '',
  `credentials` varchar(100) NOT NULL DEFAULT '',
  `license_no` varchar(100) NOT NULL DEFAULT '',
  `ptr_no` varchar(100) NOT NULL DEFAULT '',
  `footer_note` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_templates_old`
--

INSERT INTO `document_templates_old` (`doc_type`, `clinic_name`, `address`, `contact_no`, `physician_name`, `credentials`, `license_no`, `ptr_no`, `footer_note`, `updated_at`) VALUES
('prescription', 'hi', '', '', '', '', '', '', '', '2026-09-08 15:23:58');

-- --------------------------------------------------------

--
-- Table structure for table `guest_bookings`
--

CREATE TABLE `guest_bookings` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `access_token` char(64) NOT NULL,
  `first_name` varchar(80) NOT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `sex` varchar(10) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `complaint` varchar(500) DEFAULT NULL,
  `room_emailed_at` datetime DEFAULT NULL,
  `summary_emailed_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guest_bookings`
--

INSERT INTO `guest_bookings` (`id`, `appointment_id`, `patient_id`, `access_token`, `first_name`, `middle_name`, `last_name`, `full_name`, `date_of_birth`, `sex`, `address`, `phone`, `email`, `complaint`, `room_emailed_at`, `summary_emailed_at`, `created_at`) VALUES
(1, 20, 16, '9895223460c7304a04676a1e69b52f99e07c53f460f3e31fd3bf6183941c6c13', 'John Noel', '', 'Orano', 'John Noel Orano', '2004-12-10', 'Male', 'NPC KANAN MAKISIG STREET', '09923139504', 'oranonoel0@gmail.com', 'feeling sick and cough', '2026-10-17 08:13:40', NULL, '2026-10-17 00:12:30');

-- --------------------------------------------------------

--
-- Table structure for table `guest_files`
--

CREATE TABLE `guest_files` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `kind` varchar(30) NOT NULL,
  `label` varchar(190) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guest_mail_log`
--

CREATE TABLE `guest_mail_log` (
  `id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `kind` varchar(20) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `guest_mail_log`
--

INSERT INTO `guest_mail_log` (`id`, `email`, `ip`, `kind`, `created_at`) VALUES
(1, 'oranonoel0@gmail.com', '136.158.41.228', 'otp', '2026-10-17 00:11:56'),
(2, 'kimpot@gmail.com', '138.84.142.185', 'otp', '2026-10-17 01:07:38');

-- --------------------------------------------------------

--
-- Table structure for table `hmo_access_log`
--

CREATE TABLE `hmo_access_log` (
  `id` int(11) NOT NULL,
  `actor_type` enum('admin','staff') NOT NULL,
  `actor_id` int(11) NOT NULL,
  `action` varchar(40) NOT NULL,
  `entity` varchar(30) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hmo_access_log`
--

INSERT INTO `hmo_access_log` (`id`, `actor_type`, `actor_id`, `action`, `entity`, `entity_id`, `appointment_id`, `detail`, `ip`, `created_at`) VALUES
(1, 'staff', 4, 'view_document', 'hmo_document', 1, 20, 'card: c84ea630-ad68-493f-afde-29d9b097729e.jpg', '136.158.41.228', '2026-10-17 01:34:36'),
(2, 'staff', 4, 'view_document', 'hmo_document', 1, 20, 'card: c84ea630-ad68-493f-afde-29d9b097729e.jpg', '136.158.41.228', '2026-10-17 01:34:41'),
(3, 'staff', 4, 'view_document', 'hmo_document', 1, 20, 'card: c84ea630-ad68-493f-afde-29d9b097729e.jpg', '136.158.41.228', '2026-10-17 01:34:46'),
(4, 'staff', 4, 'view_document', 'hmo_document', 2, 21, 'card: file_0000000055c4820797b4882678d9bc87.png', '136.158.41.228', '2026-10-17 01:34:48'),
(5, 'staff', 4, 'view_document', 'hmo_document', 4, 24, 'card: Neon Crown Z Gaming Emblem.png', '175.176.29.226', '2026-10-17 05:40:53'),
(6, 'staff', 4, 'view_document', 'hmo_document', 4, 24, 'card: Neon Crown Z Gaming Emblem.png', '175.176.29.226', '2026-10-17 05:42:33'),
(7, 'staff', 4, 'view_document', 'hmo_document', 4, 24, 'card: Neon Crown Z Gaming Emblem.png', '175.176.29.226', '2026-10-17 05:43:37'),
(8, 'staff', 4, 'view_document', 'hmo_document', 4, 24, 'card: Neon Crown Z Gaming Emblem.png', '175.176.29.226', '2026-10-17 05:43:49');

-- --------------------------------------------------------

--
-- Table structure for table `hmo_case_events`
--

CREATE TABLE `hmo_case_events` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `event_type` varchar(30) NOT NULL,
  `status_from` varchar(30) DEFAULT NULL,
  `status_to` varchar(30) DEFAULT NULL,
  `summary` varchar(255) NOT NULL,
  `detail` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hmo_case_events`
--

INSERT INTO `hmo_case_events` (`id`, `appointment_id`, `staff_id`, `event_type`, `status_from`, `status_to`, `summary`, `detail`, `created_at`) VALUES
(1, 20, NULL, 'Patient', NULL, NULL, 'Patient uploaded 1 document.', 'HMO card / membership proof', '2026-10-17 00:14:57'),
(2, 20, NULL, 'Patient', NULL, NULL, 'Patient submitted the booking for HMO verification.', '1 document(s) on file; no LOA yet', '2026-10-17 00:15:13'),
(3, 21, NULL, 'Patient', NULL, NULL, 'Patient uploaded 1 document.', 'HMO card / membership proof', '2026-10-17 00:24:56'),
(4, 21, NULL, 'Patient', NULL, NULL, 'Patient submitted the booking for HMO verification.', '1 document(s) on file; no LOA yet', '2026-10-17 00:25:24'),
(5, 24, NULL, 'Patient', NULL, NULL, 'Patient uploaded 1 document.', 'Valid ID', '2026-10-17 05:37:15'),
(6, 24, NULL, 'Patient', NULL, NULL, 'Patient removed a document.', NULL, '2026-10-17 05:38:25'),
(7, 24, NULL, 'Patient', NULL, NULL, 'Patient uploaded 1 document.', 'HMO card / membership proof', '2026-10-17 05:39:16'),
(8, 24, NULL, 'Patient', NULL, NULL, 'Patient submitted the booking for HMO verification.', '1 document(s) on file; LOA number given', '2026-10-17 05:39:34'),
(9, 24, 4, 'Status', 'Pending', 'In Verification', 'Verification started.', NULL, '2026-10-17 05:42:32'),
(10, 24, 4, 'Authorization', 'In Verification', 'Approved', 'HMO approved.', 'Verified by: LOA/eLOA presented by patient\nLOA / authorization no.: 30858439348\nService: Online Consultation', '2026-10-17 05:43:49');

-- --------------------------------------------------------

--
-- Table structure for table `hmo_contact_log`
--

CREATE TABLE `hmo_contact_log` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `channel` varchar(40) NOT NULL,
  `contact_detail` varchar(150) DEFAULT NULL,
  `contacted_at` datetime NOT NULL,
  `hmo_reference_no` varchar(80) DEFAULT NULL,
  `response` text NOT NULL,
  `follow_up_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hmo_coverage`
--

CREATE TABLE `hmo_coverage` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `description` varchar(200) NOT NULL,
  `coverage_type` enum('Percentage','Fixed') NOT NULL DEFAULT 'Percentage',
  `coverage_value` decimal(10,2) NOT NULL DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hmo_coverage`
--

INSERT INTO `hmo_coverage` (`id`, `provider_id`, `description`, `coverage_type`, `coverage_value`, `notes`, `created_at`) VALUES
(1, 1, 'General Consultation', 'Percentage', 100.00, 'Fully covered, no co-pay.', '2026-08-23 11:28:12'),
(2, 1, 'Laboratory Tests', 'Percentage', 80.00, '20% co-pay applies.', '2026-08-23 11:28:12'),
(3, 2, 'General Consultation', 'Fixed', 300.00, 'Fixed coverage amount, excess billed to patient.', '2026-08-23 11:28:12');

-- --------------------------------------------------------

--
-- Table structure for table `hmo_providers`
--

CREATE TABLE `hmo_providers` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `license_no` varchar(30) DEFAULT NULL,
  `contact_person` varchar(150) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `status` enum('Active','Archived') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `on_hold` tinyint(1) NOT NULL DEFAULT 0,
  `hold_reason` varchar(255) DEFAULT NULL,
  `hold_since` datetime DEFAULT NULL,
  `provider_code` varchar(20) DEFAULT NULL,
  `support_channels` varchar(255) DEFAULT NULL,
  `teleconsult_available` tinyint(1) NOT NULL DEFAULT 1,
  `effective_from` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `hmo_providers`
--

INSERT INTO `hmo_providers` (`id`, `name`, `license_no`, `contact_person`, `phone`, `email`, `status`, `created_at`, `updated_at`, `on_hold`, `hold_reason`, `hold_since`, `provider_code`, `support_channels`, `teleconsult_available`, `effective_from`) VALUES
(1, 'Maxicare Healthcare Corp.', 'HMO-2023-07-R', 'Anna Cruz', '02-8888-1234', 'partners@maxicare.example', 'Active', '2026-08-23 11:28:12', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(2, 'Asalus Corporation (Intellicare)', 'HMO-2023-06-R', 'Mark Reyes', '02-8888-5678', 'partners@intellicare.example', 'Active', '2026-08-23 11:28:12', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(3, 'Asiancare Health Systems, Inc.', 'HMO-2024-01-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(4, 'Avega Managed Care, Inc.', 'HMO-2023-05-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(5, 'Carewell Health Systems, Inc.', 'HMO-2023-09-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(6, 'Cooperative Health Management Federation', 'HMO-2024-03-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(7, 'Dynamic Care Corporation', 'HMO-2023-20-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(8, 'EastWest Healthcare, Inc.', 'HMO-2023-14-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(9, 'Getwell Health Systems, Inc.', 'HMO-2023-13-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(10, 'Health Care and Development Corporation of the Philippines', 'HMO-2024-04-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(11, 'Health Delivery System, Inc.', 'HMO-2024-06-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(12, 'Health Maintenance, Inc.', 'HMO-2023-02-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(13, 'Health Plan Philippines, Inc.', 'HMO-2023-04-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(14, 'IMS Wellth Care, Inc.', 'HMO-2023-01-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(15, 'Insular Health Care, Inc.', 'HMO-2023-03-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(16, 'Kaiser International Healthgroup, Inc.', 'HMO-2023-08-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(17, 'Life & Health HMP, Inc.', 'HMO-2023-16-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(18, 'Medicard Philippines, Inc.', 'HMO-2023-12-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(19, 'Medicare Plus, Inc.', 'HMO-2023-21-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(20, 'Medocare Health Systems, Inc.', 'HMO-2023-17-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(21, 'Metrocare Health Systems, Incorporated', 'HMO-2023-11-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(22, 'MI Healthcare Inc.', 'HMO-2024-05-O', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(23, 'Optimum Medical and Healthcare Services Inc.', 'HMO-2023-19-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(24, 'Pacific Cross Health Care, Inc.', 'HMO-2023-10-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(25, 'PhilhealthCare, Inc. (PhilCare)', 'HMO-2023-15-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(26, 'Value Care Health Systems, Inc.', 'HMO-2023-18-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL),
(27, 'Wellcare Health Maintenance Inc.', 'HMO-2024-02-R', NULL, NULL, NULL, 'Active', '2026-10-11 06:25:22', '2026-10-11 06:25:22', 0, NULL, NULL, NULL, NULL, 1, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `hmo_provider_contacts`
--

CREATE TABLE `hmo_provider_contacts` (
  `id` int(11) NOT NULL,
  `provider_id` int(11) NOT NULL,
  `agent_name` varchar(120) NOT NULL,
  `role_title` varchar(80) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `channel` varchar(60) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hmo_provider_doctors`
--

CREATE TABLE `hmo_provider_doctors` (
  `provider_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hmo_rules`
--

CREATE TABLE `hmo_rules` (
  `provider_id` int(11) NOT NULL,
  `loa_requirement` enum('required','optional','not_applicable') NOT NULL DEFAULT 'optional',
  `member_fields` varchar(255) NOT NULL DEFAULT '',
  `required_docs` varchar(255) NOT NULL DEFAULT 'card',
  `verification_method` enum('portal','api','phone','email') NOT NULL DEFAULT 'phone',
  `validity_days` int(11) DEFAULT NULL,
  `consult_types` varchar(255) NOT NULL DEFAULT 'Online Consultation,Follow-up Consultation',
  `copay_note` varchar(255) DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `hmo_staff_permissions`
--

CREATE TABLE `hmo_staff_permissions` (
  `staff_id` int(11) NOT NULL,
  `can_verify` tinyint(1) NOT NULL DEFAULT 1,
  `can_update_status` tinyint(1) NOT NULL DEFAULT 1,
  `can_view_documents` tinyint(1) NOT NULL DEFAULT 1,
  `updated_by` int(11) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `issued_documents`
--

CREATE TABLE `issued_documents` (
  `id` int(11) NOT NULL,
  `appointment_id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `doc_type` enum('prescription','lab_request','med_cert') NOT NULL,
  `doc_no` varchar(40) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `payload_json` longtext DEFAULT NULL,
  `plain_text` longtext DEFAULT NULL,
  `issued_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `issued_documents`
--

INSERT INTO `issued_documents` (`id`, `appointment_id`, `patient_id`, `doctor_id`, `doc_type`, `doc_no`, `file_path`, `payload_json`, `plain_text`, `issued_at`) VALUES
(1, 4, 8, 4, 'prescription', 'TC-RX-2026-0004-1381', 'uploads/generated_documents/document_4_prescription_20260918_215343.pdf', '{\"diagnosis\":\"\",\"prescription\":{\"drugs\":[{\"name\":\"Biogesic\",\"dispense\":\"14\",\"freq\":\"1 every hours\",\"duration\":\"7 days\",\"sig\":\"Take after meals\"}],\"notes\":\"\"},\"med_cert\":{\"leave_from\":\"\",\"leave_to\":\"\",\"fitness\":\"Unfit for work\\/school duties (Strict Bed Rest)\",\"remarks\":\"\",\"notes\":\"\"},\"lab_request\":{\"priority\":\"Routine\",\"tests\":[],\"indication\":\"\",\"notes\":\"\"}}', 'Telecare\nTelecare\nContact No.: 09923139504\n\nELECTRONIC MEDICAL PRESCRIPTION\n\nPatient Name: Jerson Uanan Sagun\nAge / Gender: 21 / \nPatient ID: #PT-0008\nDate: September 18, 2026 - 5:09 PM\n\nRx\n1. Biogesic   Disp: 14\n   Sig: Take after meals (1 every hours — 7 days)\n\nEXAMPLE DOCTOR#1, MD\nLicense No.: 0148920\nPTR No.: 8841209', '2026-09-18 06:53:43'),
(2, 4, 8, 4, 'prescription', 'TC-RX-2026-0004-DDF4', 'uploads/generated_documents/document_4_prescription_20260918_221317.pdf', '{\"diagnosis\":\"EXAMPLE\",\"prescription\":{\"drugs\":[{\"name\":\"BIOGESIC\",\"dispense\":\"2 TABS\",\"freq\":\"DRINK 1 EVERY 6 HOURS\",\"duration\":\"7 DAYS\",\"sig\":\"TAKE AFTER MEALS\"}],\"notes\":\"\"},\"med_cert\":{\"leave_from\":\"\",\"leave_to\":\"\",\"fitness\":\"Unfit for work\\/school duties (Strict Bed Rest)\",\"remarks\":\"\",\"notes\":\"\"},\"lab_request\":{\"priority\":\"Routine\",\"tests\":[],\"indication\":\"\",\"notes\":\"\"}}', 'Telecare\nTelecare\nContact No.: 09923139504\n\nELECTRONIC MEDICAL PRESCRIPTION\n\nPatient Name: Jerson Uanan Sagun\nAge / Gender: 21 / Male\nPatient ID: #PT-0008\nDate: September 18, 2026 - 5:09 PM\nClinical Impression / Diagnosis: EXAMPLE\n\nRx\n1. BIOGESIC   Disp: 2 TABS\n   Sig: TAKE AFTER MEALS (DRINK 1 EVERY 6 HOURS — 7 DAYS)\n\nEXAMPLE DOCTOR#1, MD\nLicense No.: 0148920\nPTR No.: 8841209', '2026-09-18 07:13:17'),
(3, 4, 8, 4, 'med_cert', 'TC-MC-2026-0004-0E31', 'uploads/generated_documents/document_4_med_cert_20260919_012123.pdf', '{\"diagnosis\":\"MED CERT\",\"prescription\":{\"drugs\":[{\"name\":\"\",\"dispense\":\"\",\"freq\":\"\",\"duration\":\"\",\"sig\":\"\"}],\"notes\":\"\"},\"med_cert\":{\"leave_from\":\"2026-09-03\",\"leave_to\":\"2026-09-24\",\"fitness\":\"Fit to return to work\\/school\",\"remarks\":\"\",\"notes\":\"\"},\"lab_request\":{\"priority\":\"Routine\",\"tests\":[],\"indication\":\"\",\"notes\":\"\"}}', 'TELE-CARE MEDICAL NETWORK\nCaloocan City, Metro Manila\nContact No.: 09923139504\n\nOFFICIAL MEDICAL CERTIFICATE\n\nPatient Name: Jerson Uanan Sagun\nAge / Gender: 21 / Male\nPatient ID: #PT-0008\nDate: September 18, 2026 - 5:09 PM\nClinical Impression / Diagnosis: MED CERT\n\nTO WHOM IT MAY CONCERN\n\nThis is to certify that Jerson Uanan Sagun, 21 years of age, was clinically examined and treated via TELE-CARE secure teleconsultation on September 18, 2026 - 5:09 PM under my medical care.\nDiagnostic Evaluation: MED CERT\nClinical Fitness Determination: Fit to return to work/school\nRecommended Rest Period: 2026-09-03 to 2026-09-24\n\nEXAMPLE DOCTOR#1, MD\nLicense No.: 0148920\nThis document can be used for non-medico-legal purposes only.', '2026-09-18 10:21:23');

-- --------------------------------------------------------

--
-- Table structure for table `lab_results`
--

CREATE TABLE `lab_results` (
  `id` int(11) NOT NULL,
  `patient_id` int(11) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `doc_type` enum('lab_result','prescription','lab_request','med_cert','unknown') DEFAULT 'unknown',
  `doc_label` varchar(200) DEFAULT NULL,
  `extracted_text` longtext DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_results`
--

INSERT INTO `lab_results` (`id`, `patient_id`, `file_path`, `doc_type`, `doc_label`, `extracted_text`, `uploaded_at`) VALUES
(1, 1, 'uploads/ocr/ocr_69aee8a9b1de2.pdf', 'prescription', 'Prescription 1', '--- Page 1 ---\nTELE-CARE Medical Clinic\n123 Mabini Street, Caloocan City, Metro Manila | Tel: (02) 8123-4567\ntelecaremedical@telecare.com\n\nPATIENT NAME: John Noel Orano DATE: March 9, 2026\nAGE / SEX: 22 years old / Male PATIENT ID: TC-2026-0042\nADDRESS: NPC Kanan Makisig St., Brgy. 171, Caloocan City\nPHONE: 09923139504\n1. Amoxicillin 500mg Capsule\n\nSig: Take 1 capsule every 8 hours for 7 days\n\nDispense: 21 capsules | Refills: 0\n2. Metformin 500mg Tablet\n\nSig: Take 1 tablet twice daily with meals (morning and evening)\n\nDispense: 60 tablets | Refills: 2\n3. Losartan Potassium 50mg Tablet\n\nSig: Take 1 tablet once daily in the morning\n\nDispense: 30 tablets | Refills: 3\nNOTES:\nPatient advised to take medications with food. Avoid alcohol while on Amoxicillin. Monitor blood sugar levels daily. Return for\nfollow-up after 2 weeks or immediately if symptoms worsen.\n\nDr. Maria Santos, MD\nCardiologist | PRC Lic. No. 0123456\nSt. Luke Medical Wellness Center\nThis prescription is valid for 30 days from the date of issue. | TELE-CARE © 2026', '2026-03-09 15:35:10'),
(2, 5, 'uploads/ocr/ocr_69b66f98ab067.pdf', 'prescription', 'Prescription 1', '--- Page 1 ---\n321 Quezon Avenue, Quezon City, Metro Manila | Tel: (02) 8321-9876\ninfo@excellcare.com | www.excellcare.com\nPATIENT NAME: ~— Bernard Dela Cruz Santos DATE: March 15, 2026\nAGE / SEX: 34 years old / Male PATIENT == TC-2026-0155\nID:\nDIAGNOSIS: Upper Respiratory Tract Infection, PHONE: 09561234567\nAsthma\nALLERGY: Sulfonamides (avoid)\nRx\n1. Azithromycin 500mg Tablet\nSig: Take 1 tablet once daily for 5 days\nDispense: 5 tablets | Refills: 0\nWARNING: Complete the full course even if feeling better. Avoid antacids within 2 hours of taking.\n2. Salbutamol 2mg Tablet\nSig: Take 1 tablet three times daily (morning, afternoon, and evening)\nDispense: 30 tablets | Refills: 2\nWARNING: May cause tremors or increased heart rate. Do not exceed prescribed dose. Avoid caffeine.\n3. Montelukast 10mg Tablet\nSig: Take 1 tablet once daily at bedtime\nDispense: 30 tablets | Refills: 3\nWARNING: Report any changes in mood or behavior immediately. Do not stop without consulting your doctor.\n4. Cetirizine 10mg Tablet\nSig: Take 1 tablet once daily at night\nDispense: 14 tablets | Refills: 1\nWARNING: May cause drowsiness. Avoid driving or operating machinery after taking. Avoid alcohol.\n5. Paracetamol 500mg Tablet\nSig: Take 1-2 tablets every 4 to 6 hours as needed for fever or pain. Do not exceed 8 tablets per day.\nDispense: 20 tablets | Refills: 0\nWARNING: Do not take with other paracetamol-containing products. Avoid alcohol. Urgent — stop\nimmediately if yellowing of skin or eyes occurs.\nNOTES:\nPatient advised to rest and increase fluid intake. Steam inhalation 2-3 times daily recommended. Avoid\ncold drinks and air-conditioned environments. Use Salbutamol as rescue medication during acute asthma\nattack. Return immediately if dysonea worsens or fever persists beyond 3 days. Follow-up in 1 week.\nDr. Ma. Aberlee Lacanaria\n\n--- Page 2 ---\nGeneral Practitioner | PRC Lic. No. 0312456\nEXCELLCARE MEDICAL SYSTEM INC.\nThis prescription is valid for 30 days from the date of issue. | EXCELLCARE © 2026', '2026-03-15 08:36:48'),
(7, 1, 'uploads/generated_documents/document_11_prescription_20260909_013440.pdf', 'prescription', 'Prescription - Dr. EXAMPLE DOCTOR#1', 'Rx [MEDICATION NAME / STRENGTH / FORM]\r\n[INSTRUCTIONS / DOSAGE]\r\nDispense: [QUANTITY / VOLUME]\r\nLabel: [DIRECTIONS FOR USE]\r\n[ADDITIONAL INSTRUCTIONS]\r\n\r\nClinical note based on consultation summary:\r\nNot discussed.', '2026-09-08 17:34:40'),
(8, 1, 'uploads/generated_documents/document_11_prescription_20260909_014052.pdf', 'prescription', 'Prescription - Dr. EXAMPLE DOCTOR#1', 'Rx [MEDICATION NAME / STRENGTH / FORM]\r\n[INSTRUCTIONS / DOSAGE]\r\nDispense: [QUANTITY / VOLUME]\r\nLabel: [DIRECTIONS FOR USE]\r\n[ADDITIONAL INSTRUCTIONS]\r\n\r\nClinical note based on consultation summary:\r\nNot discussed.', '2026-09-08 17:40:52'),
(9, 8, 'uploads/generated_documents/document_4_prescription_20260918_215343.pdf', 'prescription', 'Prescription - Dr. EXAMPLE DOCTOR#1', 'Telecare\nTelecare\nContact No.: 09923139504\n\nELECTRONIC MEDICAL PRESCRIPTION\n\nPatient Name: Jerson Uanan Sagun\nAge / Gender: 21 / \nPatient ID: #PT-0008\nDate: September 18, 2026 - 5:09 PM\n\nRx\n1. Biogesic   Disp: 14\n   Sig: Take after meals (1 every hours — 7 days)\n\nEXAMPLE DOCTOR#1, MD\nLicense No.: 0148920\nPTR No.: 8841209', '2026-09-18 13:53:43'),
(10, 8, 'uploads/generated_documents/document_4_prescription_20260918_221317.pdf', 'prescription', 'Prescription - Dr. EXAMPLE DOCTOR#1', 'Telecare\nTelecare\nContact No.: 09923139504\n\nELECTRONIC MEDICAL PRESCRIPTION\n\nPatient Name: Jerson Uanan Sagun\nAge / Gender: 21 / Male\nPatient ID: #PT-0008\nDate: September 18, 2026 - 5:09 PM\nClinical Impression / Diagnosis: EXAMPLE\n\nRx\n1. BIOGESIC   Disp: 2 TABS\n   Sig: TAKE AFTER MEALS (DRINK 1 EVERY 6 HOURS — 7 DAYS)\n\nEXAMPLE DOCTOR#1, MD\nLicense No.: 0148920\nPTR No.: 8841209', '2026-09-18 14:13:17'),
(11, 1, 'uploads/ocr/ocr_6aad6a662dac8.pdf', 'unknown', '', '', '2026-09-18 16:44:24'),
(12, 1, 'uploads/ocr/ocr_6aad6a78597c5.pdf', 'lab_result', '', '--- Page 1 ---\nTELE-CARE Medical Laboratory	\r\n123 Mabini Street, Caloocan City, Metro Manila | Tel: (02) 8123-4567	\r\nlab @telecare.com | Licensed Clinical Laboratory	\r\nPATIENT NAME:	John Noel Orano	DATE COLLECTED:	March 9, 2026 9:00	\r\nAM	\r\nAGE / SEX:	22 years old / Male	DATE RELEASED:	March 9, 2026 2:00	\r\nPM	\r\nPATIENT ID:	TC-2026-0042	REQUESTING	Dr. Maria Santos	\r\nPHYSICIAN:	\r\nSPECIMEN:	Whole Blood / Serum	LAB REF NO:	LAB-2026-03-0991	\r\nCOMPLETE BLOOD COUNT (CBC)	\r\nTEST	RESULT	UNIT	NORMAL RANGE	FLAG	\r\nHemoglobin	14.2	g/dL	13.5 - 17.5	\r\nHematocrit	42.1	%	41.0 - 53.0	\r\nRBC Count	4.85	10^6/uL	4.50 - 5.90	\r\nWBC Count	11.8	10^3/uL	4.5 - 11.0	HIGH	\r\nPlatelet Count	215	10^3/uL	150 - 400	\r\nNeutrophils	78	%	50 - 70	HIGH	\r\nLymphocytes	16	%	20 - 40	LOW	\r\nMonocytes	6	2 - 8	\r\nBLOOD CHEMISTRY	\r\nTEST	RESULT	UNIT	NORMAL RANGE	FLAG	\r\nFasting Blood Glucose	98	mg/dL	70 - 100	\r\nTotal Cholesterol	215	mg/dL	<200	HIGH	\r\nLDL Cholesterol	138	mg/dL	<130	HIGH	\r\nHDL Cholesterol	38	mg/dL	> 40	LOW	\r\nTriglycerides	178	mg/dL	< 150	HIGH	\r\nCreatinine	0.92	mg/dL	0.70 - 1.30	\r\nUric Acid	7.8	mg/dL	3.5 - 7.2	HIGH\n\n--- Page 2 ---\nSodium	139	mEg/L	136 - 145	\r\nPotassium	3.3	mEq/L	3.5 - 5.0	LOW	\r\nREMARKS: Elevated WBC and Neutrophils may indicate infection or inflammation. Lipid panel is abnormal - patient	\r\nadvised to follow low-fat diet and increase physical activity. Uric Acid is high - limit red meat and seafood intake.	\r\nPotassium is low - recommend dietary supplementation. Please correlate with clinical findings.	\r\nMedical Technologist: Ana Reyes, RMT	\r\nLicense No. MT-0045678 | TELE-CARE Medical Laboratory	\r\nThis result is confidential and intended solely for the named patient and requesting physician. I TELE-CARE © 2026', '2026-09-18 16:44:42'),
(13, 8, 'uploads/generated_documents/document_4_med_cert_20260919_012123.pdf', 'med_cert', 'Medical Certificate - Dr. EXAMPLE DOCTOR#1', 'TELE-CARE MEDICAL NETWORK\nCaloocan City, Metro Manila\nContact No.: 09923139504\n\nOFFICIAL MEDICAL CERTIFICATE\n\nPatient Name: Jerson Uanan Sagun\nAge / Gender: 21 / Male\nPatient ID: #PT-0008\nDate: September 18, 2026 - 5:09 PM\nClinical Impression / Diagnosis: MED CERT\n\nTO WHOM IT MAY CONCERN\n\nThis is to certify that Jerson Uanan Sagun, 21 years of age, was clinically examined and treated via TELE-CARE secure teleconsultation on September 18, 2026 - 5:09 PM under my medical care.\nDiagnostic Evaluation: MED CERT\nClinical Fitness Determination: Fit to return to work/school\nRecommended Rest Period: 2026-09-03 to 2026-09-24\n\nEXAMPLE DOCTOR#1, MD\nLicense No.: 0148920\nThis document can be used for non-medico-legal purposes only.', '2026-09-18 17:21:23');

-- --------------------------------------------------------

--
-- Table structure for table `legal_policies`
--

CREATE TABLE `legal_policies` (
  `id` int(11) NOT NULL,
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `legal_policies`
--

INSERT INTO `legal_policies` (`id`, `slug`, `title`, `type`, `short_desc`, `content`, `version`, `status`, `applicable_to`, `effective_date`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'data-privacy-notice', 'Data Privacy Notice', 'Data & Privacy', 'Guidelines for data collection, usage, and protection.', '<h4>1. Who We Are</h4>\r\n<p>This Data Privacy Notice is issued by TELE-CARE (\"we,\" \"us,\" or \"our\") for our telemedicine platform, in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and its Implementing Rules and Regulations.</p>\r\n\r\n<h4>2. Personal Data We Collect</h4>\r\n<p>When you register and use TELE-CARE, we collect:</p>\r\n<ul>\r\n  <li>Identity information — full name, date of birth, contact number, email address</li>\r\n  <li>Account credentials — password (stored in encrypted/hashed form)</li>\r\n  <li>Health information — medical history, symptoms, consultation notes, prescriptions, lab results, and other information you or your attending doctor provide during a teleconsultation</li>\r\n  <li>Technical data — device information, IP address, browser type, and log data related to your use of the platform</li>\r\n  <li>Communications — messages, video/audio session metadata, and files exchanged with healthcare providers through the platform</li>\r\n</ul>\r\n\r\n<h4>3. Why We Collect Your Data</h4>\r\n<p>Your personal and health data are collected and processed to:</p>\r\n<ul>\r\n  <li>Create and manage your patient account</li>\r\n  <li>Facilitate teleconsultations between you and licensed healthcare providers</li>\r\n  <li>Maintain accurate medical and consultation records</li>\r\n  <li>Send appointment confirmations, reminders, and account-related notifications</li>\r\n  <li>Process payments for consultations, where applicable</li>\r\n  <li>Comply with legal, regulatory, and reporting obligations</li>\r\n  <li>Improve the safety, security, and functionality of the platform</li>\r\n</ul>\r\n\r\n<h4>4. Sensitive Personal Information</h4>\r\n<p>Health-related data is classified as \"sensitive personal information\" under RA 10173. We only process this data with your explicit consent, and access is restricted to your attending healthcare provider, authorized clinic/administrative staff directly involved in your care, and personnel required by law or regulation.</p>\r\n\r\n<h4>5. Data Sharing and Disclosure</h4>\r\n<p>We do not sell your personal data. Your information may only be shared with:</p>\r\n<ul>\r\n  <li>Licensed doctors and staff directly involved in your consultation</li>\r\n  <li>Service providers who support platform operations (e.g., email delivery, secure hosting) under confidentiality obligations</li>\r\n  <li>Government agencies or regulators, when required by law, court order, or public health reporting requirements</li>\r\n</ul>\r\n\r\n<h4>6. Data Retention</h4>\r\n<p>Your personal and medical records are retained for as long as your account is active and for a period thereafter as required by applicable healthcare recordkeeping laws and regulations, after which the data will be securely disposed of or anonymized.</p>\r\n\r\n<h4>7. Your Rights</h4>\r\n<p>Under the Data Privacy Act, you have the right to be informed, to access, to object, to correct, to erase or block your data (subject to legal retention requirements), to data portability, and to file a complaint with the National Privacy Commission. To exercise these rights, contact us through the channels provided on your patient dashboard.</p>\r\n\r\n<h4>8. Security Measures</h4>\r\n<p>We apply organizational, physical, and technical safeguards — including password hashing, encrypted connections, and access controls — to protect your data against unauthorized access, alteration, disclosure, or destruction.</p>\r\n\r\n<h4>9. Consent</h4>\r\n<p>By creating a TELE-CARE account, you acknowledge that you have read and understood this Data Privacy Notice and consent to the collection, use, and processing of your personal and sensitive personal information as described above.</p>', 'v1.4', 'Published', 'all', '2026-09-07', 'Super Admin', '2026-09-07 10:30:07', '2026-09-07 11:23:47'),
(2, 'privacy-policy', 'Privacy Policy', 'Data & Privacy', 'Details on how user information is collected, used, and secured.', '<h4>1. Overview</h4>\r\n<p>This Privacy Policy explains how TELE-CARE handles information collected through our telemedicine platform, in addition to the specific commitments made in our Data Privacy Notice.&nbsp;</p>\r\n\r\n<h4>2. Information We Collect Automatically</h4>\r\n<p>When you use TELE-CARE, our systems may automatically collect device type, browser, IP address, session timestamps, and general usage patterns (e.g., pages visited, features used) to help us maintain and improve the platform.</p>\r\n\r\n<h4>3. Cookies and Similar Technologies</h4>\r\n<p>TELE-CARE may use cookies or similar technologies to keep you logged in, remember your preferences, and understand how the platform is used. You can control cookies through your browser settings, though disabling them may affect platform functionality.</p>\r\n\r\n<h4>4. How We Use Information</h4>\r\n<ul>\r\n  <li>To operate, maintain, and secure the platform</li>\r\n  <li>To personalize your experience (e.g., pre-filling known details, showing relevant appointment information)</li>\r\n  <li>To detect, investigate, and prevent fraudulent or unauthorized activity</li>\r\n  <li>To analyze aggregate usage trends for service improvement, using de-identified data where possible</li>\r\n</ul>\r\n\r\n<h4>5. Third-Party Services</h4>\r\n<p>TELE-CARE relies on trusted third-party providers for functions such as email delivery and secure video communication. These providers process data only as necessary to perform their function and are contractually or technically restricted from using your data for unrelated purposes.</p>\r\n\r\n<h4>6. Data Storage and International Transfers</h4>\r\n<p>Your data is stored on secure servers. Where any data is processed or stored outside the Philippines by a service provider, we take reasonable steps to ensure it remains protected to a standard consistent with the Data Privacy Act.</p>\r\n\r\n<h4>7. Children\'s Privacy</h4>\r\n<p>TELE-CARE is intended for users 18 years of age and older. We do not knowingly collect personal data from minors through direct patient registration.</p>\r\n\r\n<h4>8. Updates to This Policy</h4>\r\n<p>We may revise this Privacy Policy periodically. Material changes will be communicated through the platform or via email prior to taking effect.</p>\r\n\r\n<h4>9. Contact Us</h4>\r\n<p>For questions, concerns, or requests relating to this Privacy Policy or your personal data, please reach out through the support channels available on your TELE-CARE patient dashboard.</p>', 'v1.5', 'Published', 'all', '2026-09-07', 'Super Admin', '2026-09-07 10:30:07', '2026-09-09 13:00:05'),
(3, 'terms-and-conditions', 'Terms of Use and Agreement', 'Terms & Agreements', 'Rules and conditions for using the platform.', '<h4>1. Acceptance of Terms</h4>\n<p>These Terms and Conditions (&quot;Terms&quot;) govern your access to and use of TELE-CARE, operated by TELE-CARE. By creating an account, you agree to be bound by these Terms. If you do not agree, do not use the platform.</p>\n\n<h4>2. Eligibility</h4>\n<p>You must be at least 18 years old to register a patient account. By registering, you represent that the information you provide is accurate, current, and complete, and that you will keep it updated.</p>\n\n<h4>3. Nature of the Service</h4>\n<p>TELE-CARE connects patients with licensed healthcare providers for remote consultations. TELE-CARE is a technology platform and does not itself practice medicine. Medical advice, diagnosis, and treatment are provided solely by the licensed healthcare professionals you consult through the platform.</p>\n\n<h4>4. Not for Emergency Use</h4>\n<p>TELE-CARE is not intended for medical emergencies. If you are experiencing a medical emergency, call your local emergency hotline or go to the nearest emergency room immediately. Do not rely on this platform for time-critical or life-threatening conditions.</p>\n\n<h4>5. Account Responsibilities</h4>\n<ul>\n  <li>You are responsible for maintaining the confidentiality of your login credentials</li>\n  <li>You are responsible for all activity that occurs under your account</li>\n  <li>You must notify us immediately of any unauthorized use of your account</li>\n  <li>Providing false medical or personal information may result in suspension or deactivation of your account</li>\n</ul>\n\n<h4>6. Consultations and Payments</h4>\n<p>Consultation fees, where applicable, will be disclosed prior to booking. Payment terms, cancellation policies, and refund conditions may be presented separately at the time of booking and form part of these Terms by reference.</p>\n\n<h4>7. Prohibited Conduct</h4>\n<p>You agree not to misuse the platform, including but not limited to: impersonating another person, attempting to access another user&#39;s account or medical records, uploading harmful code, or using the platform for any unlawful purpose.</p>\n\n<h4>8. Account Suspension and Termination</h4>\n<p>TELE-CARE reserves the right to suspend or deactivate accounts that violate these Terms, provide fraudulent information, or misuse the platform, with or without prior notice where warranted.</p>\n\n<h4>9. Limitation of Liability</h4>\n<p>To the extent permitted by law, TELE-CARE shall not be liable for indirect, incidental, or consequential damages arising from your use of the platform, technical interruptions, or reliance on information exchanged during a teleconsultation, except as required by applicable law.</p>\n\n<h4>10. Changes to These Terms</h4>\n<p>We may update these Terms from time to time. Continued use of TELE-CARE after changes are posted constitutes acceptance of the revised Terms.</p>\n\n<h4>11. Governing Law</h4>\n<p>These Terms are governed by the laws of the Republic of the Philippines.</p>', 'v1.1', 'Published', 'all', '2026-09-07', 'Super Admin', '2026-09-07 10:30:07', '2026-09-07 10:30:07'),
(4, 'payment-policy', 'Payment Policy', 'Payments', 'Policies for payments, refunds, and transactions.', '<h3>Overview</h3>\n<p>TELE-CARE is a telehealth platform that connects you with licensed doctors for remote consultations. By using our service, you acknowledge this Payment &amp; Data Handling Policy.</p>\n\n<h3>Information We Collect</h3>\n<ul>\n  <li><strong>Account:</strong> Full name, email, phone, date of birth</li>\n  <li><strong>Medical:</strong> Health records, consultation notes, diagnoses</li>\n  <li><strong>Payment:</strong> Billing details only&mdash;card details are NOT stored by us</li>\n  <li><strong>Communications:</strong> Chat messages, voice and video recordings during consultations</li>\n</ul>\n\n<h3>Voice &amp; Video Recording</h3>\n<div class=\"highlight\">\n  <strong>You consent to voice and video recording during consultations for:</strong>\n  <ul style=\"margin-top:0.6rem;\">\n    <li>Medical record keeping and continuity of care</li>\n    <li>Quality assurance and compliance verification</li>\n    <li>Patient and provider safety</li>\n    <li>AI Summarization of the teleconsultations</li>\n  </ul>\n</div>\n<p style=\"font-size:0.88rem;margin-top:0.8rem;\">Recordings are encrypted and stored securely. Contact support to access your recording.</p>\n\n<h3>Payment Security</h3>\n<p><strong>We use PayMongo for all payments.</strong> Your card details are transmitted directly to PayMongo and are NOT stored on TELE-CARE servers. We only store billing name, email, and payment status for records.</p>\n<p style=\"font-size:0.88rem;\"><em>PayMongo is PCI DSS compliant and handles all sensitive payment data securely.</em></p>\n\n<h3>How We Use Your Information</h3>\n<ul>\n  <li>To provide telehealth services and schedule appointments</li>\n  <li>To process payments securely through PayMongo</li>\n  <li>To maintain medical records and consultation history</li>\n  <li>To send appointment reminders and follow-ups</li>\n  <li>To improve platform security and performance</li>\n  <li>To comply with legal requirements</li>\n</ul>\n\n<h3>Who Can Access Your Information</h3>\n<ul>\n  <li><strong>Your Doctor:</strong> Full access to medical records for treatment only</li>\n  <li><strong>Support Team:</strong> Limited access to billing and account info</li>\n  <li><strong>PayMongo:</strong> Billing details for payment processing only</li>\n  <li><strong>Legal:</strong> Disclosure only as required by law</li>\n</ul>\n<p style=\"font-size:0.88rem;margin-top:0.8rem;\"><em>Your consultation recordings and medical records are NOT shared with third parties without your consent.</em></p>\n\n<h3>Data Security</h3>\n<ul>\n  <li>All data transmitted via TLS/SSL encryption</li>\n  <li>Medical records stored on secure servers with access controls</li>\n  <li>Payment processing delegated to certified PayMongo</li>\n  <li>Consultation sessions require authentication</li>\n</ul>\n\n<h3>Your Rights</h3>\n<ul>\n  <li>Request access to your personal data and medical records</li>\n  <li>Request correction of inaccurate information</li>\n  <li>Request the clinic for deletion of your account at any time</li>\n</ul>\n\n<h3>Contact</h3>\n<p>For questions about this Payment &amp; Data Handling Policy or your data:<br/><strong>telecareteamsystem@gmail.com</strong></p>', 'v1.0', 'Published', 'all', '2026-09-07', 'Super Admin', '2026-09-07 10:30:07', '2026-09-07 10:30:07');

-- --------------------------------------------------------

--
-- Table structure for table `legal_policy_versions`
--

CREATE TABLE `legal_policy_versions` (
  `id` int(11) NOT NULL,
  `policy_id` int(11) NOT NULL,
  `version` varchar(20) NOT NULL,
  `content` longtext NOT NULL,
  `status` varchar(20) NOT NULL,
  `revision_notes` text DEFAULT NULL,
  `updated_by` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `legal_policy_versions`
--

INSERT INTO `legal_policy_versions` (`id`, `policy_id`, `version`, `content`, `status`, `revision_notes`, `updated_by`, `created_at`) VALUES
(1, 1, 'v1.2', '<h4>1. Who We Are</h4>\n<p>This Data Privacy Notice is issued by TELE-CARE (&quot;we,&quot; &quot;us,&quot; or &quot;our&quot;) for our telemedicine platform, in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and its Implementing Rules and Regulations.</p>\n\n<h4>2. Personal Data We Collect</h4>\n<p>When you register and use TELE-CARE, we collect:</p>\n<ul>\n  <li>Identity information &mdash; full name, date of birth, contact number, email address</li>\n  <li>Account credentials &mdash; password (stored in encrypted/hashed form)</li>\n  <li>Health information &mdash; medical history, symptoms, consultation notes, prescriptions, lab results, and other information you or your attending doctor provide during a teleconsultation</li>\n  <li>Technical data &mdash; device information, IP address, browser type, and log data related to your use of the platform</li>\n  <li>Communications &mdash; messages, video/audio session metadata, and files exchanged with healthcare providers through the platform</li>\n</ul>\n\n<h4>3. Why We Collect Your Data</h4>\n<p>Your personal and health data are collected and processed to:</p>\n<ul>\n  <li>Create and manage your patient account</li>\n  <li>Facilitate teleconsultations between you and licensed healthcare providers</li>\n  <li>Maintain accurate medical and consultation records</li>\n  <li>Send appointment confirmations, reminders, and account-related notifications</li>\n  <li>Process payments for consultations, where applicable</li>\n  <li>Comply with legal, regulatory, and reporting obligations</li>\n  <li>Improve the safety, security, and functionality of the platform</li>\n</ul>\n\n<h4>4. Sensitive Personal Information</h4>\n<p>Health-related data is classified as &quot;sensitive personal information&quot; under RA 10173. We only process this data with your explicit consent, and access is restricted to your attending healthcare provider, authorized clinic/administrative staff directly involved in your care, and personnel required by law or regulation.</p>\n\n<h4>5. Data Sharing and Disclosure</h4>\n<p>We do not sell your personal data. Your information may only be shared with:</p>\n<ul>\n  <li>Licensed doctors and staff directly involved in your consultation</li>\n  <li>Service providers who support platform operations (e.g., email delivery, secure hosting) under confidentiality obligations</li>\n  <li>Government agencies or regulators, when required by law, court order, or public health reporting requirements</li>\n</ul>\n\n<h4>6. Data Retention</h4>\n<p>Your personal and medical records are retained for as long as your account is active and for a period thereafter as required by applicable healthcare recordkeeping laws and regulations, after which the data will be securely disposed of or anonymized.</p>\n\n<h4>7. Your Rights</h4>\n<p>Under the Data Privacy Act, you have the right to be informed, to access, to object, to correct, to erase or block your data (subject to legal retention requirements), to data portability, and to file a complaint with the National Privacy Commission. To exercise these rights, contact us through the channels provided on your patient dashboard.</p>\n\n<h4>8. Security Measures</h4>\n<p>We apply organizational, physical, and technical safeguards &mdash; including password hashing, encrypted connections, and access controls &mdash; to protect your data against unauthorized access, alteration, disclosure, or destruction.</p>\n\n<h4>9. Consent</h4>\n<p>By creating a TELE-CARE account, you acknowledge that you have read and understood this Data Privacy Notice and consent to the collection, use, and processing of your personal and sensitive personal information as described above.</p>', 'Published', 'Initial migration from hardcoded page content.', 'Super Admin', '2026-09-07 10:30:07'),
(2, 2, 'v1.3', '<h4>1. Overview</h4>\n<p>This Privacy Policy explains how TELE-CARE handles information collected through our telemedicine platform, in addition to the specific commitments made in our Data Privacy Notice.</p>\n\n<h4>2. Information We Collect Automatically</h4>\n<p>When you use TELE-CARE, our systems may automatically collect device type, browser, IP address, session timestamps, and general usage patterns (e.g., pages visited, features used) to help us maintain and improve the platform.</p>\n\n<h4>3. Cookies and Similar Technologies</h4>\n<p>TELE-CARE may use cookies or similar technologies to keep you logged in, remember your preferences, and understand how the platform is used. You can control cookies through your browser settings, though disabling them may affect platform functionality.</p>\n\n<h4>4. How We Use Information</h4>\n<ul>\n  <li>To operate, maintain, and secure the platform</li>\n  <li>To personalize your experience (e.g., pre-filling known details, showing relevant appointment information)</li>\n  <li>To detect, investigate, and prevent fraudulent or unauthorized activity</li>\n  <li>To analyze aggregate usage trends for service improvement, using de-identified data where possible</li>\n</ul>\n\n<h4>5. Third-Party Services</h4>\n<p>TELE-CARE relies on trusted third-party providers for functions such as email delivery and secure video communication. These providers process data only as necessary to perform their function and are contractually or technically restricted from using your data for unrelated purposes.</p>\n\n<h4>6. Data Storage and International Transfers</h4>\n<p>Your data is stored on secure servers. Where any data is processed or stored outside the Philippines by a service provider, we take reasonable steps to ensure it remains protected to a standard consistent with the Data Privacy Act.</p>\n\n<h4>7. Children&#39;s Privacy</h4>\n<p>TELE-CARE is intended for users 18 years of age and older. We do not knowingly collect personal data from minors through direct patient registration.</p>\n\n<h4>8. Updates to This Policy</h4>\n<p>We may revise this Privacy Policy periodically. Material changes will be communicated through the platform or via email prior to taking effect.</p>\n\n<h4>9. Contact Us</h4>\n<p>For questions, concerns, or requests relating to this Privacy Policy or your personal data, please reach out through the support channels available on your TELE-CARE patient dashboard.</p>', 'Published', 'Initial migration from hardcoded page content.', 'Super Admin', '2026-09-07 10:30:07'),
(3, 3, 'v1.1', '<h4>1. Acceptance of Terms</h4>\n<p>These Terms and Conditions (&quot;Terms&quot;) govern your access to and use of TELE-CARE, operated by TELE-CARE. By creating an account, you agree to be bound by these Terms. If you do not agree, do not use the platform.</p>\n\n<h4>2. Eligibility</h4>\n<p>You must be at least 18 years old to register a patient account. By registering, you represent that the information you provide is accurate, current, and complete, and that you will keep it updated.</p>\n\n<h4>3. Nature of the Service</h4>\n<p>TELE-CARE connects patients with licensed healthcare providers for remote consultations. TELE-CARE is a technology platform and does not itself practice medicine. Medical advice, diagnosis, and treatment are provided solely by the licensed healthcare professionals you consult through the platform.</p>\n\n<h4>4. Not for Emergency Use</h4>\n<p>TELE-CARE is not intended for medical emergencies. If you are experiencing a medical emergency, call your local emergency hotline or go to the nearest emergency room immediately. Do not rely on this platform for time-critical or life-threatening conditions.</p>\n\n<h4>5. Account Responsibilities</h4>\n<ul>\n  <li>You are responsible for maintaining the confidentiality of your login credentials</li>\n  <li>You are responsible for all activity that occurs under your account</li>\n  <li>You must notify us immediately of any unauthorized use of your account</li>\n  <li>Providing false medical or personal information may result in suspension or deactivation of your account</li>\n</ul>\n\n<h4>6. Consultations and Payments</h4>\n<p>Consultation fees, where applicable, will be disclosed prior to booking. Payment terms, cancellation policies, and refund conditions may be presented separately at the time of booking and form part of these Terms by reference.</p>\n\n<h4>7. Prohibited Conduct</h4>\n<p>You agree not to misuse the platform, including but not limited to: impersonating another person, attempting to access another user&#39;s account or medical records, uploading harmful code, or using the platform for any unlawful purpose.</p>\n\n<h4>8. Account Suspension and Termination</h4>\n<p>TELE-CARE reserves the right to suspend or deactivate accounts that violate these Terms, provide fraudulent information, or misuse the platform, with or without prior notice where warranted.</p>\n\n<h4>9. Limitation of Liability</h4>\n<p>To the extent permitted by law, TELE-CARE shall not be liable for indirect, incidental, or consequential damages arising from your use of the platform, technical interruptions, or reliance on information exchanged during a teleconsultation, except as required by applicable law.</p>\n\n<h4>10. Changes to These Terms</h4>\n<p>We may update these Terms from time to time. Continued use of TELE-CARE after changes are posted constitutes acceptance of the revised Terms.</p>\n\n<h4>11. Governing Law</h4>\n<p>These Terms are governed by the laws of the Republic of the Philippines.</p>', 'Published', 'Initial migration from hardcoded page content.', 'Super Admin', '2026-09-07 10:30:07'),
(4, 4, 'v1.0', '<h3>Overview</h3>\n<p>TELE-CARE is a telehealth platform that connects you with licensed doctors for remote consultations. By using our service, you acknowledge this Payment &amp; Data Handling Policy.</p>\n\n<h3>Information We Collect</h3>\n<ul>\n  <li><strong>Account:</strong> Full name, email, phone, date of birth</li>\n  <li><strong>Medical:</strong> Health records, consultation notes, diagnoses</li>\n  <li><strong>Payment:</strong> Billing details only&mdash;card details are NOT stored by us</li>\n  <li><strong>Communications:</strong> Chat messages, voice and video recordings during consultations</li>\n</ul>\n\n<h3>Voice &amp; Video Recording</h3>\n<div class=\"highlight\">\n  <strong>You consent to voice and video recording during consultations for:</strong>\n  <ul style=\"margin-top:0.6rem;\">\n    <li>Medical record keeping and continuity of care</li>\n    <li>Quality assurance and compliance verification</li>\n    <li>Patient and provider safety</li>\n    <li>AI Summarization of the teleconsultations</li>\n  </ul>\n</div>\n<p style=\"font-size:0.88rem;margin-top:0.8rem;\">Recordings are encrypted and stored securely. Contact support to access your recording.</p>\n\n<h3>Payment Security</h3>\n<p><strong>We use PayMongo for all payments.</strong> Your card details are transmitted directly to PayMongo and are NOT stored on TELE-CARE servers. We only store billing name, email, and payment status for records.</p>\n<p style=\"font-size:0.88rem;\"><em>PayMongo is PCI DSS compliant and handles all sensitive payment data securely.</em></p>\n\n<h3>How We Use Your Information</h3>\n<ul>\n  <li>To provide telehealth services and schedule appointments</li>\n  <li>To process payments securely through PayMongo</li>\n  <li>To maintain medical records and consultation history</li>\n  <li>To send appointment reminders and follow-ups</li>\n  <li>To improve platform security and performance</li>\n  <li>To comply with legal requirements</li>\n</ul>\n\n<h3>Who Can Access Your Information</h3>\n<ul>\n  <li><strong>Your Doctor:</strong> Full access to medical records for treatment only</li>\n  <li><strong>Support Team:</strong> Limited access to billing and account info</li>\n  <li><strong>PayMongo:</strong> Billing details for payment processing only</li>\n  <li><strong>Legal:</strong> Disclosure only as required by law</li>\n</ul>\n<p style=\"font-size:0.88rem;margin-top:0.8rem;\"><em>Your consultation recordings and medical records are NOT shared with third parties without your consent.</em></p>\n\n<h3>Data Security</h3>\n<ul>\n  <li>All data transmitted via TLS/SSL encryption</li>\n  <li>Medical records stored on secure servers with access controls</li>\n  <li>Payment processing delegated to certified PayMongo</li>\n  <li>Consultation sessions require authentication</li>\n</ul>\n\n<h3>Your Rights</h3>\n<ul>\n  <li>Request access to your personal data and medical records</li>\n  <li>Request correction of inaccurate information</li>\n  <li>Request the clinic for deletion of your account at any time</li>\n</ul>\n\n<h3>Contact</h3>\n<p>For questions about this Payment &amp; Data Handling Policy or your data:<br/><strong>telecareteamsystem@gmail.com</strong></p>', 'Published', 'Initial migration from hardcoded page content.', 'Super Admin', '2026-09-07 10:30:07'),
(8, 1, 'v1.3', '<h4>1. Who We Are</h4>\r\n<p>This Data Privacy Notice is issued by TELE-CARE (\"we,\" \"us,\" or \"our\") for our telemedicine platform, in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and its Implementing Rules and Regulations. ok</p>\r\n\r\n<h4>2. Personal Data We Collect</h4>\r\n<p>When you register and use TELE-CARE, we collect:</p>\r\n<ul>\r\n  <li>Identity information — full name, date of birth, contact number, email address</li>\r\n  <li>Account credentials — password (stored in encrypted/hashed form)</li>\r\n  <li>Health information — medical history, symptoms, consultation notes, prescriptions, lab results, and other information you or your attending doctor provide during a teleconsultation</li>\r\n  <li>Technical data — device information, IP address, browser type, and log data related to your use of the platform</li>\r\n  <li>Communications — messages, video/audio session metadata, and files exchanged with healthcare providers through the platform</li>\r\n</ul>\r\n\r\n<h4>3. Why We Collect Your Data</h4>\r\n<p>Your personal and health data are collected and processed to:</p>\r\n<ul>\r\n  <li>Create and manage your patient account</li>\r\n  <li>Facilitate teleconsultations between you and licensed healthcare providers</li>\r\n  <li>Maintain accurate medical and consultation records</li>\r\n  <li>Send appointment confirmations, reminders, and account-related notifications</li>\r\n  <li>Process payments for consultations, where applicable</li>\r\n  <li>Comply with legal, regulatory, and reporting obligations</li>\r\n  <li>Improve the safety, security, and functionality of the platform</li>\r\n</ul>\r\n\r\n<h4>4. Sensitive Personal Information</h4>\r\n<p>Health-related data is classified as \"sensitive personal information\" under RA 10173. We only process this data with your explicit consent, and access is restricted to your attending healthcare provider, authorized clinic/administrative staff directly involved in your care, and personnel required by law or regulation.</p>\r\n\r\n<h4>5. Data Sharing and Disclosure</h4>\r\n<p>We do not sell your personal data. Your information may only be shared with:</p>\r\n<ul>\r\n  <li>Licensed doctors and staff directly involved in your consultation</li>\r\n  <li>Service providers who support platform operations (e.g., email delivery, secure hosting) under confidentiality obligations</li>\r\n  <li>Government agencies or regulators, when required by law, court order, or public health reporting requirements</li>\r\n</ul>\r\n\r\n<h4>6. Data Retention</h4>\r\n<p>Your personal and medical records are retained for as long as your account is active and for a period thereafter as required by applicable healthcare recordkeeping laws and regulations, after which the data will be securely disposed of or anonymized.</p>\r\n\r\n<h4>7. Your Rights</h4>\r\n<p>Under the Data Privacy Act, you have the right to be informed, to access, to object, to correct, to erase or block your data (subject to legal retention requirements), to data portability, and to file a complaint with the National Privacy Commission. To exercise these rights, contact us through the channels provided on your patient dashboard.</p>\r\n\r\n<h4>8. Security Measures</h4>\r\n<p>We apply organizational, physical, and technical safeguards — including password hashing, encrypted connections, and access controls — to protect your data against unauthorized access, alteration, disclosure, or destruction.</p>\r\n\r\n<h4>9. Consent</h4>\r\n<p>By creating a TELE-CARE account, you acknowledge that you have read and understood this Data Privacy Notice and consent to the collection, use, and processing of your personal and sensitive personal information as described above.</p>', 'Published', 'try', 'Super Admin', '2026-09-07 11:23:17'),
(9, 1, 'v1.4', '<h4>1. Who We Are</h4>\r\n<p>This Data Privacy Notice is issued by TELE-CARE (\"we,\" \"us,\" or \"our\") for our telemedicine platform, in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173) and its Implementing Rules and Regulations.</p>\r\n\r\n<h4>2. Personal Data We Collect</h4>\r\n<p>When you register and use TELE-CARE, we collect:</p>\r\n<ul>\r\n  <li>Identity information — full name, date of birth, contact number, email address</li>\r\n  <li>Account credentials — password (stored in encrypted/hashed form)</li>\r\n  <li>Health information — medical history, symptoms, consultation notes, prescriptions, lab results, and other information you or your attending doctor provide during a teleconsultation</li>\r\n  <li>Technical data — device information, IP address, browser type, and log data related to your use of the platform</li>\r\n  <li>Communications — messages, video/audio session metadata, and files exchanged with healthcare providers through the platform</li>\r\n</ul>\r\n\r\n<h4>3. Why We Collect Your Data</h4>\r\n<p>Your personal and health data are collected and processed to:</p>\r\n<ul>\r\n  <li>Create and manage your patient account</li>\r\n  <li>Facilitate teleconsultations between you and licensed healthcare providers</li>\r\n  <li>Maintain accurate medical and consultation records</li>\r\n  <li>Send appointment confirmations, reminders, and account-related notifications</li>\r\n  <li>Process payments for consultations, where applicable</li>\r\n  <li>Comply with legal, regulatory, and reporting obligations</li>\r\n  <li>Improve the safety, security, and functionality of the platform</li>\r\n</ul>\r\n\r\n<h4>4. Sensitive Personal Information</h4>\r\n<p>Health-related data is classified as \"sensitive personal information\" under RA 10173. We only process this data with your explicit consent, and access is restricted to your attending healthcare provider, authorized clinic/administrative staff directly involved in your care, and personnel required by law or regulation.</p>\r\n\r\n<h4>5. Data Sharing and Disclosure</h4>\r\n<p>We do not sell your personal data. Your information may only be shared with:</p>\r\n<ul>\r\n  <li>Licensed doctors and staff directly involved in your consultation</li>\r\n  <li>Service providers who support platform operations (e.g., email delivery, secure hosting) under confidentiality obligations</li>\r\n  <li>Government agencies or regulators, when required by law, court order, or public health reporting requirements</li>\r\n</ul>\r\n\r\n<h4>6. Data Retention</h4>\r\n<p>Your personal and medical records are retained for as long as your account is active and for a period thereafter as required by applicable healthcare recordkeeping laws and regulations, after which the data will be securely disposed of or anonymized.</p>\r\n\r\n<h4>7. Your Rights</h4>\r\n<p>Under the Data Privacy Act, you have the right to be informed, to access, to object, to correct, to erase or block your data (subject to legal retention requirements), to data portability, and to file a complaint with the National Privacy Commission. To exercise these rights, contact us through the channels provided on your patient dashboard.</p>\r\n\r\n<h4>8. Security Measures</h4>\r\n<p>We apply organizational, physical, and technical safeguards — including password hashing, encrypted connections, and access controls — to protect your data against unauthorized access, alteration, disclosure, or destruction.</p>\r\n\r\n<h4>9. Consent</h4>\r\n<p>By creating a TELE-CARE account, you acknowledge that you have read and understood this Data Privacy Notice and consent to the collection, use, and processing of your personal and sensitive personal information as described above.</p>', 'Published', 'done testing', 'Super Admin', '2026-09-07 11:23:47'),
(10, 2, 'v1.4', '<h4>1. Overview</h4>\r\n<p>This Privacy Policy explains how TELE-CARE handles information collected through our telemedicine platform, in addition to the specific commitments made in our Data Privacy Notice. ok</p>\r\n\r\n<h4>2. Information We Collect Automatically</h4>\r\n<p>When you use TELE-CARE, our systems may automatically collect device type, browser, IP address, session timestamps, and general usage patterns (e.g., pages visited, features used) to help us maintain and improve the platform.</p>\r\n\r\n<h4>3. Cookies and Similar Technologies</h4>\r\n<p>TELE-CARE may use cookies or similar technologies to keep you logged in, remember your preferences, and understand how the platform is used. You can control cookies through your browser settings, though disabling them may affect platform functionality.</p>\r\n\r\n<h4>4. How We Use Information</h4>\r\n<ul>\r\n  <li>To operate, maintain, and secure the platform</li>\r\n  <li>To personalize your experience (e.g., pre-filling known details, showing relevant appointment information)</li>\r\n  <li>To detect, investigate, and prevent fraudulent or unauthorized activity</li>\r\n  <li>To analyze aggregate usage trends for service improvement, using de-identified data where possible</li>\r\n</ul>\r\n\r\n<h4>5. Third-Party Services</h4>\r\n<p>TELE-CARE relies on trusted third-party providers for functions such as email delivery and secure video communication. These providers process data only as necessary to perform their function and are contractually or technically restricted from using your data for unrelated purposes.</p>\r\n\r\n<h4>6. Data Storage and International Transfers</h4>\r\n<p>Your data is stored on secure servers. Where any data is processed or stored outside the Philippines by a service provider, we take reasonable steps to ensure it remains protected to a standard consistent with the Data Privacy Act.</p>\r\n\r\n<h4>7. Children\'s Privacy</h4>\r\n<p>TELE-CARE is intended for users 18 years of age and older. We do not knowingly collect personal data from minors through direct patient registration.</p>\r\n\r\n<h4>8. Updates to This Policy</h4>\r\n<p>We may revise this Privacy Policy periodically. Material changes will be communicated through the platform or via email prior to taking effect.</p>\r\n\r\n<h4>9. Contact Us</h4>\r\n<p>For questions, concerns, or requests relating to this Privacy Policy or your personal data, please reach out through the support channels available on your TELE-CARE patient dashboard.</p>', 'Published', 'test', 'Super Admin', '2026-09-09 12:55:15'),
(11, 2, 'v1.5', '<h4>1. Overview</h4>\r\n<p>This Privacy Policy explains how TELE-CARE handles information collected through our telemedicine platform, in addition to the specific commitments made in our Data Privacy Notice.&nbsp;</p>\r\n\r\n<h4>2. Information We Collect Automatically</h4>\r\n<p>When you use TELE-CARE, our systems may automatically collect device type, browser, IP address, session timestamps, and general usage patterns (e.g., pages visited, features used) to help us maintain and improve the platform.</p>\r\n\r\n<h4>3. Cookies and Similar Technologies</h4>\r\n<p>TELE-CARE may use cookies or similar technologies to keep you logged in, remember your preferences, and understand how the platform is used. You can control cookies through your browser settings, though disabling them may affect platform functionality.</p>\r\n\r\n<h4>4. How We Use Information</h4>\r\n<ul>\r\n  <li>To operate, maintain, and secure the platform</li>\r\n  <li>To personalize your experience (e.g., pre-filling known details, showing relevant appointment information)</li>\r\n  <li>To detect, investigate, and prevent fraudulent or unauthorized activity</li>\r\n  <li>To analyze aggregate usage trends for service improvement, using de-identified data where possible</li>\r\n</ul>\r\n\r\n<h4>5. Third-Party Services</h4>\r\n<p>TELE-CARE relies on trusted third-party providers for functions such as email delivery and secure video communication. These providers process data only as necessary to perform their function and are contractually or technically restricted from using your data for unrelated purposes.</p>\r\n\r\n<h4>6. Data Storage and International Transfers</h4>\r\n<p>Your data is stored on secure servers. Where any data is processed or stored outside the Philippines by a service provider, we take reasonable steps to ensure it remains protected to a standard consistent with the Data Privacy Act.</p>\r\n\r\n<h4>7. Children\'s Privacy</h4>\r\n<p>TELE-CARE is intended for users 18 years of age and older. We do not knowingly collect personal data from minors through direct patient registration.</p>\r\n\r\n<h4>8. Updates to This Policy</h4>\r\n<p>We may revise this Privacy Policy periodically. Material changes will be communicated through the platform or via email prior to taking effect.</p>\r\n\r\n<h4>9. Contact Us</h4>\r\n<p>For questions, concerns, or requests relating to this Privacy Policy or your personal data, please reach out through the support channels available on your TELE-CARE patient dashboard.</p>', 'Published', 'test', 'Super Admin', '2026-09-09 13:00:05');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `audience` varchar(20) NOT NULL DEFAULT 'patient',
  `patient_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(150) NOT NULL,
  `message` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reference_type` varchar(30) DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `audience`, `patient_id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`, `reference_type`, `reference_id`) VALUES
(1, 'patient', 1, 'appointment_approved', 'Appointment Approved', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 5, 2026 at 12:01 AM has been confirmed.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 10),
(2, 'patient', 1, 'appointment_reminder', 'Appointment Reminder', 'You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 12:01 AM.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 10),
(3, 'patient', 1, 'appointment_approved', 'Appointment Approved', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 5, 2026 at 12:00 AM has been confirmed.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 9),
(4, 'patient', 1, 'appointment_reminder', 'Appointment Reminder', 'You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 12:00 AM.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 9),
(5, 'patient', 1, 'appointment_approved', 'Appointment Approved', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:59 PM has been confirmed.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 8),
(6, 'patient', 1, 'appointment_reminder', 'Appointment Reminder', 'You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 11:59 PM.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 8),
(7, 'patient', 1, 'appointment_approved', 'Appointment Approved', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:59 PM has been confirmed.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 7),
(8, 'patient', 1, 'appointment_reminder', 'Appointment Reminder', 'You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 11:59 PM.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 7),
(9, 'patient', 1, 'appointment_approved', 'Appointment Approved', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:59 PM has been confirmed.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 6),
(10, 'patient', 1, 'appointment_reminder', 'Appointment Reminder', 'You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 11:59 PM.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 6),
(11, 'patient', 1, 'appointment_approved', 'Appointment Approved', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:58 PM has been confirmed.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 5),
(12, 'patient', 1, 'appointment_reminder', 'Appointment Reminder', 'You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 11:58 PM.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 5),
(13, 'patient', 1, 'appointment_cancelled', 'Appointment Cancelled', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Aug 23, 2026 at 5:00 PM was cancelled.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 4),
(14, 'patient', 1, 'appointment_cancelled', 'Appointment Cancelled', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Aug 22, 2026 at 10:30 PM was cancelled.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 3),
(15, 'patient', 1, 'appointment_cancelled', 'Appointment Cancelled', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Aug 22, 2026 at 9:30 PM was cancelled.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 2),
(16, 'patient', 1, 'appointment_approved', 'Appointment Approved', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Aug 22, 2026 at 5:00 PM has been confirmed.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 1),
(17, 'patient', 1, 'appointment_missed', 'Missed Appointment', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Aug 22, 2026 at 5:00 PM was missed.', 'router.php?page=visits', 1, '2026-09-04 16:21:59', 'appointment', 1),
(36, 'patient', 1, 'appointment_missed', 'Missed Appointment', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 5, 2026 at 12:01 AM was missed.', 'router.php?page=visits', 1, '2026-09-04 16:24:31', 'appointment', 10),
(38, 'patient', 1, 'appointment_missed', 'Missed Appointment', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 5, 2026 at 12:00 AM was missed.', 'router.php?page=visits', 1, '2026-09-04 16:24:31', 'appointment', 9),
(40, 'patient', 1, 'appointment_missed', 'Missed Appointment', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:59 PM was missed.', 'router.php?page=visits', 1, '2026-09-04 16:24:31', 'appointment', 8),
(42, 'patient', 1, 'appointment_missed', 'Missed Appointment', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:59 PM was missed.', 'router.php?page=visits', 1, '2026-09-04 16:24:31', 'appointment', 7),
(44, 'patient', 1, 'appointment_missed', 'Missed Appointment', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:59 PM was missed.', 'router.php?page=visits', 1, '2026-09-04 16:24:31', 'appointment', 6),
(46, 'patient', 1, 'appointment_missed', 'Missed Appointment', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 4, 2026 at 11:58 PM was missed.', 'router.php?page=visits', 1, '2026-09-04 16:24:31', 'appointment', 5),
(460, 'patient', 1, 'appointment_approved', 'Appointment Approved', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 9, 2026 at 4:30 PM has been confirmed.', 'router.php?page=visits', 1, '2026-09-08 13:40:35', 'appointment', 11),
(461, 'patient', 1, 'appointment_reminder', 'Appointment Reminder', 'You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 4:30 PM.', 'router.php?page=visits', 1, '2026-09-08 13:40:35', 'appointment', 11),
(612, 'patient', 1, 'consultation_completed', 'Consultation Completed', 'Your consultation with Dr. EXAMPLE DOCTOR#1 on Sep 9, 2026 has been completed.', 'router.php?page=visits', 1, '2026-09-08 17:20:20', 'appointment', 11),
(1476, 'patient', 1, 'appointment_approved', 'Appointment Approved', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 9, 2026 at 10:30 PM has been confirmed.', 'router.php?page=visits', 1, '2026-09-09 13:35:54', 'appointment', 12),
(1477, 'patient', 1, 'appointment_reminder', 'Appointment Reminder', 'You have an appointment with Dr. EXAMPLE DOCTOR#1 tomorrow at 10:30 PM.', 'router.php?page=visits', 1, '2026-09-09 13:35:54', 'appointment', 12),
(2258, 'patient', 1, 'consultation_soon', 'Consultation Starting Soon', 'Your consultation with Dr. EXAMPLE DOCTOR#1 starts at 10:30 PM.', 'router.php?page=visits', 1, '2026-09-09 14:04:34', 'appointment', 12),
(2562, 'patient', 1, 'appointment_missed', 'Missed Appointment', 'Your appointment with Dr. EXAMPLE DOCTOR#1 on Sep 9, 2026 at 10:30 PM was missed.', 'router.php?page=visits', 1, '2026-09-09 14:49:25', 'appointment', 12),
(2821, 'patient', 1, 'appointment_request', 'Appointment Request Submitted', 'Your request for an appointment with Dr. EXAMPLE DOCTOR#1 on Sep 11, 2026 at 1:00 PM is pending confirmation.', 'router.php?page=visits', 1, '2026-09-09 14:54:57', 'appointment', 13),
(3073, 'patient', 1, 'instant_call', 'Incoming Instant Call', 'Dr. EXAMPLE DOCTOR#1 started an instant video consultation with you. Tap to join.', 'router.php?page=visits', 1, '2026-09-10 12:30:44', 'appointment', 14),
(3074, 'patient', 1, 'instant_call', 'Incoming Instant Call', 'Dr. EXAMPLE DOCTOR#1 started an instant video consultation with you. Tap to join.', 'router.php?page=visits', 1, '2026-09-16 08:38:08', 'appointment', 20),
(3075, 'patient', 1, 'instant_call', 'Incoming Instant Call', 'Dr. EXAMPLE DOCTOR#1 started an instant video consultation with you. Tap to join.', 'router.php?page=visits', 1, '2026-09-16 09:43:01', 'appointment', 21),
(3076, 'patient', 7, 'instant_call', 'Incoming Instant Call', 'Dr. EXAMPLE DOCTOR#1 started an instant video consultation with you. Tap to join.', 'router.php?page=visits', 0, '2026-09-18 09:08:46', 'appointment', 3),
(3077, 'patient', 8, 'instant_call', 'Incoming Instant Call', 'Dr. EXAMPLE DOCTOR#1 started an instant video consultation with you. Tap to join.', 'router.php?page=visits', 1, '2026-09-18 09:09:14', 'appointment', 4),
(3078, 'patient', 8, 'instant_call', 'Incoming Instant Call', 'Dr. EXAMPLE DOCTOR#1 started an instant video consultation with you. Tap to join.', 'router.php?page=visits', 1, '2026-09-18 09:17:47', 'appointment', 5),
(3079, 'patient', 8, 'instant_call', 'Incoming Instant Call', 'Dr. EXAMPLE DOCTOR#1 started an instant video consultation with you. Tap to join.', 'router.php?page=visits', 1, '2026-09-18 10:29:27', 'appointment', 6),
(3080, 'patient', 8, 'instant_call', 'Incoming Instant Call', 'Dr. EXAMPLE DOCTOR#1 started an instant video consultation with you. Tap to join.', 'router.php?page=visits', 1, '2026-09-18 15:41:44', 'appointment', 9),
(3081, 'patient', 8, 'instant_call', 'Incoming Instant Call', 'Dr. EXAMPLE DOCTOR#1 started an instant video consultation with you. Tap to join.', 'router.php?page=visits', 0, '2026-09-25 14:41:40', 'appointment', 12),
(3082, 'patient', 8, 'instant_call', 'Incoming Instant Call', 'Dr. EXAMPLE DOCTOR#1 started an instant video consultation with you. Tap to join.', 'router.php?page=visits', 1, '2026-09-25 16:26:07', 'appointment', 14),
(3083, 'staff', 0, 'new_booking', 'New appointment request', 'John Noel Oraño booked an appointment (Oct 15, 1:00 PM) that\'s waiting for the doctor\'s review.', 'appointments.php?tab=Pending', 0, '2026-10-15 04:53:23', 'appointment', 19),
(3132, 'patient', 16, 'hmo_pending', 'Waiting for HMO approval', 'Your HMO booking was submitted. The clinic is asking your HMO for approval. Upload your documents to speed this up.', 'router.php?page=booking/hmo_status&appt_id=20', 1, '2026-10-17 00:13:40', 'appointment', 20),
(3133, 'patient', 15, 'hmo_pending', 'Waiting for HMO approval', 'Your HMO booking was submitted. The clinic is asking your HMO for approval. Upload your documents to speed this up.', 'router.php?page=booking/hmo_status&appt_id=21', 1, '2026-10-17 00:24:31', 'appointment', 21),
(3134, 'staff', 0, 'new_booking', 'New appointment request', 'Andrei Mesa booked an appointment (Oct 23, 4:00 PM) that\'s waiting for the doctor\'s review.', 'appointments.php?tab=Pending', 0, '2026-10-17 01:34:31', 'appointment', 23),
(3138, 'patient', 15, 'hmo_pending', 'Waiting for HMO approval', 'Your HMO booking was submitted. The clinic is asking your HMO for approval. Upload your documents to speed this up.', 'router.php?page=booking/hmo_status&appt_id=23', 0, '2026-10-17 01:34:40', 'appointment', 23),
(3139, 'patient', 10, 'hmo_pending', 'LOA received', 'We received your LOA. The clinic will check and accept it. Your appointment is confirmed once it is accepted.', 'router.php?page=booking/hmo_status&appt_id=24', 1, '2026-10-17 05:36:04', 'appointment', 24),
(3140, 'patient', 10, 'hmo_approved', 'LOA accepted — appointment confirmed', 'The clinic accepted your LOA (PhilhealthCare, Inc. (PhilCare)). Your appointment on Oct 17, 2026 at 2:30 PM is confirmed.', 'router.php?page=booking/hmo_status&appt_id=24', 1, '2026-10-17 05:43:49', 'appointment', 24),
(3141, 'patient', 10, 'yakap_pending', 'Waiting for YAKAP verification', 'Your PhilHealth YAKAP details were submitted. Your appointment is confirmed once the clinic verifies them.', 'router.php?page=booking/confirmed&appt_id=25', 0, '2026-10-17 06:51:49', 'appointment', 25);

-- --------------------------------------------------------

--
-- Table structure for table `notification_reads`
--

CREATE TABLE `notification_reads` (
  `id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notification_reads`
--

INSERT INTO `notification_reads` (`id`, `notification_id`, `staff_id`, `read_at`) VALUES
(1, 3083, 4, '2026-10-15 04:53:41');

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` int(11) NOT NULL,
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
  `google_id` varchar(255) DEFAULT NULL,
  `auth_provider` varchar(20) NOT NULL DEFAULT 'local',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_verified` tinyint(1) NOT NULL DEFAULT 0,
  `verification_token` varchar(64) DEFAULT NULL,
  `token_expires_at` datetime DEFAULT NULL,
  `reset_token` varchar(64) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `remember_selector` varchar(32) DEFAULT NULL,
  `remember_validator_hash` varchar(64) DEFAULT NULL,
  `remember_expires` datetime DEFAULT NULL,
  `two_factor_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `notif_appointment_reminders` tinyint(1) NOT NULL DEFAULT 1,
  `notif_payment_notifications` tinyint(1) NOT NULL DEFAULT 1,
  `notif_medical_record_updates` tinyint(1) NOT NULL DEFAULT 1,
  `account_status` varchar(20) NOT NULL DEFAULT 'active',
  `is_guest` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `full_name`, `date_of_birth`, `gender`, `email`, `phone_number`, `address`, `profile_photo`, `emergency_name`, `emergency_relationship`, `emergency_number`, `password`, `security_question`, `security_answer`, `home_address`, `city`, `country_region`, `insurance_provider`, `insurance_policy_no`, `preferred_language`, `google_id`, `auth_provider`, `created_at`, `updated_at`, `is_verified`, `verification_token`, `token_expires_at`, `reset_token`, `reset_expires`, `is_active`, `remember_selector`, `remember_validator_hash`, `remember_expires`, `two_factor_enabled`, `notif_appointment_reminders`, `notif_payment_notifications`, `notif_medical_record_updates`, `account_status`, `is_guest`) VALUES
(1, 'John Noel Oraño', '2004-12-10', 'Male', 'johnnoelorano@gmail.com', '09923139504', NULL, 'uploads/profiles/patient_69ad35693609a.png', '', '', '', '$2y$10$HDx76CM.oTMDTehDq.Rwmu16LHCvP.rpz5WHuK3cx/S1ufdeLa5ka', '', NULL, 'NPC KANAN MAKISIG STREET BARANGAY 171', 'CALOOCAN CITY', 'Philippines', '', '', 'English', NULL, 'local', '2026-03-08 06:11:37', '2026-09-09 15:33:49', 1, NULL, NULL, 'a192bfa68c01c8c184036738739167867708f4d54f374ccea90a07346441a8e8', '2026-09-09 21:45:04', 1, NULL, NULL, NULL, 0, 1, 1, 1, 'active', 0),
(5, 'Cid Kagenou', '2011-03-01', 'Male', 'cidkag1210@gmail.com', '09123456789', NULL, 'uploads/profiles/patient_69b66c6f61c19.png', '', '', '', '$2y$10$X.uZM7Zu/WPogMFGiCEIjOIDyRQYm.7vchIVgaamtNEZgdaQPjByO', '', NULL, 'Hillcrest Village Gate 1', 'Caloocan City', 'Philippines', '', '', 'English', NULL, 'local', '2026-03-15 08:00:28', '2026-03-29 18:50:03', 1, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 0, 1, 1, 1, 'active', 0),
(6, 'John Nowel', '2008-04-03', 'Male', 'sumivalo10@gmail.com', '+639923139504', NULL, NULL, '', '', '', '$2y$10$z3EWoneDOMdsJezI/0cgBOvGTgWjbPwYxSLOE0SgoZhOY5dHq46YG', '', NULL, '', 'City of Caloocan', 'Philippines', '', '', 'English', NULL, 'local', '2026-04-04 07:07:34', '2026-04-04 07:18:53', 0, '9d01ef1f345fbe20b2875603736c41a22457ab33fba251d3182fe89238061f0b', '2026-04-05 09:18:53', NULL, NULL, 1, NULL, NULL, NULL, 0, 1, 1, 1, 'active', 0),
(7, 'Jerson Sagun', '2008-08-09', 'Male', 'jersonsagun@gmail.com', '+639923139504', NULL, NULL, '', '', '', '$2y$10$h4kFa3vluW0FOuw0v6yNseqhRWMjTDu8Xug5pCBimowS21FLLaQUO', '', NULL, '', 'City of Caloocan', 'Philippines', '', '', 'English', NULL, 'local', '2026-08-10 08:17:30', '2026-08-10 08:17:30', 0, '1d9162f9963ac2cef5c6451c0c2b93f320565d409d8da6f37a026f1d22880a5f', '2026-08-11 10:17:30', NULL, NULL, 1, NULL, NULL, NULL, 0, 1, 1, 1, 'active', 0),
(8, 'Jerson Uanan Sagun', '2004-10-15', 'Male', 'sagun.jersonbsis2023@gmail.com', '+639602059511', NULL, NULL, NULL, NULL, NULL, '$2y$10$WefO41SasPK3Pws2u5oHoeC3r3r7q3.8E5.4ORgVoIG.gaKpp9pWy', NULL, NULL, 'Senate Village', 'City of Caloocan', NULL, NULL, NULL, NULL, NULL, 'local', '2026-08-14 11:23:03', '2026-09-18 14:12:03', 1, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 0, 1, 1, 1, 'active', 0),
(9, 'sadw asda asdasd', '2008-09-06', NULL, 'sdawasda@sadwad.com', '+639999999999', NULL, NULL, NULL, NULL, NULL, '$2y$10$kG.C92nV.a0uyzGkWeF.0unoPG/nwIZGQjJI7G7PtTeLsDMzytUcC', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'local', '2026-09-13 15:06:56', '2026-09-13 15:06:56', 0, '556b5a087dca305fb5c354372b1313fd9b1074c8a6177aba6f2af913ab2dfa4b', '2026-09-14 17:06:56', NULL, NULL, 1, NULL, NULL, NULL, 0, 1, 1, 1, 'active', 0),
(10, 'Payawal Ian Matthew S.', '2005-12-10', 'Male', 'jaimepayawal17@gmail.com', '+639615081766', NULL, 'uploads/profiles/patient_g_6aace9a8cdd643.52498971.jpg', '', '', '', '$2y$10$sdFLyPvtEBlc6KTopubZV.8esaq1PfYyMXNmPhBj1hiTIdFVh4La.', '', NULL, '', 'City of Caloocan', 'Philippines', '', '', 'English', '102080237813838958464', 'google', '2026-09-18 07:35:04', '2026-09-18 07:35:34', 1, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 0, 1, 1, 1, 'active', 0),
(11, 'James Rhian Sagun', '2006-12-18', 'Male', 'sagunjames24@gmail.com', '+639212757413', NULL, 'uploads/profiles/patient_g_6ab37a98e71b11.22557523.jpg', '', '', '', '$2y$10$dNt4fjHh957eRIzDE1MMI.SW9PmcwSLx0fGLF.j.Z8CdoZSGzQTVW', '', NULL, '', 'City of Caloocan', 'Philippines', '', '', 'English', '109232164228015068324', 'google', '2026-09-23 07:07:05', '2026-09-23 07:07:33', 1, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 0, 1, 1, 1, 'active', 0),
(12, 'Aiko Maite Norton Gibson', '1987-05-02', NULL, 'kimpot236@gmail.com', '+639999999999', NULL, NULL, NULL, NULL, NULL, '$2y$10$5hETIs8RpnrW/Kcpi7TGk.aTMB.l2FKSt9U6INsQzOMwsuv4Iwy0K', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'local', '2026-09-24 09:59:58', '2026-09-24 09:59:58', 0, '21a2dc6fc81e89a7423bf729632f79f61aa7ce5941d383e0b686a0a32f16c715', '2026-09-25 09:59:58', NULL, NULL, 1, NULL, NULL, NULL, 0, 1, 1, 1, 'active', 0),
(13, 'Lesley Nathan Grimes Wyatt', '2006-04-22', NULL, 'jersonsagun1123@gmail.com', '+639999999999', NULL, NULL, NULL, NULL, NULL, '$2y$10$Fq1vh0Dbd58hqfySYTWSJupYjsQXIlJHf1Y7/LbCtk6.m7LUzF9jW', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'local', '2026-09-24 10:03:10', '2026-09-24 10:03:34', 1, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 0, 1, 1, 1, 'active', 0),
(14, 'Payawal Ian Matthew S.', '2008-09-10', 'Male', 'jrry76318@gmail.com', '+639615081766', NULL, 'uploads/profiles/patient_g_6ab77301c6de00.09629412.jpg', '', '', '', '$2y$10$0fipuadZzT4HpyynIw7mI.xd65ecH9jaYwoC5CKadpLJTAmavy366', '', NULL, '', 'City of Caloocan', 'Philippines', '', '', 'English', '102626037926101907819', 'google', '2026-09-26 07:23:45', '2026-09-26 07:24:27', 1, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 0, 1, 1, 1, 'active', 0),
(15, 'Andrei Mesa', '2004-06-30', NULL, 'chakarib11@gmail.com', '+639660138508', NULL, NULL, NULL, NULL, NULL, '$2y$10$88jBsDQwRIK1BfVbKuulXOg9OXWu0.7GUPSrrQD07ZwDh2RVCA7Gi', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 'local', '2026-10-01 14:15:19', '2026-10-01 14:15:44', 1, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 0, 1, 1, 1, 'active', 0),
(16, 'John Noel Orano', '2004-12-10', 'Male', 'oranonoel0@gmail.com', '09923139504', 'NPC KANAN MAKISIG STREET', NULL, NULL, NULL, NULL, '$2y$10$h9TLbuIAqKRN/NwlmA3ZNebvrfIEAJh3QW2.rmHZW0Ng0ThMgevE2', NULL, NULL, 'NPC KANAN MAKISIG STREET', NULL, NULL, NULL, NULL, NULL, NULL, 'local', '2026-10-17 00:12:30', '2026-10-17 00:12:30', 1, NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, 0, 1, 1, 1, 'active', 1);

-- --------------------------------------------------------

--
-- Table structure for table `pos_sales`
--

CREATE TABLE `pos_sales` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `patient_name` varchar(150) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pos_sales`
--

INSERT INTO `pos_sales` (`id`, `staff_id`, `patient_name`, `total_amount`, `created_at`) VALUES
(1, 4, NULL, 222.00, '2026-08-24 21:09:18'),
(2, 4, 'Ian ricaro', 350.00, '2026-09-18 10:27:22');

-- --------------------------------------------------------

--
-- Table structure for table `pos_sale_items`
--

CREATE TABLE `pos_sale_items` (
  `id` int(11) NOT NULL,
  `sale_id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `service_name` varchar(150) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pos_sale_items`
--

INSERT INTO `pos_sale_items` (`id`, `sale_id`, `service_id`, `service_name`, `quantity`, `unit_price`, `subtotal`) VALUES
(1, 1, 8, 'sda', 1, 222.00, 222.00),
(2, 2, 1, 'CBC Test', 1, 350.00, 350.00);

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `category` enum('Medicine','Testing Kits') NOT NULL,
  `description` text DEFAULT NULL,
  `unit` enum('Tablet','Capsule','Bottle','Box','Vial','Piece','Pack','Syrup') NOT NULL DEFAULT 'Piece',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reorder_level` int(11) NOT NULL DEFAULT 0,
  `stock_quantity` int(11) NOT NULL DEFAULT 0,
  `status` enum('Active','Archived') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `category`, `description`, `unit`, `price`, `reorder_level`, `stock_quantity`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Paracetamol 500mg', 'Medicine', 'Pain reliever / fever reducer', 'Tablet', 10.00, 20, 118, 'Active', '2026-08-22 15:33:51', '2026-09-18 17:27:22'),
(2, 'Amoxicillin 500mg', 'Medicine', 'Antibiotic capsule', 'Capsule', 25.00, 15, 50, 'Active', '2026-08-22 15:33:51', '2026-08-22 15:33:51'),
(3, 'Vitamin C 500mg', 'Medicine', 'Immune support supplement', 'Tablet', 15.00, 10, 80, 'Active', '2026-08-22 15:33:51', '2026-08-23 13:02:48'),
(4, 'Digital Thermometer', 'Medicine', 'Non-contact infrared thermometer', 'Piece', 350.00, 5, 12, 'Archived', '2026-08-22 15:33:51', '2026-09-18 17:37:41'),
(5, 'Cough Syrup 60ml', 'Medicine', 'Relief for dry and wet cough', 'Bottle', 85.00, 8, 3, 'Active', '2026-08-22 15:33:51', '2026-08-22 15:33:51'),
(26, 'Urine Specimen Container', 'Testing Kits', 'Sterile container for urinalysis specimen collection.', 'Piece', 10.00, 20, 100, 'Active', '2026-08-23 14:10:22', '2026-08-23 14:11:49'),
(27, 'Stool Specimen Container', 'Testing Kits', 'Container for fecalysis (stool) specimen collection.', 'Piece', 10.00, 20, 100, 'Active', '2026-08-23 14:10:22', '2026-08-23 14:11:49'),
(28, 'Fecal Occult Blood Test Stick', 'Testing Kits', 'Stick used for fecal occult blood testing.', 'Piece', 15.00, 20, 100, 'Active', '2026-08-23 14:10:22', '2026-08-23 14:11:49'),
(29, 'Pregnancy Test Kit (Strip)', 'Testing Kits', 'Urine pregnancy test strip.', 'Piece', 25.00, 15, 100, 'Active', '2026-08-23 14:10:22', '2026-08-23 14:11:49'),
(30, 'Blood Glucose Test Strip', 'Testing Kits', 'Test strip for blood glucose monitoring.', 'Piece', 20.00, 30, 100, 'Active', '2026-08-23 14:10:22', '2026-08-23 14:11:49'),
(31, 'Lancet', 'Testing Kits', 'Sterile lancet for capillary blood sampling.', 'Piece', 3.00, 50, 99, 'Active', '2026-08-23 14:10:22', '2026-08-24 13:09:18'),
(32, 'Cotton Swab (Sterile)', 'Testing Kits', 'Sterile cotton swab for specimen collection.', 'Piece', 2.00, 50, 100, 'Active', '2026-08-23 14:10:22', '2026-08-23 14:11:49'),
(33, 'Alcohol Swab', 'Testing Kits', 'Alcohol prep pad for skin disinfection before sampling.', 'Piece', 1.50, 50, 100, 'Active', '2026-08-23 14:10:22', '2026-08-23 14:11:49'),
(34, 'Vacutainer Blood Collection Tube', 'Testing Kits', 'Tube for venous blood sample collection.', 'Piece', 12.00, 30, 100, 'Active', '2026-08-23 14:10:22', '2026-08-23 14:11:49'),
(35, 'Rapid Antigen Test Kit', 'Testing Kits', 'Rapid antigen test kit for infectious disease screening.', 'Box', 150.00, 10, 100, 'Active', '2026-08-23 14:10:22', '2026-08-23 14:11:49');

-- --------------------------------------------------------

--
-- Table structure for table `receipt_settings`
--

CREATE TABLE `receipt_settings` (
  `id` int(11) NOT NULL DEFAULT 1,
  `clinic_name` varchar(150) NOT NULL DEFAULT 'ExcellCare Medical System Inc.',
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `tin` varchar(30) DEFAULT NULL,
  `footer_note` varchar(255) DEFAULT 'Thank you for choosing ExcellCare!',
  `show_logo` tinyint(1) NOT NULL DEFAULT 1,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

--
-- Dumping data for table `receipt_settings`
--

INSERT INTO `receipt_settings` (`id`, `clinic_name`, `address`, `phone`, `tin`, `footer_note`, `show_logo`, `updated_at`) VALUES
(1, 'ExcellCare Medical System Inc.', '123 Sample St., Quezon City', '(02) 8888-0000', '000-000-000-000', 'Thank you for choosing ExcellCare!', 1, '2026-08-23 11:28:41');

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `category` enum('Laboratory','X-ray','Chemical','Consultation','Other') NOT NULL DEFAULT 'Other',
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `price`, `category`, `status`, `created_at`, `updated_at`) VALUES
(1, 'CBC Test', 'Complete Blood Count test', 350.00, 'Laboratory', 'Active', '2026-08-23 11:25:58', '2026-08-23 11:25:58'),
(2, 'Urinalysis', 'Routine urine test', 150.00, 'Laboratory', 'Active', '2026-08-23 11:25:58', '2026-08-23 11:25:58'),
(3, 'X-Ray Imaging', 'Standard single-view X-ray', 320.00, 'X-ray', 'Active', '2026-08-23 11:25:58', '2026-08-23 11:25:58'),
(4, 'General Consultation', 'Walk-in consultation with a physician', 500.00, 'Consultation', 'Active', '2026-08-23 11:25:58', '2026-08-29 07:32:37'),
(5, 'asda', 'asdada', 222.00, 'Laboratory', 'Inactive', '2026-08-23 12:54:32', '2026-08-29 07:33:06'),
(6, 'SADA', 'DASDSAD', 222.00, 'Laboratory', 'Active', '2026-08-23 13:09:23', '2026-08-23 13:09:23'),
(7, 'sdada', 'adad', 222.00, 'Laboratory', 'Active', '2026-08-23 14:07:43', '2026-08-23 14:07:43'),
(8, 'sda', 'adas', 222.00, 'Laboratory', 'Active', '2026-08-23 14:11:57', '2026-08-23 14:11:57');

-- --------------------------------------------------------

--
-- Table structure for table `service_requirements`
--

CREATE TABLE `service_requirements` (
  `id` int(11) NOT NULL,
  `service_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity_used` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `service_requirements`
--

INSERT INTO `service_requirements` (`id`, `service_id`, `product_id`, `quantity_used`, `created_at`) VALUES
(5, 8, 31, 1, '2026-08-23 14:12:02'),
(6, 5, 26, 1, '2026-08-23 14:12:30'),
(7, 2, 26, 1, '2026-08-23 14:54:45');

-- --------------------------------------------------------

--
-- Table structure for table `staff_accounts`
--

CREATE TABLE `staff_accounts` (
  `id` int(11) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `role` varchar(50) NOT NULL DEFAULT 'staff',
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_accounts`
--

INSERT INTO `staff_accounts` (`id`, `full_name`, `email`, `password`, `status`, `role`, `created_at`) VALUES
(4, 'TELE-CARE Staff', 'staff@telecare.com', '$2y$10$YnlzuMjQm2ntktIn7faJ9eOWpWDQwGxNhLOk5hmquxEvebnJ/Ko7G', 'active', 'staff', '2026-03-22 15:47:28');

-- --------------------------------------------------------

--
-- Table structure for table `super_admins`
--

CREATE TABLE `super_admins` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `super_admins`
--

INSERT INTO `super_admins` (`id`, `full_name`, `email`, `password`, `created_at`) VALUES
(1, 'Super Admin', 'superadmin@telecare.com', '$2y$10$dq8gzjM4wCAHe0Z8wPUqpuek.39tc6ydQENkeuWKA1yZe.PrvdJh2', '2026-09-06 14:25:10');

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
  ADD UNIQUE KEY `uniq_reference_no` (`reference_no`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `appointment_document_drafts`
--
ALTER TABLE `appointment_document_drafts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_appt_doc` (`appointment_id`,`doc_type`),
  ADD KEY `idx_appointment_id` (`appointment_id`);

--
-- Indexes for table `appointment_hmo`
--
ALTER TABLE `appointment_hmo`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_hmo_appt` (`appointment_id`);

--
-- Indexes for table `appointment_hmo_docs`
--
ALTER TABLE `appointment_hmo_docs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hmo_docs_appt` (`appointment_id`);

--
-- Indexes for table `appointment_logs`
--
ALTER TABLE `appointment_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `appointment_id` (`appointment_id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `appointment_yakap`
--
ALTER TABLE `appointment_yakap`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_yakap_appt` (`appointment_id`);

--
-- Indexes for table `app_login_handoffs`
--
ALTER TABLE `app_login_handoffs`
  ADD PRIMARY KEY (`token_hash`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_id` (`admin_id`),
  ADD KEY `entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `discounts`
--
ALTER TABLE `discounts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Indexes for table `doctor_ratings`
--
ALTER TABLE `doctor_ratings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_patient_doctor` (`patient_id`,`doctor_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `doctor_schedule_requests`
--
ALTER TABLE `doctor_schedule_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doctor_status` (`doctor_id`,`status`);

--
-- Indexes for table `doctor_schedule_settings`
--
ALTER TABLE `doctor_schedule_settings`
  ADD PRIMARY KEY (`doctor_id`);

--
-- Indexes for table `document_templates`
--
ALTER TABLE `document_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_doc_type` (`doc_type`);

--
-- Indexes for table `document_templates_old`
--
ALTER TABLE `document_templates_old`
  ADD PRIMARY KEY (`doc_type`);

--
-- Indexes for table `guest_bookings`
--
ALTER TABLE `guest_bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_gb_appt` (`appointment_id`),
  ADD UNIQUE KEY `uniq_gb_token` (`access_token`),
  ADD KEY `idx_gb_patient` (`patient_id`);

--
-- Indexes for table `guest_files`
--
ALTER TABLE `guest_files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gf_appt` (`appointment_id`);

--
-- Indexes for table `guest_mail_log`
--
ALTER TABLE `guest_mail_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_gm_email` (`email`,`created_at`),
  ADD KEY `idx_gm_ip` (`ip`,`created_at`);

--
-- Indexes for table `hmo_access_log`
--
ALTER TABLE `hmo_access_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hal_actor` (`actor_type`,`actor_id`),
  ADD KEY `idx_hal_appt` (`appointment_id`);

--
-- Indexes for table `hmo_case_events`
--
ALTER TABLE `hmo_case_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hmo_events_appt` (`appointment_id`,`id`);

--
-- Indexes for table `hmo_contact_log`
--
ALTER TABLE `hmo_contact_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hmo_contact_appt` (`appointment_id`);

--
-- Indexes for table `hmo_coverage`
--
ALTER TABLE `hmo_coverage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `provider_id` (`provider_id`);

--
-- Indexes for table `hmo_providers`
--
ALTER TABLE `hmo_providers`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `hmo_provider_contacts`
--
ALTER TABLE `hmo_provider_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_hpc_provider` (`provider_id`);

--
-- Indexes for table `hmo_provider_doctors`
--
ALTER TABLE `hmo_provider_doctors`
  ADD PRIMARY KEY (`provider_id`,`doctor_id`);

--
-- Indexes for table `hmo_rules`
--
ALTER TABLE `hmo_rules`
  ADD PRIMARY KEY (`provider_id`);

--
-- Indexes for table `hmo_staff_permissions`
--
ALTER TABLE `hmo_staff_permissions`
  ADD PRIMARY KEY (`staff_id`);

--
-- Indexes for table `issued_documents`
--
ALTER TABLE `issued_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_doc_no` (`doc_no`),
  ADD KEY `idx_patient` (`patient_id`),
  ADD KEY `idx_appt` (`appointment_id`);

--
-- Indexes for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`);

--
-- Indexes for table `legal_policies`
--
ALTER TABLE `legal_policies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`);

--
-- Indexes for table `legal_policy_versions`
--
ALTER TABLE `legal_policy_versions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `policy_id` (`policy_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_notif` (`patient_id`,`type`,`reference_id`),
  ADD KEY `idx_patient_read` (`patient_id`,`is_read`),
  ADD KEY `idx_patient_created` (`patient_id`,`created_at`);

--
-- Indexes for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_notif_staff` (`notification_id`,`staff_id`),
  ADD KEY `idx_staff_id` (`staff_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uq_google_id` (`google_id`);

--
-- Indexes for table `pos_sales`
--
ALTER TABLE `pos_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `staff_id` (`staff_id`);

--
-- Indexes for table `pos_sale_items`
--
ALTER TABLE `pos_sale_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sale_id` (`sale_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `doctor_id` (`doctor_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `receipt_settings`
--
ALTER TABLE `receipt_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `service_requirements`
--
ALTER TABLE `service_requirements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_service_product` (`service_id`,`product_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `staff_accounts`
--
ALTER TABLE `staff_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `super_admins`
--
ALTER TABLE `super_admins`
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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `appointment_document_drafts`
--
ALTER TABLE `appointment_document_drafts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `appointment_hmo`
--
ALTER TABLE `appointment_hmo`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `appointment_hmo_docs`
--
ALTER TABLE `appointment_hmo_docs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `appointment_logs`
--
ALTER TABLE `appointment_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `appointment_yakap`
--
ALTER TABLE `appointment_yakap`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

--
-- AUTO_INCREMENT for table `discounts`
--
ALTER TABLE `discounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `doctor_ratings`
--
ALTER TABLE `doctor_ratings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=158;

--
-- AUTO_INCREMENT for table `doctor_schedule_requests`
--
ALTER TABLE `doctor_schedule_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `document_templates`
--
ALTER TABLE `document_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `guest_bookings`
--
ALTER TABLE `guest_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `guest_files`
--
ALTER TABLE `guest_files`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guest_mail_log`
--
ALTER TABLE `guest_mail_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `hmo_access_log`
--
ALTER TABLE `hmo_access_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `hmo_case_events`
--
ALTER TABLE `hmo_case_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `hmo_contact_log`
--
ALTER TABLE `hmo_contact_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hmo_coverage`
--
ALTER TABLE `hmo_coverage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hmo_providers`
--
ALTER TABLE `hmo_providers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `hmo_provider_contacts`
--
ALTER TABLE `hmo_provider_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `issued_documents`
--
ALTER TABLE `issued_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lab_results`
--
ALTER TABLE `lab_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `legal_policies`
--
ALTER TABLE `legal_policies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `legal_policy_versions`
--
ALTER TABLE `legal_policy_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3142;

--
-- AUTO_INCREMENT for table `notification_reads`
--
ALTER TABLE `notification_reads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `pos_sales`
--
ALTER TABLE `pos_sales`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pos_sale_items`
--
ALTER TABLE `pos_sale_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `service_requirements`
--
ALTER TABLE `service_requirements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `staff_accounts`
--
ALTER TABLE `staff_accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `super_admins`
--
ALTER TABLE `super_admins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- Constraints for table `appointment_document_drafts`
--
ALTER TABLE `appointment_document_drafts`
  ADD CONSTRAINT `appointment_document_drafts_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_hmo`
--
ALTER TABLE `appointment_hmo`
  ADD CONSTRAINT `appointment_hmo_fk` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_hmo_docs`
--
ALTER TABLE `appointment_hmo_docs`
  ADD CONSTRAINT `appointment_hmo_docs_fk` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_logs`
--
ALTER TABLE `appointment_logs`
  ADD CONSTRAINT `appointment_logs_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_logs_ibfk_2` FOREIGN KEY (`staff_id`) REFERENCES `staff_accounts` (`id`);

--
-- Constraints for table `appointment_yakap`
--
ALTER TABLE `appointment_yakap`
  ADD CONSTRAINT `appointment_yakap_fk` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`verified_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `doctor_ratings`
--
ALTER TABLE `doctor_ratings`
  ADD CONSTRAINT `doctor_ratings_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `doctor_ratings_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_schedules`
--
ALTER TABLE `doctor_schedules`
  ADD CONSTRAINT `doctor_schedules_ibfk_1` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_schedule_settings`
--
ALTER TABLE `doctor_schedule_settings`
  ADD CONSTRAINT `doctor_schedule_settings_doctor_fk` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hmo_case_events`
--
ALTER TABLE `hmo_case_events`
  ADD CONSTRAINT `hmo_case_events_fk` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hmo_contact_log`
--
ALTER TABLE `hmo_contact_log`
  ADD CONSTRAINT `hmo_contact_log_fk` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hmo_coverage`
--
ALTER TABLE `hmo_coverage`
  ADD CONSTRAINT `hmo_coverage_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `hmo_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hmo_provider_contacts`
--
ALTER TABLE `hmo_provider_contacts`
  ADD CONSTRAINT `hmo_provider_contacts_fk` FOREIGN KEY (`provider_id`) REFERENCES `hmo_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hmo_provider_doctors`
--
ALTER TABLE `hmo_provider_doctors`
  ADD CONSTRAINT `hmo_provider_doctors_fk` FOREIGN KEY (`provider_id`) REFERENCES `hmo_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hmo_rules`
--
ALTER TABLE `hmo_rules`
  ADD CONSTRAINT `hmo_rules_fk` FOREIGN KEY (`provider_id`) REFERENCES `hmo_providers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lab_results`
--
ALTER TABLE `lab_results`
  ADD CONSTRAINT `lab_results_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `legal_policy_versions`
--
ALTER TABLE `legal_policy_versions`
  ADD CONSTRAINT `fk_policy_version_policy` FOREIGN KEY (`policy_id`) REFERENCES `legal_policies` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notification_reads`
--
ALTER TABLE `notification_reads`
  ADD CONSTRAINT `fk_notification_reads_notification` FOREIGN KEY (`notification_id`) REFERENCES `notifications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `prescriptions_ibfk_2` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `service_requirements`
--
ALTER TABLE `service_requirements`
  ADD CONSTRAINT `service_requirements_ibfk_1` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `service_requirements_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
