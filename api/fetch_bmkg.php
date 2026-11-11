<?php
require __DIR__ . '/../config/db.php';
api_boot();
// Cache 5 menit supaya tidak membebani server BMKG.
$cache = sys_get_temp_dir() . '/sigap_bmkg.json';
if (is_file($cache) && time() - filemtime($cache) < 300) { header('Content-Type: application/json'); readfile($cache); exit; }

$ch = curl_init('https://data.bmkg.go.id/DataMKG/TEWS/autogempa.xml');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8]);
$xml = curl_exec($ch); curl_close($ch);
$x = $xml ? @simplexml_load_string($xml) : false;
if (!$x || !isset($x->gempa)) json_out(['ok' => false, 'message' => 'Data BMKG tidak tersedia'], 502);

$g = $x->gempa; $mag = (float)$g->Magnitude;
try { // simpan ke official_alerts (UNIQUE source+datetime mencegah duplikat)
    $pdo->prepare('INSERT IGNORE INTO official_alerts (source,event_type,description,magnitude,`datetime`) VALUES (?,?,?,?,?)')
        ->execute(['BMKG', 'Gempa bumi', (string)$g->Wilayah, $mag, date('Y-m-d H:i:s', strtotime((string)$g->DateTime))]);
} catch (Throwable $e) {}
$out = json_encode(['ok' => true, 'level' => $mag >= 5 ? 'merah' : 'kuning',
    'text' => sprintf('Gempa M%s • %s • %s • Kedalaman %s • %s', $g->Magnitude, $g->Tanggal . ' ' . $g->Jam, $g->Wilayah, $g->Kedalaman, $g->Potensi)]);
file_put_contents($cache, $out);
header('Content-Type: application/json'); echo $out;
