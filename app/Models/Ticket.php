<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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

    // Relasi
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function assignedStaff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function logs()
    {
        return $this->hasMany(TicketLog::class);
    }

    public function staffProfile()
    {
        return $this->hasOneThrough(
            StaffProfile::class,
            User::class,
            'id',          // foreign key di users
            'user_id',     // foreign key di staff_profiles
            'staff_id',    // local key di tickets
            'id'           // local key di users
        );
    }
}