import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // Varian dark: mengikuti class "dark" pada <html> (tombol ganti tema),
    // bukan pengaturan sistem operasi.
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            // Warna merek TRUSTA. Dipakai di banyak view (bg-trusta-teal, text-navy-900, ...)
            // namun sebelumnya tidak pernah didefinisikan sehingga tombol tampak tanpa latar.
            colors: {
                trusta: {
                    teal: '#14B8A6',
                    'teal-light': '#2DD4BF',
                    gold: '#C9A84C',
                    navy: '#0F172A',
                    'navy-light': '#111827',
                    surface: '#1E293B',
                },
                navy: {
                    700: '#1E293B',
                    800: '#111827',
                    900: '#0F172A',
                    950: '#0A0F1E',
                },
            },
            fontFamily: {
                sans: ['Inter', 'Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
