<?php

namespace App\Providers;

use App\Models\Article;
use App\Models\Category;
use App\Models\CategoryDomainKeyword;
use App\Observers\ArticleObserver;
use App\Observers\CategoryDomainKeywordObserver;
use App\Observers\CategoryObserver;
use App\Services\Chatbot\ChatbotRetrievalService;
use App\Services\Chatbot\CosineSimilarityService;
use App\Services\Chatbot\DomainDetectionService;
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
        
        // DomainDetectionService sebagai singleton agar loadDomainKeywords
        // hanya dijalankan sekali per request lifecycle
        $this->app->singleton(DomainDetectionService::class);
        
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
        // Register Article Observer untuk auto-indexing
        // Observer akan trigger cache rebuild saat artikel dibuat/diupdate/dihapus
        Article::observe(ArticleObserver::class);

        // Register Category Observer untuk invalidasi cache domain detection
        // Observer akan menghapus cache keyword domain saat kategori berubah
        Category::observe(CategoryObserver::class);

        // Register CategoryDomainKeyword Observer
        // Invalidasi cache saat keyword domain ditambah/diubah/dihapus langsung
        CategoryDomainKeyword::observe(CategoryDomainKeywordObserver::class);
    }
}