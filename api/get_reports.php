<?php
require __DIR__ . '/../config/db.php';
api_boot();
// Human-in-the-Loop: laporan 'menunggu' & 'ditolak' TIDAK tampil di peta publik.
// Filter wilayah opsional: ?province=...&regency=...&district=... (nama kolom dari whitelist, nilai lewat prepared statement)
$where = ["current_status IN ('diverifikasi','diproses','selesai')"]; $params = [];
foreach (['province', 'regency', 'district'] as $col) {
    if (!empty($_GET[$col])) { $where[] = "$col = ?"; $params[] = mb_substr($_GET[$col], 0, 80); }
}
$st = $pdo->prepare('SELECT id, upvote_count, ticket_number, photo_before_path, incident_time, title, category, description, priority, latitude, longitude, current_status, created_at
                     FROM reports WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC LIMIT 200');
$st->execute($params);
$rows = $st->fetchAll();
foreach ($rows as &$r) { $r['latitude'] = (float)$r['latitude']; $r['longitude'] = (float)$r['longitude']; }
json_out(['ok' => true, 'data' => $rows]);
