<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\Student;

class ReportController extends Controller
{
    public function index()
    {
        return response()->json([
            'stats' => [
                'totalStudents' => Student::count(),
                'totalEnrollments' => Enrollment::count(),
                'verifiedPayments' => Payment::where('status', 'verified')->count(),
                'totalRevenue' => (float) Payment::where('status', 'verified')->sum('amount'),
                'completedSessions' => Schedule::where('status', 'done')->count(),
            ],
        ]);
    }
}
