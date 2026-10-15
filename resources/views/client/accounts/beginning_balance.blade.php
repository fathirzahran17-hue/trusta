<x-app-layout>
@section('title', 'Saldo Awal Akun')
@section('subtitle', 'Masukkan saldo awal sebelum memulai transaksi di sistem')

<div class="flex items-center justify-between mb-6">
    <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl px-4 py-3 flex items-start gap-3 flex-1 mr-4">
        <svg class="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-amber-300/80">
            Jika total Debit dan Kredit tidak seimbang, sistem akan otomatis membuat akun <em>"Opening Balance Equity"</em> untuk menyeimbangkan neraca.
        </p>
    </div>
    <a href="{{ route('client.accounts.index') }}"
        class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 text-sm font-medium rounded-xl transition-colors flex items-center gap-2 flex-shrink-0">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
        Kembali ke Akun
    </a>
</div>

<div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
    <form action="{{ route('client.accounts.store_beginning_balance') }}" method="POST">
        @csrf

        {{-- Header form --}}
        <div class="px-6 py-4 border-b border-gray-800 flex flex-wrap items-center gap-4">
            <div>
                <label class="block text-xs text-gray-400 mb-1">Tanggal Saldo Awal</label>
                <input type="date" name="date" required
                    value="{{ old('date', date('Y-m-d')) }}"
                    class="bg-gray-900 border border-gray-700 text-white rounded-lg px-3 py-2 text-sm focus:ring-trusta-teal focus:border-trusta-teal">
            </div>
            <p class="text-xs text-gray-500 mt-4">Pilih tanggal cut-off neraca Anda</p>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-[#0F172A] text-gray-400 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 w-24">Kode</th>
                        <th class="px-4 py-3">Nama Akun</th>
                        <th class="px-4 py-3 w-28">Kategori</th>
                        <th class="px-4 py-3 w-24 text-center">Saldo Normal</th>
                        <th class="px-4 py-3 w-52 text-right">Saldo Awal (Rp)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/50">
                    @foreach($accounts as $account)
                    <tr class="hover:bg-gray-800/30 transition-colors {{ !$account->parent_id ? 'bg-gray-900/30' : '' }}">
                        <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ $account->code }}</td>
                        <td class="px-4 py-3 {{ !$account->parent_id ? 'font-semibold text-white' : 'text-gray-300 pl-8' }}">
                            {{ $account->name }}
                        </td>
                        <td class="px-4 py-3 text-gray-400 text-xs">
                            @switch($account->type)
                                @case('asset') Aset @break
                                @case('liability') Kewajiban @break
                                @case('equity') Ekuitas @break
                                @case('revenue') Pendapatan @break
                                @case('expense') Beban @break
                                @default {{ ucfirst($account->type) }}
                            @endswitch
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $account->normal_balance == 'debit'
                                    ? 'bg-blue-500/10 text-blue-400 border border-blue-500/20'
                                    : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}">
                                {{ $account->normal_balance == 'debit' ? 'Debit' : 'Kredit' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if(!$account->parent_id)
                                <span class="text-xs text-gray-500 italic">—</span>
                            @else
                                <input type="number"
                                    name="balances[{{ $account->id }}]"
                                    value="{{ old('balances.'.$account->id, $account->opening_balance ?? 0) }}"
                                    step="0.01" min="0"
                                    class="w-full bg-gray-900 border border-gray-700 text-white text-right rounded-lg px-3 py-1.5 text-sm focus:ring-trusta-teal focus:border-trusta-teal focus:bg-gray-800 transition-colors">
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Footer actions --}}
        <div class="px-6 py-4 border-t border-gray-800 flex justify-end gap-3">
            <a href="{{ route('client.accounts.index') }}"
                class="px-5 py-2.5 bg-gray-800 hover:bg-gray-700 text-gray-300 font-medium rounded-xl text-sm transition-colors">
                Batal
            </a>
            <button type="submit"
                class="px-5 py-2.5 bg-trusta-teal hover:bg-teal-400 text-navy-900 font-bold rounded-xl text-sm transition-colors shadow-lg flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                Simpan Saldo Awal
            </button>
        </div>
    </form>
</div>
</x-app-layout>
