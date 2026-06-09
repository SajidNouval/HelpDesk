<?php

namespace App\Services\Chatbot;

/**
 * =========================================================================
 * SERVICE COSINE SIMILARITY
 * =========================================================================
 *
 * Layanan ini bertanggung jawab untuk menghitung tingkat kemiripan (similarity)
 * antara dua vektor menggunakan metode Cosine Similarity.
 *
 * Cosine Similarity mengukur sudut antara dua vektor dalam ruang berdimensi-n.
 * Nilai yang dihasilkan berada pada rentang 0 hingga 1, di mana:
 * - Nilai 1 berarti kedua vektor identik (sudut = 0°)
 * - Nilai 0 berarti kedua vektor tidak memiliki kesamaan (sudut = 90°)
 *
 * Fungsi utama layanan ini:
 * - Menghitung kemiripan antar dua vektor sparse (pairwise).
 * - Menghitung kemiripan satu query terhadap banyak dokumen sekaligus (batch).
 * - Meranking dokumen berdasarkan skor kemiripan.
 * - Memberikan boost pada term tertentu untuk meningkatkan relevansi.
 *
 * Digunakan oleh:
 * - AdvancedRetrievalService (untuk hybrid ranking)
 * - ChatbotRetrievalService (untuk reranking ringan)
 */
class CosineSimilarityService
{
    /**
     * =========================================================================
     * 1. METODE CALCULATE
     * =========================================================================
     *
     * Fungsi:
     * Menghitung nilai Cosine Similarity antara dua vektor sparse.
     *
     * Alur Proses:
     * 1. Menerima dua vektor sparse.
     * 2. Memeriksa apakah salah satu vektor kosong.
     * 3. Menggabungkan seluruh term unik dari kedua vektor.
     * 4. Menghitung dot product dan magnitude untuk setiap term.
     * 5. Mengembalikan hasil bagi dot product dengan perkalian kedua magnitude.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - float nilai similarity antara 0.0 (tidak mirip) hingga 1.0 (identik)
     */
    public function calculate(array $vectorA, array $vectorB): float
    {
        // Jika salah satu vektor kosong, kemiripan pasti 0
        if (empty($vectorA) || empty($vectorB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        // Gabungkan semua term unik dari kedua vektor agar dimensi konsisten
        $allTerms = array_unique(array_merge(array_keys($vectorA), array_keys($vectorB)));

        // Iterasi setiap term untuk menghitung dot product dan magnitude
        foreach ($allTerms as $term) {
            $aValue = $vectorA[$term] ?? 0;
            $bValue = $vectorB[$term] ?? 0;

            $dotProduct += $aValue * $bValue;
            $magnitudeA += $aValue * $aValue;
            $magnitudeB += $bValue * $bValue;
        }

        // Hitung akar kuadrat untuk mendapatkan panjang (norm) vektor
        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        // Hindari pembagian dengan nol jika salah satu vektor bernilai nol
        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        // Kembalikan nilai cosine similarity
        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * =========================================================================
     * 1. METODE CALCULATE BATCH
     * =========================================================================
     *
     * Fungsi:
     * Menghitung Cosine Similarity antara satu vektor query dan sekumpulan vektor dokumen.
     *
     * Alur Proses:
     * 1. Menerima vektor query dan array vektor dokumen.
     * 2. Iterasi seluruh dokumen dalam koleksi.
     * 3. Hitung similarity antara query dan setiap dokumen.
     * 4. Simpan hasil dengan kunci berupa ID dokumen.
     * 5. Mengembalikan array similarity.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array [docId => float similarity]
     */
    public function calculateBatch(array $queryVector, array $documentVectors): array
    {
        $similarities = [];

        // Hitung similarity query terhadap setiap dokumen satu per satu
        foreach ($documentVectors as $docId => $docVector) {
            $similarities[$docId] = $this->calculate($queryVector, $docVector);
        }

        return $similarities;
    }

    /**
     * =========================================================================
     * 1. METODE RANK DOCUMENTS
     * =========================================================================
     *
     * Fungsi:
     * Mengurutkan dokumen berdasarkan skor similarity tertinggi.
     *
     * Alur Proses:
     * 1. Menerima array similarity dan batas jumlah dokumen.
     * 2. Urutkan array similarity secara descending.
     * 3. Ambil ID dokumen sebanyak batas teratas.
     * 4. Mengembalikan daftar ID dokumen yang sudah terurut.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array daftar ID dokumen yang sudah diurutkan dari similarity tertinggi
     */
    public function rankDocuments(array $similarities, int $limit = 5): array
    {
        // Urutkan descending berdasarkan nilai similarity
        arsort($similarities);

        // Kembalikan hanya key (ID dokumen), bukan nilai similarity-nya
        return array_slice(array_keys($similarities), 0, $limit, true);
    }

    /**
     * =========================================================================
     * 1. METODE GET TOP DOCUMENTS
     * =========================================================================
     *
     * Fungsi:
     * Mengambil N dokumen dengan skor similarity tertinggi beserta nilai similarity-nya.
     *
     * Alur Proses:
     * 1. Menerima array similarity dan batas jumlah dokumen.
     * 2. Urutkan similarity secara descending.
     * 3. Ambil top N ID dokumen.
     * 4. Ambil top N skor similarity.
     * 5. Mengembalikan keduanya dalam satu array terstruktur.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array ['ranked' => [docId => skor], 'top' => [docId, ...]]
     */
    public function getTopDocuments(array $similarities, int $limit = 5): array
    {
        // Urutkan descending berdasarkan nilai similarity
        arsort($similarities);

        // Ambil ID dokumen teratas
        $topIds = array_slice(array_keys($similarities), 0, $limit, true);

        // Ambil pasangan ID → skor untuk N dokumen teratas
        $ranked = array_slice($similarities, 0, $limit, true);

        return [
            'ranked' => $ranked,
            'top'    => $topIds,
        ];
    }

    /**
     * =========================================================================
     * 1. METODE MEETS THRESHOLD
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah nilai similarity sudah memenuhi batas minimum.
     *
     * Alur Proses:
     * 1. Menerima nilai similarity dan nilai threshold.
     * 2. Membandingkan similarity dengan threshold.
     * 3. Mengembalikan true jika similarity >= threshold.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool true jika similarity >= ambang, false jika di bawah ambang
     */
    public function meetsThreshold(float $similarity, float $threshold): bool
    {
        return $similarity >= $threshold;
    }

    /**
     * =========================================================================
     * 1. METODE NORMALIZE SCORE
     * =========================================================================
     *
     * Fungsi:
     * Memastikan nilai similarity berada dalam rentang valid 0.0 hingga 1.0.
     *
     * Alur Proses:
     * 1. Menerima nilai similarity mentah.
     * 2. Menjepit nilai ke rentang [0.0, 1.0] menggunakan max dan min.
     * 3. Mengembalikan nilai yang sudah dinormalisasi.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - float nilai similarity yang sudah dinormalisasi ke [0.0, 1.0]
     */
    public function normalizeScore(float $similarity): float
    {
        return max(0.0, min(1.0, $similarity));
    }

    /**
     * =========================================================================
     * 1. METODE CALCULATE WITH BOOST
     * =========================================================================
     *
     * Fungsi:
     * Menghitung Cosine Similarity dengan tambahan boost untuk term-term tertentu.
     *
     * Alur Proses:
     * 1. Menerima vektor query, vektor dokumen, dan faktor boost.
     * 2. Hitung similarity dasar menggunakan calculate().
     * 3. Jika tidak ada boost factor, kembalikan similarity dasar.
     * 4. Untuk setiap term yang memiliki boost dan muncul di kedua vektor, hitung bonus.
     * 5. Mengembalikan similarity dasar ditambah total bonus.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - float nilai similarity yang sudah ditingkatkan dengan boost
     */
    public function calculateWithBoost(array $queryVector, array $docVector, array $boostFactors = []): float
    {
        // Jika salah satu vektor kosong, kemiripan pasti 0
        if (empty($queryVector) || empty($docVector)) {
            return 0.0;
        }

        // Hitung similarity dasar terlebih dahulu
        $baseSimilarity = $this->calculate($queryVector, $docVector);

        // Jika tidak ada boost factor, langsung kembalikan similarity dasar
        if (empty($boostFactors)) {
            return $baseSimilarity;
        }

        $boostBonus = 0.0;

        // Hitung bonus boost untuk setiap term yang ada di kedua vektor
        foreach ($boostFactors as $term => $boost) {
            if (isset($queryVector[$term]) && isset($docVector[$term])) {
                $boostBonus += $queryVector[$term] * $docVector[$term] * ($boost - 1);
            }
        }

        return $baseSimilarity + $boostBonus;
    }
}