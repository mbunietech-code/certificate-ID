<?php

namespace App\Http\Controllers;

use App\Models\IdCardTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

/** Route parameter {idTemplate} is bound explicitly (tenant-scoped) in AppServiceProvider. */
class IdCardTemplateController extends TemplateController
{
    protected function modelClass(): string
    {
        return IdCardTemplate::class;
    }

    protected function kind(): string
    {
        return 'id_card';
    }

    protected function routePrefix(): string
    {
        return 'templates.id-cards';
    }

    protected function settingsRules(?Model $template): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(IdCardTemplate::TYPES))],
            'width_mm' => ['required', 'numeric', 'min:20', 'max:300'],
            'height_mm' => ['required', 'numeric', 'min:20', 'max:300'],
            'dpi' => ['required', 'integer', Rule::in([150, 200, 300, 600])],
            'has_back' => ['nullable', 'boolean'],
        ];
    }

    protected function applySettings(Model $template, array $data): void
    {
        $template->fill([
            'name' => $data['name'],
            'type' => $data['type'],
            'width_mm' => $data['width_mm'],
            'height_mm' => $data['height_mm'],
            'orientation' => $data['width_mm'] >= $data['height_mm'] ? 'landscape' : 'portrait',
            'dpi' => $data['dpi'],
            'has_back' => (bool) ($data['has_back'] ?? false),
        ]);
    }
}
