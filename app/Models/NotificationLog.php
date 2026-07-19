<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['channel', 'event', 'recipient', 'message', 'sent', 'error', 'user_id'])]
class NotificationLog extends Model
{
    protected function casts(): array
    {
        return [
            'sent' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $channel, string $event, ?string $recipient, ?string $message, bool $sent, ?string $error = null, ?int $userId = null): self
    {
        return static::create([
            'channel' => $channel,
            'event' => $event,
            'recipient' => $recipient,
            'message' => $message,
            'sent' => $sent,
            'error' => $error,
            'user_id' => $userId,
        ]);
    }
}
