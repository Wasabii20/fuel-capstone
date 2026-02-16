-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3307
-- Generation Time: Feb 16, 2026 at 09:23 AM
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
-- Database: `bfp_fuel_system`
--

-- --------------------------------------------------------

--
-- Table structure for table `drivers`
--

CREATE TABLE `drivers` (
  `driver_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `license_no` varchar(50) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `drivers`
--

INSERT INTO `drivers` (`driver_id`, `full_name`, `license_no`, `status`) VALUES
(1, 'Justin P. Endico', '9991', 'active'),
(2, 'Michael G. Astillo', '1999', 'active'),
(5, 'Haide Galdo', '9898', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `fuel_stocks`
--

CREATE TABLE `fuel_stocks` (
  `id` int(11) NOT NULL,
  `stock_type` varchar(50) NOT NULL DEFAULT 'gasoline' COMMENT 'Type of fuel stock (gasoline, diesel, etc.)',
  `category` varchar(50) DEFAULT 'fuel tank',
  `container_label` varchar(100) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL COMMENT 'Amount in liters',
  `transaction_type` enum('added','removed') NOT NULL COMMENT 'Type of transaction',
  `note` text DEFAULT NULL COMMENT 'Notes about the transaction',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks all fuel stock transactions (additions and removals)';

--
-- Dumping data for table `fuel_stocks`
--

INSERT INTO `fuel_stocks` (`id`, `stock_type`, `category`, `container_label`, `amount`, `transaction_type`, `note`, `created_at`, `updated_at`) VALUES
(28, 'gasoline', 'fuel can', 'Fuel can ', 10.00, 'removed', '', '2026-02-16 01:24:29', '2026-02-16 01:24:29'),
(29, 'gasoline', 'fuel can', 'Fuel can ', 10.00, 'removed', '', '2026-02-16 01:24:43', '2026-02-16 01:24:43'),
(30, 'gasoline', 'fuel can', 'Fuel can ', 10.00, 'removed', '', '2026-02-16 01:24:47', '2026-02-16 01:24:47'),
(31, 'gasoline', 'fuel can', 'Eagle', 10.00, 'added', 'Ordered', '2026-02-16 01:33:08', '2026-02-16 01:33:08'),
(32, 'gasoline', 'fuel can', 'Eagle', 10.00, 'added', 'Ordered', '2026-02-16 01:33:18', '2026-02-16 01:33:18'),
(33, 'gasoline', 'fuel can', 'Eagle', 10.00, 'added', 'Ordered', '2026-02-16 01:33:25', '2026-02-16 01:33:25'),
(34, 'gasoline', 'fuel can', 'Eagle', 10.00, 'added', 'Ordered', '2026-02-16 01:33:31', '2026-02-16 01:33:31'),
(35, 'gasoline', 'fuel tank', NULL, 50.00, 'added', 'refill', '2026-02-16 01:34:24', '2026-02-16 01:34:24'),
(36, 'gasoline', 'fuel can', 'Eagle', 40.00, 'removed', '', '2026-02-16 01:42:38', '2026-02-16 01:42:38'),
(37, 'gasoline', 'fuel tank', NULL, 20.00, 'removed', '', '2026-02-16 01:42:54', '2026-02-16 01:42:54'),
(38, 'gasoline', 'fuel tank', NULL, 50.00, 'added', '', '2026-02-16 01:43:13', '2026-02-16 01:43:13'),
(39, 'gasoline', 'fuel tank', NULL, 600.00, 'added', 'Refill', '2026-02-16 01:43:43', '2026-02-16 01:43:43'),
(40, 'gasoline', 'fuel can', 'Eagle', 10.00, 'added', 'Ordered', '2026-02-16 01:44:12', '2026-02-16 01:44:12'),
(41, 'gasoline', 'fuel can', 'Eagle', 10.00, 'removed', 'Use for refilling vehicles', '2026-02-16 02:12:20', '2026-02-16 02:12:20');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` longtext NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_data` longtext DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `related_id`, `related_data`, `is_read`, `created_at`) VALUES
(9, 1, 'trip_request', '🚗 Trip Request from Jonas Lim', 'Driver: Jonas Lim (Admin)\nUser ID: 1', 1, NULL, 0, '2026-01-31 08:23:27'),
(10, 4, 'trip_request', '🚗 Trip Request from Jonas Lim', 'Driver: Jonas Lim (Admin)\nUser ID: 1', 1, NULL, 0, '2026-01-31 08:23:27'),
(12, 4, 'trip_request', '🚗 Trip Request from Jonas Lim', 'Driver: Jonas Lim (Admin)\nUser ID: 1', 1, NULL, 0, '2026-02-02 20:06:22'),
(13, 1, 'trip_approved', '✓ Trip Request Approved', 'Admin Jonas Lim has approved your trip ticket request and is creating the ticket details now.', 11, NULL, 0, '2026-02-02 20:06:32'),
(15, 4, 'trip_request', '🚗 Trip Request from Jonas Lim', 'Driver: Jonas Lim (Admin)\nUser ID: 1', 1, NULL, 0, '2026-02-02 20:12:09'),
(16, 1, 'trip_approved', '✓ Trip Request Approved', 'Admin Jonas Lim has approved your trip ticket request and is creating the ticket details now.', 14, NULL, 0, '2026-02-02 20:12:18'),
(17, 1, 'trip_request', '🚗 Trip Request from Haide Galdo', 'Driver: Haide Galdo (Driver)\nUser ID: 14', 14, NULL, 0, '2026-02-11 10:37:30'),
(18, 4, 'trip_request', '🚗 Trip Request from Haide Galdo', 'Driver: Haide Galdo (Driver)\nUser ID: 14', 14, NULL, 0, '2026-02-11 10:37:30'),
(20, 14, 'trip_approved', '✓ Trip Request Approved', 'Admin Haide Galdo has approved your trip ticket request and is creating the ticket details now.', 19, NULL, 0, '2026-02-11 11:00:39');

-- --------------------------------------------------------

--
-- Table structure for table `price_data`
--

CREATE TABLE `price_data` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `unit` varchar(50) DEFAULT 'per unit',
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `price_data`
--

INSERT INTO `price_data` (`id`, `item_name`, `price`, `unit`, `last_updated`, `updated_by`) VALUES
(1, 'Grease Oil', 3.00, 'per liter', '2026-01-27 02:17:28', 1),
(2, 'Lubricating Oil', 5.00, 'per liter', '2026-01-27 02:17:28', 1),
(3, 'Gear Oil', 10.00, 'per liter', '2026-01-27 02:17:28', 1),
(4, 'Fuel Price', 58.00, 'per liter', '2026-01-27 02:17:28', 1),
(5, 'Vehicle Repair - General', 1000.00, 'per service', '2026-01-27 05:53:30', 1);

-- --------------------------------------------------------

--
-- Table structure for table `trip_tickets`
--

CREATE TABLE `trip_tickets` (
  `id` int(11) NOT NULL,
  `control_no` varchar(50) NOT NULL,
  `ticket_date` date NOT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `vehicle_plate_no` varchar(20) NOT NULL,
  `authorized_passenger` varchar(255) DEFAULT NULL,
  `places_to_visit` text DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `dep_office_time` varchar(20) DEFAULT NULL,
  `arr_location_time` varchar(20) DEFAULT NULL,
  `dep_location_time` varchar(20) DEFAULT NULL,
  `arr_office_time` varchar(20) DEFAULT NULL,
  `approx_distance` decimal(10,2) DEFAULT NULL,
  `speedometer_start` int(11) DEFAULT NULL,
  `speedometer_end` int(11) DEFAULT NULL,
  `gas_balance_start` decimal(10,2) DEFAULT NULL,
  `gas_issued_office` decimal(10,2) DEFAULT NULL,
  `gas_added_trip` decimal(10,2) DEFAULT NULL,
  `gas_total` decimal(10,2) DEFAULT NULL,
  `gas_used_trip` decimal(10,2) DEFAULT NULL,
  `gas_balance_end` decimal(10,2) DEFAULT NULL,
  `gear_oil_issued` decimal(10,2) DEFAULT NULL,
  `lub_oil_issued` decimal(10,2) DEFAULT NULL,
  `grease_issued` decimal(10,2) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `authorized_by` varchar(100) DEFAULT NULL,
  `auth_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('Pending','Active','Submitted') NOT NULL DEFAULT 'Pending',
  `qr_code` varchar(255) DEFAULT NULL,
  `passenger_1_name` varchar(255) DEFAULT NULL,
  `passenger_1_date` date DEFAULT NULL,
  `passenger_2_name` varchar(255) DEFAULT NULL,
  `passenger_2_date` date DEFAULT NULL,
  `passenger_3_name` varchar(255) DEFAULT NULL,
  `passenger_3_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `trip_tickets`
--

INSERT INTO `trip_tickets` (`id`, `control_no`, `ticket_date`, `driver_id`, `vehicle_plate_no`, `authorized_passenger`, `places_to_visit`, `purpose`, `dep_office_time`, `arr_location_time`, `dep_location_time`, `arr_office_time`, `approx_distance`, `speedometer_start`, `speedometer_end`, `gas_balance_start`, `gas_issued_office`, `gas_added_trip`, `gas_total`, `gas_used_trip`, `gas_balance_end`, `gear_oil_issued`, `lub_oil_issued`, `grease_issued`, `remarks`, `authorized_by`, `auth_date`, `created_at`, `status`, `qr_code`, `passenger_1_name`, `passenger_1_date`, `passenger_2_name`, `passenger_2_date`, `passenger_3_name`, `passenger_3_date`) VALUES
(35, '1001', '2026-02-16', 5, 'BFP-001', 'jonas', 'Mr.Work', 'Fire alert', '10am', '11am', '12pm', '1pm', 4.17, 10, 20, 87.00, 10.00, 10.00, 107.00, 15.00, 92.00, 0.00, 0.00, 0.00, 'An easy trip and Save a lot of time', NULL, NULL, '2026-02-16 04:41:34', 'Pending', 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=http%3A%2F%2Flocalhost%2Ffuelcapstone%2Fview_ticket.php%3Fid%3D35%26control%3D1001', 'Jonas C. Lim', '2026-02-16', '', '0000-00-00', '', '0000-00-00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user','chief') NOT NULL,
  `profile_pic` varchar(255) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `driver_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `profile_pic`, `first_name`, `last_name`, `email`, `phone`, `department`, `position`, `employee_id`, `driver_id`, `created_at`) VALUES
(1, 'jonas', '$2y$10$eDb6DLsO/Tx0QmdSU0K9Yutj3Mt.qFXm4PXHKJuMtjCS94Gy3cq/O', 'admin', 'uploads/profile_pics/user_1_1770782997.jpg', 'Jonas', 'Lim', 'dragonspare15@gmail.com', '09684028417', 'Fire Operations', 'Admin', '1001', NULL, '2026-01-09 03:47:55'),
(2, 'justin', '$2y$10$s/XZ049a0J2DwFo/IXkat.pxvss6Og0BXr0mDGq7bQ/BAvRY/JKRy', 'user', 'uploads/profile_pics/user_2_1768789390.jpg', 'Justin', 'Endico', 'pcoquilla@gmail.com', '09684028417', 'Fire Operations', 'driver', '1000', 1, '2026-01-09 04:10:54'),
(3, 'michael', '$2y$10$i.B2VSAmJHr1YB3MLJ8bpOlYv.kluz5Oh1obioLgZrwTpfhOyz.JG', 'user', 'uploads/profile_pics/user_3_1768802791.jpg', 'Michael', 'Astillo', 'example@gmail.com', '', 'Fire Operations', 'driver', '', 2, '2026-01-19 06:05:00'),
(4, 'Steffi', '$2y$10$4aw3yiQWwjn/bip.YceN1O0UyfFeZgDnwjJPc2z2SkdJGVDFGxs.O', 'admin', 'uploads/profile_pics/user_4_1768994505.jpg', 'Steffi', 'Canete', 'Steffi.canete@lsu.edu.ph', '', 'Fire Operations', 'Admin', '', NULL, '2026-01-21 10:57:37'),
(14, 'HaideUser', '$2y$10$pAXPrBilVW6GvjCGpgKPEuArW6s/F8PDBBS3GsrF.SeB3nN5Agb4m', 'user', 'uploads/profile_pics/user_14_1770775938.png', 'Haide', 'Galdo', 'example@gmail.com', '', 'Fire Operations', 'Driver', '', 5, '2026-02-11 01:57:54'),
(15, 'Haide', '$2y$10$uJM7cG3qnCXx/7Tu1ZD.g.Y0dCb2incdUT4PcjEI9MOtx9IM7A8gG', 'admin', 'uploads/profile_pics/user_15_1770775643.png', 'Haide', 'Galdo', 'example@gmail.com', '', 'Fire Operations', 'Admin', '', NULL, '2026-02-11 02:06:32');

-- --------------------------------------------------------

--
-- Table structure for table `user_logs`
--

CREATE TABLE `user_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `role` enum('admin','user','chief') NOT NULL,
  `action` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `module` enum('auth','users','drivers','vehicles','trip_tickets') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `browser` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_logs`
--

INSERT INTO `user_logs` (`id`, `user_id`, `role`, `action`, `description`, `module`, `reference_id`, `browser`, `ip_address`, `created_at`) VALUES
(1, 4, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 11:18:27'),
(2, 4, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 11:36:36'),
(3, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 11:39:47'),
(4, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 13:00:12'),
(5, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 13:00:22'),
(6, 1, 'admin', 'Login', 'User logged in', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 14:25:26'),
(7, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 17:09:07'),
(8, 2, 'user', 'Login', 'User logged in', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 17:09:18'),
(9, 1, 'admin', 'Login', 'User logged in', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 17:09:23'),
(10, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 17:09:33'),
(11, 4, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 17:09:56'),
(12, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 17:12:46'),
(13, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 17:12:59'),
(14, 4, 'admin', 'Login', 'User logged in', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '::1', '2026-01-21 17:13:15'),
(15, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 01:16:29'),
(16, 1, 'admin', 'Login', 'User logged in', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 01:42:33'),
(17, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1002', 'trip_tickets', 14, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 03:22:39'),
(18, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1003', 'trip_tickets', 15, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 05:20:28'),
(19, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 05:46:13'),
(20, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 06:06:04'),
(21, 4, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 06:55:43'),
(22, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 06:58:58'),
(23, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 07:00:38'),
(24, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1001', 'trip_tickets', 16, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 08:16:39'),
(25, 1, 'admin', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 16, NULL, NULL, '2026-01-26 08:44:42'),
(26, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1004', 'trip_tickets', 17, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 09:22:52'),
(27, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1004', 'trip_tickets', 19, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 09:23:38'),
(28, 1, 'admin', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 19, NULL, NULL, '2026-01-26 09:24:13'),
(29, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1005', 'trip_tickets', 20, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 09:31:34'),
(30, 1, 'admin', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 20, NULL, NULL, '2026-01-26 09:36:45'),
(31, 1, 'admin', 'Add Driver', 'Added driver: Fransis with default user account (Officer/123)', 'drivers', 3, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 10:31:06'),
(32, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 10:34:25'),
(33, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 10:48:50'),
(36, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 11:05:14'),
(37, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 11:30:40'),
(38, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1006', 'trip_tickets', 21, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 12:19:27'),
(39, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1007', 'trip_tickets', 22, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-26 14:05:27'),
(40, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-01-27 01:17:09'),
(41, 1, 'admin', 'Delete User', 'Deleted user with ID: 7', 'users', 7, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 01:29:12'),
(42, 1, 'admin', 'Delete User', 'Deleted user with ID: 5', 'users', 5, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 01:29:24'),
(43, 1, 'admin', 'Delete Driver', 'Deleted driver with ID: 3', 'drivers', 3, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 01:29:43'),
(44, 1, 'admin', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 22, NULL, NULL, '2026-01-27 01:33:20'),
(45, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 03:40:26'),
(46, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 03:41:18'),
(47, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1008', 'trip_tickets', 24, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 05:33:28'),
(48, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 05:33:47'),
(49, 2, 'user', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 24, NULL, NULL, '2026-01-27 05:34:00'),
(50, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 05:52:10'),
(51, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1009', 'trip_tickets', 25, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:05:56'),
(52, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:06:02'),
(53, 3, 'user', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 25, NULL, NULL, '2026-01-27 06:08:05'),
(54, 1, 'admin', 'Login', 'User logged in', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:10:40'),
(55, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:11:31'),
(56, 3, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:30:32'),
(57, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:36:09'),
(58, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:36:16'),
(59, 3, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:38:03'),
(60, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:38:34'),
(61, 3, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:42:50'),
(62, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:44:14'),
(63, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:44:26'),
(64, 3, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:47:06'),
(65, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:47:35'),
(66, 3, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:53:14'),
(67, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:53:49'),
(68, 3, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:54:24'),
(69, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:54:41'),
(70, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1010', 'trip_tickets', 26, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:56:14'),
(71, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 06:56:28'),
(72, 2, 'user', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 26, NULL, NULL, '2026-01-27 06:57:04'),
(73, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 07:07:41'),
(74, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 07:08:57'),
(75, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 07:09:46'),
(76, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1001', 'trip_tickets', 27, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 07:23:03'),
(77, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 07:23:13'),
(78, 3, 'user', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 27, NULL, NULL, '2026-01-27 07:23:31'),
(79, 3, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 07:24:15'),
(80, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 07:26:36'),
(81, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 07:35:59'),
(82, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1002', 'trip_tickets', 28, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36 Edg/144.0.0.0', '::1', '2026-01-27 07:48:08'),
(83, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-02 12:05:53'),
(84, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-02 12:11:47'),
(85, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1001', 'trip_tickets', 29, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-02 12:14:44'),
(86, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-02 12:14:58'),
(87, 2, 'user', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 29, NULL, NULL, '2026-02-02 12:15:51'),
(88, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1003', 'trip_tickets', 30, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-02 12:18:18'),
(89, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-02 12:18:23'),
(90, 3, 'user', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 30, NULL, NULL, '2026-02-02 12:20:05'),
(91, 3, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-02 12:20:24'),
(92, 1, 'admin', 'Login', 'User logged in', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-09 14:07:41'),
(93, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1002', 'trip_tickets', 31, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-09 14:11:38'),
(94, 1, 'admin', 'Add User', 'Created user: Haide', 'users', 9, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 00:55:42'),
(95, 1, 'admin', 'Add User', 'Created user: HaideUser', 'users', 10, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 00:56:45'),
(96, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 00:56:56'),
(98, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:03:01'),
(100, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:03:54'),
(104, 1, 'admin', 'Delete User', 'Deleted user with ID: 10', 'users', 10, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:10:21'),
(105, 1, 'admin', 'Delete User', 'Deleted user with ID: 9', 'users', 9, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:10:27'),
(107, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:15:15'),
(109, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:19:10'),
(117, 1, 'admin', 'Delete User', 'Deleted user with ID: 12', 'users', 12, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:35:18'),
(118, 1, 'admin', 'Delete User', 'Deleted user with ID: 11', 'users', 11, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:35:24'),
(119, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:36:36'),
(121, 1, 'admin', 'Delete User', 'Deleted user with ID: 13', 'users', 13, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:51:57'),
(122, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:52:10'),
(123, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:53:10'),
(124, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:53:26'),
(125, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 01:57:35'),
(126, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 02:05:34'),
(127, 15, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 02:11:20'),
(128, 14, '', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 02:25:23'),
(129, 1, 'admin', 'Update User Role', 'Changed user role to: user', 'users', 14, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 02:28:54'),
(130, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 02:29:23'),
(131, 14, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 02:30:01'),
(132, 15, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 02:32:43'),
(133, 14, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 02:37:41'),
(134, 15, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1004', 'trip_tickets', 32, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 03:02:46'),
(135, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1005', 'trip_tickets', 33, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-11 04:14:21'),
(136, 1, 'admin', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 33, NULL, NULL, '2026-02-11 04:14:38'),
(137, 1, 'admin', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 32, NULL, NULL, '2026-02-11 04:25:27'),
(138, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-12 14:17:11'),
(139, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-12 14:19:23'),
(140, 2, 'user', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 31, NULL, NULL, '2026-02-12 14:20:16'),
(141, 2, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-12 14:20:25'),
(142, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1006', 'trip_tickets', 34, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-12 14:25:23'),
(143, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-12 14:25:32'),
(144, 3, 'user', 'Update Trip Ticket Status', 'Updated trip ticket status to: Active', 'trip_tickets', 34, NULL, NULL, '2026-02-12 14:26:46'),
(145, 3, 'user', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '::1', '2026-02-12 14:29:02'),
(146, 1, 'admin', 'Create Trip Ticket', 'Created trip ticket: Control#1001', 'trip_tickets', 35, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-16 04:41:34'),
(147, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-16 04:49:47'),
(148, 1, 'admin', 'Login', 'User logged in', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-16 04:59:31'),
(149, 1, 'admin', 'Logout', 'User logged out', 'auth', NULL, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '::1', '2026-02-16 05:00:22');

-- --------------------------------------------------------

--
-- Table structure for table `vehicles`
--

CREATE TABLE `vehicles` (
  `id` int(11) NOT NULL,
  `vehicle_no` varchar(50) NOT NULL,
  `vehicle_type` varchar(100) NOT NULL,
  `make` varchar(100) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `year` int(4) DEFAULT NULL,
  `color` varchar(50) DEFAULT NULL,
  `engine_no` varchar(100) DEFAULT NULL,
  `chassis_no` varchar(100) DEFAULT NULL,
  `fuel_type` enum('gasoline','diesel','hybrid','lpg') DEFAULT 'gasoline',
  `fuel_capacity` decimal(10,2) DEFAULT 0.00,
  `current_fuel` decimal(10,2) DEFAULT 0.00,
  `gps_enabled` tinyint(1) DEFAULT 0,
  `gps_device_id` varchar(100) DEFAULT NULL,
  `sensor_enabled` tinyint(1) DEFAULT 0,
  `sensor_device_id` varchar(100) DEFAULT NULL,
  `vehicle_photo` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL COMMENT 'Vehicle description and details',
  `status` enum('available','deployed','inactive','in_repair') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicles`
--

INSERT INTO `vehicles` (`id`, `vehicle_no`, `vehicle_type`, `make`, `model`, `year`, `color`, `engine_no`, `chassis_no`, `fuel_type`, `fuel_capacity`, `current_fuel`, `gps_enabled`, `gps_device_id`, `sensor_enabled`, `sensor_device_id`, `vehicle_photo`, `description`, `status`, `created_at`) VALUES
(1, 'BFP-001', 'Fire Truck', 'Hino', '500 Series', 2020, 'Red', 'HN001', 'CH001', 'diesel', 200.00, 95.00, 1, 'GPS-001', 1, 'SENSOR-001', NULL, 'Main Fire Truck Unit', 'available', '2025-11-12 18:31:55'),
(2, 'BFP-002', 'Rescue Truck', 'Isuzu', 'NPR', 2021, 'Red', 'IS002', 'CH002', 'diesel', 150.00, 51.00, 1, 'GPS-002', 1, 'SENSOR-002', NULL, 'Rescue Operations Vehicle', 'available', '2025-11-12 18:31:55'),
(3, 'BFP-003', 'Ambulance', 'Toyota', 'Hiace', 2022, 'White', 'TY003', 'CH003', 'gasoline', 80.00, 64.00, 1, 'GPS-003', 1, 'SENSOR-003', NULL, 'Medical Emergency Vehicle', 'available', '2025-11-12 18:31:55'),
(4, 'BFP-004', 'Patrol Vehicle', 'Nissan', 'Patrol', 2019, 'White', 'NS004', 'CH004', 'diesel', 120.00, 60.00, 1, 'GPS-004', 1, 'SENSOR-004', NULL, 'Patrol Unit', 'available', '2025-11-12 18:31:55'),
(5, 'BFP-005', 'Water Tanker', 'Hino', '700', 2020, 'Silver', 'HN005', 'CH005', 'diesel', 250.00, 91.00, 1, 'GPS-005', 1, 'SENSOR-005', NULL, 'Water Transport Unit', 'available', '2025-11-12 18:31:55');

-- --------------------------------------------------------

--
-- Table structure for table `vehicle_repairs`
--

CREATE TABLE `vehicle_repairs` (
  `id` int(11) NOT NULL,
  `vehicle_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `requester_name` varchar(100) DEFAULT NULL,
  `repair_type` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `priority` enum('low','medium','high','urgent') DEFAULT 'medium',
  `status` enum('pending','approved','in_progress','completed','rejected') DEFAULT 'pending',
  `requested_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `approval_date` datetime DEFAULT NULL,
  `completed_date` datetime DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approver_name` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `taken_by_personnel` varchar(100) DEFAULT NULL COMMENT 'Person who brought the vehicle to the shop',
  `repair_location` varchar(255) DEFAULT NULL,
  `actual_repair_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vehicle_repairs`
--

INSERT INTO `vehicle_repairs` (`id`, `vehicle_id`, `user_id`, `requester_name`, `repair_type`, `description`, `priority`, `status`, `requested_date`, `approval_date`, `completed_date`, `approved_by`, `approver_name`, `notes`, `taken_by_personnel`, `repair_location`, `actual_repair_date`) VALUES
(3, 4, 1, 'jonas', 'Other', 'in need of refuel', 'medium', 'in_progress', '2026-01-27 04:34:13', '2026-01-27 12:44:37', NULL, NULL, NULL, NULL, 'Michael', 'Repair shop maasin', NULL),
(7, 5, 1, 'jonas', 'Brakes', 'Breaks broken', 'high', 'pending', '2026-01-27 07:39:26', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(8, 4, 1, 'jonas', 'Other', 'in need of fuel refill', 'low', 'completed', '2026-02-02 12:21:57', '2026-02-02 20:22:15', '2026-02-02 20:22:26', NULL, NULL, NULL, 'Michael', 'Repair shop maasin', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `drivers`
--
ALTER TABLE `drivers`
  ADD PRIMARY KEY (`driver_id`);

--
-- Indexes for table `fuel_stocks`
--
ALTER TABLE `fuel_stocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_stock_type` (`stock_type`),
  ADD KEY `idx_transaction_type` (`transaction_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `price_data`
--
ALTER TABLE `price_data`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `item_name` (`item_name`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Indexes for table `trip_tickets`
--
ALTER TABLE `trip_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `control_no` (`control_no`),
  ADD KEY `fk_trip_driver` (`driver_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_user_driver` (`driver_id`);

--
-- Indexes for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_module_ref` (`module`,`reference_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `vehicles`
--
ALTER TABLE `vehicles`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vehicle_repairs`
--
ALTER TABLE `vehicle_repairs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vehicle_id` (`vehicle_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_priority` (`priority`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `drivers`
--
ALTER TABLE `drivers`
  MODIFY `driver_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `fuel_stocks`
--
ALTER TABLE `fuel_stocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `price_data`
--
ALTER TABLE `price_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `trip_tickets`
--
ALTER TABLE `trip_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `user_logs`
--
ALTER TABLE `user_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=150;

--
-- AUTO_INCREMENT for table `vehicles`
--
ALTER TABLE `vehicles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `vehicle_repairs`
--
ALTER TABLE `vehicle_repairs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `price_data`
--
ALTER TABLE `price_data`
  ADD CONSTRAINT `price_data_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `trip_tickets`
--
ALTER TABLE `trip_tickets`
  ADD CONSTRAINT `fk_trip_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`) ON UPDATE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_driver` FOREIGN KEY (`driver_id`) REFERENCES `drivers` (`driver_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `user_logs`
--
ALTER TABLE `user_logs`
  ADD CONSTRAINT `fk_user_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vehicle_repairs`
--
ALTER TABLE `vehicle_repairs`
  ADD CONSTRAINT `fk_repair_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_repair_vehicle` FOREIGN KEY (`vehicle_id`) REFERENCES `vehicles` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
