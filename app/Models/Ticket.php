<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * =========================================================================
 * MODEL TICKET
 * =========================================================================
 *
 * Model ini merepresentasikan tabel tickets.
 *
 * Tanggung Jawab:
 * - Menyimpan data tiket helpdesk.
 * - Mengelola relasi tiket dengan kategori, staff, user, pesan, dan log.
 * - Menyimpan status dan priority tiket.
 *
 * Relasi:
 * - belongsTo(Category): Kategori tiket
 * - belongsTo(User): Staff yang menangani tiket
 * - belongsTo(User): User yang membuat tiket
 * - hasMany(Message): Pesan dalam tiket
 * - hasMany(TicketLog): Log aktivitas tiket
 */
class Ticket extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'tickets';

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'category_id',
        'user_id',
        'staff_id',
        'status',
        'priority',
        'assigned_at',
        'closed_at',
        'email_verified_at',
        'tracking_token',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'closed_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    /**
     * Fungsi:
     * Mengambil kategori tiket.
     *
     * Output:
     * - Relasi belongsTo Category.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Fungsi:
     * Mengambil staff yang ditugaskan menangani tiket.
     *
     * Output:
     * - Relasi belongsTo User.
     */
    public function assignedStaff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /**
     * Fungsi:
     * Mengambil user yang membuat tiket.
     *
     * Output:
     * - Relasi belongsTo User.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Fungsi:
     * Mengambil semua pesan dalam tiket.
     *
     * Output:
     * - Relasi hasMany Message.
     */
    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Fungsi:
     * Mengambil semua log aktivitas tiket.
     *
     * Output:
     * - Relasi hasMany TicketLog.
     */
    public function logs()
    {
        return $this->hasMany(TicketLog::class);
    }
}