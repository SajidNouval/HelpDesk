<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(\App\Services\Chatbot\ChatbotRetrievalService::class);

echo "========================================\n";
echo "DETAILED TYPO QUERY TEST\n";
echo "========================================\n\n";

// Test typo queries that should return security articles
$queries = ['viruss', 'ransomwre', 'malwere', 'trojon', 'pritner', 'kompter'];

foreach ($queries as $q) {
    echo "Query: '$q'\n";
    echo str_repeat('-', 60) . "\n";
    
    $result = $service->retrieve($q, 5);
    
    if (!empty($result['results'])) {
        echo "Results:\n";
        foreach ($result['results'] as $i => $r) {
            echo "  " . ($i+1) . ". " . $r['title'] . " (Score: " . $r['similarity'] . ")\n";
        }
    } else {
        echo "  No results found\n";
    }
    
    // Show detailed debug info
    if (isset($result['debug'])) {
        $debug = $result['debug'];
        echo "\nDebug Information:\n";
        echo "  - Original Query: " . ($debug['original_query'] ?? 'N/A') . "\n";
        echo "  - Normalized Query: " . ($debug['normalized_query'] ?? 'N/A') . "\n";
        echo "  - Typo Corrections: " . json_encode($debug['typo_corrections'] ?? []) . "\n";
        echo "  - Typo Detected: " . (isset($debug['typo_detection']) ? ($debug['typo_detection']['is_typo'] ? 'YES' : 'NO') : 'N/A') . "\n";
        
        if (isset($debug['typo_detection'])) {
            echo "  - Typo Confidence: " . ($debug['typo_detection']['confidence'] ?? 0) . "\n";
            echo "  - Typo Reason: " . ($debug['typo_detection']['reason'] ?? 'N/A') . "\n";
        }
        
        echo "  - Scoring Mode: " . ($debug['scoring_mode'] ?? 'N/A') . "\n";
        echo "  - Typesense Used: " . ($result['typesense_used'] ? 'YES' : 'NO') . "\n";
        echo "  - Typesense Candidates: " . ($result['typesense_candidates'] ?? 0) . "\n";
        echo "  - Domain Detected: " . ($result['detected_domain'] ?? 'none') . "\n";
        
        // Show document-level scores if available
        if (isset($debug['doc_scores'])) {
            echo "\n  Document Scores (top 5):\n";
            $count = 0;
            foreach ($debug['doc_scores'] as $docId => $scores) {
                if ($count >= 5) break;
                echo "    - Doc $docId: " . ($scores['title'] ?? 'Unknown') . "\n";
                echo "      Base: " . ($scores['base_similarity'] ?? 0) . ", ";
                echo "      Final: " . ($scores['final_score'] ?? 0) . "\n";
                if (isset($scores['security_matches'])) {
                    echo "      Security matches: " . implode(', ', $scores['security_matches']) . "\n";
                }
                $count++;
            }
        }
        
        // Show security boost info
        if (isset($debug['security_boost_applied'])) {
            echo "\n  Security Boost Applied:\n";
            foreach ($debug['security_boost_applied'] as $docId => $info) {
                echo "    - Doc $docId: " . ($info['title'] ?? 'Unknown') . "\n";
                echo "      Original: " . $info['original_score'] . " -> Boosted: " . $info['boosted_score'] . "\n";
            }
        }
        
        // Show generic penalty info
        if (isset($debug['generic_penalty_applied'])) {
            echo "\n  Generic Penalty Applied:\n";
            foreach ($debug['generic_penalty_applied'] as $docId => $info) {
                echo "    - Doc $docId: " . ($info['title'] ?? 'Unknown') . "\n";
                echo "      Original: " . $info['original_score'] . " -> Penalized: " . $info['penalized_score'] . "\n";
                echo "      Reason: " . ($info['reason'] ?? 'N/A') . "\n";
            }
        }
    }
    
    echo "\n\n";
}

echo "========================================\n";
echo "TEST COMPLETE\n";
echo "========================================\n";