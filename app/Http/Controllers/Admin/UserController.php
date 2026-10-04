<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\SetPasswordInvitation;
use App\Support\MailConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->string('role')->toString();
        $search = $request->string('search')->toString();

        $users = User::query()
            ->select('id', 'name', 'email', 'phone', 'role', 'is_active', 'created_at')
            ->when($role !== '', fn ($q) => $q->where('role', $role))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'role' => $role,
                'search' => $search,
            ],
            'invitation' => $request->session()->get('invitation'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Users/Create', [
            'mailDeliverable' => MailConfig::isDeliverable(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $role = Role::from($data['role']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make(Str::password(32)),
        ]);

        $user->forceFill([
            'role' => $role,
            'is_active' => true,
        ])->save();

        $invitation = $this->sendPasswordInvitation($user);

        return redirect()
            ->route('admin.users.index')
            ->with('success', $invitation['sent']
                ? 'Account created. Password setup invitation was sent.'
                : 'Account created. Copy the one-time setup link below (mail is not configured).')
            ->with('invitation', $invitation);
    }

    public function edit(User $user)
    {
        return Inertia::render('Admin/Users/Edit', [
            'user' => $user->only('id', 'name', 'email', 'phone', 'role', 'is_active'),
            'isSelf' => $user->id === request()->user()?->id,
            'isLastAdmin' => $user->isLastAdmin(),
            'hasFinancialHistory' => $user->hasFinancialHistory(),
            'mailDeliverable' => MailConfig::isDeliverable(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $newRole = Role::from($data['role']);
        $actor = $request->user();

        if ($user->id === $actor?->id && $newRole !== Role::Admin) {
            throw ValidationException::withMessages([
                'role' => 'You cannot demote your own admin account.',
            ]);
        }

        if ($user->isAdmin() && $newRole !== Role::Admin && $user->isLastAdmin()) {
            throw ValidationException::withMessages([
                'role' => 'The last remaining admin cannot be demoted.',
            ]);
        }

        $user->fill([
            'name' => $data['name'] ?? $user->name,
            'phone' => $data['phone'] ?? $user->phone,
        ]);
        $user->forceFill(['role' => $newRole])->save();

        return back()->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user)
    {
        $actor = $request->user();

        if ($user->id === $actor?->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        if ($user->isLastAdmin()) {
            throw ValidationException::withMessages([
                'user' => 'The last remaining admin cannot be removed.',
            ]);
        }

        if ($user->hasFinancialHistory()) {
            throw ValidationException::withMessages([
                'user' => 'This user has bookings or payments and cannot be deleted. Suspend the account instead.',
            ]);
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted.');
    }

    public function suspend(Request $request, User $user)
    {
        $this->guardSelfAction($request, $user, 'suspend');

        if ($user->isLastAdmin()) {
            throw ValidationException::withMessages([
                'user' => 'The last remaining admin cannot be suspended.',
            ]);
        }

        $user->forceFill(['is_active' => false])->save();

        return back()->with('success', 'Account suspended.');
    }

    public function reactivate(User $user)
    {
        $user->forceFill(['is_active' => true])->save();

        return back()->with('success', 'Account reactivated.');
    }

    public function resendInvitation(Request $request, User $user)
    {
        $invitation = $this->sendPasswordInvitation($user);

        return back()
            ->with('success', $invitation['sent']
                ? 'Password setup invitation was sent.'
                : 'Mail is not configured. Use the one-time setup link below.')
            ->with('invitation', $invitation);
    }

    /**
     * Create a one-time invitation token (expires per auth.passwords.invitations.expire = 24h).
     *
     * Never write the setup URL to application logs. When mail is not deliverable,
     * the URL is flashed once to the admin session only (encrypted cookie).
     *
     * @return array{sent: bool, setup_url: ?string, email: string, expires_minutes: int}
     */
    private function sendPasswordInvitation(User $user): array
    {
        $token = Password::broker('invitations')->createToken($user);
        $expiresMinutes = (int) config('auth.passwords.invitations.expire', 1440);

        $roleLabel = match ($user->role) {
            Role::Admin => 'admin',
            Role::Owner => 'owner',
            default => 'user',
        };

        if (MailConfig::isDeliverable()) {
            $user->notify(new SetPasswordInvitation($token, $roleLabel));

            return [
                'sent' => true,
                'setup_url' => null,
                'email' => $user->email,
                'expires_minutes' => $expiresMinutes,
            ];
        }

        // Avoid Mail::log / array drivers (they would persist the token in plain text).
        $setupUrl = url(route('password.set', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        return [
            'sent' => false,
            'setup_url' => $setupUrl,
            'email' => $user->email,
            'expires_minutes' => $expiresMinutes,
        ];
    }

    private function guardSelfAction(Request $request, User $user, string $action): void
    {
        if ($user->id === $request->user()?->id) {
            throw ValidationException::withMessages([
                'user' => "You cannot {$action} your own account.",
            ]);
        }
    }
}
