<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

/**
 * Legacy workspace payment_instructions / payment_methods JSON are optional notes only.
 * Platform payment methods (admin settings) are required for guest checkout.
 */
trait ValidatesWorkspacePaymentInstructions
{
    /**
     * @return array<string, mixed>
     */
    protected function paymentInstructionRules(): array
    {
        return [
            'payment_instructions' => ['nullable', 'string', 'max:2000'],
            'payment_methods' => ['nullable', 'array'],
            'payment_methods.*' => ['string'],
        ];
    }

    protected function validatePublishedPaymentInstructions(Validator $validator): void
    {
        // Intentionally empty: publishing no longer requires per-workspace payment setup.
    }
}
