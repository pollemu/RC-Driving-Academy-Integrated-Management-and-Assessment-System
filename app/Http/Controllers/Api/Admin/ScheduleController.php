<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Schedule;
use App\Models\StudentSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index()
    {
        $schedules = Schedule::with(['course', 'instructor.user'])->latest('date')->get();

        $data = $schedules->map(function (Schedule $schedule) {
            return [
                'id' => $schedule->id,
                'course' => [
                    'id' => $schedule->course->id,
                    'name' => $schedule->course->name,
                    'type' => $schedule->course->type,
                ],
                'instructor' => [
                    'id' => $schedule->instructor->id,
                    'name' => $schedule->instructor->user->name,
                ],
                'date' => $schedule->date?->format('Y-m-d'),
                'time_start' => $schedule->time_start,
                'time_end' => $schedule->time_end,
                'venue' => $schedule->venue,
                'max_students' => $schedule->max_students,
                'booked' => $schedule->studentSchedules()->count(),
                'status' => $schedule->status,
            ];
        });

        return response()->json(['schedules' => $data]);
    }

    public function courses()
    {
        $courses = Course::where('is_active', true)->get()->map(function (Course $course) {
            return [
                'id' => $course->id,
                'name' => $course->name,
                'type' => $course->type,
                'fee' => $course->fee,
                'downpayment' => $course->downpayment,
            ];
        });

        return response()->json(['courses' => $courses]);
    }

    public function show(Schedule $schedule)
    {
        $schedule->load(['course', 'instructor.user']);

        $bookings = StudentSchedule::with('student.user')
            ->where('schedule_id', $schedule->id)
            ->get()
            ->map(function (StudentSchedule $booking) {
                return [
                    'student_id' => $booking->student_id,
                    'student_name' => $booking->student->user->name,
                ];
            });

        $attendance = $schedule->attendance()->get()->map(function ($a) {
            return [
                'student_id' => $a->student_id,
                'status' => $a->status,
                'notes' => $a->notes,
                'recorded_at' => $a->recorded_at,
            ];
        });

        return response()->json([
            'schedule' => $schedule,
            'bookings' => $bookings,
            'attendance' => $attendance,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'instructor_id' => ['required', 'exists:instructors,id'],
            'date' => ['required', 'date'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i', 'after:time_start'],
            'venue' => ['nullable', 'string', 'max:255'],
            'max_students' => ['required', 'integer', 'min:1'],
        ]);

        // Academy operates Monday–Saturday only (no sessions on Sunday).
        if (Carbon::parse($data['date'])->dayOfWeek === Carbon::SUNDAY) {
            return response()->json([
                'message' => 'The academy is closed on Sundays. Please pick a Monday–Saturday date.',
            ], 422);
        }

        if ($this->hasConflict($data)) {
            return response()->json([
                'message' => 'This instructor already has a session at the same date/time.',
            ], 422);
        }

        $schedule = Schedule::create($data + ['status' => 'open']);

        $schedule->load(['course', 'instructor.user']);

        return response()->json(['schedule' => $schedule], 201);
    }

    public function cancel(Schedule $schedule)
    {
        $schedule->update(['status' => 'cancelled']);

        return response()->json(['schedule' => $schedule]);
    }

    protected function hasConflict(array $data): bool
    {
        return Schedule::where('instructor_id', $data['instructor_id'])
            ->where('date', $data['date'])
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($data) {
                $query->where(function ($q) use ($data) {
                    $q->where('time_start', '<', $data['time_end'])
                        ->where('time_end', '>', $data['time_start']);
                });
            })
            ->exists();
    }

    /* Admin oversight: every session with its instructor and attendance summary. */
    public function attendanceIndex()
    {
        $schedules = Schedule::with(['course', 'instructor.user'])
            ->where('status', '!=', 'cancelled')
            ->orderBy('date')
            ->get();

        $data = $schedules->map(function (Schedule $schedule) {
            $attendance = $schedule->attendance()->get();
            $counts = [
                'present' => $attendance->where('status', 'present')->count(),
                'absent' => $attendance->where('status', 'absent')->count(),
                'late' => $attendance->where('status', 'late')->count(),
                'recorded' => $attendance->count(),
                'students' => $schedule->studentSchedules()->where('status', 'confirmed')->count(),
            ];

            return [
                'id' => $schedule->id,
                'course' => ['name' => $schedule->course->name, 'type' => $schedule->course->type],
                'instructor' => [
                    'id' => $schedule->instructor->id,
                    'name' => $schedule->instructor->user->name,
                ],
                'date' => $schedule->date?->format('Y-m-d'),
                'time_start' => $schedule->time_start,
                'time_end' => $schedule->time_end,
                'venue' => $schedule->venue,
                'status' => $schedule->status,
                'attendance' => $counts,
            ];
        });

        return response()->json(['schedules' => $data]);
    }

    /* Admin oversight: full attendance records for one session, recorded by instructor. */
    public function attendanceShow(Schedule $schedule)
    {
        $schedule->load(['course', 'instructor.user']);

        $attendance = $schedule->attendance()->with('student.user')->get()->map(function ($a) {
            return [
                'student_id' => $a->student_id,
                'student_name' => $a->student->user->name ?? null,
                'status' => $a->status,
                'notes' => $a->notes,
                'recorded_at' => $a->recorded_at,
            ];
        });

        return response()->json([
            'schedule' => [
                'id' => $schedule->id,
                'course' => ['name' => $schedule->course->name],
                'instructor' => ['name' => $schedule->instructor->user->name],
                'date' => $schedule->date?->format('Y-m-d'),
                'time_start' => $schedule->time_start,
                'time_end' => $schedule->time_end,
                'venue' => $schedule->venue,
            ],
            'attendance' => $attendance,
        ]);
    }
}
