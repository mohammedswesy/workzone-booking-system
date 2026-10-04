<?php

namespace App\Http\Controllers\Owner;

use App\Enums\BookingMode;
use App\Enums\WorkspaceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Models\Amenity;
use App\Models\Location;
use App\Models\Workspace;
use App\Models\WorkspaceImage;
use App\Services\Workspaces\WorkspaceGalleryService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceGalleryService $gallery,
    ) {
        $this->authorizeResource(Workspace::class, 'workspace');
    }

    public function index(Request $request)
    {
        $owner = $request->user();
        $q = $request->input('search');
        $perPage = (int) ($request->input('per_page') ?? 9);

        $spaces = Workspace::query()
            ->where('owner_id', $owner->id)
            ->with([
                'place:id,name,city',
                'images' => fn ($img) => $img->orderBy('sort_order')->limit(1),
                'amenities:id,name',
            ])
            ->when($q, fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
                ->orWhere('location', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
            ))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Owner/Workspaces/Index', [
            'spaces' => $spaces,
            'filters' => ['search' => $q, 'per_page' => $perPage],
        ]);
    }

    public function create()
    {
        return Inertia::render('Owner/Workspaces/Create', [
            'locations' => Location::query()->orderBy('name')->get(['id', 'name', 'city']),
            'amenities' => Amenity::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(StoreWorkspaceRequest $request)
    {
        $data = collect($request->validated())->except(['image', 'images', 'amenities', 'owner_id'])->all();
        $data['owner_id'] = $request->user()->id;
        $data['status'] = $data['status'] ?? WorkspaceStatus::Published->value;
        $data['booking_mode'] = $data['booking_mode'] ?? BookingMode::Seat->value;

        $workspace = Workspace::create($data);

        if ($request->filled('amenities')) {
            $workspace->amenities()->sync($request->input('amenities', []));
        }

        if ($request->hasFile('image')) {
            $this->gallery->add($workspace, $request->file('image'), primary: true);
        }

        foreach ($request->file('images', []) as $index => $file) {
            $this->gallery->add($workspace, $file, primary: ! $request->hasFile('image') && $index === 0);
        }

        return redirect()->route('owner.workspaces.index')->with('success', 'Workspace created.');
    }

    public function edit(Workspace $workspace)
    {
        $workspace->load([
            'images' => fn ($q) => $q->orderBy('sort_order'),
            'amenities:id,name',
            'place:id,name,city',
        ]);

        return Inertia::render('Owner/Workspaces/Edit', [
            'workspace' => $workspace,
            'needsPaymentSetup' => $workspace->hasPlaceholderPaymentInstructions(),
            'bookingModeLocked' => $workspace->hasActiveFutureBookings(),
            'locations' => Location::query()->orderBy('name')->get(['id', 'name', 'city']),
            'amenities' => Amenity::query()->orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace)
    {
        $data = collect($request->validated())->except(['image', 'images', 'amenities', 'owner_id'])->all();
        $workspace->update($data);

        if ($request->has('amenities')) {
            $workspace->amenities()->sync($request->input('amenities', []));
        }

        if ($request->hasFile('image')) {
            $this->gallery->add($workspace, $request->file('image'), primary: true);
        }

        foreach ($request->file('images', []) as $file) {
            $this->gallery->add($workspace, $file);
        }

        return redirect()->route('owner.workspaces.index')->with('success', 'Workspace updated.');
    }

    public function destroy(Workspace $workspace)
    {
        if ($workspace->bookings()->exists()) {
            $workspace->update(['status' => WorkspaceStatus::Archived]);

            return back()->with('success', 'Workspace archived because it has bookings.');
        }

        foreach ($workspace->images as $image) {
            $this->gallery->delete($image);
        }

        $workspace->delete();

        return back()->with('success', 'Workspace deleted.');
    }

    public function setPrimaryImage(Workspace $workspace, WorkspaceImage $image)
    {
        $this->authorize('update', $workspace);
        abort_unless($image->workspace_id === $workspace->id, 404);

        $this->gallery->setPrimary($image);

        return back()->with('success', 'Primary image updated.');
    }

    public function reorderImages(Request $request, Workspace $workspace)
    {
        $this->authorize('update', $workspace);

        $data = $request->validate([
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['integer', 'exists:workspace_images,id'],
        ]);

        $ownedIds = $workspace->images()->pluck('id')->all();
        foreach ($data['order'] as $id) {
            abort_unless(in_array((int) $id, $ownedIds, true), 422);
        }

        $this->gallery->reorder($workspace, $data['order']);

        return back()->with('success', 'Gallery order updated.');
    }

    public function destroyImage(Workspace $workspace, WorkspaceImage $image)
    {
        $this->authorize('update', $workspace);
        abort_unless($image->workspace_id === $workspace->id, 404);

        $this->gallery->delete($image);

        return back()->with('success', 'Image removed.');
    }
}
