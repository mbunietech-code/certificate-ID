<?php

namespace App\Services\Documents;

use App\Models\IdCard;
use GdImage;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;

/** Renders a document side to a PNG image for downloads and printer tests. */
class DocumentPngRenderer
{
    private const DEFAULT_DPI = 300;

    /** @var array<string, string> */
    private array $palette = [];

    public function __construct(private CodeImageService $codes) {}

    public function idCardFront(IdCard $card, int $dpi = self::DEFAULT_DPI): string
    {
        $template = $card->template;
        if (! $template) {
            throw new RuntimeException('This ID card has no template.');
        }

        $sides = $template->sides();
        if (! isset($sides['front']) || ! is_array($sides['front'])) {
            throw new RuntimeException('This ID card template has no front side.');
        }

        return $this->renderSide(
            $sides['front'],
            (float) $template->width_mm,
            (float) $template->height_mm,
            DocumentData::forIdCard($card),
            $dpi,
        );
    }

    /** @param  array<string, mixed>  $side */
    public function renderSide(array $side, float $widthMm, float $heightMm, DocumentData $data, int $dpi = self::DEFAULT_DPI): string
    {
        $dpi = max(72, min(600, $dpi));
        $scale = $dpi / 25.4;
        $this->palette = $data->colors;

        $image = $this->canvas($this->px($widthMm, $scale), $this->px($heightMm, $scale));
        $background = is_array($side['background'] ?? null) ? $side['background'] : [];
        $this->fill($image, $this->color($image, $background['color'] ?? '#ffffff'));

        if (! empty($background['image']) && ($source = $this->loadImage((string) $background['image']))) {
            $this->pasteImage($image, $source, 0, 0, imagesx($image), imagesy($image), 'stretch');
            imagedestroy($source);
        }

        $elements = array_values(array_filter($side['elements'] ?? [], 'is_array'));
        $indexed = array_map(null, $elements, array_keys($elements));
        usort($indexed, fn ($a, $b) => [(int) ($a[0]['z'] ?? 0), $a[1]] <=> [(int) ($b[0]['z'] ?? 0), $b[1]]);

        foreach ($indexed as [$element]) {
            $this->drawElement($image, $element, $data, $scale, $dpi);
        }

        return $this->png($image);
    }

    /** @param  array<string, mixed>  $element */
    private function drawElement(GdImage $canvas, array $element, DocumentData $data, float $scale, int $dpi): void
    {
        $type = $element['type'] ?? null;
        $x = $this->px((float) ($element['x'] ?? 0), $scale);
        $y = $this->px((float) ($element['y'] ?? 0), $scale);
        $w = max(1, $this->px((float) ($element['w'] ?? 1), $scale));
        $h = max(1, $this->px((float) ($element['h'] ?? 1), $scale));
        $opacity = isset($element['opacity']) ? max(0.0, min(1.0, (float) $element['opacity'])) : 1.0;

        match ($type) {
            'text' => $this->drawText($canvas, $element, $data, $x, $y, $w, $h, $dpi, $opacity),
            'image' => $this->drawImageElement($canvas, $element, $data, $x, $y, $w, $h, $scale, $opacity),
            'qr' => $this->drawCode($canvas, $element, $data, $x, $y, $w, $h, 'qr', $opacity),
            'barcode' => $this->drawCode($canvas, $element, $data, $x, $y, $w, $h, 'barcode', $opacity),
            'rect' => $this->drawRectElement($canvas, $element, $x, $y, $w, $h, $scale, $opacity),
            'line' => $this->drawLine($canvas, $element, $x, $y, $w, $scale, $opacity),
            default => null,
        };
    }

    /** @param  array<string, mixed>  $element */
    private function drawText(GdImage $canvas, array $element, DocumentData $data, int $x, int $y, int $w, int $h, int $dpi, float $opacity): void
    {
        $text = $this->substitutePlain((string) ($element['content'] ?? ''), $data->text);
        if (! empty($element['uppercase'])) {
            $text = mb_strtoupper($text);
        }

        $family = (string) ($element['fontFamily'] ?? 'helvetica');
        $bold = ($element['fontWeight'] ?? 'normal') === 'bold';
        $italic = ! empty($element['italic']);
        $font = $this->fontPath($family, $bold, $italic);
        $fontSize = max(2.0, (float) ($element['fontSize'] ?? 10) * $dpi / 72);
        $minFontSize = max(2.0, (float) ($element['minFontSize'] ?? ($fontSize / 2)) * $dpi / 72);
        $lines = preg_split('/\R/', $text) ?: [''];

        if (! empty($element['shrink'])) {
            while ($fontSize > $minFontSize && $this->widestLine($lines, $fontSize, $font) > $w) {
                $fontSize -= 0.5;
            }
        }

        $box = $this->canvas($w, $h);
        $color = $this->color($box, $element['color'] ?? '#000000', $opacity);
        $lineHeight = max(0.8, min(3.0, (float) ($element['lineHeight'] ?? 1.2)));
        $step = $fontSize * $lineHeight;
        $metrics = $this->textBox('Ag', $fontSize, $font);
        $baseline = max(1.0, -$metrics[7]);
        $align = in_array($element['align'] ?? 'left', ['left', 'center', 'right'], true) ? $element['align'] : 'left';

        foreach ($lines as $index => $line) {
            $lineWidth = $this->textWidth($line, $fontSize, $font);
            $lineX = match ($align) {
                'center' => (int) round(($w - $lineWidth) / 2),
                'right' => (int) round($w - $lineWidth),
                default => 0,
            };
            $lineY = (int) round($baseline + $index * $step);
            if ($lineY > $h + $fontSize) {
                break;
            }
            imagettftext($box, $fontSize, 0, max(0, $lineX), $lineY, $color, $font, $line);
        }

        imagecopy($canvas, $box, $x, $y, 0, 0, $w, $h);
        imagedestroy($box);
    }

    /** @param  array<string, mixed>  $element */
    private function drawImageElement(GdImage $canvas, array $element, DocumentData $data, int $x, int $y, int $w, int $h, float $scale, float $opacity): void
    {
        $border = max(0, $this->px((float) ($element['borderWidth'] ?? 0), $scale));
        $radius = max(0, $this->px((float) ($element['radius'] ?? 0), $scale));
        $innerX = $x + $border;
        $innerY = $y + $border;
        $innerW = max(1, $w - 2 * $border);
        $innerH = max(1, $h - 2 * $border);
        $source = (string) ($element['source'] ?? 'custom');
        $path = $source === 'custom' ? ($element['src'] ?? null) : ($data->images[$source] ?? null);

        if ($border > 0) {
            $this->roundedRectangle($canvas, $x, $y, $x + $w - 1, $y + $h - 1, $radius, $this->color($canvas, $element['borderColor'] ?? '#000000', $opacity));
        }

        if (! $path || ! ($image = $this->loadImage((string) $path))) {
            if ($source === 'photo') {
                $this->drawPhotoPlaceholder($canvas, $innerX, $innerY, $innerW, $innerH, $opacity);
            }

            return;
        }

        $this->pasteImage($canvas, $image, $innerX, $innerY, $innerW, $innerH, (string) ($element['fit'] ?? ($source === 'photo' ? 'cover' : 'contain')));
        imagedestroy($image);
    }

    /** @param  array<string, mixed>  $element */
    private function drawCode(GdImage $canvas, array $element, DocumentData $data, int $x, int $y, int $w, int $h, string $kind, float $opacity): void
    {
        $content = trim($this->substitutePlain((string) ($element['content'] ?? ($kind === 'qr' ? '{verification_url}' : '{card_number}')), $data->text));
        if ($content === '') {
            return;
        }

        $color = $this->resolveColor($element['color'] ?? '#000000');
        $uri = $kind === 'qr'
            ? $this->codes->qr($content, $color, max($w, $h))
            : $this->codes->barcode($content, $color);

        if (! $uri || ! ($image = $this->loadDataUri($uri))) {
            return;
        }

        $sizeW = $kind === 'qr' ? min($w, $h) : $w;
        $sizeH = $kind === 'qr' ? min($w, $h) : $h;
        $this->pasteImage($canvas, $image, $x, $y, $sizeW, $sizeH, 'stretch', $opacity);
        imagedestroy($image);
    }

    /** @param  array<string, mixed>  $element */
    private function drawRectElement(GdImage $canvas, array $element, int $x, int $y, int $w, int $h, float $scale, float $opacity): void
    {
        $border = max(0, $this->px((float) ($element['borderWidth'] ?? 0), $scale));
        $fill = $element['fill'] ?? null;
        $radius = ! empty($element['ellipse'])
            ? (int) round(min($w, $h) / 2)
            : max(0, $this->px((float) ($element['radius'] ?? 0), $scale));

        if ($fill && $fill !== 'transparent') {
            $color = $this->color($canvas, $fill, $opacity);
            if (! empty($element['ellipse'])) {
                imagefilledellipse($canvas, $x + (int) round($w / 2), $y + (int) round($h / 2), $w, $h, $color);
            } else {
                $this->filledRoundedRectangle($canvas, $x, $y, $x + $w - 1, $y + $h - 1, $radius, $color);
            }
        }

        if ($border > 0) {
            imagesetthickness($canvas, $border);
            $color = $this->color($canvas, $element['borderColor'] ?? '#000000', $opacity);
            if (! empty($element['ellipse'])) {
                imageellipse($canvas, $x + (int) round($w / 2), $y + (int) round($h / 2), $w - $border, $h - $border, $color);
            } else {
                $this->roundedRectangle($canvas, $x, $y, $x + $w - 1, $y + $h - 1, $radius, $color);
            }
            imagesetthickness($canvas, 1);
        }
    }

    /** @param  array<string, mixed>  $element */
    private function drawLine(GdImage $canvas, array $element, int $x, int $y, int $w, float $scale, float $opacity): void
    {
        $thickness = max(1, $this->px((float) ($element['thickness'] ?? 0.3), $scale));
        imagefilledrectangle($canvas, $x, $y, $x + $w - 1, $y + $thickness - 1, $this->color($canvas, $element['color'] ?? '#000000', $opacity));
    }

    private function pasteImage(GdImage $canvas, GdImage $source, int $x, int $y, int $w, int $h, string $fit, float $opacity = 1.0): void
    {
        $sw = imagesx($source);
        $sh = imagesy($source);
        if ($sw <= 0 || $sh <= 0) {
            return;
        }

        $ratio = match ($fit) {
            'cover' => max($w / $sw, $h / $sh),
            'contain' => min($w / $sw, $h / $sh),
            default => null,
        };
        $dw = $ratio === null ? $w : max(1, (int) round($sw * $ratio));
        $dh = $ratio === null ? $h : max(1, (int) round($sh * $ratio));

        $layer = $this->canvas($w, $h);
        imagecopyresampled($layer, $source, (int) round(($w - $dw) / 2), (int) round(($h - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);

        if ($opacity < 1) {
            imagecopymerge($canvas, $layer, $x, $y, 0, 0, $w, $h, (int) round($opacity * 100));
        } else {
            imagecopy($canvas, $layer, $x, $y, 0, 0, $w, $h);
        }

        imagedestroy($layer);
    }

    private function drawPhotoPlaceholder(GdImage $canvas, int $x, int $y, int $w, int $h, float $opacity): void
    {
        imagefilledrectangle($canvas, $x, $y, $x + $w - 1, $y + $h - 1, $this->color($canvas, '#d5e8ea', $opacity));
        imagefilledellipse($canvas, $x + (int) round($w / 2), $y + (int) round($h * 0.32), (int) round($w * 0.42), (int) round($w * 0.42), $this->color($canvas, '#9bbfc5', $opacity));
        imagefilledellipse($canvas, $x + (int) round($w / 2), $y + (int) round($h * 0.92), (int) round($w * 0.74), (int) round($h * 0.58), $this->color($canvas, '#9bbfc5', $opacity));
    }

    private function canvas(int $w, int $h): GdImage
    {
        $image = imagecreatetruecolor(max(1, $w), max(1, $h));
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        imagealphablending($image, true);

        return $image;
    }

    private function color(GdImage $image, mixed $value, float $opacity = 1.0): int
    {
        [$r, $g, $b, $alpha] = $this->rgba($value);
        $alpha = min(127, max(0, $alpha + (int) round((1 - $opacity) * 127)));

        return imagecolorallocatealpha($image, $r, $g, $b, $alpha);
    }

    /** @return array{0: int, 1: int, 2: int, 3: int} */
    private function rgba(mixed $value): array
    {
        $hex = $this->resolveColor($value);
        if (strlen($hex) === 4) {
            $hex = '#'.$hex[1].$hex[1].$hex[2].$hex[2].$hex[3].$hex[3];
        }

        return [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
            0,
        ];
    }

    private function resolveColor(mixed $value): string
    {
        if (is_string($value) && str_starts_with($value, '@')) {
            $value = $this->palette[substr($value, 1)] ?? null;
        }

        return is_string($value) && preg_match('/^#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?$/', $value) ? $value : '#000000';
    }

    private function fill(GdImage $image, int $color): void
    {
        imagefilledrectangle($image, 0, 0, imagesx($image), imagesy($image), $color);
    }

    private function loadImage(string $path): ?GdImage
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || str_contains($path, '..') || str_contains($path, ':')) {
            return null;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        return $this->imageFromString($disk->get($path));
    }

    private function loadDataUri(string $uri): ?GdImage
    {
        if (! preg_match('/^data:image\/png;base64,(.+)$/', $uri, $matches)) {
            return null;
        }

        $contents = base64_decode($matches[1], true);

        return is_string($contents) ? $this->imageFromString($contents) : null;
    }

    private function imageFromString(string $contents): ?GdImage
    {
        $image = @imagecreatefromstring($contents);
        if (! $image instanceof GdImage) {
            return null;
        }
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    /** @return array<int, int> */
    private function textBox(string $text, float $size, string $font): array
    {
        $box = imagettfbbox($size, 0, $font, $text);
        if ($box === false) {
            throw new InvalidArgumentException('Unable to measure text.');
        }

        return $box;
    }

    /** @param  array<int, string>  $lines */
    private function widestLine(array $lines, float $size, string $font): float
    {
        return max(array_map(fn (string $line): float => $this->textWidth($line, $size, $font), $lines) ?: [0]);
    }

    private function textWidth(string $text, float $size, string $font): float
    {
        $box = $this->textBox($text === '' ? ' ' : $text, $size, $font);

        return abs($box[2] - $box[0]);
    }

    private function fontPath(string $family, bool $bold, bool $italic): string
    {
        $base = base_path('vendor/dompdf/dompdf/lib/fonts');
        $family = mb_strtolower($family);
        $prefix = str_contains($family, 'courier') || str_contains($family, 'mono')
            ? 'DejaVuSansMono'
            : (str_contains($family, 'times') || str_contains($family, 'serif') ? 'DejaVuSerif' : 'DejaVuSans');
        $suffix = $bold && $italic ? '-BoldOblique' : ($bold ? '-Bold' : ($italic ? '-Oblique' : ''));

        return "{$base}/{$prefix}{$suffix}.ttf";
    }

    private function substitutePlain(string $content, array $values): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', fn ($matches) => array_key_exists($matches[1], $values) ? (string) $values[$matches[1]] : $matches[0], $content);
    }

    private function px(float $mm, float $scale): int
    {
        return (int) round($mm * $scale);
    }

    private function filledRoundedRectangle(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        if ($radius <= 0) {
            imagefilledrectangle($image, $x1, $y1, $x2, $y2, $color);

            return;
        }

        $radius = min($radius, (int) floor(($x2 - $x1) / 2), (int) floor(($y2 - $y1) / 2));
        imagefilledrectangle($image, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
        imagefilledrectangle($image, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagefilledellipse($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
        imagefilledellipse($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color);
    }

    private function roundedRectangle(GdImage $image, int $x1, int $y1, int $x2, int $y2, int $radius, int $color): void
    {
        if ($radius <= 0) {
            imagerectangle($image, $x1, $y1, $x2, $y2, $color);

            return;
        }

        imageline($image, $x1 + $radius, $y1, $x2 - $radius, $y1, $color);
        imageline($image, $x1 + $radius, $y2, $x2 - $radius, $y2, $color);
        imageline($image, $x1, $y1 + $radius, $x1, $y2 - $radius, $color);
        imageline($image, $x2, $y1 + $radius, $x2, $y2 - $radius, $color);
        imagearc($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color);
        imagearc($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color);
        imagearc($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color);
        imagearc($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color);
    }

    private function png(GdImage $image): string
    {
        ob_start();
        imagepng($image, null, 6);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
