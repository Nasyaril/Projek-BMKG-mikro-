-- Fitur Darurat
USE sigap_db;
ALTER TABLE reports
  ADD COLUMN IF NOT EXISTS is_emergency TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS reporter_ip_hash CHAR(64) NULL;
