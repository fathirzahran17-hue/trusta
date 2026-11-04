<?php

namespace App\Support;

use App\Models\InventoryMovement;
use App\Services\AccountResolver;

/** Jenis barang: menentukan akun persediaan dan urutan tampil di daftar pilihan tiap form. */
class ItemKind
{
    public const RAW = 'bahan_baku';
    public const SUPPORT = 'bahan_penolong';
    public const FINISHED = 'barang_jadi';
    public const MERCH = 'barang_dagang';

    public const TYPES = [
        self::RAW => ['label' => 'Bahan Baku', 'account' => 'Persediaan Bahan Baku'],
        self::SUPPORT => ['label' => 'Bahan Penolong', 'account' => 'Persediaan Bahan Penolong'],
        self::FINISHED => ['label' => 'Barang Jadi', 'account' => 'Persediaan Barang Jadi'],
        self::MERCH => ['label' => 'Barang Dagang', 'account' => 'Persediaan Barang Dagang'],
    ];

    /** Yang paling sering dipakai di tiap konteks tampil lebih dulu. */
    private const ORDER = [
        'purchase' => [self::RAW, self::SUPPORT, self::MERCH, self::FINISHED],
        'sales' => [self::FINISHED, self::MERCH, self::RAW, self::SUPPORT],
        'input' => [self::RAW, self::SUPPORT, self::FINISHED, self::MERCH],
        'output' => [self::FINISHED, self::MERCH, self::RAW, self::SUPPORT],
    ];

    public static function label(?string $kind): ?string
    {
        return self::TYPES[$kind]['label'] ?? null;
    }

    public static function isMaterial(?string $kind): bool
    {
        return in_array($kind, [self::RAW, self::SUPPORT], true);
    }

    public static function fromAccountName(?string $name): ?string
    {
        foreach (self::TYPES as $kind => $t) {
            if ($name && stripos($name, $t['account']) === 0) {
                return $kind;
            }
        }

        return null;
    }

    /** Jenis yang akunnya ada di Bagan Akun perusahaan. @return array<string,\App\Models\Account> */
    public static function available(int $companyId): array
    {
        $accounts = AccountResolver::inventoryAccounts($companyId);
        $out = [];
        foreach (self::TYPES as $kind => $t) {
            if ($a = $accounts->first(fn ($acc) => self::fromAccountName($acc->name) === $kind)) {
                $out[$kind] = $a;
            }
        }

        return $out;
    }

    /**
     * Kelompok untuk daftar pilihan barang.
     * @return array<int,array{label:?string,items:\Illuminate\Support\Collection,warn:bool}>
     */
    public static function groups($products, string $context): array
    {
        $byKind = collect($products)->groupBy(fn ($p) => $p->type === 'goods' ? ($p->item_kind ?: 'lain') : 'jasa');
        $groups = [];
        foreach (self::ORDER[$context] ?? self::ORDER['purchase'] as $kind) {
            if ($byKind->has($kind)) {
                $groups[] = ['label' => self::label($kind), 'items' => $byKind[$kind], 'warn' => $context === 'sales' && self::isMaterial($kind)];
            }
        }
        if ($byKind->has('lain')) {
            $groups[] = ['label' => 'Barang', 'items' => $byKind['lain'], 'warn' => false];
        }
        if ($byKind->has('jasa')) {
            $groups[] = ['label' => 'Jasa', 'items' => $byKind['jasa'], 'warn' => false];
        }
        if (count($groups) === 1) {
            $groups[0]['label'] = null;
        }

        return $groups;
    }

    /** Stok semua barang perusahaan sekaligus, agar form dengan banyak barang tidak menjalankan query per barang. */
    public static function stockMap(int $companyId): array
    {
        return InventoryMovement::where('company_id', $companyId)
            ->selectRaw("product_id, SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) AS qty")
            ->groupBy('product_id')->pluck('qty', 'product_id')->map(fn ($q) => (float) $q)->all();
    }
}
