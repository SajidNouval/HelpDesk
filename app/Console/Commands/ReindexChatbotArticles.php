<?php

namespace App\Console\Commands;

use App\Services\Chatbot\ChatbotRetrievalService;
use Illuminate\Console\Command;

/**
 * =========================================================================
 * COMMAND REINDEX CHATBOT ARTICLES - REINDEX ARTIKEL CHATBOT
 * =========================================================================
 *
 * Command ini digunakan untuk me-reindex artikel untuk chatbot TF-IDF search.
 *
 * Fitur Utama:
 * - Reindex artikel untuk chatbot TF-IDF search.
 * - Force reindex dengan opsi --force.
 * - Menampilkan statistik reindexing.
 */
class ReindexChatbotArticles extends Command
{
    protected $signature = 'chatbot:reindex {--force : Force reindex semua artikel}';

    protected $description = 'Index atau re-index artikel untuk chatbot TF-IDF search';

    private ChatbotRetrievalService $retrievalService;

    /**
     * Fungsi:
     * Membuat instance command baru.
     */
    public function __construct(ChatbotRetrievalService $retrievalService)
    {
        parent::__construct();
        $this->retrievalService = $retrievalService;
    }

    /**
     * Fungsi:
     * Menjalankan command reindex.
     *
     * Alur Proses:
     * 1. Tampilkan pesan memulai reindexing.
     * 2. Jika opsi --force, hapus cache lama.
     * 3. Rebuild cache dengan mengambil semua artikel published & approved.
     * 4. Tampilkan statistik reindexing.
     * 5. Return exit code 0 jika sukses, 1 jika gagal.
     */
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