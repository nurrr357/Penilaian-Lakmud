<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SpearmanPageTest extends TestCase
{
    use DatabaseTransactions;

    private function operator(): User
    {
        $this->seed();

        return User::where('email', 'operator@example.test')->firstOrFail();
    }

    public function test_guest_is_redirected_and_user_without_results_permission_is_forbidden(): void
    {
        $this->get('/evaluasi-spearman')->assertRedirect('/login');

        $this->seed();

        $this->actingAs(User::factory()->create())
            ->get('/evaluasi-spearman')
            ->assertForbidden();
    }

    public function test_operator_sees_summary_charts_and_table(): void
    {
        $response = $this->actingAs($this->operator())->get('/evaluasi-spearman');

        $response->assertOk()
            ->assertSee('Evaluasi Spearman')
            ->assertSee('Template 5 Aspek vs MOORA 6 Kriteria')
            ->assertSee('702')
            ->assertSee('0,3744')
            ->assertSee('8,82e-25')
            ->assertSee('14,02%')
            ->assertSee('MOORA 6')
            ->assertSee('spearman-scatter.png', false)
            ->assertSee('spearman-histogram.png', false)
            ->assertSee('77,666667')
            ->assertSee('0,03278270')
            ->assertSee('<th scope="col">No</th><th scope="col">Metode</th><th scope="col">Jumlah</th><th scope="col">Keterangan</th>', false)
            ->assertSee('<th scope="col">No LAKMUD</th><th scope="col">Nama</th><th scope="col">No MOORA</th><th scope="col">Metode</th>', false)
            ->assertDontSee('No HP')
            ->assertDontSee('85730047715')
            ->assertViewHas('listing', fn ($rows) => count($rows) === 10 && $rows->total() === 702)
            ->assertViewHas('event', fn ($event) => $event === null);
    }

    public function test_search_filters_by_name_and_formats_tied_ranks(): void
    {
        $this->actingAs($this->operator());

        $this->get('/evaluasi-spearman?q='.urlencode("A'IMATUL"))
            ->assertOk()
            ->assertSee("A'IMATUL MUKHOFININGTIAS")
            ->assertDontSee('ZUMROTUL')
            ->assertViewHas('listing', fn ($rows) => count($rows) === 1 && $rows->total() === 1);

        $this->get('/evaluasi-spearman?q='.urlencode('853/LAKMUD/35.07/74.55/III/2023'))
            ->assertOk()
            ->assertSee('ADE CINTA OKTAVIANI')
            ->assertDontSee('ZUMROTUL')
            ->assertViewHas('listing', fn ($rows) => count($rows) === 1 && $rows->total() === 1);

        $this->get('/evaluasi-spearman?q=ZUMROTUL')
            ->assertOk()
            ->assertSee('ZUMROTUL KURNIA')
            ->assertSee('463,5');
    }

    public function test_per_page_accepts_only_supported_sizes_and_page_is_clamped(): void
    {
        $this->actingAs($this->operator());

        $this->get('/evaluasi-spearman?per_page=25')
            ->assertOk()
            ->assertViewHas('listing', fn ($rows) => $rows->perPage() === 25 && $rows->total() === 702 && count($rows) === 25);

        $this->get('/evaluasi-spearman?per_page=99&page=9999')
            ->assertOk()
            ->assertViewHas('listing', fn ($rows) => $rows->perPage() === 10 && $rows->currentPage() === 71 && count($rows) === 2);
    }

    public function test_menu_places_spearman_after_hasil_perangkingan(): void
    {
        $response = $this->actingAs($this->operator())->get('/evaluasi-spearman');

        $response->assertOk()
            ->assertSeeInOrder(['Hasil Perangkingan', 'Evaluasi Spearman'])
            ->assertSee('href="/evaluasi-spearman"', false);
    }
}
