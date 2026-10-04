<?php

use App\Enums\WorkspaceStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Support\PaymentInstructionsPlaceholder;

it('keeps published workspaces without payment instructions on the public listing', function () {
    $owner = User::factory()->owner()->create(['is_active' => true]);

    $legacy = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Legacy Published Space',
        'status' => WorkspaceStatus::Published,
        'payment_instructions' => null,
        'payment_methods' => null,
    ]);

    expect($legacy->fresh()->payment_instructions)->toBeNull();

    $this->get(route('spaces.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/Workspaces/Index')
            ->where('spaces.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => (int) ($row['id'] ?? 0) === (int) $legacy->id
            ))
        );
});

it('backfills payment instructions for published workspaces missing them', function () {
    $owner = User::factory()->owner()->create(['is_active' => true]);

    $legacy = Workspace::factory()->create([
        'owner_id' => $owner->id,
        'name' => 'Needs Backfill Space',
        'status' => WorkspaceStatus::Published,
        'payment_instructions' => null,
        'payment_methods' => null,
    ]);

    $migration = require database_path('migrations/2026_10_04_170000_backfill_workspace_payment_instructions.php');
    $migration->up();

    $legacy->refresh();

    expect($legacy->payment_instructions)->toBe(PaymentInstructionsPlaceholder::EN);

    $this->get(route('spaces.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('spaces.data', fn ($rows) => collect($rows)->contains(
                fn ($row) => (int) ($row['id'] ?? 0) === (int) $legacy->id
            ))
        );
});
