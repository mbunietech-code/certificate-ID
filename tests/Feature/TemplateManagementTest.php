<?php

namespace Tests\Feature;

use App\Models\CertificateTemplate;
use App\Models\IdCardTemplate;
use App\Models\Role;
use Illuminate\Http\UploadedFile;
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
