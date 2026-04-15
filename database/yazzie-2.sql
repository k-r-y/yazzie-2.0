-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 14, 2026 at 02:28 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `yazzie`
--

-- --------------------------------------------------------

--
-- Table structure for table `archived_bookings`
--

CREATE TABLE `archived_bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `original_id` int(10) UNSIGNED NOT NULL COMMENT 'Original booking ID before archive',
  `client_name` varchar(100) NOT NULL,
  `client_phone` varchar(20) DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `event_location` text DEFAULT NULL,
  `pax_count` int(10) UNSIGNED NOT NULL,
  `total_cost` decimal(10,2) NOT NULL,
  `amount_paid` decimal(10,2) NOT NULL,
  `payment_status` enum('unpaid','partial','paid') NOT NULL,
  `notes` text DEFAULT NULL,
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_by` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `archived_bookings`
--

INSERT INTO `archived_bookings` (`id`, `original_id`, `client_name`, `client_phone`, `event_date`, `event_time`, `event_location`, `pax_count`, `total_cost`, `amount_paid`, `payment_status`, `notes`, `archived_at`, `archived_by`) VALUES
(1, 3, 'Dela Torre Family', '09201234567', '2026-04-04', '10:00:00', 'GMA, Cavite', 30, 16500.00, 16500.00, 'paid', NULL, '2026-04-12 11:19:11', 1),
(2, 11, 'vfsvfsvfs', '09646465464', '2026-04-20', NULL, 'dfsvfsf', 300, 35000.00, 35000.00, 'paid', NULL, '2026-04-13 13:03:49', 1);

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL COMMENT 'Who performed the action',
  `action` varchar(60) NOT NULL COMMENT 'e.g. payment_recorded, booking_confirmed',
  `entity` varchar(30) NOT NULL COMMENT 'booking | payment | client | job_order',
  `entity_id` int(10) UNSIGNED NOT NULL,
  `old_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'State before the change' CHECK (json_valid(`old_value`)),
  `new_value` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'State after the change' CHECK (json_valid(`new_value`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `action`, `entity`, `entity_id`, `old_value`, `new_value`, `ip_address`, `created_at`) VALUES
(1, 1, 'payment_recorded', 'payment', 1, NULL, '{\"booking_id\":3,\"amount\":4555,\"method\":\"cash\"}', '::1', '2026-04-11 13:03:05'),
(2, 1, 'payment_recorded', 'payment', 0, NULL, '{\"booking_id\":\"4\",\"amount\":38441.669999999998253770172595977783203125,\"notes\":\"Downpayment\"}', '::1', '2026-04-11 15:18:15'),
(3, 1, 'booking_created', 'booking', 4, NULL, '{\"client_id\":1,\"event_date\":\"2026-04-23\",\"total_cost\":76883.330000000001746229827404022216796875,\"booking_status\":\"confirmed\"}', '::1', '2026-04-11 15:18:15'),
(4, 1, 'payment_recorded', 'payment', 3, NULL, '{\"booking_id\":4,\"amount\":38441.66000000000349245965480804443359375,\"method\":\"cash\"}', '::1', '2026-04-11 15:19:03'),
(5, 1, 'payment_recorded', 'payment', 0, NULL, '{\"booking_id\":\"5\",\"amount\":5000,\"notes\":\"Downpayment\"}', '::1', '2026-04-11 15:25:53'),
(6, 1, 'booking_created', 'booking', 5, NULL, '{\"client_id\":4,\"event_date\":\"2026-04-16\",\"total_cost\":10000,\"booking_status\":\"confirmed\"}', '::1', '2026-04-11 15:25:53'),
(7, 1, 'payment_recorded', 'payment', 0, NULL, '{\"booking_id\":\"6\",\"amount\":17500,\"notes\":\"Downpayment\"}', '::1', '2026-04-11 15:39:04'),
(8, 1, 'booking_created', 'booking', 6, NULL, '{\"client_id\":5,\"event_date\":\"2026-04-30\",\"total_cost\":35000,\"booking_status\":\"confirmed\"}', '::1', '2026-04-11 15:39:04'),
(9, 1, 'payment_recorded', 'payment', 0, NULL, '{\"booking_id\":\"7\",\"amount\":17500,\"notes\":\"Downpayment\"}', '::1', '2026-04-11 15:57:07'),
(10, 1, 'booking_created', 'booking', 7, NULL, '{\"client_id\":6,\"event_date\":\"2026-04-15\",\"total_cost\":30720,\"booking_status\":\"confirmed\"}', '::1', '2026-04-11 15:57:07'),
(11, 1, 'payment_recorded', 'payment', 7, NULL, '{\"booking_id\":3,\"amount\":11945,\"method\":\"cash\"}', '::1', '2026-04-12 11:19:03'),
(12, 1, 'payment_recorded', 'payment', 0, NULL, '{\"booking_id\":\"8\",\"amount\":14625,\"notes\":\"Downpayment\"}', '::1', '2026-04-12 11:27:46'),
(13, 1, 'booking_created', 'booking', 8, NULL, '{\"client_id\":6,\"event_date\":\"2026-04-25\",\"total_cost\":29250,\"booking_status\":\"confirmed\"}', '::1', '2026-04-12 11:27:46'),
(14, 1, 'payment_recorded', 'payment', 0, NULL, '{\"booking_id\":\"9\",\"amount\":17500,\"notes\":\"Downpayment\"}', '::1', '2026-04-12 12:37:44'),
(15, 1, 'booking_created', 'booking', 9, NULL, '{\"client_id\":4,\"event_date\":\"2026-04-24\",\"total_cost\":35000,\"booking_status\":\"confirmed\"}', '::1', '2026-04-12 12:37:44'),
(16, 1, 'booking_status_changed', 'booking', 9, '{\"booking_status\":\"confirmed\"}', '{\"booking_status\":\"cancelled\"}', '::1', '2026-04-13 00:21:54'),
(17, 1, 'payment_recorded', 'payment', 0, NULL, '{\"booking_id\":\"10\",\"amount\":17500,\"notes\":\"Downpayment\"}', '::1', '2026-04-13 02:43:35'),
(18, 1, 'booking_created', 'booking', 10, NULL, '{\"client_id\":13,\"event_date\":\"2026-04-16\",\"total_cost\":35000,\"booking_status\":\"confirmed\"}', '::1', '2026-04-13 02:43:35'),
(19, 1, 'payment_recorded', 'payment', 11, NULL, '{\"booking_id\":10,\"amount\":17500,\"method\":\"cash\"}', '::1', '2026-04-13 02:44:37'),
(20, 1, 'payment_recorded', 'payment', 0, NULL, '{\"booking_id\":\"11\",\"amount\":17500,\"notes\":\"Downpayment\"}', '::1', '2026-04-13 07:51:19'),
(21, 1, 'booking_created', 'booking', 11, NULL, '{\"client_id\":14,\"event_date\":\"2026-04-20\",\"total_cost\":35000,\"booking_status\":\"confirmed\"}', '::1', '2026-04-13 07:51:19'),
(22, 1, 'payment_recorded', 'payment', 13, NULL, '{\"booking_id\":11,\"amount\":17500,\"method\":\"cash\"}', '::1', '2026-04-13 07:53:04'),
(23, 1, 'booking_status_changed', 'booking', 11, '{\"booking_status\":\"confirmed\"}', '{\"booking_status\":\"completed\"}', '::1', '2026-04-13 07:59:59'),
(24, 1, 'payment_recorded', 'payment', 0, NULL, '{\"booking_id\":\"12\",\"amount\":10000,\"notes\":\"Downpayment\"}', '::1', '2026-04-13 23:57:21'),
(25, 1, 'booking_created', 'booking', 12, NULL, '{\"client_id\":15,\"event_date\":\"2026-04-20\",\"total_cost\":20000,\"booking_status\":\"confirmed\"}', '::1', '2026-04-13 23:57:21'),
(26, 1, 'payment_recorded', 'payment', 15, NULL, '{\"booking_id\":12,\"amount\":10000,\"method\":\"cash\"}', '::1', '2026-04-14 00:02:57'),
(27, 1, 'payment_recorded', 'payment', 0, NULL, '{\"booking_id\":\"13\",\"amount\":5000,\"notes\":\"Downpayment\"}', '::1', '2026-04-14 12:07:51'),
(28, 1, 'booking_created', 'booking', 13, NULL, '{\"client_id\":11,\"event_date\":\"2026-04-29\",\"total_cost\":10000,\"booking_status\":\"confirmed\"}', '::1', '2026-04-14 12:07:51'),
(29, 1, 'payment_recorded', 'payment', 17, NULL, '{\"booking_id\":8,\"amount\":14625,\"method\":\"cash\"}', '::1', '2026-04-14 12:08:35');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(10) UNSIGNED NOT NULL,
  `client_id` int(10) UNSIGNED NOT NULL,
  `package_id` int(10) UNSIGNED DEFAULT NULL,
  `event_date` date NOT NULL,
  `event_time` time DEFAULT NULL,
  `event_location` text DEFAULT NULL,
  `pax_count` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `base_pax` int(10) UNSIGNED DEFAULT NULL,
  `extra_pax` int(10) UNSIGNED DEFAULT 0,
  `base_price` decimal(10,2) DEFAULT NULL,
  `extra_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00,
  `payment_status` enum('unpaid','partial','paid') NOT NULL DEFAULT 'unpaid',
  `booking_status` enum('inquiry','pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'inquiry',
  `invoice_token` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `client_id`, `package_id`, `event_date`, `event_time`, `event_location`, `pax_count`, `base_pax`, `extra_pax`, `base_price`, `extra_cost`, `total_cost`, `amount_paid`, `payment_status`, `booking_status`, `invoice_token`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(8, 6, 4, '2026-04-25', NULL, 'Dasmariñas', 234, 200, 34, 25000.00, 4250.00, 29250.00, 29250.00, 'paid', 'confirmed', '62c75e60b4f20c9b1f88c0caf04622b9', 'helloWorld', 1, '2026-04-12 11:27:46', '2026-04-14 12:08:35'),
(9, 4, 6, '2026-04-24', NULL, 'Dasmariñas', 300, 300, 0, 35000.00, 0.00, 35000.00, 17500.00, 'partial', 'cancelled', '74157c985622621ef39fc01d6688d752', 'basta', 1, '2026-04-12 12:37:44', '2026-04-13 00:21:54'),
(10, 13, 6, '2026-04-16', NULL, 'Dasmariñas', 300, 300, 0, 35000.00, 0.00, 35000.00, 35000.00, 'paid', 'confirmed', 'c62e3ba89ded6caaa2fe0e010267fc21', NULL, 1, '2026-04-13 02:43:35', '2026-04-13 02:44:37'),
(12, 15, 3, '2026-04-20', NULL, 'Dasmariñas', 150, 150, 0, 20000.00, 0.00, 20000.00, 20000.00, 'paid', 'confirmed', '4ee2c458bb3ee05100ba3a9767916ed7', NULL, 1, '2026-04-13 23:57:21', '2026-04-14 00:02:57'),
(13, 11, 1, '2026-04-29', NULL, 'Dasmariñas', 50, 50, 0, 10000.00, 0.00, 10000.00, 5000.00, 'partial', 'confirmed', '444af182ceafe8d2a11ba4a9aefb0e1b', NULL, 1, '2026-04-14 12:07:51', '2026-04-14 12:07:51');

-- --------------------------------------------------------

--
-- Table structure for table `booking_dishes`
--

CREATE TABLE `booking_dishes` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `dish_id` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_dishes`
--

INSERT INTO `booking_dishes` (`id`, `booking_id`, `dish_id`) VALUES
(31, 8, 5),
(32, 8, 6),
(33, 8, 8),
(34, 8, 12),
(35, 8, 15),
(39, 8, 20),
(36, 8, 23),
(37, 8, 26),
(38, 8, 28),
(40, 8, 31),
(41, 9, 2),
(42, 9, 4),
(43, 9, 7),
(44, 9, 8),
(45, 9, 9),
(46, 9, 11),
(47, 9, 13),
(48, 9, 14),
(49, 9, 15),
(51, 9, 16),
(52, 9, 18),
(53, 9, 20),
(50, 9, 23),
(54, 10, 2),
(55, 10, 4),
(56, 10, 7),
(57, 10, 9),
(58, 10, 11),
(59, 10, 14),
(60, 10, 15),
(64, 10, 17),
(65, 10, 20),
(61, 10, 23),
(62, 10, 27),
(63, 10, 28),
(66, 10, 31),
(80, 12, 2),
(81, 12, 3),
(82, 12, 5),
(83, 12, 8),
(84, 12, 13),
(85, 12, 15),
(87, 12, 16),
(88, 12, 18),
(86, 12, 28),
(89, 13, 3),
(90, 13, 5),
(91, 13, 10),
(92, 13, 26),
(93, 13, 27),
(94, 13, 32);

-- --------------------------------------------------------

--
-- Table structure for table `booking_staff`
--

CREATE TABLE `booking_staff` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'Staff',
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `assigned_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `name`, `email`, `phone`, `address`, `created_at`) VALUES
(1, 'Forger Family', 'santos@gmail.com', '09171234567', 'Dasmariñas City, Cavite', '2026-04-11 11:52:14'),
(2, 'Reyes-Cruz Wedding', 'reyes@gmail.com', '09181234567', 'Imus, Cavite', '2026-04-11 11:52:14'),
(3, 'Lim Corporation', 'lim@company.com', '09191234567', 'Bacoor, Cavite', '2026-04-11 11:52:14'),
(4, 'Dela Torre Family', 'delatorre@gmail.com', '09201234567', 'GMA, Cavite', '2026-04-11 11:52:14'),
(5, 'KRY KUFAL', 'basta@email.com', '09998765732', 'Dasmariñas', '2026-04-11 15:07:39'),
(6, 'Capsule Corporation', 'events@acmecorp.com', '09180001111', '123 Business Blvd, Manila', '2026-04-11 15:11:32'),
(7, 'Forger Family', 'carlos.santos@gmail.com', '09180002222', '456 Residential St, Quezon City', '2026-04-11 15:11:32'),
(8, 'U.A. High School', 'admin@techinnovators.ph', '09180003333', '789 IT Park, Makati', '2026-04-11 15:11:32'),
(9, 'basta', '', '34222222222', '', '2026-04-12 12:58:48'),
(10, 'adsada', '', '33333333333', '', '2026-04-12 13:01:01'),
(11, 'asdasdasdas', 'adasdadasd', '2232', '', '2026-04-12 13:02:40'),
(12, 'sdasda', 'asdasda@fdsf.dfsd', '09973863767', '', '2026-04-12 13:12:38'),
(13, 'joven kufal', 'asdasdawdasdasda@dsfsd.vsdf', '09878263972', '', '2026-04-13 02:39:37'),
(14, 'vfsvfsvfs', 'vgdfbgfnhfnhfhnfnhgnhnhfnh@fqre.hgrh', '09646465464', '', '2026-04-13 07:48:09'),
(15, 'hello world', 'dsvfesfsgsdfdfsd@jhfhytd.jfhg', '09268623762', '', '2026-04-13 23:54:28');

-- --------------------------------------------------------

--
-- Table structure for table `dishes`
--

CREATE TABLE `dishes` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `category` enum('main','dessert') NOT NULL DEFAULT 'main',
  `base_pax` int(11) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `dishes`
--

INSERT INTO `dishes` (`id`, `name`, `category`, `base_pax`, `is_active`, `created_at`) VALUES
(1, 'Sanji\'s Fried Rice', 'main', 2, 1, '2026-04-11 05:58:33'),
(2, 'Goku\'s Meat Feast', 'main', 2, 1, '2026-04-11 05:58:33'),
(3, 'Pork Mechado', 'main', 2, 1, '2026-04-11 05:58:33'),
(4, 'Ichiraku Ramen', 'main', 2, 1, '2026-04-11 05:58:33'),
(5, 'Pork Menudo', 'main', 2, 1, '2026-04-11 05:58:33'),
(6, 'Soba Noodles (Demon Slayer)', 'main', 2, 1, '2026-04-11 05:58:33'),
(7, 'Embotido', 'main', 2, 1, '2026-04-11 05:58:33'),
(8, 'Pork Humba', 'main', 2, 1, '2026-04-11 05:58:33'),
(9, 'Grilled Liempo', 'main', 2, 1, '2026-04-11 05:58:33'),
(10, 'Soma\'s Fake Pork Roast', 'main', 2, 1, '2026-04-11 05:58:33'),
(11, 'Chicken Curry', 'main', 2, 1, '2026-04-11 05:58:33'),
(12, 'Sinigang na Baboy', 'main', 2, 1, '2026-04-11 05:58:33'),
(13, 'Dinuguan', 'main', 2, 1, '2026-04-11 05:58:33'),
(14, 'Callos', 'main', 2, 1, '2026-04-11 05:58:33'),
(15, 'Pork Bistek', 'main', 2, 1, '2026-04-11 05:58:33'),
(16, 'Kyoko\'s Taiyaki', 'dessert', 2, 1, '2026-04-11 05:58:33'),
(17, 'Coffee Jelly (Saiki K)', 'dessert', 2, 1, '2026-04-11 05:58:33'),
(18, 'Halo-Halo', 'dessert', 2, 1, '2026-04-11 05:58:33'),
(19, 'Maja Blanca', 'dessert', 2, 1, '2026-04-11 05:58:33'),
(20, 'Fruit Salad', 'dessert', 2, 1, '2026-04-11 05:58:33'),
(21, 'Palitaw', 'dessert', 2, 1, '2026-04-11 05:58:33'),
(22, 'Biko', 'dessert', 2, 1, '2026-04-11 05:58:33'),
(23, 'Ichiraku Ramen', 'main', 2, 1, '2026-04-11 15:11:32'),
(24, 'Sanji\'s Fried Rice', 'main', 2, 1, '2026-04-11 15:11:32'),
(25, 'Tonio\'s Italian Pasta (JoJo)', 'main', 2, 1, '2026-04-11 15:11:32'),
(26, 'Soma\'s Fake Pork Roast', 'main', 2, 1, '2026-04-11 15:11:32'),
(27, 'Goku\'s Meat Feast', 'main', 2, 1, '2026-04-11 15:11:32'),
(28, 'Soba Noodles (Demon Slayer)', 'main', 2, 1, '2026-04-11 15:11:32'),
(29, 'All Blue Mixed Seafood', 'main', 2, 1, '2026-04-11 15:11:32'),
(30, 'Spicy Mapo Tofu (Angel Beats)', 'main', 2, 1, '2026-04-11 15:11:32'),
(31, 'Coffee Jelly (Saiki K)', 'dessert', 2, 1, '2026-04-11 15:11:32'),
(32, 'Kyoko\'s Taiyaki', 'dessert', 2, 1, '2026-04-11 15:11:32');

-- --------------------------------------------------------

--
-- Table structure for table `job_orders`
--

CREATE TABLE `job_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `role_required` varchar(50) NOT NULL DEFAULT 'waiter',
  `status` enum('pending','accepted','declined') NOT NULL DEFAULT 'pending',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `responded_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `job_orders`
--

INSERT INTO `job_orders` (`id`, `booking_id`, `staff_id`, `role_required`, `status`, `sent_at`, `responded_at`, `notes`) VALUES
(1, 10, 4, 'Head Cook', 'pending', '2026-04-13 02:43:35', NULL, NULL),
(2, 10, 10, 'Waiter', 'pending', '2026-04-13 02:43:35', NULL, NULL),
(3, 10, 11, 'Waiter', 'pending', '2026-04-13 02:43:35', NULL, NULL),
(4, 10, 8, 'Waiter', 'pending', '2026-04-13 02:43:35', NULL, NULL),
(5, 10, 7, 'Waiter', 'pending', '2026-04-13 02:43:35', NULL, NULL),
(6, 10, 9, 'Waiter', 'pending', '2026-04-13 02:43:35', NULL, NULL),
(7, 10, 3, 'Waiter', 'pending', '2026-04-13 02:43:35', NULL, NULL),
(8, 10, 12, 'Waiter', 'pending', '2026-04-13 02:43:35', NULL, NULL),
(17, 12, 4, 'Waiter', 'pending', '2026-04-13 23:57:21', NULL, NULL),
(18, 12, 10, 'Waiter', 'pending', '2026-04-13 23:57:21', NULL, NULL),
(19, 12, 8, 'Waiter', 'pending', '2026-04-13 23:57:21', NULL, NULL),
(20, 12, 7, 'Waiter', 'pending', '2026-04-13 23:57:21', NULL, NULL),
(21, 12, 9, 'Head Cook', 'pending', '2026-04-13 23:57:21', NULL, NULL),
(22, 12, 6, 'Waiter', 'pending', '2026-04-13 23:57:21', NULL, NULL),
(23, 12, 3, 'Waiter', 'pending', '2026-04-13 23:57:21', NULL, NULL),
(24, 13, 4, 'Waiter', 'pending', '2026-04-14 12:07:51', NULL, NULL),
(25, 13, 10, 'Waiter', 'pending', '2026-04-14 12:07:51', NULL, NULL),
(26, 13, 5, 'Waiter', 'pending', '2026-04-14 12:07:51', NULL, NULL),
(27, 13, 11, 'Waiter', 'pending', '2026-04-14 12:07:51', NULL, NULL),
(28, 13, 8, 'Waiter', 'pending', '2026-04-14 12:07:51', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `leave_requests`
--

CREATE TABLE `leave_requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `staff_id` int(10) UNSIGNED NOT NULL,
  `leave_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `leave_requests`
--

INSERT INTO `leave_requests` (`id`, `staff_id`, `leave_date`, `reason`, `status`, `reviewed_by`, `reviewed_at`, `created_at`) VALUES
(1, 5, '2026-04-22', 'tinatamad ako', 'approved', 1, '2026-04-11 14:46:10', '2026-04-11 14:45:52'),
(2, 5, '2026-04-30', 'tinatamad ako', 'approved', 1, '2026-04-11 14:48:26', '2026-04-11 14:48:11'),
(3, 5, '2026-04-15', '', 'rejected', 1, '2026-04-13 07:03:08', '2026-04-13 02:49:23'),
(5, 5, '2026-04-28', 'basta', 'approved', 1, '2026-04-13 02:50:55', '2026-04-13 02:49:41'),
(6, 5, '2026-04-25', 'asdasdas', 'approved', 1, '2026-04-14 00:07:55', '2026-04-14 00:06:44');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `type` enum('job_assigned','leave_approved','leave_rejected','general') NOT NULL DEFAULT 'general',
  `title` varchar(150) NOT NULL,
  `body` text DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `booking_id` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `body`, `is_read`, `booking_id`, `created_at`) VALUES
(1, 5, 'leave_approved', '✅ Leave Approved', 'Your leave request for April 22, 2026 has been approved.', 1, NULL, '2026-04-11 14:46:10'),
(2, 5, 'leave_approved', '✅ Leave Approved', 'Your leave request for April 30, 2026 has been approved.', 1, NULL, '2026-04-11 14:48:26'),
(3, 4, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Head Cook. Please check your schedule.', 0, NULL, '2026-04-13 02:43:35'),
(4, 10, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 02:43:35'),
(5, 11, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 02:43:35'),
(6, 8, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 02:43:35'),
(7, 7, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 02:43:35'),
(8, 9, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 02:43:35'),
(9, 3, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 02:43:35'),
(10, 12, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 02:43:35'),
(11, 5, 'leave_approved', '✅ Leave Approved', 'Your leave request for April 28, 2026 has been approved.', 1, NULL, '2026-04-13 02:50:55'),
(12, 5, 'leave_rejected', '❌ Leave Request Rejected', 'Your leave request for April 15, 2026 was not approved.', 1, NULL, '2026-04-13 07:03:08'),
(13, 4, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 07:51:19'),
(14, 10, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 07:51:19'),
(15, 5, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 1, NULL, '2026-04-13 07:51:19'),
(16, 11, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 07:51:19'),
(17, 9, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 07:51:19'),
(18, 6, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 07:51:19'),
(19, 3, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 07:51:19'),
(20, 12, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 07:51:19'),
(21, 4, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 23:57:21'),
(22, 10, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 23:57:21'),
(23, 8, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 23:57:21'),
(24, 7, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 23:57:21'),
(25, 9, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Head Cook. Please check your schedule.', 0, NULL, '2026-04-13 23:57:21'),
(26, 6, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 23:57:21'),
(27, 3, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-13 23:57:21'),
(28, 5, 'leave_approved', '✅ Leave Approved', 'Your leave request for April 25, 2026 has been approved.', 1, NULL, '2026-04-14 00:07:55'),
(29, 4, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-14 12:07:51'),
(30, 10, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-14 12:07:51'),
(31, 5, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 1, NULL, '2026-04-14 12:07:51'),
(32, 11, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-14 12:07:51'),
(33, 8, 'job_assigned', 'New Job Assignment', 'You have been assigned to a booking as a Waiter. Please check your schedule.', 0, NULL, '2026-04-14 12:07:51');

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int(10) UNSIGNED NOT NULL,
  `set_name` varchar(10) NOT NULL COMMENT 'Set A, Set B, …',
  `pax_count` int(10) UNSIGNED NOT NULL COMMENT 'Base pax count for this tier',
  `price` decimal(10,2) NOT NULL COMMENT 'Flat price at base pax_count',
  `max_main_dishes` int(10) UNSIGNED NOT NULL DEFAULT 5,
  `max_desserts` int(10) UNSIGNED NOT NULL DEFAULT 1,
  `includes_rice` tinyint(1) NOT NULL DEFAULT 1,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `set_name`, `pax_count`, `price`, `max_main_dishes`, `max_desserts`, `includes_rice`, `is_active`) VALUES
(1, 'Set A', 50, 10000.00, 5, 1, 1, 1),
(2, 'Set B', 100, 15000.00, 5, 1, 1, 1),
(3, 'Set C', 150, 20000.00, 5, 1, 1, 1),
(4, 'Set D', 200, 25000.00, 5, 1, 1, 1),
(5, 'Set E', 250, 30000.00, 5, 1, 1, 1),
(6, 'Set F', 300, 35000.00, 5, 1, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(10) UNSIGNED NOT NULL,
  `booking_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('cash','bank_transfer','gcash','maya') NOT NULL DEFAULT 'cash',
  `reference_no` varchar(100) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `notes` text DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `booking_id`, `amount`, `payment_method`, `reference_no`, `payment_date`, `notes`, `recorded_by`, `created_at`) VALUES
(8, 8, 14625.00, 'cash', NULL, '2026-04-12', 'Downpayment', 1, '2026-04-12 11:27:46'),
(9, 9, 17500.00, 'cash', NULL, '2026-04-12', 'Downpayment', 1, '2026-04-12 12:37:44'),
(10, 10, 17500.00, 'cash', NULL, '2026-04-13', 'Downpayment', 1, '2026-04-13 02:43:35'),
(11, 10, 17500.00, 'cash', '', '2026-04-13', NULL, 1, '2026-04-13 02:44:37'),
(14, 12, 10000.00, 'cash', NULL, '2026-04-14', 'Downpayment', 1, '2026-04-13 23:57:21'),
(15, 12, 10000.00, 'cash', '', '2026-04-14', NULL, 1, '2026-04-14 00:02:57'),
(16, 13, 5000.00, 'cash', NULL, '2026-04-14', 'Downpayment', 1, '2026-04-14 12:07:51'),
(17, 8, 14625.00, 'cash', '', '2026-04-14', NULL, 1, '2026-04-14 12:08:35');

-- --------------------------------------------------------

--
-- Table structure for table `recipe_ingredients`
--

CREATE TABLE `recipe_ingredients` (
  `id` int(10) UNSIGNED NOT NULL,
  `dish_id` int(10) UNSIGNED NOT NULL,
  `ingredient_name` varchar(100) NOT NULL,
  `base_quantity` decimal(10,4) NOT NULL,
  `unit` varchar(30) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `recipe_ingredients`
--

INSERT INTO `recipe_ingredients` (`id`, `dish_id`, `ingredient_name`, `base_quantity`, `unit`, `created_at`) VALUES
(1, 1, 'Chicken', 10.0000, 'kg', '2026-04-11 16:09:58'),
(2, 1, 'Soy Sauce', 1.5000, 'L', '2026-04-11 16:09:58'),
(3, 11, 'Chicken breast', 500.0000, 'g', '2026-04-12 14:42:08'),
(4, 17, 'gelatin powerrr', 2.0000, 'packs', '2026-04-12 15:19:07'),
(5, 11, 'gata', 10.0000, 'L', '2026-04-12 15:39:30'),
(6, 30, 'tofu', 2.0000, 'pcs', '2026-04-13 00:13:16'),
(7, 11, 'potato', 300.0000, 'g', '2026-04-13 02:45:57');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','frontdesk','staff') NOT NULL DEFAULT 'staff',
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `is_active`, `created_at`) VALUES
(1, 'KRY', 'admin@yazzies.com', '$2y$10$TuTsnd39a5ZcRqkoaiYNR.F0iatk1wY2T1Ht2mFiEgHR4H9.S1Oyy', 'admin', '', 1, '2026-04-11 11:52:14'),
(2, 'Maria Santos', 'frontdesk@yazzies.com', '$2y$10$iI7wM/uIRB5ub8weQBkmden/Can4KNgFC.r56eL0n66SQosaeRSD6', 'frontdesk', NULL, 1, '2026-04-11 11:52:14'),
(3, 'Ramon Dela Cruz', 'ramon@yazzies.com', '$2y$10$mqKa0tOGpj8vcs4RSosSsOGXi.eMErPGnCFBDKP.M2AEcPDpZvHp.', 'staff', NULL, 1, '2026-04-11 11:52:14'),
(4, 'Ana Lim Kufal', 'ana@yazzies.com', '$2y$10$C5mqddpLcI4CVFfSg2fk2.PRagQ6eMsqVoSCpuhcl8O8CK7mF6Cqi', 'staff', '', 1, '2026-04-11 11:52:14'),
(5, 'HelloWorld', 'pawcasiano@kld.edu.ph', '$2y$10$knx9EBbIEy51VQJ/cD90lehwXEi7MiTIxY5D.VuVs0MmfiIIm1icO', 'staff', '09950219612', 1, '2026-04-11 14:43:24'),
(6, 'Naruto Uzumaki', 'juan@yazzies.com', '$2y$10$CeAkaxfPxVL9BTItr3EcfOCrZ19uj3UvQgVWXnp3OzXT8lBvScq4G', 'staff', '09171234560', 1, '2026-04-11 15:11:32'),
(7, 'Mikasa Ackerman', 'maria@yazzies.com', '$2y$10$CeAkaxfPxVL9BTItr3EcfOCrZ19uj3UvQgVWXnp3OzXT8lBvScq4G', 'staff', '09171234561', 1, '2026-04-11 15:11:32'),
(8, 'Levi Ackerman', 'jose@yazzies.com', '$2y$10$CeAkaxfPxVL9BTItr3EcfOCrZ19uj3UvQgVWXnp3OzXT8lBvScq4G', 'staff', '09171234562', 1, '2026-04-11 15:11:32'),
(9, 'Monkey D. Luffy', 'andres@yazzies.com', '$2y$10$CeAkaxfPxVL9BTItr3EcfOCrZ19uj3UvQgVWXnp3OzXT8lBvScq4G', 'staff', '09171234563', 1, '2026-04-11 15:11:32'),
(10, 'Erza Scarlet', 'gabriela@yazzies.com', '$2y$10$CeAkaxfPxVL9BTItr3EcfOCrZ19uj3UvQgVWXnp3OzXT8lBvScq4G', 'staff', '09171234564', 1, '2026-04-11 15:11:32'),
(11, 'L Lawliet', 'apolinario@yazzies.com', '$2y$10$CeAkaxfPxVL9BTItr3EcfOCrZ19uj3UvQgVWXnp3OzXT8lBvScq4G', 'staff', '09171234565', 1, '2026-04-11 15:11:32'),
(12, 'Roronoa Zoro', 'antonio@yazzies.com', '$2y$10$CeAkaxfPxVL9BTItr3EcfOCrZ19uj3UvQgVWXnp3OzXT8lBvScq4G', 'staff', '09171234566', 1, '2026-04-11 15:11:32'),
(13, 'Saber', 'melchora@yazzies.com', '$2y$10$CeAkaxfPxVL9BTItr3EcfOCrZ19uj3UvQgVWXnp3OzXT8lBvScq4G', 'staff', '09171234567', 1, '2026-04-11 15:11:32'),
(14, 'Sasuke Uchiha', 'emilio@yazzies.com', '$2y$10$CeAkaxfPxVL9BTItr3EcfOCrZ19uj3UvQgVWXnp3OzXT8lBvScq4G', 'staff', '09171234568', 1, '2026-04-11 15:11:32'),
(15, 'Saitama', 'lapulapu@yazzies.com', '$2y$10$CeAkaxfPxVL9BTItr3EcfOCrZ19uj3UvQgVWXnp3OzXT8lBvScq4G', 'staff', '09171234569', 1, '2026-04-11 15:11:32'),
(16, 'fff', 'try@kld.edu.ph', '$2y$10$S9ARQHbDnIxnPWBEvyZVL.R68SJd/SjJKR1NKUIKHAELvyXVhub26', 'admin', '', 1, '2026-04-13 07:56:24');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `archived_bookings`
--
ALTER TABLE `archived_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_date` (`event_date`),
  ADD KEY `idx_archived_by` (`archived_by`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_al_entity` (`entity`,`entity_id`),
  ADD KEY `idx_al_user` (`user_id`),
  ADD KEY `idx_al_action` (`action`),
  ADD KEY `idx_al_ts` (`created_at`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `idx_unique_event_date` (`event_date`),
  ADD KEY `idx_client` (`client_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_pay_status` (`payment_status`),
  ADD KEY `idx_book_status` (`booking_status`),
  ADD KEY `idx_package_id` (`package_id`);

--
-- Indexes for table `booking_dishes`
--
ALTER TABLE `booking_dishes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_booking_dish` (`booking_id`,`dish_id`),
  ADD KEY `idx_bd_booking` (`booking_id`),
  ADD KEY `idx_bd_dish` (`dish_id`);

--
-- Indexes for table `booking_staff`
--
ALTER TABLE `booking_staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_booking_staff` (`booking_id`,`staff_id`),
  ADD KEY `idx_staff_id` (`staff_id`),
  ADD KEY `fk_bs_assigned_by` (`assigned_by`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `dishes`
--
ALTER TABLE `dishes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_category` (`category`);

--
-- Indexes for table `job_orders`
--
ALTER TABLE `job_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_staff_id` (`staff_id`);

--
-- Indexes for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_staff_date` (`staff_id`,`leave_date`),
  ADD KEY `idx_date` (`leave_date`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `fk_lr_reviewer` (`reviewed_by`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `fk_notif_booking` (`booking_id`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_set_name` (`set_name`),
  ADD UNIQUE KEY `uq_pax_count` (`pax_count`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking` (`booking_id`),
  ADD KEY `idx_recorded` (`recorded_by`);

--
-- Indexes for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dish_id` (`dish_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `archived_bookings`
--
ALTER TABLE `archived_bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `booking_dishes`
--
ALTER TABLE `booking_dishes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=95;

--
-- AUTO_INCREMENT for table `booking_staff`
--
ALTER TABLE `booking_staff`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `dishes`
--
ALTER TABLE `dishes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `job_orders`
--
ALTER TABLE `job_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `leave_requests`
--
ALTER TABLE `leave_requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `fk_bookings_client` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `fk_bookings_package2` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bookings_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `booking_dishes`
--
ALTER TABLE `booking_dishes`
  ADD CONSTRAINT `fk_bd_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bd_dish` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`);

--
-- Constraints for table `booking_staff`
--
ALTER TABLE `booking_staff`
  ADD CONSTRAINT `fk_bs_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bs_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bs_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `job_orders`
--
ALTER TABLE `job_orders`
  ADD CONSTRAINT `fk_joborders_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_joborders_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `leave_requests`
--
ALTER TABLE `leave_requests`
  ADD CONSTRAINT `fk_lr_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_lr_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `fk_payments_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_payments_user` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `recipe_ingredients`
--
ALTER TABLE `recipe_ingredients`
  ADD CONSTRAINT `recipe_ingredients_ibfk_1` FOREIGN KEY (`dish_id`) REFERENCES `dishes` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
