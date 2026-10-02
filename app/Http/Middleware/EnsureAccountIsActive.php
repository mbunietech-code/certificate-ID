<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out users that were deactivated, lost their role, or whose school
 * was deactivated since they logged in.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            $user->loadMissing(['role', 'school']);
            $reason = match (true) {
                ! $user->isActive() => 'Your account has been deactivated.',
                ! $user->role => 'Your account has no role assigned.',
                ! $user->isSuperAdmin() && (! $user->school || ! $user->school->isActive()) => 'Your school account is not active.',
                default => null,
            };

            if ($reason) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => $reason]);
            }
        }

        return $next($request);
    }
}
