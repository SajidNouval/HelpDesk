<?php

require __DIR__.'/vendor/autoload.php';

use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\CosineSimilarityService;
use App\Services\Chatbot\ChatbotRetrievalService;
use App\Models\Article;
use Illuminate\Support\Facades\DB;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== DEBUG RETRIEVAL BUG: TITLE 'wwww' NOT MATCHING ===\n\n";

// Test data
$articleTitle = "wwww";
$articleContent = "w";
$userQuery = "artikel tentang w kalau ga salah judulnya wwww";

echo "TEST DATA:\n";
echo "Article Title: '$articleTitle'\n";
echo "Article Content: '$articleContent'\n";
echo "User Query: '$userQuery'\n\n";

// Initialize services
$preprocessor = new PreprocessingService();
$tfidfService = new TfidfService($preprocessor);
$similarityService = new CosineSimilarityService();
$retrievalService = new ChatbotRetrievalService($preprocessor, $tfidfService, $similarityService);

echo "=== STEP 1: PREPROCESSING ANALYSIS ===\n\n";

// Test preprocessing step by step
echo "1.1 Query Preprocessing:\n";
echo "    Original: '$userQuery'\n";
$processedQuery = $preprocessor->preprocess($userQuery);
echo "    Processed tokens: " . json_encode($processedQuery) . "\n";
echo "    Contains 'wwww'? " . (in_array('wwww', $processedQuery) ? 'YES' : 'NO') . "\n\n";

echo "1.2 Title Preprocessing:\n";
echo "    Original: '$articleTitle'\n";
$processedTitle = $preprocessor->preprocess($articleTitle);
echo "    Processed tokens: " . json_encode($processedTitle) . "\n";
echo "    Contains 'wwww'? " . (in_array('wwww', $processedTitle) ? 'YES' : 'NO') . "\n\n";

echo "1.3 Content Preprocessing:\n";
echo "    Original: '$articleContent'\n";
$processedContent = $preprocessor->preprocess($articleContent);
echo "    Processed tokens: " . json_encode($processedContent) . "\n\n";

echo "=== STEP 2: INDIVIDUAL PREPROCESSING STEPS ===\n\n";

// Trace each preprocessing step
$text = $userQuery;
echo "2.1 Case Folding:\n";
$reflector = new ReflectionClass($preprocessor);
$method = $reflector->getMethod('caseFolding');
$method->setAccessible(true);
$afterCase = $method->invoke($preprocessor, $text);
echo "    Input: '$text'\n";
echo "    Output: '$afterCase'\n\n";

echo "2.2 Cleaning:\n";
$method = $reflector->getMethod('cleaning');
$method->setAccessible(true);
$afterClean = $method->invoke($preprocessor, $afterCase);
echo "    Input: '$afterCase'\n";
echo "    Output: '$afterClean'\n\n";

echo "2.3 Tokenization:\n";
$method = $reflector->getMethod('tokenize');
$method->setAccessible(true);
$tokens = $method->invoke($preprocessor, $afterClean);
echo "    Input: '$afterClean'\n";
echo "    Output: " . json_encode($tokens) . "\n\n";

echo "2.4 Stopword Removal:\n";
$method = $reflector->getMethod('removeStopwords');
$method->setAccessible(true);
$afterStopwords = $method->invoke($preprocessor, $tokens);
echo "    Input: " . json_encode($tokens) . "\n";
echo "    Output: " . json_encode($afterStopwords) . "\n";
echo "    Is 'wwww' removed? " . (in_array('wwww', $tokens) && !in_array('wwww', $afterStopwords) ? 'YES (STOPWORD)' : 'NO') . "\n\n";

echo "2.5 Stemming:\n";
$method = $reflector->getMethod('stemAll');
$method->setAccessible(true);
$afterStem = $method->invoke($preprocessor, $afterStopwords);
echo "    Input: " . json_encode($afterStopwords) . "\n";
echo "    Output: " . json_encode($afterStem) . "\n";
echo "    Is 'wwww' changed? " . (in_array('wwww', $afterStopwords) && !in_array('wwww', $afterStem) ? 'YES (STEMMED)' : 'NO') . "\n\n";

echo "2.6 Minimum Length Filter (>=2 chars):\n";
$filtered = array_values(array_filter($afterStem, fn($t) => mb_strlen($t) >= 2));
echo "    Input: " . json_encode($afterStem) . "\n";
echo "    Output: " . json_encode($filtered) . "\n";
echo "    Is 'wwww' removed? " . (in_array('wwww', $afterStem) && !in_array('wwww', $filtered) ? 'YES (TOO SHORT)' : 'NO') . "\n\n";

echo "=== STEP 3: DOCUMENT PREPROCESSING (AS USED IN RETRIEVAL) ===\n\n";

// Simulate what happens in prepareDocuments()
echo "3.1 Preprocess each field separately:\n";
$titleTokens = $preprocessor->preprocess($articleTitle);
$excerptTokens = $preprocessor->preprocess('');
$keywordsTokens = $preprocessor->preprocess('');
$contentTokens = $preprocessor->preprocess($articleContent);

echo "    Title tokens: " . json_encode($titleTokens) . "\n";
echo "    Excerpt tokens: " . json_encode($excerptTokens) . "\n";
echo "    Keywords tokens: " . json_encode($keywordsTokens) . "\n";
echo "    Content tokens: " . json_encode($contentTokens) . "\n\n";

echo "3.2 Combine tokens with weights (as in prepareDocuments):\n";
$allTokens = [];

// Add title tokens with weight (multiply occurrences)
foreach ($titleTokens as $token) {
    $allTokens[] = $token;
    $allTokens[] = $token; // Extra weight for title
}

// Add excerpt tokens with weight
foreach ($excerptTokens as $token) {
    $allTokens[] = $token;
    if (rand(0, 1) === 0) {
        $allTokens[] = $token;
    }
}

// Add keywords tokens with weight
foreach ($keywordsTokens as $token) {
    $allTokens[] = $token;
    if (rand(0, 1) === 0) {
        $allTokens[] = $token;
    }
}

// Add content tokens
$allTokens = array_merge($allTokens, $contentTokens);

echo "    Combined tokens: " . json_encode($allTokens) . "\n";
echo "    Contains 'wwww'? " . (in_array('wwww', $allTokens) ? 'YES' : 'NO') . "\n";
echo "    Count of 'wwww': " . (array_count_values($allTokens)['wwww'] ?? 0) . "\n\n";

echo "3.3 Calculate term frequency:\n";
$frequency = [];
foreach ($allTokens as $token) {
    $frequency[$token] = ($frequency[$token] ?? 0) + 1;
}
echo "    Frequency array: " . json_encode($frequency) . "\n";
echo "    'wwww' frequency: " . ($frequency['wwww'] ?? 0) . "\n\n";

echo "=== STEP 4: TF-IDF VECTOR ANALYSIS ===\n\n";

// Create a mock document structure like in prepareDocuments
$documents = [
    1 => [
        'text' => implode(' ', $allTokens),
        'frequency' => $frequency,
        'title' => $articleTitle,
        'title_tokens' => $titleTokens,
        'excerpt' => '',
        'keywords' => '',
        'slug' => 'test-article',
        'category_id' => 1,
    ]
];

echo "4.1 Build TF-IDF vectors:\n";
$documentTermFrequencies = [1 => $frequency];
$idf = $tfidfService->calculateIDF($documentTermFrequencies);
$tf = $tfidfService->calculateTF($frequency);
$tfidfVector = $tfidfService->calculateTFIDF($tf, $idf);

echo "    IDF scores: " . json_encode($idf) . "\n";
echo "    TF scores: " . json_encode($tf) . "\n";
echo "    TF-IDF vector: " . json_encode($tfidfVector) . "\n";
echo "    Contains 'wwww'? " . (isset($tfidfVector['wwww']) ? 'YES' : 'NO') . "\n";
echo "    'wwww' TF-IDF weight: " . ($tfidfVector['wwww'] ?? 0) . "\n\n";

echo "4.2 Calculate query TF-IDF:\n";
$queryVector = $tfidfService->calculateQueryTFIDF($userQuery, $idf);
echo "    Query vector: " . json_encode($queryVector) . "\n";
echo "    Contains 'wwww'? " . (isset($queryVector['wwww']) ? 'YES' : 'NO') . "\n";
echo "    'wwww' weight: " . ($queryVector['wwww'] ?? 0) . "\n\n";

echo "=== STEP 5: COSINE SIMILARITY CALCULATION ===\n\n";

echo "5.1 Base cosine similarity:\n";
$baseSimilarity = $similarityService->calculate($queryVector, $tfidfVector);
echo "    Query vector: " . json_encode($queryVector) . "\n";
echo "    Document vector: " . json_encode($tfidfVector) . "\n";
echo "    Base similarity: $baseSimilarity\n\n";

echo "5.2 Title boost calculation:\n";
$titleTokens = $documents[1]['title_tokens'];
$queryTerms = array_keys($queryVector);
$matchedTerms = 0;
$totalQueryTerms = count($queryTerms);

echo "    Title tokens: " . json_encode($titleTokens) . "\n";
echo "    Query terms: " . json_encode($queryTerms) . "\n";

foreach ($queryTerms as $term) {
    if (in_array($term, $titleTokens)) {
        $matchedTerms++;
    }
}

echo "    Matched terms: $matchedTerms\n";
echo "    Total query terms: $totalQueryTerms\n";
$titleMatchRatio = $totalQueryTerms > 0 ? $matchedTerms / $totalQueryTerms : 0;
echo "    Title match ratio: $titleMatchRatio\n";
$titleBoost = $titleMatchRatio * 0.5;
echo "    Title boost factor: $titleBoost\n\n";

$boostedSimilarity = $baseSimilarity + ($baseSimilarity * $titleBoost);
echo "5.3 Final boosted similarity:\n";
echo "    Base: $baseSimilarity\n";
echo "    Boost: " . ($baseSimilarity * $titleBoost) . "\n";
echo "    Final: $boostedSimilarity\n\n";

echo "=== STEP 6: THRESHOLD CHECK ===\n\n";
$threshold = 0.05;
echo "Similarity threshold: $threshold\n";
echo "Final similarity: $boostedSimilarity\n";
echo "Meets threshold? " . ($boostedSimilarity >= $threshold ? 'YES' : 'NO') . "\n\n";

echo "=== STEP 7: FULL RETRIEVAL TEST ===\n\n";

// Create a real article in database for testing
echo "7.1 Creating test article in database...\n";
try {
    $testArticle = Article::create([
        'title' => $articleTitle,
        'content' => $articleContent,
        'excerpt' => 'Test article for debugging',
        'keywords' => 'test,debug',
        'slug' => 'test-article-wwww-' . time(),
        'category_id' => 1,
        'is_published' => true,
        'publish_status' => 'approved',
        'views' => 0,
    ]);
    echo "    Created article ID: {$testArticle->id}\n\n";
} catch (Exception $e) {
    echo "    Error creating article: " . $e->getMessage() . "\n\n";
    exit(1);
}

echo "7.2 Running full retrieval service...\n";
try {
    $result = $retrievalService->retrieve($userQuery, 3);
    
    echo "    Retrieval result:\n";
    echo "    Total results: " . $result['total'] . "\n";
    echo "    Threshold met: " . ($result['threshold_met'] ? 'YES' : 'NO') . "\n";
    echo "    Max similarity: " . $result['max_similarity'] . "\n";
    
    if (!empty($result['results'])) {
        echo "    Top results:\n";
        foreach ($result['results'] as $i => $article) {
            echo "      " . ($i + 1) . ". {$article['title']} (similarity: {$article['similarity']})\n";
        }
    } else {
        echo "    No results found - FALLBACK TRIGGERED\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "    Error: " . $e->getMessage() . "\n";
    echo "    Trace: " . $e->getTraceAsString() . "\n\n";
}

// Cleanup
echo "7.3 Cleaning up test article...\n";
$testArticle->delete();
echo "    Deleted test article\n\n";

echo "=== DEBUG SUMMARY ===\n\n";

$issues = [];

if (!in_array('wwww', $processedQuery)) {
    $issues[] = "Query preprocessing removes 'wwww' token";
}

if (!in_array('wwww', $processedTitle)) {
    $issues[] = "Title preprocessing removes 'wwww' token";
}

if (!isset($tfidfVector['wwww'])) {
    $issues[] = "Document TF-IDF vector does not contain 'wwww'";
}

if (!isset($queryVector['wwww'])) {
    $issues[] = "Query TF-IDF vector does not contain 'wwww'";
}

if ($boostedSimilarity < $threshold) {
    $issues[] = "Similarity score ($boostedSimilarity) below threshold ($threshold)";
}

if (empty($issues)) {
    echo "No issues found - retrieval should work correctly.\n";
    echo "If still failing, check:\n";
    echo "  - Article is published and approved\n";
    echo "  - Cache is cleared\n";
    echo "  - Database connection is working\n";
} else {
    echo "IDENTIFIED ISSUES:\n";
    foreach ($issues as $i => $issue) {
        echo "  " . ($i + 1) . ". $issue\n";
    }
}

echo "\n=== END DEBUG ===\n";