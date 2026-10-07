<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAssessment;
use App\Models\Assessment;
use App\Models\Feedback;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentSchedule;
use App\Services\AssessmentSuggestion;
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

        $isTdc = $schedule->course->type === 'TDC';

        $rules = [
            'raw_text' => ['required', 'string', 'min:10'],
            'star_steering' => ['nullable', 'integer', 'between:1,5'],
            'star_speed' => ['nullable', 'integer', 'between:1,5'],
            'star_lane' => ['nullable', 'integer', 'between:1,5'],
            'star_parking' => ['nullable', 'integer', 'between:1,5'],
            'star_safety' => ['nullable', 'integer', 'between:1,5'],
            'star_mirror' => ['nullable', 'integer', 'between:1,5'],
            'note_steering' => ['nullable', 'string', 'max:120'],
            'note_speed' => ['nullable', 'string', 'max:120'],
            'note_lane' => ['nullable', 'string', 'max:120'],
            'note_parking' => ['nullable', 'string', 'max:120'],
            'note_safety' => ['nullable', 'string', 'max:120'],
            'note_mirror' => ['nullable', 'string', 'max:120'],
            'exam_score' => $isTdc ? ['required', 'numeric', 'between:0,100'] : ['nullable', 'numeric', 'between:0,100'],
        ];
        $data = $request->validate($rules);

        $feedback = Feedback::updateOrCreate(
            ['schedule_id' => $schedule->id, 'student_id' => $student->id],
            array_merge($data, ['instructor_id' => $request->user()->instructor->id])
        );

        $queued = false;
        if ($schedule->course->type === 'PDC') {
            ProcessAssessment::dispatch($feedback);
            $queued = true;
        }

        // TDC is classroom theory — graded by EXAM score only (no driving stars).
        // PDC is behind-the-wheel — graded by the driving star ratings (+optional exam).
        if ($isTdc) {
            $exam = $data['exam_score'] ?? null;
            $score = $exam;
            $passed = $exam !== null ? $exam >= 75 : null;
            $classification = $exam === null ? 'avg'
                : ($exam >= 85 ? 'fast' : ($exam >= 75 ? 'avg' : 'needs'));
            $stars = [];
        } else {
            $stars = [
                'steering' => $feedback->star_steering,
                'speed'    => $feedback->star_speed,
                'lane'     => $feedback->star_lane,
                'parking'  => $feedback->star_parking,
                'safety'   => $feedback->star_safety,
                'mirror'   => $feedback->star_mirror,
            ];
        }
        $notes = [
            'steering' => $feedback->note_steering,
            'speed'    => $feedback->note_speed,
            'lane'     => $feedback->note_lane,
            'parking'  => $feedback->note_parking,
            'safety'   => $feedback->note_safety,
            'mirror'   => $feedback->note_mirror,
        ];

        $built = AssessmentSuggestion::build($stars, $notes);

        // For PDC, classify from the driving stars; for TDC we already set
        // $classification from the exam score above.
        if (! $isTdc) {
            $classification = AssessmentSuggestion::classify($stars);
        }

        // Composite score + pass/fail.
        //  - TDC: exam score only (classroom theory, no driving stars).
        //  - PDC: practical star average (0-5 -> 0-100), blended 50/50 with
        //    exam if one was recorded. Passed when score >= 75.
        if ($isTdc) {
            $exam = $data['exam_score'] ?? null;
            $avg = null;
            $practical = null;
            $score = $exam;
        } else {
            $avg = collect($stars)->filter()->avg();
            $practical = $avg ? round($avg / 5 * 100, 2) : null;
            $exam = $data['exam_score'] ?? null;
            if ($practical !== null && $exam !== null) {
                $score = round(($practical + $exam) / 2, 2);
            } elseif ($practical !== null) {
                $score = $practical;
            } elseif ($exam !== null) {
                $score = $exam;
            } else {
                $score = null;
            }
        }
        $passed = $score !== null ? $score >= 75 : null;

        Assessment::updateOrCreate(
            ['feedback_id' => $feedback->id],
            [
                'classification'  => $classification,
                'confidence_score' => $classification === 'fast' ? 0.9
                                        : ($classification === 'needs' ? 0.7 : 0.8),
                'exam_score' => $exam,
                'score' => $score,
                'passed' => $passed,
                'recommendation' => $classification === 'fast'
                                        ? 'Ready for certification — keep up the good work.'
                                        : ($classification === 'needs'
                                            ? 'Mandatory remediation recommended before solo driving.'
                                            : 'Standard review recommended to reach consistent proficiency.'),
                'override_applied' => false,
                'suggestions' => $built['suggestions'],
            ]
        );

        // Mark the student's booking for this session as finished, and finish
        // the schedule itself once every booked student has been assessed.
        StudentSchedule::where('schedule_id', $schedule->id)
            ->where('student_id', $student->id)
            ->update(['status' => 'done']);

        $remaining = StudentSchedule::where('schedule_id', $schedule->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();
        if ($remaining === 0) {
            $schedule->update(['status' => 'done']);
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
