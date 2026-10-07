<?php

namespace App\Actions\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Str;

class CreateOwnerAccount
{
    public function __construct(
        private readonly SendPasswordInvitation $invitations,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: ?string, password?: ?string, send_invitation?: bool, must_change_password?: bool}  $data
     * @return array{
     *     user: User,
     *     invitation: ?array{sent: bool, setup_url: ?string, email: string, expires_minutes: int},
     *     plain_password: ?string
     * }
     */
    public function handle(array $data): array
    {
        $sendInvitation = (bool) ($data['send_invitation'] ?? false);

        $plainPassword = $sendInvitation
            ? Str::password(32)
            : (string) $data['password'];

        $mustChange = $sendInvitation
            ? false
            : (bool) ($data['must_change_password'] ?? true);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            // Plain value — User casts password with the hashed cast.
            'password' => $plainPassword,
        ]);

        $user->forceFill([
            'role' => Role::Owner,
            'is_active' => true,
            'must_change_password' => $mustChange,
        ])->save();

        $invitation = null;
        if ($sendInvitation) {
            $invitation = $this->invitations->handle($user->fresh());
        }

        return [
            'user' => $user->fresh(),
            'invitation' => $invitation,
            // Only returned once to the admin for display. Never logged or stored.
            'plain_password' => $sendInvitation ? null : $plainPassword,
        ];
    }
}
