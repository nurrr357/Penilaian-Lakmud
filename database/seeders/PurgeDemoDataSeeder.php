<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Hapus data demo lama sebelum mengisi data kegiatan nyata.
 *
 * Yang dihapus (sesuai docs/plan-seed-data-lakmud.md §5):
 *  1. kegiatan demo 'Demo verifikasi MOORA' beserta seluruh turunannya
 *     (materi, pendaftaran peserta, absensi, nilai, snapshot MOORA) — lewat cascade FK;
 *  2. peserta yang tidak lagi terdaftar di kegiatan mana pun (yatim).
 *
 * Daftar target dicetak lebih dulu, penghapusan berjalan dalam satu transaksi.
 * Jalankan setelah backup: php artisan db:seed --class=PurgeDemoDataSeeder
 */
class PurgeDemoDataSeeder extends Seeder
{
    private const DEMO_EVENT = 'Demo verifikasi MOORA';

    public function run(): void
    {
        $events = DB::table('lakmud_events')->where('name', self::DEMO_EVENT)->get(['id', 'name', 'organization']);

        if ($events->isEmpty()) {
            $this->command?->info('Tidak ada kegiatan demo dengan nama '.self::DEMO_EVENT.'.');
        }

        foreach ($events as $event) {
            $this->command?->info(sprintf(
                'Target: kegiatan #%d "%s" (%s) — materi %d, pendaftaran %d.',
                $event->id,
                $event->name,
                $event->organization,
                DB::table('event_materials')->where('lakmud_event_id', $event->id)->count(),
                DB::table('event_participants')->where('lakmud_event_id', $event->id)->count(),
            ));
        }

        $orphans = DB::table('participants')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('event_participants')
                ->whereColumn('event_participants.participant_id', 'participants.id'))
            ->get(['id', 'full_name']);

        foreach ($orphans as $participant) {
            $this->command?->info(sprintf('Target: peserta yatim #%d "%s".', $participant->id, $participant->full_name));
        }

        $before = $this->counts();

        DB::transaction(function () use ($events, $orphans) {
            foreach ($events as $event) {
                DB::table('lakmud_events')->where('id', $event->id)->delete();
            }
            if ($orphans->isNotEmpty()) {
                DB::table('participants')->whereIn('id', $orphans->pluck('id')->all())->delete();
            }
        });

        $after = $this->counts();

        $this->command?->info('Dihapus: '.($before['lakmud_events'] - $after['lakmud_events']).' kegiatan, '
            .($before['participants'] - $after['participants']).' peserta, '
            .($before['moora_runs'] - $after['moora_runs']).' snapshot MOORA.');
        $this->command?->table(
            ['tabel', 'sebelum', 'sesudah'],
            collect($before)->map(fn ($value, $table) => [$table, $value, $after[$table]])->values()->all(),
        );
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $tables = ['lakmud_events', 'event_materials', 'participants', 'event_participants', 'attendance_records', 'assessment_scores', 'moora_aspect_scores', 'moora_runs', 'moora_results'];

        return collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    }
}
