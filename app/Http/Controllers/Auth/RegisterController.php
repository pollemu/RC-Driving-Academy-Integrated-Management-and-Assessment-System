<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'birthdate' => ['nullable', 'date'],
            'license_type' => ['nullable', 'in:non-pro,pro,student-permit'],
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
        ]));

        Auth::login($user);

        return redirect()->route('student.dashboard');
    }
}
