<?php

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArticleApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
    }
    public function test_staff_can_create_pending_article()
    {
        $staff = User::factory()->staff()->create();

        $response = $this->actingAs($staff)->post('/staff/articles', [
            'title' => 'Test Article',
            'content' => 'Test content',
            'category_id' => Category::factory()->create()->id,
        ]);

        $response->assertRedirect();

        $article = Article::latest()->first();
        $this->assertEquals('pending', $article->publish_status);
        $this->assertFalse($article->is_published);
    }

    public function test_admin_can_approve_article()
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->pending()->create();

        $response = $this->actingAs($admin)->post("/admin/articles/{$article->id}/approve");

        $response->assertRedirect();
        $article->refresh();

        $this->assertEquals('approved', $article->publish_status);
        $this->assertTrue($article->is_published);
    }

    public function test_admin_can_reject_article_with_note()
    {
        $admin = User::factory()->admin()->create();
        $article = Article::factory()->pending()->create();

        $response = $this->actingAs($admin)->post("/admin/articles/{$article->id}/reject", [
            'rejection_note' => 'Article needs more content',
        ]);

        $response->assertRedirect();
        $article->refresh();

        $this->assertEquals('rejected', $article->publish_status);
        $this->assertFalse($article->is_published);
        $this->assertEquals('Article needs more content', $article->rejection_note);
    }

    public function test_public_can_only_see_approved_articles()
    {
        Article::factory()->approved()->create(['title' => 'Approved Article']);
        Article::factory()->pending()->create(['title' => 'Pending Article']);

        $response = $this->get('/articles');

        $response->assertSee('Approved Article');
        $response->assertDontSee('Pending Article');
    }
}