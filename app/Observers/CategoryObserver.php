<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\Chatbot\DomainDetectionService;
use Illuminate\Support\Facades\Log;

/**
 * =========================================================================
 * OBSERVER CATEGORY
 * =========================================================================
 *
 * Observer ini memastikan cache domain detection chatbot di-invalidasi
 * setiap kali ada perubahan data kategori (create, update, delete).
 *
 * Mengapa diperlukan:
 * - DomainDetectionService menyimpan mapping keyword domain di cache (TTL 1 jam)
 * - Jika kategori baru ditambah atau keyword diubah, cache lama harus
 *   dihapus agar sistem langsung membaca data terbaru dari database
 *
 * Digunakan oleh:
 * - AppServiceProvider::boot() via Category::observe(CategoryObserver::class)
 */
class CategoryObserver
{
    private DomainDetectionService $domainDetector;

    public function __construct(DomainDetectionService $domainDetector)
    {
        $this->domainDetector = $domainDetector;
    }

    /**
     * Fungsi:
     * Invalidasi cache domain saat kategori baru dibuat.
     */
    public function created(Category $category): void
    {
        $this->safeClearDomainCache('created', $category);
    }

    /**
     * Fungsi:
     * Invalidasi cache domain saat kategori diperbarui.
     */
    public function updated(Category $category): void
    {
        $this->safeClearDomainCache('updated', $category);
    }

    /**
     * Fungsi:
     * Invalidasi cache domain saat kategori dihapus.
     */
    public function deleted(Category $category): void
    {
        $this->safeClearDomainCache('deleted', $category);
    }

    /**
     * Fungsi:
     * Menghapus cache domain dengan penanganan error yang aman.
     * Error tidak menyebabkan operasi kategori gagal.
     */
    private function safeClearDomainCache(string $event, Category $category): void
    {
        try {
            $this->domainDetector->clearCache();

            if (config('app.debug', false)) {
                Log::debug('Domain detection cache cleared', [
                    'event'         => $event,
                    'category_id'   => $category->id,
                    'category_name' => $category->name,
                ]);
            }
        } catch (\Exception $e) {
            // Log error tapi jangan gagalkan operasi kategori
            Log::error('Failed to clear domain detection cache', [
                'event'         => $event,
                'category_id'   => $category->id,
                'category_name' => $category->name,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
