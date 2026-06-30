<?php

namespace App\Jobs;

use App\Services\Chatbot\ChatbotRetrievalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RebuildChatbotCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Tentukan jumlah maksimum percobaan job jika terjadi kegagalan.
     */
    public int $tries = 3;

    /**
     * Tentukan jumlah detik sebelum job timeout.
     */
    public int $timeout = 120;

    /**
     * Membuat instance job baru.
     */
    public function __construct()
    {
        //
    }

    /**
     * Eksekusi job.
     */
    public function handle(ChatbotRetrievalService $retrievalService): void
    {
        Log::info('RebuildChatbotCacheJob: Memulai pembangunan ulang cache TF-IDF...');
        
        try {
            $result = $retrievalService->rebuildCache();
            
            Log::info('RebuildChatbotCacheJob: Berhasil membangun ulang cache TF-IDF', [
                'documents' => $result['documents'] ?? 0,
                'terms' => $result['terms'] ?? 0,
            ]);
        } catch (\Exception $e) {
            Log::error('RebuildChatbotCacheJob: Gagal membangun ulang cache TF-IDF', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            throw $e;
        }
    }
}
