<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Certification;
use App\Models\StudentSchedule;
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
                    'course' => ['name' => $s->course->name],
                    'date' => $s->date,
                    'time_start' => $s->time_start,
                    'time_end' => $s->time_end,
                    'instructor_name' => $s->instructor->user->name,
                ];
            });

        $assessments = Assessment::whereHas('feedback', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->with('feedback.schedule.course')
            ->latest()
            ->get()
            ->map(function (Assessment $a) {
                return [
                    'course_name' => $a->feedback->schedule->course->name,
                    'classification' => $a->classification,
                    'confidence_score' => $a->confidence_score,
                    'override_applied' => $a->override_applied,
                    'recommendation' => $a->recommendation,
                ];
            });

        $certifications = Certification::where('student_id', $student->id)
            ->with('course')
            ->get()
            ->map(function (Certification $c) {
                return [
                    'course' => ['name' => $c->course->name],
                    'certificate_no' => $c->certificate_no,
                    'status' => $c->status,
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
