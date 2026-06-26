<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * =========================================================================
 * MODEL CATEGORY DOMAIN KEYWORD
 * =========================================================================
 *
 * Model ini merepresentasikan tabel category_domain_keywords.
 * Setiap record menyimpan satu kata kunci yang diasosiasikan dengan
 * sebuah kategori, digunakan oleh DomainDetectionService untuk mendeteksi
 * domain query pengguna secara dinamis dari database.
 *
 * Relasi:
 * - belongsTo(Category): Kategori pemilik keyword ini
 *
 * Observer:
 * - CategoryDomainKeywordObserver: Invalidasi cache domain detection
 *   secara otomatis saat keyword dibuat, diubah, atau dihapus
 */
class CategoryDomainKeyword extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'category_domain_keywords';

    protected $fillable = [
        'category_id',
        'keyword',
    ];

    /**
     * Fungsi:
     * Mengambil kategori yang memiliki keyword ini.
     *
     * Output:
     * - Relasi belongsTo Category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
