<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Attendance;
use App\Models\Certification;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\Student;
use App\Services\CertGateService;
use Illuminate\Support\Facades\DB;

/**
 * Admin reporting. The dashboard endpoint (/admin/dashboard) returns a few
 * headline counts; this endpoint returns the six detailed report cards the
 * management pack expects. Every figure is computed from live data.
 *
 *   1. Enrollments by course
 *   2. Revenue (verified payments) by month
 *   3. Payment status breakdown
 *   4. Certifications issued (by course)
 *   5. Attendance summary (present/late/absent)
 *   6. Assessment pass/fail distribution
 */
class ReportController extends Controller
{
    public function index()
    {
        $enrollmentsByCourse = Course::withCount('enrollments')->get()->map(function (Course $course) {
            return [
                'course' => $course->name,
                'type' => $course->type,
                'enrollments' => $course->enrollments_count,
                'active' => $course->enrollments()->where('status', 'active')->count(),
                'completed' => $course->enrollments()->where('status', 'completed')->count(),
            ];
        });

        $revenueByMonth = Payment::where('status', 'verified')
            ->select(DB::raw("strftime('%Y-%m', verified_at) as month"), DB::raw('SUM(amount) as total'))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(function ($row) {
                return ['month' => $row->month, 'revenue' => (float) $row->total];
            });

        $paymentStatus = [
            'pending' => Payment::where('status', 'pending')->count(),
            'verified' => Payment::where('status', 'verified')->count(),
            'rejected' => Payment::where('status', 'rejected')->count(),
        ];

        $certificationsByCourse = Course::withCount('certifications')->get()->map(function (Course $course) {
            return [
                'course' => $course->name,
                'issued' => $course->certifications_count,
                'revoked' => $course->certifications()->where('status', 'revoked')->count(),
            ];
        });

        $attendance = [
            'present' => Attendance::where('status', 'present')->count(),
            'late' => Attendance::where('status', 'late')->count(),
            'absent' => Attendance::where('status', 'absent')->count(),
            'sessions_done' => Schedule::where('status', 'done')->count(),
        ];

        $assessments = Assessment::query();
        $assessmentSummary = [
            'total' => $assessments->count(),
            'passed' => (clone $assessments)->where('passed', true)->count(),
            'failed' => (clone $assessments)->where('passed', false)->count(),
            'fast' => (clone $assessments)->where('classification', 'fast')->count(),
            'average' => (clone $assessments)->where('classification', 'average')->count(),
            'needs' => (clone $assessments)->where('classification', 'needs')->count(),
            'safety_flagged' => (clone $assessments)->where('override_applied', true)->count(),
        ];

        $totals = [
            'students' => Student::count(),
            'instructors' => DB::table('instructors')->count(),
            'enrollments' => Enrollment::count(),
            'revenue' => (float) Payment::where('status', 'verified')->sum('amount'),
            'certifications' => Certification::count(),
        ];

        return response()->json([
            'totals' => $totals,
            'enrollmentsByCourse' => $enrollmentsByCourse,
            'revenueByMonth' => $revenueByMonth,
            'paymentStatus' => $paymentStatus,
            'certificationsByCourse' => $certificationsByCourse,
            'attendance' => $attendance,
            'assessments' => $assessmentSummary,
        ]);
    }

    /* Recent notification log (SMS/Email/Maps attempts) so admins can verify
       the notification subsystem is firing even before provider keys exist. */
    public function notifications()
    {
        $logs = NotificationLog::with('user')
            ->latest()
            ->paginate(20);

        $data = $logs->getCollection()->map(function (NotificationLog $log) {
            return [
                'id' => $log->id,
                'channel' => $log->channel,
                'event' => $log->event,
                'recipient' => $log->recipient,
                'message' => $log->message,
                'sent' => $log->sent,
                'error' => $log->error,
                'user_name' => $log->user?->name,
                'created_at' => $log->created_at,
            ];
        });

        $gate = new CertGateService();

        return response()->json([
            'logs' => $data->values(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'channels' => [
                'sms_enabled' => config('notifications.sms.enabled') && ! empty(config('services.semaphore.api_key')),
                'mail_enabled' => config('notifications.mail.enabled'),
                'maps_enabled' => config('notifications.maps.enabled') && ! empty(config('services.google_maps.api_key')),
            ],
            'cert_settings' => [
                'pdc_required_sessions' => $gate->pdcRequiredSessions(),
                'require_full_payment' => $gate->requireFullPayment(),
                'require_safety_clear' => $gate->requireSafetyClear(),
            ],
        ]);
    }

    /* Update configurable certification gates. */
    public function updateCertSettings(Request $request)
    {
        $data = $request->validate([
            'pdc_required_sessions' => ['nullable', 'integer', 'min:1'],
            'require_full_payment' => ['nullable', 'boolean'],
            'require_safety_clear' => ['nullable', 'boolean'],
        ]);

        $gate = new CertGateService();
        if (isset($data['pdc_required_sessions'])) {
            Setting::set($gate::PDC_REQUIRED_SESSIONS_KEY, $data['pdc_required_sessions']);
        }
        if (isset($data['require_full_payment'])) {
            Setting::set($gate::REQUIRE_FULL_PAYMENT_KEY, $data['require_full_payment']);
        }
        if (isset($data['require_safety_clear'])) {
            Setting::set($gate::REQUIRE_SAFETY_CLEAR_KEY, $data['require_safety_clear']);
        }

        return response()->json([
            'cert_settings' => [
                'pdc_required_sessions' => $gate->pdcRequiredSessions(),
                'require_full_payment' => $gate->requireFullPayment(),
                'require_safety_clear' => $gate->requireSafetyClear(),
            ],
        ]);
    }
}
