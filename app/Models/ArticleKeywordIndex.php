<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleKeywordIndex extends Model
{
    use HasFactory;

    protected $table = 'article_keyword_index';

    public $timestamps = false;

    protected $fillable = [
        'article_id',
        'keyword',
        'tf',
        'field_boosts',
    ];

    protected $casts = [
        'field_boosts' => 'array',
    ];

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Disable timestamps untuk performance
     */
    public const CREATED_AT = null;
    public const UPDATED_AT = null;
}
