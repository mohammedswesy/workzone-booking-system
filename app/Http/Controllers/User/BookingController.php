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
use App\Support\AppTimezone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingPricingService $pricing,
        private readonly CreateBooking $createBooking,
        private readonly SeatAvailability $seats,
        private readonly \App\Services\Availability\AvailabilityService $availability,
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
            'workspace:id,name,location,price_per_hour',
            'payments' => fn ($q) => $q->latest(),
        ]);

        $workspace = $booking->workspace;
        $platformMethods = \App\Models\PlatformPaymentMethod::query()
            ->active()
            ->get()
            ->map->toBookerArray()
            ->values();
        $detailsReady = $platformMethods->isNotEmpty();
        $timeoutMinutes = (int) config('booking.pending_timeout_minutes', 30);
        $expiresAt = $booking->created_at?->copy()->addMinutes($timeoutMinutes);

        return Inertia::render('User/Bookings/Show', [
            'booking' => [
                ...$booking->toArray(),
                'expires_at' => $expiresAt?->toIso8601String(),
                'pending_timeout_minutes' => $timeoutMinutes,
                'workspace' => $workspace ? [
                    'id' => $workspace->id,
                    'name' => $workspace->name,
                    'location' => $workspace->location,
                    'price_per_hour' => $workspace->price_per_hour,
                    'payment_details_ready' => $detailsReady,
                ] : null,
            ],
            'platformPaymentMethods' => $detailsReady ? $platformMethods : [],
        ]);
    }

    public function create(Request $request)
    {
        $workspaceId = $request->integer('workspace_id');

        $workspaces = Workspace::query()
            ->published()
            ->with(['activeOffers', 'venue:id,timezone', 'hours', 'venue.hours', 'availabilityExceptions', 'venue.availabilityExceptions'])
            ->select('id', 'name', 'price_per_hour', 'opening_time', 'closing_time', 'location', 'capacity', 'booking_mode', 'venue_id', 'inherits_venue_hours', 'bookings_paused', 'bookings_paused_note')
            ->orderBy('name')
            ->get()
            ->map(fn (Workspace $ws) => $this->workspacePayload($ws));

        $workspace = null;
        if ($workspaceId) {
            $ws = Workspace::query()
                ->published()
                ->with(['activeOffers', 'venue:id,timezone', 'hours', 'venue.hours', 'availabilityExceptions', 'venue.availabilityExceptions'])
                ->select('id', 'name', 'price_per_hour', 'opening_time', 'closing_time', 'location', 'capacity', 'booking_mode', 'venue_id', 'inherits_venue_hours', 'bookings_paused', 'bookings_paused_note')
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
            $this->availability->parseInput($workspace, $data['start_at']),
            $this->availability->parseInput($workspace, $data['end_at']),
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

        $workspace = Workspace::query()->with(['venue', 'hours', 'venue.hours', 'availabilityExceptions', 'venue.availabilityExceptions'])->findOrFail($data['workspace_id']);
        $start = $this->availability->parseInput($workspace, $data['start_at']);
        $end = $this->availability->parseInput($workspace, $data['end_at']);
        $ignore = isset($data['ignore_booking_id']) ? (int) $data['ignore_booking_id'] : null;

        $decision = $this->availability->evaluate($workspace, $start, $end);
        $duration = max(15, (int) $start->diffInMinutes($end));
        $next = $decision->allowed
            ? null
            : $this->availability->nextAvailableSlot($workspace, $duration, $start);

        $remaining = $decision->allowed
            ? $this->seats->remainingSeats($workspace, $start, $end, $ignore)
            : 0;
        $resolved = $this->seats->resolveSeats($workspace, isset($data['seats']) ? (int) $data['seats'] : 1);
        $quoteSeats = $workspace->booking_mode === BookingMode::Whole
            ? max(1, (int) $workspace->capacity)
            : min($resolved, max(1, $remaining));

        $quote = $decision->allowed
            ? $this->pricing->quote($workspace, $start, $end, seats: $quoteSeats)
            : null;

        return response()->json([
            'booking_mode' => $workspace->booking_mode?->value ?? BookingMode::Seat->value,
            'capacity' => (int) $workspace->capacity,
            'remaining_seats' => $remaining,
            'seats' => $quoteSeats,
            'quote' => $quote?->toArray(),
            'available' => $decision->allowed,
            'reason' => $decision->localizedMessage(),
            'reason_key' => $decision->reasonKey,
            'timezone' => $this->availability->timezoneFor($workspace),
            'next_slot' => $next,
            'schedule' => $this->availability->weeklySchedule($workspace),
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
            $start = AppTimezone::parseInput($data['start_at']);
            $end = AppTimezone::parseInput($data['end_at']);
            $seats = $this->seats->resolveSeats(
                $workspace,
                isset($data['seats']) ? (int) $data['seats'] : (int) $booking->seats,
            );
            $this->seats->assertCanBook($workspace, $start, $end, $seats, $booking->id);
            $this->createBooking->assertWithinOpeningHours($workspace, $start, $end);

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
        $ws->loadMissing(['venue', 'hours', 'venue.hours', 'availabilityExceptions', 'venue.availabilityExceptions']);
        $start = now()->addHour()->seconds(0);
        $end = $start->copy()->addHour();
        $quote = $this->pricing->quote($ws, $start, $end);
        $schedule = $this->availability->weeklySchedule($ws);

        return [
            'id' => $ws->id,
            'name' => $ws->name,
            'location' => $ws->location,
            'capacity' => (int) $ws->capacity,
            'booking_mode' => $ws->booking_mode?->value ?? BookingMode::Seat->value,
            'price_per_hour' => $quote->pricePerHour,
            'effective_price_per_hour' => $quote->finalAmount,
            'active_discount_percent' => $quote->discountPercent,
            /** @deprecated Prefer schedule from AvailabilityService */
            'opening_time' => $schedule[0]['opens_at'] ?? $ws->opening_time,
            'closing_time' => $schedule[0]['closes_at'] ?? $ws->closing_time,
            'schedule' => $schedule,
            'timezone' => $this->availability->timezoneFor($ws),
        ];
    }
}
