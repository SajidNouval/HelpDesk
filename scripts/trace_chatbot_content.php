<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Models\Article;
use App\Services\Chatbot\AdvancedRetrievalService;
use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\CosineSimilarityService;
use App\Services\Chatbot\DomainDetectionService;
use App\Services\Chatbot\VocabularyService;
use App\Services\Chatbot\ImportantPhraseService;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$preprocessor = new PreprocessingService();
$tfidfService = new TfidfService($preprocessor);
$similarityService = new CosineSimilarityService();
$domainDetector = new DomainDetectionService($preprocessor);
$vocabularyService = new VocabularyService();
$phraseService = new ImportantPhraseService();

$service = new AdvancedRetrievalService(
    $preprocessor,
    $tfidfService,
    $similarityService,
    $domainDetector,
    $vocabularyService,
    $phraseService
);

$queries = [
    'wifi lambat',
    'printer tidak mau print',
];

foreach ($queries as $query) {
    echo "\n=== QUERY: $query ===\n";

    $reflect = new ReflectionClass($service);
    $getDomainFilteredArticles = $reflect->getMethod('getDomainFilteredArticles');
    $getDomainFilteredArticles->setAccessible(true);
    $getPublishedArticles = $reflect->getMethod('getPublishedArticles');
    $getPublishedArticles->setAccessible(true);
    $prepareDocuments = $reflect->getMethod('prepareDocuments');
    $prepareDocuments->setAccessible(true);
    $buildTfidfVectors = $reflect->getMethod('buildTfidfVectors');
    $buildTfidfVectors->setAccessible(true);
    $hybridRanking = $reflect->getMethod('hybridRanking');
    $hybridRanking->setAccessible(true);
    $diversifyResults = $reflect->getMethod('diversifyResults');
    $diversifyResults->setAccessible(true);
    $applyThresholdAndLimit = $reflect->getMethod('applyThresholdAndLimit');
    $applyThresholdAndLimit->setAccessible(true);
    $formatResponse = $reflect->getMethod('formatResponse');
    $formatResponse->setAccessible(true);
    $expandQuery = $reflect->getMethod('expandQuery');
    $expandQuery->setAccessible(true);
    $getAllowedCategories = $reflect->getMethod('getAllowedCategories');
    $getAllowedCategories->setAccessible(true);

    // replicate the service pipeline
    $domainInfo = $domainDetector->detectDomain($query);
    $allowedCategories = $getAllowedCategories->invoke($service, $domainInfo);
    $articles = $getDomainFilteredArticles->invoke($service, $allowedCategories);

    if ($articles->isEmpty()) {
        $articles = $getPublishedArticles->invoke($service);
        echo "DB query returned 0 domain-filtered articles, falling back to all published articles.\n";
    }

    echo "\n-- Database articles loaded (count: " . $articles->count() . ") --\n";
    $sample = [];
    foreach ($articles->take(3) as $article) {
        $sample[] = [
            'id' => $article->id,
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'content_length' => mb_strlen($article->content ?? ''),
            'content_sample' => mb_substr($article->content ?? '', 0, 200),
            'slug' => $article->slug,
        ];
    }
    echo json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    $expandedQuery = $expandQuery->invoke($service, $query, $domainInfo['domain'] ?? null);
    $documents = $prepareDocuments->invoke($service, $articles);
    echo "\n-- Prepared documents keys for first item --\n";
    $firstDoc = reset($documents);
    echo json_encode(array_keys($firstDoc), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    echo "\n-- First document content-related fields --\n";
    echo json_encode([
        'title' => $firstDoc['title'] ?? null,
        'excerpt' => $firstDoc['excerpt'] ?? null,
        'content' => array_key_exists('content', $firstDoc) ? $firstDoc['content'] : null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    $tfidfData = $buildTfidfVectors->invoke($service, $documents);
    $queryVector = $tfidfService->calculateQueryTFIDF($expandedQuery, $tfidfData['idf']);
    $rankedResults = $hybridRanking->invoke($service, $queryVector, $tfidfData['vectors'], $documents, $query, $domainInfo, $allowedCategories);
    $diversified = $diversifyResults->invoke($service, $rankedResults, $documents);
    $finalResults = $applyThresholdAndLimit->invoke($service, $diversified, 5);

    echo "\n-- buildFinalResults / applyThresholdAndLimit output (first item) --\n";
    echo json_encode(array_map(function ($item) {
        return [
            'id' => $item['id'],
            'title' => $item['title'],
            'excerpt' => $item['excerpt'],
            'content' => $item['content'],
            'content_length' => mb_strlen($item['content']),
            'slug' => $item['slug'],
            'category_name' => $item['category_name'] ?? null,
            'final_score' => $item['final_score'],
            'confidence' => $item['confidence'],
        ];
    }, $finalResults), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    $retrievalResult = $service->retrieve($query, 5);
    $shallowRetrieval = [
        'results' => array_map(function ($item) {
            return [
                'id' => $item['id'],
                'title' => $item['title'],
                'excerpt' => $item['excerpt'],
                'content' => $item['content'],
                'slug' => $item['slug'],
                'category_name' => $item['category_name'] ?? null,
                'final_score' => $item['final_score'] ?? null,
                'confidence' => $item['confidence'] ?? null,
            ];
        }, $retrievalResult['results'] ?? []),
        'query' => $retrievalResult['query'] ?? null,
        'total' => $retrievalResult['total'] ?? null,
        'threshold_met' => $retrievalResult['threshold_met'] ?? null,
        'max_similarity' => $retrievalResult['max_similarity'] ?? null,
        'detected_domain' => $retrievalResult['detected_domain'] ?? null,
    ];

    echo "\n-- retrieval() output --\n";
    echo json_encode($shallowRetrieval, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    $formatted = $formatResponse->invoke($service, $retrievalResult);
    echo "\n-- formatResponse() output --\n";
    echo json_encode($formatted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    echo "\n-- Final response JSON --\n";
    echo json_encode($formatted, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    echo "\n-- Code line causing content to be empty --\n";
    echo "AdvancedRetrievalService::applyThresholdAndLimit(), line containing 'content' => \$doc['content'] ?? ''\n";
}

echo "\nDone.\n";
