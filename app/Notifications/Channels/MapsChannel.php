<?php

namespace App\Notifications\Channels;

use App\Models\NotificationLog;

/**
 * Maps channel for PDC route / venue mapping (Google Maps).
 * Currently generates a shareable Google Maps directions URL for a
 * destination venue. When MAPS_ENABLED=false or no key is present the
 * URL is logged but not "resolved" — the frontend can still render the
 * link. Real routing/geocoding can be layered on later using
 * GOOGLE_MAPS_API_KEY.
 */
class MapsChannel
{
    public function directionsUrl(string $destination, ?string $origin = null): string
    {
        $params = $origin
            ? 'origin=' . urlencode($origin) . '&destination=' . urlencode($destination)
            : 'destination=' . urlencode($destination);

        return 'https://www.google.com/maps/dir/?api=1&' . $params;
    }

    public function embedUrl(string $destination): ?string
    {
        $apiKey = config('services.google_maps.api_key');
        $enabled = config('notifications.maps.enabled');

        if (! $enabled || ! $apiKey) {
            return null;
        }

        return 'https://www.google.com/maps/embed/v1/place?key=' . $apiKey . '&q=' . urlencode($destination);
    }

    public function log(string $event, string $destination, ?string $url = null, ?int $userId = null): void
    {
        $enabled = config('notifications.maps.enabled');
        NotificationLog::record(
            'maps',
            $event,
            $destination,
            $url ?? $this->directionsUrl($destination),
            (bool) $enabled,
            $enabled ? null : 'disabled:no-key',
            $userId
        );
    }
}
