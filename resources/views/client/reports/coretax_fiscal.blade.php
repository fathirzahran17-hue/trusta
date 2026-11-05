<x-app-layout>
    @section('title', 'Penyesuaian Fiskal')
    @section('subtitle', $lampiranList[$lampiran] . ' · Tahun Pajak ' . $year)

    @php
        $canEdit = auth()->user()->role !== 'client_staff';
        $input = fn ($v) => (float) $v != 0 ? \App\Support\Money::fmt($v) : '';
    @endphp

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
    </form>

    <div class="rpt-note info" style="margin-bottom:12px">
        Kolom Komersial terisi otomatis dari Buku Besar. Isi kolom lainnya hanya bila ada penyesuaian. Penyesuaian positif dan negatif wajib memakai kode penyesuaian, boleh lebih dari satu, dipisahkan koma.
    </div>

    @if($errors->any())
        <div class="rpt-note bad" style="margin-bottom:12px">
            @foreach($errors->all() as $message)<div>{{ $message }}</div>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('client.reports.coretax.fiscal.save', ['lampiran' => $lampiran, 'tahun' => $year]) }}" id="fiscalForm">
        @csrf
        <div class="rpt-paper">
            <div class="rpt-body">
                <table class="rpt-table rpt-wide coretax-fiscal">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Akun</th>
                            <th style="text-align:right">Komersial</th>
                            <th style="text-align:right">Tidak Termasuk Objek Pajak</th>
                            <th style="text-align:right">Dikenakan PPh Final</th>
                            <th style="text-align:right">Penyesuaian Positif</th>
                            <th style="text-align:right">Penyesuaian Negatif</th>
                            <th>Kode Penyesuaian</th>
                            <th style="text-align:right">Nilai Fiskal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rows as $line)
                            @if($line['kind'] === 'header')
                                <tr class="section"><td colspan="9">{{ $line['name'] }}</td></tr>
                                @continue
                            @endif
                            @php
                                $code = $line['code'];
                                $v = $values[$code] ?? [];
                                $income = ! empty($line['income']);
                            @endphp
                            @if($line['kind'] === 'total')
                                <tr class="{{ $code === '4800' ? 'total' : 'subtotal' }}" data-total="{{ implode(',', $line['sum']) }}" data-code="{{ $code }}">
                                    <td>{{ $code }}</td>
                                    <td>{{ $line['name'] }}</td>
                                    <td class="r">{{ \App\Support\ReportFormat::money($v['commercial'] ?? 0) }}</td>
                                    <td colspan="5"></td>
                                    <td class="r" data-fiscal>{{ \App\Support\ReportFormat::money($v['fiscal'] ?? 0) }}</td>
                                </tr>
                            @else
                                <tr data-code="{{ $code }}" data-kind="{{ $income ? 'income' : (! empty($line['raises']) ? 'raises' : 'expense') }}" data-commercial="{{ (float) ($v['commercial'] ?? 0) }}">
                                    <td>{{ $code }}</td>
                                    <td>{{ $line['name'] }}</td>
                                    <td class="r">{{ \App\Support\ReportFormat::money($v['commercial'] ?? 0) }}</td>
                                    @foreach([['non_object', 'non_object'], ['final_income', 'final']] as [$field, $key])
                                        <td class="r">
                                            @if($income)
                                                <input type="text" inputmode="decimal" autocomplete="off" class="money-in coretax-in" data-col="{{ $field }}" name="rows[{{ $code }}][{{ $field }}]" value="{{ old("rows.$code.$field", $input($v[$key] ?? 0)) }}" aria-label="{{ $line['name'] }}" @disabled(! $canEdit)>
                                            @endif
                                        </td>
                                    @endforeach
                                    @foreach(['positive', 'negative'] as $field)
                                        <td class="r">
                                            <input type="text" inputmode="decimal" autocomplete="off" class="money-in coretax-in" data-col="{{ $field }}" name="rows[{{ $code }}][{{ $field }}]" value="{{ old("rows.$code.$field", $input($v[$field] ?? 0)) }}" aria-label="{{ $line['name'] }}" @disabled(! $canEdit)>
                                        </td>
                                    @endforeach
                                    <td>
                                        <input type="text" autocomplete="off" list="coretaxCodes" class="coretax-in coretax-codes" name="rows[{{ $code }}][adjustment_codes]" value="{{ old("rows.$code.adjustment_codes", $v['codes'] ?? '') }}" aria-label="Kode penyesuaian {{ $line['name'] }}" @disabled(! $canEdit)>
                                    </td>
                                    <td class="r" data-fiscal>{{ \App\Support\ReportFormat::money($v['fiscal'] ?? 0) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
                <datalist id="coretaxCodes">
                    @foreach($adjustmentCodes as $key => $meaning)<option value="{{ $key }}">{{ $meaning }}</option>@endforeach
                </datalist>
            </div>
        </div>

        <div class="rpt-note info" style="margin-top:12px">
            <strong>Kode yang sering dipakai:</strong>
            @foreach($adjustmentCodes as $key => $meaning){{ $key }} {{ $meaning }}@if(! $loop->last); @endif @endforeach.
            Kode lain tetap bisa diketik, cek artinya di Coretax.
        </div>

        @if($canEdit)
            <div style="display:flex;justify-content:flex-end;margin-top:14px">
                <button type="submit" class="rpt-btn primary">Simpan Penyesuaian</button>
            </div>
        @endif
    </form>

    <script>
    (function () {
        var form = document.getElementById('fiscalForm');
        if (!form) return;
        var fmt = function (n) {
            if (Math.abs(n) < 0.005) return '-';
            var s = window.fmtAmount(Math.abs(n));
            return n < 0 ? '(' + s + ')' : s;
        };
        var read = function (row, col) {
            var el = row.querySelector('[data-col="' + col + '"]');
            return el && el.value ? (window.parseMoney(el.value) || 0) : 0;
        };
        var recalc = function () {
            var fiscal = {};
            form.querySelectorAll('tr[data-code]').forEach(function (row) {
                var value;
                if (row.dataset.total) {
                    value = row.dataset.total.split(',').reduce(function (sum, op) {
                        return sum + (op[0] === '-' ? -1 : 1) * (fiscal[op.slice(1)] || 0);
                    }, 0);
                } else {
                    var base = parseFloat(row.dataset.commercial) || 0;
                    var pos = read(row, 'positive'), neg = read(row, 'negative');
                    if (row.dataset.kind === 'income') value = base - read(row, 'non_object') - read(row, 'final_income') + pos - neg;
                    else if (row.dataset.kind === 'raises') value = base + pos - neg;
                    else value = base - pos + neg;
                }
                fiscal[row.dataset.code] = value;
                var cell = row.querySelector('[data-fiscal]');
                cell.textContent = fmt(value);
                cell.classList.toggle('neg', value < 0);
            });
        };
        form.addEventListener('input', function (e) { if (e.target.classList.contains('money-in')) recalc(); });
        form.addEventListener('change', recalc);
    })();
    </script>
</x-app-layout>
