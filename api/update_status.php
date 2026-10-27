<?php
require __DIR__ . '/../config/db.php';
api_boot();
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') json_out(['ok' => false, 'message' => 'Akses ditolak'], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Metode tidak diizinkan'], 405);

$id   = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
$act  = $_POST['action'] ?? '';
$note = mb_substr(trim($_POST['note'] ?? ''), 0, 300);
$map  = ['proses' => 'diproses', 'selesai' => 'selesai'];
if (!$id || !isset($map[$act])) json_out(['ok' => false, 'message' => 'Permintaan tidak valid'], 422);

$pdo->beginTransaction();
$st = $pdo->prepare('SELECT current_status FROM reports WHERE id=? FOR UPDATE');
$st->execute([$id]);
$cur = $st->fetchColumn();
$allowed = $act === 'proses' ? ['diverifikasi'] : ['diverifikasi', 'diproses'];
if (!$cur || !in_array($cur, $allowed, true)) {
    $pdo->rollBack();
    json_out(['ok' => false, 'message' => 'Status laporan tidak bisa diubah ke tahap ini.'], 409);
}

$new = $map[$act];
$pdo->prepare('UPDATE reports SET current_status=?, admin_instruction=COALESCE(?, admin_instruction) WHERE id=?')
    ->execute([$new, $note ?: null, $id]);
$pdo->prepare('INSERT INTO status_history (report_id,status_name,changed_by_user_id) VALUES (?,?,?)')
    ->execute([$id, $new, $_SESSION['uid']]);

// Kartu "Tim Lapangan" di Cek Status: dibuat saat diproses, diselesaikan saat selesai
$a = $pdo->prepare('SELECT id FROM assignments WHERE report_id=? ORDER BY id DESC LIMIT 1');
$a->execute([$id]);
$aid = $a->fetchColumn();
if ($act === 'proses' && !$aid) {
    $pdo->prepare('INSERT INTO assignments (report_id, assigned_to_user_id) VALUES (?,?)')->execute([$id, $_SESSION['uid']]);
} elseif ($act === 'selesai') {
    if ($aid) $pdo->prepare('UPDATE assignments SET completed_at=NOW() WHERE id=?')->execute([$aid]);
    else $pdo->prepare('INSERT INTO assignments (report_id, assigned_to_user_id, completed_at) VALUES (?,?,NOW())')->execute([$id, $_SESSION['uid']]);
}
$pdo->commit();
json_out(['ok' => true, 'status' => $new]);