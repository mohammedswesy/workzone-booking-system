<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\PlatformPaymentMethod;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueImage;
use App\Models\Workspace;
use App\Services\Ledger\OwnerLedgerService;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private readonly OwnerLedgerService $ledger,
    ) {}

    public function __invoke()
    {
        $collected = Money::of(
            Payment::query()->where('status', PaymentStatus::Paid)->sum('amount')
        );

        $platform = $this->ledger->platformTotals();

        $pendingProofsQuery = Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('provider', PaymentProvider::Manual);

        $unpaidPendingProofs = (clone $pendingProofsQuery)->count();

        $oldestPendingProofs = (clone $pendingProofsQuery)
            ->with([
                'booking:id,user_id,workspace_id,total_price,created_at',
                'booking.user:id,name,email',
                'booking.workspace:id,name',
            ])
            ->orderBy('created_at')
            ->limit(10)
            ->get(['id', 'booking_id', 'amount', 'transfer_reference', 'created_at', 'status']);

        $bookingsByStatus = Booking::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentBookings = Booking::query()
            ->with(['workspace:id,name', 'user:id,name'])
            ->latest()
            ->limit(8)
            ->get(['id', 'user_id', 'workspace_id', 'total_price', 'status', 'payment_status', 'created_at']);

        $activePlatformMethods = PlatformPaymentMethod::query()->active()->count();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::where('role', Role::User)->count(),
                'owners' => User::where('role', Role::Owner)->count(),
                'workspaces' => Workspace::count(),
                'bookings' => Booking::count(),
                'pending_bookings' => Booking::where('status', BookingStatus::Pending)->count(),
                'revenue' => $collected,
                'collected' => $collected,
                'owed_to_owners' => $platform['owed_to_owners'],
                'commission_earned' => $platform['commission_earned'],
                'paid_out' => $platform['paid_out'],
                'unpaid_pending_proofs' => $unpaidPendingProofs,
                'open_payouts' => OwnerPayout::query()
                    ->whereIn('status', [PayoutStatus::Requested, PayoutStatus::Approved])
                    ->count(),
                'active_platform_methods' => $activePlatformMethods,
                'venues_without_coordinates' => Venue::query()->withoutCoordinates()->count(),
            ],
            'platformMethodsMissing' => $activePlatformMethods === 0,
            'demoImagesInUse' => VenueImage::query()->where('is_demo', true)->exists(),
            'oldestPendingProofs' => $oldestPendingProofs,
            'bookingsByStatus' => $bookingsByStatus,
            'recentBookings' => $recentBookings,
            'reconciliation' => $this->reconciliationCard(),
        ]);
    }

    /**
     * Merge payments + availability verify caches into the admin reconcile card.
     *
     * @return array{ok: bool, issue_count: int, checked_at: ?string, issues: array<int, mixed>, sources: array<string, mixed>}
     */
    private function reconciliationCard(): array
    {
        $payments = Cache::get('payments.reconcile.last', [
            'ok' => true,
            'issue_count' => 0,
            'checked_at' => null,
            'issues' => [],
        ]);
        $availability = Cache::get('availability.verify.last', [
            'ok' => true,
            'issue_count' => 0,
            'checked_at' => null,
            'issues' => [],
        ]);

        $payCount = (int) ($payments['issue_count'] ?? 0);
        $availCount = (int) ($availability['issue_count'] ?? 0);

        return [
            'ok' => (bool) ($payments['ok'] ?? true) && (bool) ($availability['ok'] ?? true),
            'issue_count' => $payCount + $availCount,
            'checked_at' => $availability['checked_at'] ?? $payments['checked_at'] ?? null,
            'issues' => array_values(array_merge(
                $payments['issues'] ?? [],
                $availability['issues'] ?? [],
            )),
            'sources' => [
                'payments' => $payments,
                'availability' => $availability,
            ],
        ];
    }
}
