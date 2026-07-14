<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Schedule;
use App\Models\StudentSchedule;
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

        $bookedIds = $student->studentSchedules()->pluck('schedule_id')->toArray();

        if (in_array($schedule->id, $bookedIds)) {
            return response()->json(['message' => 'You have already booked this session.'], 422);
        }

        if ($schedule->isFull()) {
            return response()->json(['message' => 'This session is already full.'], 422);
        }

        StudentSchedule::create([
            'student_id' => $student->id,
            'schedule_id' => $schedule->id,
            'booked_at' => now(),
        ]);

        if ($schedule->isFull()) {
            $schedule->update(['status' => 'full']);
        }

        return response()->json(['ok' => true]);
    }
}
