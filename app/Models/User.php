<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * =========================================================================
 * MODEL USER
 * =========================================================================
 *
 * Model ini merepresentasikan tabel users.
 *
 * Tanggung Jawab:
 * - Menyimpan data pengguna (admin dan staff).
 * - Mengelola autentikasi dan otorisasi.
 * - Mengelola relasi user dengan tiket, artikel, dan staff profiles.
 *
 * Relasi:
 * - hasMany(StaffProfile): Profil staff
 * - hasMany(Ticket): Tiket yang ditugaskan ke staff
 * - hasMany(Article): Artikel yang dibuat staff
 */
class User extends Authenticatable
{


    use HasApiTokens, HasFactory, HasUlids, Notifiable;
    protected $table = 'users';

    /**
     * Fungsi:
     * Memeriksa apakah user adalah admin.
     *
     * Output:
     * - Boolean true jika role adalah admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Fungsi:
     * Memeriksa apakah user adalah staff.
     *
     * Output:
     * - Boolean true jika role adalah staff.
     */
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

    /**
     * Fungsi:
     * Mengambil semua staff profiles milik user.
     *
     * Output:
     * - Relasi hasMany StaffProfile.
     */
    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class);
    }

    /**
     * Fungsi:
     * Mengambil semua tiket yang ditugaskan ke staff.
     *
     * Output:
     * - Relasi hasMany Ticket.
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'staff_id');
    }

    /**
     * Fungsi:
     * Mengambil semua artikel yang dibuat oleh staff.
     *
     * Output:
     * - Relasi hasMany Article.
     */
    public function articles()
    {
        return $this->hasMany(Article::class, 'staff_id');
    }
}
