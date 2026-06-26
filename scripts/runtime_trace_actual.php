<?php

namespace Illuminate\Support\Facades {
    class Cache
    {
        public static function get($key, $default = null)
        {
            return $default;
        }
        public static function put($key, $value, $ttl = null)
        {
            return true;
        }
        public static function forget($key)
        {
            return true;
        }
    }
}

namespace {
    if (!function_exists('config')) {
        function config($key = null, $default = null)
        {
            return $default;
        }
    }

    require __DIR__ . '/../vendor/autoload.php';

    if (function_exists('app')) {
        $container = app();
        if (method_exists($container, 'bound') && !$container->bound('url')) {
            class FakeUrlGenerator
            {
                public function route($name, $parameters = [], $absolute = true)
                {
                    $path = is_array($parameters) ? implode('/', array_values((array) $parameters)) : $parameters;
                    return '#'.trim($name . ($path ? '/'.$path : ''), '/');
                }
            }
            $container->instance('url', new FakeUrlGenerator());
        }
    }

    use App\Services\Chatbot\AdvancedRetrievalService;
    use App\Services\Chatbot\PreprocessingService;
    use App\Services\Chatbot\TfidfService;
    use App\Services\Chatbot\CosineSimilarityService;
    use App\Services\Chatbot\DomainDetectionService;
    use App\Services\Chatbot\VocabularyService;
    use App\Services\Chatbot\ImportantPhraseService;
    use Illuminate\Support\Collection;
    use Illuminate\Support\Str;

if (!class_exists('App\\Services\\Chatbot\\FakeVocabularyService')) {
    class FakeVocabularyService extends VocabularyService
    {
        public function __construct()
        {
            // No Laravel bootstrap required.
        }

        public function normalizeQuery(string $query): array
        {
            return [
                'original' => $query,
                'normalized' => $query,
                'corrections' => [],
            ];
        }
    }
}

srand(1234);

$preprocessor = new PreprocessingService();
$tfidfService = new TfidfService($preprocessor);
$similarityService = new CosineSimilarityService();
$domainDetector = new DomainDetectionService($preprocessor);
$vocabularyService = new FakeVocabularyService();
$phraseService = new ImportantPhraseService();

$service = new AdvancedRetrievalService(
    $preprocessor,
    $tfidfService,
    $similarityService,
    $domainDetector,
    $vocabularyService,
    $phraseService
);

$reflect = new ReflectionClass($service);
$prepareDocuments = $reflect->getMethod('prepareDocuments');
$prepareDocuments->setAccessible(true);
$buildTfidfVectors = $reflect->getMethod('buildTfidfVectors');
$buildTfidfVectors->setAccessible(true);
$hybridRanking = $reflect->getMethod('hybridRanking');
$hybridRanking->setAccessible(true);
$diversifyResults = $reflect->getMethod('diversifyResults');
$diversifyResults->setAccessible(true);
$applyThresholdAndLimit = $reflect->getMethod('applyThresholdAndLimit');
$applyThresholdAndLimit->setAccessible(true);
$expandQuery = $reflect->getMethod('expandQuery');
$expandQuery->setAccessible(true);
$getAllowedCategories = $reflect->getMethod('getAllowedCategories');
$getAllowedCategories->setAccessible(true);
$detectMultiIntent = $reflect->getMethod('detectMultiIntent');
$detectMultiIntent->setAccessible(true);
$normalizeTypos = $reflect->getMethod('normalizeTypos');
$normalizeTypos->setAccessible(true);
$normalizeSynonyms = $reflect->getMethod('normalizeSynonyms');
$normalizeSynonyms->setAccessible(true);
$calculateTitleOverlap = $reflect->getMethod('calculateTitleOverlap');
$calculateTitleOverlap->setAccessible(true);
$calculateQueryCoverage = $reflect->getMethod('calculateQueryCoverage');
$calculateQueryCoverage->setAccessible(true);
$calculateExactPhraseBonus = $reflect->getMethod('calculateExactPhraseBonus');
$calculateExactPhraseBonus->setAccessible(true);
$calculateDomainMatch = $reflect->getMethod('calculateDomainMatch');
$calculateDomainMatch->setAccessible(true);
$calculateDomainPenalty = $reflect->getMethod('calculateDomainPenalty');
$calculateDomainPenalty->setAccessible(true);

$domainReflect = new ReflectionClass($domainDetector);
$applySynonymMappingMethod = $domainReflect->getMethod('applySynonymMapping');
$applySynonymMappingMethod->setAccessible(true);
$tokenizeQueryMethod = $domainReflect->getMethod('tokenizeQuery');
$tokenizeQueryMethod->setAccessible(true);
$scoreDomainsMethod = $domainReflect->getMethod('scoreDomains');
$scoreDomainsMethod->setAccessible(true);

$detectDomainInfo = function (string $query) use (
    $preprocessor,
    $applySynonymMappingMethod,
    $tokenizeQueryMethod,
    $scoreDomainsMethod,
    $domainDetector
) {
    $normalizedQuery = $preprocessor->normalizeTypos($query);
    $normalizedQuery = $applySynonymMappingMethod->invoke($domainDetector, $normalizedQuery);
    $tokens = $tokenizeQueryMethod->invoke($domainDetector, $normalizedQuery);
    $domainScores = $scoreDomainsMethod->invoke($domainDetector, $tokens);

    $detectedDomains = array_filter($domainScores, fn($score) => $score >= 0.3);
    if (empty($detectedDomains)) {
        return [
            'detected' => false,
            'domain' => null,
            'confidence' => 0.0,
            'all_scores' => $domainScores,
        ];
    }

    arsort($detectedDomains);
    $primaryDomain = array_key_first($detectedDomains);

    return [
        'detected' => true,
        'domain' => $primaryDomain,
        'confidence' => round($detectedDomains[$primaryDomain], 4),
        'all_scores' => $domainScores,
    ];
};

$articlesData = [
    [
        'id' => 1,
        'title' => 'Cara Mengatasi Wifi Tidak Terhubung ke Perangkat',
        'content' => 'Wifi yang tidak terhubung dapat disebabkan oleh beberapa faktor. Pertama, pastikan router dalam keadaan menyala dan lampu indikator berfungsi dengan baik. Kedua, cek apakah perangkat Anda dalam jangkauan signal wifi. Ketiga, coba restart router dengan mencabut kabel power selama 10 detik kemudian pasang kembali. Jika masih belum berhasil, coba lupakan jaringan wifi di pengaturan perangkat Anda kemudian sambung kembali dengan memasukkan password. Pastikan juga MAC address filtering tidak memblokir perangkat Anda.',
        'excerpt' => 'Panduan lengkap mengatasi masalah wifi yang tidak terhubung ke perangkat Anda.',
        'keywords' => 'wifi tidak terhubung, router wifi, signal wifi, restart router, MAC address filtering, jaringan wireless',
        'slug' => Str::slug('Cara Mengatasi Wifi Tidak Terhubung ke Perangkat'),
        'category_id' => 1,
        'category_name' => 'Wifi',
    ],
    [
        'id' => 2,
        'title' => 'Solusi Internet Lambat pada Jaringan Wifi Kantor',
        'content' => 'Internet lambat pada wifi kantor dapat mengganggu produktivitas. Penyebab umum: terlalu banyak perangkat terhubung ke access point, bandwidth terbagi untuk download/upload besar, atau interferensi channel wifi. Solusi: 1) Batasi jumlah perangkat per access point (maksimal 15-20 device), 2) Gunakan Quality of Service (QoS) untuk prioritaskan traffic penting, 3) Upgrade paket bandwidth dari ISP, 4) Gunakan kabel LAN untuk perangkat kritis seperti PC server, 5) Pindah ke frekuensi 5GHz untuk mengurangi interferensi.',
        'excerpt' => 'Tips mengatasi internet lambat khusus untuk jaringan wifi kantor.',
        'keywords' => 'internet lambat, bandwidth kantor, QoS router, access point, frekuensi 5GHz, ISP',
        'slug' => Str::slug('Solusi Internet Lambat pada Jaringan Wifi Kantor'),
        'category_id' => 1,
        'category_name' => 'Wifi',
    ],
    [
        'id' => 3,
        'title' => 'Cara Reset Router dan Konfigurasi Ulang Jaringan',
        'content' => 'Reset router diperlukan saat konfigurasi bermasalah atau lupa password admin. Langkah reset: 1) Cari tombol reset di belakang router (biasanya lubang kecil), 2) Tekan tombol reset selama 10-15 detik menggunakan paperclip, 3) Tunggu router restart (semua lampu berkedip), 4) Akses halaman admin router di 192.168.1.1 atau 192.168.0.1, 5) Login dengan kredensial default (admin/admin atau lihat stiker router), 6) Konfigurasi ulang SSID, password wifi, dan pengaturan keamanan. Catatan: reset akan menghapus semua pengaturan custom.',
        'excerpt' => 'Panduan reset router ke pengaturan pabrik dan konfigurasi ulang.',
        'keywords' => 'reset router, konfigurasi router, admin router, 192.168.1.1, SSID, password default',
        'slug' => Str::slug('Cara Reset Router dan Konfigurasi Ulang Jaringan'),
        'category_id' => 1,
        'category_name' => 'Wifi',
    ],
    [
        'id' => 4,
        'title' => 'Mengatasi Wifi Sering Putus Nyambung pada Jaringan Kantor',
        'content' => 'Wifi yang sering terputus-putus di kantor sangat mengganggu. Penyebab dan solusi: 1) Interferensi channel - ubah channel wifi ke channel 1, 6, atau 11 yang tidak overlap, 2) Jarak terlalu jauh dari access point - gunakan wifi extender atau mesh network, 3) Router overheating - pastikan ventilasi router baik dan tidak tertutup debu, 4) Driver network adapter usang - update driver wifi adapter di Device Manager, 5) Terlalu banyak perangkat - batasi koneksi atau upgrade ke access point enterprise, 6) Firmware router usang - update ke versi terbaru dari website manufacturer.',
        'excerpt' => 'Solusi wifi tidak stabil yang sering terputus di lingkungan kantor.',
        'keywords' => 'wifi putus nyambung, interferensi channel, wifi extender, mesh network, firmware router, access point enterprise',
        'slug' => Str::slug('Mengatasi Wifi Sering Putus Nyambung pada Jaringan Kantor'),
        'category_id' => 1,
        'category_name' => 'Wifi',
    ],
    [
        'id' => 5,
        'title' => 'Cara Mengamankan Jaringan Wifi dari Akses Tidak Sah',
        'content' => 'Keamanan jaringan wifi kantor sangat penting untuk melindungi data perusahaan. Langkah pengamanan: 1) Gunakan enkripsi WPA3 atau WPA2-AES, hindari WEP yang sudah usang dan mudah diretas, 2) Ganti password wifi secara berkala (minimal 3 bulan sekali), 3) Gunakan password yang kuat minimal 12 karakter dengan kombinasi huruf besar, huruf kecil, angka, dan simbol, 4) Sembunyikan SSID network agar tidak terlihat di daftar wifi publik, 5) Aktifkan MAC address filtering untuk whitelist perangkat yang boleh connect, 6) Update firmware router secara rutin untuk patch keamanan, 7) Pisahkan network guest dari network internal perusahaan menggunakan VLAN.',
        'excerpt' => 'Strategi pengamanan jaringan wifi perusahaan dari akses tidak sah.',
        'keywords' => 'keamanan wifi, enkripsi WPA3, WPA2-AES, MAC address filtering, hide SSID, VLAN, network guest',
        'slug' => Str::slug('Cara Mengamankan Jaringan Wifi dari Akses Tidak Sah'),
        'category_id' => 1,
        'category_name' => 'Wifi',
    ],
    [
        'id' => 6,
        'title' => 'Troubleshooting Koneksi Internet Putus Nyambung dari ISP',
        'content' => 'Koneksi internet yang sering putus dari provider ISP memerlukan troubleshooting sistematis. Langkah diagnosis: 1) Cek lampu indikator modem - jika lampu LOS/PON berkedip merah, ada gangguan dari ISP, 2) Test koneksi langsung ke modem menggunakan kabel LAN (bypass router) untuk pastikan masalah bukan dari router, 3) Ping gateway modem (biasanya 192.168.100.1) - jika timeout, masalah di modem atau kabel, 4) Cek apakah ada gangguan massal di area Anda dengan hubungi call center ISP, 5) Restart modem dengan mencabut power 30 detik, 6) Jika menggunakan fiber optic, pastikan kabel fiber tidak tertekuk tajam atau rusak.',
        'excerpt' => 'Cara mendiagnosis dan mengatasi masalah koneksi internet dari provider ISP.',
        'keywords' => 'internet putus, ISP gangguan, modem indicator, fiber optic, call center ISP, restart modem',
        'slug' => Str::slug('Troubleshooting Koneksi Internet Putus Nyambung dari ISP'),
        'category_id' => 1,
        'category_name' => 'Wifi',
    ],
    [
        'id' => 7,
        'title' => 'Cara Mengatasi Printer Offline dan Tidak Terdeteksi',
        'content' => 'Printer yang statusnya offline dan tidak terdeteksi komputer dapat diatasi dengan langkah berikut: 1) Periksa koneksi kabel USB - pastikan tertancap kuat di kedua port, coba port USB lain di komputer, 2) Untuk printer network, pastikan IP address printer sama subnet dengan komputer (ping IP printer dari Command Prompt), 3) Set printer sebagai default device di Control Panel > Devices and Printers > klik kanan printer > Set as default printer, 4) Restart Print Spooler service (tekan Win+R > services.msc > cari Print Spooler > restart), 5) Update atau reinstall driver printer dari website manufacturer, 6) Hapus printer dari Devices and Printers lalu add kembali menggunakan Add a printer wizard.',
        'excerpt' => 'Solusi lengkap mengatasi printer yang status offline dan tidak terdeteksi komputer.',
        'keywords' => 'printer offline, printer tidak terdeteksi, kabel USB, IP address printer, Print Spooler, driver printer',
        'slug' => Str::slug('Cara Mengatasi Printer Offline dan Tidak Terdeteksi'),        'category_id' => 2,        'category_name' => 'Printer',
    ],
    [
        'id' => 8,
        'title' => 'Troubleshooting Printer Tidak Mau Ngeprint (No Response)',
        'content' => 'Printer yang tidak merespon perintah print memerlukan troubleshooting sistematis: 1) Periksa apakah printer menyala dan tidak ada error code di display printer, 2) Cek apakah ada kertas di tray dan tidak ada kertas macet (paper jam), 3) Pastikan tinta/toner tidak habis - cek level melalui printer properties, 4) Clear print queue yang macet - buka Devices and Printers > klik printer > See what\'s printing > Cancel all documents, 5) Restart printer dengan mencabut power 30 detik, 6) Test print dari printer properties (Printer Properties > General > Print Test Page), 7) Jika masih tidak bisa, uninstall driver printer sepenuhnya dan install ulang dari website manufacturer.',
        'excerpt' => 'Cara mengatasi printer yang tidak merespon perintah print.',
        'keywords' => 'printer tidak ngeprint, printer no response, paper jam, tinta habis, print queue, test print',
        'slug' => Str::slug('Troubleshooting Printer Tidak Mau Ngeprint (No Response)'),
        'category_id' => 2,
        'category_name' => 'Printer',
    ],
    [
        'id' => 9,
        'title' => 'Cara Mengatasi Hasil Print Bergaris atau Tidak Jelas',
        'content' => 'Hasil print bergaris atau tidak jelas menandakan masalah di print head atau tinta. Untuk printer inkjet: 1) Jalankan print head cleaning melalui printer properties (Maintenance tab > Head Cleaning), 2) Lakukan nozzle check untuk cek kondisi nozzle, 3) Jika masih bergaris, lakukan deep cleaning (maksimal 3x berturut-turut), 4) Pastikan printer digunakan minimal 1x seminggu untuk mencegah tinta kering di print head, 5) Ganti cartridge jika tinta sudah sangat sedikit atau expired. Untuk printer laser: 1) Goyangkan toner cartridge secara horizontal untuk meratakan toner, 2) Bersihkan corona wire menggunakan cotton bud, 3) Ganti drum unit jika sudah aus (biasanya setelah 10.000-15.000 halaman), 4) Cek fuser unit jika hasil print mudah luntur.',
        'excerpt' => 'Solusi hasil print bergaris atau tidak jelas pada printer inkjet dan laser.',
        'keywords' => 'hasil print bergaris, print head cleaning, nozzle check, toner cartridge, drum unit, fuser',
        'slug' => Str::slug('Cara Mengatasi Hasil Print Bergaris atau Tidak Jelas'),
        'category_id' => 2,
        'category_name' => 'Printer',
    ],
    [
        'id' => 10,
        'title' => 'Cara Mengatasi Kertas Macet (Paper Jam) di Printer',
        'content' => 'Kertas macet adalah masalah printer yang paling umum. Cara mengatasi: 1) Matikan printer dan cabut kabel power untuk keamanan, 2) Buka semua cover printer (depan, belakang, atas) sesuai manual printer, 3) Tarik kertas yang macet secara perlahan searah jalur kertas (jangan ditarik paksa berlawanan arah karena bisa merusak roller), 4) Cek apakah ada sobekan kertas yang tertinggal di dalam printer, 5) Periksa roller pickup - bersihkan dengan lap lembab jika licin atau berdebu, 6) Pastikan kertas di tray tidak terlalu penuh (maksimal 80% kapasitas tray), 7) Gunakan kertas dengan gramatur yang sesuai (70-80 gsm untuk printer biasa), 8) Setelah kertas keluar, nyalakan printer dan test print. Jika paper jam berulang, ganti roller pickup yang sudah aus.',
        'excerpt' => 'Panduan lengkap mengatasi kertas macet di printer dengan aman.',
        'keywords' => 'kertas macet, paper jam, roller pickup, tray printer, jalur kertas, sobekan kertas',
        'slug' => Str::slug('Cara Mengatasi Kertas Macet (Paper Jam) di Printer'),
        'category_id' => 2,
        'category_name' => 'Printer',
    ],
    [
        'id' => 11,
        'title' => 'Cara Install Driver Printer di Windows 10/11',
        'content' => 'Install driver printer yang benar penting untuk fungsi optimal. Langkah install: 1) Download driver printer dari website manufacturer (HP, Canon, Epson, Brother) sesuai model printer dan versi Windows (32-bit atau 64-bit), 2) Jalankan file installer sebagai administrator (klik kanan > Run as Administrator), 3) Ikuti wizard instalasi - pilih koneksi USB atau Network sesuai jenis printer, 4) Untuk printer USB, sambungkan kabel USB saat diminta installer, 5) Untuk printer network, masukkan IP address printer saat diminta, 6) Setelah install selesai, lakukan test print, 7) Set sebagai default printer jika diperlukan. Alternatif: Windows Update bisa download driver otomatis, tapi driver dari manufacturer biasanya lebih lengkap fiturnya.',
        'excerpt' => 'Panduan install driver printer di Windows 10/11 dengan benar.',
        'keywords' => 'install driver printer, driver HP, driver Canon, driver Epson, Windows 10, Windows 11, printer USB, printer network',
        'slug' => Str::slug('Cara Install Driver Printer di Windows 10/11'),
        'category_id' => 2,
        'category_name' => 'Printer',
    ],
    [
        'id' => 12,
        'title' => 'Cara Setting Printer Network (LAN/WiFi) di Kantor',
        'content' => "Setting printer network memungkinkan printer digunakan bersama di kantor. Langkah setting: 1) Pastikan printer dan komputer terhubung ke network/switch yang sama, 2) Set IP address static untuk printer (melalui menu printer > Network > TCP/IP) agar IP tidak berubah, contoh: 192.168.1.200, 3) Di komputer, buka Control Panel > Devices and Printers > Add a printer > 'The printer that I want isn\'t listed', 4) Pilih 'Add a printer using a TCP/IP address or hostname', 5) Masukkan IP address printer, 6) Windows akan detect printer dan install driver (atau pilih driver manual), 7) Beri nama printer yang deskriptif (contoh: 'Printer HR Lantai 2'), 8) Test print dari komputer. Untuk sharing printer via USB: enable 'Printer Sharing' di printer properties > Sharing tab.",
        'excerpt' => 'Cara setting printer network/LAN untuk digunakan bersama di kantor.',
        'keywords' => 'printer network, printer LAN, IP address static, TCP/IP, sharing printer, switch network',
        'slug' => Str::slug('Cara Setting Printer Network (LAN/WiFi) di Kantor'),
        'category_id' => 2,
        'category_name' => 'Printer',
    ],
];

$articles = new Collection(array_map(function ($item) {
    $object = new stdClass();
    foreach ($item as $key => $value) {
        $object->{$key} = $value;
    }
    $object->category = (object)['name' => $item['category_name']];
    return $object;
}, $articlesData));

$query = 'wifi lemot dan printer tidak mau print';
$intents = $detectMultiIntent->invoke($service, $query);

$sections = [];
$sections[] = '# RUNTIME CALCULATION RESULT';
$sections[] = '';
$sections[] = 'Query: `' . $query . '`';
$sections[] = '';
$sections[] = 'Detected intents: ' . implode(', ', array_map(fn($i) => '`' . $i . '`', $intents));
$sections[] = '';

$finalMerged = [];
$allFinalItems = [];

foreach ($intents as $intentIndex => $intent) {
    $normalized = $normalizeTypos->invoke($service, $intent);
    $normalized = $normalizeSynonyms->invoke($service, $normalized);

    $domainInfo = $detectDomainInfo($normalized);
    $allowedCategories = $getAllowedCategories->invoke($service, $domainInfo);
    $filteredArticles = $articles->filter(function ($article) use ($allowedCategories) {
        $cat = strtolower(trim($article->category->name));
        return in_array($cat, array_map('strtolower', $allowedCategories), true);
    });

    if ($filteredArticles->isEmpty()) {
        $filteredArticles = $articles;
        $usedFallback = true;
    } else {
        $usedFallback = false;
    }

    $expandedQuery = $expandQuery->invoke($service, $normalized, $domainInfo['domain'] ?? null);
    $documents = $prepareDocuments->invoke($service, $filteredArticles);
    $tfidfData = $buildTfidfVectors->invoke($service, $documents);
    $queryVector = $tfidfService->calculateQueryTFIDF($expandedQuery, $tfidfData['idf']);

    $ranked = $hybridRanking->invoke($service, $queryVector, $tfidfData['vectors'], $documents, $normalized, $domainInfo, $allowedCategories);
    $diversified = $diversifyResults->invoke($service, $ranked, $documents);
    $finalResults = $applyThresholdAndLimit->invoke($service, $diversified, 5);

    $sections[] = '## Intent `' . $intent . '`';
    $sections[] = '';
    $sections[] = '- Normalized intent: `' . $normalized . '`';
    $sections[] = '- Detected domain: `' . ($domainInfo['domain'] ?? 'none') . '`';
    $sections[] = '- Detected domain confidence: `' . ($domainInfo['confidence'] ?? 0) . '`';
    $sections[] = '- Allowed categories: `' . implode('`, `', $allowedCategories) . '`';
    $sections[] = '- Domain filter fallback applied: `' . ($usedFallback ? 'yes' : 'no') . '`';
    $sections[] = '- Expanded query: `' . $expandedQuery . '`';
    $sections[] = '';

    $queryTokens = $preprocessor->preprocess($expandedQuery, true);
    $queryFrequency = array_count_values($queryTokens);
    $queryTf = $tfidfService->calculateTF($queryFrequency);

    $sections[] = '### Query preprocessing';
    $sections[] = '';
    $sections[] = '- Query tokens: `' . implode('`, `', $queryTokens) . '`';
    $sections[] = '- Query term frequency (TF):';
    foreach ($queryTf as $term => $value) {
        $sections[] = '  - `' . $term . '`: ' . round($value, 6);
    }
    $sections[] = '- Query TF-IDF vector:';
    foreach ($queryVector as $term => $value) {
        $sections[] = '  - `' . $term . '`: ' . round($value, 12);
    }
    $sections[] = '- IDF values used for query:';
    foreach ($queryVector as $term => $_) {
        $sections[] = '  - `' . $term . '`: ' . round($tfidfData['idf'][$term] ?? 0, 12);
    }
    $sections[] = '';

    $sections[] = '### Document candidates (' . count($documents) . ')';
    $sections[] = '';

    foreach ($documents as $docId => $doc) {
        $docTfInfo = $preprocessor->preprocessDocument($doc['text']);
        $docTf = $tfidfService->calculateTF($docTfInfo['frequency']);
        $docTfidf = $tfidfService->calculateTFIDF($docTf, $tfidfData['idf']);
        $cosineSimilarity = $similarityService->calculate($queryVector, $docTfidf);
        $titleOverlapValue = $calculateTitleOverlap->invoke($service, $queryVector, $doc);
        $queryCoverageValue = $calculateQueryCoverage->invoke($service, $queryVector, $docTfidf);
        $exactPhraseBonusValue = $calculateExactPhraseBonus->invoke($service, $normalized, $doc);
        $domainMatchValue = $calculateDomainMatch->invoke($service, $doc, $domainInfo, $allowedCategories);
        $domainPenaltyValue = $calculateDomainPenalty->invoke($service, $doc, $domainInfo);
        $phraseBoostScore = 0.0;
        $phraseBoostDetails = [];

        $hasImportantPhrase = !empty($phraseService->detectPhrases($normalized));
        if ($hasImportantPhrase) {
            $phraseResult = $phraseService->getPhraseBoostScore($normalized, $doc);
            $phraseBoostScore = $phraseResult['total_boost'];
            $phraseBoostDetails = [
                'phrase_boost' => $phraseResult['phrase_boost'],
                'ngram_boost' => $phraseResult['ngram_boost'],
                'detected_phrases' => $phraseResult['detected_phrases'],
                'phrase_matches' => $phraseResult['phrase_matches'],
                'bigram_matches' => $phraseResult['bigram_matches'],
                'trigram_matches' => $phraseResult['trigram_matches'],
            ];
        }

        $weightedScore = (
            ($cosineSimilarity * 0.30) +
            ($titleOverlapValue * 0.25) +
            ($domainMatchValue * 0.15) +
            ($queryCoverageValue * 0.15) +
            ($exactPhraseBonusValue * 0.10) +
            (0.05 * 0.05)
        ) + $domainPenaltyValue + $phraseBoostScore;
        $weightedScore = max(0, min(1.0, $weightedScore));

        $sections[] = '#### Article: `' . $doc['title'] . '`';
        $sections[] = '';
        $sections[] = '- Category: `' . $doc['category_name'] . '`';
        $sections[] = '- Document text length: `' . strlen($doc['text']) . '` characters';
        $sections[] = '- Document frequency terms:';
        foreach ($docTfInfo['frequency'] as $term => $count) {
            $sections[] = '  - `' . $term . '`: ' . $count;
        }
        $sections[] = '- Document TF values:';
        foreach ($docTf as $term => $value) {
            $sections[] = '  - `' . $term . '`: ' . round($value, 12);
        }
        $sections[] = '- Document TF-IDF vector (selected terms):';
        foreach ($docTfidf as $term => $value) {
            if (isset($queryVector[$term]) || in_array($term, ['wifi','printer','lemot','mau','print','cetak','tidak','connect','tidak','terhubung','tidak','mau','print'], true)) {
                $sections[] = '  - `' . $term . '`: ' . round($value, 12);
            }
        }
        $sections[] = '- Cosine similarity: `' . round($cosineSimilarity, 12) . '`';
        $sections[] = '- Title overlap: `' . round($titleOverlapValue, 12) . '`';
        $sections[] = '- Query coverage: `' . round($queryCoverageValue, 12) . '`';
        $sections[] = '- Exact phrase bonus: `' . round($exactPhraseBonusValue, 12) . '`';
        $sections[] = '- Domain match: `' . round($domainMatchValue, 12) . '`';
        $sections[] = '- Domain penalty: `' . round($domainPenaltyValue, 12) . '`';
        $sections[] = '- Phrase boost total: `' . round($phraseBoostScore, 12) . '`';
        if (!empty($phraseBoostDetails)) {
            $formatPhraseList = fn(array $values) => implode('`, `', array_map(fn($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value, $values));

            $sections[] = '  - phrase_boost: `' . round($phraseBoostDetails['phrase_boost'], 12) . '`';
            $sections[] = '  - ngram_boost: `' . round($phraseBoostDetails['ngram_boost'], 12) . '`';
            $sections[] = '  - detected_phrases: `' . $formatPhraseList($phraseBoostDetails['detected_phrases']) . '`';
            $sections[] = '  - bigram_matches: `' . $formatPhraseList($phraseBoostDetails['bigram_matches']) . '`';
            $sections[] = '  - trigram_matches: `' . $formatPhraseList($phraseBoostDetails['trigram_matches']) . '`';
        }
        $sections[] = '- Calculated final score before diversification: `' . round($weightedScore, 12) . '`';
        $sections[] = '';
    }

    $sections[] = '### Final ranked results for intent `' . $intent . '`';
    foreach ($finalResults as $item) {
        $sections[] = '- `' . $item['title'] . '` (final_score: ' . round($item['final_score'], 12) . ', category: `' . ($item['document']['category_name'] ?? 'unknown') . '`)' ;
    }
    $sections[] = '';

    foreach ($finalResults as $item) {
        $allFinalItems[$item['id'] ?? $item['doc_id'] ?? $item['title']] = $item;
    }
}

$sections[] = '## Merged multi-intent final top results';
$sections[] = '';
foreach ($allFinalItems as $item) {
    $sections[] = '- `' . $item['title'] . '` (final_score: ' . round($item['final_score'], 12) . ', category: `' . ($item['document']['category_name'] ?? 'unknown') . '`, intent: `' . ($item['_intent_query'] ?? 'n/a') . '`)';
}

file_put_contents(__DIR__ . '/../RUNTIME_CALCULATION_RESULT.md', implode("\n", $sections));
echo "Wrote RUNTIME_CALCULATION_RESULT.md\n";
}

