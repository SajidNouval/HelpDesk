<?php

namespace App\Services\Chatbot;

use App\Models\Category;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * =========================================================================
 * SERVICE DOMAIN DETECTION
 * =========================================================================
 *
 * Layanan ini melakukan deteksi domain (topik IT) dari query pengguna dan
 * menyaring kategori artikel yang relevan sebelum proses retrieval TF-IDF.
 *
 * Terdapat dua fungsi deteksi utama:
 * 1. Deteksi Domain   : Menentukan topik IT spesifik (wifi, printer, email, dll.)
 * 2. Deteksi Out-of-Domain : Menentukan apakah query sama sekali di luar IT/support
 *
 * Pipeline deteksi domain:
 * 1. Normalisasi sinonim dan koreksi typo pada query.
 * 2. Tokenisasi query menjadi term-term individual.
 * 3. Pencocokan token dengan kata kunci domain yang terkurasi.
 * 4. Penilaian skor kepercayaan (confidence) untuk setiap domain.
 * 5. Pengembalian ID kategori database yang relevan untuk penyaringan artikel.
 *
 * Pipeline deteksi out-of-domain:
 * 1. Normalisasi dan tokenisasi query.
 * 2. Pengecekan kata kunci non-IT eksplisit (kucing, rendang, mobil, dll.).
 * 3. Penghitungan token IT dalam query.
 * 4. Pengecekan overlap vocabulary IT.
 * 5. Evaluasi gabungan semua sinyal untuk keputusan final.
 *
 * Prinsip desain:
 * - Selalu accept query yang mengandung token "never reject" (virus, docker, dll.)
 * - Lebih baik menerima query IT yang borderline daripada menolak yang valid
 * - Deteksi berdasarkan daftar kata kunci terkurasi, bukan heuristik bebas
 *
 * Digunakan oleh:
 * - AdvancedRetrievalService
 * - ChatbotRetrievalService
 */
class DomainDetectionService
{
    // Konfigurasi cache untuk menyimpan saran domain
    private const DOMAIN_CACHE_KEY = 'chatbot:domain:mapping';
    private const DOMAIN_CACHE_TTL = 3600; // 1 jam dalam detik

    /**
     * Daftar domain IT yang valid dan terverifikasi.
     * Hanya domain-domain ini yang dapat dikenali oleh sistem.
     * Tidak ada domain yang ditambahkan secara dinamis dari input pengguna.
     */
    public array $curatedDomains = [
        'wifi',
        'internet',
        'jaringan',
        'printer',
        'komputer',
        'email',
        'website',
        'aplikasi',
        'akun',
        'security',
        'bsod',
        'windows',
        'server',
        'driver',
        'hardware',
    ];

    /**
     * Daftar subtopik yang terkurasi untuk setiap domain.
     * Subtopik ini bersifat statis dan terverifikasi — tidak berasal dari input pengguna.
     * Digunakan untuk menampilkan pilihan topik yang lebih spesifik kepada pengguna.
     */
    public array $curatedSubtopics = [
        'wifi' => [
            'wifi lemot',
            'wifi tidak connect',
            'wifi sering putus',
            'wifi tidak terdeteksi',
            'wifi tidak bisa connect',
        ],
        'internet' => [
            'internet lemot',
            'internet putus-putus',
            'internet tidak stabil',
            'koneksi internet lambat',
            'internet tidak bisa diakses',
        ],
        'printer' => [
            'printer error',
            'printer tidak respon',
            'printer tidak mau print',
            'printer macet',
            'printer tidak terdeteksi',
        ],
        'komputer' => [
            'komputer lemot',
            'komputer sering hang',
            'komputer blue screen',
            'komputer tidak mau nyala',
            'komputer lambat',
        ],
        'email' => [
            'email tidak masuk',
            'email tidak bisa dikirim',
            'email error',
            'lupa password email',
            'email spam',
        ],
        'website' => [
            'website tidak bisa diakses',
            'website lemot',
            'website error',
            'login website gagal',
            'website tidak responsif',
        ],
        'aplikasi' => [
            'aplikasi error',
            'aplikasi tidak bisa dibuka',
            'aplikasi lemot',
            'aplikasi crash',
            'aplikasi tidak responsif',
        ],
        'akun' => [
            'lupa password',
            'akun terkunci',
            'tidak bisa login',
            'akun diretas',
            'registrasi gagal',
        ],
    ];

    /**
     * Domain-domain yang ditampilkan saat query ambigu memerlukan klarifikasi.
     * Hanya domain dengan confidence tinggi yang dimasukkan di sini.
     */
    public array $clarificationDomains = [
        'wifi',
        'internet',
        'komputer',
        'printer',
        'email',
        'aplikasi',
    ];

    /**
     * Pemetaan domain ke kata kunci dan nama kategori di database.
     *
     * Struktur setiap entry:
     * - 'kata kunci'   : Kata-kata yang menunjukkan domain ini dalam query
     * - 'kategori' : Nama kategori di database yang terkait dengan domain ini
     *                  (harus cocok dengan nama kategori aktual di database, case-insensitive)
     *
     * PENTING: Nilai 'kategori' harus sesuai dengan data di tabel kategori di database.
     */
    private array $domainKeywords = [
        'wifi' => [
            'keywords'   => ['wifi', 'wi-fi', 'wireless', 'wlan', 'hotspot', 'access point', 'ap', 'router wifi'],
            'categories' => ['wifi'],
        ],
        'internet' => [
            'keywords'   => ['internet', 'inet', 'koneksi internet', 'sinyal internet', 'bandwidth', 'quota', 'paket data'],
            'categories' => ['internet'],
        ],
        'jaringan' => [
            'keywords'   => ['jaringan', 'network', 'lan', 'wan', 'ethernet', 'kabel jaringan', 'switch', 'hub'],
            'categories' => ['wifi', 'internet'],
        ],
        'printer' => [
            'keywords'   => ['printer', 'printing', 'cetak', 'mencetak', 'epson', 'canon', 'hp printer', 'ink', 'tinta', 'cartridge', 'toner'],
            'categories' => ['hardware'],
        ],
        'komputer' => [
            'keywords'   => ['komputer', 'computer', 'pc', 'laptop', 'notebook', 'desktop'],
            'categories' => ['hardware'],
        ],
        'email' => [
            'keywords'   => ['email', 'e-mail', 'surel', 'mail', 'gmail', 'outlook', 'yahoo mail'],
            'categories' => ['email'],
        ],
        'website' => [
            'keywords'   => ['website', 'web', 'situs', 'portal', 'halaman web', 'browser', 'chrome', 'firefox'],
            'categories' => ['internet'],
        ],
        'aplikasi' => [
            'keywords'   => ['aplikasi', 'application', 'software', 'perangkat lunak', 'program', 'app'],
            'categories' => ['aplikasi'],
        ],
        'akun' => [
            'keywords'   => ['akun', 'account', 'login', 'masuk', 'daftar', 'register', 'password', 'kata sandi', 'username'],
            'categories' => ['email', 'aplikasi'],
        ],
        'security' => [
            'keywords'   => ['ransomware', 'malware', 'virus', 'trojan', 'spyware', 'adware', 'worm', 'rootkit', 'keylogger', 'phishing', 'backdoor', 'exploit', 'antivirus', 'windows defender'],
            'categories' => ['security'],
        ],
        'bsod' => [
            'keywords'   => ['bsod', 'blue screen', 'crash', 'system crash', 'stop error', 'screen of death'],
            'categories' => ['hardware'],
        ],
    ];

    /**
     * Term-term generik yang TIDAK digunakan untuk deteksi domain.
     * Term ini terlalu umum dan tidak mencerminkan intent spesifik.
     * Keberadaannya diabaikan saat proses penilaian domain.
     */
    private array $genericTerms = [
        'lemot', 'lambat', 'slow', 'error', 'masalah', 'tidak', 'bisa',
        'mau', 'sudah', 'belum', 'ingin', 'harus', 'perlu', 'cara', 'bagaimana',
        'apa', 'kenapa', 'mengapa', 'sangat', 'banget', 'sekali', 'benar', 'sungguh',
        'bermasalah', 'rusak', 'mati', 'hilang', 'tidak bisa', 'gagal',
    ];

    /**
     * Kosakata lengkap domain IT/support yang valid.
     * Digunakan untuk mengukur seberapa banyak token dalam query
     * yang termasuk dalam ranah IT sebelum menolak query sebagai out-of-domain.
     *
     * Kategori yang dicakup:
     * - Domain IT inti (wifi, internet, printer, dll.)
     * - Sistem operasi dan platform
     * - Term keamanan siber (WAJIB diakui sebagai IT)
     * - Perangkat keras
     * - Masalah umum IT
     * - DevOps/pengembangan (WAJIB diakui sebagai IT)
     */
    private array $itDomainVocabulary = [
        // Domain IT inti
        'wifi', 'internet', 'jaringan', 'network', 'lan', 'wan', 'ethernet',
        'printer', 'printing', 'cetak', 'scanner',
        'komputer', 'computer', 'pc', 'laptop', 'notebook', 'desktop',
        'email', 'e-mail', 'mail', 'gmail', 'outlook',
        'website', 'web', 'browser', 'chrome', 'firefox', 'situs',
        'aplikasi', 'application', 'software', 'program', 'app',
        'akun', 'account', 'login', 'password', 'username',

        // Sistem operasi dan platform
        'windows', 'linux', 'macos', 'android', 'ios',
        'ubuntu', 'debian', 'centos', 'fedora',
        'xp', 'vista', 'win7', 'win8', 'win10', 'win11',
        'server', 'vps', 'cloud',

        // Term keamanan siber (KRITIS — harus selalu dikenali sebagai IT)
        'virus', 'malware', 'ransomware', 'trojan', 'spyware', 'adware',
        'worm', 'rootkit', 'keylogger', 'phishing', 'antivirus',
        'security', 'hack', 'hacker', 'firewall',

        // Perangkat keras
        'hardware', 'ram', 'cpu', 'gpu', 'ssd', 'hdd', 'motherboard',
        'monitor', 'keyboard', 'mouse', 'speaker', 'microphone',
        'router', 'modem', 'switch', 'hub', 'access point',
        'driver', 'firmware', 'bios',

        // Masalah umum IT
        'lemot', 'lambat', 'error', 'crash', 'hang', 'freeze',
        'bsod', 'blue screen', 'restart', 'shutdown',
        'connect', 'koneksi', 'sinyal', 'bandwidth',
        'install', 'uninstall', 'update', 'upgrade', 'download',
        'backup', 'restore', 'format', 'reset',
        'masuk', 'diakses', 'dibuka', 'dijalankan',

        // DevOps/pengembangan (KRITIS — harus selalu dikenali sebagai IT)
        'docker', 'kubernetes', 'k8s', 'container',
        'git', 'github', 'gitlab',
        'hosting', 'domain', 'ssl', 'https',
        'api', 'database', 'mysql', 'postgresql', 'mongodb', 'sql',

        // Aksi IT umum
        'troubleshoot', 'troubleshooting', 'fix', 'repair', 'solve',
        'configure', 'setting', 'setup', 'install',

        // Merek teknologi populer
        'microsoft', 'google', 'apple', 'adobe',
        'office', 'excel', 'word', 'powerpoint',
        'pdf', 'zip', 'rar', 'compress',
        'bluetooth', 'usb', 'hdmi', 'vga',
    ];

    /**
     * Token yang TIDAK PERNAH boleh ditolak sebagai out-of-domain.
     * Jika query mengandung salah satu dari token ini, query SELALU diterima
     * sebagai query IT, tanpa memandang sinyal lainnya.
     *
     * Token-token ini adalah kata kunci teknis yang sangat spesifik dan definitif
     * sebagai domain IT/support (tidak ada kemungkinan ambigu).
     */
    private array $neverRejectTokens = [
        // Token keamanan siber
        'virus', 'malware', 'ransomware', 'trojan', 'spyware', 'phishing', 'antivirus',

        // Token DevOps/infrastruktur
        'docker', 'kubernetes', 'k8s', 'container',

        // Token jaringan
        'wifi', 'jaringan', 'network', 'vpn', 'router', 'modem',

        // Token perangkat keras peripheral
        'printer', 'scanner',

        // Token data/database
        'database', 'mysql', 'postgresql', 'mongodb', 'sql',

        // Token komunikasi digital
        'email', 'gmail', 'outlook',

        // Token web
        'website', 'browser', 'chrome', 'firefox',

        // Token akun
        'akun', 'login', 'password',
    ];

    /**
     * Kata kunci non-IT yang menyebabkan penolakan langsung (immediate rejection).
     * Jika query mengandung salah satu dari kata kunci ini, query ditolak
     * sebagai out-of-domain tanpa melalui evaluasi lebih lanjut.
     *
     * Kategori yang termasuk: hewan, makanan, kendaraan, hiburan, belanja,
     * perjalanan, kesehatan, pendidikan non-IT, dan topik umum lainnya.
     */
    private array $outOfDomainKeywords = [
        // Hewan
        'kucing', 'anjing', 'ikan', 'burung', 'ular', 'tikus',
        // Makanan dan memasak
        'rendang', 'nasi', 'gado', 'sate', 'bakso', 'mie',
        'masak', 'memasak', 'dapur', 'resep', 'makanan', 'minuman',

        // Kendaraan
        'mobil', 'motor', 'sepeda', 'truk', 'bus', 'kereta',
        'balap', 'rally', 'otomotif', 'bengkel', 'sparepart',
        'parkir', 'tilang', 'sim', 'stnk',

        // Hiburan dan olahraga
        'film', 'musik', 'lagu', 'game', 'gaming',
        'netflix', 'youtube', 'tiktok', 'instagram', 'facebook',
        'bola', 'sepakbola', 'basket', 'badminton', 'renang',

        // Belanja dan keuangan
        'belanja', 'beli', 'jual', 'harga', 'diskon', 'promo',
        'bank', 'tabungan', 'kredit', 'pinjaman', 'asuransi',
        'shopee', 'tokopedia', 'lazada', 'bukalapak',

        // Perjalanan dan tempat
        'hotel', 'tiket', 'pesawat', 'liburan', 'wisata',
        'bandara', 'stasiun', 'terminal', 'pelabuhan',

        // Kesehatan dan medis
        'sakit', 'dokter', 'rumah sakit', 'obat', 'klinik',
        'covid', 'corona', 'vaksin', 'flu', 'demam',

        // Pendidikan non-IT
        'sekolah', 'kuliah', 'ujian', 'nilai', 'ijazah',
        'matematika', 'fisika', 'kimia', 'biologi', 'sejarah',

        // Topik umum lainnya
        'cuaca', 'hujan', 'panas', 'gempi', 'banjir',
        'politik', 'pemerintah', 'presiden', 'menteri',
        'agama', 'ibadah', 'puasa', 'lebaran', 'natal',
    ];

    // Rasio minimum overlap vocabulary IT yang diperlukan agar query diterima
    private const MIN_VOCABULARY_OVERLAP = 0.20;

    // Jumlah minimum token IT yang harus ada dalam query
    private const MIN_IT_TOKENS = 1;

    // Batas kepercayaan minimum untuk deteksi domain (diturunkan agar lebih toleran)
    private const DOMAIN_CONFIDENCE_THRESHOLD = 0.05;

    // Pesan penolakan yang ditampilkan untuk query out-of-domain
    public const OUT_OF_DOMAIN_MESSAGE = 'Maaf, saya hanya dapat membantu masalah terkait IT.';

    /**
     * Pemetaan sinonim untuk normalisasi query sebelum tokenisasi.
     * Memastikan variasi penulisan yang berbeda diperlakukan sama.
     * Contoh: "kompter" → "komputer", "wfi" → "wifi", "e-mail" → "email"
     *
     * Kategori sinonim:
     * - Kecepatan (lambat, pelan → lemot)
     * - Koneksi (koneksi, sambungan → internet)
     * - Error (eror, galat → error)
     * - Perangkat dan komponen (typo umum)
     * - Term keamanan (toleransi typo untuk keamanan terms)
     */
    private array $synonymMappings = [
        // Sinonim kecepatan
        'lambat'      => 'lemot',
        'pelan'       => 'lemot',
        'lamban'      => 'lemot',

        // Sinonim koneksi
        'koneksi'     => 'internet',
        'sambungan'   => 'internet',
        'terhubung'   => 'connect',

        // Sinonim error
        'eror'        => 'error',
        'erorr'       => 'error',
        'galat'       => 'error',
        'masalah'     => 'error',

        // Sinonim perangkat — toleransi typo umum
        'komputer'    => 'komputer',
        'kompter'     => 'komputer',
        'komputerr'   => 'komputer',

        // Sinonim printer — toleransi typo umum
        'pritner'     => 'printer',
        'printter'    => 'printer',
        'prnter'      => 'printer',

        // Sinonim WiFi — toleransi typo umum
        'wfi'         => 'wifi',
        'wiif'        => 'wifi',
        'wifii'       => 'wifi',
        'wi-fi'       => 'wifi',

        // Sinonim internet — toleransi typo umum
        'intenet'     => 'internet',
        'intrnet'     => 'internet',
        'inet'        => 'internet',

        // Sinonim jaringan — toleransi typo umum
        'jaringn'     => 'jaringan',
        'jaring'      => 'jaringan',

        // Sinonim email — toleransi typo umum
        'emai'        => 'email',
        'emal'        => 'email',
        'e-mail'      => 'email',

        // Sinonim term keamanan — toleransi typo untuk kata kunci kritis
        'ransomwre'   => 'ransomware',
        'ransomwaree' => 'ransomware',
        'ransomware'  => 'ransomware',
        'viruss'      => 'virus',
        'viruse'      => 'virus',
        'viru'        => 'virus',
        'malwere'     => 'malware',
        'malwre'      => 'malware',
        'trojan'      => 'trojan',
        'trojanhorse' => 'trojan',
        'spyware'     => 'spyware',
        'phising'     => 'phishing',

        // Sinonim website — toleransi typo umum
        'webiste'     => 'website',
        'websit'      => 'website',

        // Sinonim aksi
        'tidak connect' => 'tidak terhubung',
        'tidak konek'   => 'tidak terhubung',
        'gak bisa'      => 'tidak bisa',
        'ga bisa'       => 'tidak bisa',
    ];

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
     * 1. METODE DETECT DOMAIN
     * =========================================================================
     *
     * Fungsi:
     * Mendeteksi domain IT utama dari query pengguna dan mengembalikan ID kategori database yang relevan.
     *
     * Alur Proses:
     * 1. Menerima query mentah dari pengguna.
     * 2. Normalisasi query dengan koreksi typo dan pemetaan sinonim.
     * 3. Tokenisasi query menjadi term individual.
     * 4. Penilaian skor untuk setiap domain berdasarkan pencocokan kata kunci.
     * 5. Filter domain yang memenuhi ambang kepercayaan.
     * 6. Ambil ID kategori database untuk domain terpilih.
     * 7. Mengembalikan hasil deteksi domain.
     *
     * Query yang Digunakan:
     * - Category::whereIn('name', $categoryNames)->pluck('id')->toArray(): Ambil ID kategori berdasarkan nama
     * - Category::where(function ($query) use ($categoryNames) { ... })->pluck('id')->toArray(): Pencarian case-insensitive
     *
     * Output:
     * - array ['detected' => bool, 'domain' => string|null, 'category_ids' => array, 'confidence' => float, 'all_scores' => array]
     */
    public function detectDomain(string $query): array
    {
        // Query kosong — tidak ada domain yang bisa dideteksi
        if (empty(trim($query))) {
            return ['detected' => false, 'domain' => null, 'category_ids' => [], 'confidence' => 0.0];
        }

        // Normalisasi query dengan koreksi typo menggunakan PreprocessingService
        $normalizedQuery = $this->preprocessor->normalizeTypos($query);

        // Terapkan pemetaan sinonim untuk toleransi typo tambahan
        $normalizedQuery = $this->applySynonymMapping($normalizedQuery);

        // Tokenisasi query menjadi token individual
        $tokens = $this->tokenizeQuery($normalizedQuery);

        // Nilai setiap domain berdasarkan pencocokan token dengan kata kunci domain
        $domainScores = $this->scoreDomains($tokens);

        // Saring domain yang melewati ambang kepercayaan minimum
        $threshold      = 0.3;
        $detectedDomains = array_filter($domainScores, fn($score) => $score >= $threshold);

        if (empty($detectedDomains)) {
            return ['detected' => false, 'domain' => null, 'category_ids' => [], 'confidence' => 0.0];
        }

        // Pilih domain dengan skor tertinggi sebagai domain utama
        arsort($detectedDomains);
        $primaryDomain = array_key_first($detectedDomains);
        $confidence    = $detectedDomains[$primaryDomain];

        // Ambil ID kategori dari database berdasarkan nama kategori yang dipetakan ke domain
        $categoryIds = $this->getCategoryIdsForDomain($primaryDomain);

        return [
            'detected'     => true,
            'domain'       => $primaryDomain,
            'category_ids' => $categoryIds,
            'confidence'   => $confidence,
            'all_scores'   => $domainScores,
        ];
    }

    /**
     * =========================================================================
     * 1. METODE DETECT OUT OF DOMAIN
     * =========================================================================
     *
     * Fungsi:
     * Menentukan apakah query pengguna berada di luar domain IT/support.
     *
     * Alur Proses:
     * 1. Menerima query mentah dari pengguna.
     * 2. Normalisasi dan tokenisasi query.
     * 3. Cek kata kunci non-IT eksplisit untuk penolakan langsung.
     * 4. Hitung jumlah token IT dalam query.
     * 5. Hitung rasio overlap vocabulary IT.
     * 6. Cek confidence deteksi domain.
     * 7. Evaluasi gabungan semua sinyal untuk keputusan final.
     * 8. Mengembalikan hasil evaluasi out-of-domain.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array ['is_out_of_domain' => bool, 'reason' => string, 'it_token_count' => int, 'vocabulary_overlap' => float, 'domain_confidence' => float]
     */
    public function detectOutOfDomain(string $query): array
    {
        // Query kosong langsung ditolak
        if (empty(trim($query))) {
            return [
                'is_out_of_domain'   => true,
                'reason'             => 'empty_query',
                'it_token_count'     => 0,
                'vocabulary_overlap' => 0.0,
            ];
        }

        // Normalisasi dan tokenisasi query
        $normalizedQuery = $this->preprocessor->normalizeTypos($query);
        $normalizedQuery = $this->applySynonymMapping($normalizedQuery);
        $tokens          = $this->tokenizeQuery($normalizedQuery);

        if (empty($tokens)) {
            return [
                'is_out_of_domain'   => true,
                'reason'             => 'no_tokens',
                'it_token_count'     => 0,
                'vocabulary_overlap' => 0.0,
            ];
        }

        // Cek kata kunci non-IT eksplisit — jika ada, tolak langsung
        $hasExplicitOutOfDomain = $this->hasExplicitOutOfDomainKeywords($tokens);
        if ($hasExplicitOutOfDomain) {
            return [
                'is_out_of_domain'   => true,
                'reason'             => 'explicit_out_of_domain_keywords',
                'it_token_count'     => 0,
                'vocabulary_overlap' => 0.0,
            ];
        }

        // Hitung jumlah token yang termasuk kosakata IT
        $itTokenCount = $this->countITDomainTokens($tokens);

        // Hitung rasio overlap kosakata IT terhadap total token bermakna
        $vocabularyOverlap = $this->calculateVocabularyOverlap($tokens);

        // Ambil confidence deteksi domain sebagai sinyal tambahan
        $domainInfo      = $this->detectDomain($query);
        $domainConfidence = $domainInfo['confidence'] ?? 0.0;

        // Evaluasi gabungan semua sinyal untuk keputusan final
        $isOutOfDomain = $this->evaluateOutOfDomain(
            $tokens,
            $itTokenCount,
            $vocabularyOverlap,
            $domainConfidence
        );

        $reason = $isOutOfDomain
            ? $this->getOutOfDomainReason($itTokenCount, $vocabularyOverlap, $domainConfidence)
            : 'in_domain';

        return [
            'is_out_of_domain'   => $isOutOfDomain,
            'reason'             => $reason,
            'it_token_count'     => $itTokenCount,
            'vocabulary_overlap' => round($vocabularyOverlap, 4),
            'domain_confidence'  => round($domainConfidence, 4),
        ];
    }

    /**
     * =========================================================================
     * 1. METODE HAS EXPLICIT OUT OF DOMAIN KEYWORDS
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah ada token dalam query yang merupakan kata kunci non-IT.
     *
     * Alur Proses:
     * 1. Menerima token-token dari query yang sudah dinormalisasi.
     * 2. Iterasi setiap token dan cek apakah ada di daftar outOfDomainKeywords.
     * 3. Mengembalikan true jika ditemukan kata kunci non-IT.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool true jika ada kata kunci non-IT eksplisit
     */
    private function hasExplicitOutOfDomainKeywords(array $tokens): bool
    {
        foreach ($tokens as $token) {
            $lowerToken = mb_strtolower($token);
            if (in_array($lowerToken, $this->outOfDomainKeywords)) {
                return true;
            }
        }
        return false;
    }

    /**
     * =========================================================================
     * 1. METODE COUNT IT DOMAIN TOKENS
     * =========================================================================
     *
     * Fungsi:
     * Menghitung berapa banyak token dalam query yang termasuk dalam kosakata IT.
     *
     * Alur Proses:
     * 1. Menerima token-token dari query yang sudah dinormalisasi.
     * 2. Iterasi setiap token dan lewati term generik.
     * 3. Hitung token yang ada dalam kosakata IT.
     * 4. Mengembalikan jumlah token IT.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - int jumlah token yang dikenali sebagai kosakata IT
     */
    private function countITDomainTokens(array $tokens): int
    {
        $count = 0;
        foreach ($tokens as $token) {
            $lowerToken = mb_strtolower($token);
            // Lewati term generik yang tidak menunjukkan intent IT spesifik
            if (in_array($lowerToken, $this->genericTerms)) {
                continue;
            }
            // Hitung token yang ada dalam kosakata IT
            if (in_array($lowerToken, $this->itDomainVocabulary)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * =========================================================================
     * 1. METODE CALCULATE VOCABULARY OVERLAP
     * =========================================================================
     *
     * Fungsi:
     * Menghitung rasio antara token IT yang ditemukan dengan total token bermakna.
     *
     * Alur Proses:
     * 1. Menerima token-token dari query yang sudah dinormalisasi.
     * 2. Lewati term generik yang tidak bermakna.
     * 3. Hitung exact match dan partial match dengan kosakata IT.
     * 4. Mengembalikan rasio overlap.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - float rasio overlap [0.0, 1.0]
     */
    private function calculateVocabularyOverlap(array $tokens): float
    {
        $meaningfulTokens = 0;
        $itMatches        = 0;

        foreach ($tokens as $token) {
            $lowerToken = mb_strtolower($token);

            // Lewati term generik yang tidak bermakna untuk perhitungan overlap
            if (in_array($lowerToken, $this->genericTerms)) {
                continue;
            }

            $meaningfulTokens++;

            if (in_array($lowerToken, $this->itDomainVocabulary)) {
                // Exact cocok: token persis ada di kosakata IT
                $itMatches++;
            } else {
                // Partial cocok untuk menangani variasi kata
                foreach ($this->itDomainVocabulary as $itTerm) {
                    if (str_contains($itTerm, $lowerToken) && mb_strlen($lowerToken) > 2) {
                        $itMatches += 0.5;
                        break;
                    }
                    if (str_contains($lowerToken, $itTerm) && mb_strlen($itTerm) > 2) {
                        $itMatches += 0.3;
                        break;
                    }
                }
            }
        }

        if ($meaningfulTokens === 0) {
            return 0.0;
        }

        return $itMatches / $meaningfulTokens;
    }

    /**
     * =========================================================================
     * 1. METODE EVALUATE OUT OF DOMAIN
     * =========================================================================
     *
     * Fungsi:
     * Mengevaluasi apakah query termasuk out-of-domain berdasarkan kombinasi sinyal.
     *
     * Alur Proses:
     * 1. Menerima token, jumlah token IT, overlap vocabulary, dan confidence domain.
     * 2. Cek token "never reject" untuk selalu menerima.
     * 3. Cek jika tidak ada token IT untuk menolak.
     * 4. Cek overlap vocabulary memadai untuk menerima.
     * 5. Cek overlap rendah dan confidence rendah untuk menolak.
     * 6. Cek confidence memadai untuk menerima.
     * 7. Default: terima jika ada minimal 1 token IT.
     * 8. Mengembalikan keputusan out-of-domain.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool true jika out-of-domain, false jika di-domain
     */
    private function evaluateOutOfDomain(array $tokens, int $itTokenCount, float $vocabularyOverlap, float $domainConfidence): bool
    {
        // KRITIS: Jika query mengandung token "never reject", SELALU terima
        // Token seperti "virus", "docker", "wifi" tidak pernah boleh ditolak
        // Juga menangani typo seperti "virussss" yang mengandung "virus"
        if ($this->containsNeverRejectToken($tokens)) {
            return false;
        }

        // Tidak ada token IT sama sekali → pasti out-of-domain
        if ($itTokenCount < self::MIN_IT_TOKENS) {
            return true;
        }

        // Ada token IT + overlap kosakata memadai → di-domain
        // Menangani kasus seperti "email tidak masuk" di mana deteksi domain mungkin lemah
        if ($itTokenCount >= 1 && $vocabularyOverlap >= self::MIN_VOCABULARY_OVERLAP) {
            return false;
        }

        // Overlap sangat rendah + confidence domain sangat rendah → out-of-domain
        if ($vocabularyOverlap < self::MIN_VOCABULARY_OVERLAP && $domainConfidence < self::DOMAIN_CONFIDENCE_THRESHOLD) {
            return true;
        }

        // Confidence domain memadai → di-domain
        if ($domainConfidence >= self::DOMAIN_CONFIDENCE_THRESHOLD) {
            return false;
        }

        // Default: terima jika ada minimal 1 token IT
        // Lebih baik menerima query borderline daripada menolak query IT yang valid
        return $itTokenCount < 1;
    }

    /**
     * =========================================================================
     * 1. METODE CONTAINS NEVER REJECT TOKEN
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah query mengandung token yang tidak boleh pernah ditolak.
     *
     * Alur Proses:
     * 1. Menerima token-token dari query yang sudah dinormalisasi.
     * 2. Cek exact match dengan token never-reject.
     * 3. Cek partial match untuk menangani typo.
     * 4. Mengembalikan true jika ditemukan token never-reject.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool true jika ditemukan token never-reject
     */
    private function containsNeverRejectToken(array $tokens): bool
    {
        foreach ($tokens as $token) {
            $lowerToken = mb_strtolower($token);

            // Cek exact cocok dengan token never-reject
            if (in_array($lowerToken, $this->neverRejectTokens)) {
                return true;
            }

            // Cek partial cocok untuk menangani typo repetitif (misalnya "virussss")
            foreach ($this->neverRejectTokens as $criticalToken) {
                // Token lebih panjang dan mengandung kata kritis (misalnya "virussss" → "virus")
                if (mb_strlen($lowerToken) > mb_strlen($criticalToken) &&
                    str_contains($lowerToken, $criticalToken)) {
                    return true;
                }
                // Token yang terpotong (misalnya "viru" → "virus") jika cukup panjang
                if (mb_strlen($criticalToken) > mb_strlen($lowerToken) &&
                    mb_strlen($lowerToken) > 3 &&
                    str_contains($criticalToken, $lowerToken)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * =========================================================================
     * 1. METODE GET OUT OF DOMAIN REASON
     * =========================================================================
     *
     * Fungsi:
     * Menentukan alasan spesifik mengapa query diklasifikasikan sebagai out-of-domain.
     *
     * Alur Proses:
     * 1. Menerima jumlah token IT, overlap vocabulary, dan confidence domain.
     * 2. Cek jika token IT kurang dari minimum.
     * 3. Cek jika overlap vocabulary rendah.
     * 4. Cek jika confidence domain rendah.
     * 5. Mengembalikan kode alasan penolakan.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string kode alasan penolakan
     */
    private function getOutOfDomainReason(int $itTokenCount, float $vocabularyOverlap, float $domainConfidence): string
    {
        if ($itTokenCount < self::MIN_IT_TOKENS) {
            return 'no_it_keywords';
        }
        if ($vocabularyOverlap < self::MIN_VOCABULARY_OVERLAP) {
            return 'low_vocabulary_overlap';
        }
        if ($domainConfidence < self::DOMAIN_CONFIDENCE_THRESHOLD) {
            return 'low_domain_confidence';
        }
        return 'in_domain';
    }

    /**
     * =========================================================================
     * 1. METODE APPLY SYNONYM MAPPING
     * =========================================================================
     *
     * Fungsi:
     * Menerapkan normalisasi sinonim pada query sebelum tokenisasi.
     *
     * Alur Proses:
     * 1. Menerima query yang akan dinormalisasi.
     * 2. Iterasi setiap mapping sinonim.
     * 3. Ganti typo dengan bentuk standar jika ditemukan.
     * 4. Mengembalikan query yang sudah dinormalisasi.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - string query yang sudah dinormalisasi sinonimnya
     */
    private function applySynonymMapping(string $query): string
    {
        $result = $query;
        foreach ($this->synonymMappings as $typo => $correct) {
            if (str_contains($result, $typo)) {
                $result = str_replace($typo, $correct, $result);
            }
        }
        return $result;
    }

    /**
     * =========================================================================
     * 1. METODE TOKENIZE QUERY
     * =========================================================================
     *
     * Fungsi:
     * Memecah query menjadi token-token individual.
     *
     * Alur Proses:
     * 1. Menerima query yang akan ditokenisasi.
     * 2. Konversi ke huruf kecil.
     * 3. Pecah query menggunakan pemisah whitespace dan tanda baca.
     * 4. Filter token dengan panjang > 1 karakter.
     * 5. Mengembalikan array token.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array token hasil tokenisasi dalam huruf kecil
     */
    private function tokenizeQuery(string $query): array
    {
        $query  = mb_strtolower($query);
        $tokens = preg_split('/[\s,;.!?()""\'\-]+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($tokens, fn($t) => mb_strlen($t) > 1);
    }

    /**
     * =========================================================================
     * 1. METODE SCORE DOMAINS
     * =========================================================================
     *
     * Fungsi:
     * Memberikan skor kepercayaan untuk setiap domain berdasarkan pencocokan kata kunci.
     *
     * Alur Proses:
     * 1. Menerima token-token dari query yang sudah dinormalisasi.
     * 2. Iterasi setiap domain dan kata kuncinya.
     * 3. Hitung exact match dan partial match.
     * 4. Normalisasi skor berdasarkan jumlah kata kunci domain.
     * 5. Normalisasi relatif jika beberapa domain terdeteksi.
     * 6. Mengembalikan array skor domain.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array [domain => skor_kepercayaan]
     */
    private function scoreDomains(array $tokens): array
    {
        $scores           = [];
        $totalDomainTokens = 0;

        foreach ($this->domainKeywords as $domain => $config) {
            $score    = 0.0;
            $keywords = $config['keywords'];

            foreach ($tokens as $token) {
                // Lewati term generik yang tidak bermakna untuk deteksi domain
                if (in_array($token, $this->genericTerms)) {
                    continue;
                }

                // Nilai setiap kata kunci domain terhadap token saat ini
                foreach ($keywords as $keyword) {
                    if ($token === $keyword) {
                        // Exact cocok: skor penuh
                        $score += 1.0;
                    } elseif (str_contains($keyword, $token) && mb_strlen($token) > 2) {
                        // Partial cocok (misalnya "wifi" cocok dengan "wireless")
                        $score += 0.5;
                    }
                }
            }

            // Normalisasi skor berdasarkan jumlah kata kunci domain
            if (!empty($keywords)) {
                $score = min(1.0, $score / (count($keywords) * 0.5));
            }

            $scores[$domain] = $score;
            if ($score > 0) {
                $totalDomainTokens++;
            }
        }

        // Normalisasi relatif jika beberapa domain terdeteksi sekaligus
        if ($totalDomainTokens > 1) {
            $maxScore = max($scores);
            if ($maxScore > 0) {
                foreach ($scores as $domain => $score) {
                    $scores[$domain] = $score / $maxScore;
                }
            }
        }

        return $scores;
    }

    /**
     * =========================================================================
     * 1. METODE GET CATEGORY IDS FOR DOMAIN
     * =========================================================================
     *
     * Fungsi:
     * Mengambil ID kategori dari database berdasarkan nama kategori yang dipetakan ke domain.
     *
     * Alur Proses:
     * 1. Menerima nama domain yang terdeteksi.
     * 2. Ambil konfigurasi domain keywords.
     * 3. Query database untuk ID kategori berdasarkan nama.
     * 4. Jika tidak ditemukan, coba pencarian case-insensitive.
     * 5. Mengembalikan array ID kategori yang relevan.
     *
     * Query yang Digunakan:
     * - Category::whereIn('name', $categoryNames)->pluck('id')->toArray(): Ambil ID kategori berdasarkan nama
     * - Category::where(function ($query) use ($categoryNames) { ... })->pluck('id')->toArray(): Pencarian case-insensitive
     *
     * Output:
     * - array ID kategori yang relevan
     */
    private function getCategoryIdsForDomain(string $domain): array
    {
        $config = $this->domainKeywords[$domain] ?? null;
        if (!$config) {
            return [];
        }

        $categoryNames = $config['categories'];

        // Query ini mengambil ID kategori berdasarkan nama yang dipetakan ke domain
        $categories = Category::whereIn('name', $categoryNames)->pluck('id')->toArray();

        // Jika tidak ada hasil, coba pencarian case-insensitive dengan normalisasi spasi
        if (empty($categories)) {
            $categories = Category::where(function ($query) use ($categoryNames) {
                foreach ($categoryNames as $name) {
                    $query->orWhereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($name))]);
                }
            })->pluck('id')->toArray();
        }

        return array_unique($categories);
    }

    /**
     * =========================================================================
     * 1. METODE GET ALL DOMAINS
     * =========================================================================
     *
     * Fungsi:
     * Mengembalikan daftar semua nama domain IT yang dikenali oleh sistem.
     *
     * Alur Proses:
     * 1. Mengambil keys dari array domainKeywords.
     * 2. Mengembalikan array nama domain.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array nama domain yang tersedia
     */
    public function getAllDomains(): array
    {
        return array_keys($this->domainKeywords);
    }

    /**
     * =========================================================================
     * 1. METODE GET DOMAIN KEYWORDS
     * =========================================================================
     *
     * Fungsi:
     * Mengembalikan kata kunci yang terkurasi untuk domain tertentu.
     *
     * Alur Proses:
     * 1. Menerima nama domain yang ingin diperiksa.
     * 2. Ambil kata kunci dari konfigurasi domainKeywords.
     * 3. Mengembalikan array kata kunci domain.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array kata kunci domain, atau array kosong jika domain tidak ditemukan
     */
    public function getDomainKeywords(string $domain): array
    {
        return $this->domainKeywords[$domain]['keywords'] ?? [];
    }

    /**
     * =========================================================================
     * 1. METODE CLEAR CACHE
     * =========================================================================
     *
     * Fungsi:
     * Menghapus cache saran domain yang tersimpan.
     *
     * Alur Proses:
     * 1. Menghapus cache menggunakan Cache::forget.
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
        Cache::forget(self::DOMAIN_CACHE_KEY);
    }

    /**
     * =========================================================================
     * 1. METODE GET CLEAN DOMAIN SUGGESTIONS
     * =========================================================================
     *
     * Fungsi:
     * Mengembalikan saran domain yang bersih dan terverifikasi untuk ditampilkan kepada pengguna.
     *
     * Alur Proses:
     * 1. Cek cache dan kembalikan langsung jika tersedia.
     * 2. Bangun saran dari konfigurasi domain terkurasi.
     * 3. Query database untuk kategori yang memiliki artikel aktif.
     * 4. Deduplikasi untuk menghindari saran ganda.
     * 5. Simpan ke cache dan kembalikan.
     *
     * Query yang Digunakan:
     * - Category::whereHas('articles', function ($query) { ... })->orderBy('name')->get(['id', 'name']): Ambil kategori dengan artikel aktif
     *
     * Output:
     * - array saran domain [['id', 'type', 'label', 'kata kunci?'], ...]
     */
    public function getCleanDomainSuggestions(): array
    {
        // Cek cache terlebih dahulu untuk menghemat query database
        $cached = Cache::get(self::DOMAIN_CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        $suggestions = [];

        // Bangun saran dari domain terkurasi
        foreach ($this->domainKeywords as $domain => $config) {
            $suggestions[] = [
                'id'       => $domain,
                'type'     => 'domain',
                'label'    => ucfirst($domain),
                'keywords' => $config['keywords'],
            ];
        }

        // Query database untuk kategori yang memiliki artikel aktif
        $categories = Category::whereHas('articles', function ($query) {
            $query->where('is_published', true)
                  ->where('publish_status', 'approved');
        })
        ->orderBy('name')
        ->get(['id', 'name']);

        // Tambahkan kategori database, hindari duplikat dengan saran domain
        foreach ($categories as $category) {
            $isDuplicate = false;
            foreach ($suggestions as $suggestion) {
                if (stripos($suggestion['label'], $category->name) !== false ||
                    stripos($category->name, $suggestion['label']) !== false) {
                    $isDuplicate = true;
                    break;
                }
            }

            if (!$isDuplicate) {
                $suggestions[] = [
                    'id'    => $category->id,
                    'type'  => 'category',
                    'label' => $category->name,
                ];
            }
        }

        // Simpan ke cache selama 1 jam
        Cache::put(self::DOMAIN_CACHE_KEY, $suggestions, self::DOMAIN_CACHE_TTL);

        return $suggestions;
    }

    /**
     * =========================================================================
     * 1. METODE GET IT DOMAIN VOCABULARY
     * =========================================================================
     *
     * Fungsi:
     * Mengembalikan seluruh kosakata IT yang digunakan untuk deteksi out-of-domain.
     *
     * Alur Proses:
     * 1. Mengembalikan array itDomainVocabulary.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array seluruh term IT yang dikenali sistem
     */
    public function getITDomainVocabulary(): array
    {
        return $this->itDomainVocabulary;
    }

    /**
     * =========================================================================
     * 1. METODE GET OUT OF DOMAIN KEYWORDS
     * =========================================================================
     *
     * Fungsi:
     * Mengembalikan daftar kata kunci non-IT yang menyebabkan penolakan langsung.
     *
     * Alur Proses:
     * 1. Mengembalikan array outOfDomainKeywords.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array kata kunci yang menandakan query di luar domain IT
     */
    public function getOutOfDomainKeywords(): array
    {
        return $this->outOfDomainKeywords;
    }
}
