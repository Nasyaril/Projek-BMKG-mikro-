<?php
require __DIR__ . '/../config/db.php';
api_boot();
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') json_out(['ok' => false], 403);
session_write_close(); // jangan tahan sesi saat polling berulang
$latest = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM reports WHERE is_emergency=1')->fetchColumn();
$active = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE is_emergency=1 AND current_status IN ('diverifikasi','diproses')")->fetchColumn();
json_out(['ok' => true, 'latest' => $latest, 'active' => $active]);
