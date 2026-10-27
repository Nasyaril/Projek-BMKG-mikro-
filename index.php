<?php
require __DIR__ . '/config/db.php';
function ic(string $n): string { return '<svg class="i" aria-hidden="true"><use href="#i-' . $n . '"/></svg>'; }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>SIGAP — Laporan gangguan lingkungan</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
</head>
<body data-v="home">
<!-- Sprite ikon (gaya Feather, stroke mengikuti warna teks) -->
<svg width="0" height="0" style="position:absolute"><defs>
<symbol id="i-user" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></symbol>
<symbol id="i-file" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8"/></symbol>
<symbol id="i-pin" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></symbol>
<symbol id="i-camera" viewBox="0 0 24 24"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></symbol>
<symbol id="i-alert" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></symbol>
<symbol id="i-home" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></symbol>
<symbol id="i-map" viewBox="0 0 24 24"><path d="M1 6v16l7-4 8 4 7-4V2l-7 4-8-4z"/><path d="M8 2v16M16 6v16"/></symbol>
<symbol id="i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
<symbol id="i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></symbol>
<symbol id="i-gear" viewBox="0 0 24 24"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/></symbol>
<symbol id="i-bell" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9M13.73 21a2 2 0 0 1-3.46 0"/></symbol>
<symbol id="i-check" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></symbol>
<symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></symbol>
<symbol id="i-lock" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></symbol>
<symbol id="i-back" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></symbol>
<symbol id="i-send" viewBox="0 0 24 24"><path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/></symbol>
<symbol id="i-right" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></symbol>
<symbol id="i-down" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></symbol>
<symbol id="i-sliders" viewBox="0 0 24 24"><path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/></symbol>
<symbol id="i-mega" viewBox="0 0 24 24"><path d="M11 5L6 9H2v6h4l5 4z"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"/></symbol>
<symbol id="i-image" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></symbol>
<symbol id="i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></symbol>
<symbol id="i-cal" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></symbol>
<symbol id="i-moon" viewBox="0 0 24 24"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></symbol>
<symbol id="i-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4"/></symbol>
<symbol id="i-up" viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.3a2 2 0 0 0 2-1.7l1.4-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/></symbol>
<symbol id="i-phone" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/></symbol>
</defs></svg>

<div id="bmkg-bar" role="alert"><span></span></div>
<header class="top">
  <button id="back" class="icon" data-go="home" aria-label="Kembali"><?= ic('back') ?></button>
  <div id="brand"><span class="logo"><?= ic('shield') ?>SIGAP</span><button type="button" class="chip" id="area-btn" aria-haspopup="dialog"><?= ic('pin') ?><span id="area-t">Pilih wilayah</span><?= ic('down') ?></button></div>
  <h1 id="ptitle"></h1>
  <button id="sos-btn" class="sos-btn" aria-label="Darurat">SOS</button>
  <button id="theme" class="icon" aria-label="Ganti mode gelap/terang"><svg class="i"><use href="#i-moon"/></svg></button>
  <button id="bell" class="icon" data-go="notif" aria-label="Notifikasi"><?= ic('bell') ?><i class="dot-o" id="bell-dot" hidden></i></button>
</header>

<div class="app">
  <!-- Beranda & Peta -->
  <section id="v-home">
    <button class="banner" data-go="form"><?= ic('camera') ?><span><b>Buat Laporan</b><small>Ambil foto, pilih kategori, kirim lokasi</small></span></button>
    <button class="banner sos-banner" id="sos-open"><?= ic('alert') ?><span><b>Nomor Darurat</b><small>Daftar nomor yang bisa dihubungi segera</small></span></button>
    <div class="stats"><div class="sec stat"><b id="st-done">0</b>Laporan diselesaikan</div><div class="sec stat"><b id="st-avg">0</b>Rata-rata respon (menit)</div></div>
    <div id="map"></div>
    <div class="legend"><span class="badge">Tinggi</span><span class="badge yellow">Sedang</span><span class="badge green">Rendah</span></div>
    <div id="home-list"><h3 class="hd">Laporan Terbaru di Sekitar Anda</h3><div id="report-list"></div></div>
    <h3 class="hd">Edukasi &amp; Mitigasi Bencana</h3><div class="news" id="news"></div>
  </section>

  <!-- 1. Form Laporan -->
  <section id="v-form" hidden>
    <form id="report-form" enctype="multipart/form-data">
      <div class="sec"><div class="sh"><span class="ib"><?= ic('user') ?></span><h3>Identitas Pelapor</h3></div>
        <label for="rn">Nama Pelapor *</label><input id="rn" name="reporter_name" maxlength="100" required placeholder="Masukkan nama Anda">
        <label for="rc">No. HP / WhatsApp *</label><input id="rc" name="reporter_contact" inputmode="tel" required placeholder="Contoh: 0812 3456 7890"></div>
      <div class="sec"><div class="sh"><span class="ib"><?= ic('file') ?></span><h3>Detail Kejadian</h3></div>
        <label for="cat">Kategori Kejadian *</label>
        <select id="cat" name="category" required><option value="">Pilih kategori kejadian</option><option value="banjir">Banjir</option><option value="pohon_tumbang">Pohon tumbang</option><option value="lampu_mati">Lampu jalan mati</option><option value="jalan_rusak">Jalan rusak</option><option value="lainnya">Lainnya</option></select>
        <label for="ttl">Judul Kejadian *</label><input id="ttl" name="title" maxlength="150" required placeholder="Contoh: Pohon tumbang di Jl. Melati">
        <label for="desc">Deskripsi / Detail Kejadian *</label><textarea id="desc" name="description" rows="3" minlength="10" required placeholder="Jelaskan kejadian secara detail…"></textarea>
        <label for="tm">Waktu Kejadian *</label><input id="tm" type="datetime-local" name="incident_time" required></div>
      <div class="sec"><div class="sh"><span class="ib"><?= ic('pin') ?></span><h3>Lokasi Kejadian</h3></div>
        <span class="lb">Live Location (GPS)</span>
        <button type="button" class="rowbtn on" id="get-loc"><?= ic('pin') ?><span id="loc-t">Gunakan Lokasi Saya</span><?= ic('right') ?></button>
        <span class="lb">Ketuk peta untuk memindahkan pin (jika GPS tidak akurat)</span>
        <div id="mini-map"></div>
        <input type="hidden" id="f-prov" name="province"><input type="hidden" id="f-reg" name="regency"><input type="hidden" id="f-dis" name="district">
        <p class="lb" id="f-area"></p>
        <input type="hidden" id="lat" name="latitude"><input type="hidden" id="lng" name="longitude"></div>
      <div class="sec"><div class="sh"><span class="ib"><?= ic('camera') ?></span><h3>Upload Foto</h3></div>
        <label class="drop" for="photo"><?= ic('camera') ?><span id="photo-t">Ketuk untuk mengunggah foto <small>(Opsional)</small></span></label>
        <input id="photo" type="file" name="photo" accept="image/*" hidden></div>
      <div class="sec"><div class="sh"><span class="ib"><?= ic('alert') ?></span><h3>Dampak yang Terlihat *</h3></div><div class="checks">
        <label><input type="checkbox" name="impacts[]" value="jalan_terhalang">Jalan terhalang</label><label><input type="checkbox" name="impacts[]" value="gangguan_listrik">Gangguan listrik</label>
        <label><input type="checkbox" name="impacts[]" value="rumah_terdampak">Rumah terdampak</label><label><input type="checkbox" name="impacts[]" value="gangguan_akses">Mengganggu akses</label>
        <label><input type="checkbox" name="impacts[]" value="ada_korban">Ada korban</label><label><input type="checkbox" name="impacts[]" value="lainnya">Lainnya</label>
        <label><input type="checkbox" name="impacts[]" value="mengancam_keselamatan">Mengancam keselamatan</label></div></div>
      <button type="submit" class="btn-primary wide" id="submit-btn"><?= ic('send') ?>Kirim Laporan</button>
    </form>
  </section>

  <!-- 2. Notifikasi -->
  <section id="v-notif" hidden>
    <div class="tabs" id="n-tabs"><button class="on" data-f="all">Semua</button><button data-f="mine">Laporan Saya</button><button data-f="sys">Sistem</button></div>
    <div id="notif-list"></div>
    <div id="ok-panel" class="okbox" hidden><div class="big"><?= ic('check') ?></div><b>Laporan berhasil dikirim!</b>
      <p style="margin:8px 0 12px">Nomor laporan: <b id="ok-t"></b><br>Kode akses: <b id="ok-c"></b></p>
      <div style="display:flex;gap:8px"><button class="btn-ghost" style="flex:1" id="ok-detail">Lihat Detail</button><button class="btn-primary" style="flex:1" id="ok-status">Cek Status Laporan</button></div></div>
  </section>

  <!-- 3. Cek Status -->
  <section id="v-status" hidden>
    <div class="info"><?= ic('search') ?><span>Masukkan nomor laporan dan kode akses untuk melihat status laporan Anda.</span></div>
    <form id="status-form">
      <div class="field"><?= ic('file') ?><div style="flex:1"><label for="st-t">Nomor Laporan</label><input id="st-t" required placeholder="Contoh: SIGAP-260930-00421"></div></div>
      <div class="field"><?= ic('lock') ?><div style="flex:1"><label for="st-c">Kode Akses</label><input id="st-c" required maxlength="8" placeholder="Contoh: 8XK4P2"></div></div>
      <button class="btn-primary wide"><?= ic('search') ?>Cek Laporan</button>
    </form>
    <div id="status-result"></div>
  </section>

  <!-- 4. Riwayat -->
  <section id="v-history" hidden>
    <div class="tabs" id="h-tabs"><button class="on" data-f="all">Semua</button><button data-f="proses">Diproses</button><button data-f="selesai">Selesai</button></div>
    <div class="filter"><?= ic('sliders') ?><select id="h-cat" aria-label="Kategori"><option value="">Semua Kategori</option><option value="banjir">Banjir</option><option value="pohon_tumbang">Pohon tumbang</option><option value="lampu_mati">Lampu jalan mati</option><option value="jalan_rusak">Jalan rusak</option><option value="lainnya">Lainnya</option></select></div>
    <div id="history-list" style="margin-top:12px"></div>
  </section>

  <section id="v-setting" hidden>
    <div class="sec"><h3>Tentang SIGAP</h3><p>Sistem Informasi Gangguan Area Pemukiman. Setiap laporan dicek pengurus RT/RW sebelum peringatan disebarkan.</p>
      <button class="btn-ghost wide" data-go="status" style="margin-top:10px">Cek Status Laporan</button></div>
  </section>
</div>

<nav class="tabbar" aria-label="Navigasi utama">
  <button data-go="home" data-t="home"><?= ic('home') ?><span>Beranda</span></button>
  <button data-go="map" data-t="map"><?= ic('map') ?><span>Peta</span></button>
  <button class="plus" data-go="form" aria-label="Buat laporan"><?= ic('plus') ?></button>
  <button data-go="history" data-t="history"><?= ic('clock') ?><span>Riwayat</span></button>
  <button data-go="setting" data-t="setting"><?= ic('gear') ?><span>Setting</span></button>
</nav>

<!-- Nomor darurat (sementara: daftar teks, tanpa input) -->
<div class="modal" id="sos-modal" role="dialog" aria-label="Nomor darurat"><div class="box">
  <h3 style="color:var(--brand)"><?= ic('alert') ?> Nomor Darurat</h3>
  <p style="font-size:.85rem;color:var(--muted)">Nyawa terancam? Segera hubungi nomor berikut (gratis).</p>
  <ul class="numlist">
    <li><b>112</b><span>Panggilan darurat terpadu (belum tersedia di semua daerah)</span><button type="button" class="callbtn" data-num="112" data-name="Panggilan darurat terpadu" aria-label="Panggil 112 (Panggilan darurat terpadu)"><?= ic('phone') ?></button></li>
    <li><b>113</b><span>Pemadam kebakaran</span><button type="button" class="callbtn" data-num="113" data-name="Pemadam kebakaran" aria-label="Panggil 113 (Pemadam kebakaran)"><?= ic('phone') ?></button></li>
    <li><b>119</b><span>Ambulans / darurat medis (juga 118)</span><button type="button" class="callbtn" data-num="119" data-name="Ambulans / darurat medis" aria-label="Panggil 119 (Ambulans / darurat medis)"><?= ic('phone') ?></button></li>
    <li><b>110</b><span>Polisi</span><button type="button" class="callbtn" data-num="110" data-name="Polisi" aria-label="Panggil 110 (Polisi)"><?= ic('phone') ?></button></li>
    <li><b>115</b><span>Basarnas: pencarian dan pertolongan (SAR)</span><button type="button" class="callbtn" data-num="115" data-name="Basarnas (SAR)" aria-label="Panggil 115 (Basarnas (SAR))"><?= ic('phone') ?></button></li>
  </ul>
  <button type="button" class="btn-primary wide" id="sos-close">Tutup</button>
</div></div>

<!-- Pemilih wilayah -->
<div class="modal" id="area-modal" role="dialog" aria-label="Pilih wilayah"><div class="box">
  <h3>Pilih wilayah</h3><p style="color:var(--muted);font-size:.85rem">Peta, daftar laporan, dan laporan baru akan mengikuti wilayah ini.</p>
  <label for="a-prov">Provinsi</label><select id="a-prov"></select>
  <label for="a-reg">Kota / Kabupaten</label><select id="a-reg" disabled><option>Pilih provinsi dulu</option></select>
  <label for="a-dis">Kecamatan</label><select id="a-dis" disabled><option>Pilih kota/kabupaten dulu</option></select>
  <div class="row" style="margin-top:16px"><button type="button" class="btn-ghost" id="a-cancel">Batal</button><button type="button" class="btn-ghost" id="a-reset">Reset</button><button type="button" class="btn-primary" id="a-ok">Terapkan</button></div>
</div></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.10.8/sweetalert2.all.min.js"></script>
<script src="assets/js/map.js?v=<?= filemtime(__DIR__ . '/assets/js/map.js') ?>"></script>
<script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
<script src="assets/js/sos.js?v=<?= filemtime(__DIR__ . '/assets/js/sos.js') ?>"></script>
</body>
</html>
