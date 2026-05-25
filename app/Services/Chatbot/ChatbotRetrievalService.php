<?php

namespace App\Services\Chatbot;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ChatbotRetrievalService
{
    // ============================================================
    // SIMPLIFIED CONFIGURATION
    // ============================================================
    // Trust Typesense ranking as primary signal
    // TF-IDF provides only LIGHT reranking (10-20% influence)
    // ============================================================
    
    private const TOP_K_RESULTS = 5;
    private const SIMILARITY_THRESHOLD = 0.05;
    private const TYPESENSE_CANDIDATE_LIMIT = 30;
    
    // Weight configuration - trust Typesense more
    private const TYPESENSE_WEIGHT = 0.85;  // 85% Typesense (primary ranking)
    private const TFIDF_WEIGHT = 0.15;       // 15% TF-IDF (light reranking only)
    
    // Light boost factors (reduced from massive values)
    private const TITLE_MATCH_BOOST = 0.5;   // Light boost for title match
    private const EXACT_MATCH_BOOST = 0.3;   // Light boost for exact match
    
    // Cache configuration
    private const VECTOR_CACHE_KEY = 'chatbot:retrieval:vectors:normalized';
    private const VECTOR_CACHE_TTL = 86400; // 24 hours
    private const IDF_CACHE_KEY = 'chatbot:retrieval:idf';
    private const TOPIC_CACHE_KEY = 'chatbot:topics';
    private const TOPIC_CACHE_TTL = 3600; // 1 hour

    private PreprocessingService $preprocessor;
    private TfidfService $tfidfService;
    private CosineSimilarityService $similarityService;
    private DomainDetectionService $domainDetector;
    private TypesenseService $typesenseService;

    // Debug info storage
    private array $debugInfo = [];
    private bool $debugMode;

    public function __construct(
        PreprocessingService $preprocessor,
        TfidfService $tfidfService,
        CosineSimilarityService $similarityService,
        DomainDetectionService $domainDetector,
        TypesenseService $typesenseService
    ) {
        $this->preprocessor = $preprocessor;
        $this->tfidfService = $tfidfService;
        $this->similarityService = $similarityService;
        $this->domainDetector = $domainDetector;
        $this->typesenseService = $typesenseService;
        $this->debugMode = config('app.debug', false);
    }

    /**
     * Main retrieval method - SIMPLIFIED PIPELINE
     * 
     * 1. Typesense: Primary retrieval with fuzzy matching → get ranked candidates
     * 2. TF-IDF: Light reranking (15% influence) → minor adjustments only
     * 
     * Key change: Trust Typesense ranking as PRIMARY signal
     */
    public function retrieve(string $query, int $limit = self::TOP_K_RESULTS): array
    {
        // Reset debug info
        $this->debugInfo = [
            'original_query' => $query,
            'typesense_used' => false,
            'typesense_candidates' => 0,
        ];

        // Step 1: Normalize query
        $normalizedQuery = $this->normalizeQuery($query);
        $this->debugInfo['normalized_query'] = $normalizedQuery;

        // Step 2: Detect domain (for optional filtering)
        $domainInfo = $this->domainDetector->detectDomain($query);
        $this->debugInfo['detected_domain'] = $domainInfo['domain'] ?? null;

        // ============================================================
        // PHASE A: TYPESENSE PRIMARY RETRIEVAL
        // Typesense is the PRIMARY ranking source (85% weight)
        // ============================================================
        $typesenseResults = null;
        $typesenseCandidates = [];
        
        if ($this->typesenseService->isConnected()) {
            $searchOptions = [];
            if (!empty($domainInfo['category_ids'])) {
                $searchOptions['category_id'] = $domainInfo['category_ids'][0] ?? null;
            }
            
            $typesenseResults = $this->typesenseService->search(
                $query,
                self::TYPESENSE_CANDIDATE_LIMIT,
                $searchOptions
            );
            
            if ($typesenseResults['success'] && !empty($typesenseResults['results'])) {
                $typesenseCandidates = $typesenseResults['results'];
                $this->debugInfo['typesense_used'] = true;
                $this->debugInfo['typesense_candidates'] = count($typesenseCandidates);
            }
        }

        // ============================================================
        // PHASE B: GET ARTICLES FOR TF-IDF LIGHT RERANKING
        // ============================================================
        $articles = $this->getArticlesForReranking($typesenseCandidates, $domainInfo);
        
        if ($articles->isEmpty()) {
            return $this->emptyResult($query);
        }

        // ============================================================
        // PHASE C: TF-IDF LIGHT RERANKING (15% influence only)
        // ============================================================
        $documents = $this->prepareDocuments($articles);
        $tfidfData = $this->buildOrRetrieveVectors($documents);
        
        $queryVector = $this->tfidfService->calculateQueryTFIDF(
            $normalizedQuery,
            $tfidfData['idf']
        );

        if (empty($queryVector)) {
            // No TF-IDF match - rely entirely on Typesense ranking
            return $this->buildTypesenseOnlyResults($typesenseCandidates, $articles, $limit);
        }

        // Calculate TF-IDF similarities for light reranking
        $tfidfSimilarities = $this->calculateTfidfSimilarities($queryVector, $tfidfData['vectors']);
        
        // Apply light boosting (title match, exact match) - minimal influence
        $boostedSimilarities = $this->applyLightBoosting($tfidfSimilarities, $documents, $queryVector);

        // ============================================================
        // PHASE D: COMBINE TYPESENSE + TF-IDF SCORES
        // Typesense: 85%, TF-IDF: 15% (light reranking)
        // ============================================================
        $combinedScores = $this->combineScores($typesenseCandidates, $boostedSimilarities);

        // ============================================================
        // PHASE E: BUILD FINAL RESULTS
        // ============================================================
        $results = $this->buildFinalResults($combinedScores, $articles, $limit);

        $thresholdMet = !empty($results) && $results[0]['similarity'] >= self::SIMILARITY_THRESHOLD;

        $this->debugInfo['final_results'] = count($results);
        $this->debugInfo['threshold_met'] = $thresholdMet;

        if ($this->debugMode) {
            Log::info('Chatbot retrieval debug', $this->debugInfo);
        }

        return [
            'results' => $results,
            'query' => $query,
            'normalized_query' => $normalizedQuery,
            'total' => count($results),
            'threshold_met' => $thresholdMet,
            'max_similarity' => !empty($results) ? $results[0]['similarity'] : 0,
            'domain_detected' => $domainInfo['detected'] ?? false,
            'detected_domain' => $domainInfo['domain'] ?? null,
            'typesense_used' => $this->debugInfo['typesense_used'],
            'typesense_candidates' => $this->debugInfo['typesense_candidates'],
            'debug' => $this->debugMode ? $this->debugInfo : null,
        ];
    }

    /**
     * Normalize query (simple preprocessing)
     */
    private function normalizeQuery(string $query): string
    {
        return $this->preprocessor->normalizeTypos($query);
    }

    /**
     * Get articles for reranking - prefer Typesense candidates
     */
    private function getArticlesForReranking(array $typesenseCandidates, array $domainInfo): Collection
    {
        if (!empty($typesenseCandidates)) {
            $candidateIds = array_column($typesenseCandidates, 'id');
            return Article::whereIn('id', $candidateIds)
                ->where('is_published', true)
                ->where('publish_status', 'approved')
                ->with('category')
                ->get();
        }

        // Fallback to database search
        $query = Article::where('is_published', true)
            ->where('publish_status', 'approved')
            ->with('category');

        if (!empty($domainInfo['category_ids'])) {
            $query->whereIn('category_id', $domainInfo['category_ids']);
        }

        return $query->select('id', 'title', 'content', 'excerpt', 'keywords', 'slug', 'category_id')
            ->get();
    }

    /**
     * Build results using Typesense ranking only (when TF-IDF fails)
     */
    private function buildTypesenseOnlyResults(array $typesenseCandidates, Collection $articles, int $limit): array
    {
        $results = [];
        $articlesMap = $articles->keyBy('id');
        
        foreach ($typesenseCandidates as $candidate) {
            if (count($results) >= $limit) {
                break;
            }
            
            if (!isset($articlesMap[$candidate['id']])) {
                continue;
            }
            
            $article = $articlesMap[$candidate['id']];
            $score = $candidate['typesense_score'] ?? 0;
            $normalizedScore = $this->normalizeTypesenseScore($score, $typesenseCandidates);
            
            $results[] = [
                'id' => $article->id,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'slug' => $article->slug,
                'category_id' => $article->category_id,
                'category_name' => $article->category->name ?? null,
                'similarity' => round($normalizedScore, 4),
                'confidence' => $this->getConfidenceLevel($normalizedScore),
                'url' => route('articles.show', $article->slug),
            ];
        }
        
        $this->debugInfo['ranking_method'] = 'typesense_only';
        
        return $results;
    }

    /**
     * Normalize Typesense score to 0-1 range
     */
    private function normalizeTypesenseScore(float $score, array $candidates): float
    {
        if (empty($candidates)) return 0;
        
        $maxScore = max(array_column($candidates, 'typesense_score'));
        if ($maxScore <= 0) return 0;
        
        return $score / $maxScore;
    }

    /**
     * Calculate TF-IDF similarities
     */
    private function calculateTfidfSimilarities(array $queryVector, array $documentVectors): array
    {
        $similarities = [];
        
        foreach ($documentVectors as $docId => $docVector) {
            $similarities[$docId] = $this->similarityService->calculate($queryVector, $docVector);
        }
        
        return $similarities;
    }

    /**
     * Apply LIGHT boosting (minimal influence)
     */
    private function applyLightBoosting(array $similarities, array $documents, array $queryVector): array
    {
        foreach ($similarities as $docId => $similarity) {
            $document = $documents[$docId] ?? null;
            if (!$document) continue;
            
            $boost = 0.0;
            
            // Light title match boost
            $titleTokens = $document['title_tokens'] ?? [];
            $queryTerms = array_keys($queryVector);
            $matchedInTitle = 0;
            
            foreach ($queryTerms as $term) {
                if (in_array($term, $titleTokens)) {
                    $matchedInTitle++;
                }
            }
            
            if (!empty($queryTerms)) {
                $titleMatchRatio = $matchedInTitle / count($queryTerms);
                $boost += $titleMatchRatio * self::TITLE_MATCH_BOOST;
            }
            
            // Light exact phrase boost
            $title = mb_strtolower($document['title'] ?? '');
            $queryPhrase = implode(' ', $queryTerms);
            if (str_contains($title, $queryPhrase)) {
                $boost += self::EXACT_MATCH_BOOST;
            }
            
            $similarities[$docId] = max(0, $similarity + $boost);
        }
        
        return $similarities;
    }

    /**
     * Combine Typesense and TF-IDF scores
     * Typesense: 85%, TF-IDF: 15%
     */
    private function combineScores(array $typesenseCandidates, array $tfidfSimilarities): array
    {
        $combinedScores = [];
        
        // Build Typesense score map
        $typesenseScores = [];
        foreach ($typesenseCandidates as $candidate) {
            $typesenseScores[$candidate['id']] = $candidate['typesense_score'] ?? 0;
        }
        
        // Normalize Typesense scores
        $maxTypesenseScore = !empty($typesenseScores) ? max($typesenseScores) : 0;
        if ($maxTypesenseScore > 0) {
            foreach ($typesenseScores as $id => $score) {
                $typesenseScores[$id] = $score / $maxTypesenseScore;
            }
        }
        
        // Normalize TF-IDF scores
        $maxTfidfScore = !empty($tfidfSimilarities) ? max($tfidfSimilarities) : 0;
        if ($maxTfidfScore > 0) {
            foreach ($tfidfSimilarities as $id => $score) {
                $tfidfSimilarities[$id] = $score / $maxTfidfScore;
            }
        }
        
        // Combine scores: 85% Typesense + 15% TF-IDF
        $allIds = array_unique(array_merge(array_keys($typesenseScores), array_keys($tfidfSimilarities)));
        
        foreach ($allIds as $id) {
            $tsScore = $typesenseScores[$id] ?? 0;
            $tfidfScore = $tfidfSimilarities[$id] ?? 0;
            
            $combinedScores[$id] = ($tsScore * self::TYPESENSE_WEIGHT) + ($tfidfScore * self::TFIDF_WEIGHT);
        }
        
        arsort($combinedScores);
        
        return $combinedScores;
    }

    /**
     * Build final results from combined scores
     */
    private function buildFinalResults(array $combinedScores, Collection $articles, int $limit): array
    {
        $results = [];
        $articlesMap = $articles->keyBy('id');
        
        foreach ($combinedScores as $docId => $score) {
            if (count($results) >= $limit) {
                break;
            }
            
            if (!isset($articlesMap[$docId])) {
                continue;
            }
            
            if ($score < self::SIMILARITY_THRESHOLD) {
                continue;
            }
            
            $article = $articlesMap[$docId];
            
            $results[] = [
                'id' => $article->id,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'slug' => $article->slug,
                'category_id' => $article->category_id,
                'category_name' => $article->category->name ?? null,
                'similarity' => round($score, 4),
                'confidence' => $this->getConfidenceLevel($score),
                'url' => route('articles.show', $article->slug),
            ];
        }
        
        return $results;
    }

    /**
     * Get confidence level based on similarity score
     */
    private function getConfidenceLevel(float $similarity): string
    {
        if ($similarity >= 0.15) {
            return 'high';
        } elseif ($similarity >= self::SIMILARITY_THRESHOLD) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Get empty result structure
     */
    private function emptyResult(string $query): array
    {
        return [
            'results' => [],
            'query' => $query,
            'total' => 0,
            'threshold_met' => false,
            'max_similarity' => 0,
        ];
    }

    /**
     * Prepare documents for TF-IDF
     */
    private function prepareDocuments(Collection $articles): array
    {
        $documents = [];

        foreach ($articles as $article) {
            $titleTokens = $this->preprocessor->preprocess($article->title);
            $excerptTokens = $this->preprocessor->preprocess($article->excerpt ?? '');
            $keywordsTokens = $this->preprocessor->preprocess($article->keywords ?? '');
            $contentTokens = $this->preprocessor->preprocess($article->content);

            // Combine tokens with title weight
            $allTokens = [];
            foreach ($titleTokens as $token) {
                $allTokens[] = $token;
                $allTokens[] = $token; // Extra weight for title
            }
            $allTokens = array_merge($allTokens, $excerptTokens, $keywordsTokens, $contentTokens);

            $frequency = array_count_values($allTokens);

            $documents[$article->id] = [
                'text' => implode(' ', $allTokens),
                'frequency' => $frequency,
                'title' => $article->title,
                'title_tokens' => $titleTokens,
                'excerpt' => $article->excerpt,
                'keywords' => $article->keywords,
                'slug' => $article->slug,
                'category_id' => $article->category_id,
            ];
        }

        return $documents;
    }

    /**
     * Build or retrieve TF-IDF vectors from cache
     */
    private function buildOrRetrieveVectors(array $documents): array
    {
        $docCount = count($documents);
        $docIds = implode(',', array_keys($documents));
        $cacheKey = self::VECTOR_CACHE_KEY . ':' . md5($docIds);

        $cached = Cache::get($cacheKey);

        if ($cached !== null && ($cached['docCount'] ?? 0) === $docCount) {
            return $cached;
        }

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

        $tfidfData = [
            'vectors' => $vectors,
            'idf' => $idf,
            'docCount' => $docCount,
        ];

        Cache::put($cacheKey, $tfidfData, self::VECTOR_CACHE_TTL);

        return $tfidfData;
    }

    /**
     * Clear all caches
     */
    public function clearCache(): void
    {
        Cache::forget(self::VECTOR_CACHE_KEY);
        Cache::forget(self::IDF_CACHE_KEY);
        Cache::forget(self::TOPIC_CACHE_KEY);
        $this->tfidfService->clearCache();

        Log::info('Chatbot retrieval cache cleared');
    }

    /**
     * Rebuild cache vectors
     */
    public function rebuildCache(): array
    {
        $this->clearCache();

        $articles = Article::where('is_published', true)
            ->where('publish_status', 'approved')
            ->get();
        $documents = $this->prepareDocuments($articles);

        $documentTermFrequencies = [];
        foreach ($documents as $docId => $doc) {
            $documentTermFrequencies[$docId] = $doc['frequency'];
        }

        $idf = $this->tfidfService->calculateIDF($documentTermFrequencies);

        return [
            'success' => true,
            'documents' => count($documents),
            'terms' => count($idf),
        ];
    }

    /**
     * Format response for chatbot display
     */
    public function formatResponse(array $retrievalResult): array
    {
        if (empty($retrievalResult['results'])) {
            return $this->noResultsResponse();
        }

        $topArticle = $retrievalResult['results'][0];
        $totalResults = count($retrievalResult['results']);
        $confidence = $topArticle['confidence'] ?? 'medium';

        $response = $this->generateResponseText($topArticle, $totalResults, $confidence);

        return [
            'success' => true,
            'response' => $response,
            'articles' => $retrievalResult['results'],
            'show_contact_button' => $confidence === 'low',
            'contact_button_text' => 'Masih butuh bantuan? Hubungi staff kami',
            'confidence' => $confidence,
        ];
    }

    /**
     * Generate response when no results found
     */
    private function noResultsResponse(): array
    {
        $responses = [
            'Maaf, saya belum menemukan artikel yang benar-benar sesuai dengan pertanyaan Anda.',
            'Saya mencari di basis pengetahuan, tetapi belum menemukan jawaban yang tepat.',
            'Pertanyaan Anda menarik, namun saya belum punya artikel yang cocok. Mungkin bisa coba dengan kata kunci lain?',
        ];

        return [
            'success' => false,
            'response' => $responses[array_rand($responses)],
            'articles' => [],
            'show_contact_button' => true,
            'contact_button_text' => 'Buat Tiket untuk Bantuan Lebih Lanjut',
            'confidence' => 'none',
        ];
    }

    /**
     * Generate natural response text
     */
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
            ];
        } else {
            $templates = [
                "Saya menemukan artikel yang mungkin membantu: **{$title}**.",
                "Coba lihat artikel ini: **{$title}**.",
            ];
        }

        $hash = md5($title . $confidence . $totalResults);
        $index = hexdec(substr($hash, 0, 4)) % count($templates);
        
        return $templates[$index];
    }

    /**
     * Check if query is a greeting
     */
    public function isGreeting(string $query): bool
    {
        $greetings = ['halo', 'hai', 'hello', 'hi', 'pagi', 'siang', 'sore', 'malam', 'assalamualaikum', 'permisi'];
        $lowerQuery = mb_strtolower(trim($query));

        foreach ($greetings as $greeting) {
            if (str_contains($lowerQuery, $greeting)) {
                return true;
            }
        }

        $preprocessed = $this->preprocessor->preprocess($query);
        foreach ($preprocessed as $token) {
            if (in_array($token, $greetings)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get random greeting response
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
     * Get dynamic topics from categories
     */
    public function getDynamicTopics(int $limit = 5): array
    {
        $cached = Cache::get(self::TOPIC_CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        $categories = Category::whereHas('articles', function ($query) {
            $query->where('is_published', true)
                  ->where('publish_status', 'approved');
        })
        ->withCount(['articles as article_count' => function ($query) {
            $query->where('is_published', true)
                  ->where('publish_status', 'approved');
        }])
        ->orderByDesc('article_count')
        ->limit($limit)
        ->get(['id', 'name']);

        $topics = [];
        foreach ($categories as $category) {
            $topics[] = [
                'id' => $category->id,
                'type' => 'category',
                'label' => $category->name,
                'count' => $category->article_count,
            ];
        }

        Cache::put(self::TOPIC_CACHE_KEY, $topics, self::TOPIC_CACHE_TTL);

        return array_slice($topics, 0, $limit);
    }

    /**
     * Get subtopics for a given topic
     */
    public function getSubtopics(string $topicLabel, int $limit = 4): array
    {
        $subtopics = [];
        $result = $this->retrieve($topicLabel, $limit + 2);

        foreach ($result['results'] as $article) {
            $subtopics[] = [
                'id' => $article['id'],
                'type' => 'article',
                'label' => $article['title'],
                'excerpt' => $this->truncateText($article['excerpt'] ?? '', 80),
                'slug' => $article['slug'],
                'url' => $article['url'],
            ];
        }

        return array_slice($subtopics, 0, $limit);
    }

    /**
     * Get article suggestion card
     */
    public function getArticleSuggestion(int $articleId): array
    {
        $article = Article::where('is_published', true)
            ->where('publish_status', 'approved')
            ->with('category')
            ->find($articleId);

        if (!$article) {
            return [
                'success' => false,
                'message' => 'Artikel tidak ditemukan.',
            ];
        }

        return [
            'success' => true,
            'article' => [
                'id' => $article->id,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'slug' => $article->slug,
                'url' => route('articles.show', $article->slug),
            ],
            'response' => "Saya menemukan artikel yang mungkin membantu: **{$article->title}**",
        ];
    }

    /**
     * Get related articles
     */
    public function getRelatedArticles(int $articleId, int $limit = 3): array
    {
        $article = Article::find($articleId);
        if (!$article) {
            return [];
        }

        $result = $this->retrieve($article->title . ' ' . $article->excerpt, $limit + 1);

        $related = [];
        foreach ($result['results'] as $result) {
            if ($result['id'] != $articleId && count($related) < $limit) {
                $related[] = $result;
            }
        }

        return $related;
    }

    /**
     * Truncate text
     */
    private function truncateText(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }

    /**
     * Clear all caches
     */
    public function clearAllCaches(): void
    {
        $this->clearCache();
        Log::info('All chatbot caches cleared');
    }
}