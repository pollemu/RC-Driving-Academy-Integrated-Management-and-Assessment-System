<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('admin.reports', [
            'totalStudents' => Student::count(),
            'totalEnrollments' => Enrollment::count(),
            'verifiedPayments' => Payment::where('status', 'verified')->count(),
            'totalRevenue' => Payment::where('status', 'verified')->sum('amount'),
            'completedSessions' => Schedule::where('status', 'done')->count(),
        ]);
    }

    public function exportStudents(): StreamedResponse
    {
        $students = Student::with('user')->get();

        return response()->streamDownload(function () use ($students) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Name', 'Email', 'Phone', 'License Type', 'Active']);
            foreach ($students as $s) {
                fputcsv($out, [
                    $s->id,
                    $s->user->name,
                    $s->user->email,
                    $s->phone,
                    $s->license_type,
                    $s->user->is_active ? 'Yes' : 'No',
                ]);
            }
            fclose($out);
        }, 'students-report.csv');
    }
}
