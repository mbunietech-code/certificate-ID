<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Key/value system settings with defaults, cached as a single array. */
class Settings
{
    private const CACHE_KEY = 'system_settings:all';

    public const DEFAULTS = [
        'system_name' => 'School ID & Certificate System',
        'system_logo' => null,
        'organization_name' => null,
        'default_paper_size' => 'A4',
        'default_id_width_mm' => '85.60',
        'default_id_height_mm' => '53.98',
        'default_id_dpi' => '300',
        'default_certificate_paper' => 'A4',
        'default_certificate_orientation' => 'landscape',
        'verification_base_url' => null, // null = APP_URL
        'date_format' => 'd/m/Y',
        'default_student_id_format' => '{CODE}/{YEAR}/{SEQ:4}',
        'default_staff_id_format' => '{CODE}/STF/{YEAR}/{SEQ:4}',
        'default_certificate_number_format' => '{CODE}/CERT/{YEAR}/{SEQ:5}',
        'id_card_validity_months' => '12',
        'queue_threshold' => '25',
    ];

    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return $all[$key] ?? $default ?? (self::DEFAULTS[$key] ?? null);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $stored = Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('system_settings')) {
                return [];
            }

            return SystemSetting::query()->pluck('value', 'key')->all();
        });

        return $this->loaded = array_merge(self::DEFAULTS, array_filter($stored, fn ($v) => $v !== null && $v !== ''));
    }

    /** @param  array<string, mixed>  $values */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }
}
