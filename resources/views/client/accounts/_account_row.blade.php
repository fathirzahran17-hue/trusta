@php
    $isHeader = $account->children->count() > 0;
    $padding = $level * 1.5 + 1.5; // in rem
@endphp

<tr class="{{ $isHeader ? 'bg-[#1a2333] border-b border-gray-700/50' : 'hover:bg-gray-700/20 border-b border-gray-700/30' }} group">
    <td class="py-3 font-mono text-gray-300 flex items-center" style="padding-left: {{ $padding }}rem; padding-right: 1.5rem;">
        @if($isHeader)
            <svg class="w-4 h-4 mr-2 text-trusta-gold" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
            <span class="font-bold text-white mr-3">{{ $account->code }}</span>
            <span class="font-bold text-white">{{ $account->name }}</span>
        @else
            <svg class="w-3 h-3 mr-2 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
            <span class="text-gray-400 mr-3">{{ $account->code }}</span>
            <span>{{ $account->name }}</span>
        @endif
        
        @if($account->is_system)
            <span class="ml-2 bg-gray-700 text-gray-400 text-[10px] px-2 py-0.5 rounded-full border border-gray-600">Sistem</span>
        @endif
    </td>
    <td class="px-6 py-2 capitalize text-xs {{ $isHeader ? 'font-semibold text-gray-300' : 'text-gray-400' }}">
        {{ $isHeader ? 'Header' : 'Detail' }}
    </td>
    <td class="px-6 py-2 text-right tabular-nums {{ $isHeader ? 'font-bold' : '' }} {{ $account->balance < 0 ? 'text-red-400' : ($isHeader ? 'text-white' : 'text-gray-300') }}">
        Rp {{ number_format($account->balance, 2, ',', '.') }}
    </td>
    <td class="px-6 py-2">
        <div class="flex items-center justify-center space-x-3 opacity-0 group-hover:opacity-100 transition-opacity">
            <a href="{{ route('client.accounts.show', $account->id) }}" class="text-trusta-teal hover:text-white transition-colors" title="Buku Besar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
            </a>
            <a href="{{ route('client.accounts.edit', $account->id) }}" class="text-gray-500 hover:text-white transition-colors" title="Edit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
            </a>
            @if(!$account->is_system)
                <form action="{{ route('client.accounts.destroy', $account->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus akun ini?');" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-gray-500 hover:text-red-400 transition-colors" title="Hapus">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    </button>
                </form>
            @endif
        </div>
    </td>
</tr>

@if($isHeader)
    @foreach($account->children as $child)
        @include('client.accounts._account_row', ['account' => $child, 'level' => $level + 1])
    @endforeach
@endif
