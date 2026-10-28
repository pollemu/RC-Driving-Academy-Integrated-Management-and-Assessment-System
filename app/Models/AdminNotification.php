<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/* Admin-facing in-app notification. One row per thing the admin should look
   at (a payment came in, a student signed up, a request needs a decision, a
   student asked for a human, a certificate was issued). The dashboard groups
   these into tabs by `category`. SMS / email / maps delivery attempts live in
   NotificationLog instead. */
#[Fillable(['category', 'title', 'body', 'meta', 'user_id', 'read_at'])]
class AdminNotification extends Model
{
    public const CATEGORY_PAYMENT = 'payment';
    public const CATEGORY_ACCOUNT = 'account';
    public const CATEGORY_REQUEST = 'request';
    public const CATEGORY_CHAT = 'chat';
    public const CATEGORY_CERTIFICATE = 'certificate';

    public const CATEGORIES = [
        self::CATEGORY_PAYMENT,
        self::CATEGORY_ACCOUNT,
        self::CATEGORY_REQUEST,
        self::CATEGORY_CHAT,
        self::CATEGORY_CERTIFICATE,
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'read_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function markRead(): void
    {
        if (! $this->read_at) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }

    /* Create a feed entry. Callers wrap this in try/catch: notifications must
       never break the action that produced them. */
    public static function record(
        string $category,
        string $title,
        ?string $body = null,
        array $meta = [],
        ?int $userId = null
    ): self {
        return static::create([
            'category' => $category,
            'title' => $title,
            'body' => $body,
            'meta' => $meta ?: null,
            'user_id' => $userId,
        ]);
    }
}
