<?php
require __DIR__ . '/../config/db.php';
api_boot();
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') json_out(['ok' => false, 'message' => 'Akses ditolak'], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Metode tidak diizinkan'], 405);

$id     = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$instr  = trim($_POST['instruction'] ?? '');
$prio   = in_array($_POST['priority'] ?? '', ['rendah', 'sedang', 'tinggi'], true) ? $_POST['priority'] : null;
if (!$id || !in_array($action, ['terima', 'tolak'], true)) json_out(['ok' => false, 'message' => 'Permintaan tidak valid'], 422);
if ($action !== 'tolak' && !$prio) json_out(['ok' => false, 'message' => 'Pilih prioritas dulu.'], 422);
if ($action === 'tolak' && $instr === '') json_out(['ok' => false, 'message' => 'Tulis alasan penolakan di kolom instruksi.'], 422);

$pdo->beginTransaction();
$st = $pdo->prepare("SELECT category, title, description, ticket_number FROM reports WHERE id=? AND current_status='menunggu' FOR UPDATE");
$st->execute([$id]);
$r = $st->fetch();
if (!$r) { $pdo->rollBack(); json_out(['ok' => false, 'message' => 'Laporan tidak ditemukan atau sudah diproses'], 404); }

$newStatus = $action === 'tolak' ? 'ditolak' : 'diverifikasi';
$pdo->prepare('UPDATE reports SET current_status=?, admin_instruction=?, priority=? WHERE id=?')
    ->execute([$newStatus, $instr ?: null, $prio, $id]);                                       // 1. ubah status
$log = $pdo->prepare('INSERT INTO status_history (report_id,status_name,changed_by_user_id) VALUES (?,?,?)');
$log->execute([$id, $newStatus, $_SESSION['uid']]);                                            // 2. jejak audit
$pdo->commit();

if ($action === 'tolak') json_out(['ok' => true, 'wa' => ['sent' => false, 'simulated' => false], 'message_preview' => '']);

// 3. Template pesan WA dirakit dinamis (kategori + prioritas + instruksi khusus admin)
$label = ucwords(str_replace('_', ' ', $r['category']));
$icon  = ['tinggi' => '🔴', 'sedang' => '🟡', 'rendah' => '🟢'][$prio];
$msg = "🚨 *PERINGATAN SIGAP*\nKejadian: *$label*\n{$r['title']}\nPrioritas: $icon " . strtoupper($prio) . "\nTiket: {$r['ticket_number']}\n\n{$r['description']}\n\n" . ($instr ? "📌 *Instruksi:* $instr\n\n" : '') . "Mohon waspada dan ikuti arahan Ketua RT/RW.";
// 4. Kirim WA (SIMULASI: send_wa() tidak mengirim apa pun selama WA_SIMULASI = true)
json_out(['ok' => true, 'wa' => send_wa($msg, WA_TARGET), 'message_preview' => $msg]);