<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

/**
 * =============================================================================
 * PROFILE CONTROLLER - PENGELOLAAN PROFIL PENGGUNA
 * =============================================================================
 * 
 * Controller ini bertanggung jawab untuk mengelola profil pengguna termasuk
 * menampilkan form profil, memperbarui informasi profil, dan menghapus akun.
 * 
 * Fitur Utama:
 * - Tampilan form edit profil
 * - Update informasi profil (name, email)
 * - Verifikasi email ulang saat email berubah
 * - Hapus akun pengguna
 * 
 * Model Terkait:
 * - User: Model pengguna
 */
class ProfileController extends Controller
{
    /**
     * =========================================================================
     * 1. METODE EDIT - TAMPILKAN FORM PROFIL
     * =========================================================================
     * 
     * Fungsi: Menampilkan form edit profil pengguna.
     * 
     * Alur Proses:
     * 1. Ambil data user yang sedang login dari request
     * 2. Kembalikan view profile.edit dengan data user
     * 
     * Query yang Digunakan:
     * - $request->user(): Mendapatkan user yang terautentikasi
     * 
     * Output:
     * - View 'profile.edit' dengan data user
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * =========================================================================
     * 2. METODE UPDATE - PERBARUI PROFIL
     * =========================================================================
     * 
     * Fungsi: Memperbarui informasi profil pengguna.
     * 
     * Alur Proses:
     * 1. Validasi input melalui ProfileUpdateRequest (form request)
     * 2. Isi data user dengan data yang sudah divalidasi
     * 3. Jika email berubah (isDirty), reset email_verified_at menjadi null
     *    untuk memaksa verifikasi email ulang
     * 4. Simpan perubahan ke database
     * 5. Redirect kembali ke form edit dengan pesan status
     * 
     * Query yang Digunakan:
     * - $request->user()->fill($validated): Isi atribut user
     * - $request->user()->save(): Simpan perubahan ke database
     * 
     * Output:
     * - Redirect ke route('profile.edit') with('status', 'profile-updated')
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * =========================================================================
     * 3. METODE DESTROY - HAPUS AKUN
     * =========================================================================
     * 
     * Fungsi: Menghapus akun pengguna secara permanen.
     * 
     * Alur Proses:
     * 1. Validasi password pengguna untuk konfirmasi
     * 2. Logout pengguna dari sistem
     * 3. Hapus record user dari database
     * 4. Invalidate session
     * 5. Regenerate token untuk keamanan
     * 6. Redirect ke halaman utama
     * 
     * Query yang Digunakan:
     * - Auth::logout(): Logout user
     * - $user->delete(): Hapus record user
     * - $request->session()->invalidate(): Hapus session
     * - $request->session()->regenerateToken(): Buat token baru
     * 
     * Output:
     * - Redirect ke route('/') (halaman utama)
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}