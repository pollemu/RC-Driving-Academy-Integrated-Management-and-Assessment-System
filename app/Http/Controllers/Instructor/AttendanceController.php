<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\Schedule;
use App\Models\StudentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(): View
    {
        $instructor = auth()->user()->instructor;

        $schedules = Schedule::with(['course'])
            ->where('instructor_id', $instructor->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('date')
            ->paginate(15);

        return view('instructor.attendance', compact('schedules'));
    }

    public function show(Schedule $schedule): View
    {
        $this->authorizeSchedule($schedule);

        $bookings = StudentSchedule::with('student.user')
            ->where('schedule_id', $schedule->id)
            ->get();

        $records = Attendance::where('schedule_id', $schedule->id)
            ->pluck('status', 'student_id')
            ->toArray();

        return view('instructor.attendance-show', compact('schedule', 'bookings', 'records'));
    }

    public function store(Request $request, Schedule $schedule): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        $statuses = $request->input('status', []);

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

        return back()->with('status', 'Attendance saved.');
    }

    protected function authorizeSchedule(Schedule $schedule): void
    {
        abort_if($schedule->instructor_id !== auth()->user()->instructor->id, 403);
    }
}
