<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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

        // Group revenue by month in PHP so it works on any DB driver
        // (strftime is sqlite-only, DATE_FORMAT is mysql-only).
        $revenueByMonth = Payment::where('status', 'verified')
            ->whereNotNull('verified_at')
            ->get(['verified_at', 'amount'])
            ->groupBy(fn ($payment) => \Illuminate\Support\Carbon::parse($payment->verified_at)->format('Y-m'))
            ->map(fn ($rows, $month) => ['month' => $month, 'revenue' => (float) $rows->sum('amount')])
            ->sortBy('month')
            ->values();

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
            'sessions_done' => Schedule::whereIn('status', ['done', 'completed'])->count(),
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
       the notification subsystem is firing even before provider keys exist.
       Also returns the in-app admin feed (tabs: payments, accounts, requests,
       chat escalations, certificates). */
    public function notifications(Request $request)
    {
        $category = $request->query('category');
        $loadLog = $request->boolean('load_log') || $category === 'log';

        $feed = AdminNotification::with('user:id,name')
            ->when(in_array($category, AdminNotification::CATEGORIES, true), fn ($q) => $q->where('category', $category))
            ->latest()
            ->paginate(20, ['id', 'category', 'title', 'body', 'meta', 'user_id', 'read_at', 'created_at'], 'feed_page');

        $feedData = $feed->getCollection()->map(function (AdminNotification $n) {
            return [
                'id' => $n->id,
                'category' => $n->category,
                'title' => $n->title,
                'body' => $n->body,
                'meta' => $n->meta ?: [],
                'user_name' => $n->user?->name,
                'read' => $n->read_at !== null,
                'read_at' => $n->read_at,
                'created_at' => $n->created_at,
            ];
        });

        $logs = collect();
        $logsPagination = null;

        if ($loadLog) {
            $logQuery = NotificationLog::with('user:id,name')
                ->latest()
                ->paginate(20, ['*'], 'log_page');

            $logsPagination = [
                'current_page' => $logQuery->currentPage(),
                'last_page' => $logQuery->lastPage(),
                'per_page' => $logQuery->perPage(),
                'total' => $logQuery->total(),
            ];

            $logs = $logQuery->getCollection()->map(function (NotificationLog $log) {
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
            })->values();
        }

        $gate = new CertGateService();

        return response()->json([
            'feed' => $feedData->values(),
            'feed_pagination' => [
                'current_page' => $feed->currentPage(),
                'last_page' => $feed->lastPage(),
                'per_page' => $feed->perPage(),
                'total' => $feed->total(),
            ],
            'counts' => $this->notificationCounts(),
            'logs' => $logs,
            'pagination' => $logsPagination ?? [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => 20,
                'total' => 0,
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

    /* Light-weight unread badge for the sidebar (no feed payload). */
    public function notificationSummary()
    {
        return response()->json(['counts' => $this->notificationCounts()]);
    }

    /* Mark feed entries read: by ids, by category, or everything. */
    public function markNotificationsRead(Request $request)
    {
        $data = $request->validate([
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer'],
            'category' => ['nullable', Rule::in(AdminNotification::CATEGORIES)],
            'all' => ['nullable', 'boolean'],
        ]);

        $query = AdminNotification::whereNull('read_at');

        if (! empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        } elseif (! empty($data['category'])) {
            $query->where('category', $data['category']);
        } elseif (empty($data['all'])) {
            return response()->json(['message' => 'Nothing to mark as read.'], 422);
        }

        $updated = $query->update(['read_at' => now()]);

        return response()->json([
            'updated' => $updated,
            'counts' => $this->notificationCounts(),
        ]);
    }

    /* Total + unread counters per category for the notification tabs. */
    protected function notificationCounts(): array
    {
        $counts = [];
        foreach (AdminNotification::CATEGORIES as $category) {
            $counts[$category] = ['total' => 0, 'unread' => 0];
        }

        AdminNotification::query()
            ->selectRaw('category, COUNT(*) as total, SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread')
            ->groupBy('category')
            ->get()
            ->each(function ($row) use (&$counts) {
                if (isset($counts[$row->category])) {
                    $counts[$row->category] = [
                        'total' => (int) $row->total,
                        'unread' => (int) $row->unread,
                    ];
                }
            });

        return [
            'categories' => $counts,
            'total' => array_sum(array_column($counts, 'total')),
            'unread' => array_sum(array_column($counts, 'unread')),
        ];
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
