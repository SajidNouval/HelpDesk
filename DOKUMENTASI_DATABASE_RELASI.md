# Dokumentasi Database Relasi HelpDesk TA

## Ringkasan

Dokumentasi ini menjelaskan struktur database lengkap sistem HelpDesk TA, termasuk semua tabel, atribut, tipe data, panjang, dan fungsi kegunaan masing-masing tabel.

---

## Daftar Tabel

1. **users** - Tabel pengguna sistem (admin/staff)
2. **categories** - Tabel kategori artikel
3. **articles** - Tabel artikel knowledge base
4. **article_feedback** - Tabel feedback artikel
5. **staff_profiles** - Tabel profil staff (relasi staff-kategori)
6. **tickets** - Tabel tiket helpdesk
7. **messages** - Tabel pesan dalam tiket
8. **ticket_logs** - Tabel log aktivitas tiket
9. **notifications** - Tabel notifikasi sistem
10. **chatbot** - Tabel knowledge base chatbot
11. **ticket_otps** - Tabel OTP verifikasi tiket
12. **settings** - Tabel pengaturan sistem

---

## 1. Tabel users

**Fungsi:** Menyimpan data pengguna sistem (admin dan staff) yang dapat login ke dashboard.

### Atribut

| Nama Kolom | Tipe Data | Panjang | Nullable | Default | Keterangan |
|------------|-----------|---------|----------|---------|------------|
| id | ULID | 26 | NO | - | Primary Key (Universally Unique Lexicographically Sortable Identifier) |
| name | VARCHAR | 50 | NO | - | Nama lengkap pengguna |
| email | VARCHAR | 50 | NO | - | Email pengguna (unique) |
| email_verified_at | TIMESTAMP | - | YES | NULL | Timestamp verifikasi email |
| password | VARCHAR | 255 | NO | - | Password yang di-hash (bcrypt) |
| role | ENUM | - | NO | 'staff' | Role pengguna: 'admin', 'staff' |
| status | ENUM | - | NO | 'active' | Status pengguna: 'active', 'inactive' |
| remember_token | VARCHAR | 100 | YES | NULL | Token untuk "remember me" |
| created_at | TIMESTAMP | - | YES | NULL | Timestamp pembuatan record |
| updated_at | TIMESTAMP | - | YES | NULL | Timestamp update terakhir |

### Index
- PRIMARY: id
- UNIQUE: email

---

## 2. Tabel categories

**Fungsi:** Menyimpan kategori artikel untuk mengelompokkan konten knowledge base.

### Atribut

| Nama Kolom | Tipe Data | Panjang | Nullable | Default | Keterangan |
|------------|-----------|---------|----------|---------|------------|
| id | ULID | 26 | NO | - | Primary Key |
| name | VARCHAR | 100 | NO | - | Nama kategori |
| description | TEXT | - | YES | NULL | Deskripsi kategori |
| created_at | TIMESTAMP | - | YES | NULL | Timestamp pembuatan record |
| updated_at | TIMESTAMP | - | YES | NULL | Timestamp update terakhir |

### Index
- PRIMARY: id

---

## 3. Tabel articles

**Fungsi:** Menyimpan artikel knowledge base yang dapat dicari oleh pengguna melalui chatbot atau pencarian manual.

### Atribut

| Nama Kolom | Tipe Data | Panjang | Nullable | Default | Keterangan |
|------------|-----------|---------|----------|---------|------------|
| id | ULID | 26 | NO | - | Primary Key |
| category_id | ULID | 26 | NO | - | Foreign Key ke categories (cascade delete) |
| staff_id | ULID | 26 | NO | - | Foreign Key ke users (penulis artikel, cascade delete) |
| title | VARCHAR | 200 | NO | - | Judul artikel |
| slug | VARCHAR | 255 | NO | - | URL-friendly slug (unique) |
| content | LONGTEXT | - | NO | - | Konten lengkap artikel |
| excerpt | TEXT | - | YES | NULL | Ringkasan/potongan artikel |
| keywords | VARCHAR | 500 | YES | NULL | Keywords untuk pencarian (dipisah koma) |
| views | INT | - | NO | 0 | Jumlah views artikel |
| is_published | BOOLEAN | - | NO | true | Status publikasi artikel |
| is_hidden | BOOLEAN | - | NO | false | Status visibilitas artikel |
| publish_status | ENUM | - | NO | 'pending' | Status approval: 'pending', 'approved', 'rejected' |
| rejection_note | TEXT | - | YES | NULL | Catatan penolakan artikel |
| created_at | TIMESTAMP | - | YES | NULL | Timestamp pembuatan record |
| updated_at | TIMESTAMP | - | YES | NULL | Timestamp update terakhir |

### Index
- PRIMARY: id
- UNIQUE: slug
- FOREIGN KEY: category_id → categories(id)
- FOREIGN KEY: staff_id → users(id)
- FULLTEXT: ft_title_content (title, content) - untuk pencarian teks

---

## 4. Tabel article_feedback

**Fungsi:** Menyimpan feedback pengguna terhadap artikel (apakah artikel membantu atau tidak).

### Atribut

| Nama Kolom | Tipe Data | Panjang | Nullable | Default | Keterangan |
|------------|-----------|---------|----------|---------|------------|
| id | ULID | 26 | NO | - | Primary Key |
| article_id | ULID | 26 | NO | - | Foreign Key ke articles (cascade delete) |
| ip_address | VARCHAR | 45 | NO | - | IP address pengirim feedback |
| is_helpful | BOOLEAN | - | NO | - | Feedback: true (membantu), false (tidak membantu) |
| created_at | TIMESTAMP | - | YES | NULL | Timestamp pembuatan record |
| updated_at | TIMESTAMP | - | YES | NULL | Timestamp update terakhir |

### Index
- PRIMARY: id
- UNIQUE: (article_id, ip_address) - satu IP hanya bisa feedback sekali per artikel
- FOREIGN KEY: article_id → articles(id)

---

## 5. Tabel staff_profiles

**Fungsi:** Menyimpan relasi many-to-many antara staff dan kategori, menentukan kategori mana yang dapat ditangani oleh staff tertentu.

### Atribut

| Nama Kolom | Tipe Data | Panjang | Nullable | Default | Keterangan |
|------------|-----------|---------|----------|---------|------------|
| id | ULID | 26 | NO | - | Primary Key |
| user_id | ULID | 26 | NO | - | Foreign Key ke users (staff, cascade delete) |
| category_id | ULID | 26 | NO | - | Foreign Key ke categories (cascade delete) |
| is_busy | BOOLEAN | - | NO | false | Status sibuk staff |
| created_at | TIMESTAMP | - | YES | NULL | Timestamp pembuatan record |
| updated_at | TIMESTAMP | - | YES | NULL | Timestamp update terakhir |

### Index
- PRIMARY: id
- FOREIGN KEY: user_id → users(id)
- FOREIGN KEY: category_id → categories(id)

---

## 6. Tabel tickets

**Fungsi:** Menyimpan tiket helpdesk yang dibuat oleh guest atau user untuk melaporkan masalah atau meminta bantuan.

### Atribut

| Nama Kolom | Tipe Data | Panjang | Nullable | Default | Keterangan |
|------------|-----------|---------|----------|---------|------------|
| id | ULID | 26 | NO | - | Primary Key |
| name | VARCHAR | 50 | NO | - | Nama pengirim (guest) |
| email | VARCHAR | 50 | NO | - | Email pengirim |
| subject | VARCHAR | 200 | NO | - | Subjek tiket |
| message | TEXT | - | NO | - | Pesan detail tiket |
| category_id | ULID | 26 | NO | - | Foreign Key ke categories (cascade delete) |
| user_id | ULID | 26 | YES | NULL | Foreign Key ke users (jika login, null on delete) |
| staff_id | ULID | 26 | YES | NULL | Foreign Key ke users (staff yang menangani, null on delete) |
| status | ENUM | - | NO | 'open' | Status tiket: 'open', 'assigned', 'progress', 'waiting', 'closed', 'suspended' |
| priority | ENUM | - | NO | 'medium' | Priority tiket: 'low', 'medium', 'high' |
| assigned_at | TIMESTAMP | - | YES | NULL | Tiket ditugaskan ke staff |
| closed_at | TIMESTAMP | - | YES | NULL | Tiket ditutup |
| email_verified_at | TIMESTAMP | - | YES | NULL | Email pengirim diverifikasi |
| tracking_token | VARCHAR | 80 | YES | NULL | Token unik untuk tracking tiket (unique) |
| created_at | TIMESTAMP | - | YES | NULL | Timestamp pembuatan record |
| updated_at | TIMESTAMP | - | YES | NULL | Timestamp update terakhir |

### Index
- PRIMARY: id
- UNIQUE: tracking_token
- FOREIGN KEY: category_id → categories(id)
- FOREIGN KEY: user_id → users(id)
- FOREIGN KEY: staff_id → users(id)

---

## 7. Tabel messages

**Fungsi:** Menyimpan pesan dalam tiket untuk komunikasi antara guest/customer dan staff.

### Atribut

| Nama Kolom | Tipe Data | Panjang | Nullable | Default | Keterangan |
|------------|-----------|---------|----------|---------|------------|
| id | ULID | 26 | NO | - | Primary Key |
| ticket_id | ULID | 26 | NO | - | Foreign Key ke tickets (cascade delete) |
| sender_type | ENUM | - | NO | - | Tipe pengirim: 'guest', 'staff' |
| sender_id | ULID | 26 | YES | NULL | Foreign Key ke users (jika staff, null on delete) |
| message | TEXT | - | NO | - | Isi pesan |
| is_read | BOOLEAN | - | NO | false | Status pesan sudah dibaca |
| created_at | TIMESTAMP | - | YES | NULL | Timestamp pembuatan record |
| updated_at | TIMESTAMP | - | YES | NULL | Timestamp update terakhir |

### Index
- PRIMARY: id
- FOREIGN KEY: ticket_id → tickets(id)
- FOREIGN KEY: sender_id → users(id)

---

## 8. Tabel ticket_logs

**Fungsi:** Menyimpan log aktivitas tiket untuk audit trail dan tracking perubahan status tiket.

### Atribut

| Nama Kolom | Tipe Data | Panjang | Nullable | Default | Keterangan |
|------------|-----------|---------|----------|---------|------------|
| id | ULID | 26 | NO | - | Primary Key |
| ticket_id | ULID | 26 | NO | - | Foreign Key ke tickets (cascade delete) |
| action | VARCHAR | 50 | NO | - | Aksi yang dilakukan: 'created', 'assigned', 'progress', 'closed', dll |
| description | TEXT | - | YES | NULL | Deskripsi detail aksi |
| created_at | TIMESTAMP | - | YES | NULL | Timestamp pembuatan record |
| updated_at | TIMESTAMP | - | YES | NULL | Timestamp update terakhir |

### Index
- PRIMARY: id
- FOREIGN KEY: ticket_id → tickets(id)

---

## 9. Tabel notifications

**Fungsi:** Menyimpan notifikasi untuk pengguna (staff/admin) tentang aktivitas sistem seperti tiket baru, pesan baru, dll.

### Atribut

| Nama Kolom | Tipe Data | Panjang | Nullable | Default | Keterangan |
|------------|-----------|---------|----------|---------|------------|
| id | ULID | 26 | NO | - | Primary Key |
| user_id | ULID | 26 | NO | - | Foreign Key ke users (cascade delete) |
| title | VARCHAR | 200 | NO | - | Judul notifikasi |
| message | TEXT | - | NO | - | Isi pesan notifikasi |
| is_read | BOOLEAN | - | NO | false | Status notifikasi sudah dibaca |
| created_at | TIMESTAMP | - | YES | NULL | Timestamp pembuatan record |
| updated_at | TIMESTAMP | - | YES | NULL | Timestamp update terakhir |

### Index
- PRIMARY: id
- FOREIGN KEY: user_id → users(id)

---

## 10. Tabel chatbot

**Fungsi:** Menyimpan knowledge base chatbot berupa keywords dan responses untuk menjawab pertanyaan pengguna secara otomatis.

### Atribut

| Nama Kolom | Tipe Data | Panjang | Nullable | Default | Keterangan |
|------------|-----------|---------|----------|---------|------------|
| id | ULID | 26 | NO | - | Primary Key |
| keywords | VARCHAR | 500 | NO | - | Keywords (dipisah koma), contoh: "wifi,internet,lemot" |
| response | LONGTEXT | - | NO | - | Jawaban chatbot |
| category_id | ULID | 26 | YES | NULL | Foreign Key ke categories (opsional, null on delete) |
| is_active | BOOLEAN | - | NO | true | Status aktif/nonaktif |
| priority | INT | - | NO | 0 | Prioritas jika keyword mirip (lebih tinggi = lebih prioritas) |
| created_at | TIMESTAMP | - | YES | NULL | Timestamp pembuatan record |
| updated_at | TIMESTAMP | - | YES | NULL | Timestamp update terakhir |

### Index
- PRIMARY: id
- FOREIGN KEY: category_id → categories(id)

---

## 11. Tabel ticket_otps

**Fungsi:** Menyimpan data OTP (One-Time Password) untuk verifikasi email sebelum membuat tiket, mencegah spam.

### Atribut

| Nama Kolom | Tipe Data | Panjang | Nullable | Default | Keterangan |
|------------|-----------|---------|----------|---------|------------|
| id | ULID | 26 | NO | - | Primary Key |
| name | VARCHAR | 50 | NO | - | Nama pengirim |
| email | VARCHAR | 50 | NO | - | Email pengirim |
| subject | VARCHAR | 200 | NO | - | Subjek tiket |
| message | TEXT | - | NO | - | Pesan tiket |
| category_id | ULID | 26 | NO | - | Foreign Key ke categories (cascade delete) |
| type | ENUM | - | NO | 'livechat' | Tipe tiket: 'livechat', 'report' |
| otp_code | VARCHAR | 6 | NO | - | Kode OTP 6 digit |
| attempts | TINYINT | - | NO | 0 | Jumlah percobaan OTP |
| expires_at | TIMESTAMP | - | NO | - | Timestamp kedaluwarsa OTP |
| token | VARCHAR | 80 | NO | - | Token unik untuk tracking (unique) |
| created_at | TIMESTAMP | - | YES | NULL | Timestamp pembuatan record |
| updated_at | TIMESTAMP | - | YES | NULL | Timestamp update terakhir |

### Index
- PRIMARY: id
- UNIQUE: token
- FOREIGN KEY: category_id → categories(id)

---

## 12. Tabel settings

**Fungsi:** Menyimpan pengaturan sistem secara dinamis (key-value pairs) untuk konfigurasi yang dapat diubah tanpa mengubah kode.

### Atribut

| Nama Kolom | Tipe Data | Panjang | Nullable | Default | Keterangan |
|------------|-----------|---------|----------|---------|------------|
| id | ULID | 26 | NO | - | Primary Key |
| key | VARCHAR | 100 | NO | - | Kunci pengaturan (unique) |
| value | LONGTEXT | - | YES | NULL | Nilai pengaturan (bisa JSON, string, dll) |
| created_at | TIMESTAMP | - | YES | NULL | Timestamp pembuatan record |
| updated_at | TIMESTAMP | - | YES | NULL | Timestamp update terakhir |

### Index
- PRIMARY: id
- UNIQUE: key

---

## Diagram Relasi Database

```
┌─────────────────┐
│     users       │
├─────────────────┤
│ id (PK)         │◄──────────────┐
│ name            │               │
│ email           │               │
│ password        │               │
│ role            │               │
│ status          │               │
└─────────────────┘               │
        │                         │
        │                         │
        │                         │
        │                         │
        │                         │
        ▼                         │
┌─────────────────┐               │
│ staff_profiles  │               │
├─────────────────┤               │
│ id (PK)         │               │
│ user_id (FK)    │───────────────┘
│ category_id (FK)│──────────────┐
│ is_busy         │               │
└─────────────────┘               │
                                   │
                                   │
        ┌──────────────────────────┘
        │
        │
        ▼
┌─────────────────┐
│   categories   │
├─────────────────┤
│ id (PK)         │◄──────────────┐
│ name            │               │
│ description     │               │
└─────────────────┘               │
        │                         │
        │                         │
        │                         │
        │                         │
        │                         │
        ▼                         │
┌─────────────────┐               │
│    articles     │               │
├─────────────────┤               │
│ id (PK)         │◄──────────────┤
│ category_id (FK)│───────────────┘
│ staff_id (FK)   │──────────────┐
│ title           │               │
│ slug            │               │
│ content         │               │
│ keywords        │               │
│ views           │               │
│ is_published    │               │
│ publish_status  │               │
└─────────────────┘               │
        │                         │
        │                         │
        │                         │
        ▼                         │
┌─────────────────┐               │
│article_feedback │               │
├─────────────────┤               │
│ id (PK)         │               │
│ article_id (FK) │───────────────┘
│ ip_address      │
│ is_helpful      │
└─────────────────┘

┌─────────────────┐
│    tickets      │
├─────────────────┤
│ id (PK)         │◄────────┐
│ name            │         │
│ email           │         │
│ subject         │         │
│ message         │         │
│ category_id (FK)│─────────┘
│ user_id (FK)    │──────────┐
│ staff_id (FK)   │──────────┤
│ status          │          │
│ priority        │          │
└─────────────────┘          │
        │                     │
        │                     │
        ▼                     │
┌─────────────────┐          │
│    messages     │          │
├─────────────────┤          │
│ id (PK)         │          │
│ ticket_id (FK)  │──────────┘
│ sender_type     │
│ sender_id (FK)  │
│ message         │
│ is_read         │
└─────────────────┘

┌─────────────────┐
│  ticket_logs    │
├─────────────────┤
│ id (PK)         │
│ ticket_id (FK)  │
│ action          │
│ description     │
└─────────────────┘

┌─────────────────┐
│ notifications   │
├─────────────────┤
│ id (PK)         │
│ user_id (FK)    │
│ title           │
│ message         │
│ is_read         │
└─────────────────┘

┌─────────────────┐
│    chatbot      │
├─────────────────┤
│ id (PK)         │
│ keywords        │
│ response        │
│ category_id (FK)│
│ is_active       │
│ priority        │
└─────────────────┘

┌─────────────────┐
│  ticket_otps    │
├─────────────────┤
│ id (PK)         │
│ name            │
│ email           │
│ subject         │
│ message         │
│ category_id (FK)│
│ type            │
│ otp_code        │
│ attempts        │
│ expires_at      │
│ token           │
└─────────────────┘

┌─────────────────┐
│    settings     │
├─────────────────┤
│ id (PK)         │
│ key             │
│ value           │
└─────────────────┘
```

---

## Ringkasan Relasi Utama

### users
- **One-to-Many** → articles (sebagai staff_id)
- **One-to-Many** → tickets (sebagai user_id)
- **One-to-Many** → tickets (sebagai staff_id)
- **One-to-Many** → messages (sebagai sender_id)
- **One-to-Many** → notifications
- **One-to-Many** → staff_profiles

### categories
- **One-to-Many** → articles
- **One-to-Many** → tickets
- **One-to-Many** → staff_profiles
- **One-to-Many** → chatbot
- **One-to-Many** → ticket_otps

### articles
- **Many-to-One** → categories
- **Many-to-One** → users (staff)
- **One-to-Many** → article_feedback

### tickets
- **Many-to-One** → categories
- **Many-to-One** → users (user_id)
- **Many-to-One** → users (staff_id)
- **One-to-Many** → messages
- **One-to-Many** → ticket_logs

### messages
- **Many-to-One** → tickets
- **Many-to-One** → users (sender_id)

---

## Catatan Penting

1. **ULID**: Semua primary key menggunakan ULID (Universally Unique Lexicographically Sortable Identifier) dengan panjang 26 karakter, yang lebih baik untuk distributed systems dan dapat diurutkan secara leksikografis.

2. **Cascade Delete**: Beberapa foreign key menggunakan cascade delete, artinya jika parent record dihapus, child records akan otomatis dihapus. Ini perlu diperhatikan untuk data integrity.

3. **Nullable Foreign Keys**: Beberapa foreign key bersifat nullable (user_id di tickets, sender_id di messages) untuk mendukung guest users yang tidak login.

4. **Timestamps**: Semua tabel memiliki created_at dan updated_at untuk tracking waktu pembuatan dan update.

5. **Optimasi Panjang Kolom**: Berdasarkan migration terbaru, panjang kolom telah dioptimalkan:
   - name/email: 50 karakter
   - password: 255 karakter
   - subject/title: 200 karakter
   - keywords: 500 karakter
   - slug: 255 karakter
   - content/response: LONGTEXT
   - message/description: TEXT
   - action/key: 50-100 karakter

---

## Tabel Tambahan Laravel (Standard)

Tabel berikut adalah tabel standar Laravel yang tidak dijelaskan detail di atas:

- **password_reset_tokens** - Token reset password
- **failed_jobs** - Job yang gagal (queue system)
- **personal_access_tokens** - Token API authentication (Sanctum)

---

*Dokumentasi ini dibuat pada tanggal 9 Juni 2026 berdasarkan struktur database terbaru.*
