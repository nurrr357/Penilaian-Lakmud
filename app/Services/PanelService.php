<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PanelService
{
    public const FIELDS = ['attendance', 'pretest', 'activity', 'ethics', 'understanding', 'posttest'];

    public function fail(string $message): never
    {
        throw ValidationException::withMessages(['panel' => $message]);
    }

    public function mutate(int $id, callable $callback, string $action): mixed
    {
        return DB::transaction(function () use ($id, $callback, $action) {
            $event = DB::table('lakmud_events')->where('id', $id)->lockForUpdate()->first();
            abort_unless($event, 404);
            if ($event->finalized) {
                $this->fail('Kegiatan dikunci. Buka kunci sebelum mengubah data.');
            }
            $result = $callback($event);
            if ($action !== 'calculate') {
                $this->stale($id);
            }
            activity()->causedBy(auth()->user())->withProperties(['lakmud_event_id' => $id, 'payload' => request()->except(['_token', 'password', 'password_confirmation', 'current_password', 'remember_token'])])->log($action);

            return $result;
        });
    }

    public function stale(int $id): void
    {
        DB::table('moora_runs')->where('lakmud_event_id', $id)->whereIn('status', ['valid', 'locked'])->update(['status' => 'stale', 'updated_at' => now()]);
    }

    public function context(?int $id): array
    {
        $events = DB::table('lakmud_events')->orderByDesc('id')->get();
        $event = $id ? $events->firstWhere('id', $id) : $events->first();
        if ($id) {
            abort_unless($event, 404);
        }
        $materials = DB::table('event_materials')->where('lakmud_event_id', $event?->id ?? 0)->orderBy('sequence')->get();
        $participants = DB::table('event_participants as ep')->join('participants as p', 'p.id', '=', 'ep.participant_id')->where('ep.lakmud_event_id', $event?->id ?? 0)->orderBy('sequence_number')->select('ep.*', 'ep.sequence_number as sequence', 'p.full_name as name')->get();
        $inputs = collect();
        foreach (['attendance_records', 'assessment_scores', 'moora_aspect_scores'] as $table) {
            foreach (DB::table($table)->whereIn('event_participant_id', $participants->pluck('id'))->get() as $record) {
                $key = $record->event_participant_id.':'.$record->event_material_id;
                $row = $inputs->get($key) ?? (object) array_fill_keys(self::FIELDS, null);
                if ($table === 'attendance_records') {
                    $row->attendance = $record->status;
                } elseif ($table === 'assessment_scores' && in_array($record->assessment_type, ['pretest', 'posttest'], true)) {
                    $row->{$record->assessment_type} = $record->score;
                } elseif ($table === 'moora_aspect_scores') {
                    foreach (['activity', 'ethics', 'understanding'] as $field) {
                        $row->$field = $record->$field;
                    }
                }
                $inputs->put($key, $row);
            }
        }
        $active = $materials->where('is_active', true);
        $completion = [];
        $complete = $active->count() === 15 && $participants->isNotEmpty();
        foreach ($participants as $participant) {
            $count = 0;
            $missing = [];
            foreach ($active as $material) {
                $row = $inputs->get($participant->id.':'.$material->id);
                $unfilled = [];
                foreach (self::FIELDS as $field) {
                    if (($row?->$field) !== null) {
                        $count++;
                    } else {
                        $unfilled[] = $field;
                    }
                }
                if ($unfilled) {
                    $missing[] = $material->name.': '.implode(', ', $unfilled);
                }
            }
            $completion[$participant->id] = ['count' => $count, 'missing' => $missing];
            if ($count !== 90) {
                $complete = false;
            }
        }
        $run = DB::table('moora_runs')->where('lakmud_event_id', $event?->id ?? 0)->latest('id')->first();
        $results = DB::table('moora_results as r')->join('event_participants as ep', 'ep.id', '=', 'r.event_participant_id')->join('participants as p', 'p.id', '=', 'ep.participant_id')->where('moora_run_id', $run?->id ?? 0)->orderBy('rank')->orderBy('event_participant_id')->select('r.*', 'p.full_name as participant_name')->get();

        return compact('events', 'event', 'materials', 'active', 'participants', 'inputs', 'completion', 'complete', 'run', 'results');
    }

    public function calculate(int $id): void
    {
        $this->mutate($id, function ($event) use ($id) {
            $data = $this->context($id);
            if (! $data['complete']) {
                $this->fail('Lengkapi tepat 15 materi aktif dan 90 input setiap peserta sebelum menghitung.');
            }
            $matrix = [];
            $certificates = [];
            foreach ($data['participants'] as $participant) {
                $criteria = array_fill(0, 6, 0);
                $components = [];
                foreach ($data['active'] as $material) {
                    $row = $data['inputs']->get($participant->id.':'.$material->id);
                    if (! in_array($row->attendance, ['present', 'absent'], true)) {
                        $this->fail('Absensi tidak valid.');
                    }
                    $values = [$row->pretest, $row->activity, $row->ethics, $row->understanding, $row->posttest];
                    $criteria[0] += $row->attendance === 'absent' ? 1 : 0;
                    foreach ($values as $index => $value) {
                        $criteria[$index + 1] += (float) $value;
                    }
                    $components[] = $values;
                }
                $matrix[$participant->id] = $criteria;
                $certificates[$participant->id] = (new CertificateScoreCalculator)->calculate($components);
            }
            $bobot = (new MooraWeights)->current();
            $calculated = (new MooraCalculator)->calculate($matrix, $bobot);
            $this->stale($id);
            $runId = DB::table('moora_runs')->insertGetId(['lakmud_event_id' => $id, 'status' => 'valid', 'formula_version' => 'moora-v1', 'weights' => json_encode($bobot), 'divisors' => json_encode($calculated['divisors']), 'alternative_count' => count($matrix), 'calculated_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
            foreach ($data['participants'] as $participant) {
                $row = $calculated['rows'][$participant->id];
                $certificate = $certificates[$participant->id];
                foreach (['criteria', 'normalized', 'weighted'] as $key) {
                    $row[$key] = json_encode($row[$key]);
                }
                DB::table('moora_results')->insert($row + ['moora_run_id' => $runId, 'event_participant_id' => $participant->id, 'created_at' => now(), 'updated_at' => now(), 'material_scores' => json_encode($certificate['materials']), 'certificate_score' => $certificate['score'], 'certificate_predicate' => $certificate['predicate'], 'certificate_status' => $certificate['status']]);
            }

            return $runId;
        }, 'calculate');
    }

    public function transition(int $id, string $action): void
    {
        DB::transaction(function () use ($id, $action) {
            $event = DB::table('lakmud_events')->where('id', $id)->lockForUpdate()->first();
            abort_unless($event, 404);
            $run = DB::table('moora_runs')->where('lakmud_event_id', $id)->latest('id')->first();
            if ($action === 'finalize') {
                if ($event->finalized && $run?->status === 'locked') {
                    return;
                }
                if (! $run || $run->status !== 'valid' || ! $this->context($id)['complete']) {
                    $this->fail('Hitung ulang hasil yang lengkap sebelum finalisasi.');
                }
                DB::table('moora_runs')->where('id', $run->id)->update(['status' => 'locked', 'locked_at' => now(), 'updated_at' => now()]);
                DB::table('lakmud_events')->where('id', $id)->update(['finalized' => true, 'updated_at' => now()]);
            } else {
                if (! $event->finalized) {
                    $this->fail('Kegiatan belum dikunci.');
                }
                DB::table('lakmud_events')->where('id', $id)->update(['finalized' => false, 'updated_at' => now()]);
                $this->stale($id);
            }
            activity()->causedBy(auth()->user())->withProperties(['lakmud_event_id' => $id, 'reason' => request('reason')])->log($action);
        });
    }
}
