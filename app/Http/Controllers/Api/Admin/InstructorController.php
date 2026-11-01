<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InstructorController extends Controller
{
    public function index(Request $request)
    {
        /* Server-side search: matches every page of the instructor list,
           not just the ten rows currently rendered. */
        $search = trim((string) $request->query('search', ''));

        $instructors = Instructor::with('user')
            ->when($search !== '', function ($q) use ($search) {
                $like = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search);
                $q->where(function ($q) use ($like) {
                    $q->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$like}%")
                            ->orWhere('email', 'like', "%{$like}%"))
                        ->orWhere('phone', 'like', "%{$like}%")
                        ->orWhere('specialization', 'like', "%{$like}%")
                        ->orWhere('license_no', 'like', "%{$like}%");
                });
            })
            ->latest()
            ->paginate(10);

        $data = $instructors->getCollection()->map(function (Instructor $instructor) {
            return [
                'id' => $instructor->id,
                'user_id' => $instructor->user_id,
                'name' => $instructor->user->name,
                'email' => $instructor->user->email,
                'phone' => $instructor->phone,
                'specialization' => $instructor->specialization,
                'gender' => $instructor->gender,
                'license_no' => $instructor->license_no,
                'is_active' => $instructor->user->is_active,
            ];
        });

        return response()->json([
            'instructors' => $data->values(),
            'pagination' => [
                'current_page' => $instructors->currentPage(),
                'last_page' => $instructors->lastPage(),
                'per_page' => $instructors->perPage(),
                'total' => $instructors->total(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:50'],
            'specialization' => ['required', 'in:TDC,PDC,TDC+PDC'],
            'license_no' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'instructor',
            'is_active' => true,
        ]);

        $user->instructor()->save(new Instructor([
            'phone' => $data['phone'] ?? null,
            'specialization' => $data['specialization'],
            'license_no' => $data['license_no'] ?? null,
        ]));

        $user->load('instructor');

        return response()->json(['instructor' => $user->instructor], 201);
    }

    public function toggleActive(Instructor $instructor)
    {
        $instructor->user->update(['is_active' => ! $instructor->user->is_active]);

        $instructor->load('user');

        return response()->json(['instructor' => $instructor]);
    }

    public function destroy(Instructor $instructor)
    {
        $instructor->user->delete();

        return response()->json(null, 204);
    }
}
