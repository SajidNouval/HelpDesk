<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$service = app(\App\Services\Chatbot\ChatbotRetrievalService::class);

echo "========================================\n";
echo "TYPO QUERY HANDLING TEST\n";
echo "========================================\n\n";

// Test typo queries
$queries = ['ransomwre', 'viruss', 'malwere', 'trojon'];

foreach ($queries as $q) {
    echo "Query: '$q'\n";
    echo str_repeat('-', 40) . "\n";
    
    $result = $service->retrieve($q, 3);
    
    if (!empty($result['results'])) {
        foreach ($result['results'] as $i => $r) {
            echo "  " . ($i+1) . ". " . $r['title'] . " (Score: " . $r['similarity'] . ")\n";
        }
    } else {
        echo "  No results\n";
    }
    
    if (isset($result['debug']['typo_detection'])) {
        $t = $result['debug']['typo_detection'];
        echo "  Typo: " . ($t['is_typo'] ? 'YES' : 'NO') . " | Mode: " . ($result['debug']['scoring_mode'] ?? 'N/A') . "\n";
    }
    echo "\n";
}

echo "TEST COMPLETE\n";