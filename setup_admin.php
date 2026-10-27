<?php
// Jalankan SEKALI di browser untuk membuat akun admin, lalu HAPUS file ini.
require __DIR__ . '/config/db.php';
$user = 'admin'; $pass = 'admin123'; // GANTI password ini
$pdo->prepare('INSERT IGNORE INTO users (username,password_hash,role) VALUES (?,?,?)')
    ->execute([$user, password_hash($pass, PASSWORD_DEFAULT), 'admin']);
echo "Admin dibuat: $user. Hapus setup_admin.php sekarang.";
