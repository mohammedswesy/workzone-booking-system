<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\VenueImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AppSecurityCheckCommand extends Command
{
    protected $signature = 'app:security-check';

    protected $description = 'Fail if production security settings are unsafe';

    public function handle(): int
    {
        $failures = [];
        $warnings = [];

        if (config('app.env') === 'production' && config('app.debug')) {
            $failures[] = 'APP_DEBUG must be false in production.';
        }

        if (! filled(config('app.key'))) {
            $failures[] = 'APP_KEY is missing.';
        }

        if (config('app.env') === 'production' && ! config('session.secure')) {
            $failures[] = 'SESSION_SECURE_COOKIE must be true in production.';
        }

        if (config('session.http_only') === false) {
            $failures[] = 'SESSION_HTTP_ONLY must be true.';
        }

        $sameSite = strtolower((string) config('session.same_site', 'lax'));
        if (! in_array($sameSite, ['lax', 'strict'], true)) {
            $failures[] = 'SESSION_SAME_SITE must be lax or strict.';
        }

        if (! filled(config('payments.ledger.cutover_at'))) {
            $failures[] = 'payments.ledger.cutover_at is unset.';
        }

        // Demo accounts should not exist in production.
        if (config('app.env') === 'production') {
            $demo = User::query()
                ->whereIn('email', [
                    'admin@workzone.test',
                    'owner@workzone.test',
                    'user@workzone.test',
                ])
                ->exists();
            if ($demo) {
                $failures[] = 'Demo accounts are present in production.';
            }
        }

        // Private proof dir must not be web-reachable via public storage link.
        $publicProofs = public_path('storage/payment-proofs');
        if (File::isDirectory($publicProofs)) {
            $failures[] = 'public/storage/payment-proofs is exposed; proofs must stay on the private disk.';
        }

        if (config('app.env') === 'production') {
            $mailer = strtolower((string) config('mail.default'));
            if (in_array($mailer, ['log', 'array'], true)) {
                $failures[] = 'MAIL_MAILER must not be log/array in production (admin alerts require real mail).';
            }
        }

        if (config('app.env') === 'production' && VenueImage::query()->where('is_demo', true)->exists()) {
            $warnings[] = 'Demo venue gallery images are present in production. Run: php artisan demo:remove-images';
        }

        foreach ($warnings as $warning) {
            $this->warn('WARN: '.$warning);
        }

        if ($failures === []) {
            $this->info('Security check passed.'.($warnings === [] ? '' : ' (with warnings)'));

            return self::SUCCESS;
        }

        $this->error('Security check failed:');
        foreach ($failures as $failure) {
            $this->line('- '.$failure);
        }

        return self::FAILURE;
    }
}
