<?php

namespace App\Services\Chatbot;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * =========================================================================
 * SERVICE CHATBOT RETRIEVAL
 * =========================================================================
 *
 * Layanan ini merupakan orkestrator utama proses pencarian artikel chatbot.
 * Ia menggabungkan dua mesin retrieval: Typesense (pencarian full-teks cepat)
 * dan TF-IDF (reranking berbasis relevansi semantik ringan).
 *
 * Arsitektur pipeline (dua fase):
 * - Fase A: Typesense sebagai mesin retrieval UTAMA (bobot 85%)
 *   Typesense menyediakan pencarian full-teks dengan fuzzy kecocokan dan
 *   penanganan typo secara native. Hasilnya adalah kandidat yang sudah
 *   diurutkan oleh Typesense.
 *
 * - Fase B: TF-IDF sebagai reranking RINGAN (bobot 15%)
 *   TF-IDF melakukan penyesuaian minor pada urutan kandidat dari Typesense.
 *   Pengaruhnya sengaja dibatasi agar tidak mengalahkan sinyal Typesense.
 *
 * Mengapa arsitektur ini dipilih:
 * - Typesense jauh lebih cepat dan akurat untuk pencocokan kata kunci
 * - TF-IDF memberikan koreksi berbasis konten untuk kasus edge case
 * - Kombinasi keduanya menghasilkan retrieval yang lebih robust
 *
 * Digunakan oleh:
 * - ChatbotController (sebagai fallback jika AdvancedRetrievalService tidak tersedia)
 */
class ChatbotRetrievalService
{
    // ============================================================
    // KONFIGURASI DASAR
    // ============================================================
    // Jumlah hasil maksimal yang dikembalikan ke pengguna
    private const TOP_K_RESULTS = 5;

    // Skor minimum agar hasil dianggap relevan dan ditampilkan
    private const SIMILARITY_THRESHOLD = 0.05;

    // Jumlah kandidat yang diminta dari Typesense (lebih banyak = reranking lebih baik)
    private const TYPESENSE_CANDIDATE_LIMIT = 30;

    // ============================================================
    // BOBOT KOMBINASI SKOR
    // ============================================================
    // Typesense mendapat bobot 85% sebagai sinyal utama
    private const TYPESENSE_WEIGHT = 0.85;

    // TF-IDF mendapat bobot 15% hanya untuk penyesuaian minor
    private const TFIDF_WEIGHT = 0.15;

    // ============================================================
    // FAKTOR BOOST RINGAN
    // ============================================================
    // Bonus ringan untuk artikel yang judulnya cocok dengan query
    private const TITLE_MATCH_BOOST = 0.5;

    // Bonus ringan untuk kecocokan frasa exact dalam judul
    private const EXACT_MATCH_BOOST = 0.3;

    // ============================================================
    // KONFIGURASI CACHE
    // ============================================================
    private const VECTOR_CACHE_KEY = 'chatbot:retrieval:vectors:normalized';
    private const VECTOR_CACHE_TTL = 86400; // 24 jam dalam detik
    private const IDF_CACHE_KEY    = 'chatbot:retrieval:idf';
    private const TOPIC_CACHE_KEY  = 'chatbot:topics';
    private const TOPIC_CACHE_TTL  = 3600; // 1 jam dalam detik

    private PreprocessingService  $preprocessor;
    private TfidfService          $tfidfService;
    private CosineSimilarityService $similarityService;
    private DomainDetectionService  $domainDetector;
    private TypesenseService        $typesenseService;

    // Penyimpanan informasi debug saat mode debug aktif
    private array $debugInfo = [];
    private bool  $debugMode;

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
    public function __construct(
        PreprocessingService $preprocessor,
        TfidfService $tfidfService,
        CosineSimilarityService $similarityService,
        DomainDetectionService $domainDetector,
        TypesenseService $typesenseService
    ) {
        $this->preprocessor      = $preprocessor;
        $this->tfidfService      = $tfidfService;
        $this->similarityService = $similarityService;
        $this->domainDetector    = $domainDetector;
        $this->typesenseService  = $typesenseService;
        $this->debugMode         = config('app.debug', false);
    }

    /**
     * 1. Fungsi ambil()
     *
     * Fungsi ini merupakan entry point utama proses pencarian artikel chatbot.
     * Pipeline yang dijalankan menggabungkan Typesense (85%) dan TF-IDF (15%).
     *
     * Tahapan pipeline:
     * 1. Normalisasi query (koreksi typo dasar).
     * 2. Deteksi domain untuk penyaringan kategori opsional.
     * 3. [FASE A] Retrieval via Typesense sebagai sumber kandidat utama.
     * 4. [FASE B] Pengambilan artikel untuk keperluan TF-IDF reranking.
     * 5. [FASE C] Perhitungan TF-IDF ringan dan boosting judul.
     * 6. [FASE D] Penggabungan skor Typesense + TF-IDF (85% + 15%).
     * 7. [FASE E] Pembangunan hasil akhir dengan filter ambang.
     *
     * Parameter:
     * - string $query : Query mentah dari pengguna
     * - int    $batas : Jumlah maksimal artikel yang dikembalikan (default: 5)
     *
     * Kembalikan:
     * - array : [
     *     'hasil'            => array,  // Daftar artikel terurut dengan skor
     *     'query'              => string, // Query asli
     *     'normalized_query'   => string, // Query setelah normalisasi
     *     'total'              => int,    // Jumlah hasil
     *     'threshold_met'      => bool,   // Apakah ada hasil di atas ambang
     *     'max_similarity'     => float,  // Skor tertinggi dari hasil
     *     'domain_detected'    => bool,   // Apakah domain berhasil terdeteksi
     *     'detected_domain'    => string|null, // Domain yang terdeteksi
     *     'typesense_used'     => bool,   // Apakah Typesense berhasil digunakan
     *     'typesense_candidates' => int,  // Jumlah kandidat dari Typesense
     *     'debug'              => array|null  // Info debug (hanya di mode debug)
     *   ]
     */
    /**
     * =========================================================================
     * 1. METODE Retrieve
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi retrieve di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - TOP_K_RESULTS
     */
    public function retrieve(string $query, int $limit = self::TOP_K_RESULTS): array
    {
        // Reset informasi debug untuk setiap request baru
        $this->debugInfo = [
            'original_query'      => $query,
            'typesense_used'      => false,
            'typesense_candidates' => 0,
        ];

        // 1.1 Normalisasi query: koreksi typo dasar
        $normalizedQuery = $this->normalizeQuery($query);
        $this->debugInfo['normalized_query'] = $normalizedQuery;

        // 1.2 Deteksi domain untuk penyaringan kategori opsional
        $domainInfo = $this->domainDetector->detectDomain($query);
        $this->debugInfo['detected_domain'] = $domainInfo['domain'] ?? null;

        // ============================================================
        // FASE A: RETRIEVAL UTAMA VIA TYPESENSE (bobot 85%)
        // Typesense adalah sumber ranking primer dengan fuzzy kecocokan
        // ============================================================
        $typesenseResults   = null;
        $typesenseCandidates = [];

        if ($this->typesenseService->isConnected()) {
            // 1.3 Bangun opsi pencarian berdasarkan domain yang terdeteksi
            $searchOptions = [];
            if (!empty($domainInfo['category_ids'])) {
                $searchOptions['category_id'] = $domainInfo['category_ids'][0] ?? null;
            }

            // 1.4 Kirim query ke Typesense dan ambil kandidat
            $typesenseResults = $this->typesenseService->search(
                $query,
                self::TYPESENSE_CANDIDATE_LIMIT,
                $searchOptions
            );

            if ($typesenseResults['success'] && !empty($typesenseResults['results'])) {
                $typesenseCandidates = $typesenseResults['results'];
                $this->debugInfo['typesense_used']       = true;
                $this->debugInfo['typesense_candidates'] = count($typesenseCandidates);
            }
        }

        // ============================================================
        // FASE B: PENGAMBILAN ARTIKEL UNTUK TF-IDF RERANKING
        // Prioritaskan kandidat dari Typesense; fallback ke database
        // ============================================================
        $articles = $this->getArticlesForReranking($typesenseCandidates, $domainInfo);

        if ($articles->isEmpty()) {
            return $this->emptyResult($query);
        }

        // ============================================================
        // FASE C: TF-IDF RERANKING RINGAN (bobot 15%)
        // Hanya memberikan penyesuaian minor, tidak mengalahkan Typesense
        // ============================================================
        $documents  = $this->prepareDocuments($articles);
        $tfidfData  = $this->buildOrRetrieveVectors($documents);

        $queryVector = $this->tfidfService->calculateQueryTFIDF(
            $normalizedQuery,
            $tfidfData['idf']
        );

        if (empty($queryVector)) {
            // Tidak ada kecocokan TF-IDF — andalkan sepenuhnya pada ranking Typesense
            return $this->buildTypesenseOnlyResults($typesenseCandidates, $articles, $limit);
        }

        // 1.5 Hitung similarity TF-IDF antara query dan setiap dokumen kandidat
        $tfidfSimilarities = $this->calculateTfidfSimilarities($queryVector, $tfidfData['vectors']);

        // 1.6 Terapkan boost ringan (judul cocok, exact frasa) dengan pengaruh minimal
        $boostedSimilarities = $this->applyLightBoosting($tfidfSimilarities, $documents, $queryVector);

        // ============================================================
        // FASE D: PENGGABUNGAN SKOR TYPESENSE + TF-IDF
        // Formula: skor_final = (Typesense × 85%) + (TF-IDF × 15%)
        // ============================================================
        $combinedScores = $this->combineScores($typesenseCandidates, $boostedSimilarities);

        // ============================================================
        // FASE E: PEMBANGUNAN HASIL AKHIR
        // Filter ambang, ambil top-N, format output
        // ============================================================
        $results = $this->buildFinalResults($combinedScores, $articles, $limit);

        $thresholdMet = !empty($results) && $results[0]['similarity'] >= self::SIMILARITY_THRESHOLD;

        $this->debugInfo['final_results'] = count($results);
        $this->debugInfo['threshold_met'] = $thresholdMet;

        if ($this->debugMode) {
            Log::info('Chatbot retrieval debug', $this->debugInfo);
        }

        return [
            'results'              => $results,
            'query'                => $query,
            'normalized_query'     => $normalizedQuery,
            'total'                => count($results),
            'threshold_met'        => $thresholdMet,
            'max_similarity'       => !empty($results) ? $results[0]['similarity'] : 0,
            'domain_detected'      => $domainInfo['detected'] ?? false,
            'detected_domain'      => $domainInfo['domain'] ?? null,
            'typesense_used'       => $this->debugInfo['typesense_used'],
            'typesense_candidates' => $this->debugInfo['typesense_candidates'],
            'debug'                => $this->debugMode ? $this->debugInfo : null,
        ];
    }

    /**
     * Fungsi pembantu: normalizeQuery() [private]
     *
     * Melakukan normalisasi dasar pada query pengguna (koreksi typo).
     * Normalisasi yang lebih dalam dilakukan oleh PreprocessingService.
     *
     * Parameter:
     * - string $query : Query mentah dari pengguna
     *
     * Kembalikan:
     * - string : Query yang sudah dinormalisasi
     */
    /**
     * =========================================================================
     * 1. METODE Normalize Query
     * =========================================================================
     *
     * Fungsi:
     * Menormalisasi normalize query agar konsisten di seluruh pipeline.
     *
     * Alur Proses:
     * 1. Membersihkan teks/kata dari variasi atau typo.
     * 1. Mengubah format ke bentuk standar.
     * 1. Mengembalikan string atau token yang dinormalisasi.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function normalizeQuery(string $query): string
    {
        return $this->preprocessor->normalizeTypos($query);
    }

    /**
     * Fungsi pembantu: getArticlesForReranking() [private]
     *
     * Mengambil artikel untuk keperluan TF-IDF reranking.
     * Prioritas utama: gunakan kandidat dari Typesense.
     * Fallback: ambil dari database dengan filter domain jika tersedia.
     *
     * Menggunakan kandidat Typesense lebih disukai karena:
     * - Artikel sudah dipilih berdasarkan relevansi Typesense
     * - Reranking TF-IDF hanya dilakukan pada subset yang relevan
     * - Mengurangi beban komputasi TF-IDF
     *
     * Parameter:
     * - array $typesenseCandidates : Kandidat dari Typesense [['id', ...], ...]
     * - array $domainInfo          : Hasil deteksi domain dari DomainDetectionService
     *
     * Kembalikan:
     * - Koleksi : Koleksi objek Article untuk diproses TF-IDF
     */
    /**
     * =========================================================================
     * 1. METODE Get Articles For Reranking
     * =========================================================================
     *
     * Fungsi:
     * Mengambil data get articles for reranking untuk keperluan logika service.
     *
     * Alur Proses:
     * 1. Menentukan sumber data untuk get articles for reranking.
     * 1. Mengambil atau memformat data.
     * 1. Mengembalikan hasil dalam struktur yang sesuai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - Collection
     */
    private function getArticlesForReranking(array $typesenseCandidates, array $domainInfo): Collection
    {
        if (!empty($typesenseCandidates)) {
            // Query ini mengambil artikel berdasarkan ID kandidat dari Typesense
            // hanya untuk artikel yang sudah dipublikasi dan disetujui
            $candidateIds = array_column($typesenseCandidates, 'id');
            return Article::whereIn('id', $candidateIds)
                ->where('is_published', true)
                ->where('publish_status', 'approved')
                ->with('category')
                ->get();
        }

        // Fallback: ambil dari database jika Typesense tidak tersedia
        $query = Article::where('is_published', true)
            ->where('publish_status', 'approved')
            ->with('category');

        // Terapkan filter domain jika terdeteksi
        if (!empty($domainInfo['category_ids'])) {
            $query->whereIn('category_id', $domainInfo['category_ids']);
        }

        return $query->select('id', 'title', 'content', 'excerpt', 'keywords', 'slug', 'category_id')
            ->get();
    }

    /**
     * Fungsi pembantu: buildTypesenseOnlyResults() [private]
     *
     * Membangun hasil akhir menggunakan ranking Typesense semata ketika
     * TF-IDF tidak menghasilkan vektor query (query tidak cocok dengan IDF).
     * Skor dinormalisasi relatif terhadap skor kandidat tertinggi.
     *
     * Parameter:
     * - array      $typesenseCandidates : Kandidat dari Typesense
     * - Koleksi $articles            : Koleksi artikel dari database
     * - int        $batas               : Jumlah maksimal hasil
     *
     * Kembalikan:
     * - array : Daftar artikel terformat dengan skor Typesense
     */
    /**
     * =========================================================================
     * 1. METODE Build Typesense Only Results
     * =========================================================================
     *
     * Fungsi:
     * Membangun objek/struktur build typesense only results untuk pipeline retrieval.
     *
     * Alur Proses:
     * 1. Mempersiapkan data awal untuk build typesense only results.
     * 1. Menggabungkan atribut penting.
     * 1. Mengembalikan objek atau array yang siap dipakai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function buildTypesenseOnlyResults(array $typesenseCandidates, Collection $articles, int $limit): array
    {
        $results     = [];
        $articlesMap = $articles->keyBy('id');

        foreach ($typesenseCandidates as $candidate) {
            if (count($results) >= $limit) {
                break;
            }

            if (!isset($articlesMap[$candidate['id']])) {
                continue;
            }

            $article         = $articlesMap[$candidate['id']];
            $score           = $candidate['typesense_score'] ?? 0;
            $normalizedScore = $this->normalizeTypesenseScore($score, $typesenseCandidates);

            $results[] = [
                'id'            => $article->id,
                'title'         => $article->title,
                'excerpt'       => $article->excerpt,
                'content'       => $article->content,
                'slug'          => $article->slug,
                'category_id'   => $article->category_id,
                'category_name' => $article->category->name ?? null,
                'similarity'    => round($normalizedScore, 4),
                'confidence'    => $this->getConfidenceLevel($normalizedScore),
                'url'           => route('articles.show', $article->slug),
            ];
        }

        $this->debugInfo['ranking_method'] = 'typesense_only';

        return $results;
    }

    /**
     * Fungsi pembantu: normalizeTypesenseScore() [private]
     *
     * Menormalisasi skor Typesense ke rentang 0-1 relatif terhadap
     * skor kandidat tertinggi dalam batch yang sama.
     *
     * Parameter:
     * - float $skor      : Skor Typesense mentah dari satu kandidat
     * - array $candidates : Seluruh kandidat untuk menentukan skor maksimal
     *
     * Kembalikan:
     * - float : Skor ternormalisasi dalam rentang [0, 1]
     */
    /**
     * =========================================================================
     * 1. METODE Normalize Typesense Score
     * =========================================================================
     *
     * Fungsi:
     * Menormalisasi normalize typesense score agar konsisten di seluruh pipeline.
     *
     * Alur Proses:
     * 1. Membersihkan teks/kata dari variasi atau typo.
     * 1. Mengubah format ke bentuk standar.
     * 1. Mengembalikan string atau token yang dinormalisasi.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - float
     */
    private function normalizeTypesenseScore(float $score, array $candidates): float
    {
        if (empty($candidates)) return 0;

        $maxScore = max(array_column($candidates, 'typesense_score'));
        if ($maxScore <= 0) return 0;

        return $score / $maxScore;
    }

    /**
     * Fungsi pembantu: calculateTfidfSimilarities() [private]
     *
     * Menghitung nilai Cosine Similarity antara vektor query TF-IDF
     * dan vektor setiap dokumen kandidat. Hasilnya digunakan sebagai
     * sinyal reranking ringan (15%).
     *
     * Parameter:
     * - array $queryVector     : Vektor TF-IDF dari query pengguna
     * - array $documentVectors : Vektor TF-IDF setiap dokumen [docId => vector]
     *
     * Kembalikan:
     * - array : [docId => float similarity]
     */
    /**
     * =========================================================================
     * 1. METODE Calculate Tfidf Similarities
     * =========================================================================
     *
     * Fungsi:
     * Menghitung nilai calculate tfidf similarities berdasarkan input yang diberikan.
     *
     * Alur Proses:
     * 1. Memproses input untuk menghitung calculate tfidf similarities.
     * 1. Menerapkan rumus atau bobot relevansi.
     * 1. Mengembalikan nilai numerik atau vektor.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
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
     * Fungsi pembantu: applyLightBoosting() [private]
     *
     * Menerapkan bonus kecil pada skor TF-IDF untuk dokumen yang judulnya
     * cocok dengan query. Pengaruhnya dibatasi agar tidak mengalahkan sinyal Typesense.
     *
     * Dua jenis boost yang diterapkan:
     * - Judul overlap boost : Proporsi term query yang muncul di judul artikel
     * - Exact frasa boost  : Bonus jika judul artikel mengandung frasa exact dari query
     *
     * Parameter:
     * - array $similarities : [docId => similarity] sebelum boosting
     * - array $dokumen    : Data dokumen termasuk judul dan token judul
     * - array $queryVector  : Vektor TF-IDF query untuk mengambil term query
     *
     * Kembalikan:
     * - array : [docId => similarity] setelah boosting diterapkan
     */
    /**
     * =========================================================================
     * 1. METODE Apply Light Boosting
     * =========================================================================
     *
     * Fungsi:
     * Menerapkan transformasi atau boost pada data apply light boosting.
     *
     * Alur Proses:
     * 1. Menerima input dasar dan aturan boosting.
     * 1. Menghitung nilai tambahan berdasarkan kondisi.
     * 1. Mengembalikan data dengan penyesuaian yang diterapkan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function applyLightBoosting(array $similarities, array $documents, array $queryVector): array
    {
        foreach ($similarities as $docId => $similarity) {
            $document = $documents[$docId] ?? null;
            if (!$document) continue;

            $boost = 0.0;

            // Hitung proporsi term query yang muncul di judul artikel
            $titleTokens    = $document['title_tokens'] ?? [];
            $queryTerms     = array_keys($queryVector);
            $matchedInTitle = 0;

            foreach ($queryTerms as $term) {
                if (in_array($term, $titleTokens)) {
                    $matchedInTitle++;
                }
            }

            // Terapkan judul overlap boost berdasarkan proporsi kecocokan
            if (!empty($queryTerms)) {
                $titleMatchRatio = $matchedInTitle / count($queryTerms);
                $boost += $titleMatchRatio * self::TITLE_MATCH_BOOST;
            }

            // Terapkan exact frasa boost jika judul mengandung frasa exact dari query
            $title       = mb_strtolower($document['title'] ?? '');
            $queryPhrase = implode(' ', $queryTerms);
            if (str_contains($title, $queryPhrase)) {
                $boost += self::EXACT_MATCH_BOOST;
            }

            $similarities[$docId] = max(0, $similarity + $boost);
        }

        return $similarities;
    }

    /**
     * Fungsi pembantu: combineScores() [private]
     *
     * Menggabungkan skor Typesense dan TF-IDF dengan bobot yang telah ditentukan.
     * Sebelum digabungkan, kedua skor dinormalisasi ke rentang 0-1 agar
     * perbandingan bersifat adil.
     *
     * Formula penggabungan:
     * skor_final = (skor_typesense_normalized × 85%) + (skor_tfidf_normalized × 15%)
     *
     * Parameter:
     * - array $typesenseCandidates : Kandidat dari Typesense dengan skor mentah
     * - array $tfidfSimilarities   : Skor TF-IDF [docId => similarity]
     *
     * Kembalikan:
     * - array : [docId => combined_score] sudah diurutkan descending
     */
    /**
     * =========================================================================
     * 1. METODE Combine Scores
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi combine scores di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function combineScores(array $typesenseCandidates, array $tfidfSimilarities): array
    {
        $combinedScores = [];

        // Bangun peta skor Typesense berindeks ID artikel
        $typesenseScores = [];
        foreach ($typesenseCandidates as $candidate) {
            $typesenseScores[$candidate['id']] = $candidate['typesense_score'] ?? 0;
        }

        // Normalisasi skor Typesense ke rentang 0-1
        $maxTypesenseScore = !empty($typesenseScores) ? max($typesenseScores) : 0;
        if ($maxTypesenseScore > 0) {
            foreach ($typesenseScores as $id => $score) {
                $typesenseScores[$id] = $score / $maxTypesenseScore;
            }
        }

        // Normalisasi skor TF-IDF ke rentang 0-1
        $maxTfidfScore = !empty($tfidfSimilarities) ? max($tfidfSimilarities) : 0;
        if ($maxTfidfScore > 0) {
            foreach ($tfidfSimilarities as $id => $score) {
                $tfidfSimilarities[$id] = $score / $maxTfidfScore;
            }
        }

        // Gabungkan skor: 85% Typesense + 15% TF-IDF
        $allIds = array_unique(array_merge(array_keys($typesenseScores), array_keys($tfidfSimilarities)));

        foreach ($allIds as $id) {
            $tsScore   = $typesenseScores[$id] ?? 0;
            $tfidfScore = $tfidfSimilarities[$id] ?? 0;

            $combinedScores[$id] = ($tsScore * self::TYPESENSE_WEIGHT) + ($tfidfScore * self::TFIDF_WEIGHT);
        }

        // Urutkan descending berdasarkan skor gabungan
        arsort($combinedScores);

        return $combinedScores;
    }

    /**
     * Fungsi pembantu: buildFinalResults() [private]
     *
     * Membangun array hasil akhir dari skor gabungan yang sudah diurutkan.
     * Artikel dengan skor di bawah ambang dilewati untuk menjaga
     * kualitas hasil yang ditampilkan ke pengguna.
     *
     * Parameter:
     * - array      $combinedScores : Skor gabungan [docId => skor] sudah terurut
     * - Koleksi $articles       : Koleksi artikel dari database
     * - int        $batas          : Jumlah maksimal hasil
     *
     * Kembalikan:
     * - array : Daftar artikel terformat dengan skor dan confidence level
     */
    /**
     * =========================================================================
     * 1. METODE Build Final Results
     * =========================================================================
     *
     * Fungsi:
     * Membangun objek/struktur build final results untuk pipeline retrieval.
     *
     * Alur Proses:
     * 1. Mempersiapkan data awal untuk build final results.
     * 1. Menggabungkan atribut penting.
     * 1. Mengembalikan objek atau array yang siap dipakai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function buildFinalResults(array $combinedScores, Collection $articles, int $limit): array
    {
        $results     = [];
        $articlesMap = $articles->keyBy('id');

        foreach ($combinedScores as $docId => $score) {
            if (count($results) >= $limit) {
                break;
            }

            if (!isset($articlesMap[$docId])) {
                continue;
            }

            // Lewati hasil yang skornya di bawah ambang minimum relevansi
            if ($score < self::SIMILARITY_THRESHOLD) {
                continue;
            }

            $article = $articlesMap[$docId];

            $results[] = [
                'id'            => $article->id,
                'title'         => $article->title,
                'excerpt'       => $article->excerpt,
                'content'       => $article->content,
                'slug'          => $article->slug,
                'category_id'   => $article->category_id,
                'category_name' => $article->category->name ?? null,
                'similarity'    => round($score, 4),
                'confidence'    => $this->getConfidenceLevel($score),
                'url'           => route('articles.show', $article->slug),
            ];
        }

        return $results;
    }

    /**
     * Fungsi pembantu: getConfidenceLevel() [private]
     *
     * Mengklasifikasikan skor similarity ke dalam tiga level kepercayaan
     * yang digunakan untuk menentukan tampilan antarmuka chatbot.
     *
     * Level confidence menentukan:
     * - 'high'   : Sistem sangat yakin, tampilkan artikel langsung
     * - 'medium' : Cukup relevan, tampilkan dengan indikasi "mungkin membantu"
     * - 'low'    : Kurang relevan, tampilkan tombol "Hubungi Staff"
     *
     * Parameter:
     * - float $similarity : Skor similarity gabungan
     *
     * Kembalikan:
     * - string : 'high', 'medium', atau 'low'
     */
    /**
     * =========================================================================
     * 1. METODE Get Confidence Level
     * =========================================================================
     *
     * Fungsi:
     * Mengambil data get confidence level untuk keperluan logika service.
     *
     * Alur Proses:
     * 1. Menentukan sumber data untuk get confidence level.
     * 1. Mengambil atau memformat data.
     * 1. Mengembalikan hasil dalam struktur yang sesuai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
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
     * Fungsi pembantu: emptyResult() [private]
     *
     * Mengembalikan struktur array kosong yang konsisten ketika tidak ada
     * artikel yang dapat diambil dari database atau Typesense.
     *
     * Parameter:
     * - string $query : Query asli yang dikirim pengguna
     *
     * Kembalikan:
     * - array : Struktur hasil kosong dengan status threshold_met = false
     */
    /**
     * =========================================================================
     * 1. METODE Empty Result
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi empty result di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function emptyResult(string $query): array
    {
        return [
            'results'       => [],
            'query'         => $query,
            'total'         => 0,
            'threshold_met' => false,
            'max_similarity' => 0,
        ];
    }

    /**
     * Fungsi pembantu: prepareDocuments() [private]
     *
     * Menyiapkan representasi dokumen untuk keperluan perhitungan TF-IDF.
     * Setiap artikel diubah menjadi gabungan token dari judul, excerpt, kata kunci,
     * dan konten. Judul mendapat bobot ganda dengan cara token-nya diduplikasi.
     *
     * Alur proses:
     * 1. Preprocessing setiap field artikel (judul, excerpt, kata kunci, konten).
     * 2. Duplikasi token judul untuk meningkatkan bobotnya.
     * 3. Gabungkan semua token dan hitung frekuensi term.
     *
     * Parameter:
     * - Koleksi $articles : Koleksi objek Article
     *
     * Kembalikan:
     * - array : [docId => ['teks', 'frequency', 'judul', 'title_tokens', ...]]
     */
    /**
     * =========================================================================
     * 1. METODE Prepare Documents
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi prepare documents di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function prepareDocuments(Collection $articles): array
    {
        $documents = [];

        foreach ($articles as $article) {
            $titleTokens    = $this->preprocessor->preprocess($article->title);
            $excerptTokens  = $this->preprocessor->preprocess($article->excerpt ?? '');
            $keywordsTokens = $this->preprocessor->preprocess($article->keywords ?? '');
            $contentTokens  = $this->preprocessor->preprocess($article->content);

            // Gabungkan semua token dengan duplikasi judul untuk bobot lebih tinggi
            $allTokens = [];
            foreach ($titleTokens as $token) {
                $allTokens[] = $token;
                $allTokens[] = $token; // Duplikasi token judul sebagai pembobot ekstra
            }
            $allTokens = array_merge($allTokens, $excerptTokens, $keywordsTokens, $contentTokens);

            $frequency = array_count_values($allTokens);

            $documents[$article->id] = [
                'text'         => implode(' ', $allTokens),
                'frequency'    => $frequency,
                'title'        => $article->title,
                'title_tokens' => $titleTokens,
                'excerpt'      => $article->excerpt,
                'keywords'     => $article->keywords,
                'slug'         => $article->slug,
                'category_id'  => $article->category_id,
            ];
        }

        return $documents;
    }

    /**
     * Fungsi pembantu: buildOrRetrieveVectors() [private]
     *
     * Membangun vektor TF-IDF dari dokumen atau mengambilnya dari cache
     * jika sudah tersimpan sebelumnya. Cache key di-generate berdasarkan
     * MD5 dari kumpulan ID dokumen untuk validasi kecocokan data.
     *
     * Parameter:
     * - array $dokumen : Dokumen yang sudah dipersiapkan oleh prepareDocuments()
     *
     * Kembalikan:
     * - array : ['vectors' => [...], 'idf' => [...], 'docCount' => int]
     */
    /**
     * =========================================================================
     * 1. METODE Build Or Retrieve Vectors
     * =========================================================================
     *
     * Fungsi:
     * Membangun objek/struktur build or retrieve vectors untuk pipeline retrieval.
     *
     * Alur Proses:
     * 1. Mempersiapkan data awal untuk build or retrieve vectors.
     * 1. Menggabungkan atribut penting.
     * 1. Mengembalikan objek atau array yang siap dipakai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function buildOrRetrieveVectors(array $documents): array
    {
        $docCount = count($documents);
        $docIds   = implode(',', array_keys($documents));
        $cacheKey = self::VECTOR_CACHE_KEY . ':' . md5($docIds);

        // Cek cache terlebih dahulu agar tidak perlu hitung ulang jika data sama
        $cached = Cache::get($cacheKey);

        if ($cached !== null && ($cached['docCount'] ?? 0) === $docCount) {
            return $cached;
        }

        // Hitung IDF dan vektor TF-IDF dari dokumen
        $documentTermFrequencies = [];
        foreach ($documents as $docId => $doc) {
            $documentTermFrequencies[$docId] = $doc['frequency'];
        }

        $idf = $this->tfidfService->calculateIDF($documentTermFrequencies);

        $vectors = [];
        foreach ($documentTermFrequencies as $docId => $termFreq) {
            $tf          = $this->tfidfService->calculateTF($termFreq);
            $vectors[$docId] = $this->tfidfService->calculateTFIDF($tf, $idf);
        }

        $tfidfData = [
            'vectors'  => $vectors,
            'idf'      => $idf,
            'docCount' => $docCount,
        ];

        // Simpan ke cache untuk digunakan kembali
        Cache::put($cacheKey, $tfidfData, self::VECTOR_CACHE_TTL);

        return $tfidfData;
    }

    /**
     * 2. Fungsi clearCache()
     *
     * Fungsi ini menghapus semua cache yang digunakan oleh ChatbotRetrievalService.
     * Perlu dipanggil ketika ada perubahan data artikel agar cache tidak menjadi basi.
     *
     * Cache yang dibersihkan:
     * - Cache vektor TF-IDF dokumen
     * - Cache nilai IDF korpus
     * - Cache topik dinamis
     * - Cache IDF di TfidfService
     *
     * Kembalikan:
     * - void
     */
    /**
     * =========================================================================
     * 1. METODE Clear Cache
     * =========================================================================
     *
     * Fungsi:
     * Menghapus data atau status internal untuk clear cache.
     *
     * Alur Proses:
     * 1. Menentukan data/entitas yang akan dihapus.
     * 1. Melakukan operasi penghapusan.
     * 1. Mengembalikan status operasional jika perlu.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - void
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
     * 3. Fungsi rebuildCache()
     *
     * Fungsi ini membersihkan cache lama dan membangun ulang statistik IDF
     * dari seluruh artikel yang sudah dipublikasi dan disetujui.
     *
     * Biasanya dipanggil secara manual oleh admin setelah:
     * - Penambahan artikel dalam jumlah banyak
     * - Penghapusan banyak artikel
     * - Perubahan signifikan pada konten artikel
     *
     * Alur proses:
     * 1. Hapus cache lama.
     * 2. Ambil semua artikel aktif dari database.
     * 3. Siapkan representasi dokumen.
     * 4. Hitung IDF dari seluruh korpus.
     *
     * Kembalikan:
     * - array : ['success' => bool, 'dokumen' => int, 'terms' => int]
     */
    /**
     * =========================================================================
     * 1. METODE Rebuild Cache
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi rebuild cache di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    public function rebuildCache(): array
    {
        // Hapus semua cache lama terlebih dahulu
        $this->clearCache();

        // Query ini mengambil semua artikel aktif untuk membangun ulang statistik IDF
        $articles  = Article::where('is_published', true)
            ->where('publish_status', 'approved')
            ->get();

        $documents = $this->prepareDocuments($articles);

        $documentTermFrequencies = [];
        foreach ($documents as $docId => $doc) {
            $documentTermFrequencies[$docId] = $doc['frequency'];
        }

        $idf = $this->tfidfService->calculateIDF($documentTermFrequencies);

        return [
            'success'   => true,
            'documents' => count($documents),
            'terms'     => count($idf),
        ];
    }

    /**
     * 4. Fungsi formatResponse()
     *
     * Fungsi ini mengubah hasil retrieval mentah menjadi format respon
     * yang siap ditampilkan di antarmuka widget chatbot.
     *
     * Format respon mencakup:
     * - Teks respon utama (summary dari artikel teratas)
     * - Daftar artikel yang relevan
     * - Tombol kontak staff (muncul jika confidence rendah)
     * - Level confidence keseluruhan
     *
     * Alur proses:
     * 1. Cek apakah ada hasil — jika tidak, kembalikan respon "tidak ditemukan".
     * 2. Ambil artikel teratas dan jumlah total hasil.
     * 3. Generate teks respon dari excerpt atau konten artikel.
     * 4. Bangun struktur respon lengkap.
     *
     * Parameter:
     * - array $retrievalResult : Hasil dari fungsi ambil()
     *
     * Kembalikan:
     * - array : [
     *     'success'             => bool,
     *     'response'            => string, // Teks respon chatbot
     *     'articles'            => array,  // Daftar artikel relevan
     *     'show_contact_button' => bool,   // Tampilkan tombol kontak staff?
     *     'contact_button_text' => string,
     *     'confidence'          => string  // 'high', 'medium', 'low', atau 'none'
     *   ]
     */
    /**
     * =========================================================================
     * 1. METODE Format Response
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi format response di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    public function formatResponse(array $retrievalResult): array
    {
        if (empty($retrievalResult['results'])) {
            return $this->noResultsResponse();
        }

        $topArticle   = $retrievalResult['results'][0];
        $totalResults = count($retrievalResult['results']);
        $confidence   = $topArticle['confidence'] ?? 'medium';

        $response = $this->generateResponseText($topArticle, $totalResults, $confidence);

        return [
            'success'             => true,
            'response'            => $response,
            'articles'            => $retrievalResult['results'],
            'show_contact_button' => $confidence === 'low',
            'contact_button_text' => 'Masih butuh bantuan? Hubungi staff kami',
            'confidence'          => $confidence,
        ];
    }

    /**
     * Fungsi pembantu: noResultsResponse() [private]
     *
     * Menghasilkan respon standar ketika tidak ada artikel yang ditemukan.
     * Menampilkan pilihan teks secara acak agar chatbot tidak terkesan monoton.
     *
     * Kembalikan:
     * - array : Struktur respon dengan articles kosong dan show_contact_button = true
     */
    /**
     * =========================================================================
     * 1. METODE No Results Response
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi no results response di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    private function noResultsResponse(): array
    {
        $responses = [
            'Maaf, saya belum menemukan artikel yang benar-benar sesuai dengan pertanyaan Anda.',
            'Saya mencari di basis pengetahuan, tetapi belum menemukan jawaban yang tepat.',
            'Pertanyaan Anda menarik, namun saya belum punya artikel yang cocok. Mungkin bisa coba dengan kata kunci lain?',
        ];

        return [
            'success'             => false,
            'response'            => $responses[array_rand($responses)],
            'articles'            => [],
            'show_contact_button' => true,
            'contact_button_text' => 'Buat Tiket untuk Bantuan Lebih Lanjut',
            'confidence'          => 'none',
        ];
    }

    /**
     * Fungsi pembantu: generateResponseText() [private]
     *
     * Menghasilkan teks respon chatbot berdasarkan artikel teratas yang ditemukan.
     * Teks terdiri dari ringkasan singkat dari excerpt atau konten artikel,
     * diikuti ajakan untuk membaca artikel terkait.
     *
     * Parameter:
     * - array  $topArticle   : Data artikel dengan skor tertinggi
     * - int    $totalResults : Total jumlah artikel yang ditemukan
     * - string $confidence   : Level kepercayaan hasil retrieval
     *
     * Kembalikan:
     * - string : Teks respon yang siap ditampilkan
     */
    /**
     * =========================================================================
     * 1. METODE Generate Response Text
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi generate response text di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function generateResponseText(array $topArticle, int $totalResults, string $confidence): string
    {
        $title   = $topArticle['title'];
        $excerpt = $topArticle['excerpt'] ?? '';
        $content = $topArticle['content'] ?? '';

        // Hasilkan ringkasan dari excerpt atau konten artikel
        $summary = $this->generateSummaryFromExcerpt($excerpt, $content, $title);

        // Gabungkan ringkasan dengan ajakan membaca artikel terkait
        return $summary . "\n\nArtikel berikut mungkin dapat membantu Anda:";
    }

    /**
     * Fungsi pembantu: generateSummaryFromExcerpt() [private]
     *
     * Menghasilkan ringkasan 2-4 kalimat dari excerpt atau konten artikel.
     * Excerpt diprioritaskan jika cukup informatif dan tidak terlalu mirip judul.
     * Jika tidak, diambil dari paragraf pertama konten.
     *
     * Parameter:
     * - string $excerpt : Excerpt artikel (bisa mengandung HTML)
     * - string $konten : Konten lengkap artikel (bisa mengandung HTML)
     * - string $judul   : Judul artikel untuk perbandingan kesamaan
     *
     * Kembalikan:
     * - string : Ringkasan teks 2-4 kalimat yang sudah dibersihkan dari HTML
     */
    /**
     * =========================================================================
     * 1. METODE Generate Summary From Excerpt
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi generate summary from excerpt di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function generateSummaryFromExcerpt(string $excerpt, string $content = '', string $title = ''): string
    {
        $excerptText      = $this->stripHtmlTags($excerpt);
        $excerptSentences = preg_split('/(?<=[.!?])\s+/', $excerptText, -1, PREG_SPLIT_NO_EMPTY);

        // Gunakan excerpt jika memiliki minimal 2 kalimat dan tidak terlalu mirip judul
        $useExcerpt = count($excerptSentences) >= 2 && !$this->isTooSimilarToTitle($excerptText, $title);

        if ($useExcerpt) {
            $summary = $this->extractSentences($excerptText, 2, 4);
        } elseif (!empty($content)) {
            // Gunakan paragraf pertama dari konten jika excerpt tidak informatif
            $contentText    = $this->stripHtmlTags($content);
            $firstParagraph = $this->extractFirstParagraph($contentText);
            $summary        = $this->extractSentences($firstParagraph, 2, 4);
        } else {
            return 'Saya menemukan beberapa informasi yang relevan dengan pertanyaan Anda.';
        }

        // Pastikan ringkasan diakhiri dengan tanda baca yang benar
        if (!in_array(substr($summary, -1), ['.', '!', '?'])) {
            $summary .= '.';
        }

        return $summary;
    }

    /**
     * Fungsi pembantu: stripHtmlTags() [private]
     *
     * Membersihkan teks dari tag HTML, mendekode HTML entities,
     * dan menormalisasi whitespace agar teks bersih untuk ditampilkan.
     *
     * Parameter:
     * - string $html : Teks yang mungkin mengandung tag HTML
     *
     * Kembalikan:
     * - string : Teks bersih tanpa HTML
     */
    /**
     * =========================================================================
     * 1. METODE Strip Html Tags
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi strip html tags di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function stripHtmlTags(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Fungsi pembantu: isTooSimilarToTitle() [private]
     *
     * Memeriksa apakah teks excerpt terlalu mirip dengan judul artikel.
     * Jika terlalu mirip, excerpt dianggap tidak informatif dan konten artikel
     * yang digunakan sebagai sumber ringkasan.
     *
     * Dua kondisi yang dianggap "terlalu mirip":
     * 1. Teks mengandung judul atau judul mengandung teks.
     * 2. Teks terlalu pendek (kurang dari 50 karakter).
     *
     * Parameter:
     * - string $teks  : Teks excerpt yang akan diperiksa
     * - string $judul : Judul artikel sebagai pembanding
     *
     * Kembalikan:
     * - bool : true jika teks terlalu mirip judul (tidak informatif)
     */
    /**
     * =========================================================================
     * 1. METODE Is Too Similar To Title
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa kondisi is too similar to title berdasarkan input.
     *
     * Alur Proses:
     * 1. Menilai kondisi yang diminta.
     * 1. Mengecek kondisi pada data input.
     * 1. Mengembalikan boolean.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool
     */
    private function isTooSimilarToTitle(string $text, string $title): bool
    {
        if (empty($title)) {
            return false;
        }

        $textLower  = mb_strtolower($text);
        $titleLower = mb_strtolower($title);

        if (str_contains($textLower, $titleLower) || str_contains($titleLower, $textLower)) {
            return true;
        }

        if (mb_strlen($text) < 50) {
            return true;
        }

        return false;
    }

    /**
     * Fungsi pembantu: extractFirstParagraph() [private]
     *
     * Mengekstraksi paragraf pertama dari teks panjang.
     * Paragraf dipisahkan oleh baris kosong (double newline).
     *
     * Parameter:
     * - string $teks : Teks panjang yang akan diambil paragraf pertamanya
     *
     * Kembalikan:
     * - string : Paragraf pertama yang sudah dibersihkan
     */
    /**
     * =========================================================================
     * 1. METODE Extract First Paragraph
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi extract first paragraph di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function extractFirstParagraph(string $text): string
    {
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($paragraphs)) {
            return $text;
        }

        return trim($paragraphs[0]);
    }

    /**
     * Fungsi pembantu: extractSentences() [private]
     *
     * Mengekstraksi sejumlah kalimat dari teks berdasarkan batas minimum dan maksimum.
     * Kalimat dipisahkan berdasarkan tanda baca akhir kalimat (.!?).
     *
     * Parameter:
     * - string $teks : Teks sumber
     * - int    $min  : Jumlah minimum kalimat
     * - int    $max  : Jumlah maksimum kalimat
     *
     * Kembalikan:
     * - string : Gabungan kalimat terpilih
     */
    /**
     * =========================================================================
     * 1. METODE Extract Sentences
     * =========================================================================
     *
     * Fungsi:
     * Melakukan operasi extract sentences di dalam service.
     *
     * Alur Proses:
     * 1. Memproses input sesuai tujuan method.
     * 1. Mengambil atau mengubah data internal.
     * 1. Mengembalikan hasil sesuai tipe return.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function extractSentences(string $text, int $min, int $max): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($sentences)) {
            return $text;
        }

        // Ambil sejumlah kalimat sesuai batas min-max
        $count             = min($max, max($min, count($sentences)));
        $selectedSentences = array_slice($sentences, 0, $count);

        return implode(' ', $selectedSentences);
    }

    /**
     * 5. Fungsi isGreeting()
     *
     * Fungsi ini mendeteksi apakah query pengguna merupakan sebuah sapaan.
     * Jika terdeteksi sebagai sapaan, chatbot merespons dengan greeting yang sesuai
     * alih-alih melakukan retrieval artikel.
     *
     * Deteksi dilakukan dua cara:
     * 1. Pencocokan substring langsung pada query yang sudah di-lowercase.
     * 2. Pencocokan token hasil preprocessing.
     *
     * Parameter:
     * - string $query : Query dari pengguna
     *
     * Kembalikan:
     * - bool : true jika query terdeteksi sebagai sapaan
     */
    /**
     * =========================================================================
     * 1. METODE Is Greeting
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa kondisi is greeting berdasarkan input.
     *
     * Alur Proses:
     * 1. Menilai kondisi yang diminta.
     * 1. Mengecek kondisi pada data input.
     * 1. Mengembalikan boolean.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool
     */
    public function isGreeting(string $query): bool
    {
        $greetings  = ['halo', 'hai', 'hello', 'hi', 'pagi', 'siang', 'sore', 'malam', 'assalamualaikum', 'permisi'];
        $lowerQuery = mb_strtolower(trim($query));

        // Cek substring langsung pada query
        foreach ($greetings as $greeting) {
            if (str_contains($lowerQuery, $greeting)) {
                return true;
            }
        }

        // Cek token hasil preprocessing sebagai fallback
        $preprocessed = $this->preprocessor->preprocess($query);
        foreach ($preprocessed as $token) {
            if (in_array($token, $greetings)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 6. Fungsi getGreetingResponse()
     *
     * Fungsi ini menghasilkan teks sapaan yang disesuaikan dengan waktu saat ini.
     * Respons dipilih secara acak dari beberapa pilihan agar tidak monoton.
     *
     * Segmentasi waktu:
     * - Sebelum jam 11 : Pagi
     * - Jam 11 - 14    : Siang
     * - Jam 14 - 18    : Sore
     * - Setelah jam 18 : Malam
     *
     * Kembalikan:
     * - string : Teks sapaan yang sesuai waktu
     */
    /**
     * =========================================================================
     * 1. METODE Get Greeting Response
     * =========================================================================
     *
     * Fungsi:
     * Mengambil data get greeting response untuk keperluan logika service.
     *
     * Alur Proses:
     * 1. Menentukan sumber data untuk get greeting response.
     * 1. Mengambil atau memformat data.
     * 1. Mengembalikan hasil dalam struktur yang sesuai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
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

        // Tambahkan pilihan sapaan generik sebagai cadangan
        $greetings[] = 'Halo! Ada yang bisa saya bantu?';

        return $greetings[array_rand($greetings)];
    }

    /**
     * 7. Fungsi getDynamicTopics()
     *
     * Fungsi ini mengambil topik-topik dinamis berdasarkan kategori artikel
     * yang memiliki artikel paling banyak, untuk ditampilkan sebagai
     * pilihan awal di antarmuka chatbot.
     *
     * Data di-cache selama 1 jam untuk mengurangi query berulang ke database.
     *
     * Alur proses:
     * 1. Cek cache — kembalikan jika tersedia.
     * 2. Query kategori yang memiliki artikel aktif, urutkan berdasarkan jumlah artikel.
     * 3. Format dan simpan ke cache.
     *
     * Parameter:
     * - int $batas : Jumlah topik maksimal (default: 5)
     *
     * Kembalikan:
     * - array : [['id', 'type', 'label', 'count'], ...]
     */
    /**
     * =========================================================================
     * 1. METODE Get Dynamic Topics
     * =========================================================================
     *
     * Fungsi:
     * Mengambil data get dynamic topics untuk keperluan logika service.
     *
     * Alur Proses:
     * 1. Menentukan sumber data untuk get dynamic topics.
     * 1. Mengambil atau memformat data.
     * 1. Mengembalikan hasil dalam struktur yang sesuai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    public function getDynamicTopics(int $limit = 5): array
    {
        // Cek cache terlebih dahulu untuk menghemat query database
        $cached = Cache::get(self::TOPIC_CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        // Query ini mengambil kategori yang memiliki artikel aktif, diurutkan
        // berdasarkan jumlah artikel terbanyak agar topik populer tampil duluan
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
                'id'    => $category->id,
                'type'  => 'category',
                'label' => $category->name,
                'count' => $category->article_count,
            ];
        }

        // Simpan ke cache selama 1 jam
        Cache::put(self::TOPIC_CACHE_KEY, $topics, self::TOPIC_CACHE_TTL);

        return array_slice($topics, 0, $limit);
    }

    /**
     * 8. Fungsi getSubtopics()
     *
     * Fungsi ini mengambil subtopik artikel yang relevan berdasarkan label topik.
     * Subtopik didapatkan dengan menjalankan retrieval menggunakan label topik
     * sebagai query, lalu mengambil artikel teratas sebagai subtopik.
     *
     * Parameter:
     * - string $topicLabel : Label topik (nama kategori) sebagai query retrieval
     * - int    $batas      : Jumlah subtopik maksimal (default: 4)
     *
     * Kembalikan:
     * - array : [['id', 'type', 'label', 'excerpt', 'slug', 'url'], ...]
     */
    /**
     * =========================================================================
     * 1. METODE Get Subtopics
     * =========================================================================
     *
     * Fungsi:
     * Mengambil data get subtopics untuk keperluan logika service.
     *
     * Alur Proses:
     * 1. Menentukan sumber data untuk get subtopics.
     * 1. Mengambil atau memformat data.
     * 1. Mengembalikan hasil dalam struktur yang sesuai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    public function getSubtopics(string $topicLabel, int $limit = 4): array
    {
        $subtopics = [];

        // Gunakan retrieval untuk menemukan artikel relevan berdasarkan label topik
        $result = $this->retrieve($topicLabel, $limit + 2);

        foreach ($result['results'] as $article) {
            $subtopics[] = [
                'id'      => $article['id'],
                'type'    => 'article',
                'label'   => $article['title'],
                'excerpt' => $this->truncateText($article['excerpt'] ?? '', 80),
                'slug'    => $article['slug'],
                'url'     => $article['url'],
            ];
        }

        return array_slice($subtopics, 0, $limit);
    }

    /**
     * 9. Fungsi getArticleSuggestion()
     *
     * Fungsi ini mengambil detail satu artikel berdasarkan ID untuk
     * ditampilkan sebagai kartu saran kepada pengguna chatbot.
     *
     * Artikel hanya dikembalikan jika statusnya dipublikasikan dan approved,
     * untuk menjamin kualitas konten yang ditampilkan.
     *
     * Parameter:
     * - int $articleId : ID artikel yang ingin ditampilkan
     *
     * Kembalikan:
     * - array : ['success' => bool, 'article' => array, 'response' => string]
     *         atau ['success' => false, 'pesan' => string] jika tidak ditemukan
     */
    /**
     * =========================================================================
     * 1. METODE Get Article Suggestion
     * =========================================================================
     *
     * Fungsi:
     * Mengambil data get article suggestion untuk keperluan logika service.
     *
     * Alur Proses:
     * 1. Menentukan sumber data untuk get article suggestion.
     * 1. Mengambil atau memformat data.
     * 1. Mengembalikan hasil dalam struktur yang sesuai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    public function getArticleSuggestion(int $articleId): array
    {
        // Query ini mengambil artikel berdasarkan ID dengan memastikan
        // artikel sudah dipublikasi dan disetujui sebelum ditampilkan
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
                'id'      => $article->id,
                'title'   => $article->title,
                'excerpt' => $article->excerpt,
                'slug'    => $article->slug,
                'url'     => route('articles.show', $article->slug),
            ],
            'response' => "Saya menemukan artikel yang mungkin membantu: **{$article->title}**",
        ];
    }

    /**
     * 10. Fungsi getRelatedArticles()
     *
     * Fungsi ini mengambil artikel-artikel yang berkaitan dengan sebuah artikel
     * dengan cara menjalankan retrieval menggunakan judul dan excerpt artikel
     * tersebut sebagai query.
     *
     * Artikel yang sedang dilihat (berdasarkan $articleId) dilewati
     * agar tidak muncul sebagai rekomendasi untuk dirinya sendiri.
     *
     * Parameter:
     * - int $articleId : ID artikel sumber yang ingin dicari artikel terkaitnya
     * - int $batas     : Jumlah artikel terkait maksimal (default: 3)
     *
     * Kembalikan:
     * - array : Daftar artikel terkait atau array kosong jika tidak ditemukan
     */
    /**
     * =========================================================================
     * 1. METODE Get Related Articles
     * =========================================================================
     *
     * Fungsi:
     * Mengambil data get related articles untuk keperluan logika service.
     *
     * Alur Proses:
     * 1. Menentukan sumber data untuk get related articles.
     * 1. Mengambil atau memformat data.
     * 1. Mengembalikan hasil dalam struktur yang sesuai.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array
     */
    public function getRelatedArticles(int $articleId, int $limit = 3): array
    {
        // Query ini mencari artikel sumber berdasarkan ID
        $article = Article::find($articleId);
        if (!$article) {
            return [];
        }

        // Jalankan retrieval menggunakan judul + excerpt sebagai query
        $result = $this->retrieve($article->title . ' ' . $article->excerpt, $limit + 1);

        $related = [];
        foreach ($result['results'] as $result) {
            // Lewati artikel itu sendiri dan hentikan jika sudah mencapai batas
            if ($result['id'] != $articleId && count($related) < $limit) {
                $related[] = $result;
            }
        }

        return $related;
    }

    /**
     * Fungsi pembantu: truncateText() [private]
     *
     * Memotong teks hingga panjang maksimal yang ditentukan dan menambahkan
     * elipsis (...) jika teks dipotong. Menggunakan mb_strlen dan mb_substr
     * untuk mendukung karakter multibyte (Unicode/UTF-8).
     *
     * Parameter:
     * - string $teks   : Teks yang akan dipotong
     * - int    $length : Panjang maksimal karakter
     *
     * Kembalikan:
     * - string : Teks yang sudah dipotong (dengan '...' jika diperlukan)
     */
    /**
     * =========================================================================
     * 1. METODE Truncate Text
     * =========================================================================
     *
     * Fungsi:
     * Memangkas teks agar sesuai batas panjang yang ditentukan.
     *
     * Alur Proses:
     * 1. Menerima teks panjang dan batas karakter.
     * 1. Memangkas teks sesuai aturan.
     * 1. Mengembalikan teks yang dipotong.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string
     */
    private function truncateText(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }

    /**
     * 11. Fungsi clearAllCaches()
     *
     * Fungsi ini menghapus seluruh cache yang terkait dengan layanan
     * retrieval chatbot, termasuk cache di TfidfService.
     * Memanggil clearCache() dan mencatat aktivitas penghapusan ke log.
     *
     * Kembalikan:
     * - void
     */
    /**
     * =========================================================================
     * 1. METODE Clear All Caches
     * =========================================================================
     *
     * Fungsi:
     * Menghapus data atau status internal untuk clear all caches.
     *
     * Alur Proses:
     * 1. Menentukan data/entitas yang akan dihapus.
     * 1. Melakukan operasi penghapusan.
     * 1. Mengembalikan status operasional jika perlu.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - void
     */
    public function clearAllCaches(): void
    {
        $this->clearCache();
        Log::info('All chatbot caches cleared');
    }
}
