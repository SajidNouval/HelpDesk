<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class Article extends Model
{
    use HasFactory, Searchable;

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

    // Relasi
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function feedback()
    {
        return $this->hasMany(ArticleFeedback::class, 'article_id');
    }

    /**
     * Scout: tentukan index name
     */
    public function searchableAs(): string
    {
        return 'articles';
    }

    /**
     * Scout: data yang diindex ke Meilisearch
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
     * Scout: hanya index artikel yang published
     */
    public function shouldBeSearchable(): bool
    {
        return $this->is_published;
    }
} 