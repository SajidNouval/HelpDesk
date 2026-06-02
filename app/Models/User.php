<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{


    use HasApiTokens, HasFactory, HasUlids, Notifiable;
    protected $table = 'users';

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class);
    }

    public function categories()
    {
        return $this->hasManyThrough(Category::class, StaffProfile::class, 'user_id', 'id', 'id', 'category_id');
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'staff_id');
    }

    public function articles()
    {
        return $this->hasMany(Article::class, 'staff_id');
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }
}
