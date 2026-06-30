<?php

namespace App\Jobs;

use App\Services\Chatbot\TypesenseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RemoveArticleFromTypesenseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public string $articleId;

    /**
     * Membuat instance job baru dengan ID artikel.
     */
    public function __construct(string $articleId)
    {
        $this->articleId = $articleId;
    }

    /**
     * Eksekusi job.
     */
    public function handle(TypesenseService $typesenseService): void
    {
        Log::info("RemoveArticleFromTypesenseJob: Memulai penghapusan artikel #{$this->articleId} dari Typesense...");

        try {
            if (!$typesenseService->isConnected()) {
                Log::warning('RemoveArticleFromTypesenseJob: Typesense tidak terhubung, job akan di-retry...');
                $this->release(10);
                return;
            }

            $result = $typesenseService->removeArticle($this->articleId);

            if ($result['success']) {
                Log::info("RemoveArticleFromTypesenseJob: Berhasil menghapus artikel #{$this->articleId} dari Typesense.");
            } else {
                Log::error("RemoveArticleFromTypesenseJob: Gagal menghapus artikel #{$this->articleId}: " . ($result['message'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            Log::error("RemoveArticleFromTypesenseJob: Exception saat menghapus artikel #{$this->articleId}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
