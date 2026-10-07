<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesVenueCoordinates
{
    /**
     * @return array<string, list<string|\Illuminate\Contracts\Validation\ValidationRule>>
     */
    protected function coordinateRules(): array
    {
        return [
            'lat' => [
                'nullable',
                'required_with:lng',
                'numeric',
                'between:-90,90',
                'regex:/^-?\d+(\.\d{1,7})?$/',
            ],
            'lng' => [
                'nullable',
                'required_with:lat',
                'numeric',
                'between:-180,180',
                'regex:/^-?\d+(\.\d{1,7})?$/',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function coordinateMessages(): array
    {
        return [
            'lat.required_with' => __('venues.validation.lat_required_with'),
            'lng.required_with' => __('venues.validation.lng_required_with'),
            'lat.between' => __('venues.validation.lat_between'),
            'lng.between' => __('venues.validation.lng_between'),
            'lat.numeric' => __('venues.validation.lat_numeric'),
            'lng.numeric' => __('venues.validation.lng_numeric'),
            'lat.regex' => __('venues.validation.lat_decimals'),
            'lng.regex' => __('venues.validation.lng_decimals'),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function coordinateAttributes(): array
    {
        return [
            'lat' => __('venues.latitude'),
            'lng' => __('venues.longitude'),
        ];
    }

    protected function withCoordinateValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $lat = $this->input('lat');
            $lng = $this->input('lng');

            if ($lat === null || $lat === '' || $lng === null || $lng === '') {
                return;
            }

            if (! is_numeric($lat) || ! is_numeric($lng)) {
                return;
            }

            $latN = (float) $lat;
            $lngN = (float) $lng;

            // Hint when values look swapped (lat out of ±90 but plausible as longitude).
            if (abs($latN) > 90 && abs($latN) <= 180 && abs($lngN) <= 90) {
                $v->errors()->add('lat', __('venues.validation.coords_swapped'));
            }
        });
    }
}
