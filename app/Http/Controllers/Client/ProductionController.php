<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Bom;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Services\ProductionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Produksi / perakitan: Resep (BOM) dan Perintah Produksi. */
class ProductionController extends Controller
{
    private function cid(): int
    {
        return (int) auth()->user()->company_id;
    }

    private function goods()
    {
        return Product::where('company_id', $this->cid())->where('type', 'goods')->where('is_active', true)->orderBy('name')->get();
    }

    // ===== Resep (BOM) =====
    public function boms()
    {
        $boms = Bom::with(['product', 'items.product'])->where('company_id', $this->cid())->orderBy('name')->get();

        return view('client.production.boms', ['boms' => $boms, 'products' => $this->goods(), 'edit' => null]);
    }

    public function editBom(Bom $bom)
    {
        abort_if($bom->company_id !== $this->cid(), 404);
        $boms = Bom::with(['product', 'items.product'])->where('company_id', $this->cid())->orderBy('name')->get();

        return view('client.production.boms', ['boms' => $boms, 'products' => $this->goods(), 'edit' => $bom->load('items')]);
    }

    private function saveBom(Request $request, ?Bom $bom)
    {
        $d = $request->validate([
            'product_id' => 'required|integer',
            'name' => 'nullable|string|max:150',
            'output_qty' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:255',
            'components' => 'required|array|min:1',
            'components.*.product_id' => 'nullable|integer',
            'components.*.quantity' => 'nullable|numeric|min:0',
        ]);
        $goods = $this->goods()->keyBy('id');
        $product = $goods[$d['product_id']] ?? null;
        if (! $product) return back()->withInput()->with('error', 'Pilih barang jadi yang valid.');
        $rows = collect($d['components'])->filter(fn ($c) => ! empty($c['product_id']) && (float) ($c['quantity'] ?? 0) > 0)->values();
        if ($rows->isEmpty()) return back()->withInput()->with('error', 'Isi minimal satu bahan.');
        foreach ($rows as $r) {
            if (! isset($goods[$r['product_id']])) return back()->withInput()->with('error', 'Bahan harus barang bersetok milik perusahaan.');
            if ((int) $r['product_id'] === $product->id) return back()->withInput()->with('error', 'Barang jadi tidak boleh menjadi bahannya sendiri.');
        }
        DB::transaction(function () use ($d, $bom, $product, $rows) {
            $data = ['company_id' => $this->cid(), 'product_id' => $product->id, 'name' => ($d['name'] ?? null) ?: 'Resep ' . $product->name,
                'output_qty' => $d['output_qty'], 'notes' => $d['notes'] ?? null, 'is_active' => true];
            $bom ? $bom->update($data) : ($bom = Bom::create($data));
            $bom->items()->delete();
            foreach ($rows as $r) {
                $bom->items()->create(['product_id' => (int) $r['product_id'], 'quantity' => (float) $r['quantity']]);
            }
        });

        return redirect()->route('client.production.boms')->with('success', 'Resep produksi disimpan.');
    }

    public function storeBom(Request $request) { return $this->saveBom($request, null); }

    public function updateBom(Request $request, Bom $bom)
    {
        abort_if($bom->company_id !== $this->cid(), 404);

        return $this->saveBom($request, $bom);
    }

    public function destroyBom(Bom $bom)
    {
        abort_if($bom->company_id !== $this->cid(), 404);
        $bom->items()->delete();
        $bom->delete();

        return back()->with('success', 'Resep dihapus. Perintah produksi yang sudah jadi tidak berubah.');
    }

    // ===== Perintah Produksi =====
    public function orders()
    {
        $cid = $this->cid();
        $orders = ProductionOrder::with(['product', 'journal'])->where('company_id', $cid)->orderByDesc('date')->orderByDesc('id')->paginate(20);
        $boms = Bom::with('items')->where('company_id', $cid)->where('is_active', true)->orderBy('name')->get();
        $products = $this->goods();
        $costAccounts = ProductionService::costAccounts($cid);
        $bomJson = $boms->map(fn ($b) => ['id' => $b->id, 'product_id' => $b->product_id, 'name' => $b->name, 'output' => (float) $b->output_qty,
            'items' => $b->items->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => (float) $i->quantity])->values()])->values();
        $priceJson = $products->mapWithKeys(fn ($p) => [$p->id => ['cost' => (float) $p->buy_price, 'stock' => (float) $p->stock, 'unit' => $p->unit]]);

        return view('client.production.orders', compact('orders', 'boms', 'products', 'costAccounts', 'bomJson', 'priceJson'));
    }

    public function storeOrder(Request $request)
    {
        \App\Support\Money::normalize($request, ['costs.*.amount']);
        $d = $request->validate([
            'date' => 'required|date',
            'product_id' => 'required|integer',
            'bom_id' => 'nullable|integer',
            'quantity' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:255',
            'components' => 'required|array',
            'components.*.product_id' => 'nullable|integer',
            'components.*.quantity' => 'nullable|numeric|min:0',
            'costs' => 'nullable|array',
            'costs.*.account_id' => 'nullable|integer',
            'costs.*.amount' => 'nullable|numeric|min:0',
            'costs.*.description' => 'nullable|string|max:150',
        ]);
        if (! empty($d['bom_id']) && ! Bom::where('company_id', $this->cid())->whereKey($d['bom_id'])->exists()) {
            $d['bom_id'] = null;
        }
        try {
            $order = ProductionService::run($this->cid(), $d, auth()->id());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('client.production.orders.show', $order)->with('success', 'Produksi ' . $order->number . ' dicatat: stok bahan berkurang, barang jadi bertambah, jurnal dibuat.');
    }

    public function showOrder(ProductionOrder $order)
    {
        abort_if($order->company_id !== $this->cid(), 404);
        $order->load(['product', 'items.product', 'costs.account', 'journal']);
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 2, ',', '.');
        $lines = $order->items->map(fn ($i) => ['name' => 'Bahan: ' . (optional($i->product)->name ?? '-'), 'description' => 'Rp ' . number_format((float) $i->unit_cost, 2, ',', '.') . ' / ' . (optional($i->product)->unit ?: 'unit') . ' = ' . $rp($i->total), 'quantity' => (float) $i->quantity])->all();
        foreach ($order->costs as $c) {
            $lines[] = ['name' => 'Biaya: ' . (optional($c->account)->name ?? '-'), 'description' => trim(($c->description ? $c->description . ' — ' : '') . $rp($c->amount)), 'quantity' => 1];
        }

        return view('client.documents.show', [
            'title' => 'Perintah Produksi',
            'number' => $order->number,
            'backUrl' => route('client.production.orders'),
            'backLabel' => 'Daftar Produksi',
            'meta' => [
                'Tanggal' => $order->date->format('d M Y'),
                'Barang Jadi' => optional($order->product)->name . ' × ' . (float) $order->quantity . ' ' . optional($order->product)->unit,
                'Biaya Bahan' => $rp($order->material_cost),
                'Biaya Tambahan' => $rp($order->extra_cost),
                'Total Biaya Produksi' => $rp($order->total_cost),
                'Harga Pokok / Unit' => 'Rp ' . number_format((float) $order->unit_cost, 2, ',', '.'),
                'Status' => $order->voided_at ? 'Dibatalkan' : 'Selesai',
                'Catatan' => $order->notes,
            ],
            'lines' => $lines,
            'showPrices' => false,
            'total' => null,
            'actions' => $order->voided_at ? [] : [
                ['label' => 'Batalkan Produksi', 'url' => route('client.production.orders.cancel', $order), 'method' => 'POST', 'style' => 'danger',
                    'confirm' => 'Batalkan produksi ' . $order->number . '? Barang jadi ditarik, bahan dikembalikan ke stok, dan dibuat jurnal balik bertanggal hari ini.'],
            ],
            'groups' => [['label' => 'Jurnal Akuntansi', 'items' => $order->journal ? [['label' => $order->journal->ref_number, 'url' => route('client.journals.show', $order->journal), 'meta' => $order->journal->transaction_date->format('d M Y')]] : []]],
        ]);
    }

    public function cancelOrder(ProductionOrder $order)
    {
        abort_if($order->company_id !== $this->cid(), 404);
        try {
            ProductionService::cancel($order, auth()->id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Produksi ' . $order->number . ' dibatalkan; stok dikembalikan dan jurnal balik dibuat.');
    }
}
