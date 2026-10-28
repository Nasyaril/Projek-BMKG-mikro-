// Peta Leaflet + marker + kartu laporan beranda. Juga berisi helper tampilan yang dipakai main.js.
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const ic = n => `<svg class="i" aria-hidden="true"><use href="#i-${n}"/></svg>`;
const LABEL = {menunggu:'Menunggu Verifikasi', diverifikasi:'Diterima', diproses:'Sedang Ditangani', selesai:'Selesai', ditolak:'Ditolak'};
const SCLS = {menunggu:'gray', diverifikasi:'blue', diproses:'orange', selesai:'green', ditolak:''};
const PRIO = {tinggi:['Prioritas Tinggi','#ef4444',''], sedang:['Prioritas Sedang','#eab308','yellow'], rendah:['Prioritas Rendah','#10b981','green']};
const badges = r => (r.is_emergency == 1 ? '<span class="badge sos">DARURAT</span>' : '')
  + `<span class="badge ${SCLS[r.current_status]}">${r.current_status === 'selesai' ? ic('check') : ''}${esc(r.is_emergency == 1 && r.current_status === 'diverifikasi' ? 'Belum dikonfirmasi' : LABEL[r.current_status])}</span>`
  + (PRIO[r.priority] ? `<span class="badge ${PRIO[r.priority][2]}">${PRIO[r.priority][0]}</span>` : '');
const fmt = s => s ? new Date(s.replace(' ', 'T')).toLocaleString('id-ID', {day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'}) : '-';
// Kartu laporan (dipakai di Beranda, Riwayat, dan Cek Status)
const reportCard = (r, attrs = '') => `<article class="item" ${attrs}><div class="thumb">${r.photo_before_path ? `<img src="${esc(r.photo_before_path)}" alt="" onerror="this.remove()">` : ic('image')}</div>
  <div class="grow"><h4>${esc(r.title)}</h4><p>${ic('pin')}${(+r.latitude).toFixed(4)}, ${(+r.longitude).toFixed(4)}</p><p>${ic('clock')}${esc(fmt(r.incident_time || r.created_at))}</p><div>${badges(r)}</div></div>${ic('right')}</article>`;

// Wilayah terpilih (disimpan main.js di localStorage) -> parameter filter untuk API
const getArea = () => { try { return JSON.parse(localStorage.getItem('sigap_area') || 'null'); } catch { return null; } };
const areaQuery = () => {
  const a = getArea(), p = new URLSearchParams();
  if (a?.pn) p.set('province', a.pn); if (a?.rn) p.set('regency', a.rn); if (a?.dn) p.set('district', a.dn);
  return p.toString() ? '?' + p : '';
};

const map = L.map('map').setView([-7.4478, 112.7183], 13); // pusat awal; ganti sesuai wilayah
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'}).addTo(map);
const layer = L.layerGroup().addTo(map);


window.PUB = [];
const votedIds = () => { try { return JSON.parse(localStorage.getItem('sigap_up') || '[]'); } catch { return []; } };
async function loadMarkers() {
  try {
    const res = await (await fetch('api/get_reports.php' + areaQuery())).json();
    window.PUB = res.data; layer.clearLayers(); const voted = votedIds();
    const list = document.getElementById('report-list');
    list.innerHTML = res.data.length ? '' : '<p>Belum ada laporan terverifikasi di wilayah ini.</p>';
    res.data.forEach(r => {
      const color = r.current_status === 'selesai' ? '#10b981' : (PRIO[r.priority] || ['', '#ef4444'])[1];
      (r.is_emergency == 1 && r.current_status !== 'selesai'
        ? L.marker([r.latitude, r.longitude], {icon: L.divIcon({className: 'sos-pin', html: '<i></i>', iconSize: [26, 26]})})
        : L.circleMarker([r.latitude, r.longitude], {radius: 11, color: '#fff', weight: 3, fillColor: color, fillOpacity: 1}))
        .bindPopup(`<div class="glass" style="padding:12px"><b>${esc(r.title)}</b><br><small>${esc(LABEL[r.current_status])} • ${esc(r.ticket_number)}</small><br><button type="button" class="detail-link" data-detail="${r.id}">Lihat detail</button></div>`, {className: 'glass'}).addTo(layer);
      const v = voted.includes(r.id);
      list.insertAdjacentHTML('beforeend', `<div class="feed">${reportCard(r, `data-detail="${r.id}" role="button" tabindex="0" aria-label="Lihat detail: ${esc(r.title)}"`)}<button class="up ${v ? 'on' : ''}" data-id="${r.id}" ${v ? 'disabled' : ''}>${ic('up')}<span>${v ? 'Terdampak' : 'Saya Terdampak'} (${r.upvote_count})</span></button></div>`);
    });
  } catch (e) { console.error('Gagal memuat laporan', e); }
}
loadMarkers();

// ---- Popup detail laporan (kartu Beranda + marker Peta). Hanya data publik: tanpa nama/kontak pelapor, tiket, atau kode akses ----
const KAT = {banjir: 'Banjir', pohon_tumbang: 'Pohon tumbang', lampu_mati: 'Lampu jalan mati', jalan_rusak: 'Jalan rusak', lainnya: 'Lainnya', darurat: 'Darurat'};
const detailModal = document.getElementById('detail-modal'), detailBox = document.getElementById('detail-box');
const closeDetail = () => detailModal.classList.remove('open');
function detailHTML(r) {
  const row = (k, v) => v ? `<div><dt>${k}</dt><dd>${esc(v)}</dd></div>` : '';
  const sec = (h, v) => v ? `<div class="dt-sec"><h4>${h}</h4><p>${esc(v)}</p></div>` : '';
  const place = [r.district, r.regency].filter(Boolean).join(', ');
  const impacts = (r.impacts || '').split(',').map(s => s.replace(/_/g, ' ').trim()).filter(Boolean).join(', ');
  const handled = r.handler ? (r.current_status === 'selesai' ? 'Ditangani oleh: ' : 'Sedang ditangani oleh: ') + r.handler : '';
  return `${r.photo_before_path ? `<img class="dt-photo" src="${esc(r.photo_before_path)}" alt="Foto laporan" onerror="this.remove()">` : ''}
    <h3 class="dt-title">${esc(r.title)}</h3><div class="dt-badges">${badges(r)}</div>
    <dl class="dt-list">${row('Kategori', KAT[r.category] || String(r.category).replace(/_/g, ' '))}${row('Waktu', fmt(r.incident_time || r.created_at))}${row('Lokasi', place)}${row('Koordinat', `${(+r.latitude).toFixed(4)}, ${(+r.longitude).toFixed(4)}`)}${row('Terdampak', r.upvote_count > 0 ? r.upvote_count + ' warga' : '')}</dl>
    ${sec('Deskripsi', r.description)}${sec('Dampak', impacts)}${sec('Instruksi admin', r.admin_instruction)}${sec('Penanganan', handled)}${r.photo_after ? `<div class="dt-sec"><h4>Dokumentasi penyelesaian</h4><img class="dt-photo" src="${esc(r.photo_after)}" alt="Foto dokumentasi penyelesaian" onerror="this.closest('.dt-sec').remove()"></div>` : ''}
    <button type="button" class="btn-primary wide dt-close" id="detail-close">Tutup</button>`;
}
function openDetail(id) {
  const r = window.PUB.find(x => String(x.id) === String(id)); if (!r) return;
  detailBox.innerHTML = detailHTML(r); detailBox.scrollTop = 0; detailModal.classList.add('open');
  document.getElementById('detail-close').onclick = closeDetail;
}
detailModal.onclick = e => { if (e.target === detailModal) closeDetail(); };   // ketuk area gelap untuk menutup
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDetail(); });
const feed = document.getElementById('report-list');
feed.addEventListener('click', e => { const c = e.target.closest('[data-detail]'); if (c) openDetail(c.dataset.detail); });
feed.addEventListener('keydown', e => { if ((e.key === 'Enter' || e.key === ' ') && e.target.matches('[data-detail]')) { e.preventDefault(); openDetail(e.target.dataset.detail); } });
// Leaflet menahan event klik di dalam popup marker, jadi tombolnya dipasang langsung saat popup terbuka
map.on('popupopen', e => { const b = e.popup.getElement()?.querySelector('[data-detail]'); if (b) b.onclick = () => { map.closePopup(); openDetail(b.dataset.detail); }; });