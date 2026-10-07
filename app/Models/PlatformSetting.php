<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PlatformSetting extends Model
{
    public $incrementing = false;

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        return Cache::remember("platform_setting:{$key}", 60, function () use ($key, $default) {
            $row = static::query()->where('key', $key)->first();

            return $row?->value ?? $default;
        });
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => is_scalar($value) || $value === null ? (string) $value : json_encode($value)]
        );
        Cache::forget("platform_setting:{$key}");
    }

    public static function commissionPercent(): string
    {
        $stored = static::getValue('commission_percent');
        if ($stored === null || $stored === '') {
            return bcadd((string) config('payments.commission_percent', 0), '0', 2);
        }

        return bcadd((string) $stored, '0', 2);
    }
}
