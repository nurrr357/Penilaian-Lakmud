import { Chart } from 'frappe-charts';

/* Frappe menyuntik CSS-nya sendiri (font sistem, sumbu abu-abu) ke dalam dokumen, jadi warna
   sumbu ditimpa di app.css dan warna seri dibaca dari token supaya tema tetap satu sumber.
   Token dibaca saat mount, bukan ditulis ulang sebagai hex di JS. */
const warna = nama => getComputedStyle(document.documentElement).getPropertyValue(nama).trim() || '#0d6560';

/**
 * Animasi masuk sengaja dimatikan (animate: 0). Di 1.6.2, draw(true) yang dipicu ResizeObserver
 * bertabrakan dengan pertukaran node SVG milik animasi SMIL, dan tabrakan itu melempar
 * "removeChild ... not a child of this node" di konsol sekaligus meninggalkan SVG selebar ukuran
 * lama (menyebabkan overflow di layar sempit). Tanpa animasi, jalur pertukaran itu tidak pernah
 * dijalankan, penggambaran ulang saat lebar berubah jadi benar dengan sendirinya, dan tidak ada
 * error di konsol. Ini bagian antarmuka yang tidak kehilangan informasi tanpa animasi.
 */
export function mountCharts() {
    const wadah = [...document.querySelectorAll('[data-chart]')];
    if (!wadah.length) return;

    wadah.forEach(el => {
        const konfigurasi = JSON.parse(el.dataset.chart);

        new Chart(el, {
            type: konfigurasi.type,
            data: konfigurasi.data,
            height: konfigurasi.height ?? 240,
            colors: (konfigurasi.colors ?? ['--lakmud-primary']).map(warna),
            strokeWidth: konfigurasi.strokeWidth,
            animate: 0,
            tooltipOptions: {
                // Donut hanya memakai kunci ini untuk teks legenda ("Lulus: 689"), karena tooltip
                // donutnya menampilkan persen. Empat desimal akan menulis "689,0000" pada angka
                // jumlah peserta yang selalu bulat, jadi donut memakai angka apa adanya.
                formatTooltipY: konfigurasi.type === 'donut'
                    ? nilai => Number(nilai)
                    : nilai => Number(nilai).toFixed(4).replace('.', ','),
            },
        });
    });
}
