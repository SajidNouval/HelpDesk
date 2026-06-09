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
     * =========================================================================
     * 1. METODE GET GREETING DATA
     * =========================================================================
     *
     * Fungsi:
     * Menyiapkan data untuk pesan greeting awal chatbot.
     *
     * Alur Proses:
     * 1. Ambil 5 kategori acak dari database.
     * 2. Untuk setiap kategori, ambil 3 artikel paling banyak dilihat.
     * 3. Gabungkan dan kembalikan sebagai data greeting terstruktur.
     *
     * Query yang Digunakan:
     * - Category::inRandomOrder()->limit(5)->get(['id', 'name', 'description']): Ambil 5 kategori acak
     * - Article::where('category_id', $category->id)->where('is_published', true)->orderBy('views', 'desc')->limit(3)->get(['id', 'title', 'slug']): Ambil 3 artikel terpopuler per kategori
     *
     * Output:
     * - array ['greeting' => string, 'categories' => array]
     */
    public function getGreetingData(): array
    {
        // Ambil 5 kategori acak dari database
        $categories = Category::inRandomOrder()
            ->limit(5)
            ->get(['id', 'name', 'description']);

        // Ambil artikel paling populer untuk setiap kategori yang dipilih
        $categoryArticles = [];
        foreach ($categories as $category) {
            // Ambil 3 artikel terpopuler berdasarkan views
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
     * =========================================================================
     * 1. METODE GET CATEGORY SUBTOPICS
     * =========================================================================
     *
     * Fungsi:
     * Mengambil subtopik relevan dari sebuah kategori berdasarkan judul artikel populer.
     *
     * Alur Proses:
     * 1. Validasi keberadaan kategori di database.
     * 2. Ambil 8 artikel terpopuler dari kategori tersebut.
     * 3. Ekstraksi frasa kunci dari setiap judul artikel.
     * 4. Deduplikasi dan batasi hingga 6 subtopik.
     * 5. Mengembalikan hasil subtopik.
     *
     * Query yang Digunakan:
     * - Category::find($categoryId): Cari kategori berdasarkan ID
     * - Article::where('category_id', $categoryId)->where('is_published', true)->orderBy('views', 'desc')->limit(8)->get(['id', 'title', 'slug']): Ambil 8 artikel terpopuler
     *
     * Output:
     * - array ['category' => nama, 'question' => pertanyaan, 'subtopics' => array] atau ['error' => pesan]
     */
    public function getCategorySubtopics(string $categoryId): array
    {
        // Cari kategori berdasarkan ID
        $category = Category::find($categoryId);
        if (!$category) {
            return ['error' => 'Kategori tidak ditemukan'];
        }

        // Ambil 8 artikel terpopuler dari kategori yang dipilih
        $articles = Article::where('category_id', $categoryId)
            ->where('is_published', true)
            ->orderBy('views', 'desc')
            ->limit(8)
            ->get(['id', 'title', 'slug']);

        // Ekstraksi frasa kunci dari judul artikel menggunakan pola regex
        $subtopics = [];
        foreach ($articles as $article) {
            $title = $article->title;

            // Pola regex untuk memotong judul sebelum kata penghubung
            $patterns = ['/^(.+?)\s+dengan/i', '/^(.+?)\s+saat/i', '/^(.+?)\s+ketika/i', '/^(.+?)\s+pada/i'];
            $extracted = $title;

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $title, $matches)) {
                    $extracted = $matches[1];
                    break;
                }
            }

            // Hanya tambahkan subtopik yang bermakna dan belum ada di daftar
            if (strlen($extracted) > 3 && !in_array($extracted, array_column($subtopics, 'label'))) {
                $subtopics[] = [
                    'id'         => $article->id,
                    'label'      => $extracted,
                    'full_title' => $title,
                    'slug'       => $article->slug,
                ];
            }
        }

        // Batasi tampilan subtopik hingga 6 item
        $subtopics = array_slice($subtopics, 0, 6);

        return [
            'category' => $category->name,
            'question' => "{$category->name} kamu sedang bermasalah apa? 😊",
            'subtopics' => $subtopics,
        ];
    }

    /**
     * =========================================================================
     * 1. METODE IS CONTEXTUAL QUERY
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah query mengandung kombinasi domain term dan issue term.
     *
     * Alur Proses:
     * 1. Menerima query pengguna dalam huruf kecil.
     * 2. Periksa apakah query mengandung domain term.
     * 3. Periksa apakah query mengandung issue term.
     * 4. Mengembalikan true jika keduanya ada.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - bool true jika query mengandung domain + issue (kontekstual)
     */
    private function isContextualQuery(string $query): bool
    {
        $hasDomain = false;
        $hasIssue  = false;

        // Periksa apakah query mengandung salah satu domain term
        foreach ($this->domainTerms as $term) {
            if (strpos($query, $term) !== false) {
                $hasDomain = true;
                break;
            }
        }

        // Periksa apakah query mengandung salah satu issue term
        foreach ($this->issueTerms as $term) {
            if (strpos($query, $term) !== false) {
                $hasIssue = true;
                break;
            }
        }

        // Dianggap kontekstual hanya jika KEDUANYA ada (domain + issue)
        return $hasDomain && $hasIssue;
    }

    /**
     * =========================================================================
     * 1. METODE CHECK AMBIGUITY
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah query pengguna bersifat ambigu dan memerlukan klarifikasi.
     *
     * Alur Proses:
     * 1. Normalisasi query ke huruf kecil.
     * 2. Lewati pengecekan jika query sudah kontekstual (domain + issue).
     * 3. Cek apakah query mengandung pola ambigu yang berdiri sendiri.
     * 4. Cek apakah query terlalu pendek dan terlalu generik.
     * 5. Mengembalikan hasil pengecekan ambiguitas.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array ['is_ambiguous' => bool] atau ['is_ambiguous' => true, 'query' => string, 'clarification' => array]
     */
    public function checkAmbiguity(string $query): array
    {
        $query = strtolower(trim($query));

        // Query yang mengandung kombinasi domain + issue tidak perlu klarifikasi
        if ($this->isContextualQuery($query)) {
            return ['is_ambiguous' => false];
        }

        // Periksa apakah query mengandung pola ambigu yang berdiri sendiri
        foreach ($this->ambiguousPatterns as $pattern) {
            if (strpos($query, $pattern) !== false) {
                $patternPos    = strpos($query, $pattern);
                $beforePattern = trim(substr($query, 0, $patternPos));
                $afterPattern  = trim(substr($query, $patternPos + strlen($pattern)));

                // Hitung kata signifikan di luar pola ambigu
                $extraWords = 0;
                if (strlen($beforePattern) > 2) $extraWords++;
                if (strlen($afterPattern) > 2) $extraWords++;

                // Ada kata signifikan lain → query kontekstual, tidak ambigu
                if ($extraWords > 0) {
                    return ['is_ambiguous' => false];
                }

                // Pola ambigu berdiri sendiri → perlu klarifikasi
                return [
                    'is_ambiguous'  => true,
                    'query'         => $query,
                    'clarification' => $this->getClarificationForQuery($query),
                ];
            }
        }

        // Query sangat pendek → terlalu generik
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
     * =========================================================================
     * 1. METODE GET CLARIFICATION FOR QUERY
     * =========================================================================
     *
     * Fungsi:
     * Menyusun pertanyaan klarifikasi yang tepat berdasarkan kata kunci ambigu.
     *
     * Alur Proses:
     * 1. Menerima query ambigu yang perlu diklarifikasi.
     * 2. Peta dari kata ambigu ke pertanyaan klarifikasi yang sesuai.
     * 3. Cari pertanyaan yang paling sesuai berdasarkan kata kunci.
     * 4. Mengembalikan pertanyaan dan saran kategori.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array ['question' => string, 'suggestions' => array]
     */
    private function getClarificationForQuery(string $query): array
    {
        // Peta dari kata ambigu ke pertanyaan klarifikasi yang sesuai
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

        // Cari pertanyaan yang paling sesuai berdasarkan kata kunci
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
     * =========================================================================
     * 1. METODE GET CATEGORY SUGGESTIONS
     * =========================================================================
     *
     * Fungsi:
     * Mengambil 4 kategori acak dari database untuk ditampilkan sebagai pilihan saran.
     *
     * Alur Proses:
     * 1. Ambil 4 kategori acak dari database.
     * 2. Deduplikasi berdasarkan nama (case-insensitive).
     * 3. Format sebagai array saran.
     * 4. Mengembalikan array saran kategori.
     *
     * Query yang Digunakan:
     * - Category::inRandomOrder()->limit(4)->get(['id', 'name']): Ambil 4 kategori acak
     *
     * Output:
     * - array saran kategori [['id', 'label', 'type'], ...]
     */
    private function getCategorySuggestions(): array
    {
        // Ambil 4 kategori acak dari database
        $categories = Category::inRandomOrder()
            ->limit(4)
            ->get(['id', 'name']);

        // Deduplikasi berdasarkan nama agar tidak ada kategori ganda
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
     * =========================================================================
     * 1. METODE STORE CONTEXT
     * =========================================================================
     *
     * Fungsi:
     * Menyimpan konteks percakapan ke dalam session Laravel.
     *
     * Alur Proses:
     * 1. Menerima tipe konteks dan data konteks.
     * 2. Ambil riwayat percakapan yang sudah tersimpan di session.
     * 3. Tambahkan konteks baru beserta timestamp.
     * 4. Potong array agar hanya 5 interaksi terakhir yang disimpan.
     * 5. Simpan kembali ke session.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - void
     */
    public function storeContext(string $context, mixed $data): void
    {
        // Ambil riwayat percakapan yang sudah ada di session
        $conversationHistory = Session::get('chatbot_conversation', []);

        // Tambahkan entri konteks baru dengan timestamp
        $conversationHistory[] = [
            'context'   => $context,
            'data'      => $data,
            'timestamp' => now()->timestamp,
        ];

        // Batasi hanya 5 interaksi terakhir untuk mencegah session membengkak
        $conversationHistory = array_slice($conversationHistory, -5);

        Session::put('chatbot_conversation', $conversationHistory);
    }

    /**
     * =========================================================================
     * 1. METODE GET CURRENT CONTEXT
     * =========================================================================
     *
     * Fungsi:
     * Mengambil konteks percakapan paling terakhir dari session.
     *
     * Alur Proses:
     * 1. Ambil seluruh riwayat percakapan dari session.
     * 2. Kembalikan entri paling terakhir sebagai konteks aktif.
     * 3. Mengembalikan null jika tidak ada riwayat.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - array|null konteks terakhir atau null jika tidak ada riwayat
     */
    public function getCurrentContext(): ?array
    {
        // Ambil seluruh riwayat percakapan dari session
        $conversationHistory = Session::get('chatbot_conversation', []);

        if (empty($conversationHistory)) {
            return null;
        }

        // Kembalikan entri paling terakhir sebagai konteks aktif
        return end($conversationHistory);
    }

    /**
     * =========================================================================
     * 1. METODE CLEAR CONTEXT
     * =========================================================================
     *
     * Fungsi:
     * Menghapus seluruh riwayat percakapan dari session.
     *
     * Alur Proses:
     * 1. Menghapus session chatbot_conversation.
     * 2. Mengembalikan void.
     *
     * Query yang Digunakan:
     * - Tidak ada query SQL langsung
     *
     * Output:
     * - void
     */
    public function clearContext(): void
    {
        Session::forget('chatbot_conversation');
    }

    /**
     * =========================================================================
     * 1. METODE GET SEARCH SUGGESTIONS
     * =========================================================================
     *
     * Fungsi:
     * Menyediakan saran pencarian berdasarkan partial query pengguna.
     *
     * Alur Proses:
     * 1. Validasi panjang query minimum (2 karakter).
     * 2. Cari artikel yang judulnya mengandung substring query.
     * 3. Format hasil sebagai array saran.
     * 4. Mengembalikan array saran.
     *
     * Query yang Digunakan:
     * - Article::where('is_published', true)->where('title', 'LIKE', "%{$query}%")->orderBy('views', 'desc')->limit($limit)->get(['id', 'title', 'slug']): Cari artikel berdasarkan judul
     *
     * Output:
     * - array saran [['id', 'label', 'slug', 'type'], ...] atau array kosong
     */
    public function getSearchSuggestions(string $query, int $limit = 5): array
    {
        $query = trim(strtolower($query));

        // Tidak tampilkan saran jika query terlalu pendek
        if (strlen($query) < 2) {
            return [];
        }

        // Cari artikel yang judulnya mengandung kata kunci pengguna
        $articles = Article::where('is_published', true)
            ->where('title', 'LIKE', "%{$query}%")
            ->orderBy('views', 'desc')
            ->limit($limit)
            ->get(['id', 'title', 'slug']);

        // Format hasil sebagai array saran autocomplete
        return $articles->map(fn($article) => [
            'id'    => $article->id,
            'label' => $article->title,
            'slug'  => $article->slug,
            'type'  => 'article',
        ])->toArray();
    }

    /**
     * =========================================================================
     * 1. METODE REFINE QUERY
     * =========================================================================
     *
     * Fungsi:
     * Memperkaya query pengguna dengan informasi konteks dari percakapan sebelumnya.
     *
     * Alur Proses:
     * 1. Menerima query baru dan konteks percakapan sebelumnya.
     * 2. Jika konteks adalah kategori, tambahkan nama kategori di depan query.
     * 3. Jika konteks adalah subtopik, tambahkan nama subtopik di depan query.
     * 4. Mengembalikan query yang sudah diperkaya.
     *
     * Query yang Digunakan:
     * - Category::find($context['data']['category_id']): Cari kategori berdasarkan ID dari konteks
     *
     * Output:
     * - string query yang sudah diperkaya dengan konteks
     */
    public function refineQuery(string $query, array $context): string
    {
        // Jika konteks sebelumnya adalah pemilihan kategori, tambahkan nama kategori
        if (isset($context['data']['category_id'])) {
            // Cari nama kategori berdasarkan ID dari konteks percakapan
            $category = Category::find($context['data']['category_id']);
            if ($category) {
                return "{$category->name} {$query}";
            }
        }

        // Jika konteks sebelumnya adalah subtopik, tambahkan nama subtopik
        if (isset($context['data']['subtopic'])) {
            return "{$context['data']['subtopic']} {$query}";
        }

        // Tidak ada konteks relevan — kembalikan query asli tanpa perubahan
        return $query;
    }

    /**
     * =========================================================================
     * 1. METODE GET RELATED ARTICLES
     * =========================================================================
     *
     * Fungsi:
     * Mengambil artikel-artikel terkait dari kategori yang sama dengan artikel yang sedang dilihat.
     *
     * Alur Proses:
     * 1. Temukan artikel sumber berdasarkan ID.
     * 2. Ambil artikel lain dari kategori yang sama dengan views tertinggi.
     * 3. Format sebagai array rekomendasi.
     * 4. Mengembalikan array artikel terkait.
     *
     * Query yang Digunakan:
     * - Article::find($articleId): Cari artikel sumber berdasarkan ID
     * - Article::where('category_id', $article->category_id)->where('id', '!=', $articleId)->where('is_published', true)->orderBy('views', 'desc')->limit($limit)->get(['id', 'title', 'slug', 'excerpt']): Ambil artikel terkait
     *
     * Output:
     * - array artikel terkait [['id', 'title', 'slug', 'excerpt', 'category_name'], ...] atau array kosong
     */
    public function getRelatedArticles(int $articleId, int $limit = 3): array
    {
        // Cari artikel sumber berdasarkan ID
        $article = Article::find($articleId);
        if (!$article) {
            return [];
        }

        // Ambil artikel terkait dari kategori yang sama dengan views terbanyak
        $related = Article::where('category_id', $article->category_id)
            ->where('id', '!=', $articleId)
            ->where('is_published', true)
            ->orderBy('views', 'desc')
            ->limit($limit)
            ->get(['id', 'title', 'slug', 'excerpt']);

        // Format hasil dengan nama kategori untuk konteks yang lebih jelas
        return $related->map(fn($art) => [
            'id'            => $art->id,
            'title'         => $art->title,
            'slug'          => $art->slug,
            'excerpt'       => $art->excerpt,
            'category_name' => $article->category->name,
        ])->toArray();
    }
}