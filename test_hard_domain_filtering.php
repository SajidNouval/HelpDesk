<?php

/**
 * HARD DOMAIN-FIRST FILTERING TEST
 * 
 * This test verifies that the hard domain-first filtering correctly:
 * 1. Filters out forbidden domain articles BEFORE TF-IDF ranking
 * 2. Only allows articles from allowed domains to compete
 * 3. Prevents cross-domain retrieval instability
 * 
 * Expected Results:
 * - "wifi lemot" → wifi/internet/jaringan articles ONLY
 * - "printer error" → printer/hardware articles ONLY
 * - "ransomware" → security articles ONLY (NOT komputer lemot)
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\Chatbot\ChatbotRetrievalService;
use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\CosineSimilarityService;
use App\Services\Chatbot\DomainDetectionService;
use App\Models\Article;
use App\Models\Category;

echo "========================================\n";
echo "HARD DOMAIN-FIRST FILTERING TEST\n";
echo "========================================\n\n";

// Get the retrieval service
$retrievalService = app(ChatbotRetrievalService::class);

// Test cases
$testCases = [
    [
        'query' => 'wifi lemot',
        'expected_domain' => 'wifi',
        'forbidden_domains' => ['printer', 'komputer', 'bsod', 'hardware'],
        'description' => 'WiFi query should NOT return printer/komputer articles',
    ],
    [
        'query' => 'printer error',
        'expected_domain' => 'printer',
        'forbidden_domains' => ['wifi', 'internet', 'jaringan', 'bsod'],
        'description' => 'Printer query should NOT return wifi/internet articles',
    ],
    [
        'query' => 'ransomware',
        'expected_domain' => 'security',
        'forbidden_domains' => ['komputer', 'printer', 'wifi', 'internet'],
        'description' => 'Security query should NOT return komputer/wifi articles',
    ],
    [
        'query' => 'internet lambat',
        'expected_domain' => 'internet',
        'forbidden_domains' => ['printer', 'komputer', 'bsod'],
        'description' => 'Internet query should NOT return printer/komputer articles',
    ],
];

$passed = 0;
$failed = 0;

foreach ($testCases as $test) {
    echo "TEST: {$test['description']}\n";
    echo "Query: '{$test['query']}'\n";
    echo "Expected domain: {$test['expected_domain']}\n";
    echo "Forbidden domains: " . implode(', ', $test['forbidden_domains']) . "\n\n";
    
    // Run retrieval
    $result = $retrievalService->retrieve($test['query'], 5);
    
    if (empty($result['results'])) {
        echo "❌ FAIL: No results returned\n\n";
        $failed++;
        continue;
    }
    
    // Check each result
    $testPassed = true;
    foreach ($result['results'] as $article) {
        $title = $article['title'];
        $titleLower = mb_strtolower($title);
        
        // Check if any forbidden domain appears in the title
        foreach ($test['forbidden_domains'] as $forbidden) {
            if (str_contains($titleLower, $forbidden)) {
                echo "❌ FAIL: Found forbidden domain '{$forbidden}' in result: '{$title}'\n";
                $testPassed = false;
            }
        }
        
        echo "  Result: {$title} (similarity: {$article['similarity']})\n";
    }
    
    if ($testPassed) {
        echo "✅ PASS\n\n";
        $passed++;
    } else {
        echo "❌ TEST FAILED\n\n";
        $failed++;
    }
}

echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Passed: {$passed}\n";
echo "Failed: {$failed}\n";
echo "Total:  " . ($passed + $failed) . "\n\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED! Hard domain-first filtering is working correctly.\n";
} else {
    echo "⚠️  Some tests failed. Review the output above.\n";
}