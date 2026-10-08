<?php

use App\Services\Documents\DesignPresets;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Benjamin William Mkapa High School (code BWM) prints its own finished back
 * design ("If found please return to…", address and headmaster stamp). It is
 * used as is: the whole back of both BWM student ID templates is that image.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->bwmSchoolIds() as $schoolId) {
            $path = "templates/{$schoolId}/bwm-back.jpg";
            $disk = Storage::disk('public');
            if (! $disk->exists($path)) {
                $disk->put($path, file_get_contents(database_path('seeders/assets/bwm/back-card.jpg')));
            }

            $this->eachStudentTemplate($schoolId, fn (array $design) => ['back' => DesignPresets::imageSide($path)] + $design);
        }
    }

    public function down(): void
    {
        foreach ($this->bwmSchoolIds() as $schoolId) {
            $this->eachStudentTemplate($schoolId, function (array $design, object $template) {
                $variant = str_contains(strtolower($template->name), 'o-level') ? 'olevel' : 'alevel';

                return ['back' => DesignPresets::photoHeader($variant)['back']] + $design;
            });
        }
    }

    /** @return array<int, int> */
    private function bwmSchoolIds(): array
    {
        return DB::table('schools')->where('school_code', 'BWM')->pluck('id')->all();
    }

    private function eachStudentTemplate(int $schoolId, Closure $change): void
    {
        DB::table('id_card_templates')->where('school_id', $schoolId)->where('type', 'STUDENT_ID')->whereNull('deleted_at')
            ->orderBy('id')->each(function (object $template) use ($change) {
                $design = json_decode($template->design_json, true) ?: [];
                DB::table('id_card_templates')->where('id', $template->id)->update([
                    'design_json' => json_encode($change($design, $template)),
                    'has_back' => true,
                    'updated_at' => now(),
                ]);
            });
    }
};
