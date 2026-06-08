<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * =============================================================================
 * NEW PASSWORD CONTROLLER - RESET KATA SANDI BARU
 * =============================================================================
 * 
 * Controller ini menangani proses reset password menggunakan token.
 * 
 * Fitur Utama:
 * - Tampilan form reset password
 * - Validasi token reset
 * - Update password baru
 */
class NewPasswordController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE CREATE - TAMPILKAN FORM RESET PASSWORD
     * =========================================================================
     * 
     * Fungsi: Menampilkan halaman reset password dengan token.
     * 
     * Alur Proses:
     * 1. Ambil token dan email dari request
     * 2. Kembalikan view reset-password dengan data token dan email
     * 
     * Output:
     * - View 'auth.reset-password'
     */
    public function create(Request $request): View
    {
        return view('auth.reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]);
    }

    /**
     * =========================================================================
     * 2. METODE STORE - PROSES RESET PASSWORD
     * =========================================================================
     * 
     * Fungsi: Memproses reset password dengan token.
     * 
     * Alur Proses:
     * 1. Validasi input (token, email, password)
     * 2. Reset password menggunakan Password::reset
     * 3. Jika berhasil:
     *    - Fire event PasswordReset
     *    - Redirect ke login dengan pesan sukses
     * 4. Jika gagal:
     *    - Redirect back with error
     * 
     * Query yang Digunakan:
     * - Password::reset([...], function($user) {...}): Reset password
     * - $user->forceFill([...]): Update password tanpa validasi
     * - $user->setRememberToken(...): Set remember token baru
     * - $user->save(): Simpan perubahan
     * 
     * Output:
     * - Redirect ke login dengan status jika berhasil
     * - Redirect back withErrors jika gagal
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) use ($request) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status == Password::PASSWORD_RESET
                    ? redirect()->route('login')->with('status', __($status))
                    : back()->withInput($request->only('email'))
                            ->withErrors(['email' => __($status)]);
    }
}