<?php

namespace App\Http\Controllers\User;

use App\Actions\Bookings\CreateBooking;
use App\Enums\BookingMode;
use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Models\Booking;
use App\Models\Workspace;
use App\Services\Bookings\SeatAvailability;
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
        private readonly SeatAvailability $seats,
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

        $workspace = $booking->workspace;
        $detailsReady = $workspace
            && ! $workspace->hasPlaceholderPaymentInstructions()
            && filled($workspace->bookerPaymentInstructions());

        return Inertia::render('User/Bookings/Show', [
            'booking' => [
                ...$booking->toArray(),
                'workspace' => $workspace ? [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'location' => $workspace->location,
                    'price_per_hour' => $workspace->price_per_hour,
                    'payment_instructions' => $detailsReady ? $workspace->bookerPaymentInstructions() : null,
                    'payment_methods' => $detailsReady ? ($workspace->payment_methods ?? []) : [],
                    'payment_details_ready' => (bool) $detailsReady,
                ] : null,
            ],
        ]);
    }

    public function create(Request $request)
    {
        $workspaceId = $request->integer('workspace_id');

        $workspaces = Workspace::query()
            ->published()
            ->with('activeOffers')
            ->select('id', 'name', 'price_per_hour', 'opening_time', 'closing_time', 'location', 'capacity', 'booking_mode')
            ->orderBy('name')
            ->get()
            ->map(fn (Workspace $ws) => $this->workspacePayload($ws));

        $workspace = null;
        if ($workspaceId) {
            $ws = Workspace::query()
                ->published()
                ->with('activeOffers')
                ->select('id', 'name', 'price_per_hour', 'opening_time', 'closing_time', 'location', 'capacity', 'booking_mode')
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
            isset($data['seats']) ? (int) $data['seats'] : 1,
        );

        return redirect()->route('user.bookings.index')->with('success', 'Booking created.');
    }

    public function availability(Request $request)
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'integer', 'exists:workspaces,id'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'seats' => ['nullable', 'integer', 'min:1'],
            'ignore_booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
        ]);

        $workspace = Workspace::query()->findOrFail($data['workspace_id']);
        $start = Carbon::parse($data['start_at'])->seconds(0);
        $end = Carbon::parse($data['end_at'])->seconds(0);
        $ignore = isset($data['ignore_booking_id']) ? (int) $data['ignore_booking_id'] : null;

        $remaining = $this->seats->remainingSeats($workspace, $start, $end, $ignore);
        $resolved = $this->seats->resolveSeats($workspace, isset($data['seats']) ? (int) $data['seats'] : 1);
        $quoteSeats = $workspace->booking_mode === BookingMode::Whole
            ? max(1, (int) $workspace->capacity)
            : min($resolved, max(1, $remaining));

        $quote = $this->pricing->quote($workspace, $start, $end, seats: $quoteSeats);

        return response()->json([
            'booking_mode' => $workspace->booking_mode?->value ?? BookingMode::Seat->value,
            'capacity' => (int) $workspace->capacity,
            'remaining_seats' => $remaining,
            'seats' => $quoteSeats,
            'quote' => $quote->toArray(),
        ]);
    }

    public function edit(Booking $booking)
    {
        $booking->load(['workspace:id,name,price_per_hour,opening_time,closing_time']);

        $workspaces = Workspace::with('activeOffers')
            ->select('id', 'name', 'price_per_hour', 'opening_time', 'closing_time', 'location', 'capacity', 'booking_mode')
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
            $seats = $this->seats->resolveSeats(
                $workspace,
                isset($data['seats']) ? (int) $data['seats'] : (int) $booking->seats,
            );
            $this->seats->assertCanBook($workspace, $start, $end, $seats, $booking->id);

            $quote = $this->pricing->quote($workspace, $start, $end, seats: $seats);

            $booking->update([
                'workspace_id' => $workspace->id,
                'start_at' => $start,
                'end_at' => $end,
                'hours' => (int) max(1, (int) ceil((float) $quote->hours)),
                'seats' => $seats,
                'total_price' => $quote->finalAmount,
            ]);
        });

        return redirect()->route('user.bookings.index')->with('success', 'Booking updated.');
    }

    public function destroy(Booking $booking)
    {
        $booking->update([
            'status' => BookingStatus::Cancelled,
        ]);

        return redirect()
            ->route('user.bookings.index')
            ->with('success', 'Booking cancelled.');
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
            'capacity' => (int) $ws->capacity,
            'booking_mode' => $ws->booking_mode?->value ?? BookingMode::Seat->value,
            'price_per_hour' => $quote->pricePerHour,
            'effective_price_per_hour' => $quote->finalAmount,
            'active_discount_percent' => $quote->discountPercent,
            'opening_time' => $ws->opening_time,
            'closing_time' => $ws->closing_time,
        ];
    }
}
