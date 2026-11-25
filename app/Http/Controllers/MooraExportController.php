<?php

namespace App\Http\Controllers;

use App\Services\MooraExcelExporter;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MooraExportController extends Controller
{
    public function __invoke(int $event, MooraExcelExporter $exporter): BinaryFileResponse|RedirectResponse
    {
        $export = $exporter->export($event);

        if ($export === null) {
            return redirect('/hasil?event_id='.$event)
                ->withErrors(['panel' => 'Belum ada hasil MOORA untuk diekspor.']);
        }

        return response()
            ->download($export['path'], $export['filename'])
            ->deleteFileAfterSend(true);
    }
}
