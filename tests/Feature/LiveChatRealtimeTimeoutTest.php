<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\Category;
use App\Models\User;
use App\Models\Message;
use App\Models\StaffProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveChatRealtimeTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow(); // Reset time mock
        parent::tearDown();
    }

    public function test_queue_position_calculation()
    {
        $category = Category::create([
            'name' => 'IT Support',
            'slug' => 'it-support',
            'description' => 'IT support category',
        ]);

        $now = now();

        \Carbon\Carbon::setTestNow($now->copy()->subMinutes(5));
        $ticket1 = Ticket::create([
            'name' => 'Guest 1',
            'email' => 'guest1@example.com',
            'subject' => 'Issue 1',
            'message' => 'Detail 1',
            'category_id' => $category->id,
            'type' => 'livechat',
            'status' => 'waiting',
        ]);

        \Carbon\Carbon::setTestNow($now->copy()->subMinutes(3));
        $ticket2 = Ticket::create([
            'name' => 'Guest 2',
            'email' => 'guest2@example.com',
            'subject' => 'Issue 2',
            'message' => 'Detail 2',
            'category_id' => $category->id,
            'type' => 'livechat',
            'status' => 'waiting',
        ]);

        \Carbon\Carbon::setTestNow($now->copy()->subMinutes(1));
        $ticket3 = Ticket::create([
            'name' => 'Guest 3',
            'email' => 'guest3@example.com',
            'subject' => 'Issue 3',
            'message' => 'Detail 3',
            'category_id' => $category->id,
            'type' => 'livechat',
            'status' => 'waiting',
        ]);

        \Carbon\Carbon::setTestNow($now);

        // Check status/queue position of Ticket 2 (should be #2)
        $response = $this->getJson("/api/tickets/{$ticket2->id}/status");
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'waiting',
            'queue_position' => 2,
            'estimated_waiting_minutes' => 4,
        ]);
    }

    public function test_queue_timeout_closes_ticket_after_20_minutes()
    {
        $category = Category::create([
            'name' => 'IT Support',
            'slug' => 'it-support',
            'description' => 'IT support category',
        ]);

        $now = now();
        \Carbon\Carbon::setTestNow($now->copy()->subMinutes(21));
        $ticket = Ticket::create([
            'name' => 'Guest',
            'email' => 'guest@example.com',
            'subject' => 'Issue',
            'message' => 'Detail',
            'category_id' => $category->id,
            'type' => 'livechat',
            'status' => 'waiting',
        ]);

        \Carbon\Carbon::setTestNow($now);

        $response = $this->getJson("/api/tickets/{$ticket->id}/status");
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'closed',
            'auto_closed' => true,
            'reason' => 'Tidak ada staff yang melayani dalam 20 menit',
        ]);

        $this->assertEquals('closed', $ticket->fresh()->status);
    }

    public function test_queue_warning_at_17_minutes()
    {
        $category = Category::create([
            'name' => 'IT Support',
            'slug' => 'it-support',
            'description' => 'IT support category',
        ]);

        $now = now();
        \Carbon\Carbon::setTestNow($now->copy()->subMinutes(18));
        $ticket = Ticket::create([
            'name' => 'Guest',
            'email' => 'guest@example.com',
            'subject' => 'Issue',
            'message' => 'Detail',
            'category_id' => $category->id,
            'type' => 'livechat',
            'status' => 'waiting',
        ]);

        \Carbon\Carbon::setTestNow($now);

        $response = $this->getJson("/api/tickets/{$ticket->id}/status");
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'waiting',
            'warning' => true,
            'warning_message' => 'Apakah Anda masih di sana? Sesi antrean ini akan ditutup otomatis karena tidak ada staff yang terhubung.',
        ]);
    }

    public function test_inactivity_warning_at_7_minutes()
    {
        $category = Category::create([
            'name' => 'IT Support',
            'slug' => 'it-support',
            'description' => 'IT support category',
        ]);

        $now = now();
        \Carbon\Carbon::setTestNow($now->copy()->subMinutes(8));
        $ticket = Ticket::create([
            'name' => 'Guest',
            'email' => 'guest@example.com',
            'subject' => 'Issue',
            'message' => 'Detail',
            'category_id' => $category->id,
            'type' => 'livechat',
            'status' => 'progress',
        ]);

        \Carbon\Carbon::setTestNow($now);

        // No messages yet, so updated_at is used as activity time.
        $response = $this->getJson("/api/tickets/{$ticket->id}/status");
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'progress',
            'warning' => true,
            'warning_message' => 'Apakah Anda masih di sana? Chat ini akan ditangguhkan otomatis karena tidak ada aktivitas.',
        ]);
    }

    public function test_inactivity_timeout_suspends_ticket_after_10_minutes()
    {
        $category = Category::create([
            'name' => 'IT Support',
            'slug' => 'it-support',
            'description' => 'IT support category',
        ]);

        $staff = User::create([
            'name' => 'Staff 1',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);

        $staffProfile = StaffProfile::create([
            'user_id' => $staff->id,
            'category_id' => $category->id,
            'is_busy' => true,
        ]);

        $now = now();
        
        // Create an older waiting ticket so the staff gets assigned this one instead of re-claiming the suspended one
        \Carbon\Carbon::setTestNow($now->copy()->subMinutes(15));
        $otherTicket = Ticket::create([
            'name' => 'Other Guest',
            'email' => 'other@example.com',
            'subject' => 'Other Issue',
            'message' => 'Other Detail',
            'category_id' => $category->id,
            'type' => 'livechat',
            'status' => 'waiting',
        ]);

        \Carbon\Carbon::setTestNow($now->copy()->subMinutes(11));
        $ticket = Ticket::create([
            'name' => 'Guest',
            'email' => 'guest@example.com',
            'subject' => 'Issue',
            'message' => 'Detail',
            'category_id' => $category->id,
            'type' => 'livechat',
            'status' => 'progress',
            'staff_id' => $staff->id,
        ]);

        \Carbon\Carbon::setTestNow($now);

        $response = $this->getJson("/api/tickets/{$ticket->id}/status");
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'waiting',
            'suspended' => true,
        ]);

        $ticket->refresh();
        $this->assertEquals('waiting', $ticket->status);
        $this->assertNull($ticket->staff_id);

        $otherTicket->refresh();
        $this->assertEquals($staff->id, $otherTicket->staff_id);
        $this->assertEquals('assigned', $otherTicket->status);
        $this->assertTrue((bool)$staffProfile->fresh()->is_busy);
    }

    public function test_report_isolation()
    {
        $category = Category::create([
            'name' => 'IT Support',
            'slug' => 'it-support',
            'description' => 'IT support category',
        ]);

        $now = now();
        \Carbon\Carbon::setTestNow($now->copy()->subMinutes(25));
        $ticket = Ticket::create([
            'name' => 'Guest',
            'email' => 'guest@example.com',
            'subject' => 'Issue',
            'message' => 'Detail',
            'category_id' => $category->id,
            'type' => 'report',
            'status' => 'waiting',
        ]);

        \Carbon\Carbon::setTestNow($now);

        $response = $this->getJson("/api/tickets/{$ticket->id}/status");
        $response->assertStatus(200);
        $response->assertJsonFragment([
            'status' => 'waiting',
            'auto_closed' => false,
        ]);

        $this->assertEquals('waiting', $ticket->fresh()->status);
    }
}
