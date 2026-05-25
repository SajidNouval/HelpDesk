<?php

namespace App\Console\Commands;

use App\Services\Chatbot\ChatbotRetrievalService;
use Illuminate\Console\Command;

class ReindexChatbotArticles extends Command
{
    protected $signature = 'chatbot:reindex {--force : Force reindex semua artikel}';

    protected $description = 'Index atau re-index artikel untuk chatbot TF-IDF search';

    private ChatbotRetrievalService $retrievalService;

    public function __construct(ChatbotRetrievalService $retrievalService)
    {
        parent::__construct();
        $this->retrievalService = $retrievalService;
    }

    public function handle(): int
    {
        $this->info('🔍 Memulai re-indexing artikel untuk chatbot TF-IDF...');

        if ($this->option('force')) {
            $this->info('🗑️  Menghapus cache lama...');
            $this->retrievalService->clearCache();
        }

        // Rebuild cache - ini akan mengambil semua artikel published & approved
        try {
            $result = $this->retrievalService->rebuildCache();

            $this->newLine();
            $this->info("✅ Re-indexing selesai!");
            $this->line("   Dokumen: {$result['documents']}");
            $this->line("   Term/Token unik: {$result['terms']}");

            return 0;
        } catch (\Exception $e) {
            $this->error("❌ Gagal re-index: {$e->getMessage()}");
            return 1;
        }
    }
}