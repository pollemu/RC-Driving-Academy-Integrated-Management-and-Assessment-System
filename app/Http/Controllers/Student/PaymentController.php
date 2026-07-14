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
        $enrollment->load(['course', 'student.user']);

        return view('student.payment', compact('enrollment'));
    }

    public function store(Request $request, Enrollment $enrollment): RedirectResponse
    {
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
        $checkoutUrl = $paymongo->createPaymentIntentWithCheckout(
            (int) round($course->fee * 100),
            'RC Driving Academy - ' . $course->name,
            $reference,
            route('student.dashboard'),
            route('student.payment.create', $enrollment)
        );

        $payment = Payment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'amount' => $course->fee,
            'method' => $data['method'],
            'reference_no' => $data['reference_no'],
            'receipt_path' => $receiptPath,
            'paymongo_id' => $checkoutUrl ? $reference : null,
            'status' => 'pending',
        ]);

        if ($checkoutUrl) {
            return redirect($checkoutUrl);
        }

        return redirect()->route('student.dashboard')
            ->with('status', 'Payment submitted for manual verification (PayMongo not configured).');
    }
}
