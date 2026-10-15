// Pengelola tema terang/gelap TRUSTA.
// Preferensi disimpan di localStorage dengan kunci "trusta-theme": "light" | "dark".
// Default: gelap (sesuai tampilan awal aplikasi).

const KEY = 'trusta-theme';
const root = document.documentElement;

function read() {
    try {
        return localStorage.getItem(KEY);
    } catch (e) {
        return null;
    }
}

function write(mode) {
    try {
        localStorage.setItem(KEY, mode);
    } catch (e) {
        /* penyimpanan diblokir: abaikan, tema tetap berlaku untuk halaman ini */
    }
}

export function currentTheme() {
    return root.classList.contains('dark') ? 'dark' : 'light';
}

export function applyTheme(mode, persist = true) {
    root.classList.toggle('dark', mode === 'dark');
    root.dataset.theme = mode;
    if (persist) write(mode);
    window.dispatchEvent(new CustomEvent('trusta:theme', { detail: { mode } }));
}

export function toggleTheme() {
    applyTheme(currentTheme() === 'dark' ? 'light' : 'dark');
}

export function initTheme() {
    // Skrip kecil di <head> sudah menerapkan tema awal (tanpa berkedip);
    // di sini kita hanya memastikan konsisten dan memasang tombol.
    const saved = read();
    applyTheme(saved === 'light' || saved === 'dark' ? saved : 'dark', false);

    document.addEventListener('click', (event) => {
        const btn = event.target.closest('[data-theme-toggle]');
        if (btn) {
            event.preventDefault();
            toggleTheme();
        }
    });

    // Sinkron antar tab
    window.addEventListener('storage', (event) => {
        if (event.key === KEY && (event.newValue === 'light' || event.newValue === 'dark')) {
            applyTheme(event.newValue, false);
        }
    });

    // Cetak selalu memakai tema terang
    let restoreDark = false;
    window.addEventListener('beforeprint', () => {
        restoreDark = root.classList.contains('dark');
        if (restoreDark) root.classList.remove('dark');
    });
    window.addEventListener('afterprint', () => {
        if (restoreDark) root.classList.add('dark');
    });
}

window.TrustaTheme = { currentTheme, applyTheme, toggleTheme };
