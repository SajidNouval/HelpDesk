<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class PreprocessingService
{
    /**
     * Kamus typo untuk normalisasi query
     * Format: 'typo' => 'correct'
     * 
     * Ditambahkan secara konfigurabel untuk menangani kesalahan pengetikan umum
     */
    private array $typoDictionary = [
        // WiFi related
        'wfi' => 'wifi',
        'wiifi' => 'wifi',
        'wfii' => 'wifi',
        'wifii' => 'wifi',
        'wi-fi' => 'wifi',
        'wifi' => 'wifi',
        
        // Internet related
        'intenet' => 'internet',
        'internrt' => 'internet',
        'intrnet' => 'internet',
        'inet' => 'internet',
        'intrnt' => 'internet',
        
        // Komputer related
        'kompter' => 'komputer',
        'komputr' => 'komputer',
        'kompoter' => 'komputer',
        'komputerr' => 'komputer',
        
        // Jaringan related
        'jaringn' => 'jaringan',
        'jaringa' => 'jaringan',
        'jaringann' => 'jaringan',
        'jaringn' => 'jaringan',
        
        // Printer related
        'prnter' => 'printer',
        'printter' => 'printer',
        'printe' => 'printer',
        'priner' => 'printer',
        'pritner' => 'printer',
        'priter' => 'printer',
        'prinetr' => 'printer',
        
        // Email related
        'emai' => 'email',
        'emaill' => 'email',
        'emil' => 'email',
        'emial' => 'email',
        'eamil' => 'email',
        'emal' => 'email',
        
        // Login related
        'logn' => 'login',
        'login' => 'login',
        'logi' => 'login',
        'lojin' => 'login',
        
        // Password related
        'pasword' => 'password',
        'passwod' => 'password',
        'passwrod' => 'password',
        'paswrod' => 'password',
        
        // Koneksi related
        'koneksi' => 'koneksi',
        'koneksii' => 'koneksi',
        'koneks' => 'koneksi',
        'koneksia' => 'koneksi',
        
        // Lemot (slow) related
        'lemot' => 'lemot',
        'lemott' => 'lemot',
        'lemot' => 'lemot',
        
        // Error related
        'eror' => 'error',
        'errror' => 'error',
        'eroor' => 'error',
        
        // Tidak related
        'tidk' => 'tidak',
        'tida' => 'tidak',
        'tiadak' => 'tidak',
        
        // Bisa related
        'bsa' => 'bisa',
        'bisa' => 'bisa',
        'biisa' => 'bisa',
        
        // Mau related
        'mau' => 'mau',
        'mau' => 'mau',
        
        // Sudah related
        'sudah' => 'sudah',
        'sudah' => 'sudah',
        'sdah' => 'sudah',
        
        // Belum related
        'belum' => 'belum',
        'belumm' => 'belum',
        'blm' => 'belum',
        
        // Cara related
        'cara' => 'cara',
        'caara' => 'cara',
        'caraa' => 'cara',
        
        // Bagaimana related
        'bagaimana' => 'bagaimana',
        'gimana' => 'bagaimana',
        'gmna' => 'bagaimana',
        
        // Kenapa related
        'kenapa' => 'kenapa',
        'knpa' => 'kenapa',
        'knapa' => 'kenapa',
        
        // Apa related
        'apa' => 'apa',
        'apaa' => 'apa',
        
        // Kenapa related
        'mengapa' => 'mengapa',
        'mengap' => 'mengapa',
        
        // Setting related
        'seting' => 'setting',
        'setting' => 'setting',
        'seting' => 'setting',
        'setelan' => 'setelan',
        
        // Instal related
        'instal' => 'instal',
        'install' => 'instal',
        'instalsi' => 'instalasi',
        'instalasi' => 'instalasi',
        
        // Update related
        'update' => 'update',
        'upadate' => 'update',
        'updt' => 'update',
        
        // Driver related
        'driver' => 'driver',
        'driverr' => 'driver',
        'diver' => 'driver',
        
        // Software related
        'software' => 'software',
        'softwere' => 'software',
        'softwre' => 'software',
        
        // Hardware related
        'hardware' => 'hardware',
        'hardwere' => 'hardware',
        'hardwre' => 'hardware',
        
        // File related
        'file' => 'file',
        'fie' => 'file',
        'filee' => 'file',
        
        // Data related
        'data' => 'data',
        'dat' => 'data',
        'daa' => 'data',
        
        // Download related
        'download' => 'download',
        'donlod' => 'download',
        'downlod' => 'download',
        
        // Upload related
        'upload' => 'upload',
        'uplod' => 'upload',
        'uplaod' => 'upload',
        
        // Docker related
        'docker' => 'docker',
        'dockerr' => 'docker',
        
        // Error related (with double r from compression)
        'error' => 'error',
        'errorr' => 'error',
        
        // Virus related (with double s from compression)
        'virus' => 'virus',
        'viruss' => 'virus',
        
        // Printer related (with double r from compression)
        'printer' => 'printer',
        'printerr' => 'printer',
        
        // Internet related (with double t from compression)
        'internet' => 'internet',
        'internett' => 'internet',
        
        // Komputer related (with double r from compression)
        'komputer' => 'komputer',
        'komputerr' => 'komputer',
    ];

    /**
     * Token konteks untuk boosting - kata-kata yang menunjukkan domain spesifik
     * Ketika query mengandung token ini, artikel dengan domain terkait harus di-boost
     */
    private array $contextTokens = [
        // Networking domain
        'wifi' => ['wifi', 'jaringan', 'wireless', 'lan', 'wan', 'router', 'access point', 'hotspot'],
        'jaringan' => ['jaringan', 'network', 'lan', 'wan', 'koneksi', 'konektivitas'],
        'internet' => ['internet', 'online', 'web', 'browser', 'website'],
        
        // Hardware domain
        'komputer' => ['komputer', 'pc', 'laptop', 'notebook', 'desktop'],
        'printer' => ['printer', 'cetak', 'mencetak', 'printing'],
        'hardware' => ['hardware', 'perangkat keras', 'komponen'],
        
        // Software domain
        'software' => ['software', 'aplikasi', 'program', 'perangkat lunak'],
        'driver' => ['driver', 'device driver', 'perangkat'],
        
        // Account domain
        'login' => ['login', 'masuk', 'akun', 'account', 'auth'],
        'password' => ['password', 'kata sandi', 'sand', 'passwd'],
        'email' => ['email', 'surel', 'mail', 'surat elektronik'],
    ];

    /**
     * IT-specific generic helpdesk terms that should have extremely low weight
     * These terms are too common in helpdesk articles and should NOT dominate TF-IDF ranking
     */
    private array $itGenericTerms = [
        'cara',
        'mengatasi',
        'solusi',
        'tutorial',
        'panduan',
        'tips',
        'langkah',
        'metode',
    ];

    /**
     * Important domain/technical tokens that should be strongly boosted
     * These are the KEY meaningful terms that should drive retrieval
     * 
     * IMPORTANT KEYWORD BOOSTING: When query contains these keywords,
     * articles containing these keywords get MASSIVE boost to ensure
     * they rank higher than generic articles.
     */
    private array $importantDomainTokens = [
        // Hardware domain
        'komputer', 'laptop', 'pc', 'desktop', 'notebook',
        'printer', 'scanner', 'mouse', 'keyboard', 'monitor',
        
        // Network domain
        'wifi', 'jaringan', 'internet', 'router', 'switch', 'hub',
        'lan', 'wan', 'ethernet', 'wireless', 'hotspot',
        
        // Software/System domain
        'email', 'website', 'aplikasi', 'software', 'driver',
        'browser', 'windows', 'linux', 'android', 'ios',
        
        // Database domain
        'database', 'mysql', 'postgresql', 'mongodb', 'sql',
        
        // Container/DevOps domain
        'docker', 'kubernetes', 'container', 'deployment',
        
        // Security/Malware domain (CRITICAL - must be strongly boosted)
        'virus', 'malware', 'ransomware', 'trojan', 'spyware',
        'adware', 'worm', 'rootkit', 'keylogger', 'phishing',
        'backdoor', 'exploit',
        
        // Problem domain (specific issues)
        'lemot', 'bsod', 'error', 'hang', 'crash',
        'login', 'password', 'akun',
    ];

    /**
     * PROTECTED TECHNICAL TOKENS - NEVER STEM THESE
     * 
     * These technical/IT/security terms MUST remain EXACT.
     * Stemming these terms destroys their meaning and causes
     * retrieval failures for security-specific queries.
     * 
     * CRITICAL: ransomware -> ransomwar (WRONG!) should stay ransomware
     * CRITICAL: malware -> malwar (WRONG!) should stay malware
     * CRITICAL: trojan -> troj (WRONG!) should stay trojan
     */
    private array $protectedTechnicalTokens = [
        // Security/Malware terms (CRITICAL - these were failing)
        'ransomware',
        'malware',
        'virus',
        'trojan',
        'spyware',
        'adware',
        'worm',
        'rootkit',
        'keylogger',
        'phishing',
        'ransomware',
        'backdoor',
        'exploit',
        'payload',
        
        // Network/Protocol terms
        'vpn',
        'wifi',
        'http',
        'https',
        'ftp',
        'ssh',
        'ssl',
        'tls',
        'dns',
        'dhcp',
        'tcp',
        'udp',
        'ip',
        'ipv4',
        'ipv6',
        'mac',
        'vlan',
        'lan',
        'wan',
        'pan',
        'man',
        'vpn',
        'proxy',
        
        // Hardware/Device terms
        'router',
        'switch',
        'hub',
        'modem',
        'printer',
        'scanner',
        'monitor',
        'keyboard',
        'mouse',
        'speaker',
        'microphone',
        'webcam',
        'bluetooth',
        'usb',
        'hdmi',
        'vga',
        'dvi',
        'ssd',
        'hdd',
        'ram',
        'rom',
        'cpu',
        'gpu',
        'bios',
        'uefi',
        'sata',
        'nvme',
        'pcie',
        
        // Software/Platform terms
        'windows',
        'linux',
        'macos',
        'android',
        'ios',
        'unix',
        'ubuntu',
        'debian',
        'centos',
        'fedora',
        'arch',
        'chrome',
        'firefox',
        'edge',
        'safari',
        'opera',
        'gmail',
        'outlook',
        'yahoo',
        'excel',
        'word',
        'powerpoint',
        'photoshop',
        'autocad',
        
        // Common technical terms
        'api',
        'sdk',
        'ide',
        'git',
        'sql',
        'mysql',
        'postgresql',
        'mongodb',
        'redis',
        'nginx',
        'apache',
        'docker',
        'kubernetes',
        'aws',
        'azure',
        'gcp',
        'json',
        'xml',
        'html',
        'css',
        'php',
        'python',
        'java',
        'javascript',
        'typescript',
        'nodejs',
        'react',
        'vue',
        'angular',
        'laravel',
        'django',
        'spring',
        
        // Error/Status codes
        'bsod',
        'http404',
        'http500',
        'http403',
        'http401',
        'http301',
        'http302',
    ];

    /**
     * Domain penalty mappings - penalize articles with these domain terms
     * when the query does NOT contain related terms
     */
    private array $domainPenaltyMappings = [
        'bsod' => ['blue', 'screen', 'crash', 'error', 'system'],
        'printer' => ['cetak', 'print', 'tinta', 'kertas'],
        'wifi' => ['wireless', 'connect', 'sinyal'],
    ];

    /**
     * Daftar stopwords Bahasa Indonesia yang diperluas
     * Sumber: kombinasi dari berbagai korpus Bahasa Indonesia
     */
    private array $stopwords = [
        // Pronouns
        'saya', 'aku', 'kami', 'kita', 'anda', 'kamu', 'dia', 'ia', 'mereka', 'beliau',
        'diriku', 'dirimu', 'dirinya', 'diri kami', 'diri mereka',
        'ku', 'mu', 'nya',
        
        // Verbs (common)
        'adalah', 'ialah', 'merupakan', 'bisa', 'dapat', 'akan', 'telah', 'sudah',
        'belum', 'sedang', 'pernah', 'ingin', 'mau', 'harus', 'boleh', 'tidak',
        'tak', 'bukan', 'jangan', 'nggak', 'ga', 'mampu', 'bisa',
        'ada', 'tiada', 'wujud', 'ada', 'pergi', 'datang', 'buat',
        
        // Prepositions
        'di', 'ke', 'dari', 'pada', 'dalam', 'kepada', 'daripada', 'oleh', 'untuk',
        'dengan', 'tanpa', 'atas', 'tentang', 'terhadap', 'menuju', 'sekitar',
        'hingga', 'sampai', 'menuju', 'sejak', 'selama', 'sesudah', 'setelah',
        
        // Conjunctions
        'dan', 'atau', 'tetapi', 'tapi', 'namun', 'sedangkan', 'melainkan', 'karena',
        'jika', 'kalau', 'apabila', 'ketika', 'sebelum', 'sesudah', 'setelah',
        'sampai', 'hingga', 'agar', 'supaya', 'bahwa', 'meskipun', 'walaupun', 'lalu',
        'kemudian', 'lagi', 'juga', 'bahkan', 'hanya', 'saja', 'pun', 'lah', 'kah', 'tah',
        'serta', 'sementara', 'sedangkan',
        
        // Demonstratives
        'ini', 'itu', 'sini', 'situ', 'sana', 'tersebut', 'begini', 'begitu',
        'ter disini', 'terdisitu', 'tersana',
        
        // Question words
        'apa', 'siapa', 'mana', 'kapan', 'bagaimana', 'mengapa', 'kenapa', 'berapa',
        'yang', 'sih', 'kok', 'nih', 'tu', 'deh',
        
        // Quantifiers
        'semua', 'setiap', 'beberapa', 'banyak', 'sedikit', 'tiap', 'satu', 'dua',
        'tiga', 'empat', 'lima', 'pertama', 'kedua', 'lain', 'sama', 'saling',
        'seluruh', 'sebagian', 'sebagainya',
        
        // Intensifiers
        'sangat', 'cukup', 'paling', 'lebih', 'kurang', 'sekali', 'terlalu',
        'agak', 'lumayan', 'benar', 'sungguh',
        
        // Adjectives (common)
        'benar', 'baik', 'besar', 'kecil', 'baru', 'lama', 'tinggi', 'rendah',
        'cantik', 'indah', 'bagus', 'jelek', 'cepat', 'lambat',
        
        // Interjections & Greetings
        'oh', 'ah', 'eh', 'ya', 'iya', 'oke', 'ok', 'halo', 'hai', 'hallo',
        'assalamualaikum', 'waalaikumsalam', 'selamat',
        
        // Polite expressions
        'tolong', 'terima', 'kasih', 'maaf', 'permisi', 'terimakasih', 'makasih',
        
        // Others
        'para', 'antaranya', 'dll', 'dsb', 'dst', 'etc', 'yaitu', 'yakni',
        'misalnya', 'contoh', 'seperti', 'ibarat', 'bagai', 'bak',
        
        // Abbreviations
        'yg', 'dgn', 'krn', 'spt', 'ttg', 'utk', 'jg', 'bgm', 'dpn', 'th',
        'dg', 'kpd', 'dlm', 'pd', 'sb', 'sbgn', 'tt', 'y', 'tdk', 'sdh', 'blm',
        'bisa', 'aja', 'gak', 'udah', 'nih', 'tu', 'sih', 'dong', 'deh', 'kok',
        'loh', 'nah', 'ih', 'aduh', 'wah', 'duh',
        
        // Common filler words
        'si', 'sang', 'para', 'kaum', 'tiap', 'masing', 'saling', 'sama',
        'sendiri', 'sendiris', 'pribadi',
    ];

    /**
     * Prefix umum Bahasa Indonesia untuk stemming
     */
    private array $prefixes = [
        'meng', 'meny', 'mem', 'men', 'm', 'pen', 'peng', 'peny', 'pem', 'pen',
        'me', 'di', 'ter', 'ke', 'se', 'pe', 'per', 'ber', 'te', 'me', 'men',
    ];

    /**
     * Suffix umum Bahasa Indonesia untuk stemming
     */
    private array $suffixes = [
        'kan', 'an', 'i', 'nya', 'lah', 'tah', 'pun', 'kah',
        'ku', 'mu', 'man', 'wan', 'wati',
    ];

    /**
     * Preprocess teks untuk query user atau dokumen
     * Menggunakan langkah yang sama untuk konsistensi
     *
     * @param string $text Teks input
     * @param bool $applyTypoCorrection Apakah akan menerapkan koreksi typo (untuk query user)
     * @return array Array token yang sudah diproses
     */
    public function preprocess(string $text, bool $applyTypoCorrection = false): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $originalText = $text;
        $text = $this->caseFolding($text);
        
        // Track preprocessing steps for debug logging
        $debugInfo = [
            'original_text' => $originalText,
            'after_case_folding' => $text,
        ];
        
        // Terapkan koreksi typo SEBELUM cleaning dan tokenization (hanya untuk query user)
        if ($applyTypoCorrection) {
            $textBeforeTypo = $text;
            $text = $this->normalizeTypos($text);
            $corrections = $this->getTypoCorrections($textBeforeTypo, $text);
            $debugInfo['typo_corrections'] = $corrections;
            $debugInfo['after_typo_correction'] = $text;
        }
        
        $text = $this->cleaning($text);
        $debugInfo['after_cleaning'] = $text;
        
        $tokens = $this->tokenize($text);
        $debugInfo['token_count_before_stopwords'] = count($tokens);
        
        $tokens = $this->removeStopwords($tokens);
        $debugInfo['token_count_after_stopwords'] = count($tokens);
        
        $tokens = $this->stemAll($tokens);
        $debugInfo['token_count_after_stemming'] = count($tokens);
        
        // Filter short tokens (less than 2 characters)
        $tokens = array_values(array_filter($tokens, fn($t) => mb_strlen($t) >= 2));
        $debugInfo['final_token_count'] = count($tokens);
        $debugInfo['final_tokens'] = $tokens;
        
        // Log preprocessing if debug mode is enabled
        if (config('app.debug', false)) {
            \Illuminate\Support\Facades\Log::debug('Preprocessing completed', $debugInfo);
        }
        
        return array_values($tokens);
    }

    /**
     * Preprocess untuk dokumen - mengembalikan token dan frequency
     *
     * @param string $text Teks dokumen
     * @return array ['tokens' => array, 'frequency' => array]
     */
    public function preprocessDocument(string $text): array
    {
        $tokens = $this->preprocess($text);
        $frequency = [];
        
        foreach ($tokens as $token) {
            $frequency[$token] = ($frequency[$token] ?? 0) + 1;
        }
        
        return [
            'tokens' => $tokens,
            'frequency' => $frequency,
        ];
    }

    /**
     * Preprocess dengan caching untuk performa
     *
     * @param string $text Teks input
     * @param string $cacheKey Cache key
     * @return array Array token yang sudah diproses
     */
    public function preprocessWithCache(string $text, string $cacheKey): array
    {
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $tokens = $this->preprocess($text);
        Cache::put($cacheKey, $tokens, 86400); // 24 hours
        
        return $tokens;
    }

    /**
     * Step 1: Case folding - konversi ke lowercase
     */
    private function caseFolding(string $text): string
    {
        return mb_strtolower($text);
    }

    /**
     * Step 2: Cleaning - hapus karakter spesial, pertahankan spasi
     */
    private function cleaning(string $text): string
    {
        // Ganti karakter non-alphanumeric dengan spasi
        $text = preg_replace('/[^a-z0-9\s]/', ' ', $text);
        // Normalisasi spasi ganda
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Step 3: Tokenization - pisahkan teks menjadi array kata
     */
    private function tokenize(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }
        return explode(' ', $text);
    }

    /**
     * Step 4: Stopword removal dengan O(1) lookup
     */
    private function removeStopwords(array $tokens): array
    {
        // Flip untuk O(1) lookup
        $stopwordsSet = array_flip($this->stopwords);
        
        return array_values(array_filter($tokens, function ($token) use ($stopwordsSet) {
            return !isset($stopwordsSet[$token]) && mb_strlen($token) > 0;
        }));
    }

    /**
     * Step 5: Stemming untuk semua token
     * PENTING: Technical tokens TIDAK akan di-stem untuk menjaga makna
     */
    private function stemAll(array $tokens): array
    {
        return array_map(fn($token) => $this->stem($token), $tokens);
    }

    /**
     * Stemming sederhana untuk Bahasa Indonesia
     * Menggunakan pendekatan kombinasi prefix dan suffix removal
     * 
     * PENTING: Protected technical tokens TIDAK akan di-stem!
     * Ini memastikan istilah seperti "ransomware", "malware", "virus" 
     * tetap utuh dan tidak berubah menjadi "ransomwar", "malwar", dll.
     */
    private function stem(string $word): string
    {
        // CRITICAL: Check if this is a protected technical token
        // If yes, DO NOT STEM - return as-is
        if ($this->isProtectedTechnicalToken($word)) {
            return $word;
        }

        // Urutkan prefix dari yang terpanjang
        $sortedPrefixes = $this->prefixes;
        usort($sortedPrefixes, fn($a, $b) => mb_strlen($b) - mb_strlen($a));

        // Coba hapus prefix
        foreach ($sortedPrefixes as $prefix) {
            if (str_starts_with($word, $prefix)) {
                $stemmed = mb_substr($word, mb_strlen($prefix));
                if (mb_strlen($stemmed) >= 2) {
                    $word = $stemmed;
                    break;
                }
            }
        }

        // Coba hapus suffix
        foreach ($this->suffixes as $suffix) {
            if (str_ends_with($word, $suffix)) {
                $stemmed = mb_substr($word, 0, mb_strlen($word) - mb_strlen($suffix));
                if (mb_strlen($stemmed) >= 2) {
                    $word = $stemmed;
                    break;
                }
            }
        }

        return $word;
    }

    /**
     * Check if a token is a protected technical token that should NOT be stemmed
     * 
     * @param string $token
     * @return bool
     */
    public function isProtectedTechnicalToken(string $token): bool
    {
        return in_array(mb_strtolower($token), $this->protectedTechnicalTokens);
    }

    /**
     * Get all protected technical tokens
     * 
     * @return array
     */
    public function getProtectedTechnicalTokens(): array
    {
        return $this->protectedTechnicalTokens;
    }

    /**
     * Dapatkan daftar stopwords
     */
    public function getStopwords(): array
    {
        return $this->stopwords;
    }

    /**
     * Tambah stopwords custom
     */
    public function addStopwords(array $words): void
    {
        $this->stopwords = array_unique(array_merge($this->stopwords, $words));
    }

    /**
     * Cek apakah sebuah kata adalah stopword
     */
    public function isStopword(string $word): bool
    {
        return in_array(mb_strtolower($word), $this->stopwords);
    }

    /**
     * Normalisasi teks untuk display (tanpa stemming)
     */
    public function normalizeForDisplay(string $text): string
    {
        $text = $this->caseFolding($text);
        $text = $this->cleaning($text);
        return $text;
    }

    /**
     * Normalize repeated characters in a token
     * 
     * Compresses repeated characters above 2 occurrences to handle spam queries.
     * Examples:
     *   virusssss -> virus
     *   wifiii -> wifi
     *   lemottt -> lemot
     *   errorrrr -> error
     *   dockerrrrrrrrrr -> docker
     * 
     * Valid double letters are preserved (google, access, support).
     * Only compresses when a character is repeated 3+ times (total 3+ consecutive).
     * 
     * @param string $token The token to normalize
     * @return string The normalized token with compressed repeated characters
     */
    public function normalizeRepeatedChars(string $token): string
    {
        // Use regex to find repeated characters and compress them
        // Pattern: matches any character followed by the same character 2+ more times (total 3+)
        // Replacement: keeps only 2 occurrences (preserving valid double letters)
        $pattern = '/(.)\1{2,}/';
        
        $result = preg_replace_callback($pattern, function ($matches) {
            $char = $matches[1];
            // Keep only 2 occurrences to preserve valid double letters
            return str_repeat($char, 2);
        }, $token);
        
        return $result ?? $token;
    }
    
    /**
     * Normalisasi typo pada teks query
     * Pipeline:
     * 1. Repeated character normalization (virusssss -> virus)
     * 2. Curated typo dictionary lookup
     * 
     * @param string $text Teks query yang akan dinormalisasi
     * @return string Teks yang sudah dinormalisasi
     */
    public function normalizeTypos(string $text): string
    {
        // Pisahkan teks menjadi token-token
        $tokens = explode(' ', $text);
        $correctedTokens = [];
        
        foreach ($tokens as $token) {
            $originalToken = $token;
            
            // STEP 1: Normalize repeated characters BEFORE dictionary lookup
            // Example: virusssss -> virus, wifiii -> wifi, lemottt -> lemot
            $compressedToken = $this->normalizeRepeatedChars($token);
            
            // Log compression for debugging
            if ($compressedToken !== $token) {
                Log::debug('Repeated character normalization in PreprocessingService', [
                    'original_token' => $token,
                    'compressed_token' => $compressedToken
                ]);
            }
            
            // STEP 2: Check typo dictionary (on compressed token)
            $corrected = $this->typoDictionary[$compressedToken] ?? $compressedToken;
            $correctedTokens[] = $corrected;
        }
        
        return implode(' ', $correctedTokens);
    }

    /**
     * Dapatkan daftar typo corrections yang diterapkan
     * Berguna untuk debugging dan logging
     * 
     * @param string $originalText Teks asli
     * @param string $correctedText Teks yang sudah dikoreksi
     * @return array Array berisi koreksi yang diterapkan
     */
    public function getTypoCorrections(string $originalText, string $correctedText): array
    {
        $originalTokens = explode(' ', mb_strtolower($originalText));
        $correctedTokens = explode(' ', mb_strtolower($correctedText));
        
        $corrections = [];
        foreach ($originalTokens as $index => $original) {
            $corrected = $correctedTokens[$index] ?? $original;
            if ($original !== $corrected) {
                $corrections[] = [
                    'original' => $original,
                    'corrected' => $corrected,
                ];
            }
        }
        
        return $corrections;
    }

    /**
     * Ekstrak context tokens dari query
     * Mengembalikan domain context yang terdeteksi
     * 
     * @param array $tokens Array token yang sudah diproses
     * @return array Array context tokens yang terdeteksi
     */
    public function extractContextTokens(array $tokens): array
    {
        $detectedContexts = [];
        
        foreach ($tokens as $token) {
            foreach ($this->contextTokens as $context => $relatedTokens) {
                if (in_array($token, $relatedTokens) || $token === $context) {
                    $detectedContexts[$context] = true;
                }
            }
        }
        
        return array_keys($detectedContexts);
    }

    /**
     * Cek apakah token adalah context token untuk domain tertentu
     * 
     * @param string $token Token yang akan dicek
     * @param string $context Domain context
     * @return bool
     */
    public function isContextToken(string $token, string $context): bool
    {
        $relatedTokens = $this->contextTokens[$context] ?? [];
        return in_array($token, $relatedTokens) || $token === $context;
    }

    /**
     * Dapatkan semua context tokens yang tersedia
     * 
     * @return array
     */
    public function getContextTokens(): array
    {
        return array_keys($this->contextTokens);
    }

    /**
     * Tambah typo correction custom
     * 
     * @param array $corrections Array ['typo' => 'correct']
     */
    public function addTypoCorrections(array $corrections): void
    {
        $this->typoDictionary = array_merge($this->typoDictionary, $corrections);
    }

    /**
     * Tambah context token custom
     * 
     * @param string $context Nama context
     * @param array $relatedTokens Token-token yang terkait
     */
    public function addContextToken(string $context, array $relatedTokens): void
    {
        $this->contextTokens[$context] = $relatedTokens;
    }

    /**
     * Get IT-specific generic terms that should have extremely low weight
     * 
     * @return array
     */
    public function getITGenericTerms(): array
    {
        return $this->itGenericTerms;
    }

    /**
     * Check if a token is an IT generic term (should have low weight)
     * 
     * @param string $token
     * @return bool
     */
    public function isITGenericTerm(string $token): bool
    {
        return in_array(mb_strtolower($token), $this->itGenericTerms);
    }

    /**
     * Get important domain tokens that should be strongly boosted
     * 
     * @return array
     */
    public function getImportantDomainTokens(): array
    {
        return $this->importantDomainTokens;
    }

    /**
     * Check if a token is an important domain token (should be boosted)
     * 
     * @param string $token
     * @return bool
     */
    public function isImportantDomainToken(string $token): bool
    {
        return in_array(mb_strtolower($token), $this->importantDomainTokens);
    }

    /**
     * Get domain penalty mappings
     * 
     * @return array
     */
    public function getDomainPenaltyMappings(): array
    {
        return $this->domainPenaltyMappings;
    }

    /**
     * Preprocess query with detailed debug information
     * Returns tokens along with debug info about stopwords removed and boosts
     * 
     * @param string $text
     * @param bool $applyTypoCorrection
     * @return array ['tokens' => array, 'removed_stopwords' => array, 'generic_terms' => array, 'domain_tokens' => array]
     */
    public function preprocessWithDebug(string $text, bool $applyTypoCorrection = false): array
    {
        if (empty(trim($text))) {
            return [
                'tokens' => [],
                'removed_stopwords' => [],
                'generic_terms' => [],
                'domain_tokens' => [],
            ];
        }

        $text = $this->caseFolding($text);
        
        if ($applyTypoCorrection) {
            $text = $this->normalizeTypos($text);
        }
        
        $text = $this->cleaning($text);
        $tokens = $this->tokenize($text);
        
        // Track removed stopwords
        $stopwordsSet = array_flip($this->stopwords);
        $removedStopwords = [];
        $tokens = array_values(array_filter($tokens, function ($token) use ($stopwordsSet, &$removedStopwords) {
            if (isset($stopwordsSet[$token])) {
                $removedStopwords[] = $token;
                return false;
            }
            return true;
        }));
        
        // Track generic terms and domain tokens
        $genericTerms = [];
        $domainTokens = [];
        
        $tokens = $this->stemAll($tokens);
        $tokens = array_values(array_filter($tokens, fn($t) => mb_strlen($t) >= 2));
        
        foreach ($tokens as $token) {
            if ($this->isITGenericTerm($token)) {
                $genericTerms[] = $token;
            }
            if ($this->isImportantDomainToken($token)) {
                $domainTokens[] = $token;
            }
        }
        
        return [
            'tokens' => array_values($tokens),
            'removed_stopwords' => $removedStopwords,
            'generic_terms' => $genericTerms,
            'domain_tokens' => $domainTokens,
        ];
    }
}
