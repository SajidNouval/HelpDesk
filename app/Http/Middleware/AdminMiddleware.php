<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * =========================================================================
 * MIDDLEWARE ADMIN - OTORISASI ADMIN
 * =========================================================================
 *
 * Middleware ini memeriksa apakah user yang sedang login adalah admin.
 * Jika bukan admin, request akan ditolak dengan error 403.
 */
class AdminMiddleware
{
    /**
     * Fungsi:
     * Menangani request masuk dan memeriksa otorisasi admin.
     *
     * Alur Proses:
     * 1. Cek apakah user sudah login.
     * 2. Cek apakah role user adalah admin.
     * 3. Jika ya, lanjutkan request.
     * 4. Jika tidak, abort dengan error 403.
     *
     * Output:
     * - Response jika authorized, atau abort 403 jika tidak.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && auth()->user()->role === 'admin' && auth()->user()->status === 'active') {
            return $next($request);
        }

        abort(403, 'Akses ditolak');
    }
}
