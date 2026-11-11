-- Jalankan setelah migrate_v2.sql
USE sigap_db;
ALTER TABLE reports ADD COLUMN upvote_count INT NOT NULL DEFAULT 0 AFTER admin_instruction;
CREATE TABLE IF NOT EXISTS official_alerts (
  id INT AUTO_INCREMENT PRIMARY KEY, source VARCHAR(30) NOT NULL, event_type VARCHAR(50) NOT NULL,
  description VARCHAR(255) NOT NULL, magnitude DECIMAL(3,1) NULL, `datetime` DATETIME NOT NULL,
  UNIQUE KEY uq_alert (source, `datetime`)
);
