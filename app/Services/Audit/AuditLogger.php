<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditLogger
{
    public function log(
        string $action,
        ?User $actor = null,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?Request $request = null,
    ): AuditLog {
        $request ??= request();

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_role' => $actor?->role instanceof \BackedEnum
                ? $actor->role->value
                : ($actor?->role),
            'action' => $action,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => $oldValues === null ? null : self::maskSensitive($oldValues),
            'new_values' => $newValues === null ? null : self::maskSensitive($newValues),
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => AppTimezone::now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function maskSensitive(array $values): array
    {
        $masked = [];
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $masked[$key] = self::maskSensitive($value);

                continue;
            }

            $keyLower = Str::lower((string) $key);
            if (Str::contains($keyLower, ['password', 'secret', 'token', 'recovery'])) {
                $masked[$key] = '[redacted]';

                continue;
            }

            if (Str::contains($keyLower, ['account_identifier', 'identifier', 'account_number', 'iban'])) {
                $masked[$key] = self::maskIdentifier((string) $value);

                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }

    public static function maskIdentifier(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $len = mb_strlen($value);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', max(0, $len - 4)).mb_substr($value, -4);
    }
}
