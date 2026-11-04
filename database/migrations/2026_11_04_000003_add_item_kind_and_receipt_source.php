<?php

use App\Support\ItemKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - products.item_kind: jenis barang (Bahan Baku, Bahan Penolong, Barang Jadi, Barang Dagang).
 * - item_receipts.source_invoice_id: penerimaan yang dibuat otomatis dari Faktur Pembelian.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'item_kind')) {
            Schema::table('products', fn (Blueprint $t) => $t->string('item_kind', 20)->nullable()->after('type'));
        }
        if (! Schema::hasColumn('item_receipts', 'source_invoice_id')) {
            Schema::table('item_receipts', function (Blueprint $t) {
                $t->unsignedBigInteger('source_invoice_id')->nullable()->index();
            });
        }

        // Barang lama: jenisnya mengikuti akun persediaannya.
        $accounts = DB::table('accounts')->pluck('name', 'id');
        $business = DB::table('companies')->pluck('business_type', 'id');
        foreach (DB::table('products')->where('type', 'goods')->whereNull('item_kind')->get(['id', 'company_id', 'inventory_account_id']) as $p) {
            $kind = ItemKind::fromAccountName($accounts[$p->inventory_account_id] ?? null)
                ?? (($business[$p->company_id] ?? null) === 'manufaktur' ? ItemKind::FINISHED : ItemKind::MERCH);
            DB::table('products')->where('id', $p->id)->update(['item_kind' => $kind]);
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('item_kind'));
        Schema::table('item_receipts', fn (Blueprint $t) => $t->dropColumn('source_invoice_id'));
    }
};
