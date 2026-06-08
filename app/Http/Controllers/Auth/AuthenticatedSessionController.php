<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * =============================================================================
 * AUTHENTICATED SESSION CONTROLLER - KEAMANAN SESI PENGGUNA
 * =============================================================================
 * 
 * Controller ini mengelola sesi autentikasi pengguna termasuk login dan logout.
 * Controller ini menangani validasi kredensial dan redirect berdasarkan role.
 * 
 * Fitur Utama:
 * - Tampilan form login
 * - Autentikasi pengguna
 * - Redirect berdasarkan role (admin/staff)
 * - Logout dengan validasi status tiket aktif
 * 
 * Model Terkait:
 * - Ticket: Untuk validasi logout staff
 */
class AuthenticatedSessionController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE CREATE - TAMPILKAN FORM LOGIN
     * =========================================================================
     * 
     * Fungsi: Menampilkan halaman login.
     * 
     * Output:
     * - View 'auth.login'
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * =========================================================================
     * 2. METODE STORE - PROSES LOGIN
     * =========================================================================
     * 
     * Fungsi: Memproses permintaan login pengguna.
     * 
     * Alur Proses:
     * 1. Autentikasi kredensial melalui LoginRequest
     * 2. Regenerate session ID untuk keamanan
     * 3. Cek role pengguna:
     *    - Admin: redirect ke admin.dashboard
     *    - Staff: redirect ke staff.dashboard
     * 4. Jika role tidak valid, logout dan kembalikan ke login
     * 
     * Query yang Digunakan:
     * - $request->authenticate(): Validasi dan login
     * - $request->session()->regenerate(): Buat session ID baru
     * 
     * Output:
     * - Redirect ke dashboard sesuai role
     * - Redirect ke login dengan error jika role tidak valid
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        if ($request->user()->role === 'admin') {
            return redirect()->intended(route('admin.dashboard'));
        }

        if ($request->user()->role === 'staff') {
            return redirect()->intended(route('staff.dashboard'));
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => __('auth.failed'),
        ]);
    }

    /**
     * =========================================================================
     * 3. METODE DESTROY - PROSES LOGOUT
     * =========================================================================
     * 
     * Fungsi: Mengakhiri sesi pengguna (logout).
     * 
     * Alur Proses:
     * 1. Cek apakah user adalah staff dengan tiket aktif (status progress)
     * 2. Jika ada tiket progress, tolak logout dengan pesan error
     * 3. Jika tidak, lanjutkan logout:
     *    - Logout dari guard web
     *    - Invalidate session
     *    - Regenerate token
     * 4. Redirect ke halaman utama
     * 
     * Query yang Digunakan:
     * - Ticket::where('staff_id', ...)->where('status', 'progress')->exists():
     *   Cek tiket aktif staff
     * - Auth::guard('web')->logout(): Logout user
     * 
     * Output:
     * - Redirect back with error jika ada tiket aktif
     * - Redirect ke '/' jika logout berhasil
     */
    public function destroy(Request $request): RedirectResponse
    {
        if ($request->user() && $request->user()->role === 'staff') {
            $hasActiveProgress = Ticket::where('staff_id', $request->user()->id)
                ->where('status', 'progress')
                ->exists();

            if ($hasActiveProgress) {
                return redirect()->back()->with('error', 'Masih melayani customer aktif. Harap selesaikan sesi live chat sebelum logout.');
            }
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}