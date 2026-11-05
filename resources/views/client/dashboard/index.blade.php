<x-app-layout>
    @section('title', 'Dashboard Utama')
    @section('subtitle', 'Ringkasan aktivitas dan keuangan perusahaan Anda')

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @php
        $rpd = fn ($v) => 'Rp ' . \App\Support\Money::fmt((float) $v);
        // Kolom sempit: ringkas mulai 1 miliar, angka lengkapnya muncul saat kursor diarahkan
        $rpf = fn ($v) => 'Rp ' . \App\Support\Money::fit((float) $v);
        $whole = fn ($v) => abs((float) $v) >= 1e9 ? \App\Support\Money::compact($v) : number_format((float) $v, 0, ',', '.');
    @endphp
    <form method="GET" action="{{ route('client.dashboard') }}" class="dash-period" x-data="{ p: '{{ $period['key'] }}' }">
        <span class="dash-period-label">Periode</span>
        <select name="period" x-model="p" @change="if (p !== 'custom') $el.form.submit()">
            @foreach($period['presets'] as $k => $l)<option value="{{ $k }}" @selected($period['key'] === $k)>{{ $l }}</option>@endforeach
        </select>
        <span x-show="p === 'custom'" @if($period['key'] !== 'custom') style="display:none" @endif class="dash-period-range">
            <input type="date" name="from" value="{{ $period['from']->toDateString() }}"> – <input type="date" name="to" value="{{ $period['to']->toDateString() }}">
            <button type="submit" class="pay-mini prim">Terapkan</button>
        </span>
        <span class="dash-period-now">{{ $period['label'] }}</span>
    </form>

    @if($alerts['overdue_ar_count'] || $alerts['overdue_ap_count'] || $alerts['low_stock'])
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        @if($alerts['overdue_ar_count'])
            <a href="{{ route('client.sales-invoices.index') }}" class="block rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm text-red-400 hover:bg-red-500/20 transition-colors">
                <div class="font-semibold">{{ $alerts['overdue_ar_count'] }} piutang jatuh tempo</div>
                <div class="text-xs opacity-90">Sisa tagihan Rp {{ \App\Support\Money::fmt($alerts['overdue_ar_amount']) }}</div>
            </a>
        @endif
        @if($alerts['overdue_ap_count'])
            <a href="{{ route('client.purchase-invoices.index') }}" class="block rounded-lg border border-yellow-500/40 bg-yellow-500/10 p-3 text-sm text-yellow-500 hover:bg-yellow-500/20 transition-colors">
                <div class="font-semibold">{{ $alerts['overdue_ap_count'] }} hutang jatuh tempo</div>
                <div class="text-xs opacity-90">Perlu dibayar Rp {{ \App\Support\Money::fmt($alerts['overdue_ap_amount']) }}</div>
            </a>
        @endif
        @if($alerts['low_stock'])
            <a href="{{ route('client.inventory.minimum_stock') }}" class="block rounded-lg border border-orange-500/40 bg-orange-500/10 p-3 text-sm text-orange-400 hover:bg-orange-500/20 transition-colors">
                <div class="font-semibold">{{ $alerts['low_stock'] }} barang stok menipis</div>
                <div class="text-xs opacity-90">Stok 5 atau kurang</div>
            </a>
        @endif
    </div>
    @endif

    <!-- 6 Column Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-6 gap-4">
        
        <!-- 1. Aktivitas Terakhir Anda -->
        <div class="lg:col-span-2 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-72">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200">Aktivitas Terakhir Anda</h3>
                <button type="button" onclick="location.reload()" title="Muat ulang" class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex-1 overflow-y-auto">
                @if($recentActivities->isEmpty())
                    <p class="text-xs text-gray-500 text-center mt-8">Belum ada aktivitas</p>
                @else
                    <div class="relative border-l border-gray-700 ml-3 space-y-4">
                        @foreach($recentActivities as $act)
                            <div class="pl-4 relative">
                                <div class="absolute w-3 h-3 bg-trusta-teal rounded-full -left-[6.5px] border-2 border-[#1E293B] top-1"></div>
                                <p class="text-xs text-gray-400">{{ $act->created_at->translatedFormat('d M') }} &bull; {{ $act->created_at->format('H:i') }}</p>
                                <p class="text-sm text-gray-200 mt-1">{{ $act->act_label }} <a href="{{ $act->act_url }}" class="link-doc">{{ $act->ref_number }}</a></p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- 2. Kegiatan Mendatang -->
        <div class="lg:col-span-2 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-72">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200">Kegiatan Mendatang <span class="text-[10px] text-gray-400 ml-1 font-normal">(jatuh tempo 14 hari)</span></h3>
                <button type="button" onclick="location.reload()" title="Muat ulang" class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex-1 overflow-y-auto">
                @if($upcomingEvents->isEmpty())
                    <p class="text-xs text-gray-500 text-center mt-8">Tidak ada tagihan/pembayaran jatuh tempo dalam 14 hari ke depan.</p>
                @else
                    <div class="space-y-3">
                        @foreach($upcomingEvents as $ev)
                            @php $d = \Carbon\Carbon::parse($ev['date']); @endphp
                            <a href="{{ $ev['url'] }}" class="flex items-start" style="text-decoration:none">
                                <div class="flex-shrink-0 mr-3 text-center" style="width:38px">
                                    <div class="text-xs text-gray-400">{{ $d->translatedFormat('D') }}</div>
                                    <div class="text-lg font-bold text-gray-200" style="line-height:1.1">{{ $d->format('d') }}</div>
                                    <div class="text-xs text-gray-400">{{ $d->translatedFormat('M') }}</div>
                                </div>
                                <div class="flex-1 pl-3" style="border-left: 2px solid {{ $ev['kind'] === 'ar' ? '#16a34a' : ($ev['kind'] === 'ap' ? '#d97706' : 'var(--accent)') }}">
                                    <p class="text-sm text-gray-200">{{ $ev['title'] }}</p>
                                    <p class="text-xs text-gray-400">{{ $ev['number'] }}@if($ev['amount'] !== null) · {{ $rpd($ev['amount']) }}@endif</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- 3. Tren Penjualan -->
        <div class="lg:col-span-2 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-72">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200"><a href="{{ route('client.reports.sales-report') }}" class="hover:text-trusta-teal">Tren Penjualan <span class="text-[10px] text-gray-400 ml-1 font-normal" title="{{ $rpd($salesTotal) }}">{{ $rpf($salesTotal) }}</span></a></h3>
                <button type="button" onclick="location.reload()" title="Muat ulang" class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex-1 relative w-full h-full">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>

        <!-- 4. Laba / Rugi Tahun ini -->
        <div class="lg:col-span-2 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-72">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200"><a href="{{ route('client.reports.profit-loss') }}" class="hover:text-trusta-teal">Laba/Rugi <span class="text-[10px] text-gray-400 ml-1 font-normal">(periode)</span></a></h3>
                <button type="button" onclick="location.reload()" title="Muat ulang" class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex-1 flex items-center justify-between">
                <div class="w-1/2 relative h-full flex flex-col justify-center items-center">
                    <div class="w-32 h-32 relative">
                        <canvas id="plDonutChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-xs text-gray-400">{{ $netProfit >= 0 ? 'Laba' : 'Rugi' }}</span>
                            <span class="text-sm font-bold {{ $netProfit > 0 ? 'text-green-400' : ($netProfit < 0 ? 'text-red-400' : 'text-gray-400') }}" title="{{ $rpd($netProfit) }}">{{ \App\Support\Money::compact($netProfit) }}</span>
                        </div>
                    </div>
                </div>
                <div class="w-1/2 pl-2 flex flex-col justify-center space-y-3">
                    <div>
                        <div class="flex items-center text-xs mb-1"><span class="w-2 h-2 rounded-full bg-teal-400 mr-2"></span> <span class="text-gray-400">Pendapatan</span></div>
                        <div class="text-sm font-semibold text-gray-200 text-right" title="{{ $rpd($revenue) }}">{{ $rpf($revenue) }}</div>
                    </div>
                    <div>
                        <div class="flex items-center text-xs mb-1"><span class="w-2 h-2 rounded-full bg-red-400 mr-2"></span> <span class="text-gray-400">Nilai HPP</span></div>
                        <div class="text-sm font-semibold text-gray-200 text-right" title="{{ $rpd($cogs) }}">{{ $rpf($cogs) }}</div>
                    </div>
                    <div>
                        <div class="flex items-center text-xs mb-1"><span class="w-2 h-2 rounded-full bg-orange-400 mr-2"></span> <span class="text-gray-400">Beban</span></div>
                        <div class="text-sm font-semibold text-gray-200 text-right" title="{{ $rpd($expense) }}">{{ $rpf($expense) }}</div>
                    </div>
                    <div class="pt-2 border-t border-gray-700">
                        <div class="text-xs text-gray-400">{{ $netProfit >= 0 ? 'Laba Bersih' : 'Rugi Bersih' }}</div>
                        <div class="text-sm font-bold {{ $netProfit > 0 ? 'text-green-400' : ($netProfit < 0 ? 'text-red-400' : 'text-gray-400') }} text-right" title="{{ $rpd($netProfit) }}">{{ $rpf($netProfit) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Arus Kas -->
        <div class="lg:col-span-2 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-72">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200"><a href="{{ route('client.cash-bank.index') }}" class="hover:text-trusta-teal">Arus Kas <span class="text-[10px] ml-1 font-normal" style="color:#16a34a" title="Uang Masuk {{ $rpd($cashTotals['in']) }}">+{{ $whole($cashTotals['in']) }}</span> <span class="text-[10px] font-normal" style="color:var(--danger)" title="Uang Keluar {{ $rpd($cashTotals['out']) }}">−{{ $whole($cashTotals['out']) }}</span></a></h3>
                <button type="button" onclick="location.reload()" title="Muat ulang" class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex-1 relative w-full h-full">
                <canvas id="cashFlowChart"></canvas>
            </div>
        </div>

        <!-- 6. Beban Perusahaan -->
        <div class="lg:col-span-2 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-72">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200"><a href="{{ route('client.reports.profit-loss') }}" class="hover:text-trusta-teal">Beban Perusahaan <span class="text-[10px] text-gray-400 ml-1 font-normal">(periode)</span></a></h3>
                <button type="button" onclick="location.reload()" title="Muat ulang" class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex-1 flex items-center">
                <div class="w-1/2 relative h-full flex flex-col justify-center items-center">
                    <div class="w-32 h-32 relative">
                        <canvas id="expenseDonutChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-[10px] text-gray-400">Total Beban</span>
                            <span class="text-sm font-bold text-gray-200" title="{{ $rpd($expense) }}">{{ \App\Support\Money::compact($expense) }}</span>
                        </div>
                    </div>
                </div>
                <div class="w-1/2 min-w-0 pl-2 flex flex-col justify-center space-y-2">
                    @php $colors = ['bg-pink-500', 'bg-yellow-500', 'bg-blue-400', 'bg-purple-500', 'bg-green-500']; @endphp
                    @forelse($topExpenses as $idx => $exp)
                    <div>
                        <div class="flex items-center text-[10px] mb-1">
                            <span class="w-2 h-2 rounded-full flex-shrink-0 {{ $colors[$idx % 5] }} mr-1"></span>
                            <span class="text-gray-400 truncate" title="{{ $exp->name }}">{{ $exp->name }}</span>
                        </div>
                        <div class="text-xs font-semibold text-gray-200 text-right" title="{{ $rpd($exp->total) }}">{{ $rpf($exp->total) }}</div>
                    </div>
                    @empty
                    <p class="text-xs text-gray-500">Belum ada data beban</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- 7. Penjualan -->
        <div class="lg:col-span-3 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-56">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200"><a href="{{ route('client.sales-invoices.index') }}" class="hover:text-trusta-teal">Penjualan <span class="text-[10px] text-gray-400 ml-1 font-normal">(Semua Data)</span></a></h3>
                <button type="button" onclick="location.reload()" title="Muat ulang" class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex flex-col flex-1">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <p class="text-xs text-gray-400">Total Pendapatan</p>
                        <h4 class="text-xl font-bold text-gray-100">Rp {{ \App\Support\Money::fmt($salesOverview['total']) }}</h4>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-400">Total Belum Lunas</p>
                        <h4 class="text-xl font-bold text-gray-100">Rp {{ \App\Support\Money::fmt($salesOverview['unpaid']) }}</h4>
                    </div>
                </div>

                <div class="flex space-x-2 text-xs text-gray-400 mb-1">
                    <div class="w-1/4">Sudah Dibayar</div>
                    <div class="w-1/4 text-center">Sisa Tagihan</div>
                    <div class="w-1/4 text-center">Belum Jatuh Tempo</div>
                    <div class="w-1/4 text-right">Lewat Jatuh Tempo</div>
                </div>
                <div class="flex space-x-2 text-xs font-semibold mb-2">
                    <div class="w-1/4 text-green-400 whitespace-nowrap" title="{{ $rpd($salesOverview['paid']) }}">{{ $rpf($salesOverview['paid']) }}</div>
                    <div class="w-1/4 text-center text-yellow-400 whitespace-nowrap" title="{{ $rpd($salesOverview['unpaid']) }}">{{ $rpf($salesOverview['unpaid']) }}</div>
                    <div class="w-1/4 text-center text-yellow-500 whitespace-nowrap" title="{{ $rpd($salesOverview['not_due']) }}">{{ $rpf($salesOverview['not_due']) }}</div>
                    <div class="w-1/4 text-right text-red-400 whitespace-nowrap" title="{{ $rpd($salesOverview['overdue']) }}">{{ $rpf($salesOverview['overdue']) }}</div>
                </div>
                <!-- Progress Bars -->
                <div class="dash-progress mt-auto">
                    @php
                        $sTotal = $salesOverview['total'] > 0 ? $salesOverview['total'] : 1; // prevent div by zero
                        $sPaid = ($salesOverview['paid'] / $sTotal) * 100;
                        $sNotDue = ($salesOverview['not_due'] / $sTotal) * 100;
                        $sOverdue = ($salesOverview['overdue'] / $sTotal) * 100;
                    @endphp
                    <div class="bg-green-500" style="width: {{ round($sPaid, 2) }}%" title="Sudah Dibayar {{ $rpd($salesOverview['paid']) }}"></div>
                    <div class="bg-yellow-500" style="width: {{ round($sNotDue, 2) }}%" title="Belum Jatuh Tempo {{ $rpd($salesOverview['not_due']) }}"></div>
                    <div class="bg-red-500" style="width: {{ round($sOverdue, 2) }}%" title="Lewat Jatuh Tempo {{ $rpd($salesOverview['overdue']) }}"></div>
                </div>
            </div>
        </div>

        <!-- 8. Pembelian -->
        <div class="lg:col-span-3 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-56">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200"><a href="{{ route('client.purchase-invoices.index') }}" class="hover:text-trusta-teal">Pembelian <span class="text-[10px] text-gray-400 ml-1 font-normal">(Semua Data)</span></a></h3>
                <button type="button" onclick="location.reload()" title="Muat ulang" class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex flex-col flex-1">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <p class="text-xs text-gray-400">Total Pembelian</p>
                        <h4 class="text-xl font-bold text-gray-100">Rp {{ \App\Support\Money::fmt($purchaseOverview['total']) }}</h4>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-400">Total Belum Lunas</p>
                        <h4 class="text-xl font-bold text-gray-100">Rp {{ \App\Support\Money::fmt($purchaseOverview['unpaid']) }}</h4>
                    </div>
                </div>

                <div class="flex space-x-2 text-xs text-gray-400 mb-1">
                    <div class="w-1/4">Sudah Dibayar</div>
                    <div class="w-1/4 text-center">Sisa Tagihan</div>
                    <div class="w-1/4 text-center">Belum Jatuh Tempo</div>
                    <div class="w-1/4 text-right">Lewat Jatuh Tempo</div>
                </div>
                <div class="flex space-x-2 text-xs font-semibold mb-2">
                    <div class="w-1/4 text-green-400 whitespace-nowrap" title="{{ $rpd($purchaseOverview['paid']) }}">{{ $rpf($purchaseOverview['paid']) }}</div>
                    <div class="w-1/4 text-center text-yellow-400 whitespace-nowrap" title="{{ $rpd($purchaseOverview['unpaid']) }}">{{ $rpf($purchaseOverview['unpaid']) }}</div>
                    <div class="w-1/4 text-center text-yellow-500 whitespace-nowrap" title="{{ $rpd($purchaseOverview['not_due']) }}">{{ $rpf($purchaseOverview['not_due']) }}</div>
                    <div class="w-1/4 text-right text-red-400 whitespace-nowrap" title="{{ $rpd($purchaseOverview['overdue']) }}">{{ $rpf($purchaseOverview['overdue']) }}</div>
                </div>
                <!-- Progress Bars -->
                <div class="dash-progress mt-auto">
                    @php
                        $pTotal = $purchaseOverview['total'] > 0 ? $purchaseOverview['total'] : 1; 
                        $pPaid = ($purchaseOverview['paid'] / $pTotal) * 100;
                        $pNotDue = ($purchaseOverview['not_due'] / $pTotal) * 100;
                        $pOverdue = ($purchaseOverview['overdue'] / $pTotal) * 100;
                    @endphp
                    <div class="bg-green-500" style="width: {{ round($pPaid, 2) }}%" title="Sudah Dibayar {{ $rpd($purchaseOverview['paid']) }}"></div>
                    <div class="bg-yellow-500" style="width: {{ round($pNotDue, 2) }}%" title="Belum Jatuh Tempo {{ $rpd($purchaseOverview['not_due']) }}"></div>
                    <div class="bg-red-500" style="width: {{ round($pOverdue, 2) }}%" title="Lewat Jatuh Tempo {{ $rpd($purchaseOverview['overdue']) }}"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Initialization Script -->
    <script>
        // Warna grafik mengikuti tema (terang/gelap) dan ikut berubah saat tema diganti tanpa memuat ulang.
        const chartTheme = () => {
            const dark = document.documentElement.classList.contains('dark');
            return { text: dark ? '#94a3b8' : '#475569', grid: dark ? 'rgba(255,255,255,0.06)' : 'rgba(15,23,42,0.08)', surface: dark ? '#1E293B' : '#ffffff' };
        };
        const trustaCharts = [];
        // Satuan naik sendiri saat nilainya mencapai 1000. Aturannya sama dengan App\\Support\\Money::compact.
        const SHORT_UNITS = @json(\App\Support\Money::compactUnits());
        const shortAmount = (value) => {
            const abs = Math.abs(value), sign = value < 0 ? '-' : '';
            const text = (n) => String(Math.round(n * 100) / 100).replace('.', ',');
            for (let i = 0; i < SHORT_UNITS.length; i++) {
                const [size, label] = SHORT_UNITS[i];
                if (abs < size) continue;
                const short = Math.round(abs / size * 100) / 100;
                return sign + (short >= 1000 && i > 0 ? text(short / 1000) + ' ' + SHORT_UNITS[i - 1][1] : text(short) + ' ' + label);
            }
            return sign + text(abs);
        };
        // Sumbu uang tanpa pecahan seperti 0,2 saat data masih kosong; label tanggal mendatar dan dilompati bila tidak muat
        const moneyTicks = { maxTicksLimit: 6, precision: 0, callback: shortAmount };
        const flatLabels = { maxRotation: 0, autoSkipPadding: 10 };
        // Lingkaran tetap terlihat walau belum ada data
        const ringData = (values, colors) => values.some(v => v > 0)
            ? { data: values, backgroundColor: colors }
            : { data: [1], backgroundColor: [chartTheme().grid] };
        const ringTip = (values) => ({ enabled: values.some(v => v > 0), callbacks: { label: rupiahTip } });
        const rupiahTip = (ctx) => (window.trustaT || (s => s))(ctx.dataset.label || ctx.label || '') + ': ' + window.fmtRp(Math.abs(ctx.parsed.y ?? ctx.parsed));
        Chart.defaults.color = chartTheme().text;
        Chart.defaults.font.family = "'Poppins', sans-serif";
        window.addEventListener('trusta:theme', () => {
            const t = chartTheme();
            Chart.defaults.color = t.text;
            trustaCharts.forEach(c => {
                Object.values(c.options.scales || {}).forEach(sc => { if (sc.grid && sc.grid.display !== false) sc.grid.color = t.grid; if (sc.ticks) sc.ticks.color = t.text; });
                c.data.datasets.forEach(d => { if (d.pointBackgroundColor) d.pointBackgroundColor = t.surface; });
                c.update('none');
            });
        });
        
        // 1. Sales Trend Line Chart
        trustaCharts.push(new Chart(document.getElementById('salesTrendChart'), {
            type: 'line',
            data: {
                labels: {!! json_encode($salesTrendLabels) !!},
                datasets: [{
                    label: 'Penjualan',
                    data: {!! json_encode($salesTrend) !!},
                    borderColor: '#2DD4BF', // teal-400
                    backgroundColor: 'rgba(45, 212, 191, 0.1)',
                    borderWidth: 2,
                    pointBackgroundColor: chartTheme().surface,
                    pointBorderColor: '#2DD4BF',
                    pointBorderWidth: 2,
                    pointRadius: {{ count($salesTrendLabels) > 20 ? 2 : 4 }},
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: rupiahTip } } },
                scales: {
                    x: { grid: { display: false, drawBorder: false }, ticks: flatLabels },
                    y: { 
                        beginAtZero: true,
                        grid: { color: chartTheme().grid, drawBorder: false },
                        ticks: moneyTicks
                    }
                }
            }
        }));

        // 2. Profit & Loss Donut
        const plValues = [{{ max(0, $revenue) }}, {{ max(0, $cogs) }}, {{ max(0, $expense) }}];
        trustaCharts.push(new Chart(document.getElementById('plDonutChart'), {
            type: 'doughnut',
            data: {
                labels: ['Pendapatan', 'HPP', 'Beban'],
                datasets: [{
                    ...ringData(plValues, ['#2DD4BF', '#F87171', '#FB923C']),
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: { legend: { display: false }, tooltip: ringTip(plValues) }
            }
        }));

        // 3. Cash Flow Chart (Bar)
        trustaCharts.push(new Chart(document.getElementById('cashFlowChart'), {
            type: 'bar',
            data: {
                labels: {!! json_encode($cashFlowLabels) !!},
                datasets: [
                    {
                        label: 'Uang Masuk',
                        data: {!! json_encode($cashFlowIn) !!},
                        backgroundColor: '#34D399', // green-400
                        borderRadius: 2,
                    },
                    {
                        label: 'Uang Keluar',
                        data: {!! json_encode($cashFlowOut) !!},
                        backgroundColor: '#F87171', // red-400
                        borderRadius: 2,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: rupiahTip } } },
                scales: {
                    x: { stacked: true, grid: { display: false }, ticks: flatLabels },
                    y: { 
                        stacked: true, 
                        grid: { color: chartTheme().grid },
                        ticks: moneyTicks
                    }
                }
            }
        }));

        // 4. Expenses Donut
        @php 
            $expLabels = $topExpenses->pluck('name')->values();
            $expData = $topExpenses->pluck('total')->map(fn ($v) => (float) $v)->values();
        @endphp
        const expValues = {!! json_encode($expData) !!};
        trustaCharts.push(new Chart(document.getElementById('expenseDonutChart'), {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($expLabels) !!},
                datasets: [{
                    ...ringData(expValues, ['#EC4899', '#EAB308', '#60A5FA', '#A855F7', '#22C55E']),
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: { legend: { display: false }, tooltip: ringTip(expValues) }
            }
        }));
    </script>
</x-app-layout>
