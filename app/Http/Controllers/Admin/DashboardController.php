<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'students' => Student::count(),
            'instructors' => User::where('role', 'instructor')->count(),
            'activeEnrollments' => Enrollment::where('status', 'active')->count(),
            'pendingPayments' => Payment::where('status', 'pending')->count(),
            'schedules' => Schedule::count(),
            'certifications' => Certification::count(),
        ]);
    }
}
