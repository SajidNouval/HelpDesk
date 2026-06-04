<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    // Staff CRUD methods
    public function index()
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
            ->orderByRaw('(staff_id = ?) DESC', [auth()->id()])
            ->paginate(10);

        return view('staff.articles.index', compact('articles'));
    }

    public function create()
    {
        $categories = Category::all();
        return view('staff.articles.create', compact('categories'));
    }

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

    public function edit(Article $article)
    {
        $this->authorizeArticleOwner($article);

        $categories = Category::all();
        return view('staff.articles.edit', compact('article', 'categories'));
    }

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

    public function destroy(Article $article)
    {
        $this->authorizeArticleOwner($article);

        $article->delete();
        return redirect()->route('staff.articles.index')->with('success', 'Artikel berhasil dihapus.');
    }

    public function resetViews(Article $article)
    {
        $this->authorizeArticleOwner($article);

        $article->update(['views' => 0]);

        return redirect()->route('staff.articles.show', $article)->with('success', 'View artikel berhasil di-reset.');
    }

    public function resetFeedback(Article $article)
    {
        $this->authorizeArticleOwner($article);

        $article->feedback()->delete();

        return redirect()->route('staff.articles.show', $article)->with('success', 'Review artikel berhasil di-reset.');
    }

    private function authorizeArticleOwner(Article $article)
    {
        if ($article->staff_id !== auth()->id()) {
            abort(403, 'Akses ditolak.');
        }
    }

    // Public methods
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

    private function generateExcerpt(?string $manualExcerpt, string $content, int $length = 200): string
    {
        if (!empty($manualExcerpt)) {
            return Str::limit(strip_tags($manualExcerpt), $length);
        }

        return Str::limit(strip_tags($content), $length);
    }

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