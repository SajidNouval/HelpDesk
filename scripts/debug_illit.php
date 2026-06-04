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
use App\Models\Article;

$preprocessor = new PreprocessingService();
$tfidfService = new TfidfService($preprocessor);
$similarityService = new CosineSimilarityService();
$domainDetector = new DomainDetectionService($preprocessor);
$vocabularyService = new VocabularyService();
$phraseService = new ImportantPhraseService();
$service = new AdvancedRetrievalService($preprocessor, $tfidfService, $similarityService, $domainDetector, $vocabularyService, $phraseService);

$query = 'illit';
$result = $service->retrieve($query, 10);
$debug = $service->getDebugInfo();

$published = Article::where('is_published', true)->where('publish_status', 'approved')->get();
$illitMatches = $published->filter(function ($article) {
    $haystack = strtolower($article->title . ' ' . $article->slug . ' ' . ($article->excerpt ?? '') . ' ' . ($article->content ?? ''));
    return str_contains($haystack, 'illit');
});
$tesssMatches = $published->filter(function ($article) {
    $haystack = strtolower($article->title . ' ' . $article->slug . ' ' . ($article->excerpt ?? '') . ' ' . ($article->content ?? ''));
    return str_contains($haystack, 'tesss');
});

echo "query={$query}\n";
echo "detected_domain=" . json_encode($debug['domain_info'] ?? null, JSON_UNESCAPED_UNICODE) . "\n";
echo "allowed_categories=" . json_encode($debug['allowed_categories'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
echo "domain_candidate_count=" . ($debug['candidate_count'] ?? null) . "\n";
echo "fallback_applied=" . (($debug['fallback_applied'] ?? false) ? 'true' : 'false') . "\n";
echo "expanded_query=" . ($debug['expanded_query'] ?? '') . "\n";
echo "published_article_count=" . $published->count() . "\n";
echo "illit_published_count=" . $illitMatches->count() . "\n";
echo "tesss_published_count=" . $tesssMatches->count() . "\n";
if ($illitMatches->isNotEmpty()) {
    echo "illit_articles=" . json_encode($illitMatches->map(fn($article) => ['id' => $article->id, 'title' => $article->title, 'slug' => $article->slug])->values()->all(), JSON_UNESCAPED_UNICODE) . "\n";
}
if ($tesssMatches->isNotEmpty()) {
    echo "tesss_articles=" . json_encode($tesssMatches->map(fn($article) => ['id' => $article->id, 'title' => $article->title, 'slug' => $article->slug])->values()->all(), JSON_UNESCAPED_UNICODE) . "\n";
}

echo "result_total=" . $result['total'] . "\n";
echo "threshold_met=" . ($result['threshold_met'] ? 'true' : 'false') . "\n";
echo "max_similarity=" . ($result['max_similarity'] ?? 0) . "\n";
echo "final_article_ids=" . json_encode(array_column($result['results'], 'id'), JSON_UNESCAPED_UNICODE) . "\n";
echo "results=" . json_encode($result['results'], JSON_UNESCAPED_UNICODE) . "\n";

echo "debug_stages=" . json_encode($debug['stages'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
if (!empty($debug['scores'])) {
    echo "scores=" . json_encode($debug['scores'], JSON_UNESCAPED_UNICODE) . "\n";
}
