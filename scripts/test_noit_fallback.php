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

$queries = ['mikrotik','vpn','email','printer','sepak bola'];

foreach ($queries as $q) {
    echo "\n== QUERY: {$q} ==\n";
    $res = $service->retrieve($q,5);
    echo "is_out_of_domain_check: " . json_encode($service->getDebugInfo()['out_of_domain_check'] ?? null) . "\n";
    echo "stages: " . json_encode($service->getDebugInfo()['stages'] ?? []) . "\n";
    echo "total results: " . ($res['total'] ?? 0) . "\n";
    foreach ($res['results'] as $r) {
        echo " - {$r['title']} (id={$r['id']}, final_score={$r['final_score']})\n";
    }
}
