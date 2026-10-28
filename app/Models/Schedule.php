<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['course_id', 'instructor_id', 'date', 'end_date', 'time_start', 'time_end', 'venue', 'max_students', 'status', 'auto_generated'])]
class Schedule extends Model
{
    /* PDC 1-on-1 lessons run in this fixed morning slot. */
    public const PDC_SLOT_START = '09:00';
    public const PDC_SLOT_END = '11:00';

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'auto_generated' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Instructor::class);
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

    public function isFull(): bool
    {
        return $this->bookedCount() >= $this->max_students;
    }

    /* Pending + confirmed both hold a seat. */
    public function bookedCount(): int
    {
        return $this->studentSchedules()
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();
    }

    /* A TDC group block spans two days (Mon–Tue, Wed–Thu, Fri–Sat). */
    public function isBlock(): bool
    {
        return $this->end_date !== null;
    }

    public function lastDay(): Carbon
    {
        return $this->end_date ? $this->end_date->copy() : $this->date->copy();
    }

    /* True when this session runs on the given calendar day. */
    public function coversDate(Carbon|string $day): bool
    {
        $d = $day instanceof Carbon ? $day->copy()->startOfDay() : Carbon::parse($day)->startOfDay();

        return $this->date->copy()->startOfDay()->lte($d) && $this->lastDay()->startOfDay()->gte($d);
    }

    /* True when the session's time overlaps the given [start, end) window.
       Used so a morning block only blocks that window, not the whole day. */
    public function overlapsTime(string $start, string $end): bool
    {
        $from = substr((string) $this->time_start, 0, 5);
        $to = substr((string) $this->time_end, 0, 5);

        if ($from === '' || $to === '') {
            return false;
        }

        return $from < $end && $to > $start;
    }
}
