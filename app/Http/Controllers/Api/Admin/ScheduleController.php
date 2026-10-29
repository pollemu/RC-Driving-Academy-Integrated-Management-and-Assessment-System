<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Schedule;
use App\Models\StudentSchedule;
use App\Services\TdcScheduler;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleController extends Controller
{
    public function index()
    {
        // Keep the rolling TDC class calendar populated so admins always see
        // the upcoming blocks (even before an instructor is assigned).
        (new TdcScheduler())->ensureUpcoming();

        $schedules = Schedule::with(['course', 'instructor.user'])->latest('date')->get();

        $data = $schedules->map(function (Schedule $schedule) {
            return [
                'id' => $schedule->id,
                'course' => [
                    'id' => $schedule->course->id,
                    'name' => $schedule->course->name,
                    'type' => $schedule->course->type,
                ],
                'instructor' => $schedule->instructor ? [
                    'id' => $schedule->instructor->id,
                    'name' => $schedule->instructor->user?->name,
                ] : null,
                'date' => $schedule->date?->format('Y-m-d'),
                'end_date' => $schedule->end_date?->format('Y-m-d'),
                'time_start' => $schedule->time_start,
                'time_end' => $schedule->time_end,
                'venue' => $schedule->venue,
                'max_students' => $schedule->max_students,
                'booked' => $schedule->bookedCount(),
                'auto_generated' => $schedule->auto_generated,
                'status' => $schedule->status,
            ];
        });

        return response()->json(['schedules' => $data]);
    }

    public function students(Schedule $schedule)
    {
        $bookings = StudentSchedule::with(['student.user'])
            ->where('schedule_id', $schedule->id)
            ->get()
            ->map(function (StudentSchedule $booking) {
                $student = $booking->student;
                $courseName = '—';
                if ($student) {
                    $enrollment = \App\Models\Enrollment::with('course')
                        ->where('student_id', $student->id)
                        ->whereIn('status', ['active', 'completed'])
                        ->first();
                    if ($enrollment && $enrollment->course) $courseName = $enrollment->course->name;
                }
                return [
                    'student_id' => $booking->student_id,
                    'name' => $student && $student->user ? $student->user->name : '—',
                    'course' => $courseName,
                    'contact_number' => $student && $student->user ? ($student->phone ?: '—') : '—',
                    'status' => $booking->status ?: 'Booked',
                ];
            });

        return response()->json(['students' => $bookings]);
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
            'instructor_id' => ['nullable', 'exists:instructors,id'],
            'date' => ['required', 'date'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i', 'after:time_start'],
            'venue' => ['nullable', 'string', 'max:255'],
            'max_students' => ['required', 'integer', 'min:1'],
        ]);

        $course = Course::findOrFail($data['course_id']);
        $isTdc = $course->type === 'TDC';
        $start = Carbon::parse($data['date']);

        // Academy operates Monday–Saturday only (no sessions on Sunday).
        if ($start->dayOfWeek === Carbon::SUNDAY) {
            return response()->json([
                'message' => 'The academy is closed on Sundays. Please pick a Monday–Saturday date.',
            ], 422);
        }

        if ($isTdc) {
            // TDC runs in fixed 2-day blocks: Mon–Tue, Wed–Thu, Fri–Sat.
            if (! in_array($start->dayOfWeek, [Carbon::MONDAY, Carbon::WEDNESDAY, Carbon::FRIDAY], true)) {
                return response()->json([
                    'message' => 'TDC classes start on Monday, Wednesday or Friday (Mon–Tue, Wed–Thu, Fri–Sat).',
                ], 422);
            }
            $data['end_date'] = $start->copy()->addDay()->toDateString();
            $data['venue'] = ($data['venue'] ?? null) ?: ($course->format === 'Online'
                ? 'Online (Live Class)'
                : 'RC Driving Academy — Main Branch');
            // An instructor can be assigned later; the class is still bookable.
            $data['instructor_id'] = $data['instructor_id'] ?? null;

            // Blocks are generated ahead of time, so the same block may already
            // exist. Refuse an identical duplicate instead of showing students
            // two copies of the same class.
            $duplicate = Schedule::where('course_id', $course->id)
                ->where('status', '!=', 'cancelled')
                ->whereDate('date', $start->toDateString())
                ->where('time_start', $data['time_start'])
                ->first();

            if ($duplicate) {
                $booked = $duplicate->studentSchedules()
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->count();

                return response()->json([
                    'message' => 'This TDC block already exists: '
                        . $start->format('M j') . '–' . $start->copy()->addDay()->format('M j')
                        . ' (' . $booked . ' of ' . $duplicate->max_students . ' booked). '
                        . 'Cancel it first if you need to replace it, or pick a different time.',
                ], 422);
            }
        } else {
            if (empty($data['instructor_id'])) {
                return response()->json([
                    'message' => 'PDC is a 1-on-1 lesson — choose an instructor.',
                ], 422);
            }
            $data['end_date'] = null;
        }

        if (! empty($data['instructor_id']) && $this->hasConflict($data)) {
            return response()->json([
                'message' => 'This instructor already has a session at the same date/time.',
            ], 422);
        }

        $schedule = Schedule::create($data + ['status' => 'open', 'auto_generated' => false]);

        $schedule->load(['course', 'instructor.user']);

        return response()->json(['schedule' => $schedule], 201);
    }

    /* Assign (or clear) the instructor for a TDC class block after it was
       created. Students do not need this to book — it only enables attendance
       and assessments for the class. */
    public function assignInstructor(Request $request, Schedule $schedule)
    {
        $data = $request->validate([
            'instructor_id' => ['nullable', 'exists:instructors,id'],
        ]);

        $schedule->update(['instructor_id' => $data['instructor_id'] ?? null]);

        return response()->json(['schedule' => $schedule->load(['course', 'instructor.user'])]);
    }

    public function cancel(Schedule $schedule)
    {
        $schedule->update(['status' => 'cancelled']);

        return response()->json(['schedule' => $schedule]);
    }

    protected function hasConflict(array $data): bool
    {
        $start = Carbon::parse($data['date'])->toDateString();
        $end = Carbon::parse($data['end_date'] ?? $data['date'])->toDateString();

        return Schedule::where('instructor_id', $data['instructor_id'])
            ->where('status', '!=', 'cancelled')
            ->whereRaw('date <= ?', [$end])
            ->whereRaw('COALESCE(end_date, date) >= ?', [$start])
            ->where(function ($query) use ($data) {
                $query->where('time_start', '<', $data['time_end'])
                    ->where('time_end', '>', $data['time_start']);
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
                'instructor' => $schedule->instructor ? [
                    'id' => $schedule->instructor->id,
                    'name' => $schedule->instructor->user?->name,
                ] : null,
                'date' => $schedule->date?->format('Y-m-d'),
                'end_date' => $schedule->end_date?->format('Y-m-d'),
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
                'instructor' => ['name' => $schedule->instructor?->user?->name],
                'date' => $schedule->date?->format('Y-m-d'),
                'end_date' => $schedule->end_date?->format('Y-m-d'),
                'time_start' => $schedule->time_start,
                'time_end' => $schedule->time_end,
                'venue' => $schedule->venue,
            ],
            'attendance' => $attendance,
        ]);
    }
}
