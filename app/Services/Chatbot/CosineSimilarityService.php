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
     * 1. Fungsi hitung()
     *
     * Fungsi ini menghitung nilai Cosine Similarity antara dua vektor sparse.
     * Vektor sparse adalah representasi di mana hanya term yang muncul yang disimpan
     * (bukan seluruh dimensi vocabulary).
     *
     * Rumus yang digunakan:
     * cosine_sim(A, B) = (A · B) / (||A|| × ||B||)
     * di mana A · B adalah dot product dan ||A||, ||B|| adalah magnitude vektor.
     *
     * Alur proses:
     * 1. Memeriksa apakah salah satu vektor kosong — langsung kembalikan 0.
     * 2. Menggabungkan seluruh term unik dari kedua vektor.
     * 3. Menghitung dot product dan magnitude untuk setiap term.
     * 4. Mengembalikan hasil bagi dot product dengan perkalian kedua magnitude.
     *
     * Parameter:
     * - array $vectorA  : Vektor pertama (biasanya vektor query TF-IDF)
     * - array $vectorB  : Vektor kedua (biasanya vektor dokumen TF-IDF)
     *
     * Kembalikan:
     * - float : Nilai similarity antara 0.0 (tidak mirip) hingga 1.0 (identik)
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

        // 1.1 Gabungkan semua term unik dari kedua vektor agar dimensi konsisten
        $allTerms = array_unique(array_merge(array_keys($vectorA), array_keys($vectorB)));

        // 1.2 Iterasi setiap term untuk menghitung dot product dan magnitude
        // Jika term tidak ada di salah satu vektor, nilainya dianggap 0
        foreach ($allTerms as $term) {
            $aValue = $vectorA[$term] ?? 0;
            $bValue = $vectorB[$term] ?? 0;

            $dotProduct += $aValue * $bValue;
            $magnitudeA += $aValue * $aValue;
            $magnitudeB += $bValue * $bValue;
        }

        // 1.3 Hitung akar kuadrat untuk mendapatkan panjang (norm) vektor
        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        // 1.4 Hindari pembagian dengan nol jika salah satu vektor bernilai nol
        if ($magnitudeA === 0.0 || $magnitudeB === 0.0) {
            return 0.0;
        }

        // 1.5 Kembalikan nilai cosine similarity
        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * 2. Fungsi calculateBatch()
     *
     * Fungsi ini menghitung nilai Cosine Similarity antara satu vektor query
     * dan sekumpulan vektor dokumen sekaligus (batch processing).
     *
     * Fungsi ini memanggil hitung() untuk setiap dokumen dan mengumpulkan
     * hasilnya dalam array berindeks dokumen ID.
     *
     * Alur proses:
     * 1. Iterasi seluruh dokumen dalam koleksi.
     * 2. Hitung similarity antara query dan setiap dokumen.
     * 3. Simpan hasil dengan kunci berupa ID dokumen.
     *
     * Parameter:
     * - array $queryVector     : Vektor TF-IDF dari query pengguna
     * - array $documentVectors : Array asosiatif [docId => vector] dari semua dokumen
     *
     * Kembalikan:
     * - array : Array asosiatif [docId => float similarity]
     */
    public function calculateBatch(array $queryVector, array $documentVectors): array
    {
        $similarities = [];

        // 2.1 Hitung similarity query terhadap setiap dokumen satu per satu
        foreach ($documentVectors as $docId => $docVector) {
            $similarities[$docId] = $this->calculate($queryVector, $docVector);
        }

        return $similarities;
    }

    /**
     * 3. Fungsi rankDocuments()
     *
     * Fungsi ini mengurutkan dokumen berdasarkan skor similarity tertinggi
     * dan mengembalikan daftar ID dokumen yang sudah terurut.
     *
     * Alur proses:
     * 1. Urutkan array similarity secara descending (skor tertinggi duluan).
     * 2. Ambil ID dokumen sebanyak $batas teratas.
     *
     * Parameter:
     * - array $similarities : Array asosiatif [docId => float similarity]
     * - int   $batas        : Jumlah maksimal dokumen yang dikembalikan (default: 5)
     *
     * Kembalikan:
     * - array : Daftar ID dokumen yang sudah diurutkan dari similarity tertinggi
     */
    public function rankDocuments(array $similarities, int $limit = 5): array
    {
        // 3.1 Urutkan descending berdasarkan nilai similarity
        arsort($similarities);

        // 3.2 Kembalikan hanya key (ID dokumen), bukan nilai similarity-nya
        return array_slice(array_keys($similarities), 0, $limit, true);
    }

    /**
     * 4. Fungsi getTopDocuments()
     *
     * Fungsi ini mengambil N dokumen dengan skor similarity tertinggi beserta
     * nilai similarity-nya, berbeda dengan rankDocuments() yang hanya mengembalikan ID.
     *
     * Alur proses:
     * 1. Urutkan similarity secara descending.
     * 2. Ambil top N ID dokumen.
     * 3. Ambil top N skor similarity.
     * 4. Kembalikan keduanya dalam satu array terstruktur.
     *
     * Parameter:
     * - array $similarities : Array asosiatif [docId => float similarity]
     * - int   $batas        : Jumlah dokumen top-N (default: 5)
     *
     * Kembalikan:
     * - array : ['ranked' => [docId => skor], 'top' => [docId, ...]]
     */
    public function getTopDocuments(array $similarities, int $limit = 5): array
    {
        // 4.1 Urutkan descending berdasarkan nilai similarity
        arsort($similarities);

        // 4.2 Ambil ID dokumen teratas
        $topIds = array_slice(array_keys($similarities), 0, $limit, true);

        // 4.3 Ambil pasangan ID → skor untuk N dokumen teratas
        $ranked = array_slice($similarities, 0, $limit, true);

        return [
            'ranked' => $ranked,
            'top'    => $topIds,
        ];
    }

    /**
     * 5. Fungsi meetsThreshold()
     *
     * Fungsi ini memeriksa apakah nilai similarity sudah memenuhi batas minimum
     * (ambang) yang ditentukan untuk dianggap sebagai hasil relevan.
     *
     * Parameter:
     * - float $similarity : Nilai similarity yang akan diperiksa
     * - float $ambang  : Nilai minimum yang harus dipenuhi
     *
     * Kembalikan:
     * - bool : true jika similarity >= ambang, false jika di bawah ambang
     */
    public function meetsThreshold(float $similarity, float $threshold): bool
    {
        return $similarity >= $threshold;
    }

    /**
     * 6. Fungsi normalizeScore()
     *
     * Fungsi ini memastikan nilai similarity berada dalam rentang valid 0.0 hingga 1.0.
     * Jika karena alasan tertentu skor berada di luar rentang (misalnya karena floating
     * point error), fungsi ini akan menjepit nilainya ke batas yang valid.
     *
     * Parameter:
     * - float $similarity : Nilai similarity mentah
     *
     * Kembalikan:
     * - float : Nilai similarity yang sudah dinormalisasi ke [0.0, 1.0]
     */
    public function normalizeScore(float $similarity): float
    {
        return max(0.0, min(1.0, $similarity));
    }

    /**
     * 7. Fungsi calculateWithBoost()
     *
     * Fungsi ini menghitung Cosine Similarity dengan tambahan boost untuk term-term
     * tertentu yang dianggap lebih penting. Boost diterapkan setelah perhitungan
     * similarity dasar sebagai bonus tambahan.
     *
     * Fungsi ini berguna untuk meningkatkan skor dokumen yang mengandung term
     * domain-spesifik penting (misalnya kata kunci keamanan IT seperti "virus", "malware").
     *
     * Alur proses:
     * 1. Hitung similarity dasar menggunakan hitung().
     * 2. Jika tidak ada boost factor, kembalikan similarity dasar.
     * 3. Untuk setiap term yang memiliki boost dan muncul di kedua vektor,
     *    hitung tambahan bonus berdasarkan nilai boost.
     * 4. Kembalikan similarity dasar ditambah total bonus.
     *
     * Parameter:
     * - array $queryVector  : Vektor TF-IDF dari query pengguna
     * - array $docVector    : Vektor TF-IDF dari dokumen
     * - array $boostFactors : Array asosiatif [term => boost_multiplier]
     *
     * Kembalikan:
     * - float : Nilai similarity yang sudah ditingkatkan dengan boost
     */
    public function calculateWithBoost(array $queryVector, array $docVector, array $boostFactors = []): float
    {
        // 7.1 Jika salah satu vektor kosong, kemiripan pasti 0
        if (empty($queryVector) || empty($docVector)) {
            return 0.0;
        }

        // 7.2 Hitung similarity dasar terlebih dahulu
        $baseSimilarity = $this->calculate($queryVector, $docVector);

        // 7.3 Jika tidak ada boost factor, langsung kembalikan similarity dasar
        if (empty($boostFactors)) {
            return $baseSimilarity;
        }

        $boostBonus = 0.0;

        // 7.4 Hitung bonus boost untuk setiap term yang ada di kedua vektor
        // Formula: bonus = nilai_query × nilai_doc × (boost_factor - 1)
        // Pengurangan 1 karena similarity dasar sudah menghitung kontribusi dasar term ini
        foreach ($boostFactors as $term => $boost) {
            if (isset($queryVector[$term]) && isset($docVector[$term])) {
                $boostBonus += $queryVector[$term] * $docVector[$term] * ($boost - 1);
            }
        }

        return $baseSimilarity + $boostBonus;
    }
}