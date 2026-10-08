<?php

namespace App\Console\Commands;

use App\Models\Staff;
use App\Models\Student;
use App\Services\ImageService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Resize photos stored before the 455 × 488 rule (or added by imports) to the ID photo size. */
class NormalizePassportPhotos extends Command
{
    protected $signature = 'photos:normalize {--dry-run : Only list photos that would be resized}';

    protected $description = 'Crop and resize all student/staff photos to exactly 455 × 488 px';

    public function handle(ImageService $images, TenantContext $tenant): int
    {
        $resized = 0;
        $skipped = 0;

        $tenant->withoutScope(function () use ($images, &$resized, &$skipped) {
            foreach ([Student::class, Staff::class] as $model) {
                $model::withTrashed()->whereNotNull('photo_path')->lazyById(200)->each(function ($person) use ($images, &$resized, &$skipped) {
                    $disk = Storage::disk('public');
                    $size = $disk->exists($person->photo_path) ? @getimagesizefromstring($disk->get($person->photo_path)) : false;

                    if (! $size || ($size[0] === ImageService::PASSPORT_WIDTH && $size[1] === ImageService::PASSPORT_HEIGHT)) {
                        $skipped++;

                        return;
                    }

                    $this->line("{$person->full_name}: {$size[0]}×{$size[1]} → ".ImageService::PASSPORT_WIDTH.'×'.ImageService::PASSPORT_HEIGHT);
                    if ($this->option('dry-run')) {
                        $resized++;

                        return;
                    }

                    try {
                        $person->forceFill(['photo_path' => $images->normalizePassportPhoto($person->photo_path)])->saveQuietly();
                        $resized++;
                    } catch (Throwable $e) {
                        $this->warn("  skipped: {$e->getMessage()}");
                        $skipped++;
                    }
                });
            }
        });

        $this->info(($this->option('dry-run') ? 'Would resize' : 'Resized')." {$resized} photo(s); {$skipped} already correct or unreadable.");

        return self::SUCCESS;
    }
}
