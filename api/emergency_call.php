<?php
// Sinyal DARURAT: langsung aktif & diteruskan ke responder tanpa menunggu konfirmasi RT/RW.
// Pengaman: honeypot, batas per IP & per nomor, nomor HP wajib, lokasi wajib, admin bisa menandai palsu.
require __DIR__ . '/../config/db.php';
api_boot();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Metode tidak diizinkan'], 405);
if (!empty($_POST['website'])) json_out(['ok' => true, 'ticket' => 'SOS-' . date('ymd') . '-00000', 'access_code' => '000000']); // bot: pura-pura sukses

$types = ['kebakaran' => 'Kebakaran', 'banjir' => 'Banjir / air naik cepat', 'longsor' => 'Longsor', 'bangunan_roboh' => 'Bangunan roboh',
          'pohon_tumbang' => 'Pohon tumbang menimpa', 'lainnya' => 'Darurat lainnya'];
$type  = $_POST['type'] ?? '';
$phone = preg_replace('/[^0-9+]/', '', $_POST['phone'] ?? '');
$name  = mb_substr(trim($_POST['name'] ?? ''), 0, 100);
$note  = mb_substr(trim($_POST['note'] ?? ''), 0, 300);
$lat   = filter_var($_POST['latitude'] ?? '', FILTER_VALIDATE_FLOAT);
$lng   = filter_var($_POST['longitude'] ?? '', FILTER_VALIDATE_FLOAT);
$prov  = mb_substr(trim($_POST['province'] ?? ''), 0, 80);
$reg   = mb_substr(trim($_POST['regency'] ?? ''), 0, 80);
$dist  = mb_substr(trim($_POST['district'] ?? ''), 0, 80);

if (!isset($types[$type]) || strlen($phone) < 8 || strlen($phone) > 16 || $lat === false || $lng === false || abs($lat) > 90 || abs($lng) > 180)
    json_out(['ok' => false, 'message' => 'Pilih jenis darurat, isi nomor HP yang valid, dan aktifkan lokasi.'], 422);

$ipHash = hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . IP_SALT);
$q = $pdo->prepare('SELECT COUNT(*) FROM reports WHERE is_emergency=1 AND reporter_ip_hash=? AND created_at > (NOW() - INTERVAL 10 MINUTE)');
$q->execute([$ipHash]);
if ((int)$q->fetchColumn() >= 3) json_out(['ok' => false, 'message' => 'Terlalu banyak sinyal dari perangkat ini. Jika masih darurat, telepon 112.'], 429);
$q = $pdo->prepare('SELECT COUNT(*) FROM reports WHERE is_emergency=1 AND reporter_contact=? AND created_at > (NOW() - INTERVAL 2 MINUTE)');
$q->execute([$phone]);
if ((int)$q->fetchColumn() > 0) json_out(['ok' => false, 'message' => 'Sinyal dari nomor ini baru saja terkirim; petugas sedang diberi tahu. Jika mendesak, telepon 112.'], 429);

$ticket = 'SOS-' . date('ymd') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
$code   = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
$label  = $types[$type];
$desc   = $note ?: 'Sinyal darurat dari warga. Segera hubungi pelapor dan cek lokasi.';
try {
    $pdo->beginTransaction();
    // Langsung 'diverifikasi' + prioritas tinggi supaya muncul di antrean admin & peta tanpa menunggu RT/RW.
    $pdo->prepare('INSERT INTO reports (ticket_number,access_code,reporter_name,reporter_contact,title,province,regency,district,incident_time,category,description,latitude,longitude,priority,is_emergency,reporter_ip_hash,current_status)
                   VALUES (?,?,?,?,?,?,?,?,NOW(),?,?,?,?,"tinggi",1,?,"diverifikasi")')
        ->execute([$ticket, $code, $name ?: null, $phone, 'DARURAT: ' . $label, $prov ?: null, $reg ?: null, $dist ?: null, $type, $desc, $lat, $lng, $ipHash]);
    $pdo->prepare('INSERT INTO status_history (report_id,status_name,changed_by_user_id) VALUES (?,?,NULL)')->execute([$pdo->lastInsertId(), 'darurat']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log((string)$e);
    json_out(['ok' => false, 'message' => APP_DEBUG ? 'Gagal menyimpan: ' . $e->getMessage() : 'Gagal mengirim sinyal. Telepon 112 sekarang.'], 500);
}

// Teruskan ke responder lewat WhatsApp (kegagalan WA tidak membatalkan sinyal yang sudah tersimpan)
$msg = "🚨🚨 *DARURAT SIGAP* 🚨🚨\nJenis: *$label*\nTiket: $ticket\nPelapor: " . ($name ?: '-') . " • $phone\n"
     . "Lokasi: https://maps.google.com/?q=$lat,$lng\n" . ($dist ? "Wilayah: Kec. $dist, $reg\n" : '')
     . "Catatan: $desc\n\n⚠️ BELUM dikonfirmasi RT/RW — segera cek lokasi dan hubungi pelapor.";
$wa = send_wa($msg, WA_TARGET_DARURAT);
json_out(['ok' => true, 'ticket' => $ticket, 'access_code' => $code, 'notified' => $wa['sent'] || $wa['simulated']]);
