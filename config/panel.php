<?php

return [
    /*
     | Akun operator pertama yang dibuat `DatabaseSeeder`.
     |
     | Di produksi `OPERATOR_PASSWORD` wajib diisi di .env sebelum `php artisan db:seed --force`;
     | kalau kosong seeder berhenti dengan pesan yang jelas, karena kata sandi acak berarti tidak
     | ada seorang pun yang bisa login. `DEMO_PASSWORD` tetap dibaca sebagai nama lama supaya
     | panduan instalasi sebelumnya dan skrip uji lokal yang memakainya tidak langsung rusak.
     |
     | Nilainya sengaja lewat config, bukan env() di dalam seeder: setelah `php artisan config:cache`
     | (langkah wajib di produksi) berkas .env tidak lagi dibaca saat runtime, sehingga env() di
     | dalam seeder akan mengembalikan null dan kata sandi akun menjadi acak.
     */
    'operator' => [
        'email' => env('OPERATOR_EMAIL', 'operator@example.test'),
        'password' => env('OPERATOR_PASSWORD') ?: env('DEMO_PASSWORD'),
    ],
];
