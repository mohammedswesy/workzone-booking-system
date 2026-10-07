<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Enums\VenueStatus;
use App\Http\Requests\Concerns\ValidatesVenueCoordinates;
use App\Models\Venue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateVenueRequest extends FormRequest
{
    use ValidatesVenueCoordinates;

    public function authorize(): bool
    {
        $venue = $this->route('venue');

        return $this->user()?->can('update', $venue) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()?->isAdmin()) {
            $this->request->remove('owner_id');
        }

        $this->merge([
            'lat' => $this->blankCoord('lat') ? null : $this->input('lat'),
            'lng' => $this->blankCoord('lng') ? null : $this->input('lng'),
        ]);
    }

    public function rules(): array
    {
        $isAdmin = (bool) $this->user()?->isAdmin();

        /** @var Venue|null $venue */
        $venue = $this->route('venue');

        return [
            'owner_id' => [
                Rule::requiredIf($isAdmin),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($q) {
                    $q->where('role', Role::Owner->value)->where('is_active', true);
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/[^0-9]/',
                Rule::unique('venues', 'slug')->ignore($venue?->id),
            ],
            'description' => ['nullable', 'string', 'max:5000'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'address' => ['nullable', 'string', 'max:255'],
            ...$this->coordinateRules(),
            'timezone' => ['nullable', 'timezone'],
            'status' => ['nullable', Rule::enum(VenueStatus::class)],
            'featured' => ['sometimes', 'boolean'],
            'amenities' => ['sometimes', 'array'],
            'amenities.*' => ['integer', 'exists:amenities,id'],
        ];
    }

    public function messages(): array
    {
        return $this->coordinateMessages();
    }

    public function attributes(): array
    {
        return $this->coordinateAttributes();
    }

    public function withValidator(Validator $validator): void
    {
        $this->withCoordinateValidator($validator);
    }

    private function blankCoord(string $key): bool
    {
        $value = $this->input($key);

        return $value === null || $value === '';
    }
}
