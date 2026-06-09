<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * =========================================================================
 * REQUEST VALIDATION PROFILE UPDATE REQUEST
 * =========================================================================
 *
 * Request validation untuk update profil user.
 *
 * Tanggung Jawab:
 * - Validasi input name dan email.
 * - Memastikan email unik untuk user (kecuali user sendiri).
 */
class ProfileUpdateRequest extends FormRequest
{
    /**
     * Fungsi:
     * Mendapatkan aturan validasi untuk update profil.
     *
     * Output:
     * - Array aturan validasi untuk name dan email.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
        ];
    }
}
