<?php

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Unduhan Excel yang tahan di hosting gratis (mis. InfinityFree):
 * file ditulis LENGKAP ke folder storage dulu (bukan langsung ke php://output), sehingga kegagalan terlihat sebagai pesan,
 * ukuran file diketahui (Content-Length), dan sisa output lain (spasi/peringatan) tidak ikut merusak isi .xlsx.
 */
class XlsxExporter
{
    /** @throws \Throwable bila file gagal dibuat */
    public static function download(Spreadsheet $spreadsheet, string $filename): StreamedResponse
    {
        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (! is_dir($dir) || ! is_writable($dir)) {
            $dir = sys_get_temp_dir();
        }
        $path = $dir . DIRECTORY_SEPARATOR . 'xlsx_' . bin2hex(random_bytes(8)) . '.xlsx';

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
        if (! is_file($path) || filesize($path) < 100) {
            @unlink($path);
            throw new \RuntimeException('File Excel gagal dibuat di server (folder sementara tidak bisa ditulis).');
        }
        $size = filesize($path);

        return new StreamedResponse(function () use ($path) {
            // Buang sisa output (spasi, peringatan) yang akan merusak isi berkas.
            while (ob_get_level() > 0 && ! (defined('PHPUNIT_COMPOSER_INSTALL') || app()->runningUnitTests())) {
                @ob_end_clean();
            }
            readfile($path);
            @unlink($path);
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'Content-Length' => (string) $size,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Content-Encoding' => 'identity',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
