<?php

namespace App\Http\Requests;

use App\Enums\BookingMode;
use App\Enums\WorkspaceStatus;
use App\Enums\WorkspaceType;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Workspace|null $workspace */
        $workspace = $this->route('workspace');

        return $workspace !== null && ($this->user()?->can('update', $workspace) ?? false);
    }

    public function rules(): array
    {
        return [
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
