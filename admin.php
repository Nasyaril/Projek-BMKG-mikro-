<?php
require __DIR__ . '/config/db.php';
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
if (isset($_GET['logout'])) { session_destroy(); header('Location: admin.php'); exit; }

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $st = $pdo->prepare("SELECT id, password_hash FROM users WHERE username=? AND role='admin'");
    $st->execute([$_POST['username']]);
    $u = $st->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = $u['id']; $_SESSION['role'] = 'admin'; $_SESSION['name'] = $_POST['username'];
        header('Location: admin.php'); exit;
    }
    $err = 'Username atau password salah.';
}
$logged = ($_SESSION['role'] ?? '') === 'admin';
if ($logged) {
    $cnt = fn(string $w) => (int)$pdo->query("SELECT COUNT(*) FROM reports $w")->fetchColumn();
    $total = $cnt(''); $wait = $cnt("WHERE current_status='menunggu'");
    $proc = $cnt("WHERE current_status IN ('diverifikasi','diproses')"); $done = $cnt("WHERE current_status='selesai'");
    $rows = $pdo->query("SELECT * FROM reports WHERE current_status='menunggu' ORDER BY created_at")->fetchAll();
    $alerts = $pdo->query('SELECT * FROM official_alerts ORDER BY `datetime` DESC LIMIT 8')->fetchAll();
    $recent = $pdo->query('SELECT ticket_number, title, category, current_status, created_at FROM reports ORDER BY created_at DESC LIMIT 6')->fetchAll();
    $running = $pdo->query("SELECT id, ticket_number, title, category, current_status, created_at FROM reports WHERE is_emergency=0 AND current_status IN ('diverifikasi','diproses') ORDER BY created_at DESC")->fetchAll();
    $badge = ['menunggu' => 'bg-gray-200 text-gray-700', 'diverifikasi' => 'bg-blue-100 text-blue-700', 'diproses' => 'bg-yellow-100 text-yellow-700', 'selesai' => 'bg-green-100 text-green-700', 'ditolak' => 'bg-red-100 text-red-700'];
    // Sinyal darurat aktif (masuk tanpa konfirmasi RT/RW) + penanda terbaru untuk polling
    $sos = $pdo->query("SELECT * FROM reports WHERE is_emergency=1 AND current_status IN ('diverifikasi','diproses') ORDER BY created_at DESC")->fetchAll();
    $sosLatest = (int)$pdo->query('SELECT COALESCE(MAX(id),0) FROM reports WHERE is_emergency=1')->fetchColumn();
    $waNum = fn(string $p) => preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $p));
}
$nice = fn(string $s) => ucwords(str_replace('_', ' ', $s));
?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>SIGAP Admin</title>
<script>try{if(localStorage.getItem('sigap_theme')==='dark')document.documentElement.classList.add('dark')}catch(e){}</script>
<script src="https://cdn.tailwindcss.com"></script>
<script>tailwind.config={darkMode:'class',theme:{extend:{colors:{brand:'#0D47A1'},fontFamily:{sans:['Plus Jakarta Sans','system-ui','sans-serif']}}}}</script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
<style>
 .glass{background:rgba(255,255,255,.7);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,.3)}
 .dark .glass{background:rgba(30,41,59,.7);border-color:rgba(255,255,255,.08)}

 .nav-btn.on{background:rgba(255,255,255,.18)}.pill.on{background:#0D47A1;color:#fff}
 ::-webkit-scrollbar{width:8px;height:8px}::-webkit-scrollbar-thumb{background:#94a3b8;border-radius:8px}
</style></head>
<body class="bg-gray-100 dark:bg-slate-900 text-gray-800 dark:text-gray-100 font-sans">

<?php if (!$logged): ?>
<form method="post" class="glass shadow-xl rounded-2xl max-w-sm mx-auto mt-[12vh] p-8">
  <h1 class="text-2xl font-extrabold text-brand dark:text-blue-400">SIGAP Admin</h1>
  <p class="text-sm text-gray-500 mb-4">Khusus pengurus yang ditunjuk.</p>
  <?php if ($err): ?><p class="text-red-500 text-sm mb-2"><?= e($err) ?></p><?php endif; ?>
  <label class="text-sm font-semibold" for="u">Username</label>
  <input id="u" name="username" required autocomplete="username" class="w-full mt-1 mb-3 px-3 py-2.5 rounded-lg border dark:bg-slate-800 dark:border-slate-600">
  <label class="text-sm font-semibold" for="p">Password</label>
  <input id="p" name="password" type="password" required autocomplete="current-password" class="w-full mt-1 mb-5 px-3 py-2.5 rounded-lg border dark:bg-slate-800 dark:border-slate-600">
  <button class="w-full bg-brand hover:opacity-90 transition text-white font-semibold py-2.5 rounded-lg">Masuk</button>
</form>

<?php else: ?>
<aside class="fixed inset-y-0 left-0 w-60 bg-brand text-white p-5 hidden md:flex flex-col">
  <div class="text-2xl font-extrabold mb-8">SIGAP</div>
  <nav class="flex-1 space-y-1 text-sm font-semibold">
    <button data-nav="dashboard" class="nav-btn w-full text-left px-3 py-2.5 rounded-lg hover:bg-white/10 transition">📊 Dashboard</button>
    <button data-nav="darurat" class="nav-btn w-full text-left px-3 py-2.5 rounded-lg hover:bg-white/10 transition flex justify-between">🚨 Darurat<?php if ($sos): ?><span class="bg-red-500 text-xs rounded-full px-2 py-0.5 animate-pulse"><?= count($sos) ?></span><?php endif; ?></button>
    <button data-nav="triage" class="nav-btn w-full text-left px-3 py-2.5 rounded-lg hover:bg-white/10 transition flex justify-between">📋 Triage laporan<?php if ($wait): ?><span class="bg-red-500 text-xs rounded-full px-2 py-0.5"><?= $wait ?></span><?php endif; ?></button>
    <button data-nav="alerts" class="nav-btn w-full text-left px-3 py-2.5 rounded-lg hover:bg-white/10 transition">🌐 Peringatan resmi</button>
    <a href="index.php" target="_blank" class="block px-3 py-2.5 rounded-lg hover:bg-white/10 transition">🏠 Lihat situs warga</a>
  </nav>
  <a href="?logout=1" class="px-3 py-2.5 rounded-lg hover:bg-white/10 transition text-sm font-semibold">⎋ Keluar</a>
</aside>

<div class="md:ml-60">
  <header class="glass sticky top-0 z-30 flex items-center justify-between px-6 py-3 shadow-sm">
    <h1 id="vtitle" class="font-extrabold text-lg">Dashboard Admin</h1>
    <div class="flex items-center gap-3 text-sm">
      <button id="theme" class="w-10 h-10 rounded-full hover:bg-gray-200 dark:hover:bg-slate-700 transition" aria-label="Mode gelap/terang">🌓</button>
      <span class="font-semibold"><?= e($_SESSION['name'] ?? 'admin') ?></span>
      <a href="?logout=1" class="md:hidden text-brand">Keluar</a>
    </div>
  </header>
  <div class="md:hidden flex gap-2 px-6 pt-4 overflow-x-auto text-sm font-semibold">
    <button data-nav="dashboard" class="pill px-4 py-2 rounded-full bg-white dark:bg-slate-800 shadow whitespace-nowrap">Dashboard</button>
    <button data-nav="darurat" class="pill px-4 py-2 rounded-full bg-red-600 text-white shadow whitespace-nowrap">🚨 Darurat<?= $sos ? " (" . count($sos) . ")" : '' ?></button>
    <button data-nav="triage" class="pill px-4 py-2 rounded-full bg-white dark:bg-slate-800 shadow whitespace-nowrap">Triage<?= $wait ? " ($wait)" : '' ?></button>
    <button data-nav="alerts" class="pill px-4 py-2 rounded-full bg-white dark:bg-slate-800 shadow whitespace-nowrap">Peringatan resmi</button>
  </div>

  <main class="p-6">
    <div data-view="dashboard" class="space-y-8">
    <?php if ($sos): ?><button data-nav="darurat" class="w-full text-left bg-red-600 text-white rounded-2xl p-4 shadow-lg animate-pulse font-semibold">🚨 <?= count($sos) ?> sinyal darurat aktif — tangani sekarang →</button><?php endif; ?>
    <section class="grid grid-cols-2 xl:grid-cols-4 gap-4">
      <?php foreach ([['Total laporan', $total, '📋', 'text-brand dark:text-blue-400'], ['Perlu verifikasi', $wait, '⏳', 'text-red-500'], ['Diproses', $proc, '🛠️', 'text-yellow-500'], ['Selesai', $done, '✅', 'text-green-500']] as [$lbl, $n, $emo, $cls]): ?>
        <div class="glass relative overflow-hidden rounded-2xl p-5 shadow-md">
          <span class="absolute -right-2 -bottom-4 text-7xl opacity-10"><?= $emo ?></span>
          <div class="text-sm text-gray-500 dark:text-gray-400"><?= $lbl ?></div>
          <div class="text-4xl font-extrabold <?= $cls ?>"><?= $n ?></div>
        </div>
      <?php endforeach; ?>
    </section>

    <section>
      <div class="flex justify-between items-center mb-3"><h2 class="font-extrabold text-lg">Laporan terbaru</h2>
        <button data-nav="triage" class="text-sm font-semibold text-brand dark:text-blue-400 hover:underline">Buka triage →</button></div>
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-md overflow-x-auto"><table class="w-full text-sm text-left">
        <thead class="bg-gray-50 dark:bg-slate-700 text-gray-500 dark:text-gray-300"><tr><th class="p-3">Tiket</th><th class="p-3">Judul</th><th class="p-3">Status</th><th class="p-3">Waktu</th></tr></thead><tbody>
        <?php foreach ($recent as $x): ?><tr class="border-t dark:border-slate-700"><td class="p-3 font-mono"><?= e($x['ticket_number']) ?></td><td class="p-3"><?= e($x['title']) ?></td>
          <td class="p-3"><span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $badge[$x['current_status']] ?? '' ?>"><?= e($x['current_status']) ?></span></td><td class="p-3 whitespace-nowrap"><?= e(date('d M Y H:i', strtotime($x['created_at']))) ?></td></tr>
        <?php endforeach; if (!$recent): ?><tr><td colspan="4" class="p-6 text-center text-gray-500">Belum ada laporan masuk.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
    </div>

    <section id="darurat" data-view="darurat" class="hidden space-y-4">
      <h2 class="font-extrabold text-lg">Sinyal Darurat Aktif</h2>
      <p class="text-sm text-gray-500 dark:text-gray-400">Masuk langsung tanpa konfirmasi RT/RW. Hubungi pelapor, koordinasikan penanganan, dan tandai palsu bila terbukti.</p>
      <?php foreach ($sos as $s): $tel = preg_replace('/[^0-9+]/', '', $s['reporter_contact']); ?>
        <article class="bg-white dark:bg-slate-800 rounded-2xl shadow-md border-l-8 border-red-500 p-5">
          <div class="flex flex-wrap justify-between gap-2">
            <h3 class="font-extrabold text-red-600"><?= e($s['title']) ?></h3>
            <span class="text-xs px-2.5 py-1 rounded-full font-semibold <?= $s['current_status'] === 'diproses' ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700' ?>"><?= $s['current_status'] === 'diproses' ? 'Sedang ditangani' : 'Belum ditangani' ?></span>
          </div>
          <p class="text-sm mt-1"><b><?= e($s['reporter_name'] ?: 'Tanpa nama') ?></b> • <?= e($s['reporter_contact']) ?> • <?= e(date('d M H:i', strtotime($s['created_at']))) ?></p>
          <p class="text-sm mt-2"><?= e($s['description']) ?></p>
          <p class="text-xs text-gray-500 mt-1"><?= e(trim(($s['district'] ?? '') . ', ' . ($s['regency'] ?? ''), ', ')) ?> • <?= e($s['latitude']) ?>, <?= e($s['longitude']) ?></p>
          <div class="flex flex-wrap gap-2 mt-3 text-sm font-semibold">
            <a href="tel:<?= e($tel) ?>" class="px-4 py-2.5 rounded-lg bg-red-600 text-white hover:opacity-90 transition">📞 Telepon</a>
            <a href="https://wa.me/<?= e($waNum($s['reporter_contact'])) ?>" target="_blank" rel="noopener" class="px-4 py-2.5 rounded-lg bg-green-600 text-white hover:opacity-90 transition">WhatsApp</a>
            <a href="https://maps.google.com/?q=<?= e($s['latitude']) ?>,<?= e($s['longitude']) ?>" target="_blank" rel="noopener" class="px-4 py-2.5 rounded-lg bg-brand text-white hover:opacity-90 transition">📍 Buka peta</a>
          </div>
          <div class="flex flex-wrap gap-2 mt-3 text-sm font-semibold border-t dark:border-slate-700 pt-3">
            <?php if ($s['current_status'] === 'diverifikasi'): ?><button data-id="<?= (int)$s['id'] ?>" data-act="proses" class="sos-act px-4 py-2 rounded-lg bg-yellow-400 text-gray-900 hover:brightness-95 transition">Tandai sedang ditangani</button><?php endif; ?>
            <button data-id="<?= (int)$s['id'] ?>" data-act="selesai" class="sos-act px-4 py-2 rounded-lg border-2 border-green-600 text-green-600 hover:bg-green-50 dark:hover:bg-green-950 transition">Selesai</button>
            <button data-id="<?= (int)$s['id'] ?>" data-act="palsu" class="sos-act px-4 py-2 rounded-lg border-2 border-red-500 text-red-500 hover:bg-red-50 dark:hover:bg-red-950 transition">Laporan palsu</button>
          </div>
        </article>
      <?php endforeach; if (!$sos): ?><div class="bg-white dark:bg-slate-800 rounded-2xl shadow-md p-8 text-center text-gray-500">Tidak ada sinyal darurat aktif. 🎉</div><?php endif; ?>
    </section>

    <section id="triage" data-view="triage" class="hidden">
      <h2 class="font-extrabold text-lg mb-3">Triage — menunggu verifikasi</h2>
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-md overflow-x-auto">
        <table class="w-full text-sm text-left">
          <thead class="bg-gray-50 dark:bg-slate-700 text-gray-500 dark:text-gray-300"><tr>
            <th class="p-3">Tiket</th><th class="p-3">Foto</th><th class="p-3">Kategori</th><th class="p-3">Waktu</th><th class="p-3 text-right">Action</th></tr></thead>
          <tbody>
          <?php foreach ($rows as $r): ?>
            <tr class="border-t dark:border-slate-700 hover:bg-blue-50/60 dark:hover:bg-slate-700/60 transition">
              <td class="p-3 font-mono"><?= e($r['ticket_number']) ?></td>
              <td class="p-3"><?php if ($r['photo_before_path']): ?><img src="<?= e($r['photo_before_path']) ?>" alt="" class="w-12 h-12 rounded-lg object-cover"><?php else: ?><div class="w-12 h-12 rounded-lg bg-gray-200 dark:bg-slate-600"></div><?php endif; ?></td>
              <td class="p-3"><b><?= e($r['title']) ?></b><div class="text-gray-500"><?= e($nice($r['category'])) ?></div><div class="text-xs text-gray-400"><?= e(trim(($r['district'] ?? '') . ', ' . ($r['regency'] ?? ''), ', ')) ?></div></td>
              <td class="p-3 whitespace-nowrap"><?= e(date('d M Y H:i', strtotime($r['created_at']))) ?></td>
              <td class="p-3 text-right"><button class="open-v bg-brand text-white px-4 py-2 rounded-lg hover:opacity-90 transition"
                data-id="<?= (int)$r['id'] ?>" data-title="<?= e($r['title']) ?>" data-cat="<?= e($nice($r['category'])) ?>" data-desc="<?= e($r['description']) ?>"
                data-photo="<?= e($r['photo_before_path']) ?>" data-lat="<?= e($r['latitude']) ?>" data-lng="<?= e($r['longitude']) ?>"
                data-by="<?= e(($r['reporter_name'] ?? '-') . ' • ' . ($r['reporter_contact'] ?? '-')) ?>" data-impacts="<?= e(str_replace('_', ' ', $r['impacts'] ?? '')) ?>">Tinjau</button></td>
            </tr>
          <?php endforeach; if (!$rows): ?><tr><td colspan="5" class="p-6 text-center text-gray-500">Tidak ada laporan yang menunggu. 🎉</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <h2 class="font-extrabold text-lg mt-8 mb-3">Penanganan — laporan yang sudah diterima</h2>
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-md overflow-x-auto">
        <table class="w-full text-sm text-left">
          <thead class="bg-gray-50 dark:bg-slate-700 text-gray-500 dark:text-gray-300"><tr><th class="p-3">Tiket</th><th class="p-3">Laporan</th><th class="p-3">Status</th><th class="p-3 text-right">Action</th></tr></thead>
          <tbody>
          <?php foreach ($running as $x): ?>
            <tr class="border-t dark:border-slate-700">
              <td class="p-3 font-mono"><?= e($x['ticket_number']) ?></td>
              <td class="p-3"><b><?= e($x['title']) ?></b><div class="text-gray-500"><?= e($nice($x['category'])) ?></div></td>
              <td class="p-3"><span class="px-2.5 py-1 rounded-full text-xs font-semibold <?= $badge[$x['current_status']] ?? '' ?>"><?= e($x['current_status']) ?></span></td>
              <td class="p-3 text-right whitespace-nowrap">
                <?php if ($x['current_status'] === 'diverifikasi'): ?><button data-id="<?= (int)$x['id'] ?>" data-act="proses" class="run-act px-3 py-2 rounded-lg bg-yellow-400 text-gray-900 font-semibold hover:brightness-95 transition">Tandai ditangani</button><?php endif; ?>
                <button data-id="<?= (int)$x['id'] ?>" data-act="selesai" class="run-act px-3 py-2 rounded-lg border-2 border-green-600 text-green-600 font-semibold hover:bg-green-50 dark:hover:bg-green-950 transition">Selesai</button>
              </td>
            </tr>
          <?php endforeach; if (!$running): ?><tr><td colspan="4" class="p-6 text-center text-gray-500">Belum ada laporan yang sedang berjalan.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section id="alerts" data-view="alerts" class="hidden">
      <h2 class="font-extrabold text-lg mb-3">Peringatan resmi (BMKG)</h2>
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-md overflow-x-auto">
        <table class="w-full text-sm text-left"><thead class="bg-gray-50 dark:bg-slate-700 text-gray-500 dark:text-gray-300"><tr><th class="p-3">Sumber</th><th class="p-3">Kejadian</th><th class="p-3">Wilayah</th><th class="p-3">Magnitudo</th><th class="p-3">Waktu</th></tr></thead><tbody>
        <?php foreach ($alerts as $a): ?><tr class="border-t dark:border-slate-700"><td class="p-3"><?= e($a['source']) ?></td><td class="p-3"><?= e($a['event_type']) ?></td><td class="p-3"><?= e($a['description']) ?></td><td class="p-3"><?= e($a['magnitude']) ?></td><td class="p-3"><?= e($a['datetime']) ?></td></tr>
        <?php endforeach; if (!$alerts): ?><tr><td colspan="5" class="p-6 text-center text-gray-500">Belum ada data. Buka beranda warga sekali agar data BMKG tersimpan.</td></tr><?php endif; ?>
        </tbody></table>
      </div>
    </section>
  </main>
</div>

<!-- Modal verifikasi -->
<div id="vmodal" class="hidden fixed inset-0 z-50 bg-black/50 backdrop-blur-sm items-center justify-center p-4">
  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-5xl max-h-[92vh] overflow-auto grid md:grid-cols-2">
    <div class="p-5 space-y-3 bg-gray-50 dark:bg-slate-900">
      <img id="v-photo" src="" alt="Foto laporan" class="w-full h-64 object-cover rounded-xl hidden">
      <div id="v-nophoto" class="w-full h-24 rounded-xl bg-gray-200 dark:bg-slate-700 grid place-items-center text-gray-500">Tidak ada foto</div>
      <div id="vmap" class="w-full h-56 rounded-xl border dark:border-slate-600"></div>
    </div>
    <div class="p-6 flex flex-col">
      <div class="text-xs font-semibold text-brand dark:text-blue-400" id="v-cat"></div>
      <h3 class="text-xl font-extrabold" id="v-title"></h3>
      <p class="text-sm text-gray-500 mt-1" id="v-by"></p>
      <p class="mt-3 text-sm" id="v-desc"></p>
      <p class="mt-2 text-xs text-gray-500">Dampak: <span id="v-impacts"></span></p>
      <label for="v-prio" class="mt-4 text-sm font-semibold">Prioritas</label>
      <select id="v-prio" class="mt-1 px-3 py-2.5 rounded-lg border dark:bg-slate-800 dark:border-slate-600">
        <option value="">Pilih prioritas</option><option value="tinggi">🔴 Tinggi</option><option value="sedang">🟡 Sedang</option><option value="rendah">🟢 Rendah</option></select>
      <label for="v-instr" class="mt-4 text-sm font-semibold">Instruksi Khusus (Contoh: Rute alternatif dari Pak RT)</label>
      <textarea id="v-instr" class="mt-1 h-40 px-3 py-2.5 rounded-lg border dark:bg-slate-800 dark:border-slate-600" placeholder="Hasil konfirmasi ke Ketua RT/RW. Untuk penolakan, tulis alasannya di sini."></textarea>
      <div class="mt-5 flex flex-wrap gap-2 justify-end">
        <button data-act="tolak" class="act px-4 py-2.5 rounded-lg border-2 border-red-500 text-red-500 font-semibold hover:bg-red-50 dark:hover:bg-red-950 transition">Tolak</button>
        <button data-act="eskalasi" class="act px-4 py-2.5 rounded-lg bg-yellow-400 text-gray-900 font-semibold hover:brightness-95 transition">Eskalasi Instansi</button>
        <button data-act="terima" class="act px-4 py-2.5 rounded-lg bg-brand text-white font-semibold hover:opacity-90 transition">Terima &amp; Broadcast WA</button>
      </div>
      <button id="v-close" class="mt-3 text-sm text-gray-500 hover:underline self-end">Tutup</button>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.10.8/sweetalert2.all.min.js"></script>
<script>
  const $ = s => document.querySelector(s);
  // Navigasi antar tampilan (tanpa reload); tampilan terakhir diingat lewat #hash
  const TITLES = {darurat: 'Sinyal Darurat', dashboard: 'Dashboard Admin', triage: 'Triage Laporan', alerts: 'Peringatan Resmi'};
  function show(v) {
    if (!TITLES[v]) v = 'dashboard';
    document.querySelectorAll('[data-view]').forEach(el => el.classList.toggle('hidden', el.dataset.view !== v));
    document.querySelectorAll('[data-nav]').forEach(b => b.classList.toggle('on', b.dataset.nav === v));
    $('#vtitle').textContent = TITLES[v]; history.replaceState(null, '', '#' + v); scrollTo(0, 0);
  }
  document.querySelectorAll('[data-nav]').forEach(b => b.onclick = () => show(b.dataset.nav));
  show(location.hash.slice(1));

  // Aksi sinyal darurat: sedang ditangani / selesai / palsu
  document.querySelectorAll('.sos-act').forEach(b => b.onclick = async () => {
    const t = {proses: 'Tandai sedang ditangani?', selesai: 'Tandai selesai?', palsu: 'Tandai sebagai laporan palsu? Titik akan dihapus dari peta.'}[b.dataset.act];
    if (!(await Swal.fire({title: t, icon: 'question', showCancelButton: true, confirmButtonText: 'Ya', cancelButtonText: 'Batal'})).isConfirmed) return;
    const fd = new FormData(); fd.append('id', b.dataset.id); fd.append('action', b.dataset.act);
    const r = await (await fetch('api/emergency_action.php', {method: 'POST', body: fd})).json();
    if (r.ok) location.reload(); else Swal.fire('Gagal', r.message, 'error');
  });

  // Aksi penanganan laporan biasa: sedang ditangani / selesai (+ catatan opsional untuk warga)
  document.querySelectorAll('.run-act').forEach(b => b.onclick = async () => {
    const t = {proses: 'Tandai sedang ditangani?', selesai: 'Tandai selesai?'}[b.dataset.act];
    const q = await Swal.fire({title: t, input: 'text', inputPlaceholder: 'Catatan untuk warga (opsional)', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya', cancelButtonText: 'Batal'});
    if (!q.isConfirmed) return;
    const fd = new FormData(); fd.append('id', b.dataset.id); fd.append('action', b.dataset.act); fd.append('note', q.value || '');
    const r = await (await fetch('api/update_status.php', {method: 'POST', body: fd})).json();
    if (r.ok) location.reload(); else Swal.fire('Gagal', r.message, 'error');
  });

  // Polling tiap 15 detik: bunyi + peringatan saat ada sinyal darurat baru
  let lastSos = <?= $sosLatest ?>;
  function beep() { try { const c = new (window.AudioContext || window.webkitAudioContext)(); [0, .25, .5].forEach(t => { const o = c.createOscillator(), g = c.createGain(); o.connect(g); g.connect(c.destination); o.frequency.value = 880; g.gain.value = .25; o.start(c.currentTime + t); o.stop(c.currentTime + t + .15); }); } catch {} }
  setInterval(async () => {
    try {
      const r = await (await fetch('api/emergency_poll.php')).json();
      if (r.ok && r.latest > lastSos) {
        lastSos = r.latest; beep(); document.title = '🚨 DARURAT BARU — SIGAP Admin';
        await Swal.fire({icon: 'warning', title: 'Sinyal darurat baru!', text: 'Buka tab Darurat dan hubungi pelapor.', confirmButtonText: 'Buka'});
        location.hash = 'darurat'; location.reload();
      }
    } catch {}
  }, 15000);

  $('#theme').onclick = () => { const d = document.documentElement.classList.toggle('dark'); try { localStorage.setItem('sigap_theme', d ? 'dark' : 'light'); } catch {} };

  let cur = null, vm, vmark;
  const modal = $('#vmodal');
  document.querySelectorAll('.open-v').forEach(b => b.onclick = () => {
    const d = b.dataset; cur = d.id;
    $('#v-cat').textContent = d.cat; $('#v-title').textContent = d.title; $('#v-by').textContent = 'Pelapor: ' + d.by;
    $('#v-desc').textContent = d.desc; $('#v-impacts').textContent = d.impacts || '-';
    $('#v-photo').classList.toggle('hidden', !d.photo); $('#v-nophoto').classList.toggle('hidden', !!d.photo); $('#v-photo').src = d.photo || '';
    $('#v-prio').value = ''; $('#v-instr').value = '';
    modal.classList.remove('hidden'); modal.classList.add('flex');
    if (!vm) { // peta mini lokasi kejadian
      vm = L.map('vmap'); L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(vm);
    }
    vm.setView([d.lat, d.lng], 16); (vmark ||= L.marker([d.lat, d.lng]).addTo(vm)).setLatLng([d.lat, d.lng]);
    setTimeout(() => vm.invalidateSize(), 100);
  });
  const close = () => { modal.classList.add('hidden'); modal.classList.remove('flex'); };
  $('#v-close').onclick = close; modal.onclick = e => { if (e.target === modal) close(); };

  document.querySelectorAll('.act').forEach(btn => btn.onclick = async () => {
    const act = btn.dataset.act;
    const ask = {tolak: 'Tolak laporan ini?', eskalasi: 'Eskalasi ke instansi?', terima: 'Terima dan broadcast ke grup warga?'}[act];
    if (!(await Swal.fire({title: ask, text: 'Pastikan sudah konfirmasi ke Ketua RT/RW.', icon: 'question', showCancelButton: true, confirmButtonText: 'Ya, lanjut', cancelButtonText: 'Batal'})).isConfirmed) return;
    const fd = new FormData(); fd.append('id', cur); fd.append('action', act);
    fd.append('priority', $('#v-prio').value); fd.append('instruction', $('#v-instr').value);
    const res = await (await fetch('api/verify_report.php', {method: 'POST', body: fd})).json();
    if (!res.ok) return Swal.fire('Gagal', res.message, 'error');
    const w = res.wa;
    await Swal.fire({icon: 'success', title: 'Tersimpan',
      text: act === 'tolak' ? 'Laporan ditolak.' : (w.simulated ? 'Mode simulasi: pesan WA belum dikirim sungguhan.' : (w.sent ? 'Pesan WA terkirim.' : 'Status tersimpan, tetapi WA gagal terkirim.'))});
    location.reload();
  });
</script>
<?php endif; ?>
</body></html>