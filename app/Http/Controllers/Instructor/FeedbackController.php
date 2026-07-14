<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAssessment;
use App\Models\Feedback;
use App\Models\Schedule;
use App\Models\StudentSchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(): View
    {
        $instructor = auth()->user()->instructor;

        $schedules = Schedule::with(['course'])
            ->where('instructor_id', $instructor->id)
            ->whereIn('status', ['done', 'open'])
            ->orderBy('date')
            ->get();

        return view('instructor.feedback', compact('schedules'));
    }

    public function create(Schedule $schedule, \App\Models\Student $student): View
    {
        $this->authorizeSchedule($schedule);

        abort_if(
            ! StudentSchedule::where('schedule_id', $schedule->id)->where('student_id', $student->id)->exists(),
            403,
            'Student is not booked in this session.'
        );

        $feedback = Feedback::where('schedule_id', $schedule->id)
            ->where('student_id', $student->id)
            ->first();

        return view('instructor.feedback-create', compact('schedule', 'student', 'feedback'));
    }

    public function store(Request $request, Schedule $schedule, \App\Models\Student $student): RedirectResponse
    {
        $this->authorizeSchedule($schedule);

        $data = $request->validate([
            'raw_text' => ['required', 'string', 'min:10'],
            'star_steering' => ['nullable', 'integer', 'between:1,5'],
            'star_speed' => ['nullable', 'integer', 'between:1,5'],
            'star_lane' => ['nullable', 'integer', 'between:1,5'],
            'star_parking' => ['nullable', 'integer', 'between:1,5'],
            'star_safety' => ['nullable', 'integer', 'between:1,5'],
            'star_mirror' => ['nullable', 'integer', 'between:1,5'],
        ]);

        $feedback = Feedback::updateOrCreate(
            ['schedule_id' => $schedule->id, 'student_id' => $student->id],
            array_merge($data, ['instructor_id' => auth()->user()->instructor->id])
        );

        if ($schedule->course->type === 'PDC') {
            ProcessAssessment::dispatch($feedback);
        }

        return redirect()->route('instructor.feedback.index')
            ->with('status', 'Feedback submitted.' . ($schedule->course->type === 'PDC' ? ' Assessment is being processed.' : ''));
    }

    protected function authorizeSchedule(Schedule $schedule): void
    {
        abort_if($schedule->instructor_id !== auth()->user()->instructor->id, 403);
    }
}
