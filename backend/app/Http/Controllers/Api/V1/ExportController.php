<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\InstitutionFiltersRequest;
use App\Services\Dashboard\ExportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /** Downloads the numbers of the current filters as `format=xlsx` (default) or `format=csv`. */
    public function show(InstitutionFiltersRequest $request, ExportService $export): BinaryFileResponse|StreamedResponse
    {
        $filters = $request->filters();
        $name = 'rowad-statistics-'.now()->format('Ymd-Hi');

        if ($request->query('format') === 'csv') {
            return response()->streamDownload(function () use ($export, $filters) {
                $out = fopen('php://output', 'w');
                $export->csv($filters, $out);
                fclose($out);
            }, "{$name}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $path = tempnam(sys_get_temp_dir(), 'rowad-xlsx-');
        $export->workbook($filters, $path);

        return response()->download($path, "{$name}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
