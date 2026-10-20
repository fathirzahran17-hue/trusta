/**
 * TRUSTA - Universal formatRupiah Helper
 * 
 * Menangani:
 * 1. Input langsung pengguna (angka, titik sebagai ribuan, koma sebagai desimal)
 * 2. Nilai mentah dari database (contoh: "1500000.00" atau 1500000)
 * 3. Parameter hiddenElement baik berupa HTMLElement maupun String ID
 */
function formatRupiah(element, hiddenElement) {
    if (!element) return;

    let raw = (element.value !== undefined && element.value !== null) ? element.value.toString().trim() : '';

    // Jika nilai berasal dari database (contoh: "1500000.00" atau "25000.50"),
    // deteksi: ada satu titik dan hanya angka (format float standar database)
    if (/^\d+\.\d+$/.test(raw)) {
        let num = parseFloat(raw);
        if (!isNaN(num)) {
            // Jika desimalnya .00, jadikan bilangan bulat
            if (num % 1 === 0) {
                raw = String(Math.round(num));
            } else {
                // Ubah titik desimal database menjadi koma desimal Indonesia
                raw = raw.replace('.', ',');
            }
        }
    }

    // Ambil digit dan koma saja (hapus titik pemisah ribuan lama atau karakter lain)
    let clean = raw.replace(/[^,\d]/g, '');
    let split = clean.split(',');
    let sisa = split[0].length % 3;
    let rupiah = split[0].substr(0, sisa);
    let ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }

    // Sambungkan bagian desimal jika ada
    if (split[1] !== undefined) {
        rupiah = rupiah + ',' + split[1].substring(0, 2); // maks 2 digit desimal
    }

    // Set nilai yang diformat ke input tampilan
    element.value = rupiah;

    // Hitung realValue untuk hiddenElement (format standar numerik database: desimal pakai titik)
    let realValue = 0;
    if (clean) {
        let numStr = split[0] + (split[1] !== undefined ? '.' + split[1].substring(0, 2) : '');
        realValue = parseFloat(numStr) || 0;
    }

    let targetHidden = hiddenElement;
    if (typeof hiddenElement === 'string') {
        targetHidden = document.getElementById(hiddenElement);
    }

    if (targetHidden) {
        targetHidden.value = realValue;
    }

    return realValue;
}

window.formatRupiah = formatRupiah;
