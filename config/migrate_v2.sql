-- Untuk database yang sudah dibuat dari versi sebelumnya
USE sigap_db;
ALTER TABLE reports
  ADD COLUMN title VARCHAR(150) NOT NULL DEFAULT '' AFTER reporter_contact,
  ADD COLUMN incident_time DATETIME NULL AFTER title,
  ADD COLUMN impacts VARCHAR(255) NULL AFTER incident_time,
  ADD COLUMN priority ENUM('rendah','sedang','tinggi') NULL AFTER impacts;
