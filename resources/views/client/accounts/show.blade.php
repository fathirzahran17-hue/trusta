<x-app-layout>
    @section('title', 'Buku Besar Akun (Account Ledger)')
    @section('subtitle', 'Detail mutasi transaksi untuk akun: ' . $account->code . ' - ' . $account->name)

    <div class="mb-6 flex justify-between items-center">
        <a href="{{ route('client.accounts.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Akun
        </a>
        <button onclick="window.print()" class="px-4 py-2 bg-gray-800 text-white font-medium rounded-lg text-sm hover:bg-gray-700 transition-colors shadow-lg flex items-center border border-gray-600">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
            Cetak (Print)
        </button>
    </div>

    <!-- Ringkasan Akun -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-gray-800/60 border border-gray-700 rounded-2xl p-6">
            <h4 class="text-sm text-gray-400 mb-1">Kode Akun</h4>
            <p class="text-2xl font-bold font-mono text-white">{{ $account->code }}</p>
        </div>
        <div class="bg-gray-800/60 border border-gray-700 rounded-2xl p-6">
            <h4 class="text-sm text-gray-400 mb-1">Nama Akun</h4>
            <p class="text-xl font-bold text-white truncate">{{ $account->name }}</p>
        </div>
        <div class="bg-gray-800/60 border border-gray-700 rounded-2xl p-6">
            <h4 class="text-sm text-gray-400 mb-1">Tipe & Saldo Normal</h4>
            <p class="text-lg font-bold text-white capitalize">{{ $account->type }} <span class="text-gray-500 text-sm font-normal">({{ $account->normal_balance }})</span></p>
        </div>
        <div class="bg-gradient-to-br from-trusta-teal/20 to-gray-800 border border-trusta-teal/30 rounded-2xl p-6 shadow-lg shadow-trusta-teal/10">
            <h4 class="text-sm text-trusta-teal mb-1">Saldo Akhir</h4>
            <p class="text-2xl font-bold text-white tabular-nums">Rp {{ number_format($account->balance, 2, ',', '.') }}</p>
        </div>
    </div>

    <!-- Tabel Mutasi (Printable Area) -->
    <div id="printableArea" class="bg-gray-800/40 border border-gray-700/50 rounded-2xl overflow-hidden">
        
        <style>
            @media print {
                body * { visibility: hidden; }
                #printableArea, #printableArea * { visibility: visible; }
                #printableArea { position: absolute; left: 0; top: 0; width: 100%; box-shadow: none; background: white; color: black; }
                .text-white { color: black !important; }
                .text-gray-400 { color: #4b5563 !important; }
                table th { background: #f3f4f6 !important; color: #111827 !important; }
                table td { border-bottom: 1px solid #e5e7eb !important; }
                .no-print { display: none !important; }
            }
        </style>

        @include('partials.ledger-filter')

        @if($totalCount > 0 || (float) $account->opening_balance != 0.0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left text-gray-300">
                    <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="px-6 py-4 font-semibold w-32">Tanggal</th>
                            <th class="px-6 py-4 font-semibold w-40">No. Referensi</th>
                            <th class="px-6 py-4 font-semibold">Keterangan</th>
                            <th class="px-6 py-4 font-semibold text-right w-36">Debit</th>
                            <th class="px-6 py-4 font-semibold text-right w-36">Kredit</th>
                            <th class="px-6 py-4 font-semibold text-right w-40 text-trusta-teal">Saldo (Balance)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50">
                        <!-- Saldo Awal -->
                        <tr class="bg-gray-800/30">
                            <td class="px-6 py-4 text-gray-500">{{ $period['from'] ? \Carbon\Carbon::parse($period['from'])->format('d/m/Y') : '-' }}</td>
                            <td class="px-6 py-4 text-gray-500">-</td>
                            <td class="px-6 py-4 font-medium italic text-gray-400">{{ $period['from'] ? 'Saldo Awal Periode' : 'Saldo Awal (Opening Balance)' }}</td>
                            <td class="px-6 py-4 text-right text-gray-500">-</td>
                            <td class="px-6 py-4 text-right text-gray-500">-</td>
                            <td class="px-6 py-4 text-right font-bold text-white tabular-nums">
                                Rp {{ number_format($periodOpening, 2, ',', '.') }}
                            </td>
                        </tr>

                        <!-- Iterasi Mutasi -->
                        @php
                            $runningBalance = (float) $periodOpening;
                            $totalDebit = 0;
                            $totalCredit = 0;
                        @endphp
                        
                        @foreach ($mutations as $mut)
                            @php
                                $totalDebit += $mut->debit;
                                $totalCredit += $mut->credit;

                                if ($account->normal_balance == 'debit') {
                                    $runningBalance = $runningBalance + $mut->debit - $mut->credit;
                                } else {
                                    $runningBalance = $runningBalance + $mut->credit - $mut->debit;
                                }
                            @endphp
                            <tr class="hover:bg-gray-700/20 transition-colors">
                                <td class="px-6 py-4">
                                    {{ \Carbon\Carbon::parse($mut->journal->transaction_date)->format('d/m/Y') }}
                                </td>
                                <td class="px-6 py-4">
                                    <a href="{{ route('client.journals.show', $mut->journal->id) }}" class="text-trusta-teal hover:underline no-print">
                                        {{ $mut->journal->ref_number }}
                                    </a>
                                    <span class="hidden" style="display:none;" id="printRef">{{ $mut->journal->ref_number }}</span>
                                </td>
                                <td class="px-6 py-4 text-white">
                                    {{ $mut->description ?: $mut->journal->description }}
                                </td>
                                <td class="px-6 py-4 text-right tabular-nums text-gray-300">
                                    {{ $mut->debit > 0 ? number_format($mut->debit, 2, ',', '.') : '-' }}
                                </td>
                                <td class="px-6 py-4 text-right tabular-nums text-gray-300">
                                    {{ $mut->credit > 0 ? number_format($mut->credit, 2, ',', '.') : '-' }}
                                </td>
                                <td class="px-6 py-4 text-right font-bold tabular-nums {{ $runningBalance < 0 ? 'text-red-400' : 'text-white' }}">
                                    Rp {{ number_format($runningBalance, 2, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                        @if(count($mutations) === 0)
                            <tr><td colspan="6" class="px-6 py-8 text-center text-gray-500">Tidak ada mutasi pada periode ini. Ubah tanggal atau pilih "Semua".</td></tr>
                        @endif
                    </tbody>
                    <tfoot class="bg-gray-800/80 border-t border-gray-700">
                        <tr>
                            <td colspan="3" class="px-6 py-4 text-right font-bold text-gray-400">TOTAL MUTASI PERIODE:</td>
                            <td class="px-6 py-4 text-right font-bold text-white tabular-nums whitespace-nowrap">Rp {{ number_format($totalDebit, 2, ',', '.') }}</td>
                            <td class="px-6 py-4 text-right font-bold text-white tabular-nums whitespace-nowrap">Rp {{ number_format($totalCredit, 2, ',', '.') }}</td>
                            <td class="px-6 py-4 text-right font-bold text-trusta-teal tabular-nums text-lg whitespace-nowrap">Rp {{ number_format($runningBalance, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @else
            <div class="p-12 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                <h3 class="text-xl font-medium text-white mb-2">Belum ada transaksi</h3>
                <p class="text-gray-400">Akun ini belum memiliki riwayat mutasi / jurnal.</p>
            </div>
        @endif
    </div>

</x-app-layout>
