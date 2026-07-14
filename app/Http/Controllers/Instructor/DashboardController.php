<?php

namespace App\Http\Controllers\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $instructor = auth()->user()->instructor;

        $schedules = Schedule::with(['course', 'studentSchedules'])
            ->where('instructor_id', $instructor->id)
            ->where('date', '>=', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->orderBy('date')
            ->get();

        $today = Schedule::with('course')
            ->where('instructor_id', $instructor->id)
            ->whereDate('date', now()->toDateString())
            ->get();

        return view('instructor.dashboard', compact('schedules', 'today'));
    }
}
