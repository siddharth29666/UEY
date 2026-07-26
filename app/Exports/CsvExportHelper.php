<?php

namespace App\Exports;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportHelper
{
    /**
     * Generate a streamed CSV response.
     *
     * @param string $filename
     * @param array $headers
     * @param iterable $rows Iterable or Generator yielding row arrays
     * @return StreamedResponse
     */
    public static function streamCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        $responseHeaders = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // Add UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Write CSV Headers
            fputcsv($handle, $headers);

            // Write CSV Rows dynamically
            foreach ($rows as $row) {
                fputcsv($handle, (array) $row);
            }

            fclose($handle);
        }, 200, $responseHeaders);
    }
}
