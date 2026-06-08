# 🧠 CHATBOT TF-IDF SYSTEM - TECHNICAL DOCUMENTATION

## 📁 FILES YANG SUDAH DIBUAT

```
app/
├── Models/
│   └── ArticleKeywordIndex.php          ← Model untuk keyword index
├── Observers/
│   └── ArticleObserver.php              ← Auto-indexing trigger
├── Services/
│   └── ArticleSearchService.php         ← TF-IDF search engine
└── Console/
    └── Commands/
        └── ReindexChatbotArticles.php   ← CLI untuk build index
database/
└── migrations/
    └── 2026_04_21_100000_create_article_keyword_index_table.php
```

---

## 🔍 HOW IT WORKS - STEP BY STEP

### **1️⃣ SAAT STAFF PUBLISH ARTIKEL**

**Trigger:** Model `Article` event `created` atau `updated`

**Process:**
```
Article::create(['title' => 'Cara Reset WiFi', 'content' => '...', 'is_published' => true])
        ↓
ArticleObserver::created() dipanggil
        ↓
ArticleSearchService::indexArticle($article) dijalankan
        ↓
Ekstrak terms dari title & content:
  - Title: "Cara Reset WiFi" → ["cara", "reset", "wifi"]
  - Content: "... router ... modem ..." → ["router", "modem", ...]
        ↓
Hitung Term Frequency (TF):
  - Per field dengan bobot: title×3, content×1
        ↓
Insert ke article_keyword_index:
  {article_id: 5, keyword: 'wifi', tf: 0.15, field_boosts: {'title': 0.33, 'content': 0}}
  {article_id: 5, keyword: 'reset', tf: 0.12, field_boosts: {'title': 0.33, 'content': 0}}
  ...
        ↓
Cache IDF scores di-bust
```

---

### **2️⃣ USER MENGIRIM PERTANYAAN**

**Input:** "Wifi saya tidak bisa konek"

**Process:**
```
ChatbotController::getResponse($request)
        ↓
1. Normalize: 
   "wifi saya tidak bisa konek" → "wifi konek"
   (hapus stopwords: saya, tidak, bisa)
        ↓
2. Exact Phrase Match? 
   Cek hardcoded phrases ["lupa password", "wifi mati", ...]
   → Tidak ada match
        ↓
3. Chatbot Rule?
   Cek tabel chatbots dengan keywords
   → Tidak ada match
        ↓
4. ArticleSearchService::search("wifi konek")
   
   a) Extract terms: ["wifi", "konek"]
      (stemming: "konek" tetap, "wifi" tetap)
   
   b) searchTfIdf():
      - Cari di ArticleKeywordIndex WHERE keyword IN ["wifi", "konek"]
      - Hasil:
        • Article#5: keyword="wifi", tf=0.15
        • Article#5: keyword="konek", tf=0.08
        • Article#8: keyword="konek", tf=0.12
   
   c) Hitung IDF scores:
      total_articles = 50
      - "wifi": docs_with_term=8 → IDF = log(50/8) ≈ 1.83
      - "konek": docs_with_term=15 → IDF = log(50/15) ≈ 1.20
   
   d) Score per artikel:
      Article#5:
        score = (0.15 × 1.83) + (0.08 × 1.20) = 0.367
        matched_terms = 2
        coverage_bonus = (2/2) × 0.2 = 0.2
        final_score = 0.367 + (0.367 × 0.2) = 0.44
      
      Article#8:
        score = (0.12 × 1.20) = 0.144
        matched_terms = 1
        coverage_bonus = (1/2) × 0.2 = 0.1
        final_score = 0.144 + (0.144 × 0.1) = 0.158
   
   e) Sort by score: Article#5 (0.44) > Article#8 (0.158)
   
   f) Return Article#5 dengan title "Cara Reset WiFi"
        ↓
5. Return ke Frontend:
   {
     success: true,
     response: "Saya menemukan artikel yang mungkin membantu: **Cara Reset WiFi**",
     articles: [{id: 5, title: "Cara Reset WiFi", ...}]
   }
```

---

## 📊 DATABASE SCHEMA

### `article_keyword_index` Table

```sql
CREATE TABLE article_keyword_index (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    article_id BIGINT NOT NULL,
    keyword VARCHAR(255) NOT NULL,
    tf FLOAT NOT NULL,
    field_boosts JSON NOT NULL DEFAULT '{}',
    UNIQUE KEY (article_id, keyword),
    INDEX (keyword, article_id),
    FOREIGN KEY (article_id) REFERENCES articles(id) ON DELETE CASCADE
);
```

**Contoh Data:**
```sql
+-----+------------+-----------+-------+---------------------------------------+
| id  | article_id | keyword   | tf    | field_boosts                          |
+-----+------------+-----------+-------+---------------------------------------+
| 1   | 5          | wifi      | 0.15  | {"title": 0.33, "content": 0}        |
| 2   | 5          | reset     | 0.12  | {"title": 0.33, "content": 0}        |
| 3   | 5          | router    | 0.08  | {"title": 0, "content": 0.25}       |
| 4   | 8          | konek     | 0.12  | {"title": 0, "content": 0.12}       |
+-----+------------+-----------+-------+---------------------------------------+
```

---

## 🧮 TF-IDF FORMULA

```
TF (Term Frequency) per artikel:
  TF(term, article) = count(term) / total_words_in_article
  
  Dengan field boosts:
  TF_FINAL = TF_title × 3 + TF_content × 1

IDF (Inverse Document Frequency):
  IDF(term) = log(total_documents / documents_with_term)
  
  Contoh: 50 articles total, 8 punya keyword "wifi"
  IDF("wifi") = log(50 / 8) = 1.83

TF-IDF Score:
  SCORE(term, article) = TF(term, article) × IDF(term)
  
  ARTICLE_SCORE = Σ SCORE(term, article)
                + coverage_bonus
  
  coverage_bonus = (matched_terms / query_terms) × 0.2
```

---

## 🎯 STEMMING LOGIC

Indonesian stemmer yang sederhana:

```php
// Input: "terkoneksi"
// Output: "konek"

Prefixes to remove: me, di, ter, ke, be, pe
  "terkoneksi" → remove "ter" → "koneksi"

Suffixes to remove: kan, an, i, nya, lah, tah
  "koneksi" → remove "i" → "koneksi" (tidak cocok)
  "koneksi" → remove "i" (akhir) → "konekssi" (invalid)
  
  Final: "koneksi" atau stemmed ke "konek"

// Hasil: "konek", "koneksi", "terkoneksi" → semua ter-match!
```

---

## 🚀 PERFORMANCE NOTES

### Index Size
- Per keyword: ~50 bytes (ID + keyword + TF + field_boosts)
- 1000 articles × 100 keywords = ~5MB

### Query Performance
- TF-IDF search: **~10-50ms** (artikel keyword_index indexed)
- FULLTEXT fallback: **~20-100ms**
- LIKE fallback: **~100-500ms** (tergantung artikel count)

### Caching
- IDF scores di-cache 24 jam di Redis/File
- Cache di-bust otomatis saat article publish/update/delete

---

## 🔧 CUSTOMIZATION

### Mengubah Field Boosts

File: `app/Services/ArticleSearchService.php`

```php
// Sekarang: title × 3
// Ubah di method indexArticle():
$tfTotal = ($tfTitle * 5) + ($tfContent * 1);  // Title lebih penting
```

### Mengubah Coverage Bonus

```php
// Sekarang: 0.2 (20% bonus)
// Ubah di method searchTfIdf():
$coverageBonus = ($matchedTerms / count($queryTerms)) * 0.5;  // 50% bonus
```

### Menambah Stopwords

```php
private function getStopwords(): array
{
    return [
        // Existing stopwords...
        'email', 'contact', 'hubungi',  // Add custom ones
    ];
}
```

### Mengubah Stemmer

Ganti method `stem()` dengan Sastrawi (lebih akurat):

```bash
composer require sastrawi/sastrawi
```

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

## 📚 EXAMPLE QUERIES

### Query 1: "koneksi internet putus"

```
Terms: ["koneksi", "internet"]
Terms after stemming: ["konek", "internet"]

ArticleKeywordIndex::whereIn('keyword', ['konek', 'internet'])
  → Returns articles with "konek" or "internet"

TF-IDF Scoring:
  Article#5 (title: "Cara Mengatasi Koneksi WiFi Putus"):
    score = (0.15 × 1.83) + (0.10 × 1.50) = 0.42

  Article#12 (title: "Internet Lambat Solusi Terbaik"):
    score = (0.08 × 1.50) = 0.12

Result: Article#5 > Article#12
```

### Query 2: "login gagal"

```
Terms: ["login", "gagal"]
After stopwords: ["login", "gagal"]

TF-IDF search → FULLTEXT → LIKE fallback
  → Finds articles about login errors

Result: Multiple articles ranked by score
```

---

## ✅ CHECKLIST UNTUK PRODUCTION

- [ ] `php artisan migrate` sudah dijalankan
- [ ] `php artisan chatbot:reindex` sudah dijalankan
- [ ] Observer terdaftar di AppServiceProvider
- [ ] Artikel sudah ter-index di article_keyword_index
- [ ] Cache warming untuk IDF scores
- [ ] Monitoring IDF cache expiration
- [ ] Backup tabel article_keyword_index

---

## 🐛 DEBUGGING

### Check IDF Scores

```bash
php artisan tinker
> Cache::get('chatbot:idf_scores')
```

### Check Indexed Keywords

```sql
SELECT DISTINCT keyword, COUNT(*) as count 
FROM article_keyword_index 
GROUP BY keyword 
ORDER BY count DESC 
LIMIT 20;
```

### Test Search

```bash
php artisan tinker
> $service = app(\App\Services\ArticleSearchService::class);
> $results = $service->search('wifi konek', 5);
> $results->pluck('title');
```
