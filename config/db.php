<?php
// Koneksi PDO + konfigurasi WA Gateway. Ubah sesuai lingkungan XAMPP/hosting.
const DB_HOST = 'localhost', DB_NAME = 'sigap_db', DB_USER = 'root', DB_PASS = '';
// Kosongkan WA_API_URL untuk mode simulasi (pesan hanya dikembalikan, tidak dikirim).
const AREA_NAME = 'Kec. Sukajaya'; // label wilayah layanan di header
const WA_API_URL = '', WA_TOKEN = '', WA_TARGET = '';          // grup warga
const WA_TARGET_DARURAT = '';  // grup responder (admin/petugas/RT/RW) yang menerima sinyal DARURAT
const IP_SALT = 'ganti-dengan-teks-acak-panjang'; // garam hash IP (pembatas penyalahgunaan)
const WA_TARGET_INSTANSI = ''; // kontak instansi (BPBD/PLN/dll) untuk eskalasi

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false, // prepared statement asli -> cegah SQL Injection
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Koneksi database gagal.');
}

const APP_DEBUG = true; // true = pesan error server ditampilkan; set false saat produksi

// Dipanggil di awal tiap file api/*: warning PHP tidak boleh mencemari JSON, error apa pun dikembalikan sebagai JSON.
function api_boot(): void {
    ini_set('display_errors', '0');
    ob_start();
    set_exception_handler(function (Throwable $e) {
        error_log((string)$e);
        json_out(['ok' => false, 'message' => APP_DEBUG ? 'Error server: ' . $e->getMessage() : 'Terjadi kesalahan di server.'], 500);
    });
    register_shutdown_function(function () { // fatal error, mis. ekstensi PHP (gd/fileinfo) belum aktif
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) && !headers_sent())
            json_out(['ok' => false, 'message' => APP_DEBUG ? 'Error server: ' . $e['message'] : 'Terjadi kesalahan di server.'], 500);
    });
}

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

function json_out(array $data, int $code = 200): void {
    if (ob_get_level()) ob_clean(); // buang output liar sebelum JSON
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
function e(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
