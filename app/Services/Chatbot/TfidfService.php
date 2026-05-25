<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;

/**
 * TfidfService - TF-IDF calculation with low-priority term weight reduction
 * 
 * This service calculates TF-IDF scores while reducing the influence of
 * generic IT helpdesk terms like 'cara', 'mengatasi', 'solusi', etc.
 */
class TfidfService
{
    private const IDF_CACHE_KEY = 'chatbot:tfidf:idf_scores';
    private const IDF_CACHE_TTL = 86400;
    
    /**
     * Low priority terms that should have reduced weight in TF-IDF
     * These generic terms are too common in helpdesk articles and
     * should not dominate the ranking
     * 
     * Category 1: Generic instructional words (cara, mengatasi, etc.)
     * Category 2: Generic technical/device words (pc, laptop, komputer, etc.)
     *              These are too common and don't indicate specific intent
     */
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
        
        // Generic technical/device words - too common, don't indicate specific intent
        'pc',
        'laptop',
        'komputer',
        'aplikasi',
        'error',
        'masalah',
        'sistem',
        'program',
        'software',
        'hardware',
        'teknologi',
        'digital',
        'online',
        'internet',
        'jaringan',
        'data',
        'file',
        'dokumen',
    ];
    
    /**
     * Weight multiplier for low-priority terms (0.1 = 90% reduction)
     */
    private const LOW_PRIORITY_WEIGHT = 0.1;

    private PreprocessingService $preprocessor;

    public function __construct(PreprocessingService $preprocessor)
    {
        $this->preprocessor = $preprocessor;
    }
    
    /**
     * Check if a term is a low-priority generic term
     */
    private function isLowPriorityTerm(string $term): bool
    {
        return in_array(mb_strtolower($term), $this->lowPriorityTerms);
    }

    /**
     * Hitung TF (Term Frequency) untuk sebuah dokumen
     */
    public function calculateTF(array $termFrequency): array
    {
        $totalTerms = array_sum($termFrequency);

        if ($totalTerms === 0) {
            return [];
        }

        $tf = [];
        foreach ($termFrequency as $term => $count) {
            $tf[$term] = $count / $totalTerms;
        }

        return $tf;
    }

    /**
     * Hitung IDF (Inverse Document Frequency) untuk semua term
     * Menggunakan smoothed IDF untuk menghindari nilai 0 ketika term ada di semua dokumen
     * Formula: log(1 + totalDocs / (1 + docCount)) + 1
     * Ini memastikan IDF selalu > 0 bahkan ketika term muncul di semua dokumen
     */
    public function calculateIDF(array $documentTermFrequencies): array
    {
        $totalDocs = count($documentTermFrequencies);

        if ($totalDocs === 0) {
            return [];
        }

        $documentFrequency = [];

        foreach ($documentTermFrequencies as $docId => $termFreq) {
            foreach (array_keys($termFreq) as $term) {
                $documentFrequency[$term] = ($documentFrequency[$term] ?? 0) + 1;
            }
        }

        $idf = [];
        foreach ($documentFrequency as $term => $docCount) {
            // Smoothed IDF: log(1 + totalDocs / (1 + docCount)) + 1
            // This ensures IDF is always positive, even when term appears in all documents
            // The +1 at the end ensures rare terms still get higher weights
            $idf[$term] = log(1 + $totalDocs / (1 + $docCount)) + 1;
        }

        return $idf;
    }

    /**
     * Hitung TF-IDF untuk sebuah dokumen
     * 
     * Low-priority generic terms (cara, mengatasi, etc.) receive
     * significantly reduced weight to prevent them from dominating
     * the ranking over domain-specific terms.
     */
    public function calculateTFIDF(array $tf, array $idf): array
    {
        $tfidf = [];

        foreach ($tf as $term => $tfValue) {
            $idfValue = $idf[$term] ?? 0;
            $score = $tfValue * $idfValue;
            
            // Apply weight reduction for low-priority generic terms
            if ($this->isLowPriorityTerm($term)) {
                $score *= self::LOW_PRIORITY_WEIGHT;
            }
            
            $tfidf[$term] = $score;
        }

        return $tfidf;
    }

    /**
     * Bangun vektor TF-IDF untuk sekumpulan dokumen
     */
    public function buildTfidfVectors(array $documents): array
    {
        $documentTermFrequencies = [];

        foreach ($documents as $docId => $doc) {
            $preprocessed = $this->preprocessor->preprocessDocument($doc['text']);
            $documentTermFrequencies[$docId] = $preprocessed['frequency'];
        }

        $idf = $this->calculateIDF($documentTermFrequencies);

        $vectors = [];
        foreach ($documentTermFrequencies as $docId => $termFreq) {
            $tf = $this->calculateTF($termFreq);
            $vectors[$docId] = $this->calculateTFIDF($tf, $idf);
        }

        return [
            'vectors' => $vectors,
            'idf' => $idf,
            'docCount' => count($documents),
        ];
    }

    /**
     * Hitung TF-IDF untuk query menggunakan IDF yang sudah ada
     * BUG FIX 1: Apply typo correction BEFORE tokenization/stemming
     * 
     * Low-priority generic terms (cara, mengatasi, etc.) receive
     * significantly reduced weight to prevent them from dominating
     * the ranking over domain-specific terms.
     */
    public function calculateQueryTFIDF(string $query, array $idf): array
    {
        // BUG FIX 1: Use preprocess with typo correction enabled (applyTypoCorrection = true)
        $tokens = $this->preprocessor->preprocess($query, true); // true = apply typo correction
        
        // Calculate term frequency
        $frequency = [];
        foreach ($tokens as $token) {
            $frequency[$token] = ($frequency[$token] ?? 0) + 1;
        }
        
        $tf = $this->calculateTF($frequency);

        $tfidf = [];
        foreach ($tf as $term => $tfValue) {
            $idfValue = $idf[$term] ?? 0;
            $score = $tfValue * $idfValue;
            
            // Apply weight reduction for low-priority generic terms
            if ($this->isLowPriorityTerm($term)) {
                $score *= self::LOW_PRIORITY_WEIGHT;
            }
            
            $tfidf[$term] = $score;
        }

        return $tfidf;
    }

    /**
     * Dapatkan IDF dari cache atau hitung baru
     */
    public function getOrComputeIDF(array $documents): array
    {
        $cached = Cache::get(self::IDF_CACHE_KEY);

        if ($cached !== null) {
            return $cached;
        }

        $result = $this->buildTfidfVectors($documents);
        Cache::put(self::IDF_CACHE_KEY, $result['idf'], self::IDF_CACHE_TTL);

        return $result['idf'];
    }

    /**
     * Clear IDF cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::IDF_CACHE_KEY);
    }

    /**
     * Normalisasi vektor (L2 normalization)
     */
    public function normalizeVector(array $vector): array
    {
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));

        if ($magnitude === 0) {
            return $vector;
        }

        $normalized = [];
        foreach ($vector as $term => $value) {
            $normalized[$term] = $value / $magnitude;
        }

        return $normalized;
    }

    /**
     * Dapatkan semua term dari sekumpulan vektor
     */
    public function getAllTerms(array $vectors): array
    {
        $terms = [];

        foreach ($vectors as $vector) {
            $terms = array_merge($terms, array_keys($vector));
        }

        return array_unique($terms);
    }

    /**
     * Konversi vektor sparse ke dense vector berdasarkan vocabulary
     */
    public function toDenseVector(array $sparseVector, array $vocabulary): array
    {
        $dense = [];

        foreach ($vocabulary as $term) {
            $dense[] = $sparseVector[$term] ?? 0;
        }

        return $dense;
    }
}