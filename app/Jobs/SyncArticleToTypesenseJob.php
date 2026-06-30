<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\Chatbot\TypesenseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncArticleToTypesenseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public Article $article;

    /**
     * Membuat instance job baru.
     */
    public function __construct(Article $article)
    {
        $this->article = $article;
    }

    /**
     * Eksekusi job.
     */
    public function handle(TypesenseService $typesenseService): void
    {
        // Pastikan model masih ada dan harus diindex
        if (!$this->article->exists || !$this->shouldIndex($this->article)) {
            Log::info("SyncArticleToTypesenseJob: Artikel #{$this->article->id} tidak layak diindex atau sudah dihapus. Melewati...");
            return;
        }

        Log::info("SyncArticleToTypesenseJob: Memulai sinkronisasi artikel #{$this->article->id} ke Typesense...");

        try {
            if (!$typesenseService->isConnected()) {
                Log::warning('SyncArticleToTypesenseJob: Typesense tidak terhubung, job akan di-retry...');
                $this->release(10); // Lepas kembali ke antrean setelah 10 detik
                return;
            }

            $result = $typesenseService->indexArticle($this->article);

            if ($result['success']) {
                Log::info("SyncArticleToTypesenseJob: Berhasil mensinkronisasi artikel #{$this->article->id} ke Typesense.");
            } else {
                Log::error("SyncArticleToTypesenseJob: Gagal mensinkronisasi artikel #{$this->article->id}: " . ($result['message'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error("SyncArticleToTypesenseJob: Exception saat sinkronisasi artikel #{$this->article->id}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Cek apakah artikel harus diindex.
     */
    private function shouldIndex(Article $article): bool
    {
        return $article->is_published === true 
            && $article->publish_status === 'approved';
    }
}
