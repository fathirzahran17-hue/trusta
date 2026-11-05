<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/** Mengubah dokumen laporan menjadi file Excel berformat (kop, header, angka, border, siap cetak A4). */
class ReportXlsx
{
    private const QTY = '#,##0.##';
    private const DATE = 'dd mmm yyyy';

    public static function build(array $doc, object $company, string $printedBy): Spreadsheet
    {
        $moneyFormat = \App\Support\Money::decimals() === 0 ? '#,##0;(#,##0);"-"' : '#,##0.00;(#,##0.00);"-"';
        $ss = new Spreadsheet();
        $ws = $ss->getActiveSheet();
        $ws->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $doc['title']), 0, 31));
        $ws->setShowGridlines(false);

        $cols = $doc['columns'];
        $n = count($cols);
        $last = Coordinate::stringFromColumnIndex($n);
        $ss->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10);

        // ---- Kop
        $ws->setCellValue('A1', mb_strtoupper($company->name ?? 'Perusahaan'));
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->setCellValue('A2', trim(($company->address ?? '') . ($company->npwp ? '  |  NPWP: ' . $company->npwp : '')));
        $ws->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('64748B');
        $ws->setCellValue('A3', mb_strtoupper($doc['title']));
        $ws->getStyle('A3')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('0F766E');
        $ws->setCellValue('A4', $doc['subtitle'] ?? '');
        $ws->getStyle('A4')->getFont()->setSize(10)->getColor()->setRGB('334155');
        foreach ([1, 2, 3, 4] as $r) $ws->mergeCells("A{$r}:{$last}{$r}");
        $ws->getStyle("A4:{$last}4")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('0F766E');

        // ---- Ringkasan
        $r = 6;
        foreach ($doc['summary'] ?? [] as $s) {
            $ws->setCellValue("A{$r}", $s['label']);
            $ws->mergeCells("A{$r}:" . Coordinate::stringFromColumnIndex(max(1, $n - 1)) . "{$r}");
            $ws->setCellValue("{$last}{$r}", $s['value']);
            $ws->getStyle("{$last}{$r}")->getNumberFormat()->setFormatCode(($s['format'] ?? 'money') === 'money' ? $moneyFormat : '#,##0');
            $ws->getStyle("A{$r}:{$last}{$r}")->getFont()->setBold(true);
            $ws->getStyle("A{$r}:{$last}{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
            $r++;
        }
        if (! empty($doc['summary'])) $r++;

        // ---- Header tabel
        $hdr = $r;
        foreach ($cols as $i => $c) {
            $cell = Coordinate::stringFromColumnIndex($i + 1) . $hdr;
            $ws->setCellValue($cell, $c['label']);
            $ws->getColumnDimensionByColumn($i + 1)->setWidth($c['width'] ?? 16);
        }
        $hs = $ws->getStyle("A{$hdr}:{$last}{$hdr}");
        $hs->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $hs->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F766E');
        $hs->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
        $ws->getRowDimension($hdr)->setRowHeight(24);
        foreach ($cols as $i => $c) {
            if (($c['align'] ?? 'left') === 'right') {
                $ws->getStyle(Coordinate::stringFromColumnIndex($i + 1) . $hdr)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        // ---- Baris
        $r = $hdr + 1;
        $first = $r;
        $indentCol = $doc['indent_col'] ?? 0;
        foreach ($doc['rows'] as $row) {
            $type = $row['type'];
            if ($type === 'spacer') { $r++; continue; }
            $range = "A{$r}:{$last}{$r}";

            if ($type === 'section') {
                $ws->setCellValue("A{$r}", $row['label']);
                $ws->mergeCells($range);
                $st = $ws->getStyle($range);
                $st->getFont()->setBold(true)->getColor()->setRGB('0F172A');
                $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
                $r++;
                continue;
            }

            foreach ($cols as $i => $c) {
                $v = $row['cells'][$i] ?? '';
                $coord = Coordinate::stringFromColumnIndex($i + 1) . $r;
                $fmt = $c['format'] ?? 'text';
                if ($v === '' || $v === null) continue;
                if ($fmt === 'date') {
                    $ws->setCellValue($coord, Date::PHPToExcel(Carbon::parse($v)));
                    $ws->getStyle($coord)->getNumberFormat()->setFormatCode(self::DATE);
                    $ws->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                } elseif (in_array($fmt, ['money', 'qty', 'int'], true) && is_numeric($v)) {
                    $ws->setCellValue($coord, (float) $v);
                    $ws->getStyle($coord)->getNumberFormat()->setFormatCode($fmt === 'money' ? $moneyFormat : ($fmt === 'qty' ? self::QTY : '#,##0'));
                } else {
                    $ws->setCellValueExplicit($coord, (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                if (($c['align'] ?? 'left') === 'right') {
                    $ws->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }
            if (($row['indent'] ?? 0) > 0) {
                $ws->getStyle(Coordinate::stringFromColumnIndex($indentCol + 1) . $r)->getAlignment()->setIndent($row['indent'])->setHorizontal(Alignment::HORIZONTAL_LEFT);
            }

            $st = $ws->getStyle($range);
            $st->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
            if (! empty($row['italic'])) $st->getFont()->setItalic(true)->getColor()->setRGB('64748B');
            if ($type === 'subtotal' || $type === 'total') {
                $st->getFont()->setBold(true);
                $st->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('64748B');
                if ($type === 'total') $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
            }
            if ($type === 'grand') {
                $st->getFont()->setBold(true)->setSize(11);
                $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('CCFBF1');
                $st->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('0F172A');
                $st->getBorders()->getBottom()->setBorderStyle(Border::BORDER_DOUBLE)->getColor()->setRGB('0F172A');
            }
            $r++;
        }

        if (empty($doc['rows'])) {
            $ws->setCellValue("A{$r}", $doc['empty'] ?? 'Tidak ada data.');
            $ws->mergeCells("A{$r}:{$last}{$r}");
            $ws->getStyle("A{$r}")->getFont()->setItalic(true);
            $r++;
        }

        // ---- Catatan & penutup
        foreach ($doc['notes'] ?? [] as $note) {
            $r++;
            $tone = is_array($note) ? $note['tone'] : ($doc['notes_tone'] ?? '');
            $ws->setCellValue("A{$r}", is_array($note) ? $note['text'] : $note);
            $ws->mergeCells("A{$r}:{$last}{$r}");
            $ws->getStyle("A{$r}")->getFont()->setItalic(true)->getColor()->setRGB(['bad' => 'B91C1C', 'info' => '475569'][$tone] ?? '047857');
        }
        $r += 2;
        $ws->setCellValue("A{$r}", 'Dicetak oleh ' . $printedBy . ' pada ' . now()->format('d/m/Y H:i') . ' — TRUSTA Accounting');
        $ws->mergeCells("A{$r}:{$last}{$r}");
        $ws->getStyle("A{$r}")->getFont()->setSize(8)->setItalic(true)->getColor()->setRGB('94A3B8');

        // ---- Tampilan & cetak
        $ws->freezePane('A' . ($hdr + 1));
        $ps = $ws->getPageSetup();
        $ps->setPaperSize(PageSetup::PAPERSIZE_A4);
        $ps->setOrientation(($doc['orientation'] ?? 'portrait') === 'landscape' ? PageSetup::ORIENTATION_LANDSCAPE : PageSetup::ORIENTATION_PORTRAIT);
        $ps->setFitToWidth(1)->setFitToHeight(0);
        $ws->getSheetView()->setZoomScale(100);
        $ps->setRowsToRepeatAtTopByStartAndEnd($hdr, $hdr);
        $ws->getPageSetup()->setHorizontalCentered(true);
        $ws->getPageMargins()->setTop(0.6)->setBottom(0.7)->setLeft(0.5)->setRight(0.5);
        $ws->getHeaderFooter()->setOddFooter('&L&8' . ($company->name ?? '') . '&C&8Halaman &P dari &N&R&8' . $doc['title']);
        $ss->getProperties()->setCreator('TRUSTA')->setTitle($doc['title']);

        if (! empty($doc['side_by_side'])) {
            self::pairSheet($ss, $doc, $company, $printedBy, $moneyFormat);
            $ss->setActiveSheetIndex(0);
        }

        return $ss;
    }

    /** Lembar dua sisi: kolom A sampai C untuk sisi kiri, E sampai G untuk sisi kanan. */
    private static function pairSheet(Spreadsheet $ss, array $doc, object $company, string $printedBy, string $moneyFormat): void
    {
        $pair = $doc['side_by_side'];
        $ws = $ss->createSheet();
        $ws->setTitle(mb_substr($pair['sheet'], 0, 31));
        $ws->setShowGridlines(false);
        $n = count($pair['columns']);
        $lastIndex = $n * 2 + 1;
        $last = Coordinate::stringFromColumnIndex($lastIndex);

        $ws->setCellValue('A1', mb_strtoupper($company->name ?? 'Perusahaan'));
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->setCellValue('A2', trim(($company->address ?? '') . ($company->npwp ? '  |  NPWP: ' . $company->npwp : '')));
        $ws->getStyle('A2')->getFont()->setSize(9)->getColor()->setRGB('64748B');
        $ws->setCellValue('A3', $pair['title']);
        $ws->getStyle('A3')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('0F766E');
        $ws->setCellValue('A4', $doc['subtitle'] ?? '');
        $ws->getStyle('A4')->getFont()->setSize(10)->getColor()->setRGB('334155');
        foreach ([1, 2, 3, 4] as $r) $ws->mergeCells("A{$r}:{$last}{$r}");
        $ws->getStyle("A4:{$last}4")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB('0F766E');

        $offsets = ['left' => 0, 'right' => $n + 1];
        $hdr = 6;
        $ws->getColumnDimensionByColumn($n + 1)->setWidth(2);
        foreach ($offsets as $offset) {
            foreach ($pair['columns'] as $i => $c) {
                $index = $offset + $i + 1;
                $cell = Coordinate::stringFromColumnIndex($index) . $hdr;
                $ws->setCellValue($cell, $c['label']);
                $ws->getColumnDimensionByColumn($index)->setWidth($c['width'] ?? 16);
                $st = $ws->getStyle($cell);
                $st->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F766E');
                $st->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true)
                    ->setHorizontal(($c['align'] ?? 'left') === 'right' ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT);
            }
        }
        $ws->getRowDimension($hdr)->setRowHeight(24);

        foreach ($offsets as $side => $offset) {
            $r = $hdr + 1;
            $from = Coordinate::stringFromColumnIndex($offset + 1);
            $to = Coordinate::stringFromColumnIndex($offset + $n);
            foreach ($pair[$side] as $row) {
                $range = "{$from}{$r}:{$to}{$r}";
                if ($row['type'] === 'spacer') {
                    $r++;
                    continue;
                }
                if ($row['type'] === 'section') {
                    $ws->setCellValue("{$from}{$r}", $row['label']);
                    $ws->mergeCells($range);
                    $st = $ws->getStyle($range);
                    $st->getFont()->setBold(true)->getColor()->setRGB('0F172A');
                    $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E2E8F0');
                    $r++;
                    continue;
                }
                foreach ($pair['columns'] as $i => $c) {
                    $coord = Coordinate::stringFromColumnIndex($offset + $i + 1) . $r;
                    $v = $row['cells'][$i] ?? '';
                    if ($v === '' || $v === null) {
                        continue;
                    }
                    if (($c['format'] ?? 'text') === 'money') {
                        $ws->setCellValue($coord, (float) $v);
                        $ws->getStyle($coord)->getNumberFormat()->setFormatCode($moneyFormat);
                        $ws->getStyle($coord)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    } else {
                        $ws->setCellValueExplicit($coord, (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                    }
                }
                $st = $ws->getStyle($range);
                $st->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
                if (($row['indent'] ?? 0) > 0) {
                    $ws->getStyle(Coordinate::stringFromColumnIndex($offset + 2) . $r)->getAlignment()->setIndent($row['indent'])->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }
                if (! empty($row['italic'])) {
                    $st->getFont()->setItalic(true)->getColor()->setRGB('64748B');
                }
                if ($row['type'] === 'subtotal' || $row['type'] === 'total') {
                    $st->getFont()->setBold(true);
                    $st->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('64748B');
                    if ($row['type'] === 'total') {
                        $st->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F1F5F9');
                    }
                }
                $r++;
            }
        }

        $r = max(count($pair['left']), count($pair['right'])) + $hdr + 2;
        $ws->setCellValue("A{$r}", 'Dicetak oleh ' . $printedBy . ' pada ' . now()->format('d/m/Y H:i') . ' — TRUSTA Accounting');
        $ws->mergeCells("A{$r}:{$last}{$r}");
        $ws->getStyle("A{$r}")->getFont()->setSize(8)->setItalic(true)->getColor()->setRGB('94A3B8');

        $ws->freezePane('A' . ($hdr + 1));
        $ps = $ws->getPageSetup();
        $ps->setPaperSize(PageSetup::PAPERSIZE_A4);
        $ps->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $ps->setFitToWidth(1)->setFitToHeight(0);
        $ps->setRowsToRepeatAtTopByStartAndEnd($hdr, $hdr);
        $ps->setHorizontalCentered(true);
        $ws->getPageMargins()->setTop(0.6)->setBottom(0.7)->setLeft(0.5)->setRight(0.5);
        $ws->getHeaderFooter()->setOddFooter('&L&8' . ($company->name ?? '') . '&C&8Halaman &P dari &N&R&8' . $pair['sheet']);
    }
}
