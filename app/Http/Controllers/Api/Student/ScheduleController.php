<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\StudentSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;

        $activeCourseIds = $student->enrollments()
            ->where('status', 'active')
            ->pluck('course_id')
            ->toArray();

        $schedules = Schedule::with(['course', 'instructor.user'])
            ->whereIn('course_id', $activeCourseIds)
            ->where('status', 'open')
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->get()
            ->map(function (Schedule $schedule) {
                return [
                    'id' => $schedule->id,
                    'course' => [
                        'name' => $schedule->course->name,
                        'type' => $schedule->course->type,
                    ],
                    'date' => $schedule->date?->format('Y-m-d'),
                    'time_start' => $schedule->time_start,
                    'time_end' => $schedule->time_end,
                    'venue' => $schedule->venue,
                    'instructor' => ['name' => $schedule->instructor->user->name],
                    'booked' => $schedule->studentSchedules()->count(),
                    'max_students' => $schedule->max_students,
                    'status' => $schedule->status,
                ];
            });

        $bookedIds = $student->studentSchedules()->pluck('schedule_id')->toArray();

        return response()->json(['schedules' => $schedules, 'bookedIds' => $bookedIds]);
    }

    /* A student may hold only ONE upcoming booking at a time.
       Portable across SQLite / MySQL: a session is "upcoming" when its
       date is after today, or it is today and has not started yet.
       Pending (awaiting admin acceptance) counts as upcoming too, so a
       student can't stack multiple pending requests across sessions. */
    protected function hasUpcomingBooking(Request $request): bool
    {
        $student = $request->user()->student;
        $now = now();

        return $student->studentSchedules()
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereHas('schedule', function ($q) use ($now) {
                $q->where(function ($inner) use ($now) {
                    $inner->whereDate('date', '>', $now->toDateString())
                        ->orWhere(function ($today) use ($now) {
                            $today->whereDate('date', '=', $now->toDateString())
                                ->whereTime('time_start', '>=', $now->format('H:i:s'));
                        });
                });
            })
            ->exists();
    }

    public function book(Request $request, Schedule $schedule)
    {
        $student = $request->user()->student;

        // Serialize concurrent booking attempts on the same session. The row
        // lock + transaction make the capacity check + insert atomic, so two
        // students clicking "Book" at the same time cannot both grab the last
        // slot.
        return DB::transaction(function () use ($request, $schedule, $student) {
            $schedule = Schedule::whereKey($schedule->id)->lockForUpdate()->first();
            if (! $schedule) {
                return response()->json(['message' => 'Session not found.'], 404);
            }

            if ($schedule->status !== 'open') {
                return response()->json(
                    ['message' => 'This session is no longer open for booking.'],
                    422
                );
            }

            $enrollment = Enrollment::where('student_id', $student->id)
                ->where('course_id', $schedule->course_id)
                ->where('status', 'active')
                ->exists();

            if (! $enrollment) {
                return response()->json(['message' => 'Payment must be verified before booking.'], 422);
            }

            if ($this->hasUpcomingBooking($request)) {
                return response()->json(
                    ['message' => 'You already have an upcoming session booked. Cancel it first to book another.'],
                    422
                );
            }

            $bookedIds = $student->studentSchedules()->pluck('schedule_id')->toArray();

            if (in_array($schedule->id, $bookedIds)) {
                return response()->json(['message' => 'You have already booked this session.'], 422);
            }

            // Count pending + confirmed together so a single slot can't be
            // stacked with multiple pending requests from different students.
            $occupied = $schedule->studentSchedules()
                ->whereIn('status', ['pending', 'confirmed'])
                ->count();
            if ($occupied >= $schedule->max_students) {
                return response()->json(['message' => 'This session is already full.'], 422);
            }

            StudentSchedule::create([
                'student_id' => $student->id,
                'schedule_id' => $schedule->id,
                'booked_at' => now(),
                'status' => 'pending',
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Booking submitted. The admin must accept the assigned instructor before your slot is confirmed.',
            ], 201);
        });
    }

    /* Cancel a booking — allowed only at least 1 hour before the session starts. */
    public function cancel(Request $request, Schedule $schedule)
    {
        $student = $request->user()->student;

        $booking = $student->studentSchedules()->where('schedule_id', $schedule->id)->first();

        if (! $booking) {
            return response()->json(['message' => 'You have not booked this session.'], 422);
        }

        $startsAt = Carbon::parse($schedule->date->format('Y-m-d') . ' ' . $schedule->time_start);
        if (now()->addHour()->gte($startsAt)) {
            return response()->json(
                ['message' => 'Cancellation is only allowed at least 1 hour before the session starts.'],
                422
            );
        }

        $booking->delete();

        if ($schedule->status === 'full') {
            $schedule->update(['status' => 'open']);
        }

        return response()->json(['ok' => true]);
    }

    /* PDC calendar: green days = days that have NO scheduled PDC session yet
       (free days the student can request). Sundays are never green. PDC only
       unlocks after TDC is passed. */
    public function pdcCalendar(Request $request)
    {
        $student = $request->user()->student;

        $pdcCourseIds = $student->enrollments()
            ->where('status', 'active')
            ->whereHas('course', fn ($q) => $q->where('type', 'PDC'))
            ->pluck('course_id')
            ->toArray();

        if (empty($pdcCourseIds)) {
            return response()->json(['green_days' => [], 'unlocked' => false]);
        }

        $month = $request->query('month'); // YYYY-MM
        if (!$month) {
            $month = now()->format('Y-m');
        }

        // Days that already have a session are not free.
        $busyDays = Schedule::whereIn('course_id', $pdcCourseIds)
            ->where('date', 'like', $month . '%')
            ->pluck('date')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()
            ->values()
            ->all();

        // Build the list of free (green) days in the month, from today onward,
        // skipping Sundays and already-busy days.
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $today = now()->startOfDay();
        $green = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            if ($d->lt($today)) continue;
            if ($d->dayOfWeek === Carbon::SUNDAY) continue;
            $key = $d->toDateString();
            if (in_array($key, $busyDays, true)) continue;
            $green[] = $key;
        }

        return response()->json(['green_days' => $green, 'unlocked' => true]);
    }

    /* Instructors available on a free day: they have NO session scheduled that
       day (so they can take a new PDC booking). */
    public function pdcDay(Request $request)
    {
        $student = $request->user()->student;
        $date = $request->query('date');

        $pdcCourseIds = $student->enrollments()
            ->where('status', 'active')
            ->whereHas('course', fn ($q) => $q->where('type', 'PDC'))
            ->pluck('course_id')
            ->toArray();

        if (empty($pdcCourseIds) || ! $date) {
            return response()->json(['instructors' => []]);
        }

        // Instructors who already have any session on this date are busy.
        $busyInstructorIds = Schedule::whereDate('date', $date)
            ->pluck('instructor_id')
            ->unique()
            ->values()
            ->all();

        $instructors = \App\Models\Instructor::with('user')
            ->whereNotIn('id', $busyInstructorIds)
            ->get()
            ->map(fn ($i) => [
                'instructor_id' => $i->id,
                'instructor_name' => $i->user->name ?? 'Instructor',
                'specialization' => $i->specialization ?? null,
            ])
            ->values()
            ->all();

        return response()->json(['instructors' => $instructors]);
    }
}
