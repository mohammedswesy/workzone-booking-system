<?php

namespace App\Http\Controllers\Owner;

use App\Enums\VenueStatus;
use App\Enums\WorkspaceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\StoreVenueRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Http\Requests\UpdateVenueRequest;
use App\Models\Amenity;
use App\Models\Location;
use App\Models\Venue;
use App\Models\VenueImage;
use App\Models\Workspace;
use App\Services\Venues\VenueGalleryService;
use App\Services\Venues\VenueLocationService;
use App\Services\Workspaces\WorkspaceGalleryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Owner venue (building) + unit (workspace) management.
 */
class VenueController extends Controller
{
    public function __construct(
        private readonly WorkspaceGalleryService $gallery,
        private readonly VenueLocationService $location,
        private readonly VenueGalleryService $venueGallery,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Venue::class);

        $q = $request->string('search')->toString();
        $venues = Venue::query()
            ->where('owner_id', $request->user()->id)
            ->withCount(['units'])
            ->with([
                'place:id,name,city,lat,lng',
                'images' => fn ($img) => $img->orderBy('sort_order')->limit(1),
            ])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('address', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Venue $v) => array_merge($v->toArray(), [
                'has_coordinates' => $this->location->hasCoordinates($v),
            ]));

        $missingCoordsCount = Venue::query()
            ->where('owner_id', $request->user()->id)
            ->withoutCoordinates()
            ->count();

        return Inertia::render('Owner/Venues/Index', [
            'venues' => $venues,
            'filters' => ['search' => $q],
            'missing_coordinates_count' => $missingCoordsCount,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Venue::class);

        return Inertia::render('Owner/Venues/Create', $this->formProps());
    }

    public function store(StoreVenueRequest $request)
    {
        $data = $request->validated();
        $data['owner_id'] = $request->user()->id;
        $data['status'] = $data['status'] ?? VenueStatus::Draft->value;

        // Never publish on create without units — force draft if published requested.
        if (($data['status'] ?? null) === VenueStatus::Published->value) {
            $data['status'] = VenueStatus::Draft->value;
        }

        $venue = Venue::query()->create(collect($data)->except(['amenities', 'lat', 'lng'])->all());

        if ($request->filled('amenities')) {
            $venue->amenities()->sync($request->input('amenities', []));
        }

        $this->location->syncCoordinates($venue, $request->input('lat'), $request->input('lng'));
        $this->seedDefaultHours($venue);

        return redirect()
            ->route('owner.venues.show', $venue)
            ->with('success', __('Venue created. Add at least one room or desk, then publish.'));
    }

    public function show(Venue $venue)
    {
        $this->authorize('update', $venue);

        $venue->load([
            'place:id,name,city,lat,lng',
            'amenities:id,name',
            'images' => fn ($q) => $q->orderBy('sort_order'),
            'units' => fn ($q) => $q->with(['images' => fn ($i) => $i->orderBy('sort_order')->limit(1)])->latest(),
        ]);

        $publishedUnits = $venue->units->where('status', WorkspaceStatus::Published)->count();

        return Inertia::render('Owner/Venues/Show', [
            'venue' => $venue,
            'checklist' => $this->location->checklist($venue, $publishedUnits),
            'hours_config_needed' => app(\App\Services\Availability\AvailabilityService::class)->venueNeedsHoursConfig($venue),
            ...$this->formProps(),
        ]);
    }

    public function edit(Venue $venue)
    {
        return $this->show($venue);
    }

    public function update(UpdateVenueRequest $request, Venue $venue)
    {
        $data = collect($request->validated())->except(['amenities', 'lat', 'lng'])->all();
        $wasPublished = $venue->status === VenueStatus::Published;
        $oldLocation = $this->location->snapshot($venue);

        $this->location->assertPublishAllowed(
            $venue,
            $data['status'] ?? $venue->status,
            $request->input('lat'),
            $request->input('lng'),
            $wasPublished,
        );

        $venue->update($data);

        if ($request->has('amenities')) {
            $venue->amenities()->sync($request->input('amenities', []));
        }

        $this->location->syncCoordinates($venue, $request->input('lat'), $request->input('lng'));
        $venue->refresh()->load('place');
        $this->location->auditIfChanged(
            $venue,
            $oldLocation,
            $this->location->snapshot($venue),
            $request->user(),
        );

        return redirect()
            ->route('owner.venues.show', $venue)
            ->with('success', 'Venue updated.');
    }

    public function destroy(Venue $venue)
    {
        $this->authorize('delete', $venue);

        if ($venue->units()->whereHas('bookings')->exists()) {
            $venue->update(['status' => VenueStatus::Archived]);

            return back()->with('success', 'Venue archived because units have bookings.');
        }

        foreach ($venue->units as $unit) {
            if ($unit->bookings()->exists()) {
                $unit->update(['status' => WorkspaceStatus::Archived]);
            } else {
                foreach ($unit->images as $image) {
                    $this->gallery->delete($image);
                }
                $unit->delete();
            }
        }

        $venue->refresh();
        if ($venue->units()->exists()) {
            $venue->update(['status' => VenueStatus::Archived]);

            return back()->with('success', 'Venue archived.');
        }

        $venue->amenities()->detach();
        $venue->images()->delete();
        $venue->delete();

        return redirect()->route('owner.venues.index')->with('success', 'Venue deleted.');
    }

    public function storeImages(Request $request, Venue $venue)
    {
        $this->authorize('update', $venue);
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:12'],
            'images.*' => ['file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        foreach ($request->file('images', []) as $index => $file) {
            $this->venueGallery->add($venue, $file, primary: $index === 0 && ! $venue->images()->exists());
        }

        return back()->with('success', __('Gallery updated.'));
    }

    public function setPrimaryImage(Venue $venue, VenueImage $image)
    {
        $this->authorize('update', $venue);
        abort_unless($image->venue_id === $venue->id, 404);
        $this->venueGallery->setPrimary($image);

        return back()->with('success', __('Primary image updated.'));
    }

    public function reorderImages(Request $request, Venue $venue)
    {
        $this->authorize('update', $venue);
        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer'],
        ]);
        $this->venueGallery->reorder($venue, $data['order']);

        return back()->with('success', __('Gallery order saved.'));
    }

    public function updateImageCaption(Request $request, Venue $venue, VenueImage $image)
    {
        $this->authorize('update', $venue);
        abort_unless($image->venue_id === $venue->id, 404);
        $data = $request->validate([
            'caption' => ['nullable', 'string', 'max:255'],
        ]);
        $this->venueGallery->updateCaption($image, $data['caption'] ?? null);

        return back()->with('success', __('Caption saved.'));
    }

    public function destroyImage(Venue $venue, VenueImage $image)
    {
        $this->authorize('update', $venue);
        abort_unless($image->venue_id === $venue->id, 404);
        $this->venueGallery->delete($image);

        return back()->with('success', __('Image removed.'));
    }

    public function createUnit(Venue $venue)
    {
        $this->authorize('update', $venue);

        return Inertia::render('Owner/Venues/Units/Create', [
            'venue' => $venue->only(['id', 'name', 'slug']),
            'amenities' => Amenity::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function storeUnit(StoreUnitRequest $request, Venue $venue)
    {
        $data = collect($request->validated())->except(['amenities', 'images'])->all();
        $data['status'] = $data['status'] ?? WorkspaceStatus::Published->value;
        $data['opening_time'] = $this->normalizeTime($data['opening_time'] ?? '08:00');
        $data['closing_time'] = $this->normalizeTime($data['closing_time'] ?? '22:00');
        $data['location'] = $venue->address ?: $venue->name;
        $data['location_id'] = $venue->location_id;
        $data['description'] = $data['description'] ?? null;

        $unit = Workspace::query()->create($data);

        if ($request->filled('amenities')) {
            $unit->amenities()->sync($request->input('amenities', []));
        }

        foreach ($request->file('images', []) as $index => $file) {
            $this->gallery->add($unit, $file, primary: $index === 0);
        }

        return redirect()
            ->route('owner.venues.show', $venue)
            ->with('success', 'Room / desk added.');
    }

    public function editUnit(Venue $venue, Workspace $workspace)
    {
        $this->authorize('update', $workspace);
        abort_unless($workspace->venue_id === $venue->id, 404);

        $workspace->load([
            'images' => fn ($q) => $q->orderBy('sort_order'),
            'amenities:id,name',
        ]);

        return Inertia::render('Owner/Venues/Units/Edit', [
            'venue' => $venue->only(['id', 'name', 'slug']),
            'workspace' => $workspace,
            'bookingModeLocked' => $workspace->hasActiveFutureBookings(),
            'amenities' => Amenity::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function updateUnit(UpdateUnitRequest $request, Venue $venue, Workspace $workspace)
    {
        abort_unless($workspace->venue_id === $venue->id, 404);

        $data = collect($request->validated())->except(['amenities', 'images'])->all();
        if (isset($data['opening_time'])) {
            $data['opening_time'] = $this->normalizeTime($data['opening_time']);
        }
        if (isset($data['closing_time'])) {
            $data['closing_time'] = $this->normalizeTime($data['closing_time']);
        }

        if (($data['status'] ?? null) === WorkspaceStatus::Archived->value
            && $workspace->hasFuturePendingOrConfirmedBookings()) {
            throw ValidationException::withMessages([
                'status' => 'Cannot archive a unit with future pending or confirmed bookings.',
            ]);
        }

        $workspace->update($data);

        if ($request->has('amenities')) {
            $workspace->amenities()->sync($request->input('amenities', []));
        }

        foreach ($request->file('images', []) as $file) {
            $this->gallery->add($workspace, $file);
        }

        return redirect()
            ->route('owner.venues.show', $venue)
            ->with('success', 'Unit updated.');
    }

    public function destroyUnit(Venue $venue, Workspace $workspace)
    {
        $this->authorize('delete', $workspace);
        abort_unless($workspace->venue_id === $venue->id, 404);

        if ($workspace->hasFuturePendingOrConfirmedBookings()) {
            return back()->with('error', 'Cannot archive a unit with future pending or confirmed bookings.');
        }

        if ($workspace->bookings()->exists()) {
            $workspace->update(['status' => WorkspaceStatus::Archived]);

            return back()->with('success', 'Unit archived because it has bookings.');
        }

        foreach ($workspace->images as $image) {
            $this->gallery->delete($image);
        }
        $workspace->delete();

        return back()->with('success', 'Unit deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'locations' => Location::query()->orderBy('name')->get(['id', 'name', 'city']),
            'amenities' => Amenity::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'cityCenters' => $this->location->cityCenters(),
        ];
    }

    private function normalizeTime(?string $time): string
    {
        if ($time === null || $time === '') {
            return '08:00:00';
        }

        return strlen($time) === 5 ? $time.':00' : $time;
    }

    private function seedDefaultHours(Venue $venue): void
    {
        if ($venue->hours()->exists()) {
            return;
        }

        for ($d = 0; $d <= 6; $d++) {
            $venue->hours()->create([
                'weekday' => $d,
                'opens_at' => '08:00:00',
                'closes_at' => '22:00:00',
                'is_closed' => false,
            ]);
        }
    }
}
