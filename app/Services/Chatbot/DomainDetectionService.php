<?php

namespace App\Services\Chatbot;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * DomainDetectionService - Lightweight domain/category detection before TF-IDF retrieval
 * 
 * Uses CURATED STATIC LISTS only - NO arbitrary article tokens, NO author names,
 * NO user data. All suggestions come from verified domain dictionaries.
 * 
 * Pipeline:
 * 1. Synonym normalization (BEFORE tokenization)
 * 2. Typo correction
 * 3. Tokenize and preprocess
 * 4. Match tokens against curated domain keyword dictionary
 * 5. Return detected domain(s) and relevant category IDs for filtering
 * 6. Filter candidate articles by category before TF-IDF
 * 
 * OUT-OF-DOMAIN DETECTION:
 * - Checks if query contains IT/support domain keywords
 * - Calculates vocabulary overlap with IT domain vocabulary
 * - Returns rejection for non-IT queries (e.g., "kucing", "rendang", "mobil balap")
 */
class DomainDetectionService
{
    // Cache configuration
    private const DOMAIN_CACHE_KEY = 'chatbot:domain:mapping';
    private const DOMAIN_CACHE_TTL = 3600; // 1 hour

    // ============================================================
    // CURATED STATIC DOMAIN LIST (NO arbitrary tokens)
    // ============================================================
    // These are the ONLY valid domain suggestions - verified and clean
    public array $curatedDomains = [
        'wifi',
        'internet',
        'jaringan',
        'printer',
        'komputer',
        'email',
        'website',
        'aplikasi',
        'akun',
        'security',
        'bsod',
        'windows',
        'server',
        'driver',
        'hardware',
    ];

    // ============================================================
    // CURATED SUBTOPICS (NO arbitrary tokens)
    // ============================================================
    // Pre-defined subtopics for each domain - verified and clean
    public array $curatedSubtopics = [
        'wifi' => [
            'wifi lemot',
            'wifi tidak connect',
            'wifi sering putus',
            'wifi tidak terdeteksi',
            'wifi tidak bisa connect',
        ],
        'internet' => [
            'internet lemot',
            'internet putus-putus',
            'internet tidak stabil',
            'koneksi internet lambat',
            'internet tidak bisa diakses',
        ],
        'printer' => [
            'printer error',
            'printer tidak respon',
            'printer tidak mau print',
            'printer macet',
            'printer tidak terdeteksi',
        ],
        'komputer' => [
            'komputer lemot',
            'komputer sering hang',
            'komputer blue screen',
            'komputer tidak mau nyala',
            'komputer lambat',
        ],
        'email' => [
            'email tidak masuk',
            'email tidak bisa dikirim',
            'email error',
            'lupa password email',
            'email spam',
        ],
        'website' => [
            'website tidak bisa diakses',
            'website lemot',
            'website error',
            'login website gagal',
            'website tidak responsif',
        ],
        'aplikasi' => [
            'aplikasi error',
            'aplikasi tidak bisa dibuka',
            'aplikasi lemot',
            'aplikasi crash',
            'aplikasi tidak responsif',
        ],
        'akun' => [
            'lupa password',
            'akun terkunci',
            'tidak bisa login',
            'akun diretas',
            'registrasi gagal',
        ],
    ];

    // ============================================================
    // CURATED CLARIFICATION DOMAINS (for ambiguous queries)
    // ============================================================
    // Only high-confidence domains shown in clarification
    public array $clarificationDomains = [
        'wifi',
        'internet',
        'komputer',
        'printer',
        'email',
        'aplikasi',
    ];

    // ============================================================
    // DOMAIN KEYWORD MAPPINGS (for detection)
    // ============================================================
    // Each domain maps to a set of keywords that indicate that domain
    // IMPORTANT: 'categories' must match ACTUAL category names in database (case-insensitive)
    private array $domainKeywords = [
        'wifi' => [
            'keywords' => ['wifi', 'wi-fi', 'wireless', 'wlan', 'hotspot', 'access point', 'ap', 'router wifi'],
            'categories' => ['wifi'], // Exact database category name
        ],
        'internet' => [
            'keywords' => ['internet', 'inet', 'koneksi internet', 'sinyal internet', 'bandwidth', 'quota', 'paket data'],
            'categories' => ['internet'], // Exact database category name
        ],
        'jaringan' => [
            'keywords' => ['jaringan', 'network', 'lan', 'wan', 'ethernet', 'kabel jaringan', 'switch', 'hub'],
            'categories' => ['wifi', 'internet'], // Maps to existing categories
        ],
        'printer' => [
            'keywords' => ['printer', 'printing', 'cetak', 'mencetak', 'epson', 'canon', 'hp printer', 'ink', 'tinta', 'cartridge', 'toner'],
            'categories' => ['hardware'], // Exact database category name
        ],
        'komputer' => [
            'keywords' => ['komputer', 'computer', 'pc', 'laptop', 'notebook', 'desktop'],
            'categories' => ['hardware'], // Exact database category name
        ],
        'email' => [
            'keywords' => ['email', 'e-mail', 'surel', 'mail', 'gmail', 'outlook', 'yahoo mail'],
            'categories' => ['email'], // Exact database category name
        ],
        'website' => [
            'keywords' => ['website', 'web', 'situs', 'portal', 'halaman web', 'browser', 'chrome', 'firefox'],
            'categories' => ['internet'], // Maps to existing category
        ],
        'aplikasi' => [
            'keywords' => ['aplikasi', 'application', 'software', 'perangkat lunak', 'program', 'app'],
            'categories' => ['aplikasi'], // Exact database category name
        ],
        'akun' => [
            'keywords' => ['akun', 'account', 'login', 'masuk', 'daftar', 'register', 'password', 'kata sandi', 'username'],
            'categories' => ['email', 'aplikasi'], // Maps to existing categories
        ],
        'security' => [
            'keywords' => ['ransomware', 'malware', 'virus', 'trojan', 'spyware', 'adware', 'worm', 'rootkit', 'keylogger', 'phishing', 'backdoor', 'exploit', 'antivirus', 'windows defender'],
            'categories' => ['security'], // Security category
        ],
        'bsod' => [
            'keywords' => ['bsod', 'blue screen', 'crash', 'system crash', 'stop error', 'screen of death'],
            'categories' => ['hardware'], // Maps to hardware category
        ],
    ];

    // Generic/problem terms that should NOT be used for domain detection
    private array $genericTerms = [
        'lemot', 'lambat', 'slow', 'error', 'masalah', 'tidak', 'bisa',
        'mau', 'sudah', 'belum', 'ingin', 'harus', 'perlu', 'cara', 'bagaimana',
        'apa', 'kenapa', 'mengapa', 'sangat', 'banget', 'sekali', 'benar', 'sungguh',
        'bermasalah', 'rusak', 'mati', 'hilang', 'tidak bisa', 'gagal',
    ];

    // ============================================================
    // OUT-OF-DOMAIN DETECTION CONFIGURATION
    // ============================================================
    // Comprehensive IT domain vocabulary for detecting non-IT queries
    // These are ALL valid IT/support related terms that users might query
    private array $itDomainVocabulary = [
        // Core IT domains
        'wifi', 'internet', 'jaringan', 'network', 'lan', 'wan', 'ethernet',
        'printer', 'printing', 'cetak', 'scanner',
        'komputer', 'computer', 'pc', 'laptop', 'notebook', 'desktop',
        'email', 'e-mail', 'mail', 'gmail', 'outlook',
        'website', 'web', 'browser', 'chrome', 'firefox', 'situs',
        'aplikasi', 'application', 'software', 'program', 'app',
        'akun', 'account', 'login', 'password', 'username',
        
        // Operating Systems & Platforms
        'windows', 'linux', 'macos', 'android', 'ios',
        'ubuntu', 'debian', 'centos', 'fedora',
        'xp', 'vista', 'win7', 'win8', 'win10', 'win11',
        'server', 'vps', 'cloud',
        
        // Security terms (CRITICAL - these MUST be recognized as IT)
        'virus', 'malware', 'ransomware', 'trojan', 'spyware', 'adware',
        'worm', 'rootkit', 'keylogger', 'phishing', 'antivirus',
        'security', 'hack', 'hacker', 'firewall',
        
        // Hardware terms
        'hardware', 'ram', 'cpu', 'gpu', 'ssd', 'hdd', 'motherboard',
        'monitor', 'keyboard', 'mouse', 'speaker', 'microphone',
        'router', 'modem', 'switch', 'hub', 'access point',
        'driver', 'firmware', 'bios',
        
        // Common IT issues
        'lemot', 'lambat', 'error', 'crash', 'hang', 'freeze',
        'bsod', 'blue screen', 'restart', 'shutdown',
        'connect', 'koneksi', 'sinyal', 'bandwidth',
        'install', 'uninstall', 'update', 'upgrade', 'download',
        'backup', 'restore', 'format', 'reset',
        'masuk', 'diakses', 'dibuka', 'dijalankan',
        
        // DevOps/Development (CRITICAL - these MUST be recognized as IT)
        'docker', 'kubernetes', 'k8s', 'container',
        'git', 'github', 'gitlab',
        'hosting', 'domain', 'ssl', 'https',
        'api', 'database', 'mysql', 'postgresql', 'mongodb', 'sql',
        
        // Common IT actions
        'troubleshoot', 'troubleshooting', 'fix', 'repair', 'solve',
        'configure', 'setting', 'setup', 'install',
        
        // Additional IT terms
        'microsoft', 'google', 'apple', 'adobe',
        'office', 'excel', 'word', 'powerpoint',
        'pdf', 'zip', 'rar', 'compress',
        'bluetooth', 'usb', 'hdmi', 'vga',
    ];
    
    // ============================================================
    // IMPORTANT TECHNICAL TOKENS (NEVER REJECT)
    // ============================================================
    // If query contains ANY of these tokens, it should NEVER be
    // classified as out-of-domain, regardless of other factors.
    // These are definitive IT/technical terms.
    private array $neverRejectTokens = [
        // Security tokens
        'virus', 'malware', 'ransomware', 'trojan', 'spyware', 'phishing', 'antivirus',
        
        // DevOps/Infrastructure tokens
        'docker', 'kubernetes', 'k8s', 'container',
        
        // Network tokens
        'wifi', 'jaringan', 'network', 'vpn', 'router', 'modem',
        
        // Hardware peripheral tokens
        'printer', 'scanner',
        
        // Data tokens
        'database', 'mysql', 'postgresql', 'mongodb', 'sql',
        
        // Communication tokens
        'email', 'gmail', 'outlook',
        
        // Web tokens
        'website', 'browser', 'chrome', 'firefox',
        
        // Account tokens
        'akun', 'login', 'password',
    ];

    // Non-IT terms that are commonly queried but are OUT-OF-DOMAIN
    // These should trigger immediate rejection
    private array $outOfDomainKeywords = [
        // Food & Cooking
        'kucing', 'anjing', 'ikan', 'burung', 'ular', 'tikus',
        'rendang', 'nasi', 'gado', 'sate', 'bakso', 'mie',
        'masak', 'memasak', 'dapur', 'resep', 'makanan', 'minuman',
        
        // Vehicles
        'mobil', 'motor', 'sepeda', 'truk', 'bus', 'kereta',
        'balap', 'rally', 'otomotif', 'bengkel', 'sparepart',
        'parkir', 'tilang', 'sim', 'stnk',
        
        // Entertainment
        'film', 'musik', 'lagu', 'game', 'gaming',
        'netflix', 'youtube', 'tiktok', 'instagram', 'facebook',
        'bola', 'sepakbola', 'basket', 'badminton', 'renang',
        
        // Shopping & Finance
        'belanja', 'beli', 'jual', 'harga', 'diskon', 'promo',
        'bank', 'tabungan', 'kredit', 'pinjaman', 'asuransi',
        'shopee', 'tokopedia', 'lazada', 'bukalapak',
        
        // Travel & Places
        'hotel', 'tiket', 'pesawat', 'liburan', 'wisata',
        'bandara', 'stasiun', 'terminal', 'pelabuhan',
        
        // Health & Medical
        'sakit', 'dokter', 'rumah sakit', 'obat', 'klinik',
        'covid', 'corona', 'vaksin', 'flu', 'demam',
        
        // Education (non-IT)
        'sekolah', 'kuliah', 'ujian', 'nilai', 'ijazah',
        'matematika', 'fisika', 'kimia', 'biologi', 'sejarah',
        
        // General non-IT
        'cuaca', 'hujan', 'panas', 'gempi', 'banjir',
        'politik', 'pemerintah', 'presiden', 'menteri',
        'agama', 'ibadah', 'puasa', 'lebaran', 'natal',
    ];

    // Minimum vocabulary overlap threshold for IT domain
    // If less than this ratio of query tokens match IT vocabulary, reject
    private const MIN_VOCABULARY_OVERLAP = 0.20;
    
    // Minimum number of IT tokens required
    private const MIN_IT_TOKENS = 1;
    
    // Confidence threshold for domain detection (lowered to accept more valid IT queries)
    private const DOMAIN_CONFIDENCE_THRESHOLD = 0.05;
    
    // Rejection message for out-of-domain queries
    public const OUT_OF_DOMAIN_MESSAGE = 'Maaf, saya hanya dapat membantu masalah terkait IT.';

    // ============================================================
    // SYNONYM NORMALIZATION (BEFORE tokenization/stemming)
    // ============================================================
    // These mappings ensure "komputer lambat" behaves like "komputer lemot"
    private array $synonymMappings = [
        // Speed-related synonyms
        'lambat' => 'lemot',
        'pelan' => 'lemot',
        'lamban' => 'lemot',
        
        // Connection synonyms
        'koneksi' => 'internet',
        'sambungan' => 'internet',
        'terhubung' => 'connect',
        
        // Error synonyms
        'eror' => 'error',
        'erorr' => 'error',
        'galat' => 'error',
        'masalah' => 'error',
        
        // Device synonyms
        'komputer' => 'komputer',
        'kompter' => 'komputer',
        'komputerr' => 'komputer',
        
        // Printer synonyms
        'pritner' => 'printer',
        'printter' => 'printer',
        'prnter' => 'printer',
        
        // WiFi synonyms
        'wfi' => 'wifi',
        'wiif' => 'wifi',
        'wifii' => 'wifi',
        'wi-fi' => 'wifi',
        
        // Internet synonyms
        'intenet' => 'internet',
        'intrnet' => 'internet',
        'inet' => 'internet',
        
        // Network synonyms
        'jaringn' => 'jaringan',
        'jaring' => 'jaringan',
        
        // Email synonyms
        'emai' => 'email',
        'emal' => 'email',
        'e-mail' => 'email',
        
        // Security synonyms (typo tolerance for security terms)
        'ransomwre' => 'ransomware',
        'ransomwaree' => 'ransomware',
        'ransomware' => 'ransomware',
        'viruss' => 'virus',
        'viruse' => 'virus',
        'viru' => 'virus',
        'malwere' => 'malware',
        'malwre' => 'malware',
        'trojan' => 'trojan',
        'trojanhorse' => 'trojan',
        'spyware' => 'spyware',
        'phising' => 'phishing',
        'phising' => 'phishing',
        
        // Website synonyms
        'webiste' => 'website',
        'websit' => 'website',
        
        // Action synonyms
        'tidak connect' => 'tidak terhubung',
        'tidak konek' => 'tidak terhubung',
        'gak bisa' => 'tidak bisa',
        'ga bisa' => 'tidak bisa',
    ];

    private PreprocessingService $preprocessor;

    public function __construct(PreprocessingService $preprocessor)
    {
        $this->preprocessor = $preprocessor;
    }

    /**
     * Detect domain(s) from query
     * Returns detected domain info and relevant category IDs for filtering
     * 
     * @param string $query Raw user query
     * @return array ['detected' => bool, 'domain' => string|null, 'category_ids' => array, 'confidence' => float]
     */
    public function detectDomain(string $query): array
    {
        if (empty(trim($query))) {
            return ['detected' => false, 'domain' => null, 'category_ids' => [], 'confidence' => 0.0];
        }

        // Step 1: Normalize query with typo correction
        $normalizedQuery = $this->preprocessor->normalizeTypos($query);
        
        // Step 2: Apply synonym mapping for additional typo tolerance
        $normalizedQuery = $this->applySynonymMapping($normalizedQuery);

        // Step 3: Tokenize (without stemming to preserve domain keywords)
        $tokens = $this->tokenizeQuery($normalizedQuery);

        // Step 4: Score each domain based on keyword matches
        $domainScores = $this->scoreDomains($tokens);

        // Step 5: Determine if any domain is detected with sufficient confidence
        $threshold = 0.3; // Minimum confidence threshold
        $detectedDomains = array_filter($domainScores, fn($score) => $score >= $threshold);

        if (empty($detectedDomains)) {
            return ['detected' => false, 'domain' => null, 'category_ids' => [], 'confidence' => 0.0];
        }

        // Get the highest scoring domain
        arsort($detectedDomains);
        $primaryDomain = array_key_first($detectedDomains);
        $confidence = $detectedDomains[$primaryDomain];

        // Get category IDs for the detected domain
        $categoryIds = $this->getCategoryIdsForDomain($primaryDomain);

        return [
            'detected' => true,
            'domain' => $primaryDomain,
            'category_ids' => $categoryIds,
            'confidence' => $confidence,
            'all_scores' => $domainScores,
        ];
    }

    /**
     * OUT-OF-DOMAIN DETECTION
     * Check if a query is outside the IT/support domain
     * 
     * Returns true if the query is OUT-OF-DOMAIN (non-IT)
     * Returns false if the query is IN-DOMAIN (IT-related)
     * 
     * @param string $query Raw user query
     * @return array ['is_out_of_domain' => bool, 'reason' => string, 'it_token_count' => int, 'vocabulary_overlap' => float]
     */
    public function detectOutOfDomain(string $query): array
    {
        if (empty(trim($query))) {
            return [
                'is_out_of_domain' => true,
                'reason' => 'empty_query',
                'it_token_count' => 0,
                'vocabulary_overlap' => 0.0,
            ];
        }

        // Step 1: Normalize query
        $normalizedQuery = $this->preprocessor->normalizeTypos($query);
        $normalizedQuery = $this->applySynonymMapping($normalizedQuery);

        // Step 2: Tokenize
        $tokens = $this->tokenizeQuery($normalizedQuery);

        if (empty($tokens)) {
            return [
                'is_out_of_domain' => true,
                'reason' => 'no_tokens',
                'it_token_count' => 0,
                'vocabulary_overlap' => 0.0,
            ];
        }

        // Step 3: Check for explicit OUT-OF-DOMAIN keywords (immediate rejection)
        $hasExplicitOutOfDomain = $this->hasExplicitOutOfDomainKeywords($tokens);
        if ($hasExplicitOutOfDomain) {
            return [
                'is_out_of_domain' => true,
                'reason' => 'explicit_out_of_domain_keywords',
                'it_token_count' => 0,
                'vocabulary_overlap' => 0.0,
            ];
        }

        // Step 4: Count IT domain tokens
        $itTokenCount = $this->countITDomainTokens($tokens);

        // Step 5: Calculate vocabulary overlap
        $vocabularyOverlap = $this->calculateVocabularyOverlap($tokens);

        // Step 6: Check domain detection confidence
        $domainInfo = $this->detectDomain($query);
        $domainConfidence = $domainInfo['confidence'] ?? 0.0;

        // Step 7: Determine if OUT-OF-DOMAIN based on multiple criteria
        $isOutOfDomain = $this->evaluateOutOfDomain(
            $tokens,
            $itTokenCount,
            $vocabularyOverlap,
            $domainConfidence
        );

        $reason = $isOutOfDomain ? $this->getOutOfDomainReason($itTokenCount, $vocabularyOverlap, $domainConfidence) : 'in_domain';

        return [
            'is_out_of_domain' => $isOutOfDomain,
            'reason' => $reason,
            'it_token_count' => $itTokenCount,
            'vocabulary_overlap' => round($vocabularyOverlap, 4),
            'domain_confidence' => round($domainConfidence, 4),
        ];
    }

    /**
     * Check if query contains explicit OUT-OF-DOMAIN keywords
     */
    private function hasExplicitOutOfDomainKeywords(array $tokens): bool
    {
        foreach ($tokens as $token) {
            $lowerToken = mb_strtolower($token);
            if (in_array($lowerToken, $this->outOfDomainKeywords)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Count how many tokens are IT domain related
     */
    private function countITDomainTokens(array $tokens): int
    {
        $count = 0;
        foreach ($tokens as $token) {
            $lowerToken = mb_strtolower($token);
            // Skip generic terms
            if (in_array($lowerToken, $this->genericTerms)) {
                continue;
            }
            // Check if it's an IT domain token
            if (in_array($lowerToken, $this->itDomainVocabulary)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Calculate vocabulary overlap ratio
     * Returns the ratio of IT domain tokens to total meaningful tokens
     */
    private function calculateVocabularyOverlap(array $tokens): float
    {
        $meaningfulTokens = 0;
        $itMatches = 0;

        foreach ($tokens as $token) {
            $lowerToken = mb_strtolower($token);
            
            // Skip generic terms
            if (in_array($lowerToken, $this->genericTerms)) {
                continue;
            }

            $meaningfulTokens++;

            // Check for exact IT vocabulary match
            if (in_array($lowerToken, $this->itDomainVocabulary)) {
                $itMatches++;
            } else {
                // Check for partial match (e.g., "printing" matches "printer")
                foreach ($this->itDomainVocabulary as $itTerm) {
                    if (str_contains($itTerm, $lowerToken) && mb_strlen($lowerToken) > 2) {
                        $itMatches += 0.5;
                        break;
                    }
                    if (str_contains($lowerToken, $itTerm) && mb_strlen($itTerm) > 2) {
                        $itMatches += 0.3;
                        break;
                    }
                }
            }
        }

        if ($meaningfulTokens === 0) {
            return 0.0;
        }

        return $itMatches / $meaningfulTokens;
    }

    /**
     * Evaluate if query is OUT-OF-DOMAIN based on multiple criteria
     * 
     * Logic:
     * 1. If query contains ANY "never reject" token -> ALWAYS IN-DOMAIN
     * 2. If no IT tokens found -> OUT-OF-DOMAIN
     * 3. If vocabulary overlap is very low AND no domain confidence -> OUT-OF-DOMAIN
     * 4. If has IT tokens AND good vocabulary overlap -> IN-DOMAIN (even with low domain confidence)
     */
    private function evaluateOutOfDomain(array $tokens, int $itTokenCount, float $vocabularyOverlap, float $domainConfidence): bool
    {
        // CRITICAL: If query contains ANY "never reject" token, ALWAYS accept as IN-DOMAIN
        // This ensures queries like "virus", "docker", "wifi" are NEVER rejected
        // Also handles typos like "virussss" which contains "virus"
        if ($this->containsNeverRejectToken($tokens)) {
            return false;
        }

        // If no IT tokens found, definitely OUT-OF-DOMAIN
        if ($itTokenCount < self::MIN_IT_TOKENS) {
            return true;
        }

        // If has IT tokens and good vocabulary overlap, accept as IN-DOMAIN
        // This handles cases like "email tidak masuk" where domain detection may be weak
        if ($itTokenCount >= 1 && $vocabularyOverlap >= self::MIN_VOCABULARY_OVERLAP) {
            return false;
        }

        // If vocabulary overlap is very low AND no domain confidence, OUT-OF-DOMAIN
        if ($vocabularyOverlap < self::MIN_VOCABULARY_OVERLAP && $domainConfidence < self::DOMAIN_CONFIDENCE_THRESHOLD) {
            return true;
        }

        // If domain confidence is decent, accept
        if ($domainConfidence >= self::DOMAIN_CONFIDENCE_THRESHOLD) {
            return false;
        }

        // Default: if we have IT tokens but weak signals, still accept
        // Better to accept a borderline IT query than reject a valid one
        return $itTokenCount < 1;
    }
    
    /**
     * Check if query tokens contain any "never reject" token
     * If ANY token matches, the query should NEVER be rejected as out-of-domain
     */
    private function containsNeverRejectToken(array $tokens): bool
    {
        foreach ($tokens as $token) {
            $lowerToken = mb_strtolower($token);
            
            // Check for exact match with never-reject tokens
            if (in_array($lowerToken, $this->neverRejectTokens)) {
                return true;
            }
            
            // Also check for partial matches (e.g., "virussss" should match "virus")
            foreach ($this->neverRejectTokens as $criticalToken) {
                // Check if token contains the critical term (handles typos like "virussss")
                if (mb_strlen($lowerToken) > mb_strlen($criticalToken) && 
                    str_contains($lowerToken, $criticalToken)) {
                    return true;
                }
                // Check if critical term contains the token (handles truncated terms)
                if (mb_strlen($criticalToken) > mb_strlen($lowerToken) && 
                    mb_strlen($lowerToken) > 3 &&
                    str_contains($criticalToken, $lowerToken)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get the reason for OUT-OF-DOMAIN classification
     */
    private function getOutOfDomainReason(int $itTokenCount, float $vocabularyOverlap, float $domainConfidence): string
    {
        if ($itTokenCount < self::MIN_IT_TOKENS) {
            return 'no_it_keywords';
        }
        if ($vocabularyOverlap < self::MIN_VOCABULARY_OVERLAP) {
            return 'low_vocabulary_overlap';
        }
        if ($domainConfidence < self::DOMAIN_CONFIDENCE_THRESHOLD) {
            return 'low_domain_confidence';
        }
        return 'in_domain';
    }

    /**
     * Apply synonym mapping for typo-tolerant domain detection
     * This normalizes synonyms BEFORE tokenization/stemming
     */
    private function applySynonymMapping(string $query): string
    {
        $result = $query;
        foreach ($this->synonymMappings as $typo => $correct) {
            if (str_contains($result, $typo)) {
                $result = str_replace($typo, $correct, $result);
            }
        }
        return $result;
    }

    /**
     * Tokenize query into individual terms
     */
    private function tokenizeQuery(string $query): array
    {
        // Simple tokenization: split by whitespace and punctuation
        $query = mb_strtolower($query);
        $tokens = preg_split('/[\s,;.!?()""\'\-]+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($tokens, fn($t) => mb_strlen($t) > 1);
    }

    /**
     * Score each domain based on keyword matches in query
     */
    private function scoreDomains(array $tokens): array
    {
        $scores = [];
        $totalDomainTokens = 0;

        foreach ($this->domainKeywords as $domain => $config) {
            $score = 0.0;
            $keywords = $config['keywords'];
            
            foreach ($tokens as $token) {
                // Skip generic terms
                if (in_array($token, $this->genericTerms)) {
                    continue;
                }

                // Check for exact keyword match
                foreach ($keywords as $keyword) {
                    if ($token === $keyword) {
                        $score += 1.0;
                    } elseif (str_contains($keyword, $token) && mb_strlen($token) > 2) {
                        // Partial match (e.g., "wifi" matches "wireless")
                        $score += 0.5;
                    }
                }
            }

            // Normalize score by number of keywords
            if (!empty($keywords)) {
                $score = min(1.0, $score / (count($keywords) * 0.5));
            }

            $scores[$domain] = $score;
            if ($score > 0) {
                $totalDomainTokens++;
            }
        }

        // Normalize scores if multiple domains detected
        if ($totalDomainTokens > 1) {
            $maxScore = max($scores);
            if ($maxScore > 0) {
                foreach ($scores as $domain => $score) {
                    $scores[$domain] = $score / $maxScore;
                }
            }
        }

        return $scores;
    }

    /**
     * Get category IDs for a detected domain
     */
    private function getCategoryIdsForDomain(string $domain): array
    {
        $config = $this->domainKeywords[$domain] ?? null;
        if (!$config) {
            return [];
        }

        $categoryNames = $config['categories'];
        
        // Query categories by name
        $categories = Category::whereIn('name', $categoryNames)->pluck('id')->toArray();

        // Also check for category name variations (case-insensitive, trimmed)
        if (empty($categories)) {
            $categories = Category::where(function ($query) use ($categoryNames) {
                foreach ($categoryNames as $name) {
                    $query->orWhereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))]);
                }
            })->pluck('id')->toArray();
        }

        return array_unique($categories);
    }

    /**
     * Get all domain names for debugging/reference
     */
    public function getAllDomains(): array
    {
        return array_keys($this->domainKeywords);
    }

    /**
     * Get domain keywords for a specific domain
     */
    public function getDomainKeywords(string $domain): array
    {
        return $this->domainKeywords[$domain]['keywords'] ?? [];
    }

    /**
     * Clear domain detection cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::DOMAIN_CACHE_KEY);
    }

    /**
     * Get clean domain suggestions (only verified domains, no user data)
     * This fixes the "jamal" suggestion pollution issue
     */
    public function getCleanDomainSuggestions(): array
    {
        $cached = Cache::get(self::DOMAIN_CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        $suggestions = [];

        // Only use verified domain keywords - NO user data, NO author names
        foreach ($this->domainKeywords as $domain => $config) {
            // Use the first (most representative) keyword as the suggestion
            $suggestions[] = [
                'id' => $domain,
                'type' => 'domain',
                'label' => ucfirst($domain),
                'keywords' => $config['keywords'],
            ];
        }

        // Also add actual categories from database (verified sources only)
        $categories = Category::whereHas('articles', function ($query) {
            $query->where('is_published', true)
                  ->where('publish_status', 'approved');
        })
        ->orderBy('name')
        ->get(['id', 'name']);

        foreach ($categories as $category) {
            // Avoid duplicates with domain suggestions
            $isDuplicate = false;
            foreach ($suggestions as $suggestion) {
                if (stripos($suggestion['label'], $category->name) !== false ||
                    stripos($category->name, $suggestion['label']) !== false) {
                    $isDuplicate = true;
                    break;
                }
            }

            if (!$isDuplicate) {
                $suggestions[] = [
                    'id' => $category->id,
                    'type' => 'category',
                    'label' => $category->name,
                ];
            }
        }

        Cache::put(self::DOMAIN_CACHE_KEY, $suggestions, self::DOMAIN_CACHE_TTL);

        return $suggestions;
    }

    /**
     * Get IT domain vocabulary for testing/debugging
     */
    public function getITDomainVocabulary(): array
    {
        return $this->itDomainVocabulary;
    }

    /**
     * Get OUT-OF-DOMAIN keywords for testing/debugging
     */
    public function getOutOfDomainKeywords(): array
    {
        return $this->outOfDomainKeywords;
    }
}