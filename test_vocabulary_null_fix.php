<?php

/**
 * Test script to verify VocabularyService null vocabulary crash fix
 * 
 * This tests that typo queries like "virusss", "viruss", "ransomwre" 
 * no longer cause in_array() crashes when vocabulary is null.
 */

// Bootstrap Laravel
require_once __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Chatbot\VocabularyService;
use Illuminate\Support\Facades\Log;

echo "=== Testing VocabularyService Null Vocabulary Fix ===\n\n";

// Create the service
$service = new VocabularyService();

// Test cases - these are the typo queries that were causing crashes
$testQueries = [
    'virusss',
    'viruss', 
    'ransomwre',
    'malwere',
    'wfi',
    'printer test',
    'virusss internet',
    '',  // empty query
    'a', // very short
];

echo "Testing normalizeQuery() with typo queries:\n";
echo str_repeat("-", 50) . "\n";

foreach ($testQueries as $query) {
    echo "\nQuery: '$query'\n";
    try {
        $result = $service->normalizeQuery($query);
        echo "  Original:   '{$result['original']}'\n";
        echo "  Normalized: '{$result['normalized']}'\n";
        echo "  Corrections: " . count($result['corrections']) . "\n";
        if (!empty($result['corrections'])) {
            foreach ($result['corrections'] as $correction) {
                echo "    - {$correction['original']} -> {$correction['corrected']} ({$correction['source']})\n";
            }
        }
        echo "  ✓ No crash!\n";
    } catch (Throwable $e) {
        echo "  ✗ ERROR: " . $e->getMessage() . "\n";
        echo "    Type: " . get_class($e) . "\n";
    }
}

echo "\n" . str_repeat("-", 50) . "\n";
echo "\nTesting loadVocabulary() never returns null:\n";
echo str_repeat("-", 50) . "\n";

$service2 = new VocabularyService();
$vocabulary = $service2->loadVocabulary();

echo "Type: " . gettype($vocabulary) . "\n";
echo "Is array: " . (is_array($vocabulary) ? 'Yes' : 'No') . "\n";
echo "Count: " . count($vocabulary) . "\n";

if (is_array($vocabulary)) {
    echo "✓ loadVocabulary() returns array (not null)\n";
} else {
    echo "✗ loadVocabulary() returned non-array\n";
}

echo "\n" . str_repeat("-", 50) . "\n";
echo "\nTesting getStats():\n";
echo str_repeat("-", 50) . "\n";

$service3 = new VocabularyService();
try {
    $stats = $service3->getStats();
    echo "Total words: {$stats['total_words']}\n";
    echo "Domain terms: {$stats['domain_terms']}\n";
    echo "Curated typos: {$stats['curated_typos']}\n";
    echo "✓ getStats() works without crash\n";
} catch (Throwable $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("-", 50) . "\n";
echo "\nTesting needsCorrection():\n";
echo str_repeat("-", 50) . "\n";

$service4 = new VocabularyService();
$testWords = ['virusss', 'virus', 'ransomwre', 'internet'];

foreach ($testWords as $word) {
    try {
        $needsCorrection = $service4->needsCorrection($word);
        echo "  '$word' needs correction: " . ($needsCorrection ? 'Yes' : 'No') . "\n";
    } catch (Throwable $e) {
        echo "  '$word' ✗ ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "All tests completed!\n";
echo "If no crashes occurred, the null vocabulary fix is working.\n";