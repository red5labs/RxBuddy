-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 19, 2025 at 02:36 AM
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
-- Database: `rxbuddy`
--

-- --------------------------------------------------------

--
-- Table structure for table `email_logs`
--

CREATE TABLE `email_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email_address` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `status` enum('sent','failed') NOT NULL,
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_logs`
--

INSERT INTO `email_logs` (`id`, `user_id`, `email_address`, `subject`, `status`, `error_message`, `sent_at`) VALUES
(1, 1, 'user@example.com', 'Medication Reminder: Aspirin', 'sent', NULL, '2025-07-17 23:03:19');

-- --------------------------------------------------------

--
-- Table structure for table `emergency_contacts`
--

CREATE TABLE `emergency_contacts` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `relationship` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `logs`
--

CREATE TABLE `logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `medication_id` int(11) NOT NULL,
  `taken_at` datetime NOT NULL,
  `method` varchar(20) DEFAULT 'manual',
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logs`
--

INSERT INTO `logs` (`id`, `user_id`, `medication_id`, `taken_at`, `method`, `notes`) VALUES
(5, 1, 1, '2025-07-17 19:12:54', 'manual', ''),
(6, 1, 2, '2025-07-17 19:32:39', 'manual', ''),
(7, 1, 2, '2025-07-17 19:53:45', 'manual', ''),
(8, 1, 4, '2025-07-18 20:31:25', 'manual', ''),
(9, 1, 3, '2025-07-18 20:31:28', 'manual', ''),
(10, 1, 1, '2025-07-18 20:31:30', 'manual', ''),
(11, 1, 2, '2025-07-18 20:31:33', 'manual', '');

-- --------------------------------------------------------

--
-- Table structure for table `medications`
--

CREATE TABLE `medications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `dosage` varchar(50) NOT NULL,
  `frequency` varchar(100) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `reminder_enabled` tinyint(1) DEFAULT 0,
  `reminder_offset_minutes` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `medications`
--

INSERT INTO `medications` (`id`, `user_id`, `name`, `dosage`, `frequency`, `start_date`, `end_date`, `notes`, `photo_url`, `is_active`, `reminder_enabled`, `reminder_offset_minutes`) VALUES
(1, 1, 'Aspirin', '300mg', 'Once daily', '2025-07-16', '2025-07-31', 'This is the notes section.', 'uploads/pills/pill_1_1752884665_687ae5b9ca593.png', 1, 1, 5),
(2, 1, 'motrin', '500mg', 'Once Daily', '2025-07-17', '2025-07-31', '', NULL, 1, 1, 0),
(3, 1, 'Tylenol', '800mg', 'Once daily', '2025-07-18', '2025-07-31', '\nStopped: Testing archive', NULL, 0, 1, 0),
(4, 1, 'xyzal', '200mg', 'Once daily', '2025-07-18', '2025-07-31', '', NULL, 1, 1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `reminders`
--

CREATE TABLE `reminders` (
  `id` int(11) NOT NULL,
  `medication_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `scheduled_time` datetime NOT NULL,
  `reminder_type` enum('email','sms') DEFAULT 'email',
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reminders`
--

INSERT INTO `reminders` (`id`, `medication_id`, `user_id`, `scheduled_time`, `reminder_type`, `status`, `sent_at`, `error_message`, `created_at`) VALUES
(1, 1, 1, '2025-07-18 18:50:00', 'email', 'pending', NULL, NULL, '2025-07-17 23:00:07'),
(2, 2, 1, '2025-07-18 18:55:00', 'email', 'pending', NULL, NULL, '2025-07-17 23:00:07'),
(3, 1, 1, '2025-07-17 19:03:11', 'email', 'sent', '2025-07-17 19:03:19', NULL, '2025-07-17 23:01:11');

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `medication_id` int(11) NOT NULL,
  `time_of_day` time DEFAULT NULL,
  `interval_hours` int(11) DEFAULT NULL,
  `custom_repeat` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `medication_id`, `time_of_day`, `interval_hours`, `custom_repeat`) VALUES
(1, 1, '18:55:00', NULL, NULL),
(2, 2, '18:55:00', NULL, NULL),
(3, 3, '07:30:00', NULL, NULL),
(4, 4, '08:30:00', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `shared_profiles`
--

CREATE TABLE `shared_profiles` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `share_name` varchar(255) NOT NULL,
  `share_email` varchar(255) NOT NULL,
  `share_type` enum('caregiver','family','healthcare_provider','other') NOT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`permissions`)),
  `share_token` varchar(64) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shared_profiles`
--

INSERT INTO `shared_profiles` (`id`, `user_id`, `share_name`, `share_email`, `share_type`, `permissions`, `share_token`, `is_active`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'Share Test', 'user@gmail.com', 'family', '{\"view_medications\":true,\"view_logs\":true,\"view_calendar\":true,\"receive_alerts\":true}', '7570db49f6991d5c324c1f27be344d8771ec6848efed6c17928713e99adf5556', 0, '2025-07-31 23:59:59', '2025-07-18 22:32:04', '2025-07-18 22:35:38'),
(2, 1, 'Share Test', 'user@gmail.com', 'family', '{\"view_medications\":true,\"view_logs\":true,\"view_calendar\":true,\"receive_alerts\":true}', '1a8f9410cb038c8780a4ef006b465c41694b9d26689e2d08dc5adfd49d824ae6', 0, '2025-07-31 23:59:59', '2025-07-18 22:39:16', '2025-07-18 22:50:08'),
(3, 1, 'Share Test', 'user@gmail.com', 'family', '{\"view_medications\":true,\"view_logs\":true,\"view_calendar\":true,\"receive_alerts\":true}', '09a14719e810f162de4f2c3422e5ff6dd59583d978eedeb7cb2c0dd4816a5b02', 0, '2025-07-31 23:59:59', '2025-07-18 22:51:46', '2025-07-18 22:57:25'),
(4, 1, 'Share Test', 'user@gmail.com', 'family', '{\"view_medications\":true,\"view_logs\":true,\"view_calendar\":true,\"receive_alerts\":true}', '22d65e93ec7700d66404eb892c5724d25730160f3d9e7c8b21b11f89c90231e9', 1, '2025-07-31 23:59:59', '2025-07-18 22:57:59', '2025-07-18 22:57:59');

-- --------------------------------------------------------

--
-- Table structure for table `shared_profile_access`
--

CREATE TABLE `shared_profile_access` (
  `id` int(11) NOT NULL,
  `shared_profile_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `accessed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shared_profile_access`
--

INSERT INTO `shared_profile_access` (`id`, `shared_profile_id`, `ip_address`, `user_agent`, `accessed_at`) VALUES
(1, 4, '::1', NULL, '2025-07-18 22:59:52'),
(2, 4, '::1', NULL, '2025-07-18 23:01:29'),
(3, 4, '::1', NULL, '2025-07-19 00:35:57');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `email_reminders` tinyint(1) DEFAULT 0,
  `email_verified` tinyint(1) DEFAULT 0,
  `email_verification_token` varchar(255) DEFAULT NULL,
  `email_verification_expires` datetime DEFAULT NULL,
  `sharing_enabled` tinyint(1) DEFAULT 0,
  `emergency_contacts_enabled` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password_hash`, `created_at`, `email_reminders`, `email_verified`, `email_verification_token`, `email_verification_expires`, `sharing_enabled`, `emergency_contacts_enabled`) VALUES
(1, 'User', 'user@example.com', '$2y$10$XmyD3EBh2DvLgKr/.B2Ml.vcr8uFrtL/8KeJmVdtDVRa.7kqFahyq', '2025-07-16 20:56:19', 1, 1, NULL, NULL, 0, 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`);

--
-- Indexes for table `logs`
--
ALTER TABLE `logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medication_id` (`medication_id`),
  ADD KEY `fk_logs_user_id` (`user_id`);

--
-- Indexes for table `medications`
--
ALTER TABLE `medications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `reminders`
--
ALTER TABLE `reminders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_scheduled_time` (`scheduled_time`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_medication_user` (`medication_id`,`user_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `medication_id` (`medication_id`);

--
-- Indexes for table `shared_profiles`
--
ALTER TABLE `shared_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `share_token` (`share_token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_share_token` (`share_token`),
  ADD KEY `idx_share_email` (`share_email`);

--
-- Indexes for table `shared_profile_access`
--
ALTER TABLE `shared_profile_access`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shared_profile_id` (`shared_profile_id`),
  ADD KEY `idx_accessed_at` (`accessed_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `email_logs`
--
ALTER TABLE `email_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `logs`
--
ALTER TABLE `logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `medications`
--
ALTER TABLE `medications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `reminders`
--
ALTER TABLE `reminders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shared_profiles`
--
ALTER TABLE `shared_profiles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `shared_profile_access`
--
ALTER TABLE `shared_profile_access`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `email_logs`
--
ALTER TABLE `email_logs`
  ADD CONSTRAINT `email_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `emergency_contacts`
--
ALTER TABLE `emergency_contacts`
  ADD CONSTRAINT `emergency_contacts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `logs`
--
ALTER TABLE `logs`
  ADD CONSTRAINT `fk_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medications`
--
ALTER TABLE `medications`
  ADD CONSTRAINT `medications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reminders`
--
ALTER TABLE `reminders`
  ADD CONSTRAINT `reminders_ibfk_1` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reminders_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `schedules`
--
ALTER TABLE `schedules`
  ADD CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`medication_id`) REFERENCES `medications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shared_profiles`
--
ALTER TABLE `shared_profiles`
  ADD CONSTRAINT `shared_profiles_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shared_profile_access`
--
ALTER TABLE `shared_profile_access`
  ADD CONSTRAINT `shared_profile_access_ibfk_1` FOREIGN KEY (`shared_profile_id`) REFERENCES `shared_profiles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
