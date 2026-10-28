<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use App\Support\Phone;
use App\Support\ValidationRules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /* Full profile for the authenticated user, including the role-specific
       detail record (student or instructor) when one exists. */
    public function show(Request $request)
    {
        return response()->json($this->profilePayload($request->user()));
    }

    /* Update the authenticated user's own name/email and role-specific details. */
    public function update(Request $request)
    {
        $user = $request->user();

        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

        $rules = [
            'name' => ValidationRules::NAME,
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ];
        $rules += $this->detailRules($user->role);

        $data = $request->validate($rules);

        $emailChanged = $data['email'] !== $user->email;

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        // Changing the email invalidates any prior verification.
        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();
        }

        $this->saveDetails($user, $data);

        return response()->json([
            'ok' => true,
            'user' => $this->userArray($user->fresh()),
        ]);
    }

    /* Change the authenticated user's password. Requires the current password,
       which is the standard, self-contained flow (no SMS/email needed). */
    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $user->update(['password' => Hash::make($data['password'])]);

        return response()->json(['ok' => true, 'message' => 'Password updated.']);
    }

    /* Email verification: step 1 — generate a 6-digit code and (once an email
       channel is live) send it to the user. Delivery is not configured yet, so
       we report that plainly instead of silently failing. */
    public function sendVerification(Request $request)
    {
        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json(['ok' => true, 'already_verified' => true, 'message' => 'Your email is already verified.']);
        }

        // Delivery (Brevo/SMTP or SMS) is not wired yet on this environment.
        // Gate on an explicit flag so we report plainly instead of silently failing.
        if (! config('services.email_verification.enabled')) {
            return response()->json([
                'ok' => false,
                'message' => 'Verification emails are not configured yet. Please try again later.',
            ], 503);
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $user->forceFill([
            'email_verification_code' => Hash::make($code),
            'email_verification_expires_at' => now()->addMinutes(15),
        ])->save();

        // TODO: deliver $code via email once Brevo HTTPS API / SMTP is active.

        return response()->json(['ok' => true, 'message' => 'A verification code was sent to your email.']);
    }

    /* Email verification: step 2 — confirm the code. */
    public function confirmVerification(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = $request->user();

        if ($user->email_verified_at) {
            return response()->json(['ok' => true, 'message' => 'Your email is already verified.']);
        }

        if (! $user->email_verification_code || ! $user->email_verification_expires_at || $user->email_verification_expires_at->isPast()) {
            return response()->json(['message' => 'This code has expired. Please request a new one.'], 422);
        }

        if (! Hash::check($data['code'], $user->email_verification_code)) {
            return response()->json(['message' => 'That code is incorrect.'], 422);
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_code' => null,
            'email_verification_expires_at' => null,
        ])->save();

        return response()->json(['ok' => true, 'message' => 'Email verified.']);
    }

    /* Forgot-password request. Delivery needs SMS (Semaphore) or email (Brevo),
       neither of which is active yet, so this reports that plainly instead of
       silently failing. Wire the actual code delivery once a channel is live. */
    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        return response()->json([
            'ok' => false,
            'message' => 'Password reset delivery is not available yet. Please ask the admin to reset your password.',
        ], 503);
    }

    /* ---- Admin: view / edit any user's profile ---- */

    public function adminShow(User $user)
    {
        return response()->json($this->profilePayload($user));
    }

    public function adminUpdate(Request $request, User $user)
    {
        $request->merge(['phone' => Phone::normalize($request->input('phone'))]);

        $rules = [
            'name' => ValidationRules::NAME,
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ];
        $rules += $this->detailRules($user->role);
        if ($request->filled('password')) {
            $rules['password'] = ['string', 'min:8'];
        }

        $data = $request->validate($rules);

        $emailChanged = $data['email'] !== $user->email;

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();
        }

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        $this->saveDetails($user, $data);

        return response()->json(['ok' => true, 'user' => $this->userArray($user->fresh())]);
    }

    /* ---- Shared helpers ---- */

    protected function detailRules(string $role): array
    {
        if ($role === 'student') {
            return [
                'phone' => ValidationRules::PHONE,
                'address' => ['nullable', 'string', 'max:500'],
                'birthdate' => ['nullable', 'date', 'before:today'],
                'license_type' => ['nullable', 'in:non-pro,pro,student-permit'],
                'gender' => ['nullable', 'in:male,female,other'],
            ];
        }

        if ($role === 'instructor') {
            return [
                'phone' => ValidationRules::PHONE,
                'specialization' => ['nullable', 'string', 'max:255'],
                'license_no' => ['nullable', 'string', 'max:255'],
                'gender' => ['nullable', 'in:male,female,other'],
            ];
        }

        return [];
    }

    protected function saveDetails(User $user, array $data): void
    {
        if ($user->isStudent() && $user->student) {
            $user->student->update([
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'birthdate' => $data['birthdate'] ?? null,
                'license_type' => $data['license_type'] ?? null,
                'gender' => $data['gender'] ?? null,
            ]);
        } elseif ($user->isInstructor() && $user->instructor) {
            $user->instructor->update([
                'phone' => $data['phone'] ?? null,
                'specialization' => $data['specialization'] ?? null,
                'license_no' => $data['license_no'] ?? null,
                'gender' => $data['gender'] ?? null,
            ]);
        }
    }

    protected function profilePayload(User $user): array
    {
        $user->loadMissing(['student', 'instructor']);

        return [
            'user' => $this->userArray($user),
            'student' => $user->student ? [
                'phone' => $user->student->phone,
                'address' => $user->student->address,
                'birthdate' => $user->student->birthdate?->format('Y-m-d'),
                'license_type' => $user->student->license_type,
                'gender' => $user->student->gender,
                'id_status' => $user->student->id_status ?: Student::ID_NONE,
                'id_document_type' => $user->student->id_document_type,
                'id_submitted_at' => $user->student->id_submitted_at?->toIso8601String(),
                'id_reviewed_at' => $user->student->id_reviewed_at?->toIso8601String(),
                'id_rejection_reason' => $user->student->id_rejection_reason,
                'has_document' => (bool) $user->student->document_path,
            ] : null,
            'instructor' => $user->instructor ? [
                'phone' => $user->instructor->phone,
                'specialization' => $user->instructor->specialization,
                'license_no' => $user->instructor->license_no,
                'gender' => $user->instructor->gender,
            ] : null,
        ];
    }

    protected function userArray(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified' => $user->email_verified_at !== null,
        ];
    }
}
