# Panduan Menjalankan Web (Project Trusta)

Dokumen ini berisi panduan lengkap tentang hal-hal yang harus Anda lakukan untuk menyiapkan dan menjalankan aplikasi web **TRUSTA** (Aplikasi Akuntansi berbasis Laravel 12).

---

## Prasyarat (Software yang Harus Sudah Terinstall)

Pastikan software berikut sudah terinstall di komputer Anda:

| Software         | Versi Minimum | Cara Cek              |
| ---------------- | ------------- | --------------------- |
| **PHP**          | 8.2+          | `php -v`              |
| **MySQL/MariaDB**| 10.4+ / 8.0+  | `mysql --version`     |
| **Node.js**      | 18+           | `node -v`             |
| **NPM**          | 9+            | `npm -v`              |
| **Composer**     | 2.x           | `composer --version`  |

> **Catatan:** Project ini menyertakan `composer.phar` di dalam folder project. Jika `composer` tidak terinstall secara global, Anda bisa menggantinya dengan `php composer.phar` pada setiap perintah yang menggunakan `composer`.

---

## Setup Pertama Kali (Hanya Dilakukan Sekali)

Jika ini pertama kalinya Anda menyiapkan project ini, ikuti langkah-langkah berikut:

### 1. Buka Terminal dan Arahkan ke Folder Project
```bash
cd "/Users/fathir/Desktop/Skripsi Fathir Teknik Informatika/trusta"
```

### 2. Install Dependensi PHP (Composer)
```bash
composer install
```
Atau jika `composer` tidak terinstall global:
```bash
php composer.phar install
```

### 3. Install Dependensi JavaScript (NPM)
```bash
npm install
```

### 4. Salin File Environment
```bash
cp .env.example .env
```
> **Penting:** Jika file `.env` sudah ada, lewati langkah ini agar konfigurasi Anda tidak tertimpa.

### 5. Generate Application Key
```bash
php artisan key:generate
```

### 6. Buat Database MySQL
Buka terminal baru atau gunakan phpMyAdmin / HeidiSQL, lalu buat database:
```sql
CREATE DATABASE trusta;
```
Pastikan konfigurasi database di file `.env` sudah sesuai:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=trusta
DB_USERNAME=root
DB_PASSWORD=
```

### 7. Jalankan Migrasi Database
```bash
php artisan migrate
```

### 8. Isi Data Awal (Seeder)
```bash
php artisan db:seed
```
Seeder akan membuat:
- **Super Admin:** `superadmin@trusta.com` / password: `password`
- **Client Admin:** `client@makmur.com` / password: `password`
- **Perusahaan:** PT Makmur Sejahtera
- **Chart of Accounts (CoA):** 26 akun standar

---

## Menjalankan Aplikasi (Setiap Kali Ingin Membuka Web)

### Cara Cepat (1 Terminal, Recommended)
Gunakan perintah `composer dev` yang sudah dikonfigurasi untuk menjalankan semua server sekaligus:
```bash
cd "/Users/fathir/Desktop/Skripsi Fathir Teknik Informatika/trusta"
composer dev
```
Perintah ini akan menjalankan 4 service sekaligus:
- 🟦 **Server Laravel** (`php artisan serve`) → http://localhost:8000
- 🟪 **Queue Worker** (`php artisan queue:listen`)
- 🩷 **Log Viewer** (`php artisan pail`)
- 🟧 **Vite Dev Server** (`npm run dev`)

### Cara Manual (2 Terminal Terpisah)

#### Terminal 1 — Server Backend (Laravel)
```bash
cd "/Users/fathir/Desktop/Skripsi Fathir Teknik Informatika/trusta"
php artisan serve
```

#### Terminal 2 — Server Frontend (Vite)
Buka **Tab Terminal Baru** (tekan `Cmd + T`), lalu jalankan:
```bash
cd "/Users/fathir/Desktop/Skripsi Fathir Teknik Informatika/trusta"
npm run dev
```

---

## Buka Web di Browser

Setelah server berjalan, buka web browser (Google Chrome / Safari) dan kunjungi:

👉 **http://127.0.0.1:8000** atau **http://localhost:8000**

---

## Akun Login

| Role           | Email                     | Password   |
| -------------- | ------------------------- | ---------- |
| Super Admin    | `superadmin@trusta.com`   | `password` |
| Client Admin   | `client@makmur.com`       | `password` |

> Anda juga bisa login menggunakan **Google Sign-In** jika sudah dikonfigurasi.

---

## Troubleshooting (Masalah Umum)

### ❌ Error "SQLSTATE[HY000] [2002] Connection refused"
- Pastikan MySQL/MariaDB sudah **berjalan** (cek XAMPP / MAMP / `brew services start mysql`).
- Pastikan konfigurasi `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` di `.env` sudah benar.

### ❌ Error "Vite manifest not found"
- Pastikan `npm run dev` sudah berjalan di terminal terpisah.
- Atau jalankan `npm run build` untuk membuat file aset statis.

### ❌ Halaman Kosong / Error 500
- Jalankan `php artisan config:clear && php artisan cache:clear && php artisan view:clear`.
- Pastikan file `.env` ada dan `APP_KEY` sudah di-generate.

### ❌ Error "Class not found"
- Jalankan `composer dump-autoload`.

### ❌ Error saat migrasi tabel sudah ada
- Jika database sudah ada isinya, gunakan `php artisan migrate:fresh --seed` (**HATI-HATI: ini menghapus semua data**).

---

## Catatan Penting

- **Jangan tutup terminal:** Biarkan terminal tetap terbuka selama Anda sedang mengakses project ini.
- **Mematikan Server:** Jika sudah selesai, tekan `Ctrl + C` di masing-masing terminal yang sedang berjalan.
- **Update Database:** Jika ada perubahan struktur database baru, jalankan `php artisan migrate`.
- **Reset Data:** Untuk mereset semua data ke awal, jalankan `php artisan migrate:fresh --seed`.
