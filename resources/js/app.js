import './bootstrap';
import Alpine from 'alpinejs';
import { initTheme } from './theme';

// Tema terang/gelap
initTheme();

// Alpine hanya dijalankan sekali. Layout tidak lagi memuat Alpine dari CDN
// (sebelumnya dimuat dua kali sehingga komponen bisa terinisialisasi ganda).
if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}
