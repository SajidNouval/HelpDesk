<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * =========================================================================
 * MODEL TICKET OTP
 * =========================================================================
 *
 * Model ini merepresentasikan tabel ticket_otps.
 *
 * Tanggung Jawab:
 * - Menyimpan data OTP untuk verifikasi tiket.
 * - Menyimpan data sementara tiket sebelum verifikasi.
 * - Mengelola relasi OTP dengan kategori.
 *
 * Relasi:
 * - belongsTo(Category): Kategori tiket
 */
class TicketOtp extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'ticket_otps';

    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'category_id',
        'type',
        'otp_code',
        'attempts',
        'expires_at',
        'token',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
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
}
