<?php

/**
 * Test script to verify Typesense fallback fix for typo queries
 * 
 * Tests:
 * - VIRUSS → Should return Virus article (NOT WiFi Tidak Terhubung)
 * - RANSOMWRE → Should return Ransomware article (NOT WiFi Tidak Terhubung)
 */

require __DIR__ . '/vendor/autoload.php';

use App\Services\Chatbot\ChatbotRetrievalService;
use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\CosineSimilarityService;
use App\Services\Chatbot\DomainDetectionService;
use App\Services\Chatbot\TypesenseService;
use Illuminate\Container\Container;
use Illuminate\Config\Repository;

// Bootstrap Laravel container
$app = new Container();
$app->instance('app', $app);
$app->instance('config', new Repository([
    'app' => ['debug' => true],
    'typesense' => [
        'enabled' => true,
        'url' => 'http://localhost:8108',
        'api_key' => env('TYPESENSE_API_KEY', 'xyz'),
        'collection' => 'articles',
    ],
]));

// Create services
$preprocessor = new PreprocessingService();
$tfidfService = new TfidfService($preprocessor);
$similarityService = new CosineSimilarityService();
$domainDetector = new DomainDetectionService($preprocessor);
$typesenseService = new TypesenseService();

$retrievalService = new ChatbotRetrievalService(
    $preprocessor,
    $tfidfService,
    $similarityService,
    $domainDetector,
    $typesenseService
);

echo "========================================\n";
echo "TYPO FALLBACK FIX TEST\n";
echo "========================================\n\n";

$testQueries = [
    'VIRUSS' => 'virus',
    'RANSOMWRE' => 'ransomware',
];

foreach ($testQueries as $query => $expectedKeyword) {
    echo "Testing query: '$query'\n";
    echo str_repeat('-', 40) . "\n";
    
    $result = $retrievalService->retrieve($query, 3);
    
    echo "Original Query: {$result['query']}\n";
    echo "Normalized Query: {$result['normalized_query']}\n";
    echo "Typesense Used: " . ($result['typesense_used'] ? 'Yes' : 'No') . "\n";
    echo "Typesense Candidates: {$result['typesense_candidates']}\n";
    echo "Typesense Fallback Applied: " . ($result['typesense_fallback_applied'] ? 'Yes' : 'No') . "\n";
    echo "Threshold Met: " . ($result['threshold_met'] ? 'Yes' : 'No') . "\n";
    echo "Max Similarity: {$result['max_similarity']}\n";
    echo "Total Results: {$result['total']}\n\n";
    
    if (!empty($result['results'])) {
        echo "Top Results:\n";
        foreach ($result['results'] as $i => $article) {
            echo "  " . ($i + 1) . ". {$article['title']} (Similarity: {$article['similarity']})\n";
            if (isset($article['is_typesense_fallback']) && $article['is_typesense_fallback']) {
                echo "     [TYPESENSE FALLBACK]\n";
            }
        }
    } else {
        echo "No results found!\n";
    }
    
    // Check if result contains expected keyword
    $topTitle = isset($result['results'][0]['title']) ? strtolower($result['results'][0]['title']) : '';
    $containsExpected = str_contains($topTitle, $expectedKeyword);
    
    echo "\n";
    if ($containsExpected) {
        echo "✅ PASS: Result contains '$expectedKeyword'\n";
    } else {
        echo "❌ FAIL: Result does NOT contain '$expectedKeyword'\n";
        echo "   Top result: " . ($result['results'][0]['title'] ?? 'NONE') . "\n";
    }
    
    // Show debug info if available
    if (!empty($result['debug'])) {
        echo "\nDebug Info:\n";
        if (isset($result['debug']['typo_detection'])) {
            echo "  Typo Detection: " . json_encode($result['debug']['typo_detection']) . "\n";
        }
        if (isset($result['debug']['typo_detection_checks'])) {
            echo "  Typo Detection Checks:\n";
            foreach ($result['debug']['typo_detection_checks'] as $check => $value) {
                echo "    - $check: " . ($value ? 'true' : 'false') . "\n";
            }
        }
        if (isset($result['debug']['scoring_mode'])) {
            echo "  Scoring Mode: {$result['debug']['scoring_mode']}\n";
        }
    }
    
    echo "\n" . str_repeat('=', 50) . "\n\n";
}

echo "Test completed.\n";