<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * =========================================================================
 * MODEL ARTICLE FEEDBACK
 * =========================================================================
 *
 * Model ini merepresentasikan tabel article_feedback.
 *
 * Tanggung Jawab:
 * - Menyimpan feedback pengguna terhadap artikel.
 * - Mengelola relasi feedback dengan artikel.
 * - Menyimpan IP address untuk mencegah spam.
 *
 * Relasi:
 * - belongsTo(Article): Artikel yang diberi feedback
 */
class ArticleFeedback extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'article_feedback';

    protected $fillable = [
        'article_id',
        'is_helpful',
        'ip_address',
    ];

    protected $casts = [
        'is_helpful' => 'boolean',
    ];

    /**
     * Fungsi:
     * Mengambil artikel yang diberi feedback.
     *
     * Output:
     * - Relasi belongsTo Article.
     */
    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}