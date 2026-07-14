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

    public function verify(Payment $payment)
    {
        if ($payment->status === 'verified') {
            return response()->json(['payment' => $payment]);
        }

        $payment->update([
            'status' => 'verified',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        $enrollment = Enrollment::firstOrNew([
            'student_id' => $payment->student_id,
            'course_id' => $payment->course_id,
        ]);
        $enrollment->status = 'active';
        $enrollment->enrolled_at = $enrollment->enrolled_at ?? now();
        $enrollment->save();

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

        $payment->load(['student.user', 'course']);

        return response()->json(['payment' => $payment]);
    }
}
