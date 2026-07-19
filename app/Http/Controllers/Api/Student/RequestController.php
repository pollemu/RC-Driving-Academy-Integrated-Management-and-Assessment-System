<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Request as StudentRequest;
use App\Models\Schedule;
use App\Notifications\NotificationService;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    /* List the student's own requests (reschedule / instructor preference). */
    public function index(Request $request)
    {
        $student = $request->user()->student;

        $items = $student->requests()->latest()->get()->map(function (StudentRequest $r) {
            return $this->shape($r);
        });

        return response()->json(['requests' => $items]);
    }

    /* Submit an instructor-gender preference (soft request, not enforced). */
    public function instructorPreference(Request $request)
    {
        $data = $request->validate([
            'preferred_gender' => ['required', 'in:male,female,any'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $student = $request->user()->student;
        // Persist the standing preference on the student record too.
        $student->update([
            'preferred_instructor_gender' => $data['preferred_gender'] === 'any' ? null : $data['preferred_gender'],
        ]);

        $req = StudentRequest::create([
            'type' => StudentRequest::TYPE_INSTRUCTOR_PREFERENCE,
            'student_id' => $student->id,
            'preferred_gender' => $data['preferred_gender'],
            'note' => $data['note'] ?? null,
            'status' => StudentRequest::STATUS_PENDING,
        ]);

        $this->notify($req, 'Instructor preference requested: ' . $data['preferred_gender']);

        return response()->json(['request' => $this->shape($req)], 201);
    }

    /* Request to move a booked session to a different open session. */
    public function reschedule(Request $request)
    {
        $data = $request->validate([
            'from_schedule_id' => ['required', 'exists:schedules,id'],
            'schedule_id' => ['required', 'exists:schedules,id', 'different:from_schedule_id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $student = $request->user()->student;

        // Must currently have a booking on the source schedule.
        $hasBooking = $student->studentSchedules()
            ->where('schedule_id', $data['from_schedule_id'])
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();
        if (! $hasBooking) {
            return response()->json(['message' => 'You are not booked on that session.'], 422);
        }

        // Target must be an open session for the same course with capacity.
        $target = Schedule::findOrFail($data['schedule_id']);
        if ($target->status !== 'open') {
            return response()->json(['message' => 'That session is not open for booking.'], 422);
        }
        $source = Schedule::findOrFail($data['from_schedule_id']);
        if ($target->course_id !== $source->course_id) {
            return response()->json(['message' => 'You can only reschedule within the same course.'], 422);
        }
        if ($target->studentSchedules()->where('status', 'confirmed')->count() >= $target->max_students) {
            return response()->json(['message' => 'The requested session is already full.'], 422);
        }

        $req = StudentRequest::create([
            'type' => StudentRequest::TYPE_RESCHEDULE,
            'student_id' => $student->id,
            'from_schedule_id' => $data['from_schedule_id'],
            'schedule_id' => $data['schedule_id'],
            'note' => $data['note'] ?? null,
            'status' => StudentRequest::STATUS_PENDING,
        ]);

        $this->notify($req, 'Reschedule requested: ' . $source->date . ' → ' . $target->date);

        return response()->json(['request' => $this->shape($req)], 201);
    }

    /* Cancel a pending request. */
    public function destroy(Request $request, StudentRequest $studentRequest)
    {
        if ($studentRequest->student_id !== $request->user()->student->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        if ($studentRequest->status !== StudentRequest::STATUS_PENDING) {
            return response()->json(['message' => 'Only pending requests can be cancelled.'], 422);
        }
        $studentRequest->delete();

        return response()->json(['ok' => true]);
    }

    protected function shape(StudentRequest $r): array
    {
        return [
            'id' => $r->id,
            'type' => $r->type,
            'status' => $r->status,
            'preferred_gender' => $r->preferred_gender,
            'note' => $r->note,
            'admin_note' => $r->admin_note,
            'from_schedule' => $r->fromSchedule ? [
                'id' => $r->fromSchedule->id,
                'date' => $r->fromSchedule->date,
                'time_start' => $r->fromSchedule->time_start,
                'time_end' => $r->fromSchedule->time_end,
            ] : null,
            'to_schedule' => $r->schedule ? [
                'id' => $r->schedule->id,
                'date' => $r->schedule->date,
                'time_start' => $r->schedule->time_start,
                'time_end' => $r->schedule->time_end,
            ] : null,
            'created_at' => $r->created_at,
        ];
    }

    protected function notify(StudentRequest $req, string $summary): void
    {
        try {
            (new NotificationService())->requestReceived($req->student, $summary);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
