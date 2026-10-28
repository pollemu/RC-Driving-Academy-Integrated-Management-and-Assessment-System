<?php

namespace App\Models;

use App\Models\AdminNotification;
use App\Notifications\NotificationService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['student_id', 'course_id', 'amount', 'method', 'reference_no', 'paymongo_id', 'receipt_path', 'status', 'verified_by', 'verified_at'])]
class Payment extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'verified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /* Mark this payment verified and activate the linked enrollment once the
       verified amount covers the course's required downpayment. */
    public function markVerified(?int $by = null, bool $notify = true): void
    {
        $this->update([
            'status' => 'verified',
            'verified_by' => $by,
            'verified_at' => now(),
        ]);

        $enrollment = Enrollment::firstOrNew([
            'student_id' => $this->student_id,
            'course_id' => $this->course_id,
        ]);

        $course = $this->course;
        if ($course && $this->amount >= $course->downpayment && $enrollment->status !== 'completed') {
            $enrollment->status = 'active';
        }
        $enrollment->enrolled_at = $enrollment->enrolled_at ?? now();
        $enrollment->save();

        // Notify the student. The admin manual "record payment" path creates the
        // row already verified, so it relies on notify=true to fire once.
        if ($notify) {
            $student = $this->student;
            $courseName = $this->course?->name ?? 'course';

            try {
                (new NotificationService())->paymentVerified(
                    $student,
                    $courseName,
                    (float) $this->amount
                );
            } catch (\Throwable $e) {
                // Notifications must never break payment verification.
                report($e);
            }

            try {
                AdminNotification::record(
                    AdminNotification::CATEGORY_PAYMENT,
                    'Payment verified',
                    ($student?->user?->name ?? 'A student')
                        . ' paid ₱' . number_format((float) $this->amount, 2)
                        . " for {$courseName}.",
                    [
                        'payment_id' => $this->id,
                        'student_id' => $this->student_id,
                        'panel' => 'payments',
                    ],
                    $student?->user_id
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
