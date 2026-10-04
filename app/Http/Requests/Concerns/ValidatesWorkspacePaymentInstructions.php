<?php

namespace App\Http\Requests\Concerns;

use App\Enums\PaymentMethod;
use App\Enums\WorkspaceStatus;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'payment_methods.*' => ['string', Rule::in(PaymentMethod::values())],
        ];
    }

    protected function validatePublishedPaymentInstructions(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $status = $this->input('status', WorkspaceStatus::Draft->value);
            if ($status instanceof WorkspaceStatus) {
                $status = $status->value;
            }

            if ($status !== WorkspaceStatus::Published->value) {
                return;
            }

            $instructions = trim((string) $this->input('payment_instructions', ''));
            $methods = $this->input('payment_methods', []);

            if ($instructions === '') {
                $v->errors()->add('payment_instructions', 'Payment instructions are required before publishing.');
            }

            if (! is_array($methods) || count($methods) < 1) {
                $v->errors()->add('payment_methods', 'Select at least one accepted payment method before publishing.');
            }
        });
    }
}
