<x-app-layout>
    @section('title', 'Chart of Accounts')
    @section('subtitle', 'Daftar buku besar akun akuntansi perusahaan Anda')

    <div class="flex justify-between items-center mb-6">
        <div>
            <a href="{{ route('client.accounts.beginning_balance') }}" class="px-4 py-2 bg-gray-700 text-white font-medium rounded-lg text-sm hover:bg-gray-600 transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
                Setup Saldo Awal
            </a>
        </div>
        <a href="{{ route('client.accounts.create') }}" class="px-4 py-2 bg-trusta-teal text-navy-900 font-medium rounded-lg text-sm hover:bg-teal-400 transition-colors shadow-lg shadow-teal-900/50 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
            Tambah Akun
        </a>
    </div>

    @php
        $types = [
            'asset' => 'Asset',
            'liability' => 'Liability',
            'equity' => 'Equity',
            'revenue' => 'Income',
            'cost_of_sales' => 'Cost of Sales',
            'expense' => 'Expense',
            'other_income' => 'Other Income',
            'other_expense' => 'Other Expense'
        ];
    @endphp

    <div x-data="{ activeTab: 'asset' }" class="bg-[#1E293B] border border-gray-800 shadow-2xl rounded-2xl overflow-hidden">
        
        <!-- Tabs Header -->
        <div class="flex flex-wrap border-b border-gray-700/50">
            @foreach($types as $key => $label)
                <button @click="activeTab = '{{ $key }}'" 
                    :class="activeTab === '{{ $key }}' ? 'border-trusta-teal text-trusta-teal bg-gray-800/60' : 'border-transparent text-gray-400 hover:text-gray-300 hover:bg-gray-700/30'"
                    class="flex-1 min-w-[120px] py-3 px-4 text-center border-b-2 font-medium text-sm transition-all duration-200 whitespace-nowrap">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <!-- Tabs Content -->
        <div class="p-0">
            @foreach($types as $key => $label)
                <div x-show="activeTab === '{{ $key }}'" x-transition.opacity.duration.300ms style="display: none;">
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left text-gray-300">
                            <thead class="text-xs uppercase bg-gray-800/60 text-gray-400 border-b border-gray-700/50">
                                <tr>
                                    <th scope="col" class="px-6 py-4 font-semibold w-1/2">Nama Akun (Account Name)</th>
                                    <th scope="col" class="px-6 py-4 font-semibold">Tipe Akun (Type)</th>
                                    <th scope="col" class="px-6 py-4 font-semibold text-right">Saldo (Balance)</th>
                                    <th scope="col" class="px-6 py-4 text-center font-semibold">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700/50">
                                @php
                                    $filteredAccounts = $accounts->where('type', $key);
                                @endphp

                                @forelse ($filteredAccounts as $account)
                                    @include('client.accounts._account_row', ['account' => $account, 'level' => 0])
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-12 text-center text-gray-400">
                                            <p>Belum ada data akun untuk kategori ini.</p>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
