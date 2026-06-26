<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * =========================================================================
 * MODEL CATEGORY
 * =========================================================================
 *
 * Model ini merepresentasikan tabel categories.
 *
 * Tanggung Jawab:
 * - Menyimpan data kategori artikel.
 * - Mengelola relasi kategori dengan artikel dan staff profiles.
 *
 * Relasi:
 * - hasMany(Article): Artikel dalam kategori
 * - hasMany(StaffProfile): Staff yang ditugaskan ke kategori
 */
class Category extends Model
{
    use HasFactory, HasUlids;
    protected $table = 'categories';

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * Fungsi:
     * Mengambil semua artikel dalam kategori.
     *
     * Output:
     * - Relasi hasMany Article.
     */
    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    /**
     * Fungsi:
     * Mengambil semua staff profiles yang ditugaskan ke kategori.
     *
     * Output:
     * - Relasi hasMany StaffProfile.
     */
    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }

    /**
     * Fungsi:
     * Mengambil semua kata kunci domain yang diasosiasikan dengan kategori ini.
     * Digunakan oleh DomainDetectionService untuk deteksi domain dinamis.
     *
     * Output:
     * - Relasi hasMany CategoryDomainKeyword.
     */
    public function domainKeywords(): HasMany
    {
        return $this->hasMany(CategoryDomainKeyword::class);
    }
}