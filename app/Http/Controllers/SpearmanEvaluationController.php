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
        $invalid = 'Data evaluasi Spearman tidak valid. Periksa struktur database/data/spearman.json.';

        if (! is_array($data) || ! isset($data['meta'], $data['rows']) || ! is_array($data['meta']) || ! is_array($data['rows'])) {
            abort(500, $invalid);
        }

        $requiredArrays = ['method', 'sources', 'match_audit', 'repeated_matches', 'conclusion'];
        foreach ($requiredArrays as $key) {
            if (! isset($data[$key]) || ! is_array($data[$key])) {
                abort(500, $invalid);
            }
        }

        $metaKeys = array_flip(['n', 'rho', 'p_value', 'ci_low', 'ci_high', 'rho_squared']);
        $rowKeys = array_flip(['no', 'name', 'certificate', 'template_value', 'rank_template', 'yi_moora', 'rank_moora', 'd', 'd2', 'match_method']);
        $sectionKeys = [
            'method' => array_flip(['term', 'description']),
            'sources' => array_flip(['workbook', 'sheet', 'description', 'count']),
            'match_audit' => array_flip(['method', 'count']),
            'repeated_matches' => array_flip(['no_lakmud', 'name', 'no_moora', 'method']),
        ];

        if (array_diff_key($metaKeys, $data['meta']) !== []) {
            abort(500, $invalid);
        }

        foreach ($data['rows'] as $row) {
            if (! is_array($row) || array_diff_key($rowKeys, $row) !== []) {
                abort(500, $invalid);
            }
        }

        foreach ($sectionKeys as $section => $keys) {
            foreach ($data[$section] as $item) {
                if (! is_array($item) || array_diff_key($keys, $item) !== []) {
                    abort(500, $invalid);
                }
            }
        }

        foreach ($data['conclusion'] as $paragraph) {
            if (! is_string($paragraph)) {
                abort(500, $invalid);
            }
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
