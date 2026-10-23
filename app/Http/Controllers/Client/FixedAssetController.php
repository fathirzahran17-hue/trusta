<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\FixedAsset;
use App\Models\FixedAssetCategory;
use Illuminate\Http\Request;

class FixedAssetController extends Controller
{
    // ========== ASET TETAP (CRUD) ==========

    public function index(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $query = FixedAsset::where('company_id', $companyId)->with('category');

        // Filter status
        if ($request->status === 'aktif') {
            $query->where('status', 'aktif');
        } elseif ($request->status === 'arsip') {
            $query->whereIn('status', ['dijual', 'dihapus', 'arsip']);
        }

        // Search
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        $assets = $query->orderBy('code')->paginate(15)->appends($request->query());
        return view('client.fixed_assets.index', compact('assets'));
    }

    public function create()
    {
        $categories = FixedAssetCategory::where('company_id', auth()->user()->company_id)->get();
        $nextCode = $this->generateNextCode();
        return view('client.fixed_assets.create', compact('categories', 'nextCode'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'fixed_asset_category_id' => 'nullable|exists:fixed_asset_categories,id',
            'acquisition_date' => 'required|date',
            'acquisition_cost' => 'required|numeric|min:0',
            'useful_life_months' => 'required|integer|min:1',
            'salvage_value' => 'nullable|numeric|min:0',
            'depreciation_method' => 'required|in:straight_line,declining_balance',
            'location' => 'nullable|string|max:255',
            'condition' => 'required|in:baik,rusak_ringan,rusak_berat',
            'serial_number' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'supplier_name' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['company_id'] = auth()->user()->company_id;
        $validated['status'] = 'aktif';
        $validated['salvage_value'] = $validated['salvage_value'] ?? 0;

        FixedAsset::create($validated);

        return redirect()->route('client.fixed_assets.index')
            ->with('success', 'Aset tetap berhasil ditambahkan.');
    }

    public function edit(FixedAsset $fixedAsset)
    {
        $this->authorizeCompany($fixedAsset);
        $categories = FixedAssetCategory::where('company_id', auth()->user()->company_id)->get();
        return view('client.fixed_assets.edit', compact('fixedAsset', 'categories'));
    }

    public function update(Request $request, FixedAsset $fixedAsset)
    {
        $this->authorizeCompany($fixedAsset);

        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'fixed_asset_category_id' => 'nullable|exists:fixed_asset_categories,id',
            'acquisition_date' => 'required|date',
            'acquisition_cost' => 'required|numeric|min:0',
            'useful_life_months' => 'required|integer|min:1',
            'salvage_value' => 'nullable|numeric|min:0',
            'depreciation_method' => 'required|in:straight_line,declining_balance',
            'location' => 'nullable|string|max:255',
            'condition' => 'required|in:baik,rusak_ringan,rusak_berat',
            'status' => 'required|in:aktif,dijual,dihapus,arsip',
            'serial_number' => 'nullable|string|max:100',
            'brand' => 'nullable|string|max:100',
            'supplier_name' => 'nullable|string|max:255',
            'invoice_number' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['salvage_value'] = $validated['salvage_value'] ?? 0;
        $fixedAsset->update($validated);

        return redirect()->route('client.fixed_assets.index')
            ->with('success', 'Aset tetap berhasil diperbarui.');
    }

    public function destroy(FixedAsset $fixedAsset)
    {
        $this->authorizeCompany($fixedAsset);
        $fixedAsset->delete();
        return redirect()->route('client.fixed_assets.index')
            ->with('success', 'Aset tetap berhasil dihapus.');
    }

    /** Aset yang masih punya sisa nilai untuk disusutkan, lengkap dengan status kelayakan pada bulan terpilih. */
    private function depreciationRows(int $companyId, string $month)
    {
        return FixedAsset::where('company_id', $companyId)
            ->where('status', 'aktif')
            ->with('category')
            ->orderBy('code')
            ->get()
            ->map(function (FixedAsset $a) use ($companyId, $month) {
                $reason = null;
                $depreciable = (float) $a->acquisition_cost - (float) $a->salvage_value;
                if ((float) $a->accumulated_depreciation >= $depreciable - 0.004) {
                    $reason = 'Sudah habis disusutkan';
                } elseif ($a->acquisition_date && $a->acquisition_date->format('Y-m') > $month) {
                    $reason = 'Belum diperoleh pada bulan ini';
                } elseif ($a->last_depreciation_date && date('Y-m', strtotime($a->last_depreciation_date)) >= $month) {
                    $reason = 'Sudah disusutkan s/d ' . date('M Y', strtotime($a->last_depreciation_date));
                }
                $expDefault = $a->depreciation_expense_account_id
                    ?: optional(\App\Services\AccountResolver::depreciationExpense($companyId, $a->category?->account_expense_code))->id;
                $accDefault = $a->accumulated_account_id
                    ?: optional(\App\Services\AccountResolver::accumulatedDepreciation($companyId, $a->category?->account_depreciation_code))->id;

                return (object) ['asset' => $a, 'amount' => $a->nextDepreciationAmount(), 'reason' => $reason, 'exp' => $expDefault, 'acc' => $accDefault];
            });
    }

    private function depreciationAccounts(int $companyId): array
    {
        $all = \App\Models\Account::where('company_id', $companyId)->where('is_active', true)->orderBy('code')->get()->reject(fn ($a) => $a->is_header);
        $expense = $all->whereIn('type', ['expense', 'cost_of_sales', 'other_expense'])
            ->sortBy(fn ($a) => (stripos($a->name, 'penyusutan') !== false ? '0' : '1') . $a->code)->values();
        $accum = $all->where('type', 'asset')->where('is_cash_bank', false)
            ->sortBy(fn ($a) => (stripos($a->name, 'akumulasi') !== false ? '0' : '1') . $a->code)->values();

        return [$expense, $accum];
    }

    /** Halaman Jalankan Penyusutan: pilih bulan, aset, dan akun jurnalnya. */
    public function depreciation(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('month')) ? $request->query('month') : date('Y-m');
        $rows = $this->depreciationRows($companyId, $month);
        [$expenseAccounts, $accumAccounts] = $this->depreciationAccounts($companyId);
        $date = date('Y-m-t', strtotime($month . '-01'));

        return view('client.fixed_assets.depreciation', compact('rows', 'month', 'date', 'expenseAccounts', 'accumAccounts'));
    }

    public function runDepreciation(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $request->validate([
            'month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'date' => 'required|date',
            'assets' => 'required|array|min:1',
            'assets.*' => 'integer',
            'exp' => 'required|array',
            'acc' => 'required|array',
        ], ['assets.required' => 'Pilih minimal satu aset yang akan disusutkan.']);

        $month = $request->month;
        $date = $request->date;
        if (date('Y-m', strtotime($date)) !== $month) {
            return back()->withInput()->with('error', 'Tanggal jurnal harus berada pada bulan penyusutan yang dipilih (' . date('M Y', strtotime($month . '-01')) . ').');
        }

        [$expenseAccounts, $accumAccounts] = $this->depreciationAccounts($companyId);
        $rows = $this->depreciationRows($companyId, $month)->keyBy(fn ($r) => $r->asset->id);

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $perAccount = [];
            $count = 0;
            $total = 0.0;
            foreach ($request->assets as $id) {
                $row = $rows->get((int) $id);
                if (!$row) {
                    throw new \Exception('Aset tidak ditemukan.');
                }
                $asset = $row->asset;
                if ($row->reason) {
                    throw new \Exception($asset->name . ': ' . $row->reason . '.');
                }
                $expId = (int) ($request->exp[$id] ?? 0);
                $accId = (int) ($request->acc[$id] ?? 0);
                if (!$expenseAccounts->contains('id', $expId)) {
                    throw new \Exception($asset->name . ': pilih Akun Beban Penyusutan yang valid.');
                }
                if (!$accumAccounts->contains('id', $accId)) {
                    throw new \Exception($asset->name . ': pilih Akun Akumulasi Penyusutan yang valid.');
                }
                if ($row->amount <= 0) {
                    continue;
                }

                $key = $expId . '-' . $accId;
                $perAccount[$key] = ['exp' => $expId, 'acc' => $accId, 'amount' => round(($perAccount[$key]['amount'] ?? 0) + $row->amount, 2)];
                $total += $row->amount;
                $count++;

                $asset->accumulated_depreciation = round((float) $asset->accumulated_depreciation + $row->amount, 2);
                $asset->last_depreciation_date = $date;
                $asset->depreciation_expense_account_id = $expId; // diingat untuk penyusutan berikutnya
                $asset->accumulated_account_id = $accId;
                $asset->save();
            }

            if ($total <= 0) {
                throw new \Exception('Tidak ada nilai penyusutan yang bisa dijurnal.');
            }

            $journal = \App\Models\Journal::create([
                'company_id' => $companyId,
                'ref_number' => \App\Services\DocumentNumber::next(\App\Models\Journal::class, 'ref_number', 'DEP-', (int) $companyId, $date),
                'type' => 'general',
                'transaction_date' => $date,
                'description' => 'Penyusutan Aset Tetap Periode ' . date('F Y', strtotime($month . '-01')),
                'status' => 'posted',
                'created_by' => auth()->id(),
            ]);
            foreach ($perAccount as $row) {
                \App\Models\JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $row['exp'],
                    'description' => 'Beban Penyusutan Aset', 'debit' => $row['amount'], 'credit' => 0]);
                \App\Models\JournalDetail::create(['journal_id' => $journal->id, 'account_id' => $row['acc'],
                    'description' => 'Akumulasi Penyusutan Aset', 'debit' => 0, 'credit' => $row['amount']]);
            }
            \App\Services\JournalGuard::assertBalanced($journal);

            \Illuminate\Support\Facades\DB::commit();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return back()->withInput()->with('error', 'Gagal memproses penyusutan: ' . $e->getMessage());
        }

        return redirect()->route('client.fixed_assets.index')
            ->with('success', $count . ' aset disusutkan untuk ' . date('F Y', strtotime($month . '-01')) . ' sebesar Rp ' . number_format($total, 2, ',', '.') . ' (jurnal ' . $journal->ref_number . ').');
    }

    // ========== KATEGORI ASET ==========

    public function categories()
    {
        $categories = FixedAssetCategory::where('company_id', auth()->user()->company_id)
            ->withCount('fixedAssets')
            ->get();
        return view('client.fixed_assets.categories', compact('categories'));
    }

    public function storeCategory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'depreciation_method' => 'required|in:straight_line,declining_balance',
            'useful_life_years' => 'required|integer|min:1|max:50',
        ]);

        $validated['company_id'] = auth()->user()->company_id;

        FixedAssetCategory::create($validated);

        return redirect()->route('client.fixed_assets.categories')
            ->with('success', 'Kategori aset berhasil ditambahkan.');
    }

    public function destroyCategory(FixedAssetCategory $category)
    {
        if ($category->company_id !== auth()->user()->company_id) abort(403);
        if ($category->fixedAssets()->count() > 0) {
            return back()->with('error', 'Kategori ini masih memiliki aset terkait dan tidak bisa dihapus.');
        }
        $category->delete();
        return back()->with('success', 'Kategori aset berhasil dihapus.');
    }

    // ========== SUB-MENU LAINNYA (Placeholder) ==========

    public function taxCategories()
    {
        $companyId = auth()->user()->company_id;
        $categories = FixedAssetCategory::where('company_id', $companyId)->orderBy('name')->get();
        return view('client.fixed_assets.tax_categories', compact('categories'));
    }

    public function modifications()
    {
        $companyId = auth()->user()->company_id;
        $assets = FixedAsset::where('company_id', $companyId)->where('status', 'aktif')->with('category')->orderBy('name')->get();
        return view('client.fixed_assets.modifications', compact('assets'));
    }

    public function dispositions()
    {
        $companyId = auth()->user()->company_id;
        $assets = FixedAsset::where('company_id', $companyId)->with('category')->orderBy('name')->get();
        return view('client.fixed_assets.dispositions', compact('assets'));
    }

    public function transfers()
    {
        $companyId = auth()->user()->company_id;
        $assets = FixedAsset::where('company_id', $companyId)->where('status', 'aktif')->with('category')->orderBy('name')->get();
        return view('client.fixed_assets.transfers', compact('assets'));
    }

    public function assetsByLocation()
    {
        $companyId = auth()->user()->company_id;
        $assets = FixedAsset::where('company_id', $companyId)
            ->where('status', 'aktif')
            ->with('category')
            ->get()
            ->groupBy('location');
        return view('client.fixed_assets.by_location', compact('assets'));
    }

    // ========== HELPERS ==========

    private function authorizeCompany(FixedAsset $fixedAsset): void
    {
        if ($fixedAsset->company_id !== auth()->user()->company_id) {
            abort(403, 'Anda tidak memiliki akses ke aset ini.');
        }
    }

    private function generateNextCode(): string
    {
        $lastAsset = FixedAsset::where('company_id', auth()->user()->company_id)
            ->orderByDesc('id')->first();
        if (!$lastAsset) return 'FA-001';
        preg_match('/FA-(\d+)/', $lastAsset->code, $matches);
        $nextNum = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
        return 'FA-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    }
}
