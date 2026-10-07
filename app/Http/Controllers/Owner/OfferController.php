<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Venue;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
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
            ->with(['workspace:id,name,venue_id', 'venue:id,name,slug'])
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
        return Inertia::render('Owner/Offers/Create', $this->formOptions());
    }

    public function store(Request $request)
    {
        $data = $this->validatedOffer($request);
        $offer = new Offer($data);

        if ($offer->is_active && $offer->overlapsAnother()) {
            throw ValidationException::withMessages([
                'starts_at' => 'An active offer already overlaps this period for the same scope.',
            ]);
        }

        $offer->save();

        return redirect()->route('owner.offers.index')
            ->with('success', 'تم إنشاء العرض بنجاح.');
    }

    public function edit(Offer $offer)
    {
        return Inertia::render('Owner/Offers/Edit', [
            'offer' => $offer->only([
                'id', 'workspace_id', 'venue_id', 'title', 'discount_percent',
                'starts_at', 'ends_at', 'is_active',
            ]),
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Offer $offer)
    {
        $data = $this->validatedOffer($request);
        $offer->fill($data);

        if ($offer->is_active && $offer->overlapsAnother()) {
            throw ValidationException::withMessages([
                'starts_at' => 'An active offer already overlaps this period for the same scope.',
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

    /**
     * @return array{workspaces: \Illuminate\Support\Collection, venues: \Illuminate\Support\Collection}
     */
    private function formOptions(): array
    {
        $ownerId = Auth::id();

        return [
            'workspaces' => Workspace::query()
                ->where('owner_id', $ownerId)
                ->with('venue:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'venue_id'])
                ->map(fn (Workspace $w) => [
                    'id' => $w->id,
                    'name' => $w->venue
                        ? $w->venue->name.' — '.$w->name
                        : $w->name,
                ]),
            'venues' => Venue::query()
                ->where('owner_id', $ownerId)
                ->orderBy('name')
                ->get(['id', 'name', 'slug']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedOffer(Request $request): array
    {
        $ownerId = Auth::id();

        $data = $request->validate([
            'scope' => ['required', Rule::in(['unit', 'venue'])],
            'workspace_id' => [
                Rule::requiredIf(($request->input('scope') === 'unit')),
                'nullable',
                'integer',
                'exists:workspaces,id',
            ],
            'venue_id' => [
                Rule::requiredIf(($request->input('scope') === 'venue')),
                'nullable',
                'integer',
                'exists:venues,id',
            ],
            'title' => ['required', 'string', 'max:255'],
            'discount_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if ($data['scope'] === 'unit') {
            abort_unless(
                Workspace::where('id', $data['workspace_id'])->where('owner_id', $ownerId)->exists(),
                403
            );

            return [
                'owner_id' => $ownerId,
                'workspace_id' => (int) $data['workspace_id'],
                'venue_id' => null,
                'title' => $data['title'],
                'discount_percent' => $data['discount_percent'],
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ];
        }

        abort_unless(
            Venue::where('id', $data['venue_id'])->where('owner_id', $ownerId)->exists(),
            403
        );

        return [
            'owner_id' => $ownerId,
            'workspace_id' => null,
            'venue_id' => (int) $data['venue_id'],
            'title' => $data['title'],
            'discount_percent' => $data['discount_percent'],
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }
}
