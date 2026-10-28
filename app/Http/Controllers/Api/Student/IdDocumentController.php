<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/* Secure valid-ID submission for the authenticated student.

   The uploaded file is stored on the PRIVATE disk (storage/app/private) and is
   never exposed by the web server. It can only be read back through this API,
   which checks that the caller owns the record (or is an admin, via the admin
   controller). Files are size-limited, type-checked by both extension and real
   MIME type, and served with `nosniff` / `no-store` headers. */
class IdDocumentController extends Controller
{
    /* Accepted IDs (kept in one place so the UI and validation cannot drift). */
    public const TYPES = [
        'philippine_passport' => 'Philippine Passport',
        'drivers_license' => "Driver's License",
        'student_permit' => 'Student Permit',
        'philsys_id' => 'PhilSys / National ID',
        'umid' => 'UMID',
        'voters_id' => "Voter's ID",
        'postal_id' => 'Postal ID',
        'prc_id' => 'PRC ID',
        'senior_citizen_id' => 'Senior Citizen ID',
        'other' => 'Other government-issued ID',
    ];

    public const MAX_KB = 5120; // 5 MB

    /* Current verification state + the upload form's type list. */
    public function show(Request $request)
    {
        $student = $this->studentOrFail($request);

        return response()->json([
            'id' => $this->payload($student),
            'types' => collect(self::TYPES)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'max_kb' => self::MAX_KB,
        ]);
    }

    /* Upload (or replace) the valid ID. Replacing an approved/rejected ID puts
       it back into the pending queue so the admin always reviews the new file. */
    public function store(Request $request)
    {
        $student = $this->studentOrFail($request);

        $data = $request->validate([
            'document' => [
                'required',
                'file',
                'max:' . self::MAX_KB,
                'mimes:jpg,jpeg,png,webp,pdf',
                'mimetypes:image/jpeg,image/png,image/webp,application/pdf',
            ],
            'document_type' => ['required', 'string', Rule::in(array_keys(self::TYPES))],
            'consent' => ['accepted'],
        ], [
            'document.max' => 'The ID file must be 5 MB or smaller.',
            'document.mimes' => 'Upload a JPG, PNG, WEBP or PDF file.',
            'document.mimetypes' => 'That file does not look like an image or PDF. Please try another copy.',
            'consent.accepted' => 'Please confirm that the ID is yours and may be reviewed by the academy.',
        ]);

        $file = $request->file('document');
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'pdf'], true)) {
            $extension = 'bin';
        }

        $path = $file->storeAs(
            'valid-ids/' . $student->id,
            Str::uuid()->toString() . '.' . $extension,
            'local'
        );

        if (! $path) {
            return response()->json(['message' => 'The ID could not be saved. Please try again.'], 500);
        }

        // Remove the previous file only after the new one is safely stored.
        if ($student->document_path && $student->document_path !== $path) {
            Storage::disk('local')->delete($student->document_path);
        }

        $student->update([
            'document_path' => $path,
            'id_document_type' => $data['document_type'],
            'id_document_name' => Str::limit($file->getClientOriginalName(), 120, ''),
            'id_document_mime' => $file->getMimeType() ?: 'application/octet-stream',
            'id_status' => Student::ID_PENDING,
            'id_submitted_at' => now(),
            'id_reviewed_at' => null,
            'id_reviewed_by' => null,
            'id_rejection_reason' => null,
        ]);

        try {
            AdminNotification::push(
                AdminNotification::CATEGORY_ACCOUNT,
                'Valid ID submitted for review',
                ($student->user?->name ?? 'A student') . ' uploaded a '
                    . (self::TYPES[$data['document_type']] ?? 'valid ID') . '.',
                ['student_id' => $student->id, 'panel' => 'idverification'],
                $student->user_id
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Valid ID submitted. An administrator will review it shortly.',
            'id' => $this->payload($student->fresh()),
        ]);
    }

    /* Stream the student's own document. */
    public function file(Request $request)
    {
        $student = $this->studentOrFail($request);

        return $this->streamDocument($student);
    }

    /* Shared with the admin controller. */
    public function streamDocument(Student $student, ?string $downloadName = null)
    {
        $disk = Storage::disk('local');

        abort_if(! $student->document_path || ! $disk->exists($student->document_path), 404, 'No valid ID on file.');

        return response()->file($disk->path($student->document_path), [
            'Content-Type' => $student->id_document_mime ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . ($downloadName ?: 'valid-id') . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function payload(Student $student): array
    {
        return [
            'status' => $student->id_status ?: Student::ID_NONE,
            'document_type' => $student->id_document_type,
            'document_type_label' => self::TYPES[$student->id_document_type] ?? null,
            'document_name' => $student->id_document_name,
            'submitted_at' => $student->id_submitted_at?->toIso8601String(),
            'reviewed_at' => $student->id_reviewed_at?->toIso8601String(),
            'rejection_reason' => $student->id_rejection_reason,
            'has_document' => (bool) $student->document_path,
            'file_url' => $student->document_path ? '/student/id-document/file' : null,
        ];
    }

    protected function studentOrFail(Request $request): Student
    {
        $student = $request->user()->student;

        abort_if(! $student, 422, 'No student profile is linked to this account.');

        return $student;
    }
}
