-- Pihak penangani (dipilih admin saat Mulai Proses / Eskalasi)
USE sigap_db;
ALTER TABLE assignments
  ADD COLUMN IF NOT EXISTS handler_name VARCHAR(100) NULL AFTER assigned_to_user_id;
