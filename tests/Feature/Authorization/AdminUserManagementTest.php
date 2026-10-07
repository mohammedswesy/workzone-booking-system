<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Enums\WorkspaceStatus;
use App\Models\Booking;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

it('registration cannot create owner or admin even with a forged role field', function () {
    $this->post('/register', [
        'name' => 'Forged Owner',
        'email' => 'forged-owner@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'owner',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'forged-owner@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(Role::User)
        ->and($user->is_active)->toBeTrue();

    Auth::logout();

    $this->post('/register', [
        'name' => 'Forged Admin',
        'email' => 'forged-admin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'admin',
    ]);

    expect(User::where('email', 'forged-admin@example.com')->first()?->role)->toBe(Role::User);
});

it('only an admin can create owner accounts', function () {
    Notification::fake();
    config()->set('mail.default', 'log');

    $admin = User::factory()->admin()->create();
    $user = User::factory()->userRole()->create();

    $this->actingAs($user)
        ->post(route('admin.users.store'), [
            'name' => 'New Owner',
            'email' => 'new-owner@example.com',
            'role' => 'owner',
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New Owner',
            'email' => 'new-owner@example.com',
            'phone' => '+970500000000',
            'role' => 'owner',
        ])
        ->assertRedirect(route('admin.users.index'));

    $owner = User::where('email', 'new-owner@example.com')->first();
    expect($owner)->not->toBeNull()
        ->and($owner->role)->toBe(Role::Owner)
        ->and($owner->phone)->toBe('+970500000000');
});

it('forbids non-admins from the admin users area', function () {
    $owner = User::factory()->owner()->create();

    $this->actingAs($owner)
        ->get(route('admin.users.index'))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('admin.users.create'))
        ->assertForbidden();
});

it('blocks suspended owners from logging in and hides their workspaces', function () {
    $owner = User::factory()->owner()->suspended()->create([
        'email' => 'suspended-owner@example.com',
        'password' => 'password',
    ]);
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'status' => WorkspaceStatus::Published,
        'name' => 'Hidden Suspended Space',
    ]);

    $this->get(route('spaces.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('spaces.data', fn ($rows) => collect($rows)->every(
                fn ($row) => ($row['name'] ?? null) !== 'Hidden Suspended Space'
            ))
        );

    $this->get(route('spaces.show', $workspace))->assertNotFound();

    $this->post('/login', [
        'email' => 'suspended-owner@example.com',
        'password' => 'password',
    ])->assertRedirect(route('account.suspended'));

    $this->assertGuest();
});

it('prevents removing or demoting the last admin', function () {
    $admin = User::factory()->admin()->create();

    expect(User::adminCount())->toBe(1);

    withPasswordConfirmed($this->actingAs($admin))
        ->put(route('admin.users.update', $admin), [
            'role' => 'user',
        ])
        ->assertSessionHasErrors('role');

    expect($admin->fresh()->role)->toBe(Role::Admin);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertSessionHasErrors('user');

    expect(User::whereKey($admin->id)->exists())->toBeTrue();
});

it('refuses to hard-delete a user who has bookings', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->userRole()->create();
    $workspace = Workspace::factory()->create();
    Booking::factory()->create([
        'user_id' => $customer->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Pending,
        'payment_status' => PaymentStatus::Unpaid,
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $customer))
        ->assertSessionHasErrors('user');

    expect(User::whereKey($customer->id)->exists())->toBeTrue();
});
