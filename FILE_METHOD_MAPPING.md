# Mapping File → Method untuk Setiap Diagram

Dokumentasi ini menunjukkan file mana yang digunakan di setiap tahap diagram, beserta method dan class yang relevan.

---

## 1. CHATBOT FLOW - File & Method Mapping

### A. Input & Validation
| File | Class | Method | Input | Output |
|------|-------|--------|-------|--------|
| `routes/web.php` | Router | POST `/api/chatbot` | Request | → ChatbotController |
| `app/Http/Controllers/ChatbotController.php` | ChatbotController | `getResponse()` | `message: string` | `JsonResponse` |

### B. DomainDetectionService - Out-of-Domain Check
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Services/Chatbot/DomainDetectionService.php` | DomainDetectionService | `detectOutOfDomain()` | Cek query di luar domain IT (return `[is_out_of_domain, reason]`) |
| | | `detectDomain()` | Deteksi domain spesifik (wifi, printer, email, dll) |

### C. VocabularyService - Normalisasi Query
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Services/Chatbot/VocabularyService.php` | VocabularyService | `normalizeQuery()` | Koreksi typo & sinonim |
| | | `normalizeTypos()` | Perbaiki typo umum (wfi → wifi) |
| | | `normalizeSynonyms()` | Konversi sinonim ke kata standar |

### D. MultiIntentDetection - Pemisahan Query Kompleks
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Services/Chatbot/AdvancedRetrievalService.php` | AdvancedRetrievalService | `detectMultiIntent()` | Pisahkan query dengan AND/OR/DENGAN/SERTA |
| | | `multiIntentRetrieval()` | Retrieval untuk setiap intent secara terpisah |
| | | `balancedMerge()` | Gabung hasil dengan round-robin |

### E. Article Selection & Preprocessing
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Models/Article.php` | Article | (Eloquent) | Model artikel di database |
| `app/Services/Chatbot/AdvancedRetrievalService.php` | AdvancedRetrievalService | `getDomainFilteredArticles()` | Query artikel by allowed categories |
| | | `getPublishedArticles()` | Fallback: ambil semua artikel published |
| | | `prepareDocuments()` | Format dokumen untuk TF-IDF |
| `app/Services/Chatbot/PreprocessingService.php` | PreprocessingService | `preprocess()` | Tokenisasi & normalisasi |
| | | `tokenize()` | Pecah teks menjadi token |
| | | `stripHtml()` | Hapus HTML tags |

### F. TF-IDF Vectorization
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Services/Chatbot/TfidfService.php` | TfidfService | `buildTfidfVectors()` | Buat vektor TF-IDF untuk semua dokumen |
| | | `calculateIDF()` | Hitung IDF (Inverse Document Frequency) |
| | | `calculateTF()` | Hitung TF (Term Frequency) |
| | | `calculateTFIDF()` | Hitung TF-IDF = TF × IDF |
| | | `calculateQueryTFIDF()` | Hitung vektor query |

### G. Cosine Similarity & Hybrid Ranking
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Services/Chatbot/CosineSimilarityService.php` | CosineSimilarityService | `calculate()` | Hitung cosine similarity(queryVector, docVector) |
| `app/Services/Chatbot/AdvancedRetrievalService.php` | AdvancedRetrievalService | `hybridRanking()` | Kombinasikan 6 sinyal scoring: |
| | | | - Cosine Similarity 30% |
| | | | - Title Overlap 25% |
| | | | - Domain Match 15% |
| | | | - Query Coverage 15% |
| | | | - Exact Phrase 10% |
| | | | - Diversification 5% |

### H. Important Phrase Detection & Boosting
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Services/Chatbot/ImportantPhraseService.php` | ImportantPhraseService | `detectPhrases()` | Deteksi phrase penting dalam query |
| | | `getPhraseBoostScore()` | Hitung bonus untuk artikel dengan phrase match |

### I. Diversification & Threshold
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Services/Chatbot/AdvancedRetrievalService.php` | AdvancedRetrievalService | `diversifyResults()` | Kurangi dominasi satu kategori |
| | | `diversifyResultsEnhanced()` | Diversifikasi advanced (kategori, title, dll) |
| | | `applyThresholdAndLimit()` | Filter by threshold & limit hasil |

### J. Escalation Decision & Response Formatting
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Services/Chatbot/AdvancedRetrievalService.php` | AdvancedRetrievalService | `trackRetrievalResult()` | Catat failure count untuk escalation |
| | | `shouldEscalate()` | Apakah failure count ≥ FAILURE_THRESHOLD (3)? |
| | | `getEscalationResponse()` | Return: "Buat Tiket" atau "Live Chat" |
| | | `getSafeFallbackResponse()` | Return: FAQ atau general response |
| | | `formatResponse()` | Format final JSON response |

### K. Output
| File | Class | Method | Output |
|------|-------|--------|--------|
| `app/Http/Controllers/ChatbotController.php` | ChatbotController | `getResponse()` | `{success, response, articles[], confidence, flags}` |

---

## 2. TICKETING FLOW - File & Method Mapping

### A. Guest Form & Validation
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `routes/web.php` | Router | GET/POST `/help` | → TicketController |
| `app/Http/Controllers/TicketController.php` | TicketController | `create()` | Show form dengan kategori & captcha |
| | | `store()` | Validasi input & simpan tiket |

### B. Rate Limiting & Validation Rules
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Http/Controllers/TicketController.php` | TicketController | `store()` | Cek rate limit by IP & email |
| | | | Validasi: name, email, subject, message, category_id, captcha |

### C. Ticket Creation & Logging
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Models/Ticket.php` | Ticket | (Eloquent Model) | Simpan: name, email, subject, message, category_id, staff_id, status, priority |
| `app/Models/TicketLog.php` | TicketLog | (Eloquent Model) | Audit trail: action, description, created_at |
| `app/Http/Controllers/TicketController.php` | TicketController | `store()` | DB::transaction() untuk atomicity |

### D. Auto-Assignment & OTP
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Http/Controllers/TicketController.php` | TicketController | `store()` | Query StaffProfile untuk least busy staff di category |
| `app/Models/TicketOtp.php` | TicketOtp | (Eloquent Model) | Generate & simpan 6-digit OTP |

### E. Email Notification
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Mail/TicketOtpMail.php` | TicketOtpMail | (Mailable) | Email OTP ke user |
| `app/Mail/TicketTrackingMail.php` | TicketTrackingMail | (Mailable) | Email tracking token ke user |
| `app/Http/Controllers/TicketController.php` | TicketController | `store()` | Mail::send() untuk kedua email |

### F. Staff Ticket Management
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Http/Controllers/Staff/TicketController.php` | Staff\TicketController | `index()` | List tiket assigned ke staff |
| | | `show()` | View detail tiket |
| | | `updatePriority()` | Update priority & create TicketLog |
| | | `updateStatus()` | Update status (assigned→progress→waiting→closed) |

### G. Output & Response
| File | Class | Method | Output |
|------|-------|--------|--------|
| `app/Http/Controllers/TicketController.php` | TicketController | `store()` | `{success, ticket_id, tracking_token}` |

---

## 3. KNOWLEDGE BASE FLOW - File & Method Mapping

### A. Article CRUD
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Http/Controllers/ArticleController.php` | ArticleController | `index()` | List artikel milik staff |
| | | `create()` | Show form untuk artikel baru |
| | | `store()` | Validasi & simpan artikel baru |
| | | `edit()` | Show form edit artikel |
| | | `update()` | Update artikel |
| | | `destroy()` | Soft delete / archive artikel |

### B. Article Model & Category
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Models/Article.php` | Article | (Eloquent Model) | Simpan: title, content, excerpt, keywords, category_id, staff_id, is_published |
| `app/Models/Category.php` | Category | (Eloquent Model) | Kategori artikel (wifi, printer, email, dll) |
| `app/Models/ArticleKeywordIndex.php` | ArticleKeywordIndex | (Eloquent Model) | (Opsional) Indeks keyword untuk search |

### C. Indexing Service
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Services/Chatbot/TypesenseService.php` | TypesenseService | `indexArticle()` | Index artikel ke Typesense |
| | | `updateArticle()` | Update artikel di index |
| | | `deleteArticle()` | Hapus dari index |
| | | `search()` | Search via Typesense |

### D. Publishing & Feedback
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Models/Article.php` | Article | (relation) | scope `published()` untuk is_published = true |
| `app/Models/ArticleFeedback.php` | ArticleFeedback | (Eloquent Model) | Simpan helpful/not helpful votes |

### E. Chatbot Integration
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Services/Chatbot/AdvancedRetrievalService.php` | AdvancedRetrievalService | `getDomainFilteredArticles()` | Query Article model untuk candidates |
| | | `prepareDocuments()` | Format artikel untuk TF-IDF |

---

## 4. LOGIN & OTORISASI FLOW - File & Method Mapping

### A. Login Form & Request
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `routes/web.php` | Router | GET/POST `/login` | → AuthenticatedSessionController |
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | AuthenticatedSessionController | `create()` | Show login form |
| `app/Http/Requests/Auth/LoginRequest.php` | LoginRequest | `authorize()` / `rules()` | Validate email & password |

### B. Authentication
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | AuthenticatedSessionController | `store()` | Call `$request->authenticate()` |
| `app/Http/Requests/Auth/LoginRequest.php` | LoginRequest | `authenticate()` | Laravel Auth::attempt() → User lookup & password verify |
| `app/Models/User.php` | User | (Authenticatable) | Model untuk login |

### C. Session & Role Check
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | AuthenticatedSessionController | `store()` | `$request->session()->regenerate()` → CSRF security |
| | | | Check `user.role` → redirect ke dashboard sesuai role |
| `app/Models/User.php` | User | `isAdmin()` / `isStaff()` | Role helper methods |

### D. Middleware Protection
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Http/Middleware/Authenticate.php` | Authenticate | (Middleware) | Ensure user authenticated |
| `app/Http/Middleware/VerifyEmail.php` | VerifyEmail | (Middleware) | Ensure email verified (optional) |
| `app/Providers/AuthServiceProvider.php` | AuthServiceProvider | (Provider) | Define Gates & Policies |

### E. Route Protection
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `routes/auth.php` | Router | Middleware `auth` | Protect authenticated routes |
| | | Middleware `admin` / `staff` | Role-based access control |

### F. Logout
| File | Class | Method | Purpose |
|------|-------|--------|---------|
| `app/Http/Controllers/Auth/AuthenticatedSessionController.php` | AuthenticatedSessionController | `destroy()` | Logout & session invalidate |
| | | | Check if staff has active ticket → block logout if yes |

---

## Summary: File → Diagram Mapping

### Chatbot Diagram Files
- **Controllers:** `ChatbotController.php`
- **Services:** `AdvancedRetrievalService.php`, `DomainDetectionService.php`, `VocabularyService.php`, `PreprocessingService.php`, `TfidfService.php`, `CosineSimilarityService.php`, `ImportantPhraseService.php`, `ConversationFlowService.php`
- **Models:** `Article.php`, `Category.php`

### Ticketing Diagram Files
- **Controllers:** `TicketController.php`, `Staff/TicketController.php`
- **Models:** `Ticket.php`, `TicketLog.php`, `TicketOtp.php`, `StaffProfile.php`, `Category.php`
- **Mails:** `TicketOtpMail.php`, `TicketTrackingMail.php`

### Knowledge Base Diagram Files
- **Controllers:** `ArticleController.php`
- **Models:** `Article.php`, `Category.php`, `ArticleFeedback.php`, `ArticleKeywordIndex.php`
- **Services:** `TypesenseService.php` (indexing)

### Login & Otorisasi Diagram Files
- **Controllers:** `Auth/AuthenticatedSessionController.php`, `Auth/RegisteredUserController.php`
- **Requests:** `Auth/LoginRequest.php`
- **Models:** `User.php`, `StaffProfile.php`
- **Middleware:** `Authenticate.php`, `VerifyEmail.php`
- **Providers:** `AuthServiceProvider.php`

---

**Dokumentasi Dibuat:** 2026-06-08  
**Basis:** Source Code + ARCHITECTURE_FOR_SKRIPSI.md
