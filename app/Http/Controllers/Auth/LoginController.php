<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Invalid credentials.']);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Your account is deactivated.']);
        }

        $request->session()->regenerate();

        return redirect()->intended($this->redirectFor($user->role));
    }

    protected function redirectFor(string $role): string
    {
        return match ($role) {
            'admin' => route('admin.dashboard'),
            'instructor' => route('instructor.dashboard'),
            'student' => route('student.dashboard'),
            default => '/',
        };
    }
}
