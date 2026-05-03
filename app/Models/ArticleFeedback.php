<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ArticleFeedback extends Model
{
    use HasFactory;

    protected $table = 'article_feedback';

    protected $fillable = [
        'article_id',
        'is_helpful',
        'ip_address',
    ];

    protected $casts = [
        'is_helpful' => 'boolean',
    ];

    // Relasi
    public function article()
    {
        return $this->belongsTo(Article::class);
    }
}