<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The super admin's "active school" selector shown in the header. */
class SchoolContextController extends Controller
{
    public function select(Request $request): View|RedirectResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            return redirect()->route('dashboard');
        }

        return view('context.select', [
            'schools' => School::active()->orderBy('name')->get(),
            'return' => $this->safeReturn($request->query('return')),
        ]);
    }

    public function switch(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'school_id' => ['nullable', 'integer', 'exists:schools,id'],
            'return' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->tenant()->selectSchool($data['school_id'] ?? null);
        $school = $this->tenant()->school();

        return redirect($this->safeReturn($data['return'] ?? null) ?? url()->previous(route('dashboard')))
            ->with('success', $school ? "Now working in {$school->name}." : 'Showing all schools.');
    }

    /** Only allow redirects back into this application. */
    private function safeReturn(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        return str_starts_with($url, url('/')) || (str_starts_with($url, '/') && ! str_starts_with($url, '//')) ? $url : null;
    }
}
