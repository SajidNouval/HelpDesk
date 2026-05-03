<?php

namespace App\Observers;

use App\Models\Article;
use App\Services\ArticleSearchService;

class ArticleObserver
{
    private ArticleSearchService $searchService;

    public function __construct(ArticleSearchService $searchService)
    {
        $this->searchService = $searchService;
    }

    /**
     * Auto-index ketika artikel dibuat
     */
    public function created(Article $article): void
    {
        if ($article->is_published) {
            $this->searchService->indexArticle($article);
        }
    }

    /**
     * Auto-index ketika artikel diupdate
     */
    public function updated(Article $article): void
    {
        // Jika artikel di-publish atau di-update, re-index
        if ($article->is_published) {
            $this->searchService->indexArticle($article);
        } else {
            // Jika di-unpublish, hapus dari index
            $this->searchService->removeArticleIndex($article->id);
        }
    }

    /**
     * Hapus index ketika artikel dihapus
     */
    public function deleted(Article $article): void
    {
        $this->searchService->removeArticleIndex($article->id);
    }

    /**
     * Restore index ketika artikel di-restore
     */
    public function restored(Article $article): void
    {
        if ($article->is_published) {
            $this->searchService->indexArticle($article);
        }
    }

    /**
     * Hapus index ketika artikel di-force delete
     */
    public function forceDeleted(Article $article): void
    {
        $this->searchService->removeArticleIndex($article->id);
    }
}
