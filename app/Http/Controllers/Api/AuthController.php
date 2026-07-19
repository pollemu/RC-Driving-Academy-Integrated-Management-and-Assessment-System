<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Notifications\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('rcda', ['*'], now()->addHours(8))->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'birthdate' => ['nullable', 'date'],
            'license_type' => ['nullable', 'in:non-pro,pro,student-permit'],
            'gender' => ['nullable', 'in:male,female,other'],
            'preferred_instructor_gender' => ['nullable', 'in:male,female,any'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'student',
            'is_active' => true,
        ]);

        $user->student()->save(new Student([
            'phone' => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'birthdate' => $data['birthdate'] ?? null,
            'license_type' => $data['license_type'] ?? null,
            'gender' => $data['gender'] ?? null,
            'preferred_instructor_gender' => isset($data['preferred_instructor_gender']) && $data['preferred_instructor_gender'] !== 'any'
                ? $data['preferred_instructor_gender']
                : null,
        ]));

        $token = $user->createToken('rcda', ['*'], now()->addHours(8))->plainTextToken;

        try {
            (new NotificationService())->registration($user);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();

        $student = $user->student;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'gender' => $student?->gender,
                'preferred_instructor_gender' => $student?->preferred_instructor_gender,
            ],
        ]);
    }

    /* Update the authenticated student's own gender / instructor-gender preference. */
    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'gender' => ['nullable', 'in:male,female,other'],
            'preferred_instructor_gender' => ['nullable', 'in:male,female'],
        ]);

        $student = $request->user()->student;
        if (! $student) {
            return response()->json(['message' => 'No student profile found.'], 422);
        }

        $student->update([
            'gender' => $data['gender'] ?? $student->gender,
            'preferred_instructor_gender' => $data['preferred_instructor_gender'] ?? $student->preferred_instructor_gender,
        ]);

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }
}
