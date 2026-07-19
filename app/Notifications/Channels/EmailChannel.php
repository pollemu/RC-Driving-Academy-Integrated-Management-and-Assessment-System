<?php

namespace App\Notifications\Channels;

use App\Models\NotificationLog;
use Illuminate\Support\Facades\Mail;

/**
 * Email channel built on Laravel's mailer (config/mail.php).
 * When MAIL_ENABLED=false the message is logged but not dispatched.
 * MAIL_MAILER=log (the default for local dev) writes to
 * storage/logs/laravel.log instead of delivering, which is the
 * intended no-key behaviour.
 */
class EmailChannel
{
    public function send(string $to, string $subject, string $body, string $event, ?int $userId = null): bool
    {
        $enabled = config('notifications.mail.enabled');
        $from = config('notifications.mail.from');

        if (! $enabled) {
            NotificationLog::record('email', $event, $to, $subject . "\n\n" . $body, false, 'disabled', $userId);
            return false;
        }

        try {
            Mail::html($body, function ($message) use ($to, $subject, $from) {
                $message->to($to)
                    ->subject($subject)
                    ->from($from['address'], $from['name']);
            });

            NotificationLog::record('email', $event, $to, $subject . "\n\n" . $body, true, null, $userId);
            return true;
        } catch (\Throwable $e) {
            NotificationLog::record('email', $event, $to, $subject . "\n\n" . $body, false, $e->getMessage(), $userId);
            return false;
        }
    }
}
