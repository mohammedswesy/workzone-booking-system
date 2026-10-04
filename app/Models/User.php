<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isOwner(): bool
    {
        return $this->role === Role::Owner;
    }

    public function isUser(): bool
    {
        return $this->role === Role::User;
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function spaces(): HasMany
    {
        return $this->hasMany(Workspace::class, 'owner_id');
    }

    public function hasFinancialHistory(): bool
    {
        if ($this->bookings()->exists()) {
            return true;
        }

        if (Payment::query()->whereHas('booking', fn ($q) => $q->where('user_id', $this->id))->exists()) {
            return true;
        }

        $workspaceIds = $this->spaces()->pluck('id');
        if ($workspaceIds->isNotEmpty() && Booking::query()->whereIn('workspace_id', $workspaceIds)->exists()) {
            return true;
        }

        return false;
    }

    public static function adminCount(): int
    {
        return static::query()->where('role', Role::Admin)->count();
    }

    public function isLastAdmin(): bool
    {
        return $this->isAdmin() && static::adminCount() <= 1;
    }
}
