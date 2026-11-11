<?php
require __DIR__ . '/../config/db.php';
api_boot();
$done = (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE current_status='selesai'")->fetchColumn();
// Rata-rata respon = selisih waktu laporan masuk -> diverifikasi (menit)
$avg = (float)$pdo->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, r.created_at, h.change_time)) FROM reports r
        JOIN status_history h ON h.report_id = r.id AND h.status_name = 'diverifikasi'")->fetchColumn();
json_out(['ok' => true, 'done' => $done, 'avg_minutes' => (int)round($avg)]);
