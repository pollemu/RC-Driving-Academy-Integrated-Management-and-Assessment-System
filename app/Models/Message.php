<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'instructor_id', 'admin_id', 'sender', 'body'])]
class Message extends Model
{
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public const SENDER_STUDENT = 'student';
    public const SENDER_INSTRUCTOR = 'instructor';
    public const SENDER_BOT = 'bot';
    public const SENDER_ADMIN = 'admin';

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /* A student is "handed off to a human" once an admin has ever replied in
       their chat thread. */
    public static function adminReplied(Student $student): bool
    {
        return static::where('student_id', $student->id)
            ->where('sender', static::SENDER_ADMIN)
            ->exists();
    }
}
