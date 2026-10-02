<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Services\Documents\NumberGenerator;
use App\Services\ImageService;
use App\Services\Settings;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** System-wide settings (super admin). */
class SettingController extends Controller
{
    public function edit(Settings $settings): View
    {
        $this->authorize('settings.system');

        return view('settings.edit', ['settings' => $settings->all()]);
    }

    public function update(Request $request, Settings $settings, ImageService $images): RedirectResponse
    {
        $this->authorize('settings.system');

        $format = function (string $attribute, mixed $value, Closure $fail) {
            if ($error = NumberGenerator::validateFormat((string) $value)) {
                $fail($error);
            }
        };

        $data = $request->validate([
            'system_name' => ['required', 'string', 'max:120'],
            'organization_name' => ['nullable', 'string', 'max:160'],
            'system_logo' => ['nullable', ...ImageService::UPLOAD_RULES],
            'remove_logo' => ['nullable', 'boolean'],
            'default_paper_size' => ['required', Rule::in(['A4', 'LETTER'])],
            'default_id_width_mm' => ['required', 'numeric', 'min:20', 'max:300'],
            'default_id_height_mm' => ['required', 'numeric', 'min:20', 'max:300'],
            'default_id_dpi' => ['required', Rule::in(['150', '200', '300', '600'])],
            'default_certificate_paper' => ['required', Rule::in(array_keys(array_filter(CertificateTemplate::PAPER_SIZES)))],
            'default_certificate_orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'verification_base_url' => ['nullable', 'url:http,https', 'max:255'],
            'date_format' => ['required', Rule::in(['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y', 'd M Y', 'j F Y'])],
            'default_student_id_format' => ['required', 'string', 'max:80', $format],
            'default_staff_id_format' => ['required', 'string', 'max:80', $format],
            'default_certificate_number_format' => ['required', 'string', 'max:80', $format],
            'id_card_validity_months' => ['required', 'integer', 'min:1', 'max:120'],
            'queue_threshold' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $values = collect($data)->except(['system_logo', 'remove_logo'])->all();
        if ($request->hasFile('system_logo')) {
            $images->delete(setting('system_logo'));
            $values['system_logo'] = $images->store($request->file('system_logo'), 'system', 600, true);
        } elseif ($request->boolean('remove_logo')) {
            $images->delete(setting('system_logo'));
            $values['system_logo'] = null;
        }

        $settings->set($values);
        $this->audit('settings.system_updated', null, 'Updated system settings', ['keys' => array_keys($values)]);

        return back()->with('success', 'Settings saved.');
    }
}
