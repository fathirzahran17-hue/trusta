<?php

namespace App\Services\Coretax;

/**
 * Daftar kode akun Lampiran 1 SPT Tahunan PPh Badan (PER-11/PJ/2025).
 * 1C dan Posisi Keuangan diambil dari formulir Lampiran 1C. 1B dan 1D dari ringkasan IdeaTax, jadi perlu dicocokkan dengan tampilan Coretax.
 *
 * Baris: header (judul), detail (diisi dari akun), derived (dihitung sistem), total (rumus dari baris lain).
 * dc: c = kredit dikurangi debit, d = debit dikurangi kredit. income: baris penghasilan yang punya kolom (4) dan (5).
 */
class CoretaxCatalog
{
    public const LAMPIRAN = ['1B' => 'Lampiran 1B - Manufaktur', '1C' => 'Lampiran 1C - Dagang', '1D' => 'Lampiran 1D - Jasa'];

    public static function forBusinessType(?string $type): string
    {
        return match (\App\Support\BusinessType::normalize($type)) {
            'manufaktur' => '1B',
            'jasa' => '1D',
            default => '1C',
        };
    }

    private static function h(string $name): array { return ['kind' => 'header', 'name' => $name]; }
    private static function inc(string $code, string $name): array { return ['kind' => 'detail', 'code' => $code, 'name' => $name, 'dc' => 'c', 'income' => true]; }
    private static function exp(string $code, string $name): array { return ['kind' => 'detail', 'code' => $code, 'name' => $name, 'dc' => 'd', 'income' => false]; }
    private static function der(string $code, string $name): array { return ['kind' => 'derived', 'code' => $code, 'name' => $name, 'dc' => 'd', 'income' => false]; }
    /** Baris "Dikurangi" di dalam HPP: makin besar, HPP makin kecil, jadi koreksi positifnya menaikkan laba. */
    private static function less(array $row): array { return $row + ['raises' => true]; }
    private static function tot(string $code, string $name, array $sum, bool $income = false): array { return ['kind' => 'total', 'code' => $code, 'name' => $name, 'sum' => $sum, 'income' => $income]; }

    private static function salesBlock(): array
    {
        return [
            self::h('Penjualan'),
            self::inc('4002', 'Penjualan Domestik'),
            self::inc('4003', 'Penjualan Ekspor'),
            self::tot('4004', 'Penjualan Bruto', ['+4002', '+4003'], true),
            self::h('Dikurangi:'),
            self::exp('4011', 'Retur'),
            self::exp('4012', 'Potongan Penjualan'),
            self::exp('4013', 'Penyesuaian Penjualan'),
            self::tot('4020', 'Penjualan Bersih', ['+4004', '-4011', '-4012', '-4013'], true),
        ];
    }

    /** Beban Usaha sampai Laba Sebelum Pajak. Lampiran 1D menurut sumbernya tidak punya 5322. */
    private static function operatingBlock(string $lampiran): array
    {
        $beban = [
            self::exp('5311', 'Gaji, Tunjangan, Bonus, Honorarium, THR, dsb'),
            self::exp('5312', 'Beban Imbalan Kerja Lainnya'),
            self::exp('5313', 'Beban Transportasi'),
            self::exp('5314', 'Beban Penyusutan dan Amortisasi'),
            self::exp('5315', 'Beban Sewa'),
            self::exp('5316', 'Beban Bunga'),
            self::exp('5317', 'Beban Sehubungan dengan Jasa'),
            self::exp('5318', 'Beban Penurunan Nilai'),
            self::exp('5319', 'Beban Royalti'),
            self::exp('5320', 'Beban Pemasaran atau Promosi'),
            self::exp('5321', 'Beban Entertainment'),
        ];
        if ($lampiran !== '1D') {
            $beban[] = self::exp('5322', 'Beban Umum dan Administrasi');
        }
        $beban[] = self::exp('5399', 'Beban Usaha Lainnya');
        $sum = array_map(fn ($r) => '+' . $r['code'], $beban);

        return [
            self::tot('4300', 'Laba Kotor', ['+4020', '-5020'], true),
            self::inc('4199', 'Pendapatan Usaha Lainnya'),
            self::h('Beban Usaha'),
            ...$beban,
            self::tot('5400', 'Jumlah Beban Usaha', $sum),
            self::tot('4500', 'Laba (Rugi) Usaha', ['+4300', '+4199', '-5400'], true),
            self::h('Pendapatan Non Usaha'),
            self::inc('4501', 'Keuntungan Selisih Kurs'),
            self::inc('4503', 'Keuntungan Penjualan Aset selain Persediaan'),
            self::inc('4511', 'Pendapatan Bunga'),
            self::inc('4599', 'Pendapatan Non Usaha Lainnya'),
            self::tot('4600', 'Jumlah Pendapatan Non Usaha', ['+4501', '+4503', '+4511', '+4599'], true),
            self::h('Beban Non Usaha'),
            self::exp('5405', 'Kerugian Penjualan Aset selain Persediaan'),
            self::exp('5409', 'Sumbangan'),
            self::exp('5421', 'Kerugian Selisih Kurs'),
            self::exp('5499', 'Beban Non Usaha Lainnya'),
            self::tot('5500', 'Jumlah Beban Non Usaha', ['+5405', '+5409', '+5421', '+5499']),
            self::tot('4700', 'Laba (Rugi) Non Usaha', ['+4600', '-5500'], true),
            self::tot('4800', 'Laba (Rugi) Sebelum Pajak', ['+4500', '+4700'], true),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function profitLoss(string $lampiran): array
    {
        return match ($lampiran) {
            '1B' => [
                ...self::salesBlock(),
                self::h('Harga Pokok Produksi'),
                self::h('Biaya Bahan Baku'),
                self::der('5021', 'Persediaan Awal Bahan Baku'),
                self::exp('5022', 'Pembelian Bahan Baku'),
                self::less(['kind' => 'detail', 'code' => '5029', 'name' => 'Dikurangi: Retur Pembelian Bahan Baku', 'dc' => 'c', 'income' => false]),
                self::tot('5030', 'Jumlah Pembelian Bahan Baku', ['+5022', '-5029']),
                self::tot('5031', 'Bahan Baku Yang Tersedia Untuk Produksi', ['+5021', '+5030']),
                self::less(self::der('5032', 'Dikurangi: Persediaan Akhir Bahan Baku')),
                self::tot('5040', 'Jumlah Biaya Bahan Baku', ['+5031', '-5032']),
                self::exp('5050', 'Biaya Tenaga Kerja Langsung'),
                self::h('Biaya Pabrikasi'),
                self::exp('5051', 'Biaya Tenaga Kerja Tidak Langsung'),
                self::exp('5052', 'Biaya Pemeliharaan dan Perbaikan Mesin'),
                self::exp('5058', 'Biaya Penyusutan dan Amortisasi'),
                self::exp('5059', 'Biaya Utilitas'),
                self::exp('5069', 'Biaya Pabrikasi Lainnya'),
                self::tot('5070', 'Jumlah Biaya Pabrikasi', ['+5051', '+5052', '+5058', '+5059', '+5069']),
                self::tot('5080', 'Jumlah Biaya Produksi', ['+5040', '+5050', '+5070']),
                self::der('5090', 'Persediaan Awal Barang Dalam Proses'),
                self::less(self::der('5099', 'Dikurangi: Persediaan Akhir Barang Dalam Proses')),
                self::tot('5100', 'Jumlah Harga Pokok Produksi', ['+5080', '+5090', '-5099']),
                self::der('5008', 'Persediaan Awal Barang Jadi'),
                self::less(self::der('5009', 'Dikurangi: Persediaan Akhir Barang Jadi')),
                self::tot('5020', 'Jumlah Harga Pokok Penjualan', ['+5100', '+5008', '-5009']),
                ...self::operatingBlock($lampiran),
            ],
            '1D' => [
                self::h('Pendapatan'),
                self::inc('4021', 'Pendapatan Jasa'),
                self::exp('4013', 'Penyesuaian Penjualan'),
                self::tot('4020', 'Pendapatan Bersih', ['+4021', '-4013'], true),
                self::exp('5020', 'Biaya Pokok Jasa'),
                ...self::operatingBlock($lampiran),
            ],
            default => [
                ...self::salesBlock(),
                self::h('Harga Pokok Penjualan (HPP)'),
                self::exp('5001', 'Pembelian'),
                self::exp('5003', 'Beban Pengangkutan'),
                self::exp('5007', 'Beban Lainnya'),
                self::der('5008', 'Persediaan - Awal'),
                self::less(self::der('5009', 'Dikurangi: Persediaan - Akhir')),
                self::tot('5020', 'Jumlah HPP', ['+5001', '+5003', '+5007', '+5008', '-5009']),
                ...self::operatingBlock($lampiran),
            ],
        };
    }

    private static function a(string $code, string $name): array { return ['kind' => 'detail', 'code' => $code, 'name' => $name, 'side' => 'asset']; }
    private static function l(string $code, string $name): array { return ['kind' => 'detail', 'code' => $code, 'name' => $name, 'side' => 'liability']; }
    private static function e(string $code, string $name): array { return ['kind' => 'detail', 'code' => $code, 'name' => $name, 'side' => 'equity']; }

    /** Laporan Posisi Keuangan, sama seperti formulir Lampiran 1C. */
    public static function position(): array
    {
        $current = [self::a('1101', 'Kas dan Setara Kas'), self::a('1200', 'Investasi'), self::a('1122', 'Piutang Usaha - Pihak Ketiga'),
            self::a('1123', 'Piutang Usaha - Pihak yang Mempunyai Hubungan Istimewa'), self::a('1124', 'Piutang Lainnya - Pihak Ketiga'),
            self::a('1125', 'Piutang Lainnya - Pihak yang Mempunyai Hubungan Istimewa'), self::a('1181', 'Aset Kontrak'),
            self::a('1131', 'Cadangan Kerugian Penurunan Nilai - Aset Lancar'), self::a('1401', 'Persediaan'), self::a('1423', 'Pajak Dibayar di Muka'),
            self::a('1421', 'Beban Dibayar di Muka'), self::a('1422', 'Uang Muka'), self::a('1499', 'Aset Lancar Lainnya')];
        $nonCurrent = [self::a('1501', 'Piutang Jangka Panjang'), self::a('1520', 'Properti Investasi'), self::a('1523', 'Tanah dan Bangunan'),
            self::a('1524', 'Dikurangi: Akumulasi Penyusutan - Tanah dan Bangunan'), self::a('1529', 'Aset Tetap Lainnya'),
            self::a('1530', 'Dikurangi: Akumulasi Penyusutan - Aset Tetap Lainnya'), self::a('1533', 'Aset Hak Guna'),
            self::a('1534', 'Dikurangi: Akumulasi Penyusutan - Aset Hak Guna'), self::a('1551', 'Investasi pada Perusahaan Asosiasi, Ventura Bersama, dan Anak Perusahaan'),
            self::a('1599', 'Investasi Jangka Panjang Lainnya'), self::a('1600', 'Aset Tak Berwujud'), self::a('1601', 'Dikurangi: Akumulasi Amortisasi - Aset Tak Berwujud'),
            self::a('1611', 'Aktiva Pajak Tangguhan'), self::a('1651', 'Klaim atas Pengembalian Pajak'), self::a('1658', 'Cadangan Kerugian Penurunan Nilai - Aset Tidak Lancar'),
            self::a('1698', 'Aset Tidak Lancar Lainnya')];
        $short = [self::l('2102', 'Utang Usaha - Pihak Ketiga'), self::l('2103', 'Utang Usaha - Pihak yang Mempunyai Hubungan Istimewa'), self::l('2111', 'Utang Bunga'),
            self::l('2191', 'Utang Pajak'), self::l('2186', 'Liabilitas Kontrak'), self::l('2187', 'Liabilitas Sewa Jangka Pendek'), self::l('2192', 'Utang Dividen'),
            self::l('2195', 'Beban yang Masih Harus Dibayar'), self::l('2201', 'Utang Bank Jangka Pendek'), self::l('2202', 'Utang Jangka Panjang yang Jatuh Tempo dalam Satu Tahun'),
            self::l('2203', 'Pendapatan Diterima di Muka'), self::l('2228', 'Liabilitas Jangka Pendek Lainnya')];
        $long = [self::l('2301', 'Utang Bank Jangka Panjang'), self::l('2303', 'Utang Jangka Panjang - Pihak Ketiga'), self::l('2304', 'Utang Jangka Panjang - Pihak yang Mempunyai Hubungan Istimewa'),
            self::l('2312', 'Liabilitas Sewa Jangka Panjang'), self::l('2322', 'Liabilitas Imbalan Kerja'), self::l('2321', 'Liabilitas Pajak Tangguhan'), self::l('2998', 'Liabilitas Jangka Panjang Lainnya')];
        $equity = [self::e('3102', 'Modal Saham'), self::e('3120', 'Tambahan Modal Disetor'), self::e('3200', 'Laba Ditahan'), self::e('3297', 'Pendapatan Komprehensif Lainnya'), self::e('3298', 'Ekuitas Lainnya')];
        $plus = fn (array $rows) => array_map(fn ($r) => '+' . $r['code'], $rows);

        return [
            self::h('Aset Lancar'), ...$current, self::tot('1500', 'Jumlah Aset Lancar', $plus($current)),
            self::h('Aset Tidak Lancar'), ...$nonCurrent, self::tot('1699', 'Jumlah Aset Tidak Lancar', $plus($nonCurrent)),
            self::tot('1700', 'Jumlah Aset', ['+1500', '+1699']),
            self::h('Liabilitas Jangka Pendek'), ...$short, self::tot('2229', 'Jumlah Liabilitas Jangka Pendek', $plus($short)),
            self::h('Liabilitas Jangka Panjang'), ...$long, self::tot('2999', 'Jumlah Liabilitas', ['+2229', ...$plus($long)]),
            self::h('Ekuitas'), ...$equity, self::tot('3299', 'Jumlah Ekuitas', $plus($equity)),
            self::tot('3300', 'Jumlah Liabilitas dan Ekuitas', ['+2999', '+3299']),
        ];
    }

    /** Kode yang bisa dipilih untuk sebuah jenis akun. @return array<string,string> kode => nama */
    public static function choices(string $accountType, string $lampiran): array
    {
        $side = ['asset' => 'asset', 'liability' => 'liability', 'equity' => 'equity'][$accountType] ?? null;
        $rows = $side ? array_filter(self::position(), fn ($r) => ($r['side'] ?? null) === $side) : array_filter(self::profitLoss($lampiran), fn ($r) => $r['kind'] === 'detail');

        return collect($rows)->filter(fn ($r) => $r['kind'] === 'detail')->mapWithKeys(fn ($r) => [$r['code'] => $r['name']])->all();
    }

    public static function isChoice(string $code, string $accountType, string $lampiran): bool
    {
        return isset(self::choices($accountType, $lampiran)[$code]);
    }
}
