<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MooraCalculator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class WeightsManagementTest extends TestCase
{
    use DatabaseTransactions;

    /** Kegiatan lengkap: 1 peserta, 15 materi, seluruh input terisi. */
    private function kegiatanLengkap(): array
    {
        $this->seed();
        $event = DB::table('lakmud_events')->insertGetId(['name' => 'TEST bobot', 'organization' => 'TEST', 'venue' => 'TEST', 'start_date' => '2026-10-01', 'end_date' => '2026-10-02']);
        $person = DB::table('participants')->insertGetId(['full_name' => 'TEST Bobot Peserta']);
        $peserta = DB::table('event_participants')->insertGetId(['lakmud_event_id' => $event, 'participant_id' => $person, 'sequence_number' => 1]);
        for ($i = 1; $i <= 15; $i++) {
            $material = DB::table('event_materials')->insertGetId(['lakmud_event_id' => $event, 'name' => 'TEST materi '.$i, 'sequence' => $i, 'is_active' => true]);
            $key = ['event_participant_id' => $peserta, 'event_material_id' => $material];
            DB::table('attendance_records')->insert($key + ['status' => 'present']);
            DB::table('assessment_scores')->insert($key + ['assessment_type' => 'pretest', 'score' => 18]);
            DB::table('assessment_scores')->insert($key + ['assessment_type' => 'posttest', 'score' => 18]);
            DB::table('moora_aspect_scores')->insert($key + ['activity' => 18, 'ethics' => 18, 'understanding' => 18]);
        }

        return [$event, $peserta];
    }

    private function operator(): User
    {
        $this->seed();

        return User::where('email', 'operator@example.test')->firstOrFail();
    }

    public function test_bobot_default_dipakai_selama_setelan_belum_disimpan(): void
    {
        $this->actingAs($this->operator())->get('/kriteria')
            ->assertOk()
            ->assertViewHas('bobot', fn (array $b) => $b === MooraCalculator::WEIGHTS)
            ->assertViewHas('terkunci', null)
            ->assertSee('Kriteria &amp; Bobot', false)
            ->assertSee('Simpan bobot')
            ->assertDontSee('Bobot snapshot run', false);
    }

    public function test_bobot_tersimpan_dan_dipakai_perhitungan_berikutnya(): void
    {
        [$event] = $this->kegiatanLengkap();
        $this->actingAs($this->operator());

        $baru = [0.05, 0.05, 0.30, 0.20, 0.20, 0.20];
        $this->post('/kriteria', ['weights' => $baru])->assertSessionHasNoErrors();
        $this->assertSame($baru, json_decode(DB::table('moora_weights')->value('weights'), true));

        $this->post('/hasil/'.$event.'/calculate')->assertSessionHasNoErrors();
        $run = DB::table('moora_runs')->where('lakmud_event_id', $event)->latest('id')->first();
        $this->assertSame($baru, json_decode($run->weights, true));
    }

    public function test_jumlah_bobot_bukan_satu_ditolak(): void
    {
        $this->actingAs($this->operator());

        $this->post('/kriteria', ['weights' => [0.10, 0.10, 0.20, 0.15, 0.20, 0.20]])
            ->assertSessionHasErrors('weights');
        $this->post('/kriteria', ['weights' => [0.10, 0.10, 0.20, 0.15, 0.20]])
            ->assertSessionHasErrors('weights');
        $this->assertSame(0, DB::table('moora_weights')->count());
    }

    public function test_bobot_terkunci_selama_ada_hasil_sah_dan_terbuka_setelah_input_diubah(): void
    {
        [$event] = $this->kegiatanLengkap();
        $this->actingAs($this->operator());
        $this->post('/hasil/'.$event.'/calculate')->assertSessionHasNoErrors();

        $this->get('/kriteria')
            ->assertOk()
            ->assertViewHas('terkunci', fn (?string $alasan) => $alasan !== null && str_contains($alasan, 'hasil perhitungan yang sah'))
            ->assertSee('Bobot sedang terkunci');

        $this->post('/kriteria', ['weights' => [0.05, 0.05, 0.30, 0.20, 0.20, 0.20]])
            ->assertSessionHasErrors('weights');
        $this->assertSame(0, DB::table('moora_weights')->count());

        // Menyimpan ulang satu input menandai run sah menjadi arsip, dan hanya sesudah itu
        // bobot boleh diubah tanpa finalisasi.
        $material = DB::table('event_materials')->where('lakmud_event_id', $event)->first();
        $peserta = DB::table('event_participants')->where('lakmud_event_id', $event)->first();
        $this->post('/input/'.$event.'/pretest', ['material_id' => $material->id, 'rows' => [$peserta->id => ['pretest' => '19']]])->assertSessionHasNoErrors();
        $this->assertSame('stale', DB::table('moora_runs')->where('lakmud_event_id', $event)->latest('id')->value('status'));

        $this->get('/kriteria')->assertOk()->assertViewHas('terkunci', null);
        $this->post('/kriteria', ['weights' => [0.05, 0.05, 0.30, 0.20, 0.20, 0.20]])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('moora_weights')->count());
    }

    public function test_bobot_terkunci_oleh_kegiatan_finalisasi_dan_terbuka_setelah_unlock(): void
    {
        [$event] = $this->kegiatanLengkap();
        $this->actingAs($this->operator());
        $this->post('/hasil/'.$event.'/calculate')->assertSessionHasNoErrors();
        $this->post('/hasil/'.$event.'/finalize')->assertSessionHasNoErrors();

        $this->get('/kriteria')
            ->assertOk()
            ->assertViewHas('terkunci', fn (?string $alasan) => $alasan !== null && str_contains($alasan, 'difinalisasi'));

        $this->post('/kriteria', ['weights' => [0.05, 0.05, 0.30, 0.20, 0.20, 0.20]])
            ->assertSessionHasErrors('weights');

        $this->post('/hasil/'.$event.'/unlock', ['reason' => 'Ubah bobot kriteria'])->assertSessionHasNoErrors();
        $this->post('/kriteria', ['weights' => [0.05, 0.05, 0.30, 0.20, 0.20, 0.20]])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('moora_weights')->count());
    }

    public function test_baca_bobot_tanpa_izin_kelola_tidak_bisa_menyimpan(): void
    {
        $this->seed();
        Permission::findOrCreate('criteria.view', 'web');
        $pembaca = User::factory()->create();
        $pembaca->givePermissionTo('criteria.view');

        $this->actingAs($pembaca)->get('/kriteria')
            ->assertOk()
            ->assertDontSee('Simpan bobot')
            ->assertSee('hanya dapat membaca bobot');
        $this->post('/kriteria', ['weights' => [0.05, 0.05, 0.30, 0.20, 0.20, 0.20]])->assertForbidden();
    }
}
