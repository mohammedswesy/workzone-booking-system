<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class OfferController extends Controller
{
    /**
     * قائمة العروض
     */
    public function index(Request $request)
    {
        $ownerId = Auth::id();
        $perPage = (int) $request->integer('per_page', 12);
        $search  = $request->string('search')->toString();

        $offers = Offer::query()
            ->with(['workspace:id,name'])
            ->where('owner_id', $ownerId) // فلترة مباشرة بالأداء الأفضل
            ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Owner/Offers/Index', [
            'offers'  => $offers,
            'filters' => ['search' => $search, 'per_page' => $perPage],
        ]);
    }

    /**
     * شاشة إنشاء
     */
    public function create()
    {
        $workspaces = Workspace::where('owner_id', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Owner/Offers/Create', [
            'workspaces' => $workspaces,
        ]);
    }

    /**
     * حفظ جديد
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'workspace_id'     => ['required', 'integer', 'exists:workspaces,id'],
            'title'            => ['required', 'string', 'max:255'],
            'discount_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'starts_at'        => ['nullable', 'date'],
            'ends_at'          => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'        => ['sometimes', 'boolean'],
        ]);

        // تأكيد ملكية الورك سبيس
        $isOwner = Workspace::where('id', $data['workspace_id'])
            ->where('owner_id', Auth::id())
            ->exists();
        abort_unless($isOwner, 403);

        // نمرّر owner_id صراحة لضمان ملء العمود دائمًا
        Offer::create([
            'owner_id'         => Auth::id(),
            'workspace_id'     => $data['workspace_id'],
            'title'            => $data['title'],
            'discount_percent' => $data['discount_percent'],
            'starts_at'        => $data['starts_at'] ?? null,
            'ends_at'          => $data['ends_at'] ?? null,
            'is_active'        => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('owner.offers.index')
            ->with('success', 'تم إنشاء العرض بنجاح.');
    }

    /**
     * شاشة تعديل
     */
    public function edit(Offer $offer)
    {
        abort_unless($offer->owner_id === Auth::id(), 403);

        $workspaces = Workspace::where('owner_id', Auth::id())
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Owner/Offers/Edit', [
            'offer'      => $offer->only([
                'id', 'workspace_id', 'title', 'discount_percent',
                'starts_at', 'ends_at', 'is_active',
            ]),
            'workspaces' => $workspaces,
        ]);
    }

    /**
     * تحديث
     */
    public function update(Request $request, Offer $offer)
    {
        abort_unless($offer->owner_id === Auth::id(), 403);

        $data = $request->validate([
            'workspace_id'     => ['required', 'integer', 'exists:workspaces,id'],
            'title'            => ['required', 'string', 'max:255'],
            'discount_percent' => ['required', 'integer', 'min:1', 'max:100'],
            'starts_at'        => ['nullable', 'date'],
            'ends_at'          => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'        => ['sometimes', 'boolean'],
        ]);

        // تأكيد ملكية الورك سبيس الجديد لو تغيّر
        $isOwner = Workspace::where('id', $data['workspace_id'])
            ->where('owner_id', Auth::id())
            ->exists();
        abort_unless($isOwner, 403);

        // نحافظ على owner_id الحالي (هو نفس Auth::id) ونحدّث باقي الحقول
        $offer->update([
            'owner_id'         => Auth::id(),
            'workspace_id'     => $data['workspace_id'],
            'title'            => $data['title'],
            'discount_percent' => $data['discount_percent'],
            'starts_at'        => $data['starts_at'] ?? null,
            'ends_at'          => $data['ends_at'] ?? null,
            'is_active'        => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('owner.offers.index')
            ->with('success', 'تم التحديث.');
    }

    /**
     * حذف
     */
    public function destroy(Offer $offer)
    {
        abort_unless($offer->owner_id === Auth::id(), 403);

        $offer->delete();

        return redirect()->route('owner.offers.index')
            ->with('success', 'تم الحذف.');
    }
}
