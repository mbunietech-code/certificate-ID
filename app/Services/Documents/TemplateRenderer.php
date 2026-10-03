<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Storage;

/**
 * Renders a template side (design JSON) into absolutely positioned HTML in
 * millimetres. The same markup feeds the browser print view and dompdf, so
 * layout math (image fitting, borders) is done here instead of relying on
 * CSS features dompdf lacks (object-fit, box-sizing).
 *
 * Element schema (all lengths in mm, font sizes in pt):
 *   common : id, type, x, y, w, h, rotation, z, opacity
 *   text   : content (with {placeholders}), fontFamily, fontSize, fontWeight, italic,
 *            color, align, lineHeight, letterSpacing, uppercase, shrink (fit to width), minFontSize
 *   image  : source (school_logo|photo|principal_signature|school_stamp|custom), src, fit (contain|cover|stretch),
 *            radius, borderWidth, borderColor
 *   qr     : content (default {verification_url}), color
 *   barcode: content (default {card_number}), color
 *   rect   : fill, borderWidth, borderColor, radius, ellipse(bool)
 *   line   : color, thickness
 * Colors are #rgb/#rrggbb or the tokens "@primary" / "@secondary" (the school's brand colors).
 */
class TemplateRenderer
{
    public const FONTS = [
        'helvetica' => ['Helvetica / Arial', 'helvetica, Arial, sans-serif'],
        'times' => ['Times', "times, 'Times New Roman', serif"],
        'courier' => ['Courier', "courier, 'Courier New', monospace"],
        'dejavu sans' => ['DejaVu Sans', "'DejaVu Sans', Verdana, sans-serif"],
        'dejavu serif' => ['DejaVu Serif', "'DejaVu Serif', Georgia, serif"],
    ];

    public const ELEMENT_TYPES = ['text', 'image', 'qr', 'barcode', 'rect', 'line'];

    public const IMAGE_SOURCES = [
        'school_logo' => 'School logo',
        'photo' => 'Photo (student / staff)',
        'principal_signature' => 'Principal signature',
        'school_stamp' => 'School stamp',
        'custom' => 'Uploaded image',
    ];

    /** @var array<string, array{0: int, 1: int}|null> */
    private array $sizeCache = [];

    /** @var array<string, string> */
    private array $palette = [];

    public function __construct(private CodeImageService $codes, private TextMeasurer $measurer) {}

    /**
     * @param  array<string, mixed>  $side  {background: {color, image}, elements: []}
     * @param  string  $mode  "pdf" (filesystem image paths) or "web" (URLs)
     */
    public function renderSide(array $side, float $w, float $h, DocumentData $data, string $mode = 'web'): string
    {
        $this->palette = $data->colors;
        $html = '';
        $bg = is_array($side['background'] ?? null) ? $side['background'] : [];
        $bgColor = $this->color($bg['color'] ?? null, '#ffffff');
        $html .= sprintf('<div style="position:absolute;left:0;top:0;width:%smm;height:%smm;background-color:%s;"></div>', $this->n($w), $this->n($h), $bgColor);

        if (! empty($bg['image']) && ($src = $this->imageSrc((string) $bg['image'], $mode))) {
            $html .= sprintf('<img src="%s" alt="" style="position:absolute;left:0;top:0;width:%smm;height:%smm;">', e($src), $this->n($w), $this->n($h));
        }

        $elements = array_values(array_filter($side['elements'] ?? [], 'is_array'));
        // Stable sort by z so later elements win ties, matching the designer.
        $indexed = array_map(null, $elements, array_keys($elements));
        usort($indexed, fn ($a, $b) => [(int) ($a[0]['z'] ?? 0), $a[1]] <=> [(int) ($b[0]['z'] ?? 0), $b[1]]);

        foreach ($indexed as [$el]) {
            $html .= $this->renderElement($el, $data, $mode);
        }

        return $html;
    }

    /** @param  array<string, mixed>  $el */
    private function renderElement(array $el, DocumentData $data, string $mode): string
    {
        $type = $el['type'] ?? null;
        $x = (float) ($el['x'] ?? 0);
        $y = (float) ($el['y'] ?? 0);
        $w = max(0.1, (float) ($el['w'] ?? 10));
        $h = max(0.1, (float) ($el['h'] ?? 5));

        $base = sprintf('position:absolute;left:%smm;top:%smm;', $this->n($x), $this->n($y));
        $rotation = (float) ($el['rotation'] ?? 0);
        if ($rotation != 0.0) {
            $base .= sprintf('transform:rotate(%sdeg);transform-origin:50%% 50%%;', $this->n($rotation));
        }
        $opacity = isset($el['opacity']) ? max(0, min(1, (float) $el['opacity'])) : 1;
        if ($opacity < 1) {
            $base .= 'opacity:'.$this->n($opacity).';';
        }

        return match ($type) {
            'text' => $this->text($el, $base, $w, $h, $data),
            'image' => $this->image($el, $base, $w, $h, $data, $mode),
            'qr' => $this->qr($el, $base, $w, $h, $data),
            'barcode' => $this->barcode($el, $base, $w, $h, $data),
            'rect' => $this->rect($el, $base, $w, $h),
            'line' => $this->line($el, $base, $w),
            default => '',
        };
    }

    private function text(array $el, string $base, float $w, float $h, DocumentData $data): string
    {
        $family = array_key_exists($el['fontFamily'] ?? '', self::FONTS) ? $el['fontFamily'] : 'helvetica';
        $font = self::FONTS[$family][1];
        $size = max(2, min(200, (float) ($el['fontSize'] ?? 10)));

        // Shrink to fit: reduce the font size until the longest line fits the box width (no wrapping).
        $shrink = ! empty($el['shrink']);
        if ($shrink) {
            $plain = $this->substitutePlain((string) ($el['content'] ?? ''), $data->text);
            if (! empty($el['uppercase'])) {
                $plain = mb_strtoupper($plain);
            }
            $size = $this->measurer->fitSize(
                preg_split('/\R/', $plain), $w, $family,
                ($el['fontWeight'] ?? 'normal') === 'bold', ! empty($el['italic']),
                $size, max(2, min($size, (float) ($el['minFontSize'] ?? $size / 2))), (float) ($el['letterSpacing'] ?? 0),
            );
        }
        $align = in_array($el['align'] ?? 'left', ['left', 'center', 'right', 'justify'], true) ? $el['align'] : 'left';
        $style = $base.sprintf(
            'width:%smm;height:%smm;overflow:hidden;font-family:%s;font-size:%spt;line-height:%s;color:%s;text-align:%s;font-weight:%s;font-style:%s;',
            $this->n($w), $this->n($h), $font, $this->n($size), $this->n(max(0.8, min(3, (float) ($el['lineHeight'] ?? 1.2)))),
            $this->color($el['color'] ?? null, '#000000'), $align,
            ($el['fontWeight'] ?? 'normal') === 'bold' ? 'bold' : 'normal',
            ! empty($el['italic']) ? 'italic' : 'normal',
        );
        if (! empty($el['uppercase'])) {
            $style .= 'text-transform:uppercase;';
        }
        if ($shrink) {
            $style .= 'white-space:nowrap;';
        }
        if (! empty($el['letterSpacing'])) {
            $style .= 'letter-spacing:'.$this->n((float) $el['letterSpacing']).'pt;';
        }

        return '<div style="'.$style.'">'.$this->substitute((string) ($el['content'] ?? ''), $data->text).'</div>';
    }

    private function image(array $el, string $base, float $w, float $h, DocumentData $data, string $mode): string
    {
        $source = $el['source'] ?? 'custom';
        $path = $source === 'custom' ? ($el['src'] ?? null) : ($data->images[$source] ?? null);
        $src = $path ? $this->imageSrc((string) $path, $mode) : null;

        $border = max(0, (float) ($el['borderWidth'] ?? 0));
        $radius = max(0, (float) ($el['radius'] ?? 0));
        $innerW = max(0.1, $w - 2 * $border);
        $innerH = max(0.1, $h - 2 * $border);
        $box = $base.sprintf('width:%smm;height:%smm;overflow:hidden;', $this->n($innerW), $this->n($innerH));
        if ($border > 0) {
            $box .= sprintf('border:%smm solid %s;', $this->n($border), $this->color($el['borderColor'] ?? null, '#000000'));
        }
        if ($radius > 0) {
            $box .= sprintf('border-radius:%smm;', $this->n($radius));
        }

        if (! $src) {
            // Missing photo shows a neutral placeholder so the layout is still readable.
            if ($source !== 'photo') {
                return '';
            }

            $head = $innerW * 0.42;
            $bodyW = $innerW * 0.74;
            $bodyH = $innerH * 0.6;

            return '<div style="'.$box.'background-color:#d5e8ea;">'
                .sprintf('<div style="position:absolute;left:%smm;top:%smm;width:%smm;height:%smm;border-radius:%smm;background-color:#9bbfc5;"></div>',
                    $this->n(($innerW - $head) / 2), $this->n($innerH * 0.14), $this->n($head), $this->n($head), $this->n($head / 2))
                .sprintf('<div style="position:absolute;left:%smm;top:%smm;width:%smm;height:%smm;border-radius:%smm / %smm;background-color:#9bbfc5;"></div>',
                    $this->n(($innerW - $bodyW) / 2), $this->n($innerH * 0.66), $this->n($bodyW), $this->n($bodyH), $this->n($bodyW / 2), $this->n($bodyH / 2))
                .'</div>';
        }

        [$iw, $ih, $ix, $iy] = $this->fit($el['fit'] ?? ($source === 'photo' ? 'cover' : 'contain'), $innerW, $innerH, $path);
        $img = sprintf('<img src="%s" alt="" style="position:absolute;left:%smm;top:%smm;width:%smm;height:%smm;">', e($src), $this->n($ix), $this->n($iy), $this->n($iw), $this->n($ih));

        return '<div style="'.$box.'">'.$img.'</div>';
    }

    private function qr(array $el, string $base, float $w, float $h, DocumentData $data): string
    {
        $content = trim($this->substitutePlain((string) ($el['content'] ?? '{verification_url}'), $data->text)) ?: $data->qr;
        $size = min($w, $h);
        $uri = $this->codes->qr($content, $this->color($el['color'] ?? null, '#000000'));

        return sprintf('<img src="%s" alt="" style="%swidth:%smm;height:%smm;">', $uri, $base, $this->n($size), $this->n($size));
    }

    private function barcode(array $el, string $base, float $w, float $h, DocumentData $data): string
    {
        $content = trim($this->substitutePlain((string) ($el['content'] ?? '{card_number}'), $data->text));
        $uri = $this->codes->barcode($content, $this->color($el['color'] ?? null, '#000000'));
        if (! $uri) {
            return '';
        }

        return sprintf('<img src="%s" alt="" style="%swidth:%smm;height:%smm;">', $uri, $base, $this->n($w), $this->n($h));
    }

    private function rect(array $el, string $base, float $w, float $h): string
    {
        $border = max(0, (float) ($el['borderWidth'] ?? 0));
        $style = $base.sprintf('width:%smm;height:%smm;', $this->n(max(0, $w - 2 * $border)), $this->n(max(0, $h - 2 * $border)));
        $fill = $el['fill'] ?? null;
        if ($fill && $fill !== 'transparent') {
            $style .= 'background-color:'.$this->color($fill, '#ffffff').';';
        }
        if ($border > 0) {
            $style .= sprintf('border:%smm solid %s;', $this->n($border), $this->color($el['borderColor'] ?? null, '#000000'));
        }
        if (! empty($el['ellipse'])) {
            $style .= sprintf('border-radius:%smm / %smm;', $this->n($w / 2), $this->n($h / 2));
        } elseif (($radius = (float) ($el['radius'] ?? 0)) > 0) {
            $style .= sprintf('border-radius:%smm;', $this->n($radius));
        }

        return '<div style="'.$style.'"></div>';
    }

    private function line(array $el, string $base, float $w): string
    {
        $thickness = max(0.05, min(20, (float) ($el['thickness'] ?? 0.3)));

        return sprintf('<div style="%swidth:%smm;height:%smm;background-color:%s;font-size:0;line-height:0;"></div>',
            $base, $this->n($w), $this->n($thickness), $this->color($el['color'] ?? null, '#000000'));
    }

    /** Escape, replace {placeholders} with escaped values, keep line breaks. */
    public function substitute(string $content, array $values): string
    {
        $escaped = e($content);

        $replaced = preg_replace_callback('/\{([a-z_]+)\}/', function ($m) use ($values) {
            return array_key_exists($m[1], $values) ? e((string) $values[$m[1]]) : $m[0];
        }, $escaped);

        return nl2br($replaced, false);
    }

    private function substitutePlain(string $content, array $values): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => array_key_exists($m[1], $values) ? (string) $values[$m[1]] : $m[0], $content);
    }

    /**
     * Compute image box for the fit mode.
     *
     * @return array{0: float, 1: float, 2: float, 3: float} width, height, left, top in mm
     */
    private function fit(string $mode, float $w, float $h, string $path): array
    {
        $natural = $this->naturalSize($path);
        if ($mode === 'stretch' || ! $natural) {
            return [$w, $h, 0, 0];
        }

        [$nw, $nh] = $natural;
        $scale = $mode === 'cover' ? max($w / $nw, $h / $nh) : min($w / $nw, $h / $nh);
        $iw = $nw * $scale;
        $ih = $nh * $scale;

        return [$iw, $ih, ($w - $iw) / 2, ($h - $ih) / 2];
    }

    /** @return array{0: int, 1: int}|null */
    private function naturalSize(string $path): ?array
    {
        if (! array_key_exists($path, $this->sizeCache)) {
            $abs = $this->safePath($path);
            $info = $abs ? @getimagesize($abs) : false;
            $this->sizeCache[$path] = $info ? [$info[0], $info[1]] : null;
        }

        return $this->sizeCache[$path];
    }

    public function imageSrc(string $path, string $mode): ?string
    {
        $abs = $this->safePath($path);
        if (! $abs) {
            return null;
        }

        return $mode === 'pdf' ? $abs : public_storage_url($path);
    }

    /** Only files that exist inside the public disk are ever rendered. */
    private function safePath(string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, ':')) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($path) ? $disk->path($path) : null;
    }

    private function color(mixed $value, string $fallback): string
    {
        if (is_string($value) && str_starts_with($value, '@')) {
            $value = $this->palette[substr($value, 1)] ?? ['primary' => '#1e3a8a', 'secondary' => '#b45309'][substr($value, 1)] ?? null;
        }

        return is_string($value) && preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) ? $value : $fallback;
    }

    /** Locale-independent number for CSS. */
    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.') ?: '0';
    }
}
