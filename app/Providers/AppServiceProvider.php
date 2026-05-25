<?php

namespace App\Providers;

use App\Models\Article;
use App\Observers\ArticleObserver;
use App\Services\Chatbot\ChatbotRetrievalService;
use App\Services\Chatbot\CosineSimilarityService;
use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\TypesenseService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     * 
     * All chatbot retrieval is now unified through ChatbotRetrievalService.
     * The old ArticleSearchService has been deprecated.
     */
    public function register(): void
    {
        // Unified Chatbot services - single TF-IDF pipeline
        $this->app->singleton(PreprocessingService::class);
        $this->app->singleton(TfidfService::class);
        $this->app->singleton(CosineSimilarityService::class);
        $this->app->singleton(ChatbotRetrievalService::class);
        
        // Typesense service for fuzzy retrieval and typo tolerance
        $this->app->singleton(TypesenseService::class);
        
        // Note: ArticleSearchService is deprecated and should not be used.
        // Use ChatbotRetrievalService for all article retrieval operations.
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Article Observer for auto-indexing
        // Observer will trigger cache rebuild when articles are created/updated/deleted
        Article::observe(ArticleObserver::class);
    }
}