-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Waktu pembuatan: 10 Nov 2026 pada 06.47
-- Versi server: 10.4.32-MariaDB
-- Versi PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sigap_db`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `assignments`
--

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `assigned_to_user_id` int(11) NOT NULL,
  `handler_name` varchar(100) DEFAULT NULL,
  `start_note` varchar(300) DEFAULT NULL,
  `finish_note` varchar(300) DEFAULT NULL,
  `photo_after_path` varchar(255) DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `assignments`
--

INSERT INTO `assignments` (`id`, `report_id`, `assigned_to_user_id`, `handler_name`, `start_note`, `finish_note`, `photo_after_path`, `completed_at`) VALUES
(1, 3, 1, NULL, NULL, NULL, NULL, '2026-10-27 11:46:10'),
(2, 2, 1, NULL, NULL, 'sudah diselesaikan', 'assets/uploads/67a7191f5ed2c160.jpg', '2026-11-06 07:06:58'),
(3, 5, 1, 'BPBD', 'sedang ditugaskan', 'terima kasih', 'assets/uploads/96c74c3741879e4e.jpg', '2026-10-30 05:55:11'),
(4, 6, 1, 'Relawan / Linmas desa', 'sedang ditugaskan', 'sudah diselesaikan', 'assets/uploads/51541fc103a19e8c.jpg', '2026-11-06 07:07:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `official_alerts`
--

CREATE TABLE `official_alerts` (
  `id` int(11) NOT NULL,
  `source` varchar(30) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `magnitude` decimal(3,1) DEFAULT NULL,
  `datetime` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `official_alerts`
--

INSERT INTO `official_alerts` (`id`, `source`, `event_type`, `description`, `magnitude`, `datetime`) VALUES
(1, 'BMKG', 'Gempa bumi', 'Pusat gempa berada di laut 39 km utara Waingapu', 4.7, '2026-10-08 20:06:02'),
(8, 'BMKG', 'Gempa bumi', 'Pusat gempa berada di laut 125 km Tenggara Bitung', 5.1, '2026-10-09 07:11:04'),
(9, 'BMKG', 'Gempa bumi', 'Pusat gempa berada di darat 7 km tenggara Deiyai', 4.5, '2026-10-12 00:07:28'),
(12, 'BMKG', 'Gempa bumi', 'Pusat gempa berada di laut 38 km Barat Daya Muarabinuangeun', 4.6, '2026-10-12 07:19:47'),
(14, 'BMKG', 'Gempa bumi', 'Pusat gempa berada di laut 44 km selatan Kab. Blitar', 4.1, '2026-10-12 07:28:13'),
(15, 'BMKG', 'Gempa bumi', 'Pusat gempa berada di laut 30 km timur laut Mbay, Nagekeo', 4.9, '2026-10-26 05:53:42'),
(22, 'BMKG', 'Gempa bumi', '187 km BaratLaut TAHUNA-KEP.SANGIHE-SULUT', 5.0, '2026-10-28 10:32:49'),
(24, 'BMKG', 'Gempa bumi', '139 km BaratLaut TUAL-MALUKU', 5.3, '2026-10-29 21:32:13'),
(27, 'BMKG', 'Gempa bumi', 'Pusat gempa berada di darat 40 km timur laut Sigi', 3.4, '2026-11-04 08:56:34');

-- --------------------------------------------------------

--
-- Struktur dari tabel `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `ticket_number` varchar(20) NOT NULL,
  `access_code` varchar(8) NOT NULL,
  `reporter_name` varchar(100) DEFAULT NULL,
  `reporter_contact` varchar(30) DEFAULT NULL,
  `title` varchar(150) NOT NULL DEFAULT '',
  `category` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `latitude` decimal(10,8) NOT NULL,
  `longitude` decimal(11,8) NOT NULL,
  `photo_before_path` varchar(255) DEFAULT NULL,
  `admin_instruction` text DEFAULT NULL,
  `current_status` enum('menunggu','diverifikasi','diproses','selesai','ditolak') NOT NULL DEFAULT 'menunggu',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `incident_time` datetime DEFAULT NULL,
  `impacts` varchar(255) DEFAULT NULL,
  `priority` enum('rendah','sedang','tinggi') DEFAULT NULL,
  `upvote_count` int(11) NOT NULL DEFAULT 0,
  `province` varchar(80) DEFAULT NULL,
  `regency` varchar(80) DEFAULT NULL,
  `district` varchar(80) DEFAULT NULL,
  `is_emergency` tinyint(1) NOT NULL DEFAULT 0,
  `reporter_ip_hash` char(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `reports`
--

INSERT INTO `reports` (`id`, `ticket_number`, `access_code`, `reporter_name`, `reporter_contact`, `title`, `category`, `description`, `latitude`, `longitude`, `photo_before_path`, `admin_instruction`, `current_status`, `created_at`, `incident_time`, `impacts`, `priority`, `upvote_count`, `province`, `regency`, `district`, `is_emergency`, `reporter_ip_hash`) VALUES
(1, 'SIGAP-261012-48534', '509959', 'TEST1', '0812344965', 'Banjir', 'banjir', 'BANJIR!!!!!!!!', -7.44219986, 112.71963864, 'assets/uploads/e8644c3dd9957f92.jpg', 'HOAK', 'ditolak', '2026-10-12 05:44:00', '2026-10-12 12:43:00', 'jalan_terhalang,rumah_terdampak,ada_korban,mengancam_keselamatan,gangguan_listrik,gangguan_akses,lainnya', 'rendah', 0, 'Jawa Timur', 'Kabupaten Sidoarjo', 'Candi', 0, NULL),
(2, 'SIGAP-261012-53096', 'C37A7F', 'Test2', '088123123', 'Pohon tumbang', 'pohon_tumbang', 'Fire in the hole', -7.43368901, 112.68925444, 'assets/uploads/08708f7e12731b56.jpg', 'OK', 'selesai', '2026-10-12 05:57:10', '2026-10-12 12:56:00', 'jalan_terhalang,ada_korban,gangguan_listrik,gangguan_akses', 'sedang', 1, 'Jawa Timur', 'Kabupaten Sidoarjo', 'Candi', 0, NULL),
(3, 'SIGAP-261012-51681', 'D69B30', 'tes3', '0812344965', 'Banjir di Sidoarjo', 'banjir', 'menghambat lalu lintas', -7.43991968, 112.69195782, 'assets/uploads/1dcf7705cf8f0479.jpg', 'mengganggu jalanan', 'selesai', '2026-10-12 06:08:07', '2026-10-12 13:06:00', 'ada_korban,gangguan_listrik,gangguan_akses', 'tinggi', 0, 'Jawa Timur', 'Kabupaten Sidoarjo', 'Candi', 0, NULL),
(4, 'SIGAP-261027-27048', '6F5317', 'TEST3', '0812344965', 'banjir', 'banjir', 'banjirr....', -7.44634648, 112.71772856, 'assets/uploads/d4c906d2f36b8ef5.jpg', NULL, 'diverifikasi', '2026-10-27 11:44:06', '2026-10-27 18:42:00', 'jalan_terhalang', 'sedang', 0, 'Jawa Timur', 'Kabupaten Sidoarjo', 'Candi', 0, NULL),
(5, 'SIGAP-261030-66525', '4F0A87', 'budi', '0812344965', 'Lampu lalu lintas mati', 'lampu_mati', 'di jalan sidoarjo', -7.44699719, 112.71871737, 'assets/uploads/e8ff63c749820053.jpg', 'berhati hati saat berkendara', 'selesai', '2026-10-30 05:51:21', '2026-10-30 12:49:00', 'gangguan_akses', 'sedang', 0, 'Jawa Timur', 'Kabupaten Sidoarjo', 'Candi', 0, NULL),
(6, 'SIGAP-261106-43694', 'CCEA0F', 'tes', '0812344965', 'pohon tumbang', 'pohon_tumbang', 'sekitar alun alun sidoarjo', -7.44675369, 112.71815952, 'assets/uploads/9ced857299c9e5a3.jpg', 'jalan ditutup', 'selesai', '2026-11-06 07:02:57', '2026-11-06 14:01:00', 'jalan_terhalang,ada_korban', 'sedang', 0, 'Jawa Timur', 'Kabupaten Sidoarjo', 'Candi', 0, NULL);

-- --------------------------------------------------------

--
-- Struktur dari tabel `status_history`
--

CREATE TABLE `status_history` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `status_name` varchar(50) NOT NULL,
  `changed_by_user_id` int(11) DEFAULT NULL,
  `change_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `status_history`
--

INSERT INTO `status_history` (`id`, `report_id`, `status_name`, `changed_by_user_id`, `change_time`) VALUES
(1, 1, 'menunggu', NULL, '2026-10-12 05:44:00'),
(2, 1, 'ditolak', 1, '2026-10-12 05:44:49'),
(3, 2, 'menunggu', NULL, '2026-10-12 05:57:10'),
(4, 2, 'diverifikasi', 1, '2026-10-12 05:57:43'),
(5, 3, 'menunggu', NULL, '2026-10-12 06:08:07'),
(6, 3, 'diverifikasi', 1, '2026-10-12 06:09:09'),
(7, 3, 'dieskalasi', 1, '2026-10-12 06:09:09'),
(8, 4, 'menunggu', NULL, '2026-10-27 11:44:06'),
(9, 4, 'diverifikasi', 1, '2026-10-27 11:44:32'),
(10, 3, 'diproses', 1, '2026-10-27 11:45:53'),
(11, 3, 'selesai', 1, '2026-10-27 11:46:10'),
(12, 2, 'diproses', 1, '2026-10-27 11:47:24'),
(13, 5, 'menunggu', NULL, '2026-10-30 05:51:21'),
(14, 5, 'diverifikasi', 1, '2026-10-30 05:53:09'),
(15, 5, 'dieskalasi', 1, '2026-10-30 05:54:21'),
(16, 5, 'diproses', 1, '2026-10-30 05:54:21'),
(17, 5, 'selesai', 1, '2026-10-30 05:55:11'),
(18, 6, 'menunggu', NULL, '2026-11-06 07:02:57'),
(19, 6, 'diverifikasi', 1, '2026-11-06 07:04:29'),
(20, 6, 'diproses', 1, '2026-11-06 07:06:02'),
(21, 2, 'selesai', 1, '2026-11-06 07:06:58'),
(22, 6, 'selesai', 1, '2026-11-06 07:07:21');

-- --------------------------------------------------------

--
-- Struktur dari tabel `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','petugas') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data untuk tabel `users`
--

INSERT INTO `users` (`id`, `username`, `password_hash`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$SPmlXu9vLlMbZHEIN8t9COjWUW7LGuE3yYJCx9JV4jULfbP1ta.r.', 'admin', '2026-10-08 08:21:19');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`),
  ADD KEY `assigned_to_user_id` (`assigned_to_user_id`);

--
-- Indeks untuk tabel `official_alerts`
--
ALTER TABLE `official_alerts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_alert` (`source`,`datetime`);

--
-- Indeks untuk tabel `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`);

--
-- Indeks untuk tabel `status_history`
--
ALTER TABLE `status_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`),
  ADD KEY `changed_by_user_id` (`changed_by_user_id`);

--
-- Indeks untuk tabel `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT untuk tabel `official_alerts`
--
ALTER TABLE `official_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT untuk tabel `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `status_history`
--
ALTER TABLE `status_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT untuk tabel `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `assignments_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `assignments_ibfk_2` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`);

--
-- Ketidakleluasaan untuk tabel `status_history`
--
ALTER TABLE `status_history`
  ADD CONSTRAINT `status_history_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `status_history_ibfk_2` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
