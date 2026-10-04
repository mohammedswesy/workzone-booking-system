<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\WorkspaceStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PaymentInstructionsPlaceholder;

it('backfills a neutral payment instructions placeholder without inventing methods', function () {
    $owner = User::factory()->owner()->create();
    $legacy = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'status' => WorkspaceStatus::Published,
        'payment_instructions' => null,
        'payment_methods' => null,
    ]);

    $migration = require database_path('migrations/2026_10_04_170000_backfill_workspace_payment_instructions.php');
    $migration->up();

    $legacy->refresh();

    expect($legacy->payment_instructions)->toBe(PaymentInstructionsPlaceholder::EN)
        ->and($legacy->payment_instructions)->toBe('Contact the owner to confirm the payment method.')
        ->and($legacy->hasPlaceholderPaymentInstructions())->toBeTrue();
});

it('normalizes legacy placeholder copy to the neutral text', function () {
    $owner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'status' => WorkspaceStatus::Published,
        'payment_instructions' => PaymentInstructionsPlaceholder::LEGACY_EN,
        'payment_methods' => ['bank_transfer', 'wallet', 'cash'],
    ]);

    $migration = require database_path('migrations/2026_10_04_180000_normalize_payment_instructions_placeholder.php');
    $migration->up();

    $workspace->refresh();

    expect($workspace->payment_instructions)->toBe(PaymentInstructionsPlaceholder::EN)
        ->and($workspace->payment_methods)->toBe([]);
});

it('does not expose placeholder payment instructions on the booking page', function () {
    $owner = User::factory()->owner()->create();
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'payment_instructions' => PaymentInstructionsPlaceholder::EN,
        'payment_methods' => [],
    ]);
    $booker = User::factory()->userRole()->create();
    $booking = Booking::factory()->create([
        'user_id' => $booker->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
    ]);

    $this->actingAs($booker)
        ->get(route('user.bookings.show', $booking))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Bookings/Show')
            ->where('booking.workspace.payment_instructions', null)
            ->where('booking.workspace.payment_details_ready', false)
            ->where('booking.workspace.payment_methods', [])
        );
});

it('shows owner dashboard banner for workspaces with placeholder payment instructions', function () {
    $owner = User::factory()->owner()->create();
    $space = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Needs Real Pay Info',
        'payment_instructions' => PaymentInstructionsPlaceholder::EN,
    ]);
    Workspace::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Ready Space',
        'payment_instructions' => "Pay cash to reception.\nAsk for WorkZone booking.",
        'payment_methods' => ['cash'],
    ]);

    $this->actingAs($owner)
        ->get(route('owner.dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Owner/Dashboard')
            ->has('needsPaymentSetup', 1)
            ->where('needsPaymentSetup.0.id', $space->id)
            ->where('needsPaymentSetup.0.name', 'Needs Real Pay Info')
        );
});
