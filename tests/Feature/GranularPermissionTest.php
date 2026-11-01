<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PanelPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class GranularPermissionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_activity_view_permission_exists_and_is_seeded(): void
    {
        $this->seed();
        $this->assertContains('activity.view', PanelPermissions::ALL);
        $this->assertDatabaseHas('permissions', ['name' => 'activity.view', 'guard_name' => 'web']);
    }

    public function test_configured_ketua_account_can_login_with_username(): void
    {
        config([
            'panel.ketua.name' => 'Ketua Uji',
            'panel.ketua.email' => 'ketua@example.test',
            'panel.ketua.username' => 'ketua',
            'panel.ketua.password' => 'sandi-ketua-uji',
        ]);

        $this->seed();
        $this->assertDatabaseHas('users', ['username' => 'ketua']);

        $ketua = User::where('username', 'ketua')->first();
        $this->assertNotSame('sandi-ketua-uji', $ketua->password, 'Password tidak boleh tersimpan plaintext.');

        $this->post('/logout'); // pastikan guest
        // intended() mengembalikan URL terakhir yang diminta ('/'), jadi cek isi halaman
        // setelah login: '/' mengarah ke dashboard (redirect -> '/dashboard'), bukan ke login.
        $this->followingRedirects()->post('/login', ['email' => 'ketua', 'password' => 'sandi-ketua-uji'])
            ->assertOk()
            ->assertSee('Dashboard', false);
    }

    public function test_seeder_does_not_create_a_default_admin_account(): void
    {
        config(['panel.ketua.email' => null, 'panel.ketua.password' => null]);

        $this->seed();

        $this->assertDatabaseMissing('users', ['username' => 'admin']);
        $this->assertDatabaseMissing('users', ['email' => 'admin@example.test']);
    }

    public function test_each_page_requires_its_own_permission(): void
    {
        $this->seed();
        $pages = ['/dashboard' => 'events.view', '/kegiatan' => 'events.view', '/absensi' => 'attendance.view', '/pretest' => 'assessments.pretest.view', '/posttest' => 'assessments.posttest.view', '/keaktifan' => 'assessments.activity.view', '/etika' => 'assessments.ethics.view', '/pemahaman' => 'assessments.understanding.view', '/perhitungan' => 'results.view', '/hasil' => 'results.view', '/kriteria' => 'criteria.view'];
        foreach ($pages as $url => $permission) {
            Permission::findOrCreate($permission, 'web');
            $user = User::factory()->create();
            $this->actingAs($user)->get($url)->assertForbidden();
            $user->givePermissionTo($permission);
            $this->actingAs($user->fresh())->get($url)->assertOk();
        }
    }

    public function test_individual_write_permissions_do_not_grant_other_writes(): void
    {
        $this->seed();
        $routes = [
            '/kegiatan' => 'events.manage',
            '/kegiatan/999999/materi' => 'materials.manage',
            '/kegiatan/999999/peserta' => 'participants.manage',
            '/input/999999/absensi' => 'attendance.input',
            '/input/999999/pretest' => 'assessments.pretest.input',
            '/input/999999/posttest' => 'assessments.posttest.input',
            '/input/999999/keaktifan' => 'assessments.activity.input',
            '/input/999999/etika' => 'assessments.ethics.input',
            '/input/999999/pemahaman' => 'assessments.understanding.input',
            '/hasil/999999/calculate' => 'results.calculate',
            '/hasil/999999/finalize' => 'results.finalize',
            '/hasil/999999/unlock' => 'results.unlock',
        ];
        foreach ($routes as $allowed => $permission) {
            $user = User::factory()->create();
            $user->givePermissionTo($permission);
            $this->actingAs($user);
            foreach ($routes as $url => $otherPermission) {
                $response = $this->post($url);
                if ($url === $allowed) {
                    $this->assertContains($response->status(), [302, 404]);
                } else {
                    $response->assertForbidden();
                }
            }
        }
    }

    public function test_view_permission_does_not_authorize_mutations(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('events.view', 'web'));
        $this->actingAs($user);
        foreach (['/kegiatan', '/kegiatan/1/materi', '/kegiatan/1/peserta', '/input/1/absensi', '/input/1/pretest', '/input/1/posttest', '/input/1/keaktifan', '/input/1/etika', '/input/1/pemahaman', '/hasil/1/calculate', '/hasil/1/finalize', '/hasil/1/unlock'] as $url) {
            $this->post($url)->assertForbidden();
        }
    }
}
