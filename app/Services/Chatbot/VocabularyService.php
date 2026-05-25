<?php

namespace App\Services\Chatbot;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * VocabularyService - Dynamic vocabulary-based query normalization
 * 
 * This service extracts vocabulary from articles (titles, keywords, content, categories)
 * and uses it for intelligent typo correction using Levenshtein distance.
 * 
 * Features:
 * - Automatic vocabulary extraction from article database
 * - Adaptive Levenshtein distance-based typo correction (threshold based on word length)
 * - Repeated character normalization (e.g., virusssss -> virus)
 * - Configurable similarity thresholds (lower for long technical terms)
 * - Hybrid mode combining curated and dynamic corrections
 * - Comprehensive debug logging
 * - Caching for performance
 */
class VocabularyService
{
    private const CACHE_KEY = 'chatbot_vocabulary';
    private const CACHE_TTL = 3600; // 1 hour
    
    // Minimum similarity threshold (0.0 to 1.0)
    // Only correct if similarity >= this threshold
    private const MIN_SIMILARITY = 0.70;
    
    // Minimum similarity threshold for long technical terms (>8 chars)
    private const MIN_SIMILARITY_LONG_WORDS = 0.65;
    
    // Minimum word length to consider for correction
    private const MIN_WORD_LENGTH = 3;
    
    // Maximum repeated character occurrences (anything above gets compressed to 1)
    // Example: virusssss -> virus (not viruss)
    private const MAX_REPEATED_CHARS = 1;
    
    /**
     * Get adaptive Levenshtein distance threshold based on word length
     * 
     * Short words (<=5): max distance = 1 (stricter)
     * Medium words (6-8): max distance = 2
     * Long words (>8): max distance = 3 (more tolerant)
     * 
     * @param string $word The word to check
     * @return int Maximum allowed Levenshtein distance
     */
    private function getAdaptiveMaxDistance(string $word): int
    {
        $length = mb_strlen($word);
        
        if ($length <= 5) {
            return 1;  // Stricter for short words
        } elseif ($length <= 8) {
            return 2;  // Standard for medium words
        } else {
            return 3;  // More tolerant for long technical terms
        }
    }
    
    /**
     * Get minimum similarity threshold based on word length
     * 
     * Long technical terms (>8 chars) get a lower threshold (70%)
     * to allow for more flexible matching
     * 
     * @param string $word The word to check
     * @return float Minimum similarity threshold (0.0 to 1.0)
     */
    private function getAdaptiveMinSimilarity(string $word): float
    {
        $length = mb_strlen($word);
        
        if ($length > 8) {
            return self::MIN_SIMILARITY_LONG_WORDS;  // 0.65 for long words
        }
        
        return self::MIN_SIMILARITY;  // 0.70 for others
    }
    
    /**
     * Normalize repeated characters in a token
     * 
     * Compresses repeated characters above MAX_REPEATED_CHARS occurrences.
     * Examples:
     *   virusssss -> virus
     *   wifiii -> wifi
     *   lemottt -> lemot
     *   errorrrr -> error
     * 
     * @param string $token The token to normalize
     * @return string The normalized token with compressed repeated characters
     */
    public function normalizeRepeatedChars(string $token): string
    {
        // Use regex to find repeated characters and compress them
        // Pattern: matches any character followed by the same character 2+ more times
        // Replacement: keeps only MAX_REPEATED_CHARS occurrences
        $pattern = '/(.)\1{2,}/';
        
        $result = preg_replace_callback($pattern, function ($matches) {
            $char = $matches[1];
            // Keep only MAX_REPEATED_CHARS occurrences
            return str_repeat($char, self::MAX_REPEATED_CHARS);
        }, $token);
        
        return $result ?? $token;
    }
    
    // Curated typo map for high-priority domain-specific terms
    // These are manually maintained for critical IT terms
    private array $curatedTypoMap = [
        // Ransomware/Malware
        'ransomwre' => 'ransomware',
        'ransomware' => 'ransomware',
        'malwere' => 'malware',
        'malwre' => 'malware',
        'trojan' => 'trojan',
        'trojan horse' => 'trojan',
        
        // Virus
        'virusss' => 'virus',
        'viruss' => 'virus',
        'viruse' => 'virus',
        'virus' => 'virus',
        
        // WiFi
        'wfi' => 'wifi',
        'wiifi' => 'wifi',
        'wfii' => 'wifi',
        'wifii' => 'wifi',
        'wi-fi' => 'wifi',
        
        // Printer
        'pritner' => 'printer',
        'prnter' => 'printer',
        'printter' => 'printer',
        'printe' => 'printer',
        'priner' => 'printer',
        'priter' => 'printer',
        'prinetr' => 'printer',
        'pirnter' => 'printer',
        
        // Computer
        'kompter' => 'komputer',
        'komputr' => 'komputer',
        'kompoter' => 'komputer',
        'komputerr' => 'komputer',
        'komputwr' => 'komputer',
        'komputer' => 'komputer',
        
        // Internet
        'intenet' => 'internet',
        'internrt' => 'internet',
        'intrnet' => 'internet',
        'intrnt' => 'internet',
        
        // Email
        'emai' => 'email',
        'emaill' => 'email',
        'emil' => 'email',
        'emial' => 'email',
        'eamil' => 'email',
        'emal' => 'email',
        'e-mail' => 'email',
    ];
    
    // Dynamic vocabulary extracted from articles
    private ?array $vocabulary = null;
    
    /**
     * Load vocabulary from cache or build if not exists
     * 
     * NEVER returns null - always returns an array (empty if no data available)
     * 
     * @return array Array of unique normalized words
     */
    public function loadVocabulary(): array
    {
        // If already loaded in memory, return it
        if (is_array($this->vocabulary)) {
            return $this->vocabulary;
        }
        
        // Try to load from cache
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && !empty($cached)) {
            $this->vocabulary = $cached;
            Log::debug('Vocabulary loaded from cache', ['word_count' => count($cached)]);
            return $this->vocabulary;
        }
        
        // Cache miss or empty - rebuild vocabulary
        Log::info('Vocabulary cache miss or empty, rebuilding...');
        return $this->rebuildVocabulary();
    }
    
    /**
     * Rebuild vocabulary from articles database
     * 
     * Extracts words from:
     * - Article titles
     * - Article keywords
     * - Article content (first 500 words for performance)
     * - Category names
     * 
     * @return array Array of unique normalized words (never null)
     */
    private function rebuildVocabulary(): array
    {
        $vocabulary = [];
        
        try {
            // Extract from article titles
            $articles = Article::where('is_published', true)
                ->where('publish_status', 'approved')
                ->get(['title', 'keywords', 'content']);
            
            foreach ($articles as $article) {
                // Extract from title
                $titleWords = $this->extractWords($article->title);
                $vocabulary = array_merge($vocabulary, $titleWords);
                
                // Extract from keywords
                if ($article->keywords) {
                    $keywordWords = $this->extractWords($article->keywords);
                    $vocabulary = array_merge($vocabulary, $keywordWords);
                }
                
                // Extract from content (limit to first 500 words for performance)
                if ($article->content) {
                    $contentWords = $this->extractWords($article->content, 500);
                    $vocabulary = array_merge($vocabulary, $contentWords);
                }
            }
            
            // Extract from category names
            $categories = Category::all(['name']);
            foreach ($categories as $category) {
                $categoryWords = $this->extractWords($category->name);
                $vocabulary = array_merge($vocabulary, $categoryWords);
            }
        } catch (\Exception $e) {
            Log::error('Error building vocabulary from database', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        // Normalize and deduplicate
        $vocabulary = array_unique($vocabulary);
        $vocabulary = array_filter($vocabulary, fn($word) => mb_strlen($word) >= self::MIN_WORD_LENGTH);
        
        // Sort by word length (shorter words first for better matching)
        usort($vocabulary, fn($a, $b) => mb_strlen($a) <=> mb_strlen($b));
        
        // Cache the vocabulary (even if empty, to avoid repeated rebuilds)
        Cache::put(self::CACHE_KEY, $vocabulary, self::CACHE_TTL);
        
        $this->vocabulary = $vocabulary;
        
        if (empty($vocabulary)) {
            Log::warning('Vocabulary rebuilt but still empty - no articles or categories available');
        } else {
            Log::info('Vocabulary rebuilt successfully', [
                'word_count' => count($vocabulary),
                'sample_words' => array_slice($vocabulary, 0, 20)
            ]);
        }
        
        return $vocabulary;
    }
    
    /**
     * Build vocabulary from articles database (alias for rebuildVocabulary for backward compatibility)
     * 
     * @return array Array of unique normalized words
     */
    public function buildVocabulary(): array
    {
        return $this->rebuildVocabulary();
    }
    
    /**
     * Extract words from text
     * 
     * @param string $text The text to extract words from
     * @param int $maxWords Maximum number of words to extract (0 = unlimited)
     * @return array Array of normalized words
     */
    private function extractWords(string $text, int $maxWords = 0): array
    {
        // Convert to lowercase
        $text = mb_strtolower($text);
        
        // Remove punctuation and special characters
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // Split into words
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Limit words if needed
        if ($maxWords > 0 && count($words) > $maxWords) {
            $words = array_slice($words, 0, $maxWords);
        }
        
        return $words;
    }
    
    /**
     * Normalize a query by correcting typos using dynamic vocabulary
     * 
     * Pipeline:
     * 1. Raw query tokens
     * 2. Repeated-character normalization (virusssss -> virus)
     * 3. Curated typo map lookup
     * 4. Dynamic vocabulary correction (adaptive Levenshtein)
     * 
     * @param string $query The input query
     * @return array ['original' => string, 'normalized' => string, 'corrections' => array]
     */
    public function normalizeQuery(string $query): array
    {
        // Load vocabulary using safe method that never returns null
        $this->loadVocabulary();
        
        // SAFETY CHECK: Ensure vocabulary is always an array
        // This prevents in_array() crashes when vocabulary is null
        if (!is_array($this->vocabulary) || empty($this->vocabulary)) {
            Log::warning('Vocabulary empty - skipping typo normalization', [
                'query' => $query,
                'vocabulary_type' => gettype($this->vocabulary),
                'vocabulary_count' => is_array($this->vocabulary) ? count($this->vocabulary) : 'null'
            ]);
            
            // Return original query without normalization
            return [
                'original' => $query,
                'normalized' => $query,
                'corrections' => []
            ];
        }
        
        $tokens = preg_split('/\s+/', $query);
        $correctedTokens = [];
        $corrections = [];
        
        foreach ($tokens as $token) {
            $originalToken = $token;
            $lowerToken = mb_strtolower($token);
            
            // Skip very short tokens
            if (mb_strlen($lowerToken) < self::MIN_WORD_LENGTH) {
                $correctedTokens[] = $token;
                continue;
            }
            
            // STEP 1: Normalize repeated characters BEFORE any other correction
            // Example: virusssss -> virus, wifiii -> wifi, lemottt -> lemot
            $compressedToken = $this->normalizeRepeatedChars($lowerToken);
            
            // Log the compression for debugging
            if ($compressedToken !== $lowerToken) {
                Log::debug('Repeated character normalization', [
                    'original_token' => $lowerToken,
                    'compressed_token' => $compressedToken
                ]);
            }
            
            // Check if compressed token is already in vocabulary
            if (in_array($compressedToken, $this->vocabulary)) {
                if ($compressedToken !== $lowerToken) {
                    $corrections[] = [
                        'original' => $lowerToken,
                        'compressed' => $compressedToken,
                        'corrected' => $compressedToken,
                        'source' => 'repeated_char_normalization',
                        'confidence' => 1.0
                    ];
                    $correctedTokens[] = $compressedToken;
                } else {
                    $correctedTokens[] = $token;
                }
                continue;
            }
            
            // STEP 2: Try curated typo map (on compressed token)
            if (isset($this->curatedTypoMap[$compressedToken])) {
                $corrected = $this->curatedTypoMap[$compressedToken];
                $corrections[] = [
                    'original' => $lowerToken,
                    'compressed' => $compressedToken,
                    'corrected' => $corrected,
                    'source' => 'curated',
                    'confidence' => 1.0
                ];
                $correctedTokens[] = $corrected;
                continue;
            }
            
            // STEP 3: Dynamic vocabulary correction using adaptive Levenshtein
            $bestMatch = $this->findBestMatch($compressedToken);
            
            if ($bestMatch !== null) {
                $corrections[] = [
                    'original' => $lowerToken,
                    'compressed' => $compressedToken,
                    'corrected' => $bestMatch['word'],
                    'source' => 'dynamic',
                    'confidence' => $bestMatch['similarity'],
                    'distance' => $bestMatch['distance'],
                    'max_distance_allowed' => $bestMatch['maxDistance']
                ];
                $correctedTokens[] = $bestMatch['word'];
            } else {
                $correctedTokens[] = $token;
            }
        }
        
        $normalizedQuery = implode(' ', $correctedTokens);
        
        Log::info('Query normalized', [
            'original' => $query,
            'normalized' => $normalizedQuery,
            'corrections_count' => count($corrections),
            'corrections' => $corrections
        ]);
        
        return [
            'original' => $query,
            'normalized' => $normalizedQuery,
            'corrections' => $corrections
        ];
    }
    
    /**
     * Find the best matching word from vocabulary using Levenshtein distance
     * 
     * Uses adaptive thresholds based on word length:
     * - Short words (<=5): stricter threshold (max distance = 1)
     * - Medium words (6-8): standard threshold (max distance = 2)
     * - Long words (>8): more tolerant (max distance = 3, lower similarity threshold)
     * 
     * @param string $token The token to match (should already be compressed/normalized)
     * @return array|null ['word' => string, 'distance' => int, 'similarity' => float, 'maxDistance' => int] or null
     */
    private function findBestMatch(string $token): ?array
    {
        // SAFETY: Ensure vocabulary is an array before iterating
        if (!is_array($this->vocabulary) || empty($this->vocabulary)) {
            return null;
        }
        
        // Get adaptive thresholds based on token length
        $maxDistance = $this->getAdaptiveMaxDistance($token);
        $minSimilarity = $this->getAdaptiveMinSimilarity($token);
        
        // Calculate max length difference for early filtering
        // For adaptive distance, we use a slightly larger window to avoid missing candidates
        $lengthFilterWindow = max($maxDistance + 1, 3);
        
        $bestMatch = null;
        $bestDistance = PHP_INT_MAX;
        $bestSimilarity = 0.0;
        
        foreach ($this->vocabulary as $word) {
            // Skip if word length difference is too large (unlikely match)
            if (abs(mb_strlen($word) - mb_strlen($token)) > $lengthFilterWindow) {
                continue;
            }
            
            $distance = levenshtein($token, $word);
            
            // Use adaptive max distance threshold
            if ($distance <= $maxDistance && $distance < $bestDistance) {
                $similarity = $this->calculateSimilarity($token, $word, $distance);
                
                // Use adaptive minimum similarity threshold
                if ($similarity >= $minSimilarity) {
                    $bestMatch = $word;
                    $bestDistance = $distance;
                    $bestSimilarity = $similarity;
                    
                    // If we found a very close match, we can stop early
                    if ($distance <= 1 && $similarity >= 0.90) {
                        break;
                    }
                }
            }
        }
        
        if ($bestMatch !== null) {
            Log::debug('Best match found', [
                'original_token' => $token,
                'matched_word' => $bestMatch,
                'distance' => $bestDistance,
                'max_distance_allowed' => $maxDistance,
                'similarity' => round($bestSimilarity * 100, 1) . '%',
                'min_similarity_required' => round($minSimilarity * 100, 1) . '%'
            ]);
            
            return [
                'word' => $bestMatch,
                'distance' => $bestDistance,
                'similarity' => $bestSimilarity,
                'maxDistance' => $maxDistance
            ];
        }
        
        Log::debug('No match found for token', [
            'original_token' => $token,
            'max_distance_checked' => $maxDistance,
            'min_similarity_required' => round($minSimilarity * 100, 1) . '%'
        ]);
        
        return null;
    }
    
    /**
     * Calculate similarity between two strings
     * 
     * @param string $str1 First string
     * @param string $str2 Second string
     * @param int $distance Levenshtein distance
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculateSimilarity(string $str1, string $str2, int $distance): float
    {
        $maxLen = max(mb_strlen($str1), mb_strlen($str2));
        
        if ($maxLen === 0) {
            return 1.0;
        }
        
        return 1.0 - ($distance / $maxLen);
    }
    
    /**
     * Get vocabulary statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        // Use loadVocabulary() which never returns null
        $this->loadVocabulary();
        
        // SAFETY: Ensure vocabulary is an array
        $vocabulary = is_array($this->vocabulary) ? $this->vocabulary : [];
        
        $domainTerms = [
            'wifi', 'internet', 'jaringan', 'printer', 'komputer', 'email',
            'website', 'aplikasi', 'akun', 'virus', 'malware', 'ransomware',
            'trojan', 'lemot', 'error', 'bsod', 'hang', 'crash'
        ];
        
        $domainCount = 0;
        foreach ($vocabulary as $word) {
            if (in_array($word, $domainTerms)) {
                $domainCount++;
            }
        }
        
        return [
            'total_words' => count($vocabulary),
            'domain_terms' => $domainCount,
            'curated_typos' => count($this->curatedTypoMap),
            'cache_key' => self::CACHE_KEY,
            'cache_ttl' => self::CACHE_TTL,
        ];
    }
    
    /**
     * Clear vocabulary cache
     * 
     * Useful when articles are updated
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->vocabulary = null;
        
        Log::info('Vocabulary cache cleared');
    }
    
    /**
     * Get the curated typo map
     * 
     * @return array
     */
    public function getCuratedTypoMap(): array
    {
        return $this->curatedTypoMap;
    }
    
    /**
     * Add a new curated typo mapping
     * 
     * @param string $typo The typo
     * @param string $correct The correct spelling
     */
    public function addCuratedTypo(string $typo, string $correct): void
    {
        $this->curatedTypoMap[mb_strtolower($typo)] = mb_strtolower($correct);
    }
    
    /**
     * Check if a word needs correction
     * 
     * @param string $word The word to check
     * @return bool
     */
    public function needsCorrection(string $word): bool
    {
        // Use loadVocabulary() which never returns null
        $this->loadVocabulary();
        
        // SAFETY: Ensure vocabulary is an array
        $vocabulary = is_array($this->vocabulary) ? $this->vocabulary : [];
        
        $lowerWord = mb_strtolower($word);
        
        // Check if already in vocabulary (safe - vocabulary is guaranteed to be an array)
        if (in_array($lowerWord, $vocabulary)) {
            return false;
        }
        
        // Check curated map
        if (isset($this->curatedTypoMap[$lowerWord])) {
            return true;
        }
        
        // Check if close to any vocabulary word
        $bestMatch = $this->findBestMatch($lowerWord);
        return $bestMatch !== null;
    }
}