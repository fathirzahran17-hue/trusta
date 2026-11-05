<x-app-layout>
    @section('title', 'Pemetaan Akun Coretax')
    @section('subtitle', $lampiranList[$lampiran] . ' · Tahun Pajak ' . $year)

    @php $canEdit = auth()->user()->role !== 'client_staff'; @endphp

    <div x-data="{ show: 'all' }">
        <form method="GET" action="{{ url()->current() }}" class="rpt-toolbar">
            <div class="rpt-filters">
                <a href="{{ route('client.reports.coretax', ['lampiran' => $lampiran, 'tahun' => $year]) }}" class="rpt-btn">Kembali ke Rekonsiliasi</a>
                <div class="rpt-field">
                    <label for="f_tahun">Tahun Pajak</label>
                    <select id="f_tahun" name="tahun">
                        @foreach($years as $y)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endforeach
                    </select>
                </div>
                <div class="rpt-field">
                    <label for="f_lampiran">Formulir</label>
                    <select id="f_lampiran" name="lampiran">
                        @foreach($lampiranList as $key => $label)<option value="{{ $key }}" @selected($key === $lampiran)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <button type="submit" class="rpt-btn primary">Terapkan</button>
            </div>
            <div class="rpt-actions">
                <button type="button" class="rpt-btn" :class="show === 'all' && 'primary'" @click="show = 'all'">Semua Akun</button>
                <button type="button" class="rpt-btn" :class="show === 'check' && 'primary'" @click="show = 'check'">Perlu Dicek ({{ $needCheck }})</button>
            </div>
        </form>

        <div class="rpt-note info" style="margin-bottom:12px">
            Sistem memilih kode dari jenis dan nama akun. Ganti kodenya kalau kurang tepat. Pilihan Anda tersimpan dan selalu didahulukan.
            Akun berlabel Perlu Dicek hanya cocok lewat jenisnya, jadi pilih kodenya untuk memastikan.
        </div>

        @if($errors->any())
            <div class="rpt-note bad" style="margin-bottom:12px">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('client.reports.coretax.mapping.save', ['lampiran' => $lampiran, 'tahun' => $year]) }}">
            @csrf
            <div class="rpt-paper">
                <div class="rpt-body">
                    <table class="rpt-table">
                        <thead>
                            <tr>
                                <th>Kode</th>
                                <th>Nama Akun</th>
                                <th class="r" style="text-align:right">Saldo</th>
                                <th>Kode Coretax</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($groups as $type => $rows)
                                <tr class="section" x-show="show === 'all'"><td colspan="5">{{ $typeLabels[$type] ?? $type }}</td></tr>
                                @foreach($rows as $m)
                                    @php
                                        $a = $m['account'];
                                        $options = $choices[$type] ?? [];
                                        $isManual = $m['status'] === 'manual';
                                        $autoName = $options[$m['code']] ?? '';
                                    @endphp
                                    <tr x-show="show === 'all' || {{ $m['status'] === 'check' ? 'true' : 'false' }}">
                                        <td class="nowrap">{{ $a->code }}</td>
                                        <td>{{ $a->name }}</td>
                                        <td class="r {{ $m['amount'] < 0 ? 'neg' : '' }}">{{ \App\Support\ReportFormat::money($m['amount']) }}</td>
                                        <td>
                                            <select name="codes[{{ $a->id }}]" class="coretax-select" aria-label="Kode Coretax untuk {{ $a->name }}" @disabled(! $canEdit)>
                                                <option value="">{{ $isManual ? 'Kembali ke otomatis' : 'Otomatis: ' . $m['code'] . ' ' . $autoName }}</option>
                                                @foreach($options as $code => $name)
                                                    <option value="{{ $code }}" @selected($isManual && $m['code'] === (string) $code)>{{ $code }} {{ $name }}</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="nowrap">
                                            @if($isManual)
                                                <span class="badge badge-success">Pilihan Anda</span>
                                            @elseif($m['status'] === 'check')
                                                <span class="badge badge-danger">Perlu Dicek</span>
                                            @else
                                                <span class="badge coretax-auto">Otomatis</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if($canEdit)
                <div style="display:flex;justify-content:flex-end;margin-top:14px">
                    <button type="submit" class="rpt-btn primary">Simpan Pemetaan</button>
                </div>
            @endif
        </form>
    </div>
</x-app-layout>
