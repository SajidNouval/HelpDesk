<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, HasUlids;
    protected $table = 'categories';

    protected $fillable = [
        'name',
        'description',
    ];

    public function articles()
    {
        return $this->hasMany(Article::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class);
    }
}