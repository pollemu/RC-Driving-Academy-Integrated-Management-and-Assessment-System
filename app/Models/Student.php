<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'instructor_id', 'phone', 'address', 'birthdate', 'license_type',
    'gender', 'preferred_instructor_gender', 'document_path',
    'id_status', 'id_document_type', 'id_document_name', 'id_document_mime',
    'id_submitted_at', 'id_reviewed_at', 'id_reviewed_by', 'id_rejection_reason',
])]
class Student extends Model
{
    use HasFactory;

    /* Valid-ID verification states. */
    public const ID_NONE = 'none';

    public const ID_PENDING = 'pending';

    public const ID_APPROVED = 'approved';

    public const ID_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'birthdate' => 'date',
            'id_submitted_at' => 'datetime',
            'id_reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function idReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_reviewed_by');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(\App\Models\Request::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(\App\Models\Message::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function studentSchedules(): HasMany
    {
        return $this->hasMany(StudentSchedule::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function certifications(): HasMany
    {
        return $this->hasMany(Certification::class);
    }
}
