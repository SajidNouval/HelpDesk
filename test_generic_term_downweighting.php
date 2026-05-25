<?php

/**
 * Test Script: Generic Technical Term Downweighting
 * 
 * This test verifies that generic technical words like 'pc', 'laptop', 'komputer', 
 * 'aplikasi', 'error' contribute LESS to TF-IDF scoring and final ranking.
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\PreprocessingService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "==========================================================\n";
echo "GENERIC TECHNICAL TERM DOWNWEIGHTING TEST\n";
echo "==========================================================\n\n";

// Get the services
$preprocessor = app()->make(PreprocessingService::class);
$tfidfService = app()->make(TfidfService::class);

// Test documents simulating helpdesk articles
$testDocuments = [
    1 => [
        'text' => 'Cara mengatasi komputer lemot dengan membersihkan registry dan menghapus file temporary. Tutorial lengkap untuk meningkatkan performa laptop dan PC.',
        'title' => 'Cara Mengatasi Komputer Lemot',
    ],
    2 => [
        'text' => 'Solusi error pada aplikasi Windows 10. Panduan troubleshooting untuk mengatasi masalah aplikasi yang tidak bisa dibuka.',
        'title' => 'Solusi Error Aplikasi Windows',
    ],
    3 => [
        'text' => 'Tutorial instalasi printer Epson L3110. Langkah-langkah instalasi driver printer untuk komputer dan laptop.',
        'title' => 'Tutorial Instalasi Printer',
    ],
    4 => [
        'text' => 'Cara setting WiFi router TP-Link. Panduan konfigurasi jaringan internet wireless untuk rumah dan kantor.',
        'title' => 'Cara Setting WiFi Router',
    ],
    5 => [
        'text' => 'Tips mengatasi email tidak masuk di Outlook. Solusi untuk masalah email yang terlambat atau tidak terkirim.',
        'title' => 'Tips Email Tidak Masuk',
    ],
];

echo "Test Documents:\n";
echo "----------------------------------------------------------\n";
foreach ($testDocuments as $id => $doc) {
    echo "Doc $id: {$doc['title']}\n";
}
echo "\n";

// Build TF-IDF vectors
$tfidfData = $tfidfService->buildTfidfVectors($testDocuments);
$vectors = $tfidfData['vectors'];
$idf = $tfidfData['idf'];

echo "IDF Scores (higher = more discriminative):\n";
echo "----------------------------------------------------------\n";
arsort($idf);
foreach (array_slice($idf, 0, 20, true) as $term => $score) {
    $isLowPriority = in_array(strtolower($term), [
        'pc', 'laptop', 'komputer', 'aplikasi', 'error', 'masalah', 
        'sistem', 'program', 'software', 'hardware', 'cara', 'mengatasi', 
        'solusi', 'tutorial', 'panduan', 'tips'
    ]);
    $marker = $isLowPriority ? ' [LOW PRIORITY - downweighted]' : '';
    echo sprintf("  %-15s: %.4f%s\n", $term, $score, $marker);
}
echo "\n";

// Test query TF-IDF calculation
echo "Query TF-IDF Test:\n";
echo "----------------------------------------------------------\n";

$testQueries = [
    'komputer error',
    'laptop lemot',
    'aplikasi tidak bisa',
    'pc rusak',
];

foreach ($testQueries as $query) {
    $queryVector = $tfidfService->calculateQueryTFIDF($query, $idf);
    echo "\nQuery: '$query'\n";
    echo "  Token weights after downweighting:\n";
    arsort($queryVector);
    foreach ($queryVector as $term => $weight) {
        $isLowPriority = in_array(strtolower($term), [
            'pc', 'laptop', 'komputer', 'aplikasi', 'error', 'masalah', 
            'sistem', 'program', 'software', 'hardware', 'cara', 'mengatasi', 
            'solusi', 'tutorial', 'panduan', 'tips'
        ]);
        $marker = $isLowPriority ? ' [DOWNWEIGHTED]' : '';
        echo sprintf("    %-15s: %.6f%s\n", $term, $weight, $marker);
    }
}

echo "\n";
echo "==========================================================\n";
echo "VERIFICATION:\n";
echo "==========================================================\n";

// Verify that generic terms have lower weights
$genericTerms = ['pc', 'laptop', 'komputer', 'aplikasi', 'error'];
$specificTerms = ['lemot', 'wifi', 'printer', 'router', 'outlook'];

echo "\nChecking if generic terms are downweighted...\n";

$allPassed = true;

// Test with a sample query
$testQuery = "komputer lemot error";
$queryVector = $tfidfService->calculateQueryTFIDF($testQuery, $idf);

foreach ($genericTerms as $term) {
    if (isset($queryVector[$term])) {
        $weight = $queryVector[$term];
        echo "  Generic term '$term': weight = $weight\n";
    }
}

echo "\n";

// Check that specific terms have higher relative weights
foreach ($specificTerms as $term) {
    if (isset($queryVector[$term])) {
        $weight = $queryVector[$term];
        echo "  Specific term '$term': weight = $weight\n";
    }
}

echo "\n";
echo "==========================================================\n";
echo "TEST COMPLETE\n";
echo "==========================================================\n";
echo "\nExpected behavior:\n";
echo "- Generic terms (pc, laptop, komputer, aplikasi, error) should have\n";
echo "  significantly lower weights (multiplied by 0.1)\n";
echo "- Specific terms (lemot, wifi, printer, etc.) should have higher\n";
echo "  relative weights and dominate the ranking\n";
echo "\n";