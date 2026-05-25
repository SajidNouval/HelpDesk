<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Chatbot extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'chatbot';

    protected $fillable = [
        'keywords',
        'response',
        'category_id',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    // Relasi ke Category
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // Scope untuk query aktif saja
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope untuk urutkan berdasarkan priority
    public function scopeOrderByPriority($query)
    {
        return $query->orderBy('priority', 'desc');
    }

    // Helper untuk parse keywords
    public function getKeywordsArray(): array
    {
        return array_filter(
            array_map('trim', explode(',', $this->keywords))
        );
    }
}

