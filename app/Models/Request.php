<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['type', 'student_id', 'schedule_id', 'from_schedule_id', 'preferred_gender', 'note', 'status', 'handled_by', 'admin_note'])]
class Request extends Model
{
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public const TYPE_RESCHEDULE = 'reschedule';
    public const TYPE_INSTRUCTOR_PREFERENCE = 'instructor_preference';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'schedule_id');
    }

    public function fromSchedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class, 'from_schedule_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
