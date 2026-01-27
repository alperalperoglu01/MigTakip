-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Anamakine: localhost:3306
-- Üretim Zamanı: 27 Oca 2026, 01:30:29
-- Sunucu sürümü: 10.6.19-MariaDB
-- PHP Sürümü: 7.4.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `sesmotors_kurye`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `bonus_tiers`
--

CREATE TABLE `bonus_tiers` (
  `id` int(11) NOT NULL,
  `min_value` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `label` varchar(60) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `bonus_tiers`
--

INSERT INTO `bonus_tiers` (`id`, `min_value`, `amount`, `label`, `active`) VALUES
(1, 0, 0.00, '0+', 1),
(2, 700, 12800.00, '700-799 (düzenle)', 1),
(3, 800, 20224.00, '800-999 (düzenle)', 1),
(4, 1000, 33408.00, '1000-1199', 1),
(5, 1200, 40300.00, '1200-1399', 1),
(6, 1400, 47970.00, '1400+', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL,
  `hourly_rate` decimal(10,2) NOT NULL DEFAULT 177.00,
  `default_daily_hours` decimal(10,2) NOT NULL DEFAULT 12.00,
  `overtime_hourly_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `overtime_rate` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `company_settings`
--

INSERT INTO `company_settings` (`id`, `hourly_rate`, `default_daily_hours`, `overtime_hourly_rate`, `overtime_rate`) VALUES
(1, 177.00, 12.00, 0.00, 0.00);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category` varchar(20) NOT NULL,
  `subject` varchar(150) NOT NULL,
  `message` text NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `contact_replies`
--

CREATE TABLE `contact_replies` (
  `id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `sender_type` varchar(10) NOT NULL,
  `sender_user_id` int(11) DEFAULT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `day_entries`
--

CREATE TABLE `day_entries` (
  `id` int(11) NOT NULL,
  `month_id` int(11) NOT NULL,
  `day` tinyint(4) NOT NULL,
  `status` enum('WORK','LEAVE','SICK','ANNUAL','OFF') NOT NULL DEFAULT 'OFF',
  `packages` int(11) NOT NULL DEFAULT 0,
  `overtime_hours` decimal(10,2) NOT NULL DEFAULT 0.00,
  `note` varchar(255) DEFAULT NULL,
  `motor_type` enum('own','rental') NOT NULL DEFAULT 'own',
  `motor_rent_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `motor_rent_daily` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `months`
--

CREATE TABLE `months` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ym` char(7) NOT NULL,
  `daily_hours` decimal(10,2) NOT NULL,
  `hourly_rate` decimal(10,2) NOT NULL,
  `locked_daily_hours` decimal(10,2) NOT NULL,
  `locked_hourly_rate` decimal(10,2) NOT NULL,
  `locked_overtime_hourly_rate` decimal(10,2) NOT NULL DEFAULT 0.00,
  `locked_help_fund` decimal(10,2) NOT NULL DEFAULT 250.00,
  `locked_franchise_fee` decimal(10,2) NOT NULL DEFAULT 1000.00,
  `locked_tevkifat_rate` decimal(5,2) NOT NULL DEFAULT 5.00,
  `is_closed` tinyint(1) NOT NULL DEFAULT 0,
  `target_packages` int(11) DEFAULT NULL,
  `remaining_days` int(11) DEFAULT NULL,
  `fuel_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `penalty_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `other_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `advance_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `tevkifat_rate` decimal(5,2) DEFAULT 5.00,
  `motor_full_month` tinyint(1) NOT NULL DEFAULT 1,
  `motor_rental_days` int(11) NOT NULL DEFAULT 0,
  `locked_accounting_fee` decimal(10,2) DEFAULT NULL,
  `locked_motor_default_type` varchar(10) DEFAULT NULL,
  `locked_motor_monthly_rent` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `months`
--

INSERT INTO `months` (`id`, `user_id`, `ym`, `daily_hours`, `hourly_rate`, `locked_daily_hours`, `locked_hourly_rate`, `locked_overtime_hourly_rate`, `locked_help_fund`, `locked_franchise_fee`, `locked_tevkifat_rate`, `is_closed`, `target_packages`, `remaining_days`, `fuel_cost`, `penalty_cost`, `other_cost`, `advance_amount`, `created_at`, `tevkifat_rate`, `motor_full_month`, `motor_rental_days`, `locked_accounting_fee`, `locked_motor_default_type`, `locked_motor_monthly_rent`) VALUES
(1, 1, '2026-01', 12.00, 177.00, 12.00, 177.00, 0.00, 250.00, 1000.00, 5.00, 0, NULL, NULL, 0.00, 0.00, 0.00, 0.00, '2026-01-26 19:24:42', 5.00, 1, 0, NULL, NULL, NULL),
(2, 1, '2026-02', 12.00, 177.00, 12.00, 177.00, 0.00, 250.00, 1000.00, 5.00, 0, NULL, NULL, 0.00, 0.00, 0.00, 0.00, '2026-01-26 19:24:42', 5.00, 1, 0, NULL, NULL, NULL),
(3, 1, '2026-03', 12.00, 177.00, 12.00, 177.00, 0.00, 250.00, 1000.00, 5.00, 0, NULL, NULL, 0.00, 0.00, 0.00, 0.00, '2026-01-26 19:24:42', 5.00, 1, 0, NULL, NULL, NULL),
(4, 1, '2026-04', 12.00, 177.00, 12.00, 177.00, 0.00, 250.00, 1000.00, 5.00, 0, NULL, NULL, 0.00, 0.00, 0.00, 0.00, '2026-01-26 19:24:42', 5.00, 1, 0, NULL, NULL, NULL),
(5, 1, '2026-05', 12.00, 177.00, 12.00, 177.00, 0.00, 250.00, 1000.00, 5.00, 0, NULL, NULL, 0.00, 0.00, 0.00, 0.00, '2026-01-26 19:24:42', 5.00, 1, 0, NULL, NULL, NULL),
(6, 1, '2026-06', 12.00, 177.00, 12.00, 177.00, 0.00, 250.00, 1000.00, 5.00, 0, NULL, NULL, 0.00, 0.00, 0.00, 0.00, '2026-01-26 19:24:42', 5.00, 1, 0, NULL, NULL, NULL),
(7, 1, '2026-07', 12.00, 177.00, 12.00, 177.00, 0.00, 250.00, 1000.00, 5.00, 0, NULL, NULL, 0.00, 0.00, 0.00, 0.00, '2026-01-26 19:24:42', 5.00, 1, 0, NULL, NULL, NULL),
(8, 1, '2026-08', 12.00, 177.00, 12.00, 177.00, 0.00, 250.00, 1000.00, 5.00, 0, NULL, NULL, 0.00, 0.00, 0.00, 0.00, '2026-01-26 19:24:42', 5.00, 1, 0, NULL, NULL, NULL),
(9, 1, '2026-09', 12.00, 177.00, 12.00, 177.00, 0.00, 250.00, 1000.00, 5.00, 0, NULL, NULL, 0.00, 0.00, 0.00, 0.00, '2026-01-26 19:24:42', 5.00, 1, 0, NULL, NULL, NULL),
(10, 1, '2026-10', 12.00, 177.00, 12.00, 177.00, 0.00, 250.00, 1000.00, 5.00, 0, NULL, NULL, 0.00, 0.00, 0.00, 0.00, '2026-01-26 19:24:42', 5.00, 1, 0, NULL, NULL, NULL),
(11, 1, '2026-11', 12.00, 177.00, 12.00, 177.00, 0.00, 250.00, 1000.00, 5.00, 0, NULL, NULL, 0.00, 0.00, 0.00, 0.00, '2026-01-26 19:24:42', 5.00, 1, 0, NULL, NULL, NULL),
(12, 1, '2026-12', 12.00, 177.00, 12.00, 177.00, 0.00, 250.00, 1000.00, 5.00, 0, NULL, NULL, 0.00, 0.00, 0.00, 0.00, '2026-01-26 19:24:42', 5.00, 1, 0, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `prime_tiers`
--

CREATE TABLE `prime_tiers` (
  `id` int(11) NOT NULL,
  `min_value` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `label` varchar(60) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `prime_tiers`
--

INSERT INTO `prime_tiers` (`id`, `min_value`, `amount`, `label`, `active`) VALUES
(1, 0, 0.00, '0-17', 1),
(2, 18, 250.00, '18-23', 1),
(3, 24, 430.00, '24-27', 1),
(4, 28, 645.00, '28-32', 1),
(5, 33, 1010.00, '33-37', 1),
(6, 38, 1595.00, '38-42', 1),
(7, 43, 2040.00, '43-48', 1),
(8, 49, 2500.00, '49+', 1);

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `email` varchar(190) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','chef','user') NOT NULL DEFAULT 'user',
  `courier_class` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `seniority_start_date` date DEFAULT NULL,
  `accounting_fee` decimal(10,2) NOT NULL DEFAULT 0.00,
  `default_motor_type` varchar(10) DEFAULT 'own',
  `default_motor_rent_monthly` decimal(10,2) DEFAULT 0.00,
  `motor_default_type` enum('own','rental') NOT NULL DEFAULT 'own',
  `motor_monthly_rent` decimal(10,2) NOT NULL DEFAULT 0.00,
  `motor_plate` varchar(20) DEFAULT NULL,
  `first_login_done` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Tablo döküm verisi `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `password_hash`, `role`, `courier_class`, `start_date`, `seniority_start_date`, `accounting_fee`, `default_motor_type`, `default_motor_rent_monthly`, `motor_default_type`, `motor_monthly_rent`, `motor_plate`, `first_login_done`, `created_at`) VALUES
(1, 'Admin', 'admin@example.com', '+90 542 130 5701', '$2b$12$RhKqIvtaTd1782EaUgDYXuif6tq69t6V9P52SA05L4vQjlGMU/zY.', 'admin', NULL, NULL, NULL, 5000.00, 'own', 0.00, 'own', 20000.00, '', 1, '2026-01-26 10:43:27');

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `bonus_tiers`
--
ALTER TABLE `bonus_tiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_bonus_min` (`min_value`);

--
-- Tablo için indeksler `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`);

--
-- Tablo için indeksler `contact_replies`
--
ALTER TABLE `contact_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contact` (`contact_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `contact_replies`
--
ALTER TABLE `contact_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
