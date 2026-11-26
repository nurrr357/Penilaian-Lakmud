<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class SpearmanEvaluationController extends Controller
{
    /**
     * Halaman evaluasi Spearman memakai berkas data statis, bukan tabel database.
     * Pencarian dan paginasi dilakukan di memori mengikuti pola PanelController
     * supaya kontrol tabel yang sama tetap berperilaku sama.
     */
    public function __invoke(Request $request): View
    {
        $path = database_path('data/spearman.json');

        if (! is_file($path)) {
            abort(500, 'Data evaluasi Spearman tidak ditemukan di database/data/spearman.json.');
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ! isset($data['meta'], $data['rows']) || ! is_array($data['meta']) || ! is_array($data['rows'])) {
            abort(500, 'Data evaluasi Spearman tidak valid. Periksa struktur database/data/spearman.json.');
        }

        $q = mb_substr(trim($request->string('q')->toString()), 0, 200);
        $rows = collect($data['rows'])->filter(fn (array $row): bool => $q === ''
            || mb_stripos((string) ($row['name'] ?? ''), $q) !== false
            || mb_stripos((string) ($row['certificate'] ?? ''), $q) !== false)->values();
        $perPage = in_array($request->integer('per_page'), [10, 25, 50], true) ? $request->integer('per_page') : 10;
        $number = min(max(1, $request->integer('page', 1)), max(1, (int) ceil($rows->count() / $perPage)));
        $listing = new LengthAwarePaginator(
            $rows->forPage($number, $perPage)->values(),
            $rows->count(),
            $perPage,
            $number,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('spearman', [
            'title' => 'Evaluasi Spearman',
            'data' => $data,
            'listing' => $listing,
            'q' => $q,
            'event' => null,
        ]);
    }
}
