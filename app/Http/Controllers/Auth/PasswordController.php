<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * =============================================================================
 * PASSWORD CONTROLLER - PENGELOLAAN KATA SANDI
 * =============================================================================
 * 
 * Controller ini menangani perubahan kata sandi pengguna.
 * 
 * Fitur Utama:
 * - Update kata sandi pengguna
 * - Validasi password saat ini
 * - Validasi kekuatan password baru
 */
class PasswordController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE UPDATE - PERBARUI KATA SANDI
     * =========================================================================
     * 
     * Fungsi: Memperbarui kata sandi pengguna.
     * 
     * Alur Proses:
     * 1. Validasi current_password (harus sesuai)
     * 2. Validasi password baru (minimal 8 karakter, confirmed)
     * 3. Hash password baru
     * 4. Update record user
     * 5. Redirect back dengan status
     * 
     * Query yang Digunakan:
     * - $request->user()->update(['password' => Hash::make(...)]):
     *   Update password user
     * 
     * Output:
     * - Redirect back with('status', 'password-updated')
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}