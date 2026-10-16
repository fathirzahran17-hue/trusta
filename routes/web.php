<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboard;
use App\Http\Controllers\Client\DashboardController as ClientDashboard;
use Illuminate\Support\Facades\Route;

// Redirect root ke login jika belum auth, atau ke dashboard masing-masing jika sudah
Route::get('/', function () {
    if (auth()->check()) {
        if (auth()->user()->isSuperAdmin()) {
            return redirect()->route('superadmin.dashboard');
        }
        return redirect()->route('client.dashboard');
    }
    return redirect()->route('login');
});

// Temporary route to run migrations safely on hosting
Route::get('/update-db', function() {
    \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
    \Illuminate\Support\Facades\Artisan::call('view:clear');
    \Illuminate\Support\Facades\Artisan::call('cache:clear');
    
    // Auto-update existing cash/bank sub-accounts
    \App\Models\Account::where('type', 'asset')
        ->where(function($q) { 
            $q->where('name', 'like', '%Kas%')->orWhere('name', 'like', '%Bank%')->orWhere('name', 'like', '%Cash%'); 
        })->get()->each(function($acc) { 
            if(!$acc->is_header) { 
                $acc->update(['is_cash_bank' => true]); 
            } 
        });

    return "Database updated successfully! <a href='/'>Return to Home</a>";
});

// Routing bawaan Breeze diubah fungsinya sebagai dispatcher
Route::get('/dashboard', function () {
    if (auth()->user()->isSuperAdmin()) {
        return redirect()->route('superadmin.dashboard');
    }
    return redirect()->route('client.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Google Auth Routes
Route::get('auth/google', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'redirect'])->name('google.login');
Route::get('auth/google/callback', [\App\Http\Controllers\Auth\GoogleAuthController::class, 'callback'])->name('google.callback');


// ==========================================
// PANEL SUPER ADMIN
// ==========================================
Route::middleware(['auth', 'superadmin'])->prefix('superadmin')->name('superadmin.')->group(function () {
    Route::get('/dashboard', [SuperAdminDashboard::class, 'index'])->name('dashboard');
    Route::get('/clients', [\App\Http\Controllers\SuperAdmin\ClientController::class, 'index'])->name('clients.index');
    Route::put('/clients/{company}/subscription', [\App\Http\Controllers\SuperAdmin\ClientController::class, 'updateSubscription'])->name('clients.subscription.update');
    Route::post('/clients/{company}/grant-premium', [\App\Http\Controllers\SuperAdmin\ClientController::class, 'grantPremium'])->name('clients.grant_premium');
    Route::post('/clients/{company}/revoke-premium', [\App\Http\Controllers\SuperAdmin\ClientController::class, 'revokePremium'])->name('clients.revoke_premium');
    Route::post('/clients/{company}/reset-password', [\App\Http\Controllers\SuperAdmin\ClientController::class, 'resetPassword'])->name('clients.reset_password');
    Route::delete('/clients/{company}', [\App\Http\Controllers\SuperAdmin\ClientController::class, 'destroy'])->name('clients.destroy');

    // Template Akun Default (CoA)
    Route::get('/default-accounts', [\App\Http\Controllers\SuperAdmin\DefaultAccountController::class, 'index'])->name('default-accounts.index');
    Route::post('/default-accounts', [\App\Http\Controllers\SuperAdmin\DefaultAccountController::class, 'store'])->name('default-accounts.store');
    Route::put('/default-accounts/{defaultAccount}', [\App\Http\Controllers\SuperAdmin\DefaultAccountController::class, 'update'])->name('default-accounts.update');
    Route::delete('/default-accounts/{defaultAccount}', [\App\Http\Controllers\SuperAdmin\DefaultAccountController::class, 'destroy'])->name('default-accounts.destroy');
    Route::post('/default-accounts/reset', [\App\Http\Controllers\SuperAdmin\DefaultAccountController::class, 'resetToDefault'])->name('default-accounts.reset');
});


// ==========================================
// PANEL CLIENT (AKUNTANSI)
// ==========================================
Route::middleware(['auth', 'client', 'premium'])->prefix('client')->name('client.')->group(function () {
    Route::get('/dashboard', [ClientDashboard::class, 'index'])->name('dashboard');
    
    // Pengaturan & Perusahaan (Khusus Admin Klien)
    Route::middleware('client_admin')->group(function () {
        Route::get('settings/preferences', [\App\Http\Controllers\Client\PreferenceController::class, 'index'])->name('settings.preferences');
        Route::post('settings/preferences', [\App\Http\Controllers\Client\PreferenceController::class, 'update'])->name('settings.preferences.update');

        // Perusahaan
        Route::get('company/activity-logs', [\App\Http\Controllers\Client\ActivityLogController::class, 'index'])->name('activity_logs.index');
        Route::get('company/payment-terms', [\App\Http\Controllers\Client\CompanySettingController::class, 'paymentTerms'])->name('settings.payment_terms');
        Route::post('company/payment-terms', [\App\Http\Controllers\Client\CompanySettingController::class, 'storePaymentTerm'])->name('settings.payment_terms.store');
        Route::delete('company/payment-terms/{paymentTerm}', [\App\Http\Controllers\Client\CompanySettingController::class, 'destroyPaymentTerm'])->name('settings.payment_terms.destroy');
        Route::resource('users', \App\Http\Controllers\Client\CompanyUserController::class)->except(['show']);
        Route::get('company/month-end', [\App\Http\Controllers\Client\CompanySettingController::class, 'monthEnd'])->name('company.month_end');
        Route::post('company/month-end', [\App\Http\Controllers\Client\CompanySettingController::class, 'processMonthEnd'])->name('company.process_month_end');
        Route::get('accounts/beginning-balance', [\App\Http\Controllers\Client\AccountController::class, 'beginningBalance'])->name('accounts.beginning_balance');
        Route::post('accounts/beginning-balance', [\App\Http\Controllers\Client\AccountController::class, 'storeBeginningBalance'])->name('accounts.store_beginning_balance');
    });
    
    // Akun & Buku Besar
    Route::get('accounts/history/{account?}', [\App\Http\Controllers\Client\AccountController::class, 'history'])->name('accounts.history');
    Route::resource('accounts', \App\Http\Controllers\Client\AccountController::class);
    Route::resource('journals', \App\Http\Controllers\Client\JournalController::class);
    Route::resource('project-jobs', \App\Http\Controllers\Client\ProjectJobController::class);
    
    // Pelanggan & Pemasok
    Route::get('customers/categories', [\App\Http\Controllers\Client\CustomerController::class, 'categories'])->name('customers.categories');
    Route::post('customers/categories', [\App\Http\Controllers\Client\CustomerController::class, 'storeCategory'])->name('customers.categories.store');
    Route::delete('customers/categories/{customerCategory}', [\App\Http\Controllers\Client\CustomerController::class, 'destroyCategory'])->name('customers.categories.destroy');
    Route::resource('customers', \App\Http\Controllers\Client\CustomerController::class);
    
    Route::get('suppliers/categories', [\App\Http\Controllers\Client\SupplierController::class, 'categories'])->name('suppliers.categories');
    Route::post('suppliers/categories', [\App\Http\Controllers\Client\SupplierController::class, 'storeCategory'])->name('suppliers.categories.store');
    Route::delete('suppliers/categories/{supplierCategory}', [\App\Http\Controllers\Client\SupplierController::class, 'destroyCategory'])->name('suppliers.categories.destroy');
    Route::resource('suppliers', \App\Http\Controllers\Client\SupplierController::class);

    // Pajak
    Route::resource('taxes', \App\Http\Controllers\Client\TaxController::class)->except(['show']);

    // Transaksi / Kas & Bank
    Route::get('cash-bank', [\App\Http\Controllers\Client\CashBankController::class, 'index'])->name('cash-bank.index');
    Route::get('cash-bank/payments', [\App\Http\Controllers\Client\CashBankController::class, 'payments'])->name('cash-bank.payments');
    Route::post('cash-bank/payments', [\App\Http\Controllers\Client\CashBankController::class, 'storePayments'])->name('cash-bank.payments.store');
    Route::get('cash-bank/receipts', [\App\Http\Controllers\Client\CashBankController::class, 'receipts'])->name('cash-bank.receipts');
    Route::post('cash-bank/receipts', [\App\Http\Controllers\Client\CashBankController::class, 'storeReceipts'])->name('cash-bank.receipts.store');
    Route::get('cash-bank/transfers', [\App\Http\Controllers\Client\CashBankController::class, 'transfers'])->name('cash-bank.transfers');
    Route::post('cash-bank/transfers', [\App\Http\Controllers\Client\CashBankController::class, 'storeTransfers'])->name('cash-bank.transfers.store');
    Route::get('cash-bank/statements', [\App\Http\Controllers\Client\CashBankController::class, 'statements'])->name('cash-bank.statements');

    Route::get('cash-bank/reconciliation', [\App\Http\Controllers\Client\CashBankController::class, 'reconciliation'])->name('cash-bank.reconciliation');
    Route::get('cash-bank/{account}', [\App\Http\Controllers\Client\CashBankController::class, 'show'])->name('cash-bank.show');
    Route::post('cash-bank/{account}/transaction', [\App\Http\Controllers\Client\CashBankController::class, 'storeTransaction'])->name('cash-bank.transaction');
    
    // Aset Tetap
    Route::get('fixed-assets', [\App\Http\Controllers\Client\FixedAssetController::class, 'index'])->name('fixed_assets.index');
    Route::get('fixed-assets/create', [\App\Http\Controllers\Client\FixedAssetController::class, 'create'])->name('fixed_assets.create');
    Route::post('fixed-assets', [\App\Http\Controllers\Client\FixedAssetController::class, 'store'])->name('fixed_assets.store');
    Route::get('fixed-assets/{fixedAsset}/edit', [\App\Http\Controllers\Client\FixedAssetController::class, 'edit'])->name('fixed_assets.edit');
    Route::put('fixed-assets/{fixedAsset}', [\App\Http\Controllers\Client\FixedAssetController::class, 'update'])->name('fixed_assets.update');
    Route::delete('fixed-assets/{fixedAsset}', [\App\Http\Controllers\Client\FixedAssetController::class, 'destroy'])->name('fixed_assets.destroy');
    Route::post('fixed-assets/run-depreciation', [\App\Http\Controllers\Client\FixedAssetController::class, 'runDepreciation'])->name('fixed_assets.run_depreciation');
    Route::get('fixed-assets/categories', [\App\Http\Controllers\Client\FixedAssetController::class, 'categories'])->name('fixed_assets.categories');
    Route::post('fixed-assets/categories', [\App\Http\Controllers\Client\FixedAssetController::class, 'storeCategory'])->name('fixed_assets.categories.store');
    Route::delete('fixed-assets/categories/{category}', [\App\Http\Controllers\Client\FixedAssetController::class, 'destroyCategory'])->name('fixed_assets.categories.destroy');
    Route::get('fixed-assets/tax-categories', [\App\Http\Controllers\Client\FixedAssetController::class, 'taxCategories'])->name('fixed_assets.tax_categories');
    Route::get('fixed-assets/modifications', [\App\Http\Controllers\Client\FixedAssetController::class, 'modifications'])->name('fixed_assets.modifications');
    Route::get('fixed-assets/dispositions', [\App\Http\Controllers\Client\FixedAssetController::class, 'dispositions'])->name('fixed_assets.dispositions');
    Route::get('fixed-assets/transfers', [\App\Http\Controllers\Client\FixedAssetController::class, 'transfers'])->name('fixed_assets.transfers');
    Route::get('fixed-assets/locations', [\App\Http\Controllers\Client\FixedAssetController::class, 'assetsByLocation'])->name('fixed_assets.locations');

    // Persediaan - Barang & Jasa
    Route::get('inventory', [\App\Http\Controllers\Client\InventoryController::class, 'index'])->name('inventory.index');
    Route::get('inventory/create', [\App\Http\Controllers\Client\InventoryController::class, 'create'])->name('inventory.create');
    Route::post('inventory', [\App\Http\Controllers\Client\InventoryController::class, 'store'])->name('inventory.store');
    Route::get('inventory/requests', [\App\Http\Controllers\Client\InventoryController::class, 'requests'])->name('inventory.requests');
    Route::post('inventory/requests', [\App\Http\Controllers\Client\InventoryController::class, 'storeRequest'])->name('inventory.requests.store');
    Route::get('inventory/transfers', [\App\Http\Controllers\Client\InventoryController::class, 'transfers'])->name('inventory.transfers');
    Route::post('inventory/transfers', [\App\Http\Controllers\Client\InventoryController::class, 'storeTransfer'])->name('inventory.transfers.store');
    Route::get('inventory/adjustments', [\App\Http\Controllers\Client\InventoryController::class, 'adjustments'])->name('inventory.adjustments');
    Route::post('inventory/adjustments', [\App\Http\Controllers\Client\InventoryController::class, 'storeAdjustment'])->name('inventory.adjustments.store');
    Route::get('inventory/stock-opname-orders', [\App\Http\Controllers\Client\InventoryController::class, 'stockOpnameOrders'])->name('inventory.stock_opname_orders');
    Route::get('inventory/stock-opname-results', [\App\Http\Controllers\Client\InventoryController::class, 'stockOpnameResults'])->name('inventory.stock_opname_results');
    Route::get('inventory/warehouses', [\App\Http\Controllers\Client\InventoryController::class, 'warehouses'])->name('inventory.warehouses');
    Route::post('inventory/warehouses', [\App\Http\Controllers\Client\InventoryController::class, 'storeWarehouse'])->name('inventory.warehouses.store');
    Route::delete('inventory/warehouses/{warehouse}', [\App\Http\Controllers\Client\InventoryController::class, 'destroyWarehouse'])->name('inventory.warehouses.destroy');
    Route::get('inventory/units', [\App\Http\Controllers\Client\InventoryController::class, 'units'])->name('inventory.units');
    Route::post('inventory/units', [\App\Http\Controllers\Client\InventoryController::class, 'storeUnit'])->name('inventory.units.store');
    Route::delete('inventory/units/{unit}', [\App\Http\Controllers\Client\InventoryController::class, 'destroyUnit'])->name('inventory.units.destroy');
    Route::get('inventory/brands', [\App\Http\Controllers\Client\InventoryController::class, 'brands'])->name('inventory.brands');
    Route::post('inventory/brands', [\App\Http\Controllers\Client\InventoryController::class, 'storeBrand'])->name('inventory.brands.store');
    Route::delete('inventory/brands/{brand}', [\App\Http\Controllers\Client\InventoryController::class, 'destroyBrand'])->name('inventory.brands.destroy');
    Route::get('inventory/categories', [\App\Http\Controllers\Client\ProductCategoryController::class, 'index'])->name('inventory.categories');
    Route::post('inventory/categories', [\App\Http\Controllers\Client\ProductCategoryController::class, 'store'])->name('inventory.categories.store');
    Route::get('inventory/categories/create', [\App\Http\Controllers\Client\ProductCategoryController::class, 'create'])->name('inventory.categories.create');
    Route::get('inventory/categories/{category}/edit', [\App\Http\Controllers\Client\ProductCategoryController::class, 'edit'])->name('inventory.categories.edit');
    Route::put('inventory/categories/{category}', [\App\Http\Controllers\Client\ProductCategoryController::class, 'update'])->name('inventory.categories.update');
    Route::delete('inventory/categories/{category}', [\App\Http\Controllers\Client\ProductCategoryController::class, 'destroy'])->name('inventory.categories.destroy');
    Route::get('inventory/order-fulfillments', [\App\Http\Controllers\Client\InventoryController::class, 'orderFulfillments'])->name('inventory.order_fulfillments');
    Route::get('inventory/items-per-warehouse', [\App\Http\Controllers\Client\InventoryController::class, 'itemsPerWarehouse'])->name('inventory.items_per_warehouse');
    Route::get('inventory/minimum-stock', [\App\Http\Controllers\Client\InventoryController::class, 'minimumStockItems'])->name('inventory.minimum_stock');
    Route::get('inventory/{product}/edit', [\App\Http\Controllers\Client\InventoryController::class, 'edit'])->name('inventory.edit');
    Route::put('inventory/{product}', [\App\Http\Controllers\Client\InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('inventory/{product}', [\App\Http\Controllers\Client\InventoryController::class, 'destroy'])->name('inventory.destroy');
    Route::get('inventory/{product}', [\App\Http\Controllers\Client\InventoryController::class, 'show'])->name('inventory.show');

    // Laporan Keuangan
    Route::get('reports', [\App\Http\Controllers\Client\ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/integrated', [\App\Http\Controllers\Client\ReportController::class, 'integrated'])->name('reports.integrated');
    Route::get('reports/profit-loss', [\App\Http\Controllers\Client\ReportController::class, 'profitLoss'])->name('reports.profit-loss');
    Route::get('reports/balance-sheet', [\App\Http\Controllers\Client\ReportController::class, 'balanceSheet'])->name('reports.balance-sheet');
    Route::get('reports/general-ledger', [\App\Http\Controllers\Client\ReportController::class, 'generalLedger'])->name('reports.general-ledger');
    Route::get('reports/trial-balance', [\App\Http\Controllers\Client\ReportController::class, 'trialBalance'])->name('reports.trial-balance');
    Route::get('reports/receivable-aging', [\App\Http\Controllers\Client\ReportController::class, 'receivableAging'])->name('reports.receivable-aging');
    Route::get('reports/receivable-summary', [\App\Http\Controllers\Client\ReportController::class, 'receivableSummary'])->name('reports.receivable-summary');
    Route::get('reports/sales-report', [\App\Http\Controllers\Client\ReportController::class, 'salesReport'])->name('reports.sales-report');
    Route::get('reports/payable-aging', [\App\Http\Controllers\Client\ReportController::class, 'payableAging'])->name('reports.payable-aging');
    Route::get('reports/payable-summary', [\App\Http\Controllers\Client\ReportController::class, 'payableSummary'])->name('reports.payable-summary');
    Route::get('reports/purchase-report', [\App\Http\Controllers\Client\ReportController::class, 'purchaseReport'])->name('reports.purchase-report');
    Route::get('reports/inventory-stock', [\App\Http\Controllers\Client\ReportController::class, 'inventoryStock'])->name('reports.inventory-stock');
    Route::get('reports/spt-ppn', [\App\Http\Controllers\Client\ReportController::class, 'sptPpn'])->name('reports.spt_ppn');
    Route::get('reports/tax-calculator', [\App\Http\Controllers\Client\ReportController::class, 'taxCalculator'])->name('reports.tax_calculator');

    // Penjualan
    Route::get('sales/orders', [\App\Http\Controllers\Client\SalesOrderController::class, 'index'])->name('sales.orders');
    Route::get('sales/orders/create', [\App\Http\Controllers\Client\SalesOrderController::class, 'create'])->name('sales.orders.create');
    Route::post('sales/orders', [\App\Http\Controllers\Client\SalesOrderController::class, 'store'])->name('sales.orders.store');
    Route::get('sales/orders/{salesOrder}', [\App\Http\Controllers\Client\SalesOrderController::class, 'show'])->whereNumber('salesOrder')->name('sales.orders.show');
    Route::get('sales/deliveries', [\App\Http\Controllers\Client\DeliveryOrderController::class, 'index'])->name('sales.deliveries');
    Route::get('sales/deliveries/create', [\App\Http\Controllers\Client\DeliveryOrderController::class, 'create'])->name('sales.deliveries.create');
    Route::post('sales/deliveries', [\App\Http\Controllers\Client\DeliveryOrderController::class, 'store'])->name('sales.deliveries.store');
    Route::get('sales/deliveries/{deliveryOrder}', [\App\Http\Controllers\Client\DeliveryOrderController::class, 'show'])->whereNumber('deliveryOrder')->name('sales.deliveries.show');
    Route::get('sales/down-payments', [\App\Http\Controllers\Client\SalesDownPaymentController::class, 'index'])->name('sales.down_payments');
    Route::get('sales/down-payments/create', [\App\Http\Controllers\Client\SalesDownPaymentController::class, 'create'])->name('sales.down_payments.create');
    Route::post('sales/down-payments', [\App\Http\Controllers\Client\SalesDownPaymentController::class, 'store'])->name('sales.down_payments.store');
    Route::get('sales/receipts', [\App\Http\Controllers\Client\SalesInvoiceController::class, 'salesReceipts'])->name('sales.receipts');
    Route::get('sales/returns', [\App\Http\Controllers\Client\SalesReturnController::class, 'index'])->name('sales.returns');
    Route::get('sales/returns/create', [\App\Http\Controllers\Client\SalesReturnController::class, 'create'])->name('sales.returns.create');
    Route::post('sales/returns', [\App\Http\Controllers\Client\SalesReturnController::class, 'store'])->name('sales.returns.store');
    Route::resource('sales-invoices', \App\Http\Controllers\Client\SalesInvoiceController::class)->only(['index','create','store','show']);
    
    // Pembelian
    Route::get('purchases/orders', [\App\Http\Controllers\Client\PurchaseOrderController::class, 'index'])->name('purchases.orders');
    Route::get('purchases/orders/create', [\App\Http\Controllers\Client\PurchaseOrderController::class, 'create'])->name('purchases.orders.create');
    Route::post('purchases/orders', [\App\Http\Controllers\Client\PurchaseOrderController::class, 'store'])->name('purchases.orders.store');
    Route::get('purchases/orders/{purchaseOrder}', [\App\Http\Controllers\Client\PurchaseOrderController::class, 'show'])->whereNumber('purchaseOrder')->name('purchases.orders.show');
    Route::get('purchases/receipts', [\App\Http\Controllers\Client\ItemReceiptController::class, 'index'])->name('purchases.receipts');
    Route::get('purchases/receipts/create', [\App\Http\Controllers\Client\ItemReceiptController::class, 'create'])->name('purchases.receipts.create');
    Route::post('purchases/receipts', [\App\Http\Controllers\Client\ItemReceiptController::class, 'store'])->name('purchases.receipts.store');
    Route::get('purchases/receipts/{itemReceipt}', [\App\Http\Controllers\Client\ItemReceiptController::class, 'show'])->whereNumber('itemReceipt')->name('purchases.receipts.show');
    Route::get('purchases/down-payments', [\App\Http\Controllers\Client\PurchaseDownPaymentController::class, 'index'])->name('purchases.down_payments');
    Route::get('purchases/down-payments/create', [\App\Http\Controllers\Client\PurchaseDownPaymentController::class, 'create'])->name('purchases.down_payments.create');
    Route::post('purchases/down-payments', [\App\Http\Controllers\Client\PurchaseDownPaymentController::class, 'store'])->name('purchases.down_payments.store');
    Route::get('purchases/payments', [\App\Http\Controllers\Client\PurchaseInvoiceController::class, 'purchasePayments'])->name('purchases.payments');
    Route::get('purchases/returns', [\App\Http\Controllers\Client\PurchaseReturnController::class, 'index'])->name('purchases.returns');
    Route::get('purchases/returns/create', [\App\Http\Controllers\Client\PurchaseReturnController::class, 'create'])->name('purchases.returns.create');
    Route::post('purchases/returns', [\App\Http\Controllers\Client\PurchaseReturnController::class, 'store'])->name('purchases.returns.store');
    Route::resource('purchase-invoices', \App\Http\Controllers\Client\PurchaseInvoiceController::class)->only(['index','create','store','show']);
});


// ==========================================
// PROFILE ROUTES (Global)
// ==========================================
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
