@php
    $isEdit = isset($fixedAsset);
    $hasJournal = $isEdit && !empty($acquisitionJournal);
    $checked = old('record_journal', $isEdit ? 0 : 1);
@endphp
<h3 class="text-lg font-medium text-white mb-4 mt-8 border-b border-gray-700 pb-2">Jurnal Perolehan</h3>

@if($hasJournal)
    <div class="rounded-lg border border-gray-700 bg-gray-800/50 p-4 text-sm text-gray-300">
        Jurnal perolehan sudah dibuat:
        <a href="{{ route('client.journals.show', $acquisitionJournal->id) }}" class="text-trusta-teal hover:underline">{{ $acquisitionJournal->ref_number }}</a>
        (Debit akun aset, Kredit sumber dana). Mengubah harga atau tanggal perolehan di atas ikut memperbarui jurnal ini.
    </div>
@else
    <label class="flex items-start gap-3 mb-4 cursor-pointer">
        <input type="checkbox" name="record_journal" value="1" id="record_journal" class="mt-1 rounded" {{ $checked ? 'checked' : '' }}>
        <span class="text-sm text-gray-300">
            Catat jurnal perolehan sekarang (Debit akun aset, Kredit kas/bank/hutang).
            <span class="block text-xs text-gray-500">Hilangkan centang bila aset ini sudah termasuk dalam Saldo Awal, agar tidak tercatat dua kali.</span>
        </span>
    </label>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="acqFields">
        <div>
            <label for="asset_account_id" class="block text-sm font-medium text-gray-300 mb-2">Akun Aset (Debit)</label>
            <select name="asset_account_id" id="asset_account_id" class="searchable-select w-full bg-gray-800 border-gray-700 text-white rounded-lg shadow-sm">
                <option value="">-- Pilih Akun Aset --</option>
                @foreach($assetAccounts as $a)
                    <option value="{{ $a->id }}" {{ (string) old('asset_account_id') === (string) $a->id ? 'selected' : '' }}>{{ $a->code }} - {{ $a->name }}</option>
                @endforeach
            </select>
            @error('asset_account_id')<p class="mt-1 text-sm text-red-400">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="funding_account_id" class="block text-sm font-medium text-gray-300 mb-2">Dibayar dari / Hutang (Kredit)</label>
            <select name="funding_account_id" id="funding_account_id" class="searchable-select w-full bg-gray-800 border-gray-700 text-white rounded-lg shadow-sm">
                <option value="">-- Pilih Kas / Bank / Hutang --</option>
                @foreach($fundingAccounts as $a)
                    <option value="{{ $a->id }}" {{ (string) old('funding_account_id') === (string) $a->id ? 'selected' : '' }}>{{ $a->code }} - {{ $a->name }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <script>
        (function () {
            var c = document.getElementById('record_journal'), box = document.getElementById('acqFields');
            function sync() { box.style.opacity = c.checked ? '1' : '.45'; box.querySelectorAll('select').forEach(function (s) { s.disabled = !c.checked; if (s.tomselect) { c.checked ? s.tomselect.enable() : s.tomselect.disable(); } }); }
            c.addEventListener('change', sync); document.addEventListener('DOMContentLoaded', sync); sync();
        })();
    </script>
@endif
