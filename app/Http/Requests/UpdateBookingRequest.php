<?php

namespace App\Http\Requests;

use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // مهم: اسم باراميتر الراوت لازم يكون 'booking'
        $booking = $this->route('booking');
        // يربط على BookingPolicy@update
        return $this->user()?->can('update', $booking) ?? false;
    }

    public function rules(): array
    {
        return [
            'workspace_id' => ['required','exists:workspaces,id'],
            'hours'        => ['required','integer','min:1'],
             'date'       => ['nullable','date'],
             'start_time' => ['nullable','date_format:H:i'],
            'end_time'   => ['nullable','after:start_time'],
              'status'       => ['required', Rule::in(Booking::STATUSES)],
        ];
    }
}
