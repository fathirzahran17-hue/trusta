<x-app-layout>
    @section('title', 'Kebutuhan Bahan')
    @section('subtitle', 'Isi rencana produksi untuk melihat bahan yang dibutuhkan dan yang masih kurang')

    @php
        $rp = fn ($v) => 'Rp ' . number_format((float) $v, 2, ',', '.');
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, ',', '.'), '0'), ',');
    @endphp

    <form method="GET" action="{{ route('client.production.requirements') }}" class="pay-card">
        <div class="pay-card-head"><h3>Rencana Produksi</h3><a class="pay-mini" href="{{ route('client.production.boms') }}">Kelola Resep</a></div>
        <div class="pay-scroll">
            <table class="pay-table">
                <thead><tr><th>Resep</th><th>Barang Jadi</th><th class="r">Hasil per Resep</th><th class="r" style="width:170px">Rencana Jumlah</th></tr></thead>
                <tbody>
                @forelse($boms as $bom)
                    <tr>
                        <td class="wrap">{{ $bom->name }}</td>
                        <td class="wrap">{{ optional($bom->product)->name }}</td>
                        <td class="r">{{ $num($bom->output_qty) }} {{ optional($bom->product)->unit }}</td>
                        <td class="r"><input type="number" step="0.01" min="0" class="pay-amt" style="width:140px" name="qty[{{ $bom->id }}]" value="{{ $planned[$bom->id] ?? '' }}" placeholder="0"></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="pay-empty">Belum ada resep. <a class="lnk" href="{{ route('client.production.boms') }}">Buat resep dulu</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="pay-foot"><span></span><button type="submit" class="btn-primary">Hitung Kebutuhan</button></div>
    </form>

    @if($planned)
        <div class="pay-card">
            <div class="pay-card-head"><h3>Kebutuhan Bahan</h3><span class="sub">{{ $shortCount }} bahan masih kurang</span></div>
            <div class="pay-scroll">
                <table class="pay-table">
                    <thead><tr><th>Bahan</th><th class="r">Dibutuhkan</th><th class="r">Stok</th><th class="r">Kurang</th><th class="r">Harga Pokok</th><th class="r">Perkiraan Biaya</th></tr></thead>
                    <tbody>
                    @foreach($rows as $row)
                        <tr>
                            <td class="wrap">{{ $row['product']->name }}</td>
                            <td class="r">{{ $num($row['need']) }} {{ $row['product']->unit }}</td>
                            <td class="r">{{ $num($row['stock']) }} {{ $row['product']->unit }}</td>
                            <td class="r"><span class="st {{ $row['short'] > 0 ? 'st-late' : 'st-paid' }}">{{ $row['short'] > 0 ? $num($row['short']) . ' ' . $row['product']->unit : 'Cukup' }}</span></td>
                            <td class="r">{{ $rp($row['product']->buy_price) }}</td>
                            <td class="r">{{ $row['short'] > 0 ? $rp($row['cost']) : '-' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="pay-foot">
                <span class="pay-total">Perkiraan biaya beli bahan yang kurang <b>{{ $rp($rows->sum('cost')) }}</b></span>
                @if($shortCount)
                    <a class="btn-primary" href="{{ route('client.purchases.orders.create', ['items' => $rows->where('short', '>', 0)->mapWithKeys(fn ($r) => [$r['product']->id => $r['order']])->all()]) }}">Buat Pesanan Pembelian</a>
                @else
                    <a class="btn-primary" href="{{ route('client.production.orders') }}">Mulai Produksi</a>
                @endif
            </div>
        </div>
    @endif
</x-app-layout>
