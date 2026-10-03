<?php

namespace App\Services\Documents;

use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Generic artwork for marketing samples: illustrated avatars (never real
 * photos), neutral emblems, a handwritten-style signature and abstract
 * header/watermark patterns. Drawn with GD once and stored on the public
 * disk under samples/ so the regular TemplateRenderer can use them.
 */
class SampleAssets
{
    public const DIR = 'samples/v1';

    /** Supersampling factor for smooth GD shapes. */
    private const SS = 4;

    /** @var array<int, array{bg: string, skin: string, hair: string, shirt: string, long: bool}> */
    private const AVATARS = [
        ['bg' => '#dbeafe', 'skin' => '#8d5524', 'hair' => '#1f1a17', 'shirt' => '#1e3a8a', 'long' => false],
        ['bg' => '#fef3c7', 'skin' => '#a0662f', 'hair' => '#22180f', 'shirt' => '#0f766e', 'long' => true],
        ['bg' => '#e0e7ff', 'skin' => '#6b4423', 'hair' => '#111111', 'shirt' => '#7f1d1d', 'long' => false],
        ['bg' => '#dcfce7', 'skin' => '#c68642', 'hair' => '#2d1d12', 'shirt' => '#334155', 'long' => true],
    ];

    /** @return array{avatars: array<int, string>, emblems: array<string, string>, signature: string, header: string, watermark: string} */
    public function ensure(): array
    {
        $disk = Storage::disk('public');
        $paths = [
            'avatars' => array_map(fn ($i) => self::DIR."/avatar-{$i}.png", array_keys(self::AVATARS)),
            'emblems' => [],
            'signature' => self::DIR.'/signature.png',
            'header' => self::DIR.'/header.png',
            'watermark' => self::DIR.'/watermark.png',
        ];

        foreach (self::AVATARS as $i => $spec) {
            if (! $disk->exists($paths['avatars'][$i])) {
                $disk->put($paths['avatars'][$i], $this->png($this->avatar($spec)));
            }
        }

        foreach (['navy' => ['#1e3a8a', '#f59e0b'], 'teal' => ['#0f766e', '#eab308'], 'maroon' => ['#7f1d1d', '#d97706'], 'slate' => ['#334155', '#38bdf8']] as $name => [$fill, $ring]) {
            $path = self::DIR."/emblem-{$name}.png";
            if (! $disk->exists($path)) {
                $disk->put($path, $this->png($this->emblem($fill, $ring)));
            }
            $paths['emblems'][$name] = $path;
        }

        foreach (['signature' => fn () => $this->signature(), 'header' => fn () => $this->header(), 'watermark' => fn () => $this->watermark()] as $key => $draw) {
            if (! $disk->exists($paths[$key])) {
                $disk->put($paths[$key], $this->png($draw()));
            }
        }

        return $paths;
    }

    /** @param  array{bg: string, skin: string, hair: string, shirt: string, long: bool}  $s */
    private function avatar(array $s): GdImage
    {
        $size = 480 * self::SS;
        $im = $this->canvas($size, $size, $s['bg']);
        $c = fn (string $hex) => $this->color($im, $hex);
        $u = fn (float $v) => (int) round($v * self::SS);

        // Long hair falling behind the shoulders.
        if ($s['long']) {
            imagefilledellipse($im, $u(240), $u(275), $u(290), $u(360), $c($s['hair']));
        }
        // Shoulders / shirt with a collar opening.
        imagefilledellipse($im, $u(240), $u(540), $u(440), $u(330), $c($s['shirt']));
        imagefilledpolygon($im, [$u(196), $u(388), $u(284), $u(388), $u(240), $u(446)], $c('#ffffff'));
        // Neck.
        imagefilledrectangle($im, $u(200), $u(300), $u(280), $u(398), $c($this->shade($s['skin'], -20)));
        // Hair volume behind the head, then ears and face.
        imagefilledellipse($im, $u(240), $u(196), $u(236), $u(214), $c($s['hair']));
        imagefilledellipse($im, $u(138), $u(240), $u(30), $u(46), $c($s['skin']));
        imagefilledellipse($im, $u(342), $u(240), $u(30), $u(46), $c($s['skin']));
        imagefilledellipse($im, $u(240), $u(236), $u(200), $u(232), $c($s['skin']));
        // Hairline over the forehead.
        imagefilledarc($im, $u(240), $u(214), $u(212), $u(196), 180, 360, $c($s['hair']), IMG_ARC_PIE);
        // Simple friendly face.
        imagefilledellipse($im, $u(204), $u(246), $u(15), $u(17), $c('#1f1a17'));
        imagefilledellipse($im, $u(276), $u(246), $u(15), $u(17), $c('#1f1a17'));
        imagesetthickness($im, $u(5));
        imagearc($im, $u(240), $u(290), $u(60), $u(36), 20, 160, $c('#3b2416'));

        return $this->downsample($im, 480, 480);
    }

    private function emblem(string $fill, string $ring): GdImage
    {
        $size = 400 * self::SS;
        $im = $this->canvas($size, $size, null);
        $u = fn (float $v) => (int) round($v * self::SS);

        imagefilledellipse($im, $u(200), $u(200), $u(392), $u(392), $this->color($im, $ring));
        imagefilledellipse($im, $u(200), $u(200), $u(350), $u(350), $this->color($im, $fill));
        imagesetthickness($im, $u(4));
        imageellipse($im, $u(200), $u(200), $u(320), $u(320), $this->color($im, $ring));

        // Open book.
        $white = $this->color($im, '#ffffff');
        imagefilledpolygon($im, [$u(110), $u(170), $u(195), $u(190), $u(195), $u(285), $u(110), $u(265)], $white);
        imagefilledpolygon($im, [$u(290), $u(170), $u(205), $u(190), $u(205), $u(285), $u(290), $u(265)], $white);
        // Star above the book.
        $star = [];
        for ($i = 0; $i < 10; $i++) {
            $r = $i % 2 === 0 ? 46 : 19;
            $a = deg2rad(-90 + $i * 36);
            $star[] = $u(200 + $r * cos($a));
            $star[] = $u(118 + $r * sin($a));
        }
        imagefilledpolygon($im, $star, $this->color($im, $ring));

        return $this->downsample($im, 400, 400);
    }

    private function signature(): GdImage
    {
        $w = 600 * self::SS;
        $h = 200 * self::SS;
        $im = $this->canvas($w, $h, null);
        $font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSerif-BoldItalic.ttf');
        imagettftext($im, 50 * self::SS, -4, 30 * self::SS, 112 * self::SS, $this->color($im, '#1d3fb8'), $font, 'A. Mwakyusa');
        imagesetthickness($im, 5 * self::SS);
        imagearc($im, 300 * self::SS, 160 * self::SS, 500 * self::SS, 40 * self::SS, 5, 175, $this->color($im, '#1d3fb8'));

        return $this->downsample($im, 600, 200);
    }

    /** Abstract campus-free header: soft diagonal bands. */
    private function header(): GdImage
    {
        $w = 1400;
        $h = 400;
        $im = $this->canvas($w, $h, '#5b8bd6');
        for ($i = -4; $i < 14; $i++) {
            $x = $i * 140;
            $color = $i % 2 === 0 ? '#6f9be0' : '#4f7fcc';
            imagefilledpolygon($im, [$x, 0, $x + 140, 0, $x + 340, $h, $x + 200, $h], $this->color($im, $color));
        }
        imagefilledellipse($im, 1180, 60, 520, 520, $this->color($im, '#86aeea'));
        imagefilledellipse($im, 200, 420, 600, 300, $this->color($im, '#3f6fbf'));

        return $im;
    }

    /** Faint dotted pattern used behind card details. */
    private function watermark(): GdImage
    {
        $w = 1400;
        $h = 900;
        $im = $this->canvas($w, $h, '#f4f6f8');
        $dot = $this->color($im, '#e3e8ee');
        for ($y = 20; $y < $h; $y += 42) {
            for ($x = ($y / 42) % 2 ? 41 : 20; $x < $w; $x += 42) {
                imagefilledellipse($im, (int) $x, $y, 9, 9, $dot);
            }
        }

        return $im;
    }

    private function canvas(int $w, int $h, ?string $background): GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        imagefill($im, 0, 0, $background ? $this->color($im, $background) : imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);

        return $im;
    }

    private function downsample(GdImage $src, int $w, int $h): GdImage
    {
        $dst = imagecreatetruecolor($w, $h);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));

        return $dst;
    }

    private function color(GdImage $im, string $hex): int
    {
        return imagecolorallocate($im, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
    }

    private function shade(string $hex, int $amount): string
    {
        $parts = array_map(fn ($i) => max(0, min(255, hexdec(substr($hex, $i, 2)) + $amount)), [1, 3, 5]);

        return sprintf('#%02x%02x%02x', ...$parts);
    }

    private function png(GdImage $im): string
    {
        ob_start();
        imagepng($im, null, 6);

        return (string) ob_get_clean();
    }
}
