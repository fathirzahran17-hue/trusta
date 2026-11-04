<?php

namespace App\Support;

use App\Exceptions\PeriodClosedException;
use Carbon\Carbon;

/**
 * Kunci periode (Tutup Buku). Transaksi bertanggal <= closing_date perusahaan tidak boleh dibuat, diubah tanggalnya, atau dihapus.
 * Tidak berlaku bila tidak ada pengguna yang login (seeder, perintah artisan, scheduler).
 */
class PeriodLock
{
    /** Nama dokumen untuk pesan kepada pengguna. */
    public static function label(string $model): string
    {
        return [
            'ProductionOrder' => 'Perintah Produksi', 'Journal' => 'Jurnal', 'SalesInvoice' => 'Faktur Penjualan', 'PurchaseInvoice' => 'Faktur Pembelian',
            'SalesOrder' => 'Pesanan Penjualan', 'PurchaseOrder' => 'Pesanan Pembelian', 'DeliveryOrder' => 'Pengiriman Barang', 'ItemReceipt' => 'Penerimaan Barang',
            'SalesReturn' => 'Retur Penjualan', 'PurchaseReturn' => 'Retur Pembelian', 'CashTransaction' => 'Transaksi Kas', 'InvoicePayment' => 'Pembayaran',
            'DownPayment' => 'Uang Muka', 'InventoryMovement' => 'Mutasi Stok', 'FixedAsset' => 'Aset Tetap', 'DepreciationEntry' => 'Penyusutan',
        ][$model] ?? 'Transaksi';
    }

    public static function closingDate(): ?Carbon
    {
        if (!auth()->check()) return null;
        $company = auth()->user()->company ?? null;
        if (!$company || !$company->closing_date) return null;

        return Carbon::parse($company->closing_date)->startOfDay();
    }

    public static function isClosed($date): bool
    {
        $closing = self::closingDate();
        if (!$closing || !$date) return false;

        return Carbon::parse($date)->startOfDay()->lte($closing);
    }

    public static function assertOpen($date, string $what = 'Transaksi'): void
    {
        if (self::isClosed($date)) {
            throw new PeriodClosedException(
                $what . ' bertanggal ' . Carbon::parse($date)->format('d M Y') . ' tidak dapat disimpan/diubah/dihapus karena periodenya sudah Tutup Buku (terkunci sampai '
                . self::closingDate()->format('d M Y') . '). Gunakan tanggal setelah itu, atau minta admin membuka kunci di Pengaturan > Proses Akhir Bulan.'
            );
        }
    }
}
