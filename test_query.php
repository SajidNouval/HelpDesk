<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$service = app(App\Services\Chatbot\AdvancedRetrievalService::class);
$result = $service->retrieve('wifi saya lemot banget');

echo "Total results: " . $result['total'] . PHP_EOL;
foreach ($result['results'] as $idx => $res) {
    echo "#" . ($idx + 1) . ": " . $res['title'] . " (Score: " . $res['final_score'] . ")" . PHP_EOL;
}
