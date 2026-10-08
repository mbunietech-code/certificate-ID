<?php

namespace App\Services;

use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Stores uploaded images safely: the file is decoded and re-encoded with GD,
 * which strips metadata and any non-image payload, and is resized to a sane
 * maximum. Only JPEG/PNG/WEBP input is accepted (never SVG).
 */
class ImageService
{
    public const UPLOAD_RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

    /**
     * @param  string  $directory  e.g. "students/3"
     * @param  bool  $keepTransparency  store PNG (logos, signatures, stamps) instead of JPEG (photos)
     */
    /** Every ID photo (student or staff) is stored at exactly this size in pixels. */
    public const PASSPORT_WIDTH = 455;

    public const PASSPORT_HEIGHT = 488;

    public function store(UploadedFile $file, string $directory, int $maxDimension = 800, bool $keepTransparency = false): string
    {
        $image = $this->resize($this->decode(file_get_contents($file->getRealPath())), $maxDimension, $keepTransparency);

        ob_start();
        if ($keepTransparency) {
            imagepng($image, null, 6);
            $ext = 'png';
        } else {
            imagejpeg($image, null, 88);
            $ext = 'jpg';
        }
        $binary = ob_get_clean();

        $path = trim($directory, '/').'/'.Str::random(32).'.'.$ext;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /**
     * Store an ID photo cropped to the 455:488 passport shape and resized to
     * exactly 455 × 488 px, whatever size or shape was uploaded or captured.
     */
    public function storePassportPhoto(UploadedFile $file, string $directory): string
    {
        return $this->putPassport(file_get_contents($file->getRealPath()), $directory);
    }

    /** Re-process an already stored photo to 455 × 488 px; returns the new path (the old file is removed). */
    public function normalizePassportPhoto(string $path): ?string
    {
        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            return null;
        }

        $newPath = $this->putPassport($disk->get($path), dirname($path));
        $disk->delete($path);

        return $newPath;
    }

    /**
     * Make a washed-out (hazy) photo vivid for printing: per-channel auto levels
     * (darkest 1% → black, brightest 0.5% → white), a mild gamma for depth and a
     * saturation boost. Returns JPEG bytes.
     */
    public function enhanceColors(string $data, float $saturation = 1.6, float $gamma = 1.1): string
    {
        $source = $this->decode($data);
        $w = imagesx($source);
        $h = imagesy($source);

        $samples = [[], [], []];
        for ($y = 0; $y < $h; $y += 3) {
            for ($x = 0; $x < $w; $x += 3) {
                $c = imagecolorat($source, $x, $y);
                $samples[0][] = ($c >> 16) & 255;
                $samples[1][] = ($c >> 8) & 255;
                $samples[2][] = $c & 255;
            }
        }

        $lut = [];
        foreach ($samples as $channel => $values) {
            sort($values);
            $low = $values[(int) (count($values) * 0.01)];
            $high = max($low + 1, $values[(int) (count($values) * 0.995)]);
            for ($v = 0; $v < 256; $v++) {
                $lut[$channel][$v] = 255 * (max(0, min(1, ($v - $low) / ($high - $low))) ** $gamma);
            }
        }

        $target = imagecreatetruecolor($w, $h);
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $c = imagecolorat($source, $x, $y);
                $r = $lut[0][($c >> 16) & 255];
                $g = $lut[1][($c >> 8) & 255];
                $b = $lut[2][$c & 255];
                $luma = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                imagesetpixel($target, $x, $y,
                    ((int) max(0, min(255, $luma + ($r - $luma) * $saturation)) << 16)
                    | ((int) max(0, min(255, $luma + ($g - $luma) * $saturation)) << 8)
                    | (int) max(0, min(255, $luma + ($b - $luma) * $saturation)));
            }
        }

        ob_start();
        imagejpeg($target, null, 92);

        return (string) ob_get_clean();
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    /** Only real JPEG/PNG/WEBP rasters are accepted; GD re-encoding strips everything else. */
    private function decode(string $data): GdImage
    {
        $info = @getimagesizefromstring($data);

        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            throw ValidationException::withMessages(['image' => 'The uploaded file is not a valid JPG, PNG or WEBP image.']);
        }
        if ($info[0] * $info[1] > 40_000_000) {
            throw ValidationException::withMessages(['image' => 'The image dimensions are too large.']);
        }

        $source = @imagecreatefromstring($data);
        if (! $source instanceof GdImage) {
            throw ValidationException::withMessages(['image' => 'The image could not be read.']);
        }

        return $source;
    }

    private function putPassport(string $data, string $directory): string
    {
        $source = $this->decode($data);
        $w = imagesx($source);
        $h = imagesy($source);
        $ratio = self::PASSPORT_WIDTH / self::PASSPORT_HEIGHT;

        // Largest 455:488 area of the image: centred horizontally, and kept towards the
        // top vertically (a quarter of the excess is cut above) so heads are never cut off.
        if ($w / $h > $ratio) {
            $cropW = (int) round($h * $ratio);
            $cropH = $h;
            $x = (int) round(($w - $cropW) / 2);
            $y = 0;
        } else {
            $cropW = $w;
            $cropH = (int) round($w / $ratio);
            $x = 0;
            $y = (int) round(($h - $cropH) / 4);
        }

        $target = imagecreatetruecolor(self::PASSPORT_WIDTH, self::PASSPORT_HEIGHT);
        imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        imagecopyresampled($target, $source, 0, 0, $x, $y, self::PASSPORT_WIDTH, self::PASSPORT_HEIGHT, $cropW, $cropH);

        ob_start();
        imagejpeg($target, null, 90);
        $path = trim($directory, '/').'/'.Str::random(32).'.jpg';
        Storage::disk('public')->put($path, (string) ob_get_clean());

        return $path;
    }

    private function resize(GdImage $source, int $max, bool $alpha): GdImage
    {
        $w = imagesx($source);
        $h = imagesy($source);
        $scale = min(1, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $target = imagecreatetruecolor($nw, $nh);
        if ($alpha) {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
        } else {
            imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
        }
        imagecopyresampled($target, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);

        return $target;
    }
}
