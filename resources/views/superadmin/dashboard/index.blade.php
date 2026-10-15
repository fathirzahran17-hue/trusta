<x-app-layout>
    @section('title', 'Super Admin Dashboard')
    @section('subtitle', 'Overview platform TRUSTA')

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <!-- Stat Card 1 -->
        <div class="bg-gray-800/40 border border-gray-700/50 p-6 rounded-2xl">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-xl bg-trusta-teal/10 flex items-center justify-center text-trusta-teal">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                </div>
                <span class="text-xs font-semibold text-trusta-teal bg-trusta-teal/10 px-2.5 py-1 rounded-full">+12%</span>
            </div>
            <h3 class="text-3xl font-poppins font-bold text-white mb-1">{{ number_format($totalCompanies) }}</h3>
            <p class="text-sm text-gray-400">Total Perusahaan (Client)</p>
        </div>

        <!-- Stat Card 2 -->
        <div class="bg-gray-800/40 border border-gray-700/50 p-6 rounded-2xl">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-xl bg-trusta-gold/10 flex items-center justify-center text-trusta-gold">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <h3 class="text-3xl font-poppins font-bold text-white mb-1">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</h3>
            <p class="text-sm text-gray-400">Pendapatan Subscription</p>
        </div>

        <!-- Stat Card 3 -->
        <div class="bg-gray-800/40 border border-gray-700/50 p-6 rounded-2xl">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-xl bg-purple-500/10 flex items-center justify-center text-purple-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                </div>
            </div>
            <h3 class="text-3xl font-poppins font-bold text-white mb-1">{{ number_format($totalUsers) }}</h3>
            <p class="text-sm text-gray-400">Total Pengguna Terdaftar</p>
        </div>

        <!-- Stat Card 4 -->
        <div class="bg-gray-800/40 border border-gray-700/50 p-6 rounded-2xl">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 rounded-xl bg-red-500/10 flex items-center justify-center text-red-400">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
            </div>
            <h3 class="text-3xl font-poppins font-bold text-white mb-1">{{ number_format($expiredSubscriptions) }}</h3>
            <p class="text-sm text-gray-400">Langganan Kedaluwarsa</p>
        </div>
    </div>

    <!-- Pendaftaran Klien Baru -->
    <div class="bg-gray-800/40 border border-gray-700/50 rounded-2xl overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-700/50 flex justify-between items-center bg-gray-800/20">
            <h3 class="text-lg font-semibold text-white">Klien (Perusahaan) Terbaru</h3>
        </div>
        <div class="overflow-x-auto">
            @if($recentCompanies->count() > 0)
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-gray-800/50 text-gray-400 text-xs uppercase tracking-widest border-b border-gray-700/50">
                            <th class="p-4 font-medium">Nama Perusahaan</th>
                            <th class="p-4 font-medium">Email</th>
                            <th class="p-4 font-medium">No. Telepon</th>
                            <th class="p-4 font-medium">Tanggal Bergabung</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700/50 text-sm">
                        @foreach($recentCompanies as $company)
                        <tr class="hover:bg-gray-800/50 transition-colors">
                            <td class="p-4 text-white font-medium">{{ $company->name }}</td>
                            <td class="p-4 text-gray-400">{{ $company->email ?? '-' }}</td>
                            <td class="p-4 text-gray-400">{{ $company->phone ?? '-' }}</td>
                            <td class="p-4 text-gray-400">{{ $company->created_at->format('d M Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="p-6 text-center text-gray-400">
                    Belum ada klien yang terdaftar.
                </div>
            @endif
        </div>
    </div>
    </div>

</x-app-layout>
