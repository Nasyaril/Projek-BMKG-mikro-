// Peta Leaflet + marker + kartu laporan beranda. Juga berisi helper tampilan yang dipakai main.js.
const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const ic = n => `<svg class="i" aria-hidden="true"><use href="#i-${n}"/></svg>`;
const LABEL = {menunggu:'Menunggu Verifikasi', diverifikasi:'Diterima', diproses:'Sedang Ditangani', selesai:'Selesai', ditolak:'Ditolak'};
const SCLS = {menunggu:'gray', diverifikasi:'blue', diproses:'orange', selesai:'green', ditolak:''};
const PRIO = {tinggi:['Prioritas Tinggi','#ef4444',''], sedang:['Prioritas Sedang','#eab308','yellow'], rendah:['Prioritas Rendah','#10b981','green']};
const badges = r => `<span class="badge ${SCLS[r.current_status]}">${r.current_status === 'selesai' ? ic('check') : ''}${esc(LABEL[r.current_status])}</span>`
  + (PRIO[r.priority] ? `<span class="badge ${PRIO[r.priority][2]}">${PRIO[r.priority][0]}</span>` : '');
const fmt = s => s ? new Date(s.replace(' ', 'T')).toLocaleString('id-ID', {day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'}) : '-';
// Kartu laporan (dipakai di Beranda, Riwayat, dan Cek Status)
const reportCard = (r, attrs = '') => `<article class="item" ${attrs}><div class="thumb">${r.photo_before_path ? `<img src="${esc(r.photo_before_path)}" alt="">` : ic('image')}</div>
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
      L.circleMarker([r.latitude, r.longitude], {radius: 11, color: '#fff', weight: 3, fillColor: color, fillOpacity: 1})
        .bindPopup(`<div class="glass" style="padding:12px"><b>${esc(r.title)}</b><br><small>${esc(LABEL[r.current_status])} • ${esc(r.ticket_number)}</small></div>`, {className: 'glass'}).addTo(layer);
      const v = voted.includes(r.id);
      list.insertAdjacentHTML('beforeend', `<div class="feed">${reportCard(r)}<button class="up ${v ? 'on' : ''}" data-id="${r.id}" ${v ? 'disabled' : ''}>${ic('up')}<span>${v ? 'Terdampak' : 'Saya Terdampak'} (${r.upvote_count})</span></button></div>`);
    });
  } catch (e) { console.error('Gagal memuat laporan', e); }
}
loadMarkers();
