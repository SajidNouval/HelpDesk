# Panduan Penggunaan Sistem HelpDesk TA

## 📋 Ringkasan

Sistem HelpDesk TA adalah sistem helpdesk dan knowledge base yang memungkinkan:
- **Guest/Tamu**: Mencari artikel, membuat tiket livechat, dan membuat laporan
- **Staff**: Menangani tiket, menulis artikel, dan memantau dashboard
- **Admin**: Mengelola pengguna, kategori, dan konfigurasi sistem

---

## 🔐 Informasi Login

### Akun Demo

| Role | Email | Password |
|------|-------|----------|
| **Admin** | admin@gmail.com | password |
| **Staff** | staff@gmail.com | password |

> **Catatan**: Password di atas adalah akun demo untuk testing. Dalam produksi, gunakan password yang aman.

---

## 🌐 Akses Sistem

### URL Utama
- **Halaman Utama**: `/articles` (Guest dapat mencari artikel)
- **Login Admin/Staff**: `/login`
- **Dashboard Admin**: `/admin/dashboard`
- **Dashboard Staff**: `/staff/dashboard`

---

## 👤 Guest/Tamu

### 1. Mencari Artikel

**Cara Mengakses:**
1. Buka halaman utama: `http://helpdeskta.test/articles`
2. Gunakan kolom pencarian di bagian atas halaman
3. Ketik kata kunci yang ingin dicari
4. Tekan Enter atau klik tombol pencarian

**Fitur Pencarian:**
- Pencarian berbasis Typesense (pencarian teks cerdas)
- Filter berdasarkan kategori
- Menampilkan artikel yang approved dan published

**Hasil Pencarian:**
- Judul artikel
- Ringkasan (excerpt)
- Kategori
- Jumlah views

### 2. Membaca Artikel

**Cara Mengakses:**
1. Dari hasil pencarian, klik judul artikel yang diinginkan
2. Artikel akan ditampilkan lengkap dengan kontennya

**Fitur Artikel:**
- Konten lengkap artikel
- Informasi penulis (staff)
- Kategori artikel
- Tanggal publikasi
- Jumlah views
- Feedback (apakah artikel membantu atau tidak)

**Memberikan Feedback:**
1. Di bagian bawah artikel, klik tombol "Ya" atau "Tidak"
2. Feedback akan disimpan untuk meningkatkan kualitas artikel

### 3. Menggunakan Chatbot

**Cara Mengakses:**
1. Klik ikon chat di pojok kanan bawah halaman
2. Ketik pertanyaan Anda
3. Chatbot akan memberikan jawaban berdasarkan knowledge base

**Fitur Chatbot:**
- Pencarian artikel otomatis
- Jawaban berbasis keywords
- Escalation ke tiket jika jawaban tidak memuaskan
- History percakapan

### 4. Membuat Tiket Livechat

**Cara Mengakses:**
1. Akses button buat tiket
2. Pilih jenis tiket: **Livechat**
3. Isi formulir:
   - **Nama**: Nama lengkap Anda
   - **Email**: Alamat email aktif
   - **Subjek**: Judul masalah Anda
   - **Pesan**: Deskripsi detail masalah
   - **Kategori**: Pilih kategori yang sesuai
4. Klik tombol "Kirim"
5. Masukkan kode OTP yang dikirim ke email Anda
6. Tiket akan dibuat dan Anda dapat melacak statusnya

**Status Live Service:**
- Jika live service **aktif**: Anda dapat membuat tiket livechat
- Jika live service **offline**: Anda hanya dapat membuat laporan/report

### 5. Membuat Laporan/Report

**Cara Mengakses:**
1. Akses button buat tiket
2. Pilih jenis tiket: **Report**
3. Isi formulir (sama seperti livechat)
4. Klik tombol "Kirim"
5. Masukkan kode OTP yang dikirim ke email Anda
6. Laporan akan dibuat dan akan diproses oleh staff

**Perbedaan Report vs Livechat:**
- **Livechat**: Komunikasi real-time dengan staff
- **Report**: Laporan yang akan diproses tanpa komunikasi real-time

### 6. Melacak Tiket

**Cara Mengakses:**
1. Setelah tiket dibuat, Anda akan mendapatkan **tracking token**
2. Simpan tracking token ini untuk melacak status tiket
3. Gunakan tracking token untuk melihat status tiket di kemudian hari

**Status Tiket:**
- **Open**: Tiket baru dibuat
- **Assigned**: Tiket ditugaskan ke staff
- **Progress**: Tiket sedang diproses
- **Waiting**: Menunggu respons dari Anda
- **Closed**: Tiket selesai
- **Suspended**: Tiket ditangguhkan

---

## 👨‍💼 Staff

### 1. Login

**Cara Login:**
1. Buka halaman login: `http://helpdeskta.test/login`
2. Masukkan email: `staff@gmail.com`
3. Masukkan password: `password`
4. Klik tombol "Login"
5. Anda akan diarahkan ke dashboard staff

### 2. Dashboard Staff

**Fitur Dashboard:**
- **Tiket Hari Ini**: Daftar tiket yang masuk hari ini
- **Tiket Waiting**: Tiket yang belum ditugaskan
- **Jumlah Artikel**: Total artikel yang Anda tulis
- **Status Live Service**: Indikator apakah live service aktif

**Navigasi Dashboard:**
- **Dashboard**: Halaman utama
- **Tiket**: Kelola tiket yang masuk
- **Artikel**: Kelola artikel yang Anda tulis
- **Profile**: Edit profil Anda

### 3. Mengelola Tiket

**Melihat Daftar Tiket:**
1. Klik menu "Tiket" di sidebar
2. Anda akan melihat daftar tiket yang ditugaskan ke Anda
3. Filter berdasarkan status, kategori, atau prioritas

**Menangani Tiket:**
1. Klik tiket yang ingin ditangani
2. Baca pesan dari guest
3. Balas pesan dengan jawaban yang jelas
4. Ubah status tiket sesuai progres:
   - **Assigned** → **Progress**: Mulai memproses
   - **Progress** → **Waiting**: Menunggu respons guest
   - **Waiting** → **Progress**: Lanjutkan proses
   - **Progress** → **Closed**: Selesaikan tiket

**Menambah Log Aktivitas:**
1. Di halaman detail tiket, klik "Tambah Log"
2. Isi deskripsi aktivitas yang dilakukan
3. Log akan tercatat untuk audit trail

**Mengubah Prioritas:**
1. Di halaman detail tiket, ubah prioritas
2. Pilih: Low, Medium, atau High
3. Perubahan akan disimpan

### 4. Menulis Artikel

**Membuat Artikel Baru:**
1. Klik menu "Artikel" di sidebar
2. Klik tombol "Buat Artikel"
3. Isi formulir:
   - **Judul**: Judul artikel yang jelas dan deskriptif
   - **Slug**: URL-friendly (otomatis dari judul)
   - **Kategori**: Pilih kategori yang sesuai
   - **Konten**: Tulis konten artikel lengkap
   - **Ringkasan**: Ringkasan singkat artikel (opsional)
   - **Keywords**: Keywords untuk pencarian (dipisah koma)
4. Klik "Simpan"
5. Artikel akan disimpan dengan status "Pending Approval"

**Mengedit Artikel:**
1. Klik menu "Artikel" di sidebar
2. Klik artikel yang ingin diedit
3. Ubah konten yang diperlukan
4. Klik "Update"

**Menghapus Artikel:**
1. Klik menu "Artikel" di sidebar
2. Klik artikel yang ingin dihapus
3. Klik tombol "Hapus"
4. Konfirmasi penghapusan

**Status Artikel:**
- **Pending**: Menunggu approval admin
- **Approved**: Artikel disetujui dan ditampilkan
- **Rejected**: Artikel ditolak (dengan catatan penolakan)

**Melihat Feedback:**
1. Klik menu "Artikel" di sidebar
2. Klik artikel yang ingin dilihat feedbacknya
3. Scroll ke bagian bawah untuk melihat feedback
4. Klik "Reset Feedback" untuk menghapus semua feedback

### 5. Melihat Statistik

**Statistik di Dashboard:**
- Jumlah tiket hari ini
- Jumlah tiket waiting
- Jumlah artikel yang ditulis
- Status live service

### 6. Edit Profil

**Cara Edit Profil:**
1. Klik menu "Profile" di sidebar
2. Ubah nama dan email jika diperlukan
3. Ubah password jika ingin mengganti
4. Klik "Update"

---

## 👨‍💼 Admin

### 1. Login

**Cara Login:**
1. Buka halaman login: `http://helpdeskta.test/login`
2. Masukkan email: `admin@gmail.com`
3. Masukkan password: `password`
4. Klik tombol "Login"
5. Anda akan diarahkan ke dashboard admin

### 2. Dashboard Admin

**Fitur Dashboard:**
- **Statistik Tiket**: Total tiket, tiket waiting, tiket closed
- **Statistik Artikel**: Total artikel, artikel pending, artikel approved
- **Statistik Staff**: Jumlah staff aktif
- **Artikel Populer**: Artikel dengan views terbanyak
- **Feedback Terbaru**: Feedback terbaru dari pengguna
- **Status Live Service**: Toggle untuk mengaktifkan/menonaktifkan live service

**Navigasi Dashboard:**
- **Dashboard**: Halaman utama
- **Tiket**: Kelola semua tiket
- **Artikel**: Kelola semua artikel
- **Kategori**: Kelola kategori
- **Staff**: Kelola akun staff
- **Chatbot**: Kelola knowledge base chatbot
- **Settings**: Pengaturan sistem

### 3. Mengelola Tiket

**Melihat Semua Tiket:**
1. Klik menu "Tiket" di sidebar
2. Anda akan melihat semua tiket di sistem
3. Filter berdasarkan status, kategori, atau prioritas

**Menugaskan Tiket ke Staff:**
1. Klik tiket yang ingin ditugaskan
2. Pilih staff dari dropdown
3. Klik "Assign"
4. Tiket akan ditugaskan ke staff tersebut

**Melihat Detail Tiket:**
1. Klik tiket yang ingin dilihat
2. Lihat semua pesan dalam tiket
3. Lihat log aktivitas tiket
4. Lihat informasi pengirim

**Mengubah Status Tiket:**
1. Di halaman detail tiket, ubah status
2. Pilih status yang diinginkan
3. Perubahan akan disimpan

### 4. Mengelola Artikel

**Melihat Semua Artikel:**
1. Klik menu "Artikel" di sidebar
2. Anda akan melihat semua artikel di sistem
3. Filter berdasarkan status atau kategori

**Menyetujui Artikel:**
1. Klik artikel dengan status "Pending"
2. Review konten artikel
3. Klik "Approve"
4. Artikel akan ditampilkan di halaman publik

**Menolak Artikel:**
1. Klik artikel dengan status "Pending"
2. Review konten artikel
3. Klik "Reject"
4. Isi catatan penolakan
5. Klik "Reject"
6. Artikel akan ditolak dan staff akan diberitahu

**Mengedit/Hapus Artikel:**
- Admin dapat mengedit atau menghapus artikel apapun

### 5. Mengelola Kategori

**Membuat Kategori Baru:**
1. Klik menu "Kategori" di sidebar
2. Klik tombol "Buat Kategori"
3. Isi nama kategori
4. Isi deskripsi (opsional)
5. Klik "Simpan"

**Mengedit Kategori:**
1. Klik kategori yang ingin diedit
2. Ubah nama atau deskripsi
3. Klik "Update"

**Menghapus Kategori:**
1. Klik kategori yang ingin dihapus
2. Klik tombol "Hapus"
3. Konfirmasi penghapusan
4. **Peringatan**: Semua artikel dalam kategori akan dihapus (cascade delete)

### 6. Mengelola Staff

**Membuat Staff Baru:**
1. Klik menu "Staff" di sidebar
2. Klik tombol "Buat Staff"
3. Isi formulir:
   - **Nama**: Nama lengkap staff
   - **Email**: Email staff
   - **Password**: Password untuk staff
   - **Role**: Pilih "staff"
   - **Status**: Pilih "active" atau "inactive"
   - **Kategori**: Pilih kategori yang dapat ditangani staff
4. Klik "Simpan"

**Mengedit Staff:**
1. Klik staff yang ingin diedit
2. Ubah informasi yang diperlukan
3. Ubah kategori yang dapat ditangani
4. Klik "Update"

**Menghapus Staff:**
1. Klik staff yang ingin dihapus
2. Klik tombol "Hapus"
3. Konfirmasi penghapusan

**Mengaktifkan/Menonaktifkan Staff:**
1. Klik staff yang ingin diubah statusnya
2. Ubah status dari "active" ke "inactive" atau sebaliknya
3. Klik "Update"

### 8. Mengatur Live Service

**Mengaktifkan Live Service:**
1. Di dashboard admin, cari bagian "Live Service"
2. Klik toggle ke "On"
3. Guest dapat membuat tiket livechat

**Menonaktifkan Live Service:**
1. Di dashboard admin, cari bagian "Live Service"
2. Klik toggle ke "Off"
3. Guest hanya dapat membuat laporan/report

---

## 📊 Status Tiket

| Status | Deskripsi |
|--------|-----------|
| **Open** | Tiket baru dibuat, belum ditugaskan |
| **Assigned** | Tiket ditugaskan ke staff |
| **Progress** | Tiket sedang diproses oleh staff |
| **Waiting** | Menunggu respons dari guest |
| **Closed** | Tiket selesai |
| **Suspended** | Tiket ditangguhkan |

---

## 📝 Status Artikel

| Status | Deskripsi |
|--------|-----------|
| **Pending** | Menunggu approval admin |
| **Approved** | Disetujui dan ditampilkan di halaman publik |
| **Rejected** | Ditolak oleh admin |

---

## 🎯 Prioritas Tiket

| Prioritas | Deskripsi |
|-----------|-----------|
| **Low** | Masalah dengan urgensi rendah |
| **Medium** | Masalah dengan urgensi sedang (default) |
| **High** | Masalah dengan urgensi tinggi |

---

## 🔧 Troubleshooting

### Tidak dapat login
- Pastikan email dan password benar
- Pastikan akun dalam status "active"
- Hubungi admin jika lupa password

### Live service offline
- Hubungi admin untuk mengaktifkan live service
- Gunakan fitur report sebagai alternatif

### Artikel tidak muncul di pencarian
- Pastikan artikel sudah disetujui (approved)
- Pastikan artikel tidak di-hide (is_hidden = false)
- Pastikan artikel published (is_published = true)

### Tiket tidak mendapat respons
- Cek status tiket di dashboard
- Hubungi admin jika tiket terlalu lama tidak diproses

### Chatbot tidak memberikan jawaban
- Pastikan keywords yang relevan ada di knowledge base
- Coba gunakan kata kunci yang berbeda
- Buat tiket manual jika chatbot tidak membantu

---

## 📞 Bantuan Tambahan

Jika Anda mengalami masalah atau memiliki pertanyaan:
1. Cek dokumentasi ini terlebih dahulu
2. Buat tiket melalui halaman bantuan
3. Hubungi admin langsung jika masalah mendesak

---

## 📌 Catatan Penting

- **Password Demo**: Password di atas hanya untuk demo. Ganti password segera setelah login pertama.
- **Backup Data**: Admin sebaiknya melakukan backup database secara berkala.
- **Security**: Jangan berbagi akun login dengan orang yang tidak berwenang.
- **Performance**: Sistem menggunakan Typesense untuk pencarian yang cepat dan akurat.
- **Maintenance**: Admin dapat menonaktifkan live service untuk maintenance.

---

*Dokumentasi ini dibuat untuk membantu pengguna memahami dan menggunakan sistem HelpDesk TA dengan mudah.*
