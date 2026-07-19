<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status');

        $payments = Payment::with(['student.user', 'course'])
            ->when(in_array($status, ['pending', 'verified', 'rejected']), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(10);

        $data = $payments->getCollection()->map(function (Payment $payment) {
            return [
                'id' => $payment->id,
                'student_name' => $payment->student->user->name,
                'student_id' => $payment->student_id,
                'course' => ['name' => $payment->course->name],
                'amount' => $payment->amount,
                'method' => $payment->method,
                'reference_no' => $payment->reference_no,
                'status' => $payment->status,
                'created_at' => $payment->created_at,
            ];
        });

        return response()->json([
            'payments' => $data->values(),
            'pagination' => [
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
            ],
        ]);
    }

    /* Admin records a manual (cash / over-the-counter) payment for a student.
       This is the offline path used when PayMongo is unavailable: the admin
       takes the payment at the counter, the row is created already verified,
       and the linked enrollment is activated so the student can book. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
            'course_id' => ['required', 'exists:courses,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'method' => ['required', 'in:cash,bdo,gcash,otc'],
            'reference_no' => ['nullable', 'string', 'max:255'],
        ]);

        $payment = Payment::create([
            'student_id' => $data['student_id'],
            'course_id' => $data['course_id'],
            'amount' => $data['amount'],
            'method' => $data['method'],
            'reference_no' => $data['reference_no'] ?? ('CASH-' . time()),
            'status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        // markVerified() activates the enrollment and notifies the student.
        $payment->markVerified(auth()->id());

        $payment->load(['student.user', 'course']);

        return response()->json(['payment' => $payment], 201);
    }

    public function verify(Payment $payment)
    {
        if ($payment->status === 'verified') {
            return response()->json(['payment' => $payment]);
        }

        // markVerified() activates the enrollment and notifies the student.
        $payment->markVerified(auth()->id(), true);

        $payment->load(['student.user', 'course']);

        return response()->json(['payment' => $payment]);
    }

    public function reject(Payment $payment)
    {
        $payment->update([
            'status' => 'rejected',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        // Revert the linked enrollment to "pending" so scheduling re-locks and the
        // student can pay again. (PayMongo auto-verifies on success; a manual
        // reject means that payment did not go through.)
        $enrollment = Enrollment::firstOrNew([
            'student_id' => $payment->student_id,
            'course_id' => $payment->course_id,
        ]);
        if ($enrollment->exists && $enrollment->status === 'active') {
            $enrollment->status = 'pending';
            $enrollment->save();
        }

        $payment->load(['student.user', 'course']);

        return response()->json(['payment' => $payment]);
    }
}
