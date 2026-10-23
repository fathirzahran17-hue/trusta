<x-app-layout>
    @section('title', 'Rekonsiliasi Bank')
    @section('subtitle', 'Pencocokan saldo catatan perusahaan dengan rekening koran')
    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 bg-[#0F172A]"><h3 class="text-sm font-semibold text-gray-300 uppercase tracking-wider">Daftar Akun Bank & Kas</h3></div>
        <table class="w-full text-sm text-left">
            <thead class="bg-[#0F172A]/50 text-gray-400 uppercase text-xs"><tr><th class="px-6 py-3">Kode</th><th class="px-6 py-3">Nama Akun</th><th class="px-6 py-3">Tipe</th><th class="px-6 py-3 text-right">Saldo Catatan</th></tr></thead>
            <tbody class="divide-y divide-gray-800">
                @forelse($accounts as $acc)
                <tr class="hover:bg-[#0F172A]/50"><td class="px-6 py-3 font-mono text-trusta-teal">{{ $acc->code }}</td><td class="px-6 py-3 text-white font-medium">{{ $acc->name }}</td><td class="px-6 py-3 text-gray-400 capitalize">{{ $acc->type }}</td><td class="px-6 py-3 text-right text-white font-semibold tabular-nums">Rp {{ number_format(abs($acc->balance ?? 0), 2, ',', '.') }}</td></tr>
                @empty
                <tr><td colspan="4" class="px-6 py-10 text-center text-gray-500">Tidak ada akun bank/kas ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-app-layout>
