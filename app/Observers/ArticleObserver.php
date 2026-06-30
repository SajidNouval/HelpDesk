<?php

namespace App\Observers;

use App\Models\Article;
use App\Services\Chatbot\ChatbotRetrievalService;
use App\Services\Chatbot\TypesenseService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ArticleObserver
{
    private ChatbotRetrievalService $retrievalService;
    private TypesenseService $typesenseService;

    public function __construct(
        ChatbotRetrievalService $retrievalService,
        TypesenseService $typesenseService
    ) {
        $this->retrievalService = $retrievalService;
        $this->typesenseService = $typesenseService;
    }

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

            $this->retrievalService->rebuildCache();
            
            if (config('app.debug', false)) {
                Log::debug('Chatbot cache rebuilt', [
                    'event' => $event,
                    'article_id' => $article->id,
                    'article_title' => $article->title,
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the article operation
            Log::error('Chatbot cache rebuild failed', [
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
            if (!$this->typesenseService->isConnected()) {
                // Typesense not connected - log warning but don't fail
                if (config('app.debug', false)) {
                    Log::warning('Typesense not connected, skipping sync', [
                        'event' => $event,
                        'article_id' => $article->id,
                    ]);
                }
                return;
            }

            $result = $this->typesenseService->indexArticle($article);
            
            if (config('app.debug', false) || $result['success']) {
                Log::debug('Article synced to Typesense', [
                    'event' => $event,
                    'article_id' => $article->id,
                    'article_title' => $article->title,
                    'result' => $result['message'] ?? '',
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the article operation
            Log::error('Typesense sync failed', [
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
            if (!$this->typesenseService->isConnected()) {
                return;
            }

            $result = $this->typesenseService->removeArticle((string) $article->id);
            
            if (config('app.debug', false)) {
                Log::debug('Article removed from Typesense', [
                    'event' => $event,
                    'article_id' => $article->id,
                    'article_title' => $article->title,
                    'result' => $result['message'] ?? '',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Typesense removal failed', [
                'event' => $event,
                'article_id' => $article->id,
                'article_title' => $article->title,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
