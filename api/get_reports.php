<?php
require __DIR__ . '/../config/db.php';
api_boot();
// Human-in-the-Loop: laporan 'menunggu' & 'ditolak' TIDAK tampil di peta publik.
// Filter wilayah opsional: ?province=...&regency=...&district=... (nama kolom dari whitelist, nilai lewat prepared statement)
$where = ["current_status IN ('diverifikasi','diproses','selesai')"]; $params = [];
foreach (['province', 'regency', 'district'] as $col) {
    if (!empty($_GET[$col])) { $where[] = "$col = ?"; $params[] = mb_substr($_GET[$col], 0, 80); }
}
$st = $pdo->prepare('SELECT id, is_emergency, upvote_count, ticket_number, photo_before_path, incident_time, title, category, description, impacts, district, regency, admin_instruction, priority, latitude, longitude, current_status, created_at,
                            (SELECT handler_name FROM assignments a WHERE a.report_id=reports.id ORDER BY a.id DESC LIMIT 1) AS handler,
                            (SELECT photo_after_path FROM assignments a2 WHERE a2.report_id=reports.id ORDER BY a2.id DESC LIMIT 1) AS photo_after
                     FROM reports WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 200');
$st->execute($params);
$rows = $st->fetchAll();
foreach ($rows as &$r) {
    $r['latitude'] = (float)$r['latitude']; $r['longitude'] = (float)$r['longitude'];
    // Privasi: titik darurat di peta publik dibulatkan (~110 m); koordinat tepat hanya sampai ke petugas.
    if ($r['is_emergency']) { $r['latitude'] = round($r['latitude'], 3); $r['longitude'] = round($r['longitude'], 3); }
}
json_out(['ok' => true, 'data' => $rows]);