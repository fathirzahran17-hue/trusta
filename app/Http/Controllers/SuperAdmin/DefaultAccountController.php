<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\DefaultAccount;
use Illuminate\Http\Request;

class DefaultAccountController extends Controller
{
    private const TYPES = 'asset,liability,equity,revenue,cost_of_sales,expense,other_income,other_expense';

    public function index(Request $request)
    {
        $tab = array_key_exists($request->query('tab'), \App\Services\CoaTemplate::INDUSTRIES) ? $request->query('tab') : 'all';
        $all = DefaultAccount::with('parent')->orderBy('code')->get();
        $accounts = $tab === 'all' ? $all : $all->filter(fn ($a) => \App\Services\CoaTemplate::applies($a->industries, [$tab]))->values();
        $counts = ['all' => $all->count()];
        foreach (array_keys(\App\Services\CoaTemplate::INDUSTRIES) as $ind) {
            $counts[$ind] = $all->filter(fn ($a) => \App\Services\CoaTemplate::applies($a->industries, [$ind]))->count();
        }
        $parentOptions = $all->where('is_system', true)->values();

        return view('superadmin.default-accounts.index', compact('accounts', 'parentOptions', 'tab', 'counts'));
    }

    private function validated(Request $request, ?DefaultAccount $acc = null): array
    {
        $v = $request->validate([
            'code' => 'required|string|max:20|unique:default_accounts,code' . ($acc ? ',' . $acc->id : ''),
            'name' => 'required|string|max:255',
            'type' => 'required|in:' . self::TYPES,
            'normal_balance' => 'required|in:debit,credit',
            'parent_id' => 'nullable|exists:default_accounts,id',
            'industries' => 'nullable|array',
            'industries.*' => 'in:' . implode(',', array_keys(\App\Services\CoaTemplate::INDUSTRIES)),
        ]);
        $ind = array_values(array_unique($v['industries'] ?? []));
        // semua bidang dicentang = berlaku umum (kosong)
        $v['industries'] = (! $ind || count($ind) === count(\App\Services\CoaTemplate::INDUSTRIES)) ? null : implode(',', $ind);
        $v['is_system'] = $request->boolean('is_system');

        return $v;
    }

    public function store(Request $request)
    {
        $v = $this->validated($request);
        $v['sort_order'] = (DefaultAccount::max('sort_order') ?? 0) + 1;
        DefaultAccount::create($v);

        return back()->with('success', "Akun template \"{$v['name']}\" ditambahkan.");
    }

    public function update(Request $request, DefaultAccount $defaultAccount)
    {
        $v = $this->validated($request, $defaultAccount);
        if ((int) ($v['parent_id'] ?? 0) === $defaultAccount->id) {
            return back()->with('error', 'Akun tidak boleh menjadi induk dirinya sendiri.');
        }
        $defaultAccount->update($v);

        return back()->with('success', "Akun template \"{$v['name']}\" diperbarui.");
    }

    public function destroy(DefaultAccount $defaultAccount)
    {
        $name = $defaultAccount->name;
        $defaultAccount->children()->delete();
        $defaultAccount->delete();

        return back()->with('success', "Akun template \"{$name}\" beserta sub-akunnya dihapus.");
    }

    public function resetToDefault()
    {
        \App\Services\CoaTemplate::install();

        return redirect()->route('superadmin.default-accounts.index')
            ->with('success', 'Template dikembalikan ke CoA standar TRUSTA (Jasa, Dagang, Manufaktur).');
    }
}
