<x-app-layout>
    @section('title', 'Kartu Job / Proyek')
    @section('subtitle', 'Manajemen daftar proyek atau job untuk pelacakan transaksi')

    <div class="mb-6 flex justify-between items-center">
        <div></div>
        <a href="{{ route('client.project-jobs.create') }}" class="px-4 py-2 bg-trusta-teal text-navy-900 font-medium rounded-lg text-sm hover:bg-teal-400 transition-colors shadow-lg shadow-teal-900/50 flex items-center">
            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Tambah Job
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-500/10 border-l-4 border-green-500 p-4 text-green-400 rounded-r-lg">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-500/10 border-l-4 border-red-500 p-4 text-red-400 rounded-r-lg">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-gray-800 rounded-xl border border-gray-700 shadow-xl overflow-hidden">
        <div class="p-6">
            <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr>
                                    <th class="border-b dark:border-slate-700 p-4 font-semibold text-slate-600 dark:text-slate-300">Kode</th>
                                    <th class="border-b dark:border-slate-700 p-4 font-semibold text-slate-600 dark:text-slate-300">Nama Job</th>
                                    <th class="border-b dark:border-slate-700 p-4 font-semibold text-slate-600 dark:text-slate-300 text-right">Pendapatan</th>
                                    <th class="border-b dark:border-slate-700 p-4 font-semibold text-slate-600 dark:text-slate-300 text-right">Pengeluaran</th>
                                    <th class="border-b dark:border-slate-700 p-4 font-semibold text-slate-600 dark:text-slate-300 text-right">Laba/Rugi</th>
                                    <th class="border-b dark:border-slate-700 p-4 font-semibold text-slate-600 dark:text-slate-300 text-center">Status</th>
                                    <th class="border-b dark:border-slate-700 p-4 font-semibold text-slate-600 dark:text-slate-300 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($jobs as $job)
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors">
                                        <td class="border-b dark:border-slate-700 p-4">{{ $job->code ?? '-' }}</td>
                                        <td class="border-b dark:border-slate-700 p-4 font-medium">
                                            {{ $job->name }}
                                            <div class="text-xs text-slate-400 mt-1">{{ $job->description ?? '' }}</div>
                                        </td>
                                        <td class="border-b dark:border-slate-700 p-4 text-right font-mono text-sm text-green-400">Rp {{ number_format($job->income_total, 2, ',', '.') }}</td>
                                        <td class="border-b dark:border-slate-700 p-4 text-right font-mono text-sm text-red-400">Rp {{ number_format($job->expense_total, 2, ',', '.') }}</td>
                                        <td class="border-b dark:border-slate-700 p-4 text-right font-mono text-sm font-bold {{ $job->profit_total >= 0 ? 'text-green-400' : 'text-red-400' }}">Rp {{ number_format($job->profit_total, 2, ',', '.') }}</td>
                                        <td class="border-b dark:border-slate-700 p-4 text-center">
                                            @if($job->is_active)
                                                <span class="px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full dark:bg-green-900/30 dark:text-green-400">Aktif</span>
                                            @else
                                                <span class="px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full dark:bg-red-900/30 dark:text-red-400">Nonaktif</span>
                                            @endif
                                        </td>
                                        <td class="border-b dark:border-slate-700 p-4 text-center space-x-2">
                                            <a href="{{ route('client.project-jobs.edit', $job) }}" class="text-trusta-teal hover:text-teal-600" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form action="{{ route('client.project-jobs.destroy', $job) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kartu job ini?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-500 hover:text-red-700" title="Hapus">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="p-4 text-center text-slate-500 dark:text-slate-400">Belum ada data Kartu Job.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
            </div>
        </div>
    </div>
</x-app-layout>
