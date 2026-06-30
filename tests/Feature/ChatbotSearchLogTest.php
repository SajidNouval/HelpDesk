<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\ChatbotSearchLog;
use App\Services\Chatbot\AdvancedRetrievalService;
use App\Services\Chatbot\TypesenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('saves chatbot search logs to database after retrieval', function () {
    // Mock TypesenseService to be disconnected so retrieval falls back to DB
    $mockTypesense = Mockery::mock(TypesenseService::class);
    $mockTypesense->shouldReceive('isConnected')->andReturn(false);
    app()->instance(TypesenseService::class, $mockTypesense);

    // 1. Create a dummy user, category, and article in database
    $user = \App\Models\User::create([
        'name' => 'Staff Support',
        'email' => 'staff@email.com',
        'password' => bcrypt('password'),
        'role' => 'staff',
    ]);

    $category = Category::create([
        'name' => 'Wifi',
        'slug' => 'wifi',
    ]);

    $article = Article::create([
        'category_id' => $category->id,
        'staff_id' => $user->id,
        'title' => 'Wifi Lemot',
        'content' => 'Jika wifi anda lemot maka solusinya adalah restart modem.',
        'slug' => 'wifi-lemot',
        'is_published' => true,
        'publish_status' => 'approved',
    ]);

    // Force rebuild vocabulary so that the new article is loaded in VocabularyService
    $vocabularyService = app(\App\Services\Chatbot\VocabularyService::class);
    $vocabularyService->clearCache();
    $vocabularyService->loadVocabulary();

    // 2. Perform retrieval via the AdvancedRetrievalService
    $service = app(AdvancedRetrievalService::class);
    $result = $service->retrieve('wifi ku lemot banget');

    // 3. Assert database has the expected log entry
    $this->assertDatabaseHas('chatbot_search_logs', [
        'query_original' => 'wifi ku lemot banget',
        'query_normalized' => 'wifi ku lemot banget', // Synonyms normalized but full query retained
        'detected_domain' => 'wifi',
        'results_count' => 1,
        'top_result_id' => $article->id,
        'top_result_title' => 'Wifi Lemot',
        'is_fallback_triggered' => false,
    ]);
});

it('logs search as fallback when no results are found', function () {
    // Mock TypesenseService to be disconnected
    $mockTypesense = Mockery::mock(TypesenseService::class);
    $mockTypesense->shouldReceive('isConnected')->andReturn(false);
    app()->instance(TypesenseService::class, $mockTypesense);

    // Force rebuild vocabulary
    $vocabularyService = app(\App\Services\Chatbot\VocabularyService::class);
    $vocabularyService->clearCache();

    // 1. Perform retrieval for a query that has no matches
    $service = app(AdvancedRetrievalService::class);
    $result = $service->retrieve('pertanyaan antariksa planet mars');

    // 2. Assert database log entry represents a fallback/empty result
    $this->assertDatabaseHas('chatbot_search_logs', [
        'query_original' => 'pertanyaan antariksa planet mars',
        'results_count' => 0,
        'top_result_id' => null,
        'is_fallback_triggered' => true,
    ]);
});
