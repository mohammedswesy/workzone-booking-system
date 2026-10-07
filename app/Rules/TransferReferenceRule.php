<?php

namespace App\Rules;

use App\Models\Payment;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class TransferReferenceRule implements ValidationRule
{
    public function __construct(
        private readonly ?int $platformPaymentMethodId = null,
        private readonly ?int $ignorePaymentId = null,
        private readonly bool $required = true,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $raw = is_string($value) ? $value : (string) $value;
        $trimmed = trim($raw);

        if ($trimmed === '') {
            if ($this->required) {
                $fail('A transaction / reference number is required for this method.');
            }

            return;
        }

        if ($trimmed !== $raw) {
            $fail('The reference must not have leading or trailing spaces.');

            return;
        }

        $length = mb_strlen($trimmed);
        if ($length < 6 || $length > 64) {
            $fail('The reference must be between 6 and 64 characters.');

            return;
        }

        if (! preg_match('/^[A-Za-z0-9\-]+$/', $trimmed)) {
            $fail('The reference may only contain letters, digits, and dashes.');

            return;
        }

        $chars = preg_split('//u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $distinct = count(array_unique($chars));
        if ($distinct < 4) {
            $fail('The reference must contain at least 4 distinct characters.');

            return;
        }

        if (preg_match('/^0+$/', $trimmed) === 1 || $distinct === 1) {
            $fail('The reference cannot be all zeros or a repeated character.');

            return;
        }

        if ($this->platformPaymentMethodId === null) {
            return;
        }

        $exists = Payment::query()
            ->when(
                $this->platformPaymentMethodId,
                fn ($q) => $q->where('platform_payment_method_id', $this->platformPaymentMethodId),
            )
            ->when($this->ignorePaymentId, fn ($q) => $q->whereKeyNot($this->ignorePaymentId))
            ->whereRaw('LOWER(transfer_reference) = ?', [Str::lower($trimmed)])
            ->exists();

        if ($exists) {
            $fail('This reference number was already used for this payment method.');
        }
    }

    /**
     * Report-only helper for existing rows (never mutates).
     *
     * @return list<string>
     */
    public static function invalidReasons(string $reference): array
    {
        $reasons = [];
        $raw = $reference;
        $trimmed = trim($raw);

        if ($trimmed !== $raw) {
            $reasons[] = 'leading_or_trailing_whitespace';
        }
        $length = mb_strlen($trimmed);
        if ($length < 6 || $length > 64) {
            $reasons[] = 'length_out_of_range';
        }
        if ($trimmed !== '' && ! preg_match('/^[A-Za-z0-9\-]+$/', $trimmed)) {
            $reasons[] = 'invalid_characters';
        }
        $chars = preg_split('//u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $distinct = count(array_unique($chars));
        if ($trimmed !== '' && $distinct < 4) {
            $reasons[] = 'fewer_than_4_distinct_characters';
        }
        if ($trimmed !== '' && (preg_match('/^0+$/', $trimmed) === 1 || $distinct === 1)) {
            $reasons[] = 'all_zeros_or_repeated';
        }

        return $reasons;
    }
}
