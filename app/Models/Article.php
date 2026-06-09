<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

/**
 * =========================================================================
 * MODEL ARTICLE
 * =========================================================================
 *
 * Model ini merepresentasikan tabel articles.
 *
 * Tanggung Jawab:
 * - Menyimpan data artikel.
 * - Mengelola relasi artikel dengan kategori, staff, dan feedback.
 * - Menyediakan fitur pencarian dengan Laravel Scout.
 * - Menyediakan scope dan accessor untuk artikel.
 *
 * Relasi:
 * - belongsTo(Category): Kategori artikel
 * - belongsTo(User): Penulis artikel (staff)
 * - hasMany(ArticleFeedback): Feedback artikel
 */
class Article extends Model
{
    use HasFactory, HasUlids, Searchable;

    protected $table = 'articles';

    protected $fillable = [
        'category_id',
        'staff_id',
        'title',
        'slug',
        'content',
        'excerpt',
        'keywords',
        'views',
        'is_published',
        'is_hidden',
        'publish_status',
        'rejection_note',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_hidden' => 'boolean',
        'views' => 'integer',
    ];

    /**
     * Fungsi:
     * Mengambil kategori yang dimiliki artikel.
     *
     * Output:
     * - Relasi belongsTo Category.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Fungsi:
     * Mengambil staff (penulis) yang membuat artikel.
     *
     * Output:
     * - Relasi belongsTo User.
     */
    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Fungsi:
     * Mengambil semua feedback yang diberikan pada artikel.
     *
     * Output:
     * - Relasi hasMany ArticleFeedback.
     */
    public function feedback()
    {
        return $this->hasMany(ArticleFeedback::class, 'article_id');
    }

    /**
     * Fungsi:
     * Menentukan nama index untuk Laravel Scout.
     *
     * Output:
     * - String nama index 'articles'.
     */
    public function searchableAs(): string
    {
        return 'articles';
    }

    /**
     * Fungsi:
     * Menentukan data yang di-index ke search engine.
     *
     * Output:
     * - Array data artikel untuk indexing.
     */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'content' => $this->content,
            'category_name' => $this->category?->name,
            'slug' => $this->slug,
            'is_published' => $this->is_published,
            'views' => $this->views,
            'created_at' => $this->created_at?->timestamp,
        ];
    }

    /**
     * Fungsi:
     * Menentukan apakah artikel harus di-index.
     *
     * Output:
     * - Boolean true jika artikel published.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_published;
    }
} 