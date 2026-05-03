<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTicketAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ticket = \App\Models\Ticket::find($request->id);

        if (!$ticket) {
            abort(404);
        }

        if (auth()->user()->role === 'admin') {
            return $next($request);
        }

        if (auth()->user()->role === 'staff' && $ticket->staff_id === auth()->id()) {
            return $next($request);
        }

        abort(403, 'Tidak punya akses ke tiket ini');
    }
}
