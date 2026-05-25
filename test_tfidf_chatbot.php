<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\CosineSimilarityService;
use App\Services\Chatbot\ChatbotRetrievalService;
use App\Models\Article;

echo "========================================\n";
echo "TF-IDF Chatbot Test\n";
echo "========================================\n\n";

// Test PreprocessingService
echo "1. Testing PreprocessingService\n";
echo "--------------------------------\n";

$preprocessor = app(PreprocessingService::class);

$testQueries = [
    "Bagaimana cara reset password?",
    "Saya lupa password saya",
    "Email tidak bisa dikirim",
    "Halo apa kabar",
    "Cara membuat tiket baru",
];

foreach ($testQueries as $query) {
    $tokens = $preprocessor->preprocess($query);
    echo "Query: $query\n";
    echo "Tokens: " . implode(', ', $tokens) . "\n\n";
}

// Test TfidfService
echo "\n2. Testing TfidfService\n";
echo "--------------------------------\n";

$tfidfService = app(TfidfService::class);

$testDocs = [
    'doc1' => ['text' => 'Cara reset password adalah dengan klik lupa password di halaman login'],
    'doc2' => ['text' => 'Email tidak bisa dikirim karena konfigurasi SMTP salah'],
    'doc3' => ['text' => 'Cara membuat tiket baru adalah dengan klik tombol buat tiket di halaman bantuan'],
];

$tfidfData = $tfidfService->buildTfidfVectors($testDocs);

echo "Documents: " . count($testDocs) . "\n";
echo "Unique terms: " . count($tfidfData['idf']) . "\n";
echo "IDF scores (first 5):\n";
foreach (array_slice($tfidfData['idf'], 0, 5) as $term => $score) {
    echo "  $term => " . round($score, 4) . "\n";
}

// Test CosineSimilarityService
echo "\n3. Testing CosineSimilarityService\n";
echo "--------------------------------\n";

$similarityService = app(CosineSimilarityService::class);

$query = "bagaimana cara reset password";
$queryVector = $tfidfService->calculateQueryTFIDF($query, $tfidfData['idf']);

echo "Query: $query\n";
echo "Query vector terms: " . implode(', ', array_keys($queryVector)) . "\n\n";

$similarities = $similarityService->calculateBatch($queryVector, $tfidfData['vectors']);

echo "Similarity scores:\n";
foreach ($similarities as $docId => $score) {
    echo "  $docId => " . round($score, 4) . "\n";
}

$topDocs = $similarityService->getTopDocuments($similarities, 2);
echo "\nTop documents: " . implode(', ', $topDocs['top']) . "\n";

// Test ChatbotRetrievalService
echo "\n4. Testing ChatbotRetrievalService\n";
echo "--------------------------------\n";

$retrievalService = app(ChatbotRetrievalService::class);

// Check if there are published articles
$articleCount = Article::where('is_published', true)
    ->where('publish_status', 'approved')
    ->count();

echo "Published & approved articles: $articleCount\n";

if ($articleCount > 0) {
    // Test retrieval
    $testQuery = "cara reset password";
    echo "\nTesting retrieval with query: '$testQuery'\n";
    
    $result = $retrievalService->retrieve($testQuery, 3);
    
    echo "Results found: " . $result['total'] . "\n";
    echo "Threshold met: " . ($result['threshold_met'] ? 'Yes' : 'No') . "\n";
    
    if (!empty($result['results'])) {
        echo "\nTop results:\n";
        foreach ($result['results'] as $i => $r) {
            echo ($i + 1) . ". {$r['title']} (similarity: {$r['similarity']})\n";
        }
    }
    
    // Test greeting detection
    echo "\nTesting greeting detection:\n";
    $greetings = ["halo", "hai", "hello", "selamat pagi"];
    foreach ($greetings as $g) {
        echo "  '$g' => " . ($retrievalService->isGreeting($g) ? 'Greeting' : 'Not greeting') . "\n";
    }
} else {
    echo "\nNo published articles found. Add some articles to test full retrieval.\n";
}

echo "\n========================================\n";
echo "All tests completed!\n";
echo "========================================\n";