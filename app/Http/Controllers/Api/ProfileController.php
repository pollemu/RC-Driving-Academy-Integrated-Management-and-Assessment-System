<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    /* Full profile for the authenticated user, including the role-specific
       detail record (student or instructor) when one exists. */
    public function show(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
            'student' => $user->student ? [
                'phone' => $user->student->phone,
                'address' => $user->student->address,
                'birthdate' => $user->student->birthdate?->format('Y-m-d'),
                'license_type' => $user->student->license_type,
                'gender' => $user->student->gender,
            ] : null,
            'instructor' => $user->instructor ? [
                'phone' => $user->instructor->phone,
                'specialization' => $user->instructor->specialization,
                'license_no' => $user->instructor->license_no,
                'gender' => $user->instructor->gender,
            ] : null,
        ]);
    }

    /* Update the authenticated user's own name/email and role-specific details. */
    public function update(Request $request)
    {
        $user = $request->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ];

        if ($user->isStudent()) {
            $rules += [
                'phone' => ['nullable', 'string', 'max:50'],
                'address' => ['nullable', 'string', 'max:500'],
                'birthdate' => ['nullable', 'date'],
                'license_type' => ['nullable', 'in:non-pro,pro,student-permit'],
                'gender' => ['nullable', 'in:male,female,other'],
            ];
        } elseif ($user->isInstructor()) {
            $rules += [
                'phone' => ['nullable', 'string', 'max:50'],
                'specialization' => ['nullable', 'string', 'max:255'],
                'license_no' => ['nullable', 'string', 'max:255'],
                'gender' => ['nullable', 'in:male,female,other'],
            ];
        }

        $data = $request->validate($rules);

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

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

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
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
}
