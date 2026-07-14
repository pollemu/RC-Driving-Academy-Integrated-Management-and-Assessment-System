<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;

class EnrollController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;
        $courses = Course::where('is_active', true)->get()->map(function (Course $course) {
            return [
                'id' => $course->id,
                'name' => $course->name,
                'type' => $course->type,
                'format' => $course->format,
                'fee' => $course->fee,
                'is_active' => $course->is_active,
            ];
        });

        $enrolled = $student->enrollments()->pluck('course_id')->toArray();

        return response()->json(['courses' => $courses, 'enrolled' => $enrolled]);
    }

    public function enroll(Request $request, Course $course)
    {
        $student = $request->user()->student;

        $enrollment = Enrollment::firstOrCreate(
            ['student_id' => $student->id, 'course_id' => $course->id],
            ['status' => 'pending', 'enrolled_at' => now()]
        );

        return response()->json([
            'enrollment' => [
                'id' => $enrollment->id,
                'course_id' => $enrollment->course_id,
                'status' => $enrollment->status,
            ],
        ]);
    }
}
