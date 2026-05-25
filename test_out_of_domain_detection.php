<?php

/**
 * Test script for OUT-OF-DOMAIN detection
 * 
 * This script tests that non-IT queries like "kucing", "rendang", "mobil balap"
 * are properly rejected with the message "Maaf, saya hanya dapat membantu masalah terkait IT."
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\Chatbot\DomainDetectionService;
use App\Services\Chatbot\PreprocessingService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========================================\n";
echo "OUT-OF-DOMAIN DETECTION TEST\n";
echo "========================================\n\n";

// Get the service from container
$domainDetector = app(DomainDetectionService::class);

// Test cases: Non-IT queries that should be REJECTED
$nonItQueries = [
    'kucing',
    'rendang',
    'mobil balap',
    'nasi goreng',
    'film action',
    'sepak bola',
    'resep masakan',
    'belanja online',
    'liburan ke bali',
    'sakit kepala',
];

// Test cases: IT queries that should be ACCEPTED
$itQueries = [
    'wifi lemot',
    'printer tidak bisa print',
    'komputer sering hang',
    'email tidak masuk',
    'internet putus-putus',
    'virus di laptop',
    'cara install windows',
    'website tidak bisa diakses',
    'lupa password akun',
    'aplikasi error',
];

echo "Testing NON-IT queries (should be REJECTED):\n";
echo "---------------------------------------------\n";
foreach ($nonItQueries as $query) {
    $result = $domainDetector->detectOutOfDomain($query);
    $status = $result['is_out_of_domain'] ? '✅ REJECTED' : '❌ ACCEPTED (BUG!)';
    echo sprintf("%-25s: %s (reason: %s)\n", 
        $query, 
        $status,
        $result['reason']
    );
}

echo "\n";
echo "Testing IT queries (should be ACCEPTED):\n";
echo "----------------------------------------\n";
foreach ($itQueries as $query) {
    $result = $domainDetector->detectOutOfDomain($query);
    $status = !$result['is_out_of_domain'] ? '✅ ACCEPTED' : '❌ REJECTED (BUG!)';
    echo sprintf("%-25s: %s (reason: %s)\n", 
        $query, 
        $status,
        $result['reason']
    );
}

echo "\n";
echo "========================================\n";
echo "Test completed!\n";
echo "========================================\n";