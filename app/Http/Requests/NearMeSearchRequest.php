<?php

namespace App\Http\Requests;

use App\Services\Venues\NearMeService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates optional near-me query params for /spaces and /spaces/map.json.
 * Coordinates are never stored — request-scoped only (no session flash of coords).
 */
class NearMeSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $lat = $this->input('near_lat');
        $lng = $this->input('near_lng');

        $this->merge([
            'near_lat' => ($lat === null || $lat === '') ? null : $lat,
            'near_lng' => ($lng === null || $lng === '') ? null : $lng,
            'radius_km' => $this->normalizeRadius($this->input('radius_km')),
        ]);
    }

    public function rules(): array
    {
        return [
            'near_lat' => [
                'nullable',
                'required_with:near_lng',
                'numeric',
                'between:-90,90',
            ],
            'near_lng' => [
                'nullable',
                'required_with:near_lat',
                'numeric',
                'between:-180,180',
            ],
            'radius_km' => [
                'nullable',
                Rule::in([5, 10, 25, 50, 100]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'near_lat.required_with' => __('venues.nearMe.lat_required_with'),
            'near_lng.required_with' => __('venues.nearMe.lng_required_with'),
            'near_lat.between' => __('venues.nearMe.lat_between'),
            'near_lng.between' => __('venues.nearMe.lng_between'),
            'near_lat.numeric' => __('venues.nearMe.lat_numeric'),
            'near_lng.numeric' => __('venues.nearMe.lng_numeric'),
            'radius_km.in' => __('venues.nearMe.radius_invalid'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $lat = $this->input('near_lat');
            $lng = $this->input('near_lng');
            if ($lat === null || $lng === null) {
                return;
            }
            if (! is_numeric($lat) || ! is_numeric($lng)) {
                return;
            }
            if (abs((float) $lat) < 0.0001 && abs((float) $lng) < 0.0001) {
                $v->errors()->add('near_lat', __('venues.nearMe.zero_zero'));
            }
        });
    }

    /**
     * Do not flash near_* into the session (privacy).
     */
    protected function failedValidation(Validator $validator): void
    {
        if ($this->expectsJson() || $this->is('spaces/map.json')) {
            throw (new ValidationException($validator))
                ->errorBag($this->errorBag)
                ->status(422);
        }

        $query = collect($this->query())->except(['near_lat', 'near_lng', 'radius_km'])->all();

        throw new HttpResponseException(
            redirect()->route('spaces.index', $query)->withErrors($validator, 'default')
        );
    }

    /**
     * @return array{lat: float, lng: float, radius_km: ?int}|null
     */
    public function origin(): ?array
    {
        return app(NearMeService::class)->parseOrigin(
            $this->filled('near_lat') ? (float) $this->input('near_lat') : null,
            $this->filled('near_lng') ? (float) $this->input('near_lng') : null,
            $this->input('radius_km'),
        );
    }

    private function normalizeRadius(mixed $value): mixed
    {
        if ($value === null || $value === '' || $value === 'any') {
            return null;
        }

        return (int) $value;
    }
}
