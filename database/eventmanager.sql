-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 12, 2025 at 05:05 PM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `eventmanager`
--

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`, `updated_at`) VALUES
(1, 'Conferencia', 'Eventos de presentación y discusión de temas específicos', '2025-03-02 19:21:53', '2025-03-02 19:21:53'),
(2, 'Taller', 'Sesiones prácticas y de aprendizaje', '2025-03-02 19:21:53', '2025-03-02 19:21:53'),
(3, 'Seminario', 'Reuniones especializadas de naturaleza técnica o académica', '2025-03-02 19:21:53', '2025-03-02 19:21:53'),
(4, 'Networking', 'Eventos para establecer contactos profesionales', '2025-03-02 19:21:53', '2025-03-02 19:21:53'),
(5, 'Cultural', 'Eventos relacionados con arte, música y cultura', '2025-03-02 19:21:53', '2025-03-02 19:21:53'),
(6, 'Conferencias', 'Eventos de presentación y discusión de temas específicos', '2025-03-02 20:13:53', '2025-03-02 20:13:53'),
(7, 'Talleres', 'Sesiones prácticas de aprendizaje y desarrollo de habilidades', '2025-03-02 20:13:53', '2025-03-02 20:13:53'),
(8, 'Seminarios', 'Reuniones especializadas para discutir temas académicos o profesionales', '2025-03-02 20:13:53', '2025-03-02 20:13:53');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type_id` int(11) DEFAULT NULL,
  `organizer_id` int(11) DEFAULT NULL,
  `modality` enum('presential','virtual','hybrid') NOT NULL,
  `capacity` int(11) DEFAULT 0,
  `price` decimal(10,2) DEFAULT 0.00,
  `location` varchar(255) DEFAULT NULL,
  `virtual_link` varchar(255) DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `max_capacity` int(11) DEFAULT NULL,
  `status` enum('draft','published','completed','cancelled') DEFAULT 'draft',
  `category_id` int(11) DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `banner_url` varchar(255) DEFAULT NULL,
  `header_color` varchar(7) DEFAULT NULL,
  `accent_color` varchar(7) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `payment_config` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payment_config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `title`, `description`, `type_id`, `organizer_id`, `modality`, `capacity`, `price`, `location`, `virtual_link`, `start_date`, `end_date`, `max_capacity`, `status`, `category_id`, `logo_url`, `banner_url`, `header_color`, `accent_color`, `created_at`, `updated_at`, `payment_config`) VALUES
(1, 'Conferencia de Tecnología 2024', 'Gran conferencia sobre las últimas tendencias en tecnología', 1, 2, 'hybrid', 0, '0.00', 'Centro de Convenciones Madrid', NULL, '2024-06-15 09:00:00', '2024-06-16 18:00:00', 500, 'published', NULL, NULL, NULL, NULL, NULL, '2025-03-02 17:46:13', '2025-03-02 17:46:13', NULL),
(2, 'RNR', 'Taller', NULL, 1, '', 20, '25.00', 'Cuenca', NULL, '2025-03-15 14:00:00', '2025-03-22 18:00:00', NULL, 'published', 2, NULL, NULL, NULL, NULL, '2025-03-02 19:30:36', '2025-03-02 19:30:36', NULL),
(4, 'RNR3', 'Networking', NULL, 4, '', 20, '25.00', 'Latacunga', NULL, '2025-03-16 18:52:00', '2025-03-23 18:52:00', NULL, 'published', 4, NULL, NULL, NULL, NULL, '2025-03-02 19:49:11', '2025-03-12 00:21:35', '{"enabled_methods":["3"]}'),
(6, 'Reunion de directivos', 'Reunion de directivos', NULL, 4, '', 100, '20.00', 'Salcedo', NULL, '2025-03-28 08:00:00', '2025-03-30 14:00:00', NULL, 'published', 6, NULL, NULL, NULL, NULL, '2025-03-10 18:06:29', '2025-03-12 00:36:50', '{"enabled_methods":["3"]}');

-- --------------------------------------------------------

--
-- Table structure for table `event_types`
--

CREATE TABLE `event_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_types`
--

INSERT INTO `event_types` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Conferencia', 'Eventos de presentación y discusión de temas específicos', '2025-03-02 17:46:13'),
(2, 'Taller', 'Sesiones prácticas de aprendizaje', '2025-03-02 17:46:13'),
(3, 'Seminario', 'Reuniones especializadas de naturaleza técnica o académica', '2025-03-02 17:46:13'),
(4, 'Congreso', 'Reuniones periódicas de profesionales del mismo campo', '2025-03-02 17:46:13'),
(5, 'Exposición', 'Muestras y exhibiciones de productos o servicios', '2025-03-02 17:46:13');

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `login_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_history`
--

INSERT INTO `login_history` (`id`, `user_id`, `login_time`, `ip_address`, `user_agent`) VALUES
(1, 1, '2025-03-02 18:46:24', '127.0.0.1', NULL),
(2, 1, '2025-03-02 18:46:56', '127.0.0.1', NULL),
(3, 1, '2025-03-02 18:51:31', '127.0.0.1', 'Unknown'),
(4, 2, '2025-03-02 19:08:51', '127.0.0.1', 'Unknown'),
(5, 3, '2025-03-02 19:09:51', '127.0.0.1', 'Unknown'),
(6, 1, '2025-03-02 19:22:23', '127.0.0.1', 'Unknown'),
(7, 2, '2025-03-02 19:31:20', '127.0.0.1', 'Unknown'),
(8, 1, '2025-03-02 19:33:23', '127.0.0.1', 'Unknown'),
(9, 2, '2025-03-02 19:36:39', '127.0.0.1', 'Unknown'),
(10, 1, '2025-03-02 19:38:45', '127.0.0.1', 'Unknown'),
(11, 1, '2025-03-02 19:39:45', '127.0.0.1', 'Unknown'),
(12, 1, '2025-03-02 19:45:24', '127.0.0.1', 'Unknown'),
(13, 4, '2025-03-02 19:47:59', '127.0.0.1', 'Unknown'),
(14, 1, '2025-03-02 19:50:02', '127.0.0.1', 'Unknown'),
(15, 2, '2025-03-02 19:50:25', '127.0.0.1', 'Unknown'),
(16, 1, '2025-03-02 19:51:07', '127.0.0.1', 'Unknown'),
(17, 4, '2025-03-02 20:05:25', '127.0.0.1', 'Unknown'),
(18, 1, '2025-03-02 20:05:56', '127.0.0.1', 'Unknown'),
(19, 1, '2025-03-02 20:14:42', '127.0.0.1', 'Unknown'),
(20, 4, '2025-03-02 20:32:57', '127.0.0.1', 'Unknown'),
(21, 3, '2025-03-02 20:59:58', '127.0.0.1', 'Unknown'),
(22, 1, '2025-03-02 21:08:15', '127.0.0.1', 'Unknown'),
(23, 1, '2025-03-02 21:54:47', '127.0.0.1', 'Unknown'),
(24, 4, '2025-03-02 21:55:12', '127.0.0.1', 'Unknown'),
(25, 3, '2025-03-02 21:55:58', '127.0.0.1', 'Unknown'),
(26, 4, '2025-03-02 22:03:35', '127.0.0.1', 'Unknown'),
(27, 4, '2025-03-02 22:14:07', '127.0.0.1', 'Unknown'),
(28, 3, '2025-03-02 22:19:55', '127.0.0.1', 'Unknown'),
(29, 3, '2025-03-02 22:34:08', '127.0.0.1', 'Unknown'),
(30, 4, '2025-03-02 22:36:53', '127.0.0.1', 'Unknown'),
(31, 3, '2025-03-02 22:43:12', '127.0.0.1', 'Unknown'),
(32, 3, '2025-03-02 22:53:01', '127.0.0.1', 'Unknown'),
(33, 3, '2025-03-02 23:00:50', '127.0.0.1', 'Unknown'),
(34, 1, '2025-03-02 23:08:16', '127.0.0.1', 'Unknown'),
(35, 4, '2025-03-02 23:10:06', '127.0.0.1', 'Unknown'),
(36, 3, '2025-03-02 23:10:47', '127.0.0.1', 'Unknown'),
(38, 1, '2025-03-03 01:38:12', '127.0.0.1', 'Unknown'),
(39, 2, '2025-03-03 01:40:41', '127.0.0.1', 'Unknown'),
(40, 3, '2025-03-03 01:41:31', '127.0.0.1', 'Unknown'),
(41, 3, '2025-03-03 01:47:54', '127.0.0.1', 'Unknown'),
(43, 3, '2025-03-03 02:03:26', '127.0.0.1', 'Unknown'),
(44, 3, '2025-03-03 02:09:49', '127.0.0.1', 'Unknown'),
(45, 1, '2025-03-03 22:02:45', '127.0.0.1', 'Unknown'),
(46, 3, '2025-03-03 22:13:34', '127.0.0.1', 'Unknown'),
(47, 3, '2025-03-03 22:17:56', '127.0.0.1', 'Unknown'),
(48, 3, '2025-03-03 23:57:02', '127.0.0.1', 'Unknown'),
(49, 4, '2025-03-04 00:28:35', '127.0.0.1', 'Unknown'),
(50, 1, '2025-03-04 00:44:47', '127.0.0.1', 'Unknown'),
(51, 3, '2025-03-04 00:45:50', '127.0.0.1', 'Unknown'),
(52, 1, '2025-03-04 00:47:54', '127.0.0.1', 'Unknown'),
(53, 3, '2025-03-04 00:52:20', '127.0.0.1', 'Unknown'),
(54, 4, '2025-03-04 00:54:38', '127.0.0.1', 'Unknown'),
(55, 3, '2025-03-04 00:55:36', '127.0.0.1', 'Unknown'),
(56, 1, '2025-03-04 00:56:17', '127.0.0.1', 'Unknown'),
(57, 3, '2025-03-04 01:01:15', '127.0.0.1', 'Unknown'),
(58, 4, '2025-03-04 01:03:21', '127.0.0.1', 'Unknown'),
(59, 3, '2025-03-04 01:09:20', '127.0.0.1', 'Unknown'),
(60, 4, '2025-03-10 16:47:40', '127.0.0.1', 'Unknown'),
(61, 3, '2025-03-10 16:54:45', '127.0.0.1', 'Unknown'),
(62, 4, '2025-03-10 16:56:21', '127.0.0.1', 'Unknown'),
(64, 4, '2025-03-10 18:08:15', '127.0.0.1', 'Unknown'),
(66, 4, '2025-03-10 20:32:38', '127.0.0.1', 'Unknown'),
(67, 3, '2025-03-10 20:33:20', '127.0.0.1', 'Unknown'),
(68, 1, '2025-03-11 13:11:17', '127.0.0.1', 'Unknown'),
(69, 3, '2025-03-11 13:30:46', '127.0.0.1', 'Unknown'),
(70, 4, '2025-03-11 13:33:14', '127.0.0.1', 'Unknown'),
(71, 3, '2025-03-11 13:34:51', '127.0.0.1', 'Unknown'),
(72, 4, '2025-03-11 13:39:22', '127.0.0.1', 'Unknown'),
(73, 3, '2025-03-11 14:57:28', '127.0.0.1', 'Unknown'),
(74, 4, '2025-03-11 14:59:51', '127.0.0.1', 'Unknown'),
(75, 3, '2025-03-11 15:00:16', '127.0.0.1', 'Unknown'),
(76, 3, '2025-03-11 15:02:39', '127.0.0.1', 'Unknown'),
(77, 4, '2025-03-11 15:03:07', '127.0.0.1', 'Unknown'),
(78, 4, '2025-03-11 21:12:45', '127.0.0.1', 'Unknown'),
(79, 1, '2025-03-11 21:21:28', '127.0.0.1', 'Unknown'),
(80, 1, '2025-03-11 21:48:54', '127.0.0.1', 'Unknown'),
(81, 1, '2025-03-11 21:54:03', '127.0.0.1', 'Unknown'),
(82, 1, '2025-03-11 23:14:34', '127.0.0.1', 'Unknown'),
(83, 9, '2025-03-11 23:15:49', '127.0.0.1', 'Unknown'),
(84, 4, '2025-03-11 23:34:55', '127.0.0.1', 'Unknown'),
(85, 3, '2025-03-12 00:32:32', '127.0.0.1', 'Unknown'),
(86, 9, '2025-03-12 00:35:51', '127.0.0.1', 'Unknown'),
(87, 4, '2025-03-12 00:36:25', '127.0.0.1', 'Unknown'),
(88, 10, '2025-03-12 00:50:33', '127.0.0.1', 'Unknown'),
(89, 4, '2025-03-12 01:10:03', '127.0.0.1', 'Unknown'),
(90, 10, '2025-03-12 01:11:02', '127.0.0.1', 'Unknown'),
(91, 3, '2025-03-12 01:30:49', '127.0.0.1', 'Unknown'),
(92, 4, '2025-03-12 01:31:19', '127.0.0.1', 'Unknown'),
(93, 10, '2025-03-12 01:33:07', '127.0.0.1', 'Unknown'),
(94, 4, '2025-03-12 01:34:45', '127.0.0.1', 'Unknown'),
(95, 10, '2025-03-12 01:38:31', '127.0.0.1', 'Unknown'),
(96, 4, '2025-03-12 02:00:44', '127.0.0.1', 'Unknown'),
(97, 10, '2025-03-12 02:03:33', '127.0.0.1', 'Unknown'),
(98, 4, '2025-03-12 14:35:39', '127.0.0.1', 'Unknown'),
(99, 9, '2025-03-12 14:46:52', '127.0.0.1', 'Unknown'),
(100, 4, '2025-03-12 14:49:48', '127.0.0.1', 'Unknown'),
(101, 9, '2025-03-12 14:50:26', '127.0.0.1', 'Unknown');

-- --------------------------------------------------------

--
-- Table structure for table `participant_categories`
--

CREATE TABLE `participant_categories` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `capacity` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `participant_categories`
--

INSERT INTO `participant_categories` (`id`, `event_id`, `name`, `description`, `price`, `capacity`, `created_at`) VALUES
(1, 1, 'General', 'Acceso a todas las conferencias', '99.99', 300, '2025-03-02 17:46:13'),
(2, 1, 'VIP', 'Acceso preferencial y materiales exclusivos', '199.99', 100, '2025-03-02 17:46:13'),
(3, 1, 'Estudiante', 'Tarifa especial para estudiantes', '49.99', 100, '2025-03-02 17:46:13'),
(4, 4, 'General', 'Acceso general al evento', '25.00', 15, '2025-03-03 23:19:54'),
(5, 4, 'Miembro IEEE', 'Tarifa especial para miembros IEEE', '20.00', 5, '2025-03-03 23:19:54');

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `registration_id` int(11) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `transaction_id` varchar(255) DEFAULT NULL,
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payments`
--

INSERT INTO `payments` (`id`, `registration_id`, `payment_method_id`, `amount`, `status`, `transaction_id`, `payment_date`, `created_at`) VALUES
(2, 9, 3, '20.00', '', NULL, '2025-03-03 23:32:35', '2025-03-03 23:24:18'),
(3, 11, 3, '20.00', '', NULL, '2025-03-03 23:43:19', '2025-03-03 23:42:58'),
(4, 12, 1, '20.00', '', NULL, '2025-03-03 23:44:14', '2025-03-03 23:44:06'),
(5, 13, 3, '20.00', '', NULL, '2025-03-04 00:18:39', '2025-03-03 23:57:30'),
(6, 14, 3, '20.00', '', NULL, '2025-03-04 00:24:28', '2025-03-04 00:19:09'),
(7, 15, 3, '20.00', '', 'receipt_67c6491779e52.pdf', '2025-03-04 00:52:53', '2025-03-04 00:24:49'),
(8, 16, 3, '20.00', '', 'receipt_67c64f3306c1a.pdf', '2025-03-04 01:01:31', '2025-03-04 00:53:31'),
(9, 17, 3, '25.00', '', 'receipt_67c65135ad6de.pdf', '2025-03-04 01:09:36', '2025-03-04 01:02:14'),
(10, 18, 1, '20.00', '', NULL, '2025-03-04 01:10:13', '2025-03-04 01:09:52'),
(11, 19, 3, '20.00', '', 'receipt_67cf19a007c1a.pdf', '2025-03-11 13:35:32', '2025-03-10 16:55:33'),
(12, 20, 3, '20.00', '', 'receipt_67d03ceeb82df.pdf', '2025-03-11 14:58:01', '2025-03-11 13:38:31'),
(13, 21, 3, '20.00', 'pending', 'receipt_67d04fa511d87.pdf', '2025-03-11 14:58:45', '2025-03-11 14:58:28'),
(14, 25, 3, '100.00', '', NULL, '2025-03-12 02:03:40', '2025-03-12 01:58:19'),
(15, 26, 3, '100.00', '', NULL, '2025-03-12 02:04:11', '2025-03-12 02:04:03'),
(16, 27, 3, '100.00', '', NULL, '2025-03-12 02:10:30', '2025-03-12 02:10:19'),
(17, 28, 3, '100.00', '', NULL, '2025-03-12 13:08:20', '2025-03-12 13:08:14'),
(18, 29, 3, '100.00', 'pending', 'receipt_67d19b85cbc56.pdf', '2025-03-12 14:34:45', '2025-03-12 13:15:54');

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `type` enum('online','transfer') NOT NULL DEFAULT 'online',
  `provider` varchar(100) DEFAULT NULL,
  `config` longtext CHARACTER SET utf8mb4 COLLATE=utf8mb4_bin DEFAULT NULL CHECK (json_valid(`config`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `payment_methods`
--

INSERT INTO `payment_methods` (`id`, `name`, `description`, `is_active`, `created_at`, `type`, `provider`, `config`) VALUES
(1, 'PayPal', 'Pago en línea a través de PayPal', 1, '2025-03-02 21:54:04', 'online', 'paypal', '{"client_id": "", "client_secret": ""}'),
(2, 'Stripe', 'Pago en línea a través de Stripe', 1, '2025-03-02 21:54:04', 'online', 'stripe', '{"publishable_key": "", "secret_key": ""}'),
(3, 'Transferencia Bancaria', 'Pago mediante transferencia bancaria', 1, '2025-03-02 21:54:04', 'transfer', 'bank', '{"bank_name": "Banco Nacional", "account_number": "123456789", "account_holder": "EventManager Inc."}');

-- --------------------------------------------------------

--
-- Table structure for table `pricing_categories`
--

CREATE TABLE `pricing_categories` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` text DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `discount_percentage` decimal(5,2) DEFAULT 0.00,
  `ieee_member_discount` decimal(5,2) DEFAULT 0.00,
  `ieee_region` varchar(10) DEFAULT NULL,
  `max_capacity` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pricing_categories`
--

INSERT INTO `pricing_categories` (`id`, `event_id`, `name`, `price`, `description`, `capacity`, `is_active`, `created_at`, `discount_percentage`, `ieee_member_discount`, `ieee_region`, `max_capacity`) VALUES
(1, 1, 'General', '0.00', 'Entrada general al evento', 0, 1, '2025-03-02 21:52:50', '0.00', '0.00', NULL, NULL),
(2, 2, 'General', '0.00', 'Entrada general al evento', 20, 1, '2025-03-02 21:52:50', '0.00', '0.00', NULL, NULL),
(4, 1, 'General', '0.00', 'Entrada general al evento', 0, 1, '2025-03-02 21:53:26', '0.00', '0.00', NULL, NULL),
(5, 2, 'General', '0.00', 'Entrada general al evento', 20, 1, '2025-03-02 21:53:26', '0.00', '0.00', NULL, NULL),
(7, 1, 'General', '0.00', 'Entrada general al evento', 0, 1, '2025-03-02 21:54:04', '0.00', '0.00', NULL, NULL),
(8, 2, 'General', '0.00', 'Entrada general al evento', 20, 1, '2025-03-02 21:54:04', '0.00', '0.00', NULL, NULL),
(9, 4, 'General', '30.00', 'Entrada general al evento', 20, 1, '2025-03-02 21:54:04', '10.00', '0.00', '1', 10),
(10, 4, 'Miembro IEEE', '20.00', 'Miembro', 10, 1, '2025-03-02 22:18:22', '10.00', '10.00', '1', 20),
(11, 6, 'Becas', '20.00', 'Miembros Becados', NULL, 1, '2025-03-12 00:37:27', '0.00', '15.00', '9', 10),
(12, 6, 'Miembros IEEE', '100.00', 'MIembros', NULL, 1, '2025-03-12 00:47:57', '0.00', '10.00', '9', 80),
(13, 6, 'General', '120.00', 'No Miembro IEEE', NULL, 1, '2025-03-12 00:48:25', '0.00', '0.00', '', 10);

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `registration_code` varchar(50) NOT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled','attended') DEFAULT 'pending',
  `payment_status` enum('pending','completed','refunded') DEFAULT 'pending',
  `payment_amount` decimal(10,2) NOT NULL,
  `comments` text DEFAULT NULL,
  `payment_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pricing_category_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `registrations`
--

INSERT INTO `registrations` (`id`, `event_id`, `user_id`, `registration_code`, `qr_code`, `status`, `payment_status`, `payment_amount`, `comments`, `payment_date`, `created_at`, `updated_at`, `pricing_category_id`) VALUES
(9, 4, 3, 'REG67C63A22404B4', NULL, 'cancelled', '', '20.00', NULL, NULL, '2025-03-03 23:24:18', '2025-03-03 23:32:35', NULL),
(10, 4, 3, 'REG67C63C59C8F9B', NULL, 'cancelled', '', '20.00', NULL, NULL, '2025-03-03 23:33:45', '2025-03-03 23:42:28', NULL),
(11, 4, 3, 'REG67C63E82BED72', NULL, 'cancelled', '', '20.00', NULL, NULL, '2025-03-03 23:42:58', '2025-03-03 23:43:19', NULL),
(12, 4, 3, 'REG67C63EC6AC45B', NULL, 'cancelled', '', '20.00', NULL, NULL, '2025-03-03 23:44:06', '2025-03-03 23:44:14', NULL),
(13, 4, 3, 'REG67C641EA8FA29', NULL, 'cancelled', '', '20.00', NULL, NULL, '2025-03-03 23:57:30', '2025-03-04 00:18:39', NULL),
(14, 4, 3, 'REG67C646FD8E79E', NULL, 'cancelled', '', '20.00', NULL, NULL, '2025-03-04 00:19:09', '2025-03-04 00:24:28', NULL),
(15, 4, 3, 'REG67C64851BE84F', NULL, 'cancelled', '', '20.00', NULL, NULL, '2025-03-04 00:24:49', '2025-03-04 00:52:53', NULL),
(16, 4, 3, 'REG67C64F0B1B8EA', NULL, 'cancelled', '', '20.00', NULL, NULL, '2025-03-04 00:53:31', '2025-03-04 01:01:31', NULL),
(17, 4, 3, 'REG67C6511699977', NULL, 'cancelled', '', '25.00', NULL, NULL, '2025-03-04 01:02:14', '2025-03-04 01:09:36', NULL),
(18, 4, 3, 'REG67C652E0578B5', NULL, 'cancelled', '', '20.00', NULL, NULL, '2025-03-04 01:09:52', '2025-03-04 01:10:13', NULL),
(19, 4, 3, 'REG67CF19850B484', NULL, 'cancelled', '', '20.00', NULL, NULL, '2025-03-10 16:55:33', '2025-03-11 13:35:32', NULL),
(20, 4, 3, 'REG67D03CD7B7C2D', NULL, 'cancelled', '', '20.00', NULL, NULL, '2025-03-11 13:38:31', '2025-03-11 14:58:01', NULL),
(21, 4, 3, 'REG67D04F94A90F2', NULL, 'confirmed', 'pending', '20.00', NULL, NULL, '2025-03-11 14:58:28', '2025-03-11 15:00:02', NULL),
(25, 6, 10, 'REG67D0EA3BEC36A', NULL, 'cancelled', '', '100.00', 'Miembro', NULL, '2025-03-12 01:58:19', '2025-03-12 02:03:40', 12),
(26, 6, 10, 'REG67D0EB938B01C', NULL, 'cancelled', '', '100.00', 'Miembro', NULL, '2025-03-12 02:04:03', '2025-03-12 02:04:11', 12),
(27, 6, 10, 'REG67D0ED0BAB35E', NULL, 'cancelled', '', '100.00', 'Miembro', NULL, '2025-03-12 02:10:19', '2025-03-12 02:10:30', 12),
(28, 6, 10, 'REG67D1873E77FD6', NULL, 'cancelled', '', '100.00', 'Miembro', NULL, '2025-03-12 13:08:14', '2025-03-12 13:08:20', 12),
(29, 6, 10, 'REG67D1890A5FC27', NULL, 'pending', 'pending', '100.00', 'Miembro IEEE', NULL, '2025-03-12 13:15:54', '2025-03-12 13:15:54', 12);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `setting_description`, `is_public`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'EventManager', 'Nombre del sitio web', 1, '2025-03-02 17:46:13', '2025-03-02 17:46:13'),
(2, 'site_description', 'Plataforma integral para la gestión de eventos', 'Descripción del sitio web', 1, '2025-03-02 17:46:13', '2025-03-02 17:46:13'),
(3, 'contact_email', 'contact@eventmanager.com', 'Email de contacto principal', 1, '2025-03-02 17:46:13', '2025-03-02 17:46:13'),
(4, 'smtp_host', 'smtp.gmail.com', 'Servidor SMTP para envío de correos', 0, '2025-03-02 17:46:13', '2025-03-02 17:46:13'),
(5, 'smtp_port', '587', 'Puerto del servidor SMTP', 0, '2025-03-02 17:46:13', '2025-03-02 17:46:13'),
(6, 'smtp_user', 'your-email@gmail.com', 'Usuario SMTP', 0, '2025-03-02 17:46:13', '2025-03-02 17:46:13'),
(7, 'smtp_password', 'your-password', 'Contraseña SMTP', 0, '2025-03-02 17:46:13', '2025-03-02 17:46:13'),
(8, 'currency', 'USD', 'Moneda predeterminada', 1, '2025-03-02 17:46:13', '2025-03-02 20:32:15'),
(9, 'timezone', 'Quito/Ecuador', 'Zona horaria del sistema', 1, '2025-03-02 17:46:13', '2025-03-02 20:32:15'),
(10, 'max_registration_limit', '1000', 'Límite máximo de registros por evento', 0, '2025-03-02 17:46:13', '2025-03-02 17:46:13'),
(11, 'enable_waitlist', 'true', 'Habilitar lista de espera cuando se alcanza el límite', 1, '2025-03-02 17:46:13', '2025-03-02 17:46:13');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('user','organizer','admin') DEFAULT 'user',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `is_active` tinyint(1) DEFAULT 1,
  `avatar_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `ieee_member` tinyint(1) DEFAULT 0,
  `ieee_member_id` varchar(50) DEFAULT NULL,
  `ieee_region` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `is_active`, `avatar_url`, `created_at`, `updated_at`, `ieee_member`, `ieee_member_id`, `ieee_region`) VALUES
(1, 'Administrador', 'admin@eventmanager.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', 1, NULL, '2025-03-02 17:46:13', '2025-03-02 17:46:13', 0, NULL, NULL),
(2, 'Organizador Demo', 'organizador@eventmanager.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'organizer', 'active', 1, NULL, '2025-03-02 17:46:13', '2025-03-02 17:46:13', 0, NULL, NULL),
(3, 'Usuario Demo', 'usuario@eventmanager.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'active', 1, NULL, '2025-03-02 17:46:13', '2025-03-02 17:46:13', 0, NULL, NULL),
(4, 'Marcelo Alvarez', 'mralvarezv@gmail.com', '$2y$10$XzFdqOKvZ8CWfeTSYjhhN.JgZayCJYWGzl6keZTxbOc9YUlvQD3QK', 'organizer', 'active', 1, NULL, '2025-03-02 19:47:31', '2025-03-02 19:47:31', 0, NULL, NULL),
(9, 'Marcelo Alvarez', 'rmalvarez@espe.edu.ec', '$2y$10$IM6bGcP2u6cNLkLFBlMFQ.H8hNVS1NVDnVT2BtsOpn/xSF2Jd5zta', 'admin', 'active', 1, NULL, '2025-03-11 23:15:27', '2025-03-11 23:15:27', 0, NULL, NULL),
(10, 'Rosi Granizo', 'ragranizo@ieee.org', '$2y$10$B4n.XqjAt/YGWpQTioKhp.vrk1idJTe6LxYPQrhd/.fs08ZMvdTbS', 'user', 'active', 1, NULL, '2025-03-12 00:50:14', '2025-03-12 00:50:14', 1, '12345678', NULL);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_event_stats`
-- (See below for the actual view)
--
CREATE TABLE `view_event_stats` (
`id` int(11)
,`title` varchar(255)
,`status` enum('draft','published','completed','cancelled')
,`total_registrations` bigint(21)
,`total_revenue` decimal(32,2)
,`max_capacity` int(11)
,`confirmed_registrations` bigint(21)
,`total_attendees` bigint(21)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_organizer_stats`
-- (See below for the actual view)
--
CREATE TABLE `view_organizer_stats` (
`organizer_id` int(11)
,`organizer_name` varchar(100)
,`total_events` bigint(21)
,`total_revenue` decimal(32,2)
,`total_registrations` bigint(21)
);

-- --------------------------------------------------------

--
-- Structure for view `view_event_stats`
--
DROP TABLE IF EXISTS `view_event_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_event_stats`  AS SELECT `e`.`id` AS `id`, `e`.`title` AS `title`, `e`.`status` AS `status`, count(distinct `r`.`id`) AS `total_registrations`, sum(`r`.`payment_amount`) AS `total_revenue`, `e`.`max_capacity` AS `max_capacity`, count(distinct case when `r`.`status` = 'confirmed' then `r`.`id` end) AS `confirmed_registrations`, count(distinct case when `r`.`status` = 'attended' then `r`.`id` end) AS `total_attendees` FROM (`events` `e` left join `registrations` `r` on(`e`.`id` = `r`.`event_id`)) GROUP BY `e`.`id``id`  ;

-- --------------------------------------------------------

--
-- Structure for view `view_organizer_stats`
--
DROP TABLE IF EXISTS `view_organizer_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_organizer_stats`  AS SELECT `u`.`id` AS `organizer_id`, `u`.`name` AS `organizer_name`, count(distinct `e`.`id`) AS `total_events`, sum(`r`.`payment_amount`) AS `total_revenue`, count(distinct `r`.`id`) AS `total_registrations` FROM ((`users` `u` left join `events` `e` on(`u`.`id` = `e`.`organizer_id`)) left join `registrations` `r` on(`e`.`id` = `r`.`event_id`)) WHERE `u`.`role` = 'organizer' GROUP BY `u`.`id``id`  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `type_id` (`type_id`),
  ADD KEY `organizer_id` (`organizer_id`),
  ADD KEY `idx_events_status` (`status`),
  ADD KEY `idx_events_start_date` (`start_date`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `event_types`
--
ALTER TABLE `event_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `participant_categories`
--
ALTER TABLE `participant_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `registration_id` (`registration_id`),
  ADD KEY `payment_method_id` (`payment_method_id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pricing_categories`
--
ALTER TABLE `pricing_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pricing_categories_event` (`event_id`),
  ADD KEY `idx_pricing_categories_dates` (`created_at`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `registration_code` (`registration_code`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_registrations_status` (`status`),
  ADD KEY `idx_pricing_category` (`pricing_category_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`),
  ADD KEY `idx_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `event_types`
--
ALTER TABLE `event_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `participant_categories`
--
ALTER TABLE `participant_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `pricing_categories`
--
ALTER TABLE `pricing_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`type_id`) REFERENCES `event_types` (`id`),
  ADD CONSTRAINT `events_ibfk_2` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `events_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `events_ibfk_4` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `participant_categories`
--
ALTER TABLE `participant_categories`
  ADD CONSTRAINT `participant_categories_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`registration_id`) REFERENCES `registrations` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`);

--
-- Constraints for table `pricing_categories`
--
ALTER TABLE `pricing_categories`
  ADD CONSTRAINT `pricing_categories_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`);

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `registrations_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`),
  ADD CONSTRAINT `registrations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `registrations_ibfk_4` FOREIGN KEY (`pricing_category_id`) REFERENCES `pricing_categories` (`id`),
  ADD CONSTRAINT `registrations_pricing_category_fk` FOREIGN KEY (`pricing_category_id`) REFERENCES `pricing_categories` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */; 