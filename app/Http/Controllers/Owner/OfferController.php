<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class OfferController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Offer::class, 'offer');
    }

    public function index(Request $request)
    {
        $ownerId = Auth::id();
        $perPage = (int) $request->integer('per_page', 12);
        $search = $request->string('search')->toString();

        $offers = Offer::query()
            ->with(['workspace:id,name'])
            ->where('owner_id', $ownerId)
            ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Owner/Offers/Index', [
            'offers' => $offers,
            'filters' => ['search' => $search, 'per_page' => $perPage],
        ]);
    }

    public function create()
    {
        $workspaces = Workspace::where('owner_id', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Owner/Offers/Create', [
            'workspaces' => $workspaces,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'title' => ['required', 'string', 'max:255'],
            'discount_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless(
            Workspace::where('id', $data['workspace_id'])->where('owner_id', Auth::id())->exists(),
            403
        );

        $offer = new Offer([
            'owner_id' => Auth::id(),
            'workspace_id' => $data['workspace_id'],
            'title' => $data['title'],
            'discount_percent' => $data['discount_percent'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        if ($offer->is_active && $offer->overlapsAnother()) {
            throw ValidationException::withMessages([
                'starts_at' => 'An active offer already overlaps this period for the workspace.',
            ]);
        }

        $offer->save();

        return redirect()->route('owner.offers.index')
            ->with('success', 'تم إنشاء العرض بنجاح.');
    }

    public function edit(Offer $offer)
    {
        $workspaces = Workspace::where('owner_id', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Owner/Offers/Edit', [
            'offer' => $offer->only([
                'id', 'workspace_id', 'title', 'discount_percent',
                'starts_at', 'ends_at', 'is_active',
            ]),
            'workspaces' => $workspaces,
        ]);
    }

    public function update(Request $request, Offer $offer)
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'title' => ['required', 'string', 'max:255'],
            'discount_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        abort_unless(
            Workspace::where('id', $data['workspace_id'])->where('owner_id', Auth::id())->exists(),
            403
        );

        $offer->fill([
            'owner_id' => Auth::id(),
            'workspace_id' => $data['workspace_id'],
            'title' => $data['title'],
            'discount_percent' => $data['discount_percent'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        if ($offer->is_active && $offer->overlapsAnother()) {
            throw ValidationException::withMessages([
                'starts_at' => 'An active offer already overlaps this period for the workspace.',
            ]);
        }

        $offer->save();

        return redirect()->route('owner.offers.index')
            ->with('success', 'تم التحديث.');
    }

    public function destroy(Offer $offer)
    {
        $offer->delete();

        return redirect()->route('owner.offers.index')
            ->with('success', 'تم الحذف.');
    }
}
