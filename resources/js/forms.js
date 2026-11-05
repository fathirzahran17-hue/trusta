/**
 * Perilaku form di seluruh aplikasi:
 * pesan validasi berbahasa Indonesia, cegah kirim dua kali, peringatan data belum tersimpan,
 * pengisian yang lebih nyaman (Enter ke kolom berikutnya, angka langsung terpilih), dan pencarian barang/pihak.
 */

const MIN_OPTIONS_FOR_SEARCH = 7;
const SEARCHABLE = 'select[name="products[]"], select#supplier_id, select#customer_id';

function messageFor(el) {
    const v = el.validity;
    if (v.valueMissing) {
        if (el.tagName === 'SELECT' || el.closest('.ts-wrapper')) return 'Silakan pilih salah satu.';
        return el.type === 'checkbox' ? 'Wajib dicentang.' : 'Kolom ini wajib diisi.';
    }
    if (v.typeMismatch) return el.type === 'email' ? 'Format email belum benar, contoh: nama@perusahaan.com.' : 'Format isian belum benar.';
    if (v.badInput) return 'Isi dengan angka.';
    if (v.rangeUnderflow) return `Nilai minimal ${el.min}.`;
    if (v.rangeOverflow) return `Nilai maksimal ${el.max}.`;
    if (v.stepMismatch) return 'Angka di belakang koma terlalu banyak.';
    if (v.tooShort) return `Minimal ${el.minLength} karakter.`;
    if (v.tooLong) return `Maksimal ${el.maxLength} karakter.`;
    if (v.patternMismatch) return 'Format isian belum sesuai.';

    return el.validationMessage;
}

/** Pesan validasi tampil di bawah kolomnya, bukan gelembung bawaan browser yang bahasanya mengikuti perangkat. */
function initValidation() {
    const shown = [];
    let toFocus = null;

    const clearValid = () => {
        for (let i = shown.length - 1; i >= 0; i--) {
            const item = shown[i];
            if (!item.el.isConnected || item.el.validity.valid) {
                item.mark.classList.remove('is-invalid');
                item.msg.remove();
                shown.splice(i, 1);
            }
        }
    };

    document.addEventListener('invalid', (e) => {
        const el = e.target;
        if (!el.closest || !el.closest('form')) return;
        e.preventDefault();

        const wrapper = el.closest('.ts-wrapper') || (el.tomselect && el.tomselect.wrapper);
        const mark = wrapper || el;
        const anchor = wrapper || el.closest('.auth-input-wrap') || el.closest('.relative') || el;
        const existing = shown.find((s) => s.el === el);
        if (existing) {
            existing.msg.textContent = messageFor(el);
        } else {
            const msg = document.createElement('div');
            msg.className = 'field-msg';
            msg.textContent = messageFor(el);
            anchor.after(msg);
            mark.classList.add('is-invalid');
            shown.push({ el, mark, msg });
        }

        const details = el.closest('details:not([open])');
        if (details) details.open = true;

        if (!toFocus) {
            toFocus = el;
            setTimeout(() => {
                const target = toFocus;
                toFocus = null;
                const wrapped = target.closest('.ts-wrapper');
                const search = target.tomselect || (wrapped && wrapped.previousElementSibling && wrapped.previousElementSibling.tomselect);
                (search ? search.wrapper : target).scrollIntoView({ block: 'center', behavior: 'smooth' });
                if (search) {
                    // Fokus tanpa membuka daftar: mengetik atau mengeklik yang akan membukanya
                    const openOnFocus = search.settings.openOnFocus;
                    search.settings.openOnFocus = false;
                    search.focus();
                    setTimeout(() => { search.settings.openOnFocus = openOnFocus; }, 50);
                } else {
                    target.focus({ preventScroll: true });
                }
            }, 0);
        }
    }, true);

    document.addEventListener('input', clearValid);
    document.addEventListener('change', clearValid);
}

/** Kirim sekali saja: tombol menampilkan tanda proses, klik berikutnya diabaikan. */
function initSubmitGuard() {
    const reset = (form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('.is-submitting').forEach((b) => { b.classList.remove('is-submitting'); b.disabled = false; });
    };

    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (e.defaultPrevented || form.method !== 'post') return;
        const submitter = e.submitter;
        if (form.target || (submitter && submitter.formTarget)) return;
        if (form.dataset.submitting) {
            e.preventDefault();
            return;
        }
        form.dataset.submitting = '1';
        const button = submitter || form.querySelector('button[type="submit"], button:not([type])');
        // Ditunda agar nilai tombol tetap ikut terkirim
        setTimeout(() => {
            if (button) { button.classList.add('is-submitting'); button.disabled = true; }
        }, 0);
        setTimeout(() => reset(form), 30000); // respons berupa unduhan tidak memuat ulang halaman
    });

    window.addEventListener('pageshow', (e) => {
        if (e.persisted) document.querySelectorAll('form[data-submitting]').forEach(reset);
    });
}

/** Peringatan saat meninggalkan form yang sudah diisi tapi belum disimpan. */
function initUnsavedGuard() {
    const guarded = (form) => form && form.method === 'post' && form.closest('.page-content') && !form.hasAttribute('data-no-guard');

    document.addEventListener('input', (e) => {
        if (e.isTrusted && guarded(e.target.form)) e.target.form.dataset.dirty = '1';
    });

    // Kirim lewat skrip (mis. onchange="this.form.submit()") tidak memicu event submit
    const submit = HTMLFormElement.prototype.submit;
    HTMLFormElement.prototype.submit = function () {
        this.dataset.submitting = '1';
        return submit.call(this);
    };

    window.addEventListener('beforeunload', (e) => {
        if ([...document.forms].some((f) => f.dataset.dirty && !f.dataset.submitting)) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
}

/** Pengisian dengan keyboard: Enter pindah ke kolom berikutnya di form transaksi, angka langsung terpilih saat kolomnya dibuka. */
function initKeyboardComfort() {
    // Kotak Kena PPN dilewati: jarang diubah, dan Enter pada kotak centang bisa langsung mengirim form
    const isField = (x) => ['INPUT', 'SELECT', 'TEXTAREA'].includes(x.tagName) && !x.disabled && !x.readOnly && x.type !== 'hidden' && x.tabIndex >= 0 && x.offsetParent !== null && !x.classList.contains('row-taxable');

    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter' || e.isComposing || e.shiftKey || e.ctrlKey || e.metaKey) return;
        const el = e.target;
        if (el.tagName !== 'INPUT' || ['radio', 'submit', 'button', 'file'].includes(el.type) || (el.type === 'checkbox' && !el.classList.contains('row-taxable'))) return;
        if (el.closest('.ts-control') || !el.form || !el.form.querySelector('.line-items, .doc-card')) return;
        e.preventDefault();
        const all = [...el.form.elements];
        const next = all.slice(all.indexOf(el) + 1).find(isField);
        if (next) next.focus();
    });

    // Pemilihan harus terjadi saat itu juga: bila ditunda, ketikan yang sudah masuk bisa ikut terpilih dan tertimpa.
    let clickedInto = null;
    document.addEventListener('focusin', (e) => {
        const el = e.target;
        if (el.tagName !== 'INPUT' || el.readOnly) return;
        if (el.type === 'number' || el.classList.contains('input-price-display') || el.classList.contains('input-amount-display')) {
            el.select();
            clickedInto = el;
        }
    });
    // Klik pertama setelah kolom mendapat fokus tetap memilih seluruh angka, juga saat fokusnya datang otomatis
    // setelah memilih barang. Bila kolom sudah fokus, browser membatalkan pilihan sesudah mouseup, jadi harus dicegah.
    // Klik berikutnya tidak disentuh, agar kursor tetap bisa diletakkan untuk memperbaiki satu angka.
    document.addEventListener('mouseup', (e) => {
        if (clickedInto && e.target === clickedInto) {
            e.preventDefault();
            clickedInto.select();
        }
        clickedInto = null;
    });
    document.addEventListener('keydown', () => { clickedInto = null; }, true);

    // Menggulir halaman di atas kolom angka tidak boleh mengubah nilainya
    document.addEventListener('wheel', (e) => {
        if (e.target === document.activeElement && e.target.type === 'number') e.target.blur();
    }, { passive: true });
}

/** Total transaksi ikut tampil di bilah tombol simpan. */
function initTotalMirror() {
    const total = document.getElementById('displayGrandTotal');
    const mirror = document.querySelector('[data-total-mirror]');
    if (!total || !mirror) return;
    const sync = () => { mirror.textContent = total.textContent.trim(); };
    sync();
    new MutationObserver(sync).observe(total, { childList: true, characterData: true, subtree: true });
}

/** Barang, pemasok, dan pelanggan bisa dicari dengan mengetik bila pilihannya banyak. */
function initSearchableSelects() {
    const eligible = (sel) => !sel.tomselect && !sel.hasAttribute('x-model') && !sel.classList.contains('searchable-select') && sel.options.length >= MIN_OPTIONS_FOR_SEARCH;

    const attach = (sel) => {
        if (!window.TomSelect || !eligible(sel)) return;
        new window.TomSelect(sel, {
            create: false,
            render: { no_results: () => '<div class="no-results">Tidak ditemukan</div>' },
            onItemAdd() {
                // Selesai memilih barang: lanjut ke kolom jumlah
                const row = sel.closest('.item-row');
                if (!row || window.matchMedia('(pointer: coarse)').matches) return;
                const qty = row.querySelector('input[name="quantities[]"]');
                if (qty) setTimeout(() => qty.focus(), 0);
            },
        });
    };
    const detach = (sel) => {
        if (sel.tomselect) sel.tomselect.destroy();
    };
    window.TrustaSelect = { attach, detach };

    document.addEventListener('DOMContentLoaded', () => {
        // Setelah baris tarikan dokumen dan layout terisi
        setTimeout(() => {
            document.querySelectorAll(SEARCHABLE).forEach(attach);
            document.querySelectorAll('#itemsBody').forEach((body) => {
                new MutationObserver((records) => {
                    records.forEach((r) => r.addedNodes.forEach((n) => {
                        if (n.nodeType !== 1 || !n.querySelectorAll) return;
                        n.querySelectorAll(SEARCHABLE).forEach(attach);
                    }));
                }).observe(body, { childList: true });
            });
        }, 60);
    });
}

/**
 * Status PPN tiap baris dokumen dan hitungan PPN di layar.
 * Harus sama dengan aturan di app/Services/DocumentTax.php, karena yang tersimpan adalah hasil hitungan server.
 */
function initLineTax() {
    const on = (v) => v === true || v === 1 || v === '1';
    const LineTax = {
        isTaxable(row) {
            const hidden = row.querySelector('.input-taxable');
            return !hidden || hidden.value !== '0';
        },
        set(row, value) {
            const hidden = row.querySelector('.input-taxable'), box = row.querySelector('.row-taxable');
            if (hidden) hidden.value = on(value) ? '1' : '0';
            if (box) box.checked = on(value);
        },
        amount(base, rate) {
            const holder = document.querySelector('[data-rounding]');
            const mode = holder ? holder.dataset.rounding : '';
            // Membuang galat pecahan dulu, supaya 110.000,00 tidak terbaca sedikit di atasnya lalu naik sendiri
            const raw = Math.round((base * rate / 100) * 10000) / 10000;
            if (mode === 'Pembulatan Ke Atas') return Math.ceil(raw);
            if (mode === 'Pembulatan Ke Bawah') return Math.floor(raw);
            return Math.round(raw * 100) / 100;
        },
        /** Dipanggil dari calculateTotals: menampilkan kolom PPN dan DPP sesuai keadaan, lalu mengembalikan nilai PPN. */
        apply(base, rate, afterDiscount) {
            const table = document.getElementById('itemsTable');
            if (table) table.dataset.tax = rate > 0 ? 'on' : 'off';
            const row = document.getElementById('rowTaxBase'), out = document.getElementById('displayTaxBase');
            if (row && out) {
                row.hidden = !(rate > 0 && Math.abs(base - afterDiscount) > 0.004);
                out.textContent = base.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }
            return rate > 0 ? LineTax.amount(base, rate) : 0;
        },
    };
    window.LineTax = LineTax;

    const recalc = () => { if (typeof window.calculateTotals === 'function') window.calculateTotals(); };
    document.addEventListener('change', (e) => {
        const el = e.target;
        if (!el.classList) return;
        if (el.classList.contains('row-taxable')) {
            const row = el.closest('.item-row');
            if (row) LineTax.set(row, el.checked);
            recalc();
        } else if (el.classList.contains('product-select')) {
            // Barang baru dipilih: status PPN mengikuti data barang. Baris akun langsung dianggap kena PPN.
            const row = el.closest('.item-row');
            if (!row || !row.querySelector('.input-taxable')) return;
            const option = el.options[el.selectedIndex];
            LineTax.set(row, !option || option.dataset.taxable !== '0');
            recalc();
        }
    });
}

export function initForms() {
    initValidation();
    initSubmitGuard();
    initUnsavedGuard();
    initKeyboardComfort();
    initSearchableSelects();
    initLineTax();
    document.addEventListener('DOMContentLoaded', initTotalMirror);
}
