<?php

use App\Enums\Role;
use App\Models\User;
use App\Notifications\SetPasswordInvitation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('expires invitation tokens after 24 hours using the invitations broker', function () {
    expect(config('auth.passwords.invitations.expire'))->toBe(60 * 24)
        ->and(config('auth.passwords.users.expire'))->toBe(60);

    $user = User::factory()->owner()->create([
        'password' => Hash::make('old-password'),
    ]);

    $token = Password::broker('invitations')->createToken($user);

    $this->travel(61)->minutes();

    // Still valid at 61 minutes (invite broker is 24h).
    $this->post('/set-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

it('rejects invitation tokens after more than 24 hours', function () {
    $user = User::factory()->owner()->create([
        'password' => Hash::make('old-password'),
    ]);

    $token = Password::broker('invitations')->createToken($user);
    $expire = (int) config('auth.passwords.invitations.expire');

    $this->travel($expire + 1)->minutes();

    $this->post('/set-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('allows a password invitation token to be used only once', function () {
    $user = User::factory()->owner()->create([
        'password' => Hash::make('old-password'),
    ]);

    $token = Password::broker('invitations')->createToken($user);

    $this->post('/set-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login'));

    $this->post('/set-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'another-password-456',
        'password_confirmation' => 'another-password-456',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

it('keeps normal password-reset links at 60 minutes', function () {
    $user = User::factory()->userRole()->create([
        'password' => Hash::make('old-password'),
    ]);

    $token = Password::broker('users')->createToken($user);

    $this->travel(61)->minutes();

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertSessionHasErrors('email');

    expect(Hash::check('old-password', $user->fresh()->password))->toBeTrue();
});

it('never logs the setup url in plain text when creating an owner without deliverable mail', function () {
    Notification::fake();
    config()->set('mail.default', 'array');

    Log::spy();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Prod Owner',
            'email' => 'prod-owner@example.com',
            'role' => Role::Owner->value,
        ])
        ->assertRedirect(route('admin.users.index'));

    $invitation = session('invitation');
    expect($invitation)->toBeArray()
        ->and($invitation['sent'])->toBeFalse()
        ->and($invitation['setup_url'])->toBeString()
        ->and($invitation['setup_url'])->toContain('set-password')
        ->and($invitation['expires_minutes'])->toBe((int) config('auth.passwords.invitations.expire'));

    $url = (string) $invitation['setup_url'];

    foreach (['info', 'debug', 'notice', 'warning', 'error', 'critical'] as $level) {
        Log::shouldNotHaveReceived($level, function ($message = null, $context = []) use ($url) {
            return str_contains(json_encode([$message, $context]) ?: '', $url);
        });
    }

    Notification::assertNothingSent();
});

it('emails the invitation without flashing the setup url when mail is deliverable', function () {
    Notification::fake();
    config()->set('mail.default', 'smtp');

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Mailed Owner',
            'email' => 'mailed-owner@example.com',
            'role' => Role::Owner->value,
        ])
        ->assertRedirect(route('admin.users.index'));

    $owner = User::where('email', 'mailed-owner@example.com')->firstOrFail();
    Notification::assertSentTo($owner, SetPasswordInvitation::class);

    expect(session('invitation.setup_url'))->toBeNull()
        ->and(session('invitation.sent'))->toBeTrue();
});
