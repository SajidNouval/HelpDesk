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
     * Fungsi pembantu internal untuk memeriksa apakah sebuah term
     * termasuk dalam daftar term prioritas rendah.
     *
     * Parameter:
     * - string $term : Term yang akan diperiksa
     *
     * Kembalikan:
     * - bool : true jika term adalah term prioritas rendah
     */
    private function isLowPriorityTerm(string $term): bool
    {
        return in_array(mb_strtolower($term), $this->lowPriorityTerms);
    }

    /**
     * 1. Fungsi calculateTF()
     *
     * Fungsi ini menghitung Term Frequency (TF) untuk sebuah dokumen.
     * TF mengukur seberapa sering sebuah term muncul relatif terhadap
     * total jumlah term dalam dokumen.
     *
     * Formula: TF(t, d) = count(t, d) / total_terms(d)
     *
     * Nilai TF yang tinggi berarti term tersebut sering muncul dalam dokumen,
     * sehingga dianggap lebih representatif untuk dokumen tersebut.
     *
     * Alur proses:
     * 1. Menjumlahkan total frekuensi semua term dalam dokumen.
     * 2. Membagi frekuensi setiap term dengan total untuk mendapatkan rasio.
     *
     * Parameter:
     * - array $termFrequency : Array asosiatif [term => jumlah_kemunculan]
     *
     * Kembalikan:
     * - array : Array asosiatif [term => nilai_tf] dalam rentang [0, 1]
     */
    public function calculateTF(array $termFrequency): array
    {
        // 1.1 Hitung total kemunculan semua term dalam dokumen
        $totalTerms = array_sum($termFrequency);

        // 1.2 Jika dokumen kosong (tidak ada term), kembalikan array kosong
        if ($totalTerms === 0) {
            return [];
        }

        $tf = [];
        // 1.3 Hitung rasio TF untuk setiap term: frekuensi term / total term
        foreach ($termFrequency as $term => $count) {
            $tf[$term] = $count / $totalTerms;
        }

        return $tf;
    }

    /**
     * 2. Fungsi calculateIDF()
     *
     * Fungsi ini menghitung Inverse Dokumen Frequency (IDF) untuk semua term
     * dalam seluruh korpus dokumen.
     *
     * IDF mengukur seberapa jarang sebuah term muncul di seluruh dokumen.
     * Term yang muncul di banyak dokumen (common term) mendapat nilai IDF rendah,
     * sedangkan term yang jarang muncul mendapat nilai IDF tinggi.
     *
     * Formula yang digunakan (Smoothed IDF):
     * IDF(t) = log(1 + N / (1 + df(t))) + 1
     * - N     : total jumlah dokumen
     * - df(t) : jumlah dokumen yang mengandung term t
     * - +1 di awal: smoothing agar IDF tidak pernah bernilai 0
     * - +1 di akhir: memastikan term langka tetap mendapat bobot lebih tinggi
     *
     * Formula smoothed ini dipilih karena:
     * - Menghindari nilai 0 ketika term muncul di semua dokumen
     * - Lebih stabil dibanding formula IDF klasik untuk korpus kecil
     *
     * Alur proses:
     * 1. Hitung jumlah dokumen yang mengandung setiap term (dokumen frequency).
     * 2. Terapkan formula smoothed IDF untuk setiap term.
     *
     * Parameter:
     * - array $documentTermFrequencies : Array asosiatif [docId => [term => frekuensi]]
     *
     * Kembalikan:
     * - array : Array asosiatif [term => nilai_idf]
     */
    public function calculateIDF(array $documentTermFrequencies): array
    {
        // 2.1 Hitung total jumlah dokumen dalam korpus
        $totalDocs = count($documentTermFrequencies);

        // 2.2 Jika tidak ada dokumen, kembalikan array kosong
        if ($totalDocs === 0) {
            return [];
        }

        $documentFrequency = [];

        // 2.3 Hitung berapa banyak dokumen yang mengandung setiap term
        foreach ($documentTermFrequencies as $docId => $termFreq) {
            foreach (array_keys($termFreq) as $term) {
                $documentFrequency[$term] = ($documentFrequency[$term] ?? 0) + 1;
            }
        }

        $idf = [];
        // 2.4 Terapkan formula Smoothed IDF untuk setiap term
        // log(1 + N / (1 + df)) + 1 — memastikan nilai selalu positif
        foreach ($documentFrequency as $term => $docCount) {
            $idf[$term] = log(1 + $totalDocs / (1 + $docCount)) + 1;
        }

        return $idf;
    }

    /**
     * 3. Fungsi calculateTFIDF()
     *
     * Fungsi ini menghitung skor TF-IDF final untuk satu dokumen
     * dengan mengalikan nilai TF dan IDF setiap term.
     *
     * Inovasi khusus: Term prioritas rendah (generik) mendapat pengurangan bobot 90%
     * menggunakan konstanta LOW_PRIORITY_WEIGHT = 0.1. Ini mencegah kata-kata umum
     * seperti "cara" dan "mengatasi" mendominasi ranking dan mengalahkan term
     * domain-spesifik yang lebih bermakna bagi intent pengguna.
     *
     * Formula: TF-IDF(t, d) = TF(t, d) × IDF(t) × weight_modifier
     * - weight_modifier = 0.1 untuk term prioritas rendah
     * - weight_modifier = 1.0 untuk term normal
     *
     * Alur proses:
     * 1. Iterasi setiap term dalam vektor TF.
     * 2. Kalikan TF dengan IDF yang sesuai.
     * 3. Kurangi bobot jika term termasuk kategori prioritas rendah.
     *
     * Parameter:
     * - array $tf  : Array asosiatif [term => nilai_tf] dari calculateTF()
     * - array $idf : Array asosiatif [term => nilai_idf] dari calculateIDF()
     *
     * Kembalikan:
     * - array : Array asosiatif [term => skor_tfidf]
     */
    public function calculateTFIDF(array $tf, array $idf): array
    {
        $tfidf = [];

        foreach ($tf as $term => $tfValue) {
            // 3.1 Ambil nilai IDF untuk term ini; default 0 jika tidak ada di IDF
            $idfValue = $idf[$term] ?? 0;
            $score = $tfValue * $idfValue;

            // 3.2 Kurangi bobot untuk term generik agar tidak mendominasi ranking
            // Term seperti "cara", "mengatasi", "masalah" dikurangi 90%
            if ($this->isLowPriorityTerm($term)) {
                $score *= self::LOW_PRIORITY_WEIGHT;
            }

            $tfidf[$term] = $score;
        }

        return $tfidf;
    }

    /**
     * 4. Fungsi buildTfidfVectors()
     *
     * Fungsi ini membangun vektor TF-IDF untuk seluruh kumpulan dokumen sekaligus.
     * Proses ini meliputi preprocessing teks, perhitungan IDF korpus-level,
     * dan perhitungan vektor TF-IDF per dokumen.
     *
     * Alur proses:
     * 1. Preprocessing setiap dokumen untuk mendapatkan frekuensi term.
     * 2. Hitung IDF berdasarkan seluruh dokumen (korpus).
     * 3. Hitung vektor TF-IDF untuk setiap dokumen menggunakan IDF yang sudah ada.
     *
     * Parameter:
     * - array $dokumen : Array asosiatif [docId => ['teks' => string, ...]]
     *
     * Kembalikan:
     * - array : ['vectors' => [docId => tfidf_vector], 'idf' => idf_array, 'docCount' => int]
     */
    public function buildTfidfVectors(array $documents): array
    {
        $documentTermFrequencies = [];

        // 4.1 Preprocessing setiap dokumen untuk mendapatkan frekuensi term
        foreach ($documents as $docId => $doc) {
            $preprocessed = $this->preprocessor->preprocessDocument($doc['text']);
            $documentTermFrequencies[$docId] = $preprocessed['frequency'];
        }

        // 4.2 Hitung IDF berdasarkan seluruh korpus dokumen
        $idf = $this->calculateIDF($documentTermFrequencies);

        $vectors = [];
        // 4.3 Bangun vektor TF-IDF untuk setiap dokumen
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
     * 5. Fungsi calculateQueryTFIDF()
     *
     * Fungsi ini menghitung vektor TF-IDF untuk query pengguna menggunakan
     * nilai IDF yang sudah dibangun dari korpus dokumen yang ada.
     *
     * Perbedaan dengan buildTfidfVectors():
     * - Fungsi ini hanya untuk SATU query, bukan kumpulan dokumen.
     * - IDF yang digunakan adalah IDF dari korpus yang sudah ada (tidak dihitung ulang).
     * - Menerapkan koreksi typo sebelum preprocessing query.
     *
     * Catatan penting: Koreksi typo (applyTypoCorrection = true) diterapkan
     * sebelum tokenisasi untuk memastikan "wfi" dikenali sebagai "wifi",
     * "emai" dikenali sebagai "email", dst.
     *
     * Alur proses:
     * 1. Preprocessing query dengan koreksi typo aktif.
     * 2. Hitung frekuensi term dari token yang dihasilkan.
     * 3. Hitung TF dari frekuensi tersebut.
     * 4. Kalikan TF × IDF dengan pengurangan bobot untuk term generik.
     *
     * Parameter:
     * - string $query : Query mentah dari pengguna
     * - array  $idf   : Array IDF yang sudah dihitung dari korpus dokumen
     *
     * Kembalikan:
     * - array : Array asosiatif [term => skor_tfidf] sebagai vektor query
     */
    public function calculateQueryTFIDF(string $query, array $idf): array
    {
        // 5.1 Preprocessing query dengan koreksi typo aktif
        // Parameter true = aktifkan koreksi typo agar "wfi" → "wifi", "emai" → "email"
        $tokens = $this->preprocessor->preprocess($query, true);

        // 5.2 Hitung frekuensi kemunculan setiap term dalam query
        $frequency = [];
        foreach ($tokens as $token) {
            $frequency[$token] = ($frequency[$token] ?? 0) + 1;
        }

        // 5.3 Hitung TF dari frekuensi term query
        $tf = $this->calculateTF($frequency);

        $tfidf = [];
        foreach ($tf as $term => $tfValue) {
            // 5.4 Ambil nilai IDF; term yang tidak ada di korpus mendapat IDF = 0
            $idfValue = $idf[$term] ?? 0;
            $score = $tfValue * $idfValue;

            // 5.5 Kurangi bobot untuk term generik yang tidak representatif
            if ($this->isLowPriorityTerm($term)) {
                $score *= self::LOW_PRIORITY_WEIGHT;
            }

            $tfidf[$term] = $score;
        }

        return $tfidf;
    }

    /**
     * 6. Fungsi getOrComputeIDF()
     *
     * Fungsi ini mengambil nilai IDF dari cache jika tersedia, atau menghitungnya
     * dari awal dan menyimpannya ke cache untuk digunakan kembali.
     *
     * Caching IDF diperlukan karena perhitungan IDF bersifat mahal (expensive)
     * secara komputasi dan hasilnya tidak berubah selama corpus dokumen tidak berubah.
     * Cache berlaku selama 24 jam (IDF_CACHE_TTL).
     *
     * Alur proses:
     * 1. Cek apakah IDF sudah tersimpan di cache.
     * 2. Jika ada di cache, kembalikan langsung tanpa menghitung ulang.
     * 3. Jika tidak ada, hitung dari dokumen dan simpan ke cache.
     *
     * Parameter:
     * - array $dokumen : Array dokumen untuk menghitung IDF jika cache kosong
     *
     * Kembalikan:
     * - array : Array asosiatif [term => nilai_idf]
     */
    public function getOrComputeIDF(array $documents): array
    {
        // 6.1 Coba ambil IDF dari cache terlebih dahulu
        $cached = Cache::get(self::IDF_CACHE_KEY);

        // 6.2 Jika cache ada, kembalikan langsung (hemat komputasi)
        if ($cached !== null) {
            return $cached;
        }

        // 6.3 Cache kosong — hitung IDF dari dokumen dan simpan ke cache
        $result = $this->buildTfidfVectors($documents);
        Cache::put(self::IDF_CACHE_KEY, $result['idf'], self::IDF_CACHE_TTL);

        return $result['idf'];
    }

    /**
     * 7. Fungsi clearCache()
     *
     * Fungsi ini menghapus cache IDF yang tersimpan untuk memaksa sistem
     * menghitung ulang IDF pada permintaan berikutnya.
     *
     * Fungsi ini perlu dipanggil ketika:
     * - Ada artikel baru yang ditambahkan ke sistem.
     * - Ada artikel yang dihapus atau dinonaktifkan.
     * - Ada perubahan signifikan pada corpus dokumen.
     *
     * Kembalikan:
     * - void
     */
    public function clearCache(): void
    {
        Cache::forget(self::IDF_CACHE_KEY);
    }

    /**
     * 8. Fungsi normalizeVector()
     *
     * Fungsi ini melakukan normalisasi L2 (Euclidean normalization) pada sebuah
     * vektor sparse. Setelah normalisasi, magnitude (panjang) vektor menjadi 1.
     *
     * Normalisasi diperlukan sebelum perhitungan Cosine Similarity agar vektor
     * dokumen yang lebih panjang tidak mendapat keuntungan tidak adil hanya
     * karena jumlah kata yang lebih banyak.
     *
     * Formula: v_normalized = v / ||v||
     * di mana ||v|| = sqrt(sum(v_i^2))
     *
     * Alur proses:
     * 1. Hitung magnitude (norm L2) dari vektor.
     * 2. Jika magnitude nol, kembalikan vektor asli (hindari pembagian nol).
     * 3. Bagi setiap komponen vektor dengan magnitude-nya.
     *
     * Parameter:
     * - array $vector : Vektor sparse yang akan dinormalisasi [term => nilai]
     *
     * Kembalikan:
     * - array : Vektor yang sudah dinormalisasi dengan magnitude = 1
     */
    public function normalizeVector(array $vector): array
    {
        // 8.1 Hitung magnitude (norm L2): sqrt(sum(v_i^2))
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v * $v, $vector)));

        // 8.2 Jika magnitude nol, kembalikan vektor asli untuk menghindari pembagian nol
        if ($magnitude === 0) {
            return $vector;
        }

        $normalized = [];
        // 8.3 Bagi setiap nilai vektor dengan magnitude untuk normalisasi
        foreach ($vector as $term => $value) {
            $normalized[$term] = $value / $magnitude;
        }

        return $normalized;
    }

    /**
     * 9. Fungsi getAllTerms()
     *
     * Fungsi ini mengumpulkan semua term unik yang muncul dalam sekumpulan vektor.
     * Hasilnya adalah vocabulary (kamus) dari seluruh koleksi vektor yang diberikan.
     *
     * Vocabulary ini digunakan untuk mengkonversi vektor sparse menjadi
     * dense vector pada fungsi toDenseVector().
     *
     * Alur proses:
     * 1. Iterasi setiap vektor dalam koleksi.
     * 2. Ambil semua key (term) dari setiap vektor.
     * 3. Gabungkan dan hapus duplikat untuk mendapatkan vocabulary unik.
     *
     * Parameter:
     * - array $vectors : Array vektor [docId => [term => nilai]]
     *
     * Kembalikan:
     * - array : Array term unik yang menjadi vocabulary koleksi
     */
    public function getAllTerms(array $vectors): array
    {
        $terms = [];

        // 9.1 Kumpulkan semua term dari setiap vektor dalam koleksi
        foreach ($vectors as $vector) {
            $terms = array_merge($terms, array_keys($vector));
        }

        // 9.2 Kembalikan hanya term unik (hapus duplikat)
        return array_unique($terms);
    }

    /**
     * 10. Fungsi toDenseVector()
     *
     * Fungsi ini mengkonversi vektor sparse menjadi vektor dense berdasarkan
     * vocabulary yang diberikan.
     *
     * Perbedaan sparse vs dense:
     * - Sparse : Hanya menyimpan term yang ada (misalnya: ['wifi' => 0.5, 'lemot' => 0.3])
     * - Dense  : Menyimpan nilai untuk SEMUA term dalam vocabulary (misalnya: [0.5, 0, 0.3, 0, ...])
     *
     * Dense vector diperlukan untuk operasi matematika tertentu yang membutuhkan
     * vektor dengan dimensi yang sama dan urutan yang konsisten.
     *
     * Alur proses:
     * 1. Iterasi setiap term dalam vocabulary.
     * 2. Ambil nilai dari vektor sparse; jika term tidak ada, gunakan 0.
     * 3. Bangun array dense secara berurutan sesuai urutan vocabulary.
     *
     * Parameter:
     * - array $sparseVector : Vektor sparse [term => nilai]
     * - array $vocabulary   : Daftar term yang menjadi dimensi vektor dense
     *
     * Kembalikan:
     * - array : Vektor dense dengan panjang sama dengan vocabulary
     */
    public function toDenseVector(array $sparseVector, array $vocabulary): array
    {
        $dense = [];

        // 10.1 Untuk setiap term dalam vocabulary, ambil nilai dari sparse vector
        // Jika term tidak ada di sparse vector, nilainya 0 (term tidak muncul di dokumen)
        foreach ($vocabulary as $term) {
            $dense[] = $sparseVector[$term] ?? 0;
        }

        return $dense;
    }
}
