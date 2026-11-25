<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class MooraExportTest extends TestCase
{
    use DatabaseTransactions;

    private function operator(): User
    {
        $this->seed();

        return User::where('email', 'operator@example.test')->firstOrFail();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function eventWithRun(bool $withResults = true): array
    {
        $user = $this->operator();
        $event = DB::table('lakmud_events')->insertGetId([
            'name' => 'TEST Export',
            'organization' => 'IPNU',
            'venue' => 'Aula',
            'start_date' => '2026-10-18',
            'end_date' => '2026-10-19',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $run = DB::table('moora_runs')->insertGetId([
            'lakmud_event_id' => $event,
            'status' => 'valid',
            'formula_version' => 'moora-v1',
            'weights' => json_encode([0.10, 0.10, 0.20, 0.15, 0.20, 0.25]),
            'divisors' => json_encode([1, 2, 3, 4, 5, 6]),
            'alternative_count' => $withResults ? 1 : 0,
            'calculated_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($withResults) {
            $participant = DB::table('participants')->insertGetId([
                'full_name' => 'Peserta Export',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $registration = DB::table('event_participants')->insertGetId([
                'lakmud_event_id' => $event,
                'participant_id' => $participant,
                'sequence_number' => 1,
                'status' => 'registered',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('moora_results')->insert([
                'moora_run_id' => $run,
                'event_participant_id' => $registration,
                'criteria' => json_encode([0, 227, 250, 221, 235, 263]),
                'normalized' => json_encode([0, 0.5, 0.6, 0.7, 0.8, 0.9]),
                'weighted' => json_encode([0, 0.05, 0.12, 0.105, 0.16, 0.225]),
                'material_scores' => json_encode([]),
                'yi' => 0.66,
                'benefit_total' => 0.76,
                'cost_total' => 0.1,
                'rank' => 1,
                'certificate_score' => 85.5,
                'certificate_predicate' => 'B',
                'certificate_status' => 'LULUS',
            ]);
        }

        return [$event, $run];
    }

    public function test_export_requires_authentication_and_results_permission(): void
    {
        $this->get('/hasil/1/export')->assertRedirect('/login');

        $user = User::factory()->create();
        $this->actingAs($user)->get('/hasil/1/export')->assertForbidden();
    }

    public function test_export_returns_xlsx_with_ranking_and_detail_sheets(): void
    {
        [$event] = $this->eventWithRun();

        $response = $this->actingAs($this->operator())->get('/hasil/'.$event.'/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));

        $path = $response->getFile()->getPathname();
        $this->assertFileExists($path);

        $workbook = IOFactory::load($path);
        $this->assertSame(['Hasil Perangkingan', 'Detail Perhitungan'], $workbook->getSheetNames());
        $this->assertSame(1, $workbook->getSheet(0)->getCell('A11')->getValue());
        $this->assertSame('Peserta Export', $workbook->getSheet(0)->getCell('B11')->getValue());
        $this->assertSame('Peserta Export', $workbook->getSheet(1)->getCell('B7')->getValue());
        $this->assertSame(0.66, $workbook->getSheet(1)->getCell('W7')->getValue());
        $workbook->disconnectWorksheets();
        @unlink($path);
    }

    public function test_export_button_only_shows_when_run_exists(): void
    {
        [$eventWithRun] = $this->eventWithRun();
        $operator = $this->operator();

        $this->actingAs($operator)
            ->get('/hasil?event_id='.$eventWithRun)
            ->assertOk()
            ->assertSee('Export Excel')
            ->assertSee('href="/hasil/'.$eventWithRun.'/export"', false);

        $eventWithoutRun = DB::table('lakmud_events')->insertGetId([
            'name' => 'TEST tombol tanpa run',
            'organization' => 'IPNU',
            'venue' => 'Aula',
            'start_date' => '2026-10-18',
            'end_date' => '2026-10-19',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($operator)
            ->get('/perhitungan?event_id='.$eventWithoutRun)
            ->assertOk()
            ->assertDontSee('Export Excel');
    }

    public function test_export_without_run_redirects_with_message(): void
    {
        $event = DB::table('lakmud_events')->insertGetId([
            'name' => 'TEST tanpa run',
            'organization' => 'IPNU',
            'venue' => 'Aula',
            'start_date' => '2026-10-18',
            'end_date' => '2026-10-19',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($this->operator())
            ->get('/hasil/'.$event.'/export')
            ->assertRedirect('/hasil?event_id='.$event)
            ->assertSessionHasErrors('panel');
    }
}
