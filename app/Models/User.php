<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',  // تأكد تضيف هذا الحقل هنا إذا ستستخدمه في التعيين الجماعي
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

public function isAdmin()
    {
        return $this->is_admin == 1;
    }
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
