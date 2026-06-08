<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * =============================================================================
 * CONFIRMABLE PASSWORD CONTROLLER - KONFIRMASI PASSWORD
 * =============================================================================
 * 
 * Controller ini menangani konfirmasi password pengguna untuk aksi sensitif.
 * 
 * Fitur Utama:
 * - Tampilan form konfirmasi password
 * - Validasi password sebelum melanjutkan ke aksi penting
 */
class ConfirmablePasswordController extends Controller
{
    /**
     * 1. METODE SHOW - TAMPILKAN FORM KONFIRMASI
     *
     * Fungsi: Menampilkan halaman konfirmasi password.
     *
     * Output:
     * - View 'auth.confirm-password'
     */
    public function show(): View
    {
        return view('auth.confirm-password');
    }

    /**
     * 2. METODE STORE - PROSES KONFIRMASI PASSWORD
     *
     * Fungsi: Memvalidasi password pengguna untuk konfirmasi.
     *
     * Alur Proses:
     * 1. Validasi password menggunakan Auth::guard('web')->validate()
     * 2. Jika password salah, throw ValidationException
     * 3. Jika password benar, simpan timestamp konfirmasi ke session
     * 4. Redirect ke halaman yang dituju
     *
     * Query yang Digunakan:
     * - Auth::guard('web')->validate([...]): Validasi kredensial
     * - $request->session()->put('auth.password_confirmed_at', time()):
     *   Simpan timestamp konfirmasi
     *
     * Output:
     * - Redirect ke intended route jika berhasil
     * - ValidationException jika password salah
     */
    public function store(Request $request): RedirectResponse
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        return redirect()->intended(RouteServiceProvider::HOME);
    }
}