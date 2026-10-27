// Menu Nomor Darurat. Dimuat setelah main.js (memakai $) dan map.js (ic, esc). Swal dari CDN.
// PENTING: tombol panggil di sini HANYA SIMULASI (pop-up). Tidak ada link tel: dan tidak ada panggilan telepon sungguhan.
const sosModal = $('#sos-modal');
const closeSos = () => sosModal.classList.remove('open');
$('#sos-btn').onclick = $('#sos-open').onclick = () => sosModal.classList.add('open');
$('#sos-close').onclick = closeSos;
sosModal.onclick = e => { if (e.target === sosModal) closeSos(); }; // ketuk area gelap untuk menutup

sosModal.addEventListener('click', e => {
  const b = e.target.closest('.callbtn'); if (!b) return;
  Swal.fire({
    title: `Memanggil ${esc(b.dataset.num)}…`,
    html: `<div class="ringing">${ic('phone')}</div><p style="margin:0">${esc(b.dataset.name)}</p>
           <p style="margin:10px 0 0;font-size:.8rem;color:#6b7280">Mode simulasi: tidak ada panggilan yang dilakukan.</p>`,
    confirmButtonText: 'Akhiri', confirmButtonColor: '#0D47A1', allowOutsideClick: false,
  });
});
