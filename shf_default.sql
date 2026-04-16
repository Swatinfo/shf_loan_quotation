-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 09, 2026 at 08:58 AM
-- Server version: 12.3.1-MariaDB-log
-- PHP Version: 8.3.29

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `shf_all_operations`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `subject_type` varchar(255) DEFAULT NULL,
  `subject_id` bigint(20) UNSIGNED DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `activity_logs`
--

TRUNCATE TABLE `activity_logs`;
--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `subject_type`, `subject_id`, `properties`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 1, 'login', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '47.11.102.239', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:14:38', '2026-04-08 17:14:38'),
(2, 1, 'login', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '122.173.87.25', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-08 17:17:10', '2026-04-08 17:17:10'),
(3, 1, 'auto_assign_stage', 'App\\Models\\StageAssignment', 4, '{\"loan_number\":\"SHF-202604-0001\",\"stage_key\":\"document_collection\",\"assigned_to_name\":\"Denish Malviya\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:21:57', '2026-04-08 17:21:57'),
(4, 1, 'convert_quotation_to_loan', 'App\\Models\\LoanDetail', 1, '{\"quotation_id\":15,\"loan_number\":\"SHF-202604-0001\",\"customer_name\":\"HARDIK VEKARIYA\",\"loan_amount\":3000000,\"bank_name\":\"HDFC Bank\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:21:57', '2026-04-08 17:21:57'),
(5, 1, 'login', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:23:07', '2026-04-08 17:23:07'),
(6, 1, 'update_document_status', 'App\\Models\\LoanDocument', 1, '{\"document_name\":\"Passport Size Photographs Both\",\"loan_number\":\"SHF-202604-0001\",\"new_status\":\"received\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:23:36', '2026-04-08 17:23:36'),
(7, 1, 'update_document_status', 'App\\Models\\LoanDocument', 2, '{\"document_name\":\"PAN Card Both\",\"loan_number\":\"SHF-202604-0001\",\"new_status\":\"received\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:23:37', '2026-04-08 17:23:37'),
(8, 1, 'update_document_status', 'App\\Models\\LoanDocument', 3, '{\"document_name\":\"Aadhaar Card Both\",\"loan_number\":\"SHF-202604-0001\",\"new_status\":\"received\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:23:38', '2026-04-08 17:23:38'),
(9, 1, 'update_document_status', 'App\\Models\\LoanDocument', 4, '{\"document_name\":\"GST Registration Certificate\",\"loan_number\":\"SHF-202604-0001\",\"new_status\":\"received\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:23:39', '2026-04-08 17:23:39'),
(10, 1, 'update_document_status', 'App\\Models\\LoanDocument', 5, '{\"document_name\":\"Udyam Registration Certificate\",\"loan_number\":\"SHF-202604-0001\",\"new_status\":\"received\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:23:41', '2026-04-08 17:23:41'),
(11, 1, 'update_document_status', 'App\\Models\\LoanDocument', 6, '{\"document_name\":\"ITR (Last 3 years)\",\"loan_number\":\"SHF-202604-0001\",\"new_status\":\"received\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:23:42', '2026-04-08 17:23:42'),
(12, 1, 'update_document_status', 'App\\Models\\LoanDocument', 7, '{\"document_name\":\"Bank Statement (Last 12 months)\",\"loan_number\":\"SHF-202604-0001\",\"new_status\":\"received\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:23:43', '2026-04-08 17:23:43'),
(13, 1, 'update_document_status', 'App\\Models\\LoanDocument', 8, '{\"document_name\":\"Current Loan Statement ( if applicable )\",\"loan_number\":\"SHF-202604-0001\",\"new_status\":\"received\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:23:44', '2026-04-08 17:23:44'),
(14, 1, 'update_stage_status', 'App\\Models\\StageAssignment', 4, '{\"loan_number\":\"SHF-202604-0001\",\"stage_key\":\"document_collection\",\"old_status\":\"pending\",\"new_status\":\"in_progress\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:23:44', '2026-04-08 17:23:44'),
(15, 1, 'update_stage_status', 'App\\Models\\StageAssignment', 4, '{\"loan_number\":\"SHF-202604-0001\",\"stage_key\":\"document_collection\",\"old_status\":\"in_progress\",\"new_status\":\"completed\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:23:44', '2026-04-08 17:23:44'),
(16, 1, 'update_stage_status', 'App\\Models\\StageAssignment', 6, '{\"loan_number\":\"SHF-202604-0001\",\"stage_key\":\"app_number\",\"old_status\":\"in_progress\",\"new_status\":\"completed\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:24:16', '2026-04-08 17:24:16'),
(17, 1, 'auto_assign_stage', 'App\\Models\\StageAssignment', 19, '{\"loan_number\":\"SHF-202604-0002\",\"stage_key\":\"document_collection\",\"assigned_to_name\":\"Denish Malviya\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:26:03', '2026-04-08 17:26:03'),
(18, 1, 'convert_quotation_to_loan', 'App\\Models\\LoanDetail', 2, '{\"quotation_id\":12,\"loan_number\":\"SHF-202604-0002\",\"customer_name\":\"SHREE GANESH JEWELLERS\",\"loan_amount\":5000000,\"bank_name\":\"ICICI Bank\"}', '47.11.97.148', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-08 17:26:03', '2026-04-08 17:26:03'),
(19, 1, 'login', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:22:32', '2026-04-09 02:22:32'),
(20, 1, 'update_document_status', 'App\\Models\\LoanDocument', 9, '{\"document_name\":\"Passport Size Photographs of All Partners\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:22:47', '2026-04-09 02:22:47'),
(21, 1, 'update_document_status', 'App\\Models\\LoanDocument', 10, '{\"document_name\":\"PAN Card of Firm\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:22:48', '2026-04-09 02:22:48'),
(22, 1, 'update_document_status', 'App\\Models\\LoanDocument', 11, '{\"document_name\":\"PAN Card of All Partners\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:22:58', '2026-04-09 02:22:58'),
(23, 1, 'update_document_status', 'App\\Models\\LoanDocument', 13, '{\"document_name\":\"Bank Statement (12 months)\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:01', '2026-04-09 02:23:01'),
(24, 1, 'update_document_status', 'App\\Models\\LoanDocument', 12, '{\"document_name\":\"Aadhaar Card of All Partners\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:01', '2026-04-09 02:23:01'),
(25, 1, 'update_document_status', 'App\\Models\\LoanDocument', 14, '{\"document_name\":\"ITR of Firm (Last 3 years)\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:28', '2026-04-09 02:23:28'),
(26, 1, 'update_document_status', 'App\\Models\\LoanDocument', 19, '{\"document_name\":\"Firm Current A\\/c Bank Statement  (12 months)\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:29', '2026-04-09 02:23:29'),
(27, 1, 'update_document_status', 'App\\Models\\LoanDocument', 20, '{\"document_name\":\"Passport Size Photographs of All Partners\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:29', '2026-04-09 02:23:29'),
(28, 1, 'update_document_status', 'App\\Models\\LoanDocument', 20, '{\"document_name\":\"Passport Size Photographs of All Partners\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:29', '2026-04-09 02:23:29'),
(29, 1, 'update_document_status', 'App\\Models\\LoanDocument', 15, '{\"document_name\":\"ITR of Partners (Last 3 years)\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:29', '2026-04-09 02:23:29'),
(30, 1, 'update_document_status', 'App\\Models\\LoanDocument', 17, '{\"document_name\":\"Board Resolution \\/ Authority Letter\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:29', '2026-04-09 02:23:29'),
(31, 1, 'update_document_status', 'App\\Models\\LoanDocument', 17, '{\"document_name\":\"Board Resolution \\/ Authority Letter\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:29', '2026-04-09 02:23:29'),
(32, 1, 'update_document_status', 'App\\Models\\LoanDocument', 20, '{\"document_name\":\"Passport Size Photographs of All Partners\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:29', '2026-04-09 02:23:29'),
(33, 1, 'update_document_status', 'App\\Models\\LoanDocument', 20, '{\"document_name\":\"Passport Size Photographs of All Partners\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:29', '2026-04-09 02:23:29'),
(34, 1, 'update_document_status', 'App\\Models\\LoanDocument', 20, '{\"document_name\":\"Passport Size Photographs of All Partners\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:29', '2026-04-09 02:23:29'),
(35, 1, 'update_document_status', 'App\\Models\\LoanDocument', 18, '{\"document_name\":\"Partnership Deed\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:29', '2026-04-09 02:23:29'),
(36, 1, 'update_document_status', 'App\\Models\\LoanDocument', 14, '{\"document_name\":\"ITR of Firm (Last 3 years)\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:32', '2026-04-09 02:23:32'),
(37, 1, 'update_document_status', 'App\\Models\\LoanDocument', 16, '{\"document_name\":\"GST Registration Certificate\",\"loan_number\":\"SHF-202604-0002\",\"new_status\":\"received\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:35', '2026-04-09 02:23:35'),
(38, 1, 'update_stage_status', 'App\\Models\\StageAssignment', 19, '{\"loan_number\":\"SHF-202604-0002\",\"stage_key\":\"document_collection\",\"old_status\":\"pending\",\"new_status\":\"in_progress\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:35', '2026-04-09 02:23:35'),
(39, 1, 'update_stage_status', 'App\\Models\\StageAssignment', 19, '{\"loan_number\":\"SHF-202604-0002\",\"stage_key\":\"document_collection\",\"old_status\":\"in_progress\",\"new_status\":\"completed\"}', '47.11.101.179', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Mobile Safari/537.36', '2026-04-09 02:23:35', '2026-04-09 02:23:35'),
(40, 1, 'login', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 06:31:24', '2026-04-09 06:31:24'),
(41, 1, 'login', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/146.0.0.0 Safari/537.36', '2026-04-09 07:20:59', '2026-04-09 07:20:59'),
(42, 1, 'login', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/146.0.0.0 Safari/537.36', '2026-04-09 07:21:49', '2026-04-09 07:21:49'),
(43, 1, 'login', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 07:24:39', '2026-04-09 07:24:39'),
(44, 22, 'impersonate_start', 'App\\Models\\User', 22, '{\"impersonator\":\"Super Admin\",\"impersonated\":\"Office Employee1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 07:24:53', '2026-04-09 07:24:53'),
(45, 1, 'impersonate_end', 'App\\Models\\User', 22, '{\"impersonator\":\"Super Admin\",\"impersonated\":\"Office Employee1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 07:28:38', '2026-04-09 07:28:38'),
(46, 1, 'logout', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 07:28:59', '2026-04-09 07:28:59'),
(47, 1, 'login', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 07:29:04', '2026-04-09 07:29:04'),
(48, 1, 'login', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 07:55:48', '2026-04-09 07:55:48'),
(49, 1, 'login', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', '2026-04-09 07:56:00', '2026-04-09 07:56:00'),
(50, 1, 'login', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/146.0.0.0 Safari/537.36', '2026-04-09 08:00:35', '2026-04-09 08:00:35'),
(51, 1, 'login', 'App\\Models\\User', 1, '{\"name\":\"Super Admin\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/146.0.0.0 Safari/537.36', '2026-04-09 08:11:24', '2026-04-09 08:11:24');

-- --------------------------------------------------------

--
-- Table structure for table `app_config`
--

DROP TABLE IF EXISTS `app_config`;
CREATE TABLE `app_config` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `config_key` varchar(255) NOT NULL,
  `config_json` longtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `app_config`
--

TRUNCATE TABLE `app_config`;
--
-- Dumping data for table `app_config`
--

INSERT INTO `app_config` (`id`, `config_key`, `config_json`, `created_at`, `updated_at`) VALUES
(1, 'main', '{\"companyName\":\"Shreenathji Home Finance\",\"companyAddress\":\"OFFICE NO 911, R K PRIME, CIRCLE, next to SILVER HEIGHT, Nehru Nagar Co operative Society, Nana Mava, Rajkot, Gujarat 360004\",\"companyPhone\":\"+91 99747 89089\",\"companyEmail\":\"info@shf.com\",\"banks\":[\"HDFC Bank\",\"ICICI Bank\",\"Axis Bank\",\"Kotak Mahindra Bank\"],\"iomCharges\":{\"thresholdAmount\":10000000,\"fixedCharge\":7000,\"percentageAbove\":0.35},\"tenures\":[5,10,15,20],\"documents_en\":{\"proprietor\":[\"Passport Size Photographs Both\",\"PAN Card Both\",\"Aadhaar Card Both\",\"GST Registration Certificate\",\"Udyam Registration Certificate\",\"ITR (Last 3 years)\",\"Bank Statement (Last 12 months)\",\"Current Loan Statement ( if applicable )\",\"Property File Xerox\"],\"partnership_llp\":[\"PAN Card of Firm\",\"Partnership Deed\",\"GST Registration Certificate\",\"ITR With Audit of Firm (Last 3 years)\",\"Firm Current A\\/c Bank Statement (Last 12 months)\",\"Current Loan Statement ( if applicable )\",\"Passport Size Photographs of All Partners\",\"PAN Card of All Partners\",\"Aadhaar Card of All Partners\",\"ITR of Partners (Last 3 years)\",\"Bank Statement of Partners (Last 12 months)\"],\"pvt_ltd\":[\"PAN Card of Company\",\"Memorandum of Association (MOA)\",\"Articles of Association (AOA)\",\"GST Registration Certificate\",\"ITR With Audit Report of Company (Last 3 years)\",\"Current Loan Statement ( if applicable )\",\"Company Current A\\/C Statement (Last 12 months)\",\"Passport Size Photographs of All Director\",\"PAN Card of All Directors\",\"Aadhaar Card of All Directors\",\"ITR of Directors (Last 3 years)\",\"Bank Statement of Directors (Last 12 months)\"],\"salaried\":[\"Passport Size Photographs Both\",\"PAN Card Both\",\"Aadhaar Card Both\",\"Salary Slips (Last 6 months)\",\"ITR (Last 2 years)\",\"Form 16 (Last 2 years)\",\"Bank Statement (Last 6 months)\",\"Property Documents (if applicable)\"]},\"documents_gu\":{\"proprietor\":[\"Passport Size Photographs Both\",\"PAN Card Both\",\"Aadhaar Card Both\",\"GST Registration Certificate\",\"Udyam Registration Certificate\",\"ITR (Last 3 years)\",\"Bank Statement (Last 12 months)\",\"Current Loan Statement (if applicable)\",\"Property File Xerox\"],\"partnership_llp\":[\"PAN Card of Firm\",\"Partnership Deed\",\"GST Registration Certificate\",\"ITR With Audit of Firm (Last 3 years)\",\"Firm Current A\\/c Bank Statement (Last 12 months)\",\"Current Loan Statement (if applicable)\",\"Passport Size Photographs of All Partners\",\"PAN Card of All Partners\",\"Aadhaar Card of All Partners\",\"ITR of Partners (Last 3 years)\",\"Bank Statement of Partners (Last 12 months)\"],\"pvt_ltd\":[\"PAN Card of Company\",\"Memorandum of Association (MOA)\",\"Articles of Association (AOA)\",\"GST Registration Certificate\",\"ITR With Audit Report of Company (Last 3 years)\",\"Current Loan Statement (if applicable)\",\"Company Current A\\/C Statement (Last 12 months)\",\"Passport Size Photographs of All Director\",\"PAN Card of All Directors\",\"Aadhaar Card of All Directors\",\"ITR of Directors (Last 3 years)\",\"Bank Statement of Directors (Last 12 months)\"],\"salaried\":[\"Passport Size Photographs Both\",\"PAN Card Both\",\"Aadhaar Card Both\",\"Salary Slips (Last 6 months)\",\"ITR (Last 2 years)\",\"Form 16 (Last 2 years)\",\"Bank Statement (Last 6 months)\",\"Property Documents (if applicable)\"]},\"gstPercent\":18,\"ourServices\":\"Home Loan, Mortgage Loan, Commercial Loan, Industrial Loan,Land Loan, Over Draft(OD)\"}', '2026-02-27 10:29:58', '2026-04-08 17:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `app_settings`
--

DROP TABLE IF EXISTS `app_settings`;
CREATE TABLE `app_settings` (
  `setting_key` varchar(255) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `app_settings`
--

TRUNCATE TABLE `app_settings`;
--
-- Dumping data for table `app_settings`
--

INSERT INTO `app_settings` (`setting_key`, `setting_value`, `updated_at`) VALUES
('additional_notes', 'Loan amount may vary based on bank\'s visit\nROI may vary based on your CIBIL score\nNo charges for part payment or loan foreclosure\nLogin fee to be paid online, will be deducted from total processing fee\nLogin fee 3000/- non-refundable\nAxis Bank account opening required\nHealth Insurance & property insurance required', '2026-04-06 01:17:53');

-- --------------------------------------------------------

--
-- Table structure for table `banks`
--

DROP TABLE IF EXISTS `banks`;
CREATE TABLE `banks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `default_employee_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `banks`
--

TRUNCATE TABLE `banks`;
--
-- Dumping data for table `banks`
--

INSERT INTO `banks` (`id`, `name`, `code`, `is_active`, `default_employee_id`, `created_at`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
(1, 'HDFC Bank', 'HDFC', 1, 15, '2026-04-06 09:54:26', '2026-04-06 09:54:26', NULL, NULL, NULL),
(2, 'ICICI Bank', 'ICICI', 1, 21, '2026-04-06 09:54:26', '2026-04-06 09:54:26', NULL, NULL, NULL),
(3, 'Axis Bank', 'AXIS', 1, 18, '2026-04-06 09:54:26', '2026-04-06 09:54:26', NULL, NULL, NULL),
(4, 'Kotak Mahindra Bank', 'KOTAK', 1, 17, '2026-04-06 09:54:26', '2026-04-06 09:54:26', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `bank_charges`
--

DROP TABLE IF EXISTS `bank_charges`;
CREATE TABLE `bank_charges` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `pf` decimal(5,2) NOT NULL DEFAULT 0.00,
  `admin` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `stamp_notary` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `registration_fee` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `advocate` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `tc` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `extra1_name` varchar(255) DEFAULT NULL,
  `extra1_amt` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `extra2_name` varchar(255) DEFAULT NULL,
  `extra2_amt` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `bank_charges`
--

TRUNCATE TABLE `bank_charges`;
--
-- Dumping data for table `bank_charges`
--

INSERT INTO `bank_charges` (`id`, `bank_name`, `pf`, `admin`, `stamp_notary`, `registration_fee`, `advocate`, `tc`, `extra1_name`, `extra1_amt`, `extra2_name`, `extra2_amt`, `created_at`, `updated_at`) VALUES
(1, 'Axis Bank', 0.50, 0, 2500, 5900, 1000, 4500, NULL, 0, NULL, 0, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(2, 'HDFC Bank', 0.60, 0, 1500, 5900, 2500, 0, NULL, 0, NULL, 0, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(3, 'ICICI Bank', 0.60, 5000, 1500, 5900, 2000, 2500, NULL, 0, NULL, 0, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(4, 'Kotak Mahindra Bank', 0.50, 0, 2500, 5900, 2500, 0, NULL, 0, NULL, 0, '2026-04-08 17:14:19', '2026-04-08 17:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `bank_employees`
--

DROP TABLE IF EXISTS `bank_employees`;
CREATE TABLE `bank_employees` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bank_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `bank_employees`
--

TRUNCATE TABLE `bank_employees`;
--
-- Dumping data for table `bank_employees`
--

INSERT INTO `bank_employees` (`id`, `bank_id`, `user_id`, `is_default`, `created_at`, `updated_at`) VALUES
(1, 1, 14, 0, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(2, 1, 15, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(3, 2, 20, 0, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(4, 2, 21, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(5, 3, 18, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(6, 3, 19, 0, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(7, 4, 16, 0, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(8, 4, 17, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(9, 3, 22, 0, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(10, 1, 22, 0, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(11, 3, 23, 0, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(12, 1, 23, 0, '2026-04-08 17:14:19', '2026-04-08 17:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `bank_location`
--

DROP TABLE IF EXISTS `bank_location`;
CREATE TABLE `bank_location` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bank_id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `bank_location`
--

TRUNCATE TABLE `bank_location`;
--
-- Dumping data for table `bank_location`
--

INSERT INTO `bank_location` (`id`, `bank_id`, `location_id`, `created_at`, `updated_at`) VALUES
(1, 1, 2, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(2, 2, 2, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(3, 2, 3, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(4, 3, 2, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(5, 3, 3, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(6, 4, 2, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(7, 4, 3, '2026-04-08 17:14:19', '2026-04-08 17:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

DROP TABLE IF EXISTS `branches`;
CREATE TABLE `branches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `manager_id` bigint(20) UNSIGNED DEFAULT NULL,
  `location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `branches`
--

TRUNCATE TABLE `branches`;
--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `name`, `code`, `address`, `city`, `phone`, `is_active`, `manager_id`, `location_id`, `created_at`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
(1, 'Rajkot Main Office', 'RJK-MAIN', 'OFFICE NO 911, R K PRIME, CIRCLE, next to SILVER HEIGHT, Nehru Nagar Co operative Society, Nana Mava, Rajkot, Gujarat 360004', 'Rajkot', '+91 99747 89089', 1, 2, 2, '2026-04-06 09:54:26', '2026-04-07 17:52:31', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `cache`
--

TRUNCATE TABLE `cache`;
--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('shf-loan-management-cache-boost:mcp:database-schema:mysql::1:0:0:0', 'a:2:{s:6:\"engine\";s:5:\"mysql\";s:6:\"tables\";a:44:{s:13:\"activity_logs\";a:10:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"user_id\";s:19:\"bigint(20) unsigned\";s:6:\"action\";s:12:\"varchar(255)\";s:12:\"subject_type\";s:12:\"varchar(255)\";s:10:\"subject_id\";s:19:\"bigint(20) unsigned\";s:10:\"properties\";s:8:\"longtext\";s:10:\"ip_address\";s:11:\"varchar(45)\";s:10:\"user_agent\";s:4:\"text\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:10:\"app_config\";a:5:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:10:\"config_key\";s:12:\"varchar(255)\";s:11:\"config_json\";s:8:\"longtext\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:12:\"app_settings\";a:3:{s:11:\"setting_key\";s:12:\"varchar(255)\";s:13:\"setting_value\";s:4:\"text\";s:10:\"updated_at\";s:9:\"timestamp\";}s:5:\"banks\";a:10:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:4:\"name\";s:12:\"varchar(255)\";s:4:\"code\";s:12:\"varchar(255)\";s:9:\"is_active\";s:10:\"tinyint(1)\";s:19:\"default_employee_id\";s:19:\"bigint(20) unsigned\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";s:10:\"updated_by\";s:19:\"bigint(20) unsigned\";s:10:\"deleted_at\";s:9:\"timestamp\";s:10:\"deleted_by\";s:19:\"bigint(20) unsigned\";}s:12:\"bank_charges\";a:14:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:9:\"bank_name\";s:12:\"varchar(255)\";s:2:\"pf\";s:12:\"decimal(5,2)\";s:5:\"admin\";s:19:\"bigint(20) unsigned\";s:12:\"stamp_notary\";s:19:\"bigint(20) unsigned\";s:16:\"registration_fee\";s:19:\"bigint(20) unsigned\";s:8:\"advocate\";s:19:\"bigint(20) unsigned\";s:2:\"tc\";s:19:\"bigint(20) unsigned\";s:11:\"extra1_name\";s:12:\"varchar(255)\";s:10:\"extra1_amt\";s:19:\"bigint(20) unsigned\";s:11:\"extra2_name\";s:12:\"varchar(255)\";s:10:\"extra2_amt\";s:19:\"bigint(20) unsigned\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:14:\"bank_employees\";a:6:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"bank_id\";s:19:\"bigint(20) unsigned\";s:7:\"user_id\";s:19:\"bigint(20) unsigned\";s:10:\"is_default\";s:10:\"tinyint(1)\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:13:\"bank_location\";a:5:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"bank_id\";s:19:\"bigint(20) unsigned\";s:11:\"location_id\";s:19:\"bigint(20) unsigned\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:8:\"branches\";a:14:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:4:\"name\";s:12:\"varchar(255)\";s:4:\"code\";s:12:\"varchar(255)\";s:7:\"address\";s:4:\"text\";s:4:\"city\";s:12:\"varchar(255)\";s:5:\"phone\";s:11:\"varchar(20)\";s:9:\"is_active\";s:10:\"tinyint(1)\";s:10:\"manager_id\";s:19:\"bigint(20) unsigned\";s:11:\"location_id\";s:19:\"bigint(20) unsigned\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";s:10:\"updated_by\";s:19:\"bigint(20) unsigned\";s:10:\"deleted_at\";s:9:\"timestamp\";s:10:\"deleted_by\";s:19:\"bigint(20) unsigned\";}s:5:\"cache\";a:3:{s:3:\"key\";s:12:\"varchar(255)\";s:5:\"value\";s:10:\"mediumtext\";s:10:\"expiration\";s:7:\"int(11)\";}s:11:\"cache_locks\";a:3:{s:3:\"key\";s:12:\"varchar(255)\";s:5:\"owner\";s:12:\"varchar(255)\";s:10:\"expiration\";s:7:\"int(11)\";}s:20:\"disbursement_details\";a:22:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"loan_id\";s:19:\"bigint(20) unsigned\";s:17:\"disbursement_type\";s:12:\"varchar(255)\";s:17:\"disbursement_date\";s:4:\"date\";s:16:\"amount_disbursed\";s:19:\"bigint(20) unsigned\";s:19:\"bank_account_number\";s:12:\"varchar(255)\";s:9:\"ifsc_code\";s:12:\"varchar(255)\";s:13:\"cheque_number\";s:12:\"varchar(255)\";s:11:\"cheque_date\";s:4:\"date\";s:7:\"cheques\";s:8:\"longtext\";s:9:\"dd_number\";s:12:\"varchar(255)\";s:7:\"dd_date\";s:4:\"date\";s:6:\"is_otc\";s:10:\"tinyint(1)\";s:10:\"otc_branch\";s:12:\"varchar(255)\";s:11:\"otc_cleared\";s:10:\"tinyint(1)\";s:16:\"otc_cleared_date\";s:4:\"date\";s:14:\"otc_cleared_by\";s:19:\"bigint(20) unsigned\";s:16:\"reference_number\";s:12:\"varchar(255)\";s:5:\"notes\";s:4:\"text\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";s:10:\"updated_by\";s:19:\"bigint(20) unsigned\";}s:11:\"failed_jobs\";a:7:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:4:\"uuid\";s:12:\"varchar(255)\";s:10:\"connection\";s:4:\"text\";s:5:\"queue\";s:4:\"text\";s:7:\"payload\";s:8:\"longtext\";s:9:\"exception\";s:8:\"longtext\";s:9:\"failed_at\";s:9:\"timestamp\";}s:4:\"jobs\";a:7:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:5:\"queue\";s:12:\"varchar(255)\";s:7:\"payload\";s:8:\"longtext\";s:8:\"attempts\";s:19:\"tinyint(3) unsigned\";s:11:\"reserved_at\";s:16:\"int(10) unsigned\";s:12:\"available_at\";s:16:\"int(10) unsigned\";s:10:\"created_at\";s:16:\"int(10) unsigned\";}s:11:\"job_batches\";a:10:{s:2:\"id\";s:12:\"varchar(255)\";s:4:\"name\";s:12:\"varchar(255)\";s:10:\"total_jobs\";s:7:\"int(11)\";s:12:\"pending_jobs\";s:7:\"int(11)\";s:11:\"failed_jobs\";s:7:\"int(11)\";s:14:\"failed_job_ids\";s:8:\"longtext\";s:7:\"options\";s:10:\"mediumtext\";s:12:\"cancelled_at\";s:7:\"int(11)\";s:10:\"created_at\";s:7:\"int(11)\";s:11:\"finished_at\";s:7:\"int(11)\";}s:12:\"loan_details\";a:33:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:11:\"loan_number\";s:12:\"varchar(255)\";s:12:\"quotation_id\";s:19:\"bigint(20) unsigned\";s:9:\"branch_id\";s:19:\"bigint(20) unsigned\";s:11:\"location_id\";s:19:\"bigint(20) unsigned\";s:7:\"bank_id\";s:19:\"bigint(20) unsigned\";s:10:\"product_id\";s:19:\"bigint(20) unsigned\";s:13:\"customer_name\";s:12:\"varchar(255)\";s:13:\"customer_type\";s:12:\"varchar(255)\";s:14:\"customer_phone\";s:11:\"varchar(20)\";s:14:\"customer_email\";s:12:\"varchar(255)\";s:11:\"loan_amount\";s:19:\"bigint(20) unsigned\";s:6:\"status\";s:12:\"varchar(255)\";s:13:\"current_stage\";s:12:\"varchar(255)\";s:9:\"bank_name\";s:12:\"varchar(255)\";s:7:\"roi_min\";s:12:\"decimal(5,2)\";s:7:\"roi_max\";s:12:\"decimal(5,2)\";s:13:\"total_charges\";s:12:\"varchar(255)\";s:18:\"application_number\";s:12:\"varchar(255)\";s:22:\"assigned_bank_employee\";s:19:\"bigint(20) unsigned\";s:8:\"due_date\";s:4:\"date\";s:11:\"rejected_at\";s:9:\"timestamp\";s:11:\"rejected_by\";s:19:\"bigint(20) unsigned\";s:14:\"rejected_stage\";s:12:\"varchar(255)\";s:16:\"rejection_reason\";s:4:\"text\";s:10:\"created_by\";s:19:\"bigint(20) unsigned\";s:16:\"assigned_advisor\";s:19:\"bigint(20) unsigned\";s:5:\"notes\";s:4:\"text\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";s:10:\"updated_by\";s:19:\"bigint(20) unsigned\";s:10:\"deleted_at\";s:9:\"timestamp\";s:10:\"deleted_by\";s:19:\"bigint(20) unsigned\";}s:14:\"loan_documents\";a:20:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"loan_id\";s:19:\"bigint(20) unsigned\";s:16:\"document_name_en\";s:12:\"varchar(255)\";s:16:\"document_name_gu\";s:12:\"varchar(255)\";s:11:\"is_required\";s:10:\"tinyint(1)\";s:6:\"status\";s:12:\"varchar(255)\";s:13:\"received_date\";s:4:\"date\";s:11:\"received_by\";s:19:\"bigint(20) unsigned\";s:15:\"rejected_reason\";s:4:\"text\";s:5:\"notes\";s:4:\"text\";s:9:\"file_path\";s:12:\"varchar(255)\";s:9:\"file_name\";s:12:\"varchar(255)\";s:9:\"file_size\";s:19:\"bigint(20) unsigned\";s:9:\"file_mime\";s:12:\"varchar(100)\";s:11:\"uploaded_by\";s:19:\"bigint(20) unsigned\";s:11:\"uploaded_at\";s:9:\"timestamp\";s:10:\"sort_order\";s:7:\"int(11)\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";s:10:\"updated_by\";s:19:\"bigint(20) unsigned\";}s:13:\"loan_progress\";a:9:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"loan_id\";s:19:\"bigint(20) unsigned\";s:12:\"total_stages\";s:7:\"int(11)\";s:16:\"completed_stages\";s:7:\"int(11)\";s:18:\"overall_percentage\";s:12:\"decimal(5,2)\";s:20:\"estimated_completion\";s:4:\"date\";s:17:\"workflow_snapshot\";s:4:\"text\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:9:\"locations\";a:8:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:9:\"parent_id\";s:19:\"bigint(20) unsigned\";s:4:\"name\";s:12:\"varchar(255)\";s:4:\"type\";s:20:\"enum(\'state\',\'city\')\";s:4:\"code\";s:11:\"varchar(20)\";s:9:\"is_active\";s:10:\"tinyint(1)\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:16:\"location_product\";a:5:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:11:\"location_id\";s:19:\"bigint(20) unsigned\";s:10:\"product_id\";s:19:\"bigint(20) unsigned\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:13:\"location_user\";a:5:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:11:\"location_id\";s:19:\"bigint(20) unsigned\";s:7:\"user_id\";s:19:\"bigint(20) unsigned\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:10:\"migrations\";a:3:{s:2:\"id\";s:16:\"int(10) unsigned\";s:9:\"migration\";s:12:\"varchar(255)\";s:5:\"batch\";s:7:\"int(11)\";}s:21:\"password_reset_tokens\";a:3:{s:5:\"email\";s:12:\"varchar(255)\";s:5:\"token\";s:12:\"varchar(255)\";s:10:\"created_at\";s:9:\"timestamp\";}s:11:\"permissions\";a:7:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:4:\"name\";s:12:\"varchar(255)\";s:4:\"slug\";s:12:\"varchar(255)\";s:5:\"group\";s:12:\"varchar(255)\";s:11:\"description\";s:12:\"varchar(255)\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:8:\"products\";a:10:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"bank_id\";s:19:\"bigint(20) unsigned\";s:4:\"name\";s:12:\"varchar(255)\";s:4:\"code\";s:12:\"varchar(255)\";s:9:\"is_active\";s:10:\"tinyint(1)\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";s:10:\"updated_by\";s:19:\"bigint(20) unsigned\";s:10:\"deleted_at\";s:9:\"timestamp\";s:10:\"deleted_by\";s:19:\"bigint(20) unsigned\";}s:14:\"product_stages\";a:13:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:10:\"product_id\";s:19:\"bigint(20) unsigned\";s:8:\"stage_id\";s:19:\"bigint(20) unsigned\";s:10:\"is_enabled\";s:10:\"tinyint(1)\";s:21:\"default_assignee_role\";s:12:\"varchar(255)\";s:15:\"default_user_id\";s:19:\"bigint(20) unsigned\";s:9:\"auto_skip\";s:10:\"tinyint(1)\";s:10:\"allow_skip\";s:10:\"tinyint(1)\";s:20:\"sub_actions_override\";s:8:\"longtext\";s:10:\"sort_order\";s:7:\"int(11)\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";s:10:\"updated_by\";s:19:\"bigint(20) unsigned\";}s:19:\"product_stage_users\";a:8:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:16:\"product_stage_id\";s:19:\"bigint(20) unsigned\";s:9:\"branch_id\";s:19:\"bigint(20) unsigned\";s:11:\"location_id\";s:19:\"bigint(20) unsigned\";s:7:\"user_id\";s:19:\"bigint(20) unsigned\";s:10:\"is_default\";s:10:\"tinyint(1)\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:15:\"query_responses\";a:5:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:14:\"stage_query_id\";s:19:\"bigint(20) unsigned\";s:13:\"response_text\";s:4:\"text\";s:12:\"responded_by\";s:19:\"bigint(20) unsigned\";s:10:\"created_at\";s:9:\"timestamp\";}s:10:\"quotations\";a:18:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"loan_id\";s:19:\"bigint(20) unsigned\";s:11:\"location_id\";s:19:\"bigint(20) unsigned\";s:7:\"user_id\";s:19:\"bigint(20) unsigned\";s:13:\"customer_name\";s:12:\"varchar(255)\";s:13:\"customer_type\";s:12:\"varchar(255)\";s:11:\"loan_amount\";s:19:\"bigint(20) unsigned\";s:12:\"pdf_filename\";s:12:\"varchar(255)\";s:8:\"pdf_path\";s:12:\"varchar(255)\";s:16:\"additional_notes\";s:4:\"text\";s:16:\"prepared_by_name\";s:12:\"varchar(255)\";s:18:\"prepared_by_mobile\";s:12:\"varchar(255)\";s:16:\"selected_tenures\";s:8:\"longtext\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";s:10:\"updated_by\";s:19:\"bigint(20) unsigned\";s:10:\"deleted_at\";s:9:\"timestamp\";s:10:\"deleted_by\";s:19:\"bigint(20) unsigned\";}s:15:\"quotation_banks\";a:19:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:12:\"quotation_id\";s:19:\"bigint(20) unsigned\";s:9:\"bank_name\";s:12:\"varchar(255)\";s:7:\"roi_min\";s:12:\"decimal(5,2)\";s:7:\"roi_max\";s:12:\"decimal(5,2)\";s:9:\"pf_charge\";s:12:\"decimal(5,2)\";s:12:\"admin_charge\";s:19:\"bigint(20) unsigned\";s:12:\"stamp_notary\";s:19:\"bigint(20) unsigned\";s:16:\"registration_fee\";s:19:\"bigint(20) unsigned\";s:13:\"advocate_fees\";s:19:\"bigint(20) unsigned\";s:10:\"iom_charge\";s:19:\"bigint(20) unsigned\";s:9:\"tc_report\";s:19:\"bigint(20) unsigned\";s:11:\"extra1_name\";s:12:\"varchar(255)\";s:13:\"extra1_amount\";s:19:\"bigint(20) unsigned\";s:11:\"extra2_name\";s:12:\"varchar(255)\";s:13:\"extra2_amount\";s:19:\"bigint(20) unsigned\";s:13:\"total_charges\";s:19:\"bigint(20) unsigned\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:19:\"quotation_documents\";a:6:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:12:\"quotation_id\";s:19:\"bigint(20) unsigned\";s:16:\"document_name_en\";s:12:\"varchar(255)\";s:16:\"document_name_gu\";s:12:\"varchar(255)\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:13:\"quotation_emi\";a:8:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:17:\"quotation_bank_id\";s:19:\"bigint(20) unsigned\";s:12:\"tenure_years\";s:7:\"int(11)\";s:11:\"monthly_emi\";s:19:\"bigint(20) unsigned\";s:14:\"total_interest\";s:19:\"bigint(20) unsigned\";s:13:\"total_payment\";s:19:\"bigint(20) unsigned\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:7:\"remarks\";a:7:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"loan_id\";s:19:\"bigint(20) unsigned\";s:9:\"stage_key\";s:12:\"varchar(255)\";s:7:\"user_id\";s:19:\"bigint(20) unsigned\";s:6:\"remark\";s:4:\"text\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:16:\"role_permissions\";a:5:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:4:\"role\";s:51:\"enum(\'super_admin\',\'admin\',\'staff\',\'bank_employee\')\";s:13:\"permission_id\";s:19:\"bigint(20) unsigned\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:8:\"sessions\";a:6:{s:2:\"id\";s:12:\"varchar(255)\";s:7:\"user_id\";s:19:\"bigint(20) unsigned\";s:10:\"ip_address\";s:11:\"varchar(45)\";s:10:\"user_agent\";s:4:\"text\";s:7:\"payload\";s:8:\"longtext\";s:13:\"last_activity\";s:7:\"int(11)\";}s:17:\"shf_notifications\";a:11:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"user_id\";s:19:\"bigint(20) unsigned\";s:5:\"title\";s:12:\"varchar(255)\";s:7:\"message\";s:4:\"text\";s:4:\"type\";s:12:\"varchar(255)\";s:7:\"is_read\";s:10:\"tinyint(1)\";s:7:\"loan_id\";s:19:\"bigint(20) unsigned\";s:9:\"stage_key\";s:12:\"varchar(255)\";s:4:\"link\";s:12:\"varchar(255)\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:6:\"stages\";a:15:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:9:\"stage_key\";s:12:\"varchar(255)\";s:10:\"is_enabled\";s:10:\"tinyint(1)\";s:13:\"stage_name_en\";s:12:\"varchar(255)\";s:13:\"stage_name_gu\";s:12:\"varchar(255)\";s:14:\"sequence_order\";s:7:\"int(11)\";s:11:\"is_parallel\";s:10:\"tinyint(1)\";s:16:\"parent_stage_key\";s:12:\"varchar(255)\";s:10:\"stage_type\";s:12:\"varchar(255)\";s:14:\"description_en\";s:4:\"text\";s:14:\"description_gu\";s:4:\"text\";s:12:\"default_role\";s:12:\"varchar(255)\";s:11:\"sub_actions\";s:8:\"longtext\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:17:\"stage_assignments\";a:15:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"loan_id\";s:19:\"bigint(20) unsigned\";s:9:\"stage_key\";s:12:\"varchar(255)\";s:11:\"assigned_to\";s:19:\"bigint(20) unsigned\";s:6:\"status\";s:12:\"varchar(255)\";s:8:\"priority\";s:12:\"varchar(255)\";s:10:\"started_at\";s:9:\"timestamp\";s:12:\"completed_at\";s:9:\"timestamp\";s:12:\"completed_by\";s:19:\"bigint(20) unsigned\";s:17:\"is_parallel_stage\";s:10:\"tinyint(1)\";s:16:\"parent_stage_key\";s:12:\"varchar(255)\";s:5:\"notes\";s:4:\"text\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";s:10:\"updated_by\";s:19:\"bigint(20) unsigned\";}s:13:\"stage_queries\";a:11:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:19:\"stage_assignment_id\";s:19:\"bigint(20) unsigned\";s:7:\"loan_id\";s:19:\"bigint(20) unsigned\";s:9:\"stage_key\";s:12:\"varchar(255)\";s:10:\"query_text\";s:4:\"text\";s:9:\"raised_by\";s:19:\"bigint(20) unsigned\";s:6:\"status\";s:12:\"varchar(255)\";s:11:\"resolved_at\";s:9:\"timestamp\";s:11:\"resolved_by\";s:19:\"bigint(20) unsigned\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:15:\"stage_transfers\";a:9:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:19:\"stage_assignment_id\";s:19:\"bigint(20) unsigned\";s:7:\"loan_id\";s:19:\"bigint(20) unsigned\";s:9:\"stage_key\";s:12:\"varchar(255)\";s:16:\"transferred_from\";s:19:\"bigint(20) unsigned\";s:14:\"transferred_to\";s:19:\"bigint(20) unsigned\";s:6:\"reason\";s:4:\"text\";s:13:\"transfer_type\";s:12:\"varchar(255)\";s:10:\"created_at\";s:9:\"timestamp\";}s:21:\"task_role_permissions\";a:5:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:9:\"task_role\";s:12:\"varchar(255)\";s:13:\"permission_id\";s:19:\"bigint(20) unsigned\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:5:\"users\";a:16:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:4:\"name\";s:12:\"varchar(255)\";s:5:\"email\";s:12:\"varchar(255)\";s:17:\"email_verified_at\";s:9:\"timestamp\";s:8:\"password\";s:12:\"varchar(255)\";s:4:\"role\";s:51:\"enum(\'super_admin\',\'admin\',\'staff\',\'bank_employee\')\";s:9:\"is_active\";s:10:\"tinyint(1)\";s:10:\"created_by\";s:19:\"bigint(20) unsigned\";s:5:\"phone\";s:11:\"varchar(20)\";s:9:\"task_role\";s:12:\"varchar(255)\";s:11:\"employee_id\";s:12:\"varchar(255)\";s:17:\"default_branch_id\";s:19:\"bigint(20) unsigned\";s:12:\"task_bank_id\";s:19:\"bigint(20) unsigned\";s:14:\"remember_token\";s:12:\"varchar(100)\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:13:\"user_branches\";a:6:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"user_id\";s:19:\"bigint(20) unsigned\";s:9:\"branch_id\";s:19:\"bigint(20) unsigned\";s:26:\"is_default_office_employee\";s:10:\"tinyint(1)\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:16:\"user_permissions\";a:6:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"user_id\";s:19:\"bigint(20) unsigned\";s:13:\"permission_id\";s:19:\"bigint(20) unsigned\";s:4:\"type\";s:20:\"enum(\'grant\',\'deny\')\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";}s:17:\"valuation_details\";a:23:{s:2:\"id\";s:19:\"bigint(20) unsigned\";s:7:\"loan_id\";s:19:\"bigint(20) unsigned\";s:14:\"valuation_type\";s:12:\"varchar(255)\";s:16:\"property_address\";s:4:\"text\";s:8:\"latitude\";s:11:\"varchar(50)\";s:9:\"longitude\";s:11:\"varchar(50)\";s:13:\"property_type\";s:12:\"varchar(255)\";s:9:\"land_area\";s:12:\"varchar(255)\";s:9:\"land_rate\";s:13:\"decimal(12,2)\";s:14:\"land_valuation\";s:19:\"bigint(20) unsigned\";s:17:\"construction_area\";s:12:\"varchar(255)\";s:17:\"construction_rate\";s:13:\"decimal(12,2)\";s:22:\"construction_valuation\";s:19:\"bigint(20) unsigned\";s:15:\"final_valuation\";s:19:\"bigint(20) unsigned\";s:12:\"market_value\";s:19:\"bigint(20) unsigned\";s:16:\"government_value\";s:19:\"bigint(20) unsigned\";s:14:\"valuation_date\";s:4:\"date\";s:13:\"valuator_name\";s:12:\"varchar(255)\";s:22:\"valuator_report_number\";s:12:\"varchar(255)\";s:5:\"notes\";s:4:\"text\";s:10:\"created_at\";s:9:\"timestamp\";s:10:\"updated_at\";s:9:\"timestamp\";s:10:\"updated_by\";s:19:\"bigint(20) unsigned\";}}}', 1775723883),
('shf-loan-management-cache-boost.roster.scan', 'a:2:{s:6:\"roster\";O:21:\"Laravel\\Roster\\Roster\":3:{s:13:\"\0*\0approaches\";O:29:\"Illuminate\\Support\\Collection\":2:{s:8:\"\0*\0items\";a:0:{}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:11:\"\0*\0packages\";O:32:\"Laravel\\Roster\\PackageCollection\":2:{s:8:\"\0*\0items\";a:9:{i:0;O:22:\"Laravel\\Roster\\Package\":8:{s:9:\"\0*\0direct\";b:1;s:13:\"\0*\0constraint\";s:5:\"^12.0\";s:9:\"\0*\0source\";E:43:\"Laravel\\Roster\\Enums\\PackageSource:COMPOSER\";s:10:\"\0*\0package\";E:37:\"Laravel\\Roster\\Enums\\Packages:LARAVEL\";s:14:\"\0*\0packageName\";s:17:\"laravel/framework\";s:10:\"\0*\0version\";s:7:\"12.53.0\";s:6:\"\0*\0dev\";b:0;s:7:\"\0*\0path\";s:57:\"F:\\G Drive\\Projects\\quotationshf\\vendor\\laravel\\framework\";}i:1;O:22:\"Laravel\\Roster\\Package\":8:{s:9:\"\0*\0direct\";b:0;s:13:\"\0*\0constraint\";s:7:\"v0.3.13\";s:9:\"\0*\0source\";r:11;s:10:\"\0*\0package\";E:37:\"Laravel\\Roster\\Enums\\Packages:PROMPTS\";s:14:\"\0*\0packageName\";s:15:\"laravel/prompts\";s:10:\"\0*\0version\";s:6:\"0.3.13\";s:6:\"\0*\0dev\";b:0;s:7:\"\0*\0path\";s:55:\"F:\\G Drive\\Projects\\quotationshf\\vendor\\laravel\\prompts\";}i:2;O:22:\"Laravel\\Roster\\Package\":8:{s:9:\"\0*\0direct\";b:1;s:13:\"\0*\0constraint\";s:4:\"^2.2\";s:9:\"\0*\0source\";r:11;s:10:\"\0*\0package\";E:35:\"Laravel\\Roster\\Enums\\Packages:BOOST\";s:14:\"\0*\0packageName\";s:13:\"laravel/boost\";s:10:\"\0*\0version\";s:5:\"2.2.1\";s:6:\"\0*\0dev\";b:1;s:7:\"\0*\0path\";s:53:\"F:\\G Drive\\Projects\\quotationshf\\vendor\\laravel\\boost\";}i:3;O:22:\"Laravel\\Roster\\Package\":8:{s:9:\"\0*\0direct\";b:1;s:13:\"\0*\0constraint\";s:4:\"^2.3\";s:9:\"\0*\0source\";r:11;s:10:\"\0*\0package\";E:36:\"Laravel\\Roster\\Enums\\Packages:BREEZE\";s:14:\"\0*\0packageName\";s:14:\"laravel/breeze\";s:10:\"\0*\0version\";s:5:\"2.3.8\";s:6:\"\0*\0dev\";b:1;s:7:\"\0*\0path\";s:54:\"F:\\G Drive\\Projects\\quotationshf\\vendor\\laravel\\breeze\";}i:4;O:22:\"Laravel\\Roster\\Package\":8:{s:9:\"\0*\0direct\";b:0;s:13:\"\0*\0constraint\";s:6:\"v0.5.9\";s:9:\"\0*\0source\";r:11;s:10:\"\0*\0package\";E:33:\"Laravel\\Roster\\Enums\\Packages:MCP\";s:14:\"\0*\0packageName\";s:11:\"laravel/mcp\";s:10:\"\0*\0version\";s:5:\"0.5.9\";s:6:\"\0*\0dev\";b:1;s:7:\"\0*\0path\";s:51:\"F:\\G Drive\\Projects\\quotationshf\\vendor\\laravel\\mcp\";}i:5;O:22:\"Laravel\\Roster\\Package\":8:{s:9:\"\0*\0direct\";b:1;s:13:\"\0*\0constraint\";s:6:\"^1.2.2\";s:9:\"\0*\0source\";r:11;s:10:\"\0*\0package\";E:34:\"Laravel\\Roster\\Enums\\Packages:PAIL\";s:14:\"\0*\0packageName\";s:12:\"laravel/pail\";s:10:\"\0*\0version\";s:5:\"1.2.6\";s:6:\"\0*\0dev\";b:1;s:7:\"\0*\0path\";s:52:\"F:\\G Drive\\Projects\\quotationshf\\vendor\\laravel\\pail\";}i:6;O:22:\"Laravel\\Roster\\Package\":8:{s:9:\"\0*\0direct\";b:1;s:13:\"\0*\0constraint\";s:5:\"^1.24\";s:9:\"\0*\0source\";r:11;s:10:\"\0*\0package\";E:34:\"Laravel\\Roster\\Enums\\Packages:PINT\";s:14:\"\0*\0packageName\";s:12:\"laravel/pint\";s:10:\"\0*\0version\";s:6:\"1.27.1\";s:6:\"\0*\0dev\";b:1;s:7:\"\0*\0path\";s:52:\"F:\\G Drive\\Projects\\quotationshf\\vendor\\laravel\\pint\";}i:7;O:22:\"Laravel\\Roster\\Package\":8:{s:9:\"\0*\0direct\";b:1;s:13:\"\0*\0constraint\";s:5:\"^1.41\";s:9:\"\0*\0source\";r:11;s:10:\"\0*\0package\";E:34:\"Laravel\\Roster\\Enums\\Packages:SAIL\";s:14:\"\0*\0packageName\";s:12:\"laravel/sail\";s:10:\"\0*\0version\";s:6:\"1.53.0\";s:6:\"\0*\0dev\";b:1;s:7:\"\0*\0path\";s:52:\"F:\\G Drive\\Projects\\quotationshf\\vendor\\laravel\\sail\";}i:8;O:22:\"Laravel\\Roster\\Package\":8:{s:9:\"\0*\0direct\";b:1;s:13:\"\0*\0constraint\";s:7:\"^11.5.3\";s:9:\"\0*\0source\";r:11;s:10:\"\0*\0package\";E:37:\"Laravel\\Roster\\Enums\\Packages:PHPUNIT\";s:14:\"\0*\0packageName\";s:15:\"phpunit/phpunit\";s:10:\"\0*\0version\";s:7:\"11.5.55\";s:6:\"\0*\0dev\";b:1;s:7:\"\0*\0path\";s:55:\"F:\\G Drive\\Projects\\quotationshf\\vendor\\phpunit\\phpunit\";}}s:28:\"\0*\0escapeWhenCastingToString\";b:0;}s:21:\"\0*\0nodePackageManager\";E:43:\"Laravel\\Roster\\Enums\\NodePackageManager:NPM\";}s:9:\"timestamp\";i:1775723860;}', 1775810260);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `cache_locks`
--

TRUNCATE TABLE `cache_locks`;
-- --------------------------------------------------------

--
-- Table structure for table `disbursement_details`
--

DROP TABLE IF EXISTS `disbursement_details`;
CREATE TABLE `disbursement_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED NOT NULL,
  `disbursement_type` varchar(255) NOT NULL,
  `disbursement_date` date DEFAULT NULL,
  `amount_disbursed` bigint(20) UNSIGNED DEFAULT NULL,
  `bank_account_number` varchar(255) DEFAULT NULL,
  `ifsc_code` varchar(255) DEFAULT NULL,
  `cheque_number` varchar(255) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `cheques` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `dd_number` varchar(255) DEFAULT NULL,
  `dd_date` date DEFAULT NULL,
  `is_otc` tinyint(1) NOT NULL DEFAULT 0,
  `otc_branch` varchar(255) DEFAULT NULL,
  `otc_cleared` tinyint(1) NOT NULL DEFAULT 0,
  `otc_cleared_date` date DEFAULT NULL,
  `otc_cleared_by` bigint(20) UNSIGNED DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `disbursement_details`
--

TRUNCATE TABLE `disbursement_details`;
-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `failed_jobs`
--

TRUNCATE TABLE `failed_jobs`;
-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `jobs`
--

TRUNCATE TABLE `jobs`;
-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `job_batches`
--

TRUNCATE TABLE `job_batches`;
-- --------------------------------------------------------

--
-- Table structure for table `loan_details`
--

DROP TABLE IF EXISTS `loan_details`;
CREATE TABLE `loan_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_number` varchar(255) NOT NULL,
  `quotation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `branch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `bank_id` bigint(20) UNSIGNED DEFAULT NULL,
  `product_id` bigint(20) UNSIGNED DEFAULT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_type` varchar(255) NOT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `loan_amount` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `current_stage` varchar(255) NOT NULL DEFAULT 'inquiry',
  `bank_name` varchar(255) DEFAULT NULL,
  `roi_min` decimal(5,2) DEFAULT NULL,
  `roi_max` decimal(5,2) DEFAULT NULL,
  `total_charges` varchar(255) DEFAULT NULL,
  `application_number` varchar(255) DEFAULT NULL,
  `assigned_bank_employee` bigint(20) UNSIGNED DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejected_by` bigint(20) UNSIGNED DEFAULT NULL,
  `rejected_stage` varchar(255) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `assigned_advisor` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `loan_details`
--

TRUNCATE TABLE `loan_details`;
--
-- Dumping data for table `loan_details`
--

INSERT INTO `loan_details` (`id`, `loan_number`, `quotation_id`, `branch_id`, `location_id`, `bank_id`, `product_id`, `customer_name`, `customer_type`, `customer_phone`, `customer_email`, `loan_amount`, `status`, `current_stage`, `bank_name`, `roi_min`, `roi_max`, `total_charges`, `application_number`, `assigned_bank_employee`, `due_date`, `rejected_at`, `rejected_by`, `rejected_stage`, `rejection_reason`, `created_by`, `assigned_advisor`, `notes`, `created_at`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
(1, 'SHF-202604-0001', 15, 1, 2, 1, 8, 'HARDIK VEKARIYA', 'proprietor', '1234567890', NULL, 3000000, 'active', 'parallel_processing', 'HDFC Bank', 8.90, 9.00, '38140', 'Hbb nn', NULL, '2026-04-15', NULL, NULL, NULL, NULL, 1, 2, 'Loan amount may vary based on bank\'s visit\r\nROI may vary based on your CIBIL score\r\nNo charges for part payment or loan foreclosure\r\nLogin fee to be paid online, will be deducted from total processing fee\r\nLogin fee 5000/- non-refundable', '2026-04-08 17:21:57', '2026-04-08 17:24:16', 1, NULL, NULL),
(2, 'SHF-202604-0002', 12, 1, 2, 2, 1, 'SHREE GANESH JEWELLERS', 'partnership_llp', '1234567890', NULL, 5000000, 'active', 'parallel_processing', 'ICICI Bank', 9.05, 9.25, '67812', NULL, NULL, '2026-04-15', NULL, NULL, NULL, NULL, 1, 5, 'Rate of interest depends on customer\'s CIBIL score\r\nLoan approval at shown rate is not guaranteed\r\nLOGIN FEE 5000 /- NON REFUNDABLE', '2026-04-08 17:26:03', '2026-04-09 02:23:35', 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `loan_documents`
--

DROP TABLE IF EXISTS `loan_documents`;
CREATE TABLE `loan_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED NOT NULL,
  `document_name_en` varchar(255) NOT NULL,
  `document_name_gu` varchar(255) DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `received_date` date DEFAULT NULL,
  `received_by` bigint(20) UNSIGNED DEFAULT NULL,
  `rejected_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) UNSIGNED DEFAULT NULL,
  `file_mime` varchar(100) DEFAULT NULL,
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL,
  `uploaded_at` timestamp NULL DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `loan_documents`
--

TRUNCATE TABLE `loan_documents`;
--
-- Dumping data for table `loan_documents`
--

INSERT INTO `loan_documents` (`id`, `loan_id`, `document_name_en`, `document_name_gu`, `is_required`, `status`, `received_date`, `received_by`, `rejected_reason`, `notes`, `file_path`, `file_name`, `file_size`, `file_mime`, `uploaded_by`, `uploaded_at`, `sort_order`, `created_at`, `updated_at`, `updated_by`) VALUES
(1, 1, 'Passport Size Photographs Both', 'Passport Size Photographs Both', 1, 'received', '2026-04-08', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-04-08 17:21:57', '2026-04-08 17:23:36', 1),
(2, 1, 'PAN Card Both', 'PAN Card Both', 1, 'received', '2026-04-08', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-04-08 17:21:57', '2026-04-08 17:23:37', 1),
(3, 1, 'Aadhaar Card Both', 'Aadhaar Card Both', 1, 'received', '2026-04-08', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2026-04-08 17:21:57', '2026-04-08 17:23:38', 1),
(4, 1, 'GST Registration Certificate', 'GST Registration Certificate', 1, 'received', '2026-04-08', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-04-08 17:21:57', '2026-04-08 17:23:39', 1),
(5, 1, 'Udyam Registration Certificate', 'Udyam Registration Certificate', 1, 'received', '2026-04-08', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, '2026-04-08 17:21:57', '2026-04-08 17:23:41', 1),
(6, 1, 'ITR (Last 3 years)', 'ITR (Last 3 years)', 1, 'received', '2026-04-08', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-04-08 17:21:57', '2026-04-08 17:23:42', 1),
(7, 1, 'Bank Statement (Last 12 months)', 'Bank Statement (Last 12 months)', 1, 'received', '2026-04-08', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 6, '2026-04-08 17:21:57', '2026-04-08 17:23:43', 1),
(8, 1, 'Current Loan Statement ( if applicable )', 'Current Loan Statement ( if applicable )', 1, 'received', '2026-04-08', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 7, '2026-04-08 17:21:57', '2026-04-08 17:23:44', 1),
(9, 2, 'Passport Size Photographs of All Partners', 'Passport Size Photographs of All Partners', 1, 'received', '2026-04-09', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-04-08 17:26:03', '2026-04-09 02:22:47', 1),
(10, 2, 'PAN Card of Firm', 'PAN Card of Firm', 1, 'received', '2026-04-09', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, '2026-04-08 17:26:03', '2026-04-09 02:22:48', 1),
(11, 2, 'PAN Card of All Partners', 'PAN Card of All Partners', 1, 'received', '2026-04-09', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 2, '2026-04-08 17:26:03', '2026-04-09 02:22:58', 1),
(12, 2, 'Aadhaar Card of All Partners', 'Aadhaar Card of All Partners', 1, 'received', '2026-04-09', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 3, '2026-04-08 17:26:03', '2026-04-09 02:23:01', 1),
(13, 2, 'Bank Statement (12 months)', 'Bank Statement (12 months)', 1, 'received', '2026-04-09', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 4, '2026-04-08 17:26:03', '2026-04-09 02:23:01', 1),
(14, 2, 'ITR of Firm (Last 3 years)', 'ITR of Firm (Last 3 years)', 1, 'received', '2026-04-09', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 5, '2026-04-08 17:26:03', '2026-04-09 02:23:28', 1),
(15, 2, 'ITR of Partners (Last 3 years)', 'ITR of Partners (Last 3 years)', 1, 'received', '2026-04-09', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 6, '2026-04-08 17:26:03', '2026-04-09 02:23:29', 1),
(16, 2, 'GST Registration Certificate', 'GST Registration Certificate', 1, 'received', '2026-04-09', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 7, '2026-04-08 17:26:03', '2026-04-09 02:23:35', 1),
(17, 2, 'Board Resolution / Authority Letter', 'Board Resolution / Authority Letter', 1, 'received', '2026-04-09', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 8, '2026-04-08 17:26:03', '2026-04-09 02:23:29', 1),
(18, 2, 'Partnership Deed', 'Partnership Deed', 1, 'received', '2026-04-09', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 9, '2026-04-08 17:26:03', '2026-04-09 02:23:29', 1),
(19, 2, 'Firm Current A/c Bank Statement  (12 months)', 'Firm Current A/c Bank Statement  (12 months)', 1, 'received', '2026-04-09', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 10, '2026-04-08 17:26:03', '2026-04-09 02:23:29', 1),
(20, 2, 'Passport Size Photographs of All Partners', 'Passport Size Photographs of All Partners', 1, 'received', '2026-04-09', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 11, '2026-04-08 17:26:03', '2026-04-09 02:23:29', 1);

-- --------------------------------------------------------

--
-- Table structure for table `loan_progress`
--

DROP TABLE IF EXISTS `loan_progress`;
CREATE TABLE `loan_progress` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED NOT NULL,
  `total_stages` int(11) NOT NULL DEFAULT 10,
  `completed_stages` int(11) NOT NULL DEFAULT 0,
  `overall_percentage` decimal(5,2) NOT NULL DEFAULT 0.00,
  `estimated_completion` date DEFAULT NULL,
  `workflow_snapshot` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `loan_progress`
--

TRUNCATE TABLE `loan_progress`;
--
-- Dumping data for table `loan_progress`
--

INSERT INTO `loan_progress` (`id`, `loan_id`, `total_stages`, `completed_stages`, `overall_percentage`, `estimated_completion`, `workflow_snapshot`, `created_at`, `updated_at`) VALUES
(1, 1, 11, 3, 27.27, NULL, '[{\"stage_key\":\"otc_clearance\",\"status\":\"pending\",\"assigned_to\":null},{\"stage_key\":\"inquiry\",\"status\":\"completed\",\"assigned_to\":null},{\"stage_key\":\"document_selection\",\"status\":\"completed\",\"assigned_to\":null},{\"stage_key\":\"document_collection\",\"status\":\"completed\",\"assigned_to\":2},{\"stage_key\":\"parallel_processing\",\"status\":\"in_progress\",\"assigned_to\":null},{\"stage_key\":\"app_number\",\"status\":\"completed\",\"assigned_to\":2},{\"stage_key\":\"bsm_osv\",\"status\":\"in_progress\",\"assigned_to\":15},{\"stage_key\":\"legal_verification\",\"status\":\"in_progress\",\"assigned_to\":2},{\"stage_key\":\"technical_valuation\",\"status\":\"in_progress\",\"assigned_to\":2},{\"stage_key\":\"rate_pf\",\"status\":\"pending\",\"assigned_to\":null},{\"stage_key\":\"sanction\",\"status\":\"pending\",\"assigned_to\":null},{\"stage_key\":\"docket\",\"status\":\"pending\",\"assigned_to\":null},{\"stage_key\":\"kfs\",\"status\":\"pending\",\"assigned_to\":null},{\"stage_key\":\"esign\",\"status\":\"pending\",\"assigned_to\":null},{\"stage_key\":\"disbursement\",\"status\":\"pending\",\"assigned_to\":null}]', '2026-04-08 17:21:57', '2026-04-08 17:24:16'),
(2, 2, 11, 3, 27.27, NULL, '[{\"stage_key\":\"app_number\",\"status\":\"in_progress\",\"assigned_to\":2},{\"stage_key\":\"bsm_osv\",\"status\":\"in_progress\",\"assigned_to\":21},{\"stage_key\":\"disbursement\",\"status\":\"pending\",\"assigned_to\":null},{\"stage_key\":\"docket\",\"status\":\"pending\",\"assigned_to\":null},{\"stage_key\":\"document_collection\",\"status\":\"completed\",\"assigned_to\":2},{\"stage_key\":\"document_selection\",\"status\":\"completed\",\"assigned_to\":null},{\"stage_key\":\"esign\",\"status\":\"pending\",\"assigned_to\":null},{\"stage_key\":\"inquiry\",\"status\":\"completed\",\"assigned_to\":null},{\"stage_key\":\"kfs\",\"status\":\"pending\",\"assigned_to\":null},{\"stage_key\":\"legal_verification\",\"status\":\"in_progress\",\"assigned_to\":2},{\"stage_key\":\"otc_clearance\",\"status\":\"pending\",\"assigned_to\":null},{\"stage_key\":\"parallel_processing\",\"status\":\"in_progress\",\"assigned_to\":null},{\"stage_key\":\"rate_pf\",\"status\":\"pending\",\"assigned_to\":null},{\"stage_key\":\"sanction\",\"status\":\"pending\",\"assigned_to\":null},{\"stage_key\":\"technical_valuation\",\"status\":\"in_progress\",\"assigned_to\":2}]', '2026-04-08 17:26:03', '2026-04-09 02:23:35');

-- --------------------------------------------------------

--
-- Table structure for table `locations`
--

DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `parent_id` bigint(20) UNSIGNED DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('state','city') NOT NULL DEFAULT 'city',
  `code` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `locations`
--

TRUNCATE TABLE `locations`;
--
-- Dumping data for table `locations`
--

INSERT INTO `locations` (`id`, `parent_id`, `name`, `type`, `code`, `is_active`, `created_at`, `updated_at`) VALUES
(1, NULL, 'Gujarat', 'state', 'GJ', 1, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(2, 1, 'Rajkot', 'city', 'RJT', 1, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(3, 1, 'Jamnagar', 'city', 'JAM', 1, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(4, 1, 'Ahmedabad', 'city', 'AMD', 1, '2026-04-08 17:14:18', '2026-04-08 17:14:18');

-- --------------------------------------------------------

--
-- Table structure for table `location_product`
--

DROP TABLE IF EXISTS `location_product`;
CREATE TABLE `location_product` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `location_product`
--

TRUNCATE TABLE `location_product`;
-- --------------------------------------------------------

--
-- Table structure for table `location_user`
--

DROP TABLE IF EXISTS `location_user`;
CREATE TABLE `location_user` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `location_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `location_user`
--

TRUNCATE TABLE `location_user`;
--
-- Dumping data for table `location_user`
--

INSERT INTO `location_user` (`id`, `location_id`, `user_id`, `created_at`, `updated_at`) VALUES
(1, 1, 17, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(2, 2, 17, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(3, 2, 22, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(4, 2, 23, '2026-04-08 17:14:19', '2026-04-08 17:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `migrations`
--

TRUNCATE TABLE `migrations`;
--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '0001_01_01_000003_create_permissions_tables', 1),
(5, '0001_01_01_000004_create_activity_logs_table', 1),
(6, '0001_01_01_000005_create_app_config_tables', 1),
(7, '0001_01_01_000006_create_quotation_tables', 1),
(8, '2026_02_26_140000_add_missing_columns_to_quotation_tables', 1),
(9, '2026_02_27_153509_rename_stamp_notary_charge_columns', 1),
(10, '2026_04_06_200000_create_banks_table', 1),
(11, '2026_04_06_200001_create_branches_table', 1),
(12, '2026_04_06_200002_create_products_table', 1),
(13, '2026_04_06_200003_create_stages_table', 1),
(14, '2026_04_06_200004_create_user_branches_table', 1),
(15, '2026_04_06_200005_add_task_fields_to_users_table', 1),
(16, '2026_04_06_210000_create_loan_details_table', 1),
(17, '2026_04_06_210001_add_loan_id_to_quotations_table', 1),
(18, '2026_04_06_220000_create_loan_documents_table', 1),
(19, '2026_04_07_084256_add_is_default_office_employee_to_user_branches', 1),
(20, '2026_04_07_100000_create_stage_assignments_table', 1),
(21, '2026_04_07_100001_create_loan_progress_table', 1),
(22, '2026_04_07_100002_create_stage_transfers_table', 1),
(23, '2026_04_07_100003_create_stage_queries_table', 1),
(24, '2026_04_07_100004_create_query_responses_table', 1),
(25, '2026_04_07_110000_create_valuation_details_table', 1),
(26, '2026_04_07_110001_create_remarks_table', 1),
(27, '2026_04_07_110002_create_notifications_table', 1),
(28, '2026_04_07_120000_create_disbursement_details_table', 1),
(29, '2026_04_07_120001_create_product_stages_table', 1),
(30, '2026_04_07_120044_add_file_columns_to_loan_documents_table', 1),
(31, '2026_04_07_130000_add_soft_deletes_to_tables', 1),
(32, '2026_04_07_140000_add_audit_columns_to_tables', 1),
(33, '2026_04_07_150000_add_default_employee_to_banks_table', 1),
(34, '2026_04_07_160000_add_default_role_to_stages_table', 1),
(35, '2026_04_07_160001_add_manager_to_branches_table', 1),
(36, '2026_04_07_170000_change_default_role_to_json_on_stages', 1),
(37, '2026_04_07_180000_create_product_stage_users_table', 1),
(38, '2026_04_07_190000_create_bank_employees_table', 1),
(39, '2026_04_07_195942_add_allow_skip_to_product_stages_table', 1),
(40, '2026_04_07_205721_seed_default_roles_and_add_sub_actions', 1),
(41, '2026_04_07_212459_add_is_enabled_to_stages_table', 1),
(42, '2026_04_07_214232_add_is_default_to_product_stage_users', 1),
(43, '2026_04_07_231539_create_locations_and_pivots', 1),
(44, '2026_04_07_234028_fix_product_stage_users_constraint', 1),
(45, '2026_04_08_002307_add_location_id_to_quotations', 1),
(46, '2026_04_08_003826_create_bank_location_table', 1),
(47, '2026_04_08_132340_create_task_role_permissions_table', 1),
(48, '2026_04_08_134923_remove_legal_advisor_role', 1),
(49, '2026_04_08_150505_update_valuation_details_for_land_construction', 1),
(50, '2026_04_08_153948_add_cheques_json_to_disbursement_details', 1),
(51, '2026_04_08_154546_add_otc_clearance_stage', 1),
(52, '2026_04_08_161030_remove_optional_stages', 1),
(53, '2026_04_08_223020_add_bank_employee_to_users_role_enum', 1);

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `password_reset_tokens`
--

TRUNCATE TABLE `password_reset_tokens`;
-- --------------------------------------------------------

--
-- Table structure for table `permissions`
--

DROP TABLE IF EXISTS `permissions`;
CREATE TABLE `permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `permissions`
--

TRUNCATE TABLE `permissions`;
--
-- Dumping data for table `permissions`
--

INSERT INTO `permissions` (`id`, `name`, `slug`, `group`, `description`, `created_at`, `updated_at`) VALUES
(1, 'View Settings', 'view_settings', 'Settings', 'View the settings page', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(2, 'Edit Company Info', 'edit_company_info', 'Settings', 'Edit company information', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(3, 'Edit Banks', 'edit_banks', 'Settings', 'Add/edit/remove banks', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(4, 'Edit Documents', 'edit_documents', 'Settings', 'Add/edit/remove required documents', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(5, 'Edit Tenures', 'edit_tenures', 'Settings', 'Add/edit/remove loan tenures', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(6, 'Edit Charges', 'edit_charges', 'Settings', 'Edit bank charges', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(7, 'Edit Services', 'edit_services', 'Settings', 'Edit service charges', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(8, 'Edit GST', 'edit_gst', 'Settings', 'Edit GST percentage', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(9, 'Create Quotation', 'create_quotation', 'Quotations', 'Create new loan quotations', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(10, 'Generate PDF', 'generate_pdf', 'Quotations', 'Generate PDF for quotations', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(11, 'View Own Quotations', 'view_own_quotations', 'Quotations', 'View quotations created by self', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(12, 'View All Quotations', 'view_all_quotations', 'Quotations', 'View all quotations across users', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(13, 'Delete Quotations', 'delete_quotations', 'Quotations', 'Delete quotations', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(14, 'Download PDF', 'download_pdf', 'Quotations', 'Download generated PDFs', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(15, 'View Users', 'view_users', 'Users', 'View the users list', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(16, 'Create Users', 'create_users', 'Users', 'Create new user accounts', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(17, 'Edit Users', 'edit_users', 'Users', 'Edit existing user accounts', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(18, 'Delete Users', 'delete_users', 'Users', 'Delete user accounts', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(19, 'Assign Roles', 'assign_roles', 'Users', 'Assign roles to users', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(20, 'Change Own Password', 'change_own_password', 'System', 'Change own password', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(21, 'Manage Permissions', 'manage_permissions', 'System', 'Manage role and user permissions', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(22, 'View Activity Log', 'view_activity_log', 'System', 'View system activity log', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(23, 'Convert to Loan', 'convert_to_loan', 'Loans', 'Convert quotation to loan task', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(24, 'View Loans', 'view_loans', 'Loans', 'View loan task list', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(25, 'View All Loans', 'view_all_loans', 'Loans', 'View all loans across users/branches', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(26, 'Create Loan', 'create_loan', 'Loans', 'Create loan tasks directly', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(27, 'Edit Loan', 'edit_loan', 'Loans', 'Edit loan details', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(28, 'Delete Loan', 'delete_loan', 'Loans', 'Delete loan tasks', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(29, 'Manage Loan Documents', 'manage_loan_documents', 'Loans', 'Mark documents as received/pending, add/remove documents', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(30, 'Manage Loan Stages', 'manage_loan_stages', 'Loans', 'Update stage status and assignments', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(31, 'Skip Loan Stages', 'skip_loan_stages', 'Loans', 'Skip stages in loan workflow', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(32, 'Add Remarks', 'add_remarks', 'Loans', 'Add remarks to loan stages', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(33, 'Manage Workflow Config', 'manage_workflow_config', 'Loans', 'Configure banks, products, branches, stage workflows', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(34, 'Upload Loan Documents', 'upload_loan_documents', 'Loans', 'Upload document files to loan documents', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(35, 'Download Loan Documents', 'download_loan_documents', 'Loans', 'Download/preview uploaded document files', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(36, 'Delete Loan Files', 'delete_loan_files', 'Loans', 'Remove uploaded document files', '2026-04-08 17:14:18', '2026-04-08 17:14:18');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `bank_id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `products`
--

TRUNCATE TABLE `products`;
--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `bank_id`, `name`, `code`, `is_active`, `created_at`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
(1, 2, 'Home Loan', NULL, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19', NULL, NULL, NULL),
(2, 2, 'LAP', NULL, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19', NULL, NULL, NULL),
(3, 2, 'OD', NULL, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19', NULL, NULL, NULL),
(4, 2, 'PRATHAM', NULL, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19', NULL, NULL, NULL),
(5, 3, 'Home Loan', NULL, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19', NULL, NULL, NULL),
(6, 3, 'LAP', NULL, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19', NULL, NULL, NULL),
(7, 3, 'ASHA', NULL, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19', NULL, NULL, NULL),
(8, 1, 'Home Loan', NULL, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19', NULL, NULL, NULL),
(9, 1, 'LAP', NULL, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19', NULL, NULL, NULL),
(10, 4, 'Home Loan', NULL, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19', NULL, NULL, NULL),
(11, 4, 'LAP', NULL, 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_stages`
--

DROP TABLE IF EXISTS `product_stages`;
CREATE TABLE `product_stages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `stage_id` bigint(20) UNSIGNED NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `default_assignee_role` varchar(255) DEFAULT NULL,
  `default_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `auto_skip` tinyint(1) NOT NULL DEFAULT 0,
  `allow_skip` tinyint(1) NOT NULL DEFAULT 1,
  `sub_actions_override` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `sort_order` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `product_stages`
--

TRUNCATE TABLE `product_stages`;
-- --------------------------------------------------------

--
-- Table structure for table `product_stage_users`
--

DROP TABLE IF EXISTS `product_stage_users`;
CREATE TABLE `product_stage_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_stage_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `is_default` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `product_stage_users`
--

TRUNCATE TABLE `product_stage_users`;
-- --------------------------------------------------------

--
-- Table structure for table `query_responses`
--

DROP TABLE IF EXISTS `query_responses`;
CREATE TABLE `query_responses` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stage_query_id` bigint(20) UNSIGNED NOT NULL,
  `response_text` text NOT NULL,
  `responded_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `query_responses`
--

TRUNCATE TABLE `query_responses`;
-- --------------------------------------------------------

--
-- Table structure for table `quotations`
--

DROP TABLE IF EXISTS `quotations`;
CREATE TABLE `quotations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED DEFAULT NULL,
  `location_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `customer_name` varchar(255) NOT NULL,
  `customer_type` varchar(255) NOT NULL,
  `loan_amount` bigint(20) UNSIGNED NOT NULL,
  `pdf_filename` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `additional_notes` text DEFAULT NULL,
  `prepared_by_name` varchar(255) DEFAULT NULL,
  `prepared_by_mobile` varchar(255) DEFAULT NULL,
  `selected_tenures` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `deleted_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `quotations`
--

TRUNCATE TABLE `quotations`;
--
-- Dumping data for table `quotations`
--

INSERT INTO `quotations` (`id`, `loan_id`, `location_id`, `user_id`, `customer_name`, `customer_type`, `loan_amount`, `pdf_filename`, `pdf_path`, `additional_notes`, `prepared_by_name`, `prepared_by_mobile`, `selected_tenures`, `created_at`, `updated_at`, `updated_by`, `deleted_at`, `deleted_by`) VALUES
(1, NULL, 2, 2, 'ASHOKBHAI CHHANGOMALBHAI LALWANI', 'proprietor', 4200000, NULL, NULL, 'Rate of interest depends on customer\'s CIBIL score\nLoan approval at shown rate is not guaranteed', 'KULDEEP PATEL', '8866236688', '[20]', '2026-02-28 09:35:53', '2026-02-28 09:35:53', NULL, NULL, NULL),
(2, NULL, 2, 2, 'AMIPARA MAHESHBHAI UKABHAI', 'proprietor', 5820000, NULL, NULL, 'Rate of interest depends on customer\'s CIBIL score\nLoan approval at shown rate is not guaranteed', 'HARDIK NASIT', '+91 9726179351', '[15,20]', '2026-03-03 07:21:14', '2026-03-03 07:21:14', NULL, NULL, NULL),
(3, NULL, 2, 2, 'AMIPARA MAHESHBHAI UKABHAI', 'proprietor', 5820000, NULL, NULL, 'Rate of interest depends on customer\'s CIBIL score\nLoan approval at shown rate is not guaranteed', 'HARDIK NASIT', '+91 9726179351', '[15,20]', '2026-03-03 07:23:29', '2026-03-03 07:23:29', NULL, NULL, NULL),
(4, NULL, 2, 2, 'AMIPARA MAHESHBHAI UKABHAI', 'proprietor', 5820000, NULL, NULL, 'Rate of interest depends on customer\'s CIBIL score\nLoan approval at shown rate is not guaranteed', 'HARDIK NASIT', '+91 9726179351', '[15,20]', '2026-03-03 07:24:43', '2026-03-03 07:24:43', NULL, NULL, NULL),
(5, NULL, 2, 2, 'Brijesh Kumar unjiya', 'proprietor', 2500000, NULL, NULL, 'Rate of interest depends on customer\'s CIBIL score\nLoan approval at shown rate is not guaranteed\nProperty insurance is mandatory', 'Nitin faldu', '+91 9687501525', '[15]', '2026-03-05 05:21:54', '2026-03-05 05:21:54', NULL, NULL, NULL),
(6, NULL, 2, 2, 'Brijesh Kumar unjiya', 'proprietor', 2500000, NULL, NULL, 'Rate of interest depends on customer\'s CIBIL score\nLoan approval at shown rate is not guaranteed\nProperty insurance is mandatory', 'Nitin faldu', '+91 9687501525', '[15]', '2026-03-05 05:26:14', '2026-03-05 05:26:14', NULL, NULL, NULL),
(7, NULL, 2, 2, 'PRASHANT KISHORBHAI JADAV', 'proprietor', 5000000, NULL, NULL, 'Rate of interest depends on customer\'s CIBIL score\nLoan approval at shown rate is not guaranteed\nInsurance is mandatory\nAXIS BANK LTD account opening is mandatory', 'CHIRAG DHOLAKIYA', '09016348138', '[10,15]', '2026-03-05 07:31:36', '2026-03-05 07:31:36', NULL, NULL, NULL),
(8, NULL, 2, 2, 'SUBHASBHAI SORATHIYA', 'proprietor', 2600000, NULL, NULL, 'Rate of interest depends on customer\'s CIBIL score\nLoan approval at shown rate is not guaranteed\nICICI account opening and insurance are not required\nWe will minimize charges as much as possible', 'Admin', '+91 9974277500', '[15,20]', '2026-03-09 06:03:47', '2026-03-09 06:03:47', NULL, NULL, NULL),
(9, NULL, 2, 2, 'MEGHANI CHANDUBHAI UKABHAI', 'proprietor', 7000000, NULL, NULL, 'Rate of interest depends on customer\'s CIBIL score\nLoan approval at shown rate is not guaranteed\nWe will minimize charges as much as possible', 'RUSHI SOJITRA  &  KULDEEP PATEL', '8460244864  &  8866236688', '[15]', '2026-03-09 07:15:57', '2026-03-09 07:15:57', NULL, NULL, NULL),
(10, NULL, 2, 2, 'HIRAPARA KEYUR', 'proprietor', 1900000, NULL, NULL, 'Rate of interest depends on customer\'s CIBIL score\nLoan approval at shown rate is not guaranteed\nWe will minimize charges as much as possible', 'Denish Malviya', '+91 99747 89089', '[20]', '2026-03-12 04:33:32', '2026-03-12 04:33:32', NULL, NULL, NULL),
(11, NULL, 2, 2, '...', 'proprietor', 2000000, NULL, NULL, 'Rate of interest depends on customer\'s CIBIL score\nLoan approval at shown rate is not guaranteed\nLOGIN FEE 3000 /- NON REFUNDABLE', 'KULDEEP PATEL', '8866236688', '[20]', '2026-03-15 00:36:32', '2026-03-15 00:36:32', NULL, NULL, NULL),
(12, 2, 2, 2, 'SHREE GANESH JEWELLERS', 'partnership_llp', 5000000, NULL, NULL, 'Rate of interest depends on customer\'s CIBIL score\nLoan approval at shown rate is not guaranteed\nLOGIN FEE 5000 /- NON REFUNDABLE', 'CHIRAG DHOLAKIYA', '90163 48138', '[10,15]', '2026-03-19 08:21:11', '2026-04-08 17:26:03', 1, NULL, NULL),
(13, NULL, 2, 2, 'TANSUKH DHIRAJLAL VEKARIYA', 'proprietor', 3500000, NULL, NULL, 'Rate of interest depends on customer\'s CIBIL score\nLoan approval at shown rate is not guaranteed\nLOGIN FEE 5000 /- NON REFUNDABLE', 'Denish Malviya', '+91 99747 89089', '[20]', '2026-03-26 07:34:28', '2026-03-26 07:34:28', NULL, NULL, NULL),
(14, NULL, 2, 9, 'SAJANBEN MUKESHBHAI AAL', 'proprietor', 4000000, NULL, NULL, 'Loan amount may vary based on bank\'s visit\nROI may vary based on your CIBIL score\nNo charges for part payment or loan foreclosure\nProperty file full copy required (with succession, copy will not be returned)\nLogin fee to be paid online, will be deducted from total processing fee\nLogin fee 5000/- non-refundable', 'CHIRAG DHOLAKIYA', '9016348138', '[10,15]', '2026-03-28 02:14:32', '2026-03-28 02:14:32', NULL, NULL, NULL),
(15, 1, 2, 2, 'HARDIK VEKARIYA', 'proprietor', 3000000, 'Loan_Proposal_HARDIK_VEKARIYA_2026-03-30_14_10_32.pdf', '/home/admin/web/loans.shfworld.com/public_html/storage/app/pdfs/Loan_Proposal_HARDIK_VEKARIYA_2026-03-30_14_10_32.pdf', 'Loan amount may vary based on bank\'s visit\nROI may vary based on your CIBIL score\nNo charges for part payment or loan foreclosure\nLogin fee to be paid online, will be deducted from total processing fee\nLogin fee 5000/- non-refundable', 'Denish Malviya', '+91 99747 89089', '[10,15]', '2026-03-30 08:40:33', '2026-04-08 17:21:57', 1, NULL, NULL),
(16, NULL, 2, 9, 'PRASHANTBHAI JADAV', 'proprietor', 2000000, NULL, NULL, 'Loan amount may vary based on bank\'s visit\nROI may vary based on your CIBIL score\nNo charges for part payment or loan foreclosure\nLogin fee to be paid online, will be deducted from total processing fee\nLogin fee 5000/- non-refundable', 'CHIRAG DHOLAKIYA', '9016348138', '[10,15]', '2026-04-04 06:36:48', '2026-04-04 06:36:48', NULL, NULL, NULL),
(17, NULL, 2, 9, 'PRASHANTBHAI JADAV', 'proprietor', 2000000, 'Loan_Proposal_PRASHANTBHAI_JADAV_2026-04-04_12_08_33.pdf', '/home/admin/web/loans.shfworld.com/public_html/storage/app/pdfs/Loan_Proposal_PRASHANTBHAI_JADAV_2026-04-04_12_08_33.pdf', 'Loan amount may vary based on bank\'s visit\nROI may vary based on your CIBIL score\nNo charges for part payment or loan foreclosure\nLogin fee to be paid online, will be deducted from total processing fee\nLogin fee 5000/- non-refundable', 'CHIRAG DHOLAKIYA', '9016348138', '[10,15]', '2026-04-04 06:38:33', '2026-04-08 17:15:15', 1, NULL, NULL),
(18, NULL, 2, 9, 'NARIGARA SURESHBHAI R', 'proprietor', 1600000, NULL, NULL, 'Loan amount may vary based on bank\'s visit\nROI may vary based on your CIBIL score\nNo charges for part payment or loan foreclosure\nLogin fee to be paid online, will be deducted from total processing fee\nLogin fee 3000/- non-refundable\nAxis Bank account opening required\nHealth Insurance & property insurance required', 'CHIRAG DHOLAKIYA', '9016348138', '[15,20]', '2026-04-06 01:17:53', '2026-04-06 23:59:57', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `quotation_banks`
--

DROP TABLE IF EXISTS `quotation_banks`;
CREATE TABLE `quotation_banks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `quotation_id` bigint(20) UNSIGNED NOT NULL,
  `bank_name` varchar(255) NOT NULL,
  `roi_min` decimal(5,2) NOT NULL DEFAULT 0.00,
  `roi_max` decimal(5,2) NOT NULL DEFAULT 0.00,
  `pf_charge` decimal(5,2) NOT NULL DEFAULT 0.00,
  `admin_charge` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `stamp_notary` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `registration_fee` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `advocate_fees` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `iom_charge` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `tc_report` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `extra1_name` varchar(255) DEFAULT NULL,
  `extra1_amount` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `extra2_name` varchar(255) DEFAULT NULL,
  `extra2_amount` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `total_charges` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `quotation_banks`
--

TRUNCATE TABLE `quotation_banks`;
--
-- Dumping data for table `quotation_banks`
--

INSERT INTO `quotation_banks` (`id`, `quotation_id`, `bank_name`, `roi_min`, `roi_max`, `pf_charge`, `admin_charge`, `stamp_notary`, `registration_fee`, `advocate_fees`, `iom_charge`, `tc_report`, `extra1_name`, `extra1_amount`, `extra2_name`, `extra2_amount`, `total_charges`, `created_at`, `updated_at`) VALUES
(1, 1, 'ICICI Bank', 7.55, 7.65, 0.25, 5000, 2000, 6000, 2500, 7000, 2500, NULL, 0, NULL, 0, 38290, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(2, 2, 'ICICI Bank', 7.40, 7.75, 0.25, 5000, 2000, 6000, 3000, 5900, 2500, NULL, 0, NULL, 0, 42469, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(3, 2, 'HDFC Bank', 7.40, 7.50, 0.50, 2360, 3000, 6000, 3000, 5900, 0, NULL, 0, NULL, 0, 55023, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(4, 3, 'ICICI Bank', 7.40, 7.75, 0.25, 5000, 2000, 6000, 3000, 5900, 2500, NULL, 0, NULL, 0, 42469, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(5, 3, 'HDFC Bank', 7.35, 7.50, 0.20, 0, 3000, 6000, 3000, 5900, 0, NULL, 0, NULL, 0, 31635, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(6, 4, 'ICICI Bank', 7.40, 7.75, 0.25, 5000, 2000, 6000, 3000, 5900, 2500, NULL, 0, NULL, 0, 42469, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(7, 4, 'HDFC Bank', 7.35, 7.50, 0.20, 0, 3000, 6000, 3000, 5900, 0, NULL, 0, NULL, 0, 31635, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(8, 5, 'HDFC Bank', 9.00, 9.15, 0.60, 0, 2500, 5900, 2500, 7000, 0, NULL, 0, NULL, 0, 35600, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(9, 5, 'Kotak Mahindra Bank', 9.00, 9.20, 0.50, 11000, 3000, 5900, 2500, 7000, 0, 'Login fees', 5900, NULL, 0, 52030, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(10, 5, 'ICICI Bank', 9.05, 9.30, 0.60, 5000, 600, 5900, 2500, 7000, 2000, NULL, 0, NULL, 0, 41600, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(11, 5, 'Axis Bank', 9.00, 9.25, 0.60, 0, 2000, 5900, 2500, 7000, 0, NULL, 0, NULL, 0, 35100, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(12, 6, 'HDFC Bank', 9.00, 9.15, 0.60, 0, 2500, 5900, 2500, 7000, 0, NULL, 0, NULL, 0, 35600, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(13, 6, 'Kotak Mahindra Bank', 9.00, 9.20, 0.50, 0, 3000, 5900, 2500, 7000, 0, 'Login fees', 5900, NULL, 0, 39050, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(14, 6, 'ICICI Bank', 9.05, 9.30, 0.60, 5000, 600, 5900, 2500, 7000, 2000, NULL, 0, NULL, 0, 41600, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(15, 6, 'Axis Bank', 9.00, 9.25, 0.60, 0, 2000, 5900, 2500, 7000, 0, NULL, 0, NULL, 0, 35100, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(16, 7, 'Axis Bank', 9.00, 9.15, 0.65, 0, 4500, 5900, 4600, 5500, 0, NULL, 0, NULL, 0, 58850, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(17, 8, 'ICICI Bank', 8.90, 9.40, 0.60, 5000, 600, 7000, 2500, 4000, 2000, NULL, 0, NULL, 0, 40408, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(18, 9, 'ICICI Bank', 9.00, 9.15, 0.75, 5000, 1000, 6000, 2500, 7000, 0, NULL, 0, NULL, 0, 84350, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(19, 10, 'HDFC Bank', 7.20, 7.50, 0.25, 0, 3000, 5000, 3000, 7000, 0, NULL, 0, NULL, 0, 23605, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(20, 11, 'ICICI Bank', 7.55, 7.75, 0.15, 5000, 1500, 6000, 2500, 7000, 2500, NULL, 0, NULL, 0, 28940, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(21, 12, 'ICICI Bank', 9.05, 9.25, 0.65, 5900, 4500, 6000, 2500, 7000, 2500, NULL, 0, NULL, 0, 67812, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(22, 12, 'Kotak Mahindra Bank', 8.50, 9.55, 0.70, 0, 4500, 5900, 2500, 7000, 0, NULL, 0, NULL, 0, 61200, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(23, 13, 'HDFC Bank', 7.20, 7.40, 0.15, 0, 2500, 5000, 3000, 7000, 0, NULL, 0, NULL, 0, 23695, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(24, 14, 'ICICI Bank', 9.40, 9.45, 0.60, 5900, 4500, 6000, 2500, 7000, 2500, NULL, 0, NULL, 0, 57782, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(25, 15, 'HDFC Bank', 8.90, 9.00, 0.60, 0, 1500, 5900, 2500, 7000, 0, NULL, 0, NULL, 0, 38140, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(26, 15, 'ICICI Bank', 9.05, 9.15, 0.60, 5000, 1500, 5900, 2500, 7000, 2500, NULL, 0, NULL, 0, 46540, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(27, 15, 'Axis Bank', 9.15, 9.25, 0.65, 0, 2500, 5900, 2500, 7000, 0, NULL, 0, NULL, 0, 40910, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(28, 15, 'Kotak Mahindra Bank', 8.90, 9.00, 0.50, 0, 2500, 5900, 2500, 7000, 0, NULL, 0, NULL, 0, 35600, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(29, 16, 'ICICI Bank', 9.00, 9.05, 0.60, 5900, 1500, 5900, 2000, 7000, 2500, NULL, 0, NULL, 0, 40022, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(30, 17, 'ICICI Bank', 9.00, 9.05, 0.60, 5000, 1500, 5900, 2000, 7000, 2500, NULL, 0, NULL, 0, 38960, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(31, 18, 'Axis Bank', 7.90, 8.10, 0.50, 0, 2500, 5900, 1000, 7000, 4500, NULL, 0, NULL, 0, 30340, '2026-04-08 17:14:19', '2026-04-08 17:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `quotation_documents`
--

DROP TABLE IF EXISTS `quotation_documents`;
CREATE TABLE `quotation_documents` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `quotation_id` bigint(20) UNSIGNED NOT NULL,
  `document_name_en` varchar(255) NOT NULL,
  `document_name_gu` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `quotation_documents`
--

TRUNCATE TABLE `quotation_documents`;
--
-- Dumping data for table `quotation_documents`
--

INSERT INTO `quotation_documents` (`id`, `quotation_id`, `document_name_en`, `document_name_gu`, `created_at`, `updated_at`) VALUES
(1, 1, 'PAN Card of Proprietor', 'PAN Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(2, 1, 'Aadhaar Card of Proprietor', 'Aadhaar Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(3, 1, 'Business Address Proof', 'Business Address Proof', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(4, 1, 'Bank Statement (12 months)', 'Bank Statement (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(5, 1, 'ITR (Last 3 years)', 'ITR (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(6, 1, 'GST Registration Certificate', 'GST Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(7, 1, 'Shop & Establishment Certificate', 'Shop & Establishment Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(8, 1, 'Property Documents (if applicable)', 'Property Documents (if applicable)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(9, 1, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(10, 1, 'Passport Size Photographs', 'Passport Size Photographs', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(11, 2, 'PAN Card of Proprietor', 'PAN Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(12, 2, 'Aadhaar Card of Proprietor', 'Aadhaar Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(13, 2, 'Business Address Proof', 'Business Address Proof', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(14, 2, 'Bank Statement (12 months)', 'Bank Statement (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(15, 2, 'ITR (Last 3 years)', 'ITR (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(16, 2, 'GST Registration Certificate', 'GST Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(17, 2, 'Shop & Establishment Certificate', 'Shop & Establishment Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(18, 2, 'Property Documents (if applicable)', 'Property Documents (if applicable)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(19, 2, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(20, 2, 'Passport Size Photographs', 'Passport Size Photographs', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(21, 3, 'PAN Card of Proprietor', 'PAN Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(22, 3, 'Aadhaar Card of Proprietor', 'Aadhaar Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(23, 3, 'Business Address Proof', 'Business Address Proof', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(24, 3, 'Bank Statement (12 months)', 'Bank Statement (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(25, 3, 'ITR (Last 3 years)', 'ITR (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(26, 3, 'GST Registration Certificate', 'GST Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(27, 3, 'Shop & Establishment Certificate', 'Shop & Establishment Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(28, 3, 'Property Documents (if applicable)', 'Property Documents (if applicable)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(29, 3, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(30, 3, 'Passport Size Photographs', 'Passport Size Photographs', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(31, 4, 'PAN Card of Proprietor', 'PAN Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(32, 4, 'Aadhaar Card of Proprietor', 'Aadhaar Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(33, 4, 'Bank Statement (12 months)', 'Bank Statement (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(34, 4, 'ITR (Last 3 years)', 'ITR (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(35, 4, 'GST Registration Certificate', 'GST Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(36, 4, 'Property Documents (if applicable)', 'Property Documents (if applicable)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(37, 4, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(38, 4, 'Passport Size Photographs', 'Passport Size Photographs', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(39, 5, 'PAN Card of Proprietor', 'PAN Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(40, 5, 'Aadhaar Card of Proprietor', 'Aadhaar Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(41, 5, 'Business Address Proof', 'Business Address Proof', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(42, 5, 'Bank Statement (12 months)', 'Bank Statement (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(43, 5, 'ITR (Last 3 years)', 'ITR (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(44, 5, 'Shop & Establishment Certificate', 'Shop & Establishment Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(45, 5, 'Property Documents (if applicable)', 'Property Documents (if applicable)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(46, 5, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(47, 5, 'Passport Size Photographs', 'Passport Size Photographs', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(48, 6, 'PAN Card of Proprietor', 'PAN Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(49, 6, 'Aadhaar Card of Proprietor', 'Aadhaar Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(50, 6, 'Business Address Proof', 'Business Address Proof', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(51, 6, 'Bank Statement (12 months)', 'Bank Statement (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(52, 6, 'ITR (Last 3 years)', 'ITR (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(53, 6, 'Shop & Establishment Certificate', 'Shop & Establishment Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(54, 6, 'Property Documents (if applicable)', 'Property Documents (if applicable)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(55, 6, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(56, 6, 'Passport Size Photographs', 'Passport Size Photographs', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(57, 7, 'PAN Card of Proprietor', 'PAN Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(58, 7, 'Aadhaar Card of Proprietor', 'Aadhaar Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(59, 7, 'Business Address Proof', 'Business Address Proof', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(60, 7, 'Bank Statement (12 months)', 'Bank Statement (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(61, 7, 'Property Documents (if applicable)', 'Property Documents (if applicable)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(62, 7, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(63, 7, 'Passport Size Photographs', 'Passport Size Photographs', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(64, 8, 'PAN Card of Proprietor', 'PAN Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(65, 8, 'Aadhaar Card of Proprietor', 'Aadhaar Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(66, 8, 'Business Address Proof', 'Business Address Proof', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(67, 8, 'Bank Statement (12 months)', 'Bank Statement (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(68, 8, 'ITR (Last 3 years)', 'ITR (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(69, 8, 'GST Registration Certificate', 'GST Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(70, 8, 'Shop & Establishment Certificate', 'Shop & Establishment Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(71, 8, 'Property Documents (if applicable)', 'Property Documents (if applicable)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(72, 8, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(73, 8, 'Passport Size Photographs', 'Passport Size Photographs', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(74, 9, 'PAN Card of Proprietor', 'PAN Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(75, 9, 'Aadhaar Card of Proprietor', 'Aadhaar Card of Proprietor', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(76, 9, 'Business Address Proof', 'Business Address Proof', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(77, 9, 'Bank Statement (12 months)', 'Bank Statement (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(78, 9, 'ITR (Last 3 years)', 'ITR (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(79, 9, 'GST Registration Certificate', 'GST Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(80, 9, 'Shop & Establishment Certificate', 'Shop & Establishment Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(81, 9, 'Property Documents (if applicable)', 'Property Documents (if applicable)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(82, 9, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(83, 9, 'Passport Size Photographs', 'Passport Size Photographs', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(84, 10, 'PAN Card Both', 'PAN Card Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(85, 10, 'Aadhaar Card Both', 'Aadhaar Card Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(86, 10, 'Bank Statement (12 months)', 'Bank Statement (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(87, 10, 'ITR (Last 3 years)', 'ITR (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(88, 10, 'GST Registration Certificate', 'GST Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(89, 10, 'Property File Xerox', 'Property File Xerox', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(90, 10, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(91, 10, 'Current Loan Statement ( if applicable )', 'Current Loan Statement ( if applicable )', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(92, 10, 'Passport Size Photographs', 'Passport Size Photographs', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(93, 11, 'PAN Card Both', 'PAN Card Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(94, 11, 'Aadhaar Card Both', 'Aadhaar Card Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(95, 11, 'Bank Statement (12 months)', 'Bank Statement (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(96, 11, 'ITR (Last 3 years)', 'ITR (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(97, 11, 'Property File Xerox', 'Property File Xerox', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(98, 11, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(99, 11, 'Current Loan Statement ( if applicable )', 'Current Loan Statement ( if applicable )', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(100, 11, 'Passport Size Photographs', 'Passport Size Photographs', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(101, 12, 'Passport Size Photographs of All Partners', 'Passport Size Photographs of All Partners', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(102, 12, 'PAN Card of Firm', 'PAN Card of Firm', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(103, 12, 'PAN Card of All Partners', 'PAN Card of All Partners', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(104, 12, 'Aadhaar Card of All Partners', 'Aadhaar Card of All Partners', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(105, 12, 'Bank Statement (12 months)', 'Bank Statement (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(106, 12, 'ITR of Firm (Last 3 years)', 'ITR of Firm (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(107, 12, 'ITR of Partners (Last 3 years)', 'ITR of Partners (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(108, 12, 'GST Registration Certificate', 'GST Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(109, 12, 'Board Resolution / Authority Letter', 'Board Resolution / Authority Letter', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(110, 12, 'Partnership Deed', 'Partnership Deed', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(111, 12, 'Firm Current A/c Bank Statement  (12 months)', 'Firm Current A/c Bank Statement  (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(112, 12, 'Passport Size Photographs of All Partners', 'Passport Size Photographs of All Partners', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(113, 13, 'Passport Size Photographs', 'Passport Size Photographs', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(114, 13, 'PAN Card Both', 'PAN Card Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(115, 13, 'Aadhaar Card Both', 'Aadhaar Card Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(116, 13, 'ITR (Last 3 years)', 'ITR (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(117, 13, 'GST Registration Certificate', 'GST Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(118, 13, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(119, 13, 'Current Loan Statement ( if applicable )', 'Current Loan Statement ( if applicable )', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(120, 13, 'Bank Statement (12 months)', 'Bank Statement (12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(121, 13, 'Property File Xerox', 'Property File Xerox', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(122, 14, 'Passport Size Photographs Both', 'Passport Size Photographs Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(123, 14, 'PAN Card Both', 'PAN Card Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(124, 14, 'Aadhaar Card Both', 'Aadhaar Card Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(125, 14, 'GST Registration Certificate', 'GST Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(126, 14, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(127, 14, 'Bank Statement (Last 12 months)', 'Bank Statement (Last 12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(128, 14, 'Current Loan Statement ( if applicable )', 'Current Loan Statement ( if applicable )', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(129, 15, 'Passport Size Photographs Both', 'Passport Size Photographs Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(130, 15, 'PAN Card Both', 'PAN Card Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(131, 15, 'Aadhaar Card Both', 'Aadhaar Card Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(132, 15, 'GST Registration Certificate', 'GST Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(133, 15, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(134, 15, 'ITR (Last 3 years)', 'ITR (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(135, 15, 'Bank Statement (Last 12 months)', 'Bank Statement (Last 12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(136, 15, 'Current Loan Statement ( if applicable )', 'Current Loan Statement ( if applicable )', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(137, 18, 'Passport Size Photographs Both', 'Passport Size Photographs Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(138, 18, 'PAN Card Both', 'PAN Card Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(139, 18, 'Aadhaar Card Both', 'Aadhaar Card Both', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(140, 18, 'GST Registration Certificate', 'GST Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(141, 18, 'Udyam Registration Certificate', 'Udyam Registration Certificate', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(142, 18, 'ITR (Last 3 years)', 'ITR (Last 3 years)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(143, 18, 'Bank Statement (Last 12 months)', 'Bank Statement (Last 12 months)', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(144, 18, 'Current Loan Statement ( if applicable )', 'Current Loan Statement ( if applicable )', '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(145, 18, 'Property File Xerox', 'Property File Xerox', '2026-04-08 17:14:19', '2026-04-08 17:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `quotation_emi`
--

DROP TABLE IF EXISTS `quotation_emi`;
CREATE TABLE `quotation_emi` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `quotation_bank_id` bigint(20) UNSIGNED NOT NULL,
  `tenure_years` int(11) NOT NULL,
  `monthly_emi` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `total_interest` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `total_payment` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `quotation_emi`
--

TRUNCATE TABLE `quotation_emi`;
--
-- Dumping data for table `quotation_emi`
--

INSERT INTO `quotation_emi` (`id`, `quotation_bank_id`, `tenure_years`, `monthly_emi`, `total_interest`, `total_payment`, `created_at`, `updated_at`) VALUES
(1, 1, 20, 33963, 3951225, 8151225, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(2, 2, 15, 53622, 3831946, 9651946, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(3, 2, 20, 46530, 5347271, 11167271, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(4, 3, 15, 53622, 3831946, 9651946, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(5, 3, 20, 46530, 5347271, 11167271, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(6, 4, 15, 53622, 3831946, 9651946, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(7, 4, 20, 46530, 5347271, 11167271, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(8, 5, 15, 53457, 3802300, 9622300, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(9, 5, 20, 46353, 5304760, 11124760, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(10, 6, 15, 53622, 3831946, 9651946, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(11, 6, 20, 46530, 5347271, 11167271, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(12, 7, 15, 53457, 3802300, 9622300, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(13, 7, 20, 46353, 5304760, 11124760, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(14, 8, 15, 25357, 2064200, 4564200, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(15, 9, 15, 25357, 2064200, 4564200, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(16, 10, 15, 25431, 2077594, 4577594, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(17, 11, 15, 25357, 2064200, 4564200, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(18, 12, 15, 25357, 2064200, 4564200, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(19, 13, 15, 25357, 2064200, 4564200, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(20, 14, 15, 25431, 2077594, 4577594, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(21, 15, 15, 25357, 2064200, 4564200, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(22, 16, 10, 63338, 2600546, 7600546, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(23, 16, 15, 50713, 4128399, 9128399, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(24, 17, 15, 26216, 2118968, 4718968, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(25, 17, 20, 23226, 2974221, 5574221, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(26, 18, 15, 70999, 5779759, 12779759, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(27, 19, 20, 14960, 1690313, 3590313, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(28, 20, 20, 16173, 1881536, 3881536, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(29, 21, 10, 63473, 2616792, 7616792, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(30, 21, 15, 50862, 4155188, 9155188, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(31, 22, 10, 61993, 2439141, 7439141, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(32, 22, 15, 49237, 3862656, 8862656, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(33, 23, 20, 27557, 3113734, 6613734, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(34, 24, 10, 51540, 2184833, 6184833, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(35, 24, 15, 41528, 3475033, 7475033, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(36, 25, 10, 37841, 1540868, 4540868, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(37, 25, 15, 30250, 2444963, 5444963, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(38, 26, 10, 38084, 1570075, 4570075, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(39, 26, 15, 30517, 2493113, 5493113, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(40, 27, 10, 38247, 1589604, 4589604, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(41, 27, 15, 30696, 2525329, 5525329, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(42, 28, 10, 37841, 1540868, 4540868, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(43, 28, 15, 30250, 2444963, 5444963, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(44, 29, 10, 25335, 1040219, 3040219, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(45, 29, 15, 20285, 1651360, 3651360, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(46, 30, 10, 25335, 1040219, 3040219, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(47, 30, 15, 20285, 1651360, 3651360, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(48, 31, 15, 15198, 1135678, 2735678, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(49, 31, 20, 13284, 1588073, 3188073, '2026-04-08 17:14:19', '2026-04-08 17:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `remarks`
--

DROP TABLE IF EXISTS `remarks`;
CREATE TABLE `remarks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED NOT NULL,
  `stage_key` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `remark` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `remarks`
--

TRUNCATE TABLE `remarks`;
-- --------------------------------------------------------

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `role` enum('super_admin','admin','staff','bank_employee') NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `role_permissions`
--

TRUNCATE TABLE `role_permissions`;
--
-- Dumping data for table `role_permissions`
--

INSERT INTO `role_permissions` (`id`, `role`, `permission_id`, `created_at`, `updated_at`) VALUES
(1, 'super_admin', 32, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(2, 'super_admin', 19, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(3, 'super_admin', 20, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(4, 'super_admin', 23, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(5, 'super_admin', 26, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(6, 'super_admin', 9, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(7, 'super_admin', 16, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(8, 'super_admin', 28, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(9, 'super_admin', 36, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(10, 'super_admin', 13, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(11, 'super_admin', 18, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(12, 'super_admin', 35, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(13, 'super_admin', 14, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(14, 'super_admin', 3, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(15, 'super_admin', 6, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(16, 'super_admin', 2, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(17, 'super_admin', 4, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(18, 'super_admin', 8, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(19, 'super_admin', 27, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(20, 'super_admin', 7, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(21, 'super_admin', 5, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(22, 'super_admin', 17, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(23, 'super_admin', 10, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(24, 'super_admin', 29, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(25, 'super_admin', 30, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(26, 'super_admin', 21, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(27, 'super_admin', 33, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(28, 'super_admin', 31, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(29, 'super_admin', 34, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(30, 'super_admin', 22, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(31, 'super_admin', 25, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(32, 'super_admin', 12, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(33, 'super_admin', 24, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(34, 'super_admin', 11, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(35, 'super_admin', 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(36, 'super_admin', 15, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(37, 'admin', 1, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(38, 'admin', 2, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(39, 'admin', 3, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(40, 'admin', 4, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(41, 'admin', 5, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(42, 'admin', 6, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(43, 'admin', 7, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(44, 'admin', 8, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(45, 'admin', 9, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(46, 'admin', 10, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(47, 'admin', 11, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(48, 'admin', 12, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(49, 'admin', 13, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(50, 'admin', 14, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(51, 'admin', 15, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(52, 'admin', 16, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(53, 'admin', 17, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(54, 'admin', 19, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(55, 'admin', 20, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(56, 'admin', 22, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(57, 'admin', 23, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(58, 'admin', 24, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(59, 'admin', 25, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(60, 'admin', 26, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(61, 'admin', 27, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(62, 'admin', 28, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(63, 'admin', 29, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(64, 'admin', 30, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(65, 'admin', 31, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(66, 'admin', 32, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(67, 'admin', 33, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(68, 'admin', 34, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(69, 'admin', 35, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(70, 'admin', 36, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(71, 'staff', 9, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(72, 'staff', 10, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(73, 'staff', 11, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(74, 'staff', 14, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(75, 'staff', 20, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(76, 'staff', 23, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(77, 'staff', 24, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(78, 'staff', 26, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(79, 'staff', 27, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(80, 'staff', 29, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(81, 'staff', 30, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(82, 'staff', 31, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(83, 'staff', 32, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(84, 'staff', 34, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(85, 'staff', 35, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(86, 'staff', 36, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(87, 'bank_employee', 20, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(88, 'bank_employee', 24, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(89, 'bank_employee', 32, '2026-04-08 17:14:19', '2026-04-08 17:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `sessions`
--

TRUNCATE TABLE `sessions`;
--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('3Gqh6srXR6B4ET27hsLrlAG4RBx6BF4aecEF7nNE', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoibTdxQnBieFZHSHk1Q3pMT0VTb1E3Z2swcGsxSmdrSlpoMzk3OFFTcCI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDk6Imh0dHBzOi8vbG9hbnByb3Bvc2FsLnRlc3QvYXBpL25vdGlmaWNhdGlvbnMvY291bnQiO3M6NToicm91dGUiO3M6MjM6ImFwaS5ub3RpZmljYXRpb25zLmNvdW50Ijt9czozOiJ1cmwiO2E6MDp7fXM6NTA6ImxvZ2luX3dlYl8zZGM3YTkxM2VmNWZkNGI4OTBlY2FiZTM0ODcwODU1NzNlMTZjZjgyIjtpOjE7fQ==', 1775719744),
('5BHF1XVfahDghf7Ud427E8AlbNWk4PZFyS6pT9lT', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/146.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiUTQ5NVBZN1poTFI1UXByR2hWSWVYWWZHTkt5b1IzR0dGbHI0SGtRSSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDk6Imh0dHBzOi8vbG9hbnByb3Bvc2FsLnRlc3QvYXBpL25vdGlmaWNhdGlvbnMvY291bnQiO3M6NToicm91dGUiO3M6MjM6ImFwaS5ub3RpZmljYXRpb25zLmNvdW50Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzNkYzdhOTEzZWY1ZmQ0Yjg5MGVjYWJlMzQ4NzA4NTU3M2UxNmNmODIiO2k6MTt9', 1775719461),
('icoYT7ojgQZzVYJHWl5CsAbqxsynvDGcbYfyvNE5', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiMXJnOW1VMTh4TU1WaVoya0lOVGg1OFhlQjhxbXhxRUVGSmFGdUFMTSI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjQ5OiJodHRwczovL2xvYW5wcm9wb3NhbC50ZXN0L2FwaS9ub3RpZmljYXRpb25zL2NvdW50IjtzOjU6InJvdXRlIjtzOjIzOiJhcGkubm90aWZpY2F0aW9ucy5jb3VudCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl8zZGM3YTkxM2VmNWZkNGI4OTBlY2FiZTM0ODcwODU1NzNlMTZjZjgyIjtpOjE7fQ==', 1775723959),
('ihKNTmn9v8PtqAeF7K9WaELrhxZGo7JqpDehfPD8', NULL, '::1', 'curl/8.18.0', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiRXBIRUhaRkxXVlhQN2txelRKaFpockxaUnd3NjNmWEJ5WHRmUkN0SyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHBzOi8vbG9hbnByb3Bvc2FsLnRlc3QvbG9naW4iO3M6NToicm91dGUiO3M6NToibG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX19', 1775719295),
('jfJVfJSXv83lKLhWMdKgIliwGo2bWJumsf4eloui', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiM0NXMlNzWVhrTXh6ZHpuN3dUMlJObGpKM0pJV3gzZEY3aWNkUUpZbSI7czozOiJ1cmwiO2E6MTp7czo4OiJpbnRlbmRlZCI7czozNToiaHR0cHM6Ly9sb2FucHJvcG9zYWwudGVzdC9kYXNoYm9hcmQiO31zOjk6Il9wcmV2aW91cyI7YToyOntzOjM6InVybCI7czozMToiaHR0cHM6Ly9sb2FucHJvcG9zYWwudGVzdC9sb2dpbiI7czo1OiJyb3V0ZSI7czo1OiJsb2dpbiI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fX0=', 1775720268),
('lOR2aC3OUo6gSK5erjZwqEA8q6MbRj4KYp7cnJ3j', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiYUgyOW81WHJoYlVZckJnZWxrS2U1VUpsTlF4Z1BOVlU4bzl0WFd3VSI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDk6Imh0dHBzOi8vbG9hbnByb3Bvc2FsLnRlc3QvYXBpL25vdGlmaWNhdGlvbnMvY291bnQiO3M6NToicm91dGUiO3M6MjM6ImFwaS5ub3RpZmljYXRpb25zLmNvdW50Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzNkYzdhOTEzZWY1ZmQ0Yjg5MGVjYWJlMzQ4NzA4NTU3M2UxNmNmODIiO2k6MTt9', 1775725125),
('LYlj5IlGv8tAWJYyMASxDRObIv1xQKzMcZDTQ8ye', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/146.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiWmpwSmJ0R0dqRktzSW1UQTEwbUV1UlkzNVhhM0l1SnJWQkNDdzU2MyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDk6Imh0dHBzOi8vbG9hbnByb3Bvc2FsLnRlc3QvYXBpL25vdGlmaWNhdGlvbnMvY291bnQiO3M6NToicm91dGUiO3M6MjM6ImFwaS5ub3RpZmljYXRpb25zLmNvdW50Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzNkYzdhOTEzZWY1ZmQ0Yjg5MGVjYWJlMzQ4NzA4NTU3M2UxNmNmODIiO2k6MTt9', 1775721780),
('OFuki8twmezbxOHZQvC3PZ1yDT5LyJmbzwUqLcXy', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/146.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiV0s3ZnZnc096NVpKUFg2TDUyREZqeFVIeXJEb0owNlp2UjlDN0lPQyI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDk6Imh0dHBzOi8vbG9hbnByb3Bvc2FsLnRlc3QvYXBpL25vdGlmaWNhdGlvbnMvY291bnQiO3M6NToicm91dGUiO3M6MjM6ImFwaS5ub3RpZmljYXRpb25zLmNvdW50Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzNkYzdhOTEzZWY1ZmQ0Yjg5MGVjYWJlMzQ4NzA4NTU3M2UxNmNmODIiO2k6MTt9', 1775722492),
('RdA1q4ObrN9AsXicjxFMOOkdjpvsq5wvEEJ9H6DW', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/146.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiWW5TSU5rRGJ2SlRUQUt5WGl5N1FHVzJVNE5EeWRJSGVwYmRlSFMzdiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDE6Imh0dHBzOi8vbG9hbnByb3Bvc2FsLnRlc3QvZm9yZ290LXBhc3N3b3JkIjtzOjU6InJvdXRlIjtzOjE2OiJwYXNzd29yZC5yZXF1ZXN0Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1775721641),
('rKkWgE5Ko92AYPt9grceAHhbctWiDzX684WI4QYD', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoieDcxWVZhS1dzYzNsSUZiY0VCckRHeWJQS1RYWEVYRlVpdEhIajBKMCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHBzOi8vbG9hbnByb3Bvc2FsLnRlc3QvbG9naW4iO3M6NToicm91dGUiO3M6NToibG9naW4iO31zOjY6Il9mbGFzaCI7YToyOntzOjM6Im9sZCI7YTowOnt9czozOiJuZXciO2E6MDp7fX1zOjM6InVybCI7YToxOntzOjg6ImludGVuZGVkIjtzOjM1OiJodHRwczovL2xvYW5wcm9wb3NhbC50ZXN0L2Rhc2hib2FyZCI7fX0=', 1775720060),
('S8VZsJJKXAdyx1OZE3AepD3QunJzAVMLBRFaCo7b', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/146.0.0.0 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiTTJ6b3JSa0lxZXJ5TjVKTUlxbnRRNG1xNDBKQTR3aXdNeERmSkRNOCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDE6Imh0dHBzOi8vbG9hbnByb3Bvc2FsLnRlc3QvZm9yZ290LXBhc3N3b3JkIjtzOjU6InJvdXRlIjtzOjE2OiJwYXNzd29yZC5yZXF1ZXN0Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1775722290),
('zireR1ZsdCXQKOYbPJcNpQ0VGMiBtJcrbrBCaTrY', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) HeadlessChrome/146.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiVG5uREtkVjhwRjhGQ01XUTB2VFl2TXBGanhEdnVOQVB5WEdPbVlQWiI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDk6Imh0dHBzOi8vbG9hbnByb3Bvc2FsLnRlc3QvYXBpL25vdGlmaWNhdGlvbnMvY291bnQiO3M6NToicm91dGUiO3M6MjM6ImFwaS5ub3RpZmljYXRpb25zLmNvdW50Ijt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo1MDoibG9naW5fd2ViXzNkYzdhOTEzZWY1ZmQ0Yjg5MGVjYWJlMzQ4NzA4NTU3M2UxNmNmODIiO2k6MTt9', 1775719259),
('ZkGhYkSRxdMMjqK2YTPoM674107EI6VE6Ifh3X4y', 1, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/146.0.0.0 Safari/537.36', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiM3NIQ3UzbzRnQUJldGowYXBlWnZrNTczWVpIUnc3RzBVMmdrOFpiUSI7czozOiJ1cmwiO2E6MDp7fXM6OToiX3ByZXZpb3VzIjthOjI6e3M6MzoidXJsIjtzOjQ5OiJodHRwczovL2xvYW5wcm9wb3NhbC50ZXN0L2FwaS9ub3RpZmljYXRpb25zL2NvdW50IjtzOjU6InJvdXRlIjtzOjIzOiJhcGkubm90aWZpY2F0aW9ucy5jb3VudCI7fXM6NjoiX2ZsYXNoIjthOjI6e3M6Mzoib2xkIjthOjA6e31zOjM6Im5ldyI7YTowOnt9fXM6NTA6ImxvZ2luX3dlYl8zZGM3YTkxM2VmNWZkNGI4OTBlY2FiZTM0ODcwODU1NzNlMTZjZjgyIjtpOjE7fQ==', 1775720350);

-- --------------------------------------------------------

--
-- Table structure for table `shf_notifications`
--

DROP TABLE IF EXISTS `shf_notifications`;
CREATE TABLE `shf_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT 'info',
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `loan_id` bigint(20) UNSIGNED DEFAULT NULL,
  `stage_key` varchar(255) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `shf_notifications`
--

TRUNCATE TABLE `shf_notifications`;
-- --------------------------------------------------------

--
-- Table structure for table `stages`
--

DROP TABLE IF EXISTS `stages`;
CREATE TABLE `stages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stage_key` varchar(255) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `stage_name_en` varchar(255) NOT NULL,
  `stage_name_gu` varchar(255) DEFAULT NULL,
  `sequence_order` int(11) NOT NULL,
  `is_parallel` tinyint(1) NOT NULL DEFAULT 0,
  `parent_stage_key` varchar(255) DEFAULT NULL,
  `stage_type` varchar(255) NOT NULL DEFAULT 'sequential',
  `description_en` text DEFAULT NULL,
  `description_gu` text DEFAULT NULL,
  `default_role` varchar(255) DEFAULT NULL,
  `sub_actions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `stages`
--

TRUNCATE TABLE `stages`;
--
-- Dumping data for table `stages`
--

INSERT INTO `stages` (`id`, `stage_key`, `is_enabled`, `stage_name_en`, `stage_name_gu`, `sequence_order`, `is_parallel`, `parent_stage_key`, `stage_type`, `description_en`, `description_gu`, `default_role`, `sub_actions`, `created_at`, `updated_at`) VALUES
(1, 'otc_clearance', 1, 'OTC Clearance', 'OTC Clearance', 11, 0, NULL, 'sequential', 'Cheque handover and OTC clearance', NULL, '[\"branch_manager\",\"loan_advisor\",\"office_employee\"]', NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(2, 'inquiry', 1, 'Loan Inquiry', 'Loan Inquiry', 1, 0, NULL, 'sequential', 'Initial customer and loan details entry', NULL, '[\"branch_manager\",\"loan_advisor\"]', NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(3, 'document_selection', 1, 'Document Selection', 'Document Selection', 2, 0, NULL, 'sequential', 'Select required documents for the loan', NULL, '[\"branch_manager\",\"loan_advisor\"]', NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(4, 'document_collection', 1, 'Document Collection', 'Document Collection', 3, 0, NULL, 'sequential', 'Collect and verify all required documents', NULL, '[\"branch_manager\",\"loan_advisor\"]', NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(5, 'parallel_processing', 1, 'Parallel Processing', 'Parallel Processing', 4, 1, NULL, 'parallel', 'Four parallel tracks processed simultaneously', NULL, NULL, NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(6, 'app_number', 1, 'Application Number', 'Application Number', 4, 0, 'parallel_processing', 'sequential', 'Enter bank application number', NULL, '[\"branch_manager\",\"loan_advisor\"]', NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(7, 'bsm_osv', 1, 'BSM/OSV Approval', 'BSM/OSV Approval', 4, 0, 'parallel_processing', 'sequential', 'Bank site and office verification', NULL, '[\"bank_employee\"]', NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(8, 'legal_verification', 1, 'Legal Verification', 'Legal Verification', 4, 0, 'parallel_processing', 'sequential', 'Legal document verification', NULL, '[\"branch_manager\",\"loan_advisor\"]', NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(9, 'technical_valuation', 1, 'Technical Valuation', 'Technical Valuation', 4, 0, 'parallel_processing', 'sequential', 'Property/asset technical valuation', NULL, '[\"branch_manager\",\"office_employee\"]', NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(10, 'rate_pf', 1, 'Rate & PF Request', 'Rate & PF Request', 5, 0, NULL, 'sequential', 'Request interest rate and processing fee from bank', NULL, '[\"branch_manager\",\"loan_advisor\"]', '[{\"key\":\"bank_rate_details\",\"name\":\"Bank Rate Details\",\"sequence\":1,\"roles\":[\"bank_employee\"],\"type\":\"form\",\"is_enabled\":true},{\"key\":\"processing_charges\",\"name\":\"Processing & Charges\",\"sequence\":2,\"roles\":[\"branch_manager\",\"loan_advisor\",\"office_employee\"],\"type\":\"form\",\"is_enabled\":true}]', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(11, 'sanction', 1, 'Sanction Letter', 'Sanction Letter', 6, 0, NULL, 'sequential', 'Bank issues sanction letter', NULL, '[\"branch_manager\",\"loan_advisor\"]', '[{\"key\":\"send_for_sanction\",\"name\":\"Send for Sanction Letter\",\"sequence\":1,\"roles\":[\"branch_manager\",\"loan_advisor\"],\"type\":\"action_button\",\"action\":\"send_for_sanction\",\"transfer_to_role\":\"bank_employee\",\"is_enabled\":true},{\"key\":\"sanction_generated\",\"name\":\"Sanction Letter Generated\",\"sequence\":2,\"roles\":[\"bank_employee\"],\"type\":\"action_button\",\"action\":\"sanction_generated\",\"transfer_to_role\":\"loan_advisor\",\"is_enabled\":true},{\"key\":\"sanction_details\",\"name\":\"Sanction Details\",\"sequence\":3,\"roles\":[\"branch_manager\",\"loan_advisor\"],\"type\":\"form\",\"is_enabled\":true}]', '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(12, 'docket', 1, 'Docket Login', 'Docket Login', 7, 0, NULL, 'sequential', 'Physical document processing and docket creation', NULL, '[\"branch_manager\",\"loan_advisor\",\"office_employee\"]', NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(13, 'kfs', 1, 'KFS Generation', 'KFS Generation', 8, 0, NULL, 'sequential', 'Key Fact Statement generation', NULL, '[\"branch_manager\",\"loan_advisor\",\"office_employee\"]', NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(14, 'esign', 1, 'E-Sign & eNACH', 'E-Sign & eNACH', 9, 0, NULL, 'sequential', 'Digital signature and eNACH mandate', NULL, '[\"branch_manager\",\"loan_advisor\",\"bank_employee\"]', NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(15, 'disbursement', 1, 'Disbursement', 'Disbursement', 10, 0, NULL, 'decision', 'Fund disbursement - transfer or cheque with OTC handling', NULL, '[\"branch_manager\",\"loan_advisor\"]', NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18'),
(16, 'property_valuation', 1, 'Property Valuation', 'Property Valuation', 4, 0, 'parallel_processing', 'sequential', 'Dedicated property valuation for LAP', NULL, '[\"branch_manager\",\"office_employee\"]', NULL, '2026-04-08 17:14:18', '2026-04-08 17:14:18');

-- --------------------------------------------------------

--
-- Table structure for table `stage_assignments`
--

DROP TABLE IF EXISTS `stage_assignments`;
CREATE TABLE `stage_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED NOT NULL,
  `stage_key` varchar(255) NOT NULL,
  `assigned_to` bigint(20) UNSIGNED DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `priority` varchar(255) NOT NULL DEFAULT 'normal',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `completed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `is_parallel_stage` tinyint(1) NOT NULL DEFAULT 0,
  `parent_stage_key` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `stage_assignments`
--

TRUNCATE TABLE `stage_assignments`;
--
-- Dumping data for table `stage_assignments`
--

INSERT INTO `stage_assignments` (`id`, `loan_id`, `stage_key`, `assigned_to`, `status`, `priority`, `started_at`, `completed_at`, `completed_by`, `is_parallel_stage`, `parent_stage_key`, `notes`, `created_at`, `updated_at`, `updated_by`) VALUES
(1, 1, 'otc_clearance', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:21:57', '2026-04-08 17:21:57', 1),
(2, 1, 'inquiry', NULL, 'completed', 'normal', '2026-04-08 17:21:57', '2026-04-08 17:21:57', 1, 0, NULL, NULL, '2026-04-08 17:21:57', '2026-04-08 17:21:57', 1),
(3, 1, 'document_selection', NULL, 'completed', 'normal', '2026-04-08 17:21:57', '2026-04-08 17:21:57', 1, 0, NULL, NULL, '2026-04-08 17:21:57', '2026-04-08 17:21:57', 1),
(4, 1, 'document_collection', 2, 'completed', 'normal', '2026-04-08 17:23:44', '2026-04-08 17:23:44', 1, 0, NULL, NULL, '2026-04-08 17:21:57', '2026-04-08 17:23:44', 1),
(5, 1, 'parallel_processing', NULL, 'in_progress', 'normal', '2026-04-08 17:23:44', NULL, NULL, 0, NULL, NULL, '2026-04-08 17:21:57', '2026-04-08 17:23:44', 1),
(6, 1, 'app_number', 2, 'completed', 'normal', '2026-04-08 17:23:44', '2026-04-08 17:24:16', 1, 1, 'parallel_processing', '{\"application_number\":\"Hbb nn\",\"docket_days_offset\":\"0\",\"custom_docket_date\":\"01\\/05\\/2026\",\"stageRemarks\":null}', '2026-04-08 17:21:57', '2026-04-08 17:24:16', 1),
(7, 1, 'bsm_osv', 15, 'in_progress', 'normal', '2026-04-08 17:23:44', NULL, NULL, 1, 'parallel_processing', NULL, '2026-04-08 17:21:57', '2026-04-08 17:23:44', 1),
(8, 1, 'legal_verification', 2, 'in_progress', 'normal', '2026-04-08 17:23:44', NULL, NULL, 1, 'parallel_processing', NULL, '2026-04-08 17:21:57', '2026-04-08 17:23:44', 1),
(9, 1, 'technical_valuation', 2, 'in_progress', 'normal', '2026-04-08 17:23:44', NULL, NULL, 1, 'parallel_processing', NULL, '2026-04-08 17:21:57', '2026-04-08 17:23:44', 1),
(10, 1, 'rate_pf', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:21:57', '2026-04-08 17:21:57', 1),
(11, 1, 'sanction', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:21:57', '2026-04-08 17:21:57', 1),
(12, 1, 'docket', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:21:57', '2026-04-08 17:21:57', 1),
(13, 1, 'kfs', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:21:57', '2026-04-08 17:21:57', 1),
(14, 1, 'esign', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:21:57', '2026-04-08 17:21:57', 1),
(15, 1, 'disbursement', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:21:57', '2026-04-08 17:21:57', 1),
(16, 2, 'otc_clearance', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:26:03', '2026-04-08 17:26:03', 1),
(17, 2, 'inquiry', NULL, 'completed', 'normal', '2026-04-08 17:26:03', '2026-04-08 17:26:03', 1, 0, NULL, NULL, '2026-04-08 17:26:03', '2026-04-08 17:26:03', 1),
(18, 2, 'document_selection', NULL, 'completed', 'normal', '2026-04-08 17:26:03', '2026-04-08 17:26:03', 1, 0, NULL, NULL, '2026-04-08 17:26:03', '2026-04-08 17:26:03', 1),
(19, 2, 'document_collection', 2, 'completed', 'normal', '2026-04-09 02:23:35', '2026-04-09 02:23:35', 1, 0, NULL, NULL, '2026-04-08 17:26:03', '2026-04-09 02:23:35', 1),
(20, 2, 'parallel_processing', NULL, 'in_progress', 'normal', '2026-04-09 02:23:35', NULL, NULL, 0, NULL, NULL, '2026-04-08 17:26:03', '2026-04-09 02:23:35', 1),
(21, 2, 'app_number', 2, 'in_progress', 'normal', '2026-04-09 02:23:35', NULL, NULL, 1, 'parallel_processing', NULL, '2026-04-08 17:26:03', '2026-04-09 02:23:35', 1),
(22, 2, 'bsm_osv', 21, 'in_progress', 'normal', '2026-04-09 02:23:35', NULL, NULL, 1, 'parallel_processing', NULL, '2026-04-08 17:26:03', '2026-04-09 02:23:35', 1),
(23, 2, 'legal_verification', 2, 'in_progress', 'normal', '2026-04-09 02:23:35', NULL, NULL, 1, 'parallel_processing', NULL, '2026-04-08 17:26:03', '2026-04-09 02:23:35', 1),
(24, 2, 'technical_valuation', 2, 'in_progress', 'normal', '2026-04-09 02:23:35', NULL, NULL, 1, 'parallel_processing', NULL, '2026-04-08 17:26:03', '2026-04-09 02:23:35', 1),
(25, 2, 'rate_pf', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:26:03', '2026-04-08 17:26:03', 1),
(26, 2, 'sanction', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:26:03', '2026-04-08 17:26:03', 1),
(27, 2, 'docket', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:26:03', '2026-04-08 17:26:03', 1),
(28, 2, 'kfs', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:26:03', '2026-04-08 17:26:03', 1),
(29, 2, 'esign', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:26:03', '2026-04-08 17:26:03', 1),
(30, 2, 'disbursement', NULL, 'pending', 'normal', NULL, NULL, NULL, 0, NULL, NULL, '2026-04-08 17:26:03', '2026-04-08 17:26:03', 1);

-- --------------------------------------------------------

--
-- Table structure for table `stage_queries`
--

DROP TABLE IF EXISTS `stage_queries`;
CREATE TABLE `stage_queries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stage_assignment_id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED NOT NULL,
  `stage_key` varchar(255) NOT NULL,
  `query_text` text NOT NULL,
  `raised_by` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `stage_queries`
--

TRUNCATE TABLE `stage_queries`;
-- --------------------------------------------------------

--
-- Table structure for table `stage_transfers`
--

DROP TABLE IF EXISTS `stage_transfers`;
CREATE TABLE `stage_transfers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `stage_assignment_id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED NOT NULL,
  `stage_key` varchar(255) NOT NULL,
  `transferred_from` bigint(20) UNSIGNED NOT NULL,
  `transferred_to` bigint(20) UNSIGNED NOT NULL,
  `reason` text DEFAULT NULL,
  `transfer_type` varchar(255) NOT NULL DEFAULT 'manual',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `stage_transfers`
--

TRUNCATE TABLE `stage_transfers`;
--
-- Dumping data for table `stage_transfers`
--

INSERT INTO `stage_transfers` (`id`, `stage_assignment_id`, `loan_id`, `stage_key`, `transferred_from`, `transferred_to`, `reason`, `transfer_type`, `created_at`) VALUES
(1, 4, 1, 'document_collection', 1, 2, 'Auto-assigned on stage advance', 'auto', '2026-04-08 17:21:57'),
(2, 19, 2, 'document_collection', 1, 2, 'Auto-assigned on stage advance', 'auto', '2026-04-08 17:26:03');

-- --------------------------------------------------------

--
-- Table structure for table `task_role_permissions`
--

DROP TABLE IF EXISTS `task_role_permissions`;
CREATE TABLE `task_role_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `task_role` varchar(255) NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `task_role_permissions`
--

TRUNCATE TABLE `task_role_permissions`;
--
-- Dumping data for table `task_role_permissions`
--

INSERT INTO `task_role_permissions` (`id`, `task_role`, `permission_id`, `created_at`, `updated_at`) VALUES
(1, 'branch_manager', 23, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(2, 'branch_manager', 24, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(3, 'branch_manager', 25, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(4, 'branch_manager', 26, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(5, 'branch_manager', 27, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(6, 'branch_manager', 29, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(7, 'branch_manager', 31, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(8, 'branch_manager', 32, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(9, 'loan_advisor', 23, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(10, 'loan_advisor', 24, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(11, 'loan_advisor', 25, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(12, 'loan_advisor', 26, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(13, 'loan_advisor', 27, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(14, 'loan_advisor', 29, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(15, 'loan_advisor', 31, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(16, 'loan_advisor', 32, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(17, 'bank_employee', 24, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(18, 'bank_employee', 32, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(19, 'office_employee', 23, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(20, 'office_employee', 24, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(21, 'office_employee', 26, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(22, 'office_employee', 27, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(23, 'office_employee', 29, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(24, 'office_employee', 31, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(25, 'office_employee', 32, '2026-04-08 17:14:19', '2026-04-08 17:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('super_admin','admin','staff','bank_employee') NOT NULL DEFAULT 'staff',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `task_role` varchar(255) DEFAULT NULL,
  `employee_id` varchar(255) DEFAULT NULL,
  `default_branch_id` bigint(20) UNSIGNED DEFAULT NULL,
  `task_bank_id` bigint(20) UNSIGNED DEFAULT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `users`
--

TRUNCATE TABLE `users`;
--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `email_verified_at`, `password`, `role`, `is_active`, `created_by`, `phone`, `task_role`, `employee_id`, `default_branch_id`, `task_bank_id`, `remember_token`, `created_at`, `updated_at`) VALUES
(1, 'Super Admin', 'admin@shf.com', NULL, '$2y$12$cVHa5YDfwYVWr/fNhDECQ.e282bu12sPFKgE679BDXtnwxmDPLzCi', 'super_admin', 1, NULL, '+91 99747 89089', NULL, NULL, 1, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(2, 'Denish Malviya', 'denish@shfworld.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'admin', 1, NULL, '+91 99747 89089', 'branch_manager', NULL, 1, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(3, 'JAYDEEP THESHIYA', 'jaydeep@shfworld.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'staff', 1, 2, '9725248300', 'loan_advisor', NULL, 1, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(4, 'KULDEEP VAISHNAV', 'kuldeep@shfworld.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'staff', 1, 2, '8866236688', 'loan_advisor', NULL, 1, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(5, 'HARDIK NASIT', 'hardik@shfworld.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'staff', 1, 2, '9726179351', 'loan_advisor', NULL, 1, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(6, 'RAHUL MARAKANA', 'rahul@shfworld.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'staff', 1, 2, '9913744162', 'loan_advisor', NULL, 1, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(7, 'DIPAK VIRANI', 'dipak@shfworld.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'staff', 1, 2, '7600143537', 'loan_advisor', NULL, 1, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(8, 'JAYESH MORI', 'jayesh@shfworld.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'staff', 1, 2, '8000232586', 'loan_advisor', NULL, 1, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(9, 'CHIRAG DHOLAKIYA', 'chirag@shfworld.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'staff', 1, 2, '9016348138', 'loan_advisor', NULL, 1, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(10, 'DAXIT MALAVIYA', 'daxit@shfworld.som', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'staff', 1, 2, '81600000286', 'loan_advisor', NULL, 1, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(11, 'MILAN DHOLAKIYA', 'milan@shfworld.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'staff', 1, 2, '8401277654', 'loan_advisor', NULL, 1, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(12, 'NITIN FALDU', 'nitin@shfworld.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'staff', 1, 2, '968701525', 'loan_advisor', NULL, 1, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(13, 'KRUPALI SHILU', 'krupali@shfworld.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'staff', 1, 2, '9099089072', 'loan_advisor', NULL, 1, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(14, 'HDFC Employee 1', 'hdfc@manager.cop', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'bank_employee', 1, 1, NULL, 'bank_employee', NULL, NULL, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(15, 'HDFC Employee 2', 'hdfc@manager2.cop', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'bank_employee', 1, 1, NULL, 'bank_employee', NULL, NULL, NULL, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(16, 'Kotak Employee 1', 'kotak@manager.cop', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'bank_employee', 1, 1, 'hdfc@manager2.cop', 'bank_employee', NULL, NULL, 4, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(17, 'Kotak Employee 2', 'kotak@manager2.cop', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'bank_employee', 1, 1, NULL, 'bank_employee', NULL, NULL, 4, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(18, 'Axix Employee 1', 'axis@manager.cop', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'bank_employee', 1, 1, NULL, 'bank_employee', NULL, NULL, 3, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(19, 'Axix Employee 2', 'axis@manager2.cop', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'bank_employee', 1, 1, NULL, 'bank_employee', NULL, NULL, 3, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(20, 'ICICI Employee 1', 'icici@manager.cop', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'bank_employee', 1, 1, NULL, 'bank_employee', NULL, NULL, 2, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(21, 'ICICI Employee 2', 'icici@manager2.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'bank_employee', 1, 1, NULL, 'bank_employee', NULL, NULL, 2, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(22, 'Office Employee1', 'vipul@office.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'staff', 1, 1, '+91 99747 89089', 'office_employee', NULL, 1, 1, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19'),
(23, 'Office Employee2', 'officeemployee2@shfworld.com', NULL, '$2y$12$9no1UBDZ3jkvwPCK/MN1c.En.eEfKhA6E7tr9Q1RCn.hSHUqzapXG', 'staff', 1, 1, NULL, 'office_employee', NULL, 1, 1, NULL, '2026-04-08 17:14:19', '2026-04-08 17:14:19');

-- --------------------------------------------------------

--
-- Table structure for table `user_branches`
--

DROP TABLE IF EXISTS `user_branches`;
CREATE TABLE `user_branches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` bigint(20) UNSIGNED NOT NULL,
  `is_default_office_employee` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `user_branches`
--

TRUNCATE TABLE `user_branches`;
--
-- Dumping data for table `user_branches`
--

INSERT INTO `user_branches` (`id`, `user_id`, `branch_id`, `is_default_office_employee`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 0, NULL, NULL),
(2, 2, 1, 0, NULL, NULL),
(3, 3, 1, 0, NULL, NULL),
(4, 4, 1, 0, NULL, NULL),
(5, 5, 1, 0, NULL, NULL),
(6, 6, 1, 0, NULL, NULL),
(7, 7, 1, 0, NULL, NULL),
(8, 8, 1, 0, NULL, NULL),
(9, 9, 1, 0, NULL, NULL),
(10, 10, 1, 0, NULL, NULL),
(11, 11, 1, 0, NULL, NULL),
(12, 12, 1, 0, NULL, NULL),
(13, 13, 1, 0, NULL, NULL),
(14, 22, 1, 0, NULL, NULL),
(15, 23, 1, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_permissions`
--

DROP TABLE IF EXISTS `user_permissions`;
CREATE TABLE `user_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `permission_id` bigint(20) UNSIGNED NOT NULL,
  `type` enum('grant','deny') NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `user_permissions`
--

TRUNCATE TABLE `user_permissions`;
-- --------------------------------------------------------

--
-- Table structure for table `valuation_details`
--

DROP TABLE IF EXISTS `valuation_details`;
CREATE TABLE `valuation_details` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `loan_id` bigint(20) UNSIGNED NOT NULL,
  `valuation_type` varchar(255) NOT NULL DEFAULT 'property',
  `property_address` text DEFAULT NULL,
  `latitude` varchar(50) DEFAULT NULL,
  `longitude` varchar(50) DEFAULT NULL,
  `property_type` varchar(255) DEFAULT NULL,
  `land_area` varchar(255) DEFAULT NULL,
  `land_rate` decimal(12,2) DEFAULT NULL,
  `land_valuation` bigint(20) UNSIGNED DEFAULT NULL,
  `construction_area` varchar(255) DEFAULT NULL,
  `construction_rate` decimal(12,2) DEFAULT NULL,
  `construction_valuation` bigint(20) UNSIGNED DEFAULT NULL,
  `final_valuation` bigint(20) UNSIGNED DEFAULT NULL,
  `market_value` bigint(20) UNSIGNED DEFAULT NULL,
  `government_value` bigint(20) UNSIGNED DEFAULT NULL,
  `valuation_date` date DEFAULT NULL,
  `valuator_name` varchar(255) DEFAULT NULL,
  `valuator_report_number` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Truncate table before insert `valuation_details`
--

TRUNCATE TABLE `valuation_details`;
--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_logs_subject_type_subject_id_index` (`subject_type`,`subject_id`),
  ADD KEY `activity_logs_user_id_index` (`user_id`),
  ADD KEY `activity_logs_created_at_index` (`created_at`);

--
-- Indexes for table `app_config`
--
ALTER TABLE `app_config`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `app_config_config_key_unique` (`config_key`);

--
-- Indexes for table `app_settings`
--
ALTER TABLE `app_settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `banks`
--
ALTER TABLE `banks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `banks_name_unique` (`name`),
  ADD KEY `banks_updated_by_foreign` (`updated_by`),
  ADD KEY `banks_deleted_by_foreign` (`deleted_by`),
  ADD KEY `banks_default_employee_id_foreign` (`default_employee_id`);

--
-- Indexes for table `bank_charges`
--
ALTER TABLE `bank_charges`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `bank_employees`
--
ALTER TABLE `bank_employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bank_employees_bank_id_user_id_unique` (`bank_id`,`user_id`),
  ADD KEY `bank_employees_user_id_foreign` (`user_id`);

--
-- Indexes for table `bank_location`
--
ALTER TABLE `bank_location`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bank_location_bank_id_location_id_unique` (`bank_id`,`location_id`),
  ADD KEY `bank_location_location_id_foreign` (`location_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `branches_code_unique` (`code`),
  ADD KEY `branches_updated_by_foreign` (`updated_by`),
  ADD KEY `branches_deleted_by_foreign` (`deleted_by`),
  ADD KEY `branches_manager_id_foreign` (`manager_id`),
  ADD KEY `branches_location_id_foreign` (`location_id`);

--
-- Indexes for table `cache`
--
ALTER TABLE `cache`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_expiration_index` (`expiration`);

--
-- Indexes for table `cache_locks`
--
ALTER TABLE `cache_locks`
  ADD PRIMARY KEY (`key`),
  ADD KEY `cache_locks_expiration_index` (`expiration`);

--
-- Indexes for table `disbursement_details`
--
ALTER TABLE `disbursement_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `disbursement_details_loan_id_unique` (`loan_id`),
  ADD KEY `disbursement_details_otc_cleared_by_foreign` (`otc_cleared_by`),
  ADD KEY `disbursement_details_updated_by_foreign` (`updated_by`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `jobs`
--
ALTER TABLE `jobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jobs_queue_index` (`queue`);

--
-- Indexes for table `job_batches`
--
ALTER TABLE `job_batches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `loan_details`
--
ALTER TABLE `loan_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `loan_details_loan_number_unique` (`loan_number`),
  ADD KEY `loan_details_quotation_id_foreign` (`quotation_id`),
  ADD KEY `loan_details_branch_id_foreign` (`branch_id`),
  ADD KEY `loan_details_bank_id_foreign` (`bank_id`),
  ADD KEY `loan_details_product_id_foreign` (`product_id`),
  ADD KEY `loan_details_assigned_bank_employee_foreign` (`assigned_bank_employee`),
  ADD KEY `loan_details_rejected_by_foreign` (`rejected_by`),
  ADD KEY `loan_details_created_by_foreign` (`created_by`),
  ADD KEY `loan_details_assigned_advisor_foreign` (`assigned_advisor`),
  ADD KEY `loan_details_status_index` (`status`),
  ADD KEY `loan_details_current_stage_index` (`current_stage`),
  ADD KEY `loan_details_customer_type_index` (`customer_type`),
  ADD KEY `loan_details_updated_by_foreign` (`updated_by`),
  ADD KEY `loan_details_deleted_by_foreign` (`deleted_by`),
  ADD KEY `loan_details_location_id_foreign` (`location_id`);

--
-- Indexes for table `loan_documents`
--
ALTER TABLE `loan_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `loan_documents_received_by_foreign` (`received_by`),
  ADD KEY `loan_documents_loan_id_index` (`loan_id`),
  ADD KEY `loan_documents_loan_id_status_index` (`loan_id`,`status`),
  ADD KEY `loan_documents_uploaded_by_foreign` (`uploaded_by`),
  ADD KEY `loan_documents_updated_by_foreign` (`updated_by`);

--
-- Indexes for table `loan_progress`
--
ALTER TABLE `loan_progress`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `loan_progress_loan_id_unique` (`loan_id`);

--
-- Indexes for table `locations`
--
ALTER TABLE `locations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `locations_name_parent_id_unique` (`name`,`parent_id`),
  ADD KEY `locations_parent_id_foreign` (`parent_id`);

--
-- Indexes for table `location_product`
--
ALTER TABLE `location_product`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `location_product_location_id_product_id_unique` (`location_id`,`product_id`),
  ADD KEY `location_product_product_id_foreign` (`product_id`);

--
-- Indexes for table `location_user`
--
ALTER TABLE `location_user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `location_user_location_id_user_id_unique` (`location_id`,`user_id`),
  ADD KEY `location_user_user_id_foreign` (`user_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permissions_slug_unique` (`slug`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `products_bank_id_name_unique` (`bank_id`,`name`),
  ADD KEY `products_updated_by_foreign` (`updated_by`),
  ADD KEY `products_deleted_by_foreign` (`deleted_by`);

--
-- Indexes for table `product_stages`
--
ALTER TABLE `product_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_stages_product_id_stage_id_unique` (`product_id`,`stage_id`),
  ADD KEY `product_stages_stage_id_foreign` (`stage_id`),
  ADD KEY `product_stages_updated_by_foreign` (`updated_by`),
  ADD KEY `product_stages_default_user_id_foreign` (`default_user_id`);

--
-- Indexes for table `product_stage_users`
--
ALTER TABLE `product_stage_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_stage_users_product_stage_id_branch_id_unique` (`product_stage_id`,`branch_id`),
  ADD KEY `product_stage_users_branch_id_foreign` (`branch_id`),
  ADD KEY `product_stage_users_user_id_foreign` (`user_id`),
  ADD KEY `product_stage_users_location_id_foreign` (`location_id`);

--
-- Indexes for table `query_responses`
--
ALTER TABLE `query_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `query_responses_stage_query_id_foreign` (`stage_query_id`),
  ADD KEY `query_responses_responded_by_foreign` (`responded_by`);

--
-- Indexes for table `quotations`
--
ALTER TABLE `quotations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotations_user_id_index` (`user_id`),
  ADD KEY `quotations_created_at_index` (`created_at`),
  ADD KEY `quotations_loan_id_foreign` (`loan_id`),
  ADD KEY `quotations_updated_by_foreign` (`updated_by`),
  ADD KEY `quotations_deleted_by_foreign` (`deleted_by`),
  ADD KEY `quotations_location_id_foreign` (`location_id`);

--
-- Indexes for table `quotation_banks`
--
ALTER TABLE `quotation_banks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_banks_quotation_id_foreign` (`quotation_id`);

--
-- Indexes for table `quotation_documents`
--
ALTER TABLE `quotation_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_documents_quotation_id_foreign` (`quotation_id`);

--
-- Indexes for table `quotation_emi`
--
ALTER TABLE `quotation_emi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quotation_emi_quotation_bank_id_foreign` (`quotation_bank_id`);

--
-- Indexes for table `remarks`
--
ALTER TABLE `remarks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `remarks_user_id_foreign` (`user_id`),
  ADD KEY `remarks_loan_id_index` (`loan_id`),
  ADD KEY `remarks_stage_key_index` (`stage_key`);

--
-- Indexes for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_permissions_role_permission_id_unique` (`role`,`permission_id`),
  ADD KEY `role_permissions_permission_id_foreign` (`permission_id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `shf_notifications`
--
ALTER TABLE `shf_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `shf_notifications_user_id_is_read_index` (`user_id`,`is_read`),
  ADD KEY `shf_notifications_loan_id_index` (`loan_id`);

--
-- Indexes for table `stages`
--
ALTER TABLE `stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stages_stage_key_unique` (`stage_key`),
  ADD KEY `stages_sequence_order_index` (`sequence_order`),
  ADD KEY `stages_parent_stage_key_index` (`parent_stage_key`);

--
-- Indexes for table `stage_assignments`
--
ALTER TABLE `stage_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stage_assignments_loan_id_stage_key_unique` (`loan_id`,`stage_key`),
  ADD KEY `stage_assignments_completed_by_foreign` (`completed_by`),
  ADD KEY `stage_assignments_stage_key_index` (`stage_key`),
  ADD KEY `stage_assignments_assigned_to_index` (`assigned_to`),
  ADD KEY `stage_assignments_status_index` (`status`),
  ADD KEY `stage_assignments_parent_stage_key_index` (`parent_stage_key`),
  ADD KEY `stage_assignments_updated_by_foreign` (`updated_by`);

--
-- Indexes for table `stage_queries`
--
ALTER TABLE `stage_queries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stage_queries_raised_by_foreign` (`raised_by`),
  ADD KEY `stage_queries_resolved_by_foreign` (`resolved_by`),
  ADD KEY `stage_queries_stage_assignment_id_status_index` (`stage_assignment_id`,`status`),
  ADD KEY `stage_queries_loan_id_index` (`loan_id`);

--
-- Indexes for table `stage_transfers`
--
ALTER TABLE `stage_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stage_transfers_stage_assignment_id_foreign` (`stage_assignment_id`),
  ADD KEY `stage_transfers_loan_id_foreign` (`loan_id`),
  ADD KEY `stage_transfers_transferred_from_foreign` (`transferred_from`),
  ADD KEY `stage_transfers_transferred_to_foreign` (`transferred_to`);

--
-- Indexes for table `task_role_permissions`
--
ALTER TABLE `task_role_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `task_role_permissions_task_role_permission_id_unique` (`task_role`,`permission_id`),
  ADD KEY `task_role_permissions_permission_id_foreign` (`permission_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD KEY `users_created_by_foreign` (`created_by`),
  ADD KEY `users_default_branch_id_foreign` (`default_branch_id`),
  ADD KEY `users_task_bank_id_foreign` (`task_bank_id`);

--
-- Indexes for table `user_branches`
--
ALTER TABLE `user_branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_branches_user_id_branch_id_unique` (`user_id`,`branch_id`),
  ADD KEY `user_branches_branch_id_foreign` (`branch_id`);

--
-- Indexes for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_permissions_user_id_permission_id_unique` (`user_id`,`permission_id`),
  ADD KEY `user_permissions_permission_id_foreign` (`permission_id`);

--
-- Indexes for table `valuation_details`
--
ALTER TABLE `valuation_details`
  ADD PRIMARY KEY (`id`),
  ADD KEY `valuation_details_loan_id_index` (`loan_id`),
  ADD KEY `valuation_details_updated_by_foreign` (`updated_by`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `app_config`
--
ALTER TABLE `app_config`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `banks`
--
ALTER TABLE `banks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bank_charges`
--
ALTER TABLE `bank_charges`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `bank_employees`
--
ALTER TABLE `bank_employees`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `bank_location`
--
ALTER TABLE `bank_location`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `disbursement_details`
--
ALTER TABLE `disbursement_details`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loan_details`
--
ALTER TABLE `loan_details`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `loan_documents`
--
ALTER TABLE `loan_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `loan_progress`
--
ALTER TABLE `loan_progress`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `locations`
--
ALTER TABLE `locations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `location_product`
--
ALTER TABLE `location_product`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `location_user`
--
ALTER TABLE `location_user`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `product_stages`
--
ALTER TABLE `product_stages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_stage_users`
--
ALTER TABLE `product_stage_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `query_responses`
--
ALTER TABLE `query_responses`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `quotations`
--
ALTER TABLE `quotations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `quotation_banks`
--
ALTER TABLE `quotation_banks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `quotation_documents`
--
ALTER TABLE `quotation_documents`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

--
-- AUTO_INCREMENT for table `quotation_emi`
--
ALTER TABLE `quotation_emi`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `remarks`
--
ALTER TABLE `remarks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_permissions`
--
ALTER TABLE `role_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `shf_notifications`
--
ALTER TABLE `shf_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stages`
--
ALTER TABLE `stages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `stage_assignments`
--
ALTER TABLE `stage_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `stage_queries`
--
ALTER TABLE `stage_queries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `stage_transfers`
--
ALTER TABLE `stage_transfers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `task_role_permissions`
--
ALTER TABLE `task_role_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=24;

--
-- AUTO_INCREMENT for table `user_branches`
--
ALTER TABLE `user_branches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `valuation_details`
--
ALTER TABLE `valuation_details`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `banks`
--
ALTER TABLE `banks`
  ADD CONSTRAINT `banks_default_employee_id_foreign` FOREIGN KEY (`default_employee_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `banks_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `banks_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bank_employees`
--
ALTER TABLE `bank_employees`
  ADD CONSTRAINT `bank_employees_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bank_employees_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bank_location`
--
ALTER TABLE `bank_location`
  ADD CONSTRAINT `bank_location_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bank_location_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `branches_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `branches_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `branches_manager_id_foreign` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `branches_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `disbursement_details`
--
ALTER TABLE `disbursement_details`
  ADD CONSTRAINT `disbursement_details_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan_details` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `disbursement_details_otc_cleared_by_foreign` FOREIGN KEY (`otc_cleared_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `disbursement_details_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `loan_details`
--
ALTER TABLE `loan_details`
  ADD CONSTRAINT `loan_details_assigned_advisor_foreign` FOREIGN KEY (`assigned_advisor`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_details_assigned_bank_employee_foreign` FOREIGN KEY (`assigned_bank_employee`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_details_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_details_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_details_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `loan_details_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_details_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_details_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_details_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_details_rejected_by_foreign` FOREIGN KEY (`rejected_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_details_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `loan_documents`
--
ALTER TABLE `loan_documents`
  ADD CONSTRAINT `loan_documents_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan_details` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `loan_documents_received_by_foreign` FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_documents_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `loan_documents_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `loan_progress`
--
ALTER TABLE `loan_progress`
  ADD CONSTRAINT `loan_progress_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan_details` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `locations`
--
ALTER TABLE `locations`
  ADD CONSTRAINT `locations_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `location_product`
--
ALTER TABLE `location_product`
  ADD CONSTRAINT `location_product_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `location_product_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `location_user`
--
ALTER TABLE `location_user`
  ADD CONSTRAINT `location_user_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `location_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `products_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_stages`
--
ALTER TABLE `product_stages`
  ADD CONSTRAINT `product_stages_default_user_id_foreign` FOREIGN KEY (`default_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_stages_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_stages_stage_id_foreign` FOREIGN KEY (`stage_id`) REFERENCES `stages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_stages_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_stage_users`
--
ALTER TABLE `product_stage_users`
  ADD CONSTRAINT `product_stage_users_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_stage_users_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `product_stage_users_product_stage_id_foreign` FOREIGN KEY (`product_stage_id`) REFERENCES `product_stages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_stage_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `query_responses`
--
ALTER TABLE `query_responses`
  ADD CONSTRAINT `query_responses_responded_by_foreign` FOREIGN KEY (`responded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `query_responses_stage_query_id_foreign` FOREIGN KEY (`stage_query_id`) REFERENCES `stage_queries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quotations`
--
ALTER TABLE `quotations`
  ADD CONSTRAINT `quotations_deleted_by_foreign` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `quotations_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan_details` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `quotations_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `quotations_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `quotations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quotation_banks`
--
ALTER TABLE `quotation_banks`
  ADD CONSTRAINT `quotation_banks_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quotation_documents`
--
ALTER TABLE `quotation_documents`
  ADD CONSTRAINT `quotation_documents_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `quotations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `quotation_emi`
--
ALTER TABLE `quotation_emi`
  ADD CONSTRAINT `quotation_emi_quotation_bank_id_foreign` FOREIGN KEY (`quotation_bank_id`) REFERENCES `quotation_banks` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `remarks`
--
ALTER TABLE `remarks`
  ADD CONSTRAINT `remarks_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan_details` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `remarks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `role_permissions`
--
ALTER TABLE `role_permissions`
  ADD CONSTRAINT `role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shf_notifications`
--
ALTER TABLE `shf_notifications`
  ADD CONSTRAINT `shf_notifications_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan_details` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `shf_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stage_assignments`
--
ALTER TABLE `stage_assignments`
  ADD CONSTRAINT `stage_assignments_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stage_assignments_completed_by_foreign` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stage_assignments_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan_details` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stage_assignments_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `stage_queries`
--
ALTER TABLE `stage_queries`
  ADD CONSTRAINT `stage_queries_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan_details` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stage_queries_raised_by_foreign` FOREIGN KEY (`raised_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stage_queries_resolved_by_foreign` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `stage_queries_stage_assignment_id_foreign` FOREIGN KEY (`stage_assignment_id`) REFERENCES `stage_assignments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `stage_transfers`
--
ALTER TABLE `stage_transfers`
  ADD CONSTRAINT `stage_transfers_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan_details` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stage_transfers_stage_assignment_id_foreign` FOREIGN KEY (`stage_assignment_id`) REFERENCES `stage_assignments` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stage_transfers_transferred_from_foreign` FOREIGN KEY (`transferred_from`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `stage_transfers_transferred_to_foreign` FOREIGN KEY (`transferred_to`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `task_role_permissions`
--
ALTER TABLE `task_role_permissions`
  ADD CONSTRAINT `task_role_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_default_branch_id_foreign` FOREIGN KEY (`default_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_task_bank_id_foreign` FOREIGN KEY (`task_bank_id`) REFERENCES `banks` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_branches`
--
ALTER TABLE `user_branches`
  ADD CONSTRAINT `user_branches_branch_id_foreign` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_branches_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `valuation_details`
--
ALTER TABLE `valuation_details`
  ADD CONSTRAINT `valuation_details_loan_id_foreign` FOREIGN KEY (`loan_id`) REFERENCES `loan_details` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `valuation_details_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
