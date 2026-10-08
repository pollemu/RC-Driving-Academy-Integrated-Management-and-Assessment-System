<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentSchedule;
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
                'instructor_name' => optional($student->instructor)->user->name ?? null,
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
            'birthdate' => $data['birthdate'] ?? ($student->birthdate?->format('Y-m-d')),
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

    public function assignInstructor(Request $request, Student $student)
    {
        $data = $request->validate([
            'instructor_id' => ['nullable', 'exists:instructors,id'],
        ]);

        $student->update(['instructor_id' => $data['instructor_id']]);

        $student->load(['user', 'instructor.user']);

        return response()->json(['student' => $student]);
    }

    /* Bookings awaiting admin acceptance of the assigned instructor. */
    public function pendingBookings()
    {
        $bookings = StudentSchedule::with(['student.user', 'schedule.course', 'schedule.instructor.user'])
            ->where('status', 'pending')
            ->orderByDesc('booked_at')
            ->get()
            ->map(function (StudentSchedule $booking) {
                return [
                    'id' => $booking->id,
                    'booked_at' => $booking->booked_at?->format('Y-m-d H:i'),
                    'status' => $booking->status,
                    'student' => [
                        'id' => $booking->student->id,
                        'name' => $booking->student->user->name,
                        'email' => $booking->student->user->email,
                    ],
                    'schedule' => [
                        'id' => $booking->schedule->id,
                        'date' => $booking->schedule->date?->format('Y-m-d'),
                        'time_start' => $booking->schedule->time_start,
                        'time_end' => $booking->schedule->time_end,
                        'venue' => $booking->schedule->venue,
                        'course' => $booking->schedule->course->name,
                        'instructor' => [
                            'id' => $booking->schedule->instructor->id,
                            'name' => $booking->schedule->instructor->user->name,
                        ],
                    ],
                ];
            });

        return response()->json(['bookings' => $bookings]);
    }

    public function acceptBooking(Request $request, StudentSchedule $studentSchedule)
    {
        if ($studentSchedule->status !== 'pending') {
            return response()->json(['message' => 'This booking is not pending acceptance.'], 422);
        }

        $confirmedCount = $studentSchedule->schedule->studentSchedules()->where('status', 'confirmed')->count();
        if ($confirmedCount >= $studentSchedule->schedule->max_students) {
            return response()->json(['message' => 'This session is already full.'], 422);
        }

        $studentSchedule->update(['status' => 'confirmed']);

        $schedule = $studentSchedule->schedule;
        if ($schedule->isFull()) {
            $schedule->update(['status' => 'full']);
        }

        return response()->json(['booking' => $studentSchedule->load(['student.user', 'schedule'])]);
    }

    public function rejectBooking(Request $request, StudentSchedule $studentSchedule)
    {
        if ($studentSchedule->status !== 'pending') {
            return response()->json(['message' => 'This booking is not pending acceptance.'], 422);
        }

        $studentSchedule->delete();

        return response()->json(['ok' => true]);
    }
}
