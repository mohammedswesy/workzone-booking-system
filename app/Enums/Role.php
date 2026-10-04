<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Owner = 'owner';
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Owner => 'Owner',
            self::User => 'User',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
