# Panduan Sinkronisasi Git & GitHub (Project TRUSTA)

Dokumen ini berisi panduan praktis dan mudah dipahami mengenai cara menyinkronkan kode antara **Laptop (Lokal)** dan **GitHub (Cloud)** untuk project **TRUSTA**.

* **Repository GitHub:** [https://github.com/fathirzahran17-hue/trusta](https://github.com/fathirzahran17-hue/trusta)
* **Branch Utama:** `main`
* **Status Autentikasi:** Token GitHub sudah tersimpan otomatis di macOS Keychain, sehingga Anda tidak perlu mengetik password/token setiap kali sinkronisasi.

---

## 📌 Alur Kerja Git Secara Umum

```
  [Laptop Anda (Lokal)]                         [GitHub (Cloud)]
           │                                            │
           │  ─────── 1. git push (Kirim Kode) ───────> │
           │                                            │
           │  <────── 2. git pull (Tarik Kode) ──────── │
```

---

## 🚀 Skenario 1: Anda Mengubah Kode di Laptop ➡️ Kirim ke GitHub (Push)

Gunakan langkah ini ketika Anda selesai mengedit file, menambah halaman/fitur baru, atau memperbaiki bug di laptop Anda dan ingin menyimpannya ke GitHub.

### Langkah-langkah:

1. **Buka Terminal** dan arahkan ke folder project:
   ```bash
   cd "/Users/fathir/Desktop/Skripsi Fathir Teknik Informatika/trusta"
   ```

2. **Cek file yang Anda ubah** (opsional tapi disarankan):
   ```bash
   git status
   ```
   *File yang Anda edit akan berwarna merah.*

3. **Kumpulkan semua file yang berubah:**
   ```bash
   git add .
   ```

4. **Beri catatan (Commit) apa saja yang Anda ubah:**
   ```bash
   git commit -m "Deskripsi perubahan yang Anda lakukan"
   ```
   *Contoh:*
   * `git commit -m "Memperbaiki tampilan form akun"`
   * `git commit -m "Menambahkan fitur ekspor laporan excel"`

5. **Kirim perubahan ke GitHub:**
   ```bash
   git push
   ```

🎉 **Selesai!** Refresh halaman GitHub Anda di browser, kode terbaru sudah tersimpan rapi di sana.

---

## 🔄 Skenario 2: Anda Mengubah Kode di GitHub ➡️ Tarik ke Laptop (Pull)

Gunakan langkah ini ketika Anda mengedit file langsung lewat website GitHub (misal edit file `README.md` atau skrip di browser), atau jika Anda mengedit kode dari laptop lain dan ingin menyinkronkan laptop ini.

### Langkah-langkah:

1. **Buka Terminal** dan arahkan ke folder project:
   ```bash
   cd "/Users/fathir/Desktop/Skripsi Fathir Teknik Informatika/trusta"
   ```

2. **Tarik dan gabungkan pembaruan dari GitHub:**
   ```bash
   git pull
   ```

🎉 **Selesai!** Seluruh perubahan yang ada di GitHub akan langsung masuk dan memperbarui file di laptop Anda secara otomatis tanpa menghapus file lainnya.

---

## 🛡️ Aturan Emas & Tips Menghindari Konflik (Conflict)

1. **Biasakan `git pull` Sebelum Mulai Bekerja:**
   Jika Anda sering mengedit lewat web GitHub atau dari tempat lain, selalu jalankan `git pull` sebelum Anda mulai mengetik kode baru di laptop. Ini mencegah terjadinya bentrok kode (*merge conflict*).

2. **Bagaimana jika ada perubahan struktur database atau dependensi yang di-pull?**
   Jika Anda menarik perubahan dari GitHub yang berisi migrasi baru atau library baru:
   * Jalankan migrasi:
     ```bash
     php artisan migrate
     ```
   * Jika ada library PHP baru:
     ```bash
     composer install
     ```
   * Jika ada library frontend baru:
     ```bash
     npm install
     ```

3. **File Rahasia (.env):**
   File `.env` di laptop Anda sengaja tidak di-upload ke GitHub demi keamanan password dan database. Pengaturan lokal Anda akan tetap aman dan tidak akan tertimpa saat Anda melakukan `git push` atau `git pull`.

---

## ⚡ Cheatsheet (Rangkuman Perintah Cepat)

| Kebutuhan | Perintah Terminal |
| :--- | :--- |
| **Kirim semua perubahan lokal ke GitHub** | `git add . && git commit -m "Update kode" && git push` |
| **Ambil perubahan terbaru dari GitHub** | `git pull` |
| **Melihat status file yang diedit** | `git status` |
| **Melihat riwayat commit terakhir** | `git log -n 5 --oneline` |

> 💡 **Tips Praktis:** Di aplikasi ini, Anda juga bisa langsung meminta bantuan saya kapan saja melalui chat, misalnya: *"Tolong push semua perubahan ini ke GitHub"* atau *"Tolong pull dari GitHub"*, dan saya akan langsung mengeksekusinya untuk Anda!
