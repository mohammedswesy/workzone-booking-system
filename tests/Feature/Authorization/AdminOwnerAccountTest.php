<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\SetPasswordInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

function strongPassword(): string
{
    return 'Str0ng-Passw0rd!';
}

it('lets an admin create an owner with email and password who can log in', function () {
    $admin = User::factory()->admin()->create();
    $password = strongPassword();

    $this->actingAs($admin)
        ->post(route('admin.owners.store'), [
            'name' => 'Direct Owner',
            'email' => 'direct-owner@example.com',
            'phone' => '+970500000111',
            'password' => $password,
            'password_confirmation' => $password,
            'must_change_password' => false,
            'role' => 'admin',
        ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Create')
            ->where('created.email', 'direct-owner@example.com')
            ->where('created.plain_password', $password)
            ->where('created.invitation', null)
        );

    // Plain password must not leak into the session for later pages.
    expect(json_encode(session()->all()))->not->toContain($password)
        ->and(session('created'))->toBeNull()
        ->and(session('invitation'))->toBeNull();

    $owner = User::where('email', 'direct-owner@example.com')->first();
    expect($owner)->not->toBeNull()
        ->and($owner->role)->toBe(Role::Owner)
        ->and($owner->must_change_password)->toBeFalse()
        ->and(Hash::check($password, $owner->password))->toBeTrue();

    // Fresh GET of create must not resurface the password.
    $this->actingAs($admin)
        ->get(route('admin.users.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Create')
            ->where('created', null)
        );

    $this->post('/logout');

    $this->post('/login', [
        'email' => 'direct-owner@example.com',
        'password' => $password,
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($owner->fresh());
});

it('defaults must_change_password to true when creating with a password', function () {
    $admin = User::factory()->admin()->create();
    $password = strongPassword();

    $this->actingAs($admin)
        ->post(route('admin.owners.store'), [
            'name' => 'Must Change Owner',
            'email' => 'must-change-owner@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])
        ->assertOk();

    expect(User::where('email', 'must-change-owner@example.com')->first()?->must_change_password)->toBeTrue();
});

it('returns credentials once via JSON and never leaves the password in session', function () {
    $admin = User::factory()->admin()->create();
    $password = strongPassword();

    $this->actingAs($admin)
        ->postJson(route('admin.owners.store'), [
            'name' => 'Inline Owner',
            'email' => 'inline-owner@example.com',
            'password' => $password,
            'password_confirmation' => $password,
            'must_change_password' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('owner.email', 'inline-owner@example.com')
        ->assertJsonPath('owner.name', 'Inline Owner')
        ->assertJsonPath('credentials.email', 'inline-owner@example.com')
        ->assertJsonPath('credentials.plain_password', $password)
        ->assertJsonPath('invitation', null);

    expect(json_encode(session()->all()))->not->toContain($password);

    $owner = User::where('email', 'inline-owner@example.com')->first();
    expect($owner->role)->toBe(Role::Owner)
        ->and($owner->must_change_password)->toBeTrue();
});

it('shows the invitation setup url when mail is not deliverable', function () {
    Notification::fake();
    config()->set('mail.default', 'array');
    Log::spy();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.owners.store'), [
            'name' => 'Invite Owner',
            'email' => 'invite-owner@example.com',
            'send_invitation' => true,
        ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Create')
            ->where('created.email', 'invite-owner@example.com')
            ->where('created.plain_password', null)
            ->where('created.invitation.sent', false)
            ->where(
                'created.invitation.setup_url',
                fn ($url) => is_string($url) && str_contains($url, 'set-password'),
            )
        );

    expect(session('invitation'))->toBeNull();

    foreach (['info', 'debug', 'notice', 'warning', 'error', 'critical'] as $level) {
        Log::shouldNotHaveReceived($level, function ($message = null, $context = []) {
            return str_contains(json_encode([$message, $context]) ?: '', 'set-password');
        });
    }

    Notification::assertNothingSent();
});

it('emails the invitation and omits the setup url when mail is deliverable', function () {
    Notification::fake();
    config()->set('mail.default', 'smtp');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.owners.store'), [
            'name' => 'Mailed Invite Owner',
            'email' => 'mailed-invite-owner@example.com',
            'send_invitation' => true,
        ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Create')
            ->where('created.email', 'mailed-invite-owner@example.com')
            ->where('created.plain_password', null)
            ->where('created.invitation.sent', true)
            ->where('created.invitation.setup_url', null)
        );

    $owner = User::where('email', 'mailed-invite-owner@example.com')->firstOrFail();
    Notification::assertSentTo($owner, SetPasswordInvitation::class);

    expect(session('invitation'))->toBeNull();
});

it('forces password change until a new distinct password is set', function () {
    $password = strongPassword();
    $owner = User::factory()->owner()->create([
        'password' => $password,
        'must_change_password' => true,
    ]);

    $this->actingAs($owner)
        ->get(route('owner.dashboard'))
        ->assertRedirect(route('password.force.edit'));

    $this->actingAs($owner)
        ->get(route('password.force.edit'))
        ->assertOk();

    $this->actingAs($owner)
        ->put(route('password.force.update'), [
            'current_password' => $password,
            'password' => $password,
            'password_confirmation' => $password,
        ])
        ->assertSessionHasErrors('password');

    $newPassword = 'An0ther-Str0ng!';

    $this->actingAs($owner)
        ->put(route('password.force.update'), [
            'current_password' => $password,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])
        ->assertRedirect(route('dashboard'));

    expect($owner->fresh()->must_change_password)->toBeFalse()
        ->and(Hash::check($newPassword, $owner->fresh()->password))->toBeTrue();

    $this->actingAs($owner->fresh())
        ->get(route('owner.dashboard'))
        ->assertOk();
});

it('ignores a forged role on the owner creation endpoint', function () {
    $admin = User::factory()->admin()->create();
    $password = strongPassword();

    $this->actingAs($admin)
        ->post(route('admin.owners.store'), [
            'name' => 'Forged Role Owner',
            'email' => 'forged-role-owner@example.com',
            'password' => $password,
            'password_confirmation' => $password,
            'role' => 'admin',
        ])
        ->assertOk();

    expect(User::where('email', 'forged-role-owner@example.com')->first()?->role)->toBe(Role::Owner);
});

it('forbids non-admins from creating owners or resetting passwords', function () {
    $owner = User::factory()->owner()->create();
    $target = User::factory()->owner()->create();
    $password = strongPassword();

    $this->actingAs($owner)
        ->post(route('admin.owners.store'), [
            'name' => 'Nope',
            'email' => 'nope-owner@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])
        ->assertForbidden();

    $this->actingAs($owner)
        ->post(route('admin.users.reset-password', $target), [
            'password' => $password,
            'password_confirmation' => $password,
        ])
        ->assertForbidden();
});

it('rejects duplicate emails with a clear message', function () {
    $admin = User::factory()->admin()->create();
    User::factory()->owner()->create(['email' => 'taken@example.com']);
    $password = strongPassword();

    $this->actingAs($admin)
        ->post(route('admin.owners.store'), [
            'name' => 'Duplicate',
            'email' => 'taken@example.com',
            'password' => $password,
            'password_confirmation' => $password,
        ])
        ->assertSessionHasErrors(['email' => 'An account with this email already exists.']);
});

it('rejects weak passwords on owner creation', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.owners.store'), [
            'name' => 'Weak',
            'email' => 'weak-owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])
        ->assertSessionHasErrors('password');

    expect(User::where('email', 'weak-owner@example.com')->exists())->toBeFalse();
});

it('lets an admin reset an owner password and require change on next login', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->owner()->create([
        'password' => 'Old-Passw0rd!!',
        'must_change_password' => false,
    ]);
    $password = strongPassword();

    withPasswordConfirmed($this->actingAs($admin))
        ->post(route('admin.users.reset-password', $owner), [
            'password' => $password,
            'password_confirmation' => $password,
            'must_change_password' => true,
        ])
        ->assertRedirect();

    $owner->refresh();
    expect($owner->must_change_password)->toBeTrue()
        ->and(Hash::check($password, $owner->password))->toBeTrue();

    $this->post('/logout');

    $this->post('/login', [
        'email' => $owner->email,
        'password' => $password,
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->get(route('owner.dashboard'))
        ->assertRedirect(route('password.force.edit'));
});

it('keeps owner A and owner B venue isolation unaffected', function () {
    $ownerA = User::factory()->owner()->create();
    $ownerB = User::factory()->owner()->create();
    $workspaceA = Workspace::factory()->create(['owner_id' => $ownerA->id, 'name' => 'Space A Only']);
    $workspaceB = Workspace::factory()->create(['owner_id' => $ownerB->id, 'name' => 'Space B Only']);

    $this->actingAs($ownerA)
        ->get(route('owner.venues.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('venues.data', 1)
            ->where('venues.data.0.id', $workspaceA->venue_id)
        );

    $this->actingAs($ownerB)
        ->get(route('owner.venues.units.edit', [$workspaceA->venue, $workspaceA]))
        ->assertForbidden();

    $this->actingAs($ownerA)
        ->get(route('owner.venues.units.edit', [$workspaceB->venue, $workspaceB]))
        ->assertForbidden();
});
