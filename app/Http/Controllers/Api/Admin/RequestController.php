<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request as StudentRequest;
use App\Models\StudentSchedule;
use App\Notifications\NotificationService;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    /* All student requests (reschedule / instructor preference), most recent first. */
    public function index(Request $request)
    {
        $status = $request->input('status');

        $items = StudentRequest::with(['student.user', 'schedule.course', 'fromSchedule.course', 'handledBy'])
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
                'note' => $r->note,
                'admin_note' => $r->admin_note,
                'from_schedule' => $r->fromSchedule ? [
                    'id' => $r->fromSchedule->id,
                    'date' => $r->fromSchedule->date,
                    'time_start' => $r->fromSchedule->time_start,
                    'course' => $r->fromSchedule->course?->name,
                ] : null,
                'to_schedule' => $r->schedule ? [
                    'id' => $r->schedule->id,
                    'date' => $r->schedule->date,
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
        ]);

        if ($studentRequest->type === StudentRequest::TYPE_RESCHEDULE) {
            $fromId = $studentRequest->from_schedule_id;
            $toId = $studentRequest->schedule_id;
            if ($fromId && $toId) {
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
        }

        $studentRequest->update([
            'status' => StudentRequest::STATUS_ACCEPTED,
            'handled_by' => auth()->id(),
            'admin_note' => $data['admin_note'] ?? null,
        ]);

        $this->notify($studentRequest, 'accepted');

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

        return response()->json(['request' => $studentRequest]);
    }

    protected function notify(StudentRequest $req, string $decision): void
    {
        try {
            $student = $req->student;
            if ($student) {
                $summary = $req->type === StudentRequest::TYPE_RESCHEDULE
                    ? "Your reschedule request was $decision."
                    : "Your instructor preference request was $decision.";
                (new NotificationService())->requestReceived($student, $summary);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
