<x-app-layout>
    @section('title', 'Dashboard Utama')
    @section('subtitle', 'Ringkasan aktivitas dan keuangan perusahaan Anda')

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <div class="mb-4 flex justify-between items-center">
        <button class="bg-gray-800 hover:bg-gray-700 text-white px-4 py-2 rounded-md border border-gray-600 text-sm flex items-center shadow-sm">
            <svg class="w-4 h-4 mr-2 text-trusta-teal" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Widget
        </button>
        <select class="bg-gray-800 border border-gray-600 text-white text-sm rounded-md focus:ring-trusta-teal focus:border-trusta-teal py-2 pl-3 pr-8">
            <option>Dashboard Utama</option>
        </select>
    </div>

    @if($alerts['overdue_ar_count'] || $alerts['overdue_ap_count'] || $alerts['low_stock'])
    <div class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-3">
        @if($alerts['overdue_ar_count'])
            <a href="{{ route('client.sales-invoices.index') }}" class="block rounded-lg border border-red-500/40 bg-red-500/10 p-3 text-sm text-red-400 hover:bg-red-500/20 transition-colors">
                <div class="font-semibold">{{ $alerts['overdue_ar_count'] }} piutang jatuh tempo</div>
                <div class="text-xs opacity-90">Sisa tagihan Rp {{ number_format($alerts['overdue_ar_amount'], 2, ',', '.') }}</div>
            </a>
        @endif
        @if($alerts['overdue_ap_count'])
            <a href="{{ route('client.purchase-invoices.index') }}" class="block rounded-lg border border-yellow-500/40 bg-yellow-500/10 p-3 text-sm text-yellow-500 hover:bg-yellow-500/20 transition-colors">
                <div class="font-semibold">{{ $alerts['overdue_ap_count'] }} hutang jatuh tempo</div>
                <div class="text-xs opacity-90">Perlu dibayar Rp {{ number_format($alerts['overdue_ap_amount'], 2, ',', '.') }}</div>
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
    <div class="grid grid-cols-1 md:grid-cols-6 gap-4">
        
        <!-- 1. Aktifitas Terakhir Anda -->
        <div class="md:col-span-2 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-72">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200">Aktifitas Terakhir Anda</h3>
                <button class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex-1 overflow-y-auto">
                @if($recentActivities->isEmpty())
                    <p class="text-xs text-gray-500 text-center mt-8">Belum ada aktifitas</p>
                @else
                    <div class="relative border-l border-gray-700 ml-3 space-y-4">
                        @foreach($recentActivities as $act)
                            <div class="pl-4 relative">
                                <div class="absolute w-3 h-3 bg-trusta-teal rounded-full -left-[6.5px] border-2 border-[#1E293B] top-1"></div>
                                <p class="text-xs text-gray-400">{{ $act->created_at->translatedFormat('d M') }} &bull; {{ $act->created_at->format('H:i') }}</p>
                                <p class="text-sm text-gray-200 mt-1">Buat Jurnal Umum <a href="{{ route('client.journals.show', $act) }}" class="link-doc">{{ $act->ref_number }}</a></p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- 2. Kegiatan Mendatang -->
        <div class="md:col-span-2 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-72">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200">Kegiatan Mendatang</h3>
                <button class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex-1 overflow-y-auto">
                @if($upcomingEvents->isEmpty())
                    <p class="text-xs text-gray-500 text-center mt-8">Tidak ada kegiatan dalam 7 hari kedepan</p>
                @else
                    <div class="space-y-4">
                        @foreach($upcomingEvents as $event)
                            <div class="flex items-start">
                                <div class="flex-shrink-0 mr-3 text-center">
                                    <div class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($event->due_date)->translatedFormat('l') }}</div>
                                    <div class="text-xl font-bold text-gray-200">{{ \Carbon\Carbon::parse($event->due_date)->format('d') }}</div>
                                    <div class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($event->due_date)->translatedFormat('M') }}</div>
                                </div>
                                <div class="flex-1 mt-1 border-l-2 border-green-500 pl-3">
                                    <p class="text-xs text-gray-400 font-semibold mb-1"><a href="{{ route('client.purchase-invoices.show', $event) }}" class="link-doc">{{ $event->invoice_number }}</a></p>
                                    <p class="text-sm text-gray-200">Jatuh Tempo Utang, {{ $event->supplier->name ?? 'Supplier' }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- 3. Tren Penjualan -->
        <div class="md:col-span-2 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-72">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200"><a href="{{ route('client.reports.sales-report') }}" class="hover:text-trusta-teal">Tren Penjualan <span class="text-[10px] text-gray-400 ml-1 font-normal">(7 hari terakhir)</span></a></h3>
                <button class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex-1 relative w-full h-full">
                <canvas id="salesTrendChart"></canvas>
            </div>
        </div>

        <!-- 4. Laba / Rugi Tahun ini -->
        <div class="md:col-span-2 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-72">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200"><a href="{{ route('client.reports.profit-loss') }}" class="hover:text-trusta-teal">Laba/Rugi Tahun ini</a></h3>
                <button class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex-1 flex items-center justify-between">
                <div class="w-1/2 relative h-full flex flex-col justify-center items-center">
                    <div class="w-32 h-32 relative">
                        <canvas id="plDonutChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-xs text-gray-400">Laba</span>
                            <span class="text-sm font-bold text-green-400">{{ $netProfit >= 0 ? '+' : '-' }}</span>
                        </div>
                    </div>
                </div>
                <div class="w-1/2 pl-2 flex flex-col justify-center space-y-3">
                    <div>
                        <div class="flex items-center text-xs mb-1"><span class="w-2 h-2 rounded-full bg-teal-400 mr-2"></span> <span class="text-gray-400">Pendapatan</span></div>
                        <div class="text-sm font-semibold text-gray-200 text-right">Rp {{ number_format($revenue, 2, ',', '.') }}</div>
                    </div>
                    <div>
                        <div class="flex items-center text-xs mb-1"><span class="w-2 h-2 rounded-full bg-red-400 mr-2"></span> <span class="text-gray-400">Nilai HPP</span></div>
                        <div class="text-sm font-semibold text-gray-200 text-right">Rp {{ number_format($cogs, 2, ',', '.') }}</div>
                    </div>
                    <div>
                        <div class="flex items-center text-xs mb-1"><span class="w-2 h-2 rounded-full bg-orange-400 mr-2"></span> <span class="text-gray-400">Beban</span></div>
                        <div class="text-sm font-semibold text-gray-200 text-right">Rp {{ number_format($expense, 2, ',', '.') }}</div>
                    </div>
                    <div class="pt-2 border-t border-gray-700">
                        <div class="text-xs text-gray-400">Laba Bersih</div>
                        <div class="text-sm font-bold {{ $netProfit >= 0 ? 'text-green-400' : 'text-red-400' }} text-right">Rp {{ number_format($netProfit, 2, ',', '.') }}</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 5. Arus Kas -->
        <div class="md:col-span-2 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-72">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200"><a href="{{ route('client.cash-bank.index') }}" class="hover:text-trusta-teal">Arus Kas <span class="text-[10px] text-gray-400 ml-1 font-normal">(7 hari terakhir)</span></a></h3>
                <button class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex-1 relative w-full h-full">
                <canvas id="cashFlowChart"></canvas>
            </div>
        </div>

        <!-- 6. Beban Perusahaan -->
        <div class="md:col-span-2 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-72">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200"><a href="{{ route('client.reports.profit-loss') }}" class="hover:text-trusta-teal">Beban Perusahaan <span class="text-[10px] text-gray-400 ml-1 font-normal">(Tahun ini)</span></a></h3>
                <button class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex-1 flex items-center">
                <div class="w-1/2 relative h-full flex flex-col justify-center items-center">
                    <div class="w-32 h-32 relative">
                        <canvas id="expenseDonutChart"></canvas>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-xs text-gray-400">Total Beban</span>
                        </div>
                    </div>
                </div>
                <div class="w-1/2 pl-2 flex flex-col justify-center space-y-2">
                    @php 
                        $colors = ['bg-pink-500', 'bg-yellow-500', 'bg-blue-400', 'bg-purple-500', 'bg-green-500'];
                        $totalTopExpense = $topExpenses->sum('total');
                    @endphp
                    @forelse($topExpenses as $idx => $exp)
                    <div>
                        <div class="flex items-center text-[10px] mb-1">
                            <span class="w-2 h-2 rounded-full {{ $colors[$idx % 5] }} mr-1"></span> 
                            <span class="text-gray-400 truncate w-full" title="{{ $exp->name }}">{{ Str::limit($exp->name, 15) }}</span>
                        </div>
                        <div class="text-xs font-semibold text-gray-200 text-right">Rp {{ number_format($exp->total, 2, ',', '.') }}</div>
                    </div>
                    @empty
                    <p class="text-xs text-gray-500">Belum ada data beban</p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- 7. Penjualan -->
        <div class="md:col-span-3 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-56">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200"><a href="{{ route('client.sales-invoices.index') }}" class="hover:text-trusta-teal">Penjualan <span class="text-[10px] text-gray-400 ml-1 font-normal">(Semua Data)</span></a></h3>
                <button class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex flex-col flex-1">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <p class="text-xs text-gray-400">Total Pendapatan</p>
                        <h4 class="text-xl font-bold text-gray-100">Rp {{ number_format($salesOverview['total'], 2, ',', '.') }}</h4>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-400">Total Belum Lunas</p>
                        <h4 class="text-xl font-bold text-gray-100">Rp {{ number_format($salesOverview['unpaid'], 2, ',', '.') }}</h4>
                    </div>
                </div>

                <div class="flex space-x-2 text-xs text-gray-400 mb-1">
                    <div class="w-1/4">Faktur Lunas</div>
                    <div class="w-1/4 text-center">Faktur Belum Lunas</div>
                    <div class="w-1/4 text-center">Belum Jatuh Tempo</div>
                    <div class="w-1/4 text-right">Lewat Jatuh Tempo</div>
                </div>
                <div class="flex space-x-2 text-xs font-semibold mb-2">
                    <div class="w-1/4 text-green-400">Rp {{ number_format($salesOverview['paid'], 2, ',', '.') }}</div>
                    <div class="w-1/4 text-center text-yellow-400">Rp {{ number_format($salesOverview['unpaid'], 2, ',', '.') }}</div>
                    <div class="w-1/4 text-center text-yellow-500">Rp {{ number_format($salesOverview['not_due'], 2, ',', '.') }}</div>
                    <div class="w-1/4 text-right text-red-400">Rp {{ number_format($salesOverview['overdue'], 2, ',', '.') }}</div>
                </div>
                <!-- Progress Bars -->
                <div class="flex space-x-1 h-3 mt-auto">
                    @php
                        $sTotal = $salesOverview['total'] > 0 ? $salesOverview['total'] : 1; // prevent div by zero
                        $sPaid = ($salesOverview['paid'] / $sTotal) * 100;
                        $sNotDue = ($salesOverview['not_due'] / $sTotal) * 100;
                        $sOverdue = ($salesOverview['overdue'] / $sTotal) * 100;
                    @endphp
                    <div class="bg-green-500 rounded-sm" style="width: {{ $sPaid }}%"></div>
                    <div class="bg-yellow-500 rounded-sm" style="width: {{ $sNotDue }}%"></div>
                    <div class="bg-red-500 rounded-sm" style="width: {{ $sOverdue }}%"></div>
                </div>
            </div>
        </div>

        <!-- 8. Pembelian -->
        <div class="md:col-span-3 bg-[#1E293B] border border-gray-700 rounded-lg shadow-lg flex flex-col h-56">
            <div class="p-3 border-b border-gray-700 flex justify-between items-center bg-gray-800/50 rounded-t-lg">
                <h3 class="text-sm font-semibold text-gray-200"><a href="{{ route('client.purchase-invoices.index') }}" class="hover:text-trusta-teal">Pembelian <span class="text-[10px] text-gray-400 ml-1 font-normal">(Semua Data)</span></a></h3>
                <button class="text-gray-400 hover:text-white"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg></button>
            </div>
            <div class="p-4 flex flex-col flex-1">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <p class="text-xs text-gray-400">Total Pembelian</p>
                        <h4 class="text-xl font-bold text-gray-100">Rp {{ number_format($purchaseOverview['total'], 2, ',', '.') }}</h4>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-400">Total Belum Lunas</p>
                        <h4 class="text-xl font-bold text-gray-100">Rp {{ number_format($purchaseOverview['unpaid'], 2, ',', '.') }}</h4>
                    </div>
                </div>

                <div class="flex space-x-2 text-xs text-gray-400 mb-1">
                    <div class="w-1/4">Faktur Lunas</div>
                    <div class="w-1/4 text-center">Faktur Belum Lunas</div>
                    <div class="w-1/4 text-center">Belum Jatuh Tempo</div>
                    <div class="w-1/4 text-right">Lewat Jatuh Tempo</div>
                </div>
                <div class="flex space-x-2 text-xs font-semibold mb-2">
                    <div class="w-1/4 text-green-400">Rp {{ number_format($purchaseOverview['paid'], 2, ',', '.') }}</div>
                    <div class="w-1/4 text-center text-yellow-400">Rp {{ number_format($purchaseOverview['unpaid'], 2, ',', '.') }}</div>
                    <div class="w-1/4 text-center text-yellow-500">Rp {{ number_format($purchaseOverview['not_due'], 2, ',', '.') }}</div>
                    <div class="w-1/4 text-right text-red-400">Rp {{ number_format($purchaseOverview['overdue'], 2, ',', '.') }}</div>
                </div>
                <!-- Progress Bars -->
                <div class="flex space-x-1 h-3 mt-auto">
                    @php
                        $pTotal = $purchaseOverview['total'] > 0 ? $purchaseOverview['total'] : 1; 
                        $pPaid = ($purchaseOverview['paid'] / $pTotal) * 100;
                        $pNotDue = ($purchaseOverview['not_due'] / $pTotal) * 100;
                        $pOverdue = ($purchaseOverview['overdue'] / $pTotal) * 100;
                    @endphp
                    <div class="bg-green-500 rounded-sm" style="width: {{ $pPaid }}%"></div>
                    <div class="bg-yellow-500 rounded-sm" style="width: {{ $pNotDue }}%"></div>
                    <div class="bg-red-500 rounded-sm" style="width: {{ $pOverdue }}%"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Initialization Script -->
    <script>
        Chart.defaults.color = '#94a3b8';
        Chart.defaults.font.family = "'Poppins', sans-serif";
        
        // 1. Sales Trend Line Chart
        new Chart(document.getElementById('salesTrendChart'), {
            type: 'line',
            data: {
                labels: {!! json_encode($salesTrendLabels) !!},
                datasets: [{
                    label: 'Penjualan',
                    data: {!! json_encode($salesTrend) !!},
                    borderColor: '#2DD4BF', // teal-400
                    backgroundColor: 'rgba(45, 212, 191, 0.1)',
                    borderWidth: 2,
                    pointBackgroundColor: '#1E293B',
                    pointBorderColor: '#2DD4BF',
                    pointBorderWidth: 2,
                    pointRadius: 4,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false, drawBorder: false } },
                    y: { 
                        grid: { color: 'rgba(255, 255, 255, 0.05)', drawBorder: false },
                        ticks: {
                            callback: function(value) {
                                if (value >= 1000000) return (value / 1000000) + 'Jt';
                                if (value >= 1000) return (value / 1000) + 'Rb';
                                return value;
                            }
                        }
                    }
                }
            }
        });

        // 2. Profit & Loss Donut
        new Chart(document.getElementById('plDonutChart'), {
            type: 'doughnut',
            data: {
                labels: ['Pendapatan', 'HPP', 'Beban'],
                datasets: [{
                    data: [{{ $revenue }}, {{ $cogs }}, {{ $expense }}],
                    backgroundColor: ['#2DD4BF', '#F87171', '#FB923C'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: { legend: { display: false } }
            }
        });

        // 3. Cash Flow Chart (Bar)
        new Chart(document.getElementById('cashFlowChart'), {
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
                plugins: { legend: { display: false } },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { 
                        stacked: true, 
                        grid: { color: 'rgba(255, 255, 255, 0.05)' },
                        ticks: {
                            callback: function(value) {
                                let v = Math.abs(value);
                                if (v >= 1000000) return (value / 1000000) + 'Jt';
                                if (v >= 1000) return (value / 1000) + 'Rb';
                                return value;
                            }
                        }
                    }
                }
            }
        });

        // 4. Expenses Donut
        @php 
            $expLabels = $topExpenses->pluck('name');
            $expData = $topExpenses->pluck('total');
        @endphp
        new Chart(document.getElementById('expenseDonutChart'), {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($expLabels) !!},
                datasets: [{
                    data: {!! json_encode($expData) !!},
                    backgroundColor: ['#EC4899', '#EAB308', '#60A5FA', '#A855F7', '#22C55E'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '75%',
                plugins: { legend: { display: false } }
            }
        });
    </script>
</x-app-layout>
