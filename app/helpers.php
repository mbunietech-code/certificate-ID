<?php

use App\Services\Settings;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return app(Settings::class)->get($key, $default);
    }
}

if (! function_exists('tenant')) {
    function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }
}

if (! function_exists('format_date')) {
    function format_date(CarbonInterface|string|null $date, ?string $format = null): string
    {
        if (! $date) {
            return '';
        }
        $date = is_string($date) ? Carbon::parse($date) : $date;

        return $date->format($format ?? setting('date_format', 'd/m/Y'));
    }
}

if (! function_exists('verification_url')) {
    /** Public verification URL embedded in QR codes. Base is configurable in system settings. */
    function verification_url(string $type, string $code): string
    {
        $base = rtrim((string) (setting('verification_base_url') ?: config('app.url')), '/');

        return "{$base}/verify/{$type}/{$code}";
    }
}
