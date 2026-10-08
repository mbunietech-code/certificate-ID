<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Models\IdCardTemplate;
use App\Models\Role;
use App\Models\Student;
use App\Services\Documents\DesignPresets;
use App\Services\Documents\DocumentData;
use App\Services\Documents\TemplateRenderer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateManagementTest extends TestCase
{
    public function test_school_admin_creates_designs_previews_and_deletes_an_id_template(): void
    {
        $school = $this->school('BWM');
        $this->actingAs($this->userFor($school));

        $this->post(route('templates.id-cards.store'), [
            'name' => 'A-Level card', 'type' => 'STUDENT_ID', 'width_mm' => 85.6, 'height_mm' => 53.98, 'dpi' => 300,
            'has_back' => 1, 'preset' => 'photo_header_alevel',
        ])->assertRedirect();
        $template = IdCardTemplate::firstOrFail();
        $this->assertSame($school->id, $template->school_id);
        $this->assertNotEmpty($template->design_json['front']['elements']);

        $this->get(route('templates.id-cards.design', $template))->assertOk()->assertSee('designer-config', false);
        $this->get(route('templates.id-cards.preview', $template))->assertOk()->assertSee($school->name);
        $this->get(route('templates.id-cards.preview', [$template, 'format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');

        $this->delete(route('templates.id-cards.destroy', $template))->assertRedirect();
        $this->assertSoftDeleted($template);
        $this->assertDatabaseHas('audit_logs', ['action' => 'template.deleted']);
    }

    public function test_saved_designs_are_sanitized(): void
    {
        $school = $this->school('BWM');
        $template = $this->idTemplate($school);
        $this->actingAs($this->userFor($school));

        $this->putJson(route('templates.id-cards.design.save', $template), ['design' => [
            'front' => [
                'background' => ['color' => 'red;background:url(x)', 'image' => '../../.env'],
                'elements' => [
                    ['id' => 'a', 'type' => 'text', 'x' => 5, 'y' => 5, 'w' => 30, 'h' => 5, 'content' => '<script>alert(1)</script>{full_name}',
                        'fontFamily' => 'Comic Sans', 'color' => 'javascript:alert(1)', 'onclick' => 'evil()', 'fontSize' => 9999],
                    ['id' => 'b', 'type' => 'iframe', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10],
                    ['id' => 'c', 'type' => 'image', 'x' => 0, 'y' => 0, 'w' => 10, 'h' => 10, 'source' => 'custom', 'src' => 'C:/Windows/win.ini'],
                ],
            ],
        ]])->assertOk();

        $design = $template->fresh()->design_json;
        $this->assertSame('#ffffff', $design['front']['background']['color']);
        $this->assertNull($design['front']['background']['image']);
        $this->assertCount(2, $design['front']['elements'], 'Unknown element types are dropped.');
        $text = $design['front']['elements'][0];
        $this->assertSame('helvetica', $text['fontFamily']);
        $this->assertSame('#000000', $text['color']);
        $this->assertEquals(200, $text['fontSize'], 'Font size is clamped.');
        $this->assertArrayNotHasKey('onclick', $text);
        $this->assertNull($design['front']['elements'][1]['src']);
        $this->assertArrayHasKey('back', $design);

        // Content is stored as text and always escaped when rendered.
        $this->get(route('templates.id-cards.preview', $template))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_template_assets_are_uploaded_into_the_school_folder(): void
    {
        $school = $this->school('BWM');
        $this->actingAs($this->userFor($school));

        $response = $this->postJson(route('templates.assets.store'), ['image' => UploadedFile::fake()->image('header.jpg', 2000, 800), 'kind' => 'background'])->assertOk();
        $this->assertStringStartsWith("templates/{$school->id}/", $response->json('path'));
        $this->assertStringEndsWith('.jpg', $response->json('path'));
    }

    public function test_super_admin_creates_global_certificate_template_in_all_schools_mode(): void
    {
        $this->actingAs($this->userFor(null));

        $this->post(route('templates.certificates.store'), [
            'name' => 'Global merit', 'paper_size' => 'A4', 'orientation' => 'portrait', 'preset' => 'certificate_merit',
            'default_title' => 'Certificate of Merit',
        ])->assertRedirect();

        $template = CertificateTemplate::withoutGlobalScopes()->firstOrFail();
        $this->assertNull($template->school_id);
        $this->assertEquals([210, 297], [$template->width_mm, $template->height_mm]);
    }

    public function test_olevel_card_prints_form_above_the_class_range(): void
    {
        $school = $this->school('BWM');
        $student = Student::factory()->for($school)->create(['level' => 'O-Level', 'class_name' => 'Form I - IV', 'stream' => null]);

        $html = app(TemplateRenderer::class)->renderSide(
            DesignPresets::photoHeader('olevel')['front'], 85.6, 53.98,
            DocumentData::sample('id_card', 'student', $school, $student), 'web',
        );

        $this->assertMatchesRegularExpression('/Form<br>\s*I - IV/', $html);
    }

    public function test_migration_stacks_the_class_on_existing_olevel_templates(): void
    {
        $school = $this->school('BWM');
        $design = DesignPresets::photoHeader('olevel');
        foreach ($design['front']['elements'] as $i => $element) {
            if (($element['content'] ?? null) === '{class_stacked}') {
                $design['front']['elements'][$i] = array_merge($element, ['content' => '{class_stream}', 'y' => 40.2, 'h' => 2.6, 'lineHeight' => 1.15]);
            }
        }
        $template = $this->idTemplate($school, IdCardTemplate::TYPE_STUDENT, 'BWM O-Level Student ID');
        $template->forceFill(['design_json' => $design])->save();

        (include database_path('migrations/2026_10_07_061437_stack_class_on_olevel_id_templates.php'))->up();

        $value = collect(IdCardTemplate::withoutGlobalScopes()->find($template->id)->design_json['front']['elements'])
            ->firstWhere('content', '{class_stacked}');
        $this->assertNotNull($value, 'The class value is switched to the stacked field.');
        $this->assertEquals(4.4, $value['h']);
        $this->assertNull(collect(IdCardTemplate::withoutGlobalScopes()->find($template->id)->design_json['front']['elements'])
            ->firstWhere('content', '{class_stream}'));
    }

    public function test_bwm_student_cards_use_the_school_back_image_as_is(): void
    {
        $bwm = $this->school('BWM');
        $bng = $this->school('BNG');
        $bwmCard = $this->idTemplate($bwm, IdCardTemplate::TYPE_STUDENT, 'BWM O-Level Student ID');
        $bngCard = $this->idTemplate($bng, IdCardTemplate::TYPE_STUDENT, 'Bangulo card');
        $frontBefore = $bwmCard->design_json['front'];

        (include database_path('migrations/2026_10_07_070413_use_school_back_design_on_bwm_id_cards.php'))->up();

        $back = IdCardTemplate::withoutGlobalScopes()->find($bwmCard->id)->design_json['back'];
        $this->assertSame("templates/{$bwm->id}/bwm-back.jpg", $back['background']['image']);
        $this->assertSame([], $back['elements']);
        $this->assertSame($frontBefore, IdCardTemplate::withoutGlobalScopes()->find($bwmCard->id)->design_json['front'], 'The front is untouched.');
        Storage::disk('public')->assertExists("templates/{$bwm->id}/bwm-back.jpg");
        $this->assertNotEmpty(IdCardTemplate::withoutGlobalScopes()->find($bngCard->id)->design_json['back']['elements'], 'Other schools keep their back.');

        $this->actingAs($this->userFor($bwm))->get(route('templates.id-cards.preview', $bwmCard))
            ->assertOk()->assertSee("templates/{$bwm->id}/bwm-back.jpg");
    }

    public function test_hazy_bwm_card_photos_are_made_vivid(): void
    {
        $bwm = $this->school('BWM');
        // A pale, low-contrast photo: a hazy grey gradient (120..230) with a muted green patch.
        $hazy = imagecreatetruecolor(60, 40);
        for ($x = 0; $x < 60; $x++) {
            $v = 120 + (int) round($x / 59 * 110);
            imageline($hazy, $x, 0, $x, 19, imagecolorallocate($hazy, $v, $v, $v));
        }
        imagefilledrectangle($hazy, 0, 20, 59, 39, imagecolorallocate($hazy, 140, 175, 130));
        ob_start();
        imagejpeg($hazy, null, 95);
        Storage::disk('public')->put("templates/{$bwm->id}/bwm-header.jpg", ob_get_clean());

        $template = $this->idTemplate($bwm, IdCardTemplate::TYPE_STUDENT, 'BWM A-Level Student ID');
        $template->forceFill(['design_json' => DesignPresets::photoHeader('alevel', "templates/{$bwm->id}/bwm-header.jpg")])->save();

        (include database_path('migrations/2026_10_07_070915_make_bwm_id_card_photos_vivid.php'))->up();

        $vividPath = "templates/{$bwm->id}/bwm-header-vivid.jpg";
        Storage::disk('public')->assertExists($vividPath);
        Storage::disk('public')->assertExists("templates/{$bwm->id}/bwm-header.jpg");
        $this->assertStringContainsString($vividPath, json_encode(IdCardTemplate::withoutGlobalScopes()->find($template->id)->design_json, JSON_UNESCAPED_SLASHES));

        // The muted green (+35 over red/blue) becomes strongly green, and the haze spans dark → light.
        $vivid = imagecreatefromstring(Storage::disk('public')->get($vividPath));
        $green = imagecolorat($vivid, 30, 30);
        $this->assertGreaterThan(80, (($green >> 8) & 255) - max(($green >> 16) & 255, $green & 255), 'Colours are boosted.');
        $this->assertLessThan(30, imagecolorat($vivid, 1, 10) & 255, 'Darkest haze becomes near black.');
        $this->assertGreaterThan(225, imagecolorat($vivid, 58, 10) & 255, 'Brightest haze becomes near white.');
    }

    public function test_registrar_can_view_but_not_manage_templates(): void
    {
        $school = $this->school('BWM');
        $template = $this->idTemplate($school);
        $this->actingAs($this->userFor($school, Role::REGISTRAR));

        $this->get(route('templates.id-cards.index'))->assertOk();
        $this->get(route('templates.id-cards.design', $template))->assertForbidden();
        $this->putJson(route('templates.id-cards.design.save', $template), ['design' => []])->assertForbidden();
    }
}
