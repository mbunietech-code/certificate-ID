<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Routes that create or generate school records need one school in context.
 * School users always have one; the super admin is asked to pick a school.
 */
class RequireSchoolContext
{
    public function __construct(private TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->tenant->hasSchool()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Select a school first.'], 409);
            }

            return redirect()->route('context.select', ['return' => $request->fullUrl()]);
        }

        return $next($request);
    }
}
