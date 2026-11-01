<?php

namespace App\Http\Controllers\Api\Student;

use App\Http\Controllers\Controller;
use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/* KYC (identity verification) submission for the authenticated student.

   Collects ONE government-ID photo/PDF and ONE selfie for the admin to
   compare manually (face match is done by a human, not by software — the
   face_match_score column stays null until such a system exists).

   Files live on the PRIVATE disk (storage/app/private) and are only ever
   readable through this API, size- and MIME-checked, streamed with
   `nosniff` / `no-store` headers (same hardening as the old valid-ID flow
   this replaces). */
class KycController extends Controller
{
    /* Accepted government IDs. Everything except 'other' is a pre-license
       identity document — NOT a driver's license or student permit, since
       those are issued by LTO *after* the training this academy gives, and
       are therefore useless at signup time (and circular as proof). */
    public const TYPES = [
        'philsys_id' => 'PSA / PhilSys National ID',
        'other' => 'Other government-issued ID',
    ];

    /* Types the OLD valid-ID flow accepted (kept so existing submissions
       still display a readable label; new submissions can't pick them). */
    protected const LEGACY_TYPES = [
        'philippine_passport' => 'Philippine Passport',
        'drivers_license' => "Driver's License",
        'student_permit' => 'Student Permit',
        'umid' => 'UMID',
        'voters_id' => "Voter's ID",
        'postal_id' => 'Postal ID',
        'prc_id' => 'PRC ID',
        'senior_citizen_id' => 'Senior Citizen ID',
    ];

    public const MAX_KB = 5120; // 5 MB per file

    protected const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];
    protected const MIMES = 'image/jpeg,image/png,image/webp';

    /* Current KYC state + everything the submission page needs. */
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'kyc' => $this->payload($user),
            'types' => collect(self::TYPES)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values(),
            'max_kb' => self::MAX_KB,
        ]);
    }

    /* Submit (or resubmit) KYC. Any resubmission of a rejected/verified
       account resets it to pending so the admin always reviews the new
       documents. Verified students cannot silently swap their ID. */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->kyc_status === User::KYC_VERIFIED && ! $user->auto_verified) {
            return response()->json([
                'message' => 'Your identity is already verified.',
            ], 422);
        }

        $data = $request->validate([
            'id_type' => ['required', 'string', Rule::in(array_keys(self::TYPES))],
            'id_document' => [
                'required',
                'file',
                'max:' . self::MAX_KB,
                'mimes:' . implode(',', self::EXTENSIONS),
                'mimetypes:' . self::MIMES,
            ],
            'selfie' => [
                'required',
                'file',
                'max:' . self::MAX_KB,
                'mimes:' . implode(',', self::EXTENSIONS),
                'mimetypes:' . self::MIMES,
            ],
            'consent' => ['accepted'],
            /* Photo evidence is always an image — a PDF selfie is meaningless. */
        ], [
            'id_type.required' => 'Please choose which ID you are uploading.',
            'id_document.required' => 'Upload a photo of your valid ID.',
            'id_document.max' => 'The ID photo must be 5 MB or smaller.',
            'id_document.mimes' => 'The ID must be a JPG or PNG photo.',
            'id_document.mimetypes' => 'That ID file does not look like a real photo. Please try another copy.',
            'selfie.required' => 'Upload a selfie photo of yourself now (holding nothing in front of your face).',
            'selfie.max' => 'The selfie must be 5 MB or smaller.',
            'selfie.mimes' => 'The selfie must be a JPG or PNG photo.',
            'selfie.mimetypes' => 'That selfie file does not look like a real photo. Please try another copy.',
            'consent.accepted' => 'Please confirm that the documents are yours and may be reviewed by the academy.',
        ]);

        $idPath = $this->storePrivate($request->file('id_document'), 'kyc/' . $user->id . '/ids');
        $selfiePath = $this->storePrivate($request->file('selfie'), 'kyc/' . $user->id . '/selfies');

        if (! $idPath || ! $selfiePath) {
            return response()->json(['message' => 'Your documents could not be saved. Please try again.'], 500);
        }

        // Only delete the old files AFTER the new ones are safely stored.
        if ($user->id_document_path) {
            Storage::disk('local')->delete($user->id_document_path);
        }
        if ($user->selfie_path) {
            Storage::disk('local')->delete($user->selfie_path);
        }

        $user->update([
            'kyc_status' => User::KYC_PENDING,
            'id_type' => $data['id_type'],
            'id_document_path' => $idPath,
            'selfie_path' => $selfiePath,
            'kyc_submitted_at' => now(),
            'kyc_verified_at' => null,
            'kyc_verified_by' => null,
            'kyc_rejection_reason' => null,
            'liveness_passed' => false,
        ]);

        try {
            AdminNotification::record(
                AdminNotification::CATEGORY_ACCOUNT,
                'KYC submitted for review',
                ($user->name ?? 'A student') . ' uploaded a '
                    . (self::TYPES[$data['id_type']] ?? 'valid ID') . ' + selfie.',
                ['user_id' => $user->id, 'panel' => 'kyc'],
                $user->id
            );
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'ok' => true,
            'message' => 'Identity documents submitted. An administrator will review them shortly — you can explore the dashboard in the meantime, but booking and payments wait until your account is verified.',
            'kyc' => $this->payload($user->fresh()),
        ]);
    }

    /* Stream the student's own documents ({file} = 'id' | 'selfie'). */
    public function file(Request $request, string $file)
    {
        abort_unless(in_array($file, ['id', 'selfie'], true), 404);

        $user = $request->user();

        return $this->streamFile($user, $file === 'id' ? $user->id_document_path : $user->selfie_path, "kyc-{$file}");
    }

    /* ---------- shared helpers ---------- */

    protected function storePrivate(\Illuminate\Http\UploadedFile $file, string $dir): ?string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        if (! in_array($extension, self::EXTENSIONS, true)) {
            $extension = 'bin';
        }

        return $file->storeAs($dir, Str::uuid()->toString() . '.' . $extension, 'local');
    }

    protected function streamFile(User $user, ?string $path, string $name)
    {
        $disk = Storage::disk('local');

        abort_if(! $path || ! $disk->exists($path), 404, 'File not on file.');

        return response()->file($disk->path($path), [
            'Content-Type' => $this->mimeFor($path),
            'Content-Disposition' => 'inline; filename="' . $name . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function mimeFor(?string $path): string
    {
        return match (strtolower(pathinfo((string) $path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'jpeg', 'jpg' => 'image/jpeg',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    protected function payload(User $user): array
    {
        return [
            'status' => $user->kyc_status ?: User::KYC_UNVERIFIED,
            'id_type' => $user->id_type,
            'id_type_label' => self::labelFor($user->id_type),
            'submitted_at' => $user->kyc_submitted_at?->toIso8601String(),
            'verified_at' => $user->kyc_verified_at?->toIso8601String(),
            'rejection_reason' => $user->kyc_rejection_reason,
            'has_id' => (bool) $user->id_document_path,
            'has_selfie' => (bool) $user->selfie_path,
        ];
    }

    /* Readable label for any stored id_type (new + legacy values). */
    public static function labelFor(?string $type): ?string
    {
        return self::TYPES[$type] ?? self::LEGACY_TYPES[$type] ?? null;
    }
}
