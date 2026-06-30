<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * =========================================================================
 * MIDDLEWARE STAFF - OTORISASI STAFF
 * =========================================================================
 *
 * Middleware ini memeriksa apakah user yang sedang login adalah staff.
 * Jika bukan staff, request akan ditolak dengan error 403.
 */
class StaffMiddleware
{
    /**
     * Fungsi:
     * Menangani request masuk dan memeriksa otorisasi staff.
     *
     * Alur Proses:
     * 1. Cek apakah user sudah login.
     * 2. Cek apakah role user adalah staff.
     * 3. Jika ya, lanjutkan request.
     * 4. Jika tidak, abort dengan error 403.
     *
     * Output:
     * - Response jika authorized, atau abort 403 jika tidak.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->role === 'staff' && auth()->user()->status === 'active') {
            return $next($request);
        }

        abort(403, 'Akses ditolak');
    }
}
