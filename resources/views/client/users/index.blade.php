<x-app-layout>
    @section('title', 'Manajemen Staf')
    @section('subtitle', 'Kelola akun karyawan yang memiliki akses ke sistem')

    <div class="mb-6 flex justify-between items-center">
        <div></div>
        <a href="{{ route('client.users.create') }}" class="px-4 py-2 bg-trusta-teal hover:bg-teal-400 text-navy-900 font-semibold rounded-lg text-sm transition-colors shadow-lg flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Akun Staf
        </a>
    </div>

    <div class="bg-[#1E293B] border border-gray-800 rounded-2xl shadow-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-[#0F172A] text-gray-400 uppercase text-xs">
                    <tr>
                        <th class="px-6 py-4">Nama Staf</th>
                        <th class="px-6 py-4">Email / Username</th>
                        <th class="px-6 py-4 text-center">Role</th>
                        <th class="px-6 py-4 text-center">Status</th>
                        <th class="px-6 py-4 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800">
                    @forelse($users as $user)
                    <tr class="hover:bg-[#0F172A]/50 transition-colors">
                        <td class="px-6 py-4 font-medium text-white">{{ $user->name }}</td>
                        <td class="px-6 py-4 text-gray-400">{{ $user->email }}</td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-medium
                                {{ $user->role === 'client_admin' ? 'bg-blue-900/50 text-blue-400' : 'bg-gray-800 text-gray-400' }}">
                                {{ $user->role === 'client_admin' ? 'Admin' : 'Staff' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($user->is_active)
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-emerald-900/50 text-emerald-400">Aktif</span>
                            @else
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-red-900/50 text-red-400">Non-Aktif</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <div class="flex items-center justify-center space-x-3">
                                <a href="{{ route('client.users.edit', $user) }}" class="text-trusta-teal hover:text-white transition-colors text-xs font-medium">Edit</a>
                                <form action="{{ route('client.users.destroy', $user) }}" method="POST" onsubmit="return confirm('Hapus akun staf ini?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-400 hover:text-red-300 transition-colors text-xs font-medium">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="px-6 py-16 text-center">
                            <svg class="w-12 h-12 text-gray-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                            <p class="text-gray-500 text-sm">Belum ada akun staf tambahan.</p>
                            <a href="{{ route('client.users.create') }}" class="mt-3 inline-block text-trusta-teal text-sm hover:underline">+ Tambah Staf Baru</a>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-app-layout>
