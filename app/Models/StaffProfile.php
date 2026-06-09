<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * =========================================================================
 * MODEL STAFF PROFILE
 * =========================================================================
 *
 * Model ini merepresentasikan tabel staff_profiles.
 *
 * Tanggung Jawab:
 * - Menyimpan profil staff helpdesk.
 * - Mengelola relasi staff dengan user dan kategori.
 * - Menyimpan status busy/idle staff.
 *
 * Relasi:
 * - belongsTo(User): User terkait profil staff
 * - belongsTo(Category): Kategori penugasan staff
 */
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

    /**
     * Fungsi:
     * Mengambil user terkait profil staff.
     *
     * Output:
     * - Relasi belongsTo User.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Fungsi:
     * Mengambil kategori penugasan staff.
     *
     * Output:
     * - Relasi belongsTo Category.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}