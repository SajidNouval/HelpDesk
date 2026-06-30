<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Log;

/**
 * =========================================================================
 * SERVICE IMPORTANT PHRASE - PENINGKATAN PERINGKAT BERDASARKAN FRASA PENTING
 * =========================================================================
 * 
 * Layanan ini menerapkan BOOSTING FRASA PENTING untuk mengatasi masalah
 * di mana query kontekstual pendek mengembalikan artikel yang salah karena
 * ranking berbasis token individual.
 * 
 * Masalah yang Diatasi:
 * Query seperti "wifi tidak terhubung" mengembalikan artikel "Internet lambat"
 * alih-alih artikel "Wifi tidak terhubung" karena token individual mendominasi.
 * 
 * Solusi:
 * Mendeteksi dan memberikan boost pada frasa penting yang mewakili intent
 * pengguna sebenarnya, seperti:
 * - tidak terhubung
 * - putus nyambung
 * - gagal login
 * - tidak terbaca
 * - tidak muncul
 * - tidak merespon
 * - koneksi gagal
 * 
 * Digunakan oleh:
 * - AdvancedRetrievalService (untuk boosting skor retrieval)
 */
class ImportantPhraseService
{
    // =========================================================================
    // KONSTANTA BOOSTING
    // =========================================================================
    // Bonus dasar untuk pencocokan frasa di konten
    private const PHRASE_MATCH_BONUS = 0.4;
    
    // Bonus kuat untuk pencocokan frasa di judul
    private const TITLE_PHRASE_BONUS = 0.6;
    
    // Bonus maksimum untuk frasa exact query di judul
    private const EXACT_QUERY_PHRASE_BONUS = 0.8;
    
    // Bonus tambahan untuk frasa yang sejalan dengan kategori
    private const PHRASE_CATEGORY_BOOST = 0.15;
    
    // Panjang frasa minimum yang dipertimbangkan
    private const MIN_PHRASE_LENGTH = 2;

    // =========================================================================
    // DAFTAR FRASA PENTING - INDIKATOR INTENT SEBENARNYA
    // =========================================================================
    // Frasa-frasa ini mewakili intent PENGGUNA SEBENARNYA dan harus
    // MENDOMINASI ranking. Ketika frasa ini muncul dalam query, artikel
    // yang mengandung frasa ini harus mendapat peringkat LEBIH TINGGI
    // daripada artikel yang hanya cocok dengan token individual.
    private array $importantPhrases = [
        // Masalah koneksi
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
        
        // Masalah deteksi
        'tidak terbaca',
        'tidak terdeteksi',
        'tidak muncul',
        'tidak kedetect',
        'tidak dikenali',
        
        // Masalah login/akses
        'gagal login',
        'tidak bisa login',
        'gagal masuk',
        'tidak bisa masuk',
        'terkunci',
        'akun terkunci',
        
        // Masalah respons
        'tidak merespon',
        'tidak respon',
        'tidak responsif',
        'tidak bereaksi',
        'diam saja',
        
        // Masalah fungsionalitas
        'tidak berfungsi',
        'tidak bisa digunakan',
        'tidak bisa dipakai',
        'tidak mau',
        'tidak bisa',
        'gagal berfungsi',
        
        // Masalah tampilan
        'tidak muncul',
        'hilang tiba-tiba',
        'menghilang',
        'blank',
        'layar hitam',
        'layar biru',
        
        // Masalah performa
        'sangat lambat',
        'lemot parah',
        'macet total',
        'hang',
        'freeze',
        'not responding',
        
        // Masalah error
        'error terus',
        'muncul error',
        'pesan error',
        'kode error',
        'notifikasi error',
    ];

    // =========================================================================
    // KATEGORI FRASA - Untuk boosting spesifik domain
    // =========================================================================
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

    private array $debugInfo = [];

    /**
     * =========================================================================
     * 1. METODE DETECT PHRASES - DETEKSI FRASA PENTING
     * =========================================================================
     * 
     * Fungsi: Mendeteksi frasa penting dalam query pengguna.
     * 
     * Alur Proses:
     * 1. Normalisasi query ke lowercase
     * 2. Urutkan frasa penting berdasarkan panjang (terpanjang dulu)
     * 3. Cek setiap frasa apakah ada dalam query
     * 4. Simpan posisi dan kategori setiap frasa yang ditemukan
     * 5. Hapus frasa yang overlap (pertahankan yang lebih panjang)
     * 6. Kembalikan array frasa yang terdeteksi
     * 
     * Parameter:
     * - string $query: Query pengguna
     * 
     * Output:
     * - array: Daftar frasa terdeteksi dengan posisi dan kategori
     */
    public function detectPhrases(string $query): array
    {
        $queryLower = strtolower(trim($query));
        $detectedPhrases = [];

        // Urutkan frasa berdasarkan panjang (terpanjang dulu) untuk pencocokan multi-kata
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

        // Hapus frasa yang overlap (pertahankan yang lebih panjang)
        $detectedPhrases = $this->removeOverlappingPhrases($detectedPhrases);

        $this->debugInfo['detected_phrases'] = $detectedPhrases;

        return $detectedPhrases;
    }

    /**
     * =========================================================================
     * 2. METODE REMOVE OVERLAPPING PHRASES - HAPUS FRASA OVERLAP (PRIVATE)
     * =========================================================================
     * 
     * Fungsi: Menghapus frasa yang overlap, mempertahankan yang lebih panjang.
     * 
     * Alur Proses:
     * 1. Jika hanya 0-1 frasa, kembalikan langsung
     * 2. Urutkan frasa berdasarkan posisi
     * 3. Iterasi dan pertahankan hanya frasa yang tidak overlap
     * 
     * Parameter:
     * - array $frasa: Daftar frasa yang mungkin overlap
     * 
     * Output:
     * - array: Daftar frasa tanpa overlap
     */
    private function removeOverlappingPhrases(array $phrases): array
    {
        if (count($phrases) <= 1) {
            return $phrases;
        }

        // Urutkan berdasarkan posisi
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
     * =========================================================================
     * 3. METODE GET PHRASE CATEGORY - DAPATKAN KATEGORI FRASA (PRIVATE)
     * =========================================================================
     * 
     * Fungsi: Mendapatkan kategori dari sebuah frasa.
     * 
     * Alur Proses:
     * 1. Iterasi setiap kategori di phraseCategories
     * 2. Cek apakah frasa ada di kategori tersebut
     * 3. Kembalikan nama kategori jika ditemukan
     * 4. Kembalikan null jika tidak ditemukan
     * 
     * Parameter:
     * - string $frasa: Frasa yang dicari kategorinya
     * 
     * Output:
     * - string|null: Nama kategori atau null
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
     * =========================================================================
     * 4. METODE CALCULATE PHRASE SCORE - HITUNG SKOR FRASA
     * =========================================================================
     * 
     * Fungsi: Menghitung skor pencocokan frasa untuk sebuah dokumen.
     * Ini adalah metode scoring utama yang diintegrasikan ke hybrid ranking.
     * 
     * Alur Proses:
     * 1. Normalisasi judul, konten, dan excerpt ke lowercase
     * 2. Untuk setiap frasa terdeteksi:
     *    a. Cek di judul (prioritas tertinggi, bonus 0.6)
     *    b. Cek di excerpt (bonus 0.32)
     *    c. Cek di konten (bonus 0.4)
     * 3. Cek apakah full query ada di judul (bonus tambahan 0.8)
     * 4. Jumlahkan semua bonus (max 1.0)
     * 
     * Parameter:
     * - string $query: Query asli
     * - array $detectedPhrases: Frasa yang terdeteksi
     * - array $dokumen: Data dokumen (judul, teks, excerpt)
     * 
     * Output:
     * - array: Skor detail termasuk phrase_matches, total_bonus, dll.
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

            // Cek judul dulu (prioritas tertinggi)
            if (str_contains($title, $phrase)) {
                $matchInfo['in_title'] = true;
                $bonus = self::TITLE_PHRASE_BONUS;
                
                // Bonus tambahan jika frasa di awal judul
                if (mb_strpos($title, $phrase) === 0) {
                    $bonus += 0.1;
                }
                
                $matchInfo['bonus'] = $bonus;
                $titlePhraseMatches[] = $phrase;
            }
            // Cek excerpt
            elseif (str_contains($excerpt, $phrase)) {
                $matchInfo['in_excerpt'] = true;
                $matchInfo['bonus'] = self::PHRASE_MATCH_BONUS * 0.8;
            }
            // Cek konten
            elseif (str_contains($content, $phrase)) {
                $matchInfo['in_content'] = true;
                $matchInfo['bonus'] = self::PHRASE_MATCH_BONUS;
            }

            $phraseMatches[] = $matchInfo;
            $totalBonus += $matchInfo['bonus'];
            $maxBonus = max($maxBonus, $matchInfo['bonus']);
        }

        // Cek apakah full query (atau bagian penting) ada di judul
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

        // Batasi total bonus
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
     * =========================================================================
     * 5. METODE CALCULATE NGRAM OVERLAP - HITUNG OVERLAP N-GRAM
     * =========================================================================
     * 
     * Fungsi: Menghitung overlap n-gram antara query dan dokumen.
     * Mendukung pencocokan bigram (2-kata) dan trigram (3-kata).
     * 
     * Alur Proses:
     * 1. Ekstrak kata dari query
     * 2. Buat bigram dari query, cek apakah ada di dokumen
     * 3. Buat trigram dari query, cek apakah ada di dokumen
     * 4. Hitung skor untuk setiap kecocokan
     * 5. Batasi skor (max 0.5 untuk bigram, 0.5 untuk trigram)
     * 
     * Parameter:
     * - string $query: Query pengguna
     * - array $dokumen: Data dokumen
     * 
     * Output:
     * - array: bigram_matches, trigram_matches, skor masing-masing
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

        // Buat bigram dari query
        if (count($queryWords) >= 2) {
            for ($i = 0; $i < count($queryWords) - 1; $i++) {
                $bigram = $queryWords[$i] . ' ' . $queryWords[$i + 1];
                
                if (str_contains($fullText, $bigram)) {
                    $bigramMatches[] = $bigram;
                    $bigramScore += 0.15; // Setiap bigram cocok menambah 0.15
                    
                    // Bonus tambahan jika bigram ada di judul
                    if (str_contains($title, $bigram)) {
                        $bigramScore += 0.1;
                    }
                }
            }
        }

        // Buat trigram dari query
        if (count($queryWords) >= 3) {
            for ($i = 0; $i < count($queryWords) - 2; $i++) {
                $trigram = $queryWords[$i] . ' ' . $queryWords[$i + 1] . ' ' . $queryWords[$i + 2];
                
                if (str_contains($fullText, $trigram)) {
                    $trigramMatches[] = $trigram;
                    $trigramScore += 0.25; // Setiap trigram cocok menambah 0.25 (sinyal lebih kuat)
                    
                    // Bonus tambahan jika trigram ada di judul
                    if (str_contains($title, $trigram)) {
                        $trigramScore += 0.15;
                    }
                }
            }
        }

        // Batasi skor
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
     * =========================================================================
     * 6. METODE GET PHRASE BOOST SCORE - DAPATKAN SKOR BOOST FRASA
     * =========================================================================
     * 
     * Fungsi: Mendapatkan skor boosting gabungan dari frasa dan n-gram.
     * Ini adalah metode utama yang dipanggil untuk frasa-based ranking enhancement.
     * 
     * Alur Proses:
     * 1. Deteksi frasa penting dalam query
     * 2. Hitung skor pencocokan frasa (jika ada frasa terdeteksi)
     * 3. Hitung overlap n-gram
     * 4. Gabungkan skor (phrase_boost + ngram_boost)
     * 5. Batasi total boost (max 1.0)
     * 
     * Parameter:
     * - string $query: Query pengguna
     * - array $dokumen: Data dokumen
     * 
     * Output:
     * - array: phrase_boost, ngram_boost, total_boost, detected_phrases, dll.
     */
    public function getPhraseBoostScore(string $query, array $document): array
    {
        // Langkah 1: Deteksi frasa penting dalam query
        $detectedPhrases = $this->detectPhrases($query);

        // Langkah 2: Hitung skor pencocokan frasa
        $phraseScore = [];
        if (!empty($detectedPhrases)) {
            $phraseScore = $this->calculatePhraseScore($query, $detectedPhrases, $document);
        }

        // Langkah 3: Hitung overlap n-gram
        $ngramResult = $this->calculateNgramOverlap($query, $document);

        // Langkah 4: Evaluasi kecocokan exact query phrase di judul secara independen
        // Ini memastikan query seperti "setting printer" yang persis ada di judul
        // mendapat boost meskipun query tidak mengandung kata masalah/error curasi.
        $exactQueryPhraseBonus = 0.0;
        $title = strtolower($document['title'] ?? '');
        $queryLower = strtolower(trim($query));
        $queryWords = explode(' ', $queryLower);
        $importantQueryWords = array_filter($queryWords, fn($w) => mb_strlen($w) > 2);
        
        if (count($importantQueryWords) >= 2) {
            $importantQueryPhrase = implode(' ', $importantQueryWords);
            if (str_contains($title, $importantQueryPhrase)) {
                $exactQueryPhraseBonus = self::EXACT_QUERY_PHRASE_BONUS;
                $this->debugInfo['exact_query_phrase_match'] = $importantQueryPhrase;
            }
        }

        // Langkah 5: Gabungkan skor
        $phraseBoost = $phraseScore['total_bonus'] ?? 0;
        
        // Jika detectedPhrases kosong, kita belum menjalankan calculatePhraseScore.
        // Tambahkan bonus exact query phrase secara manual ke phraseBoost.
        if (empty($detectedPhrases)) {
            $phraseBoost += $exactQueryPhraseBonus;
        }
        
        $ngramBoost = $ngramResult['total_ngram_score'] ?? 0;
        
        // Total boost
        $totalBoost = $phraseBoost + $ngramBoost;
        $totalBoost = min($totalBoost, 1.0); // Batasi max 1.0

        return [
            'phrase_boost' => $phraseBoost,
            'ngram_boost' => $ngramBoost,
            'total_boost' => $totalBoost,
            'detected_phrases' => $detectedPhrases,
            'phrase_matches' => $phraseScore['phrase_matches'] ?? [],
            'title_phrase_matches' => array_unique(array_merge(
                $phraseScore['title_phrase_matches'] ?? [],
                $exactQueryPhraseBonus > 0 ? [$importantQueryPhrase] : []
            )),
            'bigram_matches' => $ngramResult['bigram_matches'],
            'trigram_matches' => $ngramResult['trigram_matches'],
            'has_important_phrase' => !empty($detectedPhrases) || ($exactQueryPhraseBonus > 0),
            'debug_info' => $this->debugInfo,
        ];
    }

    /**
     * =========================================================================
     * 7. METODE HAS IMPORTANT PHRASE - CEK ADANYA FRASA PENTING
     * =========================================================================
     * 
     * Fungsi: Memeriksa apakah query mengandung frasa penting apa pun.
     * 
     * Alur Proses:
     * 1. Deteksi frasa dalam query
     * 2. Kembalikan true jika ada frasa terdeteksi
     * 
     * Parameter:
     * - string $query: Query pengguna
     * 
     * Output:
     * - bool: Benar jika ada frasa penting
     */
    public function hasImportantPhrase(string $query): bool
    {
        $phrases = $this->detectPhrases($query);
        return !empty($phrases);
    }

    /**
     * =========================================================================
     * 8. METODE GET ALL PHRASES - DAPATKAN SEMUA FRASA
     * =========================================================================
     * 
     * Fungsi: Mendapatkan semua frasa penting (untuk debugging/testing).
     * 
     * Output:
     * - array: Daftar semua frasa penting
     */
    public function getAllPhrases(): array
    {
        return $this->importantPhrases;
    }

    /**
     * =========================================================================
     * 9. METODE GET PHRASES BY CATEGORY - DAPATKAN FRASA PER KATEGORI
     * =========================================================================
     * 
     * Fungsi: Mendapatkan frasa berdasarkan kategori tertentu.
     * 
     * Parameter:
     * - string $kategori: Nama kategori
     * 
     * Output:
     * - array: Daftar frasa untuk kategori tersebut
     */
    public function getPhrasesByCategory(string $category): array
    {
        return $this->phraseCategories[$category] ?? [];
    }

    /**
     * =========================================================================
     * 10. METODE ADD PHRASE - TAMBAHKAN FRASA BARU
     * =========================================================================
     * 
     * Fungsi: Menambahkan frasa kustom baru (berguna untuk domain-specific customization).
     * 
     * Alur Proses:
     * 1. Cek apakah frasa belum ada di importantPhrases
     * 2. Tambahkan ke importantPhrases
     * 3. Jika ada kategori, tambahkan juga ke phraseCategories
     * 
     * Parameter:
     * - string $frasa: Frasa yang ditambahkan
     * - string|null $kategori: Kategori frasa (opsional)
     * 
     * Output:
     * - void
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
     * =========================================================================
     * 11. METODE GET DEBUG INFO - DAPATKAN INFO DEBUG
     * =========================================================================
     * 
     * Fungsi: Mendapatkan informasi debugging.
     * 
     * Output:
     * - array: Informasi debug
     */
    public function getDebugInfo(): array
    {
        return $this->debugInfo;
    }

    /**
     * =========================================================================
     * 12. METODE CLEAR DEBUG INFO - BERSIHKAN INFO DEBUG
     * =========================================================================
     * 
     * Fungsi: Membersihkan informasi debugging.
     * 
     * Output:
     * - void
     */
    public function clearDebugInfo(): void
    {
        $this->debugInfo = [];
    }
}