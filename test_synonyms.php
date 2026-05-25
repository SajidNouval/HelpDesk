<?php

/**
 * Test script for Typesense Synonym functionality
 * 
 * Run with: php test_synonyms.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\Chatbot\TypesenseService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========================================\n";
echo "Typesense Synonym Test\n";
echo "========================================\n\n";

// Initialize the service
$typesenseService = new TypesenseService();

if (!$typesenseService->isConnected()) {
    echo "❌ Cannot connect to Typesense server.\n";
    exit(1);
}

echo "✅ Connected to Typesense server!\n\n";

// Test 1: Show intent synonym sets
echo "----------------------------------------\n";
echo "Test 1: Intent Synonym Sets Definition\n";
echo "----------------------------------------\n\n";

$synonymSets = $typesenseService->getIntentSynonymSets();
foreach ($synonymSets as $intent => $terms) {
    echo "📌 {$intent}:\n";
    echo "   " . implode(', ', $terms) . "\n\n";
}

// Test 2: Create all synonyms
echo "----------------------------------------\n";
echo "Test 2: Creating Synonym Sets in Typesense\n";
echo "----------------------------------------\n\n";

$result = $typesenseService->createAllSynonyms();
if ($result['success']) {
    echo "✅ Created {$result['created']} synonym sets\n";
} else {
    echo "❌ Failed to create some synonym sets\n";
    if (!empty($result['details'])) {
        foreach ($result['details'] as $detail) {
            echo "   - {$detail['intent']}: {$detail['error']}\n";
        }
    }
}
echo "\n";

// Test 3: Retrieve all synonyms
echo "----------------------------------------\n";
echo "Test 3: Retrieving All Synonyms from Typesense\n";
echo "----------------------------------------\n\n";

$allSynonyms = $typesenseService->getAllSynonyms();
echo "Found " . count($allSynonyms) . " synonym sets:\n";
foreach ($allSynonyms as $synonym) {
    echo "📌 {$synonym['id']}: " . implode(', ', $synonym['synonyms']) . "\n";
}
echo "\n";

// Test 4: Match synonym intents
echo "----------------------------------------\n";
echo "Test 4: Match Query Against Synonym Intents\n";
echo "----------------------------------------\n\n";

$testQueries = [
    'wifi gagal konek',
    'internet tidak connect',
    'printer ga bisa print',
    'virus malware trojan',
    'login gagal masuk akun',
    'email tidak bisa kirim',
    'internet lambat buffering',
];

foreach ($testQueries as $query) {
    echo "🔍 Query: \"{$query}\"\n";
    $matched = $typesenseService->matchSynonymIntents($query);
    if (!empty($matched)) {
        foreach ($matched as $intent => $data) {
            echo "   ✅ Matched intent: {$intent}\n";
            echo "      Terms found: " . implode(', ', $data['matched_terms']) . "\n";
        }
    } else {
        echo "   ❌ No synonym matches\n";
    }
    echo "\n";
}

// Test 5: Search with synonyms (test the actual search)
echo "----------------------------------------\n";
echo "Test 5: Search with Synonym Expansion\n";
echo "----------------------------------------\n\n";

$testSearchQueries = [
    'wifi gagal konek',
    'internet tidak connect',
    'printer ga bisa print',
];

foreach ($testSearchQueries as $query) {
    echo "🔍 Search: \"{$query}\"\n";
    $result = $typesenseService->search($query, 3);
    if ($result['success'] && !empty($result['results'])) {
        echo "   Found " . count($result['results']) . " results:\n";
        foreach ($result['results'] as $i => $article) {
            echo "   " . ($i + 1) . ". {$article['title']} (score: {$article['typesense_score']})\n";
        }
    } else {
        echo "   No results found\n";
    }
    echo "\n";
}

echo "========================================\n";
echo "Test Complete!\n";
echo "========================================\n";