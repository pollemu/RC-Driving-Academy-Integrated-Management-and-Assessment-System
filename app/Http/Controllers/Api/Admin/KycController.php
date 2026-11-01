<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Student\KycController as StudentKycController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/* Admin KYC review. The admin sees the student's uploaded ID photo and
   selfie SIDE BY SIDE and verifies BY EYE that it is the same person and a
   plausible government ID. Approving attests to that manual inspection:
   liveness_passed is set true (the human sees a live subject), and the
   face_match_score column stays null on purpose — no fake auto-FR numbers. */
class KycController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['all', 'unverified', 'pending', 'verified', 'rejected'])],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $status = $request->query('status', 'pending');
        $search = trim((string) $request->query('search', ''));

        $query = User::where('role', 'student')->with(['kycVerifiedBy']);

        if ($status !== 'all') {
            $query->where('kyc_status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%');
            });
        }

        $users = $query->orderByDesc('kyc_submitted_at')->get();

        return response()->json([
            'verifications' => $users->map(fn (User $user) => $this->row($user))->values(),
            'counts' => [
                'pending' => User::where('role', 'student')->where('kyc_status', User::KYC_PENDING)->count(),
                'verified' => User::where('role', 'student')->where('kyc_status', User::KYC_VERIFIED)->count(),
                'rejected' => User::where('role', 'student')->where('kyc_status', User::KYC_REJECTED)->count(),
                'unverified' => User::where('role', 'student')->where('kyc_status', User::KYC_UNVERIFIED)->count(),
            ],
            'types' => StudentKycController::TYPES,
        ]);
    }

    public function file(User $user, string $file)
    {
        abort_unless(in_array($file, ['id', 'selfie'], true), 404);
        abort_if(! $user->isStudent(), 404, 'Not a student.');

        $path = $file === 'id' ? $user->id_document_path : $user->selfie_path;

        $disk = Storage::disk('local');
        abort_if(! $path || ! $disk->exists($path), 404, 'No document uploaded.');

        $mime = match (strtolower(pathinfo((string) $path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'jpeg', 'jpg' => 'image/jpeg',
            default => 'application/octet-stream',
        };

        return response()->file($disk->path($path), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="kyc-' . $file . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function approve(Request $request, User $user)
    {
        $data = $request->validate([
            /* Manual comparison of the ID photo against the selfie. Without
               BOTH the face check and the ID check the submission is not
               verified — this keeps a rejected ID or a photo-of-a-photo from
               sneaking through. */
            'face_match_confirmed' => ['accepted'],
            'id_document_confirmed' => ['accepted'],
        ], [
            'face_match_confirmed.accepted' => 'Confirm that the selfie shows the same person as the ID before approving.',
            'id_document_confirmed.accepted' => 'Confirm that the ID photo is a valid government ID before approving.',
        ]);

        abort_if(! $user->id_document_path, 422, 'This student has not uploaded KYC documents yet.');
        abort_unless($user->isStudent(), 422, 'Only students go through KYC.');

        $user->update([
            'kyc_status' => User::KYC_VERIFIED,
            'kyc_verified_at' => now(),
            'kyc_verified_by' => $request->user()->id,
            'kyc_rejection_reason' => null,
            'liveness_passed' => true,
            'auto_verified' => false,
            /* face_match_score stays null: verified by a human, not an
               automated face-recognition model. */
        ]);

        // Keep the legacy students.id_status in sync so any un-migrated
        // consumer of that column still reads a sane value.
        optional($user->student)->update([
            'id_status' => 'approved',
            'id_reviewed_at' => now(),
            'id_reviewed_by' => $request->user()->id,
            'id_rejection_reason' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Identity verified. ' . $user->name . ' can now book lessons and pay.',
            'verification' => $this->row($user->fresh()),
        ]);
    }

    public function reject(Request $request, User $user)
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'reason.required' => 'Tell the student why the documents were rejected so they can resubmit.',
            'reason.min' => 'Please write a slightly more specific reason (at least 5 characters).',
        ]);

        abort_if(! $user->id_document_path, 422, 'This student has not uploaded KYC documents yet.');
        abort_unless($user->isStudent(), 422, 'Only students go through KYC.');

        $user->update([
            'kyc_status' => User::KYC_REJECTED,
            'kyc_verified_at' => null,
            'kyc_verified_by' => $request->user()->id,
            'kyc_rejection_reason' => $data['reason'],
            'liveness_passed' => false,
        ]);

        optional($user->student)->update([
            'id_status' => 'rejected',
            'id_reviewed_at' => now(),
            'id_reviewed_by' => $request->user()->id,
            'id_rejection_reason' => $data['reason'],
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'KYC rejected. The student will see your reason and can resubmit.',
            'verification' => $this->row($user->fresh()),
        ]);
    }

    protected function row(User $user): array
    {
        return [
            'user_id' => $user->id,
            'student_id' => $user->student?->id,
            'student_name' => $user->name,
            'email' => $user->email,
            'status' => $user->kyc_status ?: User::KYC_UNVERIFIED,
            'id_type' => $user->id_type,
            'id_type_label' => StudentKycController::labelFor($user->id_type),
            'submitted_at' => $user->kyc_submitted_at?->toIso8601String(),
            'verified_at' => $user->kyc_verified_at?->toIso8601String(),
            'verified_by' => $user->kycVerifiedBy?->name,
            'rejection_reason' => $user->kyc_rejection_reason,
            'has_id' => (bool) $user->id_document_path,
            'has_selfie' => (bool) $user->selfie_path,
            'id_file_url' => $user->id_document_path ? '/admin/kyc/' . $user->id . '/file/id' : null,
            'selfie_file_url' => $user->selfie_path ? '/admin/kyc/' . $user->id . '/file/selfie' : null,
        ];
    }
}
