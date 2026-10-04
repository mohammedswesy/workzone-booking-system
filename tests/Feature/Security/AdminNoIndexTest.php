<?php

use App\Models\User;

it('sends noindex headers on admin routes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertInertia(fn ($page) => $page->where('robotsNoIndex', true));
});

it('does not mark public spaces index as noindex', function () {
    $this->get(route('spaces.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('robotsNoIndex', false));
});
