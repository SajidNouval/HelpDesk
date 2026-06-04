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
use App\Models\Category;

// Instantiate services
$preprocessor = new PreprocessingService();
$tfidfService = new TfidfService($preprocessor);
$similarityService = new CosineSimilarityService();
$domainDetector = new DomainDetectionService($preprocessor);
$vocabularyService = new VocabularyService();
$phraseService = new ImportantPhraseService();
$service = new AdvancedRetrievalService($preprocessor, $tfidfService, $similarityService, $domainDetector, $vocabularyService, $phraseService);

// Load categories from DB
$categories = Category::all()->map(fn($c)=>['id'=>$c->id,'name'=>$c->name]);
$categoryNames = $categories->pluck('name')->map(fn($n)=>mb_strtolower(trim($n)))->unique()->values()->all();

// Extract domainCategoryMap values from AdvancedRetrievalService source
$source = file_get_contents(app_path('Services/Chatbot/AdvancedRetrievalService.php'));
preg_match('/private array \$domainCategoryMap = \[(.*?)\];/s', $source, $m);
$domainMapValues = [];
if (!empty($m[1])) {
    // crude parse: find all quoted strings inside the array
    preg_match_all("/'([A-Za-z0-9 _\\-&]+)'/", $m[1], $matches);
    if (!empty($matches[1])) {
        $domainMapValues = array_map(fn($s)=>mb_strtolower(trim($s)), $matches[1]);
    }
}
$domainMapValues = array_values(array_unique($domainMapValues));

// Official categories = intersection of DB categories and domainCategoryMap values
$officialCategories = array_values(array_intersect($categoryNames, $domainMapValues));

echo "All DB categories (count=" . count($categoryNames) . "):\n";
foreach ($categoryNames as $n) echo " - $n\n";

echo "\nDomainCategoryMap categories (unique count=" . count($domainMapValues) . "):\n";
foreach ($domainMapValues as $n) echo " - $n\n";

echo "\nOfficial categories (intersection count=" . count($officialCategories) . "):\n";
foreach ($officialCategories as $n) echo " - $n\n";

// Find specific articles and their categories
$targets = [
    'illit',
    'tesss',
    'mikrotik',
];
foreach ($targets as $t) {
    $art = Article::where('is_published', true)->where('publish_status','approved')
        ->where(function($q) use ($t){
            $q->where('slug', $t)->orWhere('title', $t)->orWhere('title','like','%'. $t . '%');
        })->with('category')->first();
    if ($art) {
        $catName = $art->category->name ?? '';
        $isOfficial = in_array(mb_strtolower(trim($catName)), $officialCategories);
        echo "\nArticle '{$art->title}' (slug={$art->slug}) -> category: {$catName} -> official: " . ($isOfficial? 'yes':'no') . "\n";
    } else {
        echo "\nArticle matching '{$t}' not found in published articles.\n";
    }
}

// Simulation: retrieval only using articles from official categories
$officialCategoryIds = Category::whereIn(DB::raw('LOWER(TRIM(name))'), $officialCategories)->pluck('id')->values()->all();

$queries = ['mikrotik','cisco','vpn','email','printer','illit','tesss','sepak bola'];

$reflect = new ReflectionClass($service);
$prepare = $reflect->getMethod('prepareDocuments'); $prepare->setAccessible(true);
$build = $reflect->getMethod('buildTfidfVectors'); $build->setAccessible(true);
$hybrid = $reflect->getMethod('hybridRanking'); $hybrid->setAccessible(true);
$apply = $reflect->getMethod('applyThresholdAndLimit'); $apply->setAccessible(true);

foreach ($queries as $q) {
    echo "\n=== QUERY: {$q} (restricted to official categories) ===\n";
    $domainInfo = $domainDetector->detectDomain($q);
    $reason = $domainInfo['detected'] ? 'domain_detected' : 'no_domain_detected';
    echo "domain_detected: " . json_encode($domainInfo) . "\n";

    $articles = Article::where('is_published', true)->where('publish_status','approved')->whereIn('category_id', $officialCategoryIds)->with('category')->get();
    echo "candidate_count (official categories): " . $articles->count() . "\n";

    if ($articles->isEmpty()) {
        echo "No articles in official categories.\n";
        continue;
    }

    $docs = $prepare->invoke($service, $articles);
    $tfidfData = $build->invoke($service, $docs);
    $queryVector = $tfidfService->calculateQueryTFIDF($q, $tfidfData['idf']);
    if (empty($queryVector)) { echo "empty query vector\n"; continue; }
    $ranked = $hybrid->invoke($service, $queryVector, $tfidfData['vectors'], $docs, $q, $domainInfo, []);
    $final = $apply->invoke($service, $ranked, 5);
    echo "results: count=" . count($final) . "\n";
    foreach ($final as $r) {
        echo " - {$r['title']} (id={$r['id']}, score={$r['final_score']}, category={$r['category_name']})\n";
    }
    if (empty($final)) echo "No final results above threshold.\n";
}

// Show whether non-official articles could appear (we restricted to official categories, so none should)
$nonOfficialArticles = Article::where('is_published', true)->where('publish_status','approved')
    ->whereNotIn('category_id', $officialCategoryIds)->get();

echo "\nNon-official published articles count: " . $nonOfficialArticles->count() . "\n";
if ($nonOfficialArticles->count() > 0) {
    echo "Examples:\n";
    foreach ($nonOfficialArticles->take(5) as $a) {
        echo " - {$a->title} (category=" . ($a->category->name ?? '') . ")\n";
    }
} else {
    echo "No non-official published articles.\n";
}
