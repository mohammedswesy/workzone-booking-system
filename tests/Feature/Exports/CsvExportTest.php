<?php

use App\Enums\BookingStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Enums\PayoutStatus;
use App\Models\Booking;
use App\Models\OwnerLedgerEntry;
use App\Models\OwnerPayout;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Csv\CsvExporter;

it('escapes formula-injection cells', function () {
    expect(CsvExporter::escapeCell('=CMD()'))->toBe("'=CMD()")
        ->and(CsvExporter::escapeCell('+1+1'))->toBe("'+1+1")
        ->and(CsvExporter::escapeCell('-2+3'))->toBe("'-2+3")
        ->and(CsvExporter::escapeCell('@SUM(A1)'))->toBe("'@SUM(A1)")
        ->and(CsvExporter::escapeCell("\tTAB"))->toBe("'\tTAB")
        ->and(CsvExporter::escapeCell('Safe name'))->toBe('Safe name')
        ->and(CsvExporter::escapeCell('مساحة غزة'))->toBe('مساحة غزة');
});

it('exports admin reports with BOM, UTF-8 Arabic, injection protection, and localized headers', function () {
    $admin = User::factory()->admin()->create();
    $owner = User::factory()->owner()->create(['name' => 'مالك تجريبي']);
    $user = User::factory()->userRole()->create([
        'name' => 'مستخدم تجريبي',
        'email' => 'guest@example.com',
    ]);
    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'name' => '=HYPERLINK("http://evil") مساحة',
    ]);
    Booking::factory()->create([
        'user_id' => $user->id,
        'workspace_id' => $workspace->id,
        'status' => BookingStatus::Confirmed,
        'payment_status' => PaymentStatus::Paid,
        'seats' => 2,
        'total_price' => '99.50',
        'start_at' => now()->setTime(10, 0),
        'end_at' => now()->setTime(12, 0),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.reports.export', ['locale' => 'ar']));

    $response->assertOk();
    $content = $response->streamedContent();

    expect(substr($content, 0, 3))->toBe(CsvExporter::BOM)
        ->and($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-type'))->toContain('charset=UTF-8')
        ->and($response->headers->get('content-disposition'))->toContain('bookings-report')
        ->and($content)->toContain('الوحدة')
        ->and($content)->toContain('المكان')
        ->and($content)->toContain('المالك')
        ->and($content)->toContain('مالك تجريبي')
        ->and($content)->toContain('مستخدم تجريبي')
        ->and($content)->toContain("'=HYPERLINK")
        ->and($content)->toContain('مؤكد')
        ->and($content)->toContain('مدفوع')
        ->and($content)->toContain('99.50');
});

it('uses english localized headers when locale=en', function () {
    $admin = User::factory()->admin()->create();
    $workspace = Workspace::factory()->create(['name' => 'Desk One']);
    Booking::factory()->create(['workspace_id' => $workspace->id]);

    $content = $this->actingAs($admin)
        ->get(route('admin.reports.export', ['locale' => 'en']))
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain('Unit')
        ->and($content)->toContain('Venue')
        ->and($content)->toContain('Owner')
        ->and($content)->toContain('Booking status')
        ->and($content)->not->toContain('المساحة')
        ->and($content)->not->toContain('الوحدة');
});

it('forbids non-admins from exporting reports', function () {
    $owner = User::factory()->owner()->create();
    $user = User::factory()->userRole()->create();

    $this->actingAs($owner)->get(route('admin.reports.export'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.reports.export'))->assertForbidden();
});

it('lets an owner export only their own payout statement csv', function () {
    $owner = User::factory()->owner()->create(['name' => 'Owner One']);
    $other = User::factory()->owner()->create(['name' => 'Owner Two']);
    $admin = User::factory()->admin()->create();

    $workspace = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'ورشة النصر',
    ]);
    $booking = Booking::factory()->create(['workspace_id' => $workspace->id]);

    $payout = OwnerPayout::create([
        'owner_id' => $owner->id,
        'amount_requested' => '20.00',
        'amount_approved' => '20.00',
        'currency' => 'USD',
        'status' => PayoutStatus::Paid,
        'transfer_reference' => '=EVILREF',
        'paid_at' => now(),
    ]);

    OwnerLedgerEntry::create([
        'idempotency_key' => 'earning:'.$booking->id,
        'owner_id' => $owner->id,
        'booking_id' => $booking->id,
        'type' => LedgerEntryType::Earning,
        'amount' => '20.00',
        'currency' => 'USD',
        'status' => 'posted',
        'note' => '+note injection',
    ]);

    $ownerCsv = $this->actingAs($owner)
        ->get(route('owner.payouts.statement.csv', ['payout' => $payout, 'locale' => 'ar']))
        ->assertOk()
        ->streamedContent();

    expect(substr($ownerCsv, 0, 3))->toBe(CsvExporter::BOM)
        ->and($ownerCsv)->toContain('ورشة النصر')
        ->and($ownerCsv)->toContain('Owner One')
        ->and($ownerCsv)->toContain("'=EVILREF")
        ->and($ownerCsv)->toContain("'+note injection")
        ->and($ownerCsv)->toContain('رقم الصرف');

    $this->actingAs($other)
        ->get(route('owner.payouts.statement.csv', $payout))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('admin.payouts.statement.csv', ['payout' => $payout, 'locale' => 'en']))
        ->assertOk()
        ->streamedContent();
});

it('keeps filtering owner-scoped admin report exports', function () {
    $admin = User::factory()->admin()->create();
    $ownerA = User::factory()->owner()->create(['name' => 'Owner A']);
    $ownerB = User::factory()->owner()->create(['name' => 'Owner B']);
    $wsA = Workspace::factory()->create(['owner_id' => $ownerA->id, 'name' => 'Alpha']);
    $wsB = Workspace::factory()->create(['owner_id' => $ownerB->id, 'name' => 'Beta']);

    Booking::factory()->create(['workspace_id' => $wsA->id, 'total_price' => '80.00']);
    Booking::factory()->create(['workspace_id' => $wsB->id, 'total_price' => '40.00']);

    $content = $this->actingAs($admin)
        ->get(route('admin.reports.export', ['owner_id' => $ownerA->id, 'locale' => 'en']))
        ->assertOk()
        ->streamedContent();

    expect($content)->toContain('Alpha')
        ->and($content)->toContain('Owner A')
        ->and($content)->not->toContain('Beta');
});
