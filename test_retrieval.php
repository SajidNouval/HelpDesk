<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\Chatbot\AdvancedRetrievalService;

$service = app(AdvancedRetrievalService::class);

$query = 'virusss';

$result = $service->retrieve($query, 5);

dd($result);