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
    
    // Check if ticket is assigned but not started for 20 minutes
    if ($ticket->status === 'assigned' && $ticket->assigned_at) {
        $assignedTime = \Carbon\Carbon::parse($ticket->assigned_at);
        $now = now();
        $minutesSinceAssigned = $assignedTime->diffInMinutes($now);
        
        // If assigned for more than 20 minutes, auto-close the ticket
        if ($minutesSinceAssigned >= 20) {
            $ticket->update([
                'status' => 'closed',
                'closed_at' => $now,
            ]);

            // Create a log entry
            \App\Models\TicketLog::create([
                'ticket_id' => $ticket->id,
                'user_id' => null, // System action
                'action' => 'auto_closed',
                'description' => 'Tiket ditutup otomatis karena staff tidak merespons dalam 20 menit setelah assignment. Guest diminta mengisi ulang formulir.'
            ]);

            return response()->json([
                'status' => 'closed',
                'assigned_staff' => $ticket->assignedStaff ? true : false,
                'auto_closed' => true,
                'reason' => 'Staff tidak merespons dalam 20 menit'
            ]);
        }
    }
    
    return response()->json([
        'status' => $ticket->status,
        'assigned_staff' => $ticket->assignedStaff ? true : false,
        'auto_closed' => false
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
            if ($ticket && in_array($ticket->status, ['open', 'assigned', 'progress'])) {
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

    // Only allow auto-close for open or waiting tickets
    if (in_array($ticket->status, ['open', 'waiting'])) {
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

        return response()->json(['success' => true, 'message' => 'Ticket closed successfully']);
    }

    return response()->json(['success' => false, 'message' => 'Cannot close ticket in current status'], 400);
})->withoutMiddleware([\Illuminate\Routing\Middleware\ThrottleRequests::class]);
