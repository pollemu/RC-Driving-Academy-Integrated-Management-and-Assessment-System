<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\StudentSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
                    'date' => $schedule->date,
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
       date is after today, or it is today and has not started yet. */
    protected function hasUpcomingBooking(Request $request): bool
    {
        $student = $request->user()->student;
        $now = now();

        return $student->studentSchedules()
            ->where('status', 'confirmed')
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

        $confirmedCount = $schedule->studentSchedules()->where('status', 'confirmed')->count();
        if ($confirmedCount >= $schedule->max_students) {
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
        ]);
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
}
