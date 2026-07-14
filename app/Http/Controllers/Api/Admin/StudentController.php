<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function index()
    {
        $students = Student::with(['user', 'enrollments.course', 'payments'])->latest()->paginate(10);

        $data = $students->getCollection()->map(function (Student $student) {
            $types = $student->enrollments
                ->map(fn ($e) => optional($e->course)->type)
                ->filter()
                ->unique()
                ->values();

            $courseLabel = match (true) {
                $types->contains('TDC') && $types->contains('PDC') => 'TDC+PDC',
                $types->contains('TDC') => 'TDC',
                $types->contains('PDC') => 'PDC',
                default => '—',
            };

            $hasVerified = $student->payments->contains(fn ($p) => $p->status === 'verified');
            $hasPending  = $student->payments->isNotEmpty();
            $paymentStatus = $hasVerified ? 'Paid' : ($hasPending ? 'Pending' : '—');

            return [
                'id' => $student->id,
                'name' => $student->user->name,
                'email' => $student->user->email,
                'phone' => $student->phone,
                'license_type' => $student->license_type,
                'course' => $courseLabel,
                'payment_status' => $paymentStatus,
                'is_active' => $student->user->is_active,
                'created_at' => $student->user->created_at,
            ];
        });

        return response()->json([
            'students' => $data->values(),
            'pagination' => [
                'current_page' => $students->currentPage(),
                'last_page' => $students->lastPage(),
                'per_page' => $students->perPage(),
                'total' => $students->total(),
            ],
        ]);
    }

    public function show(Student $student)
    {
        $student->load(['user', 'enrollments.course', 'certifications.course']);

        return response()->json(['student' => $student]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'birthdate' => ['nullable', 'date'],
            'license_type' => ['nullable', 'in:non-pro,pro,student-permit'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'student',
            'is_active' => true,
        ]);

        $user->student()->save(new Student([
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'license_type' => $data['license_type'] ?? null,
        ]));

        $user->load('student');

        return response()->json(['student' => $user->student], 201);
    }

    public function update(Request $request, Student $student)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,' . $student->user_id],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'birthdate' => ['nullable', 'date'],
            'license_type' => ['nullable', 'in:non-pro,pro,student-permit'],
        ]);

        $student->update([
            'phone' => $data['phone'] ?? $student->phone,
            'address' => $data['address'] ?? $student->address,
            'birthdate' => $data['birthdate'] ?? $student->birthdate,
            'license_type' => $data['license_type'] ?? $student->license_type,
        ]);

        if (isset($data['name']) || isset($data['email'])) {
            $student->user->update([
                'name' => $data['name'] ?? $student->user->name,
                'email' => $data['email'] ?? $student->user->email,
            ]);
        }

        $student->load('user');

        return response()->json(['student' => $student]);
    }

    public function destroy(Student $student)
    {
        $student->user->delete();

        return response()->json(null, 204);
    }

    public function toggleActive(Student $student)
    {
        $student->user->update(['is_active' => ! $student->user->is_active]);

        $student->load('user');

        return response()->json(['student' => $student]);
    }
}
