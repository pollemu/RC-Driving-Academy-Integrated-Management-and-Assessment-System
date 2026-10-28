<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Course;
use App\Models\Request as StudentRequest;
use App\Models\Schedule;
use App\Models\StudentSchedule;
use App\Notifications\NotificationService;
use App\Services\PdcAssignment;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    /* All student requests (reschedule / instructor preference), most recent first. */
    public function index(Request $request)
    {
        $status = $request->input('status');

        $items = StudentRequest::with(['student.user', 'schedule.course', 'fromSchedule.course', 'preferredInstructor.user', 'handledBy'])
            ->when(in_array($status, ['pending', 'accepted', 'rejected']), fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15);

        $data = $items->getCollection()->map(function (StudentRequest $r) {
            return [
                'id' => $r->id,
                'type' => $r->type,
                'status' => $r->status,
                'student_name' => $r->student?->user?->name,
                'student_id' => $r->student_id,
                'preferred_gender' => $r->preferred_gender,
                'preferred_instructor_gender' => $r->student?->preferred_instructor_gender,
                'preferred_date' => $r->preferred_date?->format('Y-m-d'),
                'preferred_instructor' => $r->preferredInstructor ? [
                    'id' => $r->preferredInstructor->id,
                    'name' => $r->preferredInstructor->user?->name,
                ] : null,
                'note' => $r->note,
                'admin_note' => $r->admin_note,
                'from_schedule' => $r->fromSchedule ? [
                    'id' => $r->fromSchedule->id,
                    'date' => $r->fromSchedule->date?->format('Y-m-d'),
                    'time_start' => $r->fromSchedule->time_start,
                    'course' => $r->fromSchedule->course?->name,
                ] : null,
                'to_schedule' => $r->schedule ? [
                    'id' => $r->schedule->id,
                    'date' => $r->schedule->date?->format('Y-m-d'),
                    'time_start' => $r->schedule->time_start,
                    'course' => $r->schedule->course?->name,
                ] : null,
                'handled_by' => $r->handledBy?->name,
                'created_at' => $r->created_at,
            ];
        });

        return response()->json([
            'requests' => $data->values(),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function accept(Request $request, StudentRequest $studentRequest)
    {
        if ($studentRequest->status !== StudentRequest::STATUS_PENDING) {
            return response()->json(['message' => 'This request is already resolved.'], 422);
        }

        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
            'instructor_id' => ['nullable', 'exists:instructors,id'],
        ]);

        if ($studentRequest->type === StudentRequest::TYPE_RESCHEDULE) {
            $fromId = $studentRequest->from_schedule_id;
            $toId = $studentRequest->schedule_id;
            if ($fromId && $toId) {
                // Re-check target capacity at accept time (the session may have
                // filled while the request was pending).
                $targetSchedule = Schedule::find($toId);
                $occupied = $targetSchedule
                    ? $targetSchedule->studentSchedules()->whereIn('status', ['confirmed', 'pending'])->count()
                    : 0;
                if ($targetSchedule && $occupied >= $targetSchedule->max_students) {
                    return response()->json(['message' => 'The requested session is already full.'], 422);
                }

                $existingTarget = StudentSchedule::where('student_id', $studentRequest->student_id)
                    ->where('schedule_id', $toId)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->first();

                $source = StudentSchedule::where('student_id', $studentRequest->student_id)
                    ->where('schedule_id', $fromId)
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->first();

                if ($existingTarget) {
                    // Already booked on the target — just cancel the old source booking.
                    if ($source) {
                        $source->update(['status' => 'cancelled']);
                    }
                    $existingTarget->update(['status' => 'confirmed']);
                } elseif ($source) {
                    $source->update(['schedule_id' => $toId, 'status' => 'confirmed']);
                }
            }
        } elseif ($studentRequest->type === StudentRequest::TYPE_BOOKING) {
            // Create the session on the requested day with the requested instructor,
            // then book the student into it.
            $course = Course::where('type', 'PDC')
                ->whereHas('enrollments', fn ($q) => $q
                    ->where('student_id', $studentRequest->student_id)
                    ->where('status', 'active'))
                ->first();

            if (! $course) {
                return response()->json(['message' => 'The student has no active PDC enrollment to book into.'], 422);
            }

            // The student's request already carries an auto-assigned
            // instructor. The admin may swap it right here, and we re-check
            // that whoever is chosen is still free in the 09:00–11:00 slot.
            $instructorId = $data['instructor_id'] ?? $studentRequest->preferred_instructor_id;

            if (! $instructorId) {
                $instructorId = (new PdcAssignment())->pick($studentRequest->preferred_date)?->id;
            }

            if (! $instructorId) {
                return response()->json([
                    'message' => 'No PDC instructor is free on that day. Pick another instructor or reject the request.',
                ], 422);
            }

            $busy = Schedule::where('instructor_id', $instructorId)
                ->where('status', '!=', 'cancelled')
                ->whereDate('date', $studentRequest->preferred_date->toDateString())
                ->where('time_start', '<', Schedule::PDC_SLOT_END)
                ->where('time_end', '>', Schedule::PDC_SLOT_START)
                ->exists();

            if ($busy) {
                return response()->json([
                    'message' => 'That instructor is no longer free at 9–11 AM on that day. Pick another instructor.',
                ], 422);
            }

            $schedule = Schedule::create([
                'course_id' => $course->id,
                'instructor_id' => $instructorId,
                'date' => $studentRequest->preferred_date->format('Y-m-d'),
                'time_start' => Schedule::PDC_SLOT_START,
                'time_end' => Schedule::PDC_SLOT_END,
                'venue' => 'RC Driving Academy — Driving Range',
                'max_students' => 1,
                'status' => 'open',
            ]);

            $studentRequest->update(['preferred_instructor_id' => $instructorId]);

            StudentSchedule::create([
                'student_id' => $studentRequest->student_id,
                'schedule_id' => $schedule->id,
                'booked_at' => now(),
                'status' => 'confirmed',
            ]);

            $studentRequest->update(['schedule_id' => $schedule->id]);
        }

        $studentRequest->update([
            'status' => StudentRequest::STATUS_ACCEPTED,
            'handled_by' => auth()->id(),
            'admin_note' => $data['admin_note'] ?? null,
        ]);

        $this->notify($studentRequest, 'accepted');
        $this->resolveNotifications($studentRequest);

        return response()->json(['request' => $studentRequest]);
    }

    public function reject(Request $request, StudentRequest $studentRequest)
    {
        if ($studentRequest->status !== StudentRequest::STATUS_PENDING) {
            return response()->json(['message' => 'This request is already resolved.'], 422);
        }

        $data = $request->validate([
            'admin_note' => ['nullable', 'string', 'max:500'],
        ]);

        $studentRequest->update([
            'status' => StudentRequest::STATUS_REJECTED,
            'handled_by' => auth()->id(),
            'admin_note' => $data['admin_note'] ?? null,
        ]);

        $this->notify($studentRequest, 'rejected');
        $this->resolveNotifications($studentRequest);

        return response()->json(['request' => $studentRequest]);
    }

    protected function notify(StudentRequest $req, string $decision): void
    {
        try {
            $student = $req->student;
            if ($student) {
                $summary = $req->type === StudentRequest::TYPE_RESCHEDULE
                    ? "Your reschedule request was $decision."
                    : ($req->type === StudentRequest::TYPE_BOOKING
                        ? "Your PDC session request for {$req->preferred_date} was $decision."
                        : "Your instructor preference request was $decision.");
                (new NotificationService())->requestReceived($student, $summary);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /* The request has been handled, so clear its unread feed entry. */
    protected function resolveNotifications(StudentRequest $req): void
    {
        try {
            $reqId = (int) $req->id;
            AdminNotification::where('category', AdminNotification::CATEGORY_REQUEST)
                ->whereNull('read_at')
                ->get()
                ->filter(fn (AdminNotification $n) => (int) ($n->meta['request_id'] ?? 0) === $reqId)
                ->each(fn (AdminNotification $n) => $n->markRead());
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
