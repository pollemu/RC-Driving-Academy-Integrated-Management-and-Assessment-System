<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
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
        $checkoutUrl = $paymongo->createPaymentIntentWithCheckout(
            (int) round($course->fee * 100),
            'RC Driving Academy - ' . $course->name,
            $reference,
            url('/student/dashboard'),
            url('/api/student/payments/' . $enrollment->id)
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

        $payload = ['payment' => ['id' => $payment->id, 'status' => $payment->status]];

        if ($checkoutUrl) {
            $payload['checkout_url'] = $checkoutUrl;
        }

        return response()->json($payload);
    }
}
