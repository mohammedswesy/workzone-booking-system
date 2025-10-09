<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Workspace;

class StoreWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // يربط على WorkspacePolicy@create
        return $this->user()?->can('create', Workspace::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name'           => ['required','string','max:255'],
            'location'       => ['required','string','max:255'],
            'capacity'       => ['required','integer','min:1'],
            'price_per_hour' => ['required','numeric','min:0'],
            'image_url'      => ['nullable','url'],
            'image'          => ['nullable','image','mimes:jpeg,png,jpg,webp','max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المساحة مطلوب',
            'location.required' => 'الموقع مطلوب',
            'capacity.min' => 'الحد الأدنى للسعة هو 1',
        ];
    }
}
