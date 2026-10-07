<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Role from the client is ignored — owners endpoint always creates owners.
        $this->request->remove('role');

        if ($this->has('email')) {
            $this->merge(['email' => strtolower(trim((string) $this->input('email')))]);
        }
    }

    public function rules(): array
    {
        $sendInvitation = $this->boolean('send_invitation');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'send_invitation' => ['sometimes', 'boolean'],
            'password' => $sendInvitation
                ? ['prohibited']
                : ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
            'must_change_password' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'An account with this email already exists.',
            'password.min' => 'The password must be at least 12 characters.',
            'password.mixed' => 'The password must include upper and lower case letters.',
            'password.numbers' => 'The password must include at least one number.',
            'password.symbols' => 'The password must include at least one symbol.',
        ];
    }
}
