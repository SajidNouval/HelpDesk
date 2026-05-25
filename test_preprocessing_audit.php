<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\ChatbotRetrievalService;

echo "============================================================\n";
echo "  PREPROCESSING & INDEXING STABILITY VERIFICATION TEST\n";
echo "============================================================\n\n";

$preprocessor = app(PreprocessingService::class);
$tfidf = app(TfidfService::class);
$retrieval = app(ChatbotRetrievalService::class);

$passed = 0;
$failed = 0;

function test(string $name, callable $test): void
{
    global $passed, $failed;
    try {
        $result = $test();
        if ($result) {
            echo "✅ PASS: $name\n";
            $passed++;
        } else {
            echo "❌ FAIL: $name\n";
            $failed++;
        }
    } catch (\Throwable $e) {
        echo "❌ ERROR: $name - ".$e->getMessage()."\n";
        $failed++;
    }
}

echo "--- Section 1: Preprocessing Consistency ---\n";

test('Empty string returns empty array', function() use ($preprocessor) {
    return $preprocessor->preprocess('') === [];
});

test('Whitespace-only string returns empty array', function() use ($preprocessor) {
    return $preprocessor->preprocess('   ') === [];
});

test('Preprocessing returns array of strings', function() use ($preprocessor) {
    $result = $preprocessor->preprocess('komputer lemot');
    return is_array($result) && count($result) > 0;
});

test('Case folding works correctly', function() use ($preprocessor) {
    $result1 = $preprocessor->preprocess('KOMPUTER');
    $result2 = $preprocessor->preprocess('komputer');
    return $result1 === $result2;
});

test('Stopwords are removed', function() use ($preprocessor) {
    $result = $preprocessor->preprocess('saya ingin komputer');
    return !in_array('saya', $result) && !in_array('ingin', $result);
});

test('Short tokens are filtered', function() use ($preprocessor) {
    $result = $preprocessor->preprocess('a an ke di komputer');
    return !in_array('a', $result) && !in_array('an', $result);
});

test('preprocessDocument returns tokens and frequency', function() use ($preprocessor) {
    $result = $preprocessor->preprocessDocument('komputer lemot');
    return isset($result['tokens']) && isset($result['frequency']);
});

echo "\n--- Section 2: Typo Normalization ---\n";

test('wfi => wifi', function() use ($preprocessor) {
    return $preprocessor->normalizeTypos('wfi') === 'wifi';
});

test('pritner => printer', function() use ($preprocessor) {
    return $preprocessor->normalizeTypos('pritner') === 'printer';
});

test('emial => email', function() use ($preprocessor) {
    return $preprocessor->normalizeTypos('emial') === 'email';
});

test('intenet => internet', function() use ($preprocessor) {
    return $preprocessor->normalizeTypos('intenet') === 'internet';
});

test('kompter => komputer', function() use ($preprocessor) {
    return $preprocessor->normalizeTypos('kompter') === 'komputer';
});

test('Typo correction in sentence', function() use ($preprocessor) {
    $result = $preprocessor->normalizeTypos('wfi saya tidak bisa connect');
    return strpos($result, 'wifi') !== false;
});

test('getTypoCorrections returns changes', function() use ($preprocessor) {
    $corrections = $preprocessor->getTypoCorrections('wfi lemot', 'wifi lemot');
    return count($corrections) === 1 && $corrections[0]['original'] === 'wfi';
});

test('Non-typo words unchanged', function() use ($preprocessor) {
    return $preprocessor->normalizeTypos('komputer') === 'komputer';
});

echo "\n--- Section 3: Stemming Protection ---\n";

$protectedTokens = ['ransomware', 'malware', 'virus', 'trojan', 'vpn', 'wifi', 'printer', 'bsod', 'gmail', 'outlook'];

foreach ($protectedTokens as $token) {
    test("Protected token '$token' NOT stemmed", function() use ($preprocessor, $token) {
        $result = $preprocessor->preprocess($token);
        return in_array($token, $result);
    });
}

test('isProtectedTechnicalToken: ransomware', function() use ($preprocessor) {
    return $preprocessor->isProtectedTechnicalToken('ransomware') === true;
});

test('isProtectedTechnicalToken: malware', function() use ($preprocessor) {
    return $preprocessor->isProtectedTechnicalToken('malware') === true;
});

test('isProtectedTechnicalToken: virus', function() use ($preprocessor) {
    return $preprocessor->isProtectedTechnicalToken('virus') === true;
});

test('isProtectedTechnicalToken: trojan', function() use ($preprocessor) {
    return $preprocessor->isProtectedTechnicalToken('trojan') === true;
});

test('isProtectedTechnicalToken: vpn', function() use ($preprocessor) {
    return $preprocessor->isProtectedTechnicalToken('vpn') === true;
});

test('isProtectedTechnicalToken: wifi', function() use ($preprocessor) {
    return $preprocessor->isProtectedTechnicalToken('wifi') === true;
});

test('isProtectedTechnicalToken: printer', function() use ($preprocessor) {
    return $preprocessor->isProtectedTechnicalToken('printer') === true;
});

test('isProtectedTechnicalToken: bsod', function() use ($preprocessor) {
    return $preprocessor->isProtectedTechnicalToken('bsod') === true;
});

test('isProtectedTechnicalToken: gmail', function() use ($preprocessor) {
    return $preprocessor->isProtectedTechnicalToken('gmail') === true;
});

test('isProtectedTechnicalToken: outlook', function() use ($preprocessor) {
    return $preprocessor->isProtectedTechnicalToken('outlook') === true;
});

test('Non-protected words ARE stemmed', function() use ($preprocessor) {
    $result = $preprocessor->preprocess('mengatasi');
    return !in_array('mengatasi', $result);
});

echo "\n--- Section 4: Query Token Protection ---\n";

test('Query ransomware produces ransomware token', function() use ($preprocessor) {
    $tokens = $preprocessor->preprocess('ransomware', true);
    return in_array('ransomware', $tokens);
});

test('Query malware produces malware token', function() use ($preprocessor) {
    $tokens = $preprocessor->preprocess('malware', true);
    return in_array('malware', $tokens);
});

test('Query vpn produces vpn token', function() use ($preprocessor) {
    $tokens = $preprocessor->preprocess('vpn tidak connect', true);
    return in_array('vpn', $tokens);
});

test('Query wifi produces wifi token', function() use ($preprocessor) {
    $tokens = $preprocessor->preprocess('wifi lemot', true);
    return in_array('wifi', $tokens);
});

test('Query printer produces printer token', function() use ($preprocessor) {
    $tokens = $preprocessor->preprocess('printer tidak mencetak', true);
    return in_array('printer', $tokens);
});

echo "\n--- Section 5: Context Token Detection ---\n";

test('Extract context from wifi tokens', function() use ($preprocessor) {
    $contexts = $preprocessor->extractContextTokens(['wifi']);
    return in_array('wifi', $contexts);
});

test('Extract context from komputer tokens', function() use ($preprocessor) {
    $contexts = $preprocessor->extractContextTokens(['komputer']);
    return in_array('komputer', $contexts);
});

test('Extract context from printer tokens', function() use ($preprocessor) {
    $contexts = $preprocessor->extractContextTokens(['printer']);
    return in_array('printer', $contexts);
});

test('Extract context from email tokens', function() use ($preprocessor) {
    $contexts = $preprocessor->extractContextTokens(['email']);
    return in_array('email', $contexts);
});

echo "\n--- Section 6: Domain Token Detection ---\n";

test('isImportantDomainToken: komputer', function() use ($preprocessor) {
    return $preprocessor->isImportantDomainToken('komputer') === true;
});

test('isImportantDomainToken: printer', function() use ($preprocessor) {
    return $preprocessor->isImportantDomainToken('printer') === true;
});

test('isImportantDomainToken: wifi', function() use ($preprocessor) {
    return $preprocessor->isImportantDomainToken('wifi') === true;
});

test('isImportantDomainToken: internet', function() use ($preprocessor) {
    return $preprocessor->isImportantDomainToken('internet') === true;
});

test('isImportantDomainToken: email', function() use ($preprocessor) {
    return $preprocessor->isImportantDomainToken('email') === true;
});

test('isImportantDomainToken: bsod', function() use ($preprocessor) {
    return $preprocessor->isImportantDomainToken('bsod') === true;
});

echo "\n--- Section 7: IT Generic Term Detection ---\n";

test('isITGenericTerm: cara', function() use ($preprocessor) {
    return $preprocessor->isITGenericTerm('cara') === true;
});

test('isITGenericTerm: mengatasi', function() use ($preprocessor) {
    return $preprocessor->isITGenericTerm('mengatasi') === true;
});

test('isITGenericTerm: solusi', function() use ($preprocessor) {
    return $preprocessor->isITGenericTerm('solusi') === true;
});

test('isITGenericTerm: tutorial', function() use ($preprocessor) {
    return $preprocessor->isITGenericTerm('tutorial') === true;
});

test('Non-generic term komputer is not generic', function() use ($preprocessor) {
    return $preprocessor->isITGenericTerm('komputer') === false;
});

echo "\n--- Section 8: Cache Operations ---\n";

test('clearCache executes without error', function() use ($retrieval) {
    $retrieval->clearCache();
    return true;
});

test('rebuildCache returns valid data', function() use ($retrieval) {
    $result = $retrieval->rebuildCache();
    return isset($result['success']) && $result['success'] === true;
});

echo "\n--- Section 9: Preprocessing with Typo Correction ---\n";

test('Preprocess with typo correction: wfi to wifi', function() use ($preprocessor) {
    $tokens = $preprocessor->preprocess('wfi', true);
    return in_array('wifi', $tokens);
});

test('Preprocess without typo correction: wfi stays', function() use ($preprocessor) {
    $tokens = $preprocessor->preprocess('wfi', false);
    return !in_array('wifi', $tokens);
});

test('Preprocess with typo correction in sentence', function() use ($preprocessor) {
    $tokens = $preprocessor->preprocess('wfi tidak connect', true);
    return in_array('wifi', $tokens);
});

echo "\n============================================================\n";
echo "  TEST SUMMARY\n";
echo "============================================================\n";
echo "  Passed: $passed\n";
echo "  Failed: $failed\n";
echo "  Total:  ".($passed + $failed)."\n";
echo "============================================================\n\n";

if ($failed === 0) {
    echo "🎉 ALL TESTS PASSED! Preprocessing and indexing pipeline is stable.\n";
} else {
    echo "⚠️  $failed test(s) failed. Review the failures above.\n";
}

echo "\n";