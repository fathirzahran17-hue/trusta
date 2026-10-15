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

    public function runDepreciation(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $date = $request->date ?? date('Y-m-t'); // Default end of current month
        
        // Find all active assets that haven't been fully depreciated
        $assets = FixedAsset::where('company_id', $companyId)
            ->where('status', 'aktif')
            ->whereRaw('accumulated_depreciation < (acquisition_cost - salvage_value)')
            ->where(function($query) use ($date) {
                // Not depreciated this month
                $query->whereNull('last_depreciation_date')
                      ->orWhereRaw('DATE_FORMAT(last_depreciation_date, "%Y-%m") < ?', [date('Y-m', strtotime($date))]);
            })
            ->get();

        if ($assets->isEmpty()) {
            return back()->with('info', 'Tidak ada aset yang perlu disusutkan untuk bulan ini.');
        }

        try {
            \Illuminate\Support\Facades\DB::beginTransaction();

            $totalDepreciation = 0;
            $journalDetails = [];

            // Get default accounts for depreciation
            $depExpenseAccount = \App\Models\Account::where('company_id', $companyId)
                ->where('name', 'like', '%Beban Penyusutan%')
                ->where('type', 'expense')
                ->first();
                
            $accDepAccount = \App\Models\Account::where('company_id', $companyId)
                ->where('name', 'like', '%Akumulasi Penyusutan%')
                ->where('type', 'asset')
                ->first();

            if (!$depExpenseAccount || !$accDepAccount) {
                throw new \Exception('Akun "Beban Penyusutan" atau "Akumulasi Penyusutan" tidak ditemukan di Bagan Akun.');
            }

            foreach ($assets as $asset) {
                $depreciableAmount = $asset->acquisition_cost - $asset->salvage_value;
                $monthlyDepreciation = 0;

                if ($asset->depreciation_method === 'straight_line') {
                    $monthlyDepreciation = $depreciableAmount / $asset->useful_life_months;
                } else if ($asset->depreciation_method === 'declining_balance') {
                    // Simplified declining balance: (Book Value) * (Rate)
                    // Assuming standard double declining rate for monthly = (2 / useful_life) 
                    $bookValue = $asset->acquisition_cost - $asset->accumulated_depreciation;
                    $rate = (2 / ($asset->useful_life_months / 12)) / 12; // Monthly rate
                    $monthlyDepreciation = $bookValue * $rate;
                }

                // Check if last depreciation amount would exceed salvage value
                $remainingToDepreciate = $depreciableAmount - $asset->accumulated_depreciation;
                if ($monthlyDepreciation > $remainingToDepreciate) {
                    $monthlyDepreciation = $remainingToDepreciate;
                }

                $totalDepreciation += $monthlyDepreciation;

                // Update asset
                $asset->accumulated_depreciation += $monthlyDepreciation;
                $asset->last_depreciation_date = $date;
                $asset->save();

                $journalDetails[] = [
                    'asset_name' => $asset->name,
                    'amount' => $monthlyDepreciation
                ];
            }

            // Create Journal
            if ($totalDepreciation > 0) {
                $journal = \App\Models\Journal::create([
                    'company_id' => $companyId,
                    'ref_number' => 'JV-DEP-' . date('Ym', strtotime($date)) . '-' . time(),
                    'type' => 'general',
                    'transaction_date' => $date,
                    'description' => 'Penyusutan Aset Tetap Periode ' . date('F Y', strtotime($date)),
                    'status' => 'posted',
                    'created_by' => auth()->id(),
                ]);

                // Debit Expense
                \App\Models\JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $depExpenseAccount->id,
                    'description' => 'Beban Penyusutan Aset',
                    'debit' => $totalDepreciation,
                    'credit' => 0,
                ]);

                // Credit Accumulated Depreciation
                \App\Models\JournalDetail::create([
                    'journal_id' => $journal->id,
                    'account_id' => $accDepAccount->id,
                    'description' => 'Akumulasi Penyusutan Aset',
                    'debit' => 0,
                    'credit' => $totalDepreciation,
                ]);
            }

            \Illuminate\Support\Facades\DB::commit();
            return back()->with('success', count($assets) . ' aset berhasil disusutkan untuk bulan ini sebesar Rp ' . number_format($totalDepreciation, 0, ',', '.'));

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            return back()->with('error', 'Gagal memproses penyusutan: ' . $e->getMessage());
        }
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
