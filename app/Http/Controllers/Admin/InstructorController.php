<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Instructor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class InstructorController extends Controller
{
    public function index(): View
    {
        $instructors = Instructor::with('user')->latest()->paginate(15);

        return view('admin.instructors', compact('instructors'));
    }

    public function create(): View
    {
        return view('admin.instructors-create');
    }

    public function store(Request $request): RedirectResponse
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

        return redirect()->route('admin.instructors.index')->with('status', 'Instructor created.');
    }

    public function toggleActive(Instructor $instructor): RedirectResponse
    {
        $instructor->user->update(['is_active' => ! $instructor->user->is_active]);

        return back()->with('status', 'Instructor account updated.');
    }
}
