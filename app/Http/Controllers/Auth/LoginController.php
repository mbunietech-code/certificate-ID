<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        if (! Auth::attempt($credentials + ['status' => 'active'], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'These credentials do not match an active account.']);
        }

        /** @var User $user */
        $user = Auth::user();
        if (! $user->isSuperAdmin() && (! $user->school || ! $user->school->isActive())) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'Your school account is not active. Contact the system administrator.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
