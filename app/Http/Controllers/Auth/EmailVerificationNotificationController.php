<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * =============================================================================
 * EMAIL VERIFICATION NOTIFICATION CONTROLLER - KIRIM ULANG VERIFIKASI EMAIL
 * =============================================================================
 * 
 * Controller ini menangani pengiriman ulang notifikasi verifikasi email.
 * 
 * Fitur Utama:
 * - Kirim ulang link verifikasi email
 */
class EmailVerificationNotificationController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE STORE - KIRIM ULANG LINK VERIFIKASI
     * 
     * 
     * Fungsi: Mengirim ulang notifikasi verifikasi email.
     * 
     * Alur Proses:
     * 1. Cek apakah user sudah verified emailnya
     * 2. Jika sudah, redirect ke HOME
     * 3. Jika belum, kirim notifikasi verifikasi
     * 4. Redirect back dengan status
     * 
     * Query yang Digunakan:
     * - $request->user()->hasVerifiedEmail(): Cek status verifikasi
     * - $request->user()->sendEmailVerificationNotification(): Kirim email
     * 
     * Output:
     * - Redirect ke HOME jika sudah verified
     * - Redirect back with('status', 'verification-link-sent') jika berhasil
     */
    public function store(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::HOME);
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}