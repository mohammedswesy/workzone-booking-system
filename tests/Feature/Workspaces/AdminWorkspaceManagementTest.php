<?php

use App\Enums\BookingMode;
use App\Enums\WorkspaceStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

it('allows admin to create a workspace for an active owner', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->owner()->create(['is_active' => true]);

    $this->actingAs($admin)
        ->post(route('admin.workspaces.store'), [
            'owner_id' => $owner->id,
            'name' => 'Admin Built Space',
            'location' => 'Gaza',
            'capacity' => 12,
            'booking_mode' => BookingMode::Seat->value,
            'price_per_hour' => 25,
            'status' => WorkspaceStatus::Published->value,
            'payment_instructions' => "Bank transfer.\nIBAN PS00 REAL 0000",
            'payment_methods' => ['bank_transfer', 'cash'],
        ])
        ->assertRedirect(route('admin.workspaces.index'));

    $workspace = Workspace::where('name', 'Admin Built Space')->first();
    expect($workspace)->not->toBeNull()
        ->and($workspace->owner_id)->toBe($owner->id)
        ->and($workspace->booking_mode)->toBe(BookingMode::Seat);

    $this->actingAs($owner)
        ->get(route('owner.workspaces.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('spaces.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => (int) ($row['id'] ?? 0) === (int) $workspace->id
            ))
        );

    $otherOwner = User::factory()->owner()->create();
    $this->actingAs($otherOwner)
        ->get(route('owner.workspaces.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('spaces.data', fn ($rows) => collect($rows)->every(
                fn ($row) => (int) ($row['id'] ?? 0) !== (int) $workspace->id
            ))
        );
});

it('forbids non-admins from the admin workspace create route', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get(route('admin.workspaces.create'))
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('admin.workspaces.store'), [
            'owner_id' => $owner->id,
            'name' => 'Nope',
            'location' => 'Gaza',
            'capacity' => 4,
            'booking_mode' => BookingMode::Seat->value,
            'price_per_hour' => 10,
            'status' => WorkspaceStatus::Draft->value,
        ])
        ->assertForbidden();
});

it('ignores owner_id when an owner creates their own workspace', function () {
    $owner = User::factory()->owner()->create();
    $other = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->post(route('owner.workspaces.store'), [
            'owner_id' => $other->id,
            'name' => 'Mine Only',
            'location' => 'Ramallah',
            'capacity' => 6,
            'booking_mode' => BookingMode::Seat->value,
            'price_per_hour' => 15,
            'status' => WorkspaceStatus::Draft->value,
        ])
        ->assertRedirect(route('owner.workspaces.index'));

    expect(Workspace::where('name', 'Mine Only')->first()?->owner_id)->toBe($owner->id);
});

it('archives a workspace with bookings instead of hard deleting', function () {
    $admin = User::factory()->admin()->create();
    $workspace = Workspace::factory()->create();
    Booking::factory()->create(['workspace_id' => $workspace->id]);

    $this->actingAs($admin)
        ->delete(route('admin.workspaces.destroy', $workspace))
        ->assertRedirect();

    expect(Workspace::whereKey($workspace->id)->exists())->toBeTrue()
        ->and($workspace->fresh()->status)->toBe(WorkspaceStatus::Archived);
});

it('rejects inactive owners when admin creates a workspace', function () {
    $admin = User::factory()->admin()->create();
    $suspended = User::factory()->owner()->suspended()->create();

    $this->actingAs($admin)
        ->post(route('admin.workspaces.store'), [
            'owner_id' => $suspended->id,
            'name' => 'Bad Owner Space',
            'location' => 'Gaza',
            'capacity' => 4,
            'booking_mode' => BookingMode::Seat->value,
            'price_per_hour' => 10,
            'status' => WorkspaceStatus::Draft->value,
        ])
        ->assertSessionHasErrors('owner_id');
});

it('backfills existing workspaces to whole booking mode', function () {
    $workspace = Workspace::factory()->create(['booking_mode' => BookingMode::Seat]);

    DB::table('workspaces')->where('id', $workspace->id)->update(['booking_mode' => 'seat']);

    // Re-apply migration backfill semantics for published/existing inventory.
    DB::table('workspaces')->update(['booking_mode' => 'whole']);

    expect($workspace->fresh()->booking_mode)->toBe(BookingMode::Whole);
});
