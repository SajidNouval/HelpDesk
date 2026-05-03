<!-- 
    CHATBOT WIDGET USAGE GUIDE
    
    This is a comprehensive guide on how to use the chatbot widget
    in your Blade templates.
-->

## Installation & Usage

### 1. Basic Implementation
```blade
<!-- Di artikel atau halaman guest -->
<x-chatbot-widget :categories="$categories ?? []" />
```

### 2. Pass Categories dari Controller
```php
// app/Http/Controllers/ArticleController.php
public function publicShow($slug)
{
    $article = Article::with('category', 'staff')
        ->where('slug', $slug)
        ->where('is_published', true)
        ->firstOrFail();
    
    $article->increment('views');
    $categories = Category::all();  // ← Pass ini
    
    return view('articles.show', compact('article', 'categories'));
}
```

### 3. Di Blade Template
```blade
<!-- resources/views/articles/show.blade.php -->
<x-chatbot-widget :categories="$categories ?? []" />
```

---

## Component Props

```blade
@props(['show' => true])
```

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `categories` | Collection | `[]` | List of categories untuk dropdown ticket form |

---

## JavaScript Events

Chatbot widget menggunakan vanilla JavaScript. Events yang bisa di-hook:

```javascript
// Ketika user submit message
// Auto-triggered oleh form submit event

// Ketika chatbot dapat response
// Response ditampilkan otomatis di chat

// Ketika user klik artikel
// Link terbuka di tab baru
```

---

## CSS Classes

Widget menggunakan Tailwind CSS. Customize dengan override:

```css
/* Override warna */
.bg-gradient-to-r.from-blue-600 {
  background: linear-gradient(to right, #your-color, #your-color);
}

/* Override ukuran */
#chatbot-widget {
  width: 24rem; /* Ubah dari w-96 */
}
```

---

## JavaScript API

### Global Variables
```javascript
// Widget element
document.getElementById('chatbot-widget')

// Messages container
document.getElementById('chatbot-messages')

// Input field
document.getElementById('chatbot-input')
```

### Methods (bisa dipanggil dari console)
```javascript
// Manually add message
document.getElementById('chatbot-messages').innerHTML += '...';

// Manually toggle widget
document.getElementById('chatbot-widget').classList.toggle('show');

// Get all messages
document.getElementById('chatbot-messages').innerText;
```

---

## Customization Examples

### 1. Change Widget Position
```blade
<!-- Move ke bottom-left -->
<style>
  #chatbot-widget {
    left: 1rem;
    right: auto;
  }
  
  #chatbot-toggle {
    left: 1rem;
    right: auto;
  }
</style>

<x-chatbot-widget :categories="$categories" />
```

### 2. Change Colors
```blade
<style>
  /* Header color -->
  .bg-gradient-to-r.from-blue-600 {
    @apply bg-gradient-to-r from-green-600 to-green-700;
  }
  
  /* Button color -->
  .bg-blue-600 {
    @apply bg-green-600;
  }
  
  .hover\:bg-blue-700 {
    @apply hover:bg-green-700;
  }
</style>

<x-chatbot-widget :categories="$categories" />
```

### 3. Show on Load
```blade
<x-chatbot-widget :categories="$categories" />

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Auto-open widget
  document.getElementById('chatbot-widget').classList.add('show');
  document.getElementById('chatbot-toggle').classList.add('hide');
});
</script>
```

### 4. Bind to External Events
```blade
<x-chatbot-widget :categories="$categories" />

<script>
// Open chatbot ketika user scroll ke bawah
document.addEventListener('scroll', function() {
  if (window.scrollY > 500) {
    document.getElementById('chatbot-widget').classList.add('show');
    document.getElementById('chatbot-toggle').classList.add('hide');
  }
});
</script>
```

---

## Form Validation

Chatbot widget memvalidasi input secara client-side dan server-side:

### Client-Side (HTML5)
```html
<input type="text" placeholder="..." required>
<input type="email" placeholder="..." >
<select required>...</select>
<textarea required></textarea>
```

### Server-Side (Laravel Validation)
```php
// ChatbotController.php
$request->validate([
    'title' => 'required|string|max:255',
    'message' => 'required|string|max:1000',
    'category_id' => 'required|exists:categories,id',
    'email' => 'email|nullable',
]);
```

---

## Error Handling

Widget menampilkan error message otomatis:

```javascript
// Jika network error
"Maaf, terjadi kesalahan. Silakan coba lagi."

// Jika validasi gagal
// (Server return validation errors)

// Jika kategori tidak ditemukan
// (Dropdown tetap kosong - user perlu pilih)
```

---

## Browser Compatibility

- ✅ Chrome 60+
- ✅ Firefox 55+
- ✅ Safari 12+
- ✅ Edge 79+
- ✅ Mobile (iOS Safari, Chrome Android)

Widget menggunakan:
- Fetch API (no IE support)
- ES6 syntax
- Tailwind CSS

---

## Performance Notes

- Widget widget **tidak auto-load** (lazy load)
- Messages **tidak di-cache** (fresh setiap kali)
- Articles **di-limit 5** per response (performance)
- Input **di-sanitize** server-side (security)

---

## Security

✅ CSRF Protection
- Token included di semua POST requests

✅ Input Validation
- Client-side: HTML5 validation
- Server-side: Laravel validation

✅ SQL Injection Prevention
- Using Laravel Eloquent ORM
- Parameterized queries

✅ XSS Prevention
- Output escaped via Blade {{ }}
- innerHTML hanya untuk trusted content

---

## Troubleshooting

### Widget tidak muncul
```bash
# Check 1: Component registered?
# Blade auto-discovers components di resources/views/components/

# Check 2: Tailwind CSS loaded?
# Pastikan @vite(['resources/css/app.css']) di head

# Check 3: JavaScript error?
# Check browser console (F12 > Console tab)
```

### Chatbot tidak merespon
```bash
# Check 1: Routes registered?
php artisan route:list | grep chatbot

# Check 2: CSRF token valid?
# Check form hidden input name="_token"

# Check 3: Categories exist?
# SELECT * FROM categories;

# Check 4: Chatbot rules exist?
# SELECT * FROM chatbot WHERE is_active = 1;
```

### Tiket tidak tersimpan
```bash
# Check 1: Guest/User table nullable?
# Pastikan user_id nullable di tickets table

# Check 2: Category ID valid?
# SELECT id FROM categories;

# Check 3: Email field in form?
# Check form input name="email"
```

---

## Related Documentation

- [CHATBOT_DOCS.md](./CHATBOT_DOCS.md) - Architecture & API
- [Blade Components](https://laravel.com/docs/11.x/blade#components)
- [Tailwind CSS](https://tailwindcss.com)

---

Generated: 2026-04-20
