<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\StudentSchedule;
use Illuminate\View\View;

class ProgressController extends Controller
{
    public function index(): View
    {
        $student = auth()->user()->student;

        $enrollments = $student->enrollments()->with('course')->latest()->get();

        $bookings = StudentSchedule::with(['schedule.course', 'schedule.instructor.user'])
            ->where('student_id', $student->id)
            ->latest()
            ->get();

        $assessments = \App\Models\Assessment::whereHas('feedback', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->with('feedback.schedule.course')
            ->latest()
            ->get();

        $certifications = Certification::where('student_id', $student->id)
            ->with('course')
            ->get();

        return view('student.progress', compact('enrollments', 'bookings', 'assessments', 'certifications'));
    }
}
