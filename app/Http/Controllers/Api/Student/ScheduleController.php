<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\StudentSchedule;
use App\Services\PdcAssignment;
use App\Services\TdcScheduler;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;

        $enrollments = $student->enrollments()
            ->where('status', 'active')
            ->with('course')
            ->get();

        $activeCourseIds = $enrollments->pluck('course_id')->toArray();
        $tdcCourseIds = $enrollments
            ->filter(fn ($e) => $e->course && $e->course->type === 'TDC')
            ->pluck('course_id')
            ->all();

        if ($tdcCourseIds) {
            $scheduler = new TdcScheduler();
            foreach ($tdcCourseIds as $courseId) {
                $scheduler->ensureUpcoming($courseId);
            }
        }

        $now = now();

        $schedules = Schedule::with(['course', 'instructor.user'])
            ->whereIn('course_id', $activeCourseIds)
            ->where('status', 'open')
            // Only sessions that have not started yet.
            ->where(function ($query) use ($now) {
                $query->whereDate('date', '>', $now->toDateString())
                    ->orWhere(function ($today) use ($now) {
                        $today->whereDate('date', '=', $now->toDateString())
                            ->whereTime('time_start', '>=', $now->format('H:i:s'));
                    });
            })
            // TDC is booked as 2-day group blocks; retire legacy single-day
            // TDC rows so they don't show up as bookable sessions.
            ->where(function ($query) {
                $query->whereNotNull('end_date')
                    ->orWhereHas('course', fn ($course) => $course->where('type', 'PDC'));
            })
            ->orderBy('date')
            ->orderBy('time_start')
            ->get()
            ->map(function (Schedule $schedule) {
                $booked = $schedule->bookedCount();

                return [
                    'id' => $schedule->id,
                    'course' => [
                        'id' => $schedule->course->id,
                        'name' => $schedule->course->name,
                        'type' => $schedule->course->type,
                    ],
                    'date' => $schedule->date?->format('Y-m-d'),
                    'end_date' => $schedule->end_date?->format('Y-m-d'),
                    'time_start' => $schedule->time_start,
                    'time_end' => $schedule->time_end,
                    'venue' => $schedule->venue,
                    'instructor' => [
                        'name' => $schedule->instructor?->user?->name,
                    ],
                    'booked' => $booked,
                    'enrolled' => $booked,
                    'max_students' => $schedule->max_students,
                    'full' => $booked >= $schedule->max_students,
                    'status' => $schedule->status,
                ];
            });

        $bookedIds = $student->studentSchedules()->pluck('schedule_id')->toArray();

        // Progress towards the one TDC class block a learner needs.
        $tdcBooked = $tdcCourseIds
            ? $student->studentSchedules()
                ->whereIn('status', ['pending', 'confirmed'])
                ->whereHas('schedule', fn ($query) => $query
                    ->whereNotNull('end_date')
                    ->whereHas('course', fn ($course) => $course->where('type', 'TDC')))
                ->count()
            : 0;

        $tdcBooked = min($tdcBooked, TdcScheduler::REQUIRED_BLOCKS);

        return response()->json([
            'schedules' => $schedules,
            'bookedIds' => $bookedIds,
            'tdc' => [
                'required' => TdcScheduler::REQUIRED_BLOCKS,
                'booked' => $tdcBooked,
                'remaining' => max(0, TdcScheduler::REQUIRED_BLOCKS - $tdcBooked),
            ],
        ]);
    }

    /* A student may hold only ONE upcoming PDC lesson at a time. TDC group
       blocks are booked separately (one 2-day class block), so they are not
       counted here. */
    protected function hasUpcomingBooking(Request $request): bool
    {
        $student = $request->user()->student;
        $now = now();

        return $student->studentSchedules()
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereHas('schedule', function ($query) use ($now) {
                $query->whereHas('course', fn ($course) => $course->where('type', 'PDC'))
                    ->where(function ($inner) use ($now) {
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

            $schedule->load('course');

            $enrollment = Enrollment::where('student_id', $student->id)
                ->where('course_id', $schedule->course_id)
                ->where('status', 'active')
                ->exists();

            if (! $enrollment) {
                return response()->json(['message' => 'Payment must be verified before booking.'], 422);
            }

            /* ---------- TDC: group class, fixed 2-day blocks, instant ---------- */
            if ($schedule->course?->type === 'TDC') {
                if (! $schedule->end_date) {
                    return response()->json(
                        ['message' => 'This TDC session is no longer bookable. Please pick one of the 2-day class blocks.'],
                        422
                    );
                }

                if (! in_array($schedule->date->dayOfWeek, [Carbon::MONDAY, Carbon::WEDNESDAY, Carbon::FRIDAY], true)) {
                    return response()->json(
                        ['message' => 'TDC classes start on Monday, Wednesday or Friday.'],
                        422
                    );
                }

                $existing = StudentSchedule::with('schedule')
                    ->where('student_id', $student->id)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->whereHas('schedule', fn ($query) => $query
                        ->whereNotNull('end_date'))
                    ->get();

                if ($existing->contains(fn ($booking) => (int) $booking->schedule_id === (int) $schedule->id)) {
                    return response()->json(['message' => 'You have already booked this class block.'], 422);
                }

                // TDC is one 2-day class block — no second block is needed.
                $held = $existing->first(fn ($booking) => $booking->schedule !== null);
                if ($held) {
                    return response()->json([
                        'message' => 'You already have a TDC class block booked ('
                            . $held->schedule->date->format('M j') . '–' . $held->schedule->lastDay()->format('M j')
                            . '). Cancel it from My Booked Sessions if you need a different block.',
                    ], 422);
                }

                if ($schedule->bookedCount() >= $schedule->max_students) {
                    return response()->json(['message' => 'This TDC class is already full.'], 422);
                }

                StudentSchedule::create([
                    'student_id' => $student->id,
                    'schedule_id' => $schedule->id,
                    'booked_at' => now(),
                    'status' => 'confirmed',
                ]);

                return response()->json([
                    'ok' => true,
                    'message' => 'TDC class booked — your 2-day class block is confirmed.',
                ], 201);
            }

            /* ---------- PDC: 1-on-1 lesson, still admin-confirmed ---------- */
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
            if ($schedule->bookedCount() >= $schedule->max_students) {
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

    /* PDC calendar. Every day of the month is labelled so the student sees:
         available  — green, a PDC-capable instructor is free 09:00–11:00
         taken      — red, every PDC-capable instructor is already booked
         preference — white, instructors are free but none match the gender
                      preference saved on the student's profile
         sunday     — gray, the academy is closed
       PDC only unlocks after TDC is passed and the student is enrolled. */
    public function pdcCalendar(Request $request)
    {
        $student = $request->user()->student;

        $pdcCourseIds = $student->enrollments()
            ->where('status', 'active')
            ->whereHas('course', fn ($q) => $q->where('type', 'PDC'))
            ->pluck('course_id')
            ->toArray();

        if (empty($pdcCourseIds)) {
            return response()->json([
                'green_days' => [],
                'days' => [],
                'preferred_instructor_gender' => null,
                'unlocked' => false,
            ]);
        }

        $month = (string) $request->query('month'); // YYYY-MM
        try {
            $start = $month !== ''
                ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable $e) {
            $start = now()->startOfMonth();
        }
        $end = $start->copy()->endOfMonth();

        $preference = $this->preferredGender($student);
        $statuses = (new PdcAssignment())->statuses($start, $end, $preference);

        $today = now()->startOfDay();
        $days = [];
        $green = [];

        foreach ($statuses as $date => $status) {
            // Past days are never bookable, so don't send them to the calendar.
            if (Carbon::parse($date)->lt($today)) {
                continue;
            }

            $days[] = ['date' => $date, 'status' => $status];

            if ($status === 'available') {
                $green[] = $date;
            }
        }

        return response()->json([
            'green_days' => $green,
            'days' => $days,
            'preferred_instructor_gender' => $preference,
            'unlocked' => true,
        ]);
    }

    /* Free PDC instructors on a day, plus the one the academy would assign.
       On a white day the list still contains the free instructors so the
       student can knowingly choose someone outside their preference. */
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
            return response()->json([
                'instructors' => [],
                'recommended' => null,
                'preferred_instructor_gender' => null,
            ]);
        }

        $day = Carbon::parse($date)->startOfDay();

        if ($day->dayOfWeek === Carbon::SUNDAY) {
            return response()->json(['message' => 'The academy is closed on Sundays.'], 422);
        }

        $preference = $this->preferredGender($student);
        $assignment = new PdcAssignment();
        $free = $assignment->freeInstructors($day);
        $recommended = $assignment->pick($day, $preference) ?: $assignment->pick($day);

        $instructors = $free
            ->map(fn ($instructor) => [
                'instructor_id' => $instructor->id,
                'instructor_name' => $instructor->user->name ?? 'Instructor',
                'specialization' => $instructor->specialization,
                'gender' => $instructor->gender,
                'matches_preference' => $preference === null || $instructor->gender === $preference,
            ])
            ->sortBy(fn ($instructor) => ($instructor['matches_preference'] ? '0' : '1') . $instructor['instructor_name'])
            ->values()
            ->all();

        return response()->json([
            'instructors' => $instructors,
            'recommended' => $recommended ? [
                'instructor_id' => $recommended->id,
                'instructor_name' => $recommended->user->name ?? 'Instructor',
                'gender' => $recommended->gender,
            ] : null,
            'preferred_instructor_gender' => $preference,
        ]);
    }

    /* The student's saved soft preference, if it is a usable value. */
    protected function preferredGender($student): ?string
    {
        return in_array($student->preferred_instructor_gender, ['male', 'female'], true)
            ? $student->preferred_instructor_gender
            : null;
    }
}
