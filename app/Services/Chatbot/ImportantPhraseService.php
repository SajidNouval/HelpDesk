<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Log;

/**
 * ImportantPhraseService - Phrase-level intent boosting for improved retrieval accuracy
 * 
 * This service implements IMPORTANT PHRASE BOOSTING to solve the problem where
 * short contextual queries retrieve wrong articles due to token-based ranking.
 * 
 * Problem: "wifi tidak terhubung" returns "Internet lambat" article instead of "Wifi tidak terhubung" article
 * Root Cause: Individual tokens (wifi, internet, lambat) dominate ranking while important phrases
 *             like "tidak terhubung" are not weighted strongly enough.
 * 
 * Solution: Detect and boost important phrases that represent true user intent:
 * - tidak terhubung (not connected)
 * - putus nyambung (intermittent connection)
 * - gagal login (login failed)
 * - tidak terbaca (not detected/read)
 * - tidak muncul (not appearing)
 * - tidak merespon (not responding)
 * - koneksi gagal (connection failed)
 * 
 * These phrases should have HIGHER ranking influence than isolated token matches.
 */
class ImportantPhraseService
{
    // ============================================================
    // IMPORTANT PHRASES - TRUE INTENT INDICATORS
    // ============================================================
    // These phrases represent REAL user intent and should DOMINATE ranking
    // When these phrases appear in a query, articles containing these phrases
    // should rank MUCH higher than articles matching only individual tokens
    private array $importantPhrases = [
        // Connection issues
        'tidak terhubung',
        'tidak connect',
        'tidak konek',
        'koneksi gagal',
        'gagal connect',
        'gagal terhubung',
        'putus nyambung',
        'sering putus',
        'tidak bisa connect',
        'tidak bisa terhubung',
        
        // Detection issues
        'tidak terbaca',
        'tidak terdeteksi',
        'tidak muncul',
        'tidak kedetect',
        'tidak dikenali',
        
        // Login/Access issues
        'gagal login',
        'tidak bisa login',
        'gagal masuk',
        'tidak bisa masuk',
        'terkunci',
        'akun terkunci',
        
        // Response issues
        'tidak merespon',
        'tidak respon',
        'tidak responsif',
        'tidak bereaksi',
        'diam saja',
        
        // Functionality issues
        'tidak berfungsi',
        'tidak bisa digunakan',
        'tidak bisa dipakai',
        'tidak mau',
        'tidak bisa',
        'gagal berfungsi',
        
        // Display issues
        'tidak muncul',
        'hilang tiba-tiba',
        'menghilang',
        'blank',
        'layar hitam',
        'layar biru',
        
        // Performance issues
        'sangat lambat',
        'lemot parah',
        'macet total',
        'hang',
        'freeze',
        'not responding',
        
        // Error issues
        'error terus',
        'muncul error',
        'pesan error',
        'kode error',
        'notifikasi error',
    ];

    // ============================================================
    // PHRASE CATEGORIES - For domain-specific boosting
    // ============================================================
    private array $phraseCategories = [
        'connection' => [
            'tidak terhubung', 'tidak connect', 'tidak konek', 'koneksi gagal',
            'gagal connect', 'gagal terhubung', 'putus nyambung', 'sering putus',
            'tidak bisa connect', 'tidak bisa terhubung',
        ],
        'detection' => [
            'tidak terbaca', 'tidak terdeteksi', 'tidak muncul', 'tidak kedetect',
            'tidak dikenali',
        ],
        'login' => [
            'gagal login', 'tidak bisa login', 'gagal masuk', 'tidak bisa masuk',
            'terkunci', 'akun terkunci',
        ],
        'response' => [
            'tidak merespon', 'tidak respon', 'tidak responsif', 'tidak bereaksi',
            'diam saja',
        ],
        'functionality' => [
            'tidak berfungsi', 'tidak bisa digunakan', 'tidak bisa dipakai',
            'tidak mau', 'tidak bisa', 'gagal berfungsi',
        ],
        'display' => [
            'tidak muncul', 'hilang tiba-tiba', 'menghilang', 'blank',
            'layar hitam', 'layar biru',
        ],
        'performance' => [
            'sangat lambat', 'lemot parah', 'macet total', 'hang', 'freeze',
            'not responding',
        ],
        'error' => [
            'error terus', 'muncul error', 'pesan error', 'kode error',
            'notifikasi error',
        ],
    ];

    // ============================================================
    // BOOSTING WEIGHTS
    // ============================================================
    private const PHRASE_MATCH_BONUS = 0.4;        // Base bonus for phrase match in content
    private const TITLE_PHRASE_BONUS = 0.6;        // Strong bonus for phrase match in title
    private const EXACT_QUERY_PHRASE_BONUS = 0.8;  // Maximum bonus for exact query phrase in title
    private const PHRASE_CATEGORY_BOOST = 0.15;    // Additional boost for category-aligned phrases
    
    // Minimum phrase length to consider (filter out too common short phrases)
    private const MIN_PHRASE_LENGTH = 2;

    private array $debugInfo = [];

    /**
     * Detect important phrases in the query
     * Returns array of detected phrases with their positions
     */
    public function detectPhrases(string $query): array
    {
        $queryLower = strtolower(trim($query));
        $detectedPhrases = [];

        // Sort phrases by length (longest first) to match multi-word phrases first
        $sortedPhrases = $this->importantPhrases;
        usort($sortedPhrases, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        foreach ($sortedPhrases as $phrase) {
            if (mb_strlen($phrase) < self::MIN_PHRASE_LENGTH) {
                continue;
            }

            if (str_contains($queryLower, $phrase)) {
                $position = mb_strpos($queryLower, $phrase);
                $detectedPhrases[] = [
                    'phrase' => $phrase,
                    'position' => $position,
                    'length' => mb_strlen($phrase),
                    'category' => $this->getPhraseCategory($phrase),
                ];
            }
        }

        // Remove overlapping phrases (keep longer ones)
        $detectedPhrases = $this->removeOverlappingPhrases($detectedPhrases);

        $this->debugInfo['detected_phrases'] = $detectedPhrases;

        return $detectedPhrases;
    }

    /**
     * Remove overlapping phrases, keeping the longer ones
     */
    private function removeOverlappingPhrases(array $phrases): array
    {
        if (count($phrases) <= 1) {
            return $phrases;
        }

        // Sort by position
        usort($phrases, fn($a, $b) => $a['position'] <=> $b['position']);

        $result = [];
        $lastEnd = -1;

        foreach ($phrases as $phrase) {
            if ($phrase['position'] >= $lastEnd) {
                $result[] = $phrase;
                $lastEnd = $phrase['position'] + $phrase['length'];
            }
        }

        return $result;
    }

    /**
     * Get the category of a phrase
     */
    private function getPhraseCategory(string $phrase): ?string
    {
        foreach ($this->phraseCategories as $category => $phrases) {
            if (in_array($phrase, $phrases)) {
                return $category;
            }
        }
        return null;
    }

    /**
     * Calculate phrase match score for a document
     * This is the main scoring method that should be integrated into hybrid ranking
     */
    public function calculatePhraseScore(
        string $query,
        array $detectedPhrases,
        array $document
    ): array {
        $title = strtolower($document['title'] ?? '');
        $content = strtolower($document['text'] ?? '');
        $excerpt = strtolower($document['excerpt'] ?? '');
        $fullText = $title . ' ' . $excerpt . ' ' . $content;

        $phraseMatches = [];
        $titlePhraseMatches = [];
        $totalBonus = 0.0;
        $maxBonus = 0.0;

        foreach ($detectedPhrases as $phraseInfo) {
            $phrase = $phraseInfo['phrase'];
            $category = $phraseInfo['category'];

            $matchInfo = [
                'phrase' => $phrase,
                'in_title' => false,
                'in_excerpt' => false,
                'in_content' => false,
                'bonus' => 0.0,
            ];

            // Check title first (highest priority)
            if (str_contains($title, $phrase)) {
                $matchInfo['in_title'] = true;
                $bonus = self::TITLE_PHRASE_BONUS;
                
                // Extra bonus if phrase is at the beginning of title
                if (mb_strpos($title, $phrase) === 0) {
                    $bonus += 0.1;
                }
                
                $matchInfo['bonus'] = $bonus;
                $titlePhraseMatches[] = $phrase;
            }
            // Check excerpt
            elseif (str_contains($excerpt, $phrase)) {
                $matchInfo['in_excerpt'] = true;
                $matchInfo['bonus'] = self::PHRASE_MATCH_BONUS * 0.8;
            }
            // Check content
            elseif (str_contains($content, $phrase)) {
                $matchInfo['in_content'] = true;
                $matchInfo['bonus'] = self::PHRASE_MATCH_BONUS;
            }

            $phraseMatches[] = $matchInfo;
            $totalBonus += $matchInfo['bonus'];
            $maxBonus = max($maxBonus, $matchInfo['bonus']);
        }

        // Check if the full query (or important part) appears in title
        $queryLower = strtolower(trim($query));
        $queryWords = explode(' ', $queryLower);
        $importantQueryWords = array_filter($queryWords, fn($w) => mb_strlen($w) > 2);
        
        if (count($importantQueryWords) >= 2) {
            $importantQueryPhrase = implode(' ', $importantQueryWords);
            if (str_contains($title, $importantQueryPhrase)) {
                $totalBonus += self::EXACT_QUERY_PHRASE_BONUS;
                $this->debugInfo['exact_query_phrase_match'] = $importantQueryPhrase;
            }
        }

        // Cap the total bonus
        $totalBonus = min($totalBonus, 1.0);

        $result = [
            'phrase_matches' => $phraseMatches,
            'title_phrase_matches' => $titlePhraseMatches,
            'total_bonus' => $totalBonus,
            'max_bonus' => $maxBonus,
            'has_title_match' => !empty($titlePhraseMatches),
            'matched_phrase_count' => count(array_filter($phraseMatches, fn($m) => $m['bonus'] > 0)),
        ];

        $this->debugInfo['phrase_score'] = $result;

        return $result;
    }

    /**
     * Calculate n-gram overlap between query and document
     * Supports bigram and trigram matching
     */
    public function calculateNgramOverlap(string $query, array $document): array
    {
        $title = strtolower($document['title'] ?? '');
        $content = strtolower($document['text'] ?? '');
        $fullText = $title . ' ' . $content;

        $queryLower = strtolower(trim($query));
        $queryWords = preg_split('/\s+/', $queryLower, -1, PREG_SPLIT_NO_EMPTY);

        $bigramMatches = [];
        $trigramMatches = [];
        $bigramScore = 0.0;
        $trigramScore = 0.0;

        // Generate bigrams from query
        if (count($queryWords) >= 2) {
            for ($i = 0; $i < count($queryWords) - 1; $i++) {
                $bigram = $queryWords[$i] . ' ' . $queryWords[$i + 1];
                
                if (str_contains($fullText, $bigram)) {
                    $bigramMatches[] = $bigram;
                    $bigramScore += 0.15; // Each bigram match adds 0.15
                    
                    // Extra bonus if bigram is in title
                    if (str_contains($title, $bigram)) {
                        $bigramScore += 0.1;
                    }
                }
            }
        }

        // Generate trigrams from query
        if (count($queryWords) >= 3) {
            for ($i = 0; $i < count($queryWords) - 2; $i++) {
                $trigram = $queryWords[$i] . ' ' . $queryWords[$i + 1] . ' ' . $queryWords[$i + 2];
                
                if (str_contains($fullText, $trigram)) {
                    $trigramMatches[] = $trigram;
                    $trigramScore += 0.25; // Each trigram match adds 0.25 (stronger signal)
                    
                    // Extra bonus if trigram is in title
                    if (str_contains($title, $trigram)) {
                        $trigramScore += 0.15;
                    }
                }
            }
        }

        // Cap scores
        $bigramScore = min($bigramScore, 0.5);
        $trigramScore = min($trigramScore, 0.5);

        $this->debugInfo['ngram'] = [
            'bigram_matches' => $bigramMatches,
            'trigram_matches' => $trigramMatches,
            'bigram_score' => round($bigramScore, 4),
            'trigram_score' => round($trigramScore, 4),
        ];

        return [
            'bigram_matches' => $bigramMatches,
            'trigram_matches' => $trigramMatches,
            'bigram_score' => $bigramScore,
            'trigram_score' => $trigramScore,
            'total_ngram_score' => $bigramScore + $trigramScore,
        ];
    }

    /**
     * Get combined phrase and n-gram boosting score
     * This is the main method to call for phrase-based ranking enhancement
     */
    public function getPhraseBoostScore(string $query, array $document): array
    {
        // Step 1: Detect important phrases in query
        $detectedPhrases = $this->detectPhrases($query);

        // Step 2: Calculate phrase match score
        $phraseScore = [];
        if (!empty($detectedPhrases)) {
            $phraseScore = $this->calculatePhraseScore($query, $detectedPhrases, $document);
        }

        // Step 3: Calculate n-gram overlap
        $ngramResult = $this->calculateNgramOverlap($query, $document);

        // Step 4: Combine scores
        $phraseBoost = $phraseScore['total_bonus'] ?? 0;
        $ngramBoost = $ngramResult['total_ngram_score'] ?? 0;
        
        // Total boost (with diminishing returns)
        $totalBoost = $phraseBoost + $ngramBoost;
        $totalBoost = min($totalBoost, 1.0); // Cap at 1.0

        return [
            'phrase_boost' => $phraseBoost,
            'ngram_boost' => $ngramBoost,
            'total_boost' => $totalBoost,
            'detected_phrases' => $detectedPhrases,
            'phrase_matches' => $phraseScore['phrase_matches'] ?? [],
            'title_phrase_matches' => $phraseScore['title_phrase_matches'] ?? [],
            'bigram_matches' => $ngramResult['bigram_matches'],
            'trigram_matches' => $ngramResult['trigram_matches'],
            'has_important_phrase' => !empty($detectedPhrases),
            'debug_info' => $this->debugInfo,
        ];
    }

    /**
     * Check if query contains any important phrase
     */
    public function hasImportantPhrase(string $query): bool
    {
        $phrases = $this->detectPhrases($query);
        return !empty($phrases);
    }

    /**
     * Get all important phrases (for debugging/testing)
     */
    public function getAllPhrases(): array
    {
        return $this->importantPhrases;
    }

    /**
     * Get phrases by category
     */
    public function getPhrasesByCategory(string $category): array
    {
        return $this->phraseCategories[$category] ?? [];
    }

    /**
     * Add custom phrase (useful for domain-specific customization)
     */
    public function addPhrase(string $phrase, ?string $category = null): void
    {
        if (!in_array($phrase, $this->importantPhrases)) {
            $this->importantPhrases[] = $phrase;
        }

        if ($category !== null) {
            if (!isset($this->phraseCategories[$category])) {
                $this->phraseCategories[$category] = [];
            }
            if (!in_array($phrase, $this->phraseCategories[$category])) {
                $this->phraseCategories[$category][] = $phrase;
            }
        }
    }

    /**
     * Get debug information
     */
    public function getDebugInfo(): array
    {
        return $this->debugInfo;
    }

    /**
     * Clear debug information
     */
    public function clearDebugInfo(): void
    {
        $this->debugInfo = [];
    }
}