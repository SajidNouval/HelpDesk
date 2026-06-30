<?php

namespace App\Observers;

use App\Models\Article;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Jobs\RebuildChatbotCacheJob;
use App\Jobs\SyncArticleToTypesenseJob;
use App\Jobs\RemoveArticleFromTypesenseJob;

class ArticleObserver
{

    /**
     * Auto-index ketika artikel dibuat
     * Hanya index jika published DAN approved
     */
    public function created(Article $article): void
    {
        if ($this->shouldIndex($article)) {
            $this->safeRebuildCache('created', $article);
            $this->safeSyncTypesense('created', $article);
        }
    }

    /**
     * Auto-reindex ketika artikel diupdate
     * Rebuild cache jika status berubah atau konten berubah
     */
    public function updated(Article $article): void
    {
        // Always rebuild cache on update - article content or status may have changed
        $this->safeRebuildCache('updated', $article);
        
        // Sync to Typesense
        if ($this->shouldIndex($article)) {
            $this->safeSyncTypesense('updated', $article);
        } else {
            // Remove from Typesense if no longer indexable
            $this->safeRemoveFromTypesense('updated', $article);
        }
    }

    /**
     * Hapus dari index ketika artikel dihapus
     */
    public function deleted(Article $article): void
    {
        $this->safeRebuildCache('deleted', $article);
        $this->safeRemoveFromTypesense('deleted', $article);
    }

    /**
     * Restore index ketika artikel di-restore
     */
    public function restored(Article $article): void
    {
        if ($this->shouldIndex($article)) {
            $this->safeRebuildCache('restored', $article);
            $this->safeSyncTypesense('restored', $article);
        }
    }

    /**
     * Hapus index ketika artikel di-force delete
     */
    public function forceDeleted(Article $article): void
    {
        $this->safeRebuildCache('forceDeleted', $article);
        $this->safeRemoveFromTypesense('forceDeleted', $article);
    }

    /**
     * Cek apakah artikel harus diindex
     * Harus published DAN approved
     */
    private function shouldIndex(Article $article): bool
    {
        return $article->is_published === true 
            && $article->publish_status === 'approved';
    }

    /**
     * Safely rebuild cache with error handling
     * Logs errors but doesn't fail the article operation
     */
    private function safeRebuildCache(string $event, Article $article): void
    {
        try {
            Cache::forget('articles_cache_version');
            Cache::forget('admin_dashboard_version');

            RebuildChatbotCacheJob::dispatch();
            
            if (config('app.debug', false)) {
                Log::debug('Chatbot cache rebuild job dispatched', [
                    'event' => $event,
                    'article_id' => $article->id,
                    'article_title' => $article->title,
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the article operation
            Log::error('Dispatched Chatbot cache rebuild job failed', [
                'event' => $event,
                'article_id' => $article->id,
                'article_title' => $article->title,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Safely sync article to Typesense
     * Logs errors but doesn't fail the article operation
     */
    private function safeSyncTypesense(string $event, Article $article): void
    {
        try {
            SyncArticleToTypesenseJob::dispatch($article);
            
            if (config('app.debug', false)) {
                Log::debug('Sync article to Typesense job dispatched', [
                    'event' => $event,
                    'article_id' => $article->id,
                    'article_title' => $article->title,
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the article operation
            Log::error('Dispatched Typesense sync job failed', [
                'event' => $event,
                'article_id' => $article->id,
                'article_title' => $article->title,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Safely remove article from Typesense
     * Logs errors but doesn't fail the article operation
     */
    private function safeRemoveFromTypesense(string $event, Article $article): void
    {
        try {
            RemoveArticleFromTypesenseJob::dispatch((string) $article->id);
            
            if (config('app.debug', false)) {
                Log::debug('Remove article from Typesense job dispatched', [
                    'event' => $event,
                    'article_id' => $article->id,
                    'article_title' => $article->title,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Dispatched Typesense removal job failed', [
                'event' => $event,
                'article_id' => $article->id,
                'article_title' => $article->title,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
