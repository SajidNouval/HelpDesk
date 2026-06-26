# AUDIT SISTEM TICKETING HELPDESK TA
## Laporan Audit Menyeluruh Berdasarkan Source Code

**Tanggal Audit:** 24 Juni 2026  
**Project:** HelpDesk TA  
**Metode:** Analisis source code, database, controller, model, migration, service, middleware, event, route, dan view

---

## 1. ALUR PEMBUATAN TIKET

### 1.1 Route yang Digunakan

**Route Utama:**
- `POST /tickets` → `TicketController@store` (web.php:127)
- `POST /tickets/request-otp` → `TicketController@requestOtp` (web.php:124)
- `POST /tickets/verify-otp` → `TicketController@verifyOtp` (web.php:125)
- `POST /reports` → `TicketController@storeReport` (web.php:128)
- `POST /chatbot/create-ticket` → `ChatbotController@createTicketAndMessage` (web.php:154)

**Route API:**
- `GET /articles/active-ticket` → Cek tiket aktif (api.php:87-129)
- `GET /tickets/latest` → Ambil tiket terbaru (api.php:81-84)
- `GET /tickets/{ticketId}/status` → Cek status tiket (api.php:29-71)
- `POST /tickets/{ticketId}/close` → Auto-close tiket (api.php:132-156)

### 1.2 Controller dan Method yang Terlibat

**Controller Utama:**
- `App\Http\Controllers\TicketController`
  - `store()` - Pembuatan tiket langsung (line 100-203)
  - `storeReport()` - Pembuatan laporan (line 229-321)
  - `requestOtp()` - Request OTP (line 336-401)
  - `verifyOtp()` - Verifikasi OTP (line 416-526)
  - `create()` - Tampilkan form tiket (line 64-73)

**Controller Tambahan:**
- `App\Http\Controllers\ChatbotController`
  - `createTicketAndMessage()` - Pembuatan tiket dari chatbot (line 425-467)

### 1.3 Validasi yang Diterapkan

**Validasi Input (TicketController@store - line 103-116):**
```php
$validationRules = [
    'name' => 'required|string|max:50',
    'email' => 'required|email|max:50',
    'subject' => 'required|string|max:200',
    'message' => 'required|string',
    'category_id' => 'required|exists:categories,id',
];
```

**Validasi OTP (TicketController@requestOtp - line 338-345):**
```php
$validated = $request->validate([
    'name' => 'required|string|max:50',
    'email' => 'required|email|max:50',
    'subject' => 'required|string|max:200',
    'message' => 'required|string',
    'category_id' => 'required|exists:categories,id',
    'type' => 'required|in:livechat,report',
]);
```

### 1.4 Mekanisme OTP

**Implementasi OTP:**
- **File:** `app/Models/TicketOtp.php`
- **Table:** `ticket_otps` (migration: 2026_05_06_000000_create_ticket_otps_table.php)

**Proses OTP:**
1. **Request OTP** (`TicketController@requestOtp` - line 336-401):
   - Generate 6-digit OTP: `str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT)` (line 365)
   - Set expiry: 15 menit (line 382)
   - Generate token unik: `Str::random(60)` (line 383)
   - Kirim email via `TicketOtpMail` (line 389)
   - Rate limit: 1 menit per IP dan email (line 352-363)

2. **Verifikasi OTP** (`TicketController@verifyOtp` - line 416-526):
   - Cek token valid (line 425)
   - Cek expiry (line 432-436)
   - Batas percobaan: 3 kali (line 438-456)
   - Hapus OTP setelah berhasil (line 506)
   - Buat tiket setelah verifikasi (line 464-474)

**Rate Limiting OTP:**
```php
Cache::put("ticket_otp_ip_{$ip}", true, 60);
Cache::put("ticket_otp_email_{$email}", true, 60);
```

### 1.5 Mekanisme Captcha

**Implementasi Captcha:**
- **Lokasi:** `TicketController@create` (line 69-71)
- **Tipe:** Simple numeric captcha (4 digit random)
- **Storage:** Session

**Generate Captcha:**
```php
$captcha = rand(1000, 9999);
session(['captcha' => $captcha]);
```

**Validasi Captcha:**
```php
if (!$request->expectsJson() && $request->captcha != session('captcha')) {
    return redirect()->back()->withErrors(['captcha' => 'Captcha salah.']);
}
```

**Catatan:** Captcha hanya untuk non-JSON request (line 112-114, 139-141)

### 1.6 Rate Limiting dan Anti-Spam

**Rate Limiting Tiket (TicketController@store - line 118-145):**
```php
// IP rate limit
if (Cache::has("ticket_ip_{$ip}")) {
    return response()->json(['error' => 'Terlalu banyak permintaan dari IP ini. Coba lagi dalam 1 menit.'], 429);
}

// Email rate limit
if (Cache::has("ticket_email_{$email}")) {
    return response()->json(['error' => 'Email ini sudah digunakan baru-baru ini. Coba lagi dalam 1 menit.'], 429);
}

// Set cache for 1 minute
Cache::put("ticket_ip_{$ip}", true, 60);
Cache::put("ticket_email_{$email}", true, 60);
```

**Rate Limiting Report (TicketController@storeReport - line 242-264):**
```php
// IP rate limit
if (Cache::has("report_ip_{$ip}")) {
    return response()->json(['error' => 'Terlalu banyak permintaan dari IP ini. Coba lagi dalam 1 menit.'], 429);
}

// Email rate limit
if (Cache::has("report_email_{$email}")) {
    return response()->json(['error' => 'Email ini sudah digunakan baru-baru ini. Coba lagi dalam 1 menit.'], 429);
}
```

**Rate Limiting OTP (TicketController@requestOtp - line 352-363):**
```php
if (Cache::has("ticket_otp_ip_{$ip}")) {
    return response()->json(['success' => false, 'message' => 'Terlalu banyak permintaan dari IP ini. Coba lagi dalam 1 menit.'], 429);
}

if (Cache::has("ticket_otp_email_{$email}")) {
    return response()->json(['success' => false, 'message' => 'Email ini sudah digunakan baru-baru ini. Coba lagi dalam 1 menit.'], 429);
}
```

---

## 2. ASSIGNMENT STAFF

### 2.1 Cara Sistem Memilih Staff

**Method Utama:**
- `TicketController@assignTicketToAvailableStaff()` (line 573-632)
- `TicketController@assignReportToStaff()` (line 657-703)

### 2.2 Berdasarkan Kategori

**Ya, assignment berdasarkan kategori:**
```php
$staffProfiles = StaffProfile::where('category_id', $ticket->category_id)
    ->where('is_busy', false)
    ->with('user')
    ->lockForUpdate()
    ->get();
```
- **Lokasi:** `TicketController@assignTicketToAvailableStaff` (line 576-580)
- **Table:** `staff_profiles.category_id` → `categories.id`

### 2.3 Menggunakan Workload

**Ya, menggunakan workload balancing:**

**Untuk Live Chat (assignTicketToAvailableStaff - line 586-606):**
```php
$staffWithCounts = $staffProfiles->map(function ($profile) {
    return [
        'profile' => $profile,
        'active_tickets' => $profile->user->tickets()
            ->whereIn('status', ['assigned', 'progress'])
            ->count(),
        'waiting_reports' => $profile->user->tickets()
            ->where('status', 'waiting')
            ->count(),
    ];
});

$best = $staffWithCounts->sort(function ($a, $b) {
    if ($a['active_tickets'] !== $b['active_tickets']) {
        return $a['active_tickets'] <=> $b['active_tickets'];
    }
    if ($a['waiting_reports'] !== $b['waiting_reports']) {
        return $a['waiting_reports'] <=> $b['waiting_reports'];
    }
    return $a['profile']->id <=> $b['profile']->id;
})->first();
```

**Prioritas Sorting:**
1. Active tickets (assigned + progress) - paling sedikit diprioritaskan
2. Waiting reports - paling sedikit diprioritaskan
3. Profile ID - tiebreaker

**Untuk Report (assignReportToStaff - line 667-681):**
```php
$staffWithWaitingCounts = $staffProfiles->map(function ($profile) {
    return [
        'profile' => $profile,
        'waiting_count' => $profile->user->tickets()
            ->where('status', 'waiting')
            ->count(),
    ];
});

$best = $staffWithWaitingCounts->sort(function ($a, $b) {
    if ($a['waiting_count'] !== $b['waiting_count']) {
        return $a['waiting_count'] <=> $b['waiting_count'];
    }
    return $a['profile']->id <=> $b['profile']->id;
})->first();
```

### 2.4 Menggunakan Status Busy

**Ya, menggunakan status busy:**

**Filter Staff yang Tidak Busy:**
```php
$staffProfiles = StaffProfile::where('category_id', $ticket->category_id)
    ->where('is_busy', false)
    ->with('user')
    ->lockForUpdate()
    ->get();
```
- **Lokasi:** `TicketController@assignTicketToAvailableStaff` (line 576-580)

**Set Staff Menjadi Busy Setelah Assignment:**
```php
$bestStaff->update(['is_busy' => true]);
```
- **Lokasi:** `TicketController@assignTicketToAvailableStaff` (line 620)

**Catatan:** `assignReportToStaff` TIDAK memfilter `is_busy` (line 659-661), hanya memfilter berdasarkan kategori.

### 2.5 Rumus/Logika yang Digunakan

**Logika Assignment Live Chat:**
1. Filter staff di kategori yang sama dengan `is_busy = false`
2. Hitung active tickets (status: assigned, progress)
3. Hitung waiting reports (status: waiting)
4. Sort berdasarkan:
   - Active tickets terendah
   - Waiting reports terendah
   - Profile ID terkecil (tiebreaker)
5. Assign tiket ke staff terbaik
6. Set staff `is_busy = true`
7. Update tiket status menjadi `assigned`

**Logika Assignment Report:**
1. Filter staff di kategori yang sama (tanpa filter busy)
2. Hitung waiting reports
3. Sort berdasarkan:
   - Waiting count terendah
   - Profile ID terkecil (tiebreaker)
4. Assign tiket ke staff terbaik
5. Update tiket status menjadi `waiting` (tetap waiting, bukan assigned)

---

## 3. WORKFLOW STATUS TIKET

### 3.1 Seluruh Status yang Tersedia

**Status Tiket (Migration: 2026_04_06_132355_ticket.php - line 65-72):**
```php
$table->enum('status', [
    'open',
    'assigned',
    'progress',
    'waiting',
    'closed',
    'suspended'
])->default('open');
```

**Status yang Tersedia:**
1. `open` - Tiket baru dibuat
2. `assigned` - Tiket sudah di-assign ke staff
3. `progress` - Tiket sedang dikerjakan
4. `waiting` - Tiket menunggu staff tersedia
5. `closed` - Tiket selesai/ditutup
6. `suspended` - Tiket ditangguhkan (defined in migration tapi TIDAK digunakan dalam code)

### 3.2 Transisi Antar Status

**Transisi Status dari Source Code:**

**1. OPEN → ASSIGNED**
- **Lokasi:** `TicketController@store` (line 164)
- **Kondisi:** Staff tersedia untuk assignment
- **Code:**
```php
$staffProfile = $this->assignTicketToAvailableStaff($ticket);
if (!$staffProfile) {
    $ticket->update(['status' => 'waiting']);
}
```

**2. OPEN → WAITING**
- **Lokasi:** `TicketController@store` (line 167)
- **Kondisi:** Tidak ada staff tersedia
- **Code:**
```php
if (!$staffProfile) {
    $ticket->update(['status' => 'waiting']);
}
```

**3. WAITING → ASSIGNED**
- **Lokasi:** `Staff\TicketController@startProgress` (line 219-223)
- **Kondisi:** Staff claim tiket waiting
- **Code:**
```php
if ($ticket->status === 'waiting' && $ticket->staff_id === null) {
    $ticket->update([
        'staff_id' => $user->id,
        'status' => 'progress',
        'assigned_at' => now(),
    ]);
}
```

**4. ASSIGNED → PROGRESS**
- **Lokasi:** `Staff\TicketController@startProgress` (line 244-246)
- **Kondisi:** Staff mulai mengerjakan tiket
- **Code:**
```php
$ticket->update([
    'status' => 'progress',
]);
```

**5. PROGRESS → CLOSED**
- **Lokasi:** `Staff\TicketController@complete` (line 375-379)
- **Kondisi:** Staff menyelesaikan tiket
- **Code:**
```php
$ticket->update([
    'status' => 'closed',
    'priority' => $request->priority,
    'closed_at' => now(),
]);
```

**6. WAITING → CLOSED**
- **Lokasi:** `Staff\TicketController@complete` (line 375-379)
- **Kondisi:** Staff menyelesaikan tiket waiting
- **Code:** Sama dengan di atas

**7. ASSIGNED → CLOSED (Reject)**
- **Lokasi:** `Staff\TicketController@reject` (line 298-302)
- **Kondisi:** Staff menolak tiket
- **Code:**
```php
$ticket->update([
    'status' => 'closed',
    'closed_at' => now(),
]);
```

**8. PROGRESS → WAITING (Suspend)**
- **Lokasi:** `Staff\TicketController@suspend` (line 481-483)
- **Kondisi:** Staff menangguhkan tiket
- **Code:**
```php
$ticket->update([
    'status' => 'waiting',
]);
```

**9. ASSIGNED → WAITING (Suspend)**
- **Lokasi:** `Staff\TicketController@suspend` (line 481-483)
- **Kondisi:** Staff menangguhkan tiket
- **Code:** Sama dengan di atas

**10. AUTO-CLOSE (Assigned > 20 menit)**
- **Lokasi:** `api.php` (line 29-71)
- **Kondisi:** Tiket assigned tapi tidak dimulai dalam 20 menit
- **Code:**
```php
if ($ticket->status === 'assigned' && $ticket->assigned_at) {
    $assignedTime = \Carbon\Carbon::parse($ticket->assigned_at);
    $now = now();
    $minutesSinceAssigned = $assignedTime->diffInMinutes($now);
    
    if ($minutesSinceAssigned >= 20) {
        $ticket->update([
            'status' => 'closed',
            'closed_at' => $now,
        ]);
    }
}
```

**11. AUTO-CLOSE (Open/Waiting > 20 menit)**
- **Lokasi:** `api.php` (line 132-156)
- **Kondisi:** Tiket open/waiting tidak ada staff tersedia dalam 20 menit
- **Code:**
```php
if (in_array($ticket->status, ['open', 'waiting'])) {
    $ticket->update([
        'status' => 'closed',
        'closed_at' => now(),
    ]);
}
```

### 3.3 Kondisi yang Menyebabkan Perubahan Status

**Tabel Transisi Status:**

| Dari Status | Ke Status | Trigger | Lokasi |
|-------------|-----------|---------|--------|
| open | assigned | Staff tersedia | TicketController@store:164 |
| open | waiting | Tidak ada staff tersedia | TicketController@store:167 |
| waiting | progress | Staff claim tiket | Staff\TicketController@startProgress:219 |
| assigned | progress | Staff mulai mengerjakan | Staff\TicketController@startProgress:244 |
| progress | closed | Staff selesaikan tiket | Staff\TicketController@complete:375 |
| waiting | closed | Staff selesaikan tiket | Staff\TicketController@complete:375 |
| assigned | closed | Staff tolak tiket | Staff\TicketController@reject:298 |
| progress | waiting | Staff tangguhkan tiket | Staff\TicketController@suspend:481 |
| assigned | waiting | Staff tangguhkan tiket | Staff\TicketController@suspend:481 |
| assigned | closed | Auto-close 20 menit | api.php:39 |
| open | closed | Auto-close 20 menit | api.php:137 |
| waiting | closed | Auto-close 20 menit | api.php:137 |

---

## 4. MEKANISME LIVE CHAT

### 4.1 Controller yang Digunakan

**Controller Utama:**
- `App\Http\Controllers\MessageController`
  - `store()` - Kirim pesan (line 71-141)
  - `index()` - Ambil pesan tiket (line 178-223)

**Controller Tambahan:**
- `App\Http\Controllers\ChatbotController`
  - `sendMessage()` - Kirim pesan dari chatbot (line 490-513)
  - `getTicketMessages()` - Ambil pesan tiket (line 533-548)

### 4.2 Event dan Broadcasting yang Digunakan

**Event yang Tersedia:**
1. **MessageSent** (`app/Events/MessageSent.php`)
   - Trigger: Ketika pesan baru dikirim
   - Channel: `ticket.{ticket_id}` (line 40)
   - Broadcast data: id, ticket_id, message, sender_type, sender_name, created_at (line 63-72)

2. **TicketClosed** (`app/Events/TicketClosed.php`)
   - Trigger: Ketika tiket ditutup
   - Channel: `ticket.{ticket_id}` (line 40)
   - Broadcast data: ticket_id (line 63-68)

3. **StaffConnected** (`app/Events/StaffConnected.php`)
   - Trigger: Ketika staff terhubung ke tiket
   - Channel: `ticket.{ticket_id}` (line 41)
   - Broadcast data: ticket_id, staff_id, staff_name (line 64-70)

**Lokasi Broadcast:**
- MessageSent: `MessageController@store` (line 138)
- TicketClosed: `Staff\TicketController@reject` (line 323), `Staff\TicketController@complete` (line 439), `Staff\TicketController@suspend` (line 543), `api.php` (line 53, 150)
- StaffConnected: `Staff\TicketController@startProgress` (line 235, 254)

### 4.3 Implementasi WebSocket/Reverb

**Channel Authorization (routes/channels.php - line 21-36):**
```php
Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = \App\Models\Ticket::find($ticketId);
    if (!$ticket) {
        return false;
    }

    if ($user && $user->role === 'admin') {
        return true;
    }

    if ($user && $user->role === 'staff' && $ticket->staff_id === $user->id) {
        return true;
    }

    return in_array($ticketId, session()->get('my_tickets', []), true);
});
```

**Authorization Logic:**
1. Admin: Akses ke semua tiket
2. Staff: Akses ke tiket yang ditugaskan ke mereka
3. Guest: Akses ke tiket yang ada di session `my_tickets`

**Catatan:** Tidak ditemukan konfigurasi Reverb spesifik dalam code. Menggunakan Laravel Broadcasting default.

### 4.4 Penyimpanan Pesan

**Table:** `messages` (migration: 2026_04_06_132425_messages.php)

**Struktur Table:**
```php
$table->ulid('id')->primary();
$table->foreignUlid('ticket_id')->constrained()->cascadeOnDelete();
$table->enum('sender_type', ['guest', 'staff']);
$table->foreignUlid('sender_id')->nullable()->constrained('users')->nullOnDelete();
$table->text('message');
$table->boolean('is_read')->default(false);
$table->timestamps();
```

**Model:** `app/Models/Message.php`

**Relasi:**
- `belongsTo(Ticket)` - Tiket tempat pesan berada
- `belongsTo(User)` - Pengirim pesan (untuk staff)

**Insert Pesan (MessageController@store - line 121-127):**
```php
$message = Message::create([
    'ticket_id' => $ticket->id,
    'sender_type' => $senderType,
    'sender_id' => $senderId,
    'message' => $request->message,
    'is_read' => false,
]);
```

**Validasi Akses Chat (MessageController@store - line 98-105):**
```php
if (!$isStaff && in_array($ticket->status, ['assigned', 'waiting', 'closed'])) {
    return response()->json(['error' => 'Tiket sedang tidak terhubung atau sudah ditutup.'], 403);
}

// Untuk tiket waiting, tidak boleh chat sama sekali
if ($ticket->status === 'waiting') {
    return response()->json(['error' => 'Tiket sedang dalam status waiting dan chat tidak diizinkan.'], 403);
}
```

**Kondisi Chat yang Diizinkan:**
- Staff: Boleh chat jika status bukan `closed`
- Guest: Boleh chat jika status `assigned` atau `progress`
- Waiting: TIDAK boleh chat sama sekali
- Closed: TIDAK boleh chat

---

## 5. MEKANISME WAITING DAN QUEUE

### 5.1 Kapan Tiket Masuk Waiting

**1. Tidak Ada Staff Tersedia (TicketController@store - line 167-172):**
```php
if (!$staffProfile) {
    $ticket->update(['status' => 'waiting']);
    TicketLog::create([
        'ticket_id' => $ticket->id,
        'action' => 'waiting',
        'description' => 'Belum ada staff tersedia',
    ]);
}
```

**2. Pembuatan Report (TicketController@storeReport - line 274):**
```php
$ticket = Ticket::create([
    'name' => $request->name,
    'email' => $request->email,
    'subject' => $request->subject,
    'message' => $request->message,
    'category_id' => $request->category_id,
    'status' => 'waiting', // Langsung waiting
]);
```

**3. Staff Suspend Tiket (Staff\TicketController@suspend - line 481-483):**
```php
$ticket->update([
    'status' => 'waiting',
]);
```

**4. Verifikasi OTP Tidak Ada Staff (TicketController@verifyOtp - line 486-491):**
```php
if (!$staffProfile) {
    $ticket->update(['status' => 'waiting']);
    TicketLog::create([
        'ticket_id' => $ticket->id,
        'action' => 'waiting',
        'description' => 'Belum ada staff tersedia setelah verifikasi OTP.',
    ]);
}
```

### 5.2 Kapan Tiket Keluar Waiting

**1. Staff Claim Tiket (Staff\TicketController@startProgress - line 219-223):**
```php
if ($ticket->status === 'waiting' && $ticket->staff_id === null) {
    $ticket->update([
        'staff_id' => $user->id,
        'status' => 'progress',
        'assigned_at' => now(),
    ]);
}
```

**2. Auto-Assignment Setelah Staff Selesai (Staff\TicketController@complete - line 397-435):**
```php
$nextTicket = Ticket::where('category_id', $staffProfile->category_id)
    ->where('status', 'waiting')
    ->whereNull('staff_id') // Hanya tiket yang belum di-assign
    ->oldest()
    ->first();

if ($nextTicket) {
    $availableStaff = StaffProfile::where('category_id', $staffProfile->category_id)
        ->where('is_busy', false)
        ->get()
        ->sortBy(function ($profile) {
            $activeCount = $profile->user->tickets()
                ->whereIn('status', ['assigned', 'progress', 'waiting'])
                ->count();
            return $activeCount;
        })
        ->first();

    if ($availableStaff) {
        $nextTicket->update([
            'staff_id' => $availableStaff->user_id,
            'status' => 'assigned',
            'assigned_at' => now(),
        ]);
    }
}
```

**3. Auto-Assignment Setelah Staff Suspend (Staff\TicketController@suspend - line 501-539):**
```php
// Logika sama dengan complete
```

**4. Staff Selesaikan Tiket Waiting (Staff\TicketController@complete - line 375-379):**
```php
$ticket->update([
    'status' => 'closed',
    'priority' => $request->priority,
    'closed_at' => now(),
]);
```

**5. Staff Tolak Tiket Waiting (Staff\TicketController@reject - line 298-302):**
```php
$ticket->update([
    'status' => 'closed',
    'closed_at' => now(),
]);
```

### 5.3 Bagaimana Sistem Memproses Antrean

**Logika Queue Processing:**

**1. FIFO (First In First Out):**
```php
$nextTicket = Ticket::where('category_id', $staffProfile->category_id)
    ->where('status', 'waiting')
    ->whereNull('staff_id')
    ->oldest() // FIFO
    ->first();
```
- **Lokasi:** `Staff\TicketController@complete` (line 397-401), `Staff\TicketController@suspend` (line 501-505)

**2. Filter Berdasarkan Kategori:**
- Hanya tiket waiting di kategori yang sama dengan staff yang baru selesai

**3. Filter Tiket Belum Di-assign:**
- `whereNull('staff_id')` - Hanya tiket yang belum ada staff-nya

**4. Load Balancing untuk Assignment:**
```php
$availableStaff = StaffProfile::where('category_id', $staffProfile->category_id)
    ->where('is_busy', false)
    ->get()
    ->sortBy(function ($profile) {
        $activeCount = $profile->user->tickets()
            ->whereIn('status', ['assigned', 'progress', 'waiting'])
            ->count();
        return $activeCount;
    })
    ->first();
```
- Pilih staff dengan active ticket paling sedikit

**5. Auto-Assignment Trigger:**
- Trigger ketika staff complete tiket
- Trigger ketika staff suspend tiket
- Tidak ada background job/cron untuk queue processing

---

## 6. MEKANISME PENYELESAIANAN TIKET

### 6.1 Proses Close Ticket

**Method:**
- `Staff\TicketController@complete()` (line 356-442)
- `Staff\TicketController@reject()` (line 284-326)
- Auto-close di `api.php` (line 29-71, 132-156)

**Proses Complete (Staff\TicketController@complete - line 356-442):**
1. Validasi akses (staff harus pemilik tiket)
2. Validasi status (hanya progress atau waiting)
3. Validasi priority input
4. Update tiket:
   ```php
   $ticket->update([
       'status' => 'closed',
       'priority' => $request->priority,
       'closed_at' => now(),
   ]);
   ```
5. Update staff status:
   ```php
   StaffProfile::where('user_id', $user->id)->update([
       'is_busy' => false,
   ]);
   ```
6. Log penyelesaian
7. Cari tiket waiting berikutnya untuk auto-assign
8. Broadcast event TicketClosed

**Proses Reject (Staff\TicketController@reject - line 284-326):**
1. Validasi akses
2. Validasi status (hanya assigned atau waiting)
3. Update tiket ke closed
4. Update staff status ke tidak busy
5. Log penolakan
6. Kirim email penolakan ke guest
7. Broadcast event TicketClosed

### 6.2 Perubahan Status Staff

**Staff Menjadi Busy:**
1. **Assignment Tiket (TicketController@assignTicketToAvailableStaff - line 620):**
   ```php
   $bestStaff->update(['is_busy' => true]);
   ```

2. **Staff Claim Tiket (Staff\TicketController@startProgress - line 225-227):**
   ```php
   StaffProfile::where('user_id', $user->id)->update([
       'is_busy' => true,
   ]);
   ```

3. **Reassign Tiket (Staff\TicketController@reassign - line 660):**
   ```php
   $newStaffProfile->update(['is_busy' => true]);
   ```

**Staff Menjadi Tidak Busy:**
1. **Complete Tiket (Staff\TicketController@complete - line 382-384):**
   ```php
   StaffProfile::where('user_id', $user->id)->update([
       'is_busy' => false,
   ]);
   ```

2. **Reject Tiket (Staff\TicketController@reject - line 305-307):**
   ```php
   StaffProfile::where('user_id', $user->id)->update([
       'is_busy' => false,
   ]);
   ```

3. **Suspend Tiket (Staff\TicketController@suspend - line 487-489):**
   ```php
   StaffProfile::where('user_id', auth()->id())->update([
       'is_busy' => false,
   ]);
   ```

4. **Reassign Tiket (Staff\TicketController@reassign - line 655):**
   ```php
   $oldStaffProfile->update(['is_busy' => false]);
   ```

### 6.3 Trigger Setelah Tiket Selesai

**1. Auto-Assign Tiket Waiting Berikutnya:**
- **Lokasi:** `Staff\TicketController@complete` (line 393-437)
- **Logika:**
  - Cari tiket waiting di kategori yang sama
  - Filter tiket yang belum di-assign
  - Pilih staff paling available
  - Assign tiket ke staff tersebut

**2. Broadcast Event TicketClosed:**
- **Lokasi:** `Staff\TicketController@complete` (line 439)
- **Code:**
  ```php
  broadcast(new \App\Events\TicketClosed($ticket));
  ```

**3. Kirim Email Rejection (jika reject):**
- **Lokasi:** `Staff\TicketController@reject` (line 316-321)
- **Code:**
  ```php
  \Mail::to($ticket->email)->send(new \App\Mail\TicketRejectionMail($ticket));
  ```

**4. Log Aktivitas:**
- **Lokasi:** `Staff\TicketController@complete` (line 387-391)
- **Code:**
  ```php
  TicketLog::create([
      'ticket_id' => $ticket->id,
      'action' => 'closed',
      'description' => 'Tiket diselesaikan oleh staff: ' . $user->name,
  ]);
  ```

---

## 7. TICKET LOG

### 7.1 Struktur Tabel

**Table:** `ticket_logs` (migration: 2026_04_06_132456_ticketlogs.php)

**Struktur:**
```php
$table->ulid('id')->primary();
$table->foreignUlid('ticket_id')->constrained()->cascadeOnDelete();
$table->string('action');
$table->text('description')->nullable();
$table->timestamps();
```

**Model:** `app/Models/TicketLog.php`

**Relasi:**
- `belongsTo(Ticket)` - Tiket yang dicatat lognya

### 7.2 Aktivitas yang Dicatat

**Action yang Dicatat:**

1. **created** - Tiket dibuat
   - Lokasi: `TicketController@store` (line 158-162)
   - Deskripsi: "Tiket dibuat oleh user"

2. **waiting** - Tiket masuk waiting
   - Lokasi: `TicketController@store` (line 168-172)
   - Deskripsi: "Belum ada staff tersedia"

3. **assigned** - Tiket di-assign ke staff
   - Lokasi: `TicketController@assignTicketToAvailableStaff` (line 622-628)
   - Deskripsi: "Tiket di-assign ke staff: {name} (active: {count}, waiting: {count})"

4. **priority_updated** - Priority diubah
   - Lokasi: `Staff\TicketController@updatePriority` (line 182-186)
   - Deskripsi: "Priority diubah dari {old} menjadi {new}"

5. **claimed** - Tiket di-claim staff
   - Lokasi: `Staff\TicketController@startProgress` (line 229-233)
   - Deskripsi: "Tiket di-claim dan dimulai oleh staff: {name}"

6. **progress_started** - Staff mulai mengerjakan
   - Lokasi: `Staff\TicketController@startProgress` (line 248-252)
   - Deskripsi: "Staff mulai mengerjakan tiket"

7. **rejected** - Tiket ditolak
   - Lokasi: `Staff\TicketController@reject` (line 310-314)
   - Deskripsi: "Tiket ditolak oleh staff: {name}. Staff tidak dapat menerima tiket pada saat ini."

8. **closed** - Tiket ditutup
   - Lokasi: `Staff\TicketController@complete` (line 387-391)
   - Deskripsi: "Tiket diselesaikan oleh staff: {name}"

9. **waiting** - Tiket ditangguhkan
   - Lokasi: `Staff\TicketController@suspend` (line 491-495)
   - Deskripsi: "Tiket ditangguhkan oleh staff dan chat dihentikan sementara."

10. **reassigned** - Tiket di-reassign
    - Lokasi: `Staff\TicketController@reassign` (line 663-667)
    - Deskripsi: "Tiket di-reassign dari {old} ke {new}"

11. **staff_update** - Update manual staff
    - Lokasi: `Staff\TicketController@storeLog` (line 578-582)
    - Deskripsi: Input manual dari staff

12. **auto_closed** - Auto-close sistem
    - Lokasi: `api.php` (line 46-51, 143-148)
    - Deskripsi: "Tiket ditutup otomatis karena staff tidak merespons dalam 20 menit setelah assignment." atau "Tiket ditutup otomatis karena tidak ada staff tersedia dalam 20 menit."

### 7.3 Controller atau Service yang Membuat Log

**Controller yang Membuat Log:**
1. `TicketController` - Log pembuatan, assignment, waiting
2. `Staff\TicketController` - Log priority, progress, reject, complete, suspend, reassign, manual log
3. `api.php` (closure) - Log auto-close

**Tidak Ada Service Khusus untuk Log:**
- Log dibuat langsung di controller menggunakan `TicketLog::create()`
- Tidak ada service layer untuk log management

**Contoh Pembuatan Log:**
```php
TicketLog::create([
    'ticket_id' => $ticket->id,
    'action' => 'created',
    'description' => 'Tiket dibuat oleh user',
]);
```

---

## 8. DATABASE TICKETING

### 8.1 Seluruh Tabel Terkait Ticketing

**1. tickets**
- Migration: `2026_04_06_132355_ticket.php`
- Model: `app/Models/Ticket.php`

**2. ticket_logs**
- Migration: `2026_04_06_132456_ticketlogs.php`
- Model: `app/Models/TicketLog.php`

**3. ticket_otps**
- Migration: `2026_05_06_000000_create_ticket_otps_table.php`
- Model: `app/Models/TicketOtp.php`

**4. messages**
- Migration: `2026_04_06_132425_messages.php`
- Model: `app/Models/Message.php`

**5. staff_profiles**
- Migration: `2026_04_06_132330_staff.php`
- Model: `app/Models/StaffProfile.php`

**6. categories**
- Migration: `2026_04_06_131118_categories.php`
- Model: `app/Models/Category.php`

**7. users**
- Migration: `2014_10_12_000000_create_users_table.php`
- Model: `app/Models/User.php`

**8. settings**
- Migration: `2026_05_12_000000_create_settings_table.php`
- Model: `app/Models/Setting.php`

### 8.2 Relasi Antar Tabel

**Relasi tickets:**
- `belongsTo(Category)` - category_id → categories.id
- `belongsTo(User, 'staff_id')` - staff_id → users.id
- `belongsTo(User, 'user_id')` - user_id → users.id
- `hasMany(Message)` - messages.ticket_id → tickets.id
- `hasMany(TicketLog)` - ticket_logs.ticket_id → tickets.id

**Relasi ticket_logs:**
- `belongsTo(Ticket)` - ticket_id → tickets.id

**Relasi ticket_otps:**
- `belongsTo(Category)` - category_id → categories.id

**Relasi messages:**
- `belongsTo(Ticket)` - ticket_id → tickets.id
- `belongsTo(User, 'sender_id')` - sender_id → users.id

**Relasi staff_profiles:**
- `belongsTo(User)` - user_id → users.id
- `belongsTo(Category)` - category_id → categories.id

**Relasi categories:**
- `hasMany(StaffProfile)` - staff_profiles.category_id → categories.id
- `hasMany(Article)` - articles.category_id → categories.id

**Relasi users:**
- `hasMany(StaffProfile)` - staff_profiles.user_id → users.id
- `hasMany(Ticket, 'staff_id')` - tickets.staff_id → users.id
- `hasMany(Ticket, 'user_id')` - tickets.user_id → users.id
- `hasMany(Article, 'staff_id')` - articles.staff_id → users.id

### 8.3 Foreign Key yang Digunakan

**tickets:**
- `category_id` → `categories.id` (cascade on delete)
- `user_id` → `users.id` (null on delete)
- `staff_id` → `users.id` (null on delete)

**ticket_logs:**
- `ticket_id` → `tickets.id` (cascade on delete)

**ticket_otps:**
- `category_id` → `categories.id` (cascade on delete)

**messages:**
- `ticket_id` → `tickets.id` (cascade on delete)
- `sender_id` → `users.id` (null on delete)

**staff_profiles:**
- `user_id` → `users.id` (cascade on delete)
- `category_id` → `categories.id` (cascade on delete)

---

## 9. SEQUENCE PROSES AKTUAL

### 9.1 Sequence Pembuatan Tiket (Direct Form)

**Langkah 1: User Akses Form**
- Route: `GET /help` → `TicketController@create`
- Generate captcha 4 digit
- Simpan captcha ke session
- Ambil semua kategori
- Cek status live service

**Langkah 2: User Submit Form**
- Route: `POST /tickets` → `TicketController@store`
- Validasi input (name, email, subject, message, category_id)
- Validasi captcha (untuk non-JSON request)
- Cek rate limit IP (1 menit)
- Cek rate limit email (1 menit)
- Set rate limit cache

**Langkah 3: Buat Tiket dalam Transaction**
- Create ticket dengan status `open`
- Create log action `created`
- Panggil `assignTicketToAvailableStaff()`

**Langkah 4: Assignment Staff**
- Query staff di kategori yang sama dengan `is_busy = false`
- Hitung active tickets per staff
- Hitung waiting reports per staff
- Sort staff berdasarkan workload terendah
- Assign tiket ke staff terbaik
- Update tiket status ke `assigned`
- Update staff `is_busy = true`
- Create log action `assigned`

**Langkah 5: Handle No Staff Available**
- Jika tidak ada staff tersedia:
  - Update tiket status ke `waiting`
  - Create log action `waiting`

**Langkah 6: Simpan Session**
- Simpan ticket_id ke session
- Simpan guest_ticket_id ke session (untuk guest)
- Simpan guest_email ke session (untuk guest)

**Langkah 7: Return Response**
- JSON response dengan data tiket (untuk API)
- Redirect dengan success message (untuk web)

### 9.2 Sequence Pembuatan Tiket (OTP Flow)

**Langkah 1: User Request OTP**
- Route: `POST /tickets/request-otp` → `TicketController@requestOtp`
- Validasi input (name, email, subject, message, category_id, type)
- Cek rate limit IP (1 menit)
- Cek rate limit email (1 menit)
- Cek live service enabled (untuk type livechat)
- Generate OTP 6 digit
- Set expiry 15 menit
- Generate token unik
- Simpan ke table `ticket_otps`
- Kirim email OTP
- Return verification token

**Langkah 2: User Verify OTP**
- Route: `POST /tickets/verify-otp` → `TicketController@verifyOtp`
- Validasi input (verification_token, otp_code)
- Cari OTP berdasarkan token (lockForUpdate)
- Cek expiry OTP
- Cek attempts (max 3)
- Validasi OTP code
- Cek live service enabled (untuk type livechat)
- Delete OTP setelah validasi

**Langkah 3: Buat Tiket dalam Transaction**
- Create ticket dengan status:
  - `open` untuk type livechat
  - `waiting` untuk type report
- Set priority `low`
- Generate tracking_token
- Set email_verified_at
- Create log action `created`

**Langkah 4: Assignment Berdasarkan Type**
- Untuk livechat:
  - Panggil `assignTicketToAvailableStaff()`
  - Jika tidak ada staff, update status ke `waiting`
- Untuk report:
  - Panggil `assignReportToStaff()`
  - Jika tidak ada staff, create log waiting

**Langkah 5: Kirim Email Tracking**
- Kirim email dengan tracking URL
- Return response dengan ticket data dan tracking URL

### 9.3 Sequence Live Chat

**Langkah 1: User Kirim Pesan**
- Route: `POST /messages` → `MessageController@store`
- Validasi input (ticket_id, message)
- Cek otorisasi (staff atau owner)
- Cek status tiket (tidak boleh waiting atau closed)
- Tentukan sender_type (staff atau guest)
- Create message
- Load relasi sender
- Broadcast event MessageSent
- Return response

**Langkah 2: Client Terima Broadcast**
- Subscribe ke channel `ticket.{ticket_id}`
- Terima event MessageSent
- Update UI dengan pesan baru

**Langkah 3: Staff Reply**
- Staff kirim pesan dengan sender_type = 'staff'
- Create message
- Broadcast event MessageSent
- Client terima dan tampilkan pesan

### 9.4 Sequence Staff Processing

**Langkah 1: Staff Lihat Daftar Tiket**
- Route: `GET /staff/tickets` → `Staff\TicketController@index`
- Query tiket milik staff
- Filter berdasarkan priority
- Pisahkan tiket: active, completed, waiting

**Langkah 2: Staff Mulai Mengerjakan**
- Route: `PATCH /staff/tickets/{ticket}/start-progress` → `Staff\TicketController@startProgress`
- Jika tiket waiting dan belum di-assign:
  - Assign ke staff
  - Update status ke `progress`
  - Update staff `is_busy = true`
  - Create log `claimed`
  - Broadcast event StaffConnected
- Jika tiket sudah assigned:
  - Update status ke `progress`
  - Create log `progress_started`
  - Broadcast event StaffConnected

**Langkah 3: Staff Chat dengan Guest**
- Kirim pesan via MessageController
- Broadcast real-time

**Langkah 4: Staff Selesaikan Tiket**
- Route: `PATCH /staff/tickets/{ticket}/complete` → `Staff\TicketController@complete`
- Validasi status (progress atau waiting)
- Update tiket status ke `closed`
- Update staff `is_busy = false`
- Create log `closed`
- Cari tiket waiting berikutnya
- Auto-assign tiket waiting ke staff available
- Broadcast event TicketClosed

**Langkah 5: Staff Tolak Tiket**
- Route: `PATCH /staff/tickets/{ticket}/reject` → `Staff\TicketController@reject`
- Validasi status (assigned atau waiting)
- Update tiket status ke `closed`
- Update staff `is_busy = false`
- Create log `rejected`
- Kirim email rejection
- Broadcast event TicketClosed

**Langkah 6: Staff Tangguhkan Tiket**
- Route: `PATCH /staff/tickets/{ticket}/suspend` → `Staff\TicketController@suspend`
- Validasi status (bukan closed)
- Update tiket status ke `waiting`
- Update staff `is_busy = false`
- Create log `waiting`
- Cari tiket waiting berikutnya
- Auto-assign tiket waiting ke staff available
- Broadcast event TicketClosed

### 9.5 Sequence Auto-Close

**Langkah 1: Client Poll Status**
- Route: `GET /tickets/{ticketId}/status` → api.php closure
- Cek status tiket

**Langkah 2: Cek Auto-Close Assigned**
- Jika status `assigned` dan assigned_at > 20 menit:
  - Update status ke `closed`
  - Update closed_at
  - Create log `auto_closed`
  - Broadcast event TicketClosed
  - Return response dengan auto_closed = true

**Langkah 3: Cek Auto-Close Open/Waiting**
- Route: `POST /tickets/{ticketId}/close` → api.php closure
- Jika status `open` atau `waiting`:
  - Update status ke `closed`
  - Update closed_at
  - Create log `auto_closed`
  - Broadcast event TicketClosed
  - Return success

---

## 10. TEMUAN PENTING

### 10.1 Fitur yang Benar-Benar Ada

**1. OTP Verification**
- ✅ Request OTP via email
- ✅ 6-digit OTP code
- ✅ 15 menit expiry
- ✅ Max 3 attempts
- ✅ Rate limiting 1 menit per IP/email
- ✅ Support untuk livechat dan report
- ✅ Check live service status

**2. Captcha**
- ✅ Simple 4-digit numeric captcha
- ✅ Session-based storage
- ✅ Hanya untuk non-JSON request
- ✅ Validasi saat submit form

**3. Rate Limiting**
- ✅ IP-based rate limiting (1 menit)
- ✅ Email-based rate limiting (1 menit)
- ✅ Terpisah untuk tiket, report, dan OTP
- ✅ Menggunakan Laravel Cache

**4. Staff Assignment dengan Load Balancing**
- ✅ Filter berdasarkan kategori
- ✅ Filter berdasarkan status busy
- ✅ Hitung active tickets
- ✅ Hitung waiting reports
- ✅ Sort berdasarkan workload terendah
- ✅ Tiebreaker dengan profile ID
- ✅ Lock for update untuk race condition prevention

**5. Live Chat dengan WebSocket**
- ✅ Real-time messaging via Laravel Broadcasting
- ✅ Channel authorization
- ✅ Event MessageSent
- ✅ Event TicketClosed
- ✅ Event StaffConnected
- ✅ Public channel untuk ticket chat

**6. Status Workflow**
- ✅ 6 status: open, assigned, progress, waiting, closed, suspended
- ✅ Transisi status yang terdefinisi
- ✅ Validasi transisi
- ✅ Auto-close mechanism

**7. Queue Processing**
- ✅ FIFO queue (oldest first)
- ✅ Filter berdasarkan kategori
- ✅ Filter tiket belum di-assign
- ✅ Auto-assign setelah staff selesai
- ✅ Load balancing untuk assignment

**8. Auto-Close Mechanism**
- ✅ Auto-close assigned > 20 menit
- ✅ Auto-close open/waiting > 20 menit
- ✅ Log auto-close
- ✅ Broadcast event

**9. Ticket Log**
- ✅ Log semua aktivitas tiket
- ✅ 12+ action types
- ✅ Deskripsi detail
- ✅ Relasi ke tiket

**10. Staff Busy Status**
- ✅ is_busy flag di staff_profiles
- ✅ Auto-set busy saat assignment
- ✅ Auto-set not busy saat complete/reject/suspend
- ✅ Filter staff berdasarkan busy status

**11. Email Notifications**
- ✅ OTP email
- ✅ Tracking email
- ✅ Rejection email
- ✅ Menggunakan Laravel Mail

**12. Session Management**
- ✅ my_tickets session array
- ✅ guest_ticket_id session
- ✅ guest_email session
- ✅ captcha session

### 10.2 Fitur yang Tidak Ada

**1. Suspended Status**
- ❌ Status `suspended` defined di migration tapi TIDAK digunakan dalam code
- ❌ Tidak ada transisi ke suspended
- ❌ Tidak ada transisi dari suspended

**2. Background Queue Processing**
- ❌ Tidak ada Laravel Queue untuk queue processing
- ❌ Tidak ada cron job untuk auto-assign
- ❌ Queue processing hanya trigger saat staff complete/suspend

**3. Reverb Configuration**
- ❌ Tidak ditemukan konfigurasi Reverb spesifik
- ❌ Menggunakan Laravel Broadcasting default

**4. Service Layer**
- ❌ Tidak ada service layer untuk ticket logic
- ❌ Semua logic di controller
- ❌ Log assignment langsung di controller

**5. Listener untuk Events**
- ❌ Tidak ada listener untuk MessageSent
- ❌ Tidak ada listener untuk TicketClosed
- ❌ Tidak ada listener untuk StaffConnected
- ❌ Events hanya untuk broadcast, tidak ada side effects

**6. Middleware untuk Rate Limiting**
- ❌ Rate limiting manual di controller
- ❌ Tidak menggunakan Laravel Rate Limiter middleware
- ❌ Tidak ada throttle middleware configuration

**7. API Authentication untuk Guest**
- ❌ Guest akses tanpa authentication
- ❌ Hanya session-based authorization
- ❌ Tidak ada API token untuk guest

**8. Read Status untuk Messages**
- ❌ Field is_read ada tapi TIDAK digunakan
- ❌ Tidak ada logic untuk mark as read
- ❌ Tidak ada unread count

**9. Priority-based Queue**
- ❌ Queue selalu FIFO (oldest first)
- ❌ Tidak ada priority-based queue processing
- ❌ Priority field ada tapi tidak mempengaruhi queue order

**10. Staff Availability Schedule**
- ❌ Tidak ada schedule/offline hours
- ❌ Staff selalu available jika is_busy = false
- ❌ Tidak ada time-based assignment

### 10.3 Perbedaan Antara Implementasi dan Dokumentasi

**Catatan:** Tidak ada file dokumentasi resmi yang ditemukan dalam codebase untuk membandingkan. Berdasarkan file markdown yang ada:

**1. TICKETING_SYSTEM_ANALYSIS.md**
- File ini adalah analisis sistem yang sudah ada
- Berisi informasi yang konsisten dengan implementasi aktual
- Tidak ada perbedaan signifikan

**2. DOKUMENTASI_SISTEM_TICKETING_DAN_CHATBOT.md**
- Dokumentasi umum sistem
- Konsisten dengan implementasi

**3. File Audit Lainnya**
- Berbagai file audit dan analisis
- Semua konsisten dengan source code

**Kesimpulan:** Implementasi aktual konsisten dengan dokumentasi yang ada. Tidak ada perbedaan signifikan antara dokumentasi dan implementasi.

### 10.4 Potensi Masalah dan Improvement

**1. Race Condition di Assignment**
- ✅ Sudah menggunakan lockForUpdate
- ⚠️ Tapi tidak ada retry mechanism jika lock gagal

**2. No Dead Letter Queue**
- ❌ Tiket yang gagal di-assign akan stuck di waiting
- ❌ Tidak ada alert untuk tiket waiting lama

**3. Manual Session Management**
- ⚠️ Session-based authorization untuk guest
- ⚠️ Bisa bermasalah jika session cleared

**4. No Validation untuk sender_type**
- ❌ MessageController menerima sender_type dari request
- ❌ Bisa dimanipulasi oleh client

**5. Hardcoded Timeouts**
- ⚠️ 20 menit auto-close hardcoded
- ⚠️ 15 menit OTP expiry hardcoded
- ⚠️ 1 menit rate limit hardcoded

**6. No Pagination untuk Logs**
- ❌ getLogs mengambil semua logs tanpa pagination
- ❌ Bisa performance issue untuk tiket dengan banyak logs

**7. Suspended Status Tidak Digunakan**
- ❌ Status suspended defined tapi tidak digunakan
- ❌ Sebaiknya dihapus atau diimplementasikan

**8. No Retry untuk Email**
- ❌ Email gagal hanya log error
- ❌ Tidak ada retry mechanism
- ❌ Tidak ada queue untuk email

**9. Chatbot Ticket Creation Tanpa Assignment**
- ❌ createTicketAndMessage tidak auto-assign
- ❌ Tiket langsung open tanpa assignment
- ❌ Berbeda dengan flow normal

**10. No Validation untuk Category Assignment**
- ❌ Tidak validasi apakah category punya staff
- ❌ Bisa create ticket untuk category tanpa staff

---

## KESIMPULAN

Sistem ticketing HelpDesk TA memiliki implementasi yang cukup komprehensif dengan fitur-fitur utama:

1. **Pembuatan Tiket:** Mendukung direct form dan OTP verification dengan rate limiting dan captcha
2. **Assignment Staff:** Load balancing berdasarkan workload dengan filter kategori dan busy status
3. **Status Workflow:** 6 status dengan transisi yang terdefinisi dengan baik
4. **Live Chat:** Real-time messaging via Laravel Broadcasting dengan 3 event types
5. **Queue Processing:** FIFO-based dengan auto-assignment setelah staff selesai
6. **Auto-Close:** Mechanism auto-close untuk tiket yang tidak terlayani dalam 20 menit
7. **Ticket Log:** Comprehensive logging untuk semua aktivitas
8. **Database:** Well-structured dengan proper foreign keys dan relations

**Area yang Perlu Improvement:**
- Implementasi suspended status atau hapus dari migration
- Tambahkan background queue processing
- Implementasi read status untuk messages
- Tambahkan dead letter queue untuk tiket stuck
- Konfigurasi timeout yang dinamis (bukan hardcoded)
- Validasi sender_type di message creation
- Pagination untuk logs
- Retry mechanism untuk email

**Secara Keseluruhan:** Sistem sudah berfungsi dengan baik untuk use case helpdesk standar. Implementasi konsisten dengan dokumentasi yang ada.
