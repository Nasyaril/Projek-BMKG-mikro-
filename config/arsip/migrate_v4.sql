-- Wilayah laporan (Provinsi > Kota/Kabupaten > Kecamatan)
USE sigap_db;
ALTER TABLE reports
  ADD COLUMN IF NOT EXISTS province VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS regency VARCHAR(80) NULL,
  ADD COLUMN IF NOT EXISTS district VARCHAR(80) NULL;
