<?php

namespace App\Services\Payments;

use App\Enums\PaymentStatus;
use Illuminate\Validation\ValidationException;

class PaymentStateMachine
{
    /**
     * Allowed transitions for payment.status.
     *
     * @var array<string, list<string>>
     */
    public const ALLOWED = [
        'pending' => ['paid', 'failed'],
        'paid' => ['refunded'],
        'unpaid' => [],
        'failed' => [],
        'refunded' => [],
    ];

    public function assertCanTransition(PaymentStatus $from, PaymentStatus $to): void
    {
        $allowed = self::ALLOWED[$from->value] ?? [];

        if (! in_array($to->value, $allowed, true)) {
            throw ValidationException::withMessages([
                'payment' => "Illegal payment transition from {$from->value} to {$to->value}.",
            ]);
        }
    }

    public function canTransition(PaymentStatus $from, PaymentStatus $to): bool
    {
        $allowed = self::ALLOWED[$from->value] ?? [];

        return in_array($to->value, $allowed, true);
    }
}
