<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * O-Level photo-header ID cards (e.g. "BWM O-Level Student ID") print the class
 * on two lines: "Form" on top and "I - IV" below, via the {class_stacked} field.
 * Only templates built from that design are touched: a "Class:" label with a
 * single-line {class_stream} value directly under it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rewrite('{class_stream}', ['content' => '{class_stacked}', 'y' => 39.6, 'h' => 4.4, 'lineHeight' => 1.0]);
    }

    public function down(): void
    {
        $this->rewrite('{class_stacked}', ['content' => '{class_stream}', 'y' => 40.2, 'h' => 2.6, 'lineHeight' => 1.15]);
    }

    /** @param  array<string, mixed>  $changes */
    private function rewrite(string $from, array $changes): void
    {
        DB::table('id_card_templates')->orderBy('id')->each(function (object $template) use ($from, $changes) {
            $design = json_decode($template->design_json, true);
            if (! is_array($design)) {
                return;
            }

            $changed = false;
            foreach ($design as $side => $content) {
                $elements = $content['elements'] ?? [];
                $label = collect($elements)->first(fn ($e) => ($e['type'] ?? null) === 'text' && ($e['content'] ?? null) === 'Class:');
                if (! $label) {
                    continue;
                }

                foreach ($elements as $i => $element) {
                    $isValueUnderLabel = ($element['type'] ?? null) === 'text'
                        && ($element['content'] ?? null) === $from
                        && abs(($element['x'] ?? 0) - ($label['x'] ?? 0)) < 0.5
                        && ($element['y'] ?? 0) > ($label['y'] ?? 0)
                        && ($element['y'] ?? 0) - ($label['y'] ?? 0) < 4;

                    if ($isValueUnderLabel) {
                        $design[$side]['elements'][$i] = array_merge($element, $changes);
                        $changed = true;
                    }
                }
            }

            if ($changed) {
                DB::table('id_card_templates')->where('id', $template->id)->update(['design_json' => json_encode($design), 'updated_at' => now()]);
            }
        });
    }
};
