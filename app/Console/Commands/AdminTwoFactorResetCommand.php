<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Console\Command;

class AdminTwoFactorResetCommand extends Command
{
    protected $signature = 'admin:2fa-reset {email : Admin account email}';

    protected $description = 'Clear an admin 2FA enrollment so they can set it up again (never prints secrets)';

    public function handle(AuditLogger $audit): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user) {
            $this->error('No user found for that email.');

            return self::FAILURE;
        }

        if (! $user->isAdmin()) {
            $this->error('That account is not an admin.');

            return self::FAILURE;
        }

        if (! $this->confirm("Reset two-factor authentication for {$user->email}? They must enroll again before accessing admin if 2FA is enforced.")) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $hadEnrollment = filled($user->two_factor_secret) || filled($user->two_factor_confirmed_at);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $audit->log(
            'admin.2fa_reset',
            actor: null,
            subject: $user,
            oldValues: ['had_enrollment' => $hadEnrollment],
            newValues: ['two_factor_cleared' => true],
        );

        $this->info('Two-factor authentication was reset. Secrets were cleared and are not shown.');
        $this->line('The admin can sign in and visit /admin/two-factor/setup to enroll again.');

        return self::SUCCESS;
    }
}
