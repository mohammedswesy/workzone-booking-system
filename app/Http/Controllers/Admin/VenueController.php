<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Venues\SyncVenueOwner;
use App\Enums\Role;
use App\Enums\VenueStatus;
use App\Enums\WorkspaceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\StoreVenueRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Http\Requests\UpdateVenueRequest;
use App\Models\Amenity;
use App\Models\Location;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueImage;
use App\Models\Workspace;
use App\Services\Venues\VenueGalleryService;
use App\Services\Venues\VenueLocationService;
use App\Services\Workspaces\WorkspaceGalleryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class VenueController extends Controller
{
    public function __construct(
        private readonly WorkspaceGalleryService $gallery,
        private readonly SyncVenueOwner $syncOwner,
        private readonly VenueLocationService $location,
        private readonly VenueGalleryService $venueGallery,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Venue::class);

        $q = $request->string('search')->toString();
        $withoutCoords = $request->boolean('without_coordinates');

        $venues = Venue::query()
            ->withCount('units')
            ->with(['owner:id,name,email', 'place:id,name,city,lat,lng'])
            ->when($q !== '', fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('address', 'like', "%{$q}%")))
            ->when($withoutCoords, fn ($query) => $query->withoutCoordinates())
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Venue $v) => array_merge($v->toArray(), [
                'has_coordinates' => $this->location->hasCoordinates($v),
            ]));

        $withoutCoordinatesCount = Venue::query()->withoutCoordinates()->count();

        return Inertia::render('Admin/Venues/Index', [
            'venues' => $venues,
            'filters' => [
                'search' => $q,
                'without_coordinates' => $withoutCoords,
            ],
            'without_coordinates_count' => $withoutCoordinatesCount,
        ]);
    }

    public function create()
    {
        $this->authorize('create', Venue::class);

        return Inertia::render('Admin/Venues/Create', $this->formProps());
    }

    public function store(StoreVenueRequest $request)
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? VenueStatus::Draft->value;
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
            ->route('admin.venues.show', $venue)
            ->with('success', 'Venue created. Add units, then publish.');
    }

    public function show(Venue $venue)
    {
        $this->authorize('update', $venue);
        $venue->load([
            'owner:id,name,email',
            'place:id,name,city,lat,lng',
            'amenities:id,name',
            'units' => fn ($q) => $q->latest(),
        ]);

        $publishedUnits = $venue->units->where('status', WorkspaceStatus::Published)->count();

        return Inertia::render('Admin/Venues/Show', [
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

        // Owner change with bookings/offers requires explicit transfer endpoint.
        unset($data['owner_id']);

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

        return redirect()->route('admin.venues.show', $venue)->with('success', 'Venue updated.');
    }

    public function transferOwner(Request $request, Venue $venue, SyncVenueOwner $sync)
    {
        $this->authorize('update', $venue);

        $validated = $request->validate([
            'owner_id' => ['required', 'integer', 'exists:users,id'],
            'confirmed' => ['accepted'],
        ]);

        $newOwner = User::query()->findOrFail($validated['owner_id']);
        $sync->transfer($venue, $newOwner, $request->user(), true);

        return back()->with('success', 'Venue owner transferred. Ledger history unchanged.');
    }

    public function destroy(Venue $venue)
    {
        $this->authorize('delete', $venue);

        if ($venue->units()->whereHas('bookings')->exists()) {
            $venue->update(['status' => VenueStatus::Archived]);

            return back()->with('success', 'Venue archived because units have bookings.');
        }

        foreach ($venue->units as $unit) {
            foreach ($unit->images as $image) {
                $this->gallery->delete($image);
            }
            $unit->delete();
        }
        $venue->amenities()->detach();
        $venue->images()->delete();
        $venue->delete();

        return redirect()->route('admin.venues.index')->with('success', 'Venue deleted.');
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

        return Inertia::render('Admin/Venues/Units/Create', [
            'venue' => $venue->only(['id', 'name', 'slug', 'owner_id']),
            'amenities' => Amenity::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function storeUnit(StoreUnitRequest $request, Venue $venue)
    {
        $data = collect($request->validated())->except(['amenities', 'images'])->all();
        $data['status'] = $data['status'] ?? WorkspaceStatus::Published->value;
        $data['opening_time'] = strlen((string) ($data['opening_time'] ?? '08:00')) === 5
            ? $data['opening_time'].':00'
            : ($data['opening_time'] ?? '08:00:00');
        $data['closing_time'] = strlen((string) ($data['closing_time'] ?? '22:00')) === 5
            ? $data['closing_time'].':00'
            : ($data['closing_time'] ?? '22:00:00');
        $data['location'] = $venue->address ?: $venue->name;
        $data['location_id'] = $venue->location_id;

        $unit = Workspace::query()->create($data);
        if ($request->filled('amenities')) {
            $unit->amenities()->sync($request->input('amenities', []));
        }
        foreach ($request->file('images', []) as $i => $file) {
            $this->gallery->add($unit, $file, primary: $i === 0);
        }

        return redirect()->route('admin.venues.show', $venue)->with('success', 'Unit added.');
    }

    public function editUnit(Venue $venue, Workspace $workspace)
    {
        $this->authorize('update', $workspace);
        abort_unless($workspace->venue_id === $venue->id, 404);
        $workspace->load(['images' => fn ($q) => $q->orderBy('sort_order'), 'amenities:id,name']);

        return Inertia::render('Admin/Venues/Units/Edit', [
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

        return redirect()->route('admin.venues.show', $venue)->with('success', 'Unit updated.');
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

            return back()->with('success', 'Unit archived.');
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
            'owners' => User::query()
                ->where('role', Role::Owner)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ];
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
