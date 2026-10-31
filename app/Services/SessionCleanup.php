<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\Carbon;

class SessionCleanup
{
    /**
     * Housekeeping for sessions that are already over:
     *
     *  1) Sessions that ended before yesterday and hold no students are
     *     deleted outright (bookings + attendance cascade with them).
     *  2) A past session whose instructor has recorded attendance for every
     *     booked student is automatically marked "completed" — completed
     *     sessions can no longer be booked.
     *
     * Called whenever the admin or instructor session lists are loaded so
     * no cron/console access is needed on shared hosting.
     */
    public function run(): array
    {
        $today = Carbon::today()->toDateString();
        $autoCompleted = 0;
        $deleted = 0;

        /* ---------- 1) Auto-complete fully-assessed past sessions ---------- */
        Schedule::with(['studentSchedules:id,schedule_id,student_id', 'attendance:id,schedule_id,student_id'])
            ->whereNotIn('status', ['completed', 'done', 'cancelled'])
            ->whereRaw("COALESCE(end_date, date) < ?", [$today])
            ->get()
            ->each(function (Schedule $schedule) use (&$autoCompleted) {
                $bookings = $schedule->studentSchedules->pluck('student_id');
                if ($bookings->isEmpty()) {
                    return; // empty sessions are handled by the prune below
                }

                $assessed = $schedule->attendance
                    ->whereIn('student_id', $bookings)
                    ->count();

                if ($assessed >= $bookings->count()) {
                    $schedule->update(['status' => 'done']);
                    $autoCompleted++;
                }
            });

        /* ---------- 2) Delete finished sessions with no students ---------- */
        $deleted = Schedule::whereRaw("COALESCE(end_date, date) < ?", [$today])
            ->doesntHave('studentSchedules')
            ->delete();

        return ['auto_completed' => $autoCompleted, 'deleted_empty' => $deleted];
    }
}
