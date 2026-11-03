<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/* Admin review of externally obtained TDC certificates (the student already
   completed the Theoretical Driving Course at another school and uploaded the
   proof). Approving sets the same `tdc_verified` flag the certification gate
   reads, so the student's later PDC enrollment is unlocked — labelled
   "TDC verified (External)". */

class TdcReviewController extends Controller
{
    /* All students who submitted an external TDC proof, newest first. */
    public function index(Request $request)
    {
        $students = Student::with('user')
            ->whereNotNull('tdc_proof_path')
            ->orderByDesc('tdc_submitted_at')
            ->get()
            ->map(fn (Student $s) => $this->shape($s));

        return response()->json([
            'submissions' => $students,
            'counts' => [
                'pending' => $students->where('status', 'pending')->count(),
                'verified' => $students->where('status', 'verified')->count(),
                'rejected' => $students->where('status', 'rejected')->count(),
            ],
        ]);
    }

    /* Stream the certificate from the private disk. */
    public function file(Student $student)
    {
        $disk = Storage::disk('local');
        $path = $student->tdc_proof_path;

        abort_if(! $path || ! $disk->exists($path), 404, 'No certificate uploaded.');

        $mime = match (strtolower(pathinfo((string) $path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'jpeg', 'jpg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        return response()->file($disk->path($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="external-tdc-certificate"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Request $request, Student $student)
    {
        if (! $student->tdc_proof_path) {
            return response()->json(['message' => 'This student has not uploaded an external TDC certificate.'], 422);
        }

        $student->update([
            'tdc_verified' => true,
            'tdc_verified_by' => auth()->id(),
            'tdc_verified_at' => now(),
            'tdc_reviewed_by' => auth()->id(),
            'tdc_rejection_reason' => null,
        ]);

        return response()->json(['student' => $this->shape($student->fresh())]);
    }

    public function reject(Request $request, Student $student)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $student->update([
            'tdc_verified' => false,
            'tdc_verified_by' => null,
            'tdc_verified_at' => null,
            'tdc_reviewed_by' => auth()->id(),
            'tdc_rejection_reason' => $data['reason'],
        ]);

        return response()->json(['student' => $this->shape($student->fresh())]);
    }

    protected function shape(Student $s): array
    {
        $status = $s->tdc_verified
            ? 'verified'
            : ($s->tdc_rejection_reason ? 'rejected' : 'pending');

        return [
            'student_id' => $s->id,
            'user_id' => $s->user_id,
            'name' => $s->user->name ?? 'Student',
            'avatar' => $s->user->avatar_path,
            'issuer' => $s->tdc_issuer,
            'completion_date' => $s->tdc_completion_date?->format('Y-m-d'),
            'submitted_at' => $s->tdc_submitted_at?->toIso8601String(),
            'verified_at' => $s->tdc_verified_at?->toIso8601String(),
            'rejection_reason' => $s->tdc_rejection_reason,
            'status' => $status,
            'file_url' => '/admin/tdc-verification/' . $s->id . '/file',
        ];
    }
}
