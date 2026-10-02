<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Route parameter {certTemplate} is bound explicitly (tenant-scoped) in AppServiceProvider. */
class CertificateTemplateController extends TemplateController
{
    protected function modelClass(): string
    {
        return CertificateTemplate::class;
    }

    protected function kind(): string
    {
        return 'certificate';
    }

    protected function routePrefix(): string
    {
        return 'templates.certificates';
    }

    protected function settingsRules(?Model $template): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'paper_size' => ['required', Rule::in(array_keys(CertificateTemplate::PAPER_SIZES))],
            'orientation' => ['required', Rule::in(['portrait', 'landscape'])],
            'width_mm' => ['required_if:paper_size,CUSTOM', 'nullable', 'numeric', 'min:50', 'max:600'],
            'height_mm' => ['required_if:paper_size,CUSTOM', 'nullable', 'numeric', 'min:50', 'max:600'],
            'default_title' => ['nullable', 'string', 'max:255'],
            'default_body' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function applySettings(Model $template, array $data): void
    {
        [$w, $h] = CertificateTemplate::dimensionsFor($data['paper_size'], $data['orientation'])
            ?? [(float) $data['width_mm'], (float) $data['height_mm']];

        $template->fill([
            'name' => $data['name'],
            'paper_size' => $data['paper_size'],
            'orientation' => $data['orientation'],
            'width_mm' => $w,
            'height_mm' => $h,
            'default_title' => $data['default_title'] ?? null,
            'default_body' => $data['default_body'] ?? null,
        ]);
    }
}
