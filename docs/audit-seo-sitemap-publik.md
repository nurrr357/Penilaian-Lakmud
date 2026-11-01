# Audit SEO, Sitemap, dan Halaman Publik — Penilaian LAKMUD

Tanggal audit: 21 Oktober 2026
Proyek: `D:\huda\project\laravel\skripsi\lakmud-penilaian`
Repository: `https://github.com/nurrr357/Penilaian-Lakmud`
Branch saat audit: `main` (commit awal audit `7486819`, working tree bersih)
Branch deployment: `production`
Domain kanonik: `https://penilaianlakmud.mochhuda.id`

Ringkas: seluruh temuan pada bagian A diperbaiki di source code. Perubahan **belum di-commit dan belum di-push**; lihat bagian G dan H.

---

## A. Ringkasan audit

### A.1 Kondisi sebelum perbaikan

| Temuan | Bukti | Akar masalah | Risiko |
|---|---|---|---|
| `/sitemap.xml` membalas 404 | `PublicSitemap::pages()` mengembalikan `[]`, `response()` memanggil `abort(404)` | Bukan masalah routing, cache, atau server: route terdaftar benar (`php artisan route:list` menampilkan `GET|HEAD sitemap.xml` dengan middleware `web` saja) dan penjagaan 404 memang disengaja karena belum ada satu pun halaman publik | Sedang — tidak ada jalur penemuan halaman publik di mesin pencari |
| Tidak ada halaman publik sama sekali | Setiap `GET` kecuali `/login` dan `/sitemap.xml` memakai middleware `auth` + `permission:*`; `/` adalah dashboard | Aplikasi dibangun sebagai panel operator murni; belum ada lapisan halaman informasi publik | Sedang — aplikasi tidak punya kehadiran pencarian, dan `/` yang semula dashboard tidak dapat diindeks dengan benar |
| Judul halaman duplikat dan generik | `resources/views/layout.blade.php`: `<title>{{ $title ?? 'Penilaian' }} | LAKMUD</title>`; hanya `login.blade.php` mengirim `$title`, seluruh halaman panel memakai nilai default yang sama | Metadata tidak terpusat dan tidak ada sumber judul per halaman | Rendah–sedang — duplikasi judul, tanpa meta description |
| Tidak ada meta description, canonical, meta robots, dan Open Graph di halaman mana pun | Tidak ada satu pun tag tersebut di `layout.blade.php` | Belum pernah ditambahkan | Rendah–sedang |
| `public/robots.txt` minimal | Isinya hanya `User-agent: *` dan `Disallow:` kosong, tanpa `Sitemap:` | Belum disesuaikan dengan daftar route nyata | Rendah |
| Halaman login dapat diindeks | Tidak ada meta robots di `layout.blade.php` yang juga dipakai `login.blade.php` | Belum ada aturan indexability | Rendah |
| Data penilaian tidak bocor ke publik | Tidak ada satu pun route tanpa `auth` selain `login`, `sitemap.xml`, dan `up` | Kontrol akses sudah benar | Tidak ada temuan |
| `public/favicon.ico` berukuran 0 byte | `ls -la public/` | Berkas favicon kosong sejak awal | Rendah — **tidak diubah** (tidak ada aset merek yang sah untuk dibuat) |
| Laravel Boost menyuntikkan browser logger di HTML | `<script id="browser-logger-active">` muncul saat `APP_ENV=local` | `Laravel\Boost\Middleware\InjectBoost` aktif hanya bila environment `local` atau `APP_DEBUG=true` (`vendor/laravel/boost/src/BoostServiceProvider.php`, `shouldRun()`) | Tidak ada di produksi (`APP_ENV=production`, `APP_DEBUG=false`) — dicatat, tidak diubah |

### A.2 Baseline sebelum perubahan

```
git status         → bersih; branch main; commit terakhir 7486819
php artisan test   → 56 test lulus, 808 assertion (15,6 detik)
vendor/bin/pint --dirty → lulus
npm run build      → lulus
```

### A.3 Akar masalah sitemap (ditegaskan)

404 pada `/sitemap.xml` **bukan** karena route hilang, cache route, konfigurasi Nginx, atau exception aplikasi:

1. `php artisan route:list --except-vendor` menampilkan `GET|HEAD sitemap.xml → sitemap › routes/web.php`.
2. `bootstrap/cache/` tidak memuat `config.php`/`routes-v7.php`, jadi tidak ada konfigurasi hasil cache yang menyimpangkan perilaku.
3. `PublicSitemap::response()` memanggil `abort(404)` ketika `pages() === []`, dan `pages()` memang mengembalikan `[]`.

Karena aplikasi belum punya halaman publik, 404 itu adalah perilaku yang benar pada saat itu. Perbaikannya bukan menghapus penjagaan, melainkan **membuat halaman publik yang nyata** lalu mengisi katalognya.

---

## B. Daftar perubahan

### Berkas baru

| Berkas | Isi | Alasan |
|---|---|---|
| `app/Services/PublicPages.php` | Katalog halaman publik: `path`, `label`, `title`, `description`, `url`; konstanta `BASE_URL`; `all()`, `get()`, `url()`, `structuredData()` | Satu sumber kebenaran untuk route publik, canonical, metadata, navigasi, sitemap, dan test — tidak ada daftar kedua yang bisa menyimpang |
| `app/Http/Controllers/PublicPageController.php` | `page(Request): View`; nama halaman diambil dari default route, dipakai sebagai nama view, `abort_if` 404 bila tidak terdaftar | Hanya halaman di katalog yang bisa membuka view; parameter request tidak pernah menjadi nama view |
| `resources/views/public-layout.blade.php` | Kerangka halaman publik dengan `<title>`, meta description, canonical, `robots index, follow`, Open Graph, Twitter card, JSON-LD, header/nav/footer | Metadata terpusat dari katalog |
| `resources/views/home.blade.php` | Beranda publik | Halaman utama yang bisa diindeks |
| `resources/views/about.blade.php` | `/tentang-aplikasi` | Informasi aplikasi, pengguna, dan batas publik/internal |
| `resources/views/features.blade.php` | `/fitur` | Daftar kemampuan per menu |
| `resources/views/method.blade.php` | `/metode-penilaian` | Alur, kriteria C1–C6, bobot, rumus (KaTeX), nilai sertifikat, predikat |
| `resources/views/privacy.blade.php` | `/kebijakan-privasi` | Data yang disimpan, akses, cookie, retensi, permintaan data |
| `resources/css/public.css` | Kelas `.pub-*` untuk halaman publik, memakai token `tokens.css` | Gaya dan komponen aplikasi dipertahankan, tanpa library baru |
| `tests/Feature/PublicPagesTest.php` | 12 test kontrak halaman publik | Menutup akses publik, metadata, kebocoran data, dan batas izin |

### Berkas yang diubah

| Berkas | Perubahan | Alasan |
|---|---|---|
| `routes/web.php` | `/sitemap.xml` tetap; route publik didaftarkan dari `PublicPages::all()`; `/dashboard` menjadi route bernama `dashboard` (auth + `permission:events.view`); `redirect()->intended('/dashboard')` | `/` bukan lagi dashboard; dashboard butuh URL tetap dan nama route agar redirect `guest` dan `intended` benar |
| `app/Services/PublicSitemap.php` | `BASE_URL` menjadi alias `PublicPages::BASE_URL`; `pages()` disusun dari katalog publik; `lastmod` selalu `null` sehingga tidak ditulis; penjagaan 404 saat katalog kosong dipertahankan | Sitemap tidak mungkin memuat URL mati atau URL internal; `lastmod` tidak diisi waktu request |
| `public/robots.txt` | Blok komentar penjelas, `Allow: /`, `Disallow: /up`, `Sitemap: https://penilaianlakmud.mochhuda.id/sitemap.xml` | Mendeklarasikan sitemap; halaman internal sengaja tidak dicantumkan karena sudah memakai meta `noindex` (atribut `Disallow` untuk URL yang sama akan membuat crawler tidak pernah membaca `noindex`-nya) |
| `resources/views/layout.blade.php` | Tautan menu Dashboard diarahkan ke `/dashboard` (termasuk penanda aktif); ditambahkan `<meta name="robots" content="noindex, nofollow">` | Menyesuaikan pemindahan dashboard; halaman panel dan login memang bukan halaman publik |
| `resources/css/app.css` | `@import './public.css';` dan satu baris dokumentasi struktur | Halaman publik memakai bundel yang sama |
| `tests/Feature/SitemapTest.php` | Kontrak baru: endpoint 200 XML, namespace, URL absolut, unik, bebas URL internal, canonical = `loc`, tidak `noindex`, robots.txt tidak memblokir halaman publik, panel tetap di belakang autentikasi | Menutup seluruh persyaratan sitemap |
| `tests/Feature/PanelTest.php`, `GranularPermissionTest.php`, `DashboardChartTest.php`, `ManagementPagesTest.php`, `ExampleTest.php` | `/` → `/dashboard` pada test yang memang menguji dashboard | Route dashboard berpindah; ekspektasi perilaku tidak diubah |
| `README.md` | Menambahkan halaman publik, `sitemap.xml`, `robots.txt`, dan letak `/dashboard` | Dokumentasi repo ikut benar |
| `docs/deployment-production.md` | Smoke test §9 diperbarui: URL publik, `/dashboard`, `sitemap.xml`, `robots.txt`, redirect login | Langkah verifikasi lama menyebut "URL tanpa login mengarah ke `/login`", yang tidak lagi berlaku untuk `/` |

### Yang **tidak** diubah

Rumus dan bobot MOORA (`app/Services/MooraCalculator.php`), nilai sertifikat dan predikat (`CertificateScoreCalculator.php`), `PanelService`, `PanelController`, seluruh model, migrasi, seeder, middleware `permission`, sistem autentikasi, audit log, dan 21 izin panel. `git diff --stat` tidak menyentuh satu pun berkas itu.

---

## C. Arsitektur route

### Halaman publik (tanpa login, boleh diindeks)

| Path | Route name | Isi |
|---|---|---|
| `/` | `home` | Beranda: ringkasan cara kerja dan alur penilaian |
| `/tentang-aplikasi` | `about` | Tentang aplikasi, pengguna, batas publik/internal |
| `/fitur` | `features` | Fitur per menu |
| `/metode-penilaian` | `method` | Alur, kriteria, bobot, rumus, predikat |
| `/kebijakan-privasi` | `privacy` | Data, akses, cookie, retensi |
| `/sitemap.xml` | `sitemap` | Daftar URL publik |
| `/robots.txt` | — | Berkas statis di `public/` |

### Tetap internal (auth + permission, tanpa perubahan aturan)

`/login` (guest, `throttle:6,1`), `/logout` (auth), `/dashboard` (auth + `events.view`), `/kegiatan`, `/materi`, `/peserta`, `/absensi`, `/pretest`, `/posttest`, `/keaktifan`, `/etika`, `/pemahaman`, `/perhitungan`, `/hasil`, `/panduan`, seluruh endpoint mutasi (`POST /kegiatan`, `PUT|DELETE /kegiatan/{event}`, `POST /kegiatan/{event}/{type}`, `PUT|DELETE /kegiatan/{event}/{type}/{record}`, `POST /input/{event}/{kind}`, `POST /hasil/{event}/{action}`), dan `/up`.

Perubahan route yang berdampak ke referensi lain sudah disesuaikan: redirect setelah login (`intended('/dashboard')`), default `RedirectIfAuthenticated` (memilih route bernama `dashboard` lebih dulu), menu sidebar, penanda menu aktif, dan lima berkas test.

---

## D. SEO, sitemap, dan robots

### Metadata per halaman (dari `PublicPages`)

| Path | Title | Meta description |
|---|---|---|
| `/` | Penilaian LAKMUD — Panel Penilaian Kegiatan Metode MOORA | Panel penilaian kegiatan LAKMUD: 15 materi, enam aspek penilaian, perhitungan MOORA, peringkat peserta, nilai sertifikat, dan predikat kelulusan. |
| `/tentang-aplikasi` | Tentang Aplikasi Penilaian LAKMUD | Penjelasan aplikasi Penilaian LAKMUD: kegiatan yang dinilai, enam aspek penilaian, alur kerja operator, dan batas antara halaman publik dan data penilaian. |
| `/fitur` | Fitur Aplikasi Penilaian LAKMUD | Fitur Penilaian LAKMUD: data kegiatan, materi, peserta, absensi, pretest, posttest, keaktifan, etika, pemahaman, perhitungan MOORA, dan hasil perangkingan. |
| `/metode-penilaian` | Metode Penilaian MOORA dan Cara Perangkingan Peserta | Alur penilaian dan perhitungan MOORA di LAKMUD: kriteria C1–C6 beserta bobotnya, normalisasi, nilai Yi, nilai sertifikat, dan predikat kelulusan peserta. |
| `/kebijakan-privasi` | Kebijakan Privasi Penilaian LAKMUD | Kebijakan privasi Penilaian LAKMUD: data yang disimpan aplikasi, siapa yang dapat mengaksesnya, dan pemisahan halaman publik dari data penilaian. |

Semua judul dan deskripsi unik, panjang deskripsi 70–160 karakter (dikunci test). Setiap halaman publik: satu `<h1>`, `<html lang="id">`, canonical absolut domain kanonik, `robots index, follow`, Open Graph (`og:type`, `og:site_name`, `og:locale`, `og:title`, `og:description`, `og:url`), dan Twitter card `summary`. Beranda memuat JSON-LD `WebSite` (name, url kanonik, inLanguage) — disusun di PHP, bukan ditulis di Blade, karena teks `@context` di dalam template dibaca Blade sebagai direktif `@context` sehingga JSON-LD ikut terkompilasi dan rusak. Kesalahan itu sempat terjadi dan tertangkap saat verifikasi HTTP; test-nya sekarang **mem-parsing** blok JSON-LD (`json_decode`) alih-alih mencocokkan potongan teks, karena JSON yang rusak tetap memuat potongan `"@type":"WebSite"`.

Data terstruktur lain sengaja tidak dipasang: tidak ada `aggregateRating`, `review`, atau data organisasi yang tidak dapat dibuktikan.

### Isi sitemap (`https://penilaianlakmud.mochhuda.id/sitemap.xml`)

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://penilaianlakmud.mochhuda.id/</loc></url>
  <url><loc>https://penilaianlakmud.mochhuda.id/tentang-aplikasi</loc></url>
  <url><loc>https://penilaianlakmud.mochhuda.id/fitur</loc></url>
  <url><loc>https://penilaianlakmud.mochhuda.id/metode-penilaian</loc></url>
  <url><loc>https://penilaianlakmud.mochhuda.id/kebijakan-privasi</loc></url>
</urlset>
```

Sitemap index tidak diperlukan untuk lima URL. `lastmod` tidak ditulis karena aplikasi tidak mencatat tanggal revisi konten halaman publik, sedangkan waktu request bukan tanggal modifikasi.

### Perilaku endpoint (terukur pada server lokal)

| URL | Status | Content-Type |
|---|---|---|
| `/sitemap.xml` | 200 | `application/xml; charset=UTF-8` |
| `/robots.txt` | 200 | `text/plain; charset=UTF-8` |
| `/`, `/tentang-aplikasi`, `/fitur`, `/metode-penilaian`, `/kebijakan-privasi` | 200 | `text/html; charset=utf-8` |
| `/dashboard`, `/kegiatan`, `/materi`, `/peserta`, `/absensi`, `/pretest`, `/posttest`, `/keaktifan`, `/etika`, `/pemahaman`, `/perhitungan`, `/hasil`, `/panduan` | 302 → `/login` | — |
| `/login` | 200 (meta `noindex, nofollow`) | `text/html; charset=utf-8` |
| `/halaman-yang-tidak-ada`, `/sitemap.xml.bak` | 404 | — |

---

## E. Security dan business regression

- **Guest diblokir dari panel**: 13 URL panel membalas 302 → `/login` (diuji lewat HTTP nyata dan test feature).
- **Tanpa izin tetap 403**: pengguna tanpa `events.view` mendapat 403 di `/dashboard`; `POST /hasil/1/calculate` juga 403. `GranularPermissionTest` tetap lulus tanpa perubahan ekspektasi.
- **Login/logout berjalan**: `POST /login` berhasil → 302 `/dashboard`; `POST /logout` → 302 `/login`; login gagal tetap mengembalikan error validasi dan tidak membuka panel; pengguna yang sudah masuk dan membuka `/login` diarahkan ke `/dashboard`.
- **Data tidak bocor ke halaman publik**: halaman publik tidak memuat nama peserta, nama kegiatan, `/logout`, field kata sandi, `data-modal-open`, potongan halaman internal, kode Blade terkompilasi, maupun pesan error PHP.
- **MOORA dan data tidak tersentuh**: tidak ada perubahan pada kalkulator, `PanelService`, model, migrasi, seeder, atau database. Seluruh test bisnis (`PanelTest` alur CRUD + snapshot + finalisasi + buka kunci + audit log, `AspekMooraPagesTest`, `DashboardChartTest`, `ReconciliationTest`, `ProductionSeederTest`) lulus tanpa penyesuaian expected result.
- **`robots.txt` tidak dipakai sebagai pengaman**: pembatas akses tetap middleware `auth` + izin; `robots.txt` hanya panduan crawler.
- **Operasi database lokal saat verifikasi**: hanya pembacaan, ditambah satu akun verifikasi sementara (`verifikasi.seo@example.test`) yang dibuat untuk menguji jalur login HTTP lalu **dihapus** (tersisa 2 akun seperti sebelumnya). Tidak ada `migrate:fresh`, `db:wipe`, atau seeder destruktif yang dijalankan.

---

## F. Hasil test dan build (aktual)

| Perintah | Hasil |
|---|---|
| `php artisan test --compact` (baseline) | 56 lulus, 808 assertion, 15,6 s |
| `php artisan test --compact` (sesudah perubahan) | **73 lulus, 73 test, 1025 assertion, 17,7 s** |
| `vendor/bin/pint --dirty --format agent` | `{"tool":"pint","result":"passed"}` |
| `npm run build` | sukses (`✓ built in 633ms`; `app-CMCM-rnZ.css` 35,46 kB, `app-BtasELpM.css` 25,34 kB, `app-PgxnbHAU.js` 357,35 kB) |
| Verifikasi HTTP end-to-end halaman publik/robots/sitemap/internal (`python verify_public_pages.py`) | **118 pemeriksaan lulus, 0 gagal** |
| Verifikasi HTTP jalur login (`python verify_login_flow.py`) | **11 pemeriksaan lulus, 0 gagal** |

Catatan kejujuran hasil:

- Test **tidak** menjalankan verifikasi di server produksi; seluruh angka di atas dari mesin lokal (`http://127.0.0.1:8099`).
- Cabang penjagaan "katalog kosong → 404" pada `PublicSitemap::response()` tidak dapat dieksekusi test selama katalog berisi halaman; yang dikunci test adalah bentuk dokumen yang membuat penjagaan itu diperlukan (urlset tanpa elemen `url`).
- Pengindeksan Google **tidak** diverifikasi dan tidak diklaim berhasil.

---

## G. Git diff

```
 M app/Services/PublicSitemap.php            |  38 +++---
 M public/robots.txt                        |  21 ++++-
 M resources/css/app.css                    |   2 +
 M resources/views/layout.blade.php         |   4 +-
 M routes/web.php                           |  22 +++-
 M tests/Feature/DashboardChartTest.php     |  10 +--
 M tests/Feature/ExampleTest.php            |   6 +-
 M tests/Feature/GranularPermissionTest.php |   2 +-
 M tests/Feature/ManagementPagesTest.php    |   2 +-
 M tests/Feature/PanelTest.php              |   6 +-
 M tests/Feature/SitemapTest.php            | 110 ++++++++-----
 M README.md, docs/deployment-production.md | dokumentasi
?? app/Http/Controllers/PublicPageController.php
?? app/Services/PublicPages.php
?? resources/css/public.css
?? resources/views/{home,about,features,method,privacy,public-layout}.blade.php
?? tests/Feature/PublicPagesTest.php
```

Belum ada `git add`, `git commit`, atau `git push`. Branch aktif `main`, sedangkan branch deployment adalah `production`; keputusan commit dan pemilihan branch diserahkan ke pemilik repository (sesuai kebiasaan: perubahan diuji lebih dulu oleh pemilik sebelum commit).

---

## H. Instruksi deployment ke VPS `/var/www/penilaianlakmud.mochhuda.id`

Tidak ada migrasi, perubahan konfigurasi `.env`, atau pekerjaan queue baru pada pembaruan ini. Perubahan berisi PHP, Blade, CSS, `robots.txt`, dan test.

```bash
cd /var/www/penilaianlakmud.mochhuda.id

# 0. Cadangan lebih dulu (kebiasaan, bukan keharusan perubahan ini)
#    mysqldump -u <user> -p <db> > /var/backups/lakmud-$(date +%F-%H%M).sql
git status                     # pastikan tidak ada perubahan lokal yang tidak dikenal
git fetch origin
git checkout production
git pull --ff-only

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci && npm run build        # atau unggah public/build hasil build lokal

sudo chown -R www-data:www-data storage bootstrap/cache   # sesuaikan user PHP-FPM

php artisan optimize:clear     # wajib: hapus cache view/route/config lama
php artisan optimize           # config, route, dan view cache untuk produksi
php artisan view:clear         # hanya bila ingin mengosongkan cache view lagi
php artisan config:clear       # hanya bila perlu mengubah .env setelah optimize
php artisan up                 # (hanya jika sebelumnya menjalankan down)
```

Yang perlu dibersihkan setelah `git pull`:

| Cache | Perintah | Alasan |
|---|---|---|
| View terkompilasi | `php artisan view:clear` | `layout.blade.php` dan enam view baru belum tercermin di cache view |
| Route | `php artisan route:clear` (atau `route:cache` via `optimize`) | `/dashboard` adalah route baru dan `/` diganti controller |
| Config | `php artisan config:clear` lalu `php artisan config:cache` | Aman dilakukan meski `.env` tidak berubah |
| Aset | `npm run build` lalu (opsional) `php artisan vendor:publish --tag=laravel-assets --force` | `public/build` ikut berubah karena `public.css` |

Pemeriksaan HTTP setelah deploy (tanpa login):

```bash
curl -sS -o /dev/null -w '%{http_code} %{content_type}\n' https://penilaianlakmud.mochhuda.id/
curl -sS -o /dev/null -w '%{http_code} %{content_type}\n' https://penilaianlakmud.mochhuda.id/sitemap.xml
curl -sS -o /dev/null -w '%{http_code} %{content_type}\n' https://penilaianlakmud.mochhuda.id/robots.txt
curl -sS -o /dev/null -w '%{http_code} -> %{redirect_url}\n' https://penilaianlakmud.mochhuda.id/dashboard
curl -sS https://penilaianlakmud.mochhuda.id/sitemap.xml | head -5
curl -sS https://penilaianlakmud.mochhuda.id/robots.txt
```

Yang diharapkan: `/` dan tiga halaman publik lain 200 `text/html`; `sitemap.xml` 200 `application/xml`; `robots.txt` 200 dan memuat baris `Sitemap:`; `/dashboard` 302 ke `/login`; login operator diarahkan ke `/dashboard`.

Rollback aman (tanpa perintah destruktif):

```bash
cd /var/www/penilaianlakmud.mochhuda.id
git log --oneline -3                    # catat commit rilis sebelumnya
git checkout <commit-sebelumnya>        # atau git revert <commit-rilis-ini>
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan optimize:clear && php artisan optimize
```

Tidak ada migrasi baru, sehingga rollback source tidak memerlukan rollback database. `migrate:fresh`, `db:wipe`, `migrate:rollback` otomatis, dan `git reset --hard` tidak dijalankan dalam prosedur ini.

---

## I. Google Search Console

Belum ada verifikasi pengindeksan yang dilakukan dari sisi ini — langkah berikut adalah tindakan di akun Google.

1. **Properti**: gunakan properti domain (`penilaianlakmud.mochhuda.id`) bila DNS dapat ditambahkan TXT; bila tidak, properti URL prefix dengan `https://penilaianlakmud.mochhuda.id`. Berkas verifikasi `public/googleff7feda9b489cb49.html` sudah ada di repository dan tetap dilayani sebagai berkas statis.
2. **Kirim sitemap**: `https://penilaianlakmud.mochhuda.id/sitemap.xml` (satu sitemap, tanpa index).
3. **Periksa status sitemap** di menu Sitemaps: status harus beralih dari "Couldn't fetch" ke "Success" dengan 5 URL ditemukan.
4. **URL Inspection** untuk lima URL publik, lalu jalankan **Live URL Test** satu per satu: `/`, `/tentang-aplikasi`, `/fitur`, `/metode-penilaian`, `/kebijakan-privasi`. Periksa bahwa "Page is available to be crawled", canonical yang dibaca Google sama dengan URL yang diuji, dan tidak ada "Blocked by robots.txt".
5. **Request indexing** untuk halaman publik yang layak; tidak perlu untuk `/login` atau URL panel.
6. **Arti status yang mungkin muncul**:
   - *URL is unknown to Google* — URL baru dikenal setelah sitemap diproses atau inspection dijalankan; bukan tanda kerusakan teknis.
   - *Discovered - currently not indexed* — URL diketahui tetapi belum dirayapi/dinilai; biasanya perkara prioritas dan kualitas, bukan akses.
   - *Crawled - currently not indexed* — halaman sudah dirayapi tetapi dinilai belum layak diindeks; periksa kedalaman konten, bukan hanya metadata.
   - *Duplicate, Google chose different canonical* — ada versi URL lain yang dianggap setara; canonical di aplikasi sudah menunjuk domain kanonik tanpa parameter, jadi curigai URL dengan parameter atau versi host/protokol lain.
   - *Page with redirect* — muncul untuk URL yang memang mengalihkan (misalnya `/dashboard` sebelum login); itu perilaku yang diharapkan, bukan kesalahan.
   - *Excluded by 'noindex' tag* — muncul untuk `/login` dan halaman panel; sesuai rencana.
7. **Bedakan masalah aplikasi dari keputusan Google**: akses 200 tanpa login, canonical, sitemap, dan `robots.txt` sudah dapat diperiksa sendiri lewat `curl`. Keputusan mengindeks sebuah halaman tetap milik Google dan tidak dijamin oleh perbaikan teknis ini.

Integrasi API Search Console tidak ditambahkan, dan tidak ada credential atau token OAuth yang disimpan di source code.

---

## J. Risiko dan hal yang belum dapat diverifikasi

1. **Produksi belum diuji.** Tidak ada akses VPS pada sesi ini. Seluruh angka status HTTP berasal dari server lokal. Setelah deploy, jalankan pemeriksaan pada bagian H sebelum menyimpulkan hasil.
2. **Nginx/Cloudflare/DNS belum diperiksa.** Canonical dan `loc` sitemap memakai `https://penilaianlakmud.mochhuda.id` (konstanta, bukan `APP_URL`). Bila domain disajikan sebagai `www.` atau lewat preview domain, canonical tetap menunjuk domain non-`www` — konsisten untuk SEO, tetapi perlu satu aturan redirect kanonik di Nginx/Cloudflare agar tidak ada dua versi host yang benar-benar tersaji. Perubahan pengaturan eksternal sengaja tidak dilakukan secara spekulatif.
3. **Staging/preview lokal** akan menampilkan canonical produksi. Ini konsekuensi keputusan "canonical = domain produksi"; bila nanti ada domain staging dengan konten berbeda, konstanta itu perlu dijadikan konfigurasi per environment.
4. **Pengindeksan Google belum terverifikasi.** Target yang dicapai adalah kelayakan teknis: HTTP 200 tanpa login, metadata lengkap, canonical konsisten dengan sitemap, `robots.txt` benar, dan tidak ada URL internal di sitemap.
5. **Favicon masih 0 byte** (`public/favicon.ico`). Tidak diubah karena membuat aset merek bukan bagian dari lingkup ini; bila diinginkan, sediakan berkas favicon asli lalu rujuk lewat `<link rel="icon">`.
6. **Panjang/kedalaman konten halaman publik** wajar untuk halaman penjelasan aplikasi, tetapi bukan artikel panjang. Bila target pencarian lebih kompetitif, tambahan konten faktual (misalnya penjelasan istilah penilaian) lebih berpengaruh daripada penambahan URL baru.
7. **Tidak ada alat analitik** di aplikasi (tidak ada script pihak ketiga). Data perilaku pengunjung tidak tersedia; itu juga alasan halaman publik tidak mengirim cookie selain cookie sesi.
8. **Berkas CSV data peserta ikut repository** (`storage/app/seed/peserta.csv`, disebut `docs/deployment-production.md` §4.1). Ini risiko privasi tingkat repository, bukan tingkat aplikasi: repository harus tetap privat. Tidak diubah oleh pekerjaan ini dan sengaja tidak dibahas di halaman kebijakan privasi publik.
9. **Cache route/view lama di server** adalah penyebab paling mungkin bila halaman `/dashboard` belum muncul setelah `git pull`; `php artisan optimize:clear` wajib dijalankan.
10. **Waktu respons halaman panel (temuan di luar lingkup SEO, tidak diubah).** Pada `php artisan serve` lokal dengan database kegiatan berisi 702 peserta, `/dashboard`, `/kegiatan`, dan `/hasil` masing-masing memakan sekitar 0,5 detik, sedangkan lima halaman publik sekitar 0,04–1,2 milidetik (halaman publik tidak menyentuh database). Penyebabnya `PanelService::context()` memuat seluruh peserta dan seluruh baris input kegiatan pada setiap permintaan, dan perilaku itu sudah ada sebelum perubahan ini (route dashboard hanya berpindah alamat). Angka server pengembangan bukan angka produksi, tetapi bila halaman panel terasa lambat di produksi, prioritas perbaikannya adalah membatasi pengambilan data per halaman, bukan menyentuh lapisan SEO.
