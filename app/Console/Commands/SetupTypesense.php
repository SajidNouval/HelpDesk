<?php

namespace App\Console\Commands;

use App\Services\Chatbot\TypesenseService;
use Illuminate\Console\Command;

class SetupTypesense extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'typesense:setup {--reindex : Reindex all articles}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Set up Typesense collection, create synonym sets, and optionally index all articles';

    private TypesenseService $typesenseService;

    public function handle(TypesenseService $typesenseService): int
    {
        $this->typesenseService = $typesenseService;

        $this->info('🔍 Typesense Setup Command');
        $this->newLine();

        // Check connection
        if (!$this->typesenseService->isConnected()) {
            $this->error('❌ Cannot connect to Typesense server.');
            $this->warn('Please ensure Typesense is running on ' . config('typesense.host') . ':' . config('typesense.port'));
            $this->warn('Default API key: ' . config('typesense.api_key'));
            
            return self::FAILURE;
        }

        $this->info('✅ Connected to Typesense server!');
        $this->newLine();

        // Create or update collection
        $this->info('📦 Setting up articles collection...');
        $result = $this->typesenseService->createOrUpdateCollection();

        if ($result['success']) {
            $this->info('✅ Collection created/updated successfully!');
        } else {
            $this->error('❌ Failed to create collection: ' . ($result['message'] ?? 'Unknown error'));
            return self::FAILURE;
        }

        $this->newLine();

        // Create intent-level synonym sets
        $this->info('🔗 Creating intent-level synonym sets...');
        $this->info('   Synonyms improve query understanding by treating related terms as equivalent.');
        $this->newLine();

        $synonymResult = $this->typesenseService->createAllSynonyms();

        if ($synonymResult['success']) {
            $this->info("✅ Created {$synonymResult['created']} synonym sets");
            
            // Show synonym set details
            $synonymSets = $this->typesenseService->getIntentSynonymSets();
            $this->table(
                ['Intent', 'Terms Count', 'Sample Terms'],
                $this->formatSynonymOverview($synonymSets)
            );
        } else {
            $this->warn("⚠️ Some synonym sets failed to create ({$synonymResult['errors']} errors)");
            if (!empty($synonymResult['details'])) {
                foreach ($synonymResult['details'] as $detail) {
                    $this->error("   - {$detail['intent']}: {$detail['error']}");
                }
            }
        }

        $this->newLine();

        // Optionally reindex all articles
        if ($this->option('reindex')) {
            $this->info('📚 Indexing all published articles...');
            $result = $this->typesenseService->indexAllArticles();

            if ($result['success']) {
                $this->info("✅ Indexed {$result['indexed']} articles ({$result['errors']} errors)");
            } else {
                $this->error('❌ Failed to index articles: ' . ($result['message'] ?? 'Unknown error'));
            }
        } else {
            $this->info('💡 Run with --reindex to index all articles');
        }

        $this->newLine();

        // Show collection stats
        $this->info('📊 Collection Statistics:');
        $stats = $this->typesenseService->getCollectionStats();
        
        if ($stats['success']) {
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Collection Name', $stats['stats']['name']],
                    ['Total Documents', $stats['stats']['num_documents']],
                    ['Total Fields', $stats['stats']['fields']],
                ]
            );
        }

        $this->newLine();
        $this->info('✨ Typesense setup complete!');
        $this->info('💬 Synonyms are now active. Queries like "wifi gagal konek" will match "connect", "konek", "terhubung", etc.');

        return self::SUCCESS;
    }

    /**
     * Format synonym sets for display in table
     */
    private function formatSynonymOverview(array $synonymSets): array
    {
        $rows = [];
        foreach ($synonymSets as $intent => $terms) {
            $sampleTerms = implode(', ', array_slice($terms, 0, 3));
            if (count($terms) > 3) {
                $sampleTerms .= ', ...';
            }
            $rows[] = [
                ucfirst($intent),
                count($terms),
                $sampleTerms,
            ];
        }
        return $rows;
    }
}
