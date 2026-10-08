<?php

use App\Services\ImageService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The BWM campus header photo (front) and the school's back design were pale
 * and hazy when printed. Vivid copies are created and both BWM student ID
 * templates point at them; the original files are kept so down() can revert.
 */
return new class extends Migration
{
    private const FILES = ['bwm-header.jpg' => 'bwm-header-vivid.jpg', 'bwm-back.jpg' => 'bwm-back-vivid.jpg'];

    public function up(): void
    {
        $disk = Storage::disk('public');
        $images = app(ImageService::class);

        foreach ($this->bwmSchoolIds() as $schoolId) {
            $map = [];
            foreach (self::FILES as $original => $vivid) {
                $from = "templates/{$schoolId}/{$original}";
                $to = "templates/{$schoolId}/{$vivid}";
                if (! $disk->exists($from)) {
                    continue;
                }
                if (! $disk->exists($to)) {
                    $disk->put($to, $images->enhanceColors($disk->get($from)));
                }
                $map[$from] = $to;
            }
            $this->repoint($schoolId, $map);
        }
    }

    public function down(): void
    {
        foreach ($this->bwmSchoolIds() as $schoolId) {
            $map = [];
            foreach (self::FILES as $original => $vivid) {
                $map["templates/{$schoolId}/{$vivid}"] = "templates/{$schoolId}/{$original}";
            }
            $this->repoint($schoolId, $map);
        }
    }

    /** @return array<int, int> */
    private function bwmSchoolIds(): array
    {
        return DB::table('schools')->where('school_code', 'BWM')->pluck('id')->all();
    }

    /** @param  array<string, string>  $map  old image path => new image path */
    private function repoint(int $schoolId, array $map): void
    {
        if ($map === []) {
            return;
        }

        DB::table('id_card_templates')->where('school_id', $schoolId)->orderBy('id')->each(function (object $template) use ($map) {
            $design = json_decode($template->design_json, true);
            if (! is_array($design)) {
                return;
            }

            $changed = false;
            array_walk_recursive($design, function (&$value) use ($map, &$changed) {
                if (is_string($value) && isset($map[$value])) {
                    $value = $map[$value];
                    $changed = true;
                }
            });

            if ($changed) {
                DB::table('id_card_templates')->where('id', $template->id)->update(['design_json' => json_encode($design), 'updated_at' => now()]);
            }
        });
    }
};
