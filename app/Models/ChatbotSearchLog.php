<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ChatbotSearchLog extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'chatbot_search_logs';

    protected $fillable = [
        'query_original',
        'query_normalized',
        'detected_domain',
        'confidence',
        'results_count',
        'top_result_id',
        'top_result_title',
        'top_result_score',
        'is_fallback_triggered',
        'ip_address',
        'user_id',
    ];

    protected $casts = [
        'confidence' => 'float',
        'results_count' => 'integer',
        'top_result_score' => 'float',
        'is_fallback_triggered' => 'boolean',
    ];

    /**
     * Relationship with the User who made the search.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with the top recommended article.
     */
    public function topResult()
    {
        return $this->belongsTo(Article::class, 'top_result_id');
    }
}
