<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketOtp extends Model
{
    use HasFactory;

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

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
