<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Isi satu kegiatan dari hasil konversi Excel (python_instruct/excel_to_seed_csv.py).
 *
 * Sumber  : storage/app/seed/peserta.csv, storage/app/seed/penilaian.csv (gitignored, unggah manual)
 * Cakupan : kegiatan, 15 materi, 702 peserta + pendaftaran, 6 aspek per peserta per materi.
 * Tidak diisi: moora_runs dan moora_results — hasil perhitungan dijalankan lewat tombol Hitung MOORA.
 *
 * Aman dijalankan ulang: semua penulisan memakai upsert pada kunci unik komposit
 * (kegiatan+nama, event+sequence, event+sequence_number, participant+material, +assessment_type).
 *
 * Jalankan: php artisan db:seed --class=LakmudDataSeeder
 */
class LakmudDataSeeder extends Seeder
{
    private const EVENT = [
        'name' => 'Lakmud',
        'organization' => 'IpnuIppnu PC Kabupaten Malang',
        'venue' => 'IpnuIppnu PC Kabupaten Malang', // mengikuti kolom sistem; nama tempat sebenarnya menyusul
        'start_date' => '2026-10-21',
        'end_date' => '2026-10-25',
    ];

    private const EXPECTED_PESERTA = 702;

    private const EXPECTED_MATERI = 15;

    private const CHUNK = 1000;

    public function run(): void
    {
        $peserta = $this->bacaCsv(storage_path('app/seed/peserta.csv'));
        $penilaian = $this->bacaCsv(storage_path('app/seed/penilaian.csv'));

        $this->validasi($peserta, $penilaian);

        $mulai = microtime(true);
        $eventId = DB::transaction(function () use ($peserta, $penilaian) {
            $eventId = $this->siapkanKegiatan();
            $materiId = $this->siapkanMateri($eventId, $penilaian);
            $pesertaId = $this->siapkanPeserta($eventId, $peserta);
            $daftarId = $this->siapkanPendaftaran($eventId, $peserta, $pesertaId);
            $this->isiNilai($daftarId, $materiId, $penilaian);

            return $eventId;
        });

        $this->laporkan($eventId, microtime(true) - $mulai);
    }

    /** @return array<int, array<int, string>> */
    private function bacaCsv(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Berkas seed tidak ditemukan: {$path}. Jalankan python_instruct/excel_to_seed_csv.py lebih dulu.");
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException("Berkas seed tidak dapat dibaca: {$path}");
        }
        $header = fgetcsv($handle);
        $rows = [];
        $line = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $line++;
            if ($row === [null] || $row === []) {
                continue;
            }
            if (count($row) !== count($header)) {
                throw new RuntimeException("Baris {$line} pada {$path} punya ".count($row).' kolom, seharusnya '.count($header).'.');
            }
            $rows[] = array_combine($header, $row);
        }
        fclose($handle);

        return $rows;
    }

    private function validasi(array $peserta, array $penilaian): void
    {
        if (count($peserta) !== self::EXPECTED_PESERTA) {
            throw new RuntimeException('peserta.csv berisi '.count($peserta).', seharusnya '.self::EXPECTED_PESERTA.'.');
        }
        $materiUrut = [];
        $perPeserta = [];
        foreach ($penilaian as $index => $row) {
            $materiUrut[(int) $row['materi_no']] = $row['materi_nama'];
            $perPeserta[(int) $row['no']][(int) $row['materi_no']] = true;

            if ((int) $row['aspek1'] !== (int) (float) $row['aspek1'] || ! in_array((float) $row['aspek1'], [0.0, 1.0], true)) {
                throw new RuntimeException('aspek1 bukan 0/1 pada baris '.($index + 1).'.');
            }
            foreach (['aspek2', 'aspek3', 'aspek4', 'aspek5', 'aspek6'] as $kolom) {
                $nilai = (float) $row[$kolom];
                if ($nilai < 10 || $nilai > 20 || round($nilai, 2) !== $nilai) {
                    throw new RuntimeException("{$kolom} di luar 10..20 atau lebih dari dua desimal pada baris ".($index + 1).'.');
                }
            }
        }
        if (count($materiUrut) !== self::EXPECTED_MATERI) {
            throw new RuntimeException('Jumlah materi '.count($materiUrut).', seharusnya '.self::EXPECTED_MATERI.'.');
        }
        foreach ($perPeserta as $no => $materi) {
            if (count($materi) !== self::EXPECTED_MATERI) {
                throw new RuntimeException("Peserta No {$no} hanya punya ".count($materi).' materi.');
            }
        }
        if (count($perPeserta) !== self::EXPECTED_PESERTA) {
            throw new RuntimeException('penilaian.csv mencakup '.count($perPeserta).' peserta, seharusnya '.self::EXPECTED_PESERTA.'.');
        }
    }

    private function siapkanKegiatan(): int
    {
        $existing = DB::table('lakmud_events')
            ->where('name', self::EVENT['name'])
            ->where('organization', self::EVENT['organization'])
            ->first();

        $now = now();

        if ($existing === null) {
            return DB::table('lakmud_events')->insertGetId(self::EVENT + [
                'finalized' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
        if ($existing->finalized) {
            throw new RuntimeException('Kegiatan sudah dikunci. Buka kunci lewat menu Hasil sebelum menjalankan seeder.');
        }
        DB::table('lakmud_events')->where('id', $existing->id)->update([
            'venue' => self::EVENT['venue'],
            'start_date' => self::EVENT['start_date'],
            'end_date' => self::EVENT['end_date'],
            'updated_at' => $now,
        ]);

        return (int) $existing->id;
    }

    /**
     * @return array<int, int> nomor urut materi => id
     */
    private function siapkanMateri(int $eventId, array $penilaian): array
    {
        $materi = [];
        foreach ($penilaian as $row) {
            $materi[(int) $row['materi_no']] = $row['materi_nama'];
        }
        ksort($materi);

        $now = now();
        $rows = [];
        foreach ($materi as $sequence => $nama) {
            $rows[] = ['lakmud_event_id' => $eventId, 'sequence' => $sequence, 'name' => $nama, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('event_materials')->upsert($rows, ['lakmud_event_id', 'sequence'], ['name', 'is_active', 'updated_at']);

        return DB::table('event_materials')->where('lakmud_event_id', $eventId)->pluck('id', 'sequence')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return array<int, int> nomor urut peserta => id peserta
     */
    private function siapkanPeserta(int $eventId, array $peserta): array
    {
        $sudahAda = DB::table('event_participants')
            ->where('lakmud_event_id', $eventId)
            ->pluck('participant_id', 'sequence_number')
            ->map(fn ($id) => (int) $id)
            ->all();

        $now = now();
        $peta = [];
        $namaBerubah = [];
        foreach ($peserta as $row) {
            $no = (int) $row['no'];
            $nama = trim($row['nama']);
            if (isset($sudahAda[$no])) {
                $peta[$no] = $sudahAda[$no];
                $namaBerubah[$sudahAda[$no]] = $nama;

                continue;
            }
            $peta[$no] = (int) DB::table('participants')->insertGetId(['full_name' => $nama, 'created_at' => $now, 'updated_at' => $now]);
        }

        // Selaraskan nama peserta yang sudah ada (biasanya tidak berubah).
        if ($namaBerubah !== []) {
            $tersimpan = DB::table('participants')->whereIn('id', array_keys($namaBerubah))->pluck('full_name', 'id')->all();
            foreach ($namaBerubah as $id => $nama) {
                if (($tersimpan[$id] ?? null) !== $nama) {
                    DB::table('participants')->where('id', $id)->update(['full_name' => $nama, 'updated_at' => $now]);
                }
            }
        }

        return $peta;
    }

    /**
     * @param  array<int, array<int, string>>  $peserta
     * @param  array<int, int>  $pesertaId
     * @return array<int, int> nomor urut peserta => id pendaftaran (event_participants)
     */
    private function siapkanPendaftaran(int $eventId, array $peserta, array $pesertaId): array
    {
        $now = now();
        $rows = [];
        foreach ($peserta as $row) {
            $no = (int) $row['no'];
            $rows[] = [
                'lakmud_event_id' => $eventId,
                'sequence_number' => $no,
                'participant_id' => $pesertaId[$no],
                'status' => 'registered',
                'registered_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            DB::table('event_participants')->upsert($chunk, ['lakmud_event_id', 'sequence_number'], ['participant_id', 'status', 'updated_at']);
        }

        return DB::table('event_participants')
            ->where('lakmud_event_id', $eventId)
            ->pluck('id', 'sequence_number')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, int>  $daftarId
     * @param  array<int, int>  $materiId
     */
    private function isiNilai(array $daftarId, array $materiId, array $penilaian): void
    {
        $now = now();
        $absensi = [];
        $nilai = [];
        $aspek = [];

        foreach ($penilaian as $row) {
            $pesertaNo = (int) $row['no'];
            $materiNo = (int) $row['materi_no'];
            if (! isset($daftarId[$pesertaNo], $materiId[$materiNo])) {
                throw new RuntimeException("Pendaftaran atau materi tidak ditemukan untuk peserta {$pesertaNo} materi {$materiNo}.");
            }
            $pendaftaran = $daftarId[$pesertaNo];
            $materi = $materiId[$materiNo];
            $umum = ['event_participant_id' => $pendaftaran, 'event_material_id' => $materi, 'created_at' => $now, 'updated_at' => $now];

            $absensi[] = $umum + ['status' => ((int) $row['aspek1']) === 1 ? 'absent' : 'present'];
            $nilai[] = $umum + ['assessment_type' => 'pretest', 'score' => (float) $row['aspek2']];
            $nilai[] = $umum + ['assessment_type' => 'posttest', 'score' => (float) $row['aspek6']];
            $aspek[] = $umum + ['activity' => (float) $row['aspek3'], 'ethics' => (float) $row['aspek4'], 'understanding' => (float) $row['aspek5']];
        }

        foreach (array_chunk($absensi, self::CHUNK) as $chunk) {
            DB::table('attendance_records')->upsert($chunk, ['event_participant_id', 'event_material_id'], ['status', 'updated_at']);
        }
        foreach (array_chunk($nilai, self::CHUNK) as $chunk) {
            DB::table('assessment_scores')->upsert($chunk, ['event_participant_id', 'event_material_id', 'assessment_type'], ['score', 'updated_at']);
        }
        foreach (array_chunk($aspek, self::CHUNK) as $chunk) {
            DB::table('moora_aspect_scores')->upsert($chunk, ['event_participant_id', 'event_material_id'], ['activity', 'ethics', 'understanding', 'updated_at']);
        }
    }

    private function laporkan(int $eventId, float $detik): void
    {
        $ringkas = [
            'lakmud_events' => DB::table('lakmud_events')->where('id', $eventId)->count(),
            'event_materials' => DB::table('event_materials')->where('lakmud_event_id', $eventId)->count(),
            'event_participants' => DB::table('event_participants')->where('lakmud_event_id', $eventId)->count(),
            'attendance_records' => DB::table('attendance_records')->count(),
            'assessment_scores' => DB::table('assessment_scores')->count(),
            'moora_aspect_scores' => DB::table('moora_aspect_scores')->count(),
            'moora_runs' => DB::table('moora_runs')->where('lakmud_event_id', $eventId)->count(),
            'moora_results' => DB::table('moora_results')->count(),
        ];

        $this->command?->info(sprintf('Kegiatan Lakmud #%d selesai diisi dalam %.1f detik.', $eventId, $detik));
        $this->command?->table(['tabel', 'baris'], collect($ringkas)->map(fn ($value, $table) => [$table, $value])->values()->all());
        if ((string) $ringkas['moora_runs'] !== '0' || (string) $ringkas['moora_results'] !== '0') {
            $this->command?->warn('Snapshot MOORA tidak kosong. Jalankan ulang setelah membersihkan hasil agar perhitungan diuji dari nol.');
        }
    }
}
