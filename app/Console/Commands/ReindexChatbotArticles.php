<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\ArticleSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReindexChatbotArticles extends Command
{
    protected $signature = 'chatbot:reindex {--force : Force reindex semua artikel}';

    protected $description = 'Index atau re-index artikel untuk chatbot search';

    private ArticleSearchService $searchService;

    public function __construct(ArticleSearchService $searchService)
    {
        parent::__construct();
        $this->searchService = $searchService;
    }

    public function handle(): int
    {
        $this->info('🔍 Memulai re-indexing artikel untuk chatbot...');

        // Clear index lama jika force
        if ($this->option('force')) {
            $this->info('🗑️  Menghapus index lama...');
            DB::table('article_keyword_index')->truncate();
        }

        // Ambil artikel yang published dan disetujui
        $articles = Article::where('is_published', true)
            ->where('publish_status', 'approved')
            ->orderBy('id')
            ->get();

        if ($articles->isEmpty()) {
            $this->warn('⚠️  Tidak ada artikel yang dipublikasikan.');
            return 0;
        }

        $this->info("📄 Ditemukan {$articles->count()} artikel yang dipublikasikan.");

        // Progress bar
        $progressBar = $this->output->createProgressBar($articles->count());
        $progressBar->start();

        $indexed = 0;
        $failed = 0;

        foreach ($articles as $article) {
            try {
                $this->searchService->indexArticle($article);
                $indexed++;
            } catch (\Exception $e) {
                $this->error("\n❌ Gagal index artikel {$article->id}: {$e->getMessage()}");
                $failed++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        // Summary
        $this->newLine(2);
        $this->info("✅ Re-indexing selesai!");
        $this->line("   Berhasil: {$indexed}");
        $this->line("   Gagal: {$failed}");

        return $failed > 0 ? 1 : 0;
    }
}
