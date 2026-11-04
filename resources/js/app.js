import './bootstrap';
import Alpine from 'alpinejs';
import { initTheme } from './theme';
import { initSearch } from './search';
import { initForms } from './forms';

// Tema terang/gelap
initTheme();
initSearch();
initForms();

// Alpine hanya dijalankan sekali. Layout tidak lagi memuat Alpine dari CDN
// (sebelumnya dimuat dua kali sehingga komponen bisa terinisialisasi ganda).
if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}
