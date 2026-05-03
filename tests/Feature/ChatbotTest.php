<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Chatbot;
use App\Models\Ticket;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create test data
        $this->category = Category::firstOrCreate(
            ['name' => 'Test Category'],
            ['slug' => 'test-category', 'description' => 'Test']
        );

        $this->article = Article::firstOrCreate(
            ['slug' => 'test-article'],
            [
                'category_id' => $this->category->id,
                'staff_id' => 1,
                'title' => 'Test Article',
                'content' => 'Test content',
                'is_published' => true,
            ]
        );

        $this->chatbotRule = Chatbot::firstOrCreate(
            ['keywords' => 'test,wifi,internet'],
            [
                'response' => 'Test response message',
                'category_id' => $this->category->id,
                'priority' => 100,
                'is_active' => true,
            ]
        );
    }

    /** @test */
    public function test_chatbot_get_response_with_match()
    {
        $response = $this->post('/chatbot/get-response', [
            'message' => 'wifi internet test',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonCount(1, 'articles');
        $response->assertJsonStructure([
            'success',
            'response',
            'articles' => [
                '*' => ['id', 'title', 'slug', 'category_id', 'views', 'category']
            ],
            'score'
        ]);
    }

    /** @test */
    public function test_chatbot_get_response_no_match()
    {
        $response = $this->post('/chatbot/get-response', [
            'message' => 'xyz abc 123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => false]);
        $response->assertJsonPath('suggest_ticket', true);
    }

    /** @test */
    public function test_chatbot_get_response_short_message()
    {
        $response = $this->post('/chatbot/get-response', [
            'message' => 'ok',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => false]);
    }

    /** @test */
    public function test_chatbot_create_ticket()
    {
        $response = $this->post('/chatbot/create-ticket', [
            'title' => 'Test Issue',
            'message' => 'Test issue description',
            'category_id' => $this->category->id,
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('tickets', [
            'title' => 'Test Issue',
            'category_id' => $this->category->id,
        ]);
    }

    /** @test */
    public function test_chatbot_model_get_keywords_array()
    {
        $keywords = $this->chatbotRule->getKeywordsArray();
        
        $this->assertIsArray($keywords);
        $this->assertContains('test', $keywords);
        $this->assertContains('wifi', $keywords);
        $this->assertContains('internet', $keywords);
    }

    /** @test */
    public function test_chatbot_scope_active()
    {
        $inactiveRule = Chatbot::create([
            'keywords' => 'inactive',
            'response' => 'Inactive rule',
            'is_active' => false,
        ]);

        $activeCount = Chatbot::active()->count();
        
        $this->assertTrue($activeCount < Chatbot::count());
    }
}
