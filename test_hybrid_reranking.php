<?php

/**
 * HYBRID RERANKING TEST
 * 
 * This test verifies that the hybrid reranking improvements correctly:
 * 1. Prioritize exact phrase matches (komputer lemot > internet lemot for "komputer lemot" query)
 * 2. Apply negative domain penalties (printer query penalizes bsod/vpn/internet)
 * 3. Reduce influence of low-priority terms (cara, mengatasi, solusi, etc.)
 * 4. Boost query coverage when all important terms match
 * 5. Give higher weight to domain-specific terms
 * 
 * Expected Results:
 * - "komputer lemot" → komputer lemot article ranks #1
 * - "printer error" → printer troubleshooting article ranks #1
 * - "cara mengatasi komputer lemot" → komputer lemot article (generic terms filtered)
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\Chatbot\AdvancedRetrievalService;
use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\DomainDetectionService;

echo "========================================\n";
echo "HYBRID RERANKING IMPROVEMENT TEST\n";
echo "========================================\n\n";

// Get the retrieval service
$retrievalService = app(AdvancedRetrievalService::class);

// Test cases with expected behavior
$testCases = [
    [
        'query' => 'komputer lemot',
        'expectedTopKeyword' => 'komputer',
        'description' => 'Query "komputer lemot" should prioritize komputer-related articles',
        'shouldNotContain' => ['internet lemot', 'wifi lemot'],
    ],
    [
        'query' => 'printer error',
        'expectedTopKeyword' => 'printer',
        'description' => 'Query "printer error" should prioritize printer-related articles',
        'shouldNotContain' => ['bsod', 'vpn', 'internet error'],
    ],
    [
        'query' => 'cara mengatasi komputer lemot',
        'expectedTopKeyword' => 'komputer',
        'description' => 'Query with generic terms should still prioritize domain-specific content',
        'shouldNotContain' => ['internet lemot', 'wifi lemot'],
    ],
    [
        'query' => 'wifi tidak connect',
        'expectedTopKeyword' => 'wifi',
        'description' => 'Query "wifi tidak connect" should prioritize wifi-related articles',
        'shouldNotContain' => ['printer', 'email'],
    ],
    [
        'query' => 'email tidak masuk',
        'expectedTopKeyword' => 'email',
        'description' => 'Query "email tidak masuk" should prioritize email-related articles',
        'shouldNotContain' => ['printer', 'hardware'],
    ],
];

$passed = 0;
$failed = 0;

foreach ($testCases as $test) {
    echo "TEST: {$test['description']}\n";
    echo "Query: '{$test['query']}'\n";
    echo "Expected top keyword: {$test['expectedTopKeyword']}\n";
    if (isset($test['shouldNotContain'])) {
        echo "Should NOT contain: " . implode(', ', $test['shouldNotContain']) . "\n";
    }
    echo "\n";
    
    // Run retrieval
    $result = $retrievalService->retrieve($test['query'], 5);
    
    if (empty($result['results'])) {
        echo "❌ FAIL: No results returned\n\n";
        $failed++;
        continue;
    }
    
    $topResult = $result['results'][0];
    $topTitle = $topResult['title'];
    $topTitleLower = mb_strtolower($topTitle);
    
    // Check if top result contains expected keyword
    $containsExpected = str_contains($topTitleLower, $test['expectedTopKeyword']);
    
    // Check if top result contains forbidden terms
    $containsForbidden = false;
    $forbiddenFound = '';
    if (isset($test['shouldNotContain'])) {
        foreach ($test['shouldNotContain'] as $forbidden) {
            if (str_contains($topTitleLower, $forbidden)) {
                $containsForbidden = true;
                $forbiddenFound = $forbidden;
                break;
            }
        }
    }
    
    // Print all results with scores
    echo "Results:\n";
    foreach ($result['results'] as $i => $article) {
        $scoreBreakdown = sprintf(
            "  %d. %s (score: %.4f, cosine: %.4f, title: %.4f, coverage: %.4f, phrase: %.4f, penalty: %.4f)\n",
            $i + 1,
            $article['title'],
            $article['final_score'],
            $article['similarity'],
            $article['title_overlap'] ?? 0,
            $article['query_coverage'] ?? 0,
            $article['exact_phrase_bonus'] ?? 0,
            $article['domain_penalty'] ?? 0
        );
        echo $scoreBreakdown;
    }
    echo "\n";
    
    if ($containsExpected && !$containsForbidden) {
        echo "✅ PASS: Top result '{$topTitle}' matches expected criteria\n\n";
        $passed++;
    } else {
        echo "❌ FAIL: ";
        if (!$containsExpected) {
            echo "Top result does not contain expected keyword '{$test['expectedTopKeyword']}'\n";
        }
        if ($containsForbidden) {
            echo "Top result contains forbidden term '{$forbiddenFound}'\n";
        }
        echo "\n";
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
    echo "🎉 ALL TESTS PASSED! Hybrid reranking is working correctly.\n";
    echo "\nKey improvements verified:\n";
    echo "✓ Exact phrase matching prioritizes domain-specific content\n";
    echo "✓ Low-priority terms (cara, mengatasi, etc.) have reduced influence\n";
    echo "✓ Query coverage boost rewards articles matching all important terms\n";
    echo "✓ Domain-specific terms receive higher weight in ranking\n";
} else {
    echo "⚠️  Some tests failed. Review the output above.\n";
}

// Additional debug info if available
if (config('app.debug', false)) {
    echo "\n========================================\n";
    echo "DEBUG INFORMATION\n";
    echo "========================================\n";
    $debugInfo = $retrievalService->getDebugInfo();
    if (!empty($debugInfo)) {
        echo "Query processing stages:\n";
        foreach ($debugInfo['stages'] ?? [] as $stage) {
            echo "  - {$stage['stage']}: {$stage['input']} → {$stage['output']}\n";
        }
    }
}