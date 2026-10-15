<x-app-layout>
    @section('title', 'Kelola Klien & Langganan')
    @section('subtitle', 'Manajemen perusahaan, pengguna, dan status langganan (SaaS)')

    <div x-data="clientManager()" class="bg-gray-900 border border-gray-800 rounded-2xl overflow-hidden shadow-2xl">
        <div class="px-6 py-5 border-b border-gray-800 flex justify-between items-center bg-gray-800/30">
            <h3 class="text-lg font-semibold text-white">Daftar Perusahaan Terdaftar</h3>
            <span class="bg-trusta-teal/10 text-trusta-teal text-xs font-bold px-3 py-1.5 rounded-lg border border-trusta-teal/20">
                Total: {{ $clients->count() }} Klien
            </span>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-gray-800/50 text-gray-400 text-xs uppercase tracking-widest border-b border-gray-800">
                        <th class="p-5 font-medium">Perusahaan</th>
                        <th class="p-5 font-medium text-center">Jml Pengguna</th>
                        <th class="p-5 font-medium">Paket Aktif (Plan)</th>
                        <th class="p-5 font-medium text-center">Status</th>
                        <th class="p-5 font-medium text-center">Premium</th>
                        <th class="p-5 font-medium text-center">Masa Berlaku</th>
                        <th class="p-5 font-medium text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800 text-sm">
                    @forelse($clients as $client)
                        @php
                            $activeSub = $client->subscriptions->first();
                            $statusColor = 'gray';
                            
                            if($activeSub) {
                                if($activeSub->status == 'active') $statusColor = 'emerald';
                                elseif($activeSub->status == 'trial') $statusColor = 'blue';
                                elseif($activeSub->status == 'expired') $statusColor = 'red';
                            }
                        @endphp
                        <tr class="hover:bg-gray-800/50 transition-colors">
                            <td class="p-5">
                                <div class="font-bold text-white text-base">{{ $client->name }}</div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ $client->email ?? 'Tanpa Email' }} | {{ $client->phone ?? 'Tanpa Telepon' }}
                                </div>
                                <div class="text-xs text-gray-600 mt-1">Terdaftar: {{ $client->created_at->format('d M Y') }}</div>
                            </td>
                            <td class="p-5 text-center">
                                <span class="inline-flex items-center justify-center bg-gray-800 border border-gray-700 text-gray-300 rounded-full w-8 h-8 font-bold text-xs">
                                    {{ $client->users->count() }}
                                </span>
                            </td>
                            <td class="p-5">
                                @if($activeSub && $activeSub->plan)
                                    <div class="font-bold text-gray-300">{{ $activeSub->plan->name }}</div>
                                    <div class="text-xs text-gray-500 mt-1">Rp {{ number_format($activeSub->plan->price, 0, ',', '.') }} / bln</div>
                                @else
                                    <span class="text-gray-500 italic">Belum ada paket</span>
                                @endif
                            </td>
                            <td class="p-5 text-center">
                                @if($activeSub)
                                    <span class="px-3 py-1 bg-{{$statusColor}}-900/30 text-{{$statusColor}}-400 border border-{{$statusColor}}-800/50 rounded-lg text-xs font-bold uppercase tracking-wider">
                                        {{ $activeSub->status }}
                                    </span>
                                @else
                                    <span class="px-3 py-1 bg-gray-800 text-gray-500 rounded-lg text-xs font-bold uppercase">None</span>
                                @endif
                            </td>
                            <td class="p-5 text-center">
                                @if($client->is_premium)
                                    <span class="text-trusta-teal text-xs font-bold border border-trusta-teal/30 bg-trusta-teal/10 px-2 py-1 rounded">PREMIUM</span>
                                @else
                                    <span class="text-gray-500 text-xs font-bold border border-gray-600 bg-gray-800 px-2 py-1 rounded">FREE / BASIC</span>
                                @endif
                            </td>
                            <td class="p-5 text-center">
                                @if($activeSub)
                                    <div class="text-gray-300 font-medium">{{ \Carbon\Carbon::parse($activeSub->end_date)->format('d M Y') }}</div>
                                    @if($activeSub->end_date < now() && $activeSub->status != 'expired')
                                        <div class="text-xs text-red-500 mt-1 font-bold">Harusnya Expired!</div>
                                    @endif
                                @else
                                    <span class="text-gray-500">-</span>
                                @endif
                            </td>
                            <td class="p-5 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    @if($client->is_premium && $activeSub)
                                        <form action="{{ route('superadmin.clients.revoke_premium', $client->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-red-900/30 text-red-400 border-red-800 hover:bg-red-900/50 rounded-lg text-xs font-medium transition-colors border">
                                                Cabut Premium
                                            </button>
                                        </form>
                                    @else
                                        <button type="button" @click="openGrantModal({{ $client->id }}, '{{ $client->name }}')" class="inline-flex items-center px-3 py-1.5 bg-trusta-teal/20 text-trusta-teal border-trusta-teal/50 hover:bg-trusta-teal/30 rounded-lg text-xs font-medium transition-colors border">
                                            Beri Premium
                                        </button>
                                    @endif
                                    @if($activeSub)
                                        <button @click="openModal({{ $client->id }}, '{{ $client->name }}', '{{ $activeSub->status }}', '{{ \Carbon\Carbon::parse($activeSub->end_date)->format('Y-m-d') }}')" class="inline-flex items-center px-3 py-1.5 bg-gray-700 hover:bg-gray-600 text-white rounded-lg text-xs font-medium transition-colors border border-gray-600" title="Ubah Status Langganan">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                                        </button>
                                    @endif
                                    <button type="button" @click="openResetPasswordModal({{ $client->id }}, '{{ $client->name }}')" class="inline-flex items-center px-3 py-1.5 bg-yellow-900/30 text-yellow-400 hover:bg-yellow-900/50 rounded-lg text-xs font-medium transition-colors border border-yellow-800" title="Reset Password">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    </button>
                                    <button type="button" @click="openDeleteModal({{ $client->id }}, '{{ $client->name }}')" class="inline-flex items-center px-3 py-1.5 bg-red-900/30 text-red-400 hover:bg-red-900/50 rounded-lg text-xs font-medium transition-colors border border-red-800" title="Hapus Perusahaan">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-gray-500">
                                <svg class="w-12 h-12 mx-auto text-gray-600 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                Belum ada perusahaan yang terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Edit Status Modal -->
        <div x-show="isModalOpen" style="display: none" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" x-transition>
            <div @click.away="isModalOpen = false" class="bg-gray-900 border border-gray-700 rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-xl font-bold text-white font-poppins">Ubah Status Langganan</h3>
                    <button @click="isModalOpen = false" class="text-gray-400 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form :action="`/superadmin/clients/${selectedCompanyId}/subscription`" method="POST">
                    @csrf
                    @method('PUT')
                    
                    <div class="mb-4 text-sm text-gray-400">
                        Klien: <span class="font-bold text-white" x-text="selectedCompanyName"></span>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Status Langganan</label>
                        <select name="status" x-model="selectedStatus" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg focus:ring-trusta-teal focus:border-trusta-teal text-sm">
                            <option value="trial">Trial (Percobaan)</option>
                            <option value="active">Active (Aktif)</option>
                            <option value="expired">Expired (Kedaluwarsa)</option>
                            <option value="cancelled">Cancelled (Dibatalkan)</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Masa Berlaku (End Date)</label>
                        <input type="date" name="end_date" x-model="selectedEndDate" required class="w-full bg-gray-800 border-gray-700 text-white rounded-lg focus:ring-trusta-teal focus:border-trusta-teal text-sm">
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" @click="isModalOpen = false" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg text-sm transition-colors border border-gray-700">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-trusta-teal hover:bg-teal-400 text-gray-900 font-bold rounded-lg text-sm transition-colors shadow-lg shadow-teal-900/50">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Grant Premium Modal -->
        <div x-show="isGrantModalOpen" style="display: none" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" x-transition>
            <div @click.away="isGrantModalOpen = false" class="bg-gray-900 border border-gray-700 rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-xl font-bold text-white font-poppins">Beri Akses Premium</h3>
                    <button @click="isGrantModalOpen = false" class="text-gray-400 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form :action="`/superadmin/clients/${selectedCompanyId}/grant-premium`" method="POST">
                    @csrf
                    
                    <div class="mb-4 text-sm text-gray-400">
                        Klien: <span class="font-bold text-white" x-text="selectedCompanyName"></span>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Pilih Paket</label>
                        <select name="plan_id" x-model="selectedPlanId" required class="w-full bg-gray-800 border-gray-700 text-white rounded-lg focus:ring-trusta-teal focus:border-trusta-teal text-sm">
                            <option value="">-- Pilih Paket --</option>
                            @foreach($plans as $plan)
                                <option value="{{ $plan->id }}">{{ $plan->name }} (Rp {{ number_format($plan->price, 0, ',', '.') }} / bln)</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Status Langganan</label>
                        <select name="status" x-model="selectedStatus" class="w-full bg-gray-800 border-gray-700 text-white rounded-lg focus:ring-trusta-teal focus:border-trusta-teal text-sm">
                            <option value="active">Active (Aktif)</option>
                            <option value="trial">Trial (Percobaan)</option>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Batas Waktu (End Date)</label>
                        <input type="date" name="end_date" x-model="selectedEndDate" required class="w-full bg-gray-800 border-gray-700 text-white rounded-lg focus:ring-trusta-teal focus:border-trusta-teal text-sm">
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" @click="isGrantModalOpen = false" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg text-sm transition-colors border border-gray-700">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-trusta-teal hover:bg-teal-400 text-gray-900 font-bold rounded-lg text-sm transition-colors shadow-lg shadow-teal-900/50">Beri Premium</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reset Password Modal -->
        <div x-show="isResetPasswordModalOpen" style="display: none" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" x-transition>
            <div @click.away="isResetPasswordModalOpen = false" class="bg-gray-900 border border-gray-700 rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-xl font-bold text-white font-poppins">Reset Password Klien</h3>
                    <button @click="isResetPasswordModalOpen = false" class="text-gray-400 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form :action="`/superadmin/clients/${selectedCompanyId}/reset-password`" method="POST">
                    @csrf
                    
                    <div class="mb-4 text-sm text-gray-400">
                        Klien: <span class="font-bold text-white" x-text="selectedCompanyName"></span>
                        <br>
                        <span class="text-xs text-yellow-500 mt-1 block">Tindakan ini akan mereset password untuk akun utama perusahaan ini secara sepihak.</span>
                    </div>

                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Password Baru <span class="text-red-400">*</span></label>
                        <input type="text" name="new_password" required minlength="8" placeholder="Minimal 8 karakter..." class="w-full bg-gray-800 border-gray-700 text-white rounded-lg focus:ring-trusta-teal focus:border-trusta-teal text-sm">
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" @click="isResetPasswordModalOpen = false" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg text-sm transition-colors border border-gray-700">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-500 text-white font-bold rounded-lg text-sm transition-colors shadow-lg">Reset Password</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div x-show="isDeleteModalOpen" style="display: none" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" x-transition>
            <div @click.away="isDeleteModalOpen = false" class="bg-gray-900 border border-red-800 rounded-2xl p-6 w-full max-w-md shadow-2xl">
                <div class="flex justify-between items-center mb-5">
                    <h3 class="text-xl font-bold text-red-400 font-poppins">⚠️ Hapus Perusahaan</h3>
                    <button @click="isDeleteModalOpen = false" class="text-gray-400 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                
                <form :action="`/superadmin/clients/${selectedCompanyId}`" method="POST">
                    @csrf
                    @method('DELETE')
                    
                    <div class="mb-4 p-4 bg-red-950/50 border border-red-800/50 rounded-xl">
                        <p class="text-sm text-red-300 font-medium mb-2">Anda akan menghapus perusahaan:</p>
                        <p class="text-lg font-bold text-white" x-text="selectedCompanyName"></p>
                        <p class="text-xs text-red-400 mt-3 leading-relaxed">
                            ⚠️ <strong>PERINGATAN:</strong> Semua data terkait perusahaan ini akan dihapus secara permanen, termasuk:
                            akun pengguna, akun perkiraan (CoA), jurnal, faktur penjualan, faktur pembelian, produk, pelanggan, supplier, dan langganan.
                        </p>
                        <p class="text-xs text-red-500 mt-2 font-bold">Tindakan ini TIDAK BISA dibatalkan!</p>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" @click="isDeleteModalOpen = false" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg text-sm transition-colors border border-gray-700">Batal</button>
                        <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-500 text-white font-bold rounded-lg text-sm transition-colors shadow-lg">Ya, Hapus Permanen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function clientManager() {
            return {
                isModalOpen: false,
                isGrantModalOpen: false,
                isResetPasswordModalOpen: false,
                isDeleteModalOpen: false,
                selectedCompanyId: null,
                selectedCompanyName: '',
                selectedStatus: '',
                selectedEndDate: '',
                selectedPlanId: '',

                openModal(id, name, status, endDate) {
                    this.selectedCompanyId = id;
                    this.selectedCompanyName = name;
                    this.selectedStatus = status;
                    this.selectedEndDate = endDate;
                    this.isModalOpen = true;
                },

                openGrantModal(id, name) {
                    this.selectedCompanyId = id;
                    this.selectedCompanyName = name;
                    this.selectedStatus = 'active';
                    
                    // Default to 1 year from now
                    let date = new Date();
                    date.setFullYear(date.getFullYear() + 1);
                    this.selectedEndDate = date.toISOString().split('T')[0];
                    
                    this.selectedPlanId = '';
                    this.isGrantModalOpen = true;
                },

                openResetPasswordModal(id, name) {
                    this.selectedCompanyId = id;
                    this.selectedCompanyName = name;
                    this.isResetPasswordModalOpen = true;
                },

                openDeleteModal(id, name) {
                    this.selectedCompanyId = id;
                    this.selectedCompanyName = name;
                    this.isDeleteModalOpen = true;
                }
            }
        }
    </script>
</x-app-layout>
