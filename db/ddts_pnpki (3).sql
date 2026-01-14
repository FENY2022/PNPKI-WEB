-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 14, 2026 at 09:29 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ddts_pnpki`
--

-- --------------------------------------------------------

--
-- Table structure for table `documents`
--

CREATE TABLE `documents` (
  `doc_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `doc_type` varchar(100) NOT NULL,
  `status` enum('Draft','Review','Signing','Completed','Returned','Archived') NOT NULL,
  `remarks` text DEFAULT NULL,
  `initiator_id` int(11) NOT NULL,
  `current_owner_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `documents`
--

INSERT INTO `documents` (`doc_id`, `title`, `doc_type`, `status`, `remarks`, `initiator_id`, `current_owner_id`, `created_at`, `updated_at`) VALUES
(17, 'MEMO', 'Memorandum', '', NULL, 7, 52, '2026-01-06 00:13:00', '2026-01-06 00:13:00'),
(20, 'MEMO', 'Memorandum', '', NULL, 7, 52, '2026-01-06 01:21:26', '2026-01-06 01:21:26'),
(26, 'MEMO', 'Memorandum', '', NULL, 918, 3, '2026-01-07 07:08:49', '2026-01-07 07:08:49'),
(35, 'MEMO', 'Memorandum', '', NULL, 7, 52, '2026-01-07 07:26:32', '2026-01-07 07:26:32'),
(43, 'MEMO', 'Memorandum', '', NULL, 918, 52, '2026-01-08 06:44:07', '2026-01-08 06:44:07'),
(44, 'MEMO', 'Memorandum', 'Draft', NULL, 918, 7, '2026-01-08 07:20:40', '2026-01-08 07:20:40'),
(45, 'MEMO', 'Memorandum', '', NULL, 918, 7, '2026-01-08 07:57:29', '2026-01-08 07:57:29'),
(46, 'MEMO', 'Memorandum', 'Review', NULL, 918, 7, '2026-01-08 07:58:59', '2026-01-14 08:28:43');

-- --------------------------------------------------------

--
-- Table structure for table `document_actions`
--

CREATE TABLE `document_actions` (
  `action_id` int(11) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL COMMENT 'e.g., Submitted, Drafted, Approved, Signed, Returned',
  `message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_actions`
--

INSERT INTO `document_actions` (`action_id`, `doc_id`, `user_id`, `action`, `message`, `created_at`) VALUES
(6, 17, 7, 'Submitted', 'DSFSDFSDFDSFSDF', '2026-01-06 00:13:00'),
(9, 20, 7, 'Submitted', 'sdfsdfgfdgfdg', '2026-01-06 01:21:26'),
(10, 20, 52, 'Edited', 'Edited document online (Version 2)', '2026-01-07 01:53:00'),
(11, 20, 52, 'Edited', 'Edited document online (v3)', '2026-01-07 04:27:43'),
(12, 20, 52, 'Edited', 'Edited document online (v4)', '2026-01-07 06:03:07'),
(13, 20, 52, 'Edited', 'Edited document online (v5)', '2026-01-07 06:03:41'),
(14, 20, 52, 'Edited', 'Edited document online (v6)', '2026-01-07 06:04:30'),
(15, 20, 52, 'Edited', 'Edited document online (v7)', '2026-01-07 06:09:22'),
(16, 20, 52, 'Edited', 'Edited document online (v8)', '2026-01-07 06:11:39'),
(17, 20, 52, 'Edited', 'Edited document online (v9)', '2026-01-07 06:15:29'),
(18, 20, 52, 'Edited', 'Edited document online (v10)', '2026-01-07 06:15:57'),
(19, 20, 52, 'Edited', 'Edited document online (v11)', '2026-01-07 06:20:19'),
(20, 26, 918, 'Submitted', 'sgffdg', '2026-01-07 07:08:49'),
(21, 35, 7, 'Submitted', 'fsdfsdf', '2026-01-07 07:26:32'),
(22, 43, 918, 'Submitted', 'sdsdsd', '2026-01-08 06:44:07'),
(23, 44, 918, 'Saved as Draft', '', '2026-01-08 07:20:40'),
(24, 45, 918, 'Submitted', '', '2026-01-08 07:57:29'),
(25, 46, 918, 'Submitted', '', '2026-01-08 07:58:59'),
(26, 46, 7, 'Returned Document', 'PLEASE FSDFSDFSDFSDFDSFDS', '2026-01-08 07:59:46');

-- --------------------------------------------------------

--
-- Table structure for table `document_files`
--

CREATE TABLE `document_files` (
  `file_id` int(11) NOT NULL,
  `doc_id` int(11) NOT NULL,
  `uploader_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL COMMENT 'Original file name',
  `filepath` varchar(255) NOT NULL COMMENT 'Stored file path',
  `version` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_files`
--

INSERT INTO `document_files` (`file_id`, `doc_id`, `uploader_id`, `filename`, `filepath`, `version`, `created_at`) VALUES
(6, 17, 7, 'AGREEMENTS-REACHED-DURING-FY-2025-DENR-CARAGA-PLANNING-AND-ICT-YEAR-END-ASSESSMENT.pdf', 'uploads/doc_695c538c6b2ea6.93298600.pdf', 1, '2026-01-06 00:13:00'),
(9, 20, 7, 'TA-MONTHLY-4th-Quarter.docx', 'uploads/doc_695c639679d0a3.30783270.docx', 1, '2026-01-06 01:21:26'),
(10, 20, 52, 'edited_695dbc7c4b78f9.89729473.docx', 'uploads/edited_695dbc7c4b78f9.89729473.docx', 2, '2026-01-07 01:53:00'),
(11, 20, 52, 'edited_695de0bf08bed7.33975095.docx', 'uploads/edited_695de0bf08bed7.33975095.docx', 3, '2026-01-07 04:27:43'),
(12, 20, 52, 'edited_695df71ba34481.40111491.docx', 'uploads/edited_695df71ba34481.40111491.docx', 4, '2026-01-07 06:03:07'),
(13, 20, 52, 'edited_695df73d44d649.32096814.docx', 'uploads/edited_695df73d44d649.32096814.docx', 5, '2026-01-07 06:03:41'),
(14, 20, 52, 'edited_695df76e18c070.60754142.docx', 'uploads/edited_695df76e18c070.60754142.docx', 6, '2026-01-07 06:04:30'),
(15, 20, 52, 'edited_695df8926cdae4.36047048.docx', 'uploads/edited_695df8926cdae4.36047048.docx', 7, '2026-01-07 06:09:22'),
(16, 20, 52, 'edited_695df91b491f45.30333785.docx', 'uploads/edited_695df91b491f45.30333785.docx', 8, '2026-01-07 06:11:39'),
(17, 20, 52, 'edited_695dfa00ed0d90.54458357.docx', 'uploads/edited_695dfa00ed0d90.54458357.docx', 9, '2026-01-07 06:15:29'),
(18, 20, 52, 'edited_695dfa1d522695.45994760.docx', 'uploads/edited_695dfa1d522695.45994760.docx', 10, '2026-01-07 06:15:57'),
(19, 20, 52, 'edited_695dfb239cf7e9.03355130.docx', 'uploads/edited_695dfb239cf7e9.03355130.docx', 11, '2026-01-07 06:20:19'),
(20, 26, 918, 'RSO-N0.-861-REGONAL-LAWIN-PATROL-TEAM-FOR-THE-1ST-QUARTER-CY-2026.pdf', 'uploads/doc_695e0681e3a285.71787659.pdf', 1, '2026-01-07 07:08:49'),
(21, 35, 7, 'RSO-N0.-861-REGONAL-LAWIN-PATROL-TEAM-FOR-THE-1ST-QUARTER-CY-2026.pdf', 'uploads/doc_695e0aa84a72b1.55630933.pdf', 1, '2026-01-07 07:26:32'),
(22, 43, 918, 'LAS_Science 3_Q3_Week5_RTP (2).pdf', 'uploads/doc_695f52373b9033.52673515.pdf', 1, '2026-01-08 06:44:07'),
(23, 44, 918, 'LAS_ENGLISH3_Q3_WEEK5 (1).pdf', 'uploads/doc_695f5ac833c453.20241906.pdf', 1, '2026-01-08 07:20:40'),
(24, 45, 918, 'LAS_Science 3_Q3_Week5_RTP (2).pdf', 'uploads/doc_695f63692da008.11952935.pdf', 1, '2026-01-08 07:57:29'),
(25, 46, 918, 'LAS_Science 3_Q3_Week5_RTP (2).pdf', 'uploads/doc_695f63c3536486.88476545.pdf', 1, '2026-01-08 07:58:59');

-- --------------------------------------------------------

--
-- Table structure for table `document_signatories`
--

CREATE TABLE `document_signatories` (
  `doc_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `signing_order` int(11) NOT NULL,
  `office_assigned` varchar(255) DEFAULT NULL,
  `station_assigned` varchar(255) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `office_id` int(11) DEFAULT NULL,
  `station_id` int(11) DEFAULT NULL,
  `batch_id` int(11) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `document_signatories`
--

INSERT INTO `document_signatories` (`doc_id`, `user_id`, `signing_order`, `office_assigned`, `station_assigned`, `assigned_at`, `office_id`, `station_id`, `batch_id`, `full_name`) VALUES
(52, 781, 1, 'REGIONAL OFFICE', 'RO SMD', '2025-11-23 08:32:53', NULL, NULL, 32, 'Juliet B. Ilogon'),
(53, 915, 2, 'REGIONAL OFFICE', 'REGIONAL OFFICE', '2025-11-23 08:32:53', NULL, NULL, 32, 'Claudio A. Nistal, Jr.'),
(54, 915, 2, 'REGIONAL OFFICE', 'REGIONAL OFFICE', '2025-11-23 08:35:18', NULL, NULL, 34, 'Claudio A. Nistal, Jr.'),
(55, 781, 1, 'REGIONAL OFFICE', 'RO SMD', '2025-11-23 08:35:18', NULL, NULL, 34, 'Juliet B. Ilogon'),
(56, 52, 1, 'REGIONAL OFFICE', 'RO ARD', '2025-12-14 03:42:55', NULL, NULL, 35, 'ATTY. CLAUDIO A. NISTAL JR.'),
(57, 53, 2, 'REGIONAL OFFICE', 'RO ORED', '2025-12-14 03:42:55', NULL, NULL, 35, 'MARITESS M. OCAMPO'),
(65, 56, 1, 'REGIONAL OFFICE', 'RO PMD', '2026-01-08 01:54:37', NULL, NULL, 31, 'MARY KATHLEEN P. PO'),
(66, 52, 2, 'REGIONAL OFFICE', 'RO ARD', '2026-01-08 01:54:37', NULL, NULL, 31, 'ATTY. CLAUDIO A. NISTAL JR.'),
(67, 53, 3, 'REGIONAL OFFICE', 'RO ORED', '2026-01-08 01:54:37', NULL, NULL, 31, 'MARITESS M. OCAMPO');

-- --------------------------------------------------------

--
-- Table structure for table `office_station`
--

CREATE TABLE `office_station` (
  `id` int(11) NOT NULL,
  `office` varchar(255) NOT NULL,
  `station` varchar(255) NOT NULL,
  `service_start` date DEFAULT NULL,
  `service_end` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `office_station`
--

INSERT INTO `office_station` (`id`, `office`, `station`, `service_start`, `service_end`) VALUES
(31, 'REGIONAL OFFICE', 'RO PMD', NULL, NULL),
(32, 'REGIONAL OFFICE', 'RO ASD', NULL, NULL),
(34, 'REGIONAL OFFICE', 'RO SMD', NULL, NULL),
(35, 'REGIONAL OFFICE', 'RO MS', '2025-12-01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `middle_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) NOT NULL,
  `suffix` varchar(20) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `division` varchar(100) DEFAULT NULL,
  `sex` enum('Male','Female','Other','Prefer not to say') DEFAULT NULL,
  `contact_number` varchar(50) DEFAULT NULL,
  `role` enum('Initiator','Section Chief','Division Chief','ARD','RED','Records Office','Admin') NOT NULL DEFAULT 'Initiator',
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `verification_token` varchar(255) DEFAULT NULL,
  `token_expiry` datetime DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expiry` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `otos_userlink` int(11) NOT NULL,
  `profile_picture_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password_hash`, `first_name`, `middle_name`, `last_name`, `suffix`, `position`, `designation`, `division`, `sex`, `contact_number`, `role`, `status`, `verification_token`, `token_expiry`, `reset_token`, `reset_token_expiry`, `created_at`, `otos_userlink`, `profile_picture_path`) VALUES
(3, 'venzonanthonie@gmail.com', '$2y$10$2yFEiICumEZvkyace6AQy.SK1tdlQk6ktHIElkW3pp.uE.YS8Zd8i', 'ANTHONIE FENY', 'VENZON', 'CATALAN', '', 'FOREST RANGER', 'COMPUTER PROGRAMMER', 'PLANNING MANAGEMENT', 'Male', '09478984921', 'Admin', 'active', NULL, NULL, 'c1c1dd1036a6b665aea587976217e6819c110c7a912302488a2d849f6ffd5316', '2026-01-08 16:40:30', '2025-11-18 05:07:15', 57, 'uploads/profile_pics/3_d8a0add5e7a4.png'),
(7, 'yowaf85723@naqulu.com', '$2y$10$rdfGhCcVTMDql9EXMBj3vON3FD2dYNXzS7zh3xbCwFzc2iUwMAfKq', 'MARY KATHLEEN', 'KATHLEEN P.', 'PO', '', 'PLANNING V', 'CHIEF PMD', 'PLANNNING AND MANAGEMENT DIVISION', 'Female', '09329342620', 'Section Chief', 'active', 'b30c41b2e656af6a8de8f5e1a9533b525f0360bee1fcd44535f75a7fde955b2c', '2025-12-10 23:57:26', NULL, NULL, '2025-12-10 21:57:26', 56, 'uploads/profile_pics/7_cbd67e9f808b.jpg'),
(52, 'claudio.nistal.atty@denr.gov.ph', '$2y$10$X2FBRhDUYoj1W7uxCPHEaewstU7CREPtohbk9a1jXszNIo17nDEIu', 'CLAUDIO', NULL, 'NISTAL JR.', NULL, '', NULL, '', NULL, NULL, 'ARD', 'active', NULL, NULL, NULL, NULL, '2025-12-15 00:45:36', 52, NULL),
(918, 'jayson.dpaloquia@gmail.com', '$2y$10$EIBmBslB.Cpq1eUQ3ICt6etf58eICcWnJk.5D3Z/owMEUVynoDikG', 'JAYSON', 'D', 'PALOQUIA', '', 'Statistician II', 'PPS STAFF', 'PLANNING AND MANAGEMENT DIVISION', 'Male', '09502889240', 'Initiator', 'active', NULL, NULL, NULL, NULL, '2026-01-06 03:49:09', 974, NULL),
(922, 'wenana1673@gavrom.com', '$2y$10$jmrWfBKI3NaI60lGPrDVz.5ndk1EBBHtgIFgE5k.BaNh.huEfhl4O', 'MARITES', 'M', 'OCAMPO', '', 'OIC, REGIONAL EXECUTIVE DIRECTOR', 'OIC, REGIONAL EXECUTIVE DIRECTOR', 'ORED', '', '09329342620', 'RED', 'active', NULL, NULL, NULL, NULL, '2026-01-08 07:55:18', 53, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`doc_id`),
  ADD KEY `initiator_id` (`initiator_id`),
  ADD KEY `current_owner_id` (`current_owner_id`);

--
-- Indexes for table `document_actions`
--
ALTER TABLE `document_actions`
  ADD PRIMARY KEY (`action_id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `document_files`
--
ALTER TABLE `document_files`
  ADD PRIMARY KEY (`file_id`),
  ADD KEY `doc_id` (`doc_id`),
  ADD KEY `uploader_id` (`uploader_id`);

--
-- Indexes for table `document_signatories`
--
ALTER TABLE `document_signatories`
  ADD PRIMARY KEY (`doc_id`,`signing_order`);

--
-- Indexes for table `office_station`
--
ALTER TABLE `office_station`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `documents`
--
ALTER TABLE `documents`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `document_actions`
--
ALTER TABLE `document_actions`
  MODIFY `action_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `document_files`
--
ALTER TABLE `document_files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `document_signatories`
--
ALTER TABLE `document_signatories`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- AUTO_INCREMENT for table `office_station`
--
ALTER TABLE `office_station`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=923;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`initiator_id`) REFERENCES `users` (`user_id`),
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`current_owner_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `document_actions`
--
ALTER TABLE `document_actions`
  ADD CONSTRAINT `document_actions_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`doc_id`),
  ADD CONSTRAINT `document_actions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`);

--
-- Constraints for table `document_files`
--
ALTER TABLE `document_files`
  ADD CONSTRAINT `document_files_ibfk_1` FOREIGN KEY (`doc_id`) REFERENCES `documents` (`doc_id`),
  ADD CONSTRAINT `document_files_ibfk_2` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`user_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
