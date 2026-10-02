<?php

namespace Tests;

use App\Models\AcademicYear;
use App\Models\CertificateTemplate;
use App\Models\IdCardTemplate;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\Documents\DesignPresets;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('public');
        Storage::fake('local');
    }

    /**
     * Create a school with a current academic year. Data is created without a
     * logged-in user, so call this before actingAs().
     */
    protected function school(string $code, ?string $name = null): School
    {
        $school = School::factory()->create(['school_code' => $code, 'name' => $name ?? "{$code} Secondary School"]);
        AcademicYear::factory()->create(['school_id' => $school->id, 'name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);

        return $school;
    }

    protected function userFor(?School $school, string $role = Role::SCHOOL_ADMIN): User
    {
        return $school
            ? User::factory()->forSchool($school, $role)->create()
            : User::factory()->superAdmin()->create();
    }

    protected function idTemplate(?School $school, string $type = IdCardTemplate::TYPE_STUDENT, string $name = 'Card'): IdCardTemplate
    {
        $preset = DesignPresets::get($type === IdCardTemplate::TYPE_STAFF ? 'staff_id_portrait' : 'student_id_classic');

        return $this->asTenant($school, fn () => IdCardTemplate::create([
            'name' => $name, 'type' => $type, 'width_mm' => $preset['width'], 'height_mm' => $preset['height'],
            'orientation' => $preset['orientation'], 'dpi' => 300, 'has_back' => true, 'design_json' => $preset['design'], 'status' => 'active',
        ]));
    }

    protected function certificateTemplate(?School $school, string $name = 'Certificate'): CertificateTemplate
    {
        $preset = DesignPresets::get('certificate_completion');

        return $this->asTenant($school, fn () => CertificateTemplate::create([
            'name' => $name, 'paper_size' => 'A4', 'orientation' => 'landscape', 'width_mm' => 297, 'height_mm' => 210,
            'design_json' => $preset['design'], 'default_title' => 'Certificate of Completion', 'status' => 'active',
        ]));
    }

    /** Run a callback scoped to a school, or unscoped (global records) when null. */
    protected function asTenant(?School $school, \Closure $callback): mixed
    {
        $tenant = app(TenantContext::class);

        return $school ? $tenant->runFor($school->id, $callback) : $tenant->withoutScope($callback);
    }

    protected function currentYear(School $school): AcademicYear
    {
        return AcademicYear::forSchool($school->id)->where('is_current', true)->firstOrFail();
    }
}
