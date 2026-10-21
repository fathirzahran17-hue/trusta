<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Isolasi data antar perusahaan (multi-tenant) di level model.
 * Selama pengguna perusahaan sedang login, semua pencarian model ini otomatis dibatasi ke company_id miliknya,
 * jadi mengganti ID di URL / form tidak bisa membuka data perusahaan lain (hasilnya 404 / kosong).
 * Super admin, proses console (seed, migrasi) dan pengguna yang belum login tidak dibatasi.
 */
trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', new class implements Scope {
            public function apply(Builder $builder, Model $model): void
            {
                $user = auth()->hasUser() ? auth()->user() : null;
                if ($user && $user->company_id && $user->role !== 'super_admin') {
                    $builder->where($model->getTable() . '.company_id', $user->company_id);
                }
            }
        });
    }
}
