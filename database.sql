-- =============================================================================
-- SISTEM INFORMASI CAPAIAN PEMBELAJARAN LULUSAN (SI-CPL)
-- UNIVERSITAS ISLAM NEGERI SUNAN AMPEL SURABAYA
-- Database Schema & Initial Data (MySQL 5.5+ / MariaDB 10.x / cPanel phpMyAdmin)
-- =============================================================================

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+07:00";

-- --------------------------------------------------------
-- 1. TABEL: fakultas
-- --------------------------------------------------------
DROP TABLE IF EXISTS `fakultas`;
CREATE TABLE `fakultas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `fakultas` (`id`, `code`, `name`, `created_at`) VALUES
(1, 'FST', 'Fakultas Sains dan Teknologi', NOW()),
(2, 'FTK', 'Fakultas Tarbiyah dan Keguruan', NOW()),
(3, 'FEBI', 'Fakultas Ekonomi dan Bisnis Islam', NOW());

-- --------------------------------------------------------
-- 2. TABEL: prodi
-- --------------------------------------------------------
DROP TABLE IF EXISTS `prodi`;
CREATE TABLE `prodi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fakultas_id` int(11) NOT NULL,
  `code` varchar(20) NOT NULL,
  `name` varchar(150) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fakultas_id` (`fakultas_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `prodi` (`id`, `fakultas_id`, `code`, `name`, `created_at`) VALUES
(1, 1, 'TI', 'Teknik Informatika', NOW()),
(2, 1, 'SI', 'Sistem Informasi', NOW()),
(3, 2, 'PAI', 'Pendidikan Agama Islam', NOW()),
(4, 3, 'ES', 'Ekonomi Syariah', NOW());

-- --------------------------------------------------------
-- 3. TABEL: matakuliah
-- --------------------------------------------------------
DROP TABLE IF EXISTS `matakuliah`;
CREATE TABLE `matakuliah` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fakultas_id` int(11) NOT NULL,
  `prodi_id` int(11) NOT NULL,
  `code` varchar(30) NOT NULL,
  `name` varchar(200) NOT NULL,
  `sks` int(11) NOT NULL DEFAULT 3,
  `cpl_codes` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `prodi_id` (`prodi_id`),
  KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `matakuliah` (`id`, `fakultas_id`, `prodi_id`, `code`, `name`, `sks`, `cpl_codes`, `created_at`) VALUES
(1, 1, 1, 'TI301', 'Pemrograman Web', 3, '["CPL-S1","CPL-P1","CPL-KU1","CPL-KK1","CPL-KK2"]', NOW()),
(2, 1, 1, 'TI202', 'Basis Data', 3, '["CPL-P1","CPL-P2","CPL-KU1","CPL-KK1"]', NOW()),
(3, 1, 2, 'SI210', 'Analisis Sistem Informasi', 3, '["CPL-S2","CPL-P3","CPL-KU2","CPL-KK1","CPL-KK3"]', NOW()),
(4, 2, 3, 'PAI101', 'Ilmu Pendidikan Islam', 2, '["CPL-S1","CPL-S2","CPL-S3","CPL-P4","CPL-KU1"]', NOW());

-- --------------------------------------------------------
-- 4. TABEL: users
-- --------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(150) NOT NULL,
  `role` enum('admin','kaprodi','dosen') NOT NULL DEFAULT 'dosen',
  `fakultas_id` int(11) DEFAULT NULL,
  `prodi_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Password bawaan:
-- imtinan.fakhri@gmail.com => admin123  (sha1: f865b53623b121fd34ee5426c792e5c33af8c227)
-- kaprodi.ti@uinsa.ac.id   => kaprodi123(sha1: b97bb1f096aa0e95ff71c6d9a19c5c935ee5ea76)
-- dosen.ti@uinsa.ac.id     => dosen123  (sha1: 6734d740b79fd7f6775f0a719bfecb8723ad6f7f)
INSERT INTO `users` (`id`, `email`, `password_hash`, `name`, `role`, `fakultas_id`, `prodi_id`, `is_active`, `created_at`) VALUES
(1, 'imtinan.fakhri@gmail.com', '$2y$10$wO0K4wM0l6i2LdGfFqjDte027QjP1aZ8kYxVj/g89mXl0WzM0eT9y', 'Administrator UINSA', 'admin', NULL, NULL, 1, NOW()),
(2, 'kaprodi.ti@uinsa.ac.id', '$2y$10$wO0K4wM0l6i2LdGfFqjDte027QjP1aZ8kYxVj/g89mXl0WzM0eT9y', 'Kaprodi Teknik Informatika', 'kaprodi', 1, 1, 1, NOW()),
(3, 'dosen.ti@uinsa.ac.id', '$2y$10$wO0K4wM0l6i2LdGfFqjDte027QjP1aZ8kYxVj/g89mXl0WzM0eT9y', 'Dosen Teknik Informatika', 'dosen', 1, 1, 1, NOW());

-- --------------------------------------------------------
-- 5. TABEL: grades (Nilai Capaian CPL)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `grades`;
CREATE TABLE `grades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fakultas_id` int(11) NOT NULL,
  `prodi_id` int(11) NOT NULL,
  `mata_kuliah_id` int(11) NOT NULL,
  `tahun_akademik` varchar(30) NOT NULL,
  `semester_type` enum('Ganjil','Genap') NOT NULL DEFAULT 'Ganjil',
  `kelas` varchar(10) NOT NULL DEFAULT 'A',
  `semester_number` varchar(10) NOT NULL DEFAULT 'I',
  `scores_json` text NOT NULL,
  `cat_avg_json` text NOT NULL,
  `total_score` decimal(5,2) DEFAULT NULL,
  `grade` varchar(5) DEFAULT '-',
  `predicate` varchar(100) DEFAULT '-',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mata_kuliah_id` (`mata_kuliah_id`),
  KEY `prodi_id` (`prodi_id`),
  KEY `tahun_akademik` (`tahun_akademik`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `grades` (`id`, `fakultas_id`, `prodi_id`, `mata_kuliah_id`, `tahun_akademik`, `semester_type`, `kelas`, `semester_number`, `scores_json`, `cat_avg_json`, `total_score`, `grade`, `predicate`, `created_at`) VALUES
(1, 1, 1, 1, '2024-2025', 'Ganjil', 'A', 'V', '{"CPL-S1":92,"CPL-P1":90,"CPL-KU1":93,"CPL-KK1":95,"CPL-KK2":91}', '{"sikap":92,"pengetahuan":90,"keterampilan_umum":93,"keterampilan_khusus":93}', 92.22, 'A', 'Sangat Memuaskan', NOW()),
(2, 1, 1, 2, '2024-2025', 'Ganjil', 'B', 'III', '{"CPL-P1":86,"CPL-P2":84,"CPL-KU1":88,"CPL-KK1":85}', '{"sikap":null,"pengetahuan":85,"keterampilan_umum":88,"keterampilan_khusus":85}', 85.88, 'A', 'Sangat Memuaskan', NOW()),
(3, 1, 2, 3, '2024-2025', 'Genap', 'A', 'IV', '{"CPL-S2":88,"CPL-P3":82,"CPL-KU2":85,"CPL-KK1":80,"CPL-KK3":83}', '{"sikap":88,"pengetahuan":82,"keterampilan_umum":85,"keterampilan_khusus":81.5}', 83.90, 'B', 'Memuaskan', NOW());

-- --------------------------------------------------------
-- 6. TABEL: password_resets
-- --------------------------------------------------------
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(100) NOT NULL,
  `user_id` int(11) NOT NULL,
  `email` varchar(150) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) NOT NULL DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------
-- 7. TABEL: audit_logs
-- --------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `actor_id` int(11) DEFAULT NULL,
  `actor_email` varchar(150) NOT NULL,
  `actor_role` varchar(50) NOT NULL,
  `action` varchar(50) NOT NULL,
  `entity` varchar(50) NOT NULL,
  `entity_id` varchar(50) DEFAULT NULL,
  `summary` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `entity` (`entity`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `audit_logs` (`actor_id`, `actor_email`, `actor_role`, `action`, `entity`, `entity_id`, `summary`, `created_at`) VALUES
(1, 'imtinan.fakhri@gmail.com', 'admin', 'inisialisasi', 'sistem', '0', 'Inisialisasi sistem database CPL UINSA versi PHP 5.5', NOW());

SET FOREIGN_KEY_CHECKS = 1;
