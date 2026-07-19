<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentSchedule;

/**
 * Evaluates whether a student is eligible to receive a certificate for a
 * given course. The base rule (an active/completed enrollment with no cert
 * yet) lives in the controller; this service layers on the richer gates the
 * original spec described for PDC:
 *
 *   1. Minimum completed PDC sessions (configurable, default 8).
 *   2. No unresolved safety flag (any PDC assessment with an override/safety
 *      keyword must be cleared — i.e. not currently "needs").
 *   3. Full payment settled (verified payments >= course fee).
 *
 * TDC only requires a passing TDC assessment. Gates are surfaced to the admin
 * UI via requirements(), and the threshold is adjustable through `settings`.
 */
class CertGateService
{
    public const PDC_REQUIRED_SESSIONS_KEY = 'pdc.required_sessions';
    public const REQUIRE_FULL_PAYMENT_KEY = 'cert.require_full_payment';
    public const REQUIRE_SAFETY_CLEAR_KEY = 'cert.require_safety_clear';

    public function pdcRequiredSessions(): int
    {
        return (int) Setting::get(self::PDC_REQUIRED_SESSIONS_KEY, 8);
    }

    public function requireFullPayment(): bool
    {
        return (bool) Setting::get(self::REQUIRE_FULL_PAYMENT_KEY, true);
    }

    public function requireSafetyClear(): bool
    {
        return (bool) Setting::get(self::REQUIRE_SAFETY_CLEAR_KEY, true);
    }

    /**
     * Returns a list of {label, met} requirements for a student/course pair so
     * the admin UI can show exactly why a certificate is or isn't ready.
     */
    public function requirements(Student $student, Course $course): array
    {
        $requirements = [];

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['active', 'completed'])
            ->first();

        $requirements[] = [
            'label' => 'Active enrollment',
            'met' => (bool) $enrollment,
        ];

        if ($course->type === 'TDC') {
            $passed = Assessment::whereHas('feedback', function ($q) use ($student) {
                $q->where('student_id', $student->id)
                  ->whereHas('schedule.course', fn ($q) => $q->where('type', 'TDC'));
            })->where('passed', true)->exists();

            $requirements[] = [
                'label' => 'TDC assessment passed',
                'met' => $passed,
            ];

            return $requirements;
        }

        // PDC-specific gates.
        $required = $this->pdcRequiredSessions();
        $completed = StudentSchedule::where('student_id', $student->id)
            ->whereHas('schedule.course', fn ($q) => $q->where('type', 'PDC'))
            ->whereHas('schedule', fn ($q) => $q->where('status', 'done'))
            ->where('status', 'confirmed')
            ->count();

        $requirements[] = [
            'label' => "Completed PDC sessions ({$completed}/{$required})",
            'met' => $completed >= $required,
        ];

        if ($this->requireSafetyClear()) {
            $flagged = Assessment::whereHas('feedback', function ($q) use ($student) {
                $q->where('student_id', $student->id)
                  ->whereHas('schedule.course', fn ($q) => $q->where('type', 'PDC'));
            })->where(function ($q) {
                $q->where('override_applied', true)
                  ->orWhere('classification', 'needs');
            })->exists();

            $requirements[] = [
                'label' => 'No unresolved safety flag',
                'met' => ! $flagged,
            ];
        }

        if ($this->requireFullPayment()) {
            $paid = (float) Payment::where('student_id', $student->id)
                ->where('course_id', $course->id)
                ->where('status', 'verified')
                ->sum('amount');
            $fee = (float) $course->fee;

            $requirements[] = [
                'label' => 'Full payment settled (₱' . number_format($paid, 2) . ' / ₱' . number_format($fee, 2) . ')',
                'met' => $paid >= $fee,
            ];
        }

        return $requirements;
    }

    public function isEligible(Student $student, Course $course): bool
    {
        foreach ($this->requirements($student, $course) as $req) {
            if (! $req['met']) {
                return false;
            }
        }
        return true;
    }
}
