<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * =========================================================================
 * STORE TICKET REQUEST - VALIDASI PEMBUATAN TIKET
 * =========================================================================
 *
 * Form Request ini merangkum semua aturan validasi untuk pembuatan tiket baru.
 * Sebelumnya validasi dilakukan secara inline di TicketController::store().
 *
 * Aturan Validasi:
 * - name: wajib, string, maks 50 karakter
 * - email: wajib, format email, maks 50 karakter
 * - subject: wajib, string, maks 200 karakter
 * - message: wajib, string
 * - category_id: wajib, harus ada di tabel categories
 * - captcha: wajib untuk non-JSON request (web form)
 */
class StoreTicketRequest extends FormRequest
{
    /**
     * Tentukan apakah user berhak membuat request ini.
     * Semua user (termasuk guest) boleh membuat tiket.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi untuk request ini.
     */
    public function rules(): array
    {
        $rules = [
            'name'        => 'required|string|max:50',
            'email'       => 'required|email|max:50',
            'subject'     => 'required|string|max:200',
            'message'     => 'required|string|max:5000',
            'category_id' => 'required|exists:categories,id',
        ];

        // Captcha hanya diperlukan untuk request web (bukan API/JSON)
        if (!$this->expectsJson()) {
            $rules['captcha'] = 'required|string';
        }

        return $rules;
    }

    /**
     * Pesan error kustom untuk setiap aturan validasi.
     */
    public function messages(): array
    {
        return [
            'name.required'        => 'Nama wajib diisi.',
            'name.max'             => 'Nama maksimal 50 karakter.',
            'email.required'       => 'Email wajib diisi.',
            'email.email'          => 'Format email tidak valid.',
            'email.max'            => 'Email maksimal 50 karakter.',
            'subject.required'     => 'Subjek tiket wajib diisi.',
            'subject.max'          => 'Subjek maksimal 200 karakter.',
            'message.required'     => 'Pesan wajib diisi.',
            'message.max'          => 'Pesan terlalu panjang (maks 5000 karakter).',
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists'   => 'Kategori yang dipilih tidak valid.',
            'captcha.required'     => 'Kode captcha wajib diisi.',
        ];
    }
}
