<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Channel untuk ticket chat - public channel untuk real-time updates
Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    $ticket = \App\Models\Ticket::find($ticketId);
    if (!$ticket) {
        return false;
    }

    // Allow all authenticated users and guests with ticket access
    if ($user && $user->role === 'staff') {
        return true;
    }

    return in_array($ticketId, session()->get('my_tickets', []));
});
