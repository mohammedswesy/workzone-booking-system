<?php

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class WorkspaceFilter
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
                        ->orWhere('location', 'like', "%{$keyword}%")
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
            ->when($this->request->boolean('featured'), fn (Builder $q) => $q->featured())
            ->when($this->request->filled('amenities'), function (Builder $q) {
                $ids = collect(explode(',', (string) $this->request->input('amenities')))
                    ->map(fn ($id) => (int) trim($id))
                    ->filter()
                    ->values();

                foreach ($ids as $id) {
                    $q->whereHas('amenities', fn (Builder $a) => $a->where('amenities.id', $id));
                }
            })
            ->when(
                $this->request->filled('status'),
                fn (Builder $q) => $q->where('status', $this->request->input('status')),
                fn (Builder $q) => $q->published(),
            );
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
            'status' => $this->request->input('status'),
            'per_page' => (int) $this->request->integer('per_page', 12),
        ];
    }
}
