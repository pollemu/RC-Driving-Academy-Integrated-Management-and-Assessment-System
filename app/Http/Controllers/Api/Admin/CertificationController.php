<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\Request;

class CertificationController extends Controller
{
    public function index()
    {
        $certifications = Certification::with(['student.user', 'course'])->latest()->get();

        $data = $certifications->map(function (Certification $certification) {
            return [
                'id' => $certification->id,
                'student_name' => $certification->student->user->name,
                'student_id' => $certification->student_id,
                'course' => ['name' => $certification->course->name],
                'certificate_no' => $certification->certificate_no,
                'status' => $certification->status,
                'issued_at' => $certification->issued_at,
            ];
        });

        return response()->json(['certifications' => $data]);
    }

    public function coursesFor(Student $student)
    {
        $active = $student->enrollments()
            ->whereIn('status', ['active', 'completed'])
            ->with('course')
            ->get();

        $already = $student->certifications()->pluck('course_id')->toArray();

        $courses = $active->map(function ($e) use ($already) {
            return [
                'course_id' => $e->course_id,
                'name' => $e->course->name,
                'eligible' => ! in_array($e->course_id, $already),
            ];
        });

        return response()->json(['courses' => $courses->values()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'course_id' => ['required', 'exists:courses,id'],
        ]);

        $enrollment = Enrollment::where('student_id', $data['student_id'])
            ->where('course_id', $data['course_id'])
            ->whereIn('status', ['active', 'completed'])
            ->exists();

        if (! $enrollment) {
            return response()->json(['message' => 'Student is not actively enrolled in this course.'], 422);
        }

        $exists = Certification::where('student_id', $data['student_id'])
            ->where('course_id', $data['course_id'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'Certificate already issued for this course.'], 422);
        }

        $certification = Certification::create([
            'student_id' => $data['student_id'],
            'course_id' => $data['course_id'],
            'issued_by' => auth()->id(),
            'issued_at' => now(),
            'certificate_no' => $this->generateCertificateNo(),
            'status' => 'active',
        ]);

        $certification->load(['student.user', 'course']);

        return response()->json(['certification' => $certification], 201);
    }

    public function revoke(Certification $certification)
    {
        $certification->update(['status' => 'revoked']);

        return response()->json(['certification' => $certification]);
    }

    protected function generateCertificateNo(): string
    {
        $year = now()->year;
        $count = Certification::whereYear('issued_at', $year)->count() + 1;

        return 'RC-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
