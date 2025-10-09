<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Workspace::class, 'workspace');
    }

    public function index(Request $request)
    {
        $q = $request->input('search');
        $spaces = Workspace::when($q, fn($query)=>
                        $query->where(fn($w)=>
                            $w->where('name','like',"%{$q}%")
                              ->orWhere('location','like',"%{$q}%")
                        )
                    )
                    ->with('owner:id,name')
                    ->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Workspaces/Index', compact('spaces'));
    }

    public function edit(Workspace $workspace)
    {
        return Inertia::render('Admin/Workspaces/edit', compact('workspace'));
    }

    public function update(Request $request, Workspace $workspace)
    {
        $data = $request->validate([
            'name'           => ['required','string','max:255'],
            'location'       => ['required','string','max:255'],
            'capacity'       => ['required','integer','min:1'],
            'price_per_hour' => ['required','numeric','min:0'],
        ]);
        $workspace->update($data);
        return back()->with('success','تم التحديث.');
    }

    public function destroy(Workspace $workspace)
    {
        $workspace->delete();
        return back()->with('success','تم الحذف.');
    }
}
