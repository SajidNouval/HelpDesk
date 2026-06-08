# Panduan Penggunaan safeFetch dan safeJson

## Overview

Sistem ini memiliki utility functions `safeFetch` dan `safeJson` yang tersedia secara global melalui `window.safeFetch` dan `window.safeJson`. Fungsi-fungsi ini dirancang untuk menangani response JSON dengan aman dan mencegah error saat parsing.

## Implementasi

### safeJson(response)
```javascript
export async function safeJson(response) {
    const contentType = response.headers.get('content-type');
    
    if (!contentType?.includes('application/json')) {
        return null;
    }
    
    try {
        return await response.json();
    } catch {
        return null;
    }
}
```

### safeFetch(url, options)
```javascript
export async function safeFetch(url, options = {}) {
    try {
        const response = await fetch(url, options);
        const data = await safeJson(response);
        
        return {
            ok: response.ok,
            status: response.status,
            data,
        };
    } catch (error) {
        console.error(error);
        
        return {
            ok: false,
            status: 500,
            data: null,
        };
    }
}
```

## Ketersediaan Global

Fungsi-fungsi ini diekspor ke `window` melalui `resources/js/bootstrap.js`:

```javascript
import { safeFetch, safeJson } from './utils/http';

window.safeFetch = safeFetch;
window.safeJson = safeJson;
```

## Cara Penggunaan

### Untuk Kode BARU (WAJIB)

Semua kode JavaScript baru HARUS menggunakan `safeFetch` atau `window.safeJson`:

#### Menggunakan safeFetch (Rekomendasi)
```javascript
// Inline script di Blade
const response = await window.safeFetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    },
    body: JSON.stringify(data)
});

if (response.ok && response.data) {
    // Handle success
    console.log(response.data);
} else {
    // Handle error
    console.error('Error:', response.status);
}
```

#### Menggunakan window.safeJson
```javascript
const response = await fetch('/api/endpoint', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    },
    body: JSON.stringify(data)
});

const data = await window.safeJson(response) || {};

if (data.success) {
    // Handle success
}
```

### Untuk Kode LAMA (Refactor Bertahap)

Kode lama yang menggunakan `response.json()` secara langsung harus direfactor secara bertahap:

#### Sebelum (TIDAK AMAN)
```javascript
const response = await fetch('/api/endpoint');
const data = await response.json(); // Bisa throw error jika bukan JSON
```

#### Sesudah (AMAN)
```javascript
const response = await fetch('/api/endpoint');
const data = await window.safeJson(response) || {}; // Aman, return null jika gagal
```

Atau lebih baik lagi:
```javascript
const response = await window.safeFetch('/api/endpoint');
const data = response.data || {}; // Sudah ter-handle errornya
```

## File yang Perlu Direfactor

Berdasarkan analisis kode, berikut file yang masih menggunakan `response.json()` langsung:

### Priority 1 (High - Banyak fetch calls)
1. `resources/views/components/articles-chat-bubble.blade.php` - 10+ tempat
2. `resources/views/articles/index.blade.php` - 2 tempat
3. `resources/views/guest/help.blade.php` - 2 tempat

### Priority 2 (Medium)
4. `resources/views/staff/tickets/show.blade.php` - Beberapa tempat (sudah ada yang pakai safeJson)

### Sudah OK
- `resources/views/components/chatbot-widget.blade.php` - Sudah menggunakan `window.safeJson`

## Best Practices

1. **Selalu gunakan `window.safeJson`** saat parsing response JSON dari `fetch()`
2. **Gunakan `window.safeFetch`** untuk error handling yang lebih komprehensif
3. **Berikan fallback value** dengan `|| {}` atau `|| []` setelah `safeJson`
4. **Cek `response.ok`** sebelum mengakses data
5. **Handle null cases** karena `safeJson` bisa return `null`

## Contoh Pattern yang Benar

```javascript
// Pattern 1: Menggunakan safeFetch (paling aman)
const result = await window.safeFetch(url, options);
if (result.ok && result.data) {
    // Success handling
} else {
    // Error handling
}

// Pattern 2: Menggunakan safeJson dengan fetch biasa
const response = await fetch(url, options);
const data = await window.safeJson(response) || {};
if (response.ok && data) {
    // Success handling
}

// Pattern 3: Dengan fallback
const messages = await window.safeJson(response) || [];
const config = await window.safeJson(response) || {};
```

## Checklist Refactoring

- [ ] Ganti semua `response.json()` dengan `window.safeJson(response)`
- [ ] Tambahkan fallback value (`|| {}` atau `|| []`)
- [ ] Pertimbangkan menggunakan `window.safeFetch` untuk error handling yang lebih baik
- [ ] Test setiap perubahan untuk memastikan tidak ada regression

## Catatan Penting

- `safeJson` akan return `null` jika:
  - Response bukan JSON (content-type tidak sesuai)
  - Terjadi error saat parsing JSON
- Selalu sediakan fallback value untuk menghindari error `Cannot read property of null`
- Untuk new code, WAJIB menggunakan salah satu dari `safeFetch` atau `safeJson`