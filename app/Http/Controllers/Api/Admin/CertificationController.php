<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Certification;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Notifications\NotificationService;
use App\Services\CertGateService;
use Illuminate\Http\Request;

class CertificationController extends Controller
{
    public function index()
    {
        $certifications = Certification::with(['student.user', 'course', 'issuedBy'])->latest('issued_at')->latest('id')->get();

        $data = $certifications->map(fn (Certification $certification) => $this->payload($certification));

        return response()->json(['certifications' => $data]);
    }

    /* One certificate, with everything the admin UI needs to re-open and print
       it long after it was issued. */
    public function show(Certification $certification)
    {
        $certification->load(['student.user', 'course', 'issuedBy']);

        return response()->json(['certification' => $this->payload($certification)]);
    }

    protected function payload(Certification $certification): array
    {
        return [
            'id' => $certification->id,
            'student_id' => $certification->student_id,
            'student_name' => $certification->student?->user?->name,
            'course_id' => $certification->course_id,
            'course' => [
                'id' => $certification->course?->id,
                'name' => $certification->course?->name,
                'type' => $certification->course?->type,
                'format' => $certification->course?->format,
            ],
            'certificate_no' => $certification->certificate_no,
            'status' => $certification->status,
            'issued_at' => $certification->issued_at?->toIso8601String(),
            'issued_by' => $certification->issuedBy?->name,
        ];
    }

    public function coursesFor(Student $student)
    {
        $gate = new CertGateService();
        $active = $student->enrollments()
            ->whereIn('status', ['active', 'completed'])
            ->with('course')
            ->get();

        $already = $student->certifications()->pluck('course_id')->toArray();

        $courses = $active->map(function ($e) use ($already, $gate, $student) {
            $noCertYet = ! in_array($e->course_id, $already);
            $requirements = $gate->requirements($student, $e->course);
            $met = $noCertYet && $gate->isEligible($student, $e->course);

            return [
                'course_id' => $e->course_id,
                'name' => $e->course->name,
                'eligible' => $met,
                'requirements' => $noCertYet ? $requirements : [],
            ];
        });

        return response()->json(['courses' => $courses->values()]);
    }

    /* Students with a completed/active enrollment but no certificate yet
       for that course — i.e. certificates the admin can issue now. */
    public function ready()
    {
        $gate = new CertGateService();
        $eligible = Student::query()
            ->whereHas('enrollments', function ($q) {
                $q->whereIn('status', ['active', 'completed']);
            })
            ->with(['user', 'enrollments' => function ($q) {
                $q->whereIn('status', ['active', 'completed'])->with('course');
            }, 'certifications'])
            ->get()
            ->flatMap(function (Student $student) use ($gate) {
                $issued = $student->certifications->pluck('course_id')->toArray();

                return $student->enrollments
                    ->filter(function ($e) use ($issued) {
                        return ! in_array($e->course_id, $issued);
                    })
                    ->map(function ($e) use ($student, $gate) {
                        $requirements = $gate->requirements($student, $e->course);
                        $met = $gate->isEligible($student, $e->course);

                        return [
                            'student_id' => $student->id,
                            'student_name' => $student->user->name,
                            'course_id' => $e->course_id,
                            'course_name' => $e->course->name,
                            'course_type' => $e->course->type,
                            'eligible' => $met,
                            'requirements' => $requirements,
                        ];
                    });
            })
            ->values();

        return response()->json(['ready' => $eligible]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'force' => ['nullable', 'boolean'],
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

        // Enforce the certification gates (TDC pass / PDC sessions / safety /
        // full payment). An admin may bypass by sending force=true so a human
        // can override an edge case, but the missing requirements are surfaced.
        $student = Student::findOrFail($data['student_id']);
        $course = Course::findOrFail($data['course_id']);
        $gate = new CertGateService();
        $requirements = $gate->requirements($student, $course);

        if (! $gate->isEligible($student, $course) && empty($data['force'])) {
            return response()->json([
                'message' => 'Student does not yet meet all certification requirements.',
                'requirements' => $requirements,
            ], 422);
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

        try {
            (new NotificationService())->certificateIssued(
                $certification->student,
                $certification->course->name,
                $certification->certificate_no
            );
        } catch (\Throwable $e) {
            report($e);
        }

        try {
            AdminNotification::push(
                AdminNotification::CATEGORY_CERTIFICATE,
                'Certificate issued',
                $certification->certificate_no . ' — '
                    . ($certification->student->user?->name ?? 'A student')
                    . ' (' . $certification->course->name . ').',
                [
                    'certification_id' => $certification->id,
                    'student_id' => $certification->student_id,
                    'panel' => 'certifications',
                ],
                $certification->student->user_id
            );
        } catch (\Throwable $e) {
            report($e);
        }

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
