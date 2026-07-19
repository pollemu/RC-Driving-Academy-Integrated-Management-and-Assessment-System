<?php

namespace App\Notifications\Channels;

use App\Models\NotificationLog;
use Illuminate\Support\Facades\Http;

/**
 * Semaphore SMS driver (Philippines).
 * Docs: https://semaphore.co — sends via the /api/v4/messages endpoint.
 * When SEMAPHORE_API_KEY is missing or SMS_ENABLED=false the message is
 * logged but not sent, so the system works end-to-end without keys.
 */
class SemaphoreSmsChannel
{
    public function send(string $to, string $message, string $event, ?int $userId = null): bool
    {
        $apiKey = config('services.semaphore.api_key');
        $enabled = config('notifications.sms.enabled');
        $sender = config('notifications.sms.from');

        if (! $enabled || ! $apiKey) {
            NotificationLog::record('sms', $event, $to, $message, false, 'disabled:no-key', $userId);
            return false;
        }

        try {
            $response = Http::asForm()->post('https://api.semaphore.co/api/v4/messages', [
                'apikey' => $apiKey,
                'number' => $to,
                'message' => $message,
                'sendername' => $sender,
            ]);

            if ($response->successful()) {
                NotificationLog::record('sms', $event, $to, $message, true, null, $userId);
                return true;
            }

            NotificationLog::record('sms', $event, $to, $message, false, $response->body(), $userId);
            return false;
        } catch (\Throwable $e) {
            NotificationLog::record('sms', $event, $to, $message, false, $e->getMessage(), $userId);
            return false;
        }
    }
}
