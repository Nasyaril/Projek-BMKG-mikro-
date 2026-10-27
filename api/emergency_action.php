<?php
require __DIR__ . '/../config/db.php';
api_boot();
session_start();
if (($_SESSION['role'] ?? '') !== 'admin') json_out(['ok' => false, 'message' => 'Akses ditolak'], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_out(['ok' => false, 'message' => 'Metode tidak diizinkan'], 405);
$id = filter_var($_POST['id'] ?? '', FILTER_VALIDATE_INT);
$map = ['proses' => 'diproses', 'selesai' => 'selesai', 'palsu' => 'ditolak'];
$act = $_POST['action'] ?? '';
if (!$id || !isset($map[$act])) json_out(['ok' => false, 'message' => 'Permintaan tidak valid'], 422);

$pdo->beginTransaction();
$st = $pdo->prepare("SELECT id FROM reports WHERE id=? AND is_emergency=1 AND current_status IN ('diverifikasi','diproses') FOR UPDATE");
$st->execute([$id]);
if (!$st->fetch()) { $pdo->rollBack(); json_out(['ok' => false, 'message' => 'Sinyal tidak ditemukan atau sudah ditutup'], 404); }
$pdo->prepare('UPDATE reports SET current_status=?, admin_instruction=COALESCE(?, admin_instruction) WHERE id=?')
    ->execute([$map[$act], $act === 'palsu' ? 'Ditandai laporan darurat palsu oleh admin.' : null, $id]);
$pdo->prepare('INSERT INTO status_history (report_id,status_name,changed_by_user_id) VALUES (?,?,?)')->execute([$id, $map[$act], $_SESSION['uid']]);
$pdo->commit();
json_out(['ok' => true]);
