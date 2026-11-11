<?php
require __DIR__ . '/../config/db.php';
api_boot();
session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Metode tidak diizinkan'], 405);
$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
if (!$id) json_out(['ok' => false, 'message' => 'ID tidak valid'], 422);
// Cegah dobel suara dari sesi yang sama (klien juga menyimpan penanda di localStorage).
if (in_array($id, $_SESSION['voted'] ?? [], true)) json_out(['ok' => false, 'message' => 'Anda sudah menandai laporan ini.'], 409);
$st = $pdo->prepare("UPDATE reports SET upvote_count = upvote_count + 1 WHERE id=? AND current_status IN ('diverifikasi','diproses')");
$st->execute([$id]);
if (!$st->rowCount()) json_out(['ok' => false, 'message' => 'Laporan tidak ditemukan.'], 404);
$_SESSION['voted'][] = $id;
$c = $pdo->prepare('SELECT upvote_count FROM reports WHERE id=?'); $c->execute([$id]);
json_out(['ok' => true, 'count' => (int)$c->fetchColumn()]);
