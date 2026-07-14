<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Promote/demote a user's role. Accounts are created as students;
     * an admin can elevate a student to instructor or admin here.
     */
    public function setRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', 'in:student,instructor,admin'],
        ]);

        $auth = $request->user();

        // Prevent an admin from accidentally removing their own access.
        if ($auth->id === $user->id && $data['role'] !== 'admin') {
            return response()->json(['message' => 'You cannot change your own admin role.'], 422);
        }

        if ($user->role === $data['role']) {
            return response()->json(['user' => $user]);
        }

        $user->update(['role' => $data['role']]);

        if ($data['role'] === 'instructor' && ! $user->instructor) {
            $user->instructor()->save(new Instructor([
                'phone' => $user->student?->phone,
            ]));
        }

        if ($data['role'] === 'student' && ! $user->student) {
            $user->student()->save(new Student([
                'phone' => $user->phone,
            ]));
        }

        $user->load('student', 'instructor');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ],
        ]);
    }
}
