{{-- Jatuh tempo mengikuti pihak yang dipilih, lalu Preferensi. Berhenti mengikuti setelah diubah sendiri. Param: $partySelect, $defaultDays --}}
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var party = document.querySelector(@js($partySelect)), date = document.getElementById('invoice_date'), due = document.getElementById('due_date');
        if (!party || !date || !due) return;
        var touched = @js(old('due_date') !== null), fallback = @js((int) $defaultDays);
        due.addEventListener('input', function () { touched = true; });
        function pad(n) { return String(n).padStart(2, '0'); }
        function sync() {
            if (touched || !date.value) return;
            var o = party.options[party.selectedIndex], term = o ? parseInt(o.dataset.term, 10) : NaN;
            var d = new Date(date.value + 'T00:00:00');
            d.setDate(d.getDate() + (term > 0 ? term : fallback));
            due.value = d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        }
        party.addEventListener('change', sync);
        date.addEventListener('change', sync);
    });
</script>
