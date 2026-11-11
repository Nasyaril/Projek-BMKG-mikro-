<?php
require __DIR__ . '/../config/db.php';
api_boot();
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') json_out(['ok' => false, 'message' => 'Akses ditolak'], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Metode tidak diizinkan'], 405);

const PIHAK_DESA = 'Relawan / Linmas desa'; // ditangani sendiri oleh desa = bukan eskalasi ke instansi luar

// Kompresi GD (sama dengan submit_report.php): resize maks 1280px lalu turunkan kualitas JPEG sampai <= 500KB.
function compress_image(string $src, string $dest, int $max = 512000): bool {
    $info = getimagesize($src);
    if (!$info) return false;
    $img = match ($info['mime']) {
        'image/jpeg' => imagecreatefromjpeg($src), 'image/png' => imagecreatefrompng($src),
        'image/webp' => imagecreatefromwebp($src), default => false,
    };
    if (!$img) return false;
    if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) { // foto HP sering miring
        $o = @exif_read_data($src)['Orientation'] ?? 1;
        $img = match ($o) { 3 => imagerotate($img, 180, 0), 6 => imagerotate($img, -90, 0), 8 => imagerotate($img, 90, 0), default => $img };
    }
    $w = imagesx($img); $h = imagesy($img); $side = 1280;
    for ($try = 0; $try < 4; $try++) {
        $s = min(1, $side / max($w, $h)); $nw = (int)($w * $s); $nh = (int)($h * $s);
        $out = imagecreatetruecolor($nw, $nh);
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        for ($q = 80; $q >= 40; $q -= 10) {
            imagejpeg($out, $dest, $q); clearstatcache(true, $dest);
            if (filesize($dest) <= $max) return true;
        }
        $side = (int)($side * 0.75);
    }
    return filesize($dest) <= $max;
}

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

// Foto dokumentasi penyelesaian (opsional, hanya saat Selesaikan). Divalidasi dulu: kalau gagal, status TIDAK berubah.
$photoPath = null; $photoFull = null;
if ($act === 'selesai' && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['photo'];
    $mime = $f['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 10 * 1024 * 1024 || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $pdo->rollBack();
        json_out(['ok' => false, 'message' => 'Foto harus JPG/PNG/WEBP, maksimal 10 MB.'], 422);
    }
    $fname = bin2hex(random_bytes(8)) . '.jpg';
    $photoFull = __DIR__ . '/../assets/uploads/' . $fname;
    if (!compress_image($f['tmp_name'], $photoFull)) {
        @unlink($photoFull); $pdo->rollBack();
        json_out(['ok' => false, 'message' => 'Foto gagal diproses.'], 422);
    }
    $photoPath = 'assets/uploads/' . $fname;   // hanya path yang masuk DB, bukan BLOB
}

$new = $map[$act];
$escalate = $act === 'proses' && $handler !== PIHAK_DESA;     // pihak luar = eskalasi
try {
    $pdo->prepare('UPDATE reports SET current_status=? WHERE id=?')->execute([$new, $id]);   // catatan admin disimpan di assignments, terpisah dari instruksi verifikasi
    $log = $pdo->prepare('INSERT INTO status_history (report_id,status_name,changed_by_user_id) VALUES (?,?,?)');
    if ($escalate) $log->execute([$id, 'dieskalasi', $_SESSION['uid']]);   // tampil di timeline warga sebelum "Sedang Ditangani"
    $log->execute([$id, $new, $_SESSION['uid']]);

    // Kartu penanganan di Cek Status: "Sedang ditangani oleh: <pihak>" lalu "Ditangani oleh: <pihak>"
    $a = $pdo->prepare('SELECT id FROM assignments WHERE report_id=? ORDER BY id DESC LIMIT 1');
    $a->execute([$id]);
    $aid = $a->fetchColumn();
    if ($act === 'proses') {
        if ($aid) $pdo->prepare('UPDATE assignments SET handler_name=?, start_note=? WHERE id=?')->execute([$handler, $note ?: null, $aid]);
        else $pdo->prepare('INSERT INTO assignments (report_id, assigned_to_user_id, handler_name, start_note) VALUES (?,?,?,?)')->execute([$id, $_SESSION['uid'], $handler, $note ?: null]);
    } else {
        if ($aid) $pdo->prepare('UPDATE assignments SET completed_at=NOW(), finish_note=?, photo_after_path=COALESCE(?, photo_after_path) WHERE id=?')->execute([$note ?: null, $photoPath, $aid]);
        else $pdo->prepare('INSERT INTO assignments (report_id, assigned_to_user_id, completed_at, finish_note, photo_after_path) VALUES (?,?,NOW(),?,?)')->execute([$id, $_SESSION['uid'], $note ?: null, $photoPath]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($photoFull) @unlink($photoFull);   // jangan tinggalkan foto yatim kalau DB gagal
    throw $e;
}

// SIMULASI: pesan eskalasi hanya dirakit sebagai pratinjau untuk admin. send_wa() SENGAJA tidak dipanggil,
// jadi tidak ada pesan yang dikirim ke instansi mana pun.
$preview = null;
if ($escalate) {
    $label = ucwords(str_replace('_', ' ', $r['category']));
    $icon  = ['tinggi' => '🔴', 'sedang' => '🟡', 'rendah' => '🟢'][$r['priority'] ?? ''] ?? '⚪';
    $preview = "📨 *ESKALASI SIGAP* → $handler\nKejadian: *$label* ({$r['title']})\nTiket: {$r['ticket_number']}\nPrioritas: $icon " . strtoupper($r['priority'] ?? '-')
             . "\n\n{$r['description']}\n\n" . ($note ? "Catatan Admin: $note\n\n" : '') . "Mohon tindak lanjut.";
}
json_out(['ok' => true, 'status' => $new, 'handler' => $act === 'proses' ? $handler : null, 'simulated' => true, 'message_preview' => $preview, 'photo' => $photoPath]);