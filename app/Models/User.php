<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // تعريف ثوابت الأدوار
    public const ROLE_ADMIN = 'admin';
    public const ROLE_OWNER = 'owner';
    public const ROLE_USER = 'user';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',  // تأكد إنه موجود في جدول المستخدمين
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function isUser(): bool
    {
        return $this->role === self::ROLE_USER;
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
