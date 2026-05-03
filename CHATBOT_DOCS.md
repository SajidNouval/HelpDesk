# 🤖 Chatbot Rule-Based Helpdesk

## 📌 Overview
Chatbot rule-based yang membantu guest user di halaman artikel untuk:
1. Mencari artikel berdasarkan pertanyaan mereka
2. Memberikan solusi otomatis jika ada artikel yang relevan
3. Jika tidak ada artikel, mengajukan untuk membuat tiket (live support)

---

## 🎯 Fitur Utama

### 1. **Smart Keyword Matching**
```
Scoring System:
- Exact match: 10 poin (user input = keyword)
- Substring match: 5 poin (keyword ada di dalam input)
- Similar match: 3 poin (levenshtein distance <= 2)

Minimum score untuk match: 5
```

### 2. **Intelligent Response**
- Jika ada rule match → tampilkan response + artikel dari category
- Jika no rule → cari artikel lewat semantic search
- Jika masih no hasil → suggest membuat tiket

### 3. **Two-Way Integration**
- Di laman artikel: Chatbot popup membantu mencari artikel lain
- Untuk guest: Dapat membuat tiket langsung dari chatbot (live support)

---

## 🏗️ Struktur File

### Backend
```
app/Http/Controllers/ChatbotController.php
├─ getResponse()              // Get bot response & articles
├─ createTicketAndMessage()   // Create tiket dari chatbot
├─ sendMessage()              // Add message ke tiket
└─ getTicketMessages()        // Get chat history

app/Models/Chatbot.php
├─ category()                 // Relasi ke Category
├─ scopeActive()             // Filter aktif saja
└─ getKeywordsArray()        // Helper parse keywords
```

### Frontend
```
resources/views/components/chatbot-widget.blade.php
├─ Chat input form
├─ Message display area
├─ Articles suggestion
├─ Ticket creation form
└─ JavaScript untuk handle interaksi

resources/views/articles/show.blade.php
└─ Include chatbot widget via component
```

### Routes
```
POST   /chatbot/get-response      // Get bot response
POST   /chatbot/create-ticket     // Create tiket
POST   /chatbot/send-message      // Send message
GET    /chatbot/ticket/{id}/messages  // Get messages
```

---

## 📊 Database Structure

### Table: `chatbot`
```sql
columns:
  - id (primary key)
  - keywords (string, comma-separated)
  - response (text, bot jawaban)
  - category_id (foreign key, nullable)
  - is_active (boolean, default: true)
  - priority (integer, default: 0)
  - timestamps
```

**Example Data:**
```
keywords: "wifi,internet,lemot,sinyal"
response: "Kami memiliki beberapa artikel tentang WiFi..."
category_id: 1 (WiFi & Internet)
priority: 100
is_active: true
```

---

## 🚀 Cara Penggunaan

### 1. Setup Database
```bash
# Jalankan migration (jika belum)
php artisan migrate

# Seed chatbot rules
php artisan db:seed --class=ChatbotSeeder
```

### 2. Tambah Rule Baru
```php
// Di admin panel atau via database
Chatbot::create([
    'keywords' => 'password,login,akses',
    'response' => 'Kami memiliki panduan untuk masalah password...',
    'category_id' => 6,  // Password & Akses category
    'priority' => 99,
    'is_active' => true,
]);
```

### 3. Gunakan di View
```blade
<x-chatbot-widget :categories="$categories" />
```

---

## 🔄 User Flow

```
┌─ Guest buka artikel
│
├─ Klik chat bubble
│
├─ Ketik pertanyaan
│  └─ POST /chatbot/get-response
│     ├─ Match rules (scoring)
│     ├─ Return response + articles
│     └─ Display to user
│
├─ User pilih artikel ✓
│  └─ Buka artikel (external)
│
└─ Tidak ada hasil yang cocok
   ├─ Show ticket creation form
   ├─ User input masalah & email
   │  └─ POST /chatbot/create-ticket
   │     ├─ Create tiket (status: open)
   │     ├─ Add user message
   │     ├─ Add bot message
   │     └─ Show success
   └─ Redirect ke help/support
```

---

## 🎨 UI/UX

### Chatbot Widget
- **Floating button** di bottom-right
- **Modal popup** dengan gradient header (blue)
- **Chat bubbles** - user (blue), bot (light blue)
- **Article links** - clickable suggestions
- **Responsive** - works on mobile & desktop
- **Tailwind CSS** - styling built-in

### States
1. **Initial** - Greeting message
2. **Searching** - Loading state
3. **Found Articles** - Display hasil
4. **No Results** - Show ticket form
5. **Success** - Ticket created confirmation

---

## 🔧 API Endpoints

### 1. Get Chatbot Response
```http
POST /chatbot/get-response
Content-Type: application/json

{
  "message": "wifi saya lambat"
}

Response:
{
  "success": true,
  "response": "Kami memiliki beberapa artikel...",
  "articles": [
    {
      "id": 1,
      "title": "Cara Fix WiFi Lambat",
      "slug": "cara-fix-wifi-lambat",
      "views": 42
    }
  ],
  "rule_id": 3
}
```

### 2. Create Ticket
```http
POST /chatbot/create-ticket
Content-Type: application/json

{
  "title": "WiFi tidak terkoneksi",
  "message": "Penjelasan detail masalah...",
  "category_id": 1,
  "email": "user@example.com"
}

Response:
{
  "success": true,
  "ticket_id": 42,
  "message": "Tiket berhasil dibuat..."
}
```

### 3. Get Messages
```http
GET /chatbot/ticket/42/messages

Response:
{
  "success": true,
  "messages": [...],
  "ticket": {
    "id": 42,
    "title": "WiFi tidak terkoneksi",
    "status": "open"
  }
}
```

---

## ⚙️ Configuration

### Priority Tuning
Edit `database/seeders/ChatbotSeeder.php` untuk adjust priority:
```php
'priority' => 100,  // Higher = checked first
```

### Minimum Score Threshold
Edit `app/Http/Controllers/ChatbotController.php`:
```php
// Line ~60
return $bestScore >= 5 ? $bestMatch : null;
```

### Levenshtein Distance
Edit `app/Http/Controllers/ChatbotController.php`:
```php
// Line ~85
private function isSimilar(..., int $threshold = 2): bool
```

---

## 🧪 Testing Checklist

- [ ] Exact match - user input = keyword
- [ ] Substring match - keyword dalam input
- [ ] Multiple keywords - test OR logic
- [ ] Similar words - typo tolerance
- [ ] No match - fallback to search
- [ ] Article display - correct category
- [ ] Ticket creation - save to database
- [ ] Email field - optional handling
- [ ] Mobile responsive - test on small screens
- [ ] Error handling - network errors

---

## 📝 Sample Chatbot Rules

```
1. WiFi Problems
   Keywords: wifi,internet,lemot,putus,sinyal
   Category: WiFi & Internet
   Priority: 100

2. Printer Issues
   Keywords: printer,print,cetak,scanner,tinta
   Category: Printer & Scanner
   Priority: 95

3. Password Reset
   Keywords: password,login,lupa,reset
   Category: Passwords & Akses
   Priority: 99

4. Hardware
   Keywords: monitor,keyboard,mouse,hardware
   Category: Hardware & Komputer
   Priority: 80
```

---

## 🐛 Troubleshooting

### Chatbot tidak muncul
- [ ] Check: `<x-chatbot-widget />` di view
- [ ] Check: JavaScript ada di bottom
- [ ] Check: CSRF token valid

### Response tidak muncul
- [ ] Check: Categories exist di database
- [ ] Check: Chatbot rules is_active = true
- [ ] Check: Network tab untuk error

### Tiket tidak tersimpan
- [ ] Check: Guest table & relationships
- [ ] Check: Category IDs valid
- [ ] Check: Email field nullable

---

## 📈 Future Improvements

- [ ] Add sentiment analysis
- [ ] ML-based keyword matching
- [ ] Multi-language support
- [ ] Analytics dashboard
- [ ] A/B testing rules
- [ ] Auto-escalation to staff
- [ ] Chat transcript export
- [ ] Integration dengan WhatsApp/Telegram

---

## 📞 Support

Untuk issue atau pertanyaan, buat tiket atau hubungi tim development.
