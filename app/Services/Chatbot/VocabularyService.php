<?php

namespace App\Services\Chatbot;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * =========================================================================
 * SERVICE VOCABULARY - KOREKSI TYPO BERBASIS KOSAKATA DINAMIS
 * =========================================================================
 * 
 * Layanan ini mengekstrak kosakata dari artikel (judul, kata kunci, konten,
 * kategori) dan menggunakannya untuk koreksi typo cerdas menggunakan
 * Levenshtein distance.
 * 
 * Masalah yang Diatasi:
 * Query seperti "virusssss" atau "wifii" tidak dikenali karena typo,
 * sehingga pencarian gagal menemukan artikel yang relevan.
 * 
 * Solusi:
 * - Ekstraksi kosakata otomatis dari database artikel
 * - Koreksi typo berbasis Levenshtein distance yang adaptif
 *   (ambang berdasarkan panjang kata)
 * - Normalisasi karakter berulang (misalnya virusssss -> virus)
 * - Threshold kesamaan yang dapat dikonfigurasi (lebih rendah untuk
 *   istilah teknis panjang)
 * - Mode hybrid yang menggabungkan koreksi kurasi dan dinamis
 * - Logging debug yang komprehensif
 * - Caching untuk performa
 * 
 * Digunakan oleh:
 * - PreprocessingService (untuk normalisasi query)
 * - ChatbotController (untuk koreksi typo)
 */
class VocabularyService
{
    // =========================================================================
    // KONFIGURASI CACHE
    // =========================================================================
    // Key cache untuk menyimpan kosakata
    private const CACHE_KEY = 'chatbot_vocabulary';
    
    // TTL cache dalam detik (1 jam)
    private const CACHE_TTL = 3600;

    // =========================================================================
    // AMBANG BATAS KOREKSI TYPO
    // =========================================================================
    // Threshold kesamaan minimum (0.0 hingga 1.0)
    // Hanya koreksi jika kesamaan >= ambang ini
    private const MIN_SIMILARITY = 0.70;
    
    // Threshold kesamaan minimum untuk istilah teknis panjang (>8 karakter)
    private const MIN_SIMILARITY_LONG_WORDS = 0.65;
    
    // Panjang kata minimum yang akan dipertimbangkan untuk koreksi
    private const MIN_WORD_LENGTH = 3;
    
    // Maksimal kemunculan karakter berulang (apa pun di atas akan dikompresi ke 1)
    // Contoh: virusssss -> virus (bukan viruss)
    private const MAX_REPEATED_CHARS = 1;

    // =========================================================================
    // PETA TYPO KURASI - Istilah Domain Spesifik Prioritas Tinggi
    // =========================================================================
    // Peta typo kurasi untuk istilah spesifik domain dengan prioritas tinggi.
    // Ini dipelihara secara manual untuk istilah IT yang kritis.
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

    // =========================================================================
    // KOSAKATA DINAMIS
    // =========================================================================
    // Kosakata dinamis yang diekstrak dari artikel
    private ?array $vocabulary = null;

    /**
     * =========================================================================
     * 1. METODE GET ADAPTIVE MAX DISTANCE - AMBIL JARAK MAKSIMUM ADAPTIF (PRIVATE)
     * =========================================================================
     * 
     * Fungsi: Mendapatkan ambang Levenshtein distance yang adaptif berdasarkan
     * panjang kata.
     * 
     * Alur Proses:
     * 1. Hitung panjang kata
     * 2. Tentukan max distance berdasarkan kategori panjang:
     *    - Kata pendek (<=5): max distance = 1 (lebih ketat)
     *    - Kata sedang (6-8): max distance = 2
     *    - Kata panjang (>8): max distance = 3 (lebih toleran)
     * 3. Kembalikan max distance
     * 
     * Parameter:
     * - string $word: Kata yang akan diperiksa
     * 
     * Output:
     * - int: Maximum Levenshtein distance yang diizinkan
     */
    private function getAdaptiveMaxDistance(string $word): int
    {
        $length = mb_strlen($word);
        
        if ($length <= 5) {
            return 1;  // Lebih ketat untuk kata pendek
        } elseif ($length <= 8) {
            return 2;  // Standar untuk kata sedang
        } else {
            return 3;  // Lebih toleran untuk istilah teknis panjang
        }
    }

    /**
     * =========================================================================
     * 2. METODE GET ADAPTIVE MIN SIMILARITY - AMBIL KESEIMBANGAN MINIMUM ADAPTIF (PRIVATE)
     * =========================================================================
     * 
     * Fungsi: Mendapatkan ambang kesamaan minimum berdasarkan panjang kata.
     * 
     * Alur Proses:
     * 1. Hitung panjang kata
     * 2. Jika kata panjang (>8), gunakan ambang lebih rendah (65%)
     * 3. Jika tidak, gunakan ambang standar (70%)
     * 
     * Parameter:
     * - string $word: Kata yang akan diperiksa
     * 
     * Output:
     * - float: Threshold kesamaan minimum (0.0 hingga 1.0)
     */
    private function getAdaptiveMinSimilarity(string $word): float
    {
        $length = mb_strlen($word);
        
        if ($length > 8) {
            return self::MIN_SIMILARITY_LONG_WORDS;  // 0.65 untuk kata panjang
        }
        
        return self::MIN_SIMILARITY;  // 0.70 untuk lainnya
    }

    /**
     * =========================================================================
     * 3. METODE NORMALIZE REPEATED CHARS - NORMALISASI KARAKTER BERULANG
     * =========================================================================
     * 
     * Fungsi: Menormalisasi karakter berulang dalam sebuah token.
     * 
     * Alur Proses:
     * 1. Gunakan regex untuk menemukan karakter berulang
     * 2. Kompresi karakter berulang di atas MAX_REPEATED_CHARS kemunculan
     * 3. Kembalikan token yang sudah dinormalisasi
     * 
     * Contoh:
     *   virusssss -> virus
     *   wifiii -> wifi
     *   lemottt -> lemot
     *   errorrrr -> error
     * 
     * Parameter:
     * - string $token: Token yang akan dinormalisasi
     * 
     * Output:
     * - string: Token yang sudah dinormalisasi
     */
    public function normalizeRepeatedChars(string $token): string
    {
        // Gunakan regex untuk menemukan karakter berulang dan mengompresinya
        // Pattern: mencocokkan karakter apa pun diikuti oleh karakter yang sama 2+ kali
        // Replacement: hanya menyimpan MAX_REPEATED_CHARS kemunculan
        $pattern = '/(.)\1{2,}/';
        
        $result = preg_replace_callback($pattern, function ($matches) {
            $char = $matches[1];
            // Hanya simpan MAX_REPEATED_CHARS kemunculan
            return str_repeat($char, self::MAX_REPEATED_CHARS);
        }, $token);
        
        return $result ?? $token;
    }

    /**
     * =========================================================================
     * 4. METODE LOAD VOCABULARY - MUAT KOSAKATA
     * =========================================================================
     * 
     * Fungsi: Memuat kosakata dari cache atau bangun jika tidak exists.
     * 
     * Alur Proses:
     * 1. Cek apakah sudah dimuat di memori, kembalikan jika ada
     * 2. Coba muat dari cache
     * 3. Jika cache miss atau kosong, bangun ulang kosakata
     * 4. Kembalikan kosakata (tidak pernah null)
     * 
     * Output:
     * - array: Array kata unik yang sudah dinormalisasi
     * 
     * Catatan Penting:
     * Metode ini TIDAK PERNAH mengembalikan null - selalu mengembalikan array
     * (kosong jika tidak ada data yang tersedia)
     */
    public function loadVocabulary(): array
    {
        // Jika sudah dimuat di memori, kembalikan
        if (is_array($this->vocabulary)) {
            return $this->vocabulary;
        }
        
        // Coba muat dari cache
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && !empty($cached)) {
            $this->vocabulary = $cached;
            Log::debug('Vocabulary loaded from cache', ['word_count' => count($cached)]);
            return $this->vocabulary;
        }
        
        // Cache miss atau kosong - bangun ulang kosakata
        Log::info('Vocabulary cache miss or empty, rebuilding...');
        return $this->rebuildVocabulary();
    }

    /**
     * =========================================================================
     * 5. METODE REBUILD VOCABULARY - BANGUN ULANG KOSAKATA (PRIVATE)
     * =========================================================================
     * 
     * Fungsi: Membangun ulang kosakata dari database artikel.
     * 
     * Alur Proses:
     * 1. Ambil semua artikel dipublikasikan dengan status approved
     * 2. Ekstrak kata dari:
     *    - Judul artikel
     *    - Kata kunci artikel
     *    - Konten artikel (500 kata pertama untuk performa)
     *    - Nama kategori
     * 3. Normalisasi dan deduplikasi
     * 4. Urutkan berdasarkan panjang kata
     * 5. Simpan ke cache
     * 6. Kembalikan kosakata
     * 
     * Output:
     * - array: Array kata unik yang sudah dinormalisasi (tidak pernah null)
     */
    private function rebuildVocabulary(): array
    {
        $vocabulary = [];
        
        try {
            // Ekstrak dari judul artikel
            $articles = Article::where('is_published', true)
                ->where('publish_status', 'approved')
                ->get(['title', 'keywords', 'content']);
            
            foreach ($articles as $article) {
                // Ekstrak dari judul
                $titleWords = $this->extractWords($article->title);
                $vocabulary = array_merge($vocabulary, $titleWords);
                
                // Ekstrak dari kata kunci
                if ($article->keywords) {
                    $keywordWords = $this->extractWords($article->keywords);
                    $vocabulary = array_merge($vocabulary, $keywordWords);
                }
                
                // Ekstrak dari konten (batasi 500 kata pertama untuk performa)
                if ($article->content) {
                    $contentWords = $this->extractWords($article->content, 500);
                    $vocabulary = array_merge($vocabulary, $contentWords);
                }
            }
            
            // Ekstrak dari nama kategori
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
        
        // Normalisasi dan deduplikasi
        $vocabulary = array_unique($vocabulary);
        $vocabulary = array_filter($vocabulary, fn($word) => mb_strlen($word) >= self::MIN_WORD_LENGTH);
        
        // Urutkan berdasarkan panjang kata (kata pendek dulu untuk pencocokan lebih baik)
        usort($vocabulary, fn($a, $b) => mb_strlen($a) <=> mb_strlen($b));
        
        // Cache kosakata (bahkan jika kosong, untuk menghindari rebuild berulang)
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
     * =========================================================================
     * 6. METODE BUILD VOCABULARY - BANGUN KOSAKATA
     * =========================================================================
     * 
     * Fungsi: Membangun kosakata dari database artikel (alias untuk rebuildVocabulary
     * untuk backward compatibility).
     * 
     * Output:
     * - array: Array kata unik yang sudah dinormalisasi
     */
    public function buildVocabulary(): array
    {
        return $this->rebuildVocabulary();
    }

    /**
     * =========================================================================
     * 7. METODE EXTRACT WORDS - EKSTRAK KATA (PRIVATE)
     * =========================================================================
     * 
     * Fungsi: Mengekstrak kata dari teks.
     * 
     * Alur Proses:
     * 1. Konversi ke lowercase
     * 2. Hapus tanda baca dan karakter khusus
     * 3. Pisahkan menjadi kata-kata
     * 4. Batasi jumlah kata jika diperlukan
     * 
     * Parameter:
     * - string $teks: Teks yang akan diekstrak kata-katanya
     * - int $maxWords: Maksimal jumlah kata yang akan diekstrak (0 = tidak terbatas)
     * 
     * Output:
     * - array: Array kata yang sudah dinormalisasi
     */
    private function extractWords(string $text, int $maxWords = 0): array
    {
        // Konversi ke lowercase
        $text = mb_strtolower($text);
        
        // Hapus tanda baca dan karakter khusus
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        
        // Pisahkan menjadi kata-kata
        $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        
        // Batasi kata jika diperlukan
        if ($maxWords > 0 && count($words) > $maxWords) {
            $words = array_slice($words, 0, $maxWords);
        }
        
        return $words;
    }

    /**
     * =========================================================================
     * 8. METODE NORMALIZE QUERY - NORMALISASI QUERY
     * =========================================================================
     * 
     * Fungsi: Menormalisasi query dengan mengoreksi typo menggunakan kosakata dinamis.
     * 
     * Alur Proses:
     * 1. Muat kosakata menggunakan metode safe yang tidak pernah mengembalikan null
     * 2. Cek safety - pastikan kosakata selalu array
     * 3. Tokenisasi query
     * 4. Untuk setiap token:
     *    a. Normalisasi karakter berulang (virusssss -> virus)
     *    b. Lookup peta typo kurasi
     *    c. Koreksi kosakata dinamis (Levenshtein adaptif)
     * 5. Gabungkan token yang sudah dikoreksi
     * 6. Log hasil normalisasi
     * 
     * Parameter:
     * - string $query: Query input
     * 
     * Output:
     * - array: ['original' => string, 'normalized' => string, 'corrections' => array]
     */
    public function normalizeQuery(string $query): array
    {
        // Muat kosakata menggunakan metode safe yang tidak pernah mengembalikan null
        $this->loadVocabulary();
        
        // CEK KEAMANAN: Pastikan kosakata selalu array
        // Ini mencegah crash in_array() ketika kosakata null
        if (!is_array($this->vocabulary) || empty($this->vocabulary)) {
            Log::warning('Vocabulary empty - skipping typo normalization', [
                'query' => $query,
                'vocabulary_type' => gettype($this->vocabulary),
                'vocabulary_count' => is_array($this->vocabulary) ? count($this->vocabulary) : 'null'
            ]);
            
            // Kembalikan query asli tanpa normalisasi
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
            
            // Lewati token yang sangat pendek
            if (mb_strlen($lowerToken) < self::MIN_WORD_LENGTH) {
                $correctedTokens[] = $token;
                continue;
            }
            
            // LANGKAH 1: Normalisasi karakter berulang SEBELUM koreksi lainnya
            // Contoh: virusssss -> virus, wifiii -> wifi, lemottt -> lemot
            $compressedToken = $this->normalizeRepeatedChars($lowerToken);
            
            // Log kompresi untuk debugging
            if ($compressedToken !== $lowerToken) {
                Log::debug('Repeated character normalization', [
                    'original_token' => $lowerToken,
                    'compressed_token' => $compressedToken
                ]);
            }
            
            // Cek apakah token yang dikompresi sudah ada di kosakata
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
            
            // LANGKAH 2: Coba peta typo kurasi (pada token yang dikompresi)
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
            
            // LANGKAH 3: Koreksi kosakata dinamis menggunakan Levenshtein adaptif
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
     * =========================================================================
     * 9. METODE FIND BEST MATCH - CARI KECOCOKAN TERBAIK (PRIVATE)
     * =========================================================================
     * 
     * Fungsi: Menemukan kata yang paling cocok dari kosakata menggunakan
     * Levenshtein distance.
     * 
     * Alur Proses:
     * 1. Cek keamanan - pastikan kosakata array
     * 2. Dapatkan ambang adaptif berdasarkan panjang token
     * 3. Hitung max length difference untuk filtering awal
     * 4. Iterasi kosakata, hitung Levenshtein distance
     * 5. Gunakan ambang adaptif untuk filter
     * 6. Hitung similarity untuk kandidat terbaik
     * 7. Kembalikan kecocokan terbaik atau null
     * 
     * Parameter:
     * - string $token: Token yang akan dicocokkan (harus sudah compressed/normalized)
     * 
     * Output:
     * - array|null: ['word' => string, 'distance' => int, 'similarity' => float, 'maxDistance' => int] atau null
     */
    private function findBestMatch(string $token): ?array
    {
        // KEAMANAN: Pastikan kosakata array sebelum iterasi
        if (!is_array($this->vocabulary) || empty($this->vocabulary)) {
            return null;
        }
        
        // Dapatkan ambang adaptif berdasarkan panjang token
        $maxDistance = $this->getAdaptiveMaxDistance($token);
        $minSimilarity = $this->getAdaptiveMinSimilarity($token);
        
        // Hitung max length difference untuk filtering awal
        // Untuk adaptive distance, kita gunakan window sedikit lebih besar untuk menghindari missing candidates
        $lengthFilterWindow = max($maxDistance + 1, 3);
        
        $bestMatch = null;
        $bestDistance = PHP_INT_MAX;
        $bestSimilarity = 0.0;
        
        foreach ($this->vocabulary as $word) {
            // Lewati jika perbedaan panjang kata terlalu besar (kemungkinan kecil cocok)
            if (abs(mb_strlen($word) - mb_strlen($token)) > $lengthFilterWindow) {
                continue;
            }
            
            $distance = levenshtein($token, $word);
            
            // Gunakan ambang max distance adaptif
            if ($distance <= $maxDistance && $distance < $bestDistance) {
                $similarity = $this->calculateSimilarity($token, $word, $distance);
                
                // Gunakan ambang minimum similarity adaptif
                if ($similarity >= $minSimilarity) {
                    $bestMatch = $word;
                    $bestDistance = $distance;
                    $bestSimilarity = $similarity;
                    
                    // Jika menemukan kecocokan yang sangat dekat, bisa berhenti lebih awal
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
     * =========================================================================
     * 10. METODE CALCULATE SIMILARITY - HITUNG KESEIMBANGAN (PRIVATE)
     * =========================================================================
     * 
     * Fungsi: Menghitung kesamaan antara dua string.
     * 
     * Parameter:
     * - string $str1: String pertama
     * - string $str2: String kedua
     * - int $distance: Levenshtein distance
     * 
     * Output:
     * - float: Skor kesamaan (0.0 hingga 1.0)
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
     * =========================================================================
     * 11. METODE GET STATS - DAPATKAN STATISTIK
     * =========================================================================
     * 
     * Fungsi: Mendapatkan statistik kosakata.
     * 
     * Output:
     * - array: Statistik kosakata (total_words, domain_terms, curated_typos, dll.)
     */
    public function getStats(): array
    {
        // Gunakan loadVocabulary() yang tidak pernah mengembalikan null
        $this->loadVocabulary();
        
        // KEAMANAN: Pastikan kosakata array
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
     * =========================================================================
     * 12. METODE CLEAR CACHE - BERSIHKAN CACHE
     * =========================================================================
     * 
     * Fungsi: Membersihkan cache kosakata.
     * 
     * Digunakan ketika artikel diperbarui.
     * 
     * Output:
     * - void
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->vocabulary = null;
        
        Log::info('Vocabulary cache cleared');
    }

    /**
     * =========================================================================
     * 13. METODE GET CURATED TYPO MAP - DAPATKAN PETA TYPO KURASI
     * =========================================================================
     * 
     * Fungsi: Mendapatkan peta typo kurasi.
     * 
     * Output:
     * - array: Peta typo kurasi
     */
    public function getCuratedTypoMap(): array
    {
        return $this->curatedTypoMap;
    }

    /**
     * =========================================================================
     * 14. METODE ADD CURATED TYPO - TAMBAHKAN TYPO KURASI
     * =========================================================================
     * 
     * Fungsi: Menambahkan pemetaan typo kurasi baru.
     * 
     * Parameter:
     * - string $typo: Typo
     * - string $correct: Ejaan yang benar
     * 
     * Output:
     * - void
     */
    public function addCuratedTypo(string $typo, string $correct): void
    {
        $this->curatedTypoMap[mb_strtolower($typo)] = mb_strtolower($correct);
    }

    /**
     * =========================================================================
     * 15. METODE NEEDS CORRECTION - PERIKSA KEBUTUHAN KOREKSI
     * =========================================================================
     * 
     * Fungsi: Memeriksa apakah sebuah kata memerlukan koreksi.
     * 
     * Alur Proses:
     * 1. Muat kosakata
     * 2. Cek apakah kata sudah ada di kosakata (tidak perlu koreksi)
     * 3. Cek di peta kurasi (perlu koreksi jika ada)
     * 4. Cek apakah dekat dengan kata kosakata apa pun
     * 
     * Parameter:
     * - string $word: Kata yang akan diperiksa
     * 
     * Output:
     * - bool: Benar jika perlu koreksi, false jika tidak
     */
    public function needsCorrection(string $word): bool
    {
        // Gunakan loadVocabulary() yang tidak pernah mengembalikan null
        $this->loadVocabulary();
        
        // KEAMANAN: Pastikan kosakata array
        $vocabulary = is_array($this->vocabulary) ? $this->vocabulary : [];
        
        $lowerWord = mb_strtolower($word);
        
        // Cek apakah sudah ada di kosakata (aman - kosakata dijamin array)
        if (in_array($lowerWord, $vocabulary)) {
            return false;
        }
        
        // Cek peta kurasi
        if (isset($this->curatedTypoMap[$lowerWord])) {
            return true;
        }
        
        // Cek apakah dekat dengan kata kosakata apa pun
        $bestMatch = $this->findBestMatch($lowerWord);
        return $bestMatch !== null;
    }
}