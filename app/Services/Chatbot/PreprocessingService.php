<?php

namespace App\Services\Chatbot;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Sastrawi\Stemmer\StemmerFactory;
use Sastrawi\Stemmer\StemmerInterface;

/**
 * =========================================================================
 * SERVICE PREPROCESSING
 * =========================================================================
 * 
 * Layanan ini menormalkan query dan dokumen untuk pipeline retrieval.
 * 
 * Fungsi utama:
 * - Normalisasi typo dan karakter berulang.
 * - Case folding dan cleaning teks.
 * - Tokenisasi, stopword removal, dan stemming.
 * - Proteksi istilah teknis agar tidak dirusak oleh stemming.
 */
class PreprocessingService
{
    /**
     * Sastrawi stemmer — library resmi untuk stemming Bahasa Indonesia.
     * Menggunakan algoritma Enhanced Confix Stripping (ECS) dengan kamus
     * 30.000+ kata dasar, jauh lebih akurat dari prefix/suffix stripping manual.
     */
    private StemmerInterface $sastrawiStemmer;

    /**
     * Hash-set O(1) dari $protectedTechnicalTokens.
     * Diinisialisasi sekali di constructor untuk menghindari in_array O(N) per token.
     */
    private array $protectedTokensLookup = [];

    /**
     * Indonesian IT terms that should not be stemmed to keep their precise IT context.
     * For example, 'jaringan' (network) should not be stemmed to 'jaring' (net/web),
     * and 'perangkat' (device) should not be stemmed to 'angkat' (lift).
     */
    private array $stemmingExceptions = [
        'jaringan',
        'perangkat',
        'keamanan',
        'pencadangan',
        'pemulihan',
        'penyaringan',
        'pemblokiran',
        'pengarsipan',
        'peralatan',
        'penyimpanan',
        'sambungan',
        'pemasangan',
        'pembaruan',
        'penghapusan',
        'pencarian',
        'pengiriman',
        'pelayanan',
        'konektivitas',
        'pengguna',
        'penggunaan',
    ];

    private array $stemmingExceptionsLookup = [];

    /**
     * Prefix yang sudah diurutkan dari terpanjang ke terpendek.
     * Diinisialisasi sekali di constructor; sebelumnya diurutkan ulang di setiap stem().
     */
    private array $sortedPrefixes = [];

    /**
     * Kamus typo untuk normalisasi query
     * Format: 'typo' => 'correct'
     * 
     * Ditambahkan secara konfigurabel untuk menangani kesalahan pengetikan umum
     */
    private array $typoDictionary = [
        // WiFi related
        'wfi'   => 'wifi',
        'wiifi' => 'wifi',
        'wfii'  => 'wifi',
        'wifii' => 'wifi',
        'wi-fi' => 'wifi',

        // Internet related
        'intenet'   => 'internet',
        'internrt'  => 'internet',
        'intrnet'   => 'internet',
        'inet'      => 'internet',
        'intrnt'    => 'internet',
        'internett' => 'internet',

        // Komputer related
        'kompter'   => 'komputer',
        'komputr'   => 'komputer',
        'kompoter'  => 'komputer',
        'komputerr' => 'komputer',
        'komputwr'  => 'komputer',

        // Jaringan related
        'jaringn'   => 'jaringan',
        'jaringa'   => 'jaringan',
        'jaringann' => 'jaringan',

        // Printer related
        'prnter'   => 'printer',
        'printter' => 'printer',
        'printe'   => 'printer',
        'priner'   => 'printer',
        'pritner'  => 'printer',
        'priter'   => 'printer',
        'prinetr'  => 'printer',
        'pirnter'  => 'printer',
        'printerr' => 'printer',

        // Email related
        'emai'   => 'email',
        'emaill' => 'email',
        'emil'   => 'email',
        'emial'  => 'email',
        'eamil'  => 'email',
        'emal'   => 'email',
        'e-mail' => 'email',

        // Login related
        'logn'  => 'login',
        'logi'  => 'login',
        'lojin' => 'login',

        // Password related
        'pasword'  => 'password',
        'passwod'  => 'password',
        'passwrod' => 'password',
        'paswrod'  => 'password',

        // Koneksi related
        'koneksii' => 'koneksi',
        'koneks'   => 'koneksi',
        'koneksia' => 'koneksi',

        // Lemot related
        'lemott' => 'lemot',

        // Error related
        'eror'   => 'error',
        'errror' => 'error',
        'eroor'  => 'error',
        'errorr' => 'error',

        // Tidak related
        'tidk'   => 'tidak',
        'tida'   => 'tidak',
        'tiadak' => 'tidak',

        // Bisa related
        'bsa'   => 'bisa',
        'biisa' => 'bisa',

        // Sudah/Belum related
        'sdah'   => 'sudah',
        'belumm' => 'belum',
        'blm'    => 'belum',

        // Cara/Bagaimana related
        'caara'  => 'cara',
        'caraa'  => 'cara',
        'gimana' => 'bagaimana',
        'gmna'   => 'bagaimana',

        // Kenapa/Mengapa related
        'knpa'  => 'kenapa',
        'knapa' => 'kenapa',
        'mengap' => 'mengapa',

        // Apa related
        'apaa' => 'apa',

        // Setting related
        'seting' => 'setting',

        // Install related
        'install'  => 'instalasi',
        'instalsi' => 'instalasi',

        // Update related
        'upadate' => 'update',
        'updt'    => 'update',

        // Driver related
        'driverr' => 'driver',
        'diver'   => 'driver',

        // Software/Hardware related
        'softwere' => 'software',
        'softwre'  => 'software',
        'hardwere' => 'hardware',
        'hardwre'  => 'hardware',

        // File/Data related
        'fie'   => 'file',
        'filee' => 'file',
        'dat'   => 'data',
        'daa'   => 'data',

        // Download/Upload related
        'donlod'  => 'download',
        'downlod' => 'download',
        'uplod'   => 'upload',
        'uplaod'  => 'upload',

        // Docker/Virus related
        'dockerr' => 'docker',
        'viruss'  => 'virus',
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
     * IT-specific generic helpdesk terms that harus have extremely low weight
     * These terms are too common di helpdesk articles dan harus NOT dominate TF-IDF ranking
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
     * Important domain/technical token that harus be strongly boosted
     * These are the KEY meaningful terms that harus drive retrieval
     * 
     * IMPORTANT KEYWORD BOOSTING: Ketika query mengandung these kata kunci,
     * articles containing these kata kunci get MASSIVE boost ke pastikan
     * they rank higher daripada generic articles.
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
     * These technical/IT/keamanan terms MUST remain EXACT.
     * Stemming these terms destroys their meaning dan causes
     * retrieval failures untuk keamanan-specific query.
     * 
     * CRITICAL: ransomware -> ransomwar (WRONG!) harus stay ransomware
     * CRITICAL: malware -> malwar (WRONG!) harus stay malware
     * CRITICAL: trojan -> troj (WRONG!) harus stay trojan
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
     * Domain penalty mappings - penalize articles dengan these domain terms
     * ketika the query does NOT mengandung related terms
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
        'agak', 'lumayan', 'benar', 'sungguh', 'banget', 'bgt',
        
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

        // Slang & informal daily conversational words (Indonesian)
        'gue', 'lu', 'lo', 'gw', 'elo', 'gua', 'kalo', 'klo', 'gimana', 'gmana', 
        'gmn', 'gpp', 'gapapa', 'pengen', 'pingin', 'bikin', 'nyari', 'nanya', 
        'kaga', 'kagak', 'cuma', 'cuman', 'tuh', 'yah', 'dlu', 'dulu', 'ntar', 'nanti',
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
     * Constructor — inisialisasi Sastrawi stemmer dan O(1) lookup sekali,
     * agar tidak diinisialisasi berulang kali saat stemming dipanggil.
     */
    public function __construct()
    {
        // UPGRADE: Sastrawi menggunakan algoritma Enhanced Confix Stripping (ECS)
        // dengan kamus 30.000+ kata dasar Bahasa Indonesia yang akurat.
        $factory = new StemmerFactory();
        $this->sastrawiStemmer = $factory->createStemmer();

        // OPTIMASI: Bangun hash-set O(1) dari protected tokens
        $this->protectedTokensLookup = array_flip($this->protectedTechnicalTokens);

        // OPTIMASI: Bangun hash-set O(1) dari stemming exceptions
        $this->stemmingExceptionsLookup = array_flip($this->stemmingExceptions);

        // OPTIMASI: Prefix diurutkan satu kali di constructor
        $this->sortedPrefixes = $this->prefixes;
        usort($this->sortedPrefixes, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
    }

    /**
     * Preprocess teks untuk query user atau dokumen
     * Menggunakan langkah yang sama untuk konsistensi
     *
     * @param string $teks Teks input
     * @param bool $applyTypoCorrection Apakah akan menerapkan koreksi typo (untuk query user)
     * @kembalikan array Array token yang sudah diproses
     */
    /**
     * =========================================================================
     * 1. Metode Preprocess Utama
     * =========================================================================
     * 
     * Melakukan preprocessing lengkap pada teks query atau dokumen.
     * Termasuk case folding, typo correction, cleaning, tokenization,
     * stopword removal, dan stemming.
     * 
     * @param string $teks Teks input
     * @param bool $applyTypoCorrection Apakah akan menerapkan koreksi typo (untuk query user)
     * @kembalikan array Array token yang sudah diproses
     */
    public function preprocess(string $text, bool $applyTypoCorrection = false): array
    {
        if (empty(trim($text))) {
            return [];
        }

        $originalText = $text;
        $text = $this->caseFolding($text);
        
        // Track preprocessing steps untuk debug logging
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
        
        // Filter short token (less daripada 2 characters)
        $tokens = array_values(array_filter($tokens, fn($t) => mb_strlen($t) >= 2));
        $debugInfo['final_token_count'] = count($tokens);
        $debugInfo['final_tokens'] = $tokens;
        
        // Log preprocessing jika debug mode is aktif
        if (config('app.debug', false)) {
            \Illuminate\Support\Facades\Log::debug('Preprocessing completed', $debugInfo);
        }
        
        return array_values($tokens);
    }

    /**
     * =========================================================================
     * 2. Metode Preprocess Dokumen
     * =========================================================================
     * 
     * Memproses teks dokumen dan mengembalikan token serta frekuensi term.
     * 
     * @param string $teks Teks dokumen
     * @kembalikan array ['token' => array, 'frequency' => array]
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
     * =========================================================================
     * 3. Metode Preprocess dengan Cache
     * =========================================================================
     * 
     * Memproses teks dan menyimpan hasil tokenisasi di cache untuk performa.
     * 
     * @param string $teks Teks input
     * @param string $cacheKey Cache key
     * @kembalikan array Array token yang sudah diproses
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
     * PENTING: Technical token TIDAK akan di-stem untuk menjaga makna
     */
    private function stemAll(array $tokens): array
    {
        return array_map(fn($token) => $this->stem($token), $tokens);
    }

    /**
     * Stemming Bahasa Indonesia menggunakan Sastrawi (Enhanced Confix Stripping).
     *
     * Sastrawi menggunakan kamus 30.000+ kata dasar dengan algoritma ECS yang
     * jauh lebih akurat daripada pendekatan manual prefix/suffix stripping.
     *
     * PENTING: Protected technical token TIDAK akan di-stem!
     * Ini memastikan istilah seperti "ransomware", "malware", "virus"
     * tetap utuh dan tidak berubah.
     */
    private function stem(string $word): string
    {
        $lowerWord = mb_strtolower($word);

        // CRITICAL: Jangan stem protected technical tokens
        if ($this->isProtectedTechnicalToken($lowerWord)) {
            return $word;
        }

        // CRITICAL: Jangan stem kata-kata pengecualian stemming Bahasa Indonesia
        if (isset($this->stemmingExceptionsLookup[$lowerWord])) {
            return $word;
        }

        // UPGRADE: Gunakan Sastrawi untuk stemming Bahasa Indonesia
        $stemmed = $this->sastrawiStemmer->stem($word);

        // Fallback: Jika hasil stem terlalu pendek, kembalikan kata asli
        return (mb_strlen($stemmed) >= 2) ? $stemmed : $word;
    }

    /**
     * Periksa jika a token is a protected technical token that harus NOT be stemmed
     * 
     * @param string $token
     * @kembalikan bool
     */
    /**
     * =========================================================================
     * 4. Metode Cek Token Teknis Terlindungi
     * =========================================================================
     * 
     * Menentukan apakah token adalah istilah teknis yang tidak boleh di-stem.
     * 
     * @param string $token
     * @kembalikan bool
     */
    public function isProtectedTechnicalToken(string $token): bool
    {
        // OPTIMASI: isset pada hash-set O(1) vs in_array O(N)
        return isset($this->protectedTokensLookup[mb_strtolower($token)]);
    }

    /**
     * Get semua protected technical token
     * 
     * @kembalikan array
     */
    /**
     * =========================================================================
     * 5. Metode Daftar Token Teknis Terlindungi
     * =========================================================================
     * 
     * Mengembalikan daftar token teknis yang harus dipertahankan.
     * 
     * @kembalikan array
     */
    public function getProtectedTechnicalTokens(): array
    {
        return $this->protectedTechnicalTokens;
    }

    /**
     * Dapatkan daftar stopwords
     */
    /**
     * =========================================================================
     * 6. Metode Daftar Stopwords
     * =========================================================================
     * 
     * Mengembalikan daftar stopwords yang digunakan dalam preprocessing.
     * 
     * @kembalikan array
     */
    public function getStopwords(): array
    {
        return $this->stopwords;
    }

    /**
     * Tambah stopwords custom
     */
    /**
     * =========================================================================
     * 7. Metode Tambah Stopwords
     * =========================================================================
     * 
     * Menambahkan stopwords custom ke daftar yang ada.
     * 
     * @param array $words
     * @kembalikan void
     */
    public function addStopwords(array $words): void
    {
        $this->stopwords = array_unique(array_merge($this->stopwords, $words));
    }

    /**
     * Cek apakah sebuah kata adalah stopword
     */
    /**
     * =========================================================================
     * 8. Metode Cek Stopword
     * =========================================================================
     * 
     * Menentukan apakah sebuah kata termasuk stopword.
     * 
     * @param string $word
     * @kembalikan bool
     */
    public function isStopword(string $word): bool
    {
        return in_array(mb_strtolower($word), $this->stopwords);
    }

    /**
     * Normalisasi teks untuk display (tanpa stemming)
     */
    /**
     * =========================================================================
     * 9. Metode Normalisasi Tampilan
     * =========================================================================
     * 
     * Menormalkan teks untuk tampilan tanpa melakukan stemming.
     * 
     * @param string $teks
     * @kembalikan string
     */
    public function normalizeForDisplay(string $text): string
    {
        $text = $this->caseFolding($text);
        $text = $this->cleaning($text);
        return $text;
    }

    /**
     * Normalize repeated characters di a token
     * 
     * Compresses repeated characters above 2 occurrences ke handle spam query.
     * Contoh:
     *   virusssss -> virus
     *   wifiii -> wifi
     *   lemottt -> lemot
     *   errorrrr -> error
     *   dockerrrrrrrrrr -> docker
     * 
     * Valid double letters are preserved (google, access, support).
     * Hanya compresses ketika a character is repeated 3+ times (total 3+ consecutive).
     * 
     * @param string $token The token ke normalize
     * @kembalikan string The normalized token dengan compressed repeated characters
     */
    /**
     * =========================================================================
     * 10. Metode Normalisasi Karakter Berulang
     * =========================================================================
     * 
     * Mengurangi karakter berulang di token untuk menangani spam query.
     * 
     * @param string $token
     * @kembalikan string
     */
    public function normalizeRepeatedChars(string $token): string
    {
        // Gunakan regex ke find repeated characters dan compress them
        // Pattern: cocok apa pun character followed dengan the same character 2+ lebih times (total 3+)
        // Replacement: keeps hanya 2 occurrences (preserving valid double letters)
        $pattern = '/(.)\1{2,}/';
        
        $result = preg_replace_callback($pattern, function ($matches) {
            $char = $matches[1];
            // Simpan hanya 2 occurrences ke preserve valid double letters
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
     * @param string $teks Teks query yang akan dinormalisasi
     * @kembalikan string Teks yang sudah dinormalisasi
     */
    /**
     * =========================================================================
     * 11. Metode Normalisasi Typo
     * =========================================================================
     * 
     * Mengoreksi typo query menggunakan kamus curated dan kompresi karakter.
     * 
     * @param string $teks
     * @kembalikan string
     */
    public function normalizeTypos(string $text): string
    {
        // Pisahkan teks menjadi token-token
        $tokens = explode(' ', $text);
        $correctedTokens = [];
        
        foreach ($tokens as $token) {
            $originalToken = $token;
            
            // STEP 1: Normalize repeated characters BEFORE dictionary lookup
            // Contoh: virusssss -> virus, wifiii -> wifi, lemottt -> lemot
            $compressedToken = $this->normalizeRepeatedChars($token);
            
            // Log compression untuk debugging
            if ($compressedToken !== $token) {
                Log::debug('Repeated character normalization in PreprocessingService', [
                    'original_token' => $token,
                    'compressed_token' => $compressedToken
                ]);
            }
            
            // STEP 2: Periksa typo dictionary (on compressed token)
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
     * @kembalikan array Array berisi koreksi yang diterapkan
     */
    /**
     * =========================================================================
     * 12. Metode Koreksi Typo
     * =========================================================================
     * 
     * Mengembalikan daftar koreksi typo yang diterapkan untuk debug.
     * 
     * @param string $originalText
     * @param string $correctedText
     * @kembalikan array
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
     * Ekstrak context token dari query
     * Mengembalikan domain context yang terdeteksi
     * 
     * @param array $token Array token yang sudah diproses
     * @kembalikan array Array context token yang terdeteksi
     */
    /**
     * =========================================================================
     * 13. Metode Ekstraksi Context Token
     * =========================================================================
     * 
     * Mengembalikan daftar domain context yang terdeteksi dari token.
     * 
     * @param array $token
     * @kembalikan array
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
     * @kembalikan bool
     */
    /**
     * =========================================================================
     * 14. Metode Cek Context Token
     * =========================================================================
     * 
     * Menentukan apakah token terkait dengan context domain tertentu.
     * 
     * @param string $token
     * @param string $context
     * @kembalikan bool
     */
    public function isContextToken(string $token, string $context): bool
    {
        $relatedTokens = $this->contextTokens[$context] ?? [];
        return in_array($token, $relatedTokens) || $token === $context;
    }

    /**
     * Dapatkan semua context token yang tersedia
     * 
     * @kembalikan array
     */
    /**
     * =========================================================================
     * 15. Metode Daftar Context Token
     * =========================================================================
     * 
     * Mengembalikan daftar nama context token yang tersedia.
     * 
     * @kembalikan array
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
    /**
     * =========================================================================
     * 16. Metode Tambah Koreksi Typo
     * =========================================================================
     * 
     * Menambahkan koreksi typo custom ke kamus internal.
     * 
     * @param array $corrections
     * @kembalikan void
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
    /**
     * =========================================================================
     * 17. Metode Tambah Context Token
     * =========================================================================
     * 
     * Menambahkan token baru yang terkait dengan context domain.
     * 
     * @param string $context
     * @param array $relatedTokens
     * @kembalikan void
     */
    public function addContextToken(string $context, array $relatedTokens): void
    {
        $this->contextTokens[$context] = $relatedTokens;
    }

    /**
     * Get IT-specific generic terms that harus have extremely low weight
     * 
     * @kembalikan array
     */
    /**
     * =========================================================================
     * 18. Metode Daftar IT Generic Terms
     * =========================================================================
     * 
     * Mengembalikan istilah generik IT dengan bobot rendah.
     * 
     * @kembalikan array
     */
    public function getITGenericTerms(): array
    {
        return $this->itGenericTerms;
    }

    /**
     * Periksa jika a token is an IT generic term (harus have low weight)
     * 
     * @param string $token
     * @kembalikan bool
     */
    /**
     * =========================================================================
     * 19. Metode Cek IT Generic Term
     * =========================================================================
     * 
     * Menentukan apakah token termasuk istilah IT generik.
     * 
     * @param string $token
     * @kembalikan bool
     */
    public function isITGenericTerm(string $token): bool
    {
        return in_array(mb_strtolower($token), $this->itGenericTerms);
    }

    /**
     * Get penting domain token that harus be strongly boosted
     * 
     * @kembalikan array
     */
    /**
     * =========================================================================
     * 20. Metode Daftar Token Domain Penting
     * =========================================================================
     * 
     * Mengembalikan istilah domain penting yang harus di-boost.
     * 
     * @kembalikan array
     */
    public function getImportantDomainTokens(): array
    {
        return $this->importantDomainTokens;
    }

    /**
     * Periksa jika a token is an penting domain token (harus be boosted)
     * 
     * @param string $token
     * @kembalikan bool
     */
    /**
     * =========================================================================
     * 21. Metode Cek Token Domain Penting
     * =========================================================================
     * 
     * Menentukan apakah token merupakan istilah domain penting.
     * 
     * @param string $token
     * @kembalikan bool
     */
    public function isImportantDomainToken(string $token): bool
    {
        return in_array(mb_strtolower($token), $this->importantDomainTokens);
    }

    /**
     * Get domain penalty mappings
     * 
     * @kembalikan array
     */
    /**
     * =========================================================================
     * 22. Metode Domain Penalty Mappings
     * =========================================================================
     * 
     * Mengembalikan konfigurasi penalti domain apabila query tidak mengandung
     * istilah terkait.
     * 
     * @kembalikan array
     */
    public function getDomainPenaltyMappings(): array
    {
        return $this->domainPenaltyMappings;
    }

    /**
     * Preprocess query dengan detailed debug informasi
     * Mengembalikan token along dengan debug info about stopwords removed dan boosts
     * 
     * @param string $teks
     * @param bool $applyTypoCorrection
     * @kembalikan array ['token' => array, 'removed_stopwords' => array, 'generic_terms' => array, 'domain_tokens' => array]
     */
    /**
     * =========================================================================
     * 23. Metode Preprocess dengan Debug
     * =========================================================================
     * 
     * Memproses query dan mengembalikan token bersama debug info seperti stopwords
     * yang dihapus dan istilah domain yang terdeteksi.
     * 
     * @param string $teks
     * @param bool $applyTypoCorrection
     * @kembalikan array ['token' => array, 'removed_stopwords' => array, 'generic_terms' => array, 'domain_tokens' => array]
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
        
        // Track generic terms dan domain token
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