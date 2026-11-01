<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManagementPagesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_participant_lists_paginate_without_changing_event_totals(): void
    {
        $this->seed();
        $user = User::factory()->create()->assignRole('operator_penilaian');
        $event = DB::table('lakmud_events')->insertGetId(['name' => 'TEST pagination', 'organization' => 'TEST', 'venue' => 'TEST', 'start_date' => '2026-10-01', 'end_date' => '2026-10-02']);
        for ($i = 1; $i <= 12; $i++) {
            $person = DB::table('participants')->insertGetId(['full_name' => sprintf('TEST row %02d', $i)]);
            DB::table('event_participants')->insert(['lakmud_event_id' => $event, 'participant_id' => $person, 'sequence_number' => $i]);
        }
        // Dashboard tidak lagi punya tabel berpaginasi maupun kolom Baris per halaman, jadi
        // assertion per_page hanya berlaku untuk halaman input dan data peserta. Pemilih
        // Kegiatan tetap ada di dashboard dan tidak berpengaruh ke assertion ini.
        foreach (['/peserta', '/absensi', '/pretest', '/posttest', '/keaktifan', '/etika', '/pemahaman'] as $url) {
            $response = $this->actingAs($user)->get($url.'?event_id='.$event);
            $response->assertOk()->assertViewHas('listing', fn ($rows) => count($rows) === 10 && $rows->total() === 12)->assertSee('name="per_page"', false);
            $this->get($url.'?event_id='.$event.'&page=2')->assertViewHas('listing', fn ($rows) => count($rows) === 2 && $rows->firstItem() === 11);
            $this->get($url.'?event_id='.$event.'&q=row+12')->assertViewHas('listing', fn ($rows) => count($rows) === 1 && $rows->total() === 1)->assertViewHas('participants', fn ($rows) => count($rows) === 12);
        }
    }

    public function test_paged_saves_preserve_other_rows_and_calculation_uses_whole_event(): void
    {
        $this->seed();
        $this->actingAs(User::factory()->create()->assignRole('operator_penilaian'));
        $event = DB::table('lakmud_events')->insertGetId(['name' => 'TEST entire event', 'organization' => 'TEST', 'venue' => 'TEST', 'start_date' => '2026-10-01', 'end_date' => '2026-10-02']);
        $ids = [];
        for ($i = 1; $i <= 12; $i++) {
            $person = DB::table('participants')->insertGetId(['full_name' => sprintf('TEST full %02d', $i)]);
            $ids[] = DB::table('event_participants')->insertGetId(['lakmud_event_id' => $event, 'participant_id' => $person, 'sequence_number' => $i]);
        }
        for ($i = 1; $i <= 15; $i++) {
            $material = DB::table('event_materials')->insertGetId(['lakmud_event_id' => $event, 'name' => 'TEST material '.$i, 'sequence' => $i, 'is_active' => true]);
            foreach ($ids as $id) {
                $key = ['event_participant_id' => $id, 'event_material_id' => $material];
                DB::table('attendance_records')->insert($key + ['status' => 'present']);
                foreach (['pretest', 'posttest'] as $type) {
                    DB::table('assessment_scores')->insert($key + ['assessment_type' => $type, 'score' => 18]);
                }
                DB::table('moora_aspect_scores')->insert($key + ['activity' => 18, 'ethics' => 18, 'understanding' => 18]);
            }
        }
        $this->post('/input/'.$event.'/pretest', ['material_id' => $material, '_table' => ['page' => 2, 'per_page' => 10], 'rows' => [$ids[10] => ['pretest' => 19]]])->assertRedirect('/pretest?event_id='.$event.'&material_id='.$material.'&per_page=10&page=2');
        $this->assertDatabaseHas('assessment_scores', ['event_participant_id' => $ids[0], 'event_material_id' => $material, 'assessment_type' => 'pretest', 'score' => 18]);
        $this->get('/dashboard?event_id='.$event.'&q=full+12&page=2')->assertViewHas('complete', true)->assertViewHas('listing', fn ($rows) => $rows->total() === 1);
        $this->post('/hasil/'.$event.'/calculate?q=full+12&page=2')->assertSessionHasNoErrors();
        $run = DB::table('moora_runs')->where('lakmud_event_id', $event)->latest('id')->first();
        $this->assertEquals(12, $run->alternative_count);
        $this->assertEquals(12, DB::table('moora_results')->where('moora_run_id', $run->id)->count());
        $this->get('/hasil?event_id='.$event)->assertViewHas('listing', fn ($rows) => count($rows) === 10 && $rows->total() === 12);
        $this->get('/hasil?event_id='.$event.'&page=2')->assertViewHas('listing', fn ($rows) => count($rows) === 2);
        $this->post('/hasil/'.$event.'/finalize')->assertSessionHasNoErrors();
        foreach (['materi', 'peserta'] as $type) {
            $this->get('/'.$type.'?event_id='.$event)->assertDontSee('data-modal-open', false);
            $this->post('/kegiatan/'.$event.'/'.$type, ['name' => 'TEST blocked', 'sequence' => 99])->assertSessionHasErrors();
        }
    }

    public function test_management_pages_have_separate_tables_and_modal_forms_and_event_rows_only_have_crud_actions(): void
    {
        $this->seed();
        $user = User::factory()->create()->assignRole('operator_penilaian');
        $event = DB::table('lakmud_events')->insertGetId(['name' => 'TEST UI event', 'organization' => 'TEST', 'venue' => 'TEST', 'start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['kegiatan', 'materi', 'peserta'] as $page) {
            $response = $this->actingAs($user)->get('/'.$page.'?event_id='.$event)->assertOk()->assertSee('<table', false)->assertSee('<dialog', false)->assertSee('name="q"', false)->assertSee('data-modal-open', false)->assertDontSee('<details', false);
            if ($page === 'kegiatan') {
                $response->assertSeeInOrder(['TEST UI event', 'Ubah', 'Hapus']);
                $row = explode('</tr>', explode('TEST UI event', $response->getContent())[1])[0];
                $this->assertStringNotContainsString('/materi?event_id=', $row);
                $this->assertStringNotContainsString('/peserta?event_id=', $row);
                $this->assertStringNotContainsString('>Materi<', $row);
                $this->assertStringNotContainsString('>Peserta<', $row);
            }
        }
        $sidebar = $this->get('/kegiatan?event_id='.$event);
        $sidebar->assertSee('href="/materi?event_id='.$event.'"', false)->assertSee('href="/peserta?event_id='.$event.'"', false);
        $this->post('/kegiatan/'.$event.'/materi', ['name' => 'TEST material', 'is_active' => 1])->assertRedirect('/materi?event_id='.$event);
        $this->post('/kegiatan/'.$event.'/peserta', ['name' => 'TEST participant'])->assertRedirect('/peserta?event_id='.$event);
        $this->assertDatabaseHas('event_materials', ['lakmud_event_id' => $event, 'name' => 'TEST material', 'sequence' => 1]);
        $this->assertDatabaseHas('event_participants', ['lakmud_event_id' => $event, 'sequence_number' => 1]);
    }

    public function test_nomor_urut_materi_dan_peserta_diisi_otomatis(): void
    {
        $this->seed();
        $user = User::factory()->create()->assignRole('operator_penilaian');
        $event = DB::table('lakmud_events')->insertGetId(['name' => 'TEST auto', 'organization' => 'TEST', 'venue' => 'TEST', 'start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($user);

        // Materi: nomor mengisi slot kosong terkecil supaya label M1 sampai M15 tetap rapat.
        foreach ([1, 2, 3] as $i) {
            $this->post('/kegiatan/'.$event.'/materi', ['name' => 'Materi '.$i, 'is_active' => 1])->assertSessionHasNoErrors();
        }
        $this->assertSame([1, 2, 3], DB::table('event_materials')->where('lakmud_event_id', $event)->orderBy('sequence')->pluck('sequence')->map(fn ($n) => (int) $n)->all());
        $this->delete('/kegiatan/'.$event.'/materi/'.DB::table('event_materials')->where('lakmud_event_id', $event)->where('sequence', 2)->value('id'))->assertSessionHasNoErrors();
        $this->post('/kegiatan/'.$event.'/materi', ['name' => 'Materi pengganti', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('event_materials', ['lakmud_event_id' => $event, 'name' => 'Materi pengganti', 'sequence' => 2]);

        // Batas 15 materi tetap berlaku walau nomor tidak lagi diisi operator.
        foreach (range(4, 15) as $i) {
            $this->post('/kegiatan/'.$event.'/materi', ['name' => 'Materi '.$i, 'is_active' => 1])->assertSessionHasNoErrors();
        }
        $this->post('/kegiatan/'.$event.'/materi', ['name' => 'Materi ke-16', 'is_active' => 1])->assertSessionHasErrors('panel');
        $this->assertSame(15, DB::table('event_materials')->where('lakmud_event_id', $event)->count());

        // Peserta: nomor melanjutkan dari nomor terbesar, jadi tidak menabrak baris yang ada.
        $this->post('/kegiatan/'.$event.'/peserta', ['name' => 'Peserta satu'])->assertSessionHasNoErrors();
        $this->post('/kegiatan/'.$event.'/peserta', ['name' => 'Peserta dua'])->assertSessionHasNoErrors();
        $this->post('/kegiatan/'.$event.'/peserta', ['name' => 'Peserta tiga'])->assertSessionHasNoErrors();
        $this->delete('/kegiatan/'.$event.'/peserta/'.DB::table('event_participants')->where('lakmud_event_id', $event)->where('sequence_number', 2)->value('id'))->assertSessionHasNoErrors();
        $this->post('/kegiatan/'.$event.'/peserta', ['name' => 'Peserta empat'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('event_participants', ['lakmud_event_id' => $event, 'sequence_number' => 4]);
        $this->assertSame([1, 3, 4], DB::table('event_participants')->where('lakmud_event_id', $event)->orderBy('sequence_number')->pluck('sequence_number')->map(fn ($n) => (int) $n)->all());
    }
}
