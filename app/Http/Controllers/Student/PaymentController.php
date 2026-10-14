<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\StudentSchedule;
use App\Services\PayMongoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function create(Enrollment $enrollment): View
    {
        $this->authorizeEnrollment($enrollment);
        $enrollment->load(['course', 'student.user']);

        return view('student.payment', compact('enrollment'));
    }

    public function store(Request $request, Enrollment $enrollment): RedirectResponse
    {
        $this->authorizeEnrollment($enrollment);

        $data = $request->validate([
            'method' => ['required', 'in:gcash,bdo'],
            'reference_no' => ['nullable', 'string', 'max:255'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $student = auth()->user()->student;
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

        if (! $manual && $paymongo->isConfigured()) {
            $session = $paymongo->createCheckoutSession(
                (int) round($course->fee * 100),
                'RC Driving Academy - ' . $course->name,
                $reference,
                route('student.dashboard'),
                route('student.payment.create', $enrollment)
            );

            if ($session) {
                $payment->paymongo_id = $session['id'];
                $payment->save();

                return redirect($session['url']);
            }
        }

        return redirect()->route('student.dashboard')
            ->with('status', 'Payment submitted for manual verification.');
    }

    /* A student may only touch their own enrollments. */
    private function authorizeEnrollment(Enrollment $enrollment): void
    {
        $studentId = auth()->user()?->student?->id;
        abort_unless($studentId && (int) $enrollment->student_id === (int) $studentId, 403, 'Not your enrollment.');
    }
}
