<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * =========================================================================
 * MIDDLEWARE CHECK TICKET ACCESS - OTORISASI AKSES TIKET
 * =========================================================================
 *
 * Middleware ini memeriksa apakah user memiliki akses ke tiket tertentu.
 * Admin memiliki akses ke semua tiket, staff hanya memiliki akses
 * ke tiket yang ditugaskan ke mereka.
 */
class CheckTicketAccess
{
    /**
     * Fungsi:
     * Menangani request masuk dan memeriksa otorisasi akses tiket.
     *
     * Alur Proses:
     * 1. Query tiket berdasarkan ID dari request.
     * 2. Jika tiket tidak ditemukan, abort 404.
     * 3. Jika user adalah admin, lanjutkan request.
     * 4. Jika user adalah staff dan tiket ditugaskan ke mereka, lanjutkan request.
     * 5. Jika tidak, abort dengan error 403.
     *
     * Output:
     * - Response jika authorized, atau abort 403/404 jika tidak.
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
