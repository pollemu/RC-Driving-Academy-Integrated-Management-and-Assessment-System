<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\StudentSchedule;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $instructor = $request->user()->instructor;

        $schedules = Schedule::with('course')
            ->where('instructor_id', $instructor->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('date')
            ->get()
            ->map(function (Schedule $schedule) {
                return [
                    'id' => $schedule->id,
                    'date' => $schedule->date?->format('Y-m-d'),
                    'course' => ['name' => $schedule->course->name],
                    'status' => $schedule->status,
                ];
            });

        return response()->json(['schedules' => $schedules]);
    }

    public function show(Request $request, Schedule $schedule)
    {
        $this->authorizeSchedule($request, $schedule);

        $bookings = StudentSchedule::with('student.user')
            ->where('schedule_id', $schedule->id)
            ->get()
            ->map(function (StudentSchedule $booking) {
                return [
                    'student_id' => $booking->student_id,
                    'student_name' => $booking->student->user->name,
                ];
            });

        $records = Attendance::where('schedule_id', $schedule->id)
            ->pluck('status', 'student_id')
            ->toArray();

        return response()->json([
            'schedule' => [
                'id' => $schedule->id,
                'date' => $schedule->date?->format('Y-m-d'),
                'time_start' => $schedule->time_start,
                'time_end' => $schedule->time_end,
                'venue' => $schedule->venue,
                'status' => $schedule->status,
                'course' => ['name' => $schedule->course->name],
            ],
            'bookings' => $bookings,
            'records' => $records,
        ]);
    }

    public function store(Request $request, Schedule $schedule)
    {
        $this->authorizeSchedule($request, $schedule);

        $statuses = $request->input('status', []);
        if (is_string($statuses)) {
            // Frontend may send a single student's status for a one-off mark.
            $statuses = $request->input('student_id')
                ? [$request->input('student_id') => $statuses]
                : [];
        }

        foreach ($statuses as $studentId => $status) {
            if (! in_array($status, ['present', 'absent', 'late'])) {
                continue;
            }
            Attendance::updateOrCreate(
                ['schedule_id' => $schedule->id, 'student_id' => $studentId],
                ['status' => $status, 'recorded_at' => now(), 'notes' => $request->input("notes.$studentId")]
            );
        }

        if ($request->boolean('mark_done')) {
            $schedule->update(['status' => 'done']);
        }

        return response()->json(['ok' => true]);
    }

    protected function authorizeSchedule(Request $request, Schedule $schedule): void
    {
        abort_if($schedule->instructor_id !== $request->user()->instructor->id, 403);
    }
}
