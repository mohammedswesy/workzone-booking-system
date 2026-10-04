<?php

namespace App\Http\Requests;

use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Enums\Role;
use App\Enums\WorkspaceStatus;
use App\Http\Requests\Concerns\ValidatesWorkspacePaymentInstructions;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkspaceRequest extends FormRequest
{
    use ValidatesWorkspacePaymentInstructions;

    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $this->user()?->can('update', $workspace) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()?->isAdmin()) {
            $this->request->remove('owner_id');
        }

        /** @var Workspace|null $workspace */
        $workspace = $this->route('workspace');

        if (! $this->filled('booking_mode') && $workspace) {
            $this->merge([
                'booking_mode' => $workspace->booking_mode?->value ?? BookingMode::Seat->value,
            ]);
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

        $validator->after(function (Validator $v) {
            /** @var Workspace|null $workspace */
            $workspace = $this->route('workspace');
            if (! $workspace) {
                return;
            }

            $incoming = (string) $this->input('booking_mode');
            $current = $workspace->booking_mode instanceof BookingMode
                ? $workspace->booking_mode->value
                : (string) $workspace->booking_mode;

            if ($incoming === $current) {
                return;
            }

            $hasActiveFuture = $workspace->bookings()
                ->whereIn('status', BookingStatus::blocking())
                ->where('end_at', '>', now())
                ->exists();

            if ($hasActiveFuture) {
                $v->errors()->add(
                    'booking_mode',
                    'Booking mode cannot be changed while this space has pending or confirmed bookings that have not ended yet.'
                );
            }
        });
    }
}
