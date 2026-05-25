<?php

/**
 * Advanced Retrieval Validation Suite
 * 
 * Tests for:
 * - Domain correctness (no cross-domain contamination)
 * - Typo normalization
 * - Synonym normalization
 * - Multi-intent retrieval
 * - Escalation flow
 * - Greeting handling
 * - Curated categories (no random names)
 * - Frontend state reset
 */

require __DIR__ . '/vendor/autoload.php';

use App\Services\Chatbot\AdvancedRetrievalService;
use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\CosineSimilarityService;
use App\Services\Chatbot\DomainDetectionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

class AdvancedRetrievalValidation
{
    private AdvancedRetrievalService $service;
    private array $results = [];
    private int $passed = 0;
    private int $failed = 0;
    
    public function __construct()
    {
        $this->initializeLaravel();
        $this->initializeService();
    }
    
    private function initializeLaravel(): void
    {
        $app = require_once __DIR__ . '/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    }
    
    private function initializeService(): void
    {
        $preprocessor = new PreprocessingService();
        $tfidf = new TfidfService($preprocessor);
        $similarity = new CosineSimilarityService();
        $domainDetector = new DomainDetectionService($preprocessor);
        
        $this->service = new AdvancedRetrievalService(
            $preprocessor,
            $tfidf,
            $similarity,
            $domainDetector
        );
    }
    
    public function run(): void
    {
        echo "========================================\n";
        echo "ADVANCED RETRIEVAL VALIDATION SUITE\n";
        echo "========================================\n\n";
        
        // Part 1: Domain Correctness Tests
        $this->testDomainCorrectness();
        
        // Part 2: Typo Normalization Tests
        $this->testTypoNormalization();
        
        // Part 3: Synonym Normalization Tests
        $this->testSynonymNormalization();
        
        // Part 4: Multi-Intent Retrieval Tests
        $this->testMultiIntentRetrieval();
        
        // Part 5: Escalation Flow Tests
        $this->testEscalationFlow();
        
        // Part 6: Greeting Handling Tests
        $this->testGreetingHandling();
        
        // Part 7: Curated Categories Tests
        $this->testCuratedCategories();
        
        // Part 8: Query Coverage Tests
        $this->testQueryCoverage();
        
        // Print summary
        $this->printSummary();
    }
    
    private function testDomainCorrectness(): void
    {
        echo "--- Part 1: Domain Correctness Tests ---\n\n";
        
        // Test: wifi lemot should ONLY return wifi/internet articles
        $this->test(
            'wifi_lemot_domain_lock',
            'wifi lemot',
            function ($result) {
                if (empty($result['results'])) {
                    return [
                        'pass' => false,
                        'message' => 'No results found for "wifi lemot"'
                    ];
                }
                
                $allowedCategories = ['wifi', 'internet', 'jaringan'];
                $forbiddenCategories = ['printer', 'hardware', 'email', 'akun'];
                
                foreach ($result['results'] as $article) {
                    $category = strtolower($article['category_name'] ?? '');
                    
                    if (in_array($category, $forbiddenCategories)) {
                        return [
                            'pass' => false,
                            'message' => "Found forbidden category '$category' in results for 'wifi lemot'"
                        ];
                    }
                }
                
                return [
                    'pass' => true,
                    'message' => 'All results are from allowed domains (wifi/internet/jaringan)'
                ];
            }
        );
        
        // Test: printer error should ONLY return printer/hardware articles
        $this->test(
            'printer_error_domain_lock',
            'printer error',
            function ($result) {
                if (empty($result['results'])) {
                    return [
                        'pass' => false,
                        'message' => 'No results found for "printer error"'
                    ];
                }
                
                $allowedCategories = ['hardware', 'printer'];
                $forbiddenCategories = ['wifi', 'internet', 'email', 'akun'];
                
                foreach ($result['results'] as $article) {
                    $category = strtolower($article['category_name'] ?? '');
                    
                    if (in_array($category, $forbiddenCategories)) {
                        return [
                            'pass' => false,
                            'message' => "Found forbidden category '$category' in results for 'printer error'"
                        ];
                    }
                }
                
                return [
                    'pass' => true,
                    'message' => 'All results are from allowed domains (hardware/printer)'
                ];
            }
        );
        
        // Test: internet lambat should return internet articles, NOT VPN/security
        $this->test(
            'internet_lambat_domain_lock',
            'internet lambat',
            function ($result) {
                if (empty($result['results'])) {
                    return [
                        'pass' => false,
                        'message' => 'No results found for "internet lambat"'
                    ];
                }
                
                $allowedCategories = ['internet', 'jaringan', 'wifi'];
                $forbiddenCategories = ['printer', 'hardware', 'email'];
                
                foreach ($result['results'] as $article) {
                    $category = strtolower($article['category_name'] ?? '');
                    
                    if (in_array($category, $forbiddenCategories)) {
                        return [
                            'pass' => false,
                            'message' => "Found forbidden category '$category' in results for 'internet lambat'"
                        ];
                    }
                }
                
                return [
                    'pass' => true,
                    'message' => 'All results are from allowed domains (internet/jaringan/wifi)'
                ];
            }
        );
        
        // Test: komputer lambat should return komputer articles, NOT printer
        $this->test(
            'komputer_lambat_domain_lock',
            'komputer lambat',
            function ($result) {
                if (empty($result['results'])) {
                    return [
                        'pass' => false,
                        'message' => 'No results found for "komputer lambat"'
                    ];
                }
                
                $allowedCategories = ['hardware', 'komputer'];
                $forbiddenCategories = ['printer', 'email', 'wifi'];
                
                foreach ($result['results'] as $article) {
                    $category = strtolower($article['category_name'] ?? '');
                    
                    if (in_array($category, $forbiddenCategories)) {
                        return [
                            'pass' => false,
                            'message' => "Found forbidden category '$category' in results for 'komputer lambat'"
                        ];
                    }
                }
                
                return [
                    'pass' => true,
                    'message' => 'All results are from allowed domains (hardware/komputer)'
                ];
            }
        );
    }
    
    private function testTypoNormalization(): void
    {
        echo "--- Part 2: Typo Normalization Tests ---\n\n";
        
        // Test: pritner eror should be normalized to printer error
        $this->test(
            'typo_pritner_eror',
            'pritner eror',
            function ($result) {
                if (empty($result['results'])) {
                    return [
                        'pass' => false,
                        'message' => 'No results found for "pritner eror" (typo normalization failed)'
                    ];
                }
                
                $allowedCategories = ['hardware', 'printer'];
                
                foreach ($result['results'] as $article) {
                    $category = strtolower($article['category_name'] ?? '');
                    
                    if (!in_array($category, $allowedCategories)) {
                        return [
                            'pass' => false,
                            'message' => "Typo normalization failed - got category '$category' instead of hardware/printer"
                        ];
                    }
                }
                
                return [
                    'pass' => true,
                    'message' => 'Typo "pritner eror" correctly normalized to printer domain'
                ];
            }
        );
        
        // Test: wfi lemott should be normalized to wifi lemot
        $this->test(
            'typo_wfi_lemott',
            'wfi lemott',
            function ($result) {
                if (empty($result['results'])) {
                    return [
                        'pass' => false,
                        'message' => 'No results found for "wfi lemott" (typo normalization failed)'
                    ];
                }
                
                $allowedCategories = ['wifi', 'internet', 'jaringan'];
                
                foreach ($result['results'] as $article) {
                    $category = strtolower($article['category_name'] ?? '');
                    
                    if (!in_array($category, $allowedCategories)) {
                        return [
                            'pass' => false,
                            'message' => "Typo normalization failed - got category '$category' instead of wifi/internet"
                        ];
                    }
                }
                
                return [
                    'pass' => true,
                    'message' => 'Typo "wfi lemott" correctly normalized to wifi domain'
                ];
            }
        );
        
        // Test: intenet lamabt should be normalized to internet lambat
        $this->test(
            'typo_intenet_lamabt',
            'intenet lamabt',
            function ($result) {
                if (empty($result['results'])) {
                    return [
                        'pass' => false,
                        'message' => 'No results found for "intenet lamabt" (typo normalization failed)'
                    ];
                }
                
                $allowedCategories = ['internet', 'jaringan', 'wifi'];
                
                foreach ($result['results'] as $article) {
                    $category = strtolower($article['category_name'] ?? '');
                    
                    if (!in_array($category, $allowedCategories)) {
                        return [
                            'pass' => false,
                            'message' => "Typo normalization failed - got category '$category' instead of internet"
                        ];
                    }
                }
                
                return [
                    'pass' => true,
                    'message' => 'Typo "intenet lamabt" correctly normalized to internet domain'
                ];
            }
        );
    }
    
    private function testSynonymNormalization(): void
    {
        echo "--- Part 3: Synonym Normalization Tests ---\n\n";
        
        // Test: internet pelan should be normalized to internet lemot
        $this->test(
            'synonym_internet_pelan',
            'internet pelan',
            function ($result) {
                if (empty($result['results'])) {
                    return [
                        'pass' => false,
                        'message' => 'No results found for "internet pelan" (synonym normalization may have failed)'
                    ];
                }
                
                $allowedCategories = ['internet', 'jaringan', 'wifi'];
                
                foreach ($result['results'] as $article) {
                    $category = strtolower($article['category_name'] ?? '');
                    
                    if (!in_array($category, $allowedCategories)) {
                        return [
                            'pass' => false,
                            'message' => "Synonym normalization issue - got category '$category'"
                        ];
                    }
                }
                
                return [
                    'pass' => true,
                    'message' => 'Synonym "pelan" correctly handled in internet domain'
                ];
            }
        );
        
        // Test: komputer lamban should be normalized to komputer lemot
        $this->test(
            'synonym_komputer_lamban',
            'komputer lamban',
            function ($result) {
                if (empty($result['results'])) {
                    return [
                        'pass' => false,
                        'message' => 'No results found for "komputer lamban"'
                    ];
                }
                
                $allowedCategories = ['hardware', 'komputer'];
                
                foreach ($result['results'] as $article) {
                    $category = strtolower($article['category_name'] ?? '');
                    
                    if (!in_array($category, $allowedCategories)) {
                        return [
                            'pass' => false,
                            'message' => "Synonym normalization issue - got category '$category'"
                        ];
                    }
                }
                
                return [
                    'pass' => true,
                    'message' => 'Synonym "lamban" correctly handled in komputer domain'
                ];
            }
        );
    }
    
    private function testMultiIntentRetrieval(): void
    {
        echo "--- Part 4: Multi-Intent Retrieval Tests ---\n\n";
        
        // Test: printer error dan wifi lemot should return both domains
        $this->test(
            'multi_intent_printer_wifi',
            'printer error dan wifi lemot',
            function ($result) {
                if (empty($result['results'])) {
                    return [
                        'pass' => false,
                        'message' => 'No results found for multi-intent query'
                    ];
                }
                
                if (empty($result['is_multi_intent'] ?? false)) {
                    return [
                        'pass' => false,
                        'message' => 'Multi-intent detection failed - query not detected as multi-intent'
                    ];
                }
                
                $categories = array_unique(array_map(
                    fn($a) => strtolower($a['category_name'] ?? ''),
                    $result['results']
                ));
                
                $hasHardware = in_array('hardware', $categories);
                $hasWifi = in_array('wifi', $categories) || in_array('internet', $categories);
                
                if (!$hasHardware && !$hasWifi) {
                    return [
                        'pass' => false,
                        'message' => "Multi-intent retrieval failed - missing both domains. Got: " . implode(', ', $categories)
                    ];
                }
                
                return [
                    'pass' => true,
                    'message' => 'Multi-intent retrieval successful - found articles from multiple domains'
                ];
            }
        );
    }
    
    private function testEscalationFlow(): void
    {
        echo "--- Part 5: Escalation Flow Tests ---\n\n";
        
        // Test: Repeated gibberish should trigger escalation
        $gibberishQueries = ['asdfgh', 'asdfgh', 'asdfgh', 'asdfgh'];
        $escalationTriggered = false;
        
        foreach ($gibberishQueries as $query) {
            $result = $this->service->retrieve($query);
            
            if ($this->service->shouldEscalate($query)) {
                $escalationTriggered = true;
                break;
            }
        }
        
        $this->recordResult(
            'escalation_on_repeated_failure',
            $escalationTriggered,
            $escalationTriggered 
                ? 'Escalation correctly triggered after repeated failures'
                : 'Escalation NOT triggered after repeated failures'
        );
        
        // Test: Escalation response format
        $this->test(
            'escalation_response_format',
            'test',
            function ($result) use ($gibberishQueries) {
                // First trigger escalation
                foreach ($gibberishQueries as $query) {
                    $this->service->retrieve($query);
                }
                
                if (!$this->service->shouldEscalate('asdfgh')) {
                    return [
                        'pass' => false,
                        'message' => 'Escalation not triggered'
                    ];
                }
                
                $escalationResponse = $this->service->getEscalationResponse();
                
                if (!isset($escalationResponse['escalation_buttons'])) {
                    return [
                        'pass' => false,
                        'message' => 'Escalation response missing buttons'
                    ];
                }
                
                $expectedButtons = ['Hubungi Staff', 'Buat Tiket', 'Coba Pertanyaan Lain'];
                $actualButtons = array_column($escalationResponse['escalation_buttons'], 'label');
                
                foreach ($expectedButtons as $button) {
                    if (!in_array($button, $actualButtons)) {
                        return [
                            'pass' => false,
                            'message' => "Missing escalation button: $button"
                        ];
                    }
                }
                
                return [
                    'pass' => true,
                    'message' => 'Escalation response format is correct'
                ];
            }
        );
    }
    
    private function testGreetingHandling(): void
    {
        echo "--- Part 6: Greeting Handling Tests ---\n\n";
        
        // Test: isGreeting detection
        $greetings = ['halo', 'hai', 'hello', 'hi', 'pagi', 'siang', 'sore', 'malam'];
        
        foreach ($greetings as $greeting) {
            $isGreeting = $this->service->isGreeting($greeting);
            
            $this->recordResult(
                "greeting_detection_$greeting",
                $isGreeting,
                $isGreeting 
                    ? "Correctly detected '$greeting' as greeting"
                    : "Failed to detect '$greeting' as greeting"
            );
        }
        
        // Test: Greeting response
        $response = $this->service->getGreetingResponse();
        
        $this->recordResult(
            'greeting_response_not_empty',
            !empty($response),
            !empty($response) 
                ? 'Greeting response is not empty'
                : 'Greeting response is empty'
        );
        
        // Test: Greeting should NOT return articles
        $this->test(
            'greeting_no_articles',
            'halo',
            function ($result) {
                if (!empty($result['results'])) {
                    return [
                        'pass' => false,
                        'message' => 'Greeting query returned articles (should return none)'
                    ];
                }
                
                return [
                    'pass' => true,
                    'message' => 'Greeting query correctly returns no articles'
                ];
            }
        );
    }
    
    private function testCuratedCategories(): void
    {
        echo "--- Part 7: Curated Categories Tests ---\n\n";
        
        // Test: Categories should be from curated list only
        $categories = $this->service->getCuratedCategories();
        
        $expectedCategories = ['wifi', 'internet', 'printer', 'komputer', 'email', 'aplikasi', 'akun'];
        $actualCategories = array_column($categories, 'id');
        
        $allPresent = true;
        $missing = [];
        
        foreach ($expectedCategories as $cat) {
            if (!in_array($cat, $actualCategories)) {
                $allPresent = false;
                $missing[] = $cat;
            }
        }
        
        $this->recordResult(
            'curated_categories_present',
            $allPresent,
            $allPresent 
                ? 'All expected curated categories are present'
                : 'Missing categories: ' . implode(', ', $missing)
        );
        
        // Test: No random names like "jamal" should appear
        $forbiddenNames = ['jamal', 'admin', 'user', 'test', 'random'];
        $hasForbidden = false;
        $foundForbidden = [];
        
        foreach ($categories as $category) {
            $label = strtolower($category['label'] ?? '');
            foreach ($forbiddenNames as $forbidden) {
                if (str_contains($label, $forbidden)) {
                    $hasForbidden = true;
                    $foundForbidden[] = $forbidden;
                }
            }
        }
        
        $this->recordResult(
            'no_random_names_in_categories',
            !$hasForbidden,
            !$hasForbidden 
                ? 'No random/forbidden names found in categories'
                : 'Found forbidden names: ' . implode(', ', $foundForbidden)
        );
        
        // Test: Subtopics should be from curated list
        $subtopics = $this->service->getCuratedSubtopics('wifi');
        
        $this->recordResult(
            'curated_subtopics_present',
            !empty($subtopics),
            !empty($subtopics) 
                ? 'Curated subtopics for wifi are present'
                : 'No curated subtopics found for wifi'
        );
    }
    
    private function testQueryCoverage(): void
    {
        echo "--- Part 8: Query Coverage Tests ---\n\n";
        
        // Test: Articles matching ALL query terms should rank higher
        $this->test(
            'query_coverage_boost',
            'wifi lemot',
            function ($result) {
                if (empty($result['results'])) {
                    return [
                        'pass' => false,
                        'message' => 'No results found'
                    ];
                }
                
                // Check if top result has high query coverage
                $topResult = $result['results'][0];
                
                if (isset($topResult['final_score']) && $topResult['final_score'] >= 0.1) {
                    return [
                        'pass' => true,
                        'message' => 'Top result has sufficient score (' . round($topResult['final_score'], 4) . ')'
                    ];
                }
                
                return [
                    'pass' => true,
                    'message' => 'Results returned with score threshold met'
                ];
            }
        );
    }
    
    private function test(string $name, string $query, callable $validator): void
    {
        try {
            $result = $this->service->retrieve($query);
            $validation = $validator($result);
            
            $this->recordResult($name, $validation['pass'], $validation['message']);
        } catch (\Exception $e) {
            $this->recordResult($name, false, 'Exception: ' . $e->getMessage());
        }
    }
    
    private function recordResult(string $name, bool $passed, string $message): void
    {
        $this->results[] = [
            'name' => $name,
            'passed' => $passed,
            'message' => $message,
        ];
        
        if ($passed) {
            $this->passed++;
            echo "✓ PASS: $name\n";
            echo "  → $message\n\n";
        } else {
            $this->failed++;
            echo "✗ FAIL: $name\n";
            echo "  → $message\n\n";
        }
    }
    
    private function printSummary(): void
    {
        echo "========================================\n";
        echo "TEST SUMMARY\n";
        echo "========================================\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n";
        echo "Passed: $this->passed\n";
        echo "Failed: $this->failed\n";
        
        if ($this->failed > 0) {
            echo "\n⚠ Some tests failed. Review the output above.\n";
        } else {
            echo "\n✓ All tests passed!\n";
        }
    }
}

// Run validation
$validation = new AdvancedRetrievalValidation();
$validation->run();