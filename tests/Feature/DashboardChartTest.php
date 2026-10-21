<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardChartTest extends TestCase
{
    use DatabaseTransactions;

    /** Kegiatan lengkap: 2 peserta, 15 materi, seluruh input terisi (pola yang sama dengan
     *  ManagementPagesTest::test_paged_saves... supaya jalur hitungnya benar-benar terpakai). */
    private function kegiatanLengkap(): array
    {
        $this->seed();
        $event = DB::table('lakmud_events')->insertGetId(['name' => 'TEST dashboard', 'organization' => 'TEST', 'venue' => 'TEST', 'start_date' => '2026-10-01', 'end_date' => '2026-10-02']);
        $peserta = [];
        foreach ([1, 2] as $i) {
            $person = DB::table('participants')->insertGetId(['full_name' => sprintf('TEST grafik %02d', $i)]);
            $peserta[] = DB::table('event_participants')->insertGetId(['lakmud_event_id' => $event, 'participant_id' => $person, 'sequence_number' => $i]);
        }
        for ($i = 1; $i <= 15; $i++) {
            $material = DB::table('event_materials')->insertGetId(['lakmud_event_id' => $event, 'name' => 'TEST material '.$i, 'sequence' => $i, 'is_active' => true]);
            foreach ($peserta as $id) {
                $key = ['event_participant_id' => $id, 'event_material_id' => $material];
                DB::table('attendance_records')->insert($key + ['status' => 'present']);
                DB::table('assessment_scores')->insert($key + ['assessment_type' => 'pretest', 'score' => 18]);
                DB::table('assessment_scores')->insert($key + ['assessment_type' => 'posttest', 'score' => 18]);
                DB::table('moora_aspect_scores')->insert($key + ['activity' => 18, 'ethics' => 18, 'understanding' => 18]);
            }
        }

        return [$event, $peserta];
    }

    private function operator(): User
    {
        $this->seed();

        return User::where('email', 'operator@example.test')->firstOrFail();
    }

    public function test_dashboard_menampilkan_kartu_dan_grafik(): void
    {
        [$event] = $this->kegiatanLengkap();
        $this->actingAs($this->operator());
        $this->post('/hasil/'.$event.'/calculate')->assertSessionHasNoErrors();

        $this->get('/?event_id='.$event)
            ->assertOk()
            ->assertSee('data-chart', false)
            ->assertViewHas('dashboard', fn (array $d) => $d['participants'] === 2
                && $d['materials'] === 15
                && $d['materials_active'] === 15
                && $d['results'] === 2
                // Fixture ini adalah satu-satunya kegiatan di dalam transaksi test, jadi angka
                // kartu Kegiatan bisa dipatok tepat 1. (Terverifikasi lewat probe: isi tabel
                // lakmud_events saat test berjalan hanya berisi kegiatan fixture.)
                && $d['events'] === 1
                && count($d['aspect']['values']) === 6
                && $d['aspect']['chart']['type'] === 'bar'
                && $d['passed']['chart']['type'] === 'donut'
                && array_sum(array_column($d['passed']['rows'], 'jumlah')) === 2);
    }

    public function test_dashboard_menampilkan_keadaan_kosong_sebelum_dihitung(): void
    {
        [$event] = $this->kegiatanLengkap();
        $this->actingAs($this->operator());

        $this->get('/?event_id='.$event)
            ->assertOk()
            ->assertSee('Grafik belum tersedia')
            ->assertDontSee('data-chart', false)
            ->assertViewHas('dashboard', fn (array $d) => $d['results'] === 0);
    }

    public function test_dashboard_tidak_lagi_memuat_kelengkapan_dan_kolom_pencarian(): void
    {
        $this->actingAs($this->operator());

        $this->get('/')
            ->assertOk()
            ->assertDontSee('Lihat input kosong')
            ->assertDontSee('Yang perlu diisi')
            ->assertDontSee('Cari nama')
            ->assertDontSee('name="per_page"', false)
            ->assertDontSee('dashboard-detail-modal');
    }

    public function test_dashboard_memfilter_berdasarkan_kegiatan_dengan_default_terbaru(): void
    {
        [$lama] = $this->kegiatanLengkap();
        $baru = DB::table('lakmud_events')->insertGetId(['name' => 'TEST dashboard terbaru', 'organization' => 'TEST', 'venue' => 'TEST', 'start_date' => '2026-10-01', 'end_date' => '2026-10-02']);
        $this->actingAs($this->operator());

        // Tanpa event_id: kegiatan terbaru dipakai, dan pemilihnya tetap dirender.
        $this->get('/')
            ->assertOk()
            ->assertSee('name="event_id"', false)
            ->assertViewHas('event', fn ($e) => $e->id === $baru)
            ->assertViewHas('dashboard', fn (array $d) => $d['materials'] === 0 && $d['results'] === 0);

        // Dengan event_id: kartu dan grafik mengikuti kegiatan yang dipilih.
        $this->get('/?event_id='.$lama)
            ->assertOk()
            ->assertViewHas('event', fn ($e) => $e->id === $lama)
            ->assertViewHas('dashboard', fn (array $d) => $d['materials'] === 15 && $d['participants'] === 2);
    }
}
