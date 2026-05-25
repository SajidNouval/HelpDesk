<?php

/**
 * Test script to validate the ranking instability fix
 * 
 * This script tests the 6 parts of the fix:
 * 1. IT-specific stopwords with extremely low weight
 * 2. Important domain token boost
 * 3. Query coverage boost
 * 4. Exact phrase boost
 * 5. Negative domain penalty
 * 6. Debug validation logging
 * 
 * Expected Results:
 * - Query: "cara mengatasi komputer lemot" should return "komputer lemot" article, NOT BSOD article
 * - Query: "printer error" should return "printer troubleshooting" article, NOT BSOD article
 */

require __DIR__ . '/vendor/autoload.php';

use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\CosineSimilarityService;
use App\Services\Chatbot\DomainDetectionService;
use App\Services\Chatbot\ChatbotRetrievalService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "==========================================================\n";
echo "RANKING INSTABILITY FIX - VALIDATION TEST\n";
echo "==========================================================\n\n";

// Get the retrieval service
$retrievalService = app()->make(ChatbotRetrievalService::class);

// Test cases
$testCases = [
    [
        'query' => 'cara mengatasi komputer lemot',
        'expectedDomain' => 'komputer',
        'shouldNotReturn' => 'BSOD',
        'description' => 'Query with generic terms should still find the correct domain article',
    ],
    [
        'query' => 'printer error',
        'expectedDomain' => 'printer',
        'shouldNotReturn' => 'BSOD',
        'description' => 'Printer query should not return BSOD articles',
    ],
    [
        'query' => 'komputer lemot',
        'expectedDomain' => 'komputer',
        'shouldNotReturn' => 'BSOD',
        'description' => 'Direct query should find the correct article',
    ],
    [
        'query' => 'wifi tidak connect',
        'expectedDomain' => 'wifi',
        'shouldNotReturn' => 'BSOD',
        'description' => 'WiFi query should not return unrelated articles',
    ],
];

echo "Running test cases...\n\n";

foreach ($testCases as $i => $testCase) {
    echo "TEST " . ($i + 1) . ": {$testCase['description']}\n";
    echo "Query: \"{$testCase['query']}\"\n";
    echo "Expected domain: {$testCase['expectedDomain']}\n";
    echo "Should NOT contain: {$testCase['shouldNotReturn']}\n";
    echo "----------------------------------------------------------\n";
    
    $result = $retrievalService->retrieve($testCase['query'], 3);
    
    if (empty($result['results'])) {
        echo "❌ FAIL: No results found\n";
    } else {
        $topResult = $result['results'][0];
        echo "Top result: {$topResult['title']}\n";
        echo "Similarity: {$topResult['similarity']}\n";
        
        // Check if result contains the unexpected term
        $titleLower = mb_strtolower($topResult['title']);
        $unexpectedLower = mb_strtolower($testCase['shouldNotReturn']);
        
        if (str_contains($titleLower, $unexpectedLower)) {
            echo "❌ FAIL: Result contains unexpected term '{$testCase['shouldNotReturn']}'\n";
        } else {
            echo "✓ PASS: Result does not contain '{$testCase['shouldNotReturn']}'\n";
        }
        
        // Check if result matches expected domain
        $expectedLower = mb_strtolower($testCase['expectedDomain']);
        if (str_contains($titleLower, $expectedLower)) {
            echo "✓ PASS: Result contains expected domain '{$testCase['expectedDomain']}'\n";
        } else {
            echo "⚠ WARNING: Result does not contain expected domain '{$testCase['expectedDomain']}'\n";
        }
    }
    
    echo "\n";
}

// Test Part 1: IT-specific stopwords
echo "==========================================================\n";
echo "PART 1: IT-SPECIFIC STOPWORDS TEST\n";
echo "==========================================================\n";

$preprocessor = app()->make(PreprocessingService::class);
$genericTerms = $preprocessor->getITGenericTerms();

echo "IT Generic Terms: " . implode(', ', $genericTerms) . "\n";

foreach ($genericTerms as $term) {
    if ($preprocessor->isITGenericTerm($term)) {
        echo "✓ '$term' correctly identified as IT generic term\n";
    } else {
        echo "❌ '$term' NOT identified as IT generic term\n";
    }
}

echo "\n";

// Test Part 2: Important domain tokens
echo "==========================================================\n";
echo "PART 2: IMPORTANT DOMAIN TOKENS TEST\n";
echo "==========================================================\n";

$domainTokens = $preprocessor->getImportantDomainTokens();
echo "Important Domain Tokens: " . implode(', ', array_slice($domainTokens, 0, 10)) . "...\n";

$testDomainTokens = ['komputer', 'printer', 'wifi', 'internet', 'email', 'bsod'];
foreach ($testDomainTokens as $token) {
    if ($preprocessor->isImportantDomainToken($token)) {
        echo "✓ '$token' correctly identified as important domain token\n";
    } else {
        echo "❌ '$token' NOT identified as important domain token\n";
    }
}

echo "\n";

// Test Part 5: Domain penalty mappings
echo "==========================================================\n";
echo "PART 5: DOMAIN PENALTY MAPPINGS TEST\n";
echo "==========================================================\n";

$penaltyMappings = $preprocessor->getDomainPenaltyMappings();
echo "Domain Penalty Mappings:\n";
foreach ($penaltyMappings as $domain => $relatedTerms) {
    echo "  $domain => " . implode(', ', $relatedTerms) . "\n";
}

echo "\n";

// Test preprocessing with debug
echo "==========================================================\n";
echo "PART 6: DEBUG PREPROCESSING TEST\n";
echo "==========================================================\n";

$debugResult = $preprocessor->preprocessWithDebug('cara mengatasi komputer lemot', true);
echo "Query: 'cara mengatasi komputer lemot'\n";
echo "Tokens: " . implode(', ', $debugResult['tokens']) . "\n";
echo "Removed Stopwords: " . implode(', ', $debugResult['removed_stopwords']) . "\n";
echo "Generic Terms Found: " . implode(', ', $debugResult['generic_terms']) . "\n";
echo "Domain Tokens Found: " . implode(', ', $debugResult['domain_tokens']) . "\n";

echo "\n";
echo "==========================================================\n";
echo "ALL TESTS COMPLETED\n";
echo "==========================================================\n";