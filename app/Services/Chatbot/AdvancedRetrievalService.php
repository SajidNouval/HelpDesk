<?php

namespace App\Services\Chatbot;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * =========================================================================
 * SERVICE ADVANCED RETRIEVAL
 * =========================================================================
 * 
 * Layanan ini bertanggung jawab untuk proses retrieval artikel berbasis TF-IDF
 * yang diperkaya dengan berbagai strategi reranking.
 * 
 * Fungsi utama:
 * - Menghitung skor relevansi multi-faktor.
 * - Menggabungkan sinyal Typesense dan TF-IDF.
 * - Menambahkan diversifikasi hasil dan penalti domain.
 * - Menentukan kapan perlu eskalasi atau fallback aman.
 * 
 * Input:
 * - Query pengguna.
 * - Hasil kandidat artikel dari Typesense.
 * 
 * Output:
 * - Hasil artikel yang diurutkan dengan skor dan confidence.
 */
class AdvancedRetrievalService
{
    // ============================================================
    // AMBANG BATAS CONFIDENCE
    // ============================================================
    // Threshold ini mengontrol kapan hasil dianggap dapat diandalkan
    private const SIMILARITY_THRESHOLD = 0.12;        // Skor minimum untuk menyertakan hasil
    private const HIGH_SIMILARITY_THRESHOLD = 0.35;   // Skor untuk confidence tinggi
    private const VERY_HIGH_SIMILARITY_THRESHOLD = 0.55; // Skor untuk confidence sangat tinggi
    private const SAFE_FALLBACK_THRESHOLD = 0.18;     // Di bawah ini, gunakan fallback aman alih-alih hasil lemah
    
    private const TOP_K_RESULTS = 5;
    private const FAILURE_THRESHOLD = 3;
    private const MAX_FAILURE_MEMORY = 10; // Maksimal query yang dilacak untuk kegagalan
    private const SESSION_FAILURE_KEY = 'chatbot_failure_memory';
    private const SESSION_CONVERSATION_KEY = 'chatbot_conversation_memory';
    private const CACHE_TTL = 86400;
    
    // ============================================================
    // BOBOT PERINGKAT HYBRID
    // ============================================================
    // Bobot ini mengontrol kontribusi setiap faktor ranking
    private const WEIGHT_COSINE = 0.30;        // Cosine similarity TF-IDF dasar
    private const WEIGHT_TITLE_OVERLAP = 0.25; // Overlap kata kunci judul (ditingkatkan)
    private const WEIGHT_DOMAIN_MATCH = 0.15;  // Keselarasan domain/kategori
    private const WEIGHT_QUERY_COVERAGE = 0.15; // Cakupan term query (ditingkatkan)
    private const WEIGHT_EXACT_PHRASE = 0.10;  // Pencocokan frasa exact
    private const WEIGHT_DIVERSIFICATION = 0.05; // Diversifikasi hasil
    
    // ============================================================
    // FAKTOR BONUS/PENALTI
    // ============================================================
    private const TITLE_BOOST_FACTOR = 2.0;
    private const EXACT_PHRASE_BONUS = 0.3;     // Bonus untuk frasa exact di judul
    private const FULL_COVERAGE_BONUS = 0.25;   // Bonus untuk pencocokan semua term penting
    private const BIGRAM_MATCH_BONUS = 0.2;     // Bonus untuk bigram (2-gram) cocok
    private const DOMAIN_PENALTY = -0.5;        // Penalti untuk domain yang salah
    private const STRONG_DOMAIN_PENALTY = -0.8; // Penalti kuat untuk domain terlarang
    private const LOW_PRIORITY_WEIGHT = 0.1;    // Multiplier bobot untuk term generik
    // ============================================================
    // TERM PRIORITAS RENDAH / GENERIK
    // ============================================================
    // Term ini harus memiliki pengaruh BERKURANG dalam ranking
    // Mereka terlalu umum di artikel helpdesk dan mengalahkan term spesifik domain
    
    // Kategori 1: Kata instruksional generik (cara, mengatasi, dll.)
    // Kategori 2: Kata teknis/perangkat generik (pc, laptop, komputer, dll.)
    // Ini terlalu umum dan tidak menunjukkan intent spesifik
    private array $lowPriorityTerms = [
        // Kata instruksional generik
        'cara',
        'mengatasi',
        'solusi',
        'tutorial',
        'panduan',
        'tips',
        'langkah',
        'metode',
        'guide',
        'help',
        'bantuan',
        'petunjuk',
        
        // Kata teknis generik - terlalu umum, tidak menunjukkan intent spesifik
        'aplikasi',
        'masalah',
        'sistem',
        'program',
        'software',
        'hardware',
        'teknologi',
        'digital',
        'online',
        'data',
        'file',
        'dokumen',
    ];
    
    // ============================================================
    // TOKEN DOMAIN PENTING (TOKEN INTENT SEBENARNYA)
    // ============================================================
    // Token-token ini mewakili intent PENGGUNA SEBENARNYA dan harus MENDOMINASI ranking
    // Ketika token ini muncul dalam query, artikel yang cocok dengan token ini harus mendapat peringkat tertinggi
    
    // CATATAN: Kata perangkat generik (pc, laptop, komputer, desktop, notebook, error)
    // sengaja DIKECUALIKAN karena terlalu umum dan menyebabkan
    // artikel hardware mengalahkan intent keamanan/software.
    private array $importantDomainTokens = [
        // Token keamanan (PRIORITAS TERTINGGI)
        'virus',
        'malware',
        'ransomware',
        'trojan',
        'spyware',
        'phishing',
        'antivirus',
        
        // Token DevOps/Infrastruktur
        'docker',
        'kubernetes',
        'k8s',
        'container',
        
        // Token jaringan
        'wifi',
        'jaringan',
        'network',
        'lan',
        'wan',
        'vpn',
        'router',
        'modem',
        
        // Token periferal hardware
        'printer',
        'scanner',
        
        // Token data
        'database',
        'mysql',
        'postgresql',
        'mongodb',
        'sql',
        
        // Token komunikasi
        'email',
        'gmail',
        'outlook',
        
        // Token web
        'website',
        'browser',
        'chrome',
        'firefox',
        
        // Token akun
        'akun',
        'login',
        'password',
        
        // Token masalah spesifik
        'lemot',
        'bsod',
        'hang',
        'crash',
        'freeze',
    ];
    
    // ============================================================
    // TOKEN PRIORITAS KEAMANAN
    // ============================================================
    // Ketika query mengandung SALAH SATU token ini, artikel keamanan
    // harus mendapat boost KUAT untuk mengalahkan artikel hardware generik
    private array $securityPriorityTokens = [
        'virus',
        'malware',
        'ransomware',
        'trojan',
        'spyware',
        'phishing',
        'antivirus',
        'security',
        'hack',
        'hacker',
        'firewall',
    ];
    
    // ============================================================
    // PEMETAAN PENALTI DOMAIN NEGATIF
    // ============================================================
    // Ketika query domain X, beri penalti artikel dari domain yang tidak terkait
    // Ini mencegah kontaminasi lintas domain (misal: query printer mengembalikan artikel BSOD)
    private array $negativeDomainPenalties = [
        'printer' => ['bsod', 'vpn', 'internet', 'wifi', 'security', 'email'],
        'komputer' => ['printer', 'vpn', 'email'],
        'wifi' => ['printer', 'email', 'bsod'],
        'internet' => ['printer', 'bsod', 'vpn'],
        'email' => ['printer', 'hardware', 'bsod'],
        'aplikasi' => ['printer', 'hardware', 'bsod'],
        'website' => ['printer', 'hardware', 'bsod'],
        'akun' => ['printer', 'hardware', 'bsod'],
    ];
    
    private array $domainCategoryMap = [
        'wifi' => ['wifi', 'internet', 'jaringan'],
        'internet' => ['internet', 'jaringan', 'wifi'],
        'jaringan' => ['jaringan', 'internet', 'wifi'],
        'printer' => ['hardware', 'printer'],
        'komputer' => ['hardware', 'komputer'],
        'email' => ['email', 'akun'],
        'website' => ['internet', 'website'],
        'aplikasi' => ['aplikasi', 'software'],
        'akun' => ['akun', 'email'],
    ];
    
    private array $forbiddenDomainMap = [
        'wifi' => ['printer', 'email', 'akun'],
        'internet' => ['printer', 'email'],
        'jaringan' => ['printer', 'email'],
        'printer' => ['wifi', 'email', 'vpn', 'security'],
        'komputer' => ['printer', 'email'],
        'email' => ['printer', 'hardware', 'wifi'],
        'website' => ['printer', 'hardware'],
        'aplikasi' => ['printer', 'hardware'],
        'akun' => ['printer', 'hardware'],
    ];
    
    private array $queryExpansionDict = [
        'wifi' => ['internet', 'jaringan', 'hotspot', 'koneksi', 'router', 'wireless', 'lan', 'wan'],
        'internet' => ['jaringan', 'koneksi', 'bandwidth', 'online', 'web', 'browser'],
        'jaringan' => ['network', 'lan', 'wan', 'ethernet', 'kabel', 'switch', 'hub'],
        'printer' => ['print', 'cetak', 'scanner', 'mencetak', 'printing', 'epson', 'canon'],
        'komputer' => ['pc', 'laptop', 'hardware', 'cpu', 'desktop', 'notebook'],
        'email' => ['gmail', 'outlook', 'pesan', 'inbox', 'mail', 'surel'],
        'website' => ['web', 'situs', 'portal', 'browser', 'chrome', 'firefox'],
        'aplikasi' => ['app', 'software', 'program', 'perangkat', 'lunak'],
        'akun' => ['login', 'masuk', 'daftar', 'register', 'password', 'username'],
    ];
    
    private array $synonymMap = [
        'lambat' => 'lemot',
        'pelan' => 'lemot',
        'lamban' => 'lemot',
        'slow' => 'lemot',
        'koneksi' => 'internet',
        'sambungan' => 'internet',
        'terhubung' => 'connect',
        'konek' => 'connect',
        'eror' => 'error',
        'erorr' => 'error',
        'galat' => 'error',
        'masalah' => 'error',
        'bermasalah' => 'error',
        'tidak connect' => 'tidak terhubung',
        'tidak konek' => 'tidak terhubung',
        'gak bisa' => 'tidak bisa',
        'ga bisa' => 'tidak bisa',
        'nggak bisa' => 'tidak bisa',
        'komputer' => 'komputer',
        'kompter' => 'komputer',
        'nyala' => 'hidup',
        'mati' => 'tidak hidup',
        'hang' => 'freeze',
        'macet' => 'freeze',
    ];
    
    private array $typoDictionary = [
        'wfi' => 'wifi',
        'wiifi' => 'wifi',
        'wfii' => 'wifi',
        'wifii' => 'wifi',
        'wi-fi' => 'wifi',
        'intenet' => 'internet',
        'internrt' => 'internet',
        'intrnet' => 'internet',
        'inet' => 'internet',
        'intrnt' => 'internet',
        'kompter' => 'komputer',
        'komputr' => 'komputer',
        'kompoter' => 'komputer',
        'komputerr' => 'komputer',
        'komputwr' => 'komputer',
        'jaringn' => 'jaringan',
        'jaringa' => 'jaringan',
        'jaringann' => 'jaringan',
        'jaring' => 'jaringan',
        'prnter' => 'printer',
        'printter' => 'printer',
        'printe' => 'printer',
        'priner' => 'printer',
        'pritner' => 'printer',
        'priter' => 'printer',
        'prinetr' => 'printer',
        'pirnter' => 'printer',
        'emai' => 'email',
        'emaill' => 'email',
        'emil' => 'email',
        'emial' => 'email',
        'eamil' => 'email',
        'emal' => 'email',
        'e-mail' => 'email',
        'logn' => 'login',
        'logi' => 'login',
        'lojin' => 'login',
        'lgin' => 'login',
        'pasword' => 'password',
        'passwod' => 'password',
        'passwrod' => 'password',
        'paswrod' => 'password',
        'passowrd' => 'password',
        'lemott' => 'lemot',
        'eror' => 'error',
        'errror' => 'error',
        'eroor' => 'error',
        'tidk' => 'tidak',
        'tida' => 'tidak',
        'tiadak' => 'tidak',
        'dak' => 'tidak',
        'bsa' => 'bisa',
        'biisa' => 'bisa',
        'sdah' => 'sudah',
        'suda' => 'sudah',
        'belumm' => 'belum',
        'blm' => 'belum',
        'blom' => 'belum',
        'aplikai' => 'aplikasi',
        'apliksi' => 'aplikasi',
        'aplaksi' => 'aplikasi',
        'webiste' => 'website',
        'websit' => 'website',
        'wesite' => 'website',
    ];
    
    private array $itStopwords = [
        'cara', 'mengatasi', 'masalah', 'sistem', 'aplikasi', 'program',
        'solusi', 'tips', 'panduan', 'tutorial', 'langkah', 'berikut',
        'artikel', 'dokumentasi', 'petunjuk', 'bantuan', 'help', 'guide',
        'untuk', 'dengan', 'pada', 'dari', 'dalam', 'yang',
        'atau', 'dan', 'adalah', 'merupakan', 'yaitu', 'yakni',
    ];
    
    public array $curatedCategories = [
        ['id' => 'wifi', 'label' => 'WiFi', 'icon' => 'wifi'],
        ['id' => 'internet', 'label' => 'Internet', 'icon' => 'globe'],
        ['id' => 'printer', 'label' => 'Printer', 'icon' => 'printer'],
        ['id' => 'komputer', 'label' => 'Komputer', 'icon' => 'desktop'],
        ['id' => 'email', 'label' => 'Email', 'icon' => 'mail'],
        ['id' => 'aplikasi', 'label' => 'Aplikasi', 'icon' => 'app'],
        ['id' => 'akun', 'label' => 'Akun', 'icon' => 'user'],
    ];
    
    public array $curatedSubtopics = [
        'wifi' => [
            'wifi lemot',
            'wifi tidak connect',
            'wifi sering putus',
            'wifi tidak terdeteksi',
        ],
        'internet' => [
            'internet lemot',
            'internet putus-putus',
            'koneksi internet lambat',
            'internet tidak stabil',
        ],
        'printer' => [
            'printer error',
            'printer tidak respon',
            'printer tidak mau print',
            'printer macet',
        ],
        'komputer' => [
            'komputer lemot',
            'komputer sering hang',
            'komputer blue screen',
            'komputer tidak mau nyala',
        ],
        'email' => [
            'email tidak masuk',
            'email tidak bisa dikirim',
            'lupa password email',
            'email error',
        ],
        'aplikasi' => [
            'aplikasi error',
            'aplikasi tidak bisa dibuka',
            'aplikasi lemot',
            'aplikasi crash',
        ],
        'akun' => [
            'lupa password',
            'akun terkunci',
            'tidak bisa login',
            'registrasi gagal',
        ],
    ];
    
    private PreprocessingService $preprocessor;
    private TfidfService $tfidfService;
    private CosineSimilarityService $similarityService;
    private DomainDetectionService $domainDetector;
    private VocabularyService $vocabularyService;
    private ImportantPhraseService $phraseService;
    
    private array $debugInfo = [];
    
    // Kuota diversifikasi kategori - memastikan keragaman hasil
    private const MAX_RESULTS_PER_CATEGORY = 2; // Maksimal 2 artikel dari kategori yang sama
    private const DIVERSIFICATION_CATEGORIES = ['troubleshooting', 'optimization', 'tutorial', 'hardware'];
    
    /**
     * =========================================================================
     * 1. METODE KONSTRUKTOR
     * =========================================================================
     *
     * Fungsi:
     * Inisialisasi dependensi service dan konfigurasi internal.
     *
     * Alur Proses:
     * 1. Menerima dependency service melalui konstruktor.
     * 1. Menyimpan dependensi ke properti internal.
     * 1. Menyiapkan mode debug jika diperlukan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - void
     */
    public function __construct(
        PreprocessingService $preprocessor,
        TfidfService $tfidfService,
        CosineSimilarityService $similarityService,
        DomainDetectionService $domainDetector,
        VocabularyService $vocabularyService,
        ImportantPhraseService $phraseService
    ) {
        $this->preprocessor = $preprocessor;
        $this->tfidfService = $tfidfService;
        $this->similarityService = $similarityService;
        $this->domainDetector = $domainDetector;
        $this->vocabularyService = $vocabularyService;
        $this->phraseService = $phraseService;
    }
    
    /**
     * =========================================================================
     * 1. METODE Retrieve
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi retrieve di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - TOP_K_RESULTS
     */
    public function retrieve(string $query, int $limit = self::TOP_K_RESULTS): array
    {
        $this->debugInfo = [
            'original_query' => $query,
            'stages' => [],
            'scores' => [],
        ];
        
        // ============================================================
        // DETEKSI OUT-OF-DOMAIN (SEBELUM retrieval apa pun)
        // ============================================================
        // Cek apakah query di luar domain IT/support
        // Jika ya, kembalikan lebih awal dengan pesan penolakan - JANGAN fallback ke artikel IT
        $outOfDomainCheck = $this->domainDetector->detectOutOfDomain($query);
        $this->debugInfo['out_of_domain_check'] = $outOfDomainCheck;
        
        if ($outOfDomainCheck['is_out_of_domain']) {
            // Jika alasannya 'no_it_keywords', tunda penolakan dan izinkan
            // retrieval berjalan sebagai fallback. Alasan out-of-domain eksplisit
            // (empty_query, explicit_out_of_domain_keywords, dll.) tetap
            // menyebabkan penolakan langsung.
            $reason = $outOfDomainCheck['reason'] ?? '';
            if ($reason !== 'no_it_keywords') {
                $this->debugInfo['stages'][] = [
                    'stage' => 'out_of_domain_detection',
                    'input' => $query,
                    'output' => 'REJECTED - ' . $reason,
                ];

                return $this->outOfDomainResult($query);
            }

            // Log bahwa kita menunda penolakan untuk no_it_keywords
            $this->debugInfo['stages'][] = [
                'stage' => 'out_of_domain_detection',
                'input' => $query,
                'output' => 'DEFERRED_REJECTION - ' . $reason,
            ];
        }
        
        $this->debugInfo['stages'][] = [
            'stage' => 'out_of_domain_detection',
            'input' => $query,
            'output' => 'PASSED - in domain',
        ];
        
        $normalizedQuery = $this->normalizeTypos($query);
        $this->debugInfo['typo_corrected_query'] = $normalizedQuery;
        $this->logStage('typo_normalization', $query, $normalizedQuery);
        
        $normalizedQuery = $this->normalizeSynonyms($normalizedQuery);
        $this->debugInfo['synonym_normalized_query'] = $normalizedQuery;
        $this->logStage('synonym_normalization', $normalizedQuery, $normalizedQuery);
        
        $intents = $this->detectMultiIntent($query);
        $this->debugInfo['intents'] = $intents;
        $this->logStage('multi_intent_detection', $query, json_encode($intents));
        
        if (count($intents) > 1) {
            return $this->multiIntentRetrieval($intents, $limit);
        }
        
        return $this->singleIntentRetrieval($normalizedQuery, $limit);
    }
    
    /**
     * =========================================================================
     * 1. METODE Single Intent Retrieval
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi single intent retrieval di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function singleIntentRetrieval(string $query, int $limit): array
    {
        $domainInfo = $this->domainDetector->detectDomain($query);
        $this->debugInfo['domain_info'] = $domainInfo;
        $this->logStage('domain_detection', $query, $domainInfo['domain'] ?? 'none');
        
        $allowedCategories = $this->getAllowedCategories($domainInfo);
        $this->debugInfo['allowed_categories'] = $allowedCategories;
        $this->logStage('domain_filtering', 'allowed', json_encode($allowedCategories));
        
        $articles = $this->getDomainFilteredArticles($allowedCategories);
        $this->debugInfo['candidate_count'] = $articles->count();
        $this->logStage('article_filtering', 'candidates', $articles->count());
        
        if ($articles->isEmpty()) {
            $articles = $this->getPublishedArticles();
            $this->debugInfo['fallback_applied'] = true;
            $this->logStage('fallback', 'all_articles', $articles->count());
        }
        
        $expandedQuery = $this->expandQuery($query, $domainInfo['domain'] ?? null);
        $this->debugInfo['expanded_query'] = $expandedQuery;
        $this->logStage('query_expansion', $query, $expandedQuery);
        
        $documents = $this->prepareDocuments($articles);
        $tfidfData = $this->buildTfidfVectors($documents);
        
        $queryVector = $this->tfidfService->calculateQueryTFIDF($expandedQuery, $tfidfData['idf']);
        
        if (empty($queryVector)) {
            return $this->emptyResult($query);
        }
        
        $rankedResults = $this->hybridRanking(
            $queryVector,
            $tfidfData['vectors'],
            $documents,
            $query,
            $domainInfo,
            $allowedCategories
        );
        
        $rankedResults = $this->diversifyResults($rankedResults, $documents);
        
        $finalResults = $this->applyThresholdAndLimit($rankedResults, $limit);
        
        $this->trackRetrievalResult($query, $finalResults);
        
        return [
            'results' => $finalResults,
            'query' => $query,
            'total' => count($finalResults),
            'threshold_met' => !empty($finalResults),
            'max_similarity' => !empty($finalResults) ? $finalResults[0]['final_score'] : 0,
            'domain_detected' => $domainInfo['detected'] ?? false,
            'detected_domain' => $domainInfo['domain'] ?? null,
            'debug' => config('app.debug', false) ? $this->debugInfo : null,
        ];
    }
    
    /**
     * =========================================================================
     * 1. METODE Multi Intent Retrieval
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi multi intent retrieval di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
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
    
    /**
     * =========================================================================
     * 1. METODE BALANCED MERGE
     * =========================================================================
     *
     * Fungsi:
     * Menggabungkan hasil dari multiple intent dengan representasi yang seimbang.
     *
     * Alur Proses:
     * 1. Menghitung kuota fair per intent.
     * 2. Melakukan round-robin untuk mengambil hasil dari setiap intent.
     * 3. Melewati duplikasi berdasarkan article ID.
     * 4. Mengembalikan hasil yang sudah digabungkan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
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
                    
                    // Mengambil satu hasil tambahan dari intent ini
                    while ($currentPosition[$intentIndex] < count($intentResults[$intentIndex])) {
                        $candidate = $intentResults[$intentIndex][$currentPosition[$intentIndex]];
                        $currentPosition[$intentIndex]++;
                        
                        if (isset($seenIds[$candidate['id']])) {
                            continue;
                        }
                        
                        if ($candidate['final_score'] < self::SIMILARITY_THRESHOLD * 0.5) {
                            continue;
                        }
                        
                        $seenIds[$candidate['id']] = true;
                        $resultsPerIntent[$intentIndex]++;
                        $totalResults++;
                        $moreRounds = true;
                        
                        unset($candidate['_intent_index'], $candidate['_intent_query']);
                        $candidate['matched_intent'] = $intentIndex;
                        
                        $finalResults[] = $candidate;
                        break;
                    }
                }
            }
        }
        
        // Tahap 3: Mengisi slot tersisa dengan kandidat terbaik
        if ($totalResults < $limit) {
            $remainingCandidates = [];
            
            for ($intentIndex = 0; $intentIndex < $numIntents; $intentIndex++) {
                while ($currentPosition[$intentIndex] < count($intentResults[$intentIndex])) {
                    $candidate = $intentResults[$intentIndex][$currentPosition[$intentIndex]];
                    $currentPosition[$intentIndex]++;
                    
                    if (!isset($seenIds[$candidate['id']])) {
                        $remainingCandidates[] = $candidate;
                    }
                }
            }
            
            // Mengurutkan kandidat tersisa berdasarkan skor
            usort($remainingCandidates, fn($a, $b) => 
                ($b['final_score'] ?? 0) <=> ($a['final_score'] ?? 0)
            );
            
            foreach ($remainingCandidates as $candidate) {
                if ($totalResults >= $limit) {
                    break;
                }
                
                if (!isset($seenIds[$candidate['id']])) {
                    $seenIds[$candidate['id']] = true;
                    unset($candidate['_intent_index'], $candidate['_intent_query']);
                    $candidate['matched_intent'] = 'overflow';
                    $finalResults[] = $candidate;
                    $totalResults++;
                }
            }
        }
        
        $this->debugInfo['merge_details'] = [
            'quota_per_intent' => $quotaPerIntent,
            'results_per_intent' => $resultsPerIntent,
        ];
        
        return $finalResults;
    }
    
    /**
     * =========================================================================
     * 1. METODE Get Allowed Categories
     * =========================================================================
     *
     * Fungsi:
     * Mengambil data get allowed categories untuk keperluan logika service.
     *
     * Alur Proses:
     * 1. Menentukan sumber data untuk get allowed categories.
     * 1. Mengambil atau memformat data.
     * 1. Mengembalikan hasil dalam struktur yang sesuai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function getAllowedCategories(array $domainInfo): array
    {
        if (!$domainInfo['detected'] || empty($domainInfo['domain'])) {
            return [];
        }
        
        $domain = $domainInfo['domain'];
        
        // Coba ambil secara dinamis dari DomainDetectionService terlebih dahulu
        $dynamicCategories = $this->domainDetector->getDomainCategories($domain);
        if (!empty($dynamicCategories)) {
            return $dynamicCategories;
        }
        
        // Fallback ke mapping hardcoded jika dinamis kosong/gagal
        return $this->domainCategoryMap[$domain] ?? [];
    }
    
    /**
     * =========================================================================
     * 1. METODE Get Domain Filtered Articles
     * =========================================================================
     *
     * Fungsi:
     * Mengambil data get domain filtered articles untuk keperluan logika service.
     *
     * Alur Proses:
     * 1. Menentukan sumber data untuk get domain filtered articles.
     * 1. Mengambil atau memformat data.
     * 1. Mengembalikan hasil dalam struktur yang sesuai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - Collection
     */
    private function getDomainFilteredArticles(array $allowedCategories): Collection
    {
        if (empty($allowedCategories)) {
            return collect();
        }
        
        $query = Article::where('is_published', true)
            ->where('publish_status', 'approved')
            ->with('category');
        
        $query->whereHas('category', function ($q) use ($allowedCategories) {
            $q->whereIn(DB::raw('LOWER(TRIM(name))'), array_map('strtolower', $allowedCategories));
        });
        
        return $query->select('id', 'title', 'content', 'excerpt', 'keywords', 'slug', 'category_id')
            ->get();
    }
    
    /**
     * =========================================================================
     * 1. METODE Get Published Articles
     * =========================================================================
     *
     * Fungsi:
     * Mengambil data get published articles untuk keperluan logika service.
     *
     * Alur Proses:
     * 1. Menentukan sumber data untuk get published articles.
     * 1. Mengambil atau memformat data.
     * 1. Mengembalikan hasil dalam struktur yang sesuai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - Collection
     */
    private function getPublishedArticles(): Collection
    {
        return Article::where('is_published', true)
            ->where('publish_status', 'approved')
            ->with('category')
            ->select('id', 'title', 'content', 'excerpt', 'keywords', 'slug', 'category_id')
            ->get();
    }
    
    /**
     * =========================================================================
     * 1. METODE Expand Query
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi expand query di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function expandQuery(string $query, ?string $domain): string
    {
        $expanded = $query;
        
        if ($domain && isset($this->queryExpansionDict[$domain])) {
            $expansionTerms = $this->queryExpansionDict[$domain];
            $expanded .= ' ' . implode(' ', $expansionTerms);
        }
        
        $tokens = explode(' ', strtolower($query));
        foreach ($tokens as $token) {
            if (isset($this->queryExpansionDict[$token])) {
                $expanded .= ' ' . implode(' ', $this->queryExpansionDict[$token]);
            }
        }
        
        return $expanded;
    }
    
    /**
     * =========================================================================
     * 1. METODE Normalize Synonyms
     * =========================================================================
     *
     * Fungsi:
     * Menormalisasi normalize synonyms agar konsisten di seluruh pipeline.
     *
     * Alur Proses:
     * 1. Membersihkan teks/kata dari variasi atau typo.
     * 1. Mengubah format ke bentuk standar.
     * 1. Mengembalikan string atau token yang dinormalisasi.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function normalizeSynonyms(string $query): string
    {
        $result = $query;
        
        uksort($this->synonymMap, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
        
        foreach ($this->synonymMap as $synonym => $normalized) {
            if (str_contains($result, $synonym)) {
                $result = str_replace($synonym, $normalized, $result);
            }
        }
        
        return $result;
    }
    
    /**
     * =========================================================================
     * 1. METODE Normalize Typos
     * =========================================================================
     *
     * Fungsi:
     * Menormalisasi normalize typos agar konsisten di seluruh pipeline.
     *
     * Alur Proses:
     * 1. Membersihkan teks/kata dari variasi atau typo.
     * 1. Mengubah format ke bentuk standar.
     * 1. Mengembalikan string atau token yang dinormalisasi.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function normalizeTypos(string $query): string
    {
        // Gunakan VocabularyService untuk dynamic typo correction
        $normalizationResult = $this->vocabularyService->normalizeQuery($query);
        
        // Store correction info untuk debugging
        if (!empty($normalizationResult['corrections'])) {
            $this->debugInfo['vocabulary_corrections'] = $normalizationResult['corrections'];
        }
        
        return $normalizationResult['normalized'];
    }
    
    /**
     * =========================================================================
     * 1. METODE Hybrid Ranking
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi hybrid ranking di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - mixed
     */
    private function hybridRanking(
        array $queryVector,
        array $documentVectors,
        array $documents,
        string $originalQuery,
        array $domainInfo,
        array $allowedCategories
    ): array {
        $rankedResults = [];
        
        // Periksa jika query has keamanan intent
        $hasSecurityIntent = $this->hasSecurityIntent($originalQuery);
        
        // ============================================================
        // IMPORTANT PHRASE DETECTION (NEW - Frasa-level intent boosting)
        // ============================================================
        // Detect penting frasa di the query (e.g., "tidak terhubung", "gagal login")
        // These frasa represent TRUE user intent dan harus DOMINATE ranking
        $detectedPhrases = $this->phraseService->detectPhrases($originalQuery);
        $hasImportantPhrase = !empty($detectedPhrases);
        
        if ($hasImportantPhrase) {
            $this->debugInfo['detected_phrases'] = array_map(fn($p) => $p['phrase'], $detectedPhrases);
        }
        
        foreach ($documentVectors as $docId => $docVector) {
            $document = $documents[$docId] ?? [];
            
            $cosineSimilarity = $this->similarityService->calculate($queryVector, $docVector);
            $titleOverlap = $this->calculateTitleOverlap($queryVector, $document);
            $domainMatch = $this->calculateDomainMatch($document, $domainInfo, $allowedCategories);
            $queryCoverage = $this->calculateQueryCoverage($queryVector, $docVector);
            $exactPhraseBonus = $this->calculateExactPhraseBonus($originalQuery, $document);
            $diversificationScore = 0.05;
            $domainPenalty = $this->calculateDomainPenalty($document, $domainInfo);
            
            // SECURITY PRIORITY BOOST
            // Ketika query mengandung keamanan token (virus, malware, ransomware, trojan),
            // strongly boost keamanan-related articles ke override generic hardware articles
            $securityBoost = 0.0;
            if ($hasSecurityIntent && $this->isSecurityDocument($document)) {
                $securityBoost = 0.35; // Strong boost untuk keamanan articles ketika keamanan intent detected
            }
            
            // ============================================================
            // IMPORTANT PHRASE BOOSTING (NEW)
            // ============================================================
            // Jika query mengandung penting frasa, boost dokumen that cocok those frasa
            // This is the KEY FIX untuk the problem where "wifi tidak terhubung" was returning
            // "Internet lambat" instead of "Wifi tidak terhubung"
            $phraseBoost = 0.0;
            $phraseBoostDetails = [];
            
            if ($hasImportantPhrase) {
                $phraseResult = $this->phraseService->getPhraseBoostScore($originalQuery, $document);
                $phraseBoost = $phraseResult['total_boost'];
                $phraseBoostDetails = [
                    'phrase_boost' => $phraseResult['phrase_boost'],
                    'ngram_boost' => $phraseResult['ngram_boost'],
                    'title_phrase_matches' => $phraseResult['title_phrase_matches'],
                    'bigram_matches' => $phraseResult['bigram_matches'],
                    'trigram_matches' => $phraseResult['trigram_matches'],
                ];
            }
            
            // Apply frasa boost as a DIRECT ADDITIVE BONUS (tidak weighted)
            // This ensures frasa cocok have STRONG influence on ranking
            $finalScore = (
                ($cosineSimilarity * self::WEIGHT_COSINE) +
                ($titleOverlap * self::WEIGHT_TITLE_OVERLAP) +
                ($domainMatch * self::WEIGHT_DOMAIN_MATCH) +
                ($queryCoverage * self::WEIGHT_QUERY_COVERAGE) +
                ($exactPhraseBonus * self::WEIGHT_EXACT_PHRASE) +
                ($diversificationScore * self::WEIGHT_DIVERSIFICATION)
            ) + $domainPenalty + $securityBoost + $phraseBoost;
            
            $finalScore = max(0, min(1.0, $finalScore));
            
            $rankedResults[$docId] = [
                'doc_id' => $docId,
                'cosine_similarity' => $cosineSimilarity,
                'title_overlap' => $titleOverlap,
                'domain_match' => $domainMatch,
                'query_coverage' => $queryCoverage,
                'exact_phrase_bonus' => $exactPhraseBonus,
                'domain_penalty' => $domainPenalty,
                'phrase_boost' => $phraseBoost,
                'final_score' => $finalScore,
                'document' => $document,
            ];
            
            $this->debugInfo['scores'][$docId] = [
                'cosine' => round($cosineSimilarity, 4),
                'title_overlap' => round($titleOverlap, 4),
                'domain_match' => round($domainMatch, 4),
                'query_coverage' => round($queryCoverage, 4),
                'exact_phrase' => round($exactPhraseBonus, 4),
                'domain_penalty' => round($domainPenalty, 4),
                'phrase_boost' => round($phraseBoost, 4),
                'phrase_details' => $phraseBoostDetails,
                'final' => round($finalScore, 4),
            ];
        }
        
        usort($rankedResults, fn($a, $b) => $b['final_score'] <=> $a['final_score']);
        
        return $rankedResults;
    }
    
    /**
     * =========================================================================
     * 1. METODE CALCULATE TITLE OVERLAP
     * =========================================================================
     *
     * Fungsi:
     * Menghitung skor overlap judul dengan bigram untuk deteksi frasa exact.
     *
     * Alur Proses:
     * 1. Memfilter term generik dengan prioritas rendah.
     * 2. Memeriksa kecocokan bigram untuk deteksi frasa exact.
     * 3. Memberikan bobot lebih tinggi untuk term domain spesifik.
     * 4. Mengembalikan skor overlap yang dihitung.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - float
     */
    private function calculateTitleOverlap(array $queryVector, array $document): float
    {
        if (empty($document['title_tokens']) || empty($queryVector)) {
            return 0.0;
        }
        
        $title = $document['title'] ?? '';
        $titleTokens = $document['title_tokens'];
        $queryTerms = array_keys($queryVector);
        
        // Filter out low-prioritas terms dari query untuk judul kecocokan
        $importantQueryTerms = array_filter($queryTerms, fn($t) => !$this->isLowPriorityTerm($t));
        
        if (empty($importantQueryTerms)) {
            $importantQueryTerms = $queryTerms; // Fallback jika semua terms are low prioritas
        }
        
        // Periksa untuk bigram (2-gram) cocok - this captures exact frasa like "komputer lemot"
        $bigramMatches = $this->calculateBigramOverlap($queryTerms, $title);
        
        // Hitung unigram overlap untuk penting terms hanya
        $matchedTerms = 0;
        $weightedMatches = 0.0;
        foreach ($importantQueryTerms as $term) {
            if (in_array($term, $titleTokens)) {
                $matchedTerms++;
                // Boost domain-specific terms
                if ($this->isDomainSpecificTerm($term)) {
                    $weightedMatches += 1.5;
                } else {
                    $weightedMatches += 1.0;
                }
            }
        }
        
        if (count($importantQueryTerms) === 0) {
            return 0.0;
        }
        
        // Combine unigram dan bigram skor
        $unigramScore = $weightedMatches / count($importantQueryTerms);
        
        // Bigram cocok are strong signals of exact frasa kecocokan
        $score = $unigramScore + ($bigramMatches * 0.3);
        
        return min(1.0, $score);
    }
    
    /**
     * =========================================================================
     * 1. METODE CALCULATE BIGRAM OVERLAP
     * =========================================================================
     *
     * Fungsi:
     * Menghitung overlap bigram antara query dan judul.
     *
     * Alur Proses:
     * 1. Memfilter term dengan prioritas rendah.
     * 2. Generate bigram dari term penting.
     * 3. Memeriksa kecocokan bigram di judul.
     * 4. Mengembalikan jumlah kecocokan bigram.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - int
     */
    private function calculateBigramOverlap(array $queryTerms, string $title): int
    {
        // Filter out low-prioritas terms untuk bigram generation
        $importantTerms = array_filter($queryTerms, fn($t) => !$this->isLowPriorityTerm($t));
        $importantTerms = array_values($importantTerms);
        
        if (count($importantTerms) < 2) {
            return 0;
        }
        
        $titleLower = strtolower($title);
        $bigramMatches = 0;
        
        // Generate bigrams dari penting query terms
        for ($i = 0; $i < count($importantTerms) - 1; $i++) {
            $bigram = $importantTerms[$i] . ' ' . $importantTerms[$i + 1];
            if (str_contains($titleLower, $bigram)) {
                $bigramMatches++;
            }
        }
        
        return $bigramMatches;
    }
    
    /**
     * =========================================================================
     * 1. METODE IS LOW PRIORITY TERM
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah term adalah term generik dengan prioritas rendah.
     *
     * Alur Proses:
     * 1. Menerima term yang akan diperiksa.
     * 2. Membandingkan dengan daftar term prioritas rendah.
     * 3. Mengembalikan status prioritas term.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool
     */
    private function isLowPriorityTerm(string $term): bool
    {
        return in_array(mb_strtolower($term), $this->lowPriorityTerms);
    }
    
    /**
     * =========================================================================
     * 1. METODE IS DOMAIN SPECIFIC TERM
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah term adalah spesifik domain.
     *
     * Alur Proses:
     * 1. Menerima term yang akan diperiksa.
     * 2. Membandingkan dengan daftar term domain spesifik.
     * 3. Mengembalikan status domain spesifik term.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool
     */
    private function isDomainSpecificTerm(string $term): bool
    {
        return in_array(mb_strtolower($term), $this->importantDomainTokens);
    }
    
    /**
     * =========================================================================
     * 1. METODE HAS SECURITY INTENT
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah query mengandung token prioritas keamanan.
     *
     * Alur Proses:
     * 1. Menerima query yang akan diperiksa.
     * 2. Memecah query menjadi token.
     * 3. Memeriksa kecocokan dengan token keamanan.
     * 4. Mengembalikan status intent keamanan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool
     */
    private function hasSecurityIntent(string $query): bool
    {
        $queryLower = strtolower($query);
        $queryTokens = preg_split('/[\s,;.!?()""\'\-]+/', $queryLower, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($queryTokens as $token) {
            if (in_array($token, $this->securityPriorityTokens)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * =========================================================================
     * 1. METODE IS SECURITY DOCUMENT
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah dokumen terkait keamanan.
     *
     * Alur Proses:
     * 1. Menerima data dokumen.
     * 2. Memeriksa kategori dokumen.
     * 3. Memeriksa token keamanan di judul dan konten.
     * 4. Mengembalikan status keamanan dokumen.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool
     */
    private function isSecurityDocument(array $document): bool
    {
        $title = strtolower($document['title'] ?? '');
        $content = strtolower($document['text'] ?? '');
        $category = strtolower($document['category_name'] ?? '');
        
        // Periksa jika kategori is keamanan-related
        if ($category === 'security') {
            return true;
        }
        
        // Periksa jika judul atau konten mengandung keamanan token
        foreach ($this->securityPriorityTokens as $token) {
            if (str_contains($title, $token) || str_contains($content, $token)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * =========================================================================
     * 1. METODE Calculate Domain Match
     * =========================================================================
     *
     * Fungsi:
     * Menghitung nilai calculate domain match berdasarkan input yang diberikan.
     *
     * Alur Proses:
     * 1. Memproses input untuk menghitung calculate domain match.
     * 1. Menerapkan rumus atau bobot relevansi.
     * 1. Mengembalikan nilai numerik atau vektor.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - float
     */
    private function calculateDomainMatch(array $document, array $domainInfo, array $allowedCategories): float
    {
        if (!$domainInfo['detected'] || empty($allowedCategories)) {
            return 0.5;
        }
        
        $docCategory = $document['category_name'] ?? '';
        $docCategoryLower = strtolower(trim($docCategory));
        
        foreach ($allowedCategories as $allowed) {
            if ($docCategoryLower === strtolower($allowed)) {
                return 1.0;
            }
        }
        
        return 0.0;
    }
    
    /**
     * =========================================================================
     * 1. METODE CALCULATE QUERY COVERAGE
     * =========================================================================
     *
     * Fungsi:
     * Menghitung skor coverage query berdasarkan term penting yang ada di dokumen.
     *
     * Alur Proses:
     * 1. Memfilter term generik dengan prioritas rendah.
     * 2. Memberikan bobot lebih tinggi untuk term domain spesifik.
     * 3. Memberikan bonus besar ketika semua term penting cocok.
     * 4. Mengembalikan skor coverage yang dihitung.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - float
     */
    private function calculateQueryCoverage(array $queryVector, array $docVector): float
    {
        if (empty($queryVector) || empty($docVector)) {
            return 0.0;
        }
        
        $queryTerms = array_keys($queryVector);
        
        // Filter out low-prioritas dan stopword terms
        $importantTerms = array_filter($queryTerms, fn($t) => 
            !in_array($t, $this->itStopwords) && !$this->isLowPriorityTerm($t)
        );
        $importantTerms = array_values($importantTerms);
        
        if (empty($importantTerms)) {
            // Fallback ke semua terms jika everything is filtered
            $importantTerms = array_filter($queryTerms, fn($t) => !in_array($t, $this->itStopwords));
            $importantTerms = array_values($importantTerms);
        }
        
        if (empty($importantTerms)) {
            return 0.0;
        }
        
        $matchedTerms = 0;
        $weightedMatches = 0.0;
        
        foreach ($importantTerms as $term) {
            if (isset($docVector[$term]) && $docVector[$term] > 0) {
                $matchedTerms++;
                // Domain-specific terms get higher weight
                if ($this->isDomainSpecificTerm($term)) {
                    $weightedMatches += 1.5;
                } else {
                    $weightedMatches += 1.0;
                }
            }
        }
        
        $baseScore = $weightedMatches / count($importantTerms);
        
        // Major boost ketika ALL penting terms cocok (full coverage)
        $coverageRatio = $matchedTerms / count($importantTerms);
        if ($coverageRatio >= 1.0) {
            $baseScore += self::FULL_COVERAGE_BONUS;
        } elseif ($coverageRatio >= 0.75) {
            $baseScore += 0.1;
        }
        
        return min(1.0, $baseScore);
    }
    
    /**
     * =========================================================================
     * 1. METODE CALCULATE EXACT PHRASE BONUS
     * =========================================================================
     *
     * Fungsi:
     * Menghitung bonus frasa exact untuk dokumen yang mengandung query di judul.
     *
     * Alur Proses:
     * 1. Memfilter term dengan prioritas rendah.
     * 2. Memeriksa kecocokan frasa exact di judul.
     * 3. Memeriksa kecocokan di excerpt dan konten.
     * 4. Mengembalikan bonus frasa yang dihitung.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - float
     */
    private function calculateExactPhraseBonus(string $originalQuery, array $document): float
    {
        $title = $document['title'] ?? '';
        $excerpt = $document['excerpt'] ?? '';
        $content = $document['text'] ?? '';
        
        $queryLower = strtolower(trim($originalQuery));
        $titleLower = strtolower($title);
        
        // Filter out low-prioritas terms dari query untuk frasa kecocokan
        $queryWords = explode(' ', $queryLower);
        $importantWords = array_filter($queryWords, fn($w) => 
            mb_strlen($w) > 2 && !$this->isLowPriorityTerm($w)
        );
        $importantWords = array_values($importantWords);
        
        // EXACT MATCH: Full query frasa di judul (highest prioritas)
        if (str_contains($titleLower, $queryLower)) {
            return 1.0;
        }
        
        // EXACT PHRASE MATCH: All penting words muncul consecutively di judul
        if (count($importantWords) >= 2) {
            $importantPhrase = implode(' ', $importantWords);
            if (str_contains($titleLower, $importantPhrase)) {
                return 0.9;
            }
        }
        
        // ALL IMPORTANT WORDS IN TITLE (tidak necessarily consecutive)
        $allImportantInTitle = true;
        foreach ($importantWords as $word) {
            if (!str_contains($titleLower, $word)) {
                $allImportantInTitle = false;
                break;
            }
        }
        
        if ($allImportantInTitle && count($importantWords) >= 2) {
            return 0.75;
        }
        
        // Most words di judul (dengan low-prioritas terms filtered)
        $wordsInTitle = 0;
        foreach ($importantWords as $word) {
            if (str_contains($titleLower, $word)) {
                $wordsInTitle++;
            }
        }
        
        if (count($importantWords) > 0 && $wordsInTitle / count($importantWords) >= 0.75) {
            return 0.5;
        }
        
        // Periksa excerpt untuk exact frasa
        $excerptLower = strtolower($excerpt);
        if (str_contains($excerptLower, $queryLower)) {
            return 0.4;
        }
        
        // Periksa konten untuk exact frasa
        $contentLower = strtolower($content);
        if (str_contains($contentLower, $queryLower)) {
            return 0.3;
        }
        
        return 0.0;
    }
    
    /**
     * =========================================================================
     * 1. METODE CALCULATE DOMAIN PENALTY
     * =========================================================================
     *
     * Fungsi:
     * Menghitung penalti domain untuk artikel dari domain yang tidak terkait.
     *
     * Alur Proses:
     * 1. Memeriksa penalti domain negatif.
     * 2. Memeriksa kata kunci domain terlarang di konten/judul.
     * 3. Menerapkan penalti yang lebih kuat untuk domain yang tidak terkait.
     * 4. Mengembalikan nilai penalti domain.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - float
     */
    private function calculateDomainPenalty(array $document, array $domainInfo): float
    {
        if (!$domainInfo['detected'] || empty($domainInfo['domain'])) {
            return 0.0;
        }
        
        $detectedDomain = $domainInfo['domain'];
        $docCategory = $document['category_name'] ?? '';
        $docCategoryLower = strtolower(trim($docCategory));
        $title = strtolower($document['title'] ?? '');
        $content = strtolower($document['text'] ?? '');
        
        // Periksa negative domain penalties (stronger penalties untuk unrelated domains)
        $negativeDomains = $this->negativeDomainPenalties[$detectedDomain] ?? [];
        foreach ($negativeDomains as $negativeDomain) {
            // Periksa jika the negative domain keyword muncul di judul atau konten
            if (str_contains($title, $negativeDomain) || str_contains($content, $negativeDomain)) {
                return self::STRONG_DOMAIN_PENALTY;
            }
            // Periksa jika the dokumen kategori cocok the negative domain
            if ($docCategoryLower === strtolower($negativeDomain)) {
                return self::STRONG_DOMAIN_PENALTY;
            }
        }
        
        // Also periksa forbidden domain peta (legacy penalty)
        $forbiddenDomains = $this->forbiddenDomainMap[$detectedDomain] ?? [];
        foreach ($forbiddenDomains as $forbidden) {
            if ($docCategoryLower === strtolower($forbidden)) {
                return self::DOMAIN_PENALTY;
            }
        }
        
        return 0.0;
    }
    
    /**
     * =========================================================================
     * 1. METODE Diversify Results
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi diversify results di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function diversifyResults(array $rankedResults, array $documents): array
    {
        $seenCategories = [];
        $seenTitlePatterns = [];
        
        foreach ($rankedResults as &$result) {
            $docId = $result['doc_id'];
            $document = $documents[$docId] ?? [];
            $category = $document['category_name'] ?? 'unknown';
            $title = strtolower($document['title'] ?? '');
            
            if (isset($seenCategories[$category])) {
                $seenCategories[$category]++;
                $penalty = 0.1 * ($seenCategories[$category] - 1);
                $result['final_score'] = max(0, $result['final_score'] - $penalty);
                $result['diversification_penalty'] = $penalty;
            } else {
                $seenCategories[$category] = 1;
            }
            
            $titleWords = preg_split('/\s+/', $title);
            foreach ($titleWords as $word) {
                if (mb_strlen($word) > 3) {
                    if (isset($seenTitlePatterns[$word])) {
                        $result['final_score'] = max(0, $result['final_score'] - 0.05);
                        $result['title_diversity_penalty'] = 0.05;
                    }
                    $seenTitlePatterns[$word] = true;
                }
            }
        }
        
        usort($rankedResults, fn($a, $b) => $b['final_score'] <=> $a['final_score']);
        
        return $rankedResults;
    }
    
    /**
     * =========================================================================
     * 1. METODE Detect Multi Intent
     * =========================================================================
     *
     * Fungsi:
     * Mendeteksi detect multi intent dari query pengguna.
     *
     * Alur Proses:
     * 1. Analisis input query.
     * 1. Cocokkan token terhadap pola atau domain.
     * 1. Mengembalikan keputusan deteksi beserta metadata.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
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
    
    /**
     * =========================================================================
     * 1. METODE Track Retrieval Result
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi track retrieval result di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - void
     */
    private function trackRetrievalResult(string $query, array $results): void
    {
        $normalizedQuery = $this->normalizeQueryForTracking($query);
        $failureMemory = Session::get(self::SESSION_FAILURE_KEY, []);
        
        if (empty($results)) {
            $failureMemory[$normalizedQuery] = ($failureMemory[$normalizedQuery] ?? 0) + 1;
        } else {
            $failureMemory[$normalizedQuery] = 0;
        }
        
        // Limit memory size
        if (count($failureMemory) > self::MAX_FAILURE_MEMORY) {
            $failureMemory = array_slice($failureMemory, -self::MAX_FAILURE_MEMORY, null, true);
        }
        
        Session::put(self::SESSION_FAILURE_KEY, $failureMemory);
    }
    
    /**
     * =========================================================================
     * 2. Metode Menentukan Eskalasi
     * =========================================================================
     * 
     * Metode ini memeriksa apakah query telah gagal berkali-kali dan
     * membutuhkan eskalasi ke staff atau tiket.
     * 
     * Parameter:
     * string $query
     * 
     * Kembalikan:
     * bool
     */
    public function shouldEscalate(string $query): bool
    {
        $normalizedQuery = $this->normalizeQueryForTracking($query);
        $failureMemory = Session::get(self::SESSION_FAILURE_KEY, []);
        return ($failureMemory[$normalizedQuery] ?? 0) >= self::FAILURE_THRESHOLD;
    }
    
    /**
     * =========================================================================
     * 3. Metode Respon Eskalasi
     * =========================================================================
     * 
     * Metode ini mengembalikan paket data ketika sistem memutuskan untuk
     * mengarahkan pengguna ke support atau tiket.
     * 
     * Kembalikan:
     * array
     */
    public function getEscalationResponse(): array
    {
        return [
            'success' => false,
            'response' => "Sepertinya saya belum menemukan solusi yang tepat 😔\n\nJangan khawatir, tim support kami siap membantu!",
            'should_escalate' => true,
            'escalation_buttons' => [
                ['label' => '💬 Live Chat', 'action' => 'contact_staff'],
                ['label' => '📧 Buat Tiket', 'action' => 'create_ticket'],
                ['label' => '🔄 Coba Pertanyaan Lain', 'action' => 'try_another'],
            ],
        ];
    }
    
    /**
     * =========================================================================
     * 4. Metode Menghitung Kegagalan Query
     * =========================================================================
     * 
     * Metode ini menghitung berapa kali query gagal menemukan hasil yang layak.
     * 
     * Parameter:
     * string $query
     * 
     * Kembalikan:
     * int
     */
    public function getFailureCount(string $query): int
    {
        $normalizedQuery = $this->normalizeQueryForTracking($query);
        $failureMemory = Session::get(self::SESSION_FAILURE_KEY, []);
        return $failureMemory[$normalizedQuery] ?? 0;
    }
    
    /**
     * Clear failure memory untuk a query (called ketika success is achieved)
     */
    /**
     * =========================================================================
     * 5. Metode Membersihkan Memori Kegagalan Query
     * =========================================================================
     * 
     * Metode ini menghapus catatan kegagalan untuk query yang sudah berhasil.
     * 
     * Parameter:
     * string $query
     * 
     * Kembalikan:
     * void
     */
    public function clearFailureForQuery(string $query): void
    {
        $normalizedQuery = $this->normalizeQueryForTracking($query);
        $failureMemory = Session::get(self::SESSION_FAILURE_KEY, []);
        unset($failureMemory[$normalizedQuery]);
        Session::put(self::SESSION_FAILURE_KEY, $failureMemory);
    }
    
    /**
     * Store conversation context untuk memory
     */
    /**
     * =========================================================================
     * 6. Metode Menyimpan Konteks Percakapan
     * =========================================================================
     * 
     * Metode ini menyimpan alur percakapan terakhir untuk mendukung sesi dialog.
     * 
     * Parameter:
     * array $context
     * 
     * Kembalikan:
     * void
     */
    public function storeConversationContext(array $context): void
    {
        $conversationMemory = Session::get(self::SESSION_CONVERSATION_KEY, []);
        
        $conversationMemory[] = array_merge($context, [
            'timestamp' => now()->timestamp,
        ]);
        
        // Simpan hanya last 10 interactions
        $conversationMemory = array_slice($conversationMemory, -10);
        
        Session::put(self::SESSION_CONVERSATION_KEY, $conversationMemory);
    }
    
    /**
     * Get recent conversation context
     */
    /**
     * =========================================================================
     * 7. Metode Mendapatkan Konteks Percakapan Terbaru
     * =========================================================================
     * 
     * Metode ini mengambil beberapa interaksi terakhir dari sesi chatbot.
     * 
     * Parameter:
     * int $batas
     * 
     * Kembalikan:
     * array
     */
    public function getRecentConversationContext(int $limit = 3): array
    {
        $conversationMemory = Session::get(self::SESSION_CONVERSATION_KEY, []);
        
        if (empty($conversationMemory)) {
            return [];
        }
        
        return array_slice(array_reverse($conversationMemory), 0, $limit);
    }
    
    /**
     * Clear conversation memory
     */
    /**
     * =========================================================================
     * 8. Metode Menghapus Memori Percakapan
     * =========================================================================
     * 
     * Metode ini mengosongkan memori percakapan sehingga sesi berikutnya
     * dimulai tanpa data sebelumnya.
     * 
     * Kembalikan:
     * void
     */
    public function clearConversationMemory(): void
    {
        Session::forget(self::SESSION_CONVERSATION_KEY);
    }
    
    /**
     * Get clarification suggestions untuk ambiguous query
     */
    /**
     * =========================================================================
     * 9. Metode Mendapatkan Saran Klarifikasi
     * =========================================================================
     * 
     * Metode ini menghasilkan saran klarifikasi berdasarkan domain query.
     * 
     * Parameter:
     * string $query
     * 
     * Kembalikan:
     * array
     */
    public function getClarificationSuggestions(string $query): array
    {
        $domainInfo = $this->domainDetector->detectDomain($query);
        $domain = $domainInfo['domain'] ?? null;
        
        if ($domain && isset($this->curatedSubtopics[$domain])) {
            return $this->getCuratedSubtopics($domain);
        }
        
        // Generic clarification
        return [
            ['id' => 'troubleshooting', 'label' => 'Troubleshooting', 'query' => 'troubleshooting'],
            ['id' => 'tutorial', 'label' => 'Tutorial', 'query' => 'tutorial'],
            ['id' => 'optimization', 'label' => 'Optimasi', 'query' => 'optimasi'],
            ['id' => 'hardware', 'label' => 'Hardware', 'query' => 'hardware'],
        ];
    }
    
    /**
     * Periksa jika query needs clarification (ambiguous/generic)
     */
    /**
     * =========================================================================
     * 10. Metode Memeriksa Kebutuhan Klarifikasi
     * =========================================================================
     * 
     * Metode ini menentukan apakah query terlalu umum atau ambigu.
     * 
     * Parameter:
     * string $query
     * 
     * Kembalikan:
     * bool
     */
    public function needsClarification(string $query): bool
    {
        $lowerQuery = strtolower(trim($query));
        
        // Very short query need clarification
        if (strlen($lowerQuery) < 5) {
            return true;
        }
        
        // Generic issue terms without domain context
        $genericOnlyPatterns = [
            '/^lemot$/i',
            '/^lambat$/i',
            '/^error$/i',
            '/^eror$/i',
            '/^tidak bisa$/i',
            '/^gak bisa$/i',
            '/^bermasalah$/i',
            '/^rusak$/i',
        ];
        
        foreach ($genericOnlyPatterns as $pattern) {
            if (preg_match($pattern, $lowerQuery)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Get clarification response untuk ambiguous query
     */
    /**
     * =========================================================================
     * 11. Metode Respon Klarifikasi
     * =========================================================================
     * 
     * Metode ini membentuk jawaban klarifikasi untuk query ambigu.
     * 
     * Parameter:
     * string $query
     * 
     * Kembalikan:
     * array
     */
    public function getClarificationResponse(string $query): array
    {
        $suggestions = $this->getClarificationSuggestions($query);
        
        $clarificationQuestions = [
            'Bisa lebih spesifik? 😊',
            'Bisa jelaskan lebih detail? 🤔',
            'Apa yang sedang bermasalah? 💭',
        ];
        
        $question = $clarificationQuestions[array_rand($clarificationQuestions)];
        
        return [
            'success' => true,
            'needs_clarification' => true,
            'clarification_question' => $question,
            'suggestions' => $suggestions,
        ];
    }
    
    /**
     * =========================================================================
     * 1. METODE DIVERSIFY RESULTS ENHANCED
     * =========================================================================
     *
     * Fungsi:
     * Melakukan diversifikasi hasil dengan kuota kategori.
     *
     * Alur Proses:
     * 1. Memeriksa kuota kategori untuk setiap hasil.
     * 2. Menerapkan penalti untuk hasil yang melebihi kuota.
     * 3. Memeriksa diversifikasi pola judul.
     * 4. Mengembalikan hasil yang sudah didiversifikasi.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function diversifyResultsEnhanced(array $rankedResults, array $documents): array
    {
        $categoryCounts = [];
        $seenTitlePatterns = [];
        $diversifiedResults = [];
        
        foreach ($rankedResults as $result) {
            $docId = $result['doc_id'];
            $document = $documents[$docId] ?? [];
            $category = $document['category_name'] ?? 'unknown';
            $title = strtolower($document['title'] ?? '');
            
            // Periksa kategori quota
            $categoryCounts[$category] = $categoryCounts[$category] ?? 0;
            if ($categoryCounts[$category] >= self::MAX_RESULTS_PER_CATEGORY) {
                // Apply heavy penalty untuk exceeding quota
                $result['final_score'] = max(0, $result['final_score'] - 0.3);
                $result['category_quota_exceeded'] = true;
            }
            
            // Periksa judul pattern diversity (avoid BSOD domination, etc.)
            $titleWords = preg_split('/\s+/', $title);
            $titlePenalty = 0;
            foreach ($titleWords as $word) {
                if (mb_strlen($word) > 3 && isset($seenTitlePatterns[$word])) {
                    $titlePenalty += 0.05;
                }
                if (mb_strlen($word) > 3) {
                    $seenTitlePatterns[$word] = ($seenTitlePatterns[$word] ?? 0) + 1;
                }
            }
            
            $result['final_score'] = max(0, $result['final_score'] - $titlePenalty);
            if ($titlePenalty > 0) {
                $result['title_diversity_penalty'] = $titlePenalty;
            }
            
            $categoryCounts[$category]++;
            $diversifiedResults[] = $result;
        }
        
        // Re-sort setelah penalties
        usort($diversifiedResults, fn($a, $b) => $b['final_score'] <=> $a['final_score']);
        
        return $diversifiedResults;
    }
    
    /**
     * =========================================================================
     * 1. METODE Normalize Query For Tracking
     * =========================================================================
     *
     * Fungsi:
     * Menormalisasi normalize query for tracking agar konsisten di seluruh pipeline.
     *
     * Alur Proses:
     * 1. Membersihkan teks/kata dari variasi atau typo.
     * 1. Mengubah format ke bentuk standar.
     * 1. Mengembalikan string atau token yang dinormalisasi.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function normalizeQueryForTracking(string $query): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $query)));
    }
    
    /**
     * =========================================================================
     * 1. METODE Prepare Documents
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi prepare documents di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function prepareDocuments(Collection $articles): array
    {
        $documents = [];
        
        foreach ($articles as $article) {
            $titleTokens = $this->preprocessor->preprocess($article->title);
            $excerptTokens = $this->preprocessor->preprocess($article->excerpt ?? '');
            $keywordsTokens = $this->preprocessor->preprocess($article->keywords ?? '');
            $contentTokens = $this->preprocessor->preprocess($article->content);
            
            $allTokens = [];
            
            for ($i = 0; $i < 3; $i++) {
                foreach ($titleTokens as $token) {
                    $allTokens[] = $token;
                }
            }
            
            for ($i = 0; $i < 2; $i++) {
                foreach ($keywordsTokens as $token) {
                    $allTokens[] = $token;
                }
            }
            
            foreach ($excerptTokens as $token) {
                $allTokens[] = $token;
                if (rand(0, 1) === 0) {
                    $allTokens[] = $token;
                }
            }
            
            $allTokens = array_merge($allTokens, $contentTokens);
            
            $frequency = [];
            foreach ($allTokens as $token) {
                $frequency[$token] = ($frequency[$token] ?? 0) + 1;
            }
            
            $documents[$article->id] = [
                'text' => implode(' ', $allTokens),
                'frequency' => $frequency,
                'title' => $article->title,
                'title_tokens' => $titleTokens,
                'excerpt' => $article->excerpt,
                'content' => $article->content,
                'keywords' => $article->keywords,
                'slug' => $article->slug,
                'category_id' => $article->category_id,
                'category_name' => $article->category->name ?? '',
            ];
        }
        
        return $documents;
    }
    
    /**
     * =========================================================================
     * 1. METODE Build Tfidf Vectors
     * =========================================================================
     *
     * Fungsi:
     * Membangun objek/struktur build tfidf vectors untuk pipeline retrieval.
     *
     * Alur Proses:
     * 1. Mempersiapkan data awal untuk build tfidf vectors.
     * 1. Menggabungkan atribut penting.
     * 1. Mengembalikan objek atau array yang siap dipakai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function buildTfidfVectors(array $documents): array
    {
        $documentTermFrequencies = [];
        foreach ($documents as $docId => $doc) {
            $documentTermFrequencies[$docId] = $doc['frequency'];
        }
        
        $idf = $this->tfidfService->calculateIDF($documentTermFrequencies);
        
        $vectors = [];
        foreach ($documentTermFrequencies as $docId => $termFreq) {
            $tf = $this->tfidfService->calculateTF($termFreq);
            $vectors[$docId] = $this->tfidfService->calculateTFIDF($tf, $idf);
        }
        
        return [
            'vectors' => $vectors,
            'idf' => $idf,
            'docCount' => count($documents),
        ];
    }
    
    /**
     * =========================================================================
     * 1. METODE Apply Threshold And Limit
     * =========================================================================
     *
     * Fungsi:
     * Menerapkan transformasi atau boost pada data apply threshold and limit.
     *
     * Alur Proses:
     * 1. Menerima input dasar dan aturan boosting.
     * 1. Menghitung nilai tambahan berdasarkan kondisi.
     * 1. Mengembalikan data dengan penyesuaian yang diterapkan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function applyThresholdAndLimit(array $rankedResults, int $limit): array
    {
        $finalResults = [];
        
        foreach ($rankedResults as $result) {
            if (count($finalResults) >= $limit) {
                break;
            }
            
            if ($result['final_score'] < self::SIMILARITY_THRESHOLD) {
                continue;
            }
            
            $doc = $result['document'];
            
            // Gunakan the doc_id dari the ranked hasil (which is the article ID)
            $articleId = $result['doc_id'] ?? $doc['doc_id'] ?? $doc['id'] ?? null;
            
            $finalResults[] = [
                'id' => $articleId,
                'title' => $doc['title'],
                'excerpt' => $doc['excerpt'],
                'content' => $doc['content'] ?? '',
                'slug' => $doc['slug'],
                'category_id' => $doc['category_id'],
                'category_name' => $doc['category_name'] ?? null,
                'similarity' => round($result['cosine_similarity'], 4),
                'final_score' => round($result['final_score'], 4),
                'confidence' => $this->getConfidenceLevel($result['final_score']),
                'url' => route('articles.show', $doc['slug']),
            ];
        }
        
        return $finalResults;
    }
    
    /**
     * =========================================================================
     * 1. METODE Get Confidence Level
     * =========================================================================
     *
     * Fungsi:
     * Mengambil data get confidence level untuk keperluan logika service.
     *
     * Alur Proses:
     * 1. Menentukan sumber data untuk get confidence level.
     * 1. Mengambil atau memformat data.
     * 1. Mengembalikan hasil dalam struktur yang sesuai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function getConfidenceLevel(float $score): string
    {
        if ($score >= self::HIGH_SIMILARITY_THRESHOLD) {
            return 'high';
        } elseif ($score >= self::SIMILARITY_THRESHOLD) {
            return 'medium';
        }
        return 'low';
    }
    
    /**
     * =========================================================================
     * 1. METODE Empty Result
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi empty result di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function emptyResult(string $query): array
    {
        $this->trackRetrievalResult($query, []);
        
        return [
            'results' => [],
            'query' => $query,
            'total' => 0,
            'threshold_met' => false,
            'max_similarity' => 0,
        ];
    }
    
    /**
     * =========================================================================
     * 1. METODE OUT OF DOMAIN RESULT
     * =========================================================================
     *
     * Fungsi:
     * Mengembalikan hasil untuk query di luar domain.
     *
     * Alur Proses:
     * 1. Menerima query yang terdeteksi di luar domain.
     * 2. Mencatat informasi penolakan ke log.
     * 3. Mengembalikan respons penolakan yang sopan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function outOfDomainResult(string $query): array
    {
        Log::info('OUT-OF-DOMAIN query rejected', [
            'query' => $query,
            'reason' => 'non-IT domain',
        ]);
        
        return [
            'results' => [],
            'query' => $query,
            'total' => 0,
            'threshold_met' => false,
            'max_similarity' => 0,
            'is_out_of_domain' => true,
            'out_of_domain_message' => DomainDetectionService::OUT_OF_DOMAIN_MESSAGE,
        ];
    }
    
    /**
     * =========================================================================
     * 1. METODE Log Stage
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi log stage di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - void
     */
    private function logStage(string $stage, string $input, string $output): void
    {
        $this->debugInfo['stages'][] = [
            'stage' => $stage,
            'input' => substr($input, 0, 100),
            'output' => substr($output, 0, 200),
        ];
    }
    
    /**
     * =========================================================================
     * 12. Metode Mendapatkan Kategori Kurasi
     * =========================================================================
     * 
     * Metode ini mengembalikan daftar kategori yang dikurasi untuk chatbot.
     * 
     * Kembalikan:
     * array
     */
    public function getCuratedCategories(): array
    {
        return $this->curatedCategories;
    }
    
    /**
     * =========================================================================
     * 13. Metode Mendapatkan Subtopik Kurasi
     * =========================================================================
     * 
     * Metode ini mengembalikan subtopik yang telah dipetakan untuk kategori.
     * 
     * Parameter:
     * string $categoryId
     * 
     * Kembalikan:
     * array
     */
    public function getCuratedSubtopics(string $categoryId): array
    {
        $subtopics = $this->curatedSubtopics[$categoryId] ?? [];
        
        return array_map(fn($subtopic) => [
            'id' => md5($subtopic),
            'label' => ucfirst($subtopic),
            'query' => $subtopic,
        ], $subtopics);
    }
    
    /**
     * =========================================================================
     * 14. Metode Mendeteksi Greeting
     * =========================================================================
     * 
     * Metode ini memeriksa apakah query adalah sapaan pengguna.
     * 
     * Parameter:
     * string $query
     * 
     * Kembalikan:
     * bool
     */
    public function isGreeting(string $query): bool
    {
        $greetings = ['halo', 'hai', 'hello', 'hi', 'pagi', 'siang', 'sore', 'malam', 'assalamualaikum', 'permisi'];
        $lowerQuery = mb_strtolower(trim($query));
        
        foreach ($greetings as $greeting) {
            if ($lowerQuery === $greeting || str_starts_with($lowerQuery, $greeting . ' ') || str_ends_with($lowerQuery, ' ' . $greeting)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * =========================================================================
     * 15. Metode Menghasilkan Respon Greeting
     * =========================================================================
     * 
     * Metode ini menghasilkan sapaan yang sesuai waktu untuk pengguna.
     * 
     * Kembalikan:
     * string
     */
    public function getGreetingResponse(): string
    {
        $hour = date('H');
        
        if ($hour < 11) {
            $greetings = ['Selamat pagi! 👋', 'Pagi! Ada yang bisa saya bantu?'];
        } elseif ($hour < 15) {
            $greetings = ['Selamat siang! 👋', 'Siang! Silakan tanyakan sesuatu.'];
        } elseif ($hour < 18) {
            $greetings = ['Selamat sore! 👋', 'Sore! Ada yang bisa saya bantu?'];
        } else {
            $greetings = ['Selamat malam! 👋', 'Malam! Silakan tanyakan sesuatu.'];
        }
        
        $greetings[] = 'Halo! Ada yang bisa saya bantu?';
        
        return $greetings[array_rand($greetings)];
    }
    
    /**
     * =========================================================================
     * 1. METODE SHOULD USE SAFE FALLBACK
     * =========================================================================
     *
     * Fungsi:
     * Mengevaluasi apakah hasil retrieval terlalu lemah dan perlu fallback aman.
     *
     * Alur Proses:
     * 1. Memeriksa skor tertinggi terhadap threshold aman.
     * 2. Memeriksa sinyal tambahan dari debug info.
     * 3. Memeriksa overlap judul dan coverage query.
     * 4. Mengembalikan keputusan penggunaan fallback.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool
     */
    private function shouldUseSafeFallback(array $retrievalResult): bool
    {
        if (empty($retrievalResult['results'])) {
            return true;
        }
        
        $topArticle = $retrievalResult['results'][0];
        $topScore = $topArticle['final_score'] ?? 0;
        
        // Jika top skor is above safe fallback ambang, hasil are acceptable
        if ($topScore >= self::SAFE_FALLBACK_THRESHOLD) {
            return false;
        }
        
        // Periksa debug info untuk additional signals
        $debugInfo = $retrievalResult['debug'] ?? null;
        if (!$debugInfo) {
            // Without debug info, we dapat't verify additional signals
            // Fall back ke skor-based decision
            return $topScore < self::SAFE_FALLBACK_THRESHOLD;
        }
        
        // Periksa jika there are apa pun strong signals di the skor
        $scores = $debugInfo['scores'] ?? [];
        $hasStrongTitleOverlap = false;
        $hasStrongQueryCoverage = false;
        $hasExactPhraseMatch = false;
        
        foreach ($scores as $docId => $scoreBreakdown) {
            // Periksa untuk strong judul overlap (above 0.5)
            if (($scoreBreakdown['title_overlap'] ?? 0) > 0.5) {
                $hasStrongTitleOverlap = true;
            }
            // Periksa untuk exact frasa cocok (exact_phrase skor of 0.75+)
            if (($scoreBreakdown['exact_phrase'] ?? 0) >= 0.75) {
                $hasExactPhraseMatch = true;
            }
            // Periksa untuk strong query coverage (above 0.7)
            if (($scoreBreakdown['query_coverage'] ?? 0) > 0.7) {
                $hasStrongQueryCoverage = true;
            }
        }
        
        // Jika ANY strong signal exists, allow the hasil through
        if ($hasStrongTitleOverlap || $hasStrongQueryCoverage || $hasExactPhraseMatch) {
            return false;
        }
        
        // All signals are weak - gunakan safe fallback
        return true;
    }
    
    /**
     * =========================================================================
     * 1. METODE GET SAFE FALLBACK RESPONSE
     * =========================================================================
     *
     * Fungsi:
     * Mengembalikan respons fallback aman untuk query yang lemah/tidak jelas.
     *
     * Alur Proses:
     * 1. Menerima query yang terlalu lemah.
     * 2. Mencatat informasi fallback ke log.
     * 3. Memilih pesan fallback yang sesuai.
     * 4. Mengembalikan respons dengan saran kategori.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function getSafeFallbackResponse(string $query): array
    {
        Log::info('Safe fallback triggered - query too weak', [
            'query' => $query,
            'reason' => 'All retrieval signals below threshold',
        ]);
        
        $fallbackMessages = [
            "Maaf, saya kurang yakin dengan jawaban yang tepat untuk pertanyaan ini 🤔\n\nBisa coba jelaskan lebih spesifik? Misalnya:\n• Sebutkan perangkat yang bermasalah (wifi, printer, komputer, dll)\n• Jelaskan gejala atau error yang muncul\n• Sertakan pesan error jika ada",
            "Sepertinya saya butuh informasi lebih detail untuk membantu Anda 💭\n\nCoba tambahkan:\n• Apa yang sedang Anda lakukan saat masalah muncul?\n• Perangkat atau aplikasi apa yang digunakan?\n• Sudah coba solusi apa saja?",
            "Mohon maaf, pertanyaan ini terlalu umum untuk saya jawab dengan tepat 😅\n\nAgar saya bisa membantu lebih baik:\n• Sebutkan jenis masalahnya (lemot, error, tidak connect, dll)\n• Perangkat apa yang bermasalah?\n• Kapan masalah ini terjadi?",
        ];
        
        $hash = md5($query . now()->timestamp);
        $index = hexdec(substr($hash, 0, 4)) % count($fallbackMessages);
        
        return [
            'success' => false,
            'response' => $fallbackMessages[$index],
            'articles' => [],
            'show_contact_button' => true,
            'contact_button_text' => 'Hubungi Staff untuk Bantuan Langsung',
            'confidence' => 'very_low',
            'is_safe_fallback' => true,
            'suggestions' => $this->getCuratedCategories(),
        ];
    }
    
    /**
     * =========================================================================
     * 16. Metode Membentuk Respon Akhir
     * =========================================================================
     * 
     * Metode ini menyusun respon chatbot final berdasarkan hasil retrieval.
     * 
     * Parameter:
     * array $retrievalResult
     * 
     * Kembalikan:
     * array
     */
    public function formatResponse(array $retrievalResult): array
    {
        // Periksa untuk OUT-OF-DOMAIN query first
        if (!empty($retrievalResult['is_out_of_domain'])) {
            return [
                'success' => false,
                'response' => $retrievalResult['out_of_domain_message'] ?? DomainDetectionService::OUT_OF_DOMAIN_MESSAGE,
                'articles' => [],
                'show_contact_button' => false,
                'is_out_of_domain' => true,
                'confidence' => 'none',
            ];
        }
        
        // Periksa jika hasil are too weak - gunakan safe fallback instead of unrelated articles
        if ($this->shouldUseSafeFallback($retrievalResult)) {
            $this->trackRetrievalResult($retrievalResult['query'] ?? '', []);
            return $this->getSafeFallbackResponse($retrievalResult['query'] ?? '');
        }
        
        if (empty($retrievalResult['results'])) {
            if ($this->shouldEscalate($retrievalResult['query'] ?? '')) {
                return $this->getEscalationResponse();
            }
            
            return [
                'success' => false,
                'response' => 'Maaf, saya belum menemukan artikel yang sesuai. Coba gunakan kata kunci yang lebih spesifik.',
                'articles' => [],
                'show_contact_button' => true,
                'contact_button_text' => 'Buat Tiket untuk Bantuan Lebih Lanjut',
                'confidence' => 'none',
            ];
        }
        
        $topArticle = $retrievalResult['results'][0];
        $confidence = $topArticle['confidence'] ?? 'medium';
        
        // Upgrade confidence ke 'very_low' jika skor is extremely low
        $topScore = $topArticle['final_score'] ?? 0;
        if ($topScore < self::SIMILARITY_THRESHOLD * 1.2) {
            $confidence = 'very_low';
        }
        
        $response = $this->generateResponseText($topArticle, count($retrievalResult['results']), $confidence);
        
        // Show contact button untuk low dan very_low confidence
        $showContactButton = in_array($confidence, ['low', 'very_low']);
        
        return [
            'success' => true,
            'response' => $response,
            'articles' => $retrievalResult['results'],
            'show_contact_button' => $showContactButton,
            'contact_button_text' => $confidence === 'very_low' 
                ? 'Masih kurang yakin? Hubungi staff kami' 
                : 'Masih butuh bantuan? Hubungi staff kami',
            'confidence' => $confidence,
        ];
    }
    
    /**
     * =========================================================================
     * 1. METODE Generate Response Text
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi generate response text di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function generateResponseText(array $topArticle, int $totalResults, string $confidence): string
    {
        $title = $topArticle['title'];
        $excerpt = $topArticle['excerpt'] ?? '';
        $content = $topArticle['content'] ?? '';

        // Generate short summary dari excerpt atau konten
        $summary = $this->generateSummaryFromExcerpt($excerpt, $content, $title);

        // Bangun response dengan summary + a lebih assistant-style label
        $response = $summary . "\n\nUntuk panduan lebih lengkap, silakan lihat artikel berikut:";

        return $response;
    }

    /**
     * =========================================================================
     * 1. METODE GENERATE SUMMARY FROM EXCERPT
     * =========================================================================
     *
     * Fungsi:
     * Menghasilkan ringkasan singkat dari excerpt atau konten.
     *
     * Alur Proses:
     * 1. Menerima excerpt, konten, dan judul.
     * 2. Membersihkan tag HTML dari excerpt.
     * 3. Memeriksa apakah excerpt informatif.
     * 4. Mengambil paragraf pertama jika excerpt tidak informatif.
     * 5. Memperpendek ringkasan sesuai batas karakter.
     * 6. Mengembalikan ringkasan yang sudah diproses.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function generateSummaryFromExcerpt(string $excerpt, string $content = '', string $title = ''): string
    {
        // Periksa jika excerpt is informative enough (tidak just a description)
        $excerptText = $this->stripHtmlTags($excerpt);
        $excerptSentences = preg_split('/(?<=[.!?])\s+/', $excerptText, -1, PREG_SPLIT_NO_EMPTY);

        // Gunakan excerpt jika it has at least 2 sentences dan is tidak too similar ke judul
        $useExcerpt = count($excerptSentences) >= 2 && !$this->isTooSimilarToTitle($excerptText, $title);

        if ($useExcerpt) {
            $summary = $this->extractSentences($excerptText, 1, 2);
        } elseif (!empty($content)) {
            // Gunakan first paragraph dari konten jika excerpt is tidak informative
            $contentText = $this->stripHtmlTags($content);
            $firstParagraph = $this->extractFirstParagraph($contentText);
            $summary = $this->extractSentences($firstParagraph, 1, 2);
        } else {
            // Fallback
            return 'Saya menemukan beberapa informasi yang relevan dengan pertanyaan Anda.';
        }

        // Shorten the summary ke a concise assistant-style response
        $summary = $this->shortenSummary($summary, 280, 2);

        // Pastikan it ends dengan proper punctuation
        if (!in_array(substr($summary, -1), ['.', '!', '?'])) {
            $summary .= '.';
        }

        return $summary;
    }

    /**
     * =========================================================================
     * 1. METODE STRIP HTML TAGS
     * =========================================================================
     *
     * Fungsi:
     * Menghapus tag HTML dari teks.
     *
     * Alur Proses:
     * 1. Menerima teks HTML.
     * 2. Menghapus tag HTML.
     * 3. Decode entitas HTML.
     * 4. Normalisasi whitespace.
     * 5. Mengembalikan teks bersih.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function stripHtmlTags(string $html): string
    {
        // Hapus HTML tags
        $text = strip_tags($html);
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * =========================================================================
     * 1. METODE IS TOO SIMILAR TO TITLE
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah teks terlalu mirip dengan judul.
     *
     * Alur Proses:
     * 1. Menerima teks dan judul.
     * 2. Memeriksa apakah teks mengandung judul.
     * 3. Memeriksa panjang teks.
     * 4. Mengembalikan status kesamaan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool
     */
    private function isTooSimilarToTitle(string $text, string $title): bool
    {
        if (empty($title)) {
            return false;
        }

        $textLower = mb_strtolower($text);
        $titleLower = mb_strtolower($title);

        // Periksa jika teks mengandung judul atau judul mengandung teks
        if (str_contains($textLower, $titleLower) || str_contains($titleLower, $textLower)) {
            return true;
        }

        // Periksa jika teks is very short (less daripada 50 chars)
        if (mb_strlen($text) < 50) {
            return true;
        }

        return false;
    }

    /**
     * =========================================================================
     * 1. METODE EXTRACT FIRST PARAGRAPH
     * =========================================================================
     *
     * Fungsi:
     * Mengambil paragraf pertama dari teks.
     *
     * Alur Proses:
     * 1. Menerima teks input.
     * 2. Memecah teks menjadi paragraf.
     * 3. Mengembalikan paragraf pertama.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function extractFirstParagraph(string $text): string
    {
        // Split dengan double newline atau multiple newlines
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($paragraphs)) {
            return $text;
        }

        // Kembalikan first paragraph, cleaned
        return trim($paragraphs[0]);
    }

    /**
     * =========================================================================
     * 1. METODE EXTRACT SENTENCES
     * =========================================================================
     *
     * Fungsi:
     * Mengambil N sampai M kalimat dari teks.
     *
     * Alur Proses:
     * 1. Menerima teks input dan batas kalimat.
     * 2. Memecah teks menjadi kalimat.
     * 3. Mengambil kalimat sesuai batas.
     * 4. Mengembalikan kalimat yang digabung.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function extractSentences(string $text, int $min, int $max): string
    {
        // Split into sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($sentences)) {
            return $text;
        }

        // Take min ke max sentences
        $count = min($max, max($min, count($sentences)));
        $selectedSentences = array_slice($sentences, 0, $count);

        return implode(' ', $selectedSentences);
    }

    /**
     * =========================================================================
     * 1. METODE Shorten Summary
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi shorten summary di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function shortenSummary(string $summary, int $maxChars = 320, int $maxSentences = 3): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', trim($summary), -1, PREG_SPLIT_NO_EMPTY);
        if (empty($sentences)) {
            return trim($summary);
        }

        $selected = [];
        foreach ($sentences as $sentence) {
            $selected[] = $sentence;
            if (count($selected) >= $maxSentences) {
                break;
            }
            if (mb_strlen(implode(' ', $selected)) >= $maxChars) {
                break;
            }
        }

        $summary = trim(implode(' ', $selected));

        // Hapus long numbered daftar details ke simpan the summary concise.
        $summary = preg_replace('/\s+(?:\d+\)|\d+\.)[\s\S]*$/u', '', $summary);
        $summary = preg_replace('/\s*Solusi:\s*[\s\S]*$/iu', '', $summary);
        $summary = rtrim($summary, ' ,;:');

        if (mb_strlen($summary) > $maxChars) {
            $summary = mb_substr($summary, 0, $maxChars);
            $summary = rtrim($summary);
            $summary = preg_replace('/[^\s]+$/u', '', $summary);
            $summary = rtrim($summary, ',.;:');
            $summary .= '...';
        }

        return trim($summary);
    }
    
    /**
     * =========================================================================
     * 17. Metode Mendapatkan Debug Info
     * =========================================================================
     * 
     * Metode ini mengembalikan informasi debugging untuk proses retrieval.
     * 
     * Kembalikan:
     * array
     */
    public function getDebugInfo(): array
    {
        return $this->debugInfo;
    }
}
