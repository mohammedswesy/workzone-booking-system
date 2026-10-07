<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Venues\CreateVenueWithUnit;
use App\Enums\WorkspaceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Models\Workspace;
use App\Services\Workspaces\WorkspaceGalleryService;
use Illuminate\Http\Request;

/**
 * Legacy admin workspace routes.
 * GET list/create redirect to venues; store/update still create/update units.
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
        return redirect()->route('admin.venues.index');
    }

    public function create()
    {
        return redirect()->route('admin.venues.create');
    }

    public function store(StoreWorkspaceRequest $request)
    {
        $data = collect($request->validated())->except(['image', 'images', 'amenities'])->all();
        $data['status'] = $data['status'] ?? WorkspaceStatus::Published->value;

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
            ->route('admin.venues.show', $workspace->venue)
            ->with('success', 'Venue and unit created for the selected owner.');
    }

    public function edit(Workspace $workspace)
    {
        $workspace->loadMissing('venue');
        if ($workspace->venue) {
            return redirect()->route('admin.venues.units.edit', [$workspace->venue, $workspace]);
        }

        return redirect()->route('admin.venues.index');
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace)
    {
        $data = collect($request->validated())->except(['image', 'images', 'amenities'])->all();
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
                $workspace->venue ? 'admin.venues.show' : 'admin.venues.index',
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

            return back()->with('success', 'Unit archived because it has bookings.');
        }

        foreach ($workspace->images as $image) {
            $this->gallery->delete($image);
        }
        $workspace->delete();

        return back()->with('success', 'Unit deleted.');
    }
}
