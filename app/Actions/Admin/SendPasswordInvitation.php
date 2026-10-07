<?php

namespace App\Actions\Admin;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\SetPasswordInvitation;
use App\Support\MailConfig;
use Illuminate\Support\Facades\Password;

class SendPasswordInvitation
{
    /**
     * Create a one-time invitation token (expires per auth.passwords.invitations.expire).
     *
     * Never write the setup URL to application logs. When mail is not deliverable,
     * the URL is flashed once to the admin session only (encrypted cookie).
     *
     * @return array{sent: bool, setup_url: ?string, email: string, expires_minutes: int}
     */
    public function handle(User $user): array
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
}
