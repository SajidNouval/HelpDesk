<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;

/**
 * =========================================================================
 * SERVICE TF-IDF (Term Frequency — Inverse Dokumen Frequency)
 * =========================================================================
 *
 * Layanan ini menghitung bobot TF-IDF untuk dokumen dan query pengguna
 * dalam konteks sistem retrieval chatbot helpdesk IT.
 *
 * TF-IDF adalah teknik pembobotan term yang menggabungkan dua konsep:
 * - TF (Term Frequency)  : Seberapa sering sebuah term muncul dalam dokumen.
 * - IDF (Inverse Dokumen Frequency) : Seberapa jarang term muncul di seluruh korpus.
 *   Term yang jarang muncul di banyak dokumen dianggap lebih informatif.
 *
 * Inovasi pada layanan ini:
 * - Istilah generik helpdesk (misalnya: "cara", "mengatasi", "masalah")
 *   sengaja dikurangi bobotnya sebesar 90% agar tidak mendominasi ranking
 *   dan menggeser term domain-spesifik yang lebih bermakna.
 *
 * Fungsi utama:
 * - Menghitung term frequency (TF) dalam satu dokumen.
 * - Menghitung inverse dokumen frequency (IDF) di seluruh korpus.
 * - Membangun vektor TF-IDF untuk kumpulan dokumen.
 * - Menghitung vektor TF-IDF untuk query pengguna.
 * - Menyimpan dan mengambil hasil IDF dari cache.
 *
 * Digunakan oleh:
 * - AdvancedRetrievalService
 * - ChatbotRetrievalService
 */
class TfidfService
{
    // Kunci dan durasi cache untuk menyimpan hasil perhitungan IDF
    private const IDF_CACHE_KEY = 'chatbot:tfidf:idf_scores';
    private const IDF_CACHE_TTL = 86400; // 24 jam dalam detik

    /**
     * Daftar term prioritas rendah yang bobotnya dikurangi secara signifikan.
     *
     * Term-term ini terlalu umum dalam artikel helpdesk IT sehingga apabila
     * dibiarkan dengan bobot normal, mereka akan mendominasi hasil ranking
     * dan mengalahkan term domain-spesifik yang lebih relevan.
     *
     * Kategori 1: Kata instruksional generik (cara, mengatasi, tutorial, dll.)
     * Kategori 2: Kata teknis/perangkat generik (pc, laptop, aplikasi, dll.)
     *             yang terlalu umum dan tidak menunjukkan intent spesifik pengguna.
     */
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

        // Kata teknis/perangkat generik — terlalu umum, tidak menunjukkan intent spesifik
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
     * Faktor pengali untuk term prioritas rendah.
     * Nilai 0.1 berarti bobot dikurangi 90% dari nilai normalnya.
     */
    private const LOW_PRIORITY_WEIGHT = 0.1;

    private PreprocessingService $preprocessor;

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
    public function __construct(PreprocessingService $preprocessor)
    {
        $this->preprocessor = $preprocessor;
    }

    /**
     * =========================================================================
     * 1. METODE IS LOW PRIORITY TERM
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah sebuah term termasuk dalam daftar term prioritas rendah.
     *
     * Alur Proses:
     * 1. Menerima term yang akan diperiksa.
     * 2. Memeriksa apakah term ada dalam daftar lowPriorityTerms.
     * 3. Mengembalikan true jika term adalah prioritas rendah.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool true jika term adalah term prioritas rendah
     */
    private function isLowPriorityTerm(string $term): bool
    {
        return in_array(mb_strtolower($term), $this->lowPriorityTerms);
    }

    /**
     * =========================================================================
     * 1. METODE CALCULATE TF
     * =========================================================================
     *
     * Fungsi:
     * Menghitung Term Frequency (TF) untuk sebuah dokumen.
     *
     * Alur Proses:
     * 1. Menerima array frekuensi term.
     * 2. Menjumlahkan total frekuensi semua term dalam dokumen.
     * 3. Membagi frekuensi setiap term dengan total untuk mendapatkan rasio.
     * 4. Mengembalikan array TF dalam rentang [0, 1].
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array [term => nilai_tf] dalam rentang [0, 1]
     */
    public function calculateTF(array $termFrequency): array
    {
        // Hitung total kemunculan semua term dalam dokumen
        $totalTerms = array_sum($termFrequency);

        // Jika dokumen kosong, kembalikan array kosong
        if ($totalTerms === 0) {
            return [];
        }

        $tf = [];
        // Hitung rasio TF untuk setiap term
        foreach ($termFrequency as $term => $count) {
            $tf[$term] = $count / $totalTerms;
        }

        return $tf;
    }

    /**
     * =========================================================================
     * 1. METODE CALCULATE IDF
     * =========================================================================
     *
     * Fungsi:
     * Menghitung Inverse Dokumen Frequency (IDF) untuk semua term dalam korpus dokumen.
     *
     * Alur Proses:
     * 1. Menerima array frekuensi term per dokumen.
     * 2. Hitung jumlah dokumen yang mengandung setiap term.
     * 3. Terapkan formula smoothed IDF untuk setiap term.
     * 4. Mengembalikan array IDF.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array [term => nilai_idf]
     */
    public function calculateIDF(array $documentTermFrequencies): array
    {
        // Hitung total jumlah dokumen dalam korpus
        $totalDocs = count($documentTermFrequencies);

        // Jika tidak ada dokumen, kembalikan array kosong
        if ($totalDocs === 0) {
            return [];
        }

        $documentFrequency = [];

        // Hitung berapa banyak dokumen yang mengandung setiap term
        foreach ($documentTermFrequencies as $docId => $termFreq) {
            foreach (array_keys($termFreq) as $term) {
                $documentFrequency[$term] = ($documentFrequency[$term] ?? 0) + 1;
            }
        }

        $idf = [];
        // Terapkan formula Smoothed IDF untuk setiap term
        foreach ($documentFrequency as $term => $docCount) {
            $idf[$term] = log(1 + $totalDocs / (1 + $docCount)) + 1;
        }

        return $idf;
    }

    /**
     * =========================================================================
     * 1. METODE CALCULATE TFIDF
     * =========================================================================
     *
     * Fungsi:
     * Menghitung skor TF-IDF final untuk satu dokumen dengan mengalikan TF dan IDF.
     *
     * Alur Proses:
     * 1. Menerima array TF dan IDF.
     * 2. Iterasi setiap term dalam vektor TF.
     * 3. Kalikan TF dengan IDF yang sesuai.
     * 4. Kurangi bobot jika term termasuk kategori prioritas rendah.
     * 5. Mengembalikan array TF-IDF.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array [term => skor_tfidf]
     */
    public function calculateTFIDF(array $tf, array $idf): array
    {
        $tfidf = [];

        foreach ($tf as $term => $tfValue) {
            // Ambil nilai IDF untuk term ini; default 0 jika tidak ada di IDF
            $idfValue = $idf[$term] ?? 0;
            $score = $tfValue * $idfValue;

            // Kurangi bobot untuk term generik agar tidak mendominasi ranking
            if ($this->isLowPriorityTerm($term)) {
                $score *= self::LOW_PRIORITY_WEIGHT;
            }

            $tfidf[$term] = $score;
        }

        return $tfidf;
    }

    /**
     * =========================================================================
     * 1. METODE BUILD TFIDF VECTORS
     * =========================================================================
     *
     * Fungsi:
     * Membangun vektor TF-IDF untuk seluruh kumpulan dokumen sekaligus.
     *
     * Alur Proses:
     * 1. Menerima array dokumen.
     * 2. Preprocessing setiap dokumen untuk mendapatkan frekuensi term.
     * 3. Hitung IDF berdasarkan seluruh dokumen (korpus).
     * 4. Hitung vektor TF-IDF untuk setiap dokumen menggunakan IDF yang sudah ada.
     * 5. Mengembalikan vektor, IDF, dan jumlah dokumen.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array ['vectors' => [docId => tfidf_vector], 'idf' => idf_array, 'docCount' => int]
     */
    public function buildTfidfVectors(array $documents): array
    {
        $documentTermFrequencies = [];

        // Preprocessing setiap dokumen untuk mendapatkan frekuensi term
        foreach ($documents as $docId => $doc) {
            $preprocessed = $this->preprocessor->preprocessDocument($doc['text']);
            $documentTermFrequencies[$docId] = $preprocessed['frequency'];
        }

        // Hitung IDF berdasarkan seluruh korpus dokumen
        $idf = $this->calculateIDF($documentTermFrequencies);

        $vectors = [];
        // Bangun vektor TF-IDF untuk setiap dokumen
        foreach ($documentTermFrequencies as $docId => $termFreq) {
            $tf = $this->calculateTF($termFreq);
            $vectors[$docId] = $this->calculateTFIDF($tf, $idf);
        }

        return [
            'vectors'  => $vectors,
            'idf'      => $idf,
            'docCount' => count($documents),
        ];
    }

    /**
     * =========================================================================
     * 1. METODE CALCULATE QUERY TFIDF
     * =========================================================================
     *
     * Fungsi:
     * Menghitung vektor TF-IDF untuk query pengguna menggunakan IDF dari korpus.
     *
     * Alur Proses:
     * 1. Menerima query mentah dan array IDF dari korpus.
     * 2. Preprocessing query dengan koreksi typo aktif.
     * 3. Hitung frekuensi term dari token yang dihasilkan.
     * 4. Hitung TF dari frekuensi tersebut.
     * 5. Kalikan TF × IDF dengan pengurangan bobot untuk term generik.
     * 6. Mengembalikan vektor query TF-IDF.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array [term => skor_tfidf] sebagai vektor query
     */
    public function calculateQueryTFIDF(string $query, array $idf): array
    {
        // Preprocessing query dengan koreksi typo aktif
        $tokens = $this->preprocessor->preprocess($query, true);

        // Hitung frekuensi kemunculan setiap term dalam query
        $frequency = [];
        foreach ($tokens as $token) {
            $frequency[$token] = ($frequency[$token] ?? 0) + 1;
        }

        // Hitung TF dari frekuensi term query
        $tf = $this->calculateTF($frequency);

        $tfidf = [];
        foreach ($tf as $term => $tfValue) {
            // Ambil nilai IDF; term yang tidak ada di korpus mendapat IDF = 0
            $idfValue = $idf[$term] ?? 0;
            $score = $tfValue * $idfValue;

            // Kurangi bobot untuk term generik yang tidak representatif
            if ($this->isLowPriorityTerm($term)) {
                $score *= self::LOW_PRIORITY_WEIGHT;
            }

            $tfidf[$term] = $score;
        }

        return $tfidf;
    }

    /**
     * =========================================================================
     * 1. METODE GET OR COMPUTE IDF
     * =========================================================================
     *
     * Fungsi:
     * Mengambil nilai IDF dari cache atau menghitungnya dari awal.
     *
     * Alur Proses:
     * 1. Menerima array dokumen.
     * 2. Cek apakah IDF sudah tersimpan di cache.
     * 3. Jika ada di cache, kembalikan langsung tanpa menghitung ulang.
     * 4. Jika tidak ada, hitung dari dokumen dan simpan ke cache.
     * 5. Mengembalikan array IDF.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array [term => nilai_idf]
     */
    public function getOrComputeIDF(array $documents): array
    {
        // Coba ambil IDF dari cache terlebih dahulu
        $cached = Cache::get(self::IDF_CACHE_KEY);

        // Jika cache ada, kembalikan langsung
        if ($cached !== null) {
            return $cached;
        }

        // Cache kosong — hitung IDF dari dokumen dan simpan ke cache
        $result = $this->buildTfidfVectors($documents);
        Cache::put(self::IDF_CACHE_KEY, $result['idf'], self::IDF_CACHE_TTL);

        return $result['idf'];
    }

    /**
     * =========================================================================
     * 1. METODE CLEAR CACHE
     * =========================================================================
     *
     * Fungsi:
     * Menghapus cache IDF yang tersimpan untuk memaksa sistem menghitung ulang.
     *
     * Alur Proses:
     * 1. Menghapus cache IDF menggunakan Cache::forget.
     * 2. Mengembalikan void.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - void
     */
    public function clearCache(): void
    {
        Cache::forget(self::IDF_CACHE_KEY);
    }

    /**
     * =========================================================================
     * 1. METODE NORMALIZE VECTOR
     * =========================================================================
     *
     * Fungsi:
     * Melakukan normalisasi L2 pada vektor sparse agar magnitude menjadi 1.
     *
     * Alur Proses:
     * 1. Menerima vektor sparse yang akan dinormalisasi.
     * 2. Hitung magnitude (norm L2) dari vektor.
     * 3. Jika magnitude nol, kembalikan vektor asli untuk menghindari pembagian nol.
     * 4. Bagi setiap komponen vektor dengan magnitude-nya.
     * 5. Mengembalikan vektor yang sudah dinormalisasi.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array vektor yang sudah dinormalisasi dengan magnitude = 1
     */
    public function normalizeVector(array $vector): array
    {
        // Hitung magnitude (norm L2)
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));

        // Jika magnitude nol, kembalikan vektor asli untuk menghindari pembagian nol
        if ($magnitude === 0) {
            return $vector;
        }

        $normalized = [];
        // Bagi setiap nilai vektor dengan magnitude untuk normalisasi
        foreach ($vector as $term => $value) {
            $normalized[$term] = $value / $magnitude;
        }

        return $normalized;
    }

    /**
     * =========================================================================
     * 1. METODE GET ALL TERMS
     * =========================================================================
     *
     * Fungsi:
     * Mengumpulkan semua term unik yang muncul dalam sekumpulan vektor.
     *
     * Alur Proses:
     * 1. Menerima array vektor.
     * 2. Iterasi setiap vektor dalam koleksi.
     * 3. Ambil semua key (term) dari setiap vektor.
     * 4. Gabungkan dan hapus duplikat untuk mendapatkan vocabulary unik.
     * 5. Mengembalikan array term unik.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array term unik yang menjadi vocabulary koleksi
     */
    public function getAllTerms(array $vectors): array
    {
        $terms = [];

        // Kumpulkan semua term dari setiap vektor dalam koleksi
        foreach ($vectors as $vector) {
            $terms = array_merge($terms, array_keys($vector));
        }

        // Kembalikan hanya term unik (hapus duplikat)
        return array_unique($terms);
    }

    /**
     * =========================================================================
     * 1. METODE TO DENSE VECTOR
     * =========================================================================
     *
     * Fungsi:
     * Mengkonversi vektor sparse menjadi vektor dense berdasarkan vocabulary.
     *
     * Alur Proses:
     * 1. Menerima vektor sparse dan vocabulary.
     * 2. Iterasi setiap term dalam vocabulary.
     * 3. Ambil nilai dari vektor sparse; jika term tidak ada, gunakan 0.
     * 4. Bangun array dense secara berurutan sesuai urutan vocabulary.
     * 5. Mengembalikan vektor dense.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array vektor dense dengan panjang sama dengan vocabulary
     */
    public function toDenseVector(array $sparseVector, array $vocabulary): array
    {
        $dense = [];

        // Untuk setiap term dalam vocabulary, ambil nilai dari sparse vector
        foreach ($vocabulary as $term) {
            $dense[] = $sparseVector[$term] ?? 0;
        }

        return $dense;
    }
}
