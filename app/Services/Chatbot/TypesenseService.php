<?php

namespace App\Services\Chatbot;

use App\Models\Article;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;

/**
 * =========================================================================
 * TYPESENSE SERVICE - INTEGRASI PENCARIAN FULL-TEKS
 * =========================================================================
 * 
 * Service ini mengelola integrasi dengan Typesense untuk pencarian full-teks
 * yang cepat dengan dukungan fuzzy kecocokan dan typo tolerance.
 * 
 * Tanggung Jawab:
 * - Menginisialisasi dan mengelola koneksi ke server Typesense.
 * - Membuat dan mengelola koleksi artikel untuk indexing.
 * - Melakukan indexing artikel (single dan bulk).
 * - Pencarian artikel dengan typo tolerance dan fuzzy kecocokan.
 * - Mengelola synonym sets untuk meningkatkan pemahaman query.
 * 
 * Digunakan Oleh:
 * - ChatbotRetrievalService
 * - AdvancedRetrievalService
 * 
 * Service Terkait:
 * - TfidfService
 * - DomainDetectionService
 */
class TypesenseService
{
    private ?Client $client = null;
    private bool $isConnected = false;
    private array $debugInfo = [];

    /**
     * =========================================================================
     * 1. KUMPULAN SINONIM BERBASIS INTENT
     * =========================================================================
     * 
     * Kumpulan sinonim tingkat intent untuk meningkatkan pemahaman query.
     * Setiap kumpulan mengelompokkan istilah terkait yang mengekspresikan
     * intent yang sama.
     */
    private array $intentSynonymSets = [
        // Intent konektivitas: istilah terkait koneksi jaringan
        'connectivity' => [
            'connect', 'konek', 'terhubung', 'tersambung', 'online',
            'connection', 'koneksi', 'sambung', 'nyambung'
        ],

        // Intent keamanan: istilah terkait ancaman keamanan
        'security' => [
            'virus', 'malware', 'trojan', 'ransomware',
            'spyware', 'adware', 'worm', 'phishing'
        ],

        // Intent pencetakan: istilah terkait printing
        'printing' => [
            'print', 'printer', 'cetak', 'ngeprint',
            'printing', 'mencetak', 'percetakan'
        ],

        // Intent autentikasi: istilah terkait login/akses akun
        'authentication' => [
            'login', 'signin', 'sign-in', 'masuk akun',
            'log in', 'log-in', 'masuk', 'sign up', 'signup',
            'register', 'daftar'
        ],

        // Intent jaringan/internet: istilah terkait masalah jaringan
        'network' => [
            'wifi', 'internet', 'jaringan', 'network',
            'lan', 'wireless', 'nirkabel', 'router',
            'modem', 'access point', 'hotspot'
        ],

        // Intent kegagalan: istilah yang mengekspresikan kegagalan
        'failure' => [
            'gagal', 'error', 'gagal konek', 'tidak bisa',
            'ga bisa', 'gak bisa', 'tidak bisa', 'tidak connect',
            'tidak terhubung', 'masalah', 'issue', 'kendala'
        ],

        // Intent kecepatan/performa: istilah terkait masalah kecepatan
        'speed' => [
            'lambat', 'slow', 'lemot', 'speed', 'kecepatan',
            'bandwidth', 'lag', 'lagging', 'buffering'
        ],

        // Intent email: istilah terkait email
        'email' => [
            'email', 'surel', 'mail', 'surat elektronik',
            'gmail', 'outlook', 'yahoo mail'
        ],
    ];

    /**
     * =========================================================================
     * 2. METODE KONSTRUKTOR - INISIALISASI KLIEN TYPESENSE
     * =========================================================================
     * 
     * Fungsi: Menginisialisasi klien Typesense saat service dibuat.
     * 
     * Alur Proses:
     * 1. Memanggil method initializeClient.
     * 2. Membangun koneksi ke server Typesense.
     * 
     * Output:
     * - Instance TypesenseService dengan koneksi aktif.
     */
    public function __construct()
    {
        $this->initializeClient();
    }

    /**
     * =========================================================================
     * 3. METODE INITIALIZE CLIENT (PRIVATE)
     * =========================================================================
     * 
     * Fungsi: Menginisialisasi koneksi ke server Typesense.
     * 
     * Alur Proses:
     * 1. Membaca konfigurasi dari config/typesense.php.
     * 2. Membuat instance klien Typesense dengan konfigurasi.
     * 3. Menguji koneksi dengan mengambil daftar koleksi.
     * 4. Menandai status koneksi sebagai terhubung atau tidak.
     * 
     * Query yang Digunakan:
     * - $this->client->collections->retrieve(): Test koneksi
     * 
     * Output:
     * - Property $this->isConnected di-set ke true atau false.
     */
    private function initializeClient(): void
    {
        try {

            $this->client = new Client([
                'nodes' => [
                    [
                        'host' => config('typesense.host'),
                        'port' => config('typesense.port'),
                        'protocol' => config('typesense.protocol'),
                    ],
                ],
                'api_key' => config('typesense.api_key'),
                'connection_timeout_seconds' => 15,
            ]);

            // test koneksi
            $this->client->collections->retrieve();

            $this->isConnected = true;

        } catch (Exception $e) {

            $this->isConnected = false;

            logger()->error('Typesense connection failed', [
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * =========================================================================
     * 4. METODE IS CONNECTED
     * =========================================================================
     * 
     * Fungsi: Memeriksa apakah koneksi ke Typesense berhasil.
     * 
     * Output:
     * - Boolean true jika terhubung, false jika tidak.
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * =========================================================================
     * 5. METODE GET CLIENT
     * =========================================================================
     * 
     * Fungsi: Mengembalikan instance klien Typesense untuk operasi langsung.
     * 
     * Output:
     * - Instance klien Typesense atau null jika tidak terhubung.
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * =========================================================================
     * 6. METODE CREATE OR UPDATE COLLECTION
     * =========================================================================
     * 
     * Fungsi: Membuat atau memperbarui koleksi artikel di Typesense.
     * 
     * Alur Proses:
     * 1. Memeriksa status koneksi Typesense.
     * 2. Membaca konfigurasi koleksi dari config.
     * 3. Jika koleksi sudah ada, hapus koleksi lama.
     * 4. Membuat koleksi baru dengan skema terbaru.
     * 5. Mengembalikan hasil operasi.
     * 
     * Query yang Digunakan:
     * - $this->client->collections[$collectionName]->retrieve(): Cek koleksi
     * - $this->client->collections[$collectionName]->delete(): Hapus koleksi
     * - $this->client->collections->create(): Buat koleksi
     * 
     * Output:
     * - Array dengan status sukses, pesan, dan data koleksi.
     */
    public function createOrUpdateCollection(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        try {
            $collectionConfig = config('typesense.collections.articles');
            $collectionName = $collectionConfig['name'];

            // 1. Periksa apakah koleksi ada
            try {
                $existingCollection = $this->client->collections[$collectionName]->retrieve();
                
                // 2. Hapus dan buat ulang agar skema tetap terbaru
                $this->client->collections[$collectionName]->delete();
                Log::info("Deleted existing Typesense collection: {$collectionName}");
            } catch (ObjectNotFound $e) {
                Log::info("Creating new Typesense collection: {$collectionName}");
            }

            // 3. Buat koleksi baru
            $collection = $this->client->collections->create([
                'name' => $collectionName,
                'fields' => $collectionConfig['fields'],
                'default_sorting_field' => $collectionConfig['default_sorting_field'],
                'token_separators' => $collectionConfig['token_separators'] ?? [],
                'symbols_to_index' => $collectionConfig['symbols_to_index'] ?? [],
            ]);

            Log::info("Typesense collection created: {$collectionName}", [
                'fields' => count($collectionConfig['fields']),
            ]);

            return [
                'success' => true,
                'message' => "Collection '{$collectionName}' created successfully",
                'collection' => $collection,
            ];
        } catch (Exception $e) {
            Log::error('Failed to create Typesense collection: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * =========================================================================
     * 7. METODE INDEX ARTICLE
     * =========================================================================
     * 
     * Fungsi: Mengindex satu artikel ke Typesense.
     * 
     * Alur Proses:
     * 1. Memeriksa status koneksi Typesense.
     * 2. Menyiapkan data artikel untuk indexing.
     * 3. Melakukan upsert dokumen ke koleksi.
     * 4. Mengembalikan hasil operasi.
     * 
     * Query yang Digunakan:
     * - $this->client->collections[$collectionName]->documents->upsert(): Upsert dokumen
     * 
     * Output:
     * - Array dengan status sukses, pesan, dan data dokumen.
     */
    public function indexArticle(Article $article): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        try {
            $collectionName = config('typesense.collections.articles.name');
            
            $document = [
                'id' => (string) $article->id,
                'title' => $article->title,
                'content' => $article->content,
                'excerpt' => $article->excerpt ?? '',
                'keywords' => $article->keywords ?? '',
                'category_name' => $article->category?->name ?? '',
                'category_id' => (string) $article->category_id,
                'slug' => $article->slug,
                'is_published' => $article->is_published,
                'views' => $article->views ?? 0,
                'created_at' => $article->created_at?->timestamp ?? time(),
            ];

            $result = $this->client->collections[$collectionName]->documents->upsert($document);

            Log::debug("Indexed article to Typesense: {$article->id}", [
                'title' => $article->title,
                'is_published' => $article->is_published,
            ]);

            return [
                'success' => true,
                'message' => "Article '{$article->id}' indexed successfully",
                'document' => $result,
            ];
        } catch (Exception $e) {
            Log::error("Failed to index article {$article->id} to Typesense: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * =========================================================================
     * 8. METODE REMOVE ARTICLE
     * =========================================================================
     * 
     * Fungsi: Menghapus artikel dari index Typesense berdasarkan ID.
     * 
     * Alur Proses:
     * 1. Memeriksa status koneksi Typesense.
     * 2. Menghapus dokumen dari koleksi berdasarkan ID.
     * 3. Menangani kasus dokumen tidak ditemukan.
     * 4. Mengembalikan hasil operasi.
     * 
     * Query yang Digunakan:
     * - $this->client->collections[$collectionName]->documents[$articleId]->delete(): Hapus dokumen
     * 
     * Output:
     * - Array dengan status sukses dan pesan.
     */
    public function removeArticle(string $articleId): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        try {
            $collectionName = config('typesense.collections.articles.name');
            
            $this->client->collections[$collectionName]->documents[$articleId]->delete();

            Log::debug("Removed article from Typesense: {$articleId}");

            return [
                'success' => true,
                'message' => "Article '{$articleId}' removed successfully",
            ];
        } catch (ObjectNotFound $e) {
            // Dokumen tidak ada, ini tidak masalah
            return [
                'success' => true,
                'message' => "Article '{$articleId}' was not in index",
            ];
        } catch (Exception $e) {
            Log::error("Failed to remove article {$articleId} from Typesense: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * =========================================================================
     * 9. KATA KUNCI KEAMANAN UNTUK BOOSTING KATEGORI
     * =========================================================================
     * 
     * Daftar kata kunci yang terkait dengan keamanan untuk boosting
     * kategori keamanan saat query mengandung istilah keamanan.
     */
    private array $securityKeywords = [
        'virus', 'viruss', 'viruses', 'malware', 'ransomware', 'ransomwre',
        'trojan', 'trojans', 'phishing', 'spyware', 'adware', 'worm',
        'security', 'keamanan', 'antivirus', 'anti-virus',
    ];

    /**
     * =========================================================================
     * 10. METODE IS SECURITY QUERY (PRIVATE)
     * =========================================================================
     * 
     * Fungsi: Memeriksa apakah query terkait dengan keamanan.
     * 
     * Alur Proses:
     * 1. Mengubah query ke huruf kecil.
     * 2. Mencocokkan query dengan daftar kata kunci keamanan.
     * 3. Mengembalikan true jika ada kecocokan.
     * 
     * Output:
     * - Boolean true jika query terkait keamanan, false jika tidak.
     */
    private function isSecurityQuery(string $query): bool
    {
        $lowerQuery = strtolower($query);
        foreach ($this->securityKeywords as $keyword) {
            if (str_contains($lowerQuery, $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * =========================================================================
     * 11. METODE NORMALIZE REPEATED CHARS (PRIVATE)
     * =========================================================================
     * 
     * Fungsi: Menormalisasi karakter berulang dalam token query.
     * 
     * Alur Proses:
     * 1. Memecah query menjadi token.
     * 2. Mengompres karakter yang diulang 3+ kali menjadi 2.
     * 3. Menggabungkan kembali token menjadi query.
     * 
     * Output:
     * - String query yang sudah dinormalisasi.
     */
    private function normalizeRepeatedChars(string $query): string
    {
        $tokens = explode(' ', $query);
        $normalizedTokens = [];
        
        foreach ($tokens as $token) {
            // Mengompres karakter yang diulang 3+ kali menjadi 2
            $pattern = '/(.)\1{2,}/';
            $normalized = preg_replace($pattern, '$1$1', $token);
            $normalizedTokens[] = $normalized ?? $token;
        }
        
        return implode(' ', $normalizedTokens);
    }

    /**
     * =========================================================================
     * 12. METODE SEARCH - PENCARIAN UTAMA
     * =========================================================================
     * 
     * Fungsi: Melakukan pencarian artikel dengan typo tolerance dan fuzzy kecocokan.
     * 
     * Alur Proses:
     * 1. Menormalisasi karakter berulang dalam query.
     * 2. Memeriksa status koneksi Typesense.
     * 3. Membangun parameter pencarian dengan ranking dan typo tolerance.
     * 4. Mendeteksi query keamanan untuk boosting kategori.
     * 5. Menjalankan pencarian ke Typesense.
     * 6. Memproses dan mengembalikan hasil pencarian.
     * 
     * Query yang Digunakan:
     * - $this->client->collections[$collectionName]->documents->search(): Pencarian dokumen
     * 
     * Output:
     * - Array dengan status sukses, hasil pencarian, total, dan debug info.
     */
    public function search(string $query, int $limit = 20, array $options = []): array
    {
        $originalQuery = $query;
        
        // Menormalisasi karakter berulang sebelum pencarian
        $normalizedQuery = $this->normalizeRepeatedChars($query);
        $query = $normalizedQuery;
        
        $version = Cache::rememberForever('articles_cache_version', fn() => time());
        $cacheKey = "typesense_search:v{$version}:" . md5(json_encode([$query, $limit, $options]));

        return Cache::remember($cacheKey, 86400, function () use ($originalQuery, $query, $limit, $options) {
            $this->debugInfo = [
                'original_query' => $originalQuery,
                'normalized_query' => $query,
                'limit' => $limit,
                'options' => $options,
                'candidates' => [],
                'typo_corrections' => [],
                'raw_hits_before_tfidf' => [],
                'security_boost_applied' => false,
            ];

            if (!$this->isConnected) {
                return [
                    'success' => false,
                    'message' => 'Typesense is not connected',
                    'results' => [],
                    'debug' => $this->debugInfo,
                ];
            }

            if (empty(trim($query))) {
                return [
                    'success' => true,
                    'message' => 'Empty query',
                    'results' => [],
                    'debug' => $this->debugInfo,
                ];
            }

            try {
                $collectionName = config('typesense.collections.articles.name');
                $searchConfig = config('typesense.search');

                // Memeriksa apakah query terkait keamanan untuk boosting
                $isSecurityQuery = $this->isSecurityQuery($query);
                $this->debugInfo['is_security_query'] = $isSecurityQuery;

                // Membangun parameter pencarian dengan ranking dan toleransi typo
                $searchParams = [
                    'q' => $query,
                    'query_by' => 'title,keywords,category_name,content',
                    'query_by_weights' => '8,6,4,2',
                    
                    // Parameter pencarian untuk ranking yang lebih baik
                    'prioritize_exact_match' => true,
                    'text_match_type' => 'max_score',
                    'token_separators' => [' ', '-'],
                    'drop_tokens_threshold' => 0,
                    
                    // Toleransi typo dengan pencarian infix
                    'num_typos' => 4,
                    'min_len_1typo' => 2,
                    'min_len_2typo' => 4,
                    
                    // Pencarian prefix dan infix untuk kecocokan typo
                    'prefix' => 'always',
                    'infix' => 'always',
                    'infix_score' => 'max_score',
                    
                    'typo_tokens_threshold' => $searchConfig['typo_tokens_threshold'] ?? 3,
                    
                    'per_page' => $limit,
                    'page' => 1,
                    'exhaustive_search' => true,
                    'filter_by' => 'is_published:true',
                ];

                // Menambahkan filter kategori jika specified
                if (isset($options['category_id'])) {
                    $searchParams['filter_by'] .= ' && category_id:=' . $options['category_id'];
                }

                // Menambahkan filter domain jika specified
                if (isset($options['domain'])) {
                    $searchParams['filter_by'] .= ' && category_name:=' . $options['domain'];
                }

                // Boost artikel keamanan untuk query terkait keamanan
                if ($isSecurityQuery) {
                    $searchParams['optional_filter_by'] = 'category_name:=Keamanan Sistem';
                    $this->debugInfo['security_boost_applied'] = true;
                    $this->debugInfo['boost_category'] = 'Keamanan Sistem';
                    
                    Log::info('Security query detected - applying category boost', [
                        'query' => $query,
                        'boost_category' => 'Keamanan Sistem',
                    ]);
                }

                // Menjalankan pencarian
                $searchResults = $this->client->collections[$collectionName]->documents->search($searchParams);

                // Log hasil Typesense untuk debugging
                $rawHitsLog = [];
                if (isset($searchResults['hits']) && !empty($searchResults['hits'])) {
                    foreach ($searchResults['hits'] as $idx => $hit) {
                        $document = $hit['document'];
                        $rawHitsLog[] = [
                            'rank' => $idx + 1,
                            'title' => $document['title'],
                            'typesense_score' => $hit['text_match'] ?? 0,
                            'category_name' => $document['category_name'] ?? '',
                        ];
                        
                        // Menyimpan di debug info untuk retrieval
                        $this->debugInfo['raw_hits_before_tfidf'][] = [
                            'rank' => $idx + 1,
                            'id' => $document['id'],
                            'title' => $document['title'],
                            'typesense_score' => $hit['text_match'] ?? 0,
                            'category_name' => $document['category_name'] ?? '',
                        ];
                    }
                }

                Log::info('RAW Typesense results (before TF-IDF reranking)', [
                    'query' => $query,
                    'hits' => $rawHitsLog,
                    'security_boost_applied' => $this->debugInfo['security_boost_applied'],
                ]);

                // Memproses hasil pencarian
                $results = [];
                if (isset($searchResults['hits']) && !empty($searchResults['hits'])) {
                    foreach ($searchResults['hits'] as $hit) {
                        $document = $hit['document'];
                        
                        $results[] = [
                            'id' => $document['id'],
                            'title' => $document['title'],
                            'content' => $document['content'],
                            'excerpt' => $document['excerpt'] ?? '',
                            'keywords' => $document['keywords'] ?? '',
                            'category_name' => $document['category_name'] ?? '',
                            'category_id' => $document['category_id'] ?? '',
                            'slug' => $document['slug'],
                            'is_published' => $document['is_published'],
                            'views' => $document['views'] ?? 0,
                            'typesense_score' => $hit['text_match'] ?? 0,
                        ];

                        $this->debugInfo['candidates'][] = [
                            'id' => $document['id'],
                            'title' => $document['title'],
                            'score' => $hit['text_match'] ?? 0,
                            'category_name' => $document['category_name'] ?? '',
                        ];
                    }
                }

                // Melacak koreksi typo dari hasil pencarian
                if (isset($searchResults['request_params']['q'])) {
                    $correctedQuery = $searchResults['request_params']['q'];
                    if ($correctedQuery !== $query) {
                        $this->debugInfo['typo_corrections'] = [
                            'original' => $query,
                            'corrected' => $correctedQuery,
                        ];
                    }
                }

                $this->debugInfo['total_found'] = $searchResults['found'] ?? 0;
                $this->debugInfo['search_time_ms'] = $searchResults['search_time_ms'] ?? 0;

                Log::info('Typesense search completed', [
                    'query' => $query,
                    'results_count' => count($results),
                    'total_found' => $searchResults['found'] ?? 0,
                    'search_time_ms' => $searchResults['search_time_ms'] ?? 0,
                    'security_boost_applied' => $this->debugInfo['security_boost_applied'],
                ]);

                return [
                    'success' => true,
                    'message' => 'Search completed',
                    'results' => $results,
                    'total' => $searchResults['found'] ?? 0,
                    'debug' => $this->debugInfo,
                ];
            } catch (Exception $e) {
                Log::error("Typesense search failed for query '{$query}': " . $e->getMessage());
                
                return [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'results' => [],
                    'debug' => $this->debugInfo,
                ];
            }
        });
    }

    /**
     * =========================================================================
     * 13. METODE INDEX ALL ARTICLES - BULK INDEXING
     * =========================================================================
     * 
     * Fungsi: Mengindex semua artikel yang sudah dipublikasi secara massal.
     * 
     * Alur Proses:
     * 1. Mengambil semua artikel yang sudah dipublikasi.
     * 2. Menyiapkan data artikel untuk bulk indexing.
     * 3. Melakukan import dokumen dalam batch.
     * 4. Menghitung jumlah sukses dan error.
     * 5. Mengembalikan hasil operasi.
     * 
     * Query yang Digunakan:
     * - Article::where('is_published', true)->with('category')->get(): Ambil artikel
     * - $this->client->collections[$collectionName]->documents->import(): Import batch
     * 
     * Output:
     * - Array dengan status sukses, jumlah indexed, errors, dan detail error.
     */
    public function indexAllArticles(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        $errorDetails = [];
        try {
            $articles = Article::where('is_published', true)
                ->with('category')
                ->get();

            $collectionName = config('typesense.collections.articles.name');
            $documents = [];
            $successCount = 0;
            $errorCount = 0;

            foreach ($articles as $article) {
                // Validasi minimal: id dan judul wajib ada
                if (empty($article->id) || empty($article->title)) {
                    Log::warning('Article missing required fields', [
                        'id' => $article->id,
                        'title' => $article->title,
                    ]);
                    $errorCount++;
                    $errorDetails[] = [
                        'article_id' => $article->id,
                        'title' => $article->title,
                        'error' => 'Missing required fields',
                    ];
                    continue;
                }
                // Menyiapkan data dokumen untuk indexing
                $documents[] = [
                    'id' => (string) $article->id,
                    'title' => $article->title,
                    'content' => $article->content,
                    'excerpt' => $article->excerpt ?? '',
                    'keywords' => $article->keywords ?? '',
                    'category_name' => $article->category?->name ?? '',
                    'category_id' => (string) $article->category_id,
                    'slug' => $article->slug,
                    'is_published' => $article->is_published,
                    'views' => $article->views ?? 0,
                    'created_at' => $article->created_at?->timestamp ?? time(),
                ];
            }

            if (count($documents) > 0) {
                // Upsert dokumen dalam batch
                $result = $this->client->collections[$collectionName]->documents->import($documents, ['action' => 'upsert']);

                // Menghitung jumlah sukses dan error
                foreach ($result as $idx => $item) {
                    if (isset($item['success']) && $item['success']) {
                        $successCount++;
                    } else {
                        $errorCount++;
                        $errorDetails[] = [
                            'article_id' => $documents[$idx]['id'] ?? null,
                            'title' => $documents[$idx]['title'] ?? null,
                            'error' => $item['error'] ?? 'Unknown error',
                        ];
                        Log::error('Bulk indexing error', [
                            'article_id' => $documents[$idx]['id'] ?? null,
                            'title' => $documents[$idx]['title'] ?? null,
                            'error' => $item['error'] ?? 'Unknown error',
                        ]);
                    }
                }
            }

            Log::info("Bulk indexed {$successCount} articles to Typesense", [
                'success' => $successCount,
                'errors' => $errorCount,
            ]);

            return [
                'success' => true,
                'message' => "Indexed {$successCount} articles, {$errorCount} errors",
                'indexed' => $successCount,
                'errors' => $errorCount,
                'error_details' => $errorDetails,
            ];
        } catch (Exception $e) {
            Log::error('Bulk indexing fatal error', [
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'indexed' => 0,
                'errors' => 1,
                'error_details' => [[
                    'error' => $e->getMessage(),
                ]],
            ];
        }
    }

    /**
     * =========================================================================
     * 14. METODE DELETE COLLECTION
     * =========================================================================
     * 
     * Fungsi: Menghapus koleksi artikel dari Typesense.
     * 
     * Alur Proses:
     * 1. Memeriksa status koneksi Typesense.
     * 2. Menghapus koleksi dari Typesense.
     * 3. Menangani kasus koleksi tidak ditemukan.
     * 4. Mengembalikan hasil operasi.
     * 
     * Query yang Digunakan:
     * - $this->client->collections[$collectionName]->delete(): Hapus koleksi
     * 
     * Output:
     * - Array dengan status sukses dan pesan.
     */
    public function deleteCollection(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        try {
            $collectionName = config('typesense.collections.articles.name');
            
            $this->client->collections[$collectionName]->delete();

            Log::info("Deleted Typesense collection: {$collectionName}");

            return [
                'success' => true,
                'message' => "Collection '{$collectionName}' deleted successfully",
            ];
        } catch (ObjectNotFound $e) {
            return [
                'success' => true,
                'message' => "Collection does not exist",
            ];
        } catch (Exception $e) {
            Log::error("Failed to delete Typesense collection: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * =========================================================================
     * 15. METODE GET COLLECTION STATS
     * =========================================================================
     * 
     * Fungsi: Mengambil statistik koleksi Typesense.
     * 
     * Alur Proses:
     * 1. Memeriksa status koneksi Typesense.
     * 2. Mengambil data koleksi dari Typesense.
     * 3. Mengembalikan statistik jumlah dokumen dan field.
     * 
     * Query yang Digunakan:
     * - $this->client->collections[$collectionName]->retrieve(): Ambil statistik
     * 
     * Output:
     * - Array dengan status sukses, pesan, dan data statistik.
     */
    public function getCollectionStats(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        try {
            $collectionName = config('typesense.collections.articles.name');
            
            $stats = $this->client->collections[$collectionName]->retrieve();

            return [
                'success' => true,
                'message' => 'Collection stats retrieved',
                'stats' => [
                    'name' => $stats['name'] ?? $collectionName,
                    'num_documents' => $stats['num_documents'] ?? 0,
                    'fields' => count($stats['fields'] ?? []),
                ],
            ];
        } catch (ObjectNotFound $e) {
            return [
                'success' => false,
                'message' => 'Collection does not exist',
            ];
        } catch (Exception $e) {
            Log::error("Failed to get Typesense collection stats: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * =========================================================================
     * 16. METODE GET DEBUG INFO
     * =========================================================================
     * 
     * Fungsi: Mengembalikan informasi debug dari operasi terakhir.
     * 
     * Output:
     * - Array berisi informasi debug pencarian.
     */
    public function getDebugInfo(): array
    {
        return $this->debugInfo;
    }

    /**
     * =========================================================================
     * 17. METODE GET SYNONYM COLLECTION NAME (PRIVATE)
     * =========================================================================
     * 
     * Fungsi: Mengembalikan nama koleksi untuk operasi synonym.
     * 
     * Output:
     * - String nama koleksi sinonim.
     */
    private function getSynonymCollectionName(): string
    {
        return config('typesense.collections.articles.name');
    }

    /**
     * =========================================================================
     * 18. METODE GET INTENT SYNONYM SETS
     * =========================================================================
     * 
     * Fungsi: Mengembalikan semua kumpulan sinonim berbasis intent.
     * 
     * Output:
     * - Array berisi semua kumpulan sinonim intent.
     */
    public function getIntentSynonymSets(): array
    {
        return $this->intentSynonymSets;
    }

    /**
     * =========================================================================
     * 19. METODE CREATE SYNONYM
     * =========================================================================
     * 
     * Fungsi: Membuat atau memperbarui satu kumpulan sinonim di Typesense.
     * 
     * Alur Proses:
     * 1. Memeriksa status koneksi Typesense.
     * 2. Validasi jumlah sinonim minimal 2.
     * 3. Melakukan upsert synonym ke koleksi.
     * 4. Mengembalikan hasil operasi.
     * 
     * Query yang Digunakan:
     * - $this->client->collections[$collectionName]->synonyms->upsert(): Upsert synonym
     * 
     * Output:
     * - Array dengan status sukses, pesan, dan data synonym.
     */
    public function createSynonym(string $synonymId, array $synonyms): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        if (empty($synonyms) || count($synonyms) < 2) {
            return ['success' => false, 'message' => 'Synonym set must have at least 2 terms'];
        }

        try {
            $collectionName = $this->getSynonymCollectionName();
            
            $synonymConfig = [
                'id' => $synonymId,
                'synonyms' => $synonyms,
            ];

            $result = $this->client->collections[$collectionName]->synonyms->upsert($synonymId, $synonymConfig);

            Log::info("Created/updated Typesense synonym: {$synonymId}", [
                'terms' => count($synonyms),
            ]);

            return [
                'success' => true,
                'message' => "Synonym '{$synonymId}' created/updated successfully",
                'data' => $result,
            ];
        } catch (Exception $e) {
            Log::error("Failed to create Typesense synonym {$synonymId}: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * =========================================================================
     * 20. METODE CREATE ALL SYNONYMS
     * =========================================================================
     * 
     * Fungsi: Membuat semua kumpulan sinonim berbasis intent di Typesense.
     * 
     * Alur Proses:
     * 1. Memeriksa status koneksi Typesense.
     * 2. Iterasi semua kumpulan sinonim intent.
     * 3. Memanggil createSynonym untuk setiap kumpulan.
     * 4. Menghitung jumlah sukses dan error.
     * 5. Mengembalikan hasil operasi.
     * 
     * Output:
     * - Array dengan status sukses, jumlah created, errors, dan detail error.
     */
    public function createAllSynonyms(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        $results = [
            'success' => true,
            'created' => 0,
            'errors' => 0,
            'details' => [],
        ];

        foreach ($this->intentSynonymSets as $intent => $synonyms) {
            // Membuat synonym untuk setiap intent
            $result = $this->createSynonym($intent, $synonyms);
            
            // Menghitung jumlah sukses dan error
            if ($result['success']) {
                $results['created']++;
            } else {
                $results['errors']++;
                $results['details'][] = [
                    'intent' => $intent,
                    'error' => $result['message'],
                ];
            }
        }

        Log::info("Created {$results['created']} synonym sets, {$results['errors']} errors");

        return $results;
    }

    /**
     * =========================================================================
     * 21. METODE GET SYNONYM
     * =========================================================================
     * 
     * Fungsi: Mengambil kumpulan sinonim tertentu dari Typesense.
     * 
     * Alur Proses:
     * 1. Memeriksa status koneksi Typesense.
     * 2. Mengambil data synonym berdasarkan ID.
     * 3. Menangani kasus synonym tidak ditemukan.
     * 4. Mengembalikan data synonym atau null.
     * 
     * Query yang Digunakan:
     * - $this->client->collections[$collectionName]->synonyms[$synonymId]->retrieve(): Ambil synonym
     * 
     * Output:
     * - Array data synonym atau null jika tidak ditemukan.
     */
    public function getSynonym(string $synonymId): ?array
    {
        if (!$this->isConnected) {
            return null;
        }

        try {
            $collectionName = $this->getSynonymCollectionName();
            $synonym = $this->client->collections[$collectionName]->synonyms[$synonymId]->retrieve();
            return $synonym;
        } catch (ObjectNotFound $e) {
            return null;
        } catch (Exception $e) {
            Log::error("Failed to get Typesense synonym {$synonymId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * =========================================================================
     * 22. METODE GET ALL SYNONYMS
     * =========================================================================
     * 
     * Fungsi: Mengambil semua kumpulan sinonim dari Typesense.
     * 
     * Alur Proses:
     * 1. Memeriksa status koneksi Typesense.
     * 2. Mengambil semua synonym dari koleksi.
     * 3. Mengembalikan array synonym.
     * 
     * Query yang Digunakan:
     * - $this->client->collections[$collectionName]->synonyms->retrieve(): Ambil semua synonym
     * 
     * Output:
     * - Array berisi semua kumpulan sinonim.
     */
    public function getAllSynonyms(): array
    {
        if (!$this->isConnected) {
            return [];
        }

        try {
            $collectionName = $this->getSynonymCollectionName();
            $synonyms = $this->client->collections[$collectionName]->synonyms->retrieve();
            return $synonyms['synonyms'] ?? [];
        } catch (Exception $e) {
            Log::error("Failed to get all Typesense synonyms: " . $e->getMessage());
            return [];
        }
    }

    /**
     * =========================================================================
     * 23. METODE DELETE SYNONYM
     * =========================================================================
     * 
     * Fungsi: Menghapus kumpulan sinonim tertentu dari Typesense.
     * 
     * Alur Proses:
     * 1. Memeriksa status koneksi Typesense.
     * 2. Menghapus synonym berdasarkan ID.
     * 3. Menangani kasus synonym tidak ditemukan.
     * 4. Mengembalikan hasil operasi.
     * 
     * Query yang Digunakan:
     * - $this->client->collections[$collectionName]->synonyms[$synonymId]->delete(): Hapus synonym
     * 
     * Output:
     * - Array dengan status sukses dan pesan.
     */
    public function deleteSynonym(string $synonymId): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        try {
            $collectionName = $this->getSynonymCollectionName();
            $this->client->collections[$collectionName]->synonyms[$synonymId]->delete();

            Log::info("Deleted Typesense synonym: {$synonymId}");

            return [
                'success' => true,
                'message' => "Synonym '{$synonymId}' deleted successfully",
            ];
        } catch (ObjectNotFound $e) {
            return [
                'success' => true,
                'message' => "Synonym '{$synonymId}' does not exist",
            ];
        } catch (Exception $e) {
            Log::error("Failed to delete Typesense synonym {$synonymId}: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * =========================================================================
     * 24. METODE DELETE ALL SYNONYMS
     * =========================================================================
     * 
     * Fungsi: Menghapus semua kumpulan sinonim dari Typesense.
     * 
     * Alur Proses:
     * 1. Memeriksa status koneksi Typesense.
     * 2. Iterasi semua kumpulan sinonim intent.
     * 3. Memanggil deleteSynonym untuk setiap kumpulan.
     * 4. Menghitung jumlah deleted dan error.
     * 5. Mengembalikan hasil operasi.
     * 
     * Output:
     * - Array dengan status sukses, jumlah deleted, dan errors.
     */
    public function deleteAllSynonyms(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        $deleted = 0;
        $errors = 0;

        foreach (array_keys($this->intentSynonymSets) as $intent) {
            // Menghapus synonym untuk setiap intent
            $result = $this->deleteSynonym($intent);
            // Menghitung jumlah deleted dan error
            if ($result['success']) {
                $deleted++;
            } else {
                $errors++;
            }
        }

        Log::info("Deleted {$deleted} synonym sets, {$errors} errors");

        return [
            'success' => true,
            'deleted' => $deleted,
            'errors' => $errors,
        ];
    }

    /**
     * =========================================================================
     * 25. METODE MATCH SYNONYM INTENTS
     * =========================================================================
     * 
     * Fungsi: Memeriksa apakah query cocok dengan intent sinonim.
     * 
     * Alur Proses:
     * 1. Mengubah query ke huruf kecil.
     * 2. Iterasi semua kumpulan sinonim intent.
     * 3. Mencocokkan query dengan setiap sinonim.
     * 4. Mengumpulkan intent yang cocok beserta istilah yang match.
     * 5. Mengembalikan array intent yang cocok.
     * 
     * Output:
     * - Array intent yang cocok dengan istilah sinonim mereka.
     */
    public function matchSynonymIntents(string $query): array
    {
        $lowerQuery = strtolower(trim($query));
        $matchedIntents = [];

        foreach ($this->intentSynonymSets as $intent => $synonyms) {
            // Memeriksa setiap sinonim dalam kumpulan intent
            foreach ($synonyms as $synonym) {
                if (str_contains($lowerQuery, strtolower($synonym))) {
                    // Jika cocok, tambahkan ke hasil
                    if (!isset($matchedIntents[$intent])) {
                        $matchedIntents[$intent] = [
                            'intent' => $intent,
                            'matched_terms' => [],
                            'all_synonyms' => $synonyms,
                        ];
                    }
                    if (!in_array($synonym, $matchedIntents[$intent]['matched_terms'])) {
                        $matchedIntents[$intent]['matched_terms'][] = $synonym;
                    }
                    break;
                }
            }
        }

        return $matchedIntents;
    }
}