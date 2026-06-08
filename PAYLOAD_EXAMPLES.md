# Contoh Payload & Output JSON untuk Setiap Endpoint

Dokumentasi ini berisi contoh request dan response JSON yang akurat berdasarkan implementasi source code.

---

## 1. CHATBOT ENDPOINTS

### 1.1 POST /api/chatbot/message (Get Response)

**Request:**
```json
{
  "message": "wifi tidak bisa connect"
}
```

**Response (Success - Normal):**
```json
{
  "success": true,
  "response": "Berikut beberapa solusi untuk masalah koneksi WiFi Anda...\n\nUntuk panduan lebih lengkap, silakan lihat artikel berikut:",
  "articles": [
    {
      "id": "abc123",
      "title": "Cara Mengatasi WiFi Tidak Connect",
      "excerpt": "WiFi tidak dapat terhubung bisa disebabkan oleh berbagai faktor...",
      "slug": "cara-mengatasi-wifi-tidak-connect",
      "category_name": "WiFi",
      "final_score": 0.68,
      "confidence": "high",
      "url": "/articles/cara-mengatasi-wifi-tidak-connect"
    },
    {
      "id": "def456",
      "title": "Troubleshooting WiFi Lemot",
      "excerpt": "Jika koneksi WiFi Anda terasa sangat lambat...",
      "slug": "troubleshooting-wifi-lemot",
      "category_name": "WiFi",
      "final_score": 0.52,
      "confidence": "medium",
      "url": "/articles/troubleshooting-wifi-lemot"
    }
  ],
  "show_contact_button": false,
  "contact_button_text": null,
  "confidence": "high",
  "diversity": {
    "categories": 1,
    "is_diverse": false
  }
}
```

**Response (Safe Fallback - Score Weak):**
```json
{
  "success": false,
  "response": "Maaf, saya kurang yakin dengan jawaban yang tepat untuk pertanyaan ini 🤔\n\nBisa coba jelaskan lebih spesifik? Misalnya:\n• Sebutkan perangkat yang bermasalah (wifi, printer, komputer, dll)\n• Jelaskan gejala atau error yang muncul\n• Sertakan pesan error jika ada",
  "articles": [],
  "show_contact_button": true,
  "contact_button_text": "Hubungi Staff untuk Bantuan Langsung",
  "confidence": "very_low",
  "is_safe_fallback": true,
  "suggestions": [
    {
      "id": "1",
      "category": "WiFi",
      "icon": "📶"
    },
    {
      "id": "2",
      "category": "Printer",
      "icon": "🖨️"
    },
    {
      "id": "3",
      "category": "Email",
      "icon": "📧"
    }
  ]
}
```

**Response (Escalation - Failure Threshold Exceeded):**
```json
{
  "success": false,
  "response": "Sepertinya saya belum menemukan solusi yang tepat 😔\n\nJangan khawatir, tim support kami siap membantu!",
  "should_escalate": true,
  "escalation_buttons": [
    {
      "label": "💬 Live Chat",
      "action": "contact_staff"
    },
    {
      "label": "📧 Buat Tiket",
      "action": "create_ticket"
    },
    {
      "label": "🔄 Coba Pertanyaan Lain",
      "action": "try_another"
    }
  ]
}
```

**Response (Out-of-Domain):**
```json
{
  "success": false,
  "response": "Pertanyaan Anda di luar bidang yang saya kuasai. Saya hanya bisa membantu dengan pertanyaan seputar teknologi dan IT support.",
  "articles": [],
  "show_contact_button": false,
  "is_out_of_domain": true,
  "confidence": "none"
}
```

**Response (Greeting):**
```json
{
  "success": true,
  "response": "Halo! 👋 Saya adalah chatbot helpdesk TA. Ada yang bisa saya bantu?",
  "articles": [],
  "categories": [
    {
      "id": "1",
      "category": "WiFi",
      "icon": "📶"
    },
    {
      "id": "2",
      "category": "Printer",
      "icon": "🖨️"
    },
    {
      "id": "3",
      "category": "Email",
      "icon": "📧"
    },
    {
      "id": "4",
      "category": "Komputer",
      "icon": "💻"
    },
    {
      "id": "5",
      "category": "Security",
      "icon": "🔒"
    }
  ]
}
```

**Response (Multi-Intent):**
```json
{
  "success": true,
  "response": "Saya menemukan artikel untuk kedua masalah Anda...",
  "articles": [
    {
      "id": "wifi123",
      "title": "Cara Mengatasi WiFi Lemot",
      "category_name": "WiFi",
      "final_score": 0.65,
      "confidence": "high",
      "matched_intent": 0
    },
    {
      "id": "printer456",
      "title": "Solusi Printer Error P02",
      "category_name": "Printer",
      "final_score": 0.62,
      "confidence": "high",
      "matched_intent": 1
    }
  ],
  "confidence": "high",
  "multi_intent": {
    "detected": true,
    "intents": ["wifi lemot", "printer error"]
  }
}
```

### 1.2 GET /api/chatbot/search (Search Endpoint)

**Request:**
```
GET /api/chatbot/search?q=cara+reset+password+gmail
```

**Response:**
```json
{
  "query": "cara reset password gmail",
  "results": [
    {
      "title": "Cara Reset Password Gmail",
      "category": "Email",
      "excerpt": "Jika Anda lupa password Gmail Anda...",
      "url": "/articles/cara-reset-password-gmail",
      "confidence": "high"
    },
    {
      "title": "Keamanan Akun Email",
      "category": "Security",
      "excerpt": "Untuk menjaga keamanan akun email Anda...",
      "url": "/articles/keamanan-akun-email",
      "confidence": "medium"
    }
  ],
  "total": 2,
  "is_multi_intent": false,
  "intents": []
}
```

---

## 2. TICKETING ENDPOINTS

### 2.1 GET /help (Show Ticket Form)

**Response:** HTML View dengan form dan kategori

### 2.2 POST /tickets (Store Ticket)

**Request:**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "subject": "WiFi tidak bisa konek",
  "message": "WiFi di kantor tidak bisa connect sejak pagi. Sudah coba restart router tapi masih sama. Mohon bantuan.",
  "category_id": "1",
  "captcha": "5432"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Tiket berhasil dibuat. Kami akan segera menghubungi Anda.",
  "ticket_id": "TKT-20260608-001",
  "tracking_token": "abc123def456ghi789",
  "redirect_url": "/tickets/track/abc123def456ghi789"
}
```

**Response (Validation Error):**
```json
{
  "success": false,
  "errors": {
    "email": ["Email harus valid"],
    "message": ["Deskripsi masalah wajib diisi"]
  }
}
```

**Response (Rate Limited):**
```json
{
  "success": false,
  "message": "Terlalu banyak permintaan dari IP ini. Silakan coba lagi dalam 1 jam."
}
```

### 2.3 GET /tickets/track/:token (Track Ticket)

**Response:**
```json
{
  "success": true,
  "ticket": {
    "id": "TKT-20260608-001",
    "subject": "WiFi tidak bisa konek",
    "status": "assigned",
    "priority": "medium",
    "created_at": "2026-06-08 10:30:00",
    "assigned_to": "Budi Santoso (IT Staff)",
    "last_update": "2026-06-08 14:00:00",
    "progress": "Staff sedang mengecek masalah Anda"
  },
  "logs": [
    {
      "timestamp": "2026-06-08 14:00:00",
      "action": "assigned",
      "message": "Tiket ditugaskan ke Budi Santoso"
    },
    {
      "timestamp": "2026-06-08 10:35:00",
      "action": "created",
      "message": "Tiket baru dibuat"
    }
  ]
}
```

### 2.4 Staff: GET /staff/tickets (List Tickets)

**Response:** HTML View dengan tabel tiket

### 2.5 Staff: PATCH /staff/tickets/:id/priority (Update Priority)

**Request:**
```json
{
  "priority": "high"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Prioritas tiket diperbarui",
  "ticket_id": "TKT-20260608-001",
  "new_priority": "high"
}
```

---

## 3. ARTICLE (KNOWLEDGE BASE) ENDPOINTS

### 3.1 Staff: GET /staff/articles (List Articles)

**Response:** HTML View dengan tabel artikel

### 3.2 Staff: POST /staff/articles (Create Article)

**Request:**
```json
{
  "category_id": "1",
  "title": "Cara Mengatasi WiFi Lemot",
  "content": "<p>WiFi yang lemot bisa disebabkan oleh berbagai faktor...</p>",
  "excerpt": "Tips mengatasi WiFi yang lambat dengan langkah-langkah mudah",
  "keywords": "wifi, lemot, lambat, koneksi, jaringan"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Artikel berhasil dibuat",
  "article_id": "article-123",
  "slug": "cara-mengatasi-wifi-lemot",
  "publish_status": "pending",
  "redirect_url": "/staff/articles/article-123/edit"
}
```

### 3.3 Staff: PATCH /staff/articles/:id (Update Article)

**Request:**
```json
{
  "title": "Cara Mengatasi WiFi Lemot (Updated)",
  "content": "<p>Konten artikel yang sudah diperbaharui...</p>",
  "excerpt": "Tips terbaru mengatasi WiFi lambat",
  "keywords": "wifi, lemot, lambat, koneksi, jaringan, router"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Artikel berhasil diperbarui",
  "article_id": "article-123",
  "publish_status": "pending_update"
}
```

### 3.4 Public: GET /articles/:slug (View Article)

**Response:** HTML View dengan konten artikel

---

## 4. AUTHENTICATION ENDPOINTS

### 4.1 GET /login (Show Login Form)

**Response:** HTML View dengan form login

### 4.2 POST /login (Authenticate)

**Request:**
```json
{
  "email": "budi@helpdesk.local",
  "password": "password123"
}
```

**Response (Success - Staff):**
```json
{
  "success": true,
  "message": "Login berhasil",
  "redirect_url": "/staff/dashboard"
}
```

**Response (Success - Admin):**
```json
{
  "success": true,
  "message": "Login berhasil",
  "redirect_url": "/admin/dashboard"
}
```

**Response (Failed):**
```json
{
  "success": false,
  "errors": {
    "email": "Email atau password salah"
  }
}
```

### 4.3 POST /logout (Logout)

**Request:** POST (any data)

**Response (Success):**
```json
{
  "success": true,
  "message": "Logout berhasil",
  "redirect_url": "/"
}
```

**Response (Staff Has Active Ticket):**
```json
{
  "success": false,
  "message": "Anda masih melayani customer aktif. Harap selesaikan sesi live chat sebelum logout.",
  "redirect_url": "/staff/dashboard"
}
```

---

## 5. CONSTANTS & THRESHOLDS

### Confidence Levels
| Score Range | Confidence Level | Action |
|-------------|------------------|--------|
| score ≥ 0.55 | very_high | Tampilkan artikel dengan yakin |
| score ≥ 0.35 | high | Tampilkan artikel normal |
| score ≥ 0.18 | medium | Tampilkan dengan saran hubungi staff |
| score ≥ 0.12 | low | Safe fallback (jangan tampilkan artikel lemah) |
| score < 0.12 | very_low | Escalation (butuh tiket/live chat) |

### Hybrid Ranking Weights
| Factor | Weight | Purpose |
|--------|--------|---------|
| Cosine Similarity | 30% | Kemiripan term-document TF-IDF |
| Title Overlap | 25% | Keyword match di title artikel |
| Domain Match | 15% | Alignment kategori dokumen |
| Query Coverage | 15% | Persentase term query di dokumen |
| Exact Phrase | 10% | Bonus untuk phrase penting |
| Diversification | 5% | Penalti untuk mengurangi duplikasi |

### Escalation & Fallback
| Constant | Value | Meaning |
|----------|-------|---------|
| SIMILARITY_THRESHOLD | 0.12 | Minimum skor untuk menampilkan artikel |
| SAFE_FALLBACK_THRESHOLD | 0.18 | Jika di bawah ini, gunakan safe fallback |
| HIGH_SIMILARITY_THRESHOLD | 0.35 | Skor confidence "high" |
| VERY_HIGH_SIMILARITY_THRESHOLD | 0.55 | Skor confidence "very_high" |
| FAILURE_THRESHOLD | 3 | Jika query gagal 3x, lakukan escalation |

---

**Dokumentasi Payload Dibuat:** 2026-06-08  
**Sumber:** Source Code Implementation
