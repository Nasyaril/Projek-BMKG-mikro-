CREATE DATABASE IF NOT EXISTS sigap_db CHARACTER SET utf8mb4;
USE sigap_db;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','petugas') NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ticket_number VARCHAR(20) NOT NULL UNIQUE,
  access_code VARCHAR(8) NOT NULL,            -- tambahan: untuk Cek Status
  reporter_name VARCHAR(100) NULL,            -- tambahan: dari form UI
  reporter_contact VARCHAR(30) NULL,          -- tambahan: notifikasi resolusi
  title VARCHAR(150) NOT NULL,                -- Judul Kejadian
  province VARCHAR(80) NULL,                  -- wilayah dari pemilih lokasi
  regency VARCHAR(80) NULL,
  district VARCHAR(80) NULL,
  incident_time DATETIME NULL,                -- Waktu Kejadian
  impacts VARCHAR(255) NULL,                  -- Dampak (checkbox, dipisah koma)
  priority ENUM('rendah','sedang','tinggi') NULL, -- diisi admin saat verifikasi
  category VARCHAR(50) NOT NULL,
  description TEXT NOT NULL,
  latitude DECIMAL(10,8) NOT NULL,
  longitude DECIMAL(11,8) NOT NULL,
  photo_before_path VARCHAR(255) NULL,
  admin_instruction TEXT NULL,
  upvote_count INT NOT NULL DEFAULT 0,        -- tombol "Saya Terdampak"
  current_status ENUM('menunggu','diverifikasi','diproses','selesai','ditolak') NOT NULL DEFAULT 'menunggu',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE status_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  status_name VARCHAR(50) NOT NULL,
  changed_by_user_id INT NULL,                -- NULL = dibuat oleh warga (tanpa login)
  change_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  FOREIGN KEY (changed_by_user_id) REFERENCES users(id)
);
CREATE TABLE assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  report_id INT NOT NULL,
  assigned_to_user_id INT NOT NULL,
  photo_after_path VARCHAR(255) NULL,
  completed_at TIMESTAMP NULL,
  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to_user_id) REFERENCES users(id)
);

CREATE TABLE official_alerts (                 -- data resmi BMKG/BPBD
  id INT AUTO_INCREMENT PRIMARY KEY,
  source VARCHAR(30) NOT NULL,
  event_type VARCHAR(50) NOT NULL,
  description VARCHAR(255) NOT NULL,
  magnitude DECIMAL(3,1) NULL,
  `datetime` DATETIME NOT NULL,
  UNIQUE KEY uq_alert (source, `datetime`)
);
