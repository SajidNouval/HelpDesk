<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'staff_profiles';

    protected $fillable = [
        'user_id',
        'category_id',
        'is_busy',
    ];

    protected $casts = [
        'is_busy' => 'boolean',
    ];

    // Relasi
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}