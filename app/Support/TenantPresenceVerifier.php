<?php

namespace App\Support;

use Illuminate\Validation\DatabasePresenceVerifier;

/**
 * Aturan validasi exists dan unique pada tabel milik perusahaan hanya melihat data perusahaan pengguna yang login.
 * Jadi ID milik perusahaan lain ditolak sebagai tidak valid walau aturannya hanya ditulis exists:accounts,id.
 */
class TenantPresenceVerifier extends DatabasePresenceVerifier
{
    private const TABLES = ['accounts', 'products', 'suppliers', 'customers', 'taxes', 'fixed_asset_categories', 'warehouses', 'sales_invoices', 'purchase_invoices', 'project_jobs'];

    protected function table($table)
    {
        $query = parent::table($table);
        $user = auth()->hasUser() ? auth()->user() : null;
        if ($user && $user->company_id && $user->role !== 'super_admin' && in_array($table, self::TABLES, true)) {
            $query->where($table . '.company_id', $user->company_id);
        }

        return $query;
    }
}
