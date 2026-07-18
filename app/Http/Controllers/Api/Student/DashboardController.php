<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Certification;
use App\Models\StudentSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $student = $request->user()->student;
        $student->load(['enrollments.course', 'certifications.course']);

        $enrollments = $student->enrollments->map(function ($e) {
            return [
                'id' => $e->id,
                'course' => [
                    'name' => $e->course->name,
                    'type' => $e->course->type,
                    'fee' => $e->course->fee,
                ],
                'status' => $e->status,
                'enrolled_at' => $e->enrolled_at,
            ];
        });

        $bookings = StudentSchedule::with(['schedule.course', 'schedule.instructor.user'])
            ->where('student_id', $student->id)
            ->latest()
            ->get()
            ->map(function (StudentSchedule $booking) {
                $s = $booking->schedule;

                return [
                    'id' => $booking->id,
                    'schedule_id' => $s->id,
                    'course' => ['name' => $s->course->name],
                    'date' => $s->date,
                    'time_start' => $s->time_start,
                    'time_end' => $s->time_end,
                    'instructor_name' => $s->instructor->user->name,
                    'status' => $booking->status,
                    'cancelable' => $s->date && $s->time_start
                        ? now()->addHour()->lt(
                            Carbon::parse($s->date->format('Y-m-d') . ' ' . $s->time_start)
                          )
                        : false,
                ];
            });

        $certifications = $student->certifications->map(function (Certification $c) {
            return [
                'course' => ['name' => $c->course->name],
                'certificate_no' => $c->certificate_no,
                'status' => $c->status,
            ];
        });

        return response()->json([
            'enrollments' => $enrollments,
            'bookings' => $bookings,
            'certifications' => $certifications,
        ]);
    }
}
