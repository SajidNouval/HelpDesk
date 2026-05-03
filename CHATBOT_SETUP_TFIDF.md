# 🚀 CHATBOT INTELLIGENT SEARCH SYSTEM - SETUP GUIDE

## ✅ FILE YANG SUDAH DIBUAT

Semua file telah berhasil dibuat dan siap digunakan:

### 1. **Model**
- [app/Models/ArticleKeywordIndex.php](app/Models/ArticleKeywordIndex.php) — Model untuk menyimpan keyword index

### 2. **Migration**
- [database/migrations/2026_04_21_100000_create_article_keyword_index_table.php](database/migrations/2026_04_21_100000_create_article_keyword_index_table.php) — Tabel article_keyword_index

### 3. **Observer**
- [app/Observers/ArticleObserver.php](app/Observers/ArticleObserver.php) — Auto-indexing saat artikel dibuat/update/hapus

### 4. **Service**
- [app/Services/ArticleSearchService.php](app/Services/ArticleSearchService.php) — TF-IDF Scoring engine

### 5. **Command**
- [app/Console/Commands/ReindexChatbotArticles.php](app/Console/Commands/ReindexChatbotArticles.php) — Build index pertama kali

### 6. **Provider (UPDATED)**
- [app/Providers/AppServiceProvider.php](app/Providers/AppServiceProvider.php) — Daftarkan ArticleObserver

---

## 📋 LANGKAH-LANGKAH SETUP

### **Step 1: Jalankan Migrasi**

```bash
php artisan migrate
```

Output yang diharapkan:
```
Migrating: 2026_04_21_100000_create_article_keyword_index_table
Migrated: 2026_04_21_100000_create_article_keyword_index_table (0.12s)
```

### **Step 2: Build Index Pertama Kali**

Setelah migrasi berhasil, index semua artikel yang sudah ada:

```bash
php artisan chatbot:reindex
```

Output yang diharapkan:
```
🔍 Memulai re-indexing artikel untuk chatbot...
📄 Ditemukan 15 artikel yang dipublikasikan.
███████████████████████████████ 15/15 [====] 100% 2 secs

✅ Re-indexing selesai!
   Berhasil: 15
   Gagal: 0
```

### **Step 3: (OPSIONAL) Gunakan Stemmer yang Lebih Akurat**

Untuk hasil stemming yang lebih baik, install Sastrawi:

```bash
composer require sastrawi/sastrawi
```

Kemudian, ganti method `stem()` di `ArticleSearchService.php` dengan:

```php
private function stem(string $word): string
{
    static $stemmer = null;
    if ($stemmer === null) {
        $stemmer = (new \Sastrawi\Stemmer\StemmerFactory())->createStemmer();
    }
    return $stemmer->stem($word);
}
```

---

## 🔄 CARA KERJA AUTO-LEARNING

Setelah setup selesai, sistem otomatis akan:

1. **Artikel Baru di-Publish** → ArticleObserver terdeteksi → Index dibuat
2. **Artikel Di-Update** → ArticleObserver terdeteksi → Index di-refresh
3. **Artikel Di-Hapus** → ArticleObserver terdeteksi → Index dihapus

Tidak perlu menjalankan command lagi! Chatbot langsung bisa menemukan artikel baru.

---

## 📊 ALUR SEARCH DENGAN TF-IDF SCORING

```
Query User: "wifi tidak bisa konek"
       ↓
[Normalisasi: lowercase, hapus stopwords, stemming]
       ↓
Terms: ["wifi", "konek"]
       ↓
[Cari di article_keyword_index]
       ↓
Hitung TF-IDF Score:
  - TF (Term Frequency) = seberapa sering term muncul
  - IDF (Inverse Document Frequency) = log(total_docs / docs_with_term)
  - Field Boost = title × 3, content × 1
  - Coverage Bonus = artikel dengan lebih banyak term match
       ↓
[Ambil artikel dengan score tertinggi]
       ↓
Return ke Frontend
```

---

## 🎯 PIPELINE CHATBOT

```
User Message: "tidak bisa login wifi"
       ↓
1. Normalize Input
       ↓
2. Exact Phrase Match? ("lupa password", "wifi mati", dst.)
   → Jika ada: Return quick response
       ↓
3. Chatbot Rule? (dari tabel chatbots)
   → Jika ada: Return rule response + articles
       ↓
4. TF-IDF Article Search (NEW!)
   → Gunakan ArticleSearchService dengan scoring
   → Fallback ke FULLTEXT jika TF-IDF tidak menemukan
   → Fallback ke LIKE jika FULLTEXT gagal
       ↓
5. Jika semua gagal: Tampilkan tombol "Hubungi Staff"
```

---

## 🔧 TROUBLESHOOTING

### Migration Error?

```bash
# Jika ada error unique constraint
php artisan migrate:rollback
php artisan migrate
```

### Command tidak terdeteksi?

Laravel 11+ otomatis menemukan commands. Jika menggunakan Laravel 10 atau lebih lama:

1. Buka `app/Console/Kernel.php`
2. Tambahkan di `protected $commands`:

```php
protected $commands = [
    \App\Console\Commands\ReindexChatbotArticles::class,
];
```

### Index tidak ter-update saat publish artikel?

Pastikan Observer terdaftar di `app/Providers/AppServiceProvider.php`:

```php
public function boot(): void
{
    Article::observe(ArticleObserver::class);
}
```

### Performance lambat?

- Pastikan tabel `article_keyword_index` punya index:
  ```sql
  SELECT * FROM information_schema.STATISTICS 
  WHERE TABLE_NAME = 'article_keyword_index';
  ```

- Jika banyak artikel (>1000), pertimbangkan pagination atau filter by category

---

## 📈 MONITORING

Monitor artikel yang ter-index:

```bash
# Via Tinker
php artisan tinker
> App\Models\ArticleKeywordIndex::count()  // Total keywords
> App\Models\ArticleKeywordIndex::distinct('article_id')->count() // Jumlah artikel ter-index
```

---

## 🎓 IMPROVEMENTS DARI SEBELUMNYA

| Masalah Lama | Solusi Baru |
|---|---|
| ❌ Hardcoded phrases | ✅ TF-IDF Smart Scoring |
| ❌ Exact keyword match saja | ✅ Term Frequency + IDF + Coverage bonus |
| ❌ Tidak ada auto-learning | ✅ ArticleObserver otomatis index |
| ❌ Tidak ada stemming ID | ✅ Simple Indonesian Stemmer |
| ❌ Cache TF-IDF | ✅ Cache IDF scores 24 jam |

---

## 📞 SUPPORT

Jika ada pertanyaan atau error, cek:

1. `storage/logs/laravel.log` — error details
2. `app/Services/ArticleSearchService.php` — TF-IDF logic
3. `app/Observers/ArticleObserver.php` — auto-indexing logic

**Selesai! 🎉 Chatbot Anda sekarang cerdas dan bisa belajar otomatis dari artikel yang dipublikasikan.**
