<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_return_items', 'account_id')) {
            Schema::table('purchase_return_items', fn (Blueprint $t) => $t->unsignedBigInteger('account_id')->nullable()->after('product_id'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_return_items', 'account_id')) {
            Schema::table('purchase_return_items', fn (Blueprint $t) => $t->dropColumn('account_id'));
        }
    }
};
