<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CertificationController extends Controller
{
    public function index(): View
    {
        $certifications = Certification::with(['student.user', 'course', 'issuedBy'])
            ->latest()
            ->paginate(15);

        return view('admin.certifications', compact('certifications'));
    }

    public function create(): View
    {
        $students = Student::with('user')->get();

        return view('admin.certifications-create', compact('students'));
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

        return response()->json($courses);
    }

    public function store(Request $request): RedirectResponse
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
            return back()->withErrors(['course_id' => 'Student is not actively enrolled in this course.']);
        }

        $exists = Certification::where('student_id', $data['student_id'])
            ->where('course_id', $data['course_id'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['course_id' => 'Certificate already issued for this course.']);
        }

        Certification::create([
            'student_id' => $data['student_id'],
            'course_id' => $data['course_id'],
            'issued_by' => auth()->id(),
            'issued_at' => now(),
            'certificate_no' => $this->generateCertificateNo(),
            'status' => 'active',
        ]);

        return redirect()->route('admin.certifications.index')->with('status', 'Certificate issued.');
    }

    public function revoke(Certification $certification): RedirectResponse
    {
        $certification->update(['status' => 'revoked']);

        return back()->with('status', 'Certificate revoked.');
    }

    protected function generateCertificateNo(): string
    {
        $year = now()->year;
        $count = Certification::whereYear('issued_at', $year)->count() + 1;

        return 'RC-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
