# CHATBOT SYSTEM ANALYSIS - SISTEM HELPMINFO

Dokumentasi ini berisi analisis menyeluruh sistem chatbot HelpMinfo berdasarkan source code asli.

---

# 1. CHATBOT QUERY PROCESSING

## Tujuan Fitur
Memproses query pengguna dan mengembalikan artikel yang relevan menggunakan hybrid retrieval (Typesense + TF-IDF).

## Alur Bisnis
1. Pengguna mengirim query ke chatbot
2. Sistem normalisasi query (koreksi typo, tokenisasi)
3. Sistem deteksi domain (wifi, internet, printer, dll)
4. Sistem cek apakah query adalah greeting
5. Sistem cek apakah query membutuhkan klarifikasi
6. Sistem lakukan retrieval artikel via Typesense (85% bobot)
7. Sistem lakukan TF-IDF reranking (15% bobot)
8. Sistem terapkan diversifikasi hasil
9. Sistem format response dengan artikel dan jawaban chatbot
10. Sistem simpan conversation memory
11. Sistem return response JSON

## Route Yang Digunakan

**File:** `routes/web.php`
**Line:** 140-150

```php
Route::post('/chatbot/response', [ChatbotController::class, 'getResponse'])->name('chatbot.response');
Route::post('/chatbot/escalate', [ChatbotController::class, 'escalateToTicket'])->name('chatbot.escalate');
Route::post('/chatbot/feedback', [ChatbotController::class, 'submitFeedback'])->name('chatbot.feedback');
Route::post('/chatbot/clear-cache', [ChatbotController::class, 'clearCache'])->name('chatbot.clear-cache');
Route::get('/chatbot/search', [ChatbotController::class, 'search'])->name('chatbot.search');
```

**Penjelasan:**
- `POST /chatbot/response`: Mendapatkan response chatbot untuk query
- `POST /chatbot/escalate`: Eskalasi ke tiket jika chatbot tidak membantu
- `POST /chatbot/feedback`: Submit feedback artikel
- `POST /chatbot/clear-cache`: Clear cache chatbot
- `GET /chatbot/search`: Pencarian artikel manual

## Controller Yang Dipanggil

**File:** `app/Http/Controllers/ChatbotController.php`
**Line:** 65-879 (getResponse method)

```php
public function getResponse(Request $request)
{
    $request->validate([
        'message' => 'required|string|max:500',
    ]);

    $userMessage = trim($request->message);
    $retrievalService = app(AdvancedRetrievalService::class);

    // Cek greeting
    if ($retrievalService->isGreeting($userMessage)) {
        $retrievalService->clearConversationMemory();
        return response()->json([
            'answer' => $retrievalService->getGreetingResponse(),
            'articles' => [],
            'is_greeting' => true,
            'needs_clarification' => false,
            'diversification_info' => null,
        ]);
    }

    // Cek klarifikasi
    if ($retrievalService->needsClarification($userMessage)) {
        $clarificationDomains = $retrievalService->getClarificationDomains($userMessage);
        return response()->json([
            'answer' => $retrievalService->getClarificationResponse($clarificationDomains),
            'articles' => [],
            'is_greeting' => false,
            'needs_clarification' => true,
            'clarification_domains' => $clarificationDomains,
            'diversification_info' => null,
        ]);
    }

    // Retrieve artikel
    $result = $retrievalService->retrieve($userMessage, 5);

    if (empty($result['articles'])) {
        return response()->json([
            'answer' => 'Maaf, saya tidak menemukan artikel yang relevan untuk pertanyaan Anda. Silakan coba dengan kata kunci yang lebih spesifik atau eskalasi ke live chat.',
            'articles' => [],
            'is_greeting' => false,
            'needs_clarification' => false,
            'diversification_info' => null,
        ]);
    }

    // Format response
    $response = $retrievalService->formatResponse($result);

    return response()->json($response);
}
```

**Penjelasan:**
- Validasi input message (required, string, max 500)
- Cek apakah query adalah greeting
- Cek apakah query membutuhkan klarifikasi
- Lakukan retrieval artikel
- Format response dengan artikel dan jawaban
- Return JSON response

## Service Yang Digunakan

**File:** `app/Services/Chatbot/AdvancedRetrievalService.php`

**Penjelasan:**
- Service utama untuk hybrid retrieval
- Menggabungkan Typesense (85%) dan TF-IDF (15%)
- Terapkan diversifikasi hasil
- Format response dengan jawaban chatbot

**File:** `app/Services/Chatbot/ConversationFlowService.php`

**Penjelasan:**
- Menangani greeting detection
- Menangani klarifikasi query ambigu
- Menyimpan conversation memory

## Model Yang Digunakan

**File:** `app/Models\Article.php`

**Field yang digunakan:**
- `id`: ULID primary key
- `title`: Judul artikel
- `content`: Konten artikel
- `excerpt`: Ringkasan artikel
- `keywords`: Kata kunci artikel
- `category_id`: ID kategori (foreign key)
- `is_published`: Status publikasi (boolean)
- `publish_status`: Status publikasi (enum: pending, approved, rejected)
- `views`: Jumlah views (integer)

**Relasi yang digunakan:**
- `belongsTo(Category)`: Kategori artikel
- `belongsTo(User, 'staff_id')`: Penulis artikel
- `hasMany(ArticleFeedback)`: Feedback artikel

**File:** `app/Models\Category.php`

**Field yang digunakan:**
- `id`: ULID primary key
- `name`: Nama kategori

**Relasi yang digunakan:**
- `hasMany(Article)`: Artikel dalam kategori

## Query Database Yang Dieksekusi

```php
// Query 1: Ambil artikel berdasarkan ID kandidat dari Typesense
Article::whereIn('id', $candidateIds)
    ->where('is_published', true)
    ->where('publish_status', 'approved')
    ->with('category')
    ->get()

// Query 2: Ambil semua artikel untuk TF-IDF (fallback)
Article::where('is_published', true)
    ->where('publish_status', 'approved')
    ->with('category')
    ->get()

// Query 3: Ambil kategori untuk domain detection
Category::all()
```

**Penjelasan:**
- Query 1: Mengambil artikel berdasarkan ID kandidat dari Typesense
- Query 2: Fallback jika Typesense tidak tersedia
- Query 3: Mengambil semua kategori untuk domain detection

## Database Yang Terlibat

**Tabel:** `articles`

**Kolom yang digunakan:**
- `id`: ULID primary key
- `category_id`: foreignUlid ke categories
- `staff_id`: foreignUlid ke users
- `title`: string, judul artikel
- `slug`: string, slug artikel
- `content`: longText, konten artikel
- `excerpt`: text, ringkasan artikel
- `keywords`: string, kata kunci artikel
- `views`: integer, jumlah views, default 0
- `is_published`: boolean, status publikasi, default true
- `is_hidden`: boolean, status tersembunyi, default false
- `publish_status`: enum (pending, approved, rejected), default pending
- `rejection_note`: text, catatan penolakan

**Tabel:** `categories`

**Kolom yang digunakan:**
- `id`: ULID primary key
- `name`: string, nama kategori
- `description`: text, deskripsi kategori

## View Yang Digunakan
Tidak ada view khusus untuk chatbot. Chatbot menggunakan JavaScript frontend.

## Event Yang Digunakan
Tidak ada event khusus untuk chatbot query processing.

## Notification Yang Digunakan
Tidak ada notification khusus untuk chatbot query processing.

## Email Yang Dikirim
Tidak ada email untuk chatbot query processing.

## Response Yang Dihasilkan

**Response greeting:**
```json
{
    "answer": "Halo! Saya adalah asisten virtual HelpMinfo. Silakan tanyakan apa saja tentang masalah IT Anda, seperti WiFi, printer, email, atau lainnya.",
    "articles": [],
    "is_greeting": true,
    "needs_clarification": false,
    "diversification_info": null
}
```

**Response klarifikasi:**
```json
{
    "answer": "Maaf, pertanyaan Anda kurang spesifik. Apakah maksud Anda:",
    "articles": [],
    "is_greeting": false,
    "needs_clarification": true,
    "clarification_domains": ["wifi", "internet"],
    "diversification_info": null
}
```

**Response retrieval:**
```json
{
    "answer": "Berikut adalah artikel yang mungkin membantu:",
    "articles": [
        {
            "id": "01H...",
            "title": "Cara Mengatasi WiFi Tidak Terhubung",
            "excerpt": "...",
            "category": "WiFi",
            "similarity_score": 0.85
        }
    ],
    "is_greeting": false,
    "needs_clarification": false,
    "diversification_info": {
        "diversified": true,
        "categories": ["WiFi", "Internet"]
    }
}
```

---

# 2. HYBRID RETRIEVAL

## Tujuan Fitur
Menggabungkan Typesense (85%) dan TF-IDF (15%) untuk retrieval artikel yang lebih akurat.

## Alur Bisnis
1. Query pengguna diterima
2. Sistem normalisasi query
3. Sistem deteksi domain untuk filtering kategori
4. Sistem kirim query ke Typesense untuk mendapatkan kandidat
5. Sistem ambil artikel berdasarkan ID kandidat
6. Sistem hitung TF-IDF untuk kandidat
7. Sistem gabungkan skor: (Typesense * 0.85) + (TF-IDF * 0.15)
8. Sistem terapkan diversifikasi kategori
9. Sistem return artikel terurut

## Route Yang Digunakan
Hybrid retrieval dipanggil secara internal dari service, tidak ada route khusus.

## Controller Yang Dipanggil
Tidak ada controller khusus. Logic ada di AdvancedRetrievalService.

## Service Yang Digunakan

**File:** `app/Services/Chatbot/AdvancedRetrievalService.php`

**Konstanta bobot:**
```php
// Bobot Typesense (sinyal utama)
private const TYPESENSE_WEIGHT = 0.85;

// Bobot TF-IDF (penyesuaian minor)
private const TFIDF_WEIGHT = 0.15;

// Threshold similarity minimum
private const SIMILARITY_THRESHOLD = 0.05;

// Bonus untuk domain match
private const DOMAIN_MATCH_BONUS = 0.2;

// Penalty untuk domain mismatch
private const DOMAIN_MISMATCH_PENALTY = 0.15;
```

**Method retrieve:**
```php
public function retrieve(string $query, int $limit = 5): array
{
    // Normalisasi query
    $normalizedQuery = $this->preprocessor->normalize($query);

    // Deteksi domain
    $domainInfo = $this->domainDetector->detectDomain($query);

    // Fase A: Typesense retrieval (85%)
    $typesenseResults = $this->typesenseService->search($query, 30);

    // Fase B: TF-IDF reranking (15%)
    $articles = $this->getArticlesForReranking($typesenseResults);
    $tfidfScores = $this->tfidfService->calculateBatch($normalizedQuery, $articles);

    // Gabungkan skor
    $finalScores = [];
    foreach ($articles as $article) {
        $typesenseScore = $typesenseResults['scores'][$article->id] ?? 0;
        $tfidfScore = $tfidfScores[$article->id] ?? 0;

        // Formula hybrid: (Typesense * 0.85) + (TF-IDF * 0.15)
        $hybridScore = ($typesenseScore * self::TYPESENSE_WEIGHT) + 
                       ($tfidfScore * self::TFIDF_WEIGHT);

        // Domain adjustment
        if ($domainInfo['domain']) {
            if ($article->category->name === $domainInfo['domain']) {
                $hybridScore += self::DOMAIN_MATCH_BONUS;
            } else {
                $hybridScore -= self::DOMAIN_MISMATCH_PENALTY;
            }
        }

        $finalScores[$article->id] = $hybridScore;
    }

    // Diversifikasi
    $diversified = $this->diversifyResults($articles, $finalScores);

    return [
        'articles' => $diversified,
        'query' => $query,
        'normalized_query' => $normalizedQuery,
        'domain_detected' => $domainInfo['domain'],
    ];
}
```

**Penjelasan:**
- Normalisasi query untuk koreksi typo
- Deteksi domain untuk filtering kategori
- Typesense retrieval sebagai sinyal utama (85%)
- TF-IDF reranking sebagai penyesuaian minor (15%)
- Gabungkan skor dengan formula hybrid
- Terapkan domain adjustment (bonus/penalty)
- Diversifikasi hasil untuk variasi kategori

## Model Yang Digunakan

**File:** `app/Models\Article.php`

**Penjelasan:**
- Lihat bagian "CHATBOT QUERY PROCESSING"

**File:** `app/Models\Category.php`

**Penjelasan:**
- Lihat bagian "CHATBOT QUERY PROCESSING"

## Query Database Yang Dieksekusi

```php
// Query 1: Ambil artikel berdasarkan ID kandidat
Article::whereIn('id', $candidateIds)
    ->where('is_published', true)
    ->where('publish_status', 'approved')
    ->with('category')
    ->get()
```

**Penjelasan:**
- Mengambil artikel berdasarkan ID kandidat dari Typesense
- Filter hanya artikel yang published dan approved
- Eager load kategori

## Database Yang Terlibat

**Tabel:** `articles`

**Penjelasan:**
- Lihat bagian "CHATBOT QUERY PROCESSING"

**Tabel:** `categories`

**Penjelasan:**
- Lihat bagian "CHATBOT QUERY PROCESSING"

## View Yang Digunakan
Tidak ada view khusus untuk hybrid retrieval.

## Event Yang Digunakan
Tidak ada event khusus untuk hybrid retrieval.

## Notification Yang Digunakan
Tidak ada notification khusus untuk hybrid retrieval.

## Email Yang Dikirim
Tidak ada email untuk hybrid retrieval.

## Response Yang Dihasilkan
Hybrid retrieval tidak menghasilkan response langsung. Hasilnya adalah array artikel terurut.

---

# ANALISIS HYBRID RETRIEVAL - FORMULA DAN BOBOT

## Formula Hybrid Scoring

**File:** `app/Services/Chatbot/AdvancedRetrievalService.php`
**Line:** 85-95 (konstanta bobot)

```php
// Bobot Typesense (sinyal utama)
private const TYPESENSE_WEIGHT = 0.85;

// Bobot TF-IDF (penyesuaian minor)
private const TFIDF_WEIGHT = 0.15;

// Threshold similarity minimum
private const SIMILARITY_THRESHOLD = 0.05;

// Bonus untuk domain match
private const DOMAIN_MATCH_BONUS = 0.2;

// Penalty untuk domain mismatch
private const DOMAIN_MISMATCH_PENALTY = 0.15;
```

**Formula lengkap:**

```
HybridScore = (TypesenseScore × 0.85) + (TfidfScore × 0.15)

Jika domain terdeteksi:
  - Jika kategori artikel match dengan domain:
    HybridScore += 0.2
  - Jika kategori artikel tidak match dengan domain:
    HybridScore -= 0.15
```

**Penjelasan:**
- Typesense mendapat bobot 85% sebagai sinyal utama karena lebih akurat untuk pencocokan kata kunci
- TF-IDF mendapat bobot 15% hanya untuk penyesuaian minor pada kasus edge case
- Domain match memberikan bonus 0.2 untuk meningkatkan relevansi domain-specific
- Domain mismatch memberikan penalty 0.15 untuk menurunkan artikel yang tidak relevan dengan domain

---

## Simulasi Kondisi Nyata

### Kasus 1: Query dengan Domain Match

**Kondisi:**
- Query: "wifi tidak terhubung"
- Domain terdeteksi: "wifi"
- Artikel A: "Cara Mengatasi WiFi Tidak Terhubung" (kategori: WiFi)
  - TypesenseScore: 0.90
  - TfidfScore: 0.85
- Artikel B: "Internet Lambat" (kategori: Internet)
  - TypesenseScore: 0.70
  - TfidfScore: 0.65

**Perhitungan:**

**Artikel A:**
```
HybridScore = (0.90 × 0.85) + (0.85 × 0.15)
HybridScore = 0.765 + 0.1275
HybridScore = 0.8925

Domain match bonus:
HybridScore += 0.2
HybridScore = 1.0925
```

**Artikel B:**
```
HybridScore = (0.70 × 0.85) + (0.65 × 0.15)
HybridScore = 0.595 + 0.0975
HybridScore = 0.6925

Domain mismatch penalty:
HybridScore -= 0.15
HybridScore = 0.5425
```

**Hasil:**
- Artikel A: 1.0925 (rank 1)
- Artikel B: 0.5425 (rank 2)

**Alasan:** Artikel A mendapatkan domain match bonus karena kategori WiFi match dengan domain yang terdeteksi, sehingga skor jauh lebih tinggi.

---

### Kasus 2: Query tanpa Domain Terdeteksi

**Kondisi:**
- Query: "komputer lemot"
- Domain terdeteksi: null
- Artikel A: "Komputer Lemot" (kategori: Hardware)
  - TypesenseScore: 0.85
  - TfidfScore: 0.80
- Artikel B: "Internet Lambat" (kategori: Internet)
  - TypesenseScore: 0.60
  - TfidfScore: 0.55

**Perhitungan:**

**Artikel A:**
```
HybridScore = (0.85 × 0.85) + (0.80 × 0.15)
HybridScore = 0.7225 + 0.12
HybridScore = 0.8425

Tidak ada domain adjustment
```

**Artikel B:**
```
HybridScore = (0.60 × 0.85) + (0.55 × 0.15)
HybridScore = 0.51 + 0.0825
HybridScore = 0.5925

Tidak ada domain adjustment
```

**Hasil:**
- Artikel A: 0.8425 (rank 1)
- Artikel B: 0.5925 (rank 2)

**Alasan:** Tanpa domain terdeteksi, tidak ada bonus atau penalty. Ranking murni berdasarkan hybrid score.

---

### Kasus 3: TF-IDF Mengalahkan Typesense (Edge Case)

**Kondisi:**
- Query: "printer error code 504"
- Domain terdeteksi: "printer"
- Artikel A: "Printer Error" (kategori: Hardware)
  - TypesenseScore: 0.50 (karena "504" tidak ada di Typesense)
  - TfidfScore: 0.90 (karena "504" ada di konten artikel)
- Artikel B: "Printer Paper Jam" (kategori: Hardware)
  - TypesenseScore: 0.70
  - TfidfScore: 0.60

**Perhitungan:**

**Artikel A:**
```
HybridScore = (0.50 × 0.85) + (0.90 × 0.15)
HybridScore = 0.425 + 0.135
HybridScore = 0.56

Domain mismatch penalty (kategori Hardware != printer):
HybridScore -= 0.15
HybridScore = 0.41
```

**Artikel B:**
```
HybridScore = (0.70 × 0.85) + (0.60 × 0.15)
HybridScore = 0.595 + 0.09
HybridScore = 0.685

Domain mismatch penalty:
HybridScore -= 0.15
HybridScore = 0.535
```

**Hasil:**
- Artikel B: 0.535 (rank 1)
- Artikel A: 0.41 (rank 2)

**Alasan:** Meskipun TF-IDF Artikel A lebih tinggi, bobot Typesense (85%) masih mendominasi. Artikel B menang karena TypesenseScore lebih tinggi dan domain adjustment sama.

---

### Kasus 4: Diversifikasi Kategori

**Kondisi:**
- Query: "tidak bisa connect"
- Domain terdeteksi: null (ambiguous)
- Artikel A: "WiFi Tidak Connect" (kategori: WiFi)
  - HybridScore: 0.85
- Artikel B: "Internet Tidak Connect" (kategori: Internet)
  - HybridScore: 0.82
- Artikel C: "Printer Tidak Connect" (kategori: Hardware)
  - HybridScore: 0.80
- Artikel D: "WiFi Tidak Connect 2" (kategori: WiFi)
  - HybridScore: 0.78

**Perhitungan:**

**Tanpa diversifikasi:**
1. Artikel A: 0.85 (WiFi)
2. Artikel B: 0.82 (Internet)
3. Artikel C: 0.80 (Hardware)
4. Artikel D: 0.78 (WiFi)

**Dengan diversifikasi (max 2 per kategori):**
1. Artikel A: 0.85 (WiFi)
2. Artikel B: 0.82 (Internet)
3. Artikel C: 0.80 (Hardware)
4. Artikel D: 0.78 (WiFi) - DIPOTONG karena WiFi sudah ada 2

**Hasil diversifikasi:**
1. Artikel A: 0.85 (WiFi)
2. Artikel B: 0.82 (Internet)
3. Artikel C: 0.80 (Hardware)

**Alasan:** Diversifikasi membatasi maksimal 2 artikel per kategori untuk memberikan variasi topik ke pengguna.

---

# 3. TYPESENSE INTEGRATION

## Tujuan Fitur
Menggunakan Typesense sebagai mesin pencarian full-text dengan fuzzy matching untuk retrieval artikel.

## Alur Bisnis
1. Query pengguna diterima
2. Sistem kirim query ke Typesense
3. Typesense melakukan pencarian dengan fuzzy matching
4. Typesense mengembalikan kandidat artikel dengan skor
5. Sistem gunakan skor Typesense sebagai sinyal utama (85%)

## Route Yang Digunakan
Typesense dipanggil secara internal dari service, tidak ada route khusus.

## Controller Yang Dipanggil
Tidak ada controller khusus. Logic ada di TypesenseService.

## Service Yang Digunakan

**File:** `app/Services/Chatbot/TypesenseService.php`

**Konfigurasi:**
```php
// Client Typesense
private $client;

// Collection name
private const COLLECTION_NAME = 'articles';

// Synonym sets untuk query expansion
private array $synonymSets = [
    'wifi' => ['wifi', 'wi-fi', 'wireless', 'wlan', 'hotspot'],
    'internet' => ['internet', 'inet', 'koneksi internet', 'sinyal internet'],
    'printer' => ['printer', 'printing', 'cetak', 'mencetak'],
];
```

**Method search:**
```php
public function search(string $query, int $limit = 10, array $options = []): array
{
    try {
        $searchParameters = [
            'q' => $query,
            'query_by' => 'title,content,excerpt,keywords',
            'per_page' => $limit,
            'num_typos' => 2,
            'min_len_1typo' => 4,
            'min_len_2typo' => 8,
            'prefix' => 'always',
            'drop_tokens_threshold' => 3,
            'typo_tokens_threshold' => 3,
        ];

        if (!empty($options['category_id'])) {
            $searchParameters['filter_by'] = "category_id:{$options['category_id']}";
        }

        $results = $this->client->collections[self::COLLECTION_NAME]
            ->documents
            ->search($searchParameters);

        return [
            'success' => true,
            'results' => $results['hits'],
            'scores' => array_column($results['hits'], 'text_match'),
        ];
    } catch (\Exception $e) {
        Log::error('Typesense search error: ' . $e->getMessage());
        return [
            'success' => false,
            'results' => [],
            'scores' => [],
        ];
    }
}
```

**Penjelasan:**
- Konfigurasi typo tolerance: maksimal 2 typo
- Prefix search: always (mencocokan prefix kata)
- Filter by category_id jika tersedia
- Return results dan scores untuk hybrid retrieval

## Model Yang Digunakan
Tidak ada model khusus untuk Typesense. Typesense adalah external service.

## Query Database Yang Dieksekusi
Tidak ada query database untuk Typesense. Typesense adalah external search engine.

## Database Yang Terlibat
Tidak ada tabel database untuk Typesense. Typesense memiliki collection sendiri.

## View Yang Digunakan
Tidak ada view khusus untuk Typesense.

## Event Yang Digunakan
Tidak ada event khusus untuk Typesense.

## Notification Yang Digunakan
Tidak ada notification khusus untuk Typesense.

## Email Yang Dikirim
Tidak ada email untuk Typesense.

## Response Yang Dihasilkan

**Response Typesense:**
```json
{
    "success": true,
    "results": [
        {
            "document": {
                "id": "01H...",
                "title": "Cara Mengatasi WiFi Tidak Terhubung",
                "content": "...",
                "category_id": "01H...",
                "category_name": "WiFi"
            },
            "text_match": 0.90
        }
    ],
    "scores": [0.90, 0.85, 0.70]
}
```

---

# 4. TF-IDF RETRIEVAL

## Tujuan Fitur
Menghitung TF-IDF untuk reranking artikel sebagai penyesuaian minor (15%) pada hasil Typesense.

## Alur Bisnis
1. Query pengguna diterima
2. Sistem normalisasi query (tokenisasi, stopword removal, stemming)
3. Sistem hitung TF (term frequency) untuk query
4. Sistem hitung IDF (inverse document frequency) dari cache
5. Sistem hitung TF-IDF untuk query
6. Sistem hitung TF-IDF untuk setiap artikel kandidat
7. Sistem hitung cosine similarity antara query dan artikel
8. Sistem gunakan skor TF-IDF sebagai penyesuaian minor (15%)

## Route Yang Digunakan
TF-IDF dipanggil secara internal dari service, tidak ada route khusus.

## Controller Yang Dipanggil
Tidak ada controller khusus. Logic ada di TfidfService.

## Service Yang Digunakan

**File:** `app/Services/Chatbot/TfidfService.php`

**Konstanta:**
```php
// Cache key untuk IDF
private const IDF_CACHE_KEY = 'chatbot:tfidf:idf';
private const IDF_CACHE_TTL = 86400; // 24 jam

// Low priority terms (weight dikurangi)
private array $lowPriorityTerms = [
    'cara', 'bagaimana', 'bagaimanakah', 'apakah', 'apa', 'kenapa', 'mengapa',
    'bagaimana cara', 'bagaimanakah cara', 'bagaimana caranya',
    'tolong', 'bantu', 'help', 'please', 'mohon', 'silakan',
    'masalah', 'problem', 'issue', 'trouble', 'gangguan',
    'saya', 'user', 'pengguna', 'kita', 'kami',
    'bisa', 'dapat', 'bisa tidak', 'dapat tidak',
    'tidak', 'tidak bisa', 'tidak dapat', 'gagal',
];
```

**Method calculate:**
```php
public function calculate(string $query, array $document): float
{
    // Tokenisasi query dan dokumen
    $queryTokens = $this->preprocessor->tokenize($query);
    $docTokens = $this->preprocessor->tokenize($document);

    // Hitung TF untuk query
    $queryTf = $this->calculateTf($queryTokens);

    // Hitung TF untuk dokumen
    $docTf = $this->calculateTf($docTokens);

    // Hitung IDF dari cache
    $idf = $this->getIdf();

    // Hitung TF-IDF untuk query
    $queryTfidf = $this->calculateTfidf($queryTf, $idf);

    // Hitung TF-IDF untuk dokumen
    $docTfidf = $this->calculateTfidf($docTf, $idf);

    // Hitung cosine similarity
    $similarity = $this->similarityService->calculate($queryTfidf, $docTfidf);

    return $similarity;
}
```

**Penjelasan:**
- Tokenisasi query dan dokumen
- Hitung TF (term frequency)
- Hitung IDF (inverse document frequency) dari cache
- Hitung TF-IDF untuk query dan dokumen
- Hitung cosine similarity
- Return similarity score

**Low priority terms adjustment:**
```php
private function applyLowPriorityAdjustment(array $tfidf): array
{
    foreach ($tfidf as $term => $score) {
        if (in_array($term, $this->lowPriorityTerms)) {
            $tfidf[$term] *= 0.3; // Kurangi bobot menjadi 30%
        }
    }
    return $tfidf;
}
```

**Penjelasan:**
- Low priority terms seperti "cara", "bagaimana", "tolong" mendapat bobot dikurangi menjadi 30%
- Ini untuk mencegah terms generik mendominasi ranking

## Model Yang Digunakan
Tidak ada model khusus untuk TF-IDF. TF-IDF menggunakan data dari Article model.

## Query Database Yang Dieksekusi
Tidak ada query database untuk TF-IDF. TF-IDF menggunakan data yang sudah di-load.

## Database Yang Terlibat
Tidak ada tabel database khusus untuk TF-IDF. Menggunakan cache untuk IDF.

## View Yang Digunakan
Tidak ada view khusus untuk TF-IDF.

## Event Yang Digunakan
Tidak ada event khusus untuk TF-IDF.

## Notification Yang Digunakan
Tidak ada notification khusus untuk TF-IDF.

## Email Yang Dikirim
Tidak ada email untuk TF-IDF.

## Response Yang Dihasilkan
TF-IDF tidak menghasilkan response langsung. Hasilnya adalah similarity score (float antara 0.0 dan 1.0).

---

# 5. DOMAIN DETECTION

## Tujuan Fitur
Mendeteksi domain/topik IT dari query pengguna untuk filtering kategori dan domain adjustment.

## Alur Bisnis
1. Query pengguna diterima
2. Sistem normalisasi query (tokenisasi, koreksi typo)
3. Sistem cek token query terhadap kata kunci domain
4. Sistem hitung confidence score untuk setiap domain
5. Sistem return domain dengan confidence tertinggi
6. Sistem return category_ids untuk filtering

## Route Yang Digunakan
Domain detection dipanggil secara internal dari service, tidak ada route khusus.

## Controller Yang Dipanggil
Tidak ada controller khusus. Logic ada di DomainDetectionService.

## Service Yang Digunakan

**File:** `app/Services/Chatbot/DomainDetectionService.php`

**Konstanta:**
```php
// Cache key untuk domain mapping
private const DOMAIN_CACHE_KEY = 'chatbot:domain:mapping';
private const DOMAIN_CACHE_TTL = 3600; // 1 jam

// Daftar domain IT yang valid
public array $curatedDomains = [
    'wifi', 'internet', 'jaringan', 'printer', 'komputer',
    'email', 'website', 'aplikasi', 'akun', 'security',
    'bsod', 'windows', 'server', 'driver', 'hardware',
];

// Pemetaan domain ke kata kunci dan kategori
private array $domainKeywords = [
    'wifi' => [
        'keywords' => ['wifi', 'wi-fi', 'wireless', 'wlan', 'hotspot', 'access point', 'ap', 'router wifi'],
        'categories' => ['wifi'],
    ],
    'internet' => [
        'keywords' => ['internet', 'inet', 'koneksi internet', 'sinyal internet', 'bandwidth', 'quota', 'paket data'],
        'categories' => ['internet'],
    ],
    'printer' => [
        'keywords' => ['printer', 'printing', 'cetak', 'mencetak', 'epson', 'canon', 'hp printer', 'ink', 'tinta', 'cartridge', 'toner'],
        'categories' => ['hardware'],
    ],
    // ... dan seterusnya
];
```

**Method detectDomain:**
```php
public function detectDomain(string $query): array
{
    // Normalisasi query
    $normalizedQuery = strtolower(trim($query));
    $tokens = explode(' ', $normalizedQuery);

    // Hitung confidence untuk setiap domain
    $domainScores = [];
    foreach ($this->curatedDomains as $domain) {
        $keywords = $this->domainKeywords[$domain]['keywords'] ?? [];
        $score = 0;

        foreach ($keywords as $keyword) {
            if (str_contains($normalizedQuery, $keyword)) {
                $score += 1;
            }
        }

        if ($score > 0) {
            $domainScores[$domain] = $score;
        }
    }

    // Sort domain berdasarkan score tertinggi
    arsort($domainScores);

    // Ambil domain dengan score tertinggi
    $topDomain = array_key_first($domainScores) ?? null;

    // Ambil category_ids untuk domain
    $categoryIds = [];
    if ($topDomain) {
        $categories = $this->domainKeywords[$topDomain]['categories'] ?? [];
        $categoryIds = $this->getCategoryIds($categories);
    }

    return [
        'domain' => $topDomain,
        'confidence' => $domainScores[$topDomain] ?? 0,
        'category_ids' => $categoryIds,
    ];
}
```

**Penjelasan:**
- Normalisasi query ke lowercase
- Tokenisasi query
- Cek setiap domain keyword dalam query
- Hitung confidence score berdasarkan jumlah keyword match
- Return domain dengan confidence tertinggi
- Return category_ids untuk filtering

## Model Yang Digunakan

**File:** `app/Models\Category.php`

**Penjelasan:**
- Lihat bagian "CHATBOT QUERY PROCESSING"

## Query Database Yang Dieksekusi

```php
// Query 1: Ambil category_ids berdasarkan nama kategori
Category::whereIn('name', $categoryNames)->pluck('id')->toArray()
```

**Penjelasan:**
- Mengambil ID kategori berdasarkan nama kategori
- Digunakan untuk filtering artikel berdasarkan domain

## Database Yang Terlibat

**Tabel:** `categories`

**Penjelasan:**
- Lihat bagian "CHATBOT QUERY PROCESSING"

## View Yang Digunakan
Tidak ada view khusus untuk domain detection.

## Event Yang Digunakan
Tidak ada event khusus untuk domain detection.

## Notification Yang Digunakan
Tidak ada notification khusus untuk domain detection.

## Email Yang Dikirim
Tidak ada email untuk domain detection.

## Response Yang Dihasilkan

**Response domain detection:**
```json
{
    "domain": "wifi",
    "confidence": 2,
    "category_ids": ["01H..."]
}
```

---

# 6. CONVERSATION FLOW

## Tujuan Fitur
Menangani flow percakapan chatbot termasuk greeting, klarifikasi, dan conversation memory.

## Alur Bisnis
1. Pengguna mengirim query
2. Sistem cek apakah query adalah greeting
3. Sistem cek apakah query ambigu dan butuh klarifikasi
4. Sistem simpan conversation memory di cache
5. Sistem return response sesuai flow

## Route Yang Digunakan
Conversation flow dipanggil secara internal dari service, tidak ada route khusus.

## Controller Yang Dipanggil
Tidak ada controller khusus. Logic ada di ConversationFlowService.

## Service Yang Digunakan

**File:** `app/Services/Chatbot/ConversationFlowService.php`

**Konstanta:**
```php
// Cache key untuk conversation memory
private const CONVERSATION_CACHE_KEY = 'chatbot:conversation:';
private const CONVERSATION_CACHE_TTL = 1800; // 30 menit

// Daftar greeting
private array $greetings = [
    'halo', 'hai', 'hi', 'hello', 'selamat pagi', 'selamat siang',
    'selamat sore', 'selamat malam', 'assalamualaikum', 'salam',
];

// Daftar pattern ambigu
private array $ambiguousPatterns = [
    '/\b(tidak|gagal|error|lemot|lambat|susah|bisa)\b/i',
];

// Peta klarifikasi
private array $clarificationMap = [
    'wifi' => 'Apakah masalah Anda terkait WiFi atau jaringan nirkabel?',
    'internet' => 'Apakah masalah Anda terkait koneksi internet atau bandwidth?',
    'printer' => 'Apakah masalah Anda terkait printer atau perangkat cetak?',
];
```

**Method isGreeting:**
```php
public function isGreeting(string $query): bool
{
    $normalizedQuery = strtolower(trim($query));

    foreach ($this->greetings as $greeting) {
        if (str_contains($normalizedQuery, $greeting)) {
            return true;
        }
    }

    return false;
}
```

**Penjelasan:**
- Normalisasi query ke lowercase
- Cek apakah query mengandung greeting
- Return true jika greeting terdeteksi

**Method needsClarification:**
```php
public function needsClarification(string $query): bool
{
    $normalizedQuery = strtolower(trim($query));

    // Cek pattern ambigu
    foreach ($this->ambiguousPatterns as $pattern) {
        if (preg_match($pattern, $normalizedQuery)) {
            // Cek apakah query mengandung domain spesifik
            $hasDomain = false;
            foreach ($this->curatedDomains as $domain) {
                if (str_contains($normalizedQuery, $domain)) {
                    $hasDomain = true;
                    break;
                }
            }

            // Jika tidak ada domain spesifik, butuh klarifikasi
            if (!$hasDomain) {
                return true;
            }
        }
    }

    return false;
}
```

**Penjelasan:**
- Normalisasi query ke lowercase
- Cek pattern ambigu (tidak, gagal, error, dll)
- Cek apakah query mengandung domain spesifik
- Jika pattern ambigu tapi tidak ada domain, butuh klarifikasi

**Method storeConversationMemory:**
```php
public function storeConversationMemory(string $sessionId, array $data): void
{
    $cacheKey = self::CONVERSATION_CACHE_KEY . $sessionId;
    Cache::put($cacheKey, $data, self::CONVERSATION_CACHE_TTL);
}
```

**Penjelasan:**
- Simpan conversation memory di cache
- TTL 30 menit
- Digunakan untuk konteks percakapan

## Model Yang Digunakan
Tidak ada model khusus untuk conversation flow. Menggunakan cache.

## Query Database Yang Dieksekusi
Tidak ada query database untuk conversation flow. Menggunakan cache.

## Database Yang Terlibat
Tidak ada tabel database untuk conversation flow. Menggunakan cache.

## View Yang Digunakan
Tidak ada view khusus untuk conversation flow.

## Event Yang Digunakan
Tidak ada event khusus untuk conversation flow.

## Notification Yang Digunakan
Tidak ada notification khusus untuk conversation flow.

## Email Yang Dikirim
Tidak ada email untuk conversation flow.

## Response Yang Dihasilkan
Conversation flow tidak menghasilkan response langsung. Hasilnya adalah boolean (isGreeting, needsClarification).

---

# 7. PREPROCESSING

## Tujuan Fitur
Menormalisasi query dan dokumen untuk pipeline retrieval (koreksi typo, tokenisasi, stopword removal, stemming).

## Alur Bisnis
1. Query atau dokumen diterima
2. Sistem normalisasi karakter (lowercase, trim)
3. Sistem koreksi typo menggunakan dictionary
4. Sistem normalisasi karakter berulang (virusssss -> virus)
5. Sistem tokenisasi teks
6. Sistem hapus stopword
7. Sistem stemming (dengan proteksi istilah teknis)
8. Sistem return hasil preprocessing

## Route Yang Digunakan
Preprocessing dipanggil secara internal dari service, tidak ada route khusus.

## Controller Yang Dipanggil
Tidak ada controller khusus. Logic ada di PreprocessingService.

## Service Yang Digunakan

**File:** `app/Services/Chatbot/PreprocessingService.php`

**Konstanta:**
```php
// Kamus typo
private array $typoDictionary = [
    'wfi' => 'wifi', 'wiifi' => 'wifi', 'wfii' => 'wifi',
    'intenet' => 'internet', 'internrt' => 'internet',
    'kompter' => 'komputer', 'komputr' => 'komputer',
    'prnter' => 'printer', 'printter' => 'printer',
    'emai' => 'email', 'emaill' => 'email',
    // ... dan seterusnya
];

// Stopword list
private array $stopwords = [
    'yang', 'dan', 'atau', 'di', 'ke', 'dari', 'pada', 'untuk',
    'dengan', 'tanpa', 'adalah', 'ialah', 'itu', 'ini',
    // ... dan seterusnya
];

// Istilah teknis yang tidak di-stem
private array $technicalTerms = [
    'wifi', 'internet', 'printer', 'email', 'website',
    'router', 'modem', 'driver', 'software', 'hardware',
    // ... dan seterusnya
];
```

**Method normalize:**
```php
public function normalize(string $text): string
{
    // Lowercase dan trim
    $text = strtolower(trim($text));

    // Koreksi typo
    $text = $this->correctTypos($text);

    // Normalisasi karakter berulang
    $text = $this->normalizeRepeatedChars($text);

    return $text;
}
```

**Penjelasan:**
- Lowercase dan trim teks
- Koreksi typo menggunakan dictionary
- Normalisasi karakter berulang

**Method tokenize:**
```php
public function tokenize(string $text): array
{
    // Normalisasi teks
    $text = $this->normalize($text);

    // Tokenisasi (split by space dan punctuation)
    $tokens = preg_split('/[\s\p{P}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

    // Hapus stopword
    $tokens = array_diff($tokens, $this->stopwords);

    // Stemming dengan proteksi istilah teknis
    $tokens = $this->stemWithProtection($tokens);

    return array_values($tokens);
}
```

**Penjelasan:**
- Normalisasi teks
- Tokenisasi (split by space dan punctuation)
- Hapus stopword
- Stemming dengan proteksi istilah teknis

## Model Yang Digunakan
Tidak ada model khusus untuk preprocessing.

## Query Database Yang Dieksekusi
Tidak ada query database untuk preprocessing.

## Database Yang Terlibat
Tidak ada tabel database untuk preprocessing.

## View Yang Digunakan
Tidak ada view khusus untuk preprocessing.

## Event Yang Digunakan
Tidak ada event khusus untuk preprocessing.

## Notification Yang Digunakan
Tidak ada notification khusus untuk preprocessing.

## Email Yang Dikirim
Tidak ada email untuk preprocessing.

## Response Yang Dihasilkan
Preprocessing tidak menghasilkan response langsung. Hasilnya adalah string (normalize) atau array (tokenize).

---

# 8. COSINE SIMILARITY

## Tujuan Fitur
Menghitung tingkat kemiripan antara dua vektor menggunakan metode Cosine Similarity.

## Alur Bisnis
1. Dua vektor diterima (query vector dan document vector)
2. Sistem cek apakah salah satu vektor kosong
3. Sistem gabungkan semua term unik dari kedua vektor
4. Sistem hitung dot product dan magnitude untuk setiap term
5. Sistem hitung cosine similarity: dot product / (magnitudeA * magnitudeB)
6. Sistem return similarity score (0.0 - 1.0)

## Route Yang Digunakan
Cosine similarity dipanggil secara internal dari service, tidak ada route khusus.

## Controller Yang Dipanggil
Tidak ada controller khusus. Logic ada di CosineSimilarityService.

## Service Yang Digunakan

**File:** `app/Services/Chatbot/CosineSimilarityService.php`

**Method calculate:**
```php
public function calculate(array $vectorA, array $vectorB): float
{
    // Cek apakah salah satu vektor kosong
    if (empty($vectorA) || empty($vectorB)) {
        return 0.0;
    }

    $dotProduct = 0.0;
    $magnitudeA = 0.0;
    $magnitudeB = 0.0;

    // Gabungkan semua term unik
    $allTerms = array_unique(array_merge(array_keys($vectorA), array_keys($vectorB)));

    // Hitung dot product dan magnitude
    foreach ($allTerms as $term) {
        $aValue = $vectorA[$term] ?? 0;
        $bValue = $vectorB[$term] ?? 0;

        $dotProduct += $aValue * $bValue;
        $magnitudeA += $aValue * $aValue;
        $magnitudeB += $bValue * $bValue;
    }

    // Hitung akar kuadrat untuk magnitude
    $magnitudeA = sqrt($magnitudeA);
    $magnitudeB = sqrt($magnitudeB);

    // Hindari pembagian dengan nol
    if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
        return 0.0;
    }

    // Return cosine similarity
    return $dotProduct / ($magnitudeA * $magnitudeB);
}
```

**Penjelasan:**
- Cek apakah salah satu vektor kosong
- Gabungkan semua term unik dari kedua vektor
- Hitung dot product dan magnitude
- Hitung cosine similarity
- Return similarity score (0.0 = tidak mirip, 1.0 = identik)

## Model Yang Digunakan
Tidak ada model khusus untuk cosine similarity.

## Query Database Yang Dieksekusi
Tidak ada query database untuk cosine similarity.

## Database Yang Terlibat
Tidak ada tabel database untuk cosine similarity.

## View Yang Digunakan
Tidak ada view khusus untuk cosine similarity.

## Event Yang Digunakan
Tidak ada event khusus untuk cosine similarity.

## Notification Yang Digunakan
Tidak ada notification khusus untuk cosine similarity.

## Email Yang Dikirim
Tidak ada email untuk cosine similarity.

## Response Yang Dihasilkan
Cosine similarity tidak menghasilkan response langsung. Hasilnya adalah float (0.0 - 1.0).

---

# 9. VOCABULARY SERVICE

## Tujuan Fitur
Mengekstrak kosakata dari artikel dan menggunakannya untuk koreksi typo cerdas menggunakan Levenshtein distance.

## Alur Bisnis
1. Sistem ekstrak kosakata dari artikel (judul, kata kunci, konten, kategori)
2. Sistem simpan kosakata di cache
3. Sistem gunakan kosakata untuk koreksi typo
4. Sistem hitung Levenshtein distance antara kata typo dan kosakata
5. Sistem koreksi jika similarity >= threshold

## Route Yang Digunakan
Vocabulary service dipanggil secara internal dari service, tidak ada route khusus.

## Controller Yang Dipanggil
Tidak ada controller khusus. Logic ada di VocabularyService.

## Service Yang Digunakan

**File:** `app/Services/Chatbot/VocabularyService.php`

**Konstanta:**
```php
// Cache key untuk kosakata
private const CACHE_KEY = 'chatbot_vocabulary';
private const CACHE_TTL = 3600; // 1 jam

// Threshold similarity minimum
private const MIN_SIMILARITY = 0.70;
private const MIN_SIMILARITY_LONG_WORDS = 0.65;

// Panjang kata minimum
private const MIN_WORD_LENGTH = 3;

// Maksimal karakter berulang
private const MAX_REPEATED_CHARS = 1;

// Peta typo kurasi
private array $curatedTypoMap = [
    'ransomwre' => 'ransomware',
    'malwere' => 'malware',
    'virusss' => 'virus',
    'wfi' => 'wifi',
    'prnter' => 'printer',
    'kompter' => 'komputer',
    // ... dan seterusnya
];
```

**Method extractVocabulary:**
```php
public function extractVocabulary(): array
{
    // Ambil semua artikel
    $articles = Article::where('is_published', true)
        ->where('publish_status', 'approved')
        ->get();

    $vocabulary = [];

    foreach ($articles as $article) {
        // Ekstrak dari judul
        $titleTokens = $this->preprocessor->tokenize($article->title);
        $vocabulary = array_merge($vocabulary, $titleTokens);

        // Ekstrak dari kata kunci
        $keywordTokens = $this->preprocessor->tokenize($article->keywords ?? '');
        $vocabulary = array_merge($vocabulary, $keywordTokens);

        // Ekstrak dari konten
        $contentTokens = $this->preprocessor->tokenize($article->content);
        $vocabulary = array_merge($vocabulary, $contentTokens);

        // Ekstrak dari kategori
        $categoryTokens = $this->preprocessor->tokenize($article->category->name);
        $vocabulary = array_merge($vocabulary, $categoryTokens);
    }

    // Hapus duplikat dan urutkan
    $vocabulary = array_unique($vocabulary);
    sort($vocabulary);

    // Simpan di cache
    Cache::put(self::CACHE_KEY, $vocabulary, self::CACHE_TTL);

    return $vocabulary;
}
```

**Penjelasan:**
- Ambil semua artikel yang published dan approved
- Ekstrak kosakata dari judul, kata kunci, konten, dan kategori
- Hapus duplikat dan urutkan
- Simpan di cache dengan TTL 1 jam

**Method correctTypo:**
```php
public function correctTypo(string $word): string
{
    // Cek peta typo kurasi dulu
    if (isset($this->curatedTypoMap[$word])) {
        return $this->curatedTypoMap[$word];
    }

    // Normalisasi karakter berulang
    $word = $this->normalizeRepeatedChars($word);

    // Ambil kosakata dari cache
    $vocabulary = Cache::get(self::CACHE_KEY, []);
    if (empty($vocabulary)) {
        $vocabulary = $this->extractVocabulary();
    }

    // Cek panjang kata minimum
    if (mb_strlen($word) < self::MIN_WORD_LENGTH) {
        return $word;
    }

    // Cari kata terdekat di kosakata
    $bestMatch = $word;
    $bestSimilarity = 0.0;

    foreach ($vocabulary as $vocabWord) {
        $similarity = $this->calculateSimilarity($word, $vocabWord);

        if ($similarity > $bestSimilarity) {
            $bestSimilarity = $similarity;
            $bestMatch = $vocabWord;
        }
    }

    // Return koreksi jika similarity >= threshold
    if ($bestSimilarity >= $this->getAdaptiveMinSimilarity($word)) {
        return $bestMatch;
    }

    return $word;
}
```

**Penjelasan:**
- Cek peta typo kurasi dulu
- Normalisasi karakter berulang
- Ambil kosakata dari cache
- Cari kata terdekat menggunakan Levenshtein distance
- Return koreksi jika similarity >= threshold

## Model Yang Digunakan

**File:** `app/Models\Article.php`

**Penjelasan:**
- Lihat bagian "CHATBOT QUERY PROCESSING"

## Query Database Yang Dieksekusi

```php
// Query 1: Ambil semua artikel untuk ekstraksi kosakata
Article::where('is_published', true)
    ->where('publish_status', 'approved')
    ->get()
```

**Penjelasan:**
- Mengambil semua artikel yang published dan approved
- Digunakan untuk ekstraksi kosakata

## Database Yang Terlibat

**Tabel:** `articles`

**Penjelasan:**
- Lihat bagian "CHATBOT QUERY PROCESSING"

## View Yang Digunakan
Tidak ada view khusus untuk vocabulary service.

## Event Yang Digunakan
Tidak ada event khusus untuk vocabulary service.

## Notification Yang Digunakan
Tidak ada notification khusus untuk vocabulary service.

## Email Yang Dikirim
Tidak ada email untuk vocabulary service.

## Response Yang Dihasilkan
Vocabulary service tidak menghasilkan response langsung. Hasilnya adalah array (extractVocabulary) atau string (correctTypo).

---

# 10. IMPORTANT PHRASE SERVICE

## Tujuan Fitur
Mendeteksi dan memberikan boost pada frasa penting yang mewakili intent pengguna sebenarnya.

## Alur Bisnis
1. Query pengguna diterima
2. Sistem deteksi frasa penting dalam query
3. Sistem berikan boost pada artikel yang mengandung frasa penting
4. Sistem terapkan boost berdasarkan lokasi (judul vs konten)
5. Sistem return skor yang sudah di-boost

## Route Yang Digunakan
Important phrase service dipanggil secara internal dari service, tidak ada route khusus.

## Controller Yang Dipanggil
Tidak ada controller khusus. Logic ada di ImportantPhraseService.

## Service Yang Digunakan

**File:** `app/Services/Chatbot\ImportantPhraseService.php`

**Konstanta:**
```php
// Bonus untuk pencocokan frasa di konten
private const PHRASE_MATCH_BONUS = 0.4;

// Bonus untuk pencocokan frasa di judul
private const TITLE_PHRASE_BONUS = 0.6;

// Bonus untuk frasa exact query di judul
private const EXACT_QUERY_PHRASE_BONUS = 0.8;

// Bonus untuk frasa yang sejalan dengan kategori
private const PHRASE_CATEGORY_BOOST = 0.15;

// Panjang frasa minimum
private const MIN_PHRASE_LENGTH = 2;

// Daftar frasa penting
private array $importantPhrases = [
    'tidak terhubung', 'tidak connect', 'tidak konek', 'koneksi gagal',
    'gagal connect', 'gagal terhubung', 'putus nyambung', 'sering putus',
    'tidak bisa connect', 'tidak bisa terhubung',
    'tidak terbaca', 'tidak terdeteksi', 'tidak muncul', 'tidak kedetect',
    'tidak dikenali',
    'gagal login', 'tidak bisa login', 'gagal masuk', 'tidak bisa masuk',
    'terkunci', 'akun terkunci',
    'tidak merespon', 'tidak respon', 'tidak responsif', 'tidak bereaksi',
    'diam saja',
    'tidak berfungsi', 'tidak bisa digunakan', 'tidak bisa dipakai',
    'tidak mau', 'tidak bisa', 'gagal berfungsi',
    'tidak muncul', 'hilang tiba-tiba', 'menghilang', 'blank',
    'layar hitam', 'layar biru',
    'sangat lambat', 'lemot parah', 'macet total', 'hang', 'freeze',
    'not responding',
    'error terus', 'muncul error', 'pesan error', 'kode error',
    'notifikasi error',
];
```

**Method detectPhrases:**
```php
public function detectPhrases(string $query): array
{
    $queryLower = strtolower(trim($query));
    $detectedPhrases = [];

    // Urutkan frasa berdasarkan panjang (terpanjang dulu)
    $sortedPhrases = $this->importantPhrases;
    usort($sortedPhrases, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

    foreach ($sortedPhrases as $phrase) {
        if (mb_strlen($phrase) < self::MIN_PHRASE_LENGTH) {
            continue;
        }

        if (str_contains($queryLower, $phrase)) {
            $position = strpos($queryLower, $phrase);
            $category = $this->getPhraseCategory($phrase);

            $detectedPhrases[] = [
                'phrase' => $phrase,
                'position' => $position,
                'category' => $category,
            ];
        }
    }

    // Hapus frasa yang overlap
    $detectedPhrases = $this->removeOverlappingPhrases($detectedPhrases);

    return $detectedPhrases;
}
```

**Penjelasan:**
- Normalisasi query ke lowercase
- Urutkan frasa berdasarkan panjang (terpanjang dulu)
- Cek setiap frasa apakah ada dalam query
- Simpan posisi dan kategori setiap frasa
- Hapus frasa yang overlap (pertahankan yang lebih panjang)
- Return array frasa terdeteksi

**Method calculatePhraseBoost:**
```php
public function calculatePhraseBoost(string $query, string $title, string $content, string $category): float
{
    $detectedPhrases = $this->detectPhrases($query);
    $totalBoost = 0.0;

    foreach ($detectedPhrases as $phraseData) {
        $phrase = $phraseData['phrase'];
        $phraseCategory = $phraseData['category'];

        // Cek frasa di judul
        if (str_contains(strtolower($title), $phrase)) {
            $totalBoost += self::TITLE_PHRASE_BONUS;
        }

        // Cek frasa di konten
        if (str_contains(strtolower($content), $phrase)) {
            $totalBoost += self::PHRASE_MATCH_BONUS;
        }

        // Bonus kategori
        if ($phraseCategory && str_contains(strtolower($category), $phraseCategory)) {
            $totalBoost += self::PHRASE_CATEGORY_BOOST;
        }
    }

    return $totalBoost;
}
```

**Penjelasan:**
- Deteksi frasa penting dalam query
- Cek frasa di judul (bonus 0.6)
- Cek frasa di konten (bonus 0.4)
- Bonus kategori jika frasa sejalan dengan kategori (0.15)
- Return total boost

## Model Yang Digunakan
Tidak ada model khusus untuk important phrase service.

## Query Database Yang Dieksekusi
Tidak ada query database untuk important phrase service.

## Database Yang Terlibat
Tidak ada tabel database untuk important phrase service.

## View Yang Digunakan
Tidak ada view khusus untuk important phrase service.

## Event Yang Digunakan
Tidak ada event khusus untuk important phrase service.

## Notification Yang Digunakan
Tidak ada notification khusus untuk important phrase service.

## Email Yang Dikirim
Tidak ada email untuk important phrase service.

## Response Yang Dihasilkan
Important phrase service tidak menghasilkan response langsung. Hasilnya adalah array (detectPhrases) atau float (calculatePhraseBoost).

---

# 11. DIVERSIFICATION

## Tujuan Fitur
Menerapkan diversifikasi hasil untuk memberikan variasi kategori ke pengguna.

## Alur Bisnis
1. Hasil retrieval diterima
2. Sistem hitung jumlah artikel per kategori
3. Sistem batasi maksimal 2 artikel per kategori
4. Sistem return hasil yang sudah di-diversifikasi

## Route Yang Digunakan
Diversifikasi dipanggil secara internal dari service, tidak ada route khusus.

## Controller Yang Dipanggil
Tidak ada controller khusus. Logic ada di AdvancedRetrievalService.

## Service Yang Digunakan

**File:** `app/Services/Chatbot/AdvancedRetrievalService.php`

**Method diversifyResults:**
```php
private function diversifyResults(Collection $articles, array $scores): array
{
    $diversified = [];
    $categoryCounts = [];

    // Sort artikel berdasarkan score
    arsort($scores);

    foreach ($scores as $articleId => $score) {
        $article = $articles->firstWhere('id', $articleId);
        if (!$article) continue;

        $category = $article->category->name ?? 'Uncategorized';

        // Batasi maksimal 2 per kategori
        if (!isset($categoryCounts[$category])) {
            $categoryCounts[$category] = 0;
        }

        if ($categoryCounts[$category] < 2) {
            $diversified[] = [
                'article' => $article,
                'score' => $score,
                'category' => $category,
            ];
            $categoryCounts[$category]++;
        }
    }

    return $diversified;
}
```

**Penjelasan:**
- Sort artikel berdasarkan score descending
- Hitung jumlah artikel per kategori
- Batasi maksimal 2 artikel per kategori
- Return hasil yang sudah di-diversifikasi

## Model Yang Digunakan

**File:** `app/Models\Article.php`

**Penjelasan:**
- Lihat bagian "CHATBOT QUERY PROCESSING"

**File:** `app/Models\Category.php`

**Penjelasan:**
- Lihat bagian "CHATBOT QUERY PROCESSING"

## Query Database Yang Dieksekusi
Tidak ada query database untuk diversifikasi. Menggunakan data yang sudah di-load.

## Database Yang Terlibat
Tidak ada tabel database khusus untuk diversifikasi.

## View Yang Digunakan
Tidak ada view khusus untuk diversifikasi.

## Event Yang Digunakan
Tidak ada event khusus untuk diversifikasi.

## Notification Yang Digunakan
Tidak ada notification khusus untuk diversifikasi.

## Email Yang Dikirim
Tidak ada email untuk diversifikasi.

## Response Yang Dihasilkan
Diversifikasi tidak menghasilkan response langsung. Hasilnya adalah array artikel yang sudah di-diversifikasi.

---

# 12. ESCALATION TO TICKET

## Tujuan Fitur
Memungkinkan pengguna untuk eskalasi ke tiket jika chatbot tidak membantu.

## Alur Bisnis
1. Pengguna klik "Eskalasi ke Live Chat"
2. Sistem validasi input (name, email, message, category_id)
3. Sistem cek status live service
4. Sistem buat tiket baru
5. Sistem auto-assign tiket ke staff
6. Sistem kirim email tracking
7. Sistem return response dengan ticket_id

## Route Yang Digunakan

**File:** `routes/web.php`
**Line:** 141

```php
Route::post('/chatbot/escalate', [ChatbotController::class, 'escalateToTicket'])->name('chatbot.escalate');
```

**Penjelasan:**
- Route ini untuk eskalasi ke tiket

## Controller Yang Dipanggil

**File:** `app/Http/Controllers/ChatbotController.php`
**Line:** 715-777 (escalateToTicket method)

```php
public function escalateToTicket(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:50',
        'email' => 'required|email|max:50',
        'message' => 'required|string',
        'category_id' => 'required|exists:categories,id',
    ]);

    // Cek live service
    if (!Setting::bool('live_service_enabled', true)) {
        return response()->json([
            'success' => false,
            'message' => 'Live service sedang offline. Silakan buat laporan/report atau coba lagi nanti.',
        ], 423);
    }

    // Buat tiket
    $ticket = DB::transaction(function () use ($request) {
        $ticket = Ticket::create([
            'name' => $request->name,
            'email' => $request->email,
            'subject' => 'Eskalasi dari Chatbot',
            'message' => $request->message,
            'category_id' => $request->category_id,
            'status' => 'open',
            'priority' => 'medium',
        ]);

        TicketLog::create([
            'ticket_id' => $ticket->id,
            'action' => 'created',
            'description' => 'Tiket dibuat dari eskalasi chatbot',
        ]);

        // Auto-assign
        $staffProfile = $this->assignTicketToAvailableStaff($ticket);

        if (!$staffProfile) {
            $ticket->update(['status' => 'waiting']);
            TicketLog::create([
                'ticket_id' => $ticket->id,
                'action' => 'waiting',
                'description' => 'Belum ada staff tersedia',
            ]);
        }

        return $ticket;
    });

    // Kirim email tracking
    $trackingUrl = route('tickets.track', ['token' => $ticket->tracking_token]);
    Mail::to($ticket->email)->send(new TicketTrackingMail($ticket, $trackingUrl));

    return response()->json([
        'success' => true,
        'message' => 'Tiket berhasil dibuat. Staff akan segera membantu Anda.',
        'ticket_id' => $ticket->id,
        'tracking_url' => $trackingUrl,
    ]);
}
```

**Penjelasan:**
- Validasi input
- Cek status live service
- Buat tiket dengan subject "Eskalasi dari Chatbot"
- Auto-assign tiket ke staff
- Kirim email tracking
- Return response dengan ticket_id

## Service Yang Digunakan
Tidak ada service khusus untuk eskalasi. Logic ada di controller.

## Model Yang Digunakan

**File:** `app/Models\Ticket.php`

**Penjelasan:**
- Lihat bagian "TICKETING SYSTEM ANALYSIS"

**File:** `app/Models\TicketLog.php`

**Penjelasan:**
- Lihat bagian "TICKETING SYSTEM ANALYSIS"

## Query Database Yang Dieksekusi

```php
// Query 1: Buat tiket
Ticket::create([...])

// Query 2: Buat log
TicketLog::create([...])

// Query 3: Auto-assign (lihat bagian Auto Assignment)
```

**Penjelasan:**
- Query 1: Insert tiket baru
- Query 2: Insert log aktivitas
- Query 3: Query untuk auto-assignment (detail di bagian Auto Assignment)

## Database Yang Terlibat

**Tabel:** `tickets`

**Penjelasan:**
- Lihat bagian "TICKETING SYSTEM ANALYSIS"

**Tabel:** `ticket_logs`

**Penjelasan:**
- Lihat bagian "TICKETING SYSTEM ANALYSIS"

## View Yang Digunakan
Tidak ada view khusus untuk eskalasi. Form eskalasi ada di view chatbot.

## Event Yang Digunakan
Tidak ada event khusus untuk eskalasi.

## Notification Yang Digunakan
Tidak ada notification khusus untuk eskalasi.

## Email Yang Dikirim

**File:** `app/Mail/TicketTrackingMail.php`

**Penjelasan:**
- Lihat bagian "TICKETING SYSTEM ANALYSIS"

## Response Yang Dihasilkan

**Response sukses:**
```json
{
    "success": true,
    "message": "Tiket berhasil dibuat. Staff akan segera membantu Anda.",
    "ticket_id": "01H...",
    "tracking_url": "http://.../tickets/track/..."
}
```

**Response live service offline:**
```json
{
    "success": false,
    "message": "Live service sedang offline. Silakan buat laporan/report atau coba lagi nanti."
}
```
HTTP Status: 423 Locked

---

# 13. ARTICLE FEEDBACK

## Tujuan Fitur
Memungkinkan pengguna untuk memberikan feedback pada artikel (helpful atau not helpful).

## Alur Bisnis
1. Pengguna klik "Helpful" atau "Not Helpful" pada artikel
2. Sistem validasi input (article_id, is_helpful)
3. Sistem cek apakah user sudah memberikan feedback sebelumnya
4. Sistem simpan feedback
5. Sistem return response sukses

## Route Yang Digunakan

**File:** `routes/web.php`
**Line:** 142

```php
Route::post('/chatbot/feedback', [ChatbotController::class, 'submitFeedback'])->name('chatbot.feedback');
```

**Penjelasan:**
- Route ini untuk submit feedback artikel

## Controller Yang Dipanggil

**File:** `app/Http/Controllers/ChatbotController.php`
**Line:** 779-823 (submitFeedback method)

```php
public function submitFeedback(Request $request)
{
    $request->validate([
        'article_id' => 'required|exists:articles,id',
        'is_helpful' => 'required|boolean',
    ]);

    // Cek apakah user sudah memberikan feedback
    $existingFeedback = ArticleFeedback::where('article_id', $request->article_id)
        ->where('session_id', session()->getId())
        ->first();

    if ($existingFeedback) {
        return response()->json([
            'success' => false,
            'message' => 'Anda sudah memberikan feedback untuk artikel ini.',
        ]);
    }

    // Simpan feedback
    ArticleFeedback::create([
        'article_id' => $request->article_id,
        'session_id' => session()->getId(),
        'is_helpful' => $request->is_helpful,
    ]);

    return response()->json([
        'success' => true,
        'message' => 'Terima kasih atas feedback Anda!',
    ]);
}
```

**Penjelasan:**
- Validasi input article_id dan is_helpful
- Cek apakah user sudah memberikan feedback berdasarkan session_id
- Jika belum, simpan feedback baru
- Return response sukses

## Service Yang Digunakan
Tidak ada service khusus untuk feedback. Logic ada di controller.

## Model Yang Digunakan

**File:** `app/Models\ArticleFeedback.php`

**Field yang digunakan:**
- `article_id`: ID artikel (foreign key)
- `session_id`: ID session pengguna
- `is_helpful`: Status helpful (boolean)

**Relasi yang digunakan:**
- `belongsTo(Article)`: Feedback belongs to artikel

**File:** `app/Models\Article.php`

**Relasi yang digunakan:**
- `hasMany(ArticleFeedback)`: Artikel has many feedback

## Query Database Yang Dieksekusi

```php
// Query 1: Cek feedback yang sudah ada
ArticleFeedback::where('article_id', $request->article_id)
    ->where('session_id', session()->getId())
    ->first()

// Query 2: Buat feedback baru
ArticleFeedback::create([
    'article_id' => $request->article_id,
    'session_id' => session()->getId(),
    'is_helpful' => $request->is_helpful,
])
```

**Penjelasan:**
- Query 1: Mencari feedback yang sudah ada berdasarkan article_id dan session_id
- Query 2: Insert feedback baru jika belum ada

## Database Yang Terlibat

**Tabel:** `article_feedback`

**Kolom:**
- `id`: ULID primary key
- `article_id`: foreignUlid ke articles
- `session_id`: string, ID session pengguna
- `is_helpful`: boolean, status helpful
- `created_at`: timestamp
- `updated_at`: timestamp

**Tabel:** `articles`

**Kolom yang digunakan:**
- `id`: ULID primary key

## View Yang Digunakan
Tidak ada view khusus untuk feedback. Tombol feedback ada di view chatbot.

## Event Yang Digunakan
Tidak ada event khusus untuk feedback.

## Notification Yang Digunakan
Tidak ada notification khusus untuk feedback.

## Email Yang Dikirim
Tidak ada email untuk feedback.

## Response Yang Dihasilkan

**Response sukses:**
```json
{
    "success": true,
    "message": "Terima kasih atas feedback Anda!"
}
```

**Response sudah ada feedback:**
```json
{
    "success": false,
    "message": "Anda sudah memberikan feedback untuk artikel ini."
}
```

---

# 14. CACHE MANAGEMENT

## Tujuan Fitur
Memungkinkan admin untuk clear cache chatbot (TF-IDF, vocabulary, dll).

## Alur Bisnis
1. Admin akses halaman cache management
2. Admin klik "Clear Cache"
3. Sistem clear cache TF-IDF
4. Sistem clear cache vocabulary
5. Sistem clear cache domain mapping
6. Sistem return response sukses

## Route Yang Digunakan

**File:** `routes/web.php`
**Line:** 143

```php
Route::post('/chatbot/clear-cache', [ChatbotController::class, 'clearCache'])->name('chatbot.clear-cache');
```

**Penjelasan:**
- Route ini untuk clear cache chatbot

## Controller Yang Dipanggil

**File:** `app/Http/Controllers/ChatbotController.php`
**Line:** 825-879 (clearCache method)

```php
public function clearCache(Request $request)
{
    $request->validate([
        'cache_type' => 'required|in:all,tfidf,vocabulary,domain',
    ]);

    $clearedCaches = [];

    switch ($request->cache_type) {
        case 'all':
            Cache::forget('chatbot:tfidf:idf');
            Cache::forget('chatbot_vocabulary');
            Cache::forget('chatbot:domain:mapping');
            Cache::forget('chatbot:retrieval:vectors:normalized');
            Cache::forget('chatbot:topics');
            $clearedCaches = ['tfidf', 'vocabulary', 'domain', 'vectors', 'topics'];
            break;

        case 'tfidf':
            Cache::forget('chatbot:tfidf:idf');
            Cache::forget('chatbot:retrieval:vectors:normalized');
            $clearedCaches = ['tfidf', 'vectors'];
            break;

        case 'vocabulary':
            Cache::forget('chatbot_vocabulary');
            $clearedCaches = ['vocabulary'];
            break;

        case 'domain':
            Cache::forget('chatbot:domain:mapping');
            Cache::forget('chatbot:topics');
            $clearedCaches = ['domain', 'topics'];
            break;
    }

    return response()->json([
        'success' => true,
        'message' => 'Cache berhasil di-clear: ' . implode(', ', $clearedCaches),
        'cleared_caches' => $clearedCaches,
    ]);
}
```

**Penjelasan:**
- Validasi input cache_type
- Clear cache sesuai tipe yang dipilih
- Return response dengan daftar cache yang di-clear

## Service Yang Digunakan
Tidak ada service khusus untuk cache management. Menggunakan Laravel Cache.

## Model Yang Digunakan
Tidak ada model khusus untuk cache management. Menggunakan Laravel Cache.

## Query Database Yang Dieksekusi
Tidak ada query database untuk cache management. Menggunakan Laravel Cache.

## Database Yang Terlibat
Tidak ada tabel database untuk cache management. Menggunakan Redis/ file cache.

## View Yang Digunakan
Tidak ada view khusus untuk cache management. Tombol clear cache ada di view admin.

## Event Yang Digunakan
Tidak ada event khusus untuk cache management.

## Notification Yang Digunakan
Tidak ada notification khusus untuk cache management.

## Email Yang Dikirim
Tidak ada email untuk cache management.

## Response Yang Dihasilkan

**Response sukses:**
```json
{
    "success": true,
    "message": "Cache berhasil di-clear: tfidf, vocabulary, domain, vectors, topics",
    "cleared_caches": ["tfidf", "vocabulary", "domain", "vectors", "topics"]
}
```

---

# 15. MANUAL SEARCH

## Tujuan Fitur
Memungkinkan pengguna untuk melakukan pencarian artikel manual tanpa chatbot.

## Alur Bisnis
1. Pengguna akses halaman pencarian
2. Pengguna input query
3. Sistem kirim query ke Typesense
4. Sistem return hasil pencarian
5. Pengguna klik artikel untuk melihat detail

## Route Yang Digunakan

**File:** `routes/web.php`
**Line:** 144

```php
Route::get('/chatbot/search', [ChatbotController::class, 'search'])->name('chatbot.search');
```

**Penjelasan:**
- Route ini untuk pencarian manual

## Controller Yang Dipanggil

**File:** `app/Http/Controllers/ChatbotController.php`
**Line:** 639-713 (search method)

```php
public function search(Request $request)
{
    $query = $request->query('q', '');

    if (empty($query)) {
        return view('chatbot.search', [
            'query' => $query,
            'results' => [],
        ]);
    }

    $typesenseService = app(TypesenseService::class);

    if (!$typesenseService->isConnected()) {
        // Fallback ke database search
        $articles = Article::where('is_published', true)
            ->where('publish_status', 'approved')
            ->where(function ($q) use ($query) {
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('content', 'like', "%{$query}%")
                    ->orWhere('keywords', 'like', "%{$query}%");
            })
            ->with('category')
            ->paginate(10);

        return view('chatbot.search', [
            'query' => $query,
            'results' => $articles,
            'typesense_used' => false,
        ]);
    }

    // Search via Typesense
    $typesenseResults = $typesenseService->search($query, 20);

    if (!$typesenseResults['success'] || empty($typesenseResults['results'])) {
        return view('chatbot.search', [
            'query' => $query,
            'results' => [],
            'typesense_used' => true,
        ]);
    }

    // Ambil artikel berdasarkan ID
    $articleIds = array_column($typesenseResults['results'], 'id');
    $articles = Article::whereIn('id', $articleIds)
        ->where('is_published', true)
        ->where('publish_status', 'approved')
        ->with('category')
        ->get()
        ->sortBy(function ($article) use ($articleIds) {
            return array_search($article->id, $articleIds);
        });

    return view('chatbot.search', [
        'query' => $query,
        'results' => $articles,
        'typesense_used' => true,
    ]);
}
```

**Penjelasan:**
- Ambil query dari request
- Jika query kosong, return view kosong
- Cek koneksi Typesense
- Jika Typesense tidak tersedia, fallback ke database search
- Search via Typesense
- Ambil artikel berdasarkan ID kandidat
- Return view dengan hasil pencarian

## Service Yang Digunakan

**File:** `app/Services/Chatbot\TypesenseService.php`

**Penjelasan:**
- Lihat bagian "TYPESENSE INTEGRATION"

## Model Yang Digunakan

**File:** `app/Models\Article.php`

**Penjelasan:**
- Lihat bagian "CHATBOT QUERY PROCESSING"

## Query Database Yang Dieksekusi

```php
// Query 1: Fallback database search
Article::where('is_published', true)
    ->where('publish_status', 'approved')
    ->where(function ($q) use ($query) {
        $q->where('title', 'like', "%{$query}%")
            ->orWhere('content', 'like', "%{$query}%")
            ->orWhere('keywords', 'like', "%{$query}%");
    })
    ->with('category')
    ->paginate(10)

// Query 2: Ambil artikel berdasarkan ID kandidat
Article::whereIn('id', $articleIds)
    ->where('is_published', true)
    ->where('publish_status', 'approved')
    ->with('category')
    ->get()
```

**Penjelasan:**
- Query 1: Fallback search jika Typesense tidak tersedia
- Query 2: Mengambil artikel berdasarkan ID kandidat dari Typesense

## Database Yang Terlibat

**Tabel:** `articles`

**Penjelasan:**
- Lihat bagian "CHATBOT QUERY PROCESSING"

**Tabel:** `categories`

**Penjelasan:**
- Lihat bagian "CHATBOT QUERY PROCESSING"

## View Yang Digunakan

**File:** `resources/views/chatbot/search.blade.php`

**Penjelasan:**
- Form input pencarian
- Daftar hasil pencarian
- Pagination

## Event Yang Digunakan
Tidak ada event khusus untuk manual search.

## Notification Yang Digunakan
Tidak ada notification khusus untuk manual search.

## Email Yang Dikirim
Tidak ada email untuk manual search.

## Response Yang Dihasilkan

**Response view:**
- View HTML dengan hasil pencarian

---

# KESIMPULAN

Sistem chatbot HelpMinfo memiliki fitur lengkap meliputi:
1. Hybrid retrieval (Typesense 85% + TF-IDF 15%) untuk akurasi maksimal
2. Domain detection untuk filtering kategori dan domain adjustment
3. Conversation flow untuk greeting dan klarifikasi query ambigu
4. Preprocessing untuk koreksi typo dan tokenisasi
5. Cosine similarity untuk perhitungan kemiripan
6. Vocabulary service untuk koreksi typo cerdas berbasis Levenshtein distance
7. Important phrase service untuk boosting frasa penting
8. Diversifikasi untuk variasi kategori hasil
9. Eskalasi ke tiket jika chatbot tidak membantu
10. Article feedback untuk pengumpulan data kualitas
11. Cache management untuk performa
12. Manual search untuk pencarian artikel tanpa chatbot

Sistem menggunakan:
- Typesense untuk full-text search dengan fuzzy matching
- TF-IDF untuk reranking semantik ringan
- Laravel Cache untuk performa (IDF, vocabulary, domain mapping)
- Database untuk penyimpanan artikel dan feedback

---

# 16. DETAIL KONSTANTA DAN THRESHOLD - ADVANCED RETRIEVAL SERVICE

## File: app/Services/Chatbot/AdvancedRetrievalService.php

### Konstanta Similarity Threshold
```php
// Threshold similarity minimum untuk hasil dianggap relevan
private const SIMILARITY_THRESHOLD = 0.12;          // 12% - threshold minimum

// Threshold untuk similarity tinggi
private const HIGH_SIMILARITY_THRESHOLD = 0.35;     // 35% - similarity tinggi

// Threshold untuk similarity sangat tinggi
private const VERY_HIGH_SIMILARITY_THRESHOLD = 0.55; // 55% - similarity sangat tinggi

// Threshold aman untuk fallback
private const SAFE_FALLBACK_THRESHOLD = 0.18;        // 18% - threshold fallback aman
```

### Konstanta Retrieval Limits
```php
// Jumlah hasil maksimal yang dikembalikan
private const TOP_K_RESULTS = 5;                      // Top 5 hasil

// Threshold kegagalan untuk eskalasi
private const FAILURE_THRESHOLD = 3;                 // 3 kegagalan sebelum eskalasi

// Memori maksimal untuk tracking kegagalan
private const MAX_FAILURE_MEMORY = 10;               // 10 kegagalan disimpan
```

### Konstanta Bobot Faktor Ranking (Total 100%)
```php
// Bobot cosine similarity
private const WEIGHT_COSINE = 0.30;                  // 30% - bobot cosine similarity

// Bobot title overlap
private const WEIGHT_TITLE_OVERLAP = 0.25;           // 25% - bobot title overlap

// Bobot domain match
private const WEIGHT_DOMAIN_MATCH = 0.15;            // 15% - bobot domain match

// Bobot query coverage
private const WEIGHT_QUERY_COVERAGE = 0.15;         // 15% - bobot query coverage

// Bobot exact phrase match
private const WEIGHT_EXACT_PHRASE = 0.10;           // 10% - bobot exact phrase

// Bobot diversifikasi
private const WEIGHT_DIVERSIFICATION = 0.05;         // 5% - bobot diversifikasi
```

### Konstanta Bonus dan Penalty
```php
// Faktor boost untuk judul
private const TITLE_BOOST_FACTOR = 2.0;              // 2.0x boost untuk judul

// Bonus untuk exact phrase match
private const EXACT_PHRASE_BONUS = 0.3;              // 0.3 - bonus exact phrase

// Bonus untuk full coverage
private const FULL_COVERAGE_BONUS = 0.25;            // 0.25 - bonus full coverage

// Bonus untuk bigram match
private const BIGRAM_MATCH_BONUS = 0.2;              // 0.2 - bonus bigram

// Penalty untuk domain mismatch
private const DOMAIN_PENALTY = -0.5;                 // -0.5 - penalty domain mismatch

// Penalty kuat untuk domain mismatch
private const STRONG_DOMAIN_PENALTY = -0.8;          // -0.8 - penalty kuat

// Bobot untuk low priority
private const LOW_PRIORITY_WEIGHT = 0.1;             // 0.1 - bobot low priority

// Maksimal hasil per kategori untuk diversifikasi
private const MAX_RESULTS_PER_CATEGORY = 2;          // 2 artikel per kategori
```

---

# 17. MULTI-INTENT DETECTION

## File: app/Services/Chatbot/AdvancedRetrievalService.php

### Tujuan Fitur
Mendeteksi dan menangani query yang mengandung multiple intents (misalnya: "wifi dan printer error") untuk memberikan hasil yang seimbang untuk setiap intent.

### Konstanta Multi-Intent
```php
// Tidak ada konstanta khusus untuk multi-intent
// Menggunakan konstanta yang sudah ada:
// - SIMILARITY_THRESHOLD (0.12) untuk filter hasil lemah
// - TOP_K_RESULTS (5) untuk limit hasil akhir
```

### Method detectMultiIntent
```php
private function detectMultiIntent(string $query): array
```
**Fungsi:** Mendeteksi apakah query mengandung multiple intents menggunakan pattern splitting.

**Kode Lengkap:**
```php
private function detectMultiIntent(string $query): array
{
    $intents = [];
    
    $patterns = [
        '/\s+dan\s+/i',
        '/\s+atau\s+/i',
        '/\s+dengan\s+/i',
        '/\s+serta\s+/i',
        '/\s+,\s+/',
    ];
    
    $parts = preg_split($patterns[0], $query);
    
    foreach ($patterns as $pattern) {
        $splitParts = preg_split($pattern, $query);
        if (count($splitParts) > count($parts)) {
            $parts = $splitParts;
        }
    }
    
    $meaningfulParts = array_filter($parts, fn($p) => mb_strlen(trim($p)) >= 3);
    
    if (count($meaningfulParts) > 1) {
        return array_values($meaningfulParts);
    }
    
    return [$query];
}
```

**Alur Proses:**
1. Definisikan pattern untuk pemisahan intent:
   - `\s+dan\s+` (kata "dan" dengan spasi)
   - `\s+atau\s+` (kata "atau" dengan spasi)
   - `\s+dengan\s+` (kata "dengan" dengan spasi)
   - `\s+serta\s+` (kata "serta" dengan spasi)
   - `\s+,\s+` (koma dengan spasi)
2. Coba split query dengan setiap pattern
3. Gunakan pattern yang menghasilkan paling banyak parts
4. Filter parts yang memiliki panjang >= 3 karakter
5. Jika lebih dari 1 meaningful part, return array intents
6. Jika tidak, return single intent dalam array

**Contoh:**
- Input: "wifi tidak terhubung dan printer error"
- Output: `["wifi tidak terhubung", "printer error"]`

- Input: "wifi, internet, printer"
- Output: `["wifi", "internet", "printer"]`

### Method multiIntentRetrieval
```php
private function multiIntentRetrieval(array $intents, int $limit): array
```
**Fungsi:** Melakukan retrieval untuk multiple intents dan menggabungkan hasil secara seimbang.

**Kode Lengkap:**
```php
private function multiIntentRetrieval(array $intents, int $limit): array
{
    $intentResults = [];
    $originalQuery = implode(' dan ', $intents);
    $allSeenIds = [];
    
    // Save the saat ini debug info ke restore setelah single intent retrievals
    $savedDebugInfo = $this->debugInfo;
    
    // Langkah 1: Retrieval hasil untuk SETIAP intent secara terpisah dengan kandidat pool lebih besar
    // Kita retrieval lebih banyak kandidat dari yang dibutuhkan untuk memastikan cukup untuk merging seimbang
    $candidatesPerIntent = max(10, $limit * 2);
    
    foreach ($intents as $index => $intent) {
        // Buat debug info baru untuk setiap intent retrieval
        $this->debugInfo = [
            'original_query' => $intent,
            'stages' => [],
            'scores' => [],
        ];
        
        $normalizedIntent = $this->normalizeTypos($intent);
        $normalizedIntent = $this->normalizeSynonyms($normalizedIntent);
        
        // Ambil lebih banyak kandidat dari fair share untuk punya opsi merging
        $result = $this->singleIntentRetrieval($normalizedIntent, $candidatesPerIntent);
        
        // Tag setiap hasil dengan source intent-nya untuk tracking
        foreach ($result['results'] as &$article) {
            $article['_intent_index'] = $index;
            $article['_intent_query'] = $intent;
        }
        
        $intentResults[$index] = $result['results'];
        
        $this->debugInfo['intent_retrieval'][$index] = [
            'intent' => $intent,
            'normalized' => $normalizedIntent,
            'results_count' => count($result['results']),
            'results' => array_map(fn($r) => [
                'id' => $r['id'],
                'title' => $r['title'],
                'score' => $r['final_score'],
            ], $result['results']),
        ];
    }
    
    // Restore debug info utama
    $this->debugInfo = $savedDebugInfo;
    $this->debugInfo['intents'] = $intents;
    
    // Langkah 2: Balanced merging - interleave hasil dari each intent
    $finalResults = $this->balancedMerge($intentResults, $limit, $allSeenIds);
    
    $this->trackRetrievalResult($originalQuery, $finalResults);
    
    $this->debugInfo['merge_strategy'] = 'balanced_interleaving';
    $this->debugInfo['intents_count'] = count($intents);
    $this->debugInfo['final_results_count'] = count($finalResults);
    
    return [
        'results' => $finalResults,
        'query' => $originalQuery,
        'total' => count($finalResults),
        'threshold_met' => !empty($finalResults),
        'max_similarity' => !empty($finalResults) ? $finalResults[0]['final_score'] : 0,
        'is_multi_intent' => true,
        'intents' => $intents,
        'debug' => config('app.debug', false) ? $this->debugInfo : null,
    ];
}
```

**Alur Proses:**
1. Hitung kandidat per intent: `max(10, limit * 2)` (minimal 10 kandidat)
2. Untuk setiap intent:
   - Normalize typo dan synonym
   - Panggil `singleIntentRetrieval` dengan candidates per intent
   - Tag setiap hasil dengan `_intent_index` dan `_intent_query`
   - Simpan hasil di `intentResults[index]`
3. Panggil `balancedMerge` untuk menggabungkan hasil secara seimbang
4. Track retrieval result untuk query original
5. Return hasil dengan flag `is_multi_intent = true`

**Konstanta yang digunakan:**
- `candidatesPerIntent = max(10, limit * 2)` - minimal 10 kandidat per intent

### Method balancedMerge
```php
private function balancedMerge(array $intentResults, int $limit, array &$seenIds): array
```
**Fungsi:** Menggabungkan hasil dari multiple intents dengan representasi yang seimbang (fair share).

**Kode Lengkap:**
```php
private function balancedMerge(array $intentResults, int $limit, array &$seenIds): array
{
    $numIntents = count($intentResults);
    if ($numIntents === 0) {
        return [];
    }
    
    // Menghitung kuota fair per intent
    $quotaPerIntent = max(1, (int) ceil($limit / $numIntents));
    
    // Melacak jumlah hasil per intent
    $resultsPerIntent = array_fill(0, $numIntents, 0);
    
    // Melacak posisi saat ini di setiap intent
    $currentPosition = array_fill(0, $numIntents, 0);
    
    $finalResults = [];
    $totalResults = 0;
    
    // Tahap 1: Round-robin untuk memberikan kuota fair ke setiap intent
    for ($intentIndex = 0; $intentIndex < $numIntents; $intentIndex++) {
        if ($totalResults >= $limit) {
            break;
        }
        
        $countForThisIntent = 0;
        $position = 0;
        $intentResultCount = count($intentResults[$intentIndex]);
        
        while ($countForThisIntent < $quotaPerIntent && $totalResults < $limit && $position < $intentResultCount) {
            $candidate = $intentResults[$intentIndex][$position];
            $position++;
            
            // Melewati duplikat
            if (isset($seenIds[$candidate['id']])) {
                continue;
            }
            
            // Melewati hasil di bawah threshold
            if (($candidate['final_score'] ?? 0) < self::SIMILARITY_THRESHOLD * 0.5) {
                continue;
            }
            
            // Menambahkan hasil
            $seenIds[$candidate['id']] = true;
            $resultsPerIntent[$intentIndex]++;
            $totalResults++;
            $countForThisIntent++;
            
            // Membersihkan field tracking internal
            unset($candidate['_intent_index'], $candidate['_intent_query']);
            $candidate['matched_intent'] = $intentIndex;
            
            $finalResults[] = $candidate;
        }
        
        // Memperbarui posisi untuk tahap overflow
        $currentPosition[$intentIndex] = $position;
    }
    
    // Tahap 2: Round-robin tambahan jika masih ada ruang
    if ($totalResults < $limit) {
        $moreRounds = true;
        $maxExtraRounds = 3;
        $round = 0;
        
        while ($moreRounds && $totalResults < $limit && $round < $maxExtraRounds) {
            $moreRounds = false;
            $round++;
            
            for ($intentIndex = 0; $intentIndex < $numIntents; $intentIndex++) {
                if ($totalResults >= $limit) {
                    break 2;
                }
                
                $position = $currentPosition[$intentIndex];
                $intentResultCount = count($intentResults[$intentIndex]);
                
                while ($position < $intentResultCount && $totalResults < $limit) {
                    $candidate = $intentResults[$intentIndex][$position];
                    $position++;
                    
                    if (isset($seenIds[$candidate['id']])) {
                        continue;
                    }
                    
                    if (($candidate['final_score'] ?? 0) < self::SIMILARITY_THRESHOLD * 0.5) {
                        continue;
                    }
                    
                    $seenIds[$candidate['id']] = true;
                    $resultsPerIntent[$intentIndex]++;
                    $totalResults++;
                    $moreRounds = true;
                    
                    unset($candidate['_intent_index'], $candidate['_intent_query']);
                    $candidate['matched_intent'] = $intentIndex;
                    
                    $finalResults[] = $candidate;
                }
                
                $currentPosition[$intentIndex] = $position;
            }
        }
    }
    
    return $finalResults;
}
```

**Alur Proses:**

**Tahap 1: Round-robin untuk kuota fair**
1. Hitung kuota per intent: `max(1, ceil(limit / numIntents))`
2. Untuk setiap intent:
   - Ambil hasil hingga kuota terpenuhi
   - Lewati duplikat (berdasarkan article ID)
   - Lewati hasil di bawah threshold: `SIMILARITY_THRESHOLD * 0.5` (0.06 = 6%)
   - Tag hasil dengan `matched_intent`
   - Tambahkan ke final results

**Tahap 2: Round-robin tambahan (overflow)**
1. Jika masih ada ruang (< limit), lakukan round-robin tambahan
2. Maksimal 3 extra rounds
3. Lanjutkan mengambil hasil dari setiap intent
4. Hentikan jika limit terpenuhi atau tidak ada hasil tersisa

**Konstanta yang digunakan:**
- `quotaPerIntent = max(1, ceil(limit / numIntents))` - kuota fair per intent
- `SIMILARITY_THRESHOLD * 0.5 = 0.06` - threshold untuk filter hasil lemah dalam merge
- `maxExtraRounds = 3` - maksimal extra rounds untuk overflow

**Contoh:**
- Input: 2 intents, limit = 5
- Kuota per intent: `ceil(5 / 2) = 3`
- Tahap 1: Intent 0 ambil 3, Intent 1 ambil 3 (total 6, tapi limit 5)
- Hasil akhir: 3 dari Intent 0, 2 dari Intent 1 (seimbang)

### Response Multi-Intent
```json
{
    "results": [...],
    "query": "wifi tidak terhubung dan printer error",
    "total": 5,
    "threshold_met": true,
    "max_similarity": 0.85,
    "is_multi_intent": true,
    "intents": ["wifi tidak terhubung", "printer error"],
    "debug": {...}
}
```

### Integrasi dengan ChatbotController
**File:** `app/Http/Controllers/ChatbotController.php`

**Method getResponse:**
```php
// 3. Perform retrieval (handles multi-intent splitting internally)
$result = $this->retrievalService->retrieve($userMessage, 5);

// 5. Add diversification info to response
if (!empty($result['results'])) {
    // Multi-intent info
    if (!empty($result['is_multi_intent'])) {
        $response['multi_intent'] = [
            'detected' => true,
            'intents' => $result['intents'] ?? [],
        ];
    }
}
```

**Penjelasan:**
- Controller tidak perlu menangani multi-intent secara eksplisit
- AdvancedRetrievalService menangani deteksi dan merging secara internal
- Response frontend menyertakan info multi-intent untuk UI

### Integrasi di Method Retrieve
**File:** `app/Services/Chatbot/AdvancedRetrievalService.php`

**Method retrieve (bagian multi-intent):**
```php
public function retrieve(string $query, int $limit = 5): array
{
    // ... domain detection dan preprocessing ...

    $intents = $this->detectMultiIntent($query);
    $this->debugInfo['intents'] = $intents;
    $this->logStage('multi_intent_detection', $query, json_encode($intents));

    if (count($intents) > 1) {
        return $this->multiIntentRetrieval($intents, $limit);
    }

    return $this->singleIntentRetrieval($normalizedQuery, $limit);
}
```

**Penjelasan:**
- Method `retrieve` memanggil `detectMultiIntent` setelah preprocessing
- Jika lebih dari 1 intent terdeteksi, panggil `multiIntentRetrieval`
- Jika hanya 1 intent, panggil `singleIntentRetrieval` (alur normal)
- Deteksi multi-intent terjadi secara otomatis tanpa intervensi manual

### Keunggulan Multi-Intent Detection
1. **Fair Representation:** Setiap intent mendapat kuota yang seimbang
2. **No Dominance:** Mencegah satu intent mendominasi hasil
3. **Duplicate Removal:** Artikel yang sama tidak muncul untuk multiple intents
4. **Quality Filter:** Hasil di bawah threshold (6%) di-filter dalam merge
5. **Overflow Handling:** Jika ada ruang, tambahkan hasil dari intents yang masih punya kandidat

### Pattern Splitting yang Didukung
| Pattern | Contoh Query | Hasil Split |
|---------|--------------|-------------|
| `dan` | "wifi dan printer error" | `["wifi", "printer error"]` |
| `atau` | "wifi atau internet" | `["wifi", "internet"]` |
| `dengan` | "wifi dengan printer" | `["wifi", "printer"]` |
| `serta` | "wifi serta internet" | `["wifi", "internet"]` |
| `,` | "wifi, internet, printer" | `["wifi", "internet", "printer"]` |

---

# 18. DETAIL KONSTANTA DAN THRESHOLD - TFIDF SERVICE

## File: app/Services/Chatbot/TfidfService.php

### Konstanta Cache
```php
// Cache key untuk IDF scores
private const IDF_CACHE_KEY = 'chatbot:tfidf:idf_scores';

// TTL cache IDF (24 jam)
private const IDF_CACHE_TTL = 86400;                 // 86400 detik = 24 jam
```

### Konstanta Low Priority Terms
```php
// Bobot untuk low priority terms (generic terms)
private const LOW_PRIORITY_WEIGHT = 0.1;             // 0.1 = 10% dari bobot normal

// Daftar low priority terms (generic helpdesk terms)
private array $lowPriorityTerms = [
    'cara', 'bagaimana', 'bagaimanakah', 'apakah', 'apa', 'kenapa', 'mengapa',
    'tolong', 'bantu', 'help', 'please', 'mohon', 'silakan',
    'masalah', 'problem', 'issue', 'trouble', 'gangguan',
    'saya', 'user', 'pengguna', 'kita', 'kami',
    'bisa', 'dapat', 'bisa tidak', 'dapat tidak',
    'tidak', 'tidak bisa', 'tidak dapat', 'gagal',
];
```

### Method calculateTF
```php
// Menghitung Term Frequency (TF)
// Formula: TF(t, d) = count(t, d) / sum(count(t', d))
// t = term, d = document
```

### Method calculateIDF
```php
// Menghitung Inverse Document Frequency (IDF)
// Formula: IDF(t) = log((N + 1) / (df(t) + 1)) + 1
// N = total documents, df(t) = document frequency
// Menggunakan smoothed IDF untuk menghindari pembagian dengan nol
```

### Method calculateTFIDF
```php
// Menghitung TF-IDF
// Formula: TF-IDF(t, d) = TF(t, d) × IDF(t)
```

---

# 18. DETAIL KONSTANTA DAN THRESHOLD - DOMAIN DETECTION SERVICE

## File: app/Services/Chatbot/DomainDetectionService.php

### Konstanta Cache
```php
// Cache key untuk domain mapping
private const DOMAIN_CACHE_KEY = 'chatbot:domain:mapping';

// TTL cache domain (1 jam)
private const DOMAIN_CACHE_TTL = 3600;               // 3600 detik = 1 jam
```

### Konstanta Threshold
```php
// Minimum vocabulary overlap untuk domain detection
private const MIN_VOCABULARY_OVERLAP = 0.20;         // 20% - minimum overlap

// Minimum IT tokens untuk domain detection
private const MIN_IT_TOKENS = 1;                     // 1 token IT minimum

// Threshold confidence untuk domain detection
private const DOMAIN_CONFIDENCE_THRESHOLD = 0.05;    // 5% - minimum confidence
```

### Konstanta Pesan
```php
// Pesan untuk out-of-domain
private const OUT_OF_DOMAIN_MESSAGE = 'Maaf, saya hanya dapat membantu masalah terkait IT.';
```

### Daftar Curated Domains
```php
// Daftar domain IT yang valid
private array $curatedDomains = [
    'wifi', 'internet', 'jaringan', 'network',
    'printer', 'printing', 'cetak',
    'komputer', 'computer', 'laptop', 'hardware',
    'email', 'surel', 'mail',
    'website', 'aplikasi', 'software',
    'akun', 'account', 'login',
    'security', 'keamanan', 'virus', 'malware',
    'bsod', 'blue screen', 'windows',
    'server', 'driver',
    'office', 'excel', 'word', 'powerpoint',
];
```

### Daftar Generic Terms (diabaikan untuk domain detection)
```php
private array $genericTerms = [
    'cara', 'bagaimana', 'apakah', 'apa', 'kenapa', 'mengapa',
    'tolong', 'bantu', 'help', 'please', 'mohon', 'silakan',
    'masalah', 'problem', 'issue', 'trouble', 'gangguan',
    'saya', 'user', 'pengguna', 'kita', 'kami',
    'bisa', 'dapat', 'tidak', 'gagal',
    'yang', 'dan', 'atau', 'di', 'ke', 'dari', 'pada', 'untuk',
];
```

### Daftar IT Domain Vocabulary (komprehensif)
```php
private array $itDomainVocabulary = [
    // Network terms
    'wifi', 'wi-fi', 'wireless', 'wlan', 'hotspot', 'access point', 'ap',
    'router', 'modem', 'switch', 'hub', 'lan', 'wan', 'vpn',
    'internet', 'inet', 'bandwidth', 'latency', 'ping', 'dns', 'ip',
    
    // Hardware terms
    'komputer', 'computer', 'laptop', 'pc', 'desktop', 'notebook',
    'printer', 'scanner', 'monitor', 'keyboard', 'mouse', 'speaker',
    'hardware', 'cpu', 'ram', 'hdd', 'ssd', 'gpu', 'motherboard',
    
    // Software terms
    'software', 'aplikasi', 'application', 'program', 'driver',
    'windows', 'linux', 'macos', 'android', 'ios',
    'office', 'excel', 'word', 'powerpoint', 'outlook',
    
    // Security terms
    'virus', 'malware', 'trojan', 'ransomware', 'spyware', 'adware', 'worm',
    'phishing', 'antivirus', 'firewall', 'encryption', 'password',
    
    // Email terms
    'email', 'surel', 'mail', 'gmail', 'outlook', 'yahoo mail',
    
    // Error terms
    'error', 'bsod', 'blue screen', 'hang', 'freeze', 'crash',
    'not responding', 'timeout', '404', '500', '503',
];
```

### Daftar Never Reject Tokens (selalu dianggap in-domain)
```php
private array $neverRejectTokens = [
    'virus', 'malware', 'ransomware', 'trojan', 'phishing',
    'wifi', 'internet', 'printer', 'komputer', 'email',
    'docker', 'kubernetes', 'api', 'database', 'sql',
];
```

### Daftar Out-of-Domain Keywords (langsung reject)
```php
private array $outOfDomainKeywords = [
    'resep', 'masak', 'kuliner', 'makanan',
    'fashion', 'busana', 'pakaian',
    'musik', 'lagu', 'film', 'movie',
    'olahraga', 'sport', 'sepakbola', 'basket',
    'kesehatan', 'obat', 'dokter', 'rumah sakit',
    'keuangan', 'bank', 'saham', 'investasi',
];
```

---

# 19. DETAIL KONSTANTA DAN THRESHOLD - IMPORTANT PHRASE SERVICE

## File: app/Services/Chatbot/ImportantPhraseService.php

### Konstanta Bonus Phrase Match
```php
// Bonus untuk phrase match di konten
private const PHRASE_MATCH_BONUS = 0.4;              // 0.4 - bonus phrase match konten

// Bonus untuk phrase match di judul
private const TITLE_PHRASE_BONUS = 0.6;              // 0.6 - bonus phrase match judul

// Bonus untuk exact query phrase di judul
private const EXACT_QUERY_PHRASE_BONUS = 0.8;        // 0.8 - bonus exact query phrase

// Bonus untuk phrase yang sejalan dengan kategori
private const PHRASE_CATEGORY_BOOST = 0.15;          // 0.15 - bonus kategori

// Panjang frasa minimum
private const MIN_PHRASE_LENGTH = 2;                  // 2 kata minimum
```

### Daftar Important Phrases
```php
private array $importantPhrases = [
    // Connectivity issues
    'tidak terhubung', 'tidak connect', 'tidak konek', 'koneksi gagal',
    'gagal connect', 'gagal terhubung', 'putus nyambung', 'sering putus',
    'tidak bisa connect', 'tidak bisa terhubung',
    
    // Detection issues
    'tidak terbaca', 'tidak terdeteksi', 'tidak muncul', 'tidak kedetect',
    'tidak dikenali',
    
    // Authentication issues
    'gagal login', 'tidak bisa login', 'gagal masuk', 'tidak bisa masuk',
    'terkunci', 'akun terkunci',
    
    // Performance issues
    'tidak merespon', 'tidak respon', 'tidak responsif', 'tidak bereaksi',
    'sangat lambat', 'lemot parah', 'macet total', 'hang', 'freeze',
    'not responding',
    
    // Functionality issues
    'tidak berfungsi', 'tidak bisa digunakan', 'tidak bisa dipakai',
    'tidak mau', 'tidak bisa', 'gagal berfungsi',
    
    // Display issues
    'tidak muncul', 'hilang tiba-tiba', 'menghilang', 'blank',
    'layar hitam', 'layar biru',
    
    // Error issues
    'error terus', 'muncul error', 'pesan error', 'kode error',
    'notifikasi error',
];
```

### Method calculateNgramOverlap
```php
// Menghitung n-gram overlap (bigram dan trigram)
// Bigram: 2 kata berurutan
// Trigram: 3 kata berurutan
// Formula: overlap = (n-gram matches) / (total n-grams)
```

---

# 20. DETAIL KONSTANTA DAN THRESHOLD - VOCABULARY SERVICE

## File: app/Services/Chatbot/VocabularyService.php

### Konstanta Cache
```php
// Cache key untuk vocabulary
private const CACHE_KEY = 'chatbot_vocabulary';

// TTL cache vocabulary (1 jam)
private const CACHE_TTL = 3600;                      // 3600 detik = 1 jam
```

### Konstanta Threshold Similarity
```php
// Threshold similarity minimum (0.0 - 1.0)
private const MIN_SIMILARITY = 0.70;                // 70% - similarity minimum

// Threshold similarity untuk kata panjang (>8 karakter)
private const MIN_SIMILARITY_LONG_WORDS = 0.65;      // 65% - untuk kata panjang

// Panjang kata minimum untuk koreksi
private const MIN_WORD_LENGTH = 3;                   // 3 karakter minimum

// Maksimal karakter berulang (dikompresi ke 1)
private const MAX_REPEATED_CHARS = 1;                // 1 karakter maksimal
```

### Konstanta Adaptive Levenshtein Distance
```php
// Kata pendek (<=5 karakter): max distance = 1
// Kata sedang (6-8 karakter): max distance = 2
// Kata panjang (>8 karakter): max distance = 3
```

### Daftar Curated Typo Map
```php
private array $curatedTypoMap = [
    // Ransomware/Malware
    'ransomwre' => 'ransomware', 'malwere' => 'malware', 'malwre' => 'malware',
    
    // Virus
    'virusss' => 'virus', 'viruss' => 'virus', 'viruse' => 'virus',
    
    // WiFi
    'wfi' => 'wifi', 'wiifi' => 'wifi', 'wfii' => 'wifi', 'wifii' => 'wifi',
    
    // Printer
    'pritner' => 'printer', 'prnter' => 'printer', 'printter' => 'printer',
    
    // Computer
    'kompter' => 'komputer', 'komputr' => 'komputer', 'kompoter' => 'komputer',
    
    // Internet
    'intenet' => 'internet', 'internrt' => 'internet', 'intrnet' => 'internet',
    
    // Email
    'emai' => 'email', 'emaill' => 'email', 'emil' => 'email', 'emial' => 'email',
];
```

### Method getAdaptiveMaxDistance
```php
// Mengembalikan max distance adaptif berdasarkan panjang kata
// - Kata <=5 karakter: return 1
// - Kata 6-8 karakter: return 2
// - Kata >8 karakter: return 3
```

### Method getAdaptiveMinSimilarity
```php
// Mengembalikan threshold similarity adaptif
// - Kata >8 karakter: return 0.65 (65%)
// - Kata lain: return 0.70 (70%)
```

### Method calculateSimilarity
```php
// Formula: similarity = 1.0 - (distance / max_length)
// distance = Levenshtein distance
// max_length = max(len(str1), len(str2))
```

---

# 21. DETAIL KONSTANTA DAN THRESHOLD - TYPESENSE SERVICE

## File: app/Services/Chatbot/TypesenseService.php

### Konstanta Koneksi
```php
// Connection timeout
private const CONNECTION_TIMEOUT_SECONDS = 15;       // 15 detik timeout koneksi
```

### Konstanta Pencarian
```php
// Query by weights (bobot field pencarian)
'query_by_weights' => '8,6,4,2'                    // title:8, keywords:6, category:4, content:2

// Typo tolerance
'num_typos' => 4                                    // Maksimal 4 typo
'min_len_1typo' => 2                                // Minimum 2 karakter untuk 1 typo
'min_len_2typo' => 4                                // Minimum 4 karakter untuk 2 typo

// Typo tokens threshold
'typo_tokens_threshold' => 3                         // 3 token typo threshold

// Search mode
'prioritize_exact_match' => true                     // Prioritaskan exact match
'text_match_type' => 'max_score'                    // Gunakan max score
'prefix' => 'always'                                // Prefix search selalu aktif
'infix' => 'always'                                 // Infix search selalu aktif
'infix_score' => 'max_score'                        // Max score untuk infix
'drop_tokens_threshold' => 0                        // Jangan drop token
'exhaustive_search' => true                         // Exhaustive search
```

### Daftar Security Keywords (untuk boosting kategori)
```php
private array $securityKeywords = [
    'virus', 'viruss', 'viruses', 'malware', 'ransomware', 'ransomwre',
    'trojan', 'trojans', 'phishing', 'spyware', 'adware', 'worm',
    'security', 'keamanan', 'antivirus', 'anti-virus',
];
```

### Daftar Intent Synonym Sets
```php
private array $intentSynonymSets = [
    'connectivity' => ['connect', 'konek', 'terhubung', 'tersambung', 'online',
                      'connection', 'koneksi', 'sambung', 'nyambung'],
    'security' => ['virus', 'malware', 'trojan', 'ransomware',
                   'spyware', 'adware', 'worm', 'phishing'],
    'printing' => ['print', 'printer', 'cetak', 'ngeprint',
                   'printing', 'mencetak', 'percetakan'],
    'authentication' => ['login', 'signin', 'sign-in', 'masuk akun',
                         'log in', 'log-in', 'masuk', 'sign up', 'signup',
                         'register', 'daftar'],
    'network' => ['wifi', 'internet', 'jaringan', 'network',
                  'lan', 'wireless', 'nirkabel', 'router',
                  'modem', 'access point', 'hotspot'],
    'failure' => ['gagal', 'error', 'gagal konek', 'tidak bisa',
                  'ga bisa', 'gak bisa', 'tidak connect',
                  'tidak terhubung', 'masalah', 'issue', 'kendala'],
    'speed' => ['lambat', 'slow', 'lemot', 'speed', 'kecepatan',
                'bandwidth', 'lag', 'lagging', 'buffering'],
    'email' => ['email', 'surel', 'mail', 'surat elektronik',
                'gmail', 'outlook', 'yahoo mail'],
];
```

---

# 22. DETAIL KONSTANTA DAN THRESHOLD - CONVERSATION FLOW SERVICE

## File: app/Services/Chatbot/ConversationFlowService.php

### Konstanta Ambiguous Patterns
```php
private array $ambiguousPatterns = [
    'lemot', 'lambat', 'error', 'eror',
    'tidak bisa', 'gak bisa', 'ga bisa',
    'bermasalah', 'masalah', 'rusak', 'mati',
    'hilang', 'blank', 'kosong', 'no signal',
    'tidak muncul', 'gak muncul',
];
```

### Konstanta Domain Terms
```php
private array $domainTerms = [
    'wifi', 'internet', 'printer', 'komputer', 'laptop',
    'software', 'aplikasi', 'email', 'jaringan', 'router', 'modem',
    'lan', 'server', 'dns', 'ip', 'usb', 'bluetooth',
    'monitor', 'keyboard', 'mouse', 'scanner', 'webcam',
    'speaker', 'microphone', 'windows', 'linux', 'android', 'ios',
    'office', 'browser', 'chrome', 'firefox', 'excel', 'word',
    'powerpoint', 'outlook', 'drive', 'folder', 'file',
    'backup', 'install', 'uninstall', 'update', 'driver',
];
```

### Konstanta Issue Terms
```php
private array $issueTerms = [
    'lemot', 'lambat', 'error', 'eror',
    'tidak bisa', 'gak bisa', 'ga bisa',
    'bermasalah', 'masalah', 'rusak', 'mati',
    'hilang', 'blank', 'kosong', 'no signal',
    'tidak muncul', 'gak muncul', 'crash', 'hang', 'freeze',
    'not responding', 'blue screen', 'overheat', 'panas',
    'bunyi', 'putus', 'disconnect', 'connect',
];
```

### Konstanta Clarification Map
```php
private array $clarificationMap = [
    'wifi' => ['WiFi lemot', 'Tidak bisa connect', 'No internet', 'Sering putus'],
    'internet' => ['Internet lemot', 'Tidak terhubung', 'DNS error', 'IP conflict'],
    'printer' => ['Printer tidak terdeteksi', 'Macet print', 'Kertas nyangkut', 'Tinta habis'],
    'komputer' => ['Komputer lemot', 'Blue screen', 'Tidak bisa nyala', 'Overheat'],
    'software' => ['Aplikasi error', 'Tidak bisa install', 'Crash', 'Update gagal'],
];
```

### Konstanta Conversation Memory
```php
// Batas riwayat percakapan di session
private const MAX_CONVERSATION_HISTORY = 5;          // 5 interaksi terakhir
```

---

# 23. DETAIL KONSTANTA DAN THRESHOLD - CHATBOT RETRIEVAL SERVICE

## File: app/Services/Chatbot/ChatbotRetrievalService.php

### Konstanta Dasar
```php
// Jumlah hasil maksimal
private const TOP_K_RESULTS = 5;                      // Top 5 hasil

// Threshold similarity minimum
private const SIMILARITY_THRESHOLD = 0.05;            // 5% - threshold minimum

// Jumlah kandidat dari Typesense
private const TYPESENSE_CANDIDATE_LIMIT = 30;         // 30 kandidat dari Typesense
```

### Konstanta Bobot Kombinasi (Total 100%)
```php
// Bobot Typesense (sinyal utama)
private const TYPESENSE_WEIGHT = 0.85;                // 85% - bobot Typesense

// Bobot TF-IDF (penyesuaian minor)
private const TFIDF_WEIGHT = 0.15;                   // 15% - bobot TF-IDF
```

### Konstanta Boost Ringan
```php
// Bonus untuk judul match
private const TITLE_MATCH_BOOST = 0.5;               // 0.5 - bonus judul match

// Bonus untuk exact match
private const EXACT_MATCH_BOOST = 0.3;               // 0.3 - bonus exact match
```

### Konstanta Cache
```php
// Cache key untuk vektor
private const VECTOR_CACHE_KEY = 'chatbot:retrieval:vectors:normalized';

// TTL cache vektor (24 jam)
private const VECTOR_CACHE_TTL = 86400;              // 86400 detik = 24 jam

// Cache key untuk IDF
private const IDF_CACHE_KEY = 'chatbot:retrieval:idf';

// Cache key untuk topik
private const TOPIC_CACHE_KEY = 'chatbot:topics';

// TTL cache topik (1 jam)
private const TOPIC_CACHE_TTL = 3600;                // 3600 detik = 1 jam
```

### Konstanta Confidence Level
```php
// Threshold untuk confidence high
private const HIGH_CONFIDENCE_THRESHOLD = 0.15;       // 15% - confidence high

// Threshold untuk confidence medium
private const MEDIUM_CONFIDENCE_THRESHOLD = 0.05;    // 5% - confidence medium (sama dengan SIMILARITY_THRESHOLD)
```

---

# 24. DETAIL KONSTANTA DAN THRESHOLD - PREPROCESSING SERVICE

## File: app/Services/Chatbot/PreprocessingService.php

### Konstanta Typo Dictionary
```php
private array $typoDictionary = [
    // WiFi typos
    'wfi' => 'wifi', 'wiifi' => 'wifi', 'wfii' => 'wifi', 'wifii' => 'wifi',
    
    // Internet typos
    'intenet' => 'internet', 'internrt' => 'internet', 'intrnet' => 'internet',
    
    // Computer typos
    'kompter' => 'komputer', 'komputr' => 'komputer', 'kompoter' => 'komputer',
    
    // Printer typos
    'pritner' => 'printer', 'prnter' => 'printer', 'printter' => 'printer',
    
    // Email typos
    'emai' => 'email', 'emaill' => 'email', 'emil' => 'email', 'emial' => 'email',
];
```

### Konstanta Context Tokens (untuk boosting)
```php
private array $contextTokens = [
    'wifi' => ['network', 'jaringan', 'internet', 'router', 'modem'],
    'printer' => ['hardware', 'cetak', 'printing', 'kertas', 'tinta'],
    'email' => ['surel', 'mail', 'outlook', 'gmail', 'komunikasi'],
];
```

### Konstanta IT Generic Terms (low weight)
```php
private array $itGenericTerms = [
    'masalah', 'problem', 'issue', 'trouble', 'gangguan',
    'bantu', 'help', 'tolong', 'mohon', 'silakan',
    'cara', 'bagaimana', 'apakah', 'apa', 'kenapa',
];
```

### Konstanta Important Domain Tokens (strong boost)
```php
private array $importantDomainTokens = [
    'ransomware', 'malware', 'virus', 'trojan', 'phishing',
    'wifi', 'internet', 'printer', 'komputer', 'email',
    'bsod', 'blue screen', 'driver', 'server',
];
```

### Konstanta Protected Technical Tokens (tidak di-stem)
```php
private array $protectedTechnicalTokens = [
    'ransomware', 'malware', 'trojan', 'phishing', 'spyware',
    'wifi', 'internet', 'router', 'modem', 'switch',
    'printer', 'scanner', 'driver', 'software', 'hardware',
    'windows', 'linux', 'android', 'ios', 'docker',
    'api', 'sql', 'database', 'server', 'client',
];
```

### Konstanta Stopwords (Indonesia)
```php
private array $stopwords = [
    'yang', 'dan', 'atau', 'di', 'ke', 'dari', 'pada', 'untuk',
    'dengan', 'tanpa', 'adalah', 'ialah', 'itu', 'ini', 'tersebut',
    'sebuah', 'seorang', 'para', 'mereka', 'kita', 'kami', 'saya',
    'anda', 'kamu', 'dia', 'mereka', 'nya', 'nya', 'nya',
    'juga', 'lagi', 'sudah', 'belum', 'telah', 'akan', 'harus',
    'bisa', 'dapat', 'tidak', 'jangan', 'hanya', 'saja', 'lebih',
    'paling', 'sangat', 'amat', 'cukup', 'kurang', 'banyak', 'sedikit',
];
```

### Konstanta Prefixes (Indonesian stemming)
```php
private array $prefixes = [
    'me', 'mem', 'men', 'meng', 'meny',
    'ber', 'bel', 'ter', 'per', 'di', 'ke',
];
```

### Konstanta Suffixes (Indonesian stemming)
```php
private array $suffixes = [
    'kan', 'i', 'an', 'nya',
];
```

---

# 25. DETAIL METHOD - COSINE SIMILARITY SERVICE

## File: app/Services/Chatbot/CosineSimilarityService.php

### Method calculate
```php
public function calculate(array $vectorA, array $vectorB): float
```
**Fungsi:** Menghitung cosine similarity antara dua vektor sparse.

**Alur Proses:**
1. Cek apakah salah satu vektor kosong, return 0.0 jika ya
2. Gabungkan semua term unik dari kedua vektor
3. Hitung dot product: Σ(A[i] × B[i])
4. Hitung magnitude A: √(Σ(A[i]²))
5. Hitung magnitude B: √(Σ(B[i]²))
6. Cek pembagian dengan nol
7. Return cosine similarity: dot / (magnitudeA × magnitudeB)

**Formula:**
```
cosine_similarity = (A · B) / (||A|| × ||B||)
```

### Method calculateBatch
```php
public function calculateBatch(array $queryVector, array $documentVectors): array
```
**Fungsi:** Menghitung cosine similarity antara query vector dan multiple document vectors.

**Alur Proses:**
1. Iterasi setiap document vector
2. Hitung similarity menggunakan method calculate
3. Return array [docId => similarity]

### Method rankDocuments
```php
public function rankDocuments(array $queryVector, array $documentVectors): array
```
**Fungsi:** Mengurutkan dokumen berdasarkan similarity score.

**Alur Proses:**
1. Hitung similarity untuk semua dokumen
2. Sort descending berdasarkan similarity
3. Return array terurut [docId => similarity]

### Method meetsThreshold
```php
public function meetsThreshold(float $score, float $threshold): bool
```
**Fungsi:** Cek apakah score memenuhi threshold.

**Return:** true jika score >= threshold, false jika tidak

### Method normalizeScore
```php
public function normalizeScore(float $score, float $maxScore): float
```
**Fungsi:** Menormalisasi score ke rentang 0-1.

**Formula:**
```
normalized_score = score / max_score
```

### Method calculateWithBoost
```php
public function calculateWithBoost(array $vectorA, array $vectorB, array $boostTerms = []): float
```
**Fungsi:** Menghitung cosine similarity dengan boosting untuk specific terms.

**Alur Proses:**
1. Boost weight untuk terms di boostTerms (misalnya ×2)
2. Hitung cosine similarity dengan boosted vectors
3. Return similarity score

---

# 26. RINGKASAN SEMUA KONSTANTA DAN THRESHOLD

## Ringkasan Threshold Similarity
| Service | Konstanta | Nilai | Persentase |
|---------|-----------|-------|------------|
| AdvancedRetrievalService | SIMILARITY_THRESHOLD | 0.12 | 12% |
| AdvancedRetrievalService | HIGH_SIMILARITY_THRESHOLD | 0.35 | 35% |
| AdvancedRetrievalService | VERY_HIGH_SIMILARITY_THRESHOLD | 0.55 | 55% |
| AdvancedRetrievalService | SAFE_FALLBACK_THRESHOLD | 0.18 | 18% |
| ChatbotRetrievalService | SIMILARITY_THRESHOLD | 0.05 | 5% |
| ChatbotRetrievalService | HIGH_CONFIDENCE_THRESHOLD | 0.15 | 15% |
| VocabularyService | MIN_SIMILARITY | 0.70 | 70% |
| VocabularyService | MIN_SIMILARITY_LONG_WORDS | 0.65 | 65% |
| DomainDetectionService | MIN_VOCABULARY_OVERLAP | 0.20 | 20% |
| DomainDetectionService | DOMAIN_CONFIDENCE_THRESHOLD | 0.05 | 5% |

## Ringkasan Bobot Faktor Ranking (AdvancedRetrievalService)
| Faktor | Konstanta | Nilai | Persentase |
|--------|-----------|-------|------------|
| Cosine Similarity | WEIGHT_COSINE | 0.30 | 30% |
| Title Overlap | WEIGHT_TITLE_OVERLAP | 0.25 | 25% |
| Domain Match | WEIGHT_DOMAIN_MATCH | 0.15 | 15% |
| Query Coverage | WEIGHT_QUERY_COVERAGE | 0.15 | 15% |
| Exact Phrase | WEIGHT_EXACT_PHRASE | 0.10 | 10% |
| Diversifikasi | WEIGHT_DIVERSIFICATION | 0.05 | 5% |
| **TOTAL** | | **1.00** | **100%** |

## Ringkasan Bobot Hybrid (ChatbotRetrievalService)
| Faktor | Konstanta | Nilai | Persentase |
|--------|-----------|-------|------------|
| Typesense | TYPESENSE_WEIGHT | 0.85 | 85% |
| TF-IDF | TFIDF_WEIGHT | 0.15 | 15% |
| **TOTAL** | | **1.00** | **100%** |

## Ringkasan Bonus dan Penalty
| Service | Konstanta | Nilai | Deskripsi |
|--------|-----------|-------|-----------|
| AdvancedRetrievalService | TITLE_BOOST_FACTOR | 2.0 | 2x boost untuk judul |
| AdvancedRetrievalService | EXACT_PHRASE_BONUS | 0.3 | Bonus exact phrase |
| AdvancedRetrievalService | FULL_COVERAGE_BONUS | 0.25 | Bonus full coverage |
| AdvancedRetrievalService | BIGRAM_MATCH_BONUS | 0.2 | Bonus bigram match |
| AdvancedRetrievalService | DOMAIN_PENALTY | -0.5 | Penalty domain mismatch |
| AdvancedRetrievalService | STRONG_DOMAIN_PENALTY | -0.8 | Penalty kuat |
| ImportantPhraseService | PHRASE_MATCH_BONUS | 0.4 | Bonus phrase konten |
| ImportantPhraseService | TITLE_PHRASE_BONUS | 0.6 | Bonus phrase judul |
| ImportantPhraseService | EXACT_QUERY_PHRASE_BONUS | 0.8 | Bonus exact query |
| ImportantPhraseService | PHRASE_CATEGORY_BOOST | 0.15 | Bonus kategori |
| ChatbotRetrievalService | TITLE_MATCH_BOOST | 0.5 | Bonus judul match |
| ChatbotRetrievalService | EXACT_MATCH_BOOST | 0.3 | Bonus exact match |

## Ringkasan Cache TTL
| Service | Konstanta | Nilai | Satuan |
|--------|-----------|-------|--------|
| TfidfService | IDF_CACHE_TTL | 86400 | 24 jam |
| DomainDetectionService | DOMAIN_CACHE_TTL | 3600 | 1 jam |
| VocabularyService | CACHE_TTL | 3600 | 1 jam |
| ChatbotRetrievalService | VECTOR_CACHE_TTL | 86400 | 24 jam |
| ChatbotRetrievalService | TOPIC_CACHE_TTL | 3600 | 1 jam |

## Ringkasan Limits
| Service | Konstanta | Nilai | Deskripsi |
|--------|-----------|-------|-----------|
| AdvancedRetrievalService | TOP_K_RESULTS | 5 | Top 5 hasil |
| AdvancedRetrievalService | FAILURE_THRESHOLD | 3 | 3 kegagalan |
| AdvancedRetrievalService | MAX_FAILURE_MEMORY | 10 | 10 kegagalan disimpan |
| AdvancedRetrievalService | MAX_RESULTS_PER_CATEGORY | 2 | 2 per kategori |
| ChatbotRetrievalService | TOP_K_RESULTS | 5 | Top 5 hasil |
| ChatbotRetrievalService | TYPESENSE_CANDIDATE_LIMIT | 30 | 30 kandidat |
| ConversationFlowService | MAX_CONVERSATION_HISTORY | 5 | 5 interaksi |
| VocabularyService | MIN_WORD_LENGTH | 3 | 3 karakter minimum |
| ImportantPhraseService | MIN_PHRASE_LENGTH | 2 | 2 kata minimum |

## Ringkasan Adaptive Thresholds
| Service | Kondisi | Threshold | Deskripsi |
|--------|---------|-----------|-----------|
| VocabularyService | Kata pendek (≤5) | Max distance = 1 | Lebih ketat |
| VocabularyService | Kata sedang (6-8) | Max distance = 2 | Standar |
| VocabularyService | Kata panjang (>8) | Max distance = 3 | Lebih toleran |
| VocabularyService | Kata panjang (>8) | Min similarity = 65% | Lebih rendah |
| VocabularyService | Kata lain | Min similarity = 70% | Standar |
