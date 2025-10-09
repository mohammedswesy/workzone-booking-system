<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use App\Http\Requests\StoreWorkspaceRequest;
use App\Http\Requests\UpdateWorkspaceRequest;

class WorkspaceController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Workspace::class, 'workspace');
    }

    public function index(Request $request)
    {
        $owner = $request->user();
        $q = $request->input('search');
        $perPage = (int) ($request->input('per_page') ?? 9);

        $spaces = Workspace::query()
            ->where('owner_id', $owner->id)
            ->when($q, fn($query) =>
                $query->where(fn($w)=>
                    $w->where('name','like',"%{$q}%")
                      ->orWhere('location','like',"%{$q}%")
                )
            )
            ->select('id','name','location','capacity','price_per_hour','image_url','created_at')
            ->latest()->paginate($perPage)->withQueryString();

        return Inertia::render('Owner/Workspaces/Index', [
            'spaces'=>$spaces,
            'filters'=>['search'=>$q,'per_page'=>$perPage],
        ]);
    }

    public function create()
    {
        return Inertia::render('Owner/Workspaces/Create');
    }

    public function store(StoreWorkspaceRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('workspaces','public');
            $data['image_url'] = Storage::url($path);
        }

        $data['owner_id'] = $request->user()->id;
        Workspace::create($data);

        return redirect()->route('owner.workspaces.index')->with('success','Workspace created ✅');
    }

    public function edit(Workspace $workspace)
    {
        return Inertia::render('Owner/Workspaces/Edit', [
            'workspace' => $workspace->only('id','name','location','capacity','price_per_hour','image_url'),
        ]);
    }

    public function update(UpdateWorkspaceRequest $request, Workspace $workspace)
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($workspace->image_url && str_starts_with($workspace->image_url, '/storage/')) {
                $relative = str_replace('/storage/', '', $workspace->image_url);
                Storage::disk('public')->delete($relative);
            }
            $path = $request->file('image')->store('workspaces','public');
            $data['image_url'] = Storage::url($path);
        }

        $workspace->update($data);

        return redirect()->route('owner.workspaces.index')->with('success','Workspace updated ✅');
    }

    public function destroy(Workspace $workspace)
    {
        if ($workspace->image_url && str_starts_with($workspace->image_url, '/storage/')) {
            $relative = str_replace('/storage/','',$workspace->image_url);
            Storage::disk('public')->delete($relative);
        }
        $workspace->delete();
        return back()->with('success','Workspace deleted 🗑️');
    }
}
