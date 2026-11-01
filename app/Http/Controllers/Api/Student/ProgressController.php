<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Certification;
use App\Models\StudentSchedule;
use App\Services\AssessmentSuggestion;
use Illuminate\Http\Request;

class ProgressController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;

        $enrollments = $student->enrollments()->with('course')->latest()->get()->map(function ($e) {
            return [
                'id' => $e->id,
                'course' => [
                    'name' => $e->course->name,
                    'type' => $e->course->type,
                    'fee' => $e->course->fee,
                ],
                'status' => $e->status,
                'enrolled_at' => $e->enrolled_at,
            ];
        });

        $bookings = StudentSchedule::with(['schedule.course', 'schedule.instructor.user'])
            ->where('student_id', $student->id)
            ->latest()
            ->get()
            ->map(function (StudentSchedule $booking) {
                $s = $booking->schedule;

                return [
                    'id' => $booking->id,
                    'course' => ['name' => $s->course->name, 'type' => $s->course->type],
                    'date' => $s->date?->format('Y-m-d'),
                    'end_date' => $s->end_date?->format('Y-m-d'),
                    'time_start' => $s->time_start,
                    'time_end' => $s->time_end,
                    'instructor_name' => $s->instructor?->user?->name,
                ];
            });

        $assessments = Assessment::whereHas('feedback', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->with('feedback.schedule.course')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (Assessment $a) {
                $f = $a->feedback;
                // Use AssessmentSuggestion's five PDC categories so the
                // student's results always mirror what the instructor rated.
                $categories = collect(AssessmentSuggestion::CATEGORIES)
                    ->map(fn ($def, $key) => [
                        'key'   => $key,
                        'label' => $def[0],
                        'score' => $f->{"star_{$key}"},
                        'note'  => $f->{"note_{$key}"},
                    ])
                    ->values()
                    ->all();

                return [
                    'feedback_id'    => $a->feedback_id,
                    'course_name'    => $f->schedule->course->name,
                    'course_type'    => $f->schedule->course->type,
                    'date'           => $f->schedule->date?->format('Y-m-d'),
                    'assessed_at'    => $a->updated_at?->toIso8601String(),
                    'classification'  => $a->classification,
                    'confidence_score' => $a->confidence_score,
                    'exam_score'     => $a->exam_score,
                    'score'          => $a->score,
                    'passed'         => $a->passed,
                    'override_applied' => $a->override_applied,
                    'recommendation' => $a->recommendation,
                    'feedback'       => $f->raw_text,
                    'categories'     => $categories,
                    'suggestions'    => $a->suggestions ?? [],
                ];
            });

        $certifications = Certification::where('student_id', $student->id)
            ->with(['course', 'issuedBy'])
            ->latest('issued_at')
            ->latest('id')
            ->get()
            ->map(function (Certification $c) use ($student) {
                return [
                    'id' => $c->id,
                    'student_name' => $student->user?->name,
                    'course' => [
                        'name' => $c->course?->name,
                        'type' => $c->course?->type,
                    ],
                    'certificate_no' => $c->certificate_no,
                    'status' => $c->status,
                    'issued_at' => $c->issued_at?->toIso8601String(),
                    'issued_by' => $c->issuedBy?->name,
                ];
            });

        return response()->json([
            'enrollments' => $enrollments,
            'bookings' => $bookings,
            'assessments' => $assessments,
            'certifications' => $certifications,
        ]);
    }
}
