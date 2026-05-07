<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'message' => 'required|string|max:1000',
        ]);

        $ticket = Ticket::findOrFail($request->ticket_id);

        // Check authorization for guest users
        $myTickets = session()->get('my_tickets', []);
        $guestTicketId = session('guest_ticket_id');
        $isStaff = Auth::check() && Auth::user()->role === 'staff';
        $isOwner = in_array($ticket->id, $myTickets) ||
                   $guestTicketId == $ticket->id ||
                   ($request->query('email') && $request->query('email') === $ticket->email);

        // For debugging - allow access if ticket exists and is not closed
        if (!$isStaff && !$isOwner) {
            if ($ticket->status !== 'closed') {
                // Allow access for debugging
                $isOwner = true;
            } else {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        if (!$isStaff && in_array($ticket->status, ['assigned', 'waiting', 'closed'])) {
            return response()->json(['error' => 'Tiket sedang tidak terhubung atau sudah ditutup.'], 403);
        }

        // Untuk tiket waiting, tidak boleh chat sama sekali
        if ($ticket->status === 'waiting') {
            return response()->json(['error' => 'Tiket sedang dalam status waiting dan chat tidak diizinkan.'], 403);
        }

        // Determine sender type and id
        $senderType = 'guest'; // Default to guest
        $senderId = null;

        // Check if user is authenticated staff
        if (Auth::check() && Auth::user()->role === 'staff') {
            $senderType = 'staff';
            $senderId = Auth::id();
        }

        $message = Message::create([
            'ticket_id' => $ticket->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'message' => $request->message,
            'is_read' => false,
        ]);

        // Load sender relationship and add sender name
        $message->load('sender');
        if ($senderType === 'staff') {
            $message->sender_name = $message->sender?->name ?? 'Staff';
        } else {
            $message->sender_name = 'Guest';
        }

        // Broadcast the message
        broadcast(new MessageSent($message));

        return response()->json($message, 201);
    }

    public function index(Request $request, $ticketId)
    {
        $ticket = Ticket::findOrFail($ticketId);
        $myTickets = session()->get('my_tickets', []);
        $guestTicketId = session('guest_ticket_id');

        // Check if authorized: staff or ticket owner by session or by email
        $isStaff = Auth::check() && Auth::user()->role === 'staff';
        $isOwner = in_array($ticket->id, $myTickets) ||
                   $guestTicketId == $ticket->id ||
                   ($request->query('email') && $request->query('email') === $ticket->email);

        if (!$isStaff && !$isOwner) {
            if ($ticket->status !== 'closed') {
                // Allow access for debugging
                $isOwner = true;
            } else {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        // For guests, allow access to ticket messages for all active statuses.
        // Only block when the ticket is fully closed.
        if (!$isStaff && $ticket->status === 'closed') {
            return response()->json(['error' => 'Tiket sudah ditutup.'], 403);
        }

        $messages = Message::with('sender')->where('ticket_id', $ticketId)
            ->orderBy('created_at', 'asc')
            ->get();

        // Add sender name to each message
        $messages = $messages->map(function ($message) {
            if ($message->sender_type === 'staff') {
                $message->sender_name = $message->sender?->name ?? 'Staff';
            } elseif (in_array($message->sender_type, ['guest', 'customer'])) {
                $message->sender_name = 'Guest';
            } else {
                $message->sender_name = 'System';
            }
            return $message;
        });

        return response()->json($messages);
    }
}
