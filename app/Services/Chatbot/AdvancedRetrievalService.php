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
 * AdvancedRetrievalService - Enhanced TF-IDF retrieval with hybrid reranking
 * 
 * Implements multi-factor ranking with:
 * - Title overlap scoring (with bigram matching for exact phrases)
 * - Exact phrase boost (full query phrase in title)
 * - Query coverage scoring (all important tokens match)
 * - Domain match boost (category alignment)
 * - Negative domain penalty (unrelated domain suppression)
 * - Low priority token filtering (generic term weight reduction)
 */
class AdvancedRetrievalService
{
    // ============================================================
    // CONFIDENCE THRESHOLDS
    // ============================================================
    // These thresholds control when results are considered reliable
    private const SIMILARITY_THRESHOLD = 0.12;        // Minimum score to include a result
    private const HIGH_SIMILARITY_THRESHOLD = 0.35;   // Score for high confidence
    private const VERY_HIGH_SIMILARITY_THRESHOLD = 0.55; // Score for very high confidence
    private const SAFE_FALLBACK_THRESHOLD = 0.18;     // Below this, use safe fallback instead of weak results
    
    private const TOP_K_RESULTS = 5;
    private const FAILURE_THRESHOLD = 3;
    private const MAX_FAILURE_MEMORY = 10; // Maximum queries to track for failure
    private const SESSION_FAILURE_KEY = 'chatbot_failure_memory';
    private const SESSION_CONVERSATION_KEY = 'chatbot_conversation_memory';
    private const CACHE_TTL = 86400;
    
    // ============================================================
    // HYBRID RANKING WEIGHTS
    // ============================================================
    // These weights control the contribution of each ranking factor
    private const WEIGHT_COSINE = 0.30;        // Base TF-IDF cosine similarity
    private const WEIGHT_TITLE_OVERLAP = 0.25; // Title keyword overlap (increased)
    private const WEIGHT_DOMAIN_MATCH = 0.15;  // Domain/category alignment
    private const WEIGHT_QUERY_COVERAGE = 0.15; // Query term coverage (increased)
    private const WEIGHT_EXACT_PHRASE = 0.10;  // Exact phrase match
    private const WEIGHT_DIVERSIFICATION = 0.05; // Result diversity
    
    // ============================================================
    // BONUS/PENALTY FACTORS
    // ============================================================
    private const TITLE_BOOST_FACTOR = 2.0;
    private const EXACT_PHRASE_BONUS = 0.3;     // Bonus for exact phrase in title
    private const FULL_COVERAGE_BONUS = 0.25;   // Bonus for matching all important terms
    private const BIGRAM_MATCH_BONUS = 0.2;     // Bonus for bigram (2-gram) matches
    private const DOMAIN_PENALTY = -0.5;        // Penalty for wrong domain
    private const STRONG_DOMAIN_PENALTY = -0.8; // Strong penalty for forbidden domains
    private const LOW_PRIORITY_WEIGHT = 0.1;    // Weight multiplier for generic terms
    // ============================================================
    // LOW PRIORITY / GENERIC TERMS
    // ============================================================
    // These terms should have REDUCED influence in ranking
    // They are too common in helpdesk articles and overpower domain-specific terms
    //
    // Category 1: Generic instructional words (cara, mengatasi, etc.)
    // Category 2: Generic technical/device words (pc, laptop, komputer, etc.)
    //              These are too common and don't indicate specific intent
    private array $lowPriorityTerms = [
        // Generic instructional words
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
        
        // Generic technical words - too common, don't indicate specific intent
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
    // IMPORTANT DOMAIN TOKENS (TRUE INTENT TOKENS)
    // ============================================================
    // These tokens represent REAL user intent and should DOMINATE ranking
    // When these appear in a query, articles matching these tokens should rank highest
    // 
    // NOTE: Generic device words (pc, laptop, komputer, desktop, notebook, error)
    // are intentionally EXCLUDED because they are too generic and cause
    // hardware articles to overpower security/software intent.
    private array $importantDomainTokens = [
        // Security tokens (HIGHEST PRIORITY)
        'virus',
        'malware',
        'ransomware',
        'trojan',
        'spyware',
        'phishing',
        'antivirus',
        
        // DevOps/Infrastructure tokens
        'docker',
        'kubernetes',
        'k8s',
        'container',
        
        // Network tokens
        'wifi',
        'jaringan',
        'network',
        'lan',
        'wan',
        'vpn',
        'router',
        'modem',
        
        // Hardware peripheral tokens
        'printer',
        'scanner',
        
        // Data tokens
        'database',
        'mysql',
        'postgresql',
        'mongodb',
        'sql',
        
        // Communication tokens
        'email',
        'gmail',
        'outlook',
        
        // Web tokens
        'website',
        'browser',
        'chrome',
        'firefox',
        
        // Account tokens
        'akun',
        'login',
        'password',
        
        // Specific issue tokens
        'lemot',
        'bsod',
        'hang',
        'crash',
        'freeze',
    ];
    
    // ============================================================
    // SECURITY PRIORITY TOKENS
    // ============================================================
    // When query contains ANY of these tokens, security articles
    // should get a STRONG boost to override generic hardware articles
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
    // NEGATIVE DOMAIN PENALTY MAPPINGS
    // ============================================================
    // When querying domain X, penalize articles from these unrelated domains
    // This prevents cross-domain contamination (e.g., printer query returning BSOD articles)
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
    
    // Diversification category quotas - ensure result diversity
    private const MAX_RESULTS_PER_CATEGORY = 2; // Maximum 2 articles from same category
    private const DIVERSIFICATION_CATEGORIES = ['troubleshooting', 'optimization', 'tutorial', 'hardware'];
    
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
    
    public function retrieve(string $query, int $limit = self::TOP_K_RESULTS): array
    {
        $this->debugInfo = [
            'original_query' => $query,
            'stages' => [],
            'scores' => [],
        ];
        
        // ============================================================
        // OUT-OF-DOMAIN DETECTION (BEFORE any retrieval)
        // ============================================================
        // Check if query is outside IT/support domain
        // If so, return early with rejection message - DO NOT fallback to IT articles
        $outOfDomainCheck = $this->domainDetector->detectOutOfDomain($query);
        $this->debugInfo['out_of_domain_check'] = $outOfDomainCheck;
        
        if ($outOfDomainCheck['is_out_of_domain']) {
            $this->debugInfo['stages'][] = [
                'stage' => 'out_of_domain_detection',
                'input' => $query,
                'output' => 'REJECTED - ' . $outOfDomainCheck['reason'],
            ];
            
            return $this->outOfDomainResult($query);
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
    
    private function multiIntentRetrieval(array $intents, int $limit): array
    {
        $intentResults = [];
        $originalQuery = implode(' dan ', $intents);
        $allSeenIds = [];
        
        // Save the current debug info to restore after single intent retrievals
        $savedDebugInfo = $this->debugInfo;
        
        // Step 1: Retrieve results for EACH intent separately with a larger candidate pool
        // We retrieve more candidates than needed to ensure we have enough for balanced merging
        $candidatesPerIntent = max(10, $limit * 2);
        
        foreach ($intents as $index => $intent) {
            // Create a fresh debug info for each intent retrieval
            $this->debugInfo = [
                'original_query' => $intent,
                'stages' => [],
                'scores' => [],
            ];
            
            $normalizedIntent = $this->normalizeTypos($intent);
            $normalizedIntent = $this->normalizeSynonyms($normalizedIntent);
            
            // Retrieve more candidates than the fair share to have options for merging
            $result = $this->singleIntentRetrieval($normalizedIntent, $candidatesPerIntent);
            
            // Tag each result with its source intent for tracking
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
        
        // Restore the main debug info
        $this->debugInfo = $savedDebugInfo;
        $this->debugInfo['intents'] = $intents;
        
        // Step 2: Balanced merging - interleave results from each intent
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
     * Balanced merge of results from multiple intents
     * 
     * This method ensures that each intent gets fair representation in the final results.
     * It uses a round-robin approach, picking the best available result from each intent
     * in turn, while avoiding duplicates.
     * 
     * Algorithm:
     * 1. Calculate fair quota per intent (limit / num_intents)
     * 2. Round-robin through intents, picking top available result from each
     * 3. Skip duplicates (same article ID)
     * 4. Continue until limit is reached or no more results available
     */
    private function balancedMerge(array $intentResults, int $limit, array &$seenIds): array
    {
        $numIntents = count($intentResults);
        if ($numIntents === 0) {
            return [];
        }
        
        // Calculate fair quota per intent (minimum 1 result per intent if possible)
        $quotaPerIntent = max(1, (int) ceil($limit / $numIntents));
        
        // Track how many results we've taken from each intent
        $resultsPerIntent = array_fill(0, $numIntents, 0);
        
        // Track current position in each intent's result list
        $currentPosition = array_fill(0, $numIntents, 0);
        
        $finalResults = [];
        $totalResults = 0;
        
        // Phase 1: Round-robin - give each intent its fair quota
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
                
                // Skip duplicates (same article ID already in results)
                if (isset($seenIds[$candidate['id']])) {
                    continue;
                }
                
                // Skip results below minimum threshold
                if (($candidate['final_score'] ?? 0) < self::SIMILARITY_THRESHOLD * 0.5) {
                    continue;
                }
                
                // Add this result
                $seenIds[$candidate['id']] = true;
                $resultsPerIntent[$intentIndex]++;
                $totalResults++;
                $countForThisIntent++;
                
                // Clean up internal tracking fields before adding to results
                unset($candidate['_intent_index'], $candidate['_intent_query']);
                $candidate['matched_intent'] = $intentIndex;
                
                $finalResults[] = $candidate;
            }
            
            // Update position for potential overflow phase
            $currentPosition[$intentIndex] = $position;
        }
        
        // Phase 2: If we still have room, do another round-robin pass
        if ($totalResults < $limit) {
            $moreRounds = true;
            $maxExtraRounds = 3; // Limit extra rounds
            $round = 0;
            
            while ($moreRounds && $totalResults < $limit && $round < $maxExtraRounds) {
                $moreRounds = false;
                $round++;
                
                for ($intentIndex = 0; $intentIndex < $numIntents; $intentIndex++) {
                    if ($totalResults >= $limit) {
                        break 2;
                    }
                    
                    // Try to get one more result from this intent
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
        
        // Phase 3: Fill remaining slots with best available from any intent
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
            
            // Sort remaining candidates by score and add top ones
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
    
    private function getAllowedCategories(array $domainInfo): array
    {
        if (!$domainInfo['detected'] || empty($domainInfo['domain'])) {
            return [];
        }
        
        $domain = $domainInfo['domain'];
        return $this->domainCategoryMap[$domain] ?? [];
    }
    
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
    
    private function getPublishedArticles(): Collection
    {
        return Article::where('is_published', true)
            ->where('publish_status', 'approved')
            ->with('category')
            ->select('id', 'title', 'content', 'excerpt', 'keywords', 'slug', 'category_id')
            ->get();
    }
    
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
    
    private function normalizeTypos(string $query): string
    {
        // Use VocabularyService for dynamic typo correction
        $normalizationResult = $this->vocabularyService->normalizeQuery($query);
        
        // Store correction info for debugging
        if (!empty($normalizationResult['corrections'])) {
            $this->debugInfo['vocabulary_corrections'] = $normalizationResult['corrections'];
        }
        
        return $normalizationResult['normalized'];
    }
    
    private function hybridRanking(
        array $queryVector,
        array $documentVectors,
        array $documents,
        string $originalQuery,
        array $domainInfo,
        array $allowedCategories
    ): array {
        $rankedResults = [];
        
        // Check if query has security intent
        $hasSecurityIntent = $this->hasSecurityIntent($originalQuery);
        
        // ============================================================
        // IMPORTANT PHRASE DETECTION (NEW - Phrase-level intent boosting)
        // ============================================================
        // Detect important phrases in the query (e.g., "tidak terhubung", "gagal login")
        // These phrases represent TRUE user intent and should DOMINATE ranking
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
            // When query contains security tokens (virus, malware, ransomware, trojan),
            // strongly boost security-related articles to override generic hardware articles
            $securityBoost = 0.0;
            if ($hasSecurityIntent && $this->isSecurityDocument($document)) {
                $securityBoost = 0.35; // Strong boost for security articles when security intent detected
            }
            
            // ============================================================
            // IMPORTANT PHRASE BOOSTING (NEW)
            // ============================================================
            // If query contains important phrases, boost documents that match those phrases
            // This is the KEY FIX for the problem where "wifi tidak terhubung" was returning
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
            
            // Apply phrase boost as a DIRECT ADDITIVE BONUS (not weighted)
            // This ensures phrase matches have STRONG influence on ranking
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
     * Calculate title overlap score with bigram matching for exact phrase detection
     * 
     * This method now:
     * 1. Filters out low-priority generic terms (cara, mengatasi, etc.)
     * 2. Checks for bigram (2-gram) matches for exact phrase detection
     * 3. Gives higher weight to domain-specific term matches
     */
    private function calculateTitleOverlap(array $queryVector, array $document): float
    {
        if (empty($document['title_tokens']) || empty($queryVector)) {
            return 0.0;
        }
        
        $title = $document['title'] ?? '';
        $titleTokens = $document['title_tokens'];
        $queryTerms = array_keys($queryVector);
        
        // Filter out low-priority terms from query for title matching
        $importantQueryTerms = array_filter($queryTerms, fn($t) => !$this->isLowPriorityTerm($t));
        
        if (empty($importantQueryTerms)) {
            $importantQueryTerms = $queryTerms; // Fallback if all terms are low priority
        }
        
        // Check for bigram (2-gram) matches - this captures exact phrases like "komputer lemot"
        $bigramMatches = $this->calculateBigramOverlap($queryTerms, $title);
        
        // Calculate unigram overlap for important terms only
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
        
        // Combine unigram and bigram scores
        $unigramScore = $weightedMatches / count($importantQueryTerms);
        
        // Bigram matches are strong signals of exact phrase matching
        $score = $unigramScore + ($bigramMatches * 0.3);
        
        return min(1.0, $score);
    }
    
    /**
     * Calculate bigram (2-gram) overlap between query and title
     * This helps detect exact phrases like "komputer lemot" in titles
     */
    private function calculateBigramOverlap(array $queryTerms, string $title): int
    {
        // Filter out low-priority terms for bigram generation
        $importantTerms = array_filter($queryTerms, fn($t) => !$this->isLowPriorityTerm($t));
        $importantTerms = array_values($importantTerms);
        
        if (count($importantTerms) < 2) {
            return 0;
        }
        
        $titleLower = strtolower($title);
        $bigramMatches = 0;
        
        // Generate bigrams from important query terms
        for ($i = 0; $i < count($importantTerms) - 1; $i++) {
            $bigram = $importantTerms[$i] . ' ' . $importantTerms[$i + 1];
            if (str_contains($titleLower, $bigram)) {
                $bigramMatches++;
            }
        }
        
        return $bigramMatches;
    }
    
    /**
     * Check if a term is a low-priority generic term
     */
    private function isLowPriorityTerm(string $term): bool
    {
        return in_array(mb_strtolower($term), $this->lowPriorityTerms);
    }
    
    /**
     * Check if a term is domain-specific (should be weighted higher)
     * 
     * Note: Generic technical terms like 'pc', 'laptop', 'komputer', 'aplikasi', 'error'
     * are intentionally EXCLUDED because they are too common and should be downweighted,
     * not boosted. Only specific domain identifiers and action terms are boosted.
     */
    private function isDomainSpecificTerm(string $term): bool
    {
        return in_array(mb_strtolower($term), $this->importantDomainTokens);
    }
    
    /**
     * Check if query contains security priority tokens
     * Returns true if ANY security token is found in the query
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
     * Check if document is security-related
     */
    private function isSecurityDocument(array $document): bool
    {
        $title = strtolower($document['title'] ?? '');
        $content = strtolower($document['text'] ?? '');
        $category = strtolower($document['category_name'] ?? '');
        
        // Check if category is security-related
        if ($category === 'security') {
            return true;
        }
        
        // Check if title or content contains security tokens
        foreach ($this->securityPriorityTokens as $token) {
            if (str_contains($title, $token) || str_contains($content, $token)) {
                return true;
            }
        }
        
        return false;
    }
    
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
     * Calculate query coverage score - measures how many important query terms
     * are present in the document
     * 
     * This method now:
     * 1. Ignores low-priority generic terms (cara, mengatasi, etc.)
     * 2. Gives major boost when ALL important terms match
     * 3. Weights domain-specific terms higher
     */
    private function calculateQueryCoverage(array $queryVector, array $docVector): float
    {
        if (empty($queryVector) || empty($docVector)) {
            return 0.0;
        }
        
        $queryTerms = array_keys($queryVector);
        
        // Filter out low-priority and stopword terms
        $importantTerms = array_filter($queryTerms, fn($t) => 
            !in_array($t, $this->itStopwords) && !$this->isLowPriorityTerm($t)
        );
        $importantTerms = array_values($importantTerms);
        
        if (empty($importantTerms)) {
            // Fallback to all terms if everything is filtered
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
        
        // Major boost when ALL important terms match (full coverage)
        $coverageRatio = $matchedTerms / count($importantTerms);
        if ($coverageRatio >= 1.0) {
            $baseScore += self::FULL_COVERAGE_BONUS;
        } elseif ($coverageRatio >= 0.75) {
            $baseScore += 0.1;
        }
        
        return min(1.0, $baseScore);
    }
    
    /**
     * Calculate exact phrase bonus - rewards documents where the query
     * appears as an exact phrase in the title
     * 
     * This method now:
     * 1. Gives maximum bonus for exact full query match in title
     * 2. Checks for bigram/phrase matches (e.g., "komputer lemot" in title)
     * 3. Filters out low-priority terms when checking word presence
     * 4. Considers word order for phrase detection
     */
    private function calculateExactPhraseBonus(string $originalQuery, array $document): float
    {
        $title = $document['title'] ?? '';
        $excerpt = $document['excerpt'] ?? '';
        $content = $document['text'] ?? '';
        
        $queryLower = strtolower(trim($originalQuery));
        $titleLower = strtolower($title);
        
        // Filter out low-priority terms from query for phrase matching
        $queryWords = explode(' ', $queryLower);
        $importantWords = array_filter($queryWords, fn($w) => 
            mb_strlen($w) > 2 && !$this->isLowPriorityTerm($w)
        );
        $importantWords = array_values($importantWords);
        
        // EXACT MATCH: Full query phrase in title (highest priority)
        if (str_contains($titleLower, $queryLower)) {
            return 1.0;
        }
        
        // EXACT PHRASE MATCH: All important words appear consecutively in title
        if (count($importantWords) >= 2) {
            $importantPhrase = implode(' ', $importantWords);
            if (str_contains($titleLower, $importantPhrase)) {
                return 0.9;
            }
        }
        
        // ALL IMPORTANT WORDS IN TITLE (not necessarily consecutive)
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
        
        // Most words in title (with low-priority terms filtered)
        $wordsInTitle = 0;
        foreach ($importantWords as $word) {
            if (str_contains($titleLower, $word)) {
                $wordsInTitle++;
            }
        }
        
        if (count($importantWords) > 0 && $wordsInTitle / count($importantWords) >= 0.75) {
            return 0.5;
        }
        
        // Check excerpt for exact phrase
        $excerptLower = strtolower($excerpt);
        if (str_contains($excerptLower, $queryLower)) {
            return 0.4;
        }
        
        // Check content for exact phrase
        $contentLower = strtolower($content);
        if (str_contains($contentLower, $queryLower)) {
            return 0.3;
        }
        
        return 0.0;
    }
    
    /**
     * Calculate domain penalty - penalizes articles from unrelated domains
     * 
     * This method now:
     * 1. Uses negative domain penalty mappings for stronger penalties
     * 2. Checks for forbidden domain keywords in article content/title
     * 3. Applies stronger penalties for clearly unrelated domains
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
        
        // Check negative domain penalties (stronger penalties for unrelated domains)
        $negativeDomains = $this->negativeDomainPenalties[$detectedDomain] ?? [];
        foreach ($negativeDomains as $negativeDomain) {
            // Check if the negative domain keyword appears in title or content
            if (str_contains($title, $negativeDomain) || str_contains($content, $negativeDomain)) {
                return self::STRONG_DOMAIN_PENALTY;
            }
            // Check if the document category matches the negative domain
            if ($docCategoryLower === strtolower($negativeDomain)) {
                return self::STRONG_DOMAIN_PENALTY;
            }
        }
        
        // Also check forbidden domain map (legacy penalty)
        $forbiddenDomains = $this->forbiddenDomainMap[$detectedDomain] ?? [];
        foreach ($forbiddenDomains as $forbidden) {
            if ($docCategoryLower === strtolower($forbidden)) {
                return self::DOMAIN_PENALTY;
            }
        }
        
        return 0.0;
    }
    
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
    
    public function shouldEscalate(string $query): bool
    {
        $normalizedQuery = $this->normalizeQueryForTracking($query);
        $failureMemory = Session::get(self::SESSION_FAILURE_KEY, []);
        return ($failureMemory[$normalizedQuery] ?? 0) >= self::FAILURE_THRESHOLD;
    }
    
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
     * Get failure count for a query
     */
    public function getFailureCount(string $query): int
    {
        $normalizedQuery = $this->normalizeQueryForTracking($query);
        $failureMemory = Session::get(self::SESSION_FAILURE_KEY, []);
        return $failureMemory[$normalizedQuery] ?? 0;
    }
    
    /**
     * Clear failure memory for a query (called when success is achieved)
     */
    public function clearFailureForQuery(string $query): void
    {
        $normalizedQuery = $this->normalizeQueryForTracking($query);
        $failureMemory = Session::get(self::SESSION_FAILURE_KEY, []);
        unset($failureMemory[$normalizedQuery]);
        Session::put(self::SESSION_FAILURE_KEY, $failureMemory);
    }
    
    /**
     * Store conversation context for memory
     */
    public function storeConversationContext(array $context): void
    {
        $conversationMemory = Session::get(self::SESSION_CONVERSATION_KEY, []);
        
        $conversationMemory[] = array_merge($context, [
            'timestamp' => now()->timestamp,
        ]);
        
        // Keep only last 10 interactions
        $conversationMemory = array_slice($conversationMemory, -10);
        
        Session::put(self::SESSION_CONVERSATION_KEY, $conversationMemory);
    }
    
    /**
     * Get recent conversation context
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
    public function clearConversationMemory(): void
    {
        Session::forget(self::SESSION_CONVERSATION_KEY);
    }
    
    /**
     * Get clarification suggestions for ambiguous queries
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
     * Check if query needs clarification (ambiguous/generic)
     */
    public function needsClarification(string $query): bool
    {
        $lowerQuery = strtolower(trim($query));
        
        // Very short queries need clarification
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
     * Get clarification response for ambiguous queries
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
     * Enhanced diversification with category quotas
     * Ensures results are diverse across categories and types
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
            
            // Check category quota
            $categoryCounts[$category] = $categoryCounts[$category] ?? 0;
            if ($categoryCounts[$category] >= self::MAX_RESULTS_PER_CATEGORY) {
                // Apply heavy penalty for exceeding quota
                $result['final_score'] = max(0, $result['final_score'] - 0.3);
                $result['category_quota_exceeded'] = true;
            }
            
            // Check title pattern diversity (avoid BSOD domination, etc.)
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
        
        // Re-sort after penalties
        usort($diversifiedResults, fn($a, $b) => $b['final_score'] <=> $a['final_score']);
        
        return $diversifiedResults;
    }
    
    private function normalizeQueryForTracking(string $query): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $query)));
    }
    
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
                'keywords' => $article->keywords,
                'slug' => $article->slug,
                'category_id' => $article->category_id,
                'category_name' => $article->category->name ?? '',
            ];
        }
        
        return $documents;
    }
    
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
            
            // Use the doc_id from the ranked result (which is the article ID)
            $articleId = $result['doc_id'] ?? $doc['doc_id'] ?? $doc['id'] ?? null;
            
            $finalResults[] = [
                'id' => $articleId,
                'title' => $doc['title'],
                'excerpt' => $doc['excerpt'],
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
    
    private function getConfidenceLevel(float $score): string
    {
        if ($score >= self::HIGH_SIMILARITY_THRESHOLD) {
            return 'high';
        } elseif ($score >= self::SIMILARITY_THRESHOLD) {
            return 'medium';
        }
        return 'low';
    }
    
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
     * Return result for OUT-OF-DOMAIN queries
     * This is called when a query is detected as non-IT related
     * 
     * DO NOT fallback to IT articles - return polite rejection instead
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
    
    private function logStage(string $stage, string $input, string $output): void
    {
        $this->debugInfo['stages'][] = [
            'stage' => $stage,
            'input' => substr($input, 0, 100),
            'output' => substr($output, 0, 200),
        ];
    }
    
    public function getCuratedCategories(): array
    {
        return $this->curatedCategories;
    }
    
    public function getCuratedSubtopics(string $categoryId): array
    {
        $subtopics = $this->curatedSubtopics[$categoryId] ?? [];
        
        return array_map(fn($subtopic) => [
            'id' => md5($subtopic),
            'label' => ucfirst($subtopic),
            'query' => $subtopic,
        ], $subtopics);
    }
    
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
     * Evaluate if retrieval results are too weak and should use safe fallback
     * 
     * Returns true if ALL of these conditions are met:
     * - Top score is below SAFE_FALLBACK_THRESHOLD
     * - No strong title overlap (exact phrase match)
     * - Low query coverage
     * - Domain mismatch or no domain detected
     */
    private function shouldUseSafeFallback(array $retrievalResult): bool
    {
        if (empty($retrievalResult['results'])) {
            return true;
        }
        
        $topArticle = $retrievalResult['results'][0];
        $topScore = $topArticle['final_score'] ?? 0;
        
        // If top score is above safe fallback threshold, results are acceptable
        if ($topScore >= self::SAFE_FALLBACK_THRESHOLD) {
            return false;
        }
        
        // Check debug info for additional signals
        $debugInfo = $retrievalResult['debug'] ?? null;
        if (!$debugInfo) {
            // Without debug info, we can't verify additional signals
            // Fall back to score-based decision
            return $topScore < self::SAFE_FALLBACK_THRESHOLD;
        }
        
        // Check if there are any strong signals in the scores
        $scores = $debugInfo['scores'] ?? [];
        $hasStrongTitleOverlap = false;
        $hasStrongQueryCoverage = false;
        $hasExactPhraseMatch = false;
        
        foreach ($scores as $docId => $scoreBreakdown) {
            // Check for strong title overlap (above 0.5)
            if (($scoreBreakdown['title_overlap'] ?? 0) > 0.5) {
                $hasStrongTitleOverlap = true;
            }
            // Check for exact phrase match (exact_phrase score of 0.75+)
            if (($scoreBreakdown['exact_phrase'] ?? 0) >= 0.75) {
                $hasExactPhraseMatch = true;
            }
            // Check for strong query coverage (above 0.7)
            if (($scoreBreakdown['query_coverage'] ?? 0) > 0.7) {
                $hasStrongQueryCoverage = true;
            }
        }
        
        // If ANY strong signal exists, allow the results through
        if ($hasStrongTitleOverlap || $hasStrongQueryCoverage || $hasExactPhraseMatch) {
            return false;
        }
        
        // All signals are weak - use safe fallback
        return true;
    }
    
    /**
     * Get safe fallback message for weak/unclear queries
     * This prevents returning unrelated articles when confidence is too low
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
    
    public function formatResponse(array $retrievalResult): array
    {
        // Check for OUT-OF-DOMAIN queries first
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
        
        // Check if results are too weak - use safe fallback instead of unrelated articles
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
        
        // Upgrade confidence to 'very_low' if score is extremely low
        $topScore = $topArticle['final_score'] ?? 0;
        if ($topScore < self::SIMILARITY_THRESHOLD * 1.2) {
            $confidence = 'very_low';
        }
        
        $response = $this->generateResponseText($topArticle, count($retrievalResult['results']), $confidence);
        
        // Show contact button for low and very_low confidence
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
    
    private function generateResponseText(array $topArticle, int $totalResults, string $confidence): string
    {
        $title = $topArticle['title'];
        
        if ($confidence === 'high') {
            $templates = [
                "Saya menemukan artikel yang sangat relevan: **{$title}** 😊",
                "Artikel ini sepertinya tepat untuk Anda: **{$title}**",
                "Saya yakin ini yang Anda cari: **{$title}** ✓",
            ];
        } elseif ($confidence === 'medium') {
            $templates = [
                "Berdasarkan pencarian saya, **{$title}** mungkin dapat membantu Anda.",
                "Saya menemukan informasi yang relevan: **{$title}**.",
                "Ada artikel yang cocok: **{$title}**.",
            ];
        } else {
            $templates = [
                "Saya menemukan artikel yang mungkin membantu: **{$title}**.",
                "Coba lihat artikel ini: **{$title}**.",
                "Mungkin ini yang Anda butuhkan: **{$title}**.",
            ];
        }
        
        $hash = md5($title . $confidence . $totalResults);
        $index = hexdec(substr($hash, 0, 4)) % count($templates);
        
        return $templates[$index];
    }
    
    public function getDebugInfo(): array
    {
        return $this->debugInfo;
    }
}