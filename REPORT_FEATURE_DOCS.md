# Fitur "Buat Laporan" di Halaman Artikel

## Ringkasan
Telah ditambahkan fitur "Buat Laporan" di halaman artikel yang memungkinkan pengunjung untuk membuat laporan/tiket langsung dari halaman artikel tanpa perlu live chat.

## Perubahan yang Dilakukan

### 1. **Database Migration**
- File: `database/migrations/2026_04_29_add_suspended_status_to_tickets.php`
- Menambah status enum baru: `'suspended'`
- Struktur enum status sekarang: `'open'`, `'assigned'`, `'progress'`, `'waiting'`, `'suspended'`, `'closed'`

### 2. **Controller: TicketController**
- **Method baru: `storeReport()`** - Membuat tiket/laporan dengan status 'suspended'
- **Method baru: `assignReportToStaff()`** - Logika auto-assign yang smart:
  - Prioritaskan staff dengan suspended tickets paling sedikit di kategori yang sama
  - Jika jumlah suspended tickets sama, prioritaskan staff yang TIDAK sedang live chat (is_busy=false)
  - Tidak ada batasan is_busy untuk pemberian laporan (bisa masuk ke staff yang sedang live chat)

### 3. **Routes**
- Tambah route baru: `POST /reports` → `TicketController@storeReport`
- Route name: `reports.store`

### 4. **Views**

#### articles/index.blade.php
- Tambah button "Buat Laporan" di navbar (warna merah)
- Include modal form report

#### articles/show.blade.php
- Tambah button "Buat Laporan" di navbar (warna merah)
- Include modal form report

#### reports/create.blade.php (file baru)
- Modal form untuk membuat laporan
- Input fields: Category, Name, Email, Subject, Message
- Form submit ke route `reports.store`

## Fitur Utama

### Auto-Assignment Logic untuk Laporan
```
1. Cari semua staff dengan kategori yang sesuai
2. Hitung jumlah suspended tickets untuk setiap staff
3. Sort berdasarkan:
   - Primary: Jumlah suspended tickets (ascending)
   - Secondary: Status is_busy (prioritaskan is_busy=false)
4. Assign ke staff dengan suspended tickets paling sedikit
5. Jika tidak ada staff untuk kategori, tiket tetap status 'suspended' dengan waiting note
```

### Flow Pembuatan Laporan
1. Pengunjung klik button "Buat Laporan" di halaman artikel
2. Modal form terbuka
3. Isi form (kategori, nama, email, subjek, pesan)
4. Submit form
5. Tiket dibuat dengan status `'suspended'`
6. System otomatis assign ke staff dengan tangguhan paling sedikit
7. TicketLog dicatat untuk audit trail

## Testing

Untuk memverifikasi fitur:

1. **Buka halaman artikel**: `http://yoursite.com/articles`
2. **Klik button "Buat Laporan"** (red button di navbar)
3. **Isi form** dengan kategori yang ada
4. **Submit form**
5. **Verifikasi di database**:
   ```sql
   SELECT * FROM tickets WHERE status = 'suspended' ORDER BY created_at DESC LIMIT 1;
   SELECT * FROM ticket_logs WHERE ticket_id = <ticket_id> ORDER BY id ASC;
   ```

## Notes
- Laporan tidak memerlukan captcha (berbeda dengan tiket biasa)
- Rate limiting tetap berlaku: 1 laporan per IP per menit, 1 laporan per email per menit
- Status tiket dari laporan adalah `'suspended'` sampai staff mulai mengerjakannya
- Staff dapat masuk ke laporan meskipun sedang live chat dengan customer lain
