<?php

use App\Actions\Bookings\CreateBooking;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\PlatformPaymentMethod;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\PlatformPaymentMethodChanged;
use App\Support\AppTimezone;
use App\Support\Csv\CsvExporter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;

it('defaults ADMIN_2FA_ENFORCED to false and redirects incomplete enrollment to setup when enforced', function () {
    expect(config('app.admin_2fa_enforced'))->toBeFalse();

    config(['app.admin_2fa_enforced' => true]);

    $admin = User::factory()->admin()->create([
        'two_factor_secret' => null,
        'two_factor_confirmed_at' => null,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('admin.two-factor.setup'));

    $this->actingAs($admin)
        ->get(route('admin.two-factor.setup'))
        ->assertOk();
});

it('resets admin 2fa via artisan without printing secrets and writes an audit log', function () {
    $admin = User::factory()->admin()->create([
        'email' => 'locked-admin@example.com',
        'two_factor_secret' => Crypt::encryptString('SECRETVALUE123456'),
        'two_factor_recovery_codes' => Crypt::encryptString(json_encode(['AAAA-BBBB'])),
        'two_factor_confirmed_at' => now(),
    ]);

    $this->artisan('admin:2fa-reset', ['email' => 'locked-admin@example.com'])
        ->expectsConfirmation('Reset two-factor authentication for locked-admin@example.com? They must enroll again before accessing admin if 2FA is enforced.', 'yes')
        ->expectsOutputToContain('Two-factor authentication was reset')
        ->doesntExpectOutputToContain('SECRETVALUE')
        ->doesntExpectOutputToContain('AAAA-BBBB')
        ->assertSuccessful();

    $admin->refresh();
    expect($admin->two_factor_secret)->toBeNull()
        ->and($admin->two_factor_recovery_codes)->toBeNull()
        ->and($admin->two_factor_confirmed_at)->toBeNull();

    expect(AuditLog::query()->where('action', 'admin.2fa_reset')->where('subject_id', $admin->id)->exists())->toBeTrue();
});

it('keeps app timezone UTC and display timezone Asia/Gaza', function () {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(config('app.display_timezone'))->toBe('Asia/Gaza')
        ->and(AppTimezone::storage())->toBe('UTC')
        ->and(AppTimezone::display())->toBe('Asia/Gaza');
});

it('interprets booking inputs in Asia/Gaza and stores UTC', function () {
    $user = User::factory()->userRole()->create();
    $workspace = Workspace::factory()->create([
        'opening_time' => '08:00:00',
        'closing_time' => '22:00:00',
    ]);

    // Winter: Asia/Gaza is typically UTC+2
    $wall = '2026-11-15 14:00';
    $utc = AppTimezone::parseInput($wall);

    expect($utc->timezone('UTC')->format('Y-m-d H:i'))->toBe('2026-11-15 12:00');

    $this->actingAs($user)
        ->post(route('user.bookings.store'), [
            'workspace_id' => $workspace->id,
            'start_at' => '2026-11-15T14:00',
            'end_at' => '2026-11-15T16:00',
            'seats' => 1,
        ])
        ->assertRedirect(route('user.bookings.index'));

    $booking = Booking::query()->where('user_id', $user->id)->latest('id')->first();
    expect($booking)->not->toBeNull()
        ->and($booking->start_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-11-15 12:00:00')
        ->and($booking->end_at->utc()->format('Y-m-d H:i:s'))->toBe('2026-11-15 14:00:00')
        ->and(AppTimezone::formatDisplay($booking->start_at, 'Y-m-d H:i'))->toBe('2026-11-15 14:00');
});

it('displays an existing 12:00 UTC booking in Asia/Gaza local time', function () {
    $booking = Booking::factory()->create([
        'start_at' => Carbon::parse('2026-01-15 12:00:00', 'UTC'),
        'end_at' => Carbon::parse('2026-01-15 14:00:00', 'UTC'),
    ]);

    expect(AppTimezone::formatDisplay($booking->start_at, 'H:i'))->toBe('14:00')
        ->and(CsvExporter::formatDateTime($booking->start_at))->toBe('2026-01-15 14:00');
});

it('validates opening hours in the display timezone around a DST boundary', function () {
    $user = User::factory()->userRole()->create();
    $workspace = Workspace::factory()->create([
        'opening_time' => '08:00:00',
        'closing_time' => '22:00:00',
    ]);

    // Palestine/Gaza DST historically shifts around late March / late October.
    // Pick a late-October evening wall clock that stays on the same local day.
    $startLocal = '2026-10-24T20:00';
    $endLocal = '2026-10-24T21:00';

    $startUtc = AppTimezone::parseInput($startLocal);
    $endUtc = AppTimezone::parseInput($endLocal);

    expect($startUtc->timezone(AppTimezone::display())->toDateString())
        ->toBe($endUtc->timezone(AppTimezone::display())->toDateString());

    $booking = app(CreateBooking::class)->handle(
        $user,
        $workspace,
        $startUtc,
        $endUtc,
        1,
    );

    expect($booking->start_at->utc()->equalTo($startUtc))->toBeTrue();

    // Past closing in local time must fail even if UTC clock still looks fine.
    expect(fn () => app(CreateBooking::class)->handle(
        $user,
        $workspace,
        AppTimezone::parseInput('2026-10-24T21:30'),
        AppTimezone::parseInput('2026-10-24T22:30'),
        1,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});

it('queues platform method change mail and audits the update', function () {
    Notification::fake();

    $admin = User::factory()->admin()->create();
    $method = PlatformPaymentMethod::factory()->create([
        'account_identifier' => 'PS99OLDACC',
        'account_holder' => 'Old Holder',
    ]);

    withPasswordConfirmed($this->actingAs($admin))
        ->post(route('admin.platform-payments.update', $method), [
            'type' => $method->type->value ?? $method->type,
            'label' => $method->label,
            'account_holder' => 'New Holder',
            'account_identifier' => 'PS99NEWACC',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(AuditLog::query()->where('action', 'platform_payment_method.update')->exists())->toBeTrue();
    Notification::assertSentTo($admin, PlatformPaymentMethodChanged::class);
    expect(new PlatformPaymentMethodChanged([], []))->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
});

it('keeps payment method updates working when admin alert notify fails', function () {
    $admin = User::factory()->admin()->create();
    $method = PlatformPaymentMethod::factory()->create([
        'account_identifier' => 'PS88X',
        'label' => 'Before',
    ]);

    $this->mock(\App\Services\Notifications\SafeAdminNotifier::class, function ($mock) {
        $mock->shouldReceive('notify')->once()->andReturn(false);
    });

    withPasswordConfirmed($this->actingAs($admin))
        ->post(route('admin.platform-payments.update', $method), [
            'type' => $method->type->value ?? $method->type,
            'label' => 'Still Saved',
            'account_holder' => 'X',
            'account_identifier' => 'PS88Y',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success')
        ->assertSessionHas('warning');

    expect($method->fresh()->label)->toBe('Still Saved')
        ->and(AuditLog::query()->where('action', 'platform_payment_method.update')->exists())->toBeTrue();
});

it('SafeAdminNotifier swallows notify exceptions', function () {
    $user = User::factory()->admin()->create();
    $bad = \Mockery::mock($user)->makePartial();
    $bad->shouldReceive('notify')->once()->andThrow(new RuntimeException('smtp down'));

    $ok = app(\App\Services\Notifications\SafeAdminNotifier::class)
        ->notify($bad, new PlatformPaymentMethodChanged(['a' => 1], ['b' => 2]));

    expect($ok)->toBeFalse();
});

it('fails app:security-check in production when MAIL_MAILER is log or array', function () {
    config([
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(str_repeat('b', 32)),
        'session.secure' => true,
        'session.http_only' => true,
        'session.same_site' => 'lax',
        'payments.ledger.cutover_at' => '2026-10-04 00:00:00',
        'mail.default' => 'log',
    ]);

    $this->artisan('app:security-check')->assertFailed();

    config(['mail.default' => 'smtp']);
    $this->artisan('app:security-check')->assertSuccessful();
});
