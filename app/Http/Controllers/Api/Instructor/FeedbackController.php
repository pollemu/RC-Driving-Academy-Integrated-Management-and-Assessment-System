<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAssessment;
use App\Models\Feedback;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentSchedule;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    public function index(Request $request)
    {
        $instructor = $request->user()->instructor;

        $schedules = Schedule::with('course')
            ->where('instructor_id', $instructor->id)
            ->whereIn('status', ['done', 'open'])
            ->orderBy('date')
            ->get()
            ->map(function (Schedule $schedule) {
                $students = StudentSchedule::with('student.user')
                    ->where('schedule_id', $schedule->id)
                    ->get()
                    ->map(function (StudentSchedule $booking) {
                        return [
                            'student_id' => $booking->student_id,
                            'student_name' => $booking->student->user->name,
                        ];
                    });

                return [
                    'id' => $schedule->id,
                    'date' => $schedule->date,
                    'course' => [
                        'name' => $schedule->course->name,
                        'type' => $schedule->course->type,
                    ],
                    'students' => $students,
                ];
            });

        return response()->json(['schedules' => $schedules]);
    }

    public function store(Request $request, Schedule $schedule, Student $student)
    {
        $this->authorizeSchedule($request, $schedule);

        abort_if(
            ! StudentSchedule::where('schedule_id', $schedule->id)->where('student_id', $student->id)->exists(),
            403,
            'Student is not booked in this session.'
        );

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
            array_merge($data, ['instructor_id' => $request->user()->instructor->id])
        );

        $queued = false;
        if ($schedule->course->type === 'PDC') {
            ProcessAssessment::dispatch($feedback);
            $queued = true;
        }

        return response()->json([
            'feedback' => ['id' => $feedback->id],
            'queued' => $queued,
        ]);
    }

    protected function authorizeSchedule(Request $request, Schedule $schedule): void
    {
        abort_if($schedule->instructor_id !== $request->user()->instructor->id, 403);
    }
}
