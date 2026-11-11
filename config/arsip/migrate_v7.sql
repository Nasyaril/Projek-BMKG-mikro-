-- Catatan penanganan dipisah dari instruksi verifikasi (tampil di kartu penanganan Cek Status)
USE sigap_db;
ALTER TABLE assignments
  ADD COLUMN IF NOT EXISTS start_note VARCHAR(300) NULL AFTER handler_name,
  ADD COLUMN IF NOT EXISTS finish_note VARCHAR(300) NULL AFTER start_note;
