<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PPN lengkap: status kena PPN per barang dan per baris, DPP dan nomor Faktur Pajak per dokumen, serta PPN uang muka.
 * Isi data lama dibuat sama dengan perhitungan lama, jadi angka dokumen yang sudah ada tidak berubah.
 */
return new class extends Migration
{
    private array $lineTables = ['sales_invoice_items', 'purchase_invoice_items', 'sales_order_details', 'purchase_order_items', 'sales_return_items', 'purchase_return_items'];

    public function up(): void
    {
        // Kolom ini belum pernah dipakai: PPN yang dipilih di dokumen selama ini berlaku untuk semua baris.
        Schema::table('products', fn (Blueprint $t) => $t->boolean('is_taxable')->default(true)->change());
        DB::table('products')->update(['is_taxable' => true]);

        foreach ($this->lineTables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->boolean('is_taxable')->default(true));
        }

        foreach (['sales_invoices', 'purchase_invoices'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('tax_invoice_number', 32)->nullable()->after('invoice_number');
                $t->decimal('tax_base', 15, 2)->default(0)->after('tax_percent');
            });
            DB::table($table)->where('tax_amount', '>', 0)->update(['tax_base' => DB::raw('subtotal - discount_amount')]);
        }

        Schema::table('purchase_orders', fn (Blueprint $t) => $t->foreignId('tax_id')->nullable()->constrained()->nullOnDelete());

        foreach (['sales_returns' => ['sales_invoices', 'sales_invoice_id'], 'purchase_returns' => ['purchase_invoices', 'purchase_invoice_id']] as $table => [$invoices, $fk]) {
            Schema::table($table, function (Blueprint $t) {
                $t->decimal('tax_base', 15, 2)->default(0)->after('total');
                $t->decimal('tax_amount', 15, 2)->default(0)->after('tax_base');
            });
            // Retur lama: total = DPP + PPN dengan tarif faktur asalnya
            $rows = DB::table($table)->join($invoices, "$invoices.id", '=', "$table.$fk")->where("$invoices.tax_percent", '>', 0)
                ->select("$table.id", "$table.total", "$invoices.tax_percent")->get();
            foreach ($rows as $r) {
                $base = round((float) $r->total / (1 + (float) $r->tax_percent / 100), 2);
                DB::table($table)->where('id', $r->id)->update(['tax_base' => $base, 'tax_amount' => round((float) $r->total - $base, 2)]);
            }
        }

        Schema::table('down_payments', function (Blueprint $t) {
            $t->foreignId('tax_id')->nullable()->after('amount')->constrained()->nullOnDelete();
            $t->decimal('tax_percent', 5, 2)->default(0)->after('tax_id');
            $t->decimal('tax_base', 15, 2)->default(0)->after('tax_percent');
            $t->decimal('tax_amount', 15, 2)->default(0)->after('tax_base');
            $t->string('tax_invoice_number', 32)->nullable()->after('tax_amount');
        });
        Schema::table('down_payment_applications', fn (Blueprint $t) => $t->decimal('tax_amount', 15, 2)->default(0)->after('amount'));
    }

    public function down(): void
    {
        Schema::table('down_payment_applications', fn (Blueprint $t) => $t->dropColumn('tax_amount'));
        Schema::table('down_payments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('tax_id');
            $t->dropColumn(['tax_percent', 'tax_base', 'tax_amount', 'tax_invoice_number']);
        });
        foreach (['sales_returns', 'purchase_returns'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['tax_base', 'tax_amount']));
        }
        Schema::table('purchase_orders', fn (Blueprint $t) => $t->dropConstrainedForeignId('tax_id'));
        foreach (['sales_invoices', 'purchase_invoices'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn(['tax_invoice_number', 'tax_base']));
        }
        foreach ($this->lineTables as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('is_taxable'));
        }
        Schema::table('products', fn (Blueprint $t) => $t->boolean('is_taxable')->default(false)->change());
    }
};
