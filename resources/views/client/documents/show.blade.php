<x-app-layout>
    @section('title', $title)
    @section('subtitle', $number)

    <div class="mb-6 flex flex-wrap gap-3 justify-between items-center no-print">
        <a href="{{ $backUrl }}" class="text-trusta-teal hover:underline flex items-center text-sm">&larr; Kembali ke {{ $backLabel }}</a>
        <div class="flex flex-wrap gap-2">
            <button type="button" onclick="window.print()" class="px-4 py-2 border border-gray-600 rounded-lg text-sm text-gray-300 hover:bg-gray-700 transition-colors">Cetak</button>
            @foreach($actions as $a)
                @if(!empty($a['method']))
                    <form method="POST" action="{{ $a['url'] }}" class="inline" @if(!empty($a['confirm'])) onsubmit="return confirm(@js($a['confirm']))" @endif>
                        @csrf
                        @if($a['method'] !== 'POST') @method($a['method']) @endif
                        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium transition-colors {{ ($a['style'] ?? '') === 'danger' ? 'border border-red-500/60 text-red-400 hover:bg-red-500/10' : 'border border-gray-600 text-gray-300 hover:bg-gray-700' }}">{{ $a['label'] }}</button>
                    </form>
                @else
                    <a href="{{ $a['url'] }}" class="px-4 py-2 {{ ($a['style'] ?? '') === 'secondary' ? 'border border-gray-600 text-gray-300 hover:bg-gray-700' : 'bg-trusta-teal text-navy-900 hover:bg-teal-400' }} font-medium rounded-lg text-sm transition-colors">{{ $a['label'] }}</a>
                @endif
            @endforeach
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-xl p-6">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
                    @foreach($meta as $label => $value)
                        @if(filled($value))
                            <div>
                                <dt class="text-xs uppercase tracking-wider text-gray-400">{{ $label }}</dt>
                                <dd class="text-white font-medium">{{ $value }}</dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>

            <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-xl overflow-hidden">
                <div class="p-6 overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="text-gray-400">
                                <th class="border-b border-gray-700 p-3">Produk / Jasa</th>
                                <th class="border-b border-gray-700 p-3 text-right">Qty</th>
                                @if($showPrices)
                                    <th class="border-b border-gray-700 p-3 text-right">Harga</th>
                                    <th class="border-b border-gray-700 p-3 text-right">Jumlah</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($lines as $l)
                                <tr>
                                    <td class="border-b border-gray-700 p-3">
                                        <div class="text-white">{{ $l['name'] }}</div>
                                        @if(!empty($l['description']))<div class="text-xs text-gray-400">{{ $l['description'] }}</div>@endif
                                    </td>
                                    <td class="border-b border-gray-700 p-3 text-right tabular-nums">{{ rtrim(rtrim(number_format($l['quantity'], 2, ',', '.'), '0'), ',') }}</td>
                                    @if($showPrices)
                                        <td class="border-b border-gray-700 p-3 text-right tabular-nums">Rp {{ number_format($l['price'], 2, ',', '.') }}</td>
                                        <td class="border-b border-gray-700 p-3 text-right tabular-nums">Rp {{ number_format($l['total'], 2, ',', '.') }}</td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="4" class="p-6 text-center text-gray-400">Tidak ada baris.</td></tr>
                            @endforelse
                        </tbody>
                        @if($showPrices && $total !== null)
                            <tfoot>
                                <tr><td colspan="3" class="p-3 text-right font-semibold text-gray-300">Total</td><td class="p-3 text-right font-bold text-white tabular-nums">Rp {{ number_format($total, 2, ',', '.') }}</td></tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </div>

            @if(!empty($progress))
            <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-xl overflow-hidden">
                <div class="px-6 pt-5 text-sm font-semibold text-gray-200">Progres Pesanan</div>
                <div class="p-6 overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="text-gray-400">
                                <th class="border-b border-gray-700 p-3">Produk / Jasa</th>
                                <th class="border-b border-gray-700 p-3 text-right">Dipesan</th>
                                <th class="border-b border-gray-700 p-3 text-right">{{ $progress['fulfilledLabel'] }}</th>
                                <th class="border-b border-gray-700 p-3 text-right">Ditagih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($progress['rows'] as $r)
                                @php $fmt = fn($n) => rtrim(rtrim(number_format($n, 2, ',', '.'), '0'), ','); @endphp
                                <tr>
                                    <td class="border-b border-gray-700 p-3 text-white">{{ $r['name'] }}</td>
                                    <td class="border-b border-gray-700 p-3 text-right tabular-nums">{{ $fmt($r['ordered']) }}</td>
                                    <td class="border-b border-gray-700 p-3 text-right tabular-nums {{ !$r['is_goods'] ? 'text-gray-500' : ($r['fulfilled'] + 0.0001 >= $r['ordered'] ? 'text-green-400' : 'text-yellow-400') }}">{{ $r['is_goods'] ? $fmt($r['fulfilled']) : '-' }}</td>
                                    <td class="border-b border-gray-700 p-3 text-right tabular-nums {{ $r['invoiced'] + 0.0001 >= $r['ordered'] ? 'text-green-400' : 'text-yellow-400' }}">{{ $fmt($r['invoiced']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif
        </div>

        <div>
            @include('partials.related-docs', ['groups' => $groups])
        </div>
    </div>
</x-app-layout>
