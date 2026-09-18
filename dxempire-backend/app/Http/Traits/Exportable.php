<?php

namespace App\Http\Traits;

use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Shared CSV/PDF export for any list screen. Deliberately not using
 * maatwebsite/excel for the CSV path — the version actually installed
 * (v1.1.5) predates the API the rest of the codebase was written against,
 * so the old Excel export was silently 500ing. Plain CSV needs no package
 * at all and opens in Excel/Sheets exactly the same as a real .xlsx would
 * for anyone just wanting the data.
 */
trait Exportable
{
    /** @param iterable<array<int, mixed>> $rows */
    protected function exportCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel renders non-ASCII correctly
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @param iterable<array<int, mixed>> $rows */
    protected function exportPdf(string $title, array $headers, iterable $rows, string $filename)
    {
        $pdf = Pdf::loadView('exports.generic_table', [
            'title'   => $title,
            'headers' => $headers,
            'rows'    => $rows,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }
}
