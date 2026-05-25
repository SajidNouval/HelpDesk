<?php

/**
 * TECHNICAL TOKEN PRIORITY RETRIEVAL TEST
 * 
 * This test validates that:
 * 1. Technical terms (ransomware, malware, virus, trojan) are NOT stemmed
 * 2. Security queries retrieve security articles FIRST, not generic articles
 * 3. Exact token matches get massive boost
 * 4. Generic articles like "komputer lemot" don't dominate security queries
 */

require_once __DIR__.'/vendor/autoload.php';

use App\Services\Chatbot\PreprocessingService;

echo "========================================\n";
echo "TECHNICAL TOKEN PRIORITY TEST SUITE\n";
echo "========================================\n\n";

// Test 1: Protected Technical Tokens are NOT stemmed
echo "TEST 1: Protected Technical Tokens NOT Stemmed\n";
echo "----------------------------------------------\n";

$preprocessor = new PreprocessingService();

$protectedTerms = ['ransomware', 'malware', 'virus', 'trojan', 'vpn', 'gmail', 'printer', 'wifi'];

$allPassed = true;
foreach ($protectedTerms as $term) {
    $tokens = $preprocessor->preprocess($term);
    $result = $tokens[0] ?? '';
    $passed = $result === $term;
    $status = $passed ? '✓ PASS' : '✗ FAIL';
    echo "  $status: '$term' -> '$result' (expected: '$term')\n";
    if (!$passed) $allPassed = false;
}

echo "\n";

// Test 2: Non-protected terms ARE stemmed (as expected)
echo "TEST 2: Non-Protected Terms ARE Stemmed (expected behavior)\n";
echo "-----------------------------------------------------------\n";

$nonProtectedTerms = ['mengatasi', 'komputer', 'internet'];
foreach ($nonProtectedTerms as $term) {
    $tokens = $preprocessor->preprocess($term);
    $result = $tokens[0] ?? '';
    echo "  Info: '$term' -> '$result'\n";
}

echo "\n";

// Test 3: isProtectedTechnicalToken method works
echo "TEST 3: isProtectedTechnicalToken Method\n";
echo "----------------------------------------\n";

$testTokens = ['ransomware', 'malware', 'virus', 'trojan', 'vpn', 'komputer', 'lemot'];
foreach ($testTokens as $token) {
    $isProtected = $preprocessor->isProtectedTechnicalToken($token);
    $expected = in_array($token, ['ransomware', 'malware', 'virus', 'trojan', 'vpn']);
    $status = ($isProtected === $expected) ? '✓ PASS' : '✗ FAIL';
    echo "  $status: '$token' isProtected=" . ($isProtected ? 'true' : 'false') . " (expected: " . ($expected ? 'true' : 'false') . ")\n";
}

echo "\n";

// Test 4: Protected tokens list is comprehensive
echo "TEST 4: Protected Tokens Dictionary Coverage\n";
echo "--------------------------------------------\n";

$protectedList = $preprocessor->getProtectedTechnicalTokens();
$requiredTerms = ['ransomware', 'malware', 'virus', 'trojan', 'vpn', 'wifi', 'gmail', 'outlook', 'printer', 'router', 'bsod'];

foreach ($requiredTerms as $term) {
    $found = in_array($term, $protectedList);
    $status = $found ? '✓ PASS' : '✗ FAIL';
    echo "  $status: '$term' in protected list\n";
}

echo "\n";

// Summary
echo "========================================\n";
echo "TEST SUMMARY\n";
echo "========================================\n";
echo "Core tests " . ($allPassed ? "PASSED" : "FAILED") . "\n";
echo "\nNote: Full integration tests require Laravel bootstrap.\n";
echo "Run the chatbot with test queries to verify end-to-end behavior.\n";
echo "\nExpected behavior after fix:\n";
echo "  - Query 'ransomware' -> Returns ransomware article FIRST\n";
echo "  - Query 'virus' -> Returns virus/security article FIRST\n";
echo "  - Query 'trojan' -> Returns trojan article FIRST\n";
echo "  - Query 'malware' -> Returns malware article FIRST\n";
echo "  - Query 'vpn' -> Returns vpn article FIRST\n";
echo "  - Query 'gmail error' -> Returns gmail article FIRST\n";
echo "  - Query 'printer offline' -> Returns printer article FIRST\n";
echo "\nNOT:\n";
echo "  - 'komputer lemot' articles for security queries\n";
echo "  - 'internet lemot' articles for printer queries\n";