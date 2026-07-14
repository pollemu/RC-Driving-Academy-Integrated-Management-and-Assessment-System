<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class InstructorController extends Controller
{
    public function index()
    {
        $instructors = Instructor::with('user')->latest()->paginate(10);

        $data = $instructors->getCollection()->map(function (Instructor $instructor) {
            return [
                'id' => $instructor->id,
                'name' => $instructor->user->name,
                'email' => $instructor->user->email,
                'phone' => $instructor->phone,
                'specialization' => $instructor->specialization,
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
}
