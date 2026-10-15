<?php

namespace App\Helpers;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExporter
{
    /**
     * Export data sebagai file CSV (bisa dibuka di Excel).
     *
     * @param string $filename  Nama file output (contoh: 'Jurnal_Umum.csv')
     * @param array  $headers   Header kolom (contoh: ['Tanggal', 'Keterangan', 'Debit', 'Kredit'])
     * @param array  $rows      Baris data (contoh: [['2026-01-01', 'Bayar Listrik', 100000, 0], ...])
     * @return StreamedResponse
     */
    public static function download(string $filename, array $headers, array $rows): StreamedResponse
    {
        return new StreamedResponse(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // BOM UTF-8 agar Excel bisa baca karakter Indonesia dengan benar
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Tulis header
            fputcsv($handle, $headers, ';');

            // Tulis data
            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ]);
    }
}
