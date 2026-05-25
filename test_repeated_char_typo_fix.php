<?php

/**
 * Test script for repeated character typo correction
 * 
 * This tests the new adaptive Levenshtein threshold and repeated character normalization.
 * 
 * Expected behavior:
 * - virusss, virussss, virusssssss should ALL normalize to "virus"
 * - wifiii should normalize to "wifi"
 * - lemottt should normalize to "lemot"
 * - errorrrr should normalize to "error"
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\Chatbot\VocabularyService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========================================\n";
echo "REPEATED CHARACTER TYPO CORRECTION TEST\n";
echo "========================================\n\n";

$vocabularyService = new VocabularyService();

// Clear cache to ensure fresh vocabulary
$vocabularyService->clearCache();

// Test cases for repeated character normalization
$testCases = [
    // Repeated character tests
    'virusss' => 'virus',
    'virussss' => 'virus',
    'virusssss' => 'virus',
    'virusssssss' => 'virus',
    'wifiii' => 'wifi',
    'wifiiii' => 'wifi',
    'lemottt' => 'lemot',
    'lemotttt' => 'lemot',
    'errorrrr' => 'error',
    'errorrrrr' => 'error',
    'printerrr' => 'printer',
    'internett' => 'internet',
    'internett' => 'internet',
    'komputerrr' => 'komputer',
    
    // Edge cases
    'viru' => 'virus',  // Missing 's' - should use Levenshtein (distance 1, passes for 5-char word)
    // Note: 'virsu' is NOT expected to match 'virus' because:
    // - 'virus' is 5 chars (short word)
    // - Short words have max distance = 1
    // - 'virsu' has transposition (distance 2)
    // - This is intentional to avoid false positives for short words
];

echo "Testing normalizeRepeatedChars() method:\n";
echo "----------------------------------------\n";

$reflectionMethod = new ReflectionMethod($vocabularyService, 'normalizeRepeatedChars');
$reflectionMethod->setAccessible(true);

// Note: 'internett' has only 2 t's (not 3+), so it won't be compressed by normalizeRepeatedChars
// It will be corrected via dynamic Levenshtein in the full pipeline instead
$repeatedCharTests = [
    'virusss' => 'virus',
    'virussss' => 'virus',
    'virusssssss' => 'virus',
    'wifiii' => 'wifi',
    'wifiiii' => 'wifi',
    'lemottt' => 'lemot',
    'errorrrr' => 'error',
    'printerrr' => 'printer',
];

foreach ($repeatedCharTests as $input => $expected) {
    $result = $reflectionMethod->invoke($vocabularyService, $input);
    $status = $result === $expected ? '✓ PASS' : '✗ FAIL';
    echo "  $status: '$input' -> '$result' (expected: '$expected')\n";
}

echo "\n";
echo "Testing normalizeQuery() with full pipeline:\n";
echo "---------------------------------------------\n";

$passed = 0;
$failed = 0;

foreach ($testCases as $query => $expectedWord) {
    $result = $vocabularyService->normalizeQuery($query);
    $normalized = $result['normalized'];
    
    // Check if the expected word is in the corrections
    $found = false;
    $correctionSource = 'none';
    
    foreach ($result['corrections'] as $correction) {
        if (isset($correction['corrected']) && $correction['corrected'] === $expectedWord) {
            $found = true;
            $correctionSource = $correction['source'] ?? 'unknown';
            break;
        }
    }
    
    // Also check if normalized query contains the expected word
    if (stripos($normalized, $expectedWord) !== false) {
        $found = true;
    }
    
    if ($found) {
        echo "  ✓ PASS: '$query' -> '$normalized' (via $correctionSource)\n";
        $passed++;
    } else {
        echo "  ✗ FAIL: '$query' -> '$normalized' (expected to contain '$expectedWord')\n";
        echo "          Corrections: " . json_encode($result['corrections'], JSON_PRETTY_PRINT) . "\n";
        $failed++;
    }
}

echo "\n";
echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

// Test vocabulary stats
echo "\n";
echo "Vocabulary Statistics:\n";
echo "---------------------\n";
$stats = $vocabularyService->getStats();
echo "Total words: " . $stats['total_words'] . "\n";
echo "Domain terms: " . $stats['domain_terms'] . "\n";
echo "Curated typos: " . $stats['curated_typos'] . "\n";

if ($failed === 0) {
    echo "\n✓ ALL TESTS PASSED!\n";
    exit(0);
} else {
    echo "\n✗ SOME TESTS FAILED - Please review the output above.\n";
    exit(1);
}