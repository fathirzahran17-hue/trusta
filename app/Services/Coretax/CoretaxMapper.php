<?php

namespace App\Services\Coretax;

use App\Models\Account;

/**
 * Memetakan akun perusahaan ke kode Lampiran 1 berdasarkan jenis dan namanya.
 * Pilihan manual selalu menang. Akun yang hanya cocok lewat jenisnya ditandai untuk dicek.
 */
class CoretaxMapper
{
    public const MANUAL = 'manual';
    public const AUTO = 'auto';
    public const CHECK = 'check';

    /** @var array<int,Account> */
    private array $byId = [];

    /** @param iterable<Account> $accounts seluruh akun perusahaan, untuk membaca induknya */
    public function __construct(iterable $accounts, private string $lampiran)
    {
        foreach ($accounts as $a) {
            $this->byId[(int) $a->id] = $a;
        }
    }

    /** @return array{code:string,status:string} */
    public function resolve(Account $a): array
    {
        if ($a->coretax_code && CoretaxCatalog::isChoice($a->coretax_code, $a->type, $this->lampiran)) {
            return ['code' => $a->coretax_code, 'status' => self::MANUAL];
        }
        [$code, $sure] = $this->suggest($a);

        return ['code' => $code, 'status' => $sure ? self::AUTO : self::CHECK];
    }

    private function ancestors(Account $a): string
    {
        $names = [];
        $seen = [];
        $id = $a->parent_id;
        while ($id && isset($this->byId[(int) $id]) && ! isset($seen[(int) $id])) {
            $seen[(int) $id] = true;
            $names[] = $this->byId[(int) $id]->name;
            $id = $this->byId[(int) $id]->parent_id;
        }

        return mb_strtolower(implode(' | ', $names));
    }

    /** @return array{0:string,1:bool} kode, yakin atau tidak */
    private function suggest(Account $a): array
    {
        $n = mb_strtolower($a->name);
        $is = fn (string $p) => preg_match('/' . $p . '/u', $n) === 1;

        return match ($a->type) {
            'asset' => $this->asset($a, $is),
            'liability' => $this->liability($a, $is),
            'equity' => $this->equity($is),
            'revenue' => $this->revenue($is),
            'cost_of_sales' => $this->costOfSales($is),
            'expense' => $this->expense($is),
            'other_income' => $this->otherIncome($is),
            'other_expense' => $this->otherExpense($is),
            default => $this->byNormalFallback($a),
        };
    }

    private function asset(Account $a, callable $is): array
    {
        $related = $is('istimewa|afiliasi|hubungan|pemegang saham|direksi|direktur');
        if ($is('cadangan.*(piutang|penurunan|kerugian)')) return [$this->nonCurrent($a) ? '1658' : '1131', true];
        if ($is('akumulasi')) {
            if ($is('tanah|bangunan|gedung')) return ['1524', true];
            if ($is('amortisasi|tak berwujud')) return ['1601', true];
            if ($is('hak guna|right of use')) return ['1534', true];

            return ['1530', true];
        }
        if ($a->is_cash_bank) return ['1101', true];
        if ($is('piutang')) {
            if ($is('jangka panjang')) return ['1501', true];
            if ($is('piutang (usaha|dagang)')) return [$related ? '1123' : '1122', true];

            return [$related ? '1125' : '1124', true];
        }
        if ($is('\bkas\b|\bbank\b|\bgiro\b|tabungan|deposito|setara kas')) return ['1101', true];
        if ($is('aset kontrak')) return ['1181', true];
        if ($is('persediaan')) return ['1401', true];
        if ($is('pajak|\bppn\b|\bpph\b')) {
            if ($is('tangguhan')) return ['1611', true];
            if ($is('klaim|restitusi|pengembalian|lebih bayar')) return ['1651', true];

            return ['1423', true];
        }
        if ($is('uang muka')) return ['1422', true];
        if ($is('dibayar d[i ]*muka')) return ['1421', true];
        if ($is('properti investasi')) return ['1520', true];
        if ($is('hak guna|right of use')) return ['1533', true];
        if ($is('tanah|bangunan|gedung')) return ['1523', true];
        if ($is('kendaraan|mesin|peralatan|inventaris|aset tetap|komputer')) return ['1529', true];
        if ($is('tak berwujud|goodwill|lisensi|hak cipta|merek|perangkat lunak')) return ['1600', true];
        if ($is('asosiasi|ventura|anak perusahaan|penyertaan')) return ['1551', true];
        if ($is('investasi')) return [$is('jangka panjang') || $this->nonCurrent($a) ? '1599' : '1200', true];
        if ($is('perlengkapan')) return ['1499', true];
        if ($is('jaminan')) return ['1698', true];

        return [$this->nonCurrent($a) ? '1698' : '1499', false];
    }

    private function nonCurrent(Account $a): bool
    {
        $chain = $this->ancestors($a);
        if (str_contains($chain, 'aset lancar') && ! str_contains($chain, 'tidak lancar')) return false;

        return (bool) preg_match('/tidak lancar|aset tetap|aset lain|jangka panjang/u', $chain);
    }

    private function liability(Account $a, callable $is): array
    {
        $chain = $this->ancestors($a);
        $long = str_contains($chain, 'jangka panjang') || $is('jangka panjang');
        $related = $is('istimewa|afiliasi|hubungan|pemegang saham|direksi|direktur');
        if ($is('(hutang|utang) (usaha|dagang)')) return [$related ? '2103' : '2102', true];
        if ($is('pajak|\bppn\b|\bpph\b')) return [$is('tangguhan') ? '2321' : '2191', true];
        if ($is('jatuh tempo')) return ['2202', true];
        if ($is('bunga')) return ['2111', true];
        if ($is('kontrak')) return ['2186', true];
        if ($is('dividen')) return ['2192', true];
        if ($is('imbalan kerja|pesangon')) return ['2322', true];
        if ($is('sewa|leasing')) return [$long ? '2312' : '2187', true];
        if ($is('bank')) return [$long ? '2301' : '2201', true];
        if ($is('diterima d[i ]*muka|uang muka')) return ['2203', true];
        if ($is('masih harus|akrual|gaji|upah|bpjs')) return ['2195', true];
        if ($long) return $related ? ['2304', true] : ($is('hutang|utang') ? ['2303', true] : ['2998', false]);

        return ['2228', $is('lain')];
    }

    private function equity(callable $is): array
    {
        if ($is('tambahan modal|agio')) return ['3120', true];
        if ($is('laba ditahan|saldo laba|laba tahun|laba berjalan|rugi')) return ['3200', true];
        if ($is('komprehensif')) return ['3297', true];
        if ($is('modal')) return ['3102', true];

        return ['3298', $is('prive|ekuitas')];
    }

    private function revenue(callable $is): array
    {
        $jasa = $this->lampiran === '1D';
        if ($is('penyesuaian')) return ['4013', true];
        if (! $jasa) {
            if ($is('retur')) return ['4011', true];
            if ($is('potongan|diskon')) return ['4012', true];
            if ($is('ekspor')) return ['4003', true];
            if ($is('jasa')) return ['4199', false]; // usaha pokoknya bukan jasa

            return ['4002', $is('penjualan|domestik|pendapatan usaha')];
        }
        if ($is('retur|potongan|diskon')) return ['4013', true];

        return ['4021', $is('jasa|pendapatan usaha')];
    }

    private function costOfSales(callable $is): array
    {
        if ($this->lampiran === '1D') return ['5020', true];
        if ($this->lampiran === '1B') {
            // Akun pembebanan ke produksi dan HPP ikut menghitung Pembelian Bahan Baku supaya total HPP sama dengan pembukuan
            if ($is('dibebankan|hpp|harga pokok|pembelian|angkut')) return ['5022', true];
            if ($is('tidak langsung')) return ['5051', true];
            if ($is('tenaga kerja|upah')) return ['5050', true];
            if ($is('pemeliharaan|perbaikan')) return ['5052', true];
            if ($is('penyusutan|amortisasi')) return ['5058', true];
            if ($is('listrik|air|gas|utilitas')) return ['5059', true];

            return ['5069', $is('overhead|pabrik')];
        }
        if ($is('angkut|pengangkutan|ongkir|freight')) return ['5003', true];
        if ($is('hpp|harga pokok|pembelian')) return ['5001', true];

        return ['5007', false];
    }

    private function expense(callable $is): array
    {
        if ($is('penyusutan|amortisasi|depresiasi')) return ['5314', true];
        if ($is('gaji|upah|tunjangan|\bthr\b|bonus|honor|lembur')) return ['5311', true];
        if ($is('bpjs|imbalan kerja|pesangon|kesejahteraan|natura')) return ['5312', true];
        if ($is('transportasi|perjalanan|bbm|bensin|\btol\b|parkir|pengiriman')) return ['5313', true];
        if ($is('kerugian piutang|penurunan nilai|tak tertagih')) return ['5318', true];
        if ($is('selisih persediaan|selisih stok')) return ['5399', true];
        if ($is('sewa')) return ['5315', true];
        if ($is('bunga')) return ['5316', true];
        if ($is('royalti')) return ['5319', true];
        if ($is('iklan|promosi|pemasaran|komisi|marketing')) return ['5320', true];
        if ($is('entertain|jamuan|representasi')) return ['5321', true];
        if ($is('perizinan')) return [$this->general(), true];
        if ($is('jasa|konsultan|profesional|audit|notaris|legal|hukum')) return ['5317', true];
        if ($is('listrik|\bair\b|telepon|internet|perlengkapan|\batk\b|alat tulis|administrasi|kantor|asuransi|pemeliharaan|perbaikan|umum|perangkat lunak|software')) return [$this->general(), true];

        return ['5399', false];
    }

    /** Lampiran 1D tidak punya kode Beban Umum dan Administrasi. */
    private function general(): string
    {
        return $this->lampiran === '1D' ? '5399' : '5322';
    }

    private function otherIncome(callable $is): array
    {
        if ($is('kurs')) return ['4501', true];
        if ($is('pelepasan|penjualan aset')) return ['4503', true];
        if ($is('bunga')) return ['4511', true];

        return ['4599', true];
    }

    private function otherExpense(callable $is): array
    {
        if ($is('pelepasan|penjualan aset')) return ['5405', true];
        if ($is('sumbangan|donasi')) return ['5409', true];
        if ($is('kurs')) return ['5421', true];
        if ($is('bunga')) return ['5316', true];
        if ($is('pajak|\bpph\b')) return ['5399', true]; // biasanya perlu koreksi fiskal positif

        return ['5499', true];
    }

    /** Jenis akun lama: diperlakukan menurut saldo normalnya. */
    private function byNormalFallback(Account $a): array
    {
        return $a->normal_balance === 'credit' ? ['2228', false] : ['1499', false];
    }
}
