-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 15, 2025 at 09:44 AM
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
  `initiator_id` int(11) NOT NULL,
  `current_owner_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(50, 56, 2, 'REGIONAL OFFICE', 'RO PMD', '2025-11-20 09:05:14', NULL, NULL, 31, 'MARY KATHLEEN P. PO'),
(51, 915, 1, 'REGIONAL OFFICE', 'REGIONAL OFFICE', '2025-11-20 09:05:14', NULL, NULL, 31, 'Claudio A. Nistal, Jr.'),
(52, 781, 1, 'REGIONAL OFFICE', 'RO SMD', '2025-11-23 08:32:53', NULL, NULL, 32, 'Juliet B. Ilogon'),
(53, 915, 2, 'REGIONAL OFFICE', 'REGIONAL OFFICE', '2025-11-23 08:32:53', NULL, NULL, 32, 'Claudio A. Nistal, Jr.'),
(54, 915, 2, 'REGIONAL OFFICE', 'REGIONAL OFFICE', '2025-11-23 08:35:18', NULL, NULL, 34, 'Claudio A. Nistal, Jr.'),
(55, 781, 1, 'REGIONAL OFFICE', 'RO SMD', '2025-11-23 08:35:18', NULL, NULL, 34, 'Juliet B. Ilogon'),
(56, 52, 1, 'REGIONAL OFFICE', 'RO ARD', '2025-12-14 03:42:55', NULL, NULL, 35, 'ATTY. CLAUDIO A. NISTAL JR.'),
(57, 53, 2, 'REGIONAL OFFICE', 'RO ORED', '2025-12-14 03:42:55', NULL, NULL, 35, 'MARITESS M. OCAMPO');

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
(3, 'venzonanthonie@gmail.com', '$2y$10$d86o9GS8gWa5ikqzqeW4.u2hHldY5wFQtTMQSAvRnhpIhPV936twK', 'ANTHONIE FENY', 'VENZON', 'CATALAN', '', 'FOREST RANGER', 'COMPUTER PROGRAMMER', 'PLANNING MANAGEMENT', 'Male', '09478984921', 'Admin', 'active', NULL, NULL, NULL, NULL, '2025-11-18 05:07:15', 0, 'uploads/profile_pics/3_d8a0add5e7a4.png'),
(7, 'yowaf85723@naqulu.com', '$2y$10$rdfGhCcVTMDql9EXMBj3vON3FD2dYNXzS7zh3xbCwFzc2iUwMAfKq', 'MARY KATHLEEN', 'KATHLEEN P.', 'PO', '', 'PLANNING V', 'CHIEF PMD', 'PLANNNING AND MANAGEMENT DIVISION', 'Female', '09329342620', 'Initiator', 'active', 'b30c41b2e656af6a8de8f5e1a9533b525f0360bee1fcd44535f75a7fde955b2c', '2025-12-10 23:57:26', NULL, NULL, '2025-12-10 21:57:26', 56, 'uploads/profile_pics/7_cbd67e9f808b.jpg'),
(52, 'claudio.nistal.atty@denr.gov.ph', '$2y$10$X2FBRhDUYoj1W7uxCPHEaewstU7CREPtohbk9a1jXszNIo17nDEIu', 'CLAUDIO', NULL, 'NISTAL JR.', NULL, '', NULL, '', NULL, NULL, 'ARD', 'active', NULL, NULL, NULL, NULL, '2025-12-15 00:45:36', 915, NULL);

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
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `document_actions`
--
ALTER TABLE `document_actions`
  MODIFY `action_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `document_files`
--
ALTER TABLE `document_files`
  MODIFY `file_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `document_signatories`
--
ALTER TABLE `document_signatories`
  MODIFY `doc_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `office_station`
--
ALTER TABLE `office_station`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=916;

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
