<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\RoleChange;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Promote/demote a user's role. Accounts are created as students;
     * an admin can elevate a student to instructor or admin here.
     * Every change is logged to role_changes for audit.
     */
    public function setRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', 'in:student,instructor,admin'],
            'specialization' => ['nullable', 'in:TDC,PDC,Both'],
            'gender' => ['nullable', 'in:male,female,other'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $auth = $request->user();

        // Prevent an admin from accidentally removing their own access.
        if ($auth->id === $user->id && $data['role'] !== 'admin') {
            return response()->json(['message' => 'You cannot change your own admin role.'], 422);
        }

        $fromRole = $user->role;

        if ($fromRole === $data['role']) {
            // Still allow updating instructor specialization without a role change.
            if ($data['role'] === 'instructor' && $user->instructor && isset($data['specialization'])) {
                $user->instructor->update(['specialization' => $data['specialization']]);
            }
            $user->load('student', 'instructor');
            return response()->json(['user' => $this->publicUser($user)]);
        }

        $user->update(['role' => $data['role']]);

        if ($data['role'] === 'instructor' && ! $user->instructor) {
            $user->instructor()->save(new Instructor([
                'phone' => $user->student?->phone,
                'specialization' => $data['specialization'] ?? 'TDC',
                'gender' => $data['gender'] ?? null,
            ]));
        } elseif ($data['role'] === 'instructor') {
            $updates = [];
            if (isset($data['specialization'])) {
                $updates['specialization'] = $data['specialization'];
            }
            if (isset($data['gender'])) {
                $updates['gender'] = $data['gender'];
            }
            if ($updates) {
                $user->instructor->update($updates);
            }
        }

        if ($data['role'] === 'student' && ! $user->student) {
            $user->student()->save(new Student([
                'phone' => $user->phone,
            ]));
        }

        RoleChange::create([
            'user_id' => $user->id,
            'changed_by' => $auth->id,
            'from_role' => $fromRole,
            'to_role' => $data['role'],
            'reason' => $data['reason'] ?? null,
        ]);

        $user->load('student', 'instructor');

        return response()->json(['user' => $this->publicUser($user)]);
    }

    protected function publicUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'specialization' => $user->instructor?->specialization,
        ];
    }

    /* Audit log of all role changes (most recent first). */
    public function roleChanges()
    {
        $changes = RoleChange::with(['user', 'changedBy'])
            ->latest()
            ->paginate(20);

        $data = $changes->getCollection()->map(function (RoleChange $change) {
            return [
                'id' => $change->id,
                'user_name' => $change->user?->name,
                'changed_by' => $change->changedBy?->name,
                'from_role' => $change->from_role,
                'to_role' => $change->to_role,
                'reason' => $change->reason,
                'created_at' => $change->created_at,
            ];
        });

        return response()->json([
            'changes' => $data->values(),
            'pagination' => [
                'current_page' => $changes->currentPage(),
                'last_page' => $changes->lastPage(),
                'per_page' => $changes->perPage(),
                'total' => $changes->total(),
            ],
        ]);
    }
}
