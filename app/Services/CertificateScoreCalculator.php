<?php

namespace App\Services;

use InvalidArgumentException;

class CertificateScoreCalculator
{
    public function calculate(array $materials): array
    {
        if (count($materials) !== 15) {
            throw new InvalidArgumentException('Nilai harus mencakup tepat 15 materi.');
        }

        $totals = [];
        foreach ($materials as $components) {
            if (! is_array($components) || count($components) !== 5) {
                throw new InvalidArgumentException('Setiap materi harus memiliki lima nilai.');
            }
            $sum = 0;
            foreach ($components as $value) {
                if (! is_numeric($value) || (float) $value < 10 || (float) $value > 20 || round((float) $value, 2) !== (float) $value) {
                    throw new InvalidArgumentException('Setiap nilai harus 10–20 dengan maksimal dua desimal.');
                }
                $sum += (int) round((float) $value * 100);
            }
            $totals[] = (float) ($sum / 100);
        }

        $unrounded = array_sum($totals) / 15;
        $score = round($unrounded, 2, PHP_ROUND_HALF_UP);
        $predicate = $this->predicate($unrounded);

        return [
            'materials' => $totals,
            'score' => $score,
            'predicate' => $predicate,
            'status' => in_array($predicate, ['A', 'B', 'C'], true) ? 'LULUS' : 'TIDAK LULUS',
        ];
    }

    public function predicate(float $score): string
    {
        if ($score < 50 || $score > 100) {
            throw new InvalidArgumentException('Nilai sertifikat harus 50–100.');
        }

        $rounded = (int) round($score, 0, PHP_ROUND_HALF_UP);

        return match (true) {
            $rounded >= 91 => 'A',
            $rounded >= 81 => 'B',
            $rounded >= 71 => 'C',
            $rounded >= 61 => 'D',
            default => 'E',
        };
    }
}
