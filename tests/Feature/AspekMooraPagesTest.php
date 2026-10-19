<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AspekMooraPagesTest extends TestCase
{
    use DatabaseTransactions;

    private function fixture(int $participants = 12): array
    {
        $this->seed();
        $event = DB::table('lakmud_events')->insertGetId([
            'name' => 'TEST ASPEK MOORA', 'organization' => 'TEST', 'venue' => 'TEST',
            'start_date' => '2026-10-01', 'end_date' => '2026-10-02',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $material = DB::table('event_materials')->insertGetId([
            'lakmud_event_id' => $event, 'name' => 'TEST Materi', 'sequence' => 1,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $ids = [];
        for ($i = 1; $i <= $participants; $i++) {
            $person = DB::table('participants')->insertGetId([
                'full_name' => sprintf('TEST Peserta %02d', $i),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $ids[] = DB::table('event_participants')->insertGetId([
                'lakmud_event_id' => $event, 'participant_id' => $person,
                'sequence_number' => $i, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [$event, $material, $ids];
    }

    public static function aspectPages(): array
    {
        return [
            'absensi' => ['absensi', 'attendance.view', 'attendance.input', 'attendance', 'present', 'attendance_records', 'status', 'Absensi'],
            'pretest' => ['pretest', 'assessments.pretest.view', 'assessments.pretest.input', 'pretest', '11.11', 'assessment_scores', 'score', 'Pretest'],
            'posttest' => ['posttest', 'assessments.posttest.view', 'assessments.posttest.input', 'posttest', '12.12', 'assessment_scores', 'score', 'Posttest'],
            'keaktifan' => ['keaktifan', 'assessments.activity.view', 'assessments.activity.input', 'activity', '13.13', 'moora_aspect_scores', 'activity', 'Keaktifan'],
            'etika' => ['etika', 'assessments.ethics.view', 'assessments.ethics.input', 'ethics', '14.14', 'moora_aspect_scores', 'ethics', 'Etika'],
            'pemahaman' => ['pemahaman', 'assessments.understanding.view', 'assessments.understanding.input', 'understanding', '15.15', 'moora_aspect_scores', 'understanding', 'Pemahaman'],
        ];
    }

    public function test_sidebar_exposes_six_authorized_aspek_moora_destinations(): void
    {
        [$event] = $this->fixture(1);
        $response = $this->actingAs(User::factory()->create()->assignRole('operator_penilaian'))
            ->get('/keaktifan?event_id='.$event);

        $response->assertOk()->assertSee('ASPEK MOORA');
        foreach (['Absensi' => '/absensi', 'Pretest' => '/pretest', 'Posttest' => '/posttest', 'Keaktifan' => '/keaktifan', 'Etika' => '/etika', 'Pemahaman' => '/pemahaman'] as $label => $url) {
            $response->assertSee($label)->assertSee('href="'.$url.'?event_id='.$event.'"', false);
        }
        $response->assertDontSee('Aspek MOORA</a>', false)->assertDontSee('<details', false);
        $this->get('/aspek?event_id='.$event)->assertNotFound();
    }

    public function test_sidebar_hides_aspek_moora_group_when_no_child_page_is_authorized(): void
    {
        [$event] = $this->fixture(1);
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('events.view', 'web'));

        $this->actingAs($user)->get('/kegiatan?event_id='.$event)
            ->assertOk()
            ->assertDontSee('>ASPEK MOORA<', false)
            ->assertDontSee('id="aspek-moora-heading"', false);
    }

    public function test_each_page_has_its_own_route_table_pagination_and_modal_actions(): void
    {
        [$event] = $this->fixture();
        $user = User::factory()->create()->assignRole('operator_penilaian');

        foreach (['absensi', 'pretest', 'posttest', 'keaktifan', 'etika', 'pemahaman'] as $page) {
            $response = $this->actingAs($user)->get('/'.$page.'?event_id='.$event);
            $response->assertOk()
                ->assertViewHas('listing', fn ($rows) => count($rows) === 10 && $rows->total() === 12)
                ->assertSee('<table', false)
                ->assertSee('Aksi')
                ->assertSee('<dialog', false)
                ->assertSee('data-modal-open', false)
                ->assertSee('name="q"', false)
                ->assertSee('value="25"', false)
                ->assertSee('value="50"', false)
                ->assertDontSee('data-unsaved', false);
            $this->get('/'.$page.'?event_id='.$event.'&page=2')
                ->assertViewHas('listing', fn ($rows) => count($rows) === 2 && $rows->firstItem() === 11)
                ->assertSee('TEST Peserta 11')->assertSee('TEST Peserta 12');
        }
    }

    #[DataProvider('aspectPages')]
    public function test_each_aspect_page_has_independent_view_and_input_permissions(string $page, string $view, string $input, string $field, string $value, string $table, string $column, string $label): void
    {
        [$event, $material, $ids] = $this->fixture(1);
        Permission::findOrCreate($view, 'web');
        Permission::findOrCreate($input, 'web');
        $user = User::factory()->create();
        $payload = ['material_id' => $material, 'rows' => [$ids[0] => [$field => $value]]];

        $this->actingAs($user)->get('/'.$page.'?event_id='.$event)->assertForbidden();
        $user->givePermissionTo($view);
        $this->actingAs($user->fresh())->get('/'.$page.'?event_id='.$event)
            ->assertOk()->assertSee($label)->assertDontSee('data-modal-open', false)->assertDontSee('<dialog', false);
        $this->post('/input/'.$event.'/'.$page, $payload)->assertForbidden();
        $user->givePermissionTo($input);
        $this->actingAs($user->fresh())->post('/input/'.$event.'/'.$page, $payload)->assertSessionHasNoErrors();
        $expected = ['event_participant_id' => $ids[0], 'event_material_id' => $material, $column => $value];
        if ($table === 'assessment_scores') {
            $expected['assessment_type'] = $field;
        }
        $this->assertDatabaseHas($table, $expected);
    }

    public function test_modal_save_marks_existing_result_stale(): void
    {
        [$event, $material, $ids] = $this->fixture(2);
        $user = User::factory()->create()->assignRole('operator_penilaian');
        $this->actingAs($user);
        DB::table('moora_runs')->insert([
            'lakmud_event_id' => $event, 'status' => 'valid', 'formula_version' => 'moora-v1',
            'weights' => '[]', 'divisors' => '[]', 'alternative_count' => 2,
            'calculated_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->post('/input/'.$event.'/keaktifan', [
            'material_id' => $material, '_modal' => 'score-'.$ids[0],
            '_table' => ['q' => 'Peserta 01', 'per_page' => 25, 'page' => 1],
            'rows' => [$ids[0] => ['activity' => '18.50']],
        ])->assertRedirect('/keaktifan?event_id='.$event.'&material_id='.$material.'&q=Peserta+01&per_page=25&page=1');
        $this->assertDatabaseHas('moora_aspect_scores', ['event_participant_id' => $ids[0], 'activity' => 18.50]);
        $this->assertSame('stale', DB::table('moora_runs')->where('lakmud_event_id', $event)->value('status'));
    }

    public function test_invalid_modal_reopens_exact_dialog_with_value_error_state_and_message(): void
    {
        [$event, $material, $ids] = $this->fixture(2);
        $this->actingAs(User::factory()->create()->assignRole('operator_penilaian'));
        $response = $this->followingRedirects()->from('/etika?event_id='.$event)->post('/input/'.$event.'/etika', [
            'material_id' => $material, '_modal' => 'score-'.$ids[1],
            'rows' => [$ids[1] => ['ethics' => '20.001']],
        ]);
        $response->assertOk()
            ->assertSee('id="score-'.$ids[1].'" class="crud-modal" aria-labelledby="score-'.$ids[1].'-title"  data-reopen', false)
            ->assertSee('value="20.001"', false)->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="score-'.$ids[1].'-value-error"', false)
            ->assertSee('id="score-'.$ids[1].'-value-error"', false)->assertSee('Data belum disimpan.');
    }

    #[DataProvider('aspectPages')]
    public function test_finalized_aspect_pages_are_exactly_read_only_and_reject_direct_posts(string $page, string $view, string $input, string $field, string $value, string $table, string $column, string $label): void
    {
        [$event, $material, $ids] = $this->fixture(1);
        $this->actingAs(User::factory()->create()->assignRole('operator_penilaian'));
        DB::table('lakmud_events')->where('id', $event)->update(['finalized' => true]);
        $this->get('/'.$page.'?event_id='.$event.'&material_id='.$material)
            ->assertOk()->assertSee($label)->assertSee('Hanya baca')
            ->assertDontSee('<dialog', false)->assertDontSee('data-modal-open', false)->assertDontSee('name="rows[', false);
        $before = DB::table($table)->count();
        $this->post('/input/'.$event.'/'.$page, ['material_id' => $material, 'rows' => [$ids[0] => [$field => $value]]])->assertSessionHasErrors();
        $this->assertSame($before, DB::table($table)->count());
    }

    public function test_save_retains_all_table_filters_and_requested_page_when_results_still_span_it(): void
    {
        [$event, $material, $ids] = $this->fixture(12);
        $this->actingAs(User::factory()->create()->assignRole('operator_penilaian'));
        $this->post('/input/'.$event.'/pretest', [
            'material_id' => $material, '_table' => ['q' => 'TEST Peserta', 'per_page' => 10, 'page' => 2],
            'rows' => [$ids[11] => ['pretest' => '16.16']],
        ])->assertRedirect('/pretest?event_id='.$event.'&material_id='.$material.'&q=TEST+Peserta&per_page=10&page=2');
        $this->get('/pretest?event_id='.$event.'&material_id='.$material.'&q=TEST+Peserta&per_page=10&page=2')
            ->assertOk()->assertSee('TEST Peserta 11')->assertSee('TEST Peserta 12');
    }

    #[DataProvider('aspectPages')]
    public function test_each_page_maps_to_its_exact_label_input_name_url_and_storage(string $page, string $view, string $input, string $field, string $value, string $table, string $column, string $label): void
    {
        [$event, $material, $ids] = $this->fixture(1);
        $this->actingAs(User::factory()->create()->assignRole('operator_penilaian'));
        $this->get('/'.$page.'?event_id='.$event.'&material_id='.$material)
            ->assertOk()->assertSee($label)->assertSee('action="/input/'.$event.'/'.$page.'"', false)
            ->assertSee('name="rows['.$ids[0].']['.$field.']"', false);
        $this->post('/input/'.$event.'/'.$page, ['material_id' => $material, 'rows' => [$ids[0] => [$field => $value]]])->assertSessionHasNoErrors();
        $expected = ['event_participant_id' => $ids[0], 'event_material_id' => $material, $column => $value];
        if ($table === 'assessment_scores') {
            $expected['assessment_type'] = $field;
        }
        $this->assertDatabaseHas($table, $expected);
        $this->get('/'.$page.'?event_id='.$event.'&material_id='.$material)->assertSee($field === 'attendance' ? 'Hadir' : $value);
    }

    public function test_saving_one_modal_leaves_off_page_records_untouched(): void
    {
        [$event, $material, $ids] = $this->fixture();
        $this->actingAs(User::factory()->create()->assignRole('operator_penilaian'));
        DB::table('assessment_scores')->insert([
            'event_participant_id' => $ids[0], 'event_material_id' => $material,
            'assessment_type' => 'pretest', 'score' => 17,
        ]);
        DB::table('assessment_scores')->insert([
            'event_participant_id' => $ids[11], 'event_material_id' => $material,
            'assessment_type' => 'pretest', 'score' => 18,
        ]);

        $this->post('/input/'.$event.'/pretest', [
            'material_id' => $material, '_table' => ['page' => 2, 'per_page' => 10],
            'rows' => [$ids[11] => ['pretest' => 19]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('assessment_scores', ['event_participant_id' => $ids[0], 'score' => 17]);
        $this->assertDatabaseHas('assessment_scores', ['event_participant_id' => $ids[11], 'score' => 19]);
    }
}
