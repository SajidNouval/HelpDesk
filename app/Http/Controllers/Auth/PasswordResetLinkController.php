<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * =============================================================================
 * PASSWORD RESET LINK CONTROLLER - LINK RESET KATA SANDI
 * =============================================================================
 * 
 * Controller ini menangani permintaan link reset password.
 * 
 * Fitur Utama:
 * - Tampilan form forgot password
 * - Pengiriman link reset password via email
 */
class PasswordResetLinkController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE CREATE - TAMPILKAN FORM LUPA PASSWORD
     * =========================================================================
     * 
     * Fungsi: Menampilkan halaman lupa password.
     * 
     * Output:
     * - View 'auth.forgot-password'
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * =========================================================================
     * 2. METODE STORE - KIRIM LINK RESET PASSWORD
     * =========================================================================
     * 
     * Fungsi: Memproses permintaan reset password.
     * 
     * Alur Proses:
     * 1. Validasi email
     * 2. Kirim link reset password menggunakan Password::sendResetLink
     * 3. Cek status response
     * 4. Redirect back dengan pesan sesuai hasil
     * 
     * Query yang Digunakan:
     * - Password::sendResetLink(['email' => ...]): Kirim link reset
     * 
     * Output:
     * - Redirect back with('status', ...) jika berhasil
     * - Redirect back withErrors(['email' => ...]) jika gagal
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                            ->withErrors(['email' => __($status)]);
    }
}