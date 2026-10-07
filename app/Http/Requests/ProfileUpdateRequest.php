<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];

        if ($this->user()?->isOwner()) {
            $rules = array_merge($rules, [
                'payout_method' => ['nullable', 'string', Rule::in(PaymentMethod::values())],
                'payout_account_holder' => ['nullable', 'string', 'max:120'],
                'payout_account_identifier' => ['nullable', 'string', 'max:255'],
                'payout_note' => ['nullable', 'string', 'max:1000'],
            ]);
        }

        if ($this->user()?->isAdmin()) {
            $rules['commission_percent'] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }

        return $rules;
    }
}
