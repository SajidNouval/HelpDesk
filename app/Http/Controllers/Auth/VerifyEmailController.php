<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

/**
 * =============================================================================
 * VERIFY EMAIL CONTROLLER - VERIFIKASI EMAIL
 * =============================================================================
 * 
 * Controller ini menangani proses verifikasi email pengguna.
 * 
 * Fitur Utama:
 * - Verifikasi email menggunakan link yang dikirim via email
 * - Fire event Verified setelah verifikasi berhasil
 */
class VerifyEmailController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE __INVOKE - PROSES VERIFIKASI EMAIL
     * =========================================================================
     * 
     * Fungsi: Memverifikasi email pengguna melalui link verifikasi.
     * 
     * Alur Proses:
     * 1. Cek apakah user sudah verified emailnya
     * 2. Jika sudah, redirect ke HOME dengan parameter verified=1
     * 3. Jika belum, panggil markEmailAsVerified()
     * 4. Fire event Verified untuk trigger listener
     * 5. Redirect ke HOME dengan parameter verified=1
     * 
     * Query yang Digunakan:
     * - $request->user()->hasVerifiedEmail(): Cek status verifikasi
     * - $request->user()->markEmailAsVerified(): Tandai email sebagai verified
     * - event(new Verified($request->user())): Fire event verifikasi
     * 
     * Output:
     * - Redirect ke HOME?verified=1
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::HOME.'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(RouteServiceProvider::HOME.'?verified=1');
    }
}