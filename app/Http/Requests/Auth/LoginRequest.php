<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * =========================================================================
 * REQUEST VALIDATION LOGIN REQUEST
 * =========================================================================
 *
 * Request validation untuk proses login.
 *
 * Tanggung Jawab:
 * - Validasi input email dan password.
 * - Autentikasi user dengan rate limiting.
 * - Memastikan user yang login memiliki role admin atau staff.
 */
class LoginRequest extends FormRequest
{
    /**
     * Fungsi:
     * Menentukan apakah user diizinkan membuat request ini.
     *
     * Output:
     * - Boolean true (semua user diizinkan login).
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Fungsi:
     * Mendapatkan aturan validasi untuk request login.
     *
     * Output:
     * - Array aturan validasi untuk email dan password.
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:50'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Fungsi:
     * Mencoba mengautentikasi kredensial user.
     *
     * Alur Proses:
     * 1. Cek rate limiting.
     * 2. Coba autentikasi dengan email dan password.
     * 3. Jika gagal, increment rate limiter dan throw error.
     * 4. Jika berhasil, cek role user (harus admin atau staff).
     * 5. Jika role tidak valid, logout dan throw error.
     * 6. Jika valid, clear rate limiter.
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (! in_array(Auth::user()->role, ['admin', 'staff'])) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Fungsi:
     * Memastikan request login tidak melebihi batas rate limiting.
     *
     * Alur Proses:
     * 1. Cek apakah terlalu banyak percobaan login.
     * 2. Jika ya, trigger event Lockout dan throw error.
     * 3. Tampilkan waktu tunggu sebelum bisa mencoba lagi.
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Fungsi:
     * Mendapatkan key untuk rate limiting berdasarkan email dan IP.
     *
     * Output:
     * - String key untuk rate limiter.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
