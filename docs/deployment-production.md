# Deployment Production — LAKMUD Penilaian

## Kebutuhan server

- PHP 8.3 atau lebih baru beserta ekstensi: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`, `pdo_mysql`, `session`, `tokenizer`, `xml`, dan `zip`.
- MySQL/MariaDB.
- Composer 2.
- Node.js/npm hanya diperlukan jika aset dibangun di server. Jika folder `public/build` dari hasil build lokal ikut diunggah, Node.js tidak diperlukan di server.
- HTTPS aktif.
- Document root domain harus diarahkan ke folder `public`, bukan root proyek.

## 1. Upload aplikasi

Upload atau clone proyek ke direktori aplikasi, misalnya:

```bash
/var/www/lakmud-penilaian
```

Jangan unggah `.env` lokal, `node_modules`, log, cache, atau kredensial development.

## 2. Instal dependency PHP

Jalankan dari root proyek:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

Jika aset frontend belum ikut diunggah:

```bash
npm ci
npm run build
```

Folder `public/build` wajib tersedia setelah tahap ini.

## 3. Buat environment production

Salin template yang sudah disiapkan:

```bash
cp .env.production.example .env
php artisan key:generate --force
```

Isi minimal berikut dengan nilai server sebenarnya:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-anda.example

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lakmud_penilaian
DB_USERNAME=lakmud_app
DB_PASSWORD=password-kuat-database

SESSION_SECURE_COOKIE=true
```

Ketentuan penting:

- Jangan menggunakan `APP_KEY` dari mesin development. Buat key baru satu kali di server dan jangan menggantinya setelah aplikasi digunakan.
- Jangan memakai user database `root`.
- `APP_URL` harus memakai domain final dan `https://`.
- Biarkan `DEMO_PASSWORD` kosong di production. Seeder membuat password acak jika akun operator belum ada.
- Jika server belum menggunakan HTTPS, jangan membuka aplikasi ke publik. Pasang TLS terlebih dahulu.

## 4. Database

Buat database dan user khusus aplikasi, lalu jalankan:

```bash
php artisan migrate --force
```

Untuk instalasi pertama yang membutuhkan role, permission, dan akun operator, isi `DEMO_PASSWORD` sementara di `.env` dengan password kuat, lalu jalankan:

```bash
php artisan config:clear
php artisan db:seed --force
```

Seeder membuat akun `operator@example.test` pada instalasi baru. Setelah seeding berhasil, hapus nilai `DEMO_PASSWORD` dari `.env`, lalu jalankan `php artisan config:clear`. Jangan memasukkan password ke repository, perintah shell, tiket, atau dokumentasi. `firstOrCreate` tidak mengubah password akun yang sudah ada; pada deployment pembaruan, pertahankan akun production yang sudah ada dan jangan mengandalkan seeder untuk mereset password.

> Sebelum menjalankan migrasi pada database yang sudah berisi data, buat dan verifikasi backup database di luar direktori proyek.

## 5. Permission direktori

User web server harus dapat menulis ke `storage` dan `bootstrap/cache`:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
```

Sesuaikan `www-data` dengan user PHP-FPM/web server pada server Anda.

## 6. Optimasi Laravel

```bash
php artisan optimize:clear
php artisan optimize
```

Aplikasi ini saat ini memakai `QUEUE_CONNECTION=sync`, sehingga tidak memerlukan queue worker. Tidak ada schedule aplikasi yang harus dipasang saat panduan ini dibuat.

## 7. Konfigurasi Nginx (VPS)

Contoh minimal:

```nginx
server {
    listen 80;
    server_name domain-anda.example;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name domain-anda.example;

    root /var/www/lakmud-penilaian/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

Sesuaikan socket PHP-FPM dan lokasi sertifikat TLS dengan server. Setelah konfigurasi berubah:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

## 8. Shared hosting/cPanel

Pilihan terbaik adalah mengarahkan document root domain/subdomain langsung ke `<root-proyek>/public`.

Jika hosting tidak mengizinkan perubahan document root, jangan memindahkan seluruh aplikasi ke `public_html`. Simpan source Laravel di luar `public_html`, lalu konsultasikan pemetaan folder `public` dengan penyedia hosting. File `.env`, `vendor`, `storage`, source PHP, dan database tidak boleh dapat diunduh dari web.

Jalankan perintah Composer/Artisan dari Terminal cPanel jika tersedia. Bila server tidak menyediakan Node.js, bangun `public/build` secara lokal lalu unggah hasilnya.

## 9. Pemeriksaan setelah deploy

```bash
php artisan about --only=environment
php artisan migrate:status
php artisan route:list --except-vendor
```

Pastikan hasil `about` menunjukkan:

- Environment: `production`
- Debug Mode: `OFF`
- URL: domain HTTPS final
- Locale: `id`
- Timezone: `Asia/Jakarta`

Lakukan smoke test:

1. `https://domain-anda.example/up` merespons sukses.
2. Halaman login dapat dibuka melalui HTTPS.
3. URL tanpa login mengarah ke `/login`.
4. Login operator berhasil setelah password ditetapkan.
5. Halaman dashboard, kegiatan, materi, peserta, input penilaian, perhitungan, dan hasil dapat dibuka sesuai permission.
6. Buat satu data uji, pastikan tersimpan, lalu hapus data uji tersebut.
7. Periksa `storage/logs/laravel.log` dan log PHP-FPM/web server; tidak boleh ada error baru.

## 10. Urutan deploy pembaruan berikutnya

```bash
php artisan down --retry=60

git pull --ff-only
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize:clear
php artisan optimize

php artisan up
```

Jika build dilakukan di CI/lokal, tahap `npm ci` dan `npm run build` di server dapat diganti dengan upload folder `public/build` dari artefak yang sudah diverifikasi.

## Rollback minimum

Sebelum pembaruan:

1. Backup database.
2. Catat commit/release aplikasi yang sedang aktif.
3. Simpan release sebelumnya sampai smoke test release baru selesai.

Jika deploy gagal, kembalikan source ke release sebelumnya dan pulihkan database hanya jika migrasi yang dijalankan tidak kompatibel dengan release lama. Jangan menjalankan `migrate:rollback` secara otomatis pada production tanpa meninjau isi migrasinya.
