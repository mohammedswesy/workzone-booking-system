<?php

namespace App\Http\Requests;

use App\Enums\BookingMode;
use App\Enums\Role;
use App\Enums\WorkspaceStatus;
use App\Http\Requests\Concerns\ValidatesWorkspacePaymentInstructions;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceRequest extends FormRequest
{
    use ValidatesWorkspacePaymentInstructions;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Workspace::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()?->isAdmin()) {
            $this->merge(['owner_id' => null]);
        }

        if (! $this->filled('booking_mode')) {
            $this->merge(['booking_mode' => BookingMode::Seat->value]);
        }
    }

    public function rules(): array
    {
        $isAdmin = (bool) $this->user()?->isAdmin();

        return array_merge([
            'owner_id' => [
                Rule::requiredIf($isAdmin),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($q) {
                    $q->where('role', Role::Owner->value)->where('is_active', true);
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'location' => ['required', 'string', 'max:255'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'capacity' => ['required', 'integer', 'min:1'],
            'booking_mode' => ['required', Rule::enum(BookingMode::class)],
            'price_per_hour' => ['required', 'numeric', 'min:0'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i', 'after:opening_time'],
            'status' => ['nullable', Rule::enum(WorkspaceStatus::class)],
            'featured' => ['sometimes', 'boolean'],
            'amenities' => ['sometimes', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'images' => ['sometimes', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ], $this->paymentInstructionRules());
    }

    public function withValidator($validator): void
    {
        $this->validatePublishedPaymentInstructions($validator);
    }
}
