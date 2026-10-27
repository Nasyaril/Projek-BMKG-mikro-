const $ = s => document.querySelector(s);
const VIEWS = ['home', 'form', 'notif', 'status', 'history', 'setting'];
const TITLES = {notif: 'Notifikasi', status: 'Cek Status Laporan', history: 'Riwayat Laporan', setting: 'Setting'};

// ---- Router sederhana (tanpa reload) ----
function go(v) {
  const full = v === 'map'; if (full) v = 'home';
  VIEWS.forEach(x => $('#v-' + x).hidden = x !== v);
  document.body.dataset.v = v; $('#ptitle').textContent = TITLES[v] || '';
  document.body.classList.toggle('full-map', full);
  document.querySelectorAll('.tabbar [data-t]').forEach(b => b.classList.toggle('on', b.dataset.t === (full ? 'map' : v)));
  if (v === 'home') setTimeout(() => map.invalidateSize(), 50);
  if (v === 'history') renderHistory();
  if (v === 'notif') renderNotif();
  if (v === 'form') { initMini(); syncFormArea(); }
  scrollTo(0, 0);
}
document.addEventListener('click', e => { const b = e.target.closest('[data-go]'); if (b) go(b.dataset.go); });
const h0 = location.hash.slice(1); go(VIEWS.includes(h0) ? h0 : 'home'); // lapor.php -> #form, cek_status.php -> #status

// ---- Laporan milik perangkat ini (tiket + kode akses) ----
const mine = () => { try { return JSON.parse(localStorage.getItem('sigap_my') || '[]'); } catch { return []; } };
const saveMine = a => { try { localStorage.setItem('sigap_my', JSON.stringify(a.slice(0, 20))); } catch {} };
$('#bell-dot').hidden = !mine().length;
async function fetchStatus(t, c) {
  const fd = new FormData(); fd.append('ticket', t); fd.append('code', c);
  return (await fetch('api/check_status.php', {method: 'POST', body: fd})).json();
}
async function loadMine() {
  const rs = await Promise.allSettled(mine().map(m => fetchStatus(m.t, m.c)));
  return rs.map((x, i) => x.value && x.value.ok ? {...x.value, code: mine()[i].c} : null).filter(Boolean);
}

// ---- Banner BMKG (juga dipakai di notifikasi Sistem) ----
let BMKG = '';
fetch('api/fetch_bmkg.php').then(r => r.json()).then(d => {
  if (d.ok) { BMKG = d.text; $('#bmkg-bar').classList.toggle('warn', d.level === 'kuning'); $('#bmkg-bar').style.display = 'block'; $('#bmkg-bar span').textContent = d.text; }
}).catch(() => {});

// ---- Form ----
// Peta mini di form: pin otomatis dari GPS, ketuk peta untuk memindahkan pin
let mini, miniMark;
function setCoords(lat, lng) {
  $('#lat').value = (+lat).toFixed(8); $('#lng').value = (+lng).toFixed(8);
  $('#loc-t').textContent = `Lokasi terisi (${(+lat).toFixed(4)}, ${(+lng).toFixed(4)})`;
  if (mini) { mini.setView([lat, lng], 16); (miniMark ||= L.marker([lat, lng]).addTo(mini)).setLatLng([lat, lng]); }
}
function locate(auto) {
  if (!navigator.geolocation) return auto || Swal.fire('Tidak didukung', 'Browser tidak mendukung GPS. Ketuk peta untuk memilih lokasi.', 'warning');
  $('#loc-t').textContent = 'Mencari lokasi…';
  navigator.geolocation.getCurrentPosition(p => setCoords(p.coords.latitude, p.coords.longitude), () => {
    $('#loc-t').textContent = 'Gunakan Lokasi Saya';
    if (!auto) Swal.fire('Lokasi gagal', 'Izinkan akses lokasi, atau ketuk peta untuk memilih lokasi.', 'error');
  }, {enableHighAccuracy: true, timeout: 10000});
}
function initMini() {
  if (!mini) {
    mini = L.map('mini-map', {zoomControl: false}).setView(map.getCenter(), 14);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap'}).addTo(mini);
    mini.on('click', e => setCoords(e.latlng.lat, e.latlng.lng));
  }
  setTimeout(() => mini.invalidateSize(), 60);
  if (!$('#lat').value) locate(true); // otomatis minta izin lokasi saat form dibuka
}
$('#get-loc').onclick = () => locate(false);
$('#photo').onchange = e => { $('#photo-t').textContent = e.target.files[0]?.name || 'Ketuk untuk mengunggah foto'; };

$('#report-form').addEventListener('submit', async ev => {
  ev.preventDefault();
  if (!getArea()?.dn) { Swal.fire('Wilayah belum dipilih', 'Pilih provinsi, kota/kabupaten, dan kecamatan lewat menu lokasi di bagian atas.', 'warning').then(openArea); return; }
  if (!$('#lat').value) return Swal.fire('Lokasi belum diisi', 'Gunakan lokasi saya atau pilih lokasi di peta.', 'warning');
  if (!ev.target.querySelector('[name="impacts[]"]:checked')) return Swal.fire('Dampak belum dipilih', 'Pilih minimal satu dampak yang terlihat.', 'warning');
  const btn = $('#submit-btn'); btn.disabled = true;
  try {
    const resp = await fetch('api/submit_report.php', {method: 'POST', body: new FormData(ev.target)});
    const raw = await resp.text(); let res;
    try { res = JSON.parse(raw.slice(raw.indexOf('{'))); } // toleran jika ada teks warning di depan JSON
    catch { res = {ok: false, message: `Server membalas bukan JSON (HTTP ${resp.status}): ` + raw.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 300)}; }
    if (res.ok) {
      saveMine([{t: res.ticket, c: res.access_code}, ...mine()]); $('#bell-dot').hidden = false;
      ev.target.reset(); $('#loc-t').textContent = 'Gunakan Lokasi Saya'; $('#photo-t').textContent = 'Ketuk untuk mengunggah foto';
      $('#ok-t').textContent = res.ticket; $('#ok-c').textContent = res.access_code; $('#ok-panel').hidden = false;
      go('notif');
    } else Swal.fire('Belum bisa dikirim', res.message, 'error');
  } catch { Swal.fire('Koneksi bermasalah', 'Periksa internet lalu kirim ulang.', 'error'); }
  btn.disabled = false;
});
$('#ok-status').onclick = () => openStatus($('#ok-t').textContent, $('#ok-c').textContent);
$('#ok-detail').onclick = $('#ok-status').onclick;

// ---- Cek status: hasil + timeline + pembaruan penanganan ----
const TL = {darurat: 'Sinyal Darurat Diterima', menunggu: 'Laporan Diterima', diverifikasi: 'Diverifikasi', dieskalasi: 'Dieskalasi ke Instansi', diproses: 'Sedang Ditangani', selesai: 'Selesai', ditolak: 'Ditolak'};
function openStatus(t, c) { $('#st-t').value = t; $('#st-c').value = c; go('status'); $('#status-form').requestSubmit(); }
$('#status-form').addEventListener('submit', async ev => {
  ev.preventDefault();
  const out = $('#status-result'); out.innerHTML = '<p>Mencari…</p>';
  const res = await fetchStatus($('#st-t').value.trim(), $('#st-c').value.trim());
  if (!res.ok) { out.innerHTML = `<p style="color:var(--red);margin-top:12px">${esc(res.message)}</p>`; return; }
  const r = res.report, last = res.history.at(-1)?.status_name;
  const steps = res.history.flatMap(h => [{l: TL[h.status_name] || h.status_name, t: h.change_time},
    ...(h.status_name === 'diverifikasi' && PRIO[r.priority] ? [{l: 'Prioritas: ' + r.priority[0].toUpperCase() + r.priority.slice(1), t: h.change_time}] : [])]);
  const todo = (last === 'selesai' || last === 'ditolak') ? '' : '<li class="todo">Selesai<br><small>(belum selesai)</small></li>';
  const upd = [];
  if (r.admin_instruction) upd.push(`<article class="item upd" style="cursor:default"><div class="circ">${ic('user')}</div><div class="grow"><h4>Admin</h4><p>${esc(r.admin_instruction)}</p></div></article>`);
  res.assignments.forEach(a => upd.push(`<article class="item upd" style="cursor:default"><div class="circ">${ic('user')}</div><div class="grow"><h4>Tim Lapangan</h4><p>${a.completed_at ? 'Penanganan selesai.' : 'Penanganan sedang dilakukan di lokasi.'}</p>${a.photo_after_path ? `<img src="${esc(a.photo_after_path)}" alt="Foto hasil penanganan">` : ''}<small>${esc(fmt(a.completed_at))}</small></div></article>`));
  out.innerHTML = `<h3 class="hd">Hasil Pencarian</h3>${reportCard(r, 'style="cursor:default"')}
    <h3 class="hd">Timeline Status</h3><ul class="tl sec">${steps.map(s => `<li><b>${esc(s.l)}</b><br><small>${esc(fmt(s.t))}</small></li>`).join('')}${todo}</ul>
    ${upd.length ? `<h3 class="hd">Pembaruan Penanganan</h3>${upd.join('')}` : ''}`;
});

// ---- Riwayat ----
let hFilter = 'all';
$('#h-tabs').onclick = e => { if (!e.target.dataset.f) return; hFilter = e.target.dataset.f; document.querySelectorAll('#h-tabs button').forEach(b => b.classList.toggle('on', b === e.target)); renderHistory(); };
$('#h-cat').onchange = renderHistory;
$('#history-list').onclick = e => { const a = e.target.closest('[data-t]'); if (a) openStatus(a.dataset.t, a.dataset.c); };
async function renderHistory() {
  const box = $('#history-list'); box.innerHTML = '<p>Memuat…</p>';
  const cat = $('#h-cat').value;
  const list = (await loadMine()).filter(x => (!cat || x.report.category === cat) &&
    (hFilter === 'all' || (hFilter === 'selesai' ? x.report.current_status === 'selesai' : !['selesai', 'ditolak'].includes(x.report.current_status))));
  box.innerHTML = list.length ? list.map(x => reportCard(x.report, `data-t="${esc(x.report.ticket_number)}" data-c="${esc(x.code)}"`)).join('')
    : '<p>Belum ada laporan di perangkat ini.</p>';
}

// ---- Notifikasi: tab Semua / Laporan Saya / Sistem ----
let nFilter = 'all';
$('#n-tabs').onclick = e => { if (!e.target.dataset.f) return; nFilter = e.target.dataset.f; document.querySelectorAll('#n-tabs button').forEach(b => b.classList.toggle('on', b === e.target)); renderNotif(); };
const NMSG = {darurat: ['Sinyal darurat terkirim', 'alert'], menunggu: ['Laporan berhasil dikirim!', 'check'], diverifikasi: ['Status laporan diperbarui', 'pin'], dieskalasi: ['Laporan dieskalasi ke instansi', 'alert'], diproses: ['Laporan sedang ditangani', 'pin'], selesai: ['Laporan selesai', 'check'], ditolak: ['Laporan ditolak', 'alert']};
const short = s => { const d = new Date(s.replace(' ', 'T')); return d.toDateString() === new Date().toDateString() ? d.toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'}) : d.toLocaleDateString('id-ID', {day: 'numeric', month: 'short'}); };
async function renderNotif() {
  const box = $('#notif-list'); box.innerHTML = '<p>Memuat…</p>';
  const items = [];
  (await loadMine()).forEach(x => {
    x.history.forEach(h => items.push({k: 'mine', time: h.change_time, icon: (NMSG[h.status_name] || ['', 'pin'])[1], title: (NMSG[h.status_name] || ['Status diperbarui'])[0],
      text: h.status_name === 'menunggu' ? `Nomor laporan: ${x.report.ticket_number}\nKode akses: ${x.code}` : x.report.title, t: x.report.ticket_number, c: x.code}));
    if (x.report.current_status === 'menunggu') items.push({k: 'mine', time: x.report.created_at, icon: 'bell', title: 'Laporan Anda sedang diverifikasi', text: 'Admin sedang memeriksa laporan Anda.', t: x.report.ticket_number, c: x.code});
  });
  const now = new Date().toISOString().slice(0, 19).replace('T', ' ');
  if (BMKG) items.push({k: 'sys', time: now, icon: 'mega', title: 'Info BMKG', text: BMKG});
  PUB.filter(r => r.current_status === 'diverifikasi').forEach(r => items.push({k: 'sys', time: r.created_at, icon: 'mega', title: 'Peringatan bencana', text: `${r.title} — ${r.description}`}));
  const shown = items.filter(i => nFilter === 'all' || i.k === nFilter).sort((a, b) => b.time.localeCompare(a.time));
  box.innerHTML = shown.length ? shown.map(i => `<article class="item" ${i.t ? `data-t="${esc(i.t)}" data-c="${esc(i.c)}"` : 'style="cursor:default"'}><div class="circ">${ic(i.icon)}</div>
    <div class="grow"><h4>${esc(i.title)}</h4><p style="white-space:pre-line;display:block">${esc(i.text)}</p></div><small>${esc(short(i.time))}</small></article>`).join('') : '<p>Belum ada notifikasi.</p>';
}
$('#notif-list').onclick = e => { const a = e.target.closest('[data-t]'); if (a) openStatus(a.dataset.t, a.dataset.c); };

// ---- Mode gelap (disimpan di localStorage, dipakai juga oleh admin.php) ----
const root = document.documentElement;
function applyTheme(t) { root.dataset.theme = t; $('#theme use').setAttribute('href', t === 'dark' ? '#i-sun' : '#i-moon'); try { localStorage.setItem('sigap_theme', t); } catch {} }
applyTheme((() => { try { return localStorage.getItem('sigap_theme'); } catch {} })() || 'light');
$('#theme').onclick = () => applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');

// ---- Statistik kinerja (animasi angka) ----
function countUp(el, to) {
  const t0 = performance.now();
  const step = n => { const p = Math.min(1, (n - t0) / 1200); el.textContent = Math.round(to * p); if (p < 1) requestAnimationFrame(step); };
  requestAnimationFrame(step);
}
fetch('api/get_stats.php').then(r => r.json()).then(d => { if (d.ok) { countUp($('#st-done'), d.done); countUp($('#st-avg'), d.avg_minutes); } }).catch(() => {});

// ---- Upvote "Saya Terdampak" (satu kali per laporan per sesi + perangkat) ----
document.addEventListener('click', async e => {
  const b = e.target.closest('.up'); if (!b || b.disabled) return;
  const fd = new FormData(); fd.append('id', b.dataset.id);
  const r = await (await fetch('api/upvote.php', {method: 'POST', body: fd})).json();
  if (r.ok) {
    b.disabled = true; b.classList.add('on'); b.querySelector('span').textContent = `Terdampak (${r.count})`;
    try { localStorage.setItem('sigap_up', JSON.stringify([...votedIds(), +b.dataset.id])); } catch {}
  } else Swal.fire('Tidak bisa ditandai', r.message, 'info');
});

// ---- Portal edukasi (contoh konten; ganti dengan artikel BPBD/BMKG asli) ----
const NEWS = [
  ['Siaga banjir: langkah sebelum air naik', 'Amankan dokumen, matikan listrik, kenali jalur evakuasi RT.', 'alert'],
  ['Pohon tumbang: apa yang harus dilakukan', 'Jauhi kabel listrik, tandai area, laporkan lewat SIGAP.', 'pin'],
  ['Kenali jalur evakuasi lingkunganmu', 'Titik kumpul dan rute alternatif sebaiknya disepakati warga.', 'map'],
  ['Tas siaga bencana: isi dan cara menyiapkan', 'Air minum, senter, obat pribadi, dan salinan dokumen.', 'home'],
];
$('#news').innerHTML = NEWS.map(n => `<article class="ncard"><div class="nthumb">${ic(n[2])}</div><div class="nbody"><h4>${esc(n[0])}</h4><p>${esc(n[1])}</p></div></article>`).join('');

// ---- Pemilih wilayah: Provinsi -> Kota/Kabupaten -> Kecamatan ----
// Data wilayah disimpan LOKAL di assets/data/wilayah.json (Kemendagri via cahyadsn/wilayah: 38 provinsi, 514 kab/kota, 7.285 kecamatan),
// jadi tidak bergantung pada API luar. Format: {p:[[id,nama]], r:{idProvinsi:[[id,nama]]}, d:{idKabKota:[[id,nama]]}}
let WDATA;
async function wil(path) {
  if (!WDATA) { const r = await fetch('assets/data/wilayah.json'); if (!r.ok) throw new Error('gagal'); WDATA = await r.json(); }
  const [lvl, id] = path.split('/');
  const rows = lvl === 'provinces' ? WDATA.p : lvl === 'regencies' ? WDATA.r[id] : WDATA.d[id];
  return (rows || []).map(([id, name]) => ({id, name}));
}
const selP = $('#a-prov'), selR = $('#a-reg'), selD = $('#a-dis'), modalA = $('#area-modal');
function fill(sel, list, ph) {
  sel.innerHTML = `<option value="">${ph}</option>` + list.map(x => `<option value="${esc(x.id)}">${esc(x.name)}</option>`).join('');
  sel.disabled = !list.length;
}
async function loadLevel(sel, path, ph, val) {
  fill(sel, [], 'Memuat…');
  try { fill(sel, await wil(path), ph); if (val) sel.value = val; }
  catch { fill(sel, [], 'Gagal memuat data wilayah'); }
}
// Berjenjang: memilih tingkat atas hanya memuat pilihan di bawahnya
selP.onchange = async () => {
  fill(selR, [], 'Pilih provinsi dulu'); fill(selD, [], 'Pilih kota/kabupaten dulu');
  if (selP.value) await loadLevel(selR, 'regencies/' + selP.value, 'Pilih kota / kabupaten');
};
selR.onchange = async () => {
  fill(selD, [], 'Pilih kota/kabupaten dulu');
  if (selR.value) await loadLevel(selD, 'districts/' + selR.value, 'Pilih kecamatan');
};
async function openArea() {
  modalA.classList.add('open');
  const a = getArea() || {};
  if (selP.options.length < 2) await loadLevel(selP, 'provinces', 'Pilih provinsi', a.pid); else selP.value = a.pid || '';
  fill(selR, [], 'Pilih provinsi dulu'); fill(selD, [], 'Pilih kota/kabupaten dulu');
  if (a.pid) {
    await loadLevel(selR, 'regencies/' + a.pid, 'Pilih kota / kabupaten', a.rid);
    if (a.rid) await loadLevel(selD, 'districts/' + a.rid, 'Pilih kecamatan', a.did);
  }
}
const AREA_DEFAULT = $('#area-t').textContent;
function chip() { const a = getArea(); $('#area-t').textContent = !a ? AREA_DEFAULT : a.dn ? 'Kec. ' + a.dn : (a.rn || a.pn); }
function syncFormArea() { // wilayah terpilih ikut tersimpan bersama laporan
  const a = getArea() || {};
  $('#f-prov').value = a.pn || ''; $('#f-reg').value = a.rn || ''; $('#f-dis').value = a.dn || '';
  $('#f-area').textContent = a.dn ? `Wilayah laporan: Kec. ${a.dn}, ${a.rn}, ${a.pn}` : 'Pilih wilayah lewat menu lokasi di bagian atas.';
}
async function flyToArea(a) { // geser peta ke wilayah (geocoding OpenStreetMap Nominatim, best-effort)
  const q = [a.dn && 'Kecamatan ' + a.dn, a.rn, a.pn, 'Indonesia'].filter(Boolean).join(', ');
  try {
    const r = await (await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(q))).json();
    if (r[0]) map.setView([+r[0].lat, +r[0].lon], a.dn ? 14 : a.rn ? 11 : 8);
  } catch {}
}
$('#area-btn').onclick = openArea;
$('#a-cancel').onclick = () => modalA.classList.remove('open');
$('#a-ok').onclick = () => {
  if (!selP.value) return Swal.fire('Pilih provinsi dulu', '', 'info');
  const t = s => s.value ? s.selectedOptions[0].textContent : '';
  const a = {pid: selP.value, pn: t(selP), rid: selR.value, rn: t(selR), did: selD.value, dn: t(selD)};
  try { localStorage.setItem('sigap_area', JSON.stringify(a)); } catch {}
  modalA.classList.remove('open'); chip(); syncFormArea(); loadMarkers(); flyToArea(a);
};
$('#a-reset').onclick = () => {
  try { localStorage.removeItem('sigap_area'); } catch {}
  selP.value = ''; fill(selR, [], 'Pilih provinsi dulu'); fill(selD, [], 'Pilih kota/kabupaten dulu');
  modalA.classList.remove('open'); chip(); syncFormArea(); loadMarkers();
};
chip(); syncFormArea(); if (getArea()) flyToArea(getArea());
