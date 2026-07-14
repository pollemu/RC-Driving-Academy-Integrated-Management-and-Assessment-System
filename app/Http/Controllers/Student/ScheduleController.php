<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\StudentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(): View
    {
        $student = auth()->user()->student;

        $activeCourseIds = $student->enrollments()
            ->where('status', 'active')
            ->pluck('course_id')
            ->toArray();

        $schedules = Schedule::with(['course', 'instructor.user'])
            ->whereIn('course_id', $activeCourseIds)
            ->where('status', 'open')
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->paginate(15);

        $bookedIds = $student->studentSchedules()->pluck('schedule_id')->toArray();

        return view('student.schedule', compact('schedules', 'bookedIds', 'activeCourseIds'));
    }

    public function book(Schedule $schedule): RedirectResponse
    {
        $student = auth()->user()->student;

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_id', $schedule->course_id)
            ->where('status', 'active')
            ->exists();

        if (! $enrollment) {
            return back()->withErrors(['book' => 'Payment must be verified before booking.']);
        }

        if (in_array($schedule->id, $student->studentSchedules()->pluck('schedule_id')->toArray())) {
            return back()->withErrors(['book' => 'You have already booked this session.']);
        }

        if ($schedule->isFull()) {
            return back()->withErrors(['book' => 'This session is already full.']);
        }

        StudentSchedule::create([
            'student_id' => $student->id,
            'schedule_id' => $schedule->id,
            'booked_at' => now(),
        ]);

        if ($schedule->isFull()) {
            $schedule->update(['status' => 'full']);
        }

        return back()->with('status', 'Session booked successfully.');
    }
}
