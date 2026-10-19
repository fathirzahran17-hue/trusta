<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Customer;
use App\Models\InvoicePayment;
use App\Models\PurchaseInvoice;
use App\Models\SalesInvoice;
use App\Models\Supplier;
use App\Services\InvoicePaymentService;
use Illuminate\Http\Request;

/**
 * Pelunasan faktur: halaman Penerimaan Penjualan / Pembayaran Pembelian (bayar per pelanggan/pemasok,
 * langsung memilih faktur yang belum lunas) dan halaman Status Pelunasan (pilah lunas vs belum).
 */
class SettlementController extends Controller
{
    private function cid(): int
    {
        return (int) auth()->user()->company_id;
    }

    private function cfg(string $kind): array
    {
        return $kind === 'sales'
            ? ['model' => SalesInvoice::class, 'party' => 'customer', 'partyCol' => 'customer_id', 'partyModel' => Customer::class,
               'partyLabel' => 'Pelanggan', 'fk' => 'sales_invoice_id', 'route' => 'client.sales.receipts', 'listRoute' => 'client.sales.settlement',
               'invRoute' => 'client.sales-invoices.show']
            : ['model' => PurchaseInvoice::class, 'party' => 'supplier', 'partyCol' => 'supplier_id', 'partyModel' => Supplier::class,
               'partyLabel' => 'Pemasok', 'fk' => 'purchase_invoice_id', 'route' => 'client.purchases.payments', 'listRoute' => 'client.purchases.settlement',
               'invRoute' => 'client.purchase-invoices.show'];
    }

    // ---- halaman bayar/terima -------------------------------------------------

    public function salesReceipts(Request $r)    { return $this->payPage($r, 'sales'); }
    public function purchasePayments(Request $r) { return $this->payPage($r, 'purchase'); }

    private function payPage(Request $r, string $kind)
    {
        $c = $this->cfg($kind);
        $cid = $this->cid();

        $open = $c['model']::with($c['party'])
            ->where('company_id', $cid)->whereIn('status', ['unpaid', 'partial'])->where('amount_due', '>', 0)
            ->orderBy('due_date')->orderBy('id')->get();

        // pelanggan/pemasok yang punya tagihan terbuka beserta fakturnya (untuk form interaktif)
        $parties = $open->groupBy($c['partyCol'])->map(function ($rows, $pid) use ($c) {
            return [
                'id' => (int) $pid,
                'name' => optional($rows->first()->{$c['party']})->name ?? '(tanpa nama)',
                'due' => round($rows->sum('amount_due'), 2),
                'invoices' => $rows->map(fn ($i) => [
                    'id' => $i->id,
                    'number' => $i->invoice_number,
                    'ref' => $i->supplier_invoice_no ?? null,
                    'date' => optional($i->invoice_date)->format('d M Y'),
                    'due_date' => optional($i->due_date)->format('d M Y'),
                    'overdue' => $i->due_date && $i->due_date->lt(today()),
                    'total' => (float) $i->total,
                    'paid' => (float) $i->amount_paid,
                    'due' => (float) $i->amount_due,
                ])->values(),
            ];
        })->sortBy('name')->values();

        $cashAccounts = Account::where('company_id', $cid)->where('is_cash_bank', true)->orderBy('code')->get()->reject(fn ($a) => $a->is_header)->values();

        // riwayat: kelompokkan per jurnal
        $rows = InvoicePayment::where('company_id', $cid)->whereNotNull($c['fk'])
            ->with(['account', $kind === 'sales' ? 'salesInvoice.customer' : 'purchaseInvoice.supplier'])
            ->orderByDesc('payment_date')->orderByDesc('id')->limit(300)->get();
        $history = $rows->groupBy(fn ($p) => $p->journal_id ?: 'p' . $p->id)->take(40)->map(function ($g) use ($kind) {
            $f = $g->first();
            $inv = $kind === 'sales' ? $f->salesInvoice : $f->purchaseInvoice;
            return (object) [
                'first' => $f,
                'number' => $f->payment_number,
                'date' => $f->payment_date,
                'account' => optional($f->account)->name,
                'party' => $kind === 'sales' ? optional(optional($inv)->customer)->name : optional(optional($inv)->supplier)->name,
                'invoices' => $g->map(fn ($p) => optional($kind === 'sales' ? $p->salesInvoice : $p->purchaseInvoice)->invoice_number)->filter()->unique()->implode(', '),
                'amount' => $g->sum('amount'),
                'voided' => (bool) $f->voided_at,
                'journal_id' => $f->journal_id,
                'notes' => $f->notes,
            ];
        })->values();

        $stats = [
            'open' => round($open->sum('amount_due'), 2),
            'openCount' => $open->count(),
            'overdue' => round($open->filter(fn ($i) => $i->due_date && $i->due_date->lt(today()))->sum('amount_due'), 2),
            'overdueCount' => $open->filter(fn ($i) => $i->due_date && $i->due_date->lt(today()))->count(),
            'month' => round(InvoicePayment::where('company_id', $cid)->whereNotNull($c['fk'])->whereNull('voided_at')
                ->whereYear('payment_date', now()->year)->whereMonth('payment_date', now()->month)->sum('amount'), 2),
        ];

        return view('client.settlement.pay', [
            'kind' => $kind, 'parties' => $parties, 'cashAccounts' => $cashAccounts, 'history' => $history, 'stats' => $stats,
            'preInvoice' => (int) $r->query('invoice'), 'preParty' => (int) $r->query('party'),
        ]);
    }

    public function store(Request $r, string $kind)
    {
        $c = $this->cfg($kind);
        $d = $r->validate([
            'party_id' => 'required|integer',
            'account_id' => 'required|integer',
            'payment_date' => 'required|date',
            'notes' => 'nullable|string|max:255',
            'alloc' => 'required|array',
            'alloc.*' => 'nullable|numeric|min:0',
        ], ['alloc.required' => 'Pilih minimal satu faktur yang dibayar.']);

        try {
            $pays = InvoicePaymentService::payMany($kind, $this->cid(), (int) $d['party_id'], $d['alloc'], (int) $d['account_id'], $d['payment_date'], $d['notes'] ?? null, auth()->id());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $total = array_sum(array_map(fn ($p) => (float) $p->amount, $pays));
        return redirect()->route($c['route'])->with('success', ($kind === 'sales' ? 'Penerimaan ' : 'Pembayaran ') . $pays[0]->payment_number . ' sebesar Rp ' . number_format($total, 0, ',', '.') . ' untuk ' . count($pays) . ' faktur berhasil dicatat.');
    }

    public function storeSales(Request $r)    { return $this->store($r, 'sales'); }
    public function storePurchase(Request $r) { return $this->store($r, 'purchase'); }

    public function voidPayment(InvoicePayment $payment)
    {
        abort_if((int) $payment->company_id !== $this->cid(), 403);
        try {
            InvoicePaymentService::voidPayment($payment, auth()->id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran ' . $payment->payment_number . ' dibatalkan. Jurnal balik dibuat dan faktur kembali ke status sebelumnya.');
    }

    // ---- status pelunasan -----------------------------------------------------

    public function salesSettlement(Request $r)    { return $this->settlement($r, 'sales'); }
    public function purchaseSettlement(Request $r) { return $this->settlement($r, 'purchase'); }

    private function settlement(Request $r, string $kind)
    {
        $c = $this->cfg($kind);
        $cid = $this->cid();
        $tab = in_array($r->query('tab'), ['unpaid', 'overdue', 'paid', 'void', 'all']) ? $r->query('tab') : 'unpaid';
        $q = trim((string) $r->query('q', ''));
        $partyId = (int) $r->query('party');

        $base = $c['model']::with($c['party'])->where('company_id', $cid)
            ->when($partyId, fn ($x) => $x->where($c['partyCol'], $partyId))
            ->when($q !== '', fn ($x) => $x->where(function ($w) use ($q, $c) {
                $w->where('invoice_number', 'like', "%$q%")
                  ->orWhereHas($c['party'], fn ($p) => $p->where('name', 'like', "%$q%"));
            }));

        $count = fn ($f) => $f(clone $base)->count();
        $tabs = [
            'unpaid' => ['Belum Lunas', $count(fn ($x) => $x->whereIn('status', ['unpaid', 'partial']))],
            'overdue' => ['Jatuh Tempo', $count(fn ($x) => $x->whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', today()))],
            'paid' => ['Lunas', $count(fn ($x) => $x->where('status', 'paid'))],
            'void' => ['Dibatalkan', $count(fn ($x) => $x->where('status', 'void'))],
            'all' => ['Semua', $count(fn ($x) => $x)],
        ];

        $list = clone $base;
        match ($tab) {
            'unpaid' => $list->whereIn('status', ['unpaid', 'partial'])->orderBy('due_date'),
            'overdue' => $list->whereIn('status', ['unpaid', 'partial'])->whereDate('due_date', '<', today())->orderBy('due_date'),
            'paid' => $list->where('status', 'paid')->orderByDesc('invoice_date'),
            'void' => $list->where('status', 'void')->orderByDesc('invoice_date'),
            default => $list->orderByDesc('invoice_date'),
        };
        $invoices = $list->orderByDesc('id')->paginate(25)->withQueryString();

        $openAll = $c['model']::where('company_id', $cid)->whereIn('status', ['unpaid', 'partial']);
        $stats = [
            'open' => round((float) (clone $openAll)->sum('amount_due'), 2),
            'overdue' => round((float) (clone $openAll)->whereDate('due_date', '<', today())->sum('amount_due'), 2),
            'paid' => round((float) $c['model']::where('company_id', $cid)->where('status', 'paid')->sum('total'), 2),
        ];
        $parties = $c['partyModel']::where('company_id', $cid)->orderBy('name')->get(['id', 'name']);

        return view('client.settlement.list', compact('kind', 'invoices', 'tabs', 'tab', 'q', 'partyId', 'parties', 'stats'));
    }
}
