<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['instructor_id', 'student_id', 'schedule_id', 'raw_text', 'star_steering', 'star_speed', 'star_lane', 'star_parking', 'star_safety', 'star_mirror'])]
class Feedback extends Model
{
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function assessment(): HasOne
    {
        return $this->hasOne(Assessment::class);
    }
}
