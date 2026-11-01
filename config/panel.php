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

    /*
     | Akun ketua opsional. Akun ini hanya dibuat kalau KETUA_EMAIL dan KETUA_PASSWORD
     | diisi. Tidak ada kata sandi default: akun dengan kredensial yang diketahui publik
     | tidak boleh masuk ke repository atau server produksi.
     */
    'ketua' => [
        'name' => env('KETUA_NAME', 'Ketua Penilaian'),
        'email' => env('KETUA_EMAIL'),
        'username' => env('KETUA_USERNAME'),
        'password' => env('KETUA_PASSWORD'),
    ],

    /*
     | Saklar sementara untuk sidang: hasil MOORA (Perhitungan & Hasil Perangkingan)
     | disembunyikan dari menu sampai sidang selesai, lalu ubah menjadi true.
     | Backend tetap menjaga route dengan permission, saklar ini hanya menyembunyikan tampilan.
     */
    'show_results' => env('PANEL_SHOW_RESULTS', false),
];
