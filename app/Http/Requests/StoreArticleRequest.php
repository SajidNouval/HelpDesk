<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

/**
 * =========================================================================
 * STORE ARTICLE REQUEST - VALIDASI PEMBUATAN ARTIKEL
 * =========================================================================
 *
 * Form Request ini merangkum semua aturan validasi untuk pembuatan
 * dan pembaruan artikel oleh staff.
 *
 * Aturan Validasi:
 * - title: wajib, string, unik per staff, maks 200 karakter
 * - content: wajib, string
 * - category_id: wajib, harus ada di tabel categories
 * - excerpt: opsional, string, maks 500 karakter
 * - keywords: opsional, string, maks 500 karakter
 */
class StoreArticleRequest extends FormRequest
{
    /**
     * Tentukan apakah user berhak membuat request ini.
     * Hanya staff yang terautentikasi yang boleh membuat artikel.
     */
    public function authorize(): bool
    {
        return Auth::check() && Auth::user()->role === 'staff';
    }

    /**
     * Aturan validasi untuk request ini.
     */
    public function rules(): array
    {
        $articleId = $this->route('article')?->id;

        return [
            'title'       => [
                'required',
                'string',
                'max:200',
                // Judul unik per staff, abaikan artikel saat ini saat update
                \Illuminate\Validation\Rule::unique('articles')->where(function ($query) {
                    return $query->where('staff_id', Auth::id());
                })->ignore($articleId),
            ],
            'content'     => 'required|string',
            'category_id' => 'required|exists:categories,id',
            'excerpt'     => 'nullable|string|max:500',
            'keywords'    => 'nullable|string|max:500',
        ];
    }

    /**
     * Pesan error kustom untuk setiap aturan validasi.
     */
    public function messages(): array
    {
        return [
            'title.required'       => 'Judul artikel wajib diisi.',
            'title.max'            => 'Judul maksimal 200 karakter.',
            'title.unique'         => 'Anda sudah memiliki artikel dengan judul yang sama.',
            'content.required'     => 'Konten artikel wajib diisi.',
            'category_id.required' => 'Kategori wajib dipilih.',
            'category_id.exists'   => 'Kategori yang dipilih tidak valid.',
            'excerpt.max'          => 'Ringkasan maksimal 500 karakter.',
            'keywords.max'         => 'Keywords maksimal 500 karakter.',
        ];
    }
}
