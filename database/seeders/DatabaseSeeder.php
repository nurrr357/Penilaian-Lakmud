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
     * Isi dasar setiap instalasi, termasuk produksi: 21 izin, satu peran operator_penilaian, dan
     * satu akun operator. Tidak ada data contoh di sini; data kegiatan nyata diisi LakmudDataSeeder
     * dari berkas CSV yang diunggah manual.
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

        $role = Role::findOrCreate('operator_penilaian', 'web');
        $role->syncPermissions($permissions);

        $user = User::firstOrCreate(
            ['email' => $this->email()],
            ['name' => 'Operator Penilaian', 'password' => $this->password()],
        );
        $user->syncRoles([$role]);

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
