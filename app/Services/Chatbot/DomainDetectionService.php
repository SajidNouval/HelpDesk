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
     * 1. Fungsi detectDomain()
     *
     * Fungsi ini mendeteksi domain IT utama dari query pengguna dan mengembalikan
     * ID kategori database yang relevan untuk digunakan sebagai filter artikel.
     *
     * Deteksi domain didasarkan pada pencocokan kata kunci terkurasi. Setiap domain
     * mendapat skor berdasarkan seberapa banyak kata kunci domain-nya muncul di query.
     * Domain dengan skor tertinggi (di atas ambang 0.3) dipilih sebagai domain utama.
     *
     * Alur proses:
     * 1. Normalisasi query: koreksi typo + pemetaan sinonim.
     * 2. Tokenisasi query menjadi term individual.
     * 3. Penilaian skor untuk setiap domain berdasarkan pencocokan kata kunci.
     * 4. Filter domain yang memenuhi ambang kepercayaan.
     * 5. Ambil ID kategori database untuk domain terpilih.
     *
     * Parameter:
     * - string $query : Query mentah dari pengguna
     *
     * Kembalikan:
     * - array : [
     *     'detected'    => bool,           // Apakah domain berhasil terdeteksi
     *     'domain'      => string|null,    // Nama domain yang terdeteksi
     *     'category_ids' => array,         // ID kategori database yang relevan
     *     'confidence'  => float,          // Nilai kepercayaan deteksi (0.0 - 1.0)
     *     'all_scores'  => array           // Skor semua domain (hanya jika detected)
     *   ]
     */
    public function detectDomain(string $query): array
    {
        // 1.1 Query kosong — tidak ada domain yang bisa dideteksi
        if (empty(trim($query))) {
            return ['detected' => false, 'domain' => null, 'category_ids' => [], 'confidence' => 0.0];
        }

        // 1.2 Normalisasi query dengan koreksi typo menggunakan PreprocessingService
        $normalizedQuery = $this->preprocessor->normalizeTypos($query);

        // 1.3 Terapkan pemetaan sinonim untuk toleransi typo tambahan
        $normalizedQuery = $this->applySynonymMapping($normalizedQuery);

        // 1.4 Tokenisasi query menjadi token individual (tanpa stemming
        // agar kata kunci domain tetap terjaga bentuk aslinya)
        $tokens = $this->tokenizeQuery($normalizedQuery);

        // 1.5 Nilai setiap domain berdasarkan pencocokan token dengan kata kunci domain
        $domainScores = $this->scoreDomains($tokens);

        // 1.6 Saring domain yang melewati ambang kepercayaan minimum
        $threshold      = 0.3;
        $detectedDomains = array_filter($domainScores, fn($score) => $score >= $threshold);

        if (empty($detectedDomains)) {
            return ['detected' => false, 'domain' => null, 'category_ids' => [], 'confidence' => 0.0];
        }

        // 1.7 Pilih domain dengan skor tertinggi sebagai domain utama
        arsort($detectedDomains);
        $primaryDomain = array_key_first($detectedDomains);
        $confidence    = $detectedDomains[$primaryDomain];

        // 1.8 Query ini mengambil ID kategori dari database berdasarkan nama kategori
        // yang dipetakan ke domain yang terdeteksi
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
     * 2. Fungsi detectOutOfDomain()
     *
     * Fungsi ini menentukan apakah query pengguna berada di luar domain IT/support.
     * Jika query termasuk out-of-domain, sistem harus menolaknya dengan pesan informatif
     * daripada mencoba mencari artikel yang tidak relevan.
     *
     * Logika evaluasi (berurutan dari yang paling kritis):
     * 1. Jika query mengandung token "never reject" → SELALU diterima (di-domain)
     * 2. Jika query mengandung kata kunci non-IT eksplisit → DITOLAK (out-of-domain)
     * 3. Jika tidak ada token IT sama sekali → DITOLAK
     * 4. Jika ada token IT + overlap vocabulary cukup → DITERIMA
     * 5. Jika overlap rendah + confidence domain rendah → DITOLAK
     * 6. Default: lebih baik menerima query borderline daripada menolak yang valid
     *
     * Alur proses:
     * 1. Normalisasi dan tokenisasi query.
     * 2. Cek kata kunci non-IT eksplisit (penolakan langsung).
     * 3. Hitung jumlah token IT dalam query.
     * 4. Hitung rasio overlap vocabulary IT.
     * 5. Cek confidence deteksi domain.
     * 6. Evaluasi gabungan semua sinyal untuk keputusan final.
     *
     * Parameter:
     * - string $query : Query mentah dari pengguna
     *
     * Kembalikan:
     * - array : [
     *     'is_out_of_domain'  => bool,    // true jika query di luar domain IT
     *     'reason'            => string,  // Alasan keputusan
     *     'it_token_count'    => int,     // Jumlah token IT yang ditemukan
     *     'vocabulary_overlap' => float,  // Rasio overlap dengan kosakata IT
     *     'domain_confidence'  => float   // Confidence deteksi domain (jika detected)
     *   ]
     */
    public function detectOutOfDomain(string $query): array
    {
        // 2.1 Query kosong langsung ditolak
        if (empty(trim($query))) {
            return [
                'is_out_of_domain'   => true,
                'reason'             => 'empty_query',
                'it_token_count'     => 0,
                'vocabulary_overlap' => 0.0,
            ];
        }

        // 2.2 Normalisasi dan tokenisasi query
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

        // 2.3 Cek kata kunci non-IT eksplisit — jika ada, tolak langsung
        // tanpa perlu evaluasi lebih lanjut
        $hasExplicitOutOfDomain = $this->hasExplicitOutOfDomainKeywords($tokens);
        if ($hasExplicitOutOfDomain) {
            return [
                'is_out_of_domain'   => true,
                'reason'             => 'explicit_out_of_domain_keywords',
                'it_token_count'     => 0,
                'vocabulary_overlap' => 0.0,
            ];
        }

        // 2.4 Hitung jumlah token yang termasuk kosakata IT
        $itTokenCount = $this->countITDomainTokens($tokens);

        // 2.5 Hitung rasio overlap kosakata IT terhadap total token bermakna
        $vocabularyOverlap = $this->calculateVocabularyOverlap($tokens);

        // 2.6 Ambil confidence deteksi domain sebagai sinyal tambahan
        $domainInfo      = $this->detectDomain($query);
        $domainConfidence = $domainInfo['confidence'] ?? 0.0;

        // 2.7 Evaluasi gabungan semua sinyal untuk keputusan final
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
     * Fungsi pembantu: hasExplicitOutOfDomainKeywords() [private]
     *
     * Memeriksa apakah ada token dalam query yang secara eksplisit
     * merupakan kata kunci non-IT (dari daftar outOfDomainKeywords).
     *
     * Parameter:
     * - array $token : Token-token dari query yang sudah dinormalisasi
     *
     * Kembalikan:
     * - bool : true jika ada kata kunci non-IT eksplisit
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
     * Fungsi pembantu: countITDomainTokens() [private]
     *
     * Menghitung berapa banyak token dalam query yang termasuk dalam
     * kosakata IT (itDomainVocabulary), dengan mengecualikan term generik.
     *
     * Parameter:
     * - array $token : Token-token dari query yang sudah dinormalisasi
     *
     * Kembalikan:
     * - int : Jumlah token yang dikenali sebagai kosakata IT
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
     * Fungsi pembantu: calculateVocabularyOverlap() [private]
     *
     * Menghitung rasio antara token IT yang ditemukan dengan total token bermakna.
     * Rasio ini mengindikasikan seberapa banyak query berkaitan dengan domain IT.
     *
     * Pencocokan dilakukan dua arah:
     * - Exact cocok: token persis sama dengan kosakata IT (bobot 1.0)
     * - Partial cocok (kosakata mengandung token): bobot 0.5
     * - Partial cocok (token mengandung kosakata): bobot 0.3
     *
     * Parameter:
     * - array $token : Token-token dari query yang sudah dinormalisasi
     *
     * Kembalikan:
     * - float : Rasio overlap [0.0, 1.0]
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
     * Fungsi pembantu: evaluateOutOfDomain() [private]
     *
     * Mengevaluasi apakah query termasuk out-of-domain berdasarkan kombinasi
     * beberapa sinyal: token IT, overlap kosakata, dan confidence domain.
     *
     * Logika berurutan (dari prioritas tertinggi):
     * 1. Jika ada token "never reject" → SELALU di-domain (kembalikan false)
     * 2. Jika tidak ada token IT → out-of-domain (kembalikan true)
     * 3. Jika ada token IT + overlap memadai → di-domain (kembalikan false)
     * 4. Jika overlap rendah + confidence domain rendah → out-of-domain (kembalikan true)
     * 5. Jika domain confidence memadai → di-domain (kembalikan false)
     * 6. Default: terima jika ada minimal 1 token IT
     *
     * Parameter:
     * - array $token           : Token-token dari query
     * - int   $itTokenCount     : Jumlah token IT
     * - float $vocabularyOverlap : Rasio overlap kosakata IT
     * - float $domainConfidence : Confidence deteksi domain
     *
     * Kembalikan:
     * - bool : true jika out-of-domain, false jika di-domain
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
     * Fungsi pembantu: containsNeverRejectToken() [private]
     *
     * Memeriksa apakah query mengandung token yang tidak boleh pernah ditolak.
     * Pengecekan dilakukan secara exact cocok maupun partial cocok untuk
     * menangani typo seperti "virussss" yang mengandung "virus".
     *
     * Parameter:
     * - array $token : Token-token dari query yang sudah dinormalisasi
     *
     * Kembalikan:
     * - bool : true jika ditemukan token "never reject"
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
     * Fungsi pembantu: getOutOfDomainReason() [private]
     *
     * Menentukan alasan spesifik mengapa query diklasifikasikan sebagai out-of-domain.
     * Alasan ini berguna untuk debugging dan pemantauan kualitas sistem.
     *
     * Parameter:
     * - int   $itTokenCount     : Jumlah token IT dalam query
     * - float $vocabularyOverlap : Rasio overlap kosakata IT
     * - float $domainConfidence : Confidence deteksi domain
     *
     * Kembalikan:
     * - string : Kode alasan penolakan
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
     * Fungsi pembantu: applySynonymMapping() [private]
     *
     * Menerapkan normalisasi sinonim pada query sebelum tokenisasi.
     * Memastikan variasi penulisan yang berbeda (termasuk typo umum)
     * diubah ke bentuk standar yang dikenali oleh sistem deteksi domain.
     *
     * Parameter:
     * - string $query : Query yang akan dinormalisasi
     *
     * Kembalikan:
     * - string : Query yang sudah dinormalisasi sinonimnya
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
     * Fungsi pembantu: tokenizeQuery() [private]
     *
     * Memecah query menjadi token-token individual menggunakan pemisah
     * whitespace dan tanda baca. Hanya token dengan panjang > 1 karakter
     * yang dipertahankan untuk menghindari noise dari karakter tunggal.
     *
     * Parameter:
     * - string $query : Query yang akan ditokenisasi
     *
     * Kembalikan:
     * - array : Array token hasil tokenisasi dalam huruf kecil
     */
    private function tokenizeQuery(string $query): array
    {
        $query  = mb_strtolower($query);
        $tokens = preg_split('/[\s,;.!?()""\'\-]+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($tokens, fn($t) => mb_strlen($t) > 1);
    }

    /**
     * Fungsi pembantu: scoreDomains() [private]
     *
     * Memberikan skor kepercayaan untuk setiap domain berdasarkan
     * seberapa banyak kata kunci domain muncul dalam token query.
     *
     * Pencocokan dilakukan dua cara:
     * - Exact cocok antara token dan kata kunci domain: skor +1.0
     * - Partial cocok (kata kunci mengandung token): skor +0.5
     *
     * Skor dinormalisasi agar berada di rentang 0.0 - 1.0.
     * Jika beberapa domain terdeteksi, skor dinormalisasi relatif terhadap skor tertinggi.
     *
     * Parameter:
     * - array $token : Token-token dari query yang sudah dinormalisasi
     *
     * Kembalikan:
     * - array : Array asosiatif [domain => skor_kepercayaan]
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
     * Fungsi pembantu: getCategoryIdsForDomain() [private]
     *
     * Mengambil ID kategori dari database berdasarkan nama kategori yang
     * dipetakan ke domain yang terdeteksi.
     *
     * Pencarian dilakukan dua tahap:
     * 1. Pencarian exact (case-sensitive) berdasarkan nama kategori.
     * 2. Jika tidak ditemukan, pencarian case-insensitive dengan TRIM.
     *
     * Parameter:
     * - string $domain : Nama domain yang terdeteksi (misalnya: 'wifi', 'printer')
     *
     * Kembalikan:
     * - array : Array ID kategori yang relevan
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
     * 3. Fungsi getAllDomains()
     *
     * Fungsi ini mengembalikan daftar semua nama domain IT yang dikenali oleh sistem.
     * Berguna untuk keperluan debugging, referensi internal, dan pengujian.
     *
     * Kembalikan:
     * - array : Array nama domain yang tersedia (misalnya: ['wifi', 'internet', ...])
     */
    public function getAllDomains(): array
    {
        return array_keys($this->domainKeywords);
    }

    /**
     * 4. Fungsi getDomainKeywords()
     *
     * Fungsi ini mengembalikan kata kunci yang terkurasi untuk domain tertentu.
     * Berguna untuk inspeksi konfigurasi dan pengujian sistem deteksi.
     *
     * Parameter:
     * - string $domain : Nama domain yang ingin diperiksa kata kuncinya
     *
     * Kembalikan:
     * - array : Array kata kunci domain, atau array kosong jika domain tidak ditemukan
     */
    public function getDomainKeywords(string $domain): array
    {
        return $this->domainKeywords[$domain]['keywords'] ?? [];
    }

    /**
     * 5. Fungsi clearCache()
     *
     * Fungsi ini menghapus cache saran domain yang tersimpan.
     * Perlu dipanggil ketika ada perubahan kategori di database
     * agar cache tidak menampilkan data yang sudah usang.
     *
     * Kembalikan:
     * - void
     */
    public function clearCache(): void
    {
        Cache::forget(self::DOMAIN_CACHE_KEY);
    }

    /**
     * 6. Fungsi getCleanDomainSuggestions()
     *
     * Fungsi ini mengembalikan saran domain yang bersih dan terverifikasi
     * untuk ditampilkan kepada pengguna sebagai pilihan topik.
     *
     * Data saran berasal dari dua sumber yang valid:
     * 1. Domain terkurasi dari konfigurasi sistem (domainKeywords).
     * 2. Kategori aktif dari database yang memiliki artikel yang dipublikasi.
     *
     * Deduplikasi dilakukan untuk menghindari tampilan yang berulang.
     * Hasil di-cache selama 1 jam untuk efisiensi.
     *
     * Alur proses:
     * 1. Cek cache — kembalikan langsung jika tersedia.
     * 2. Bangun saran dari konfigurasi domain terkurasi.
     * 3. Tambahkan kategori dari database yang memiliki artikel aktif.
     * 4. Deduplikasi untuk menghindari saran ganda.
     * 5. Simpan ke cache dan kembalikan.
     *
     * Kembalikan:
     * - array : Array saran domain [['id', 'type', 'label', 'kata kunci?'], ...]
     */
    public function getCleanDomainSuggestions(): array
    {
        // 6.1 Cek cache terlebih dahulu untuk menghemat query database
        $cached = Cache::get(self::DOMAIN_CACHE_KEY);
        if ($cached !== null) {
            return $cached;
        }

        $suggestions = [];

        // 6.2 Bangun saran dari domain terkurasi (tidak dari input pengguna)
        foreach ($this->domainKeywords as $domain => $config) {
            $suggestions[] = [
                'id'       => $domain,
                'type'     => 'domain',
                'label'    => ucfirst($domain),
                'keywords' => $config['keywords'],
            ];
        }

        // 6.3 Query ini mengambil kategori dari database yang memiliki artikel aktif
        // sebagai sumber saran tambahan yang valid
        $categories = Category::whereHas('articles', function ($query) {
            $query->where('is_published', true)
                  ->where('publish_status', 'approved');
        })
        ->orderBy('name')
        ->get(['id', 'name']);

        // 6.4 Tambahkan kategori database, hindari duplikat dengan saran domain
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

        // 6.5 Simpan ke cache selama 1 jam agar tidak berulang query ke database
        Cache::put(self::DOMAIN_CACHE_KEY, $suggestions, self::DOMAIN_CACHE_TTL);

        return $suggestions;
    }

    /**
     * 7. Fungsi getITDomainVocabulary()
     *
     * Fungsi ini mengembalikan seluruh kosakata IT yang digunakan untuk
     * deteksi out-of-domain. Berguna untuk debugging dan pengujian cakupan kosakata.
     *
     * Kembalikan:
     * - array : Array seluruh term IT yang dikenali sistem
     */
    public function getITDomainVocabulary(): array
    {
        return $this->itDomainVocabulary;
    }

    /**
     * 8. Fungsi getOutOfDomainKeywords()
     *
     * Fungsi ini mengembalikan daftar kata kunci non-IT yang menyebabkan
     * penolakan langsung (immediate rejection). Berguna untuk debugging
     * dan pemeriksaan daftar kata kunci yang diblokir.
     *
     * Kembalikan:
     * - array : Array kata kunci yang menandakan query di luar domain IT
     */
    public function getOutOfDomainKeywords(): array
    {
        return $this->outOfDomainKeywords;
    }
}
