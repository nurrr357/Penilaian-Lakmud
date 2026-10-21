<?php

namespace App\Http\Controllers;

use App\Http\Requests\InputRequest;
use App\Services\PanelService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PanelController extends Controller
{
    public function __construct(private PanelService $panel) {}

    public function page(Request $request)
    {
        $page = $request->route()->defaults['page'] ?? 'dashboard';
        $data = $this->panel->context($request->integer('event_id') ?: null);
        $material = $data['active']->firstWhere('id', $request->integer('material_id')) ?? $data['active']->first();

        $source = match ($page) {
            'kegiatan' => $data['events'],
            'materi' => $data['materials'],
            'perhitungan', 'hasil' => $data['results'],
            default => $data['participants'],
        };
        $q = mb_substr(trim($request->string('q')->toString()), 0, 200);
        $filtered = $source->filter(fn ($row) => $q === '' || mb_stripos($row->name ?? $row->participant_name ?? '', $q) !== false)->values();
        $perPage = in_array($request->integer('per_page'), [10, 25, 50], true) ? $request->integer('per_page') : 10;
        $number = min(max(1, $request->integer('page', 1)), max(1, (int) ceil($filtered->count() / $perPage)));
        $listing = new LengthAwarePaginator($filtered->forPage($number, $perPage)->values(), $filtered->count(), $perPage, $number, ['path' => $request->url(), 'query' => $request->query()]);

        return view('panel', $data + compact('page', 'material', 'listing', 'q') + [
            // Dashboard saja yang perlu angka ini. Halaman lain tetap seperti semula, termasuk
            // blok listing yang tetap dibangun karena dipakai halaman absensi sampai hasil.
            'dashboard' => $page === 'dashboard' ? $this->dashboard($data) : [],
        ]);
    }

    private function tableQuery(Request $request): array
    {
        return array_filter([
            'q' => mb_substr($request->string('_table.q')->toString(), 0, 200),
            'per_page' => in_array($request->integer('_table.per_page'), [10, 25, 50], true) ? $request->integer('_table.per_page') : null,
            'page' => $request->filled('_table.page') ? max(1, $request->integer('_table.page')) : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function event(Request $request, ?int $event = null)
    {
        if ($request->isMethod('delete')) {
            $this->panel->mutate($event, fn () => DB::table('lakmud_events')->where('id', $event)->delete(), 'event.deleted');

            return redirect('/kegiatan')->with('success', 'Kegiatan dihapus.');
        }
        $data = $request->validate(['name' => 'required|string|max:200', 'organization' => 'required|string|max:200', 'venue' => 'required|string|max:200', 'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date']);
        $data['updated_at'] = now();
        if ($event) {
            $this->panel->mutate($event, fn () => DB::table('lakmud_events')->where('id', $event)->update($data), 'event.updated');
        } else {
            $event = DB::transaction(function () use ($data) {
                $id = DB::table('lakmud_events')->insertGetId($data + ['created_at' => now()]);
                activity()->causedBy(auth()->user())->withProperties(['event_id' => $id, 'data' => $data])->log('event.created');

                return $id;
            });
        }

        return redirect('/kegiatan?'.http_build_query(['event_id' => $event] + $this->tableQuery($request)))->with('success', 'Kegiatan disimpan.');
    }

    public function child(Request $request, int $event, string $type, ?int $record = null)
    {
        abort_unless($request->user()->can($type === 'materi' ? 'materials.manage' : 'participants.manage'), 403);
        $table = match ($type) {
            'materi' => 'event_materials', 'peserta' => 'event_participants', default => abort(404)
        };
        $sequence = $type === 'materi' ? 'sequence' : 'sequence_number';
        $data = [];
        if (! $request->isMethod('delete')) {
            $data = $request->validate(['name' => 'required|string|max:200', 'sequence' => ['required', 'integer', 'min:1', $type === 'materi' ? 'max:15' : 'max:99999', Rule::unique($table, $sequence)->where('lakmud_event_id', $event)->ignore($record)], 'is_active' => 'sometimes|boolean']);
            if ($type === 'materi') {
                $data['is_active'] = $request->boolean('is_active');
            } else {
                unset($data['is_active']);
            }
        }
        $this->panel->mutate($event, function () use ($request, $event, $record, $table, $data) {
            $query = DB::table($table)->where('lakmud_event_id', $event)->where('id', $record);
            if ($record) {
                abort_unless($query->exists(), 404);
            }
            if ($table === 'event_participants' && ! $request->isMethod('delete')) {
                $person = $record ? $query->value('participant_id') : DB::table('participants')->insertGetId(['full_name' => $data['name'], 'created_at' => now(), 'updated_at' => now()]);
                DB::table('participants')->where('id', $person)->update(['full_name' => $data['name'], 'updated_at' => now()]);
                $data['participant_id'] = $person;
                $data['sequence_number'] = $data['sequence'];
                unset($data['name'], $data['sequence']);
            }
            if ($request->isMethod('delete')) {
                $query->delete();
            } elseif ($record) {
                $query->update($data + ['updated_at' => now()]);
            } else {
                DB::table($table)->insert($data + ['lakmud_event_id' => $event, 'created_at' => now(), 'updated_at' => now()]);
            }
        }, $table.'.'.$request->method());

        return redirect('/'.$type.'?'.http_build_query(['event_id' => $event] + $this->tableQuery($request)))->with('success', 'Data disimpan.');
    }

    public function input(InputRequest $request, int $event, string $kind)
    {
        $data = $request->validated();
        $this->panel->mutate($event, function () use ($event, $data) {
            if (! DB::table('event_materials')->where('lakmud_event_id', $event)->where('id', $data['material_id'])->where('is_active', true)->exists()) {
                $this->panel->fail('Materi tidak lagi aktif. Muat ulang halaman.');
            }
            $ids = DB::table('event_participants')->where('lakmud_event_id', $event)->pluck('id')->all();
            foreach ($data['rows'] as $id => $values) {
                if (! ctype_digit((string) $id) || ! in_array((int) $id, $ids, true)) {
                    $this->panel->fail('Peserta bukan anggota kegiatan ini.');
                }
                $key = ['event_participant_id' => $id, 'event_material_id' => $data['material_id']];
                foreach ($values as $field => $value) {
                    if ($field === 'attendance') {
                        $query = DB::table('attendance_records')->where($key);
                        if ($value === null) {
                            $query->delete();
                        } else {
                            DB::table('attendance_records')->updateOrInsert($key, ['status' => $value, 'marked_by' => auth()->id(), 'marked_at' => now(), 'updated_at' => now(), 'created_at' => $query->value('created_at') ?? now()]);
                        }
                    } elseif (in_array($field, ['pretest', 'posttest'], true)) {
                        $scoreKey = $key + ['assessment_type' => $field];
                        $query = DB::table('assessment_scores')->where($scoreKey);
                        if ($value === null) {
                            $query->delete();
                        } else {
                            DB::table('assessment_scores')->updateOrInsert($scoreKey, ['score' => $value, 'scored_by' => auth()->id(), 'scored_at' => now(), 'updated_at' => now(), 'created_at' => $query->value('created_at') ?? now()]);
                        }
                    } else {
                        DB::table('moora_aspect_scores')->updateOrInsert($key, [$field => $value, 'scored_by' => auth()->id(), 'updated_at' => now(), 'created_at' => DB::table('moora_aspect_scores')->where($key)->value('created_at') ?? now()]);
                    }
                }
            }
        }, 'inputs.'.$kind);

        return redirect('/'.$kind.'?'.http_build_query(['event_id' => $event, 'material_id' => $data['material_id']] + $this->tableQuery($request)))->with('success', 'Penilaian disimpan.');
    }

    public function result(Request $request, int $event, string $action)
    {
        abort_unless($request->user()->can('results.'.$action), 403);
        abort_unless(in_array($action, ['calculate', 'finalize', 'unlock']), 404);
        if ($action === 'unlock') {
            $request->validate(['reason' => 'required|string|min:5|max:500']);
        }
        if ($action === 'calculate') {
            $this->panel->calculate($event);
        } else {
            $this->panel->transition($event, $action);
        }

        return redirect('/hasil?event_id='.$event)->with('success', 'Status hasil diperbarui.');
    }

    /**
     * Angka dashboard: tiga kartu metrik, satu batang rata-rata normalisasi 6 aspek, satu donut
     * lulus/tidak lulus. Semuanya dari data yang sudah dimuat PanelService::context() untuk
     * kegiatan terpilih, tanpa query baru, dan sengaja memakai snapshot (normalized,
     * certificate_status) supaya angkanya tidak mungkin menyimpang dari tabel Hasil Perangkingan.
     */
    private function dashboard(array $data): array
    {
        $aspek = ['Absensi', 'Pretest', 'Keaktifan', 'Etika', 'Pemahaman', 'Posttest'];
        $hasil = $data['results'];
        $jumlah = $hasil->count();

        $total = array_fill(0, 6, 0.0);
        foreach ($hasil as $baris) {
            $normal = json_decode($baris->normalized, true) ?: [];
            foreach (range(0, 5) as $j) {
                $total[$j] += (float) ($normal[$j] ?? 0);
            }
        }

        $nilai = array_map(fn (float $t) => $jumlah > 0 ? round($t / $jumlah, 4) : 0.0, $total);
        $lulus = $hasil->where('certificate_status', 'LULUS')->count();
        $gagal = $hasil->where('certificate_status', 'TIDAK LULUS')->count();

        $barisAspek = [];
        $ringkasAspek = [];
        foreach ($aspek as $i => $nama) {
            $teks = number_format($nilai[$i], 4, ',', '.');
            $barisAspek[] = ['nama' => $nama, 'nilai' => $teks];
            $ringkasAspek[] = $nama.' '.$teks;
        }

        $persen = fn (int $bagian) => $jumlah > 0 ? round($bagian / $jumlah * 100, 1) : 0.0;

        return [
            'participants' => $data['participants']->count(),
            'materials' => $data['materials']->count(),
            'materials_active' => $data['active']->count(),
            'events' => $data['events']->count(),
            'results' => $jumlah,
            'status_class' => match ($data['run']?->status) {
                'locked' => 'finalized',
                'valid' => 'active',
                default => 'draft',
            },
            'aspect' => [
                'labels' => $aspek,
                'values' => $nilai,
                'rows' => $barisAspek,
                'summary' => 'Rata-rata nilai normalisasi 6 aspek: '.implode('; ', $ringkasAspek)
                    .'. Skala 0 sampai 1, semakin kecil semakin dekat ke nilai terbaik antar peserta.',
                'chart' => [
                    'type' => 'bar',
                    'data' => [
                        'labels' => $aspek,
                        'datasets' => [['name' => 'Rata-rata N', 'values' => $nilai]],
                    ],
                    'colors' => ['--lakmud-primary'],
                    'height' => 260,
                ],
            ],
            'passed' => [
                'rows' => [
                    ['nama' => 'Lulus', 'jumlah' => $lulus, 'persen' => number_format($persen($lulus), 1, ',', '.'), 'swatch' => 'ok'],
                    ['nama' => 'Tidak lulus', 'jumlah' => $gagal, 'persen' => number_format($persen($gagal), 1, ',', '.'), 'swatch' => 'no'],
                ],
                'summary' => 'Dari '.$jumlah.' peserta: '.$lulus.' lulus ('.$persen($lulus).' persen), '
                    .$gagal.' tidak lulus ('.$persen($gagal).' persen). Predikat A, B, C lulus.',
                'chart' => [
                    'type' => 'donut',
                    'data' => [
                        'labels' => ['Lulus', 'Tidak lulus'],
                        'datasets' => [['values' => [$lulus, $gagal]]],
                    ],
                    'colors' => ['--lakmud-success', '--lakmud-error'],
                    'height' => 300,
                    'strokeWidth' => 28,
                ],
            ],
        ];
    }
}
