<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * @deprecated This class is deprecated. Use App\Services\Chatbot\ChatbotRetrievalService instead.
 * 
 * This class was part of the old dual TF-IDF system that caused inconsistent retrieval behavior.
 * All chatbot retrieval should now use ChatbotRetrievalService which provides:
 * - Unified TF-IDF pipeline
 * - Consistent preprocessing
 * - Title boosting
 * - Proper cosine similarity ranking
 * - Top 3 results
 * 
 * For migration:
 * - Replace ArticleSearchService with ChatbotRetrievalService
 * - Use ChatbotRetrievalService::retrieve() instead of search()
 * - Use ChatbotRetrievalService::rebuildCache() instead of indexArticle()
 */
class ArticleSearchService
{
    public function __construct()
    {
        Log::warning('ArticleSearchService is deprecated. Use ChatbotRetrievalService instead.');
    }

    /**
     * @deprecated Use ChatbotRetrievalService::retrieve() instead
     */
    public function search(string $query, int $limit = 5): \Illuminate\Support\Collection
    {
        Log::warning('ArticleSearchService::search() is deprecated. Use ChatbotRetrievalService::retrieve().');
        
        // Return empty collection - caller should use ChatbotRetrievalService instead
        return collect();
    }

    /**
     * @deprecated Use ChatbotRetrievalService::rebuildCache() instead
     */
    public function indexArticle(\App\Models\Article $article): void
    {
        Log::warning('ArticleSearchService::indexArticle() is deprecated. Use ChatbotRetrievalService::rebuildCache().');
    }

    /**
     * @deprecated Use ChatbotRetrievalService::rebuildCache() instead
     */
    public function removeArticleIndex(string|int $articleId): void
    {
        Log::warning('ArticleSearchService::removeArticleIndex() is deprecated. Use ChatbotRetrievalService::clearCache().');
    }
}