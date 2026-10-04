<?php

use App\Enums\Role;
use App\Models\Offer;
use App\Models\User;
use App\Models\Workspace;

function actingAsRole(Role $role): User
{
    return User::factory()->create([
        'role' => $role,
    ]);
}

describe('guests cannot reach protected areas', function () {
    it('redirects guests from admin dashboard to login', function () {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    });

    it('redirects guests from owner dashboard to login', function () {
        $this->get(route('owner.dashboard'))->assertRedirect(route('login'));
    });

    it('redirects guests from owner offers to login', function () {
        $this->get(route('owner.offers.index'))->assertRedirect(route('login'));
    });

    it('does not expose the old unauthenticated offers resource', function () {
        $this->get('/offers')->assertNotFound();
        $this->get('/offers/create')->assertNotFound();
        $this->post('/offers', [])->assertNotFound();
    });
});

describe('users cannot reach owner or admin endpoints', function () {
    it('forbids users from admin dashboard', function () {
        $user = actingAsRole(Role::User);

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    });

    it('forbids users from admin users index', function () {
        $user = actingAsRole(Role::User);

        $this->actingAs($user)
            ->get(route('admin.users.index'))
            ->assertForbidden();
    });

    it('forbids users from owner dashboard', function () {
        $user = actingAsRole(Role::User);

        $this->actingAs($user)
            ->get(route('owner.dashboard'))
            ->assertForbidden();
    });

    it('forbids users from owner workspaces', function () {
        $user = actingAsRole(Role::User);

        $this->actingAs($user)
            ->get(route('owner.workspaces.index'))
            ->assertForbidden();
    });

    it('forbids users from owner offers index and store', function () {
        $user = actingAsRole(Role::User);
        $owner = actingAsRole(Role::Owner);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($user)
            ->get(route('owner.offers.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('owner.offers.store'), [
                'workspace_id' => $workspace->id,
                'title' => 'Hack offer',
                'discount_percent' => 50,
                'is_active' => true,
            ])
            ->assertForbidden();
    });

    it('forbids users from updating another owners offer via owner routes', function () {
        $user = actingAsRole(Role::User);
        $owner = actingAsRole(Role::Owner);
        $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
        $offer = Offer::create([
            'owner_id' => $owner->id,
            'workspace_id' => $workspace->id,
            'title' => 'Owner offer',
            'discount_percent' => 10,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->put(route('owner.offers.update', $offer), [
                'workspace_id' => $workspace->id,
                'title' => 'Hijacked',
                'discount_percent' => 90,
                'is_active' => true,
            ])
            ->assertForbidden();
    });
});

describe('role is not mass assignable', function () {
    it('ignores role on user create via fillable', function () {
        $user = User::create([
            'name' => 'Escalator',
            'email' => 'escalator@example.com',
            'password' => 'password',
            'role' => Role::Admin->value,
        ]);

        $user->refresh();

        expect($user->role)->toBe(Role::User);
    });
});
