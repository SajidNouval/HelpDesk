<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

/**
 * =============================================================================
 * REGISTERED USER CONTROLLER - REGISTRASI PENGGUNA BARU
 * =============================================================================
 * 
 * Controller ini menangani pendaftaran pengguna baru ke dalam sistem.
 * 
 * Fitur Utama:
 * - Tampilan form registrasi
 * - Validasi data pendaftaran
 * - Pembuatan akun pengguna baru
 * - Auto-login setelah registrasi berhasil
 * 
 * Model Terkait:
 * - User: Model pengguna
 */
class RegisteredUserController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE CREATE - TAMPILKAN FORM REGISTRASI
     * =========================================================================
     * 
     * Fungsi: Menampilkan halaman pendaftaran.
     * 
     * Output:
     * - View 'auth.register'
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * =========================================================================
     * 2. METODE STORE - PROSES REGISTRASI
     * =========================================================================
     * 
     * Fungsi: Memproses pendaftaran pengguna baru.
     * 
     * Alur Proses:
     * 1. Validasi input (name, email, password)
     * 2. Buat user baru dengan password yang sudah di-hash
     * 3. Fire event Registered untuk trigger listener
     * 4. Auto-login pengguna
     * 5. Redirect ke halaman home
     * 
     * Query yang Digunakan:
     * - User::create([...]): Insert user baru
     * - Hash::make($password): Hash password
     * - event(new Registered($user)): Trigger event
     * - Auth::login($user): Auto-login
     * 
     * Output:
     * - Redirect ke RouteServiceProvider::HOME
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }
}