<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\StudentSchedule;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $instructor = $request->user()->instructor;

        $todayString = now()->toDateString();

        $today = Schedule::with('course')
            ->where('instructor_id', $instructor->id)
            ->where('status', '!=', 'cancelled')
            // A TDC block spans two days, so it is "today" on either day.
            ->whereRaw('date <= ?', [$todayString])
            ->whereRaw('COALESCE(end_date, date) >= ?', [$todayString])
            ->get()
            ->map(fn ($s) => $this->shape($s));

        $upcoming = Schedule::with('course')
            ->where('instructor_id', $instructor->id)
            ->where('date', '>', $todayString)
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
            'date' => $schedule->date?->format('Y-m-d'),
            'end_date' => $schedule->end_date?->format('Y-m-d'),
            'time_start' => $schedule->time_start,
            'time_end' => $schedule->time_end,
            'venue' => $schedule->venue,
            'status' => $schedule->status,
        ];
    }

    /* Students assigned to this instructor plus those booked into their sessions. */
    public function students(Request $request)
    {
        $instructor = $request->user()->instructor;

        $assigned = $instructor->students()->with('user')->get()->map(function ($s) {
            return [
                'id' => $s->id,
                'name' => $s->user->name,
                'email' => $s->user->email,
                'phone' => $s->phone,
                'preferred_instructor_gender' => $s->preferred_instructor_gender,
                'source' => 'assigned',
            ];
        });

        $booked = StudentSchedule::with('student.user')
            ->where('status', 'confirmed')
            ->whereHas('schedule', fn ($q) => $q->where('instructor_id', $instructor->id))
            ->get()
            ->map(fn ($b) => $b->student)
            ->unique('id')
            ->values()
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'name' => $s->user->name,
                    'email' => $s->user->email,
                    'phone' => $s->phone,
                    'preferred_instructor_gender' => $s->preferred_instructor_gender,
                    'source' => 'session',
                ];
            });

        $merged = $assigned->concat($booked)
            ->unique('id')
            ->values();

        return response()->json(['students' => $merged]);
    }
}
