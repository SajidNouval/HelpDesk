<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Chatbot\AdvancedRetrievalService;
use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\CosineSimilarityService;
use App\Services\Chatbot\DomainDetectionService;
use App\Services\Chatbot\VocabularyService;
use App\Services\Chatbot\ImportantPhraseService;

$preprocessor = new PreprocessingService();
$tfidfService = new TfidfService($preprocessor);
$similarityService = new CosineSimilarityService();
$domainDetector = new DomainDetectionService($preprocessor);
$vocabularyService = new VocabularyService();
$phraseService = new ImportantPhraseService();
$service = new AdvancedRetrievalService($preprocessor, $tfidfService, $similarityService, $domainDetector, $vocabularyService, $phraseService);

$queries = [
    'wifi',
    'printer',
    'email',
    'outlook',
    'vpn',
    'mikrotik',
    'cisco',
    'illit',
    'sepak bola',
];

foreach ($queries as $query) {
    $domainInfo = $domainDetector->detectDomain($query);
    $reflection = new ReflectionClass($service);
    $singleIntent = $reflection->getMethod('singleIntentRetrieval');
    $singleIntent->setAccessible(true);
    $result = $singleIntent->invoke($service, $query, 5);
    echo "QUERY: {$query}\n";
    echo "  domain_detected: " . json_encode($domainInfo, JSON_UNESCAPED_UNICODE) . "\n";
    echo "  total_results: " . count($result['results']) . "\n";
    echo "  threshold_met: " . ($result['threshold_met'] ? 'true' : 'false') . "\n";
    echo "  max_score: " . ($result['max_similarity'] ?? 0) . "\n";
    echo "  ids: " . json_encode(array_column($result['results'], 'id'), JSON_UNESCAPED_UNICODE) . "\n";
    foreach ($result['results'] as $article) {
        echo "    - " . $article['title'] . " (score=" . $article['final_score'] . ", category=" . ($article['category_name'] ?? '') . ")\n";
    }
    echo "\n";
}
