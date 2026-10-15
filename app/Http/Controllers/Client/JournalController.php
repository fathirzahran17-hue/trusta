<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\JournalDetail;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class JournalController extends Controller
{
    public function index(Request $request)
    {
        $companyId = auth()->user()->company_id;

        $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        $search = $request->input('search');

        $query = Journal::where('company_id', $companyId)
            ->with(['createdBy', 'details.account'])
            ->whereBetween('transaction_date', [$startDate, $endDate]);
            
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('ref_number', 'like', "%{$search}%")
                  ->orWhere('ref_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $query->orderBy('transaction_date', 'desc')
              ->orderBy('id', 'desc');

        // Export Excel (Maatwebsite)
        if ($request->has('export') && $request->export == 'excel') {
            $journals = $query->get();
            $data = compact('journals', 'startDate', 'endDate');
            return Excel::download(new class($data) implements FromView {
                private $data;
                public function __construct($data) { $this->data = $data; }
                public function view(): View { return view('client.journals.exports.excel', $this->data); }
            }, 'Jurnal_Umum_' . $startDate . '_sd_' . $endDate . '.xlsx');
        }

        // Export CSV (Lokal / Alternatif)
        if ($request->has('export') && $request->export == 'csv') {
            $journals = $query->get();
            $headers = ['Tanggal', 'No. Jurnal', 'Keterangan', 'Akun', 'Debit', 'Kredit'];
            $rows = [];
            foreach ($journals as $journal) {
                foreach ($journal->details as $detail) {
                    $rows[] = [
                        $journal->transaction_date,
                        $journal->ref_number,
                        $journal->description,
                        ($detail->account->code ?? '') . ' - ' . ($detail->account->name ?? ''),
                        $detail->debit,
                        $detail->credit,
                    ];
                }
            }
            return \App\Helpers\CsvExporter::download(
                'Jurnal_Umum_' . $startDate . '_sd_' . $endDate . '.csv',
                $headers,
                $rows
            );
        }

        // Export PDF (printable view)
        if ($request->has('export') && $request->export == 'pdf') {
            $journals = $query->get();
            return view('client.journals.exports.pdf', compact('journals', 'startDate', 'endDate'));
        }

        $journals = $query->paginate(15)->appends($request->query());

        return view('client.journals.index', compact('journals', 'startDate', 'endDate'));
    }

    public function create()
    {
        $accounts = Account::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
            
        // Generate nomor referensi otomatis (JU-YYYYMM-XXX)
        $companyId = auth()->user()->company_id;
        $prefix = 'JU-' . Carbon::now()->format('Ym') . '-';
        $lastJournal = Journal::where('company_id', $companyId)
            ->where('ref_number', 'like', $prefix . '%')
            ->orderBy('id', 'desc')
            ->first();
            
        $nextNum = 1;
        if ($lastJournal) {
            $lastNum = (int) substr($lastJournal->ref_number, -3);
            $nextNum = $lastNum + 1;
        }
        $refNumber = $prefix . str_pad($nextNum, 3, '0', STR_PAD_LEFT);

        $jobs = \App\Models\ProjectJob::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->get();

        return view('client.journals.create', compact('accounts', 'refNumber', 'jobs'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'transaction_date' => 'required|date',
            'ref_number' => 'required|string|max:100',
            'description' => 'required|string',
            'accounts' => 'required|array|min:2',
            'accounts.*' => 'required|exists:accounts,id',
            'debits' => 'required|array|min:2',
            'debits.*' => 'nullable|numeric|min:0',
            'credits' => 'required|array|min:2',
            'credits.*' => 'nullable|numeric|min:0',
            'row_descriptions' => 'nullable|array',
        ]);

        // Cek Balance (Keseimbangan Debit & Kredit)
        $totalDebit = array_sum(array_map('floatval', $request->debits));
        $totalCredit = array_sum(array_map('floatval', $request->credits));

        if (abs($totalDebit - $totalCredit) > 0.01 || $totalDebit == 0) {
            return back()->withInput()->with('error', 'Total Debit dan Kredit tidak seimbang atau nol. Mohon periksa kembali input Anda.');
        }

        try {
            DB::beginTransaction();

            $journal = Journal::create([
                'company_id' => auth()->user()->company_id,
                'ref_number' => $request->ref_number,
                'type' => 'general',
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);

            foreach ($request->accounts as $index => $accountId) {
                $debit = floatval($request->debits[$index] ?? 0);
                $credit = floatval($request->credits[$index] ?? 0);
                
                // Hanya simpan baris yang ada nilainya
                if ($debit > 0 || $credit > 0) {
                    $jobId = $request->jobs[$index] ?? null;
                    JournalDetail::create([
                        'journal_id' => $journal->id,
                        'account_id' => $accountId,
                        'project_job_id' => $jobId,
                        'debit' => $debit,
                        'credit' => $credit,
                        'description' => $request->row_descriptions[$index] ?? null,
                    ]);
                }
            }

            \App\Models\ActivityLog::create([
                'company_id' => auth()->user()->company_id,
                'user_id' => auth()->id(),
                'action' => 'create',
                'model_type' => \App\Models\Journal::class,
                'model_id' => $journal->id,
                'description' => 'Membuat Jurnal Baru: ' . $journal->ref_number,
                'ip_address' => request()->ip(),
            ]);

            DB::commit();
            return redirect()->route('client.journals.index')->with('success', 'Jurnal berhasil disimpan dan diposting.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    public function show(Journal $journal)
    {
        if ($journal->company_id !== auth()->user()->company_id) abort(403);
        $journal->load(['details.account', 'details.projectJob', 'createdBy']);
        return view('client.journals.show', compact('journal'));
    }

    public function edit(Journal $journal)
    {
        if ($journal->company_id !== auth()->user()->company_id) abort(403);
        
        $accounts = Account::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
            
        $jobs = \App\Models\ProjectJob::where('company_id', auth()->user()->company_id)
            ->where('is_active', true)
            ->get();
            
        $journal->load('details');
        
        return view('client.journals.edit', compact('journal', 'accounts', 'jobs'));
    }

    public function update(Request $request, Journal $journal)
    {
        if ($journal->company_id !== auth()->user()->company_id) abort(403);

        $request->validate([
            'transaction_date' => 'required|date',
            'ref_number' => 'required|string|max:100',
            'description' => 'required|string',
            'accounts' => 'required|array|min:2',
            'accounts.*' => 'required|exists:accounts,id',
            'debits' => 'required|array|min:2',
            'debits.*' => 'nullable|numeric|min:0',
            'credits' => 'required|array|min:2',
            'credits.*' => 'nullable|numeric|min:0',
            'row_descriptions' => 'nullable|array',
        ]);

        // Cek Balance
        $totalDebit = array_sum(array_map('floatval', $request->debits));
        $totalCredit = array_sum(array_map('floatval', $request->credits));

        if (abs($totalDebit - $totalCredit) > 0.01 || $totalDebit == 0) {
            return back()->withInput()->with('error', 'Total Debit dan Kredit tidak seimbang atau nol. Mohon periksa kembali input Anda.');
        }

        try {
            DB::beginTransaction();

            // Update Header
            $journal->update([
                'ref_number' => $request->ref_number,
                'transaction_date' => $request->transaction_date,
                'description' => $request->description,
            ]);

            // Delete old details
            $journal->details()->delete();

            // Insert new details
            foreach ($request->accounts as $index => $accountId) {
                $debit = floatval($request->debits[$index] ?? 0);
                $credit = floatval($request->credits[$index] ?? 0);
                
                if ($debit > 0 || $credit > 0) {
                    $jobId = $request->jobs[$index] ?? null;
                    JournalDetail::create([
                        'journal_id' => $journal->id,
                        'account_id' => $accountId,
                        'project_job_id' => $jobId,
                        'debit' => $debit,
                        'credit' => $credit,
                        'description' => $request->row_descriptions[$index] ?? null,
                    ]);
                }
            }

            DB::commit();
            return redirect()->route('client.journals.show', $journal->id)->with('success', 'Jurnal berhasil diperbarui.');

        } catch (\Exception $e) {
            DB::rollback();
            return back()->withInput()->with('error', 'Terjadi kesalahan sistem: ' . $e->getMessage());
        }
    }

    // Hapus jurnal (Void)
    public function destroy(Journal $journal)
    {
        if ($journal->company_id !== auth()->user()->company_id) abort(403);
        
        try {
            DB::beginTransaction();
            // Hapus detail dan induk
            $journalNumber = $journal->ref_number;
            $journal->details()->delete();
            $journal->delete();
            
            \App\Models\ActivityLog::create([
                'company_id' => auth()->user()->company_id,
                'user_id' => auth()->id(),
                'action' => 'delete',
                'model_type' => \App\Models\Journal::class,
                'model_id' => $journal->id,
                'description' => 'Membatalkan (Void) Jurnal: ' . $journalNumber,
                'ip_address' => request()->ip(),
            ]);

            DB::commit();
            return redirect()->route('client.journals.index')->with('success', 'Jurnal berhasil dihapus (Void).');
        } catch (\Exception $e) {
            DB::rollback();
            return back()->with('error', 'Gagal menghapus jurnal.');
        }
    }

    public function activityLog()
    {
        $logs = \App\Models\ActivityLog::with('user')
            ->where('company_id', auth()->user()->company_id)
            ->where('model_type', \App\Models\Journal::class)
            ->latest()
            ->paginate(20);

        return view('client.journals.activity_logs', compact('logs'));
    }
}
