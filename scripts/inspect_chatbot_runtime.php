<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\Chatbot\PreprocessingService;
use App\Services\Chatbot\TfidfService;
use App\Services\Chatbot\CosineSimilarityService;
use App\Services\Chatbot\ChatbotRetrievalService;
use App\Services\Chatbot\DomainDetectionService;
use App\Services\Chatbot\TypesenseService;
use App\Models\Article;

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$preprocessor = new PreprocessingService();
$tfidfService = new TfidfService($preprocessor);
$similarityService = new CosineSimilarityService();
$domainDetector = new DomainDetectionService($preprocessor);
$typesenseService = new TypesenseService();
$retrievalService = new ChatbotRetrievalService($preprocessor, $tfidfService, $similarityService, $domainDetector, $typesenseService);

$queries = [
    'wifi lambat',
    'printer tidak mau print',
];

foreach ($queries as $query) {
    echo "\n=== QUERY: $query ===\n";

    $result = $retrievalService->retrieve($query, 1);

    // If no results, create a test article to demonstrate
    $created = null;
    if (empty($result['results'])) {
        echo "No results from retrieval; creating a temporary test article...\n";
        $title = "Penanganan $query";
        $excerpt = "<p>Ini adalah ringkasan mengenai $query. Coba periksa koneksi, restart perangkat. Jika masih lambat, hubungi administrator.</p>";
        $content = "<p>Langkah pertama untuk menangani masalah: matikan router, tunggu 30 detik, hidupkan kembali. Jika masih bermasalah, periksa kabel dan konfigurasinya.</p>\n\n<p>Langkah kedua: periksa beban jaringan dan batasi perangkat yang menggunakan bandwidth berlebih.</p>";

        $created = Article::create([
            'title' => $title,
            'content' => $content,
            'excerpt' => $excerpt,
            'keywords' => '$query',
            'slug' => 'inspect-' . str_replace(' ', '-', $query) . '-' . time(),
            'category_id' => 1,
            'is_published' => true,
            'publish_status' => 'approved',
            'views' => 0,
        ]);

        // Rerun retrieval
        $result = $retrievalService->retrieve($query, 1);
    }

    if (empty($result['results'])) {
        echo "Still no results; skipping.\n";
        if ($created) { $created->delete(); }
        continue;
    }

    $top = $result['results'][0];

    $title = $top['title'] ?? '';
    $excerpt = $top['excerpt'] ?? '';
    $content = $top['content'] ?? '';

    echo "- Judul artikel terpilih: $title\n";
    echo "- Nilai excerpt mentah: $excerpt\n";
    echo "- Panjang excerpt: " . mb_strlen($excerpt) . "\n";
    $contentFirst200 = mb_substr($content, 0, 200);
    echo "- Nilai content mentah (200 karakter pertama): $contentFirst200\n";
    echo "- Panjang content: " . mb_strlen($content) . "\n\n";

    // Use reflection to call private methods
    $rc = new ReflectionClass($retrievalService);
    $m_strip = $rc->getMethod('stripHtmlTags'); $m_strip->setAccessible(true);
    $m_firstPara = $rc->getMethod('extractFirstParagraph'); $m_firstPara->setAccessible(true);
    $m_extractS = $rc->getMethod('extractSentences'); $m_extractS->setAccessible(true);
    $m_generate = $rc->getMethod('generateSummaryFromExcerpt'); $m_generate->setAccessible(true);
    $m_isTooSimilar = $rc->getMethod('isTooSimilarToTitle'); $m_isTooSimilar->setAccessible(true);

    $stripExcerpt = $m_strip->invoke($retrievalService, $excerpt);
    $stripContent = $m_strip->invoke($retrievalService, $content);
    $firstParagraph = $m_firstPara->invoke($retrievalService, $stripContent);
    $sentencesExcerpt = $m_extractS->invoke($retrievalService, $stripExcerpt, 2, 4);
    $sentencesContent = $m_extractS->invoke($retrievalService, $firstParagraph, 2, 4);
    $generated = $m_generate->invoke($retrievalService, $excerpt, $content, $title);
    $excerptSentencesCount = count(preg_split('/(?<=[.!?])\s+/', $stripExcerpt, -1, PREG_SPLIT_NO_EMPTY));
    $isTooSimilar = $m_isTooSimilar->invoke($retrievalService, $stripExcerpt, $title);

    echo "Hasil setiap langkah:\n";
    echo "1. stripHtmlTags(excerpt): $stripExcerpt\n";
    echo "2. stripHtmlTags(content): $stripContent\n";
    echo "3. extractFirstParagraph(content): $firstParagraph\n";
    echo "4. extractSentences(excerpt) [2,4]: $sentencesExcerpt\n";
    echo "5. extractSentences(content) [2,4]: $sentencesContent\n\n";

    echo "Debug tambahan untuk keputusan generateSummaryFromExcerpt():\n";
    echo "- Excerpt after strip length: " . mb_strlen($stripExcerpt) . "\n";
    echo "- Excerpt sentence count: $excerptSentencesCount\n";
    echo "- isTooSimilarToTitle(excerpt,title): " . ($isTooSimilar ? 'YES' : 'NO') . "\n";
    echo "- Content empty? " . (empty(trim($stripContent)) ? 'YES' : 'NO') . "\n";
    echo "- Summary produced by generateSummaryFromExcerpt(): $generated\n";

    // Explain why fallback chosen (if it matches fallback)
    $fallback = 'Saya menemukan beberapa informasi yang relevan dengan pertanyaan Anda.';
    if ($generated === $fallback) {
        echo "\nAlasan menghasilkan fallback: karena excerpt tidak memenuhi syarat (kurang dari 2 kalimat atau terlalu mirip judul) dan content kosong.\n";
    } else {
        echo "\nAlasan ringkasan dipilih dari excerpt/content: excerpt memiliki >=2 kalimat dan tidak terlalu mirip judul, atau content menyediakan paragraf pertama yang cukup.\n";
    }

    // Cleanup created article
    if ($created) {
        $created->delete();
        echo "Temporary article deleted.\n";
    }
}

echo "\nDone.\n";
