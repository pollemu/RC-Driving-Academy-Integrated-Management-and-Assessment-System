<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/* Upload of an EXTERNAL TDC certificate. Students who already completed the
   Theoretical Driving Course at another LTO-accredited school submit the
   issuing school, the completion date, and a photo/scan of the certificate —
   an admin verifies it, and CertGate then treats their TDC requirement as
   satisfied ("TDC verified (External)"). Files live on the PRIVATE disk and
   are only streamed through the API (same hardening as KYC documents). */

class TdcReviewController extends Controller
{
    public const MAX_KB = 5120; // 5 MB

    protected const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
    protected const MIMES = 'image/jpeg,image/png,image/webp,application/pdf';

    public function show(Request $request)
    {
        $student = $request->user()->student;

        return response()->json([
            'tdc' => $this->payload($student),
            'max_kb' => self::MAX_KB,
        ]);
    }

    public function store(Request $request)
    {
        $student = $request->user()->student;

        if ($student->tdc_verified) {
            return response()->json([
                'message' => 'Your external TDC certificate is already verified.',
            ], 422);
        }

        $data = $request->validate([
            'tdc_issuer' => ['required', 'string', 'max:150'],
            'tdc_completion_date' => ['required', 'date', 'before_or_equal:today'],
            'tdc_proof' => [
                'required',
                'file',
                'max:' . self::MAX_KB,
                'mimes:' . implode(',', self::EXTENSIONS),
                'mimetypes:' . self::MIMES,
            ],
        ], [
            'tdc_issuer.required' => 'Enter the name of the school/center that issued your TDC certificate.',
            'tdc_completion_date.required' => 'Enter the date you completed the TDC.',
            'tdc_completion_date.before_or_equal' => 'The completion date cannot be in the future.',
            'tdc_proof.required' => 'Upload a photo or scan of your TDC certificate.',
            'tdc_proof.max' => 'The certificate must be 5 MB or smaller.',
            'tdc_proof.mimes' => 'The certificate must be a JPG, PNG, WEBP image or a PDF.',
            'tdc_proof.mimetypes' => 'That file does not look like a real certificate copy. Please try another one.',
        ]);

        $extension = strtolower($request->file('tdc_proof')->getClientOriginalExtension() ?: 'bin');
        if (! in_array($extension, self::EXTENSIONS, true)) {
            $extension = 'bin';
        }
        $path = $request->file('tdc_proof')->storeAs(
            'tdc/' . $student->id,
            Str::uuid()->toString() . '.' . $extension,
            'local'
        );

        if (! $path) {
            return response()->json(['message' => 'The file could not be saved. Please try again.'], 500);
        }

        // The old copy is only removed once the new one is safely stored.
        if ($student->tdc_proof_path) {
            Storage::disk('local')->delete($student->tdc_proof_path);
        }

        $student->update([
            'tdc_source' => 'external',
            'tdc_issuer' => $data['tdc_issuer'],
            'tdc_completion_date' => $data['tdc_completion_date'],
            'tdc_proof_path' => $path,
            'tdc_verified' => false,
            'tdc_verified_by' => null,
            'tdc_verified_at' => null,
            'tdc_reviewed_by' => null,
            'tdc_rejection_reason' => null,
            'tdc_submitted_at' => now(),
        ]);

        try {
            AdminNotification::record(
                AdminNotification::CATEGORY_CERTIFICATE,
                'External TDC certificate submitted',
                ($student->user?->name ?? 'A student') . ' uploaded a TDC certificate from '
                    . $data['tdc_issuer'] . ' for review.',
                ['student_id' => $student->id, 'panel' => 'tdcverification'],
                $student->user_id
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Certificate submitted. The admin will review it soon — you can track the status right here.',
            'tdc' => $this->payload($student->fresh()),
        ]);
    }

    /* Stream the student's own uploaded certificate. */
    public function file(Request $request)
    {
        $student = $request->user()->student;
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

    /* Readable status for the student panel. */
    protected function payload($student): array
    {
        $status = $student->tdc_verified
            ? 'verified'
            : ($student->tdc_rejection_reason ? 'rejected' : ($student->tdc_proof_path ? 'pending' : 'none'));

        return [
            'status' => $status,
            'source' => $student->tdc_source,
            'issuer' => $student->tdc_issuer,
            'completion_date' => $student->tdc_completion_date?->format('Y-m-d'),
            'submitted_at' => $student->tdc_submitted_at?->toIso8601String(),
            'verified_at' => $student->tdc_verified_at?->toIso8601String(),
            'rejection_reason' => $student->tdc_rejection_reason,
            'has_proof' => (bool) $student->tdc_proof_path,
            'file_url' => $student->tdc_proof_path ? '/student/tdc-review/file' : null,
        ];
    }
}
