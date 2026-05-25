<?php

/**
 * Comprehensive test for repeated character normalization in PreprocessingService
 * 
 * Tests the full pipeline:
 * 1. normalizeRepeatedChars() method
 * 2. normalizeTypos() with repeated char normalization integrated
 * 3. Full preprocess() pipeline
 * 
 * Expected behavior:
 * - dockerrrrrrrrrr -> docker
 * - viruuuuuusssss -> virus
 * - wifiiiiiiii -> wifi
 * - lemottttttt -> lemot
 * - errorrrrrrr -> error
 * - Valid double letters preserved (google, access, support)
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\Chatbot\PreprocessingService;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========================================\n";
echo "REPEATED CHARACTER NORMALIZATION TEST\n";
echo "========================================\n\n";

$preprocessor = new PreprocessingService();

// Test 1: normalizeRepeatedChars() method
echo "TEST 1: normalizeRepeatedChars() method\n";
echo "----------------------------------------\n";

$repeatedCharTests = [
    // Spam queries from the issue
    'dockerrrrrrrrrr' => 'dockerr',  // Compresses to 2 chars (docker -> dockerr)
    'viruuuuuusssss' => 'viruuss',   // Both u and s compressed to 2
    'wifiiiiiiii' => 'wifii',        // i compressed to 2
    'lemottttttt' => 'lemott',       // t compressed to 2
    'errorrrrrrr' => 'errorr',       // r compressed to 2
    
    // Valid double letters should be preserved
    'google' => 'google',            // No change - only 2 o's
    'access' => 'access',            // No change - only 2 c's and 2 s's
    'support' => 'support',          // No change - only 2 p's
    'success' => 'success',          // No change - only 2 c's and 2 s's
    'address' => 'address',          // No change - only 2 d's and 2 s's
    
    // Edge cases - regex compresses 3+ consecutive chars to 2
    'hellllo' => 'hello',            // llll compressed to ll
    'yesssss' => 'yess',             // sssss compressed to ss
    'nooo' => 'noo',                 // ooo compressed to oo
    'baaam' => 'baam',               // aaa compressed to aa
];

$passed = 0;
$failed = 0;

foreach ($repeatedCharTests as $input => $expected) {
    $result = $preprocessor->normalizeRepeatedChars($input);
    $status = $result === $expected ? '✓ PASS' : '✗ FAIL';
    
    if ($result === $expected) {
        $passed++;
    } else {
        $failed++;
    }
    
    echo "  $status: '$input' -> '$result' (expected: '$expected')\n";
}

echo "\n";

// Test 2: normalizeTypos() with repeated char normalization
echo "TEST 2: normalizeTypos() with repeated char normalization\n";
echo "----------------------------------------------------------\n";

$typoTests = [
    // Spam queries that should be normalized
    'dockerrrrrrrrrr' => 'docker',   // After compression + dictionary lookup
    'wifiiiiiiii' => 'wifi',         // After compression + dictionary lookup
    'lemottttttt' => 'lemot',        // After compression + dictionary lookup
    'errorrrrrrr' => 'error',        // After compression + dictionary lookup
    
    // Sentence queries
    'pc ku kena dockerrrrrr' => 'pc ku kena docker',
    'wifiiiiii lambat' => 'wifi lambat',
    'komputerrr lemot' => 'komputer lemot',
];

foreach ($typoTests as $input => $expected) {
    $result = $preprocessor->normalizeTypos($input);
    $status = $result === $expected ? '✓ PASS' : '✗ FAIL';
    
    if ($result === $expected) {
        $passed++;
    } else {
        $failed++;
    }
    
    echo "  $status: '$input' -> '$result'\n";
    if ($result !== $expected) {
        echo "       Expected: '$expected'\n";
    }
}

echo "\n";

// Test 3: Full preprocess() pipeline
echo "TEST 3: Full preprocess() pipeline with typo correction\n";
echo "--------------------------------------------------------\n";

$preprocessTests = [
    'dockerrrrrrrrrr' => ['docker'],
    'wifiiiiiiii' => ['wifi'],
    'lemottttttt' => ['lemot'],
    'errorrrrrrr' => ['error'],
    // Note: 'kena' gets stemmed to 'na' by the stemmer, 'ku' is a stopword
    'pc ku kena dockerrrrrr' => ['pc', 'na', 'docker'],
    // Note: 'tidak' and 'bisa' are stopwords, so they're removed
    'wifiiiiii tidak bisa connect' => ['wifi', 'connect'],
];

foreach ($preprocessTests as $input => $expectedTokens) {
    $result = $preprocessor->preprocess($input, true);  // Apply typo correction
    $status = $result === $expectedTokens ? '✓ PASS' : '✗ FAIL';
    
    if ($result === $expectedTokens) {
        $passed++;
    } else {
        $failed++;
    }
    
    echo "  $status: '$input'\n";
    echo "       Got: [" . implode(', ', $result) . "]\n";
    echo "       Expected: [" . implode(', ', $expectedTokens) . "]\n";
}

echo "\n";

// Test 4: Verify valid double letters are preserved
echo "TEST 4: Valid double letters preservation\n";
echo "------------------------------------------\n";

$validDoubleLetterTests = [
    'google' => 'google',
    'access' => 'access',
    'support' => 'support',
    'success' => 'success',
    'address' => 'address',
    'password' => 'password',
    'session' => 'session',
    'connection' => 'connection',
];

foreach ($validDoubleLetterTests as $input => $expected) {
    $result = $preprocessor->normalizeRepeatedChars($input);
    $status = $result === $expected ? '✓ PASS' : '✗ FAIL';
    
    if ($result === $expected) {
        $passed++;
    } else {
        $failed++;
    }
    
    echo "  $status: '$input' -> '$result' (expected: '$expected')\n";
}

echo "\n";

// Summary
echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Passed: $passed\n";
echo "Failed: $failed\n";
echo "Total:  " . ($passed + $failed) . "\n";

if ($failed === 0) {
    echo "\n✓ ALL TESTS PASSED!\n";
    exit(0);
} else {
    echo "\n✗ SOME TESTS FAILED - Please review the output above.\n";
    exit(1);
}