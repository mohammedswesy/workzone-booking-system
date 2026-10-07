<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\SendPasswordInvitation;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Models\User;
use App\Services\Audit\AuditLogger;
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
    public function __construct(
        private readonly SendPasswordInvitation $invitations,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $role = $request->string('role')->toString();
        $search = $request->string('search')->toString();
        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

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
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'role' => $role,
                'search' => $search,
                'per_page' => $perPage,
            ],
            'invitation' => $request->session()->get('invitation'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Admin/Users/Create', [
            'mailDeliverable' => MailConfig::isDeliverable(),
            'created' => null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:40'],
            'role' => ['required', Rule::enum(Role::class)],
        ], [
            'email.unique' => 'An account with this email already exists.',
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
            'must_change_password' => false,
        ])->save();

        $invitation = $this->invitations->handle($user);

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
            'user' => $user->only('id', 'name', 'email', 'phone', 'role', 'is_active', 'must_change_password'),
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

        $oldRole = $user->role;
        $user->fill([
            'name' => $data['name'] ?? $user->name,
            'phone' => $data['phone'] ?? $user->phone,
        ]);
        $user->forceFill(['role' => $newRole])->save();

        if ($oldRole !== $newRole) {
            $this->audit->log(
                'user.role_change',
                actor: $actor,
                subject: $user,
                oldValues: ['role' => $oldRole instanceof \BackedEnum ? $oldRole->value : $oldRole],
                newValues: ['role' => $newRole->value],
            );
        }

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

        $this->audit->log(
            'user.suspend',
            actor: $request->user(),
            subject: $user,
            newValues: ['is_active' => false],
        );

        return back()->with('success', 'Account suspended.');
    }

    public function reactivate(User $user)
    {
        $user->forceFill(['is_active' => true])->save();

        $this->audit->log(
            'user.reactivate',
            actor: request()->user(),
            subject: $user,
            newValues: ['is_active' => true],
        );

        return back()->with('success', 'Account reactivated.');
    }

    public function resendInvitation(User $user)
    {
        $invitation = $this->invitations->handle($user);

        return back()
            ->with('success', $invitation['sent']
                ? 'Password setup invitation was sent.'
                : 'Mail is not configured. Use the one-time setup link below.')
            ->with('invitation', $invitation);
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user)
    {
        if ($request->boolean('send_reset_link')) {
            if (! MailConfig::isDeliverable()) {
                throw ValidationException::withMessages([
                    'send_reset_link' => 'Mail is not configured. Set a password directly instead.',
                ]);
            }

            $status = Password::broker('users')->sendResetLink(
                ['email' => $user->email]
            );

            if ($status !== Password::RESET_LINK_SENT) {
                throw ValidationException::withMessages([
                    'email' => [__($status)],
                ]);
            }

            return back()->with('success', 'Password reset link was sent.');
        }

        $user->forceFill([
            'password' => $request->validated('password'),
            'must_change_password' => $request->boolean('must_change_password', true),
            'remember_token' => Str::random(60),
        ])->save();

        $this->audit->log(
            'user.password_reset',
            actor: $request->user(),
            subject: $user,
            newValues: [
                'must_change_password' => $user->must_change_password,
            ],
        );

        return back()->with('success', 'Password updated. The user must sign in with the new password.');
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
