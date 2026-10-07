<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        $sendResetLink = $this->boolean('send_reset_link');

        return [
            'send_reset_link' => ['sometimes', 'boolean'],
            'password' => $sendResetLink
                ? ['prohibited']
                : ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'must_change_password' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'password.min' => 'The password must be at least 12 characters.',
            'password.mixed' => 'The password must include upper and lower case letters.',
            'password.numbers' => 'The password must include at least one number.',
            'password.symbols' => 'The password must include at least one symbol.',
        ];
    }
}
