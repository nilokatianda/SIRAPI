# Instruksi Otomatis AI Assistant - Proyek SIRAPI

Dokumen ini merupakan aturan dasar (**Workspace Rules**) yang **wajib dipatuhi oleh AI Assistant (Antigravity / Gemini)** setiap kali berinteraksi dan mengerjakan permintaan/task dari pengguna pada repositori `SIRAPI`.

---

## ⚡ Protokol Eksekusi Wajib (Mandatory Workflow)

Setiap kali menerima prompt baru dari pengguna:

### 1. SEBELUM Memulai Task (Pre-Task Protocol)
1. **Wajib Membaca `PROGRESION.md`**:
   - Panggil tool `read_file` untuk membaca baris-baris terakhir dari `PROGRESION.md`.
   - Pahami histori pengerjaan sebelumnya, konteks kode, dan status perubahan terakhir agar tidak terjadi konflik atau regresi kode.
2. Analisis kebutuhan pengguna terhadap kode eksisting dan dokumentasi.

### 2. SELAMA Pengerjaan Task (In-Task Protocol)
1. Patuhi standar penulisan kode PHP/MySQLi yang aman (Prepared Statements, sanitasi input `htmlspecialchars`, penanganan exception relasi database).
2. Pertahankan komentar kode dan konsistensi desain antarmuka hijau modern khas SIRAPI.

### 3. SETELAH Menyelesaikan Task (Post-Task Protocol)
1. **Wajib Memperbarui `PROGRESION.md`**:
   - Catat riwayat pengerjaan dengan format:
     - Nomor urut & Tanggal serta Waktu/Jam (Zona waktu lokal / WIB).
     - Prompt / Permintaan pengguna yang asli.
     - Ringkasan **Before** (kondisi/masalah sebelum eksekusi).
     - Ringkasan **After** (perubahan, perbaikan, atau penambahan fitur yang diterapkan).
     - Daftar file yang dibuat atau dimodifikasi.
2. **Wajib Menilai dan Memperbarui `README.md`**:
   - Evaluasi apakah task yang baru saja selesai menghasilkan:
     - Fitur baru
     - Perubahan antarmuka / alur kerja operator
     - Perubahan skema database / query teknis
     - Perubahan konfigurasi / kebutuhan deployment
   - Jika **ADA perubahan/fitur baru**, lakukan update pada bagian terkait di `README.md` agar dokumentasi tetap relevan bagi Operator, Teknisi, maupun Engineer.
   - Jika tidak ada perubahan fitur baru (misalnya hanya pertanyaan atau refaktor minor), catat di riwayat bahwa README tidak memerlukan pembaruan.

