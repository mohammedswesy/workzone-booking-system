<?php

namespace App\Http\Controllers\Owner;

use App\Actions\Venues\CreateVenueWithUnit;
use App\Enums\BookingMode;
use App\Enums\WorkspaceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Models\Workspace;
use App\Models\WorkspaceImage;
use App\Services\Workspaces\WorkspaceGalleryService;
use Illuminate\Http\Request;

/**
 * Legacy owner workspace routes.
 * GET list/create redirect to venues; store/update still work for unit CRUD + tests.
 */
class WorkspaceController extends Controller
{
    public function __construct(
        private readonly WorkspaceGalleryService $gallery,
        private readonly CreateVenueWithUnit $createVenueWithUnit,
    ) {
        $this->authorizeResource(Workspace::class, 'workspace');
    }

    public function index(Request $request)
    {
        return redirect()->route('owner.venues.index');
    }

    public function create()
    {
        return redirect()->route('owner.venues.create');
    }

    public function store(StoreWorkspaceRequest $request)
    {
        $data = collect($request->validated())->except(['image', 'images', 'amenities', 'owner_id'])->all();
        $data['owner_id'] = $request->user()->id;
        $data['status'] = $data['status'] ?? WorkspaceStatus::Published->value;
        $data['booking_mode'] = $data['booking_mode'] ?? BookingMode::Seat->value;

        $workspace = $this->createVenueWithUnit->handle($data);

        if ($request->filled('amenities')) {
            $workspace->amenities()->sync($request->input('amenities', []));
        }

        if ($request->hasFile('image')) {
            $this->gallery->add($workspace, $request->file('image'), primary: true);
        }

        foreach ($request->file('images', []) as $index => $file) {
            $this->gallery->add($workspace, $file, primary: ! $request->hasFile('image') && $index === 0);
        }

        return redirect()
            ->route('owner.venues.show', $workspace->venue)
            ->with('success', 'Venue and unit created.');
    }

    public function edit(Workspace $workspace)
    {
        $workspace->loadMissing('venue');
        if ($workspace->venue) {
            return redirect()->route('owner.venues.units.edit', [$workspace->venue, $workspace]);
        }

        return redirect()->route('owner.venues.index');
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

        $workspace->loadMissing('venue');

        return redirect()
            ->route(
                $workspace->venue
                    ? 'owner.venues.show'
                    : 'owner.venues.index',
                $workspace->venue ?: []
            )
            ->with('success', 'Unit updated.');
    }

    public function destroy(Workspace $workspace)
    {
        if ($workspace->hasFuturePendingOrConfirmedBookings()) {
            return back()->with('error', 'Cannot archive a unit with future pending or confirmed bookings.');
        }

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
