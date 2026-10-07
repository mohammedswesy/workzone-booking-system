<?php

namespace App\Filters;

use App\Enums\WorkspaceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class VenueFilter
{
    public function __construct(
        private readonly Request $request,
    ) {}

    public function apply(Builder $query): Builder
    {
        $keyword = $this->request->string('search')->toString()
            ?: $this->request->string('q')->toString();

        return $query
            ->when($keyword !== '', function (Builder $q) use ($keyword) {
                $q->where(function (Builder $inner) use ($keyword) {
                    $inner->where('name', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%")
                        ->orWhere('address', 'like', "%{$keyword}%")
                        ->orWhereHas('place', fn (Builder $l) => $l
                            ->where('name', 'like', "%{$keyword}%")
                            ->orWhere('city', 'like', "%{$keyword}%")
                            ->orWhere('address', 'like', "%{$keyword}%"));
                });
            })
            ->when($this->request->filled('location_id'), fn (Builder $q) => $q->where(
                'location_id',
                $this->request->integer('location_id')
            ))
            ->when($this->request->filled('city'), function (Builder $q) {
                $city = $this->request->string('city')->toString();
                $q->whereHas('place', fn (Builder $l) => $l->where('city', 'like', "%{$city}%"));
            })
            ->when($this->request->boolean('featured'), fn (Builder $q) => $q->featured())
            ->when($this->request->filled('amenities'), function (Builder $q) {
                $ids = collect(explode(',', (string) $this->request->input('amenities')))
                    ->map(fn ($id) => (int) trim($id))
                    ->filter()
                    ->values();

                foreach ($ids as $id) {
                    $q->where(function (Builder $outer) use ($id) {
                        $outer->whereHas('amenities', fn (Builder $a) => $a->where('amenities.id', $id))
                            ->orWhereHas('units', fn (Builder $u) => $u
                                ->where('status', WorkspaceStatus::Published)
                                ->whereHas('amenities', fn (Builder $a) => $a->where('amenities.id', $id)));
                    });
                }
            })
            ->when(
                $this->request->filled('status'),
                fn (Builder $q) => $q->where('status', $this->request->input('status')),
                fn (Builder $q) => $q->published(),
            )
            ->whereHas('units', function (Builder $units) {
                $units->where('status', WorkspaceStatus::Published)
                    ->when($this->request->filled('min_price'), fn (Builder $q) => $q->where(
                        'price_per_hour',
                        '>=',
                        $this->request->input('min_price')
                    ))
                    ->when($this->request->filled('max_price'), fn (Builder $q) => $q->where(
                        'price_per_hour',
                        '<=',
                        $this->request->input('max_price')
                    ))
                    ->when($this->request->filled('capacity'), fn (Builder $q) => $q->where(
                        'capacity',
                        '>=',
                        $this->request->integer('capacity')
                    ))
                    ->when($this->request->filled('type'), fn (Builder $q) => $q->where(
                        'type',
                        $this->request->input('type')
                    ));
            });
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return [
            'search' => $this->request->string('search')->toString() ?: $this->request->string('q')->toString(),
            'location_id' => $this->request->input('location_id'),
            'city' => $this->request->string('city')->toString(),
            'min_price' => $this->request->input('min_price'),
            'max_price' => $this->request->input('max_price'),
            'capacity' => $this->request->input('capacity'),
            'amenities' => $this->request->input('amenities'),
            'featured' => $this->request->boolean('featured'),
            'type' => $this->request->input('type'),
            'open_now' => $this->request->boolean('open_now'),
            'status' => $this->request->input('status'),
            'per_page' => (int) $this->request->integer('per_page', 12),
            'near_lat' => $this->request->filled('near_lat') ? $this->request->input('near_lat') : null,
            'near_lng' => $this->request->filled('near_lng') ? $this->request->input('near_lng') : null,
            'radius_km' => $this->request->filled('radius_km') && $this->request->input('radius_km') !== 'any'
                ? $this->request->input('radius_km')
                : null,
        ];
    }

    public function wantsOpenNow(): bool
    {
        return $this->request->boolean('open_now');
    }
}
