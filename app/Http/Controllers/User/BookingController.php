<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function __construct()
    {
        // تأكد عندك BookingPolicy
        $this->authorizeResource(Booking::class, 'booking');
    }

    /**
     * قائمة حجوزات المستخدم
     */
    public function index(Request $request)
    {
        $query = Booking::query()
            ->with(['workspace:id,name,price_per_hour'])
            ->where('user_id', Auth::id())
            ->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $bookings = $query->paginate($request->integer('per_page', 12))
                          ->withQueryString();

        return Inertia::render('User/Bookings/Index', [
            'bookings' => $bookings,
            'filters'  => [
                'status'   => $request->string('status')->toString(),
                'per_page' => (int) $request->integer('per_page', 12),
            ],
        ]);
    }

    /**
     * تفاصيل حجز واحد
     */
    public function show(Booking $booking)
    {
        $booking->load(['workspace:id,name,location,price_per_hour']);

        // احسب السعر الفعّال (للعرض فقط)
        $effective = $this->effectivePricePerHour($booking->workspace);

        return Inertia::render('User/Bookings/Show', [
            'booking' => [
                'id'          => $booking->id,
                'hours'       => $booking->hours,
                'status'      => $booking->status,
                'total_price' => $booking->total_price,
                'created_at'  => $booking->created_at,
                'workspace'   => [
                    'id'                       => $booking->workspace->id,
                    'name'                     => $booking->workspace->name,
                    'location'                 => $booking->workspace->location,
                    'price_per_hour'           => (float) $booking->workspace->price_per_hour,
                    'effective_price_per_hour' => $effective['price'],
                    'active_discount_percent'  => $effective['discount'],
                ],
            ],
        ]);
    }

    /**
     * شاشة إنشاء حجز
     */
    public function create(Request $request)
    {
        $workspaceId = $request->integer('workspace_id');

        // نحضّر قائمة المساحات مع السعر الفعّال
        $workspaces = Workspace::select('id','name','price_per_hour')
            ->orderBy('name')
            ->get()
            ->map(function ($ws) {
                $eff = $this->effectivePricePerHour($ws);
                return [
                    'id'                       => $ws->id,
                    'name'                     => $ws->name,
                    'price_per_hour'           => (float) $ws->price_per_hour,
                    'effective_price_per_hour' => $eff['price'],
                    'active_discount_percent'  => $eff['discount'],
                ];
            });

        $workspace = null;
        if ($workspaceId) {
            $ws = Workspace::select('id','name','price_per_hour','location')->find($workspaceId);
            if ($ws) {
                $eff = $this->effectivePricePerHour($ws);
                $workspace = [
                    'id'                       => $ws->id,
                    'name'                     => $ws->name,
                    'location'                 => $ws->location,
                    'price_per_hour'           => (float) $ws->price_per_hour,
                    'effective_price_per_hour' => $eff['price'],
                    'active_discount_percent'  => $eff['discount'],
                ];
            }
        }

        return Inertia::render('User/Bookings/Create', [
            'workspaces' => $workspaces,
            'preselect'  => $workspace['id'] ?? null,
            'workspace'  => $workspace,
        ]);
    }

    /**
     * حفظ حجز جديد
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'workspace_id' => ['required','integer','exists:workspaces,id'],
            'hours'        => ['required','integer','min:1'],
        ]);

        // منع وجود حجز pending لنفس المساحة للمستخدم نفسه
        $exists = Booking::where('user_id', Auth::id())
            ->where('workspace_id', $data['workspace_id'])
            ->where('status', Booking::STATUS_PENDING ?? 'pending')
            ->exists();

        if ($exists) {
            return back()
                ->withErrors(['workspace_id' => 'لديك حجز معلق لهذه المساحة.'])
                ->withInput();
        }

        $workspace = Workspace::findOrFail($data['workspace_id']);
        $effective = $this->effectivePricePerHour($workspace); // السعر بعد الخصم

        Booking::create([
            'user_id'      => Auth::id(),
            'workspace_id' => $workspace->id,
            'hours'        => $data['hours'],
            'total_price'  => $effective['price'] * $data['hours'],
            'status'       => Booking::STATUS_PENDING ?? 'pending',
        ]);

        return redirect()->route('user.bookings.index')->with('success', 'تم إنشاء الحجز.');
    }

    /**
     * شاشة تعديل الحجز
     */
    public function edit(Booking $booking)
    {
        $booking->load(['workspace:id,name,price_per_hour']);

        $workspaces = Workspace::select('id','name','price_per_hour')
            ->orderBy('name')
            ->get()
            ->map(function ($ws) {
                $eff = $this->effectivePricePerHour($ws);
                return [
                    'id'                       => $ws->id,
                    'name'                     => $ws->name,
                    'price_per_hour'           => (float) $ws->price_per_hour,
                    'effective_price_per_hour' => $eff['price'],
                    'active_discount_percent'  => $eff['discount'],
                ];
            });

        return Inertia::render('User/Bookings/Edit', [
            'booking'    => $booking,
            'workspaces' => $workspaces,
        ]);
    }

    /**
     * تحديث الحجز
     */
    public function update(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'workspace_id' => ['required','integer','exists:workspaces,id'],
            'hours'        => ['required','integer','min:1'],
        ]);

        $workspace = Workspace::findOrFail($data['workspace_id']);
        $effective = $this->effectivePricePerHour($workspace);

        $booking->update([
            'workspace_id' => $workspace->id,
            'hours'        => $data['hours'],
            'total_price'  => $effective['price'] * $data['hours'],
        ]);

        return redirect()->route('user.bookings.index')->with('success', 'تم التحديث.');
    }

    /**
     * حذف الحجز
     */
    public function destroy(Booking $booking)
    {
        $booking->delete();

        return redirect()
            ->route('user.bookings.index')
            ->with('success', 'تم الحذف.');
    }

    /**
     * احسب السعر الفعّال حسب أعلى خصم فعّال على المساحة
     * يتطلب وجود علاقة offers() في Workspace
     */
    private function effectivePricePerHour(Workspace $workspace): array
    {
        $now = now();

        // لو العلاقة مش محمّلة، حمّل العروض الفعّالة فقط
        if (! $workspace->relationLoaded('offers')) {
            $workspace->load(['offers' => function ($q) use ($now) {
                $q->select('workspace_id','discount_percent','is_active','starts_at','ends_at')
                  ->where('is_active', true)
                  ->where(function ($w) use ($now) {
                      $w->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                  })
                  ->where(function ($w) use ($now) {
                      $w->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                  });
            }]);
        }

        $base     = (float) $workspace->price_per_hour;
        $discount = (int) ($workspace->offers->max('discount_percent') ?? 0);
        $price    = $discount > 0 ? round($base * (1 - $discount/100), 2) : $base;

        return ['price' => $price, 'discount' => $discount];
    }
}
