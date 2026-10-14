<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\PayMongoService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;

        $payments = Payment::with('course')
            ->where('student_id', $student->id)
            ->latest()
            ->get()
            ->map(function (Payment $payment) {
                return [
                    'id' => $payment->id,
                    'course' => ['name' => $payment->course->name],
                    'amount' => $payment->amount,
                    'method' => $payment->method,
                    'status' => $payment->status,
                    'created_at' => $payment->created_at,
                ];
            });

        return response()->json(['payments' => $payments]);
    }

    public function create(Request $request, Enrollment $enrollment)
    {
        $this->authorizeEnrollment($request, $enrollment);
        $enrollment->load(['course', 'student.user']);

        return response()->json([
            'enrollment' => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
            ],
            'course' => [
                'name' => $enrollment->course->name,
                'fee' => $enrollment->course->fee,
            ],
        ]);
    }

    public function store(Request $request, Enrollment $enrollment)
    {
        $this->authorizeEnrollment($request, $enrollment);

        $data = $request->validate([
            'method' => ['required', 'in:gcash,bdo'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $student = $request->user()->student;
        $course = $enrollment->course;

        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->store('receipts', 'public');
        }

        $paymongo = new PayMongoService();
        $reference = 'PAY-' . $enrollment->id . '-' . time();
        $manual = ! empty($data['reference_no']) || $request->hasFile('receipt');

        $payment = Payment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => $course->fee,
            'method' => $data['method'],
            'reference_no' => $data['reference_no'] ?? null,
            'receipt_path' => $receiptPath,
            'status' => 'pending',
        ]);

        $payload = ['payment' => ['id' => $payment->id, 'status' => $payment->status]];

        // Online checkout only for card/e-wallet payments with no manual proof.
        if (! $manual && $paymongo->isConfigured()) {
            $session = $paymongo->createCheckoutSession(
                (int) round($course->fee * 100),
                'RC Driving Academy - ' . $course->name,
                $reference,
                url('/student/dashboard'),
                url('/student/dashboard')
            );

            if ($session) {
                $payment->paymongo_id = $session['id'];
                $payment->save();

                $payload['checkout_url'] = $session['url'];
                $payload['session_id'] = $session['id'];
            } else {
                $payload['message'] = 'Online payment is unavailable. Please submit a reference number for manual verification.';
            }
        }

        return response()->json($payload);
    }

    /* Bulk "cart" payment. Creates one pending payment per enrolled (not-yet-active)
       course using that course's required downpayment, then opens a SINGLE PayMongo
       Checkout Session for the combined total. The student is redirected to PayMongo's
       hosted page (GCash / Card / OTC) — no manual reference entry. */
    public function cartStore(Request $request)
    {
        $data = $request->validate([
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'fake' => ['nullable', 'boolean'],
        ]);

        $student = $request->user()->student;

        /* DEMO ONLY: when 'fake' is true, skip PayMongo and mark the downpayments
           verified immediately so enrollments activate without a real gateway.
           Requires ALLOW_FAKE_PAYMENTS=true and is hard-disabled in production. */
        if (! empty($data['fake'])) {
            if (app()->environment('production') || ! config('app.allow_fake_payments')) {
                return response()->json(['message' => 'Demo payments are not available.'], 403);
            }
            $payments = [];
            foreach ($data['course_ids'] as $courseId) {
                $enrollment = $student->enrollments()->where('course_id', $courseId)->first();
                if (! $enrollment || $enrollment->status === 'active') {
                    continue;
                }
                $course = $enrollment->course;
                $payment = Payment::create([
                    'student_id' => $student->id,
                    'course_id' => $courseId,
                    'amount' => $course->downpayment,
                    'method' => 'demo',
                    'reference_no' => 'DEMO-' . $student->id . '-' . time(),
                    'status' => 'pending',
                ]);
                $payment->markVerified($request->user()->id, false);
                $payments[] = $payment;
            }

            if (empty($payments)) {
                return response()->json(['message' => 'No enrolled courses to bill. Enroll first.'], 422);
            }

            return response()->json([
                'demo' => true,
                'verified' => true,
                'payments' => collect($payments)->map(fn ($p) => [
                    'id' => $p->id, 'amount' => $p->amount, 'status' => $p->status,
                ]),
            ]);
        }

        $payments = [];
        foreach ($data['course_ids'] as $courseId) {
            $enrollment = $student->enrollments()->where('course_id', $courseId)->first();
            if (! $enrollment) {
                continue; // safety: only bill courses the student is actually enrolled in
            }
            if ($enrollment->status === 'active') {
                continue; // already paid / activated — nothing to bill
            }
            $course = $enrollment->course;
            $payment = Payment::firstOrCreate(
                ['student_id' => $student->id, 'course_id' => $courseId, 'status' => 'pending'],
                [
                    'amount' => $course->downpayment,
                    'method' => 'paymongo',
                    'reference_no' => 'PAY-' . $student->id . '-' . time(),
                    'status' => 'pending',
                ]
            );
            $payments[] = $payment;
        }

        if (empty($payments)) {
            return response()->json(['message' => 'No enrolled courses to bill. Enroll first.'], 422);
        }

        $total = collect($payments)->sum('amount');

        $origin = $request->getSchemeAndHttpHost();
        $success = $origin . '/student/dashboard.html';
        $cancel  = $origin . '/student/dashboard.html';
        $reference = 'RC-' . $student->id . '-' . time();

        $session = (new PayMongoService())->createCheckoutSession(
            (int) round($total * 100),
            'RC Driving Academy - Enrollment',
            $reference,
            $success,
            $cancel
        );

        if (! $session || empty($session['url'])) {
            return response()->json(['message' => 'Could not start PayMongo checkout. Please try again.'], 502);
        }

        foreach ($payments as $payment) {
            $payment->paymongo_id = $session['id'];
            $payment->save();
        }

        return response()->json([
            'checkout_url' => $session['url'],
            'session_id'   => $session['id'],
            'payments' => collect($payments)->map(fn ($p) => [
                'id' => $p->id, 'amount' => $p->amount, 'status' => $p->status,
            ]),
            'total' => $total,
        ]);
    }

    /* Called by the SPA after PayMongo redirects back (success_url). Reads the
       checkout session from PayMongo and, if paid, auto-verifies + activates. */
    public function returnVerify(Request $request)
    {
        $sessionId = $request->query('session')
            ?? $request->query('checkout_session_id')
            ?? $request->query('payment_id');

        if (! $sessionId) {
            return response()->json(['verified' => false, 'message' => 'Missing checkout session.']);
        }

        $student = $request->user()->student;

        $payments = Payment::where('student_id', $student->id)
            ->where('paymongo_id', $sessionId)
            ->where('status', 'pending')
            ->get();

        if ($payments->isEmpty()) {
            return response()->json([
                'verified' => true,
                'already' => true,
                'message' => 'No pending payments for this session.',
            ]);
        }

        $info = (new PayMongoService())->getCheckoutSession($sessionId);
        if (! $info || empty($info['paid'])) {
            return response()->json([
                'verified' => false,
                'message' => 'PayMongo has not confirmed this payment yet.',
            ]);
        }

        // Never activate on a partial or tampered amount.
        $expected = (int) round($payments->sum('amount') * 100);
        if ($info['paid_amount_centavos'] > 0 && $info['paid_amount_centavos'] < $expected) {
            return response()->json([
                'verified' => false,
                'message' => 'The amount paid does not match the amount due. Please contact the academy.',
            ]);
        }

        foreach ($payments as $payment) {
            $payment->markVerified($request->user()->id);
        }

        return response()->json(['verified' => true, 'payments' => $payments->count()]);
    }

    /* A student may only touch their own enrollments. */
    private function authorizeEnrollment(Request $request, Enrollment $enrollment): void
    {
        $studentId = $request->user()->student?->id;
        abort_unless($studentId && (int) $enrollment->student_id === (int) $studentId, 403, 'Not your enrollment.');
    }
}
