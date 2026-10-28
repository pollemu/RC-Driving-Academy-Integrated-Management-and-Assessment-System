<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Student\IdDocumentController;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/* Admin review queue for student valid IDs. Approving or rejecting only changes
   the verification state — see the note in SYSTEM_FLOW (no gating by default). */
class IdVerificationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['all', 'none', 'pending', 'approved', 'rejected'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $status = $request->query('status', 'pending');
        $search = trim((string) $request->query('search', ''));

        $query = Student::with(['user', 'idReviewedBy']);

        if ($status === 'none') {
            $query->where(function ($q) {
                $q->whereNull('id_status')->orWhere('id_status', Student::ID_NONE);
            });
        } else {
            $query->where('id_status', '!=', Student::ID_NONE);

            if ($status && $status !== 'all') {
                $query->where('id_status', $status);
            }
        }

        if ($search !== '') {
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $students = $query->latest('id_submitted_at')->get();

        return response()->json([
            'verifications' => $students->map(fn (Student $student) => $this->row($student))->values(),
            'counts' => [
                'pending' => Student::where('id_status', Student::ID_PENDING)->count(),
                'approved' => Student::where('id_status', Student::ID_APPROVED)->count(),
                'rejected' => Student::where('id_status', Student::ID_REJECTED)->count(),
                'none' => Student::where('id_status', Student::ID_NONE)->count(),
            ],
            'types' => IdDocumentController::TYPES,
        ]);
    }

    public function show(Student $student)
    {
        $student->load(['user', 'idReviewedBy']);

        return response()->json(['verification' => $this->row($student)]);
    }

    public function file(Student $student)
    {
        return (new IdDocumentController())->streamDocument($student);
    }

    public function approve(Request $request, Student $student)
    {
        abort_if(! $student->document_path, 422, 'This student has not uploaded an ID yet.');

        $student->update([
            'id_status' => Student::ID_APPROVED,
            'id_reviewed_at' => now(),
            'id_reviewed_by' => $request->user()->id,
            'id_rejection_reason' => null,
        ]);

        $student->load(['user', 'idReviewedBy']);

        return response()->json([
            'ok' => true,
            'message' => 'Valid ID approved. The student can now see the verified badge.',
            'verification' => $this->row($student),
        ]);
    }

    public function reject(Request $request, Student $student)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Tell the student why the ID was rejected so they can fix it.',
            'reason.min' => 'Please write a slightly more specific reason (at least 5 characters).',
        ]);

        abort_if(! $student->document_path, 422, 'This student has not uploaded an ID yet.');

        $student->update([
            'id_status' => Student::ID_REJECTED,
            'id_reviewed_at' => now(),
            'id_reviewed_by' => $request->user()->id,
            'id_rejection_reason' => $data['reason'],
        ]);

        $student->load(['user', 'idReviewedBy']);

        return response()->json([
            'ok' => true,
            'message' => 'Valid ID rejected. The student will see your reason.',
            'verification' => $this->row($student),
        ]);
    }

    protected function row(Student $student): array
    {
        return [
            'student_id' => $student->id,
            'student_name' => $student->user?->name,
            'email' => $student->user?->email,
            'phone' => $student->phone,
            'status' => $student->id_status ?: Student::ID_NONE,
            'document_type' => $student->id_document_type,
            'document_type_label' => IdDocumentController::TYPES[$student->id_document_type] ?? null,
            'document_name' => $student->id_document_name,
            'submitted_at' => $student->id_submitted_at?->toIso8601String(),
            'reviewed_at' => $student->id_reviewed_at?->toIso8601String(),
            'reviewed_by' => $student->idReviewedBy?->name,
            'rejection_reason' => $student->id_rejection_reason,
            'has_document' => (bool) $student->document_path,
            'file_url' => $student->document_path ? '/admin/id-verifications/' . $student->id . '/file' : null,
        ];
    }
}
