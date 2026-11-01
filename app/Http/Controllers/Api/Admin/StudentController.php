<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentSchedule;
use App\Models\User;
use App\Services\AssessmentSuggestion;
use App\Support\Phone;
use App\Support\ValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        /* Server-side search: applies across ALL pages of the list, unlike
           the old client-only filter that hid rows on the current page. */
        $search = trim((string) $request->query('search', ''));

        $query = Student::with(['user', 'enrollments.course', 'payments'])
            ->when($search !== '', function ($q) use ($search) {
                $like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
                $q->where(function ($q) use ($like) {
                    $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$like}%")
                            ->orWhere('email', 'like', "%{$like}%"))
                        ->orWhere('phone', 'like', "%{$like}%")
                        ->orWhereHas('enrollments.course', fn ($c) => $c->where('name', 'like', "%{$like}%")
                                ->orWhere('type', 'like', "%{$like}%"));
                });
            })
            ->latest()
            ->paginate(10);

        $data = $query->getCollection()->map(function (Student $student) {
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

            return [
                'id' => $student->id,
                'user_id' => $student->user_id,
                'name' => $student->user->name,
                'email' => $student->user->email,
                'course' => $courseLabel,
                'joined_at' => $student->user->created_at?->toIso8601String(),
                'is_active' => $student->user->is_active,
            ];
        });

        return response()->json([
            'students' => $data->values(),
            'pagination' => [
                'current_page' => $query->currentPage(),
                'last_page' => $query->lastPage(),
                'per_page' => $query->perPage(),
                'total' => $query->total(),
            ],
        ]);
    }

    /* Everything the admin's "View" modal can show: contact details,
       payment, age, preferred/assigned instructor, TDC + PDC progress
       and the full assessment history (scores readable when assessed). */
    public function show(Student $student)
    {
        $student->load(['user', 'instructor.user']);

        $user = $student->user;

        $enrollments = $student->enrollments()->with('course')->latest()->get();

        $types = $enrollments->map(fn ($e) => optional($e->course)->type)->filter()->unique()->values();
        $courseLabel = match (true) {
            $types->contains('TDC') && $types->contains('PDC') => 'TDC+PDC',
            $types->contains('TDC') => 'TDC',
            $types->contains('PDC') => 'PDC',
            default => '—',
        };

        // Payments: totals and simple status for badges.
        $payments = $student->payments()->orderByDesc('id')->get(['id', 'amount', 'method', 'status', 'paid_at', 'verified_at']);
        $paidTotal = (float) $student->payments->where('status', 'verified')->sum('amount');
        $hasVerified = $student->payments->contains(fn ($p) => $p->status === 'verified');
        $paymentStatus = $hasVerified ? 'Paid' : ($student->payments->isNotEmpty() ? 'Pending' : '—');

        // Assessments (works for both TDC exam grading and PDC star grading).
        $assessments = Assessment::whereHas('feedback', fn ($q) => $q->where('student_id', $student->id))
            ->with('feedback.schedule.course')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (Assessment $a) {
                $f = $a->feedback;
                $categories = collect(AssessmentSuggestion::CATEGORIES)
                    ->map(fn ($def, $key) => [
                        'key' => $key,
                        'label' => $def[0],
                        'score' => $f->{"star_{$key}"},
                        'note' => $f->{"note_{$key}"},
                    ])
                    ->values()
                    ->all();

                return [
                    'course_name' => $f->schedule->course->name ?? '—',
                    'course_type' => $f->schedule->course->type ?? '—',
                    'date' => $f->schedule->date?->format('Y-m-d'),
                    'classification' => $a->classification,
                    'score' => $a->score,
                    'exam_score' => $a->exam_score,
                    'passed' => $a->passed,
                    'confidence_score' => $a->confidence_score,
                    'recommendation' => $a->recommendation,
                    'feedback' => $f->raw_text,
                    'categories' => $categories,
                ];
            });

        // TDC progress: one 2-day class block + a passing assessment (75+),
        // or an externally verified TDC.
        $tdcBooked = $student->studentSchedules()
            ->whereHas('schedule.course', fn ($q) => $q->where('type', 'TDC'))
            ->whereIn('status', ['pending', 'confirmed'])
            ->count();
        $tdcAssessment = $assessments->firstWhere('course_type', 'TDC');

        // PDC progress: booked 1-on-1 sessions + assessment history.
        $pdcBooked = $student->studentSchedules()
            ->whereHas('schedule.course', fn ($q) => $q->where('type', 'PDC'))
            ->whereIn('status', ['pending', 'confirmed', 'done'])
            ->count();
        $pdcAssessments = $assessments->where('course_type', 'PDC')->values();

        return response()->json([
            'student' => $student,
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $student->phone,
                'address' => $student->address,
                'gender' => $student->gender,
                'birthdate' => $student->birthdate?->format('Y-m-d'),
                'age' => $student->birthdate?->age,
                'license_type' => $student->license_type,
                'joined_at' => $user->created_at?->toIso8601String(),
                'is_active' => $user->is_active,
                'course' => $courseLabel,
                'preferred_instructor_gender' => $student->preferred_instructor_gender,
                'instructor_name' => optional($student->instructor)->user->name,
                'kyc_status' => $user->kyc_status,
                'payment_status' => $paymentStatus,
                'payment_paid_total' => $paidTotal,
                'tdc_verified' => (bool) $student->tdc_verified,
                'tdc' => [
                    'enrolled' => $types->contains('TDC'),
                    'booked_blocks' => $tdcBooked,
                    'assessment' => $tdcAssessment ? [
                        'exam_score' => $tdcAssessment['exam_score'],
                        'passed' => $tdcAssessment['passed'],
                        'date' => $tdcAssessment['date'],
                    ] : null,
                ],
                'pdc' => [
                    'enrolled' => $types->contains('PDC'),
                    'booked_sessions' => $pdcBooked,
                    'assessed_sessions' => $pdcAssessments->count(),
                    'latest_classification' => $pdcAssessments->first()['classification'] ?? null,
                    'latest_score' => $pdcAssessments->first()['score'] ?? null,
                ],
            ],
            'assessments' => $assessments,
            'payments' => $payments->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'method' => $p->method,
                'status' => $p->status,
                'date' => ($p->paid_at ?? $p->verified_at ?? $p->created_at)?->toIso8601String(),
            ])->all(),
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

        $data = $request->validate([
            'name' => ValidationRules::NAME,
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'phone' => ValidationRules::PHONE,
            'address' => ['nullable', 'string', 'max:500'],
            'birthdate' => ['nullable', 'date', 'before:today'],
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
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

        $data = $request->validate([
            'name' => ValidationRules::NAME,
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,' . $student->user_id],
            'phone' => ValidationRules::PHONE,
            'address' => ['nullable', 'string', 'max:500'],
            'birthdate' => ['nullable', 'date', 'before:today'],
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
                        'end_date' => $booking->schedule->end_date?->format('Y-m-d'),
                        'time_start' => $booking->schedule->time_start,
                        'time_end' => $booking->schedule->time_end,
                        'venue' => $booking->schedule->venue,
                        'course' => $booking->schedule->course->name,
                        'instructor' => $booking->schedule->instructor ? [
                            'id' => $booking->schedule->instructor->id,
                            'name' => $booking->schedule->instructor->user?->name,
                        ] : null,
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
