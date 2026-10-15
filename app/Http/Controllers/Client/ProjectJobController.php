<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\ProjectJob;
use Illuminate\Http\Request;

class ProjectJobController extends Controller
{
    public function index()
    {
        $companyId = auth()->user()->company_id;
        
        $jobs = ProjectJob::where('company_id', $companyId)
            ->with(['journalDetails' => function($q) {
                $q->join('accounts', 'journal_details.account_id', '=', 'accounts.id')
                  ->select('journal_details.*', 'accounts.type as account_type');
            }])
            ->get();
            
        foreach ($jobs as $job) {
            $income = 0;
            $expense = 0;
            
            foreach ($job->journalDetails as $detail) {
                if (in_array($detail->account_type, ['revenue', 'other_income'])) {
                    $income += ($detail->credit - $detail->debit);
                } elseif (in_array($detail->account_type, ['cost_of_sales', 'expense', 'other_expense'])) {
                    $expense += ($detail->debit - $detail->credit);
                }
            }
            
            $job->income_total = $income;
            $job->expense_total = $expense;
            $job->profit_total = $income - $expense;
        }

        return view('client.project_jobs.index', compact('jobs'));
    }

    public function create()
    {
        return view('client.project_jobs.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        ProjectJob::create([
            'company_id' => auth()->user()->company_id,
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('client.project-jobs.index')->with('success', 'Kartu Job berhasil ditambahkan.');
    }

    public function edit(ProjectJob $projectJob)
    {
        if ($projectJob->company_id !== auth()->user()->company_id) {
            abort(403);
        }
        return view('client.project_jobs.edit', compact('projectJob'));
    }

    public function update(Request $request, ProjectJob $projectJob)
    {
        if ($projectJob->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        $request->validate([
            'code' => 'nullable|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $projectJob->update([
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('client.project-jobs.index')->with('success', 'Kartu Job berhasil diperbarui.');
    }

    public function destroy(ProjectJob $projectJob)
    {
        if ($projectJob->company_id !== auth()->user()->company_id) {
            abort(403);
        }

        try {
            $projectJob->delete();
            return redirect()->route('client.project-jobs.index')->with('success', 'Kartu Job berhasil dihapus.');
        } catch (\Exception $e) {
            return redirect()->route('client.project-jobs.index')->with('error', 'Kartu Job tidak dapat dihapus karena sudah digunakan dalam transaksi.');
        }
    }
}
