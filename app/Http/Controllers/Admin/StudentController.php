<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function index(): View
    {
        $students = Student::with('user')->latest()->paginate(15);

        return view('admin.students', compact('students'));
    }

    public function show(Student $student): View
    {
        $student->load(['user', 'enrollments.course', 'payments.course', 'certifications.course']);

        return view('admin.students-show', compact('student'));
    }

    public function toggleActive(Student $student): RedirectResponse
    {
        $student->user->update(['is_active' => ! $student->user->is_active]);

        return back()->with('status', 'Student account updated.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $student->user->delete();

        return redirect()->route('admin.students.index')->with('status', 'Student deleted.');
    }
}
