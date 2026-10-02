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
    public function store(UploadedFile $file, string $directory, int $maxDimension = 800, bool $keepTransparency = false): string
    {
        $data = file_get_contents($file->getRealPath());
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

        $image = $this->resize($source, $maxDimension, $keepTransparency);

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

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
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
