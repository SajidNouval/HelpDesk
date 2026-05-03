# 🚀 Chatbot Quick Start

## Install & Deploy

### 1️⃣ Run Database
```bash
# Migration sudah ada (2026_04_19_162604_chatbot.php)
php artisan migrate

# Seed chatbot rules
php artisan db:seed --class=ChatbotSeeder
```

### 2️⃣ Add to View
```blade
<!-- Di resources/views/articles/show.blade.php atau halaman guest lain -->
<x-chatbot-widget :categories="$categories ?? []" />
```

### 3️⃣ Verify Routes
```bash
php artisan route:list | grep chatbot
```

Harus ada 4 routes:
- `POST /chatbot/get-response`
- `POST /chatbot/create-ticket`
- `POST /chatbot/send-message`
- `GET /chatbot/ticket/{ticket}/messages`

---

## 📝 Tambah Rule Baru

**Method 1: Database Direct**
```sql
INSERT INTO chatbot (keywords, response, category_id, priority, is_active, created_at, updated_at) 
VALUES ('keyword1,keyword2,keyword3', 'Bot response text', 1, 100, 1, NOW(), NOW());
```

**Method 2: Tinker**
```bash
php artisan tinker

>>> App\Models\Chatbot::create([
...   'keywords' => 'password,login,reset',
...   'response' => 'Kami memiliki panduan untuk reset password...',
...   'category_id' => 6,
...   'priority' => 99,
... ])
```

**Method 3: Admin Panel** (jika ada)
- Buat CRUD untuk Chatbot model

---

## 🎯 How It Works

```
User ketik: "WiFi saya lambat"
    ↓
Controller->getResponse() cari rule
    ↓
Match "wifi,internet,lemot" → score 10 + 5 = 15 ✅
    ↓
Return rule response + articles dari category WiFi
    ↓
User lihat response + artikel terkait
    ↓
Klik artikel (open di tab baru)
```

---

## 🔍 Scoring System

```
Exact match   → 10 poin  ("wifi" = user input "wifi")
Substring     → 5 poin   ("wifi" ada di "wifi saya lambat")
Similar       → 3 poin   ("wift" mirip "wifi" - typo tolerance)
                         
Total ≥ 5 = Match ✅
```

---

## ⚙️ File Structure

```
✅ DONE:
  app/Http/Controllers/ChatbotController.php  (125 lines)
  app/Models/Chatbot.php                      (updated with scopes)
  routes/web.php                              (4 new routes)
  resources/views/components/chatbot-widget.blade.php  (complete UI)
  database/seeders/ChatbotSeeder.php         (sample rules)
  
📚 DOCS:
  CHATBOT_DOCS.md                            (full documentation)
  CHATBOT_WIDGET_USAGE.md                    (component guide)
  CHATBOT_QUICKSTART.md                      (this file)
```

---

## 🧪 Test It!

```javascript
// Open browser console (F12)
// Buka laman artikel atau help page

// Test 1: Get response
fetch('/chatbot/get-response', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': document.querySelector('[name="_token"]').value
  },
  body: JSON.stringify({ message: 'wifi saya lambat' })
})
.then(r => r.json())
.then(d => console.log(d))

// Test 2: Manual widget toggle
document.getElementById('chatbot-widget').classList.toggle('show')
```

---

## 🐛 Debug Tips

### Check if routes registered
```bash
php artisan route:list | grep chatbot
# Harus output 4 routes
```

### Check database
```bash
php artisan tinker
>>> App\Models\Chatbot::count()
>>> App\Models\Chatbot::first()
```

### Check browser console
```javascript
// F12 > Console tab
// Lihat error messages
// Check Network tab untuk API response
```

### Check logs
```bash
tail -f storage/logs/laravel.log
```

---

## 🎨 Customize UI

### Change Colors
Edit `chatbot-widget.blade.php` line dengan `bg-blue-`:
```blade
from-blue-600 to-blue-700     → your colors
bg-blue-100 text-blue-900     → your colors
```

### Change Size
Edit `chatbot-widget.blade.php` line:
```blade
w-96 h-[600px]    → your width/height
max-w-xs          → your max-width
```

### Change Position
Edit `chatbot-widget.blade.php`:
```blade
<!-- From bottom-right -->
<div id="chatbot-widget" class="fixed bottom-4 right-4 ...">

<!-- To bottom-left -->
<div id="chatbot-widget" class="fixed bottom-4 left-4 ...">
```

---

## 📞 API Response Examples

### Success with articles
```json
{
  "success": true,
  "response": "Kami memiliki beberapa artikel...",
  "articles": [
    { "id": 1, "title": "Fix WiFi", "slug": "fix-wifi" }
  ]
}
```

### No match - suggest ticket
```json
{
  "success": false,
  "response": "Maaf, kami belum memiliki artikel...",
  "articles": [],
  "suggest_ticket": true
}
```

### Ticket created
```json
{
  "success": true,
  "ticket_id": 42,
  "message": "Tiket berhasil dibuat..."
}
```

---

## ✨ Next Steps

- [ ] Test semua scenario
- [ ] Adjust rules berdasarkan user feedback
- [ ] Setup admin panel untuk manage rules
- [ ] Monitor chatbot performance
- [ ] Add analytics tracking (optional)

---

**Created:** 2026-04-20
**Status:** ✅ Ready to Use
