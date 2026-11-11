<?php
require __DIR__ . '/../config/db.php';
api_boot();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Metode tidak diizinkan'], 405);

// Kompresi GD: resize maks 1280px lalu turunkan kualitas JPEG sampai <= 500KB.
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

$cats = ['banjir', 'pohon_tumbang', 'lampu_mati', 'jalan_rusak', 'lainnya'];
$cat  = $_POST['category'] ?? '';
$desc = trim($_POST['description'] ?? '');
$lat  = filter_var($_POST['latitude'] ?? '', FILTER_VALIDATE_FLOAT);
$lng  = filter_var($_POST['longitude'] ?? '', FILTER_VALIDATE_FLOAT);
$name = mb_substr(trim($_POST['reporter_name'] ?? ''), 0, 100);
$contact = preg_replace('/[^0-9+]/', '', $_POST['reporter_contact'] ?? '');
$title = mb_substr(trim($_POST['title'] ?? ''), 0, 150);
$prov = mb_substr(trim($_POST['province'] ?? ''), 0, 80);
$reg  = mb_substr(trim($_POST['regency'] ?? ''), 0, 80);
$dist = mb_substr(trim($_POST['district'] ?? ''), 0, 80);
$ts = !empty($_POST['incident_time']) ? strtotime($_POST['incident_time']) : false;
$incident = $ts ? date('Y-m-d H:i:s', $ts) : null;
$allowedImpacts = ['jalan_terhalang','rumah_terdampak','ada_korban','mengancam_keselamatan','gangguan_listrik','gangguan_akses','lainnya'];
$impacts = implode(',', array_intersect($allowedImpacts, (array)($_POST['impacts'] ?? []))); // whitelist

if (!in_array($cat, $cats, true) || mb_strlen($desc) < 10 || mb_strlen($title) < 3 || $name === '' || strlen($contact) < 8 || !$incident || $prov === '' || $reg === '' || $dist === '' || $lat === false || $lng === false
    || $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180)
    json_out(['ok' => false, 'message' => 'Lengkapi nama, No. HP, kategori, judul, deskripsi (min. 10 karakter), waktu, lokasi, dan wilayah (pilih lewat menu lokasi di bagian atas).'], 422);

$photoPath = null;
if (!empty($_FILES['photo']['tmp_name'])) {
    $f = $_FILES['photo'];
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if ($f['error'] !== UPLOAD_ERR_OK || $f['size'] > 10 * 1024 * 1024 || !in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true))
        json_out(['ok' => false, 'message' => 'Foto harus JPG/PNG/WEBP, maksimal 10 MB.'], 422);
    $fname = bin2hex(random_bytes(8)) . '.jpg';
    if (!compress_image($f['tmp_name'], __DIR__ . '/../assets/uploads/' . $fname))
        json_out(['ok' => false, 'message' => 'Foto gagal diproses.'], 422);
    $photoPath = 'assets/uploads/' . $fname; // hanya path yang masuk DB, bukan BLOB
}

$ticket = 'SIGAP-' . date('ymd') . '-' . str_pad((string)random_int(1, 99999), 5, '0', STR_PAD_LEFT);
$code   = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
try {
    $pdo->beginTransaction();
    $pdo->prepare('INSERT INTO reports (ticket_number,access_code,reporter_name,reporter_contact,title,province,regency,district,incident_time,impacts,category,description,latitude,longitude,photo_before_path,current_status)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,"menunggu")')
        ->execute([$ticket, $code, $name, $contact, $title, $prov, $reg, $dist, $incident, $impacts ?: null, $cat, $desc, $lat, $lng, $photoPath]);
    $pdo->prepare('INSERT INTO status_history (report_id,status_name,changed_by_user_id) VALUES (?,?,NULL)')
        ->execute([$pdo->lastInsertId(), 'menunggu']);
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log((string)$e);
    json_out(['ok' => false, 'message' => APP_DEBUG ? 'Gagal menyimpan: ' . $e->getMessage() : 'Gagal menyimpan laporan, coba lagi.'], 500);
}
json_out(['ok' => true, 'ticket' => $ticket, 'access_code' => $code]);
