<?php

require __DIR__ . '/vendor/autoload.php';

use App\Services\Chatbot\VocabularyService;
use Illuminate\Support\Facades\App;

// Bootstrap Laravel
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "========================================\n";
echo "VOCABULARY-BASED TYPO CORRECTION TEST\n";
echo "========================================\n\n";

// Get the VocabularyService
$vocabularyService = app(VocabularyService::class);

// Test 1: Build vocabulary
echo "TEST 1: Building Vocabulary\n";
echo "----------------------------\n";
$vocabulary = $vocabularyService->buildVocabulary();
echo "Total words in vocabulary: " . count($vocabulary) . "\n";
echo "Sample words: " . implode(', ', array_slice($vocabulary, 0, 30)) . "\n\n";

// Test 2: Stats
echo "TEST 2: Vocabulary Statistics\n";
echo "------------------------------\n";
$stats = $vocabularyService->getStats();
foreach ($stats as $key => $value) {
    echo "$key: $value\n";
}
echo "\n";

// Test 3: Curated typo corrections
echo "TEST 3: Curated Typo Corrections\n";
echo "---------------------------------\n";
$curatedTypos = [
    'virusss' => 'virus',
    'viruss' => 'virus',
    'ransomwre' => 'ransomware',
    'pritner' => 'printer',
    'kompter' => 'komputer',
];

foreach ($curatedTypos as $typo => $expected) {
    $result = $vocabularyService->normalizeQuery($typo);
    $corrected = $result['normalized'];
    $status = $corrected === $expected ? '✓ PASS' : '✗ FAIL';
    echo "$status: '$typo' -> '$corrected' (expected: '$expected')\n";
    if (!empty($result['corrections'])) {
        foreach ($result['corrections'] as $correction) {
            echo "       Source: {$correction['source']}, Confidence: " . number_format($correction['confidence'], 2) . "\n";
        }
    }
}
echo "\n";

// Test 4: Dynamic typo corrections (using Levenshtein)
echo "TEST 4: Dynamic Typo Corrections (Levenshtein)\n";
echo "-----------------------------------------------\n";
$dynamicTypos = [
    'virusss',    // Should correct to 'virus'
    'ransomwre',  // Should correct to 'ransomware'
    'malwere',    // Should correct to 'malware'
    'wfi',        // Should correct to 'wifi'
    'prnter',     // Should correct to 'printer'
    'komputr',    // Should correct to 'komputer'
    'intenet',    // Should correct to 'internet'
    'emai',       // Should correct to 'email'
];

foreach ($dynamicTypos as $typo) {
    $result = $vocabularyService->normalizeQuery($typo);
    $corrected = $result['normalized'];
    echo "Query: '$typo' -> '$corrected'\n";
    if (!empty($result['corrections'])) {
        foreach ($result['corrections'] as $correction) {
            echo "       Original: {$correction['original']}, Corrected: {$correction['corrected']}\n";
            echo "       Source: {$correction['source']}, Confidence: " . number_format($correction['confidence'], 2);
            if (isset($correction['distance'])) {
                echo ", Distance: {$correction['distance']}";
            }
            echo "\n";
        }
    } else {
        echo "       No correction applied\n";
    }
}
echo "\n";

// Test 5: Multi-word queries
echo "TEST 5: Multi-word Query Normalization\n";
echo "---------------------------------------\n";
$multiWordQueries = [
    'cara mengatasi virusss',
    'kompter lemot',
    'wifi tidak connect',
    'printer error',
    'ransomwre protection',
];

foreach ($multiWordQueries as $query) {
    $result = $vocabularyService->normalizeQuery($query);
    echo "Query: '$query'\n";
    echo "Normalized: '{$result['normalized']}'\n";
    if (!empty($result['corrections'])) {
        foreach ($result['corrections'] as $correction) {
            echo "  Correction: {$correction['original']} -> {$correction['corrected']} ({$correction['source']})\n";
        }
    }
    echo "\n";
}

// Test 6: Words that should NOT be corrected
echo "TEST 6: No Correction Needed\n";
echo "-----------------------------\n";
$noCorrectionNeeded = [
    'virus',
    'wifi',
    'printer',
    'komputer',
    'internet',
    'email',
];

foreach ($noCorrectionNeeded as $word) {
    $result = $vocabularyService->normalizeQuery($word);
    $status = $result['normalized'] === $word ? '✓ PASS' : '✗ FAIL';
    echo "$status: '$word' -> '{$result['normalized']}' (should remain unchanged)\n";
}
echo "\n";

// Test 7: Check if words need correction
echo "TEST 7: needsCorrection() Method\n";
echo "---------------------------------\n";
$checkWords = ['virusss', 'virus', 'pritner', 'printer', 'kompter', 'komputer'];
foreach ($checkWords as $word) {
    $needsCorrection = $vocabularyService->needsCorrection($word);
    $status = $needsCorrection ? 'NEEDS correction' : 'NO correction needed';
    echo "'$word': $status\n";
}
echo "\n";

// Test 8: Cache management
echo "TEST 8: Cache Management\n";
echo "-------------------------\n";
echo "Clearing cache...\n";
$vocabularyService->clearCache();
echo "Cache cleared. Rebuilding vocabulary...\n";
$vocabulary = $vocabularyService->buildVocabulary();
echo "Vocabulary rebuilt with " . count($vocabulary) . " words\n\n";

echo "========================================\n";
echo "ALL TESTS COMPLETED\n";
echo "========================================\n";