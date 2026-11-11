# Database SIGAP

## Install awal

Buat database kosong bernama `sigap_db` dengan charset `utf8mb4` dan collation `utf8mb4_general_ci`, lalu import `sigap_db.sql`.

## Penambahan kolom

Jika fitur baru membutuhkan kolom, tambahkan satu baris di `config/schema.php`, misalnya:

```php
'reports' => [
    'rt_rw' => 'VARCHAR(20) NULL',
],
```

Buka aplikasi saat `APP_DEBUG` bernilai `true`; kolom yang belum ada akan dibuat otomatis.

## Batasan

Mekanisme ini hanya untuk penambahan kolom/tabel. Rename, hapus, atau perubahan tipe kolom harus dilakukan secara manual.
