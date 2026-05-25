<?php

/**
 * Test Typesense Hybrid Retrieval
 * 
 * This script tests the hybrid retrieval pipeline to verify:
 * 1. Typesense fuzzy search is working with typo tolerance
 * 2. TF-IDF reranking is applied to Typesense candidates
 * 3. Domain filtering is not blocking valid results
 */

require __DIR__.'/vendor/autoload.php';

use App\Services\Chatbot\ChatbotRetrievalService;
use App\Services\Chatbot\TypesenseService;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "=== TYPESENSE HYBRID RETRIEVAL TEST ===\n\n";

// Test queries with typos
$testQueries = [
    'ransomwre',      // Should find ransomware articles
    'viruss',         // Should find virus/security articles
    'pritner',        // Should find printer articles
    'kompter lemot',  // Should find komputer lemot articles
    'wfi lemot',      // Should find wifi lemot articles
];

$typesenseService = app(TypesenseService::class);
$retrievalService = app(ChatbotRetrievalService::class);

echo "Typesense Connected: " . ($typesenseService->isConnected() ? "YES" : "NO") . "\n\n";

foreach ($testQueries as $query) {
    echo "----------------------------------------\n";
    echo "Query: '$query'\n";
    echo "----------------------------------------\n";
    
    // Test Typesense search directly
    $typesenseResult = $typesenseService->search($query, 10);
    
    echo "Typesense Results:\n";
    if ($typesenseResult['success'] && !empty($typesenseResult['results'])) {
        foreach ($typesenseResult['results'] as $i => $result) {
            echo "  " . ($i + 1) . ". [ID: {$result['id']}] {$result['title']} (Score: {$result['typesense_score']})\n";
        }
    } else {
        echo "  No results found\n";
    }
    
    if (!empty($typesenseResult['debug']['typo_corrections'])) {
        echo "  Typo Correction: {$typesenseResult['debug']['typo_corrections']['original']} → {$typesenseResult['debug']['typo_corrections']['corrected']}\n";
    }
    
    echo "\n";
    
    // Test full hybrid retrieval
    $retrievalResult = $retrievalService->retrieve($query, 5);
    
    echo "Hybrid Retrieval Results:\n";
    echo "  Typesense Used: " . ($retrievalResult['typesense_used'] ?? 'N/A') . "\n";
    echo "  Typesense Candidates: " . ($retrievalResult['typesense_candidates'] ?? 0) . "\n";
    echo "  Final Results: " . ($retrievalResult['total'] ?? 0) . "\n";
    echo "  Threshold Met: " . ($retrievalResult['threshold_met'] ?? false ? "YES" : "NO") . "\n";
    
    if (!empty($retrievalResult['results'])) {
        foreach ($retrievalResult['results'] as $i => $result) {
            echo "  " . ($i + 1) . ". [ID: {$result['id']}] {$result['title']} (Similarity: {$result['similarity']})\n";
        }
    } else {
        echo "  No results found\n";
    }
    
    // Show debug info if available
    if (!empty($retrievalResult['debug'])) {
        echo "\n  Debug Info:\n";
        if (isset($retrievalResult['debug']['retrieval_method'])) {
            echo "    Retrieval Method: {$retrievalResult['debug']['retrieval_method']}\n";
        }
        if (isset($retrievalResult['debug']['detected_domain'])) {
            echo "    Detected Domain: {$retrievalResult['debug']['detected_domain']}\n";
        }
        if (isset($retrievalResult['debug']['hard_filter_fallback_applied'])) {
            echo "    Hard Filter Fallback: YES\n";
        }
        if (isset($retrievalResult['debug']['filtered_articles']) && !empty($retrievalResult['debug']['filtered_articles'])) {
            echo "    Filtered Articles:\n";
            foreach ($retrievalResult['debug']['filtered_articles'] as $id => $info) {
                echo "      - Article $id: {$info['title']} (Reason: {$info['reason']})\n";
            }
        }
    }
    
    echo "\n";
}

echo "\n=== TEST COMPLETE ===\n";