-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 11, 2026 at 09:55 PM
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
-- Database: `uivent_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `active_sessions`
--

CREATE TABLE `active_sessions` (
  `id` int(10) UNSIGNED NOT NULL,
  `admin_id` int(10) UNSIGNED DEFAULT NULL,
  `campus_id` int(10) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `started_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_action` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `session_key` varchar(128) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `campus_id` int(10) UNSIGNED DEFAULT NULL,
  `club_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(120) DEFAULT NULL,
  `club_name` varchar(150) DEFAULT NULL COMMENT 'Public club/society name shown on event pages',
  `avatar` varchar(500) DEFAULT NULL,
  `email` varchar(180) NOT NULL,
  `phone` varchar(30) DEFAULT NULL COMMENT 'Club contact phone number',
  `office_location` varchar(255) DEFAULT NULL COMMENT 'Physical office address / room number',
  `password` varchar(255) DEFAULT NULL,
  `role` enum('HEP Coordinator','Event Coordinator','Attendance Officer') NOT NULL DEFAULT 'Event Coordinator',
  `status` enum('active','pending','suspended','idle') NOT NULL DEFAULT 'pending',
  `invite_token` varchar(80) DEFAULT NULL,
  `last_active` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`id`, `campus_id`, `club_id`, `name`, `club_name`, `avatar`, `email`, `phone`, `office_location`, `password`, `role`, `status`, `invite_token`, `last_active`, `created_at`) VALUES
(6, NULL, 6, 'MPP UiTM Machang', 'MPP UiTM Machang', NULL, 'mpp@machang.uitm.edu.my', '+60199531646', 'Student Centre,Level 1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Event Coordinator', 'active', NULL, '2026-07-03 19:18:22', '2026-07-03 19:18:22'),
(7, NULL, 7, 'JPK Kolej Tun Hussein Onn', 'JPK Kolej Tun Hussein Onn', NULL, 'jpk.tho@machang.uitm.edu.my', NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Event Coordinator', 'suspended', NULL, '2026-07-03 19:18:22', '2026-07-03 19:18:22'),
(8, NULL, 8, 'JPK Kolej Dato Onn', 'JPK Kolej Dato Onn', NULL, 'jpk.do@machang.uitm.edu.my', NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Event Coordinator', 'suspended', NULL, '2026-07-03 19:18:22', '2026-07-03 19:18:22'),
(9, NULL, 9, 'JPK Kolej Tun Abdul Razak', 'JPK Kolej Tun Abdul Razak', NULL, 'jpk.tar@machang.uitm.edu.my', NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Event Coordinator', 'active', NULL, '2026-07-03 19:18:22', '2026-07-03 19:18:22'),
(10, NULL, 10, 'JPK Kolej Tunku Abdul Rahman', 'JPK Kolej Tunku Abdul Rahman', NULL, 'jpk.tunku@machang.uitm.edu.my', NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Event Coordinator', 'active', NULL, '2026-07-03 19:18:22', '2026-07-03 19:18:22'),
(11, NULL, 11, 'JPK Kolej Tun Dr. Mahathir', 'JPK Kolej Tun Dr. Mahathir', NULL, 'jpk.tdm@machang.uitm.edu.my', NULL, NULL, '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Event Coordinator', 'active', NULL, '2026-07-03 19:18:22', '2026-07-03 19:18:22');

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `actor_name` varchar(120) NOT NULL,
  `actor_type` enum('super_admin','admin') NOT NULL DEFAULT 'admin',
  `action` varchar(80) NOT NULL,
  `target` varchar(255) DEFAULT NULL,
  `campus_id` int(10) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `actor_name`, `actor_type`, `action`, `target`, `campus_id`, `ip_address`, `created_at`) VALUES
(1, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-01 21:20:24'),
(2, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-03 13:52:59'),
(3, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-03 15:32:05'),
(4, 'Super Admin', 'super_admin', 'LOGOUT', 'Super Admin session ended', NULL, '::1', '2026-07-03 15:36:54'),
(5, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-03 15:37:56'),
(6, 'Super Admin', 'super_admin', 'LOGOUT', 'Super Admin session ended', NULL, '::1', '2026-07-03 15:38:02'),
(7, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-03 16:51:39'),
(8, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-03 16:57:23'),
(9, 'Super Admin', 'super_admin', 'LOGOUT', 'Super Admin session ended', NULL, '::1', '2026-07-03 17:37:24'),
(10, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-03 17:37:57'),
(11, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-03 17:39:29'),
(12, 'Super Admin', 'super_admin', 'LOGOUT', 'Super Admin session ended', NULL, '::1', '2026-07-03 18:29:03'),
(13, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-03 18:33:08'),
(14, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-03 18:33:22'),
(15, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-03 19:37:26'),
(16, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-03 20:57:32'),
(17, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-03 22:37:37'),
(18, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-03 22:37:54'),
(19, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-04 02:47:23'),
(20, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-04 03:17:22'),
(21, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-04 03:17:44'),
(22, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-04 03:46:01'),
(23, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-04 03:47:34'),
(24, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-05 03:27:51'),
(25, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-06 15:41:00'),
(26, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-06 15:41:53'),
(27, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-06 15:43:48'),
(28, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-06 16:04:28'),
(30, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-07 21:41:07'),
(31, 'Super Admin', 'super_admin', 'EMAIL BROADCAST SENT', 'Subject: \"s\" to 6 recipient(s) (all)', NULL, '::1', '2026-07-08 04:11:51'),
(32, 'Super Admin', 'super_admin', 'EMAIL BROADCAST SENT', 'Subject: \"s\" to 6 recipient(s) (all)', NULL, '::1', '2026-07-08 04:12:03'),
(33, 'Super Admin', 'super_admin', 'EMAIL BROADCAST SENT', 'Subject: \"s\" to 6 recipient(s) (all)', NULL, '::1', '2026-07-08 04:12:16'),
(34, 'Super Admin', 'super_admin', 'EMAIL BROADCAST SENT', 'Subject: \"s\" to 6 recipient(s) (all)', NULL, '::1', '2026-07-08 04:12:28'),
(35, 'Super Admin', 'super_admin', 'EMAIL BROADCAST SENT', 'Subject: \"s\" to 6 recipient(s) (all)', NULL, '::1', '2026-07-08 04:12:40'),
(36, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-08 04:12:55'),
(37, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-08 04:13:35'),
(38, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-08 04:19:39'),
(39, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-08 06:04:34'),
(40, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-08 06:05:12'),
(41, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-08 06:10:12'),
(42, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-08 07:39:48'),
(43, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-08 08:13:52'),
(44, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-08 08:14:05'),
(45, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-08 08:37:59'),
(46, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-08 09:15:35'),
(47, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-08 09:18:15'),
(48, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-08 09:23:06'),
(49, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-08 09:28:47'),
(50, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-08 09:31:57'),
(51, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-08 10:00:57'),
(52, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-08 10:01:41'),
(53, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-08 10:03:05'),
(54, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-08 10:49:18'),
(55, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-08 10:51:13'),
(56, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-08 10:52:39'),
(57, 'Super Admin', 'super_admin', 'SUSPENDED', 'Club ID 7 suspended', NULL, '::1', '2026-07-08 10:53:27'),
(58, 'Super Admin', 'super_admin', 'SUSPEND_STUDENT', 'Student ID: 10', NULL, '::1', '2026-07-08 10:53:44'),
(59, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-08 10:58:24'),
(60, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-08 11:00:42'),
(61, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-08 11:36:34'),
(62, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-08 11:42:34'),
(63, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-08 11:45:36'),
(64, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-08 11:45:57'),
(65, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-08 11:48:47'),
(66, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-11 00:27:45'),
(67, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-11 00:41:46'),
(68, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-11 00:49:59'),
(69, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-11 01:10:11'),
(70, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-11 01:11:42'),
(71, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-11 01:19:33'),
(72, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-11 01:23:29'),
(73, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-11 02:11:19'),
(74, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-11 02:11:40'),
(75, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-11 02:12:53'),
(76, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-11 02:28:48'),
(77, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-11 02:38:44'),
(78, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-11 02:49:06'),
(79, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-11 14:19:40'),
(80, 'Super Admin', 'super_admin', 'LOGIN', 'Admin session started', NULL, '::1', '2026-07-11 14:22:24'),
(81, 'Super Admin', 'super_admin', 'LOGIN', 'Super Admin session started', NULL, '::1', '2026-07-11 14:26:18'),
(82, 'Super Admin', 'super_admin', 'LOGIN', 'User session started', NULL, '::1', '2026-07-11 14:32:00'),
(83, 'Super Admin', 'super_admin', 'EMAIL BROADCAST SENT', 'Subject: \"Please Update\" to 5 recipient(s) (all)', NULL, '::1', '2026-07-12 01:00:47'),
(84, 'Super Admin', 'super_admin', 'EMAIL BROADCAST SENT', 'Subject: \"Please Update\" to 5 recipient(s) (all)', NULL, '::1', '2026-07-12 01:00:57'),
(85, 'Super Admin', 'super_admin', 'BROADCAST SENT', 'Severity: Info', NULL, '::1', '2026-07-12 01:01:37'),
(86, 'Super Admin', 'super_admin', 'BLACKLISTED', 'Club ID 8 blacklisted. Reason: Did not Update the profile', NULL, '::1', '2026-07-12 01:24:40'),
(87, 'Super Admin', 'super_admin', 'FLAG_DISPUTE', 'Tx ID 11 flagged as Disputed', NULL, '::1', '2026-07-12 02:25:30'),
(88, 'Super Admin', 'super_admin', 'FORCE_SETTLE', 'Tx ID 13 force-settled by superadmin', NULL, '::1', '2026-07-12 02:27:17'),
(89, 'Super Admin', 'super_admin', 'CONFIG CHANGED', 'Feature \'registration_frozen\' set to 1', NULL, '::1', '2026-07-12 02:55:01'),
(90, 'Super Admin', 'super_admin', 'CONFIG CHANGED', 'Feature \'maintenance_mode\' set to 1', NULL, '::1', '2026-07-12 02:55:02'),
(91, 'Super Admin', 'super_admin', 'FORCE_SETTLE', 'Tx ID 16 force-settled by superadmin', NULL, '::1', '2026-07-12 03:53:01');

-- --------------------------------------------------------

--
-- Table structure for table `campuses`
--

CREATE TABLE `campuses` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(20) NOT NULL,
  `city` varchar(80) DEFAULT NULL,
  `state` varchar(80) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `campuses`
--

INSERT INTO `campuses` (`id`, `name`, `code`, `city`, `state`, `is_active`, `created_at`) VALUES
(1, 'UiTM Machang', 'MCG', 'Machang', 'Kelantan', 1, '2026-06-26 23:22:52');

-- --------------------------------------------------------

--
-- Stand-in structure for view `campus_stats`
-- (See below for the actual view)
--
CREATE TABLE `campus_stats` (
`campus_id` int(10) unsigned
,`name` varchar(120)
,`active_events` bigint(21)
,`total_students` bigint(21)
,`avg_attendance_pct` decimal(8,5)
,`admin_count` bigint(21)
,`status` varchar(6)
);

-- --------------------------------------------------------

--
-- Table structure for table `escalated_approvals`
--

CREATE TABLE `escalated_approvals` (
  `id` int(10) UNSIGNED NOT NULL,
  `campus_id` int(10) UNSIGNED DEFAULT NULL,
  `club_id` int(10) UNSIGNED DEFAULT NULL,
  `event_id` int(10) UNSIGNED DEFAULT NULL,
  `type` enum('capacity_override','admin_dispute','cross_campus','bulk_data','other') NOT NULL DEFAULT 'other',
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `requested_by_id` int(10) UNSIGNED DEFAULT NULL,
  `requested_by_name` varchar(120) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `decision_by` int(10) UNSIGNED DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(10) UNSIGNED NOT NULL,
  `campus_id` int(10) UNSIGNED DEFAULT NULL,
  `club_id` int(10) UNSIGNED DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `title` varchar(200) NOT NULL,
  `venue` varchar(200) DEFAULT NULL,
  `category` enum('Academic','Cultural','Sports','Other') NOT NULL DEFAULT 'Other',
  `status` enum('upcoming','open','under_review','closed','cancelled','archived') NOT NULL DEFAULT 'upcoming',
  `capacity` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `registration_fee` decimal(8,2) NOT NULL DEFAULT 0.00,
  `registered_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_paid` tinyint(1) NOT NULL DEFAULT 0,
  `fee_amount` decimal(8,2) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_details` text DEFAULT NULL,
  `payment_deadline` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `campus_id`, `club_id`, `created_by`, `title`, `venue`, `category`, `status`, `capacity`, `registration_fee`, `registered_count`, `start_date`, `end_date`, `description`, `image_url`, `created_at`, `updated_at`, `is_paid`, `fee_amount`, `payment_method`, `payment_details`, `payment_deadline`) VALUES
(4, NULL, 6, 6, 'Majlis Anugerah Dekan Sesi 2024/2025', 'Dewan Professional UiTM Machang', 'Academic', 'open', 1000, 0.00, 1, '2026-07-04 03:09:00', '2026-07-31 03:12:00', '', NULL, '2026-07-04 03:09:58', '2026-07-11 17:22:38', 0, NULL, NULL, NULL, NULL),
(5, NULL, 6, 6, 'Wadah Tamu Bersama Timbalan Rektor Hal Ehwal Pelajar', 'Online', 'Academic', 'upcoming', 5000, 50.00, 1, '2026-07-04 03:16:00', '2026-08-12 00:30:00', '', NULL, '2026-07-04 03:17:07', '2026-07-11 17:23:07', 0, NULL, NULL, NULL, NULL),
(6, NULL, 7, 7, 'Malam Bersama Staf Residen Kolej', 'Dewan Professional UiTM Machang', 'Other', 'upcoming', 5000, 0.00, 1, '2026-07-04 20:00:00', '2026-07-31 00:47:00', '', NULL, '2026-07-04 03:47:22', '2026-07-12 02:55:41', 0, NULL, NULL, NULL, NULL),
(7, NULL, 6, 6, 'Pink Suzy with Zussie', 'Medan Ilmu', 'Other', 'upcoming', 150, 25.00, 1, '2026-07-12 05:27:00', '2026-07-30 22:30:00', '', NULL, '2026-07-08 05:27:41', '2026-07-11 17:45:41', 0, NULL, NULL, NULL, NULL),
(8, NULL, NULL, 6, 'Catrity Run with Whiskers', 'Medan Ilmu', 'Sports', 'open', 3000, 0.00, 1, '2026-07-11 16:12:00', '2026-07-18 12:12:00', '', NULL, '2026-07-11 16:12:55', '2026-07-11 17:43:41', 0, NULL, NULL, NULL, NULL),
(9, NULL, NULL, 9, 'Kuliah Maghrib', 'Pusat Islam', 'Other', 'upcoming', 5000, 0.00, 0, '2026-07-11 19:00:00', '2026-07-11 23:52:00', '', 'uploads/banners/banner_6a52128a39b61.jpeg', '2026-07-11 17:53:14', '2026-07-11 17:53:14', 0, NULL, NULL, NULL, NULL),
(10, NULL, NULL, 9, 'Met Gala with Tarians', 'Medan Ilmu', 'Cultural', 'upcoming', 200, 35.00, 1, '2026-07-12 18:06:00', '2026-08-04 22:59:00', '', NULL, '2026-07-12 03:05:01', '2026-07-12 03:15:00', 1, 35.00, 'Online Banking', 'Transfer to account number: 1370008123562 (Hafiz Shuib)\r\nReference (Met Gala_NAME)', '2026-08-04 22:59:00');

-- --------------------------------------------------------

--
-- Table structure for table `global_config`
--

CREATE TABLE `global_config` (
  `id` int(10) UNSIGNED NOT NULL,
  `config_key` varchar(80) NOT NULL,
  `config_value` text DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `global_config`
--

INSERT INTO `global_config` (`id`, `config_key`, `config_value`, `description`, `updated_by`, `updated_at`) VALUES
(1, 'academic_term_label', 'Semester 2, 2025/2026', 'Label shown on dashboard', NULL, '2026-06-26 23:22:52'),
(2, 'last_backup_date', '2026-06-26 23:22:52', 'Timestamp of last DB backup', NULL, '2026-06-26 23:22:52'),
(3, 'maintenance_mode', '1', '1 = all student portals offline', NULL, '2026-07-12 02:55:02'),
(4, 'max_event_capacity', '1000', 'Hard cap for any single event', NULL, '2026-06-26 23:22:52'),
(5, 'registration_frozen', '1', '1 = all registrations paused', NULL, '2026-07-12 02:55:01');

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `campus_id` int(10) UNSIGNED NOT NULL,
  `status` enum('registered','attended','cancelled','no_show') NOT NULL DEFAULT 'registered',
  `registered_at` datetime NOT NULL DEFAULT current_timestamp(),
  `attended_at` datetime DEFAULT NULL,
  `attendance_status` enum('pending','attended','absent') NOT NULL DEFAULT 'pending',
  `cancelled_at` datetime DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `event_id`, `student_id`, `campus_id`, `status`, `registered_at`, `attended_at`, `attendance_status`, `cancelled_at`, `notes`) VALUES
(19, 4, 1, 1, 'registered', '2026-07-11 17:22:38', NULL, 'pending', NULL, NULL),
(20, 5, 1, 1, 'registered', '2026-07-11 17:23:07', NULL, 'pending', NULL, NULL),
(21, 7, 1, 1, 'registered', '2026-07-11 17:35:02', NULL, 'pending', NULL, NULL),
(22, 8, 1, 1, 'registered', '2026-07-11 17:43:41', NULL, 'pending', NULL, NULL),
(23, 6, 1, 1, 'registered', '2026-07-12 02:55:41', NULL, 'pending', NULL, NULL),
(24, 10, 1, 1, 'registered', '2026-07-12 03:05:37', NULL, 'pending', NULL, NULL);

--
-- Triggers `registrations`
--
DELIMITER $$
CREATE TRIGGER `trg_reg_delete` AFTER DELETE ON `registrations` FOR EACH ROW BEGIN
  IF OLD.status = 'registered' THEN
    UPDATE events
    SET registered_count = GREATEST(registered_count - 1, 0)
    WHERE id = OLD.event_id;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_reg_insert` AFTER INSERT ON `registrations` FOR EACH ROW BEGIN
  IF NEW.status = 'registered' THEN
    UPDATE events
    SET registered_count = registered_count + 1
    WHERE id = NEW.event_id;
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_reg_update` AFTER UPDATE ON `registrations` FOR EACH ROW BEGIN
  -- Leaving 'registered' state: decrement
  IF OLD.status = 'registered'
     AND NEW.status IN ('cancelled', 'attended', 'no_show') THEN
    UPDATE events
    SET registered_count = GREATEST(registered_count - 1, 0)
    WHERE id = NEW.event_id;
  END IF;

  -- Re-entering 'registered' state from cancelled: increment
  IF OLD.status = 'cancelled' AND NEW.status = 'registered' THEN
    UPDATE events
    SET registered_count = registered_count + 1
    WHERE id = NEW.event_id;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(10) UNSIGNED NOT NULL,
  `campus_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `matric_no` varchar(40) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `faculty` varchar(10) DEFAULT NULL,
  `year` tinyint(1) UNSIGNED DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `students`
--

INSERT INTO `students` (`id`, `campus_id`, `name`, `email`, `password`, `matric_no`, `phone`, `faculty`, `year`, `is_active`, `created_at`) VALUES
(1, 1, 'NUR FATIRAH BINTI MAT DAUD', 'student@uitm.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2023123456', NULL, NULL, NULL, 1, '2026-07-03 13:51:48'),
(2, 1, 'Aina Syafiqah binti Razali', 'ainasyafiqah@student.uitm.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CB22110001', NULL, 'FSKM', 3, 1, '2022-09-01 08:00:00'),
(3, 1, 'Muhammad Haziq bin Sulaiman', 'mhaziq@student.uitm.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CB22110042', NULL, 'FPP', 3, 1, '2022-09-01 08:00:00'),
(4, 1, 'Priya Devi a/p Krishnan', 'priyadevi@student.uitm.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CB23110015', NULL, 'FSM', 2, 0, '2023-09-05 08:00:00'),
(5, 1, 'Lim Kai Xuan', 'limkaixuan@student.uitm.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CB21110088', NULL, 'FP', 4, 1, '2021-09-01 08:00:00'),
(6, 1, 'Nurul Izzah binti Ahmad', 'nurulizzah@student.uitm.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CB23110077', NULL, 'FSPP', 2, 1, '2023-09-05 08:00:00'),
(7, 1, 'Aaron Tan Wei Ming', 'aarontan@student.uitm.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CB22110103', NULL, 'FSKM', 3, 1, '2022-09-01 08:00:00'),
(8, 1, 'Siti Norfatihah binti Zainudin', 'sitinorfatihah@student.uitm.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CB24110009', NULL, 'ACIS', 1, 1, '2024-09-02 08:00:00'),
(9, 1, 'Danial Afiq bin Mohd Nor', 'danialafiq@student.uitm.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CB21110056', NULL, 'FPP', 4, 0, '2021-09-01 08:00:00'),
(10, 1, 'Grace Wong Hui Ying', 'gracewong@student.uitm.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CB23110041', NULL, 'FSR', 2, 0, '2023-09-05 08:00:00'),
(11, 1, 'Khairul Aiman bin Rusli', 'khairulai@student.uitm.edu.my', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'CB22110066', NULL, 'FSKM', 3, 1, '2022-09-01 08:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `super_admins`
--

CREATE TABLE `super_admins` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(180) NOT NULL,
  `password` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_login` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `super_admins`
--

INSERT INTO `super_admins` (`id`, `name`, `email`, `password`, `is_active`, `created_at`, `last_login`) VALUES
(1, 'Super Admin', 'super@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, '2026-06-26 23:22:52', NULL);

-- --------------------------------------------------------

--
-- Structure for view `campus_stats`
--
DROP TABLE IF EXISTS `campus_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `campus_stats`  AS SELECT `c`.`id` AS `campus_id`, `c`.`name` AS `name`, count(distinct case when `e`.`status` = 'open' then `e`.`id` end) AS `active_events`, count(distinct `s`.`id`) AS `total_students`, coalesce(avg(case when `r`.`status` = 'attended' then 100.0 else 0 end),0.00) AS `avg_attendance_pct`, count(distinct case when `a`.`status` = 'active' then `a`.`id` end) AS `admin_count`, CASE WHEN count(distinct case when `a`.`status` = 'active' then `a`.`id` end) = 0 THEN 'alert' WHEN max(`a`.`last_active`) < current_timestamp() - interval 1 hour THEN 'idle' ELSE 'online' END AS `status` FROM ((((`campuses` `c` left join `events` `e` on(`e`.`campus_id` = `c`.`id`)) left join `students` `s` on(`s`.`campus_id` = `c`.`id` and `s`.`is_active` = 1)) left join `registrations` `r` on(`r`.`campus_id` = `c`.`id`)) left join `admins` `a` on(`a`.`campus_id` = `c`.`id`)) WHERE `c`.`is_active` = 1 GROUP BY `c`.`id`, `c`.`name` ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `active_sessions`
--
ALTER TABLE `active_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_session_key` (`session_key`),
  ADD KEY `idx_sess_admin` (`admin_id`),
  ADD KEY `fk_sess_campus` (`campus_id`);

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_admin_email` (`email`),
  ADD KEY `idx_admin_campus` (`campus_id`),
  ADD KEY `idx_admin_status` (`status`),
  ADD KEY `idx_admin_club` (`club_id`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_log_actor` (`actor_name`),
  ADD KEY `idx_log_action` (`action`),
  ADD KEY `idx_log_campus` (`campus_id`),
  ADD KEY `idx_log_created` (`created_at`);

--
-- Indexes for table `campuses`
--
ALTER TABLE `campuses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_campus_code` (`code`);

--
-- Indexes for table `escalated_approvals`
--
ALTER TABLE `escalated_approvals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_esc_status` (`status`),
  ADD KEY `idx_esc_campus` (`campus_id`),
  ADD KEY `fk_esc_requester` (`requested_by_id`),
  ADD KEY `fk_esc_decision` (`decision_by`),
  ADD KEY `idx_esc_club` (`club_id`),
  ADD KEY `idx_event_id` (`event_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_campus` (`campus_id`),
  ADD KEY `idx_event_status` (`status`),
  ADD KEY `idx_event_start` (`start_date`),
  ADD KEY `idx_event_created` (`created_by`);

--
-- Indexes for table `global_config`
--
ALTER TABLE `global_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_config_key` (`config_key`),
  ADD KEY `fk_config_updater` (`updated_by`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_reg_event_student` (`event_id`,`student_id`),
  ADD KEY `idx_reg_event` (`event_id`),
  ADD KEY `idx_reg_student` (`student_id`),
  ADD KEY `idx_reg_campus` (`campus_id`),
  ADD KEY `idx_reg_status` (`status`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_student_email` (`email`),
  ADD UNIQUE KEY `uq_student_matric` (`matric_no`),
  ADD KEY `idx_student_campus` (`campus_id`);

--
-- Indexes for table `super_admins`
--
ALTER TABLE `super_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sa_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `active_sessions`
--
ALTER TABLE `active_sessions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `campuses`
--
ALTER TABLE `campuses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `escalated_approvals`
--
ALTER TABLE `escalated_approvals`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `global_config`
--
ALTER TABLE `global_config`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `super_admins`
--
ALTER TABLE `super_admins`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `active_sessions`
--
ALTER TABLE `active_sessions`
  ADD CONSTRAINT `fk_sess_admin` FOREIGN KEY (`admin_id`) REFERENCES `admins` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sess_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `fk_admin_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_admin_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD CONSTRAINT `fk_log_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `escalated_approvals`
--
ALTER TABLE `escalated_approvals`
  ADD CONSTRAINT `fk_esc_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_esc_club` FOREIGN KEY (`club_id`) REFERENCES `clubs` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_esc_decision` FOREIGN KEY (`decision_by`) REFERENCES `super_admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_esc_requester` FOREIGN KEY (`requested_by_id`) REFERENCES `admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `fk_event_admin` FOREIGN KEY (`created_by`) REFERENCES `admins` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_event_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `global_config`
--
ALTER TABLE `global_config`
  ADD CONSTRAINT `fk_config_updater` FOREIGN KEY (`updated_by`) REFERENCES `super_admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `fk_reg_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reg_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_reg_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `fk_student_campus` FOREIGN KEY (`campus_id`) REFERENCES `campuses` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
