<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PanelTest extends TestCase
{
    use DatabaseTransactions;

    private function operator(): User
    {
        $this->seed();

        return User::where('email', 'operator@example.test')->firstOrFail();
    }

    public function test_authentication_and_permission_boundary(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertForbidden();
        $this->actingAs($this->operator());
        foreach (['/dashboard', '/kegiatan', '/absensi', '/pretest', '/posttest', '/keaktifan', '/etika', '/pemahaman', '/perhitungan', '/hasil', '/kriteria'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/panduan')->assertNotFound();
    }

    public function test_crud_and_cross_event_input_isolation(): void
    {
        $this->actingAs($this->operator());
        $payload = ['name' => 'CRUD test', 'organization' => 'IPNU', 'venue' => 'Aula', 'start_date' => '2026-10-18', 'end_date' => '2026-10-19'];
        $this->post('/kegiatan', $payload)->assertSessionHasNoErrors();
        $id = DB::table('lakmud_events')->latest('id')->value('id');
        $this->post('/kegiatan', $payload)->assertSessionHasNoErrors();
        $other = DB::table('lakmud_events')->latest('id')->value('id');
        $this->post("/kegiatan/$id/materi", ['name' => 'Materi', 'sequence' => 1, 'is_active' => 1])->assertSessionHasNoErrors();
        $material = DB::table('event_materials')->where('lakmud_event_id', $id)->value('id');
        $this->post("/kegiatan/$other/peserta", ['name' => 'Asing'])->assertSessionHasNoErrors();
        $foreign = DB::table('event_participants')->where('lakmud_event_id', $other)->value('id');
        $this->post("/input/$id/pretest", ['material_id' => $material, 'rows' => [$foreign => ['pretest' => 18]]])->assertSessionHasErrors();
        $this->assertDatabaseMissing('assessment_scores', ['event_participant_id' => $foreign]);
        $this->put("/kegiatan/$other/materi/$material", ['name' => 'Tidak boleh', 'sequence' => 1, 'is_active' => 1])->assertNotFound();
        $this->put("/kegiatan/$id/materi/$material", ['name' => 'Revisi materi', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->get('/materi?event_id='.$id)->assertSee('Revisi materi');
        $this->post("/kegiatan/$id/materi", ['name' => 'Materi kedua', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('event_materials', ['lakmud_event_id' => $id, 'name' => 'Materi kedua', 'sequence' => 2]);
        $this->put("/kegiatan/$id", array_merge($payload, ['name' => 'Revisi kegiatan']))->assertSessionHasNoErrors();
        $this->delete("/kegiatan/$id/materi/$material")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('event_materials', ['id' => $material]);
        $this->delete("/kegiatan/$other/peserta/$foreign")->assertSessionHasNoErrors();
        $this->delete("/kegiatan/$id")->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('lakmud_events', ['id' => $id]);
    }

    public function test_perhitungan_page_is_paginated(): void
    {
        $this->actingAs($this->operator());
        $this->post('/kegiatan', ['name' => 'Pagination', 'organization' => 'IPNU', 'venue' => 'Aula', 'start_date' => '2026-10-18', 'end_date' => '2026-10-19'])->assertSessionHasNoErrors();
        $event = DB::table('lakmud_events')->where('name', 'Pagination')->first();
        for ($i = 1; $i <= 15; $i++) {
            $this->post("/kegiatan/{$event->id}/materi", ['name' => "Materi $i", 'sequence' => $i, 'is_active' => 1])->assertSessionHasNoErrors();
        }
        for ($i = 1; $i <= 11; $i++) {
            $this->post("/kegiatan/{$event->id}/peserta", ['name' => "Peserta $i"])->assertSessionHasNoErrors();
        }
        $participantIds = DB::table('event_participants')->where('lakmud_event_id', $event->id)->pluck('id');
        foreach (DB::table('event_materials')->where('lakmud_event_id', $event->id)->get() as $material) {
            foreach (['pretest', 'posttest', 'keaktifan', 'etika', 'pemahaman', 'absensi'] as $kind) {
                $rows = [];
                foreach ($participantIds as $pid) {
                    $rows[$pid] = match ($kind) {
                        'absensi' => ['attendance' => 'present'], 'keaktifan' => ['activity' => '18'], 'etika' => ['ethics' => '18'], 'pemahaman' => ['understanding' => '18'], default => [$kind => '18']
                    };
                }
                $this->post("/input/{$event->id}/$kind", ['material_id' => $material->id, 'rows' => $rows])->assertSessionHasNoErrors();
            }
        }
        $this->post("/hasil/{$event->id}/calculate")->assertSessionHasNoErrors();

        $this->get('/perhitungan?event_id='.$event->id.'&per_page=10')
            ->assertOk()
            ->assertSee('Halaman 1 dari 2')
            ->assertSee('pagination__item', false);
        $this->get('/perhitungan?event_id='.$event->id.'&per_page=50')
            ->assertOk()
            ->assertSee('Halaman 1 dari 1');
    }

    public function test_perhitungan_snapshot_rows_have_detail_buttons_and_template(): void
    {
        $this->actingAs($this->operator());
        $this->post('/kegiatan', ['name' => 'Detail Modal', 'organization' => 'IPNU', 'venue' => 'Aula', 'start_date' => '2026-10-18', 'end_date' => '2026-10-19'])->assertSessionHasNoErrors();
        $event = DB::table('lakmud_events')->where('name', 'Detail Modal')->first();
        for ($i = 1; $i <= 15; $i++) {
            $this->post("/kegiatan/{$event->id}/materi", ['name' => "Materi $i", 'sequence' => $i, 'is_active' => 1])->assertSessionHasNoErrors();
        }
        $this->post("/kegiatan/{$event->id}/peserta", ['name' => 'Peserta Uji'])->assertSessionHasNoErrors();
        $participant = DB::table('event_participants')->where('lakmud_event_id', $event->id)->first();
        foreach (DB::table('event_materials')->where('lakmud_event_id', $event->id)->get() as $material) {
            foreach (['pretest', 'posttest', 'keaktifan', 'etika', 'pemahaman', 'absensi'] as $kind) {
                $row = match ($kind) {
                    'absensi' => ['attendance' => 'present'], 'keaktifan' => ['activity' => '18'], 'etika' => ['ethics' => '18'], 'pemahaman' => ['understanding' => '18'], default => [$kind => '18']
                };
                $this->post("/input/{$event->id}/$kind", ['material_id' => $material->id, 'rows' => [$participant->id => $row]])->assertSessionHasNoErrors();
            }
        }
        $this->post("/hasil/{$event->id}/calculate")->assertSessionHasNoErrors();

        $this->get('/perhitungan?event_id='.$event->id)
            ->assertOk()
            ->assertSee('data-modal-open="moora-detail-', false)
            ->assertSee('id="moora-detail-', false)
            ->assertSee('Lihat detail')
            ->assertSee('id="hasil-detail-modal"', false)
            ->assertSee('Substitusi angka per kriteria');
    }

    public function test_complete_workflow_validation_snapshots_locks_and_stale(): void
    {
        $this->actingAs($this->operator());
        $this->post('/kegiatan', ['name' => 'Pengujian', 'organization' => 'IPNU', 'venue' => 'Aula', 'start_date' => '2026-10-18', 'end_date' => '2026-10-19'])->assertSessionHasNoErrors();
        $event = DB::table('lakmud_events')->where('name', 'Pengujian')->first();
        $this->post("/kegiatan/{$event->id}/peserta", ['name' => 'Peserta Uji'])->assertSessionHasNoErrors();
        $participant = DB::table('event_participants')->where('lakmud_event_id', $event->id)->first();
        for ($i = 1; $i <= 15; $i++) {
            $this->post("/kegiatan/{$event->id}/materi", ['name' => "Materi $i", 'sequence' => $i, 'is_active' => 1])->assertSessionHasNoErrors();
        }
        $this->post("/hasil/{$event->id}/calculate")->assertSessionHasErrors();
        foreach (DB::table('event_materials')->where('lakmud_event_id', $event->id)->get() as $material) {
            foreach (['pretest', 'posttest', 'keaktifan', 'etika', 'pemahaman', 'absensi'] as $kind) {
                $row = match ($kind) {
                    'absensi' => ['attendance' => 'present'], 'keaktifan' => ['activity' => '18'], 'etika' => ['ethics' => '18'], 'pemahaman' => ['understanding' => '18'], default => [$kind => '18']
                };
                $this->post("/input/{$event->id}/$kind", ['material_id' => $material->id, 'rows' => [$participant->id => $row]])->assertSessionHasNoErrors();
            }
        }
        $material = DB::table('event_materials')->where('lakmud_event_id', $event->id)->first();
        foreach (['9.99', '20.01', '10.001', '1e1'] as $bad) {
            $this->post("/input/{$event->id}/pretest", ['material_id' => $material->id, 'rows' => [$participant->id => ['pretest' => $bad]]])->assertSessionHasErrors();
        }
        $this->post("/hasil/{$event->id}/calculate")->assertSessionHasNoErrors();
        $run = DB::table('moora_runs')->where('lakmud_event_id', $event->id)->latest('id')->first();
        $this->assertSame('valid', $run->status);
        $result = DB::table('moora_results')->where('moora_run_id', $run->id)->first();
        $this->assertEquals(90, $result->certificate_score);
        $this->assertSame('B', $result->certificate_predicate);
        $detail = $this->get('/perhitungan?event_id='.$event->id);
        $detail->assertOk()->assertSee('Peserta Uji')
            ->assertSee('Snapshot Peserta')
            ->assertDontSee('Detail Perhitungan MOORA')
            ->assertSee('Menampilkan ', false)
            ->assertDontSee('C1-C6: ')
            ->assertDontSee('Normalisasi: ')
            ->assertDontSee('Terbobot: ')
            ->assertSee('Lihat detail')
            ->assertSee('data-modal-open="moora-detail-', false);
        $reader = User::factory()->create();
        $reader->givePermissionTo('results.view');
        $this->actingAs($reader)->get('/perhitungan?event_id='.$event->id)
            ->assertOk()->assertSee('Snapshot Peserta')->assertSee('Peserta Uji')
            ->assertDontSee('Hitung MOORA')->assertDontSee('>Finalisasi<', false);
        $this->actingAs($reader)->get('/hasil?event_id='.$event->id)
            ->assertOk()->assertSee('Peringkat MOORA')->assertSee('Peserta Uji')
            ->assertDontSee('>Finalisasi<', false)->assertDontSee('Buka kunci');
        $this->actingAs($this->operator());
        $this->post("/hasil/{$event->id}/finalize")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('moora_runs', ['id' => $run->id, 'status' => 'locked']);
        $this->post("/input/{$event->id}/pretest", ['material_id' => $material->id, 'rows' => [$participant->id => ['pretest' => '19']]])->assertSessionHasErrors();
        $this->post("/kegiatan/{$event->id}/peserta", ['name' => 'Blocked', 'sequence' => 2])->assertSessionHasErrors();
        $this->post("/hasil/{$event->id}/unlock", ['reason' => 'Koreksi nilai'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('moora_runs', ['id' => $run->id, 'status' => 'stale']);
        $this->post("/hasil/{$event->id}/finalize")->assertSessionHasErrors();
        $this->post("/hasil/{$event->id}/calculate")->assertSessionHasNoErrors();
        $this->post("/input/{$event->id}/pretest", ['material_id' => $material->id, 'rows' => [$participant->id => ['pretest' => '19']]])->assertSessionHasNoErrors();
        $this->assertSame('stale', DB::table('moora_runs')->where('lakmud_event_id', $event->id)->latest('id')->value('status'));
        $this->assertDatabaseHas('moora_results', ['id' => $result->id, 'certificate_score' => 90]);
        $this->assertTrue(DB::table('activity_log')->exists());
    }
}
