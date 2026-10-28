<x-app-layout>
    @section('title', 'Detail Jurnal Umum')
    @section('subtitle', 'Ref: ' . $journal->ref_number)

    <div class="mb-6 flex justify-between items-center">
        <a href="{{ route('client.journals.index') }}" class="text-trusta-teal hover:text-white flex items-center text-sm transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Kembali ke Daftar Jurnal
        </a>

        <div class="flex space-x-3">
            @if($source)
                @if($source['url'])
                <a href="{{ $source['url'] }}" class="px-4 py-2 border border-trusta-teal/50 text-trusta-teal hover:bg-trusta-teal hover:text-navy-900 rounded-lg text-sm font-medium transition-colors flex items-center">
                    Buka {{ $source['label'] }}{{ $source['number'] ? ' ' . $source['number'] : '' }}
                </a>
                @endif
                @if($source['cancel'])
                <form action="{{ $source['cancel']['url'] }}" method="POST" onsubmit="return confirm(@js($source['cancel']['confirm']));" class="inline">
                    @csrf
                    <button type="submit" class="px-4 py-2 border border-red-500/50 text-red-400 hover:bg-red-500 hover:text-white rounded-lg text-sm font-medium transition-colors flex items-center">
                        {{ $source['cancel']['label'] }}
                    </button>
                </form>
                @endif
            @else
            @if($journal->status !== 'void')
            <a href="{{ route('client.journals.edit', $journal->id) }}" class="px-4 py-2 border border-trusta-teal/50 text-trusta-teal hover:bg-trusta-teal hover:text-navy-900 rounded-lg text-sm font-medium transition-colors flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                Ubah Jurnal
            </a>
            
            <form action="{{ route('client.journals.destroy', $journal->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin membatalkan (void) jurnal ini? Saldo akun akan dikembalikan seperti semula.');" class="inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-4 py-2 border border-red-500/50 text-red-400 hover:bg-red-500 hover:text-white rounded-lg text-sm font-medium transition-colors flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Void Jurnal
                </button>
            </form>
            @endif
            @endif
        </div>
    </div>

    @if($source)
    <div class="mb-4 max-w-4xl" style="border:1px solid var(--border, rgba(255,255,255,.1)); border-radius:10px; padding:10px 14px; font-size:.82rem; color:var(--text-muted, #94a3b8);">
        Jurnal ini dibuat otomatis oleh <b style="color:var(--text-strong, #fff)">{{ $source['label'] }}{{ $source['number'] ? ' ' . $source['number'] : '' }}</b>, jadi tidak bisa diubah/di-void langsung di sini.
        @if($source['cancel']) Gunakan tombol <b>{{ $source['cancel']['label'] }}</b>: catatannya tetap ada (ditandai Dibatalkan), dibuat jurnal balik, dan dokumen terkait kembali terbuka.
        @elseif($source['url']) Batalkan atau ubah lewat dokumennya.@endif
    </div>
    @endif

    <div class="bg-[#1E293B] border border-gray-800 shadow-2xl rounded-2xl overflow-hidden max-w-4xl">
        <div class="p-8 border-b border-gray-700">
            <div class="flex justify-between items-start">
                <div>
                    <h2 class="text-2xl font-bold text-white mb-2">Jurnal Umum</h2>
                    <p class="text-gray-400">{{ $journal->description }}</p>
                </div>
                <div class="text-right">
                    <div class="text-sm text-gray-400 mb-1">Nomor Referensi</div>
                    <div class="text-xl font-bold text-trusta-teal">{{ $journal->ref_number }}</div>
                    
                    <div class="mt-4 text-sm text-gray-400 mb-1">Tanggal Transaksi</div>
                    <div class="text-white font-medium">{{ $journal->transaction_date->format('d F Y') }}</div>
                </div>
            </div>
        </div>

        <div class="p-8 bg-gray-900/50">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="text-xs uppercase bg-gray-800/80 text-gray-400 border-b border-gray-700">
                        <tr>
                            <th class="px-4 py-3 rounded-tl-lg">Kode Akun</th>
                            <th class="px-4 py-3">Nama Akun</th>
                            <th class="px-4 py-3">Keterangan</th>
                            <th class="px-4 py-3">Kartu Job</th>
                            <th class="px-4 py-3 text-right">Debit</th>
                            <th class="px-4 py-3 rounded-tr-lg text-right">Kredit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50 text-gray-300">
                        @foreach($journal->details as $detail)
                            <tr>
                                <td class="px-4 py-4 font-mono text-xs">{{ $detail->account->code }}</td>
                                <td class="px-4 py-4 font-medium text-white">{{ $detail->account->name }}</td>
                                <td class="px-4 py-4 text-gray-400 italic">{{ $detail->description ?: '-' }}</td>
                                <td class="px-4 py-4 text-gray-400">
                                    @if($detail->projectJob)
                                        <a href="{{ route('client.project-jobs.show', $detail->projectJob) }}" class="px-2 py-1 bg-slate-700/50 rounded-full text-xs text-slate-300 hover:text-trusta-teal">
                                            {{ $detail->projectJob->code ? $detail->projectJob->code.' - ' : '' }}{{ $detail->projectJob->name }}
                                        </a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right">
                                    {{ $detail->debit > 0 ? 'Rp ' . number_format($detail->debit, 2, ',', '.') : '-' }}
                                </td>
                                <td class="px-4 py-4 text-right">
                                    {{ $detail->credit > 0 ? 'Rp ' . number_format($detail->credit, 2, ',', '.') : '-' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-gray-600 bg-gray-800/80">
                        <tr>
                            <td colspan="4" class="px-4 py-4 text-right font-bold text-white uppercase text-xs">Total</td>
                            <td class="px-4 py-4 text-right font-bold text-trusta-teal text-lg">Rp {{ number_format($journal->total_debit, 2, ',', '.') }}</td>
                            <td class="px-4 py-4 text-right font-bold text-trusta-teal text-lg">Rp {{ number_format($journal->total_credit, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-8 flex justify-between items-center text-sm">
                <div class="text-gray-500">
                    Dibuat oleh: <span class="text-gray-300">{{ $journal->createdBy->name ?? '-' }}</span> pada {{ $journal->created_at->format('d M Y, H:i') }}
                </div>
                <div>
                    Status: 
                    @if($journal->status === 'posted')
                        <span class="text-green-400 font-bold uppercase">Posted</span>
                    @elseif($journal->status === 'void')
                        <span class="text-red-400 font-bold uppercase">Void</span>
                    @else
                        <span class="text-gray-400 font-bold uppercase">{{ $journal->status }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @php
        $src = \App\Services\DocumentLinker::sourceOfJournal($journal);
        $jobs = $journal->details->pluck('projectJob')->filter()->unique('id');
        $relGroups = [
            ['label' => 'Dokumen Sumber', 'items' => $src ? [['label' => $src['number'], 'url' => $src['url'], 'meta' => $src['label']]] : []],
            ['label' => 'Kartu Job', 'items' => $jobs->map(fn ($j) => ['label' => $j->name, 'url' => route('client.project-jobs.show', $j), 'meta' => $j->code])->values()->all()],
        ];
    @endphp
    <div class="max-w-4xl mx-auto mt-6">
        @include('partials.related-docs', ['groups' => $relGroups])
    </div>
</x-app-layout>
