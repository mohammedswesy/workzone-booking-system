<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Booking;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        // يربط على BookingPolicy@create
        return $this->user()?->can('create', Booking::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'workspace_id' => ['required','exists:workspaces,id'],
            'hours'        => ['required','integer','min:1'],
            // لو عندك حقول وقت/تاريخ ضفها هنا:
             'date'       => ['nullable','date'],
             'start_time' => ['nullable','date_format:H:i'],
            'end_time'   => ['nullable','after:start_time'],
        ];
    }

    public function messages(): array
    {
        return [
            'workspace_id.required' => 'اختر مساحة عمل',
            'workspace_id.exists'   => 'المساحة غير موجودة',
            'hours.min'             => 'عدد الساعات يجب أن يكون 1 على الأقل',
        ];
    }
}
