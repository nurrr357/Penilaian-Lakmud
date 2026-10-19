<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_storage_uses_the_inherited_assessment_contract(): void
    {
        foreach (['attendance_records' => ['event_participant_id', 'event_material_id', 'status', 'notes', 'marked_by', 'marked_at'], 'assessment_scores' => ['event_participant_id', 'event_material_id', 'assessment_type', 'score', 'scored_by', 'scored_at'], 'moora_aspect_scores' => ['activity', 'ethics', 'understanding', 'scored_by'], 'moora_runs' => ['lakmud_event_id', 'locked_at'], 'moora_results' => ['moora_run_id', 'event_participant_id', 'created_at', 'updated_at']] as $table => $columns) {
            $this->assertTrue(Schema::hasColumns($table, $columns), $table);
        }
        $this->assertFalse(Schema::hasColumn('moora_results', 'participant_name'));
        $this->assertFalse(Schema::hasTable('inputs'));
    }

    public function test_read_only_pages_hide_mutation_controls_with_existing_data(): void
    {
        $this->seed();
        $operator = User::where('email', 'operator@example.test')->firstOrFail();
        $this->actingAs($operator)->post('/kegiatan', ['name' => 'Read boundary', 'organization' => 'QA', 'venue' => 'QA', 'start_date' => '2026-10-18', 'end_date' => '2026-10-19']);
        $id = DB::table('lakmud_events')->latest('id')->value('id');
        $this->post("/kegiatan/$id/peserta", ['name' => 'Private roster', 'sequence' => 1]);
        $this->post("/kegiatan/$id/materi", ['name' => 'Material QA', 'sequence' => 1, 'is_active' => 1]);
        $reader = User::factory()->create();
        $reader->givePermissionTo(['events.view', 'attendance.view', 'results.view']);
        $this->actingAs($reader)->get("/kegiatan?event_id=$id")->assertOk()->assertDontSee('Private roster')->assertDontSee('Tambah kegiatan')->assertDontSee('Hapus materi');
        $this->get("/absensi?event_id=$id")->assertOk()->assertDontSee('Simpan absensi')->assertDontSee('name="rows[', false);
        $this->get("/hasil?event_id=$id")->assertOk()->assertDontSee('Hitung MOORA')->assertDontSee('>Finalisasi<', false);
        $reader->givePermissionTo('participants.view');
        $this->actingAs($reader->fresh())->get("/peserta?event_id=$id")->assertOk()->assertSee('Private roster')->assertDontSee('Tambah peserta')->assertDontSee('Hapus peserta');
    }

    public function test_participant_read_permission_is_independent_and_read_only(): void
    {
        $this->seed();
        $user = User::factory()->create();
        $this->actingAs($user)->get('/peserta')->assertForbidden();
        $user->givePermissionTo('participants.view');
        $this->actingAs($user->fresh())->get('/peserta')->assertOk()->assertDontSee('Tambah peserta')->assertDontSee('Tambah kegiatan');
        $this->get('/kegiatan')->assertForbidden();
        $this->post('/kegiatan/1/peserta')->assertForbidden();
    }
}
