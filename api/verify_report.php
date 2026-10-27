<?php
require __DIR__ . '/../config/db.php';
api_boot();
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') json_out(['ok' => false, 'message' => 'Akses ditolak'], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Metode tidak diizinkan'], 405);

// Kirim pesan ke WA Gateway lewat cURL POST. Mode simulasi jika WA_API_URL / target kosong.
function send_wa(string $msg, string $target): array {
    if (WA_API_URL === '' || $target === '') return ['sent' => false, 'simulated' => true];
    $ch = curl_init(WA_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Authorization: ' . WA_TOKEN],
        CURLOPT_POSTFIELDS => http_build_query(['target' => $target, 'message' => $msg]),
    ]);
    curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['sent' => $code >= 200 && $code < 300, 'simulated' => false];
}

$id     = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';
$instr  = trim($_POST['instruction'] ?? '');
$prio   = in_array($_POST['priority'] ?? '', ['rendah', 'sedang', 'tinggi'], true) ? $_POST['priority'] : null;
if (!$id || !in_array($action, ['terima', 'tolak', 'eskalasi'], true)) json_out(['ok' => false, 'message' => 'Permintaan tidak valid'], 422);
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
if ($action === 'eskalasi') $log->execute([$id, 'dieskalasi', $_SESSION['uid']]);
$pdo->commit();

if ($action === 'tolak') json_out(['ok' => true, 'wa' => ['sent' => false, 'simulated' => false], 'message_preview' => '']);

// 3. Template pesan WA dirakit dinamis (kategori + prioritas + instruksi khusus admin)
$label = ucwords(str_replace('_', ' ', $r['category']));
$icon  = ['tinggi' => '🔴', 'sedang' => '🟡', 'rendah' => '🟢'][$prio];
$msg = $action === 'eskalasi'
    ? "📨 *ESKALASI SIGAP*\nKejadian: *$label* ({$r['title']})\nTiket: {$r['ticket_number']}\nPrioritas: $icon " . strtoupper($prio) . "\n\n{$r['description']}\n\n" . ($instr ? "Catatan RT/Admin: $instr\n\n" : '') . "Mohon tindak lanjut instansi terkait."
    : "🚨 *PERINGATAN SIGAP*\nKejadian: *$label*\n{$r['title']}\nPrioritas: $icon " . strtoupper($prio) . "\nTiket: {$r['ticket_number']}\n\n{$r['description']}\n\n" . ($instr ? "📌 *Instruksi:* $instr\n\n" : '') . "Mohon waspada dan ikuti arahan Ketua RT/RW.";
// 4. Tembak API WA (kegagalan WA tidak membatalkan perubahan status)
json_out(['ok' => true, 'wa' => send_wa($msg, $action === 'eskalasi' ? WA_TARGET_INSTANSI : WA_TARGET), 'message_preview' => $msg]);
