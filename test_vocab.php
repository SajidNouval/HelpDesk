<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Chatbot\VocabularyService;

$service = app(VocabularyService::class);

$queries = [
    'virusss',
    'viruss',
    'ransomwre',
    'malwere',
    'trojon',
];

foreach ($queries as $query) {

    echo "\n====================\n";
    echo "QUERY: $query\n";

    try {

        $normalized = $service->normalizeQuery($query);

        echo "NORMALIZED:\n";

        var_dump($normalized);

    } catch (\Throwable $e) {

        echo "ERROR:\n";
        echo $e->getMessage() . "\n";

    }
}