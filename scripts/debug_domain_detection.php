<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Chatbot\DomainDetectionService;
use App\Services\Chatbot\PreprocessingService;

$service = new DomainDetectionService(new PreprocessingService());
$queries = [
    'wifi',
    'printer',
    'email',
    'outlook',
    'vpn',
    'mikrotik',
    'router',
    'laptop lemot',
    'install driver',
    'windows update',
];

foreach ($queries as $query) {
    $result = $service->detectOutOfDomain($query);
    echo "QUERY: {$query}\n";
    echo "  is_out_of_domain: " . ($result['is_out_of_domain'] ? 'true' : 'false') . "\n";
    echo "  reason: {$result['reason']}\n";
    echo "  it_token_count: {$result['it_token_count']}\n";
    echo "  vocabulary_overlap: {$result['vocabulary_overlap']}\n";
    echo "  domain_confidence: " . ($result['domain_confidence'] ?? 'n/a') . "\n";
    echo "\n";
}
