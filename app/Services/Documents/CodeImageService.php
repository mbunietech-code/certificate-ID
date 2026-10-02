<?php

namespace App\Services\Documents;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Writer;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Throwable;

/** QR codes and Code128 barcodes as PNG data URIs (pure GD, works in dompdf and browsers). */
class CodeImageService
{
    /** @var array<string, string> */
    private array $memo = [];

    public function qr(string $data, string $color = '#000000', int $sizePx = 360): string
    {
        $key = "qr|{$data}|{$color}|{$sizePx}";

        return $this->memo[$key] ??= $this->makeQr($data, $color, $sizePx);
    }

    public function barcode(string $value, string $color = '#000000'): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $key = "bc|{$value}|{$color}";

        if (! array_key_exists($key, $this->memo)) {
            try {
                $png = (new BarcodeGeneratorPNG)->getBarcode($value, BarcodeGeneratorPNG::TYPE_CODE_128, 3, 90, $this->rgb($color));
                $this->memo[$key] = 'data:image/png;base64,'.base64_encode($png);
            } catch (Throwable $e) {
                report($e);
                $this->memo[$key] = null;
            }
        }

        return $this->memo[$key];
    }

    private function makeQr(string $data, string $color, int $sizePx): string
    {
        [$r, $g, $b] = $this->rgb($color);
        $fill = Fill::uniformColor(new Rgb(255, 255, 255), new Rgb($r, $g, $b));
        $writer = new Writer(new GDLibRenderer($sizePx, 1, 'png', 9, $fill));
        $png = $writer->writeString($data, Encoder::DEFAULT_BYTE_MODE_ENCODING, ErrorCorrectionLevel::M());

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /** @return array{int, int, int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return [0, 0, 0];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
