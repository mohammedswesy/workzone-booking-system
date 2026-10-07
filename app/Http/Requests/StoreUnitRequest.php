<?php

namespace App\Http\Requests;

use App\Enums\BookingMode;
use App\Enums\WorkspaceStatus;
use App\Enums\WorkspaceType;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Venue|null $venue */
        $venue = $this->route('venue');

        return $venue !== null && ($this->user()?->can('update', $venue) ?? false);
    }

    protected function prepareForValidation(): void
    {
        /** @var Venue|null $venue */
        $venue = $this->route('venue');

        if ($venue) {
            $this->merge([
                'venue_id' => $venue->id,
                'owner_id' => $venue->owner_id,
            ]);
        }

        if (! $this->filled('booking_mode')) {
            $this->merge(['booking_mode' => BookingMode::Seat->value]);
        }

        if (! $this->filled('type')) {
            $this->merge(['type' => WorkspaceType::Other->value]);
        }
    }

    public function rules(): array
    {
        /** @var Venue|null $venue */
        $venue = $this->route('venue');

        return [
            'venue_id' => [
                'required',
                'integer',
                Rule::in($venue ? [$venue->id] : []),
            ],
            'owner_id' => [
                'required',
                'integer',
                Rule::in($venue ? [$venue->owner_id] : []),
            ],
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'booking_mode' => ['required', Rule::enum(BookingMode::class)],
            'type' => ['required', Rule::enum(WorkspaceType::class)],
            'price_per_hour' => ['required', 'numeric', 'min:0'],
            'opening_time' => ['nullable', 'date_format:H:i'],
            'closing_time' => ['nullable', 'date_format:H:i', 'after:opening_time'],
            'status' => ['nullable', Rule::enum(WorkspaceStatus::class)],
            'amenities' => ['sometimes', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
            'images' => ['sometimes', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }
}
