<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PanelPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KetuaRoleTest extends TestCase
{
    use DatabaseTransactions;

    private function ketua(): User
    {
        $this->seed();
        $user = User::factory()->create();
        $user->assignRole('ketua');

        return $user;
    }

    public function test_ketua_role_is_seeded_with_view_only_permissions(): void
    {
        $this->seed();
        $role = Role::findByName('ketua', 'web');
        $names = $role->permissions->pluck('name')->sort()->values()->all();
        $this->assertSame([
            'activity.view',
            'assessments.activity.view', 'assessments.ethics.view', 'assessments.posttest.view',
            'assessments.pretest.view', 'assessments.understanding.view', 'attendance.view',
            'criteria.view', 'events.view', 'participants.view', 'results.finalize', 'results.view',
        ], $names);
    }

    public function test_activity_log_page_requires_activity_view_permission(): void
    {
        $user = $this->ketua(); // ketua sudah punya activity.view melalui role
        $this->actingAs($user)->get('/log')->assertOk()->assertSee('Log Aktivitas');
        $this->actingAs(User::factory()->create())->get('/log')->assertForbidden();
    }

    public function test_activity_log_lists_recorded_actions(): void
    {
        $this->seed();
        $user = User::firstWhere('email', 'operator@example.test') ?? User::factory()->create();
        $user->givePermissionTo('activity.view');
        activity()->log('audit.probe.unik-2026');
        $this->actingAs($user->fresh())->get('/log')
            ->assertOk()
            ->assertSee('audit.probe.unik-2026');
    }

    public function test_login_and_logout_are_recorded_with_identifier_and_without_password(): void
    {
        $this->seed();
        $user = User::factory()->create(['email' => 'ketua@example.test', 'username' => 'ketua']);
        $user->assignRole('ketua');

        $this->post('/login', ['email' => 'ketua', 'password' => 'password']);
        $this->assertDatabaseHas('activity_log', ['description' => 'auth.login']);
        $login = Activity::where('description', 'auth.login')->latest('id')->first();
        $this->assertSame('ketua', $login->properties->get('identifier'));
        $this->assertStringNotContainsString('password', json_encode($login->properties->toArray()));

        $this->post('/logout');
        $this->assertDatabaseHas('activity_log', ['description' => 'auth.logout']);
    }

    public function test_ketua_cannot_perform_any_mutation(): void
    {
        $user = $this->ketua();
        $this->actingAs($user);
        $writes = [
            '/kegiatan', '/kegiatan/999999/materi', '/kegiatan/999999/peserta',
            '/input/999999/absensi', '/input/999999/pretest', '/input/999999/posttest',
            '/input/999999/keaktifan', '/input/999999/etika', '/input/999999/pemahaman',
            '/kriteria',
            '/hasil/999999/calculate', '/hasil/999999/unlock',
        ];
        // POST diuji untuk semua endpoint tulis; DELETE hanya terdaftar untuk
        // /kegiatan/{event} dan /kegiatan/{event}/{type}/{record}, jadi DELETE di URL lain
        // membalas 405 Method Not Allowed — sama-sama berarti aksi tidak diizinkan.
        foreach ($writes as $url) {
            $this->post($url)->assertForbidden();
        }
        $this->delete('/kegiatan/999999')->assertForbidden();
        $this->delete('/kegiatan/999999/materi/1')->assertForbidden();
        // DELETE tidak terdaftar untuk /input/... dan PUT tidak terdaftar untuk /kriteria —
        // Laravel membalas 405 Method Not Allowed, sama-sama berarti aksi tidak tersedia.
        $this->delete('/input/999999/pretest')->assertStatus(405);
        $this->put('/kriteria', ['weights' => array_fill(0, 6, 0.1)])->assertStatus(405);
    }

    public function test_ketua_can_view_every_read_page(): void
    {
        $user = $this->ketua();
        $this->actingAs($user);
        foreach (['/dashboard', '/kegiatan', '/materi', '/peserta', '/absensi', '/pretest', '/posttest', '/keaktifan', '/etika', '/pemahaman', '/perhitungan', '/hasil', '/kriteria', '/log'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_operator_still_has_finalize_and_all_permissions(): void
    {
        $this->seed();
        $operator = Role::findByName('operator_penilaian', 'web');
        $this->assertTrue($operator->hasPermissionTo('results.finalize'));
        // Operator: semua permission KECUALI activity.view (jejak audit hanya untuk ketua).
        $this->assertSame(count(PanelPermissions::ALL) - 1, $operator->permissions->count());
        $this->assertFalse($operator->hasPermissionTo(PanelPermissions::ACTIVITY_VIEW));
    }
}
