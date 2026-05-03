<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

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

    public function resetViews(Article $article): RedirectResponse
    {
        $article->update(['views' => 0]);
        return redirect()->route('admin.articles.index')->with('success', 'View artikel berhasil di-reset.');
    }

    public function resetFeedback(Article $article): RedirectResponse
    {
        $article->feedback()->delete();
        return redirect()->route('admin.articles.index')->with('success', 'Feedback artikel berhasil di-reset.');
    }

    public function toggleHide(Article $article): RedirectResponse
    {
        $article->update(['is_hidden' => !$article->is_hidden]);
        
        $message = $article->is_hidden 
            ? 'Artikel berhasil disembunyikan dari publik.' 
            : 'Artikel berhasil ditampilkan ke publik.';
        
        return redirect()->route('admin.articles.index')->with('success', $message);
    }

    public function approve(Article $article): RedirectResponse
    {
        $article->update([
            'is_published' => true,
            'publish_status' => 'approved',
            'rejection_note' => null,
        ]);
        
        return redirect()->route('admin.articles.index')->with('success', 'Artikel berhasil disetujui dan dipublikasikan.');
    }

    public function reject(Article $article): RedirectResponse
    {
        request()->validate([
            'rejection_note' => 'required|string|max:1000',
        ]);

        $article->update([
            'is_published' => false,
            'publish_status' => 'rejected',
            'rejection_note' => request()->rejection_note,
        ]);

        return redirect()->route('admin.articles.index')->with('success', 'Artikel ditolak. Catatan penolakan telah disimpan.');
    }

    public function storeRejectionNote(Article $article): RedirectResponse
    {
        request()->validate([
            'rejection_note' => 'required|string|max:1000',
        ]);

        $article->update([
            'rejection_note' => request()->rejection_note,
        ]);

        return redirect()->route('admin.articles.index')->with('success', 'Catatan penolakan berhasil disimpan.');
    }
}
