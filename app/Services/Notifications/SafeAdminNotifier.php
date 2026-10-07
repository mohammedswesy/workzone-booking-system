<?php

namespace App\Services\Notifications;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Notifications\Notification;
use Throwable;

class SafeAdminNotifier
{
    /**
     * Queue/send an admin notification without letting mail failures block the caller.
     */
    public function notify(?Authenticatable $user, Notification $notification): bool
    {
        if (! $user) {
            return false;
        }

        try {
            $user->notify($notification);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
