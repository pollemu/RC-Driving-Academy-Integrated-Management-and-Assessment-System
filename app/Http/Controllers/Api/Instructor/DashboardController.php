<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $instructor = $request->user()->instructor;

        $today = Schedule::with('course')
            ->where('instructor_id', $instructor->id)
            ->whereDate('date', now()->toDateString())
            ->get()
            ->map(fn ($s) => $this->shape($s));

        $upcoming = Schedule::with('course')
            ->where('instructor_id', $instructor->id)
            ->where('date', '>', now()->toDateString())
            ->where('status', '!=', 'cancelled')
            ->orderBy('date')
            ->get()
            ->map(fn ($s) => $this->shape($s));

        return response()->json(['today' => $today, 'upcoming' => $upcoming]);
    }

    protected function shape(Schedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'course' => ['name' => $schedule->course->name],
            'time_start' => $schedule->time_start,
            'time_end' => $schedule->time_end,
            'venue' => $schedule->venue,
            'status' => $schedule->status,
        ];
    }
}
