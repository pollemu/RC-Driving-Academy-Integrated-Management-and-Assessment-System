<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Http\Request;

class EnrollController extends Controller
{
    /* Has the student completed a TDC (an assessment exists for a TDC session)?
       PDC enrollment is locked until this is true. */
    protected function tdcCompleted(Request $request): bool
    {
        $studentId = $request->user()->student->id;

        return Assessment::whereHas('feedback', function ($q) use ($studentId) {
            $q->where('student_id', $studentId)
               ->whereHas('schedule.course', fn ($q) => $q->where('type', 'TDC'));
        })->exists();
    }

    public function index(Request $request)
    {
        $student = $request->user()->student;

        $enrollments = $student->enrollments()->get()->keyBy('course_id');
        $tdcCompleted = $this->tdcCompleted($request);
        $hasTdc = $enrollments->contains(fn ($e) => optional($e->course)->type === 'TDC');

        $courses = Course::where('is_active', true)->get()->map(function (Course $course) use ($enrollments) {
            $enr = $enrollments->get($course->id);
            return [
                'id' => $course->id,
                'name' => $course->name,
                'type' => $course->type,
                'format' => $course->format,
                'fee' => $course->fee,
                'downpayment' => $course->downpayment,
                'is_active' => $course->is_active,
                'enrolled' => (bool) $enr,
                'enrollment_status' => $enr ? $enr->status : null,
            ];
        });

        return response()->json([
            'courses' => $courses,
            'enrolled' => $enrollments->pluck('course_id')->toArray(),
            'tdc_completed' => $tdcCompleted,
            'has_tdc' => $hasTdc,
        ]);
    }

    /* Bulk "cart" enrollment. TDC is single-select (one of F2F/Online);
       PDC requires a completed TDC first. */
    public function cartEnroll(Request $request)
    {
        $data = $request->validate([
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ]);

        $student = $request->user()->student;
        $tdcCompleted = $this->tdcCompleted($request);

        $courses = Course::whereIn('id', $data['course_ids'])->get();

        $tdcCount = $courses->where('type', 'TDC')->count();
        $hasPdc = $courses->where('type', 'PDC')->count() > 0;

        if ($tdcCount > 1) {
            return response()->json(
                ['message' => 'You can only enroll in one TDC — choose either Face-to-Face or Online, not both.'],
                422
            );
        }

        if ($hasPdc && ! $tdcCompleted) {
            return response()->json(
                ['message' => 'Please complete your TDC first before enrolling in any PDC course.'],
                422
            );
        }

        // A non-active TDC (pending/rejected) may be changed to the other modality
        // before it is paid/activated. An active TDC stays locked to one course.
        $existingTdcEnr = $student->enrollments()
            ->whereHas('course', fn ($q) => $q->where('type', 'TDC'))
            ->first();

        if ($tdcCount > 0 && $existingTdcEnr) {
            if ($existingTdcEnr->status === 'active' || $existingTdcEnr->status === 'completed') {
                return response()->json(
                    ['message' => 'You are already enrolled in an active TDC course. Only one TDC is allowed.'],
                    422
                );
            }
            $selectedTdcId = $courses->where('type', 'TDC')->first()->id;
            if ($existingTdcEnr->course_id !== $selectedTdcId) {
                // Switching modality: drop the old non-active TDC enrollment and its
                // unpaid/rejected payments, then enroll the newly selected one.
                Payment::where('student_id', $student->id)
                    ->where('course_id', $existingTdcEnr->course_id)
                    ->whereIn('status', ['pending', 'rejected'])
                    ->delete();
                $existingTdcEnr->delete();
            }
        }

        $results = [];
        foreach ($courses as $course) {
            $enrollment = Enrollment::firstOrCreate(
                ['student_id' => $student->id, 'course_id' => $course->id],
                ['status' => 'pending', 'enrolled_at' => now()]
            );

            $results[] = [
                'course_id' => $course->id,
                'name' => $course->name,
                'required_amount' => (float) $course->downpayment,
                'status' => $enrollment->status,
            ];
        }

        return response()->json([
            'enrollments' => $results,
            'tdc_completed' => $tdcCompleted,
        ]);
    }
}
