<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\Category;
use App\Models\User;
use App\Models\StaffProfile;
use App\Models\TicketLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffStatusToggleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\VerifyCsrfToken::class]);
    }

    public function test_staff_can_toggle_status_active_to_inactive_and_vice_versa()
    {
        $staff = User::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'Staf Test',
            'email' => 'staf@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'status' => 'active',
        ]);

        $this->actingAs($staff);

        // Toggle to inactive
        $response = $this->post(route('staff.toggle-status'));
        $response->assertRedirect();
        $this->assertEquals('inactive', $staff->fresh()->status);

        // Toggle to active
        $response = $this->post(route('staff.toggle-status'));
        $response->assertRedirect();
        $this->assertEquals('active', $staff->fresh()->status);
    }

    public function test_inactive_staff_does_not_receive_livechat_ticket()
    {
        $category = Category::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'IT Help',
            'slug' => 'it-help',
        ]);

        $staff = User::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'Staf Wifi',
            'email' => 'wifi@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'status' => 'inactive', // Staf inaktif
        ]);

        StaffProfile::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => $staff->id,
            'category_id' => $category->id,
            'is_busy' => false,
        ]);

        $ticket = Ticket::create([
            'name' => 'Client',
            'email' => 'client@example.com',
            'subject' => 'Internet issue',
            'message' => 'Internet is slow',
            'category_id' => $category->id,
            'type' => 'livechat',
            'status' => 'open',
        ]);

        // Run assignment
        $assignmentService = resolve(\App\Services\TicketAssignmentService::class);
        $assignedStaff = $assignmentService->assignLiveChat($ticket);

        // Verify no staff was assigned because the only staff is inactive
        $this->assertNull($assignedStaff);
        $ticket->refresh();
        $this->assertEquals('open', $ticket->status); // stays open (later marked waiting by controller)
    }

    public function test_activating_staff_automatically_assigns_waiting_tickets()
    {
        $category = Category::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'IT Help',
            'slug' => 'it-help',
        ]);

        $staff = User::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'Staf Wifi',
            'email' => 'wifi@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'status' => 'inactive',
        ]);

        $profile = StaffProfile::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => $staff->id,
            'category_id' => $category->id,
            'is_busy' => false,
        ]);

        // Create a waiting ticket (unassigned)
        $ticket = Ticket::create([
            'name' => 'Client',
            'email' => 'client@example.com',
            'subject' => 'Internet issue',
            'message' => 'Internet is slow',
            'category_id' => $category->id,
            'type' => 'livechat',
            'status' => 'waiting',
            'staff_id' => null,
        ]);

        $this->actingAs($staff);

        // Toggle status to active
        $response = $this->post(route('staff.toggle-status'));
        $response->assertRedirect();
        
        $staff->refresh();
        $this->assertEquals('active', $staff->status);

        // Verify the ticket was automatically assigned to the staff
        $ticket->refresh();
        $this->assertEquals('assigned', $ticket->status);
        $this->assertEquals($staff->id, $ticket->staff_id);

        $profile->refresh();
        $this->assertTrue((bool)$profile->is_busy);
    }

    public function test_staff_cannot_deactivate_when_in_active_live_chat()
    {
        $category = Category::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'IT Help',
            'slug' => 'it-help',
        ]);

        $staff = User::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'Staf Wifi',
            'email' => 'wifi@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'status' => 'active',
        ]);

        StaffProfile::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => $staff->id,
            'category_id' => $category->id,
            'is_busy' => true,
        ]);

        // Active chat ticket
        Ticket::create([
            'name' => 'Client',
            'email' => 'client@example.com',
            'subject' => 'Chatting',
            'message' => 'Need help',
            'category_id' => $category->id,
            'type' => 'livechat',
            'status' => 'progress', // In progress chat
            'staff_id' => $staff->id,
        ]);

        $this->actingAs($staff);

        $response = $this->post(route('staff.toggle-status'));
        $response->assertSessionHas('error');
        $this->assertEquals('active', $staff->fresh()->status);
    }

    public function test_deactivating_staff_automatically_closes_assigned_unprogressed_tickets()
    {
        $category = Category::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'IT Help',
            'slug' => 'it-help',
        ]);

        $staff = User::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'name' => 'Staf Wifi',
            'email' => 'wifi@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'status' => 'active',
        ]);

        StaffProfile::create([
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'user_id' => $staff->id,
            'category_id' => $category->id,
            'is_busy' => true,
        ]);

        // Incoming ticket (assigned but not yet progressed)
        $ticket = Ticket::create([
            'name' => 'Client',
            'email' => 'client@example.com',
            'subject' => 'Chat request',
            'message' => 'Need quick support',
            'category_id' => $category->id,
            'type' => 'livechat',
            'status' => 'assigned',
            'staff_id' => $staff->id,
        ]);

        $this->actingAs($staff);

        $response = $this->post(route('staff.toggle-status'));
        $response->assertRedirect();
        
        $staff->refresh();
        $this->assertEquals('inactive', $staff->status);

        // Verify ticket is closed automatically
        $ticket->refresh();
        $this->assertEquals('closed', $ticket->status);

        // Verify ticket log recorded the auto-close reason
        $logExists = TicketLog::where('ticket_id', $ticket->id)
            ->where('action', 'rejected')
            ->exists();
        $this->assertTrue($logExists);
    }
}
