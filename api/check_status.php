<?php
require __DIR__ . '/../config/db.php';
api_boot();
// Cek status: butuh nomor laporan + kode akses (kode tidak pernah dikembalikan).
$t = trim($_POST['ticket'] ?? ''); $c = strtoupper(trim($_POST['code'] ?? ''));
$st = $pdo->prepare('SELECT id, is_emergency, access_code, ticket_number, title, category, priority, latitude, longitude, photo_before_path, incident_time, current_status, admin_instruction, created_at FROM reports WHERE ticket_number=?');
$st->execute([$t]);
$r = $st->fetch();
if (!$r || !hash_equals($r['access_code'], $c)) { usleep(400000); json_out(['ok' => false, 'message' => 'Nomor laporan atau kode akses tidak cocok.'], 404); }
$h = $pdo->prepare('SELECT status_name, change_time FROM status_history WHERE report_id=? ORDER BY change_time, id');
$h->execute([$r['id']]);
$a = $pdo->prepare('SELECT photo_after_path, completed_at FROM assignments WHERE report_id=? ORDER BY id');
$a->execute([$r['id']]);
$assign = $a->fetchAll();
unset($r['id'], $r['access_code']);
json_out(['ok' => true, 'report' => $r, 'history' => $h->fetchAll(), 'assignments' => $assign]);
