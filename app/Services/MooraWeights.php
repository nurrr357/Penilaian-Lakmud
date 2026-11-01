<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Setelan bobot kriteria MOORA yang berlaku untuk semua kegiatan.
 *
 * Definisi kriteria tidak dapat diubah dari panel: C1 sampai C6 tetap seperti pada skripsi,
 * dan yang disimpan di sini hanya enam angka bobotnya. Perhitungan yang sudah pernah
 * dijalankan tidak ikut berubah, karena setiap run menyimpan salinan bobotnya sendiri di
 * kolom `moora_runs.weights`.
 */
class MooraWeights
{
    /** Jumlah kriteria MOORA: C1 cost, C2 sampai C6 benefit. */
    public const COUNT = 6;

    /** Toleransi pembandingan jumlah bobot, cukup ketat untuk bilangan pecahan biner. */
    private const SUM_TOLERANCE = 1e-9;

    /**
     * Bobot yang sedang berlaku. Selama tabel setelan kosong, dipakai bobot default
     * MooraCalculator supaya instalasi baru dan data lama menghasilkan angka yang sama.
     *
     * @return array<int, float>
     */
    public function current(): array
    {
        $tersimpan = DB::table('moora_weights')->orderBy('id')->value('weights');

        return $tersimpan === null ? MooraCalculator::WEIGHTS : $this->normalize(json_decode($tersimpan, true));
    }

    /**
     * Ubah bobot global. Perubahan ditolak selama masih ada hasil yang sah, karena hasil itu
     * dihitung dengan bobot lain dan harus dibuka kuncinya lebih dulu.
     *
     * @param  array<int, mixed>  $weights
     *
     * @throws ValidationException
     */
    public function update(array $weights): void
    {
        $bobot = $this->validated($weights);

        DB::transaction(function () use ($bobot) {
            $this->ensureUnlocked();
            $sekarang = now();
            $baris = DB::table('moora_weights')->orderBy('id')->first();
            $nilai = ['weights' => json_encode($bobot), 'updated_by' => auth()->id(), 'updated_at' => $sekarang];

            if ($baris) {
                DB::table('moora_weights')->where('id', $baris->id)->update($nilai);
            } else {
                DB::table('moora_weights')->insert($nilai + ['created_at' => $sekarang]);
            }

            activity()->causedBy(auth()->user())->withProperties(['weights' => $bobot])->log('criteria.weights.updated');
        });
    }

    /**
     * Alasan bobot tidak dapat diubah, atau null bila masih boleh diubah. Dipakai halaman
     * kriteria untuk menonaktifkan form sekaligus menjelaskan sebabnya, dan dipakai update()
     * sebagai penjaga terakhir supaya aturan tidak hanya hidup di tampilan.
     */
    public function lockReason(): ?string
    {
        $terkunci = DB::table('lakmud_events')->where('finalized', true)->orderBy('id')->value('name');

        if ($terkunci !== null) {
            return 'Kegiatan '.$terkunci.' sudah difinalisasi. Buka kunci kegiatan itu di halaman Hasil Perangkingan sebelum mengubah bobot.';
        }

        $sah = DB::table('moora_runs')->join('lakmud_events', 'lakmud_events.id', '=', 'moora_runs.lakmud_event_id')
            ->where('moora_runs.status', 'valid')->orderBy('moora_runs.id')->value('lakmud_events.name');

        if ($sah !== null) {
            // Hasil yang masih sah belum difinalisasi, jadi tombol Buka kunci belum ada di halaman
            // hasil. Perubahan input membuat run itu menjadi arsip, dan setelah itu bobot terbuka.
            return 'Kegiatan '.$sah.' punya hasil perhitungan yang sah. Simpan ulang salah satu input penilaian di kegiatan itu supaya hasilnya menjadi arsip, atau finalisasi lalu buka kunci di halaman Hasil Perangkingan.';
        }

        return null;
    }

    /**
     * @param  array<int, mixed>  $weights
     * @return array<int, float>
     *
     * @throws ValidationException
     */
    private function validated(array $weights): array
    {
        $bobot = $this->normalize($weights);

        if (count($bobot) !== self::COUNT) {
            throw ValidationException::withMessages(['weights' => 'Bobot harus berisi tepat enam nilai untuk C1 sampai C6.']);
        }

        foreach ($bobot as $index => $nilai) {
            if ($nilai === null) {
                throw ValidationException::withMessages(['weights' => 'Bobot C'.($index + 1).' harus berupa angka.']);
            }
            if ($nilai <= 0) {
                throw ValidationException::withMessages(['weights' => 'Bobot C'.($index + 1).' harus lebih besar dari nol.']);
            }
        }

        if (abs(array_sum($bobot) - 1.0) > self::SUM_TOLERANCE) {
            throw ValidationException::withMessages(['weights' => 'Jumlah bobot C1 sampai C6 harus tepat 1,00.']);
        }

        return $bobot;
    }

    /**
     * @param  array<int, mixed>  $weights
     * @return array<int, float|null>
     */
    private function normalize(array $weights): array
    {
        return array_map(fn ($nilai) => is_numeric($nilai) ? (float) $nilai : null, array_values($weights));
    }

    /**
     * @throws ValidationException
     */
    private function ensureUnlocked(): void
    {
        $alasan = $this->lockReason();

        if ($alasan !== null) {
            throw ValidationException::withMessages(['weights' => $alasan]);
        }
    }
}
