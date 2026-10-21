<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PanelPermissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ProductionSeederTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Seeder dipanggil langsung, bukan lewat `$this->seed()`: di lingkungan produksi perintah
     * `db:seed` menanyakan konfirmasi lebih dulu dan output konsolnya diganti mock oleh harness
     * pengujian, sehingga kedua hal itu mengaburkan apa yang sebenarnya diuji di sini.
     */
    private function jalankanSeeder(): void
    {
        (new DatabaseSeeder)->run();
    }

    /** Produksi tanpa kata sandi harus berhenti, bukan membuat akun berkata sandi acak. */
    public function test_seeder_dasar_berhenti_di_produksi_tanpa_kata_sandi(): void
    {
        $this->app['env'] = 'production';
        config(['panel.operator.password' => null, 'panel.operator.email' => 'operator@lakmud.test']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/OPERATOR_PASSWORD/');

        $this->jalankanSeeder();
    }

    /** Dengan kata sandi diisi, akun operator produksi benar-benar bisa login dan berperan benar. */
    public function test_seeder_dasar_membuat_akun_operator_produksi(): void
    {
        $this->app['env'] = 'production';
        config([
            'panel.operator.email' => 'operator@lakmud.test',
            'panel.operator.password' => 'kata-sandi-produksi-panjang',
        ]);

        $this->jalankanSeeder();

        $user = User::where('email', 'operator@lakmud.test')->firstOrFail();
        $this->assertTrue(Hash::check('kata-sandi-produksi-panjang', $user->password), 'kata sandi tersimpan sebagai hash yang cocok');
        $this->assertTrue($user->hasRole('operator_penilaian'), 'akun memegang peran operator_penilaian');

        foreach (PanelPermissions::ALL as $nama) {
            $this->assertTrue(Permission::where('name', $nama)->exists(), 'izin '.$nama.' tersedia');
        }
    }

    /** Seeder dasar tidak membawa data contoh apa pun ke produksi. */
    public function test_seeder_dasar_tidak_menambah_data_contoh(): void
    {
        $this->app['env'] = 'production';
        config([
            'panel.operator.email' => 'operator@lakmud.test',
            'panel.operator.password' => 'kata-sandi-produksi-panjang',
        ]);

        $hitung = fn () => [
            DB::table('lakmud_events')->count(),
            DB::table('participants')->count(),
            DB::table('event_materials')->count(),
            DB::table('moora_runs')->count(),
            DB::table('moora_results')->count(),
        ];

        $sebelum = $hitung();
        $this->jalankanSeeder();

        $this->assertSame($sebelum, $hitung(), 'seeder dasar hanya menulis izin, peran, dan akun');
    }

    /** Menjalankan ulang tidak menggandakan akun dan tidak mereset kata sandi yang sudah ada. */
    public function test_seeder_dasar_aman_dijalankan_ulang(): void
    {
        $this->app['env'] = 'production';
        config([
            'panel.operator.email' => 'operator@lakmud.test',
            'panel.operator.password' => 'sandi-pertama-panjang',
        ]);

        $this->jalankanSeeder();
        config(['panel.operator.password' => 'sandi-kedua-panjang']);
        $this->jalankanSeeder();

        $this->assertSame(1, User::where('email', 'operator@lakmud.test')->count(), 'akun tidak digandakan');
        $this->assertTrue(
            Hash::check('sandi-pertama-panjang', User::where('email', 'operator@lakmud.test')->firstOrFail()->password),
            'kata sandi lama tidak diubah oleh seeder'
        );
    }
}
