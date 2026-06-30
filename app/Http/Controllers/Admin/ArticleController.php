<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

/**
 * =============================================================================
 * ADMIN ARTICLE CONTROLLER - PENGELOLAAN ARTIKEL ADMIN
 * =============================================================================
 * 
 * Controller ini bertanggung jawab untuk mengelola artikel dari sisi admin.
 * Admin memiliki wewenang penuh untuk menyetujui, menolak, menyembunyikan,
 * atau mereset statistik artikel yang dibuat oleh staff.
 * 
 * Fitur Utama:
 * - Daftar semua artikel dengan filtering dan sorting
 * - Detail artikel dengan statistik feedback
 * - Persetujuan (approve) artikel untuk dipublikasi
 * - Penolakan (reject) artikel dengan catatan
 * - Sembunyikan/tampilkan artikel dari publik
 * - Reset views dan feedback artikel
 * 
 * Model Terkait:
 * - Article: Model artikel
 * - Category: Kategori artikel (relasi)
 * - User/Staff: Penulis artikel (relasi)
 */
class ArticleController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE INDEX - DAFTAR ARTIKEL ADMIN
     * =========================================================================
     * 
     * Fungsi: Menampilkan daftar semua artikel untuk admin.
     * 
     * Alur Proses:
     * 1. Ambil parameter filter dari request (pencarian, sorting, status)
     * 2. Bangun query dengan relasi category dan staff
     * 3. Hitung jumlah feedback helpful dan not helpful
     * 4. Terapkan filter pencarian berdasarkan judul, konten, atau nama staff
     * 5. Terapkan filter status publikasi
     * 6. Terapkan sorting berdasarkan parameter
     * 7. Hitung statistik artikel (total, pending, approved, rejected)
     * 8. Kembalikan view admin dengan data lengkap
     * 
     * Query yang Digunakan:
     * - Article::with('category', 'staff'): Load relasi
     * - withCount(): Hitung feedback helpful/not helpful
     * - when($search, ...): Filter pencarian LIKE pada title, content, staff name
     * - when($status, ...): Filter publish_status
     * - paginate(20): Pagination 20 item per halaman
     * 
     * Output:
     * - View 'admin.articles.index' dengan data artikel dan statistik
     */
    public function index(): View
    {
        $search = request('q');
        $sort = request('sort', 'created_desc');
        $status = request('status');

        $articlesQuery = Article::select(['id', 'category_id', 'staff_id', 'title', 'slug', 'views', 'publish_status', 'is_hidden', 'created_at'])
            ->with([
                'category:id,name',
                'staff:id,name'
            ])
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
                        ->orWhereHas('staff', function ($query) use ($search) {
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
            default:
                $articlesQuery->orderBy('created_at', 'desc');
        }

        $articles = $articlesQuery->paginate(20)->withQueryString();

        $stats = Article::selectRaw("
            count(*) as total,
            count(case when publish_status = 'pending' then 1 end) as pending,
            count(case when publish_status = 'approved' then 1 end) as approved,
            count(case when publish_status = 'rejected' then 1 end) as rejected
        ")->first();

        $totalArticles = $stats->total;
        $pendingArticles = $stats->pending;
        $approvedArticles = $stats->approved;
        $rejectedArticles = $stats->rejected;

        return view('admin.articles.index', compact(
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
     * 2. METODE SHOW - DETAIL ARTIKEL ADMIN
     * =========================================================================
     * 
     * Fungsi: Menampilkan detail lengkap artikel untuk admin.
     * 
     * Alur Proses:
     * 1. Load relasi category dan staff
     * 2. Hitung jumlah feedback helpful dan not helpful
     * 3. Kembalikan view detail
     * 
     * Query yang Digunakan:
     * - $article->load(['category', 'staff']): Load relasi
     * - loadCount(): Hitung feedback
     * 
     * Output:
     * - View 'admin.articles.show'
     */
    public function show(Article $article): View
    {
        $article->load(['category:id,name', 'staff:id,name']);
        $article->loadCount([
            'feedback as helpful_count' => function ($query) {
                $query->where('is_helpful', true);
            },
            'feedback as not_helpful_count' => function ($query) {
                $query->where('is_helpful', false);
            },
        ]);

        return view('admin.articles.show', compact('article'));
    }

    /**
     * =========================================================================
     * 3. HELPER - BUILD JSON RESPONSE
     * =========================================================================
     * 
     * Fungsi: Membangun response JSON standar untuk operasi artikel.
     * 
     * Output:
     * - JsonResponse dengan data artikel (views, is_hidden, publish_status, dll)
     */
    private function buildJsonArticleResponse(Article $article, string $message): JsonResponse
    {
        $feedbackStats = $article->feedback()
            ->selectRaw("
                count(case when is_helpful = 1 then 1 end) as helpful,
                count(case when is_helpful = 0 then 1 end) as not_helpful
            ")
            ->first();

        return response()->json([
            'success' => true,
            'message' => $message,
            'article' => [
                'id' => $article->id,
                'views' => $article->views,
                'is_hidden' => $article->is_hidden,
                'publish_status' => $article->publish_status,
                'helpful_count' => $feedbackStats->helpful ?? 0,
                'not_helpful_count' => $feedbackStats->not_helpful ?? 0,
                'rejection_note' => $article->rejection_note,
            ],
        ]);
    }

    /**
     * =========================================================================
     * 4. METODE RESET VIEWS - RESET COUNTER VIEW
     * =========================================================================
     * 
     * Fungsi: Mereset counter views artikel menjadi 0.
     * 
     * Query yang Digunakan:
     * - $article->update(['views' => 0]): Update counter
     * 
     * Output:
     * - Redirect atau JSON response dengan pesan sukses
     */
    public function resetViews(Article $article): RedirectResponse|JsonResponse
    {
        $article->update(['views' => 0]);

        if (request()->expectsJson()) {
            return $this->buildJsonArticleResponse($article, 'View artikel berhasil di-reset.');
        }

        return $this->safeRedirect('admin.articles.index')->with('success', 'View artikel berhasil di-reset.');
    }

    /**
     * =========================================================================
     * 5. METODE RESET FEEDBACK - HAPUS FEEDBACK
     * =========================================================================
     * 
     * Fungsi: Menghapus semua feedback artikel.
     * 
     * Query yang Digunakan:
     * - $article->feedback()->delete(): Hapus semua feedback
     * 
     * Output:
     * - Redirect atau JSON response
     */
    public function resetFeedback(Article $article): RedirectResponse|JsonResponse
    {
        $article->feedback()->delete();

        if (request()->expectsJson()) {
            return $this->buildJsonArticleResponse($article, 'Feedback artikel berhasil di-reset.');
        }

        return $this->safeRedirect('admin.articles.index')->with('success', 'Feedback artikel berhasil di-reset.');
    }

    /**
     * =========================================================================
     * 6. METODE TOGGLE HIDE - SEMBUNYIKAN/TAMPILKAN ARTIKEL
     * =========================================================================
     * 
     * Fungsi: Toggle visibilitas artikel untuk publik.
     * 
     * Alur Proses:
     * 1. Toggle nilai is_hidden (true -> false, false -> true)
     * 2. Tentukan pesan berdasarkan status baru
     * 3. Kembalikan response
     * 
     * Query yang Digunakan:
     * - $article->update(['is_hidden' => !$article->is_hidden]): Toggle status
     * 
     * Output:
     * - Redirect atau JSON response
     */
    public function toggleHide(Article $article): RedirectResponse|JsonResponse
    {
        $article->update(['is_hidden' => !$article->is_hidden]);
        
        $message = $article->is_hidden 
            ? 'Artikel berhasil disembunyikan dari publik.' 
            : 'Artikel berhasil ditampilkan ke publik.';

        if (request()->expectsJson()) {
            return $this->buildJsonArticleResponse($article, $message);
        }
        
        return $this->safeRedirect('admin.articles.index')->with('success', $message);
    }

    /**
     * =========================================================================
     * 7. METODE APPROVE - SETUJUI ARTIKEL
     * =========================================================================
     * 
     * Fungsi: Menyetujui artikel untuk dipublikasi.
     * 
     * Alur Proses:
     * 1. Update is_published = true
     * 2. Update publish_status = 'approved'
     * 3. Hapus rejection_note jika ada
     * 4. Kembalikan response
     * 
     * Query yang Digunakan:
     * - $article->update([...]): Update status publikasi
     * 
     * Output:
     * - Redirect atau JSON response
     */
    public function approve(Article $article): RedirectResponse|JsonResponse
    {
        $article->update([
            'is_published' => true,
            'publish_status' => 'approved',
            'rejection_note' => null,
        ]);
        
        if (request()->expectsJson()) {
            return $this->buildJsonArticleResponse($article, 'Artikel berhasil disetujui dan dipublikasikan.');
        }

        return $this->safeRedirect('admin.articles.index')->with('success', 'Artikel berhasil disetujui dan dipublikasikan.');
    }

    /**
     * =========================================================================
     * 8. METODE REJECT - TOLAK ARTIKEL
     * =========================================================================
     * 
     * Fungsi: Menolak artikel dengan catatan penolakan.
     * 
     * Alur Proses:
     * 1. Validasi rejection_note (wajib diisi)
     * 2. Update is_published = false
     * 3. Update publish_status = 'rejected'
     * 4. Simpan rejection_note
     * 5. Kembalikan response
     * 
     * Query yang Digunakan:
     * - request()->validate(): Validasi input
     * - $article->update([...]): Update status penolakan
     * 
     * Output:
     * - Redirect atau JSON response
     */
    public function reject(Article $article): RedirectResponse|JsonResponse
    {
        request()->validate([
            'rejection_note' => 'required|string|max:1000',
        ]);

        $article->update([
            'is_published' => false,
            'publish_status' => 'rejected',
            'rejection_note' => request()->rejection_note,
        ]);

        if (request()->expectsJson()) {
            return $this->buildJsonArticleResponse($article, 'Artikel ditolak. Catatan penolakan telah disimpan.');
        }

        return $this->safeRedirect('admin.articles.index')->with('success', 'Artikel ditolak. Catatan penolakan telah disimpan.');
    }

    /**
     * =========================================================================
     * 9. METODE STORE REJECTION NOTE - SIMPAN CATATAN PENOLAKAN
     * =========================================================================
     * 
     * Fungsi: Menyimpan/memperbarui catatan penolakan untuk artikel.
     * 
     * Alur Proses:
     * 1. Validasi rejection_note
     * 2. Update rejection_note pada artikel
     * 3. Kembalikan response
     * 
     * Query yang Digunakan:
     * - $article->update(['rejection_note' => ...]): Update catatan
     * 
     * Output:
     * - Redirect atau JSON response
     */
    public function storeRejectionNote(Article $article): RedirectResponse|JsonResponse
    {
        request()->validate([
            'rejection_note' => 'required|string',
        ]);

        $article->update([
            'rejection_note' => request()->rejection_note,
        ]);

        if (request()->expectsJson()) {
            return $this->buildJsonArticleResponse($article, 'Catatan penolakan berhasil disimpan.');
        }

        return $this->safeRedirect('admin.articles.index')->with('success', 'Catatan penolakan berhasil disimpan.');
    }
}