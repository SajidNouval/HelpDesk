<?php

namespace App\Services\Chatbot;

use App\Models\Article;
use Exception;
use Illuminate\Support\Facades\Log;
use Typesense\Client;
use Typesense\Exceptions\ObjectNotFound;

/**
 * =========================================================================
 * SERVICE TYPESENSE
 * =========================================================================
 * 
 * Layanan ini mengelola integrasi dengan Typesense untuk pencarian full-teks
 * yang cepat dengan dukungan fuzzy kecocokan dan typo tolerance.
 * 
 * Fungsi utama:
 * - Menginisialisasi dan mengelola koneksi ke server Typesense.
 * - Membuat dan mengelola koleksi artikel untuk indexing.
 * - Melakukan indexing artikel (single dan bulk).
 * - Pencarian artikel dengan typo tolerance dan fuzzy kecocokan.
 * - Mengelola synonym sets untuk meningkatkan pemahaman query.
 * 
 * Digunakan oleh:
 * - ChatbotRetrievalService (sebagai mesin retrieval utama)
 * - AdvancedRetrievalService (sebagai sumber kandidat)
 */
class TypesenseService
{
    private ?Client $client = null;
    private bool $isConnected = false;
    private array $debugInfo = [];

    /**
     * =========================================================================
     * 1. Kumpulan Sinonim Berbasis Intent
     * =========================================================================
     * 
     * Kumpulan sinonim tingkat intent untuk meningkatkan pemahaman query.
     * Setiap kumpulan mengelompokkan istilah terkait yang mengekspresikan
     * intent yang sama. Kumpulan ini KECIL dan BERFOKUS PADA INTENT,
     * bukan kamus manual yang besar.
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
     * 2. Konstruktor - Inisialisasi Klien Typesense
     * =========================================================================
     * 
     * Menginisialisasi klien Typesense saat service dibuat.
     */
    public function __construct()
    {
        $this->initializeClient();
    }

    /**
     * =========================================================================
     * 3. Metode Initialize Klien (Private)
     * =========================================================================
     * 
     * Menginisialisasi koneksi ke server Typesense menggunakan konfigurasi
     * dari file config/typesense.php.
     * 
     * @kembalikan void
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
     * 4. Metode isConnected()
     * =========================================================================
     * 
     * Memeriksa apakah koneksi ke Typesense berhasil didirikan.
     * 
     * @kembalikan bool Benar jika terhubung, false jika tidak
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }

    /**
     * =========================================================================
     * 5. Metode getClient()
     * =========================================================================
     * 
     * Mengembalikan instance klien Typesense untuk operasi langsung.
     * 
     * @kembalikan Klien|null Instance klien atau null jika tidak terhubung
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * =========================================================================
     * 6. Metode createOrUpdateCollection()
     * =========================================================================
     * 
     * Membuat atau memperbarui koleksi artikel di Typesense.
     * Jika koleksi sudah ada, akan dihapus dan dibuat ulang untuk memastikan
     * skema selalu sesuai dengan konfigurasi terbaru.
     * 
     * @kembalikan array ['success' => bool, 'pesan' => string, 'koleksi' => mixed]
     */
    public function createOrUpdateCollection(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        try {
            $collectionConfig = config('typesense.collections.articles');
            $collectionName = $collectionConfig['name'];

            // Periksa apakah koleksi ada
            try {
                $existingCollection = $this->client->collections[$collectionName]->retrieve();
                
                // Hapus dan buat ulang agar skema tetap terbaru
                $this->client->collections[$collectionName]->delete();
                Log::info("Deleted existing Typesense collection: {$collectionName}");
            } catch (ObjectNotFound $e) {
                Log::info("Creating new Typesense collection: {$collectionName}");
            }

            // Buat the koleksi
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
     * 7. Metode indexArticle()
     * =========================================================================
     * 
     * Mengindex satu artikel ke Typesense. Menggunakan upsert untuk
     * mengupdate artikel yang sudah ada atau menambah yang baru.
     * 
     * @param Article $article Objek artikel yang akan diindex
     * @kembalikan array ['success' => bool, 'pesan' => string, 'dokumen' => mixed]
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
     * 8. Metode removeArticle()
     * =========================================================================
     * 
     * Menghapus artikel dari index Typesense berdasarkan ID.
     * 
     * @param string $articleId ID artikel yang akan dihapus
     * @kembalikan array ['success' => bool, 'pesan' => string]
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
     * 9. Kata Kunci Keamanan untuk Boosting Kategori
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
     * 10. Metode isSecurityQuery() (Private)
     * =========================================================================
     * 
     * Memeriksa apakah query terkait dengan keamanan untuk boosting.
     * 
     * @param string $query Query yang akan diperiksa
     * @kembalikan bool Benar jika query terkait keamanan
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
     * 11. Metode normalizeRepeatedChars() (Private)
     * =========================================================================
     * 
     * Menormalisasi karakter berulang dalam token query.
     * Menangani typo ekstrem seperti "vvvvvirus" -> "vvvirus".
     * 
     * @param string $query Query yang akan dinormalisasi
     * @kembalikan string Query yang sudah dinormalisasi
     */
    private function normalizeRepeatedChars(string $query): string
    {
        $tokens = explode(' ', $query);
        $normalizedTokens = [];
        
        foreach ($tokens as $token) {
            // Kompres karakter yang diulang 3+ kali menjadi 2
            // This handles cases like "vvvvvirus" -> "vvvirus"
            $pattern = '/(.)\1{2,}/';
            $normalized = preg_replace($pattern, '$1$1', $token);
            $normalizedTokens[] = $normalized ?? $token;
        }
        
        return implode(' ', $normalizedTokens);
    }

    /**
     * =========================================================================
     * 12. Metode pencarian() - Pencarian Utama
     * =========================================================================
     * 
     * Melakukan pencarian artikel dengan typo tolerance dan fuzzy kecocokan.
     * Ini adalah metode retrieval utama untuk chatbot.
     * 
     * @param string $query Query pencarian dari pengguna
     * @param int $batas Jumlah maksimal hasil yang dikembalikan
     * @param array $opsi Opsi pencarian tambahan (category_id, domain)
     * @kembalikan array ['success' => bool, 'pesan' => string, 'hasil' => array, 'total' => int, 'debug' => array]
     */
    public function search(string $query, int $limit = 20, array $options = []): array
    {
        $originalQuery = $query;
        
        // STEP 0: Normalize repeated characters sebelum searching
        // This handles extreme typo like "vvvvvirus" -> "vvvirus"
        $normalizedQuery = $this->normalizeRepeatedChars($query);
        $query = $normalizedQuery;
        
        $this->debugInfo = [
            'original_query' => $originalQuery,
            'normalized_query' => $normalizedQuery,
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

            // Periksa apakah query terkait keamanan untuk boosting
            $isSecurityQuery = $this->isSecurityQuery($query);
            $this->debugInfo['is_security_query'] = $isSecurityQuery;

            // Bangun parameter pencarian dengan ranking dan toleransi typo yang ditingkatkan
            $searchParams = [
                'q' => $query,
                'query_by' => 'title,keywords,category_name,content',  // Prioritize judul, kata kunci, kategori
                'query_by_weights' => '8,6,4,2',         // Judul=8, kata kunci=6, kategori=4, konten=2
                
                // STEP 1: Improved pencarian parameter untuk better ranking
                'prioritize_exact_match' => true,         // Prioritize exact cocok
                'text_match_type' => 'max_score',         // Gunakan max skor untuk multi-field kecocokan
                'token_separators' => [' ', '-'],         // Token separators untuk better word boundary detection
                'drop_tokens_threshold' => 0,             // Never drop token - simpan semua query terms
                
                // STEP 2: Enhanced typo tolerance dengan infix pencarian
                // Allow up ke 4 typo untuk very long words (handles extreme typo like "vvvvvirus")
                'num_typos' => 4,                         // Allow up ke 4 typo (increased dari 3)
                'min_len_1typo' => 2,                     // Minimum length untuk 1 typo (reduced dari 3)
                'min_len_2typo' => 4,                     // Minimum length untuk 2 typo (reduced dari 6)
                
                // Enable both prefix AND infix pencarian untuk kecocokan typo within words
                'prefix' => 'always',                     // Match at beginning of words
                'infix' => 'always',                      // Match anywhere within words (critical untuk typo like "doocker")
                'infix_score' => 'max_score',             // Gunakan max skor ketika infix cocok
                
                'typo_tokens_threshold' => $searchConfig['typo_tokens_threshold'] ?? 3,
                
                'per_page' => $limit,
                'page' => 1,
                'exhaustive_search' => true,              // Enable exhaustive pencarian untuk better typo kecocokan
                'filter_by' => 'is_published:true',       // Hanya pencarian dipublikasikan articles
            ];

            // Tambahkan kategori filter jika specified
            if (isset($options['category_id'])) {
                $searchParams['filter_by'] .= ' && category_id:=' . $options['category_id'];
            }

            // Tambahkan domain filter jika specified
            if (isset($options['domain'])) {
                $searchParams['filter_by'] .= ' && category_name:=' . $options['domain'];
            }

            // STEP 3: Boost keamanan articles untuk keamanan-related query
            if ($isSecurityQuery) {
                // Gunakan optional_filter_by ke boost keamanan kategori articles without excluding others
                $searchParams['optional_filter_by'] = 'category_name:=Keamanan Sistem';
                $this->debugInfo['security_boost_applied'] = true;
                $this->debugInfo['boost_category'] = 'Keamanan Sistem';
                
                Log::info('Security query detected - applying category boost', [
                    'query' => $query,
                    'boost_category' => 'Keamanan Sistem',
                ]);
            }

            // Jalankan pencarian
            $searchResults = $this->client->collections[$collectionName]->documents->search($searchParams);

            // STEP 5: Log RAW Typesense hits BEFORE TF-IDF (critical untuk debugging)
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
                    
                    // Store di debug info untuk retrieval
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

            // Proses hasil
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

            // Lacak koreksi typo dari hasil pencarian
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
    }

    /**
     * =========================================================================
     * 13. Metode indexAllArticles() - Bulk Indexing
     * =========================================================================
     * 
     * Mengindex semua artikel yang sudah dipublikasi secara massal.
     * Digunakan untuk rebuilding index atau initial indexing.
     * 
     * @kembalikan array ['success' => bool, 'pesan' => string, 'indexed' => int, 'errors' => int, 'error_details' => array]
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
                // Upsert dokumen di batch
                $result = $this->client->collections[$collectionName]->documents->import($documents, ['action' => 'upsert']);

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
     * 14. Metode deleteCollection()
     * =========================================================================
     * 
     * Menghapus koleksi artikel dari Typesense.
     * 
     * @kembalikan array ['success' => bool, 'pesan' => string]
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
     * 15. Metode getCollectionStats()
     * =========================================================================
     * 
     * Mengambil statistik koleksi Typesense (jumlah dokumen, field, dll).
     * 
     * @kembalikan array ['success' => bool, 'pesan' => string, 'stats' => array]
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
     * 16. Metode getDebugInfo()
     * =========================================================================
     * 
     * Mengembalikan informasi debug dari operasi terakhir.
     * 
     * @kembalikan array Informasi debug
     */
    public function getDebugInfo(): array
    {
        return $this->debugInfo;
    }

    /**
     * =========================================================================
     * 17. Metode getSynonymCollectionName() (Private)
     * =========================================================================
     * 
     * Mengembalikan nama koleksi untuk operasi synonym.
     * 
     * @kembalikan string Nama koleksi sinonim
     */
    private function getSynonymCollectionName(): string
    {
        return config('typesense.collections.articles.name');
    }

    /**
     * =========================================================================
     * 18. Metode getIntentSynonymSets()
     * =========================================================================
     * 
     * Mengembalikan semua kumpulan sinonim berbasis intent.
     * 
     * @kembalikan array Kumpulan sinonim
     */
    public function getIntentSynonymSets(): array
    {
        return $this->intentSynonymSets;
    }

    /**
     * =========================================================================
     * 19. Metode createSynonym()
     * =========================================================================
     * 
     * Membuat atau memperbarui satu kumpulan sinonim di Typesense.
     * 
     * @param string $synonymId Identifier unik untuk kumpulan sinonim
     * @param array $synonyms Array istilah sinonim
     * @kembalikan array ['success' => bool, 'pesan' => string, 'data' => mixed]
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
     * 20. Metode createAllSynonyms()
     * =========================================================================
     * 
     * Membuat semua kumpulan sinonim berbasis intent di Typesense.
     * 
     * @kembalikan array ['success' => bool, 'created' => int, 'errors' => int, 'details' => array]
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
            $result = $this->createSynonym($intent, $synonyms);
            
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
     * 21. Metode getSynonym()
     * =========================================================================
     * 
     * Mengambil kumpulan sinonim tertentu dari Typesense.
     * 
     * @param string $synonymId Identifier kumpulan sinonim
     * @kembalikan array|null Data sinonim atau null jika tidak ditemukan
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
     * 22. Metode getAllSynonyms()
     * =========================================================================
     * 
     * Mengambil semua kumpulan sinonim dari Typesense.
     * 
     * @kembalikan array Array semua kumpulan sinonim
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
     * 23. Metode deleteSynonym()
     * =========================================================================
     * 
     * Menghapus kumpulan sinonim tertentu dari Typesense.
     * 
     * @param string $synonymId Identifier kumpulan sinonim
     * @kembalikan array ['success' => bool, 'pesan' => string]
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
     * 24. Metode deleteAllSynonyms()
     * =========================================================================
     * 
     * Menghapus semua kumpulan sinonim dari Typesense.
     * 
     * @kembalikan array ['success' => bool, 'deleted' => int, 'errors' => int]
     */
    public function deleteAllSynonyms(): array
    {
        if (!$this->isConnected) {
            return ['success' => false, 'message' => 'Typesense is not connected'];
        }

        $deleted = 0;
        $errors = 0;

        foreach (array_keys($this->intentSynonymSets) as $intent) {
            $result = $this->deleteSynonym($intent);
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
     * 25. Metode matchSynonymIntents()
     * =========================================================================
     * 
     * Memeriksa apakah query cocok dengan intent sinonim apa pun.
     * 
     * @param string $query Query pencarian
     * @kembalikan array Array intent yang cocok dengan istilah sinonim mereka
     */
    public function matchSynonymIntents(string $query): array
    {
        $lowerQuery = strtolower(trim($query));
        $matchedIntents = [];

        foreach ($this->intentSynonymSets as $intent => $synonyms) {
            foreach ($synonyms as $synonym) {
                if (str_contains($lowerQuery, strtolower($synonym))) {
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