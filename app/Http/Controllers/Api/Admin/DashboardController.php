<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        return response()->json([
            'stats' => [
                'students' => Student::count(),
                'instructors' => User::where('role', 'instructor')->count(),
                'activeEnrollments' => Enrollment::where('status', 'active')->count(),
                'pendingPayments' => Payment::where('status', 'pending')->count(),
                'schedules' => Schedule::count(),
                'certifications' => Certification::count(),
            ],
        ]);
    }
}
