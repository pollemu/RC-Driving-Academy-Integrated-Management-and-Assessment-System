<?php

namespace App\Services;

use App\Models\Instructor;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Works out who can take a PDC lesson on a given day.
 *
 * PDC lessons are 1-on-1 and always use the fixed 09:00–11:00 morning slot.
 * A TDC block (08:00–12:00) only keeps its own instructor busy, so the rest
 * of the staff are still free for a practical lesson that morning.
 *
 * Availability is also filtered by the student's soft instructor-gender
 * preference: a day can be open but have nobody of the preferred gender free.
 */
class PdcAssignment
{
    /** Instructors who can actually teach a practical lesson. */
    public function eligibleInstructors(): Collection
    {
        return Instructor::with('user')
            ->where(function ($query) {
                $query->whereNull('specialization')
                    ->orWhere('specialization', 'like', '%PDC%');
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * [Y-m-d => instructor ids] who are busy in the 09:00–11:00 PDC slot
     * between two dates. Every day of a multi-day block is included.
     */
    public function busyByDay(Carbon $from, Carbon $to): array
    {
        $busy = [];

        Schedule::whereNotNull('instructor_id')
            ->where('status', '!=', 'cancelled')
            ->whereRaw('date <= ?', [$to->toDateString()])
            ->whereRaw('COALESCE(end_date, date) >= ?', [$from->toDateString()])
            ->get(['instructor_id', 'date', 'end_date', 'time_start', 'time_end'])
            ->each(function (Schedule $schedule) use (&$busy, $from, $to) {
                if (! $schedule->overlapsTime(Schedule::PDC_SLOT_START, Schedule::PDC_SLOT_END)) {
                    return;
                }

                $cursor = $schedule->date->copy()->max($from->copy());
                $lastKey = $schedule->lastDay()->copy()->min($to->copy())->toDateString();

                // Compare strings so the cursor can never share an instance
                // with the end date and advance both at once.
                while ($cursor->toDateString() <= $lastKey) {
                    $busy[$cursor->toDateString()][$schedule->instructor_id] = true;
                    $cursor->addDay();
                }
            });

        return $busy;
    }

    /** PDC-capable instructors with a free 09:00–11:00 slot on the day. */
    public function freeInstructors(Carbon $day, ?string $gender = null): Collection
    {
        $busy = $this->busyByDay($day->copy(), $day->copy());
        $busyIds = array_keys($busy[$day->toDateString()] ?? []);

        return $this->eligibleInstructors()
            ->whereNotIn('id', $busyIds)
            ->when($gender, fn (Collection $list) => $list->where('gender', $gender))
            ->values();
    }

    /**
     * Date => available | preference | taken | sunday.
     *
     * "preference" means instructors are free, but none of them match the
     * student's requested gender — the white days on the calendar.
     */
    public function statuses(Carbon $from, Carbon $to, ?string $preference = null): array
    {
        $eligible = $this->eligibleInstructors();
        $busy = $this->busyByDay($from, $to);
        $statuses = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();

            if ($day->dayOfWeek === Carbon::SUNDAY) {
                $statuses[$key] = 'sunday';
                continue;
            }

            $free = $eligible->whereNotIn('id', array_keys($busy[$key] ?? []));

            if ($free->isEmpty()) {
                $statuses[$key] = 'taken';
            } elseif ($preference && $free->where('gender', $preference)->isEmpty()) {
                $statuses[$key] = 'preference';
            } else {
                $statuses[$key] = 'available';
            }
        }

        return $statuses;
    }

    /**
     * The instructor to auto-assign for a day. Matching gender first, then the
     * least-loaded instructor (fewest upcoming PDC lessons) so work is shared.
     *
     * Returns null when the preference cannot be honoured — callers decide
     * whether to fall back to the opposite gender.
     */
    public function pick(Carbon $day, ?string $preference = null): ?Instructor
    {
        $free = $this->freeInstructors($day, $preference);

        if ($free->isEmpty()) {
            return null;
        }

        $loads = Schedule::query()
            ->whereIn('instructor_id', $free->pluck('id'))
            ->whereHas('course', fn ($query) => $query->where('type', 'PDC'))
            ->where('status', '!=', 'cancelled')
            ->whereDate('date', '>=', $day->toDateString())
            ->selectRaw('instructor_id, COUNT(*) AS total')
            ->groupBy('instructor_id')
            ->pluck('total', 'instructor_id');

        return $free
            ->sortBy(fn (Instructor $instructor) => sprintf(
                '%06d-%06d',
                (int) ($loads[$instructor->id] ?? 0),
                $instructor->id
            ))
            ->first();
    }
}
