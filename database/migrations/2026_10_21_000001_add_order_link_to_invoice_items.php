<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Satu faktur boleh menagih beberapa pesanan: tiap baris faktur mencatat pesanan asalnya. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_order_id')->nullable()->after('sales_invoice_id')->index();
        });
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('po_id')->nullable()->after('purchase_invoice_id')->index();
        });

        // faktur lama: semua barisnya berasal dari pesanan di header faktur
        DB::table('sales_invoice_items')->orderBy('id')->chunkById(500, function ($items) {
            $map = DB::table('sales_invoices')->whereIn('id', $items->pluck('sales_invoice_id'))->pluck('sales_order_id', 'id');
            foreach ($items as $i) {
                if (!empty($map[$i->sales_invoice_id])) {
                    DB::table('sales_invoice_items')->where('id', $i->id)->update(['sales_order_id' => $map[$i->sales_invoice_id]]);
                }
            }
        });
        DB::table('purchase_invoice_items')->orderBy('id')->chunkById(500, function ($items) {
            $map = DB::table('purchase_invoices')->whereIn('id', $items->pluck('purchase_invoice_id'))->pluck('po_id', 'id');
            foreach ($items as $i) {
                if (!empty($map[$i->purchase_invoice_id])) {
                    DB::table('purchase_invoice_items')->where('id', $i->id)->update(['po_id' => $map[$i->purchase_invoice_id]]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_items', fn (Blueprint $t) => $t->dropColumn('sales_order_id'));
        Schema::table('purchase_invoice_items', fn (Blueprint $t) => $t->dropColumn('po_id'));
    }
};
