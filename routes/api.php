<?php

use App\Http\Controllers\MessageController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Message routes without request throttling for chat polling
Route::middleware(['web'])->post('/messages', [MessageController::class, 'store'])
    ->withoutMiddleware([\Illuminate\Routing\Middleware\ThrottleRequests::class, \App\Http\Middleware\VerifyCsrfToken::class]);

Route::middleware(['web'])->get('/tickets/{ticketId}/messages', [MessageController::class, 'index'])
    ->withoutMiddleware([\Illuminate\Routing\Middleware\ThrottleRequests::class]);

Route::middleware(['web'])->get('/tickets/{ticketId}/status', function($ticketId) {
    $ticket = \App\Models\Ticket::with('assignedStaff')->findOrFail($ticketId);
    
    // Only apply auto-close/auto-suspend timeouts for live chat tickets
    if ($ticket->type === 'livechat') {
        $now = now();

        // SCENARIO 1: Ticket waiting in queue without staff
        if ($ticket->status === 'waiting' && !$ticket->staff_id) {
            $createdTime = \Carbon\Carbon::parse($ticket->created_at);
            $minutesWaiting = $createdTime->diffInMinutes($now);

            if ($minutesWaiting >= 20) {
                $ticket->update([
                    'status' => 'closed',
                    'closed_at' => $now,
                ]);

                \App\Models\TicketLog::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => null, // System action
                    'action' => 'closed_by_system_timeout',
                    'description' => 'Tiket live chat ditutup otomatis oleh sistem karena tidak ada staff yang melayani dalam 20 menit.'
                ]);

                broadcast(new \App\Events\TicketClosed($ticket));
                \App\Services\TicketAssignmentService::broadcastQueueUpdateForCategory($ticket->category_id);

                return response()->json([
                    'status' => 'closed',
                    'assigned_staff' => false,
                    'staff_name' => null,
                    'auto_closed' => true,
                    'reason' => 'Tidak ada staff yang melayani dalam 20 menit'
                ]);
            }

            if ($minutesWaiting >= 17) {
                return response()->json([
                    'status' => 'waiting',
                    'assigned_staff' => false,
                    'staff_name' => null,
                    'auto_closed' => false,
                    'warning' => true,
                    'warning_message' => 'Apakah Anda masih di sana? Sesi antrean ini akan ditutup otomatis karena tidak ada staff yang terhubung.',
                    'queue_position' => 1,
                    'estimated_waiting_minutes' => 2
                ]);
            }
        }

        // SCENARIO 2: Ticket assigned to staff but not started
        if ($ticket->status === 'assigned' && $ticket->assigned_at) {
            $assignedTime = \Carbon\Carbon::parse($ticket->assigned_at);
            $minutesSinceAssigned = $assignedTime->diffInMinutes($now);

            if ($minutesSinceAssigned >= 20) {
                $ticket->update([
                    'status' => 'closed',
                    'closed_at' => $now,
                ]);

                \App\Models\TicketLog::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => null,
                    'action' => 'closed_by_system_timeout',
                    'description' => 'Tiket ditutup otomatis karena staff tidak merespons dalam 20 menit setelah assignment.'
                ]);

                broadcast(new \App\Events\TicketClosed($ticket));
                \App\Services\TicketAssignmentService::broadcastQueueUpdateForCategory($ticket->category_id);

                return response()->json([
                    'status' => 'closed',
                    'assigned_staff' => $ticket->assignedStaff ? true : false,
                    'staff_name' => $ticket->assignedStaff ? $ticket->assignedStaff->name : null,
                    'auto_closed' => true,
                    'reason' => 'Staff tidak merespons dalam 20 menit'
                ]);
            }
        }

        // SCENARIO 3: Active chat in progress, check guest inactivity
        if ($ticket->status === 'progress') {
            $lastGuestMsg = \App\Models\Message::where('ticket_id', $ticket->id)
                ->where('sender_type', 'guest')
                ->latest()
                ->first();

            $lastActiveTime = $lastGuestMsg ? $lastGuestMsg->created_at : $ticket->updated_at;
            $minutesInactive = \Carbon\Carbon::parse($lastActiveTime)->diffInMinutes($now);

            if ($minutesInactive >= 10) {
                $staffId = $ticket->staff_id;

                $ticket->update([
                    'status' => 'waiting',
                    'staff_id' => null,
                ]);

                if ($staffId) {
                    \App\Models\StaffProfile::where('user_id', $staffId)->update([
                        'is_busy' => false,
                    ]);
                }

                \App\Models\TicketLog::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => null,
                    'action' => 'waiting',
                    'description' => 'Tiket ditangguhkan otomatis oleh sistem karena guest tidak aktif selama 10 menit.'
                ]);

                broadcast(new \App\Events\TicketClosed($ticket));

                if ($staffId) {
                    $freedStaffProfile = \App\Models\StaffProfile::where('user_id', $staffId)->first();
                    if ($freedStaffProfile) {
                        $assignmentService = resolve(\App\Services\TicketAssignmentService::class);
                        $assignmentService->assignNextWaiting($freedStaffProfile);
                    }
                }

                \App\Services\TicketAssignmentService::broadcastQueueUpdateForCategory($ticket->category_id);

                return response()->json([
                    'status' => 'waiting',
                    'assigned_staff' => false,
                    'staff_name' => null,
                    'auto_closed' => false,
                    'suspended' => true,
                    'reason' => 'Guest tidak aktif selama 10 menit'
                ]);
            }

            if ($minutesInactive >= 7) {
                return response()->json([
                    'status' => 'progress',
                    'assigned_staff' => true,
                    'staff_name' => $ticket->assignedStaff ? $ticket->assignedStaff->name : null,
                    'auto_closed' => false,
                    'warning' => true,
                    'warning_message' => 'Apakah Anda masih di sana? Chat ini akan ditangguhkan otomatis karena tidak ada aktivitas.'
                ]);
            }
        }
    }

    // Calculate queue position if ticket is waiting in queue
    $queuePosition = null;
    $estimatedWaitingMinutes = null;
    if ($ticket->type === 'livechat' && $ticket->status === 'waiting' && !$ticket->staff_id) {
        $queuePosition = \App\Models\Ticket::where('type', 'livechat')
            ->where('category_id', $ticket->category_id)
            ->where('status', 'waiting')
            ->whereNull('staff_id')
            ->where('created_at', '<', $ticket->created_at)
            ->count() + 1;
        $estimatedWaitingMinutes = $queuePosition * 2;
    }
    
    return response()->json([
        'status' => $ticket->status,
        'assigned_staff' => $ticket->assignedStaff ? true : false,
        'staff_name' => $ticket->assignedStaff ? $ticket->assignedStaff->name : null,
        'auto_closed' => false,
        'queue_position' => $queuePosition,
        'estimated_waiting_minutes' => $estimatedWaitingMinutes
    ]);
})->withoutMiddleware([\Illuminate\Routing\Middleware\ThrottleRequests::class]);

// Get ticket logs
Route::middleware(['web'])->get('/tickets/{ticketId}/logs', function($ticketId) {
    $ticket = \App\Models\Ticket::findOrFail($ticketId);
    $logs = $ticket->logs()->orderBy('created_at', 'desc')->get();
    return response()->json($logs);
})->withoutMiddleware([\Illuminate\Routing\Middleware\ThrottleRequests::class]);

// Get latest ticket (for guest)
Route::middleware(['web'])->get('/tickets/latest', function() {
    $ticket = \App\Models\Ticket::latest()->first();
    return response()->json($ticket);
})->withoutMiddleware([\Illuminate\Routing\Middleware\ThrottleRequests::class]);

// Check if user has an active ticket
Route::middleware(['web'])->get('/articles/active-ticket', function(Request $request) {
    // Try to get ticket ID from query parameter (from localStorage)
    $ticketId = $request->query('ticket_id');

    if ($ticketId) {
        $ticket = \App\Models\Ticket::find($ticketId);
        if ($ticket && (in_array($ticket->status, ['open', 'assigned', 'progress']) || ($ticket->status === 'waiting' && !$ticket->staff_id))) {
            return response()->json(['ticket_id' => $ticket->id, 'status' => $ticket->status]);
        }
    }

    // Try to get from session
    $ticketId = session('guest_ticket_id');
    if ($ticketId) {
        $ticket = \App\Models\Ticket::find($ticketId);
        if ($ticket && (in_array($ticket->status, ['open', 'assigned', 'progress']) || ($ticket->status === 'waiting' && !$ticket->staff_id))) {
            return response()->json(['ticket_id' => $ticket->id, 'status' => $ticket->status]);
        }
    }

    // Check if there's a ticket created in this session by email
    $email = $request->query('email') ?? session('guest_email');
    if ($email) {
        $ticket = \App\Models\Ticket::where('email', $email)
            ->where(function ($query) {
                $query->whereIn('status', ['open', 'assigned', 'progress'])
                      ->orWhere(function ($query) {
                          $query->where('status', 'waiting')
                                ->whereNull('staff_id');
                      });
            })
            ->latest()
            ->first();

        if ($ticket) {
            // Store in session
            session(['guest_ticket_id' => $ticket->id, 'guest_email' => $email]);
            return response()->json(['ticket_id' => $ticket->id, 'status' => $ticket->status]);
        }
    }

    return response()->json(['ticket_id' => null, 'status' => null]);
})->withoutMiddleware([\Illuminate\Routing\Middleware\ThrottleRequests::class]);

// Cancel ticket (auto-cancel after 20 minutes)
Route::middleware(['web'])->post('/tickets/{ticketId}/close', function($ticketId) {
    $ticket = \App\Models\Ticket::findOrFail($ticketId);

    // Only allow auto-close for livechat tickets that are open or waiting
    if ($ticket->type === 'livechat' && in_array($ticket->status, ['open', 'waiting'])) {
        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        // Create a log entry
        \App\Models\TicketLog::create([
            'ticket_id' => $ticket->id,
            'user_id' => null, // System action
            'action' => 'auto_closed',
            'description' => 'Tiket ditutup otomatis karena tidak ada staff tersedia dalam 20 menit. Guest diminta mengisi ulang formulir.'
        ]);

        broadcast(new \App\Events\TicketClosed($ticket));
        \App\Services\TicketAssignmentService::broadcastQueueUpdateForCategory($ticket->category_id);

        return response()->json(['success' => true, 'message' => 'Ticket closed successfully']);
    }

    return response()->json(['success' => false, 'message' => 'Cannot close ticket in current status or is not a livechat ticket'], 400);
})->withoutMiddleware([\Illuminate\Routing\Middleware\ThrottleRequests::class]);
