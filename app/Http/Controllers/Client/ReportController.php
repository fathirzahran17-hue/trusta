<?php

namespace App\Http\Controllers\Client;

use App\Helpers\CsvExporter;
use App\Http\Controllers\Controller;
use App\Services\Reports\ReportBuilder;
use App\Services\Reports\ReportXlsx;
use App\Support\ReportFormat;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    public function index()
    {
        return view('client.reports.index');
    }

    public function sptPpn()
    {
        return view('client.reports.spt_ppn');
    }

    public function taxCalculator()
    {
        return view('client.reports.tax_calculator');
    }

    public function integrated(Request $r)       { return $this->respond($r, fn ($b) => $b->integrated($r)); }
    public function profitLoss(Request $r)       { return $this->respond($r, fn ($b) => $b->profitLoss($r)); }
    public function balanceSheet(Request $r)     { return $this->respond($r, fn ($b) => $b->balanceSheet($r)); }
    public function generalLedger(Request $r)    { return $this->respond($r, fn ($b) => $b->generalLedger($r)); }
    public function trialBalance(Request $r)     { return $this->respond($r, fn ($b) => $b->trialBalance($r)); }
    public function receivableAging(Request $r)  { return $this->respond($r, fn ($b) => $b->receivableAging($r)); }
    public function receivableSummary(Request $r){ return $this->respond($r, fn ($b) => $b->receivableSummary($r)); }
    public function salesReport(Request $r)      { return $this->respond($r, fn ($b) => $b->salesReport($r)); }
    public function payableAging(Request $r)     { return $this->respond($r, fn ($b) => $b->payableAging($r)); }
    public function payableSummary(Request $r)   { return $this->respond($r, fn ($b) => $b->payableSummary($r)); }
    public function purchaseReport(Request $r)   { return $this->respond($r, fn ($b) => $b->purchaseReport($r)); }
    public function inventoryStock(Request $r)   { return $this->respond($r, fn ($b) => $b->inventoryStock($r)); }

    /** Satu pintu untuk semua laporan: layar, PDF (halaman cetak A4), Excel, CSV. */
    private function respond(Request $request, callable $make)
    {
        $user = auth()->user();
        $company = $user->company;
        $doc = $make(new ReportBuilder($user->company_id));
        $file = $doc['file'] ?? $doc['key'];

        switch ($request->input('export')) {
            case 'excel':
                try {
                    return \App\Helpers\XlsxExporter::download(ReportXlsx::build($doc, $company, $user->name), $file . '.xlsx');
                } catch (\Throwable $e) {
                    report($e);
                    return back()->with('error', 'Unduhan Excel gagal: ' . $e->getMessage() . ' — coba tombol CSV, atau kirim pesan ini ke admin.');
                }

            case 'csv':
                $headers = array_column($doc['columns'], 'label');
                $rows = [];
                foreach ($doc['rows'] as $row) {
                    if ($row['type'] === 'spacer') continue;
                    if ($row['type'] === 'section') { $rows[] = array_merge([$row['label']], array_fill(0, count($headers) - 1, '')); continue; }
                    $line = [];
                    foreach ($doc['columns'] as $i => $c) {
                        $v = $row['cells'][$i] ?? '';
                        $fmt = $c['format'] ?? 'text';
                        $line[] = $fmt === 'date' ? ($v ? \Carbon\Carbon::parse($v)->toDateString() : '') : (is_numeric($v) && $fmt !== 'text' ? (float) $v : $v);
                    }
                    $rows[] = $line;
                }
                return CsvExporter::download($file . '.csv', $headers, $rows);

            case 'pdf':
                return view('client.reports.print', compact('doc', 'company') + ['printedBy' => $user->name]);
        }

        return view('client.reports.show', compact('doc'));
    }
}
