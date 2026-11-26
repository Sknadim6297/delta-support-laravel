-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 26, 2026 at 09:41 AM
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
-- Database: `delta_support`
--

-- --------------------------------------------------------

--
-- Table structure for table `activation_wallet_transactions`
--

CREATE TABLE `activation_wallet_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `amount` decimal(20,4) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'credit',
  `remarks` varchar(255) DEFAULT NULL,
  `reference` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `admin_activity_logs`
--

CREATE TABLE `admin_activity_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(40) NOT NULL,
  `subject` varchar(40) NOT NULL,
  `format` varchar(8) DEFAULT NULL,
  `scope` varchar(16) DEFAULT NULL,
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `row_count` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_activity_logs`
--

INSERT INTO `admin_activity_logs` (`id`, `user_id`, `action`, `subject`, `format`, `scope`, `filters`, `row_count`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin_login', 'admin', NULL, 'admin', '[]', 1, '2026-11-23 04:06:29', '2026-11-23 04:06:29'),
(2, 1, 'package_created', 'package:DST-TOUR-001', 'admin', 'packages', '{\"package_code\":\"DST-TOUR-001\"}', 1, '2026-11-23 04:21:14', '2026-11-23 04:21:14'),
(3, 1, 'customer_package_purchase', 'PUR-20261123-NIZ4KE', NULL, 'admin', '{\"purchase_reference\":\"PUR-20261123-NIZ4KE\",\"invoice_number\":\"DS-INV-20261123-WBW7WR\",\"member_id\":\"ST100000\",\"package_code\":\"DST-TOUR-001\",\"payment_method\":\"usdt\",\"payment_reference\":\"DWDDEWFFSEGEG\",\"amount\":\"50.0000\",\"denomination\":\"USDT\"}', 1, '2026-11-23 04:23:59', '2026-11-23 04:23:59'),
(4, 1, 'package_catalog_updated', 'package:DST-TOUR-001', 'admin', 'packages', '{\"package_code\":\"DST-TOUR-001\"}', 1, '2026-11-23 04:36:50', '2026-11-23 04:36:50'),
(5, 1, 'admin_login', 'admin', NULL, 'admin', '[]', 1, '2026-11-23 05:50:45', '2026-11-23 05:50:45'),
(6, 1, 'package_catalog_updated', 'package:DST-TOUR-001', 'admin', 'packages', '{\"package_code\":\"DST-TOUR-001\"}', 1, '2026-11-23 05:52:10', '2026-11-23 05:52:10'),
(7, 1, 'package_catalog_updated', 'package:DST-TOUR-001', 'admin', 'packages', '{\"package_code\":\"DST-TOUR-001\"}', 1, '2026-11-23 05:54:41', '2026-11-23 05:54:41'),
(8, 1, 'admin_logout', 'admin', NULL, 'admin', '[]', 1, '2026-11-23 07:24:08', '2026-11-23 07:24:08'),
(9, 1, 'admin_login', 'admin', NULL, 'admin', '[]', 1, '2026-11-23 07:24:53', '2026-11-23 07:24:53'),
(10, 1, 'reward_campaign_publish', '1', NULL, 'admin', '{\"title\":\"Campaign title\",\"status\":\"active\"}', 1, '2026-11-23 07:27:13', '2026-11-23 07:27:13'),
(11, 1, 'admin_login', 'admin', NULL, 'admin', '[]', 1, '2026-11-23 23:28:26', '2026-11-23 23:28:26'),
(12, 2, 'admin_login', 'admin', NULL, 'admin', '[]', 1, '2026-11-24 00:01:49', '2026-11-24 00:01:49'),
(13, 1, 'admin_login', 'admin', NULL, 'admin', '[]', 1, '2026-11-24 03:50:37', '2026-11-24 03:50:37'),
(14, 1, 'admin_login', 'admin', NULL, 'admin', '[]', 1, '2026-11-24 04:04:20', '2026-11-24 04:04:20'),
(15, 1, 'admin_login', 'admin', NULL, 'admin', '{\"admin_role\":\"main\",\"login_id\":null}', 1, '2026-11-26 00:30:10', '2026-11-26 00:30:10'),
(16, 1, 'sub_admin_created', 'sub_admin:3', NULL, 'admin', '{\"login_id\":\"E2ESUB01\",\"name\":\"E2E Sub Admin\",\"permissions\":[\"dashboard\",\"members.view\",\"support.manage\",\"support.view\"],\"is_active\":true}', 1, '2026-11-26 00:37:59', '2026-11-26 00:37:59'),
(17, 1, 'sub_admin_deactivated', 'sub_admin:3', NULL, 'admin', '{\"login_id\":\"E2ESUB01\",\"name\":\"E2E Sub Admin\",\"is_active\":false}', 1, '2026-11-26 00:37:59', '2026-11-26 00:37:59'),
(18, 1, 'sub_admin_activated', 'sub_admin:3', NULL, 'admin', '{\"login_id\":\"E2ESUB01\",\"name\":\"E2E Sub Admin\",\"is_active\":true}', 1, '2026-11-26 00:38:24', '2026-11-26 00:38:24'),
(19, 1, 'admin_login', 'admin', NULL, 'admin', '{\"admin_role\":\"main\",\"login_id\":null}', 1, '2026-11-26 00:38:41', '2026-11-26 00:38:41'),
(20, 1, 'admin_logout', 'admin', NULL, 'admin', '[]', 1, '2026-11-26 00:39:34', '2026-11-26 00:39:34'),
(21, 3, 'admin_login', 'admin', NULL, 'admin', '{\"admin_role\":\"sub\",\"login_id\":\"E2ESUB01\"}', 1, '2026-11-26 00:39:57', '2026-11-26 00:39:57'),
(22, 1, 'sub_admin_created', 'sub_admin:4', NULL, 'admin', '{\"login_id\":\"ST888888\",\"name\":\"Sk Nadeem\",\"permissions\":[\"dashboard\",\"settings.branding\",\"settings.general\",\"settings.security\",\"support.manage\",\"support.view\",\"tour_requests.export\",\"tour_requests.manage\",\"tour_requests.view\"],\"is_active\":true}', 1, '2026-11-26 00:45:20', '2026-11-26 00:45:20'),
(23, 1, 'admin_logout', 'admin', NULL, 'admin', '[]', 1, '2026-11-26 00:46:57', '2026-11-26 00:46:57'),
(24, 4, 'admin_login', 'admin', NULL, 'admin', '{\"admin_role\":\"sub\",\"login_id\":\"ST888888\"}', 1, '2026-11-26 00:47:07', '2026-11-26 00:47:07'),
(25, 4, 'admin_logout', 'admin', NULL, 'admin', '[]', 1, '2026-11-26 00:47:13', '2026-11-26 00:47:13'),
(26, 1, 'admin_login', 'admin', NULL, 'admin', '{\"admin_role\":\"main\",\"login_id\":null}', 1, '2026-11-26 00:47:27', '2026-11-26 00:47:27'),
(27, 1, 'sub_admin_updated', 'sub_admin:4', NULL, 'admin', '{\"login_id\":\"ST888888\",\"name\":\"Sk Nadeem\",\"before\":{\"name\":\"Sk Nadeem\",\"login_id\":\"ST888888\",\"email\":\"sknadimsk8509@gmail.com\",\"is_active\":true,\"permissions\":[\"dashboard\",\"settings.branding\",\"settings.general\",\"settings.security\",\"support.manage\",\"support.view\",\"tour_requests.export\",\"tour_requests.manage\",\"tour_requests.view\"]},\"after\":{\"name\":\"Sk Nadeem\",\"login_id\":\"ST888888\",\"email\":\"sknadimsk8509@gmail.com\",\"is_active\":true,\"permissions\":[\"dashboard\",\"members.create\",\"members.edit\",\"members.impersonate\",\"members.payment_controls\",\"members.status\",\"members.view\",\"ranks.manage\",\"support.manage\",\"support.view\"]}}', 1, '2026-11-26 00:47:54', '2026-11-26 00:47:54'),
(28, 1, 'admin_logout', 'admin', NULL, 'admin', '[]', 1, '2026-11-26 00:48:17', '2026-11-26 00:48:17'),
(29, 4, 'admin_login', 'admin', NULL, 'admin', '{\"admin_role\":\"sub\",\"login_id\":\"ST888888\"}', 1, '2026-11-26 00:48:28', '2026-11-26 00:48:28'),
(30, 4, 'admin_logout', 'admin', NULL, 'admin', '[]', 1, '2026-11-26 01:06:46', '2026-11-26 01:06:46'),
(31, 1, 'admin_login', 'admin', NULL, 'admin', '{\"admin_role\":\"main\",\"login_id\":null}', 1, '2026-11-26 01:07:02', '2026-11-26 01:07:02'),
(32, 1, 'sub_admin_deactivated', 'sub_admin:4', NULL, 'admin', '{\"login_id\":\"ST888888\",\"name\":\"Sk Nadeem\",\"is_active\":false}', 1, '2026-11-26 01:07:17', '2026-11-26 01:07:17'),
(33, 1, 'sub_admin_created', 'sub_admin:5', NULL, 'admin', '{\"login_id\":\"ST999999\",\"name\":\"Sk Nadeem\",\"permissions\":[\"dashboard\",\"reports\"],\"is_active\":true}', 1, '2026-11-26 01:08:48', '2026-11-26 01:08:48'),
(34, 1, 'admin_logout', 'admin', NULL, 'admin', '[]', 1, '2026-11-26 01:09:03', '2026-11-26 01:09:03'),
(35, 5, 'admin_login', 'admin', NULL, 'admin', '{\"admin_role\":\"sub\",\"login_id\":\"ST999999\"}', 1, '2026-11-26 01:09:09', '2026-11-26 01:09:09'),
(36, 5, 'report_export', 'members', 'pdf', 'full', '{\"Sort\":\"newest\"}', 2, '2026-11-26 01:09:16', '2026-11-26 01:09:16'),
(37, 5, 'admin_logout', 'admin', NULL, 'admin', '[]', 1, '2026-11-26 01:09:23', '2026-11-26 01:09:23'),
(38, 1, 'admin_login', 'admin', NULL, 'admin', '{\"admin_role\":\"main\",\"login_id\":null}', 1, '2026-11-26 01:44:21', '2026-11-26 01:44:21');

-- --------------------------------------------------------

--
-- Table structure for table `admin_user_permissions`
--

CREATE TABLE `admin_user_permissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `permission_key` varchar(96) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_user_permissions`
--

INSERT INTO `admin_user_permissions` (`id`, `user_id`, `permission_key`, `created_at`, `updated_at`) VALUES
(5, 3, 'dashboard', '2026-11-26 00:38:24', '2026-11-26 00:38:24'),
(6, 3, 'members.view', '2026-11-26 00:38:24', '2026-11-26 00:38:24'),
(7, 3, 'support.view', '2026-11-26 00:38:24', '2026-11-26 00:38:24'),
(8, 3, 'support.manage', '2026-11-26 00:38:24', '2026-11-26 00:38:24'),
(18, 4, 'dashboard', '2026-11-26 00:47:54', '2026-11-26 00:47:54'),
(19, 4, 'members.view', '2026-11-26 00:47:54', '2026-11-26 00:47:54'),
(20, 4, 'members.create', '2026-11-26 00:47:54', '2026-11-26 00:47:54'),
(21, 4, 'members.edit', '2026-11-26 00:47:54', '2026-11-26 00:47:54'),
(22, 4, 'members.impersonate', '2026-11-26 00:47:54', '2026-11-26 00:47:54'),
(23, 4, 'members.payment_controls', '2026-11-26 00:47:54', '2026-11-26 00:47:54'),
(24, 4, 'members.status', '2026-11-26 00:47:54', '2026-11-26 00:47:54'),
(25, 4, 'ranks.manage', '2026-11-26 00:47:54', '2026-11-26 00:47:54'),
(26, 4, 'support.view', '2026-11-26 00:47:54', '2026-11-26 00:47:54'),
(27, 4, 'support.manage', '2026-11-26 00:47:54', '2026-11-26 00:47:54'),
(28, 5, 'dashboard', '2026-11-26 01:08:48', '2026-11-26 01:08:48'),
(29, 5, 'reports', '2026-11-26 01:08:48', '2026-11-26 01:08:48');

-- --------------------------------------------------------

--
-- Table structure for table `associate_commission_packages`
--

CREATE TABLE `associate_commission_packages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `associate_commission_version_id` bigint(20) UNSIGNED NOT NULL,
  `tier_code` varchar(32) NOT NULL,
  `label` varchar(255) NOT NULL,
  `package_price` decimal(16,4) NOT NULL,
  `mapped_package_code` varchar(32) DEFAULT NULL,
  `daily_total` decimal(16,4) NOT NULL,
  `duration_total` decimal(16,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `associate_commission_packages`
--

INSERT INTO `associate_commission_packages` (`id`, `associate_commission_version_id`, `tier_code`, `label`, `package_price`, `mapped_package_code`, `daily_total`, `duration_total`, `created_at`, `updated_at`) VALUES
(1, 1, 'silver', 'SILVER PACKAGE', 50.0000, 'DST-TOUR-001', 0.1200, 48.0000, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(2, 1, 'gold', 'GOLD PACKAGE', 100.0000, 'DST-ROI-002', 0.2400, 96.0000, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(3, 1, 'premium', 'PREMIUM PACKAGE', 500.0000, 'DST-TOUR-003', 1.2000, 480.0000, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(4, 1, 'vip', 'VIP PACKAGE', 1000.0000, 'DST-TOUR-004', 2.4000, 960.0000, '2026-11-23 04:26:58', '2026-11-23 04:26:58');

-- --------------------------------------------------------

--
-- Table structure for table `associate_commission_ranks`
--

CREATE TABLE `associate_commission_ranks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `associate_commission_package_id` bigint(20) UNSIGNED NOT NULL,
  `rank` tinyint(3) UNSIGNED NOT NULL,
  `daily_amount` decimal(16,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `associate_commission_ranks`
--

INSERT INTO `associate_commission_ranks` (`id`, `associate_commission_package_id`, `rank`, `daily_amount`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(2, 1, 2, 0.0150, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(3, 1, 3, 0.0090, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(4, 1, 4, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(5, 1, 5, 0.0030, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(6, 1, 6, 0.0030, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(7, 1, 7, 0.0030, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(8, 1, 8, 0.0030, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(9, 1, 9, 0.0030, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(10, 1, 10, 0.0030, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(11, 1, 11, 0.0030, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(12, 1, 12, 0.0030, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(13, 1, 13, 0.0030, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(14, 1, 14, 0.0030, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(15, 1, 15, 0.0030, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(16, 1, 16, 0.0030, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(17, 1, 17, 0.0030, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(18, 1, 18, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(19, 1, 19, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(20, 1, 20, 0.0090, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(21, 2, 1, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(22, 2, 2, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(23, 2, 3, 0.0180, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(24, 2, 4, 0.0120, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(25, 2, 5, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(26, 2, 6, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(27, 2, 7, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(28, 2, 8, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(29, 2, 9, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(30, 2, 10, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(31, 2, 11, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(32, 2, 12, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(33, 2, 13, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(34, 2, 14, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(35, 2, 15, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(36, 2, 16, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(37, 2, 17, 0.0060, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(38, 2, 18, 0.0120, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(39, 2, 19, 0.0120, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(40, 2, 20, 0.0180, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(41, 3, 1, 0.3000, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(42, 3, 2, 0.1500, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(43, 3, 3, 0.0900, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(44, 3, 4, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(45, 3, 5, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(46, 3, 6, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(47, 3, 7, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(48, 3, 8, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(49, 3, 9, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(50, 3, 10, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(51, 3, 11, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(52, 3, 12, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(53, 3, 13, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(54, 3, 14, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(55, 3, 15, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(56, 3, 16, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(57, 3, 17, 0.0300, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(58, 3, 18, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(59, 3, 19, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(60, 3, 20, 0.0900, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(61, 4, 1, 0.6000, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(62, 4, 2, 0.3000, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(63, 4, 3, 0.1800, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(64, 4, 4, 0.1200, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(65, 4, 5, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(66, 4, 6, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(67, 4, 7, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(68, 4, 8, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(69, 4, 9, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(70, 4, 10, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(71, 4, 11, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(72, 4, 12, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(73, 4, 13, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(74, 4, 14, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(75, 4, 15, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(76, 4, 16, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(77, 4, 17, 0.0600, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(78, 4, 18, 0.1200, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(79, 4, 19, 0.1200, '2026-11-23 04:26:58', '2026-11-23 04:26:58'),
(80, 4, 20, 0.1800, '2026-11-23 04:26:58', '2026-11-23 04:26:58');

-- --------------------------------------------------------

--
-- Table structure for table `associate_commission_versions`
--

CREATE TABLE `associate_commission_versions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `version_code` varchar(64) NOT NULL,
  `label` varchar(255) NOT NULL,
  `commission_days` smallint(5) UNSIGNED NOT NULL DEFAULT 400,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `snapshot_on_approval` tinyint(1) NOT NULL DEFAULT 0,
  `effective_from` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `associate_commission_versions`
--

INSERT INTO `associate_commission_versions` (`id`, `version_code`, `label`, `commission_days`, `is_active`, `snapshot_on_approval`, `effective_from`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'associate_v1', 'Associate Commission Structure v1', 400, 0, 0, NULL, 'Official 20-rank Associate Commission chart for Silver/Gold/Premium/VIP. Inactive until written activation. Does not define ROI.', '2026-11-23 04:26:58', '2026-11-23 04:26:58');

-- --------------------------------------------------------

--
-- Table structure for table `cache`
--

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cache`
--

INSERT INTO `cache` (`key`, `value`, `expiration`) VALUES
('delta-support-cache-356a192b7913b04c54574d18c28d46e6395428ab', 'i:2;', 1795675164),
('delta-support-cache-356a192b7913b04c54574d18c28d46e6395428ab:timer', 'i:1795675164;', 1795675164),
('delta-support-cache-424f74a6a7ed4d4ed4761507ebcd209a6ef0937b', 'i:1;', 1795512919),
('delta-support-cache-424f74a6a7ed4d4ed4761507ebcd209a6ef0937b:timer', 'i:1795512919;', 1795512919),
('delta-support-cache-5c785c036466adea360111aa28563bfd556b5fba', 'i:1;', 1795677320),
('delta-support-cache-5c785c036466adea360111aa28563bfd556b5fba:timer', 'i:1795677320;', 1795677320),
('delta-support-cache-ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4', 'i:1;', 1795675215),
('delta-support-cache-ac3478d69a3c81fa62e60f5c3696165a4e5e6ac4:timer', 'i:1795675215;', 1795675215);

-- --------------------------------------------------------

--
-- Table structure for table `cache_locks`
--

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `commission_plan_levels`
--

CREATE TABLE `commission_plan_levels` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `plan_code` varchar(255) NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL,
  `percentage` decimal(8,4) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `primary_color` varchar(7) DEFAULT NULL,
  `primary_hover_color` varchar(7) DEFAULT NULL,
  `navy_color` varchar(7) DEFAULT NULL,
  `canvas_color` varchar(7) DEFAULT NULL,
  `border_color` varchar(7) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `company_settings`
--

INSERT INTO `company_settings` (`id`, `company_name`, `address`, `email`, `phone`, `logo_path`, `primary_color`, `primary_hover_color`, `navy_color`, `canvas_color`, `border_color`, `created_at`, `updated_at`) VALUES
(1, 'Delta Support', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-11-23 04:23:59', '2026-11-23 04:23:59');

-- --------------------------------------------------------

--
-- Table structure for table `failed_jobs`
--

CREATE TABLE `failed_jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `financial_controls`
--

CREATE TABLE `financial_controls` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `roi_calculation_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `roi_wallet_crediting_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `level_calculation_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `level_wallet_crediting_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `rewards_evaluation_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `withdrawal_requests_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `withdrawal_approval_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `external_payouts_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `daily_financial_cycle_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `financial_automation_armed` tinyint(1) NOT NULL DEFAULT 0,
  `financial_emergency_stop` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `financial_controls`
--

INSERT INTO `financial_controls` (`id`, `roi_calculation_enabled`, `roi_wallet_crediting_enabled`, `level_calculation_enabled`, `level_wallet_crediting_enabled`, `rewards_evaluation_enabled`, `withdrawal_requests_enabled`, `withdrawal_approval_enabled`, `external_payouts_enabled`, `daily_financial_cycle_enabled`, `created_at`, `updated_at`, `financial_automation_armed`, `financial_emergency_stop`) VALUES
(1, 0, 0, 0, 0, 0, 0, 0, 0, 0, '2026-11-23 04:33:06', '2026-11-23 04:33:06', 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `financial_historical_recovery_runs`
--

CREATE TABLE `financial_historical_recovery_runs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `database_name` varchar(255) NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date NOT NULL,
  `package_codes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`package_codes`)),
  `mode` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL,
  `days_processed` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `roi_records` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `commission_records` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `roi_amount` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `commission_amount` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `day_results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`day_results`)),
  `message` text DEFAULT NULL,
  `actor` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `financial_tour_recovery_runs`
--

CREATE TABLE `financial_tour_recovery_runs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `database_name` varchar(255) NOT NULL,
  `business_date` date DEFAULT NULL,
  `requested_dates` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`requested_dates`)),
  `package_code` varchar(32) NOT NULL DEFAULT 'DST-TOUR-003',
  `mode` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL,
  `dates_processed` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `roi_records` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `commission_records` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `roi_amount` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `commission_amount` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `commission_pool_amount` decimal(16,4) NOT NULL DEFAULT 0.0000,
  `day_results` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`day_results`)),
  `message` text DEFAULT NULL,
  `actor` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `impersonation_logs`
--

CREATE TABLE `impersonation_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `admin_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(32) NOT NULL,
  `action` varchar(16) NOT NULL,
  `started_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ended_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(1000) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `impersonation_logs`
--

INSERT INTO `impersonation_logs` (`id`, `admin_id`, `member_id`, `action`, `started_at`, `ended_at`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 1, 'ST100000', 'ended', '2026-11-23 10:06:12', '2026-11-23 04:36:12', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-23 04:24:12', '2026-11-23 04:36:12'),
(2, 1, 'ST100000', 'ended', '2026-11-23 12:54:05', '2026-11-23 07:24:05', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-23 04:38:06', '2026-11-23 07:24:05'),
(3, 1, 'ST100000', 'ended', '2026-11-23 12:54:05', '2026-11-23 07:24:05', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-23 07:23:54', '2026-11-23 07:24:05'),
(4, 1, 'ST100000', 'ended', '2026-11-24 06:24:15', '2026-11-24 00:54:15', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-24 00:52:27', '2026-11-24 00:54:15'),
(5, 2, 'STVIS001', 'started', '2026-11-24 01:04:30', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/3.31.15 Chrome/150.0.7871.250 Electron/43.8.9 Safari/537.36', '2026-11-24 01:04:30', '2026-11-24 01:04:30');

-- --------------------------------------------------------

--
-- Table structure for table `investments`
--

CREATE TABLE `investments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `investment_id` varchar(255) NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `amount` decimal(20,4) NOT NULL,
  `closing_amount` decimal(20,4) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `closed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `investment_withdrawals`
--

CREATE TABLE `investment_withdrawals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `withdrawal_id` varchar(255) NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `investment_id` varchar(255) NOT NULL,
  `investment_amount` decimal(20,4) NOT NULL,
  `withdrawal_amount` decimal(20,4) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `withdrawn_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_number` varchar(255) NOT NULL,
  `package_purchase_id` bigint(20) UNSIGNED NOT NULL,
  `purchase_reference` varchar(255) DEFAULT NULL,
  `member_id` varchar(255) NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `package_name` varchar(255) NOT NULL,
  `package_code` varchar(255) DEFAULT NULL,
  `package_details` text DEFAULT NULL,
  `price_usd` decimal(16,4) NOT NULL,
  `currency_label` varchar(255) DEFAULT NULL,
  `network` varchar(255) DEFAULT NULL,
  `payment_reference` varchar(255) NOT NULL,
  `payment_method` varchar(32) DEFAULT NULL,
  `company_name` varchar(255) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `company_email` varchar(255) DEFAULT NULL,
  `company_phone` varchar(255) DEFAULT NULL,
  `purchased_at` datetime NOT NULL,
  `approved_at` datetime NOT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'approved',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `denomination` varchar(8) NOT NULL DEFAULT 'USD'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `invoices`
--

INSERT INTO `invoices` (`id`, `invoice_number`, `package_purchase_id`, `purchase_reference`, `member_id`, `member_name`, `package_name`, `package_code`, `package_details`, `price_usd`, `currency_label`, `network`, `payment_reference`, `payment_method`, `company_name`, `company_address`, `company_email`, `company_phone`, `purchased_at`, `approved_at`, `status`, `created_at`, `updated_at`, `denomination`) VALUES
(1, 'DS-INV-20261123-WBW7WR', 1, 'PUR-20261123-NIZ4KE', 'ST100000', 'Rahul Das', 'SILVER PACKAGE', 'DST-TOUR-001', 'dummy benefits\nDestinations: Tour destinations ---declare\nDuration: 2 Days / 1 Nights\nBenefits', 50.0000, 'USDT', 'USDT', 'DWDDEWFFSEGEG', 'usdt', 'Delta Support', NULL, NULL, NULL, '2026-11-23 09:53:59', '2026-11-23 09:53:59', 'approved', '2026-11-23 04:23:59', '2026-11-23 04:23:59', 'USDT');

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
--

CREATE TABLE `jobs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL,
  `reserved_at` int(10) UNSIGNED DEFAULT NULL,
  `available_at` int(10) UNSIGNED NOT NULL,
  `created_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `job_batches`
--

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

-- --------------------------------------------------------

--
-- Table structure for table `level_commissions`
--

CREATE TABLE `level_commissions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL,
  `percentage` decimal(8,4) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `level_commission_report_global_daily_summaries`
--

CREATE TABLE `level_commission_report_global_daily_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `generation_id` bigint(20) UNSIGNED NOT NULL,
  `date_key` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `transaction_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `total_income_amount` decimal(38,4) NOT NULL DEFAULT 0.0000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `level_commission_report_level_daily_summaries`
--

CREATE TABLE `level_commission_report_level_daily_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `generation_id` bigint(20) UNSIGNED NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL,
  `date_key` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `transaction_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `total_income_amount` decimal(38,4) NOT NULL DEFAULT 0.0000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `level_commission_report_member_level_daily_summaries`
--

CREATE TABLE `level_commission_report_member_level_daily_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `generation_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL,
  `date_key` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `transaction_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `total_income_amount` decimal(38,4) NOT NULL DEFAULT 0.0000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `level_commission_transactions`
--

CREATE TABLE `level_commission_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(255) NOT NULL,
  `investment_id` varchar(255) NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `from_member_id` varchar(255) NOT NULL,
  `from_member_name` varchar(255) NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL,
  `business_date` date DEFAULT NULL,
  `on_amount` decimal(20,4) NOT NULL,
  `rate_percentage` decimal(6,3) NOT NULL,
  `income_amount` decimal(20,4) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `level_income_commission_rates`
--

CREATE TABLE `level_income_commission_rates` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `level_income_rule_version_id` bigint(20) UNSIGNED NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL,
  `percentage` decimal(10,4) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `level_income_commission_rates`
--

INSERT INTO `level_income_commission_rates` (`id`, `level_income_rule_version_id`, `level`, `percentage`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 25.0000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(2, 1, 2, 12.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(3, 1, 3, 7.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(4, 1, 4, 5.0000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(5, 1, 5, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(6, 1, 6, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(7, 1, 7, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(8, 1, 8, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(9, 1, 9, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(10, 1, 10, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(11, 1, 11, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(12, 1, 12, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(13, 1, 13, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(14, 1, 14, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(15, 1, 15, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(16, 1, 16, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(17, 1, 17, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(18, 1, 18, 5.0000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(19, 1, 19, 5.0000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(20, 1, 20, 7.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(21, 2, 1, 25.0000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(22, 2, 2, 12.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(23, 2, 3, 7.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(24, 2, 4, 5.0000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(25, 2, 5, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(26, 2, 6, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(27, 2, 7, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(28, 2, 8, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(29, 2, 9, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(30, 2, 10, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(31, 2, 11, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(32, 2, 12, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(33, 2, 13, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(34, 2, 14, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(35, 2, 15, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(36, 2, 16, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(37, 2, 17, 2.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(38, 2, 18, 5.0000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(39, 2, 19, 5.0000, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(40, 2, 20, 7.5000, '2026-11-24 02:22:36', '2026-11-24 02:22:36');

-- --------------------------------------------------------

--
-- Table structure for table `level_income_rule_audits`
--

CREATE TABLE `level_income_rule_audits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `level_income_rule_version_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `before_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_json`)),
  `after_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_json`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `level_income_rule_audits`
--

INSERT INTO `level_income_rule_audits` (`id`, `level_income_rule_version_id`, `action`, `actor_id`, `before_json`, `after_json`, `ip_address`, `created_at`, `updated_at`) VALUES
(1, 1, 'seeded_legacy_baseline', NULL, NULL, '{\"version_code\":\"legacy_live_v1\",\"posting_authorized\":false}', NULL, '2026-11-24 02:22:36', '2026-11-24 02:22:36');

-- --------------------------------------------------------

--
-- Table structure for table `level_income_rule_versions`
--

CREATE TABLE `level_income_rule_versions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `version_code` varchar(64) NOT NULL,
  `label` varchar(160) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'draft',
  `posting_authorized` tinyint(1) NOT NULL DEFAULT 0,
  `applicability` varchar(64) NOT NULL DEFAULT 'unspecified',
  `direct_qualification_basis` varchar(64) NOT NULL DEFAULT 'income_eligible_directs',
  `tour_only_qualifies_for_unlock` tinyint(1) NOT NULL DEFAULT 0,
  `combined_joining_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `combined_purchase_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `combined_purchase_scope` varchar(64) NOT NULL DEFAULT 'approved_package_directs',
  `combined_repeat_purchase_mode` varchar(32) NOT NULL DEFAULT 'unique_directs',
  `combined_double_credit_policy` varchar(32) NOT NULL DEFAULT 'prevent_overlap',
  `combined_joining_unit_value` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `combined_purchase_unit_value` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `grandfather_existing_unlock` tinyint(1) NOT NULL DEFAULT 0,
  `grandfather_transition_date` date DEFAULT NULL,
  `grandfather_captured_at` timestamp NULL DEFAULT NULL,
  `grandfather_captured_by` bigint(20) UNSIGNED DEFAULT NULL,
  `effective_from` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `change_note` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `published_by` bigint(20) UNSIGNED DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `level_income_rule_versions`
--

INSERT INTO `level_income_rule_versions` (`id`, `version_code`, `label`, `status`, `posting_authorized`, `applicability`, `direct_qualification_basis`, `tour_only_qualifies_for_unlock`, `combined_joining_enabled`, `combined_purchase_enabled`, `combined_purchase_scope`, `combined_repeat_purchase_mode`, `combined_double_credit_policy`, `combined_joining_unit_value`, `combined_purchase_unit_value`, `grandfather_existing_unlock`, `grandfather_transition_date`, `grandfather_captured_at`, `grandfather_captured_by`, `effective_from`, `notes`, `change_note`, `created_by`, `published_by`, `published_at`, `created_at`, `updated_at`) VALUES
(1, 'legacy_live_v1', 'Legacy live unlock & rates (baseline)', 'published', 0, 'unspecified', 'income_eligible_directs', 0, 0, 0, 'approved_package_directs', 'unique_directs', 'prevent_overlap', 1, 1, 0, NULL, NULL, NULL, NULL, 'Mirrors hard-coded ReferralLevelEligibility + CommissionPlan::RATE_BY_LEVEL. posting_authorized=false keeps financial:daily on code defaults until explicit authorization.', 'Seeded baseline from audit.', NULL, NULL, '2026-11-24 02:22:36', '2026-11-24 02:22:36', '2026-11-24 06:18:44'),
(2, 'proposed_activation_unlock_v1', 'Proposed: activation unlocks levels 1–4', 'draft', 0, 'future_purchases_only', 'approved_package_directs', 1, 1, 1, 'approved_package_directs', 'unique_directs', 'prevent_overlap', 1, 1, 1, NULL, NULL, NULL, NULL, 'PROPOSED: activation unlocks 1–4 at 0 directs. Direct qualification draft default = unique directs with any approved package (incl. Tour Only for unlock only). Not posting-authorized. ROI/commission generation remain separate from unlock qualification.', 'Combined joining+purchase settings staged (disabled until basis=combined_joining_and_purchases and BD approves formula). Grandfathering remains available. Not posting-authorized.', NULL, NULL, NULL, '2026-11-24 02:22:36', '2026-11-24 07:14:56');

-- --------------------------------------------------------

--
-- Table structure for table `level_income_unlock_bands`
--

CREATE TABLE `level_income_unlock_bands` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `level_income_rule_version_id` bigint(20) UNSIGNED NOT NULL,
  `sort_order` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `start_level` tinyint(3) UNSIGNED NOT NULL,
  `end_level` tinyint(3) UNSIGNED NOT NULL,
  `min_qualifying_directs` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `requires_personal_activation` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `level_income_unlock_bands`
--

INSERT INTO `level_income_unlock_bands` (`id`, `level_income_rule_version_id`, `sort_order`, `start_level`, `end_level`, `min_qualifying_directs`, `requires_personal_activation`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 4, 1, 1, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(2, 1, 2, 5, 8, 2, 1, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(3, 1, 3, 9, 12, 3, 1, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(4, 1, 4, 13, 16, 4, 1, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(5, 1, 5, 17, 20, 5, 1, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(6, 2, 1, 1, 4, 0, 1, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(7, 2, 2, 5, 8, 1, 1, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(8, 2, 3, 9, 12, 2, 1, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(9, 2, 4, 13, 16, 3, 1, '2026-11-24 02:22:36', '2026-11-24 02:22:36'),
(10, 2, 5, 17, 20, 4, 1, '2026-11-24 02:22:36', '2026-11-24 02:22:36');

-- --------------------------------------------------------

--
-- Table structure for table `level_income_unlock_grandfather_snapshots`
--

CREATE TABLE `level_income_unlock_grandfather_snapshots` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `level_income_rule_version_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(32) NOT NULL,
  `legacy_unlocked_levels` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `income_eligible_direct_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `registered_direct_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `personally_activated_legacy` tinyint(1) NOT NULL DEFAULT 0,
  `transition_date` date NOT NULL,
  `captured_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `captured_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `members`
--

CREATE TABLE `members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `rank_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sponsor_id` varchar(255) NOT NULL,
  `sponsor_name` varchar(255) NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `wallet_address` varchar(255) DEFAULT NULL,
  `activation_wallet_amount` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `working_wallet_amount` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `roi_wallet_amount` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `mobile_no` varchar(255) NOT NULL,
  `pan_card_no` varchar(255) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `normalized_email` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `security_pin_hash` varchar(255) DEFAULT NULL,
  `security_pin_set_at` timestamp NULL DEFAULT NULL,
  `security_pin_failed_attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `security_pin_locked_until` timestamp NULL DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `members`
--

INSERT INTO `members` (`id`, `member_id`, `rank_id`, `sponsor_id`, `sponsor_name`, `member_name`, `wallet_address`, `activation_wallet_amount`, `working_wallet_amount`, `roi_wallet_amount`, `mobile_no`, `pan_card_no`, `email`, `normalized_email`, `password`, `security_pin_hash`, `security_pin_set_at`, `security_pin_failed_attempts`, `security_pin_locked_until`, `status`, `created_at`, `updated_at`) VALUES
(1, 'ST100000', NULL, 'ST666666', 'Admin', 'Rahul Das', 'BOHIC', 0.0000, 0.0000, 0.0000, '6297616918', NULL, 'sknadimsk8509@gmail.com', 'sknadimsk8509@gmail.com', '$2y$12$CYCS4V0/099gBab8xJYneOm8YjsFTSojaUUd43EHHLAEwyLrA61jK', NULL, NULL, 0, NULL, 'active', '2026-11-23 04:07:17', '2026-11-23 12:55:11'),
(2, 'STVIS001', NULL, 'ST666666', 'Admin', 'Phase1 Visual Member', NULL, 0.0000, 0.0000, 0.0000, '9000000001', NULL, 'phase1.visual.member@local-test.invalid', 'phase1.visual.member@local-test.invalid', '$2y$12$Yb2.zbcu6zYdH/a0/fYpbumbM.WhRIkGBXV7kn/yOs/JI0GnthOoy', NULL, NULL, 0, NULL, 'active', '2026-11-23 23:59:03', '2026-11-24 05:29:04');

-- --------------------------------------------------------

--
-- Table structure for table `member_email_account_locks`
--

CREATE TABLE `member_email_account_locks` (
  `normalized_email` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `member_email_account_locks`
--

INSERT INTO `member_email_account_locks` (`normalized_email`, `created_at`, `updated_at`) VALUES
('phase1.visual.member@local-test.invalid', '2026-11-23 23:59:03', '2026-11-23 23:59:03'),
('sknadimsk8509@gmail.com', '2026-11-23 04:07:17', '2026-11-23 04:07:17');

-- --------------------------------------------------------

--
-- Table structure for table `member_password_otps`
--

CREATE TABLE `member_password_otps` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `member_password_setups`
--

CREATE TABLE `member_password_setups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `member_password_setups`
--

INSERT INTO `member_password_setups` (`id`, `member_id`, `token_hash`, `expires_at`, `used_at`, `created_at`, `updated_at`) VALUES
(1, 'ST100000', 'e3a870dcccf64da1c53277ec8cef4b95977bb031243ba0981242caa8e3ecfe15', '2026-11-23 05:07:17', NULL, '2026-11-23 04:07:17', '2026-11-23 04:07:17');

-- --------------------------------------------------------

--
-- Table structure for table `member_payment_blocks`
--

CREATE TABLE `member_payment_blocks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(32) NOT NULL,
  `income_type` varchar(16) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `opened_by_event_id` bigint(20) UNSIGNED DEFAULT NULL,
  `closed_by_event_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `member_payment_controls`
--

CREATE TABLE `member_payment_controls` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(32) NOT NULL,
  `master_enabled` tinyint(1) NOT NULL DEFAULT 1,
  `roi_preference` tinyint(1) NOT NULL DEFAULT 1,
  `level_preference` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(32) NOT NULL DEFAULT 'active',
  `last_reason` text DEFAULT NULL,
  `last_effective_from` date DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `member_payment_control_events`
--

CREATE TABLE `member_payment_control_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(32) NOT NULL,
  `action` varchar(64) NOT NULL,
  `reason` text NOT NULL,
  `effective_from` date NOT NULL,
  `before_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_json`)),
  `after_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_json`)),
  `actor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `member_preview_sessions`
--

CREATE TABLE `member_preview_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uuid` char(36) NOT NULL,
  `admin_user_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(32) NOT NULL,
  `token_hash` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `revoked_at` timestamp NULL DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `member_preview_sessions`
--

INSERT INTO `member_preview_sessions` (`id`, `uuid`, `admin_user_id`, `member_id`, `token_hash`, `expires_at`, `revoked_at`, `last_used_at`, `ip_address`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, '2a169e08-3aca-4648-943e-bc8208d29389', 1, 'ST100000', '87eb01bdacff0ab500ae9c05ed3f9bddd87ed1f2f9fbb7ef07bfea01ca7cac2b', '2026-11-23 12:54:05', '2026-11-23 07:24:05', '2026-11-23 07:24:05', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-23 07:23:54', '2026-11-23 07:24:05'),
(2, 'fbc923dd-f2cf-4593-8e8a-a29bf37cec6b', 1, 'ST100000', '829ec46fa0472c24d8eb10913addf57987a3c7b89cac872cbf9a98f76f7a07c1', '2026-11-24 06:24:15', '2026-11-24 00:54:15', '2026-11-24 00:54:15', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36', '2026-11-24 00:52:27', '2026-11-24 00:54:15'),
(3, 'aa51a655-96f6-470b-9638-1f5c697c76a6', 2, 'STVIS001', '80ea328c629c7756603f85b5ebca49439f899bee42a33025cf23a69a62e7c0d5', '2026-11-24 07:17:21', NULL, '2026-11-24 01:47:21', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/3.31.15 Chrome/150.0.7871.250 Electron/43.8.9 Safari/537.36', '2026-11-24 01:04:30', '2026-11-24 01:47:21');

-- --------------------------------------------------------

--
-- Table structure for table `member_security_pin_resets`
--

CREATE TABLE `member_security_pin_resets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `verified_at` timestamp NULL DEFAULT NULL,
  `consumed_at` timestamp NULL DEFAULT NULL,
  `invalidated_at` timestamp NULL DEFAULT NULL,
  `attempts` tinyint(3) UNSIGNED NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `member_transfers`
--

CREATE TABLE `member_transfers` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `transfer_reference` varchar(255) NOT NULL,
  `sender_member_id` varchar(255) NOT NULL,
  `receiver_member_id` varchar(255) NOT NULL,
  `source_wallet` varchar(20) NOT NULL,
  `destination_wallet` varchar(20) NOT NULL,
  `amount` decimal(20,4) NOT NULL,
  `fee_amount` decimal(20,4) NOT NULL,
  `currency` varchar(8) NOT NULL DEFAULT 'USDT',
  `status` varchar(20) NOT NULL,
  `idempotency_key` char(36) NOT NULL,
  `business_date` date NOT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `reversal_actor` varchar(255) DEFAULT NULL,
  `reversal_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `member_transfer_settings`
--

CREATE TABLE `member_transfer_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_transfers_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `minimum_amount` decimal(20,4) NOT NULL DEFAULT 1.0000,
  `maximum_amount` decimal(20,4) NOT NULL DEFAULT 1000.0000,
  `daily_limit` decimal(20,4) NOT NULL DEFAULT 5000.0000,
  `fee_amount` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `fee_type` varchar(20) DEFAULT NULL,
  `fee_value` decimal(20,4) DEFAULT NULL,
  `denomination` varchar(8) NOT NULL DEFAULT 'USDT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` int(10) UNSIGNED NOT NULL,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_09_14_000000_create_members_table', 1),
(5, '2026_09_14_000001_add_pan_card_no_to_members_table', 1),
(6, '2026_09_15_000000_create_investments_table', 1),
(7, '2026_09_15_000001_add_activation_wallet_amount_to_members_table', 1),
(8, '2026_09_15_000002_create_activation_wallet_transactions_table', 1),
(9, '2026_09_16_000001_add_working_wallet_amount_to_members_table', 1),
(10, '2026_09_16_000002_add_type_and_remarks_to_activation_wallet_transactions_table', 1),
(11, '2026_09_16_000003_create_level_commission_transactions_table', 1),
(12, '2026_09_16_000004_create_roi_transactions_table', 1),
(13, '2026_09_16_000005_add_category_and_roi_indexes_to_investments_table', 1),
(14, '2026_09_16_000006_add_roi_wallet_amount_to_members_table', 1),
(15, '2026_09_16_000007_add_roi_lifecycle_fields_to_roi_transactions_table', 1),
(16, '2026_09_16_000008_remove_category_from_investments_table', 1),
(17, '2026_09_16_000009_create_level_commissions_table', 1),
(18, '2026_09_17_000001_add_closed_at_to_investments_table', 1),
(19, '2026_09_17_000002_create_investment_withdrawals_table', 1),
(20, '2026_09_17_000003_add_business_date_to_level_commission_transactions_table', 1),
(21, '2026_09_17_000004_add_is_admin_to_users_table', 1),
(22, '2026_09_19_000001_create_member_password_otps_table', 1),
(23, '2026_09_22_000001_add_closing_amount_to_investments_table', 1),
(24, '2026_09_22_000002_create_ranks_table', 1),
(25, '2026_09_22_000003_add_sponsor_id_index_to_members_table', 1),
(26, '2026_09_22_000004_add_status_to_ranks_table', 1),
(27, '2026_09_22_000005_add_rank_id_to_members_table', 1),
(28, '2026_09_22_000006_create_rank_achievements_table', 1),
(29, '2026_09_25_000001_create_stress_test_datasets_table', 1),
(30, '2026_09_25_000002_add_rank_achievement_report_indexes', 1),
(31, '2026_09_28_000001_add_level_income_report_indexes', 1),
(32, '2026_09_28_000002_add_admin_level_income_index_composites', 1),
(33, '2026_09_28_000003_add_roi_report_indexes', 1),
(34, '2026_09_29_000001_create_report_daily_summaries', 1),
(35, '2026_10_02_000001_create_simulation_checkpoints_table', 1),
(36, '2026_10_02_000002_convert_future_business_timestamps_to_datetime', 1),
(37, '2026_10_26_100001_create_delta_support_commerce_tables', 2),
(38, '2026_10_27_100001_add_package_content_fields', 3),
(39, '2026_10_27_110001_add_invoice_snapshot_columns', 4),
(40, '2026_10_27_120001_create_delta_support_income_configuration', 5),
(41, '2026_10_27_140001_add_denomination_to_commerce_amounts', 6),
(42, '2026_10_27_150001_refresh_usdt_payment_instructions', 7),
(43, '2026_10_27_160001_create_usdt_withdrawal_foundation', 7),
(44, '2026_10_27_170001_add_member_status_list_index', 8),
(45, '2026_10_28_100000_add_branding_to_company_settings', 9),
(46, '2026_10_28_110000_create_impersonation_logs_table', 10),
(47, '2026_10_28_130001_index_package_purchase_submitted_at', 11),
(48, '2026_10_28_140000_correct_coastal_weekend_income_eligibility', 12),
(49, '2026_10_28_150000_create_admin_activity_logs_table', 13),
(50, '2026_10_28_150001_add_catalog_report_indexes', 13),
(51, '2026_10_28_160000_create_package_income_accrual_tables', 14),
(52, '2026_10_28_170000_update_hill_station_package_description', 15),
(53, '2026_10_28_180000_create_package_income_runs_table', 16),
(54, '2026_10_28_190000_create_reward_tables', 17),
(55, '2026_10_28_200000_create_reward_evaluation_runs_table', 18),
(56, '2026_10_28_210000_add_must_change_password_to_users_table', 19),
(57, '2026_10_29_120000_create_financial_controls_table', 20),
(58, '2026_10_29_120001_add_balances_to_wallet_ledger_entries', 20),
(59, '2026_10_29_160000_add_security_pin_to_members_table', 21),
(60, '2026_10_29_160001_create_member_security_pin_resets_table', 22),
(61, '2026_10_29_170000_create_member_transfer_settings_table', 23),
(62, '2026_10_29_170001_create_member_transfers_table', 23),
(63, '2026_10_29_180000_create_support_ticket_tables', 24),
(64, '2026_10_30_100000_add_financial_automation_controls', 25),
(65, '2026_10_30_110000_create_production_initializations_table', 25),
(66, '2026_10_31_101800_create_member_password_setups_table', 25),
(67, '2026_10_31_120000_add_paid_at_to_withdrawal_requests', 26),
(68, '2026_11_02_143000_add_payment_method_to_commerce_tables', 27),
(69, '2026_11_13_150000_create_package_income_activations_table', 28),
(70, '2026_11_16_140000_create_financial_historical_recovery_runs_table', 28),
(71, '2026_11_16_180000_create_financial_tour_recovery_runs_table', 28),
(72, '2026_11_21_120000_add_member_transfer_charge_fields', 28),
(73, '2026_11_21_130000_create_wallet_package_purchase_reservations', 28),
(74, '2026_11_21_140000_add_member_email_account_limit', 29),
(75, '2026_11_21_170000_create_associate_commission_structure_tables', 30),
(76, '2026_11_21_180000_map_associate_commission_package_codes', 31),
(77, '2026_11_21_190000_correct_associate_commission_production_codes', 32),
(78, '2026_11_23_130000_add_package_purchase_reservation_allocations', 33),
(79, '2026_11_23_140000_ensure_production_catalog_packages_active', 34),
(80, '2026_11_23_150000_ensure_six_production_tour_packages_active', 35),
(81, '2026_11_23_160000_create_package_plan_versioning_tables', 35),
(82, '2026_11_23_170000_create_member_preview_sessions_table', 36),
(83, '2026_11_23_180000_add_package_auto_roi_enabled', 37),
(84, '2026_11_23_190000_apply_safe_package_auto_roi_defaults', 38),
(85, '2026_11_23_200000_add_package_validity_duration_fields', 39),
(86, '2026_11_23_210000_add_reward_campaign_media_fields', 40),
(87, '2026_11_24_130000_create_level_income_rule_tables', 41),
(88, '2026_11_24_143000_create_member_payment_control_tables', 42),
(89, '2026_11_24_150000_create_tour_interest_request_tables', 43),
(90, '2026_11_24_160000_set_level_income_proposed_default_applicability', 44),
(91, '2026_11_24_170000_add_level_income_qualification_settings', 45),
(92, '2026_11_24_180000_add_level_income_unlock_grandfathering', 46),
(93, '2026_11_24_190000_add_combined_level_unlock_qualification', 47),
(94, '2026_11_26_110000_add_sub_admin_management', 48),
(95, '2026_11_26_130000_add_soft_deletes_to_users_table', 49),
(96, '2026_11_26_140000_normalize_combined_joining_and_purchase_basis', 49);

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `code` varchar(255) NOT NULL,
  `package_type` varchar(255) NOT NULL DEFAULT 'tour',
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `full_description` text DEFAULT NULL,
  `price_usd` decimal(16,4) NOT NULL,
  `tour_title` varchar(255) DEFAULT NULL,
  `tour_details` text DEFAULT NULL,
  `destinations` text DEFAULT NULL,
  `duration` varchar(255) DEFAULT NULL,
  `benefits` text DEFAULT NULL,
  `inclusions` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `roi_eligible` tinyint(1) NOT NULL DEFAULT 0,
  `auto_roi_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `level_commission_eligible` tinyint(1) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `purchase_available` tinyint(1) NOT NULL DEFAULT 1,
  `income_source` varchar(32) NOT NULL DEFAULT 'legacy_config',
  `active_plan_version_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `validity_unlimited` tinyint(1) NOT NULL DEFAULT 0,
  `validity_duration` int(10) UNSIGNED DEFAULT NULL,
  `validity_unit` varchar(16) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `denomination` varchar(8) NOT NULL DEFAULT 'USD'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `code`, `package_type`, `name`, `description`, `full_description`, `price_usd`, `tour_title`, `tour_details`, `destinations`, `duration`, `benefits`, `inclusions`, `exclusions`, `image_path`, `roi_eligible`, `auto_roi_enabled`, `level_commission_eligible`, `is_active`, `purchase_available`, `income_source`, `active_plan_version_id`, `sort_order`, `validity_unlimited`, `validity_duration`, `validity_unit`, `created_at`, `updated_at`, `denomination`) VALUES
(1, 'DST-TOUR-001', 'tour_roi', 'SILVER PACKAGE', 'Short description', 'Full description', 50.0000, 'dummy benefits', 'Tour details (catalog)', 'Tour destinations ---declare', '2 Days / 1 Nights', 'Benefits', 'Inclusions', 'Exclusions', 'packages/9ixLWSX4OrEsWZu4c6Eef6CtqMaEwOrOIcZIYkLF.png', 1, 0, 1, 1, 1, 'database', 7, 1, 0, NULL, NULL, '2026-11-23 04:21:14', '2026-11-23 06:03:46', 'USDT');

-- --------------------------------------------------------

--
-- Table structure for table `package_income_activations`
--

CREATE TABLE `package_income_activations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `package_code` varchar(255) NOT NULL,
  `activation_business_date` date NOT NULL,
  `activated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `activated_by` varchar(255) DEFAULT NULL,
  `confirm_token` varchar(64) DEFAULT NULL,
  `terms_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`terms_snapshot`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `package_income_runs`
--

CREATE TABLE `package_income_runs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL,
  `credited_roi` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `credited_commissions` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `message` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `package_income_terms`
--

CREATE TABLE `package_income_terms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `package_purchase_id` bigint(20) UNSIGNED NOT NULL,
  `package_plan_version_id` bigint(20) UNSIGNED DEFAULT NULL,
  `income_definition_source` varchar(40) DEFAULT NULL,
  `reference` varchar(255) NOT NULL,
  `income_eligible` tinyint(1) DEFAULT NULL,
  `monthly_rate_percentage` decimal(8,4) DEFAULT NULL,
  `monthly_amount` decimal(16,4) DEFAULT NULL,
  `duration_months` smallint(5) UNSIGNED DEFAULT NULL,
  `commission_base` varchar(64) DEFAULT NULL,
  `first_accrual_rule` varchar(64) DEFAULT NULL,
  `business_timezone` varchar(64) DEFAULT NULL,
  `associate_commission_version` varchar(64) DEFAULT NULL,
  `associate_tier_code` varchar(32) DEFAULT NULL,
  `associate_commission_days` smallint(5) UNSIGNED DEFAULT NULL,
  `associate_daily_total` decimal(16,4) DEFAULT NULL,
  `associate_rank_amounts` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`associate_rank_amounts`)),
  `snapshotted_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `package_income_terms`
--

INSERT INTO `package_income_terms` (`id`, `package_purchase_id`, `package_plan_version_id`, `income_definition_source`, `reference`, `income_eligible`, `monthly_rate_percentage`, `monthly_amount`, `duration_months`, `commission_base`, `first_accrual_rule`, `business_timezone`, `associate_commission_version`, `associate_tier_code`, `associate_commission_days`, `associate_daily_total`, `associate_rank_amounts`, `snapshotted_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'purchase_plan_version', 'PIT-PUR-20261123-NIZ4KE', 1, NULL, 0.1110, 900, '0.2400', 'approval_plus_wait_days', 'Asia/Kolkata', NULL, NULL, NULL, NULL, NULL, '2026-11-23 09:53:59', '2026-11-23 04:23:59', '2026-11-23 04:23:59');

-- --------------------------------------------------------

--
-- Table structure for table `package_level_accruals`
--

CREATE TABLE `package_level_accruals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(255) NOT NULL,
  `idempotency_key` varchar(255) NOT NULL,
  `package_purchase_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `from_member_id` varchar(255) NOT NULL,
  `from_member_name` varchar(255) NOT NULL,
  `level` tinyint(3) UNSIGNED NOT NULL,
  `package_code` varchar(255) NOT NULL,
  `package_name` varchar(255) NOT NULL,
  `business_date` date NOT NULL,
  `pool_amount` decimal(16,4) NOT NULL,
  `rate_percentage` decimal(8,4) NOT NULL,
  `amount` decimal(16,4) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'accrued',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `package_plan_audits`
--

CREATE TABLE `package_plan_audits` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `package_id` bigint(20) UNSIGNED NOT NULL,
  `package_plan_version_id` bigint(20) UNSIGNED DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(64) NOT NULL,
  `before_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_json`)),
  `after_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_json`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `package_plan_audits`
--

INSERT INTO `package_plan_audits` (`id`, `package_id`, `package_plan_version_id`, `user_id`, `action`, `before_json`, `after_json`, `ip_address`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 'package_created', NULL, '{\"id\":1,\"code\":\"DST-TOUR-001\",\"name\":\"SILVER PACKAGE\",\"package_type\":\"tour_roi\",\"price_usd\":\"50.0000\",\"is_active\":true,\"purchase_available\":true,\"roi_eligible\":true,\"level_commission_eligible\":true,\"income_source\":\"database\",\"active_plan_version_id\":1,\"sort_order\":1}', '127.0.0.1', '2026-11-23 04:21:14', '2026-11-23 04:21:14'),
(2, 1, 1, 1, 'plan_published', NULL, '{\"package_id\":1,\"version_number\":1,\"status\":\"published\",\"created_by\":1,\"has_daily_roi\":true,\"roi_daily\":\"0.1110\",\"roi_days\":900,\"wait_days\":9,\"roi_start_rule\":\"approval_plus_wait_days\",\"roi_end_rule\":\"first_accrual_plus_duration_minus_one\",\"commission_pool\":\"0.2400\",\"commission_days\":400,\"level_commission_eligible\":true,\"tour_eligible_after_days\":30,\"tour_nights\":1,\"tour_days\":2,\"tour_adults\":2,\"tour_children\":1,\"food_coupon_amount\":\"1000.0000\",\"food_coupon_currency\":\"INR\",\"tour_description\":\"Tour description\",\"label\":null,\"change_note\":\"511188\",\"accrual_authority\":true,\"published_at\":\"2026-11-23T09:51:14.000000Z\",\"published_by\":1,\"updated_at\":\"2026-11-23T09:51:14.000000Z\",\"created_at\":\"2026-11-23T09:51:14.000000Z\",\"id\":1}', '127.0.0.1', '2026-11-23 04:21:14', '2026-11-23 04:21:14'),
(3, 1, 2, 1, 'plan_published_new_version', NULL, '{\"package_id\":1,\"version_number\":2,\"status\":\"published\",\"created_by\":1,\"has_daily_roi\":true,\"roi_daily\":\"0.1058\",\"roi_days\":900,\"wait_days\":10,\"roi_start_rule\":\"approval_plus_wait_days\",\"roi_end_rule\":\"first_accrual_plus_duration_minus_one\",\"commission_pool\":null,\"commission_days\":null,\"level_commission_eligible\":true,\"tour_eligible_after_days\":30,\"tour_nights\":1,\"tour_days\":2,\"tour_adults\":2,\"tour_children\":1,\"food_coupon_amount\":null,\"food_coupon_currency\":null,\"tour_description\":null,\"label\":\"original_tour_plan_v1_silver\",\"change_note\":\"Corrected approved Original Tour Plan SILVER 0.1058\\u00d7900. Prior 0.1110 preserved for existing purchase.\",\"accrual_authority\":true,\"published_at\":\"2026-11-23T09:57:25.000000Z\",\"published_by\":1,\"updated_at\":\"2026-11-23T09:57:25.000000Z\",\"created_at\":\"2026-11-23T09:57:25.000000Z\",\"id\":2}', NULL, '2026-11-23 04:27:25', '2026-11-23 04:27:25'),
(4, 1, NULL, 1, 'catalog_updated', '{\"id\":1,\"code\":\"DST-TOUR-001\",\"name\":\"SILVER PACKAGE\",\"package_type\":\"tour_roi\",\"price_usd\":\"50.0000\",\"is_active\":true,\"purchase_available\":true,\"roi_eligible\":true,\"level_commission_eligible\":true,\"income_source\":\"database\",\"active_plan_version_id\":2,\"sort_order\":1}', '{\"id\":1,\"code\":\"DST-TOUR-001\",\"name\":\"SILVER PACKAGE\",\"package_type\":\"tour_roi\",\"price_usd\":\"50.0000\",\"is_active\":true,\"purchase_available\":true,\"roi_eligible\":true,\"level_commission_eligible\":true,\"income_source\":\"database\",\"active_plan_version_id\":2,\"sort_order\":1}', '127.0.0.1', '2026-11-23 04:36:50', '2026-11-23 04:36:50'),
(5, 1, 3, 1, 'plan_published_new_version', NULL, '{\"package_id\":1,\"version_number\":3,\"status\":\"published\",\"created_by\":1,\"has_daily_roi\":true,\"roi_daily\":\"0.1110\",\"roi_days\":900,\"wait_days\":10,\"roi_start_rule\":\"approval_plus_wait_days\",\"roi_end_rule\":\"first_accrual_plus_duration_minus_one\",\"commission_pool\":null,\"commission_days\":null,\"level_commission_eligible\":true,\"tour_eligible_after_days\":30,\"tour_nights\":1,\"tour_days\":2,\"tour_adults\":2,\"tour_children\":1,\"food_coupon_amount\":null,\"food_coupon_currency\":\"INR\",\"tour_description\":\"Tour details (catalog)\",\"label\":\"original_tour_plan_v1_silver\",\"change_note\":\"Corrected approved Original Tour Plan SILVER 0.1058\\u00d7900. Prior 0.1110 preserved for existing purchase.\",\"accrual_authority\":false,\"published_at\":\"2026-11-23T10:06:50.000000Z\",\"published_by\":1,\"updated_at\":\"2026-11-23T10:06:50.000000Z\",\"created_at\":\"2026-11-23T10:06:50.000000Z\",\"id\":3}', '127.0.0.1', '2026-11-23 04:36:50', '2026-11-23 04:36:50'),
(6, 1, 4, 1, 'plan_published_new_version', NULL, '{\"package_id\":1,\"version_number\":4,\"status\":\"published\",\"created_by\":1,\"has_daily_roi\":true,\"roi_daily\":\"0.1058\",\"roi_days\":900,\"wait_days\":10,\"roi_start_rule\":\"approval_plus_wait_days\",\"roi_end_rule\":\"first_accrual_plus_duration_minus_one\",\"commission_pool\":null,\"commission_days\":null,\"level_commission_eligible\":true,\"tour_eligible_after_days\":30,\"tour_nights\":1,\"tour_days\":2,\"tour_adults\":2,\"tour_children\":1,\"food_coupon_amount\":null,\"food_coupon_currency\":null,\"tour_description\":null,\"label\":\"original_tour_plan_v1_silver\",\"change_note\":\"Restore approved SILVER catalog ROI 0.1058\\u00d7900 for future purchases. v3 had wrong 0.1110 with accrual_authority=false (Admin showed ROI Not Configured). v1\\/v2\\/purchase snapshots untouched.\",\"accrual_authority\":true,\"published_at\":\"2026-11-23T10:30:15.000000Z\",\"published_by\":1,\"updated_at\":\"2026-11-23T10:30:15.000000Z\",\"created_at\":\"2026-11-23T10:30:15.000000Z\",\"id\":4}', NULL, '2026-11-23 05:00:15', '2026-11-23 05:00:15'),
(7, 1, 5, 1, 'plan_published_new_version', NULL, '{\"package_id\":1,\"version_number\":5,\"status\":\"published\",\"created_by\":1,\"has_daily_roi\":true,\"roi_daily\":\"0.1058\",\"roi_days\":900,\"wait_days\":10,\"roi_start_rule\":\"approval_plus_wait_days\",\"roi_end_rule\":\"first_accrual_plus_duration_minus_one\",\"commission_pool\":null,\"commission_days\":null,\"level_commission_eligible\":true,\"tour_eligible_after_days\":null,\"tour_nights\":1,\"tour_days\":2,\"tour_adults\":2,\"tour_children\":1,\"food_coupon_amount\":null,\"food_coupon_currency\":\"INR\",\"tour_description\":null,\"label\":null,\"change_note\":\"simple_admin_package_save\",\"accrual_authority\":true,\"published_at\":\"2026-11-23T11:22:10.000000Z\",\"published_by\":1,\"updated_at\":\"2026-11-23T11:22:10.000000Z\",\"created_at\":\"2026-11-23T11:22:10.000000Z\",\"id\":5}', '127.0.0.1', '2026-11-23 05:52:10', '2026-11-23 05:52:10'),
(8, 1, NULL, 1, 'catalog_updated', '{\"id\":1,\"code\":\"DST-TOUR-001\",\"name\":\"SILVER PACKAGE\",\"package_type\":\"tour_roi\",\"price_usd\":\"50.0000\",\"is_active\":true,\"purchase_available\":true,\"roi_eligible\":true,\"auto_roi_enabled\":null,\"level_commission_eligible\":true,\"income_source\":\"database\",\"active_plan_version_id\":5,\"sort_order\":1}', '{\"id\":1,\"code\":\"DST-TOUR-001\",\"name\":\"SILVER PACKAGE\",\"package_type\":\"tour_roi\",\"price_usd\":\"50.0000\",\"is_active\":true,\"purchase_available\":true,\"roi_eligible\":true,\"auto_roi_enabled\":null,\"level_commission_eligible\":true,\"income_source\":\"database\",\"active_plan_version_id\":5,\"sort_order\":1}', '127.0.0.1', '2026-11-23 05:52:10', '2026-11-23 05:52:10'),
(9, 1, 6, 1, 'plan_published_new_version', NULL, '{\"package_id\":1,\"version_number\":6,\"status\":\"published\",\"created_by\":1,\"has_daily_roi\":true,\"roi_daily\":\"0.1110\",\"roi_days\":900,\"wait_days\":10,\"roi_start_rule\":\"approval_plus_wait_days\",\"roi_end_rule\":\"first_accrual_plus_duration_minus_one\",\"commission_pool\":null,\"commission_days\":null,\"level_commission_eligible\":true,\"tour_eligible_after_days\":null,\"tour_nights\":1,\"tour_days\":2,\"tour_adults\":2,\"tour_children\":1,\"food_coupon_amount\":null,\"food_coupon_currency\":\"INR\",\"tour_description\":null,\"label\":null,\"change_note\":\"simple_admin_package_save\",\"accrual_authority\":true,\"published_at\":\"2026-11-23T11:24:41.000000Z\",\"published_by\":1,\"updated_at\":\"2026-11-23T11:24:41.000000Z\",\"created_at\":\"2026-11-23T11:24:41.000000Z\",\"id\":6}', '127.0.0.1', '2026-11-23 05:54:41', '2026-11-23 05:54:41'),
(10, 1, NULL, 1, 'catalog_updated', '{\"id\":1,\"code\":\"DST-TOUR-001\",\"name\":\"SILVER PACKAGE\",\"package_type\":\"tour_roi\",\"price_usd\":\"50.0000\",\"is_active\":true,\"purchase_available\":true,\"roi_eligible\":true,\"auto_roi_enabled\":true,\"level_commission_eligible\":true,\"income_source\":\"database\",\"active_plan_version_id\":6,\"sort_order\":1}', '{\"id\":1,\"code\":\"DST-TOUR-001\",\"name\":\"SILVER PACKAGE\",\"package_type\":\"tour_roi\",\"price_usd\":\"50.0000\",\"is_active\":true,\"purchase_available\":true,\"roi_eligible\":true,\"auto_roi_enabled\":true,\"level_commission_eligible\":true,\"income_source\":\"database\",\"active_plan_version_id\":6,\"sort_order\":1}', '127.0.0.1', '2026-11-23 05:54:41', '2026-11-23 05:54:41'),
(11, 1, 7, 1, 'plan_published_new_version', NULL, '{\"package_id\":1,\"version_number\":7,\"status\":\"published\",\"created_by\":1,\"has_daily_roi\":true,\"roi_daily\":\"0.1100\",\"roi_days\":900,\"wait_days\":10,\"roi_start_rule\":\"approval_plus_wait_days\",\"roi_end_rule\":\"first_accrual_plus_duration_minus_one\",\"commission_pool\":null,\"commission_days\":null,\"level_commission_eligible\":true,\"tour_eligible_after_days\":null,\"tour_nights\":1,\"tour_days\":2,\"tour_adults\":2,\"tour_children\":1,\"food_coupon_amount\":null,\"food_coupon_currency\":null,\"tour_description\":null,\"label\":null,\"change_note\":\"admin requested daily roi 0.11\",\"accrual_authority\":true,\"published_at\":\"2026-11-23T11:33:46.000000Z\",\"published_by\":1,\"updated_at\":\"2026-11-23T11:33:46.000000Z\",\"created_at\":\"2026-11-23T11:33:46.000000Z\",\"id\":7}', NULL, '2026-11-23 06:03:46', '2026-11-23 06:03:46');

-- --------------------------------------------------------

--
-- Table structure for table `package_plan_versions`
--

CREATE TABLE `package_plan_versions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `package_id` bigint(20) UNSIGNED NOT NULL,
  `version_number` int(10) UNSIGNED NOT NULL,
  `status` varchar(24) NOT NULL DEFAULT 'draft',
  `has_daily_roi` tinyint(1) NOT NULL DEFAULT 0,
  `roi_daily` decimal(18,4) DEFAULT NULL,
  `roi_days` int(10) UNSIGNED DEFAULT NULL,
  `wait_days` int(10) UNSIGNED DEFAULT NULL,
  `roi_start_rule` varchar(64) DEFAULT NULL,
  `roi_end_rule` varchar(64) DEFAULT NULL,
  `commission_pool` decimal(18,4) DEFAULT NULL,
  `commission_days` int(10) UNSIGNED DEFAULT NULL,
  `level_commission_eligible` tinyint(1) NOT NULL DEFAULT 0,
  `tour_eligible_after_days` int(10) UNSIGNED DEFAULT NULL,
  `tour_nights` int(10) UNSIGNED DEFAULT NULL,
  `tour_days` int(10) UNSIGNED DEFAULT NULL,
  `tour_adults` int(10) UNSIGNED DEFAULT NULL,
  `tour_children` int(10) UNSIGNED DEFAULT NULL,
  `food_coupon_amount` decimal(18,4) DEFAULT NULL,
  `food_coupon_currency` varchar(16) DEFAULT NULL,
  `validity_unlimited` tinyint(1) NOT NULL DEFAULT 0,
  `validity_duration` int(10) UNSIGNED DEFAULT NULL,
  `validity_unit` varchar(16) DEFAULT NULL,
  `tour_description` text DEFAULT NULL,
  `label` varchar(120) DEFAULT NULL,
  `change_note` text DEFAULT NULL,
  `accrual_authority` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `published_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `package_plan_versions`
--

INSERT INTO `package_plan_versions` (`id`, `package_id`, `version_number`, `status`, `has_daily_roi`, `roi_daily`, `roi_days`, `wait_days`, `roi_start_rule`, `roi_end_rule`, `commission_pool`, `commission_days`, `level_commission_eligible`, `tour_eligible_after_days`, `tour_nights`, `tour_days`, `tour_adults`, `tour_children`, `food_coupon_amount`, `food_coupon_currency`, `validity_unlimited`, `validity_duration`, `validity_unit`, `tour_description`, `label`, `change_note`, `accrual_authority`, `published_at`, `created_by`, `published_by`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'superseded', 1, 0.1110, 900, 9, 'approval_plus_wait_days', 'first_accrual_plus_duration_minus_one', 0.2400, 400, 1, 30, 1, 2, 2, 1, 1000.0000, 'INR', 0, NULL, NULL, 'Tour description', NULL, '511188', 1, '2026-11-23 04:21:14', 1, 1, '2026-11-23 04:21:14', '2026-11-23 04:27:25'),
(2, 1, 2, 'superseded', 1, 0.1058, 900, 10, 'approval_plus_wait_days', 'first_accrual_plus_duration_minus_one', NULL, NULL, 1, 30, 1, 2, 2, 1, NULL, NULL, 0, NULL, NULL, NULL, 'original_tour_plan_v1_silver', 'Corrected approved Original Tour Plan SILVER 0.1058×900. Prior 0.1110 preserved for existing purchase.', 1, '2026-11-23 04:27:25', 1, 1, '2026-11-23 04:27:25', '2026-11-23 04:36:50'),
(3, 1, 3, 'superseded', 1, 0.1110, 900, 10, 'approval_plus_wait_days', 'first_accrual_plus_duration_minus_one', NULL, NULL, 1, 30, 1, 2, 2, 1, NULL, 'INR', 0, NULL, NULL, 'Tour details (catalog)', 'original_tour_plan_v1_silver', 'Corrected approved Original Tour Plan SILVER 0.1058×900. Prior 0.1110 preserved for existing purchase.', 0, '2026-11-23 04:36:50', 1, 1, '2026-11-23 04:36:50', '2026-11-23 05:00:15'),
(4, 1, 4, 'superseded', 1, 0.1058, 900, 10, 'approval_plus_wait_days', 'first_accrual_plus_duration_minus_one', NULL, NULL, 1, 30, 1, 2, 2, 1, NULL, NULL, 0, NULL, NULL, NULL, 'original_tour_plan_v1_silver', 'Restore approved SILVER catalog ROI 0.1058×900 for future purchases. v3 had wrong 0.1110 with accrual_authority=false (Admin showed ROI Not Configured). v1/v2/purchase snapshots untouched.', 1, '2026-11-23 05:00:15', 1, 1, '2026-11-23 05:00:15', '2026-11-23 05:52:10'),
(5, 1, 5, 'superseded', 1, 0.1058, 900, 10, 'approval_plus_wait_days', 'first_accrual_plus_duration_minus_one', NULL, NULL, 1, NULL, 1, 2, 2, 1, NULL, 'INR', 0, NULL, NULL, NULL, NULL, 'simple_admin_package_save', 1, '2026-11-23 05:52:10', 1, 1, '2026-11-23 05:52:10', '2026-11-23 05:54:41'),
(6, 1, 6, 'superseded', 1, 0.1110, 900, 10, 'approval_plus_wait_days', 'first_accrual_plus_duration_minus_one', NULL, NULL, 1, NULL, 1, 2, 2, 1, NULL, 'INR', 0, NULL, NULL, NULL, NULL, 'simple_admin_package_save', 1, '2026-11-23 05:54:41', 1, 1, '2026-11-23 05:54:41', '2026-11-23 06:03:46'),
(7, 1, 7, 'published', 1, 0.1100, 900, 10, 'approval_plus_wait_days', 'first_accrual_plus_duration_minus_one', NULL, NULL, 1, NULL, 1, 2, 2, 1, NULL, NULL, 0, NULL, NULL, NULL, NULL, 'admin requested daily roi 0.11', 1, '2026-11-23 06:03:46', 1, 1, '2026-11-23 06:03:46', '2026-11-23 06:03:46');

-- --------------------------------------------------------

--
-- Table structure for table `package_purchases`
--

CREATE TABLE `package_purchases` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(255) NOT NULL,
  `idempotency_key` char(36) NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `package_id` bigint(20) UNSIGNED NOT NULL,
  `package_plan_version_id` bigint(20) UNSIGNED DEFAULT NULL,
  `validity_unlimited` tinyint(1) DEFAULT NULL,
  `validity_duration` int(10) UNSIGNED DEFAULT NULL,
  `validity_unit` varchar(16) DEFAULT NULL,
  `package_code` varchar(255) NOT NULL,
  `package_name` varchar(255) NOT NULL,
  `package_type` varchar(255) DEFAULT NULL,
  `price_usd` decimal(16,4) NOT NULL,
  `tour_title` varchar(255) DEFAULT NULL,
  `tour_details` text DEFAULT NULL,
  `duration` varchar(255) DEFAULT NULL,
  `benefits` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `full_description` text DEFAULT NULL,
  `destinations` text DEFAULT NULL,
  `inclusions` text DEFAULT NULL,
  `exclusions` text DEFAULT NULL,
  `currency_label` varchar(255) DEFAULT NULL,
  `network` varchar(255) DEFAULT NULL,
  `payment_reference` varchar(255) NOT NULL,
  `payment_method` varchar(32) DEFAULT NULL,
  `funding_source` varchar(32) DEFAULT NULL,
  `funding_wallet` varchar(20) DEFAULT NULL,
  `status` varchar(255) NOT NULL DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `submitted_at` datetime NOT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `denomination` varchar(8) NOT NULL DEFAULT 'USD'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `package_purchases`
--

INSERT INTO `package_purchases` (`id`, `reference`, `idempotency_key`, `member_id`, `package_id`, `package_plan_version_id`, `validity_unlimited`, `validity_duration`, `validity_unit`, `package_code`, `package_name`, `package_type`, `price_usd`, `tour_title`, `tour_details`, `duration`, `benefits`, `description`, `full_description`, `destinations`, `inclusions`, `exclusions`, `currency_label`, `network`, `payment_reference`, `payment_method`, `funding_source`, `funding_wallet`, `status`, `rejection_reason`, `submitted_at`, `reviewed_at`, `reviewed_by`, `created_at`, `updated_at`, `denomination`) VALUES
(1, 'PUR-20261123-NIZ4KE', 'fb8d6d8c-2e76-4e7d-bbba-3fd710f59ea1', 'ST100000', 1, 1, NULL, NULL, NULL, 'DST-TOUR-001', 'SILVER PACKAGE', 'tour_roi', 50.0000, 'dummy benefits', 'Tour details (catalog)', '2 Days / 1 Nights', 'Benefits', 'Short description', 'Full description', 'Tour destinations ---declare', 'Inclusions', 'Exclusions', 'USDT', 'USDT', 'DWDDEWFFSEGEG', 'usdt', NULL, NULL, 'approved', NULL, '2026-11-23 09:53:59', '2026-11-23 09:53:59', 1, '2026-11-23 04:23:59', '2026-11-23 04:23:59', 'USDT');

-- --------------------------------------------------------

--
-- Table structure for table `package_purchase_reservations`
--

CREATE TABLE `package_purchase_reservations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `package_purchase_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `wallet` varchar(20) NOT NULL,
  `reserved_amount` decimal(20,4) NOT NULL,
  `allocations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allocations`)),
  `status` enum('active','settled','released') NOT NULL DEFAULT 'active',
  `reservation_reference` varchar(255) NOT NULL,
  `idempotency_key` char(36) NOT NULL,
  `reserved_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `settled_at` timestamp NULL DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `package_roi_accruals`
--

CREATE TABLE `package_roi_accruals` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(255) NOT NULL,
  `idempotency_key` varchar(255) NOT NULL,
  `package_purchase_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `package_code` varchar(255) NOT NULL,
  `package_name` varchar(255) NOT NULL,
  `business_date` date NOT NULL,
  `amount` decimal(16,4) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'accrued',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_decisions`
--

CREATE TABLE `payment_decisions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `package_purchase_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_decisions`
--

INSERT INTO `payment_decisions` (`id`, `package_purchase_id`, `user_id`, `action`, `reason`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'approved', NULL, '2026-11-23 04:23:59', '2026-11-23 04:23:59');

-- --------------------------------------------------------

--
-- Table structure for table `payment_settings`
--

CREATE TABLE `payment_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `currency_label` varchar(255) DEFAULT NULL,
  `network` varchar(255) DEFAULT NULL,
  `receiving_address` varchar(255) DEFAULT NULL,
  `qr_image_path` varchar(255) DEFAULT NULL,
  `instructions` text DEFAULT NULL,
  `reference_pattern` varchar(255) DEFAULT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payment_settings`
--

INSERT INTO `payment_settings` (`id`, `currency_label`, `network`, `receiving_address`, `qr_image_path`, `instructions`, `reference_pattern`, `is_enabled`, `created_at`, `updated_at`) VALUES
(1, NULL, NULL, NULL, NULL, NULL, NULL, 0, '2026-11-23 04:23:59', '2026-11-23 04:23:59');

-- --------------------------------------------------------

--
-- Table structure for table `production_initializations`
--

CREATE TABLE `production_initializations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `marker` varchar(255) NOT NULL,
  `driver` varchar(255) NOT NULL,
  `host` varchar(255) NOT NULL DEFAULT '',
  `port` varchar(255) NOT NULL DEFAULT '',
  `database_name` varchar(255) NOT NULL,
  `initialized_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ranks`
--

CREATE TABLE `ranks` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `required_full_team_business` decimal(20,4) NOT NULL,
  `unlocked_levels` tinyint(3) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` tinyint(3) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rank_achievements`
--

CREATE TABLE `rank_achievements` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `rank_id` bigint(20) UNSIGNED NOT NULL,
  `qualifying_business_amount` decimal(20,4) NOT NULL DEFAULT 0.0000,
  `achieved_at` datetime NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_summary_generations`
--

CREATE TABLE `report_summary_generations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'building',
  `roi_last_transaction_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `level_last_transaction_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `roi_source_max_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `level_source_max_id` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `source_timezone` varchar(64) DEFAULT NULL,
  `failure_message` text DEFAULT NULL,
  `validated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `report_summary_state`
--

CREATE TABLE `report_summary_state` (
  `id` tinyint(3) UNSIGNED NOT NULL,
  `active_generation_id` bigint(20) UNSIGNED DEFAULT NULL,
  `writes_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `rebuild_in_progress` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reward_assignments`
--

CREATE TABLE `reward_assignments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `qualification_id` bigint(20) UNSIGNED NOT NULL,
  `campaign_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `gift_name` varchar(255) NOT NULL,
  `status` varchar(20) NOT NULL,
  `selected_at` timestamp NULL DEFAULT NULL,
  `superseded_at` timestamp NULL DEFAULT NULL,
  `superseded_by_campaign_id` bigint(20) UNSIGNED DEFAULT NULL,
  `conflict_campaign_id` bigint(20) UNSIGNED DEFAULT NULL,
  `conflict_note` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reward_campaigns`
--

CREATE TABLE `reward_campaigns` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `gift_name` varchar(255) NOT NULL,
  `gift_image_path` varchar(255) DEFAULT NULL,
  `video_path` varchar(255) DEFAULT NULL,
  `gift_description` text DEFAULT NULL,
  `target_amount` decimal(16,4) NOT NULL,
  `denomination` varchar(8) NOT NULL DEFAULT 'USDT',
  `starts_on` date NOT NULL,
  `ends_on` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `promote_on_login` tinyint(1) NOT NULL DEFAULT 1,
  `stock_quantity` int(10) UNSIGNED DEFAULT NULL,
  `terms` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `published_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reward_campaigns`
--

INSERT INTO `reward_campaigns` (`id`, `title`, `description`, `gift_name`, `gift_image_path`, `video_path`, `gift_description`, `target_amount`, `denomination`, `starts_on`, `ends_on`, `status`, `promote_on_login`, `stock_quantity`, `terms`, `admin_notes`, `published_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Campaign title', 'Campaign description', 'Reward gift name', 'rewards/C7S3V4xpm3onNIJk6OLj9wfZsOs7x6Kp7mS5sYWb.jpg', NULL, 'Gift description', 1000.0000, 'USDT', '2026-11-23', '2026-11-29', 'active', 1, 2, 'Terms and conditions', 'Internal admin notes', '2026-11-23 07:27:13', 1, '2026-11-23 07:27:10', '2026-11-23 07:27:13');

-- --------------------------------------------------------

--
-- Table structure for table `reward_deliveries`
--

CREATE TABLE `reward_deliveries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `assignment_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `method` varchar(255) DEFAULT NULL,
  `tracking_reference` varchar(255) DEFAULT NULL,
  `dispatched_at` timestamp NULL DEFAULT NULL,
  `delivered_at` timestamp NULL DEFAULT NULL,
  `recipient_confirmation` varchar(255) DEFAULT NULL,
  `internal_notes` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reward_delivery_events`
--

CREATE TABLE `reward_delivery_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `delivery_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `from_status` varchar(20) NOT NULL,
  `to_status` varchar(20) NOT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reward_evaluation_runs`
--

CREATE TABLE `reward_evaluation_runs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `business_date` date NOT NULL,
  `status` varchar(20) NOT NULL,
  `members_reviewed` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `selected_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `message` text NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reward_qualifications`
--

CREATE TABLE `reward_qualifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `campaign_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `sponsor_id` varchar(255) DEFAULT NULL,
  `achieved_amount` decimal(16,4) NOT NULL,
  `target_amount` decimal(16,4) NOT NULL,
  `status` varchar(20) NOT NULL,
  `qualified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reward_qualifications`
--

INSERT INTO `reward_qualifications` (`id`, `campaign_id`, `member_id`, `member_name`, `sponsor_id`, `achieved_amount`, `target_amount`, `status`, `qualified_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'ST100000', 'Rahul Das', 'ST666666', 50.0000, 1000.0000, 'in_progress', NULL, '2026-11-23 07:27:13', '2026-11-23 07:27:13');

-- --------------------------------------------------------

--
-- Table structure for table `roi_report_global_daily_summaries`
--

CREATE TABLE `roi_report_global_daily_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `generation_id` bigint(20) UNSIGNED NOT NULL,
  `date_key` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `transaction_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `total_income_amount` decimal(38,4) NOT NULL DEFAULT 0.0000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roi_report_member_daily_summaries`
--

CREATE TABLE `roi_report_member_daily_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `generation_id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `date_key` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `transaction_count` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `total_income_amount` decimal(38,4) NOT NULL DEFAULT 0.0000
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roi_transactions`
--

CREATE TABLE `roi_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(255) NOT NULL,
  `investment_id` varchar(255) NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `member_name` varchar(255) NOT NULL,
  `on_amount` decimal(20,4) NOT NULL,
  `rate_percentage` decimal(6,3) DEFAULT NULL,
  `income_amount` decimal(20,4) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'generated',
  `roi_date` date NOT NULL,
  `withdrawable_on` date DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `user_id`, `ip_address`, `user_agent`, `payload`, `last_activity`) VALUES
('1GJEkbloWkvgF54ETfAf29A7z1PbwrVDslq5beyq', NULL, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/3.32.4 Chrome/150.0.7871.250 Electron/43.8.9 Safari/537.36', 'YTozOntzOjY6Il90b2tlbiI7czo0MDoiaE9QMFFJR2g5em5jN2ZKejVRa3BkOWxCQ0ROTFBhYWpKdG00bzFJWCI7czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MjE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMCI7czo1OiJyb3V0ZSI7czo0OiJob21lIjt9czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319fQ==', 1795674614),
('iTJpU9lIhnS6Szvnf7jGmjnU3AiSUm56fCRS7ety', 1, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/158.0.0.0 Safari/537.36', 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoiZ2tmT0ZPcGF5WlFJb3JqaWJxQVE2Y3N2ZG00dHJzRnVoYWZWc25pVCI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6MzE6Imh0dHA6Ly8xMjcuMC4wLjE6ODAwMC9kYXNoYm9hcmQiO3M6NToicm91dGUiO3M6OToiZGFzaGJvYXJkIjt9czo1MDoibG9naW5fd2ViXzU5YmEzNmFkZGMyYjJmOTQwMTU4MGYwMTRjN2Y1OGVhNGUzMDk4OWQiO2k6MTt9', 1795677269),
('tho2CNtl3p8SE04KBiq2zePdfzeZy6tjj95eKCSo', 3, '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Cursor/3.32.4 Chrome/150.0.7871.250 Electron/43.8.9 Safari/537.36', 'YTo1OntzOjY6Il90b2tlbiI7czo0MDoiVms3RlFyV2U1ZTJreWdtcmpXRDZhYmlXVGtLaXY2bjRQczVuYXpXeCI7czo2OiJfZmxhc2giO2E6Mjp7czozOiJvbGQiO2E6MDp7fXM6MzoibmV3IjthOjA6e319czo5OiJfcHJldmlvdXMiO2E6Mjp7czozOiJ1cmwiO3M6NDY6Imh0dHA6Ly8xMjcuMC4wLjE6ODAxMC9hZG1pbi9zZXR0aW5ncy9maW5hbmNpYWwiO3M6NToicm91dGUiO3M6MjQ6ImFkbWluLnNldHRpbmdzLmZpbmFuY2lhbCI7fXM6MzoidXJsIjthOjE6e3M6ODoiaW50ZW5kZWQiO3M6MzM6Imh0dHA6Ly8xMjcuMC4wLjE6ODAxMC9hZG1pbi9sb2dpbiI7fXM6NTA6ImxvZ2luX3dlYl81OWJhMzZhZGRjMmIyZjk0MDE1ODBmMDE0YzdmNThlYTRlMzA5ODlkIjtpOjM7fQ==', 1795673425);

-- --------------------------------------------------------

--
-- Table structure for table `simulation_checkpoints`
--

CREATE TABLE `simulation_checkpoints` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `run_key` varchar(64) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `last_completed_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'ready',
  `processed_days` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `cumulative_roi_amount` decimal(38,4) NOT NULL DEFAULT 0.0000,
  `cumulative_level_commission_amount` decimal(38,4) NOT NULL DEFAULT 0.0000,
  `failure_message` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `simulation_daily_investment_income`
--

CREATE TABLE `simulation_daily_investment_income` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `investment_id` varchar(255) NOT NULL,
  `business_date` date NOT NULL,
  `roi_income` decimal(38,4) NOT NULL DEFAULT 0.0000,
  `commission_income` decimal(38,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `simulation_daily_member_wallets`
--

CREATE TABLE `simulation_daily_member_wallets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `business_date` date NOT NULL,
  `roi_income` decimal(38,4) NOT NULL DEFAULT 0.0000,
  `commission_income` decimal(38,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `simulation_investment_income_totals`
--

CREATE TABLE `simulation_investment_income_totals` (
  `investment_id` varchar(255) NOT NULL,
  `roi_income` decimal(38,4) NOT NULL DEFAULT 0.0000,
  `commission_income` decimal(38,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `simulation_member_wallet_totals`
--

CREATE TABLE `simulation_member_wallet_totals` (
  `member_id` varchar(255) NOT NULL,
  `roi_income` decimal(38,4) NOT NULL DEFAULT 0.0000,
  `commission_income` decimal(38,4) NOT NULL DEFAULT 0.0000,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `stress_test_datasets`
--

CREATE TABLE `stress_test_datasets` (
  `dataset_id` varchar(255) NOT NULL,
  `root_member_id` varchar(255) NOT NULL,
  `member_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `investment_count` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `status` varchar(255) NOT NULL DEFAULT 'seeded',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_attachments`
--

CREATE TABLE `support_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `support_message_id` bigint(20) UNSIGNED NOT NULL,
  `disk` varchar(20) NOT NULL DEFAULT 'local',
  `path` varchar(255) NOT NULL,
  `original_name` varchar(120) NOT NULL,
  `mime` varchar(80) NOT NULL,
  `size` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_messages`
--

CREATE TABLE `support_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `support_ticket_id` bigint(20) UNSIGNED NOT NULL,
  `sender_role` varchar(20) NOT NULL,
  `sender_member_id` varchar(255) DEFAULT NULL,
  `sender_user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `body` text NOT NULL,
  `idempotency_key` char(36) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_notifications`
--

CREATE TABLE `support_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `audience` varchar(20) NOT NULL,
  `member_id` varchar(255) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `support_ticket_id` bigint(20) UNSIGNED DEFAULT NULL,
  `tour_interest_request_id` bigint(20) UNSIGNED DEFAULT NULL,
  `summary` varchar(180) NOT NULL,
  `email_status` varchar(20) NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `support_notifications`
--

INSERT INTO `support_notifications` (`id`, `audience`, `member_id`, `user_id`, `support_ticket_id`, `tour_interest_request_id`, `summary`, `email_status`, `read_at`, `created_at`) VALUES
(1, 'admin', NULL, 1, NULL, 1, 'New tour interest from ST100000 (Rahul Das).', 'skipped', NULL, '2026-11-24 04:05:35'),
(2, 'admin', NULL, 2, NULL, 1, 'New tour interest from ST100000 (Rahul Das).', 'skipped', NULL, '2026-11-24 04:05:35'),
(3, 'member', 'ST100000', NULL, NULL, 1, 'Your tour interest request is now Contacted.', 'skipped', NULL, '2026-11-24 04:06:01');

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(255) NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `category` varchar(40) NOT NULL,
  `priority` varchar(20) NOT NULL,
  `status` varchar(30) NOT NULL,
  `idempotency_key` char(36) NOT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `closed_by` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tour_interest_requests`
--

CREATE TABLE `tour_interest_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `member_id` varchar(32) NOT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'pending',
  `active_marker` char(1) DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `contacted_at` timestamp NULL DEFAULT NULL,
  `withdrawn_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `assigned_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `source` varchar(32) NOT NULL DEFAULT 'member_dashboard',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tour_interest_requests`
--

INSERT INTO `tour_interest_requests` (`id`, `member_id`, `status`, `active_marker`, `submitted_at`, `contacted_at`, `withdrawn_at`, `closed_at`, `assigned_admin_id`, `admin_notes`, `source`, `created_at`, `updated_at`) VALUES
(1, 'ST100000', 'cancelled', NULL, '2026-11-24 09:36:01', '2026-11-24 04:06:01', '2026-11-24 04:06:01', '2026-11-24 04:06:01', 1, 'Audit private note — must not surface to member', 'member_dashboard', '2026-11-24 04:05:35', '2026-11-24 04:06:01');

-- --------------------------------------------------------

--
-- Table structure for table `tour_interest_request_events`
--

CREATE TABLE `tour_interest_request_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tour_interest_request_id` bigint(20) UNSIGNED NOT NULL,
  `action` varchar(64) NOT NULL,
  `from_status` varchar(32) DEFAULT NULL,
  `to_status` varchar(32) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `actor_type` varchar(16) NOT NULL,
  `actor_member_id` varchar(32) DEFAULT NULL,
  `actor_admin_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tour_interest_request_events`
--

INSERT INTO `tour_interest_request_events` (`id`, `tour_interest_request_id`, `action`, `from_status`, `to_status`, `note`, `actor_type`, `actor_member_id`, `actor_admin_id`, `created_at`, `updated_at`) VALUES
(1, 1, 'submitted', NULL, 'pending', 'Member expressed tour interest.', 'member', 'ST100000', NULL, '2026-11-24 04:05:35', '2026-11-24 04:05:35'),
(2, 1, 'status_updated', 'pending', 'contacted', 'Status updated by admin.', 'admin', NULL, 1, '2026-11-24 04:06:01', '2026-11-24 04:06:01'),
(3, 1, 'withdrawn', 'contacted', 'cancelled', 'Member withdrew tour interest.', 'member', 'ST100000', NULL, '2026-11-24 04:06:01', '2026-11-24 04:06:01');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `login_id` varchar(64) DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `admin_role` varchar(16) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `login_id`, `is_admin`, `admin_role`, `is_active`, `must_change_password`, `last_login_at`, `created_by`, `email_verified_at`, `password`, `remember_token`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Delta Support Admin', 'admin@deltasupport.local', NULL, 1, 'main', 1, 0, '2026-11-26 01:44:21', NULL, NULL, '$2y$12$ADBZiaf8HfYsoFlIz.047OR.evym4x.BV9JfPCzIO05AWOjgNP8fy', NULL, '2026-11-23 04:06:09', '2026-11-26 01:44:21', NULL),
(2, 'Phase1 Visual Admin', 'phase1.visual.admin@local-test.invalid', NULL, 1, 'main', 1, 0, NULL, NULL, NULL, '$2y$12$zDF.hXH/tbGoURJu.VmP.ez8wYC.D4eIkJLvopmaMivzpzU7hoVhW', NULL, '2026-11-23 23:59:02', '2026-11-26 00:25:34', NULL),
(3, 'E2E Sub Admin', 'e2esub01@example.test', 'E2ESUB01', 1, 'sub', 1, 0, '2026-11-26 00:39:57', 1, NULL, '$2y$12$mC36t.aR6dBb.X5LjqQrhuiw4Jh2V9cDBMm3JZR/6j27V.UcwRzvC', NULL, '2026-11-26 00:37:58', '2026-11-26 00:39:57', NULL),
(4, 'Sk Nadeem', 'sknadimsk8509@gmail.com', 'ST888888', 1, 'sub', 0, 0, '2026-11-26 00:48:28', 1, NULL, '$2y$12$aTw8fAyP27HBvzwI2iUwsOUf8WPcPCa0eb1R.AdK6qmsrzjacT57y', NULL, '2026-11-26 00:45:20', '2026-11-26 01:07:17', NULL),
(5, 'Sk Nadeem', 'sknadim6297@gmail.com', 'ST999999', 1, 'sub', 1, 0, '2026-11-26 01:09:09', 1, NULL, '$2y$12$FZz6Clv8M0RfSzp5I.VnAeyAl6fnQRfKSZv/35xejKWC9w36LdXNq', NULL, '2026-11-26 01:08:48', '2026-11-26 01:09:09', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `wallet_ledger_entries`
--

CREATE TABLE `wallet_ledger_entries` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `wallet` varchar(20) NOT NULL,
  `direction` enum('credit','debit','transfer','hold','release','reversal','settlement','adjustment') NOT NULL,
  `amount` decimal(20,4) NOT NULL,
  `opening_balance` decimal(20,4) DEFAULT NULL,
  `closing_balance` decimal(20,4) DEFAULT NULL,
  `denomination` varchar(8) NOT NULL DEFAULT 'USDT',
  `member_id` varchar(255) NOT NULL,
  `reference` varchar(255) NOT NULL,
  `idempotency_key` char(36) NOT NULL,
  `package_purchase_id` bigint(20) UNSIGNED DEFAULT NULL,
  `withdrawal_request_id` bigint(20) UNSIGNED DEFAULT NULL,
  `counter_entry_id` bigint(20) UNSIGNED DEFAULT NULL,
  `actor` varchar(255) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_requests`
--

CREATE TABLE `withdrawal_requests` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `reference` varchar(255) NOT NULL,
  `idempotency_key` char(36) NOT NULL,
  `member_id` varchar(255) NOT NULL,
  `source_wallet` varchar(20) NOT NULL,
  `amount` decimal(20,4) NOT NULL,
  `charge_amount` decimal(20,4) DEFAULT NULL,
  `net_amount` decimal(20,4) DEFAULT NULL,
  `denomination` varchar(8) NOT NULL DEFAULT 'USDT',
  `network` varchar(255) DEFAULT NULL,
  `receiving_address` varchar(255) DEFAULT NULL,
  `status` enum('draft','pending_review','approved','processing','completed','rejected','cancelled','failed') NOT NULL DEFAULT 'draft',
  `payout_network` varchar(255) DEFAULT NULL,
  `payout_txid` varchar(255) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_settings`
--

CREATE TABLE `withdrawal_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `minimum_amount` decimal(20,4) NOT NULL DEFAULT 20.0000,
  `maximum_amount` decimal(20,4) DEFAULT NULL,
  `charge_type` varchar(20) DEFAULT NULL,
  `charge_value` decimal(20,4) DEFAULT NULL,
  `daily_limit` decimal(20,4) DEFAULT NULL,
  `monthly_limit` decimal(20,4) DEFAULT NULL,
  `eligible_wallets` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`eligible_wallets`)),
  `supported_networks` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`supported_networks`)),
  `address_validation_policy` text DEFAULT NULL,
  `member_cancellation_policy` text DEFAULT NULL,
  `submissions_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `denomination` varchar(8) NOT NULL DEFAULT 'USDT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `withdrawal_settings`
--

INSERT INTO `withdrawal_settings` (`id`, `minimum_amount`, `maximum_amount`, `charge_type`, `charge_value`, `daily_limit`, `monthly_limit`, `eligible_wallets`, `supported_networks`, `address_validation_policy`, `member_cancellation_policy`, `submissions_enabled`, `denomination`, `created_at`, `updated_at`) VALUES
(1, 20.0000, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0, 'USDT', '2026-11-23 04:06:30', '2026-11-23 04:06:30');

-- --------------------------------------------------------

--
-- Table structure for table `withdrawal_status_events`
--

CREATE TABLE `withdrawal_status_events` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `withdrawal_request_id` bigint(20) UNSIGNED NOT NULL,
  `from_status` varchar(40) DEFAULT NULL,
  `to_status` varchar(40) NOT NULL,
  `actor` varchar(255) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activation_wallet_transactions`
--
ALTER TABLE `activation_wallet_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `activation_wallet_transactions_reference_unique` (`reference`),
  ADD KEY `activation_wallet_transactions_member_id_created_at_index` (`member_id`,`created_at`),
  ADD KEY `activation_wallet_transactions_type_index` (`type`),
  ADD KEY `activation_wallet_created_at_index` (`created_at`);

--
-- Indexes for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `admin_activity_logs_user_id_created_at_index` (`user_id`,`created_at`);

--
-- Indexes for table `admin_user_permissions`
--
ALTER TABLE `admin_user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `admin_user_permissions_user_perm_unique` (`user_id`,`permission_key`),
  ADD KEY `admin_user_permissions_permission_key_index` (`permission_key`);

--
-- Indexes for table `associate_commission_packages`
--
ALTER TABLE `associate_commission_packages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `assoc_comm_pkg_version_tier_uq` (`associate_commission_version_id`,`tier_code`),
  ADD KEY `associate_commission_packages_mapped_package_code_index` (`mapped_package_code`);

--
-- Indexes for table `associate_commission_ranks`
--
ALTER TABLE `associate_commission_ranks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `assoc_comm_rank_pkg_rank_uq` (`associate_commission_package_id`,`rank`);

--
-- Indexes for table `associate_commission_versions`
--
ALTER TABLE `associate_commission_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `associate_commission_versions_version_code_unique` (`version_code`);

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
-- Indexes for table `commission_plan_levels`
--
ALTER TABLE `commission_plan_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `commission_plan_levels_plan_code_level_unique` (`plan_code`,`level`);

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`);

--
-- Indexes for table `financial_controls`
--
ALTER TABLE `financial_controls`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `financial_historical_recovery_runs`
--
ALTER TABLE `financial_historical_recovery_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `financial_historical_recovery_runs_from_date_to_date_index` (`from_date`,`to_date`),
  ADD KEY `financial_historical_recovery_runs_status_index` (`status`);

--
-- Indexes for table `financial_tour_recovery_runs`
--
ALTER TABLE `financial_tour_recovery_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `financial_tour_recovery_runs_business_date_index` (`business_date`),
  ADD KEY `financial_tour_recovery_runs_status_index` (`status`),
  ADD KEY `financial_tour_recovery_runs_mode_created_at_index` (`mode`,`created_at`);

--
-- Indexes for table `impersonation_logs`
--
ALTER TABLE `impersonation_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `impersonation_logs_admin_id_foreign` (`admin_id`);

--
-- Indexes for table `investments`
--
ALTER TABLE `investments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `investments_investment_id_unique` (`investment_id`),
  ADD KEY `investments_member_id_status_index` (`member_id`,`status`),
  ADD KEY `investments_status_created_at_index` (`status`,`created_at`),
  ADD KEY `investments_status_closed_at_index` (`status`,`closed_at`);

--
-- Indexes for table `investment_withdrawals`
--
ALTER TABLE `investment_withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `investment_withdrawals_withdrawal_id_unique` (`withdrawal_id`),
  ADD UNIQUE KEY `investment_withdrawals_investment_id_unique` (`investment_id`),
  ADD KEY `investment_withdrawals_member_id_status_withdrawn_at_index` (`member_id`,`status`,`withdrawn_at`),
  ADD KEY `investment_withdrawals_investment_id_status_index` (`investment_id`,`status`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`),
  ADD UNIQUE KEY `invoices_package_purchase_id_unique` (`package_purchase_id`),
  ADD KEY `invoices_member_id_index` (`member_id`),
  ADD KEY `invoices_approved_at_index` (`approved_at`);

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
-- Indexes for table `level_commissions`
--
ALTER TABLE `level_commissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `level_commissions_level_unique` (`level`),
  ADD KEY `level_commissions_is_active_level_index` (`is_active`,`level`);

--
-- Indexes for table `level_commission_report_global_daily_summaries`
--
ALTER TABLE `level_commission_report_global_daily_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lc_global_daily_generation_date_unique` (`generation_id`,`date_key`);

--
-- Indexes for table `level_commission_report_level_daily_summaries`
--
ALTER TABLE `level_commission_report_level_daily_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lc_level_daily_generation_level_date_unique` (`generation_id`,`level`,`date_key`);

--
-- Indexes for table `level_commission_report_member_level_daily_summaries`
--
ALTER TABLE `level_commission_report_member_level_daily_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `lc_member_level_daily_generation_member_level_date_unique` (`generation_id`,`member_id`,`level`,`date_key`);

--
-- Indexes for table `level_commission_transactions`
--
ALTER TABLE `level_commission_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `level_commission_transactions_reference_unique` (`reference`),
  ADD UNIQUE KEY `level_commission_investment_member_date_unique` (`investment_id`,`member_id`,`business_date`),
  ADD KEY `level_commission_transactions_member_id_created_at_index` (`member_id`,`created_at`),
  ADD KEY `level_commission_transactions_investment_id_business_date_index` (`investment_id`,`business_date`),
  ADD KEY `lct_created_at_id_index` (`created_at`,`id`),
  ADD KEY `lct_level_created_at_id_index` (`level`,`created_at`,`id`),
  ADD KEY `lct_income_amount_index` (`income_amount`),
  ADD KEY `lct_member_created_at_id_index` (`member_id`,`created_at`,`id`),
  ADD KEY `lct_level_member_created_at_id_index` (`level`,`member_id`,`created_at`,`id`),
  ADD KEY `lct_member_level_created_at_id_index` (`member_id`,`level`,`created_at`,`id`),
  ADD KEY `lc_simulation_date_investment_index` (`business_date`,`investment_id`);

--
-- Indexes for table `level_income_commission_rates`
--
ALTER TABLE `level_income_commission_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `li_commission_rates_version_level_uq` (`level_income_rule_version_id`,`level`);

--
-- Indexes for table `level_income_rule_audits`
--
ALTER TABLE `level_income_rule_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `li_rule_audits_version_fk` (`level_income_rule_version_id`),
  ADD KEY `li_rule_audits_actor_fk` (`actor_id`),
  ADD KEY `li_rule_audits_action_created_idx` (`action`,`created_at`);

--
-- Indexes for table `level_income_rule_versions`
--
ALTER TABLE `level_income_rule_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `level_income_rule_versions_version_code_unique` (`version_code`),
  ADD KEY `li_rule_versions_status_auth_idx` (`status`,`posting_authorized`),
  ADD KEY `li_rule_versions_created_by_fk` (`created_by`),
  ADD KEY `li_rule_versions_published_by_fk` (`published_by`),
  ADD KEY `level_income_rule_versions_grandfather_captured_by_foreign` (`grandfather_captured_by`);

--
-- Indexes for table `level_income_unlock_bands`
--
ALTER TABLE `level_income_unlock_bands`
  ADD PRIMARY KEY (`id`),
  ADD KEY `li_unlock_bands_version_sort_idx` (`level_income_rule_version_id`,`sort_order`);

--
-- Indexes for table `level_income_unlock_grandfather_snapshots`
--
ALTER TABLE `level_income_unlock_grandfather_snapshots`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `members`
--
ALTER TABLE `members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `members_member_id_unique` (`member_id`),
  ADD KEY `members_sponsor_id_index` (`sponsor_id`),
  ADD KEY `members_rank_id_index` (`rank_id`),
  ADD KEY `members_status_created_at_index` (`status`,`created_at`),
  ADD KEY `members_created_at_index` (`created_at`),
  ADD KEY `members_normalized_email_index` (`normalized_email`);

--
-- Indexes for table `member_email_account_locks`
--
ALTER TABLE `member_email_account_locks`
  ADD PRIMARY KEY (`normalized_email`);

--
-- Indexes for table `member_password_otps`
--
ALTER TABLE `member_password_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `member_password_otps_member_id_created_at_index` (`member_id`,`created_at`),
  ADD KEY `member_password_otps_member_id_index` (`member_id`);

--
-- Indexes for table `member_password_setups`
--
ALTER TABLE `member_password_setups`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_password_setups_token_hash_unique` (`token_hash`),
  ADD KEY `member_password_setups_member_id_index` (`member_id`);

--
-- Indexes for table `member_payment_blocks`
--
ALTER TABLE `member_payment_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mpc_blocks_member_type_start_idx` (`member_id`,`income_type`,`start_date`),
  ADD KEY `mpc_blocks_member_type_end_idx` (`member_id`,`income_type`,`end_date`);

--
-- Indexes for table `member_payment_controls`
--
ALTER TABLE `member_payment_controls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_payment_controls_member_id_unique` (`member_id`),
  ADD KEY `mpc_controls_updated_by_fk` (`updated_by`),
  ADD KEY `mpc_controls_status_idx` (`status`);

--
-- Indexes for table `member_payment_control_events`
--
ALTER TABLE `member_payment_control_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mpc_events_actor_fk` (`actor_id`),
  ADD KEY `mpc_events_member_created_idx` (`member_id`,`created_at`);

--
-- Indexes for table `member_preview_sessions`
--
ALTER TABLE `member_preview_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_preview_sessions_uuid_unique` (`uuid`),
  ADD KEY `member_preview_sessions_admin_user_id_revoked_at_index` (`admin_user_id`,`revoked_at`),
  ADD KEY `member_preview_sessions_member_id_index` (`member_id`);

--
-- Indexes for table `member_security_pin_resets`
--
ALTER TABLE `member_security_pin_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `msr_member_open_idx` (`member_id`,`consumed_at`,`invalidated_at`),
  ADD KEY `member_security_pin_resets_member_id_index` (`member_id`);

--
-- Indexes for table `member_transfers`
--
ALTER TABLE `member_transfers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `member_transfers_transfer_reference_unique` (`transfer_reference`),
  ADD UNIQUE KEY `member_transfers_idempotency_key_unique` (`idempotency_key`),
  ADD KEY `mtr_sender_day_idx` (`sender_member_id`,`business_date`),
  ADD KEY `mtr_receiver_idx` (`receiver_member_id`),
  ADD KEY `mtr_status_day_idx` (`status`,`business_date`);

--
-- Indexes for table `member_transfer_settings`
--
ALTER TABLE `member_transfer_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `packages_code_unique` (`code`),
  ADD KEY `packages_package_type_index` (`package_type`);

--
-- Indexes for table `package_income_activations`
--
ALTER TABLE `package_income_activations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `package_income_activations_package_code_unique` (`package_code`);

--
-- Indexes for table `package_income_runs`
--
ALTER TABLE `package_income_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `package_income_runs_status_id_index` (`status`,`id`),
  ADD KEY `package_income_runs_business_date_index` (`business_date`);

--
-- Indexes for table `package_income_terms`
--
ALTER TABLE `package_income_terms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `package_income_terms_package_purchase_id_unique` (`package_purchase_id`),
  ADD UNIQUE KEY `package_income_terms_reference_unique` (`reference`),
  ADD KEY `package_income_terms_package_plan_version_id_foreign` (`package_plan_version_id`);

--
-- Indexes for table `package_level_accruals`
--
ALTER TABLE `package_level_accruals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `package_level_purchase_member_date_unique` (`package_purchase_id`,`member_id`,`business_date`),
  ADD UNIQUE KEY `package_level_accruals_reference_unique` (`reference`),
  ADD UNIQUE KEY `package_level_accruals_idempotency_key_unique` (`idempotency_key`),
  ADD KEY `package_level_accruals_member_id_business_date_index` (`member_id`,`business_date`);

--
-- Indexes for table `package_plan_audits`
--
ALTER TABLE `package_plan_audits`
  ADD PRIMARY KEY (`id`),
  ADD KEY `package_plan_audits_package_plan_version_id_foreign` (`package_plan_version_id`),
  ADD KEY `package_plan_audits_user_id_foreign` (`user_id`),
  ADD KEY `package_plan_audits_package_id_created_at_index` (`package_id`,`created_at`);

--
-- Indexes for table `package_plan_versions`
--
ALTER TABLE `package_plan_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `package_plan_versions_package_id_version_number_unique` (`package_id`,`version_number`),
  ADD KEY `package_plan_versions_created_by_foreign` (`created_by`),
  ADD KEY `package_plan_versions_published_by_foreign` (`published_by`),
  ADD KEY `package_plan_versions_package_id_status_index` (`package_id`,`status`);

--
-- Indexes for table `package_purchases`
--
ALTER TABLE `package_purchases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `package_purchases_reference_unique` (`reference`),
  ADD UNIQUE KEY `package_purchases_idempotency_key_unique` (`idempotency_key`),
  ADD UNIQUE KEY `package_purchases_network_payment_reference_unique` (`network`,`payment_reference`),
  ADD KEY `package_purchases_package_id_foreign` (`package_id`),
  ADD KEY `package_purchases_reviewed_by_foreign` (`reviewed_by`),
  ADD KEY `package_purchases_member_id_status_index` (`member_id`,`status`),
  ADD KEY `package_purchases_status_index` (`status`),
  ADD KEY `package_purchases_submitted_at_index` (`submitted_at`),
  ADD KEY `package_purchases_reward_business_index` (`status`,`denomination`,`reviewed_at`),
  ADD KEY `package_purchases_funding_source_status_index` (`funding_source`,`status`),
  ADD KEY `package_purchases_package_plan_version_id_foreign` (`package_plan_version_id`);

--
-- Indexes for table `package_purchase_reservations`
--
ALTER TABLE `package_purchase_reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `package_purchase_reservations_package_purchase_id_unique` (`package_purchase_id`),
  ADD UNIQUE KEY `package_purchase_reservations_reservation_reference_unique` (`reservation_reference`),
  ADD UNIQUE KEY `package_purchase_reservations_idempotency_key_unique` (`idempotency_key`),
  ADD KEY `ppr_member_wallet_status_idx` (`member_id`,`wallet`,`status`),
  ADD KEY `ppr_status_reserved_idx` (`status`,`reserved_at`);

--
-- Indexes for table `package_roi_accruals`
--
ALTER TABLE `package_roi_accruals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `package_roi_purchase_date_unique` (`package_purchase_id`,`business_date`),
  ADD UNIQUE KEY `package_roi_accruals_reference_unique` (`reference`),
  ADD UNIQUE KEY `package_roi_accruals_idempotency_key_unique` (`idempotency_key`),
  ADD KEY `package_roi_accruals_member_id_business_date_index` (`member_id`,`business_date`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`email`);

--
-- Indexes for table `payment_decisions`
--
ALTER TABLE `payment_decisions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `payment_decisions_user_id_foreign` (`user_id`),
  ADD KEY `payment_decisions_package_purchase_id_action_index` (`package_purchase_id`,`action`);

--
-- Indexes for table `payment_settings`
--
ALTER TABLE `payment_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `production_initializations`
--
ALTER TABLE `production_initializations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `production_initializations_marker_unique` (`marker`);

--
-- Indexes for table `ranks`
--
ALTER TABLE `ranks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ranks_name_unique` (`name`),
  ADD UNIQUE KEY `ranks_sort_order_unique` (`sort_order`),
  ADD KEY `ranks_required_full_team_business_index` (`required_full_team_business`),
  ADD KEY `ranks_is_active_sort_order_index` (`is_active`,`sort_order`);

--
-- Indexes for table `rank_achievements`
--
ALTER TABLE `rank_achievements`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rank_achievement_member_rank_unique` (`member_id`,`rank_id`),
  ADD KEY `rank_achievements_member_id_achieved_at_index` (`member_id`,`achieved_at`),
  ADD KEY `rank_achievements_rank_id_index` (`rank_id`),
  ADD KEY `rank_achievements_achieved_at_index` (`achieved_at`),
  ADD KEY `rank_achievements_rank_date_index` (`rank_id`,`achieved_at`);

--
-- Indexes for table `report_summary_generations`
--
ALTER TABLE `report_summary_generations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `report_summary_state`
--
ALTER TABLE `report_summary_state`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_summary_state_active_generation_id_foreign` (`active_generation_id`);

--
-- Indexes for table `reward_assignments`
--
ALTER TABLE `reward_assignments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reward_assignments_member_id_campaign_id_unique` (`member_id`,`campaign_id`),
  ADD KEY `reward_assignments_qualification_id_foreign` (`qualification_id`),
  ADD KEY `reward_assignments_campaign_id_foreign` (`campaign_id`),
  ADD KEY `reward_assignments_superseded_by_campaign_id_foreign` (`superseded_by_campaign_id`),
  ADD KEY `reward_assignments_conflict_campaign_id_foreign` (`conflict_campaign_id`),
  ADD KEY `reward_assignments_member_id_status_index` (`member_id`,`status`);

--
-- Indexes for table `reward_campaigns`
--
ALTER TABLE `reward_campaigns`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reward_campaigns_created_by_foreign` (`created_by`),
  ADD KEY `reward_campaigns_status_starts_on_index` (`status`,`starts_on`),
  ADD KEY `reward_campaigns_ends_on_index` (`ends_on`);

--
-- Indexes for table `reward_deliveries`
--
ALTER TABLE `reward_deliveries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reward_deliveries_assignment_id_unique` (`assignment_id`),
  ADD KEY `reward_deliveries_status_index` (`status`);

--
-- Indexes for table `reward_delivery_events`
--
ALTER TABLE `reward_delivery_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reward_delivery_events_user_id_foreign` (`user_id`),
  ADD KEY `reward_delivery_events_delivery_id_created_at_index` (`delivery_id`,`created_at`);

--
-- Indexes for table `reward_evaluation_runs`
--
ALTER TABLE `reward_evaluation_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reward_evaluation_runs_business_date_status_index` (`business_date`,`status`);

--
-- Indexes for table `reward_qualifications`
--
ALTER TABLE `reward_qualifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reward_qualifications_campaign_id_member_id_unique` (`campaign_id`,`member_id`),
  ADD KEY `reward_qualifications_status_qualified_at_index` (`status`,`qualified_at`),
  ADD KEY `reward_qualifications_member_id_index` (`member_id`);

--
-- Indexes for table `roi_report_global_daily_summaries`
--
ALTER TABLE `roi_report_global_daily_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roi_global_daily_generation_date_unique` (`generation_id`,`date_key`);

--
-- Indexes for table `roi_report_member_daily_summaries`
--
ALTER TABLE `roi_report_member_daily_summaries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roi_member_daily_generation_member_date_unique` (`generation_id`,`member_id`,`date_key`);

--
-- Indexes for table `roi_transactions`
--
ALTER TABLE `roi_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `roi_investment_date_unique` (`investment_id`,`roi_date`),
  ADD UNIQUE KEY `roi_transactions_reference_unique` (`reference`),
  ADD KEY `roi_transactions_member_id_created_at_index` (`member_id`,`created_at`),
  ADD KEY `roi_transactions_member_id_status_withdrawable_on_index` (`member_id`,`status`,`withdrawable_on`),
  ADD KEY `roi_created_at_id_index` (`created_at`,`id`),
  ADD KEY `roi_member_created_at_id_index` (`member_id`,`created_at`,`id`),
  ADD KEY `roi_simulation_date_investment_index` (`roi_date`,`investment_id`),
  ADD KEY `roi_transactions_roi_date_index` (`roi_date`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sessions_user_id_index` (`user_id`),
  ADD KEY `sessions_last_activity_index` (`last_activity`);

--
-- Indexes for table `simulation_checkpoints`
--
ALTER TABLE `simulation_checkpoints`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `simulation_checkpoints_run_key_unique` (`run_key`),
  ADD KEY `simulation_checkpoints_status_last_completed_date_index` (`status`,`last_completed_date`);

--
-- Indexes for table `simulation_daily_investment_income`
--
ALTER TABLE `simulation_daily_investment_income`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sim_daily_investment_date_unique` (`investment_id`,`business_date`),
  ADD KEY `sim_daily_investment_date_index` (`business_date`,`investment_id`);

--
-- Indexes for table `simulation_daily_member_wallets`
--
ALTER TABLE `simulation_daily_member_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sim_daily_member_date_unique` (`member_id`,`business_date`),
  ADD KEY `sim_daily_member_date_index` (`business_date`,`member_id`);

--
-- Indexes for table `simulation_investment_income_totals`
--
ALTER TABLE `simulation_investment_income_totals`
  ADD PRIMARY KEY (`investment_id`);

--
-- Indexes for table `simulation_member_wallet_totals`
--
ALTER TABLE `simulation_member_wallet_totals`
  ADD PRIMARY KEY (`member_id`);

--
-- Indexes for table `stress_test_datasets`
--
ALTER TABLE `stress_test_datasets`
  ADD PRIMARY KEY (`dataset_id`);

--
-- Indexes for table `support_attachments`
--
ALTER TABLE `support_attachments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sta_path_uidx` (`path`),
  ADD KEY `support_attachments_support_message_id_foreign` (`support_message_id`);

--
-- Indexes for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `support_messages_idempotency_key_unique` (`idempotency_key`),
  ADD KEY `support_messages_sender_user_id_foreign` (`sender_user_id`),
  ADD KEY `support_messages_sender_member_id_foreign` (`sender_member_id`),
  ADD KEY `stm_ticket_id_idx` (`support_ticket_id`,`id`);

--
-- Indexes for table `support_notifications`
--
ALTER TABLE `support_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `support_notifications_user_id_foreign` (`user_id`),
  ADD KEY `support_notifications_support_ticket_id_foreign` (`support_ticket_id`),
  ADD KEY `support_notifications_member_id_foreign` (`member_id`),
  ADD KEY `stn_member_read_idx` (`audience`,`member_id`,`read_at`),
  ADD KEY `stn_admin_read_idx` (`audience`,`user_id`,`read_at`),
  ADD KEY `stn_tour_request_idx` (`tour_interest_request_id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `support_tickets_reference_unique` (`reference`),
  ADD UNIQUE KEY `support_tickets_idempotency_key_unique` (`idempotency_key`),
  ADD KEY `stk_member_status_idx` (`member_id`,`status`),
  ADD KEY `stk_status_created_idx` (`status`,`created_at`),
  ADD KEY `stk_category_priority_idx` (`category`,`priority`);

--
-- Indexes for table `tour_interest_requests`
--
ALTER TABLE `tour_interest_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tour_req_member_active_uq` (`member_id`,`active_marker`),
  ADD KEY `tour_req_status_submitted_idx` (`status`,`submitted_at`),
  ADD KEY `tour_req_member_idx` (`member_id`),
  ADD KEY `tour_req_assigned_admin_fk` (`assigned_admin_id`);

--
-- Indexes for table `tour_interest_request_events`
--
ALTER TABLE `tour_interest_request_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tour_req_events_request_idx` (`tour_interest_request_id`,`id`),
  ADD KEY `tour_req_events_admin_fk` (`actor_admin_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `users_email_unique` (`email`),
  ADD UNIQUE KEY `users_login_id_unique` (`login_id`),
  ADD KEY `users_created_by_foreign` (`created_by`);

--
-- Indexes for table `wallet_ledger_entries`
--
ALTER TABLE `wallet_ledger_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wallet_ledger_entries_reference_unique` (`reference`),
  ADD UNIQUE KEY `wallet_ledger_entries_idempotency_key_unique` (`idempotency_key`),
  ADD KEY `wallet_ledger_entries_package_purchase_id_foreign` (`package_purchase_id`),
  ADD KEY `wallet_ledger_entries_withdrawal_request_id_foreign` (`withdrawal_request_id`),
  ADD KEY `wallet_ledger_entries_member_id_wallet_created_at_index` (`member_id`,`wallet`,`created_at`),
  ADD KEY `wallet_ledger_entries_counter_entry_id_foreign` (`counter_entry_id`);

--
-- Indexes for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `withdrawal_requests_reference_unique` (`reference`),
  ADD UNIQUE KEY `withdrawal_requests_idempotency_key_unique` (`idempotency_key`),
  ADD UNIQUE KEY `withdrawal_payout_network_txid_unique` (`payout_network`,`payout_txid`),
  ADD KEY `withdrawal_requests_member_id_status_index` (`member_id`,`status`),
  ADD KEY `withdrawal_requests_status_index` (`status`);

--
-- Indexes for table `withdrawal_settings`
--
ALTER TABLE `withdrawal_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `withdrawal_status_events`
--
ALTER TABLE `withdrawal_status_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `withdrawal_status_events_withdrawal_request_id_created_at_index` (`withdrawal_request_id`,`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activation_wallet_transactions`
--
ALTER TABLE `activation_wallet_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `admin_user_permissions`
--
ALTER TABLE `admin_user_permissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `associate_commission_packages`
--
ALTER TABLE `associate_commission_packages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `associate_commission_ranks`
--
ALTER TABLE `associate_commission_ranks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=81;

--
-- AUTO_INCREMENT for table `associate_commission_versions`
--
ALTER TABLE `associate_commission_versions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `commission_plan_levels`
--
ALTER TABLE `commission_plan_levels`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_settings`
--
ALTER TABLE `company_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `failed_jobs`
--
ALTER TABLE `failed_jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `financial_controls`
--
ALTER TABLE `financial_controls`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `financial_historical_recovery_runs`
--
ALTER TABLE `financial_historical_recovery_runs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `financial_tour_recovery_runs`
--
ALTER TABLE `financial_tour_recovery_runs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `impersonation_logs`
--
ALTER TABLE `impersonation_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `investments`
--
ALTER TABLE `investments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `investment_withdrawals`
--
ALTER TABLE `investment_withdrawals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `jobs`
--
ALTER TABLE `jobs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `level_commissions`
--
ALTER TABLE `level_commissions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `level_commission_report_global_daily_summaries`
--
ALTER TABLE `level_commission_report_global_daily_summaries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `level_commission_report_level_daily_summaries`
--
ALTER TABLE `level_commission_report_level_daily_summaries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `level_commission_report_member_level_daily_summaries`
--
ALTER TABLE `level_commission_report_member_level_daily_summaries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `level_commission_transactions`
--
ALTER TABLE `level_commission_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `level_income_commission_rates`
--
ALTER TABLE `level_income_commission_rates`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `level_income_rule_audits`
--
ALTER TABLE `level_income_rule_audits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `level_income_rule_versions`
--
ALTER TABLE `level_income_rule_versions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `level_income_unlock_bands`
--
ALTER TABLE `level_income_unlock_bands`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `level_income_unlock_grandfather_snapshots`
--
ALTER TABLE `level_income_unlock_grandfather_snapshots`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `members`
--
ALTER TABLE `members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `member_password_otps`
--
ALTER TABLE `member_password_otps`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `member_password_setups`
--
ALTER TABLE `member_password_setups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `member_payment_blocks`
--
ALTER TABLE `member_payment_blocks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `member_payment_controls`
--
ALTER TABLE `member_payment_controls`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `member_payment_control_events`
--
ALTER TABLE `member_payment_control_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `member_preview_sessions`
--
ALTER TABLE `member_preview_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `member_security_pin_resets`
--
ALTER TABLE `member_security_pin_resets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `member_transfers`
--
ALTER TABLE `member_transfers`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `member_transfer_settings`
--
ALTER TABLE `member_transfer_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `package_income_activations`
--
ALTER TABLE `package_income_activations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `package_income_runs`
--
ALTER TABLE `package_income_runs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `package_income_terms`
--
ALTER TABLE `package_income_terms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `package_level_accruals`
--
ALTER TABLE `package_level_accruals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `package_plan_audits`
--
ALTER TABLE `package_plan_audits`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `package_plan_versions`
--
ALTER TABLE `package_plan_versions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `package_purchases`
--
ALTER TABLE `package_purchases`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `package_purchase_reservations`
--
ALTER TABLE `package_purchase_reservations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `package_roi_accruals`
--
ALTER TABLE `package_roi_accruals`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_decisions`
--
ALTER TABLE `payment_decisions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `payment_settings`
--
ALTER TABLE `payment_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `production_initializations`
--
ALTER TABLE `production_initializations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ranks`
--
ALTER TABLE `ranks`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rank_achievements`
--
ALTER TABLE `rank_achievements`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `report_summary_generations`
--
ALTER TABLE `report_summary_generations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reward_assignments`
--
ALTER TABLE `reward_assignments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reward_campaigns`
--
ALTER TABLE `reward_campaigns`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reward_deliveries`
--
ALTER TABLE `reward_deliveries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reward_delivery_events`
--
ALTER TABLE `reward_delivery_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reward_evaluation_runs`
--
ALTER TABLE `reward_evaluation_runs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reward_qualifications`
--
ALTER TABLE `reward_qualifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `roi_report_global_daily_summaries`
--
ALTER TABLE `roi_report_global_daily_summaries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roi_report_member_daily_summaries`
--
ALTER TABLE `roi_report_member_daily_summaries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roi_transactions`
--
ALTER TABLE `roi_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `simulation_checkpoints`
--
ALTER TABLE `simulation_checkpoints`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `simulation_daily_investment_income`
--
ALTER TABLE `simulation_daily_investment_income`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `simulation_daily_member_wallets`
--
ALTER TABLE `simulation_daily_member_wallets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_attachments`
--
ALTER TABLE `support_attachments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_messages`
--
ALTER TABLE `support_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_notifications`
--
ALTER TABLE `support_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tour_interest_requests`
--
ALTER TABLE `tour_interest_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tour_interest_request_events`
--
ALTER TABLE `tour_interest_request_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `wallet_ledger_entries`
--
ALTER TABLE `wallet_ledger_entries`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `withdrawal_settings`
--
ALTER TABLE `withdrawal_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `withdrawal_status_events`
--
ALTER TABLE `withdrawal_status_events`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin_activity_logs`
--
ALTER TABLE `admin_activity_logs`
  ADD CONSTRAINT `admin_activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `admin_user_permissions`
--
ALTER TABLE `admin_user_permissions`
  ADD CONSTRAINT `admin_user_permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `associate_commission_packages`
--
ALTER TABLE `associate_commission_packages`
  ADD CONSTRAINT `assoc_comm_pkg_version_fk` FOREIGN KEY (`associate_commission_version_id`) REFERENCES `associate_commission_versions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `associate_commission_ranks`
--
ALTER TABLE `associate_commission_ranks`
  ADD CONSTRAINT `assoc_comm_rank_pkg_fk` FOREIGN KEY (`associate_commission_package_id`) REFERENCES `associate_commission_packages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `impersonation_logs`
--
ALTER TABLE `impersonation_logs`
  ADD CONSTRAINT `impersonation_logs_admin_id_foreign` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `invoices_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`),
  ADD CONSTRAINT `invoices_package_purchase_id_foreign` FOREIGN KEY (`package_purchase_id`) REFERENCES `package_purchases` (`id`);

--
-- Constraints for table `level_commission_report_global_daily_summaries`
--
ALTER TABLE `level_commission_report_global_daily_summaries`
  ADD CONSTRAINT `lc_global_daily_gen_fk` FOREIGN KEY (`generation_id`) REFERENCES `report_summary_generations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `level_commission_report_level_daily_summaries`
--
ALTER TABLE `level_commission_report_level_daily_summaries`
  ADD CONSTRAINT `lc_level_daily_gen_fk` FOREIGN KEY (`generation_id`) REFERENCES `report_summary_generations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `level_commission_report_member_level_daily_summaries`
--
ALTER TABLE `level_commission_report_member_level_daily_summaries`
  ADD CONSTRAINT `lc_member_level_daily_gen_fk` FOREIGN KEY (`generation_id`) REFERENCES `report_summary_generations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `level_income_commission_rates`
--
ALTER TABLE `level_income_commission_rates`
  ADD CONSTRAINT `li_commission_rates_version_fk` FOREIGN KEY (`level_income_rule_version_id`) REFERENCES `level_income_rule_versions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `level_income_rule_audits`
--
ALTER TABLE `level_income_rule_audits`
  ADD CONSTRAINT `li_rule_audits_actor_fk` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `li_rule_audits_version_fk` FOREIGN KEY (`level_income_rule_version_id`) REFERENCES `level_income_rule_versions` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `level_income_rule_versions`
--
ALTER TABLE `level_income_rule_versions`
  ADD CONSTRAINT `level_income_rule_versions_grandfather_captured_by_foreign` FOREIGN KEY (`grandfather_captured_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `li_rule_versions_created_by_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `li_rule_versions_published_by_fk` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `level_income_unlock_bands`
--
ALTER TABLE `level_income_unlock_bands`
  ADD CONSTRAINT `li_unlock_bands_version_fk` FOREIGN KEY (`level_income_rule_version_id`) REFERENCES `level_income_rule_versions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `members`
--
ALTER TABLE `members`
  ADD CONSTRAINT `members_rank_id_foreign` FOREIGN KEY (`rank_id`) REFERENCES `ranks` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `member_payment_controls`
--
ALTER TABLE `member_payment_controls`
  ADD CONSTRAINT `mpc_controls_updated_by_fk` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `member_payment_control_events`
--
ALTER TABLE `member_payment_control_events`
  ADD CONSTRAINT `mpc_events_actor_fk` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `member_preview_sessions`
--
ALTER TABLE `member_preview_sessions`
  ADD CONSTRAINT `member_preview_sessions_admin_user_id_foreign` FOREIGN KEY (`admin_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `member_transfers`
--
ALTER TABLE `member_transfers`
  ADD CONSTRAINT `member_transfers_receiver_member_id_foreign` FOREIGN KEY (`receiver_member_id`) REFERENCES `members` (`member_id`),
  ADD CONSTRAINT `member_transfers_sender_member_id_foreign` FOREIGN KEY (`sender_member_id`) REFERENCES `members` (`member_id`);

--
-- Constraints for table `package_income_terms`
--
ALTER TABLE `package_income_terms`
  ADD CONSTRAINT `package_income_terms_package_plan_version_id_foreign` FOREIGN KEY (`package_plan_version_id`) REFERENCES `package_plan_versions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `package_income_terms_package_purchase_id_foreign` FOREIGN KEY (`package_purchase_id`) REFERENCES `package_purchases` (`id`);

--
-- Constraints for table `package_level_accruals`
--
ALTER TABLE `package_level_accruals`
  ADD CONSTRAINT `package_level_accruals_package_purchase_id_foreign` FOREIGN KEY (`package_purchase_id`) REFERENCES `package_purchases` (`id`);

--
-- Constraints for table `package_plan_audits`
--
ALTER TABLE `package_plan_audits`
  ADD CONSTRAINT `package_plan_audits_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `package_plan_audits_package_plan_version_id_foreign` FOREIGN KEY (`package_plan_version_id`) REFERENCES `package_plan_versions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `package_plan_audits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `package_plan_versions`
--
ALTER TABLE `package_plan_versions`
  ADD CONSTRAINT `package_plan_versions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `package_plan_versions_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `package_plan_versions_published_by_foreign` FOREIGN KEY (`published_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `package_purchases`
--
ALTER TABLE `package_purchases`
  ADD CONSTRAINT `package_purchases_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`),
  ADD CONSTRAINT `package_purchases_package_id_foreign` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`),
  ADD CONSTRAINT `package_purchases_package_plan_version_id_foreign` FOREIGN KEY (`package_plan_version_id`) REFERENCES `package_plan_versions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `package_purchases_reviewed_by_foreign` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `package_purchase_reservations`
--
ALTER TABLE `package_purchase_reservations`
  ADD CONSTRAINT `package_purchase_reservations_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`),
  ADD CONSTRAINT `package_purchase_reservations_package_purchase_id_foreign` FOREIGN KEY (`package_purchase_id`) REFERENCES `package_purchases` (`id`);

--
-- Constraints for table `package_roi_accruals`
--
ALTER TABLE `package_roi_accruals`
  ADD CONSTRAINT `package_roi_accruals_package_purchase_id_foreign` FOREIGN KEY (`package_purchase_id`) REFERENCES `package_purchases` (`id`);

--
-- Constraints for table `payment_decisions`
--
ALTER TABLE `payment_decisions`
  ADD CONSTRAINT `payment_decisions_package_purchase_id_foreign` FOREIGN KEY (`package_purchase_id`) REFERENCES `package_purchases` (`id`),
  ADD CONSTRAINT `payment_decisions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `rank_achievements`
--
ALTER TABLE `rank_achievements`
  ADD CONSTRAINT `rank_achievements_rank_id_foreign` FOREIGN KEY (`rank_id`) REFERENCES `ranks` (`id`);

--
-- Constraints for table `report_summary_state`
--
ALTER TABLE `report_summary_state`
  ADD CONSTRAINT `report_summary_state_active_generation_id_foreign` FOREIGN KEY (`active_generation_id`) REFERENCES `report_summary_generations` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reward_assignments`
--
ALTER TABLE `reward_assignments`
  ADD CONSTRAINT `reward_assignments_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `reward_campaigns` (`id`),
  ADD CONSTRAINT `reward_assignments_conflict_campaign_id_foreign` FOREIGN KEY (`conflict_campaign_id`) REFERENCES `reward_campaigns` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reward_assignments_qualification_id_foreign` FOREIGN KEY (`qualification_id`) REFERENCES `reward_qualifications` (`id`),
  ADD CONSTRAINT `reward_assignments_superseded_by_campaign_id_foreign` FOREIGN KEY (`superseded_by_campaign_id`) REFERENCES `reward_campaigns` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reward_campaigns`
--
ALTER TABLE `reward_campaigns`
  ADD CONSTRAINT `reward_campaigns_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reward_deliveries`
--
ALTER TABLE `reward_deliveries`
  ADD CONSTRAINT `reward_deliveries_assignment_id_foreign` FOREIGN KEY (`assignment_id`) REFERENCES `reward_assignments` (`id`);

--
-- Constraints for table `reward_delivery_events`
--
ALTER TABLE `reward_delivery_events`
  ADD CONSTRAINT `reward_delivery_events_delivery_id_foreign` FOREIGN KEY (`delivery_id`) REFERENCES `reward_deliveries` (`id`),
  ADD CONSTRAINT `reward_delivery_events_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reward_qualifications`
--
ALTER TABLE `reward_qualifications`
  ADD CONSTRAINT `reward_qualifications_campaign_id_foreign` FOREIGN KEY (`campaign_id`) REFERENCES `reward_campaigns` (`id`);

--
-- Constraints for table `roi_report_global_daily_summaries`
--
ALTER TABLE `roi_report_global_daily_summaries`
  ADD CONSTRAINT `roi_global_daily_gen_fk` FOREIGN KEY (`generation_id`) REFERENCES `report_summary_generations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `roi_report_member_daily_summaries`
--
ALTER TABLE `roi_report_member_daily_summaries`
  ADD CONSTRAINT `roi_member_daily_gen_fk` FOREIGN KEY (`generation_id`) REFERENCES `report_summary_generations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_attachments`
--
ALTER TABLE `support_attachments`
  ADD CONSTRAINT `support_attachments_support_message_id_foreign` FOREIGN KEY (`support_message_id`) REFERENCES `support_messages` (`id`);

--
-- Constraints for table `support_messages`
--
ALTER TABLE `support_messages`
  ADD CONSTRAINT `support_messages_sender_member_id_foreign` FOREIGN KEY (`sender_member_id`) REFERENCES `members` (`member_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_messages_sender_user_id_foreign` FOREIGN KEY (`sender_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_messages_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`);

--
-- Constraints for table `support_notifications`
--
ALTER TABLE `support_notifications`
  ADD CONSTRAINT `stn_tour_request_fk` FOREIGN KEY (`tour_interest_request_id`) REFERENCES `tour_interest_requests` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `support_notifications_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_notifications_support_ticket_id_foreign` FOREIGN KEY (`support_ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `support_notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD CONSTRAINT `support_tickets_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`);

--
-- Constraints for table `tour_interest_requests`
--
ALTER TABLE `tour_interest_requests`
  ADD CONSTRAINT `tour_req_assigned_admin_fk` FOREIGN KEY (`assigned_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `tour_interest_request_events`
--
ALTER TABLE `tour_interest_request_events`
  ADD CONSTRAINT `tour_interest_request_events_tour_interest_request_id_foreign` FOREIGN KEY (`tour_interest_request_id`) REFERENCES `tour_interest_requests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tour_req_events_admin_fk` FOREIGN KEY (`actor_admin_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `wallet_ledger_entries`
--
ALTER TABLE `wallet_ledger_entries`
  ADD CONSTRAINT `wallet_ledger_entries_counter_entry_id_foreign` FOREIGN KEY (`counter_entry_id`) REFERENCES `wallet_ledger_entries` (`id`),
  ADD CONSTRAINT `wallet_ledger_entries_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`),
  ADD CONSTRAINT `wallet_ledger_entries_package_purchase_id_foreign` FOREIGN KEY (`package_purchase_id`) REFERENCES `package_purchases` (`id`),
  ADD CONSTRAINT `wallet_ledger_entries_withdrawal_request_id_foreign` FOREIGN KEY (`withdrawal_request_id`) REFERENCES `withdrawal_requests` (`id`);

--
-- Constraints for table `withdrawal_requests`
--
ALTER TABLE `withdrawal_requests`
  ADD CONSTRAINT `withdrawal_requests_member_id_foreign` FOREIGN KEY (`member_id`) REFERENCES `members` (`member_id`);

--
-- Constraints for table `withdrawal_status_events`
--
ALTER TABLE `withdrawal_status_events`
  ADD CONSTRAINT `withdrawal_status_events_withdrawal_request_id_foreign` FOREIGN KEY (`withdrawal_request_id`) REFERENCES `withdrawal_requests` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
