# Dokumentasi Arsitektur Sistem — Persiapan Sidang Skripsi

Dokumentasi ini merangkum struktur sistem, alur utama (tiket, chatbot, knowledge base, login/otorisasi), serta relasi antar service, controller, dan model. Setiap modul berisi daftar file terkait, fungsi tiap file, input/output, dan hubungan antar modul.

**Petunjuk penggunaan:** gunakan dokumen ini sebagai ringkasan cepat untuk presentasi sidang; setiap bagian menyertakan referensi file di repository.

**Struktur Dokumen**
- **Struktur Sistem**
- **Alur Tiket**
- **Alur Chatbot**
- **Alur Knowledge Base**
- **Alur Login dan Otorisasi**
- **Relasi Antar Service**
- **Relasi Antar Controller**
- **Relasi Antar Model**

**Struktur Sistem**
- **Framework & pola**: Aplikasi dibangun dengan Laravel (MVC). Folder utama: [app](app) (Models, Http/Controllers, Services, Mail, Observers), [routes](routes) untuk rute, [resources/views] untuk tampilan.
- **Modul utama**: Ticketing, Chatbot (retrieval & conversation), Knowledge Base (Article / Category), Authentication/Authorization, Messaging.
- **Service layer**: berada di [app/Services](app/Services) — khususnya subfolder Chatbot untuk komponen retrieval.

**Alur Tiket**
- File terlibat:
  - [app/Http/Controllers/TicketController.php](app/Http/Controllers/TicketController.php)
  - [app/Models/Ticket.php](app/Models/Ticket.php)
  - [app/Models/TicketLog.php](app/Models/TicketLog.php)
  - [app/Models/TicketOtp.php](app/Models/TicketOtp.php)
  - Mailers: [app/Mail/TicketTrackingMail.php](app/Mail/TicketTrackingMail.php), [app/Mail/TicketRejectionMail.php](app/Mail/TicketRejectionMail.php)

- Fungsi ringkas tiap file:
  - `TicketController`: menerima request pembuatan dan update tiket, validasi input, memanggil model/service untuk persist, mengarahkan user.
  - `Ticket`: model Eloquent tiket — atribut (user_id, subject, content, status, priority, dll.), relasi ke `TicketLog` dan `User`.
  - `TicketLog`: menyimpan histori perubahan status atau komentar staff.
  - `TicketOtp`: bila sistem memakai OTP untuk verifikasi pembuatan tiket.
  - Mail classes: mengirim pemberitahuan terkait tiket.

- Input: HTTP request (form atau API) berisi user identity, subjek, deskripsi masalah, lampiran.
- Output: Pembuatan record Ticket, notifikasi email, respon HTTP (sukses/gagal).
- Hubungan: Ticket terkait ke `User` (pemilik), ke `StaffProfile` untuk penugasan; ticket flow dapat dipicu dari Chatbot (aksi `create_ticket`) — lihat [app/Http/Controllers/ChatbotController.php](app/Http/Controllers/ChatbotController.php).

**Alur Chatbot**
- File terlibat (service & controller):
  - [app/Http/Controllers/ChatbotController.php](app/Http/Controllers/ChatbotController.php)
  - [app/Services/Chatbot/AdvancedRetrievalService.php](app/Services/Chatbot/AdvancedRetrievalService.php)
  - [app/Services/Chatbot/ChatbotRetrievalService.php](app/Services/Chatbot/ChatbotRetrievalService.php)
  - [app/Services/Chatbot/PreprocessingService.php](app/Services/Chatbot/PreprocessingService.php)
  - [app/Services/Chatbot/TfidfService.php](app/Services/Chatbot/TfidfService.php)
  - [app/Services/Chatbot/CosineSimilarityService.php](app/Services/Chatbot/CosineSimilarityService.php)
  - [app/Services/Chatbot/DomainDetectionService.php](app/Services/Chatbot/DomainDetectionService.php)
  - [app/Services/Chatbot/VocabularyService.php](app/Services/Chatbot/VocabularyService.php)
  - [app/Services/Chatbot/ImportantPhraseService.php](app/Services/Chatbot/ImportantPhraseService.php)
  - (Optional) [app/Models/Chatbot.php](app/Models/Chatbot.php) untuk penyimpanan konfigurasi atau log

- Fungsi ringkas:
  - `ChatbotController`: endpoint untuk menerima pesan user, memanggil service retrieval, mengatur sesi percakapan, merespon ke UI/API.
  - `AdvancedRetrievalService`: implementasi TF-IDF + reranking hybrid (cosine, title overlap, domain match, phrase boosting, diversifikasi) — menghasilkan daftar artikel terurut beserta confidence dan debug info.
  - `ChatbotRetrievalService`: (jika ada) orkestrasi lebih tinggi untuk memilih antara strategi retrieval (simple/hybrid)
  - `PreprocessingService`: tokenisasi, stopword removal, normalisasi.
  - `TfidfService`: menghitung TF, IDF, dan vektor TF-IDF.
  - `CosineSimilarityService`: menghitung kemiripan cosine antara query dan dokumen.
  - `DomainDetectionService`: mendeteksi domain (wifi, printer, email, dll.) dan out-of-domain rejection.
  - `VocabularyService`: koreksi typo dan normalisasi query.
  - `ImportantPhraseService`: deteksi phrase penting dan scoring tambahan.

- Input: teks query dari pengguna, konteks percakapan (opsional).
- Output: paket JSON berisi `success`, `response` (text assistant), `articles` (list hasil dengan id, title, excerpt, confidence), flags (escalate, safe_fallback).
- Hubungan: Chatbot memanfaatkan `Article`/`Category` sebagai sumber knowledge base; dapat menghasilkan aksi tiket (panggil TicketController flow) atau live chat (message/ticket creation).

**Alur Knowledge Base (Article)**
- File terlibat:
  - [app/Models/Article.php](app/Models/Article.php)
  - [app/Models/Category.php](app/Models/Category.php)
  - [app/Http/Controllers/ArticleController.php](app/Http/Controllers/ArticleController.php)
  - Indexing / search integration: [app/Services/Chatbot/TypesenseService.php](app/Services/Chatbot/TypesenseService.php) (jika digunakan)
  - Artikel pendukung: [app/Models/ArticleKeywordIndex.php](app/Models/ArticleKeywordIndex.php), [app/Models/ArticleFeedback.php](app/Models/ArticleFeedback.php)

- Fungsi ringkas:
  - `Article`: model Eloquent untuk konten artikel (title, content, excerpt, keywords, is_published, publish_status, slug, category_id).
  - `Category`: kategori artikel; dipakai untuk domain detection dan filtering.
  - `ArticleKeywordIndex`: (opsional) tabel indeks kata untuk pencarian cepat.
  - `ArticleFeedback`: menyimpan feedback pengguna terhadap artikel.

- Input: pembuatan/ubah artikel lewat UI/admin (form data), import indexing dari pipeline.
- Output: artikel terpublikasi yang dapat di-query oleh Chatbot/Typesense.
- Hubungan: Articles digunakan langsung oleh `AdvancedRetrievalService` via Eloquent queries (`getDomainFilteredArticles`, `getPublishedArticles`). Indexing service (Typesense) membantu kandidat retrieval awal.

**Alur Login dan Otorisasi**
- File terlibat:
  - Controllers Auth: [app/Http/Controllers/Auth/AuthenticatedSessionController.php](app/Http/Controllers/Auth/AuthenticatedSessionController.php), [app/Http/Controllers/Auth/RegisteredUserController.php](app/Http/Controllers/Auth/RegisteredUserController.php), [app/Models/User.php](app/Models/User.php)
  - Middleware & Gates: periksa `app/Http/Middleware` (jika ada), `AuthServiceProvider` (policies)

- Fungsi ringkas:
  - `AuthenticatedSessionController`: meng-handle login/logout, session creation.
  - `RegisteredUserController`: registrasi user baru.
  - `User` model: atribut user, relasi ke `StaffProfile` untuk role staff.

- Input: kredensial (email/username + password), token reset, verifikasi email.
- Output: session cookie / JWT (tergantung konfigurasi), redirect ke dashboard, akses terotorisasi.
- Hubungan: Aksi sensitif (membuat/menugaskan tiket, mengelola artikel) membutuhkan otorisasi role (staff/admin).

**Relasi Antar Service**
- Daftar service utama (`app/Services/Chatbot`):
  - `PreprocessingService` → dipakai oleh `AdvancedRetrievalService` untuk tokenisasi dan ekstraksi token.
  - `TfidfService` → menghitung idf/tf, dipakai oleh `AdvancedRetrievalService`.
  - `CosineSimilarityService` → menghitung skor similarity.
  - `DomainDetectionService` → menentukan domain query, dipakai sangat awal di retrieval.
  - `VocabularyService` → normalisasi query & koreksi typo.
  - `ImportantPhraseService` → mendeteksi phrase penting dan memberikan phrase boost.
  - `TypesenseService` → (opsional) memberikan kandidat awal untuk reranking.

- Pola interaksi: `ChatbotController` → (orchestrator) memanggil `AdvancedRetrievalService` → service ini memanggil Preprocessing, TF-IDF, Similarity, DomainDetection, Vocabulary, PhraseService. Retrieval bisa menghasilkan side-effect: menyimpan conversation memory (`Session`), meningkatnya failure counters, atau eskalasi ke Ticket flow.

**Relasi Antar Controller**
- Hubungan kontrol (high-level):
  - `ChatbotController` berinteraksi dengan `TicketController` (aksi pembuatan tiket berdasarkan tombol eskalasi).
  - `ArticleController` menyediakan CRUD untuk knowledge base yang dipakai oleh `AdvancedRetrievalService`.
  - `Auth` controllers mengamankan akses ke `Staff` controllers (e.g., [app/Http/Controllers/Staff/TicketController.php](app/Http/Controllers/Staff/TicketController.php)).

**Relasi Antar Model**
- Model utama dan relasi umum:
  - `User` (1) — (N) `Ticket` (user membuat banyak tiket)
  - `Ticket` (1) — (N) `TicketLog`
  - `Article` (N) — (1) `Category`
  - `Article` (1) — (N) `ArticleFeedback`
  - `StaffProfile` terkait ke `User` untuk role/atribut staff

**Contoh per-modul: format detail (contoh: Chatbot - AdvancedRetrievalService)**
- File: [app/Services/Chatbot/AdvancedRetrievalService.php](app/Services/Chatbot/AdvancedRetrievalService.php)
  - Fungsi: kalkulasi hybrid retrieval (TF-IDF + reranking), handling out-of-domain, query expansion, diversification, fallback & eskalasi.
  - Input: `string $query`, `int $limit` (opsional), serta dependensi service (Preprocessing, TFIDF, CosineSimilarity, DomainDetection, Vocabulary, PhraseService).
  - Output: array berisi `results` (list artikel dengan `id,title,excerpt,final_score,confidence`), `debug` (opsional), flags (`should_escalate`, `is_out_of_domain`).
  - Hubungan: memanggil model `Article` untuk kandidat, bergantung pada `Category` untuk domain filtering; memicu `TicketController` via ChatbotController saat eskalasi.

**Saran bahan presentasi Sidang**
- Tampilkan diagram alir singkat untuk setiap flow (Chatbot retrieval → decision → articles / safe fallback / escalate)
- Sertakan contoh query dan trace debug dari `AdvancedRetrievalService::retrieve()` (aktifkan `app.debug` untuk menampilkan `debug` payload).
- Jelaskan metrik confidence thresholds (nilai konstanta di service) dan bagaimana threshold memicu fallback atau eskalasi.

--
Dokumentasi ini adalah ringkasan awal. Jika Anda setuju, saya bisa:
1) Mengembangkan diagram (Mermaid) untuk tiap alur.
2) Menyusun tabel lengkap file→fungsi→input/output untuk semua controller dan model.
3) Menambahkan contoh skenario percakapan chatbot lengkap dengan trace debug.

Sebutkan opsi yang Anda inginkan selanjutnya.

=================================================================
DETAIL DOKUMENTASI PER MODUL (UNTUK SIDANG SKRIPSI)
=================================================================

Catatan: Dokumen berikut fokus pada analisis arsitektur dan dokumentasi file. Tidak ada perubahan kode.

1) Modul: Chatbot
- Tujuan modul: Menyediakan antarmuka percakapan otomatis untuk membantu pengguna menemukan solusi dari knowledge base dan menentukan eskalasi (tiket/live chat) saat perlu.
- File yang terlibat (utama):
  - `app/Http/Controllers/ChatbotController.php` — menerima request chat, mengelola session, mengembalikan respon ke UI/API.
  - `app/Services/Chatbot/AdvancedRetrievalService.php` — core hybrid retrieval, reranking, fallback, eskalasi.
  - `app/Services/Chatbot/ChatbotRetrievalService.php` — orkestrator retrieval (jika ada).
  - `app/Services/Chatbot/PreprocessingService.php` — tokenisasi, stopword, normalisasi.
  - `app/Services/Chatbot/TfidfService.php` — hitung TF, IDF, TF-IDF vektor.
  - `app/Services/Chatbot/CosineSimilarityService.php` — hitung cosine similarity.
  - `app/Services/Chatbot/DomainDetectionService.php` — deteksi domain topik dan out-of-domain.
  - `app/Services/Chatbot/VocabularyService.php` — koreksi typo, sinonim, normalisasi query.
  - `app/Services/Chatbot/ImportantPhraseService.php` — deteksi phrase penting dan scoring.
  - `app/Services/Chatbot/ConversationFlowService.php` — (jika ada) atur state dialog & follow-ups.
  - `app/Models/Article.php`, `app/Models/Category.php`, `app/Models/ArticleFeedback.php` — sumber knowledge base.

- Input: teks query (string), metadata session (user id, language, last interactions), limit hasil.
- Output: paket JSON: `success`, `response` (text), `articles` (array of {id, title, excerpt, slug, category, final_score, confidence}), flags (`is_out_of_domain`, `is_safe_fallback`, `should_escalate`).
- Alur proses (ringkas):
  1. `ChatbotController` menerima request dari UI/API.
  2. Controller memvalidasi input dan memanggil `AdvancedRetrievalService::retrieve($query)`.
  3. `AdvancedRetrievalService` memanggil `DomainDetectionService` untuk out-of-domain.
  4. Jika di-domain: normalisasi (VocabularyService), preprocessing (PreprocessingService), perluasan query, ambil kandidat artikel dari DB atau Typesense.
  5. Bangun vektor TF-IDF via `TfidfService`, hitung cosine via `CosineSimilarityService`.
  6. Hitung faktor tambahan: title overlap, query coverage, exact phrase bonus, domain penalty, diversifikasi.
  7. Terapkan threshold -> jika lemah, gunakan safe fallback atau eskalasi.
  8. `ChatbotController` menerima hasil, format response dan kembalikan ke client.

- Relasi dengan modul lain: Chatbot bergantung penuh pada Knowledge Base (`Article`, `Category`) untuk sumber jawaban; dapat memicu Ticketing (pembuatan tiket) atau Message/live chat via `TicketController`/`MessageController`.

### Confidence Thresholds
- `SIMILARITY_THRESHOLD = 0.12`: skor minimum yang harus dipenuhi agar hasil dianggap relevan dan dapat ditampilkan.
- `SAFE_FALLBACK_THRESHOLD = 0.18`: jika skor teratas di bawah ambang ini, sistem memilih safe fallback alih-alih menampilkan hasil lemah.
- `HIGH_SIMILARITY_THRESHOLD = 0.35`: skor yang menandakan confidence tinggi; digunakan untuk penilaian kualitas hasil.
- `VERY_HIGH_SIMILARITY_THRESHOLD = 0.55`: skor yang menandakan confidence sangat tinggi.

### Hybrid Ranking Formula
- `Cosine Similarity = 30%`
- `Title Overlap = 25%`
- `Domain Match = 15%`
- `Query Coverage = 15%`
- `Exact Phrase Match = 10%`
- `Diversification = 5%`

Rumus final score:

`final_score = (cosine * 0.30) + (title_overlap * 0.25) + (domain_match * 0.15) + (query_coverage * 0.15) + (exact_phrase_bonus * 0.10) + (diversification * 0.05) + domain_penalty + security_boost + phrase_boost`

- `domain_penalty` dapat bernilai negatif jika artikel berasal dari domain yang salah.
- `phrase_boost` adalah bonus aditif ketika query berisi phrase penting yang cocok.

### Escalation Mechanism
- `FAILURE_THRESHOLD = 3`: jika query yang sama gagal lebih dari tiga kali, sistem menganggap perlu eskalasi.
- `shouldEscalate()` mengembalikan `true` ketika count kegagalan query di session mencapai ambang ini.
- `getEscalationResponse()` mengembalikan payload eskalasi berupa teks penjelasan dan tombol aksi `Live Chat`, `Buat Tiket`, `Coba Pertanyaan Lain`.

Alur sederhana:

`Query gagal` -> `Failure Memory` -> `shouldEscalate() == true` -> `getEscalationResponse()` -> `Ticket/Live Chat`

### Multi Intent Retrieval
- `detectMultiIntent()` memecah query panjang yang berisi konjungsi seperti `dan`, `atau`, `dengan`, `serta`, atau koma.
- `multiIntentRetrieval()` memanggil `singleIntentRetrieval()` untuk setiap intent yang terdeteksi, mengambil pool kandidat lebih besar, lalu menandai hasil menurut intent.
- `balancedMerge()` menggabungkan hasil dengan strategi round-robin dan kuota per intent, memastikan setiap intent mendapat representasi.

Contoh implementasi: query `"wifi lemot dan printer error"` didekomposisi menjadi intents `['wifi lemot', 'printer error']`, kemudian kedua hasil digabung secara seimbang.

### Domain Filtering
- `domainCategoryMap` adalah peta domain ke kategori artikel yang valid; misalnya `wifi` -> `['wifi','internet','jaringan']`.
- `forbiddenDomainMap` mendefinisikan domain yang tidak boleh muncul untuk query tertentu; misalnya `printer` tidak boleh berisi `wifi`, `email`, `vpn`, `security`.
- `negativeDomainPenalties` memberikan penalti ketika dokumen yang tidak relevan muncul dalam query domain tertentu; misalnya ketika query `wifi` dan artikel menyebut `printer` atau berasal dari kategori `printer`.

Penalti dijelaskan dalam implementasi:
- `STRONG_DOMAIN_PENALTY = -0.8` ketika dokumen menyebut kata domain negatif yang jelas atau kategori dokumen cocok dengan domain negatif.
- `DOMAIN_PENALTY = -0.5` ketika dokumen berada dalam kategori yang terlarang menurut `forbiddenDomainMap`.

### Query Expansion
- `queryExpansionDict` adalah kamus istilah domain yang menambahkan sinonim dan istilah terkait ke query, misalnya `wifi` ditambahkan dengan `internet`, `jaringan`, `hotspot`, `koneksi`, `router`, `wireless`, `lan`, `wan`.
- Ekspansi terjadi baik berdasarkan domain terdeteksi maupun token individual dalam query.
- Tujuan ilmiahnya adalah meningkatkan recall retrieval dengan menambahkan variasi istilah yang relevan tanpa mengubah intent asli.

### Kontribusi Penelitian
Sistem ini menggabungkan beberapa teknik yang dijelaskan secara eksplisit dalam implementasi:
- TF-IDF untuk representasi vektor dan perhitungan kemiripan term-document.
- Cosine Similarity untuk menghitung kedekatan query-dokumen pada vektor TF-IDF.
- Hybrid Ranking untuk menggabungkan sinyal lexical (`cosine`, `title overlap`, `query coverage`, `exact phrase`) dan konteks domain.
- Domain Detection untuk memfilter domain yang valid dan mengurangi kontaminasi lintas-domain.
- Query Expansion untuk memperbaiki recall dengan menambahkan sinonim dan istilah terkait secara kontekstual.
- Escalation Mechanism untuk mendeteksi repeated failure dan mengalihkan ke tiket/live chat secara sistematis.


-- Rincian service Chatbot penting (ringkasan fungsi):
- `AdvancedRetrievalService` — fungsi utama: `retrieve(string $query, int $limit) : array`, `formatResponse(array $retrievalResult) : array`, `shouldEscalate(string $query) : bool`, `getEscalationResponse() : array`, helper lain (normalize, expandQuery, buildTfidfVectors, hybridRanking, diversifyResults, applyThresholdAndLimit).
- `TfidfService` — fungsi utama: `calculateTF(array $termFreq)`, `calculateIDF(array $docTermFreqs)`, `calculateTFIDF(array $tf, array $idf)`, `calculateQueryTFIDF(string $query, array $idf)`.
- `CosineSimilarityService` — fungsi utama: `calculate(array $vecA, array $vecB) : float`.
- `PreprocessingService` — fungsi utama: `preprocess(string $text) : array` (menghasilkan token yang distandarisasi), `stripHtml`, `tokenize`.
- `DomainDetectionService` — fungsi utama: `detectDomain(string $query) : array`, `detectOutOfDomain(string $query) : array`.
- `VocabularyService` — fungsi utama: `normalizeQuery(string $query) : array (normalized, corrections)`.
- `ImportantPhraseService` — fungsi utama: `detectPhrases(string $query)`, `getPhraseBoostScore(string $query, array $document)`.

2) Modul: Ticketing
- Tujuan: Menyimpan, mengelola, dan melacak masalah yang dilaporkan pengguna; menyediakan alur eskalasi dari chatbot ke staf.
- File yang terlibat:
  - `app/Http/Controllers/TicketController.php` (user-facing), `app/Http/Controllers/Staff/TicketController.php` (staff actions)
  - `app/Models/Ticket.php`, `app/Models/TicketLog.php`, `app/Models/TicketOtp.php`
  - Mail: `app/Mail/TicketTrackingMail.php`, `app/Mail/TicketRejectionMail.php`, `app/Mail/TicketOtpMail.php`
  - Views: resources/views/tickets/* (form & detail)

- Input: form API request (user details, subject, description, attachments, priority)
- Output: record DB (Ticket), email notifikasi, response HTTP
- Alur proses: user mengirim laporan → `TicketController@store` validasi → simpan `Ticket` → buat `TicketLog` → kirim email tracking → return respon. Dari chatbot: tombol eskalasi memicu API yang memanggil controller ini.
- Relasi: Ticket -> `User` (pemilik), Ticket assigned -> `StaffProfile`, TicketLog menyimpan histori.

3) Modul: Knowledge Base
- Tujuan: Menyediakan konten artikel teknis terstruktur yang dapat dicari.
- File yang terlibat: `app/Models/Article.php`, `app/Models/Category.php`, `app/Models/ArticleKeywordIndex.php`, `app/Models/ArticleFeedback.php`, `app/Http/Controllers/ArticleController.php`, `app/Observers/ArticleObserver.php` (jika ada), `app/Services/Chatbot/TypesenseService.php` (indexing/search)
- Input: CRUD artikel via admin UI (title, content, excerpt, keywords, category, publish_status)
- Output: Artikel terpublikasi untuk di-query oleh chatbot/penemuan internal
- Alur proses: admin membuat atau mengubah artikel → Observer/Controller melakukan indexing ke Typesense (opsional) → article tersedia untuk retrieval
- Relasi: Articles ↔ Category; ArticleFeedback membantu evaluasi kualitas, ArticleKeywordIndex mempercepat pencarian.

4) Modul: Authentication & Authorization
- Tujuan: Kelola login, registrasi, reset password, verifikasi email, dan pembatasan akses berdasarkan role.
- File utama: `app/Http/Controllers/Auth/*` (AuthenticatedSessionController, RegisteredUserController, PasswordController, VerifyEmailController), `app/Models/User.php`, `app/Providers/AuthServiceProvider.php`, middleware `app/Http/Middleware/*`
- Input: kredensial, token verifikasi, permintaan logout
- Output: session cookie (web), token, redirect, responses error/ok
- Alur proses: user login → `AuthenticatedSessionController@store` → Laravel Auth menyediakan session → middleware `auth` melindungi rute staff/admin
- Relasi: User ↔ StaffProfile; otorisasi mempengaruhi akses ke `Staff` controllers dan fitur manajemen KB/Ticket.

5) Modul: Dashboard Admin
- Tujuan: Panel admin untuk mengelola users, articles, categories, settings, dan melihat laporan.
- File: `app/Http/Controllers/Admin/*` (UserController, DashboardController, CategoryController), views `resources/views/admin/*`.
- Input: action admin (CRUD), filter, laporan request
- Output: update DB, render halaman admin, export laporan
- Alur proses: admin mengakses dashboard → controller memverifikasi role → query model → render view
- Relasi: Admin mengelola `Article`, `Category`, `User`, `Setting`.

6) Modul: Dashboard Staff
- Tujuan: Panel untuk staf meninjau tiket, merespon, dan menutup masalah.
- File: `app/Http/Controllers/Staff/*` (TicketController, DashboardController)
- Input: aksi staff (ambil tiket, update status, komentar)
- Output: update Ticket status, Notifikasi, history log
- Alur proses: staff login → lihat tiket → update → buat `TicketLog` → notifikasi.

--------------------------------------------------------------
TABEL: Controller → Fungsi (ringkas)
--------------------------------------------------------------
Berikut tabel ringkas (controller utama):
- `ChatbotController`:
  - `handle()` / `store()` : terima pesan user, panggil retrieval, return response
  - `escalate()` : endpoint pembuatan tiket dari UI chatbot
- `TicketController` (user):
  - `store()` : buat tiket
  - `show()` : tampilkan detail
  - `index()` : list tiket user
- `Staff/TicketController`:
  - `assign()` : menugaskan tiket
  - `update()` : update status/komentar
  - `destroy()` : hapus/arsip
- `ArticleController`:
  - `index()`, `create()`, `store()`, `edit()`, `update()`, `destroy()` — CRUD artikel
- `Admin/DashboardController`:
  - `index()` : ringkasan KPI & laporan

--------------------------------------------------------------
TABEL: Service → Fungsi (ringkas)
--------------------------------------------------------------
- `AdvancedRetrievalService`:
  - `retrieve(string $query, int $limit)` — end-to-end retrieval
  - `formatResponse(array $retrievalResult)` — format final response
  - `shouldEscalate(string $query)` — memutuskan eskalasi
  - helper: `hybridRanking`, `buildTfidfVectors`, `prepareDocuments`, `diversifyResults`
- `TfidfService`:
  - `calculateIDF(array $docTermFreqs)`, `calculateTF(array $termFreq)`, `calculateTFIDF(array $tf, array $idf)`, `calculateQueryTFIDF(string $query, array $idf)`
- `CosineSimilarityService`:
  - `calculate(array $vecA, array $vecB) : float`
- `PreprocessingService`:
  - `preprocess(string $text) : array` (tokenize, normalize)
- `DomainDetectionService`:
  - `detectDomain(string $query)`, `detectOutOfDomain(string $query)`
- `VocabularyService`:
  - `normalizeQuery(string $query) : array`
- `ImportantPhraseService`:
  - `detectPhrases(string $query)`, `getPhraseBoostScore(...)`

--------------------------------------------------------------
TABEL: Model → Fungsi (ringkas)
--------------------------------------------------------------
- `User` : identity, authentication, relasi ke `Ticket`, `StaffProfile`
- `Ticket` : simpan tiket, relasi ke `TicketLog`, `User`, status workflow
- `TicketLog` : histori perubahan tiket
- `Article` : konten KB, atribut publish, relasi ke `Category`
- `Category` : klasifikasi artikel (digunakan untuk domain filtering)
- `ArticleFeedback` : menyimpan feedback pembaca

--------------------------------------------------------------
TABEL: Middleware → Fungsi (ringkas)
--------------------------------------------------------------
- `auth` : memastikan user terautentikasi
- `verified` : memastikan email terverifikasi
- `role:staff|admin` (custom) : membatasi akses berdasarkan role

=================================================================
Dokumentasi detail: Chatbot — Alur query pengguna (end-to-end)
=================================================================

1. User Query (UI) —> `ChatbotController`
- Input: { user_id (opsional), session_id, query_text, metadata }
- Controller: validasi, ambil session context, panggil service retrieval.

2. `ChatbotController` —> `AdvancedRetrievalService::retrieve($query)`
- Tujuan panggilan: dapatkan daftar artikel terbaik + keputusan (fallback/escalate).

3. `AdvancedRetrievalService` internal steps:
  a) Out-of-domain detection: panggil `DomainDetectionService::detectOutOfDomain($query)`. Jika out-of-domain => kembalikan `outOfDomainResult`.
  b) Normalisasi: `VocabularyService::normalizeQuery($query)` untuk koreksi typo & sinonim.
  c) Multi-intent detection: `detectMultiIntent()` — jika multi intent, lakukan `multiIntentRetrieval()`.
  d) Domain detection: `DomainDetectionService::detectDomain($query)` untuk menentukan allowed categories.
  e) Candidate selection: `getDomainFilteredArticles($allowedCategories)` — query Eloquent ke `Article` atau fallback ke `getPublishedArticles()`.
  f) Preprocessing & doc preparation: `PreprocessingService->preprocess()` pada title/excerpt/content untuk membentuk token list dan frequency
  g) TF-IDF building: `TfidfService->calculateIDF()` dan `calculateTFIDF()` untuk dokumen; `calculateQueryTFIDF()` untuk query
  h) Scoring / Hybrid ranking: untuk setiap dokumen: hitung cosine similarity (`CosineSimilarityService`), title overlap, query coverage, exact phrase bonus (`ImportantPhraseService`), domain penalty, diversification penalties. Kombinasikan sesuai bobot untuk `final_score`.
  i) Diversification: `diversifyResults()` / `diversifyResultsEnhanced()` untuk mengurangi dominasi satu kategori.
  j) Threshold & limit: `applyThresholdAndLimit()` untuk memfilter skor rendah dan membatasi jumlah hasil.
  k) Track & memory: `trackRetrievalResult()` untuk failure counting (Session store); `storeConversationContext()` jika perlu.
  l) Decision: jika empty atau weak & failure count tinggi => `shouldEscalate()` -> `getEscalationResponse()`; jika weak tapi tidak eskalasi -> `getSafeFallbackResponse()`; else -> `formatResponse()`.

4. Response dikembalikan ke `ChatbotController` dan diteruskan ke client.

=================================================================
Mapping: User Query → Controller → Service → Model → Response (contoh)
=================================================================

- User Query: "wifi tidak connect"
  - Controller: `ChatbotController@handle` menerima request
  - Service: `AdvancedRetrievalService::retrieve("wifi tidak connect")`
    - `DomainDetectionService::detectDomain` -> domain 'wifi'
    - `VocabularyService::normalizeQuery` -> perbaikan typo/synonym
    - `getDomainFilteredArticles(['wifi','internet','jaringan'])` -> Eloquent `Article` query (models: `Article`, `Category`)
    - `PreprocessingService->preprocess` -> tokens
    - `TfidfService->calculateQueryTFIDF` & build doc vectors
    - `CosineSimilarityService->calculate` untuk setiap doc vector
    - `ImportantPhraseService->getPhraseBoostScore` untuk phrase 'tidak connect'
    - `hybridRanking` -> ranking & final_score
    - `applyThresholdAndLimit` -> final article list
  - Controller formats response: assistant text + `articles` list
  - Response sample: { success: true, response: "Ringkasan...", articles: [ ... ], confidence: 'high' }

=================================================================
Per-file penting: nama file, tujuan, method utama, hubungan
=================================================================

- `app/Services/Chatbot/AdvancedRetrievalService.php`
  - Tujuan: implementasi hybrid retrieval & decision logic
  - Method utama: `retrieve()`, `singleIntentRetrieval()`, `multiIntentRetrieval()`, `hybridRanking()`, `formatResponse()`
  - Hubungan: memanggil `PreprocessingService`, `TfidfService`, `CosineSimilarityService`, `DomainDetectionService`, `VocabularyService`, `ImportantPhraseService`; query `Article` model.

- `app/Services/Chatbot/TfidfService.php`
  - Tujuan: hitung TF, IDF, TF-IDF dan vektor query
  - Method utama: `calculateTF()`, `calculateIDF()`, `calculateTFIDF()`, `calculateQueryTFIDF()`
  - Hubungan: digunakan oleh `AdvancedRetrievalService` untuk membangun vektor.

- `app/Services/Chatbot/CosineSimilarityService.php`
  - Tujuan: menyediakan fungsi perhitungan kemiripan vektor
  - Method utama: `calculate(array $a, array $b)`
  - Hubungan: dipanggil dari `AdvancedRetrievalService::hybridRanking()`.

- `app/Services/Chatbot/ConversationFlowService.php` (jika ada)
  - Tujuan: memanage state percakapan, follow-up prompts, dan dialog tree
  - Method utama: `nextPrompt()`, `shouldClarify()`, `handleUserAction()`
  - Hubungan: dapat digunakan oleh `ChatbotController` sebelum/selesai pemanggilan retrieval.

- `app/Services/Chatbot/DomainDetectionService.php`
  - Tujuan: mendeteksi domain dan menolak out-of-domain
  - Method utama: `detectDomain()`, `detectOutOfDomain()`
  - Hubungan: dipanggil awal pada retrieval untuk filtering dan pesan penolakan.

=================================================================
Penutup dan langkah selanjutnya
=================================================================

Saya sudah memperluas dokumentasi untuk semua modul prioritas Anda dan menambahkan pemetaan end-to-end untuk alur chatbot. Saat ini dokumen utama ada di: [docs/ARCHITECTURE_FOR_SKRIPSI.md](docs/ARCHITECTURE_FOR_SKRIPSI.md).

Opsi selanjutnya (pilih):
- Review & perbaikan spesifik: saya bisa menguraikan tabel file→method untuk setiap file di `app/` (detail method signature).
- Tambah skenario percakapan dan contoh debug trace dari `AdvancedRetrievalService::retrieve()` (butuh akses app.debug atau sample payload; saya bisa menyusun contoh hipotetik).
- Siapkan ringkasan slide singkat (poin-poin) untuk presentasi sidang.

Saya tidak membuat diagram dan tidak mengubah kode seperti permintaan. Mau saya kerjakan opsi mana berikutnya? 

