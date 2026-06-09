<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * =========================================================================
 * ARTICLE CONTROLLER - PENGELOLAAN ARTIKEL
 * =========================================================================
 *
 * Controller ini bertanggung jawab untuk mengelola artikel dalam sistem helpdesk.
 * Staff dapat membuat, mengedit, menghapus, dan mempublikasikan artikel.
 * Pengguna dapat melihat artikel yang sudah dipublikasikan.
 *
 * Fitur Utama:
 * - CRUD artikel untuk staff (create, read, update, delete)
 * - Pencarian dan sorting artikel
 * - Reset views dan feedback
 * - Approval workflow untuk artikel
 * - Tampilan publik artikel
 *
 * Model Terkait:
 * - Article: Model artikel
 * - Category: Model kategori
 * - Setting: Model pengaturan sistem
 */
class ArticleController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE INDEX - DAFTAR ARTIKEL STAFF
     * =========================================================================
     *
     * Fungsi:
     * Menampilkan daftar artikel milik staff yang sedang login.
     *
     * Alur Proses:
     * 1. Mengambil parameter pencarian, sorting, dan status.
     * 2. Membangun query artikel dengan relasi kategori dan staff.
     * 3. Menghitung feedback helpful dan not helpful.
     * 4. Menerapkan filter pencarian jika ada.
     * 5. Menerapkan filter status jika ada.
     * 6. Menerapkan sorting berdasarkan parameter.
     * 7. Menghitung statistik artikel (total, pending, approved, rejected).
     * 8. Mengembalikan view dengan data artikel.
     *
     * Query yang Digunakan:
     * - Article::with('category', 'staff'): Load relasi artikel
     * - withCount('feedback as helpful_count'): Hitung feedback helpful
     * - where('staff_id', auth()->id()): Filter artikel staff login
     * - where('publish_status', $status): Filter status
     * - orderBy(): Sorting berdasarkan parameter
     * - paginate(10): Pagination
     *
     * Output:
     * - View 'staff.articles.index' dengan data artikel dan statistik.
     */
    public function index()
    {
        $search = request('q');
        $sort = request('sort', 'created_desc');
        $status = request('status');

        $articlesQuery = Article::with('category', 'staff')
            ->where('staff_id', auth()->id())
            ->withCount([
                'feedback as helpful_count' => function ($query) {
                    $query->where('is_helpful', true);
                },
                'feedback as not_helpful_count' => function ($query) {
                    $query->where('is_helpful', false);
                },
            ])
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($query) use ($search) {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('publish_status', $status);
            });

        switch ($sort) {
            case 'created_asc':
                $articlesQuery->orderBy('created_at', 'asc');
                break;
            case 'created_desc':
                $articlesQuery->orderBy('created_at', 'desc');
                break;
            case 'title_asc':
                $articlesQuery->orderBy('title', 'asc');
                break;
            case 'title_desc':
                $articlesQuery->orderBy('title', 'desc');
                break;
            case 'views_desc':
                $articlesQuery->orderBy('views', 'desc');
                break;
            case 'views_asc':
                $articlesQuery->orderBy('views', 'asc');
                break;
            default:
                $articlesQuery->orderBy('created_at', 'desc');
        }

        $articles = $articlesQuery->paginate(10)->withQueryString();

        $totalArticles = Article::where('staff_id', auth()->id())->count();
        $pendingArticles = Article::where('staff_id', auth()->id())->where('publish_status', 'pending')->count();
        $approvedArticles = Article::where('staff_id', auth()->id())->where('publish_status', 'approved')->count();
        $rejectedArticles = Article::where('staff_id', auth()->id())->where('publish_status', 'rejected')->count();

        return view('staff.articles.index', compact(
            'articles',
            'search',
            'sort',
            'status',
            'totalArticles',
            'pendingArticles',
            'approvedArticles',
            'rejectedArticles'
        ));
    }

    /**
     * =========================================================================
     * 2. METODE CREATE - FORM TAMBAH ARTIKEL
     * =========================================================================
     *
     * Fungsi:
     * Menampilkan form untuk membuat artikel baru.
     *
     * Alur Proses:
     * 1. Mengambil semua kategori.
     * 2. Mengembalikan view form create.
     *
     * Query yang Digunakan:
     * - Category::all(): Ambil semua kategori
     *
     * Output:
     * - View 'staff.articles.create' dengan data kategori.
     */
    public function create()
    {
        $categories = Category::all();
        return view('staff.articles.create', compact('categories'));
    }

    /**
     * =========================================================================
     * 3. METODE STORE - SIMPAN ARTIKEL BARU
     * =========================================================================
     *
     * Fungsi:
     * Menyimpan artikel baru ke database.
     *
     * Alur Proses:
     * 1. Melakukan validasi input.
     * 2. Generate slug unik dari judul.
     * 3. Generate excerpt dari input atau konten.
     * 4. Menyimpan artikel dengan status pending.
     * 5. Redirect ke halaman daftar artikel.
     *
     * Query yang Digunakan:
     * - Article::create(): Insert artikel baru
     *
     * Output:
     * - Redirect ke route('staff.articles.index') dengan pesan sukses.
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'keywords' => 'nullable|string|max:255',
        ]);

        $slug = $this->generateUniqueSlug($request->title);

        Article::create([
            'category_id' => $request->category_id,
            'staff_id' => auth()->id(),
            'title' => $request->title,
            'slug' => $slug,
            'content' => $request->content,
            'excerpt' => $this->generateExcerpt($request->excerpt, $request->content),
            'keywords' => $request->keywords,
            'is_published' => false,
            'publish_status' => 'pending',
        ]);

        return redirect()->route('staff.articles.index')->with('success', 'Artikel berhasil dibuat dan menunggu persetujuan admin.');
    }

    /**
     * =========================================================================
     * 4. METODE SHOW - DETAIL ARTIKEL
     * =========================================================================
     *
     * Fungsi:
     * Menampilkan detail artikel.
     *
     * Alur Proses:
     * 1. Load count feedback helpful dan not helpful.
     * 2. Mengembalikan view detail artikel.
     *
     * Query yang Digunakan:
     * - withCount('feedback as helpful_count'): Hitung feedback helpful
     * - withCount('feedback as not_helpful_count'): Hitung feedback not helpful
     *
     * Output:
     * - View 'staff.articles.show' dengan data artikel.
     */
    public function show(Article $article)
    {
        $article->loadCount([
            'feedback as helpful_count' => function ($query) {
                $query->where('is_helpful', true);
            },
            'feedback as not_helpful_count' => function ($query) {
                $query->where('is_helpful', false);
            },
        ]);
        return view('staff.articles.show', compact('article'));
    }

    /**
     * =========================================================================
     * 5. METODE EDIT - FORM EDIT ARTIKEL
     * =========================================================================
     *
     * Fungsi:
     * Menampilkan form edit artikel.
     *
     * Alur Proses:
     * 1. Cek otorisasi pemilik artikel.
     * 2. Mengambil semua kategori.
     * 3. Mengembalikan view edit artikel.
     *
     * Query yang Digunakan:
     * - Category::all(): Ambil semua kategori
     *
     * Output:
     * - View 'staff.articles.edit' dengan data artikel dan kategori.
     */
    public function edit(Article $article)
    {
        $this->authorizeArticleOwner($article);

        $categories = Category::all();
        return view('staff.articles.edit', compact('article', 'categories'));
    }

    /**
     * =========================================================================
     * 6. METODE UPDATE - PERBARUI ARTIKEL
     * =========================================================================
     *
     * Fungsi:
     * Memperbarui data artikel.
     *
     * Alur Proses:
     * 1. Cek otorisasi pemilik artikel.
     * 2. Melakukan validasi input.
     * 3. Generate slug unik dari judul.
     * 4. Cek apakah artikel sudah approved sebelumnya.
     * 5. Generate excerpt dari input atau konten.
     * 6. Update artikel dengan status yang sesuai.
     * 7. Redirect ke halaman detail artikel.
     *
     * Query yang Digunakan:
     * - $article->update(): Update artikel
     *
     * Output:
     * - Redirect ke route('staff.articles.show') dengan pesan sukses.
     */
    public function update(Request $request, Article $article)
    {
        $this->authorizeArticleOwner($article);
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string|max:500',
            'keywords' => 'nullable|string|max:255',
        ]);

        $slug = $this->generateUniqueSlug($request->title, $article->id);

        // Jika artikel sudah approved sebelumnya, tetap approved tanpa perlu approval ulang
        $alreadyApproved = $article->is_published && $article->publish_status === 'approved';

        $article->update([
            'category_id' => $request->category_id,
            'title' => $request->title,
            'slug' => $slug,
            'content' => $request->content,
            'excerpt' => $this->generateExcerpt($request->excerpt, $request->content),
            'keywords' => $request->keywords,
            'is_published' => $alreadyApproved ? true : false,
            'publish_status' => $alreadyApproved ? 'approved' : 'pending',
            'rejection_note' => null,
        ]);

        $message = $alreadyApproved 
            ? 'Artikel berhasil diperbarui dan langsung dipublikasikan.' 
            : 'Artikel berhasil diperbarui dan menunggu persetujuan admin.';

        return redirect()->route('staff.articles.show', $article)->with('success', $message);
    }

    /**
     * =========================================================================
     * 7. METODE DESTROY - HAPUS ARTIKEL
     * =========================================================================
     *
     * Fungsi:
     * Menghapus artikel dari database.
     *
     * Alur Proses:
     * 1. Cek otorisasi pemilik artikel.
     * 2. Hapus artikel.
     * 3. Redirect ke halaman daftar artikel.
     *
     * Query yang Digunakan:
     * - $article->delete(): Hapus artikel
     *
     * Output:
     * - Redirect ke route('staff.articles.index') dengan pesan sukses.
     */
    public function destroy(Article $article)
    {
        $this->authorizeArticleOwner($article);

        $article->delete();
        return redirect()->route('staff.articles.index')->with('success', 'Artikel berhasil dihapus.');
    }

    /**
     * =========================================================================
     * 8. METODE RESET VIEWS - RESET VIEW ARTIKEL
     * =========================================================================
     *
     * Fungsi:
     * Mereset jumlah views artikel menjadi 0.
     *
     * Alur Proses:
     * 1. Cek otorisasi pemilik artikel.
     * 2. Update views menjadi 0.
     * 3. Redirect ke halaman detail artikel.
     *
     * Query yang Digunakan:
     * - $article->update(['views' => 0]): Reset views
     *
     * Output:
     * - Redirect ke route('staff.articles.show') dengan pesan sukses.
     */
    public function resetViews(Article $article)
    {
        $this->authorizeArticleOwner($article);

        $article->update(['views' => 0]);

        return redirect()->route('staff.articles.show', $article)->with('success', 'View artikel berhasil di-reset.');
    }

    /**
     * =========================================================================
     * 9. METODE RESET FEEDBACK - RESET REVIEW ARTIKEL
     * =========================================================================
     *
     * Fungsi:
     * Menghapus semua feedback artikel.
     *
     * Alur Proses:
     * 1. Cek otorisasi pemilik artikel.
     * 2. Hapus semua feedback artikel.
     * 3. Redirect ke halaman detail artikel.
     *
     * Query yang Digunakan:
     * - $article->feedback()->delete(): Hapus feedback
     *
     * Output:
     * - Redirect ke route('staff.articles.show') dengan pesan sukses.
     */
    public function resetFeedback(Article $article)
    {
        $this->authorizeArticleOwner($article);

        $article->feedback()->delete();

        return redirect()->route('staff.articles.show', $article)->with('success', 'Review artikel berhasil di-reset.');
    }

    /**
     * =========================================================================
     * 10. METODE AUTHORIZE ARTICLE OWNER - CEK OTORISASI
     * =========================================================================
     *
     * Fungsi:
     * Memeriksa apakah staff yang sedang login adalah pemilik artikel.
     *
     * Alur Proses:
     * 1. Cek apakah staff_id artikel sama dengan user login.
     * 2. Jika tidak sama, abort dengan error 403.
     *
     * Output:
     * - Abort 403 jika tidak authorized.
     */
    private function authorizeArticleOwner(Article $article)
    {
        if ($article->staff_id !== auth()->id()) {
            abort(403, 'Akses ditolak.');
        }
    }

    /**
     * =========================================================================
     * 11. METODE PUBLIC INDEX - DAFTAR ARTIKEL PUBLIK
     * =========================================================================
     *
     * Fungsi:
     * Menampilkan daftar artikel yang sudah dipublikasikan untuk publik.
     *
     * Alur Proses:
     * 1. Mengambil parameter kategori dari query.
     * 2. Query artikel yang published, tidak hidden, dan approved.
     * 3. Filter berdasarkan kategori jika ada.
     * 4. Mengambil semua kategori.
     * 5. Cek setting live service enabled.
     * 6. Mengembalikan view dengan data artikel.
     *
     * Query yang Digunakan:
     * - Article::with('category', 'staff'): Load relasi artikel
     * - where('is_published', true): Filter artikel published
     * - where('is_hidden', false): Filter artikel tidak hidden
     * - where('publish_status', 'approved'): Filter artikel approved
     * - where('category_id', $selectedCategoryId): Filter kategori
     * - paginate(10): Pagination
     *
     * Output:
     * - View 'articles.index' dengan data artikel dan kategori.
     */
    public function publicIndex(Request $request)
    {
        $selectedCategoryId = $request->query('category');

        $articles = Article::with('category', 'staff')
            ->where('is_published', true)
            ->where('is_hidden', false)
            ->where('publish_status', 'approved')
            ->when($selectedCategoryId, function ($query, $selectedCategoryId) {
                return $query->where('category_id', $selectedCategoryId);
            })
            ->paginate(10)
            ->withQueryString();

        $categories = Category::all();
        $liveServiceEnabled = Setting::bool('live_service_enabled', true);

        return view('articles.index', compact('articles', 'categories', 'selectedCategoryId', 'liveServiceEnabled'));
    }

    /**
     * =========================================================================
     * 12. METODE PUBLIC SHOW - DETAIL ARTIKEL PUBLIK
     * =========================================================================
     *
     * Fungsi:
     * Menampilkan detail artikel untuk publik berdasarkan slug.
     *
     * Alur Proses:
     * 1. Query artikel berdasarkan slug yang published, tidak hidden, dan approved.
     * 2. Cek apakah artikel sudah dilihat oleh user di session.
     * 3. Jika belum, increment views dan simpan ke session.
     * 4. Mengambil semua kategori.
     * 5. Mengembalikan view detail artikel.
     *
     * Query yang Digunakan:
     * - Article::with('category', 'staff'): Load relasi artikel
     * - where('slug', $slug): Filter berdasarkan slug
     * - where('is_published', true): Filter artikel published
     * - where('is_hidden', false): Filter artikel tidak hidden
     * - where('publish_status', 'approved'): Filter artikel approved
     * - $article->increment('views'): Increment views
     *
     * Output:
     * - View 'articles.show' dengan data artikel dan kategori.
     */
    public function publicShow($slug)
    {
        $article = Article::with('category', 'staff')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->where('is_hidden', false)
            ->where('publish_status', 'approved')
            ->firstOrFail();

        $viewedArticles = session()->get('viewed_articles', []);
        if (! in_array($article->id, $viewedArticles, true)) {
            $article->increment('views');
            session()->push('viewed_articles', $article->id);
        }

        $categories = Category::all();
        return view('articles.show', compact('article', 'categories'));
    }

    /**
     * =========================================================================
     * 13. METODE GENERATE UNIQUE SLUG - BUAT SLUG UNIK
     * =========================================================================
     *
     * Fungsi:
     * Membuat slug unik dari judul artikel.
     *
     * Alur Proses:
     * 1. Generate slug dari judul menggunakan Str::slug().
     * 2. Simpan slug asli.
     * 3. Cek apakah slug sudah ada di database.
     * 4. Jika sudah ada, tambahkan angka di akhir slug.
     * 5. Ulangi pengecekan sampai slug unik ditemukan.
     *
     * Query yang Digunakan:
     * - Article::where('slug', $slug): Cek slug sudah ada
     * - when($ignoreArticleId): Abaikan artikel tertentu saat cek
     *
     * Output:
     * - String slug yang unik.
     */
    private function generateUniqueSlug(string $title, ?string $ignoreArticleId = null): string
    {
        $slug = Str::slug($title);
        $originalSlug = $slug;
        $count = 1;

        while (Article::where('slug', $slug)
            ->when($ignoreArticleId, fn ($query) => $query->where('id', '!=', $ignoreArticleId))
            ->exists()) {
            $slug = $originalSlug . '-' . $count;
            $count++;
        }

        return $slug;
    }

    /**
     * =========================================================================
     * 14. METODE GENERATE EXCERPT - BUAT RINGKASAN
     * =========================================================================
     *
     * Fungsi:
     * Membuat ringkasan artikel dari manual excerpt atau konten.
     *
     * Alur Proses:
     * 1. Cek apakah manual excerpt ada dan tidak kosong.
     * 2. Jika ada, gunakan manual excerpt dan batasi panjangnya.
     * 3. Jika tidak ada, gunakan konten dan batasi panjangnya.
     *
     * Output:
     * - String excerpt dengan panjang maksimal.
     */
    private function generateExcerpt(?string $manualExcerpt, string $content, int $length = 200): string
    {
        if (!empty($manualExcerpt)) {
            return Str::limit(strip_tags($manualExcerpt), $length);
        }

        return Str::limit(strip_tags($content), $length);
    }

    /**
     * =========================================================================
     * 15. METODE STORE FEEDBACK - SIMPAN FEEDBACK ARTIKEL
     * =========================================================================
     *
     * Fungsi:
     * Menyimpan feedback user terhadap artikel.
     *
     * Alur Proses:
     * 1. Validasi input feedback.
     * 2. Cek apakah user sudah memberikan feedback untuk artikel ini.
     * 3. Jika belum, buat feedback baru.
     * 4. Jika sudah, kembalikan error.
     * 5. Redirect kembali dengan pesan sukses.
     *
     * Query yang Digunakan:
     * - ArticleFeedback::firstOrCreate(): Cek atau buat feedback
     *
     * Output:
     * - Redirect back dengan pesan sukses atau error.
     */
    public function storeFeedback(Request $request, Article $article)
    {
        $request->validate([
            'is_helpful' => 'required|boolean',
        ]);

        $feedback = \App\Models\ArticleFeedback::firstOrCreate([
            'article_id' => $article->id,
            'ip_address' => $request->ip(),
        ], [
            'is_helpful' => $request->is_helpful,
        ]);

        if (! $feedback->wasRecentlyCreated) {
            return redirect()->back()->with('error', 'Anda sudah memberikan feedback untuk artikel ini.');
        }

        return redirect()->back()->with('success', 'Terima kasih atas feedback Anda!');
    }
}