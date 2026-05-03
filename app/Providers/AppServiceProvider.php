<?php

namespace App\Providers;

use App\Models\Article;
use App\Observers\ArticleObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\ArticleSearchService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Auto-learning: index artikel saat dibuat/diupdate/dihapus
        Article::observe(ArticleObserver::class);


    }
}
