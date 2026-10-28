<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paket langganan yang bisa diatur super admin (harga bulanan & tahunan), pembayaran via transfer + bukti
 * yang dikonfirmasi admin, dan pengaturan platform (rekening tujuan, WhatsApp, trial otomatis).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $t) {
            if (! Schema::hasColumn('plans', 'price_yearly')) $t->decimal('price_yearly', 14, 2)->nullable();
            if (! Schema::hasColumn('plans', 'description')) $t->string('description')->nullable();
            if (! Schema::hasColumn('plans', 'features')) $t->text('features')->nullable();
            if (! Schema::hasColumn('plans', 'sort_order')) $t->integer('sort_order')->default(0);
            if (! Schema::hasColumn('plans', 'is_public')) $t->boolean('is_public')->default(true);
        });
        Schema::table('subscriptions', function (Blueprint $t) {
            if (! Schema::hasColumn('subscriptions', 'billing_cycle')) $t->string('billing_cycle', 10)->nullable();
            if (! Schema::hasColumn('subscriptions', 'payment_id')) $t->unsignedBigInteger('payment_id')->nullable();
        });
        if (! Schema::hasTable('subscription_payments')) {
            Schema::create('subscription_payments', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('company_id')->index();
                $t->unsignedBigInteger('plan_id');
                $t->string('billing_cycle', 10); // monthly | yearly
                $t->unsignedSmallInteger('months');
                $t->decimal('amount', 14, 2);
                $t->string('payer_name')->nullable();
                $t->string('payer_bank')->nullable();
                $t->date('paid_at')->nullable();
                $t->string('proof_path')->nullable();
                $t->string('notes')->nullable();
                $t->string('status', 12)->default('pending'); // pending | approved | rejected
                $t->string('admin_note')->nullable();
                $t->unsignedBigInteger('reviewed_by')->nullable();
                $t->timestamp('reviewed_at')->nullable();
                $t->unsignedBigInteger('subscription_id')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('app_settings')) {
            Schema::create('app_settings', function (Blueprint $t) {
                $t->id();
                $t->string('key', 80)->unique();
                $t->text('value')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('subscription_payments');
        Schema::table('subscriptions', fn (Blueprint $t) => $t->dropColumn(['billing_cycle', 'payment_id']));
        Schema::table('plans', fn (Blueprint $t) => $t->dropColumn(['price_yearly', 'description', 'features', 'sort_order', 'is_public']));
    }
};
