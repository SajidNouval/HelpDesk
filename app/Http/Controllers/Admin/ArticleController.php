<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;

class ArticleController extends Controller
{
    public function index(): View
    {
        $articles = Article::with('category', 'staff')
            ->withCount([
                'feedback as helpful_count' => function ($query) {
                    $query->where('is_helpful', true);
                },
                'feedback as not_helpful_count' => function ($query) {
                    $query->where('is_helpful', false);
                },
            ])
            ->orderBy('views', 'desc')
            ->paginate(20);

        return view('admin.articles.index', compact('articles'));
    }

    public function show(Article $article): View
    {
        $article->load(['category', 'staff']);
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

    private function buildJsonArticleResponse(Article $article, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'article' => [
                'id' => $article->id,
                'views' => $article->views,
                'is_hidden' => $article->is_hidden,
                'publish_status' => $article->publish_status,
                'helpful_count' => $article->feedback()->where('is_helpful', true)->count(),
                'not_helpful_count' => $article->feedback()->where('is_helpful', false)->count(),
                'rejection_note' => $article->rejection_note,
            ],
        ]);
    }

    public function resetViews(Article $article): RedirectResponse|JsonResponse
    {
        $article->update(['views' => 0]);

        if (request()->expectsJson()) {
            return $this->buildJsonArticleResponse($article, 'View artikel berhasil di-reset.');
        }

        return redirect()->route('admin.articles.index')->with('success', 'View artikel berhasil di-reset.');
    }

    public function resetFeedback(Article $article): RedirectResponse|JsonResponse
    {
        $article->feedback()->delete();

        if (request()->expectsJson()) {
            return $this->buildJsonArticleResponse($article, 'Feedback artikel berhasil di-reset.');
        }

        return redirect()->route('admin.articles.index')->with('success', 'Feedback artikel berhasil di-reset.');
    }

    public function toggleHide(Article $article): RedirectResponse|JsonResponse
    {
        $article->update(['is_hidden' => !$article->is_hidden]);
        
        $message = $article->is_hidden 
            ? 'Artikel berhasil disembunyikan dari publik.' 
            : 'Artikel berhasil ditampilkan ke publik.';

        if (request()->expectsJson()) {
            return $this->buildJsonArticleResponse($article, $message);
        }
        
        return redirect()->route('admin.articles.index')->with('success', $message);
    }

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

        return redirect()->route('admin.articles.index')->with('success', 'Artikel berhasil disetujui dan dipublikasikan.');
    }

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

        return redirect()->route('admin.articles.index')->with('success', 'Artikel ditolak. Catatan penolakan telah disimpan.');
    }

    public function storeRejectionNote(Article $article): RedirectResponse|JsonResponse
    {
        request()->validate([
            'rejection_note' => 'required|string|max:1000',
        ]);

        $article->update([
            'rejection_note' => request()->rejection_note,
        ]);

        if (request()->expectsJson()) {
            return $this->buildJsonArticleResponse($article, 'Catatan penolakan berhasil disimpan.');
        }

        return redirect()->route('admin.articles.index')->with('success', 'Catatan penolakan berhasil disimpan.');
    }
}
