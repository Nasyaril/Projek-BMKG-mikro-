<?php
require __DIR__ . '/../config/db.php';
api_boot();
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') json_out(['ok' => false, 'message' => 'Akses ditolak'], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Metode tidak diizinkan'], 405);

const PIHAK_DESA = 'Relawan / Linmas desa'; // ditangani sendiri oleh desa = bukan eskalasi ke instansi luar

$id      = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
$act     = $_POST['action'] ?? '';
$note    = mb_substr(trim($_POST['note'] ?? ''), 0, 300);
$handler = mb_substr(trim($_POST['handler'] ?? ''), 0, 100);   // pihak penangani yang dipilih admin
$map     = ['proses' => 'diproses', 'selesai' => 'selesai'];
if (!$id || !isset($map[$act])) json_out(['ok' => false, 'message' => 'Permintaan tidak valid'], 422);
if ($act === 'proses' && $handler === '') json_out(['ok' => false, 'message' => 'Pilih pihak yang menangani dulu.'], 422);

$pdo->beginTransaction();
$st = $pdo->prepare('SELECT current_status, category, title, description, ticket_number, priority FROM reports WHERE id=? FOR UPDATE');
$st->execute([$id]);
$r = $st->fetch();
$cur = $r['current_status'] ?? null;
$allowed = $act === 'proses' ? ['diverifikasi'] : ['diverifikasi', 'diproses'];
if (!$cur || !in_array($cur, $allowed, true)) {
    $pdo->rollBack();
    json_out(['ok' => false, 'message' => 'Status laporan tidak bisa diubah ke tahap ini.'], 409);
}

$new = $map[$act];
$escalate = $act === 'proses' && $handler !== PIHAK_DESA;     // pihak luar = eskalasi
$pdo->prepare('UPDATE reports SET current_status=?, admin_instruction=CONCAT_WS(CHAR(10), admin_instruction, ?) WHERE id=?')
    ->execute([$new, $note ?: null, $id]);
$log = $pdo->prepare('INSERT INTO status_history (report_id,status_name,changed_by_user_id) VALUES (?,?,?)');
if ($escalate) $log->execute([$id, 'dieskalasi', $_SESSION['uid']]);   // tampil di timeline warga sebelum "Sedang Ditangani"
$log->execute([$id, $new, $_SESSION['uid']]);

// Kartu penanganan di Cek Status: "Sedang ditangani oleh: <pihak>" lalu "Ditangani oleh: <pihak>"
$a = $pdo->prepare('SELECT id FROM assignments WHERE report_id=? ORDER BY id DESC LIMIT 1');
$a->execute([$id]);
$aid = $a->fetchColumn();
if ($act === 'proses') {
    if ($aid) $pdo->prepare('UPDATE assignments SET handler_name=? WHERE id=?')->execute([$handler, $aid]);
    else $pdo->prepare('INSERT INTO assignments (report_id, assigned_to_user_id, handler_name) VALUES (?,?,?)')->execute([$id, $_SESSION['uid'], $handler]);
} else {
    if ($aid) $pdo->prepare('UPDATE assignments SET completed_at=NOW() WHERE id=?')->execute([$aid]);
    else $pdo->prepare('INSERT INTO assignments (report_id, assigned_to_user_id, completed_at) VALUES (?,?,NOW())')->execute([$id, $_SESSION['uid']]);
}
$pdo->commit();

// SIMULASI: pesan eskalasi hanya dirakit sebagai pratinjau untuk admin. send_wa() SENGAJA tidak dipanggil,
// jadi tidak ada pesan yang dikirim ke instansi mana pun.
$preview = null;
if ($escalate) {
    $label = ucwords(str_replace('_', ' ', $r['category']));
    $icon  = ['tinggi' => '🔴', 'sedang' => '🟡', 'rendah' => '🟢'][$r['priority'] ?? ''] ?? '⚪';
    $preview = "📨 *ESKALASI SIGAP* → $handler\nKejadian: *$label* ({$r['title']})\nTiket: {$r['ticket_number']}\nPrioritas: $icon " . strtoupper($r['priority'] ?? '-')
             . "\n\n{$r['description']}\n\n" . ($note ? "Catatan Admin: $note\n\n" : '') . "Mohon tindak lanjut.";
}
json_out(['ok' => true, 'status' => $new, 'handler' => $act === 'proses' ? $handler : null, 'simulated' => true, 'message_preview' => $preview]);