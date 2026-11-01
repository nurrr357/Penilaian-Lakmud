<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\PanelPermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    /**
     * Isi dasar setiap instalasi, termasuk produksi: 23 izin, peran operator_penilaian, peran
     * ketua, satu akun operator, dan akun ketua opsional dari environment. Tidak ada data contoh
     * di sini; data kegiatan nyata diisi LakmudDataSeeder dari berkas CSV di storage/app/seed.
     *
     * Aman dijalankan ulang: `firstOrCreate` tidak pernah mengubah kata sandi akun yang sudah ada,
     * jadi menjalankan seeder ini pada deployment pembaruan tidak mereset akun pengguna.
     *
     * Di produksi kata sandi wajib ada (OPERATOR_PASSWORD atau DEMO_PASSWORD di .env). Kalau kosong,
     * seeder berhenti dengan pesan yang jelas daripada membuat akun berkata sandi acak yang tidak
     * bisa dipakai login siapa pun.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = PanelPermissions::ALL;
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Operator mengerjakan seluruh alur penilaian, TANPA membaca log aktivitas:
        // jejak audit hanya untuk ketua. activity.view sengaja dikecualikan.
        $role = Role::findOrCreate('operator_penilaian', 'web');
        $role->syncPermissions(array_values(array_filter(
            $permissions,
            fn (string $p) => $p !== PanelPermissions::ACTIVITY_VIEW,
        )));

        $user = User::firstOrCreate(
            ['email' => $this->email()],
            ['name' => 'Operator Penilaian', 'password' => $this->password()],
        );
        $user->syncRoles([$role]);

        // Ketua memantau: semua izin baca + activity.view + menyetujui finalisasi hasil.
        // Tanpa izin input, manage, calculate, unlock — input nilai dan buka kunci tetap
        // tugas operator.
        $ketuaRole = Role::findOrCreate('ketua', 'web');
        $ketuaRole->syncPermissions(array_merge(
            array_filter($permissions, fn (string $p) => str_ends_with($p, '.view')),
            [PanelPermissions::WEIGHTS_VIEW, PanelPermissions::ACTIVITY_VIEW],
        ));
        $ketuaRole->givePermissionTo('results.finalize');

        // Akun ketua tidak punya kata sandi default. Tanpa KETUA_EMAIL dan KETUA_PASSWORD,
        // akun tidak dibuat; ini mencegah kredensial yang diketahui publik masuk ke server.
        $ketuaEmail = (string) config('panel.ketua.email');
        $ketuaPassword = (string) config('panel.ketua.password');
        if ($ketuaEmail !== '' && $ketuaPassword !== '') {
            $ketua = User::firstOrCreate(
                ['email' => $ketuaEmail],
                [
                    'name' => (string) config('panel.ketua.name'),
                    'username' => config('panel.ketua.username') ?: null,
                    'password' => $ketuaPassword,
                ],
            );
            $ketua->syncRoles([$ketuaRole]);
            $this->command?->info(sprintf('Akun ketua %s siap.', $ketua->email));
        } else {
            $this->command?->warn('Akun ketua tidak dibuat. Isi KETUA_EMAIL dan KETUA_PASSWORD di .env, jalankan php artisan config:clear, lalu ulangi seeder ini.');
        }

        $this->command?->info(sprintf('Peran operator_penilaian siap dengan %d izin.', count($permissions)));
        $this->command?->info(sprintf('Akun operator %s siap.', $user->email));

        if (app()->environment('production') && str_ends_with($user->email, '@example.test')) {
            $this->command?->warn('Email akun operator masih alamat contoh. Isi OPERATOR_EMAIL dengan alamat asli, jalankan php artisan config:clear, lalu ulangi seeder ini.');
        }
    }

    private function email(): string
    {
        return (string) (config('panel.operator.email') ?: 'operator@example.test');
    }

    /**
     * Kata sandi hanya dipakai saat akun belum ada. Dibaca dari config, bukan env(), supaya nilainya
     * tetap terbaca setelah `php artisan config:cache` (perintah wajib di produksi): ketika
     * konfigurasi sudah di-cache, berkas .env tidak lagi dimuat sehingga env() mengembalikan null.
     */
    private function password(): string
    {
        $password = (string) config('panel.operator.password');

        if ($password !== '') {
            return $password;
        }

        if (app()->environment('production')) {
            throw new RuntimeException(
                'Kata sandi akun operator belum diisi. Set OPERATOR_PASSWORD (atau DEMO_PASSWORD) di .env, '
                .'jalankan php artisan config:clear, lalu ulangi php artisan db:seed --force.'
            );
        }

        // Di luar produksi kata sandi acak dipakai supaya tidak ada kredensial tetap yang bisa
        // dipakai di lingkungan bersama.
        return Str::random(40);
    }
}
