<?php

namespace App\Console\Commands;

use App\Models\Article;
use Illuminate\Console\Command;
use MeiliSearch\Client as MeiliSearchClient;
use Exception;

/**
 * =========================================================================
 * COMMAND SETUP SEARCH - SETUP MEILISEARCH
 * =========================================================================
 *
 * Command ini digunakan untuk setup Meilisearch index untuk artikel.
 *
 * Fitur Utama:
 * - Setup Meilisearch index untuk artikel.
 * - Konfigurasi searchable, filterable, dan sortable attributes.
 * - Setup ranking rules dan synonyms.
 * - Import semua artikel ke Meilisearch.
 */
class SetupSearchCommand extends Command
{
    protected $signature = 'chatbot:setup-search {--fresh : Hapus index lama dan buat ulang}';

    protected $description = 'Setup Meilisearch index untuk artikel dengan settings optimal';

    /**
     * Fungsi:
     * Menjalankan command setup search.
     *
     * Alur Proses:
     * 1. Cek koneksi Meilisearch.
     * 2. Hapus index lama jika opsi --fresh.
     * 3. Buat index articles dengan primary key id.
     * 4. Set searchable, filterable, dan sortable attributes.
     * 5. Set ranking rules dan synonyms.
     * 6. Set typo tolerance.
     * 7. Import semua artikel.
     * 8. Tampilkan statistik.
     * 9. Return exit code SUCCESS atau FAILURE.
     */
    public function handle(): int
    {
        $this->info('🚀 Setting up search untuk Chatbot...');

        $meiliClient = new MeiliSearchClient(
            config('scout.meilisearch.host'),
            config('scout.meilisearch.key')
        );

        $indexName = 'articles';

        try {
            // Cek koneksi Meilisearch
            $this->info('🔍 Mengecek koneksi Meilisearch...');
            $health = $meiliClient->health();
            $this->info('✅ Meilisearch tersedia: ' . $health['status']);

            // Hapus index jika --fresh
            if ($this->option('fresh')) {
                $this->warn('🗑️  Menghapus index lama...');
                $meiliClient->deleteIndex($indexName);
                $this->info('✅ Index dihapus');
            }

            // Buat index jika belum ada
            $this->info('📝 Membuat index articles...');
            $index = $meiliClient->createIndex($indexName, ['primaryKey' => 'id']);

            // Tunggu index ready
            $this->info('⏳ Menunggu index ready...');
            sleep(2);

            // Set searchable attributes
            $this->info('🔧 Mengatur searchable attributes...');
            $index->updateSearchableAttributes([
                'title',
                'content',
                'category_name',
            ]);

            // Set filterable attributes
            $this->info('🔧 Mengatur filterable attributes...');
            $index->updateFilterableAttributes([
                'is_published',
                'category_name',
            ]);

            // Set sortable attributes
            $this->info('🔧 Mengatur sortable attributes...');
            $index->updateSortableAttributes([
                'views',
                'created_at',
            ]);

            // Set ranking rules
            $this->info('🔧 Mengatur ranking rules...');
            $index->updateRankingRules([
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness',
            ]);

            // Set synonyms (Bahasa Indonesia)
            $this->info('🔧 Mengatur synonyms...');
            $index->updateSynonyms([
                'login' => ['masuk', 'log in', 'signin'],
                'password' => ['kata sandi', 'sandi', 'pass'],
                'wifi' => ['wireless', 'internet', 'jaringan'],
                'error' => ['kesalahan', 'galat', 'bug'],
                'reset' => ['atur ulang', 'restart', 'ulang'],
                'lupa' => ['lupakan', 'hilang', 'kehilangan'],
                'tidak bisa' => ['gagal', 'error', 'bukan'],
                'bagaimana' => ['cara', 'gimana', 'how'],
                'apa' => ['what', 'kenapa', 'why'],
            ]);

            // Set typo tolerance
            $this->info('🔧 Mengatur typo tolerance...');
            $index->updateTypoTolerance([
                'enabled' => true,
                'minWordSizeForTypos' => [
                    'oneTypo' => 5,
                    'twoTypos' => 10,
                ],
                'disableOnWords' => [],
                'disableOnAttributes' => [],
            ]);

            // Import semua artikel
            $this->info('📥 Mengimport artikel...');
            $this->call('scout:import', [
                'model' => Article::class,
            ]);

            // Cek jumlah dokumen
            $stats = $index->stats();
            $this->info('✅ Setup selesai! ' . $stats['numberOfDocuments'] . ' artikel diindex');

            return self::SUCCESS;

        } catch (Exception $e) {
            $this->error('❌ Setup gagal: ' . $e->getMessage());
            $this->warn('💡 Pastikan Meilisearch berjalan dan konfigurasi benar');
            return self::FAILURE;
        }
    }
}