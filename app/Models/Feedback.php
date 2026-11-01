<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['instructor_id', 'student_id', 'schedule_id', 'raw_text',
    // PDC (practical driving) — the five behind-the-wheel categories.
    'star_engine', 'star_brake', 'star_turning', 'star_parking', 'star_traffic',
    'note_engine', 'note_brake', 'note_turning', 'note_parking', 'note_traffic',
    // Legacy three-skill and six-skill columns, kept so old records load/save safely.
    'star_steering', 'star_mirror', 'note_steering', 'note_mirror',
    'star_speed', 'star_lane', 'star_safety',
    'note_speed', 'note_lane', 'note_safety'])]
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
