<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Instructor;
use App\Models\Schedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function index(): View
    {
        $schedules = Schedule::with(['course', 'instructor.user'])
            ->latest('date')
            ->paginate(15);

        return view('admin.schedules', compact('schedules'));
    }

    public function create(): View
    {
        $courses = Course::where('is_active', true)->get();
        $instructors = Instructor::with('user')->get();

        return view('admin.schedules-create', compact('courses', 'instructors'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'instructor_id' => ['required', 'exists:instructors,id'],
            'date' => ['required', 'date'],
            'time_start' => ['required', 'date_format:H:i'],
            'time_end' => ['required', 'date_format:H:i', 'after:time_start'],
            'venue' => ['nullable', 'string', 'max:255'],
            'max_students' => ['required', 'integer', 'min:1'],
        ]);

        if ($this->hasConflict($data)) {
            return back()->withInput()->withErrors([
                'instructor_id' => 'This instructor already has a session at the same date/time.',
            ]);
        }

        Schedule::create($data + ['status' => 'open']);

        return redirect()->route('admin.schedules.index')->with('status', 'Schedule created.');
    }

    public function cancel(Schedule $schedule): RedirectResponse
    {
        $schedule->update(['status' => 'cancelled']);

        return back()->with('status', 'Schedule cancelled.');
    }

    protected function hasConflict(array $data): bool
    {
        return Schedule::where('instructor_id', $data['instructor_id'])
            ->where('date', $data['date'])
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($data) {
                $query->where(function ($q) use ($data) {
                    $q->where('time_start', '<', $data['time_end'])
                        ->where('time_end', '>', $data['time_start']);
                });
            })
            ->exists();
    }
}
