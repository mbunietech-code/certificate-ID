<?php

namespace App\Http\Controllers;

use App\Models\CertificateTemplate;
use App\Models\IdCardTemplate;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Services\Documents\DesignPresets;
use App\Services\Documents\DesignSanitizer;
use App\Services\Documents\DocumentData;
use App\Services\Documents\PageComposer;
use App\Services\Documents\Placeholders;
use App\Services\Documents\TemplateRenderer;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Shared CRUD + designer for ID card and certificate templates.
 *
 * Templates with school_id NULL are global (created by the super admin while
 * "All schools" is selected) and can be used or duplicated by every school.
 */
abstract class TemplateController extends Controller
{
    /** @return class-string<IdCardTemplate|CertificateTemplate> */
    abstract protected function modelClass(): string;

    abstract protected function kind(): string;

    abstract protected function routePrefix(): string;

    /** @return array<string, mixed> */
    abstract protected function settingsRules(?Model $template): array;

    /** @param  array<string, mixed>  $data */
    abstract protected function applySettings(Model $template, array $data): void;

    public function index(Request $request): View
    {
        $class = $this->modelClass();
        $this->authorize('viewAny', $class);

        $templates = $class::query()
            ->with(['school:id,school_code,name', 'creator:id,name'])
            ->when($request->query('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByRaw('school_id is null')->orderBy('name')
            ->paginate(24)->withQueryString();

        return view('templates.index', $this->shared() + compact('templates'));
    }

    public function create(Request $request): View
    {
        $class = $this->modelClass();
        $this->authorize('create', $class);

        $template = new $class;
        $presets = DesignPresets::optionsFor($this->kind());

        return view('templates.create', $this->shared() + compact('template', 'presets') + [
            'preset' => $request->query('preset', array_key_first($presets)),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $class = $this->modelClass();
        $this->authorize('create', $class);

        $data = $request->validate($this->settingsRules(null) + [
            'preset' => ['required', Rule::in(array_keys(DesignPresets::optionsFor($this->kind())))],
        ]);

        $preset = DesignPresets::get($data['preset']);
        $template = new $class;
        $this->applySettings($template, $data);
        $template->design_json = $this->resizeDesign($preset['design'], (float) $preset['width'], (float) $preset['height'], (float) $template->width_mm, (float) $template->height_mm);
        $template->created_by = $request->user()->id;
        $template->status = 'active';
        // school_id comes from the tenant context (NULL = global when the super admin has "All schools" selected).
        $template->save();

        $this->audit('template.created', $template, "Created {$this->kind()} template {$template->name}".($template->isGlobal() ? ' (global)' : ''));

        return redirect()->route($this->routePrefix().'.design', $template)->with('success', 'Template created. Arrange the elements and save.');
    }

    public function edit(Model $template): View
    {
        $this->authorize('update', $template);

        return view('templates.edit', $this->shared() + compact('template'));
    }

    public function update(Request $request, Model $template): RedirectResponse
    {
        $this->authorize('update', $template);

        $data = $request->validate($this->settingsRules($template) + ['status' => ['required', Rule::in(['active', 'inactive'])]]);
        $this->applySettings($template, $data);
        $template->status = $data['status'];
        $changes = array_keys($template->getDirty());
        $template->save();

        $this->audit('template.updated', $template, "Updated {$this->kind()} template {$template->name}", ['changed' => $changes]);

        return redirect()->route($this->routePrefix().'.index')->with('success', 'Template settings saved.');
    }

    public function destroy(Model $template): RedirectResponse
    {
        $this->authorize('delete', $template);

        $template->delete();
        $this->audit('template.deleted', $template, "Deleted {$this->kind()} template {$template->name}");

        return redirect()->route($this->routePrefix().'.index')->with('success', 'Template deleted. Documents already issued with it are not affected.');
    }

    public function duplicate(Request $request, Model $template): RedirectResponse
    {
        $this->authorize('duplicate', $template);
        $this->authorize('create', $this->modelClass());

        $copy = $template->replicate(['school_id', 'is_default', 'created_by']);
        $copy->name = mb_substr($template->name.' (copy)', 0, 120);
        $copy->created_by = $request->user()->id;
        $copy->is_default = false;
        $copy->save();

        $this->audit('template.created', $copy, "Duplicated template {$template->name}");

        return redirect()->route($this->routePrefix().'.design', $copy)->with('success', 'Template duplicated into '.($copy->isGlobal() ? 'global templates' : 'your school').'.');
    }

    public function design(Model $template): View
    {
        $this->authorize('update', $template);

        $school = $this->sampleSchool($template);
        $holderType = $template->holderType();
        $sample = DocumentData::sample($this->kind(), $holderType, $school, $this->sampleHolder($school, $holderType));
        $disk = Storage::disk('public');

        return view('templates.designer', $this->shared() + [
            'template' => $template,
            'config' => [
                'kind' => $this->kind(),
                'width' => (float) $template->width_mm,
                'height' => (float) $template->height_mm,
                'sides' => array_keys($template->sides()),
                'design' => $template->design_json,
                'placeholders' => Placeholders::groupsFor($this->kind(), $holderType),
                'sample' => $sample->text,
                'sampleImages' => collect($sample->images)->map(fn ($p) => $p && $disk->exists($p) ? $disk->url($p) : null),
                'colors' => $sample->colors + ['primary' => '#1e3a8a', 'secondary' => '#b45309'],
                'fonts' => collect(TemplateRenderer::FONTS)->map(fn ($f) => ['label' => $f[0], 'css' => $f[1]]),
                'imageSources' => TemplateRenderer::IMAGE_SOURCES,
                'saveUrl' => route($this->routePrefix().'.design.save', $template),
                'previewUrl' => route($this->routePrefix().'.preview', $template),
                'uploadUrl' => route('templates.assets.store'),
                'assetBase' => $disk->url(''),
            ],
        ]);
    }

    public function saveDesign(Request $request, Model $template, DesignSanitizer $sanitizer): JsonResponse
    {
        $this->authorize('update', $template);

        $request->validate(['design' => ['required', 'array']]);
        $template->design_json = $sanitizer->clean($request->input('design'), array_keys($template->sides()) + ($template instanceof IdCardTemplate ? [1 => 'back'] : []), (float) $template->width_mm, (float) $template->height_mm);
        $template->save();

        $this->audit('template.design_saved', $template, "Saved design of {$template->name}");

        return response()->json(['message' => 'Design saved.', 'savedAt' => now()->format('H:i:s')]);
    }

    /** Render the template with sample data (real school branding and, when available, a real student). */
    public function preview(Request $request, Model $template, PageComposer $composer): Response
    {
        $this->authorize('view', $template);

        $school = $this->sampleSchool($template);
        $data = DocumentData::sample($this->kind(), $template->holderType(), $school, $this->sampleHolder($school, $template->holderType()));
        $composed = $composer->compose($template, [$data], ['layout' => 'card', 'include_back' => true], $request->query('format') === 'pdf' ? 'pdf' : 'web');

        if ($request->query('format') === 'pdf') {
            return response($composer->pdf($composed, $template->name), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="template-preview.pdf"',
            ]);
        }

        return response($composer->html($composed, "Preview – {$template->name}", 'web', [
            ['label' => 'PDF preview', 'url' => route($this->routePrefix().'.preview', [$template, 'format' => 'pdf'])],
        ]));
    }

    /** Upload an image (background, decoration…) for use inside templates. */
    public function uploadAsset(Request $request, ImageService $images): JsonResponse
    {
        $this->authorize('templates.manage');

        $request->validate(['image' => ['required', ...ImageService::UPLOAD_RULES], 'kind' => ['nullable', Rule::in(['background', 'image'])]]);
        $folder = 'templates/'.($this->tenant()->scopedSchoolId() ?? 'global');
        $max = $request->input('kind') === 'background' ? 3508 : 1600; // A4 @ 300dpi long edge
        // JPEG uploads (photos, backgrounds) stay JPEG; PNG/WEBP keep transparency.
        $keepTransparency = $request->file('image')->getMimeType() !== 'image/jpeg';
        $path = $images->store($request->file('image'), $folder, $max, $keepTransparency);

        $this->audit('template.asset_uploaded', null, "Uploaded template image {$path}");

        return response()->json(['path' => $path, 'url' => public_storage_url($path)]);
    }

    /** @return array<string, mixed> */
    protected function shared(): array
    {
        return ['kind' => $this->kind(), 'routePrefix' => $this->routePrefix()];
    }

    protected function sampleSchool(Model $template): ?School
    {
        if ($template->school_id) {
            return School::find($template->school_id);
        }

        return $this->tenant()->school() ?? School::active()->orderBy('id')->first();
    }

    protected function sampleHolder(?School $school, string $holderType): Student|Staff|null
    {
        if (! $school) {
            return null;
        }
        $class = $holderType === 'staff' ? Staff::class : Student::class;

        return $class::forSchool($school->id)->whereNotNull('photo_path')->first() ?? $class::forSchool($school->id)->first();
    }

    /** Scale a preset designed for one size onto the chosen template size. */
    protected function resizeDesign(array $design, float $fromW, float $fromH, float $toW, float $toH): array
    {
        if (abs($fromW - $toW) < 0.01 && abs($fromH - $toH) < 0.01) {
            return $design;
        }

        $sx = $toW / $fromW;
        $sy = $toH / $fromH;
        $font = min($sx, $sy);
        foreach ($design as $side => $content) {
            foreach ($content['elements'] ?? [] as $i => $el) {
                $el['x'] = round($el['x'] * $sx, 2);
                $el['y'] = round($el['y'] * $sy, 2);
                $el['w'] = round($el['w'] * $sx, 2);
                $el['h'] = round($el['h'] * $sy, 2);
                if (isset($el['fontSize'])) {
                    $el['fontSize'] = round($el['fontSize'] * $font, 1);
                }
                $design[$side]['elements'][$i] = $el;
            }
        }

        return $design;
    }
}
