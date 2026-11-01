<?php

namespace App\Services;

use InvalidArgumentException;

class MooraCalculator
{
    /**
     * Bobot default C1 sampai C6. Nilai ini tetap menjadi acuan saat panel belum pernah
     * menyimpan bobot global (lihat MooraWeights::current()).
     */
    public const WEIGHTS = [0.10, 0.10, 0.20, 0.15, 0.20, 0.25];

    /**
     * @param  array<int, array<int, float|int>>  $matrix
     * @param  array<int, float>  $weights  Bobot aktif C1 sampai C6, berjumlah 1.
     */
    public function calculate(array $matrix, array $weights = self::WEIGHTS): array
    {
        if (count($weights) !== 6) {
            throw new InvalidArgumentException('Bobot harus berisi tepat enam nilai C1–C6.');
        }
        if ($matrix === []) {
            throw new InvalidArgumentException('Belum ada peserta untuk dihitung.');
        }

        $squares = array_fill(0, 6, 0.0);
        foreach ($matrix as $criteria) {
            if (! is_array($criteria) || count($criteria) !== 6 || array_keys($criteria) !== range(0, 5)) {
                throw new InvalidArgumentException('Setiap peserta harus memiliki tepat enam kriteria C1–C6.');
            }
            foreach ($criteria as $index => $value) {
                if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
                    throw new InvalidArgumentException('Kriteria harus berupa nilai nonnegatif yang valid.');
                }
                $squares[$index] += (float) $value * (float) $value;
            }
        }

        $divisors = array_map(sqrt(...), $squares);
        foreach (array_slice($divisors, 1) as $divisor) {
            if ($divisor == 0.0) {
                throw new InvalidArgumentException('Pembagi kriteria manfaat tidak boleh nol.');
            }
        }

        $rows = [];
        foreach ($matrix as $participantId => $criteria) {
            $normalized = [];
            $weighted = [];
            foreach ($criteria as $index => $value) {
                $normalized[] = $divisors[$index] == 0.0 ? 0.0 : (float) $value / $divisors[$index];
                $weighted[] = $normalized[$index] * $weights[$index];
            }
            $benefit = array_sum(array_slice($weighted, 1));
            $rows[$participantId] = [
                'criteria' => $criteria,
                'normalized' => $normalized,
                'weighted' => $weighted,
                'benefit_total' => $benefit,
                'cost_total' => $weighted[0],
                'yi' => $benefit - $weighted[0],
            ];
        }

        $sorted = array_column($rows, 'yi');
        rsort($sorted, SORT_NUMERIC);
        $rankByScore = [];
        foreach ($sorted as $position => $score) {
            $key = sprintf('%.17g', $score);
            $rankByScore[$key] ??= $position + 1;
        }
        foreach ($rows as &$row) {
            $row['rank'] = $rankByScore[sprintf('%.17g', $row['yi'])];
        }
        unset($row);

        return ['divisors' => $divisors, 'rows' => $rows];
    }
}
