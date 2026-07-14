<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EnrollController extends Controller
{
    public function index(): View
    {
        $student = auth()->user()->student;
        $courses = Course::where('is_active', true)->get();
        $enrolled = $student->enrollments()->pluck('course_id')->toArray();

        return view('student.enroll', compact('courses', 'enrolled'));
    }

    public function enroll(Course $course): RedirectResponse
    {
        $student = auth()->user()->student;

        $enrollment = Enrollment::firstOrCreate(
            ['student_id' => $student->id, 'course_id' => $course->id],
            ['status' => 'pending', 'enrolled_at' => now()]
        );

        return redirect()->route('student.payment.create', $enrollment)
            ->with('status', 'Proceed to payment to activate your enrollment.');
    }
}
