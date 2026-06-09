# Dokumentasi Sistem Ticketing dan Chatbot HelpDesk TA

Dokumentasi ini menjelaskan proses lengkap sistem ticketing dan chatbot beserta kode-kode yang terlibat.

---

# BAGIAN 1: SISTEM TICKETING

## 1.1 Overview Sistem Ticketing

Sistem ticketing HelpDesk TA memungkinkan pengguna (guest/staff/admin) untuk:
- Membuat tiket bantuan melalui form
- Melacak status tiket
- Staff menerima dan memproses tiket
- Auto-assignment tiket ke staff yang tersedia

## 1.2 Alur Proses Ticketing

### Langkah 1: Pengguna Mengisi Form Tiket

**File:** `resources/views/guest/help.blade.php`

Pengguna mengakses halaman bantuan melalui route `/help` dan mengisi form dengan:
- Nama
- Email
- Subjek
- Pesan
- Kategori
- Captcha (untuk non-JSON request)

**Route:** `routes/web.php` line 123
```php
Route::get('/help', [TicketController::class, 'create'])->name('guest.help');
```

**Controller:** `app/Http/Controllers/TicketController.php` line 64-73
```php
public function create()
{
    $categories = Category::all();
    $liveServiceEnabled = Setting::bool('live_service_enabled', true);

    // Generate simple captcha
    $captcha = rand(1000, 9999);
    session(['captcha' => $captcha]);
    return view('guest.help', compact('categories', 'captcha', 'liveServiceEnabled'));
}
```

### Langkah 2: Submit Form Tiket

**Route:** `routes/web.php` line 127
```php
Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
```

**Controller:** `app/Http/Controllers/TicketController.php` line 100-203

Proses yang terjadi:
1. **Validasi Input** (line 102-116)
   - Validasi field: name, email, subject, message, category_id
   - Captcha hanya untuk non-JSON request

2. **Anti-Spam Check** (line 118-145)
   - Cek rate limit berdasarkan IP (1 menit)
   - Cek rate limit berdasarkan email (1 menit)
   - Validasi captcha

3. **Buat Tiket dalam Transaksi** (line 147-176)
   ```php
   $ticket = DB::transaction(function () use ($request) {
       $ticket = Ticket::create([
           'name' => $request->name,
           'email' => $request->email,
           'subject' => $request->subject,
           'message' => $request->message,
           'category_id' => $request->category_id,
           'status' => 'open',
       ]);

       TicketLog::create([
           'ticket_id' => $ticket->id,
           'action' => 'created',
           'description' => 'Tiket dibuat oleh user',
       ]);

       $staffProfile = $this->assignTicketToAvailableStaff($ticket);

       if (!$staffProfile) {
           $ticket->update(['status' => 'waiting']);
           TicketLog::create([
               'ticket_id' => $ticket->id,
               'action' => 'waiting',
               'description' => 'Belum ada staff tersedia',
           ]);
       }

       return $ticket;
   });
   ```

### Langkah 3: Auto-Assignment Tiket ke Staff

**Controller:** `app/Http/Controllers/TicketController.php` line 573-632

**Method:** `assignTicketToAvailableStaff()`

Alur assignment:
1. **Query Staff yang Tersedia** (line 576-580)
   ```php
   $staffProfiles = StaffProfile::where('category_id', $ticket->category_id)
       ->where('is_busy', false)
       ->with('user')
       ->lockForUpdate()
       ->get();
   ```

2. **Hitung Beban Kerja Staff** (line 586-596)
   - Hitung active tickets (status: assigned, progress)
   - Hitung waiting reports (status: waiting)
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
   ```

3. **Sort Staff Berdasarkan Beban Kerja Terendah** (line 598-606)
   - Prioritas 1: Active tickets paling sedikit
   - Prioritas 2: Waiting reports paling sedikit
   - Prioritas 3: ID staff terkecil (tie-breaker)

4. **Assign Tiket ke Staff Terbaik** (line 614-628)
   ```php
   $ticket->update([
       'staff_id' => $bestStaff->user_id,
       'status' => 'assigned',
       'assigned_at' => now(),
   ]);

   $bestStaff->update(['is_busy' => true]);

   TicketLog::create([
       'ticket_id' => $ticket->id,
       'action' => 'assigned',
       'description' => 'Tiket di-assign ke staff: ' . $bestStaff->user->name,
   ]);
   ```

**Model StaffProfile:** `app/Models/StaffProfile.php`
- Menyimpan profil staff dengan field: user_id, category_id, is_busy
- Relasi: belongsTo(User), belongsTo(Category)

### Langkah 4: Staff Melihat Daftar Tiket

**Route:** `routes/web.php` line 81-82
```php
Route::get('/staff/tickets', [StaffTicketController::class, 'index'])
    ->name('staff.tickets.index');
```

**Controller:** `app/Http/Controllers/Staff/TicketController.php` line 66-110

Proses:
1. Query tiket yang ditugaskan ke staff yang login
2. Filter berdasarkan priority jika ada
3. Pisahkan tiket berdasarkan status:
   - **Active Ticket**: status 'assigned' atau 'progress'
   - **Completed Tickets**: status 'closed'
   - **Waiting Tickets**: status 'waiting'

```php
public function index(Request $request): View
{
    $user = auth()->user();

    // Get tiket yang ditugaskan ke staff ini
    $ticketsQuery = Ticket::where('staff_id', $user->id)
        ->with(['category', 'user']);

    // Filter by priority if provided
    if ($request->has('priority') && $request->priority) {
        $ticketsQuery->where('priority', $request->priority);
    }

    $tickets = $ticketsQuery->latest()->get();

    // Pisahkan berdasarkan status
    $activeTicket = Ticket::where('staff_id', $user->id)
        ->whereIn('status', ['assigned', 'progress'])
        ->with(['category', 'user'])
        ->first();

    $completedTickets = Ticket::where('staff_id', $user->id)
        ->where('status', 'closed')
        ->with(['category', 'user'])
        ->latest()
        ->get();

    $waitingTickets = Ticket::where('staff_id', $user->id)
        ->where('status', 'waiting')
        ->with(['category', 'user'])
        ->oldest()
        ->get();

    return view('staff.tickets.index', compact('user', 'tickets', 'activeTicket', 'completedTickets', 'waitingTickets'));
}
```

### Langkah 5: Staff Memulai Mengerjakan Tiket

**Route:** `routes/web.php` line 87-88
```php
Route::patch('/staff/tickets/{ticket}/start-progress', [StaffTicketController::class, 'startProgress'])
    ->name('staff.tickets.start-progress');
```

**Controller:** `app/Http/Controllers/Staff/TicketController.php` line 214-257

Proses:
1. Jika tiket waiting dan belum di-assign, assign ke staff yang meng-claim
2. Update status staff menjadi busy
3. Catat log bahwa tiket di-claim
4. Broadcast event StaffConnected

```php
public function startProgress(Ticket $ticket): RedirectResponse
{
    $user = auth()->user();

    if ($ticket->status === 'waiting' && $ticket->staff_id === null) {
        $ticket->update([
            'staff_id' => $user->id,
            'status' => 'progress',
            'assigned_at' => now(),
        ]);

        StaffProfile::where('user_id', $user->id)->update([
            'is_busy' => true,
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'action' => 'claimed',
            'description' => 'Tiket di-claim dan dimulai oleh staff: ' . $user->name,
        ]);

        broadcast(new \App\Events\StaffConnected($ticket, $user))->toOthers();

        return back()->with('success', 'Tiket berhasil di-claim dan mulai dikerjakan');
    }

    // ... lanjutan untuk tiket yang sudah di-assign
}
```

### Langkah 6: Staff Menyelesaikan Tiket

**Route:** `routes/web.php` line 91-92
```php
Route::patch('/staff/tickets/{ticket}/complete', [StaffTicketController::class, 'complete'])
    ->name('staff.tickets.complete');
```

**Controller:** `app/Http/Controllers/Staff/TicketController.php` line 356-442

Proses:
1. Validasi akses (tiket milik staff)
2. Validasi status tiket (progress atau waiting)
3. Update tiket menjadi closed
4. Update status staff menjadi tidak busy
5. Catat log penyelesaian
6. **Auto-assign tiket waiting berikutnya** di kategori yang sama

```php
public function complete(Request $request, Ticket $ticket): RedirectResponse
{
    // Validasi akses
    if ($ticket->staff_id !== auth()->id()) {
        abort(403, 'Anda tidak memiliki akses');
    }

    // Update ticket
    $ticket->update([
        'status' => 'closed',
        'priority' => $request->priority,
        'closed_at' => now(),
    ]);

    // Update staff status jadi tidak sibuk
    StaffProfile::where('user_id', auth()->id())->update([
        'is_busy' => false,
    ]);

    // Log completion
    TicketLog::create([
        'ticket_id' => $ticket->id,
        'action' => 'closed',
        'description' => 'Tiket diselesaikan oleh staff: ' . auth()->user()->name,
    ]);

    // ✨ Cari tiket waiting dengan kategori yang sama untuk di-assign
    $staffProfile = StaffProfile::where('user_id', auth()->id())->first();
    
    if ($staffProfile) {
        $nextTicket = Ticket::where('category_id', $staffProfile->category_id)
            ->where('status', 'waiting')
            ->whereNull('staff_id')
            ->oldest()
            ->first();

        if ($nextTicket) {
            // Cari staff paling available di kategori yang sama
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
                // Assign ke staff yang paling available
                $nextTicket->update([
                    'staff_id' => $availableStaff->user_id,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                ]);

                $availableStaff->update(['is_busy' => true]);

                TicketLog::create([
                    'ticket_id' => $nextTicket->id,
                    'action' => 'assigned',
                    'description' => 'Tiket di-assign ke staff: ' . $availableStaff->user->name,
                ]);
            }
        }
    }

    broadcast(new \App\Events\TicketClosed($ticket));

    return redirect()->route('staff.tickets.index')->with('success', 'Tiket berhasil ditandai selesai!');
}
```

### Langkah 7: Staff Menolak Tiket

**Route:** `routes/web.php` line 89-90
```php
Route::patch('/staff/tickets/{ticket}/reject', [StaffTicketController::class, 'reject'])
    ->name('staff.tickets.reject');
```

**Controller:** `app/Http/Controllers/Staff/TicketController.php` line 284-326

Proses:
1. Validasi akses
2. Validasi status (hanya assigned atau waiting)
3. Update tiket menjadi closed
4. Update staff menjadi tidak busy
5. Catat log penolakan
6. Kirim email penolakan ke guest
7. Broadcast event TicketClosed

### Langkah 8: Staff Menangguhkan Tiket

**Route:** `routes/web.php` line 93-94
```php
Route::patch('/staff/tickets/{ticket}/suspend', [StaffTicketController::class, 'suspend'])
    ->name('staff.tickets.suspend');
```

**Controller:** `app/Http/Controllers/Staff/TicketController.php` line 471-546

Proses:
1. Validasi akses
2. Cek tiket belum closed
3. Update tiket menjadi waiting
4. Update staff menjadi tidak busy
5. Catat log penangguhan
6. Auto-assign tiket waiting berikutnya

## 1.3 Model-Model Terkait Ticketing

### Model Ticket
**File:** `app/Models/Ticket.php`

Field:
- id, name, email, subject, message
- category_id, user_id, staff_id
- status (open, assigned, progress, waiting, closed)
- priority (low, medium, high)
- assigned_at, closed_at, email_verified_at
- tracking_token

Relasi:
- belongsTo(Category)
- belongsTo(User, 'staff_id') - staff yang menangani
- belongsTo(User, 'user_id') - user yang membuat
- hasMany(Message)
- hasMany(TicketLog)

### Model TicketLog
**File:** `app/Models/TicketLog.php`

Field:
- ticket_id, action, description

Relasi:
- belongsTo(Ticket)

### Model Message
**File:** `app/Models/Message.php`

Field:
- ticket_id, sender_type, sender_id, message, is_read

Relasi:
- belongsTo(Ticket)
- belongsTo(User, 'sender_id')

### Model StaffProfile
**File:** `app/Models/StaffProfile.php`

Field:
- user_id, category_id, is_busy

Relasi:
- belongsTo(User)
- belongsTo(Category)

## 1.4 Fitur Tambahan Ticketing

### Pelacakan Tiket (Ticket Tracking)
**Route:** `routes/web.php` line 126
```php
Route::get('/tickets/track/{token}', [TicketController::class, 'track'])->name('tickets.track');
```

**Controller:** `app/Http/Controllers/TicketController.php` line 541-546

Pengguna dapat melacak status tiket menggunakan tracking_token yang dikirim via email.

### OTP Verification
**Route:** `routes/web.php` line 124-125
```php
Route::post('/tickets/request-otp', [TicketController::class, 'requestOtp'])->name('tickets.request-otp');
Route::post('/tickets/verify-otp', [TicketController::class, 'verifyOtp'])->name('tickets.verify-otp');
```

**Controller:** `app/Http/Controllers/TicketController.php` line 336-526

Untuk livechat dan report, sistem menggunakan OTP verification:
1. User request OTP dengan data tiket
2. OTP dikirim ke email user
3. User verifikasi OTP
4. Tiket dibuat setelah verifikasi berhasil

### Auto-Close Tiket
**Route:** `routes/api.php` line 29-71

Sistem otomatis menutup tiket jika:
- Tiket assigned tapi staff tidak merespons dalam 20 menit
- Tiket open/waiting tanpa staff tersedia dalam 20 menit

---

# BAGIAN 2: SISTEM CHATBOT

## 2.1 Overview Sistem Chatbot

Sistem chatbot HelpDesk TA menggunakan pendekatan retrieval-based dengan:
- **Typesense** (85% bobot) - pencarian full-teks cepat dengan fuzzy matching
- **TF-IDF** (15% bobot) - reranking berbasis relevansi semantik ringan
- **Advanced Retrieval** - multi-faktor scoring dengan domain detection

## 2.2 Arsitektur Chatbot

### Komponen Utama
1. **ChatbotController** - Controller utama yang menangani request chatbot
2. **AdvancedRetrievalService** - Layanan retrieval artikel dengan multi-faktor scoring
3. **ChatbotRetrievalService** - Layanan retrieval fallback (Typesense + TF-IDF)
4. **ConversationFlowService** - Mengelola alur percakapan dan greeting
5. **Support Services**:
   - PreprocessingService - preprocessing teks (typo correction, tokenization)
   - TfidfService - perhitungan TF-IDF
   - CosineSimilarityService - perhitungan cosine similarity
   - DomainDetectionService - deteksi domain/kategori
   - TypesenseService - integrasi dengan Typesense search engine

## 2.3 Alur Proses Chatbot

### Langkah 1: Pengguna Mengirim Pesan ke Chatbot

**Route:** `routes/web.php` line 151
```php
Route::post('/chatbot/get-response', [ChatbotController::class, 'getResponse'])->name('chatbot.get-response');
```

**Controller:** `app/Http/Controllers/ChatbotController.php` line 71-157

### Langkah 2: Controller Memproses Request

**Method:** `getResponse()`

Proses:
1. **Validasi Input** (line 73-83)
   ```php
   $request->validate([
       'message' => 'required|string|max:1000',
   ]);

   $userMessage = trim($request->input('message'));

   // Validate minimum length
   if (mb_strlen($userMessage) < 3) {
       return $this->errorResponse('Pertanyaan terlalu pendek. Silakan jelaskan masalah Anda lebih detail.');
   }
   ```

2. **Handle Greeting** (line 91-102)
   ```php
   if ($this->retrievalService->isGreeting($userMessage)) {
       $this->retrievalService->clearConversationMemory();
       
       return response()->json([
           'success'  => true,
           'response' => $this->retrievalService->getGreetingResponse(),
           'articles' => [],
           'categories' => $this->retrievalService->getCuratedCategories(),
       ]);
   }
   ```

3. **Check Clarification Needs** (line 104-115)
   ```php
   if ($this->retrievalService->needsClarification($userMessage)) {
       $clarification = $this->retrievalService->getClarificationResponse($userMessage);
       
       $this->retrievalService->storeConversationContext([
           'type' => 'clarification_requested',
           'query' => $userMessage,
       ]);
       
       return response()->json($clarification);
   }
   ```

4. **Perform Retrieval** (line 117-129)
   ```php
   $result = $this->retrievalService->retrieve($userMessage, 5);
   
   $this->retrievalService->storeConversationContext([
       'type' => 'retrieval',
       'query' => $userMessage,
       'found_results' => !empty($result['results']),
       'result_count' => count($result['results'] ?? []),
   ]);

   $response = $this->retrievalService->formatResponse($result);
   ```

5. **Format Response** (line 131-146)
   - Tambahkan informasi diversifikasi kategori
   - Tambahkan informasi multi-intent jika terdeteksi

### Langkah 3: AdvancedRetrievalService Melakukan Retrieval

**File:** `app/Services/Chatbot/AdvancedRetrievalService.php`

**Method:** `retrieve()`

Proses retrieval multi-fase:

#### Fase 1: Preprocessing Query
- Normalisasi query (typo correction)
- Deteksi domain/kategori
- Ekspansi query dengan sinonim

#### Fase 2: Typesense Retrieval (85% Bobot)
- Kirim query ke Typesense
- Dapatkan kandidat artikel dengan skor Typesense
- Gunakan fuzzy matching untuk typo tolerance

#### Fase 3: TF-IDF Reranking (15% Bobot)
- Hitung TF-IDF untuk query dan dokumen kandidat
- Hitung cosine similarity
- Terapkan boosting untuk judul yang cocok

#### Fase 4: Hybrid Scoring
Kombinasi skor dengan bobot:
```php
private const WEIGHT_COSINE = 0.30;        // Cosine similarity TF-IDF
private const WEIGHT_TITLE_OVERLAP = 0.25; // Overlap kata kunci judul
private const WEIGHT_DOMAIN_MATCH = 0.15;  // Keselarasan domain/kategori
private const WEIGHT_QUERY_COVERAGE = 0.15; // Cakupan term query
private const WEIGHT_EXACT_PHRASE = 0.10;  // Pencocokan frasa exact
private const WEIGHT_DIVERSIFICATION = 0.05; // Diversifikasi hasil
```

#### Fase 5: Domain Filtering
- Deteksi domain dari query (wifi, printer, email, dll)
- Beri penalti untuk artikel dari domain yang tidak terkait
- Beri boost untuk artikel dari domain yang sesuai

#### Fase 6: Result Formatting
- Filter hasil di bawah threshold (0.12)
- Ambil top-5 hasil
- Tambahkan confidence level (high/medium/low)

### Langkah 4: ConversationFlowService Mengelola Alur Percakapan

**File:** `app/Services/Chatbot/ConversationFlowService.php`

#### Greeting Data
**Method:** `getGreetingData()` (line 181-200)

Menyiapkan data untuk pesan greeting awal:
```php
public function getGreetingData(): array
{
    // Ambil 5 kategori acak
    $categories = Category::inRandomOrder()
        ->limit(5)
        ->get(['id', 'name', 'description']);

    // Ambil artikel populer untuk setiap kategori
    $categoryArticles = [];
    foreach ($categories as $category) {
        $articles = Article::where('category_id', $category->id)
            ->where('is_published', true)
            ->orderBy('views', 'desc')
            ->limit(3)
            ->get(['id', 'title', 'slug']);

        $categoryArticles[$category->id] = $articles;
    }

    return [
        'greeting' => 'Halo! Saya SiMinfo, asisten virtual HelpDesk TA. Ada yang bisa saya bantu?',
        'categories' => $categories,
        'category_articles' => $categoryArticles,
    ];
}
```

#### Ambiguity Detection
**Method:** `checkAmbiguity()` (line 200+)

Mendeteksi query yang ambigu (misal: hanya "lemot" tanpa konteks):
```php
private array $ambiguousPatterns = [
    'lemot', 'lambat', 'error', 'eror',
    'tidak bisa', 'gak bisa', 'ga bisa',
    'bermasalah', 'masalah', 'rusak', 'mati',
    // ...
];

private array $domainTerms = [
    'wifi', 'internet', 'printer', 'komputer',
    'email', 'jaringan', 'router', 'modem',
    // ...
];
```

Jika query hanya mengandung issue term tanpa domain term, sistem meminta klarifikasi.

### Langkah 5: Response Formatting

**Method:** `formatResponse()` di AdvancedRetrievalService

Format response yang dikembalikan ke frontend:
```php
return [
    'success' => true,
    'response' => 'Text response chatbot',
    'articles' => [
        [
            'id' => 1,
            'title' => 'Judul Artikel',
            'excerpt' => 'Ringkasan artikel',
            'similarity' => 0.85,
            'confidence' => 'high',
            'url' => 'https://...',
            'category_name' => 'WiFi',
        ],
        // ...
    ],
    'confidence' => 'high',
    'should_escalate' => false,
    'diversity' => [
        'categories' => 2,
        'is_diverse' => true,
    ],
];
```

## 2.4 Fitur Chatbot Tambahan

### Pencarian Chatbot
**Route:** `routes/web.php` line 152
```php
Route::post('/chatbot/search', [ChatbotController::class, 'chatbotSearch'])->name('chatbot.search');
```

Endpoint untuk pencarian manual artikel dengan advanced TF-IDF.

### Pembuatan Tiket dari Chatbot
**Route:** `routes/web.php` line 154
```php
Route::post('/chatbot/create-ticket', [ChatbotController::class, 'createTicketAndMessage'])->name('chatbot.create-ticket');
```

**Controller:** `app/Http/Controllers/ChatbotController.php` line 425-467

Membuat tiket baru dari chatbot:
```php
public function createTicketAndMessage(Request $request): JsonResponse
{
    $request->validate([
        'title'       => 'required|string|max:255',
        'message'     => 'required|string|max:2000',
        'category_id' => 'required|exists:categories,id',
        'email'       => 'nullable|email|max:255',
    ]);

    $ticket = Ticket::create([
        'user_id'     => auth()->id(),
        'name'        => auth()->user()?->name ?? 'Guest User',
        'email'       => $request->email,
        'subject'     => $request->title,
        'message'     => $request->message,
        'category_id' => $request->category_id,
        'priority'    => 'low',
        'status'      => 'open',
    ]);

    Message::insert([
        [
            'ticket_id'   => $ticket->id,
            'sender_type' => 'guest',
            'message'     => $request->message,
            'created_at'  => now(),
            'updated_at'  => now(),
        ],
        [
            'ticket_id'   => $ticket->id,
            'sender_type' => 'bot',
            'message'     => 'Terima kasih telah menghubungi kami. Tim support kami akan segera merespons tiket Anda.',
            'created_at'  => now(),
            'updated_at'  => now(),
        ],
    ]);

    return response()->json([
        'success'   => true,
        'ticket_id' => $ticket->id,
        'message'   => 'Tiket berhasil dibuat. Tim kami akan segera menghubungi Anda.',
    ]);
}
```

### Pesan dalam Tiket
**Route:** `routes/web.php` line 155-156
```php
Route::post('/chatbot/send-message', [ChatbotController::class, 'sendMessage'])->name('chatbot.send-message');
Route::get('/chatbot/ticket/{ticket}/messages', [ChatbotController::class, 'getTicketMessages'])->name('chatbot.messages');
```

Mengirim dan mengambil pesan dalam tiket chatbot.

### Greeting dan Topik
**Route:** `routes/web.php` line 159-162
```php
Route::get('/chatbot/greeting', [ChatbotController::class, 'getGreeting'])->name('chatbot.greeting');
Route::post('/chatbot/category-subtopics', [ChatbotController::class, 'getCategorySubtopics'])->name('chatbot.category-subtopics');
Route::post('/chatbot/check-ambiguity', [ChatbotController::class, 'checkAmbiguity'])->name('chatbot.check-ambiguity');
Route::get('/chatbot/search-suggestions', [ChatbotController::class, 'getSearchSuggestions'])->name('chatbot.search-suggestions');
```

Endpoint untuk fitur interaktif chatbot:
- Greeting dengan kategori chips
- Subtopics berdasarkan kategori
- Deteksi ambigu
- Saran pencarian

### Cache Management
**Route:** `routes/web.php` line 170-173
```php
Route::middleware(['auth', 'admin'])->prefix('admin/chatbot')->name('admin.chatbot.')->group(function () {
    Route::post('/rebuild-cache', [ChatbotController::class, 'rebuildCache'])->name('rebuild-cache');
    Route::post('/clear-cache', [ChatbotController::class, 'clearCache'])->name('clear-cache');
});
```

Admin dapat rebuild dan clear cache chatbot untuk mengoptimasi performa.

## 2.5 Model-Model Terkait Chatbot

### Model Article
Digunakan untuk retrieval artikel chatbot.

### Model Category
Digunakan untuk domain detection dan filtering.

## 2.6 Services Chatbot

### PreprocessingService
- Typo correction menggunakan dictionary
- Tokenization teks
- Normalisasi teks

### TfidfService
- Perhitungan Term Frequency (TF)
- Perhitungan Inverse Document Frequency (IDF)
- Perhitungan TF-IDF score

### CosineSimilarityService
- Perhitungan cosine similarity antara vektor query dan dokumen

### DomainDetectionService
- Deteksi domain/kategori dari query
- Mapping query ke kategori
- Filter artikel berdasarkan domain

### TypesenseService
- Integrasi dengan Typesense search engine
- Pencarian full-teks dengan fuzzy matching
- Hybrid search dengan typo tolerance

### ImportantPhraseService
- Deteksi frasa penting dalam query
- Boosting untuk frasa exact match

### VocabularyService
- Manajemen vocabulary untuk TF-IDF
- Cache vocabulary untuk performa

---

# RINGKASAN FILE UTAMA

## Ticketing System
- **Controller**: `app/Http/Controllers/TicketController.php`, `app/Http/Controllers/Staff/TicketController.php`
- **Model**: `app/Models/Ticket.php`, `app/Models/TicketLog.php`, `app/Models/Message.php`, `app/Models/StaffProfile.php`
- **Routes**: `routes/web.php` (lines 123-128), `routes/api.php` (lines 22-156)
- **Views**: `resources/views/guest/help.blade.php`, `resources/views/staff/tickets/*.blade.php`

## Chatbot System
- **Controller**: `app/Http/Controllers/ChatbotController.php`
- **Services**: `app/Services/Chatbot/*.php`
  - `AdvancedRetrievalService.php` - retrieval multi-faktor
  - `ChatbotRetrievalService.php` - retrieval fallback
  - `ConversationFlowService.php` - alur percakapan
  - `PreprocessingService.php` - preprocessing teks
  - `TfidfService.php` - perhitungan TF-IDF
  - `CosineSimilarityService.php` - cosine similarity
  - `DomainDetectionService.php` - deteksi domain
  - `TypesenseService.php` - integrasi Typesense
- **Routes**: `routes/web.php` (lines 150-173)
- **Views**: `resources/views/components/chatbot-widget.blade.php`

---

# KESIMPULAN

Sistem HelpDesk TA memiliki dua komponen utama:

1. **Sistem Ticketing** - Mengelola tiket bantuan dari pembuatan hingga penyelesaian dengan auto-assignment ke staff yang tersedia berdasarkan load balancing.

2. **Sistem Chatbot** - Memberikan respon otomatis menggunakan retrieval-based approach dengan kombinasi Typesense (85%) dan TF-IDF (15%) untuk memberikan artikel yang relevan berdasarkan query pengguna.

Kedua sistem terintegrasi sehingga pengguna dapat:
- Mendapatkan jawaban instan dari chatbot
- Membuat tiket jika chatbot tidak dapat membantu
- Melacak status tiket yang dibuat
- Staff memproses tiket dengan efisien

Dokumentasi ini dibuat pada tanggal: 2026-06-09
