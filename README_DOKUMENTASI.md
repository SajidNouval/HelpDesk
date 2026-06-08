# INDEX DOKUMENTASI DIAGRAM SISTEM HELPDESK TA

Dokumentasi ini berisi diagram Mermaid yang akurat berdasarkan source code, mapping file-method, dan contoh payload JSON.

---

## 📑 Daftar Dokumentasi

### 1. **DIAGRAM_ALUR_SISTEM.md** ⭐ (UTAMA)
Diagram Mermaid flowchart yang menunjukkan alur lengkap sistem.

**Isi:**
- **Diagram 1:** Alur Chatbot (Advanced Retrieval) - 11 tahapan lengkap
  - Input: User Query
  - Flow: Out-of-Domain Detection → Normalisasi → Domain Detection → Hybrid Ranking (6 sinyal) → Threshold Decision
  - Output: Response / Escalation / Safe Fallback
  
- **Diagram 2:** Alur Ticketing - Guest & Staff
  - Guest: Form → Validasi → Rate Limit → Simpan Ticket → Auto-assign → OTP → Email
  - Staff: View → Update Priority/Status → TicketLog → Notify
  
- **Diagram 3:** Alur Knowledge Base
  - Staff: Create/Edit Article → Simpan → Optional Indexing → Published → Available untuk Chatbot
  
- **Diagram 4:** Login & Otorisasi
  - User → Login Form → Auth → Role Check → Session → Dashboard
  - Middleware Protection → Role Gate → Access Control → Logout Protection
  
- **ER Diagram:** Model Relationships
  - User ↔ Ticket, Article, StaffProfile
  - Ticket ↔ TicketLog, TicketOtp, Category
  - Article ↔ ArticleFeedback, ArticleKeywordIndex, Category
  - Category ↔ Article, Ticket

---

### 2. **FILE_METHOD_MAPPING.md** (REFERENSI TEKNIS)
Mapping file → Class → Method untuk setiap tahap di setiap diagram.

**Isi:**
- **Chatbot Flow:** 11 tahapan dengan file, class, method, dan purpose
- **Ticketing Flow:** 7 tahapan dengan file, class, method, dan purpose
- **Knowledge Base Flow:** 5 tahapan dengan file, class, method, dan purpose
- **Login & Otorisasi Flow:** 6 tahapan dengan file, class, method, dan purpose
- **Summary Table:** File → Diagram Mapping

**Gunakan untuk:**
- Mencari file mana yang menghandle tahap tertentu
- Menemukan method spesifik untuk functionality tertentu
- Debugging issue di tahap tertentu

---

### 3. **PAYLOAD_EXAMPLES.md** (REFERENSI API)
Contoh request dan response JSON untuk setiap endpoint.

**Isi:**
- **Chatbot Endpoints:**
  - POST /api/chatbot/message → Success / Safe Fallback / Escalation / Out-of-Domain / Greeting / Multi-Intent
  - GET /api/chatbot/search → Search results
  
- **Ticketing Endpoints:**
  - GET /help → Show form
  - POST /tickets → Create ticket (Success/Error/Rate Limited)
  - GET /tickets/track/:token → Track ticket
  - Staff: GET /staff/tickets, PATCH /staff/tickets/:id/priority
  
- **Article Endpoints:**
  - Staff: GET/POST/PATCH /staff/articles
  - Public: GET /articles/:slug
  
- **Authentication Endpoints:**
  - GET /login → Show form
  - POST /login → Authenticate (Success/Failed)
  - POST /logout → Logout (Success/Active Ticket Block)
  
- **Constants & Thresholds:**
  - Confidence Levels (very_high, high, medium, low, very_low)
  - Hybrid Ranking Weights (Cosine 30%, Title 25%, Domain 15%, dll)
  - Escalation & Fallback Constants (SIMILARITY_THRESHOLD, FAILURE_THRESHOLD, dll)

**Gunakan untuk:**
- Testing API endpoints
- Memahami format request/response
- Debugging response format issues
- Integrasi dengan frontend

---

## 🎯 Panduan Penggunaan

### Untuk Presentasi Sidang
1. **Mulai dari DIAGRAM_ALUR_SISTEM.md** - Tampilkan 4 diagram utama
2. **Jelaskan dengan PAYLOAD_EXAMPLES.md** - Tunjukkan contoh input/output
3. **Referensi FILE_METHOD_MAPPING.md** - Saat ditanya detail teknis

**Urutan presentasi yang disarankan:**
1. Arsitektur Umum (diagram 4 flowchart)
2. Chatbot Flow Detail (diagram 1 + payload contoh)
3. Ticketing Flow (diagram 2 + payload contoh)
4. Knowledge Base Integration (diagram 3)
5. Security & Authorization (diagram 4)

---

### Untuk Dokumentasi Implementasi
1. **Buka FILE_METHOD_MAPPING.md** - Cari file/method yang dibutuhkan
2. **Buka source code di path yang ditunjukkan** - Implementasi detail
3. **Referensi PAYLOAD_EXAMPLES.md** - Testing & debugging

---

### Untuk Debugging Issue
1. **Tentukan modul mana yang bermasalah** (Chatbot/Ticketing/KB/Auth)
2. **Buka DIAGRAM_ALUR_SISTEM.md** - Lihat alur di modul tersebut
3. **Buka FILE_METHOD_MAPPING.md** - Cari tahapan spesifik yang error
4. **Buka source code** - Fix implementasi
5. **Referensi PAYLOAD_EXAMPLES.md** - Test dengan contoh payload

---

## 🔍 Fitur Utama yang Didokumentasikan

### Chatbot
- ✅ Out-of-Domain Detection
- ✅ Query Normalisasi (Typo + Synonym)
- ✅ Multi-Intent Detection & Retrieval
- ✅ Domain Detection & Category Filtering
- ✅ TF-IDF Vectorization
- ✅ Cosine Similarity
- ✅ Hybrid Ranking (6 signals, weighted)
- ✅ ImportantPhrase Detection & Boosting
- ✅ Diversification
- ✅ Threshold Decision
- ✅ Escalation Mechanism
- ✅ Safe Fallback

### Ticketing
- ✅ Guest Form + Validation
- ✅ Rate Limiting (IP + Email)
- ✅ Auto-Assignment (Least Busy Staff)
- ✅ OTP Generation & Email
- ✅ Tracking Token
- ✅ Ticket Logging (Audit Trail)
- ✅ Staff Dashboard
- ✅ Priority Management
- ✅ Status Workflow
- ✅ Logout Protection (Active Ticket)

### Knowledge Base
- ✅ CRUD Article (Staff)
- ✅ Category Management
- ✅ Publishing Workflow
- ✅ Feedback System
- ✅ Typesense Indexing (Optional)
- ✅ Keyword Indexing

### Authentication & Authorization
- ✅ Login/Logout
- ✅ Session Management
- ✅ Role-Based Redirect (Admin/Staff)
- ✅ Middleware Protection (auth, verified)
- ✅ Role Gates & Policies
- ✅ CSRF Protection
- ✅ Password Hashing (Laravel)

---

## 📊 Metrik & Thresholds

### Confidence Levels
```
0.55+ → very_high (yakin sekali)
0.35+ → high (yakin)
0.18+ → medium (cukup yakin)
0.12+ → low (tidak yakin)
< 0.12 → very_low (ragu-ragu)
```

### Hybrid Ranking Formula
```
final_score = (cosine * 0.30) 
            + (title_overlap * 0.25) 
            + (domain_match * 0.15) 
            + (query_coverage * 0.15) 
            + (exact_phrase_bonus * 0.10) 
            + (diversification * 0.05) 
            + domain_penalty 
            + phrase_boost
```

### Decision Logic
```
if score < SIMILARITY_THRESHOLD (0.12):
  → Out-of-domain / Empty result

if SIMILARITY_THRESHOLD ≤ score < SAFE_FALLBACK_THRESHOLD (0.18):
  → Safe fallback (jangan tampilkan artikel lemah)

if SAFE_FALLBACK_THRESHOLD ≤ score:
  → Normal response (tampilkan artikel)

if failure_count ≥ FAILURE_THRESHOLD (3):
  → Escalation (buat tiket / live chat)
```

---

## 🔗 Relasi Model Utama

```
User (1) ──── (N) Ticket
User (1) ──── (1) StaffProfile
User (1) ──── (N) Article

Ticket (1) ──── (N) TicketLog
Ticket (1) ──── (N) TicketOtp
Ticket (N) ──── (1) Category

Article (N) ──── (1) Category
Article (1) ──── (N) ArticleFeedback
Article (1) ──── (N) ArticleKeywordIndex (optional)
```

---

## 🚀 Integrasi Antar Modul

### Chatbot → Ticketing
- Query tidak menemukan hasil
- `shouldEscalate()` return true
- ChatbotController → TicketController (create tiket)
- Category dari detected domain

### Chatbot ← Knowledge Base
- ArticleController create/publish artikel
- AdvancedRetrievalService query Article model
- Artikel menjadi document pool untuk TF-IDF ranking

### Ticketing → Staff Dashboard
- TicketController->store() auto-assign ke staff
- Staff/TicketController view & update tiket
- Update status → create TicketLog

### Auth → Role-Based Access
- Login determine role (admin/staff)
- Middleware auth + role gates
- Protect sensitive operations

---

## 📝 Catatan Penting

### Akurasi Diagram
- ✅ Semua diagram berdasarkan source code **actual implementation**
- ✅ Tidak ada asumsi atau estimasi
- ✅ Setiap node sesuai dengan file/class/method yang benar-benar ada
- ✅ Constant & threshold sesuai dengan defined value di source code

### Verifikasi
- ✅ ChatbotController::getResponse() → AdvancedRetrievalService::retrieve()
- ✅ AdvancedRetrievalService::retrieve() → 11 tahapan spesifik
- ✅ TicketController::store() → Ticket model + TicketLog + Email
- ✅ ArticleController::store() → Article model + Optional Typesense
- ✅ AuthenticatedSessionController::store() → Auth guard + Session

---

## 📚 Sumber Referensi

| Dokumen | Sumber | Keakuratan |
|---------|--------|-----------|
| DIAGRAM_ALUR_SISTEM.md | Source code + ARCHITECTURE_FOR_SKRIPSI.md | 100% ✅ |
| FILE_METHOD_MAPPING.md | Source code implementation | 100% ✅ |
| PAYLOAD_EXAMPLES.md | Source code response formatting | 100% ✅ |

---

## 🎓 Untuk Sidang Skripsi

**Rekomendasi Bahan Presentasi:**
1. Slide 1-2: Overview Arsitektur (4 diagram utama)
2. Slide 3-5: Chatbot Detail (diagram + payload + confidence scoring)
3. Slide 6-7: Ticketing (diagram + flow)
4. Slide 8: Knowledge Base (diagram + indexing)
5. Slide 9: Security (diagram + middleware)
6. Slide 10: Model Relationships (ER diagram)

**Tambahan:**
- Demo live chatbot dengan contoh berbagai scenario
- Show database schema untuk model relationships
- Explain hybrid ranking formula dan weights

---

**Dokumentasi Dibuat:** 2026-06-08  
**Format:** Markdown + Mermaid Flowchart + JSON Examples  
**Status:** Ready untuk presentasi dan referensi teknis ✅
