# 🎫 Sistem Ticket Support Helpdesk - Dokumentasi Implementasi

## 📋 Ringkasan

Sistem ticket support lengkap untuk helpdesk dengan dua interface utama:
- **Guest Interface**: Halaman publik untuk membuat tiket
- **Staff Interface**: Dashboard untuk staff mengerjakan tiket

---

## 🏗️ Arsitektur Sistem

### Database Models
- **Ticket**: Model utama dengan status (open, waiting, assigned, progress, closed)
- **StaffProfile**: Profil staff dengan category dan status is_busy
- **Category**: Kategori untuk mengorganisir tiket
- **TicketLog**: Log audit untuk setiap perubahan tiket
- **Message**: Komunikasi antara guest dan staff

### Status Ticket
```
open → waiting (jika tidak ada staff tersedia)
     → assigned (jika ada staff tersedia)
assigned → progress (staff mulai mengerjakan)
progress → closed (selesai)
```

---

## 👥 Guest Interface

### URL: `/help`

#### Fitur
1. **Help Page** - Halaman informasi dengan:
   - Penjelasan singkat layanan
   - Card info (Respon Cepat, Solusi Terpercaya, 24/7)
   - FAQ section
   - Button "Buat Tiket Baru"

2. **Form Modal**
   - **Category**: Dropdown pilihan kategori (required)
   - **Nama**: Nama guest (required)
   - **Email**: Email guest (required)
   - **Subject**: Judul tiket (required)
   - **Message**: Deskripsi masalah (required)

#### Auto-Assignment Logic
```php
POST /tickets → TicketController@store
1. Validasi input
2. Buat ticket dengan status='open'
3. Cari available staff dengan:
   - category_id sesuai
   - is_busy = false
4. Jika ada:
   - Assign ke staff
   - Set status='assigned'
   - Set assigned_at=now()
   - Update staff is_busy=true
   - Log: 'assigned'
5. Jika tidak:
   - Set status='waiting'
   - Log: 'waiting'
```

---

## 👨‍💼 Staff Interface

### Dashboard: `/staff/dashboard`
- Grid card menampilkan jumlah tiket dan artikel
- Link ke halaman tiket staff

### Tiket Staff: `/staff/tickets`

#### Tampilan
1. **Active Ticket Section**
   - Menampilkan 1 tiket yang sedang dikerjakan (status: assigned/progress)
   - Informasi lengkap: category, dari, pesan
   - Priority selector (low/medium/high)
   - Action buttons

2. **Tab Navigation**
   - **Semua Tiket**: Semua tiket yang ditugaskan
   - **Selesai**: Tiket dengan status closed
   - **Menunggu**: Tiket menunggu assign setelah staff selesai

#### Fitur per Ticket

**1. Set Priority**
```
PATCH /staff/tickets/{ticket}/priority
- Input: priority (low|medium|high)
- Update ticket.priority
- Log perubahan
```

**2. Mulai Mengerjakan**
```
PATCH /staff/tickets/{ticket}/start-progress
- Change status: assigned → progress
- Log: 'progress_started'
```

**3. Tandai Selesai**
```
PATCH /staff/tickets/{ticket}/complete
- Change status: progress → closed
- Set closed_at=now()
- Update staff is_busy=false
- Log: 'closed'
- AUTO-ASSIGN NEXT TICKET:
  - Cari ticket waiting dengan category sama
  - Assign ke staff yang baru selesai
  - Set status='assigned'
  - Update is_busy=true
```

### Detail Ticket: `/staff/tickets/{ticket}`
- Informasi lengkap customer dan tiket
- Priority selector dengan update button
- Detail grid (kategori, dibuat, diassign, ditutup)
- Pesan terformatkan
- Riwayat komunikasi (messages timeline)
- Action buttons

---

## 📁 File Structure

### Controllers
```
app/Http/Controllers/
├── TicketController.php              ← Guest ticket creation
└── Staff/TicketController.php        ← Staff ticket management
```

### Views
```
resources/views/
├── guest/
│   └── help.blade.php               ← Help page dengan list FAQ
├── staff/
│   ├── dashboard.blade.php          ← Staff dashboard overview
│   └── tickets/
│       ├── index.blade.php          ← Ticket list dengan tabs
│       └── show.blade.php           ← Detail ticket view
└── tickets/
    └── create.blade.php             ← Modal form untuk ticket
```

### Routes
```
Public Routes:
GET  /help                           → TicketController@create
POST /tickets                        → TicketController@store

Staff Routes:
GET    /staff/tickets                → StaffTicketController@index
GET    /staff/tickets/{ticket}       → StaffTicketController@show
PATCH  /staff/tickets/{ticket}/priority      → updatePriority
PATCH  /staff/tickets/{ticket}/start-progress → startProgress
PATCH  /staff/tickets/{ticket}/complete      → complete
```

---

## 🔄 Alur Sistem

### 1. Guest Membuat Ticket
```
User membuka /help
    ↓
Klik "Buat Tiket Baru"
    ↓
Modal form tampil
    ↓
Isi form (category, name, email, subject, message)
    ↓
Submit form
    ↓
POST /tickets
    ↓
TicketController@store:
  - Create ticket (status=open)
  - Check available staff:
    - IF staff available → assign (status=assigned, is_busy=true)
    - IF no staff → pending (status=waiting)
  - Redirect back dengan success message
```

### 2. Staff Menerima & Mengerjakan Ticket
```
Ticket auto-assigned ke staff yang available
    ↓
Staff login ke /staff/dashboard
    ↓
Klik "Lihat Tiket"
    ↓
Lihat tiket di Active Ticket section
    ↓
Klik "Mulai Mengerjakan" (optional)
    ↓
Set priority jika diperlukan
    ↓
Klik "Tandai Selesai"
    ↓
PATCH /staff/tickets/{ticket}/complete
    ↓
StaffTicketController@complete:
  - Close ticket (status=closed)
  - Set closed_at
  - Update staff is_busy=false
  - AUTO: Check waiting tickets
  - AUTO: Assign next ticket (jika ada)
  - Redirect dengan success
```

---

## 🌟 Fitur Khusus

### Auto-Assignment System
- Ticket otomatis masuk ke staff yang available dengan category sama
- Jika tidak ada staff, ticket pending sampai ada staff yang selesai
- Setelah staff selesai 1 ticket, ticket berikutnya langsung di-assign
- Mencegah staff handle lebih dari 1 ticket (is_busy flag)

### Priority Management
- Low (🟢): Tidak mendesak
- Medium (🟡): Standar (default)
- High (🔴): Mendesak

### Timestamp Tracking
- `assigned_at`: Kapan ticket di-assign ke staff
- `closed_at`: Kapan ticket ditutup/selesai

### Logging System
- Setiap action di-log di ticket_logs table
- Action: created, assigned, progress_started, priority_updated, closed

---

## ✅ Testing Checklist

### Guest Side
- [ ] Visit `/help` → halaman kebuka dengan form button
- [ ] Click "Buat Tiket Baru" → modal muncul
- [ ] Submit form → ticket created, redirect success
- [ ] Check email verification (jika diperlukan)

### Staff Side
- [ ] Login as staff → redirect ke staff.dashboard
- [ ] Click "Lihat Tiket" → lihat assigned tickets
- [ ] View ticket details → semua info tampil lengkap
- [ ] Set priority → priority tersimpan
- [ ] Click "Mulai Mengerjakan" → status berubah
- [ ] Click "Tandai Selesai" → ticket closed
- [ ] Next waiting ticket auto-assigned (jika ada)

### System Side
- [ ] Check ticket_logs untuk audit trail
- [ ] Verify staff profile is_busy status berubah
- [ ] Test dengan multiple staff (kategori sama)
- [ ] Test dengan no available staff (status pending)
- [ ] Verify email notifications (optional)

---

## 🎯 Poin Penting Implementasi

1. **Category-based Assignment**: Staff hanya dapat tiket dari kategori yang ditugaskan
2. **One Ticket at a Time**: Staff hanya bisa ketika is_busy=false
3. **Auto-Queue**: Waiting tickets otomatis assign saat staff selesai
4. **Status Tracking**: Semua status change ter-log untuk audit
5. **Timestamp Recording**: assigned_at dan closed_at direkam untuk analytics

---

## 🚀 Peningkatan Masa Depan

### Fitur yang Bisa Ditambah
- [ ] Email notifications untuk guest dan staff
- [ ] Real-time status updates (WebSocket/Livewire)
- [ ] Customer feedback/rating setelah ticket closed
- [ ] SLA tracking (response time, resolution time)
- [ ] Staff performance metrics
- [ ] Advanced filtering dan search
- [ ] Bulk operations untuk admin
- [ ] Integration dengan external ticketing system
- [ ] Mobile app untuk staff
- [ ] Chatbot integration untuk common issues

---

## 📞 Support & Troubleshooting

### Error: "Tidak ada kategori"
- Pastikan admin sudah create categories di /admin/categories
- Assign category ke staff di /admin/categories/{category}

### Ticket tidak ter-assign otomatis
- Check apakah ada staff dengan category sesuai
- Pastikan staff profile created dengan is_busy=false
- Check ticket_logs untuk debug

### Staff tidak bisa lihat tiket
- Verify staff_id di ticket ada
- Check staff punya role='staff' di users table
- Pastikan staff profile exists

---

Dibuat: April 2026
Last Updated: April 15, 2026
