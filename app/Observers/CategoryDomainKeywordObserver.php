<?php

namespace App\Observers;

use App\Models\CategoryDomainKeyword;
use App\Services\Chatbot\DomainDetectionService;
use Illuminate\Support\Facades\Log;

/**
 * =========================================================================
 * OBSERVER CATEGORY DOMAIN KEYWORD
 * =========================================================================
 *
 * Observer ini memastikan cache domain detection chatbot di-invalidasi
 * setiap kali ada perubahan data keyword domain (create, update, delete).
 *
 * Mengapa diperlukan:
 * - DomainDetectionService menyimpan mapping keyword domain di cache (TTL 1 jam)
 * - Jika keyword baru ditambahkan atau keyword diubah/dihapus, cache lama
 *   harus dihapus agar sistem langsung membaca data terbaru dari database
 * - Serupa dengan ArticleObserver yang me-rebuild cache saat artikel berubah
 *
 * Digunakan oleh:
 * - AppServiceProvider::boot() via CategoryDomainKeyword::observe(...)
 */
class CategoryDomainKeywordObserver
{
    private DomainDetectionService $domainDetector;

    public function __construct(DomainDetectionService $domainDetector)
    {
        $this->domainDetector = $domainDetector;
    }

    /**
     * Fungsi:
     * Invalidasi cache domain saat keyword baru ditambahkan.
     */
    public function created(CategoryDomainKeyword $keyword): void
    {
        $this->safeClearDomainCache('created', $keyword);
    }

    /**
     * Fungsi:
     * Invalidasi cache domain saat keyword diperbarui.
     */
    public function updated(CategoryDomainKeyword $keyword): void
    {
        $this->safeClearDomainCache('updated', $keyword);
    }

    /**
     * Fungsi:
     * Invalidasi cache domain saat keyword dihapus.
     */
    public function deleted(CategoryDomainKeyword $keyword): void
    {
        $this->safeClearDomainCache('deleted', $keyword);
    }

    /**
     * Fungsi:
     * Menghapus cache domain dengan penanganan error yang aman.
     * Error tidak menyebabkan operasi keyword gagal.
     */
    private function safeClearDomainCache(string $event, CategoryDomainKeyword $keyword): void
    {
        try {
            $this->domainDetector->clearCache();

            if (config('app.debug', false)) {
                Log::debug('Domain detection cache cleared due to keyword change', [
                    'event'       => $event,
                    'keyword_id'  => $keyword->id,
                    'keyword'     => $keyword->keyword,
                    'category_id' => $keyword->category_id,
                ]);
            }
        } catch (\Exception $e) {
            // Log error tapi jangan gagalkan operasi keyword
            Log::error('Failed to clear domain detection cache on keyword change', [
                'event'       => $event,
                'keyword_id'  => $keyword->id,
                'keyword'     => $keyword->keyword,
                'category_id' => $keyword->category_id,
                'error'       => $e->getMessage(),
            ]);
        }
    }
}
