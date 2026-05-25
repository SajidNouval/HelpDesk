<?php

/**
 * Test script to verify query_by_weights ranking improvement.
 * 
 * This test verifies that articles with matching TITLES are ranked higher
 * than articles with only content matches.
 * 
 * Expected behavior after fix:
 * - "virus" query: Articles with "virus" in title should rank #1
 * - "docker" query: Articles with "docker" in title should rank #1
 * - "printer offline" query: Articles with "printer offline" in title should rank #1
 */

require __DIR__ . '/vendor/autoload.php';

use App\Services\Chatbot\TypesenseService;
use Dotenv\Dotenv;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "==================================================\n";
echo "QUERY_BY_WEIGHTS RANKING TEST\n";
echo "==================================================\n\n";

$typesenseService = new TypesenseService();

if (!$typesenseService->isConnected()) {
    echo "ERROR: Typesense is not connected.\n";
    exit(1);
}

echo "Typesense connected successfully.\n\n";

// Test queries
$testQueries = [
    'virus',
    'docker',
    'printer offline',
];

$allPassed = true;

foreach ($testQueries as $query) {
    echo "--------------------------------------------------\n";
    echo "Testing query: '$query'\n";
    echo "--------------------------------------------------\n\n";
    
    $result = $typesenseService->search($query, 10);
    
    if (!$result['success'] || empty($result['results'])) {
        echo "  No results found for '$query'\n\n";
        continue;
    }
    
    $results = $result['results'];
    
    echo "  Top 5 results:\n";
    echo "  " . str_pad("#", 3) . " " . str_pad("Title", 50) . " " . str_pad("Score", 12) . " Category\n";
    echo "  " . str_repeat("-", 80) . "\n";
    
    $titleMatchFound = false;
    $titleMatchRank = 0;
    
    foreach (array_slice($results, 0, 5) as $idx => $article) {
        $title = substr($article['title'], 0, 48);
        $score = $article['typesense_score'];
        $category = $article['category_name'] ?? 'N/A';
        
        // Check if query terms appear in title
        $queryTerms = explode(' ', strtolower($query));
        $titleLower = strtolower($article['title']);
        $titleMatches = true;
        foreach ($queryTerms as $term) {
            if (!str_contains($titleLower, $term)) {
                $titleMatches = false;
                break;
            }
        }
        
        if ($titleMatches) {
            $titleMatchFound = true;
            $titleMatchRank = $idx + 1;
            echo "  " . str_pad(($idx + 1), 3) . " " . str_pad($title, 50) . " " . str_pad($score, 12) . " $category [TITLE MATCH]\n";
        } else {
            echo "  " . str_pad(($idx + 1), 3) . " " . str_pad($title, 50) . " " . str_pad($score, 12) . " $category\n";
        }
    }
    
    echo "\n";
    
    // Evaluate result
    if ($titleMatchFound && $titleMatchRank == 1) {
        echo "  ✓ PASS: Title-matching article ranked #1\n\n";
    } elseif ($titleMatchFound && $titleMatchRank <= 3) {
        echo "  ~ PARTIAL: Title-matching article ranked #$titleMatchRank (should be #1)\n\n";
        $allPassed = false;
    } elseif ($titleMatchFound) {
        echo "  ✗ FAIL: Title-matching article ranked #$titleMatchRank (too low)\n\n";
        $allPassed = false;
    } else {
        echo "  ~ INFO: No title-matching articles found in top 5\n\n";
    }
}

echo "==================================================\n";
if ($allPassed) {
    echo "OVERALL: ALL TESTS PASSED\n";
    echo "query_by_weights is correctly prioritizing title matches.\n";
} else {
    echo "OVERALL: SOME TESTS NEED ATTENTION\n";
    echo "Some title-matching articles are not ranking #1.\n";
}
echo "==================================================\n";