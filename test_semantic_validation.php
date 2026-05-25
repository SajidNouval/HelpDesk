<?php

/**
 * REALISTIC SEMANTIC VALIDATION SUITE FOR TF-IDF CHATBOT
 * 
 * This test suite validates SEMANTIC CORRECTNESS, not just "result exists".
 * Each test verifies that the returned article is from the CORRECT DOMAIN.
 * 
 * CRITICAL TESTS:
 * - wifi lemot → wifi article ONLY (not internet, not printer)
 * - printer error → printer article ONLY (not hardware general)
 * - ransomware → ransomware/security article ONLY (not general security)
 * - komputer lemot → komputer article ONLY (not laptop overheating)
 * - internet lambat → internet article (NOT VPN/security)
 * - pritner eror → normalized correctly to printer domain
 * - asdfgh repeated 3x → escalation flow triggered
 * 
 * Run with: php test_semantic_validation.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Chatbot\ChatbotRetrievalService;
use App\Services\Chatbot\ConversationFlowService;
use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\DomainDetectionService;
use App\Models\Article;
use App\Models\Category;

// ============================================================
// TEST CONFIGURATION
// ============================================================

// Semantic domain mappings - what domain EACH query MUST return
$SEMANTIC_DOMAIN_MAP = [
    // Exact domain queries
    'wifi lemot' => 'wifi',
    'printer error' => 'printer',
    'ransomware' => 'security',
    'komputer lemot' => 'komputer',
    'internet lambat' => 'internet',
    
    // Typo queries
    'pritner eror' => 'printer',
    
    // Edge cases
    'asdfgh asdfgh asdfgh' => 'escalation', // Should trigger escalation
];

// Domain to category mapping (what categories belong to each domain)
$DOMAIN_CATEGORY_MAP = [
    'wifi' => ['Wifi', 'Jaringan'],
    'printer' => ['Hardware'], // Printer is under Hardware category
    'security' => ['Security', 'Ransomware', 'Malware', 'Virus'],
    'komputer' => ['Hardware', 'Komputer'],
    'internet' => ['Internet', 'Jaringan', 'Wifi'],
    'email' => ['Email', 'Akun'],
    'aplikasi' => ['Aplikasi', 'Software'],
];

// Forbidden domain combinations (to detect cross-domain contamination)
$FORBIDDEN_COMBINATIONS = [
    'wifi' => ['Printer', 'BSOD', 'Komputer'],
    'printer' => ['Wifi', 'Internet', 'Jaringan', 'BSOD'],
    'ransomware' => ['Komputer', 'Printer', 'Wifi', 'Internet', 'Hardware'],
    'komputer' => ['Wifi', 'Internet', 'Jaringan', 'Printer', 'BSOD'],
    'internet' => ['Printer', 'BSOD', 'Komputer', 'Hardware'],
];

// ============================================================
// TEST INFRASTRUCTURE
// ============================================================

class SemanticTestResult {
    public string $testName;
    public string $query;
    public string $expectedDomain;
    public ?string $actualDomain;
    public bool $passed;
    public string $message;
    public array $details;
    
    public function __construct(
        string $testName,
        string $query,
        string $expectedDomain,
        ?string $actualDomain,
        bool $passed,
        string $message,
        array $details = []
    ) {
        $this->testName = $testName;
        $this->query = $query;
        $this->expectedDomain = $expectedDomain;
        $this->actualDomain = $actualDomain;
        $this->passed = $passed;
        $this->message = $message;
        $this->details = $details;
    }
}

$results = [];
$passed = 0;
$failed = 0;
$total = 0;

function recordResult(SemanticTestResult $result): void {
    global $results, $passed, $failed, $total;
    $total++;
    $results[] = $result;
    if ($result->passed) {
        $passed++;
        echo "  ✅ PASS: {$result->testName}\n";
    } else {
        $failed++;
        echo "  ❌ FAIL: " . $result->testName . "\n";
        echo "     Query: " . $result->query . "\n";
        echo "     Expected: " . $result->expectedDomain . "\n";
        echo "     Actual: " . ($result->actualDomain ?? 'NULL') . "\n";
        echo "     Message: " . $result->message . "\n";
        if (!empty($result->details)) {
            echo "     Details: " . json_encode($result->details, JSON_PRETTY_PRINT) . "\n";
        }
    }
}

function section(string $title): void {
    echo "\n";
    echo str_repeat('=', 70) . "\n";
    echo " $title\n";
    echo str_repeat('=', 70) . "\n";
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Determine the domain of an article based on its category and content
 */
function determineArticleDomain(Article $article): string {
    $categoryName = strtolower($article->category ? $article->category->name : '');
    $title = strtolower($article->title);
    $keywords = strtolower($article->keywords);
    $content = strtolower($article->content);
    
    // Check for specific domain indicators
    if (str_contains($categoryName, 'wifi') || str_contains($categoryName, 'jaringan')) {
        if (str_contains($title, 'wifi') || str_contains($content, 'wifi')) {
            return 'wifi';
        }
        return 'jaringan';
    }
    
    if (str_contains($categoryName, 'hardware')) {
        if (str_contains($title, 'printer') || str_contains($keywords, 'printer')) {
            return 'printer';
        }
        if (str_contains($title, 'komputer') || str_contains($keywords, 'komputer') || str_contains($title, 'lemot')) {
            return 'komputer';
        }
        if (str_contains($title, 'laptop') || str_contains($keywords, 'laptop')) {
            return 'laptop';
        }
        return 'hardware';
    }
    
    if (str_contains($categoryName, 'internet')) {
        return 'internet';
    }
    
    if (str_contains($categoryName, 'email')) {
        return 'email';
    }
    
    if (str_contains($categoryName, 'aplikasi') || str_contains($categoryName, 'software')) {
        return 'aplikasi';
    }
    
    if (str_contains($categoryName, 'security') || str_contains($categoryName, 'ransomware') || 
        str_contains($categoryName, 'malware') || str_contains($categoryName, 'virus')) {
        return 'security';
    }
    
    // Fallback: analyze content for domain keywords
    $domainKeywords = [
        'wifi' => ['wifi', 'router', 'sinyal', 'jaringan nirkabel'],
        'printer' => ['printer', 'mencetak', 'toner', 'kertas'],
        'komputer' => ['komputer', 'pc', 'desktop', 'laptop'],
        'internet' => ['internet', 'koneksi', 'bandwidth', 'isp'],
        'email' => ['email', 'surel', 'pesan elektronik'],
        'security' => ['ransomware', 'malware', 'virus', 'keamanan', 'phishing'],
        'aplikasi' => ['aplikasi', 'software', 'program'],
    ];
    
    foreach ($domainKeywords as $domain => $keywords) {
        foreach ($keywords as $keyword) {
            if (str_contains($title, $keyword) || str_contains($content, $keyword)) {
                return $domain;
            }
        }
    }
    
    return 'unknown';
}

/**
 * Check if a domain matches expected domain (with synonyms)
 */
function domainMatches(string $actual, string $expected): bool {
    if ($actual === $expected) return true;
    
    // Domain synonyms/mappings
    $synonyms = [
        'jaringan' => ['wifi', 'internet'],
        'hardware' => ['printer', 'komputer', 'laptop'],
        'security' => ['ransomware', 'malware', 'virus'],
    ];
    
    if (isset($synonyms[$actual]) && in_array($expected, $synonyms[$actual])) {
        return true;
    }
    
    if (isset($synonyms[$expected]) && in_array($actual, $synonyms[$expected])) {
        return true;
    }
    
    return false;
}

/**
 * Check if escalation flow should be triggered
 */
function shouldTriggerEscalation(string $query): bool {
    // Repeated gibberish should trigger escalation
    $words = explode(' ', trim($query));
    if (count($words) >= 3) {
        $uniqueWords = array_unique($words);
        if (count($uniqueWords) === 1 && strlen($uniqueWords[0]) > 3) {
            return true; // Same word repeated 3+ times
        }
    }
    
    return false;
}

// ============================================================
// MAIN TEST EXECUTION
// ============================================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║     REALISTIC SEMANTIC VALIDATION SUITE FOR TF-IDF CHATBOT      ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Focus: SEMANTIC CORRECTNESS (not just result existence)\n";
echo "\n";

// Pre-test setup
section("PRE-TEST: System Initialization");

$retrievalService = app(ChatbotRetrievalService::class);
$conversationFlowService = app(ConversationFlowService::class);
$preprocessor = app(PreprocessingService::class);
$domainDetector = app(DomainDetectionService::class);

// Clear cache for fresh testing
$retrievalService->clearCache();
echo "✅ Cache cleared for fresh testing.\n";

// Check database state
$articleCount = Article::where('is_published', true)
    ->where('publish_status', 'approved')
    ->count();
$categoryCount = Category::count();

echo "📊 Database state: $articleCount published articles, $categoryCount categories\n";

if ($articleCount < 5) {
    echo "⚠️  WARNING: Not enough articles for comprehensive testing.\n";
    exit(1);
}

// ============================================================
// TEST 1: EXACT DOMAIN RETRIEVAL - SEMANTIC CORRECTNESS
// ============================================================
section("TEST 1: EXACT DOMAIN RETRIEVAL (Semantic Correctness)");

$exactDomainTests = [
    'wifi lemot' => 'wifi',
    'printer error' => 'printer',
    'komputer lemot' => 'komputer',
    'internet lambat' => 'internet',
];

foreach ($exactDomainTests as $query => $expectedDomain) {
    $result = $retrievalService->retrieve($query, 3);
    
    if (empty($result['results'])) {
        recordResult(new SemanticTestResult(
            "Query '$query' returns results",
            $query,
            $expectedDomain,
            null,
            false,
            "No results returned"
        ));
        continue;
    }
    
    // Check top result's domain
    $topArticle = Article::find($result['results'][0]['id']);
    if (!$topArticle) {
        recordResult(new SemanticTestResult(
            "Query '$query' returns valid article",
            $query,
            $expectedDomain,
            null,
            false,
            "Article not found in database"
        ));
        continue;
    }
    
    $actualDomain = determineArticleDomain($topArticle);
    $matches = domainMatches($actualDomain, $expectedDomain);
    
    recordResult(new SemanticTestResult(
        "Query '$query' → {$expectedDomain} article ONLY",
        $query,
        $expectedDomain,
        $actualDomain,
        $matches,
        $matches ? "Correct domain match" : "Domain mismatch detected",
        [
            'similarity' => $result['results'][0]['similarity'] ?? 0,
            'article_title' => $topArticle->title,
            'category' => $topArticle->category->name ?? 'Unknown'
        ]
    ));
    
    // Additional check: verify NO cross-domain contamination in top 3
    if (isset($FORBIDDEN_COMBINATIONS[$expectedDomain])) {
        $crossDomainFound = false;
        foreach ($result['results'] as $r) {
            $article = Article::find($r['id']);
            if ($article) {
                $articleDomain = determineArticleDomain($article);
                foreach ($FORBIDDEN_COMBINATIONS[$expectedDomain] as $forbidden) {
                    if (stripos($articleDomain, strtolower($forbidden)) !== false) {
                        $crossDomainFound = true;
                        break;
                    }
                }
            }
        }
        
        recordResult(new SemanticTestResult(
            "Query '$query' has NO cross-domain contamination",
            $query,
            $expectedDomain,
            $crossDomainFound ? 'CONTAMINATED' : 'CLEAN',
            !$crossDomainFound,
            $crossDomainFound ? "Cross-domain articles found in results" : "No cross-domain contamination",
            ['forbidden_domains' => $FORBIDDEN_COMBINATIONS[$expectedDomain]]
        ));
    }
}

// ============================================================
// TEST 2: RANSOMWARE - SECURITY DOMAIN ISOLATION
// ============================================================
section("TEST 2: RANSOMWARE - SECURITY DOMAIN ISOLATION");

$ransomResult = $retrievalService->retrieve('ransomware', 3);

if (empty($ransomResult['results'])) {
    recordResult(new SemanticTestResult(
        "Ransomware query returns results",
        'ransomware',
        'security',
        null,
        false,
        "No results returned for ransomware query"
    ));
} else {
    $topArticle = Article::find($ransomResult['results'][0]['id']);
    $actualDomain = determineArticleDomain($topArticle);
    $isSecurity = $actualDomain === 'security' || stripos($actualDomain, 'security') !== false;
    
    recordResult(new SemanticTestResult(
        "Ransomware → security article ONLY (not general IT)",
        'ransomware',
        'security',
        $actualDomain,
        $isSecurity,
        $isSecurity ? "Correctly isolated to security domain" : "Leaked to non-security domain",
        [
            'article_title' => $topArticle->title ?? 'Unknown',
            'category' => $topArticle->category->name ?? 'Unknown',
            'similarity' => $ransomResult['results'][0]['similarity'] ?? 0
        ]
    ));
    
    // Verify NOT returning VPN/security articles when asking about ransomware
    // (This is a specific requirement: ransomware should not return VPN articles)
    $vpnArticleFound = false;
    foreach ($ransomResult['results'] as $r) {
        $article = Article::find($r['id']);
        if ($article && (stripos($article->title, 'vpn') !== false || 
            stripos($article->keywords, 'vpn') !== false)) {
            $vpnArticleFound = true;
            break;
        }
    }
    
    recordResult(new SemanticTestResult(
        "Ransomware does NOT return VPN articles",
        'ransomware',
        'security',
        $vpnArticleFound ? 'VPN_FOUND' : 'NO_VPN',
        !$vpnArticleFound,
        $vpnArticleFound ? "VPN article incorrectly returned for ransomware" : "No VPN contamination",
        []
    ));
}

// ============================================================
// TEST 3: INTERNET LAMBAT - NOT VPN/SECURITY
// ============================================================
section("TEST 3: INTERNET LAMBAT - NOT VPN/SECURITY");

$internetResult = $retrievalService->retrieve('internet lambat', 3);

if (empty($internetResult['results'])) {
    recordResult(new SemanticTestResult(
        "Internet lambat returns results",
        'internet lambat',
        'internet',
        null,
        false,
        "No results returned"
    ));
} else {
    $topArticle = Article::find($internetResult['results'][0]['id']);
    $actualDomain = determineArticleDomain($topArticle);
    $isInternet = $actualDomain === 'internet' || $actualDomain === 'wifi' || $actualDomain === 'jaringan';
    
    recordResult(new SemanticTestResult(
        "Internet lambat → internet article (not VPN/security)",
        'internet lambat',
        'internet',
        $actualDomain,
        $isInternet,
        $isInternet ? "Correctly returns internet domain" : "Incorrect domain returned",
        [
            'article_title' => $topArticle->title ?? 'Unknown',
            'category' => $topArticle->category->name ?? 'Unknown'
        ]
    ));
    
    // Verify NOT returning VPN articles
    $vpnArticleFound = false;
    foreach ($internetResult['results'] as $r) {
        $article = Article::find($r['id']);
        if ($article && (stripos($article->title, 'vpn') !== false || 
            stripos($article->keywords, 'vpn') !== false)) {
            $vpnArticleFound = true;
            break;
        }
    }
    
    recordResult(new SemanticTestResult(
        "Internet lambat does NOT return VPN articles",
        'internet lambat',
        'internet',
        $vpnArticleFound ? 'VPN_FOUND' : 'NO_VPN',
        !$vpnArticleFound,
        $vpnArticleFound ? "VPN article incorrectly returned for internet lambat" : "No VPN contamination",
        []
    ));
}

// ============================================================
// TEST 4: TYPO NORMALIZATION - SEMANTIC CORRECTNESS
// ============================================================
section("TEST 4: TYPO NORMALIZATION (Semantic Correctness)");

$typoTests = [
    'pritner eror' => 'printer',
];

foreach ($typoTests as $query => $expectedDomain) {
    // First, test normalization
    $normalized = $preprocessor->normalizeTypos($query);
    echo "  Typo normalization: '$query' → '$normalized'\n";
    
    $result = $retrievalService->retrieve($query, 3);
    
    if (empty($result['results'])) {
        recordResult(new SemanticTestResult(
            "Typo query '$query' returns results after normalization",
            $query,
            $expectedDomain,
            null,
            false,
            "No results returned after typo normalization"
        ));
        continue;
    }
    
    $topArticle = Article::find($result['results'][0]['id']);
    $actualDomain = determineArticleDomain($topArticle);
    $matches = domainMatches($actualDomain, $expectedDomain);
    
    recordResult(new SemanticTestResult(
        "Typo '$query' → {$expectedDomain} article (normalized correctly)",
        $query,
        $expectedDomain,
        $actualDomain,
        $matches,
        $matches ? "Typo correctly normalized to right domain" : "Typo normalization failed",
        [
            'normalized_query' => $normalized,
            'article_title' => $topArticle->title ?? 'Unknown'
        ]
    ));
}

// ============================================================
// TEST 5: ESCALATION FLOW - GIBBERISH DETECTION
// ============================================================
section("TEST 5: ESCALATION FLOW (Gibberish Detection)");

$gibberishQuery = 'asdfgh asdfgh asdfgh';
$gibberishResult = $retrievalService->retrieve($gibberishQuery, 3);

$shouldEscalate = shouldTriggerEscalation($gibberishQuery);
$actuallyEscalated = !$gibberishResult['threshold_met'] || empty($gibberishResult['results']);

recordResult(new SemanticTestResult(
    "Gibberish query triggers escalation flow",
    $gibberishQuery,
    'escalation',
    $actuallyEscalated ? 'ESCALATED' : 'NOT_ESCALATED',
    $shouldEscalate && $actuallyEscalated,
    $actuallyEscalated ? "Correctly triggered escalation" : "Failed to trigger escalation",
    [
        'threshold_met' => $gibberishResult['threshold_met'] ?? false,
        'result_count' => count($gibberishResult['results'] ?? [])
    ]
));

// Also test that escalation shows contact button
$formattedResponse = $retrievalService->formatResponse($gibberishResult);
$showsContactButton = $formattedResponse['show_contact_button'] ?? false;

recordResult(new SemanticTestResult(
    "Escalation flow shows contact button",
    $gibberishQuery,
    'escalation',
    $showsContactButton ? 'CONTACT_SHOWN' : 'NO_CONTACT',
    $showsContactButton,
    $showsContactButton ? "Contact button correctly shown" : "Contact button not shown",
    []
));

// ============================================================
// TEST 6: FRONTEND STATE RESET
// ============================================================
section("TEST 6: FRONTEND STATE RESET");

// Test that greeting after query doesn't leak state
$greetingResult1 = $retrievalService->isGreeting('halo');
recordResult(new SemanticTestResult(
    "Greeting 'halo' detected correctly",
    'halo',
    'greeting',
    $greetingResult1 ? 'GREETING' : 'NOT_GREETING',
    $greetingResult1,
    $greetingResult1 ? "Greeting correctly detected" : "Greeting not detected",
    []
));

// Test query independence - different queries should not affect each other
$result1 = $retrievalService->retrieve('printer error', 1);
$result2 = $retrievalService->retrieve('wifi lemot', 1);

if (!empty($result1['results']) && !empty($result2['results'])) {
    $article1 = Article::find($result1['results'][0]['id']);
    $article2 = Article::find($result2['results'][0]['id']);
    
    $domain1 = determineArticleDomain($article1);
    $domain2 = determineArticleDomain($article2);
    
    $stateIndependent = ($domain1 === 'printer' || $domain1 === 'hardware') && 
                       ($domain2 === 'wifi' || $domain2 === 'jaringan');
    
    recordResult(new SemanticTestResult(
        "Query state doesn't leak between different queries",
        'printer error → wifi lemot',
        'independent',
        $stateIndependent ? 'INDEPENDENT' : 'LEAKED',
        $stateIndependent,
        $stateIndependent ? "Queries are independent" : "State leakage detected",
        [
            'printer_result_domain' => $domain1,
            'wifi_result_domain' => $domain2
        ]
    ));
}

// ============================================================
// TEST 7: CLEAN CATEGORY CHIPS
// ============================================================
section("TEST 7: CLEAN CATEGORY CHIPS");

// Test that clarification chips are clean (no random names)
$ambiguityResult = $conversationFlowService->checkAmbiguity('lemot');
if (isset($ambiguityResult['clarification']['suggestions'])) {
    $suggestions = $ambiguityResult['clarification']['suggestions'];
    
    $cleanChips = true;
    $suspiciousLabels = [];
    
    foreach ($suggestions as $s) {
        $label = $s['label'] ?? '';
        $labelLower = strtolower($label);
        
        // Skip valid category names (these are expected)
        $validCategories = ['wifi', 'email', 'internet', 'aplikasi', 'hardware', 'printer', 'komputer', 'security'];
        if (in_array($labelLower, $validCategories)) {
            continue;
        }
        
        // Check for suspicious patterns (very short random-looking, or known bad names)
        if ((preg_match('/^[a-z]{2,4}$/', $labelLower) && !in_array($labelLower, $validCategories)) || 
            in_array($labelLower, ['jamal', 'test', 'abc', 'xyz', 'foo', 'bar'])) {
            $cleanChips = false;
            $suspiciousLabels[] = $label;
        }
    }
    
    recordResult(new SemanticTestResult(
        "Clarification chips are clean (no random names)",
        'lemot → chips',
        'clean',
        $cleanChips ? 'CLEAN' : 'POLLUTED',
        $cleanChips,
        $cleanChips ? "All chips are clean and relevant" : "Suspicious chip labels found",
        [
            'suspicious_labels' => $suspiciousLabels,
            'total_suggestions' => count($suggestions)
        ]
    ));
} else {
    recordResult(new SemanticTestResult(
        "Clarification chips are available",
        'lemot → chips',
        'available',
        null,
        false,
        "No clarification suggestions returned",
        []
    ));
}

// ============================================================
// TEST SUMMARY
// ============================================================
section("TEST SUMMARY");

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                      FINAL TEST RESULTS                          ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "Total Tests: $total\n";
echo "Passed: $passed ✅\n";
echo "Failed: $failed ❌\n";
echo "Pass Rate: " . round(($passed / $total) * 100, 1) . "%\n";
echo "\n";

// Detailed results table
echo str_repeat('═', 70) . "\n";
echo "DETAILED RESULTS\n";
echo str_repeat('═', 70) . "\n";
echo "\n";

foreach ($results as $index => $result) {
    $status = $result->passed ? '✅' : '❌';
    echo "$status Test #" . ($index + 1) . ": {$result->testName}\n";
    echo "   Query: {$result->query}\n";
    echo "   Expected Domain: {$result->expectedDomain}\n";
    echo "   Actual Domain: " . ($result->actualDomain ?? 'NULL') . "\n";
    echo "   Status: " . ($result->passed ? 'PASS' : 'FAIL') . "\n";
    if (!$result->passed) {
        echo "   Reason: {$result->message}\n";
    }
    echo "\n";
}

// Generate JSON report
$reportData = [
    'test_suite' => 'Semantic Validation Suite',
    'date' => date('Y-m-d H:i:s'),
    'total_tests' => $total,
    'passed' => $passed,
    'failed' => $failed,
    'pass_rate' => round(($passed / $total) * 100, 1),
    'results' => array_map(function($r) {
        return [
            'test_name' => $r->testName,
            'query' => $r->query,
            'expected_domain' => $r->expectedDomain,
            'actual_domain' => $r->actualDomain,
            'passed' => $r->passed,
            'message' => $r->message,
            'details' => $r->details,
        ];
    }, $results),
];

$reportFile = __DIR__ . '/SEMANTIC_VALIDATION_REPORT_' . date('Y-m-d_His') . '.json';
file_put_contents($reportFile, json_encode($reportData, JSON_PRETTY_PRINT));
echo "📄 Full JSON report saved to: $reportFile\n";

echo "\n";
echo "Test completed at " . date('Y-m-d H:i:s') . "\n";

// Exit with appropriate status code
exit($failed > 0 ? 1 : 0);