<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

/**
 * Membuat lampiran Excel dari snapshot MOORA terakhir. Export hanya membaca
 * moora_runs dan moora_results; tidak menjalankan ulang perhitungan.
 */
class MooraExcelExporter
{
    /**
     * @return array{path: string, filename: string}|null
     */
    public function export(int $eventId): ?array
    {
        $event = DB::table('lakmud_events')->where('id', $eventId)->first();
        abort_unless($event, 404);

        $run = DB::table('moora_runs')->where('lakmud_event_id', $eventId)->latest('id')->first();
        if ($run === null) {
            return null;
        }

        $results = DB::table('moora_results as r')
            ->join('event_participants as ep', 'ep.id', '=', 'r.event_participant_id')
            ->join('participants as p', 'p.id', '=', 'ep.participant_id')
            ->where('r.moora_run_id', $run->id)
            ->orderBy('r.rank')
            ->orderBy('r.event_participant_id')
            ->select('r.*', 'p.full_name as participant_name')
            ->get();

        $weights = $this->numbers($run->weights);
        $divisors = $this->numbers($run->divisors);

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getProperties()
            ->setCreator('LAKMUD')
            ->setTitle('Hasil MOORA '.$event->name)
            ->setDescription('Export hasil perangkingan dan detail perhitungan MOORA.');

        $this->rankingSheet($spreadsheet->getActiveSheet(), $event, $run, $results, $weights);
        $this->detailSheet($spreadsheet->createSheet(), $event, $run, $results, $divisors);

        $path = tempnam(sys_get_temp_dir(), 'moora-');
        if ($path === false) {
            throw new RuntimeException('Berkas sementara export tidak dapat dibuat.');
        }

        try {
            (new Xlsx($spreadsheet))->save($path);
        } catch (Throwable $exception) {
            @unlink($path);

            throw $exception;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        $slug = Str::slug($event->name) ?: 'kegiatan';

        return [
            'path' => $path,
            'filename' => 'hasil-moora-'.$slug.'-run-'.$run->id.'.xlsx',
        ];
    }

    private function rankingSheet(Worksheet $sheet, object $event, object $run, object $results, array $weights): void
    {
        $sheet->setTitle('Hasil Perangkingan');
        $sheet->getDefaultRowDimension()->setRowHeight(18);
        $this->title($sheet, 'A1:F1', 'HASIL PERANGKINGAN MOORA');

        $this->detail($sheet, 2, 'Kegiatan', $event->name, 'F');
        $this->detail($sheet, 3, 'Organisasi', $event->organization, 'F');
        $this->detail($sheet, 4, 'Tempat', $event->venue, 'F');
        $this->detail($sheet, 5, 'Tanggal', $this->date($event->start_date).' s.d. '.$this->date($event->end_date), 'F');
        $this->detail($sheet, 6, 'Run', '#'.$run->id.' · '.$run->formula_version.' · '.$run->status, 'F');
        $this->detail($sheet, 7, 'Dihitung', $this->dateTime($run->created_at), 'F');
        $this->detail($sheet, 8, 'Bobot C1-C6', $this->weights($weights), 'F');

        $headers = ['Ranking', 'Peserta', 'Yi', 'Nilai Sertifikat', 'Predikat', 'Status'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'10', $header);
        }
        $this->header($sheet, 'A10:F10');

        $row = 11;
        foreach ($results as $result) {
            $sheet->setCellValue('A'.$row, (int) $result->rank);
            $this->text($sheet, 'B'.$row, $result->participant_name);
            $sheet->setCellValue('C'.$row, (float) $result->yi);
            $sheet->setCellValue('D'.$row, (float) $result->certificate_score);
            $this->text($sheet, 'E'.$row, $result->certificate_predicate);
            $this->text($sheet, 'F'.$row, $result->certificate_status);
            $row++;
        }

        $last = max(11, $row - 1);
        $sheet->getStyle('C11:C'.$last)->getNumberFormat()->setFormatCode('0.0000000000');
        $sheet->getStyle('D11:D'.$last)->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle('A11:F'.$last)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->freezePane('A11');

        if ($results->isNotEmpty()) {
            $sheet->setAutoFilter('A10:F'.$last);
        }

        foreach (['A' => 10, 'B' => 40, 'C' => 18, 'D' => 18, 'E' => 12, 'F' => 14] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function detailSheet(Worksheet $sheet, object $event, object $run, object $results, array $divisors): void
    {
        $sheet->setTitle('Detail Perhitungan');
        $sheet->getDefaultRowDimension()->setRowHeight(18);
        $this->title($sheet, 'A1:Z1', 'DETAIL PERHITUNGAN MOORA');

        $this->detail($sheet, 2, 'Kegiatan', $event->name, 'Z');
        $this->detail($sheet, 3, 'Run', '#'.$run->id.' · '.$run->formula_version.' · '.$run->status, 'Z');
        $this->detail($sheet, 4, 'Pembagi D1-D6', $this->divisors($divisors), 'Z');

        $headers = ['Ranking', 'Peserta'];
        foreach (range(1, 6) as $index) {
            $headers[] = 'C'.$index;
        }
        foreach (range(1, 6) as $index) {
            $headers[] = 'N'.$index;
        }
        foreach (range(1, 6) as $index) {
            $headers[] = 'V'.$index;
        }
        $headers = array_merge($headers, ['Benefit', 'Cost', 'Yi', 'Nilai Sertifikat', 'Predikat', 'Status']);

        foreach ($headers as $index => $header) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($index + 1).'6', $header);
        }
        $this->header($sheet, 'A6:Z6');

        $row = 7;
        foreach ($results as $result) {
            $values = array_merge(
                [(int) $result->rank],
                [$result->participant_name],
                $this->six($result->criteria),
                $this->six($result->normalized),
                $this->six($result->weighted),
                [
                    (float) $result->benefit_total,
                    (float) $result->cost_total,
                    (float) $result->yi,
                    (float) $result->certificate_score,
                ],
                [$result->certificate_predicate, $result->certificate_status],
            );

            foreach ($values as $index => $value) {
                $coordinate = Coordinate::stringFromColumnIndex($index + 1).$row;
                if (in_array($index, [1, 24, 25], true)) {
                    $this->text($sheet, $coordinate, $value);
                } else {
                    $sheet->setCellValue($coordinate, $value);
                }
            }
            $row++;
        }

        $last = max(7, $row - 1);
        $sheet->getStyle('C7:H'.$last)->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle('I7:T'.$last)->getNumberFormat()->setFormatCode('0.00000000');
        $sheet->getStyle('U7:V'.$last)->getNumberFormat()->setFormatCode('0.00000000');
        $sheet->getStyle('W7:W'.$last)->getNumberFormat()->setFormatCode('0.0000000000');
        $sheet->getStyle('X7:X'.$last)->getNumberFormat()->setFormatCode('0.00');
        $sheet->getStyle('A7:Z'.$last)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->freezePane('C7');

        if ($results->isNotEmpty()) {
            $sheet->setAutoFilter('A6:Z'.$last);
        }

        $sheet->getColumnDimension('A')->setWidth(9);
        $sheet->getColumnDimension('B')->setWidth(36);
        foreach (range('C', 'H') as $column) {
            $sheet->getColumnDimension($column)->setWidth(10);
        }
        foreach (range('I', 'T') as $column) {
            $sheet->getColumnDimension($column)->setWidth(15);
        }
        foreach (['U', 'V', 'W', 'X', 'Y', 'Z'] as $column) {
            $sheet->getColumnDimension($column)->setWidth(14);
        }
    }

    private function title(Worksheet $sheet, string $range, string $text): void
    {
        $sheet->mergeCells($range);
        $this->text($sheet, explode(':', $range)[0], $text);
        $sheet->getRowDimension(1)->setRowHeight(26);
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0D6560');
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function detail(Worksheet $sheet, int $row, string $label, string $value, string $mergeTo): void
    {
        $this->text($sheet, 'A'.$row, $label);
        $this->text($sheet, 'B'.$row, $value);
        $sheet->mergeCells('B'.$row.':'.$mergeTo.$row);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true);
    }

    private function header(Worksheet $sheet, string $range): void
    {
        $sheet->getRowDimension((int) substr($range, strpos($range, ':') + 2))->setRowHeight(30);
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF0D6560');
        $style->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER)
            ->setWrapText(true);
    }

    private function text(Worksheet $sheet, string $coordinate, mixed $value): void
    {
        $sheet->setCellValueExplicit($coordinate, (string) ($value ?? ''), DataType::TYPE_STRING);
    }

    /**
     * @return array<int, float>
     */
    private function numbers(?string $json): array
    {
        $values = json_decode((string) $json, true);

        return array_map(fn (mixed $value) => (float) $value, is_array($values) ? array_values($values) : []);
    }

    /**
     * @return array<int, float>
     */
    private function six(?string $json): array
    {
        return array_pad($this->numbers($json), 6, 0.0);
    }

    private function weights(array $weights): string
    {
        return implode(' | ', array_map(
            fn (float $weight) => number_format($weight, 2, ',', '.'),
            array_pad($weights, 6, 0.0),
        ));
    }

    private function divisors(array $divisors): string
    {
        return implode(' | ', array_map(
            fn (float $divisor) => number_format($divisor, 8, ',', '.'),
            array_pad($divisors, 6, 0.0),
        ));
    }

    private function date(?string $date): string
    {
        return $date ? Carbon::parse($date)->format('d-m-Y') : '-';
    }

    private function dateTime(?string $date): string
    {
        return $date ? Carbon::parse($date)->format('d-m-Y H:i') : '-';
    }
}
