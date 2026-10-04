<?php

namespace App\Http\Controllers\User;

use App\Actions\Bookings\CreateBooking;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Models\Booking;
use App\Models\Workspace;
use App\Services\Pricing\BookingPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingPricingService $pricing,
        private readonly CreateBooking $createBooking,
    ) {
        $this->authorizeResource(Booking::class, 'booking');
    }

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
            'filters' => [
                'status' => $request->string('status')->toString(),
                'per_page' => (int) $request->integer('per_page', 12),
            ],
        ]);
    }

    public function show(Booking $booking)
    {
        $booking->load([
            'workspace:id,name,location,price_per_hour,payment_instructions,payment_methods',
            'payments' => fn ($q) => $q->latest(),
        ]);

        return Inertia::render('User/Bookings/Show', [
            'booking' => $booking,
        ]);
    }

    public function create(Request $request)
    {
        $workspaceId = $request->integer('workspace_id');

        $workspaces = Workspace::query()
            ->with('activeOffers')
            ->select('id', 'name', 'price_per_hour', 'opening_time', 'closing_time', 'location')
            ->orderBy('name')
            ->get()
            ->map(fn (Workspace $ws) => $this->workspacePayload($ws));

        $workspace = null;
        if ($workspaceId) {
            $ws = Workspace::with('activeOffers')
                ->select('id', 'name', 'price_per_hour', 'opening_time', 'closing_time', 'location')
                ->find($workspaceId);
            if ($ws) {
                $workspace = $this->workspacePayload($ws);
            }
        }

        return Inertia::render('User/Bookings/Create', [
            'workspaces' => $workspaces,
            'preselect' => $workspace['id'] ?? null,
            'workspace' => $workspace,
        ]);
    }

    public function store(StoreBookingRequest $request)
    {
        $data = $request->validated();
        $workspace = Workspace::findOrFail($data['workspace_id']);

        $this->createBooking->handle(
            $request->user(),
            $workspace,
            Carbon::parse($data['start_at']),
            Carbon::parse($data['end_at']),
        );

        return redirect()->route('user.bookings.index')->with('success', 'تم إنشاء الحجز.');
    }

    public function edit(Booking $booking)
    {
        $booking->load(['workspace:id,name,price_per_hour,opening_time,closing_time']);

        $workspaces = Workspace::with('activeOffers')
            ->select('id', 'name', 'price_per_hour', 'opening_time', 'closing_time')
            ->orderBy('name')
            ->get()
            ->map(fn (Workspace $ws) => $this->workspacePayload($ws));

        return Inertia::render('User/Bookings/Edit', [
            'booking' => $booking,
            'workspaces' => $workspaces,
        ]);
    }

    public function update(UpdateBookingRequest $request, Booking $booking)
    {
        $data = $request->validated();

        DB::transaction(function () use ($booking, $data) {
            $workspace = Workspace::whereKey($data['workspace_id'])->lockForUpdate()->firstOrFail();
            $start = Carbon::parse($data['start_at'])->seconds(0);
            $end = Carbon::parse($data['end_at'])->seconds(0);

            $overlap = Booking::query()
                ->where('workspace_id', $workspace->id)
                ->whereKeyNot($booking->id)
                ->whereIn('status', \App\Enums\BookingStatus::blocking())
                ->where('start_at', '<', $end)
                ->where('end_at', '>', $start)
                ->lockForUpdate()
                ->exists();

            if ($overlap) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'start_at' => 'This workspace is already booked for the selected time range.',
                ]);
            }

            $quote = $this->pricing->quote($workspace, $start, $end);

            $booking->update([
                'workspace_id' => $workspace->id,
                'start_at' => $start,
                'end_at' => $end,
                'hours' => (int) max(1, (int) ceil((float) $quote->hours)),
                'total_price' => $quote->finalAmount,
            ]);
        });

        return redirect()->route('user.bookings.index')->with('success', 'تم التحديث.');
    }

    public function destroy(Booking $booking)
    {
        $booking->update([
            'status' => BookingStatus::Cancelled,
        ]);

        return redirect()
            ->route('user.bookings.index')
            ->with('success', 'تم إلغاء الحجز.');
    }

    /**
     * @return array<string, mixed>
     */
    private function workspacePayload(Workspace $ws): array
    {
        $start = now()->addHour()->seconds(0);
        $end = $start->copy()->addHour();
        $quote = $this->pricing->quote($ws, $start, $end);

        return [
            'id' => $ws->id,
            'name' => $ws->name,
            'location' => $ws->location,
            'price_per_hour' => $quote->pricePerHour,
            'effective_price_per_hour' => $quote->finalAmount,
            'active_discount_percent' => $quote->discountPercent,
            'opening_time' => $ws->opening_time,
            'closing_time' => $ws->closing_time,
        ];
    }
}
