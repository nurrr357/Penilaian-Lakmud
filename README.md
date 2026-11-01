# Penilaian LAKMUD — MOORA

Aplikasi admin penilaian LAKMUD berbasis Laravel untuk mengelola kegiatan, materi, peserta, enam komponen penilaian, perhitungan MOORA, dan hasil perangkingan.

## Fitur

- CRUD kegiatan, materi, dan peserta melalui tabel serta modal.
- Halaman Absensi, Pretest, Posttest, Keaktifan, Etika, dan Pemahaman.
- Pencarian dan pagination 10/25/50.
- Perhitungan MOORA dengan bobot C1–C6 yang dapat diatur global di halaman Kriteria & Bobot.
- Perangkingan Yi, finalisasi, penguncian, pembukaan kunci, dan audit log.

Panel operator berada di `/dashboard`; `/` hanya mengalihkan ke `/login` untuk pengunjung yang belum masuk.

## Instalasi

```bash
git clone https://github.com/nurrr357/Penilaian-Lakmud.git
cd Penilaian-Lakmud
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Atur koneksi MySQL dan `DEMO_PASSWORD` di `.env`, lalu:

```bash
php artisan migrate --seed
npm run build
php artisan serve
```

Akun operator hasil seeder: `operator@example.test`, dengan kata sandi dari `DEMO_PASSWORD` (tidak disimpan dalam repository). Akun ketua opsional dibuat hanya jika `KETUA_EMAIL` dan `KETUA_PASSWORD` diisi. Untuk produksi, seeder membaca `OPERATOR_EMAIL`, `OPERATOR_PASSWORD`, dan variabel `KETUA_*`; langkah lengkapnya ada di `docs/deployment-production.md`.

## Pengujian

```bash
php artisan test --compact
vendor/bin/pint --test --format agent
npm run build
```

## Aturan MOORA

Diperlukan tepat 15 materi aktif. Seluruh peserta harus memiliki absensi dan lima nilai pada semua materi.

| Kriteria | Jenis | Bobot |
|---|---|---:|
| C1 — jumlah ketidakhadiran | Cost | 0,10 |
| C2 — total pretest | Benefit | 0,10 |
| C3 — total keaktifan | Benefit | 0,20 |
| C4 — total etika | Benefit | 0,15 |
| C5 — total pemahaman | Benefit | 0,20 |
| C6 — total posttest | Benefit | 0,25 |

Perangkingan menggunakan Yi tertinggi ke terendah. Nilai seri menggunakan ranking kompetisi dan tidak dibulatkan sebelum dibandingkan.

