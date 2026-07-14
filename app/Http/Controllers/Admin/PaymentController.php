<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->input('status', 'pending');

        $payments = Payment::with(['student.user', 'course'])
            ->when(in_array($status, ['pending', 'verified', 'rejected']), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15);

        return view('admin.payments', compact('payments', 'status'));
    }

    public function verify(Payment $payment): RedirectResponse
    {
        if ($payment->status === 'verified') {
            return back()->with('status', 'Already verified.');
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

        return back()->with('status', 'Payment verified and enrollment activated.');
    }

    public function reject(Payment $payment): RedirectResponse
    {
        $payment->update([
            'status' => 'rejected',
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);

        return back()->with('status', 'Payment rejected.');
    }
}
