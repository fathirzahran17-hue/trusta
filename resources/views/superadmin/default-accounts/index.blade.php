<x-app-layout>
    @section('title', 'Template Bagan Akun (CoA)')
    @section('subtitle', 'Akun standar yang otomatis dibuat untuk perusahaan baru, sesuai bidang usahanya')

    @php
        $industries = \App\Services\CoaTemplate::INDUSTRIES;
        $typeLabels = ['asset' => 'Aset', 'liability' => 'Kewajiban', 'equity' => 'Ekuitas', 'revenue' => 'Pendapatan', 'cost_of_sales' => 'Harga Pokok', 'expense' => 'Beban Usaha', 'other_income' => 'Pendapatan Lain', 'other_expense' => 'Beban Lain'];
    @endphp

    <div x-data="coaTpl()">
        <div class="pay-tabs">
            <a href="{{ route('superadmin.default-accounts.index') }}" class="pay-tab {{ $tab === 'all' ? 'on' : '' }}">Semua <span class="n">{{ $counts['all'] }}</span></a>
            @foreach($industries as $key => $label)
                <a href="{{ route('superadmin.default-accounts.index', ['tab' => $key]) }}" class="pay-tab {{ $tab === $key ? 'on' : '' }}">{{ $label }} <span class="n">{{ $counts[$key] }}</span></a>
            @endforeach
            <span style="flex:1"></span>
            <button type="button" class="rpt-btn primary" @click="add()">+ Tambah Akun</button>
            <form action="{{ route('superadmin.default-accounts.reset') }}" method="POST" onsubmit="return confirm('Kembalikan template ke CoA standar TRUSTA? Perubahan Anda pada template akan hilang (akun perusahaan yang sudah ada tidak terpengaruh).')">
                @csrf <button type="submit" class="rpt-btn">Reset ke Standar</button>
            </form>
        </div>
        <p class="pay-flow">Perusahaan <b>Dagang &amp; Jasa</b> mendapat gabungan akun Jasa + Dagang. Akun bertanda "Semua" dipakai semua bidang. Perubahan template hanya berlaku untuk perusahaan baru.</p>

        <div class="pay-card" x-show="open" x-cloak>
            <div class="pay-card-head"><h3 x-text="id ? 'Ubah Akun Template' : 'Tambah Akun Template'"></h3></div>
            <form :action="id ? '{{ url('superadmin/default-accounts') }}/' + id : '{{ route('superadmin.default-accounts.store') }}'" method="POST">
                @csrf
                <template x-if="id"><input type="hidden" name="_method" value="PUT"></template>
                <div class="pay-form-grid">
                    <div class="pay-field"><label>Kode *</label><input type="text" name="code" x-model="f.code" required placeholder="1-1101"></div>
                    <div class="pay-field" style="grid-column: span 2"><label>Nama Akun *</label><input type="text" name="name" x-model="f.name" required></div>
                    <div class="pay-field"><label>Tipe *</label>
                        <select name="type" x-model="f.type">@foreach($typeLabels as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>
                    <div class="pay-field"><label>Saldo Normal *</label>
                        <select name="normal_balance" x-model="f.bal"><option value="debit">Debit</option><option value="credit">Kredit</option></select></div>
                    <div class="pay-field" style="grid-column: span 2"><label>Induk</label>
                        <select name="parent_id" x-model="f.parent"><option value="">— Tanpa induk —</option>@foreach($parentOptions as $po)<option value="{{ $po->id }}">{{ $po->code }} - {{ $po->name }}</option>@endforeach</select></div>
                    <div class="pay-field" style="grid-column: span 2"><label>Berlaku untuk bidang</label>
                        <div style="display:flex; gap:14px; flex-wrap:wrap; padding-top:6px;">
                            @foreach($industries as $key => $label)
                                <label style="display:flex; gap:6px; align-items:center; font-size:.82rem; color:var(--text)"><input type="checkbox" name="industries[]" value="{{ $key }}" :checked="f.ind.includes('{{ $key }}')" @change="toggle('{{ $key }}')"> {{ $label }}</label>
                            @endforeach
                        </div>
                        <div style="font-size:.68rem; color:var(--text-dim); margin-top:3px">Kosongkan semua (atau centang semua) = berlaku untuk semua bidang.</div>
                    </div>
                    <div class="pay-field"><label>Jenis</label>
                        <label style="display:flex; gap:6px; align-items:center; font-size:.82rem; color:var(--text); padding-top:6px"><input type="checkbox" name="is_system" value="1" x-model="f.header"> Akun induk (header)</label></div>
                </div>
                <div class="pay-foot">
                    <span class="pay-total">Nama akun penting untuk jurnal otomatis (mis. "Persediaan…", "Hutang Usaha", "Piutang Usaha") — ubah dengan hati-hati.</span>
                    <span><button type="button" class="rpt-btn" @click="open = false">Batal</button> <button type="submit" class="btn-primary" x-text="id ? 'Simpan' : 'Tambahkan'"></button></span>
                </div>
            </form>
        </div>

        <div class="pay-card">
            <div class="pay-scroll">
                <table class="pay-table">
                    <thead><tr><th>Kode</th><th>Nama Akun</th><th>Tipe</th><th class="c">Saldo Normal</th><th>Bidang</th><th class="c">Aksi</th></tr></thead>
                    <tbody>
                    @forelse($accounts as $a)
                        @php $depth = $a->parent_id ? ($a->parent && $a->parent->parent_id ? 2 : 1) : 0; $ind = array_filter(explode(',', (string) $a->industries)); @endphp
                        <tr style="{{ $a->is_system ? 'background: var(--hover-bg);' : '' }}">
                            <td style="font-family: ui-monospace, monospace; color: var(--accent); font-weight:700">{{ $a->code }}</td>
                            <td class="wrap" style="padding-left: {{ 12 + $depth * 18 }}px; {{ $a->is_system ? 'font-weight:700; color: var(--text-strong)' : '' }}">{{ $a->name }}</td>
                            <td>{{ $typeLabels[$a->type] ?? $a->type }}</td>
                            <td class="c">{{ $a->normal_balance === 'debit' ? 'Debit' : 'Kredit' }}</td>
                            <td>
                                @if(!$ind)<span style="color: var(--text-dim); font-size:.72rem">Semua</span>
                                @else @foreach($ind as $i)<span class="st st-partial" style="margin-right:3px">{{ $industries[$i] ?? $i }}</span>@endforeach @endif
                            </td>
                            <td class="c" style="white-space:nowrap">
                                <button type="button" class="pay-mini" @click="edit({{ $a->id }}, @js(['code' => $a->code, 'name' => $a->name, 'type' => $a->type, 'bal' => $a->normal_balance, 'parent' => (string) ($a->parent_id ?? ''), 'header' => (bool) $a->is_system, 'ind' => array_values($ind)]))">Ubah</button>
                                <form action="{{ route('superadmin.default-accounts.destroy', $a->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Hapus akun template {{ addslashes($a->name) }} beserta sub-akunnya?')">
                                    @csrf @method('DELETE') <button type="submit" class="pay-mini dng">Hapus</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="pay-empty">Template kosong. Klik "Reset ke Standar" untuk mengisi CoA standar.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function coaTpl() {
            const blank = { code: '', name: '', type: 'asset', bal: 'debit', parent: '', header: false, ind: [] };
            return {
                open: false, id: null, f: { ...blank },
                add() { this.id = null; this.f = { ...blank, ind: [] }; this.open = true; this.$nextTick(() => document.querySelector('.page-content').scrollTo({ top: 0, behavior: 'smooth' })); },
                edit(id, data) { this.id = id; this.f = { ...data }; this.open = true; this.$nextTick(() => document.querySelector('.page-content').scrollTo({ top: 0, behavior: 'smooth' })); },
                toggle(k) { this.f.ind = this.f.ind.includes(k) ? this.f.ind.filter(x => x !== k) : [...this.f.ind, k]; },
            };
        }
    </script>
</x-app-layout>
