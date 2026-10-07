<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Workspace;

it('scopes owner dashboard stats to the owner only', function () {
    $owner = User::factory()->owner()->create();
    $otherOwner = User::factory()->owner()->create();

    $mine = Workspace::factory()->create(['owner_id' => $owner->id]);
    $theirs = Workspace::factory()->create(['owner_id' => $otherOwner->id]);

    Booking::factory()->count(2)->create([
        'workspace_id' => $mine->id,
        'status' => BookingStatus::Pending,
    ]);
    Booking::factory()->count(5)->create([
        'workspace_id' => $theirs->id,
        'status' => BookingStatus::Pending,
    ]);

    Payment::create([
        'booking_id' => Booking::factory()->create([
            'workspace_id' => $mine->id,
            'status' => BookingStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'total_price' => '100.00',
        ])->id,
        'provider' => PaymentProvider::Manual,
        'reference' => 'pay-owner-1',
        'amount' => '100.00',
        'currency' => 'USD',
        'status' => PaymentStatus::Paid,
        'paid_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('owner.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Owner/Dashboard')
            ->where('stats.workspaces_count', 1)
            ->where('stats.bookings_count', 3)
            ->where('stats.pending_count', 2)
            ->where('stats.available_balance', '0.00')
            ->where('stats.pending_balance', '100.00')
        );
});

it('shows admin report kpis and average booking value', function () {
    $admin = User::factory()->admin()->create();
    $workspace = Workspace::factory()->create();

    Booking::factory()->create([
        'workspace_id' => $workspace->id,
        'total_price' => '100.00',
        'status' => BookingStatus::Confirmed,
        'payment_status' => PaymentStatus::Paid,
    ]);
    Booking::factory()->create([
        'workspace_id' => $workspace->id,
        'total_price' => '50.00',
        'status' => BookingStatus::Confirmed,
        'payment_status' => PaymentStatus::Paid,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Reports/Index')
            ->where('kpis.bookings', 2)
            ->where('kpis.revenue', 150)
            ->where('kpis.average_booking_value', 75)
        );
});

it('filters admin reports by owner and exports csv', function () {
    $admin = User::factory()->admin()->create();
    $ownerA = User::factory()->owner()->create(['name' => 'Owner A']);
    $ownerB = User::factory()->owner()->create(['name' => 'Owner B']);
    $wsA = Workspace::factory()->create(['owner_id' => $ownerA->id, 'name' => 'Alpha']);
    $wsB = Workspace::factory()->create(['owner_id' => $ownerB->id, 'name' => 'Beta']);

    Booking::factory()->create(['workspace_id' => $wsA->id, 'total_price' => '80.00']);
    Booking::factory()->create(['workspace_id' => $wsB->id, 'total_price' => '40.00']);

    $this->actingAs($admin)
        ->get(route('admin.reports.index', ['owner_id' => $ownerA->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('kpis.bookings', 1)
            ->where('topWorkspaces.0.name', 'Alpha — Alpha')
        );

    $csv = $this->actingAs($admin)
        ->get(route('admin.reports.export', ['owner_id' => $ownerA->id]));

    $csv->assertOk();
    expect($csv->headers->get('content-disposition'))->toContain('bookings-report');
    expect($csv->streamedContent())->toContain('Alpha')
        ->and($csv->streamedContent())->not->toContain('Beta');
});

it('shows user dashboard counts for the authenticated user only', function () {
    $user = User::factory()->userRole()->create();
    $other = User::factory()->userRole()->create();

    Booking::factory()->count(2)->create([
        'user_id' => $user->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
    ]);
    Booking::factory()->count(4)->create([
        'user_id' => $other->id,
        'status' => BookingStatus::Pending,
    ]);

    $this->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Dashboard')
            ->where('stats.bookings_count', 2)
            ->where('stats.pending_count', 2)
            ->where('stats.unpaid_count', 2)
        );
});

it('forbids non-admins from reports', function () {
    $user = User::factory()->create(['role' => Role::User]);

    $this->actingAs($user)
        ->get(route('admin.reports.index'))
        ->assertForbidden();
});
