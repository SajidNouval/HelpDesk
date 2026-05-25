<?php

/**
 * Test script to verify Typesense security article ranking
 * 
 * This test verifies that security-related queries like "VIRUSS" and "RANSOMWRE"
 * return security articles (Virus, Ransomware) BEFORE generic WiFi articles.
 * 
 * Run with: php test_typesense_security_ranking.php
 */

require_once 'vendor/autoload.php';

use App\Services\Chatbot\TypesenseService;
use Illuminate\Support\Facades\Log;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "==========================================================\n";
echo "TYPESENSE SECURITY RANKING TEST\n";
echo "==========================================================\n\n";

// Test queries with typos
$testQueries = [
    'viruss',      // Typo for "virus"
    'ransomwre',   // Typo for "ransomware"
    'malware',     // Correct spelling
    'trojan',      // Security keyword
    'virus',       // Correct spelling
    'ransomware',  // Correct spelling
];

$typesenseService = new TypesenseService();

if (!$typesenseService->isConnected()) {
    echo "❌ ERROR: Typesense is not connected!\n";
    exit(1);
}

echo "✅ Typesense connected successfully\n\n";

// Security categories we expect
$securityCategories = [
    'Keamanan Sistem',
    'Security',
    'Malware',
    'Virus',
    'Ransomware',
];

// Non-security categories (should NOT appear first for security queries)
$nonSecurityCategories = [
    'Jaringan & Konektivitas',
    'WiFi',
    'Network',
    'Internet',
];

foreach ($testQueries as $query) {
    echo "----------------------------------------------------------\n";
    echo "Testing query: '$query'\n";
    echo "----------------------------------------------------------\n";
    
    $result = $typesenseService->search($query, 10);
    
    if (!$result['success']) {
        echo "❌ Search failed: {$result['message']}\n\n";
        continue;
    }
    
    $results = $result['results'];
    $debug = $result['debug'];
    
    echo "Total found: {$result['total']}\n";
    echo "Security boost applied: " . ($debug['security_boost_applied'] ? 'YES' : 'NO') . "\n";
    echo "Is security query: " . ($debug['is_security_query'] ? 'YES' : 'NO') . "\n\n";
    
    if (empty($results)) {
        echo "⚠️  No results found!\n\n";
        continue;
    }
    
    // Show top 5 results
    echo "TOP RESULTS (from Typesense, before TF-IDF):\n";
    echo str_pad('#', 4, ' ') . str_pad('Title', 50, ' ') . str_pad('Category', 25, ' ') . "Score\n";
    echo str_repeat('-', 100) . "\n";
    
    $hasSecurityFirst = false;
    $firstResultIsSecurity = false;
    
    foreach (array_slice($results, 0, 5) as $idx => $article) {
        $rank = $idx + 1;
        $title = substr($article['title'], 0, 48);
        $category = substr($article['category_name'] ?? '', 0, 23);
        $score = $article['typesense_score'];
        
        $isSecurityCategory = in_array($article['category_name'] ?? '', $securityCategories);
        $isNonSecurityCategory = in_array($article['category_name'] ?? '', $nonSecurityCategories);
        
        $marker = '';
        if ($rank === 1) {
            $firstResultIsSecurity = $isSecurityCategory;
            if ($isSecurityCategory) {
                $marker = ' ✅';
                $hasSecurityFirst = true;
            } else if ($isNonSecurityCategory) {
                $marker = ' ❌ WRONG!';
            }
        } else if ($isSecurityCategory) {
            $marker = ' ✅';
        }
        
        echo str_pad($rank, 3, ' ') . str_pad($title, 52, ' ') . str_pad($category, 26, ' ') . $score . $marker . "\n";
    }
    
    echo "\n";
    
    // Verdict
    if ($debug['is_security_query']) {
        if ($firstResultIsSecurity) {
            echo "✅ PASS: Security article ranked #1 for query '$query'\n";
        } else {
            echo "❌ FAIL: Non-security article ranked #1 for security query '$query'\n";
            echo "   Expected: Virus/Ransomware/Malware article\n";
            echo "   Got: {$results[0]['title']} (Category: {$results[0]['category_name']})\n";
        }
    } else {
        echo "ℹ️  INFO: Not detected as security query, no ranking requirements\n";
    }
    
    echo "\n";
}

echo "==========================================================\n";
echo "RAW DEBUG INFO FOR FIRST QUERY\n";
echo "==========================================================\n\n";

// Show detailed debug info for first query
$firstQuery = $testQueries[0];
$result = $typesenseService->search($firstQuery, 10);
$debug = $result['debug'];

echo "Query: '$firstQuery'\n";
echo "Security boost applied: " . ($debug['security_boost_applied'] ? 'YES' : 'NO') . "\n\n";

if (!empty($debug['raw_hits_before_tfidf'])) {
    echo "Raw hits from Typesense (before any TF-IDF reranking):\n";
    foreach ($debug['raw_hits_before_tfidf'] as $hit) {
        echo "  Rank #{$hit['rank']}: {$hit['title']}\n";
        echo "    Category: {$hit['category_name']}\n";
        echo "    Score: {$hit['typesense_score']}\n\n";
    }
}

echo "==========================================================\n";
echo "TEST COMPLETE\n";
echo "==========================================================\n";