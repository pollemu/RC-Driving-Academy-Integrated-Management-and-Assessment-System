<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Enrollment;
use App\Models\Request as StudentRequest;
use App\Models\Schedule;
use App\Notifications\NotificationService;
use App\Services\PdcAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    /* List the student's own requests (reschedule / booking) plus the soft
       instructor preference saved on their profile. */
    public function index(Request $request)
    {
        $student = $request->user()->student;

        $items = $student->requests()->latest()->get()->map(function (StudentRequest $r) {
            return $this->shape($r);
        });

        return response()->json([
            'requests' => $items,
            'preferred_instructor_gender' => in_array($student->preferred_instructor_gender, ['male', 'female'], true)
                ? $student->preferred_instructor_gender
                : null,
        ]);
    }

    /* Save the instructor-gender preference straight onto the student profile.
       It is a soft filter for the PDC calendar, not a permission, so it applies
       immediately and the admin never has to approve it. */
    public function instructorPreference(Request $request)
    {
        $data = $request->validate([
            'preferred_gender' => ['required', 'in:male,female,any'],
        ]);

        $student = $request->user()->student;
        $preference = $data['preferred_gender'] === 'any' ? null : $data['preferred_gender'];

        $student->update(['preferred_instructor_gender' => $preference]);

        return response()->json([
            'ok' => true,
            'preferred_instructor_gender' => $preference,
            'message' => $preference
                ? 'Preference saved. PDC days with a free ' . $preference . ' instructor are shown in green.'
                : 'Preference cleared. Every free PDC day is shown in green.',
        ]);
    }

    /* Request a new PDC session on a free day. The instructor is auto-assigned
       (gender preference first, then the least-loaded free instructor); the
       admin reviews and can swap the instructor before confirming. */
    public function bookingRequest(Request $request)
    {
        $data = $request->validate([
            'preferred_date' => ['required', 'date', 'after_or_equal:today'],
            'preferred_instructor_id' => ['nullable', 'exists:instructors,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $student = $request->user()->student;
        $day = Carbon::parse($data['preferred_date'])->startOfDay();

        // The academy is closed on Sundays — no bookings on a rest day.
        if ($day->dayOfWeek === Carbon::SUNDAY) {
            return response()->json(['message' => 'The academy is closed on Sundays. Pick a day from Monday to Saturday.'], 422);
        }

        // A student may hold one upcoming PDC lesson.
        $hasUpcoming = $student->studentSchedules()
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereHas('schedule', fn ($query) => $query
                ->whereHas('course', fn ($course) => $course->where('type', 'PDC'))
                ->whereDate('date', '>=', now()->toDateString()))
            ->exists();

        if ($hasUpcoming) {
            return response()->json(['message' => 'You already have an upcoming PDC lesson. Cancel it first if you want a different day.'], 422);
        }

        // Student may only hold one pending booking request.
        $open = $student->requests()
            ->where('type', StudentRequest::TYPE_BOOKING)
            ->where('status', StudentRequest::STATUS_PENDING)
            ->exists();
        if ($open) {
            return response()->json(['message' => 'You already have a pending booking request. Wait for the admin to accept it.'], 422);
        }

        $assignment = new PdcAssignment();
        $preference = in_array($student->preferred_instructor_gender, ['male', 'female'], true)
            ? $student->preferred_instructor_gender
            : null;
        $freeIds = array_map('intval', $assignment->freeInstructors($day)->pluck('id')->all());

        $instructorId = $data['preferred_instructor_id'] ?? null;

        if ($instructorId) {
            if (! in_array((int) $instructorId, $freeIds, true)) {
                return response()->json(['message' => 'That instructor is already booked for the 9–11 AM PDC slot. Choose another instructor or day.'], 422);
            }
        } else {
            // Prefer a matching instructor; fall back to the least-loaded free
            // one so a white day can still be requested knowingly.
            $picked = $assignment->pick($day, $preference) ?: $assignment->pick($day);

            if (! $picked) {
                return response()->json(['message' => 'Every PDC instructor is already booked on that day. Please pick another day.'], 422);
            }

            $instructorId = $picked->id;
        }

        $req = StudentRequest::create([
            'type' => StudentRequest::TYPE_BOOKING,
            'student_id' => $student->id,
            'preferred_date' => $day->toDateString(),
            'preferred_instructor_id' => $instructorId,
            'note' => $data['note'] ?? null,
            'status' => StudentRequest::STATUS_PENDING,
        ]);

        $this->notify($req, 'New PDC session requested for ' . $day->toDateString());

        $this->notifyAdmins(
            'New PDC booking request',
            ($student->user?->name ?? 'A student')
                . ' requested a ' . ($req->preferredInstructor?->specialization && str_contains(strtolower($req->preferredInstructor->specialization), '2') ? 'PDC 2 Wheels (Motorcycle)' : 'PDC lesson')
                . ' on ' . $day->format('M j, Y')
                . ' with ' . ($req->preferredInstructor?->user?->name ?? 'an auto-assigned instructor')
                . '.',
            [
                'request_id' => $req->id,
                'student_id' => $student->id,
                'panel' => 'requests',
            ],
            $student->user_id
        );

        return response()->json([
            'request' => $this->shape($req),
            'message' => 'PDC request sent for ' . $day->format('M j')
                . ' with ' . ($req->preferredInstructor?->user?->name ?? 'an instructor')
                . '. The admin will confirm it.',
        ], 201);
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

        $this->notifyAdmins(
            'Reschedule request',
            ($student->user?->name ?? 'A student')
                . ' wants to move a session from ' . $source->date->format('M j, Y')
                . ' to ' . $target->date->format('M j, Y') . '.',
            [
                'request_id' => $req->id,
                'student_id' => $student->id,
                'panel' => 'requests',
            ],
            $student->user_id
        );

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
            'preferred_date' => $r->preferred_date?->format('Y-m-d'),
            'preferred_instructor' => $r->preferredInstructor ? [
                'id' => $r->preferredInstructor->id,
                'name' => $r->preferredInstructor->user->name ?? 'Instructor',
            ] : null,
            'note' => $r->note,
            'admin_note' => $r->admin_note,
            'from_schedule' => $r->fromSchedule ? [
                'id' => $r->fromSchedule->id,
                'date' => $r->fromSchedule->date?->format('Y-m-d'),
                'time_start' => $r->fromSchedule->time_start,
                'time_end' => $r->fromSchedule->time_end,
            ] : null,
            'to_schedule' => $r->schedule ? [
                'id' => $r->schedule->id,
                'date' => $r->schedule->date?->format('Y-m-d'),
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

    /* Push the request into the admin's in-app feed (Requests tab). */
    protected function notifyAdmins(string $title, string $body, array $meta, ?int $userId): void
    {
        try {
            AdminNotification::record(
                AdminNotification::CATEGORY_REQUEST,
                $title,
                $body,
                $meta,
                $userId
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
