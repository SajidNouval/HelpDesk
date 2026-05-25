<?php

namespace App\Services\Chatbot;

class CosineSimilarityService
{
    /**
     * Hitung cosine similarity antara dua vektor sparse
     */
    public function calculate(array $vectorA, array $vectorB): float
    {
        if (empty($vectorA) || empty($vectorB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        $allTerms = array_unique(array_merge(array_keys($vectorA), array_keys($vectorB)));

        foreach ($allTerms as $term) {
            $aValue = $vectorA[$term] ?? 0;
            $bValue = $vectorB[$term] ?? 0;

            $dotProduct += $aValue * $bValue;
            $magnitudeA += $aValue * $aValue;
            $magnitudeB += $bValue * $bValue;
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Hitung cosine similarity antara query dan sekumpulan dokumen
     */
    public function calculateBatch(array $queryVector, array $documentVectors): array
    {
        $similarities = [];

        foreach ($documentVectors as $docId => $docVector) {
            $similarities[$docId] = $this->calculate($queryVector, $docVector);
        }

        return $similarities;
    }

    /**
     * Ranking dokumen berdasarkan similarity score
     */
    public function rankDocuments(array $similarities, int $limit = 5): array
    {
        arsort($similarities);
        return array_slice(array_keys($similarities), 0, $limit, true);
    }

    /**
     * Dapatkan top N dokumen dengan similarity tertinggi
     */
    public function getTopDocuments(array $similarities, int $limit = 5): array
    {
        arsort($similarities);

        $topIds = array_slice(array_keys($similarities), 0, $limit, true);
        $ranked = array_slice($similarities, 0, $limit, true);

        return [
            'ranked' => $ranked,
            'top' => $topIds,
        ];
    }

    /**
     * Cek apakah similarity score melewati threshold
     */
    public function meetsThreshold(float $similarity, float $threshold): bool
    {
        return $similarity >= $threshold;
    }

    /**
     * Normalisasi skor similarity ke rentang 0-1
     */
    public function normalizeScore(float $similarity): float
    {
        return max(0.0, min(1.0, $similarity));
    }

    /**
     * Hitung similarity dengan field boosting
     */
    public function calculateWithBoost(array $queryVector, array $docVector, array $boostFactors = []): float
    {
        if (empty($queryVector) || empty($docVector)) {
            return 0.0;
        }

        $baseSimilarity = $this->calculate($queryVector, $docVector);

        if (empty($boostFactors)) {
            return $baseSimilarity;
        }

        $boostBonus = 0.0;

        foreach ($boostFactors as $term => $boost) {
            if (isset($queryVector[$term]) && isset($docVector[$term])) {
                $boostBonus += $queryVector[$term] * $docVector[$term] * ($boost - 1);
            }
        }

        return $baseSimilarity + $boostBonus;
    }
}