<?php

namespace App\Services\Chatbot;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\Session;

/**
 * =========================================================================
 * SERVICE CONVERSATION FLOW
 * =========================================================================
 *
 * Layanan ini mengelola alur percakapan chatbot SiMinfo secara deterministik.
 * Semua respon didasarkan pada data yang tersimpan di database, bukan generatif.
 *
 * Tanggung jawab utama layanan ini:
 * - Menyajikan pesan greeting awal beserta kategori dan artikel populer.
 * - Mengarahkan pengguna ke subtopik berdasarkan kategori yang dipilih.
 * - Mendeteksi query yang ambigu dan meminta klarifikasi.
 * - Menyimpan dan mengambil konteks percakapan multi-turn di session.
 * - Menyediakan saran pencarian berdasarkan judul artikel.
 * - Mengambil artikel terkait untuk rekomendasi lanjutan.
 *
 * Konsep alur percakapan:
 * 1. Pengguna mendapat greeting → tampil kategori acak.
 * 2. Pengguna memilih kategori → tampil subtopik dari artikel populer.
 * 3. Pengguna mengetik query → diperiksa apakah ambigu atau kontekstual.
 * 4. Query ambigu (hanya "lemot", "error") → sistem meminta klarifikasi.
 * 5. Query kontekstual ("wifi lemot") → langsung ke proses retrieval.
 *
 * Digunakan oleh:
 * - ChatbotController
 */
class ConversationFlowService
{
    /**
     * Pola query yang dianggap ambigu ketika berdiri sendiri tanpa konteks domain.
     * Contoh: pengguna hanya mengetik "lemot" tanpa menyebutkan "wifi" atau "komputer".
     */
    private array $ambiguousPatterns = [
        'lemot',
        'lambat',
        'error',
        'eror',
        'tidak bisa',
        'gak bisa',
        'ga bisa',
        'bermasalah',
        'masalah',
        'rusak',
        'mati',
        'hilang',
        'blank',
        'kosong',
        'no signal',
        'tidak muncul',
        'gak muncul',
    ];

    /**
     * Term domain/konteks yang menunjukkan topik spesifik.
     * Jika kombinasi dengan term masalah (issueTerms), query dianggap kontekstual
     * dan proses klarifikasi dilewati.
     * Contoh: "wifi lemot" → domain "wifi" + issue "lemot" = kontekstual, tidak ambigu.
     */
    private array $domainTerms = [
        'wifi',
        'internet',
        'printer',
        'komputer',
        'laptop',
        'software',
        'aplikasi',
        'email',
        'jaringan',
        'router',
        'modem',
        'lan',
        'server',
        'dns',
        'ip',
        'usb',
        'bluetooth',
        'monitor',
        'keyboard',
        'mouse',
        'scanner',
        'webcam',
        'speaker',
        'microphone',
        'windows',
        'linux',
        'android',
        'ios',
        'office',
        'browser',
        'chrome',
        'firefox',
        'excel',
        'word',
        'powerpoint',
        'outlook',
        'drive',
        'folder',
        'file',
        'backup',
        'install',
        'uninstall',
        'update',
        'driver',
    ];

    /**
     * Term masalah/gangguan yang biasanya dikombinasikan dengan domain term.
     * Term ini saja tidak cukup untuk menentukan topik — perlu dikombinasikan
     * dengan domain term agar query dianggap kontekstual.
     */
    private array $issueTerms = [
        'lemot',
        'lambat',
        'error',
        'eror',
        'tidak bisa',
        'gak bisa',
        'ga bisa',
        'bermasalah',
        'masalah',
        'rusak',
        'mati',
        'hilang',
        'blank',
        'kosong',
        'no signal',
        'tidak muncul',
        'gak muncul',
        'crash',
        'hang',
        'freeze',
        'not responding',
        'blue screen',
        'overheat',
        'panas',
        'bunyi',
        'putus',
        'disconnect',
        'connect',
    ];

    /**
     * Pemetaan kategori ke saran klarifikasi yang ditampilkan saat query ambigu.
     * Saran ini membantu pengguna mempersempit masalah mereka.
     */
    private array $clarificationMap = [
        'wifi'     => ['WiFi lemot', 'Tidak bisa connect', 'No internet', 'Sering putus'],
        'internet' => ['Internet lemot', 'Tidak terhubung', 'DNS error', 'IP conflict'],
        'printer'  => ['Printer tidak terdeteksi', 'Macet print', 'Kertas nyangkut', 'Tinta habis'],
        'komputer' => ['Komputer lemot', 'Blue screen', 'Tidak bisa nyala', 'Overheat'],
        'software' => ['Aplikasi error', 'Tidak bisa install', 'Crash', 'Update gagal'],
    ];

    /**
     * 1. Fungsi getGreetingData()
     *
     * Fungsi ini menyiapkan data untuk pesan greeting awal chatbot.
     * Data yang dikembalikan mencakup:
     * - Teks pesan sapaan dari chatbot SiMinfo.
     * - 5 kategori acak dari database beserta artikel populer masing-masing.
     *
     * Data ini digunakan untuk menampilkan tombol kategori di antarmuka chatbot
     * sehingga pengguna dapat memilih topik masalah dengan mudah.
     *
     * Alur proses:
     * 1. Ambil 5 kategori acak dari database.
     * 2. Untuk setiap kategori, ambil 3 artikel paling banyak dilihat (views).
     * 3. Gabungkan dan kembalikan sebagai data greeting terstruktur.
     *
     * Kembalikan:
     * - array : ['greeting' => string, 'kategori' => array]
     */
    public function getGreetingData(): array
    {
        // 1.1 Query ini mengambil 5 kategori secara acak dari database
        // untuk ditampilkan sebagai pilihan topik di greeting chatbot
        $categories = Category::inRandomOrder()
            ->limit(5)
            ->get(['id', 'name', 'description']);

        // 1.2 Ambil artikel paling populer untuk setiap kategori yang dipilih
        $categoryArticles = [];
        foreach ($categories as $category) {
            // 1.3 Query ini mengambil 3 artikel terpopuler (berdasarkan views)
            // dari setiap kategori yang berstatus dipublikasikan
            $articles = Article::where('category_id', $category->id)
                ->where('is_published', true)
                ->orderBy('views', 'desc')
                ->limit(3)
                ->get(['id', 'title', 'slug']);

            $categoryArticles[$category->id] = $articles;
        }

        return [
            'greeting'   => "Halo! 👋\nSaya SiMinfo.\nAda masalah apa hari ini?",
            'categories' => $categories->map(fn($cat) => [
                'id'          => $cat->id,
                'label'       => $cat->name,
                'description' => $cat->description,
                'articles'    => $categoryArticles[$cat->id] ?? [],
            ]),
        ];
    }

    /**
     * 2. Fungsi getCategorySubtopics()
     *
     * Fungsi ini mengambil subtopik relevan dari sebuah kategori berdasarkan
     * judul-judul artikel paling populer dalam kategori tersebut.
     *
     * Subtopik diekstraksi dari judul artikel dengan memotong teks sebelum kata
     * penghubung seperti "dengan", "saat", "ketika", "pada" — mengambil bagian
     * masalah utamanya saja.
     *
     * Alur proses:
     * 1. Validasi keberadaan kategori di database.
     * 2. Ambil 8 artikel terpopuler dari kategori tersebut.
     * 3. Ekstraksi frasa kunci dari setiap judul artikel.
     * 4. Deduplikasi dan batasi hingga 6 subtopik.
     *
     * Parameter:
     * - string $categoryId : ID kategori yang dicari subtopiknya
     *
     * Kembalikan:
     * - array : ['kategori' => nama, 'question' => pertanyaan, 'subtopics' => array]
     *         atau ['error' => pesan] jika kategori tidak ditemukan
     */
    public function getCategorySubtopics(string $categoryId): array
    {
        // 2.1 Query ini mencari kategori berdasarkan ID yang diberikan
        $category = Category::find($categoryId);
        if (!$category) {
            return ['error' => 'Kategori tidak ditemukan'];
        }

        // 2.2 Query ini mengambil 8 artikel terpopuler dari kategori yang dipilih
        // untuk dijadikan sumber ekstraksi subtopik
        $articles = Article::where('category_id', $categoryId)
            ->where('is_published', true)
            ->orderBy('views', 'desc')
            ->limit(8)
            ->get(['id', 'title', 'slug']);

        // 2.3 Ekstraksi frasa kunci dari judul artikel menggunakan pola regex
        $subtopics = [];
        foreach ($articles as $article) {
            $title = $article->title;

            // Pola regex untuk memotong judul sebelum kata penghubung
            // Tujuan: mengambil inti masalah dari judul yang panjang
            $patterns = ['/^(.+?)\s+dengan/i', '/^(.+?)\s+saat/i', '/^(.+?)\s+ketika/i', '/^(.+?)\s+pada/i'];
            $extracted = $title;

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $title, $matches)) {
                    $extracted = $matches[1];
                    break;
                }
            }

            // 2.4 Hanya tambahkan subtopik yang bermakna (lebih dari 3 karakter)
            // dan belum ada di daftar sebelumnya (deduplikasi)
            if (strlen($extracted) > 3 && !in_array($extracted, array_column($subtopics, 'label'))) {
                $subtopics[] = [
                    'id'         => $article->id,
                    'label'      => $extracted,
                    'full_title' => $title,
                    'slug'       => $article->slug,
                ];
            }
        }

        // 2.5 Batasi tampilan subtopik hingga 6 item agar tidak terlalu panjang
        $subtopics = array_slice($subtopics, 0, 6);

        return [
            'category' => $category->name,
            'question' => "{$category->name} kamu sedang bermasalah apa? 😊",
            'subtopics' => $subtopics,
        ];
    }

    /**
     * 3. Fungsi isContextualQuery() [private]
     *
     * Fungsi pembantu internal yang memeriksa apakah sebuah query mengandung
     * kombinasi domain term DAN issue term sekaligus, sehingga dianggap kontekstual.
     *
     * Query kontekstual tidak perlu klarifikasi karena sudah cukup spesifik.
     * Contoh kontekstual: "wifi lemot" (domain: wifi + issue: lemot)
     * Contoh tidak kontekstual: "lemot" saja (hanya issue, tidak ada domain)
     *
     * Parameter:
     * - string $query : Query pengguna dalam huruf kecil
     *
     * Kembalikan:
     * - bool : true jika query mengandung domain + issue (kontekstual)
     */
    private function isContextualQuery(string $query): bool
    {
        $hasDomain = false;
        $hasIssue  = false;

        // 3.1 Periksa apakah query mengandung salah satu domain term
        foreach ($this->domainTerms as $term) {
            if (strpos($query, $term) !== false) {
                $hasDomain = true;
                break;
            }
        }

        // 3.2 Periksa apakah query mengandung salah satu issue term
        foreach ($this->issueTerms as $term) {
            if (strpos($query, $term) !== false) {
                $hasIssue = true;
                break;
            }
        }

        // 3.3 Dianggap kontekstual hanya jika KEDUANYA ada (domain + issue)
        return $hasDomain && $hasIssue;
    }

    /**
     * 4. Fungsi checkAmbiguity()
     *
     * Fungsi ini memeriksa apakah query pengguna bersifat ambigu dan memerlukan
     * pertanyaan klarifikasi sebelum dilakukan retrieval artikel.
     *
     * Logika deteksi ambiguitas:
     * 1. Query kontekstual (domain + issue) → TIDAK ambigu, langsung ke retrieval.
     * 2. Query mengandung pola ambigu TAPI ada kata signifikan lain → TIDAK ambigu.
     * 3. Query hanya berisi pola ambigu tanpa konteks → AMBIGU, perlu klarifikasi.
     * 4. Query sangat pendek (< 5 karakter, hanya huruf) → AMBIGU.
     *
     * Alur proses:
     * 1. Normalisasi query ke huruf kecil.
     * 2. Lewati pengecekan jika query sudah kontekstual (domain + issue).
     * 3. Cek apakah query mengandung pola ambigu yang berdiri sendiri.
     * 4. Cek apakah query terlalu pendek dan terlalu generik.
     *
     * Parameter:
     * - string $query : Query mentah dari pengguna
     *
     * Kembalikan:
     * - array : ['is_ambiguous' => bool] atau
     *           ['is_ambiguous' => true, 'query' => string, 'clarification' => array]
     */
    public function checkAmbiguity(string $query): array
    {
        $query = strtolower(trim($query));

        // 4.1 Query yang mengandung kombinasi domain + issue tidak perlu klarifikasi
        // Contoh: "wifi lemot" → langsung ke retrieval tanpa tanya balik
        if ($this->isContextualQuery($query)) {
            return ['is_ambiguous' => false];
        }

        // 4.2 Periksa apakah query mengandung pola ambigu yang berdiri sendiri
        foreach ($this->ambiguousPatterns as $pattern) {
            if (strpos($query, $pattern) !== false) {
                $patternPos    = strpos($query, $pattern);
                $beforePattern = trim(substr($query, 0, $patternPos));
                $afterPattern  = trim(substr($query, $patternPos + strlen($pattern)));

                // 4.3 Hitung kata signifikan (lebih dari 2 karakter) di luar pola ambigu
                // Jika ada kata signifikan lain, query tidak sepenuhnya ambigu
                $extraWords = 0;
                if (strlen($beforePattern) > 2) $extraWords++;
                if (strlen($afterPattern) > 2) $extraWords++;

                // 4.4 Ada kata signifikan lain → query kontekstual, tidak ambigu
                if ($extraWords > 0) {
                    return ['is_ambiguous' => false];
                }

                // 4.5 Pola ambigu berdiri sendiri → perlu klarifikasi
                return [
                    'is_ambiguous'  => true,
                    'query'         => $query,
                    'clarification' => $this->getClarificationForQuery($query),
                ];
            }
        }

        // 4.6 Query sangat pendek (< 5 karakter, hanya huruf) → terlalu generik
        if (strlen($query) < 5 && preg_match('/^[a-z]+$/', $query)) {
            return [
                'is_ambiguous'  => true,
                'query'         => $query,
                'clarification' => [
                    'question'    => 'Bisa lebih spesifik? 😊',
                    'suggestions' => $this->getCategorySuggestions(),
                ],
            ];
        }

        return ['is_ambiguous' => false];
    }

    /**
     * 5. Fungsi getClarificationForQuery() [private]
     *
     * Fungsi pembantu internal yang menyusun pertanyaan klarifikasi yang tepat
     * berdasarkan kata kunci ambigu dalam query pengguna.
     *
     * Pemetaan dilakukan dari kata kunci ambigu ke pertanyaan yang lebih personal.
     * Misalnya: "lemot" → "Yang sedang lemot apa ya? 😊"
     *
     * Parameter:
     * - string $query : Query ambigu yang perlu diklarifikasi
     *
     * Kembalikan:
     * - array : ['question' => string, 'suggestions' => array]
     */
    private function getClarificationForQuery(string $query): array
    {
        // 5.1 Peta dari kata ambigu ke pertanyaan klarifikasi yang sesuai
        $categoryMap = [
            'lemot'     => 'Yang sedang lemot apa ya? 😊',
            'lambat'    => 'Yang sedang lambat apa ya? 😊',
            'error'     => 'Error di bagian mana? 😊',
            'eror'      => 'Error di bagian mana? 😊',
            'tidak bisa' => 'Tidak bisa apa? 😊',
            'gak bisa'  => 'Gak bisa apa? 😊',
            'ga bisa'   => 'Gak bisa apa? 😊',
            'bermasalah' => 'Bermasalah di bagian mana? 😊',
            'masalah'   => 'Masalah di bagian mana? 😊',
            'rusak'     => 'Yang rusak apa? 😊',
            'mati'      => 'Yang mati apa? 😊',
            'blank'     => 'Yang blank apa? 😊',
            'kosong'    => 'Yang kosong apa? 😊',
        ];

        // 5.2 Cari pertanyaan yang paling sesuai berdasarkan kata kunci
        $question = 'Bisa lebih spesifik? 😊';
        foreach ($categoryMap as $keyword => $q) {
            if (strpos($query, $keyword) !== false) {
                $question = $q;
                break;
            }
        }

        return [
            'question'    => $question,
            'suggestions' => $this->getCategorySuggestions(),
        ];
    }

    /**
     * 6. Fungsi getCategorySuggestions() [private]
     *
     * Fungsi pembantu internal yang mengambil 4 kategori acak dari database
     * untuk ditampilkan sebagai pilihan saran ketika query ambigu.
     *
     * Deduplikasi dilakukan untuk menghindari kategori dengan nama sama
     * ditampilkan dua kali (meskipun memiliki ID berbeda).
     *
     * Alur proses:
     * 1. Ambil 4 kategori acak dari database.
     * 2. Deduplikasi berdasarkan nama (case-insensitive).
     * 3. Format sebagai array saran.
     *
     * Kembalikan:
     * - array : Array saran kategori [['id', 'label', 'type'], ...]
     */
    private function getCategorySuggestions(): array
    {
        // 6.1 Query ini mengambil 4 kategori secara acak untuk ditampilkan
        // sebagai pilihan saran klarifikasi kepada pengguna
        $categories = Category::inRandomOrder()
            ->limit(4)
            ->get(['id', 'name']);

        // 6.2 Deduplikasi berdasarkan nama agar tidak ada kategori ganda
        $seen   = [];
        $unique = [];
        foreach ($categories as $cat) {
            $normalizedName = strtolower(trim($cat->name));
            if (!isset($seen[$normalizedName])) {
                $seen[$normalizedName] = true;
                $unique[] = [
                    'id'    => $cat->id,
                    'label' => $cat->name,
                    'type'  => 'category',
                ];
            }
        }

        return $unique;
    }

    /**
     * 7. Fungsi storeContext()
     *
     * Fungsi ini menyimpan konteks percakapan ke dalam session Laravel.
     * Konteks digunakan untuk memahami pertanyaan lanjutan dari pengguna
     * dalam percakapan multi-turn.
     *
     * Hanya 5 interaksi terakhir yang disimpan untuk mencegah session membengkak
     * (session bloat) yang dapat memperlambat performa.
     *
     * Alur proses:
     * 1. Ambil riwayat percakapan yang sudah tersimpan di session.
     * 2. Tambahkan konteks baru beserta timestamp.
     * 3. Potong array agar hanya 5 interaksi terakhir yang disimpan.
     * 4. Simpan kembali ke session.
     *
     * Parameter:
     * - string $context : Tipe konteks percakapan (misalnya: 'kategori', 'article')
     * - mixed  $data    : Data konteks yang ingin disimpan
     *
     * Kembalikan:
     * - void
     */
    public function storeContext(string $context, mixed $data): void
    {
        // 7.1 Ambil riwayat percakapan yang sudah ada di session
        $conversationHistory = Session::get('chatbot_conversation', []);

        // 7.2 Tambahkan entri konteks baru dengan timestamp
        $conversationHistory[] = [
            'context'   => $context,
            'data'      => $data,
            'timestamp' => now()->timestamp,
        ];

        // 7.3 Batasi hanya 5 interaksi terakhir untuk mencegah session membengkak
        $conversationHistory = array_slice($conversationHistory, -5);

        Session::put('chatbot_conversation', $conversationHistory);
    }

    /**
     * 8. Fungsi getCurrentContext()
     *
     * Fungsi ini mengambil konteks percakapan paling terakhir dari session.
     * Konteks ini digunakan untuk memahami topik percakapan sebelumnya
     * saat pengguna mengirim pertanyaan lanjutan.
     *
     * Kembalikan:
     * - array|null : Konteks terakhir atau null jika tidak ada riwayat
     */
    public function getCurrentContext(): ?array
    {
        // 8.1 Ambil seluruh riwayat percakapan dari session
        $conversationHistory = Session::get('chatbot_conversation', []);

        if (empty($conversationHistory)) {
            return null;
        }

        // 8.2 Kembalikan entri paling terakhir sebagai konteks aktif
        return end($conversationHistory);
    }

    /**
     * 9. Fungsi clearContext()
     *
     * Fungsi ini menghapus seluruh riwayat percakapan dari session.
     * Dipanggil ketika sesi percakapan baru dimulai atau pengguna
     * meminta reset percakapan.
     *
     * Kembalikan:
     * - void
     */
    public function clearContext(): void
    {
        Session::forget('chatbot_conversation');
    }

    /**
     * 10. Fungsi getSearchSuggestions()
     *
     * Fungsi ini menyediakan saran pencarian berdasarkan partial query pengguna.
     * Saran diambil dari judul artikel yang sudah dipublikasi dan diurutkan
     * berdasarkan popularitas (views).
     *
     * Fungsi ini digunakan untuk fitur autocomplete/typeahead di antarmuka chatbot.
     * Query minimum adalah 2 karakter untuk menghindari terlalu banyak hasil.
     *
     * Alur proses:
     * 1. Validasi panjang query minimum (2 karakter).
     * 2. Cari artikel yang judulnya mengandung substring query.
     * 3. Format hasil sebagai array saran.
     *
     * Parameter:
     * - string $query : Partial query dari pengguna (minimum 2 karakter)
     * - int    $batas : Jumlah saran maksimal yang dikembalikan (default: 5)
     *
     * Kembalikan:
     * - array : Array saran [['id', 'label', 'slug', 'type'], ...] atau array kosong
     */
    public function getSearchSuggestions(string $query, int $limit = 5): array
    {
        $query = trim(strtolower($query));

        // 10.1 Tidak tampilkan saran jika query terlalu pendek (kurang dari 2 karakter)
        if (strlen($query) < 2) {
            return [];
        }

        // 10.2 Query ini mencari artikel yang judulnya mengandung kata kunci pengguna
        // dan mengurutkannya berdasarkan jumlah tampilan (views) terbanyak
        $articles = Article::where('is_published', true)
            ->where('title', 'LIKE', "%{$query}%")
            ->orderBy('views', 'desc')
            ->limit($limit)
            ->get(['id', 'title', 'slug']);

        // 10.3 Format hasil sebagai array saran autocomplete
        return $articles->map(fn($article) => [
            'id'    => $article->id,
            'label' => $article->title,
            'slug'  => $article->slug,
            'type'  => 'article',
        ])->toArray();
    }

    /**
     * 11. Fungsi refineQuery()
     *
     * Fungsi ini memperkaya query pengguna dengan informasi konteks dari percakapan
     * sebelumnya. Jika konteks menyebut kategori atau subtopik tertentu,
     * nama tersebut ditambahkan di depan query baru.
     *
     * Contoh:
     * - Konteks sebelumnya: pengguna memilih kategori "WiFi"
     * - Query baru: "lemot"
     * - Query yang diperhalus: "WiFi lemot"
     *
     * Parameter:
     * - string $query   : Query baru dari pengguna
     * - array  $context : Konteks percakapan sebelumnya dari getCurrentContext()
     *
     * Kembalikan:
     * - string : Query yang sudah diperkaya dengan konteks
     */
    public function refineQuery(string $query, array $context): string
    {
        // 11.1 Jika konteks sebelumnya adalah pemilihan kategori, tambahkan nama kategori
        if (isset($context['data']['category_id'])) {
            // Query ini mencari nama kategori berdasarkan ID dari konteks percakapan
            $category = Category::find($context['data']['category_id']);
            if ($category) {
                return "{$category->name} {$query}";
            }
        }

        // 11.2 Jika konteks sebelumnya adalah subtopik, tambahkan nama subtopik
        if (isset($context['data']['subtopic'])) {
            return "{$context['data']['subtopic']} {$query}";
        }

        // 11.3 Tidak ada konteks relevan — kembalikan query asli tanpa perubahan
        return $query;
    }

    /**
     * 12. Fungsi getRelatedArticles()
     *
     * Fungsi ini mengambil artikel-artikel terkait dari kategori yang sama
     * dengan artikel yang sedang dilihat, untuk ditampilkan sebagai rekomendasi.
     *
     * Artikel yang ditampilkan adalah artikel dengan views terbanyak
     * dalam kategori yang sama, selain artikel saat ini.
     *
     * Alur proses:
     * 1. Temukan artikel sumber berdasarkan ID.
     * 2. Ambil artikel lain dari kategori yang sama dengan views tertinggi.
     * 3. Format sebagai array rekomendasi.
     *
     * Parameter:
     * - int $articleId : ID artikel sumber
     * - int $batas     : Jumlah artikel terkait yang dikembalikan (default: 3)
     *
     * Kembalikan:
     * - array : Array artikel terkait [['id', 'judul', 'slug', 'excerpt', 'category_name'], ...]
     *         atau array kosong jika artikel tidak ditemukan
     */
    public function getRelatedArticles(int $articleId, int $limit = 3): array
    {
        // 12.1 Query ini mencari artikel sumber berdasarkan ID
        $article = Article::find($articleId);
        if (!$article) {
            return [];
        }

        // 12.2 Query ini mengambil artikel terkait dari kategori yang sama
        // dengan jumlah views terbanyak, kecuali artikel yang sedang dilihat
        $related = Article::where('category_id', $article->category_id)
            ->where('id', '!=', $articleId)
            ->where('is_published', true)
            ->orderBy('views', 'desc')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'excerpt']);

        // 12.3 Format hasil dengan nama kategori untuk konteks yang lebih jelas
        return $related->map(fn($art) => [
            'id'            => $art->id,
            'title'         => $art->title,
            'slug'          => $art->slug,
            'excerpt'       => $art->excerpt,
            'category_name' => $article->category->name,
        ])->toArray();
    }
}