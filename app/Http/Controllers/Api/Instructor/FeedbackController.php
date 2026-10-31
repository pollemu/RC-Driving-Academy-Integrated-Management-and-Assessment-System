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
            ->whereIn('status', ['done', 'completed', 'open'])
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
                    'date' => $schedule->date?->format('Y-m-d'),
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

        // PDC always rates the three practical skills. TDC is exam-only, so
        // those fields are simply absent and stay nullable.
        $starRule = $isTdc ? 'nullable' : 'required';
        $rules = [
            'raw_text' => ['required', 'string', 'min:10'],
            'star_steering' => [$starRule, 'integer', 'between:1,5'],
            'star_brake' => [$starRule, 'integer', 'between:1,5'],
            'star_mirror' => [$starRule, 'integer', 'between:1,5'],
            'note_steering' => ['nullable', 'string', 'max:120'],
            'note_brake' => ['nullable', 'string', 'max:120'],
            'note_mirror' => ['nullable', 'string', 'max:120'],
            'exam_score' => $isTdc ? ['required', 'numeric', 'between:0,100'] : ['nullable', 'numeric', 'between:0,100'],
        ];
        $data = $request->validate($rules);

        $feedback = Feedback::updateOrCreate(
            ['schedule_id' => $schedule->id, 'student_id' => $student->id],
            array_merge($data, ['instructor_id' => $request->user()->instructor->id])
        );

        $queued = false;
        $exam = $data['exam_score'] ?? null;

        // TDC is classroom theory — graded by EXAM score only (no driving stars).
        // PDC is behind-the-wheel — graded by steering / braking / side mirror
        // star ratings plus the instructor's written feedback.
        if ($isTdc) {
            $stars = [];
            $notes = [];
            $score = $exam;
            $classification = $exam === null ? 'average'
                : ($exam >= 85 ? 'fast' : ($exam >= 75 ? 'average' : 'needs'));
            $built = ['failed' => [], 'suggestions' => []];
        } else {
            $stars = [
                'steering' => $feedback->star_steering,
                'brake'    => $feedback->star_brake,
                'mirror'   => $feedback->star_mirror,
            ];
            $notes = [
                'steering' => $feedback->note_steering,
                'brake'    => $feedback->note_brake,
                'mirror'   => $feedback->note_mirror,
            ];

            $built = AssessmentSuggestion::build($stars, $notes);
            $classification = AssessmentSuggestion::classify($stars);

            // Practical star average (0-5 -> 0-100), blended 50/50 with an
            // optional exam score. Passed when score >= 75.
            $avg = collect($stars)->filter()->avg();
            $practical = $avg ? round($avg / 5 * 100, 2) : null;
            if ($practical !== null && $exam !== null) {
                $score = round(($practical + $exam) / 2, 2);
            } elseif ($practical !== null) {
                $score = $practical;
            } else {
                $score = $exam;
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

        // Enrich with the NLP model *after* the rule-based assessment exists,
        // otherwise a synchronous queue would see no assessment to enrich.
        if (! $isTdc) {
            ProcessAssessment::dispatch($feedback);
            $queued = true;
        }

        $final = Assessment::where('feedback_id', $feedback->id)->first();

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
            'assessment' => [
                'classification' => $final?->classification ?? $classification,
                'confidence_score' => $final?->confidence_score,
                'score' => $final?->score ?? $score,
                'passed' => $final?->passed ?? $passed,
                'course_type' => $schedule->course->type,
            ],
            'queued' => $queued,
        ]);
    }

    protected function authorizeSchedule(Request $request, Schedule $schedule): void
    {
        abort_if($schedule->instructor_id !== $request->user()->instructor->id, 403);
    }
}
