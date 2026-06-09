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
     * =========================================================================
     * 1. METODE RETRIEVE
     * =========================================================================
     *
     * Fungsi:
     * Melakukan pencarian artikel chatbot dengan menggabungkan Typesense (85%) dan TF-IDF (15%).
     *
     * Alur Proses:
     * 1. Menormalisasi query dan mendeteksi domain.
     * 2. Melakukan retrieval via Typesense sebagai sumber kandidat utama.
     * 3. Mengambil artikel untuk keperluan TF-IDF reranking.
     * 4. Menghitung TF-IDF ringan dan boosting judul.
     * 5. Menggabungkan skor Typesense + TF-IDF.
     * 6. Membangun hasil akhir dengan filter ambang.
     *
     * Query yang Digunakan:
     * - Article::whereIn('id', $candidateIds)->where('is_published', true)->where('publish_status', 'approved')->with('category')->get(): Ambil artikel berdasarkan ID kandidat
     * - Article::where('is_published', true)->where('publish_status', 'approved')->with('category')->get(): Ambil artikel fallback
     *
     * Output:
     * - array dengan hasil artikel terurut, query, normalized_query, total, threshold_met, max_similarity, domain_detected, detected_domain, typesense_used, typesense_candidates, debug
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
     * =========================================================================
     * 1. METODE NORMALIZE QUERY
     * =========================================================================
     *
     * Fungsi:
     * Menormalisasi query pengguna untuk koreksi typo dasar.
     *
     * Alur Proses:
     * 1. Menerima query mentah dari pengguna.
     * 2. Memanggil PreprocessingService untuk koreksi typo.
     * 3. Mengembalikan query yang sudah dinormalisasi.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string query yang sudah dinormalisasi
     */
    private function normalizeQuery(string $query): string
    {
        return $this->preprocessor->normalizeTypos($query);
    }

    /**
     * =========================================================================
     * 1. METODE GET ARTICLES FOR RERANKING
     * =========================================================================
     *
     * Fungsi:
     * Mengambil artikel untuk keperluan TF-IDF reranking dengan prioritas kandidat Typesense.
     *
     * Alur Proses:
     * 1. Menggunakan kandidat dari Typesense jika tersedia.
     * 2. Mengambil artikel dari database berdasarkan ID kandidat.
     * 3. Fallback ke database dengan filter domain jika Typesense tidak tersedia.
     * 4. Mengembalikan koleksi artikel untuk diproses TF-IDF.
     *
     * Query yang Digunakan:
     * - Article::whereIn('id', $candidateIds)->where('is_published', true)->where('publish_status', 'approved')->with('category')->get(): Ambil artikel berdasarkan ID kandidat
     * - Article::where('is_published', true)->where('publish_status', 'approved')->with('category')->get(): Ambil artikel fallback
     *
     * Output:
     * - Collection objek Article untuk diproses TF-IDF
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
     * =========================================================================
     * 1. METODE BUILD TYPESENSE ONLY RESULTS
     * =========================================================================
     *
     * Fungsi:
     * Membangun hasil akhir menggunakan ranking Typesense semata ketika TF-IDF tidak menghasilkan vektor query.
     *
     * Alur Proses:
     * 1. Menerima kandidat dari Typesense dan koleksi artikel.
     * 2. Menormalisasi skor Typesense relatif terhadap skor tertinggi.
     * 3. Memformat artikel dengan skor yang dinormalisasi.
     * 4. Mengembalikan hasil terbatas sesuai limit.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array daftar artikel terformat dengan skor Typesense
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
     * =========================================================================
     * 1. METODE NORMALIZE TYPESENSE SCORE
     * =========================================================================
     *
     * Fungsi:
     * Menormalisasi skor Typesense ke rentang 0-1 relatif terhadap skor tertinggi.
     *
     * Alur Proses:
     * 1. Menerima skor mentah dan semua kandidat.
     * 2. Menentukan skor maksimal dari semua kandidat.
     * 3. Membagi skor dengan skor maksimal.
     * 4. Mengembalikan skor ternormalisasi.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - float skor ternormalisasi dalam rentang [0, 1]
     */
    private function normalizeTypesenseScore(float $score, array $candidates): float
    {
        if (empty($candidates)) return 0;

        $maxScore = max(array_column($candidates, 'typesense_score'));
        if ($maxScore <= 0) return 0;

        return $score / $maxScore;
    }

    /**
     * =========================================================================
     * 1. METODE CALCULATE TFIDF SIMILARITIES
     * =========================================================================
     *
     * Fungsi:
     * Menghitung Cosine Similarity antara vektor query TF-IDF dan vektor dokumen kandidat.
     *
     * Alur Proses:
     * 1. Menerima vektor query dan vektor dokumen.
     * 2. Menghitung similarity untuk setiap dokumen.
     * 3. Mengembalikan array similarity per dokumen.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array [docId => float similarity]
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
     * =========================================================================
     * 1. METODE APPLY LIGHT BOOSTING
     * =========================================================================
     *
     * Fungsi:
     * Menerapkan bonus kecil pada skor TF-IDF untuk dokumen dengan judul yang cocok query.
     *
     * Alur Proses:
     * 1. Menerima similarity, dokumen, dan vektor query.
     * 2. Menghitung judul overlap boost berdasarkan proporsi kecocokan.
     * 3. Menghitung exact frasa boost jika judul mengandung frasa exact.
     * 4. Mengembalikan similarity setelah boosting diterapkan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array [docId => similarity] setelah boosting diterapkan
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
     * =========================================================================
     * 1. METODE COMBINE SCORES
     * =========================================================================
     *
     * Fungsi:
     * Menggabungkan skor Typesense dan TF-IDF dengan bobot 85% dan 15%.
     *
     * Alur Proses:
     * 1. Menerima kandidat Typesense dan similarity TF-IDF.
     * 2. Menormalisasi kedua skor ke rentang 0-1.
     * 3. Menggabungkan dengan formula: (Typesense × 85%) + (TF-IDF × 15%).
     * 4. Mengurutkan hasil descending dan mengembalikan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array [docId => combined_score] sudah diurutkan descending
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
     * =========================================================================
     * 1. METODE BUILD FINAL RESULTS
     * =========================================================================
     *
     * Fungsi:
     * Membangun array hasil akhir dari skor gabungan yang sudah diurutkan.
     *
     * Alur Proses:
     * 1. Menerima skor gabungan terurut dan koleksi artikel.
     * 2. Melewati artikel dengan skor di bawah threshold.
     * 3. Memformat artikel dengan skor dan confidence level.
     * 4. Mengembalikan hasil terbatas sesuai limit.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array daftar artikel terformat dengan skor dan confidence level
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
     * =========================================================================
     * 1. METODE GET CONFIDENCE LEVEL
     * =========================================================================
     *
     * Fungsi:
     * Mengklasifikasikan skor similarity ke dalam level kepercayaan.
     *
     * Alur Proses:
     * 1. Menerima skor similarity.
     * 2. Memeriksa skor terhadap threshold.
     * 3. Mengembalikan level confidence: high, medium, atau low.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string 'high', 'medium', atau 'low'
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
     * =========================================================================
     * 1. METODE EMPTY RESULT
     * =========================================================================
     *
     * Fungsi:
     * Mengembalikan struktur array kosong ketika tidak ada artikel yang ditemukan.
     *
     * Alur Proses:
     * 1. Menerima query asli.
     * 2. Mengembalikan struktur hasil kosong dengan status threshold_met = false.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array struktur hasil kosong
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
     * =========================================================================
     * 1. METODE PREPARE DOCUMENTS
     * =========================================================================
     *
     * Fungsi:
     * Menyiapkan representasi dokumen untuk perhitungan TF-IDF.
     *
     * Alur Proses:
     * 1. Menerima koleksi artikel.
     * 2. Preprocessing setiap field artikel (judul, excerpt, kata kunci, konten).
     * 3. Duplikasi token judul untuk meningkatkan bobot.
     * 4. Menggabungkan semua token dan menghitung frekuensi term.
     * 5. Mengembalikan array dokumen yang disiapkan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array [docId => ['text', 'frequency', 'title', 'title_tokens', ...]]
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
     * =========================================================================
     * 1. METODE BUILD OR RETRIEVE VECTORS
     * =========================================================================
     *
     * Fungsi:
     * Membangun vektor TF-IDF dari dokumen atau mengambilnya dari cache.
     *
     * Alur Proses:
     * 1. Menerima dokumen yang sudah dipersiapkan.
     * 2. Generate cache key berdasarkan MD5 dari ID dokumen.
     * 3. Cek cache dan kembalikan jika tersedia.
     * 4. Hitung IDF dan vektor TF-IDF jika tidak ada di cache.
     * 5. Simpan ke cache dan kembalikan hasil.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array ['vectors' => [...], 'idf' => [...], 'docCount' => int]
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
     * =========================================================================
     * 1. METODE CLEAR CACHE
     * =========================================================================
     *
     * Fungsi:
     * Menghapus semua cache yang digunakan oleh ChatbotRetrievalService.
     *
     * Alur Proses:
     * 1. Menghapus cache vektor TF-IDF dokumen.
     * 2. Menghapus cache nilai IDF korpus.
     * 3. Menghapus cache topik dinamis.
     * 4. Menghapus cache IDF di TfidfService.
     * 5. Mencatat aktivitas penghapusan ke log.
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
     * =========================================================================
     * 1. METODE REBUILD CACHE
     * =========================================================================
     *
     * Fungsi:
     * Membersihkan cache lama dan membangun ulang statistik IDF dari seluruh artikel.
     *
     * Alur Proses:
     * 1. Menghapus semua cache lama.
     * 2. Mengambil semua artikel aktif dari database.
     * 3. Menyiapkan representasi dokumen.
     * 4. Menghitung IDF dari seluruh korpus.
     * 5. Mengembalikan hasil rebuild cache.
     *
     * Query yang Digunakan:
     * - Article::where('is_published', true)->where('publish_status', 'approved')->get(): Ambil semua artikel aktif
     *
     * Output:
     * - array ['success' => bool, 'documents' => int, 'terms' => int]
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
     * =========================================================================
     * 1. METODE FORMAT RESPONSE
     * =========================================================================
     *
     * Fungsi:
     * Mengubah hasil retrieval mentah menjadi format respon untuk antarmuka chatbot.
     *
     * Alur Proses:
     * 1. Menerima hasil retrieval mentah.
     * 2. Cek apakah ada hasil - jika tidak, kembalikan respon tidak ditemukan.
     * 3. Ambil artikel teratas dan jumlah total hasil.
     * 4. Generate teks respon dari excerpt atau konten artikel.
     * 5. Bangun struktur respon lengkap dengan tombol kontak jika confidence rendah.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array dengan success, response, articles, show_contact_button, contact_button_text, confidence
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
     * =========================================================================
     * 1. METODE NO RESULTS RESPONSE
     * =========================================================================
     *
     * Fungsi:
     * Menghasilkan respon standar ketika tidak ada artikel yang ditemukan.
     *
     * Alur Proses:
     * 1. Memilih teks respon secara acak dari pilihan yang tersedia.
     * 2. Mengembalikan struktur respon dengan articles kosong dan show_contact_button = true.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array struktur respon tidak ditemukan
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
     * =========================================================================
     * 1. METODE GENERATE RESPONSE TEXT
     * =========================================================================
     *
     * Fungsi:
     * Menghasilkan teks respon chatbot berdasarkan artikel teratas yang ditemukan.
     *
     * Alur Proses:
     * 1. Menerima artikel teratas, total hasil, dan level confidence.
     * 2. Generate ringkasan dari excerpt atau konten artikel.
     * 3. Gabungkan ringkasan dengan ajakan membaca artikel terkait.
     * 4. Mengembalikan teks respon yang siap ditampilkan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string teks respon yang siap ditampilkan
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
     * =========================================================================
     * 1. METODE GENERATE SUMMARY FROM EXCERPT
     * =========================================================================
     *
     * Fungsi:
     * Menghasilkan ringkasan 2-4 kalimat dari excerpt atau konten artikel.
     *
     * Alur Proses:
     * 1. Menerima excerpt, konten, dan judul artikel.
     * 2. Membersihkan excerpt dari HTML dan memecah menjadi kalimat.
     * 3. Gunakan excerpt jika memiliki minimal 2 kalimat dan tidak terlalu mirip judul.
     * 4. Gunakan paragraf pertama konten jika excerpt tidak informatif.
     * 5. Mengembalikan ringkasan 2-4 kalimat yang sudah dibersihkan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string ringkasan teks 2-4 kalimat yang sudah dibersihkan dari HTML
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
     * =========================================================================
     * 1. METODE STRIP HTML TAGS
     * =========================================================================
     *
     * Fungsi:
     * Membersihkan teks dari tag HTML dan menormalisasi whitespace.
     *
     * Alur Proses:
     * 1. Menerima teks yang mungkin mengandung tag HTML.
     * 2. Menghapus semua tag HTML.
     * 3. Mendekode HTML entities.
     * 4. Menormalisasi whitespace dan trim.
     * 5. Mengembalikan teks bersih tanpa HTML.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string teks bersih tanpa HTML
     */
    private function stripHtmlTags(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * =========================================================================
     * 1. METODE IS TOO SIMILAR TO TITLE
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah teks excerpt terlalu mirip dengan judul artikel.
     *
     * Alur Proses:
     * 1. Menerima teks excerpt dan judul artikel.
     * 2. Memeriksa apakah teks mengandung judul atau sebaliknya.
     * 3. Memeriksa apakah teks terlalu pendek (kurang dari 50 karakter).
     * 4. Mengembalikan true jika terlalu mirip atau terlalu pendek.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool true jika teks terlalu mirip judul
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
     * =========================================================================
     * 1. METODE EXTRACT FIRST PARAGRAPH
     * =========================================================================
     *
     * Fungsi:
     * Mengekstraksi paragraf pertama dari teks panjang.
     *
     * Alur Proses:
     * 1. Menerima teks panjang.
     * 2. Memisahkan teks menjadi paragraf berdasarkan baris kosong.
     * 3. Mengembalikan paragraf pertama yang sudah dibersihkan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string paragraf pertama yang sudah dibersihkan
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
     * =========================================================================
     * 1. METODE EXTRACT SENTENCES
     * =========================================================================
     *
     * Fungsi:
     * Mengekstraksi sejumlah kalimat dari teks berdasarkan batas minimum dan maksimum.
     *
     * Alur Proses:
     * 1. Menerima teks sumber, batas minimum, dan batas maksimum.
     * 2. Memisahkan teks menjadi kalimat berdasarkan tanda baca akhir.
     * 3. Mengambil sejumlah kalimat sesuai batas min-max.
     * 4. Mengembalikan gabungan kalimat terpilih.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string gabungan kalimat terpilih
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
     * =========================================================================
     * 1. METODE IS GREETING
     * =========================================================================
     *
     * Fungsi:
     * Mendeteksi apakah query pengguna merupakan sebuah sapaan.
     *
     * Alur Proses:
     * 1. Menerima query dari pengguna.
     * 2. Mencocokkan substring langsung pada query yang sudah di-lowercase.
     * 3. Mencocokan token hasil preprocessing sebagai fallback.
     * 4. Mengembalikan true jika query terdeteksi sebagai sapaan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool true jika query terdeteksi sebagai sapaan
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
     * =========================================================================
     * 1. METODE GET GREETING RESPONSE
     * =========================================================================
     *
     * Fungsi:
     * Menghasilkan teks sapaan yang disesuaikan dengan waktu saat ini.
     *
     * Alur Proses:
     * 1. Menentukan waktu saat ini.
     * 2. Memilih sapaan berdasarkan segmentasi waktu (pagi, siang, sore, malam).
     * 3. Menambahkan pilihan sapaan generik sebagai cadangan.
     * 4. Mengembalikan sapaan yang dipilih secara acak.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string teks sapaan yang sesuai waktu
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
     * =========================================================================
     * 1. METODE GET DYNAMIC TOPICS
     * =========================================================================
     *
     * Fungsi:
     * Mengambil topik-topik dinamis berdasarkan kategori artikel dengan artikel terbanyak.
     *
     * Alur Proses:
     * 1. Cek cache dan kembalikan jika tersedia.
     * 2. Query kategori yang memiliki artikel aktif, urutkan berdasarkan jumlah artikel.
     * 3. Format topik dan simpan ke cache selama 1 jam.
     * 4. Mengembalikan array topik dinamis.
     *
     * Query yang Digunakan:
     * - Category::whereHas('articles', function ($query) { $query->where('is_published', true)->where('publish_status', 'approved'); })->withCount(['articles as article_count' => function ($query) { $query->where('is_published', true)->where('publish_status', 'approved'); }])->orderByDesc('article_count')->limit($limit)->get(['id', 'name']): Ambil kategori dengan artikel terbanyak
     *
     * Output:
     * - array [['id', 'type', 'label', 'count'], ...]
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
     * =========================================================================
     * 1. METODE GET SUBTOPICS
     * =========================================================================
     *
     * Fungsi:
     * Mengambil subtopik artikel yang relevan berdasarkan label topik.
     *
     * Alur Proses:
     * 1. Menerima label topik dan batas jumlah subtopik.
     * 2. Menjalankan retrieval menggunakan label topik sebagai query.
     * 3. Mengambil artikel teratas sebagai subtopik.
     * 4. Memformat dan mengembalikan array subtopik.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array [['id', 'type', 'label', 'excerpt', 'slug', 'url'], ...]
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
     * =========================================================================
     * 1. METODE GET ARTICLE SUGGESTION
     * =========================================================================
     *
     * Fungsi:
     * Mengambil detail satu artikel berdasarkan ID untuk ditampilkan sebagai kartu saran.
     *
     * Alur Proses:
     * 1. Menerima ID artikel.
     * 2. Query artikel berdasarkan ID dengan filter published dan approved.
     * 3. Memformat artikel dengan detail yang diperlukan.
     * 4. Mengembalikan array artikel atau pesan tidak ditemukan.
     *
     * Query yang Digunakan:
     * - Article::where('is_published', true)->where('publish_status', 'approved')->with('category')->find($articleId): Ambil artikel berdasarkan ID
     *
     * Output:
     * - array ['success' => bool, 'article' => array, 'response' => string] atau ['success' => false, 'message' => string]
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
     * =========================================================================
     * 1. METODE GET RELATED ARTICLES
     * =========================================================================
     *
     * Fungsi:
     * Mengambil artikel-artikel yang berkaitan dengan sebuah artikel.
     *
     * Alur Proses:
     * 1. Menerima ID artikel sumber dan batas jumlah artikel terkait.
     * 2. Query artikel sumber berdasarkan ID.
     * 3. Jalankan retrieval menggunakan judul dan excerpt sebagai query.
     * 4. Lewati artikel itu sendiri dan batasi hasil sesuai limit.
     * 5. Mengembalikan daftar artikel terkait.
     *
     * Query yang Digunakan:
     * - Article::find($articleId): Ambil artikel sumber berdasarkan ID
     *
     * Output:
     * - array daftar artikel terkait atau array kosong jika tidak ditemukan
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
     * =========================================================================
     * 1. METODE TRUNCATE TEXT
     * =========================================================================
     *
     * Fungsi:
     * Memotong teks hingga panjang maksimal yang ditentukan dan menambahkan elipsis.
     *
     * Alur Proses:
     * 1. Menerima teks dan panjang maksimal.
     * 2. Memeriksa panjang teks menggunakan mb_strlen untuk dukungan Unicode.
     * 3. Jika teks lebih panjang dari batas, potong menggunakan mb_substr dan tambahkan '...'.
     * 4. Mengembalikan teks yang sudah dipotong atau teks asli jika tidak perlu dipotong.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string teks yang sudah dipotong (dengan '...' jika diperlukan)
     */
    private function truncateText(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length) . '...';
    }

    /**
     * =========================================================================
     * 1. METODE CLEAR ALL CACHES
     * =========================================================================
     *
     * Fungsi:
     * Menghapus seluruh cache yang terkait dengan layanan retrieval chatbot.
     *
     * Alur Proses:
     * 1. Memanggil clearCache() untuk menghapus cache di service ini.
     * 2. Mencatat aktivitas penghapusan ke log.
     * 3. Mengembalikan void.
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
