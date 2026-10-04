<?php

namespace App\Support;

class MailConfig
{
    /**
     * Whether outbound mail is expected to leave the app (not log/array).
     */
    public static function isDeliverable(): bool
    {
        $mailer = (string) config('mail.default', 'log');

        return ! in_array($mailer, ['log', 'array'], true);
    }
}
