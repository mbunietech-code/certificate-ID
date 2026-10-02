<?php

namespace App\Services\Documents;

use Illuminate\Validation\ValidationException;

/**
 * Whitelists and clamps a design posted by the template designer so only
 * known element types/properties with sane values are ever stored.
 */
class DesignSanitizer
{
    public const MAX_ELEMENTS = 300;

    private const COMMON = ['id', 'type', 'x', 'y', 'w', 'h', 'rotation', 'z', 'opacity', 'locked', 'name'];

    private const BY_TYPE = [
        'text' => ['content', 'fontFamily', 'fontSize', 'fontWeight', 'italic', 'color', 'align', 'lineHeight', 'letterSpacing', 'uppercase', 'shrink', 'minFontSize'],
        'image' => ['source', 'src', 'fit', 'radius', 'borderWidth', 'borderColor'],
        'qr' => ['content', 'color'],
        'barcode' => ['content', 'color'],
        'rect' => ['fill', 'borderWidth', 'borderColor', 'radius', 'ellipse'],
        'line' => ['color', 'thickness'],
    ];

    /**
     * @param  array<string, mixed>  $design
     * @param  array<int, string>  $sides
     * @return array<string, mixed>
     */
    public function clean(array $design, array $sides, float $maxW, float $maxH): array
    {
        $clean = [];
        $total = 0;

        foreach ($sides as $side) {
            $input = is_array($design[$side] ?? null) ? $design[$side] : [];
            $bg = is_array($input['background'] ?? null) ? $input['background'] : [];

            $elements = [];
            foreach (array_values(is_array($input['elements'] ?? null) ? $input['elements'] : []) as $i => $el) {
                if (! is_array($el) || ! isset(self::BY_TYPE[$el['type'] ?? ''])) {
                    continue;
                }
                if (++$total > self::MAX_ELEMENTS) {
                    throw ValidationException::withMessages(['design' => 'A template may contain at most '.self::MAX_ELEMENTS.' elements.']);
                }
                $elements[] = $this->element($el, $i, $maxW, $maxH);
            }

            $clean[$side] = [
                'background' => [
                    'color' => $this->color($bg['color'] ?? '#ffffff') ?? '#ffffff',
                    'image' => $this->assetPath($bg['image'] ?? null),
                ],
                'elements' => $elements,
            ];
        }

        return $clean;
    }

    /** @param  array<string, mixed>  $el */
    private function element(array $el, int $index, float $maxW, float $maxH): array
    {
        $type = $el['type'];
        $allowed = array_merge(self::COMMON, self::BY_TYPE[$type]);
        $out = array_intersect_key($el, array_flip($allowed));

        $out['id'] = preg_match('/^[A-Za-z0-9_-]{1,40}$/', (string) ($el['id'] ?? '')) ? $el['id'] : 'el'.$index;
        $out['x'] = $this->num($el['x'] ?? 0, -$maxW, $maxW * 2);
        $out['y'] = $this->num($el['y'] ?? 0, -$maxH, $maxH * 2);
        $out['w'] = $this->num($el['w'] ?? 10, 0.1, $maxW * 3);
        $out['h'] = $this->num($el['h'] ?? 5, 0.05, $maxH * 3);
        $out['rotation'] = $this->num($el['rotation'] ?? 0, -360, 360);
        $out['z'] = (int) $this->num($el['z'] ?? $index, -1000, 10000);
        if (isset($el['opacity'])) {
            $out['opacity'] = $this->num($el['opacity'], 0, 1);
        }
        if (isset($el['name'])) {
            $out['name'] = mb_substr((string) $el['name'], 0, 60);
        }
        $out['locked'] = ! empty($el['locked']);

        foreach (['content'] as $key) {
            if (array_key_exists($key, $out)) {
                $out[$key] = mb_substr((string) $out[$key], 0, 3000);
            }
        }
        foreach (['color', 'borderColor', 'fill'] as $key) {
            if (array_key_exists($key, $out)) {
                $out[$key] = $key === 'fill' && $out[$key] === 'transparent' ? 'transparent' : ($this->color($out[$key]) ?? '#000000');
            }
        }

        if ($type === 'text') {
            $out['fontFamily'] = array_key_exists($out['fontFamily'] ?? '', TemplateRenderer::FONTS) ? $out['fontFamily'] : 'helvetica';
            $out['fontSize'] = $this->num($out['fontSize'] ?? 10, 2, 200);
            $out['fontWeight'] = ($out['fontWeight'] ?? '') === 'bold' ? 'bold' : 'normal';
            $out['align'] = in_array($out['align'] ?? '', ['left', 'center', 'right', 'justify'], true) ? $out['align'] : 'left';
            $out['lineHeight'] = $this->num($out['lineHeight'] ?? 1.2, 0.8, 3);
            $out['letterSpacing'] = $this->num($out['letterSpacing'] ?? 0, -2, 20);
            $out['italic'] = ! empty($out['italic']);
            $out['uppercase'] = ! empty($out['uppercase']);
            $out['shrink'] = ! empty($out['shrink']);
            $out['minFontSize'] = $this->num($out['minFontSize'] ?? $out['fontSize'] / 2, 2, $out['fontSize']);
        }
        if ($type === 'image') {
            $out['source'] = array_key_exists($out['source'] ?? '', TemplateRenderer::IMAGE_SOURCES) ? $out['source'] : 'custom';
            $out['src'] = $out['source'] === 'custom' ? $this->assetPath($out['src'] ?? null) : null;
            $out['fit'] = in_array($out['fit'] ?? '', ['contain', 'cover', 'stretch'], true) ? $out['fit'] : 'contain';
        }
        foreach (['radius', 'borderWidth'] as $key) {
            if (array_key_exists($key, $out)) {
                $out[$key] = $this->num($out[$key], 0, 100);
            }
        }
        if ($type === 'rect') {
            $out['ellipse'] = ! empty($out['ellipse']);
        }
        if ($type === 'line') {
            $out['thickness'] = $this->num($out['thickness'] ?? 0.3, 0.05, 20);
        }

        return $out;
    }

    private function num(mixed $value, float $min, float $max): float
    {
        $n = is_numeric($value) ? (float) $value : 0.0;

        return round(max($min, min($max, $n)), 3);
    }

    private function color(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        if (in_array($value, ['@primary', '@secondary'], true)) {
            return $value;
        }

        return preg_match('/^#[0-9a-fA-F]{6}$|^#[0-9a-fA-F]{3}$/', $value) ? $value : null;
    }

    /** Uploaded template assets live under templates/ on the public disk. */
    private function assetPath(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return preg_match('#^templates/[A-Za-z0-9_\-/]+\.(png|jpg)$#', $value) && ! str_contains($value, '..') ? $value : null;
    }
}
