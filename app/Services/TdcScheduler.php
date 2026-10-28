<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Instructor;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Keeps the fixed TDC group-class calendar populated.
 *
 * TDC is not a 1-on-1 lesson: the academy runs class blocks every week —
 * Monday–Tuesday, Wednesday–Thursday and Friday–Saturday — and a learner
 * books ONE 2-day block to complete the course. Several blocks are kept open
 * each week so learners can choose a schedule that suits them. The blocks
 * exist independently of any student's instructor preference, so availability
 * is always visible.
 */
class TdcScheduler
{
    /** How many weeks ahead (including the current one) to keep populated. */
    public const WEEKS_AHEAD = 4;

    /** Default class size for a TDC block. */
    public const CAPACITY = 20;

    /** Day offsets from Monday: Monday (0), Wednesday (2), Friday (4). */
    public const BLOCK_START_OFFSETS = [0, 2, 4];

    /** TDC is completed with one 2-day class block. */
    public const REQUIRED_BLOCKS = 1;

    public function ensureUpcoming(?int $courseId = null, int $weeks = self::WEEKS_AHEAD): void
    {
        $courses = Course::query()
            ->where('type', 'TDC')
            ->where('is_active', true)
            ->when($courseId, fn ($query) => $query->whereKey($courseId))
            ->get();

        if ($courses->isEmpty()) {
            return;
        }

        $busy = $this->instructorBusyMap();
        $instructors = $this->instructorCandidates();

        foreach ($courses as $course) {
            $this->ensureForCourse($course, $weeks, $instructors, $busy);
        }
    }

    /**
     * Create any missing blocks for one course.
     *
     * @param  Collection<int, Instructor>  $instructors
     * @param  array<int, array<int, array{0: string, 1: string}>>  $busy
     */
    public function ensureForCourse(
        Course $course,
        int $weeks = self::WEEKS_AHEAD,
        ?Collection $instructors = null,
        ?array &$busy = null
    ): void {
        $today = Carbon::today();
        $monday = $today->copy()->startOfWeek(Carbon::MONDAY);

        // Existing rows (including cancelled ones) are never recreated, so an
        // admin cancelling a block sticks.
        $existing = Schedule::query()
            ->where('course_id', $course->id)
            ->whereDate('date', '>=', $monday->toDateString())
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();

        $instructors ??= $this->instructorCandidates();
        if ($busy === null) {
            $busy = $this->instructorBusyMap();
        }

        for ($week = 0; $week < $weeks; $week++) {
            $weekStart = $monday->copy()->addWeeks($week);

            foreach (self::BLOCK_START_OFFSETS as $offset) {
                $start = $weekStart->copy()->addDays($offset);
                $end = $start->copy()->addDay(); // Mon→Tue, Wed→Thu, Fri→Sat

                if ($start->lt($today)) {
                    continue; // already started/finished
                }

                $key = $start->toDateString();
                if (in_array($key, $existing, true)) {
                    continue;
                }

                $instructorId = $this->pickInstructor($instructors, $busy, $start, $end);

                Schedule::create([
                    'course_id' => $course->id,
                    'instructor_id' => $instructorId,
                    'date' => $key,
                    'end_date' => $end->toDateString(),
                    'time_start' => '08:00',
                    'time_end' => '12:00',
                    'venue' => $course->format === 'Online'
                        ? 'Online (Live Class)'
                        : 'RC Driving Academy — Main Branch',
                    'max_students' => self::CAPACITY,
                    'status' => 'open',
                    'auto_generated' => true,
                ]);

                $existing[] = $key;

                if ($instructorId) {
                    $busy[$instructorId][] = [$key, $end->toDateString()];
                }
            }
        }
    }

    /**
     * Instructors ordered so TDC-capable staff are tried first.
     *
     * @return Collection<int, Instructor>
     */
    protected function instructorCandidates(): Collection
    {
        return Instructor::query()
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Instructor $instructor) => str_contains((string) $instructor->specialization, 'TDC') ? 0 : 1)
            ->values();
    }

    /**
     * @return array<int, array<int, array{0: string, 1: string}>>
     */
    protected function instructorBusyMap(): array
    {
        $map = [];

        Schedule::query()
            ->whereNotNull('instructor_id')
            ->where('status', '!=', 'cancelled')
            ->get(['instructor_id', 'date', 'end_date'])
            ->each(function (Schedule $schedule) use (&$map) {
                $map[$schedule->instructor_id][] = [
                    $schedule->date->toDateString(),
                    $schedule->lastDay()->toDateString(),
                ];
            });

        return $map;
    }

    /**
     * First instructor with both class days free (TDC-capable staff first).
     *
     * @param  Collection<int, Instructor>  $instructors
     * @param  array<int, array<int, array{0: string, 1: string}>>  $busy
     */
    protected function pickInstructor(Collection $instructors, array $busy, Carbon $start, Carbon $end): ?int
    {
        foreach ($instructors as $instructor) {
            $ranges = $busy[$instructor->id] ?? [];
            $conflict = false;

            foreach ($ranges as [$rangeStart, $rangeEnd]) {
                if ($rangeStart <= $end->toDateString() && $rangeEnd >= $start->toDateString()) {
                    $conflict = true;
                    break;
                }
            }

            if (! $conflict) {
                return $instructor->id;
            }
        }

        return null;
    }
}
