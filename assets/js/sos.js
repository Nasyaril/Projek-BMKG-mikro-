// Menu Nomor Darurat (sementara: daftar nomor + keterangan, tanpa input). Dimuat setelah main.js (memakai $).
// Pengiriman sinyal darurat (api/emergency_call.php) sengaja belum dihubungkan ke tampilan ini.
const sosModal = $('#sos-modal');
const closeSos = () => sosModal.classList.remove('open');
$('#sos-btn').onclick = $('#sos-open').onclick = () => sosModal.classList.add('open');
$('#sos-close').onclick = closeSos;
sosModal.onclick = e => { if (e.target === sosModal) closeSos(); }; // ketuk area gelap untuk menutup
