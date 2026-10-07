<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    public function rules(): array
    {
        // Wall-clock values are interpreted in the display timezone in the controller.
        // Do not use after:now here — that would treat naive inputs as UTC.
        return [
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'seats' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
