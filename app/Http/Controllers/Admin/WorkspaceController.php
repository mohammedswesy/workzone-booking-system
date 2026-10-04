<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Enums\WorkspaceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;
use App\Models\Amenity;
use App\Models\Location;
use App\Models\User;
use App\Models\Workspace;
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
        $q = $request->input('search');
        $spaces = Workspace::when($q, fn ($query) => $query->where(fn ($w) => $w->where('name', 'like', "%{$q}%")
            ->orWhere('location', 'like', "%{$q}%")
            ->orWhere('description', 'like', "%{$q}%")
        ))
            ->with(['owner:id,name', 'place:id,name,city', 'amenities:id,name'])
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Workspaces/Index', [
            'spaces' => $spaces,
            'filters' => ['search' => $q],
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Workspaces/Create', $this->formProps());
    }

    public function store(StoreWorkspaceRequest $request)
    {
        $data = collect($request->validated())->except(['image', 'images', 'amenities'])->all();
        $data['status'] = $data['status'] ?? WorkspaceStatus::Published->value;

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

        return redirect()
            ->route('admin.workspaces.index')
            ->with('success', 'Workspace created for the selected owner.');
    }

    public function edit(Workspace $workspace)
    {
        $workspace->load([
            'images' => fn ($q) => $q->orderBy('sort_order'),
            'amenities:id,name',
            'place:id,name,city',
            'owner:id,name,email',
        ]);

        return Inertia::render('Admin/Workspaces/Edit', array_merge($this->formProps(), [
            'workspace' => $workspace,
            'needsPaymentSetup' => $workspace->hasPlaceholderPaymentInstructions(),
            'bookingModeLocked' => $workspace->hasActiveFutureBookings(),
            'hasBookings' => $workspace->bookings()->exists(),
        ]));
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

        return redirect()
            ->route('admin.workspaces.index')
            ->with('success', 'Workspace updated.');
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

    /**
     * @return array<string, mixed>
     */
    private function formProps(): array
    {
        return [
            'locations' => Location::query()->orderBy('name')->get(['id', 'name', 'city']),
            'amenities' => Amenity::query()->orderBy('name')->get(['id', 'name', 'slug']),
            'owners' => User::query()
                ->where('role', Role::Owner)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ];
    }
}
