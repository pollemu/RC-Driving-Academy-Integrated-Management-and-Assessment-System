<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentSchedule;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $monthStart = now()->startOfMonth();
        $lastMonthStart = now()->subMonthNoOverflow()->startOfMonth();
        $lastMonthEnd = now()->subMonthNoOverflow()->endOfMonth();

        $verifiedThisMonth = Payment::where('status', 'verified')
            ->whereBetween('verified_at', [$monthStart, now()])
            ->sum('amount');

        $verifiedLastMonth = Payment::where('status', 'verified')
            ->whereBetween('verified_at', [$lastMonthStart, $lastMonthEnd])
            ->sum('amount');

        $pendingPayments = Payment::where('status', 'pending')->count();
        $pendingBookings = StudentSchedule::where('status', 'pending')->count();
        $pendingIds = Student::where('id_status', Student::ID_PENDING)->count();

        // Classification is normally fast/average/needs, but older TDC rows used
        // "avg". Normalise before counting so the chart never splits a bucket.
        $proficiency = ['fast' => 0, 'average' => 0, 'needs' => 0];
        Assessment::query()
            ->selectRaw("CASE WHEN classification = 'avg' THEN 'average' ELSE classification END as bucket, count(*) as total")
            ->whereNotNull('classification')
            ->groupBy('bucket')
            ->pluck('total', 'bucket')
            ->each(function ($total, $bucket) use (&$proficiency) {
                if (array_key_exists($bucket, $proficiency)) {
                    $proficiency[$bucket] = (int) $total;
                }
            });

        $tdcStudents = Student::whereHas('enrollments.course', fn ($q) => $q->where('type', 'TDC'))->count();
        $pdcStudents = Student::whereHas('enrollments.course', fn ($q) => $q->where('type', 'PDC'))->count();

        $recent = Enrollment::with(['student.user', 'course'])
            ->orderByDesc('enrolled_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(function (Enrollment $enrollment) {
                return [
                    'student' => $enrollment->student?->user?->name ?? '—',
                    'course' => $enrollment->course?->name ?? '—',
                    'type' => $enrollment->course?->type,
                    'status' => $enrollment->status,
                    'date' => $enrollment->enrolled_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'stats' => [
                'students' => Student::count(),
                'instructors' => User::where('role', 'instructor')->count(),
                'activeEnrollments' => Enrollment::where('status', 'active')->count(),
                'completedEnrollments' => Enrollment::where('status', 'completed')->count(),
                'pendingPayments' => $pendingPayments,
                'pendingBookings' => $pendingBookings,
                'pendingIdVerifications' => $pendingIds,
                'schedules' => Schedule::count(),
                'schedulesToday' => Schedule::where('status', '!=', 'cancelled')
                    ->whereRaw('date <= ?', [today()->toDateString()])
                    ->whereRaw('COALESCE(end_date, date) >= ?', [today()->toDateString()])
                    ->count(),
                'certifications' => Certification::where('status', 'active')->count(),
                'revenueMonth' => (float) $verifiedThisMonth,
                'revenueLastMonth' => (float) $verifiedLastMonth,
                'pendingActions' => $pendingPayments + $pendingBookings + $pendingIds,
            ],
            'recentEnrollments' => $recent,
            'proficiency' => $proficiency,
            'courseBreakdown' => [
                'tdc' => $tdcStudents,
                'pdc' => $pdcStudents,
            ],
        ]);
    }
}
