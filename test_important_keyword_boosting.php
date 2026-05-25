<?php

/**
 * Test script for IMPORTANT KEYWORD BOOSTING
 * 
 * This script tests that queries containing important keywords like "virus", "docker", "printer"
 * correctly return domain-specific articles instead of generic articles like "PC lemot" or "WiFi".
 * 
 * Expected behavior:
 * - "pc ku kena virus" → should return Virus article, NOT PC lemot
 * - "docker di laptop error" → should return Docker article, NOT generic
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\CosineSimilarityService;
use App\Services\Chatbot\DomainDetectionService;
use App\Services\Chatbot\TypesenseService;
use App\Services\Chatbot\ChatbotRetrievalService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========================================\n";
echo "IMPORTANT KEYWORD BOOSTING TEST\n";
echo "========================================\n\n";

// Get the retrieval service
$retrievalService = app(ChatbotRetrievalService::class);

// Test cases
$testCases = [
    [
        'query' => 'pc ku kena virus',
        'expected_keyword' => 'virus',
        'description' => 'Query with "virus" should return virus-related article',
    ],
    [
        'query' => 'docker di laptop error',
        'expected_keyword' => 'docker',
        'description' => 'Query with "docker" should return docker-related article',
    ],
    [
        'query' => 'printer tidak bisa ngeprint',
        'expected_keyword' => 'printer',
        'description' => 'Query with "printer" should return printer-related article',
    ],
    [
        'query' => 'wifi lemot banget',
        'expected_keyword' => 'wifi',
        'description' => 'Query with "wifi" should return wifi-related article',
    ],
    [
        'query' => 'database mysql error',
        'expected_keyword' => 'database',
        'description' => 'Query with "database" should return database-related article',
    ],
];

echo "Running tests...\n\n";

foreach ($testCases as $test) {
    echo "----------------------------------------\n";
    echo "TEST: {$test['description']}\n";
    echo "QUERY: \"{$test['query']}\"\n";
    echo "EXPECTED KEYWORD: {$test['expected_keyword']}\n\n";
    
    $result = $retrievalService->retrieve($test['query'], 3);
    
    if (empty($result['results'])) {
        echo "❌ FAIL: No results found\n";
        continue;
    }
    
    $topResult = $result['results'][0];
    echo "TOP RESULT:\n";
    echo "  Title: " . $topResult['title'] . "\n";
    echo "  Similarity: " . $topResult['similarity'] . "\n";
    echo "  Category: " . ($topResult['category_name'] ?? 'N/A') . "\n";
    
    // Check if the top result contains the expected keyword
    $titleLower = mb_strtolower($topResult['title']);
    $keywordLower = mb_strtolower($test['expected_keyword']);
    
    if (str_contains($titleLower, $keywordLower)) {
        echo "✅ PASS: Top result contains keyword '{$test['expected_keyword']}'\n";
    } else {
        echo "⚠️  CHECK: Top result doesn't contain keyword in title\n";
        // Check debug info for keyword boost
        if (isset($result['debug']['important_keyword_matches'])) {
            echo "  Important keyword matches:\n";
            foreach ($result['debug']['important_keyword_matches'] as $docTitle => $matchInfo) {
                echo "    - {$docTitle}: " . implode(', ', $matchInfo['keywords']) . " (boost: {$matchInfo['total_boost']})\n";
            }
        }
    }
    
    echo "\n";
}

echo "========================================\n";
echo "TEST COMPLETE\n";
echo "========================================\n";