<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // مهم: اسم باراميتر الراوت لازم يكون 'workspace'
        $workspace = $this->route('workspace');
        // يربط على WorkspacePolicy@update
        return $this->user()?->can('update', $workspace) ?? false;
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
}
