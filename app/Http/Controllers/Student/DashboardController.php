<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\StudentSchedule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $student = auth()->user()->student;
        $student->load(['enrollments.course', 'certifications.course']);

        $bookings = StudentSchedule::with(['schedule.course', 'schedule.instructor.user'])
            ->where('student_id', $student->id)
            ->latest()
            ->get();

        return view('student.dashboard', compact('student', 'bookings'));
    }
}
