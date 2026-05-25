<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Chatbot\TypesenseService;

$service = app(TypesenseService::class);

$queries = [
    'virusss',
    'viruss',
    'ransomwre',
];

foreach ($queries as $query) {

    echo "\n====================\n";
    echo "QUERY: $query\n";

    $results = $service->searchArticles($query);

    print_r($results);
}