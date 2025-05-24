<?php

namespace App\Http\Controllers;

use App\Models\Workspace;
use Illuminate\Http\Request;
use App\Models\Booking;
use Illuminate\Support\Facades\Auth;
class WorkspaceController extends Controller
{
    //
    public function index()
{
    $workspaces = Workspace::all();
    return view('workspaces.index', compact('workspaces'));
}


public function create()
{
    return view('workspaces.create');
}


public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'location' => 'nullable|string|max:255',
        'capacity' => 'nullable|integer|min:1',
         'price_per_hour' => 'required|numeric|min:0',
        // أضف باقي الحقول حسب الجدول
    ]);

    Workspace::create($request->all());

    return redirect()->route('workspaces.index')->with('success', 'تم إضافة مكان العمل بنجاح.');
}


public function show($id)
{
    $workspace = Workspace::findOrFail($id);
    return view('workspaces.show', compact('workspace'));
}



public function edit($id)
{
    $workspace = Workspace::findOrFail($id);
    return view('workspaces.edit', compact('workspace'));
}



public function update(Request $request, $id)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'location' => 'nullable|string|max:255',
        'capacity' => 'nullable|integer|min:1',
         'price_per_hour' => 'required|numeric|min:0',
        // أضف باقي الحقول حسب الجدول
    ]);

    $workspace = Workspace::findOrFail($id);
    $workspace->update($request->all());

    return redirect()->route('workspaces.index')->with('success', 'تم تحديث مكان العمل بنجاح.');
}


public function destroy($id)
{
    $workspace = Workspace::findOrFail($id);
    $workspace->delete();

    return redirect()->route('workspaces.index')->with('success', 'تم حذف مكان العمل بنجاح.');
}





public function book(Request $request, $id)
{
    $workspace = Workspace::findOrFail($id);

    $request->validate([
        'hours' => 'required|integer|min:1',
    ]);

    $total_price = $workspace->price_per_hour * $request->hours;

    // تسجيل الحجز مع حالة pending
    $booking = Booking::create([
        'user_id' => Auth::id(),
        'workspace_id' => $workspace->id,
        'hours' => $request->hours,
        'total_price' => $total_price,
        'status' => 'pending',
    ]);

    return redirect()->route('workspaces.show', $workspace->id)->with('success', 'تم تسجيل الحجز، الرجاء إتمام الدفع.');
}


}
