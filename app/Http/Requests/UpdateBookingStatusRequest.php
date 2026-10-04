<?php

namespace App\Http\Requests;

use App\Enums\BookingStatus;
use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $this->user()?->can('update', $booking) ?? false;
    }

    public function rules(): array
    {
        $allowed = [BookingStatus::Confirmed, BookingStatus::Cancelled];

        if ($this->user()?->role === Role::Admin) {
            $allowed = BookingStatus::cases();
        }

        return [
            'status' => ['required', Rule::enum(BookingStatus::class)->only($allowed)],
        ];
    }
}
