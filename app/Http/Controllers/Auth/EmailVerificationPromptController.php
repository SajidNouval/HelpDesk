<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 
 * EMAIL VERIFICATION PROMPT CONTROLLER - PROMPT VERIFIKASI EMAIL
 * 
 * 
 * Controller ini menampilkan prompt verifikasi email untuk pengguna
 * yang belum memverifikasi email mereka.
 * 
 * Fitur Utama:
 * - Tampilan halaman verifikasi email
 * - Redirect jika email sudah terverifikasi
 */
class EmailVerificationPromptController extends Controller
{
    /**
     * 
     * 1. METODE __INVOKE - TAMPILKAN HALAMAN VERIFIKASI
     * 
     * 
     * Fungsi: Menampilkan halaman verifikasi email atau redirect jika sudah verified.
     * 
     * Alur Proses:
     * 1. Cek apakah user sudah verified emailnya
     * 2. Jika sudah, redirect ke intended route atau HOME
     * 3. Jika belum, tampilkan view verifikasi
     * 
     * Query yang Digunakan:
     * - $request->user()->hasVerifiedEmail(): Cek status verifikasi
     * 
     * Output:
     * - Redirect ke intended route jika sudah verified
     * - View 'auth.verify-email' jika belum verified
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        return $request->user()->hasVerifiedEmail()
                    ? redirect()->intended(RouteServiceProvider::HOME)
                    : view('auth.verify-email');
    }
}