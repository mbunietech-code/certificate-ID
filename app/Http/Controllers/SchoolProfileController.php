<?php

namespace App\Http\Controllers;

use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Profile, branding and numbering of the school in context (school admins). */
class SchoolProfileController extends Controller
{
    public function edit(): View
    {
        $school = $this->tenant()->school();
        $this->authorize('updateProfile', $school);

        return view('school-profile.edit', compact('school'));
    }

    public function update(Request $request, ImageService $images): RedirectResponse
    {
        $school = $this->tenant()->school();
        $this->authorize('updateProfile', $school);

        $data = $request->validate(SchoolController::rules());
        $school->fill(SchoolController::attributes($data));
        $changes = array_keys($school->getDirty());
        $school->save();
        SchoolController::storeImages($school, $request, $images);

        $this->audit('settings.school_updated', $school, 'Updated school profile & branding', ['changed' => $changes]);

        return back()->with('success', 'School profile saved.');
    }
}
